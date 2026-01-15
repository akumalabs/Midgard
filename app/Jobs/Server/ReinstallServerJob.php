<?php

namespace App\Jobs\Server;

use App\Models\Deployment;
use App\Models\DeploymentStep;
use App\Models\Server;
use App\Models\Template;
use App\Repositories\Proxmox\Server\ProxmoxCloudinitRepository;
use App\Repositories\Proxmox\Server\ProxmoxConfigRepository;
use App\Repositories\Proxmox\Server\ProxmoxPowerRepository;
use App\Repositories\Proxmox\Server\ProxmoxServerRepository;
use App\Services\Proxmox\ProxmoxApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Reinstall a server - following Convoy pattern with deployment tracking
 */
class ReinstallServerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 900;

    public function __construct(
        protected Server $server,
        protected Template $template,
        protected string $password,
        protected ?int $deploymentId = null,
    ) {}

    public function handle(): void
    {
        $deployment = $this->deploymentId 
            ? Deployment::find($this->deploymentId) 
            : null;

        try {
            logger()->info("Starting reinstall for server {$this->server->vmid}");
            
            // Update server status
            $this->server->update(['status' => 'reinstalling']);
            
            // Start deployment
            $deployment?->start();
            
            $client = new ProxmoxApiClient($this->server->node);
            $serverRepo = (new ProxmoxServerRepository($client))->setServer($this->server);
            $powerRepo = (new ProxmoxPowerRepository($client))->setServer($this->server);
            $configRepo = (new ProxmoxConfigRepository($client))->setServer($this->server);
            $cloudinitRepo = (new ProxmoxCloudinitRepository($client))->setServer($this->server);
            
            // Step 1: Stop VM
            $this->runStep($deployment, 'Stopping VM', function() use ($powerRepo) {
                try {
                    $powerRepo->kill();
                } catch (\Exception $e) {
                    logger()->info("VM might already be stopped: " . $e->getMessage());
                }
            });
            
            // Step 2: Wait for VM to stop and unlock
            $this->runStep($deployment, 'Deleting VM', function() use ($client, $serverRepo) {
                $serverRepo->waitForUnlock(60, 2);
                
                // Delete the old VM
                $client->deleteVM($this->server->vmid);
                
                // Wait for deletion to complete
                sleep(5);
            });
            
            // Step 3: Clone template
            $this->runStep($deployment, 'Cloning template', function() use ($client) {
                $client->cloneVM(
                    $this->template->vmid,
                    $this->server->vmid,
                    [
                        'name' => $this->server->name,
                        'target' => $this->server->node->cluster,
                        'storage' => $this->server->node->vm_storage ?? 'local-lvm',
                    ]
                );
                
                // Wait for clone to start
                sleep(3);
            });
            
            // Step 4: Configure server
            $this->runStep($deployment, 'Configuring server', function() use ($serverRepo, $configRepo, $client) {
                // Wait for clone to complete
                $serverRepo->waitForUnlock(120, 3);
                
                // Update VM config
                $configRepo->update([
                    'name' => $this->server->name,
                    'cores' => $this->server->cpu,
                    'memory' => (int) ($this->server->memory / 1048576),
                ]);
                
                // Resize disk if needed
                $diskSizeBytes = $this->server->disk;
                if ($diskSizeBytes > 0) {
                    try {
                        $client->resizeDisk($this->server->vmid, 'scsi0', $diskSizeBytes);
                    } catch (\Exception $e) {
                        logger()->warning("Failed to resize disk: " . $e->getMessage());
                    }
                }
            });
            
            // Step 5: Update password & cloud-init
            $this->runStep($deployment, 'Updating password', function() use ($client) {
                $cloudinitParams = [
                    'ciuser' => 'root',
                    'cipassword' => $this->password,
                ];
                
                // Get primary address
                $primaryAddress = $this->server->addresses()->where('is_primary', true)->first();
                if ($primaryAddress) {
                    $cloudinitParams['ipconfig0'] = "ip={$primaryAddress->address}/{$primaryAddress->cidr},gw={$primaryAddress->gateway}";
                }
                
                $client->updateVMConfig($this->server->vmid, $cloudinitParams);
            });
            
            // Step 6: Start VM
            $this->runStep($deployment, 'Starting VM', function() use ($powerRepo) {
                $powerRepo->start();
            });
            
            // Mark complete
            $this->server->update([
                'status' => 'running',
                'installed_at' => now(),
            ]);
            
            $deployment?->complete();
            
            logger()->info("Reinstall completed for server {$this->server->vmid}");
            
        } catch (\Exception $e) {
            logger()->error("Reinstall failed for server {$this->server->vmid}: " . $e->getMessage());
            
            $this->server->update(['status' => 'error']);
            $deployment?->fail($e->getMessage());
            
            throw $e;
        }
    }
    
    protected function runStep(?Deployment $deployment, string $stepName, callable $callback): void
    {
        $step = $deployment?->steps()->where('name', $stepName)->first();
        $step?->start();
        
        try {
            $callback();
            $step?->complete();
        } catch (\Exception $e) {
            $step?->fail($e->getMessage());
            throw $e;
        }
    }
}

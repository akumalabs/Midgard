<?php

namespace App\Jobs\Server;

use App\Models\Server;
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
 * ConfigureVmJob - Configure VM after clone (resize, cloud-init, network)
 * Following Convoy pattern for post-creation configuration
 */
class ConfigureVmJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(
        protected Server $server,
        protected ?string $password = null,
        protected array $addressIds = [],
    ) {}

    public function handle(): void
    {
        logger()->info("Configuring server {$this->server->vmid}", [
            'name' => $this->server->name,
            'password_set' => !empty($this->password),
            'address_ids' => $this->addressIds,
        ]);
        
        $client = new ProxmoxApiClient($this->server->node);
        $serverRepo = (new ProxmoxServerRepository($client))->setServer($this->server);
        $configRepo = (new ProxmoxConfigRepository($client))->setServer($this->server);
        $cloudinitRepo = (new ProxmoxCloudinitRepository($client))->setServer($this->server);
        $powerRepo = (new ProxmoxPowerRepository($client))->setServer($this->server);
        
        // Wait for VM to be unlocked (clone operation)
        logger()->info("Waiting for VM to be unlocked...");
        $serverRepo->waitForUnlock();
        
        // Update VM name and resources via config endpoint
        logger()->info("Updating VM config (name, cpu, memory)...");
        $vmConfig = [
            'name' => $this->server->name,
            'cores' => $this->server->cpu,
            'memory' => (int) ($this->server->memory / 1048576), // bytes to MB
            'agent' => 1, // Enable QEMU Guest Agent
        ];
        $configRepo->update($vmConfig);
        
        // Resize disk if needed
        $diskSizeBytes = $this->server->disk;
        $diskSizeGb = (int) ceil($diskSizeBytes / 1073741824);
        if ($diskSizeGb > 0) {
            try {
                logger()->info("Resizing disk to {$diskSizeGb}GB...");
                $client->resizeDisk($this->server->vmid, 'scsi0', $diskSizeBytes);
            } catch (\Exception $e) {
                logger()->warning("Failed to resize disk: " . $e->getMessage());
            }
        }
        
        // Configure cloud-init with all settings
        logger()->info("Configuring cloud-init...");
        $this->configureCloudinit($client, $cloudinitRepo);
        
        // Regenerate cloud-init image to apply changes
        logger()->info("Regenerating cloud-init image...");
        try {
            $cloudinitRepo->regenerate();
        } catch (\Exception $e) {
            logger()->warning("Cloudinit regenerate failed (may not be supported): " . $e->getMessage());
        }
        
        // Start the VM first
        try {
            logger()->info("Starting VM {$this->server->vmid}...");
            $powerRepo->start();
            logger()->info("Server {$this->server->vmid} started successfully");
            
            // Update server status to running after successful start
            $this->server->update([
                'status' => 'running',
                'installed_at' => now(),
                'is_installing' => false,
            ]);
        } catch (\Exception $e) {
            logger()->warning("Failed to start VM after config: " . $e->getMessage());
            // Still mark as stopped if start failed
            $this->server->update([
                'status' => 'stopped',
                'installed_at' => now(),
                'is_installing' => false,
            ]);
        }
        
        logger()->info("Server {$this->server->vmid} configuration complete");
    }

    protected function configureCloudinit(ProxmoxApiClient $client, ProxmoxCloudinitRepository $cloudinitRepo): void
    {
        // Build cloud-init params directly for Proxmox (using raw parameter names)
        $cloudinitParams = [];
        
        // Set cloud-init user
        $cloudinitParams['ciuser'] = 'root';
        
        // Set password if provided
        if ($this->password) {
            $cloudinitParams['cipassword'] = $this->password;
            logger()->info("Setting cloud-init password");
        }
        
        // Configure IP if addresses are assigned
        if (!empty($this->addressIds)) {
            $addresses = \App\Models\Address::whereIn('id', $this->addressIds)->get();
            
            foreach ($addresses as $index => $address) {
                // Assign address to server
                $address->update([
                    'server_id' => $this->server->id,
                    'is_primary' => $index === 0,
                ]);
                
                // Set ipconfig for primary interface
                if ($index === 0) {
                    $cloudinitParams['ipconfig0'] = "ip={$address->address}/{$address->cidr},gw={$address->gateway}";
                    logger()->info("Setting ipconfig0: {$cloudinitParams['ipconfig0']}");
                }
            }
        }
        
        // Apply cloud-init config directly via Proxmox API
        if (!empty($cloudinitParams)) {
            $client->updateVMConfig($this->server->vmid, $cloudinitParams);
            logger()->info("Applied cloud-init config", $cloudinitParams);
        }
    }
}

<?php

namespace App\Jobs\Server;

use App\Models\Server;
use App\Repositories\Proxmox\Server\ProxmoxCloudinitRepository;
use App\Repositories\Proxmox\Server\ProxmoxConfigRepository;
use App\Repositories\Proxmox\Server\ProxmoxPowerRepository;
use App\Repositories\Proxmox\Server\ProxmoxServerRepository;
use App\Services\Ipam\AddressService;
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
    public int $timeout = 120;

    public function __construct(
        protected Server $server,
        protected ?string $password = null,
        protected array $addressIds = [],
    ) {}

    public function handle(): void
    {
        logger()->info("Configuring server {$this->server->vmid}");
        
        $client = new ProxmoxApiClient($this->server->node);
        $serverRepo = (new ProxmoxServerRepository($client))->setServer($this->server);
        $configRepo = (new ProxmoxConfigRepository($client))->setServer($this->server);
        $cloudinitRepo = (new ProxmoxCloudinitRepository($client))->setServer($this->server);
        $powerRepo = (new ProxmoxPowerRepository($client))->setServer($this->server);
        
        // Wait for VM to be unlocked
        $serverRepo->waitForUnlock();
        
        // Update CPU, memory, and ensure name is set
        $configRepo->update([
            'name' => $this->server->name,
            'cores' => $this->server->cpu,
            'memory' => (int) ($this->server->memory / 1048576), // bytes to MB
        ]);
        
        // Resize disk - convert bytes to GB for Proxmox
        $diskSizeBytes = $this->server->disk;
        $diskSizeGb = (int) ceil($diskSizeBytes / 1073741824);
        if ($diskSizeGb > 0) {
            try {
                $client->resizeDisk($this->server->vmid, 'scsi0', $diskSizeBytes);
            } catch (\Exception $e) {
                logger()->warning("Failed to resize disk: " . $e->getMessage());
            }
        }
        
        // Configure cloud-init user and hostname
        $cloudinitConfig = [
            'ciuser' => 'root',
        ];
        
        if ($this->password) {
            $cloudinitConfig['cipassword'] = $this->password;
        }
        
        // Set hostname via cloud-init (searchdomain can help with FQDN)
        $cloudinitRepo->configure($cloudinitConfig);
        
        // Allocate IP addresses
        if (!empty($this->addressIds)) {
            $this->configureNetwork($cloudinitRepo);
        }
        
        // Regenerate cloud-init
        $cloudinitRepo->regenerate();
        
        // Update server status
        $this->server->update([
            'status' => 'installed',
            'installed_at' => now(),
        ]);
        
        // Start the VM
        try {
            $powerRepo->start();
            logger()->info("Server {$this->server->vmid} started successfully");
        } catch (\Exception $e) {
            logger()->warning("Failed to start VM after config: " . $e->getMessage());
        }
        
        logger()->info("Server {$this->server->vmid} configuration complete");
    }

    protected function configureNetwork(ProxmoxCloudinitRepository $cloudinitRepo): void
    {
        $addresses = \App\Models\Address::whereIn('id', $this->addressIds)->get();
        
        foreach ($addresses as $index => $address) {
            $address->update([
                'server_id' => $this->server->id,
                'is_primary' => $index === 0,
            ]);
            
            // Configure cloud-init network for primary interface
            if ($index === 0) {
                $cloudinitRepo->setIpConfig(
                    "{$address->address}/{$address->cidr}",
                    $address->gateway,
                    0
                );
            }
        }
    }
}

<?php

namespace App\Services\Servers;

use App\Models\Server;
use App\Repositories\Proxmox\Server\ProxmoxConfigRepository;

/**
 * ServerNetworkBandwidthService - Manages network rate limits
 */
class ServerNetworkBandwidthService
{
    public function __construct(
        protected ProxmoxConfigRepository $configRepository
    ) {}

    /**
     * Set rate limit for the primary interface.
     */
    public function setLimit(Server $server, float $mbps): void
    {
        // 0 means unlimited
        // Proxmox expects MB/s usually for 'rate' parameter on net device
        
        // We need to fetch current config to get interface string, parse it, update rate, send back
        // Or if we know the interface structure, valid update might be tricky without replacement
        
        // Simplified: Fetch config -> parse net0 -> update rate -> push
        $config = $this->configRepository->get($server);
        
        if (isset($config['net0'])) {
            $net0 = $config['net0'];
            $parts = explode(',', $net0);
            $newParts = [];
            $foundRate = false;

            foreach ($parts as $part) {
                if (str_starts_with(trim($part), 'rate=')) {
                    $newParts[] = "rate={$mbps}";
                    $foundRate = true;
                } else {
                    $newParts[] = $part;
                }
            }
            
            if (!$foundRate && $mbps > 0) {
                $newParts[] = "rate={$mbps}";
            }

            $this->configRepository->update($server, [
                'net0' => implode(',', $newParts)
            ]);
        }
    }
}

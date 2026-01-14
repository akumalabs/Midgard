<?php

namespace App\Services\Servers;

use App\Models\Server;
use App\Services\ActivityService;
use App\Enums\Activity\ServerActivity;
use App\Repositories\Proxmox\Server\ProxmoxConfigRepository;

/**
 * ServerResourceService - Manages server resources (CPU, RAM, Disk)
 */
class ServerResourceService
{
    public function __construct(
        protected ProxmoxConfigRepository $configRepository
    ) {}

    /**
     * Update CPU and Memory.
     */
    public function updateCompute(Server $server, int $cores, int $memory): void
    {
        // Validate against node limits if necessary (Rules/HasSufficient...)
        
        $config = [
            'cores' => $cores,
            'memory' => $memory,
        ];

        $this->configRepository->update($server, $config);
        
        // Update local model
        $server->update([
            'cpu' => $cores,
            'memory' => $memory,
        ]);

        ActivityService::forServer($server, ServerActivity::SETTINGS_UPDATE->value, $config);
    }

    /**
     * Resize disk.
     */
    public function resizeDisk(Server $server, string $disk, int $sizeGb): void
    {
        // Size string for Proxmox (e.g., "+10G" or absolute)
        // Proxmox usually takes size increment or absolute size
        // We'll assume absolute size for this service method for clarity
        
        // NOTE: Proxmox resize is usually additive or absolute. 
        // This repo method needs to handle the logic.
        // Assuming configRepository has a specific resize method or we use update specific syntax
        
        // In Convoy/Midgard repo pattern we might need a specific resize method in ProxmoxDiskRepository
        // Let's assume we use ProxmoxDiskRepository here instead of generic Config
        $diskRepo = app(\App\Repositories\Proxmox\Server\ProxmoxDiskRepository::class);
        $diskRepo->resize($server, $disk, $sizeGb);

        $server->update(['disk' => $sizeGb]);
        
        ActivityService::forServer($server, ServerActivity::DISK_RESIZE->value, [
            'disk' => $disk,
            'size' => $sizeGb
        ]);
    }
}

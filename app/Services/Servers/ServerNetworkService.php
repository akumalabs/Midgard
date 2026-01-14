<?php

namespace App\Services\Servers;

use App\Models\Server;
use App\Services\ActivityService;
use App\Enums\Activity\ServerActivity;
use App\Repositories\Proxmox\Server\ProxmoxConfigRepository;
use App\Data\Server\Proxmox\Config\NetworkDeviceData;

/**
 * ServerNetworkService - Manages server network interfaces following Convoy pattern
 */
class ServerNetworkService
{
    public function __construct(
        protected ProxmoxConfigRepository $configRepository
    ) {}

    /**
     * Update network configuration for a server.
     */
    public function update(Server $server, array $interfaces): void
    {
        // Generate Proxmox compatible config
        $config = [];
        
        foreach ($interfaces as $index => $interface) {
            $key = "net{$index}";
            $config[$key] = $this->buildInterfaceString($interface);
        }

        // Apply to Proxmox
        $this->configRepository->update($server, $config);

        // storage updates in local DB if needed (usually handled by sync job)
        
        ActivityService::forServer($server, ServerActivity::NETWORK_UPDATE->value);
    }

    /**
     * Add a new network interface.
     */
    public function addInterface(Server $server, array $data): void
    {
        // Find next available interface index
        $index = $this->getNextAvailableIndex($server);
        $key = "net{$index}";

        // Generate MAC if not provided
        if (empty($data['mac_address'])) {
            $data['mac_address'] = $this->generateMacAddress();
        }

        $config = [
            $key => $this->buildInterfaceString($data)
        ];

        $this->configRepository->update($server, $config);
        
        ActivityService::forServer($server, ServerActivity::NETWORK_UPDATE->value, [
            'interface' => $key,
            'action' => 'add'
        ]);
    }

    /**
     * Remove a network interface.
     */
    public function removeInterface(Server $server, int $index): void
    {
        $key = "net{$index}";
        
        // In Proxmox, setting to null deletes it
        $this->configRepository->update($server, [
            $key => null
        ]);

        ActivityService::forServer($server, ServerActivity::NETWORK_UPDATE->value, [
            'interface' => $key,
            'action' => 'remove'
        ]);
    }

    protected function buildInterfaceString(array $data): string
    {
        // Format: model=virtio,macaddr=XX:XX...,bridge=vmbr0,firewall=1,rate=10
        $parts = [];
        
        $parts[] = 'model=' . ($data['model'] ?? 'virtio');
        $parts[] = 'macaddr=' . $data['mac_address'];
        $parts[] = 'bridge=' . ($data['bridge'] ?? 'vmbr0');
        
        if (isset($data['firewall'])) {
            $parts[] = 'firewall=' . ($data['firewall'] ? '1' : '0');
        }
        
        if (isset($data['rate_limit'])) {
            $parts[] = 'rate=' . $data['rate_limit'];
        }

        if (isset($data['vlan_id'])) {
            $parts[] = 'tag=' . $data['vlan_id'];
        }

        return implode(',', $parts);
    }

    protected function getNextAvailableIndex(Server $server): int
    {
        // This would typically query current config to find gap
        // Simplified for now assuming sequential append
        // Real implementation should fetch current config via repository
        return 1; // Placeholder: impl should fetch current count
    }

    protected function generateMacAddress(): string
    {
        // Generate random MAC with private OUI
        // 02:00:00 - 02:FF:FF:FF:FF:FF is locally administered
        return sprintf(
            '02:%02X:%02X:%02X:%02X:%02X',
            rand(0, 255),
            rand(0, 255),
            rand(0, 255),
            rand(0, 255),
            rand(0, 255)
        );
    }
}

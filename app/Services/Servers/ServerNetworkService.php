<?php

namespace App\Services\Servers;

use App\Models\Server;
use App\Services\ActivityService;
use App\Enums\Activity\ServerActivity;
use App\Repositories\Proxmox\Server\ProxmoxConfigRepository;

/**
 * ServerNetworkService - Manages server network interfaces following Convoy pattern
 */
class ServerNetworkService
{
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
        $repository = new ProxmoxConfigRepository($server);
        $repository->update($config);

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

        $repository = new ProxmoxConfigRepository($server);
        $repository->update($config);
        
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
        
        // In Proxmox, setting delete parameter removes the config
        $repository = new ProxmoxConfigRepository($server);
        $repository->update(['delete' => $key]);

        ActivityService::forServer($server, ServerActivity::NETWORK_UPDATE->value, [
            'interface' => $key,
            'action' => 'remove'
        ]);
    }

    /**
     * Get current interfaces for a server.
     */
    public function getInterfaces(Server $server): array
    {
        $repository = new ProxmoxConfigRepository($server);
        $config = $repository->get();
        
        $interfaces = [];
        for ($i = 0; $i < 8; $i++) {
            $key = "net{$i}";
            if (isset($config[$key])) {
                $interfaces[] = $this->parseInterfaceString($config[$key], $i);
            }
        }
        
        return $interfaces;
    }

    protected function buildInterfaceString(array $data): string
    {
        // Format: model=virtio,macaddr=XX:XX...,bridge=vmbr0,firewall=1,rate=10
        $parts = [];
        
        $parts[] = ($data['model'] ?? 'virtio') . '=' . $data['mac_address'];
        $parts[] = 'bridge=' . ($data['bridge'] ?? 'vmbr0');
        
        if (isset($data['firewall'])) {
            $parts[] = 'firewall=' . ($data['firewall'] ? '1' : '0');
        }
        
        if (isset($data['rate_limit']) && $data['rate_limit'] > 0) {
            $parts[] = 'rate=' . $data['rate_limit'];
        }

        if (isset($data['vlan_id'])) {
            $parts[] = 'tag=' . $data['vlan_id'];
        }

        return implode(',', $parts);
    }

    protected function parseInterfaceString(string $value, int $index): array
    {
        // Parse format: model=mac,bridge=vmbr0,firewall=1,...
        $parts = explode(',', $value);
        $interface = ['device' => "net{$index}"];
        
        foreach ($parts as $part) {
            if (strpos($part, '=') !== false) {
                [$key, $val] = explode('=', $part, 2);
                $interface[$key] = $val;
            } else {
                // First part is usually model=mac
                if (preg_match('/^(virtio|e1000|rtl8139)=(.+)/', $part, $m)) {
                    $interface['model'] = $m[1];
                    $interface['mac'] = $m[2];
                }
            }
        }
        
        return $interface;
    }

    protected function getNextAvailableIndex(Server $server): int
    {
        $repository = new ProxmoxConfigRepository($server);
        $config = $repository->get();
        
        for ($i = 0; $i < 8; $i++) {
            if (!isset($config["net{$i}"])) {
                return $i;
            }
        }
        
        throw new \Exception('Maximum of 8 network interfaces reached.');
    }

    protected function generateMacAddress(): string
    {
        // Generate random MAC with private OUI (locally administered)
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

<?php

namespace App\Services\Servers;

use App\Models\Server;
use App\Repositories\Proxmox\Server\ProxmoxCloudinitRepository;
use App\Services\Proxmox\ProxmoxApiClient;

/**
 * CloudinitService - Manages cloud-init configuration following Convoy pattern
 */
class CloudinitService
{
    /**
     * Configure cloud-init for a server.
     */
    public function configure(Server $server, array $config): void
    {
        $repository = $this->getRepository($server);
        $repository->configure($this->buildConfig($server, $config));
    }

    /**
     * Update user password.
     */
    public function setPassword(Server $server, string $password): void
    {
        $repository = $this->getRepository($server);
        $repository->setPassword($password);
    }

    /**
     * Update SSH keys.
     */
    public function setSshKeys(Server $server, array $keys): void
    {
        $repository = $this->getRepository($server);
        $repository->setSshKeys($keys);
    }

    /**
     * Update network configuration.
     */
    public function setNetwork(Server $server, array $network): void
    {
        $repository = $this->getRepository($server);
        
        if (isset($network['ip']) && isset($network['gateway'])) {
            $ip = sprintf('%s/%s', $network['ip'], $network['cidr'] ?? 24);
            $repository->setIpConfig($ip, $network['gateway']);
        }
    }

    /**
     * Regenerate cloud-init image.
     */
    public function regenerate(Server $server): void
    {
        $repository = $this->getRepository($server);
        $repository->regenerate();
    }

    /**
     * Build full cloud-init config from server and custom config.
     */
    protected function buildConfig(Server $server, array $config): array
    {
        $cloudinitConfig = [];

        // User configuration
        if (isset($config['user'])) {
            $cloudinitConfig['user'] = $config['user'];
        }

        if (isset($config['password'])) {
            $cloudinitConfig['password'] = $config['password'];
        }

        if (isset($config['ssh_keys'])) {
            $cloudinitConfig['ssh_keys'] = $config['ssh_keys'];
        }

        // Network configuration
        if (isset($config['ip'])) {
            $cloudinitConfig['ip'] = sprintf('%s/%s', $config['ip'], $config['cidr'] ?? 24);
            $cloudinitConfig['gateway'] = $config['gateway'] ?? '';
        }

        // DNS
        if (isset($config['nameserver'])) {
            $cloudinitConfig['nameservers'] = is_array($config['nameserver']) 
                ? $config['nameserver'] 
                : [$config['nameserver']];
        }

        if (isset($config['searchdomain'])) {
            $cloudinitConfig['searchdomain'] = $config['searchdomain'];
        }

        return $cloudinitConfig;
    }

    /**
     * Get repository instance for server.
     */
    protected function getRepository(Server $server): ProxmoxCloudinitRepository
    {
        $client = new ProxmoxApiClient($server->node);
        $repository = new ProxmoxCloudinitRepository($client);
        $repository->setServer($server);
        return $repository;
    }
}

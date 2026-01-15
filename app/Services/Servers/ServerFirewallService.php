<?php

namespace App\Services\Servers;

use App\Models\Server;
use App\Services\ActivityService;
use App\Enums\Activity\ServerActivity;
use App\Repositories\Proxmox\Server\ProxmoxFirewallRepository;

/**
 * ServerFirewallService - Manages firewall rules following Convoy pattern
 */
class ServerFirewallService
{
    /**
     * Enable or disable firewall.
     */
    public function toggle(Server $server, bool $enabled): void
    {
        $repository = new ProxmoxFirewallRepository($server);
        
        if ($enabled) {
            $repository->enable();
        } else {
            $repository->disable();
        }
        
        ActivityService::forServer($server, ServerActivity::FIREWALL_UPDATE->value, [
            'enabled' => $enabled
        ]);
    }

    /**
     * Add a firewall rule.
     */
    public function addRule(Server $server, array $ruleData): void
    {
        $repository = new ProxmoxFirewallRepository($server);
        $repository->createRule($ruleData);
        
        ActivityService::forServer($server, ServerActivity::FIREWALL_RULE_CREATE->value, [
            'rule' => $ruleData
        ]);
    }

    /**
     * Remove a firewall rule.
     */
    public function removeRule(Server $server, int $pos): void
    {
        $repository = new ProxmoxFirewallRepository($server);
        $repository->deleteRule($pos);
        
        ActivityService::forServer($server, ServerActivity::FIREWALL_RULE_DELETE->value, [
            'position' => $pos
        ]);
    }

    /**
     * Update a firewall rule.
     */
    public function updateRule(Server $server, int $pos, array $ruleData): void
    {
        $repository = new ProxmoxFirewallRepository($server);
        $repository->updateRule($pos, $ruleData);
        
        ActivityService::forServer($server, ServerActivity::FIREWALL_RULE_UPDATE->value, [
            'position' => $pos,
            'rule' => $ruleData
        ]);
    }

    /**
     * Get all rules for a server.
     */
    public function getRules(Server $server): array
    {
        $repository = new ProxmoxFirewallRepository($server);
        return $repository->getRules();
    }

    /**
     * Sync ipsets or security groups if needed.
     */
    public function sync(Server $server): void
    {
        // Implementation might involve pulling rules from DB and pushing to Proxmox
        // if we are treating DB as source of truth
    }
}

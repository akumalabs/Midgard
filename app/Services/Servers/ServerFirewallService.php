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
    public function __construct(
        protected ProxmoxFirewallRepository $firewallRepository
    ) {}

    /**
     * Enable or disable firewall.
     */
    public function toggle(Server $server, bool $enabled): void
    {
        $this->firewallRepository->setFirewallEnabled($server, $enabled);
        
        ActivityService::forServer($server, ServerActivity::FIREWALL_UPDATE->value, [
            'enabled' => $enabled
        ]);
    }

    /**
     * Add a firewall rule.
     */
    public function addRule(Server $server, array $ruleData): void
    {
        $this->firewallRepository->createRule($server, $ruleData);
        
        ActivityService::forServer($server, ServerActivity::FIREWALL_RULE_CREATE->value, [
            'rule' => $ruleData
        ]);
    }

    /**
     * Remove a firewall rule.
     */
    public function removeRule(Server $server, int $pos): void
    {
        $this->firewallRepository->deleteRule($server, $pos);
        
        ActivityService::forServer($server, ServerActivity::FIREWALL_RULE_DELETE->value, [
            'position' => $pos
        ]);
    }

    /**
     * Update a firewall rule.
     */
    public function updateRule(Server $server, int $pos, array $ruleData): void
    {
        $this->firewallRepository->updateRule($server, $pos, $ruleData);
        
        ActivityService::forServer($server, ServerActivity::FIREWALL_RULE_UPDATE->value, [
            'position' => $pos,
            'rule' => $ruleData
        ]);
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

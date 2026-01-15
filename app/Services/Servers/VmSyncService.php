<?php

namespace App\Services\Servers;

use App\Models\Server;
use App\Models\Node;
use App\Repositories\Proxmox\Server\ProxmoxActivityRepository;
use App\Repositories\Proxmox\Server\ProxmoxGuestAgentRepository;
use App\Services\Proxmox\ProxmoxApiClient;
use Illuminate\Support\Facades\Log;

/**
 * VmSyncService - Syncs real-world Proxmox state to Midgard DB following Convoy pattern
 */
class VmSyncService
{
    /**
     * Sync state for a single server.
     */
    public function sync(Server $server): void
    {
        try {
            $client = new ProxmoxApiClient($server->node);
            $activityRepo = new ProxmoxActivityRepository($client);
            $activityRepo->setServer($server);
            
            $statusData = $activityRepo->getCurrentStatus();
            
            // Update status
            if (isset($statusData['status'])) {
                $status = $statusData['status'];
                
                // Only update if changed (optional optimization)
                if ($server->status !== $status) {
                    $server->update(['status' => $status]);
                }
            }
            
            // Sync Guest Agent Info if running
            if (($statusData['status'] ?? '') === 'running') {
                $this->syncGuestAgent($server, $client);
            }

        } catch (\Exception $e) {
            Log::warning("Failed to sync server {$server->id}: " . $e->getMessage());
        }
    }

    /**
     * Sync guest agent information.
     */
    protected function syncGuestAgent(Server $server, ProxmoxApiClient $client): void
    {
        try {
            $agentRepo = new ProxmoxGuestAgentRepository($client);
            $agentRepo->setServer($server);
            
            // Implementation of info fetching to be added if models support it
            // e.g. updating IP addresses in DB from agent info
            
        } catch (\Exception $e) {
            // Agent might not be running or installed, suppress loud errors
        }
    }

    /**
     * Sync all servers on a node.
     */
    public function syncNode(Node $node): void
    {
        foreach ($node->servers as $server) {
            $this->sync($server);
        }
    }
}

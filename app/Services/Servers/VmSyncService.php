<?php

namespace App\Services\Servers;

use App\Models\Server;
use App\Models\Node;
use App\Repositories\Proxmox\Server\ProxmoxActivityRepository;
use App\Repositories\Proxmox\Server\ProxmoxGuestAgentRepository;
use App\Repositories\Proxmox\Server\ProxmoxStatisticsRepository;
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
            $activityRepo = new ProxmoxActivityRepository($server);
            $statusData = $activityRepo->getCurrentStatus();
            
            // Update status
            if (isset($statusData['status'])) {
                $status = $statusData['status'];
                if ($status === 'stopped' && $statusData['lock'] ?? false) {
                    // special handling for locked
                }
                
                // Only update if changed (optional optimization)
                if ($server->status !== $status) {
                    $server->update(['status' => $status]);
                }
            }
            
            // Sync Guest Agent Info if running
            if (($statusData['status'] ?? '') === 'running') {
                $this->syncGuestAgent($server);
            }

        } catch (\Exception $e) {
            Log::warning("Failed to sync server {$server->id}: " . $e->getMessage());
        }
    }

    /**
     * Sync guest agent information.
     */
    protected function syncGuestAgent(Server $server): void
    {
        try {
            $agentRepo = new ProxmoxGuestAgentRepository($server);
            
            // This might verify agent is running before trying deep fetch
            // Basic check:
            // $agentRepo->ping(); 
            
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
        // Fetch all VM statuses from node at once (batch)
        // This requires a repository method on Node level typically, 
        // e.g. ProxmoxRepository::getClusterResources or similar.
        
        // For now, iterate (simplest, though not most efficient)
        foreach ($node->servers as $server) {
            $this->sync($server);
        }
    }
}

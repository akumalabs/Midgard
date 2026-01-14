<?php

namespace App\Jobs\Node;

use App\Services\Nodes\UserPruneService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * PruneUsersJob - Removes stale users from Proxmox nodes
 */
class PruneUsersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Logic to remove PVE realm users that no longer exist in DB
        // or have been deleted.
        
        \Log::info("Pruning stale users from nodes...");
    }
}

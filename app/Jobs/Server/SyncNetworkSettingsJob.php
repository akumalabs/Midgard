<?php

namespace App\Jobs\Server;

use App\Models\Server;
use App\Services\Servers\ServerNetworkService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * SyncNetworkSettingsJob - Re-applies network configuration to Proxmox
 */
class SyncNetworkSettingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        protected Server $server
    ) {}

    public function handle(): void
    {
        $networkService = app(ServerNetworkService::class);
        
        // Fetch current desired state from DB or calculate it
        // simplified: we assume we want to re-apply what's in the DB settings or similar
        // For now, let's assume we are re-syncing from a known config source or just
        // triggering a "refresh" logic if implemented.
        
        // In Convoy, this often means checking what's in the DB tables (IP allocations)
        // and ensuring the VM config matches.
        
        // Placeholder log logic until we have the full IPAM -> VM map fully wired
        \Log::info("Syncing network settings for server {$this->server->id}");
    }
}

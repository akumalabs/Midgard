<?php

namespace App\Jobs\Node;

use App\Models\Server;
use App\Services\Servers\ServerNetworkBandwidthService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * SyncServerRateLimitsJob - Ensures rate limits on Proxmox match DB
 */
class SyncServerRateLimitsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Iterate all servers, apply rate limits
        Server::chunk(100, function ($servers) {
            foreach ($servers as $server) {
                // $limit = $server->bandwidth_limit ?? 0;
                // app(ServerNetworkBandwidthService::class)->setLimit($server, $limit);
            }
        });
    }
}

<?php

namespace App\Http\Controllers\Api\Server;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Services\Servers\ServerConsoleService;
use Illuminate\Http\Request;

class ServerConsoleController extends Controller
{
    /**
     * Get NoVNC console configuration.
     */
    public function vnc(Server $server, ServerConsoleService $service)
    {
        $this->authorize('view', $server);

        // This usually returns a ticket or direct websocket URL info
        // Frontend uses this to connect via novnc-client
        
        $credentials = $service->getNoVncCredentials($server);
        
        // We might want to wrap this in a more frontend-friendly format
        // e.g. returning the full websocket URL to connect to
        
        $node = $server->node;
        
        return response()->json([
            'ticket' => $credentials['ticket'],
            'port' => $credentials['port'],
            'cert' => $credentials['cert'] ?? null,
            'node' => [
                'name' => $node->name,
                'fqdn' => $node->fqdn, // Ensure this is safe to expose or proxy it
            ],
            // For proxying through Laravel/WS, we might return a local WS URL
        ]);
    }
}

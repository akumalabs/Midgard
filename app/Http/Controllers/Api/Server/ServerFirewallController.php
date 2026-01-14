<?php

namespace App\Http\Controllers\Api\Server;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Services\Servers\ServerFirewallService;
use Illuminate\Http\Request;

class ServerFirewallController extends Controller
{
    public function __construct(
        protected ServerFirewallService $firewallService
    ) {}

    /**
     * List firewall rules.
     */
    public function index(Server $server)
    {
        $this->authorize('view', $server);
        
        // Ensure Repository is available to fetch rules
        $rules = app(\App\Repositories\Proxmox\Server\ProxmoxFirewallRepository::class)->getRules($server);

        return response()->json(['rules' => $rules]);
    }

    /**
     * Create a firewall rule.
     */
    public function store(Request $request, Server $server)
    {
        $this->authorize('update', $server);

        $validated = $request->validate([
            'type' => 'required|in:in,out',
            'action' => 'required|in:ACCEPT,DROP,REJECT',
            'macro' => 'nullable|string',
            'dest' => 'nullable|string',
            'source' => 'nullable|string',
            'proto' => 'nullable|string',
            'sport' => 'nullable|string',
            'dport' => 'nullable|string',
            'enable' => 'nullable|boolean',
        ]);

        $this->firewallService->addRule($server, $validated);

        return response()->json(['message' => 'Firewall rule created.'], 200);
    }

    /**
     * Delete a firewall rule.
     */
    public function destroy(Server $server, int $pos)
    {
        $this->authorize('update', $server);

        $this->firewallService->removeRule($server, $pos);

        return response()->json(['message' => 'Firewall rule deleted.'], 200);
    }

    /**
     * Toggle firewall status.
     */
    public function toggle(Request $request, Server $server)
    {
        $this->authorize('update', $server);

        $validated = $request->validate(['enabled' => 'required|boolean']);

        $this->firewallService->toggle($server, $validated['enabled']);

        return response()->json(['message' => 'Firewall status updated.'], 200);
    }
}

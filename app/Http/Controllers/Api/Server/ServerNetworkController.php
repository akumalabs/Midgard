<?php

namespace App\Http\Controllers\Api\Server;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Services\Servers\ServerNetworkService;
use Illuminate\Http\Request;

class ServerNetworkController extends Controller
{
    public function __construct(
        protected ServerNetworkService $networkService
    ) {}

    /**
     * List network interfaces.
     */
    public function index(Server $server)
    {
        $this->authorize('view', $server);

        // This assumes we have a way to get current interface state
        // Either from DB json column or querying Proxmox
        // For now, returning config from DB
        
        // This is a placeholder for fetching parsed network interfaces
        return response()->json([
            'interfaces' => [] // TODO: Add parser to fetch real interfaces
        ]);
    }

    /**
     * Add a network interface.
     */
    public function store(Request $request, Server $server)
    {
        $this->authorize('update', $server);

        $validated = $request->validate([
            'model' => 'nullable|string|in:virtio,e1000,rtl8139,vmxnet3',
            'bridge' => 'nullable|string',
            'firewall' => 'nullable|boolean',
            'rate_limit' => 'nullable|numeric|min:0',
            'vlan_id' => 'nullable|integer|min:1|max:4094',
        ]);

        $this->networkService->addInterface($server, $validated);

        return response()->json(['message' => 'Interface added.'], 200);
    }

    /**
     * Remove a network interface.
     */
    public function destroy(Server $server, int $index)
    {
        $this->authorize('update', $server);

        $this->networkService->removeInterface($server, $index);

        return response()->json(['message' => 'Interface removed.'], 200);
    }
}

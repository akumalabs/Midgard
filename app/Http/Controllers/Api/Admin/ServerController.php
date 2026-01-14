<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Node;
use App\Models\Server;
use App\Models\User;
use App\Services\Proxmox\ProxmoxApiClient;
use App\Services\Proxmox\ProxmoxApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServerController extends Controller
{
    /**
     * List all servers (admin view).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Server::with(['user', 'node.location'])
            ->withCount('addresses');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by node
        if ($request->has('node_id')) {
            $query->where('node_id', $request->node_id);
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $servers = $query->orderBy('created_at', 'desc')->get()
            ->map(fn($server) => $this->formatServer($server));

        return response()->json([
            'data' => $servers,
        ]);
    }

    /**
     * Get a single server.
     */
    public function show(Server $server): JsonResponse
    {
        $server->load(['user', 'node.location', 'addresses']);

        return response()->json([
            'data' => $this->formatServer($server, true),
        ]);
    }

    /**
     * Create a new server.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'node_id' => ['required', 'exists:nodes,id'],
            'name' => ['required', 'string', 'max:255'],
            'hostname' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'description' => ['nullable', 'string'],
            'cpu' => ['required', 'integer', 'min:1', 'max:128'],
            'memory' => ['required', 'integer', 'min:536870912'], // 512MB minimum
            'disk' => ['required', 'integer', 'min:1073741824'], // 1GB minimum
            'bandwidth_limit' => ['nullable', 'integer', 'min:0'],
            'template_vmid' => ['required', 'string'], // Template to clone from
            'vmid' => ['nullable', 'integer', 'min:100'], // Optional custom VMID
            'address_pool_id' => ['nullable', 'exists:address_pools,id'],
            'ip_address' => ['nullable', 'ip'],
        ]);

        $node = Node::findOrFail($validated['node_id']);

        try {
            $client = new ProxmoxApiClient($node);

            // Get next VMID or use custom one
            $vmid = $validated['vmid'] ?? $client->getNextVmid();

            // Clone from template
            $task = $client->cloneVM(
                (int) $validated['template_vmid'],
                $vmid,
                [
                    'name' => $validated['name'],
                    'description' => $validated['description'] ?? '',
                ]
            );

            // Create server record
            $server = Server::create([
                'user_id' => $validated['user_id'],
                'node_id' => $validated['node_id'],
                'vmid' => (string) $vmid,
                'name' => $validated['name'],
                'hostname' => $validated['hostname'],
                'password' => $validated['password'] ?? null,
                'description' => $validated['description'],
                'cpu' => $validated['cpu'],
                'memory' => $validated['memory'],
                'disk' => $validated['disk'],
                'bandwidth_limit' => $validated['bandwidth_limit'],
                'status' => 'installing',
                'is_installing' => true,
            ]);

            // TODO: Queue job to wait for clone completion and configure VM

            $server->load(['user', 'node.location']);

            return response()->json([
                'message' => 'Server creation started',
                'data' => $this->formatServer($server),
            ], 201);

        } catch (ProxmoxApiException $e) {
            return response()->json([
                'message' => 'Failed to create server',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update a server.
     */
    public function update(Request $request, Server $server): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'hostname' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'cpu' => ['sometimes', 'integer', 'min:1', 'max:128'],
            'memory' => ['sometimes', 'integer', 'min:536870912'],
            'disk' => ['sometimes', 'integer', 'min:1073741824'],
            'bandwidth_limit' => ['nullable', 'integer', 'min:0'],
            'is_suspended' => ['sometimes', 'boolean'],
        ]);

        $server->update($validated);

        // If resources changed, update Proxmox config
        if (isset($validated['cpu']) || isset($validated['memory'])) {
            try {
                $client = new ProxmoxApiClient($server->node);
                $config = [];

                if (isset($validated['cpu'])) {
                    $config['cores'] = $validated['cpu'];
                }
                if (isset($validated['memory'])) {
                    $config['memory'] = (int) ($validated['memory'] / 1024 / 1024); // Convert to MB
                }

                if (!empty($config)) {
                    $client->updateVMConfig((int) $server->vmid, $config);
                }
            } catch (ProxmoxApiException $e) {
                // Log error but don't fail the update
                logger()->error('Failed to update Proxmox config', [
                    'server' => $server->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'message' => 'Server updated successfully',
            'data' => $this->formatServer($server->fresh(['user', 'node.location'])),
        ]);
    }

    /**
     * Delete a server.
     */
    public function destroy(Server $server): JsonResponse
    {
        try {
            $client = new ProxmoxApiClient($server->node);

            // Stop the VM first if running
            if ($server->status === 'running') {
                $client->stopVM((int) $server->vmid);
                sleep(5); // Wait a bit
            }

            // Delete from Proxmox
            $client->deleteVM((int) $server->vmid);

            // Delete from database
            $server->delete();

            return response()->json([
                'message' => 'Server deleted successfully',
            ]);

        } catch (ProxmoxApiException $e) {
            return response()->json([
                'message' => 'Failed to delete server',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Power action on a server.
     */
    public function power(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'action' => ['required', 'in:start,stop,restart,shutdown,reset'],
        ]);

        try {
            $client = new ProxmoxApiClient($server->node);
            $action = $request->action;

            match ($action) {
                'start' => $client->startVM((int) $server->vmid),
                'stop' => $client->stopVM((int) $server->vmid),
                'shutdown' => $client->shutdownVM((int) $server->vmid),
                'restart' => $client->rebootVM((int) $server->vmid),
                'reset' => $client->resetVM((int) $server->vmid),
            };

            // Update status
            $newStatus = match ($action) {
                'start', 'restart', 'reset' => 'running',
                'stop', 'shutdown' => 'stopped',
            };
            $server->update(['status' => $newStatus]);

            return response()->json([
                'message' => "Server {$action} initiated",
                'data' => ['status' => $newStatus],
            ]);

        } catch (ProxmoxApiException $e) {
            return response()->json([
                'message' => "Failed to {$request->action} server",
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get server status from Proxmox.
     */
    public function status(Server $server): JsonResponse
    {
        try {
            $client = new ProxmoxApiClient($server->node);
            $status = $client->getVMStatus((int) $server->vmid);

            // Update local status
            $proxmoxStatus = $status['status'] ?? 'unknown';
            if ($proxmoxStatus !== $server->status && in_array($proxmoxStatus, ['running', 'stopped'])) {
                $server->update(['status' => $proxmoxStatus]);
            }

            return response()->json([
                'data' => [
                    'status' => $proxmoxStatus,
                    'uptime' => $status['uptime'] ?? 0,
                    'cpu' => ($status['cpu'] ?? 0) * 100,
                    'memory' => [
                        'used' => $status['mem'] ?? 0,
                        'total' => $status['maxmem'] ?? 0,
                    ],
                    'disk' => [
                        'read' => $status['diskread'] ?? 0,
                        'write' => $status['diskwrite'] ?? 0,
                    ],
                    'network' => [
                        'in' => $status['netin'] ?? 0,
                        'out' => $status['netout'] ?? 0,
                    ],
                ],
            ]);

        } catch (ProxmoxApiException $e) {
            return response()->json([
                'message' => 'Failed to get server status',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Format server for API response.
     */
    protected function formatServer(Server $server, bool $detailed = false): array
    {
        $data = [
            'id' => $server->id,
            'uuid' => $server->uuid,
            'vmid' => $server->vmid,
            'name' => $server->name,
            'hostname' => $server->hostname,
            'status' => $server->status,
            'is_suspended' => $server->is_suspended,
            'cpu' => $server->cpu,
            'memory' => $server->memory,
            'memory_formatted' => $server->formatted_memory,
            'disk' => $server->disk,
            'disk_formatted' => $server->formatted_disk,
            'bandwidth_limit' => $server->bandwidth_limit,
            'bandwidth_usage' => $server->bandwidth_usage,
            'user' => $server->user ? [
                'id' => $server->user->id,
                'name' => $server->user->name,
                'email' => $server->user->email,
            ] : null,
            'node' => $server->node ? [
                'id' => $server->node->id,
                'name' => $server->node->name,
                'location' => $server->node->location ? [
                    'id' => $server->node->location->id,
                    'name' => $server->node->location->name,
                    'short_code' => $server->node->location->short_code,
                ] : null,
            ] : null,
            'created_at' => $server->created_at,
        ];

        if ($detailed) {
            $data['description'] = $server->description;
            $data['installed_at'] = $server->installed_at;
            $data['addresses'] = $server->addresses->map(fn($addr) => [
                'id' => $addr->id,
                'address' => $addr->address,
                'cidr' => $addr->cidr,
                'gateway' => $addr->gateway,
                'type' => $addr->type,
                'is_primary' => $addr->is_primary,
            ]);
        }

        return $data;
    }
}

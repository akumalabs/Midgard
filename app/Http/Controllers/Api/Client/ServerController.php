<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Services\Proxmox\ProxmoxApiClient;
use App\Services\Proxmox\ProxmoxApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServerController extends Controller
{
    /**
     * List servers for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $servers = $request->user()
            ->servers()
            ->with('node.location')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($server) => $this->formatServer($server));

        return response()->json([
            'data' => $servers,
        ]);
    }

    /**
     * Get a single server (must belong to user).
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $server = $request->user()
            ->servers()
            ->where('uuid', $uuid)
            ->with(['node.location', 'addresses'])
            ->firstOrFail();

        return response()->json([
            'data' => $this->formatServer($server, true),
        ]);
    }

    /**
     * Get server status from Proxmox.
     */
    public function status(Request $request, string $uuid): JsonResponse
    {
        $server = $request->user()
            ->servers()
            ->where('uuid', $uuid)
            ->with('node')
            ->firstOrFail();

        try {
            $client = new ProxmoxApiClient($server->node);
            $status = $client->getVMStatus((int) $server->vmid);

            return response()->json([
                'data' => [
                    'status' => $status['status'] ?? 'unknown',
                    'uptime' => $status['uptime'] ?? 0,
                    'cpu' => round(($status['cpu'] ?? 0) * 100, 2),
                    'memory' => [
                        'used' => $status['mem'] ?? 0,
                        'total' => $status['maxmem'] ?? 0,
                        'percentage' => ($status['maxmem'] ?? 0) > 0
                            ? round(($status['mem'] / $status['maxmem']) * 100, 2)
                            : 0,
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
     * Power action on a server.
     */
    public function power(Request $request, string $uuid): JsonResponse
    {
        $server = $request->user()
            ->servers()
            ->where('uuid', $uuid)
            ->with('node')
            ->firstOrFail();

        // Check if server is suspended
        if ($server->is_suspended) {
            return response()->json([
                'message' => 'Cannot control a suspended server',
            ], 403);
        }

        $request->validate([
            'action' => ['required', 'in:start,stop,restart,shutdown'],
        ]);

        try {
            $client = new ProxmoxApiClient($server->node);
            $action = $request->action;

            match ($action) {
                'start' => $client->startVM((int) $server->vmid),
                'stop' => $client->stopVM((int) $server->vmid),
                'shutdown' => $client->shutdownVM((int) $server->vmid),
                'restart' => $client->rebootVM((int) $server->vmid),
            };

            // Update status
            $newStatus = match ($action) {
                'start', 'restart' => 'running',
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
     * Get VNC console access.
     */
    public function console(Request $request, string $uuid): JsonResponse
    {
        $server = $request->user()
            ->servers()
            ->where('uuid', $uuid)
            ->with('node')
            ->firstOrFail();

        if ($server->is_suspended) {
            return response()->json([
                'message' => 'Cannot access console of a suspended server',
            ], 403);
        }

        try {
            $client = new ProxmoxApiClient($server->node);
            $proxy = $client->getVNCProxy((int) $server->vmid);

            return response()->json([
                'data' => [
                    'ticket' => $proxy['ticket'] ?? null,
                    'port' => $proxy['port'] ?? null,
                    'url' => "wss://{$server->node->fqdn}:{$proxy['port']}",
                ],
            ]);

        } catch (ProxmoxApiException $e) {
            return response()->json([
                'message' => 'Failed to get console access',
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
            'node' => $server->node ? [
                'name' => $server->node->name,
                'location' => $server->node->location ? [
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

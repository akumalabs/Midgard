<?php

namespace App\Http\Controllers\Api\Client;

use App\Data\Server\ServerStateData;
use App\Enums\Server\PowerCommand;
use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Repositories\Proxmox\Server\ProxmoxPowerRepository;
use App\Repositories\Proxmox\Server\ProxmoxServerRepository;
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
     * Uses Convoy-style ServerStateData DTO
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
            $repository = (new ProxmoxServerRepository($client))->setServer($server);
            $state = $repository->getState();

            return response()->json([
                'data' => [
                    'status' => $state->state->value,
                    'uptime' => $state->uptime,
                    'uptime_formatted' => $state->uptimeFormatted(),
                    'cpu' => $state->cpuPercent(),
                    'memory' => [
                        'used' => $state->memoryUsed,
                        'total' => $state->memoryTotal,
                        'percentage' => $state->memoryPercent(),
                    ],
                    'disk' => [
                        'used' => $state->diskUsed,
                        'total' => $state->diskTotal,
                        'percentage' => $state->diskPercent(),
                    ],
                    'network' => [
                        'in' => $state->netIn,
                        'out' => $state->netOut,
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
     * Uses Convoy-style PowerCommand enum and ProxmoxPowerRepository
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
            'action' => ['required', 'in:start,stop,restart,shutdown,kill'],
        ]);

        try {
            $client = new ProxmoxApiClient($server->node);
            $repository = (new ProxmoxPowerRepository($client))->setServer($server);
            $action = $request->action;
            
            logger()->info("Power action {$action} on server {$server->vmid}");

            // Map to PowerCommand enum and send via repository
            $result = match ($action) {
                'start' => $repository->start(),
                'stop', 'kill' => $repository->kill(),
                'shutdown' => $repository->shutdown(),
                'restart' => $repository->reboot(),
            };
            
            logger()->info("Power action result", ['upid' => $result]);

            // Update status
            $newStatus = match ($action) {
                'start', 'restart' => 'running',
                'stop', 'shutdown', 'kill' => 'stopped',
            };
            $server->update(['status' => $newStatus]);

            return response()->json([
                'message' => "Server {$action} initiated",
                'data' => ['status' => $newStatus, 'upid' => $result],
            ]);

        } catch (ProxmoxApiException $e) {
            logger()->error("ProxmoxApiException in power action", [
                'action' => $request->action,
                'vmid' => $server->vmid,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => "Failed to {$request->action} server",
                'error' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            logger()->error("General exception in power action", [
                'action' => $request->action,
                'vmid' => $server->vmid,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'message' => "Failed to {$request->action} server",
                'error' => $e->getMessage(),
            ], 500);
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
                    'vmid' => $server->vmid,
                    'name' => $server->name,
                    'node' => [
                        'fqdn' => $server->node->fqdn,
                        'name' => $server->node->cluster,
                    ],
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
     * Update server password via cloud-init.
     * Follows Convoy pattern for authentication settings.
     */
    public function updatePassword(Request $request, string $uuid): JsonResponse
    {
        $server = $request->user()
            ->servers()
            ->where('uuid', $uuid)
            ->with('node')
            ->firstOrFail();

        if ($server->is_suspended) {
            return response()->json([
                'message' => 'Cannot update password of a suspended server',
            ], 403);
        }

        $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:72'],
        ]);

        try {
            $client = new ProxmoxApiClient($server->node);
            $repository = (new \App\Repositories\Proxmox\Server\ProxmoxConfigRepository($client))
                ->setServer($server);
            
            $repository->setPassword($request->password);
            
            logger()->info("Password updated for server {$server->vmid}");

            return response()->json([
                'message' => 'Password updated successfully. Reboot required to apply.',
            ]);

        } catch (ProxmoxApiException $e) {
            return response()->json([
                'message' => 'Failed to update password',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Reinstall server from template.
     * Uses Convoy-style deployment tracking for progress visibility.
     */
    public function reinstall(Request $request, string $uuid): JsonResponse
    {
        $server = $request->user()
            ->servers()
            ->where('uuid', $uuid)
            ->with('node')
            ->firstOrFail();

        if ($server->is_suspended) {
            return response()->json([
                'message' => 'Cannot reinstall a suspended server',
            ], 403);
        }

        $request->validate([
            'template_id' => ['required', 'exists:templates,id'],
            'password' => ['required', 'string', 'min:8', 'max:72'],
        ]);

        $template = \App\Models\Template::findOrFail($request->template_id);

        // Create deployment with steps for tracking
        $deploymentService = app(\App\Services\Servers\DeploymentService::class);
        $deployment = $deploymentService->create($server, [
            'Stopping VM',
            'Deleting VM',
            'Cloning template',
            'Configuring server',
            'Updating password',
            'Starting VM',
        ]);

        // Dispatch reinstall job with deployment ID
        \App\Jobs\Server\ReinstallServerJob::dispatch(
            $server, 
            $template, 
            $request->password,
            $deployment->id
        );

        return response()->json([
            'message' => 'Server reinstall started',
            'deployment_uuid' => $deployment->uuid,
        ]);
    }

    /**
     * Get current active deployment for a server.
     */
    public function currentDeployment(Request $request, string $uuid): JsonResponse
    {
        $server = $request->user()
            ->servers()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $deployment = $server->deployments()
            ->whereIn('status', ['pending', 'running'])
            ->with('steps')
            ->latest()
            ->first();

        if (!$deployment) {
            return response()->json([
                'data' => null,
            ]);
        }

        return response()->json([
            'data' => [
                'uuid' => $deployment->uuid,
                'status' => $deployment->status,
                'error' => $deployment->error,
                'started_at' => $deployment->started_at?->toIso8601String(),
                'completed_at' => $deployment->completed_at?->toIso8601String(),
                'steps' => $deployment->steps->map(fn($step) => [
                    'name' => $step->name,
                    'status' => $step->status,
                    'error' => $step->error,
                    'started_at' => $step->started_at?->toIso8601String(),
                    'completed_at' => $step->completed_at?->toIso8601String(),
                    'duration' => $step->started_at && $step->completed_at 
                        ? $step->started_at->diffInSeconds($step->completed_at) 
                        : null,
                ]),
            ],
        ]);
    }

    /**
     * Mount ISO to server.
     */
    public function mountIso(Request $request, string $uuid): JsonResponse
    {
        $server = $request->user()
            ->servers()
            ->where('uuid', $uuid)
            ->with('node')
            ->firstOrFail();

        if ($server->is_suspended) {
            return response()->json([
                'message' => 'Cannot mount ISO on a suspended server',
            ], 403);
        }

        $request->validate([
            'storage' => ['required', 'string'],
            'iso' => ['required', 'string'],
        ]);

        try {
            $client = new ProxmoxApiClient($server->node);
            $repository = (new \App\Repositories\Proxmox\Server\ProxmoxConfigRepository($client))
                ->setServer($server);
            
            $repository->mountIso($request->storage, $request->iso);

            return response()->json([
                'message' => 'ISO mounted successfully',
            ]);

        } catch (ProxmoxApiException $e) {
            return response()->json([
                'message' => 'Failed to mount ISO',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Unmount ISO from server.
     */
    public function unmountIso(Request $request, string $uuid): JsonResponse
    {
        $server = $request->user()
            ->servers()
            ->where('uuid', $uuid)
            ->with('node')
            ->firstOrFail();

        if ($server->is_suspended) {
            return response()->json([
                'message' => 'Cannot unmount ISO from a suspended server',
            ], 403);
        }

        try {
            $client = new ProxmoxApiClient($server->node);
            $repository = (new \App\Repositories\Proxmox\Server\ProxmoxConfigRepository($client))
                ->setServer($server);
            
            $repository->unmountIso();

            return response()->json([
                'message' => 'ISO unmounted successfully',
            ]);

        } catch (ProxmoxApiException $e) {
            return response()->json([
                'message' => 'Failed to unmount ISO',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * List snapshots for a server.
     */
    public function listSnapshots(Request $request, string $uuid): JsonResponse
    {
        $server = $request->user()
            ->servers()
            ->where('uuid', $uuid)
            ->with('node')
            ->firstOrFail();

        try {
            $client = new ProxmoxApiClient($server->node);
            $repository = (new \App\Repositories\Proxmox\Server\ProxmoxSnapshotRepository($client))
                ->setServer($server);
            
            $snapshots = $repository->list();
            
            // Filter out 'current' state entry
            $snapshots = array_filter($snapshots, fn($s) => ($s['name'] ?? '') !== 'current');

            return response()->json([
                'data' => array_values($snapshots),
            ]);

        } catch (ProxmoxApiException $e) {
            return response()->json([
                'message' => 'Failed to list snapshots',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Create a snapshot.
     */
    public function createSnapshot(Request $request, string $uuid): JsonResponse
    {
        $server = $request->user()
            ->servers()
            ->where('uuid', $uuid)
            ->with('node')
            ->firstOrFail();

        if ($server->is_suspended) {
            return response()->json([
                'message' => 'Cannot create snapshot of a suspended server',
            ], 403);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:40', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'description' => ['nullable', 'string', 'max:255'],
            'include_ram' => ['nullable', 'boolean'],
        ]);

        try {
            $client = new ProxmoxApiClient($server->node);
            $repository = (new \App\Repositories\Proxmox\Server\ProxmoxSnapshotRepository($client))
                ->setServer($server);
            
            $upid = $repository->create(
                $request->name,
                $request->description,
                $request->boolean('include_ram', false)
            );

            return response()->json([
                'message' => 'Snapshot creation started',
                'upid' => $upid,
            ]);

        } catch (ProxmoxApiException $e) {
            return response()->json([
                'message' => 'Failed to create snapshot',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Rollback to a snapshot.
     */
    public function rollbackSnapshot(Request $request, string $uuid, string $name): JsonResponse
    {
        $server = $request->user()
            ->servers()
            ->where('uuid', $uuid)
            ->with('node')
            ->firstOrFail();

        if ($server->is_suspended) {
            return response()->json([
                'message' => 'Cannot rollback a suspended server',
            ], 403);
        }

        try {
            $client = new ProxmoxApiClient($server->node);
            $repository = (new \App\Repositories\Proxmox\Server\ProxmoxSnapshotRepository($client))
                ->setServer($server);
            
            $upid = $repository->rollback($name);

            return response()->json([
                'message' => 'Snapshot rollback started',
                'upid' => $upid,
            ]);

        } catch (ProxmoxApiException $e) {
            return response()->json([
                'message' => 'Failed to rollback snapshot',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Delete a snapshot.
     */
    public function deleteSnapshot(Request $request, string $uuid, string $name): JsonResponse
    {
        $server = $request->user()
            ->servers()
            ->where('uuid', $uuid)
            ->with('node')
            ->firstOrFail();

        if ($server->is_suspended) {
            return response()->json([
                'message' => 'Cannot delete snapshot of a suspended server',
            ], 403);
        }

        try {
            $client = new ProxmoxApiClient($server->node);
            $repository = (new \App\Repositories\Proxmox\Server\ProxmoxSnapshotRepository($client))
                ->setServer($server);
            
            $upid = $repository->delete($name);

            return response()->json([
                'message' => 'Snapshot deletion started',
                'upid' => $upid,
            ]);

        } catch (ProxmoxApiException $e) {
            return response()->json([
                'message' => 'Failed to delete snapshot',
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

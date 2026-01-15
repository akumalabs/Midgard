<?php

namespace App\Http\Controllers\Api\Server;

use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Models\Server;
use App\Services\Backups\BackupCreationService;
use App\Services\Backups\BackupDeletionService;
use App\Services\Backups\RestoreFromBackupService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServerBackupController extends Controller
{
    /**
     * List backups for a server.
     */
    public function index(Server $server)
    {
        $this->authorize('view', $server);

        // Fetch backups from DB (synced)
        return response()->json([
            'backups' => $server->backups()->latest()->get()
        ]);
    }

    /**
     * Create a new backup.
     */
    public function store(Request $request, Server $server, BackupCreationService $service)
    {
        $this->authorize('update', $server);

        $validated = $request->validate([
            'mode' => ['required', Rule::in(['snapshot', 'suspend', 'stop'])],
            'compression' => ['nullable', Rule::in(['zstd', 'gzip', 'lzo', 'none'])],
        ]);

        $backup = $service->handle(
            $server,
            $validated['mode'],
            $validated['compression'] ?? 'zstd'
        );

        return response()->json([
            'message' => 'Backup creation started.',
            'backup' => $backup
        ], 202);
    }

    /**
     * Restore from a backup.
     */
    public function restore(Request $request, Server $server, Backup $backup, RestoreFromBackupService $service)
    {
        $this->authorize('update', $server);

        if ($backup->server_id !== $server->id) {
            abort(403, 'Backup does not belong to this server.');
        }

        $service->handle($backup);

        return response()->json([
            'message' => 'Restoration started.'
        ], 202);
    }

    /**
     * Delete a backup.
     */
    public function destroy(Server $server, Backup $backup, BackupDeletionService $service)
    {
        $this->authorize('update', $server);

        if ($backup->server_id !== $server->id) {
            abort(403, 'Backup does not belong to this server.');
        }

        $service->handle($backup);

        return response()->json([
            'message' => 'Backup deletion started.'
        ], 202);
    }
}

<?php

namespace App\Services\Backups;

use App\Enums\Activity\ServerActivity;
use App\Jobs\Backup\MonitorBackupJob;
use App\Models\Backup;
use App\Models\Server;
use App\Repositories\Proxmox\Server\ProxmoxBackupRepository;
use App\Services\ActivityService;

/**
 * BackupCreationService - Creates server backups following Convoy pattern
 */
class BackupCreationService
{
    /**
     * Create a new backup for the server.
     */
    public function handle(Server $server, string $mode = 'snapshot', string $compression = 'zstd'): Backup
    {
        // Check backup limits
        $this->validateBackupLimit($server);

        // Create backup record
        $backup = Backup::create([
            'server_id' => $server->id,
            'name' => $this->generateBackupName($server),
            'status' => 'creating',
        ]);

        // Start backup on Proxmox using repository
        $storage = $server->node->backup_storage ?? 'local';
        
        $repository = new ProxmoxBackupRepository($server);
        $taskId = $repository->create($storage, $mode, $compression);

        $backup->update(['task_id' => $taskId]);

        // Dispatch job to monitor backup progress
        MonitorBackupJob::dispatch($backup);

        // Log activity
        ActivityService::forServer($server, ServerActivity::BACKUP_CREATE->value, [
            'backup_id' => $backup->id,
            'mode' => $mode,
        ]);

        return $backup;
    }

    /**
     * Validate backup limit hasn't been exceeded.
     */
    protected function validateBackupLimit(Server $server): void
    {
        $limit = $server->backup_limit ?? config('midgard.backups.limit', 10);
        $current = $server->backups()->where('status', '!=', 'failed')->count();

        if ($current >= $limit) {
            throw new \Exception("Backup limit of {$limit} has been reached.");
        }
    }

    /**
     * Generate a unique backup name.
     */
    protected function generateBackupName(Server $server): string
    {
        return sprintf(
            'vzdump-qemu-%d-%s',
            $server->vmid,
            now()->format('Y_m_d-H_i_s')
        );
    }
}

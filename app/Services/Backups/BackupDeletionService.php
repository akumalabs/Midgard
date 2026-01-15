<?php

namespace App\Services\Backups;

use App\Enums\Activity\ServerActivity;
use App\Jobs\Backup\WaitUntilBackupIsDeletedJob;
use App\Models\Backup;
use App\Repositories\Proxmox\Server\ProxmoxBackupRepository;
use App\Services\ActivityService;

/**
 * BackupDeletionService - Deletes server backups following Convoy pattern
 */
class BackupDeletionService
{
    /**
     * Delete a backup.
     */
    public function handle(Backup $backup): void
    {
        $server = $backup->server;

        // Mark as deleting
        $backup->update(['status' => 'deleting']);

        // Delete from Proxmox
        // Extract storage from volid (format: storage:backup/filename)
        $volid = $backup->volid;
        $storage = explode(':', $volid)[0] ?? 'local';
        
        $repository = new ProxmoxBackupRepository($server);
        $repository->delete($storage, $volid);

        // Dispatch job to wait for deletion
        WaitUntilBackupIsDeletedJob::dispatch($backup);

        // Log activity
        ActivityService::forServer($server, ServerActivity::BACKUP_DELETE->value, [
            'backup_id' => $backup->id,
            'backup_name' => $backup->name,
        ]);
    }

    /**
     * Force delete (skip Proxmox, just remove record).
     */
    public function forceDelete(Backup $backup): void
    {
        $server = $backup->server;

        // Log activity
        ActivityService::forServer($server, ServerActivity::BACKUP_DELETE->value, [
            'backup_id' => $backup->id,
            'backup_name' => $backup->name,
            'force' => true,
        ]);

        $backup->delete();
    }
}

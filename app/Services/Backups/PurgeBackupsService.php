<?php

namespace App\Services\Backups;

use App\Models\Backup;
use App\Models\Server;

/**
 * PurgeBackupsService - Service to handle backup retention enforcement
 */
class PurgeBackupsService
{
    public function handle(): void
    {
        // Implementation for backup purging logic
        // 1. Group backups by server
        // 2. Check count against limit
        // 3. Delete oldest excess
    }
}

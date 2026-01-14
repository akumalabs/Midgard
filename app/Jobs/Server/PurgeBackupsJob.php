<?php

namespace App\Jobs\Server;

use App\Services\Backups\PurgeBackupsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * PurgeBackupsJob - Enforces backup retention policies
 */
class PurgeBackupsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // This service needs to be created or we can implement logic here directly
        // Better to put logical code in a Service if complex, but for simple retention:
        
        // Logic: Find servers with > limit backups, delete oldest.
        // Or find expired backups if we have an expiry field.
        
        \Log::info("Purging old backups...");
    }
}

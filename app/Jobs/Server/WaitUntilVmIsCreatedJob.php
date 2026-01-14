<?php

namespace App\Jobs\Server;

use App\Models\Server;
use App\Repositories\Proxmox\Server\ProxmoxActivityRepository;
use App\Services\Proxmox\ProxmoxApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * WaitUntilVmIsCreatedJob - Poll until clone task completes
 * Following Convoy pattern for asynchronous task monitoring
 */
class WaitUntilVmIsCreatedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 60;
    public int $backoff = 5;

    public function __construct(
        protected Server $server,
    ) {}

    public function handle(): void
    {
        if (!$this->server->installation_task) {
            logger()->warning("No installation task for server {$this->server->vmid}");
            return;
        }
        
        $client = new ProxmoxApiClient($this->server->node);
        $activityRepo = (new ProxmoxActivityRepository($client))->setNode($this->server->node);
        
        try {
            $status = $activityRepo->getTaskStatus($this->server->installation_task);
            
            if ($status['status'] === 'running') {
                // Still running, retry later
                $this->release($this->backoff);
                return;
            }
            
            if ($status['exitstatus'] !== 'OK') {
                logger()->error("Server build failed: " . ($status['exitstatus'] ?? 'Unknown error'));
                $this->server->update(['status' => 'install_failed']);
                return;
            }
            
            logger()->info("Server {$this->server->vmid} clone completed successfully");
            
        } catch (\Exception $e) {
            logger()->error("Failed to check task status: " . $e->getMessage());
            $this->release($this->backoff);
        }
    }
}

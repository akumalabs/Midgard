<?php

namespace App\Jobs\Server;

use App\Enums\Activity\ServerActivity;
use App\Enums\Server\PowerCommand;
use App\Models\Server;
use App\Repositories\Proxmox\Server\ProxmoxPowerRepository;
use App\Services\ActivityService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * SendPowerCommandJob - Sends power command to server following Convoy pattern
 */
class SendPowerCommandJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        protected Server $server,
        protected PowerCommand $command
    ) {}

    public function handle(): void
    {
        $powerRepo = new ProxmoxPowerRepository($this->server);

        match ($this->command) {
            PowerCommand::START => $powerRepo->start(),
            PowerCommand::STOP => $powerRepo->stop(),
            PowerCommand::SHUTDOWN => $powerRepo->shutdown(),
            PowerCommand::REBOOT => $powerRepo->reboot(),
            PowerCommand::KILL => $powerRepo->kill(),
            PowerCommand::RESET => $powerRepo->reset(),
        };

        // Log activity
        $activity = match ($this->command) {
            PowerCommand::START => ServerActivity::START,
            PowerCommand::STOP => ServerActivity::STOP,
            PowerCommand::SHUTDOWN => ServerActivity::SHUTDOWN,
            PowerCommand::REBOOT => ServerActivity::RESTART,
            PowerCommand::KILL => ServerActivity::KILL,
            PowerCommand::RESET => ServerActivity::RESTART,
        };

        ActivityService::forServer($this->server, $activity->value);
    }

    public function failed(\Throwable $exception): void
    {
        \Log::error("Power command {$this->command->value} failed for server {$this->server->id}: {$exception->getMessage()}");
    }
}

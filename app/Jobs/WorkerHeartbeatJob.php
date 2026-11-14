<?php

namespace App\Jobs;

use App\Models\AppSetting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class WorkerHeartbeatJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        AppSetting::setValue('worker_last_heartbeat_at', now()->toIso8601String());
    }
}

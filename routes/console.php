<?php

use App\Jobs\WorkerHeartbeatJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('aguia:heartbeat', function () {
    WorkerHeartbeatJob::dispatch();
    $this->info('Worker heartbeat dispatched.');
})->purpose('Dispatch a lightweight job to verify queue worker liveness.');

Schedule::job(new WorkerHeartbeatJob())->everyMinute();

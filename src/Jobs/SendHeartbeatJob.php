<?php

namespace LaraFleet\Agent\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use LaraFleet\Agent\HeartbeatRunner;
use Throwable;

class SendHeartbeatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    public function handle(HeartbeatRunner $runner): void
    {
        $runner->run();
    }

    public function failed(Throwable $e): void
    {
        logger()->warning('LaraFleet heartbeat failed: '.$e->getMessage());
    }
}

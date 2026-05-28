<?php

namespace LaraFleet\Agent\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use LaraFleet\Agent\Collectors\ComposerPackageCollector;
use LaraFleet\Agent\Collectors\DeploymentCollector;
use LaraFleet\Agent\Collectors\DiskUsageCollector;
use LaraFleet\Agent\Collectors\EnvSnapshotCollector;
use LaraFleet\Agent\Collectors\LaravelVersionCollector;
use LaraFleet\Agent\Collectors\NpmPackageCollector;
use LaraFleet\Agent\Collectors\PhpVersionCollector;
use LaraFleet\Agent\Collectors\QueueStatusCollector;
use LaraFleet\Agent\Collectors\SchedulerCollector;
use LaraFleet\Agent\Http\HeartbeatClient;
use Throwable;

class SendHeartbeatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    public function handle(HeartbeatClient $client): void
    {
        $collectors = [
            new LaravelVersionCollector,
            new PhpVersionCollector,
            new ComposerPackageCollector,
            new NpmPackageCollector,
            new QueueStatusCollector,
            new SchedulerCollector,
            new DiskUsageCollector,
            new EnvSnapshotCollector,
            new DeploymentCollector,
        ];

        $payload = ['timestamp' => time()];

        foreach ($collectors as $collector) {
            try {
                $payload = array_merge($payload, $collector->collect());
            } catch (Throwable $e) {
                logger()->warning('LaraFleet collector failed: '.get_class($collector).': '.$e->getMessage());
            }
        }

        $client->send($payload);
    }

    public function failed(Throwable $e): void
    {
        logger()->warning('LaraFleet heartbeat failed: '.$e->getMessage());
    }
}

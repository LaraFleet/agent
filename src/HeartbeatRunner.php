<?php

namespace LaraFleet\Agent;

use Illuminate\Support\Facades\Cache;
use LaraFleet\Agent\Collectors\ComposerPackageCollector;
use LaraFleet\Agent\Collectors\Contracts\Collector;
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

class HeartbeatRunner
{
    private const CACHE_KEY = 'larafleet_agent:collector:%s:last_run';

    public function __construct(private HeartbeatClient $client) {}

    public function run(): void
    {
        $payload = ['timestamp' => time()];

        foreach ($this->cheapCollectors() as $collector) {
            $payload = $this->merge($payload, $collector);
        }

        $intervals = (array) config('larafleet-agent.collectors.intervals', []);
        $isFull = false;

        foreach ($this->expensiveGroups() as $name => $collectors) {
            if (! $this->due($name, (int) ($intervals[$name] ?? 3600))) {
                continue;
            }

            foreach ($collectors as $collector) {
                $payload = $this->merge($payload, $collector);
            }

            $isFull = true;
        }

        $payload['type'] = $isFull ? 'full' : 'quick';

        $this->client->send($payload);
    }

    /** @return list<Collector> */
    private function cheapCollectors(): array
    {
        return [
            new QueueStatusCollector,
            new SchedulerCollector,
            new DiskUsageCollector,
        ];
    }

    /**
     * @return array<string, list<Collector>>
     */
    private function expensiveGroups(): array
    {
        return [
            'composer' => [new ComposerPackageCollector],
            'npm' => [new NpmPackageCollector],
            'environment' => [
                new LaravelVersionCollector,
                new PhpVersionCollector,
                new EnvSnapshotCollector,
                new DeploymentCollector,
            ],
        ];
    }

    private function due(string $name, int $intervalSeconds): bool
    {
        $key = sprintf(self::CACHE_KEY, $name);
        $last = (int) Cache::get($key, 0);

        if ((time() - $last) < $intervalSeconds) {
            return false;
        }

        Cache::put($key, time(), $intervalSeconds * 3);

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function merge(array $payload, Collector $collector): array
    {
        try {
            return array_merge($payload, $collector->collect());
        } catch (Throwable $e) {
            logger()->warning('LaraFleet collector failed: '.get_class($collector).': '.$e->getMessage());

            return array_merge($payload, array_fill_keys($collector->keys(), null));
        }
    }
}

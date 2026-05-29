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

/**
 * Orchestriert die Collectoren und sendet den Heartbeat.
 *
 * Single source of truth – wird sowohl vom HeartbeatCommand (synchron im
 * Scheduler) als auch vom SendHeartbeatJob (Queue) aufgerufen.
 *
 * Günstige Collectoren laufen bei jedem Run, teure nur gemäß ihrem Intervall
 * (Cache-gesteuert). Läuft mindestens ein teurer Collector, ist der Heartbeat
 * ein vollständiger Snapshot (type=full), sonst ein Partial-Update (type=quick).
 */
class HeartbeatRunner
{
    /** Cache-Key-Schema für die Fälligkeitsprüfung der teuren Collectoren. */
    private const CACHE_KEY = 'larafleet_agent:collector:%s:last_run';

    public function __construct(private HeartbeatClient $client) {}

    public function run(): void
    {
        $payload = ['timestamp' => time()];

        // 1) Günstige Collectoren – immer.
        foreach ($this->cheapCollectors() as $collector) {
            $payload = $this->merge($payload, $collector);
        }

        // 2) Teure Collectoren – nur wenn laut Intervall fällig.
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

        // 3) Typ ableiten und senden. type ist Pflichtfeld der Zentrale.
        $payload['type'] = $isFull ? 'full' : 'quick';

        $this->client->send($payload);
    }

    /**
     * Günstige Collectoren – laufen bei jedem Heartbeat.
     *
     * @return list<Collector>
     */
    private function cheapCollectors(): array
    {
        return [
            new QueueStatusCollector,
            new SchedulerCollector,
            new DiskUsageCollector,
        ];
    }

    /**
     * Teure Collector-Gruppen – laufen nur gemäß collectors.intervals.
     * Der Schlüssel entspricht dem Intervall-Eintrag und dem Cache-Namespace.
     *
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

    /**
     * Prüft, ob eine Collector-Gruppe laut Intervall fällig ist, und markiert
     * sie bei Fälligkeit sofort als ausgeführt. Beim ersten Run (leerer Cache)
     * sind alle Gruppen fällig → Baseline-Snapshot (type=full).
     */
    private function due(string $name, int $intervalSeconds): bool
    {
        $key = sprintf(self::CACHE_KEY, $name);
        $last = (int) Cache::get($key, 0);

        if ((time() - $last) < $intervalSeconds) {
            return false;
        }

        Cache::put($key, time(), $intervalSeconds * 2); // TTL großzügig > Intervall

        return true;
    }

    /**
     * Führt einen Collector aus und merged das Ergebnis. Fehler werden geloggt,
     * brechen den Heartbeat aber nicht ab.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function merge(array $payload, Collector $collector): array
    {
        try {
            return array_merge($payload, $collector->collect());
        } catch (Throwable $e) {
            logger()->warning('LaraFleet collector failed: '.get_class($collector).': '.$e->getMessage());

            return $payload;
        }
    }
}

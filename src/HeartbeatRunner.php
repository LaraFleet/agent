<?php

namespace LaraFleet\Agent;

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
 * Orchestriert alle Collectoren und sendet einen vollständigen Heartbeat.
 *
 * Single source of truth – wird sowohl vom HeartbeatCommand (synchron im
 * Scheduler) als auch vom SendHeartbeatJob (Queue) aufgerufen.
 *
 * Alle Collectoren laufen bei jedem Run. Schlägt ein Collector fehl, werden
 * seine Keys mit null befüllt – der Heartbeat wird trotzdem gesendet.
 */
class HeartbeatRunner
{
    public function __construct(private HeartbeatClient $client) {}

    public function run(): void
    {
        $payload = ['timestamp' => time(), 'type' => 'full'];

        foreach ($this->collectors() as $collector) {
            $payload = $this->merge($payload, $collector);
        }

        $this->client->send($payload);
    }

    /** @return list<Collector> */
    private function collectors(): array
    {
        return [
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
    }

    /**
     * Führt einen Collector aus und merged das Ergebnis. Fehler werden geloggt;
     * die Keys des Collectors werden mit null befüllt, damit der Heartbeat
     * vollständig bleibt.
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

            return array_merge($payload, array_fill_keys($collector->keys(), null));
        }
    }
}

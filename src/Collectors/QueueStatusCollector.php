<?php

namespace LaraFleet\Agent\Collectors;

use Illuminate\Support\Facades\DB;
use LaraFleet\Agent\Collectors\Contracts\Collector;
use Laravel\Horizon\Contracts\JobRepository;
use Throwable;

class QueueStatusCollector implements Collector
{
    public function keys(): array
    {
        return ['queue'];
    }

    public function collect(): array
    {
        try {
            return [
                'queue' => [
                    'failed_jobs' => $this->countFailedJobs(),
                    'size' => $this->countQueueSize(),
                ],
            ];
        } catch (Throwable) {
            return [
                'queue' => [
                    'failed_jobs' => null,
                    'size' => null,
                ],
            ];
        }
    }

    private function countFailedJobs(): int
    {
        if (DB::getSchemaBuilder()->hasTable('failed_jobs')) {
            return (int) DB::table('failed_jobs')->count();
        }

        return 0;
    }

    private function countQueueSize(): int
    {
        $connection = config('queue.default');

        if ($connection === 'database' && DB::getSchemaBuilder()->hasTable('jobs')) {
            return (int) DB::table('jobs')->count();
        }

        if ($connection === 'redis' && class_exists(JobRepository::class)) {
            try {
                return app(JobRepository::class)->countRecent();
            } catch (Throwable) {
                // Horizon nicht initialisiert
            }
        }

        return 0;
    }
}

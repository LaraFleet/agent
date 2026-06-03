<?php

namespace LaraFleet\Agent\Collectors;

use LaraFleet\Agent\Collectors\Contracts\Collector;
use Throwable;

class SchedulerCollector implements Collector
{
    private const CACHE_KEY = 'larafleet_agent_scheduler_last_run';

    public function keys(): array
    {
        return ['scheduler'];
    }

    public function collect(): array
    {
        try {
            $intervalMinutes = (int) config('larafleet-agent.interval_minutes', 60);
            $lastRun = cache()->get(self::CACHE_KEY);

            $missed = $lastRun === null || now()->diffInMinutes($lastRun) > (int) ceil($intervalMinutes * 1.5);

            cache()->put(self::CACHE_KEY, now(), now()->addMinutes($intervalMinutes * 3));

            return [
                'scheduler' => [
                    'last_run_at' => $lastRun?->toIso8601String(),
                    'missed' => $missed,
                ],
            ];
        } catch (Throwable) {
            return [
                'scheduler' => [
                    'last_run_at' => null,
                    'missed' => null,
                ],
            ];
        }
    }
}

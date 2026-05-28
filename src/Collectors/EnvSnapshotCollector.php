<?php

namespace LaraFleet\Agent\Collectors;

use LaraFleet\Agent\Collectors\Contracts\Collector;

class EnvSnapshotCollector implements Collector
{
    public function collect(): array
    {
        $whitelist = config('larafleet-agent.env_whitelist', []);
        $snapshot = [];

        foreach ($whitelist as $key) {
            $value = env($key);
            if ($value !== null) {
                $snapshot[$key] = (string) $value;
            }
        }

        return [
            'env_snapshot' => $snapshot,
        ];
    }
}

<?php

namespace LaraFleet\Agent\Collectors;

use Illuminate\Foundation\Application;
use LaraFleet\Agent\Collectors\Contracts\Collector;

class LaravelVersionCollector implements Collector
{
    public function keys(): array
    {
        return ['laravel_version'];
    }

    public function collect(): array
    {
        return [
            'laravel_version' => Application::VERSION,
        ];
    }
}

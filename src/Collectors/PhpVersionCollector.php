<?php

namespace LaraFleet\Agent\Collectors;

use LaraFleet\Agent\Collectors\Contracts\Collector;

class PhpVersionCollector implements Collector
{
    public function keys(): array
    {
        return ['php_version', 'php_extensions'];
    }

    public function collect(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'php_extensions' => get_loaded_extensions(),
        ];
    }
}

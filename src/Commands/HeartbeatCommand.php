<?php

namespace LaraFleet\Agent\Commands;

use Illuminate\Console\Command;
use LaraFleet\Agent\HeartbeatRunner;

class HeartbeatCommand extends Command
{
    protected $signature = 'larafleet:heartbeat';

    protected $description = 'Sendet einen LaraFleet-Heartbeat an die Zentrale';

    public function handle(HeartbeatRunner $runner): int
    {
        $runner->run();

        return self::SUCCESS;
    }
}

<?php

namespace LaraFleet\Agent;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use LaraFleet\Agent\Commands\InstallCommand;
use LaraFleet\Agent\Jobs\SendHeartbeatJob;

class AgentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/larafleet-agent.php',
            'larafleet-agent'
        );
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/larafleet-agent.php' => config_path('larafleet-agent.php'),
        ], 'larafleet-agent-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
            ]);
        }

        if (config('larafleet-agent.api_key')) {
            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);
                $interval = (int) config('larafleet-agent.interval_minutes', 1);

                $job = $schedule->job(SendHeartbeatJob::class);

                if ($interval === 1) {
                    $job->everyMinute();
                } else {
                    $job->cron("*/{$interval} * * * *");
                }

                $job->withoutOverlapping();
            });
        }
    }
}

<?php

namespace LaraFleet\Agent;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use LaraFleet\Agent\Commands\HeartbeatCommand;
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
                HeartbeatCommand::class,
            ]);
        }

        if (config('larafleet-agent.api_key')) {
            $this->app->booted(function () {
                $this->scheduleHeartbeat($this->app->make(Schedule::class));
            });
        }
    }

    /**
     * Registriert den Heartbeat im Scheduler – als synchroner Command (Default,
     * benötigt nur den Standard-Cron) oder als Queue-Job (benötigt einen Worker).
     */
    private function scheduleHeartbeat(Schedule $schedule): void
    {
        $interval = max(1, (int) config('larafleet-agent.interval_minutes', 5));

        if (config('larafleet-agent.dispatch') === 'job') {
            $event = $schedule->job(SendHeartbeatJob::class, config('larafleet-agent.queue'));
        } else {
            $event = $schedule->command('larafleet:heartbeat');
        }

        $this->applyInterval($event, $interval)->withoutOverlapping();
    }

    private function applyInterval(Event $event, int $interval): Event
    {
        return $interval === 1
            ? $event->everyMinute()
            : $event->cron("*/{$interval} * * * *");
    }
}

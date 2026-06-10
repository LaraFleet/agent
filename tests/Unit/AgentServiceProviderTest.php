<?php

namespace LaraFleet\Agent\Tests\Unit;

use LaraFleet\Agent\AgentServiceProvider;
use Orchestra\Testbench\TestCase;

class AgentServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [AgentServiceProvider::class];
    }

    public function test_deep_merge_fills_missing_nested_keys(): void
    {
        // Simuliert einen Nutzer mit alter published config (nur composer, kein npm/environment)
        $this->app['config']->set('larafleet-agent.collectors.intervals', [
            'composer' => 1800,
        ]);

        (new AgentServiceProvider($this->app))->register();

        $intervals = config('larafleet-agent.collectors.intervals');

        $this->assertSame(1800, $intervals['composer']);   // Nutzerwert bleibt erhalten
        $this->assertSame(3600, $intervals['npm']);         // Package-Default aufgefüllt
        $this->assertSame(3600, $intervals['environment']); // Package-Default aufgefüllt
    }

    public function test_deep_merge_does_not_overwrite_user_top_level_values(): void
    {
        $this->app['config']->set('larafleet-agent.timeout', 30);

        (new AgentServiceProvider($this->app))->register();

        $this->assertSame(30, config('larafleet-agent.timeout'));
    }

    public function test_deep_merge_fills_entirely_missing_top_level_key(): void
    {
        // Nutzer hat collectors komplett aus seiner Config entfernt
        $this->app['config']->set('larafleet-agent', array_diff_key(
            config('larafleet-agent'),
            ['collectors' => null]
        ));

        (new AgentServiceProvider($this->app))->register();

        $this->assertSame(3600, config('larafleet-agent.collectors.intervals.composer'));
    }
}

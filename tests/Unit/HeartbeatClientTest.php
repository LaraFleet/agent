<?php

namespace LaraFleet\Agent\Tests\Unit;

use LaraFleet\Agent\AgentServiceProvider;
use LaraFleet\Agent\Http\HeartbeatClient;
use Orchestra\Testbench\TestCase;
use RuntimeException;

class HeartbeatClientTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [AgentServiceProvider::class];
    }

    public function test_throws_when_api_key_missing(): void
    {
        $this->app['config']->set('larafleet-agent.api_key', null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/LARAFLEET_API_KEY/');

        (new HeartbeatClient)->send(['test' => true]);
    }

    public function test_signature_format(): void
    {
        $client = new HeartbeatClient;
        $reflection = new \ReflectionMethod($client, 'sign');
        $reflection->setAccessible(true);

        $signature = $reflection->invoke($client, 1234567890, '{"test":true}', 'mykey');

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $signature);
    }
}

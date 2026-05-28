<?php

namespace LaraFleet\Agent\Tests\Feature;

use Illuminate\Support\Facades\Http;
use LaraFleet\Agent\AgentServiceProvider;
use LaraFleet\Agent\Jobs\SendHeartbeatJob;
use Orchestra\Testbench\TestCase;

class HeartbeatTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [AgentServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('larafleet-agent.api_key', 'test-api-key-64chars');
        $app['config']->set('larafleet-agent.endpoint', 'https://larafleet.test/api/heartbeat');
        $app['config']->set('larafleet-agent.npm_enabled', false);
    }

    public function test_heartbeat_sends_post_with_correct_headers(): void
    {
        Http::fake([
            'https://larafleet.test/api/heartbeat' => Http::response(['ok' => true], 200),
        ]);

        dispatch_sync(new SendHeartbeatJob);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://larafleet.test/api/heartbeat'
                && $request->method() === 'POST'
                && $request->hasHeader('X-LaraFleet-Signature')
                && $request->hasHeader('X-LaraFleet-Timestamp')
                && $request->hasHeader('X-LaraFleet-Api-Key', 'test-api-key-64chars');
        });
    }

    public function test_heartbeat_payload_contains_required_fields(): void
    {
        Http::fake([
            'https://larafleet.test/api/heartbeat' => Http::response(['ok' => true], 200),
        ]);

        dispatch_sync(new SendHeartbeatJob);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return isset($body['timestamp'])
                && isset($body['laravel_version'])
                && isset($body['php_version'])
                && isset($body['composer_packages'])
                && isset($body['queue'])
                && isset($body['scheduler'])
                && isset($body['env_snapshot']);
        });
    }

    public function test_signature_is_valid_hmac(): void
    {
        Http::fake([
            'https://larafleet.test/api/heartbeat' => Http::response(['ok' => true], 200),
        ]);

        dispatch_sync(new SendHeartbeatJob);

        Http::assertSent(function ($request) {
            $timestamp = (int) $request->header('X-LaraFleet-Timestamp')[0];
            $signature = str_replace('sha256=', '', $request->header('X-LaraFleet-Signature')[0]);
            $body = $request->body();
            $apiKey = 'test-api-key-64chars';

            $expected = hash_hmac('sha256', $timestamp.'.'.$body, $apiKey);

            return hash_equals($expected, $signature);
        });
    }

    public function test_heartbeat_fails_silently_when_server_unreachable(): void
    {
        Http::fake([
            'https://larafleet.test/api/heartbeat' => Http::response(null, 500),
        ]);

        $this->expectNotToPerformAssertions();
        dispatch_sync(new SendHeartbeatJob);
    }
}

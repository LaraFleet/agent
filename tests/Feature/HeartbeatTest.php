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

    private function fakeEndpoint(): void
    {
        Http::fake([
            'https://larafleet.test/api/heartbeat' => Http::response(['ok' => true], 200),
        ]);
    }

    public function test_heartbeat_sends_post_with_correct_headers(): void
    {
        $this->fakeEndpoint();

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
        $this->fakeEndpoint();

        dispatch_sync(new SendHeartbeatJob);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return isset($body['timestamp'])
                && isset($body['type'])
                && isset($body['laravel_version'])
                && isset($body['php_version'])
                && array_key_exists('composer_packages', $body)
                && array_key_exists('composer_advisories', $body)
                && isset($body['queue'])
                && isset($body['scheduler'])
                && isset($body['env_snapshot']);
        });
    }

    public function test_signature_is_valid_hmac(): void
    {
        $this->fakeEndpoint();

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

    public function test_every_run_is_full_snapshot(): void
    {
        $this->fakeEndpoint();

        dispatch_sync(new SendHeartbeatJob);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return ($body['type'] ?? null) === 'full'
                && array_key_exists('composer_packages', $body)
                && array_key_exists('composer_advisories', $body)
                && isset($body['laravel_version']);
        });
    }

    public function test_second_run_is_also_full_snapshot(): void
    {
        $this->fakeEndpoint();

        dispatch_sync(new SendHeartbeatJob);
        dispatch_sync(new SendHeartbeatJob);

        $fullCount = collect(Http::recorded())
            ->filter(fn ($pair) => (json_decode($pair[0]->body(), true)['type'] ?? null) === 'full')
            ->count();

        $this->assertSame(2, $fullCount);
    }

    public function test_npm_disabled_sends_null_values(): void
    {
        $this->fakeEndpoint();

        dispatch_sync(new SendHeartbeatJob);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return array_key_exists('npm_packages', $body)
                && $body['npm_packages'] === null
                && array_key_exists('npm_advisories', $body)
                && $body['npm_advisories'] === null;
        });
    }

    public function test_command_sends_heartbeat(): void
    {
        $this->fakeEndpoint();

        $this->artisan('larafleet:heartbeat')->assertSuccessful();

        Http::assertSent(fn ($request) => (json_decode($request->body(), true)['type'] ?? null) === 'full');
    }
}

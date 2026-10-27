<?php

namespace LaraFleet\Agent\Tests\Unit\Collectors;

use Illuminate\Support\Facades\File;
use LaraFleet\Agent\Collectors\NpmPackageCollector;
use Orchestra\Testbench\TestCase;

class NpmAuditTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/larafleet-npm-'.bin2hex(random_bytes(6));
        mkdir($this->dir);
        file_put_contents($this->dir.'/package.json', '{}');
        file_put_contents($this->dir.'/package-lock.json', '{"packages":{}}');

        $this->app->setBasePath($this->dir);
        config()->set('larafleet-agent.npm_enabled', true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    /**
     * @param  array{exit_code: ?int, output: string, error_output: string}  $audit
     */
    private function collector(array $audit): NpmPackageCollector
    {
        return new class($audit) extends NpmPackageCollector
        {
            /** @var list<string> */
            public array $commands = [];

            public function __construct(private array $audit) {}

            protected function runCommand(string $commandLine): array
            {
                $this->commands[] = $commandLine;

                return str_contains($commandLine, ' audit ')
                    ? $this->audit
                    : ['exit_code' => 0, 'output' => '{}', 'error_output' => ''];
            }
        };
    }

    public function test_keys_contain_audit_error(): void
    {
        $this->assertContains('npm_audit_error', (new NpmPackageCollector)->keys());
    }

    public function test_success_with_vulnerabilities_and_non_zero_exit_code(): void
    {
        $json = json_encode(['vulnerabilities' => ['lodash' => ['via' => [[
            'url' => 'https://github.com/advisories/GHSA-x',
            'severity' => 'critical',
            'title' => 'Prototype Pollution',
        ]]]]]);

        $result = $this->collector(['exit_code' => 1, 'output' => $json, 'error_output' => ''])->collect();

        $this->assertNull($result['npm_audit_error']);
        $this->assertCount(1, $result['npm_advisories']);
        $this->assertSame('lodash', $result['npm_advisories'][0]['package']);
    }

    public function test_success_without_vulnerabilities(): void
    {
        $result = $this->collector(['exit_code' => 0, 'output' => '{"vulnerabilities":{}}', 'error_output' => ''])->collect();

        $this->assertSame([], $result['npm_advisories']);
        $this->assertNull($result['npm_audit_error']);
    }

    public function test_empty_output_is_an_error(): void
    {
        $result = $this->collector(['exit_code' => 127, 'output' => '', 'error_output' => 'sh: 1: npm: not found'])->collect();

        $this->assertNull($result['npm_advisories']);
        $this->assertSame('npm audit returned no output (exit code 127): sh: 1: npm: not found', $result['npm_audit_error']);
    }

    public function test_invalid_json_is_an_error(): void
    {
        $result = $this->collector(['exit_code' => 0, 'output' => 'not json', 'error_output' => ''])->collect();

        $this->assertNull($result['npm_advisories']);
        $this->assertSame('npm audit returned invalid JSON (exit code 0)', $result['npm_audit_error']);
    }

    public function test_npm_error_json_is_an_error(): void
    {
        $result = $this->collector(['exit_code' => 1, 'output' => '{"error":{"code":"ENOAUDIT","summary":"x"}}', 'error_output' => ''])->collect();

        $this->assertNull($result['npm_advisories']);
        $this->assertSame('npm audit failed with ENOAUDIT (exit code 1)', $result['npm_audit_error']);
    }

    public function test_audit_is_skipped_without_package_lock(): void
    {
        unlink($this->dir.'/package-lock.json');

        $collector = $this->collector(['exit_code' => 127, 'output' => '', 'error_output' => '']);
        $result = $collector->collect();

        $this->assertNull($result['npm_advisories']);
        $this->assertNull($result['npm_audit_error']);
        $this->assertSame(['npm outdated --json'], $collector->commands);
    }

    public function test_disabled_npm_returns_null_keys(): void
    {
        config()->set('larafleet-agent.npm_enabled', false);

        $this->assertSame(
            ['npm_packages' => null, 'npm_advisories' => null, 'npm_audit_error' => null],
            (new NpmPackageCollector)->collect(),
        );
    }

    public function test_uses_configured_binary(): void
    {
        config()->set('larafleet-agent.npm_binary', '/opt/plesk/node/22/bin/node /opt/plesk/node/22/bin/npm');

        $collector = $this->collector(['exit_code' => 0, 'output' => '{"vulnerabilities":{}}', 'error_output' => '']);
        $collector->collect();

        $this->assertContains('/opt/plesk/node/22/bin/node /opt/plesk/node/22/bin/npm audit --json', $collector->commands);
    }
}

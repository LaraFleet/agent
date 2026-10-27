<?php

namespace LaraFleet\Agent\Tests\Unit\Collectors;

use Illuminate\Support\Facades\File;
use LaraFleet\Agent\Collectors\ComposerPackageCollector;
use Orchestra\Testbench\TestCase;

class ComposerAuditTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        // Leeres Projektverzeichnis: ohne composer.lock läuft nur composer audit.
        $this->dir = sys_get_temp_dir().'/larafleet-composer-'.bin2hex(random_bytes(6));
        mkdir($this->dir);
        $this->app->setBasePath($this->dir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    /**
     * @param  array{exit_code: ?int, output: string, error_output: string}  $result
     */
    private function collector(array $result): ComposerPackageCollector
    {
        return new class($result) extends ComposerPackageCollector
        {
            /** @var list<string> */
            public array $commands = [];

            public function __construct(private array $result) {}

            protected function runCommand(string $commandLine): array
            {
                $this->commands[] = $commandLine;

                return $this->result;
            }
        };
    }

    public function test_keys_contain_audit_error(): void
    {
        $this->assertContains('composer_audit_error', (new ComposerPackageCollector)->keys());
    }

    public function test_success_with_advisories_and_non_zero_exit_code(): void
    {
        $json = json_encode(['advisories' => ['vendor/pkg' => [[
            'cve' => 'CVE-2026-0001',
            'severity' => 'HIGH',
            'title' => 'RCE',
            'link' => 'https://example.test',
        ]]]]);

        $result = $this->collector(['exit_code' => 1, 'output' => $json, 'error_output' => 'Found 1 security vulnerability advisory'])->collect();

        $this->assertNull($result['composer_audit_error']);
        $this->assertCount(1, $result['composer_advisories']);
        $this->assertSame('vendor/pkg', $result['composer_advisories'][0]['package']);
        $this->assertSame('high', $result['composer_advisories'][0]['severity']);
    }

    public function test_success_without_advisories(): void
    {
        $result = $this->collector(['exit_code' => 0, 'output' => '{"advisories":[],"abandoned":[]}', 'error_output' => ''])->collect();

        $this->assertSame([], $result['composer_advisories']);
        $this->assertNull($result['composer_audit_error']);
    }

    public function test_empty_output_is_an_error(): void
    {
        $result = $this->collector(['exit_code' => 127, 'output' => '', 'error_output' => "sh: 1: composer: not found\nmore"])->collect();

        $this->assertNull($result['composer_advisories']);
        $this->assertSame('composer audit returned no output (exit code 127): sh: 1: composer: not found', $result['composer_audit_error']);
    }

    public function test_invalid_json_is_an_error(): void
    {
        $result = $this->collector(['exit_code' => 0, 'output' => 'PHP Warning: something', 'error_output' => ''])->collect();

        $this->assertNull($result['composer_advisories']);
        $this->assertSame('composer audit returned invalid JSON (exit code 0)', $result['composer_audit_error']);
    }

    public function test_json_without_advisories_key_is_an_error(): void
    {
        $result = $this->collector(['exit_code' => 0, 'output' => '{"foo":1}', 'error_output' => ''])->collect();

        $this->assertNull($result['composer_advisories']);
        $this->assertStringStartsWith('composer audit returned invalid JSON', $result['composer_audit_error']);
    }

    public function test_error_is_sanitized_and_truncated(): void
    {
        $stderr = 'Failed to clone https://user:secret@git.example.test/repo.git in '.base_path().'/vendor '.str_repeat('x', 600);

        $error = $this->collector(['exit_code' => 1, 'output' => '', 'error_output' => $stderr])->collect()['composer_audit_error'];

        $this->assertStringNotContainsString('secret', $error);
        $this->assertStringNotContainsString(base_path(), $error);
        $this->assertStringContainsString('https://***@git.example.test', $error);
        $this->assertSame(500, mb_strlen($error));
    }

    public function test_uses_configured_binary(): void
    {
        config()->set('larafleet-agent.composer_binary', '/opt/plesk/php/8.3/bin/php /usr/local/bin/composer');

        $collector = $this->collector(['exit_code' => 0, 'output' => '{"advisories":[]}', 'error_output' => '']);
        $collector->collect();

        $this->assertSame(['/opt/plesk/php/8.3/bin/php /usr/local/bin/composer audit --format=json --no-interaction --no-ansi'], $collector->commands);
    }

    public function test_real_process_keeps_stderr_out_of_json(): void
    {
        file_put_contents($this->dir.'/fake-composer.php', '<?php fwrite(STDERR, "warning\n"); echo json_encode(["advisories" => []]); exit(1);');
        config()->set('larafleet-agent.composer_binary', escapeshellarg(PHP_BINARY).' '.escapeshellarg($this->dir.'/fake-composer.php'));

        $result = (new ComposerPackageCollector)->collect();

        $this->assertSame([], $result['composer_advisories']);
        $this->assertNull($result['composer_audit_error']);
    }
}

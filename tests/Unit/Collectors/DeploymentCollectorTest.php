<?php

namespace LaraFleet\Agent\Tests\Unit\Collectors;

use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use LaraFleet\Agent\Collectors\DeploymentCollector;
use Orchestra\Testbench\TestCase;

class DeploymentCollectorTest extends TestCase
{
    private const HASH = '0123456789abcdef0123456789abcdef01234567';

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/larafleet-deploy-'.bin2hex(random_bytes(6));
        mkdir($this->dir);

        $this->app->setBasePath($this->dir);
        config()->set('larafleet-agent.deployment_file', 'vendor/autoload.php');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    private function writeFile(string $path, string $content, int $mtime): void
    {
        $full = $this->dir.'/'.$path;

        if (! is_dir(dirname($full))) {
            mkdir(dirname($full), 0777, true);
        }

        file_put_contents($full, $content);
        touch($full, $mtime);
    }

    private function iso(int $timestamp): string
    {
        return Carbon::createFromTimestamp($timestamp)->toIso8601String();
    }

    public function test_returns_null_when_no_candidate_exists(): void
    {
        $this->assertSame(
            ['deployment_at' => null, 'deployment_hash' => null],
            (new DeploymentCollector)->collect(),
        );
    }

    public function test_uses_deployment_file_when_it_is_the_only_candidate(): void
    {
        $this->writeFile('vendor/autoload.php', '<?php', 1_700_000_000);

        $result = (new DeploymentCollector)->collect();

        $this->assertSame($this->iso(1_700_000_000), $result['deployment_at']);
        $this->assertNull($result['deployment_hash']);
    }

    public function test_respects_configured_deployment_file(): void
    {
        config()->set('larafleet-agent.deployment_file', 'RELEASE');
        $this->writeFile('vendor/autoload.php', '<?php', 1_800_000_000);
        $this->writeFile('RELEASE', '42', 1_700_000_000);

        $this->assertSame($this->iso(1_700_000_000), (new DeploymentCollector)->collect()['deployment_at']);
    }

    public function test_uses_newest_mtime_of_git_ref_file(): void
    {
        $this->writeFile('vendor/autoload.php', '<?php', 1_700_000_000);
        $this->writeFile('vendor/composer/installed.json', '{}', 1_700_000_100);
        $this->writeFile('.git/HEAD', "ref: refs/heads/master\n", 1_600_000_000);
        $this->writeFile('.git/refs/heads/master', self::HASH."\n", 1_700_000_500);

        $result = (new DeploymentCollector)->collect();

        $this->assertSame($this->iso(1_700_000_500), $result['deployment_at']);
        $this->assertSame(self::HASH, $result['deployment_hash']);
    }

    public function test_uses_newest_mtime_of_installed_json(): void
    {
        $this->writeFile('vendor/autoload.php', '<?php', 1_700_000_000);
        $this->writeFile('vendor/composer/installed.json', '{}', 1_700_000_900);
        $this->writeFile('.git/HEAD', "ref: refs/heads/master\n", 1_600_000_000);
        $this->writeFile('.git/refs/heads/master', self::HASH."\n", 1_700_000_500);

        $this->assertSame($this->iso(1_700_000_900), (new DeploymentCollector)->collect()['deployment_at']);
    }

    public function test_uses_packed_refs_when_ref_is_only_packed(): void
    {
        $this->writeFile('vendor/autoload.php', '<?php', 1_700_000_000);
        $this->writeFile('.git/HEAD', "ref: refs/heads/master\n", 1_600_000_000);
        $this->writeFile('.git/packed-refs', "# pack-refs with: peeled fully-peeled sorted\n".self::HASH." refs/heads/master\n", 1_700_000_700);

        $result = (new DeploymentCollector)->collect();

        $this->assertSame($this->iso(1_700_000_700), $result['deployment_at']);
        $this->assertSame(self::HASH, $result['deployment_hash']);
    }

    public function test_ignores_packed_refs_without_the_checked_out_ref(): void
    {
        $this->writeFile('vendor/autoload.php', '<?php', 1_700_000_000);
        $this->writeFile('.git/HEAD', "ref: refs/heads/master\n", 1_600_000_000);
        $this->writeFile('.git/packed-refs', self::HASH." refs/heads/develop\n", 1_700_000_700);

        $this->assertSame($this->iso(1_700_000_000), (new DeploymentCollector)->collect()['deployment_at']);
    }

    public function test_uses_head_file_when_detached(): void
    {
        $this->writeFile('vendor/autoload.php', '<?php', 1_700_000_000);
        $this->writeFile('.git/HEAD', self::HASH."\n", 1_700_000_300);
        $this->writeFile('.git/refs/heads/master', str_repeat('f', 40)."\n", 1_700_000_200);

        $result = (new DeploymentCollector)->collect();

        $this->assertSame($this->iso(1_700_000_300), $result['deployment_at']);
        $this->assertSame(self::HASH, $result['deployment_hash']);
    }

    public function test_works_without_deployment_file(): void
    {
        $this->writeFile('.git/HEAD', "ref: refs/heads/master\n", 1_600_000_000);
        $this->writeFile('.git/refs/heads/master', self::HASH."\n", 1_700_000_500);

        $result = (new DeploymentCollector)->collect();

        $this->assertSame($this->iso(1_700_000_500), $result['deployment_at']);
        $this->assertSame(self::HASH, $result['deployment_hash']);
    }
}

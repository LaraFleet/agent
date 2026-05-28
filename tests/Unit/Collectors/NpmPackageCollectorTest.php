<?php

namespace LaraFleet\Agent\Tests\Unit\Collectors;

use LaraFleet\Agent\Collectors\NpmPackageCollector;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class NpmPackageCollectorTest extends TestCase
{
    private ReflectionMethod $method;

    protected function setUp(): void
    {
        $this->method = new ReflectionMethod(NpmPackageCollector::class, 'resolveUpdateType');
        $this->method->setAccessible(true);
    }

    private function resolve(string $current, string $latest): string
    {
        return $this->method->invoke(new NpmPackageCollector, $current, $latest);
    }

    public function test_major_update(): void
    {
        $this->assertSame('major', $this->resolve('1.2.3', '2.0.0'));
    }

    public function test_minor_update(): void
    {
        $this->assertSame('minor', $this->resolve('1.2.3', '1.3.0'));
    }

    public function test_patch_update(): void
    {
        $this->assertSame('patch', $this->resolve('1.2.3', '1.2.4'));
    }

    public function test_strips_caret_prefix(): void
    {
        $this->assertSame('major', $this->resolve('^1.0.0', '^2.0.0'));
    }

    public function test_strips_tilde_prefix(): void
    {
        $this->assertSame('minor', $this->resolve('~1.2.0', '~1.3.0'));
    }

    public function test_strips_v_prefix(): void
    {
        $this->assertSame('patch', $this->resolve('v1.2.3', 'v1.2.4'));
    }

    public function test_same_version_returns_patch(): void
    {
        $this->assertSame('patch', $this->resolve('1.0.0', '1.0.0'));
    }
}

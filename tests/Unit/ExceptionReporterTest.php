<?php

namespace LaraFleet\Agent\Tests\Unit;

use Illuminate\Validation\ValidationException;
use LaraFleet\Agent\AgentServiceProvider;
use LaraFleet\Agent\Http\ExceptionReporter;
use Orchestra\Testbench\TestCase;
use RuntimeException;

class ExceptionReporterTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [AgentServiceProvider::class];
    }

    public function test_should_report_returns_false_for_listed_exception(): void
    {
        $reporter = new ExceptionReporter;

        $e = ValidationException::withMessages(['field' => 'error']);

        $this->assertFalse($reporter->shouldReport($e));
    }

    public function test_should_report_returns_true_for_unlisted_exception(): void
    {
        $reporter = new ExceptionReporter;

        $this->assertTrue($reporter->shouldReport(new RuntimeException('test')));
    }

    public function test_filter_input_replaces_dontflash_keys(): void
    {
        $reporter = new ExceptionReporter;

        $result = $reporter->filterInput([
            'name' => 'Max',
            'password' => 'secret',
            'email' => 'max@example.com',
        ]);

        $this->assertSame('Max', $result['name']);
        $this->assertSame('[FILTERED]', $result['password']);
        $this->assertSame('max@example.com', $result['email']);
    }

    public function test_filter_input_is_case_insensitive(): void
    {
        $reporter = new ExceptionReporter;

        $result = $reporter->filterInput(['PASSWORD' => 'secret']);

        $this->assertSame('[FILTERED]', $result['PASSWORD']);
    }

    public function test_filter_input_leaves_unlisted_keys_untouched(): void
    {
        $reporter = new ExceptionReporter;

        $result = $reporter->filterInput(['ref' => 'campaign_x', 'page' => '2']);

        $this->assertSame('campaign_x', $result['ref']);
        $this->assertSame('2', $result['page']);
    }
}

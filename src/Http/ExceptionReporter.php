<?php

namespace LaraFleet\Agent\Http;

use Illuminate\Support\Facades\Http;

class ExceptionReporter
{
    public function report(\Throwable $e): void
    {
        try {
            if (! $this->shouldReport($e)) {
                return;
            }

            $apiKey = config('larafleet-agent.api_key');
            if (empty($apiKey)) {
                return;
            }

            $payload = $this->buildPayload($e);
            $timestamp = time();
            $body = json_encode($payload, JSON_THROW_ON_ERROR);
            $signature = $this->sign($timestamp, $body, $apiKey);

            Http::timeout(config('larafleet-agent.timeout', 10))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-LaraFleet-Api-Key' => $apiKey,
                    'X-LaraFleet-Signature' => 'sha256='.$signature,
                    'X-LaraFleet-Timestamp' => (string) $timestamp,
                ])
                ->send('POST', $this->exceptionsEndpoint(), ['body' => $body]);
        } catch (\Throwable $ex) {
            logger()->warning('LaraFleet exception reporting failed: '.$ex->getMessage());
        }
    }

    public function shouldReport(\Throwable $e): bool
    {
        foreach (config('larafleet-agent.exceptions.dontReport', []) as $class) {
            if ($e instanceof $class) {
                return false;
            }
        }

        return true;
    }

    public function filterInput(array $input): array
    {
        $dontFlash = array_map('strtolower', config('larafleet-agent.exceptions.dontFlash', []));

        $result = [];
        foreach ($input as $key => $value) {
            $result[$key] = in_array(strtolower((string) $key), $dontFlash, true)
                ? '[FILTERED]'
                : $value;
        }

        return $result;
    }

    public function buildPayload(\Throwable $e): array
    {
        return [
            'exception' => $this->buildExceptionBlock($e),
            'request' => $this->buildRequestBlock(),
            'context' => [
                'laravel_version' => app()->version(),
                'php_version' => PHP_VERSION,
                'environment' => app()->environment(),
            ],
            'occurred_at' => now()->toIso8601String(),
        ];
    }

    private function buildExceptionBlock(\Throwable $e): array
    {
        $trace = array_map(
            fn ($frame) => ($frame['file'] ?? '[internal]').':'.($frame['line'] ?? '?')
                .' '.($frame['class'] ?? '').($frame['type'] ?? '').($frame['function'] ?? ''),
            $e->getTrace()
        );

        return [
            'class' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $trace,
            'fingerprint' => hash('sha256', get_class($e).$e->getMessage().$e->getFile().$e->getLine()),
        ];
    }

    private function buildRequestBlock(): ?array
    {
        if (app()->runningInConsole()) {
            return null;
        }

        try {
            $request = app('request');

            return [
                'url' => $request->url(),
                'method' => $request->method(),
                'query' => $this->filterInput($request->query()),
                'input' => $this->filterInput($request->except($request->query())),
                'user_id' => optional(auth()->user())?->getAuthIdentifier(),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function exceptionsEndpoint(): string
    {
        return preg_replace('#/api/[^/?]+$#', '/api/exceptions', config('larafleet-agent.endpoint'));
    }

    private function sign(int $timestamp, string $body, string $apiKey): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$body, $apiKey);
    }
}

<?php

namespace LaraFleet\Agent\Http;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HeartbeatClient
{
    public function send(array $payload): void
    {
        $apiKey = config('larafleet-agent.api_key');

        if (empty($apiKey)) {
            throw new RuntimeException('LaraFleet: LARAFLEET_API_KEY ist nicht gesetzt.');
        }

        $endpoint = config('larafleet-agent.endpoint');
        $timestamp = time();
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = $this->sign($timestamp, $body, $apiKey);

        try {
            Http::timeout(config('larafleet-agent.timeout', 10))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-LaraFleet-Api-Key' => $apiKey,
                    'X-LaraFleet-Signature' => 'sha256='.$signature,
                    'X-LaraFleet-Timestamp' => (string) $timestamp,
                ])
                ->send('POST', $endpoint, ['body' => $body]);
        } catch (ConnectionException $e) {
            logger()->warning('LaraFleet heartbeat connection failed: '.$e->getMessage());
        }
    }

    /**
     * HMAC-SHA256: timestamp + "." + json-body, signiert mit API-Key.
     * Muss exakt so in der LaraFleet-Zentrale reproduzierbar sein.
     */
    private function sign(int $timestamp, string $body, string $apiKey): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$body, $apiKey);
    }
}

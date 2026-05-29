<?php

namespace LaraFleet\Agent\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class InstallCommand extends Command
{
    protected $signature = 'larafleet:install
                            {--api-key= : API-Key aus der LaraFleet-Zentrale}
                            {--endpoint= : URL der LaraFleet-Zentrale (optional)}';

    protected $description = 'LaraFleet Agent installieren und konfigurieren';

    public function handle(): int
    {
        $this->info('LaraFleet Agent – Installation');
        $this->newLine();

        $this->call('vendor:publish', [
            '--tag' => 'larafleet-agent-config',
            '--force' => false,
        ]);

        $apiKey = $this->option('api-key') ?? $this->ask('API-Key aus der LaraFleet-Zentrale');

        if (empty($apiKey)) {
            $this->error('Kein API-Key angegeben. Installation abgebrochen.');

            return self::FAILURE;
        }

        $endpoint = $this->option('endpoint') ?? 'https://app.larafleet.com/api/heartbeat';

        $this->addToEnv('LARAFLEET_ENDPOINT', $endpoint);
        $this->addToEnv('LARAFLEET_API_KEY', $apiKey);

        $this->newLine();
        $this->info('✓ .env wurde aktualisiert.');
        $this->info('✓ LaraFleet Agent ist einsatzbereit.');
        $this->newLine();
        $this->line('Der Agent sendet alle 5 Minuten einen Heartbeat über den Laravel Scheduler.');
        $this->line('Stelle sicher, dass der Scheduler läuft:');
        $this->comment('  * * * * * php /pfad-zur-app/artisan schedule:run >> /dev/null 2>&1');
        $this->newLine();
        $this->line('Standard-Modus "command" läuft synchron im Scheduler – kein Queue-Worker nötig.');
        $this->line('Für Apps mit eigenem Worker optional: LARAFLEET_DISPATCH=job in der .env.');
        $this->newLine();

        return self::SUCCESS;
    }

    private function addToEnv(string $key, string $value): void
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            return;
        }

        $content = file_get_contents($envPath);

        if (Str::contains($content, $key.'=')) {
            $content = preg_replace(
                '/^'.preg_quote($key, '/').'=.*/m',
                $key.'='.$value,
                $content
            );
        } else {
            $content = rtrim($content).PHP_EOL.$key.'='.$value.PHP_EOL;
        }

        file_put_contents($envPath, $content);
    }
}

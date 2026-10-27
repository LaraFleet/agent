<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return [
    /*
    |--------------------------------------------------------------------------
    | LaraFleet API Endpoint
    |--------------------------------------------------------------------------
    | Die URL der LaraFleet-Zentrale, an die Heartbeats gesendet werden.
    */
    'endpoint' => env('LARAFLEET_ENDPOINT', 'https://app.larafleet.com/api/heartbeat'),

    /*
    |--------------------------------------------------------------------------
    | API Key
    |--------------------------------------------------------------------------
    | Der projektspezifische API-Key aus der LaraFleet-Zentrale.
    | Wird für die HMAC-SHA256-Signierung verwendet.
    */
    'api_key' => env('LARAFLEET_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Heartbeat Interval
    |--------------------------------------------------------------------------
    | Wie häufig der Heartbeat-Scheduler läuft (in Minuten).
    | Standard: 5 Minuten. Günstige Collectoren laufen bei jedem Lauf, teure
    | nur gemäß collectors.intervals → ~24 Full-Snapshots/Tag.
    */
    'interval_minutes' => (int) env('LARAFLEET_INTERVAL', 5),

    /*
    |--------------------------------------------------------------------------
    | Collector-Intervalle
    |--------------------------------------------------------------------------
    | Teure Collectoren (Shell-Subprozesse / selten ändernde Daten) laufen nur
    | im angegebenen Intervall (Sekunden). Günstige Collectoren (Queue,
    | Scheduler, Disk) laufen bei jedem Heartbeat und brauchen keinen Eintrag.
    | Läuft in einem Run mindestens ein teurer Collector, ist der Heartbeat ein
    | vollständiger Snapshot (type=full), sonst ein Partial-Update (type=quick).
    */
    'collectors' => [
        'intervals' => [
            'composer' => (int) env('LARAFLEET_INTERVAL_COMPOSER', 3600),
            'npm' => (int) env('LARAFLEET_INTERVAL_NPM', 3600),
            'environment' => (int) env('LARAFLEET_INTERVAL_ENVIRONMENT', 3600),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dispatch Mode
    |--------------------------------------------------------------------------
    | Wie der Heartbeat ausgeführt wird:
    |   'command' = synchron im Scheduler (larafleet:heartbeat). Empfohlen –
    |               benötigt nur den Standard-Cron `php artisan schedule:run`,
    |               keinen Queue-Worker/Supervisor.
    |   'job'     = als Queue-Job (SendHeartbeatJob). Nur sinnvoll, wenn die App
    |               bereits einen laufenden Queue-Worker hat und die Last
    |               auslagern will.
    */
    'dispatch' => env('LARAFLEET_DISPATCH', 'command'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Timeout
    |--------------------------------------------------------------------------
    | Timeout in Sekunden für den HTTP-Request zur LaraFleet-Zentrale.
    */
    'timeout' => (int) env('LARAFLEET_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Queue Connection
    |--------------------------------------------------------------------------
    | Wenn gesetzt, wird der Heartbeat-Job auf dieser Queue verarbeitet.
    | null = synchron (Standard, empfohlen für kleine Apps).
    */
    'queue' => env('LARAFLEET_QUEUE', null),

    /*
    |--------------------------------------------------------------------------
    | Env Whitelist
    |--------------------------------------------------------------------------
    | Nur diese .env-Keys werden im Snapshot übermittelt.
    | Niemals Passwörter, API-Keys oder Secrets auflisten.
    */
    'env_whitelist' => [
        'APP_ENV',
        'APP_DEBUG',
        'APP_URL',
        'DB_CONNECTION',
        'CACHE_STORE',
        'QUEUE_CONNECTION',
        'MAIL_MAILER',
        'BROADCAST_CONNECTION',
        'FILESYSTEM_DISK',
    ],

    /*
    |--------------------------------------------------------------------------
    | npm Support
    |--------------------------------------------------------------------------
    | Aktiviert das Sammeln von npm-Paket- und Sicherheitsdaten.
    | Deaktivieren falls die App kein Node.js / package.json hat.
    */
    'npm_enabled' => (bool) env('LARAFLEET_NPM', true),

    /*
    |--------------------------------------------------------------------------
    | Composer- / npm-Binary
    |--------------------------------------------------------------------------
    | Aufruf für composer bzw. npm in den Collectoren. Nötig, wenn das Binary
    | nicht im PATH des Cron-Users liegt oder mit einer bestimmten PHP-Version
    | laufen soll, z. B. auf Plesk:
    |   LARAFLEET_COMPOSER_BINARY="/opt/plesk/php/8.3/bin/php /usr/local/bin/composer"
    |   LARAFLEET_NPM_BINARY="/opt/plesk/node/22/bin/node /opt/plesk/node/22/bin/npm"
    */
    'composer_binary' => env('LARAFLEET_COMPOSER_BINARY', 'composer'),
    'npm_binary' => env('LARAFLEET_NPM_BINARY', 'npm'),

    /*
    |--------------------------------------------------------------------------
    | Deployment Detection
    |--------------------------------------------------------------------------
    | Deployment-Zeitpunkt = jüngste mtime aus dieser Datei, der Git-Ref-Datei
    | des ausgecheckten Branches (bzw. .git/HEAD / .git/packed-refs) und
    | vendor/composer/installed.json – fehlende Dateien werden übersprungen.
    | Die Datei hier ist relevant für Deployments ohne .git (z. B. Envoyer).
    */
    'deployment_file' => env('LARAFLEET_DEPLOY_FILE', 'vendor/autoload.php'),

    /*
    |--------------------------------------------------------------------------
    | Exception Reporting
    |--------------------------------------------------------------------------
    | Sendet unbehandelte Exceptions an die LaraFleet-Zentrale.
    | dontReport: Diese Exception-Klassen werden nicht gemeldet.
    | dontFlash:  Diese Request-Parameter werden durch [FILTERED] ersetzt
    |             (gilt für query-Parameter und POST-Input gleichermaßen).
    */
    'exceptions' => [
        'enabled' => env('LARAFLEET_EXCEPTIONS_ENABLED', true),
        'dontReport' => [
            AuthenticationException::class,
            AuthorizationException::class,
            ModelNotFoundException::class,
            TokenMismatchException::class,
            ValidationException::class,
            NotFoundHttpException::class,
            MethodNotAllowedHttpException::class,
        ],
        'dontFlash' => [
            'password',
            'password_confirmation',
            'current_password',
            'token',
            'api_key',
        ],
    ],
];

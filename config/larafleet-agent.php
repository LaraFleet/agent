<?php

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
    | Wie häufig Heartbeats gesendet werden (in Minuten).
    | Standard: 1 Minute. Kleiner Wert = mehr Last auf der überwachten App.
    */
    'interval_minutes' => (int) env('LARAFLEET_INTERVAL', 1),

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
    | Deployment Detection
    |--------------------------------------------------------------------------
    | Pfad zur Datei, deren mtime als Deployment-Zeitpunkt gilt.
    | Standard: vendor/autoload.php (wird bei jedem composer install aktualisiert).
    */
    'deployment_file' => env('LARAFLEET_DEPLOY_FILE', 'vendor/autoload.php'),
];

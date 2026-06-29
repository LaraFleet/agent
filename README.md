# LaraFleet Agent

[![Tests](https://github.com/larafleet/agent/actions/workflows/tests.yml/badge.svg)](https://github.com/larafleet/agent/actions/workflows/tests.yml)

The official monitoring agent for [LaraFleet](https://larafleet.com) –
the monitoring platform for Laravel applications.

## Installation

```bash
composer require larafleet/agent
php artisan larafleet:install
```

The install command prompts for your API key from the LaraFleet dashboard
and updates `.env` automatically.

## How it works

The agent runs `larafleet:heartbeat` every 5 minutes via the Laravel Scheduler.
Only the standard cron entry is required — no queue worker or Supervisor needed:

```
* * * * * php /path-to-app/artisan schedule:run >> /dev/null 2>&1
```

**Cheap collectors** (Queue, Scheduler, Disk) run on every heartbeat.
**Expensive collectors** (Composer, npm, Laravel/PHP version, Env) run once per hour.

| Heartbeat type | Frequency | Payload |
|---|---|---|
| `full` | Hourly (and first run) | All fields |
| `quick` | Every 5 min (between full) | Queue, Scheduler, Disk only |

This reduces snapshot rows in the dashboard from ~288 to ~24 per project per day.

## What data is sent?

| Category | Details | Frequency |
|---|---|---|
| Queue status | Failed jobs, queue size | Every 5 min |
| Scheduler | Last run, missed | Every 5 min |
| Disk/storage | Directory size in MB | Every 5 min |
| Laravel version | `Application::VERSION` | Hourly |
| PHP version + extensions | `PHP_VERSION`, `get_loaded_extensions()` | Hourly |
| Composer packages | Installed, outdated (major/minor/patch) | Hourly |
| Composer security advisories | via `composer audit` | Hourly |
| npm packages | Installed, outdated | Hourly |
| npm security advisories | via `npm audit` | Hourly |
| Env snapshot | Whitelisted keys only (configurable) | Hourly |
| Deployment | Timestamp (mtime of vendor/autoload.php), Git hash | Hourly |

## Exception Reporting

Unhandled exceptions are automatically forwarded to LaraFleet — no extra setup
required after installation. The agent hooks into Laravel's exception handler
and sends a signed report to `POST /api/exceptions`.

**What is reported:**

| Field | Description |
|---|---|
| `exception.class` | Exception class name |
| `exception.message` | Exception message |
| `exception.file` / `line` | Source location |
| `exception.trace` | Full stack trace |
| `exception.fingerprint` | SHA-256 hash for deduplication |
| `request.url` | Request URL (path only, no query string) |
| `request.query` / `input` | GET and POST parameters (sensitive fields filtered) |
| `request.user_id` | Authenticated user ID (no other user data) |
| `context` | Laravel version, PHP version, environment |

**Filtering:** the following request keys are replaced with `[FILTERED]` before
transmission: `password`, `password_confirmation`, `current_password`, `token`,
`api_key`. Custom keys can be added via `exceptions.dontFlash`.

**Disabling:**

```bash
LARAFLEET_EXCEPTIONS_ENABLED=false
```

Or extend `exceptions.dontReport` in `config/larafleet-agent.php` to suppress
specific exception classes:

```php
'exceptions' => [
    'dontReport' => [
        \App\Exceptions\MyExpectedException::class,
    ],
],
```

## Security

- Every request is signed with **HMAC-SHA256**
- **Replay protection**: requests older than 60 seconds are rejected by the server
- **Env whitelist**: only explicitly allowed keys are transmitted
- **Input filtering**: sensitive request parameters are never transmitted in plain text
- No SSH credentials, no passwords, no full `.env` files

## Configuration

```bash
php artisan vendor:publish --tag=larafleet-agent-config
```

All options in `config/larafleet-agent.php`:

| Key | Default | Description |
|---|---|---|
| `endpoint` | `https://app.larafleet.com/api/heartbeat` | Server URL |
| `api_key` | — | From your LaraFleet project settings |
| `interval_minutes` | `5` | Scheduler run frequency in minutes |
| `dispatch` | `command` | `command` (synchronous, recommended) or `job` (Queue) |
| `timeout` | `10` | HTTP timeout in seconds |
| `queue` | `null` | Queue connection for `dispatch=job` mode |
| `npm_enabled` | `true` | Enable npm data collection |
| `env_whitelist` | see config | Allowed env keys |
| `deployment_file` | `vendor/autoload.php` | File used for deployment detection |
| `collectors.intervals.composer` | `3600` | Composer collector interval in seconds |
| `collectors.intervals.npm` | `3600` | npm collector interval in seconds |
| `collectors.intervals.environment` | `3600` | Version/Env collector interval in seconds |
| `exceptions.enabled` | `true` | Enable exception reporting |
| `exceptions.dontReport` | see config | Exception classes that are never reported |
| `exceptions.dontFlash` | see config | Request keys replaced with `[FILTERED]` |

### Dispatch mode

By default the heartbeat runs synchronously inside the Scheduler process (`dispatch=command`).
For apps that already have a running Queue Worker, set `LARAFLEET_DISPATCH=job` in `.env`
to offload the heartbeat to the queue instead.

## License

MIT

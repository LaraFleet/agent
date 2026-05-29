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

## Security

- Every heartbeat is signed with **HMAC-SHA256**
- **Replay protection**: requests older than 60 seconds are rejected by the server
- **Env whitelist**: only explicitly allowed keys are transmitted
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

### Dispatch mode

By default the heartbeat runs synchronously inside the Scheduler process (`dispatch=command`).
For apps that already have a running Queue Worker, set `LARAFLEET_DISPATCH=job` in `.env`
to offload the heartbeat to the queue instead.

## License

MIT

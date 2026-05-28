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

## What data is sent?

| Category | Details |
|---|---|
| Laravel version | `Application::VERSION` |
| PHP version + extensions | `PHP_VERSION`, `get_loaded_extensions()` |
| Composer packages | Installed, outdated (major/minor/patch) |
| Composer security advisories | via `composer audit` |
| npm packages | Installed, outdated |
| npm security advisories | via `npm audit` |
| Queue status | Failed jobs, queue size |
| Scheduler | Last run, missed |
| Disk/storage | Directory size in MB |
| Env snapshot | Whitelisted keys only (configurable) |
| Deployment | Timestamp (mtime of vendor/autoload.php), Git hash |

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
| `interval_minutes` | `1` | Heartbeat frequency in minutes |
| `timeout` | `10` | HTTP timeout in seconds |
| `queue` | `null` | Queue connection (`null` = synchronous) |
| `npm_enabled` | `true` | Enable npm data collection |
| `env_whitelist` | see config | Allowed env keys |
| `deployment_file` | `vendor/autoload.php` | File used for deployment detection |

## License

MIT

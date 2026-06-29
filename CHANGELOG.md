# Changelog

All notable changes to this package will be documented in this file.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [0.2.0] – 2026-06-29

### Added
- **Exception Reporting:** Unhandled exceptions are now automatically reported
  to the LaraFleet dashboard via `POST /api/exceptions`.
  - `ExceptionReporter` class with fire-and-forget semantics — a LaraFleet
    outage never affects the host application.
  - Each report is signed with HMAC-SHA256, identical to the heartbeat mechanism.
  - Payload includes exception class, message, file, line, full stack trace,
    a SHA-256 fingerprint (for deduplication), request context, and environment.
  - Sensitive request parameters (`password`, `password_confirmation`,
    `current_password`, `token`, `api_key`) are replaced with `[FILTERED]`
    in both query string and POST input before transmission.
  - URL is sent without query string; filtered query parameters are available
    in a separate `query` field.
  - `exceptions.dontReport` list — exceptions that are never forwarded
    (default: `AuthenticationException`, `AuthorizationException`,
    `ModelNotFoundException`, `TokenMismatchException`, `ValidationException`,
    `NotFoundHttpException`, `MethodNotAllowedHttpException`).
  - `exceptions.dontFlash` list — request keys that are always filtered
    (case-insensitive).
  - Registration via `ExceptionHandler::reportable()` in the service provider;
    guarded with `method_exists` for compatibility with custom handlers.
  - Reporting is skipped silently when no `api_key` is configured.

### Configuration

New `exceptions` block in `config/larafleet-agent.php`:

```php
'exceptions' => [
    'enabled'    => env('LARAFLEET_EXCEPTIONS_ENABLED', true),
    'dontReport' => [ /* exception classes */ ],
    'dontFlash'  => [ /* request keys to filter */ ],
],
```

## [0.1.6] – 2026-06-10

### Changed
- **Dual heartbeat restored:** Every 5 minutes the agent sends a `type=quick`
  partial update (Queue, Scheduler, Disk). Once per hour (cache-controlled,
  default 3600 s) it sends a `type=full` snapshot that additionally includes
  Composer, NPM, Laravel/PHP version and Env data.
- Default heartbeat interval reverted from 60 minutes back to **5 minutes**
  (`LARAFLEET_INTERVAL`).

### Added
- `collectors.intervals` config section restored with
  `LARAFLEET_INTERVAL_COMPOSER`, `LARAFLEET_INTERVAL_NPM`,
  `LARAFLEET_INTERVAL_ENVIRONMENT` (default: 3600 s each).

## [0.1.5] – 2026-06-03

### Changed
- `SchedulerCollector` — the `missed` threshold and cache TTL now scale
  dynamically with `interval_minutes` (threshold: 1.5×, TTL: 3×) so the flag
  remains accurate regardless of the configured interval.
- `ComposerPackageCollector` — returns `null` (instead of `[]`) for
  `composer_packages` when `composer.lock` is absent, and for
  `composer_advisories` when `composer audit` produces no valid JSON output.
- `NpmPackageCollector` — returns `null` (instead of `[]`) for both
  `npm_packages` and `npm_advisories` when npm is disabled or `package.json`
  is absent; `npm_advisories` is `null` when `npm audit` produces no valid
  JSON output.

### Added
- `keys(): array` method on the `Collector` interface. `HeartbeatRunner` uses
  this to fill a failed collector's keys with `null` so the heartbeat payload
  is always structurally complete even when individual collectors throw.

## [0.1.4] – 2026-06-02

### Added
- `CHANGELOG.md` following the [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) format.

## [0.1.3] – 2026-05-29

### Added
- `HeartbeatRunner` as a central orchestration layer: cheap collectors
  (Queue, Scheduler, Disk) run on every heartbeat, expensive collectors
  (Composer, NPM, Environment) only according to configurable intervals.
- Heartbeat type `full` vs. `quick`: if at least one expensive collector runs,
  the agent sends a complete snapshot (`type=full`), otherwise a partial update
  (`type=quick`). The `type` field is now a required part of every payload.
- Configurable `dispatch` mode (`command` or `job`) via `LARAFLEET_DISPATCH`.
  Default: `command` (synchronous in the scheduler, no queue worker required).
- Per-collector intervals configurable via `collectors.intervals` in
  `config/larafleet-agent.php` or environment variables:
  - `LARAFLEET_INTERVAL_COMPOSER` (default: 3600 s)
  - `LARAFLEET_INTERVAL_NPM` (default: 3600 s)
  - `LARAFLEET_INTERVAL_ENVIRONMENT` (default: 3600 s)
- `HeartbeatRunner` is bound as a singleton in the service container and can
  be resolved via dependency injection or `app(HeartbeatRunner::class)`.

### Changed
- Default heartbeat interval increased from 1 minute to **5 minutes**
  (`LARAFLEET_INTERVAL`), since expensive collectors only run hourly anyway.
- `SendHeartbeatJob` now fully delegates to `HeartbeatRunner::run()`.
- `HeartbeatCommand` supports both dispatch modes and registers correctly
  with the Laravel scheduler.

## [0.1.2] – 2026-05-28

### Added
- GitHub Actions workflow for automated tests (`phpunit`) and code style
  validation (`laravel/pint`) across PHP 8.2/8.3/8.4 × Laravel 10/11/12.
- CI status badge in README.

### Fixed
- Set `fail-fast: false` in the test matrix so all PHP/Laravel combinations
  run to completion even when individual jobs fail.
- Added `FORCE_JS_ACTIONS_TO_NODE24=1` environment variable to suppress
  Node.js deprecation warnings in GitHub Actions.
- Added Laravel 13 compatibility in `composer.json` and testbench dependencies.

## [0.1.1] – 2026-05-28

### Added
- First full release of the LaraFleet Agent.
- **Collectors:**
  - `QueueStatusCollector` – number of failed jobs and current queue size
    (database & Redis/Horizon).
  - `SchedulerCollector` – timestamp of the last scheduler run and a `missed`
    flag when the scheduler has not run for more than 2 minutes.
  - `DiskUsageCollector` – project directory and storage size in MB.
  - `ComposerPackageCollector` – installed packages, outdated versions
    (major/minor/patch) via `composer outdated`, security advisories via
    `composer audit`.
  - `NpmPackageCollector` – NPM packages from `package-lock.json`, outdated
    versions via `npm outdated`, vulnerabilities via `npm audit`.
  - `LaravelVersionCollector` – current Laravel framework version.
  - `PhpVersionCollector` – PHP version and loaded extensions.
  - `EnvSnapshotCollector` – whitelist-filtered snapshot of `.env` values.
  - `DeploymentCollector` – deployment timestamp (mtime of
    `vendor/autoload.php`) and current Git commit hash.
- **HTTP client** with HMAC-SHA256 request signing (`X-LaraFleet-Signature`,
  `X-LaraFleet-Timestamp`, `X-LaraFleet-Api-Key`).
- **Artisan commands:** `larafleet:heartbeat` (manual / scheduler) and
  `larafleet:install` (publishes config, appends `.env` stubs).
- **Service provider** with automatic scheduler registration.
- Configuration file `config/larafleet-agent.php` with endpoint, API key,
  timeout, interval, and ENV whitelist.
- Feature tests (`HeartbeatTest`) and unit tests for collectors and the HTTP
  client.

[0.2.0]: https://github.com/larafleet/agent/compare/v0.1.6...v0.2.0
[0.1.6]: https://github.com/larafleet/agent/compare/v0.1.5...v0.1.6
[0.1.5]: https://github.com/larafleet/agent/compare/v0.1.4...v0.1.5
[0.1.4]: https://github.com/larafleet/agent/compare/v0.1.3...v0.1.4
[0.1.3]: https://github.com/larafleet/agent/compare/v0.1.2...v0.1.3
[0.1.2]: https://github.com/larafleet/agent/compare/v0.1.1...v0.1.2
[0.1.1]: https://github.com/larafleet/agent/releases/tag/v0.1.1
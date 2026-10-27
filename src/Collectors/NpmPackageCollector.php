<?php

namespace LaraFleet\Agent\Collectors;

use LaraFleet\Agent\Collectors\Concerns\RunsCommands;
use LaraFleet\Agent\Collectors\Contracts\Collector;

class NpmPackageCollector implements Collector
{
    use RunsCommands;

    public function keys(): array
    {
        return ['npm_packages', 'npm_advisories', 'npm_audit_error'];
    }

    public function collect(): array
    {
        if (! config('larafleet-agent.npm_enabled', true) || ! file_exists(base_path('package.json'))) {
            return ['npm_packages' => null, 'npm_advisories' => null, 'npm_audit_error' => null];
        }

        // npm audit setzt ein Lockfile voraus – ohne läuft der Audit nicht.
        [$advisories, $auditError] = file_exists(base_path('package-lock.json'))
            ? $this->collectAdvisories()
            : [null, null];

        return [
            'npm_packages' => $this->collectPackages(),
            'npm_advisories' => $advisories,
            'npm_audit_error' => $auditError,
        ];
    }

    /**
     * @return array<int, array{name: string, version: string, latest: string, outdated: bool, type: string}>
     */
    private function collectPackages(): array
    {
        $installed = $this->getInstalledVersions();

        $outdatedOutput = $this->npm('outdated --json')['output'];
        $outdated = json_decode($outdatedOutput, true) ?? [];

        $result = [];

        foreach ($installed as $name => $version) {
            $isOutdated = isset($outdated[$name]);
            $latest = $isOutdated ? ($outdated[$name]['latest'] ?? $version) : $version;

            $result[] = [
                'name' => $name,
                'version' => $version,
                'latest' => $latest,
                'outdated' => $isOutdated,
                'type' => $isOutdated ? $this->resolveUpdateType($version, $latest) : 'none',
            ];
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    private function getInstalledVersions(): array
    {
        $lockFile = base_path('package-lock.json');

        if (! file_exists($lockFile)) {
            return [];
        }

        $lock = json_decode(file_get_contents($lockFile), true);
        $result = [];

        foreach ($lock['packages'] ?? [] as $path => $data) {
            if ($path === '' || ! str_starts_with($path, 'node_modules/')) {
                continue;
            }
            $name = substr($path, strlen('node_modules/'));
            if (! str_contains($name, '/node_modules/')) {
                $result[$name] = $data['version'] ?? '';
            }
        }

        return $result;
    }

    /**
     * Ein Exit-Code ≠ 0 ist kein Fehler – npm audit meldet so gefundene
     * Schwachstellen. Maßgeblich ist, ob gültiges JSON mit "vulnerabilities" kommt.
     *
     * @return array{0: array<int, array{package: string, ghsa: string, severity: string, title: string}>|null, 1: string|null}
     */
    private function collectAdvisories(): array
    {
        $run = $this->npm('audit --json');

        if ($run['output'] === '') {
            return [null, $this->describeFailure('npm audit returned no output', $run)];
        }

        $data = json_decode($run['output'], true);

        if (! is_array($data) || ! is_array($data['vulnerabilities'] ?? null)) {
            $reason = isset($data['error']['code'])
                ? 'npm audit failed with '.$data['error']['code']
                : 'npm audit returned invalid JSON';

            return [null, $this->describeFailure($reason, $run)];
        }

        $result = [];

        foreach ($data['vulnerabilities'] ?? [] as $pkgName => $vuln) {
            foreach ($vuln['via'] ?? [] as $via) {
                if (! is_array($via)) {
                    continue;
                }

                $result[] = [
                    'package' => $pkgName,
                    'ghsa' => $via['url'] ?? '',
                    'cve' => $via['cve'] ?? '',
                    'severity' => strtolower($via['severity'] ?? 'unknown'),
                    'title' => $via['title'] ?? '',
                    'description' => $via['overview'] ?? '',
                ];
            }
        }

        return [$result, null];
    }

    private function resolveUpdateType(string $current, string $latest): string
    {
        $current = ltrim($current, '^~v');
        $latest = ltrim($latest, '^~v');

        $c = array_map('intval', explode('.', $current));
        $l = array_map('intval', explode('.', $latest));

        if (($l[0] ?? 0) > ($c[0] ?? 0)) {
            return 'major';
        }

        if (($l[1] ?? 0) > ($c[1] ?? 0)) {
            return 'minor';
        }

        return 'patch';
    }

    /**
     * @return array{exit_code: ?int, output: string, error_output: string}
     */
    private function npm(string $arguments): array
    {
        return $this->runTool(config('larafleet-agent.npm_binary') ?: 'npm', $arguments);
    }
}

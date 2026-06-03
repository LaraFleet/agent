<?php

namespace LaraFleet\Agent\Collectors;

use LaraFleet\Agent\Collectors\Contracts\Collector;

class NpmPackageCollector implements Collector
{
    public function keys(): array
    {
        return ['npm_packages', 'npm_advisories'];
    }

    public function collect(): array
    {
        if (! config('larafleet-agent.npm_enabled', true) || ! file_exists(base_path('package.json'))) {
            return ['npm_packages' => null, 'npm_advisories' => null];
        }

        return [
            'npm_packages' => $this->collectPackages(),
            'npm_advisories' => $this->collectAdvisories(),
        ];
    }

    /**
     * @return array<int, array{name: string, version: string, latest: string, outdated: bool, type: string}>
     */
    private function collectPackages(): array
    {
        $installed = $this->getInstalledVersions();

        $outdatedOutput = $this->exec('npm outdated --json 2>/dev/null');
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
     * @return array<int, array{package: string, ghsa: string, severity: string, title: string}>|null
     */
    private function collectAdvisories(): ?array
    {
        $output = $this->exec('npm audit --json 2>/dev/null');

        if (empty($output)) {
            return null;
        }

        $data = json_decode($output, true);

        if (! is_array($data)) {
            return null;
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

        return $result;
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

    private function exec(string $command): string
    {
        $output = shell_exec('cd '.escapeshellarg(base_path()).' && '.$command);

        return trim($output ?? '');
    }
}

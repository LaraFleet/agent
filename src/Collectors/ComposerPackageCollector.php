<?php

namespace LaraFleet\Agent\Collectors;

use LaraFleet\Agent\Collectors\Contracts\Collector;

class ComposerPackageCollector implements Collector
{
    public function collect(): array
    {
        return [
            'composer_packages' => $this->collectPackages(),
            'composer_advisories' => $this->collectAdvisories(),
        ];
    }

    /**
     * @return array<int, array{name: string, version: string, latest: string, outdated: bool, type: string}>
     */
    private function collectPackages(): array
    {
        $lockFile = base_path('composer.lock');
        $installed = [];

        if (file_exists($lockFile)) {
            $lock = json_decode(file_get_contents($lockFile), true);
            foreach ($lock['packages'] ?? [] as $pkg) {
                $installed[$pkg['name']] = $pkg['version'];
            }
        }

        $outdated = $this->runComposerOutdated();

        $result = [];

        foreach ($installed as $name => $version) {
            $isOutdated = isset($outdated[$name]);
            $result[] = [
                'name' => $name,
                'version' => $version,
                'latest' => $isOutdated ? $outdated[$name]['latest'] : $version,
                'outdated' => $isOutdated,
                'type' => $isOutdated ? $this->resolveUpdateType($version, $outdated[$name]['latest']) : 'none',
            ];
        }

        return $result;
    }

    /**
     * @return array<string, array{latest: string}>
     */
    private function runComposerOutdated(): array
    {
        $output = $this->exec('composer outdated --format=json --no-interaction --no-ansi 2>/dev/null');

        if (empty($output)) {
            return [];
        }

        $data = json_decode($output, true);

        if (! isset($data['installed'])) {
            return [];
        }

        $result = [];
        foreach ($data['installed'] as $pkg) {
            $result[$pkg['name']] = [
                'latest' => $pkg['latest'] ?? $pkg['version'],
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array{package: string, cve: string, severity: string, title: string, link: string}>
     */
    private function collectAdvisories(): array
    {
        $output = $this->exec('composer audit --format=json --no-interaction --no-ansi 2>/dev/null');

        if (empty($output)) {
            return [];
        }

        $data = json_decode($output, true);
        $result = [];

        foreach ($data['advisories'] ?? [] as $packageName => $advisories) {
            foreach ($advisories as $advisory) {
                $result[] = [
                    'package' => $packageName,
                    'cve' => $advisory['cve'] ?? ($advisory['advisoryId'] ?? ''),
                    'severity' => strtolower($advisory['severity'] ?? 'unknown'),
                    'title' => $advisory['title'] ?? '',
                    'link' => $advisory['link'] ?? '',
                    'description' => $advisory['description'] ?? '',
                ];
            }
        }

        return $result;
    }

    private function resolveUpdateType(string $current, string $latest): string
    {
        $current = ltrim($current, '^~v');
        $latest = ltrim($latest, '^~v');

        $currentParts = array_map('intval', explode('.', $current));
        $latestParts = array_map('intval', explode('.', $latest));

        if (($latestParts[0] ?? 0) > ($currentParts[0] ?? 0)) {
            return 'major';
        }

        if (($latestParts[1] ?? 0) > ($currentParts[1] ?? 0)) {
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

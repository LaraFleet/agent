<?php

namespace LaraFleet\Agent\Collectors;

use LaraFleet\Agent\Collectors\Concerns\RunsCommands;
use LaraFleet\Agent\Collectors\Contracts\Collector;

class ComposerPackageCollector implements Collector
{
    use RunsCommands;

    public function keys(): array
    {
        return ['composer_packages', 'composer_advisories', 'composer_audit_error'];
    }

    public function collect(): array
    {
        [$advisories, $auditError] = $this->collectAdvisories();

        return [
            'composer_packages' => $this->collectPackages(),
            'composer_advisories' => $advisories,
            'composer_audit_error' => $auditError,
        ];
    }

    /**
     * @return array<int, array{name: string, version: string, latest: string, outdated: bool, type: string}>|null
     */
    private function collectPackages(): ?array
    {
        $lockFile = base_path('composer.lock');

        if (! file_exists($lockFile)) {
            return null;
        }

        $installed = [];
        $lock = json_decode(file_get_contents($lockFile), true);
        foreach ($lock['packages'] ?? [] as $pkg) {
            $installed[$pkg['name']] = $pkg['version'];
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
        $output = $this->composer('outdated --format=json --no-interaction --no-ansi')['output'];

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
     * Ein Exit-Code ≠ 0 ist kein Fehler – composer audit meldet so gefundene
     * Advisories. Maßgeblich ist, ob gültiges JSON mit "advisories" kommt.
     *
     * @return array{0: array<int, array{package: string, cve: string, severity: string, title: string, link: string}>|null, 1: string|null}
     */
    private function collectAdvisories(): array
    {
        $run = $this->composer('audit --format=json --no-interaction --no-ansi');

        if ($run['output'] === '') {
            return [null, $this->describeFailure('composer audit returned no output', $run)];
        }

        $data = json_decode($run['output'], true);

        if (! is_array($data) || ! is_array($data['advisories'] ?? null)) {
            return [null, $this->describeFailure('composer audit returned invalid JSON', $run)];
        }

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

        return [$result, null];
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

    /**
     * @return array{exit_code: ?int, output: string, error_output: string}
     */
    private function composer(string $arguments): array
    {
        return $this->runTool(config('larafleet-agent.composer_binary') ?: 'composer', $arguments);
    }
}

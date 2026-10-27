<?php

namespace LaraFleet\Agent\Collectors;

use Carbon\Carbon;
use LaraFleet\Agent\Collectors\Contracts\Collector;

class DeploymentCollector implements Collector
{
    public function keys(): array
    {
        return ['deployment_at', 'deployment_hash'];
    }

    public function collect(): array
    {
        $mtimes = array_map('filemtime', $this->deploymentCandidates());

        if ($mtimes === []) {
            return [
                'deployment_at' => null,
                'deployment_hash' => null,
            ];
        }

        return [
            'deployment_at' => Carbon::createFromTimestamp(max($mtimes))->toIso8601String(),
            'deployment_hash' => $this->resolveGitHash(),
        ];
    }

    /**
     * Existierende Dateien, deren jüngste mtime als Deployment-Zeitpunkt gilt.
     * vendor/autoload.php allein reicht nicht: Composer schreibt sie nur neu,
     * wenn sich ihr Inhalt ändert (z. B. nicht bei reinen Lock- oder Code-Updates).
     *
     * @return list<string>
     */
    private function deploymentCandidates(): array
    {
        $candidates = [
            base_path(config('larafleet-agent.deployment_file', 'vendor/autoload.php')),
            $this->resolveGitRefFile(),
            base_path('vendor/composer/installed.json'),
        ];

        return array_values(array_unique(array_filter(
            $candidates,
            fn (?string $path) => $path !== null && is_file($path),
        )));
    }

    /**
     * Datei, die beim Aktualisieren des ausgecheckten Commits geschrieben wird:
     * die Ref-Datei, auf die HEAD zeigt, bei detached HEAD .git/HEAD selbst,
     * liegt die Ref nur gepackt vor, .git/packed-refs.
     */
    private function resolveGitRefFile(): ?string
    {
        $headFile = base_path('.git/HEAD');

        if (! is_file($headFile)) {
            return null;
        }

        $head = trim(file_get_contents($headFile));

        if (! str_starts_with($head, 'ref: ')) {
            return $headFile;
        }

        $ref = substr($head, 5);
        $refPath = base_path('.git/'.$ref);

        if (is_file($refPath)) {
            return $refPath;
        }

        $packedRefs = base_path('.git/packed-refs');

        if (is_file($packedRefs) && preg_match('/^[0-9a-f]{40,64} '.preg_quote($ref, '/').'$/m', file_get_contents($packedRefs))) {
            return $packedRefs;
        }

        return null;
    }

    private function resolveGitHash(): ?string
    {
        $headFile = base_path('.git/HEAD');

        if (! file_exists($headFile)) {
            return null;
        }

        $head = trim(file_get_contents($headFile));

        if (! str_starts_with($head, 'ref: ')) {
            return strlen($head) === 40 ? $head : null;
        }

        $refPath = base_path('.git/'.substr($head, 5));

        if (file_exists($refPath)) {
            return trim(file_get_contents($refPath)) ?: null;
        }

        $packedRefs = base_path('.git/packed-refs');

        if (file_exists($packedRefs)) {
            $ref = substr($head, 5);
            foreach (explode("\n", file_get_contents($packedRefs)) as $line) {
                if (str_ends_with(trim($line), $ref)) {
                    return explode(' ', $line)[0] ?? null;
                }
            }
        }

        return null;
    }
}

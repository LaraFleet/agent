<?php

namespace LaraFleet\Agent\Collectors;

use Carbon\Carbon;
use LaraFleet\Agent\Collectors\Contracts\Collector;

class DeploymentCollector implements Collector
{
    public function collect(): array
    {
        $deployFile = base_path(config('larafleet-agent.deployment_file', 'vendor/autoload.php'));

        if (! file_exists($deployFile)) {
            return [
                'deployment_at' => null,
                'deployment_hash' => null,
            ];
        }

        $mtime = filemtime($deployFile);

        return [
            'deployment_at' => Carbon::createFromTimestamp($mtime)->toIso8601String(),
            'deployment_hash' => $this->resolveGitHash(),
        ];
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

<?php

namespace LaraFleet\Agent\Collectors;

use LaraFleet\Agent\Collectors\Contracts\Collector;

class DiskUsageCollector implements Collector
{
    public function keys(): array
    {
        return ['disk_usage_mb', 'storage_usage_mb'];
    }

    public function collect(): array
    {
        $basePath = base_path();
        $storagePath = storage_path();

        return [
            'disk_usage_mb' => $this->dirSizeMb($basePath),
            'storage_usage_mb' => $this->dirSizeMb($storagePath),
        ];
    }

    private function dirSizeMb(string $path): ?int
    {
        if (PHP_OS_FAMILY === 'Linux' || PHP_OS_FAMILY === 'Darwin') {
            $output = shell_exec('du -sk '.escapeshellarg($path).' 2>/dev/null');
            if ($output && preg_match('/^(\d+)/', $output, $matches)) {
                return (int) round((int) $matches[1] / 1024);
            }
        }

        $bytes = 0;
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $bytes += $file->getSize();
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return (int) round($bytes / 1024 / 1024);
    }
}

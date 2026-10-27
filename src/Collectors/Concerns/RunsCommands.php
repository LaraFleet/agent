<?php

namespace LaraFleet\Agent\Collectors\Concerns;

use Symfony\Component\Process\Process;
use Throwable;

trait RunsCommands
{
    /**
     * Führt "<binary> <arguments>" im Projektverzeichnis aus. Das Binary stammt
     * aus der Config (vertrauenswürdig, darf z. B. "php /usr/local/bin/composer"
     * sein), die Argumente sind fest im Code hinterlegt.
     *
     * @return array{exit_code: ?int, output: string, error_output: string}
     */
    protected function runTool(string $binary, string $arguments): array
    {
        return $this->runCommand(trim($binary).' '.$arguments);
    }

    /**
     * @return array{exit_code: ?int, output: string, error_output: string}
     */
    protected function runCommand(string $commandLine): array
    {
        try {
            $process = Process::fromShellCommandline($commandLine, base_path(), null, null, null);
            $process->run();

            return [
                'exit_code' => $process->getExitCode(),
                'output' => trim($process->getOutput()),
                'error_output' => trim($process->getErrorOutput()),
            ];
        } catch (Throwable $e) {
            return ['exit_code' => null, 'output' => '', 'error_output' => $e->getMessage()];
        }
    }

    /**
     * Kurze Fehlerbeschreibung für den Payload: Grund, Exit-Code und erste
     * stderr-Zeile – ohne Projektpfad und Zugangsdaten in URLs, max. 500 Zeichen.
     *
     * @param  array{exit_code: ?int, output: string, error_output: string}  $result
     */
    protected function describeFailure(string $reason, array $result): string
    {
        $message = $reason.' (exit code '.($result['exit_code'] ?? 'n/a').')';

        $firstLine = trim(strtok($result['error_output'], "\r\n") ?: '');

        if ($firstLine !== '') {
            $message .= ': '.$firstLine;
        }

        $message = str_replace(base_path(), '.', $message);
        $message = preg_replace('#://[^/\s@]+@#', '://***@', $message);

        return mb_substr($message, 0, 500);
    }
}

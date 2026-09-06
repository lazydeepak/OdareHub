<?php
declare(strict_types=1);

namespace Platform\Recovery;

final class MySqlDumpProvider
{
    private string $dumpExecutable;

    public function __construct(string $dumpExecutable)
    {
        $this->dumpExecutable = $dumpExecutable;
    }

    /**
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    public function createDump(array $config, string $targetDirectory, string $payloadName = 'database.sql.gz'): array
    {
        if ($this->invalidPayloadName($payloadName)) {
            return $this->failure('Payload name must be a simple relative filename.');
        }

        $targetDirectory = rtrim($targetDirectory, '/');
        if (!is_dir($targetDirectory) || !is_writable($targetDirectory)) {
            return $this->failure('Target directory is not writable.');
        }
        if (!is_file($this->dumpExecutable) || !is_executable($this->dumpExecutable)) {
            return $this->failure('Dump executable is unavailable.');
        }

        $database = trim((string)($config['name'] ?? ''));
        if ($database === '') {
            return $this->failure('Database name is required.');
        }

        $defaultsFile = tempnam($targetDirectory, 'mysql-defaults-');
        if ($defaultsFile === false) {
            return $this->failure('Unable to create temporary database defaults file.');
        }

        $sqlFile = tempnam($targetDirectory, 'mysql-dump-');
        if ($sqlFile === false) {
            @unlink($defaultsFile);
            return $this->failure('Unable to create temporary dump file.');
        }

        try {
            chmod($defaultsFile, 0600);
            file_put_contents($defaultsFile, $this->defaultsFileContents($config));

            $command = [
                $this->dumpExecutable,
                '--defaults-extra-file=' . $defaultsFile,
                '--single-transaction',
                '--routines',
                '--triggers',
                '--events',
                '--default-character-set=' . trim((string)($config['charset'] ?? 'utf8mb4')),
                '--result-file=' . $sqlFile,
                $database,
            ];

            $result = $this->run($command, $targetDirectory);
            if (($result['exit_code'] ?? 1) !== 0) {
                return $this->failure('Dump command failed.', [
                    'exit_code' => $result['exit_code'] ?? null,
                    'stderr' => $this->redact((string)($result['stderr'] ?? ''), $config),
                ]);
            }

            if (!is_file($sqlFile) || filesize($sqlFile) === 0) {
                return $this->failure('Dump command did not produce a readable SQL file.');
            }

            $target = $targetDirectory . '/' . ltrim($payloadName, '/');
            $encoded = gzencode((string)file_get_contents($sqlFile), 6);
            if ($encoded === false || file_put_contents($target, $encoded) === false) {
                return $this->failure('Unable to write compressed database dump.');
            }

            return [
                'ok' => true,
                'errors' => [],
                'warnings' => [],
                'provider' => 'mysqldump',
                'provider_version' => 'unknown',
                'identity' => [
                    'host_label' => trim((string)($config['host'] ?? 'localhost')),
                    'port' => (int)($config['port'] ?? 3306),
                    'database' => $database,
                    'charset' => trim((string)($config['charset'] ?? 'utf8mb4')),
                ],
                'payload' => [
                    'path' => basename($target),
                    'sha256' => hash_file('sha256', $target) ?: '',
                    'size_bytes' => filesize($target) ?: 0,
                ],
            ];
        } finally {
            @unlink($defaultsFile);
            @unlink($sqlFile);
        }
    }

    /**
     * @param array<int,string> $command
     * @return array{exit_code:int,stderr:string}
     */
    private function run(array $command, string $cwd): array
    {
        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $cwd
        );

        if (!is_resource($process)) {
            return ['exit_code' => 1, 'stderr' => 'Unable to start dump process.'];
        }

        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return ['exit_code' => $exitCode, 'stderr' => (string)$stderr];
    }

    /** @param array<string,mixed> $config */
    private function defaultsFileContents(array $config): string
    {
        $lines = [
            '[client]',
            'host=' . trim((string)($config['host'] ?? 'localhost')),
            'port=' . (int)($config['port'] ?? 3306),
            'user=' . trim((string)($config['user'] ?? '')),
            'password=' . (string)($config['pass'] ?? ''),
        ];

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param array<string,mixed> $details
     * @return array<string,mixed>
     */
    private function failure(string $message, array $details = []): array
    {
        return [
            'ok' => false,
            'errors' => [$message],
            'warnings' => [],
            'details' => $details,
        ];
    }

    /** @param array<string,mixed> $config */
    private function redact(string $message, array $config): string
    {
        foreach (['pass', 'password'] as $key) {
            $secret = (string)($config[$key] ?? '');
            if ($secret !== '') {
                $message = str_replace($secret, '[redacted]', $message);
            }
        }

        return $message;
    }

    private function invalidPayloadName(string $payloadName): bool
    {
        return trim($payloadName) === ''
            || basename($payloadName) !== $payloadName
            || str_contains($payloadName, '..')
            || str_contains($payloadName, '/')
            || str_contains($payloadName, '\\');
    }
}

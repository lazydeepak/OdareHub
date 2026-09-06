<?php
declare(strict_types=1);

namespace Platform\Recovery;

final class MySqlRestoreProvider
{
    private string $mysqlExecutable;

    public function __construct(string $mysqlExecutable)
    {
        $this->mysqlExecutable = $mysqlExecutable;
    }

    /**
     * @param array<string,mixed> $config
     * @param array<string,mixed> $sourceIdentity
     * @return array<string,mixed>
     */
    public function rehearse(string $dumpPath, array $config, string $rehearsalDatabase, array $sourceIdentity): array
    {
        $rehearsalDatabase = trim($rehearsalDatabase);
        if ($rehearsalDatabase === '') {
            return $this->failure('Rehearsal database name is required.');
        }
        if ($rehearsalDatabase === (string)($sourceIdentity['database'] ?? '')) {
            return $this->failure('Rehearsal database must differ from the source database.');
        }
        if (!preg_match('/^[A-Za-z0-9_.-]+$/', $rehearsalDatabase)) {
            return $this->failure('Rehearsal database name contains unsupported characters.');
        }
        if (!is_file($dumpPath)) {
            return $this->failure('Database dump payload is missing.');
        }
        if (!is_file($this->mysqlExecutable) || !is_executable($this->mysqlExecutable)) {
            return $this->failure('MySQL client executable is unavailable.');
        }

        $targetDirectory = dirname($dumpPath);
        $defaultsFile = tempnam($targetDirectory, 'mysql-restore-defaults-');
        $sqlFile = tempnam($targetDirectory, 'mysql-restore-');
        if ($defaultsFile === false || $sqlFile === false) {
            if ($defaultsFile !== false) {
                @unlink($defaultsFile);
            }
            if ($sqlFile !== false) {
                @unlink($sqlFile);
            }
            return $this->failure('Unable to create temporary restore files.');
        }

        try {
            chmod($defaultsFile, 0600);
            file_put_contents($defaultsFile, $this->defaultsFileContents($config));
            $decoded = gzdecode((string)file_get_contents($dumpPath));
            if ($decoded === false || file_put_contents($sqlFile, $decoded) === false) {
                return $this->failure('Unable to read compressed database dump.');
            }

            $setupSql = sprintf(
                "DROP DATABASE IF EXISTS `%s`; CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;",
                str_replace('`', '``', $rehearsalDatabase),
                str_replace('`', '``', $rehearsalDatabase)
            );
            $setup = $this->run([$this->mysqlExecutable, '--defaults-extra-file=' . $defaultsFile, '-e', $setupSql], $targetDirectory);
            if (($setup['exit_code'] ?? 1) !== 0) {
                return $this->failure('Unable to prepare rehearsal database.', [
                    'exit_code' => $setup['exit_code'] ?? null,
                    'stderr' => $this->redact((string)($setup['stderr'] ?? ''), $config),
                ]);
            }

            $restore = $this->run([$this->mysqlExecutable, '--defaults-extra-file=' . $defaultsFile, $rehearsalDatabase], $targetDirectory, (string)file_get_contents($sqlFile));
            if (($restore['exit_code'] ?? 1) !== 0) {
                return $this->failure('Unable to restore dump into rehearsal database.', [
                    'exit_code' => $restore['exit_code'] ?? null,
                    'stderr' => $this->redact((string)($restore['stderr'] ?? ''), $config),
                ]);
            }

            return [
                'ok' => true,
                'errors' => [],
                'warnings' => [],
                'provider' => 'mysql',
                'rehearsal_database' => $rehearsalDatabase,
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
    private function run(array $command, string $cwd, string $stdin = ''): array
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
            return ['exit_code' => 1, 'stderr' => 'Unable to start mysql process.'];
        }

        fwrite($pipes[0], $stdin);
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        return ['exit_code' => proc_close($process), 'stderr' => (string)$stderr];
    }

    /** @param array<string,mixed> $config */
    private function defaultsFileContents(array $config): string
    {
        return implode("\n", [
            '[client]',
            'host=' . trim((string)($config['host'] ?? 'localhost')),
            'port=' . (int)($config['port'] ?? 3306),
            'user=' . trim((string)($config['user'] ?? '')),
            'password=' . (string)($config['pass'] ?? ''),
        ]) . "\n";
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
}

<?php
declare(strict_types=1);

/**
 * Narrow runtime attestation for a disposable local MySQL process.
 *
 * This is supplementary to disposable_db_harness.php. The existing harness
 * remains proof-only and never authorizes execution. This authorizer accepts
 * no environment configuration and only authorizes an explicitly supplied
 * process/socket/datadir tuple after checking the live process and server.
 */
final class DisposableDbExecutionAuthorizer
{
    /**
     * @return array{authorized:bool,checks:array<int,string>,failures:array<int,string>,server:array<string,mixed>}
     */
    public static function authorize(int $pid, string $socket, string $datadir, string $resourceRoot, int $expectedPort = 0): array
    {
        $checks = [];
        $failures = [];
        $server = [];
        $check = static function (bool $condition, string $label) use (&$checks, &$failures): void {
            if ($condition) {
                $checks[] = $label;
                return;
            }
            $failures[] = $label;
        };

        $tmpRoot = self::canonicalDirectory(sys_get_temp_dir());
        $root = self::canonicalDirectory($resourceRoot);
        $data = self::canonicalDirectory($datadir);
        $socketPath = self::normalizePath($socket);

        $check($pid > 1, 'pid is a non-system process id');
        $check($root !== null && $tmpRoot !== null && self::isWithin($root, $tmpRoot), 'resource root is inside system temporary storage');
        $check($data !== null && $root !== null && self::isWithin($data, $root), 'datadir is inside the supplied resource root');
        $check($socketPath !== '' && $root !== null && self::isWithinOrEqual(dirname($socketPath), $root), 'socket directory is inside the supplied resource root');
        $check($data !== null && !self::containsInstalledPath($data), 'datadir is not an installed application database path');
        $check($socketPath !== '' && !self::containsInstalledPath($socketPath), 'socket is not an installed application database socket');
        $check($data !== null && is_dir($data), 'datadir exists');
        $check($socketPath !== '' && self::isUnixSocket($socketPath), 'unique disposable socket exists');

        if ($pid <= 1 || $root === null || $data === null || $socketPath === '' || !is_dir($data) || !self::isUnixSocket($socketPath)) {
            return [
                'authorized' => false,
                'checks' => $checks,
                'failures' => $failures,
                'server' => $server,
            ];
        }

        $processUser = self::commandOutput(['/bin/ps', '-p', (string)$pid, '-o', 'user=']);
        $processCommand = self::commandOutput(['/bin/ps', '-p', (string)$pid, '-o', 'command=']);
        $expectedUser = function_exists('posix_geteuid') && function_exists('posix_getpwuid')
            ? (posix_getpwuid(posix_geteuid())['name'] ?? '')
            : '';
        $check($processUser !== null && $expectedUser !== '' && trim($processUser) === $expectedUser, 'process is owned by the current non-root user');
        $check($expectedUser !== '' && $expectedUser !== 'root', 'authorizer is not running as root');
        $check($processCommand !== null && preg_match('/(?:^|\/)(?:mysqld|mariadbd)(?:\s|$)/', trim($processCommand)) === 1, 'process is a MySQL/MariaDB server');
        $check($processCommand !== null && str_contains($processCommand, '--no-defaults'), 'server was started without inherited defaults');
        $networkIsDisabled = $processCommand !== null && str_contains($processCommand, '--skip-networking');
        $loopbackNetwork = $processCommand !== null
            && str_contains($processCommand, '--bind-address=127.0.0.1')
            && $expectedPort > 0
            && str_contains($processCommand, '--port=' . $expectedPort);
        $check($networkIsDisabled || $loopbackNetwork, 'server is socket-only or loopback-bound to the attested port');
        $check($processCommand !== null && str_contains($processCommand, '--datadir=' . $data), 'process datadir matches attested datadir');
        $check($processCommand !== null && str_contains($processCommand, '--socket=' . $socketPath), 'process socket matches attested socket');
        $check($processCommand !== null && !self::containsInstalledPath($processCommand), 'process command does not reference installed database storage');

        $socketFiles = self::commandOutput(['/usr/sbin/lsof', '-nP', '-a', '-p', (string)$pid, '-U', '-Fn']);
        $networkFiles = self::commandOutput(['/usr/sbin/lsof', '-nP', '-a', '-p', (string)$pid, '-i']);
        $check($socketFiles !== null && str_contains($socketFiles, 'n' . $socketPath), 'live process owns the attested Unix socket');
        if ($networkIsDisabled) {
            $check($networkFiles === null || trim($networkFiles) === '', 'live process has no network listener');
        } else {
            $check($networkFiles !== null && str_contains($networkFiles, '127.0.0.1:' . $expectedPort), 'live process listener is the attested loopback port');
            $check($networkFiles === null || !preg_match('/(?:\*|0\.0\.0\.0|\[::\]):/', $networkFiles), 'live process has no wildcard network listener');
        }

        mysqli_report(MYSQLI_REPORT_OFF);
        $connection = @new mysqli('localhost', 'root', '', '', 0, $socketPath);
        $check(!$connection->connect_errno, 'server accepts a root connection only through the attested socket');
        if (!$connection->connect_errno) {
            $result = @$connection->query('SELECT @@datadir AS datadir, @@socket AS socket_path, @@skip_networking AS skip_networking, @@port AS port');
            $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
            if (is_array($row)) {
                $server = $row;
            }
            $serverData = isset($server['datadir']) ? self::canonicalDirectory(rtrim((string)$server['datadir'], '/')) : null;
            $serverSocket = isset($server['socket_path']) ? self::normalizePath((string)$server['socket_path']) : '';
            $check($serverData === $data, 'server-reported datadir matches attested datadir');
            $check($serverSocket === $socketPath, 'server-reported socket matches attested socket');
            $serverNetworkingDisabled = (string)($server['skip_networking'] ?? '') === '1';
            $serverPort = (int)($server['port'] ?? -1);
            $check($serverNetworkingDisabled || ($serverPort === $expectedPort && $expectedPort > 0), 'server-reported network mode matches attested mode');
            $check($serverNetworkingDisabled ? $serverPort === 0 : $serverPort === $expectedPort, 'server-reported port matches attested port');
            $connection->close();
        }

        return [
            'authorized' => $failures === [],
            'checks' => $checks,
            'failures' => $failures,
            'server' => $server,
        ];
    }

    private static function canonicalDirectory(string $path): ?string
    {
        $real = realpath($path);
        return $real === false ? null : rtrim($real, DIRECTORY_SEPARATOR);
    }

    private static function normalizePath(string $path): string
    {
        $directory = dirname($path);
        $realDirectory = realpath($directory);
        return $realDirectory === false ? '' : rtrim($realDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($path);
    }

    private static function isWithin(string $path, string $parent): bool
    {
        $path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $parent = rtrim($parent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return str_starts_with($path, $parent) && $path !== $parent;
    }

    private static function isWithinOrEqual(string $path, string $parent): bool
    {
        $path = rtrim($path, DIRECTORY_SEPARATOR);
        $parent = rtrim($parent, DIRECTORY_SEPARATOR);
        return $path === $parent || self::isWithin($path, $parent);
    }

    private static function containsInstalledPath(string $value): bool
    {
        $normalized = str_replace('\\', '/', $value);
        foreach ([
            '/Applications/ServBay/',
            '/storage/db_config.php',
            '/storage/',
            '/var/lib/mysql/',
            '/var/lib/mariadb/',
        ] as $blocked) {
            if (str_contains($normalized, $blocked)) {
                return true;
            }
        }
        return false;
    }

    private static function isUnixSocket(string $path): bool
    {
        return file_exists($path) && filetype($path) === 'socket';
    }

    /** @param array<int,string> $command */
    private static function commandOutput(array $command): ?string
    {
        $escaped = array_map(static fn (string $part): string => escapeshellarg($part), $command);
        $descriptor = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = @proc_open(implode(' ', $escaped), $descriptor, $pipes);
        if (!is_resource($process)) {
            return null;
        }
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        return $exit === 0 ? (string)$stdout : null;
    }
}

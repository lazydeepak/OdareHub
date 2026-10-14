<?php
declare(strict_types=1);

/**
 * Pure pre-connection guard for disposable database probes.
 *
 * This class reads only the explicitly supplied temporary configuration file.
 * It never opens a database connection, executes SQL, invokes a shell command,
 * or discovers installed credentials.
 */
final class DisposableDbTestConfigurationGuard
{
    /**
     * @param array<string,string|int> $input
     * @return array{config_path:string,config_hash:string,identity:array{host:string,port:int,name:string,user:string}}
     */
    public static function authorize(array $input): array
    {
        $repo = self::required($input, 'repo');
        $appRoot = self::required($input, 'app_root');
        $resourceRoot = self::required($input, 'resource_root');
        $expectedHost = self::required($input, 'host');
        $expectedPort = self::port($input['port'] ?? null);
        $expectedName = self::required($input, 'name');
        $expectedUser = self::required($input, 'user');

        $repoReal = self::directory($repo, 'repo');
        $appRootReal = self::directory($appRoot, 'app_root');
        $resourceRootReal = self::directory($resourceRoot, 'resource_root');
        if ($appRootReal === $repoReal || self::contains($repoReal, $appRootReal)) {
            throw new \RuntimeException('test APP_ROOT must not be the repository or a repository child');
        }
        if (!self::contains($resourceRootReal, $appRootReal)) {
            throw new \RuntimeException('test APP_ROOT must be inside the attested resource root');
        }

        if ($expectedHost !== '127.0.0.1') {
            throw new \RuntimeException('test database host must be the attested loopback endpoint');
        }
        self::assertSafeName($expectedName, 'database name');
        self::assertTestUser($expectedUser);
        self::assertEnvironmentIsNotContradictory($expectedHost, $expectedPort, $expectedName, $expectedUser);

        $configPath = $appRootReal . '/storage/db_config.php';
        if (!is_file($configPath)) {
            throw new \RuntimeException('isolated test database config is missing');
        }
        $configReal = realpath($configPath);
        if ($configReal === false || !self::contains($appRootReal, $configReal)) {
            throw new \RuntimeException('test database config escapes APP_ROOT');
        }
        if (self::contains($repoReal, $configReal)) {
            throw new \RuntimeException('installed repository database config is forbidden');
        }

        $config = require $configReal;
        if (!is_array($config)) {
            throw new \RuntimeException('isolated database config must return an array');
        }
        $actual = [
            'host' => trim((string)($config['host'] ?? '')),
            'port' => (int)($config['port'] ?? 0),
            'name' => trim((string)($config['name'] ?? '')),
            'user' => trim((string)($config['user'] ?? '')),
        ];
        $expected = [
            'host' => $expectedHost,
            'port' => $expectedPort,
            'name' => $expectedName,
            'user' => $expectedUser,
        ];
        if ($actual !== $expected) {
            throw new \RuntimeException('isolated database config does not match the attested endpoint identity');
        }
        if (trim((string)($config['pass'] ?? '')) === '') {
            throw new \RuntimeException('isolated database config has no test credential');
        }

        return [
            'config_path' => $configReal,
            'config_hash' => hash_file('sha256', $configReal),
            'identity' => $actual,
        ];
    }

    /**
     * Revalidate the exact file and endpoint identity captured before the DB
     * layer is loaded. This blocks silent target changes after authorization.
     *
     * @param array{config_path:string,config_hash:string,identity:array{host:string,port:int,name:string,user:string}} $snapshot
     */
    public static function assertUnchanged(array $snapshot): void
    {
        $path = $snapshot['config_path'];
        if (!is_file($path) || hash_file('sha256', $path) !== $snapshot['config_hash']) {
            throw new \RuntimeException('isolated database config changed after authorization');
        }
        $config = require $path;
        $actual = [
            'host' => trim((string)($config['host'] ?? '')),
            'port' => (int)($config['port'] ?? 0),
            'name' => trim((string)($config['name'] ?? '')),
            'user' => trim((string)($config['user'] ?? '')),
        ];
        if ($actual !== $snapshot['identity']) {
            throw new \RuntimeException('isolated database endpoint identity changed after authorization');
        }
    }

    /** @param array<string,string|int> $input */
    private static function required(array $input, string $key): string
    {
        $value = trim((string)($input[$key] ?? ''));
        if ($value === '') {
            throw new \RuntimeException("missing test configuration field {$key}");
        }
        return $value;
    }

    private static function port(mixed $value): int
    {
        $raw = trim((string)$value);
        if (!ctype_digit($raw) || (int)$raw < 1 || (int)$raw > 65535) {
            throw new \RuntimeException('test database port is invalid');
        }
        return (int)$raw;
    }

    private static function directory(string $path, string $label): string
    {
        $real = realpath($path);
        if ($real === false || !is_dir($real)) {
            throw new \RuntimeException("{$label} is not an existing directory");
        }
        return rtrim($real, DIRECTORY_SEPARATOR);
    }

    private static function contains(string $parent, string $child): bool
    {
        $parent = rtrim($parent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return str_starts_with(rtrim($child, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR, $parent);
    }

    private static function assertSafeName(string $name, string $label): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)
            || preg_match('/(?:erp|odarehub|susankhya|production|prod|live)/i', $name)) {
            throw new \RuntimeException("{$label} is unsafe or production-like");
        }
    }

    private static function assertTestUser(string $user): void
    {
        if (in_array(strtolower($user), ['root', 'admin', 'odarehub', 'susankhya'], true)) {
            throw new \RuntimeException('test database user is not test-only');
        }
    }

    private static function assertEnvironmentIsNotContradictory(string $host, int $port, string $name, string $user): void
    {
        foreach (['ERP_DB_HOST', 'DB_HOST'] as $key) {
            $value = trim((string)(getenv($key) ?: ''));
            if ($value !== '' && $value !== $host) {
                throw new \RuntimeException("ambient {$key} contradicts the attested host");
            }
        }
        foreach (['ERP_DB_PORT', 'DB_PORT'] as $key) {
            $value = trim((string)(getenv($key) ?: ''));
            if ($value !== '' && (!ctype_digit($value) || (int)$value !== $port)) {
                throw new \RuntimeException("ambient {$key} contradicts the attested port");
            }
        }
        foreach (['ERP_DB_NAME', 'DB_NAME'] as $key) {
            $value = trim((string)(getenv($key) ?: ''));
            if ($value !== '' && $value !== $name) {
                throw new \RuntimeException("ambient {$key} contradicts the attested database");
            }
        }
        foreach (['ERP_DB_USER', 'DB_USER'] as $key) {
            $value = trim((string)(getenv($key) ?: ''));
            if ($value !== '' && $value !== $user) {
                throw new \RuntimeException("ambient {$key} contradicts the attested user");
            }
        }
        foreach (['ERP_DB_PASS', 'DB_PASS'] as $key) {
            if (getenv($key) !== false && (string)getenv($key) !== '') {
                throw new \RuntimeException("ambient {$key} is forbidden for disposable probe execution");
            }
        }
    }
}

<?php
declare(strict_types=1);

namespace Platform\Recovery;

final class RecoveryProviderPreflightService
{
    /** @var callable():array<string,mixed>|null */
    private $configResolver;

    /** @var array<string,string|false> */
    private array $environment;

    private string $root;

    /**
     * @param callable():array<string,mixed>|null $configResolver
     * @param array<string,string|false>|null $environment
     */
    public function __construct(?callable $configResolver = null, ?array $environment = null, ?string $root = null)
    {
        $this->configResolver = $configResolver;
        $this->environment = $environment ?? $_ENV + $_SERVER + ['PATH' => getenv('PATH') ?: ''];
        $this->root = rtrim($root ?? (defined('APP_ROOT') ? (string)APP_ROOT : dirname(__DIR__, 2)), '/');
    }

    /**
     * @return array<string,mixed>
     */
    public function inspect(): array
    {
        $checks = [
            'php' => [
                'version' => PHP_VERSION,
                'minimum' => '8.1.0',
                'ok' => version_compare(PHP_VERSION, '8.1.0', '>='),
            ],
            'extensions' => [],
        ];
        $blockers = [];
        $warnings = [];

        if (!$checks['php']['ok']) {
            $blockers[] = 'PHP 8.1 or newer is required.';
        }

        foreach (['mysqli', 'zip', 'json'] as $extension) {
            $available = extension_loaded($extension);
            $checks['extensions'][$extension] = ['available' => $available];
            if (!$available) {
                $blockers[] = 'Required PHP extension is unavailable: ' . $extension;
            }
        }

        $config = $this->resolveConfig();
        $database = $this->databaseIdentity($config);
        if (!$database['resolved']) {
            $blockers[] = 'Database configuration is unavailable.';
        }

        $dump = $this->dumpExecutable();
        if (!$dump['available']) {
            $blockers[] = 'MySQL/MariaDB dump executable is unavailable.';
        }

        $storage = $this->storageCandidate();
        if ($storage['parent_exists'] && !$storage['parent_writable']) {
            $blockers[] = 'Recovery storage parent is not writable.';
        }
        if (!$storage['parent_exists']) {
            $warnings[] = 'Recovery storage parent does not exist; preflight did not create it.';
        }

        return [
            'ok' => $blockers === [],
            'blockers' => $blockers,
            'warnings' => $warnings,
            'checks' => $checks,
            'database' => $database,
            'dump_executable' => $dump,
            'storage' => $storage,
            'configured_dump_path_supported' => false,
        ];
    }

    /** @return array<string,mixed>|null */
    private function resolveConfig(): ?array
    {
        if ($this->configResolver !== null) {
            $config = ($this->configResolver)();
            return is_array($config) ? $config : null;
        }

        if (!function_exists('app_db_config')) {
            require_once $this->root . '/app/Core/helpers.php';
        }

        $config = app_db_config();
        return is_array($config) ? $config : null;
    }

    /**
     * @param array<string,mixed>|null $config
     * @return array<string,mixed>
     */
    private function databaseIdentity(?array $config): array
    {
        if ($config === null) {
            return [
                'resolved' => false,
                'source' => 'none',
                'identity' => null,
            ];
        }

        $host = trim((string)($config['host'] ?? 'localhost'));
        $name = trim((string)($config['name'] ?? ''));
        $source = trim((string)($config['_source'] ?? 'resolved'));

        return [
            'resolved' => $host !== '' && $name !== '',
            'source' => $source !== '' ? $source : 'resolved',
            'identity' => [
                'host_label' => $host,
                'port' => (int)($config['port'] ?? 3306),
                'database' => $name,
                'charset' => trim((string)($config['charset'] ?? 'utf8mb4')),
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function dumpExecutable(): array
    {
        foreach (['mysqldump', 'mariadb-dump', 'mysqldump.exe', 'mariadb-dump.exe'] as $candidate) {
            $path = $this->findExecutable($candidate);
            if ($path !== null) {
                return [
                    'available' => true,
                    'name' => $candidate,
                    'path' => $path,
                ];
            }
        }

        return [
            'available' => false,
            'name' => null,
            'path' => null,
        ];
    }

    private function findExecutable(string $name): ?string
    {
        $path = (string)($this->environment['PATH'] ?? '');
        $separator = str_contains($path, ';') ? ';' : PATH_SEPARATOR;
        foreach (array_filter(array_map('trim', explode($separator, $path))) as $directory) {
            $candidate = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /** @return array<string,mixed> */
    private function storageCandidate(): array
    {
        $parent = $this->root . '/storage';
        $candidate = $parent . '/recovery-points';
        $candidateExists = is_dir($candidate);

        return [
            'parent_path' => $parent,
            'parent_exists' => is_dir($parent),
            'parent_writable' => is_dir($parent) && is_writable($parent),
            'candidate_path' => $candidate,
            'candidate_exists' => $candidateExists,
            'candidate_writable' => $candidateExists ? is_writable($candidate) : null,
        ];
    }
}
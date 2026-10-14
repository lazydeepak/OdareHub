<?php
declare(strict_types=1);

/**
 * Common pre-bootstrap boundary for DB-backed application probes.
 *
 * A DB-backed probe may not use the repository as APP_ROOT. It must receive a
 * complete isolated source root, an attested temporary resource root, and the
 * process/socket/datadir identity. Missing evidence fails before autoloading.
 */
final class DisposableDbTestEntryPointGuard
{
    /**
     * @return array{app_root:string,config_path:string,config_hash:string}
     */
    public static function authorize(string $prefix, string $repositoryRoot): array
    {
        $env = static function (string $key): string {
            $value = getenv($key);
            return $value === false ? '' : trim((string)$value);
        };
        $repo = $env($prefix . '_REPO');
        $appRoot = $env($prefix . '_APP_ROOT');
        $resourceRoot = $env($prefix . '_DB_RESOURCE_ROOT');
        $port = $env($prefix . '_DB_PORT');
        $name = $env($prefix . '_DB_NAME');
        $user = $env($prefix . '_DB_USER');
        $pid = $env($prefix . '_DB_PID');
        $socket = $env($prefix . '_DB_SOCKET');
        $datadir = $env($prefix . '_DB_DATADIR');
        foreach (compact('repo', 'appRoot', 'resourceRoot', 'port', 'name', 'user', 'pid', 'socket', 'datadir') as $key => $value) {
            if ($value === '') {
                throw new \RuntimeException("missing governed {$prefix} database entry-point evidence: {$key}");
            }
        }
        $repositoryReal = realpath($repositoryRoot);
        $providedRepoReal = realpath($repo);
        if ($repositoryReal === false || $providedRepoReal === false || $repositoryReal !== $providedRepoReal) {
            throw new \RuntimeException('governed database probe repository identity mismatch');
        }

        require_once __DIR__ . '/DisposableDbTestConfigurationGuard.php';
        $configuration = DisposableDbTestConfigurationGuard::authorize([
            'repo' => $repositoryReal,
            'app_root' => $appRoot,
            'resource_root' => $resourceRoot,
            'host' => '127.0.0.1',
            'port' => $port,
            'name' => $name,
            'user' => $user,
        ]);

        $appRootReal = realpath($appRoot);
        if ($appRootReal === false
            || !is_file($appRootReal . '/vendor/autoload.php')
            || !is_dir($appRootReal . '/apps')) {
            throw new \RuntimeException('governed APP_ROOT must be a complete isolated source root');
        }

        require_once __DIR__ . '/DisposableDbExecutionAuthorizer.php';
        $execution = DisposableDbExecutionAuthorizer::authorize(
            (int)$pid,
            $socket,
            $datadir,
            $resourceRoot,
            (int)$port
        );
        if (!$execution['authorized']) {
            throw new \RuntimeException('disposable execution attestation failed before application bootstrap');
        }
        DisposableDbTestConfigurationGuard::assertUnchanged($configuration);
        register_shutdown_function(static function () use ($configuration): void {
            try {
                DisposableDbTestConfigurationGuard::assertUnchanged($configuration);
            } catch (\Throwable $e) {
                fwrite(STDERR, "FAIL: governed database configuration changed during probe: {$e->getMessage()}\n");
                exit(2);
            }
        });

        return [
            'app_root' => $appRootReal,
            'config_path' => $configuration['config_path'],
            'config_hash' => $configuration['config_hash'],
        ];
    }
}

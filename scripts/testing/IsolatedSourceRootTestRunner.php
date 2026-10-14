<?php
declare(strict_types=1);

/**
 * Preparation-only source-root builder for governed database probes.
 *
 * This class never loads application code, creates database authority, starts
 * a process, opens a socket, reads installed configuration, or executes SQL.
 */
final class IsolatedSourceRootTestRunner
{
    private const VERSION = 'oh-auto1-044-v1';

    /**
     * @return list<string>
     */
    public static function databaseEntryPoints(): array
    {
        return [
            'apps/Procurement/Tests/probe_procurement_concurrency.php',
            'apps/Procurement/Tests/probe_behavioral_cancellation.php',
            'apps/Procurement/Tests/probe_behavioral_request_cancel.php',
            'apps/Procurement/Tests/probe_behavioral_supplier_update.php',
            'apps/Manufacturing/Tests/probe_upstream_supply_transaction.php',
            'apps/Manufacturing/Tests/probe_upstream_supply_concurrency.php',
            'apps/Hospitality/Tests/probe_reservation_create_concurrency.php',
            'apps/Hospitality/Tests/probe_reservation_transition_concurrency.php',
            'apps/Hospitality/Tests/probe_slice1_registration.php',
            'apps/Hospitality/Tests/probe_slice2_migrations.php',
            'apps/Hospitality/Tests/probe_slice3_modules.php',
            'apps/Hospitality/Tests/probe_slice4_routes_navigation.php',
            'apps/Hospitality/Tests/probe_slice5_rooms_guests_crud.php',
            'apps/Hospitality/Tests/probe_slice6_reservations_lifecycle.php',
            'apps/Hospitality/Tests/probe_slice7_frontdesk_folio.php',
            'apps/Hospitality/Tests/probe_slice8_housekeeping_board.php',
            'apps/Hospitality/Tests/probe_frontdesk_void_readiness.php',
            'apps/Hospitality/Tests/probe_install_admin_lifecycle.php',
            'apps/Hospitality/Tests/probe_operator_slice2_contribution.php',
            'apps/Hospitality/Tests/probe_operator_slice4_housekeeping_action.php',
            'apps/Hospitality/Tests/probe_operator_slice5_frontdesk_checkin.php',
            'apps/Hospitality/Tests/probe_operator_slice6_frontdesk_checkout.php',
            'apps/Hospitality/Tests/probe_operator_slice7_add_charge.php',
            'apps/Hospitality/Tests/probe_operator_slice8_cancel.php',
            'apps/Hospitality/Tests/probe_operator_slice9_no_show.php',
        ];
    }

    /**
     * Prepare a complete source root with no runtime storage or credentials.
     *
     * @return array{workspace:string,source_root:string,manifest_path:string,manifest:array<string,mixed>}
     */
    public static function prepare(string $repositoryRoot, ?string $workspace = null): array
    {
        $repository = self::directory($repositoryRoot, 'repository');
        $temporaryRoot = self::directory(sys_get_temp_dir(), 'system temp');
        $workspacePath = $workspace === null
            ? $temporaryRoot . '/oh-auto1-044-' . bin2hex(random_bytes(8))
            : self::absolutePath($workspace);
        $workspacePath = self::canonicalNewPath($workspacePath);
        self::assertWorkspaceDestination($repository, $workspacePath, $temporaryRoot);
        if (file_exists($workspacePath)) {
            throw new \RuntimeException('runner workspace already exists');
        }
        if (!mkdir($workspacePath, 0700, true)) {
            throw new \RuntimeException('runner workspace could not be created');
        }

        $sourceRoot = $workspacePath . '/source-root';
        mkdir($sourceRoot, 0700, true);
        $ownerToken = bin2hex(random_bytes(24));
        $marker = [
            'runner' => self::VERSION,
            'owner_token' => $ownerToken,
            'repository' => hash('sha256', $repository),
            'created_at' => gmdate('c'),
        ];
        self::writeJson($workspacePath . '/.oh-auto1-044-owner.json', $marker);

        try {
            $copied = [];
            foreach (['app', 'apps', 'platform', 'plugins'] as $directory) {
                self::copyTree($repository . '/' . $directory, $sourceRoot . '/' . $directory, $repository, $copied);
            }
            self::copyFile($repository . '/composer.json', $sourceRoot . '/composer.json', $repository, $copied);
            if (is_file($repository . '/composer.lock')) {
                self::copyFile($repository . '/composer.lock', $sourceRoot . '/composer.lock', $repository, $copied);
            }
            self::copyVendor($repository . '/vendor', $sourceRoot . '/vendor', $repository, $copied);

            mkdir($sourceRoot . '/storage', 0700, true);
            self::assertPreparedRoot($sourceRoot);
            $manifest = [
                'runner' => self::VERSION,
                'source_root' => 'source-root',
                'authority' => 'none',
                'bootstrap_executed' => false,
                'database_connected' => false,
                'sql_executed' => false,
                'excluded' => [
                    '.env files',
                    'storage contents and db_config.php',
                    'runtime logs and database data',
                    'private keys and credential-named files',
                    'vendor test/docs/examples/tooling trees',
                    'vendor/bin generated caches',
                    'all symlinks',
                ],
                'entry_points' => self::databaseEntryPoints(),
                'files' => $copied,
            ];
            $manifestPath = $workspacePath . '/source-manifest.json';
            self::writeJson($manifestPath, $manifest);

            return [
                'workspace' => $workspacePath,
                'source_root' => $sourceRoot,
                'manifest_path' => $manifestPath,
                'manifest' => $manifest,
            ];
        } catch (\Throwable $e) {
            if (is_file($workspacePath . '/.oh-auto1-044-owner.json')) {
                self::cleanup($workspacePath);
            }
            throw $e;
        }
    }

    /** @param array<string,mixed> $manifest */
    public static function assertManifestUnchanged(string $sourceRoot, array $manifest): void
    {
        foreach ((array)($manifest['files'] ?? []) as $relative => $hash) {
            $path = self::resolveRelative($sourceRoot, (string)$relative);
            if (!is_file($path) || hash_file('sha256', $path) !== $hash) {
                throw new \RuntimeException("staged source changed: {$relative}");
            }
        }
    }

    public static function cleanup(string $workspace): void
    {
        $path = self::absolutePath($workspace);
        $tmp = self::directory(sys_get_temp_dir(), 'system temp');
        if (!self::within($tmp, $path)) {
            throw new \RuntimeException('cleanup target is outside the runner-owned temporary root');
        }
        $markerPath = $path . '/.oh-auto1-044-owner.json';
        if (!is_file($markerPath)) {
            throw new \RuntimeException('cleanup target lacks runner ownership marker');
        }
        $marker = json_decode((string)file_get_contents($markerPath), true);
        if (!is_array($marker) || ($marker['runner'] ?? '') !== self::VERSION || trim((string)($marker['owner_token'] ?? '')) === '') {
            throw new \RuntimeException('cleanup target has an invalid runner ownership marker');
        }
        self::removeTree($path);
    }

    public static function validateRelativePath(string $relative): string
    {
        if ($relative === '' || str_starts_with($relative, '/') || str_contains($relative, '\\')) {
            throw new \RuntimeException('relative path is absolute or malformed');
        }
        $parts = explode('/', $relative);
        if (in_array('..', $parts, true) || in_array('', $parts, true)) {
            throw new \RuntimeException('relative path contains traversal or empty segments');
        }
        return implode('/', $parts);
    }

    private static function copyTree(string $source, string $destination, string $repository, array &$copied): void
    {
        if (!is_dir($source)) {
            throw new \RuntimeException("required source directory is missing: {$source}");
        }
        self::walk($source, $destination, $repository, $copied);
    }

    private static function walk(string $source, string $destination, string $repository, array &$copied): void
    {
        if (is_link($source)) {
            throw new \RuntimeException("symlink is forbidden: {$source}");
        }
        if (!is_dir($destination) && !mkdir($destination, 0700, true)) {
            throw new \RuntimeException("cannot create staged directory: {$destination}");
        }
        $iterator = new \FilesystemIterator($source, \FilesystemIterator::SKIP_DOTS);
        foreach ($iterator as $item) {
            $name = $item->getFilename();
            $sourcePath = $item->getPathname();
            if (self::excluded($sourcePath, $name)) {
                continue;
            }
            if ($item->isLink()) {
                throw new \RuntimeException("symlink is forbidden: {$sourcePath}");
            }
            $targetPath = $destination . '/' . $name;
            if ($item->isDir()) {
                self::walk($sourcePath, $targetPath, $repository, $copied);
            } elseif ($item->isFile()) {
                self::copyFile($sourcePath, $targetPath, $repository, $copied);
            }
        }
    }

    private static function copyVendor(string $source, string $destination, string $repository, array &$copied): void
    {
        if (!is_dir($source)) {
            throw new \RuntimeException('vendor dependency root is missing');
        }
        foreach (['autoload.php', 'autoload_real.php', 'autoload_static.php', 'autoload_psr4.php', 'autoload_namespaces.php', 'autoload_classmap.php', 'autoload_files.php', 'autoload_runtime.php'] as $file) {
            if (is_file($source . '/' . $file)) {
                self::copyFile($source . '/' . $file, $destination . '/' . $file, $repository, $copied);
            }
        }
        self::copyTree($source . '/composer', $destination . '/composer', $repository, $copied);
        $installedPath = $source . '/composer/installed.json';
        $installed = is_file($installedPath) ? json_decode((string)file_get_contents($installedPath), true) : null;
        if (!is_array($installed)) {
            throw new \RuntimeException('vendor installed dependency manifest is missing or invalid');
        }
        foreach ((array)($installed['packages'] ?? $installed) as $package) {
            $name = is_array($package) ? (string)($package['name'] ?? '') : '';
            if (!preg_match('/^([a-z0-9_.-]+)\/([a-z0-9_.-]+)$/i', $name, $match)) {
                throw new \RuntimeException('invalid installed dependency name');
            }
            self::copyTree($source . '/' . $match[1] . '/' . $match[2], $destination . '/' . $match[1] . '/' . $match[2], $repository, $copied);
        }
    }

    private static function copyFile(string $source, string $destination, string $repository, array &$copied): void
    {
        if (is_link($source) || !is_file($source)) {
            throw new \RuntimeException("required source file is missing or symlinked: {$source}");
        }
        $real = realpath($source);
        if ($real === false || !self::within($repository, $real)) {
            throw new \RuntimeException("source escapes repository: {$source}");
        }
        if (self::credentialPath($source)) {
            throw new \RuntimeException("credential-bearing path is forbidden: {$source}");
        }
        if (!is_dir(dirname($destination)) && !mkdir(dirname($destination), 0700, true)) {
            throw new \RuntimeException("cannot create staged file directory: {$destination}");
        }
        if (!copy($source, $destination)) {
            throw new \RuntimeException("cannot stage file: {$source}");
        }
        $relative = ltrim(substr($real, strlen($repository)), DIRECTORY_SEPARATOR);
        $copied[$relative] = hash_file('sha256', $destination);
    }

    private static function assertPreparedRoot(string $sourceRoot): void
    {
        if (is_file($sourceRoot . '/storage/db_config.php') || is_file($sourceRoot . '/.env')) {
            throw new \RuntimeException('prepared source root contains installed configuration');
        }
        foreach (self::databaseEntryPoints() as $entryPoint) {
            if (!is_file($sourceRoot . '/' . $entryPoint)) {
                throw new \RuntimeException("guarded entry point was not staged: {$entryPoint}");
            }
        }
        if (!is_file($sourceRoot . '/vendor/autoload.php')) {
            throw new \RuntimeException('prepared dependency autoload is missing');
        }
    }

    private static function excluded(string $path, string $basename): bool
    {
        $normalized = str_replace('\\', '/', $path);
        if (preg_match('~/(?:storage|\.git)(?:/|$)~', $normalized)) {
            return true;
        }
        if ($basename === 'bin' && preg_match('~/vendor/bin$~', $normalized)) {
            return true;
        }
        if (preg_match('~/vendor/[^/]+/[^/]+/(?:_test|tests?|docs|examples|\.github|bin)(?:/|$)~i', $normalized)) {
            return true;
        }
        return self::credentialPath($path);
    }

    private static function credentialPath(string $path): bool
    {
        $normalized = strtolower(str_replace('\\', '/', $path));
        return preg_match('~/(?:\.env(?:\.|$)|db_config\.php$|credentials?|secrets?|private[^/]*\.(?:pem|key)$|[^/]+\.(?:pem|key)$)~', $normalized) === 1;
    }

    private static function resolveRelative(string $root, string $relative): string
    {
        $safe = self::validateRelativePath($relative);
        $path = rtrim(self::directory($root, 'source root'), DIRECTORY_SEPARATOR) . '/' . $safe;
        $parent = realpath(dirname($path));
        if ($parent === false || !self::within(self::directory($root, 'source root'), $parent)) {
            throw new \RuntimeException('relative path escapes source root');
        }
        return $path;
    }

    private static function assertWorkspaceDestination(string $repository, string $workspace, string $temporaryRoot): void
    {
        if (!self::within($temporaryRoot, $workspace) || self::within($repository, $workspace)) {
            throw new \RuntimeException('workspace must be a new child of system temp outside the repository');
        }
    }

    private static function canonicalNewPath(string $path): string
    {
        $parent = realpath(dirname($path));
        if ($parent === false || !is_dir($parent)) {
            throw new \RuntimeException('runner workspace parent must be an existing directory');
        }
        return rtrim($parent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($path);
    }

    private static function directory(string $path, string $label): string
    {
        $real = realpath($path);
        if ($real === false || !is_dir($real)) {
            throw new \RuntimeException("{$label} is not an existing directory");
        }
        return rtrim($real, DIRECTORY_SEPARATOR);
    }

    private static function absolutePath(string $path): string
    {
        if ($path === '' || !str_starts_with($path, DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('runner path must be absolute');
        }
        return rtrim($path, DIRECTORY_SEPARATOR);
    }

    private static function within(string $parent, string $child): bool
    {
        $parent = rtrim($parent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return str_starts_with(rtrim($child, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR, $parent);
    }

    private static function writeJson(string $path, array $value): void
    {
        if (file_put_contents($path, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX) === false) {
            throw new \RuntimeException("cannot write runner manifest: {$path}");
        }
    }

    private static function removeTree(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);
            return;
        }
        if (!is_dir($path)) {
            return;
        }
        foreach (new \FilesystemIterator($path, \FilesystemIterator::SKIP_DOTS) as $item) {
            self::removeTree($item->getPathname());
        }
        rmdir($path);
    }
}

<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Container;
use App\Core\DB;
use App\Core\EventBus;
use App\Core\PluginManager;
use App\Core\Router;
use App\Core\View;

final class AppInstallService
{
    private AppPackageService $packageService;
    private AppRegistryService $registry;
    private AppMigrationService $migrationService;
    private ModuleLifecycleService $modules;

    public function __construct(
        ?AppPackageService $packageService = null,
        ?AppRegistryService $registry = null,
        ?AppMigrationService $migrationService = null,
        ?ModuleLifecycleService $modules = null
    ) {
        $this->packageService = $packageService ?? new AppPackageService();
        $this->registry = $registry ?? new AppRegistryService();
        $this->migrationService = $migrationService ?? new AppMigrationService();
        $this->modules = $modules ?? new ModuleLifecycleService();
    }

    public function uploadAndRegister(array $file): array
    {
        $upload = $this->packageService->upload($file);
        return $this->registerPackageZip($upload['zip_path'], (string)$upload['checksum']);
    }

    public function registerPackageZip(string $zipPath, ?string $checksum = null): array
    {
        $extracted = $this->packageService->extractAndValidate($zipPath);
        $manifest = $extracted['manifest'];

        $currentUser = (string)(Auth::user()['email'] ?? 'system');

        $existing = $this->registry->find((string)$manifest['id']);
        if ($existing) {
            $existingVersion = (string)($existing['version'] ?? '0.0.0');
            $incomingVersion = (string)$manifest['version'];

            if (version_compare($incomingVersion, $existingVersion, '<')) {
                throw new \RuntimeException('Uploaded package version is older than installed/registered app version');
            }

            if (version_compare($incomingVersion, $existingVersion, '==')) {
                $existingChecksum = (string)($existing['checksum'] ?? '');
                $incomingChecksum = $checksum ?? (hash_file('sha256', $zipPath) ?: '');
                if ($existingChecksum !== '' && $existingChecksum === $incomingChecksum) {
                    throw new \RuntimeException('Duplicate app package upload: same app id, version, and checksum already registered');
                }
            }
        }

        $effectiveChecksum = $checksum ?? (hash_file('sha256', $zipPath) ?: '');

        $this->registry->upsertUploaded(
            $manifest,
            (string)$extracted['extract_dir'],
            $effectiveChecksum,
            $currentUser
        );

        if ($existing && version_compare((string)$manifest['version'], (string)($existing['version'] ?? '0.0.0'), '>')) {
            $this->registry->setStatus((string)$manifest['id'], AppRegistryService::STATUS_UPGRADE_PENDING, null);
        }

        AppPlatformLogger::lifecycle('uploaded', (string)$manifest['id'], [
            'zip' => $zipPath,
            'checksum' => $effectiveChecksum,
            'package_type' => (string)($manifest['package_type'] ?? 'bundle'),
        ]);

        return [
            'manifest' => $manifest,
            'extract_dir' => (string)$extracted['extract_dir'],
            'zip_path' => $zipPath,
        ];
    }

    /**
     * @return array<int,string>
     */
    public function install(string $appKey): array
    {
        $stack = [];
        return $this->installInternal($appKey, $stack);
    }

    /**
     * @return array<int,string>
     */
    public function prepareInstallDependencies(string $appKey): array
    {
        $stack = [];
        return $this->prepareInstallDependenciesInternal($appKey, $stack);
    }

    /**
     * @param array<int,string> $stack
     * @return array<int,string>
     */
    private function installInternal(string $appKey, array &$stack): array
    {
        $appKey = strtolower(trim($appKey));
        if ($appKey === '') {
            throw new \RuntimeException('App key is required for install.');
        }

        $token = 'app:' . $appKey;
        $this->assertNoCycle($stack, $token);
        $stack[] = $token;

        try {
            $messages = $this->prepareInstallDependenciesInternal($appKey, $stack);
            $row = $this->registryRow($appKey);
            $manifest = $this->storedManifest($row);

            $entry = trim((string)($manifest['entry'] ?? ''));
            if ($entry === '') {
                throw new \RuntimeException('Manifest entry is missing');
            }

            $db = DB::conn();
            $db->begin_transaction();
            try {
                $installPath = $this->packageService->moveInstalledTree((string)$row['install_path'], (string)$row['app_key']);
                $entryFile = rtrim($installPath, '/') . '/' . ltrim($entry, '/');
                if (!is_file($entryFile)) {
                    throw new \RuntimeException('Install failed: entry file not found after package move');
                }

                $migrationsPath = rtrim($installPath, '/') . '/' . ltrim((string)$manifest['migrations_path'], '/');

                $applied = $this->migrationService->runMigrations((string)$row['app_key'], (string)$row['version'], $migrationsPath);
                (new AppRuntimeRegistryService())->refreshAppRuntimeArtifacts((string)$row['app_key'], $manifest, false);
                $legacyBridgeResults = AppLegacyBridgeService::installLegacyPluginsForApp((string)$row['app_key'], $manifest);

                DB::query(
                    'UPDATE core_apps SET status=?, install_path=?, installed_at=NOW(), error_text=NULL WHERE app_key=?',
                    [AppRegistryService::STATUS_INSTALLED, $installPath, (string)$row['app_key']]
                );

                $snapshot = [
                    'app_key' => (string)$row['app_key'],
                    'version' => (string)$row['version'],
                    'applied_migrations' => $applied,
                ];
                $createdBy = (string)(Auth::user()['email'] ?? 'system');
                $this->migrationService->writeSchemaSnapshot((string)$row['app_key'], (string)$row['version'], $snapshot, $createdBy);

                $db->commit();
                AppPlatformLogger::lifecycle('installed', (string)$row['app_key'], [
                    'migrations' => $applied,
                    'legacy_bridge' => $legacyBridgeResults,
                ]);
                return array_values(array_unique($messages));
            } catch (\Throwable $e) {
                $db->rollback();
                $this->registry->setStatus((string)$row['app_key'], AppRegistryService::STATUS_BROKEN, $e->getMessage());
                AppPlatformLogger::lifecycle('install_failed', (string)$row['app_key'], ['error' => $e->getMessage()]);
                throw $e;
            }
        } finally {
            array_pop($stack);
        }
    }

    /**
     * @param array<int,string> $stack
     * @return array<int,string>
     */
    private function prepareInstallDependenciesInternal(string $appKey, array &$stack): array
    {
        $row = $this->registryRow($appKey);
        $manifest = $this->storedManifest($row);
        $messages = [];

        foreach ((array)($manifest['dependencies'] ?? []) as $rawRequirement) {
            $messages = array_merge($messages, $this->ensureDependencySatisfied((string)$rawRequirement, $stack));
        }

        return array_values(array_unique(array_filter(array_map('strval', $messages))));
    }

    /**
     * @param array<int,string> $stack
     * @return array<int,string>
     */
    private function ensureDependencySatisfied(string $rawRequirement, array &$stack): array
    {
        $rawRequirement = trim($rawRequirement);
        if ($rawRequirement === '') {
            return [];
        }

        $dependencyName = DependencyRequirementService::name($rawRequirement);
        if ($dependencyName === '') {
            return [];
        }

        $bundleRow = $this->bundleDependencyRow($dependencyName);
        if (is_array($bundleRow)) {
            return $this->ensureBundleDependencyInstalled($bundleRow, $rawRequirement, $stack);
        }

        $module = $this->moduleDependencyManifest($dependencyName);
        if (is_array($module)) {
            return $this->ensureModuleDependencyInstalled(
                (string)$module['name'],
                (array)$module['manifest'],
                $rawRequirement,
                $stack
            );
        }

        throw new \RuntimeException('Missing install dependency: ' . $rawRequirement);
    }

    /**
     * @param array<string,mixed> $row
     * @param array<int,string> $stack
     * @return array<int,string>
     */
    private function ensureBundleDependencyInstalled(array $row, string $rawRequirement, array &$stack): array
    {
        $appKey = strtolower(trim((string)($row['app_key'] ?? '')));
        if ($appKey === '') {
            throw new \RuntimeException('Missing install dependency: ' . $rawRequirement);
        }

        $actualVersion = trim((string)($row['version'] ?? ''));
        if (!DependencyRequirementService::isSatisfied($rawRequirement, $actualVersion)) {
            throw new \RuntimeException('Dependency version mismatch: ' . $rawRequirement . ' is not satisfied by bundle ' . $appKey . ' (' . ($actualVersion !== '' ? $actualVersion : 'unknown') . ').');
        }

        $readyStatuses = [
            AppRegistryService::STATUS_INSTALLED,
            AppRegistryService::STATUS_ENABLED,
            AppRegistryService::STATUS_DISABLED,
        ];
        $installableStatuses = [
            AppRegistryService::STATUS_UPLOADED,
            AppRegistryService::STATUS_UNINSTALLED,
            AppRegistryService::STATUS_UPGRADE_PENDING,
            AppRegistryService::STATUS_BROKEN,
        ];
        $status = (string)($row['status'] ?? AppRegistryService::STATUS_UPLOADED);

        if (in_array($status, $readyStatuses, true)) {
            return [];
        }

        if (!in_array($status, $installableStatuses, true)) {
            throw new \RuntimeException('Missing install dependency: ' . $rawRequirement);
        }

        $messages = $this->installInternal($appKey, $stack);
        $messages[] = 'Installed bundle dependency: ' . $appKey . '.';
        return array_values(array_unique($messages));
    }

    /**
     * @param array<string,mixed> $manifest
     * @param array<int,string> $stack
     * @return array<int,string>
     */
    private function ensureModuleDependencyInstalled(string $moduleName, array $manifest, string $rawRequirement, array &$stack): array
    {
        $moduleName = trim($moduleName);
        if ($moduleName === '') {
            throw new \RuntimeException('Missing install dependency: ' . $rawRequirement);
        }

        $token = 'module:' . strtolower($moduleName);
        $this->assertNoCycle($stack, $token);
        $stack[] = $token;

        try {
            $messages = [];
            $manifestVersion = trim((string)($manifest['version'] ?? ''));
            $installedRow = DB::fetchOne('SELECT version, status FROM installed_plugins WHERE name=? LIMIT 1', [$moduleName]);
            $installedVersion = trim((string)($installedRow['version'] ?? ''));

            if ($installedVersion === '' && !DependencyRequirementService::isSatisfied($rawRequirement, $manifestVersion)) {
                throw new \RuntimeException('Dependency version mismatch: ' . $rawRequirement . ' is not satisfied by module ' . $moduleName . ' (' . ($manifestVersion !== '' ? $manifestVersion : 'unknown') . ').');
            }

            foreach ((array)($manifest['requires'] ?? []) as $nestedRequirement) {
                $messages = array_merge($messages, $this->ensureDependencySatisfied((string)$nestedRequirement, $stack));
            }

            if ($installedVersion !== '' && !DependencyRequirementService::isSatisfied($rawRequirement, $installedVersion)) {
                if ($manifestVersion !== '' && DependencyRequirementService::isSatisfied($rawRequirement, $manifestVersion)) {
                    $this->modules->run($moduleName, 'repair');
                    $messages[] = 'Updated module dependency: ' . $moduleName . '.';
                    $installedRow = DB::fetchOne('SELECT version, status FROM installed_plugins WHERE name=? LIMIT 1', [$moduleName]);
                    $installedVersion = trim((string)($installedRow['version'] ?? ''));
                } else {
                    throw new \RuntimeException('Dependency version mismatch: ' . $rawRequirement . ' is not satisfied by module ' . $moduleName . ' (' . $installedVersion . ').');
                }
            }

            if (!$this->pluginManager()->isInstalled($moduleName)) {
                $this->modules->run($moduleName, 'install');
                $messages[] = 'Installed module dependency: ' . $moduleName . '.';
                $installedRow = DB::fetchOne('SELECT version, status FROM installed_plugins WHERE name=? LIMIT 1', [$moduleName]);
                $installedVersion = trim((string)($installedRow['version'] ?? ''));
            }

            $actualVersion = $installedVersion !== '' ? $installedVersion : $manifestVersion;
            if (!DependencyRequirementService::isSatisfied($rawRequirement, $actualVersion)) {
                throw new \RuntimeException('Dependency version mismatch: ' . $rawRequirement . ' is not satisfied by module ' . $moduleName . ' (' . ($actualVersion !== '' ? $actualVersion : 'unknown') . ').');
            }

            if (($this->pluginManager()->status($moduleName) ?? 'missing') !== 'active') {
                $this->pluginManager()->enable($moduleName);
                $messages[] = 'Enabled module dependency: ' . $moduleName . '.';
            }

            return array_values(array_unique($messages));
        } finally {
            array_pop($stack);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function registryRow(string $appKey): array
    {
        $appKey = strtolower(trim($appKey));
        $row = $this->registry->find($appKey);
        if (!is_array($row) && $appKey !== '') {
            foreach ($this->registry->listAll() as $candidate) {
                if (strcasecmp((string)($candidate['app_key'] ?? ''), $appKey) === 0) {
                    $row = $candidate;
                    break;
                }
            }
        }

        if (!is_array($row)) {
            throw new \RuntimeException('App not found in core registry: ' . $appKey);
        }

        return $row;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function storedManifest(array $row): array
    {
        $manifest = json_decode((string)($row['manifest_json'] ?? '{}'), true);
        if (!is_array($manifest)) {
            throw new \RuntimeException('Stored manifest_json is invalid');
        }

        $manifestId = trim((string)($manifest['id'] ?? ''));
        if ($manifestId === '' || strtolower($manifestId) !== strtolower((string)$row['app_key'])) {
            throw new \RuntimeException('Manifest id mismatch with app_key');
        }

        return $manifest;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function bundleDependencyRow(string $dependencyName): ?array
    {
        $exact = $this->registry->find($dependencyName);
        if (is_array($exact)) {
            return $exact;
        }

        $lower = strtolower(trim($dependencyName));
        if ($lower !== '' && $lower !== $dependencyName) {
            $normalized = $this->registry->find($lower);
            if (is_array($normalized)) {
                return $normalized;
            }
        }

        foreach ($this->registry->listAll() as $row) {
            if (strcasecmp((string)($row['app_key'] ?? ''), $dependencyName) === 0) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return array{name:string,manifest:array<string,mixed>}|null
     */
    private function moduleDependencyManifest(string $dependencyName): ?array
    {
        $manifests = $this->pluginManager()->manifests();
        if (isset($manifests[$dependencyName]) && is_array($manifests[$dependencyName])) {
            return [
                'name' => $dependencyName,
                'manifest' => $manifests[$dependencyName],
            ];
        }

        foreach ($manifests as $name => $manifest) {
            if (strcasecmp((string)$name, $dependencyName) === 0 && is_array($manifest)) {
                return [
                    'name' => (string)$name,
                    'manifest' => $manifest,
                ];
            }
        }

        return null;
    }

    /**
     * @param array<int,string> $stack
     */
    private function assertNoCycle(array $stack, string $token): void
    {
        if (!in_array($token, $stack, true)) {
            return;
        }

        $chain = array_map(function (string $entry): string {
            if (str_starts_with($entry, 'app:')) {
                return substr($entry, 4);
            }
            if (str_starts_with($entry, 'module:')) {
                return substr($entry, 7);
            }
            return $entry;
        }, array_merge($stack, [$token]));

        throw new \RuntimeException('Circular install dependency detected: ' . implode(' -> ', $chain));
    }

    private function pluginManager(): PluginManager
    {
        static $manager = null;
        if ($manager instanceof PluginManager) {
            return $manager;
        }

        $container = new Container();
        $router = new Router();
        $view = new View(APP_ROOT . '/public/views');
        $bus = new EventBus();
        $manager = new PluginManager(APP_ROOT . '/plugins', $container, $router, $view, $bus);

        return $manager;
    }
}

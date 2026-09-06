<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Core\DB;
use App\Core\EventBus;
use App\Core\PluginManager;
use App\Core\Router;
use App\Core\View;

final class VersionCatalogService
{
    private ?array $sbaioCatalog = null;
    private ?array $manufacturingCatalog = null;
    private ?array $coreCatalog = null;

    /**
     * @return array<string,mixed>
     */
    public function coreInfo(): array
    {
        $catalog = $this->coreCatalog();
        $entries = $this->normalizeEntries((array)($catalog['releases'] ?? []));
        return [
            'layer' => 'core',
            'key' => 'core',
            'label' => 'Susankhya OS Core',
            'current_version' => APP_VERSION,
            'target_version' => APP_VERSION,
            'upgrade_available' => false,
            'latest_entry' => $entries[0] ?? $this->emptyEntry(APP_VERSION),
            'entries' => $entries,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function suiteInfo(string $suiteKey): array
    {
        $suiteKey = strtolower(trim($suiteKey));
        $manifestPath = match ($suiteKey) {
            'sbaio' => APP_ROOT . '/apps/SBAIO/manifest.json',
            'manufacturing' => APP_ROOT . '/apps/Manufacturing/manifest.json',
            default => '',
        };
        $manifest = $manifestPath !== '' && is_file($manifestPath)
            ? AppManifestService::loadFromFile($manifestPath)
            : [];
        $registered = $suiteKey !== '' ? DB::fetchOne('SELECT version, status FROM core_apps WHERE app_key=? LIMIT 1', [$suiteKey]) : null;
        $snapshot = $suiteKey !== ''
            ? DB::fetchOne('SELECT app_version FROM core_schema_snapshots WHERE app_key=? ORDER BY id DESC LIMIT 1', [$suiteKey])
            : null;
        $currentVersion = trim((string)($snapshot['app_version'] ?? ($registered['version'] ?? ($manifest['version'] ?? '0.0.0'))));
        $targetVersion = trim((string)($registered['version'] ?? ($manifest['version'] ?? $currentVersion)));
        $entries = $this->normalizeEntries((array)($this->suiteCatalog($suiteKey)['suite']['releases'] ?? []));

        return [
            'layer' => 'suite',
            'key' => $suiteKey,
            'label' => (string)($manifest['name'] ?? ucfirst($suiteKey)),
            'current_version' => $currentVersion,
            'target_version' => $targetVersion,
            'upgrade_available' => $currentVersion !== '' && $targetVersion !== '' && version_compare($targetVersion, $currentVersion, '>'),
            'latest_entry' => $this->entryForVersion($entries, $targetVersion) ?? ($entries[0] ?? $this->emptyEntry($targetVersion)),
            'entries' => $entries,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function moduleInfo(string $moduleName): array
    {
        $moduleName = trim($moduleName);
        $manifest = $this->pluginManager()->manifests()[$moduleName] ?? [];
        $suiteKey = strtolower(trim((string)($manifest['suite'] ?? 'other')));
        $installed = DB::fetchOne('SELECT version, status FROM installed_plugins WHERE name=? LIMIT 1', [$moduleName]);
        $currentVersion = trim((string)($installed['version'] ?? ($manifest['version'] ?? '0.0.0')));
        $targetVersion = trim((string)($manifest['version'] ?? $currentVersion));
        $entries = $this->normalizeEntries((array)($this->moduleCatalog($suiteKey, $moduleName)['releases'] ?? []));

        return [
            'layer' => 'module',
            'key' => $moduleName,
            'suite' => $suiteKey,
            'label' => (string)($manifest['display_name'] ?? $moduleName),
            'current_version' => $currentVersion,
            'target_version' => $targetVersion,
            'upgrade_available' => $currentVersion !== '' && $targetVersion !== '' && version_compare($targetVersion, $currentVersion, '>'),
            'latest_entry' => $this->entryForVersion($entries, $targetVersion) ?? ($entries[0] ?? $this->emptyEntry($targetVersion)),
            'entries' => $entries,
        ];
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function suiteModuleMap(string $suiteKey): array
    {
        $map = [];
        foreach ($this->pluginManager()->manifests() as $moduleName => $manifest) {
            if (strtolower(trim((string)($manifest['suite'] ?? ''))) !== strtolower(trim($suiteKey))) {
                continue;
            }
            $map[$moduleName] = $this->moduleInfo($moduleName);
        }
        return $map;
    }

    /**
     * @return array<string,mixed>
     */
    public function appDetailInfo(string $appKey): array
    {
        if ($appKey === 'core') {
            return $this->coreInfo();
        }
        return $this->suiteInfo($appKey);
    }

    /**
     * @return array<string,mixed>
     */
    public function coreCatalog(): array
    {
        if (is_array($this->coreCatalog)) {
            return $this->coreCatalog;
        }
        $file = APP_ROOT . '/app/versioning/core.php';
        $data = is_file($file) ? require $file : [];
        $this->coreCatalog = is_array($data) ? $data : [];
        return $this->coreCatalog;
    }

    /**
     * @return array<string,mixed>
     */
    private function suiteCatalog(string $suiteKey): array
    {
        if ($suiteKey === 'sbaio') {
            if (!is_array($this->sbaioCatalog)) {
                $file = APP_ROOT . '/apps/SBAIO/versioning.php';
                $data = is_file($file) ? require $file : [];
                $this->sbaioCatalog = is_array($data) ? $data : [];
            }
            return $this->sbaioCatalog;
        }
        if ($suiteKey === 'manufacturing') {
            if (!is_array($this->manufacturingCatalog)) {
                $file = APP_ROOT . '/apps/Manufacturing/versioning.php';
                $data = is_file($file) ? require $file : [];
                $this->manufacturingCatalog = is_array($data) ? $data : [];
            }
            return $this->manufacturingCatalog;
        }
        return [];
    }

    /**
     * @return array<string,mixed>
     */
    private function moduleCatalog(string $suiteKey, string $moduleName): array
    {
        $catalog = $this->suiteCatalog($suiteKey);
        $modules = is_array($catalog['modules'] ?? null) ? $catalog['modules'] : [];
        return is_array($modules[$moduleName] ?? null) ? $modules[$moduleName] : [];
    }

    /**
     * @param array<int,array<string,mixed>> $entries
     * @return array<int,array<string,mixed>>
     */
    private function normalizeEntries(array $entries): array
    {
        return array_values(array_map(function (array $entry): array {
            return [
                'version' => (string)($entry['version'] ?? '0.0.0'),
                'release_date' => (string)($entry['release_date'] ?? ''),
                'scope' => (string)($entry['scope'] ?? ''),
                'summary' => (string)($entry['summary'] ?? 'No changelog summary recorded yet.'),
                'breaking_changes' => array_values(array_map('strval', (array)($entry['breaking_changes'] ?? []))),
                'migration_notes' => array_values(array_map('strval', (array)($entry['migration_notes'] ?? []))),
                'setup_upgrade_notes' => array_values(array_map('strval', (array)($entry['setup_upgrade_notes'] ?? []))),
            ];
        }, $entries));
    }

    /**
     * @param array<int,array<string,mixed>> $entries
     * @return array<string,mixed>|null
     */
    private function entryForVersion(array $entries, string $version): ?array
    {
        foreach ($entries as $entry) {
            if ((string)($entry['version'] ?? '') === $version) {
                return $entry;
            }
        }
        return null;
    }

    /**
     * @return array<string,mixed>
     */
    private function emptyEntry(string $version): array
    {
        return [
            'version' => $version,
            'release_date' => '',
            'scope' => '',
            'summary' => 'No changelog summary recorded yet.',
            'breaking_changes' => [],
            'migration_notes' => [],
            'setup_upgrade_notes' => [],
        ];
    }

    private function pluginManager(): PluginManager
    {
        static $manager = null;
        if ($manager instanceof PluginManager) {
            return $manager;
        }
        $manager = new PluginManager(
            APP_ROOT . '/plugins',
            new Container(),
            new Router(),
            new View(APP_ROOT . '/public/views'),
            new EventBus()
        );
        return $manager;
    }
}

<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Core\EventBus;
use App\Core\PackageManager;
use App\Core\PluginManager;
use App\Core\Router;
use App\Core\View;
use ZipArchive;

final class SuiteExportService
{
    private const TYPE_METADATA_ONLY = 'metadata_only';
    private const TYPE_PACKAGE_ONLY = 'package_only';
    private const TYPE_DATA_ONLY = 'data_only';
    private const TYPE_PACKAGE_DATA = 'package_data';
    private const TYPE_FULL_SNAPSHOT = 'full_snapshot';

    private ExportHistoryService $history;
    private PluginManager $plugins;

    public function __construct(?ExportHistoryService $history = null, ?PluginManager $plugins = null)
    {
        $this->history = $history ?? new ExportHistoryService();
        $this->plugins = $plugins ?? self::pluginManager();
    }

    /**
     * @return array<string,string>
     */
    public function exportTypes(): array
    {
        return [
            self::TYPE_METADATA_ONLY => 'Metadata only',
            self::TYPE_PACKAGE_ONLY => 'Package only',
            self::TYPE_DATA_ONLY => 'Data only',
            self::TYPE_PACKAGE_DATA => 'Package + data',
            self::TYPE_FULL_SNAPSHOT => 'Full backup snapshot',
        ];
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function suiteCards(): array
    {
        $cards = [];
        foreach (['sbaio', 'manufacturing'] as $suiteKey) {
            $cards[$suiteKey] = $this->suiteCard($suiteKey);
        }
        return $cards;
    }

    /**
     * @return array<string,mixed>
     */
    public function suiteCard(string $suiteKey): array
    {
        $suite = $this->suiteDefinition($suiteKey);
        return [
            'suite_key' => $suiteKey,
            'label' => (string)($suite['label'] ?? ucfirst($suiteKey)),
            'app_key' => $suiteKey,
            'modules' => $suite['modules'],
            'history' => $this->history->recentRuns($suiteKey, 'suite', 12),
            'export_types' => $this->exportTypes(),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function moduleCards(string $suiteKey): array
    {
        $suite = $this->suiteDefinition($suiteKey);
        $cards = [];
        foreach ((array)$suite['modules'] as $module) {
            $cards[] = [
                'module_name' => (string)($module['name'] ?? ''),
                'display_name' => (string)($module['display_name'] ?? $module['name'] ?? ''),
                'required_tables' => (array)($module['required_tables'] ?? []),
                'status' => (string)($module['status'] ?? 'missing'),
                'history' => array_values(array_filter(
                    $this->history->recentRuns($suiteKey, 'module', 50),
                    static fn(array $row): bool => (string)($row['target_key'] ?? '') === (string)($module['name'] ?? '')
                )),
                'export_types' => $this->exportTypes(),
            ];
        }
        return $cards;
    }

    /**
     * @return array<string,mixed>
     */
    public function exportSuite(string $suiteKey, string $exportType): array
    {
        $suite = $this->suiteDefinition($suiteKey);
        $exportType = $this->normalizeExportType($exportType);
        try {
            $result = $this->buildExport('suite', $suiteKey, $suiteKey, $exportType, (string)($suite['label'] ?? $suiteKey), (array)$suite['modules']);
            $this->history->recordSuccess('suite', $suiteKey, $suiteKey, $exportType, basename($result['file_path']), (string)$result['file_path'], $result, (string)($result['warning_text'] ?? ''));
            return $result;
        } catch (\Throwable $e) {
            $this->history->recordFailure('suite', $suiteKey, $suiteKey, $exportType, $e->getMessage());
            throw $e;
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function exportModule(string $suiteKey, string $moduleName, string $exportType): array
    {
        $suite = $this->suiteDefinition($suiteKey);
        $moduleName = trim($moduleName);
        $exportType = $this->normalizeExportType($exportType);
        $module = null;
        foreach ((array)$suite['modules'] as $candidate) {
            if ((string)($candidate['name'] ?? '') === $moduleName) {
                $module = $candidate;
                break;
            }
        }
        if (!is_array($module)) {
            throw new \RuntimeException('Unknown module for suite export: ' . $moduleName);
        }

        try {
            $result = $this->buildExport('module', $moduleName, $suiteKey, $exportType, (string)($module['display_name'] ?? $moduleName), [$module]);
            $this->history->recordSuccess('module', $moduleName, $suiteKey, $exportType, basename($result['file_path']), (string)$result['file_path'], $result, (string)($result['warning_text'] ?? ''));
            return $result;
        } catch (\Throwable $e) {
            $this->history->recordFailure('module', $moduleName, $suiteKey, $exportType, $e->getMessage());
            throw $e;
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function buildExport(string $targetType, string $targetKey, string $suiteKey, string $exportType, string $label, array $moduleRows): array
    {
        $exportDir = APP_ROOT . '/storage/exports';
        if (!is_dir($exportDir) && !mkdir($exportDir, 0775, true)) {
            throw new \RuntimeException('Unable to create exports directory.');
        }

        $timestamp = date('Ymd_His');
        $baseName = strtolower($suiteKey . '_' . preg_replace('/[^A-Za-z0-9_]+/', '_', $targetKey) . '_' . $exportType . '_' . $timestamp);
        $metadata = $this->metadataPayload($targetType, $targetKey, $suiteKey, $label, $moduleRows, $exportType);
        $dataPayload = $this->dataPayload($moduleRows);
        $warningText = $dataPayload['warning_text'];
        unset($dataPayload['warning_text']);

        if ($exportType === self::TYPE_METADATA_ONLY) {
            $filePath = $exportDir . '/' . $baseName . '.json';
            file_put_contents($filePath, json_encode(['metadata' => $metadata], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return [
                'target_type' => $targetType,
                'target_key' => $targetKey,
                'suite_key' => $suiteKey,
                'export_type' => $exportType,
                'file_path' => $filePath,
                'warning_text' => $warningText,
                'module_count' => count($moduleRows),
            ];
        }

        if ($exportType === self::TYPE_DATA_ONLY) {
            $filePath = $exportDir . '/' . $baseName . '_data.json';
            file_put_contents($filePath, json_encode(['metadata' => $metadata, 'data' => $dataPayload], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return [
                'target_type' => $targetType,
                'target_key' => $targetKey,
                'suite_key' => $suiteKey,
                'export_type' => $exportType,
                'file_path' => $filePath,
                'warning_text' => $warningText,
                'module_count' => count($moduleRows),
                'table_count' => count((array)($dataPayload['tables'] ?? [])),
            ];
        }

        $packagePaths = [];
        if (in_array($exportType, [self::TYPE_PACKAGE_ONLY, self::TYPE_PACKAGE_DATA, self::TYPE_FULL_SNAPSHOT], true)) {
            $packagePaths = $this->packagePayload($targetType, $targetKey, $suiteKey, $moduleRows);
        }

        $zipPath = $exportDir . '/' . $baseName . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to create export archive.');
        }

        $zip->addFromString('metadata.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        if (in_array($exportType, [self::TYPE_PACKAGE_DATA, self::TYPE_FULL_SNAPSHOT], true)) {
            $zip->addFromString('data.json', json_encode($dataPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
        if ($exportType === self::TYPE_FULL_SNAPSHOT) {
            $zip->addFromString('snapshot_manifest.json', json_encode([
                'created_at' => date('c'),
                'suite_key' => $suiteKey,
                'target_type' => $targetType,
                'target_key' => $targetKey,
                'export_type' => $exportType,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        foreach ($packagePaths as $localName => $path) {
            if (is_file($path)) {
                $zip->addFile($path, $localName);
            }
        }
        $zip->close();

        return [
            'target_type' => $targetType,
            'target_key' => $targetKey,
            'suite_key' => $suiteKey,
            'export_type' => $exportType,
            'file_path' => $zipPath,
            'warning_text' => $warningText,
            'module_count' => count($moduleRows),
            'table_count' => count((array)($dataPayload['tables'] ?? [])),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function suiteDefinition(string $suiteKey): array
    {
        $suiteKey = strtolower(trim($suiteKey));
        $appPath = APP_ROOT . '/apps/' . ($suiteKey === 'sbaio' ? 'SBAIO' : 'Manufacturing');
        $manifest = AppManifestService::loadFromFile($appPath . '/manifest.json');
        $moduleNames = array_values(array_map('strval', (array)($manifest['legacy_bridge_plugins'] ?? [])));
        $pluginManifests = $this->plugins->manifests();
        $moduleRows = [];
        foreach ($moduleNames as $moduleName) {
            $pluginManifest = $pluginManifests[$moduleName] ?? [];
            $moduleRows[] = [
                'name' => $moduleName,
                'display_name' => (string)($pluginManifest['display_name'] ?? $moduleName),
                'required_tables' => array_values(array_map('strval', (array)($pluginManifest['required_tables'] ?? []))),
                'status' => $this->plugins->status($moduleName) ?? 'missing',
            ];
        }
        return [
            'label' => (string)($manifest['name'] ?? ucfirst($suiteKey)),
            'manifest' => $manifest,
            'modules' => $moduleRows,
        ];
    }

    private function normalizeExportType(string $exportType): string
    {
        $exportType = trim($exportType);
        if (!isset($this->exportTypes()[$exportType])) {
            throw new \RuntimeException('Unsupported export type.');
        }
        return $exportType;
    }

    /**
     * @return array<string,mixed>
     */
    private function metadataPayload(string $targetType, string $targetKey, string $suiteKey, string $label, array $moduleRows, string $exportType): array
    {
        return [
            'target_type' => $targetType,
            'target_key' => $targetKey,
            'suite_key' => $suiteKey,
            'label' => $label,
            'export_type' => $exportType,
            'created_at' => date('c'),
            'modules' => array_map(static fn(array $module): array => [
                'name' => (string)($module['name'] ?? ''),
                'display_name' => (string)($module['display_name'] ?? ''),
                'required_tables' => array_values(array_map('strval', (array)($module['required_tables'] ?? []))),
                'status' => (string)($module['status'] ?? ''),
            ], $moduleRows),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function dataPayload(array $moduleRows): array
    {
        $tables = [];
        $warnings = [];
        $seen = [];
        foreach ($moduleRows as $module) {
            foreach ((array)($module['required_tables'] ?? []) as $table) {
                $table = trim((string)$table);
                if ($table === '' || isset($seen[$table])) {
                    continue;
                }
                $seen[$table] = true;
                try {
                    $tables[$table] = \App\Core\DB::fetchAll('SELECT * FROM `' . str_replace('`', '``', $table) . '`');
                } catch (\Throwable $e) {
                    $tables[$table] = [];
                    $warnings[] = 'Skipped missing or unreadable table: ' . $table;
                }
            }
        }

        return [
            'tables' => $tables,
            'warning_text' => implode(' ', $warnings),
        ];
    }

    /**
     * @return array<string,string>
     */
    private function packagePayload(string $targetType, string $targetKey, string $suiteKey, array $moduleRows): array
    {
        $paths = [];
        if ($targetType === 'suite') {
            $paths['package/' . $suiteKey . '.zip'] = (new AppExportService())->exportToZip($suiteKey);
            return $paths;
        }

        $moduleName = $targetKey;
        $paths['package/' . $moduleName . '.zip'] = PackageManager::exportPluginZip($moduleName);
        return $paths;
    }

    private static function pluginManager(): PluginManager
    {
        return new PluginManager(
            APP_ROOT . '/plugins',
            new Container(),
            new Router(),
            new View(APP_ROOT . '/public/views'),
            new EventBus()
        );
    }
}

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

final class ReleasePackagingService
{
    private DeploymentReadinessService $readiness;

    public function __construct(?DeploymentReadinessService $readiness = null)
    {
        $this->readiness = $readiness ?? new DeploymentReadinessService();
    }

    /**
     * @param array<string,mixed> $previewPayload
     * @return array<string,mixed>
     */
    public function generateFromPreview(array $previewPayload): array
    {
        $preview = (array)($previewPayload['preview'] ?? []);
        if (empty($preview['ready'])) {
            throw new \RuntimeException('Release is not deployment-ready. Resolve blocking issues before packaging.');
        }

        $scopeKey = trim((string)($preview['scope_key'] ?? 'core_only'));
        $version = trim((string)($previewPayload['release_version'] ?? ''));
        $notes = trim((string)($previewPayload['notes'] ?? ''));
        $included = (array)($preview['included_components'] ?? ['suites' => [], 'modules' => []]);
        $includedSuites = array_values(array_map('strval', (array)($included['suites'] ?? [])));
        $includedModules = array_values(array_map('strval', (array)($included['modules'] ?? [])));

        $dir = APP_ROOT . '/storage/releases';
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
            throw new \RuntimeException('Unable to create release package directory.');
        }

        $baseName = 'release_' . $scopeKey . '_v' . preg_replace('/[^A-Za-z0-9._-]+/', '_', $version) . '_' . date('Ymd_His');
        $zipPath = $dir . '/' . $baseName . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to create release archive.');
        }

        $metadata = [
            'version' => $version,
            'scope_key' => $scopeKey,
            'scope_label' => $this->readiness->scopeOptions()[$scopeKey] ?? $scopeKey,
            'generated_at' => date('c'),
            'generated_by' => (string)(\App\Core\Auth::user()['email'] ?? 'system'),
            'included_suites' => $includedSuites,
            'included_modules' => $includedModules,
            'warnings' => array_values((array)($preview['non_blocking_warnings'] ?? [])),
            'errors' => array_values((array)($preview['blocking_issues'] ?? [])),
            'notes' => $notes,
            'readiness_summary' => (array)($preview['summary'] ?? []),
            'version_context' => (array)($preview['version_context'] ?? []),
        ];
        $zip->addFromString('release_metadata.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->addCorePayload($zip, $scopeKey);
        $this->addSuitesPayload($zip, $includedSuites);
        $this->addModulesPayload($zip, $scopeKey, $includedModules);

        $zip->close();

        return [
            'package_name' => basename($zipPath),
            'package_path' => $zipPath,
            'metadata' => $metadata,
            'warning_text' => implode(' ', (array)($preview['non_blocking_warnings'] ?? [])),
        ];
    }

    private function addCorePayload(ZipArchive $zip, string $scopeKey): void
    {
        if (!in_array($scopeKey, ['core_only', 'core_selected_suites'], true)) {
            return;
        }

        $this->zipDirectory($zip, APP_ROOT . '/app', 'core/app');
        $this->zipDirectory($zip, APP_ROOT . '/public', 'core/public');
        foreach (['Base', 'ACL'] as $pluginName) {
            $path = APP_ROOT . '/plugins/' . $pluginName;
            if (is_dir($path)) {
                $this->zipDirectory($zip, $path, 'core/plugins/' . $pluginName);
            }
        }
    }

    /**
     * @param array<int,string> $suiteKeys
     */
    private function addSuitesPayload(ZipArchive $zip, array $suiteKeys): void
    {
        foreach ($suiteKeys as $suiteKey) {
            $appPath = $this->resolveAppDirectory($suiteKey);
            if ($appPath !== '' && is_dir($appPath)) {
                $this->zipDirectory($zip, $appPath, 'suites/' . basename($appPath));
            }
        }
    }

    /**
     * @param array<int,string> $moduleNames
     */
    private function addModulesPayload(ZipArchive $zip, string $scopeKey, array $moduleNames): void
    {
        if ($scopeKey !== 'module_only') {
            return;
        }

        foreach ($moduleNames as $moduleName) {
            $tempZip = PackageManager::exportPluginZip($moduleName);
            if (is_file($tempZip)) {
                $zip->addFile($tempZip, 'modules/' . basename($tempZip));
            }
        }
    }

    private function zipDirectory(ZipArchive $zip, string $source, string $prefix): void
    {
        $items = scandir($source) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            if ($item === '.DS_Store') {
                continue;
            }

            $full = $source . '/' . $item;
            $local = $prefix . '/' . $item;
            if (is_dir($full)) {
                $zip->addEmptyDir($local);
                $this->zipDirectory($zip, $full, $local);
                continue;
            }
            $zip->addFile($full, $local);
        }
    }

    private function resolveAppDirectory(string $appKey): string
    {
        $base = APP_ROOT . '/apps';
        $direct = $base . '/' . $appKey;
        if (is_dir($direct)) {
            return $direct;
        }
        foreach (scandir($base) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            if (strcasecmp($item, $appKey) === 0 && is_dir($base . '/' . $item)) {
                return $base . '/' . $item;
            }
        }
        return '';
    }
}

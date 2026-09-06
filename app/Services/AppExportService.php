<?php
declare(strict_types=1);

namespace App\Services;

final class AppExportService
{
    public function exportToZip(string $appKey): string
    {
        $appKey = trim($appKey);
        if ($appKey === '') {
            throw new \RuntimeException('Missing app key for export');
        }

        $row = \App\Core\DB::fetchOne('SELECT status FROM core_apps WHERE app_key=? LIMIT 1', [$appKey]);
        if (!$row) {
            throw new \RuntimeException('App is not registered in core_apps');
        }

        $status = (string)($row['status'] ?? '');
        if (!in_array($status, ['installed', 'enabled', 'disabled', 'upgrade_pending', 'broken'], true)) {
            throw new \RuntimeException('App must be installed before export');
        }

        $sourceDir = $this->resolveAppDirectory($appKey);
        if (!is_dir($sourceDir)) {
            throw new \RuntimeException('App directory not found: ' . $appKey);
        }

        $manifest = AppManifestService::loadFromFile($sourceDir . '/manifest.json');
        if (!(bool)($manifest['can_export'] ?? false)) {
            throw new \RuntimeException('App does not allow export');
        }

        $target = dirname(__DIR__, 2) . '/packages/exports/' . $appKey . '-' . (string)$manifest['version'] . '.zip';

        $zip = new \ZipArchive();
        if ($zip->open($target, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to create export ZIP');
        }

        $this->zipDirectory($zip, $sourceDir, basename($sourceDir));
        $zip->close();

        AppPlatformLogger::lifecycle('exported', $appKey, ['zip' => $target]);
        return $target;
    }

    private function zipDirectory(\ZipArchive $zip, string $source, string $prefix): void
    {
        $items = scandir($source) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
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
        $base = dirname(__DIR__, 2) . '/apps';
        $direct = $base . '/' . $appKey;
        if (is_dir($direct)) {
            return $direct;
        }

        $items = scandir($base) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            if (strcasecmp($item, $appKey) === 0 && is_dir($base . '/' . $item)) {
                return $base . '/' . $item;
            }
        }

        return $direct;
    }
}

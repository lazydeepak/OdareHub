<?php
declare(strict_types=1);

namespace App\Services;

final class AppPackageService
{
    private string $packagesRoot;
    private string $uploadedDir;
    private string $tempDir;

    public function __construct(?string $packagesRoot = null)
    {
        $this->packagesRoot = $packagesRoot ?: dirname(__DIR__, 2) . '/packages';
        $this->uploadedDir = $this->packagesRoot . '/uploaded';
        $this->tempDir = $this->packagesRoot . '/temp';
    }

    public function upload(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('ZIP upload failed');
        }

        $name = (string)($file['name'] ?? '');
        if (!preg_match('/\.zip$/i', $name)) {
            throw new \RuntimeException('Only ZIP files are supported');
        }

        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name) ?: ('app_' . time() . '.zip');
        $target = $this->uploadedDir . '/' . date('Ymd_His') . '_' . $safeName;

        if (!@move_uploaded_file((string)$file['tmp_name'], $target)) {
            throw new \RuntimeException('Failed to save uploaded ZIP');
        }

        return [
            'zip_path' => $target,
            'checksum' => hash_file('sha256', $target) ?: '',
        ];
    }

    public function extractAndValidate(string $zipPath): array
    {
        $extractDir = $this->tempDir . '/pkg_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
        if (!is_dir($extractDir) && !mkdir($extractDir, 0775, true) && !is_dir($extractDir)) {
            throw new \RuntimeException('Unable to prepare temp extraction directory');
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('Unable to open ZIP package');
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = (string)$zip->getNameIndex($i);
            if ($entry === '' || str_starts_with($entry, '/') || str_contains($entry, '..')) {
                $zip->close();
                throw new \RuntimeException('Invalid ZIP structure: unsafe archive path detected');
            }
        }

        if (!$zip->extractTo($extractDir)) {
            $zip->close();
            throw new \RuntimeException('Unable to extract ZIP package');
        }
        $zip->close();

        $manifestPath = $this->findManifestPath($extractDir);
        $manifest = AppManifestService::loadFromFile($manifestPath);
        $appRoot = dirname($manifestPath);
        $packageType = trim((string)($manifest['package_type'] ?? 'bundle'));
        $appKey = trim((string)($manifest['app_key'] ?? $manifest['id'] ?? ''));

        if ($packageType !== 'bundle') {
            throw new \RuntimeException('App package manifest must declare package_type=bundle');
        }
        if ($appKey === '' || $appKey !== (string)$manifest['id']) {
            throw new \RuntimeException('App package app_key must match manifest id');
        }

        $entryPath = $appRoot . '/' . ltrim((string)$manifest['entry'], '/');
        if (!is_file($entryPath)) {
            throw new \RuntimeException('manifest entry file not found in package: ' . (string)$manifest['entry']);
        }

        $migrationsPath = trim((string)$manifest['migrations_path']);
        if ($migrationsPath !== '') {
            $migrationDir = $appRoot . '/' . ltrim($migrationsPath, '/');
            if (!is_dir($migrationDir)) {
                throw new \RuntimeException('manifest migrations_path does not exist: ' . $migrationsPath);
            }
        }

        return [
            'extract_dir' => $appRoot,
            'manifest_path' => $manifestPath,
            'manifest' => $manifest,
        ];
    }

    public function moveInstalledTree(string $sourceDir, string $appId): string
    {
        $appsRoot = dirname(__DIR__, 2) . '/apps';
        $targetDir = $appsRoot . '/' . $this->resolveBundleDirectoryName($sourceDir, $appId);
        $realSource = realpath($sourceDir) ?: '';
        $realTarget = realpath($targetDir) ?: '';

        // Locally discovered bundles may already live at their canonical install path.
        // Treat that as an in-place install instead of deleting and re-copying the same tree.
        if ($realSource !== '' && $realTarget !== '' && $realSource === $realTarget) {
            return $targetDir;
        }

        if (is_dir($targetDir)) {
            $this->rrmdir($targetDir);
        }

        $this->copyTree($sourceDir, $targetDir);
        return $targetDir;
    }

    private function resolveBundleDirectoryName(string $sourceDir, string $appId): string
    {
        $manifestFile = rtrim($sourceDir, '/') . '/manifest.json';
        if (is_file($manifestFile)) {
            $json = json_decode((string)file_get_contents($manifestFile), true);
            if (is_array($json)) {
                $dirName = trim((string)($json['directory_name'] ?? $json['bundle_dir'] ?? ''));
                if ($dirName !== '' && preg_match('/^[A-Za-z][A-Za-z0-9_.-]*$/', $dirName)) {
                    return $dirName;
                }
            }
        }

        $base = basename(rtrim($sourceDir, '/'));
        if ($base !== '' && !preg_match('/^pkg_\d{8}_\d{6}_[a-f0-9]+$/', $base)) {
            return $base;
        }

        return $appId;
    }

    private function findManifestPath(string $extractDir): string
    {
        $direct = $extractDir . '/manifest.json';
        if (is_file($direct)) {
            return $direct;
        }

        $children = glob($extractDir . '/*', GLOB_ONLYDIR) ?: [];
        foreach ($children as $child) {
            $candidate = $child . '/manifest.json';
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException('manifest.json not found in ZIP package');
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->rrmdir($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }

    private function copyTree(string $from, string $to): void
    {
        if (!is_dir($to) && !mkdir($to, 0775, true) && !is_dir($to)) {
            throw new \RuntimeException('Unable to create app install directory');
        }

        $items = scandir($from) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $src = $from . '/' . $item;
            $dst = $to . '/' . $item;

            if (is_dir($src)) {
                $this->copyTree($src, $dst);
                continue;
            }

            if (!@copy($src, $dst)) {
                throw new \RuntimeException('Failed to copy app package file: ' . $item);
            }
        }
    }
}

<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\AppInstallService;
use ZipArchive;

final class PackageManager
{
    public static function packagesDir(): string
    {
        return APP_ROOT . '/storage/plugin_packages';
    }

    public static function backupsDir(): string
    {
        return APP_ROOT . '/storage/plugin_backups';
    }

    public static function tmpDir(): string
    {
        return APP_ROOT . '/storage/tmp_plugins';
    }

    public static function exportsDir(): string
    {
        return APP_ROOT . '/storage/plugin_exports';
    }

    public static function pluginsDir(): string
    {
        return APP_ROOT . '/plugins';
    }

    public static function canonicalModulePath(string $pluginName, ?string $ownerApp = null): string
    {
        $pluginName = trim($pluginName);
        $ownerApp = trim((string)$ownerApp);
        if ($pluginName === '') {
            return '';
        }

        if ($ownerApp !== '') {
            $candidate = APP_ROOT . '/apps/' . $ownerApp . '/modules/' . $pluginName;
            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        $appDirs = glob(APP_ROOT . '/apps/*/modules/' . $pluginName, GLOB_ONLYDIR) ?: [];
        return $appDirs !== [] ? (string)$appDirs[0] : '';
    }

    public static function listPackages(): array
    {
        $dir = self::packagesDir();
        if (!is_dir($dir)) return [];

        $files = glob($dir . '/*.zip') ?: [];
        rsort($files);

        $out = [];
        foreach ($files as $p) {
            $meta = self::readManifestFromZip($p);
            $out[] = [
                'file' => basename($p),
                'path' => $p,
                'meta' => $meta,
                'target_path' => $meta['ok'] ? self::targetPathForMeta($meta) : '',
                'mtime' => @filemtime($p) ?: 0,
                'size' => @filesize($p) ?: 0,
            ];
        }
        return $out;
    }

    public static function readManifestFromZip(string $zipPath): array
    {
        $z = new ZipArchive();
        if ($z->open($zipPath) !== true) {
            return ['ok' => false, 'error' => 'Cannot open zip'];
        }

        $pluginJsonIndex = null;
        $appManifestIndex = null;

        for ($i = 0; $i < $z->numFiles; $i++) {
            $name = $z->getNameIndex($i);
            if (!$name) continue;

            // basic safety even on read
            if (self::isBadZipEntry($name)) {
                $z->close();
                return ['ok' => false, 'error' => 'Zip contains unsafe paths'];
            }

            if ($appManifestIndex === null && preg_match('#(^|/)(manifest\.json)$#', $name)) {
                $appManifestIndex = $i;
            }

            if ($pluginJsonIndex === null && preg_match('#(^|/)(plugin\.json)$#', $name)) {
                $pluginJsonIndex = $i;
            }
        }

        if ($appManifestIndex !== null) {
            $raw = $z->getFromIndex($appManifestIndex);
            $z->close();
            return self::parseBundleManifest($raw);
        }

        if ($pluginJsonIndex === null) {
            $z->close();
            return ['ok' => false, 'error' => 'manifest.json or plugin.json not found in zip'];
        }

        $raw = $z->getFromIndex($pluginJsonIndex);
        $z->close();

        return self::parseModuleManifest($raw);
    }

    public static function targetPathForMeta(array $meta): string
    {
        if (!(bool)($meta['ok'] ?? false)) {
            return '';
        }

        $packageType = (string)($meta['package_type'] ?? 'module');
        if ($packageType === 'bundle') {
            $appKey = trim((string)($meta['app_key'] ?? $meta['name'] ?? ''));
            return $appKey === '' ? '' : (APP_ROOT . '/apps/' . $appKey);
        }

        $ownerApp = trim((string)($meta['owner_app'] ?? ''));
        $moduleName = trim((string)($meta['name'] ?? ''));
        if ($ownerApp !== '' && $moduleName !== '') {
            return APP_ROOT . '/apps/' . $ownerApp . '/modules/' . $moduleName;
        }

        return $moduleName === '' ? '' : (APP_ROOT . '/plugins/' . $moduleName);
    }

    public static function uploadPackage(array $file, ?string $expectedType = null): string
    {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new \RuntimeException('No upload received');
        }

        $maxBytes = 50 * 1024 * 1024;
        if (($file['size'] ?? 0) > $maxBytes) {
            throw new \RuntimeException('Zip too large (max 50MB)');
        }

        $dir = self::packagesDir();
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new \RuntimeException('Cannot create packages directory');
        }

        $original = (string)($file['name'] ?? 'package.zip');
        $original = preg_replace('/[^A-Za-z0-9._-]/', '_', $original);
        if (!str_ends_with(strtolower($original), '.zip')) {
            $original .= '.zip';
        }

        $ts = date('Ymd_His');
        $dest = $dir . '/' . $ts . '__' . $original;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new \RuntimeException('Failed to save uploaded zip');
        }

        $meta = self::readManifestFromZip($dest);
        if (!$meta['ok']) {
            @unlink($dest);
            throw new \RuntimeException('Invalid package zip: ' . $meta['error']);
        }

        $expected = trim((string)$expectedType);
        if ($expected !== '' && $expected !== (string)($meta['package_type'] ?? '')) {
            @unlink($dest);
            throw new \RuntimeException('Uploaded package type mismatch. Expected ' . $expected . ', detected ' . (string)($meta['package_type'] ?? 'unknown'));
        }

        return basename($dest);
    }

    /**
     * Apply package zip according to detected package type.
     * - bundle: register/install via AppInstallService into /apps/<Bundle>
     * - module: install into /apps/<Owner>/modules/<Module> when owner_app is present
     *   otherwise fall back to legacy /plugins/<Name>
     */
    public static function applyPackage(string $zipFile, string $mode): void
    {
        $zipPath = is_file($zipFile) ? $zipFile : (self::packagesDir() . '/' . basename($zipFile));
        if (!is_file($zipPath)) {
            throw new \RuntimeException('Package not found');
        }

        $meta = self::readManifestFromZip($zipPath);
        if (!$meta['ok']) {
            throw new \RuntimeException('Bad package: ' . $meta['error']);
        }

        if ((string)($meta['package_type'] ?? 'module') === 'bundle') {
            self::applyBundlePackage($zipPath, $mode);
            return;
        }

        self::applyModulePackage($zipPath, $mode, $meta);
    }

    private static function parseBundleManifest($raw): array
    {
        if (!is_string($raw) || $raw === '') {
            return ['ok' => false, 'error' => 'manifest.json unreadable'];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return ['ok' => false, 'error' => 'manifest.json invalid JSON'];
        }

        $name = trim((string)($data['name'] ?? ''));
        $appKey = trim((string)($data['app_key'] ?? $data['id'] ?? ''));
        $version = (string)($data['version'] ?? '0.0.0');
        $requires = $data['dependencies'] ?? [];
        $packageType = trim((string)($data['package_type'] ?? 'bundle'));

        if ($packageType !== 'bundle') {
            return ['ok' => false, 'error' => 'manifest.json package_type must be bundle'];
        }
        if ($appKey === '' || !preg_match('/^[a-z0-9][a-z0-9_\\-.]*$/', $appKey)) {
            return ['ok' => false, 'error' => 'Invalid app_key in manifest.json'];
        }
        if ($name === '') {
            $name = $appKey;
        }
        if ($version === '') {
            $version = '0.0.0';
        }
        if (!is_array($requires)) {
            $requires = [];
        }

        return [
            'ok' => true,
            'package_type' => 'bundle',
            'name' => $name,
            'app_key' => $appKey,
            'version' => $version,
            'requires' => $requires,
            'owner_app' => $appKey,
            'module_key' => '',
        ];
    }

    private static function parseModuleManifest($raw): array
    {
        if (!is_string($raw) || $raw === '') {
            return ['ok' => false, 'error' => 'plugin.json unreadable'];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return ['ok' => false, 'error' => 'plugin.json invalid JSON'];
        }

        $name = (string)($data['name'] ?? '');
        $version = (string)($data['version'] ?? '0.0.0');
        $requires = $data['requires'] ?? [];
        $packageType = trim((string)($data['package_type'] ?? 'module'));
        $ownerApp = trim((string)($data['owner_app'] ?? $data['app_key'] ?? ''));
        $moduleKey = trim((string)($data['module_key'] ?? $name));

        if (!in_array($packageType, ['module', 'plugin'], true)) {
            return ['ok' => false, 'error' => 'plugin.json package_type must be module or plugin'];
        }
        if ($name === '' || !preg_match('/^[A-Za-z][A-Za-z0-9_]+$/', $name)) {
            return ['ok' => false, 'error' => 'Invalid plugin/module name in plugin.json'];
        }
        if ($version === '') {
            $version = '0.0.0';
        }
        if (!is_array($requires)) {
            $requires = [];
        }

        if ($ownerApp === '' && strtolower((string)($data['suite'] ?? '')) === 'manufacturing') {
            $ownerApp = 'manufacturing';
        }

        return [
            'ok' => true,
            'package_type' => $packageType === 'plugin' ? 'module' : $packageType,
            'legacy_package_type' => $packageType,
            'name' => $name,
            'module_key' => $moduleKey !== '' ? $moduleKey : $name,
            'owner_app' => $ownerApp,
            'app_key' => $ownerApp,
            'version' => $version,
            'requires' => $requires,
        ];
    }

    private static function applyBundlePackage(string $zipPath, string $mode): void
    {
        if (!in_array($mode, ['install', 'repair', 'upgrade'], true)) {
            throw new \RuntimeException('Invalid apply mode');
        }

        $installer = new AppInstallService();
        $result = $installer->registerPackageZip($zipPath);
        $appKey = (string)($result['manifest']['id'] ?? '');
        if ($appKey === '') {
            throw new \RuntimeException('Bundle package missing app identifier');
        }

        $installer->install($appKey);
    }

    private static function applyModulePackage(string $zipPath, string $mode, array $meta): void
    {
        $name = (string)$meta['name'];
        $target = self::targetPathForMeta($meta);
        $exists = $target !== '' && is_dir($target);

        if ($target === '') {
            throw new \RuntimeException('Module package target could not be resolved');
        }

        $ownerApp = trim((string)($meta['owner_app'] ?? ''));
        if ($ownerApp !== '') {
            $ownerAppDir = APP_ROOT . '/apps/' . $ownerApp;
            if (!is_dir($ownerAppDir)) {
                throw new \RuntimeException('Owner app not found for module package: ' . $ownerApp);
            }
            $modulesDir = dirname($target);
            if (!is_dir($modulesDir) && !mkdir($modulesDir, 0755, true) && !is_dir($modulesDir)) {
                throw new \RuntimeException('Cannot create owner app modules directory');
            }
        }

        if ($mode === 'install' && $exists) {
            throw new \RuntimeException("Module folder already exists: {$name}");
        }
        if (($mode === 'repair' || $mode === 'upgrade') && !$exists) {
            throw new \RuntimeException("Module not present for {$mode}: {$name}");
        }
        if (!in_array($mode, ['install','repair','upgrade'], true)) {
            throw new \RuntimeException('Invalid apply mode');
        }

        // Extract zip into a temp folder safely
        $tmpBase = self::tmpDir();
        if (!is_dir($tmpBase) && !mkdir($tmpBase, 0755, true)) {
            throw new \RuntimeException('Cannot create tmp directory');
        }

        $tmp = $tmpBase . '/' . $name . '__' . bin2hex(random_bytes(6));
        if (!mkdir($tmp, 0755, true)) {
            throw new \RuntimeException('Cannot create temp workspace');
        }

        self::safeExtractZip($zipPath, $tmp);

        $extractedPluginDir = $tmp . '/' . $name;
        if (!is_dir($extractedPluginDir)) {
            if (!is_file($tmp . '/plugin.json')) {
                self::rrmdir($tmp);
                throw new \RuntimeException("Zip must contain folder '{$name}' or root plugin.json");
            }
            $extractedPluginDir = $tmp;
        }

        if (!is_file($extractedPluginDir . '/plugin.json')) {
            self::rrmdir($tmp);
            throw new \RuntimeException("Extracted module missing plugin.json");
        }

        if ($exists) {
            $backupZip = self::backupsDir() . '/' . $name . '__' . date('Ymd_His') . '.zip';
            self::zipDirectory($target, $backupZip);
        }

        if ($exists) {
            $old = dirname($target) . '/__old__' . $name . '__' . bin2hex(random_bytes(4));
            if (!rename($target, $old)) {
                self::rrmdir($tmp);
                throw new \RuntimeException('Failed to rename old module folder (permissions?)');
            }

            if (!rename($extractedPluginDir, $target)) {
                @rename($old, $target);
                self::rrmdir($tmp);
                throw new \RuntimeException('Failed to move new module folder into place');
            }

            self::rrmdir($old);

        } else {
            if (!rename($extractedPluginDir, $target)) {
                self::rrmdir($tmp);
                throw new \RuntimeException('Failed to move module folder into place');
            }
        }

        self::rrmdir($tmp);

        if ($ownerApp !== '') {
            self::ensureLegacyPluginSymlink($name, $target);
        }
    }

    public static function exportPluginZip(string $pluginName): string
    {
        $pluginName = trim($pluginName);
        if ($pluginName === '' || !preg_match('/^[A-Za-z][A-Za-z0-9_]+$/', $pluginName)) {
            throw new \RuntimeException('Invalid plugin name for export');
        }

        $source = self::pluginSourcePath($pluginName);
        if (!is_dir($source)) {
            throw new \RuntimeException('Plugin directory not found: ' . $pluginName);
        }

        $pluginJsonPath = $source . '/plugin.json';
        if (!is_file($pluginJsonPath)) {
            throw new \RuntimeException('plugin.json missing for plugin export: ' . $pluginName);
        }

        $json = json_decode((string)file_get_contents($pluginJsonPath), true);
        if (!is_array($json)) {
            throw new \RuntimeException('Invalid plugin.json for plugin export: ' . $pluginName);
        }

        $manifestName = trim((string)($json['name'] ?? ''));
        if ($manifestName === '' || !preg_match('/^[A-Za-z][A-Za-z0-9_]+$/', $manifestName)) {
            throw new \RuntimeException('Invalid plugin name in plugin.json for export: ' . $pluginName);
        }

        if (strcasecmp($manifestName, $pluginName) !== 0) {
            throw new \RuntimeException('Export blocked: folder/plugin mismatch (' . $pluginName . ' vs ' . $manifestName . ')');
        }

        $version = trim((string)($json['version'] ?? '0.0.0'));
        if ($version === '') {
            $version = '0.0.0';
        }

        $exportsDir = self::exportsDir();
        if (!is_dir($exportsDir) && !mkdir($exportsDir, 0755, true)) {
            throw new \RuntimeException('Cannot create export directory');
        }

        $ts = date('Ymd_His');
        $zipName = $pluginName . '-' . $version . '-' . $ts . '.zip';
        $zipPath = $exportsDir . '/' . $zipName;

        $overrides = [];
        if (preg_match('#/apps/([^/]+)/modules/([^/]+)$#', str_replace('\\', '/', $source), $matches)) {
            $ownerApp = (string)($matches[1] ?? '');
            if ($ownerApp !== '') {
                $json['package_type'] = 'module';
                $json['owner_app'] = $json['owner_app'] ?? $ownerApp;
                $json['module_key'] = $json['module_key'] ?? $pluginName;
                $overrides['plugin.json'] = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }
        }

        // Force package root folder to match plugin.json name for re-upload compatibility.
        self::zipDirectory($source, $zipPath, $pluginName, $overrides);
        return $zipPath;
    }

    public static function pluginSourcePath(string $pluginName): string
    {
        $pluginName = trim($pluginName);
        if ($pluginName === '') {
            return '';
        }

        $canonical = self::canonicalModulePath($pluginName);
        if ($canonical !== '') {
            return $canonical;
        }

        $legacy = self::pluginsDir() . '/' . $pluginName;
        if (is_dir($legacy)) {
            return $legacy;
        }

        return $legacy;
    }

    private static function safeExtractZip(string $zipPath, string $destDir): void
    {
        $z = new ZipArchive();
        if ($z->open($zipPath) !== true) {
            throw new \RuntimeException('Cannot open zip for extraction');
        }

        // simple caps (adjust as you like)
        $maxFiles = 5000;
        $maxTotal = 200 * 1024 * 1024; // 200MB extracted cap (rough)

        $total = 0;
        if ($z->numFiles > $maxFiles) {
            $z->close();
            throw new \RuntimeException('Zip contains too many files');
        }

        for ($i=0; $i<$z->numFiles; $i++) {
            $name = $z->getNameIndex($i);
            if (!$name) continue;

            if (self::isBadZipEntry($name)) {
                $z->close();
                throw new \RuntimeException('Zip contains unsafe paths');
            }

            // block .htaccess anywhere
            if (preg_match('#(^|/)\.htaccess$#i', $name)) {
                $z->close();
                throw new \RuntimeException('Zip contains forbidden file: .htaccess');
            }

            $stat = $z->statIndex($i);
            $sz = (int)($stat['size'] ?? 0);
            $total += $sz;
            if ($total > $maxTotal) {
                $z->close();
                throw new \RuntimeException('Zip too large when extracted');
            }
        }

        if (!$z->extractTo($destDir)) {
            $z->close();
            throw new \RuntimeException('Extraction failed');
        }

        $z->close();
    }

    private static function isBadZipEntry(string $name): bool
    {
        if (str_contains($name, "\0")) return true;
        if (str_starts_with($name, '/')) return true;
        if (preg_match('#^[A-Za-z]:[\\\\/]#', $name)) return true; // Windows drive
        if (str_contains($name, '../') || str_contains($name, '..\\')) return true;
        return false;
    }

    private static function zipDirectory(string $srcDir, string $zipPath, ?string $rootDirName = null, array $overrides = []): void
    {
        $srcDir = rtrim($srcDir, '/');
        $dirName = $rootDirName !== null ? trim($rootDirName) : basename($srcDir);
        if ($dirName === '' || str_contains($dirName, '/')) {
            throw new \RuntimeException('Invalid root directory name for zip');
        }

        $z = new ZipArchive();
        if ($z->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Cannot create backup zip');
        }

        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($srcDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iter as $file) {
            /** @var \SplFileInfo $file */
            $path = $file->getPathname();
            $localRel = ltrim(str_replace($srcDir, '', $path), '/');
            $rel = $dirName . '/' . $localRel;

            if ($file->isDir()) {
                $z->addEmptyDir($rel);
            } else {
                if (array_key_exists($localRel, $overrides)) {
                    $z->addFromString($rel, (string)$overrides[$localRel]);
                    continue;
                }
                $z->addFile($path, $rel);
            }
        }

        foreach ($overrides as $localRel => $contents) {
            $localRel = ltrim((string)$localRel, '/');
            if ($localRel === '') {
                continue;
            }
            $rel = $dirName . '/' . $localRel;
            if ($z->locateName($rel) === false) {
                $z->addFromString($rel, (string)$contents);
            }
        }

        $z->close();
    }

    private static function rrmdir(string $path): void
    {
        if (!file_exists($path)) return;

        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($it as $f) {
            if ($f->isDir()) @rmdir($f->getPathname());
            else @unlink($f->getPathname());
        }
        @rmdir($path);
    }

    private static function ensureLegacyPluginSymlink(string $pluginName, string $target): void
    {
        $link = self::pluginsDir() . '/' . $pluginName;
        if (is_link($link)) {
            @unlink($link);
        } elseif (is_dir($link) || file_exists($link)) {
            return;
        }

        $relative = self::relativePath(dirname($link), $target);
        @symlink($relative, $link);
    }

    private static function relativePath(string $fromDir, string $toPath): string
    {
        $from = explode('/', trim(str_replace('\\', '/', realpath($fromDir) ?: $fromDir), '/'));
        $to = explode('/', trim(str_replace('\\', '/', realpath($toPath) ?: $toPath), '/'));

        while ($from !== [] && $to !== [] && $from[0] === $to[0]) {
            array_shift($from);
            array_shift($to);
        }

        return str_repeat('../', count($from)) . implode('/', $to);
    }
}

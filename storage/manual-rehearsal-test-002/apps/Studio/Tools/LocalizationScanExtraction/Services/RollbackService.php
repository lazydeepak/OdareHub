<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\LocalizationScanExtraction\Services;

final class RollbackService
{
    public static function rollbackFromReport(array $snapshotPaths): array
    {
        $restored = [];
        $failed = [];

        foreach ($snapshotPaths as $label => $path) {
            if ($path === null) {
                continue;
            }
            if (!is_file($path)) {
                $failed[] = ['label' => $label, 'reason' => 'Snapshot file not found: ' . $path];
                continue;
            }

            $targetPath = self::inferTargetPath($path);
            if ($targetPath === null) {
                $failed[] = ['label' => $label, 'reason' => 'Cannot infer target path from snapshot: ' . $path];
                continue;
            }

            if (!self::isTargetAllowed($path, $targetPath)) {
                $failed[] = ['label' => $label, 'reason' => 'Target path is outside the snapshot owner boundary: ' . $targetPath];
                continue;
            }

            $targetDir = dirname($targetPath);
            if (!is_dir($targetDir)) {
                @mkdir($targetDir, 0755, true);
            }
            $restoredBytes = @copy($path, $targetPath);
            if ($restoredBytes === false) {
                $failed[] = ['label' => $label, 'reason' => 'Copy failed from ' . $path . ' to ' . $targetPath];
                continue;
            }

            $restored[] = ['label' => $label, 'snapshot' => $path, 'target' => $targetPath];
        }

        return [
            'restored' => $restored,
            'failed' => $failed,
            'ok' => $failed === [],
        ];
    }

    public static function inferTargetPath(string $snapshotPath): ?string
    {
        $manifestTarget = self::targetFromManifest($snapshotPath);
        if ($manifestTarget !== null) {
            return $manifestTarget;
        }

        $basename = basename($snapshotPath);

        // Strip the timestamp suffix to get {label}-{filename}
        // Timestamp format: -YYYYMMDD_HHMMSS_RAND.bak
        $tsPattern = '/^(.+)-[0-9]{8}_[0-9]{6}_[0-9]+\.bak$/';
        if (!preg_match($tsPattern, $basename, $m)) {
            return null;
        }
        $labelAndFile = $m[1]; // e.g. "add-missing-keys-en.php" or "extract-locale-en.php"

        // The filename is the segment after the LAST hyphen (labels may contain hyphens,
        // but filenames like "en.php" do not; for source files with hyphens in their name
        // we rely on known-label-prefix extraction below).
        $lastHyphen = strrpos($labelAndFile, '-');
        if ($lastHyphen === false) {
            return null;
        }
        $originalFile = substr($labelAndFile, $lastHyphen + 1);

        // Resolve the owner key from the snapshot directory structure.
        // Snapshots are stored in: storage/studio-snapshots/localization-scan-extraction/[owner-subpath]/...
        // For the top-level snapshots (legacy format), the owner subpath is empty.
        // For owner-subdirectoried snapshots (future), infer from the subpath.
        $snapshotDir = dirname($snapshotPath);
        $storagePrefix = APP_ROOT . '/storage/studio-snapshots/localization-scan-extraction';
        $pathUnderStorage = substr($snapshotDir, strlen($storagePrefix));
        $pathUnderStorage = ltrim($pathUnderStorage, '/');

        // Current snapshots store all files at the top level (no owner subdirs),
        // so $pathUnderStorage is empty. Use the snapshot label as the owner key
        // when no subdirectory is present, since owner-dependent snapshots will
        // use owner subdirectories in future.
        $ownerKey = $pathUnderStorage;

        $localePath = self::resolveOwnerLangPath($ownerKey, $originalFile);
        if ($localePath !== null) {
            return $localePath;
        }

        return null;
    }

    private static function targetFromManifest(string $snapshotPath): ?string
    {
        $manifestPath = $snapshotPath . '.json';
        if (!is_file($manifestPath)) {
            return null;
        }

        $raw = @file_get_contents($manifestPath);
        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }

        $target = (string)($data['target_path'] ?? '');
        if ($target === '') {
            $relative = (string)($data['target_relative_path'] ?? '');
            if ($relative !== '') {
                $target = APP_ROOT . '/' . ltrim($relative, '/');
            }
        }

        $normalized = self::normalizePath($target);
        if ($normalized === null || !str_starts_with($normalized, APP_ROOT . '/')) {
            return null;
        }

        return $normalized;
    }

    private static function isTargetAllowed(string $snapshotPath, string $targetPath): bool
    {
        $manifestPath = $snapshotPath . '.json';
        $ownerKey = null;
        if (is_file($manifestPath)) {
            $raw = @file_get_contents($manifestPath);
            $data = $raw !== false ? json_decode($raw, true) : null;
            if (is_array($data) && isset($data['owner_key'])) {
                $ownerKey = (string)$data['owner_key'];
            }
        }

        if ($ownerKey === null || $ownerKey === '') {
            $ownerKey = self::ownerKeyFromSnapshotPath($snapshotPath);
        }

        if ($ownerKey === null || $ownerKey === '') {
            return false;
        }

        $ownerRoot = self::resolveOwnerPath($ownerKey);
        $target = self::normalizePath($targetPath);
        if ($ownerRoot === null || $target === null) {
            return false;
        }

        $ownerRoot = rtrim(self::normalizePath($ownerRoot) ?? '', '/');
        return $ownerRoot !== '' && ($target === $ownerRoot || str_starts_with($target, $ownerRoot . '/'));
    }

    private static function ownerKeyFromSnapshotPath(string $snapshotPath): ?string
    {
        $storagePrefix = APP_ROOT . '/storage/studio-snapshots/localization-scan-extraction';
        $snapshotDir = dirname($snapshotPath);
        if (!str_starts_with($snapshotDir, $storagePrefix)) {
            return null;
        }

        $pathUnderStorage = ltrim(substr($snapshotDir, strlen($storagePrefix)), '/');
        return $pathUnderStorage !== '' ? $pathUnderStorage : null;
    }

    private static function normalizePath(string $path): ?string
    {
        if ($path === '') {
            return null;
        }

        $real = realpath($path);
        if ($real !== false) {
            return $real;
        }

        $parts = [];
        $isAbsolute = str_starts_with($path, '/');
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }

        return ($isAbsolute ? '/' : '') . implode('/', $parts);
    }

    private static function resolveOwnerLangPath(string $ownerKey, string $filename): ?string
    {
        $base = self::resolveOwnerPath($ownerKey);
        if ($base === null) {
            return null;
        }

        $candidates = [
            $base . '/Resources/lang/' . $filename,
            $base . '/lang/' . $filename,
        ];

        foreach ($candidates as $p) {
            if (is_file($p)) {
                return $p;
            }
        }

        return $base . '/Resources/lang/' . $filename;
    }

    private static function resolveOwnerPath(string $ownerKey): ?string
    {
        if (str_starts_with($ownerKey, 'Plugin/')) {
            return APP_ROOT . '/plugins/' . substr($ownerKey, 7);
        }
        if ($ownerKey === '') {
            return null;
        }
        if (preg_match('#^(Plugin/)?(.+)$#', $ownerKey, $m)) {
            $remainder = $m[2];
        } else {
            $remainder = $ownerKey;
        }
        if (str_starts_with($remainder, 'Studio/Tools/')) {
            return APP_ROOT . '/apps/Studio/Tools/' . substr($remainder, 13);
        }
        if (str_contains($remainder, '/')) {
            $parts = explode('/', $remainder, 2);
            return APP_ROOT . '/apps/' . $parts[0] . '/modules/' . $parts[1];
        }
        return APP_ROOT . '/apps/' . $remainder;
    }
}

<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\OwnerStructureScan\Services;

require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureOwnerDiscoveryService.php';

final class OwnerStructureFilesystemScannerService
{
    /**
     * @return array<string,mixed>
     */
    public static function scan(string $ownerKey): array
    {
        $startedAt = microtime(true);
        $appRoot = (string)(realpath(APP_ROOT) ?: APP_ROOT);
        $resolution = OwnerStructureOwnerDiscoveryService::resolve($ownerKey);
        $owner = isset($resolution['selected_owner']) && is_array($resolution['selected_owner']) ? $resolution['selected_owner'] : [];
        $ownerKey = (string)($owner['owner_key'] ?? $resolution['selected_owner_key'] ?? '');
        $ownerRoot = (string)($owner['owner_root_path'] ?? '');
        $ownerRoot = (string)(realpath($ownerRoot) ?: $ownerRoot);
        $ownerLabel = (string)($owner['display_label'] ?? $ownerKey);

        $counters = [
            'folders' => 0,
            'files' => 0,
            'errors' => 0,
            'skipped' => 0,
        ];

        $entries = [];
        $totalSize = is_dir($ownerRoot)
            ? self::scanPath($ownerRoot, $appRoot, $ownerRoot, $entries, $counters)
            : 0;

        usort($entries, static function (array $a, array $b): int {
            return strcasecmp((string)($a['relative_path'] ?? ''), (string)($b['relative_path'] ?? ''));
        });

        return [
            'owner_key' => $ownerKey,
            'owner_label' => $ownerLabel,
            'owner_type' => (string)($owner['owner_type'] ?? 'unknown'),
            'owner_root_path' => $ownerRoot,
            'owner_root_relative_path' => self::relativePath($ownerRoot, $appRoot),
            'app_root' => $appRoot,
            'duration_ms' => (int)round((microtime(true) - $startedAt) * 1000),
            'total_folders' => $counters['folders'],
            'total_files' => $counters['files'],
            'total_size' => $totalSize,
            'errors' => $counters['errors'],
            'skipped' => $counters['skipped'],
            'entries' => $entries,
            'supported_owners' => array_map(static function (array $item): string {
                return (string)($item['owner_key'] ?? '');
            }, (array)($resolution['owners'] ?? [])),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $entries
     * @param array<string,int> $counters
     */
    private static function scanPath(string $path, string $appRoot, string $ownerRoot, array &$entries, array &$counters): int
    {
        if (is_link($path)) {
            $counters['skipped']++;
            return 0;
        }

        $isDir = is_dir($path);
        $relativePath = self::relativePath($path, $appRoot);
        $ownerRelativePath = self::relativePath($path, $ownerRoot);
        if ($ownerRelativePath === '') {
            $ownerRelativePath = '.';
        }

        if (!$isDir) {
            $size = self::safeFileSize($path);
            if ($size < 0) {
                $counters['errors']++;
                $size = 0;
            }
            $counters['files']++;
            $entries[] = self::entry($path, $relativePath, $ownerRelativePath, 'file', $size);
            return $size;
        }

        $counters['folders']++;
        $totalSize = 0;
        if (!is_readable($path)) {
            $counters['errors']++;
            $entries[] = self::entry($path, $relativePath, $ownerRelativePath, 'folder', 0, 'unreadable_directory');
            return 0;
        }

        $children = scandir($path);
        if ($children === false) {
            $counters['errors']++;
            $entries[] = self::entry($path, $relativePath, $ownerRelativePath, 'folder', 0, 'scan_failed');
            return 0;
        }

        foreach ($children as $child) {
            if ($child === '.' || $child === '..') {
                continue;
            }
            $totalSize += self::scanPath($path . DIRECTORY_SEPARATOR . $child, $appRoot, $ownerRoot, $entries, $counters);
        }

        $entries[] = self::entry($path, $relativePath, $ownerRelativePath, 'folder', $totalSize);
        return $totalSize;
    }

    /**
     * @return array<string,mixed>
     */
    private static function entry(string $path, string $relativePath, string $ownerRelativePath, string $type, int $size, string $error = ''): array
    {
        return [
            'type' => $type,
            'name' => basename($path),
            'physical_path' => $path,
            'relative_path' => $relativePath,
            'owner_relative_path' => $ownerRelativePath,
            'size' => $size,
            'error' => $error,
        ];
    }

    private static function safeFileSize(string $path): int
    {
        clearstatcache(true, $path);
        $size = @filesize($path);
        return is_int($size) ? $size : -1;
    }

    private static function relativePath(string $path, string $root): string
    {
        if ($path === $root) {
            return '';
        }
        $prefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (str_starts_with($path, $prefix)) {
            return str_replace('\\', '/', substr($path, strlen($prefix)));
        }
        return str_replace('\\', '/', $path);
    }
}

<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\HelperTool\Services;

trait RepoTreeScannerScanTrait
{
    /**
     * @return array<string,mixed>
     */
    public static function scanFromRoot(): array
    {
        $root = (string)(realpath(APP_ROOT) ?: APP_ROOT);
        return self::scanPath($root);
    }

    /**
     * @return array<string,mixed>
     */
    public static function scanPath(string $root): array
    {
        $root = (string)(realpath($root) ?: $root);
        $counters = [
            'files' => 0,
            'dirs' => 0,
            'errors' => 0,
            'skipped' => 0,
        ];
        $summary = self::emptySummary();
        $owners = self::discoverOwners($root);

        $startedAt = microtime(true);
        $tree = self::scanNode($root, $root, $counters, $summary);
        self::assignOwnerKeysToTree($tree, $owners);
        $durationMs = (int)round((microtime(true) - $startedAt) * 1000);
        $summary = self::finalizeSummary($summary, $counters, (int)($tree['size'] ?? 0), $durationMs);
        $ownerStats = self::computeOwnerStats($owners, $tree);
        $ownerEntities = self::discoverEntitiesForOwners(self::separateRepoOwners($owners), $tree, $root);

        return [
            'root_path' => $root,
            'tree' => $tree,
            'counters' => $counters,
            'duration_ms' => $durationMs,
            'inventory_summary' => $summary['inventory_summary'],
            'file_type_summary' => $summary['file_type_summary'],
            'suspicious_summary' => $summary['suspicious_summary'],
            'skipped_paths' => $summary['skipped_paths'],
            'owner_owners' => $owners,
            'owner_repo_owners' => self::separateRepoOwners($owners),
            'owner_ew_owners' => self::separateWorkspaceOwners($owners),
            'owner_hierarchy' => self::buildOwnerHierarchy($owners),
            'owner_stats' => $ownerStats,
            'owner_entities' => $ownerEntities,
            'entity_summary' => self::computeEntitySummary($ownerEntities),
        ];
    }

    /**
     * @param array<string,mixed> $node
     * @param array<int,array<string,mixed>> $owners
     */
    private static function assignOwnerKeysToTree(array &$node, array $owners): void
    {
        $relPath = (string)($node['relative_path'] ?? '');
        $node['owner_key'] = self::assignOwnerKey($relPath, $owners);
        if (!isset($node['children']) || !is_array($node['children'])) {
            return;
        }
        $keys = array_keys($node['children']);
        foreach ($keys as $k) {
            if (is_array($node['children'][$k])) {
                self::assignOwnerKeysToTree($node['children'][$k], $owners);
            }
        }
    }
    /**
     * @param array<string,int> $counters
     * @param array<string,mixed> $summary
     * @return array<string,mixed>
     */
    private static function scanNode(string $path, string $root, array &$counters, array &$summary): array
    {
        $isDir = is_dir($path);
        $name = basename($path);
        if ($name === '' || $name === DIRECTORY_SEPARATOR) {
            $name = basename($root);
        }

        $relativePath = self::relativePath($path, $root);
        if ($relativePath === '') {
            $relativePath = '.';
        }

        if (!$isDir) {
            $size = self::safeFileSize($path);
            if ($size < 0) {
                $size = 0;
                $counters['errors']++;
            }
            $counters['files']++;
            $classification = self::recordFile($summary, $relativePath, $name, $size);
            return [
                'type' => 'file',
                'name' => $name,
                'relative_path' => $relativePath,
                'size' => $size,
                'artifact_categories' => $classification['categories'],
                'artifact_reasons' => $classification['reasons'],
                'children' => [],
            ];
        }

        $counters['dirs']++;
        $children = [];
        $totalSize = 0;

        if (!is_readable($path)) {
            $counters['errors']++;
            return [
                'type' => 'dir',
                'name' => $name,
                'relative_path' => $relativePath,
                'size' => 0,
                'children' => [],
                'error' => 'unreadable_directory',
            ];
        }

        $entries = scandir($path);
        if ($entries === false) {
            $counters['errors']++;
            return [
                'type' => 'dir',
                'name' => $name,
                'relative_path' => $relativePath,
                'size' => 0,
                'children' => [],
                'error' => 'scan_failed',
            ];
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if ($relativePath === '.' && $entry === '.git') {
                $counters['skipped']++;
                self::recordSkippedPath($summary, '.git', 'repository_metadata');
                continue;
            }

            $childPath = $path . DIRECTORY_SEPARATOR . $entry;
            if (is_link($childPath)) {
                $counters['skipped']++;
                self::recordSkippedPath($summary, self::relativePath($childPath, $root), 'symlink');
                continue;
            }

            $child = self::scanNode($childPath, $root, $counters, $summary);
            $children[] = $child;
            $totalSize += (int)($child['size'] ?? 0);
        }

        usort($children, static function (array $a, array $b): int {
            $aType = (string)($a['type'] ?? 'file');
            $bType = (string)($b['type'] ?? 'file');
            if ($aType !== $bType) {
                return ($aType === 'dir') ? -1 : 1;
            }
            return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        });

        return [
            'type' => 'dir',
            'name' => $name,
            'relative_path' => $relativePath,
            'size' => $totalSize,
            'artifact_categories' => self::aggregateChildCategories($children),
            'children' => $children,
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

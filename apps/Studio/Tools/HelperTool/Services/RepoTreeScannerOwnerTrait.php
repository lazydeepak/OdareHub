<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\HelperTool\Services;

use Apps\Studio\Services\StudioOwnerDiscoveryService;

trait RepoTreeScannerOwnerTrait
{
    /**
     * @return array<string,array<string,mixed>>
     */
    public static function getEntityTypeRegistry(): array
    {
        return self::ENTITY_TYPE_REGISTRY;
    }

    /**
     * @return array<int,string>
     */
    public static function getEntityTypeKeys(): array
    {
        $defs = self::ENTITY_TYPE_REGISTRY;
        uasort($defs, static fn(array $a, array $b): int => ($a['sort_order'] ?? 99) <=> ($b['sort_order'] ?? 99));
        return array_keys($defs);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function discoverOwners(?string $scanRoot = null): array
    {
        $result = StudioOwnerDiscoveryService::discover(
            $scanRoot,
            StudioOwnerDiscoveryService::PROFILE_REPOSITORY_SCANNER
        );
        $owners = $result['owners'] ?? [];

        return is_array($owners)
            ? array_values(array_filter($owners, 'is_array'))
            : [];
    }

    /**
     * @param array<int,array<string,mixed>> $owners
     * @return array<int,array<string,mixed>>
     */
    public static function separateRepoOwners(array $owners): array
    {
        $repo = [];
        foreach ($owners as $o) {
            $type = (string)($o['owner_type'] ?? '');
            if ($type !== 'engineering_workspace') {
                $repo[] = $o;
            }
        }
        return $repo;
    }

    /**
     * @param array<int,array<string,mixed>> $owners
     * @return array<int,array<string,mixed>>
     */
    public static function separateWorkspaceOwners(array $owners): array
    {
        $ews = [];
        foreach ($owners as $o) {
            $type = (string)($o['owner_type'] ?? '');
            if ($type === 'engineering_workspace') {
                $ews[] = $o;
            }
        }
        return $ews;
    }

    /**
     * @param array<int,array<string,mixed>> $owners
     * @return array<int,array{owner:array<string,mixed>,children:array<string,array<string,mixed>>}>
     */
    public static function buildOwnerHierarchy(array $owners): array
    {
        $parents = [];
        $children = [];

        foreach ($owners as $owner) {
            $key = (string)($owner['owner_key'] ?? '');
            $type = (string)($owner['owner_type'] ?? '');
            if ($key === '' || $type === 'engineering_workspace') {
                continue;
            }
            if (str_contains($key, '/')) {
                $children[$key] = $owner;
            } else {
                $parents[$key] = [
                    'owner' => $owner,
                    'children' => [],
                ];
            }
        }

        foreach ($children as $childKey => $child) {
            $slashPos = strpos($childKey, '/');
            $parentKey = substr($childKey, 0, $slashPos);
            if (isset($parents[$parentKey])) {
                $parents[$parentKey]['children'][$childKey] = $child;
            } else {
                $parents[$childKey] = [
                    'owner' => $child,
                    'children' => [],
                ];
            }
        }

        return array_values($parents);
    }

    /**
     * @param array<string,mixed> $owners
     */
    public static function assignOwnerKey(string $relativePath, array $owners): string
    {
        $best = '';
        $bestLen = 0;
        foreach ($owners as $owner) {
            $prefix = (string)($owner['relative_path'] ?? '');
            if ($prefix === '') {
                continue;
            }
            $prefixLen = strlen($prefix);
            if ($prefixLen > $bestLen && str_starts_with($relativePath, $prefix)) {
                $nextChar = $relativePath[$prefixLen] ?? '/';
                if ($nextChar === '/' || $prefixLen === 0) {
                    $best = (string)($owner['owner_key'] ?? '');
                    $bestLen = $prefixLen;
                }
            }
        }
        return $best;
    }

    /**
     * @param array<string,mixed> $owners
     * @param array<string,mixed> $tree
     * @return array<string,array<string,mixed>>
     */
    public static function computeOwnerStats(array $owners, array $tree): array
    {
        $stats = [];
        foreach ($owners as $owner) {
            $key = (string)($owner['owner_key'] ?? '');
            if ($key !== '') {
                $stats[$key] = [
                    'file_count' => 0,
                    'dir_count' => 0,
                    'total_bytes' => 0,
                    'owner_key' => $key,
                ];
            }
        }
        self::accumulateOwnerStats($tree, $stats);
        return $stats;
    }

    /**
     * @param array<string,mixed> $node
     * @param array<string,array<string,mixed>> $stats
     */
    private static function accumulateOwnerStats(array $node, array &$stats): void
    {
        $ownerKey = (string)($node['owner_key'] ?? '');
        if ($ownerKey !== '' && isset($stats[$ownerKey])) {
            $stats[$ownerKey]['total_bytes'] += (int)($node['size'] ?? 0);
            if (($node['type'] ?? '') === 'file') {
                $stats[$ownerKey]['file_count']++;
            } else {
                $stats[$ownerKey]['dir_count']++;
            }
        }
        foreach ($node['children'] ?? [] as $child) {
            if (is_array($child)) {
                self::accumulateOwnerStats($child, $stats);
            }
        }
    }
}

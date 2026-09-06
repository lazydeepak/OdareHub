<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\HelperTool\Services;

trait RepoTreeScannerEntityTrait
{
    /**
     * Discover entities for all repo owners from the scanned tree.
     *
     * @param array<int,array<string,mixed>> $repoOwners
     * @param array<string,mixed> $tree
     * @return array<string,array<int,array<string,mixed>>> owner_key => entity[]
     */
    public static function discoverEntitiesForOwners(array $repoOwners, array $tree, string $rootPath): array
    {
        $entities = [];

        $fileList = [];
        self::collectFilePaths($tree, $fileList);

        $ownerFileMap = [];
        foreach ($fileList as $relPath) {
            $ownerKey = self::assignOwnerKey($relPath, $repoOwners);
            if ($ownerKey !== '') {
                $ownerFileMap[$ownerKey][] = $relPath;
            }
        }

        foreach ($repoOwners as $owner) {
            $ownerKey = (string)($owner['owner_key'] ?? '');
            if ($ownerKey === '') {
                continue;
            }
            $ownerFiles = $ownerFileMap[$ownerKey] ?? [];
            $entities[$ownerKey] = self::discoverEntitiesForOwnerFiles($ownerKey, $ownerFiles, $rootPath);
        }

        return $entities;
    }

    /**
     * Compute repository-wide entity summary from per-owner entity data.
     *
     * @param array<string,array<int,array<string,mixed>>> $ownerEntities owner_key => entity[]
     * @return array<string,mixed>
     */
    public static function computeEntitySummary(array $ownerEntities): array
    {
        $registry = self::ENTITY_TYPE_REGISTRY;
        $counts = [];
        $distribution = [];
        $total = 0;

        foreach ($registry as $key => $def) {
            $counts[$key] = 0;
            $distribution[$key] = [];
        }

        $counts['unknown'] = 0;
        $distribution['unknown'] = [];

        foreach ($ownerEntities as $ownerKey => $entities) {
            $ownerLabel = $ownerKey;
            $perType = [];
            foreach ($entities as $e) {
                $t = (string)($e['type'] ?? 'unknown');
                $perType[$t] = ($perType[$t] ?? 0) + 1;
            }
            foreach ($perType as $t => $c) {
                $counts[$t] = ($counts[$t] ?? 0) + $c;
                $total += $c;
                $distribution[$t][] = ['owner_key' => $ownerKey, 'count' => $c];
            }
        }

        foreach ($distribution as $t => $owners) {
            usort($owners, static fn(array $a, array $b): int => ($b['count'] ?? 0) <=> ($a['count'] ?? 0));
            $distribution[$t] = $owners;
        }

        return [
            'counts' => $counts,
            'distribution' => $distribution,
            'total' => $total,
        ];
    }

    /**
     * @param array<string,mixed> $node
     * @param array<int,string> $out
     */
    private static function collectFilePaths(array $node, array &$out): void
    {
        if (($node['type'] ?? '') === 'file') {
            $relPath = (string)($node['relative_path'] ?? '');
            if ($relPath !== '') {
                $out[] = $relPath;
            }
            return;
        }
        foreach ($node['children'] ?? [] as $child) {
            if (is_array($child)) {
                self::collectFilePaths($child, $out);
            }
        }
    }

    /**
     * @param array<int,string> $ownerFiles
     * @return array<int,array<string,mixed>>
     */
    private static function discoverEntitiesForOwnerFiles(string $ownerKey, array $ownerFiles, string $rootPath): array
    {
        $entities = [];
        $registry = self::ENTITY_TYPE_REGISTRY;

        foreach ($ownerFiles as $relPath) {
            $typeKey = self::classifyEntityPath($relPath);
            if ($typeKey === null || !isset($registry[$typeKey])) {
                continue;
            }

            $typeDef = $registry[$typeKey];
            $absPath = $rootPath . '/' . $relPath;
            $strategy = (string)($typeDef['evidence_strategy'] ?? 'file_path');
            $confidenceRule = (string)($typeDef['confidence_rule'] ?? 'always_certain');

            if ($strategy === 'route_registration') {
                $routePaths = self::extractRoutePaths($absPath);
                if ($routePaths !== []) {
                    foreach ($routePaths as $rp) {
                        $entities[] = [
                            'type' => $typeKey,
                            'name' => $rp,
                            'owner_key' => $ownerKey,
                            'source_path' => $relPath,
                            'evidence' => 'Route registration: ' . $rp,
                            'is_certain' => true,
                        ];
                    }
                }
                continue;
            }

            $baseName = pathinfo($relPath, PATHINFO_FILENAME);
            $name = $baseName;
            $evidence = 'File path: ' . $relPath;
            $isCertain = ($confidenceRule !== 'class_found');

            if ($strategy === 'class_declaration') {
                $classDecl = self::extractClassDeclaration($absPath);
                if ($classDecl !== null) {
                    $name = $classDecl;
                    $evidence = 'Class declaration: ' . $classDecl;
                    $isCertain = true;
                } elseif ($confidenceRule === 'class_found') {
                    $isCertain = false;
                    $evidence = 'File path: ' . $relPath . ' (no class declaration found)';
                }
            }

            $entities[] = [
                'type' => $typeKey,
                'name' => $name,
                'owner_key' => $ownerKey,
                'source_path' => $relPath,
                'evidence' => $evidence,
                'is_certain' => $isCertain,
            ];
        }

        $orderMap = [];
        foreach ($registry as $k => $def) {
            $orderMap[$k] = (int)($def['sort_order'] ?? 99);
        }

        usort($entities, static function (array $a, array $b) use ($orderMap): int {
            $aOrd = $orderMap[$a['type']] ?? 99;
            $bOrd = $orderMap[$b['type']] ?? 99;
            if ($aOrd !== $bOrd) {
                return $aOrd <=> $bOrd;
            }
            return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        });

        return $entities;
    }

    private static function classifyEntityPath(string $relativePath): ?string
    {
        $ext = '.' . (string)pathinfo($relativePath, PATHINFO_EXTENSION);

        foreach (self::ENTITY_TYPE_REGISTRY as $key => $def) {
            if (!empty($def['is_routes_file'])) {
                if (self::isRoutesFileDetect($relativePath, $ext)) {
                    return $key;
                }
                continue;
            }
            $pattern = (string)($def['path_pattern'] ?? '');
            $reqExt = (string)($def['file_extension'] ?? '');
            if ($pattern !== '' && str_contains($relativePath, $pattern)) {
                if ($reqExt === '' || $ext === $reqExt) {
                    return $key;
                }
            }
        }
        return null;
    }

    private static function isRoutesFileDetect(string $relativePath, string $ext): bool
    {
        if ($ext !== '.php') {
            return false;
        }
        $base = basename($relativePath);
        if ($base === 'routes.php') {
            return true;
        }
        $dir = dirname($relativePath);
        if (basename($dir) === 'Routes') {
            return true;
        }
        return false;
    }

    /**
     * @return array<int,string>
     */
    private static function extractRoutePaths(string $filePath): array
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            return [];
        }

        $content = @file_get_contents($filePath);
        if ($content === false || $content === '') {
            return [];
        }

        $paths = [];
        $pattern = '/\$router\s*->\s*(get|post|put|delete|patch)\s*\(\s*([\'\"])([^\'\"]+)\2\s*,/i';
        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER) > 0) {
            foreach ($matches as $m) {
                $method = strtoupper((string)($m[1] ?? ''));
                $path = (string)($m[3] ?? '');
                if ($path !== '') {
                    $paths[] = $method . ' ' . $path;
                }
            }
        }

        return array_unique($paths);
    }

    private static function extractClassDeclaration(string $filePath): ?string
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            return null;
        }

        $handle = @fopen($filePath, 'rb');
        if ($handle === false) {
            return null;
        }

        $content = '';
        $line = '';
        $read = 0;
        while (($line = fgets($handle)) !== false && $read < 40) {
            $content .= $line;
            $read++;
        }
        fclose($handle);

        if (preg_match('/\b(?:final\s+)?(?:abstract\s+)?class\s+(\w+)/i', $content, $m) === 1) {
            return (string)($m[1] ?? null);
        }

        return null;
    }
    /**
     * Build a flat, client-embeddable search index from already-discovered scan data.
     *
     * Each entry has:
     *   result_type  — 'file' | 'owner' | 'entity' | 'route' | 'workspace'
     *   label        — primary human-readable display string
     *   sublabel     — secondary context string (owner key, entity type, etc.)
     *   path         — relative file path if applicable (used to open File Inspector)
     *   owner_key    — owner key if applicable (used to activate owner filter)
     *   action       — 'inspect_file' | 'select_owner' | 'none'
     *   evidence     — brief provenance note shown in results
     *
     * @param array<string,mixed> $scanResult full result from scanPath()
     * @return array<int,array<string,mixed>>
     */
    public static function buildSearchIndex(array $scanResult): array
    {
        $index = [];

        // ---- Files ----
        $tree = isset($scanResult['tree']) && is_array($scanResult['tree']) ? $scanResult['tree'] : [];
        if ($tree !== []) {
            $fileList = [];
            self::collectFilePaths($tree, $fileList);
            $owners = isset($scanResult['owner_owners']) && is_array($scanResult['owner_owners'])
                ? $scanResult['owner_owners'] : [];
            foreach ($fileList as $relPath) {
                $name = basename($relPath);
                $ownerKey = self::assignOwnerKey($relPath, $owners);
                $index[] = [
                    'result_type' => 'file',
                    'label' => $name,
                    'sublabel' => $relPath,
                    'path' => $relPath,
                    'owner_key' => $ownerKey,
                    'action' => 'inspect_file',
                    'evidence' => 'file',
                ];
            }
        }

        // ---- Owners ----
        $repoOwners = isset($scanResult['owner_repo_owners']) && is_array($scanResult['owner_repo_owners'])
            ? $scanResult['owner_repo_owners'] : [];
        foreach ($repoOwners as $owner) {
            $ok = (string)($owner['owner_key'] ?? '');
            if ($ok === '') {
                continue;
            }
            $index[] = [
                'result_type' => 'owner',
                'label' => (string)($owner['display_label'] ?? $ok),
                'sublabel' => (string)($owner['owner_type'] ?? ''),
                'path' => (string)($owner['relative_path'] ?? ''),
                'owner_key' => $ok,
                'action' => 'select_owner',
                'evidence' => 'owner',
            ];
        }

        // ---- Engineering Workspaces ----
        $ewOwners = isset($scanResult['owner_ew_owners']) && is_array($scanResult['owner_ew_owners'])
            ? $scanResult['owner_ew_owners'] : [];
        foreach ($ewOwners as $ew) {
            $ok = (string)($ew['owner_key'] ?? '');
            if ($ok === '') {
                continue;
            }
            $index[] = [
                'result_type' => 'workspace',
                'label' => (string)($ew['display_label'] ?? $ok),
                'sublabel' => 'engineering_workspace',
                'path' => (string)($ew['relative_path'] ?? ''),
                'owner_key' => $ok,
                'action' => 'none',
                'evidence' => 'workspace',
            ];
        }

        // ---- Entities (controllers, services, views) and Routes ----
        $ownerEntities = isset($scanResult['owner_entities']) && is_array($scanResult['owner_entities'])
            ? $scanResult['owner_entities'] : [];
        foreach ($ownerEntities as $ownerKey => $entities) {
            if (!is_array($entities)) {
                continue;
            }
            foreach ($entities as $entity) {
                if (!is_array($entity)) {
                    continue;
                }
                $entityType = (string)($entity['type'] ?? '');
                $isRoute = ($entityType === 'route');
                $index[] = [
                    'result_type' => $isRoute ? 'route' : 'entity',
                    'label' => (string)($entity['name'] ?? ''),
                    'sublabel' => $entityType . ' · ' . (string)$ownerKey,
                    'path' => (string)($entity['source_path'] ?? ''),
                    'owner_key' => (string)$ownerKey,
                    'action' => 'inspect_file',
                    'evidence' => (string)($entity['evidence'] ?? $entityType),
                ];
            }
        }

        return $index;
    }

    /**
     * Build a mapping from repo owner keys to matching engineering workspace keys.
     *
     * Repo owners (app/module/plugin/platform) are matched against workspace
     * owners (EW/...) by longest-prefix alignment. Plugin/* owners fall back
     * to EW/Plugin when no exact child workspace exists.
     *
     * @param array<int,array<string,mixed>> $owners Full owner list from discoverOwners()
     * @return array<string,string> repo_owner_key => ew_owner_key
     */
    public static function resolveOwnerToWorkspaceMap(array $owners): array
    {
        $ewByPath = [];
        $repoKeys = [];
        foreach ($owners as $owner) {
            $key = (string)($owner['owner_key'] ?? '');
            $type = (string)($owner['owner_type'] ?? '');
            if ($type === 'engineering_workspace') {
                $ewByPath[preg_replace('#^EW/#', '', $key)] = $key;
            } else {
                $repoKeys[] = $key;
            }
        }
        $map = [];
        foreach ($repoKeys as $repoKey) {
            if (isset($ewByPath[$repoKey])) {
                $map[$repoKey] = $ewByPath[$repoKey];
                continue;
            }
            if (str_contains($repoKey, '/')) {
                $parts = explode('/', $repoKey);
                while (count($parts) > 0) {
                    $candidate = implode('/', $parts);
                    if (isset($ewByPath[$candidate])) {
                        $map[$repoKey] = $ewByPath[$candidate];
                        break;
                    }
                    array_pop($parts);
                }
            }
        }
        return $map;
    }
}

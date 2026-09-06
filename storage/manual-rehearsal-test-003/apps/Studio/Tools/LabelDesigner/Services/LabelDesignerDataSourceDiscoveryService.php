<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\LabelDesigner\Services;

use App\Core\DB;
use Throwable;

/**
 * Read-only owner-scoped DB source discovery for future label contexts.
 *
 * This service inspects schema metadata only. Results are candidates, not
 * approved label sources, and no selection is saved or bound to a label.
 */
final class LabelDesignerDataSourceDiscoveryService
{
    private const MAX_SOURCES_PER_OWNER = 12;
    private const MAX_COLUMNS_PER_SOURCE = 32;

    /**
     * @return array{owners:array<int,array<string,mixed>>,selected_owner_key:string,candidate_sources:array<int,array<string,mixed>>,source_count:int,column_count:int,error:string}
     */
    public static function discover(?string $selectedOwnerKey = null): array
    {
        $owners = self::knownOwners();
        $selectedOwner = self::selectOwner($owners, $selectedOwnerKey);

        if ($selectedOwner === null) {
            return [
                'owners' => [],
                'selected_owner_key' => '',
                'candidate_sources' => [],
                'source_count' => 0,
                'column_count' => 0,
                'error' => '',
            ];
        }

        try {
            $allSources = self::schemaSources();
            $candidateSources = self::filterCandidateSources($allSources, $selectedOwner);
            $candidateSources = array_slice($candidateSources, 0, self::MAX_SOURCES_PER_OWNER);
            $candidateSources = self::attachColumns($candidateSources);
        } catch (Throwable $e) {
            return [
                'owners' => $owners,
                'selected_owner_key' => (string)$selectedOwner['owner_key'],
                'candidate_sources' => [],
                'source_count' => 0,
                'column_count' => 0,
                'error' => 'DB metadata unavailable for read-only discovery.',
            ];
        }

        $columnCount = 0;
        foreach ($candidateSources as $source) {
            $columns = isset($source['columns']) && is_array($source['columns']) ? $source['columns'] : [];
            $columnCount += count($columns);
        }

        return [
            'owners' => $owners,
            'selected_owner_key' => (string)$selectedOwner['owner_key'],
            'candidate_sources' => $candidateSources,
            'source_count' => count($candidateSources),
            'column_count' => $columnCount,
            'error' => '',
        ];
    }

    /**
     * @return array<int,array{owner_key:string,owner_type:string,display_name:string,root_path:string,tokens:array<int,string>}>
     */
    private static function knownOwners(): array
    {
        $owners = [];

        foreach (self::childDirectories(APP_ROOT . '/apps') as $appDir) {
            $manifest = self::readManifest($appDir . '/manifest.json');
            $appKey = self::ownerKey($manifest, basename($appDir));
            $owners[] = [
                'owner_key' => $appKey,
                'owner_type' => 'app',
                'display_name' => self::displayName($manifest, basename($appDir)),
                'root_path' => self::relativePath($appDir),
                'tokens' => self::ownerTokens($appKey, $manifest, basename($appDir)),
            ];

            foreach (self::childDirectories($appDir . '/modules') as $moduleDir) {
                $moduleManifest = self::readManifest($moduleDir . '/plugin.json');
                $moduleKey = self::ownerKey($moduleManifest, basename($moduleDir));
                $compoundKey = $appKey . '/' . $moduleKey;
                $owners[] = [
                    'owner_key' => $compoundKey,
                    'owner_type' => 'module',
                    'display_name' => self::displayName($moduleManifest, basename($moduleDir)),
                    'root_path' => self::relativePath($moduleDir),
                    'tokens' => self::ownerTokens($compoundKey, $moduleManifest, basename($moduleDir), $appKey),
                ];
            }
        }

        foreach (self::childDirectories(APP_ROOT . '/plugins') as $pluginDir) {
            $manifest = self::readManifest($pluginDir . '/plugin.json');
            $pluginKey = self::ownerKey($manifest, basename($pluginDir));
            $owners[] = [
                'owner_key' => $pluginKey,
                'owner_type' => 'plugin',
                'display_name' => self::displayName($manifest, basename($pluginDir)),
                'root_path' => self::relativePath($pluginDir),
                'tokens' => self::ownerTokens($pluginKey, $manifest, basename($pluginDir)),
            ];
        }

        usort($owners, static fn (array $a, array $b): int => strcmp((string)$a['owner_key'], (string)$b['owner_key']));
        return $owners;
    }

    /**
     * @param array<int,array<string,mixed>> $owners
     * @return array<string,mixed>|null
     */
    private static function selectOwner(array $owners, ?string $selectedOwnerKey): ?array
    {
        $selectedOwnerKey = trim((string)$selectedOwnerKey);
        foreach ($owners as $owner) {
            if (
                $selectedOwnerKey !== ''
                && hash_equals(strtolower((string)$owner['owner_key']), strtolower($selectedOwnerKey))
            ) {
                return $owner;
            }
        }

        foreach ($owners as $owner) {
            if ((string)$owner['owner_type'] === 'app' && strtolower((string)$owner['owner_key']) === 'manufacturing') {
                return $owner;
            }
        }

        return $owners[0] ?? null;
    }

    /**
     * @return array<int,array{source_name:string,source_type:string}>
     */
    private static function schemaSources(): array
    {
        $rows = DB::fetchAll(
            "SELECT TABLE_NAME AS source_name, TABLE_TYPE AS source_type
             FROM information_schema.tables
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_TYPE IN ('BASE TABLE', 'VIEW')
             ORDER BY TABLE_NAME ASC"
        );

        $sources = [];
        foreach ($rows as $row) {
            $name = (string)($row['source_name'] ?? '');
            if (!self::isSafeIdentifier($name) || self::isSystemSource($name)) {
                continue;
            }

            $sources[] = [
                'source_name' => $name,
                'source_type' => (string)($row['source_type'] ?? 'BASE TABLE'),
            ];
        }

        return $sources;
    }

    /**
     * @param array<int,array{source_name:string,source_type:string}> $allSources
     * @param array<string,mixed> $owner
     * @return array<int,array<string,mixed>>
     */
    private static function filterCandidateSources(array $allSources, array $owner): array
    {
        $tokens = isset($owner['tokens']) && is_array($owner['tokens']) ? $owner['tokens'] : [];
        $candidates = [];

        foreach ($allSources as $source) {
            $sourceName = strtolower((string)$source['source_name']);
            $matchedToken = self::matchedOwnerToken($sourceName, $tokens);
            if ($matchedToken === '') {
                continue;
            }

            $candidates[] = [
                'source_name' => $source['source_name'],
                'source_type' => $source['source_type'],
                'candidate_reason' => 'Matched owner naming token: ' . $matchedToken,
                'approval_status' => 'candidate_only',
                'columns' => [],
            ];
        }

        usort($candidates, static function (array $a, array $b): int {
            return strcmp((string)$a['source_name'], (string)$b['source_name']);
        });

        return $candidates;
    }

    /**
     * @param array<int,array<string,mixed>> $candidateSources
     * @return array<int,array<string,mixed>>
     */
    private static function attachColumns(array $candidateSources): array
    {
        foreach ($candidateSources as $index => $source) {
            $sourceName = (string)($source['source_name'] ?? '');
            if (!self::isSafeIdentifier($sourceName)) {
                continue;
            }

            $rows = DB::fetchAll(
                "SELECT COLUMN_NAME AS column_name, DATA_TYPE AS data_type, IS_NULLABLE AS is_nullable
                 FROM information_schema.columns
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                 ORDER BY ORDINAL_POSITION ASC",
                [$sourceName]
            );

            $columns = [];
            foreach ($rows as $row) {
                $columnName = (string)($row['column_name'] ?? '');
                if (!self::isSafeIdentifier($columnName)) {
                    continue;
                }

                $columns[] = [
                    'column_name' => $columnName,
                    'data_type' => (string)($row['data_type'] ?? ''),
                    'is_nullable' => (string)($row['is_nullable'] ?? ''),
                ];

                if (count($columns) >= self::MAX_COLUMNS_PER_SOURCE) {
                    break;
                }
            }

            $candidateSources[$index]['columns'] = $columns;
        }

        return $candidateSources;
    }

    /**
     * @param array<int,string> $tokens
     */
    private static function matchedOwnerToken(string $sourceName, array $tokens): string
    {
        foreach ($tokens as $token) {
            $token = strtolower($token);
            if ($token === '') {
                continue;
            }

            if (
                $sourceName === $token
                || str_starts_with($sourceName, $token . '_')
                || str_contains($sourceName, '_' . $token . '_')
                || str_ends_with($sourceName, '_' . $token)
            ) {
                return $token;
            }
        }

        return '';
    }

    /**
     * @param array<string,mixed> $manifest
     * @return array<int,string>
     */
    private static function ownerTokens(string $ownerKey, array $manifest, string $directoryName, string $parentKey = ''): array
    {
        $raw = [$ownerKey, $directoryName, $parentKey];
        foreach (['id', 'app_key', 'key', 'name', 'directory_name'] as $key) {
            if (isset($manifest[$key]) && is_string($manifest[$key])) {
                $raw[] = $manifest[$key];
            }
        }

        if (str_contains(strtolower($ownerKey), 'manufacturing')) {
            array_push($raw, 'manufacturing', 'mfg', 'products', 'product', 'part', 'parts', 'qc', 'dispatch');
        }
        if (str_contains(strtolower($ownerKey), 'inventory')) {
            array_push($raw, 'inventory', 'stock', 'bin', 'bins', 'location', 'locations');
        }
        if (str_contains(strtolower($ownerKey), 'lazypos') || str_contains(strtolower($ownerKey), 'pos')) {
            array_push($raw, 'lazypos', 'pos', 'product', 'products', 'price', 'prices');
        }

        $tokens = [];
        foreach ($raw as $value) {
            foreach (preg_split('/[^a-zA-Z0-9]+/', (string)$value) ?: [] as $part) {
                $part = strtolower(trim($part));
                if (strlen($part) < 3) {
                    continue;
                }
                $tokens[$part] = $part;
            }
        }

        return array_values($tokens);
    }

    /**
     * @return array<string,mixed>
     */
    private static function readManifest(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $json = file_get_contents($path);
        if (!is_string($json) || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string,mixed> $manifest
     */
    private static function ownerKey(array $manifest, string $fallback): string
    {
        foreach (['app_key', 'key', 'id', 'name'] as $key) {
            if (!empty($manifest[$key]) && is_string($manifest[$key])) {
                return self::slug((string)$manifest[$key]);
            }
        }

        return self::slug($fallback);
    }

    /**
     * @param array<string,mixed> $manifest
     */
    private static function displayName(array $manifest, string $fallback): string
    {
        return trim((string)($manifest['name'] ?? $fallback));
    }

    /**
     * @return array<int,string>
     */
    private static function childDirectories(string $dir): array
    {
        $realDir = realpath($dir);
        if (!is_string($realDir) || !is_dir($realDir) || !self::isInsideAppRoot($realDir)) {
            return [];
        }

        $children = scandir($realDir);
        if (!is_array($children)) {
            return [];
        }

        $dirs = [];
        foreach ($children as $child) {
            if ($child === '.' || $child === '..' || str_starts_with($child, '.')) {
                continue;
            }
            $path = $realDir . '/' . $child;
            if (is_dir($path)) {
                $dirs[] = $path;
            }
        }

        sort($dirs);
        return $dirs;
    }

    private static function slug(string $value): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $value) ?? '', '_'));
        return $slug !== '' ? $slug : 'unknown';
    }

    private static function isSafeIdentifier(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9_]+$/', $value) === 1;
    }

    private static function isSystemSource(string $sourceName): bool
    {
        $sourceName = strtolower($sourceName);
        $blockedPrefixes = [
            'core_',
            'acl_',
            'user_',
            'users',
            'permissions',
            'role_',
            'studio_',
            'menus',
            'migrations',
            'installed_',
        ];

        foreach ($blockedPrefixes as $prefix) {
            if ($sourceName === $prefix || str_starts_with($sourceName, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private static function isInsideAppRoot(string $path): bool
    {
        $root = realpath(APP_ROOT);
        if (!is_string($root)) {
            return false;
        }

        return $path === $root || str_starts_with($path, $root . '/');
    }

    private static function relativePath(string $path): string
    {
        $root = realpath(APP_ROOT);
        $realPath = realpath($path);
        if (!is_string($root)) {
            return $path;
        }

        $normalized = is_string($realPath) ? $realPath : $path;
        if ($normalized === $root) {
            return '.';
        }

        if (str_starts_with($normalized, $root . '/')) {
            return substr($normalized, strlen($root) + 1);
        }

        return $path;
    }
}

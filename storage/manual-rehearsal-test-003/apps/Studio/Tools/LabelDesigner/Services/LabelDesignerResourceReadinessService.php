<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\LabelDesigner\Services;

final class LabelDesignerResourceReadinessService
{
    private const FOLDER_STRUCTURE = [
        'Resources/labels',
        'Resources/labels/contexts',
        'Resources/labels/templates',
        'Resources/labels/rules',
    ];

    private const SNAPSHOT_ROOT = APP_ROOT . '/storage/studio-snapshots/label-designer';

    /**
     * Owner keys that do not have a business label lifecycle.
     * Infrastructure, tooling, and system layers only — they manage
     * platform behavior, not producible/labelable business data.
     */
    private const NON_LABEL_LIFECYCLE_OWNERS = [
        'shell',
        'studio',
        'platform',
        'platform/qrcode',
        'generated',
        'acl',
        'admintools',
        'audit',
        'base',
        'bus',
    ];

    public static function isLabelLifecycleOwner(string $ownerKey): bool
    {
        $normalized = strtolower(trim($ownerKey));
        if ($normalized === '') {
            return false;
        }

        // Only module-level owners (containing '/') have a business label lifecycle.
        // Bare app keys such as 'manufacturing' are parent containers whose label
        // resources live under module owners like 'manufacturing/products'.
        if (!str_contains($normalized, '/')) {
            return false;
        }

        return !in_array($normalized, self::NON_LABEL_LIFECYCLE_OWNERS, true);
    }

    public static function checkReadiness(): array
    {
        $owners = self::discoverOwners();
        $results = [];
        $completeCount = 0;
        $partialCount = 0;
        $absentCount = 0;
        $lifecycleOwnerCount = 0;

        foreach ($owners as $owner) {
            $ownerKey = $owner['owner_key'];
            $isLifecycle = self::isLabelLifecycleOwner($ownerKey);
            $absRoot = $owner['root_path_abs'];
            $readiness = [];
            $allExist = true;
            $noneExist = true;

            foreach (self::FOLDER_STRUCTURE as $folder) {
                $fullPath = $absRoot . '/' . $folder;
                $exists = is_dir($fullPath);
                $readiness[] = [
                    'folder' => $folder,
                    'exists' => $exists,
                    'path' => self::toRelativePath($fullPath),
                ];
                if (!$exists) {
                    $allExist = false;
                } else {
                    $noneExist = false;
                }
            }

            if ($allExist) {
                $completeCount++;
            } elseif ($noneExist) {
                $absentCount++;
            } else {
                $partialCount++;
            }

            if ($isLifecycle) {
                $lifecycleOwnerCount++;
            }

            $results[] = [
                'owner_key' => $ownerKey,
                'owner_type' => $owner['owner_type'],
                'display_name' => $owner['display_name'],
                'root_path' => $owner['root_path_rel'],
                'readiness' => $readiness,
                'all_exist' => $allExist,
                'none_exist' => $noneExist,
                'is_label_lifecycle_owner' => $isLifecycle,
            ];
        }

        return [
            'owners' => $results,
            'owner_count' => count($results),
            'complete_count' => $completeCount,
            'partial_count' => $partialCount,
            'absent_count' => $absentCount,
            'lifecycle_owner_count' => $lifecycleOwnerCount,
        ];
    }

    public static function createMissingFolders(array $input, array $actor = []): array
    {
        $ownerKey = trim((string)($input['owner_key'] ?? ''));
        $confirm = trim((string)($input['confirm_create'] ?? ''));

        if ($confirm !== 'yes') {
            return ['ok' => false, 'errors' => ['Confirmation is required.']];
        }

        $owners = self::discoverOwners();
        $selectedOwner = null;
        foreach ($owners as $owner) {
            if (hash_equals($owner['owner_key'], $ownerKey)) {
                $selectedOwner = $owner;
                break;
            }
        }

        if ($selectedOwner === null) {
            return ['ok' => false, 'errors' => ['Selected owner does not exist.']];
        }

        if (!self::isLabelLifecycleOwner($ownerKey)) {
            return ['ok' => false, 'errors' => ['Selected owner has no business label lifecycle and is not eligible for label resource folders.']];
        }

        $resolvedRoot = self::resolveOwnerRoot($selectedOwner['root_path_rel']);
        if ($resolvedRoot === '') {
            return ['ok' => false, 'errors' => ['Owner root path is invalid or outside allowed boundaries.']];
        }

        if (self::isForbiddenPath($resolvedRoot)) {
            return ['ok' => false, 'errors' => ['Cannot create label resource folders in Core, vendor, or public paths.']];
        }

        $errors = [];
        $created = [];
        $snapshotBefore = [];
        $snapshotAfter = [];

        foreach (self::FOLDER_STRUCTURE as $folder) {
            $fullPath = $resolvedRoot . '/' . $folder;
            $relPath = self::toRelativePath($fullPath);

            if (is_dir($fullPath)) {
                $snapshotBefore[] = ['folder' => $relPath, 'existed' => true, 'action' => 'skipped'];
                continue;
            }

            if (!self::isPathInside($fullPath, $resolvedRoot)) {
                $errors[] = 'Path traversal blocked for: ' . $relPath;
                continue;
            }

            $snapshotBefore[] = ['folder' => $relPath, 'existed' => false, 'action' => 'create'];

            if (!@mkdir($fullPath, 0755, true) && !is_dir($fullPath)) {
                $errors[] = 'Failed to create folder: ' . $relPath;
                continue;
            }

            $created[] = $relPath;
            $snapshotAfter[] = ['folder' => $relPath, 'created' => true];
        }

        $snapshotResult = self::writeSnapshot([
            'owner' => [
                'owner_key' => $selectedOwner['owner_key'],
                'owner_type' => $selectedOwner['owner_type'],
                'display_name' => $selectedOwner['display_name'],
                'root_path' => $selectedOwner['root_path_rel'],
            ],
            'action' => 'create_resource_folders',
            'resource_type' => 'resource_folders',
            'target_root' => self::toRelativePath($resolvedRoot),
            'before' => $snapshotBefore,
            'after' => $snapshotAfter,
            'created_folders' => $created,
            'errors' => $errors,
            'actor' => self::actorSummary($actor),
            'timestamp' => gmdate('c'),
            'validation' => [
                'owner_exists' => true,
                'owner_root_valid' => true,
                'path_traversal_blocked' => true,
                'no_core_path' => !self::isForbiddenPath($resolvedRoot),
            ],
            'rollback_hint' => 'Removed label resource folders under: ' . self::toRelativePath($resolvedRoot),
        ]);

        $ok = empty($errors);

        if ($ok && empty($created)) {
            return [
                'ok' => true,
                'errors' => [],
                'message' => 'All required label resource folders already exist.',
                'created' => [],
                'snapshot_path' => (string)($snapshotResult['path_rel'] ?? ''),
                'owner_key' => $ownerKey,
            ];
        }

        if (!$ok) {
            return [
                'ok' => false,
                'errors' => $errors,
                'created' => $created,
                'snapshot_path' => (string)($snapshotResult['path_rel'] ?? ''),
                'owner_key' => $ownerKey,
            ];
        }

        return [
            'ok' => true,
            'errors' => [],
            'created' => $created,
            'snapshot_path' => (string)($snapshotResult['path_rel'] ?? ''),
            'owner_key' => $ownerKey,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function discoverOwners(): array
    {
        $owners = [];

        foreach (self::childDirectories(APP_ROOT . '/apps') as $appDir) {
            $manifest = self::readManifest($appDir . '/manifest.json');
            $appKey = self::ownerKey($manifest, basename($appDir));
            $absPath = realpath($appDir);
            if (!is_string($absPath)) {
                continue;
            }

            $owners[] = [
                'owner_key' => $appKey,
                'owner_type' => 'app',
                'display_name' => self::displayName($manifest, basename($appDir)),
                'root_path_rel' => self::toRelativePath($absPath),
                'root_path_abs' => $absPath,
            ];

            $modulesDir = $absPath . '/modules';
            if (is_dir($modulesDir)) {
                foreach (self::childDirectories($modulesDir) as $moduleDir) {
                    $moduleManifest = self::readManifest($moduleDir . '/plugin.json');
                    $moduleKey = self::ownerKey($moduleManifest, basename($moduleDir));
                    $compoundKey = $appKey . '/' . $moduleKey;
                    $moduleAbsPath = realpath($moduleDir);
                    if (!is_string($moduleAbsPath)) {
                        continue;
                    }

                    $owners[] = [
                        'owner_key' => $compoundKey,
                        'owner_type' => 'module',
                        'display_name' => self::displayName($moduleManifest, basename($moduleDir)),
                        'root_path_rel' => self::toRelativePath($moduleAbsPath),
                        'root_path_abs' => $moduleAbsPath,
                    ];
                }
            }
        }

        foreach (self::childDirectories(APP_ROOT . '/plugins') as $pluginDir) {
            $manifest = self::readManifest($pluginDir . '/plugin.json');
            $pluginKey = self::ownerKey($manifest, basename($pluginDir));
            $absPath = realpath($pluginDir);
            if (!is_string($absPath)) {
                continue;
            }

            $owners[] = [
                'owner_key' => $pluginKey,
                'owner_type' => 'plugin',
                'display_name' => self::displayName($manifest, basename($pluginDir)),
                'root_path_rel' => self::toRelativePath($absPath),
                'root_path_abs' => $absPath,
            ];
        }

        usort($owners, static fn (array $a, array $b): int => strcmp((string)$a['owner_key'], (string)$b['owner_key']));
        return $owners;
    }

    /**
     * @return array<int, string>
     */
    private static function childDirectories(string $parent): array
    {
        $realDir = realpath($parent);
        if (!is_string($realDir) || !is_dir($realDir)) {
            return [];
        }

        $entries = scandir($realDir);
        if (!is_array($entries)) {
            return [];
        }

        $dirs = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                continue;
            }
            $path = $realDir . '/' . $entry;
            if (is_dir($path)) {
                $dirs[] = $path;
            }
        }

        sort($dirs);
        return $dirs;
    }

    /**
     * @return array<string, mixed>
     */
    private static function readManifest(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $content = file_get_contents($path);
        if (!is_string($content) || $content === '') {
            return [];
        }
        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $manifest
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
     * @param array<string, mixed> $manifest
     */
    private static function displayName(array $manifest, string $fallback): string
    {
        return trim((string)($manifest['name'] ?? $fallback));
    }

    private static function slug(string $value): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $value) ?? '', '_'));
        return $slug !== '' ? $slug : 'unknown';
    }

    private static function resolveOwnerRoot(string $relativeRoot): string
    {
        $relativeRoot = trim($relativeRoot);
        if ($relativeRoot === '' || str_contains($relativeRoot, '..')) {
            return '';
        }

        $absolute = APP_ROOT . '/' . ltrim($relativeRoot, '/');
        $real = realpath($absolute);
        if (!is_string($real) || !is_dir($real)) {
            return '';
        }

        $appsRoot = realpath(APP_ROOT . '/apps');
        $pluginsRoot = realpath(APP_ROOT . '/plugins');

        $insideApps = is_string($appsRoot) && ($real === $appsRoot || str_starts_with($real, $appsRoot . '/'));
        $insidePlugins = is_string($pluginsRoot) && ($real === $pluginsRoot || str_starts_with($real, $pluginsRoot . '/'));

        if (!$insideApps && !$insidePlugins) {
            return '';
        }

        return $real;
    }

    private static function isForbiddenPath(string $normalizedPath): bool
    {
        $root = rtrim(str_replace('\\', '/', (string)APP_ROOT), '/');

        $corePath = $root . '/app';
        if (str_starts_with($normalizedPath, $corePath . '/') || $normalizedPath === $corePath) {
            return true;
        }

        $vendorPath = $root . '/vendor';
        if (str_starts_with($normalizedPath, $vendorPath . '/') || $normalizedPath === $vendorPath) {
            return true;
        }

        $publicPath = $root . '/public';
        if (str_starts_with($normalizedPath, $publicPath . '/') || $normalizedPath === $publicPath) {
            return true;
        }

        return false;
    }

    private static function isPathInside(string $path, string $root): bool
    {
        $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/');
        $normalizedPath = str_replace('\\', '/', $path);

        return $normalizedPath === $normalizedRoot || str_starts_with($normalizedPath, $normalizedRoot . '/');
    }

    private static function toRelativePath(string $path): string
    {
        $root = rtrim((string)APP_ROOT, '/');
        $normalized = str_replace('\\', '/', $path);
        if (str_starts_with($normalized, $root . '/')) {
            return substr($normalized, strlen($root) + 1);
        }
        return $path;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, path_rel?: string}
     */
    private static function writeSnapshot(array $payload): array
    {
        if (!is_dir(self::SNAPSHOT_ROOT)) {
            if (!@mkdir(self::SNAPSHOT_ROOT, 0755, true) && !is_dir(self::SNAPSHOT_ROOT)) {
                return ['ok' => false];
            }
        }

        $ownerKey = (string)($payload['owner']['owner_key'] ?? 'owner');
        $safeKey = preg_replace('/[^a-z0-9._-]+/i', '-', strtolower($ownerKey)) ?: 'owner';
        $snapshotId = gmdate('Ymd_His') . '_' . substr(sha1($safeKey . microtime(true)), 0, 10);
        $snapshotPath = self::SNAPSHOT_ROOT . '/folder-create-' . $safeKey . '-' . $snapshotId . '.json';

        $payload['snapshot_id'] = $snapshotId;
        $payload['created_at'] = gmdate('c');

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') {
            return ['ok' => false];
        }

        $written = @file_put_contents($snapshotPath, $json . "\n");
        if ($written === false) {
            return ['ok' => false];
        }

        return [
            'ok' => true,
            'path_rel' => self::toRelativePath($snapshotPath),
        ];
    }

    /**
     * @param array<string, mixed> $actor
     * @return array<string, string>
     */
    private static function actorSummary(array $actor): array
    {
        return [
            'id' => isset($actor['id']) ? (string)$actor['id'] : '',
            'email' => isset($actor['email']) ? (string)$actor['email'] : '',
            'username' => isset($actor['username']) ? (string)$actor['username'] : '',
            'name' => isset($actor['name']) ? (string)$actor['name'] : '',
        ];
    }
}

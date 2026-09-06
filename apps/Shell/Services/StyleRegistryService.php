<?php
declare(strict_types=1);

namespace Apps\Shell\Services;

use App\Core\DB;

/**
 * StyleRegistryService
 *
 * Manages CSS registration and discovery per surface.
 * Apps/modules register their styles via manifest.json "styles" array.
 * Composers query forSurface() to get ordered, deduped CSS entries.
 *
 * Registration format in manifest.json:
 * "styles": [
 *   {
 *     "key": "unique.identifier",
 *     "path": "styles/app.css",
 *     "scope": "app",
 *     "surfaces": ["admin", "operator"],
 *     "order": 200
 *   }
 * ]
 */
final class StyleRegistryService
{
    /** @var array<string,array<string,mixed>> Cache of app manifests */
    private static array $manifestCache = [];

    /** @var array<string,array<int,array<string,mixed>>> Cache of registered styles per surface */
    private static array $registryCache = [];

    /**
     * Returns global shared stylesheets that load first.
     * @return array<int,array<string,mixed>>
     */
    public static function globals(): array
    {
        return [
            [
                'key' => 'global.normalize',
                'path' => '/assets/normalize.css',
                'url' => '/assets/normalize.css',
                'order' => 10,
                'version' => self::getFileVersion('public/assets/normalize.css'),
            ],
            [
                'key' => 'global.rendering-foundation',
                'path' => '/assets/rendering/foundation.css',
                'url' => '/assets/rendering/foundation.css',
                'order' => 15,
                'version' => self::getFileVersion('public/assets/rendering/foundation.css'),
            ],
            [
                'key' => 'global.theme',
                'path' => '/assets/theme.css',
                'url' => '/assets/theme.css',
                'order' => 20,
                'version' => self::getFileVersion('public/assets/theme.css'),
            ],
            [
                'key' => 'global.layout',
                'path' => '/assets/layout.css',
                'url' => '/assets/layout.css',
                'order' => 30,
                'version' => self::getFileVersion('public/assets/layout.css'),
            ],
            [
                'key' => 'global.wrapper-shared',
                'path' => '/assets/wrapper-shared.css',
                'url' => '/assets/wrapper-shared.css',
                'order' => 40,
                'version' => self::getFileVersion('public/assets/wrapper-shared.css'),
            ],
        ];
    }

    /**
     * Get all CSS entries for a surface, ordered and deduped.
     * @param string $surface Surface name (admin, operator, work-entry, display, auth)
     * @param array<string,mixed> $context Optional context for filtering (active_assigned_apps, assigned_apps)
     * @return array<int,array<string,mixed>> Ordered CSS entries with resolved URLs and versions
     */
    public static function forSurface(string $surface, array $context = []): array
    {
        $surface = strtolower(trim($surface));
        if ($surface === '') {
            return [];
        }

        $cacheKey = $surface . '|' . json_encode($context);
        if (array_key_exists($cacheKey, self::$registryCache)) {
            return self::$registryCache[$cacheKey];
        }

        $entries = self::loadStylesForSurface($surface, $context);
        // Dedupe by key, keeping highest order
        $deduped = [];
        foreach ($entries as $entry) {
            $key = (string)($entry['key'] ?? '');
            if ($key === '') {
                continue;
            }
            if (!isset($deduped[$key]) || (int)($entry['order'] ?? 0) > (int)($deduped[$key]['order'] ?? 0)) {
                $deduped[$key] = $entry;
            }
        }

        // Sort by order
        usort($deduped, static fn (array $a, array $b): int => (int)($a['order'] ?? 0) <=> (int)($b['order'] ?? 0));

        self::$registryCache[$cacheKey] = array_values($deduped);
        return self::$registryCache[$cacheKey];
    }

    /**
     * Return the cache-busted global Foundation/theme and explicit Shell files
     * needed by an isolated preview. The aggregate shell.css remains a source
     * import map, but is deliberately excluded from runtime and preview chains.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function previewChain(string $surface): array
    {
        $surface = strtolower(trim($surface));
        if ($surface === '') {
            return [];
        }

        $globals = array_values(array_filter(
            self::globals(),
            static fn (array $entry): bool => in_array(
                (string)($entry['key'] ?? ''),
                ['global.rendering-foundation', 'global.theme'],
                true
            )
        ));

        $manifestFile = APP_ROOT . '/apps/Shell/manifest.json';
        $manifest = is_file($manifestFile)
            ? json_decode((string)file_get_contents($manifestFile), true)
            : null;
        if (!is_array($manifest)) {
            return $globals;
        }

        $manifest['__install_path'] = APP_ROOT . '/apps/Shell';
        return array_merge($globals, self::stylesFromManifest($surface, 'shell', $manifest));
    }

    /**
     * Clear all caches (used in tests or after app hot-reload).
     */
    public static function clearCache(): void
    {
        self::$manifestCache = [];
        self::$registryCache = [];
    }

    /**
     * Load all styles registered for a specific surface from app manifests.
     * @param string $surface Surface identifier
     * @param array<string,mixed> $context Optional context for filtering
     * @return array<int,array<string,mixed>> Raw CSS entries (may contain duplicates by key)
     */
    private static function loadStylesForSurface(string $surface, array $context): array
    {
        $entries = [];
        $activeApps = self::activeAssignedApps($context);

        foreach ($activeApps as $appKey) {
            $manifest = self::loadAppManifest($appKey);
            if (!is_array($manifest)) {
                continue;
            }

            array_push($entries, ...self::stylesFromManifest($surface, $appKey, $manifest));
        }

        return $entries;
    }

    /**
     * @param array<string,mixed> $manifest
     * @return array<int,array<string,mixed>>
     */
    private static function stylesFromManifest(string $surface, string $appKey, array $manifest): array
    {
        $entries = [];
        foreach ((array)($manifest['styles'] ?? []) as $styleEntry) {
            if (!is_array($styleEntry) || ($styleEntry['runtime'] ?? true) === false) {
                continue;
            }

            if (!self::surfaceMatches($surface, $styleEntry)) {
                continue;
            }

            $resolved = self::resolveStyleEntry(
                $styleEntry,
                $appKey,
                (string)($manifest['__install_path'] ?? '')
            );
            if ($resolved !== null) {
                $entries[] = $resolved;
            }
        }

        usort($entries, static fn (array $a, array $b): int => (int)($a['order'] ?? 0) <=> (int)($b['order'] ?? 0));
        return $entries;
    }

    /**
     * Check if a style entry applies to the requested surface.
     * @param string $surface Surface name (admin, operator, work-entry, display, auth)
     * @param array<string,mixed> $entry Style entry from manifest
     * @return bool
     */
    private static function surfaceMatches(string $surface, array $entry): bool
    {
        $surfaces = (array)($entry['surfaces'] ?? []);
        return in_array(strtolower($surface), array_map('strtolower', $surfaces), true);
    }

    /**
     * Resolve a single style entry from manifest into a full CSS link entry.
     * @param array<string,mixed> $entry Style entry from manifest
     * @param string $appKey App key
     * @param string $installPath App install path
     * @return array<string,mixed>|null Resolved entry or null if invalid
     */
    private static function resolveStyleEntry(array $entry, string $appKey, string $installPath): ?array
    {
        $key = strtolower(trim((string)($entry['key'] ?? '')));
        $path = trim((string)($entry['path'] ?? ''));
        $scope = strtolower(trim((string)($entry['scope'] ?? 'app')));
        $order = (int)($entry['order'] ?? 200);

        if ($key === '' || $path === '') {
            return null;
        }

        $installPath = rtrim($installPath, '/');
        if ($installPath === '' || !is_dir($installPath)) {
            return null;
        }

        $absolutePath = $installPath . '/' . ltrim($path, '/');
        if (!is_file($absolutePath)) {
            return null;
        }

        // Determine URL path based on scope
        if ($scope === 'module') {
            $moduleKey = strtolower(trim((string)($entry['module'] ?? '')));
            if ($moduleKey === '') {
                return null;
            }
            $url = '/assets/apps/' . strtolower($appKey) . '/modules/' . $moduleKey . '/styles.css';
        } else {
            // app scope
            $url = '/assets/apps/' . strtolower($appKey) . '/styles/' . basename($path);
        }

        return [
            'key' => $key,
            'path' => $path,
            'url' => $url,
            'absolute_path' => $absolutePath,
            'scope' => $scope,
            'app_key' => $appKey,
            'module_key' => $scope === 'module' ? strtolower(trim((string)($entry['module'] ?? ''))) : null,
            'order' => $order,
            'version' => self::getFileVersion($absolutePath),
        ];
    }

    /**
     * Get filemtime version string for cache-busting.
     * @param string $filePath Absolute path to file (can be relative to APP_ROOT)
     * @return string File modification time as version
     */
    private static function getFileVersion(string $filePath): string
    {
        if (!str_starts_with($filePath, '/')) {
            $filePath = APP_ROOT . '/' . ltrim($filePath, '/');
        }
        return is_file($filePath) ? (string)filemtime($filePath) : '1';
    }

    /**
     * Get the list of active/assigned apps for the current context.
     * Always includes 'shell' since it's the core UI framework.
     * @param array<string,mixed> $context Optional context for filtering
     * @return array<int,string> List of lowercase app keys
     */
    private static function activeAssignedApps(array $context): array
    {
        $apps = array_map(
            static fn ($value): string => strtolower(trim((string)$value)),
            (array)($context['active_assigned_apps'] ?? $context['assigned_apps'] ?? [])
        );
        $apps = array_values(array_filter(array_unique($apps), static fn (string $value): bool => $value !== ''));

        // No user context (auth/display/kiosk surfaces): fall back to all enabled apps.
        if ($apps === []) {
            try {
                $rows = DB::fetchAll("SELECT app_key FROM core_apps WHERE LOWER(status) IN ('enabled','active')");
                foreach ($rows as $row) {
                    $key = strtolower(trim((string)($row['app_key'] ?? '')));
                    if ($key !== '' && !in_array($key, $apps, true)) {
                        $apps[] = $key;
                    }
                }
            } catch (\Throwable) {
                // ignore — shell still gets added below
            }
        }

        // Studio-generated apps live outside core_apps; enumerate them from disk.
        $generatedRoot = defined('APP_ROOT') ? rtrim((string)APP_ROOT, '/') . '/apps/Generated' : '';
        if ($generatedRoot !== '' && is_dir($generatedRoot)) {
            foreach ((glob($generatedRoot . '/*/manifest.json') ?: []) as $manifestFile) {
                $key = strtolower(trim(basename(dirname($manifestFile))));
                if ($key !== '' && !in_array($key, $apps, true)) {
                    $apps[] = $key;
                }
            }
        }

        // Shell app provides core UI framework styles — always include it.
        if (!in_array('shell', $apps, true)) {
            array_unshift($apps, 'shell');
        }

        return $apps;
    }

    /**
     * Load app manifest from DB or from file if bootstrapping.
     * @param string $appKey App key (lowercase)
     * @return array<string,mixed>|null Manifest array with __install_path added, or null if not found
     */
    private static function loadAppManifest(string $appKey): ?array
    {
        $key = strtolower(trim($appKey));
        if ($key === '') {
            return null;
        }

        if (array_key_exists($key, self::$manifestCache)) {
            return self::$manifestCache[$key];
        }

        // Load from core_apps table
        try {
            $row = DB::fetchOne('SELECT install_path, status FROM core_apps WHERE LOWER(app_key) = ? LIMIT 1', [$key]);
            if (is_array($row)) {
                $status = strtolower(trim((string)($row['status'] ?? 'inactive')));
                if (in_array($status, ['enabled', 'active'], true)) {
                    $installPath = trim((string)($row['install_path'] ?? ''));
                    if ($installPath !== '' && is_dir($installPath)) {
                        $manifestFile = rtrim($installPath, '/') . '/manifest.json';
                        if (is_file($manifestFile)) {
                            $manifest = json_decode(file_get_contents($manifestFile), true);
                            if (is_array($manifest)) {
                                $manifest['__install_path'] = $installPath;
                                self::$manifestCache[$key] = $manifest;
                                return $manifest;
                            }
                        }
                    }
                }
            }
        } catch (\Throwable) {
            // fall through to Generated lookup
        }

        // Fall back to Studio-generated app manifest (not in core_apps).
        if (defined('APP_ROOT')) {
            $generatedPath = rtrim((string)APP_ROOT, '/') . '/apps/Generated/' . $key;
            $manifestFile = $generatedPath . '/manifest.json';
            if (is_dir($generatedPath) && is_file($manifestFile)) {
                $manifest = json_decode((string)file_get_contents($manifestFile), true);
                if (is_array($manifest)) {
                    $manifest['__install_path'] = $generatedPath;
                    self::$manifestCache[$key] = $manifest;
                    return $manifest;
                }
            }
        }

        self::$manifestCache[$key] = null;
        return null;
    }
}

<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

final class StudioOwnerDiscoveryService
{
    public const PROFILE_CANONICAL = 'canonical';
    public const PROFILE_REPOSITORY_SCANNER = 'repository_scanner';
    public const PROFILE_OWNER_STRUCTURE = 'owner_structure';

    private const DEFAULT_OWNER = 'Manufacturing/Products';
    private const OWNER_FILES = [
        'manifest.json',
        'plugin.json',
        'routes.php',
        'menu.php',
        'navigation.php',
        'bootstrap.php',
    ];
    private const OWNER_DIRECTORIES = [
        'Controllers',
        'Services',
        'Views',
        'Resources',
        'migrations',
        'Database',
    ];
    private const REPOSITORY_SCANNER_FILES = [
        'manifest.json',
        'plugin.json',
        'routes.php',
        'bootstrap.php',
    ];
    private const REPOSITORY_SCANNER_DIRECTORIES = [
        'Controllers',
        'Services',
        'Views',
        'Resources',
    ];
    private const EXCLUDED_SEGMENTS = [
        '',
        '.git',
        'cache',
        'node_modules',
        'runtime',
        'storage',
        'tmp',
        'temp',
        'vendor',
    ];

    /**
     * @return array{root_path:string,owners:array<int,array<string,mixed>>,diagnostics:array<int,array<string,string>>}
     */
    public static function discover(?string $root = null, string $profile = self::PROFILE_CANONICAL): array
    {
        $requestedRoot = $root ?? (defined('APP_ROOT') ? (string)APP_ROOT : '');
        $realRoot = $requestedRoot !== '' ? realpath($requestedRoot) : false;
        if ($realRoot === false || !is_dir($realRoot)) {
            return [
                'root_path' => $requestedRoot,
                'owners' => [],
                'diagnostics' => [[
                    'code' => 'OWNER_ROOT_UNAVAILABLE',
                    'severity' => 'error',
                    'message' => 'Owner discovery root is unavailable.',
                    'owner_key' => '',
                    'path' => $requestedRoot,
                ]],
            ];
        }

        $rootPath = rtrim((string)$realRoot, DIRECTORY_SEPARATOR);
        $diagnostics = [];
        $ownersByKey = [];

        self::discoverApps($rootPath, $ownersByKey, $diagnostics);
        self::discoverPlugins($rootPath, $ownersByKey, $diagnostics);
        self::discoverPlatformRoot($rootPath, $ownersByKey, $diagnostics);
        self::discoverEngineeringWorkspaces($rootPath, $ownersByKey, $diagnostics);
        self::discoverGeneratedOwners($rootPath, $ownersByKey, $diagnostics);

        $owners = array_values($ownersByKey);
        usort($owners, static fn(array $left, array $right): int => strcasecmp(
            (string)($left['owner_key'] ?? ''),
            (string)($right['owner_key'] ?? '')
        ));

        foreach ($owners as &$owner) {
            unset($owner['_priority']);
        }
        unset($owner);

        return [
            'root_path' => $rootPath,
            'owners' => self::projectOwners($owners, $profile),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $owners
     * @return array<int,array<string,mixed>>
     */
    public static function projectOwners(array $owners, string $profile): array
    {
        if ($profile === self::PROFILE_CANONICAL) {
            return array_values($owners);
        }

        if ($profile === self::PROFILE_REPOSITORY_SCANNER) {
            return self::projectRepositoryScannerOwners($owners);
        }

        if ($profile === self::PROFILE_OWNER_STRUCTURE) {
            return self::projectOwnerStructureOwners($owners);
        }

        return [];
    }

    /**
     * @param array<int,array<string,mixed>> $owners
     * @return array<string,mixed>
     */
    public static function resolve(string $requestedOwnerKey, array $owners, string $defaultOwnerKey = ''): array
    {
        $index = [];
        foreach ($owners as $owner) {
            $key = (string)($owner['owner_key'] ?? '');
            if ($key !== '') {
                $index[$key] = $owner;
            }
        }

        $requested = trim($requestedOwnerKey);
        $default = isset($index[$defaultOwnerKey])
            ? $defaultOwnerKey
            : (string)array_key_first($index);

        if ($requested === '') {
            return self::resolutionResult($owners, null, '', $default, 'missing_owner_key');
        }

        if (!self::isValidOwnerKey($requested)) {
            return self::resolutionResult($owners, null, '', $default, 'invalid_owner_key');
        }

        if (!isset($index[$requested])) {
            return self::resolutionResult($owners, null, '', $default, 'unknown_owner_key');
        }

        return self::resolutionResult($owners, $index[$requested], $requested, $default, '');
    }

    public static function isValidOwnerKey(string $ownerKey): bool
    {
        $ownerKey = trim($ownerKey);
        if ($ownerKey === '' || str_starts_with($ownerKey, '/') || str_contains($ownerKey, '\\') || str_contains($ownerKey, '..')) {
            return false;
        }

        return preg_match('/^[A-Za-z][A-Za-z0-9_-]*(\/[A-Za-z][A-Za-z0-9_-]*){0,7}$/', $ownerKey) === 1;
    }

    /**
     * @param array<string,array<string,mixed>> $ownersByKey
     * @param array<int,array<string,string>> $diagnostics
     */
    private static function discoverApps(string $rootPath, array &$ownersByKey, array &$diagnostics): void
    {
        $appsRoot = $rootPath . DIRECTORY_SEPARATOR . 'apps';
        foreach (self::childDirectories($appsRoot, $diagnostics) as $appPath) {
            $appName = basename($appPath);
            $signals = self::ownerSignals($appPath);
            if (self::hasSignals($signals)) {
                self::addOwner(
                    $ownersByKey,
                    $diagnostics,
                    self::ownerRecord(
                        $appName,
                        $appPath,
                        $rootPath,
                        self::ownerTypeForApp($appName),
                        'apps',
                        '',
                        $signals,
                        100
                    )
                );
            }

            $modulesRoot = $appPath . DIRECTORY_SEPARATOR . 'modules';
            foreach (self::childDirectories($modulesRoot, $diagnostics) as $modulePath) {
                $moduleName = basename($modulePath);
                $moduleSignals = self::ownerSignals($modulePath);
                if (!self::hasSignals($moduleSignals)) {
                    continue;
                }
                self::addOwner(
                    $ownersByKey,
                    $diagnostics,
                    self::ownerRecord(
                        $appName . '/' . $moduleName,
                        $modulePath,
                        $rootPath,
                        'module',
                        'apps',
                        $appName,
                        $moduleSignals,
                        100
                    )
                );
            }
        }
    }

    /**
     * @param array<string,array<string,mixed>> $ownersByKey
     * @param array<int,array<string,string>> $diagnostics
     */
    private static function discoverPlugins(string $rootPath, array &$ownersByKey, array &$diagnostics): void
    {
        $pluginsRoot = $rootPath . DIRECTORY_SEPARATOR . 'plugins';
        foreach (self::childDirectories($pluginsRoot, $diagnostics) as $pluginPath) {
            $signals = self::ownerSignals($pluginPath);
            if (!self::hasSignals($signals)) {
                continue;
            }
            $pluginName = basename($pluginPath);
            self::addOwner(
                $ownersByKey,
                $diagnostics,
                self::ownerRecord(
                    'Plugin/' . $pluginName,
                    $pluginPath,
                    $rootPath,
                    'plugin',
                    'plugins',
                    '',
                    $signals,
                    90
                )
            );
        }
    }

    /**
     * @param array<string,array<string,mixed>> $ownersByKey
     * @param array<int,array<string,string>> $diagnostics
     */
    private static function discoverPlatformRoot(string $rootPath, array &$ownersByKey, array &$diagnostics): void
    {
        $platformPath = $rootPath . DIRECTORY_SEPARATOR . 'platform';
        if (!is_dir($platformPath)) {
            return;
        }

        self::addOwner(
            $ownersByKey,
            $diagnostics,
            self::ownerRecord(
                'Platform',
                $platformPath,
                $rootPath,
                'platform',
                'platform',
                '',
                self::ownerSignals($platformPath),
                50
            )
        );
    }

    /**
     * @param array<string,array<string,mixed>> $ownersByKey
     * @param array<int,array<string,string>> $diagnostics
     */
    private static function discoverEngineeringWorkspaces(string $rootPath, array &$ownersByKey, array &$diagnostics): void
    {
        $engineeringRoot = $rootPath . DIRECTORY_SEPARATOR . 'engineering';
        foreach (self::childDirectories($engineeringRoot, $diagnostics) as $workspacePath) {
            $workspaceName = basename($workspacePath);
            self::addEngineeringWorkspace(
                $workspacePath,
                $workspaceName,
                '',
                $rootPath,
                $ownersByKey,
                $diagnostics,
                true
            );
        }
    }

    /**
     * @param array<string,array<string,mixed>> $ownersByKey
     * @param array<int,array<string,string>> $diagnostics
     */
    private static function addEngineeringWorkspace(
        string $path,
        string $relativeKey,
        string $parentKey,
        string $rootPath,
        array &$ownersByKey,
        array &$diagnostics,
        bool $force
    ): void {
        $signals = self::ownerSignals($path);
        if (!$force && !self::hasSignals($signals)) {
            return;
        }

        $ownerKey = 'EW/' . str_replace('\\', '/', $relativeKey);
        self::addOwner(
            $ownersByKey,
            $diagnostics,
            self::ownerRecord(
                $ownerKey,
                $path,
                $rootPath,
                'engineering_workspace',
                'engineering',
                $parentKey,
                $signals,
                80
            )
        );

        foreach (self::childDirectories($path, $diagnostics) as $childPath) {
            $childName = basename($childPath);
            $childRelativeKey = $relativeKey . '/' . $childName;
            $childSignals = self::ownerSignals($childPath);
            if (!self::hasSignals($childSignals)) {
                continue;
            }
            self::addEngineeringWorkspace(
                $childPath,
                $childRelativeKey,
                $ownerKey,
                $rootPath,
                $ownersByKey,
                $diagnostics,
                false
            );
        }
    }

    /**
     * @param array<string,array<string,mixed>> $ownersByKey
     * @param array<int,array<string,string>> $diagnostics
     */
    private static function discoverGeneratedOwners(string $rootPath, array &$ownersByKey, array &$diagnostics): void
    {
        $generatedRoot = $rootPath . DIRECTORY_SEPARATOR . 'apps' . DIRECTORY_SEPARATOR . 'Generated';
        foreach (self::childDirectories($generatedRoot, $diagnostics) as $generatedAppPath) {
            $generatedAppName = basename($generatedAppPath);
            $appSignals = self::ownerSignals($generatedAppPath);
            $modulePaths = self::childDirectories($generatedAppPath, $diagnostics);
            if (!self::hasSignals($appSignals) && $modulePaths === []) {
                continue;
            }

            $appKey = 'Generated/' . $generatedAppName;
            self::addOwner(
                $ownersByKey,
                $diagnostics,
                self::ownerRecord(
                    $appKey,
                    $generatedAppPath,
                    $rootPath,
                    'generated',
                    'generated',
                    'Generated',
                    $appSignals,
                    95
                )
            );

            foreach ($modulePaths as $modulePath) {
                $moduleName = basename($modulePath);
                $moduleSignals = self::ownerSignals($modulePath);
                if (!self::hasSignals($moduleSignals)) {
                    continue;
                }
                self::addOwner(
                    $ownersByKey,
                    $diagnostics,
                    self::ownerRecord(
                        $appKey . '/' . $moduleName,
                        $modulePath,
                        $rootPath,
                        'module',
                        'generated',
                        $appKey,
                        $moduleSignals,
                        95
                    )
                );
            }
        }
    }

    /**
     * @param array<string,array<string,mixed>> $ownersByKey
     * @param array<int,array<string,string>> $diagnostics
     * @param array<string,mixed> $record
     */
    private static function addOwner(array &$ownersByKey, array &$diagnostics, array $record): void
    {
        $key = (string)($record['owner_key'] ?? '');
        if ($key === '') {
            return;
        }

        if (!isset($ownersByKey[$key])) {
            $ownersByKey[$key] = $record;
            return;
        }

        $existing = $ownersByKey[$key];
        $existingPriority = (int)($existing['_priority'] ?? 0);
        $newPriority = (int)($record['_priority'] ?? 0);
        if ($newPriority > $existingPriority) {
            $ownersByKey[$key] = $record;
        }

        $diagnostics[] = [
            'code' => 'OWNER_KEY_COLLISION',
            'severity' => 'warning',
            'message' => 'Multiple owner roots resolved to the same owner key; the higher-priority canonical root was retained.',
            'owner_key' => $key,
            'path' => (string)($record['relative_path'] ?? ''),
        ];
    }

    /**
     * @param array{files:array<int,string>,directories:array<int,string>} $signals
     * @return array<string,mixed>
     */
    private static function ownerRecord(
        string $ownerKey,
        string $path,
        string $rootPath,
        string $ownerType,
        string $source,
        string $parentOwnerKey,
        array $signals,
        int $priority
    ): array {
        $realPath = (string)(realpath($path) ?: $path);
        $relativePath = self::relativePath($realPath, $rootPath);

        return [
            'owner_key' => $ownerKey,
            'display_label' => self::displayLabel($ownerKey),
            'owner_type' => $ownerType,
            'source' => $source,
            'parent_owner_key' => $parentOwnerKey,
            'root_path' => $realPath,
            'relative_path' => $relativePath,
            'owner_root_path' => $realPath,
            'owner_root_relative_path' => $relativePath,
            'evidence_files' => $signals['files'],
            'evidence_directories' => $signals['directories'],
            '_priority' => $priority,
        ];
    }

    /**
     * @return array{files:array<int,string>,directories:array<int,string>}
     */
    private static function ownerSignals(string $path): array
    {
        $files = [];
        foreach (self::OWNER_FILES as $file) {
            if (is_file($path . DIRECTORY_SEPARATOR . $file)) {
                $files[] = $file;
            }
        }

        $directories = [];
        foreach (self::OWNER_DIRECTORIES as $directory) {
            if (is_dir($path . DIRECTORY_SEPARATOR . $directory)) {
                $directories[] = $directory;
            }
        }

        return ['files' => $files, 'directories' => $directories];
    }

    /**
     * @param array{files:array<int,string>,directories:array<int,string>} $signals
     */
    private static function hasSignals(array $signals): bool
    {
        return $signals['files'] !== [] || $signals['directories'] !== [];
    }

    /**
     * @param array<int,array<string,mixed>> $owners
     * @return array<int,array<string,mixed>>
     */
    private static function projectRepositoryScannerOwners(array $owners): array
    {
        $byKey = [];
        foreach ($owners as $owner) {
            $key = (string)($owner['owner_key'] ?? '');
            if ($key !== '') {
                $byKey[$key] = $owner;
            }
        }

        $projected = [];
        foreach ($owners as $owner) {
            $source = (string)($owner['source'] ?? '');
            $type = (string)($owner['owner_type'] ?? '');
            $include = false;

            if ($source === 'apps') {
                $include = self::matchesRepositoryScannerEvidence($owner);
                if ($include && $type === 'module') {
                    $parentKey = (string)($owner['parent_owner_key'] ?? '');
                    $include = isset($byKey[$parentKey]) && self::matchesRepositoryScannerEvidence($byKey[$parentKey]);
                }
            } elseif ($source === 'plugins') {
                $include = self::matchesRepositoryScannerEvidence($owner);
            } elseif ($source === 'platform' || $source === 'engineering') {
                $include = true;
            }

            if (!$include) {
                continue;
            }

            $projected[] = [
                'owner_key' => (string)$owner['owner_key'],
                'display_label' => (string)$owner['display_label'],
                'owner_type' => $type,
                'root_path' => (string)$owner['root_path'],
                'relative_path' => (string)$owner['relative_path'],
            ];
        }

        usort($projected, static fn(array $left, array $right): int => strcasecmp(
            (string)($left['owner_key'] ?? ''),
            (string)($right['owner_key'] ?? '')
        ));

        return $projected;
    }

    /**
     * @param array<string,mixed> $owner
     */
    private static function matchesRepositoryScannerEvidence(array $owner): bool
    {
        $files = array_values(array_intersect(
            (array)($owner['evidence_files'] ?? []),
            self::REPOSITORY_SCANNER_FILES
        ));
        $directories = array_values(array_intersect(
            (array)($owner['evidence_directories'] ?? []),
            self::REPOSITORY_SCANNER_DIRECTORIES
        ));

        return $files !== [] || $directories !== [];
    }

    /**
     * @param array<int,array<string,mixed>> $owners
     * @return array<int,array<string,mixed>>
     */
    private static function projectOwnerStructureOwners(array $owners): array
    {
        $projected = [];
        foreach ($owners as $owner) {
            if ((string)($owner['source'] ?? '') !== 'apps') {
                continue;
            }

            $type = (string)($owner['owner_type'] ?? '');
            if (!in_array($type, ['app', 'module', 'system', 'studio', 'generated'], true)) {
                continue;
            }

            $projected[] = [
                'owner_key' => (string)$owner['owner_key'],
                'display_label' => (string)$owner['display_label'],
                'owner_root_path' => (string)$owner['owner_root_path'],
                'owner_root_relative_path' => (string)$owner['owner_root_relative_path'],
                'owner_type' => $type,
            ];
        }

        usort($projected, static function (array $left, array $right): int {
            $leftKey = (string)($left['owner_key'] ?? '');
            $rightKey = (string)($right['owner_key'] ?? '');
            if ($leftKey === self::DEFAULT_OWNER) {
                return -1;
            }
            if ($rightKey === self::DEFAULT_OWNER) {
                return 1;
            }
            return strcasecmp($leftKey, $rightKey);
        });

        return $projected;
    }

    /**
     * @return array<int,string>
     */
    private static function childDirectories(string $path, array &$diagnostics): array
    {
        if (!is_dir($path)) {
            return [];
        }

        $entries = scandir($path);
        if ($entries === false) {
            $diagnostics[] = [
                'code' => 'OWNER_DIRECTORY_UNREADABLE',
                'severity' => 'warning',
                'message' => 'Owner discovery could not read a directory.',
                'owner_key' => '',
                'path' => $path,
            ];
            return [];
        }

        $directories = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || self::isExcludedSegment($entry)) {
                continue;
            }
            $child = $path . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($child) && !is_link($child)) {
                $directories[] = $child;
            }
        }

        natcasesort($directories);
        return array_values($directories);
    }

    private static function isExcludedSegment(string $segment): bool
    {
        return in_array(strtolower(trim($segment)), self::EXCLUDED_SEGMENTS, true);
    }

    private static function ownerTypeForApp(string $appName): string
    {
        $normalized = strtolower($appName);
        if ($normalized === 'studio') {
            return 'studio';
        }
        if (in_array($normalized, ['system', 'shell', 'platform'], true)) {
            return 'system';
        }
        if ($normalized === 'generated') {
            return 'generated';
        }
        return 'app';
    }

    private static function displayLabel(string $ownerKey): string
    {
        $segments = array_values(array_filter(explode('/', str_replace('\\', '/', $ownerKey)), 'strlen'));
        $labels = [];
        foreach ($segments as $segment) {
            if ($segment !== '' && strtoupper($segment) === $segment) {
                $labels[] = $segment;
                continue;
            }
            $labels[] = trim((string)preg_replace('/(?<!^)([A-Z])/', ' $1', $segment));
        }
        return implode(' / ', $labels);
    }

    private static function relativePath(string $path, string $rootPath): string
    {
        if ($path === $rootPath) {
            return '';
        }
        $prefix = rtrim($rootPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (str_starts_with($path, $prefix)) {
            return str_replace('\\', '/', substr($path, strlen($prefix)));
        }
        return str_replace('\\', '/', $path);
    }

    /**
     * @param array<int,array<string,mixed>> $owners
     * @param array<string,mixed>|null $selectedOwner
     * @return array<string,mixed>
     */
    private static function resolutionResult(
        array $owners,
        ?array $selectedOwner,
        string $selectedOwnerKey,
        string $defaultOwnerKey,
        string $errorCode
    ): array {
        return [
            'owners' => $owners,
            'selected_owner' => $selectedOwner,
            'selected_owner_key' => $selectedOwnerKey,
            'default_owner_key' => $defaultOwnerKey,
            'invalid_owner_key' => $errorCode !== '',
            'error_code' => $errorCode,
        ];
    }
}

<?php
declare(strict_types=1);

namespace Plugins\AdminTools\Services;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ModuleHealthReportService
{
    public static function generate(string $root): array
    {
        $root = rtrim($root, '/');
        $manifestPaths = glob($root . '/apps/*/modules/*/plugin.json') ?: [];
        sort($manifestPaths);

        $modules = [];
        foreach ($manifestPaths as $manifestPath) {
            $modules[] = self::inspectManifest($root, $manifestPath);
        }

        return [
            'generated_at' => date('c'),
            'summary' => self::summary($modules),
            'modules' => $modules,
        ];
    }

    private static function inspectManifest(string $root, string $manifestPath): array
    {
        $moduleDir = dirname($manifestPath);
        $manifest = json_decode((string)file_get_contents($manifestPath), true);
        if (!is_array($manifest)) {
            return [
                'module' => basename($moduleDir),
                'suite' => basename(dirname(dirname($moduleDir))),
                'module_type' => 'unknown',
                'target_maturity_level' => 'unknown',
                'score_percent' => 0,
                'band' => 'invalid_manifest',
                'missing' => ['valid_manifest'],
                'partial' => [],
                'capabilities' => [],
                'path' => self::relativePath($root, $moduleDir),
            ];
        }

        $declared = (array)($manifest['declared_capabilities'] ?? []);
        $moduleType = (string)($manifest['module_type'] ?? 'undeclared');
        $requiredForType = self::requiredCapabilitiesForType($moduleType);
        $capabilityScores = [];
        $missing = [];
        $partial = [];

        foreach ($declared as $capability) {
            $capability = (string)$capability;
            [$score, $reason] = self::scoreCapability($moduleDir, $manifest, $capability);
            $capabilityScores[$capability] = [
                'score' => $score,
                'reason' => $reason,
            ];
            if ($score === 0) {
                $missing[] = $capability;
            } elseif ($score === 1) {
                $partial[] = $capability;
            }
        }

        $maxScore = count($declared) * 2;
        $actualScore = array_sum(array_map(
            static fn(array $row): int => (int)$row['score'],
            $capabilityScores
        ));
        $scorePercent = $maxScore > 0 ? (int)round(($actualScore / $maxScore) * 100) : 0;

        $missingRequiredDeclarations = array_values(array_diff($requiredForType, $declared));
        $requiredCapabilityGaps = [];
        foreach ($requiredForType as $requiredCapability) {
            $score = (int)($capabilityScores[$requiredCapability]['score'] ?? 0);
            if ($score < 2) {
                $requiredCapabilityGaps[] = $requiredCapability;
            }
        }

        return [
            'module' => (string)($manifest['name'] ?? basename($moduleDir)),
            'suite' => (string)($manifest['suite'] ?? basename(dirname(dirname($moduleDir)))),
            'module_type' => $moduleType,
            'target_maturity_level' => (string)($manifest['target_maturity_level'] ?? 'undeclared'),
            'score_percent' => $scorePercent,
            'band' => self::bandForScore($scorePercent),
            'missing' => $missing,
            'partial' => $partial,
            'required_for_type' => $requiredForType,
            'missing_required_declarations' => $missingRequiredDeclarations,
            'required_capability_gaps' => $requiredCapabilityGaps,
            'capabilities' => $capabilityScores,
            'path' => self::relativePath($root, $moduleDir),
        ];
    }

    /**
     * @return array<int,string>
     */
    private static function requiredCapabilitiesForType(string $moduleType): array
    {
        return match ($moduleType) {
            'service_only' => ['services', 'lifecycle_hooks'],
            'governance' => ['services', 'permissions', 'lifecycle_hooks'],
            'integration' => ['routes', 'lifecycle_hooks'],
            'dashboard_only' => ['routes', 'views', 'permissions'],
            'business_entity' => ['routes', 'controllers', 'views', 'forms', 'schema', 'permissions'],
            'planning' => ['routes', 'controllers', 'views', 'schema', 'permissions', 'reports'],
            'process_execution' => ['routes', 'controllers', 'views', 'forms', 'schema', 'permissions', 'reports'],
            default => [],
        };
    }

    private static function summary(array $modules): array
    {
        $summary = [
            'total' => count($modules),
            'mature' => 0,
            'usable_with_tracked_gaps' => 0,
            'partial_module' => 0,
            'baby_module_or_shell' => 0,
            'undeclared_or_non_functional' => 0,
            'invalid_manifest' => 0,
        ];

        foreach ($modules as $module) {
            $band = (string)($module['band'] ?? '');
            if (array_key_exists($band, $summary)) {
                $summary[$band]++;
            }
        }

        return $summary;
    }

    private static function scoreCapability(string $moduleDir, array $manifest, string $capability): array
    {
        return match ($capability) {
            'routes' => self::fileScore($moduleDir . '/routes.php'),
            'views' => self::countFiles($moduleDir . '/Views', '*.php') > 0 ? [2, 'module views found'] : [0, 'no module views found'],
            'forms' => self::anyFileExists($moduleDir . '/Views', ['add.php', 'edit.php', 'form.php'])
                ? [2, 'form view found']
                : [0, 'no add/edit/form view found'],
            'schema' => self::hasSchema($moduleDir, $manifest),
            'migrations' => self::countFiles($moduleDir . '/migrations', '*.sql') > 0 ? [2, 'migration files found'] : [0, 'no migration files found'],
            'controllers' => self::countFiles($moduleDir . '/Controllers', '*.php') > 0 ? [2, 'controller files found'] : [0, 'no controller files found'],
            'services' => self::hasServices($moduleDir) ? [2, 'service files found'] : [0, 'no service files found'],
            'permissions' => self::hasPermissions($moduleDir) ? [2, 'policy or permission surface found'] : [1, 'permission surface not explicit'],
            'menus' => self::anyFileExists($moduleDir, ['menu.php', 'navigation.php']) ? [2, 'menu/navigation found'] : [0, 'no menu/navigation found'],
            'widgets' => self::countFiles($moduleDir . '/Services', '*WidgetRegistry.php') > 0 ? [2, 'widget registry found'] : [0, 'no widget registry found'],
            'charts' => self::hasNamedFile($moduleDir, 'chart') ? [2, 'chart file found'] : [1, 'chart capability not explicit'],
            'dashboard' => self::anyFileExists($moduleDir, ['dashboard.php']) || self::anyFileExists($moduleDir . '/Views', ['dashboard.php'])
                ? [2, 'dashboard surface found']
                : [0, 'no dashboard surface found'],
            'reports' => self::hasReport($moduleDir) ? [2, 'report or print surface found'] : [0, 'no report surface found'],
            'exports' => self::hasExport($moduleDir) ? [2, 'export/print/pdf surface found'] : [0, 'no export surface found'],
            'lifecycle_hooks' => self::hasLifecycleHooks($moduleDir) ? [2, 'lifecycle hooks found'] : [1, 'lifecycle hooks not explicit'],
            'localization' => self::hasLocalization($moduleDir) ? [2, 'module localization found'] : [0, 'no module localization found'],
            'dependencies' => !empty($manifest['requires']) ? [2, 'dependencies declared'] : [1, 'no dependencies declared'],
            default => [1, 'unknown capability'],
        };
    }

    private static function fileScore(string $path): array
    {
        return is_file($path) ? [2, 'file found'] : [0, 'file missing'];
    }

    private static function countFiles(string $dir, string $pattern): int
    {
        if (!is_dir($dir)) {
            return 0;
        }
        return count(glob(rtrim($dir, '/') . '/' . $pattern) ?: []);
    }

    private static function anyFileExists(string $dir, array $names): bool
    {
        foreach ($names as $name) {
            if (is_file(rtrim($dir, '/') . '/' . $name)) {
                return true;
            }
        }
        return false;
    }

    private static function hasSchema(string $moduleDir, array $manifest): array
    {
        if (self::countFiles($moduleDir . '/migrations', '*.sql') > 0) {
            return [2, 'migration files found'];
        }
        if (!empty($manifest['required_tables']) && is_array($manifest['required_tables'])) {
            return [1, 'required tables declared without module migration'];
        }
        if (self::hasNamedFile($moduleDir, 'Schema')) {
            return [1, 'schema service found without migration'];
        }
        return [0, 'no schema declaration found'];
    }

    private static function hasServices(string $moduleDir): bool
    {
        if (self::countFiles($moduleDir . '/Services', '*.php') > 0) {
            return true;
        }
        foreach (glob($moduleDir . '/*Service.php') ?: [] as $file) {
            if (is_file($file)) {
                return true;
            }
        }
        return false;
    }

    private static function hasPermissions(string $moduleDir): bool
    {
        foreach (glob($moduleDir . '/*Policies.php') ?: [] as $file) {
            if (is_file($file)) {
                return true;
            }
        }
        return self::hasNamedFile($moduleDir, 'Permission') || self::hasNamedFile($moduleDir, 'Policy');
    }

    private static function hasNamedFile(string $moduleDir, string $needle): bool
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($moduleDir, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }
            if (stripos($file->getFilename(), $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    private static function hasReport(string $moduleDir): bool
    {
        return self::hasNamedFile($moduleDir, 'report')
            || self::hasNamedFile($moduleDir, 'pdf')
            || self::hasNamedFile($moduleDir, 'print');
    }

    private static function hasExport(string $moduleDir): bool
    {
        return self::hasNamedFile($moduleDir, 'export')
            || self::hasNamedFile($moduleDir, 'pdf')
            || self::hasNamedFile($moduleDir, 'print');
    }

    private static function hasLifecycleHooks(string $moduleDir): bool
    {
        return self::anyFileExists($moduleDir, ['install.php', 'uninstall.php', 'update.php', 'bootstrap.php'])
            || self::hasNamedFile($moduleDir, 'Hooks');
    }

    private static function hasLocalization(string $moduleDir): bool
    {
        foreach (['lang', 'Lang', 'Locale', 'locale', 'locales'] as $dir) {
            if (is_dir($moduleDir . '/' . $dir)) {
                return true;
            }
        }
        return false;
    }

    private static function bandForScore(int $score): string
    {
        if ($score >= 90) {
            return 'mature';
        }
        if ($score >= 70) {
            return 'usable_with_tracked_gaps';
        }
        if ($score >= 40) {
            return 'partial_module';
        }
        if ($score > 0) {
            return 'baby_module_or_shell';
        }
        return 'undeclared_or_non_functional';
    }

    private static function relativePath(string $root, string $path): string
    {
        return ltrim(str_replace(rtrim($root, '/') . '/', '', $path), '/');
    }
}

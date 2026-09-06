<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\ReportDesigner\Services;

final class ReportDesignerSourceCatalogService
{
    /**
     * @return array{mode:string,warning:string,suites:array<string,array<string,mixed>>,diagnostics:array<int,string>}
     */
    public static function catalog(): array
    {
        $diagnostics = [];
        try {
            $tables = ReportDesignerDbDiscoveryService::tables();
        } catch (\Throwable $e) {
            $tables = [];
            $diagnostics[] = 'Database schema discovery is unavailable: ' . $e->getMessage();
        }

        $suites = [];
        foreach (glob(APP_ROOT . '/apps/*/manifest.json') ?: [] as $manifestPath) {
            $manifest = self::readManifest($manifestPath);
            if ($manifest === [] || strtolower((string)($manifest['type'] ?? '')) !== 'business') {
                continue;
            }

            $suiteKey = self::key((string)($manifest['app_key'] ?? $manifest['id'] ?? ''));
            if ($suiteKey === '') {
                continue;
            }

            $appTables = self::appTables(dirname($manifestPath), $tables);
            $sources = [];
            foreach (self::manifestSources($manifest, dirname($manifestPath)) as $source) {
                $matchedTables = self::matchTables(
                    $suiteKey,
                    (string)$source['key'],
                    (string)$source['label'],
                    $appTables
                );
                $sourceTables = [];
                foreach ($matchedTables as $table) {
                    try {
                        $columns = ReportDesignerDbDiscoveryService::columns($table);
                    } catch (\Throwable $e) {
                        $columns = [];
                        $diagnostics[] = 'Column discovery failed for ' . $table . ': ' . $e->getMessage();
                    }

                    if ($columns !== []) {
                        $sourceTables[$table] = [
                            'key' => $table,
                            'label' => self::label($table),
                            'columns' => $columns,
                        ];
                    }
                }

                if ($sourceTables === []) {
                    continue;
                }

                $source['tables'] = $sourceTables;
                $sources[(string)$source['key']] = $source;
            }

            if ($sources === []) {
                continue;
            }

            $suites[$suiteKey] = [
                'key' => $suiteKey,
                'label' => trim((string)($manifest['name'] ?? '')) ?: self::label($suiteKey),
                'sources' => $sources,
            ];
        }

        uasort($suites, static fn(array $left, array $right): int => strcasecmp(
            (string)($left['label'] ?? ''),
            (string)($right['label'] ?? '')
        ));

        return [
            'mode' => 'bootstrap_db_discovery',
            'warning' => 'Current sources are discovered from installed app/module metadata and database schema. This is a temporary bridge until business apps publish formal report-source providers.',
            'suites' => $suites,
            'diagnostics' => array_values(array_unique($diagnostics)),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function readManifest(string $path): array
    {
        $decoded = json_decode((string)@file_get_contents($path), true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string,mixed> $manifest
     * @return array<int,array{key:string,label:string,origin:string}>
     */
    private static function manifestSources(array $manifest, string $appPath): array
    {
        $sources = [];
        foreach ((array)($manifest['modules'] ?? []) as $module) {
            if (!is_array($module)) {
                continue;
            }
            self::addSource(
                $sources,
                (string)($module['key'] ?? $module['name'] ?? ''),
                (string)($module['name'] ?? $module['key'] ?? ''),
                'manifest'
            );
        }

        foreach (array_merge(
            (array)($manifest['native_modules'] ?? []),
            (array)($manifest['legacy_bridge_plugins'] ?? [])
        ) as $module) {
            self::addSource($sources, (string)$module, self::label((string)$module), 'manifest');
        }

        foreach (glob($appPath . '/modules/*', GLOB_ONLYDIR) ?: [] as $modulePath) {
            $name = basename($modulePath);
            self::addSource($sources, $name, self::label($name), 'module_directory');
        }

        uasort($sources, static fn(array $left, array $right): int => strcasecmp($left['label'], $right['label']));
        return array_values($sources);
    }

    /**
     * @param array<int,string> $liveTables
     * @return array<int,string>
     */
    private static function appTables(string $appPath, array $liveTables): array
    {
        $declared = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($appPath, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || !in_array(strtolower($file->getExtension()), ['php', 'sql'], true)) {
                continue;
            }

            $contents = @file_get_contents($file->getPathname());
            if (!is_string($contents) || $contents === '') {
                continue;
            }
            if (preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([A-Za-z0-9_]+)`?/i', $contents, $matches)) {
                foreach ((array)($matches[1] ?? []) as $table) {
                    $declared[(string)$table] = true;
                }
            }
        }

        return array_values(array_filter(
            $liveTables,
            static fn(string $table): bool => isset($declared[$table])
        ));
    }

    /**
     * @param array<string,array{key:string,label:string,origin:string}> $sources
     */
    private static function addSource(array &$sources, string $key, string $label, string $origin): void
    {
        $normalizedKey = self::key($key);
        if ($normalizedKey === '') {
            return;
        }

        if (!isset($sources[$normalizedKey]) || $origin === 'manifest') {
            $sources[$normalizedKey] = [
                'key' => $normalizedKey,
                'label' => trim($label) !== '' ? trim($label) : self::label($normalizedKey),
                'origin' => $origin,
            ];
        }
    }

    /**
     * @param array<int,string> $tables
     * @return array<int,string>
     */
    private static function matchTables(string $suiteKey, string $sourceKey, string $sourceLabel, array $tables): array
    {
        $source = self::key($sourceKey);
        $tokens = array_values(array_filter(
            array_unique(array_merge(self::tokens($source), self::tokens($sourceLabel))),
            static fn(string $token): bool => strlen($token) >= 3
                && !in_array($token, [
                    'app',
                    'module',
                    'management',
                    'workspace',
                    'system',
                    'entries',
                    'entry',
                    'plans',
                    'plan',
                    'orders',
                    'order',
                    'records',
                    'record',
                ], true)
        ));

        $scored = [];
        foreach ($tables as $table) {
            $tableKey = self::key($table);
            $tableTokens = self::tokens($tableKey);
            $score = 0;
            if ($source !== '' && $tableKey === $source) {
                $score += 120;
            }
            if ($source !== '' && (str_contains($tableKey, $source) || str_contains($source, $tableKey))) {
                $score += 70;
            }
            foreach ($tokens as $token) {
                foreach ($tableTokens as $tableToken) {
                    if ($token === $tableToken || self::singular($token) === self::singular($tableToken)) {
                        $score += 25;
                    }
                }
            }
            if (str_starts_with($tableKey, $suiteKey . '_') || str_starts_with($tableKey, 'mfg_')) {
                $score += 5;
            }
            if ($score >= 25) {
                $scored[$table] = $score;
            }
        }

        arsort($scored);
        return array_slice(array_keys($scored), 0, 8);
    }

    /**
     * @return array<int,string>
     */
    private static function tokens(string $value): array
    {
        $value = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $value) ?? $value;
        return array_values(array_filter(explode('_', self::key($value))));
    }

    private static function singular(string $value): string
    {
        return strlen($value) > 3 && str_ends_with($value, 's') ? substr($value, 0, -1) : $value;
    }

    private static function key(string $value): string
    {
        $value = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', trim($value)) ?? $value;
        $value = strtolower((string)preg_replace('/[^A-Za-z0-9]+/', '_', $value));
        return trim($value, '_');
    }

    private static function label(string $value): string
    {
        $value = preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', trim($value)) ?? $value;
        return ucwords(str_replace(['_', '-'], ' ', $value));
    }
}

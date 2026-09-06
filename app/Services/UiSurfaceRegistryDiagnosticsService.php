<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

final class UiSurfaceRegistryDiagnosticsService
{
    /**
     * @return array<string,mixed>
     */
    public function diagnosticsForApp(string $appKey): array
    {
        $appKey = strtolower(trim($appKey));
        if ($appKey === '') {
            return $this->emptyDiagnostics();
        }

        $row = DB::fetchOne('SELECT app_key, status, install_path, manifest_json FROM core_apps WHERE app_key=? LIMIT 1', [$appKey]);
        if (!is_array($row)) {
            return $this->emptyDiagnostics();
        }

        $manifest = $this->loadManifestPayload($row);
        $contract = is_array($manifest['runtime_contract'] ?? null) ? (array)$manifest['runtime_contract'] : [];

        $declaredNavContract = $this->navItemsFromManifestContract($contract);
        $navFromNavigation = $this->navItemsFromNavigationFile((string)($row['install_path'] ?? ''), $appKey);
        $navFromContract = $declaredNavContract !== [] ? $declaredNavContract : $navFromNavigation;

        $hookRows = DB::fetchAll(
            'SELECT hook_type, hook_key, payload_json, is_enabled FROM core_app_hooks WHERE app_key=? ORDER BY id ASC',
            [$appKey]
        );

        $surfaceRows = $this->surfaceRowsFromHooks($hookRows);
        $deprecatedAliases = $this->deprecatedAliasesFromHookRoutes($hookRows);
        $navigationAlignment = $this->navigationAlignment($declaredNavContract, $navFromContract, $navFromNavigation);

        $labelRefs = array_merge(
            $this->labelRefsFromNav($navFromContract, 'manifest_navigation_contract'),
            $this->labelRefsFromNav($navFromNavigation, 'navigation_php_contract'),
            $this->labelRefsFromSurfaces($surfaceRows),
            $this->labelRefsFromContractSurfaces($contract)
        );

        $missingLocaleKeys = $this->missingLocaleKeys($labelRefs);
        $duplicateNavigationOwnership = $this->duplicateNavigationOwnership($declaredNavContract, $navFromContract, $navFromNavigation, $surfaceRows);
        $sharedRouteReuse = $this->sharedRouteReuse($navFromContract, $surfaceRows);

        return [
            'summary' => [
                'app_key' => $appKey,
                'app_status' => (string)($row['status'] ?? 'unknown'),
                'nav_contract_items' => count($navFromContract),
                'navigation_php_items' => count($navFromNavigation),
                'surface_rows' => count($surfaceRows),
                'deprecated_ui_aliases' => count($deprecatedAliases),
                'missing_locale_keys' => count($missingLocaleKeys),
                'duplicate_declarations' => count($duplicateNavigationOwnership),
                'duplicate_navigation_ownership' => count($duplicateNavigationOwnership),
                'shared_route_reuse' => count($sharedRouteReuse),
                'navigation_contract_mismatches' => count((array)($navigationAlignment['mismatches'] ?? [])),
                'navigation_contract_source' => (string)($navigationAlignment['contract_source'] ?? 'navigation_php_projection'),
            ],
            'nav_contract_items' => $navFromContract,
            'navigation_php_items' => $navFromNavigation,
            'surface_rows' => $surfaceRows,
            'deprecated_aliases' => $deprecatedAliases,
            'navigation_alignment' => $navigationAlignment,
            'missing_locale_keys' => $missingLocaleKeys,
            'duplicate_declarations' => $duplicateNavigationOwnership,
            'duplicate_navigation_ownership' => $duplicateNavigationOwnership,
            'shared_route_reuse' => $sharedRouteReuse,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function emptyDiagnostics(): array
    {
        return [
            'summary' => [
                'app_key' => '',
                'app_status' => 'unknown',
                'nav_contract_items' => 0,
                'navigation_php_items' => 0,
                'surface_rows' => 0,
                'deprecated_ui_aliases' => 0,
                'missing_locale_keys' => 0,
                'duplicate_declarations' => 0,
                'duplicate_navigation_ownership' => 0,
                'shared_route_reuse' => 0,
                'navigation_contract_mismatches' => 0,
                'navigation_contract_source' => 'navigation_php_projection',
            ],
            'nav_contract_items' => [],
            'navigation_php_items' => [],
            'surface_rows' => [],
            'deprecated_aliases' => [],
            'navigation_alignment' => ['contract_source' => 'navigation_php_projection', 'mismatches' => []],
            'missing_locale_keys' => [],
            'duplicate_declarations' => [],
            'duplicate_navigation_ownership' => [],
            'shared_route_reuse' => [],
        ];
    }

    /**
     * @param array<string,mixed> $contract
     * @return array<int,array<string,mixed>>
     */
    private function navItemsFromManifestContract(array $contract): array
    {
        $items = [];
        foreach ((array)($contract['navigation_items'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $key = trim((string)($item['key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $items[] = [
                'source' => 'manifest_navigation_contract',
                'key' => $key,
                'feature_key' => trim((string)($item['feature_key'] ?? '')),
                'label_key' => trim((string)($item['label_key'] ?? '')),
                'label' => trim((string)($item['label'] ?? '')),
                'url' => trim((string)($item['url'] ?? '')),
                'role_visibility' => array_values(array_map('strval', (array)($item['role_visibility'] ?? []))),
                'nav_visible' => true,
                'owner_app' => trim((string)($item['owner_app'] ?? '')),
            ];
        }

        usort($items, static function (array $a, array $b): int {
            return strcmp((string)($a['key'] ?? ''), (string)($b['key'] ?? ''));
        });

        return $items;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function navItemsFromNavigationFile(string $installPath, string $appKey): array
    {
        $file = rtrim($installPath, '/') . '/navigation.php';
        if (!is_file($file)) {
            return [];
        }

        try {
            $payload = require $file;
        } catch (\Throwable $e) {
            return [];
        }

        if (!is_array($payload)) {
            return [];
        }

        $items = [];
        foreach ((array)($payload['items'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $key = trim((string)($item['key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $items[] = [
                'source' => 'navigation_php_contract',
                'key' => $key,
                'feature_key' => trim((string)($item['feature_key'] ?? '')),
                'label_key' => trim((string)($item['label_key'] ?? '')),
                'label' => trim((string)($item['label'] ?? '')),
                'url' => trim((string)($item['url'] ?? '')),
                'role_visibility' => $this->normalizeRoleVisibility((string)($item['visible_if'] ?? '')),
                'source_key' => trim((string)($item['source_key'] ?? '')),
                'owner' => trim((string)($item['owner'] ?? $appKey)),
                'nav_visible' => true,
                'owner_app' => $appKey,
            ];
        }

        usort($items, static function (array $a, array $b): int {
            return strcmp((string)($a['key'] ?? ''), (string)($b['key'] ?? ''));
        });

        return $items;
    }

    /**
     * @param array<int,array<string,mixed>> $hookRows
     * @return array<int,array<string,mixed>>
     */
    private function surfaceRowsFromHooks(array $hookRows): array
    {
        $types = ['widget', 'chart', 'dashboard', 'search_entry', 'menu'];
        $rows = [];

        foreach ($hookRows as $row) {
            $type = trim((string)($row['hook_type'] ?? ''));
            if (!in_array($type, $types, true)) {
                continue;
            }

            $payload = json_decode((string)($row['payload_json'] ?? '{}'), true);
            $payload = is_array($payload) ? $payload : [];
            $key = trim((string)($payload['key'] ?? $row['hook_key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $rows[] = [
                'surface_type' => $type,
                'key' => $key,
                'label_key' => trim((string)($payload['label_key'] ?? ($payload['title_key'] ?? ''))),
                'label' => trim((string)($payload['label'] ?? ($payload['title'] ?? ''))),
                'url' => trim((string)($payload['url'] ?? '')),
                'feature_key' => trim((string)($payload['feature_key'] ?? '')),
                'nav_visible' => (bool)($payload['nav_visible'] ?? false),
                'search_visible' => (bool)($payload['search_visible'] ?? false),
                'enabled' => ((int)($row['is_enabled'] ?? 0) === 1),
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $left = (string)($a['surface_type'] ?? '') . '|' . (string)($a['key'] ?? '');
            $right = (string)($b['surface_type'] ?? '') . '|' . (string)($b['key'] ?? '');
            return strcmp($left, $right);
        });

        return $rows;
    }

    /**
     * @param array<int,array<string,mixed>> $hookRows
     * @return array<int,array<string,mixed>>
     */
    private function deprecatedAliasesFromHookRoutes(array $hookRows): array
    {
        $aliases = [];

        foreach ($hookRows as $row) {
            if (trim((string)($row['hook_type'] ?? '')) !== 'route') {
                continue;
            }

            $payload = json_decode((string)($row['payload_json'] ?? '{}'), true);
            if (!is_array($payload)) {
                continue;
            }

            $kind = strtolower(trim((string)($payload['kind'] ?? ((string)($payload['compat_redirect'] ?? '') !== '' ? 'alias' : 'canonical'))));
            if ($kind !== 'alias') {
                continue;
            }

            if (!(bool)($payload['deprecated'] ?? false)) {
                continue;
            }

            $aliases[] = [
                'path' => trim((string)($payload['path'] ?? $row['hook_key'] ?? '')),
                'canonical_target' => trim((string)($payload['canonical_target'] ?? ($payload['compat_redirect'] ?? ''))),
                'feature_key' => trim((string)($payload['feature_key'] ?? '')),
                'compatibility' => (bool)($payload['compatibility'] ?? true),
                'enabled' => ((int)($row['is_enabled'] ?? 0) === 1),
            ];
        }

        usort($aliases, static function (array $a, array $b): int {
            return strcmp((string)($a['path'] ?? ''), (string)($b['path'] ?? ''));
        });

        return $aliases;
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @return array<int,array<string,mixed>>
     */
    private function labelRefsFromNav(array $items, string $domain): array
    {
        $refs = [];
        foreach ($items as $item) {
            $labelKey = trim((string)($item['label_key'] ?? ''));
            if ($labelKey === '') {
                continue;
            }
            $refs[] = [
                'domain' => $domain,
                'type' => 'nav_item',
                'key' => (string)($item['key'] ?? ''),
                'label_key' => $labelKey,
            ];
        }
        return $refs;
    }

    /**
     * @param array<int,array<string,mixed>> $surfaceRows
     * @return array<int,array<string,mixed>>
     */
    private function labelRefsFromSurfaces(array $surfaceRows): array
    {
        $refs = [];
        foreach ($surfaceRows as $row) {
            $labelKey = trim((string)($row['label_key'] ?? ''));
            if ($labelKey === '') {
                continue;
            }
            $refs[] = [
                'domain' => 'runtime_hooks',
                'type' => (string)($row['surface_type'] ?? 'surface'),
                'key' => (string)($row['key'] ?? ''),
                'label_key' => $labelKey,
            ];
        }
        return $refs;
    }

    /**
     * @param array<string,mixed> $contract
     * @return array<int,array<string,mixed>>
     */
    private function labelRefsFromContractSurfaces(array $contract): array
    {
        $refs = [];
        foreach (['widgets', 'charts', 'dashboards', 'search_entries'] as $bucket) {
            foreach ((array)($contract[$bucket] ?? []) as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $labelKey = trim((string)($entry['label_key'] ?? ''));
                if ($labelKey === '') {
                    continue;
                }
                $refs[] = [
                    'domain' => 'manifest_runtime_contract',
                    'type' => $bucket,
                    'key' => trim((string)($entry['key'] ?? '')),
                    'label_key' => $labelKey,
                ];
            }
        }
        return $refs;
    }

    /**
     * @param array<int,array<string,mixed>> $labelRefs
     * @return array<int,array<string,mixed>>
     */
    private function missingLocaleKeys(array $labelRefs): array
    {
        $en = $this->loadLocale('en');
        $ja = $this->loadLocale('ja');

        $missing = [];
        $seen = [];
        foreach ($labelRefs as $ref) {
            $labelKey = trim((string)($ref['label_key'] ?? ''));
            if ($labelKey === '') {
                continue;
            }

            $seenKey = strtolower((string)($ref['domain'] ?? '') . '|' . (string)($ref['type'] ?? '') . '|' . (string)($ref['key'] ?? '') . '|' . $labelKey);
            if (isset($seen[$seenKey])) {
                continue;
            }
            $seen[$seenKey] = true;

            $missingEn = !array_key_exists($labelKey, $en);
            $missingJa = !array_key_exists($labelKey, $ja);
            if (!$missingEn && !$missingJa) {
                continue;
            }

            $missing[] = [
                'domain' => (string)($ref['domain'] ?? ''),
                'type' => (string)($ref['type'] ?? ''),
                'key' => (string)($ref['key'] ?? ''),
                'label_key' => $labelKey,
                'missing_en' => $missingEn,
                'missing_ja' => $missingJa,
            ];
        }

        usort($missing, static function (array $a, array $b): int {
            $left = (string)($a['domain'] ?? '') . '|' . (string)($a['type'] ?? '') . '|' . (string)($a['key'] ?? '') . '|' . (string)($a['label_key'] ?? '');
            $right = (string)($b['domain'] ?? '') . '|' . (string)($b['type'] ?? '') . '|' . (string)($b['key'] ?? '') . '|' . (string)($b['label_key'] ?? '');
            return strcmp($left, $right);
        });

        return $missing;
    }

    /**
     * @param array<int,array<string,mixed>> $navContract
     * @param array<int,array<string,mixed>> $navPhp
     * @param array<int,array<string,mixed>> $surfaceRows
     * @return array<int,array<string,mixed>>
     */
    private function duplicateNavigationOwnership(array $declaredNavContract, array $navContract, array $navPhp, array $surfaceRows): array
    {
        $duplicates = [];
        $rows = [];

        if ($declaredNavContract !== []) {
            foreach ($navContract as $item) {
                $rows[] = $this->normalizeOwnershipRow($item, 'navigation_owner', (string)($item['source'] ?? 'manifest_navigation_contract'));
            }
        }

        foreach ($navPhp as $item) {
            $rows[] = $this->normalizeOwnershipRow($item, 'navigation_owner', 'navigation_php_contract');
        }

        $byKey = [];
        $byUrl = [];
        foreach ($rows as $row) {
            $key = strtolower(trim((string)($row['key'] ?? '')));
            $url = strtolower(trim((string)($row['url'] ?? '')));
            if ($key !== '') {
                $byKey[$key][] = $row;
            }
            if ($url !== '' && str_starts_with($url, '/')) {
                $byUrl[$url][] = $row;
            }
        }

        foreach ($byKey as $key => $entries) {
            if (count($entries) < 2) {
                continue;
            }
            $duplicates[] = [
                'composite_key' => 'navigation_key|' . $key,
                'count' => count($entries),
                'entries' => $entries,
            ];
        }

        foreach ($byUrl as $url => $entries) {
            $uniqueIntent = [];
            foreach ($entries as $entry) {
                $uniqueIntent[(string)($entry['source'] ?? '') . '|' . (string)($entry['key'] ?? '')] = true;
            }
            if (count($uniqueIntent) < 2) {
                continue;
            }
            $duplicates[] = [
                'composite_key' => 'navigation_url|' . $url,
                'count' => count($entries),
                'entries' => $entries,
            ];
        }

        usort($duplicates, static function (array $a, array $b): int {
            return strcmp((string)($a['composite_key'] ?? ''), (string)($b['composite_key'] ?? ''));
        });

        return $duplicates;
    }

    /**
     * @param array<int,array<string,mixed>> $navContract
     * @param array<int,array<string,mixed>> $surfaceRows
     * @return array<int,array<string,mixed>>
     */
    private function sharedRouteReuse(array $navContract, array $surfaceRows): array
    {
        $rows = [];
        foreach ($navContract as $item) {
            $rows[] = $this->normalizeOwnershipRow($item, 'navigation_owner', (string)($item['source'] ?? 'manifest_navigation_contract'));
        }
        foreach ($surfaceRows as $row) {
            $bucket = $this->surfaceBucket((string)($row['surface_type'] ?? ''));
            if ($bucket === 'compat_alias' || $bucket === 'navigation_owner') {
                continue;
            }
            $rows[] = $this->normalizeOwnershipRow($row, $bucket, (string)($row['surface_type'] ?? 'surface'));
        }

        $byUrl = [];
        foreach ($rows as $row) {
            $url = strtolower(trim((string)($row['url'] ?? '')));
            if ($url === '' || !str_starts_with($url, '/')) {
                continue;
            }
            $byUrl[$url][] = $row;
        }

        $reuse = [];
        foreach ($byUrl as $url => $entries) {
            $buckets = [];
            foreach ($entries as $entry) {
                $buckets[(string)($entry['bucket'] ?? 'unknown')] = true;
            }
            if (count($entries) < 2 || count($buckets) < 2) {
                continue;
            }
            $reuse[] = [
                'composite_key' => 'shared_url|' . $url,
                'count' => count($entries),
                'entries' => $entries,
            ];
        }

        usort($reuse, static function (array $a, array $b): int {
            return strcmp((string)($a['composite_key'] ?? ''), (string)($b['composite_key'] ?? ''));
        });

        return $reuse;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function normalizeOwnershipRow(array $row, string $bucket, string $source): array
    {
        return [
            'bucket' => $bucket,
            'source' => $source,
            'key' => trim((string)($row['key'] ?? '')),
            'feature_key' => trim((string)($row['feature_key'] ?? '')),
            'url' => trim((string)($row['url'] ?? '')),
            'surface_type' => trim((string)($row['surface_type'] ?? 'nav_item')),
        ];
    }

    private function surfaceBucket(string $surfaceType): string
    {
        return match (strtolower(trim($surfaceType))) {
            'menu' => 'shortcut_surface',
            'widget', 'dashboard' => 'shortcut_surface',
            'chart' => 'analytics_surface',
            'search_entry' => 'search_surface',
            'route_alias' => 'compat_alias',
            default => 'other_surface',
        };
    }

    /**
     * @param array<int,array<string,mixed>> $declaredNavContract
     * @param array<int,array<string,mixed>> $canonicalNavContract
     * @param array<int,array<string,mixed>> $navPhp
     * @return array<string,mixed>
     */
    private function navigationAlignment(array $declaredNavContract, array $canonicalNavContract, array $navPhp): array
    {
        $contractSource = $declaredNavContract !== [] ? 'manifest_navigation_contract' : 'navigation_php_projection';
        if ($declaredNavContract === []) {
            return [
                'contract_source' => $contractSource,
                'mismatches' => [],
            ];
        }

        $contractIndex = $this->navigationIndex($canonicalNavContract);
        $navIndex = $this->navigationIndex($navPhp);
        $keys = array_values(array_unique(array_merge(array_keys($contractIndex), array_keys($navIndex))));
        sort($keys, SORT_STRING);

        $mismatches = [];
        foreach ($keys as $key) {
            $contractRow = $contractIndex[$key] ?? null;
            $navRow = $navIndex[$key] ?? null;
            if (!is_array($contractRow) || !is_array($navRow)) {
                $mismatches[] = [
                    'key' => $key,
                    'reason' => !is_array($contractRow) ? 'missing_from_contract' : 'missing_from_navigation_php',
                    'contract_url' => (string)($contractRow['url'] ?? ''),
                    'navigation_php_url' => (string)($navRow['url'] ?? ''),
                ];
                continue;
            }

            if (
                (string)($contractRow['url'] ?? '') !== (string)($navRow['url'] ?? '')
                || (string)($contractRow['feature_key'] ?? '') !== (string)($navRow['feature_key'] ?? '')
            ) {
                $mismatches[] = [
                    'key' => $key,
                    'reason' => 'ownership_contract_mismatch',
                    'contract_url' => (string)($contractRow['url'] ?? ''),
                    'navigation_php_url' => (string)($navRow['url'] ?? ''),
                ];
            }
        }

        return [
            'contract_source' => $contractSource,
            'mismatches' => $mismatches,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @return array<string,array<string,mixed>>
     */
    private function navigationIndex(array $items): array
    {
        $index = [];
        foreach ($items as $item) {
            $key = strtolower(trim((string)($item['key'] ?? '')));
            if ($key === '') {
                continue;
            }
            $index[$key] = $item;
        }
        return $index;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function loadManifestPayload(array $row): array
    {
        $installPath = rtrim((string)($row['install_path'] ?? ''), '/');
        $manifestPath = $installPath !== '' ? $installPath . '/manifest.json' : '';
        if ($manifestPath !== '' && is_file($manifestPath)) {
            try {
                $payload = json_decode((string)file_get_contents($manifestPath), true);
                if (is_array($payload)) {
                    return $payload;
                }
            } catch (\Throwable $e) {
            }
        }

        $manifest = json_decode((string)($row['manifest_json'] ?? '{}'), true);
        return is_array($manifest) ? $manifest : [];
    }

    /**
     * @return array<string,string>
     */
    private function loadLocale(string $lang): array
    {
        $lang = strtolower(trim($lang));
        if (!in_array($lang, ['en', 'ja'], true)) {
            return [];
        }

        $file = APP_ROOT . '/app/Locale/' . $lang . '.php';
        if (!is_file($file)) {
            return [];
        }

        try {
            $payload = require $file;
        } catch (\Throwable $e) {
            return [];
        }

        if (!is_array($payload)) {
            return [];
        }

        $out = [];
        foreach ($payload as $k => $v) {
            $key = trim((string)$k);
            if ($key === '') {
                continue;
            }
            $out[$key] = (string)$v;
        }
        return $out;
    }

    /**
     * @return array<int,string>
     */
    private function normalizeRoleVisibility(string $visibleIf): array
    {
        $visibleIf = trim($visibleIf);
        if ($visibleIf === '' || in_array($visibleIf, ['always', 'logged_in'], true)) {
            return [];
        }
        return [$visibleIf];
    }
}

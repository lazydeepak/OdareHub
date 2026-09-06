<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

use App\Core\RouteRuntimeAuthority;

final class StudioNavCandidateProviderService
{
    /** @var array<int,string> */
    private const CONTRACT_FIELDS = [
        'candidate_id',
        'owner_app',
        'owner_module',
        'source_type',
        'source_key',
        'label',
        'route_path',
        'route_name',
        'current_nav_key',
        'current_url',
        'proposed_url',
        'permission_key',
        'visibility',
        'status',
        'diagnostics',
    ];

    /**
     * @return array<int,string>
     */
    public static function contractFields(): array
    {
        return self::CONTRACT_FIELDS;
    }

    /**
     * @param array<string,array<string,callable>> $routeMap
     * @return array<string,mixed>
     */
    public static function listCandidates(array $routeMap): array
    {
        RouteRuntimeAuthority::seed($routeMap);
        $diagnostics = RouteRuntimeAuthority::diagnostics();
        return self::buildCandidatesFromDiagnostics($diagnostics);
    }

    /**
     * @param array<string,mixed> $diagnostics
     * @return array<string,mixed>
     */
    public static function buildCandidatesFromDiagnostics(array $diagnostics): array
    {
        $linkedRows = array_values(array_filter((array)($diagnostics['linked_routes'] ?? []), 'is_array'));
        $declaredRows = array_values(array_filter((array)($diagnostics['declared_routes'] ?? []), 'is_array'));
        $contractRows = array_values(array_filter((array)($diagnostics['route_contracts'] ?? []), 'is_array'));

        $candidates = [];
        $pathFrequency = [];
        $coveredPaths = [];

        foreach ($linkedRows as $row) {
            $sourceKey = trim((string)($row['source_key'] ?? ''));
            $sourceHint = self::parseSourceKey($sourceKey);

            $ownerApp = trim((string)$sourceHint['owner_app']);
            if ($ownerApp === '') {
                $ownerApp = trim((string)($row['owner_key'] ?? ''));
            }

            $ownerModule = trim((string)$sourceHint['owner_module']);
            $currentUrl = self::normalizePath((string)($row['url'] ?? ''));
            $runtimeUrl = self::normalizePath((string)($row['runtime_url'] ?? ''));
            if ($runtimeUrl === '') {
                $runtimeUrl = $currentUrl;
            }

            $statusRaw = trim((string)($row['status'] ?? ''));
            $status = self::mapLinkedStatus($statusRaw);

            $candidate = self::createCandidate([
                'candidate_id' => 'linked:' . substr(sha1(implode('|', [
                    trim((string)($row['owner_type'] ?? '')),
                    trim((string)($row['owner_key'] ?? '')),
                    $sourceKey,
                    $currentUrl,
                    $runtimeUrl,
                ])), 0, 16),
                'owner_app' => $ownerApp,
                'owner_module' => $ownerModule,
                'source_type' => 'navigation_php',
                'source_key' => $sourceKey !== '' ? $sourceKey : trim((string)($row['key'] ?? '')),
                'label' => trim((string)($row['label'] ?? '')),
                'route_path' => $runtimeUrl,
                'route_name' => '',
                'current_nav_key' => trim((string)($row['key'] ?? '')),
                'current_url' => $currentUrl,
                'proposed_url' => $runtimeUrl,
                'permission_key' => '',
                'visibility' => trim((string)($row['visible_if'] ?? 'always')),
                'status' => $status,
                'diagnostics' => self::buildLinkedDiagnostics($statusRaw, $row),
            ]);

            $keyPath = $candidate['proposed_url'] !== '' ? $candidate['proposed_url'] : $candidate['current_url'];
            if ($keyPath !== '') {
                $pathFrequency[$keyPath] = (int)($pathFrequency[$keyPath] ?? 0) + 1;
                $coveredPaths[$keyPath] = true;
            }

            $candidates[] = $candidate;
        }

        foreach ($candidates as &$candidate) {
            $keyPath = $candidate['proposed_url'] !== '' ? $candidate['proposed_url'] : $candidate['current_url'];
            if ($keyPath !== '' && (int)($pathFrequency[$keyPath] ?? 0) > 1) {
                $candidate['status'] = 'ambiguous';
                $candidate['diagnostics'][] = 'multiple_navigation_entries_for_same_route';
            }
        }
        unset($candidate);

        foreach ($declaredRows as $row) {
            $path = self::normalizePath((string)($row['path'] ?? ''));
            if ($path === '') {
                continue;
            }
            if (isset($coveredPaths[$path])) {
                continue;
            }

            $declaredStatus = trim((string)($row['status'] ?? 'declared_missing'));
            $candidateStatus = $declaredStatus === 'disabled_by_app_status' ? 'blocked' : 'missing_nav';

            $candidates[] = self::createCandidate([
                'candidate_id' => 'declared:' . substr(sha1(implode('|', [
                    trim((string)($row['app_key'] ?? '')),
                    $path,
                ])), 0, 16),
                'owner_app' => trim((string)($row['app_key'] ?? '')),
                'owner_module' => '',
                'source_type' => 'declared_route',
                'source_key' => trim((string)($row['app_key'] ?? '')) . ':' . $path,
                'label' => $path,
                'route_path' => $path,
                'route_name' => '',
                'current_nav_key' => '',
                'current_url' => '',
                'proposed_url' => $path,
                'permission_key' => '',
                'visibility' => 'always',
                'status' => $candidateStatus,
                'diagnostics' => ['declared_route_status:' . $declaredStatus],
            ]);
            $coveredPaths[$path] = true;
        }

        foreach ($contractRows as $row) {
            $path = self::normalizePath((string)($row['path'] ?? ''));
            if ($path === '') {
                continue;
            }
            if (isset($coveredPaths[$path])) {
                continue;
            }

            $appStatus = strtolower(trim((string)($row['app_status'] ?? 'unknown')));
            $hookEnabled = (bool)($row['hook_enabled'] ?? false);
            $candidateStatus = ($appStatus !== 'enabled' || !$hookEnabled) ? 'blocked' : 'missing_nav';

            $visibility = 'always';
            if (is_array($row['role_visibility'] ?? null) && $row['role_visibility'] !== []) {
                $visibility = 'roles:' . implode(',', array_map('strval', (array)$row['role_visibility']));
            }

            $diagnosticsList = [
                'contract_kind:' . trim((string)($row['kind'] ?? 'canonical')),
                'app_status:' . $appStatus,
                'hook_enabled:' . ($hookEnabled ? '1' : '0'),
            ];
            if (!empty($row['compatibility'])) {
                $diagnosticsList[] = 'compatibility_route';
            }
            if (!empty($row['deprecated'])) {
                $diagnosticsList[] = 'deprecated_route';
            }

            $featureKey = trim((string)($row['feature_key'] ?? ''));
            $sourceKey = $featureKey !== '' ? $featureKey : $path;

            $candidates[] = self::createCandidate([
                'candidate_id' => 'contract:' . substr(sha1(implode('|', [
                    trim((string)($row['app_key'] ?? '')),
                    $path,
                    $sourceKey,
                ])), 0, 16),
                'owner_app' => trim((string)($row['owner_app'] ?? $row['app_key'] ?? '')),
                'owner_module' => '',
                'source_type' => 'route_contract',
                'source_key' => $sourceKey,
                'label' => $featureKey !== '' ? $featureKey : $path,
                'route_path' => $path,
                'route_name' => $featureKey,
                'current_nav_key' => '',
                'current_url' => '',
                'proposed_url' => $path,
                'permission_key' => '',
                'visibility' => $visibility,
                'status' => $candidateStatus,
                'diagnostics' => $diagnosticsList,
            ]);
            $coveredPaths[$path] = true;
        }

        usort($candidates, static function (array $a, array $b): int {
            $left = implode('|', [
                (string)($a['owner_app'] ?? ''),
                (string)($a['owner_module'] ?? ''),
                (string)($a['source_type'] ?? ''),
                (string)($a['source_key'] ?? ''),
                (string)($a['proposed_url'] ?? ''),
            ]);
            $right = implode('|', [
                (string)($b['owner_app'] ?? ''),
                (string)($b['owner_module'] ?? ''),
                (string)($b['source_type'] ?? ''),
                (string)($b['source_key'] ?? ''),
                (string)($b['proposed_url'] ?? ''),
            ]);
            return strcmp($left, $right);
        });

        $counts = [
            'total' => count($candidates),
            'linked' => 0,
            'missing_nav' => 0,
            'mismatch' => 0,
            'ambiguous' => 0,
            'blocked' => 0,
        ];

        foreach ($candidates as $candidate) {
            $status = trim((string)($candidate['status'] ?? ''));
            if (isset($counts[$status])) {
                $counts[$status]++;
            }
        }

        return [
            'contract_fields' => self::CONTRACT_FIELDS,
            'candidates' => $candidates,
            'counts' => $counts,
            'source_counts' => [
                'linked_routes' => count($linkedRows),
                'declared_routes' => count($declaredRows),
                'route_contracts' => count($contractRows),
            ],
        ];
    }

    private static function mapLinkedStatus(string $linkedStatus): string
    {
        return match ($linkedStatus) {
            'loaded' => 'linked',
            'legacy_fallback', 'broken_missing' => 'mismatch',
            'disabled_by_app_status' => 'blocked',
            default => 'blocked',
        };
    }

    /**
     * @param array<string,mixed> $row
     * @return array<int,string>
     */
    private static function buildLinkedDiagnostics(string $statusRaw, array $row): array
    {
        $diagnostics = ['linked_route_status:' . ($statusRaw !== '' ? $statusRaw : 'unknown')];

        $runtimeUrl = self::normalizePath((string)($row['runtime_url'] ?? ''));
        $currentUrl = self::normalizePath((string)($row['url'] ?? ''));
        if ($runtimeUrl !== '' && $currentUrl !== '' && $runtimeUrl !== $currentUrl) {
            $diagnostics[] = 'runtime_uses_fallback_url';
        }

        $ownerStatus = trim((string)($row['owner_status'] ?? ''));
        if ($ownerStatus !== '') {
            $diagnostics[] = 'owner_status:' . strtolower($ownerStatus);
        }

        return $diagnostics;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private static function createCandidate(array $payload): array
    {
        $candidate = [];
        foreach (self::CONTRACT_FIELDS as $field) {
            if ($field === 'diagnostics') {
                $candidate[$field] = array_values(array_filter(array_map('strval', (array)($payload[$field] ?? [])), static fn(string $v): bool => trim($v) !== ''));
                continue;
            }
            $candidate[$field] = trim((string)($payload[$field] ?? ''));
        }
        return $candidate;
    }

    /**
     * @return array{owner_app:string,owner_module:string}
     */
    private static function parseSourceKey(string $sourceKey): array
    {
        $parts = array_values(array_filter(explode('.', trim($sourceKey)), static fn(string $v): bool => $v !== ''));
        if ($parts === []) {
            return ['owner_app' => '', 'owner_module' => ''];
        }

        if (count($parts) >= 4 && $parts[0] === 'studio' && $parts[1] === 'generated') {
            return [
                'owner_app' => trim((string)$parts[2]),
                'owner_module' => trim((string)$parts[3]),
            ];
        }

        if (count($parts) >= 3 && $parts[0] === 'apps') {
            return [
                'owner_app' => trim((string)$parts[1]),
                'owner_module' => trim((string)$parts[2]),
            ];
        }

        if (count($parts) >= 2) {
            return [
                'owner_app' => trim((string)$parts[0]),
                'owner_module' => trim((string)$parts[1]),
            ];
        }

        return ['owner_app' => trim((string)$parts[0]), 'owner_module' => ''];
    }

    private static function normalizePath(string $path): string
    {
        $trimmed = trim($path);
        if ($trimmed === '' || !str_starts_with($trimmed, '/')) {
            return '';
        }

        $parsed = parse_url($trimmed, PHP_URL_PATH);
        $normalized = '/' . ltrim((string)($parsed ?: '/'), '/');
        return rtrim($normalized, '/') ?: '/';
    }
}

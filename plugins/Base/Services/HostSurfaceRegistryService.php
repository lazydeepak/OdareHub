<?php
declare(strict_types=1);

namespace Plugins\Base\Services;

use App\Core\DB;

final class HostSurfaceRegistryService
{
    private const VIEW_KINDS = [
        'kpi',
        'table',
        'form',
        'chart',
        'cards',
        'queue',
        'timeline',
        'mixed',
    ];

    private const WIDGET_TYPES = [
        'action',
        'informative',
        'alert',
        'queue',
        'progress',
        'chart',
        'timeline',
        'approval',
        'watchlist',
        'reference',
        'activity_feed',
        'display',
    ];

    private const PLACEMENT_ZONES = [
        'primary_work',
        'operator_actions',
        'monitoring',
        'supporting_visibility',
        'dashboard_summary',
        'display_wall',
    ];

    /**
     * @param array<string,mixed> $context
     * @return array<string,array<int,array<string,mixed>>>
     */
    public static function build(string $surface, array $context = []): array
    {
        $surface = trim($surface);
        if ($surface === '' || !self::tableExists('core_app_hooks') || !self::tableExists('core_apps')) {
            return [];
        }

        $rows = DB::fetchAll(
            "SELECT h.app_key, h.hook_key, h.payload_json, a.install_path
             FROM core_app_hooks h
             INNER JOIN core_apps a ON a.app_key = h.app_key
             WHERE a.status='enabled' AND h.is_enabled=1 AND h.hook_type='host_surface'
             ORDER BY h.app_key ASC, h.id ASC"
        );

        $activeAssignedApps = array_values(array_filter(array_map(
            static fn($token): string => strtolower(trim((string)$token)),
            (array)($context['context']['active_assigned_apps'] ?? $context['context']['assigned_apps'] ?? [])
        ), static fn(string $token): bool => $token !== ''));
        $assignedApps = array_values(array_filter(array_map(
            static fn($token): string => strtolower(trim((string)$token)),
            (array)($context['context']['assigned_apps'] ?? [])
        ), static fn(string $token): bool => $token !== ''));
        $priorityApps = $activeAssignedApps !== [] ? $activeAssignedApps : $assignedApps;
        $appPriority = [];
        foreach ($priorityApps as $idx => $appKey) {
            if (!isset($appPriority[$appKey])) {
                $appPriority[$appKey] = $idx;
            }
        }

        $moduleVisibility = array_fill_keys(
            array_values(array_filter(array_map(
                static fn($token): string => strtolower(trim((string)$token)),
                (array)($context['context']['module_visibility'] ?? [])
            ), static fn(string $token): bool => $token !== '')),
            true
        );

        $regions = [];
        foreach ($rows as $row) {
            $payload = json_decode((string)($row['payload_json'] ?? '{}'), true);
            if (!is_array($payload)) {
                continue;
            }

            if (trim((string)($payload['surface'] ?? '')) !== $surface) {
                continue;
            }

            $region = trim((string)($payload['region'] ?? ''));
            $provider = trim((string)($payload['provider'] ?? ''));
            if ($region === '' || $provider === '' || !str_contains($provider, '::')) {
                continue;
            }

            [$class, $method] = explode('::', $provider, 2);
            if ($class === '' || $method === '') {
                continue;
            }

            try {
                $providerFile = trim((string)($payload['provider_file'] ?? ''));
                $installPath = rtrim((string)($row['install_path'] ?? ''), '/');
                if ($providerFile !== '' && $installPath !== '') {
                    $candidate = $installPath . '/' . ltrim($providerFile, '/');
                    if (is_file($candidate) && !class_exists($class, false)) {
                        require_once $candidate;
                    }
                }

                if (!class_exists($class) || !method_exists($class, $method)) {
                    continue;
                }

                $result = $class::$method([
                    'surface' => $surface,
                    'region' => $region,
                    'payload' => $payload,
                    'app_key' => (string)($row['app_key'] ?? ''),
                    'hook_key' => (string)($row['hook_key'] ?? ''),
                    'context' => $context,
                ]);
            } catch (\Throwable $e) {
                continue;
            }

            $items = self::normalizeItems($result, $surface, $region, (string)($row['app_key'] ?? ''));
            $items = self::filterItemsByAccess($items, $context);
            if ($items === []) {
                continue;
            }

            foreach ($items as $item) {
                $item['app_key'] = (string)($row['app_key'] ?? '');
                $item['region'] = $region;
                $item['_weight'] = (int)($item['weight'] ?? $payload['order'] ?? 100);
                $appKey = strtolower(trim((string)($item['app_key'] ?? '')));
                $moduleKey = strtolower(trim((string)($item['module_key'] ?? $item['module'] ?? '')));
                $item['_assigned_app_priority'] = (int)($appPriority[$appKey] ?? 9999);
                $item['_module_priority'] = ($moduleKey !== '' && isset($moduleVisibility[$moduleKey])) ? 0 : 1;
                $regions[$region][] = $item;
            }
        }

        foreach ($regions as $region => $items) {
            usort($items, static function (array $a, array $b): int {
                $appPriorityCmp = ((int)($a['_assigned_app_priority'] ?? 9999)) <=> ((int)($b['_assigned_app_priority'] ?? 9999));
                if ($appPriorityCmp !== 0) {
                    return $appPriorityCmp;
                }

                $modulePriorityCmp = ((int)($a['_module_priority'] ?? 1)) <=> ((int)($b['_module_priority'] ?? 1));
                if ($modulePriorityCmp !== 0) {
                    return $modulePriorityCmp;
                }

                $weightCmp = ((int)($a['_weight'] ?? 100)) <=> ((int)($b['_weight'] ?? 100));
                if ($weightCmp !== 0) {
                    return $weightCmp;
                }
                return strcmp((string)($a['title'] ?? $a['label'] ?? ''), (string)($b['title'] ?? $b['label'] ?? ''));
            });
            $regions[$region] = array_map(static function (array $item): array {
                unset($item['_weight']);
                unset($item['_assigned_app_priority']);
                unset($item['_module_priority']);
                return $item;
            }, $items);
        }

        return $regions;
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    private static function filterItemsByAccess(array $items, array $context): array
    {
        $filtered = [];
        foreach ($items as $item) {
            $permissionProfile = strtolower(trim((string)($item['permission_profile'] ?? '')));
            if (in_array($permissionProfile, ['none', 'deny', 'denied', 'forbidden', 'no_access'], true)) {
                continue;
            }
            $filtered[] = $item;
        }

        $items = $filtered;
        if (!function_exists('platform_user_access_policy_contract')) {
            return $items;
        }

        $policy = platform_user_access_policy_contract();
        if (!is_object($policy)) {
            return $items;
        }

        $user = is_array($context['user'] ?? null) ? (array)$context['user'] : null;
        if (!is_array($user)) {
            return $items;
        }

        $routeAllowed = static function ($policy, array $user, string $url): bool {
            $path = trim((string)parse_url($url, PHP_URL_PATH));
            if ($path === '' || $path === '#') {
                return true;
            }

            $decision = $policy->routeAccessDecision($user, $path, 'GET');
            return (bool)($decision['allowed'] ?? false);
        };

        $sanitizeByAccess = static function (array $item) use ($policy, $user, $routeAllowed): array {
            $url = trim((string)($item['url'] ?? ''));
            if ($url !== '' && !$routeAllowed($policy, $user, $url)) {
                $item['url'] = '';
                $item['url_label'] = '';
            }

            $rows = [];
            foreach ((array)($item['rows'] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $rowUrl = trim((string)($row['url'] ?? ''));
                if ($rowUrl !== '' && !$routeAllowed($policy, $user, $rowUrl)) {
                    $row['url'] = '';
                    $row['url_label'] = '';
                }
                $rows[] = $row;
            }
            if ($rows !== []) {
                $item['rows'] = $rows;
            }

            $toolbarActions = [];
            foreach ((array)($item['toolbar_actions'] ?? []) as $action) {
                if (!is_array($action)) {
                    continue;
                }
                $actionUrl = trim((string)($action['url'] ?? ''));
                if ($actionUrl !== '' && !$routeAllowed($policy, $user, $actionUrl)) {
                    continue;
                }
                $toolbarActions[] = $action;
            }
            if ($toolbarActions !== []) {
                $item['toolbar_actions'] = $toolbarActions;
            } elseif (array_key_exists('toolbar_actions', $item)) {
                $item['toolbar_actions'] = [];
            }

            return $item;
        };

        $filtered = [];
        foreach ($items as $item) {
            $filtered[] = $sanitizeByAccess($item);
        }

        return $filtered;
    }

    /**
     * @param mixed $result
     * @return array<int,array<string,mixed>>
     */
    private static function normalizeItems($result, string $surface, string $region, string $appKey): array
    {
        if (!is_array($result)) {
            return [];
        }

        if (array_is_list($result)) {
            return array_values(array_map(
                static fn(array $item): array => self::normalizeTaxonomy($item, $surface, $region, $appKey),
                array_values(array_filter($result, 'is_array'))
            ));
        }

        if (isset($result['items']) && is_array($result['items'])) {
            return array_values(array_map(
                static fn(array $item): array => self::normalizeTaxonomy($item, $surface, $region, $appKey),
                array_values(array_filter((array)$result['items'], 'is_array'))
            ));
        }

        return [self::normalizeTaxonomy($result, $surface, $region, $appKey)];
    }

    /**
     * @param array<string,mixed> $item
     * @return array<string,mixed>
     */
    private static function normalizeTaxonomy(array $item, string $surface, string $region, string $appKey): array
    {
        $item['widget_key'] = self::normalizeWidgetKey($item, $region);
        $item['widget_type'] = self::normalizeWidgetType((string)($item['widget_type'] ?? ''), $region, $item);
        $item['view_kind'] = self::normalizeViewKind((string)($item['view_kind'] ?? $item['kind'] ?? ''), $region, $item);
        $item['placement_zone'] = self::normalizePlacementZone((string)($item['placement_zone'] ?? ''), $region, $item);
        $item['interaction_profiles'] = self::normalizeInteractionProfiles(
            $item['interaction_profiles'] ?? null,
            (string)$item['widget_type'],
            (string)$item['placement_zone']
        );
        $item['access_authorities'] = array_values(array_filter(
            array_map('strval', (array)($item['access_authorities'] ?? [])),
            static fn(string $value): bool => trim($value) !== ''
        ));
        $item['permission_profile'] = trim((string)($item['permission_profile'] ?? ''));
        $item['supports_empty_state'] = (bool)($item['supports_empty_state'] ?? (trim((string)($item['empty_message'] ?? '')) !== ''));
        $item['supports_clickthrough'] = (bool)($item['supports_clickthrough'] ?? self::supportsClickthrough($item));
        $item['surface_key'] = trim((string)($item['surface_key'] ?? $surface));
        $item['app_key'] = trim((string)($item['app_key'] ?? $appKey));
        $item['priority'] = (int)($item['priority'] ?? $item['weight'] ?? 100);

        return $item;
    }

    /**
     * @param array<string,mixed> $item
     */
    private static function normalizeViewKind(string $viewKind, string $region, array $item): string
    {
        $viewKind = strtolower(trim($viewKind));
        if (in_array($viewKind, self::VIEW_KINDS, true)) {
            return $viewKind;
        }

        if ($viewKind === 'watchlist') {
            return 'queue';
        }

        $widgetType = strtolower(trim((string)($item['widget_type'] ?? '')));

        if ($region === 'summary_cards') {
            return 'kpi';
        }

        if ($region === 'header_actions' || $region === 'quick_links') {
            return 'cards';
        }

        if ($region === 'monitoring_sections') {
            return match (strtolower(trim((string)($item['kind'] ?? 'table')))) {
                'chart' => 'chart',
                'form' => 'form',
                'timeline' => 'timeline',
                'watchlist' => 'queue',
                'table' => 'table',
                default => $widgetType === 'queue' ? 'queue' : 'mixed',
            };
        }

        return match ($widgetType) {
            'chart', 'progress' => 'chart',
            'approval', 'queue', 'watchlist' => 'queue',
            'action' => 'form',
            default => 'cards',
        };
    }

    /**
     * @param array<string,mixed> $item
     */
    private static function normalizeWidgetKey(array $item, string $region): string
    {
        $candidate = trim((string)($item['widget_key'] ?? $item['key'] ?? ''));
        if ($candidate !== '') {
            return $candidate;
        }

        $title = trim((string)($item['title'] ?? $item['label'] ?? $region));
        $normalized = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $title) ?? '');
        $normalized = trim($normalized, '_');

        return $normalized !== '' ? $normalized : ('widget_' . $region);
    }

    /**
     * @param array<string,mixed> $item
     */
    private static function normalizeWidgetType(string $widgetType, string $region, array $item): string
    {
        $widgetType = strtolower(trim($widgetType));
        if (in_array($widgetType, self::WIDGET_TYPES, true)) {
            return $widgetType;
        }

        return match ($region) {
            'header_actions' => 'action',
            'quick_links' => 'reference',
            'summary_cards' => 'informative',
            'monitoring_sections' => self::inferMonitoringWidgetType($item),
            default => 'informative',
        };
    }

    /**
     * @param array<string,mixed> $item
     */
    private static function inferMonitoringWidgetType(array $item): string
    {
        $kind = strtolower(trim((string)($item['kind'] ?? 'table')));

        return match ($kind) {
            'chart' => 'chart',
            'form' => 'action',
            'watchlist' => 'watchlist',
            'timeline' => 'timeline',
            default => 'queue',
        };
    }

    private static function normalizePlacementZone(string $placementZone, string $region, array $item): string
    {
        $placementZone = strtolower(trim($placementZone));
        if (in_array($placementZone, self::PLACEMENT_ZONES, true)) {
            return $placementZone;
        }

        $widgetType = strtolower(trim((string)($item['widget_type'] ?? '')));
        if ($widgetType === 'display') {
            return 'display_wall';
        }

        return match ($region) {
            'header_actions' => 'operator_actions',
            'summary_cards' => 'dashboard_summary',
            'quick_links' => 'supporting_visibility',
            'monitoring_sections' => 'monitoring',
            default => 'supporting_visibility',
        };
    }

    /**
     * @param mixed $profiles
     * @return array<int,string>
     */
    private static function normalizeInteractionProfiles($profiles, string $widgetType, string $placementZone): array
    {
        $normalized = array_values(array_filter(array_map(
            static fn($profile): string => strtolower(trim((string)$profile)),
            is_array($profiles) ? $profiles : []
        ), static fn(string $profile): bool => $profile !== ''));
        if ($normalized !== []) {
            return $normalized;
        }

        if ($widgetType === 'display' || $placementZone === 'display_wall') {
            return ['display'];
        }

        if ($placementZone === 'operator_actions') {
            return ['worker', 'leader', 'admin'];
        }

        return ['worker', 'leader', 'admin', 'read_only'];
    }

    /**
     * @param array<string,mixed> $item
     */
    private static function supportsClickthrough(array $item): bool
    {
        if (trim((string)($item['url'] ?? '')) !== '') {
            return true;
        }

        foreach ((array)($item['rows'] ?? []) as $row) {
            if (is_array($row) && trim((string)($row['url'] ?? '')) !== '') {
                return true;
            }
        }

        foreach ((array)($item['toolbar_actions'] ?? []) as $action) {
            if (is_array($action) && trim((string)($action['url'] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    private static function tableExists(string $table): bool
    {
        try {
            return DB::fetchOne(
                'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1',
                [$table]
            ) !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }
}

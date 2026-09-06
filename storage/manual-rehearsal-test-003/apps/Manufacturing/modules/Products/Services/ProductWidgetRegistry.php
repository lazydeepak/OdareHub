<?php
declare(strict_types=1);

namespace Plugins\Products\Services;

use Apps\Manufacturing\Services\ModuleSurfaceWidgetFactory;

final class ProductWidgetRegistry
{
    /**
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    public static function contribute(string $region, array $context = []): array
    {
        $items = ModuleSurfaceWidgetFactory::build($region, $context, [
            'key' => 'products',
            'title' => 'Parts Master',
            'url' => '/products',
            'report_url' => '/apps/manufacturing/products/report',
            'export_url' => '/apps/manufacturing/products/export',
            'table' => 'products',
            'priority' => 22,
            'include_summary' => true,
            'include_worker_view' => false,
        ]);

        if ($region !== 'monitoring_sections') {
            return $items;
        }

        $section = self::partsViewSection($context);
        if ($section !== null) {
            $items[] = $section;
        }

        return $items;
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null
     */
    private static function partsViewSection(array $context): ?array
    {
        $user = is_array($context['user'] ?? null) ? (array)$context['user'] : [];
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0 || !self::canAccessPartsView()) {
            return null;
        }

        $mode = self::meInteractionMode($context, $user);

        if (!class_exists(PartsMasterSnapshotService::class, false)) {
            require_once __DIR__ . '/PartsMasterSnapshotService.php';
        }

        if (!class_exists(PartsMasterSnapshotService::class)) {
            return null;
        }

        try {
            $snapshot = PartsMasterSnapshotService::build(
                '',
                'all',
                date('Y-m-d'),
                $userId,
                'my',
                'coverage_balance_qty',
                'asc'
            );
        } catch (\Throwable $e) {
            return null;
        }

        $rows = is_array($snapshot['rows'] ?? null) ? (array)$snapshot['rows'] : [];
        $scopeSource = (string)($snapshot['scope_source'] ?? 'assigned_parts');
        $description = $scopeSource === 'assigned_parts'
            ? (string)t('mfg.parts.description.assigned')
            : (string)t('mfg.parts.description.risk');

        $isReadOnlyMode = in_array($mode, ['read_only', 'display'], true);
        $maxRows = $mode === 'worker' ? 8 : 6;

        $columns = match ($mode) {
            'worker' => [(string)t('mfg.parts.col.part_name'), (string)t('mfg.parts.col.part_number'), (string)t('mfg.parts.col.coverage_balance')],
            'read_only', 'display' => [(string)t('mfg.parts.col.part_name'), (string)t('mfg.parts.col.part_number'), (string)t('mfg.parts.col.coverage_balance')],
            default => [(string)t('mfg.parts.col.part_name'), (string)t('mfg.parts.col.part_number'), (string)t('mfg.parts.col.stock'), (string)t('mfg.parts.col.target_today'), (string)t('mfg.parts.col.coverage_balance')],
        };

        $title = match ($mode) {
            'worker' => (string)t('mfg.parts.title.worker'),
            'leader' => (string)t('mfg.parts.title.leader'),
            'admin' => (string)t('mfg.parts.title.admin'),
            'read_only' => (string)t('mfg.parts.title.read_only'),
            'display' => (string)t('mfg.parts.title.display'),
            default => (string)t('mfg.parts.title.default'),
        };

        $profiles = $mode === 'display'
            ? ['display']
            : [$mode];

        $mappedRows = array_map(static function (array $row) use ($mode, $isReadOnlyMode): array {
            $coverage = (float)($row['coverage_balance_qty'] ?? 0);
            $stockQty = (float)($row['stock_qty'] ?? 0);
            $targetTodayQty = max(0.0, (float)($row['target_today_qty'] ?? 0));
            $readinessPct = $targetTodayQty > 0 ? round(min(100.0, ($stockQty / $targetTodayQty) * 100), 1) : ($coverage >= 0 ? 100.0 : 0.0);

            $status = (string)t('mfg.parts.status.healthy');
            $statusTone = 'success';
            if ($coverage < 0) {
                $status = abs($coverage) >= 50 ? (string)t('mfg.parts.status.critical') : (string)t('mfg.parts.status.risk');
                $statusTone = abs($coverage) >= 50 ? 'danger' : 'warning';
            } elseif ($readinessPct < 80) {
                $status = (string)t('mfg.parts.status.watch');
                $statusTone = 'warning';
            }

            $meta = (string)t('mfg.parts.meta.coverage', ['value' => number_format($coverage, 2, '.', ',')]);
            if ($targetTodayQty > 0) {
                $meta = (string)t('mfg.parts.meta.stock_vs_target', [
                    'stock' => number_format($stockQty, 2, '.', ','),
                    'target' => number_format($targetTodayQty, 2, '.', ','),
                ]);
            }

            if ($mode === 'worker') {
                return [
                    'title' => (string)($row['part_name'] ?? '-'),
                    'subtitle' => (string)($row['part_number'] ?? '-'),
                    'meta' => $meta,
                    'status' => $status,
                    'status_tone' => $statusTone,
                    'progress_pct' => $readinessPct,
                    'stock_qty' => round($stockQty, 2),
                    'target_today_qty' => round($targetTodayQty, 2),
                    'coverage_balance_qty' => round($coverage, 2),
                    'cells' => [
                        (string)($row['part_name'] ?? '-'),
                        (string)($row['part_number'] ?? '-'),
                        number_format($coverage, 2, '.', ','),
                    ],
                    'url' => '/apps/manufacturing/products/360?id=' . (int)($row['part_id'] ?? 0),
                    'url_label' => (string)t('mfg.parts.action.part_360'),
                ];
            }

            if ($isReadOnlyMode) {
                return [
                    'title' => (string)($row['part_name'] ?? '-'),
                    'subtitle' => (string)($row['part_number'] ?? '-'),
                    'meta' => $meta,
                    'status' => $status,
                    'status_tone' => $statusTone,
                    'progress_pct' => $readinessPct,
                    'stock_qty' => round($stockQty, 2),
                    'target_today_qty' => round($targetTodayQty, 2),
                    'coverage_balance_qty' => round($coverage, 2),
                    'cells' => [
                        (string)($row['part_name'] ?? '-'),
                        (string)($row['part_number'] ?? '-'),
                        number_format($coverage, 2, '.', ','),
                    ],
                ];
            }

            return [
                'title' => (string)($row['part_name'] ?? '-'),
                'subtitle' => (string)($row['part_number'] ?? '-'),
                'meta' => $meta,
                'status' => $status,
                'status_tone' => $statusTone,
                'progress_pct' => $readinessPct,
                'stock_qty' => round($stockQty, 2),
                'target_today_qty' => round($targetTodayQty, 2),
                'coverage_balance_qty' => round($coverage, 2),
                'cells' => [
                    (string)($row['part_name'] ?? '-'),
                    (string)($row['part_number'] ?? '-'),
                    number_format((float)($row['stock_qty'] ?? 0), 2, '.', ','),
                    number_format((float)($row['target_today_qty'] ?? 0), 2, '.', ','),
                    number_format($coverage, 2, '.', ','),
                ],
                'url' => '/apps/manufacturing/products/360?id=' . (int)($row['part_id'] ?? 0),
                'url_label' => (string)t('mfg.parts.action.part_360'),
            ];
        }, array_slice($rows, 0, $maxRows));

        return [
            'widget_key' => 'parts_view',
            'surface_key' => 'me',
            'view_kind' => 'table',
            'widget_type' => 'watchlist',
            'placement_zone' => 'monitoring',
            'interaction_profiles' => $profiles,
            'supports_empty_state' => true,
            'supports_clickthrough' => !$isReadOnlyMode,
            'title' => $title,
            'description' => $description,
            'kind' => 'watchlist',
            'columns' => $columns,
            'rows' => $mappedRows,
            'toolbar_actions' => $isReadOnlyMode ? [] : [
                ['label' => (string)t('mfg.parts.action.view_all'), 'url' => '/apps/manufacturing/products'],
            ],
            'empty_message' => (string)t('mfg.parts.empty'),
            'priority' => 15,
            'weight' => 15,
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed> $user
     */
    private static function meInteractionMode(array $context, array $user): string
    {
        $ctx = is_array($context['context'] ?? null) ? (array)$context['context'] : [];
        $profiles = array_values((array)($ctx['access_profiles'] ?? []));
        $duties = array_values((array)($ctx['duty_codes'] ?? []));
        $tokens = array_values(array_filter(array_map(
            static fn($token): string => strtolower(trim((string)$token)),
            array_merge($profiles, $duties)
        ), static fn(string $token): bool => $token !== ''));

        if (in_array('display', $tokens, true)) {
            return 'display';
        }
        if (in_array('read_only', $tokens, true)) {
            return 'read_only';
        }

        $authorityRole = strtolower(trim((string)($user['authority_role'] ?? $user['account_class'] ?? '')));
        if (in_array($authorityRole, ['platform_admin', 'app_admin', 'admin'], true)) {
            return 'admin';
        }

        $role = strtolower(trim((string)($user['role'] ?? '')));
        if (str_contains($role, 'leader')) {
            return 'leader';
        }

        return 'worker';
    }

    private static function canAccessPartsView(): bool
    {
        if (!function_exists('platform_user_access_policy_contract')) {
            return true;
        }

        $policy = platform_user_access_policy_contract();
        if (!is_object($policy) || !function_exists('auth_user')) {
            return true;
        }

        try {
            $decision = $policy->routeAccessDecision(auth_user(), '/apps/manufacturing/products', 'GET');
            return (bool)($decision['allowed'] ?? false);
        } catch (\Throwable $e) {
            return true;
        }
    }
}
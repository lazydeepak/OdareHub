<?php
declare(strict_types=1);

namespace Plugins\Coverage\Services;

use App\Core\DB;
use Apps\Manufacturing\Services\ModuleSurfaceWidgetFactory;

final class CoverageWidgetRegistry
{
    /**
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    public static function contribute(string $region, array $context = []): array
    {
        return ModuleSurfaceWidgetFactory::build($region, $context, [
            'key' => 'coverage',
            'title' => 'Coverage Analytics',
            'url' => '/apps/manufacturing/coverage',
            'report_url' => '/apps/manufacturing/coverage/report',
            'export_url' => '/apps/manufacturing/coverage/export',
            'table' => 'daily_orders',
            'priority' => 20,
            'include_summary' => true,
            'summary_value' => static fn (): int => self::summary()['critical_orders_count'],
            'summary_meta' => static fn (): string => 'Low coverage: ' . self::summary()['low_coverage_orders_count'] . ' · Avg coverage: ' . number_format((float)self::summary()['coverage_pct'], 1) . '%',
            'summary_tone' => static fn (): string => self::summary()['critical_orders_count'] > 0 ? 'danger' : (self::summary()['low_coverage_orders_count'] > 0 ? 'warn' : 'success'),
            'summary_widget_type' => static fn (): string => self::summary()['critical_orders_count'] > 0 ? 'alert' : 'informative',
            'chart_title' => (string)t('mfg.coverage.chart_title'),
            'chart_description' => (string)t('mfg.coverage.chart_description'),
            'chart_rows' => static fn (): array => [
                ['label' => (string)t('mfg.coverage.kpi.critical'), 'value' => self::summary()['critical_orders_count'], 'meta' => (string)t('mfg.coverage.meta.critical')],
                ['label' => (string)t('mfg.coverage.kpi.low_coverage'), 'value' => self::summary()['low_coverage_orders_count'], 'meta' => (string)t('mfg.coverage.meta.low_coverage')],
                ['label' => (string)t('mfg.coverage.kpi.due_today'), 'value' => self::summary()['window_today_count'], 'meta' => (string)t('mfg.coverage.meta.due_today')],
                ['label' => (string)t('mfg.coverage.kpi.due_3d'), 'value' => self::summary()['window_3day_count'], 'meta' => (string)t('mfg.coverage.meta.due_3d')],
            ],
            'table_title' => 'Coverage · Decision Queues',
            'table_description' => 'Open the exact shortage queue that needs action first.',
            'table_kind' => 'queue',
            'table_rows' => static fn (): array => [
                [
                    'title' => 'Critical Hotlist',
                    'subtitle' => 'Orders under severe shortage pressure',
                    'meta' => 'Immediate shortage escalation lane',
                    'status' => self::summary()['critical_orders_count'] > 0 ? 'Critical' : 'Stable',
                    'status_tone' => self::summary()['critical_orders_count'] > 0 ? 'danger' : 'success',
                    'progress_pct' => self::queueSharePercent(self::summary()['critical_orders_count']),
                    'entries' => (int)self::summary()['critical_orders_count'],
                    'cells' => ['Critical Hotlist', 'Orders under severe shortage pressure', (string)self::summary()['critical_orders_count']],
                    'url' => '/apps/manufacturing/coverage/report',
                    'url_label' => 'Open Hotlist',
                ],
                [
                    'title' => 'Daily Orders Risk',
                    'subtitle' => 'Low-coverage demand queue',
                    'meta' => 'Shortage qty ' . number_format((float)self::summary()['shortage_qty']),
                    'status' => self::summary()['low_coverage_orders_count'] > 0 ? 'Watch' : 'Stable',
                    'status_tone' => self::summary()['low_coverage_orders_count'] > 0 ? 'warning' : 'success',
                    'progress_pct' => self::queueSharePercent(self::summary()['low_coverage_orders_count']),
                    'entries' => (int)self::summary()['low_coverage_orders_count'],
                    'cells' => ['Daily Orders Risk', 'Low-coverage demand queue', (string)self::summary()['low_coverage_orders_count']],
                    'url' => '/daily-orders?coverage=low',
                    'url_label' => 'Open Orders',
                ],
                [
                    'title' => '7-Day Demand',
                    'subtitle' => 'Orders due within the next 7 days',
                    'meta' => self::summary()['window_today_count'] . ' already due today',
                    'status' => self::summary()['window_7day_count'] > 0 ? 'Planning' : 'Clear',
                    'status_tone' => self::summary()['window_7day_count'] > 0 ? 'info' : 'success',
                    'progress_pct' => self::queueSharePercent(self::summary()['window_7day_count']),
                    'entries' => (int)self::summary()['window_7day_count'],
                    'cells' => ['7-Day Demand', 'Orders due within the next 7 days', (string)self::summary()['window_7day_count']],
                    'url' => '/daily-orders?to_date=' . rawurlencode(date('Y-m-d', strtotime('+7 days'))),
                    'url_label' => 'Open Window',
                ],
            ],
            'form_title' => 'Coverage · Focus Window',
            'form_description' => 'Launch the coverage workspace with a near-term demand horizon in mind.',
            'form_action' => '/daily-orders',
            'form_requires_companion_views' => true,
            'form_fields' => [
                [
                    'type' => 'select',
                    'name' => 'coverage',
                    'label' => 'Coverage State',
                    'options' => [
                        ['value' => 'low', 'label' => 'Low', 'selected' => true],
                        ['value' => 'partial', 'label' => 'Partial'],
                        ['value' => 'full', 'label' => 'Full'],
                    ],
                ],
                [
                    'type' => 'date',
                    'name' => 'to_date',
                    'label' => 'Required By',
                    'value' => date('Y-m-d', strtotime('+3 days')),
                ],
            ],
            'form_actions' => [
                ['type' => 'submit', 'label' => 'Open Coverage Queue', 'primary' => true],
            ],
        ]);
    }

    /**
     * @return array<string,int|float>
     */
    private static function summary(): array
    {
        static $summary = null;
        if (is_array($summary)) {
            return $summary;
        }

        $summary = [
            'open_orders' => 0,
            'demand_qty' => 0.0,
            'shortage_qty' => 0.0,
            'coverage_pct' => 0.0,
            'critical_orders_count' => 0,
            'low_coverage_orders_count' => 0,
            'fully_covered_orders_count' => 0,
            'window_today_count' => 0,
            'window_3day_count' => 0,
            'window_7day_count' => 0,
        ];

        try {
            $row = DB::fetchOne(
                "SELECT
                    COUNT(*) AS open_orders,
                    COALESCE(SUM(qty), 0) AS demand_qty,
                    COALESCE(SUM(COALESCE(shortage_qty, 0)), 0) AS shortage_qty,
                    COALESCE(ROUND(AVG(COALESCE(coverage_pct, 0)), 2), 0) AS coverage_pct,
                    COALESCE(SUM(CASE
                        WHEN qty > 0 AND (
                            COALESCE(shortage_qty, 0) / NULLIF(qty, 0) >= 0.50
                            OR COALESCE(coverage_pct, 0) < 25
                        ) THEN 1 ELSE 0 END), 0) AS critical_orders_count,
                    COALESCE(SUM(CASE
                        WHEN qty > 0
                             AND COALESCE(shortage_qty, 0) > 0
                             AND NOT (
                                 COALESCE(shortage_qty, 0) / NULLIF(qty, 0) >= 0.50
                                 OR COALESCE(coverage_pct, 0) < 25
                             )
                        THEN 1 ELSE 0 END), 0) AS low_coverage_orders_count,
                    COALESCE(SUM(CASE
                        WHEN qty > 0 AND COALESCE(shortage_qty, 0) <= 0 THEN 1 ELSE 0 END), 0) AS fully_covered_orders_count,
                    COALESCE(SUM(CASE
                        WHEN DATE(COALESCE(required_date, order_date)) <= CURDATE() THEN 1 ELSE 0 END), 0) AS window_today_count,
                    COALESCE(SUM(CASE
                        WHEN DATE(COALESCE(required_date, order_date)) <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 1 ELSE 0 END), 0) AS window_3day_count,
                    COALESCE(SUM(CASE
                        WHEN DATE(COALESCE(required_date, order_date)) <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END), 0) AS window_7day_count
                 FROM daily_orders
                 WHERE LOWER(COALESCE(status, '')) NOT IN ('closed', 'completed', 'cancelled', 'canceled')"
            );
            if (is_array($row)) {
                $summary = [
                    'open_orders'                  => (int)($row['open_orders'] ?? 0),
                    'demand_qty'                   => (float)($row['demand_qty'] ?? 0.0),
                    'shortage_qty'                 => (float)($row['shortage_qty'] ?? 0.0),
                    'coverage_pct'                 => (float)($row['coverage_pct'] ?? 0.0),
                    'critical_orders_count'        => (int)($row['critical_orders_count'] ?? 0),
                    'low_coverage_orders_count'    => (int)($row['low_coverage_orders_count'] ?? 0),
                    'fully_covered_orders_count'   => (int)($row['fully_covered_orders_count'] ?? 0),
                    'window_today_count'            => (int)($row['window_today_count'] ?? 0),
                    'window_3day_count'             => (int)($row['window_3day_count'] ?? 0),
                    'window_7day_count'             => (int)($row['window_7day_count'] ?? 0),
                ];
            }
        } catch (\Throwable $e) {
        }

        return $summary;
    }

    private static function queueSharePercent(int|float|string $count): float
    {
        $openOrders = max(0.0, (float)self::summary()['open_orders']);
        $value = max(0.0, (float)$count);
        if ($openOrders <= 0.0) {
            return 0.0;
        }

        return round(min(100.0, ($value / $openOrders) * 100), 1);
    }
}

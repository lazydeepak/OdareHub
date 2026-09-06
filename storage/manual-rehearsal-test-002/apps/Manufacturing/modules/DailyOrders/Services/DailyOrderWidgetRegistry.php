<?php
declare(strict_types=1);

namespace Plugins\DailyOrders\Services;

use App\Core\DB;
use Apps\Manufacturing\Services\ModuleSurfaceWidgetFactory;

final class DailyOrderWidgetRegistry
{
    /**
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    public static function contribute(string $region, array $context = []): array
    {
        return ModuleSurfaceWidgetFactory::build($region, $context, [
            'key' => 'daily_orders',
            'title' => 'Daily Orders',
            'url' => '/daily-orders',
            'report_url' => '/daily-orders/report',
            'export_url' => '/daily-orders/export',
            'table' => 'daily_orders',
            'priority' => 21,
            'include_summary' => true,
            'summary_value' => static fn (): int => self::snapshot()['due_today'],
            'summary_meta' => static fn (): string => 'Low coverage: ' . self::snapshot()['low_coverage'] . ' · Shortage qty: ' . number_format((float)self::snapshot()['total_shortage']),
            'summary_tone' => static fn (): string => (self::snapshot()['low_coverage'] > 0 || self::snapshot()['due_today'] > 0) ? 'warn' : 'info',
            'summary_widget_type' => static fn (): string => (self::snapshot()['low_coverage'] > 0 || self::snapshot()['due_today'] > 0) ? 'alert' : 'informative',
            'chart_title' => (string)t('mfg.orders.chart_title'),
            'chart_description' => (string)t('mfg.orders.chart_description'),
            'chart_rows' => static fn (): array => [
                ['label' => (string)t('mfg.orders.kpi.due_today'), 'value' => self::snapshot()['due_today'], 'meta' => (string)t('mfg.orders.meta.due_today')],
                ['label' => (string)t('mfg.orders.kpi.due_7d'), 'value' => self::snapshot()['due_7day'], 'meta' => (string)t('mfg.orders.meta.due_7d')],
                ['label' => (string)t('mfg.orders.kpi.low_coverage'), 'value' => self::snapshot()['low_coverage'], 'meta' => (string)t('mfg.orders.meta.low_coverage')],
                ['label' => (string)t('mfg.orders.kpi.full_coverage'), 'value' => self::snapshot()['full_coverage'], 'meta' => (string)t('mfg.orders.meta.full_coverage')],
            ],
            'table_title' => 'Daily Orders · Queues',
            'table_description' => 'Reference queues that separate immediate demand from coverage risk.',
            'table_kind' => 'queue',
            'table_rows' => static fn (): array => [
                [
                    'title' => 'Due Today',
                    'subtitle' => 'Orders required today or earlier',
                    'meta' => self::snapshot()['due_today'] . ' open orders',
                    'status' => self::snapshot()['due_today'] > 0 ? 'Urgent' : 'Stable',
                    'progress_pct' => self::queueSharePercent(self::snapshot()['due_today']),
                    'entries' => (int)self::snapshot()['due_today'],
                    'cells' => ['Due Today', 'Orders required today or earlier', (string)self::snapshot()['due_today']],
                    'url' => '/daily-orders?to_date=' . rawurlencode(date('Y-m-d')),
                    'url_label' => 'Open Queue',
                ],
                [
                    'title' => 'Coverage Risk',
                    'subtitle' => 'Low-coverage open orders',
                    'meta' => 'Shortage qty ' . number_format((float)self::snapshot()['total_shortage']),
                    'status' => self::snapshot()['low_coverage'] > 0 ? 'Watch' : 'Stable',
                    'progress_pct' => self::queueSharePercent(self::snapshot()['low_coverage']),
                    'entries' => (int)self::snapshot()['low_coverage'],
                    'cells' => ['Coverage Risk', 'Low-coverage open orders', (string)self::snapshot()['low_coverage']],
                    'url' => '/daily-orders?coverage=low',
                    'url_label' => 'Review Risk',
                ],
                [
                    'title' => '7-Day Window',
                    'subtitle' => 'Open orders due in the next 7 days',
                    'meta' => self::snapshot()['due_7day'] . ' near-term orders',
                    'status' => self::snapshot()['due_7day'] > 0 ? 'Planning' : 'Clear',
                    'progress_pct' => self::queueSharePercent(self::snapshot()['due_7day']),
                    'entries' => (int)self::snapshot()['due_7day'],
                    'cells' => ['7-Day Window', 'Open orders due in the next 7 days', (string)self::snapshot()['due_7day']],
                    'url' => '/daily-orders?to_date=' . rawurlencode(date('Y-m-d', strtotime('+7 days'))),
                    'url_label' => 'Open Window',
                ],
            ],
            'form_title' => 'Daily Orders · Filter',
            'form_description' => 'Jump into demand by status, coverage state, and due window.',
            'form_requires_companion_views' => true,
            'form_fields' => [
                [
                    'type' => 'select',
                    'name' => 'coverage',
                    'label' => 'Coverage',
                    'options' => [
                        ['value' => '', 'label' => 'All', 'selected' => true],
                        ['value' => 'low', 'label' => 'Low'],
                        ['value' => 'partial', 'label' => 'Partial'],
                        ['value' => 'full', 'label' => 'Full'],
                    ],
                ],
                [
                    'type' => 'text',
                    'name' => 'status',
                    'label' => 'Status',
                    'value' => '',
                ],
                [
                    'type' => 'date',
                    'name' => 'to_date',
                    'label' => 'Due By',
                    'value' => date('Y-m-d', strtotime('+7 days')),
                ],
            ],
            'form_actions' => [
                ['type' => 'submit', 'label' => 'Open Demand Queue', 'primary' => true],
            ],
        ]);
    }

    /**
     * @return array<string,int|float>
     */
    private static function snapshot(): array
    {
        static $snapshot = null;
        if (is_array($snapshot)) {
            return $snapshot;
        }

        $snapshot = [
            'total_open' => 0,
            'total_qty' => 0.0,
            'total_shortage' => 0.0,
            'due_today' => 0,
            'due_7day' => 0,
            'low_coverage' => 0,
            'full_coverage' => 0,
        ];

        try {
            $row = DB::fetchOne(
                "SELECT
                    COUNT(*) AS total_open,
                    COALESCE(SUM(qty), 0) AS total_qty,
                    COALESCE(SUM(COALESCE(shortage_qty, 0)), 0) AS total_shortage,
                    COALESCE(SUM(CASE WHEN DATE(COALESCE(required_date, order_date)) <= CURDATE() THEN 1 ELSE 0 END), 0) AS due_today,
                    COALESCE(SUM(CASE WHEN DATE(COALESCE(required_date, order_date)) <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END), 0) AS due_7day,
                    COALESCE(SUM(CASE WHEN coverage_status = 'Low' THEN 1 ELSE 0 END), 0) AS low_coverage,
                    COALESCE(SUM(CASE WHEN coverage_status = 'Full' THEN 1 ELSE 0 END), 0) AS full_coverage
                 FROM daily_orders
                 WHERE LOWER(COALESCE(status, '')) NOT IN ('closed', 'completed', 'cancelled', 'canceled')"
            );
            if (is_array($row)) {
                $snapshot = array_merge($snapshot, $row);
            }
        } catch (\Throwable $e) {
        }

        return $snapshot;
    }

    private static function queueSharePercent(int|float|string $count): float
    {
        $totalOpen = max(0.0, (float)self::snapshot()['total_open']);
        $value = max(0.0, (float)$count);
        if ($totalOpen <= 0.0) {
            return 0.0;
        }

        return round(min(100.0, ($value / $totalOpen) * 100), 1);
    }
}

<?php
declare(strict_types=1);

namespace Plugins\ProductionEntries\Services;

use App\Core\DB;
use Apps\Manufacturing\Services\ModuleSurfaceWidgetFactory;

final class ProductionEntryWidgetRegistry
{
    /**
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    public static function contribute(string $region, array $context = []): array
    {
        $items = [];

        if ($region === 'summary_cards') {
            $openEntries = self::openEntryCount();
            $items[] = [
                'widget_key' => 'production_entries',
                'view_kind' => 'kpi',
                'widget_type' => $openEntries > 0 ? 'alert' : 'informative',
                'placement_zone' => 'primary_work',
                'interaction_profiles' => ['worker', 'leader', 'admin', 'read_only'],
                'title' => 'Production Entries',
                'value' => $openEntries,
                'meta' => 'Execution records currently open in production.',
                'url' => '/apps/manufacturing/production-operation',
                'tone' => $openEntries > 0 ? 'warn' : 'success',
                'weight' => 12,
            ];
        }

        return array_merge($items, ModuleSurfaceWidgetFactory::build($region, $context, [
            'key' => 'production_entries',
            'title' => 'Production Entries',
            'url' => '/apps/manufacturing/production-operation',
            'report_url' => '/production-entries/report',
            'export_url' => '/production-entries/export',
            'table' => 'production_entries',
            'priority' => 26,
            'include_summary' => false,
            'chart_title' => (string)t('mfg.prod_entries.chart_title'),
            'chart_description' => (string)t('mfg.prod_entries.chart_description'),
            'chart_rows' => static fn (): array => [
                ['label' => (string)t('mfg.prod_entries.kpi.open_entries'), 'value' => self::snapshot()['open_entries'], 'meta' => (string)t('mfg.prod_entries.meta.open_entries')],
                ['label' => (string)t('mfg.prod_entries.kpi.today'), 'value' => self::snapshot()['today'], 'meta' => (string)t('mfg.prod_entries.meta.today')],
                ['label' => (string)t('mfg.prod_entries.kpi.last_7d'), 'value' => self::snapshot()['week'], 'meta' => (string)t('mfg.prod_entries.meta.last_7d')],
                ['label' => (string)t('mfg.prod_entries.kpi.rejected_qty'), 'value' => number_format((float)self::snapshot()['rejected']), 'meta' => (string)t('mfg.prod_entries.meta.rejected_qty')],
            ],
            'table_title' => 'Production Entries · Execution Views',
            'table_description' => 'Use the operator queue, report, or export depending on the execution question.',
            'table_rows' => static fn (): array => [
                [
                    'cells' => ['Open Queue', 'Current production entries needing closure', (string)self::snapshot()['open_entries']],
                    'url' => '/apps/manufacturing/production-operation?status=Open',
                    'url_label' => 'Open Queue',
                ],
                [
                    'cells' => ['Today Activity', 'Entries recorded on the current date', (string)self::snapshot()['today']],
                    'url' => '/production-entries?production_date=' . rawurlencode(date('Y-m-d')),
                    'url_label' => 'Open Today',
                ],
                [
                    'cells' => ['Execution Report', '14-day production summary and machine mix', number_format((float)self::snapshot()['produced'])],
                    'url' => '/production-entries/report',
                    'url_label' => 'Open Report',
                ],
            ],
            'form_title' => 'Production Entries · Filter',
            'form_description' => 'Jump into execution by date, machine, product, or state.',
            'form_action' => '/production-entries',
            'form_fields' => [
                [
                    'type' => 'date',
                    'name' => 'production_date',
                    'label' => 'Production Date',
                    'value' => date('Y-m-d'),
                ],
                [
                    'type' => 'text',
                    'name' => 'machine_id',
                    'label' => 'Machine ID',
                    'value' => '',
                ],
                [
                    'type' => 'text',
                    'name' => 'status',
                    'label' => 'Status',
                    'value' => '',
                ],
            ],
            'form_actions' => [
                ['type' => 'submit', 'label' => 'Open Execution Queue', 'primary' => true],
            ],
        ]));
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
            'open_entries' => 0,
            'produced' => 0.0,
            'good' => 0.0,
            'rejected' => 0.0,
            'today' => 0,
            'week' => 0,
        ];

        if (!self::tableExists('production_entries')) {
            return $snapshot;
        }

        try {
            $row = DB::fetchOne(
                "SELECT
                    COALESCE(SUM(CASE WHEN LOWER(COALESCE(status,'')) NOT IN ('closed','completed','cancelled','canceled') THEN 1 ELSE 0 END),0) AS open_entries,
                    COALESCE(SUM(produced_qty),0) AS produced,
                    COALESCE(SUM(good_qty),0) AS good,
                    COALESCE(SUM(rejected_qty),0) AS rejected,
                    COALESCE(SUM(CASE WHEN production_date = CURDATE() THEN 1 ELSE 0 END),0) AS today,
                    COALESCE(SUM(CASE WHEN production_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END),0) AS week
                 FROM production_entries
                 WHERE production_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)"
            );
            if (is_array($row)) {
                $snapshot = array_merge($snapshot, $row);
            }
        } catch (\Throwable $e) {
        }

        return $snapshot;
    }

    private static function openEntryCount(): int
    {
        if (!self::tableExists('production_entries')) {
            return 0;
        }

        $row = DB::fetchOne(
            "SELECT COUNT(*) AS total
             FROM production_entries
             WHERE LOWER(COALESCE(status,'')) NOT IN ('closed','completed','cancelled','canceled')"
        );

        return (int)($row['total'] ?? 0);
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
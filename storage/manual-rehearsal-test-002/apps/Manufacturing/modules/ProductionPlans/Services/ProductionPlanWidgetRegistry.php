<?php
declare(strict_types=1);

namespace Plugins\ProductionPlans\Services;

use App\Core\DB;
use Apps\Manufacturing\Services\ModuleSurfaceWidgetFactory;

final class ProductionPlanWidgetRegistry
{
    /**
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    public static function contribute(string $region, array $context = []): array
    {
        return ModuleSurfaceWidgetFactory::build($region, $context, [
            'key' => 'production_plans',
            'title' => 'Production Plans',
            'url' => '/production-plans',
            'report_url' => '/production-plans/report',
            'export_url' => '/production-plans/export',
            'table' => 'production_plans',
            'priority' => 24,
            'include_summary' => true,
            'summary_value' => static fn (): int => self::summary()['plans_today'],
            'summary_meta' => static fn (): string => 'Low coverage: ' . self::summary()['low_coverage'] . ' · Shortage qty: ' . number_format((float)self::summary()['total_shortage']),
            'summary_tone' => static fn (): string => (self::summary()['low_coverage'] > 0 || self::summary()['plans_today'] > 0) ? 'warn' : 'info',
            'summary_widget_type' => static fn (): string => (self::summary()['low_coverage'] > 0 || self::summary()['plans_today'] > 0) ? 'alert' : 'informative',
            'chart_title' => (string)t('mfg.prod_plans.chart_title'),
            'chart_description' => (string)t('mfg.prod_plans.chart_description'),
            'chart_rows' => static fn (): array => [
                ['label' => (string)t('mfg.prod_plans.kpi.today'), 'value' => self::summary()['plans_today'], 'meta' => (string)t('mfg.prod_plans.meta.today')],
                ['label' => (string)t('mfg.prod_plans.kpi.7d'), 'value' => self::summary()['plans_7day'], 'meta' => (string)t('mfg.prod_plans.meta.7d')],
                ['label' => (string)t('mfg.prod_plans.kpi.14d'), 'value' => self::summary()['plans_14day'], 'meta' => (string)t('mfg.prod_plans.meta.14d')],
                ['label' => (string)t('mfg.prod_plans.kpi.low_coverage'), 'value' => self::summary()['low_coverage'], 'meta' => (string)t('mfg.prod_plans.meta.low_coverage')],
            ],
            'table_title' => 'Production Plans · Work Queues',
            'table_description' => 'Open planning windows separated by urgency and readiness.',
            'table_rows' => static fn (): array => [
                [
                    'cells' => ['Today Queue', 'Plans scheduled for today', (string)self::summary()['plans_today']],
                    'url' => '/production-plans?plan_date=' . rawurlencode(date('Y-m-d')),
                    'url_label' => 'Open Queue',
                ],
                [
                    'cells' => ['Coverage Risk', 'Plans below 50% coverage', (string)self::summary()['low_coverage']],
                    'url' => '/production-plans/report',
                    'url_label' => 'Review Plans',
                ],
                [
                    'cells' => ['14-Day Window', 'All open plans in the next 14 days', (string)self::summary()['open_plans']],
                    'url' => '/production-plans',
                    'url_label' => 'Open Planner',
                ],
            ],
            'form_title' => 'Production Plans · Filter',
            'form_description' => 'Open the planning queue by date, machine, or status.',
            'form_fields' => [
                [
                    'type' => 'date',
                    'name' => 'plan_date',
                    'label' => 'Plan Date',
                    'value' => date('Y-m-d'),
                ],
                [
                    'type' => 'text',
                    'name' => 'status',
                    'label' => 'Status',
                    'value' => '',
                ],
                [
                    'type' => 'text',
                    'name' => 'machine_id',
                    'label' => 'Machine ID',
                    'value' => '',
                ],
            ],
            'form_actions' => [
                ['type' => 'submit', 'label' => 'Open Planning Queue', 'primary' => true],
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
            'open_plans' => 0,
            'planned_qty' => 0.0,
            'total_shortage' => 0.0,
            'plans_today' => 0,
            'plans_7day' => 0,
            'plans_14day' => 0,
            'low_coverage' => 0,
            'full_coverage' => 0,
        ];

        try {
            $row = DB::fetchOne(
                "SELECT
                    COUNT(*) AS open_plans,
                    COALESCE(SUM(planned_qty), 0) AS planned_qty,
                    COALESCE(SUM(COALESCE(shortage_qty, 0)), 0) AS total_shortage,
                    COALESCE(SUM(CASE WHEN plan_date = CURDATE() THEN 1 ELSE 0 END), 0) AS plans_today,
                    COALESCE(SUM(CASE WHEN plan_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END), 0) AS plans_7day,
                    COALESCE(SUM(CASE WHEN plan_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY) THEN 1 ELSE 0 END), 0) AS plans_14day,
                    COALESCE(SUM(CASE WHEN COALESCE(coverage_pct, 0) < 50 THEN 1 ELSE 0 END), 0) AS low_coverage,
                    COALESCE(SUM(CASE WHEN COALESCE(shortage_qty, 0) <= 0 THEN 1 ELSE 0 END), 0) AS full_coverage
                 FROM production_plans
                 WHERE LOWER(COALESCE(status, '')) NOT IN ('closed', 'completed', 'cancelled', 'canceled')
                   AND plan_date >= CURDATE()
                   AND plan_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)"
            );
            if (is_array($row)) {
                $summary = array_merge($summary, $row);
            }
        } catch (\Throwable $e) {
        }

        return $summary;
    }
}

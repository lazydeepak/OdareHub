<?php
declare(strict_types=1);

namespace Plugins\DispatchEntries\Services;

use App\Core\DB;
use Apps\Manufacturing\Services\ModuleSurfaceWidgetFactory;
use Plugins\DispatchEntries\Services\DispatchLeaderDashboardService;

final class DispatchEntryWidgetRegistry
{
    /**
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    public static function contribute(string $region, array $context = []): array
    {
        $items = [];

        if ($region === 'summary_cards') {
            $dispatchHold = self::dispatchHoldCount();
            $items[] = [
                'widget_key' => 'dispatch_ops',
                'view_kind' => 'kpi',
                'widget_type' => 'alert',
                'placement_zone' => 'primary_work',
                'interaction_profiles' => ['worker', 'leader', 'admin', 'read_only'],
                'title' => t('nav.dispatch_ops'),
                'value' => $dispatchHold,
                'meta' => t('ops.dispatch_leader.blocked_hold'),
                'url' => '/apps/manufacturing/dispatch-ops',
                'tone' => 'warn',
                'weight' => 15,
            ];
        }

        return array_merge($items, ModuleSurfaceWidgetFactory::build($region, $context, [
            'key' => 'dispatch_entries',
            'title' => 'Dispatch Ops',
            'url' => '/apps/manufacturing/dispatch-ops',
            'report_url' => '/dispatch-entries/report',
            'export_url' => '/dispatch-entries/export',
            'table' => 'dispatch_entries',
            'priority' => 31,
            'include_summary' => false,
            'chart_title' => (string)t('mfg.dispatch.chart_title'),
            'chart_description' => (string)t('mfg.dispatch.chart_description'),
            'chart_rows' => static fn (array $ctx): array => [
                ['label' => (string)t('mfg.dispatch.kpi.ready_now'), 'value' => self::dashboard($ctx)['kpi']['ready_now'] ?? 0, 'meta' => (string)t('mfg.dispatch.meta.ready_now')],
                ['label' => (string)t('mfg.dispatch.kpi.blocked_hold'), 'value' => self::dashboard($ctx)['kpi']['blocked_hold'] ?? 0, 'meta' => (string)t('mfg.dispatch.meta.blocked_hold')],
                ['label' => (string)t('mfg.dispatch.kpi.partial_queue'), 'value' => self::dashboard($ctx)['kpi']['partial_queue'] ?? 0, 'meta' => (string)t('mfg.dispatch.meta.partial_queue')],
                ['label' => (string)t('mfg.dispatch.kpi.overdue'), 'value' => self::dashboard($ctx)['kpi']['aging_overdue'] ?? 0, 'meta' => (string)t('mfg.dispatch.meta.overdue')],
            ],
            'table_title' => 'Dispatch Ops · Control Queues',
            'table_description' => 'Open the live dispatch board, blocked hold list, or execution report.',
            'table_rows' => static fn (array $ctx): array => [
                [
                    'cells' => ['Dispatch Board', 'Leader dispatch workspace', (string)(self::dashboard($ctx)['kpi']['ready_now'] ?? 0)],
                    'url' => '/apps/manufacturing/dispatch-ops',
                    'url_label' => 'Open Board',
                ],
                [
                    'cells' => ['Blocked Hold', 'Dispatch entries on hold or blocked', (string)(self::dashboard($ctx)['kpi']['blocked_hold'] ?? 0)],
                    'url' => '/dispatch-entries?dispatch_status=Blocked',
                    'url_label' => 'Open Queue',
                ],
                [
                    'cells' => ['Execution Report', 'Outbound mode and completion mix', (string)(self::dashboard($ctx)['kpi']['partial_queue'] ?? 0)],
                    'url' => '/dispatch-entries/report',
                    'url_label' => 'Open Report',
                ],
            ],
            'form_title' => 'Dispatch Ops · Filter',
            'form_description' => 'Filter dispatch execution by date or state.',
            'form_action' => '/dispatch-entries',
            'form_fields' => [
                [
                    'type' => 'date',
                    'name' => 'dispatch_date',
                    'label' => 'Dispatch Date',
                    'value' => date('Y-m-d'),
                ],
                [
                    'type' => 'text',
                    'name' => 'dispatch_status',
                    'label' => 'Dispatch Status',
                    'value' => '',
                ],
            ],
            'form_actions' => [
                ['type' => 'submit', 'label' => 'Open Dispatch Queue', 'primary' => true],
            ],
        ]));
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private static function dashboard(array $context): array
    {
        static $cache = [];
        $user = is_array($context['user'] ?? null) ? $context['user'] : null;
        $key = md5(json_encode($user));
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        try {
            $cache[$key] = DispatchLeaderDashboardService::build([], $user);
        } catch (\Throwable $e) {
            $cache[$key] = ['kpi' => ['ready_now' => 0, 'blocked_hold' => 0, 'partial_queue' => 0, 'aging_overdue' => 0]];
        }

        return $cache[$key];
    }

    private static function dispatchHoldCount(): int
    {
        if (!self::tableExists('dispatch_entries')) {
            return 0;
        }

        $row = DB::fetchOne(
            "SELECT COUNT(*) AS total
             FROM dispatch_entries
             WHERE LOWER(COALESCE(dispatch_status,'')) IN ('hold','blocked','pending','ready')"
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
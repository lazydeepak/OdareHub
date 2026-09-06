<?php
declare(strict_types=1);

namespace Plugins\QCEntries\Services;

use App\Core\DB;
use Apps\Manufacturing\Services\ModuleSurfaceWidgetFactory;
use Plugins\QCEntries\Services\QcLeaderDashboardService;

final class QCEntryWidgetRegistry
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
                'widget_key' => 'qc_entries',
                'view_kind' => 'kpi',
                'widget_type' => $openEntries > 0 ? 'alert' : 'informative',
                'placement_zone' => 'primary_work',
                'interaction_profiles' => ['worker', 'leader', 'admin', 'read_only'],
                'title' => 'QC Entries',
                'value' => $openEntries,
                'meta' => 'Execution records currently open in QC.',
                'url' => '/apps/manufacturing/qc-workboard',
                'tone' => $openEntries > 0 ? 'warn' : 'success',
                'weight' => 13,
            ];
        }

        return array_merge($items, ModuleSurfaceWidgetFactory::build($region, $context, [
            'key' => 'qc_entries',
            'title' => 'QC Entries',
            'url' => '/apps/manufacturing/qc-workboard',
            'report_url' => '/qc-entries/report',
            'export_url' => '/qc-entries/export',
            'table' => 'qc_entries',
            'priority' => 30,
            'include_summary' => false,
            'chart_title' => (string)t('mfg.qc.chart_title'),
            'chart_description' => (string)t('mfg.qc.chart_description'),
            'chart_rows' => static fn (array $ctx): array => [
                ['label' => (string)t('mfg.qc.kpi.run_now'), 'value' => self::dashboard($ctx)['kpi']['run_now'] ?? 0, 'meta' => (string)t('mfg.qc.meta.run_now')],
                ['label' => (string)t('mfg.qc.kpi.pending_qc'), 'value' => self::dashboard($ctx)['kpi']['pending_qc'] ?? 0, 'meta' => (string)t('mfg.qc.meta.pending_qc')],
                ['label' => (string)t('mfg.qc.kpi.failed_recheck'), 'value' => self::dashboard($ctx)['kpi']['failed_recheck'] ?? 0, 'meta' => (string)t('mfg.qc.meta.failed_recheck')],
                ['label' => (string)t('mfg.qc.kpi.ready_dispatch'), 'value' => self::dashboard($ctx)['kpi']['ready_dispatch'] ?? 0, 'meta' => (string)t('mfg.qc.meta.ready_dispatch')],
            ],
            'table_title' => 'QC Entries · Action Queues',
            'table_description' => 'Separate immediate run, backlog, and failure follow-up queues.',
            'table_rows' => static fn (array $ctx): array => [
                [
                    'cells' => ['QC Workboard', 'Leader dashboard for live QC prioritization', (string)(self::dashboard($ctx)['kpi']['run_now'] ?? 0)],
                    'url' => '/apps/manufacturing/qc-workboard',
                    'url_label' => 'Open Workboard',
                ],
                [
                    'cells' => ['Open Entries', 'QC entries still open', (string)self::openEntryCount()],
                    'url' => '/qc-entries?status=Open',
                    'url_label' => 'Open Queue',
                ],
                [
                    'cells' => ['Type Review', 'Inspect QC results by type and approval', (string)(self::dashboard($ctx)['kpi']['failed_recheck'] ?? 0)],
                    'url' => '/qc-entries/report',
                    'url_label' => 'Open Report',
                ],
            ],
            'form_title' => 'QC Entries · Filter',
            'form_description' => 'Filter QC execution by status or inspection type.',
            'form_action' => '/qc-entries',
            'form_fields' => [
                [
                    'type' => 'text',
                    'name' => 'status',
                    'label' => 'Status',
                    'value' => '',
                ],
                [
                    'type' => 'text',
                    'name' => 'qc_type',
                    'label' => 'QC Type',
                    'value' => '',
                ],
            ],
            'form_actions' => [
                ['type' => 'submit', 'label' => 'Open QC Queue', 'primary' => true],
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
            $cache[$key] = QcLeaderDashboardService::build([], $user);
        } catch (\Throwable $e) {
            $cache[$key] = ['kpi' => ['run_now' => 0, 'pending_qc' => 0, 'failed_recheck' => 0, 'ready_dispatch' => 0]];
        }

        return $cache[$key];
    }

    private static function openEntryCount(): int
    {
        if (!self::tableExists('qc_entries')) {
            return 0;
        }

        $row = DB::fetchOne(
            "SELECT COUNT(*) AS total
             FROM qc_entries
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
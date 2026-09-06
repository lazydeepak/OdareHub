<?php
declare(strict_types=1);

namespace Plugins\Payroll\Services;

use App\Core\DB;

final class PayrollWidgetRegistry
{
    /**
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    public static function contribute(string $region, array $context = []): array
    {
        return match ($region) {
            'summary_cards'   => self::summaryCard(),
            'watchlist_items' => self::watchlistItem(),
            default           => [],
        };
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function summaryCard(): array
    {
        $count = self::draftPayrollCount();

        return [[
            'widget_key'           => 'draft_payroll',
            'view_kind'            => 'kpi',
            'widget_type'          => $count > 0 ? 'alert' : 'informative',
            'placement_zone'       => 'dashboard_summary',
            'interaction_profiles' => ['worker', 'leader', 'admin', 'read_only'],
            'key'                  => 'draft_payroll',
            'title'                => t('sbaio.host.draft_payroll'),
            'value'                => $count,
            'meta'                 => t('sbaio.host.payroll_attention'),
            'url'                  => '/apps/sbaio/payroll',
            'tone'                 => $count > 0 ? 'warn' : 'info',
            'entries'              => self::draftPayrollEntries(3),
            'weight'               => 52,
        ]];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function watchlistItem(): array
    {
        $count = self::draftPayrollCount();

        return [[
            'label'        => t('sbaio.host.draft_payroll'),
            'count'        => $count,
            'url'          => '/apps/sbaio/payroll',
            'action_label' => t('common.review'),
            'meta'         => t('sbaio.host.payroll_attention'),
            'tone'         => self::watchlistTone($count, 2),
            'weight'       => 40,
        ]];
    }

    private static function draftPayrollCount(): int
    {
        if (!self::tableExists('sbaio_payroll_runs')) {
            return 0;
        }

        $row = DB::fetchOne(
            "SELECT COUNT(*) AS total
             FROM sbaio_payroll_runs
             WHERE LOWER(COALESCE(run_status, 'draft')) IN ('draft','generated','pending')"
        );

        return (int)($row['total'] ?? 0);
    }

    /**
     * @return array<int,array<string,string>>
     */
    private static function draftPayrollEntries(int $limit): array
    {
        if (!self::tableExists('sbaio_payroll_runs')) {
            return [];
        }

        $rows = DB::fetchAll(
            "SELECT period_key, period_year, period_month, period_month_label, run_status
             FROM sbaio_payroll_runs
             WHERE LOWER(COALESCE(run_status, 'draft')) IN ('draft','generated','pending')
             ORDER BY period_start DESC
             LIMIT " . (int)$limit
        );

        return array_values(array_map(static function (array $row): array {
            $label = trim((string)($row['period_month_label'] ?? ''));
            if ($label === '') {
                $year  = trim((string)($row['period_year'] ?? ''));
                $month = trim((string)($row['period_month'] ?? ''));
                $label = trim($year . ($month !== '' ? '-' . str_pad($month, 2, '0', STR_PAD_LEFT) : ''), '-');
            }
            $periodKey = trim((string)($row['period_key'] ?? ''));
            $status    = ucfirst(trim((string)($row['run_status'] ?? 'draft')));

            return [
                'label' => $label !== '' ? $label : ($periodKey !== '' ? $periodKey : 'Draft payroll'),
                'meta'  => trim(($periodKey !== '' ? $periodKey . ' · ' : '') . $status),
                'url'   => '/apps/sbaio/payroll',
            ];
        }, $rows));
    }

    private static function watchlistTone(int $count, int $dangerThreshold): string
    {
        if ($count >= $dangerThreshold) {
            return 'danger';
        }

        return $count > 0 ? 'warn' : 'info';
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

<?php
declare(strict_types=1);

namespace Plugins\Timecards\Services;

use App\Core\DB;

final class TimecardWidgetRegistry
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
        $count = self::draftTimecardCount();

        return [[
            'widget_key'           => 'draft_timecards',
            'view_kind'            => 'kpi',
            'widget_type'          => $count > 0 ? 'alert' : 'informative',
            'placement_zone'       => 'dashboard_summary',
            'interaction_profiles' => ['worker', 'leader', 'admin', 'read_only'],
            'key'                  => 'draft_timecards',
            'title'                => t('sbaio.host.draft_timecards'),
            'value'                => $count,
            'meta'                 => t('sbaio.host.timecards_attention'),
            'url'                  => '/apps/sbaio/timecards',
            'tone'                 => $count > 0 ? 'warn' : 'info',
            'entries'              => self::draftPeriodEntries(3),
            'weight'               => 51,
        ]];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function watchlistItem(): array
    {
        $count = self::draftTimecardCount();

        return [[
            'label'        => t('sbaio.host.draft_timecards'),
            'count'        => $count,
            'url'          => '/apps/sbaio/timecards',
            'action_label' => t('common.review'),
            'meta'         => t('sbaio.host.timecards_attention'),
            'tone'         => self::watchlistTone($count, 3),
            'weight'       => 20,
        ]];
    }

    private static function draftTimecardCount(): int
    {
        if (!self::tableExists('sbaio_timecard_periods')) {
            return 0;
        }

        $row = DB::fetchOne(
            "SELECT COUNT(*) AS total
             FROM sbaio_timecard_periods
             WHERE LOWER(COALESCE(approval_status, 'draft')) IN ('draft','generated','pending')"
        );

        return (int)($row['total'] ?? 0);
    }

    /**
     * @return array<int,array<string,string>>
     */
    private static function draftPeriodEntries(int $limit): array
    {
        if (!self::tableExists('sbaio_timecard_periods')) {
            return [];
        }

        $rows = DB::fetchAll(
            "SELECT period_key, approval_status
             FROM sbaio_timecard_periods
             WHERE LOWER(COALESCE(approval_status, 'draft')) IN ('draft','generated','pending')
             ORDER BY period_start DESC
             LIMIT " . (int)$limit
        );

        return array_values(array_map(static function (array $row): array {
            $periodKey = trim((string)($row['period_key'] ?? ''));
            $status    = ucfirst(trim((string)($row['approval_status'] ?? 'draft')));

            return [
                'label' => $periodKey !== '' ? $periodKey : 'Draft period',
                'meta'  => $status,
                'url'   => '/apps/sbaio/timecards',
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

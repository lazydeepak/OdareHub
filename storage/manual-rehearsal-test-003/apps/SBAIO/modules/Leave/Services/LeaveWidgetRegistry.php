<?php
declare(strict_types=1);

namespace Plugins\Leave\Services;

use App\Core\DB;

final class LeaveWidgetRegistry
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
        $count = self::pendingLeaveCount();

        return [[
            'widget_key'           => 'pending_leave',
            'view_kind'            => 'kpi',
            'widget_type'          => $count > 0 ? 'alert' : 'informative',
            'placement_zone'       => 'dashboard_summary',
            'interaction_profiles' => ['worker', 'leader', 'admin', 'read_only'],
            'key'                  => 'pending_leave',
            'title'                => t('sbaio.host.pending_leave'),
            'value'                => $count,
            'meta'                 => t('sbaio.host.leave_attention'),
            'url'                  => '/apps/sbaio/leave',
            'tone'                 => $count > 0 ? 'warn' : 'info',
            'entries'              => self::pendingLeaveEntries(3),
            'weight'               => 53,
        ]];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function watchlistItem(): array
    {
        $count = self::pendingLeaveCount();

        return [[
            'label'        => t('sbaio.host.pending_leave'),
            'count'        => $count,
            'url'          => '/apps/sbaio/leave',
            'action_label' => t('common.review'),
            'meta'         => t('sbaio.host.leave_attention'),
            'tone'         => self::watchlistTone($count, 3),
            'weight'       => 30,
        ]];
    }

    private static function pendingLeaveCount(): int
    {
        if (!self::tableExists('sbaio_leave_requests')) {
            return 0;
        }

        $row = DB::fetchOne(
            "SELECT COUNT(*) AS total
             FROM sbaio_leave_requests
             WHERE LOWER(COALESCE(leave_status, 'draft')) IN ('draft','pending','submitted')"
        );

        return (int)($row['total'] ?? 0);
    }

    /**
     * @return array<int,array<string,string>>
     */
    private static function pendingLeaveEntries(int $limit): array
    {
        if (!self::tableExists('sbaio_leave_requests')) {
            return [];
        }

        $rows = DB::fetchAll(
            "SELECT l.start_date, l.end_date, l.leave_type, l.leave_status, s.full_name, s.employee_code
             FROM sbaio_leave_requests l
             LEFT JOIN sbaio_staff s ON s.id = l.staff_id
             WHERE LOWER(COALESCE(l.leave_status, 'draft')) IN ('draft','pending','submitted')
             ORDER BY l.start_date DESC, l.id DESC
             LIMIT " . (int)$limit
        );

        return array_values(array_map(static function (array $row): array {
            $name      = trim((string)($row['full_name'] ?? ''));
            $code      = trim((string)($row['employee_code'] ?? ''));
            $start     = trim((string)($row['start_date'] ?? ''));
            $end       = trim((string)($row['end_date'] ?? ''));
            $status    = ucfirst(trim((string)($row['leave_status'] ?? 'draft')));
            $leaveType = trim((string)($row['leave_type'] ?? 'leave'));
            $dateLabel = $start;
            if ($end !== '' && $end !== $start) {
                $dateLabel .= ' → ' . $end;
            }

            return [
                'label' => trim(($code !== '' ? $code . ' ' : '') . ($name !== '' ? $name : 'Unknown staff')),
                'meta'  => trim($dateLabel . ' · ' . ucfirst($leaveType) . ' · ' . $status),
                'url'   => '/apps/sbaio/leave',
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

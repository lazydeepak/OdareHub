<?php
declare(strict_types=1);

namespace Plugins\Attendance\Services;

use App\Core\DB;

final class AttendanceWidgetRegistry
{
    /**
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    public static function contribute(string $region, array $context = []): array
    {
        return match ($region) {
            'summary_cards'  => self::summaryCard(),
            'watchlist_items' => self::watchlistItem(),
            default          => [],
        };
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function summaryCard(): array
    {
        $count = self::missingPunchCount();

        return [[
            'widget_key'           => 'missing_punches',
            'view_kind'            => 'kpi',
            'widget_type'          => $count > 0 ? 'alert' : 'informative',
            'placement_zone'       => 'dashboard_summary',
            'interaction_profiles' => ['worker', 'leader', 'admin', 'read_only'],
            'key'                  => 'missing_punches',
            'title'                => t('sbaio.host.missing_punches'),
            'value'                => $count,
            'meta'                 => t('sbaio.host.attendance_attention'),
            'url'                  => '/apps/sbaio/attendance',
            'tone'                 => $count > 0 ? 'warn' : 'info',
            'entries'              => self::missingPunchEntries(3),
            'weight'               => 50,
        ]];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function watchlistItem(): array
    {
        $count = self::missingPunchCount();

        return [[
            'label'        => t('sbaio.host.attendance_exceptions'),
            'count'        => $count,
            'url'          => '/apps/sbaio/attendance',
            'action_label' => t('common.investigate'),
            'meta'         => t('sbaio.host.attendance_attention'),
            'tone'         => self::watchlistTone($count, 5),
            'weight'       => 10,
        ]];
    }

    private static function missingPunchCount(): int
    {
        if (!self::tableExists('sbaio_attendance_daily')) {
            return 0;
        }

        $row = DB::fetchOne(
            "SELECT COUNT(*) AS total
             FROM sbaio_attendance_daily
             WHERE (work_start_at IS NULL OR work_end_at IS NULL)
               AND LOWER(COALESCE(day_marker, 'workday')) NOT IN ('holiday','leave','off_day','closed')"
        );

        return (int)($row['total'] ?? 0);
    }

    /**
     * @return array<int,array<string,string>>
     */
    private static function missingPunchEntries(int $limit): array
    {
        if (!self::tableExists('sbaio_attendance_daily')) {
            return [];
        }

        $rows = DB::fetchAll(
            "SELECT a.attendance_date, s.full_name, s.employee_code, a.work_start_at, a.work_end_at
             FROM sbaio_attendance_daily a
             LEFT JOIN sbaio_staff s ON s.id = a.staff_id
             WHERE (a.work_start_at IS NULL OR a.work_end_at IS NULL)
               AND LOWER(COALESCE(a.day_marker, 'workday')) NOT IN ('holiday','leave','off_day','closed')
             ORDER BY a.attendance_date DESC, a.id DESC
             LIMIT " . (int)$limit
        );

        return array_values(array_map(static function (array $row): array {
            $name    = trim((string)($row['full_name'] ?? ''));
            $code    = trim((string)($row['employee_code'] ?? ''));
            $date    = trim((string)($row['attendance_date'] ?? ''));
            $missing = [];
            if (($row['work_start_at'] ?? null) === null || trim((string)($row['work_start_at'] ?? '')) === '') {
                $missing[] = 'start';
            }
            if (($row['work_end_at'] ?? null) === null || trim((string)($row['work_end_at'] ?? '')) === '') {
                $missing[] = 'end';
            }

            return [
                'label' => trim(($code !== '' ? $code . ' ' : '') . ($name !== '' ? $name : 'Unknown staff')),
                'meta'  => trim($date . ($missing !== [] ? ' · Missing ' . implode(' + ', $missing) : '')),
                'url'   => '/apps/sbaio/attendance',
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

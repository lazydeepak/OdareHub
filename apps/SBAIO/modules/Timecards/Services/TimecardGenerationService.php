<?php
declare(strict_types=1);

namespace Plugins\Timecards\Services;

use App\Core\DB;

final class TimecardGenerationService
{
    /**
     * @param ?array<int,int> $staffFilterIds
     * @return array{period_key:string,staff_count:int,daily_rows:int,period_rows:int}
     */
    public static function generate(string $startDate, string $endDate, ?array $staffFilterIds = null): array
    {
        $start = new \DateTimeImmutable($startDate);
        $end = new \DateTimeImmutable($endDate);
        if ($end < $start) {
            throw new \RuntimeException('End date must be on or after start date.');
        }

        $staffFilterIds = array_values(array_filter(array_map(
            static fn($value): int => (int)$value,
            $staffFilterIds ?? []
        ), static fn(int $value): bool => $value > 0));

        $staffSql = "SELECT id, full_name, name_ref, salary_type, salary_rate, helper_classification, join_date, hire_date, exit_date
             FROM sbaio_staff
             WHERE (employment_status = 'active' OR employment_status IS NULL) AND (status = 'active' OR status IS NULL)";
        $staffParams = [];
        if ($staffFilterIds !== []) {
            $placeholders = implode(',', array_fill(0, count($staffFilterIds), '?'));
            $staffSql .= " AND id IN ({$placeholders})";
            $staffParams = $staffFilterIds;
        }

        $staffRows = DB::fetchAll(
            $staffSql,
            $staffParams
        );
        $staffById = [];
        $staffIds = [];
        foreach ($staffRows as $row) {
            $staffId = (int)($row['id'] ?? 0);
            if ($staffId <= 0) {
                continue;
            }
            $staffById[$staffId] = $row;
            $staffIds[] = $staffId;
        }
        $staffIds = array_values(array_filter($staffIds, static fn(int $id): bool => $id > 0));
        if ($staffIds === []) {
            throw new \RuntimeException('Create at least one active staff record before generating timecards.');
        }

        $attendanceRows = DB::fetchAll(
            'SELECT * FROM sbaio_attendance_daily WHERE attendance_date BETWEEN ? AND ?',
            [$startDate, $endDate]
        );
        $scheduleRows = DB::fetchAll(
            'SELECT * FROM sbaio_schedule_assignments WHERE schedule_date BETWEEN ? AND ?',
            [$startDate, $endDate]
        );
        $leaveRows = DB::fetchAll(
            "SELECT * FROM sbaio_leave_requests WHERE leave_status = 'approved' AND end_date >= ? AND start_date <= ?",
            [$startDate, $endDate]
        );
        $holidayRows = DB::fetchAll(
            "SELECT * FROM sbaio_holiday_calendar WHERE is_active = 1 AND holiday_date BETWEEN ? AND ?",
            [$startDate, $endDate]
        );

        $attendanceByStaffDate = [];
        foreach ($attendanceRows as $row) {
            $attendanceByStaffDate[(int)$row['staff_id'] . '|' . (string)$row['attendance_date']] = $row;
        }

        $scheduleByStaffDate = [];
        foreach ($scheduleRows as $row) {
            $scheduleByStaffDate[(int)$row['staff_id'] . '|' . (string)$row['schedule_date']] = $row;
        }

        $holidayByDate = [];
        foreach ($holidayRows as $row) {
            $holidayByDate[(string)$row['holiday_date']] = $row;
        }

        $leaveByStaffDate = [];
        foreach ($leaveRows as $row) {
            $leaveStart = new \DateTimeImmutable((string)$row['start_date']);
            $leaveEnd = new \DateTimeImmutable((string)$row['end_date']);
            $period = new \DatePeriod($leaveStart, new \DateInterval('P1D'), $leaveEnd->modify('+1 day'));
            foreach ($period as $date) {
                $leaveByStaffDate[(int)$row['staff_id'] . '|' . $date->format('Y-m-d')] = $row;
            }
        }

        $periodKey = $start->format('Ymd') . '_' . $end->format('Ymd');
        $dailyCount = 0;
        $periodCount = 0;

        foreach ($staffIds as $staffId) {
            $staff = $staffById[$staffId] ?? [];
            $summary = [
                'total_days' => 0,
                'worked_minutes' => 0,
                'worked_hours' => 0.0,
                'overtime_minutes' => 0,
                'workday_count' => 0,
                'leave_days' => 0.0,
                'holiday_days' => 0.0,
                'holiday_work_days' => 0.0,
                'exception_count' => 0,
            ];

            $days = new \DatePeriod($start, new \DateInterval('P1D'), $end->modify('+1 day'));
            foreach ($days as $date) {
                $dateKey = $date->format('Y-m-d');
                $key = $staffId . '|' . $dateKey;
                $attendance = $attendanceByStaffDate[$key] ?? null;
                $schedule = $scheduleByStaffDate[$key] ?? null;
                $leave = $leaveByStaffDate[$key] ?? null;
                $holiday = $holidayByDate[$dateKey] ?? null;

                $actualStart = (string)($attendance['work_start_at'] ?? '');
                $actualEnd = (string)($attendance['work_end_at'] ?? '');
                $breakStart = (string)($attendance['break_start_at'] ?? '');
                $breakEnd = (string)($attendance['break_end_at'] ?? '');
                $workedMinutes = self::workedMinutes($actualStart, $actualEnd, $breakStart, $breakEnd);
                $attendanceMarker = trim((string)($attendance['day_marker'] ?? ''));
                $scheduledMinutes = self::scheduledMinutes((string)($schedule['expected_start_time'] ?? ''), (string)($schedule['expected_end_time'] ?? ''), (int)($schedule['expected_break_minutes'] ?? 0));
                $legacyNameRef = self::legacyNameRef($staff);
                $salaryType = self::normalizeLabel((string)($staff['salary_type'] ?? ''));
                $salaryRate = isset($staff['salary_rate']) ? (float)$staff['salary_rate'] : null;
                $helperFlag = self::helperFlagForDate($staff, $dateKey);
                $monthLabel = $date->format('F');
                $weekdayLabel = $date->format('D');

                $dayStatus = 'off_day';
                if ($leave !== null) {
                    $dayStatus = 'leave';
                } elseif ($attendanceMarker !== '' && $attendanceMarker !== 'workday') {
                    $dayStatus = $attendanceMarker;
                } elseif ($holiday !== null) {
                    $dayStatus = 'holiday';
                } elseif ($schedule !== null || $attendance !== null) {
                    $dayStatus = 'workday';
                }

                if ($actualStart !== '' && $dayStatus === 'off_day') {
                    $dayStatus = 'workday';
                }

                $overtimeMinutes = 0;
                if ($dayStatus === 'workday') {
                    $overtimeMinutes = max(0, $workedMinutes - (8 * 60));
                }

                $exceptionStatus = 'normal';
                if ($attendance !== null && (($actualStart === '' && $actualEnd !== '') || ($actualStart !== '' && $actualEnd === ''))) {
                    $exceptionStatus = 'missing_punch';
                } elseif ($attendance === null && $schedule !== null && $leave === null && $holiday === null) {
                    $exceptionStatus = 'absent';
                }

                DB::query(
                    'INSERT INTO sbaio_timecards_daily (
                        staff_id, legacy_name_ref, work_date, attendance_row_id, scheduled_start_at, scheduled_end_at,
                        actual_start_at, actual_end_at, actual_break_start_at, actual_break_end_at, break_minutes,
                        worked_minutes, scheduled_minutes, overtime_minutes, day_status, attendance_marker, exception_status, salary_type,
                        salary_rate, daily_salary_amount, month_label, weekday_label, helper_flag, leave_type,
                        holiday_name, approval_status, generated_at
                     ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
                     ON DUPLICATE KEY UPDATE
                        legacy_name_ref=VALUES(legacy_name_ref),
                        attendance_row_id=VALUES(attendance_row_id),
                        scheduled_start_at=VALUES(scheduled_start_at),
                        scheduled_end_at=VALUES(scheduled_end_at),
                        actual_start_at=VALUES(actual_start_at),
                        actual_end_at=VALUES(actual_end_at),
                        actual_break_start_at=VALUES(actual_break_start_at),
                        actual_break_end_at=VALUES(actual_break_end_at),
                        break_minutes=VALUES(break_minutes),
                        worked_minutes=VALUES(worked_minutes),
                        scheduled_minutes=VALUES(scheduled_minutes),
                        overtime_minutes=VALUES(overtime_minutes),
                        day_status=VALUES(day_status),
                        attendance_marker=VALUES(attendance_marker),
                        exception_status=VALUES(exception_status),
                        salary_type=VALUES(salary_type),
                        salary_rate=VALUES(salary_rate),
                        daily_salary_amount=VALUES(daily_salary_amount),
                        month_label=VALUES(month_label),
                        weekday_label=VALUES(weekday_label),
                        helper_flag=VALUES(helper_flag),
                        leave_type=VALUES(leave_type),
                        holiday_name=VALUES(holiday_name),
                        approval_status=\'draft\',
                        generated_at=NOW()',
                    [
                        $staffId,
                        $legacyNameRef !== '' ? $legacyNameRef : null,
                        $dateKey,
                        $attendance['id'] ?? null,
                        self::combineDateAndTime($dateKey, (string)($schedule['expected_start_time'] ?? '')),
                        self::combineDateAndTime($dateKey, (string)($schedule['expected_end_time'] ?? '')),
                        $actualStart !== '' ? $actualStart : null,
                        $actualEnd !== '' ? $actualEnd : null,
                        $breakStart !== '' ? $breakStart : null,
                        $breakEnd !== '' ? $breakEnd : null,
                        self::breakMinutes($breakStart, $breakEnd),
                        $workedMinutes,
                        $scheduledMinutes,
                        $overtimeMinutes,
                        $dayStatus,
                        $attendanceMarker !== '' ? $attendanceMarker : null,
                        $exceptionStatus,
                        $salaryType !== '' ? $salaryType : null,
                        $salaryRate,
                        null,
                        $monthLabel,
                        $weekdayLabel,
                        $helperFlag,
                        $leave !== null ? self::normalizeLabel((string)($leave['leave_type'] ?? 'leave')) : null,
                        $holiday !== null ? self::normalizeLabel((string)($holiday['holiday_name'] ?? '')) : null,
                        'draft',
                    ]
                );
                $dailyCount++;

                if ($dayStatus !== 'off_day') {
                    $summary['total_days']++;
                }
                $summary['worked_minutes'] += $workedMinutes;
                $summary['worked_hours'] += round($workedMinutes / 60, 2);
                $summary['overtime_minutes'] += $overtimeMinutes;
                if ($dayStatus === 'workday') {
                    $summary['workday_count']++;
                }
                if ($dayStatus === 'leave') {
                    $summary['leave_days'] += 1.0;
                }
                if ($dayStatus === 'holiday') {
                    $summary['holiday_days'] += 1.0;
                }
                if ($dayStatus === 'holiday' && $workedMinutes > 0) {
                    $summary['holiday_work_days'] += 1.0;
                }
                if ($exceptionStatus !== 'normal') {
                    $summary['exception_count']++;
                }
            }

            DB::query(
                'INSERT INTO sbaio_timecard_periods (
                    staff_id, legacy_name_ref, period_key, period_start, period_end, total_days, total_worked_minutes,
                    total_worked_hours, overtime_minutes, workday_count, leave_days, holiday_days, holiday_work_days, exception_count,
                    salary_type, salary_rate, approval_status, locked_at
                 ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, ?, NULL)
                 ON DUPLICATE KEY UPDATE
                    legacy_name_ref=VALUES(legacy_name_ref),
                    total_days=VALUES(total_days),
                    total_worked_minutes=VALUES(total_worked_minutes),
                    total_worked_hours=VALUES(total_worked_hours),
                    overtime_minutes=VALUES(overtime_minutes),
                    workday_count=VALUES(workday_count),
                    leave_days=VALUES(leave_days),
                    holiday_days=VALUES(holiday_days),
                    holiday_work_days=VALUES(holiday_work_days),
                    exception_count=VALUES(exception_count),
                    salary_type=VALUES(salary_type),
                    salary_rate=VALUES(salary_rate),
                    approval_status=\'draft\',
                    locked_at=NULL',
                [
                    $staffId,
                    $legacyNameRef !== '' ? $legacyNameRef : null,
                    $periodKey,
                    $startDate,
                    $endDate,
                    $summary['total_days'],
                    $summary['worked_minutes'],
                    round($summary['worked_hours'], 2),
                    $summary['overtime_minutes'],
                    $summary['workday_count'],
                    $summary['leave_days'],
                    $summary['holiday_days'],
                    $summary['holiday_work_days'],
                    $summary['exception_count'],
                    $salaryType !== '' ? $salaryType : null,
                    $salaryRate,
                    'draft',
                ]
            );
            $periodCount++;
        }

        return [
            'period_key' => $periodKey,
            'staff_count' => count($staffIds),
            'daily_rows' => $dailyCount,
            'period_rows' => $periodCount,
        ];
    }

    private static function workedMinutes(string $start, string $end, string $breakStart, string $breakEnd): int
    {
        if ($start === '' || $end === '') {
            return 0;
        }
        $minutes = max(0, self::minutesBetween($start, $end) - self::breakMinutes($breakStart, $breakEnd));
        return $minutes;
    }

    private static function breakMinutes(string $breakStart, string $breakEnd): int
    {
        if ($breakStart === '' || $breakEnd === '') {
            return 0;
        }
        return max(0, self::minutesBetween($breakStart, $breakEnd));
    }

    private static function scheduledMinutes(string $startTime, string $endTime, int $breakMinutes): int
    {
        $startTime = trim($startTime);
        $endTime = trim($endTime);
        if ($startTime === '' || $endTime === '') {
            return 0;
        }

        return max(0, self::minutesBetween('2000-01-01 ' . $startTime, '2000-01-01 ' . $endTime) - max(0, $breakMinutes));
    }

    /**
     * @param array<string,mixed> $staff
     */
    private static function helperFlagForDate(array $staff, string $date): int
    {
        $helper = trim((string)($staff['helper_classification'] ?? ''));
        if ($helper === '') {
            return 0;
        }

        $effectiveStart = (string)($staff['join_date'] ?? '') !== '' ? (string)$staff['join_date'] : (string)($staff['hire_date'] ?? '');
        $effectiveEnd = (string)($staff['exit_date'] ?? '');
        if ($effectiveStart !== '' && $date < $effectiveStart) {
            return 0;
        }
        if ($effectiveEnd !== '' && $date > $effectiveEnd) {
            return 0;
        }

        return 1;
    }

    private static function normalizeLabel(string $value): string
    {
        return trim($value);
    }

    /**
     * @param array<string,mixed> $staff
     */
    private static function legacyNameRef(array $staff): string
    {
        $nameRef = trim((string)($staff['name_ref'] ?? ''));
        if ($nameRef !== '') {
            return $nameRef;
        }

        $fullName = trim((string)($staff['full_name'] ?? ''));
        if ($fullName === '') {
            return '';
        }

        return $fullName . '   様';
    }

    private static function minutesBetween(string $start, string $end): int
    {
        $from = strtotime($start);
        $to = strtotime($end);
        if ($from === false || $to === false) {
            return 0;
        }
        return max(0, (int)floor(($to - $from) / 60));
    }

    private static function combineDateAndTime(string $date, string $time): ?string
    {
        $time = trim($time);
        if ($date === '' || $time === '') {
            return null;
        }
        return $date . ' ' . $time;
    }
}

<?php
declare(strict_types=1);

namespace Plugins\Timecards\Services;

use App\Core\DB;

final class TimecardService
{
    /** @var array<string,array{rows:array<int,array<string,mixed>>,summary:array<string,mixed>}> */
    private static array $reportCache = [];

    private const ATTENDANCE_FILTERS = ['all', 'present', 'holiday', 'leave'];

    private const MONTH_LABELS = [
        1 => 'January',
        2 => 'February',
        3 => 'March',
        4 => 'April',
        5 => 'May',
        6 => 'June',
        7 => 'July',
        8 => 'August',
        9 => 'September',
        10 => 'October',
        11 => 'November',
        12 => 'December',
    ];

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public static function resolveFilters(array $input): array
    {
        $latestPeriod = self::latestDataPeriod();
        $yearOptions = self::yearOptions($latestPeriod['year']);
        $branchOptions = self::staffDimensionOptions('branch_name');
        $departmentOptions = self::staffDimensionOptions('department_name');

        $branch = self::validatedOption((string)($input['branch'] ?? ''), $branchOptions);
        $department = self::validatedOption((string)($input['department'] ?? ''), $departmentOptions);

        $year = (int)($input['year'] ?? 0);
        if (!in_array($year, $yearOptions, true)) {
            $year = $latestPeriod['year'];
        }

        $month = (int)($input['month'] ?? 0);
        if ($month < 1 || $month > 12) {
            $month = $latestPeriod['month'];
        }

        $monthStart = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $monthEnd = $monthStart->modify('last day of this month');

        $staffRows = self::staffRows($branch, $department);
        $staffOptions = [];
        $staffIds = [];
        foreach ($staffRows as $row) {
            $staffId = (int)($row['id'] ?? 0);
            if ($staffId <= 0) {
                continue;
            }
            $staffIds[] = $staffId;
            $staffOptions[] = [
                'id' => $staffId,
                'label' => self::staffOptionLabel($row),
            ];
        }

        $staffId = (int)($input['staff_id'] ?? 0);
        if (!in_array($staffId, $staffIds, true)) {
            $staffId = $staffIds[0] ?? 0;
        }

        $selectedStaff = null;
        foreach ($staffRows as $row) {
            if ((int)($row['id'] ?? 0) === $staffId) {
                $selectedStaff = $row;
                break;
            }
        }

        $attendanceType = strtolower(trim((string)($input['attendance_type'] ?? 'all')));
        if (!in_array($attendanceType, self::ATTENDANCE_FILTERS, true)) {
            $attendanceType = 'all';
        }

        $mode = strtolower(trim((string)($input['mode'] ?? 'screen'))) === 'print' ? 'print' : 'screen';

        return [
            'staff_id' => $staffId,
            'year' => $year,
            'month' => $month,
            'attendance_type' => $attendanceType,
            'branch' => $branch,
            'department' => $department,
            'mode' => $mode,
            'period_start' => $monthStart->format('Y-m-d'),
            'period_end' => $monthEnd->format('Y-m-d'),
            'period_label' => self::MONTH_LABELS[$month] . ' ' . $year,
            'selected_staff' => $selectedStaff,
            'staff_options' => $staffOptions,
            'year_options' => $yearOptions,
            'month_options' => self::MONTH_LABELS,
            'branch_options' => $branchOptions,
            'department_options' => $departmentOptions,
        ];
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<int,array<string,mixed>>
     */
    public static function getDailyRows(array $filters): array
    {
        return self::buildReport($filters)['rows'];
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public static function getMonthlySummary(array $filters): array
    {
        return self::buildReport($filters)['summary'];
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{rows:array<int,array<string,mixed>>,summary:array<string,mixed>}
     */
    private static function buildReport(array $filters): array
    {
        $cacheKey = md5(json_encode([
            'staff_id' => (int)($filters['staff_id'] ?? 0),
            'year' => (int)($filters['year'] ?? 0),
            'month' => (int)($filters['month'] ?? 0),
            'attendance_type' => (string)($filters['attendance_type'] ?? 'all'),
            'branch' => (string)($filters['branch'] ?? ''),
            'department' => (string)($filters['department'] ?? ''),
        ]));
        if (isset(self::$reportCache[$cacheKey])) {
            return self::$reportCache[$cacheKey];
        }

        $staff = is_array($filters['selected_staff'] ?? null) ? $filters['selected_staff'] : null;
        $staffId = (int)($filters['staff_id'] ?? 0);
        $periodStart = (string)($filters['period_start'] ?? '');
        $periodEnd = (string)($filters['period_end'] ?? '');

        if ($staffId <= 0 || $staff === null || $periodStart === '' || $periodEnd === '') {
            $report = [
                'rows' => [],
                'summary' => self::emptySummary($filters, $staff),
            ];
            self::$reportCache[$cacheKey] = $report;
            return $report;
        }

        $attendanceByDate = [];
        if (self::tableExists('sbaio_attendance_daily')) {
            $attendanceRows = DB::fetchAll(
                'SELECT attendance_date, work_start_at, work_end_at, break_start_at, break_end_at, day_marker
                 FROM sbaio_attendance_daily
                 WHERE staff_id = ? AND attendance_date BETWEEN ? AND ?
                 ORDER BY attendance_date ASC',
                [$staffId, $periodStart, $periodEnd]
            );
            foreach ($attendanceRows as $row) {
                $attendanceByDate[(string)($row['attendance_date'] ?? '')] = $row;
            }
        }

        $scheduleByDate = [];
        if (self::tableExists('sbaio_schedule_assignments')) {
            $scheduleRows = DB::fetchAll(
                'SELECT schedule_date, expected_start_time, expected_end_time, break_start_time, break_end_time, expected_break_minutes
                 FROM sbaio_schedule_assignments
                 WHERE staff_id = ? AND schedule_date BETWEEN ? AND ?
                 ORDER BY schedule_date ASC',
                [$staffId, $periodStart, $periodEnd]
            );
            foreach ($scheduleRows as $row) {
                $scheduleByDate[(string)($row['schedule_date'] ?? '')] = $row;
            }
        }

        $leaveByDate = [];
        if (self::tableExists('sbaio_leave_requests')) {
            $leaveRows = DB::fetchAll(
                "SELECT start_date, end_date, leave_type
                 FROM sbaio_leave_requests
                 WHERE staff_id = ? AND leave_status = 'approved' AND end_date >= ? AND start_date <= ?",
                [$staffId, $periodStart, $periodEnd]
            );
            foreach ($leaveRows as $row) {
                $leaveStart = new \DateTimeImmutable((string)($row['start_date'] ?? $periodStart));
                $leaveEnd = new \DateTimeImmutable((string)($row['end_date'] ?? $periodEnd));
                $days = new \DatePeriod($leaveStart, new \DateInterval('P1D'), $leaveEnd->modify('+1 day'));
                foreach ($days as $date) {
                    $leaveByDate[$date->format('Y-m-d')] = $row;
                }
            }
        }

        $holidayByDate = [];
        if (self::tableExists('sbaio_holiday_calendar')) {
            $holidayRows = DB::fetchAll(
                'SELECT holiday_date, holiday_name
                 FROM sbaio_holiday_calendar
                 WHERE is_active = 1 AND holiday_date BETWEEN ? AND ?',
                [$periodStart, $periodEnd]
            );
            foreach ($holidayRows as $row) {
                $holidayByDate[(string)($row['holiday_date'] ?? '')] = $row;
            }
        }

        $allRows = [];
        $attendanceFilter = (string)($filters['attendance_type'] ?? 'all');
        $days = new \DatePeriod(
            new \DateTimeImmutable($periodStart),
            new \DateInterval('P1D'),
            (new \DateTimeImmutable($periodEnd))->modify('+1 day')
        );

        foreach ($days as $date) {
            $dateKey = $date->format('Y-m-d');
            $attendance = $attendanceByDate[$dateKey] ?? null;
            $schedule = $scheduleByDate[$dateKey] ?? null;
            $leave = $leaveByDate[$dateKey] ?? null;
            $holiday = $holidayByDate[$dateKey] ?? null;

            $row = self::buildDailyRow($dateKey, $attendance, $schedule, $leave, $holiday);
            $allRows[] = $row;
        }

        $rows = self::applyAttendanceFilter($allRows, $attendanceFilter);
        $summary = self::summarizeRows(
            $rows,
            $allRows,
            $staff,
            (int)($filters['year'] ?? 0),
            (int)($filters['month'] ?? 0),
            (string)($filters['period_label'] ?? '')
        );

        $report = [
            'rows' => $rows,
            'summary' => $summary,
        ];
        self::$reportCache[$cacheKey] = $report;
        return $report;
    }

    /**
     * @param ?array<string,mixed> $attendance
     * @param ?array<string,mixed> $schedule
     * @param ?array<string,mixed> $leave
     * @param ?array<string,mixed> $holiday
     * @return array<string,mixed>
     */
    private static function buildDailyRow(
        string $dateKey,
        ?array $attendance,
        ?array $schedule,
        ?array $leave,
        ?array $holiday
    ): array {
        $marker = strtolower(trim((string)($attendance['day_marker'] ?? '')));
        $workStart = (string)($attendance['work_start_at'] ?? '');
        $workEnd = (string)($attendance['work_end_at'] ?? '');
        $breakStart = (string)($attendance['break_start_at'] ?? '');
        $breakEnd = (string)($attendance['break_end_at'] ?? '');
        $workedMinutes = self::workedMinutes($workStart, $workEnd, $breakStart, $breakEnd);

        $scheduleDefined = trim((string)($schedule['expected_start_time'] ?? '')) !== ''
            || trim((string)($schedule['expected_end_time'] ?? '')) !== ''
            || (int)($schedule['expected_break_minutes'] ?? 0) > 0;
        $hasAttendance = $workStart !== '' || $workEnd !== '' || $breakStart !== '' || $breakEnd !== '' || $marker === 'workday' || $marker === 'present';

        $attendanceType = 'off';
        if ($leave !== null || $marker === 'leave') {
            $attendanceType = 'leave';
        } elseif ($holiday !== null || in_array($marker, ['holiday', 'public_holiday'], true)) {
            $attendanceType = 'holiday';
        } elseif ($hasAttendance) {
            $attendanceType = 'present';
        } elseif ($scheduleDefined) {
            $attendanceType = 'absent';
        }

        $attendanceLabel = match ($attendanceType) {
            'leave' => self::titleize((string)($leave['leave_type'] ?? 'Leave')),
            'holiday' => trim((string)($holiday['holiday_name'] ?? '')) !== '' ? (string)$holiday['holiday_name'] : 'Holiday',
            'present' => 'Present',
            'absent' => 'Absent',
            default => 'Off',
        };

        return [
            'date' => $dateKey,
            'date_label' => (new \DateTimeImmutable($dateKey))->format('Y-m-d'),
            'day' => (new \DateTimeImmutable($dateKey))->format('D'),
            'attendance' => $attendanceLabel,
            'attendance_type' => $attendanceType,
            'start_time' => self::formatTime($workStart),
            'end_time' => self::formatTime($workEnd),
            'break_start' => self::formatTime($breakStart),
            'break_end' => self::formatTime($breakEnd),
            'work_hours' => self::formatHoursDecimal($workedMinutes),
            'worked_minutes' => $workedMinutes,
            'is_operational_day' => in_array($attendanceType, ['present', 'leave', 'holiday', 'absent'], true),
            'is_paid_day' => in_array($attendanceType, ['present', 'leave', 'holiday'], true),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private static function applyAttendanceFilter(array $rows, string $attendanceFilter): array
    {
        if ($attendanceFilter === 'all') {
            return $rows;
        }

        return array_values(array_filter(
            $rows,
            static fn(array $row): bool => (string)($row['attendance_type'] ?? '') === $attendanceFilter
        ));
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<int,array<string,mixed>> $allRows
     * @param array<string,mixed> $staff
     * @return array<string,mixed>
     */
    private static function summarizeRows(array $rows, array $allRows, array $staff, int $year, int $month, string $periodLabel): array
    {
        $totalWorkedMinutes = 0;
        $totalWorkDays = 0;
        $presentDays = 0;
        $holidayDays = 0;
        $paidVisibleDays = 0;

        foreach ($rows as $row) {
            $totalWorkedMinutes += (int)($row['worked_minutes'] ?? 0);
            if (!empty($row['is_operational_day'])) {
                $totalWorkDays++;
            }
            if ((string)($row['attendance_type'] ?? '') === 'present') {
                $presentDays++;
            }
            if ((string)($row['attendance_type'] ?? '') === 'holiday') {
                $holidayDays++;
            }
            if (!empty($row['is_paid_day'])) {
                $paidVisibleDays++;
            }
        }

        $allOperationalDays = 0;
        foreach ($allRows as $row) {
            if (!empty($row['is_operational_day'])) {
                $allOperationalDays++;
            }
        }

        $salaryEstimate = self::estimateSalary(
            $staff,
            $year,
            $month,
            $totalWorkedMinutes,
            $paidVisibleDays,
            $totalWorkDays,
            $allOperationalDays
        );

        return [
            'selected_staff_name' => trim((string)($staff['full_name'] ?? '')) !== '' ? (string)$staff['full_name'] : 'No staff selected',
            'selected_staff_code' => trim((string)($staff['employee_code'] ?? '')),
            'branch_name' => trim((string)($staff['branch_name'] ?? '')),
            'department_name' => trim((string)($staff['department_name'] ?? '')),
            'salary_type' => trim((string)($staff['salary_type'] ?? '')),
            'period_label' => $periodLabel,
            'total_salary' => $salaryEstimate['amount'],
            'total_salary_label' => self::formatMoney($salaryEstimate['amount']),
            'salary_note' => $salaryEstimate['note'],
            'total_work_hours' => self::formatHoursLabel($totalWorkedMinutes),
            'total_work_hours_decimal' => self::formatHoursDecimal($totalWorkedMinutes) . ' hrs',
            'total_work_days' => $totalWorkDays,
            'present_days' => $presentDays,
            'holiday_days' => $holidayDays,
            'row_count' => count($rows),
        ];
    }

    /**
     * @param ?array<string,mixed> $staff
     * @return array<string,mixed>
     */
    private static function emptySummary(array $filters, ?array $staff): array
    {
        return [
            'selected_staff_name' => trim((string)($staff['full_name'] ?? '')) !== '' ? (string)$staff['full_name'] : 'No staff selected',
            'selected_staff_code' => trim((string)($staff['employee_code'] ?? '')),
            'branch_name' => trim((string)($staff['branch_name'] ?? '')),
            'department_name' => trim((string)($staff['department_name'] ?? '')),
            'salary_type' => trim((string)($staff['salary_type'] ?? '')),
            'period_label' => (string)($filters['period_label'] ?? ''),
            'total_salary' => 0.0,
            'total_salary_label' => self::formatMoney(0.0),
            'salary_note' => 'No salary estimate available for the selected filters.',
            'total_work_hours' => self::formatHoursLabel(0),
            'total_work_hours_decimal' => '0.00 hrs',
            'total_work_days' => 0,
            'present_days' => 0,
            'holiday_days' => 0,
            'row_count' => 0,
        ];
    }

    /**
     * @param array<string,mixed> $staff
     * @return array{amount:float,note:string}
     */
    private static function estimateSalary(
        array $staff,
        int $year,
        int $month,
        int $visibleWorkedMinutes,
        int $paidVisibleDays,
        int $visibleOperationalDays,
        int $allOperationalDays
    ): array {
        $salaryType = self::normalizeSalaryType((string)($staff['salary_type'] ?? ''));
        $salaryRate = isset($staff['salary_rate']) ? (float)$staff['salary_rate'] : 0.0;
        $monthlyReference = self::lookupMonthlySalaryReference(
            (int)($staff['id'] ?? 0),
            (string)($staff['name_ref'] ?? ''),
            $year,
            $month
        );

        if ($monthlyReference !== null) {
            $amount = $monthlyReference;
            $note = 'Based on imported monthly salary reference.';
            if ($allOperationalDays > 0 && $visibleOperationalDays > 0 && $visibleOperationalDays < $allOperationalDays) {
                $amount = round($monthlyReference * ($visibleOperationalDays / $allOperationalDays), 2);
                $note = 'Prorated from imported monthly salary reference for the visible rows.';
            }
            return ['amount' => $amount, 'note' => $note];
        }

        if ($salaryType === 'hourly') {
            return [
                'amount' => round(($visibleWorkedMinutes / 60) * $salaryRate, 2),
                'note' => 'Estimated from hourly rate and worked hours.',
            ];
        }

        if ($salaryType === 'daily') {
            return [
                'amount' => round($paidVisibleDays * $salaryRate, 2),
                'note' => 'Estimated from daily rate and paid days.',
            ];
        }

        $amount = $salaryRate;
        $note = $salaryRate > 0 ? 'Estimated from monthly/fixed staff rate.' : 'No salary reference found for this staff member.';
        if ($salaryRate > 0 && $allOperationalDays > 0 && $visibleOperationalDays > 0 && $visibleOperationalDays < $allOperationalDays) {
            $amount = round($salaryRate * ($visibleOperationalDays / $allOperationalDays), 2);
            $note = 'Prorated from monthly/fixed staff rate for the visible rows.';
        }

        return ['amount' => round($amount, 2), 'note' => $note];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function staffRows(string $branch, string $department): array
    {
        if (!self::tableExists('sbaio_staff')) {
            return [];
        }

        $where = ["COALESCE(status, 'active') <> 'archived'"];
        $params = [];
        if ($branch !== '') {
            $where[] = 'branch_name = ?';
            $params[] = $branch;
        }
        if ($department !== '') {
            $where[] = 'department_name = ?';
            $params[] = $department;
        }

        return DB::fetchAll(
            'SELECT id, employee_code, full_name, name_ref, branch_name, department_name, salary_type, salary_rate, employment_status, status
             FROM sbaio_staff
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY CASE WHEN COALESCE(employment_status, \'active\') = \'active\' THEN 0 ELSE 1 END, full_name ASC',
            $params
        );
    }

    /**
     * @return array<int,string>
     */
    private static function staffDimensionOptions(string $column): array
    {
        if (!self::tableExists('sbaio_staff')) {
            return [];
        }

        $rows = DB::fetchAll(
            'SELECT DISTINCT ' . $column . ' AS value
             FROM sbaio_staff
             WHERE COALESCE(status, \'active\') <> \'archived\' AND ' . $column . ' IS NOT NULL AND TRIM(' . $column . ') <> \'\'
             ORDER BY ' . $column . ' ASC'
        );

        return array_values(array_filter(array_map(
            static fn(array $row): string => trim((string)($row['value'] ?? '')),
            $rows
        )));
    }

    /**
     * @return array<int,int>
     */
    private static function yearOptions(int $fallbackYear): array
    {
        $years = [];
        foreach (self::yearQuerySpecs() as $spec) {
            if (!self::tableExists($spec['table'])) {
                continue;
            }
            $rows = DB::fetchAll(
                'SELECT DISTINCT YEAR(' . $spec['column'] . ') AS year_value
                 FROM ' . $spec['table'] . '
                 WHERE ' . $spec['column'] . ' IS NOT NULL
                 ORDER BY year_value DESC'
            );
            foreach ($rows as $row) {
                $year = (int)($row['year_value'] ?? 0);
                if ($year > 0) {
                    $years[$year] = $year;
                }
            }
        }

        if ($years === []) {
            $years[$fallbackYear] = $fallbackYear;
        }

        rsort($years);
        return array_values($years);
    }

    /**
     * @return array{year:int,month:int}
     */
    private static function latestDataPeriod(): array
    {
        $latest = null;
        foreach (self::latestDateSpecs() as $spec) {
            if (!self::tableExists($spec['table'])) {
                continue;
            }
            $row = DB::fetchOne(
                'SELECT MAX(' . $spec['column'] . ') AS latest_date FROM ' . $spec['table']
            );
            $candidate = trim((string)($row['latest_date'] ?? ''));
            if ($candidate === '') {
                continue;
            }
            if ($latest === null || $candidate > $latest) {
                $latest = $candidate;
            }
        }

        $date = $latest !== null ? new \DateTimeImmutable($latest) : new \DateTimeImmutable('today');
        return [
            'year' => (int)$date->format('Y'),
            'month' => (int)$date->format('n'),
        ];
    }

    private static function lookupMonthlySalaryReference(int $staffId, string $nameRef, int $year, int $month): ?float
    {
        if ($year <= 0 || $month <= 0 || !self::tableExists('sbaio_salary_monthly_rates')) {
            return null;
        }

        if ($staffId > 0) {
            $row = DB::fetchOne(
                'SELECT reference_amount
                 FROM sbaio_salary_monthly_rates
                 WHERE staff_id = ? AND period_year = ? AND period_month = ?
                 LIMIT 1',
                [$staffId, $year, $month]
            );
            if (($row['reference_amount'] ?? null) !== null) {
                return (float)$row['reference_amount'];
            }
        }

        $nameRef = trim($nameRef);
        if ($nameRef === '') {
            return null;
        }

        $row = DB::fetchOne(
            'SELECT reference_amount
             FROM sbaio_salary_monthly_rates
             WHERE legacy_name_ref = ? AND period_year = ? AND period_month = ?
             LIMIT 1',
            [$nameRef, $year, $month]
        );
        if (($row['reference_amount'] ?? null) === null) {
            return null;
        }
        return (float)$row['reference_amount'];
    }

    /**
     * @return array<int,array{table:string,column:string}>
     */
    private static function latestDateSpecs(): array
    {
        return [
            ['table' => 'sbaio_attendance_daily', 'column' => 'attendance_date'],
            ['table' => 'sbaio_schedule_assignments', 'column' => 'schedule_date'],
            ['table' => 'sbaio_leave_requests', 'column' => 'end_date'],
            ['table' => 'sbaio_holiday_calendar', 'column' => 'holiday_date'],
            ['table' => 'sbaio_timecards_daily', 'column' => 'work_date'],
        ];
    }

    /**
     * @return array<int,array{table:string,column:string}>
     */
    private static function yearQuerySpecs(): array
    {
        return [
            ['table' => 'sbaio_attendance_daily', 'column' => 'attendance_date'],
            ['table' => 'sbaio_schedule_assignments', 'column' => 'schedule_date'],
            ['table' => 'sbaio_leave_requests', 'column' => 'start_date'],
            ['table' => 'sbaio_leave_requests', 'column' => 'end_date'],
            ['table' => 'sbaio_holiday_calendar', 'column' => 'holiday_date'],
            ['table' => 'sbaio_timecards_daily', 'column' => 'work_date'],
        ];
    }

    private static function workedMinutes(string $start, string $end, string $breakStart, string $breakEnd): int
    {
        if ($start === '' || $end === '') {
            return 0;
        }

        return max(0, self::minutesBetween($start, $end) - self::minutesBetween($breakStart, $breakEnd));
    }

    private static function minutesBetween(string $start, string $end): int
    {
        if ($start === '' || $end === '') {
            return 0;
        }

        $from = strtotime($start);
        $to = strtotime($end);
        if ($from === false || $to === false) {
            return 0;
        }

        return max(0, (int)floor(($to - $from) / 60));
    }

    private static function formatTime(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return '';
        }

        return date('H:i', $timestamp);
    }

    private static function formatHoursDecimal(int $minutes): string
    {
        return number_format(max(0, $minutes) / 60, 2, '.', '');
    }

    private static function formatHoursLabel(int $minutes): string
    {
        return self::formatHoursDecimal($minutes) . ' hrs';
    }

    private static function formatMoney(float $amount): string
    {
        return number_format($amount, 2, '.', ',');
    }

    private static function normalizeSalaryType(string $salaryType): string
    {
        $normalized = strtolower(trim($salaryType));
        return match ($normalized) {
            'hourly', 'hour' => 'hourly',
            'daily', 'day' => 'daily',
            'monthly', 'fixed', 'salary' => 'monthly',
            default => $normalized,
        };
    }

    private static function titleize(string $value): string
    {
        $value = str_replace(['_', '-'], ' ', trim($value));
        return $value === '' ? 'Leave' : ucwords($value);
    }

    private static function validatedOption(string $value, array $allowedOptions): string
    {
        $value = trim($value);
        return in_array($value, $allowedOptions, true) ? $value : '';
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function staffOptionLabel(array $row): string
    {
        $name = trim((string)($row['full_name'] ?? ''));
        $code = trim((string)($row['employee_code'] ?? ''));
        return $code !== '' ? $name . ' (' . $code . ')' : $name;
    }

    private static function tableExists(string $table): bool
    {
        static $cache = [];
        if (isset($cache[$table])) {
            return $cache[$table];
        }

        try {
            $cache[$table] = DB::fetchOne(
                'SELECT 1
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = ?
                 LIMIT 1',
                [$table]
            ) !== null;
        } catch (\Throwable) {
            $cache[$table] = false;
        }

        return $cache[$table];
    }
}

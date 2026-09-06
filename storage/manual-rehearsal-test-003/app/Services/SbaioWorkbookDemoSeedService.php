<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

final class SbaioWorkbookDemoSeedService
{
    private const WORKBOOK_SETTING_KEY = 'demo.sbaio.workbook_path';
    private const WORKBOOK_SOURCE_LABEL = 'workbook_demo_seed';
    private const SALARY_SOURCE_LABEL = 'workbook_salary_sheet_demo';
    private const FIXED_SOURCE_LABEL = 'workbook_fulltime_rates_demo';
    private const STAFF_CODE_PREFIX = 'WB-SBAIO-';
    private const LEAVE_CODE_PREFIX = 'WB-SBAIO-LEAVE-';
    private const HOLIDAY_CODE_PREFIX = 'WB-SBAIO-HOLIDAY-';
    private const SALE_REF_PREFIX = 'WB-SBAIO-SALE-';
    private const EXPENSE_REF_PREFIX = 'WB-SBAIO-EXP-';
    private const TEMPLATE_PREFIX = '[WB SBAIO] ';

    /**
     * @return array<string,int>
     */
    public function seed(): array
    {
        $workbook = $this->openWorkbook($this->resolveWorkbookPath());
        $salaryReferences = $this->parseSalaryReferences($workbook);
        $fixedProfiles = $this->parseFixedProfiles($workbook);
        $staffRecords = $this->collectStaffRecords($workbook, $salaryReferences, $fixedProfiles);
        $staffMap = $this->seedStaff($staffRecords, $salaryReferences, $fixedProfiles);

        $counts = [
            'sbaio_staff' => count($staffMap),
        ];

        $scheduleCounts = $this->seedSchedules($workbook, $staffMap);
        $holidayCount = $this->seedHolidayCalendar($workbook);
        $leaveCount = $this->seedLeaveRequests($workbook, $staffMap);
        $attendanceSeed = $this->seedAttendanceFromWorkbook($workbook, $staffMap);
        $salaryCount = $this->seedSalaryReferences($salaryReferences, $staffMap);
        $fixedCount = $this->seedFixedProfiles($fixedProfiles, $staffMap);
        $opsCounts = $this->seedSalesAndExpenses($workbook);
        $generated = $this->generateTimecards($attendanceSeed['months'], array_values(array_map(
            static fn(array $row): int => (int)($row['id'] ?? 0),
            $staffMap
        )));
        $this->approveGeneratedPeriods($generated['period_keys']);
        $payrollCounts = $this->scaffoldLatestPayrollRun($generated['period_keys']);

        $counts['sbaio_schedule_templates'] = $scheduleCounts['templates'];
        $counts['sbaio_schedule_assignments'] = $scheduleCounts['assignments'];
        $counts['sbaio_holiday_calendar'] = $holidayCount;
        $counts['sbaio_leave_requests'] = $leaveCount;
        $counts['sbaio_attendance_daily'] = $attendanceSeed['attendance_rows'];
        $counts['sbaio_salary_monthly_rates'] = $salaryCount;
        $counts['sbaio_payroll_fixed_profiles'] = $fixedCount;
        $counts['sbaio_sales'] = $opsCounts['sales'];
        $counts['sbaio_expenses'] = $opsCounts['expenses'];
        $counts['sbaio_timecards_daily'] = $generated['daily_rows'];
        $counts['sbaio_timecard_periods'] = $generated['period_rows'];
        $counts['sbaio_payroll_runs'] = $payrollCounts['runs'];
        $counts['sbaio_payroll_records'] = $payrollCounts['records'];

        return array_filter($counts, static fn(int $count): bool => $count > 0);
    }

    private function resolveWorkbookPath(): string
    {
        $configured = '';
        try {
            $row = DB::fetchOne('SELECT setting_value FROM core_settings WHERE setting_key = ? LIMIT 1', [self::WORKBOOK_SETTING_KEY]);
            $configured = trim((string)($row['setting_value'] ?? ''));
        } catch (\Throwable) {
            $configured = '';
        }

        $candidates = [];
        if ($configured !== '') {
            $candidates[] = $configured;
        }
        if (defined('APP_ROOT')) {
            $candidates[] = APP_ROOT . '/storage/demo/sbaio-foundation.xlsm';
            $candidates[] = APP_ROOT . '/storage/demo/SBAIO.xlsm';
            $candidates[] = APP_ROOT . '/storage/demo/SBAIO.xlsm.xlsx';
        }

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && is_file($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException(
            'SBAIO workbook demo source was not found. Set core setting "' . self::WORKBOOK_SETTING_KEY . '" or place the workbook at storage/demo/sbaio-foundation.xlsm.'
        );
    }

    /**
     * @param array<string,array{rows:array<int,array<string,string>>}> $workbook
     * @param array<string,array<string,mixed>> $salaryReferences
     * @param array<string,array<string,mixed>> $fixedProfiles
     * @return array<string,array<string,mixed>>
     */
    private function collectStaffRecords(array $workbook, array $salaryReferences, array $fixedProfiles): array
    {
        $records = [];

        foreach (['Staffs2026', 'Staffs'] as $sheetName) {
            foreach ($this->rowsAfterHeader($workbook, $sheetName, 4) as $rowNumber => $row) {
                $fullName = $this->normalizeFullName((string)($row['C'] ?? ''));
                $nameRef = $this->normalizeNameRef((string)($row['M'] ?? ''));
                if ($nameRef === '' && $fullName !== '') {
                    $nameRef = $this->nameRefFromFullName($fullName);
                }
                if ($nameRef === '') {
                    continue;
                }

                $record = $records[$nameRef] ?? $this->emptyStaffRecord($nameRef);
                $record['legacy_sn'] = $this->preferValue($record['legacy_sn'], trim((string)($row['B'] ?? '')));
                $record['full_name'] = $this->preferValue($record['full_name'], $fullName);
                $record['salary_type'] = $this->preferSalaryType((string)$record['salary_type'], (string)($row['D'] ?? ''));
                $record['nationality'] = $this->preferValue($record['nationality'], trim((string)($row['E'] ?? '')));
                $record['gender'] = $this->preferValue($record['gender'], trim((string)($row['F'] ?? '')));
                $record['date_of_birth'] = $record['date_of_birth'] ?? $this->excelSerialToDateString((string)($row['G'] ?? ''), false);
                $record['join_date'] = $record['join_date'] ?? $this->excelSerialToDateString((string)($row['H'] ?? ''), true);
                $record['exit_date'] = $record['exit_date'] ?? $this->excelSerialToDateString((string)($row['I'] ?? ''), true);
                $record['legacy_job_details'] = $this->preferValue($record['legacy_job_details'], trim((string)($row['L'] ?? '')));
                $records[$nameRef] = $this->decorateStaffRecord($record, $salaryReferences[$nameRef] ?? null, $fixedProfiles[$nameRef] ?? null);
            }
        }

        foreach ($this->collectSupplementalNameRefs($workbook, $salaryReferences, $fixedProfiles) as $nameRef) {
            if ($nameRef === '') {
                continue;
            }
            $record = $records[$nameRef] ?? $this->emptyStaffRecord($nameRef);
            $record['full_name'] = $this->preferValue((string)$record['full_name'], $this->fullNameFromNameRef($nameRef));
            $records[$nameRef] = $this->decorateStaffRecord($record, $salaryReferences[$nameRef] ?? null, $fixedProfiles[$nameRef] ?? null);
        }

        uasort(
            $records,
            static fn(array $left, array $right): int => strcmp((string)($left['full_name'] ?? ''), (string)($right['full_name'] ?? ''))
        );

        $sequence = 1;
        foreach ($records as $nameRef => $record) {
            $legacySn = trim((string)($record['legacy_sn'] ?? ''));
            $suffix = $legacySn !== '' ? preg_replace('/[^A-Za-z0-9]+/', '', $legacySn) : str_pad((string)$sequence, 3, '0', STR_PAD_LEFT);
            $suffix = $suffix !== '' ? $suffix : str_pad((string)$sequence, 3, '0', STR_PAD_LEFT);
            $records[$nameRef]['employee_code'] = self::STAFF_CODE_PREFIX . strtoupper($suffix);
            $sequence++;
        }

        return $records;
    }

    /**
     * @param array<string,array<string,mixed>> $salaryReferences
     * @param array<string,array<string,mixed>> $fixedProfiles
     * @return array<int,string>
     */
    private function collectSupplementalNameRefs(array $workbook, array $salaryReferences, array $fixedProfiles): array
    {
        $nameRefs = array_keys($salaryReferences + $fixedProfiles);

        foreach ($this->rowsAfterHeader($workbook, 'TimeCard', 16) as $row) {
            $nameRef = $this->normalizeNameRef((string)($row['B'] ?? ''));
            if ($nameRef !== '') {
                $nameRefs[] = $nameRef;
            }
        }

        foreach ($this->rowsAfterHeader($workbook, 'Shift', 17) as $row) {
            $nameRef = $this->normalizeNameRef((string)($row['C'] ?? ''));
            if ($nameRef !== '') {
                $nameRefs[] = $nameRef;
            }
        }

        foreach ($this->rowsAfterHeader($workbook, 'Leave', 8) as $row) {
            $nameRef = $this->normalizeNameRef((string)($row['B'] ?? ''));
            if ($nameRef !== '') {
                $nameRefs[] = $nameRef;
            }
        }

        $nameRefs = array_values(array_unique(array_filter($nameRefs, static fn(string $value): bool => $value !== '')));
        sort($nameRefs);
        return $nameRefs;
    }

    /**
     * @param array<string,array<string,mixed>> $records
     * @param array<string,array<string,mixed>> $salaryReferences
     * @param array<string,array<string,mixed>> $fixedProfiles
     * @return array<string,array{id:int,full_name:string,name_ref:string,employee_code:string,salary_type:string}>
     */
    private function seedStaff(array $records, array $salaryReferences, array $fixedProfiles): array
    {
        $map = [];
        foreach ($records as $nameRef => $record) {
            $fullName = trim((string)($record['full_name'] ?? ''));
            if ($fullName === '') {
                $fullName = $this->fullNameFromNameRef($nameRef);
            }
            $salaryType = $this->normalizeSalaryType((string)($record['salary_type'] ?? ''));
            $salaryType = $salaryType !== '' ? $salaryType : $this->normalizeSalaryType((string)($salaryReferences[$nameRef]['salary_type'] ?? ''));

            $salaryRate = $record['salary_rate'] ?? null;
            if ($salaryRate === null && isset($salaryReferences[$nameRef]['current_rate'])) {
                $salaryRate = (float)$salaryReferences[$nameRef]['current_rate'];
            }
            if ($salaryRate === null && isset($fixedProfiles[$nameRef]['basic_salary_amount'])) {
                $salaryRate = (float)$fixedProfiles[$nameRef]['basic_salary_amount'];
            }

            $employmentStatus = ((string)($record['exit_date'] ?? '')) !== '' ? 'inactive' : 'active';

            DB::query(
                'INSERT INTO sbaio_staff (
                    legacy_sn, employee_code, full_name, name_ref, email, role_label, staff_type, salary_type, salary_rate,
                    helper_classification, nationality, gender, date_of_birth, employment_status, department_name, branch_name,
                    hire_date, join_date, exit_date, legacy_job_details, residence_card_front, residence_card_back, status
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [
                    $this->nullIfBlank((string)($record['legacy_sn'] ?? '')),
                    (string)($record['employee_code'] ?? ''),
                    $fullName,
                    $nameRef,
                    $this->nullIfBlank((string)($record['email'] ?? '')),
                    $this->nullIfBlank((string)($record['role_label'] ?? '')),
                    $this->nullIfBlank((string)($record['staff_type'] ?? '')),
                    $this->nullIfBlank($salaryType),
                    $salaryRate,
                    $this->nullIfBlank((string)($record['helper_classification'] ?? '')),
                    $this->nullIfBlank((string)($record['nationality'] ?? '')),
                    $this->nullIfBlank((string)($record['gender'] ?? '')),
                    $this->nullIfBlank((string)($record['date_of_birth'] ?? '')),
                    $employmentStatus,
                    $this->nullIfBlank((string)($record['department_name'] ?? '')),
                    $this->nullIfBlank((string)($record['branch_name'] ?? '')),
                    $this->nullIfBlank((string)($record['hire_date'] ?? '')),
                    $this->nullIfBlank((string)($record['join_date'] ?? '')),
                    $this->nullIfBlank((string)($record['exit_date'] ?? '')),
                    $this->nullIfBlank((string)($record['legacy_job_details'] ?? '')),
                    null,
                    null,
                    'active',
                ]
            );

            $map[$nameRef] = [
                'id' => (int)DB::conn()->insert_id,
                'full_name' => $fullName,
                'name_ref' => $nameRef,
                'employee_code' => (string)($record['employee_code'] ?? ''),
                'salary_type' => $salaryType,
            ];
        }

        return $map;
    }

    /**
     * @param array<string,array{id:int,full_name:string,name_ref:string,employee_code:string,salary_type:string}> $staffMap
     * @return array{templates:int,assignments:int}
     */
    private function seedSchedules(array $workbook, array $staffMap): array
    {
        $templates = [];
        $templateIds = [];
        $templateCount = 0;
        $assignmentCount = 0;

        foreach ($this->rowsAfterHeader($workbook, 'Shift', 17) as $row) {
            $nameRef = $this->normalizeNameRef((string)($row['C'] ?? ''));
            if ($nameRef === '' || !isset($staffMap[$nameRef])) {
                continue;
            }

            $rangeStart = $this->excelDate((string)($row['E'] ?? ''), true);
            $rangeEnd = $this->excelDate((string)($row['F'] ?? ''), true);
            if (!($rangeStart instanceof \DateTimeImmutable) || !($rangeEnd instanceof \DateTimeImmutable) || $rangeEnd < $rangeStart) {
                continue;
            }

            $dayOfWeek = $this->normalizeWeekday((string)($row['D'] ?? ''));
            $startTime = $this->excelTime((string)($row['G'] ?? ''));
            $endTime = $this->excelTime((string)($row['H'] ?? ''));
            $breakStart = $this->excelTime((string)($row['I'] ?? ''));
            $breakEnd = $this->excelTime((string)($row['J'] ?? ''));
            $jobLabel = $this->normalizeTitle((string)($row['K'] ?? ''));
            $scheduledMinutes = $this->resolvedScheduledMinutes($startTime, $endTime, $breakStart, $breakEnd, (string)($row['L'] ?? ''));

            $templateKey = implode('|', [$dayOfWeek, $startTime, $endTime, $breakStart, $breakEnd, $jobLabel]);
            if (!isset($templateIds[$templateKey])) {
                $templateName = self::TEMPLATE_PREFIX . trim(implode(' ', array_filter([
                    $jobLabel,
                    $dayOfWeek,
                    $startTime !== '' || $endTime !== '' ? $startTime . ($endTime !== '' ? '-' . $endTime : '') : '',
                ])));

                DB::query(
                    'INSERT INTO sbaio_schedule_templates (
                        template_name, shift_code, day_of_week, range_start_date, range_end_date, expected_start_time, expected_end_time,
                        break_start_time, break_end_time, expected_break_minutes, job_label, scheduled_minutes, legacy_name_ref, is_active
                    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1)',
                    [
                        $templateName !== self::TEMPLATE_PREFIX ? $templateName : self::TEMPLATE_PREFIX . 'Shift',
                        $this->templateCode($dayOfWeek, $jobLabel, $startTime),
                        $this->nullIfBlank($dayOfWeek),
                        $rangeStart->format('Y-m-d'),
                        $rangeEnd->format('Y-m-d'),
                        $this->nullIfBlank($startTime),
                        $this->nullIfBlank($endTime),
                        $this->nullIfBlank($breakStart),
                        $this->nullIfBlank($breakEnd),
                        $this->breakMinutes($breakStart, $breakEnd),
                        $this->nullIfBlank($jobLabel),
                        $scheduledMinutes,
                        $nameRef,
                    ]
                );
                $templateIds[$templateKey] = (int)DB::conn()->insert_id;
                $templateCount++;
            }

            $period = new \DatePeriod($rangeStart, new \DateInterval('P1D'), $rangeEnd->modify('+1 day'));
            foreach ($period as $date) {
                if ($dayOfWeek !== '' && $date->format('D') !== $dayOfWeek) {
                    continue;
                }

                DB::query(
                    'INSERT INTO sbaio_schedule_assignments (
                        staff_id, legacy_name_ref, schedule_date, template_id, expected_start_time, expected_end_time, break_start_time, break_end_time,
                        expected_break_minutes, day_of_week, range_start_date, range_end_date, job_label, scheduled_minutes, assignment_status
                    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE
                        legacy_name_ref=VALUES(legacy_name_ref),
                        template_id=VALUES(template_id),
                        expected_start_time=VALUES(expected_start_time),
                        expected_end_time=VALUES(expected_end_time),
                        break_start_time=VALUES(break_start_time),
                        break_end_time=VALUES(break_end_time),
                        expected_break_minutes=VALUES(expected_break_minutes),
                        day_of_week=VALUES(day_of_week),
                        range_start_date=VALUES(range_start_date),
                        range_end_date=VALUES(range_end_date),
                        job_label=VALUES(job_label),
                        scheduled_minutes=VALUES(scheduled_minutes),
                        assignment_status=VALUES(assignment_status)',
                    [
                        $staffMap[$nameRef]['id'],
                        $nameRef,
                        $date->format('Y-m-d'),
                        $templateIds[$templateKey],
                        $this->nullIfBlank($startTime),
                        $this->nullIfBlank($endTime),
                        $this->nullIfBlank($breakStart),
                        $this->nullIfBlank($breakEnd),
                        $this->breakMinutes($breakStart, $breakEnd),
                        $this->nullIfBlank($dayOfWeek),
                        $rangeStart->format('Y-m-d'),
                        $rangeEnd->format('Y-m-d'),
                        $this->nullIfBlank($jobLabel),
                        $scheduledMinutes,
                        'planned',
                    ]
                );
                $assignmentCount++;
            }
        }

        return [
            'templates' => $templateCount,
            'assignments' => $assignmentCount,
        ];
    }

    private function seedHolidayCalendar(array $workbook): int
    {
        $count = 0;
        foreach ($this->rowsAfterHeader($workbook, 'Calendar', 5) as $rowNumber => $row) {
            $date = $this->excelDate((string)($row['B'] ?? ''), true);
            if (!($date instanceof \DateTimeImmutable)) {
                continue;
            }

            $holidayName = trim((string)($row['D'] ?? ''));
            $closed = trim((string)($row['E'] ?? ''));
            if ($holidayName === '' && $closed === '') {
                continue;
            }

            DB::query(
                'INSERT INTO sbaio_holiday_calendar (holiday_date, holiday_name, holiday_type, holiday_code, is_closed_day, notes, is_active)
                 VALUES (?,?,?,?,?,?,1)
                 ON DUPLICATE KEY UPDATE holiday_name=VALUES(holiday_name), holiday_type=VALUES(holiday_type), holiday_code=VALUES(holiday_code), is_closed_day=VALUES(is_closed_day), notes=VALUES(notes), is_active=1',
                [
                    $date->format('Y-m-d'),
                    $holidayName !== '' ? $holidayName : 'Closed Day',
                    $closed !== '' ? 'closed_day' : 'public_holiday',
                    self::HOLIDAY_CODE_PREFIX . $date->format('Ymd') . '-' . str_pad((string)$rowNumber, 3, '0', STR_PAD_LEFT),
                    $closed !== '' ? 1 : 0,
                    $closed !== '' ? 'Workbook closed marker: ' . $closed : 'Seeded from workbook calendar.',
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * @param array<string,array{id:int,full_name:string,name_ref:string,employee_code:string,salary_type:string}> $staffMap
     */
    private function seedLeaveRequests(array $workbook, array $staffMap): int
    {
        $count = 0;
        foreach ($this->rowsAfterHeader($workbook, 'Leave', 8) as $rowNumber => $row) {
            $nameRef = $this->normalizeNameRef((string)($row['B'] ?? ''));
            if ($nameRef === '' || !isset($staffMap[$nameRef])) {
                continue;
            }

            $start = $this->excelDate((string)($row['D'] ?? ''), true);
            $end = $this->excelDate((string)($row['E'] ?? ''), true);
            if (!($start instanceof \DateTimeImmutable) || !($end instanceof \DateTimeImmutable) || $end < $start) {
                continue;
            }

            $payment = strtolower(trim((string)($row['F'] ?? '')));
            $duration = strtolower(trim((string)($row['G'] ?? '')));
            $approval = strtolower(trim((string)($row['H'] ?? '')));

            DB::query(
                'INSERT INTO sbaio_leave_requests (
                    staff_id, legacy_name_ref, leave_code, leave_type, start_date, end_date, partial_day_unit, leave_status, notes, approved_by, approved_at
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                [
                    $staffMap[$nameRef]['id'],
                    $nameRef,
                    self::LEAVE_CODE_PREFIX . $start->format('Ymd') . '-' . str_pad((string)$rowNumber, 3, '0', STR_PAD_LEFT),
                    $payment === 'paid' ? 'paid_leave' : 'leave',
                    $start->format('Y-m-d'),
                    $end->format('Y-m-d'),
                    $duration === 'half' ? 'half_day' : null,
                    $approval === 'approved' ? 'approved' : 'pending',
                    $this->leaveNote($payment, $duration, $approval),
                    $approval === 'approved' ? 'workbook.demo' : null,
                    $approval === 'approved' ? date('Y-m-d H:i:s') : null,
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * @param array<string,array{id:int,full_name:string,name_ref:string,employee_code:string,salary_type:string}> $staffMap
     * @return array{attendance_rows:int,months:array<int,string>}
     */
    private function seedAttendanceFromWorkbook(array $workbook, array $staffMap): array
    {
        $attendanceRows = 0;
        $months = [];

        foreach ($this->rowsAfterHeader($workbook, 'TimeCard', 16) as $row) {
            $nameRef = $this->normalizeNameRef((string)($row['B'] ?? ''));
            if ($nameRef === '' || !isset($staffMap[$nameRef])) {
                continue;
            }

            $date = $this->excelDate((string)($row['C'] ?? ''), true);
            if (!($date instanceof \DateTimeImmutable)) {
                continue;
            }

            $startTime = $this->excelTime((string)($row['D'] ?? ''));
            $endTime = $this->excelTime((string)($row['E'] ?? ''));
            $breakStart = $this->excelTime((string)($row['F'] ?? ''));
            $breakEnd = $this->excelTime((string)($row['G'] ?? ''));
            $marker = $this->attendanceMarker((string)($row['I'] ?? ''));

            DB::query(
                'INSERT INTO sbaio_attendance_daily (
                    staff_id, attendance_date, work_start_at, work_end_at, break_start_at, break_end_at, day_marker, source_label, is_manual_correction, correction_note
                ) VALUES (?,?,?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE
                    work_start_at=VALUES(work_start_at),
                    work_end_at=VALUES(work_end_at),
                    break_start_at=VALUES(break_start_at),
                    break_end_at=VALUES(break_end_at),
                    day_marker=VALUES(day_marker),
                    source_label=VALUES(source_label),
                    is_manual_correction=VALUES(is_manual_correction),
                    correction_note=VALUES(correction_note)',
                [
                    $staffMap[$nameRef]['id'],
                    $date->format('Y-m-d'),
                    $this->combineDateAndTime($date, $startTime),
                    $this->combineDateAndTime($date, $endTime),
                    $this->combineDateAndTime($date, $breakStart),
                    $this->combineDateAndTime($date, $breakEnd),
                    $marker,
                    self::WORKBOOK_SOURCE_LABEL,
                    0,
                    $this->timecardNote((string)($row['I'] ?? '')),
                ]
            );

            $attendanceRows++;
            $months[(int)$date->format('Ym')] = $date->format('Y-m');
        }

        sort($months);
        return [
            'attendance_rows' => $attendanceRows,
            'months' => array_values($months),
        ];
    }

    /**
     * @param array<string,array<string,mixed>> $salaryReferences
     * @param array<string,array{id:int,full_name:string,name_ref:string,employee_code:string,salary_type:string}> $staffMap
     */
    private function seedSalaryReferences(array $salaryReferences, array $staffMap): int
    {
        $count = 0;
        foreach ($salaryReferences as $nameRef => $row) {
            if (!isset($staffMap[$nameRef])) {
                continue;
            }
            $salaryType = $this->normalizeSalaryType((string)($row['salary_type'] ?? ''));
            foreach ((array)($row['months'] ?? []) as $month => $amount) {
                if (!is_numeric($amount)) {
                    continue;
                }
                DB::query(
                    'INSERT INTO sbaio_salary_monthly_rates (staff_id, legacy_name_ref, salary_type, period_year, period_month, reference_amount, source_label)
                     VALUES (?,?,?,?,?,?,?)',
                    [
                        $staffMap[$nameRef]['id'],
                        $nameRef,
                        $salaryType !== '' ? $salaryType : null,
                        (int)date('Y'),
                        (int)$month,
                        round((float)$amount, 2),
                        self::SALARY_SOURCE_LABEL,
                    ]
                );
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param array<string,array<string,mixed>> $fixedProfiles
     * @param array<string,array{id:int,full_name:string,name_ref:string,employee_code:string,salary_type:string}> $staffMap
     */
    private function seedFixedProfiles(array $fixedProfiles, array $staffMap): int
    {
        $count = 0;
        foreach ($fixedProfiles as $nameRef => $row) {
            if (!isset($staffMap[$nameRef])) {
                continue;
            }

            DB::query(
                'INSERT INTO sbaio_payroll_fixed_profiles (
                    staff_id, legacy_name_ref, basic_salary_amount, taxable_earnings_amount, social_ins_subject_amount, actual_gross_pay_amount,
                    gross_pay_amount, health_insurance_amount, pension_insurance_amount, employment_insurance_amount, social_insurance_total,
                    taxable_income_amount, income_tax_amount, basic_insurance_amount, specified_insurance_amount, long_term_care_insurance_amount,
                    fixed_tax_reduction_amount, total_deductions_amount, cash_payment_amount, net_pay_amount, dependents_count, source_label
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [
                    $staffMap[$nameRef]['id'],
                    $nameRef,
                    $row['basic_salary_amount'] ?? null,
                    $row['taxable_earnings_amount'] ?? null,
                    $row['social_ins_subject_amount'] ?? null,
                    $row['actual_gross_pay_amount'] ?? null,
                    $row['gross_pay_amount'] ?? null,
                    $row['health_insurance_amount'] ?? null,
                    $row['pension_insurance_amount'] ?? null,
                    $row['employment_insurance_amount'] ?? null,
                    $row['social_insurance_total'] ?? null,
                    $row['taxable_income_amount'] ?? null,
                    $row['income_tax_amount'] ?? null,
                    $row['basic_insurance_amount'] ?? null,
                    $row['specified_insurance_amount'] ?? null,
                    $row['long_term_care_insurance_amount'] ?? null,
                    $row['fixed_tax_reduction_amount'] ?? null,
                    $row['total_deductions_amount'] ?? null,
                    $row['cash_payment_amount'] ?? null,
                    $row['net_pay_amount'] ?? null,
                    $row['dependents_count'] ?? null,
                    self::FIXED_SOURCE_LABEL,
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * @return array{sales:int,expenses:int}
     */
    private function seedSalesAndExpenses(array $workbook): array
    {
        $sales = 0;
        $expenses = 0;
        $dateCounters = [];

        foreach ($this->rowsAfterHeader($workbook, 'DayBook', 24) as $rowNumber => $row) {
            $date = $this->excelDate((string)($row['D'] ?? ''), true);
            $type = strtolower(trim((string)($row['E'] ?? '')));
            $accountName = trim((string)($row['F'] ?? ''));
            $description = trim((string)($row['G'] ?? ''));
            $amount = $this->numericValue((string)($row['H'] ?? ''));
            if (!($date instanceof \DateTimeImmutable) || $type === '' || $amount === null) {
                continue;
            }

            $dateKey = $date->format('Ymd');
            $dateCounters[$dateKey] = ($dateCounters[$dateKey] ?? 0) + 1;
            $suffix = str_pad((string)$dateCounters[$dateKey], 3, '0', STR_PAD_LEFT);

            if ($type === 'sales') {
                DB::query(
                    'INSERT INTO sbaio_sales (sale_ref, customer_name, amount, sale_status, sale_date) VALUES (?,?,?,?,?)',
                    [
                        self::SALE_REF_PREFIX . $dateKey . '-' . $suffix,
                        $this->nullIfBlank($description !== '' ? $description : $accountName),
                        $amount,
                        'posted',
                        $date->format('Y-m-d'),
                    ]
                );
                $sales++;
                continue;
            }

            if (!in_array($type, ['expense', 'purchase'], true)) {
                continue;
            }

            DB::query(
                'INSERT INTO sbaio_expenses (expense_ref, category_name, amount, expense_status, expense_date) VALUES (?,?,?,?,?)',
                [
                    self::EXPENSE_REF_PREFIX . $dateKey . '-' . $suffix,
                    $this->nullIfBlank($description !== '' ? $description : ($accountName !== '' ? $accountName : ucfirst($type))),
                    $amount,
                    'posted',
                    $date->format('Y-m-d'),
                ]
            );
            $expenses++;
        }

        return [
            'sales' => $sales,
            'expenses' => $expenses,
        ];
    }

    /**
     * @param array<int,string> $months
     * @param array<int,int> $staffIds
     * @return array{daily_rows:int,period_rows:int,period_keys:array<int,string>}
     */
    private function generateTimecards(array $months, array $staffIds): array
    {
        if ($months === [] || $staffIds === []) {
            return [
                'daily_rows' => 0,
                'period_rows' => 0,
                'period_keys' => [],
            ];
        }

        if (defined('APP_ROOT')) {
            require_once APP_ROOT . '/apps/SBAIO/modules/Timecards/Services/TimecardGenerationService.php';
        }

        $dailyRows = 0;
        $periodRows = 0;
        $periodKeys = [];
        foreach ($months as $monthKey) {
            $monthStart = new \DateTimeImmutable($monthKey . '-01');
            $monthEnd = $monthStart->modify('last day of this month');
            $result = \Plugins\Timecards\Services\TimecardGenerationService::generate(
                $monthStart->format('Y-m-d'),
                $monthEnd->format('Y-m-d'),
                $staffIds
            );
            $dailyRows += (int)($result['daily_rows'] ?? 0);
            $periodRows += (int)($result['period_rows'] ?? 0);
            $periodKeys[] = (string)($result['period_key'] ?? '');
        }

        return [
            'daily_rows' => $dailyRows,
            'period_rows' => $periodRows,
            'period_keys' => array_values(array_filter($periodKeys, static fn(string $value): bool => $value !== '')),
        ];
    }

    /**
     * @param array<int,string> $periodKeys
     */
    private function approveGeneratedPeriods(array $periodKeys): void
    {
        foreach ($periodKeys as $periodKey) {
            DB::query(
                "UPDATE sbaio_timecard_periods SET approval_status = 'approved' WHERE period_key = ?",
                [$periodKey]
            );
        }
    }

    /**
     * @param array<int,string> $periodKeys
     * @return array{runs:int,records:int}
     */
    private function scaffoldLatestPayrollRun(array $periodKeys): array
    {
        if ($periodKeys === []) {
            return ['runs' => 0, 'records' => 0];
        }

        $latest = end($periodKeys);
        if (!is_string($latest) || $latest === '') {
            return ['runs' => 0, 'records' => 0];
        }

        if (defined('APP_ROOT')) {
            require_once APP_ROOT . '/apps/SBAIO/modules/Payroll/Services/PayrollScaffoldService.php';
        }

        $result = \Plugins\Payroll\Services\PayrollScaffoldService::createFromApprovedPeriod($latest);
        return [
            'runs' => (int)(!empty($result['run_id']) ? 1 : 0),
            'records' => (int)($result['record_count'] ?? 0),
        ];
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function parseSalaryReferences(array $workbook): array
    {
        $references = [];
        foreach ($this->rowsAfterHeader($workbook, 'Salary', 8) as $row) {
            $nameRef = $this->normalizeNameRef((string)($row['B'] ?? ''));
            if ($nameRef === '' || $nameRef === '0') {
                continue;
            }

            $months = [];
            foreach (range(1, 12) as $month) {
                $column = chr(ord('D') + ($month - 1));
                $amount = $this->numericValue((string)($row[$column] ?? ''));
                if ($amount !== null) {
                    $months[$month] = $amount;
                }
            }

            if ($months === []) {
                continue;
            }

            $currentMonth = (int)date('n');
            $currentRate = $months[$currentMonth] ?? reset($months);
            $references[$nameRef] = [
                'salary_type' => $this->normalizeSalaryType((string)($row['C'] ?? '')),
                'months' => $months,
                'current_rate' => $currentRate !== false ? (float)$currentRate : null,
            ];
        }

        return $references;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function parseFixedProfiles(array $workbook): array
    {
        $profiles = [];
        $sheet = $workbook['FulltimeRates']['rows'] ?? [];
        if (!is_array($sheet) || $sheet === []) {
            return $profiles;
        }

        foreach ($this->columnSequence('B', 'Z') as $column) {
            $fullName = $this->normalizeFullName((string)($sheet[1][$column] ?? ''));
            if ($fullName === '') {
                continue;
            }

            $nameRef = $this->nameRefFromFullName($fullName);
            $profiles[$nameRef] = [
                'basic_salary_amount' => $this->numericValue((string)($sheet[2][$column] ?? '')),
                'taxable_earnings_amount' => $this->numericValue((string)($sheet[3][$column] ?? '')),
                'social_ins_subject_amount' => $this->numericValue((string)($sheet[4][$column] ?? '')),
                'actual_gross_pay_amount' => $this->numericValue((string)($sheet[5][$column] ?? '')),
                'gross_pay_amount' => $this->numericValue((string)($sheet[6][$column] ?? '')),
                'health_insurance_amount' => $this->numericValue((string)($sheet[7][$column] ?? '')),
                'pension_insurance_amount' => $this->numericValue((string)($sheet[8][$column] ?? '')),
                'employment_insurance_amount' => $this->numericValue((string)($sheet[9][$column] ?? '')),
                'social_insurance_total' => $this->numericValue((string)($sheet[10][$column] ?? '')),
                'taxable_income_amount' => $this->numericValue((string)($sheet[11][$column] ?? '')),
                'income_tax_amount' => $this->numericValue((string)($sheet[12][$column] ?? '')),
                'basic_insurance_amount' => $this->numericValue((string)($sheet[13][$column] ?? '')),
                'specified_insurance_amount' => $this->numericValue((string)($sheet[14][$column] ?? '')),
                'long_term_care_insurance_amount' => $this->numericValue((string)($sheet[15][$column] ?? '')),
                'fixed_tax_reduction_amount' => $this->numericValue((string)($sheet[16][$column] ?? '')),
                'total_deductions_amount' => $this->numericValue((string)($sheet[17][$column] ?? '')),
                'cash_payment_amount' => $this->numericValue((string)($sheet[18][$column] ?? '')),
                'net_pay_amount' => $this->numericValue((string)($sheet[19][$column] ?? '')),
                'dependents_count' => $this->intValue((string)($sheet[20][$column] ?? '')),
            ];
        }

        return $profiles;
    }

    /**
     * @return array<string,array{rows:array<int,array<string,string>>}>
     */
    private function openWorkbook(string $workbookPath): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($workbookPath) !== true) {
            throw new \RuntimeException('Workbook could not be opened.');
        }

        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if (!is_string($workbookXml) || !is_string($relsXml)) {
            throw new \RuntimeException('Workbook relationships could not be read.');
        }

        $sharedStrings = $this->sharedStrings($zip);
        $sheetTargets = $this->sheetTargets($workbookXml, $relsXml);
        $sheets = [];
        foreach ($sheetTargets as $name => $target) {
            $xml = $zip->getFromName('xl/' . ltrim($target, '/'));
            if (!is_string($xml)) {
                continue;
            }
            $sheets[$name] = [
                'rows' => $this->sheetRows($xml, $sharedStrings),
            ];
        }
        $zip->close();

        return $sheets;
    }

    /**
     * @return array<int,string>
     */
    private function sharedStrings(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if (!is_string($xml) || $xml === '') {
            return [];
        }

        $doc = simplexml_load_string($xml);
        if ($doc === false) {
            return [];
        }

        $doc->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $strings = [];
        foreach ($doc->xpath('//a:si') ?: [] as $si) {
            $si->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $text = '';
            foreach ($si->xpath('.//a:t') ?: [] as $node) {
                $text .= (string)$node;
            }
            $strings[] = $text;
        }

        return $strings;
    }

    /**
     * @return array<string,string>
     */
    private function sheetTargets(string $workbookXml, string $relsXml): array
    {
        $workbook = simplexml_load_string($workbookXml);
        $rels = simplexml_load_string($relsXml);
        if ($workbook === false || $rels === false) {
            return [];
        }

        $relMap = [];
        foreach ($rels->Relationship as $rel) {
            $relMap[(string)$rel['Id']] = (string)$rel['Target'];
        }

        $workbook->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

        $targets = [];
        foreach ($workbook->xpath('//a:sheets/a:sheet') ?: [] as $sheet) {
            $name = (string)$sheet['name'];
            $rid = (string)$sheet->attributes('r', true)['id'];
            if ($name !== '' && isset($relMap[$rid])) {
                $targets[$name] = $relMap[$rid];
            }
        }

        return $targets;
    }

    /**
     * @param array<int,string> $sharedStrings
     * @return array<int,array<string,string>>
     */
    private function sheetRows(string $xml, array $sharedStrings): array
    {
        $doc = simplexml_load_string($xml);
        if ($doc === false || !isset($doc->sheetData)) {
            return [];
        }

        $doc->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rows = [];
        foreach ($doc->xpath('//a:sheetData/a:row') ?: [] as $rowNode) {
            $rowNumber = (int)($rowNode['r'] ?? 0);
            if ($rowNumber <= 0) {
                continue;
            }

            $row = [];
            foreach ($rowNode->c as $cell) {
                $ref = strtoupper((string)($cell['r'] ?? ''));
                $column = preg_replace('/[^A-Z]/', '', $ref) ?? '';
                if ($column === '') {
                    continue;
                }

                $type = (string)($cell['t'] ?? '');
                $value = '';
                if ($type === 's') {
                    $sharedIdx = (int)($cell->v ?? 0);
                    $value = (string)($sharedStrings[$sharedIdx] ?? '');
                } elseif ($type === 'inlineStr') {
                    $value = isset($cell->is->t) ? (string)$cell->is->t : '';
                } else {
                    $value = isset($cell->v) ? (string)$cell->v : '';
                }

                $row[$column] = trim($value);
            }

            $rows[$rowNumber] = $row;
        }

        return $rows;
    }

    /**
     * @return array<int,array<string,string>>
     */
    private function rowsAfterHeader(array $workbook, string $sheetName, int $headerRow): array
    {
        $rows = $workbook[$sheetName]['rows'] ?? [];
        if (!is_array($rows)) {
            return [];
        }

        $filtered = [];
        foreach ($rows as $rowNumber => $row) {
            if ($rowNumber <= $headerRow || !is_array($row)) {
                continue;
            }

            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $filtered[$rowNumber] = $row;
        }

        return $filtered;
    }

    /**
     * @param array<string,string> $row
     */
    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string)$value) !== '') {
                return false;
            }
        }
        return true;
    }

    /**
     * @return array<string,mixed>
     */
    private function emptyStaffRecord(string $nameRef): array
    {
        return [
            'legacy_sn' => '',
            'full_name' => $this->fullNameFromNameRef($nameRef),
            'salary_type' => '',
            'salary_rate' => null,
            'nationality' => '',
            'gender' => '',
            'date_of_birth' => null,
            'join_date' => null,
            'exit_date' => null,
            'legacy_job_details' => '',
            'role_label' => '',
            'staff_type' => '',
            'department_name' => '',
            'branch_name' => '',
            'email' => '',
            'helper_classification' => '',
            'hire_date' => null,
        ];
    }

    /**
     * @param ?array<string,mixed> $salaryReference
     * @param ?array<string,mixed> $fixedProfile
     * @return array<string,mixed>
     */
    private function decorateStaffRecord(array $record, ?array $salaryReference, ?array $fixedProfile): array
    {
        $jobDetails = strtoupper(trim((string)($record['legacy_job_details'] ?? '')));
        [$department, $branch, $staffType] = $this->jobPlacement($jobDetails);
        $fullName = trim((string)($record['full_name'] ?? ''));
        $record['department_name'] = $record['department_name'] ?: $department;
        $record['branch_name'] = $record['branch_name'] ?: $branch;
        $record['staff_type'] = $record['staff_type'] ?: $staffType;
        $record['role_label'] = $record['role_label'] ?: ($department !== '' ? $department : 'Operations');
        $record['email'] = $record['email'] ?: $this->demoEmail($fullName);
        $record['salary_type'] = $this->preferSalaryType(
            (string)($record['salary_type'] ?? ''),
            (string)($salaryReference['salary_type'] ?? '')
        );
        if (($record['salary_rate'] ?? null) === null) {
            if (($salaryReference['current_rate'] ?? null) !== null) {
                $record['salary_rate'] = (float)$salaryReference['current_rate'];
            } elseif (($fixedProfile['basic_salary_amount'] ?? null) !== null) {
                $record['salary_rate'] = (float)$fixedProfile['basic_salary_amount'];
            }
        }
        $record['hire_date'] = $record['hire_date'] ?? $record['join_date'];
        return $record;
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    private function jobPlacement(string $jobDetails): array
    {
        return match ($jobDetails) {
            'MANAGEMENT' => ['Management', 'Head Office', 'office'],
            'P&P OFFICE' => ['Office', 'Head Office', 'office'],
            'RESTAURANT' => ['Restaurant Ops', 'Restaurant Floor', 'worker'],
            'CHAUTARI SPICE CENTER' => ['Spice Center', 'Spice Center', 'worker'],
            default => ['Operations', 'Main Branch', 'worker'],
        };
    }

    private function preferValue(string $existing, string $incoming): string
    {
        return trim($existing) !== '' ? $existing : trim($incoming);
    }

    private function preferSalaryType(string $existing, string $incoming): string
    {
        $existing = $this->normalizeSalaryType($existing);
        if ($existing !== '') {
            return $existing;
        }
        return $this->normalizeSalaryType($incoming);
    }

    private function normalizeSalaryType(string $value): string
    {
        $value = strtolower(trim($value));
        return match ($value) {
            'fixed', 'monthly', 'salary' => 'Fixed',
            'hourly', 'hour' => 'Hourly',
            'daily', 'day' => 'Daily',
            default => trim($value),
        };
    }

    private function normalizeNameRef(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
        if ($value === '' || $value === '0' || $value === '#N/A') {
            return '';
        }
        $value = preg_replace('/\s+様$/u', ' 様', $value) ?? $value;
        if ($this->isWorkbookSummaryLabel($value)) {
            return '';
        }
        return trim($value);
    }

    private function normalizeFullName(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
        if ($this->isWorkbookSummaryLabel($value)) {
            return '';
        }
        return $value === '#N/A' ? '' : $value;
    }

    private function fullNameFromNameRef(string $nameRef): string
    {
        $nameRef = $this->normalizeNameRef($nameRef);
        $nameRef = preg_replace('/\s+様$/u', '', $nameRef) ?? $nameRef;
        return trim($nameRef);
    }

    private function nameRefFromFullName(string $fullName): string
    {
        $fullName = $this->normalizeFullName($fullName);
        return $fullName !== '' ? $fullName . ' 様' : '';
    }

    private function demoEmail(string $fullName): string
    {
        $slug = strtolower($fullName);
        $slug = preg_replace('/[^a-z0-9]+/', '.', $slug) ?? 'staff';
        $slug = trim($slug, '.');
        return ($slug !== '' ? $slug : 'staff') . '@demo.sbaio.test';
    }

    private function nullIfBlank(?string $value): ?string
    {
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }

    private function isWorkbookSummaryLabel(string $value): bool
    {
        $normalized = strtoupper(trim((string)(preg_replace('/\s+様$/u', '', $value) ?? $value)));
        return in_array($normalized, ['GRAND TOTAL', 'TOTAL', 'SUBTOTAL'], true);
    }

    private function numericValue(string $value): ?float
    {
        $value = trim($value);
        if ($value === '' || !is_numeric($value)) {
            return null;
        }
        return round((float)$value, 2);
    }

    private function intValue(string $value): ?int
    {
        $value = trim($value);
        if ($value === '' || !is_numeric($value)) {
            return null;
        }
        return (int)$value;
    }

    private function excelSerialToDateString(string $value, bool $shiftToCurrentYear): ?string
    {
        $date = $this->excelDate($value, $shiftToCurrentYear);
        return $date instanceof \DateTimeImmutable ? $date->format('Y-m-d') : null;
    }

    private function excelDate(string $value, bool $shiftToCurrentYear): ?\DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '' || !is_numeric($value)) {
            return null;
        }
        $serial = (float)$value;
        if ($serial <= 0) {
            return null;
        }
        $date = (new \DateTimeImmutable('1899-12-30 00:00:00'))->modify('+' . (string)floor($serial) . ' days');
        return $shiftToCurrentYear ? $this->normalizedToCurrentYear($date) : $date;
    }

    private function excelTime(string $value): string
    {
        $value = trim($value);
        if ($value === '' || !is_numeric($value)) {
            return '';
        }
        $numeric = (float)$value;
        if ($numeric < 0) {
            return '';
        }
        if ($numeric >= 1.0) {
            $seconds = (int)round(($numeric - floor($numeric)) * 86400);
        } else {
            $seconds = (int)round($numeric * 86400);
        }
        $seconds = max(0, $seconds % 86400);
        return gmdate('H:i:s', $seconds);
    }

    private function normalizedToCurrentYear(\DateTimeImmutable $date): \DateTimeImmutable
    {
        $currentYear = (int)date('Y');
        $year = (int)$date->format('Y');
        if ($year >= $currentYear) {
            return $date;
        }

        $month = (int)$date->format('n');
        $day = (int)$date->format('j');
        $lastDay = (int)(new \DateTimeImmutable(sprintf('%04d-%02d-01', $currentYear, $month)))->modify('last day of this month')->format('j');
        $safeDay = min($day, $lastDay);
        return $date->setDate($currentYear, $month, $safeDay);
    }

    private function attendanceMarker(string $marker): string
    {
        $marker = trim($marker);
        return match ($marker) {
            '出勤' => 'workday',
            '休日' => 'holiday',
            '休暇' => 'leave',
            default => $marker !== '' ? 'workday' : 'off_day',
        };
    }

    private function timecardNote(string $marker): string
    {
        $marker = trim($marker);
        return $marker !== '' ? 'Workbook timecard marker: ' . $marker : 'Seeded from workbook timecard.';
    }

    private function leaveNote(string $payment, string $duration, string $approval): string
    {
        $parts = array_filter([
            $payment !== '' ? 'payment=' . $payment : '',
            $duration !== '' ? 'duration=' . $duration : '',
            $approval !== '' ? 'approval=' . $approval : '',
        ]);
        return $parts === [] ? 'Seeded from workbook leave sheet.' : 'Workbook leave: ' . implode(', ', $parts);
    }

    private function normalizeWeekday(string $value): string
    {
        $value = ucfirst(strtolower(trim($value)));
        return match ($value) {
            'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun' => $value,
            default => '',
        };
    }

    private function normalizeTitle(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        return ucwords(strtolower($value));
    }

    private function combineDateAndTime(\DateTimeImmutable $date, string $time): ?string
    {
        $time = trim($time);
        if ($time === '') {
            return null;
        }
        return $date->format('Y-m-d') . ' ' . $time;
    }

    private function breakMinutes(string $breakStart, string $breakEnd): int
    {
        if ($breakStart === '' || $breakEnd === '') {
            return 0;
        }
        $start = strtotime('2000-01-01 ' . $breakStart);
        $end = strtotime('2000-01-01 ' . $breakEnd);
        if ($start === false || $end === false || $end <= $start) {
            return 0;
        }
        return (int)floor(($end - $start) / 60);
    }

    private function resolvedScheduledMinutes(string $startTime, string $endTime, string $breakStart, string $breakEnd, string $hoursValue): int
    {
        if ($startTime !== '' && $endTime !== '') {
            $start = strtotime('2000-01-01 ' . $startTime);
            $end = strtotime('2000-01-01 ' . $endTime);
            if ($start !== false && $end !== false && $end > $start) {
                return max(0, (int)floor(($end - $start) / 60) - $this->breakMinutes($breakStart, $breakEnd));
            }
        }

        $numericHours = $this->numericValue($hoursValue);
        if ($numericHours === null) {
            return 0;
        }

        // Workbook stores shift hours as day-fractions.
        if ($numericHours > 0 && $numericHours < 1) {
            return (int)round($numericHours * 24 * 60);
        }

        return (int)round($numericHours * 60);
    }

    private function templateCode(string $dayOfWeek, string $jobLabel, string $startTime): string
    {
        $seed = strtoupper(substr(preg_replace('/[^A-Za-z0-9]+/', '', $jobLabel) ?? 'SHIFT', 0, 6));
        $day = strtoupper($dayOfWeek !== '' ? $dayOfWeek : 'ANY');
        $time = substr(str_replace(':', '', $startTime !== '' ? $startTime : '000000'), 0, 4);
        return 'WB-' . $day . '-' . $seed . '-' . $time;
    }

    /**
     * @return array<int,string>
     */
    private function columnSequence(string $start, string $end): array
    {
        $columns = [];
        $startIndex = $this->columnIndex($start);
        $endIndex = $this->columnIndex($end);
        for ($index = $startIndex; $index <= $endIndex; $index++) {
            $columns[] = $this->columnLabel($index);
        }
        return $columns;
    }

    private function columnIndex(string $column): int
    {
        $column = strtoupper(trim($column));
        $index = 0;
        $length = strlen($column);
        for ($i = 0; $i < $length; $i++) {
            $index = ($index * 26) + (ord($column[$i]) - 64);
        }
        return max(1, $index);
    }

    private function columnLabel(int $index): string
    {
        $label = '';
        while ($index > 0) {
            $index--;
            $label = chr(65 + ($index % 26)) . $label;
            $index = intdiv($index, 26);
        }
        return $label;
    }
}

<?php
declare(strict_types=1);

namespace Plugins\Payroll\Services;

use App\Core\DB;

final class PayrollScaffoldService
{
    /**
     * @return array{period_key:string,run_id:int,record_count:int}
     */
    public static function createFromApprovedPeriod(string $periodKey): array
    {
        $periodRows = DB::fetchAll(
            "SELECT p.*, s.salary_type, s.salary_rate
             FROM sbaio_timecard_periods p
             LEFT JOIN sbaio_staff s ON s.id = p.staff_id
             WHERE p.period_key = ? AND p.approval_status = 'approved'",
            [$periodKey]
        );
        if ($periodRows === []) {
            throw new \RuntimeException('No approved timecard periods were found for the selected period.');
        }

        $first = $periodRows[0];
        $periodStart = (string)$first['period_start'];
        $periodEnd = (string)$first['period_end'];
        $periodDate = new \DateTimeImmutable($periodStart);
        $periodYear = (int)$periodDate->format('Y');
        $periodMonth = (int)$periodDate->format('n');
        $periodMonthLabel = $periodDate->format('F');
        $payslipVariant = self::resolveRunPayslipVariant($periodRows);

        DB::query(
            "INSERT INTO sbaio_payroll_runs (period_key, period_start, period_end, period_year, period_month, period_month_label, payslip_variant, run_status, approval_status, locked_at, generated_at)
             VALUES (?,?,?,?,?,?,?, ?, ?, NULL, NOW())
             ON DUPLICATE KEY UPDATE period_year=VALUES(period_year), period_month=VALUES(period_month), period_month_label=VALUES(period_month_label), payslip_variant=VALUES(payslip_variant), run_status='scaffolded', approval_status='draft', locked_at=NULL, generated_at=NOW()",
            [
                $periodKey,
                $periodStart,
                $periodEnd,
                $periodYear,
                $periodMonth,
                $periodMonthLabel,
                $payslipVariant,
                'scaffolded',
                'draft',
            ]
        );

        $run = DB::fetchOne('SELECT id FROM sbaio_payroll_runs WHERE period_key = ? LIMIT 1', [$periodKey]);
        $runId = (int)($run['id'] ?? 0);
        if ($runId <= 0) {
            throw new \RuntimeException('Payroll run could not be created.');
        }

        DB::query('DELETE FROM sbaio_payroll_records WHERE payroll_run_id = ?', [$runId]);

        foreach ($periodRows as $row) {
            $salaryType = self::normalizeSalaryType((string)($row['salary_type'] ?? ''));
            $salaryRate = isset($row['salary_rate']) ? (float)$row['salary_rate'] : null;
            [$salaryReferenceAmount, $usedMonthlyReference] = self::salaryReferenceAmount(
                (int)($row['staff_id'] ?? 0),
                (string)($row['legacy_name_ref'] ?? ''),
                $periodYear,
                $periodMonth,
                $salaryType,
                $salaryRate
            );
            $workedMinutes = (int)($row['total_worked_minutes'] ?? 0);
            $workedHours = isset($row['total_worked_hours']) ? (float)$row['total_worked_hours'] : round($workedMinutes / 60, 2);
            $workedDays = (float)($row['total_days'] ?? 0);
            $workdayCount = (int)($row['workday_count'] ?? 0);
            $leaveDays = (float)($row['leave_days'] ?? 0);
            $holidayDays = (float)($row['holiday_days'] ?? 0);
            $holidayWorkDays = (float)($row['holiday_work_days'] ?? 0);
            $overtimeMinutes = (int)($row['overtime_minutes'] ?? 0);
            $recordVariant = self::recordPayslipVariant($salaryType);
            $fixedProfile = $salaryType === 'Fixed'
                ? self::lookupFixedProfile((int)($row['staff_id'] ?? 0), (string)($row['legacy_name_ref'] ?? ''))
                : null;
            $baseSalaryAmount = $salaryType === 'Fixed'
                ? (($fixedProfile['basic_salary_amount'] ?? null) !== null ? (float)$fixedProfile['basic_salary_amount'] : $salaryReferenceAmount)
                : null;
            $hourlyPayAmount = $salaryType === 'Hourly' && $salaryRate !== null ? round(($workedMinutes / 60) * $salaryRate, 2) : null;
            $grossAmount = $salaryType === 'Fixed'
                ? self::fixedProfileAmount($fixedProfile, 'gross_pay_amount', 'actual_gross_pay_amount')
                : 0.0;
            $netAmount = $salaryType === 'Fixed'
                ? self::fixedProfileAmount($fixedProfile, 'net_pay_amount', 'cash_payment_amount')
                : 0.0;
            $notes = self::calcNotes($salaryType, $usedMonthlyReference, $overtimeMinutes > 0, $fixedProfile !== null);

            DB::query(
                'INSERT INTO sbaio_payroll_records (
                    payroll_run_id, staff_id, legacy_name_ref, salary_type, salary_rate, salary_reference_amount,
                    payslip_variant, worked_days, workday_count, worked_minutes, worked_hours, leave_days, holiday_days,
                    holiday_work_days, overtime_minutes, health_insurance_amount, pension_insurance_amount, employment_insurance_amount,
                    social_insurance_total, taxable_income_amount, income_tax_amount, basic_insurance_amount,
                    specified_insurance_amount, long_term_care_insurance_amount, fixed_tax_reduction_amount,
                    total_deductions_amount, cash_payment_amount, dependents_count, base_salary_amount, hourly_pay_amount, gross_amount, net_amount,
                    payslip_working_days_label, payslip_working_time_label, calc_notes
                 ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [
                    $runId,
                    (int)($row['staff_id'] ?? 0),
                    self::normalizeLabel((string)($row['legacy_name_ref'] ?? '')) !== '' ? (string)$row['legacy_name_ref'] : null,
                    $salaryType !== '' ? $salaryType : null,
                    $salaryRate,
                    $salaryReferenceAmount,
                    $recordVariant,
                    $workedDays,
                    $workdayCount,
                    $workedMinutes,
                    $workedHours,
                    $leaveDays,
                    $holidayDays,
                    $holidayWorkDays,
                    $overtimeMinutes,
                    self::fixedProfileValue($fixedProfile, 'health_insurance_amount'),
                    self::fixedProfileValue($fixedProfile, 'pension_insurance_amount'),
                    self::fixedProfileValue($fixedProfile, 'employment_insurance_amount'),
                    self::fixedProfileValue($fixedProfile, 'social_insurance_total'),
                    self::fixedProfileValue($fixedProfile, 'taxable_income_amount'),
                    self::fixedProfileValue($fixedProfile, 'income_tax_amount'),
                    self::fixedProfileValue($fixedProfile, 'basic_insurance_amount'),
                    self::fixedProfileValue($fixedProfile, 'specified_insurance_amount'),
                    self::fixedProfileValue($fixedProfile, 'long_term_care_insurance_amount'),
                    self::fixedProfileValue($fixedProfile, 'fixed_tax_reduction_amount'),
                    self::fixedProfileValue($fixedProfile, 'total_deductions_amount'),
                    self::fixedProfileValue($fixedProfile, 'cash_payment_amount'),
                    self::fixedProfileInt($fixedProfile, 'dependents_count'),
                    $baseSalaryAmount,
                    $hourlyPayAmount,
                    $grossAmount,
                    $netAmount,
                    sprintf('Working Days: %s', self::formatDaysLabel($workedDays, $periodYear, $periodMonth)),
                    sprintf('Working Time: %s', self::formatWorkedTimeLabel($workedMinutes)),
                    $notes,
                ]
            );
        }

        return [
            'period_key' => $periodKey,
            'run_id' => $runId,
            'record_count' => count($periodRows),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $periodRows
     */
    private static function resolveRunPayslipVariant(array $periodRows): string
    {
        $variants = [];
        foreach ($periodRows as $row) {
            $variants[self::recordPayslipVariant((string)($row['salary_type'] ?? ''))] = true;
        }
        if ($variants === []) {
            return 'mixed';
        }
        if (count($variants) === 1) {
            return (string)array_key_first($variants);
        }
        return 'mixed';
    }

    private static function recordPayslipVariant(string $salaryType): string
    {
        $salaryType = self::normalizeSalaryType($salaryType);
        if ($salaryType === 'Fixed') {
            return 'full_time';
        }
        if ($salaryType === 'Hourly') {
            return 'part_time';
        }
        return 'standard';
    }

    /**
     * @return array{0:?float,1:bool}
     */
    private static function salaryReferenceAmount(int $staffId, string $legacyNameRef, int $periodYear, int $periodMonth, string $salaryType, ?float $salaryRate): array
    {
        $monthly = self::lookupMonthlySalaryReference($staffId, $legacyNameRef, $periodYear, $periodMonth);
        if ($monthly !== null) {
            return [$monthly, true];
        }
        if ($salaryRate !== null) {
            return [round($salaryRate, 2), false];
        }
        $salaryType = self::normalizeSalaryType($salaryType);
        if ($salaryType === 'Fixed' || $salaryType === 'Hourly') {
            return [null, false];
        }
        return [null, false];
    }

    private static function formatWorkedTimeLabel(int $workedMinutes): string
    {
        $hours = intdiv(max(0, $workedMinutes), 60);
        $minutes = max(0, $workedMinutes) % 60;
        return sprintf('%dh %dm', $hours, $minutes);
    }

    private static function formatDaysLabel(float $workedDays, int $year, int $month): string
    {
        $monthStart = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $monthEnd = $monthStart->modify('last day of this month');
        return sprintf('%s to %s / %.2f days', $monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d'), $workedDays);
    }

    private static function calcNotes(string $salaryType, bool $usedMonthlyReference, bool $hasOvertime, bool $usedFixedProfile): string
    {
        $salaryType = self::normalizeSalaryType($salaryType);
        $referenceNote = $usedMonthlyReference
            ? ' Monthly salary reference came from the legacy Salary-sheet-style monthly rate table.'
            : ' Monthly salary reference currently falls back to the staff master rate when no imported Salary-sheet month value is available.';
        $overtimeNote = $hasOvertime
            ? ' Overtime minutes use the workbook payslip rule: time above 8 hours on workdays.'
            : ' Overtime follows the workbook payslip rule of time above 8 hours on workdays.';
        $fixedProfileNote = $usedFixedProfile
            ? ' Fixed-pay component fields came from the imported FulltimeRates profile.'
            : ' Fixed-pay deduction and net-pay fields remain empty until a matching FulltimeRates profile is imported.';
        if ($salaryType === 'Fixed') {
            return 'Workbook-compatible scaffold for fixed salary payslip structure. Base salary reference is carried forward, but generalized deductions, premiums, and net-pay formulas remain deferred pending workbook/VBA extraction.' . $referenceNote . $overtimeNote . $fixedProfileNote;
        }
        if ($salaryType === 'Hourly') {
            return 'Workbook-compatible scaffold for hourly payslip structure. Worked time and hourly reference pay are carried forward, but rounding, premiums, helper impacts, and final net-pay formulas remain deferred pending workbook/VBA extraction.' . $referenceNote . $overtimeNote;
        }
        return 'Scaffold only. Final payroll formula, rounding, premiums, and helper classification impacts are pending legacy VBA/formula extraction.' . $referenceNote . $overtimeNote;
    }

    private static function normalizeLabel(string $value): string
    {
        return trim($value);
    }

    private static function normalizeSalaryType(string $value): string
    {
        $value = strtolower(trim($value));
        return match ($value) {
            'fixed', 'full_time', 'fulltime', 'monthly' => 'Fixed',
            'hourly', 'part_time', 'parttime' => 'Hourly',
            default => trim($value),
        };
    }

    private static function lookupMonthlySalaryReference(int $staffId, string $legacyNameRef, int $periodYear, int $periodMonth): ?float
    {
        if ($staffId > 0) {
            $row = DB::fetchOne(
                'SELECT reference_amount FROM sbaio_salary_monthly_rates WHERE staff_id = ? AND period_year = ? AND period_month = ? LIMIT 1',
                [$staffId, $periodYear, $periodMonth]
            );
            if ($row !== null && isset($row['reference_amount'])) {
                return round((float)$row['reference_amount'], 2);
            }
        }

        $legacyNameRef = trim($legacyNameRef);
        if ($legacyNameRef !== '') {
            $row = DB::fetchOne(
                'SELECT reference_amount FROM sbaio_salary_monthly_rates WHERE legacy_name_ref = ? AND period_year = ? AND period_month = ? LIMIT 1',
                [$legacyNameRef, $periodYear, $periodMonth]
            );
            if ($row !== null && isset($row['reference_amount'])) {
                return round((float)$row['reference_amount'], 2);
            }
        }

        return null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function lookupFixedProfile(int $staffId, string $legacyNameRef): ?array
    {
        if ($staffId > 0) {
            $row = DB::fetchOne(
                'SELECT * FROM sbaio_payroll_fixed_profiles WHERE staff_id = ? LIMIT 1',
                [$staffId]
            );
            if ($row !== null) {
                return $row;
            }
        }

        $legacyNameRef = trim($legacyNameRef);
        if ($legacyNameRef !== '') {
            $row = DB::fetchOne(
                'SELECT * FROM sbaio_payroll_fixed_profiles WHERE legacy_name_ref = ? LIMIT 1',
                [$legacyNameRef]
            );
            if ($row !== null) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed>|null $profile
     */
    private static function fixedProfileValue(?array $profile, string $field): ?float
    {
        if ($profile === null || !isset($profile[$field]) || $profile[$field] === null || $profile[$field] === '') {
            return null;
        }
        return round((float)$profile[$field], 2);
    }

    /**
     * @param array<string,mixed>|null $profile
     */
    private static function fixedProfileInt(?array $profile, string $field): ?int
    {
        if ($profile === null || !isset($profile[$field]) || $profile[$field] === null || $profile[$field] === '') {
            return null;
        }
        return (int)$profile[$field];
    }

    /**
     * @param array<string,mixed>|null $profile
     */
    private static function fixedProfileAmount(?array $profile, string ...$fields): float
    {
        foreach ($fields as $field) {
            $value = self::fixedProfileValue($profile, $field);
            if ($value !== null) {
                return $value;
            }
        }
        return 0.0;
    }
}

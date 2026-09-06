<?php
declare(strict_types=1);

namespace Plugins\Payroll\Services;

use App\Core\DB;

final class PayrollParityValidationService
{
    /**
     * @return array{rows:array<int,array<string,mixed>>,summary:array<string,int>}
     */
    public static function report(int $limit = 60): array
    {
        $limit = max(1, min(200, $limit));
        $keys = DB::fetchAll(
            "(SELECT smr.staff_id, smr.legacy_name_ref, smr.period_year, smr.period_month
              FROM sbaio_salary_monthly_rates smr)
             UNION
             (SELECT pr.staff_id, pr.legacy_name_ref, run.period_year, run.period_month
              FROM sbaio_payroll_records pr
              INNER JOIN sbaio_payroll_runs run ON run.id = pr.payroll_run_id)
             ORDER BY period_year DESC, period_month DESC, legacy_name_ref ASC
             LIMIT ?",
            [$limit]
        );

        $rows = [];
        $summary = [
            'match' => 0,
            'mismatch' => 0,
            'missing_imported_source' => 0,
            'missing_payroll_record' => 0,
            'unmatched_staff_mapping' => 0,
            'deferred' => 0,
        ];

        foreach ($keys as $key) {
            $row = self::buildRow($key);
            $rows[] = $row;
            $status = (string)($row['overall_status'] ?? 'deferred');
            if (!isset($summary[$status])) {
                $summary[$status] = 0;
            }
            $summary[$status]++;
        }

        return ['rows' => $rows, 'summary' => $summary];
    }

    /**
     * @param array<string,mixed> $key
     * @return array<string,mixed>
     */
    private static function buildRow(array $key): array
    {
        $staffId = isset($key['staff_id']) ? (int)$key['staff_id'] : 0;
        $legacyNameRef = trim((string)($key['legacy_name_ref'] ?? ''));
        $periodYear = (int)($key['period_year'] ?? 0);
        $periodMonth = (int)($key['period_month'] ?? 0);

        $staff = self::staffRow($staffId, $legacyNameRef);
        $monthly = self::monthlyRow($staffId, $legacyNameRef, $periodYear, $periodMonth);
        $record = self::payrollRecord($staffId, $legacyNameRef, $periodYear, $periodMonth);

        $effectiveNameRef = trim((string)($staff['name_ref'] ?? ''));
        if ($effectiveNameRef === '') {
            $effectiveNameRef = $legacyNameRef;
        }
        $employeeLabel = trim((string)($staff['full_name'] ?? ''));
        if ($employeeLabel === '') {
            $employeeLabel = self::fullNameFromNameRef($effectiveNameRef);
        }

        $salaryType = self::normalizeSalaryType((string)($record['salary_type'] ?? ($monthly['salary_type'] ?? ($staff['salary_type'] ?? ''))));
        $variant = self::variantFor($salaryType);
        $fixedProfile = $salaryType === 'Fixed' ? self::fixedProfile($staffId, $effectiveNameRef) : null;

        $fieldDefs = self::fieldDefinitions($salaryType);
        $fields = [];
        $hasMismatch = false;
        $hasComparable = false;
        $hasImportedGap = false;
        $details = [];

        foreach ($fieldDefs as $fieldKey => $def) {
            [$importedValue, $importedAvailable] = self::importedFieldValue($fieldKey, $salaryType, $monthly, $fixedProfile, $variant);
            [$generatedValue, $generatedAvailable] = self::generatedFieldValue($fieldKey, $record, $variant);
            $status = self::comparisonStatus(
                (string)$def['mode'],
                $importedAvailable,
                $generatedAvailable,
                $importedValue,
                $generatedValue
            );
            $fields[$fieldKey] = [
                'label' => $def['label'],
                'imported' => $importedValue,
                'generated' => $generatedValue,
                'status' => $status,
            ];
            if ($status === 'mismatch') {
                $hasMismatch = true;
                $details[] = (string)$def['label'];
            } elseif ($status === 'missing_imported_source') {
                $hasImportedGap = true;
                $details[] = (string)$def['label'] . ' source missing';
            } elseif ($status === 'deferred') {
                $details[] = (string)$def['label'] . ' deferred';
            } else {
                $hasComparable = true;
            }
        }

        $overall = 'match';
        if ($record === null) {
            $overall = 'missing_payroll_record';
        } elseif ($staff === null && $effectiveNameRef === '') {
            $overall = 'unmatched_staff_mapping';
        } elseif ($hasMismatch) {
            $overall = 'mismatch';
        } elseif ($hasImportedGap) {
            $overall = 'missing_imported_source';
        } elseif (!$hasComparable && $hasImportedGap) {
            $overall = 'missing_imported_source';
        } elseif (!$hasComparable) {
            $overall = 'deferred';
        }

        return [
            'employee' => $employeeLabel !== '' ? $employeeLabel : $effectiveNameRef,
            'legacy_name_ref' => $effectiveNameRef,
            'period_year' => $periodYear,
            'period_month' => $periodMonth,
            'period_label' => sprintf('%04d-%02d', $periodYear, $periodMonth),
            'salary_type' => $salaryType,
            'payslip_variant' => $variant,
            'overall_status' => $overall,
            'details' => $details,
            'fields' => $fields,
        ];
    }

    /**
     * @param array<string,mixed>|null $staff
     * @param array<string,mixed>|null $monthly
     * @param array<string,mixed>|null $fixedProfile
     * @return array{0:mixed,1:bool}
     */
    private static function importedFieldValue(string $field, string $salaryType, ?array $monthly, ?array $fixedProfile, string $variant): array
    {
        return match ($field) {
            'salary_type' => [$salaryType !== '' ? $salaryType : null, $monthly !== null || $fixedProfile !== null],
            'payslip_variant' => [$variant, $salaryType !== ''],
            'salary_reference_amount' => [$monthly['reference_amount'] ?? null, $monthly !== null],
            'base_salary_amount' => [$fixedProfile['basic_salary_amount'] ?? null, $salaryType === 'Fixed' && $fixedProfile !== null],
            'health_insurance_amount' => [$fixedProfile['health_insurance_amount'] ?? null, $salaryType === 'Fixed' && $fixedProfile !== null],
            'pension_insurance_amount' => [$fixedProfile['pension_insurance_amount'] ?? null, $salaryType === 'Fixed' && $fixedProfile !== null],
            'employment_insurance_amount' => [$fixedProfile['employment_insurance_amount'] ?? null, $salaryType === 'Fixed' && $fixedProfile !== null],
            'total_deductions_amount' => [$fixedProfile['total_deductions_amount'] ?? null, $salaryType === 'Fixed' && $fixedProfile !== null],
            'taxable_income_amount' => [$fixedProfile['taxable_income_amount'] ?? null, $salaryType === 'Fixed' && $fixedProfile !== null],
            'income_tax_amount' => [$fixedProfile['income_tax_amount'] ?? null, $salaryType === 'Fixed' && $fixedProfile !== null],
            'gross_amount' => [$fixedProfile['gross_pay_amount'] ?? ($fixedProfile['actual_gross_pay_amount'] ?? null), $salaryType === 'Fixed' && $fixedProfile !== null],
            'net_amount' => [$fixedProfile['net_pay_amount'] ?? ($fixedProfile['cash_payment_amount'] ?? null), $salaryType === 'Fixed' && $fixedProfile !== null],
            'cash_payment_amount' => [$fixedProfile['cash_payment_amount'] ?? null, $salaryType === 'Fixed' && $fixedProfile !== null],
            'dependents_count' => [$fixedProfile['dependents_count'] ?? null, $salaryType === 'Fixed' && $fixedProfile !== null],
            'hourly_pay_amount' => [null, false],
            default => [null, false],
        };
    }

    /**
     * @param array<string,mixed>|null $record
     * @return array{0:mixed,1:bool}
     */
    private static function generatedFieldValue(string $field, ?array $record, string $variant): array
    {
        if ($record === null) {
            return [null, false];
        }
        return match ($field) {
            'salary_type' => [self::normalizeSalaryType((string)($record['salary_type'] ?? '')), true],
            'payslip_variant' => [$record['payslip_variant'] ?? $variant, true],
            'salary_reference_amount' => [$record['salary_reference_amount'] ?? null, true],
            'base_salary_amount' => [$record['base_salary_amount'] ?? null, true],
            'health_insurance_amount' => [$record['health_insurance_amount'] ?? null, true],
            'pension_insurance_amount' => [$record['pension_insurance_amount'] ?? null, true],
            'employment_insurance_amount' => [$record['employment_insurance_amount'] ?? null, true],
            'total_deductions_amount' => [$record['total_deductions_amount'] ?? null, true],
            'taxable_income_amount' => [$record['taxable_income_amount'] ?? null, true],
            'income_tax_amount' => [$record['income_tax_amount'] ?? null, true],
            'gross_amount' => [$record['gross_amount'] ?? null, true],
            'net_amount' => [$record['net_amount'] ?? null, true],
            'cash_payment_amount' => [$record['cash_payment_amount'] ?? null, true],
            'dependents_count' => [$record['dependents_count'] ?? null, true],
            'hourly_pay_amount' => [$record['hourly_pay_amount'] ?? null, true],
            default => [null, false],
        };
    }

    private static function comparisonStatus(string $mode, bool $importedAvailable, bool $generatedAvailable, mixed $importedValue, mixed $generatedValue): string
    {
        if ($mode === 'deferred') {
            return 'deferred';
        }
        if (!$generatedAvailable) {
            return 'missing_payroll_record';
        }
        if (!$importedAvailable) {
            return 'missing_imported_source';
        }
        if (self::valuesMatch($importedValue, $generatedValue)) {
            return 'match';
        }
        return 'mismatch';
    }

    private static function valuesMatch(mixed $left, mixed $right): bool
    {
        if (($left === null || $left === '') && ($right === null || $right === '')) {
            return true;
        }
        if (is_numeric($left) && is_numeric($right)) {
            return abs((float)$left - (float)$right) < 0.01;
        }
        return trim((string)$left) === trim((string)$right);
    }

    /**
     * @return array<string,array{label:string,mode:string}>
     */
    private static function fieldDefinitions(string $salaryType): array
    {
        $base = [
            'salary_type' => ['label' => 'Salary Type', 'mode' => 'compare'],
            'payslip_variant' => ['label' => 'Payslip Variant', 'mode' => 'compare'],
            'salary_reference_amount' => ['label' => 'Salary Reference', 'mode' => 'compare'],
        ];

        if ($salaryType === 'Fixed') {
            return $base + [
                'base_salary_amount' => ['label' => 'Base Salary', 'mode' => 'compare'],
                'health_insurance_amount' => ['label' => 'Health Insurance', 'mode' => 'compare'],
                'pension_insurance_amount' => ['label' => 'Pension Insurance', 'mode' => 'compare'],
                'employment_insurance_amount' => ['label' => 'Employment Insurance', 'mode' => 'compare'],
                'total_deductions_amount' => ['label' => 'Total Deductions', 'mode' => 'compare'],
                'taxable_income_amount' => ['label' => 'Taxable Income', 'mode' => 'compare'],
                'income_tax_amount' => ['label' => 'Income Tax', 'mode' => 'compare'],
                'gross_amount' => ['label' => 'Gross Amount', 'mode' => 'compare'],
                'cash_payment_amount' => ['label' => 'Cash Payment', 'mode' => 'compare'],
                'net_amount' => ['label' => 'Net Amount', 'mode' => 'compare'],
                'dependents_count' => ['label' => 'Dependents', 'mode' => 'compare'],
            ];
        }

        return $base + [
            'hourly_pay_amount' => ['label' => 'Hourly Pay', 'mode' => 'deferred'],
            'gross_amount' => ['label' => 'Gross Amount', 'mode' => 'deferred'],
            'net_amount' => ['label' => 'Net Amount', 'mode' => 'deferred'],
        ];
    }

    /**
     * @param array<string,mixed> $key
     * @return array<string,mixed>|null
     */
    private static function staffRow(int $staffId, string $legacyNameRef): ?array
    {
        if ($staffId > 0) {
            $row = DB::fetchOne('SELECT * FROM sbaio_staff WHERE id = ? LIMIT 1', [$staffId]);
            if ($row !== null) {
                return $row;
            }
        }
        if ($legacyNameRef !== '') {
            $row = DB::fetchOne('SELECT * FROM sbaio_staff WHERE name_ref = ? LIMIT 1', [$legacyNameRef]);
            if ($row !== null) {
                return $row;
            }
            $fullName = self::fullNameFromNameRef($legacyNameRef);
            if ($fullName !== '') {
                $row = DB::fetchOne('SELECT * FROM sbaio_staff WHERE full_name = ? LIMIT 1', [$fullName]);
                if ($row !== null) {
                    return $row;
                }
            }
        }
        return null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function monthlyRow(int $staffId, string $legacyNameRef, int $periodYear, int $periodMonth): ?array
    {
        if ($staffId > 0) {
            $row = DB::fetchOne(
                'SELECT * FROM sbaio_salary_monthly_rates WHERE staff_id = ? AND period_year = ? AND period_month = ? LIMIT 1',
                [$staffId, $periodYear, $periodMonth]
            );
            if ($row !== null) {
                return $row;
            }
        }
        if ($legacyNameRef !== '') {
            $row = DB::fetchOne(
                'SELECT * FROM sbaio_salary_monthly_rates WHERE legacy_name_ref = ? AND period_year = ? AND period_month = ? LIMIT 1',
                [$legacyNameRef, $periodYear, $periodMonth]
            );
            if ($row !== null) {
                return $row;
            }
        }
        return null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function fixedProfile(int $staffId, string $legacyNameRef): ?array
    {
        if ($staffId > 0) {
            $row = DB::fetchOne('SELECT * FROM sbaio_payroll_fixed_profiles WHERE staff_id = ? LIMIT 1', [$staffId]);
            if ($row !== null) {
                return $row;
            }
        }
        if ($legacyNameRef !== '') {
            $row = DB::fetchOne('SELECT * FROM sbaio_payroll_fixed_profiles WHERE legacy_name_ref = ? LIMIT 1', [$legacyNameRef]);
            if ($row !== null) {
                return $row;
            }
        }
        return null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function payrollRecord(int $staffId, string $legacyNameRef, int $periodYear, int $periodMonth): ?array
    {
        if ($staffId > 0) {
            $row = DB::fetchOne(
                'SELECT pr.*, run.period_year, run.period_month
                 FROM sbaio_payroll_records pr
                 INNER JOIN sbaio_payroll_runs run ON run.id = pr.payroll_run_id
                 WHERE pr.staff_id = ? AND run.period_year = ? AND run.period_month = ?
                 ORDER BY pr.id DESC
                 LIMIT 1',
                [$staffId, $periodYear, $periodMonth]
            );
            if ($row !== null) {
                return $row;
            }
        }
        if ($legacyNameRef !== '') {
            $row = DB::fetchOne(
                'SELECT pr.*, run.period_year, run.period_month
                 FROM sbaio_payroll_records pr
                 INNER JOIN sbaio_payroll_runs run ON run.id = pr.payroll_run_id
                 WHERE pr.legacy_name_ref = ? AND run.period_year = ? AND run.period_month = ?
                 ORDER BY pr.id DESC
                 LIMIT 1',
                [$legacyNameRef, $periodYear, $periodMonth]
            );
            if ($row !== null) {
                return $row;
            }
        }
        return null;
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

    private static function variantFor(string $salaryType): string
    {
        return match (self::normalizeSalaryType($salaryType)) {
            'Fixed' => 'full_time',
            'Hourly' => 'part_time',
            default => 'standard',
        };
    }

    private static function fullNameFromNameRef(string $nameRef): string
    {
        return trim((string)preg_replace('/\s+様$/u', '', trim($nameRef)));
    }
}

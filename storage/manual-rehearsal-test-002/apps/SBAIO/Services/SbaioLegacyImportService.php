<?php
declare(strict_types=1);

namespace SBAIO\Services;

use App\Core\DB;
use App\Services\TabularImportReaderService;
use ZipArchive;

final class SbaioLegacyImportService
{
    /**
     * @return array<string,mixed>
     */
    public function previewWorkbook(string $workbookPath, string $sourceName): array
    {
        if (!is_file($workbookPath)) {
            throw new \RuntimeException('Workbook file not found.');
        }

        $book = $this->openWorkbook($workbookPath);
        $salaryPreview = $this->previewSalarySheet($book);
        $fixedPreview = $this->previewFulltimeRates($book);

        $warnings = [];
        if (($salaryPreview['sheet_found'] ?? false) !== true) {
            $warnings[] = 'Salary sheet was not found in the workbook.';
        }
        if (($fixedPreview['sheet_found'] ?? false) !== true) {
            $warnings[] = 'FulltimeRates sheet was not found in the workbook.';
        }

        return [
            'suite_key' => 'sbaio',
            'import_type' => 'legacy_workbook',
            'source_file' => $sourceName,
            'year' => (int)($salaryPreview['year'] ?? date('Y')),
            'salary_preview' => $salaryPreview,
            'fixed_preview' => $fixedPreview,
            'valid_rows' => (int)($salaryPreview['valid_rows'] ?? 0) + (int)($fixedPreview['valid_rows'] ?? 0),
            'invalid_rows' => (int)($salaryPreview['invalid_rows'] ?? 0) + (int)($fixedPreview['invalid_rows'] ?? 0),
            'skipped_rows' => (int)($salaryPreview['skipped_rows'] ?? 0) + (int)($fixedPreview['skipped_rows'] ?? 0),
            'duplicate_rows' => (int)($salaryPreview['duplicate_rows'] ?? 0) + (int)($fixedPreview['duplicate_rows'] ?? 0),
            'warnings' => $warnings,
            'warning_text' => implode(' ', $warnings),
        ];
    }

    /**
     * @param array<string,mixed> $preview
     * @return array<string,mixed>
     */
    public function importPreview(array $preview): array
    {
        $salaryRows = array_values(array_filter((array)($preview['salary_preview']['rows'] ?? []), static fn(array $row): bool => empty($row['errors'])));
        $fixedRows = array_values(array_filter((array)($preview['fixed_preview']['rows'] ?? []), static fn(array $row): bool => empty($row['errors'])));

        $conn = DB::conn();
        $conn->begin_transaction();
        try {
            DB::query("DELETE FROM sbaio_salary_monthly_rates WHERE source_label = 'legacy_salary_sheet'");
            DB::query("DELETE FROM sbaio_payroll_fixed_profiles WHERE source_label = 'legacy_fulltime_rates'");

            $salaryImported = 0;
            foreach ($salaryRows as $row) {
                DB::query(
                    "INSERT INTO sbaio_salary_monthly_rates (staff_id, legacy_name_ref, salary_type, period_year, period_month, reference_amount, source_label)
                     VALUES (?,?,?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE legacy_name_ref=VALUES(legacy_name_ref), salary_type=VALUES(salary_type), reference_amount=VALUES(reference_amount), source_label=VALUES(source_label)",
                    [
                        $row['staff_id'] ?: null,
                        $row['legacy_name_ref'],
                        $row['salary_type'] !== '' ? $row['salary_type'] : null,
                        $row['period_year'],
                        $row['period_month'],
                        $row['reference_amount'],
                        'legacy_salary_sheet',
                    ]
                );
                $salaryImported++;
            }

            $fixedImported = 0;
            foreach ($fixedRows as $row) {
                DB::query(
                    "INSERT INTO sbaio_payroll_fixed_profiles (
                        staff_id, legacy_name_ref, basic_salary_amount, taxable_earnings_amount, social_ins_subject_amount,
                        actual_gross_pay_amount, gross_pay_amount, health_insurance_amount, pension_insurance_amount,
                        employment_insurance_amount, social_insurance_total, taxable_income_amount, income_tax_amount,
                        basic_insurance_amount, specified_insurance_amount, long_term_care_insurance_amount,
                        fixed_tax_reduction_amount, total_deductions_amount, cash_payment_amount, net_pay_amount,
                        dependents_count, source_label
                     ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE
                        legacy_name_ref=VALUES(legacy_name_ref),
                        basic_salary_amount=VALUES(basic_salary_amount),
                        taxable_earnings_amount=VALUES(taxable_earnings_amount),
                        social_ins_subject_amount=VALUES(social_ins_subject_amount),
                        actual_gross_pay_amount=VALUES(actual_gross_pay_amount),
                        gross_pay_amount=VALUES(gross_pay_amount),
                        health_insurance_amount=VALUES(health_insurance_amount),
                        pension_insurance_amount=VALUES(pension_insurance_amount),
                        employment_insurance_amount=VALUES(employment_insurance_amount),
                        social_insurance_total=VALUES(social_insurance_total),
                        taxable_income_amount=VALUES(taxable_income_amount),
                        income_tax_amount=VALUES(income_tax_amount),
                        basic_insurance_amount=VALUES(basic_insurance_amount),
                        specified_insurance_amount=VALUES(specified_insurance_amount),
                        long_term_care_insurance_amount=VALUES(long_term_care_insurance_amount),
                        fixed_tax_reduction_amount=VALUES(fixed_tax_reduction_amount),
                        total_deductions_amount=VALUES(total_deductions_amount),
                        cash_payment_amount=VALUES(cash_payment_amount),
                        net_pay_amount=VALUES(net_pay_amount),
                        dependents_count=VALUES(dependents_count),
                        source_label=VALUES(source_label)",
                    [
                        $row['staff_id'] ?: null,
                        $row['legacy_name_ref'],
                        $row['basic_salary_amount'],
                        $row['taxable_earnings_amount'],
                        $row['social_ins_subject_amount'],
                        $row['actual_gross_pay_amount'],
                        $row['gross_pay_amount'],
                        $row['health_insurance_amount'],
                        $row['pension_insurance_amount'],
                        $row['employment_insurance_amount'],
                        $row['social_insurance_total'],
                        $row['taxable_income_amount'],
                        $row['income_tax_amount'],
                        $row['basic_insurance_amount'],
                        $row['specified_insurance_amount'],
                        $row['long_term_care_insurance_amount'],
                        $row['fixed_tax_reduction_amount'],
                        $row['total_deductions_amount'],
                        $row['cash_payment_amount'],
                        $row['net_pay_amount'],
                        $row['dependents_count'],
                        'legacy_fulltime_rates',
                    ]
                );
                $fixedImported++;
            }

            $conn->commit();

            return [
                'suite_key' => 'sbaio',
                'import_type' => 'legacy_workbook',
                'valid_rows' => count($salaryRows) + count($fixedRows),
                'invalid_rows' => (int)($preview['invalid_rows'] ?? 0),
                'skipped_rows' => (int)($preview['skipped_rows'] ?? 0),
                'duplicate_rows' => (int)($preview['duplicate_rows'] ?? 0),
                'imported_rows' => $salaryImported + $fixedImported,
                'failed_rows' => 0,
                'salary_rows_imported' => $salaryImported,
                'fixed_profiles_imported' => $fixedImported,
                'warning_text' => trim((string)($preview['warning_text'] ?? '')),
            ];
        } catch (\Throwable $e) {
            $conn->rollback();
            throw $e;
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function previewSalarySheet(array $book): array
    {
        $sheet = $book['Salary']['rows'] ?? [];
        if (!is_array($sheet) || $sheet === []) {
            return [
                'sheet_found' => false,
                'rows' => [],
                'valid_rows' => 0,
                'invalid_rows' => 0,
                'skipped_rows' => 0,
                'duplicate_rows' => 0,
                'year' => (int)date('Y'),
                'sample_rows' => [],
            ];
        }

        $year = $this->salarySheetYear($sheet);
        $rows = [];
        $valid = 0;
        $invalid = 0;
        $skipped = 0;
        $duplicates = 0;
        for ($rowIndex = 9; $rowIndex <= 400; $rowIndex++) {
            $legacyNameRef = trim($this->resolvedSalaryCell($book, $sheet[$rowIndex]['B'] ?? '', 'M'));
            if ($legacyNameRef === '') {
                $skipped++;
                continue;
            }

            $salaryType = trim($this->resolvedSalaryCell($book, $sheet[$rowIndex]['C'] ?? '', 'D'));
            $staffId = $this->staffIdByNameRef($legacyNameRef);
            for ($month = 1; $month <= 12; $month++) {
                $column = chr(ord('D') + ($month - 1));
                $value = $this->numericValue($sheet[$rowIndex][$column] ?? null);
                if ($value === null) {
                    $skipped++;
                    continue;
                }

                $errors = [];
                if ($staffId === null) {
                    $errors[] = 'Could not map workbook NameRef to an SBAIO staff record.';
                }

                $isDuplicate = false;
                if ($staffId !== null) {
                    $existing = DB::fetchOne(
                        'SELECT id FROM sbaio_salary_monthly_rates WHERE staff_id=? AND period_year=? AND period_month=? LIMIT 1',
                        [$staffId, $year, $month]
                    );
                    $isDuplicate = is_array($existing);
                }

                $rows[] = [
                    'entity' => 'salary_monthly_rates',
                    'source_row' => $rowIndex,
                    'legacy_name_ref' => $legacyNameRef,
                    'staff_id' => $staffId,
                    'salary_type' => $salaryType,
                    'period_year' => $year,
                    'period_month' => $month,
                    'reference_amount' => $value,
                    'duplicate' => $isDuplicate,
                    'errors' => $errors,
                ];

                if ($isDuplicate) {
                    $duplicates++;
                }
                if ($errors === []) {
                    $valid++;
                } else {
                    $invalid++;
                }
            }
        }

        return [
            'sheet_found' => true,
            'year' => $year,
            'rows' => $rows,
            'valid_rows' => $valid,
            'invalid_rows' => $invalid,
            'skipped_rows' => $skipped,
            'duplicate_rows' => $duplicates,
            'sample_rows' => array_slice($rows, 0, 8),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function previewFulltimeRates(array $book): array
    {
        $sheet = $book['FulltimeRates']['rows'] ?? [];
        if (!is_array($sheet) || $sheet === []) {
            return [
                'sheet_found' => false,
                'rows' => [],
                'valid_rows' => 0,
                'invalid_rows' => 0,
                'skipped_rows' => 0,
                'duplicate_rows' => 0,
                'sample_rows' => [],
            ];
        }

        $rows = [];
        $valid = 0;
        $invalid = 0;
        $skipped = 0;
        $duplicates = 0;
        foreach (['B', 'C', 'D'] as $column) {
            $name = trim((string)($sheet[1][$column] ?? ''));
            if ($name === '') {
                $skipped++;
                continue;
            }

            $legacyNameRef = $this->nameRef($name);
            $staffId = $this->staffIdByNameRef($legacyNameRef);
            $errors = [];
            if ($staffId === null) {
                $errors[] = 'Could not map fixed-pay profile to an SBAIO staff record.';
            }
            $existing = $staffId !== null
                ? DB::fetchOne('SELECT id FROM sbaio_payroll_fixed_profiles WHERE staff_id=? LIMIT 1', [$staffId])
                : null;
            $isDuplicate = is_array($existing);

            $row = [
                'entity' => 'payroll_fixed_profiles',
                'source_column' => $column,
                'legacy_name_ref' => $legacyNameRef,
                'staff_id' => $staffId,
                'basic_salary_amount' => $this->numericValue($sheet[2][$column] ?? null),
                'taxable_earnings_amount' => $this->numericValue($sheet[3][$column] ?? null),
                'social_ins_subject_amount' => $this->numericValue($sheet[4][$column] ?? null),
                'actual_gross_pay_amount' => $this->numericValue($sheet[5][$column] ?? null),
                'gross_pay_amount' => $this->numericValue($sheet[6][$column] ?? null),
                'health_insurance_amount' => $this->numericValue($sheet[7][$column] ?? null),
                'pension_insurance_amount' => $this->numericValue($sheet[8][$column] ?? null),
                'employment_insurance_amount' => $this->numericValue($sheet[9][$column] ?? null),
                'social_insurance_total' => $this->numericValue($sheet[10][$column] ?? null),
                'taxable_income_amount' => $this->numericValue($sheet[11][$column] ?? null),
                'income_tax_amount' => $this->numericValue($sheet[12][$column] ?? null),
                'basic_insurance_amount' => $this->numericValue($sheet[13][$column] ?? null),
                'specified_insurance_amount' => $this->numericValue($sheet[14][$column] ?? null),
                'long_term_care_insurance_amount' => $this->numericValue($sheet[15][$column] ?? null),
                'fixed_tax_reduction_amount' => $this->numericValue($sheet[16][$column] ?? null),
                'total_deductions_amount' => $this->numericValue($sheet[17][$column] ?? null),
                'cash_payment_amount' => $this->numericValue($sheet[18][$column] ?? null),
                'net_pay_amount' => $this->numericValue($sheet[19][$column] ?? null),
                'dependents_count' => $this->numericValue($sheet[20][$column] ?? null),
                'duplicate' => $isDuplicate,
                'errors' => $errors,
            ];
            $rows[] = $row;

            if ($isDuplicate) {
                $duplicates++;
            }
            if ($errors === []) {
                $valid++;
            } else {
                $invalid++;
            }
        }

        return [
            'sheet_found' => true,
            'rows' => $rows,
            'valid_rows' => $valid,
            'invalid_rows' => $invalid,
            'skipped_rows' => $skipped,
            'duplicate_rows' => $duplicates,
            'sample_rows' => array_slice($rows, 0, 3),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function openWorkbook(string $workbookPath): array
    {
        $zip = new ZipArchive();
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
     * @param array<int,array<string,string>> $rows
     */
    private function salarySheetYear(array $rows): int
    {
        foreach ($rows as $columns) {
            foreach ($columns as $value) {
                if (preg_match('/Salary Sheet\\s+(\\d{4})/i', $value, $m)) {
                    return (int)$m[1];
                }
            }
        }
        return (int)date('Y');
    }

    private function staffIdByNameRef(string $legacyNameRef): ?int
    {
        $legacyNameRef = trim($legacyNameRef);
        if ($legacyNameRef === '') {
            return null;
        }
        $row = DB::fetchOne('SELECT id FROM sbaio_staff WHERE name_ref = ? LIMIT 1', [$legacyNameRef]);
        if ($row !== null) {
            return (int)$row['id'];
        }

        $fullName = trim(preg_replace('/\\s+様$/u', '', $legacyNameRef) ?? $legacyNameRef);
        if ($fullName === '') {
            return null;
        }

        $row = DB::fetchOne('SELECT id FROM sbaio_staff WHERE full_name = ? LIMIT 1', [$fullName]);
        return $row !== null ? (int)$row['id'] : null;
    }

    private function nameRef(string $name): string
    {
        return trim($name) . '   様';
    }

    private function resolvedSalaryCell(array $book, mixed $value, string $expectedColumn): string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }
        if (!preg_match('/^=([A-Za-z0-9_]+)!([A-Z]+)(\\d+)$/', $value, $m)) {
            return $value;
        }

        $sheetName = $m[1];
        $targetColumn = $m[2];
        $targetRow = (int)$m[3];
        $sheet = $book[$sheetName]['rows'] ?? null;
        if (!is_array($sheet)) {
            return $value;
        }

        $targetValue = trim((string)($sheet[$targetRow][$targetColumn] ?? ''));
        if ($targetValue !== '' && $targetValue[0] !== '=') {
            return $targetValue;
        }

        if ($expectedColumn === 'M') {
            $fullName = trim((string)($sheet[$targetRow]['C'] ?? ''));
            return $fullName !== '' ? $this->nameRef($fullName) : '';
        }
        if ($expectedColumn === 'D') {
            return trim((string)($sheet[$targetRow]['D'] ?? ''));
        }

        return $targetValue;
    }

    private function numericValue(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return round((float)$value, 2);
        }
        return null;
    }

    /**
     * @return array<int,string>
     */
    private function sharedStrings(ZipArchive $zip): array
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
     * @return array<int,array<string,string>>
     */
    private function sheetRows(string $sheetXml, array $sharedStrings): array
    {
        $sheet = simplexml_load_string($sheetXml);
        if ($sheet === false) {
            return [];
        }
        $sheet->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $rows = [];
        foreach ($sheet->xpath('//a:sheetData/a:row') ?: [] as $row) {
            $rowIndex = (int)$row['r'];
            $cols = [];
            foreach ($row->c as $cell) {
                $ref = (string)$cell['r'];
                $col = preg_replace('/\\d+/', '', $ref) ?: $ref;
                $type = (string)$cell['t'];
                $formula = isset($cell->f) ? trim((string)$cell->f) : '';
                $value = isset($cell->v) ? (string)$cell->v : '';
                if ($formula !== '') {
                    $cols[$col] = '=' . $formula;
                    continue;
                }
                if ($type === 's' && $value !== '' && isset($sharedStrings[(int)$value])) {
                    $cols[$col] = $sharedStrings[(int)$value];
                    continue;
                }
                $cols[$col] = $value;
            }
            $rows[$rowIndex] = $cols;
        }

        return $rows;
    }
}

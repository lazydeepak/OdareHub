<?php
declare(strict_types=1);

namespace Plugins\Payroll\Services;

use App\Core\DB;
use ZipArchive;

final class LegacyWorkbookImportService
{
    /**
     * @return array{salary_rows:int,fixed_profiles:int,year:int}
     */
    public static function import(string $workbookPath): array
    {
        if (!is_file($workbookPath)) {
            throw new \RuntimeException('Workbook file not found: ' . $workbookPath);
        }

        $book = self::openWorkbook($workbookPath);
        $salaryRows = self::importSalarySheet($book);
        $fixedProfiles = self::importFulltimeRates($book);

        return [
            'salary_rows' => $salaryRows,
            'fixed_profiles' => $fixedProfiles,
            'year' => self::salarySheetYear($book['Salary']['rows'] ?? []),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function openWorkbook(string $workbookPath): array
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

        $sharedStrings = self::sharedStrings($zip);
        $sheetTargets = self::sheetTargets($workbookXml, $relsXml);
        $sheets = [];
        foreach ($sheetTargets as $name => $target) {
            $xml = $zip->getFromName('xl/' . ltrim($target, '/'));
            if (!is_string($xml)) {
                continue;
            }
            $sheets[$name] = [
                'rows' => self::sheetRows($xml, $sharedStrings),
            ];
        }
        $zip->close();

        return $sheets;
    }

    /**
     * @param array<string,mixed> $book
     */
    private static function importSalarySheet(array $book): int
    {
        $sheet = $book['Salary']['rows'] ?? [];
        if (!is_array($sheet) || $sheet === []) {
            return 0;
        }

        DB::query("DELETE FROM sbaio_salary_monthly_rates WHERE source_label = 'legacy_salary_sheet'");

        $year = self::salarySheetYear($sheet);
        $rowsImported = 0;
        for ($row = 9; $row <= 400; $row++) {
            $legacyNameRef = trim(self::resolvedSalaryCell($book, $sheet[$row]['B'] ?? '', 'M'));
            if ($legacyNameRef === '') {
                continue;
            }
            $salaryType = trim(self::resolvedSalaryCell($book, $sheet[$row]['C'] ?? '', 'D'));
            $staffId = self::staffIdByNameRef($legacyNameRef);
            for ($month = 1; $month <= 12; $month++) {
                $column = chr(ord('D') + ($month - 1));
                $value = self::numericValue($sheet[$row][$column] ?? null);
                if ($value === null) {
                    continue;
                }
                DB::query(
                    "INSERT INTO sbaio_salary_monthly_rates (staff_id, legacy_name_ref, salary_type, period_year, period_month, reference_amount, source_label)
                     VALUES (?,?,?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE legacy_name_ref=VALUES(legacy_name_ref), salary_type=VALUES(salary_type), reference_amount=VALUES(reference_amount), source_label=VALUES(source_label)",
                    [$staffId, $legacyNameRef, $salaryType !== '' ? $salaryType : null, $year, $month, $value, 'legacy_salary_sheet']
                );
                $rowsImported++;
            }
        }

        return $rowsImported;
    }

    /**
     * @param array<string,mixed> $book
     */
    private static function importFulltimeRates(array $book): int
    {
        $sheet = $book['FulltimeRates']['rows'] ?? [];
        if (!is_array($sheet) || $sheet === []) {
            return 0;
        }

        DB::query("DELETE FROM sbaio_payroll_fixed_profiles WHERE source_label = 'legacy_fulltime_rates'");

        $profiles = [];
        foreach (['B', 'C', 'D'] as $column) {
            $name = trim((string)($sheet[1][$column] ?? ''));
            if ($name === '') {
                continue;
            }
            $profiles[$column] = [
                'legacy_name_ref' => self::nameRef($name),
                'staff_id' => self::staffIdByNameRef(self::nameRef($name)),
                'basic_salary_amount' => self::numericValue($sheet[2][$column] ?? null),
                'taxable_earnings_amount' => self::numericValue($sheet[3][$column] ?? null),
                'social_ins_subject_amount' => self::numericValue($sheet[4][$column] ?? null),
                'actual_gross_pay_amount' => self::numericValue($sheet[5][$column] ?? null),
                'gross_pay_amount' => self::numericValue($sheet[6][$column] ?? null),
                'health_insurance_amount' => self::numericValue($sheet[7][$column] ?? null),
                'pension_insurance_amount' => self::numericValue($sheet[8][$column] ?? null),
                'employment_insurance_amount' => self::numericValue($sheet[9][$column] ?? null),
                'social_insurance_total' => self::numericValue($sheet[10][$column] ?? null),
                'taxable_income_amount' => self::numericValue($sheet[11][$column] ?? null),
                'income_tax_amount' => self::numericValue($sheet[12][$column] ?? null),
                'basic_insurance_amount' => self::numericValue($sheet[13][$column] ?? null),
                'specified_insurance_amount' => self::numericValue($sheet[14][$column] ?? null),
                'long_term_care_insurance_amount' => self::numericValue($sheet[15][$column] ?? null),
                'fixed_tax_reduction_amount' => self::numericValue($sheet[16][$column] ?? null),
                'total_deductions_amount' => self::numericValue($sheet[17][$column] ?? null),
                'cash_payment_amount' => self::numericValue($sheet[18][$column] ?? null),
                'net_pay_amount' => self::numericValue($sheet[19][$column] ?? null),
                'dependents_count' => self::numericValue($sheet[20][$column] ?? null),
                'source_label' => 'legacy_fulltime_rates',
            ];
        }

        $count = 0;
        foreach ($profiles as $profile) {
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
                    $profile['staff_id'],
                    $profile['legacy_name_ref'],
                    $profile['basic_salary_amount'],
                    $profile['taxable_earnings_amount'],
                    $profile['social_ins_subject_amount'],
                    $profile['actual_gross_pay_amount'],
                    $profile['gross_pay_amount'],
                    $profile['health_insurance_amount'],
                    $profile['pension_insurance_amount'],
                    $profile['employment_insurance_amount'],
                    $profile['social_insurance_total'],
                    $profile['taxable_income_amount'],
                    $profile['income_tax_amount'],
                    $profile['basic_insurance_amount'],
                    $profile['specified_insurance_amount'],
                    $profile['long_term_care_insurance_amount'],
                    $profile['fixed_tax_reduction_amount'],
                    $profile['total_deductions_amount'],
                    $profile['cash_payment_amount'],
                    $profile['net_pay_amount'],
                    $profile['dependents_count'],
                    $profile['source_label'],
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * @param array<int,array<string,string>> $rows
     */
    private static function salarySheetYear(array $rows): int
    {
        foreach ($rows as $columns) {
            foreach ($columns as $value) {
                if (preg_match('/Salary Sheet\s+(\d{4})/i', $value, $m)) {
                    return (int)$m[1];
                }
            }
        }
        return (int)date('Y');
    }

    private static function staffIdByNameRef(string $legacyNameRef): ?int
    {
        $legacyNameRef = trim($legacyNameRef);
        if ($legacyNameRef === '') {
            return null;
        }
        $row = DB::fetchOne('SELECT id FROM sbaio_staff WHERE name_ref = ? LIMIT 1', [$legacyNameRef]);
        if ($row !== null) {
            return (int)$row['id'];
        }

        $fullName = trim(preg_replace('/\s+様$/u', '', $legacyNameRef) ?? $legacyNameRef);
        if ($fullName === '') {
            return null;
        }

        $row = DB::fetchOne('SELECT id FROM sbaio_staff WHERE full_name = ? LIMIT 1', [$fullName]);
        return $row !== null ? (int)$row['id'] : null;
    }

    private static function nameRef(string $name): string
    {
        return trim($name) . '   様';
    }

    /**
     * @param array<string,mixed> $book
     */
    private static function resolvedSalaryCell(array $book, mixed $value, string $expectedColumn): string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }
        if (!preg_match('/^=([A-Za-z0-9_]+)!([A-Z]+)(\d+)$/', $value, $m)) {
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
            return $fullName !== '' ? self::nameRef($fullName) : '';
        }
        if ($expectedColumn === 'D') {
            return trim((string)($sheet[$targetRow]['D'] ?? ''));
        }

        return $targetValue;
    }

    private static function numericValue(mixed $value): ?float
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
    private static function sharedStrings(ZipArchive $zip): array
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
    private static function sheetTargets(string $workbookXml, string $relsXml): array
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
    private static function sheetRows(string $sheetXml, array $sharedStrings): array
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
                $col = preg_replace('/\d+/', '', $ref) ?: $ref;
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

<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services;

use App\Core\DB;
use App\Services\TabularImportReaderService;

final class ManufacturingLegacyImportService
{
    private TabularImportReaderService $reader;

    public function __construct(?TabularImportReaderService $reader = null)
    {
        $this->reader = $reader ?? new TabularImportReaderService();
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function templates(): array
    {
        return [
            'products' => [
                'key' => 'products',
                'label' => 'Parts / Products',
                'mode' => 'ready',
                'description' => 'Import parts master rows with part number, model, producer, and active flag.',
                'headers' => ['parts_name', 'parts_number', 'model', 'producer', 'lead', 'notes', 'is_active'],
                'sample_row' => ['Legacy Part', 'LEG-P-001', 'A100', 'Legacy Plant', '7', 'Imported from legacy parts list', '1'],
            ],
            'machines' => [
                'key' => 'machines',
                'label' => 'Machines',
                'mode' => 'ready',
                'description' => 'Import machine master rows with machine number, name, section, capacity, and active flag.',
                'headers' => ['machine_no', 'machine_name', 'section', 'status', 'capacity_per_hour', 'notes', 'is_active'],
                'sample_row' => ['MAC-001', 'Mazak 1', 'Machining', 'Available', '120', 'Migrated legacy machine', '1'],
            ],
            'daily_orders' => [
                'key' => 'daily_orders',
                'label' => 'Daily Orders',
                'mode' => 'ready',
                'description' => 'Import demand rows with customer, qty, order date, and product lookup columns.',
                'headers' => ['order_date', 'required_date', 'customer_name', 'parts_number', 'qty', 'dispatch_deadline', 'status', 'notes'],
                'sample_row' => ['2026-04-01', '2026-04-07', 'Legacy Customer', 'LEG-P-001', '120', '2026-04-07 17:00:00', 'Open', 'Migrated daily order'],
            ],
            'pre_orders' => [
                'key' => 'pre_orders',
                'label' => 'Pre Orders',
                'mode' => 'ready',
                'description' => 'Import forecast/pre-order rows with planned quantity and product lookup columns.',
                'headers' => ['forecast_type', 'planning_priority', 'parts_number', 'planned_qty', 'balance_qty', 'required_date', 'notes'],
                'sample_row' => ['Monthly Forecast', 'High', 'LEG-P-001', '300', '300', '2026-04-20', 'Migrated forecast'],
            ],
            'part_machine_map' => [
                'key' => 'part_machine_map',
                'label' => 'Part-Machine Map',
                'mode' => 'ready',
                'description' => 'Import compatible machine mappings between parts and machines.',
                'headers' => ['parts_number', 'machine_no', 'is_active', 'notes'],
                'sample_row' => ['LEG-P-001', 'MAC-001', '1', 'Migrated mapping'],
            ],
            'production_plans' => [
                'key' => 'production_plans',
                'label' => 'Production Plans Template',
                'mode' => 'template_only',
                'description' => 'Template-only starter for staged production plan migration.',
                'headers' => ['plan_date', 'parts_number', 'machine_no', 'planned_qty', 'sequence_no', 'status', 'notes'],
                'sample_row' => ['2026-04-10', 'LEG-P-001', 'MAC-001', '100', '1', 'Planned', 'Template starter'],
            ],
            'qc_plans' => [
                'key' => 'qc_plans',
                'label' => 'QC Plans Template',
                'mode' => 'template_only',
                'description' => 'Template-only starter for staged QC plan migration.',
                'headers' => ['plan_date', 'required_date', 'parts_number', 'planned_qty', 'priority', 'status', 'notes'],
                'sample_row' => ['2026-04-12', '2026-04-14', 'LEG-P-001', '80', 'Normal', 'Open', 'Template starter'],
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function previewImport(string $importType, string $path, string $sourceName): array
    {
        $template = $this->templates()[$importType] ?? null;
        if (!is_array($template)) {
            throw new \RuntimeException('Unknown manufacturing import type.');
        }

        $ext = strtolower(pathinfo($sourceName, PATHINFO_EXTENSION));
        $rows = $this->reader->read($path, $ext);
        if ($rows === []) {
            throw new \RuntimeException('No rows found in import file.');
        }

        $header = array_shift($rows);
        if (!is_array($header)) {
            throw new \RuntimeException('Header row is missing or invalid.');
        }

        $headerMap = $this->buildHeaderMap($header);
        $required = array_values(array_filter((array)($template['headers'] ?? []), static fn(string $header): bool => !in_array($header, ['notes', 'required_date', 'dispatch_deadline', 'status', 'balance_qty', 'planning_priority', 'model', 'producer', 'lead', 'capacity_per_hour', 'is_active', 'sequence_no', 'priority'], true)));
        foreach ($required as $requiredHeader) {
            if (!isset($headerMap[$requiredHeader])) {
                throw new \RuntimeException('Missing required header: ' . $requiredHeader);
            }
        }

        $previewRows = [];
        $valid = 0;
        $invalid = 0;
        $skipped = 0;
        $duplicates = 0;
        $warnings = [];
        $productLookup = $this->productLookup();
        $machineLookup = $this->machineLookup();

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                $skipped++;
                continue;
            }
            $lineNumber = $index + 2;
            $normalized = match ($importType) {
                'products' => $this->normalizeProductRow($row, $headerMap),
                'machines' => $this->normalizeMachineRow($row, $headerMap),
                'daily_orders' => $this->normalizeDailyOrderRow($row, $headerMap, $productLookup),
                'pre_orders' => $this->normalizePreOrderRow($row, $headerMap, $productLookup),
                'part_machine_map' => $this->normalizePartMachineRow($row, $headerMap, $productLookup, $machineLookup),
                default => ['errors' => ['Template is not commit-ready yet.']],
            };
            $normalized['line_number'] = $lineNumber;
            $normalized['duplicate'] = $this->detectDuplicate($importType, $normalized);
            if (!empty($normalized['duplicate'])) {
                $duplicates++;
            }
            if ($normalized['errors'] === []) {
                $valid++;
            } else {
                $invalid++;
            }
            $previewRows[] = $normalized;
        }

        if (($template['mode'] ?? '') === 'template_only') {
            $warnings[] = 'This import type is template-ready only. Preview and download are supported, but commit is intentionally disabled.';
        }

        return [
            'suite_key' => 'manufacturing',
            'import_type' => $importType,
            'source_file' => $sourceName,
            'template' => $template,
            'rows' => $previewRows,
            'valid_rows' => $valid,
            'invalid_rows' => $invalid,
            'skipped_rows' => $skipped,
            'duplicate_rows' => $duplicates,
            'warnings' => $warnings,
            'warning_text' => implode(' ', $warnings),
            'sample_rows' => array_slice($previewRows, 0, 8),
        ];
    }

    /**
     * @param array<string,mixed> $preview
     * @return array<string,mixed>
     */
    public function importPreview(array $preview): array
    {
        $importType = (string)($preview['import_type'] ?? '');
        $template = $this->templates()[$importType] ?? null;
        if (!is_array($template)) {
            throw new \RuntimeException('Unknown manufacturing import type.');
        }
        if ((string)($template['mode'] ?? '') !== 'ready') {
            throw new \RuntimeException('This import type is template-ready only and cannot be committed yet.');
        }

        $rows = array_values(array_filter((array)($preview['rows'] ?? []), static fn(array $row): bool => empty($row['errors'])));
        $conn = DB::conn();
        $conn->begin_transaction();
        try {
            $imported = 0;
            foreach ($rows as $row) {
                match ($importType) {
                    'products' => $this->commitProductRow($row),
                    'machines' => $this->commitMachineRow($row),
                    'daily_orders' => $this->commitDailyOrderRow($row),
                    'pre_orders' => $this->commitPreOrderRow($row),
                    'part_machine_map' => $this->commitPartMachineRow($row),
                    default => null,
                };
                $imported++;
            }
            $conn->commit();

            if (class_exists('\\Plugins\\Coverage\\Services\\CoverageService') && in_array($importType, ['daily_orders', 'pre_orders'], true)) {
                \Plugins\Coverage\Services\CoverageService::recalculateAllOpenOrders();
            }

            return [
                'suite_key' => 'manufacturing',
                'import_type' => $importType,
                'valid_rows' => (int)($preview['valid_rows'] ?? 0),
                'invalid_rows' => (int)($preview['invalid_rows'] ?? 0),
                'skipped_rows' => (int)($preview['skipped_rows'] ?? 0),
                'duplicate_rows' => (int)($preview['duplicate_rows'] ?? 0),
                'imported_rows' => $imported,
                'failed_rows' => 0,
                'warning_text' => trim((string)($preview['warning_text'] ?? '')),
            ];
        } catch (\Throwable $e) {
            $conn->rollback();
            throw $e;
        }
    }

    /**
     * @return array<string,string>
     */
    public function templateDownloadPayload(string $importType): array
    {
        $template = $this->templates()[$importType] ?? null;
        if (!is_array($template)) {
            throw new \RuntimeException('Unknown template.');
        }

        $header = implode(',', array_map([$this, 'csvValue'], (array)$template['headers']));
        $sample = implode(',', array_map([$this, 'csvValue'], (array)$template['sample_row']));
        return [
            'filename' => 'manufacturing_' . $importType . '_template.csv',
            'content' => $header . "\n" . $sample . "\n",
        ];
    }

    /**
     * @return array<string,int>
     */
    private function productLookup(): array
    {
        $rows = DB::fetchAll('SELECT id, parts_number FROM products');
        $map = [];
        foreach ($rows as $row) {
            $partsNumber = strtolower(trim((string)($row['parts_number'] ?? '')));
            if ($partsNumber !== '') {
                $map[$partsNumber] = (int)($row['id'] ?? 0);
            }
        }
        return $map;
    }

    /**
     * @return array<string,int>
     */
    private function machineLookup(): array
    {
        $rows = DB::fetchAll('SELECT id, machine_no FROM machines');
        $map = [];
        foreach ($rows as $row) {
            $machineNo = strtolower(trim((string)($row['machine_no'] ?? '')));
            if ($machineNo !== '') {
                $map[$machineNo] = (int)($row['id'] ?? 0);
            }
        }
        return $map;
    }

    /**
     * @param array<int,string> $header
     * @return array<string,int>
     */
    private function buildHeaderMap(array $header): array
    {
        $map = [];
        foreach ($header as $index => $value) {
            $key = strtolower(trim((string)$value));
            if ($key !== '') {
                $map[$key] = (int)$index;
            }
        }
        return $map;
    }

    /**
     * @param array<int,string> $row
     * @param array<string,int> $headerMap
     * @return array<string,mixed>
     */
    private function normalizeProductRow(array $row, array $headerMap): array
    {
        $partsName = trim((string)($row[$headerMap['parts_name']] ?? ''));
        $partsNumber = trim((string)($row[$headerMap['parts_number']] ?? ''));
        $errors = [];
        if ($partsName === '' || $partsNumber === '') {
            $errors[] = 'parts_name and parts_number are required.';
        }
        return [
            'parts_name' => $partsName,
            'parts_number' => $partsNumber,
            'model' => trim((string)($row[$headerMap['model']] ?? '')),
            'producer' => trim((string)($row[$headerMap['producer']] ?? '')),
            'lead' => trim((string)($row[$headerMap['lead']] ?? '')),
            'notes' => trim((string)($row[$headerMap['notes']] ?? '')),
            'is_active' => ((string)($row[$headerMap['is_active']] ?? '1') === '0') ? 0 : 1,
            'errors' => $errors,
        ];
    }

    /**
     * @param array<int,string> $row
     * @param array<string,int> $headerMap
     * @return array<string,mixed>
     */
    private function normalizeMachineRow(array $row, array $headerMap): array
    {
        $machineNo = trim((string)($row[$headerMap['machine_no']] ?? ''));
        $machineName = trim((string)($row[$headerMap['machine_name']] ?? ''));
        $errors = [];
        if ($machineNo === '' || $machineName === '') {
            $errors[] = 'machine_no and machine_name are required.';
        }
        return [
            'machine_no' => $machineNo,
            'machine_name' => $machineName,
            'section' => trim((string)($row[$headerMap['section']] ?? '')),
            'status' => trim((string)($row[$headerMap['status']] ?? 'Available')),
            'capacity_per_hour' => is_numeric((string)($row[$headerMap['capacity_per_hour']] ?? '')) ? (float)$row[$headerMap['capacity_per_hour']] : null,
            'notes' => trim((string)($row[$headerMap['notes']] ?? '')),
            'is_active' => ((string)($row[$headerMap['is_active']] ?? '1') === '0') ? 0 : 1,
            'errors' => $errors,
        ];
    }

    /**
     * @param array<int,string> $row
     * @param array<string,int> $headerMap
     * @param array<string,int> $productLookup
     * @return array<string,mixed>
     */
    private function normalizeDailyOrderRow(array $row, array $headerMap, array $productLookup): array
    {
        $partsNumber = strtolower(trim((string)($row[$headerMap['parts_number']] ?? '')));
        $productId = (int)($productLookup[$partsNumber] ?? 0);
        $errors = [];
        if ($productId <= 0) {
            $errors[] = 'parts_number could not be matched to an existing product.';
        }
        $orderDate = trim((string)($row[$headerMap['order_date']] ?? ''));
        $customerName = trim((string)($row[$headerMap['customer_name']] ?? ''));
        $qty = is_numeric((string)($row[$headerMap['qty']] ?? '')) ? (float)$row[$headerMap['qty']] : 0;
        if ($orderDate === '' || $customerName === '' || $qty <= 0) {
            $errors[] = 'order_date, customer_name, and qty are required.';
        }
        return [
            'order_date' => $orderDate,
            'required_date' => trim((string)($row[$headerMap['required_date']] ?? '')),
            'customer_name' => $customerName,
            'product_id' => $productId,
            'parts_number' => strtoupper($partsNumber),
            'qty' => $qty,
            'dispatch_deadline' => trim((string)($row[$headerMap['dispatch_deadline']] ?? '')),
            'status' => trim((string)($row[$headerMap['status']] ?? 'Open')),
            'notes' => trim((string)($row[$headerMap['notes']] ?? '')),
            'errors' => $errors,
        ];
    }

    /**
     * @param array<int,string> $row
     * @param array<string,int> $headerMap
     * @param array<string,int> $productLookup
     * @return array<string,mixed>
     */
    private function normalizePreOrderRow(array $row, array $headerMap, array $productLookup): array
    {
        $partsNumber = strtolower(trim((string)($row[$headerMap['parts_number']] ?? '')));
        $productId = (int)($productLookup[$partsNumber] ?? 0);
        $plannedQty = is_numeric((string)($row[$headerMap['planned_qty']] ?? '')) ? (float)$row[$headerMap['planned_qty']] : 0;
        $errors = [];
        if ($productId <= 0) {
            $errors[] = 'parts_number could not be matched to an existing product.';
        }
        if (trim((string)($row[$headerMap['forecast_type']] ?? '')) === '' || $plannedQty <= 0) {
            $errors[] = 'forecast_type and planned_qty are required.';
        }
        return [
            'forecast_type' => trim((string)($row[$headerMap['forecast_type']] ?? '')),
            'planning_priority' => trim((string)($row[$headerMap['planning_priority']] ?? 'Normal')),
            'product_id' => $productId,
            'parts_number' => strtoupper($partsNumber),
            'planned_qty' => $plannedQty,
            'balance_qty' => is_numeric((string)($row[$headerMap['balance_qty']] ?? '')) ? (float)$row[$headerMap['balance_qty']] : $plannedQty,
            'required_date' => trim((string)($row[$headerMap['required_date']] ?? '')),
            'notes' => trim((string)($row[$headerMap['notes']] ?? '')),
            'errors' => $errors,
        ];
    }

    /**
     * @param array<int,string> $row
     * @param array<string,int> $headerMap
     * @param array<string,int> $productLookup
     * @param array<string,int> $machineLookup
     * @return array<string,mixed>
     */
    private function normalizePartMachineRow(array $row, array $headerMap, array $productLookup, array $machineLookup): array
    {
        $partsNumber = strtolower(trim((string)($row[$headerMap['parts_number']] ?? '')));
        $machineNo = strtolower(trim((string)($row[$headerMap['machine_no']] ?? '')));
        $productId = (int)($productLookup[$partsNumber] ?? 0);
        $machineId = (int)($machineLookup[$machineNo] ?? 0);
        $errors = [];
        if ($productId <= 0) {
            $errors[] = 'parts_number could not be matched to an existing product.';
        }
        if ($machineId <= 0) {
            $errors[] = 'machine_no could not be matched to an existing machine.';
        }
        return [
            'product_id' => $productId,
            'parts_number' => strtoupper($partsNumber),
            'machine_id' => $machineId,
            'machine_no' => strtoupper($machineNo),
            'is_active' => ((string)($row[$headerMap['is_active']] ?? '1') === '0') ? 0 : 1,
            'notes' => trim((string)($row[$headerMap['notes']] ?? '')),
            'errors' => $errors,
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private function detectDuplicate(string $importType, array $row): bool
    {
        return match ($importType) {
            'products' => is_array(DB::fetchOne('SELECT id FROM products WHERE parts_number=? LIMIT 1', [$row['parts_number']])),
            'machines' => is_array(DB::fetchOne('SELECT id FROM machines WHERE machine_no=? LIMIT 1', [$row['machine_no']])),
            'daily_orders' => is_array(DB::fetchOne('SELECT id FROM daily_orders WHERE order_date=? AND customer_name=? AND product_id=? AND qty=? LIMIT 1', [$row['order_date'], $row['customer_name'], $row['product_id'], $row['qty']])),
            'pre_orders' => is_array(DB::fetchOne('SELECT id FROM pre_orders WHERE forecast_type=? AND product_id=? AND planned_qty=? AND required_date <=> ? LIMIT 1', [$row['forecast_type'], $row['product_id'], $row['planned_qty'], $row['required_date'] !== '' ? $row['required_date'] : null])),
            'part_machine_map' => is_array(DB::fetchOne('SELECT id FROM part_machine_map WHERE product_id=? AND machine_id=? AND is_active=? LIMIT 1', [$row['product_id'], $row['machine_id'], $row['is_active']])),
            default => false,
        };
    }

    /**
     * @param array<string,mixed> $row
     */
    private function commitProductRow(array $row): void
    {
        DB::query(
            'INSERT INTO products (parts_name, parts_number, model, producer, `lead`, notes, is_active)
             VALUES (?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE parts_name=VALUES(parts_name), model=VALUES(model), producer=VALUES(producer), `lead`=VALUES(`lead`), notes=VALUES(notes), is_active=VALUES(is_active), updated_at=NOW()',
            [$row['parts_name'], $row['parts_number'], $row['model'], $row['producer'], $row['lead'], $row['notes'], $row['is_active']]
        );
    }

    /**
     * @param array<string,mixed> $row
     */
    private function commitMachineRow(array $row): void
    {
        DB::query(
            'INSERT INTO machines (machine_no, machine_name, section, status, capacity_per_hour, notes, is_active)
             VALUES (?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE machine_name=VALUES(machine_name), section=VALUES(section), status=VALUES(status), capacity_per_hour=VALUES(capacity_per_hour), notes=VALUES(notes), is_active=VALUES(is_active), updated_at=NOW()',
            [$row['machine_no'], $row['machine_name'], $row['section'], $row['status'], $row['capacity_per_hour'], $row['notes'], $row['is_active']]
        );
    }

    /**
     * @param array<string,mixed> $row
     */
    private function commitDailyOrderRow(array $row): void
    {
        DB::query(
            'INSERT INTO daily_orders (order_date, required_date, customer_name, product_id, qty, dispatch_deadline, coverage_pct, coverage_status, shortage_qty, planned_supply_qty, status, notes)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [$row['order_date'], $row['required_date'] !== '' ? $row['required_date'] : null, $row['customer_name'], $row['product_id'], $row['qty'], $row['dispatch_deadline'] !== '' ? $row['dispatch_deadline'] : null, 0, 'Low', 0, 0, $row['status'], $row['notes']]
        );
    }

    /**
     * @param array<string,mixed> $row
     */
    private function commitPreOrderRow(array $row): void
    {
        DB::query(
            'INSERT INTO pre_orders (forecast_type, planning_priority, product_id, planned_qty, balance_qty, required_date, notes)
             VALUES (?,?,?,?,?,?,?)',
            [$row['forecast_type'], $row['planning_priority'], $row['product_id'], $row['planned_qty'], $row['balance_qty'], $row['required_date'] !== '' ? $row['required_date'] : null, $row['notes']]
        );
    }

    /**
     * @param array<string,mixed> $row
     */
    private function commitPartMachineRow(array $row): void
    {
        DB::query(
            'INSERT INTO part_machine_map (product_id, machine_id, is_active, notes)
             VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE notes=VALUES(notes), updated_at=NOW()',
            [$row['product_id'], $row['machine_id'], $row['is_active'], $row['notes']]
        );
    }

    private function csvValue(string $value): string
    {
        $escaped = str_replace('"', '""', $value);
        return '"' . $escaped . '"';
    }
}

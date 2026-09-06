<?php
declare(strict_types=1);

namespace Apps\Shell\Services;

use App\Core\DB;

require_once __DIR__ . '/OperatorSurfaceContributionRegistry.php';

final class DataExchangeService
{
    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null
     */
    public static function findDefinition(array $context, string $adapterKey): ?array
    {
        $needle = strtolower(trim($adapterKey));
        if ($needle === '') {
            return null;
        }

        foreach (OperatorSurfaceContributionRegistry::dataExchangeDefinitions($context) as $definition) {
            if (strtolower(trim((string)($definition['key'] ?? ''))) === $needle) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $definition
     * @param array<string,mixed> $file
     * @return array{ok:bool,error:string,file_name:string,row_count:int,header:list<string>,stored_path:string}
     */
    public static function processImportUpload(array $definition, array $file): array
    {
        $tmp = (string)($file['tmp_name'] ?? '');
        $name = trim((string)($file['name'] ?? ''));
        $size = (int)($file['size'] ?? 0);
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK) {
            return [
                'ok' => false,
                'error' => 'upload_failed',
                'file_name' => $name,
                'row_count' => 0,
                'header' => [],
                'stored_path' => '',
            ];
        }

        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return [
                'ok' => false,
                'error' => 'invalid_upload',
                'file_name' => $name,
                'row_count' => 0,
                'header' => [],
                'stored_path' => '',
            ];
        }

        if ($size <= 0 || $size > 5 * 1024 * 1024) {
            return [
                'ok' => false,
                'error' => 'size_limit',
                'file_name' => $name,
                'row_count' => 0,
                'header' => [],
                'stored_path' => '',
            ];
        }

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'tsv', 'txt'], true)) {
            return [
                'ok' => false,
                'error' => 'format_unsupported',
                'file_name' => $name,
                'row_count' => 0,
                'header' => [],
                'stored_path' => '',
            ];
        }

        $delimiter = $extension === 'tsv' ? "\t" : ',';
        $fp = fopen($tmp, 'rb');
        if ($fp === false) {
            return [
                'ok' => false,
                'error' => 'cannot_read',
                'file_name' => $name,
                'row_count' => 0,
                'header' => [],
                'stored_path' => '',
            ];
        }

        $headerRow = fgetcsv($fp, 0, $delimiter);
        $header = [];
        if (is_array($headerRow)) {
            foreach ($headerRow as $column) {
                $header[] = self::normalizeColumnName((string)$column);
            }
        }

        $rowCount = 0;
        while (($row = fgetcsv($fp, 0, $delimiter)) !== false) {
            if (is_array($row) && array_filter($row, static fn ($cell): bool => trim((string)$cell) !== '') !== []) {
                $rowCount++;
            }
        }
        fclose($fp);

        if ($header === []) {
            return [
                'ok' => false,
                'error' => 'header_missing',
                'file_name' => $name,
                'row_count' => 0,
                'header' => [],
                'stored_path' => '',
            ];
        }

        $requiredColumns = array_map(
            static fn ($value): string => self::normalizeColumnName((string)$value),
            (array)($definition['import_columns'] ?? [])
        );
        foreach ($requiredColumns as $requiredColumn) {
            if ($requiredColumn !== '' && !in_array($requiredColumn, $header, true)) {
                return [
                    'ok' => false,
                    'error' => 'required_columns_missing',
                    'file_name' => $name,
                    'row_count' => $rowCount,
                    'header' => $header,
                    'stored_path' => '',
                ];
            }
        }

        $safeName = self::safeFilename($name);
        $datePath = date('Ymd');
        $targetDir = APP_ROOT . '/storage/tmp/data_exchange/' . $datePath;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return [
                'ok' => false,
                'error' => 'storage_failed',
                'file_name' => $safeName,
                'row_count' => $rowCount,
                'header' => $header,
                'stored_path' => '',
            ];
        }

        $targetPath = $targetDir . '/' . date('His') . '_' . bin2hex(random_bytes(4)) . '_' . $safeName;
        if (!move_uploaded_file($tmp, $targetPath)) {
            return [
                'ok' => false,
                'error' => 'storage_failed',
                'file_name' => $safeName,
                'row_count' => $rowCount,
                'header' => $header,
                'stored_path' => '',
            ];
        }

        return [
            'ok' => true,
            'error' => '',
            'file_name' => $safeName,
            'row_count' => $rowCount,
            'header' => $header,
            'stored_path' => $targetPath,
        ];
    }

    /**
     * @param array<string,mixed> $definition
     * @return array{ok:bool,error:string,mode:string,processed_rows:int,inserted_rows:int,skipped_rows:int}
     */
    public static function executeImportFromFile(array $definition, string $storedPath, bool $allowApprovalRequired = false): array
    {
        if ($storedPath === '' || !is_file($storedPath)) {
            return [
                'ok' => false,
                'error' => 'stored_file_missing',
                'mode' => 'none',
                'processed_rows' => 0,
                'inserted_rows' => 0,
                'skipped_rows' => 0,
            ];
        }

        $governanceMode = strtolower(trim((string)($definition['governance_mode'] ?? 'audited')));
        if ($governanceMode === 'approval_required' && !$allowApprovalRequired) {
            return [
                'ok' => true,
                'error' => '',
                'mode' => 'queued',
                'processed_rows' => 0,
                'inserted_rows' => 0,
                'skipped_rows' => 0,
            ];
        }

        $adapterKey = strtolower(trim((string)($definition['key'] ?? '')));
        $rows = self::parseDelimitedFile($storedPath);
        if ($rows === []) {
            return [
                'ok' => false,
                'error' => 'no_rows',
                'mode' => 'none',
                'processed_rows' => 0,
                'inserted_rows' => 0,
                'skipped_rows' => 0,
            ];
        }

        return match ($adapterKey) {
            'sbaio.attendance_daily' => self::applySbaioAttendanceImport($rows),
            'sbaio.timecard_weekly' => self::applySbaioTimecardImport($rows),
            'mfg.daily_orders' => self::applyMfgDailyOrdersImport($rows),
            'mfg.production_daily' => self::applyMfgProductionImport($rows),
            'mfg.dispatch_manifest' => self::applyMfgDispatchImport($rows),
            default => [
                'ok' => true,
                'error' => '',
                'mode' => 'audited_noop',
                'processed_rows' => count($rows),
                'inserted_rows' => 0,
                'skipped_rows' => count($rows),
            ],
        };
    }

    /**
     * @param array<string,mixed> $definition
     * @param array<int,string> $header
     */
    public static function enqueueImportJob(
        array $definition,
        int $uploadedByUserId,
        string $fileName,
        string $storedPath,
        int $rowCount,
        array $header
    ): int {
        self::ensureImportJobsTable();

        $definitionJson = json_encode([
            'key' => (string)($definition['key'] ?? ''),
            'app_key' => (string)($definition['app_key'] ?? ''),
            'module_key' => (string)($definition['module_key'] ?? ''),
            'title' => (string)($definition['title'] ?? ''),
            'governance_mode' => (string)($definition['governance_mode'] ?? 'approval_required'),
            'import_columns' => (array)($definition['import_columns'] ?? []),
            'export_columns' => (array)($definition['export_columns'] ?? []),
        ], JSON_UNESCAPED_UNICODE);

        DB::query(
            'INSERT INTO data_exchange_jobs (adapter_key, app_key, module_key, governance_mode, status, uploaded_by_user_id, file_name, stored_path, row_count, header_json, definition_json, result_json)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                strtolower(trim((string)($definition['key'] ?? ''))),
                strtolower(trim((string)($definition['app_key'] ?? ''))),
                strtolower(trim((string)($definition['module_key'] ?? ''))),
                strtolower(trim((string)($definition['governance_mode'] ?? 'approval_required'))),
                'pending',
                $uploadedByUserId,
                $fileName,
                $storedPath,
                $rowCount,
                json_encode($header, JSON_UNESCAPED_UNICODE),
                $definitionJson,
                json_encode(['queued_at' => date('c')], JSON_UNESCAPED_UNICODE),
            ]
        );

        $row = DB::fetchOne('SELECT LAST_INSERT_ID() AS id');
        return (int)($row['id'] ?? 0);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function listQueueJobs(int $userId, bool $canApprove, string $adapterKey = '', int $limit = 25): array
    {
        self::ensureImportJobsTable();

        $where = ['status IN (\'pending\',\'approved_applied\',\'approved_failed\',\'rejected\')'];
        $params = [];
        if ($adapterKey !== '') {
            $where[] = 'adapter_key = ?';
            $params[] = strtolower(trim($adapterKey));
        }
        if (!$canApprove) {
            $where[] = 'uploaded_by_user_id = ?';
            $params[] = $userId;
        }
        $sql = 'SELECT id, adapter_key, app_key, module_key, governance_mode, status, uploaded_by_user_id, approved_by_user_id, file_name, row_count, created_at, approved_at, result_json
                FROM data_exchange_jobs
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY id DESC LIMIT ' . max(1, min(100, $limit));

        $rows = DB::fetchAll($sql, $params) ?: [];
        foreach ($rows as &$row) {
            $row['result'] = json_decode((string)($row['result_json'] ?? '{}'), true);
        }
        unset($row);
        return $rows;
    }

    /**
     * @return array{ok:bool,error:string,job_id:int,status:string,execution:array<string,mixed>}
     */
    public static function approveQueuedJob(int $jobId, int $approverUserId): array
    {
        self::ensureImportJobsTable();

        $job = DB::fetchOne('SELECT * FROM data_exchange_jobs WHERE id = ? LIMIT 1', [$jobId]);
        if (!is_array($job)) {
            return ['ok' => false, 'error' => 'job_not_found', 'job_id' => $jobId, 'status' => '', 'execution' => []];
        }
        if (strtolower(trim((string)($job['status'] ?? ''))) !== 'pending') {
            return ['ok' => false, 'error' => 'job_not_pending', 'job_id' => $jobId, 'status' => (string)($job['status'] ?? ''), 'execution' => []];
        }

        $definition = json_decode((string)($job['definition_json'] ?? '{}'), true);
        if (!is_array($definition) || trim((string)($definition['key'] ?? '')) === '') {
            return ['ok' => false, 'error' => 'job_definition_missing', 'job_id' => $jobId, 'status' => 'pending', 'execution' => []];
        }

        $execution = self::executeImportFromFile($definition, (string)($job['stored_path'] ?? ''), true);
        $execution['adapter_key'] = (string)($job['adapter_key'] ?? '');
        $execution['app_key'] = (string)($job['app_key'] ?? '');
        $execution['module_key'] = (string)($job['module_key'] ?? '');
        $execution['title'] = (string)($definition['title'] ?? '');
        $status = $execution['ok'] ? 'approved_applied' : 'approved_failed';
        $resultPayload = json_encode([
            'approved_at' => date('c'),
            'execution' => $execution,
        ], JSON_UNESCAPED_UNICODE);

        DB::query(
            'UPDATE data_exchange_jobs
             SET status = ?, approved_by_user_id = ?, approved_at = NOW(), result_json = ?, updated_at = NOW()
             WHERE id = ? LIMIT 1',
            [$status, $approverUserId, $resultPayload, $jobId]
        );

        return [
            'ok' => (bool)$execution['ok'],
            'error' => (string)($execution['error'] ?? ''),
            'job_id' => $jobId,
            'status' => $status,
            'execution' => $execution,
        ];
    }

    /**
     * @param array<int,array<string,string>> $rows
     * @return array{ok:bool,error:string,mode:string,processed_rows:int,inserted_rows:int,skipped_rows:int}
     */
    private static function applyMfgDailyOrdersImport(array $rows): array
    {
        $processed = 0;
        $inserted = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $processed++;

            $orderDate = trim((string)($row['order_date'] ?? ''));
            $requiredDate = trim((string)($row['required_date'] ?? ''));
            $customerName = trim((string)($row['customer_name'] ?? ''));
            $qty = (float)($row['qty'] ?? 0);
            $status = trim((string)($row['status'] ?? 'Open'));
            $notes = trim((string)($row['notes'] ?? ''));
            $dispatchDeadline = trim((string)($row['dispatch_deadline'] ?? ''));

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $orderDate) || $customerName === '' || $qty <= 0) {
                $skipped++;
                continue;
            }

            if ($requiredDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $requiredDate)) {
                $requiredDate = '';
            }

            if ($dispatchDeadline !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2})?)?$/', $dispatchDeadline)) {
                $dispatchDeadline = '';
            }
            if ($dispatchDeadline !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dispatchDeadline)) {
                $dispatchDeadline .= ' 00:00:00';
            }
            if ($dispatchDeadline !== '' && preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}$/', $dispatchDeadline)) {
                $dispatchDeadline .= ':00';
            }
            $dispatchDeadline = str_replace('T', ' ', $dispatchDeadline);

            $productId = (int)($row['product_id'] ?? 0);
            if ($productId <= 0) {
                $partNumber = trim((string)($row['parts_number'] ?? ''));
                $partName = trim((string)($row['parts_name'] ?? ''));
                if ($partNumber !== '') {
                    $product = DB::fetchOne('SELECT id FROM products WHERE parts_number = ? LIMIT 1', [$partNumber]);
                    $productId = (int)($product['id'] ?? 0);
                } elseif ($partName !== '') {
                    $product = DB::fetchOne('SELECT id FROM products WHERE parts_name = ? LIMIT 1', [$partName]);
                    $productId = (int)($product['id'] ?? 0);
                }
            }

            if ($productId <= 0) {
                $skipped++;
                continue;
            }

            try {
                DB::query(
                    'INSERT INTO daily_orders (order_date, required_date, customer_name, product_id, qty, dispatch_deadline, coverage_pct, coverage_status, shortage_qty, planned_supply_qty, status, notes)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                    [
                        $orderDate,
                        $requiredDate !== '' ? $requiredDate : null,
                        $customerName,
                        $productId,
                        $qty,
                        $dispatchDeadline !== '' ? $dispatchDeadline : null,
                        0.0,
                        'Low',
                        0.0,
                        0.0,
                        $status !== '' ? $status : 'Open',
                        $notes !== '' ? $notes : null,
                    ]
                );
                $inserted++;
            } catch (\Throwable) {
                $skipped++;
            }
        }

        return [
            'ok' => true,
            'error' => '',
            'mode' => 'approved_applied',
            'processed_rows' => $processed,
            'inserted_rows' => $inserted,
            'skipped_rows' => $skipped,
        ];
    }

    /**
     * @param array<string,mixed> $definition
     */
    public static function buildExportTemplateCsv(array $definition): string
    {
        $columns = array_values(array_filter(array_map(
            static fn ($value): string => trim((string)$value),
            (array)($definition['export_columns'] ?? $definition['import_columns'] ?? [])
        ), static fn (string $value): bool => $value !== ''));

        if ($columns === []) {
            $columns = ['id', 'record_date', 'value'];
        }

        $handle = fopen('php://temp', 'wb+');
        if ($handle === false) {
            return '';
        }

        fputcsv($handle, $columns);
        fputcsv($handle, array_fill(0, count($columns), ''));

        rewind($handle);
        $content = (string)stream_get_contents($handle);
        fclose($handle);
        return $content;
    }

    /**
     * @param array<string,mixed> $definition
     * @param array<string,mixed> $meta
     */
    public static function logAudit(int $userId, string $intent, array $definition, array $meta = []): void
    {
        $entityType = 'data_exchange_' . strtolower(trim($intent));
        $appKey = strtolower(trim((string)($definition['app_key'] ?? 'platform')));
        $moduleKey = strtolower(trim((string)($definition['module_key'] ?? 'data_exchange')));
        $title = trim((string)($definition['title'] ?? $definition['key'] ?? 'data_exchange'));
        $note = match (strtolower(trim($intent))) {
            'import' => 'Data Exchange import requested: ' . $title,
            'approve' => 'Data Exchange queued import approved: ' . $title,
            default => 'Data Exchange export template requested: ' . $title,
        };

        $payload = json_encode([
            'adapter_key' => (string)($definition['key'] ?? ''),
            'governance_mode' => (string)($definition['governance_mode'] ?? 'audited'),
            'meta' => $meta,
        ], JSON_UNESCAPED_UNICODE);

        DB::query(
            'INSERT INTO audit_activity_log (entity_type, entity_id, event_type, action_name, actor_user_id, app_key, module_key, note_text, metadata_json)
             VALUES (?,?,?,?,?,?,?,?,?)',
            [$entityType, 0, 'lifecycle', 'requested', $userId, $appKey, $moduleKey, $note, $payload]
        );
    }

    private static function safeFilename(string $name): string
    {
        $base = basename($name);
        $base = preg_replace('/[^a-zA-Z0-9._-]/', '_', $base) ?? 'upload.csv';
        return $base === '' ? 'upload.csv' : $base;
    }

    private static function normalizeColumnName(string $value): string
    {
        return strtolower(trim(preg_replace('/\s+/', '_', $value) ?? ''));
    }

    /**
     * @return array<int,array<string,string>>
     */
    private static function parseDelimitedFile(string $path): array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $delimiter = $ext === 'tsv' ? "\t" : ',';

        $fp = fopen($path, 'rb');
        if ($fp === false) {
            return [];
        }

        $headerRaw = fgetcsv($fp, 0, $delimiter);
        if (!is_array($headerRaw)) {
            fclose($fp);
            return [];
        }

        $headers = array_map(static fn ($value): string => self::normalizeColumnName((string)$value), $headerRaw);
        $rows = [];
        while (($raw = fgetcsv($fp, 0, $delimiter)) !== false) {
            if (!is_array($raw)) {
                continue;
            }
            $mapped = [];
            foreach ($headers as $index => $name) {
                if ($name === '') {
                    continue;
                }
                $mapped[$name] = trim((string)($raw[$index] ?? ''));
            }
            if (array_filter($mapped, static fn ($value): bool => trim((string)$value) !== '') === []) {
                continue;
            }
            $rows[] = $mapped;
        }

        fclose($fp);
        return $rows;
    }

    /**
     * @param array<int,array<string,string>> $rows
     * @return array{ok:bool,error:string,mode:string,processed_rows:int,inserted_rows:int,skipped_rows:int}
     */
    private static function applySbaioAttendanceImport(array $rows): array
    {
        $processed = 0;
        $inserted = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $processed++;
            $date = trim((string)($row['attendance_date'] ?? ''));
            $employeeCode = trim((string)($row['employee_code'] ?? ''));
            $status = trim((string)($row['status'] ?? 'workday'));
            $hoursWorked = (float)($row['hours_worked'] ?? 0);
            $notes = trim((string)($row['notes'] ?? ''));

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $skipped++;
                continue;
            }

            $staffId = self::resolveStaffIdByEmployeeCode($employeeCode);
            $startAt = null;
            $endAt = null;
            if ($hoursWorked > 0) {
                $startAt = $date . ' 09:00:00';
                $minutes = (int)round($hoursWorked * 60);
                $endAt = date('Y-m-d H:i:s', strtotime($startAt . ' +' . $minutes . ' minutes'));
            }

            $dayMarker = self::normalizeAttendanceMarker($status);
            $correctionNote = trim(($employeeCode !== '' ? ('employee_code=' . $employeeCode . '; ') : '') . $notes);

            try {
                DB::query(
                    'INSERT INTO sbaio_attendance_daily (staff_id, attendance_date, work_start_at, work_end_at, day_marker, source_label, is_manual_correction, correction_note)
                     VALUES (?,?,?,?,?,?,?,?)',
                    [$staffId > 0 ? $staffId : null, $date, $startAt, $endAt, $dayMarker, 'import:data_exchange', 1, $correctionNote !== '' ? $correctionNote : null]
                );
                $inserted++;
            } catch (\Throwable) {
                $skipped++;
            }
        }

        return [
            'ok' => true,
            'error' => '',
            'mode' => 'applied',
            'processed_rows' => $processed,
            'inserted_rows' => $inserted,
            'skipped_rows' => $skipped,
        ];
    }

    /**
     * @param array<int,array<string,string>> $rows
     * @return array{ok:bool,error:string,mode:string,processed_rows:int,inserted_rows:int,skipped_rows:int}
     */
    private static function applySbaioTimecardImport(array $rows): array
    {
        $processed = 0;
        $inserted = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $processed++;

            $weekStart = trim((string)($row['week_start_date'] ?? ''));
            $employeeCode = trim((string)($row['employee_code'] ?? ''));
            $clockIn = trim((string)($row['clock_in'] ?? '09:00'));
            $clockOut = trim((string)($row['clock_out'] ?? '17:00'));
            $breakMinutes = (int)($row['break_minutes'] ?? 0);
            $totalHours = (float)($row['total_hours'] ?? 0);

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekStart)) {
                $skipped++;
                continue;
            }

            $workDate = $weekStart;
            $startAt = self::composeDateTime($workDate, $clockIn);
            $endAt = self::composeDateTime($workDate, $clockOut);
            if ($startAt === null || $endAt === null) {
                $skipped++;
                continue;
            }

            $workedMinutes = (int)max(0, round($totalHours * 60));
            if ($workedMinutes === 0) {
                $diff = (int)((strtotime($endAt) - strtotime($startAt)) / 60);
                $workedMinutes = max(0, $diff - max(0, $breakMinutes));
            }

            $staffId = self::resolveStaffIdByEmployeeCode($employeeCode);

            try {
                DB::query(
                    'INSERT INTO sbaio_timecards_daily (staff_id, legacy_name_ref, work_date, actual_start_at, actual_end_at, break_minutes, worked_minutes, day_status, attendance_marker, exception_status, approval_status)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                    [
                        $staffId > 0 ? $staffId : null,
                        $employeeCode !== '' ? $employeeCode : null,
                        $workDate,
                        $startAt,
                        $endAt,
                        max(0, $breakMinutes),
                        $workedMinutes,
                        'workday',
                        'import',
                        'normal',
                        'draft',
                    ]
                );
                $inserted++;
            } catch (\Throwable) {
                $skipped++;
            }
        }

        return [
            'ok' => true,
            'error' => '',
            'mode' => 'applied',
            'processed_rows' => $processed,
            'inserted_rows' => $inserted,
            'skipped_rows' => $skipped,
        ];
    }

    private static function resolveStaffIdByEmployeeCode(string $employeeCode): int
    {
        if ($employeeCode === '') {
            return 0;
        }

        try {
            $row = DB::fetchOne(
                'SELECT id FROM sbaio_staff WHERE LOWER(COALESCE(employee_code,\'\')) = LOWER(?) LIMIT 1',
                [$employeeCode]
            );
            return (int)($row['id'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    private static function normalizeAttendanceMarker(string $status): string
    {
        $s = strtolower(trim($status));
        return match ($s) {
            'present', 'workday', 'normal' => 'workday',
            'late' => 'late',
            'absent' => 'absent',
            'leave' => 'leave',
            default => 'workday',
        };
    }

    private static function composeDateTime(string $date, string $time): ?string
    {
        $normalized = preg_replace('/[^0-9:]/', '', $time) ?? '';
        if (!preg_match('/^\d{1,2}:\d{2}$/', $normalized)) {
            return null;
        }
        [$hh, $mm] = array_map('intval', explode(':', $normalized, 2));
        if ($hh < 0 || $hh > 23 || $mm < 0 || $mm > 59) {
            return null;
        }
        return sprintf('%s %02d:%02d:00', $date, $hh, $mm);
    }

    /**
     * @param array<int,array<string,string>> $rows
     * @return array{ok:bool,error:string,mode:string,processed_rows:int,inserted_rows:int,skipped_rows:int}
     */
    private static function applyMfgProductionImport(array $rows): array
    {
        $processed = 0;
        $inserted = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $processed++;
            $date = trim((string)($row['production_date'] ?? ''));
            $machineNo = trim((string)($row['machine_no'] ?? ''));
            $partNumber = trim((string)($row['part_number'] ?? ''));
            $shift = trim((string)($row['shift'] ?? 'Day'));
            $producedQty = (float)($row['produced_qty'] ?? 0);
            $rejectedQty = (float)($row['rejected_qty'] ?? 0);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $machineNo === '' || $partNumber === '' || $producedQty <= 0) {
                $skipped++;
                continue;
            }

            $machine = DB::fetchOne('SELECT id FROM machines WHERE machine_no = ? LIMIT 1', [$machineNo]);
            $product = DB::fetchOne('SELECT id FROM products WHERE parts_number = ? LIMIT 1', [$partNumber]);
            $machineId = (int)($machine['id'] ?? 0);
            $productId = (int)($product['id'] ?? 0);
            if ($machineId <= 0 || $productId <= 0) {
                $skipped++;
                continue;
            }

            $goodQty = max(0, $producedQty - max(0, $rejectedQty));
            try {
                DB::query(
                    'INSERT INTO production_entries (production_date, shift, machine_id, product_id, produced_qty, rejected_qty, good_qty, status, notes)
                     VALUES (?,?,?,?,?,?,?,?,?)',
                    [$date, $shift !== '' ? $shift : 'Day', $machineId, $productId, $producedQty, max(0, $rejectedQty), $goodQty, 'Draft', 'import:data_exchange']
                );
                $inserted++;
            } catch (\Throwable) {
                $skipped++;
            }
        }

        return [
            'ok' => true,
            'error' => '',
            'mode' => 'approved_applied',
            'processed_rows' => $processed,
            'inserted_rows' => $inserted,
            'skipped_rows' => $skipped,
        ];
    }

    /**
     * @param array<int,array<string,string>> $rows
     * @return array{ok:bool,error:string,mode:string,processed_rows:int,inserted_rows:int,skipped_rows:int}
     */
    private static function applyMfgDispatchImport(array $rows): array
    {
        $processed = 0;
        $inserted = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $processed++;
            $date = trim((string)($row['dispatch_date'] ?? ''));
            $partNumber = trim((string)($row['part_number'] ?? ''));
            $qty = (float)($row['dispatch_qty'] ?? 0);
            $routeCode = trim((string)($row['route_code'] ?? ''));
            $status = trim((string)($row['status'] ?? 'Ready'));
            $truckNo = trim((string)($row['truck_no'] ?? ''));
            $driver = trim((string)($row['driver_name'] ?? ''));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $partNumber === '' || $qty <= 0) {
                $skipped++;
                continue;
            }

            $product = DB::fetchOne('SELECT id FROM products WHERE parts_number = ? LIMIT 1', [$partNumber]);
            $productId = (int)($product['id'] ?? 0);
            if ($productId <= 0) {
                $skipped++;
                continue;
            }

            try {
                DB::query(
                    'INSERT INTO dispatch_entries (dispatch_date, product_id, dispatchable_qty, destination, dispatch_type, dispatch_status, remarks, driver_name, truck_no, prepared_by, completion_status)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                    [
                        $date,
                        $productId,
                        $qty,
                        $routeCode !== '' ? $routeCode : 'Import Route',
                        'Regular',
                        $status !== '' ? $status : 'Ready',
                        'import:data_exchange',
                        $driver !== '' ? $driver : null,
                        $truckNo !== '' ? $truckNo : null,
                        'data_exchange',
                        'draft',
                    ]
                );
                $inserted++;
            } catch (\Throwable) {
                $skipped++;
            }
        }

        return [
            'ok' => true,
            'error' => '',
            'mode' => 'approved_applied',
            'processed_rows' => $processed,
            'inserted_rows' => $inserted,
            'skipped_rows' => $skipped,
        ];
    }

    private static function ensureImportJobsTable(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }
        DB::query(
            "CREATE TABLE IF NOT EXISTS data_exchange_jobs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                adapter_key VARCHAR(120) NOT NULL,
                app_key VARCHAR(80) NOT NULL,
                module_key VARCHAR(120) NOT NULL,
                governance_mode VARCHAR(60) NOT NULL DEFAULT 'approval_required',
                status VARCHAR(40) NOT NULL DEFAULT 'pending',
                uploaded_by_user_id INT NOT NULL,
                approved_by_user_id INT NULL,
                file_name VARCHAR(255) NOT NULL,
                stored_path VARCHAR(500) NOT NULL,
                row_count INT NOT NULL DEFAULT 0,
                header_json TEXT NULL,
                definition_json LONGTEXT NULL,
                result_json LONGTEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                approved_at DATETIME NULL,
                INDEX idx_dx_jobs_status (status),
                INDEX idx_dx_jobs_adapter (adapter_key),
                INDEX idx_dx_jobs_uploaded_by (uploaded_by_user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $ensured = true;
    }
}

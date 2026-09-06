<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Controllers;

use App\Core\AuditLogService;
use App\Core\Auth;
use App\Core\DB;
use App\Core\HandoffEngine;
use App\Core\View;
use Apps\Manufacturing\Services\Order360Service;
use Apps\Manufacturing\Services\DemandEngineService;
use Plugins\Coverage\Services\CoverageService;
use ZipArchive;

final class DailyOrdersController
{
    private const IMPORT_MAX_ROWS = 5000;

    public static function index(View $view): void
    {
        AuditLogService::ensureSchema();
        $q = trim((string)($_GET['q'] ?? ''));
        $status = trim((string)($_GET['status'] ?? ''));
        $productId = (int)($_GET['product_id'] ?? 0);
        $coverageFilter = strtolower(trim((string)($_GET['coverage'] ?? (string)($_GET['coverage_status'] ?? ''))));
        $fromDate = trim((string)($_GET['from_date'] ?? ''));
        $toDate = trim((string)($_GET['to_date'] ?? ''));

        $sql = "SELECT d.*, p.parts_name, p.parts_number
                FROM daily_orders d
                INNER JOIN products p ON p.id = d.product_id
                WHERE 1=1";
        $params = [];

        if ($q !== '') {
            $sql .= " AND (d.customer_name LIKE ? OR p.parts_name LIKE ? OR p.parts_number LIKE ?)";
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if ($status !== '') {
            $sql .= ' AND d.status = ?';
            $params[] = $status;
        }
        if ($productId > 0) {
            $sql .= ' AND d.product_id = ?';
            $params[] = $productId;
        }
        if ($coverageFilter !== '') {
            $map = [
                'low' => 'Low',
                'partial' => 'Partial',
                'full' => 'Full',
            ];
            if (isset($map[$coverageFilter])) {
                $sql .= ' AND d.coverage_status = ?';
                $params[] = $map[$coverageFilter];
            }
        }
        if ($fromDate !== '') {
            $sql .= ' AND d.order_date >= ?';
            $params[] = $fromDate;
        }
        if ($toDate !== '') {
            $sql .= ' AND d.order_date <= ?';
            $params[] = $toDate;
        }

        $sql .= ' ORDER BY d.order_date DESC, d.id DESC LIMIT 400';
        $rows = DB::fetchAll($sql, $params);
        $auditSummary = AuditLogService::latestByEntityIds('daily_order', array_map(static fn(array $row): int => (int)($row['id'] ?? 0), $rows));

        $view->render('manufacturing::daily_orders/index.php', [
            'pageTitle' => 'Daily Orders',
            'rows' => $rows,
            'audit_summary' => $auditSummary,
            'q' => $q,
            'status' => $status,
            'product_id' => $productId,
            'coverage' => $coverageFilter,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'flash' => self::pullFlash('ok'),
            'error' => self::pullFlash('err'),
        ]);
    }

    public static function addForm(View $view): void
    {
        $view->render('manufacturing::daily_orders/add.php', [
            'pageTitle' => 'Add Daily Order',
            'products' => self::products(),
            'old' => $_SESSION['daily_orders_old'] ?? [],
            'error' => self::pullFlash('err'),
        ]);
        unset($_SESSION['daily_orders_old']);
    }

    public static function importForm(View $view): void
    {
        $view->render('manufacturing::daily_orders/import.php', [
            'pageTitle' => 'Import Daily Orders',
            'error' => self::pullFlash('err'),
        ]);
    }

    /**
     * @param array<string,mixed>|null $file
     */
    public static function importUpload(?array $file): void
    {
        AuditLogService::ensureSchema();
        if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            self::flash('err', 'Please select a CSV or Excel file to import.');
            header('Location: /daily-orders/import');
            exit;
        }

        $tmpPath = (string)($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            self::flash('err', 'Upload failed. Please try again.');
            header('Location: /daily-orders/import');
            exit;
        }

        $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'xlsx'], true)) {
            self::flash('err', 'Unsupported file type. Use .csv or .xlsx');
            header('Location: /daily-orders/import');
            exit;
        }

        try {
            $rows = $ext === 'xlsx' ? self::readXlsxRows($tmpPath) : self::readCsvRows($tmpPath);
        } catch (\Throwable $e) {
            self::flash('err', 'Unable to read import file: ' . $e->getMessage());
            header('Location: /daily-orders/import');
            exit;
        }

        if (empty($rows)) {
            self::flash('err', 'No rows found in file.');
            header('Location: /daily-orders/import');
            exit;
        }

        if (count($rows) > self::IMPORT_MAX_ROWS) {
            self::flash('err', 'Too many rows. Maximum allowed per import is ' . self::IMPORT_MAX_ROWS . '.');
            header('Location: /daily-orders/import');
            exit;
        }

        $header = array_shift($rows);
        if (!is_array($header)) {
            self::flash('err', 'Header row is missing or invalid.');
            header('Location: /daily-orders/import');
            exit;
        }

        $headerMap = self::buildHeaderMap($header);
        if (!isset($headerMap['order_date']) || !isset($headerMap['customer_name']) || !isset($headerMap['qty'])) {
            self::flash('err', 'Header must include order_date, customer_name, and qty columns.');
            header('Location: /daily-orders/import');
            exit;
        }
        if (!isset($headerMap['product_id']) && !isset($headerMap['parts_number']) && !isset($headerMap['parts_name'])) {
            self::flash('err', 'Header must include product_id, parts_number, or parts_name.');
            header('Location: /daily-orders/import');
            exit;
        }

        $productMaps = self::buildProductLookupMaps();
        $inserted = 0;
        $skipped = 0;
        $reasons = [];
        $affectedProductIds = [];
        $insertedAuditRows = [];

        $conn = DB::conn();
        $conn->begin_transaction();
        try {
            foreach ($rows as $lineIndex => $row) {
                if (!is_array($row)) {
                    $skipped++;
                    self::collectReason($reasons, $lineIndex + 2, 'Invalid row format.');
                    continue;
                }

                $orderDate = self::cellFromMap($row, $headerMap, ['order_date']);
                $customer = self::cellFromMap($row, $headerMap, ['customer_name']);
                $qtyRaw = self::cellFromMap($row, $headerMap, ['qty']);
                $requiredDate = self::cellFromMap($row, $headerMap, ['required_date']);
                $dispatchDeadline = self::cellFromMap($row, $headerMap, ['dispatch_deadline']);
                $status = self::cellFromMap($row, $headerMap, ['status']);
                $notes = self::cellFromMap($row, $headerMap, ['notes']);

                $orderDate = self::normalizeDate($orderDate);
                $requiredDate = self::normalizeDate($requiredDate);
                $dispatchDeadline = self::normalizeDate($dispatchDeadline);
                $customer = trim($customer);
                $qty = self::parseFloat($qtyRaw);

                $productId = self::resolveProductId($row, $headerMap, $productMaps);

                if ($orderDate === '' || $customer === '' || $qty <= 0 || $productId <= 0) {
                    $skipped++;
                    self::collectReason($reasons, $lineIndex + 2, 'Required values missing or invalid (order_date, customer_name, qty, product).');
                    continue;
                }

                DB::query(
                    'INSERT INTO daily_orders (order_date, required_date, customer_name, product_id, qty, dispatch_deadline, coverage_pct, coverage_status, shortage_qty, planned_supply_qty, status, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                    [
                        $orderDate,
                        $requiredDate,
                        $customer,
                        $productId,
                        $qty,
                        $dispatchDeadline,
                        0.0,
                        'Low',
                        0.0,
                        0.0,
                        $status !== '' ? $status : 'Open',
                        $notes,
                    ]
                );
                $insertedId = (int)$conn->insert_id;
                $inserted++;
                $affectedProductIds[$productId] = true;
                $insertedAuditRows[] = [
                    'id' => $insertedId,
                    'order_date' => $orderDate,
                    'required_date' => $requiredDate,
                    'customer_name' => $customer,
                    'product_id' => $productId,
                    'qty' => $qty,
                    'dispatch_deadline' => $dispatchDeadline,
                    'status' => $status !== '' ? $status : 'Open',
                    'notes' => $notes,
                ];
            }

            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollback();
            self::flash('err', 'Import failed: ' . $e->getMessage());
            header('Location: /daily-orders/import');
            exit;
        }

        foreach ($insertedAuditRows as $auditRow) {
            AuditLogService::logEvent(
                'daily_order',
                (int)($auditRow['id'] ?? 0),
                AuditLogService::EVENT_LIFECYCLE,
                AuditLogService::ACTION_CREATED,
                Auth::user(),
                [
                    'app' => 'manufacturing',
                    'module' => 'demands',
                    'new_state' => (string)($auditRow['status'] ?? 'Open'),
                    'note' => 'Daily order imported.',
                    'metadata' => ['source' => 'bulk_import'],
                    'diff' => AuditLogService::diffImportantFields([], (array)$auditRow, self::auditDiffFields()),
                ]
            );
        }

        self::recalculateCoverage(array_map('intval', array_keys($affectedProductIds)));
        try {
            $recalculatedProductIds = array_map('intval', array_keys($affectedProductIds));
            DemandEngineService::recalculateDemands($recalculatedProductIds);
            self::logDemandRegeneratedForProducts($recalculatedProductIds, 'bulk_import');
        } catch (\Throwable $e) {
            self::flash('err', 'Demand refresh failed: ' . $e->getMessage());
        }

        if ($inserted > 0) {
            self::flash('ok', 'Import completed. Inserted ' . $inserted . ' row(s).' . ($skipped > 0 ? ' Skipped ' . $skipped . ' row(s).' : ''));
        } else {
            self::flash('err', 'Import finished with no inserted rows.');
        }

        if ($skipped > 0 && !empty($reasons)) {
            self::flash('err', 'Skipped ' . $skipped . ' row(s): ' . implode(' | ', $reasons));
        }
    }

    public static function create(array $input): void
    {
        AuditLogService::ensureSchema();
        $data = self::sanitize($input);
        $_SESSION['daily_orders_old'] = $data;

        if ($data['order_date'] === '' || $data['customer_name'] === '' || $data['product_id'] <= 0 || $data['qty'] <= 0) {
            self::flash('err', 'Order date, customer, product, and qty are required.');
            header('Location: /daily-orders/add');
            exit;
        }

        DB::query(
            'INSERT INTO daily_orders (order_date, required_date, customer_name, product_id, qty, dispatch_deadline, coverage_pct, coverage_status, shortage_qty, planned_supply_qty, status, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $data['order_date'], $data['required_date'], $data['customer_name'], $data['product_id'], $data['qty'],
                $data['dispatch_deadline'], 0.0, 'Low', 0.0, 0.0,
                $data['status'], $data['notes']
            ]
        );
        $recordId = (int)DB::conn()->insert_id;
        $created = DB::fetchOne('SELECT * FROM daily_orders WHERE id=? LIMIT 1', [$recordId]) ?: [];
        AuditLogService::logEvent(
            'daily_order',
            $recordId,
            AuditLogService::EVENT_LIFECYCLE,
            AuditLogService::ACTION_CREATED,
            Auth::user(),
            [
                'app' => 'manufacturing',
                'module' => 'demands',
                'new_state' => (string)($created['status'] ?? 'Open'),
                'note' => 'Daily order created.',
                'diff' => AuditLogService::diffImportantFields([], $created, self::auditDiffFields()),
            ]
        );

        self::recalculateCoverage([$data['product_id']]);
        try {
            $recalculatedProductIds = [$data['product_id']];
            DemandEngineService::recalculateDemands($recalculatedProductIds);
            self::logDemandRegeneratedForProducts($recalculatedProductIds, 'create');
        } catch (\Throwable $e) {
            self::flash('err', 'Demand refresh failed: ' . $e->getMessage());
        }

        HandoffEngine::syncDailyOrder($recordId);
        unset($_SESSION['daily_orders_old']);
        self::flash('ok', 'Daily order created.');
    }

    public static function editForm(View $view, int $id): void
    {
        AuditLogService::ensureSchema();
        HandoffEngine::syncDailyOrder($id);
        if ($id <= 0) {
            self::flash('err', 'Invalid daily order id.');
            header('Location: /daily-orders');
            exit;
        }

        $row = DB::fetchOne('SELECT * FROM daily_orders WHERE id=? LIMIT 1', [$id]);
        if (!$row) {
            self::flash('err', 'Daily order not found.');
            header('Location: /daily-orders');
            exit;
        }

        $view->render('manufacturing::daily_orders/edit.php', [
            'pageTitle' => 'Edit Daily Order',
            'row' => $row,
            'products' => self::products(),
            'ownership_summary' => HandoffEngine::summaryForEntity('daily_order', $id),
            'activity_timeline' => AuditLogService::timeline('daily_order', $id, 80),
            'error' => self::pullFlash('err'),
        ]);
    }

    public static function order360(View $view, int $id): void
    {
        HandoffEngine::syncDailyOrder($id);
        if ($id <= 0) {
            self::flash('err', 'Invalid daily order id.');
            header('Location: /daily-orders');
            exit;
        }

        $payload = Order360Service::build($id);
        if (!$payload) {
            self::flash('err', 'Daily order not found.');
            header('Location: /daily-orders');
            exit;
        }

        $order = (array)($payload['order'] ?? []);
        $user = \App\Core\Auth::user();
        $currentRole = strtolower(trim((string)($user['role'] ?? '')));
        $view->render('manufacturing::daily_orders/order_360.php', [
            'pageTitle' => 'Order 360 #' . (int)($order['id'] ?? $id),
            'payload' => $payload,
            'current_role' => $currentRole,
            'error' => self::pullFlash('err'),
            'flash' => self::pullFlash('ok'),
        ]);
    }

    public static function update(array $input): void
    {
        AuditLogService::ensureSchema();
        $id = (int)($input['id'] ?? 0);
        $data = self::sanitize($input);

        if ($id <= 0 || $data['order_date'] === '' || $data['customer_name'] === '' || $data['product_id'] <= 0 || $data['qty'] <= 0) {
            self::flash('err', 'Invalid payload.');
            header('Location: /daily-orders');
            exit;
        }

        $existing = DB::fetchOne('SELECT * FROM daily_orders WHERE id=? LIMIT 1', [$id]);
        if (!$existing) {
            self::flash('err', 'Daily order not found.');
            header('Location: /daily-orders');
            exit;
        }
        $oldProductId = (int)($existing['product_id'] ?? 0);

        DB::query(
            'UPDATE daily_orders SET order_date=?, required_date=?, customer_name=?, product_id=?, qty=?, dispatch_deadline=?, coverage_pct=?, coverage_status=?, shortage_qty=?, planned_supply_qty=?, status=?, notes=?, updated_at=NOW() WHERE id=?',
            [
                $data['order_date'], $data['required_date'], $data['customer_name'], $data['product_id'], $data['qty'],
                $data['dispatch_deadline'], 0.0, 'Low', 0.0, 0.0,
                $data['status'], $data['notes'], $id
            ]
        );
        $after = DB::fetchOne('SELECT * FROM daily_orders WHERE id=? LIMIT 1', [$id]) ?: [];
        $nextStatus = strtolower(trim((string)($after['status'] ?? '')));
        $action = in_array($nextStatus, ['cancelled', 'canceled', 'archived'], true)
            ? AuditLogService::ACTION_CANCELLED
            : AuditLogService::ACTION_UPDATED;
        AuditLogService::logEvent(
            'daily_order',
            $id,
            AuditLogService::EVENT_LIFECYCLE,
            $action,
            Auth::user(),
            [
                'app' => 'manufacturing',
                'module' => 'demands',
                'old_state' => (string)($existing['status'] ?? ''),
                'new_state' => (string)($after['status'] ?? ''),
                'note' => $action === AuditLogService::ACTION_CANCELLED ? 'Daily order cancelled/archived.' : 'Daily order updated.',
                'diff' => AuditLogService::diffImportantFields($existing, $after, self::auditDiffFields()),
            ]
        );

        self::recalculateCoverage([$oldProductId, $data['product_id']]);
        try {
            $recalculatedProductIds = [$oldProductId, $data['product_id']];
            DemandEngineService::recalculateDemands($recalculatedProductIds);
            self::logDemandRegeneratedForProducts($recalculatedProductIds, 'update');
        } catch (\Throwable $e) {
            self::flash('err', 'Demand refresh failed: ' . $e->getMessage());
        }

        HandoffEngine::syncDailyOrder($id);
        self::flash('ok', 'Daily order updated.');
    }

    public static function delete(int $id): void
    {
        AuditLogService::ensureSchema();
        if ($id <= 0) {
            self::flash('err', 'Invalid daily order id.');
            return;
        }
        $existing = DB::fetchOne('SELECT * FROM daily_orders WHERE id=? LIMIT 1', [$id]);
        if (!$existing) {
            self::flash('err', 'Daily order not found.');
            return;
        }

        DB::query('DELETE FROM daily_orders WHERE id=? LIMIT 1', [$id]);
        AuditLogService::logEvent(
            'daily_order',
            $id,
            AuditLogService::EVENT_LIFECYCLE,
            AuditLogService::ACTION_DELETED,
            Auth::user(),
            [
                'app' => 'manufacturing',
                'module' => 'demands',
                'old_state' => (string)($existing['status'] ?? ''),
                'note' => 'Daily order deleted.',
                'diff' => AuditLogService::diffImportantFields($existing, [], self::auditDiffFields()),
            ]
        );
        self::recalculateCoverage([(int)($existing['product_id'] ?? 0)]);
        try {
            $recalculatedProductIds = [(int)($existing['product_id'] ?? 0)];
            DemandEngineService::recalculateDemands($recalculatedProductIds);
            self::logDemandRegeneratedForProducts($recalculatedProductIds, 'delete');
        } catch (\Throwable $e) {
            self::flash('err', 'Demand refresh failed: ' . $e->getMessage());
        }
        self::flash('ok', 'Daily order deleted.');
    }

    private static function products(): array
    {
        return DB::fetchAll('SELECT id, parts_name, parts_number FROM products WHERE is_active=1 ORDER BY parts_name ASC LIMIT 1000');
    }

    private static function sanitize(array $input): array
    {
        return [
            'order_date' => trim((string)($input['order_date'] ?? '')),
            'required_date' => trim((string)($input['required_date'] ?? '')),
            'customer_name' => trim((string)($input['customer_name'] ?? '')),
            'product_id' => (int)($input['product_id'] ?? 0),
            'qty' => (float)($input['qty'] ?? 0),
            'dispatch_deadline' => trim((string)($input['dispatch_deadline'] ?? '')),
            'status' => trim((string)($input['status'] ?? 'Open')),
            'notes' => trim((string)($input['notes'] ?? '')),
        ];
    }

    /**
     * @return array<int,array<int,string>>
     */
    private static function readCsvRows(string $path): array
    {
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            throw new \RuntimeException('Unable to open CSV file.');
        }

        $rows = [];
        $firstLine = fgets($fh);
        if ($firstLine === false) {
            fclose($fh);
            return [];
        }
        $delimiter = self::detectDelimiter($firstLine);
        rewind($fh);

        while (($line = fgetcsv($fh, 0, $delimiter)) !== false) {
            $rows[] = array_map(static fn($v) => trim((string)$v), $line);
        }
        fclose($fh);
        return $rows;
    }

    /**
     * @return array<int,array<int,string>>
     */
    private static function readXlsxRows(string $path): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new \RuntimeException('ZipArchive extension is required for XLSX import.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Could not open XLSX archive.');
        }

        $sharedStrings = self::readSharedStringsFromXlsx($zip);
        $sheetXml = self::readFirstWorksheetXml($zip);
        $zip->close();

        if ($sheetXml === '') {
            return [];
        }

        $xml = simplexml_load_string($sheetXml);
        if ($xml === false || !isset($xml->sheetData)) {
            return [];
        }

        $rows = [];
        foreach ($xml->sheetData->row as $rowNode) {
            $row = [];
            $maxCol = -1;
            foreach ($rowNode->c as $cell) {
                $ref = (string)($cell['r'] ?? '');
                $colIndex = self::columnIndexFromRef($ref);
                if ($colIndex > $maxCol) {
                    $maxCol = $colIndex;
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

                if ($colIndex < 0) {
                    $row[] = trim($value);
                } else {
                    $row[$colIndex] = trim($value);
                }
            }

            if ($maxCol >= 0) {
                for ($i = 0; $i <= $maxCol; $i++) {
                    if (!array_key_exists($i, $row)) {
                        $row[$i] = '';
                    }
                }
                ksort($row);
                $rows[] = array_values($row);
            }
        }

        return $rows;
    }

    /**
     * @return array<int,string>
     */
    private static function readSharedStringsFromXlsx(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if (!is_string($xml) || $xml === '') {
            return [];
        }

        $doc = simplexml_load_string($xml);
        if ($doc === false) {
            return [];
        }

        $strings = [];
        foreach ($doc->si as $si) {
            if (isset($si->t)) {
                $strings[] = (string)$si->t;
                continue;
            }

            $text = '';
            foreach ($si->r as $run) {
                $text .= (string)($run->t ?? '');
            }
            $strings[] = $text;
        }
        return $strings;
    }

    private static function readFirstWorksheetXml(ZipArchive $zip): string
    {
        $firstSheet = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string)$zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                $firstSheet = $name;
                break;
            }
        }

        if ($firstSheet === '') {
            return '';
        }

        $xml = $zip->getFromName($firstSheet);
        return is_string($xml) ? $xml : '';
    }

    private static function columnIndexFromRef(string $ref): int
    {
        if ($ref === '') {
            return -1;
        }

        if (!preg_match('/^([A-Z]+)/', strtoupper($ref), $m)) {
            return -1;
        }

        $letters = $m[1];
        $idx = 0;
        $len = strlen($letters);
        for ($i = 0; $i < $len; $i++) {
            $idx = ($idx * 26) + (ord($letters[$i]) - 64);
        }
        return $idx - 1;
    }

    private static function detectDelimiter(string $line): string
    {
        $candidates = [',', ';', "\t", '|'];
        $best = ',';
        $bestCount = -1;
        foreach ($candidates as $delimiter) {
            $count = substr_count($line, $delimiter);
            if ($count > $bestCount) {
                $bestCount = $count;
                $best = $delimiter;
            }
        }
        return $best;
    }

    /**
     * @param array<int,string> $header
     * @return array<string,int>
     */
    private static function buildHeaderMap(array $header): array
    {
        $map = [];
        foreach ($header as $idx => $name) {
            $normalized = self::normalizeHeader($name);
            if ($normalized === '') {
                continue;
            }
            $map[$normalized] = $idx;
        }

        return $map;
    }

    private static function normalizeHeader(string $value): string
    {
        $k = str_replace("\xEF\xBB\xBF", '', $value);
        $k = strtolower(trim($k));
        $k = str_replace([' ', '-', '.'], '_', $k);
        $aliases = [
            'date' => 'order_date',
            'orderdate' => 'order_date',
            'customer' => 'customer_name',
            'client' => 'customer_name',
            'part_number' => 'parts_number',
            'item_code' => 'parts_number',
            'part_name' => 'parts_name',
            'product_name' => 'parts_name',
            'quantity' => 'qty',
            'amount' => 'qty',
            'deadline' => 'dispatch_deadline',
            'delivery_date' => 'required_date',
        ];
        return $aliases[$k] ?? $k;
    }

    /**
     * @return array<string,array<string,int>>
     */
    private static function buildProductLookupMaps(): array
    {
        $rows = DB::fetchAll('SELECT id, parts_number, parts_name FROM products');
        $byId = [];
        $byNumber = [];
        $byName = [];
        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $byId[(string)$id] = $id;

            $num = strtolower(trim((string)($row['parts_number'] ?? '')));
            if ($num !== '') {
                $byNumber[$num] = $id;
            }

            $name = strtolower(trim((string)($row['parts_name'] ?? '')));
            if ($name !== '') {
                $byName[$name] = $id;
            }
        }
        return ['id' => $byId, 'number' => $byNumber, 'name' => $byName];
    }

    /**
     * @param array<int,string> $row
     * @param array<string,int> $headerMap
     * @param array<string,array<string,int>> $productMaps
     */
    private static function resolveProductId(array $row, array $headerMap, array $productMaps): int
    {
        $directId = self::cellFromMap($row, $headerMap, ['product_id']);
        if ($directId !== '') {
            $id = (int)$directId;
            if ($id > 0 && isset($productMaps['id'][(string)$id])) {
                return $id;
            }
        }

        $partsNumber = strtolower(trim(self::cellFromMap($row, $headerMap, ['parts_number'])));
        if ($partsNumber !== '' && isset($productMaps['number'][$partsNumber])) {
            return (int)$productMaps['number'][$partsNumber];
        }

        $partsName = strtolower(trim(self::cellFromMap($row, $headerMap, ['parts_name'])));
        if ($partsName !== '' && isset($productMaps['name'][$partsName])) {
            return (int)$productMaps['name'][$partsName];
        }

        return 0;
    }

    /**
     * @param array<int,string> $row
     * @param array<string,int> $headerMap
     * @param array<int,string> $keys
     */
    private static function cellFromMap(array $row, array $headerMap, array $keys): string
    {
        foreach ($keys as $key) {
            if (!isset($headerMap[$key])) {
                continue;
            }
            $idx = (int)$headerMap[$key];
            return trim((string)($row[$idx] ?? ''));
        }
        return '';
    }

    private static function normalizeDate(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw;
        }

        if (preg_match('/^\d{4}\/\d{2}\/\d{2}$/', $raw)) {
            return str_replace('/', '-', $raw);
        }

        if (is_numeric($raw)) {
            $excelEpochDays = (float)$raw;
            if ($excelEpochDays > 0) {
                $seconds = (int)round(($excelEpochDays - 25569) * 86400);
                if ($seconds > 0) {
                    return gmdate('Y-m-d', $seconds);
                }
            }
        }

        $ts = strtotime($raw);
        if ($ts !== false) {
            return date('Y-m-d', $ts);
        }

        return '';
    }

    private static function parseFloat(string $raw): float
    {
        $raw = trim($raw);
        if ($raw === '') {
            return 0.0;
        }
        $normalized = str_replace([',', ' '], ['', ''], $raw);
        return is_numeric($normalized) ? (float)$normalized : 0.0;
    }

    /**
     * @param array<int,string> $reasons
     */
    private static function collectReason(array &$reasons, int $line, string $message): void
    {
        if (count($reasons) >= 6) {
            return;
        }
        $reasons[] = 'line ' . $line . ': ' . $message;
    }

    private static function recalculateCoverage(array $productIds): void
    {
        try {
            CoverageService::recalculateForProducts($productIds);
        } catch (\Throwable $e) {
            self::flash('err', 'Coverage refresh failed: ' . $e->getMessage());
        }
    }

    /**
     * @param array<int,int> $productIds
     */
    private static function logDemandRegeneratedForProducts(array $productIds, string $source): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $productIds), static fn(int $v): bool => $v > 0)));
        foreach ($ids as $productId) {
            AuditLogService::logEvent(
                'product',
                $productId,
                AuditLogService::EVENT_SYSTEM,
                AuditLogService::ACTION_DEMAND_REGENERATED,
                Auth::user(),
                [
                    'app' => 'manufacturing',
                    'module' => 'demands',
                    'note' => 'Demand engine regenerated for product.',
                    'metadata' => [
                        'source' => $source,
                    ],
                ]
            );
        }
    }

    /**
     * @return array<int,string>
     */
    private static function auditDiffFields(): array
    {
        return [
            'order_date',
            'required_date',
            'customer_name',
            'product_id',
            'qty',
            'dispatch_deadline',
            'coverage_pct',
            'coverage_status',
            'shortage_qty',
            'planned_supply_qty',
            'status',
            'notes',
        ];
    }

    private static function flash(string $key, string $msg): void
    {
        $_SESSION['daily_orders_flash_' . $key] = $msg;
    }

    private static function pullFlash(string $key): string
    {
        $k = 'daily_orders_flash_' . $key;
        $v = (string)($_SESSION[$k] ?? '');
        unset($_SESSION[$k]);
        return $v;
    }
}

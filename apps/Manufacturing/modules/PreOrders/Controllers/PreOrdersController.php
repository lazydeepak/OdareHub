<?php
declare(strict_types=1);

namespace Plugins\PreOrders\Controllers;

use App\Core\DB;
use App\Core\View;
use Plugins\Coverage\Services\CoverageService;
use ZipArchive;

final class PreOrdersController
{
    private const IMPORT_MAX_ROWS = 5000;

    public static function index(View $view): void
    {
        $q = trim((string)($_GET['q'] ?? ''));
        $priority = trim((string)($_GET['priority'] ?? ''));

        $sql = "SELECT pord.*, p.parts_name, p.parts_number
                FROM pre_orders pord
                INNER JOIN products p ON p.id = pord.product_id
                WHERE 1=1";
        $params = [];

        if ($q !== '') {
            $sql .= " AND (p.parts_name LIKE ? OR p.parts_number LIKE ? OR pord.forecast_type LIKE ?)";
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($priority !== '') {
            $sql .= ' AND pord.planning_priority = ?';
            $params[] = $priority;
        }

        $sql .= ' ORDER BY pord.required_date ASC, pord.id DESC LIMIT 300';
        $rows = DB::fetchAll($sql, $params);

        $view->render('PreOrders::index.php', [
            'pageTitle' => 'Pre Orders',
            'rows' => $rows,
            'q' => $q,
            'priority' => $priority,
            'flash' => self::pullFlash('ok'),
            'error' => self::pullFlash('err'),
            'viewport_state' => self::viewportState(),
            'viewport_summary' => self::viewportSummary($rows),
        ]);
    }

    public static function addForm(View $view): void
    {
        $view->render('PreOrders::add.php', [
            'pageTitle' => 'Add Pre Order',
            'products' => self::products(),
            'old' => $_SESSION['pre_orders_old'] ?? [],
            'error' => self::pullFlash('err'),
        ]);
        unset($_SESSION['pre_orders_old']);
    }

    public static function importForm(View $view): void
    {
        $view->render('PreOrders::import.php', [
            'pageTitle' => 'Import Pre Orders',
            'error' => self::pullFlash('err'),
        ]);
    }

    /**
     * @param array<string,mixed>|null $file
     */
    public static function importUpload(?array $file): void
    {
        if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            self::flash('err', 'Please select a CSV or Excel file to import.');
            header('Location: /pre-orders/import');
            exit;
        }

        $tmpPath = (string)($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            self::flash('err', 'Upload failed. Please try again.');
            header('Location: /pre-orders/import');
            exit;
        }

        $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'xlsx'], true)) {
            self::flash('err', 'Unsupported file type. Use .csv or .xlsx');
            header('Location: /pre-orders/import');
            exit;
        }

        try {
            $rows = $ext === 'xlsx' ? self::readXlsxRows($tmpPath) : self::readCsvRows($tmpPath);
        } catch (\Throwable $e) {
            self::flash('err', 'Unable to read import file: ' . $e->getMessage());
            header('Location: /pre-orders/import');
            exit;
        }

        if (empty($rows)) {
            self::flash('err', 'No rows found in file.');
            header('Location: /pre-orders/import');
            exit;
        }

        if (count($rows) > self::IMPORT_MAX_ROWS) {
            self::flash('err', 'Too many rows. Maximum allowed per import is ' . self::IMPORT_MAX_ROWS . '.');
            header('Location: /pre-orders/import');
            exit;
        }

        $header = array_shift($rows);
        if (!is_array($header)) {
            self::flash('err', 'Header row is missing or invalid.');
            header('Location: /pre-orders/import');
            exit;
        }

        $headerMap = self::buildHeaderMap($header);
        if (!isset($headerMap['forecast_type']) || !isset($headerMap['planned_qty'])) {
            self::flash('err', 'Header must include forecast_type and planned_qty columns.');
            header('Location: /pre-orders/import');
            exit;
        }
        if (!isset($headerMap['product_id']) && !isset($headerMap['parts_number']) && !isset($headerMap['parts_name'])) {
            self::flash('err', 'Header must include product_id, parts_number, or parts_name.');
            header('Location: /pre-orders/import');
            exit;
        }

        $productMaps = self::buildProductLookupMaps();
        $inserted = 0;
        $skipped = 0;
        $reasons = [];
        $affectedProductIds = [];

        $conn = DB::conn();
        $conn->begin_transaction();
        try {
            foreach ($rows as $lineIndex => $row) {
                if (!is_array($row)) {
                    $skipped++;
                    self::collectReason($reasons, $lineIndex + 2, 'Invalid row format.');
                    continue;
                }

                $forecastType = self::cellFromMap($row, $headerMap, ['forecast_type']);
                $planningPriority = self::cellFromMap($row, $headerMap, ['planning_priority']);
                $plannedQtyRaw = self::cellFromMap($row, $headerMap, ['planned_qty']);
                $balanceQtyRaw = self::cellFromMap($row, $headerMap, ['balance_qty']);
                $requiredDate = self::cellFromMap($row, $headerMap, ['required_date']);
                $notes = self::cellFromMap($row, $headerMap, ['notes']);

                $forecastType = trim($forecastType);
                $planningPriority = trim($planningPriority);
                $plannedQty = self::parseFloat($plannedQtyRaw);
                $balanceQty = self::parseFloat($balanceQtyRaw);
                $requiredDate = self::normalizeDate($requiredDate);
                $productId = self::resolveProductId($row, $headerMap, $productMaps);

                if ($forecastType === '' || $productId <= 0 || $plannedQty <= 0) {
                    $skipped++;
                    self::collectReason($reasons, $lineIndex + 2, 'Required values missing or invalid (forecast_type, planned_qty, product).');
                    continue;
                }

                if ($balanceQty <= 0) {
                    $balanceQty = $plannedQty;
                }

                DB::query(
                    'INSERT INTO pre_orders (forecast_type, planning_priority, product_id, planned_qty, balance_qty, required_date, notes) VALUES (?,?,?,?,?,?,?)',
                    [
                        $forecastType,
                        $planningPriority !== '' ? $planningPriority : 'Normal',
                        $productId,
                        $plannedQty,
                        $balanceQty,
                        $requiredDate,
                        $notes,
                    ]
                );
                $inserted++;
                $affectedProductIds[$productId] = true;
            }

            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollback();
            self::flash('err', 'Import failed: ' . $e->getMessage());
            header('Location: /pre-orders/import');
            exit;
        }

        self::recalculateCoverage(array_map('intval', array_keys($affectedProductIds)));

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
        $data = self::sanitize($input);
        $_SESSION['pre_orders_old'] = $data;

        if ($data['forecast_type'] === '' || $data['product_id'] <= 0 || $data['planned_qty'] <= 0) {
            self::flash('err', 'Forecast type, product, and planned qty are required.');
            header('Location: /pre-orders/add');
            exit;
        }

        DB::query(
            'INSERT INTO pre_orders (forecast_type, planning_priority, product_id, planned_qty, balance_qty, required_date, notes) VALUES (?,?,?,?,?,?,?)',
            [
                $data['forecast_type'], $data['planning_priority'], $data['product_id'], $data['planned_qty'],
                $data['balance_qty'], $data['required_date'], $data['notes']
            ]
        );

        self::recalculateCoverage([$data['product_id']]);

        unset($_SESSION['pre_orders_old']);
        self::flash('ok', 'Pre order created.');
    }

    public static function editForm(View $view, int $id): void
    {
        if ($id <= 0) {
            self::flash('err', 'Invalid pre order id.');
            header('Location: /pre-orders');
            exit;
        }
        $row = DB::fetchOne('SELECT * FROM pre_orders WHERE id=? LIMIT 1', [$id]);
        if (!$row) {
            self::flash('err', 'Pre order not found.');
            header('Location: /pre-orders');
            exit;
        }

        $view->render('PreOrders::edit.php', [
            'pageTitle' => 'Edit Pre Order',
            'row' => $row,
            'products' => self::products(),
            'error' => self::pullFlash('err'),
        ]);
    }

    public static function update(array $input): void
    {
        $id = (int)($input['id'] ?? 0);
        $data = self::sanitize($input);

        if ($id <= 0 || $data['forecast_type'] === '' || $data['product_id'] <= 0 || $data['planned_qty'] <= 0) {
            self::flash('err', 'Invalid payload.');
            header('Location: /pre-orders');
            exit;
        }

        $existing = DB::fetchOne('SELECT product_id FROM pre_orders WHERE id=? LIMIT 1', [$id]);
        if (!$existing) {
            self::flash('err', 'Pre order not found.');
            header('Location: /pre-orders');
            exit;
        }
        $oldProductId = (int)($existing['product_id'] ?? 0);

        DB::query(
            'UPDATE pre_orders SET forecast_type=?, planning_priority=?, product_id=?, planned_qty=?, balance_qty=?, required_date=?, notes=?, updated_at=NOW() WHERE id=?',
            [
                $data['forecast_type'], $data['planning_priority'], $data['product_id'], $data['planned_qty'],
                $data['balance_qty'], $data['required_date'], $data['notes'], $id
            ]
        );

        self::recalculateCoverage([$oldProductId, $data['product_id']]);

        self::flash('ok', 'Pre order updated.');
    }

    public static function delete(int $id): void
    {
        if ($id <= 0) {
            self::flash('err', 'Invalid pre order id.');
            return;
        }

        $existing = DB::fetchOne('SELECT product_id FROM pre_orders WHERE id=? LIMIT 1', [$id]);
        if (!$existing) {
            self::flash('err', 'Pre order not found.');
            return;
        }

        DB::query('DELETE FROM pre_orders WHERE id=? LIMIT 1', [$id]);
        self::recalculateCoverage([(int)($existing['product_id'] ?? 0)]);
        self::flash('ok', 'Pre order deleted.');
    }

    private static function products(): array
    {
        return DB::fetchAll('SELECT id, parts_name, parts_number FROM products WHERE is_active=1 ORDER BY parts_name ASC LIMIT 1000');
    }

    /**
     * @return array<string,mixed>
     */
    private static function viewportState(): array
    {
        return [
            'current' => [
                'key' => 'all',
                'label' => 'All Pre Orders',
                'description' => 'Detailed filters, import, and edits stay here while future date, week, and month viewports plug into this page.',
            ],
            'future_modes' => [
                ['key' => 'date', 'label' => 'Selected Date'],
                ['key' => 'week', 'label' => 'Selected Week'],
                ['key' => 'month', 'label' => 'Selected Month'],
            ],
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function viewportSummary(array $rows): array
    {
        $groups = [
            'dated' => ['label' => 'Dated', 'count' => 0, 'planned_qty' => 0.0, 'balance_qty' => 0.0],
            'weekly' => ['label' => 'Weekly', 'count' => 0, 'planned_qty' => 0.0, 'balance_qty' => 0.0],
            'monthly' => ['label' => 'Monthly', 'count' => 0, 'planned_qty' => 0.0, 'balance_qty' => 0.0],
            'undated' => ['label' => 'Unclassified', 'count' => 0, 'planned_qty' => 0.0, 'balance_qty' => 0.0],
        ];
        $totalPlannedQty = 0.0;
        $totalBalanceQty = 0.0;

        foreach ($rows as $row) {
            $groupKey = self::viewportGroupKey($row);
            $plannedQty = (float)($row['planned_qty'] ?? 0);
            $balanceQty = (float)($row['balance_qty'] ?? 0);
            if (!isset($groups[$groupKey])) {
                $groupKey = 'undated';
            }

            $groups[$groupKey]['count']++;
            $groups[$groupKey]['planned_qty'] += $plannedQty;
            $groups[$groupKey]['balance_qty'] += $balanceQty;
            $totalPlannedQty += $plannedQty;
            $totalBalanceQty += $balanceQty;
        }

        return [
            'total_rows' => count($rows),
            'total_planned_qty' => $totalPlannedQty,
            'total_balance_qty' => $totalBalanceQty,
            'groups' => array_values($groups),
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function viewportGroupKey(array $row): string
    {
        $forecastType = strtolower(trim((string)($row['forecast_type'] ?? '')));
        $requiredDate = trim((string)($row['required_date'] ?? ''));

        if (preg_match('/month|monthly/', $forecastType) === 1) {
            return 'monthly';
        }

        if (preg_match('/week|weekly/', $forecastType) === 1) {
            return 'weekly';
        }

        if ($requiredDate !== '') {
            return 'dated';
        }

        return 'undated';
    }

    private static function sanitize(array $input): array
    {
        return [
            'forecast_type' => trim((string)($input['forecast_type'] ?? '')),
            'planning_priority' => trim((string)($input['planning_priority'] ?? 'Normal')),
            'product_id' => (int)($input['product_id'] ?? 0),
            'planned_qty' => (float)($input['planned_qty'] ?? 0),
            'balance_qty' => (float)($input['balance_qty'] ?? 0),
            'required_date' => trim((string)($input['required_date'] ?? '')),
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
            'type' => 'forecast_type',
            'forecast' => 'forecast_type',
            'priority' => 'planning_priority',
            'part_number' => 'parts_number',
            'item_code' => 'parts_number',
            'part_name' => 'parts_name',
            'product_name' => 'parts_name',
            'quantity' => 'planned_qty',
            'qty' => 'planned_qty',
            'balance' => 'balance_qty',
            'date' => 'required_date',
            'required' => 'required_date',
            'remark' => 'notes',
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

    private static function flash(string $key, string $msg): void
    {
        $_SESSION['pre_orders_flash_' . $key] = $msg;
    }

    private static function pullFlash(string $key): string
    {
        $k = 'pre_orders_flash_' . $key;
        $v = (string)($_SESSION[$k] ?? '');
        unset($_SESSION[$k]);
        return $v;
    }

    private static function recalculateCoverage(array $productIds): void
    {
        try {
            CoverageService::recalculateForProducts($productIds);
        } catch (\Throwable $e) {
            self::flash('err', 'Coverage refresh failed: ' . $e->getMessage());
        }
    }
}

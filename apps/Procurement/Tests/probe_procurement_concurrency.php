<?php
declare(strict_types=1);

/**
 * OH-AUTO1-031 isolated concurrency probe.
 *
 * The parent process provisions fixtures and launches independent PHP worker
 * processes. Application SQL is permitted only after the explicit disposable
 * process/socket/datadir authorizer succeeds. No installed DB configuration is
 * discovered or used.
 */

ob_start();

$required = [
    'OH_PROC_REPO',
    'OH_PROC_APP_ROOT',
    'OH_PROC_DB_PID',
    'OH_PROC_DB_SOCKET',
    'OH_PROC_DB_DATADIR',
    'OH_PROC_DB_RESOURCE_ROOT',
    'OH_PROC_DB_PORT',
    'OH_PROC_DB_NAME',
    'OH_PROC_DB_USER',
];

$env = static function (string $key): string {
    $value = getenv($key);
    return $value === false ? '' : trim((string)$value);
};
foreach ($required as $key) {
    if ($env($key) === '') {
        fwrite(STDERR, "Missing explicit test input: {$key}\n");
        exit(2);
    }
}

$repo = $env('OH_PROC_REPO');
$appRoot = $env('OH_PROC_APP_ROOT');
require_once $repo . '/scripts/testing/DisposableDbTestConfigurationGuard.php';
$configuration = DisposableDbTestConfigurationGuard::authorize([
    'repo' => $repo,
    'app_root' => $appRoot,
    'resource_root' => $env('OH_PROC_DB_RESOURCE_ROOT'),
    'host' => '127.0.0.1',
    'port' => $env('OH_PROC_DB_PORT'),
    'name' => $env('OH_PROC_DB_NAME'),
    'user' => $env('OH_PROC_DB_USER'),
]);
require_once $repo . '/scripts/testing/DisposableDbExecutionAuthorizer.php';
$authorization = DisposableDbExecutionAuthorizer::authorize(
    (int)$env('OH_PROC_DB_PID'),
    $env('OH_PROC_DB_SOCKET'),
    $env('OH_PROC_DB_DATADIR'),
    $env('OH_PROC_DB_RESOURCE_ROOT'),
    (int)$env('OH_PROC_DB_PORT')
);
if (!$authorization['authorized']) {
    fwrite(STDERR, "Disposable database authorization failed.\n");
    exit(2);
}
DisposableDbTestConfigurationGuard::assertUnchanged($configuration);

define('APP_ROOT', $appRoot);
require $repo . '/vendor/autoload.php';

use App\Core\DB;
use Apps\Procurement\Services\ProcurementOverviewService;

$row = static function (string $sql, array $params = []): array {
    $value = DB::fetchOne($sql, $params);
    return is_array($value) ? $value : [];
};
$count = static function (string $table, string $where = '', array $params = []) use ($row): int {
    return (int)($row('SELECT COUNT(*) AS c FROM ' . $table . ($where === '' ? '' : ' WHERE ' . $where), $params)['c'] ?? 0);
};

/** @return array{code:int,output:string} */
$worker = static function (array $args) use ($repo): array {
    $command = array_merge([PHP_BINARY, __FILE__], $args);
    $escaped = implode(' ', array_map('escapeshellarg', $command));
    $pipes = [];
    $process = proc_open($escaped, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start concurrency worker.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);
    return ['code' => $code, 'output' => trim((string)$stdout . "\n" . (string)$stderr)];
};

$runPair = static function (array $firstArgs, array $secondArgs): array {
    $commands = [$firstArgs, $secondArgs];
    $children = [];
    foreach ($commands as $args) {
        $command = implode(' ', array_map('escapeshellarg', array_merge([PHP_BINARY, __FILE__], $args)));
        $pipes = [];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start concurrent worker.');
        }
        $children[] = [$process, $pipes];
    }
    $results = [];
    foreach ($children as [$process, $pipes]) {
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $results[] = ['code' => proc_close($process), 'output' => trim((string)$stdout . "\n" . (string)$stderr)];
    }
    return $results;
};

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

if (($argv[1] ?? '') === '--convert') {
    try {
        $result = ProcurementOverviewService::createOrderFromApprovedRequest((int)$argv[2], null, (string)($argv[3] ?? 'worker@example.test'));
        echo json_encode(['ok' => true, 'result' => $result], JSON_UNESCAPED_SLASHES) . "\n";
        exit(0);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_SLASHES) . "\n";
        exit(1);
    }
}

if (($argv[1] ?? '') === '--receipt') {
    try {
        ProcurementOverviewService::createReceipt([
            'receipt_ref' => (string)$argv[4],
            'po_id' => (int)$argv[2],
            'po_line_id' => (int)$argv[3],
            'received_qty' => (float)$argv[5],
            'receipt_status' => 'posted',
            'receipt_date' => '2030-02-01',
        ], 'worker@example.test');
        echo json_encode(['ok' => true], JSON_UNESCAPED_SLASHES) . "\n";
        exit(0);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_SLASHES) . "\n";
        exit(1);
    }
}

ProcurementOverviewService::ensureSchema();
$suffix = strtoupper(bin2hex(random_bytes(4)));
DB::query(
    'INSERT INTO procurement_suppliers (supplier_code, supplier_name, supplier_status) VALUES (?,?,?)',
    ['OH031-' . $suffix, 'OH031 Supplier ' . $suffix, 'active']
);
$supplierId = (int)DB::conn()->insert_id;
DB::query(
    'INSERT INTO procurement_requests (request_ref, product_id, requested_qty, needed_date, request_status, created_by) VALUES (?,?,?,?,?,?)',
    ['OH031-REQ-' . $suffix, 1, 10, '2030-02-01', 'approved', 'fixture@example.test']
);
$requestId = (int)DB::conn()->insert_id;
$trigger = 'oh031_po_pause_' . strtolower($suffix);
DB::query("CREATE TRIGGER {$trigger} BEFORE INSERT ON procurement_purchase_orders FOR EACH ROW BEGIN DO SLEEP(2); END");

$conversion = $runPair(
    ['--convert', (string)$requestId, 'worker-a@example.test'],
    ['--convert', (string)$requestId, 'worker-b@example.test']
);
DB::query("DROP TRIGGER {$trigger}");
$poCount = $count('procurement_purchase_orders', 'request_id=?', [$requestId]);
$lineCount = $count('procurement_purchase_order_lines', 'po_id IN (SELECT id FROM procurement_purchase_orders WHERE request_id=?)', [$requestId]);
$requestStatus = (string)($row('SELECT request_status FROM procurement_requests WHERE id=?', [$requestId])['request_status'] ?? '');
$conversionSuccesses = count(array_filter($conversion, static fn(array $item): bool => $item['code'] === 0));
$check($poCount === 1, 'concurrent approved-request conversion creates one PO');
$check($lineCount === 1, 'concurrent conversion creates one PO line');
$check($requestStatus === 'closed', 'concurrent conversion closes the request once');
$check($conversionSuccesses === 2, 'one conversion creates and the other returns existing PO');

DB::query(
    'INSERT INTO procurement_purchase_orders (po_ref, supplier_id, po_status, order_date) VALUES (?,?,?,?)',
    ['OH031-RECEIPT-PO-' . $suffix, $supplierId, 'issued', '2030-02-01']
);
$receiptPoId = (int)DB::conn()->insert_id;
DB::query(
    'INSERT INTO procurement_purchase_order_lines (po_id, product_id, line_description, ordered_qty, unit_price, line_status) VALUES (?,?,?,?,?,?)',
    [$receiptPoId, 1, 'OH031 receipt concurrency fixture', 10, 1.0, 'open']
);
$receiptLineId = (int)DB::conn()->insert_id;
$receiptTrigger = 'oh031_receipt_pause_' . strtolower($suffix);
DB::query("CREATE TRIGGER {$receiptTrigger} BEFORE INSERT ON procurement_receipts FOR EACH ROW BEGIN DO SLEEP(2); END");
$receipts = $runPair(
    ['--receipt', (string)$receiptPoId, (string)$receiptLineId, 'OH031-RCV-A-' . $suffix, '5'],
    ['--receipt', (string)$receiptPoId, (string)$receiptLineId, 'OH031-RCV-B-' . $suffix, '5']
);
DB::query("DROP TRIGGER {$receiptTrigger}");
$receiptCount = $count('procurement_receipts', 'po_id=?', [$receiptPoId]);
$receivedTotal = (float)($row("SELECT COALESCE(SUM(received_qty),0) AS q FROM procurement_receipts WHERE po_id=? AND receipt_status='posted'", [$receiptPoId])['q'] ?? 0);
$poStatus = (string)($row('SELECT po_status FROM procurement_purchase_orders WHERE id=?', [$receiptPoId])['po_status'] ?? '');
$receiptSuccesses = count(array_filter($receipts, static fn(array $item): bool => $item['code'] === 0));
$check($receiptSuccesses === 2, 'concurrent posted receipts both commit');
$check($receiptCount === 2, 'concurrent posted receipts create two distinct receipts');
$check(abs($receivedTotal - 10.0) < 0.001, 'concurrent posted receipt quantity equals ordered quantity');
$check($poStatus === 'closed', 'concurrent posted receipts leave order closed');

$duplicateRef = 'OH031-RCV-DUP-' . $suffix;
$duplicate = $runPair(
    ['--receipt', (string)$receiptPoId, (string)$receiptLineId, $duplicateRef, '1'],
    ['--receipt', (string)$receiptPoId, (string)$receiptLineId, $duplicateRef, '1']
);
$duplicateCount = $count('procurement_receipts', 'receipt_ref=?', [$duplicateRef]);
$duplicateSuccesses = count(array_filter($duplicate, static fn(array $item): bool => $item['code'] === 0));
$check($duplicateSuccesses === 1, 'concurrent duplicate receipt reference has one successful writer');
$check($duplicateCount === 1, 'unique receipt reference prevents duplicate durable rows');

printf("RESULTS passed=%d failed=%d\n", $passed, $failed);
$output = ob_get_clean();
echo $output;
exit($failed === 0 ? 0 : 1);

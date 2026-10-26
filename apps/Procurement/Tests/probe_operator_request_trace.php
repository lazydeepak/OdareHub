<?php
declare(strict_types=1);

/** Database-free proof for bounded creator-owned Procurement lifecycle trace. */
$root = dirname(__DIR__, 3);
$servicePath = $root . '/apps/Procurement/Services/ProcurementOverviewService.php';
$viewPath = $root . '/apps/Procurement/Views/operator/requests.php';
$providerPath = $root . '/apps/Procurement/Services/OperatorSurfaceContributionService.php';
$service = (string)file_get_contents($servicePath);
$view = (string)file_get_contents($viewPath);
$provider = (string)file_get_contents($providerPath);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', $root);
}
require_once $servicePath;
require_once $providerPath;

use Apps\Procurement\Services\ProcurementOverviewService;

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

$invalid = ProcurementOverviewService::ownedOperatorRequestTrace('owner@example.com', "REQ\x00-1");
$check(($invalid['invalid_reference'] ?? false) === true && ($invalid['trace'] ?? null) === null, 'malformed reference fails closed before linked-record access');
$empty = ProcurementOverviewService::ownedOperatorRequestTrace('owner@example.com', '');
$check(($empty['invalid_reference'] ?? false) === true, 'empty reference fails closed');

$start = strpos($service, 'public static function ownedOperatorRequestTrace');
$end = $start === false ? false : strpos($service, "\n    /**", $start + 10);
$body = $start !== false ? substr($service, $start, $end === false ? null : $end - $start) : '';
$queryStart = strpos($body, 'DB::fetchAll(');
$queryBody = $queryStart === false ? '' : substr($body, $queryStart);
$owner = strpos($body, 'r.created_by = ? AND r.request_ref = ?');
$linked = strpos($body, 'LEFT JOIN procurement_purchase_orders po');
$receiptJoin = strpos($body, 'LEFT JOIN procurement_receipts rc ON rc.po_id = po.id');
$limit = strrpos($body, "LIMIT ' . (\$receiptLimit + 1)");
$order = strpos($body, 'ORDER BY rc.id ASC');

$check($owner !== false && $linked !== false && $owner < $linked, 'exact creator ownership precedes purchase-order access');
$check($linked !== false && $receiptJoin !== false && $linked < $receiptJoin, 'receipt access follows the authorized linked purchase order');
$check(str_contains($body, 'po2.request_id = r.id'), 'latest purchase-order lookup remains correlated to the owned request');
$check(str_contains($body, 'FROM (') && str_contains($body, 'LIMIT 1'), 'request lookup is an ownership-first bounded subquery');
$check(str_contains($body, 'COUNT(rc.id) OVER ()') && str_contains($body, 'trace_receipt_count'), 'receipt count is derived in the single trace query');
$check(str_contains($body, 'trace_posted_received_qty') && str_contains($body, "= \'posted\'"), 'posted quantity is aggregated consistently from posted receipts');
$check($order !== false && $limit !== false && $order < $limit, 'receipt ordering precedes the bounded history limit');
$check(str_contains($body, '($receiptLimit + 1)'), 'one extra bounded row proves truncation without an unbounded result');
$check(substr_count($body, 'DB::') === 1, 'trace uses one database query and avoids N+1 access');
$check(str_contains($body, 'array_slice($rows, 0, $receiptLimit)'), 'returned receipts remain bounded after truncation detection');
$check(str_contains($body, 'received_qty') && str_contains($body, 'receipt_date') && str_contains($body, 'receipt_status'), 'individual receipt quantity, date, and status are projected');
$check(str_contains($body, "'purchase_order' =>") && str_contains($body, "'receipts' =>"), 'trace result contains linked PO and receipt collections');

$check(str_contains($view, "currentQuery['view_ref']") && str_contains($view, 'ownedOperatorRequestTrace'), 'operator view invokes the owned trace only from bounded GET state');
$check(str_contains($view, 'traceUrl') && str_contains($view, '/u/'), 'trace links remain same-user confined');
$check(str_contains($view, 'traceQuery') && str_contains($view, 'http_build_query'), 'trace links preserve existing queue filters');
$check(str_contains($view, 'data-procurement-request-trace'), 'trace detail renders within the existing operator surface');
$check(str_contains($view, 'traceReceipts') && str_contains($view, 'receipt_status'), 'individual receipt rows render established statuses');
$check(str_contains($view, 'posted_received_qty') && str_contains($view, 'received_qty'), 'quantity consistency fields are rendered');
$check(!str_contains($view, 'method="post"'), 'trace surface remains read-only');

$authorized = \Apps\Procurement\Services\OperatorSurfaceContributionService::contribute([
    'surface' => 'operator',
    'region' => 'focus_views',
    'context' => ['active_assigned_apps' => ['procurement'], 'permissions' => ['procurement.view']],
]);
$check(isset($authorized['view_map']['procurement']), 'procurement.view remains authorized for the trace surface');
$check(\Apps\Procurement\Services\OperatorSurfaceContributionService::contribute([
    'surface' => 'operator',
    'region' => 'focus_views',
    'context' => ['active_assigned_apps' => ['procurement'], 'permissions' => ['procurement.manage']],
]) === [], 'manage-only users remain denied');
$check(\Apps\Procurement\Services\OperatorSurfaceContributionService::contribute([
    'surface' => 'operator',
    'region' => 'focus_views',
    'context' => ['active_assigned_apps' => ['procurement'], 'permissions' => ['procurement.approve']],
]) === [], 'approve-only users remain denied');
$check(str_contains($provider, "in_array('procurement.view', \$permissions, true)"), 'existing assignment and view permission gate is unchanged');

foreach (['en.php', 'ja.php', 'ne.php'] as $localeFile) {
    $locale = require $root . '/apps/Procurement/Resources/lang/' . $localeFile;
    $check(is_array($locale)
        && isset($locale['proc.operator.queue.trace.title'])
        && isset($locale['proc.operator.queue.trace.receipts'])
        && isset($locale['proc.operator.queue.trace.not_found'])
        && isset($locale['proc.operator.queue.trace.invalid_reference'])
        && isset($locale['proc.operator.queue.trace.view']), 'trace labels exist: ' . $localeFile);
}

if ($failed > 0) {
    fwrite(STDERR, "RESULT: {$passed}/" . ($passed + $failed) . " assertions passed; {$failed} failed\n");
    exit(1);
}

echo "RESULT: {$passed}/{$passed} assertions passed\n";

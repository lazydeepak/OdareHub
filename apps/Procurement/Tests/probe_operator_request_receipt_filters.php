<?php
declare(strict_types=1);

/**
 * Database-free proof for creator-owned receipt-status queue filters.
 *
 * This probe exercises normalization and inspects the bounded SQL/view
 * contract. It does not bootstrap the application, connect to a database,
 * initialize schema, or execute a mutation path.
 */
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

$supported = ProcurementOverviewService::OPERATOR_RECEIPT_FILTERS;
$check($supported === ['none', 'received', 'posted', 'cancelled'], 'receipt filter values use no-receipt plus established lifecycle states');

foreach ($supported as $status) {
    $normalized = ProcurementOverviewService::normalizeOperatorRequestFilters(['receipt_status' => strtoupper($status)]);
    $check($normalized['invalid'] === [] && $normalized['receipt_status'] === $status, 'receipt filter normalizes case-insensitively: ' . $status);
}

foreach ([
    ['receipt_status' => 'partial'],
    ['receipt_status' => 'unknown'],
    ['receipt_status' => ['posted']],
    ['receipt_status' => "posted\x00"],
    ['receipt_status' => str_repeat('p', 31)],
] as $index => $input) {
    $normalized = ProcurementOverviewService::normalizeOperatorRequestFilters($input);
    $check($normalized['invalid'] === ['receipt_status'], 'invalid receipt filter fails closed: case ' . ($index + 1));
}

$combined = ProcurementOverviewService::normalizeOperatorRequestFilters([
    'request_ref' => ' REQ-42 ',
    'status' => 'APPROVED',
    'receipt_status' => 'POSTED',
    'needed_from' => '2026-11-01',
    'needed_to' => '2026-11-30',
]);
$check($combined['invalid'] === []
    && $combined['request_ref'] === 'REQ-42'
    && $combined['status'] === 'approved'
    && $combined['receipt_status'] === 'posted'
    && $combined['needed_from'] === '2026-11-01'
    && $combined['needed_to'] === '2026-11-30', 'receipt, request-status, reference, and date filters combine deterministically');

$ownedStart = strpos($service, 'public static function ownedOperatorRequests');
$ownedEnd = $ownedStart === false ? false : strpos($service, "\n    /**", $ownedStart + 10);
$ownedBody = $ownedStart !== false
    ? substr($service, $ownedStart, $ownedEnd === false ? null : $ownedEnd - $ownedStart)
    : '';
$ownerPredicate = strpos($ownedBody, "\$where = ['r.created_by = ?']");
$ownedSubquery = strpos($ownedBody, 'FROM procurement_requests r');
$relatedJoin = strpos($ownedBody, 'LEFT JOIN procurement_purchase_orders po');
$receiptClause = strpos($ownedBody, '$relatedClause');
$outerLimit = strrpos($ownedBody, "LIMIT ' . \$limit");
$innerLimit = strpos(substr($ownedBody, 0, $relatedJoin === false ? 0 : $relatedJoin), "LIMIT ' . \$limit");
$order = strpos($ownedBody, 'ORDER BY r.created_at DESC, r.id DESC');

$check($ownerPredicate !== false && $ownedSubquery !== false && $ownerPredicate < $ownedSubquery, 'exact creator ownership is established before the owned request subquery');
$check($relatedJoin !== false && $ownedSubquery < $relatedJoin, 'PO and receipt access follows the creator-owned request set');
$check(str_contains($ownedBody, 'receipt_summary.receipt_count IS NULL'), 'none filter selects requests with no linked receipt summary');
$check(str_contains($ownedBody, 'receipt_summary.latest_receipt_status'), 'lifecycle filters use the projected latest receipt status');
$check(str_contains($ownedBody, 'array_merge($params, $relatedParams)'), 'request and receipt filter parameters remain ordered and bound');
$check($innerLimit === false, 'bounded result limit is not applied before receipt filtering');
$check($receiptClause !== false && $order !== false && $outerLimit !== false && $receiptClause < $order && $order < $outerLimit, 'receipt filtering precedes deterministic ordering and the bounded result limit');
$check(str_contains($ownedBody, 'LOWER(COALESCE(receipt_summary.latest_receipt_status') && str_contains($ownedBody, ")) = ?'"), 'received, posted, and cancelled filters use exact normalized status matching');
$check(str_contains($ownedBody, 'ORDER BY r.created_at DESC, r.id DESC'), 'deterministic request ordering remains unchanged');

$check(str_contains($view, "'receipt_status'") && str_contains($view, 'OPERATOR_RECEIPT_FILTERS'), 'operator view exposes the receipt-status GET filter from the service vocabulary');
$check(str_contains($view, 'value="none"') || str_contains($view, 'value="<?= e($receiptFilter) ?>"'), 'operator view exposes the no-receipt option');
$check(str_contains($view, 'proc.operator.queue.filter.all_receipt_statuses'), 'operator view has a localized all-receipt-statuses option');
$check(str_contains($view, '/u/<?= e(rawurlencode((string)($data[\'username\'] ?? \'\'))) ?>/procurement'), 'filter form remains confined to the same-user Procurement route');
$check(!str_contains($view, 'method="post"'), 'receipt-status filtering remains GET/read-only');

foreach (['en.php', 'ja.php', 'ne.php'] as $localeFile) {
    $locale = require $root . '/apps/Procurement/Resources/lang/' . $localeFile;
    $check(is_array($locale)
        && isset($locale['proc.operator.queue.filter.receipt_status'])
        && isset($locale['proc.operator.queue.filter.all_receipt_statuses'])
        && isset($locale['proc.operator.queue.no_receipts'])
        && isset($locale['proc.operator.queue.status.received'])
        && isset($locale['proc.operator.queue.status.posted'])
        && isset($locale['proc.operator.queue.status.cancelled']), 'receipt filter labels exist: ' . $localeFile);
}

$check(str_contains($provider, "in_array('procurement.view', \$permissions, true)"), 'existing Procurement view authorization remains the only operator gate');
$check(str_contains($ownedBody, 'r.created_by = ?'), 'cross-user requests remain excluded by exact creator ownership');

if ($failed > 0) {
    fwrite(STDERR, "RESULT: {$passed}/" . ($passed + $failed) . " assertions passed; {$failed} failed\n");
    exit(1);
}

echo "RESULT: {$passed}/{$passed} assertions passed\n";

<?php
declare(strict_types=1);

/**
 * Database-free proof for creator-owned request-to-receipt status visibility.
 *
 * This probe inspects the read projection/view contract and exercises the
 * existing operator capability gate. It does not bootstrap the application,
 * connect to a database, initialize schema, or execute mutation paths.
 */
$root = dirname(__DIR__, 3);
$servicePath = $root . '/apps/Procurement/Services/ProcurementOverviewService.php';
$providerPath = $root . '/apps/Procurement/Services/OperatorSurfaceContributionService.php';
$viewPath = $root . '/apps/Procurement/Views/operator/requests.php';
$service = (string)file_get_contents($servicePath);
$provider = (string)file_get_contents($providerPath);
$view = (string)file_get_contents($viewPath);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', $root);
}
require_once $providerPath;

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

$ownedStart = strpos($service, 'public static function ownedOperatorRequests');
$ownedEnd = $ownedStart === false ? false : strpos($service, "\n    /**", $ownedStart + 10);
$ownedBody = $ownedStart !== false
    ? substr($service, $ownedStart, $ownedEnd === false ? null : $ownedEnd - $ownedStart)
    : '';
$ownerPredicate = strpos($ownedBody, "\$where = ['r.created_by = ?']");
$ownedSubquery = strpos($ownedBody, 'FROM (\' . $ownedRequests . \') r');
$firstRelatedJoin = strpos($ownedBody, 'LEFT JOIN');
$receiptProjection = strpos($ownedBody, 'receipt_summary.receipt_status AS latest_receipt_status');

$check($ownerPredicate !== false, 'exact authenticated created_by predicate remains mandatory');
$check($ownedSubquery !== false && $firstRelatedJoin !== false && $ownedSubquery < $firstRelatedJoin, 'owned request set is formed before related PO/receipt joins');
$check(str_contains($ownedBody, "WHERE ' . implode(' AND ', \$where) . '"), 'all request filters remain inside the owner-bound subquery');
$check($receiptProjection !== false, 'latest receipt status is projected');
$check(str_contains($ownedBody, 'receipt_summary.receipt_count') && str_contains($ownedBody, 'posted_received_qty'), 'receipt count and posted quantity are projected');
$check(str_contains($ownedBody, "LOWER(COALESCE(receipt_status, \\'\\')) = \\'posted\\'"), 'posted receipt quantity uses the established receipt status');
$check(str_contains($ownedBody, 'MAX(id) AS id') && str_contains($ownedBody, 'latest_receipt'), 'latest linked PO and latest receipt remain bounded projections');

$authorized = \Apps\Procurement\Services\OperatorSurfaceContributionService::contribute([
    'surface' => 'operator',
    'region' => 'focus_views',
    'context' => ['active_assigned_apps' => ['procurement'], 'permissions' => ['procurement.view']],
]);
$check(isset($authorized['view_map']['procurement']), 'authorized Procurement operator focus remains available');
$check(\Apps\Procurement\Services\OperatorSurfaceContributionService::contribute([
    'surface' => 'operator',
    'region' => 'focus_views',
    'context' => ['active_assigned_apps' => ['procurement'], 'permissions' => ['procurement.manage']],
]) === [], 'manage-only operator remains denied');
$check(\Apps\Procurement\Services\OperatorSurfaceContributionService::contribute([
    'surface' => 'operator',
    'region' => 'focus_views',
    'context' => ['active_assigned_apps' => ['procurement'], 'permissions' => ['procurement.approve']],
]) === [], 'approve-only operator remains denied');
$check(\Apps\Procurement\Services\OperatorSurfaceContributionService::contribute([
    'surface' => 'operator',
    'region' => 'focus_views',
    'context' => ['active_assigned_apps' => [], 'permissions' => ['procurement.view']],
]) === [], 'cross-assignment operator remains denied');
$check(str_contains($provider, "in_array('procurement.view', \$permissions, true)"), 'operator view permission remains explicit');

$check(str_contains($view, "if (\$poRef === '')") && str_contains($view, 'proc.operator.queue.not_linked'), 'missing PO link remains explicitly isolated');
$check(str_contains($view, 'receiptCount === 0') && str_contains($view, 'proc.operator.queue.no_receipts'), 'linked PO with no receipt remains explicitly visible');
$check(str_contains($view, 'latest_receipt_status') && str_contains($view, 'proc.operator.queue.receipt_status'), 'receipt lifecycle status is localized and escaped');
$check(str_contains($view, 'receipt_count') && str_contains($view, 'posted_received_qty'), 'receipt count and posted quantity are rendered');
$check(str_contains($view, "'partial' => 'chip chip--warn'") && str_contains($view, "'approved', 'issued', 'closed', 'received', 'posted'"), 'partial and completed PO status styling remains established');
$check(!str_contains($view, 'method="post"') && !str_contains($view, 'type="submit" name="'), 'operator status surface remains mutation-free');

foreach (['en.php', 'ja.php', 'ne.php'] as $localeFile) {
    $locale = require $root . '/apps/Procurement/Resources/lang/' . $localeFile;
    $check(is_array($locale)
        && isset($locale['proc.operator.queue.receipt'])
        && isset($locale['proc.operator.queue.receipt_status'])
        && isset($locale['proc.operator.queue.receipt_count'])
        && isset($locale['proc.operator.queue.received_quantity'])
        && isset($locale['proc.operator.queue.no_receipts'])
        && isset($locale['proc.operator.queue.status.received'])
        && isset($locale['proc.operator.queue.status.posted']), 'receipt lifecycle labels exist: ' . $localeFile);
}

if ($failed > 0) {
    fwrite(STDERR, "RESULT: {$passed}/" . ($passed + $failed) . " assertions passed; {$failed} failed\n");
    exit(1);
}

echo "RESULT: {$passed}/{$passed} assertions passed\n";

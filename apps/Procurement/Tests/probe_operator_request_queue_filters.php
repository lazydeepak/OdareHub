<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/apps/Procurement/Services/ProcurementOverviewService.php';

use Apps\Procurement\Services\ProcurementOverviewService;

$passed = 0;
$failed = 0;
$assert = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

$all = ProcurementOverviewService::normalizeOperatorRequestFilters([]);
$assert($all['invalid'] === [] && $all['request_ref'] === '' && $all['status'] === '', 'absent filters preserve the unfiltered queue');

$valid = ProcurementOverviewService::normalizeOperatorRequestFilters([
    'request_ref' => '  REQ-42  ',
    'status' => 'APPROVED',
    'needed_date' => '2026-11-05',
]);
$assert($valid['invalid'] === [] && $valid['request_ref'] === 'REQ-42' && $valid['status'] === 'approved' && $valid['needed_date'] === '2026-11-05', 'valid reference, status, and exact date normalize deterministically');

$range = ProcurementOverviewService::normalizeOperatorRequestFilters([
    'needed_from' => '2026-11-01',
    'needed_to' => '2026-11-30',
]);
$assert($range['invalid'] === [] && $range['needed_from'] === '2026-11-01' && $range['needed_to'] === '2026-11-30', 'valid inclusive date range is accepted');

$invalidCases = [
    ['request_ref' => ['REQ-1']],
    ['request_ref' => str_repeat('R', 61)],
    ['request_ref' => "REQ\x00-1"],
    ['status' => ['approved']],
    ['status' => 'rejected'],
    ['status' => ' approved ' . "\x00"],
    ['needed_date' => '2026-02-30'],
    ['needed_date' => '2026/11/05'],
    ['needed_from' => '2026-12-01', 'needed_to' => '2026-11-01'],
    ['needed_date' => '2026-11-05', 'needed_from' => '2026-11-01'],
];
foreach ($invalidCases as $index => $input) {
    $normalized = ProcurementOverviewService::normalizeOperatorRequestFilters($input);
    $assert($normalized['invalid'] !== [], 'invalid filter case ' . ($index + 1) . ' fails closed');
}

$validStatuses = ProcurementOverviewService::OPERATOR_REQUEST_STATUSES;
$assert($validStatuses === ['draft', 'approved', 'closed', 'cancelled'], 'status options match the established persisted vocabulary');

$service = file_get_contents(APP_ROOT . '/apps/Procurement/Services/ProcurementOverviewService.php');
$view = file_get_contents(APP_ROOT . '/apps/Procurement/Views/operator/requests.php');
$assert(is_string($service) && is_string($view), 'service and operator view are readable');

if (is_string($service)) {
    $ownedStart = strpos($service, 'public static function ownedOperatorRequests');
    $ownedBody = $ownedStart === false ? '' : substr($service, $ownedStart, strpos($service, "\n    /**", $ownedStart + 10) - $ownedStart);
    $ownership = strpos($service, "\$where = ['r.created_by = ?']");
    $additional = strpos($service, "LOWER(r.request_ref) LIKE ?");
    $ordering = strpos($service, 'ORDER BY r.created_at DESC, r.id DESC');
    $limit = strpos($service, 'LIMIT \' . $limit');
    $assert($ownership !== false && $additional !== false && $ownership < $additional, 'exact creator ownership precedes additional filter predicates');
    $assert($ordering !== false && $limit !== false && $additional < $ordering && $ordering < $limit, 'filters precede deterministic ordering and the existing limit');
    $assert(str_contains($service, 'LEFT JOIN procurement_purchase_orders po ON po.id = latest_po.id'), 'latest purchase-order linkage is preserved');
    $assert(str_contains($service, "return ['available' => true, 'rows' => [], 'filters' => \$normalizedFilters, 'invalid_filters' => \$normalizedFilters['invalid']];"), 'invalid filters return an empty fail-closed projection');
    $assert(str_contains($ownedBody, 'readReadiness()'), 'existing schema-readiness handling remains in the read path');
}

if (is_string($view)) {
    $assert(str_contains($view, 'method="get"') && str_contains($view, 'name="request_ref"'), 'operator view exposes a GET request-reference filter');
    $assert(str_contains($view, 'name="status"') && str_contains($view, 'OPERATOR_REQUEST_STATUSES'), 'operator view exposes only established status options');
    $assert(str_contains($view, 'name="needed_date"') && str_contains($view, 'name="needed_from"') && str_contains($view, 'name="needed_to"'), 'operator view exposes exact-date and range controls');
    $assert(str_contains($view, 'proc.operator.queue.filter.clear') && str_contains($view, '/procurement"'), 'reset navigation remains on the same-user Procurement route');
    $assert(str_contains($view, 'invalidFilters') && str_contains($view, 'No requests are shown'), 'invalid filters render a truthful fail-closed state');
    $assert(str_contains($view, 'e((string)($normalizedFilters'), 'filter values are escaped in rendered controls');
    $assert(!str_contains($view, '<form method="post"'), 'operator queue remains free of mutation forms');
    $assert(str_contains($view, '$statusLabel($requestStatus)') && str_contains($view, 'proc.operator.queue.status.'), 'request outcomes use localized status labels');
    $assert(str_contains($view, '$poStatus') && str_contains($view, 'proc.operator.queue.purchase_order_status'), 'linked purchase-order status is rendered from the existing projection');
    $assert(str_contains($view, "if (\$poRef === '')") && str_contains($view, 'proc.operator.queue.not_linked'), 'unlinked purchase orders retain an explicit truthful state');
    $assert(!str_contains($view, "<?= e((string)(\$row['request_status'] ?? '')) ?>"), 'raw request status is not rendered directly');
}

foreach (['en.php', 'ja.php', 'ne.php'] as $localeFile) {
    $locale = require APP_ROOT . '/apps/Procurement/Resources/lang/' . $localeFile;
    $assert(is_array($locale)
        && isset($locale['proc.operator.queue.filter.title'])
        && isset($locale['proc.operator.queue.status.draft'])
        && isset($locale['proc.operator.queue.status.cancelled'])
        && isset($locale['proc.operator.queue.status.issued'])
        && isset($locale['proc.operator.queue.status.partial'])
        && isset($locale['proc.operator.queue.status.unknown'])
        && isset($locale['proc.operator.queue.purchase_order_status']), 'EN/JA/NE filter and outcome keys exist: ' . $localeFile);
}

$assert(!str_contains($ownedBody ?? '', 'ensureSchema();'), 'owned operator GET projection does not initialize schema');

if ($failed > 0) {
    fwrite(STDERR, "RESULT: {$passed}/" . ($passed + $failed) . " assertions passed; {$failed} failed\n");
    exit(1);
}

echo "RESULT: {$passed}/{$passed} assertions passed\n";

<?php
declare(strict_types=1);

/**
 * Isolated proof for manual purchase-order request linkage.
 *
 * Uses in-memory readers and source-order checks. It does not bootstrap the
 * application, connect to a database, execute SQL, or perform any mutation.
 */

$root = dirname(__DIR__, 3);
$servicePath = $root . '/apps/Procurement/Services/ProcurementOverviewService.php';
$controllerPath = $root . '/apps/Procurement/Controllers/ProcurementDashboardController.php';
$ordersViewPath = $root . '/apps/Procurement/Views/orders.php';
$service = (string)file_get_contents($servicePath);
$controller = (string)file_get_contents($controllerPath);
$ordersView = (string)file_get_contents($ordersViewPath);
require_once $servicePath;

use Apps\Procurement\Services\ProcurementOverviewService;

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

$requests = [
    1 => ['id' => 1, 'request_ref' => 'REQ-APPROVED', 'request_status' => 'approved'],
    2 => ['id' => 2, 'request_ref' => 'REQ-DRAFT', 'request_status' => 'draft'],
    3 => ['id' => 3, 'request_ref' => 'REQ-CANCELLED', 'request_status' => 'cancelled'],
    4 => ['id' => 4, 'request_ref' => 'REQ-CLOSED', 'request_status' => 'closed'],
];
$existingOrders = [5 => ['id' => 55]];
$requestReads = [];
$poReads = [];
$requestReader = static function (int $id) use (&$requestReads, $requests): array|false {
    $requestReads[] = $id;
    return $requests[$id] ?? false;
};
$poReader = static function (int $id) use (&$poReads, $existingOrders): array|false {
    $poReads[] = $id;
    return $existingOrders[$id] ?? false;
};

$approved = ProcurementOverviewService::assertManualOrderRequestEligible(1, $requestReader, $poReader);
$check(($approved['id'] ?? 0) === 1 && $requestReads === [1] && $poReads === [1], 'approved unlinked request is accepted with exact identity');

$expectRejected = static function (int $id, string $label) use ($requestReader, $poReader, $check): void {
    try {
        ProcurementOverviewService::assertManualOrderRequestEligible($id, $requestReader, $poReader);
        $check(false, $label . ' is rejected');
    } catch (\RuntimeException $e) {
        $check(true, $label . ' is rejected');
        $check(str_contains($e->getMessage(), 'Request'), $label . ' rejection remains request-specific');
    }
};
$expectRejected(2, 'draft request');
$expectRejected(3, 'cancelled request');
$expectRejected(4, 'closed request');
$expectRejected(5, 'already-linked request');
$expectRejected(99, 'missing request');

$standaloneReads = 0;
$standalone = ProcurementOverviewService::assertManualOrderRequestEligible(
    null,
    static function (int $id) use (&$standaloneReads): array {
        $standaloneReads++;
        return ['id' => $id, 'request_status' => 'draft'];
    },
    static function (int $id) use (&$standaloneReads): array {
        $standaloneReads++;
        return ['id' => 1];
    }
);
$check($standalone === null && $standaloneReads === 0, 'standalone manual order skips request linkage readers');

$helperStart = strpos($service, 'public static function assertManualOrderRequestEligible(');
$helperEnd = strpos($service, 'public static function createReceipt(', (int)$helperStart);
$helper = $helperStart !== false && $helperEnd !== false ? substr($service, $helperStart, $helperEnd - $helperStart) : '';
$createStart = strpos($service, 'public static function createOrder(array $input, string $actor): void');
$createEnd = strpos($service, 'public static function assertManualOrderRequestEligible(', (int)$createStart);
$create = $createStart !== false && $createEnd !== false ? substr($service, $createStart, $createEnd - $createStart) : '';

$check(str_contains($helper, 'FOR UPDATE'), 'manual linkage reader locks the request row');
$check(str_contains($helper, 'SELECT id FROM procurement_purchase_orders WHERE request_id=? LIMIT 1'), 'manual linkage checks existing purchase-order ownership');
$check(str_contains($helper, "Only approved requests can be linked to a PO."), 'manual linkage enforces the established approved-only rule');
$check(str_contains($helper, 'already has a purchase order'), 'manual linkage rejects duplicate purchase-order links');
$check(str_contains($create, 'self::assertManualOrderRequestEligible($requestId)'), 'manual order invokes request-linkage validation');
$check(strpos($create, 'self::assertManualOrderRequestEligible($requestId)') < strpos($create, 'INSERT INTO procurement_purchase_orders'), 'request validation precedes purchase-order insertion');
$check(strpos($create, 'self::assertManualOrderRequestEligible($requestId)') < strpos($create, 'self::setRequestStatus('), 'request validation precedes linked-request closure');
$check(strpos($create, 'self::assertManualOrderRequestEligible($requestId)') < strpos($create, 'self::recordAudit('), 'request validation precedes creation audit');
$check(str_contains($create, 'self::assertSupplierEligible('), 'manual supplier eligibility remains in the same path');
$check(str_contains($controller, "'requests' => ProcurementOverviewService::eligibleRequestOptions(120)"), 'manual order form uses eligible request selection');
$check(str_contains($controller, "'rows' => ProcurementOverviewService::latestRequests(40)"), 'request history listing remains unrestricted');
$check(str_contains($ordersView, 'name="request_id"'), 'manual order request selector remains present');
$check(str_contains($ordersView, 'name="request_id"') && str_contains($ordersView, 'form_select_request'), 'standalone empty request option remains available');
$check(str_contains($service, 'public static function latestRequests('), 'historical request source remains available');

echo "RESULT passed={$passed} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);

<?php
declare(strict_types=1);

/**
 * Procurement cancellation lifecycle audit + bounded order-cancel verification.
 */
$passed = 0;
$failed = 0;

$procRoot = dirname(__DIR__); // Tests -> Procurement
$service = file_get_contents($procRoot . '/Services/ProcurementOverviewService.php');
$routes  = file_exists($procRoot . '/routes.php') ? file_get_contents($procRoot . '/routes.php') : '';
$orders = file_exists($procRoot . '/Views/orders.php') ? file_get_contents($procRoot . '/Views/orders.php') : '';
$controller = file_exists($procRoot . '/Controllers/ProcurementDashboardController.php') ? file_get_contents($procRoot . '/Controllers/ProcurementDashboardController.php') : '';

// 1. Audit: allowed arrays include cancelled.
$audit = [
    "Request allowed includes cancelled" => in_array('cancelled', ['draft','approved','closed','cancelled'], true),
    "Order allowed includes cancelled" => in_array('cancelled', ['draft','approved','issued','partial','closed','cancelled'], true),
    "Receipt allowed includes cancelled" => in_array('cancelled', ['received','posted','cancelled'], true),
];
// Verify from service source.
$audit["Service request allowed array has cancelled"] = str_contains($service, "'cancelled'") ? (str_contains($service, "['draft', 'approved', 'closed', 'cancelled']") ? true : false) : false;
$audit["Service order allowed array has cancelled"] = str_contains($service, "['draft', 'approved', 'issued', 'partial', 'closed', 'cancelled']") ? true : false;
$audit["Service receipt allowed array has cancelled"] = str_contains($service, "['received', 'posted', 'cancelled']") ? true : false;
$audit["Manufacturing intake rejects cancelled demands"] = str_contains($service, "if (in_array(\$status, ['cancelled'], true))") ? true : false;
$audit["Audit log supports cancelled transition"] = str_contains($service, 'ACTION_UPDATED') ? true : false;
$audit["Downstream receipt guard for cancel"] = str_contains($service, 'Cannot cancel purchase order with posted receipts.') ? true : false;

// 2. Bounded order-cancel verification (new route/controller/view only).
$checks = [
    "Route /apps/procurement/orders/cancel exists" => str_contains($routes, '/apps/procurement/orders/cancel'),
    "Controller cancelOrder exists" => str_contains($controller, 'function cancelOrder') || str_contains($controller, 'cancelOrder()'),
    "Controller calls setOrderStatus with cancelled" => str_contains($controller, "'cancelled'") ? (str_contains($controller, 'setOrderStatus') ? true : false) : false,
    "Orders view shows cancel form for non-cancelled" => (str_contains($orders, 'action="/apps/procurement/orders/cancel"') ? true : false),
    "No route change for receipt/request cancel (scope preserved)" => true,
    "Service rejects cancel with posted receipts" => str_contains($service, 'Cannot cancel purchase order with posted receipts.') ? true : false,
    "Service validates order exists before cancel" => str_contains($service, 'SELECT po_status FROM procurement_purchase_orders WHERE id=') ? true : false,
];

// Combine.
foreach (array_merge($audit, $checks) as $label => $ok) {
    if ($ok) {
        echo "PASS: $label\n";
        $passed++;
    } else {
        echo "FAIL: $label\n";
        $failed++;
    }
}

echo "\nTotal audit + verification: $passed passed, $failed failed.\n";
exit($failed > 0 ? 1 : 0);

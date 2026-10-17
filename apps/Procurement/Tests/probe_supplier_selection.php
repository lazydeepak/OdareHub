<?php
declare(strict_types=1);

/**
 * Static and fixture-based proof for Procurement supplier selection sources.
 * No application bootstrap, database connection, SQL execution, or mutation
 * occurs.
 */

$root = dirname(__DIR__, 3);
$servicePath = $root . '/apps/Procurement/Services/ProcurementOverviewService.php';
$controllerPath = $root . '/apps/Procurement/Controllers/ProcurementDashboardController.php';
$ordersViewPath = $root . '/apps/Procurement/Views/orders.php';
$requestsViewPath = $root . '/apps/Procurement/Views/requests.php';
$dashboardViewPath = $root . '/apps/Procurement/Views/dashboard.php';

$service = (string)file_get_contents($servicePath);
$controller = (string)file_get_contents($controllerPath);
$ordersView = (string)file_get_contents($ordersViewPath);
$requestsView = (string)file_get_contents($requestsViewPath);
$dashboardView = (string)file_get_contents($dashboardViewPath);

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

$methodStart = strpos($service, 'public static function eligibleSupplierOptions(');
$methodEnd = strpos($service, 'public static function createSupplier(', (int)$methodStart);
$eligibleMethod = $methodStart !== false && $methodEnd !== false
    ? substr($service, $methodStart, $methodEnd - $methodStart)
    : '';

$check($eligibleMethod !== '', 'eligible supplier selection method exists');
$check(str_contains($eligibleMethod, "LOWER(TRIM(COALESCE(supplier_status, 'active'))) NOT IN ('inactive', 'disabled', 'blocked')"), 'selection trims ordinary spaces before applying established ineligible statuses');
$check(!str_contains($eligibleMethod, "IN ('active')"), 'selection remains exclusion-based rather than active-only');
$check(str_contains($eligibleMethod, "COALESCE(supplier_status, 'active')"), 'null supplier status retains the active fallback');
$check(strpos($eligibleMethod, 'NOT IN') < strpos($eligibleMethod, 'ORDER BY supplier_name ASC'), 'eligibility filtering precedes supplier ordering');
$check(strpos($eligibleMethod, 'ORDER BY supplier_name ASC') < strpos($eligibleMethod, 'LIMIT '), 'supplier ordering precedes the result limit');
$check(str_contains($service, 'public static function supplierOptions('), 'administrative supplier selection remains available');
$check(str_contains($controller, "'suppliers' => ProcurementOverviewService::supplierOptions(120)"), 'dashboard administration retains the unrestricted supplier source');
$check(substr_count($controller, "'suppliers' => ProcurementOverviewService::eligibleSupplierOptions(120)") === 2, 'requests and orders use the eligible supplier source');

$fixtureSuppliers = [
    ['id' => 1, 'supplier_name' => 'Active', 'supplier_status' => 'active'],
    ['id' => 2, 'supplier_name' => 'Uppercase active', 'supplier_status' => 'ACTIVE'],
    ['id' => 3, 'supplier_name' => 'Null status', 'supplier_status' => null],
    ['id' => 4, 'supplier_name' => 'Inactive', 'supplier_status' => 'inactive'],
    ['id' => 5, 'supplier_name' => 'Disabled', 'supplier_status' => 'disabled'],
    ['id' => 6, 'supplier_name' => 'Blocked', 'supplier_status' => 'blocked'],
    ['id' => 7, 'supplier_name' => 'Padded inactive', 'supplier_status' => ' inactive '],
    ['id' => 8, 'supplier_name' => 'Padded disabled', 'supplier_status' => ' DISABLED '],
    ['id' => 9, 'supplier_name' => 'Padded blocked', 'supplier_status' => ' BlOcKeD '],
    ['id' => 10, 'supplier_name' => 'Legacy review', 'supplier_status' => 'review'],
    ['id' => 11, 'supplier_name' => 'Legacy pending', 'supplier_status' => 'pending'],
];
$eligibleFixture = array_values(array_filter(
    $fixtureSuppliers,
    static fn(array $supplier): bool => !in_array(
        strtolower(trim((string)($supplier['supplier_status'] ?? 'active'))),
        ['inactive', 'disabled', 'blocked'],
        true
    )
));
$check(array_column($eligibleFixture, 'id') === [1, 2, 3, 10, 11], 'fixture selection retains active, uppercase active, null, and legacy statuses');
$check(!array_intersect(array_column($eligibleFixture, 'id'), [4, 5, 6, 7, 8, 9]), 'fixture selection excludes plain and padded inactive, disabled, and blocked suppliers');
$limitedFixture = array_slice($eligibleFixture, 0, 1);
$check(count($limitedFixture) === 1 && $limitedFixture[0]['id'] === 1, 'eligible filtering occurs before applying the result limit');

$check(str_contains($ordersView, 'name="supplier_id" required'), 'manual order supplier selector remains required');
$check(str_contains($requestsView, 'name="supplier_id"') && str_contains($requestsView, 'proc.requests.auto_supplier'), 'approved-request selector retains the Auto supplier option');
$check(str_contains($dashboardView, 'name="supplier_id" required'), 'supplier administration selector remains available');
$check(str_contains($dashboardView, 'name="supplier_status"'), 'supplier administration retains lifecycle editing');
$check(str_contains($controller, 'latestOrders(40)'), 'historical order loading remains unchanged');
$check(str_contains($service, 'self::assertSupplierEligible('), 'server-side eligibility validation remains authoritative');

echo "RESULT passed={$passed} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);

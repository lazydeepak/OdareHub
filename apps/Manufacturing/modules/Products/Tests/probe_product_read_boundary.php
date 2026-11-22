<?php
declare(strict_types=1);

$root = dirname(__DIR__, 5);
$readPath = $root . '/apps/Manufacturing/modules/Products/Services/ProductReadService.php';
$scanPath = $root . '/apps/Manufacturing/modules/Products/Services/ProductScanAdmissionService.php';
$qrPath = $root . '/apps/Platform/modules/QRCode/Controllers/QRCodeController.php';
$read = (string)file_get_contents($readPath);
$scan = (string)file_get_contents($scanPath);
$qr = (string)file_get_contents($qrPath);
$scanMethod = '';
if (preg_match('/public static function scanProduct\(.*?\n    \}\n\n    public static function stockIndex/s', $qr, $matches) === 1) {
    $scanMethod = $matches[0];
}

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    if ($condition) {
        $passed++;
        echo "PASS: {$label}\n";
        return;
    }
    $failed++;
    echo "FAIL: {$label}\n";
};

$check(str_contains($read, 'final class ProductReadService'), 'central Product read service exists');
$check(str_contains($read, 'findById('), 'central read seam supports ID lookup');
$check(str_contains($read, 'findByPartNumber('), 'central read seam supports part-number lookup');
$check(str_contains($read, 'findByQrIdentity('), 'central read seam supports QR identity lookup');
$check(substr_count($read, 'OrganizationMembershipContext $context') === 3, 'all public reads require opaque organization context');
$check(substr_count($read, 'organization_id=?') === 2, 'all direct SQL lookup branches apply organization ownership');
$check(substr_count($read, 'SELECT * FROM products') === 2, 'central seam contains only bounded lookup SQL');
$check(!str_contains($read, 'ProductOrganizationScope'), 'central seam does not resolve raw request organization input');
$check(!str_contains($read, 'requestedOrganizationId'), 'central seam exposes no raw organization-ID read API');
$check(str_contains($read, 'parts_number=? AND organization_id=?'), 'part-number lookup cannot escape tenant predicate');
$check(str_contains($scan, 'ProductReadService::findByQrIdentity('), 'QR admission uses central read seam');
$check($scanMethod !== '' && !str_contains($scanMethod, 'DB::'), 'QR scan controller does not retain direct Product detail SQL');
$check(str_contains($qr, 'ProductScanAdmissionService::resolve('), 'QR controller retains Products-owned admission');
$check($scanMethod !== '' && !str_contains($scanMethod, 'findProduct('), 'QR scan controller has no unrestricted Product detail fallback');

echo "Product read boundary probe: {$passed}/" . ($passed + $failed) . " assertions passed\n";
exit($failed === 0 ? 0 : 1);

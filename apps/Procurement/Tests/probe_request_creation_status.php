<?php
declare(strict_types=1);

/**
 * OH-AUTO2-132 isolated request-creation status proof.
 *
 * Pure normalization is invoked through reflection and the creation method is
 * checked for source ordering. No application bootstrap, database connection,
 * SQL execution, schema provisioning, or mutation method is invoked.
 */

$root = dirname(__DIR__, 3);
$servicePath = $root . '/apps/Procurement/Services/ProcurementOverviewService.php';
$controllerPath = $root . '/apps/Procurement/Controllers/ProcurementDashboardController.php';
$viewPath = $root . '/apps/Procurement/Views/requests.php';
$requestTransitionProofPath = $root . '/apps/Procurement/Tests/probe_request_status_transaction.php';
$linkageProofPath = $root . '/apps/Procurement/Tests/probe_request_linkage_integrity.php';

require_once $servicePath;

use Apps\Procurement\Services\ProcurementOverviewService;

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

$normalize = new ReflectionMethod(ProcurementOverviewService::class, 'normalizeRequestCreationStatus');
$normalizeRequestCreationStatus = static function ($value) use ($normalize): string {
    return (string)$normalize->invoke(null, $value);
};

foreach ([null, '', '   '] as $value) {
    $check($normalizeRequestCreationStatus($value) === 'draft', 'missing or blank creation status defaults to draft');
}

foreach (['draft', 'approved', 'closed', 'cancelled'] as $status) {
    $check($normalizeRequestCreationStatus($status) === $status, "supported creation status {$status} is accepted");
}

$check($normalizeRequestCreationStatus('  ApPrOvEd  ') === 'approved', 'mixed-case and surrounding whitespace normalize to approved');
$check($normalizeRequestCreationStatus("\tCANCELLED\n") === 'cancelled', 'mixed-case and surrounding whitespace normalize to cancelled');

foreach (['issued', 'posted', 'unknown', 'draft-now', ['closed'], 123] as $value) {
    try {
        $normalizeRequestCreationStatus($value);
        $check(false, 'unsupported or malformed creation status is rejected');
    } catch (InvalidArgumentException $e) {
        $check($e->getMessage() === 'Unsupported request status transition.', 'unsupported or malformed creation status is rejected');
    }
}

$source = (string)file_get_contents($servicePath);
$controller = (string)file_get_contents($controllerPath);
$view = (string)file_get_contents($viewPath);
$createStart = strpos($source, 'public static function createRequest(array $input, string $actor): void');
$createEnd = strpos($source, 'public static function intakeFromManufacturingDemand(', (int)$createStart);
$create = $createStart !== false && $createEnd !== false ? substr($source, $createStart, $createEnd - $createStart) : '';
$intakeStart = strpos($source, 'public static function intakeFromManufacturingDemand(');
$intakeEnd = strpos($source, 'public static function createOrder(array $input, string $actor): void', (int)$intakeStart);
$intake = $intakeStart !== false && $intakeEnd !== false ? substr($source, $intakeStart, $intakeEnd - $intakeStart) : '';

$statusCall = strpos($create, 'self::normalizeRequestCreationStatus(');
$qtyValidation = strpos($create, 'Requested quantity must be greater than zero.');
$requestRef = strpos($create, "self::nextRef('PR')");
$insert = strpos($create, 'INSERT INTO procurement_requests');
$audit = strpos($create, "'new_state' => \$requestStatus");

$check($create !== '', 'createRequest method is available');
$check($statusCall !== false, 'createRequest uses creation status normalization');
$check($statusCall !== false && ($qtyValidation === false || $statusCall < $qtyValidation) && ($requestRef === false || $statusCall < $requestRef) && $statusCall < $insert, 'status validation precedes quantity/reference work and insertion');
$check(substr_count($create, '$input[\'request_status\']') === 1, 'raw request status input is read only for canonicalization');
$check($insert !== false && $audit !== false && $insert < $audit, 'request insertion and audit both use canonical status');
$check(str_contains($create, "'new_state' => \$requestStatus"), 'creation audit new_state uses canonical status');
$check(str_contains($create, 'requested_qty'), 'positive requested-quantity validation remains present');
$check(str_contains($create, 'source_app') && str_contains($create, 'source_ref_type') && str_contains($create, 'source_ref_id'), 'request provenance fields remain present');
$check(str_contains($create, 'self::nullIfBlank((string)($input[\'needed_date\'] ?? \'\'))'), 'optional request fields remain unchanged');

$check(str_contains($intake, "'draft',"), 'Manufacturing-demand intake explicitly creates draft requests');
$check(str_contains($intake, "'new_state' => 'draft'"), 'Manufacturing-demand audit remains draft');
$check(str_contains($source, 'private static function normalizeRequestStatus(string $status)'), 'existing request transition normalizer remains present');
$check(str_contains($source, 'self::normalizeRequestStatus($status)'), 'direct/internal request transitions retain shared normalizer');
$check(str_contains($controller, "Auth::requireAppAccess('procurement')") && str_contains($controller, 'Auth::requireCsrf('), 'controller authorization and CSRF remain intact');
$check(str_contains($controller, 'ProcurementOverviewService::createRequest($_POST'), 'manual controller dispatch remains unchanged');
$check(str_contains($view, 'name="request_status" value="draft"'), 'manual request form still submits draft');
$check(is_file($requestTransitionProofPath), 'request transition regression remains available');
$check(is_file($linkageProofPath), 'request linkage regression remains available');

echo "RESULT passed={$passed} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);

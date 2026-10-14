<?php
declare(strict_types=1);

/**
 * OH-AUTO1-020 static transaction-contract audit.
 *
 * This probe is intentionally DB-free. It inspects source structure only and
 * verifies explicit transaction boundaries for Procurement multi-write
 * operations. It must never bootstrap the application DB or invoke a business
 * mutation.
 */

$root = dirname(__DIR__, 3);
$procurementPath = $root . '/apps/Procurement/Services/ProcurementOverviewService.php';
$manufacturingPath = $root . '/apps/Manufacturing/Services/UpstreamSupplyGenerationService.php';
$manufacturingControllerPath = $root . '/apps/Manufacturing/Controllers/DemandController.php';
$hospitalityPath = $root . '/apps/Hospitality/Services/FrontDeskService.php';

$passed = 0;
$failed = 0;
$pass = static function (string $label) use (&$passed): void {
    $passed++;
    echo "  PASS [{$label}]\n";
};
$fail = static function (string $label, string $detail = '') use (&$failed): void {
    $failed++;
    echo "  FAIL [{$label}]" . ($detail !== '' ? ": {$detail}" : '') . "\n";
};

$read = static function (string $path) use ($fail): string {
    $source = @file_get_contents($path);
    if (!is_string($source) || $source === '') {
        $fail('source available', $path);
        return '';
    }
    return $source;
};

$methodBody = static function (string $source, string $method): string {
    $start = preg_match('/(?:public|private|protected)\s+static\s+function\s+' . preg_quote($method, '/') . '\s*\(/', $source, $match, PREG_OFFSET_CAPTURE);
    if ($start !== 1 || !isset($match[0][1])) {
        return '';
    }
    $start = (int)$match[0][1];
    $nextMatch = [];
    $next = preg_match('/(?:public|private|protected)\s+static\s+function\s+\w+\s*\(/', $source, $nextMatch, PREG_OFFSET_CAPTURE, $start + 1);
    $next = ($next === 1 && isset($nextMatch[0][1])) ? (int)$nextMatch[0][1] : false;
    return substr($source, $start, $next === false ? null : $next - $start);
};

$contains = static function (string $body, string $pattern): bool {
    return preg_match($pattern, $body) === 1;
};

echo "== OH-AUTO1-020 static transaction-contract audit ==\n";

$procurement = $read($procurementPath);
$manufacturing = $read($manufacturingPath);
$manufacturingController = $read($manufacturingControllerPath);
$hospitality = $read($hospitalityPath);

if ($procurement !== '') {
    $pass('Procurement source loaded');
    $probeSource = (string)@file_get_contents(__FILE__);
    if ($probeSource !== '' && !preg_match('/(?:DB::conn\s*\(|DB::query\s*\(|plugins\/Base\/bootstrap)/', $probeSource)) {
        $pass('Probe remains DB-free');
    } else {
        $fail('Probe remains DB-free');
    }

    foreach (['createOrder', 'createOrderFromApprovedRequest', 'createReceipt'] as $method) {
        $body = $methodBody($procurement, $method);
        if ($body === '') {
            $fail("Procurement method exists: {$method}");
            continue;
        }
        $pass("Procurement method exists: {$method}");
        $writes = preg_match_all('/DB::query\s*\(\s*[\'\"](?:INSERT|UPDATE|DELETE)/i', $body);
        $multiWriteEvidence = $writes !== false && $writes >= 2;
        if ($method === 'createReceipt') {
            $multiWriteEvidence = $writes === 1
                && $contains($body, '/recordAudit\s*\(/')
                && $contains($body, '/recomputeOrderStatusFromReceipts\s*\(/');
        }
        if ($multiWriteEvidence) {
            $pass("{$method} has multiple durable writes");
        } else {
            $fail("{$method} multi-write classification", 'expected at least two direct INSERT/UPDATE statements');
        }
        $hasBoundary = $contains($body, '/->begin_transaction\s*\(/')
            && $contains($body, '/->commit\s*\(/')
            && $contains($body, '/->rollback\s*\(/');
        $hasBoundary
            ? $pass("{$method} has explicit commit/rollback boundary")
            : $fail("{$method} transaction profile", 'multi-write operation lacks commit/rollback boundary');
    }

    $order = $methodBody($procurement, 'createOrder');
    $receipt = $methodBody($procurement, 'createReceipt');
    $contains($order, '/procurement_purchase_orders/') && $contains($order, '/procurement_purchase_order_lines/')
        ? $pass('createOrder writes order then order line')
        : $fail('createOrder write sequence');
    $contains($receipt, '/Receipt line reference does not belong/') && $contains($receipt, '/procurement_receipts/')
        ? $pass('createReceipt owns receipt-line validation before insert')
        : $fail('createReceipt validation boundary');
}

if ($manufacturing !== '') {
    $pass('Manufacturing upstream source loaded');
    foreach (['syncProductionCandidate', 'syncProcurementDemand'] as $method) {
        $body = $methodBody($manufacturing, $method);
        if ($body === '') {
            $fail("Manufacturing method exists: {$method}");
            continue;
        }
        $pass("Manufacturing method exists: {$method}");
        if ($contains($body, '/DB::query\s*\(\s*[\'\"](?:INSERT|UPDATE)/i') && $contains($body, '/upsertLink\s*\(/')) {
            $pass("{$method} target/link multi-write path identified");
        } else {
            $fail("{$method} target/link multi-write classification");
        }
        $contains($body, '/begin_transaction|->commit\s*\(|->rollback\s*\(/')
            ? $pass("{$method} has explicit commit/rollback boundary")
            : $fail("{$method} transaction profile", 'target/link pair lacks commit/rollback boundary');
    }

    $generate = $methodBody($manufacturing, 'generate');
    if (!$contains($generate, '/ensureSchema\s*\(/')
        && $contains($generate, '/syncProductionCandidate|syncProcurementDemand/')) {
        $pass('Manufacturing generate remains free of runtime schema-ensure DDL');
    } else {
        $fail('Manufacturing runtime DDL boundary contract');
    }
}

if ($manufacturingController !== '') {
    if (str_contains($manufacturingController, 'UpstreamSupplyGenerationService::generate')
        && !preg_match('/begin_transaction|->commit\s*\(|->rollback\s*\(/', $manufacturingController)) {
        $pass('Current Manufacturing generation caller owns no outer transaction');
    } else {
        $fail('Manufacturing caller transaction ownership scan');
    }
}

if ($hospitality !== '') {
    $pass('Hospitality front-desk source loaded');
    foreach (['checkout', 'addChargeForReservation', 'voidCharge'] as $method) {
        $body = $methodBody($hospitality, $method);
        if ($body === '') {
            $fail("Hospitality method exists: {$method}");
            continue;
        }
        $pass("Hospitality method exists: {$method}");
        $hasBoundary = $contains($body, '/->begin_transaction\s*\(/')
            && $contains($body, '/->commit\s*\(/')
            && $contains($body, '/->rollback\s*\(/');
        $hasLock = $contains($body, '/FOR UPDATE/i');
        $hasBoundary ? $pass("{$method} has commit/rollback coverage") : $fail("{$method} transaction boundary");
        $hasLock ? $pass("{$method} has row-lock evidence") : $fail("{$method} row-lock evidence");
    }
}

echo "\nResult: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);

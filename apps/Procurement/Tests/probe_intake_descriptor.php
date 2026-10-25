<?php
declare(strict_types=1);

/**
 * OH-AUTO3-PROC-CONTRACT-PRE DB-free descriptor boundary probe.
 * No application bootstrap, database connection, schema change, or intake
 * dispatch is permitted here.
 */

require_once dirname(__DIR__) . '/Services/ProcurementIntakeDescriptor.php';
require_once dirname(__DIR__) . '/Services/ProcurementIntakeDescriptorValidator.php';

use Apps\Procurement\Services\ProcurementIntakeDescriptorValidator;

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

$base = [
    'source_app' => 'manufacturing',
    'source_entity' => 'mfg_procurement_demands',
    'source_record_id' => '42',
    'source_revision' => 'legacy',
    'actor' => 'operator@example.test',
    'organization_scope' => 'org:example',
    'record_scope' => 'mfg_procurement_demands:42',
    'capability' => 'procurement.request.intake',
];

$valid = ProcurementIntakeDescriptorValidator::validate($base);
$check($valid['valid'] === true && $valid['descriptor'] !== null, 'valid descriptor accepted');
$check($valid['descriptor']?->toArray()['source_entity'] === 'mfg_procurement_demands', 'canonical historical source entity is preserved');
$check($valid['descriptor']?->toArray()['source_revision'] === 'legacy', 'legacy revision compatibility is explicit');
$check($valid['descriptor']?->toArray()['idempotency_key'] === hash('sha256', "manufacturing\x1fmfg_procurement_demands\x1f42\x1flegacy"), 'idempotency key is server-derived from source identity');
$check($valid['descriptor']?->toArray()['actor'] === 'operator@example.test', 'authorization actor evidence is retained');

$replay = ProcurementIntakeDescriptorValidator::validate($base);
$check($replay['descriptor']?->idempotencyKey() === $valid['descriptor']?->idempotencyKey(), 'exact replay derives the same key');

$revision = $base;
$revision['source_revision'] = '2';
$revised = ProcurementIntakeDescriptorValidator::validate($revision);
$check($revised['valid'] === true, 'explicit revision is accepted');
$check($revised['descriptor']?->idempotencyKey() !== $valid['descriptor']?->idempotencyKey(), 'different revision derives a different key');

foreach ([
    ['source_app' => 'Manufacturing', 'label' => 'uppercase app key rejected'],
    ['source_record_id' => '', 'label' => 'empty source record rejected'],
    ['source_revision' => "1\n2", 'label' => 'control character rejected'],
    ['capability' => 'procurement.request.write', 'label' => 'unsupported capability rejected'],
    ['organization_scope' => '', 'label' => 'missing organization evidence rejected'],
    ['record_scope' => '', 'label' => 'missing record evidence rejected'],
] as $case) {
    $invalid = $base;
    $key = array_key_first($case);
    $invalid[$key] = $case[$key];
    $result = ProcurementIntakeDescriptorValidator::validate($invalid);
    $check($result['valid'] === false && $result['descriptor'] === null, $case['label']);
}

$probeSource = (string)file_get_contents(__FILE__);
$dbCallMarker = 'DB' . '::';
$intakeServiceMarker = 'Procurement' . 'OverviewService';
$check(!str_contains($probeSource, $dbCallMarker), 'probe contains no DB calls');
$check(!str_contains($probeSource, $intakeServiceMarker), 'probe does not dispatch intake');

echo "RESULTS passed={$passed} failed={$failed}\n";
exit($failed > 0 ? 1 : 0);

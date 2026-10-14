<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/vendor/autoload.php';

use Platform\Updates\InstalledSystemCompatibilityPreflightService;

$passed = 0;
$failed = 0;

function compatibility_assert(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }
    $failed++;
    fwrite(STDERR, "FAIL: {$label}\n");
}

function compatibility_state(): array
{
    return [
        'product_id' => 'susankhya-os',
        'product_version' => '1.0.0',
        'platform' => ['product_id' => 'susankhya-os', 'version' => '1.0.0', 'build_id' => 'git:installed'],
        'apps' => [
            ['id' => 'billing', 'version' => '2.0.0', 'status' => 'active'],
        ],
        'modules' => [
            ['id' => 'reports', 'version' => '3.0.0', 'status' => 'active'],
            ['id' => 'shared', 'version' => '1.0.0', 'status' => 'installed'],
        ],
        'schema' => ['version' => '5.0.0'],
        'migrations' => ['pending' => [], 'failed' => [], 'order' => []],
    ];
}

function compatibility_requirements(): array
{
    return [
        'minimum_product_version' => '1.0.0',
        'maximum_product_version' => null,
        'core' => ['minimum' => '1.0.0'],
        'apps' => [
            ['id' => 'billing', 'required' => true, 'minimum' => '2.0.0', 'maximum' => '3.0.0'],
        ],
        'modules' => [
            ['id' => 'reports', 'required' => true, 'minimum' => '3.0.0', 'requires' => ['shared']],
        ],
        'schema' => ['minimum' => '5.0.0'],
        'php' => ['minimum' => '8.1.0'],
        'php_extensions' => ['json'],
    ];
}

$service = new InstalledSystemCompatibilityPreflightService();
$valid = $service->validate(compatibility_requirements(), compatibility_state(), '1.0.0');
compatibility_assert($valid['ok'] === true, 'matching installed system is compatible');

$missingEvidence = $service->validate(compatibility_requirements(), ['product_version' => '1.0.0'], '1.0.0');
compatibility_assert($missingEvidence['ok'] === false, 'missing installed-state evidence is blocked');

$productMismatch = compatibility_state();
$productMismatch['product_id'] = 'other-product';
compatibility_assert(!$service->validate(compatibility_requirements(), $productMismatch, '1.0.0')['ok'], 'product identity mismatch is blocked');

$platformMismatch = compatibility_state();
$platformMismatch['platform']['product_id'] = 'other-product';
compatibility_assert(!$service->validate(compatibility_requirements(), $platformMismatch, '1.0.0')['ok'], 'platform identity mismatch is blocked');

$appVersion = compatibility_state();
$appVersion['apps'][0]['version'] = '1.0.0';
compatibility_assert(!$service->validate(compatibility_requirements(), $appVersion, '1.0.0')['ok'], 'application version incompatibility is blocked');

$missingApp = compatibility_state();
$missingApp['apps'] = [];
compatibility_assert(!$service->validate(compatibility_requirements(), $missingApp, '1.0.0')['ok'], 'missing required application is blocked');

$moduleVersion = compatibility_state();
$moduleVersion['modules'][0]['version'] = '2.0.0';
compatibility_assert(!$service->validate(compatibility_requirements(), $moduleVersion, '1.0.0')['ok'], 'module version incompatibility is blocked');

$missingDependency = compatibility_state();
$missingDependency['modules'] = [['id' => 'reports', 'version' => '3.0.0', 'status' => 'active']];
compatibility_assert(!$service->validate(compatibility_requirements(), $missingDependency, '1.0.0')['ok'], 'missing module dependency is blocked');

$disabledModule = compatibility_state();
$disabledModule['modules'][0]['status'] = 'disabled';
compatibility_assert(!$service->validate(compatibility_requirements(), $disabledModule, '1.0.0')['ok'], 'disabled required module is not treated as enabled');

$schemaMismatch = compatibility_state();
$schemaMismatch['schema']['version'] = '4.0.0';
compatibility_assert(!$service->validate(compatibility_requirements(), $schemaMismatch, '1.0.0')['ok'], 'schema baseline mismatch is blocked');

$pendingMigration = compatibility_state();
$pendingMigration['migrations']['pending'] = ['001_pending.sql'];
compatibility_assert(!$service->validate(compatibility_requirements(), $pendingMigration, '1.0.0')['ok'], 'pending migration is blocked');

$failedMigration = compatibility_state();
$failedMigration['migrations']['failed'] = ['000_failed.sql'];
compatibility_assert(!$service->validate(compatibility_requirements(), $failedMigration, '1.0.0')['ok'], 'failed migration is blocked');

$malformed = compatibility_state();
$malformed['modules'] = ['reports'];
compatibility_assert(!$service->validate(compatibility_requirements(), $malformed, '1.0.0')['ok'], 'malformed module evidence is blocked');

$malformedRequirements = compatibility_requirements();
unset($malformedRequirements['schema']);
compatibility_assert(!$service->validate($malformedRequirements, compatibility_state(), '1.0.0')['ok'], 'malformed release compatibility metadata is blocked');

$contradictory = compatibility_state();
$contradictory['platform']['product_id'] = 'other-product';
$contradictory['product_id'] = 'susankhya-os';
compatibility_assert(!$service->validate(compatibility_requirements(), $contradictory, '1.0.0')['ok'], 'contradictory installed identity evidence is blocked');

$downgrade = compatibility_state();
$downgrade['product_version'] = '2.0.0';
$downgrade['platform']['version'] = '2.0.0';
compatibility_assert(!$service->validate(compatibility_requirements(), $downgrade, '1.0.0')['ok'], 'unsupported downgrade is blocked');

echo "Installed-system compatibility probe: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);

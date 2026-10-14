<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/vendor/autoload.php';

use Platform\Updates\InstalledSystemCompatibilityPreflightService;
use Platform\Updates\MigrationCompatibilityPreflightService;

$passed = 0;
$failed = 0;

function migration_assert(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }
    $failed++;
    fwrite(STDERR, "FAIL: {$label}\n");
}

function migration_state(array $migrations): array
{
    return [
        'product_id' => 'susankhya-os',
        'product_version' => '1.0.0',
        'platform' => [
            'product_id' => 'susankhya-os',
            'version' => '1.0.0',
            'build_id' => 'git:installed',
        ],
        'apps' => [
            ['id' => 'platform', 'version' => '1.0.0', 'status' => 'enabled'],
        ],
        'modules' => [],
        'schema' => ['version' => '1.0.0'],
        'migrations' => $migrations,
    ];
}

function migration_metadata(?string $target = null): array
{
    return [
        'core' => ['minimum' => '1.0.0'],
        'apps' => [],
        'modules' => [],
        'schema' => ['minimum' => '1.0.0', 'target' => $target],
    ];
}

$verifiedEvidence = [
    'identities_verified' => true,
    'ownership_verified' => true,
    'checksums_verified' => true,
    'ordering_verified' => true,
    'target_schema_verified' => true,
    'target_schema' => '1.0.0',
    'errors' => [],
];

$matching = migration_state([
    'pending' => [],
    'failed' => [],
    'order' => ['platform:001.sql', 'platform:002.sql'],
    'evidence' => $verifiedEvidence,
]);
$service = new MigrationCompatibilityPreflightService();
$matchingResult = $service->validate(migration_metadata('1.0.0'), $matching);
migration_assert($matchingResult['ok'] === true, 'matching migration history is structurally valid');
migration_assert($matchingResult['eligibility_blockers'] === [], 'complete migration evidence has no eligibility blocker');

$missing = $matching;
$missing['migrations']['pending'] = ['platform:002.sql'];
$missingResult = $service->validate(migration_metadata('1.0.0'), $missing);
migration_assert($missingResult['ok'] === false, 'missing required migration blocks compatibility');

$failedMigrationState = $matching;
$failedMigrationState['migrations']['failed'] = ['platform:002.sql'];
migration_assert($service->validate(migration_metadata('1.0.0'), $failedMigrationState)['ok'] === false, 'failed migration blocks compatibility');

$duplicate = $matching;
$duplicate['migrations']['evidence']['errors'] = ['duplicate migration identity: platform:001.sql.'];
$duplicate['migrations']['evidence']['identities_verified'] = false;
$duplicateResult = $service->validate(migration_metadata('1.0.0'), $duplicate);
migration_assert($duplicateResult['eligibility_blockers'] !== [], 'duplicate migration identity blocks eligibility');

$invalidOrder = $matching;
$invalidOrder['migrations']['evidence']['ordering_verified'] = false;
$invalidOrder['migrations']['evidence']['errors'] = ['migration ledger order does not match the migration runner order.'];
$invalidOrderResult = $service->validate(migration_metadata('1.0.0'), $invalidOrder);
migration_assert($invalidOrderResult['eligibility_blockers'] !== [], 'invalid migration ordering blocks eligibility');

$missingOwner = $matching;
$missingOwner['migrations']['evidence']['ownership_verified'] = false;
$missingOwner['migrations']['evidence']['errors'] = ['migration owner is not an installed application: missing.'];
migration_assert($service->validate(migration_metadata('1.0.0'), $missingOwner)['eligibility_blockers'] !== [], 'missing migration owner blocks eligibility');

$badChecksum = $matching;
$badChecksum['migrations']['evidence']['checksums_verified'] = false;
$badChecksum['migrations']['evidence']['errors'] = ['migration checksum mismatch: platform:001.sql.'];
migration_assert($service->validate(migration_metadata('1.0.0'), $badChecksum)['eligibility_blockers'] !== [], 'migration checksum mismatch blocks eligibility');

$unknownSchema = $matching;
$unknownSchema['schema']['version'] = '';
$unknownSchemaResult = (new InstalledSystemCompatibilityPreflightService())->validate(migration_metadata('1.0.0'), $unknownSchema, '1.0.0');
migration_assert($unknownSchemaResult['ok'] === false, 'unknown installed schema baseline blocks compatibility');

$unsupportedDowngrade = (new InstalledSystemCompatibilityPreflightService())->validate(
    migration_metadata('1.0.0'),
    $matching,
    '0.9.0'
);
migration_assert($unsupportedDowngrade['ok'] === false, 'unsupported downgrade blocks compatibility');

$targetMismatch = $matching;
$targetMismatch['migrations']['evidence']['target_schema'] = '2.0.0';
$targetMismatchResult = $service->validate(migration_metadata('1.0.0'), $targetMismatch);
migration_assert($targetMismatchResult['ok'] === false, 'target schema mismatch blocks compatibility');

$malformed = $matching;
$malformed['migrations']['order'] = 'not-an-array';
$malformedResult = $service->validate(migration_metadata('1.0.0'), $malformed);
migration_assert($malformedResult['ok'] === false, 'malformed migration evidence blocks compatibility');

$unavailable = migration_state(['pending' => [], 'failed' => [], 'order' => []]);
$unavailableResult = $service->validate(migration_metadata(null), $unavailable);
migration_assert($unavailableResult['eligibility_blockers'] !== [], 'missing ordering and target contracts remain blocked');

$crossRelease = $matching;
$crossRelease['migrations']['evidence']['target_schema'] = 'release-A-schema';
$crossReleaseResult = $service->validate(migration_metadata('release-B-schema'), $crossRelease);
migration_assert($crossReleaseResult['ok'] === false, 'cross-release schema evidence reuse is rejected');

$compatibility = (new InstalledSystemCompatibilityPreflightService())->validate(
    migration_metadata('1.0.0'),
    $matching,
    '1.0.0'
);
migration_assert($compatibility['ok'] === true, 'compatibility accepts complete authoritative migration evidence');

$bypass = $matching;
$bypass['migrations']['pending'] = [];
$bypass['migrations']['failed'] = [];
$bypass['migrations']['evidence']['ordering_verified'] = false;
$bypassResult = (new InstalledSystemCompatibilityPreflightService())->validate(
    migration_metadata('1.0.0'),
    $bypass,
    '1.0.0'
);
migration_assert($bypassResult['ok'] === true && $bypassResult['eligibility_blockers'] !== [], 'apply-preparation cannot bypass unverified migration eligibility');

echo "Migration compatibility probe: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);

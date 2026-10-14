<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/vendor/autoload.php';

use Platform\Updates\InstalledSystemCompatibilityPreflightService;
use Platform\Updates\InstalledSystemEvidenceProvider;

$passed = 0;
$failed = 0;

function evidence_assert(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }
    $failed++;
    fwrite(STDERR, "FAIL: {$label}\n");
}

function evidence_rm_tree(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }
    foreach (scandir($path) ?: [] as $item) {
        if ($item !== '.' && $item !== '..') {
            evidence_rm_tree($path . '/' . $item);
        }
    }
    @rmdir($path);
}

function evidence_provider(array $apps, array $modules, array $identity, array $migrations, array $snapshots): InstalledSystemEvidenceProvider
{
    return new InstalledSystemEvidenceProvider(
        static fn(): array => $apps,
        static fn(): array => $modules,
        static fn(): array => $identity,
        static fn(): array => $migrations,
        static fn(): array => $snapshots
    );
}

function evidence_requirements(): array
{
    return [
        'minimum_product_version' => '1.0.0',
        'maximum_product_version' => null,
        'core' => ['minimum' => '1.0.0'],
        'apps' => [['id' => 'billing', 'required' => true, 'minimum' => '2.0.0']],
        'modules' => [['id' => 'reports', 'required' => true, 'minimum' => '3.0.0', 'requires' => ['shared']]],
        'schema' => ['minimum' => '1.0.0'],
        'php' => ['minimum' => '8.1.0'],
        'php_extensions' => ['json'],
    ];
}

$tmp = sys_get_temp_dir() . '/susankhya-installed-evidence-' . bin2hex(random_bytes(6));
mkdir($tmp, 0700, true);
$migrationDir = $tmp . '/platform/migrations';
mkdir($migrationDir, 0700, true);
file_put_contents($migrationDir . '/001_platform.sql', 'SELECT 1;');

$apps = [
    [
        'app_key' => 'platform',
        'version' => '1.0.0',
        'status' => 'enabled',
        'build_id' => 'git:installed',
        'install_path' => $tmp . '/platform',
        'manifest_json' => json_encode(['migrations_path' => 'migrations']),
    ],
    [
        'app_key' => 'billing',
        'version' => '2.0.0',
        'status' => 'enabled',
        'install_path' => $tmp . '/billing',
        'manifest_json' => json_encode([]),
    ],
];
$modules = [
    ['name' => 'reports', 'version' => '3.0.0', 'status' => 'active', 'requires_json' => json_encode(['shared'])],
    ['name' => 'shared', 'version' => '1.0.0', 'status' => 'active', 'requires_json' => json_encode([])],
];
$identity = [['setting_key' => 'product_id', 'setting_value' => 'susankhya-os']];
$migrations = [['id' => 1, 'app_key' => 'platform', 'version' => '1.0.0', 'migration_name' => '001_platform.sql', 'checksum' => hash_file('sha256', $migrationDir . '/001_platform.sql'), 'status' => 'applied']];
$snapshotJson = json_encode(['schema_version' => '1.0.0']);
$snapshots = [['app_key' => 'platform', 'app_version' => '1.0.0', 'snapshot_json' => $snapshotJson, 'checksum' => hash('sha256', (string)$snapshotJson)]];

try {
    $provider = evidence_provider($apps, $modules, $identity, $migrations, $snapshots);
    $valid = $provider->collect();
    evidence_assert($valid['ok'] === true, 'complete read-only installed evidence validates');
    evidence_assert(($valid['state']['product_id'] ?? '') === 'susankhya-os', 'product identity comes from identity source');
    evidence_assert(($valid['state']['platform']['version'] ?? '') === '1.0.0', 'platform version comes from installed registry');
    evidence_assert(count((array)($valid['state']['apps'] ?? [])) === 1, 'installed application registry is represented');
    evidence_assert(count((array)($valid['state']['modules'] ?? [])) === 2, 'installed module registry is represented');
    evidence_assert(($valid['state']['migrations']['pending'] ?? ['unexpected']) === [], 'applied migration ledger proves no pending migration');

    $compatibility = (new InstalledSystemCompatibilityPreflightService())->validate(
        evidence_requirements(),
        $valid['state'],
        '1.0.0'
    );
    evidence_assert($compatibility['ok'] === true, 'provider output satisfies compatibility preflight');

    $missingRegistry = evidence_provider([], $modules, $identity, $migrations, $snapshots)->collect();
    evidence_assert($missingRegistry['ok'] === false, 'missing application registry fails closed');

    $missingDatabase = new InstalledSystemEvidenceProvider(
        static function (): array { throw new RuntimeException('database connection unavailable'); },
        static function (): array { throw new RuntimeException('database connection unavailable'); },
        static function (): array { throw new RuntimeException('database connection unavailable'); },
        static function (): array { throw new RuntimeException('database connection unavailable'); },
        static function (): array { throw new RuntimeException('database connection unavailable'); }
    );
    evidence_assert($missingDatabase->collect()['ok'] === false, 'missing database connection fails closed');

    $disabled = $modules;
    $disabled[0]['status'] = 'inactive';
    $disabledResult = evidence_provider($apps, $disabled, $identity, $migrations, $snapshots)->collect();
    $disabledCompatibility = (new InstalledSystemCompatibilityPreflightService())->validate(evidence_requirements(), $disabledResult['state'], '1.0.0');
    evidence_assert($disabledCompatibility['ok'] === false, 'disabled required module is incompatible');

    $missingComponent = $apps;
    array_pop($missingComponent);
    $missingComponentResult = evidence_provider($missingComponent, $modules, $identity, $migrations, $snapshots)->collect();
    $missingComponentCompatibility = (new InstalledSystemCompatibilityPreflightService())->validate(evidence_requirements(), $missingComponentResult['state'], '1.0.0');
    evidence_assert($missingComponentCompatibility['ok'] === false, 'missing required application is incompatible');

    $dependencyMismatch = [$modules[0]];
    $dependencyResult = evidence_provider($apps, $dependencyMismatch, $identity, $migrations, $snapshots)->collect();
    $dependencyCompatibility = (new InstalledSystemCompatibilityPreflightService())->validate(evidence_requirements(), $dependencyResult['state'], '1.0.0');
    evidence_assert($dependencyCompatibility['ok'] === false, 'missing module dependency is incompatible');

    $contradictory = $apps;
    $contradictory[0]['status'] = 'mystery';
    evidence_assert(evidence_provider($contradictory, $modules, $identity, $migrations, $snapshots)->collect()['ok'] === false, 'contradictory lifecycle status fails closed');

    $missingSchema = evidence_provider($apps, $modules, $identity, $migrations, [])->collect();
    evidence_assert($missingSchema['ok'] === false, 'missing schema ledger fails closed');

    $pending = $migrations;
    $pending[0]['migration_name'] = '000_other.sql';
    $pendingResult = evidence_provider($apps, $modules, $identity, $pending, $snapshots)->collect();
    evidence_assert(($pendingResult['state']['migrations']['pending'] ?? []) !== [], 'unledgered declared migration is reported pending');
    evidence_assert(!(new InstalledSystemCompatibilityPreflightService())->validate(evidence_requirements(), $pendingResult['state'], '1.0.0')['ok'], 'pending migration blocks compatibility');

    $failedMigration = $migrations;
    $failedMigration[0]['status'] = 'failed';
    $failedResult = evidence_provider($apps, $modules, $identity, $failedMigration, $snapshots)->collect();
    evidence_assert(($failedResult['state']['migrations']['failed'] ?? []) !== [], 'failed migration is preserved');
    evidence_assert(!(new InstalledSystemCompatibilityPreflightService())->validate(evidence_requirements(), $failedResult['state'], '1.0.0')['ok'], 'failed migration blocks compatibility');

    $unsupportedPlatform = $apps;
    $unsupportedPlatform[0]['version'] = '0.5.0';
    $unsupportedResult = evidence_provider($unsupportedPlatform, $modules, $identity, $migrations, $snapshots)->collect();
    evidence_assert(!(new InstalledSystemCompatibilityPreflightService())->validate(evidence_requirements(), $unsupportedResult['state'], '1.0.0')['ok'], 'unsupported platform version blocks compatibility');

    $incompleteVersion = $apps;
    $incompleteVersion[1]['version'] = '';
    evidence_assert(evidence_provider($incompleteVersion, $modules, $identity, $migrations, $snapshots)->collect()['ok'] === false, 'incomplete component version fails closed');

    $cli = (string)file_get_contents(APP_ROOT . '/scripts/system/recovery_point.php');
    evidence_assert(str_contains($cli, 'InstalledSystemEvidenceProvider::fromRuntime()'), 'CLI uses authoritative evidence provider');
    evidence_assert(!str_contains($cli, "['product_version' => APP_VERSION]"), 'CLI no longer injects placeholder product version evidence');
} finally {
    evidence_rm_tree($tmp);
}

echo "Installed-system evidence provider probe: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);

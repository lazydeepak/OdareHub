<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/vendor/autoload.php';

use Platform\Updates\InstalledSystemEvidenceProvider;

$passed = 0;
$failed = 0;

function build_identity_assert(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }
    $failed++;
    fwrite(STDERR, "FAIL: {$label}\n");
}

function build_identity_provider(array $apps, array $identity): InstalledSystemEvidenceProvider
{
    return new InstalledSystemEvidenceProvider(
        static fn(): array => $apps,
        static fn(): array => [],
        static fn(): array => $identity,
        static fn(): array => [['id' => 1, 'app_key' => 'platform', 'version' => '1.0.0', 'migration_name' => '001_platform.sql', 'checksum' => str_repeat('a', 64), 'status' => 'applied']],
        static fn(): array => [['app_key' => 'platform', 'app_version' => '1.0.0', 'snapshot_json' => '{"schema_version":"1.0.0"}', 'checksum' => hash('sha256', '{"schema_version":"1.0.0"}')]],
    );
}

$apps = [[
    'app_key' => 'platform',
    'version' => '1.0.0',
    'status' => 'enabled',
    'build_id' => 'build-installed-001',
    // No migration directory is declared in this fixture; this probe tests identity only.
    'install_path' => sys_get_temp_dir(),
    'manifest_json' => json_encode([]),
]];
$identity = [['setting_key' => 'product_id', 'setting_value' => 'susankhya-os']];

$valid = build_identity_provider($apps, $identity)->collect();
build_identity_assert($valid['ok'] === true, 'explicit platform registry build identity is preserved in structured evidence');
build_identity_assert(($valid['state']['platform']['build_id'] ?? '') === 'build-installed-001', 'installed build identity comes from the installed platform row');
build_identity_assert(($valid['state']['platform']['build_id'] ?? '') !== 'git:repository-head', 'repository revision is not substituted for installed build identity');
build_identity_assert(($valid['state']['platform']['build_id'] ?? '') !== hash('sha256', 'package'), 'package checksum is not substituted for installed build identity');

$missing = $apps;
unset($missing[0]['build_id']);
$missingResult = build_identity_provider($missing, $identity)->collect();
build_identity_assert($missingResult['ok'] === false, 'missing installed build identity fails closed');
build_identity_assert(str_contains(implode(' ', $missingResult['errors']), 'build identity'), 'missing identity has an explicit diagnostic');

$malformed = $apps;
$malformed[0]['build_id'] = '   ';
$malformedResult = build_identity_provider($malformed, $identity)->collect();
build_identity_assert($malformedResult['ok'] === false, 'blank installed build identity fails closed');

$contradictoryProduct = build_identity_provider($apps, [
    ['setting_key' => 'product_id', 'setting_value' => 'other-product'],
])->collect();
build_identity_assert(($contradictoryProduct['state']['product_id'] ?? '') === 'other-product', 'product identity remains sourced separately from build identity');
build_identity_assert($contradictoryProduct['ok'] === true, 'provider does not silently invent a cross-source product/build contract');

$schema = (string)file_get_contents(APP_ROOT . '/app/Services/AppMigrationService.php');
build_identity_assert(!preg_match('/\bbuild_id\b/', $schema), 'core_apps schema has no persisted build identity column');
$discovery = (string)file_get_contents(APP_ROOT . '/app/Services/AppLocalDiscoveryService.php');
build_identity_assert(!str_contains($discovery, 'build_id'), 'local app discovery does not fabricate or persist build identity');

$cli = (string)file_get_contents(APP_ROOT . '/scripts/system/recovery_point.php');
build_identity_assert(str_contains($cli, 'authoritative installed-state evidence is unavailable; recovery identity cannot be fabricated from repository HEAD.'), 'recovery creation blocks without installed identity');
build_identity_assert(!str_contains($cli, "'build_id' => git_head()"), 'recovery creation no longer uses repository HEAD as installed build identity');

echo "Installed build identity probe: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);

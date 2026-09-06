<?php
declare(strict_types=1);

$repoRoot = dirname(__DIR__, 3);
$fixtureRoot = sys_get_temp_dir() . '/studio-owner-discovery-' . bin2hex(random_bytes(4));
mkdir($fixtureRoot, 0777, true);
define('APP_ROOT', $fixtureRoot);

require_once $repoRoot . '/apps/Studio/Services/StudioOwnerDiscoveryService.php';
require_once $repoRoot . '/apps/Studio/Tools/HelperTool/Services/RepoTreeScannerService.php';
require_once $repoRoot . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureOwnerDiscoveryService.php';

use Apps\Studio\Services\StudioOwnerDiscoveryService;
use Apps\Studio\Tools\HelperTool\Services\RepoTreeScannerService;
use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureOwnerDiscoveryService;

$passed = 0;
$failed = 0;

function sod_assert(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }
    $failed++;
    echo "FAIL: {$label}\n";
}

function sod_file(string $path, string $content = ''): void
{
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0777, true);
    }
    file_put_contents($path, $content);
}

function sod_remove(string $path): void
{
    if (!is_dir($path)) {
        @unlink($path);
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $child = $path . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($child) && !is_link($child)) {
            sod_remove($child);
        } else {
            @unlink($child);
        }
    }
    @rmdir($path);
}

function sod_index(array $owners): array
{
    $index = [];
    foreach ($owners as $owner) {
        $key = (string)($owner['owner_key'] ?? '');
        if ($key !== '') {
            $index[$key] = $owner;
        }
    }
    return $index;
}

try {
    sod_file($fixtureRoot . '/apps/MyApp/Controllers/Foo.php', '<?php');
    sod_file($fixtureRoot . '/apps/MyApp/modules/MyModule/Services/Bar.php', '<?php');
    sod_file($fixtureRoot . '/apps/MenuOnly/menu.php', '<?php return [];');
    sod_file($fixtureRoot . '/apps/ContainerOnly/modules/DatabaseModule/Database/schema.php', '<?php');
    sod_file($fixtureRoot . '/apps/Platform/Services/AppPlatform.php', '<?php');
    sod_file($fixtureRoot . '/platform/Services/LegacyPlatform.php', '<?php');
    sod_file($fixtureRoot . '/plugins/MyPlugin/plugin.json', '{}');
    sod_file($fixtureRoot . '/apps/Generated/sample_app/sample_module/Services/GeneratedService.php', '<?php');
    sod_file($fixtureRoot . '/engineering/Studio/Resources/workspace.json', '{}');
    sod_file($fixtureRoot . '/engineering/Studio/CustomizationStudio/Resources/workspace.json', '{}');
    sod_file($fixtureRoot . '/apps/NotOwner/assets/lowercase.txt', 'x');
    sod_file($fixtureRoot . '/apps/cache/Controllers/Ignore.php', '<?php');

    $canonicalResult = StudioOwnerDiscoveryService::discover($fixtureRoot);
    $canonical = sod_index($canonicalResult['owners'] ?? []);

    sod_assert(($canonicalResult['diagnostics'][0]['code'] ?? '') === 'OWNER_KEY_COLLISION', 'duplicate Platform roots emit collision diagnostic');
    sod_assert(isset($canonical['MyApp']), 'canonical discovers app owner');
    sod_assert(isset($canonical['MyApp/MyModule']), 'canonical discovers module owner');
    sod_assert(isset($canonical['MenuOnly']), 'canonical discovers menu-only owner');
    sod_assert(isset($canonical['ContainerOnly/DatabaseModule']), 'canonical discovers module below non-owner app container');
    sod_assert(isset($canonical['Plugin/MyPlugin']), 'canonical discovers plugin owner');
    sod_assert(isset($canonical['Platform']), 'canonical discovers Platform owner');
    sod_assert(($canonical['Platform']['relative_path'] ?? '') === 'apps/Platform', 'canonical prioritizes apps/Platform over legacy platform root');
    sod_assert(isset($canonical['Generated/sample_app']), 'canonical discovers generated app owner');
    sod_assert(isset($canonical['Generated/sample_app/sample_module']), 'canonical discovers generated module owner');
    sod_assert(isset($canonical['EW/Studio']), 'canonical discovers engineering workspace');
    sod_assert(isset($canonical['EW/Studio/CustomizationStudio']), 'canonical discovers nested engineering workspace');
    sod_assert(!isset($canonical['NotOwner']), 'canonical ignores non-qualifying app directories');
    sod_assert(!isset($canonical['cache']), 'canonical excludes cache owner segment');
    sod_assert(($canonical['MyApp/MyModule']['parent_owner_key'] ?? '') === 'MyApp', 'canonical records module parent');
    sod_assert(($canonical['Plugin/MyPlugin']['source'] ?? '') === 'plugins', 'canonical records source');
    sod_assert(($canonical['MyApp']['root_path'] ?? '') === ($canonical['MyApp']['owner_root_path'] ?? null), 'canonical includes compatibility absolute path alias');

    $canonicalKeys = array_keys($canonical);
    $sortedKeys = $canonicalKeys;
    natcasesort($sortedKeys);
    sod_assert(array_values($sortedKeys) === $canonicalKeys, 'canonical owners are deterministically ordered');

    $repositoryResult = StudioOwnerDiscoveryService::discover($fixtureRoot, StudioOwnerDiscoveryService::PROFILE_REPOSITORY_SCANNER);
    $repository = sod_index($repositoryResult['owners'] ?? []);
    sod_assert(isset($repository['MyApp']), 'repository projection keeps app');
    sod_assert(isset($repository['MyApp/MyModule']), 'repository projection keeps module with eligible parent');
    sod_assert(isset($repository['Plugin/MyPlugin']), 'repository projection keeps plugin');
    sod_assert(isset($repository['Platform']), 'repository projection keeps Platform');
    sod_assert(isset($repository['EW/Studio']), 'repository projection keeps engineering workspace');
    sod_assert(!isset($repository['MenuOnly']), 'repository projection preserves exclusion of menu-only owner');
    sod_assert(!isset($repository['ContainerOnly/DatabaseModule']), 'repository projection preserves parent eligibility rule');
    sod_assert(!isset($repository['Generated/sample_app']), 'repository projection does not introduce nested generated owners');
    sod_assert(array_keys($repository['MyApp']) === ['owner_key', 'display_label', 'owner_type', 'root_path', 'relative_path'], 'repository projection preserves output fields');

    $repositoryAdapterOwners = RepoTreeScannerService::discoverOwners($fixtureRoot);
    sod_assert($repositoryAdapterOwners === ($repositoryResult['owners'] ?? []), 'Repository Scanner delegates with exact parity');

    $repositoryScan = RepoTreeScannerService::scanPath($fixtureRoot);
    sod_assert(($repositoryScan['owner_owners'] ?? []) === $repositoryAdapterOwners, 'Repository Scanner scan consumes canonical owners');

    $repositoryInspection = RepoTreeScannerService::inspectFileFromRoot('apps/MyApp/Controllers/Foo.php', $fixtureRoot);
    sod_assert(($repositoryInspection['owner_key'] ?? '') === 'MyApp', 'Repository Scanner inspection resolves canonical owner');

    $ownerStructureResult = StudioOwnerDiscoveryService::discover($fixtureRoot, StudioOwnerDiscoveryService::PROFILE_OWNER_STRUCTURE);
    $ownerStructure = sod_index($ownerStructureResult['owners'] ?? []);
    sod_assert(isset($ownerStructure['MyApp']), 'owner structure projection keeps app');
    sod_assert(isset($ownerStructure['MenuOnly']), 'owner structure projection keeps menu-only owner');
    sod_assert(isset($ownerStructure['ContainerOnly/DatabaseModule']), 'owner structure projection keeps module below non-owner container');
    sod_assert(!isset($ownerStructure['Plugin/MyPlugin']), 'owner structure projection excludes plugin');
    sod_assert(!isset($ownerStructure['EW/Studio']), 'owner structure projection excludes engineering workspace');
    sod_assert(!isset($ownerStructure['Generated/sample_app']), 'owner structure projection excludes nested generated owner');
    sod_assert(array_keys($ownerStructure['MyApp']) === ['owner_key', 'display_label', 'owner_root_path', 'owner_root_relative_path', 'owner_type'], 'owner structure projection preserves output fields');

    $adapterOwners = OwnerStructureOwnerDiscoveryService::discover();
    sod_assert($adapterOwners === ($ownerStructureResult['owners'] ?? []), 'Owner Structure adapter delegates with exact parity');

    $resolved = OwnerStructureOwnerDiscoveryService::resolve('MyApp/MyModule');
    sod_assert(($resolved['selected_owner_key'] ?? '') === 'MyApp/MyModule', 'Owner Structure adapter resolves known owner');
    sod_assert(($resolved['invalid_owner_key'] ?? true) === false, 'known owner is valid');

    $invalidKey = '.' . './bad';
    $invalid = OwnerStructureOwnerDiscoveryService::resolve($invalidKey);
    sod_assert(($invalid['error'] ?? '') === 'Invalid owner key. No owner was selected.', 'Owner Structure adapter preserves invalid-key message');

    $unknown = OwnerStructureOwnerDiscoveryService::resolve('UnknownOwner');
    sod_assert(($unknown['error'] ?? '') === 'Unknown owner key. No owner was selected.', 'Owner Structure adapter preserves unknown-key message');

    $missing = OwnerStructureOwnerDiscoveryService::resolve('');
    sod_assert(($missing['error'] ?? '') === 'No owner selected.', 'Owner Structure adapter preserves missing-key message');

    $capabilityResolve = StudioOwnerDiscoveryService::resolve('Plugin/MyPlugin', $canonicalResult['owners'] ?? []);
    sod_assert(($capabilityResolve['selected_owner_key'] ?? '') === 'Plugin/MyPlugin', 'canonical capability resolves plugin owner');
    sod_assert(StudioOwnerDiscoveryService::isValidOwnerKey('EW/Studio/CustomizationStudio'), 'canonical validator accepts nested workspace key');
    sod_assert(!StudioOwnerDiscoveryService::isValidOwnerKey($invalidKey), 'canonical validator rejects traversal');
} finally {
    sod_remove($fixtureRoot);
}

echo "Studio owner discovery capability probe: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);

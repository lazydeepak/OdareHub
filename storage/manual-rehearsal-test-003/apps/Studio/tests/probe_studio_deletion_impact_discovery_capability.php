<?php
declare(strict_types=1);

$repoRoot = dirname(__DIR__, 3);
require_once $repoRoot . '/apps/Studio/Services/StudioOwnerDiscoveryService.php';
require_once $repoRoot . '/apps/Studio/Services/StudioReferenceDiscoveryService.php';
require_once $repoRoot . '/apps/Studio/Services/StudioDeletionImpactDiscoveryService.php';
require_once $repoRoot . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionImpactService.php';

use Apps\Studio\Services\StudioDeletionImpactDiscoveryService;
use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureDeletionImpactService;

$passed = 0;
$failed = 0;
function did_assert(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }
    $failed++;
    fwrite(STDERR, "FAIL: {$label}\n");
}

function did_write(string $path, string $content): void
{
    $directory = dirname($path);
    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }
    file_put_contents($path, $content);
}

function did_remove_tree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $child = $path . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($child) && !is_link($child)) {
            did_remove_tree($child);
        } else {
            @unlink($child);
        }
    }
    @rmdir($path);
}

$fixtureRoot = sys_get_temp_dir() . '/studio-deletion-impact-' . bin2hex(random_bytes(4));
mkdir($fixtureRoot, 0777, true);

try {
    did_write($fixtureRoot . '/apps/Sales/manifest.json', "{}\n");
    did_write($fixtureRoot . '/apps/Sales/modules/Orders/manifest.json', "{}\n");
    did_write(
        $fixtureRoot . '/apps/Sales/modules/Orders/Services/SelfReference.php',
        "<?php\nuse Apps\\Sales\\Modules\\Orders\\OrderService;\n"
    );
    did_write($fixtureRoot . '/apps/Billing/manifest.json', "{}\n");
    did_write(
        $fixtureRoot . '/apps/Billing/Services/InvoiceService.php',
        "<?php\nuse Apps\\Sales\\Modules\\Orders\\OrderService;\n\$dependency = 'Sales/Orders';\n"
    );
    did_write($fixtureRoot . '/apps/Studio/manifest.json', "{}\n");
    did_write(
        $fixtureRoot . '/apps/Studio/Tools/AppBuilder.php',
        "<?php\n\$target = 'apps/Sales/modules/Orders';\n"
    );
    did_write(
        $fixtureRoot . '/docs/orders.md',
        "Historical owner path: apps/Sales/modules/Orders\n"
    );

    $blocked = StudioDeletionImpactDiscoveryService::discover([
        'target_type' => 'owner',
        'owner_key' => 'Sales/Orders',
    ], $fixtureRoot);

    did_assert(($blocked['status'] ?? '') === 'ok', 'owner deletion assessment succeeds');
    did_assert(($blocked['effect'] ?? '') === 'read', 'capability declares read-only effect');
    did_assert(($blocked['target']['target_type'] ?? '') === 'owner', 'owner target type preserved');
    did_assert(($blocked['target']['owner_key'] ?? '') === 'Sales/Orders', 'canonical owner key resolved');
    did_assert(($blocked['target']['target_path'] ?? '') === 'apps/Sales/modules/Orders', 'canonical owner path resolved');
    did_assert(($blocked['deletion_readiness'] ?? '') === 'blocked', 'runtime references block deletion');
    did_assert(($blocked['safe_to_delete'] ?? '') === 'no', 'blocked deletion is not safe');
    did_assert(in_array('RUNTIME_REFERENCES_EXIST', $blocked['blocking_reasons'] ?? [], true), 'runtime blocking reason emitted');
    did_assert((int)($blocked['summary']['reference_count'] ?? -1) === 4, 'path, owner-key, tooling, and docs references discovered');
    did_assert((int)($blocked['summary']['runtime_blockers'] ?? -1) === 2, 'namespace and logical owner dependencies block deletion');
    did_assert((int)($blocked['summary']['review_required'] ?? -1) === 1, 'Studio tooling dependency requires review');
    did_assert((int)($blocked['summary']['cleanup_only'] ?? -1) === 1, 'documentation dependency is cleanup-only');
    did_assert((int)($blocked['summary']['dependent_owner_count'] ?? -1) === 3, 'dependent owners and repository scope grouped');
    did_assert(($blocked['excluded_self_scope'] ?? '') === 'apps/Sales/modules/Orders', 'target subtree is excluded as self-contained');

    $referencePaths = array_map(
        static fn(array $reference): string => (string)($reference['file_path'] ?? ''),
        is_array($blocked['references'] ?? null) ? $blocked['references'] : []
    );
    did_assert(!in_array('apps/Sales/modules/Orders/Services/SelfReference.php', $referencePaths, true), 'self-contained references do not block deletion');

    $dependents = [];
    foreach (($blocked['dependent_owners'] ?? []) as $dependent) {
        if (!is_array($dependent)) {
            continue;
        }
        $key = (string)($dependent['owner_key'] ?? '');
        $dependents[$key !== '' ? $key : '@repository'] = $dependent;
    }
    did_assert(($dependents['Billing']['severity'] ?? '') === 'blocking', 'Billing is a blocking dependent owner');
    did_assert((int)($dependents['Billing']['reference_count'] ?? 0) === 2, 'Billing path and owner-key references aggregate');
    did_assert(($dependents['Studio']['severity'] ?? '') === 'review', 'Studio is a review dependent owner');
    did_assert(($dependents['@repository']['severity'] ?? '') === 'cleanup', 'documentation is retained as repository cleanup');

    did_write(
        $fixtureRoot . '/apps/Billing/Services/InvoiceService.php',
        "<?php\n\$dependency = 'unrelated';\n"
    );
    $needsReview = StudioDeletionImpactDiscoveryService::discover([
        'target_type' => 'owner',
        'owner_key' => 'Sales/Orders',
    ], $fixtureRoot);
    did_assert(($needsReview['deletion_readiness'] ?? '') === 'needs_review', 'tooling-only dependency requires review');
    did_assert(($needsReview['safe_to_delete'] ?? '') === 'review', 'review state is explicit');
    did_assert((int)($needsReview['summary']['reference_count'] ?? -1) === 2, 'tooling and docs evidence remain');

    did_write($fixtureRoot . '/apps/Studio/Tools/AppBuilder.php', "<?php\n\$target = 'unrelated';\n");
    $cleanup = StudioDeletionImpactDiscoveryService::discover([
        'target_type' => 'owner',
        'owner_key' => 'Sales/Orders',
    ], $fixtureRoot);
    did_assert(($cleanup['deletion_readiness'] ?? '') === 'ready_with_cleanup', 'docs-only impact is ready with cleanup');
    did_assert(($cleanup['safe_to_delete'] ?? '') === 'yes', 'cleanup-only impact is safe after cleanup planning');
    did_assert((int)($cleanup['summary']['cleanup_only'] ?? -1) === 1, 'cleanup-only reference count retained');

    did_write($fixtureRoot . '/docs/orders.md', "No owner reference remains.\n");
    $ready = StudioDeletionImpactDiscoveryService::discover([
        'target_type' => 'owner',
        'owner_key' => 'Sales/Orders',
    ], $fixtureRoot);
    did_assert(($ready['deletion_readiness'] ?? '') === 'ready', 'zero inbound references is ready');
    did_assert(($ready['safe_to_delete'] ?? '') === 'yes', 'ready target is safe');
    did_assert((int)($ready['summary']['reference_count'] ?? -1) === 0, 'ready target has zero inbound references');

    did_write($fixtureRoot . '/apps/Sales/modules/Orders/Resources/menu.php', "<?php\nreturn [];\n");
    $component = StudioDeletionImpactDiscoveryService::discover([
        'target_type' => 'component',
        'owner_key' => 'Sales/Orders',
        'target_path' => 'apps/Sales/modules/Orders/Resources/menu.php',
    ], $fixtureRoot);
    did_assert(($component['status'] ?? '') === 'ok', 'component deletion assessment succeeds');
    did_assert(($component['target']['target_type'] ?? '') === 'component', 'component target type preserved');
    did_assert(($component['deletion_readiness'] ?? '') === 'ready', 'unreferenced component is ready');

    $outside = StudioDeletionImpactDiscoveryService::discover([
        'target_type' => 'component',
        'owner_key' => 'Sales/Orders',
        'target_path' => 'apps/Billing/Services/InvoiceService.php',
    ], $fixtureRoot);
    did_assert(($outside['status'] ?? '') === 'error', 'component outside owner is rejected');
    did_assert(($outside['safe_to_delete'] ?? '') === 'no', 'invalid component target is not safe');

    $missingOwner = StudioDeletionImpactDiscoveryService::discover([
        'target_type' => 'owner',
        'owner_key' => 'Missing/Owner',
    ], $fixtureRoot);
    did_assert(($missingOwner['status'] ?? '') === 'error', 'unknown owner is rejected');
    did_assert(($missingOwner['deletion_readiness'] ?? '') === 'unknown', 'unknown owner readiness remains unknown');

    $missingComponent = StudioDeletionImpactDiscoveryService::discover([
        'target_type' => 'component',
        'owner_key' => 'Sales/Orders',
        'target_path' => 'apps/Sales/modules/Orders/Services/Missing.php',
    ], $fixtureRoot);
    did_assert(($missingComponent['status'] ?? '') === 'error', 'missing component is rejected');
    $missingCodes = array_map(
        static fn(array $diagnostic): string => (string)($diagnostic['code'] ?? ''),
        is_array($missingComponent['diagnostics'] ?? null) ? $missingComponent['diagnostics'] : []
    );
    did_assert(in_array('DELETION_TARGET_NOT_FOUND', $missingCodes, true), 'missing component diagnostic emitted');

    $adapter = OwnerStructureDeletionImpactService::assess([
        'owner_key' => 'Sales/Orders',
    ], [], $fixtureRoot);
    did_assert(($adapter['target']['owner_key'] ?? '') === 'Sales/Orders', 'Owner Structure adapter delegates selected owner');
    did_assert(($adapter['effect'] ?? '') === 'read', 'Owner Structure adapter preserves read effect');

    $missingEvidence = StudioDeletionImpactDiscoveryService::compose(
        ['target_type' => 'owner', 'owner_key' => 'Sales/Orders'],
        [
            'root_path' => $fixtureRoot,
            'owners' => [[
                'owner_key' => 'Sales/Orders',
                'display_label' => 'Sales / Orders',
                'owner_type' => 'module',
                'relative_path' => 'apps/Sales/modules/Orders',
            ]],
            'diagnostics' => [],
        ],
        ['status' => 'ok', 'items' => [], 'diagnostics' => []]
    );
    did_assert(($missingEvidence['deletion_readiness'] ?? '') === 'unknown', 'missing reference evidence cannot be interpreted as ready');
    did_assert(in_array('REFERENCE_DISCOVERY_INCOMPLETE', $missingEvidence['blocking_reasons'] ?? [], true), 'missing evidence blocks deletion readiness');
} finally {
    did_remove_tree($fixtureRoot);
}

echo "[probe] Studio deletion impact discovery: {$passed}/" . ($passed + $failed) . " assertions passed\n";
exit($failed === 0 ? 0 : 1);

<?php
declare(strict_types=1);

$repoRoot = dirname(__DIR__, 3);
require_once $repoRoot . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionImpactWorkspaceService.php';

use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureDeletionImpactWorkspaceService;

$passed = 0;
$failed = 0;

function osdiw_assert(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }
    $failed++;
    fwrite(STDERR, "ASSERTION FAILED: {$label}\n");
}

$owners = [
    ['owner_key' => 'Sales/Orders', 'owner_type' => 'module', 'relative_path' => 'apps/Sales/modules/Orders'],
    ['owner_key' => 'CRM/Leads', 'owner_type' => 'module', 'relative_path' => 'apps/CRM/modules/Leads'],
];

$idle = OwnerStructureDeletionImpactWorkspaceService::build($owners, 'Sales/Orders', false);
osdiw_assert(($idle['status'] ?? '') === 'idle', 'idle state is returned before explicit request');
osdiw_assert(($idle['requested'] ?? true) === false, 'idle state records request=false');
osdiw_assert(array_key_exists('assessment', $idle) && $idle['assessment'] === null, 'idle state does not invoke assessment');
osdiw_assert(($idle['selected_owner']['owner_key'] ?? '') === 'Sales/Orders', 'idle state resolves selected owner');

$invocations = 0;
$assessor = static function (array $owner, array $options, ?string $root) use (&$invocations): array {
    $invocations++;
    osdiw_assert(($owner['owner_key'] ?? '') === 'Sales/Orders', 'assessor receives selected owner');
    osdiw_assert($options === [], 'workspace adds no mutation options');
    osdiw_assert($root === '/fixture', 'workspace forwards explicit root');
    return [
        'status' => 'ok',
        'effect' => 'read',
        'summary' => [
            'reference_count' => 2,
            'files_referencing' => 2,
            'dependent_owner_count' => 1,
            'runtime_blockers' => 1,
            'review_required' => 1,
            'cleanup_only' => 0,
        ],
        'deletion_readiness' => 'blocked',
        'safe_to_delete' => 'no',
        'blocking_reasons' => ['runtime_references_exist', 'manual_review_required'],
        'dependent_owners' => [[
            'owner_key' => 'CRM/Leads',
            'owner_type' => 'module',
            'scope' => 'owner',
            'severity' => 'blocking',
            'reference_count' => 2,
            'files' => ['apps/CRM/modules/Leads/manifest.json'],
        ]],
        'references' => [[
            'file_path' => 'apps/CRM/modules/Leads/manifest.json',
            'line_number' => 4,
            'matched_pattern' => 'Sales/Orders',
            'relevance' => 'RUNTIME_BLOCKING',
            'impact_severity' => 'blocking',
        ]],
        'diagnostics' => [],
    ];
};

$ready = OwnerStructureDeletionImpactWorkspaceService::build($owners, 'Sales/Orders', true, '/fixture', $assessor);
osdiw_assert($invocations === 1, 'requested workspace invokes assessor exactly once');
osdiw_assert(($ready['status'] ?? '') === 'ready', 'successful capability result becomes ready workspace');
osdiw_assert(($ready['deletion_readiness'] ?? '') === 'blocked', 'readiness is preserved');
osdiw_assert(($ready['safe_to_delete'] ?? '') === 'no', 'safe-to-delete result is preserved');
osdiw_assert((int)($ready['summary']['runtime_blockers'] ?? 0) === 1, 'runtime blocker summary is preserved');
osdiw_assert(count($ready['blocking_reasons'] ?? []) === 2, 'blocking reasons are normalized');
osdiw_assert(($ready['dependent_owners'][0]['owner_key'] ?? '') === 'CRM/Leads', 'dependent owner evidence is preserved');
osdiw_assert(($ready['references'][0]['impact_severity'] ?? '') === 'blocking', 'reference severity is preserved');

$unknown = OwnerStructureDeletionImpactWorkspaceService::build($owners, 'Unknown/Owner', true, null, $assessor);
osdiw_assert(($unknown['status'] ?? '') === 'error', 'unknown owner fails closed');
osdiw_assert(($unknown['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_IMPACT_OWNER_UNAVAILABLE', 'unknown owner diagnostic is deterministic');
osdiw_assert($invocations === 1, 'unknown owner does not invoke assessor');

$throwing = static function (): array {
    throw new RuntimeException('fixture failure');
};
$error = OwnerStructureDeletionImpactWorkspaceService::build($owners, 'Sales/Orders', true, null, $throwing);
osdiw_assert(($error['status'] ?? '') === 'error', 'assessor exception is contained');
osdiw_assert(($error['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_IMPACT_WORKSPACE_FAILED', 'exception diagnostic is deterministic');
osdiw_assert(($error['diagnostics'][0]['message'] ?? '') === 'fixture failure', 'exception message is retained for diagnosis');

$invalid = OwnerStructureDeletionImpactWorkspaceService::build(
    $owners,
    'Sales/Orders',
    true,
    null,
    static fn (): string => 'invalid'
);
osdiw_assert(($invalid['status'] ?? '') === 'error', 'invalid assessor output fails closed');
osdiw_assert(($invalid['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_IMPACT_RESULT_INVALID', 'invalid result diagnostic is deterministic');

if ($failed > 0) {
    fwrite(STDERR, "[probe] Owner Structure deletion impact workspace FAILED: {$passed} passed, {$failed} failed\n");
    exit(1);
}

echo "[probe] Owner Structure deletion impact workspace: {$passed}/{$passed} assertions passed\n";

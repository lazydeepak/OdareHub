<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/platform/Security/EngineeringWorkspaceContentContract.php';
require_once APP_ROOT . '/platform/Security/EngineeringWorkspaceResolver.php';
require_once APP_ROOT . '/platform/Security/PlatformAuthority.php';
require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/EngineeringWorkspaceArtifactService.php';
require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/EngineeringWorkspaceInitializationService.php';
require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureMigrationPlannerService.php';
require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureReferenceDiscoveryService.php';
require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureOwnerDiscoveryService.php';
require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureScanSectionGuardService.php';

use Platform\Security\EngineeringWorkspaceContentContract;
use Apps\Studio\Tools\OwnerStructureScan\Services\EngineeringWorkspaceArtifactService;
use Apps\Studio\Tools\OwnerStructureScan\Services\EngineeringWorkspaceInitializationService;
use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureMigrationPlannerService;
use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureReferenceDiscoveryService;
use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureOwnerDiscoveryService;
use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureScanSectionGuardService;

$passed = 0;
$failed = 0;

function oss_assert_eq(mixed $expected, mixed $actual, string $label): void
{
    global $passed, $failed;
    if ($expected === $actual) {
        $passed++;
        return;
    }
    $failed++;
    echo "  FAIL [$label]: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . PHP_EOL;
}

function oss_assert_true(mixed $actual, string $label): void
{
    oss_assert_eq(true, $actual, $label);
}

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

echo "--- EngineeringWorkspaceArtifactService ---" . PHP_EOL;

// 1. Mapped owner with four valid documents
$a = EngineeringWorkspaceArtifactService::resolveArtifacts('Manufacturing/Products');
oss_assert_eq('Linked valid', $a['overall_state'], 'Manufacturing/Products overall state');
oss_assert_true($a['is_supported_workspace'], 'Manufacturing/Products supported');
oss_assert_eq(4, $a['valid_count'], 'Manufacturing/Products valid count');
oss_assert_eq(0, $a['missing_count'], 'Manufacturing/Products missing count');
oss_assert_eq(0, $a['invalid_count'], 'Manufacturing/Products invalid count');
oss_assert_eq(4, count($a['documents']), 'Manufacturing/Products document count');
oss_assert_eq('engineering/Manufacturing/Products', $a['canonical_relative_path'], 'Manufacturing/Products canonical path');
foreach ($a['documents'] as $doc) {
    oss_assert_eq('Linked valid', $doc['status'] ?? '', 'Manufacturing/Products doc is Linked valid: ' . ($doc['document_key'] ?? ''));
    oss_assert_eq('canonical_document_valid', $doc['reason_code'] ?? '', 'Manufacturing/Products reason code valid: ' . ($doc['document_key'] ?? ''));
}

// 1b. Mapped owner with one missing document
$canonicalPaths = [];
foreach (EngineeringWorkspaceContentContract::allowedDocumentKeys() as $docKey) {
    $canonicalPaths[$docKey] = EngineeringWorkspaceContentContract::documentPath('Manufacturing/Products', $docKey);
}
$oneMissingPaths = $canonicalPaths;
$oneMissingPaths['work'] = null;
$missingOne = EngineeringWorkspaceArtifactService::resolveWorkspaceArtifacts('Manufacturing/Products', $oneMissingPaths);
oss_assert_eq('Initialization required', $missingOne['overall_state'], 'one missing doc overall state');
oss_assert_eq(1, $missingOne['missing_count'], 'one missing doc missing count');
foreach ($missingOne['documents'] as $doc) {
    if (($doc['document_key'] ?? '') === 'work') {
        oss_assert_eq('Initialization required', $doc['status'] ?? '', 'work document missing state');
        oss_assert_eq('canonical_document_missing', $doc['reason_code'] ?? '', 'work document missing reason');
    } else {
        oss_assert_eq('Linked valid', $doc['status'] ?? '', 'other document remains linked valid: ' . ($doc['document_key'] ?? ''));
    }
}

// 1c. Mapped owner with one malformed document
$malformedPath = tempnam(sys_get_temp_dir(), 'ew-malformed-');
if ($malformedPath === false) {
    echo '  FAIL [temp malformed file]: could not create temporary file' . PHP_EOL;
    exit(1);
}
file_put_contents($malformedPath, "# Manufacturing Products\n\n## Wrong Section\n\nMalformed probe only.\n");
$malformedPaths = $canonicalPaths;
$malformedPaths['rules'] = $malformedPath;
$malformed = EngineeringWorkspaceArtifactService::resolveWorkspaceArtifacts('Manufacturing/Products', $malformedPaths);
@unlink($malformedPath);
oss_assert_eq('Contract repair required', $malformed['overall_state'], 'malformed doc overall state');
oss_assert_eq(1, $malformed['invalid_count'], 'malformed doc invalid count');
foreach ($malformed['documents'] as $doc) {
    if (($doc['document_key'] ?? '') === 'rules') {
        oss_assert_eq('Contract repair required', $doc['status'] ?? '', 'rules document repair state');
        oss_assert_eq('canonical_document_contract_failed', $doc['reason_code'] ?? '', 'rules document repair reason');
    }
}

// 2. Another mapped owner
$a2 = EngineeringWorkspaceArtifactService::resolveArtifacts('Plugin/Base');
oss_assert_eq('Linked valid', $a2['overall_state'], 'Plugin/Base overall state');
oss_assert_eq(4, $a2['valid_count'], 'Plugin/Base valid count');

// 3. Mapped Studio workspace
$a3 = EngineeringWorkspaceArtifactService::resolveArtifacts('Studio');
oss_assert_eq('Linked valid', $a3['overall_state'], 'Studio overall state');
oss_assert_eq('engineering/Studio', $a3['canonical_relative_path'], 'Studio canonical path');

// 4. All 8 supported workspace keys resolve as supported
foreach (EngineeringWorkspaceContentContract::supportedWorkspaceKeys() as $wk) {
    $a = EngineeringWorkspaceArtifactService::resolveArtifacts($wk);
    oss_assert_true($a['is_supported_workspace'], "$wk is supported");
}
echo "  All 8 supported workspaces resolve" . PHP_EOL;

// 5. Unmapped owner gives Not applicable with no fallback
$unsupported = ['Manufacturing', 'Manufacturing/Coverage', 'SBAIO', 'Payroll', 'Shell', 'Platform'];
foreach ($unsupported as $uk) {
    $a = EngineeringWorkspaceArtifactService::resolveArtifacts($uk);
    oss_assert_eq('Not applicable', $a['overall_state'], "$uk: Not applicable");
    oss_assert_eq(false, $a['is_supported_workspace'], "$uk: not supported");
    oss_assert_eq(0, count($a['documents']), "$uk: zero documents");
}
echo "  All unsupported owners return Not applicable with no fallback" . PHP_EOL;

// 5b. Invalid/no owner gives no selected owner and no default fallback.
$noOwner = OwnerStructureOwnerDiscoveryService::resolve('');
oss_assert_eq('', $noOwner['selected_owner_key'] ?? 'unexpected', 'no owner: no selected owner key');
oss_assert_eq(null, $noOwner['selected_owner'] ?? null, 'no owner: no selected owner object');
$invalidOwner = OwnerStructureOwnerDiscoveryService::resolve('../../app');
oss_assert_eq('', $invalidOwner['selected_owner_key'] ?? 'unexpected', 'invalid owner: no selected owner key');
oss_assert_eq(null, $invalidOwner['selected_owner'] ?? null, 'invalid owner: no selected owner object');
$unknownOwner = OwnerStructureOwnerDiscoveryService::resolve('No/SuchOwner');
oss_assert_eq('', $unknownOwner['selected_owner_key'] ?? 'unexpected', 'unknown owner: no selected owner key');
echo "  Invalid/no owner returns no fallback owner" . PHP_EOL;

// 5c. Artifact service failure is explicit, never Not applicable.
$failedArtifacts = EngineeringWorkspaceArtifactService::failedState('Manufacturing/Products', 'probe failure');
oss_assert_eq('Artifact resolution failed', $failedArtifacts['overall_state'], 'failure state is explicit');
oss_assert_eq('probe failure', $failedArtifacts['error'], 'failure state carries controlled error');

echo PHP_EOL . "--- MigrationPlannerService: Engineering Workspace Operations ---" . PHP_EOL;

// 6. Linked valid owner produces zero engineering workspace ops
$plan = OwnerStructureMigrationPlannerService::plan(
    ['owner_key' => 'Manufacturing/Products', 'owner_root_relative_path' => 'apps/Manufacturing/modules/Products'],
    [], [], ['findings' => []],
    EngineeringWorkspaceArtifactService::resolveArtifacts('Manufacturing/Products')
);
$ewOps = isset($plan['engineering_workspace_operations']) && is_array($plan['engineering_workspace_operations']) ? $plan['engineering_workspace_operations'] : [];
oss_assert_eq(0, count($ewOps), 'Manufacturing/Products: zero engineering workspace ops');
echo "  Linked valid owner has zero engineering workspace ops" . PHP_EOL;

// 7. Not applicable owner produces zero ops
$plan2 = OwnerStructureMigrationPlannerService::plan(
    ['owner_key' => 'Manufacturing/MaterialManagement', 'owner_root_relative_path' => 'apps/Manufacturing/modules/MaterialManagement'],
    [], [], ['findings' => []],
    EngineeringWorkspaceArtifactService::resolveArtifacts('Manufacturing/MaterialManagement')
);
$ewOps2 = isset($plan2['engineering_workspace_operations']) && is_array($plan2['engineering_workspace_operations']) ? $plan2['engineering_workspace_operations'] : [];
oss_assert_eq(0, count($ewOps2), 'MaterialManagement: zero engineering workspace ops');
echo "  Not applicable owner has zero engineering workspace ops" . PHP_EOL;

// 8. Missing documents produce CREATE operations
$missingArtifacts = [
    'workspace_key' => 'Studio/tools/TestWorkspace',
    'canonical_relative_path' => 'engineering/Studio/tools/TestWorkspace',
    'is_supported_workspace' => true,
    'documents' => [
        ['document_key' => 'overview', 'document_label' => 'Overview', 'canonical_filename' => 'overview.md', 'canonical_path' => null, 'exists' => false, 'state' => 'initialization_required'],
        ['document_key' => 'work', 'document_label' => 'Work', 'canonical_filename' => 'work.md', 'canonical_path' => null, 'exists' => false, 'state' => 'initialization_required'],
        ['document_key' => 'rules', 'document_label' => 'Rules', 'canonical_filename' => 'rules.md', 'canonical_path' => null, 'exists' => false, 'state' => 'initialization_required'],
        ['document_key' => 'decisions', 'document_label' => 'Decisions', 'canonical_filename' => 'decisions.md', 'canonical_path' => null, 'exists' => false, 'state' => 'initialization_required'],
    ],
    'valid_count' => 0,
    'missing_count' => 4,
    'invalid_count' => 0,
    'overall_state' => 'Initialization required',
];
// First without folder — should create folder op first
$missingPlan = OwnerStructureMigrationPlannerService::plan(
    ['owner_key' => 'Studio/tools/TestWorkspace', 'owner_root_relative_path' => 'apps/Studio/tools/TestWorkspace'],
    [], [], ['findings' => []],
    $missingArtifacts
);
$missingOps = isset($missingPlan['engineering_workspace_operations']) && is_array($missingPlan['engineering_workspace_operations']) ? $missingPlan['engineering_workspace_operations'] : [];
oss_assert_true(count($missingOps) > 0, 'Missing docs produce operations');
oss_assert_eq('CREATE_ENGINEERING_WORKSPACE_FOLDER', $missingOps[0]['operation_type'], 'First op is folder creation');
oss_assert_eq('CREATE_WORKSPACE_DOCUMENT_FROM_TEMPLATE', $missingOps[1]['operation_type'], 'Second op is document creation');
echo "  Missing documents produce CREATE operations in correct order" . PHP_EOL;

// 9. Review-only workspace operations are modeled without execution.
$reviewArtifacts = [
    'workspace_key' => 'Manufacturing/Products',
    'canonical_relative_path' => 'engineering/Manufacturing/Products',
    'is_supported_workspace' => true,
    'documents' => [
        [
            'document_key' => 'overview',
            'document_label' => 'Overview',
            'canonical_filename' => 'overview.md',
            'canonical_path' => 'engineering/Manufacturing/Products/overview.md',
            'current_path' => 'engineering/Manufacturing/Products/readme.md',
            'exists' => true,
            'state' => 'migration_required',
        ],
        [
            'document_key' => 'work',
            'document_label' => 'Work',
            'canonical_filename' => 'work.md',
            'canonical_path' => 'engineering/Manufacturing/Products/work.md',
            'exists' => true,
            'state' => 'contract_repair_required',
        ],
        [
            'document_key' => 'rules',
            'document_label' => 'Rules',
            'canonical_filename' => 'rules.md',
            'canonical_path' => 'engineering/Manufacturing/Products/rules.md',
            'current_path' => 'engineering/Manufacturing/Products/rules.old.md',
            'exists' => true,
            'state' => 'conflict_review_required',
        ],
    ],
    'valid_count' => 0,
    'missing_count' => 0,
    'invalid_count' => 1,
    'migration_count' => 1,
    'conflict_count' => 1,
    'overall_state' => 'Conflict review required',
];
$reviewPlan = OwnerStructureMigrationPlannerService::plan(
    ['owner_key' => 'Manufacturing/Products', 'owner_root_relative_path' => 'apps/Manufacturing/modules/Products'],
    [], [], ['findings' => []],
    $reviewArtifacts
);
$reviewOps = isset($reviewPlan['engineering_workspace_operations']) && is_array($reviewPlan['engineering_workspace_operations']) ? $reviewPlan['engineering_workspace_operations'] : [];
$reviewTypes = array_values(array_unique(array_map(static fn (array $op): string => (string)($op['operation_type'] ?? ''), $reviewOps)));
oss_assert_true(in_array('RENAME_WORKSPACE_DOCUMENT', $reviewTypes, true), 'planner emits RENAME_WORKSPACE_DOCUMENT');
oss_assert_true(in_array('REPAIR_WORKSPACE_DOCUMENT_CONTRACT', $reviewTypes, true), 'planner emits REPAIR_WORKSPACE_DOCUMENT_CONTRACT');
oss_assert_true(in_array('ARCHIVE_ORPHAN_WORKSPACE_DOCUMENT', $reviewTypes, true), 'planner emits ARCHIVE_ORPHAN_WORKSPACE_DOCUMENT');
foreach ($reviewOps as $op) {
    oss_assert_eq('no', $op['automatic'] ?? '', 'review-only workspace op is not automatic: ' . ($op['operation_type'] ?? ''));
}
echo "  Review-only workspace operation types are planned without execution" . PHP_EOL;

echo PHP_EOL . "--- EngineeringWorkspaceInitializationService ---" . PHP_EOL;

// 10. Initialization plan exposes only canonical create operations and a freshness fingerprint.
$initPlan = EngineeringWorkspaceInitializationService::buildInitializationPlan('Manufacturing/Products', [
    'engineering_workspace_operations' => [
        [
            'operation_id' => 'oss_111111111111',
            'operation_type' => 'CREATE_ENGINEERING_WORKSPACE_FOLDER',
            'target_path' => 'forged/path',
            'execution_order' => 1,
        ],
        [
            'operation_id' => 'oss_222222222222',
            'operation_type' => 'CREATE_WORKSPACE_DOCUMENT_FROM_TEMPLATE',
            'target_path' => 'engineering/Manufacturing/Products/work.md',
            'execution_order' => 2,
        ],
        [
            'operation_id' => 'oss_333333333333',
            'operation_type' => 'REPAIR_WORKSPACE_DOCUMENT_CONTRACT',
            'target_path' => 'engineering/Manufacturing/Products/rules.md',
            'execution_order' => 3,
        ],
        [
            'operation_id' => 'oss_444444444444',
            'operation_type' => 'CREATE_WORKSPACE_DOCUMENT_FROM_TEMPLATE',
            'target_path' => 'engineering/Manufacturing/Products/notes.md',
            'execution_order' => 4,
        ],
    ],
]);
$eligibleInitOps = isset($initPlan['eligible_operations']) && is_array($initPlan['eligible_operations']) ? $initPlan['eligible_operations'] : [];
oss_assert_eq(2, count($eligibleInitOps), 'initialization plan includes only allowed canonical create operations');
oss_assert_eq('engineering/Manufacturing/Products', $eligibleInitOps[0]['target_path'] ?? '', 'folder target is canonicalized server-side');
oss_assert_eq('engineering/Manufacturing/Products/work.md', $eligibleInitOps[1]['target_path'] ?? '', 'document target remains canonical');
oss_assert_eq('work', $eligibleInitOps[1]['document_type'] ?? '', 'document type is derived server-side from canonical target');
oss_assert_true(is_string($initPlan['fingerprint'] ?? null) && strlen((string)$initPlan['fingerprint']) === 64, 'initialization plan has sha256 fingerprint');

// 11. Unsafe execution requests fail without writes.
$adminActor = ['authority_role' => 'platform_admin', 'user_id' => 'probe'];
$invalidApply = EngineeringWorkspaceInitializationService::initialize('Manufacturing', ['oss_111111111111'], (string)$initPlan['fingerprint'], $adminActor);
oss_assert_eq(false, $invalidApply['ok'] ?? true, 'unmapped owner initialization is rejected');
oss_assert_eq('workspace_mapping_not_exact', $invalidApply['reason_code'] ?? '', 'unmapped owner rejection reason');
$noSelectionApply = EngineeringWorkspaceInitializationService::initialize('Manufacturing/Products', [], (string)$initPlan['fingerprint'], $adminActor);
oss_assert_eq(false, $noSelectionApply['ok'] ?? true, 'empty operation selection is rejected');
oss_assert_eq('no_operations_selected', $noSelectionApply['reason_code'] ?? '', 'empty selection rejection reason');
$unauthorizedApply = EngineeringWorkspaceInitializationService::initialize('Manufacturing/Products', ['oss_111111111111'], (string)$initPlan['fingerprint'], ['authority_role' => 'app_user']);
oss_assert_eq(false, $unauthorizedApply['ok'] ?? true, 'non-admin initialization is rejected');
oss_assert_eq('unauthorized', $unauthorizedApply['reason_code'] ?? '', 'non-admin rejection reason');
echo "  Initialization service rejects unsafe execution requests" . PHP_EOL;

// 12. Browser contract: POST submits only owner, fingerprint, CSRF, and selected operation IDs.
$previewSource = (string)file_get_contents(APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Views/preview.php');
$routesSource = (string)file_get_contents(APP_ROOT . '/apps/Studio/routes.php');
$controllerSource = (string)file_get_contents(APP_ROOT . '/apps/Studio/Controllers/StudioController.php');
oss_assert_true(str_contains($routesSource, "/apps/studio/tools/owner-structure-scan/initialize-workspace-artifacts"), 'dedicated Owner Structure Scan POST route exists');
oss_assert_true(str_contains($controllerSource, "Auth::requireCsrf((string)(\$_POST['csrf'] ?? '')"), 'initialize endpoint requires CSRF');
oss_assert_true(str_contains($previewSource, 'name="operation_ids[]"'), 'initialization UI uses explicit selected operation IDs');
oss_assert_true(str_contains($previewSource, 'name="plan_fingerprint"'), 'initialization UI submits server plan fingerprint');
oss_assert_true(!str_contains($previewSource, 'name="target_path"'), 'initialization UI does not submit target paths');
oss_assert_true(!str_contains($previewSource, 'Initialize All'), 'initialization UI has no initialize-all action');
echo "  Browser execution contract is constrained" . PHP_EOL;

// 13. Section isolation: artifact service failure renders a local safe error card.
$thrownArtifactSection = OwnerStructureScanSectionGuardService::safeSection(
    'engineering_workspace_artifacts',
    static function (): array {
        throw new RuntimeException('probe secret failure /tmp/owner-structure-path');
    },
    'EWS_ARTIFACT_SERVICE_FAILED',
    'Engineering Workspace artifact scan could not complete.'
);
oss_assert_eq('failed', $thrownArtifactSection['status'] ?? '', 'safeSection converts thrown artifact failure to failed section');
oss_assert_eq('EWS_ARTIFACT_SERVICE_FAILED', $thrownArtifactSection['error_code'] ?? '', 'safeSection preserves safe artifact error code');

$ownerStructureScanModel = [
    'scan_requested' => true,
    'scan_result' => [
        'owner_label' => 'Manufacturing / Products',
        'owner_type' => 'module',
        'owner_root_path' => 'apps/Manufacturing/modules/Products',
        'duration_ms' => 0,
        'total_folders' => 0,
        'total_files' => 0,
        'total_size' => 0,
    ],
    'classification' => [
        'groups' => [],
        'unknown_count' => 0,
    ],
    'contract_v2_diagnosis' => null,
    'migration_plan' => null,
    'reference_discovery' => null,
    'engineering_workspace_artifacts' => null,
    'engineering_workspace_artifacts_section' => $thrownArtifactSection,
    'migration_plan_section' => OwnerStructureScanSectionGuardService::emptySection('OSS_MIGRATION_PREREQUISITE_UNAVAILABLE', 'Migration planner is waiting for required scan sections.'),
    'engineering_workspace_initialization_section' => OwnerStructureScanSectionGuardService::emptySection('EWS_INITIALIZATION_PREREQUISITE_UNAVAILABLE', 'Initialization controls are disabled until artifact and planner sections complete.'),
    'reference_discovery_section' => OwnerStructureScanSectionGuardService::emptySection('OSS_REFERENCE_PREREQUISITE_UNAVAILABLE', 'Reference discovery is waiting for migration planner results.'),
    'owners' => [
        ['owner_key' => 'Manufacturing/Products', 'display_label' => 'Manufacturing / Products', 'owner_type' => 'module'],
    ],
    'selected_owner_key' => 'Manufacturing/Products',
    'owner_error' => '',
    'csrf' => 'probe',
];
ob_start();
require APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Views/preview.php';
$failedSectionHtml = (string)ob_get_clean();
oss_assert_true(str_contains($failedSectionHtml, 'Owner Structure Scan'), 'page shell renders when artifact section fails');
oss_assert_true(str_contains($failedSectionHtml, 'Engineering Workspace Artifacts'), 'artifact section title renders when artifact section fails');
oss_assert_true(str_contains($failedSectionHtml, 'EWS_ARTIFACT_SERVICE_FAILED'), 'artifact failure safe code renders');
oss_assert_true(str_contains($failedSectionHtml, 'Retry this section'), 'artifact failure retry action renders');
oss_assert_true(!str_contains($failedSectionHtml, 'probe secret failure'), 'artifact failure exception message is not exposed');
oss_assert_true(!str_contains($failedSectionHtml, '/tmp/owner-structure-path'), 'artifact failure filesystem path is not exposed');
echo "  Section isolation renders safe artifact failure card" . PHP_EOL;

echo PHP_EOL . "--- Reference Discovery: Relevance Categorization ---" . PHP_EOL;

// 14. Relevance categories via reflection on private method
$ref = new ReflectionMethod(OwnerStructureReferenceDiscoveryService::class, 'categorizeRelevance');
// PHP 8.1+ setAccessible has no effect but kept for compatibility
if (PHP_VERSION_ID < 80100) {
    $ref->setAccessible(true);
}

oss_assert_eq('ENGINEERING_WORKSPACE', $ref->invoke(null, 'engineering/Studio/overview.md', 'exact path', 'high'), 'engineering workspace file');
oss_assert_eq('ENGINEERING_WORKSPACE', $ref->invoke(null, 'engineering/Manufacturing/Products/work.md', 'basename', 'medium'), 'engineering work file');
oss_assert_eq('LOW_CONFIDENCE_TEXT', $ref->invoke(null, 'apps/Studio/tests/probe_foo.php', 'basename', 'medium'), 'test probe file');
oss_assert_eq('SELF_REFERENCE', $ref->invoke(null, 'AGENTS.md', 'basename', 'medium'), 'AGENTS.md reference');
oss_assert_eq('DOCUMENTATION_HISTORY', $ref->invoke(null, 'docs/architecture/foo.md', 'basename', 'medium'), 'docs path');
oss_assert_eq('OWNER_METADATA', $ref->invoke(null, 'apps/Manufacturing/Resources/lang/en.php', 'exact path', 'high'), 'locale resource');
oss_assert_eq('STUDIO_TOOLING', $ref->invoke(null, 'apps/Studio/Tools/Foo/bar.php', 'exact path', 'high'), 'Studio tooling');
oss_assert_eq('RUNTIME_BLOCKING', $ref->invoke(null, 'apps/Manufacturing/Controllers/ProductController.php', 'exact path', 'high'), 'runtime blocking');
oss_assert_eq('LOW_CONFIDENCE_TEXT', $ref->invoke(null, 'apps/Manufacturing/Controllers/ProductController.php', 'owner key', 'low'), 'low confidence text');
echo "  All 9 relevance categories resolve correctly" . PHP_EOL;

echo PHP_EOL . "=== Results: {$passed} passed, {$failed} failed ===" . PHP_EOL;

if ($failed > 0) {
    exit(1);
}

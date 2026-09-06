<?php
declare(strict_types=1);

require_once APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Services/StyleCompliancePresentationSummaryService.php';

/** @var array<string,mixed> $styleComplianceResult */
$styleComplianceResult = isset($styleComplianceResult) && is_array($styleComplianceResult) ? $styleComplianceResult : [];

$csrfToken = (string)($styleComplianceResult['csrf'] ?? '');
$scope = (string)($styleComplianceResult['scope'] ?? 'all_owners');
$ownerKey = (string)($styleComplianceResult['owner_key'] ?? '');
$workspace = (string)($styleComplianceResult['workspace'] ?? 'overview');
$workspace = $workspace === 'shell-inventory' ? 'shell-inventory' : 'overview';
$owners = isset($styleComplianceResult['owners']) && is_array($styleComplianceResult['owners']) ? $styleComplianceResult['owners'] : [];
$scopeDescriptor = isset($styleComplianceResult['scope_descriptor']) && is_array($styleComplianceResult['scope_descriptor']) ? $styleComplianceResult['scope_descriptor'] : [];
$summary = isset($styleComplianceResult['summary']) && is_array($styleComplianceResult['summary']) ? $styleComplianceResult['summary'] : [];
$tokens = isset($styleComplianceResult['tokens']) && is_array($styleComplianceResult['tokens']) ? $styleComplianceResult['tokens'] : [];
$candidates = isset($styleComplianceResult['candidates']) && is_array($styleComplianceResult['candidates']) ? $styleComplianceResult['candidates'] : [];
$boundary = isset($styleComplianceResult['boundary_findings']) && is_array($styleComplianceResult['boundary_findings']) ? $styleComplianceResult['boundary_findings'] : [];
$foundationConcerns = isset($styleComplianceResult['foundation_concerns']) && is_array($styleComplianceResult['foundation_concerns']) ? $styleComplianceResult['foundation_concerns'] : [];
$affected = isset($styleComplianceResult['affected_files']) && is_array($styleComplianceResult['affected_files']) ? $styleComplianceResult['affected_files'] : [];
$shellInventory = isset($styleComplianceResult['shell_inventory']) && is_array($styleComplianceResult['shell_inventory']) ? $styleComplianceResult['shell_inventory'] : [];
$themeRepairProposals = isset($styleComplianceResult['theme_repair_proposals']) && is_array($styleComplianceResult['theme_repair_proposals']) ? $styleComplianceResult['theme_repair_proposals'] : [];
$executionCapability = isset($styleComplianceResult['execution_capability']) && is_array($styleComplianceResult['execution_capability'])
    ? $styleComplianceResult['execution_capability']
    : (isset($themeRepairProposals['execution_capability']) && is_array($themeRepairProposals['execution_capability']) ? $themeRepairProposals['execution_capability'] : []);
$guardedExecutionEnabled = !empty($executionCapability['executor_enabled']);
$governance = isset($styleComplianceResult['governance']) && is_array($styleComplianceResult['governance']) ? $styleComplianceResult['governance'] : [];
$scanReady = !empty($styleComplianceResult['scan_ready']);
$scanInitialized = array_key_exists('scan_initialized', $styleComplianceResult) ? !empty($styleComplianceResult['scan_initialized']) : $scanReady;
$declarationCount = (int)($summary['in_scope'] ?? 0);
$awareCount = (int)($summary['in_scope_aware'] ?? 0);
$nonCompliantCount = (int)($summary['in_scope_unaware'] ?? 0);
$repairReadyCount = (int)($summary['repairable'] ?? 0);
$boundaryCount = (int)($summary['boundary_findings'] ?? 0);
$inScopeCount = (int)($summary['in_scope'] ?? 0);
$evidenceOnlyCount = (int)($summary['evidence_only'] ?? 0);
$complianceScore = $declarationCount > 0 ? (int)round(($awareCount / $declarationCount) * 100) : null;
$repairReadinessScore = $nonCompliantCount > 0 ? (int)round(($repairReadyCount / $nonCompliantCount) * 100) : null;
$presentationState = $scanInitialized && $scanReady
    ? \Apps\Studio\Tools\CustomizationStudio\Diagnose\StyleCompliance\Services\StyleCompliancePresentationSummaryService::build($styleComplianceResult, $scope, $ownerKey, $workspace)
    : [];
$presentationSummary = isset($presentationState['summary']) && is_array($presentationState['summary']) ? $presentationState['summary'] : [];
$primaryAffectedFile = '';
foreach ($affected as $affectedRow) {
    if (!is_array($affectedRow)) {
        continue;
    }
    $file = (string)($affectedRow['file'] ?? '');
    if ($file !== '') {
        $primaryAffectedFile = $file;
        break;
    }
}
$assessmentKey = 'assessment_status_compliant';
if ($declarationCount === 0) {
    $assessmentKey = 'assessment_not_assessed';
} elseif ($boundaryCount > 0) {
    $assessmentKey = 'assessment_status_blocked';
} elseif ($nonCompliantCount > 0) {
    $assessmentKey = 'assessment_status_attention';
}
$nextStepKey = 'assessment_next_clear';
if ($declarationCount === 0) {
    $nextStepKey = 'assessment_next_not_assessed';
} elseif ($nonCompliantCount > 0) {
    $nextStepKey = $repairReadyCount > 0 ? 'assessment_next_repair' : 'assessment_next_review';
}
$proposalByFile = [];
$proposalBlockedCount = 0;
$proposalLaneCounts = [
    'theme_scope_migration' => 0,
    'semantic_alias' => 0,
    'replace_literal_with_token' => 0,
    'semantic_token_correction' => 0,
    'manual_review' => 0,
    'blocked' => 0,
];
$darkVariantQueue = [];
$candidateTokenKeys = [];
foreach ($candidates as $candidate) {
    if (!is_array($candidate)) {
        continue;
    }
    $token = (string)($candidate['token'] ?? '');
    if ($token === '') {
        continue;
    }
    $candidateTokenKeys[$token] = true;
    $files = isset($candidate['files']) && is_array($candidate['files']) ? $candidate['files'] : [];
    $isBlocked = str_contains((string)($candidate['proposed_dark'] ?? ''), 'TODO');
    if ($isBlocked) {
        $proposalBlockedCount++;
        $proposalLaneCounts['blocked']++;
        $darkVariantQueue[] = [
            'token' => $token,
            'current_value' => (string)($candidate['current_value'] ?? ''),
            'proposed_semantic' => (string)($candidate['proposed_semantic'] ?? ''),
            'repair_action' => (string)($candidate['repair_action'] ?? 'semantic_alias'),
            'files' => $files,
        ];
    }
    $repairAction = (string)($candidate['repair_action'] ?? 'semantic_alias');
    if (!array_key_exists($repairAction, $proposalLaneCounts)) {
        $repairAction = 'semantic_alias';
    }
    $proposalLaneCounts[$repairAction]++;
    foreach ($files as $fileValue) {
        $file = (string)$fileValue;
        if ($file === '') {
            continue;
        }
        if (!isset($proposalByFile[$file])) {
            $proposalByFile[$file] = [
                'file' => $file,
                'replacements' => [],
                'blocked' => 0,
            ];
        }
        $proposalByFile[$file]['replacements'][] = [
            'token' => $token,
            'current_value' => (string)($candidate['current_value'] ?? ''),
            'proposed_semantic' => (string)($candidate['proposed_semantic'] ?? ''),
            'repair_action' => $repairAction,
            'confidence' => (string)($candidate['confidence'] ?? 'low'),
            'blocked' => $isBlocked,
        ];
        if ($isBlocked) {
            $proposalByFile[$file]['blocked']++;
        }
    }
}
$manualReviewTokens = [];
$manualReviewQueue = [];
foreach ($tokens as $tokenRow) {
    if (!is_array($tokenRow) || !empty($tokenRow['theme_aware'])) {
        continue;
    }
    // Skip evidence-only tokens (structural, definition) — they are not compliance issues
    $complianceScope = (string)($tokenRow['compliance_scope'] ?? 'in_scope');
    if ($complianceScope !== 'in_scope') {
        continue;
    }
    $token = (string)($tokenRow['token'] ?? '');
    if ($token === '' || isset($candidateTokenKeys[$token])) {
        continue;
    }
    $manualReviewTokens[$token] = true;
    if (!isset($manualReviewQueue[$token])) {
        $cls = isset($tokenRow['value_classification']) && is_array($tokenRow['value_classification'])
            ? $tokenRow['value_classification']
            : ['canonical' => '', 'type' => ''];
        $manualReviewQueue[$token] = [
            'token' => $token,
            'current_value' => (string)($cls['canonical'] ?? ''),
            'value_type' => (string)($cls['type'] ?? ''),
            'files' => [],
        ];
    }
    $file = (string)($tokenRow['file'] ?? '');
    if ($file !== '') {
        $manualReviewQueue[$token]['files'][$file] = true;
    }
}
$manualReviewQueue = array_values(array_map(static function (array $row): array {
    $files = array_keys($row['files']);
    sort($files, SORT_STRING);
    $row['files'] = $files;
    return $row;
}, $manualReviewQueue));
$proposalLaneCounts['manual_review'] = count($manualReviewTokens);
$proposalStatusKey = $proposalBlockedCount > 0 ? 'proposal_status_blocked' : 'proposal_status_ready';
$applyReadiness = [
    [
        'key' => 'readiness_boundary_clear',
        'state' => $boundaryCount === 0 ? 'pass' : 'blocked',
    ],
    [
        'key' => 'readiness_dark_variants',
        'state' => $proposalBlockedCount === 0 ? 'pass' : 'blocked',
    ],
    [
        'key' => 'readiness_manual_review',
        'state' => $proposalLaneCounts['manual_review'] === 0 ? 'pass' : 'blocked',
    ],
    [
        'key' => 'readiness_candidate_plan',
        'state' => $repairReadyCount > 0 || $nonCompliantCount === 0 ? 'pass' : 'blocked',
    ],
    [
        'key' => 'readiness_snapshot',
        'state' => 'pending',
    ],
    [
        'key' => 'readiness_approval',
        'state' => 'pending',
    ],
    [
        'key' => 'readiness_apply_route',
        'state' => $guardedExecutionEnabled ? 'pass' : 'blocked',
    ],
];

require __DIR__ . '/_locale.php';
// .sc-next-action-link compatibility marker for CSS ownership gate; runtime styles live in style-compliance.css.
?>
<div class="sc-container gs-tool-page">
    <?php require __DIR__ . '/Shared/_page_header.php'; ?>
    <?php require __DIR__ . '/Cockpit/_mission_banner.php'; ?>
    <?php require __DIR__ . '/Cockpit/_scanner.php'; ?>
    <?php require __DIR__ . '/Cockpit/_action_center.php'; ?>
    <?php require __DIR__ . '/Cockpit/_platform_health.php'; ?>
    <?php require __DIR__ . '/Shared/_results_mount.php'; ?>
</div>

<div class="sc-safety-bar gs-tool-status-bar"><?= e($sc($guardedExecutionEnabled ? 'safety_bar_guarded' : 'safety_bar_readonly')) ?></div>

<?php require __DIR__ . '/Shared/_scripts.php'; ?>

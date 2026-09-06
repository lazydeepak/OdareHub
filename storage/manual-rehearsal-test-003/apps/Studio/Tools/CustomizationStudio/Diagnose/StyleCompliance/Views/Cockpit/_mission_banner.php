<?php
declare(strict_types=1);

$ownersScanned = isset($styleComplianceResult['owners_scanned']) ? (int)$styleComplianceResult['owners_scanned'] : 0;
$filesScanned = isset($styleComplianceResult['files_scanned']) ? (int)$styleComplianceResult['files_scanned'] : 0;
$scanResultOwnerKey = isset($styleComplianceResult['owner_key']) ? (string)$styleComplianceResult['owner_key'] : '';
$themeRepairProposals = isset($themeRepairProposals) && is_array($themeRepairProposals) ? $themeRepairProposals : [];
$themeProposalSummary = isset($themeRepairProposals['summary']) && is_array($themeRepairProposals['summary']) ? $themeRepairProposals['summary'] : [];
$themeProposalQueues = isset($themeRepairProposals['queues']) && is_array($themeRepairProposals['queues']) ? $themeRepairProposals['queues'] : [];
$hasScan = !empty($scanInitialized) && !empty($scanReady);
$presentationSummary = isset($presentationSummary) && is_array($presentationSummary) ? $presentationSummary : [];
$repairCount = $hasScan ? (int)($presentationSummary['fixable_now'] ?? 0) : null;
$decisionCount = $hasScan ? (int)($presentationSummary['decision_backlog'] ?? 0) : 0;
$reviewTotal = $hasScan ? (int)($presentationSummary['review_only'] ?? 0) : null;
$scopeLabel = $scope === 'owner' ? $sourceScopeLabel('owner') : $sourceScopeLabel($scope);
$ownerDisplay = $scope === 'owner' && $ownerKey !== '' ? $ownerKey : ($scanResultOwnerKey !== '' ? $scanResultOwnerKey : $sc('shell_inventory_filter_all'));
$missionText = $sc('scan_header_ready');
if ($repairCount !== null && $repairCount > 0) {
    $missionText = sprintf($sc('scan_header_fixable'), $repairCount);
} elseif ($repairCount === 0 && $reviewTotal !== null && $reviewTotal > 0) {
    $missionText = $sc('scan_header_review');
}
?>
<?php require __DIR__ . '/../Shared/_toolbar.php'; ?>
<section class="sc-cockpit" data-sc-cockpit data-scan-status="<?= e($hasScan ? 'complete' : 'not_scanned') ?>" aria-label="Style Compliance operations cockpit">
    <header class="sc-cp-header">
        <div>
            <div class="sc-cp-title" data-sc-cockpit-mission><?= e($missionText) ?></div>
            <div class="sc-cp-sub"><?= e($sc('scan_header_subtitle')) ?></div>
        </div>
        <div class="sc-cp-badge" data-sc-cockpit-status aria-live="polite" role="status"><?= e($hasScan ? $sc('scan_status_complete') : $sc('scan_status_not_scanned')) ?></div>
        <output class="sc-cp-error" data-sc-cockpit-error aria-live="polite" role="alert" hidden></output>
    </header>

    <div class="sc-cp-layout">

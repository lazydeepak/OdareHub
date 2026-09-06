<?php
declare(strict_types=1);
?>
<output class="sc-prior-evidence" data-sc-prior-evidence aria-live="polite" aria-atomic="true" hidden></output>
<details class="sc-section-collapsible sc-investigation-fold" id="scInvestigationDetails">
    <summary>
        <span>
            <span class="sc-investigation-title">4. Detailed Investigation</span>
            <span class="sc-investigation-copy">Full findings, diagnostics, ready repairs, and detailed analysis.</span>
        </span>
        <span class="sc-investigation-state">
            <span class="sc-investigation-toggle-label">
                <span class="sc-investigation-closed-label gs-tool-closed-label">Click to expand</span>
                <span class="sc-investigation-open-label gs-tool-open-label">Click to collapse</span>
            </span>
            <span data-sc-cockpit-investigation-state><?= $scanInitialized ? 'Results are ready' : 'Awaiting scan' ?></span>
        </span>
    </summary>
    <div class="sc-investigation-body">
        <?php require __DIR__ . '/_workspace_tabs.php'; ?>
        <div id="style-compliance-results">
        <?php if (!$scanInitialized): ?>
            <div class="sc-empty"><?= e($sc('scan_not_started')) ?></div>
        <?php elseif (!$scanReady): ?>
            <div class="sc-empty"><?= e($sc('no_scope_root')) ?></div>
        <?php else: ?>
            <?php require __DIR__ . '/../_result_sections.php'; ?>
        <?php endif; ?>
        </div>
    </div>
</details>
<div class="sc-cockpit-footer">
    <div>
        Last scan: <span data-sc-cockpit-footer-last><?= $scanInitialized ? 'Just now' : '—' ?></span>
        <span aria-hidden="true"> • </span>
        Scope: <span data-sc-cockpit-scope-monitor><?= e($scope === 'owner' ? $sourceScopeLabel('owner') : $sourceScopeLabel($scope)) ?></span>
        <span aria-hidden="true"> • </span>
        Owners: <span data-sc-cockpit-owners><?= $scanInitialized && isset($styleComplianceResult['owners_scanned']) ? (int)$styleComplianceResult['owners_scanned'] : '—' ?></span>
        <span aria-hidden="true"> • </span>
        Files: <span data-sc-cockpit-files><?= $scanInitialized && isset($styleComplianceResult['files_scanned']) ? (int)$styleComplianceResult['files_scanned'] : '—' ?></span>
    </div>
    <a href="/apps/studio/tools/customization-studio/diagnose/style-compliance?scope=all_owners&amp;workspace=shell-inventory">Foundation Governance Inventory</a>
</div>

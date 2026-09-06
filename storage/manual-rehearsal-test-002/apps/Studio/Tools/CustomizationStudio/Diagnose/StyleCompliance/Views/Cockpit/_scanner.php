<?php
declare(strict_types=1);

$scanScopeTitle = $scope === 'owner' && $ownerDisplay !== '' ? $ownerDisplay : $scopeLabel;
$scanScopeDescKey = 'scan_scope_desc_' . (in_array($scope, ['owner', 'shell', 'theme'], true) ? $scope : 'all_owners');
$scanSummaryText = $hasScan
    ? str_replace(
        ['{files}', '{findings}', '{fixable_now}'],
        [(string)(int)$filesScanned, (string)(int)($presentationSummary['findings_total'] ?? 0), (string)(int)($presentationSummary['fixable_now'] ?? 0)],
        $sc('scan_console_complete_summary')
    )
    : $sc('scan_console_initial_summary');
$scanNextText = $hasScan ? $sc(((int)($presentationSummary['fixable_now'] ?? 0)) > 0 ? 'scan_console_next_fixable' : 'scan_console_next_review') : '';
?>
<div class="sc-cp-left">
    <div class="sc-cp-scanner-title">1. Scan</div>
    <div class="sc-scan-stage sc-scan-console">
        <div class="sc-scan-context">
            <div class="sc-scan-context-k"><?= e($sc('migration_source_scope')) ?></div>
            <h3 data-sc-scan-scope-title data-sc-cockpit-scope-monitor><?= e($scanScopeTitle) ?></h3>
            <p data-sc-scan-scope-desc><?= e($sc($scanScopeDescKey)) ?></p>
        </div>
        <div class="sc-scan-visual" data-sc-scan-visual>
            <div class="sc-scan-status" data-sc-cockpit-scan-mode data-sc-scan-status aria-live="polite"><?= e($hasScan ? $sc('scan_status_complete') : $sc('scan_status_not_scanned')) ?></div>
            <button type="submit" form="scScopeForm" id="scScanButton" class="sc-scan-btn sc-scan-orbit" aria-live="polite">
                <span class="sc-scan-orbit-ring" aria-hidden="true"></span>
                <span class="sc-scan-orbit-inner">
                    <span class="sc-scan-btn-label" data-sc-scan-label data-sc-cockpit-orbit-label><?= e($hasScan ? $sc('scan_button_complete') : $sc('scan_button_ready')) ?></span>
                </span>
            </button>
        </div>
        <div class="sc-scan-outcome">
            <p data-sc-scan-summary><?= e($scanSummaryText) ?></p>
            <p class="sc-scan-next" data-sc-scan-next<?= $scanNextText === '' ? ' hidden' : '' ?>><?= e($scanNextText) ?></p>
        </div>
    </div>
</div>

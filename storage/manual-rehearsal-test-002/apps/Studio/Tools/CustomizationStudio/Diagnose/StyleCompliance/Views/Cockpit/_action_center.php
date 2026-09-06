<?php
declare(strict_types=1);
?>
<div class="sc-cp-right">
    <div class="sc-cp-scanner-title">2. Results &amp; Action</div>
    <div class="sc-cp-hero <?= ($repairCount ?? 0) > 0 ? 'ready' : '' ?>" data-sc-cockpit-action>
        <div class="sc-cp-hero-main">
            <div class="sc-cp-hero-k" data-sc-cockpit-action-label><?= e(($repairCount ?? 0) > 0 ? $sc('dashboard_ready_to_repair') : 'Investigation') ?></div>
            <span class="sc-cp-hero-word" data-sc-cockpit-action-word><?= e(($repairCount ?? 0) > 0 ? 'Yes' : ($hasScan ? 'Review' : 'Pending')) ?></span>
            <div class="sc-cp-hero-sub" data-sc-cockpit-action-sub><?= e(($repairCount ?? 0) > 0 ? $sc($guardedExecutionEnabled ? 'verified_candidates_summary_body_guarded' : 'verified_candidates_summary_body_readonly') : ($hasScan ? $sc('action_center_no_repair_why') : 'Run scan to populate the All Owners cockpit.')) ?></div>
            <div class="sc-cp-hero-cta">
                <a href="<?= ($repairCount ?? 0) > 0 ? '#scVerifiedCandidates' : '#scInvestigationDetails' ?>" data-sc-cockpit-primary-cta><?= e(($repairCount ?? 0) > 0 ? 'Open Fixable Now' : 'Open Detailed Investigation') ?></a>
            </div>
        </div>
        <div class="sc-cp-hero-side" aria-label="Result totals">
            <div class="sc-cp-side-stat sc-cp-side-ready">
                <span class="ico" aria-hidden="true">✓</span>
                <div><strong data-sc-cockpit-ready><?= $repairCount !== null ? (int)$repairCount : '—' ?></strong><span><?= e($sc('dashboard_ready_to_repair')) ?></span></div>
            </div>
            <div class="sc-cp-side-stat sc-cp-side-review">
                <span class="ico" aria-hidden="true">!</span>
                <div><strong data-sc-cockpit-review><?= $reviewTotal !== null ? (int)$reviewTotal : '—' ?></strong><span><?= e($sc('dashboard_needs_review')) ?></span></div>
            </div>
            <div class="sc-cp-side-stat sc-cp-side-total">
                <span class="ico" aria-hidden="true">≡</span>
                <div><strong data-sc-cockpit-total><?= $hasScan ? (int)($presentationSummary['findings_total'] ?? 0) : '—' ?></strong><span><?= e($sc('dashboard_total_findings')) ?></span></div>
            </div>
            <div class="sc-cp-side-stat sc-cp-side-blocked">
                <span class="ico" aria-hidden="true">□</span>
                <div><strong data-sc-cockpit-blocked>—</strong><span>Blocked / no safe repair</span></div>
            </div>
        </div>
    </div>
    </div>

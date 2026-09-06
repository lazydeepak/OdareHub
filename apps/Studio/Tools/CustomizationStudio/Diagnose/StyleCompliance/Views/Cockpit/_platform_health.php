<?php
declare(strict_types=1);
?>
</div>
<section class="sc-cp-health" aria-label="Platform health summary">
    <div class="sc-cp-scanner-title">3. Platform Health Summary</div>
    <div class="sc-cp-metrics" aria-label="Cockpit health metrics">
        <div class="sc-cp-metric sc-cp-metric-total"><span class="sc-cp-metric-ico" aria-hidden="true">≡</span><div><div class="sc-cp-metric-n" data-sc-cockpit-total><?= $hasScan ? (int)($presentationSummary['findings_total'] ?? 0) : '—' ?></div><div class="sc-cp-metric-k"><?= e($sc('dashboard_total_findings')) ?></div></div></div>
        <div class="sc-cp-metric sc-cp-metric-review"><span class="sc-cp-metric-ico" aria-hidden="true">!</span><div><div class="sc-cp-metric-n" data-sc-cockpit-review><?= $reviewTotal !== null ? (int)$reviewTotal : '—' ?></div><div class="sc-cp-metric-k"><?= e($sc('dashboard_needs_review')) ?></div></div></div>
        <div class="sc-cp-metric sc-cp-metric-ready"><span class="sc-cp-metric-ico" aria-hidden="true">✓</span><div><div class="sc-cp-metric-n" data-sc-cockpit-ready><?= $repairCount !== null ? (int)$repairCount : '—' ?></div><div class="sc-cp-metric-k"><?= e($sc('dashboard_ready_to_repair')) ?></div></div></div>
        <div class="sc-cp-metric sc-cp-metric-decision"><span class="sc-cp-metric-ico" aria-hidden="true">□</span><div><div class="sc-cp-metric-n" data-sc-cockpit-decisions><?= $hasScan ? (int)$decisionCount : '—' ?></div><div class="sc-cp-metric-k">Decision backlog</div></div></div>
        <div class="sc-cp-metric sc-cp-metric-score"><span class="sc-cp-metric-ico" aria-hidden="true">✓</span><div><div class="sc-cp-metric-n" data-sc-cockpit-score><?= $hasScan && $complianceScore !== null ? (int)$complianceScore . '%' : '—' ?></div><div class="sc-cp-metric-k"><?= e($sc('dashboard_compliance_score')) ?></div></div></div>
    </div>
</section>
</section>

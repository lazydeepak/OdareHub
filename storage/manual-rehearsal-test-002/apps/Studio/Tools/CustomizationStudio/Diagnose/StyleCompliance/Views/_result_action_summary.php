<?php
declare(strict_types=1);

$detCount = (int)($themeProposalSummary['deterministic_future_apply'] ?? 0);
$accessCount = (int)($themeProposalSummary['accessibility_review_items'] ?? 0);
$reviewQueueForCounts = isset($themeProposalQueues['classification_and_accessibility_review']) && is_array($themeProposalQueues['classification_and_accessibility_review'])
    ? $themeProposalQueues['classification_and_accessibility_review']
    : [];
$classCount = count(array_filter($reviewQueueForCounts, static fn(array $proposal): bool => (string)($proposal['review_reason_code'] ?? '') !== 'accessibility_review'));
$manualQueueForCounts = isset($themeProposalQueues['manual_semantic_decisions']) && is_array($themeProposalQueues['manual_semantic_decisions'])
    ? $themeProposalQueues['manual_semantic_decisions']
    : [];
$manualCount = $manualQueueForCounts !== [] ? count($manualQueueForCounts) : (int)($themeProposalSummary['manual_semantic_decisions'] ?? 0);
$decisionBacklogTotal = $manualCount + $classCount;
$handoffCount = (int)($themeProposalSummary['future_effects_handoffs'] ?? 0);
$executionCapability = isset($executionCapability) && is_array($executionCapability) ? $executionCapability : [];
$guardedExecutionEnabled = !empty($executionCapability['executor_enabled']);
$primaryHref = '#scVerifiedCandidates';
$primaryTitle = $sc('verified_candidates_title');
$primaryCount = $detCount;
$primaryBody = $sc($guardedExecutionEnabled ? 'verified_candidates_summary_body_guarded' : 'verified_candidates_summary_body_readonly');
$primaryAction = $sc('inspect_candidates');
if ($detCount === 0) {
    if ($accessCount > 0) {
        $primaryHref = '#scAccessibilityReview';
        $primaryTitle = $sc('accessibility_review_title');
        $primaryCount = $accessCount;
        $primaryBody = $sc('accessibility_review_summary_body');
        $primaryAction = $sc('inspect_accessibility_findings');
    } elseif ($decisionBacklogTotal > 0) {
        $primaryHref = '#scDecisionBacklog';
        $primaryTitle = $sc('decision_backlog_title');
        $primaryCount = $decisionBacklogTotal;
        $primaryBody = $sc('decision_backlog_summary_body');
        $primaryAction = $sc('inspect_decision_backlog');
    } elseif ($handoffCount > 0) {
        $primaryHref = '#scEffectsHandoffs';
        $primaryTitle = $sc('effects_handoffs_title');
        $primaryCount = $handoffCount;
        $primaryBody = $sc('effects_handoffs_summary_body');
        $primaryAction = $sc('inspect_effects_handoffs');
    } else {
        $primaryHref = '#scDiagnostics';
        $primaryTitle = $sc('workspace_no_active_work_title');
        $primaryCount = 0;
        $primaryBody = $sc('workspace_no_active_work_body');
        $primaryAction = $sc('view_evidence');
    }
}
?>
<section class="sc-action-summary" aria-labelledby="scActionSummaryTitle">
    <div class="sc-primary-task">
        <p class="sc-section-kicker"><?= e($sc('workspace_next_work')) ?></p>
        <h3 id="scActionSummaryTitle"><?= e($primaryTitle) ?> · <?= (int)$primaryCount ?></h3>
        <p><?= e($primaryBody) ?></p>
        <a href="<?= e($primaryHref) ?>" class="sc-next-action-link gs-tool-action-link"><?= e($primaryAction) ?></a>
    </div>
    <div class="sc-task-queues" aria-label="<?= e($sc('workspace_secondary_queues')) ?>">
        <a href="#scAccessibilityReview" class="sc-task-queue sc-task-queue-urgent">
            <strong><?= e($sc('accessibility_review_title')) ?> · <?= (int)$accessCount ?></strong>
            <span><?= e($sc('accessibility_review_short')) ?></span>
        </a>
        <a href="#scDecisionBacklog" class="sc-task-queue">
            <strong><?= e($sc('decision_backlog_title')) ?> · <?= (int)$decisionBacklogTotal ?></strong>
            <span><?= e(sprintf($sc('decision_backlog_breakdown'), $manualCount, $classCount)) ?></span>
        </a>
        <a href="#scEffectsHandoffs" class="sc-task-queue">
            <strong><?= e($sc('effects_handoffs_title')) ?> · <?= (int)$handoffCount ?></strong>
            <span><?= e($sc('effects_handoffs_short')) ?></span>
        </a>
    </div>
</section>

<?php
declare(strict_types=1);

$fullReadyQueue = isset($themeProposalQueues['ready_for_future_guarded_apply']) && is_array($themeProposalQueues['ready_for_future_guarded_apply'])
    ? $themeProposalQueues['ready_for_future_guarded_apply']
    : [];
$fullReadyQueue = array_values(array_filter($fullReadyQueue, static function ($proposal): bool {
    return is_array($proposal)
        && \Apps\Studio\Tools\CustomizationStudio\Diagnose\StyleCompliance\Services\StyleComplianceDeterministicFixOneService::eligibility($proposal)['state'] === 'ready';
}));
$cardLimit = 5;
$totalCap = 100;
$readyQueue = array_slice($fullReadyQueue, 0, $totalCap);
$readyVisible = array_slice($readyQueue, 0, $cardLimit);
$remainingCount = count($readyQueue) - count($readyVisible);
$singleReadyOwner = '';
$seenOwners = 0;
foreach ($readyVisible as $proposal) {
    if (!is_array($proposal)) continue;
    $owner = (string)($proposal['source_owner'] ?? '');
    if ($owner !== '') {
        if ($singleReadyOwner === '') {
            $singleReadyOwner = $owner;
            $seenOwners = 1;
        } elseif ($singleReadyOwner !== $owner) {
            $singleReadyOwner = '';
            break;
        }
    }
}
$renderCard = static function (array $proposal) use ($sc, $confidenceClass, $confidenceLabel, $singleReadyOwner): void {
    $proposalId = (string)($proposal['proposal_id'] ?? '');
    $detailsId = 'sc-candidate-' . md5($proposalId !== '' ? $proposalId : json_encode($proposal));
    $owner = (string)($proposal['source_owner'] ?? '');
    $filePath = (string)($proposal['file_path'] ?? '');
    $line = (int)($proposal['line'] ?? 0);
    $selector = (string)($proposal['selector'] ?? '');
    $property = (string)($proposal['property'] ?? '');
    $detection = (string)($proposal['detection_confidence'] ?? $proposal['confidence'] ?? 'low');
    $mapping = (string)($proposal['semantic_mapping_confidence'] ?? 'unknown');
    $eligibility = (string)($proposal['future_apply_eligibility'] ?? 'not_applicable');
    $preconditions = isset($proposal['apply_preconditions']) && is_array($proposal['apply_preconditions']) ? $proposal['apply_preconditions'] : [];
    $preconditions = array_values(array_filter($preconditions, static fn(mixed $precondition): bool => true));
    $preconditionLabels = array_map(static function (mixed $precondition) use ($sc): string {
        $key = (string)$precondition;
        $label = $key !== '' ? $sc($key) : '';
        if ($label !== '' && $label !== $key) {
            return $label;
        }
        return ucwords(str_replace('_', ' ', $key));
    }, $preconditions);
    $blockedBy = isset($proposal['blocked_by']) && is_array($proposal['blocked_by']) ? $proposal['blocked_by'] : [];
    $location = '';
    if ($singleReadyOwner === '' && $owner !== '') {
        $location = $owner . ' · ';
    }
    $location .= $filePath;
    ?>
    <div class="sc-queue-card">
        <div class="sc-queue-card-main">
            <div class="sc-queue-card-location">
                <strong><?= e($location) ?></strong>
                <span class="sc-queue-card-selector"><?= e($selector) ?><?= $property !== '' ? ' → ' . e($property) : '' ?></span>
            </div>
            <div class="sc-queue-card-values">
                <code class="sc-queue-card-old"><?= e((string)($proposal['current_value'] ?? '')) ?></code>
                <span class="sc-queue-card-arrow">→</span>
                <code class="sc-queue-card-new"><?= e((string)($proposal['replacement_value'] ?? '')) ?></code>
            </div>
            <div class="sc-queue-card-actions">
                <span class="sc-readiness-pass">Fixable Now</span>
                <button type="button" class="sc-fix-one" data-proposal-id="<?= e($proposalId) ?>">Fix</button>
                <span class="sc-readiness-inline" data-fix-one-status>Ready</span>
            </div>
            <div class="sc-readiness-result" data-fix-one-result hidden></div>
        </div>
        <details class="sc-queue-card-evidence">
            <summary><?= e($sc('view_evidence')) ?></summary>
            <div class="sc-detail-columns">
                <section>
                    <h4><?= e($sc('candidate_detail_trusted')) ?></h4>
                    <dl class="sc-evidence-dl">
                        <dt><?= e($sc('semantic_mapping_confidence_label')) ?></dt>
                        <dd><?= e($sc('semantic_mapping_confidence_' . $mapping)) ?></dd>
                        <dt><?= e($sc('detection_confidence_label')) ?></dt>
                        <dd><span class="<?= e($confidenceClass($detection)) ?>"><?= e($confidenceLabel($detection)) ?></span></dd>
                        <dt><?= e($sc('replacement_rationale')) ?></dt>
                        <dd><?= e((string)($proposal['replacement_rationale'] ?? '')) ?></dd>
                    </dl>
                </section>
                <section>
                    <h4><?= e($sc('candidate_detail_source')) ?></h4>
                    <dl class="sc-evidence-dl">
                        <dt><?= e($sc('col_file')) ?></dt><dd><code><?= e($filePath) ?></code></dd>
                        <dt><?= e($sc('evidence_line')) ?></dt><dd><?= (int)$line ?></dd>
                        <dt><?= e($sc('col_selector')) ?></dt><dd><code><?= e($selector) ?></code></dd>
                        <dt><?= e($sc('col_property')) ?></dt><dd><code><?= e($property) ?></code></dd>
                    </dl>
                </section>
                <section>
                    <h4><?= e($sc('candidate_detail_safeguards')) ?></h4>
                    <dl class="sc-evidence-dl">
                        <dt><?= e($sc('future_apply_eligibility_label')) ?></dt>
                        <dd><?= e($sc('future_apply_eligibility_' . $eligibility)) ?></dd>
                        <dt><?= e($sc('proposal_preconditions')) ?></dt>
                        <dd><?= e($preconditionLabels === [] ? $sc('none') : implode(', ', $preconditionLabels)) ?></dd>
                        <dt><?= e($sc('proposal_blockers')) ?></dt>
                        <dd><?= e($blockedBy === [] ? $sc('none') : implode(', ', array_map('strval', $blockedBy))) ?></dd>
                    </dl>
                </section>
                <section>
                    <h4><?= e($sc('candidate_detail_technical')) ?></h4>
                    <dl class="sc-evidence-dl">
                        <dt><?= e($sc('proposal_id_label')) ?></dt><dd><code><?= e($proposalId) ?></code></dd>
                        <dt><?= e($sc('replacement_token')) ?></dt><dd><code><?= e((string)($proposal['replacement_token'] ?? '')) ?></code></dd>
                        <dt><?= e($sc('migration_state')) ?></dt><dd><?= e((string)($proposal['migration_state'] ?? '')) ?></dd>
                        <dt><?= e($sc('migration_confidence')) ?></dt><dd><?= e($confidenceLabel((string)($proposal['confidence'] ?? 'low'))) ?></dd>
                    </dl>
                </section>
            </div>
        </details>
    </div>
    <?php
};
?>
<section class="sc-section sc-task-section" id="scVerifiedCandidates" aria-labelledby="scVerifiedCandidatesTitle">
    <div class="sc-section-heading">
        <div>
            <h3 id="scVerifiedCandidatesTitle">Fixable Now</h3>
            <p class="sc-proposal-meta">Deterministic V1 fixes only. Each action rewrites one exact CSS declaration and immediately re-scans the affected owner.</p>
        </div>
        <div class="sc-section-count" data-fixable-now-count><?= (int)count($readyQueue) ?></div>
    </div>
    <?php if ($readyQueue === []): ?>
        <div class="sc-empty">No deterministic Fix One rows are available for this scan.</div>
    <?php else: ?>
        <?php
        $cardIndex = 0;
        foreach ($readyQueue as $proposal):
            $proposal = is_array($proposal) ? $proposal : [];
            $isExtra = $cardIndex >= $cardLimit;
            $cardIndex++;
        ?>
            <div class="<?= $isExtra ? 'sc-queue-card-extra' : '' ?>"<?= $isExtra ? ' hidden' : '' ?>>
                <?php $renderCard($proposal); ?>
            </div>
        <?php endforeach; ?>
        <?php if ($remainingCount > 0): ?>
            <div class="sc-remaining-control">
                <button type="button" class="sc-remaining-toggle" data-show="<?= e($sc('show_remaining_cards')) ?>" data-hide="<?= e($sc('hide_remaining_cards')) ?>">
                    <?= str_replace(['{count}'], [(int)$remainingCount], $sc('show_remaining_cards')) ?>
                </button>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

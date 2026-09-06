<?php
declare(strict_types=1);

$reviewQueue = isset($themeProposalQueues['classification_and_accessibility_review']) && is_array($themeProposalQueues['classification_and_accessibility_review'])
    ? $themeProposalQueues['classification_and_accessibility_review']
    : [];
$accessibilityQueue = array_values(array_filter($reviewQueue, static fn(array $proposal): bool => (string)($proposal['review_reason_code'] ?? '') === 'accessibility_review'));
$classificationQueue = array_values(array_filter($reviewQueue, static fn(array $proposal): bool => (string)($proposal['review_reason_code'] ?? '') !== 'accessibility_review'));
$manualQueue = isset($themeProposalQueues['manual_semantic_decisions']) && is_array($themeProposalQueues['manual_semantic_decisions'])
    ? $themeProposalQueues['manual_semantic_decisions']
    : [];
$handoffQueue = isset($themeProposalQueues['future_tool_handoffs']) && is_array($themeProposalQueues['future_tool_handoffs'])
    ? $themeProposalQueues['future_tool_handoffs']
    : [];
$decisionItems = array_values(array_merge($manualQueue, $classificationQueue));
$manualCount = count($manualQueue);
$classificationCount = count($classificationQueue);
$decisionBacklogTotal = count($decisionItems);
$sourceAreaLabel = static function (array $proposal) use ($sc): string {
    $file = (string)($proposal['file_path'] ?? '');
    if (str_contains($file, 'ReportDesigner') || str_contains($file, 'report-designer')) {
        return $sc('source_area_report_designer');
    }
    if (str_contains($file, 'Localization') || str_contains($file, 'localization')) {
        return $sc('source_area_localization_studio');
    }
    if (str_contains($file, 'CssTokenEditor') || str_contains($file, 'css-token')) {
        return $sc('source_area_css_token_editor');
    }
    return $sc('source_area_other');
};
$decisionFamilyLabel = static function (array $proposal) use ($sc): string {
    $property = strtolower((string)($proposal['property'] ?? ''));
    $selector = strtolower((string)($proposal['selector'] ?? ''));
    if (str_contains($selector, 'danger') || str_contains($selector, 'warning') || str_contains($selector, 'success') || str_contains($selector, 'error') || str_contains($selector, 'status')) {
        return $sc('decision_family_status');
    }
    if ($property === 'color' || str_contains($property, 'text')) {
        return $sc('decision_family_text');
    }
    if ($property === 'background' || $property === 'background-color') {
        return $sc('decision_family_background');
    }
    if (str_starts_with($property, 'border')) {
        return $sc('decision_family_border');
    }
    if (in_array($property, ['box-shadow', 'filter', 'opacity', 'transform'], true)) {
        return $sc('decision_family_effects');
    }
    return $sc('decision_family_other');
};
$decisionGroups = [];
foreach ($decisionItems as $proposal) {
    if (!is_array($proposal)) {
        continue;
    }
    $area = $sourceAreaLabel($proposal);
    $family = $decisionFamilyLabel($proposal);
    if (!isset($decisionGroups[$area])) {
        $decisionGroups[$area] = ['count' => 0, 'families' => []];
    }
    $decisionGroups[$area]['count']++;
    if (!isset($decisionGroups[$area]['families'][$family])) {
        $decisionGroups[$area]['families'][$family] = [];
    }
    $decisionGroups[$area]['families'][$family][] = $proposal;
}
uksort($decisionGroups, static function (string $a, string $b): int {
    $order = [
        'Report Designer' => 0,
        'Localization Studio' => 1,
        'CSS Token Editor' => 2,
        'Other Studio files' => 3,
    ];
    return ($order[$a] ?? 99) <=> ($order[$b] ?? 99) ?: strcmp($a, $b);
});
$renderDecisionRows = static function (array $items) use ($sc, $confidenceClass, $confidenceLabel, $planQueueLimit): void {
    $visible = array_slice($items, 0, $planQueueLimit);
    ?>
    <div class="sc-table-scroll">
        <table class="sc-table gs-tool-scroll-table sc-decision-table">
            <thead>
                <tr>
                    <th><?= e($sc('col_selector')) ?></th>
                    <th><?= e($sc('col_property')) ?></th>
                    <th><?= e($sc('col_current')) ?></th>
                    <th><?= e($sc('why_automation_stopped')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($visible as $proposal): ?>
                    <?php
                    $proposal = is_array($proposal) ? $proposal : [];
                    $detection = (string)($proposal['detection_confidence'] ?? $proposal['confidence'] ?? 'low');
                    $reason = (string)($proposal['review_reason_code'] ?? 'semantic_mapping_required');
                    ?>
                    <tr>
                        <td><code><?= e((string)($proposal['selector'] ?? '')) ?></code></td>
                        <td><code><?= e((string)($proposal['property'] ?? '')) ?></code></td>
                        <td><code><?= e((string)($proposal['current_value'] ?? '')) ?></code></td>
                        <td>
                            <?= e($sc('semantic_role_not_proven')) ?>
                            <details>
                                <summary><?= e($sc('view_evidence')) ?></summary>
                                <dl class="sc-evidence-dl">
                                    <dt><?= e($sc('proposal_id_label')) ?></dt><dd><code><?= e((string)($proposal['proposal_id'] ?? '')) ?></code></dd>
                                    <dt><?= e($sc('col_file')) ?></dt><dd><code><?= e((string)($proposal['file_path'] ?? '')) ?></code></dd>
                                    <dt><?= e($sc('evidence_line')) ?></dt><dd><?= (int)($proposal['line'] ?? 0) ?></dd>
                                    <dt><?= e($sc('confidence_model')) ?></dt><dd><span class="<?= e($confidenceClass($detection)) ?>"><?= e($confidenceLabel($detection)) ?></span></dd>
                                    <dt><?= e($sc('raw_reason_code')) ?></dt><dd><code><?= e($reason) ?></code></dd>
                                    <dt><?= e($sc('future_apply_eligibility_label')) ?></dt><dd><?= e($sc('future_apply_eligibility_' . (string)($proposal['future_apply_eligibility'] ?? 'not_applicable'))) ?></dd>
                                </dl>
                            </details>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (count($items) > count($visible)): ?>
        <div class="sc-empty">
            <?= str_replace(['{shown}', '{total}'], [(int)count($visible), (int)count($items)], $sc('showing_manual_decisions')) ?>
        </div>
    <?php endif; ?>
    <?php
};
?>
<section class="sc-section" id="scReviewOnly">
    <h3>Review Only</h3>
    <p class="sc-proposal-meta">These findings are informational in V1 and cannot be fixed from this tool.</p>
</section>
<details class="sc-section-collapsible" id="scAccessibilityReview">
    <summary>
        <span class="sc-section-collapsible-heading"><?= e($sc('accessibility_review_title')) ?></span>
        <span class="sc-section-count"><?= (int)count($accessibilityQueue) ?></span>
    </summary>
    <?php if ($accessibilityQueue === []): ?>
        <div class="sc-empty"><?= e($sc('accessibility_review_empty')) ?></div>
    <?php else: ?>
        <p class="sc-proposal-meta"><?= e(sprintf($sc('accessibility_review_panel_body'), count($accessibilityQueue))) ?></p>
        <?php $renderDecisionRows($accessibilityQueue); ?>
    <?php endif; ?>
</details>

<details class="sc-section-collapsible" id="scDecisionBacklog">
    <summary>
        <span class="sc-section-collapsible-heading"><?= e($sc('decision_backlog_title')) ?></span>
        <span class="sc-section-count"><?= (int)$decisionBacklogTotal ?></span>
        <span class="sc-section-collapsible-meta"><?= e(sprintf($sc('semantic_decisions_count'), $manualCount)) ?> · <?= e(sprintf($sc('classification_reviews_count'), $classificationCount)) ?></span>
    </summary>
    <?php if ($decisionItems === []): ?>
        <div class="sc-empty"><?= e($sc('decision_backlog_empty')) ?></div>
    <?php else: ?>
        <p class="sc-proposal-meta"><?= e($sc('decision_backlog_subtitle')) ?></p>
        <p class="sc-proposal-meta sc-muted-notice"><?= e($sc('decision_backlog_readonly')) ?></p>
        <div class="sc-decision-groups">
            <?php foreach ($decisionGroups as $area => $group): ?>
                <?php
                $families = isset($group['families']) && is_array($group['families']) ? $group['families'] : [];
                $familyLabels = array_map(static fn(string $family, array $rows): string => $family . ' (' . count($rows) . ')', array_keys($families), $families);
                ?>
                <details class="sc-decision-group">
                    <summary>
                        <span class="sc-file-group-path"><?= e($area) ?></span>
                        <span class="sc-file-group-count"><?= e(sprintf($sc('count_badge'), (int)($group['count'] ?? 0))) ?></span>
                        <span class="sc-family-preview"><?= e(implode(' · ', array_slice($familyLabels, 0, 3))) ?></span>
                    </summary>
                    <p class="sc-proposal-meta"><?= e($sc('semantic_role_not_proven')) ?></p>
                    <?php foreach ($families as $family => $familyItems): ?>
                        <details class="sc-file-group">
                            <summary><?= e($family) ?> <?= e(sprintf($sc('count_badge'), count($familyItems))) ?></summary>
                            <?php $renderDecisionRows($familyItems); ?>
                        </details>
                    <?php endforeach; ?>
                </details>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</details>

<details class="sc-section-collapsible" id="scEffectsHandoffs">
    <summary>
        <span class="sc-section-collapsible-heading"><?= e($sc('effects_handoffs_title')) ?></span>
        <span class="sc-section-count"><?= (int)count($handoffQueue) ?></span>
    </summary>
    <a href="/apps/studio/tools/customization-studio/effects" class="sc-next-action-link gs-tool-action-link"><?= e($sc('open_special_effects')) ?></a>
    <p class="sc-proposal-meta"><?= e(sprintf($sc('effects_handoffs_panel_body'), count($handoffQueue))) ?></p>
    <?php if ($handoffQueue === []): ?>
        <div class="sc-empty"><?= e($sc('effects_handoffs_empty')) ?></div>
    <?php else: ?>
        <?php $renderDecisionRows($handoffQueue); ?>
    <?php endif; ?>
</details>

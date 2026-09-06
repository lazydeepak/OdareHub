        <?php
        /* Group tokens by scan_category and repair lane for inventory */
        $groupedTokens = [];
        $categoryOrder = ['token_consumer', 'visual_literal', 'token_definition', 'structural_out_of_scope', 'dynamic_unsupported', 'print_pdf', 'effect_candidate'];
        $workflowOrder = ['theme', 'shell_foundation', 'special_effect', 'structural', 'print_pdf', 'unsupported'];
        $workflowGroupedTokens = [];
        foreach ($tokens as $row) {
            if (!is_array($row)) continue;
            $cat = (string)($row['scan_category'] ?? '');
            if (!isset($groupedTokens[$cat])) $groupedTokens[$cat] = [];
            $groupedTokens[$cat][] = $row;
            $domain = (string)($row['style_domain'] ?? 'owner_surface');
            if (!isset($workflowGroupedTokens[$domain])) $workflowGroupedTokens[$domain] = [];
            $workflowGroupedTokens[$domain][] = $row;
        }
        /* Separate structural tokens for out-of-scope evidence section */
        $structuralTokens = isset($groupedTokens['structural_out_of_scope']) ? $groupedTokens['structural_out_of_scope'] : [];
        $tokenDisplayLimit = 250;
        $structuralDisplayLimit = 150;
        $candidateDisplayLimit = 150;
        $boundaryDisplayLimit = 150;
        $affectedDisplayLimit = 200;
        $planCandidateLimit = 100;
        $planQueueLimit = 100;
        $diagnosticJsonLimit = 25;
        $proposalFileDisplayLimit = 100;
        $inventoryDisplayLimit = 120;
        $themeRepairProposals = isset($themeRepairProposals) && is_array($themeRepairProposals) ? $themeRepairProposals : [];
        $themeProposalSummary = isset($themeRepairProposals['summary']) && is_array($themeRepairProposals['summary']) ? $themeRepairProposals['summary'] : [];
        $themeNonExecutableSummary = isset($themeRepairProposals['non_executable_summary']) && is_array($themeRepairProposals['non_executable_summary']) ? $themeRepairProposals['non_executable_summary'] : [];
        $guardedApplyPreflight = isset($themeRepairProposals['guarded_apply_preflight']) && is_array($themeRepairProposals['guarded_apply_preflight']) ? $themeRepairProposals['guarded_apply_preflight'] : [];
        $guardedApplySummary = isset($guardedApplyPreflight['summary']) && is_array($guardedApplyPreflight['summary']) ? $guardedApplyPreflight['summary'] : [];
        $guardedApplyPlans = isset($guardedApplyPreflight['plans']) && is_array($guardedApplyPreflight['plans']) ? $guardedApplyPreflight['plans'] : [];
        $themeProposalQueues = isset($themeRepairProposals['queues']) && is_array($themeRepairProposals['queues']) ? $themeRepairProposals['queues'] : [];
        $themeProposalReadiness = isset($themeRepairProposals['readiness']) && is_array($themeRepairProposals['readiness']) ? $themeRepairProposals['readiness'] : [];
        $themeProposalItems = isset($themeRepairProposals['items']) && is_array($themeRepairProposals['items']) ? $themeRepairProposals['items'] : [];
        $renderThemeProposalRows = static function (array $items, bool $showReplacement) use ($sc, $confidenceClass, $confidenceLabel): void {
            ?>
            <table class="sc-table gs-tool-scroll-table sc-proposal-table">
                <thead>
                    <tr>
                        <?php if ($showReplacement): ?>
                            <th><?= e($sc('proposal_file')) ?></th>
                            <th><?= e($sc('col_selector_property')) ?></th>
                            <th><?= e($sc('col_current')) ?></th>
                            <th><?= e($sc('replacement_token')) ?></th>
                            <th><?= e($sc('col_owner')) ?></th>
                            <th><?= e($sc('col_preflight')) ?></th>
                        <?php else: ?>
                            <th><?= e($sc('proposal_file')) ?></th>
                            <th><?= e($sc('evidence_line')) ?></th>
                            <th><?= e($sc('col_selector')) ?></th>
                            <th><?= e($sc('col_property')) ?></th>
                            <th><?= e($sc('col_current')) ?></th>
                            <th><?= e($sc('manual_semantic_reason')) ?></th>
                            <th><?= e($sc('confidence_model')) ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $proposal): ?>
                        <?php
                        $proposal = is_array($proposal) ? $proposal : [];
                        $reason = (string)($proposal['review_reason_code'] ?? '');
                        $detection = (string)($proposal['detection_confidence'] ?? $proposal['confidence'] ?? 'low');
                        $mapping = (string)($proposal['semantic_mapping_confidence'] ?? 'unknown');
                        $eligibility = (string)($proposal['future_apply_eligibility'] ?? 'not_applicable');
                        ?>
                        <tr>
                            <?php if ($showReplacement): ?>
                                <td><?= e((string)($proposal['file_path'] ?? '')) ?></td>
                                <td><code><?= e((string)($proposal['selector'] ?? '')) ?></code><br><code><?= e((string)($proposal['property'] ?? '')) ?></code></td>
                                <td><code><?= e((string)($proposal['current_value'] ?? '')) ?></code></td>
                                <td><code><?= e((string)($proposal['replacement_value'] ?? '')) ?></code><br><span class="sc-token-count"><?= e((string)($proposal['replacement_token'] ?? '')) ?></span></td>
                                <td><?= e((string)($proposal['source_owner'] ?? '')) ?></td>
                                <td>
                                    <span class="<?= e($confidenceClass($eligibility)) ?>"><?= e($sc('future_apply_eligibility_' . $eligibility)) ?></span>
                                    <details>
                                        <summary><?= e($sc('preflight_evidence_label')) ?></summary>
                                        <dl class="sc-evidence-dl">
                                            <dt><?= e($sc('proposal_id_label')) ?></dt>
                                            <dd><code><?= e((string)($proposal['proposal_id'] ?? '')) ?></code></dd>
                                            <dt><?= e($sc('evidence_line')) ?></dt>
                                            <dd><?= (int)($proposal['line'] ?? 0) ?></dd>
                                            <dt><?= e($sc('replacement_rationale')) ?></dt>
                                            <dd><?= e((string)($proposal['replacement_rationale'] ?? $proposal['review_reason_code'] ?? '')) ?></dd>
                                            <dt><?= e($sc('detection_confidence_label')) ?></dt>
                                            <dd><span class="<?= e($confidenceClass($detection)) ?>"><?= e($confidenceLabel($detection)) ?></span></dd>
                                            <dt><?= e($sc('semantic_mapping_confidence_label')) ?></dt>
                                            <dd><?= e($sc('semantic_mapping_confidence_' . $mapping)) ?></dd>
                                            <dt><?= e($sc('future_apply_eligibility_label')) ?></dt>
                                            <dd><?= e($sc('future_apply_eligibility_' . $eligibility)) ?></dd>
                                        </dl>
                                    </details>
                                </td>
                            <?php else: ?>
                                <td><?= e((string)($proposal['file_path'] ?? '')) ?><br><code><?= e((string)($proposal['proposal_id'] ?? '')) ?></code></td>
                                <td><?= (int)($proposal['line'] ?? 0) ?></td>
                                <td><code><?= e((string)($proposal['selector'] ?? '')) ?></code></td>
                                <td><code><?= e((string)($proposal['property'] ?? '')) ?></code></td>
                                <td><code><?= e((string)($proposal['current_value'] ?? '')) ?></code></td>
                                <td><?= e($sc($reason !== '' ? $reason : 'semantic_mapping_required')) ?></td>
                                <td>
                                    <div><?= e($sc('detection_confidence_label')) ?>: <span class="<?= e($confidenceClass($detection)) ?>"><?= e($confidenceLabel($detection)) ?></span></div>
                                    <div><?= e($sc('automatic_mapping_label')) ?>: <?= e($sc('semantic_mapping_confidence_' . $mapping)) ?></div>
                                    <div><?= e($sc('future_apply_eligibility_label')) ?>: <?= e($sc('future_apply_eligibility_' . $eligibility)) ?></div>
                                    <details>
                                        <summary><span class="sc-evidence-summary-label"><?= e($sc('evidence_details')) ?></span></summary>
                                        <dl class="sc-evidence-dl">
                                            <dt><?= e($sc('proposal_id_label')) ?></dt>
                                            <dd><code><?= e((string)($proposal['proposal_id'] ?? '')) ?></code></dd>
                                            <dt><?= e($sc('replacement_rationale')) ?></dt>
                                            <dd><?= e((string)($proposal['replacement_rationale'] ?? $proposal['review_reason_code'] ?? '')) ?></dd>
                                            <dt><?= e($sc('detection_confidence_label')) ?></dt>
                                            <dd><span class="<?= e($confidenceClass($detection)) ?>"><?= e($confidenceLabel($detection)) ?></span></dd>
                                            <dt><?= e($sc('future_apply_eligibility_label')) ?></dt>
                                            <dd><?= e($sc('future_apply_eligibility_' . $eligibility)) ?></dd>
                                        </dl>
                                    </details>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php
        };
        ?>
        <?php if (($workspace ?? 'overview') === 'shell-inventory'): ?>
            <?php
            $shellInventory = isset($shellInventory) && is_array($shellInventory) ? $shellInventory : [];
            $shellInventorySummary = isset($shellInventory['summary']) && is_array($shellInventory['summary']) ? $shellInventory['summary'] : [];
            $shellInventoryItems = isset($shellInventory['items']) && is_array($shellInventory['items']) ? $shellInventory['items'] : [];
            $shellInventoryCritical = array_values(array_filter($shellInventoryItems, static fn(array $item): bool => (string)($item['inventory_population'] ?? '') === 'eligible_candidate' && in_array((string)($item['criticality'] ?? ''), ['critical', 'high'], true)));
            $shellInventoryShared = array_values(array_filter($shellInventoryItems, static fn(array $item): bool => (string)($item['recommended_disposition'] ?? '') === 'candidate_shared_shell_primitive'));
            $shellInventoryOwnerLocal = array_values(array_filter($shellInventoryItems, static fn(array $item): bool => (string)($item['recommended_disposition'] ?? '') === 'remain_owner_local_shell_governed'));
            $shellInventoryReview = array_values(array_filter($shellInventoryItems, static fn(array $item): bool => (string)($item['recommended_disposition'] ?? '') === 'classification_review' && (string)($item['review_reason_code'] ?? '') !== ''));
            $shellInventoryExcluded = array_values(array_filter($shellInventoryItems, static fn(array $item): bool => (string)($item['recommended_disposition'] ?? '') === 'excluded_or_unsupported'));
            $shellInventoryLowConfidence = array_values(array_filter($shellInventoryItems, static fn(array $item): bool => in_array((string)($item['confidence'] ?? ''), ['low', 'none'], true)));
            $shellInventoryEvidenceOnly = array_values(array_filter($shellInventoryItems, static fn(array $item): bool => (string)($item['inventory_population'] ?? '') === 'evidence_only'));
            $shellInventoryVisible = array_slice($shellInventoryItems, 0, $inventoryDisplayLimit);
            $renderInventoryRows = static function (array $items) use ($sc, $sourceScopeLabel, $govDomainHumanLabel, $confidenceClass, $confidenceLabel): void {
                ?>
                <table class="sc-table gs-tool-scroll-table sc-inventory-table">
                    <thead>
                        <tr>
                            <th><?= e($sc('shell_inventory_col_selector_decl')) ?></th>
                            <th><?= e($sc('migration_source_owner')) ?></th>
                            <th><?= e($sc('migration_source_scope')) ?></th>
                            <th><?= e($sc('shell_inventory_col_population')) ?></th>
                            <th><?= e($sc('shell_inventory_col_candidate_type')) ?></th>
                            <th><?= e($sc('migration_governance_domain')) ?></th>
                            <th><?= e($sc('shell_inventory_col_cross_owner')) ?></th>
                            <th><?= e($sc('shell_inventory_col_criticality')) ?></th>
                            <th><?= e($sc('shell_inventory_col_disposition')) ?></th>
                            <th><?= e($sc('migration_target_owner')) ?></th>
                            <th><?= e($sc('migration_confidence')) ?></th>
                            <th><?= e($sc('migration_reason')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php
                            $candidateType = (string)($item['shell_candidate_type'] ?? '');
                            $disposition = (string)($item['recommended_disposition'] ?? '');
                            $population = (string)($item['inventory_population'] ?? 'evidence_only');
                            $criticality = (string)($item['criticality'] ?? 'unknown');
                            $confidence = (string)($item['confidence'] ?? 'none');
                            $evidence = isset($item['shell_evidence']) && is_array($item['shell_evidence']) ? $item['shell_evidence'] : [];
                            ?>
                            <tr>
                                <td>
                                    <code><?= e((string)($item['selector'] ?? '')) ?></code><br>
                                    <code><?= e((string)($item['property'] ?? '')) ?>: <?= e((string)($item['value'] ?? '')) ?></code><br>
                                    <span class="sc-token-count"><?= e((string)($item['file_path'] ?? '')) ?>:<?= (int)($item['line'] ?? 0) ?></span>
                                    <details class="sc-inventory-evidence">
                                        <summary><?= e($sc('shell_inventory_evidence_payload')) ?></summary>
                                        <dl class="sc-evidence-dl">
                                            <dt><?= e($sc('shell_inventory_id')) ?></dt><dd><code><?= e((string)($item['inventory_id'] ?? '')) ?></code></dd>
                                            <dt><?= e($sc('shell_inventory_structural_signature')) ?></dt><dd><code><?= e((string)($item['structural_signature'] ?? '')) ?></code></dd>
                                            <dt><?= e($sc('shell_inventory_inclusion_reason')) ?></dt><dd><?= e((string)($item['inventory_inclusion_reason'] ?? '')) ?></dd>
                                            <dt><?= e($sc('shell_inventory_evidence')) ?></dt><dd><?= e(implode(', ', array_map('strval', $evidence))) ?></dd>
                                            <dt><?= e($sc('shell_inventory_cross_owner_evidence')) ?></dt><dd><?= e(implode(', ', array_map('strval', isset($item['cross_owner_evidence']) && is_array($item['cross_owner_evidence']) ? $item['cross_owner_evidence'] : []))) ?></dd>
                                            <dt><?= e($sc('shell_inventory_review_reason_code')) ?></dt><dd><?= e($sc('shell_review_' . ((string)($item['review_reason_code'] ?? '') ?: 'none'))) ?></dd>
                                            <dt><?= e($sc('shell_inventory_criticality_reason')) ?></dt><dd><?= e((string)($item['criticality_reason'] ?? '')) ?></dd>
                                            <dt><?= e($sc('migration_state')) ?></dt><dd><?= e((string)($item['migration_state'] ?? '')) ?></dd>
                                            <dt><?= e($sc('migration_governance_required_domain')) ?></dt><dd><?= e($govDomainHumanLabel((string)($item['governance_required_domain'] ?? 'not_applicable'))) ?></dd>
                                            <dt><?= e($sc('shell_inventory_review_required')) ?></dt><dd><?= !empty($item['review_required']) ? 'true' : 'false' ?></dd>
                                        </dl>
                                    </details>
                                </td>
                                <td><?= e((string)($item['source_owner'] ?? '')) ?></td>
                                <td><?= e($sourceScopeLabel((string)($item['source_scope'] ?? ''))) ?></td>
                                <td><?= e($sc('shell_population_' . $population)) ?></td>
                                <td><?= e($sc('shell_candidate_' . $candidateType)) ?></td>
                                <td><?= e($govDomainHumanLabel((string)($item['governance_domain'] ?? 'not_applicable'))) ?></td>
                                <td><?= (int)($item['distinct_owner_count'] ?? 0) ?> / <?= (int)($item['cross_owner_usage_count'] ?? 0) ?></td>
                                <td><span class="sc-badge sc-badge-keyword"><?= e($sc('shell_criticality_' . $criticality)) ?></span></td>
                                <td><?= e($sc('shell_disposition_' . $disposition)) ?></td>
                                <td><?= e((string)($item['target_owner'] ?? '')) ?></td>
                                <td><span class="<?= e($confidenceClass($confidence === 'none' ? 'low' : $confidence)) ?>"><?= e($confidence === 'none' ? $sc('confidence_none') : $confidenceLabel($confidence)) ?></span></td>
                                <td>
                                    <?= e((string)($item['disposition_reason'] ?? '')) ?>
                                    <?php if (($item['review_reason_code'] ?? '') !== ''): ?>
                                        <br><span class="sc-token-count"><?= e($sc('shell_review_' . (string)$item['review_reason_code'])) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php
            };
            ?>
            <section class="sc-section" data-workspace="shell-inventory">
                <h3><?= e($sc('shell_inventory_title')) ?></h3>
                <p class="sc-proposal-meta"><?= e($sc('shell_inventory_desc')) ?></p>
                <div class="sc-summary-grid">
                    <div class="sc-summary-cell"><span class="num"><?= (int)($shellInventorySummary['declarations_scanned'] ?? 0) ?></span><span class="lbl"><?= e($sc('shell_inventory_declarations_scanned')) ?></span></div>
                    <div class="sc-summary-cell"><span class="num"><?= (int)($shellInventorySummary['shell_relevant_evidence'] ?? 0) ?></span><span class="lbl"><?= e($sc('shell_inventory_relevant_evidence')) ?></span></div>
                    <div class="sc-summary-cell"><span class="num"><?= (int)($shellInventorySummary['eligible_candidates'] ?? 0) ?></span><span class="lbl"><?= e($sc('shell_inventory_eligible_candidates')) ?></span></div>
                    <div class="sc-summary-cell"><span class="num"><?= (int)($shellInventorySummary['critical'] ?? 0) ?></span><span class="lbl"><?= e($sc('shell_inventory_critical')) ?></span></div>
                    <div class="sc-summary-cell"><span class="num"><?= (int)($shellInventorySummary['candidate_shared_shell_primitive'] ?? 0) ?></span><span class="lbl"><?= e($sc('shell_disposition_candidate_shared_shell_primitive')) ?></span></div>
                    <div class="sc-summary-cell"><span class="num"><?= (int)($shellInventorySummary['remain_owner_local_shell_governed'] ?? 0) ?></span><span class="lbl"><?= e($sc('shell_disposition_remain_owner_local_shell_governed')) ?></span></div>
                    <div class="sc-summary-cell"><span class="num"><?= (int)($shellInventorySummary['classification_review'] ?? 0) ?></span><span class="lbl"><?= e($sc('shell_disposition_classification_review')) ?></span></div>
                    <div class="sc-summary-cell"><span class="num"><?= (int)($shellInventorySummary['evidence_only'] ?? 0) ?></span><span class="lbl"><?= e($sc('shell_population_evidence_only')) ?></span></div>
                    <div class="sc-summary-cell"><span class="num"><?= (int)($shellInventorySummary['outside_inventory'] ?? 0) ?></span><span class="lbl"><?= e($sc('shell_population_outside_inventory')) ?></span></div>
                    <div class="sc-summary-cell"><span class="num"><?= (int)($shellInventorySummary['excluded'] ?? 0) ?></span><span class="lbl"><?= e($sc('shell_population_excluded')) ?></span></div>
                </div>
                <p class="sc-proposal-meta"><?= e($sc('shell_inventory_metric_hint')) ?></p>
                <div class="sc-filter-row" aria-label="<?= e($sc('shell_inventory_filters')) ?>">
                    <span><?= e($sc('migration_source_scope')) ?>: <?= e($scope === 'owner' ? $sourceScopeLabel('owner') : $sourceScopeLabel($scope)) ?></span>
                    <span><?= e($sc('migration_source_owner')) ?>: <?= e($ownerKey !== '' ? $ownerKey : $sc('shell_inventory_filter_all')) ?></span>
                    <span><?= e($sc('shell_inventory_col_candidate_type')) ?>: <?= e($sc('shell_inventory_filter_all')) ?></span>
                    <span><?= e($sc('shell_inventory_col_disposition')) ?>: <?= e($sc('shell_inventory_filter_all')) ?></span>
                    <span><?= e($sc('migration_confidence')) ?>: <?= e($sc('shell_inventory_filter_all')) ?></span>
                    <span><?= e($sc('shell_inventory_col_criticality')) ?>: <?= e($sc('shell_inventory_filter_all')) ?></span>
                    <span><?= e($sc('shell_inventory_col_cross_owner')) ?>: <?= e($sc('shell_inventory_filter_all')) ?></span>
                </div>
            </section>
            <section class="sc-section">
                <h3><?= e($sc('shell_inventory_critical_title')) ?></h3>
                <?php if ($shellInventoryCritical === []): ?>
                    <div class="sc-empty"><?= e($sc('shell_inventory_empty')) ?></div>
                <?php else: ?>
                    <?php $renderInventoryRows(array_slice($shellInventoryCritical, 0, $inventoryDisplayLimit)); ?>
                <?php endif; ?>
            </section>
            <section class="sc-section">
                <h3><?= e($sc('shell_inventory_shared_title')) ?></h3>
                <?php if ($shellInventoryShared === []): ?>
                    <div class="sc-empty"><?= e($sc('shell_inventory_empty')) ?></div>
                <?php else: ?>
                    <?php $renderInventoryRows(array_slice($shellInventoryShared, 0, $inventoryDisplayLimit)); ?>
                <?php endif; ?>
            </section>
            <section class="sc-section">
                <h3><?= e($sc('shell_inventory_review_title')) ?></h3>
                <?php if ($shellInventoryReview === []): ?>
                    <div class="sc-empty"><?= e($sc('shell_inventory_empty')) ?></div>
                <?php else: ?>
                    <?php $renderInventoryRows(array_slice($shellInventoryReview, 0, $inventoryDisplayLimit)); ?>
                <?php endif; ?>
            </section>
            <section class="sc-section">
                <details <?= count($shellInventoryOwnerLocal) <= 50 ? 'open' : '' ?>>
                    <summary class="sc-structural-summary"><?= e($sc('shell_inventory_owner_local_title')) ?> <span class="sc-token-count">(<?= (int)count($shellInventoryOwnerLocal) ?>)</span></summary>
                    <?php if ($shellInventoryOwnerLocal === []): ?>
                        <div class="sc-empty"><?= e($sc('shell_inventory_empty')) ?></div>
                    <?php else: ?>
                        <?php $renderInventoryRows(array_slice($shellInventoryOwnerLocal, 0, $inventoryDisplayLimit)); ?>
                    <?php endif; ?>
                </details>
            </section>
            <section class="sc-section">
                <details>
                    <summary class="sc-structural-summary"><?= e($sc('shell_inventory_evidence_only_title')) ?> <span class="sc-token-count">(<?= (int)count($shellInventoryEvidenceOnly) ?>)</span></summary>
                    <?php if ($shellInventoryEvidenceOnly === []): ?>
                        <div class="sc-empty"><?= e($sc('shell_inventory_empty')) ?></div>
                    <?php else: ?>
                        <?php $renderInventoryRows(array_slice($shellInventoryEvidenceOnly, 0, $inventoryDisplayLimit)); ?>
                    <?php endif; ?>
                </details>
            </section>
            <section class="sc-section">
                <details>
                    <summary class="sc-structural-summary"><?= e($sc('shell_inventory_low_conf_title')) ?> <span class="sc-token-count">(<?= (int)count($shellInventoryLowConfidence) ?>)</span></summary>
                    <?php if ($shellInventoryLowConfidence === []): ?>
                        <div class="sc-empty"><?= e($sc('shell_inventory_empty')) ?></div>
                    <?php else: ?>
                        <?php $renderInventoryRows(array_slice($shellInventoryLowConfidence, 0, $inventoryDisplayLimit)); ?>
                    <?php endif; ?>
                </details>
            </section>
            <section class="sc-section">
                <details>
                    <summary class="sc-structural-summary"><?= e($sc('shell_inventory_excluded_title')) ?> <span class="sc-token-count">(<?= (int)count($shellInventoryExcluded) ?>)</span></summary>
                    <?php if ($shellInventoryExcluded === []): ?>
                        <div class="sc-empty"><?= e($sc('shell_inventory_empty')) ?></div>
                    <?php else: ?>
                        <?php $renderInventoryRows(array_slice($shellInventoryExcluded, 0, $inventoryDisplayLimit)); ?>
                    <?php endif; ?>
                </details>
            </section>
            <section class="sc-section">
                <details>
                    <summary class="sc-structural-summary"><?= e($sc('shell_inventory_all_title')) ?> <span class="sc-token-count">(<?= (int)count($shellInventoryItems) ?>)</span></summary>
                    <?php if ($shellInventoryItems === []): ?>
                        <div class="sc-empty"><?= e($sc('shell_inventory_empty')) ?></div>
                    <?php else: ?>
                        <?php $renderInventoryRows($shellInventoryVisible); ?>
                    <?php endif; ?>
                </details>
            </section>
            <?php return; ?>
        <?php endif; ?>
        <?php
        require __DIR__ . '/_result_context.php';
        $presentationSummary = isset($presentationSummary) && is_array($presentationSummary) ? $presentationSummary : [];
        $statsRepair = (int)($presentationSummary['fixable_now'] ?? 0);
        $statsAccess = (int)($themeProposalSummary['accessibility_review_items'] ?? 0);
        $statsManual = count($themeProposalQueues['manual_semantic_decisions'] ?? []);
        $statsClassify = count(array_filter($themeProposalQueues['classification_and_accessibility_review'] ?? [], static fn($p): bool => ((string)($p['review_reason_code'] ?? '')) !== 'accessibility_review'));
        $statsDecision = (int)($presentationSummary['decision_backlog'] ?? ($statsManual + $statsClassify));
        $statsEffects = (int)($themeProposalSummary['future_effects_handoffs'] ?? 0);
        $statsReviewTotal = (int)($presentationSummary['review_only'] ?? ($statsAccess + $statsDecision + count($foundationConcerns)));
        $workflowStep = 1;
        $workflowStepScanDone = true;
        $workflowStepReviewDone = $statsRepair > 0;
        $workflowStepApplyDone = false;
        if ($statsRepair > 0) {
            $workflowStep = 3;
        } elseif ($statsReviewTotal > 0) {
            $workflowStep = 2;
        }
        // Action Center capability boundary:
        // - Primary action = deterministic V1 Fix One rows.
        // - Secondary links = read-only evidence (inspection only, no decision capture).
        $showNoRepair = $statsRepair === 0 && ($statsDecision > 0 || $statsAccess > 0);
        ?>
        <!-- Workflow indicator -->
        <div class="sc-workflow">
            <span class="sc-workflow-step sc-workflow-step-done"><?= e($sc('workflow_step_scan')) ?></span>
            <span class="sc-workflow-connector sc-workflow-connector-done"></span>
            <span class="sc-workflow-step <?= $workflowStep === 1 ? 'sc-workflow-step-active' : ($workflowStep === 3 ? 'sc-workflow-step-done' : '') ?>"><?= e($sc('workflow_step_review')) ?></span>
            <span class="sc-workflow-connector <?= $workflowStep === 3 ? 'sc-workflow-connector-done' : '' ?>"></span>
            <span class="sc-workflow-step <?= $workflowStep === 3 ? 'sc-workflow-step-active' : '' ?>"><?= e($sc('workflow_step_repair')) ?></span>
        </div>
        <!-- Dashboard cards -->
        <div class="sc-dashboard gs-tool-metric-grid">
            <div class="sc-dash-card gs-tool-metric-card sc-dash-default">
                <span class="num"><?= (int)($presentationSummary['findings_total'] ?? 0) ?></span>
                <span class="lbl"><?= e($sc('dashboard_total_findings')) ?></span>
            </div>
            <div class="sc-dash-card gs-tool-metric-card sc-dash-ready">
                <span class="num"><?= (int)$statsRepair ?></span>
                <span class="lbl"><?= e($sc('dashboard_ready_to_repair')) ?></span>
            </div>
            <div class="sc-dash-card gs-tool-metric-card sc-dash-review">
                <span class="num"><?= (int)$statsReviewTotal ?></span>
                <span class="lbl"><?= e($sc('dashboard_needs_review')) ?></span>
            </div>
            <div class="sc-dash-card gs-tool-metric-card sc-dash-score">
                <span class="num"><?= $complianceScore !== null ? (int)$complianceScore . '%' : '--' ?></span>
                <span class="lbl"><?= e($sc('dashboard_compliance_score')) ?></span>
            </div>
        </div>
        <!-- Action Center -->
        <div class="sc-action-card gs-tool-action-card">
            <h3><?= e($sc('action_center_title')) ?></h3>
            <?php if ($statsRepair > 0): ?>
                <p><?= e(sprintf($sc('action_center_repair'), $statsRepair)) ?></p>
                <a href="#scVerifiedCandidates" class="sc-next-action-link gs-tool-action-link"><?= e($sc('repair_queue_title')) ?> →</a>
            <?php elseif ($showNoRepair): ?>
                <p><?= e($sc('action_center_no_repair')) ?></p>
                <p class="sc-proposal-meta"><?= e($sc('action_center_no_repair_why')) ?></p>
                <?php if ($statsDecision > 0 || $statsAccess > 0): ?>
                <div class="sc-action-center-secondary">
                    <?php if ($statsDecision > 0): ?>
                        <a href="#scDecisionBacklog" class="sc-next-action-link gs-tool-action-link sc-link-secondary"><?= e(sprintf($sc('action_center_secondary_decision'), $statsDecision)) ?> →</a>
                    <?php endif; ?>
                    <?php if ($statsAccess > 0): ?>
                        <a href="#scAccessibilityReview" class="sc-next-action-link gs-tool-action-link sc-link-secondary"><?= e(sprintf($sc('action_center_secondary_accessibility'), $statsAccess)) ?> →</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <p><?= e($sc('action_center_no_action')) ?></p>
            <?php endif; ?>
        </div>
        <?php if ($foundationConcerns !== []): ?>
        <details class="sc-section-collapsible">
            <summary><h3><?= e($sc('foundation_concerns_title')) ?></h3> <span class="sc-token-count">(<?= count($foundationConcerns) ?>)</span></summary>
            <p class="sc-meta"><?= $sc('foundation_concerns_desc') ?></p>
            <div class="table-wrap">
                <table class="sc-table gs-tool-scroll-table">
                    <thead>
                        <tr>
                            <th><?= e($sc('col_file')) ?></th>
                            <th><?= e($sc('col_selector')) ?></th>
                            <th><?= e($sc('foundation_missing')) ?></th>
                            <th><?= e($sc('col_severity')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach (array_slice($foundationConcerns, 0, 100) as $c): ?>
                        <?php $cFile = (string)($c['file'] ?? ''); ?>
                        <?php $cSelector = (string)($c['selector'] ?? ''); ?>
                        <?php $cMissing = array_map('strval', $c['missing'] ?? []); ?>
                        <?php $cSev = (string)($c['severity'] ?? 'info'); ?>
                        <tr>
                            <td><code><?= e($cFile) ?></code></td>
                            <td><code><?= e($cSelector) ?></code></td>
                            <td><?= e(implode(', ', $cMissing)) ?></td>
                            <td><span class="sc-badge sc-badge-<?= e($cSev) ?>"><?= e($sc('severity_' . $cSev)) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </details>
        <?php endif; ?>
        <?php
        require __DIR__ . '/_result_verified_fixes.php';
        require __DIR__ . '/_result_decision_backlog.php';
        require __DIR__ . '/_result_diagnostics.php';
        ?>

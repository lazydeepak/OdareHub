
        <!-- Diagnostics wrapper: all detailed inventory collapsed under one details element -->
        <details id="scDiagnostics" class="sc-section sc-diagnostics-wrap">
            <summary class="sc-diagnostics-summary"><?= e($sc('section_diagnostics_title')) ?></summary>

        <!-- About this scan (closed by default) -->
        <details class="sc-about-scan">
            <summary><?= e($sc('about_this_scan_title')) ?></summary>
            <div class="sc-scope-note-grid">
                <div>
                    <strong><?= e($sc('scope_note_current_title')) ?></strong>
                    <ul class="sc-note-list">
                        <li><?= e($sc('scope_note_current_item_css')) ?></li>
                        <li><?= e($sc('scope_note_current_item_php')) ?></li>
                        <li><?= e($sc('scope_note_current_item_repair')) ?></li>
                    </ul>
                </div>
                <div>
                    <strong><?= e($sc('scope_note_future_title')) ?></strong>
                    <ul class="sc-note-list">
                        <li><?= e($sc('scope_note_future_item_js')) ?></li>
                        <li><?= e($sc('scope_note_future_item_svg')) ?></li>
                        <li><?= e($sc('scope_note_future_item_effects')) ?></li>
                        <li><?= e($sc('scope_note_future_item_templates')) ?></li>
                    </ul>
                </div>
                <div>
                    <strong><?= e($sc('scope_note_boundaries_title')) ?></strong>
                    <ul class="sc-note-list">
                        <li><?= e($sc('scope_note_boundary_vendor')) ?></li>
                        <li><?= e($sc('scope_note_boundary_embedded')) ?></li>
                    </ul>
                </div>
            </div>
        </details>

        <!-- Summary -->
        <section class="sc-section">
            <h3><?= e($sc('summary_title')) ?></h3>
            <div class="sc-summary-grid">
                <div class="sc-summary-cell"><span class="num"><?= (int)($summary['total_tokens'] ?? 0) ?></span><span class="lbl"><?= e($sc('summary_total_tokens')) ?></span></div>
                <div class="sc-summary-cell"><span class="num"><?= (int)($summary['in_scope'] ?? 0) ?></span><span class="lbl"><?= e($sc('summary_in_scope')) ?></span></div>
                <div class="sc-summary-cell"><span class="num"><?= (int)($summary['in_scope_aware'] ?? 0) ?></span><span class="lbl"><?= e($sc('summary_theme_aware')) ?></span></div>
                <div class="sc-summary-cell"><span class="num"><?= (int)($summary['in_scope_unaware'] ?? 0) ?></span><span class="lbl"><?= e($sc('summary_unaware')) ?></span></div>
                <div class="sc-summary-cell"><span class="num"><?= (int)($summary['visual_literals'] ?? 0) ?></span><span class="lbl"><?= e($sc('summary_visual_literals')) ?></span></div>
                <div class="sc-summary-cell"><span class="num"><?= (int)($summary['repairable'] ?? 0) ?></span><span class="lbl"><?= e($sc('summary_repairable')) ?></span></div>
                <div class="sc-summary-cell"><span class="num"><?= (int)($summary['token_definitions'] ?? 0) ?></span><span class="lbl"><?= e($sc('summary_token_definitions')) ?></span></div>
                <div class="sc-summary-cell"><span class="num"><?= (int)($summary['evidence_only'] ?? 0) ?></span><span class="lbl"><?= e($sc('summary_evidence_only')) ?></span></div>
                <div class="sc-summary-cell"><span class="num"><?= (int)($summary['structural'] ?? 0) ?></span><span class="lbl"><?= e($sc('summary_structural')) ?></span></div>
                <div class="sc-summary-cell"><span class="num"><?= (int)($summary['effect_candidates'] ?? 0) ?></span><span class="lbl"><?= e($sc('summary_effect_candidates')) ?></span></div>
                <div class="sc-summary-cell"><span class="num"><?= (int)($summary['dynamic_unsupported'] ?? 0) ?></span><span class="lbl"><?= e($sc('summary_dynamic_unsupported')) ?></span></div>
                <div class="sc-summary-cell"><span class="num"><?= (int)($summary['print_pdf'] ?? 0) ?></span><span class="lbl"><?= e($sc('summary_print_pdf')) ?></span></div>
                <div class="sc-summary-cell"><span class="num"><?= (int)($summary['boundary_findings'] ?? 0) ?></span><span class="lbl"><?= e($sc('summary_boundary')) ?></span></div>
            </div>
        </section>

        <!-- Compliance assessment -->
        <section class="sc-section">
            <h3><?= e($sc('assessment_title')) ?></h3>
            <div class="sc-assessment">
                <div class="sc-assessment-item">
                    <span class="sc-assessment-label"><?= e($sc('assessment_status')) ?></span>
                    <span class="sc-assessment-value"><?= e($sc($assessmentKey)) ?></span>
                </div>
                <div class="sc-assessment-item">
                    <span class="sc-assessment-label"><?= e($sc('assessment_score')) ?></span>
                    <span class="sc-assessment-value"><?= $complianceScore !== null ? $complianceScore . '%' : '—' ?></span>
                </div>
                <div class="sc-assessment-item">
                    <span class="sc-assessment-label"><?= e($sc('assessment_readiness')) ?></span>
                    <span class="sc-assessment-value"><?= $repairReadinessScore !== null ? $repairReadinessScore . '%' : '—' ?></span>
                </div>
                <div class="sc-assessment-item">
                    <span class="sc-assessment-label"><?= e($sc('assessment_primary_file')) ?></span>
                    <span class="sc-assessment-value"><?= e($primaryAffectedFile !== '' ? $primaryAffectedFile : $sc('assessment_no_file')) ?></span>
                </div>
                <div class="sc-assessment-next">
                    <span class="sc-assessment-label"><?= e($sc('assessment_next_step')) ?></span>
                    <span class="sc-assessment-value"><?= e($sc($nextStepKey)) ?></span>
                </div>
            </div>
        </section>

        <!-- Repair workflow lanes -->
        <section class="sc-section">
            <h3><?= e($sc('workflow_title')) ?></h3>
            <p class="sc-proposal-meta"><?= e($sc('workflow_hint')) ?></p>
            <div class="sc-summary-grid">
                <div class="sc-summary-cell"><span class="num"><?= (int)($summary['domain_theme'] ?? 0) ?></span><span class="lbl"><?= e($sc('workflow_theme')) ?></span></div>
                <div class="sc-summary-cell"><span class="num"><?= (int)($summary['domain_shell_foundation'] ?? 0) ?></span><span class="lbl"><?= e($sc('workflow_shell')) ?></span></div>
                <div class="sc-summary-cell"><span class="num"><?= (int)($summary['domain_owner_surface'] ?? 0) ?></span><span class="lbl"><?= e($sc('workflow_owner_local')) ?></span></div>
                <div class="sc-summary-cell"><span class="num"><?= (int)($summary['domain_special_effect'] ?? 0) ?></span><span class="lbl"><?= e($sc('workflow_effects')) ?></span></div>
                <div class="sc-summary-cell"><span class="num"><?= (int)($summary['domain_structural'] ?? 0) ?></span><span class="lbl"><?= e($sc('workflow_structural')) ?></span></div>
                <div class="sc-summary-cell"><span class="num"><?= (int)($summary['domain_print_pdf'] ?? 0) ?></span><span class="lbl"><?= e($sc('workflow_print')) ?></span></div>
                <div class="sc-summary-cell"><span class="num"><?= (int)($summary['domain_unsupported'] ?? 0) ?></span><span class="lbl"><?= e($sc('workflow_unsupported')) ?></span></div>
            </div>
        </section>

        <!-- Theme-aware repair proposal contract -->
        <section class="sc-section">
            <h3><?= e($sc('proposal_title')) ?></h3>
            <?php
            $executionCapability = isset($executionCapability) && is_array($executionCapability) ? $executionCapability : [];
            $guardedExecutionEnabled = !empty($executionCapability['executor_enabled']);
            ?>
            <div class="sc-proposal-meta">
                <span><strong><?= e($sc('proposal_status')) ?>:</strong> <?= e($sc($guardedExecutionEnabled ? 'guarded_apply_available' : 'future_apply_not_enabled')) ?></span>
                <span><?= e($sc($guardedExecutionEnabled ? 'proposal_hint_guarded' : 'proposal_hint_readonly')) ?></span>
            </div>
            <p class="sc-proposal-meta"><?= e($sc($guardedExecutionEnabled ? 'theme_repair_guarded_notice' : 'theme_repair_readonly_notice')) ?></p>
            <div class="sc-summary-grid" aria-label="<?= e($sc('proposal_lanes_title')) ?>">
                <div class="sc-summary-cell"><span class="num"><?= (int)($themeProposalSummary['deterministic_future_apply'] ?? 0) ?></span><span class="lbl"><?= e($sc('proposal_count_deterministic_future_apply')) ?></span></div>
                <div class="sc-summary-cell"><span class="num"><?= (int)($themeProposalSummary['manual_semantic_decisions'] ?? 0) ?></span><span class="lbl"><?= e($sc('proposal_count_manual_decisions')) ?></span></div>
                <div class="sc-summary-cell"><span class="num"><?= (int)($themeProposalSummary['classification_review_items'] ?? 0) ?></span><span class="lbl"><?= e($sc('proposal_count_classification_review')) ?></span></div>
                <div class="sc-summary-cell"><span class="num"><?= (int)($themeProposalSummary['accessibility_review_items'] ?? 0) ?></span><span class="lbl"><?= e($sc('proposal_count_accessibility_review')) ?></span></div>
                <div class="sc-summary-cell"><span class="num"><?= (int)($themeProposalSummary['future_effects_handoffs'] ?? 0) ?></span><span class="lbl"><?= e($sc('proposal_count_future_effects')) ?></span></div>
                <div class="sc-summary-cell"><span class="num"><?= (int)($themeProposalSummary['excluded_unsupported'] ?? 0) ?></span><span class="lbl"><?= e($sc('proposal_count_excluded')) ?></span></div>
                <div class="sc-summary-cell"><span class="num"><?= (int)($themeProposalSummary['files_with_deterministic'] ?? 0) ?></span><span class="lbl"><?= e($sc('proposal_count_files_deterministic')) ?></span></div>
                <div class="sc-summary-cell"><span class="num"><?= (int)($themeProposalSummary['files_with_manual'] ?? 0) ?></span><span class="lbl"><?= e($sc('proposal_count_files_manual')) ?></span></div>
            </div>
            <h4><?= e($sc('guarded_apply_preflight_title')) ?></h4>
            <p class="sc-proposal-meta">
                <?= e($sc($guardedExecutionEnabled ? 'guarded_apply_preflight_desc_guarded' : 'guarded_apply_preflight_desc_readonly')) ?>
                <a href="/apps/studio/tools/customization-studio/diagnose/theme-doctor"><?= e($sc('theme_doctor_link')) ?></a>
            </p>
            <div class="sc-summary-grid" aria-label="<?= e($sc('guarded_apply_preflight_title')) ?>">
                <div class="sc-summary-cell"><span class="num"><?= (int)($guardedApplySummary['eligible_after_preflight'] ?? 0) ?></span><span class="lbl"><?= e($sc('preflight_eligible')) ?></span></div>
                <div class="sc-summary-cell"><span class="num"><?= (int)($guardedApplySummary['blocked'] ?? 0) ?></span><span class="lbl"><?= e($sc('preflight_blocked')) ?></span></div>
                <div class="sc-summary-cell"><span class="num"><?= (int)($guardedApplySummary['stale'] ?? 0) ?></span><span class="lbl"><?= e($sc('preflight_stale')) ?></span></div>
                <div class="sc-summary-cell"><span class="num"><?= (int)($guardedApplySummary['review_required'] ?? 0) ?></span><span class="lbl"><?= e($sc('preflight_review_required')) ?></span></div>
                <div class="sc-summary-cell"><span class="num"><?= (int)($guardedApplySummary['owner_review_required'] ?? 0) ?></span><span class="lbl"><?= e($sc('owner_review_required')) ?></span></div>
                <div class="sc-summary-cell"><span class="num"><?= (int)($guardedApplySummary['single_declaration_scope'] ?? 0) ?></span><span class="lbl"><?= e($sc('single_declaration_scope_only')) ?></span></div>
                <?php if (!$guardedExecutionEnabled): ?>
                    <div class="sc-summary-cell"><span class="num"><?= (int)($guardedApplySummary['future_executor_not_enabled'] ?? 0) ?></span><span class="lbl"><?= e($sc('future_executor_not_enabled')) ?></span></div>
                <?php endif; ?>
            </div>
            <p class="sc-proposal-meta"><?= e($sc($guardedExecutionEnabled ? 'snapshot_rollback_guarded_notice' : 'snapshot_rollback_future_notice')) ?></p>
            <?php if ($guardedApplyPlans === []): ?>
                <div class="sc-empty"><?= e($sc('preflight_empty')) ?></div>
            <?php else: ?>
                <table class="sc-table gs-tool-scroll-table sc-proposal-table">
                    <thead>
                        <tr>
                            <th><?= e($sc('proposal_file')) ?></th>
                            <th><?= e($sc('evidence_line')) ?></th>
                            <th><?= e($sc('selector_property')) ?></th>
                            <th><?= e($sc('expected_current_value')) ?></th>
                            <th><?= e($sc('proposed_replacement')) ?></th>
                            <th><?= e($sc('migration_source_owner')) ?></th>
                            <th><?= e($sc('required_reviewer')) ?></th>
                            <th><?= e($sc('preflight_status')) ?></th>
                            <th><?= e($sc('source_integrity')) ?></th>
                            <th><?= e($sc('token_contract')) ?></th>
                            <th><?= e($sc('validation_requirements')) ?></th>
                            <th><?= e($sc('proposal_blockers')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($guardedApplyPlans, 0, $planQueueLimit) as $plan): ?>
                            <?php
                            $plan = is_array($plan) ? $plan : [];
                            $checks = isset($plan['preflight_checks']) && is_array($plan['preflight_checks']) ? $plan['preflight_checks'] : [];
                            $blockedBy = isset($plan['blocked_by']) && is_array($plan['blocked_by']) ? $plan['blocked_by'] : [];
                            $invalidatedBy = isset($plan['invalidated_by']) && is_array($plan['invalidated_by']) ? $plan['invalidated_by'] : [];
                            $validations = isset($plan['required_validations']) && is_array($plan['required_validations']) ? $plan['required_validations'] : [];
                            $sourceCheckOk = (string)($plan['source_fingerprint'] ?? '') !== '' && $invalidatedBy === [];
                            $tokenCheckOk = (string)($plan['proposed_replacement_token'] ?? '') !== '';
                            ?>
                            <tr>
                                <td><?= e((string)($plan['file_path'] ?? '')) ?><br><code><?= e((string)($plan['apply_plan_id'] ?? '')) ?></code></td>
                                <td><?= (int)($plan['line'] ?? 0) ?></td>
                                <td><code><?= e((string)($plan['selector'] ?? '')) ?></code><br><code><?= e((string)($plan['property'] ?? '')) ?></code></td>
                                <td><code><?= e((string)($plan['expected_current_value'] ?? '')) ?></code></td>
                                <td><code><?= e((string)($plan['proposed_replacement_value'] ?? '')) ?></code><br><span class="sc-token-count"><?= e((string)($plan['proposed_replacement_token'] ?? '')) ?></span></td>
                                <td><?= e((string)($plan['source_owner'] ?? '')) ?></td>
                                <td><?= e((string)($plan['required_reviewer'] ?? '')) ?></td>
                                <td><span class="<?= e($readinessClass((string)($plan['preflight_status'] ?? '') === 'ready' ? 'pass' : ((string)($plan['preflight_status'] ?? '') === 'stale' ? 'blocked' : 'pending'))) ?>"><?= e($sc('preflight_status_' . (string)($plan['preflight_status'] ?? 'blocked'))) ?></span></td>
                                <td><?= e($sourceCheckOk ? $sc('integrity_present') : $sc('integrity_blocked')) ?></td>
                                <td><?= e($tokenCheckOk ? $sc('token_contract_present') : $sc('token_contract_missing')) ?></td>
                                <td><?= e((string)count($validations)) ?> <?= e($sc('validation_requirements')) ?></td>
                                <td><?= e($blockedBy === [] && $invalidatedBy === [] ? $sc('none') : implode(', ', array_map('strval', array_merge($blockedBy, $invalidatedBy)))) ?></td>
                            </tr>
                            <tr>
                                <td colspan="12">
                                    <details>
                                        <summary><?= e($sc('full_preflight_details')) ?></summary>
                                        <dl class="sc-evidence-dl">
                                            <dt><?= e($sc('full_fingerprints')) ?></dt>
                                            <dd><code><?= e((string)($plan['source_fingerprint'] ?? '')) ?></code><br><code><?= e((string)($plan['declaration_fingerprint'] ?? '')) ?></code><br><code><?= e((string)($plan['proposal_fingerprint'] ?? '')) ?></code></dd>
                                            <dt><?= e($sc('proposal_preconditions')) ?></dt>
                                            <dd><?= e(implode(', ', array_map(static fn(array $c): string => (string)($c['key'] ?? '') . '=' . (string)($c['state'] ?? ''), $checks))) ?></dd>
                                            <dt><?= e($sc('future_snapshot_requirements')) ?></dt>
                                            <dd><?= e(implode(', ', array_map('strval', isset($plan['required_evidence']) && is_array($plan['required_evidence']) ? $plan['required_evidence'] : []))) ?></dd>
                                            <dt><?= e($sc('future_rollback_requirements')) ?></dt>
                                            <dd><?= e((string)($plan['rollback_contract'] ?? '')) ?></dd>
                                            <dt><?= e($sc('full_validation_matrix')) ?></dt>
                                            <dd><?= e(implode(', ', array_map('strval', $validations))) ?></dd>
                                        </dl>
                                    </details>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <h4><?= e($sc('non_executable_population_title')) ?></h4>
            <p class="sc-proposal-meta"><?= e($sc('non_executable_population_desc')) ?></p>
            <div class="sc-summary-grid">
                <div class="sc-summary-cell"><span class="num"><?= (int)($themeNonExecutableSummary['print_pdf_review'] ?? 0) ?></span><span class="lbl"><?= e($sc('print_pdf_candidate')) ?></span></div>
                <div class="sc-summary-cell"><span class="num"><?= (int)($themeNonExecutableSummary['dynamic_unsupported'] ?? 0) ?></span><span class="lbl"><?= e($sc('dynamic_or_unsupported')) ?></span></div>
                <div class="sc-summary-cell"><span class="num"><?= (int)($themeNonExecutableSummary['not_repairable'] ?? 0) ?></span><span class="lbl"><?= e($sc('proposal_class_not_repairable')) ?></span></div>
                <div class="sc-summary-cell"><span class="num"><?= (int)($themeNonExecutableSummary['excluded_source'] ?? 0) ?></span><span class="lbl"><?= e($sc('excluded_source')) ?></span></div>
                <div class="sc-summary-cell"><span class="num"><?= (int)($themeNonExecutableSummary['future_tool_handoff'] ?? 0) ?></span><span class="lbl"><?= e($sc('future_effects_handoff')) ?></span></div>
            </div>
            <h4><?= e($sc('readiness_title')) ?></h4>
            <table class="sc-table gs-tool-scroll-table">
                <thead>
                    <tr>
                        <th><?= e($sc('readiness_check')) ?></th>
                        <th><?= e($sc('readiness_state')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($themeProposalReadiness as $check): ?>
                        <?php
                        $checkKey = (string)($check['key'] ?? '');
                        $state = (string)($check['state'] ?? 'blocked');
                        ?>
                        <tr>
                            <td><?= e($sc($checkKey)) ?></td>
                            <td><span class="<?= e($readinessClass($state)) ?>"><?= e($sc('readiness_state_' . $state)) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <!-- Migration Required -->
        <section class="sc-section">
            <h3><?= e($sc('migration_title')) ?></h3>
            <p class="sc-desc"><?= e($sc('migration_desc')) ?></p>
            <?php
            $migStates = [
                'none' => 'migration_none',
                'value_fix' => 'migration_value_fix',
                'domain_migration' => 'migration_domain',
                'classification_review' => 'migration_review',
                'future_handoff' => 'migration_handoff',
                'not_applicable' => 'migration_not_applicable',
            ];
            $migGrouped = [];
            foreach ($tokens as $row) {
                if (!is_array($row)) continue;
                $state = (string)($row['migration_state'] ?? 'none');
                if (!isset($migGrouped[$state])) $migGrouped[$state] = [];
                $migGrouped[$state][] = $row;
            }
            $hasMigration = false;
            foreach (['value_fix', 'domain_migration', 'classification_review', 'future_handoff'] as $activeState) {
                if (!empty($migGrouped[$activeState])) { $hasMigration = true; break; }
            }

            ?>
            <?php if (!$hasMigration): ?>
                <div class="sc-empty"><?= e($sc('migration_empty')) ?></div>
            <?php else: ?>
                <div class="sc-summary-grid sc-migration-summary">
                    <?php foreach ($migStates as $stateKey => $labelKey):
                        $count = isset($migGrouped[$stateKey]) ? count($migGrouped[$stateKey]) : 0; ?>
                        <div class="sc-summary-cell sc-mig-cell-<?= e($stateKey) ?>">
                            <span class="num"><?= (int)$count ?></span>
                            <span class="lbl"><?= e($sc($labelKey)) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php foreach ($migStates as $stateKey => $labelKey):
                    $group = $migGrouped[$stateKey] ?? [];
                    if ($group === []) continue;
                    $visibleMigrationGroup = array_slice($group, 0, $tokenDisplayLimit);
                    ?>
                    <details class="sc-migration-group" <?= in_array($stateKey, ['value_fix', 'domain_migration', 'classification_review', 'future_handoff'], true) ? 'open' : '' ?>>
                        <summary class="sc-migration-summary">
                            <?= e($sc($labelKey)) ?>
                            <span class="sc-token-count">(<?= (int)count($group) ?>)</span>
                        </summary>
                        <table class="sc-table gs-tool-scroll-table sc-migration-table">
                            <thead>
                                <tr>
                                    <th><?= e($sc('col_token')) ?></th>
<th><?= e($sc('migration_source_scope')) ?></th>
<th><?= e($sc('migration_source_owner')) ?></th>
<th><?= e($sc('migration_governance_domain')) ?></th>
<th><?= e($sc('migration_governance_required_domain')) ?></th>
                                    <th><?= e($sc('migration_state')) ?></th>
                                    <th><?= e($sc('migration_reason')) ?></th>
                                    <th><?= e($sc('migration_target_owner')) ?></th>
                                    <th><?= e($sc('migration_target_tool')) ?></th>
                                    <th><?= e($sc('migration_confidence')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($visibleMigrationGroup as $row): ?>
                                    <tr class="sc-mig-row-<?= e($stateKey) ?>">
                                        <td><code><?= e((string)($row['token'] ?? '')) ?></code></td>
<td><?= e($sourceScopeLabel((string)($row['source_scope'] ?? ''))) ?></td>
<td><?= e((string)($row['source_owner'] ?? '')) ?></td>
<td><?= e($govDomainHumanLabel((string)($row['governance_domain'] ?? 'not_applicable'))) ?></td>
<td><?= e($govDomainHumanLabel((string)($row['governance_required_domain'] ?? 'not_applicable'))) ?></td>
                                        <td><span class="sc-badge sc-badge-<?= e($stateKey) ?>"><?= e($sc('migration_state_' . $stateKey)) ?></span></td>
                                        <td><?= e((string)($row['migration_reason'] ?? '')) ?></td>
                                        <td><?= e((string)($row['target_owner'] ?? '')) ?></td>
                                        <td><code><?= e($targetToolLabel((string)($row['target_tool'] ?? ''))) ?></code></td>
                                        <td><span class="<?= e($confidenceClass((string)($row['migration_confidence'] ?? 'low'))) ?>"><?= e($confidenceLabel((string)($row['migration_confidence'] ?? ''))) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if (count($group) > count($visibleMigrationGroup)): ?>
                            <div class="sc-empty">
                                <?= str_replace(['{shown}', '{total}'], [(int)count($visibleMigrationGroup), (int)count($group)], $sc('showing_token_findings')) ?>
                            </div>
                        <?php endif; ?>
                    </details>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <!-- Token inventory grouped by repair workflow lane -->
        <section class="sc-section">
            <h3><?= e($sc('inventory_title')) ?></h3>
            <?php if ($tokens === []): ?>
                <div class="sc-empty"><?= e($sc('inventory_empty')) ?></div>
            <?php else: ?>
                <?php
                $workflowLabels = [
                    'theme' => $sc('workflow_theme'),
                    'shell_foundation' => $sc('workflow_shell'),
                    'special_effect' => $sc('workflow_effects'),
                    'structural' => $sc('workflow_structural'),
                    'print_pdf' => $sc('workflow_print'),
                    'unsupported' => $sc('workflow_unsupported'),
                ];
                $groupLabels = [
                    'token_consumer' => $sc('summary_token_consumers'),
                    'visual_literal' => $sc('summary_visual_literals'),
                    'token_definition' => $sc('summary_token_definitions'),
                    'structural_out_of_scope' => $sc('structural_section_title'),
                    'dynamic_unsupported' => $sc('summary_dynamic_unsupported'),
                    'print_pdf' => $sc('summary_print_pdf'),
                    'effect_candidate' => $sc('summary_effect_candidates'),
                ];
                ?>
                <?php foreach ($workflowOrder as $domainKey): ?>
                    <?php if (!isset($workflowGroupedTokens[$domainKey]) || $workflowGroupedTokens[$domainKey] === []) continue; ?>
                    <?php
                    $group = $workflowGroupedTokens[$domainKey];
                    $visibleGroup = array_slice($group, 0, $tokenDisplayLimit);
                    $label = isset($workflowLabels[$domainKey]) ? $workflowLabels[$domainKey] : $domainKey;
                    $categoryCounts = [];
                    foreach ($group as $categoryCountRow) {
                        $categoryKey = (string)($categoryCountRow['scan_category'] ?? '');
                        if ($categoryKey === '') {
                            $categoryKey = 'uncategorized';
                        }
                        $categoryCounts[$categoryKey] = ($categoryCounts[$categoryKey] ?? 0) + 1;
                    }
                    ksort($categoryCounts, SORT_STRING);
                    ?>
                    <details <?= in_array($domainKey, ['theme', 'shell_foundation'], true) ? 'open' : '' ?> class="sc-token-group">
                        <summary>
                            <?= e($label) ?>
                            <span class="sc-token-count">(<?= (int)count($group) ?>)</span>
                        </summary>
                        <div class="sc-lane-breakdown">
                            <?php foreach ($categoryCounts as $categoryKey => $categoryCount): ?>
                                <span class="sc-badge sc-badge-keyword"><?= e((isset($groupLabels[$categoryKey]) ? $groupLabels[$categoryKey] : $categoryKey) . ': ' . $categoryCount) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <div class="table-wrap">
                        <table class="sc-table gs-tool-scroll-table sc-token-table">
                            <thead>
                                <tr>
                                    <th><?= e($sc('col_token')) ?></th>
                                    <th><?= e($sc('col_property')) ?></th>
                                    <th><?= e($sc('col_value')) ?></th>
                                    <th><?= e($sc('col_type')) ?></th>
                                    <th><?= e($sc('col_file')) ?></th>
                                    <th><?= e($sc('col_selector')) ?></th>
                                    <th><?= e($sc('col_state')) ?></th>
                                    <th><?= e($sc('col_scope')) ?></th>
                                    <th><?= e($sc('col_category')) ?></th>
                                    <th><?= e($sc('col_repair_owner')) ?></th>
                                    <th><?= e($sc('col_tool')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($visibleGroup as $row): ?>
                                    <?php
                                    $aware = !empty($row['theme_aware']);
                                    $cls = $row['value_classification'] ?? ['type' => '', 'canonical' => ''];
                                    $property = (string)($row['property'] ?? $row['token'] ?? '');
                                    $tokenRefs = isset($row['token_references']) && is_array($row['token_references']) ? $row['token_references'] : [];
                                    $valueConstruct = (string)($row['value_construct'] ?? '');
                                    $lineStart = (int)($row['line_start'] ?? 0);
                                    $lineEnd = (int)($row['line_end'] ?? 0);
                                    $atRuleCtx = (string)($row['at_rule_context'] ?? '');
                                    $parseConf = (string)($row['parse_confidence'] ?? 'high');
                                    $sourceType = (string)($row['source_type'] ?? 'css');
                                    $scanCategory = (string)($row['scan_category'] ?? '');
                                    $complianceScope = (string)($row['compliance_scope'] ?? 'in_scope');
                                    $repairOwner = (string)($row['repair_owner'] ?? '');
                                    $recommendedTool = (string)($row['recommended_tool'] ?? '');
                                    $evidenceId = 'ev-' . md5($row['kind'] . '|' . $row['token'] . '|' . $row['file'] . '|' . $row['selector'] . '|' . $row['value']);
                                    ?>
                                    <tr>
                                        <td><code><?= e((string)($row['token'] ?? '')) ?></code></td>
                                        <td><code><?= e($property) ?></code></td>
                                        <td><code><?= e((string)($cls['canonical'] ?? '')) ?></code></td>
                                        <td><?= e((string)($cls['type'] ?? '')) ?></td>
                                        <td><?= e((string)($row['file'] ?? '')) ?></td>
                                        <td><?= e((string)($row['selector'] ?? '')) ?></td>
                                        <td>
                                            <?php if ($aware): ?>
                                                <span class="sc-badge sc-badge-aware"><?= e($sc('state_aware')) ?></span>
                                            <?php else: ?>
                                                <span class="sc-badge sc-badge-unaware"><?= e($sc('state_unaware')) ?></span>
                                            <?php endif; ?>
                                            <button type="button" class="sc-evidence-toggle" data-target="<?= e($evidenceId) ?>"><?= e($sc('evidence_details')) ?></button>
                                        </td>
                                        <td>
                                            <span class="sc-badge sc-badge-<?= $complianceScope === 'in_scope' ? 'aware' : 'unaware' ?>"><?= e($complianceScopeLabel($complianceScope)) ?></span>
                                        </td>
                                        <td><?= e(isset($groupLabels[$scanCategory]) ? $groupLabels[$scanCategory] : $scanCategory) ?></td>
                                        <td><?= e($repairOwner) ?></td>
                                        <td><code><?= e($targetToolLabel($recommendedTool)) ?></code></td>
                                    </tr>
                                    <tr id="<?= e($evidenceId) ?>" class="sc-evidence-row" hidden>
                                        <td colspan="11" class="sc-evidence-detail-cell">
                                            <dl class="sc-evidence-dl">
                                                <dt><?= e($sc('col_file')) ?></dt>
                                                <dd><code><?= e($row['file'] ?? '') ?></code> : <?= $lineStart ?> <?= $lineEnd > $lineStart ? '– ' . $lineEnd : '' ?></dd>
                                                <dt><?= e($sc('col_selector')) ?></dt>
                                                <dd><code><?= e((string)($row['selector'] ?? '')) ?></code></dd>
                                                <?php if ($atRuleCtx !== ''): ?>
                                                    <dt><?= e($sc('evidence_at_rule')) ?></dt>
                                                    <dd><code><?= e($atRuleCtx) ?></code></dd>
                                                <?php endif; ?>
                                                <dt><?= e($sc('col_property')) ?></dt>
                                                <dd><code><?= e($property) ?></code></dd>
                                                <dt><?= e($sc('evidence_raw_value')) ?></dt>
                                                <dd><code><?= e((string)($row['raw_value'] ?? '')) ?></code></dd>
                                                <dt><?= e($sc('evidence_normalized')) ?></dt>
                                                <dd><code><?= e((string)($row['normalized_value'] ?? '')) ?></code></dd>
                                                <?php if ($tokenRefs !== []): ?>
                                                    <dt><?= e($sc('evidence_token_refs')) ?></dt>
                                                    <dd><?php foreach ($tokenRefs as $tr): ?><code class="sc-inline-code"><?= e($tr) ?></code><?php endforeach; ?></dd>
                                                <?php endif; ?>
                                                <dt><?= e($sc('evidence_value_construct')) ?></dt>
                                                <dd><span class="sc-badge sc-badge-<?= e($valueConstruct) ?>"><?= e($sc('value_construct_' . $valueConstruct)) ?></span></dd>
                                                <dt><?= e($sc('evidence_source_type')) ?></dt>
                                                <dd><?= e($sourceType) ?></dd>
                                                <dt><?= e($sc('evidence_parse_confidence')) ?></dt>
                                                <dd><span class="sc-badge sc-badge-<?= $parseConf === 'high' ? 'aware' : ($parseConf === 'partial' ? 'conf-med' : 'conf-low') ?>"><?= e($sc('confidence_' . $parseConf)) ?></span></dd>
                                                <dt><?= e($sc('col_category')) ?></dt>
                                                <dd><span class="sc-badge sc-badge-<?= $scanCategory === 'token_consumer' ? 'aware' : ($scanCategory === 'visual_literal' ? 'conf-med' : 'unaware') ?>"><?= e(isset($groupLabels[$scanCategory]) ? $groupLabels[$scanCategory] : $scanCategory) ?></span></dd>
                                                <dt><?= e($sc('evidence_repair_lane')) ?></dt>
                                                <dd><?= e($repairLaneLabel((string)($row['repair_lane'] ?? ''))) ?></dd>
                                                <dt><?= e($sc('col_repair_owner')) ?></dt>
                                                <dd><?= e($repairOwner) ?></dd>
                                                <dt><?= e($sc('col_tool')) ?></dt>
                                                <dd><code><?= e($targetToolLabel($recommendedTool)) ?></code></dd>
                                            </dl>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                        <?php if (count($group) > count($visibleGroup)): ?>
                            <div class="sc-empty">
                                <?= str_replace(['{shown}', '{total}'], [(int)count($visibleGroup), (int)count($group)], $sc('showing_token_findings')) ?>
                            </div>
                        <?php endif; ?>
                    </details>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <!-- Out-of-scope structural evidence section (collapsed, always rendered if non-empty) -->
        <?php if ($structuralTokens !== []): ?>
        <section class="sc-section">
            <details>
                <summary class="sc-structural-summary">
                    <?= e($sc('structural_section_title')) ?>
                    <span class="sc-token-count">(<?= (int)count($structuralTokens) ?>)</span>
                </summary>
                <p class="sc-structural-desc"><?= e($sc('structural_section_desc')) ?></p>
                <table class="sc-table gs-tool-scroll-table sc-token-table">
                    <thead>
                        <tr>
                            <th><?= e($sc('col_file')) ?></th>
                            <th><?= e($sc('evidence_line')) ?></th>
                            <th><?= e($sc('col_property')) ?></th>
                            <th><?= e($sc('col_value')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($structuralTokens, 0, $structuralDisplayLimit) as $row): ?>
                            <?php
                            $property = (string)($row['property'] ?? $row['token'] ?? '');
                            $lineStart = (int)($row['line_start'] ?? 0);
                            $cls = $row['value_classification'] ?? ['canonical' => ''];
                            $evidenceId = 'ev-struct-' . md5($row['kind'] . '|' . $row['token'] . '|' . $row['file'] . '|' . $row['selector'] . '|' . $row['value']);
                            ?>
                            <tr>
                                <td><?= e((string)($row['file'] ?? '')) ?></td>
                                <td><?= $lineStart ?></td>
                                <td><code><?= e($property) ?></code></td>
                                <td><code><?= e((string)($cls['canonical'] ?? '')) ?></code>
                                    <button type="button" class="sc-evidence-toggle" data-target="<?= e($evidenceId) ?>"><?= e($sc('evidence_details')) ?></button>
                                </td>
                            </tr>
                            <tr id="<?= e($evidenceId) ?>" class="sc-evidence-row" hidden>
                                <td colspan="4" class="sc-evidence-detail-cell">
                                    <dl class="sc-evidence-dl">
                                        <dt><?= e($sc('col_file')) ?></dt>
                                        <dd><code><?= e($row['file'] ?? '') ?></code> : <?= $lineStart ?></dd>
                                        <dt><?= e($sc('col_selector')) ?></dt>
                                        <dd><code><?= e((string)($row['selector'] ?? '')) ?></code></dd>
                                        <dt><?= e($sc('col_property')) ?></dt>
                                        <dd><code><?= e($property) ?></code></dd>
                                        <dt><?= e($sc('evidence_raw_value')) ?></dt>
                                        <dd><code><?= e((string)($row['raw_value'] ?? '')) ?></code></dd>
                                        <dt><?= e($sc('col_category')) ?></dt>
                                        <dd><span class="sc-badge sc-badge-unaware"><?= e($sc('structural_section_title')) ?></span></dd>
                                    </dl>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (count($structuralTokens) > $structuralDisplayLimit): ?>
                    <div class="sc-empty">
                        <?= str_replace(['{shown}', '{total}'], [(int)$structuralDisplayLimit, (int)count($structuralTokens)], $sc('showing_structural')) ?>
                    </div>
                <?php endif; ?>
            </details>
        </section>
        <?php endif; ?>

        <!-- Repair candidates -->
        <section class="sc-section">
            <h3><?= e($sc('candidates_title')) ?></h3>
            <?php if ($candidates === []): ?>
                <div class="sc-empty"><?= e($sc('candidates_empty')) ?></div>
            <?php else: ?>
                <div class="table-wrap">
                <table class="sc-table gs-tool-scroll-table">
                    <thead>
                        <tr>
                            <th><?= e($sc('col_token')) ?></th>
                            <th><?= e($sc('col_current')) ?></th>
                            <th><?= e($sc('col_proposed')) ?></th>
                            <th><?= e($sc('col_light')) ?></th>
                            <th><?= e($sc('col_dark')) ?></th>
                            <th><?= e($sc('col_confidence')) ?></th>
                            <th><?= e($sc('col_occurrences')) ?></th>
                            <th><?= e($sc('col_affected_files')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($candidates, 0, $candidateDisplayLimit) as $c): ?>
                            <tr>
                                <td><code><?= e((string)($c['token'] ?? '')) ?></code></td>
                                <td><code><?= e((string)($c['current_value'] ?? '')) ?></code></td>
                                <td><code><?= e((string)($c['proposed_semantic'] ?? '')) ?></code></td>
                                <td><code><?= e((string)($c['proposed_light'] ?? '')) ?></code></td>
                                <td><code><?= e((string)($c['proposed_dark'] ?? '')) ?></code></td>
                                <td><span class="<?= e($confidenceClass((string)($c['confidence'] ?? 'low'))) ?>"><?= e($confidenceLabel((string)($c['confidence'] ?? ''))) ?></span></td>
                                <td><?= (int)($c['occurrences'] ?? 0) ?></td>
                                <td>
                                    <?php $files = isset($c['files']) && is_array($c['files']) ? $c['files'] : []; ?>
                                    <?php if ($files === []): ?>
                                        —
                                    <?php else: ?>
                                        <?php foreach ($files as $f): ?>
                                            <div><?= e((string)$f) ?></div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php if (count($candidates) > $candidateDisplayLimit): ?>
                    <div class="sc-empty">
                        <?= str_replace(['{shown}', '{total}'], [(int)$candidateDisplayLimit, (int)count($candidates)], $sc('showing_candidates')) ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <!-- Boundary findings -->
        <section class="sc-section">
            <h3><?= e($sc('boundary_title')) ?></h3>
            <?php if ($boundary === []): ?>
                <div class="sc-empty"><?= e($sc('boundary_empty')) ?></div>
            <?php else: ?>
                <table class="sc-table gs-tool-scroll-table">
                    <thead>
                        <tr>
                            <th><?= e($sc('boundary_kind')) ?></th>
                            <th><?= e($sc('col_token')) ?></th>
                            <th><?= e($sc('col_file')) ?></th>
                            <th><?= e($sc('boundary_message')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($boundary, 0, $boundaryDisplayLimit) as $b): ?>
                            <tr>
                                <td><?= e((string)($b['kind'] ?? '')) ?></td>
                                <td><code><?= e((string)($b['token'] ?? '')) ?></code></td>
                                <td><?= e((string)($b['file'] ?? '')) ?></td>
                                <td><?= e((string)($b['message'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (count($boundary) > $boundaryDisplayLimit): ?>
                    <div class="sc-empty">
                        <?= str_replace(['{shown}', '{total}'], [(int)$boundaryDisplayLimit, (int)count($boundary)], $sc('showing_boundary')) ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <!-- Affected files -->
        <section class="sc-section">
            <h3><?= e($sc('affected_title')) ?></h3>
            <?php if ($affected === []): ?>
                <div class="sc-empty"><?= e($sc('affected_empty')) ?></div>
            <?php else: ?>
                <table class="sc-table gs-tool-scroll-table">
                    <thead>
                        <tr>
                            <th><?= e($sc('col_file')) ?></th>
                            <th><?= e($sc('col_declarations')) ?></th>
                            <th><?= e($sc('summary_theme_aware')) ?></th>
                            <th><?= e($sc('summary_unaware')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($affected, 0, $affectedDisplayLimit) as $a): ?>
                            <tr>
                                <td><?= e((string)($a['file'] ?? '')) ?></td>
                                <td><?= (int)($a['declarations'] ?? 0) ?></td>
                                <td><?= (int)($a['theme_aware'] ?? 0) ?></td>
                                <td><?= (int)($a['unaware'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (count($affected) > $affectedDisplayLimit): ?>
                    <div class="sc-empty">
                        <?= str_replace(['{shown}', '{total}'], [(int)$affectedDisplayLimit, (int)count($affected)], $sc('showing_affected_files')) ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <!-- Repair plan preview -->
        <section class="sc-section sc-plan">
            <h3><?= e($sc('plan_title')) ?></h3>
            <p class="sc-plan-hint"><?= e($sc($guardedExecutionEnabled ? 'plan_hint_guarded' : 'plan_hint_readonly')) ?></p>
            <p class="sc-plan-hint sc-plan-hint-final"><?= e($sc('plan_copy_hint')) ?></p>
            <textarea readonly aria-label="<?= e($sc('plan_title')) ?>"><?= e((string)json_encode([
                'scope' => $scope,
                'owner_key' => $ownerKey,
                'scope_descriptor' => $scopeDescriptor,
                'summary' => $summary,
                'theme_repair_proposals' => [
                    'summary' => $themeProposalSummary,
                    'readiness' => $themeProposalReadiness,
                    'queues' => [
                        'ready_for_future_guarded_apply' => array_slice((array)($themeProposalQueues['ready_for_future_guarded_apply'] ?? []), 0, $diagnosticJsonLimit),
                        'manual_semantic_decisions' => array_slice((array)($themeProposalQueues['manual_semantic_decisions'] ?? []), 0, $diagnosticJsonLimit),
                        'classification_and_accessibility_review' => array_slice((array)($themeProposalQueues['classification_and_accessibility_review'] ?? []), 0, $diagnosticJsonLimit),
                        'future_tool_handoffs' => array_slice((array)($themeProposalQueues['future_tool_handoffs'] ?? []), 0, $diagnosticJsonLimit),
                    ],
                    'items_total' => count($themeProposalItems),
                    'future_apply_enabled' => $guardedExecutionEnabled,
                    'execution_capability' => $executionCapability,
                ],
                'candidates' => array_slice($candidates, 0, $diagnosticJsonLimit),
                'candidates_total' => count($candidates),
                'boundary_findings' => array_slice($boundary, 0, $diagnosticJsonLimit),
                'boundary_findings_total' => count($boundary),
                'governance' => $governance,
                'apply_authorized' => false,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></textarea>
        </section>

        <!-- Governance -->
        <section class="sc-section">
            <h3><?= e($sc('governance_title')) ?></h3>
            <dl class="sc-gov-list">
                <dt><?= e($sc('gov_scan_timestamp')) ?></dt><dd><?= e((string)($governance['scan_timestamp'] ?? '')) ?></dd>
                <dt><?= e($sc('gov_scope')) ?></dt><dd><?= e((string)($governance['scope'] ?? '')) ?></dd>
                <dt><?= e($sc('gov_scanned_root')) ?></dt><dd><?= e((string)($governance['scanned_root'] ?? '')) ?></dd>
                <dt><?= e($sc('gov_files_scanned')) ?></dt><dd><?= (int)($governance['files_scanned'] ?? 0) ?></dd>
                <dt><?= e($sc('gov_exclusions')) ?></dt><dd>
                    <?php $ex = isset($governance['exclusions']) && is_array($governance['exclusions']) ? $governance['exclusions'] : []; ?>
                    <?= e(implode(', ', array_map('strval', $ex))) ?>
                </dd>
                <dt><?= e($sc('gov_writes')) ?></dt><dd><?= e((string)($governance['writes_performed'] ?? '')) ?></dd>
                <dt><?= e($sc('gov_snapshot')) ?></dt><dd><?= e((string)($governance['snapshot_written'] ?? '')) ?></dd>
                <dt><?= e($sc('gov_apply')) ?></dt><dd><?= !empty($governance['apply_authorized']) ? 'true' : 'false' ?></dd>
                <dt><?= e($sc('gov_phase')) ?></dt><dd><?= e((string)($governance['tool_phase'] ?? '')) ?></dd>
            </dl>
        </section>
        </details>

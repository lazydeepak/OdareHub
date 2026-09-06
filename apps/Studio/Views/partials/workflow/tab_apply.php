      <div id="tab-apply" class="tab-panel">
        <div class="u-style-7edcda98a8">
          <button class="u-style-1c13226961" type="button" id="btn-back-changes">← <?= e($gs('tab.changes')) ?></button>
        </div>

        <?php
          $applyCompilePlanValue = $result['compile_plan'] ?? null;
          $applyCompilePlan = is_array($applyCompilePlanValue) ? $applyCompilePlanValue : [];
          $applyHasCompile = $applyCompilePlan !== [];
          $applyHasAnalyze = $result !== null && (array_key_exists('data_contract', $result) || array_key_exists('dependency_graph', $result));
          $applyHasImpact = $applyHasAnalyze;
          $applyApprovalPayloadValue = $result['approval_payload'] ?? null;
          $applyApprovalSummaryValue = $result['approval_summary'] ?? null;
          $applyApprovalPayload = is_array($applyApprovalPayloadValue) ? $applyApprovalPayloadValue : [];
          $applyApprovalSummary = is_array($applyApprovalSummaryValue) ? $applyApprovalSummaryValue : [];
          $applyPublishGate = is_array($result['publish_gate'] ?? null) ? $result['publish_gate'] : [];
          $applyPublishGateErrors = is_array($applyPublishGate['errors'] ?? null) ? $applyPublishGate['errors'] : [];
          $applyPipelineGuard = is_array($result['pipeline_guard'] ?? null) ? $result['pipeline_guard'] : [];
          $applyPipelineErrors = is_array($applyPipelineGuard['errors'] ?? null) ? $applyPipelineGuard['errors'] : [];
          $applyRuntimeGateErrors = array_values(array_unique(array_merge($applyPublishGateErrors, $applyPipelineErrors)));
          $applyDecision = (string)($applyApprovalPayload['decision'] ?? 'approved');
          $applyReason = (string)($applyApprovalPayload['reason'] ?? '');
          $applyHighRiskCount = (int)($applyApprovalSummary['high_risk'] ?? 0);
          $applyRiskLabel = $gs('pipeline_risk_medium');
          $applyRiskChipClass = 'warning';
          if ($applyHighRiskCount > 0) {
              $applyRiskLabel = $gs('impact_high');
              $applyRiskChipClass = 'danger';
          }
          $applyNextStepMessageKey = $applyHasAnalyze
              ? 'apply_next_step_ready_message'
              : 'apply_next_step_message';
        ?>

        <section class="card">
          <h3><?= e($gs('apply_tab_title')) ?></h3>
          <p class="muted"><?= e($gs('apply_tab_subtitle')) ?></p>
          <div class="note info u-style-f7b2ba090a"><?= e($gs($applyNextStepMessageKey)) ?></div>

          <div class="apply-step-flow" aria-label="Apply flow steps">
            <span class="step">✔ <?= e($gs('apply_step_1_edit')) ?></span>
            <span class="arrow">→</span>
            <span class="step"><?= e($gs('apply_step_2_analyze')) ?></span>
            <span class="arrow">→</span>
            <span class="step"><?= e($gs('apply_step_3_changes')) ?></span>
            <span class="arrow">→</span>
            <span class="step"><?= e($gs('apply_step_4_apply')) ?></span>
          </div>

          <?php if ($applyHasCompile): ?>
            <form method="post" action="/apps/studio/approval-preview" class="stack" id="gs-apply-form"
              data-analyze-ok="<?= $applyHasAnalyze ? '1' : '0' ?>"
              data-err-title="<?= e($gs('apply_gate_failed_title')) ?>"
              data-err-analyze="<?= e($gs('apply_gate_error_analyze')) ?>"
              data-err-confirm="<?= e($gs('apply_gate_error_confirm')) ?>"
              data-err-reason="<?= e($gs('apply_gate_error_reason')) ?>">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="studio_mode" value="<?= e($studioMode === 'edit_existing' ? 'edit_existing' : 'create_new') ?>">
              <input type="hidden" name="se_previous_bundle" value="<?= e($getInput('se_previous_bundle', $inputs)) ?>">
              <input type="hidden" name="app_manifest" value="<?= e($getInput('app_manifest', $inputs)) ?>">
              <input type="hidden" name="module_manifest" value="<?= e($getInput('module_manifest', $inputs)) ?>">
              <input type="hidden" name="view_definition" value="<?= e($getInput('view_definition', $inputs)) ?>">
              <input type="hidden" name="navigation_definition" value="<?= e($getInput('navigation_definition', $inputs)) ?>">

              <div class="note info u-style-86338c943e">
                <strong><?= e($gs('apply_gate_requirements_title')) ?></strong>
                <ul class="u-style-45e8195c24">
                  <li><?= e($gs('apply_gate_requirements_analyze')) ?></li>
                  <li><?= e($gs('apply_gate_requirements_confirm')) ?></li>
                  <li><?= e($gs('apply_gate_requirements_reason')) ?></li>
                </ul>
              </div>

              <?php if ($applyRuntimeGateErrors !== []): ?>
                <div class="note warning u-style-86338c943e">
                  <strong><?= e($gs('apply_gate_runtime_errors')) ?></strong>
                  <ul class="u-style-45e8195c24">
                    <?php foreach ($applyRuntimeGateErrors as $runtimeGateError): ?>
                      <?php
                        $runtimeGateErrorText = t((string)$runtimeGateError);
                        if ($runtimeGateErrorText === (string)$runtimeGateError) {
                            $runtimeGateErrorText = $gs((string)$runtimeGateError);
                        }
                      ?>
                      <li><?= e($runtimeGateErrorText) ?></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              <?php endif; ?>

              <div id="gs-apply-gate-errors" class="note warning u-style-c8be1ccba6"></div>

              <h4><?= e($gs('apply_metadata_title')) ?></h4>
              <label class="form-label" for="gs-apply-decision"><?= e($gs('decision')) ?></label>
              <select class="form-input" id="gs-apply-decision" name="decision">
                <option value="approved"<?= $applyDecision === 'approved' ? ' selected' : '' ?>><?= e($gs('approve')) ?></option>
                <option value="rejected"<?= $applyDecision === 'rejected' ? ' selected' : '' ?>><?= e($gs('reject')) ?></option>
              </select>

              <label class="form-label" for="gs-apply-reason"><?= e($gs('reason')) ?></label>
              <textarea class="form-input" id="gs-apply-reason" name="reason" rows="3" required><?= e($applyReason) ?></textarea>

              <label class="form-label u-style-18ae89f4a0">
                <input type="checkbox" id="gs-apply-confirmation" name="impact_confirmation" value="1"<?= $getInput('impact_confirmation', $inputs) === '1' ? ' checked' : '' ?>>
                <?= e($gs('apply_confirmation_label')) ?>
              </label>

              <label class="form-label u-style-18ae89f4a0" data-flow-scope="upgrade">
                <input type="checkbox" name="risk_acknowledged" value="1"<?= !empty($applyApprovalPayload['risk_acknowledged']) ? ' checked' : '' ?>>
                <?= e($gs('risk_ack_label')) ?>
              </label>

              <label class="form-label u-style-18ae89f4a0" data-flow-scope="upgrade">
                <input type="checkbox" name="migration_override" value="1"<?= $getInput('migration_override', $inputs) === '1' ? ' checked' : '' ?>>
                <?= e($gs('migration_override_label')) ?>
              </label>

              <label class="form-label" for="gs-apply-migration-reason" data-flow-scope="upgrade"><?= e($gs('migration_override_reason')) ?></label>
              <textarea class="form-input" id="gs-apply-migration-reason" name="migration_override_reason" rows="2" data-flow-scope="upgrade"><?= e($getInput('migration_override_reason', $inputs)) ?></textarea>

              <div class="form-actions">
                <button class="btn" type="submit"><?= e($gs('preview_approval')) ?></button>
                <button id="btn-apply" class="primary btn btn-primary btn-primary-action" type="submit" formaction="/apps/studio/apply-snapshot"><?= e($gs('primary.apply_apply_changes')) ?></button>
              </div>
              <div id="gs-rollback-snapshot-notice" class="note" style="display:none">
                <?= e($gsBatch1('rollback_snapshot_notice')) ?>
              </div>
            </form>
          <?php else: ?>
            <div class="note warning">
              <?= e($gs('apply_no_data')) ?>
            </div>
          <?php endif; ?>
        </section>
      </div>

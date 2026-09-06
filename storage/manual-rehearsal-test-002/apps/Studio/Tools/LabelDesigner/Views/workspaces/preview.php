          <?php if (!empty($selectedReadinessSummary['is_label_lifecycle_owner'])): ?>
          <!-- Phase 14: Refocused Preview workspace (lifecycle owner) -->
          <div class="ld-workspace" data-workspace="preview" id="ld-workspace-preview">
          <dt><span><?= e($ld('preview_title')) ?></span></dt>
          <dd class="gs-studio-tools-cards">
            <div class="gs-studio-tool-card">
              <div class="gs-studio-tool-card-title-row">
                <span class="gs-studio-tool-card-title"><?= e($ld('preview_title')) ?></span>
              </div>
              <p class="gs-studio-tool-card-purpose"><?= e($ld('preview_subtitle')) ?></p>
              <div class="ld-status-row">
                <?php if ($selectedOwnerKey !== ''): ?>
                  <span class="ld-badge ld-badge-ready"><?= e($ld('preview_prerequisites_owner')) ?>: <?= e($selectedOwnerKey) ?></span>
                <?php else: ?>
                  <span class="ld-badge ld-badge-missing"><?= e($ld('preview_prerequisites_missing')) ?>: <?= e($ld('preview_prerequisites_owner')) ?></span>
                <?php endif; ?>
                <?php if ($overviewContextKey !== ''): ?>
                  <span class="ld-badge ld-badge-ready"><?= e($ld('preview_prerequisites_context')) ?>: <?= e($overviewContextKey) ?></span>
                <?php else: ?>
                  <span class="ld-badge ld-badge-missing"><?= e($ld('preview_prerequisites_missing')) ?>: <?= e($ld('preview_prerequisites_context')) ?></span>
                <?php endif; ?>
                <?php if ($overviewTemplateKey !== ''): ?>
                  <span class="ld-badge ld-badge-ready"><?= e($ld('preview_prerequisites_template')) ?>: <?= e($overviewTemplateKey) ?></span>
                <?php else: ?>
                  <span class="ld-badge ld-badge-missing"><?= e($ld('preview_prerequisites_missing')) ?>: <?= e($ld('preview_prerequisites_template')) ?></span>
                <?php endif; ?>
                <span class="ld-badge"><?= e($ld('preview_prerequisites_rules')) ?>: <?= $overviewRuleCount ?></span>
              </div>

              <!-- Preview setup -->
              <h4 style="margin:16px 0 4px"><?= e($ld('preview_setup_title')) ?></h4>
              <?php if (empty($previewContextOptions)): ?>
                <p class="gs-studio-tool-card-purpose"><?= e($ld('preview_unavailable_title')) ?></p>
                <p class="gs-studio-tool-card-purpose"><?= e($ld('preview_unavailable_detail')) ?></p>
                <p class="gs-studio-tool-card-purpose">
                  <a href="?workspace=build&amp;owner=<?= e(urlencode($selectedDataSourceOwnerKey)) ?>"><?= e($ld('preview_unavailable_action_build')) ?></a>
                </p>
                <?php $renderProgressCard(); ?>
                <div class="ld-next-actions">
                  <a class="ld-next-action" href="/apps/studio/tools/label-designer<?= $buildUrl(['workspace' => 'build']) ?>"><?= $ld('next_action_continue_build') ?></a>
                  <a class="ld-next-action ld-next-action-secondary" href="/apps/studio/tools/label-designer<?= $buildUrl(['workspace' => 'governance']) ?>"><?= $ld('next_action_open_governance') ?></a>
                </div>
              <?php else: ?>
                <form method="post" action="/apps/studio/tools/label-designer/preview/render">
                  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="workspace" value="<?= $activeWorkspace ?>">
                  <input type="hidden" name="owner" value="<?= e($selectedDataSourceOwnerKey) ?>">
                  <input type="hidden" name="context_key" value="<?= e($previewSelectedContextId) ?>">
                  <input type="hidden" name="template_key" value="<?= e($previewSelectedTemplateId) ?>">
                  <p class="gs-studio-tool-card-purpose">
                    <?= e($ld('preview_context')) ?>:
                    <select name="preview_context_id" aria-label="<?= e($ld('preview_context')) ?>">
                      <?php foreach ($previewContextOptions as $contextOpt): ?>
                        <?php
                        $optContextId = (string)($contextOpt['context_id'] ?? '');
                        $optContextKey = (string)($contextOpt['context_key'] ?? '');
                        $optOwnerKey = (string)($contextOpt['owner_key'] ?? '');
                        ?>
                        <?php $ctxSelected = ($previewSelectedContextId !== '' && $optContextId === $previewSelectedContextId) ? ' selected="selected"' : ''; ?>
                        <option value="<?= e($optContextId) ?>"<?= $ctxSelected ?>>
                          <?= e($optOwnerKey) ?> :: <?= e($optContextKey) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </p>
                  <p class="gs-studio-tool-card-purpose">
                    <?= e($ld('preview_template')) ?>:
                    <select name="preview_template_id" aria-label="<?= e($ld('preview_template')) ?>">
                      <option value="">— <?= e($ld('preview_template')) ?> —</option>
                      <?php foreach ($previewTemplateOptions as $templateOpt): ?>
                        <?php
                        $optTemplateId = (string)($templateOpt['template_id'] ?? '');
                        $optTemplateKey = (string)($templateOpt['template_key'] ?? '');
                        $optOwnerKey = (string)($templateOpt['owner_key'] ?? '');
                        $optSelected = ($previewSelectedTemplateId !== '' && $optTemplateId === $previewSelectedTemplateId)
                            ? ' selected="selected"'
                            : '';
                        ?>
                        <option value="<?= e($optTemplateId) ?>"<?= $optSelected ?>>
                          <?= e($optOwnerKey) ?> :: <?= e($optTemplateKey) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </p>
                  <p class="gs-studio-tool-card-purpose">
                    <label>
                      <input type="checkbox" name="load_rules" value="1"<?= $previewRulesEnabled === '1' ? ' checked="checked"' : '' ?>>
                      <?= e($ld('preview_load_rules')) ?>
                    </label>
                  </p>
                  <button type="submit"><?= e($ld('preview_button')) ?></button>
                </form>
              <?php endif; ?>

              <!-- Rendered label preview -->
              <?php if ($previewResult !== []): ?>
                <hr style="margin:16px 0;border:none;border-top:1px solid var(--border-subtle,#ddd)">
                <h4 style="margin:8px 0 4px"><?= e($ld('preview_render_title')) ?></h4>
                <p class="gs-studio-tool-card-purpose">
                  <?= $previewRenderAllowed
                      ? '<span style="color:var(--status-positive,#2e7d32);font-weight:600">' . e($ld('preview_render_allowed')) . '</span>'
                      : '<span style="color:var(--status-critical,#d32f2f);font-weight:600">' . e($ld('preview_render_blocked')) . '</span>' ?>
                  —
                  <?= e($ld('preview_field_count')) ?>: <?= count($previewResolvedFields) ?>
                  —
                  <?= e($ld('preview_sample_data')) ?>: <?= count($previewSampleData) ?>
                </p>

                <?php
                $consolidatedPreviewHtml = $previewHtml;
                $sandboxEffectActive = false;
                $rsModifiedHtmlForConsolidation = trim((string)($ruleSandboxResult['modified_html'] ?? ''));
                if ($rsModifiedHtmlForConsolidation !== '') {
                    $consolidatedPreviewHtml = $rsModifiedHtmlForConsolidation;
                    $sandboxEffectActive = true;
                }
                ?>
                <?php if ($sandboxEffectActive): ?>
                  <p class="gs-studio-tool-card-purpose">
                    <small><strong><?= e($ld('preview_rules_applied')) ?></strong></small>
                  </p>
                <?php endif; ?>
                <div class="label-renderer-preview-container" style="margin:8px 0">
                  <?= $consolidatedPreviewHtml ?>
                </div>

                <!-- Rule-effect status table -->
                <?php if ($previewRuleCount > 0): ?>
                  <h4 style="margin:12px 0 4px"><?= e($ld('preview_rules_applied')) ?> (<?= $previewRuleCount ?> found, <?= $previewRuleMatchedCount ?> matched)</h4>
                  <div style="overflow-x:auto;margin:8px 0">
                    <table style="width:100%;border-collapse:collapse;font-size:0.85em">
                      <thead>
                        <tr>
                          <th style="text-align:left;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)">Rule Key</th>
                          <th style="text-align:left;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)">Condition Evaluations</th>
                          <th style="text-align:left;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)">Result</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($previewRuleEvaluation as $eval): ?>
                          <?php
                          $evalRuleKey = (string)($eval['rule_key'] ?? '');
                          $condEvals = isset($eval['condition_evaluations']) && is_array($eval['condition_evaluations'])
                              ? $eval['condition_evaluations']
                              : [];
                          $allMatched = !empty($eval['all_conditions_matched']);
                          ?>
                          <tr>
                            <td style="padding:3px 8px;border-bottom:1px solid var(--border-subtle,#eee)">
                              <code><?= e($evalRuleKey) ?></code>
                            </td>
                            <td style="padding:3px 8px;border-bottom:1px solid var(--border-subtle,#eee)">
                              <?php if ($condEvals !== []): ?>
                                <ul style="margin:0;padding-left:16px">
                                  <?php foreach ($condEvals as $ce): ?>
                                    <li>
                                      <code><?= e((string)($ce['field_key'] ?? '')) ?></code>
                                      <?= e((string)($ce['operator'] ?? '')) ?>
                                      "<?= e((string)($ce['compare_value'] ?? '')) ?>
                                      → <?= !empty($ce['matched'])
                                          ? '<span style="color:var(--status-positive,#2e7d32);font-weight:600">MATCH</span>'
                                          : '<span style="color:var(--status-critical,#d32f2f);font-weight:600">NO MATCH</span>' ?>
                                    </li>
                                  <?php endforeach; ?>
                                </ul>
                              <?php else: ?>
                                <em>No conditions</em>
                              <?php endif; ?>
                            </td>
                            <td style="padding:3px 8px;border-bottom:1px solid var(--border-subtle,#eee);font-weight:600">
                              <?php if ($allMatched): ?>
                                <span style="color:var(--status-positive,#2e7d32)">Applied</span>
                              <?php else: ?>
                                <span style="color:var(--text-muted,#999)">Skipped</span>
                              <?php endif; ?>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php elseif ($previewResult !== [] && $previewRuleCount === 0 && $previewRenderAllowed): ?>
                  <p class="gs-studio-tool-card-purpose"><small><?= e($ld('preview_rules_none')) ?></small></p>
                <?php endif; ?>

                <!-- Diagnostics (collapsed) -->
                <details style="margin:12px 0">
                  <summary style="cursor:pointer;font-weight:600"><?= e($ld('preview_diagnostics_title')) ?></summary>
                  <?php
                  $panelItems = [];
                  foreach ($previewDiagnostics as $diag) {
                      $sev = (string)($diag['severity'] ?? 'PASS');
                      $code = trim((string)($diag['check_key'] ?? '') . ' ' . (string)($diag['label'] ?? ''));
                      $panelItems[] = ['severity' => $sev, 'code' => $code, 'message' => (string)($diag['message'] ?? '')];
                  }
                  ?>
                  <?php if ($panelItems !== []): ?>
                    <?php require __DIR__ . '/../partials/diagnostics-panel.php'; ?>
                  <?php else: ?>
                    <p class="gs-studio-tool-card-purpose"><small><?= e($ld('preview_diagnostics_pass')) ?></small></p>
                  <?php endif; ?>
                </details>
              <?php else: ?>
                <!-- No preview rendered yet: empty state -->
                <hr style="margin:16px 0;border:none;border-top:1px solid var(--border-subtle,#ddd)">
                <h4 style="margin:8px 0 4px"><?= e($ld('preview_render_title')) ?></h4>
                <p class="gs-studio-tool-card-purpose"><?= e($ld('preview_no_preview_title')) ?></p>
                <p class="gs-studio-tool-card-purpose"><small><?= e($ld('preview_no_preview_detail')) ?></small></p>
              <?php endif; ?>

              <!-- Cross-workspace link to Rules sandbox -->
              <hr style="margin:16px 0;border:none;border-top:1px solid var(--border-subtle,#ddd)">
              <p class="gs-studio-tool-card-purpose" style="font-size:0.9em">
                <?= e($ld('rules_test_note')) ?>
                <a href="?workspace=rules&amp;owner=<?= e(urlencode($selectedDataSourceOwnerKey)) ?>#ld-workspace-rules-sandbox"><?= e($ld('rules_test_title')) ?></a>
              </p>
            </div>

            <?php $renderProgressCard(); ?>
            <div class="ld-next-actions">
              <?php if (!$wsProgress['has_context']): ?>
                <a class="ld-next-action" href="/apps/studio/tools/label-designer<?= $buildUrl(['workspace' => 'build']) ?>"><?= $ld('next_action_create_context') ?></a>
              <?php elseif (!$wsProgress['has_template']): ?>
                <a class="ld-next-action" href="/apps/studio/tools/label-designer<?= $buildUrl(['workspace' => 'build']) ?>"><?= $ld('next_action_create_template') ?></a>
              <?php elseif (!$wsProgress['has_rules']): ?>
                <a class="ld-next-action" href="/apps/studio/tools/label-designer<?= $buildUrl(['workspace' => 'rules']) ?>"><?= $ld('next_action_continue_rules') ?></a>
              <?php else: ?>
                <a class="ld-next-action" href="/apps/studio/tools/label-designer<?= $buildUrl(['workspace' => 'governance']) ?>"><?= $ld('next_action_open_governance') ?></a>
              <?php endif; ?>
            </div>
          </dd>
          </div>
          <?php else: ?>
          <!-- Phase 14: Non-lifecycle owner parent guidance for Preview -->
          <div class="ld-workspace" data-workspace="preview" id="ld-workspace-preview-parent">
          <dt><span><?= e($ld('preview_title')) ?></span></dt>
          <dd class="gs-studio-tools-cards">
            <div class="gs-studio-tool-card">
              <div class="gs-studio-tool-card-title-row">
                <span class="gs-studio-tool-card-title"><?= e($ld('manufacturing_guidance_title')) ?></span>
              </div>
              <p class="gs-studio-tool-card-purpose"><?= e($ld('manufacturing_guidance_explanation')) ?></p>
              <p class="gs-studio-tool-card-purpose">
                <a href="?workspace=overview&amp;owner=Manufacturing%2FProducts"><?= e($ld('manufacturing_guidance_action')) ?></a>
              </p>
            </div>
          </dd>
          </div>
          <?php endif; ?>

          <?php if (!empty($selectedReadinessSummary['is_label_lifecycle_owner'])): ?>
          <div class="ld-workspace" data-workspace="rules" id="ld-workspace-rules-compatibility">
          <!-- Phase 13: Rules workspace - header section -->
          <dt><span><?= e($ld('rules_title')) ?></span></dt>
          <dd class="gs-studio-tools-cards">
            <div class="gs-studio-tool-card">
              <div class="gs-studio-tool-card-title-row">
                <span class="gs-studio-tool-card-title"><?= e($ld('rules_status_title')) ?></span>
              </div>
              <p class="gs-studio-tool-card-purpose"><?= e($ld('rules_subtitle')) ?></p>
              <div class="ld-status-row">
                <span class="ld-badge"><?= e($ld('rules_owner_title')) ?>: <?= e($selectedDataSourceOwnerKey) ?></span>
                <span class="ld-badge"><?= e($ld('rules_lifecycle_label')) ?>: <?= !empty($selectedReadinessSummary['is_label_lifecycle_owner']) ? 'lifecycle' : 'non-lifecycle' ?></span>
              </div>
              <?php if (!empty($ruleCreateContexts) && !empty($ruleCreateTemplates)): ?>
                <p class="gs-studio-tool-card-purpose">
                  <strong><?= e($ld('rules_can_create')) ?></strong>
                </p>
              <?php elseif (empty($ruleCreateContexts)): ?>
                <p class="gs-studio-tool-card-purpose">
                  <strong><?= e($ld('rules_cannot_create_no_context')) ?></strong>
                </p>
              <?php elseif (empty($ruleCreateTemplates)): ?>
                <p class="gs-studio-tool-card-purpose">
                  <strong><?= e($ld('rules_cannot_create_no_template')) ?></strong>
                </p>
              <?php endif; ?>
            </div>
          </dd>
          </div>

          <div class="ld-workspace" data-workspace="rules" id="ld-workspace-rules-existing">
          <!-- Phase 16.3: Existing Rules table -->
          <dt><span><?= e($ld('rules_inventory_title')) ?></span></dt>
          <dd class="gs-studio-tools-cards">
            <?php if (empty($existingRules)): ?>
            <div class="gs-studio-tool-card">
              <div class="gs-studio-tool-card-title-row">
                <span class="gs-studio-tool-card-title"><?= e($ld('rules_empty_title')) ?></span>
              </div>
              <p class="gs-studio-tool-card-purpose"><?= e($ld('rules_empty_guidance')) ?></p>
              <p class="gs-studio-tool-card-purpose"><a href="#ld-workspace-rules-create" class="button-link"><?= e($ld('rules_empty_action')) ?></a></p>
            </div>
            <?php else: ?>
               <div class="ld-table-wrap"><table class="ld-existing-table">
                <thead>
                  <tr>
                    <th><?= e($ld('build_existing_templates_key')) ?></th>
                    <th><?= e($ld('rules_inventory_context')) ?></th>
                    <th><?= e($ld('rules_inventory_template')) ?></th>
                    <th><?= e($ld('rules_inventory_priority')) ?></th>
                    <th><?= e($ld('rules_inventory_condition')) ?></th>
                    <th><?= e($ld('rules_inventory_effect')) ?></th>
                    <th><?= e($ld('rules_inventory_enabled')) ?></th>
                    <th><?= e($ld('build_existing_action_view')) ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($existingRules as $r): ?>
                  <?php
                  $rKey = (string)($r['rule_key'] ?? '');
                  $rContext = (string)($r['context_key'] ?? '');
                  $rTemplate = (string)($r['template_key'] ?? '');
                  $rPriority = (string)($r['priority'] ?? '');
                  $rEnabled = !empty($r['enabled']);
                  $rConditions = isset($r['conditions']) && is_array($r['conditions']) ? $r['conditions'] : [];
                  $rEffects = isset($r['effects']) && is_array($r['effects']) ? $r['effects'] : [];
                  $rCondSummary = '';
                  foreach ($rConditions as $rc) {
                      if (!is_array($rc)) continue;
                      $rcf = (string)($rc['field_key'] ?? '');
                      $rco = str_replace('_', ' ', (string)($rc['operator'] ?? ''));
                      $rcv = (string)($rc['value'] ?? '');
                      $rCondSummary = $rcf . ' ' . $rco . ($rcv !== '' ? ' "' . $rcv . '"' : '');
                  }
                  $rEffSummary = '';
                  foreach ($rEffects as $re) {
                      if (!is_array($re)) continue;
                      $ret = (string)($re['type'] ?? '');
                      $rev = (string)($re['value'] ?? '');
                      $rEffSummary = $ret . ($rev !== '' ? ' "' . $rev . '"' : '');
                  }
                  $rViewUrl = '/apps/studio/tools/label-designer?workspace=rules'
                      . '&owner=' . urlencode($selectedDataSourceOwnerKey)
                      . '&view_rule=' . urlencode($rKey);
                  ?>
                  <tr>
                    <td><code><?= e($rKey) ?></code></td>
                    <td><code><?= e($rContext) ?></code></td>
                    <td><code><?= e($rTemplate) ?></code></td>
                    <td><?= e($rPriority ?: '—') ?></td>
                    <td><code><?= e($rCondSummary ?: '—') ?></code></td>
                    <td><code><?= e($rEffSummary ?: '—') ?></code></td>
                    <td><span class="ld-badge ld-badge-<?= $rEnabled ? 'ready' : 'muted' ?>"><?= $rEnabled ? e($ld('rules_inventory_enabled')) : e($ld('status_disabled')) ?></span></td>
                    <td class="ld-actions-cell">
                      <a href="<?= e($rViewUrl) ?>" class="ld-action-link" title="<?= e($ld('build_existing_action_view')) ?>"><?= e($ld('build_existing_action_view')) ?></a>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table></div>
            <?php endif; ?>
          </dd>
          </div>

          <?php if ($viewRule !== '' || $selectedRule !== null): ?>
          <div class="ld-workspace" data-workspace="rules" id="ld-workspace-rules-inspector">
          <dt class="ld-workspace-hidden-heading"><span><?= e($ld('rules_inspector_title')) ?></span></dt>
          <dd class="gs-studio-tools-cards">
            <!-- Phase 16.3: Rule inspector -->
            <?php if ($viewRule !== '' && $selectedRule === null): ?>
              <div class="ld-inspector-not-found">
                <div class="ld-inspector-not-found-title"><?= e($ld('rules_inspector_not_found')) ?></div>
                <p><a href="?workspace=rules&amp;owner=<?= e(urlencode($selectedDataSourceOwnerKey)) ?>" class="ld-inspector-return ld-action-link"><?= e($ld('rules_inspector_return_to_rules')) ?></a></p>
              </div>
            <?php endif; ?>

            <?php if ($selectedRule !== null): ?>
            <div class="ld-inspector-card">
              <div class="ld-inspector-card-title"><?= e($ld('rules_inspector_title')) ?></div>
              <div class="ld-inspector-meta">
                <span class="ld-inspector-meta-item"><span class="ld-inspector-meta-label"><?= e($ld('build_context_inspector_owner')) ?>:</span> <span class="ld-inspector-meta-value"><?= e($selectedDataSourceOwnerKey) ?></span></span>
                <span class="ld-inspector-meta-item"><span class="ld-inspector-meta-label"><?= e($ld('rules_inspector_rule_key')) ?>:</span> <span class="ld-inspector-meta-value"><?= e((string)($selectedRule['rule_key'] ?? '')) ?></span></span>
                <span class="ld-inspector-meta-item"><span class="ld-inspector-meta-label"><?= e($ld('build_context_inspector_context_key')) ?>:</span> <span class="ld-inspector-meta-value"><?= e((string)($selectedRule['context_key'] ?? '')) ?></span></span>
                <span class="ld-inspector-meta-item"><span class="ld-inspector-meta-label"><?= e($ld('build_template_inspector_template_key')) ?>:</span> <span class="ld-inspector-meta-value"><?= e((string)($selectedRule['template_key'] ?? '')) ?></span></span>
                <span class="ld-inspector-meta-item"><span class="ld-inspector-meta-label"><?= e($ld('rules_inventory_priority')) ?>:</span> <span class="ld-inspector-meta-value"><?= e((string)($selectedRule['priority'] ?? '—')) ?></span></span>
                <span class="ld-inspector-meta-item"><span class="ld-inspector-meta-label"><?= e($ld('rules_inventory_enabled')) ?>:</span> <span class="ld-inspector-meta-value"><?= e(!empty($selectedRule['enabled']) ? $ld('rules_inventory_enabled') : $ld('status_disabled')) ?></span></span>
              </div>
              <?php $renderFlash(); ?>
              <?php
              $inspConditions = isset($selectedRule['conditions']) && is_array($selectedRule['conditions'])
                  ? array_values(array_filter($selectedRule['conditions'], 'is_array'))
                  : [];
              $inspEffects = isset($selectedRule['effects']) && is_array($selectedRule['effects'])
                  ? array_values(array_filter($selectedRule['effects'], 'is_array'))
                  : [];
              ?>
              <?php if ($inspConditions !== []): ?>
              <div class="ld-inspector-section-title"><?= e($ld('rules_inspector_condition_table_title')) ?></div>
              <div class="ld-table-wrap"><table class="ld-inspector-table">
                <thead>
                  <tr>
                    <th><?= e($ld('rules_inspector_field')) ?></th>
                    <th><?= e($ld('rules_inspector_operator')) ?></th>
                    <th><?= e($ld('rules_inspector_value')) ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($inspConditions as $c): ?>
                  <tr>
                    <td><code><?= e((string)($c['field_key'] ?? '')) ?></code></td>
                    <td><code><?= e(str_replace('_', ' ', (string)($c['operator'] ?? ''))) ?></code></td>
                    <td><code><?= e((string)($c['value'] ?? '—')) ?></code></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table></div>
              <?php endif; ?>
              <?php if ($inspEffects !== []): ?>
              <div class="ld-inspector-section-title"><?= e($ld('rules_inspector_effect_table_title')) ?></div>
              <div class="ld-table-wrap"><table class="ld-inspector-table">
                <thead>
                  <tr>
                    <th><?= e($ld('rules_inspector_type')) ?></th>
                    <th><?= e($ld('rules_inspector_target')) ?></th>
                    <th><?= e($ld('rules_inspector_value')) ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($inspEffects as $e): ?>
                  <tr>
                    <td><code><?= e((string)($e['type'] ?? '')) ?></code></td>
                    <td><code><?= e((string)($e['target'] ?? '')) ?></code></td>
                    <td><?= e((string)($e['value'] ?? '—')) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table></div>
              <?php endif; ?>
              <?php if ($ruleSummary !== ''): ?>
              <div class="ld-inspector-section-title"><?= e($ld('rules_inspector_summary_title')) ?></div>
              <p class="ld-inspector-summary-text"><?= e($ruleSummary) ?></p>
              <?php endif; ?>
              <?php
              $ruleCtxKey = (string)($selectedRule['context_key'] ?? '');
              $ruleTplKey = (string)($selectedRule['template_key'] ?? '');
              ?>
              <p style="margin-top:6px">
                <a href="?workspace=preview&amp;owner=<?= e(urlencode($selectedDataSourceOwnerKey)) ?>&amp;context_key=<?= e(urlencode($ruleCtxKey)) ?>&amp;template_key=<?= e(urlencode($ruleTplKey)) ?>&amp;rules_enabled=1" class="ld-action-link"><?= e($ld('rules_inspector_preview_rule')) ?></a>
                &middot;
                <a href="?workspace=rules&amp;owner=<?= e(urlencode($selectedDataSourceOwnerKey)) ?>" class="ld-inspector-return ld-action-link"><?= e($ld('rules_inspector_return_to_rules')) ?></a>
              </p>
              <!-- Phase 16.7: Rule edit form -->
              <details class="ld-advanced ld-edit-form" style="margin-top:8px">
              <summary><?= e($ld('rule_edit_title')) ?></summary>
              <?php $renderDiagTable('rule_edit'); ?>
              <form method="post" action="/apps/studio/tools/label-designer/rule/edit">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="workspace" value="rules">
                <input type="hidden" name="owner_key" value="<?= e($selectedDataSourceOwnerKey) ?>">
                <input type="hidden" name="rule_key" value="<?= e((string)($selectedRule['rule_key'] ?? '')) ?>">
                <label style="display:block;margin:4px 0">
                  <span class="ld-meta-label"><?= e($ld('rule_edit_label_label')) ?>:</span>
                  <input type="text" name="label" value="<?= e((string)($selectedRule['label'] ?? '')) ?>" style="width:100%;box-sizing:border-box">
                </label>
                <label style="display:block;margin:4px 0">
                  <span class="ld-meta-label"><?= e($ld('rule_edit_description_label')) ?>:</span>
                  <input type="text" name="description" value="<?= e((string)($selectedRule['description'] ?? '')) ?>" style="width:100%;box-sizing:border-box">
                </label>
                <label style="display:block;margin:4px 0">
                  <span class="ld-meta-label"><?= e($ld('rule_edit_priority_label')) ?>:</span>
                  <input type="text" name="priority" value="<?= e((string)($selectedRule['priority'] ?? '')) ?>" style="width:100%;box-sizing:border-box">
                </label>
                <label style="display:block;margin:4px 0">
                  <span class="ld-meta-label"><?= e($ld('rule_edit_enabled_label')) ?>:</span>
                  <select name="enabled" style="width:100%;box-sizing:border-box">
                    <option value="1"<?= !empty($selectedRule['enabled']) ? ' selected' : '' ?>><?= e($ld('rules_inventory_enabled')) ?></option>
                    <option value="0"<?= empty($selectedRule['enabled']) ? ' selected' : '' ?>><?= e($ld('status_disabled')) ?></option>
                  </select>
                </label>
                <?php if ($inspConditions !== []): ?>
                <div class="ld-inspector-section-title" style="margin-top:6px"><?= e($ld('rules_inspector_condition_table_title')) ?></div>
                <div class="ld-table-wrap"><table class="ld-inspector-table">
                  <thead>
                    <tr>
                      <th><?= e($ld('rules_inspector_field')) ?></th>
                      <th><?= e($ld('rules_inspector_operator')) ?></th>
                      <th><?= e($ld('rule_edit_condition_value_label')) ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($inspConditions as $ci => $c): ?>
                    <tr>
                      <td><code><?= e((string)($c['field_key'] ?? '')) ?></code></td>
                      <td><code><?= e(str_replace('_', ' ', (string)($c['operator'] ?? ''))) ?></code></td>
                      <td>
                        <input type="text" name="condition_values[<?= e((string)$ci) ?>][value]" value="<?= e((string)($c['value'] ?? '')) ?>" style="width:100%;box-sizing:border-box">
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table></div>
                <?php endif; ?>
                <?php if ($inspEffects !== []): ?>
                <div class="ld-inspector-section-title" style="margin-top:6px"><?= e($ld('rules_inspector_effect_table_title')) ?></div>
                <div class="ld-table-wrap"><table class="ld-inspector-table">
                  <thead>
                    <tr>
                      <th><?= e($ld('rules_inspector_type')) ?></th>
                      <th><?= e($ld('rules_inspector_target')) ?></th>
                      <th><?= e($ld('rule_edit_effect_label_label')) ?></th>
                      <th><?= e($ld('rule_edit_effect_value_label')) ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($inspEffects as $ei => $e): ?>
                    <tr>
                      <td><code><?= e((string)($e['type'] ?? '')) ?></code></td>
                      <td><code><?= e((string)($e['target'] ?? '')) ?></code></td>
                      <td>
                        <input type="text" name="effect_labels[<?= e((string)$ei) ?>][label]" value="<?= e((string)($e['label'] ?? '')) ?>" style="width:100%;box-sizing:border-box">
                      </td>
                      <td>
                        <input type="text" name="effect_labels[<?= e((string)$ei) ?>][value]" value="<?= e((string)($e['value'] ?? '')) ?>" style="width:100%;box-sizing:border-box">
                      </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                  </table></div>
                  <?php endif; ?>
                  <label style="display:block;margin:4px 0">
                    <input type="checkbox" name="confirm_edit" value="yes"> <?= e($ld('rule_edit_confirm_text')) ?>
                </label>
                <button type="submit" class="gs-button" style="margin-top:4px"><?= e($ld('rule_edit_button')) ?></button>
              </form>
              </details>
              <!-- Phase 16.5: Duplicate rule form -->
              <?php
              $ruleKeyVal = (string)($selectedRule['rule_key'] ?? '');
              ?>
              <details class="ld-advanced" style="margin-top:8px">
              <summary><?= e($ld('duplicate_title')) ?></summary>
              <form method="post" action="/apps/studio/tools/label-designer/duplicate-resource">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="workspace" value="rules">
                <input type="hidden" name="owner_key" value="<?= e($selectedDataSourceOwnerKey) ?>">
                <input type="hidden" name="resource_type" value="rule">
                <input type="hidden" name="source_key" value="<?= e($ruleKeyVal) ?>">
                <label style="display:block;margin:4px 0">
                  <span class="ld-meta-label"><?= e($ld('duplicate_new_key_label')) ?>:</span>
                  <input type="text" name="new_key" value="<?= e($ruleKeyVal) ?>.copy" style="width:100%;box-sizing:border-box" required>
                </label>
                <label style="display:block;margin:4px 0">
                  <input type="checkbox" name="confirm_duplicate" value="yes"> <?= e($ld('duplicate_confirm_text')) ?>
                </label>
                <button type="submit" class="gs-button" style="margin-top:4px"><?= e($ld('duplicate_button')) ?></button>
              </form>
              </details>
            </div>
            <?php endif; ?>
          </dd>
          </div>
          <?php endif; ?>
          <?php endif; ?>

          <?php if (!empty($selectedReadinessSummary['is_label_lifecycle_owner'])): ?>
          <div class="ld-workspace" data-workspace="rules" id="ld-workspace-rules-sandbox">
          <!-- Phase 13: Rule sandbox (moved from preview) -->
          <dt><span><?= e($ld('rules_test_title')) ?></span></dt>
          <dd class="gs-studio-tools-cards">
            <div class="gs-studio-tool-card">
              <div class="gs-studio-tool-card-title-row">
                <span class="gs-studio-tool-card-title"><?= e($ld('rules_test_title')) ?></span>
              </div>
              <p class="gs-studio-tool-card-purpose"><?= e($ld('rules_test_note')) ?></p>

              <?php if ($previewResult === [] || $previewRenderAllowed === false): ?>
                <p class="gs-studio-tool-card-purpose"><?= e($ld('rules_test_no_preview')) ?></p>
              <?php else: ?>
                <form method="post" action="/apps/studio/tools/label-designer/preview/rule-sandbox">
                  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="workspace" value="rules">
                  <input type="hidden" name="owner" value="<?= e($selectedDataSourceOwnerKey) ?>">
                  <input type="hidden" name="preview_context_id" value="<?= e((string)($_POST['preview_context_id'] ?? $previewContextOptions[0]['context_id'] ?? '')) ?>">
                  <input type="hidden" name="preview_template_id" value="<?= e((string)($_POST['preview_template_id'] ?? $previewSelectedTemplateId)) ?>">

                  <div class="ld-table-wrap"><table class="ld-form-table" style="font-size:0.85em">
                    <tr>
                      <td style="padding:4px 8px 4px 0;white-space:nowrap"><?= e($ld('rule_sandbox_condition_field')) ?>:</td>
                      <td style="padding:4px 0">
                        <select name="rs_condition_field" aria-label="<?= e($ld('rule_sandbox_condition_field')) ?>" style="max-width:200px">
                          <option value="">— <?= e($ld('rule_sandbox_condition_field')) ?> —</option>
                          <?php foreach ($ruleSandboxFields as $rsField): ?>
                            <?php $rsFieldKey = (string)($rsField['field_key'] ?? ''); ?>
                            <?php if ($rsFieldKey === '') { continue; } ?>
                            <option value="<?= e($rsFieldKey) ?>"><?= e($rsFieldKey) ?></option>
                          <?php endforeach; ?>
                        </select>
                      </td>
                    </tr>
                    <tr>
                      <td style="padding:4px 8px 4px 0;white-space:nowrap"><?= e($ld('rule_sandbox_operator')) ?>:</td>
                      <td style="padding:4px 0">
                        <select name="rs_operator" aria-label="<?= e($ld('rule_sandbox_operator')) ?>">
                          <option value="equals"><?= e($ld('rule_sandbox_operator_equals')) ?></option>
                          <option value="not_equals"><?= e($ld('rule_sandbox_operator_not_equals')) ?></option>
                          <option value="empty"><?= e($ld('rule_sandbox_operator_empty')) ?></option>
                          <option value="not_empty"><?= e($ld('rule_sandbox_operator_not_empty')) ?></option>
                          <option value="greater_than"><?= e($ld('rule_sandbox_operator_greater_than')) ?></option>
                          <option value="less_than"><?= e($ld('rule_sandbox_operator_less_than')) ?></option>
                          <option value="contains"><?= e($ld('rule_sandbox_operator_contains')) ?></option>
                        </select>
                      </td>
                    </tr>
                    <tr>
                      <td style="padding:4px 8px 4px 0;white-space:nowrap"><?= e($ld('rule_sandbox_compare_value')) ?>:</td>
                      <td style="padding:4px 0">
                        <input type="text" name="rs_compare_value" value="" placeholder= "<?= e($tt('studio.preview_active_message')) ?>" style="max-width:200px">
                      </td>
                    </tr>
                    <tr>
                      <td style="padding:4px 8px 4px 0;white-space:nowrap"><?= e($ld('rule_sandbox_effect_type')) ?>:</td>
                      <td style="padding:4px 0">
                        <select name="rs_effect_type" aria-label="<?= e($ld('rule_sandbox_effect_type')) ?>" onchange="toggleRuleEffectTarget(this.value)">
                          <option value="show_badge"><?= e($ld('rule_sandbox_effect_show_badge')) ?></option>
                          <option value="hide_field"><?= e($ld('rule_sandbox_effect_hide_field')) ?></option>
                          <option value="show_warning"><?= e($ld('rule_sandbox_effect_show_warning')) ?></option>
                          <option value="set_style_token"><?= e($ld('rule_sandbox_effect_set_style_token')) ?></option>
                        </select>
                      </td>
                    </tr>
                    <tr>
                      <td style="padding:4px 8px 4px 0;white-space:nowrap"><?= e($ld('rule_sandbox_effect_target')) ?>:</td>
                      <td style="padding:4px 0">
                        <select name="rs_effect_target" aria-label="<?= e($ld('rule_sandbox_effect_target')) ?>" id="rs_effect_target" style="max-width:200px">
                          <option value="">— <?= e($ld('rule_sandbox_effect_target')) ?> —</option>
                          <optgroup label="Preview sections">
                            <option value="preview_header"><?= e($ld('rule_sandbox_target_preview_header')) ?></option>
                            <option value="preview_footer"><?= e($ld('rule_sandbox_target_preview_footer')) ?></option>
                          </optgroup>
                          <optgroup label= "<?= e($tt('studio.fields_label')) ?>" id="rs_field_targets">
                            <?php foreach ($ruleSandboxFields as $rsField): ?>
                              <?php $rsFieldKey = (string)($rsField['field_key'] ?? ''); ?>
                              <?php if ($rsFieldKey === '') { continue; } ?>
                              <option value="<?= e($rsFieldKey) ?>"><?= e($rsFieldKey) ?></option>
                            <?php endforeach; ?>
                          </optgroup>
                        </select>
                      </td>
                    </tr>
                    <tr>
                      <td style="padding:4px 8px 4px 0;white-space:nowrap"><?= e($ld('rule_sandbox_effect_value')) ?>:</td>
                      <td style="padding:4px 0">
                        <input type="text" name="rs_effect_value" value="" placeholder= "<?= e($tt('studio.e_g_qc_label')) ?>" style="max-width:300px">
                      </td>
                    </tr>
                  </table></div>

                  <p class="gs-studio-tool-card-purpose">
                    <small><?= e($ld('rule_sandbox_field_hint')) ?></small>
                  </p>
                  <button type="submit"><?= e($ld('rule_sandbox_button')) ?></button>
                </form>

                <?php if ($ruleSandboxResult !== []): ?>
                  <hr style="margin:16px 0;border:none;border-top:1px solid var(--border-subtle,#ddd)">
                  <?php require __DIR__ . '/../partials/diagnostics-panel.php'; ?>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </dd>
          </div>
          <?php endif; ?>

          <?php if ($wsProgress['is_lifecycle']): ?>
          <div class="ld-workspace" data-workspace="rules" id="ld-workspace-rules-next-actions">
          <dt class="ld-workspace-hidden-heading"><span><?= e($ld('rules_title')) ?></span></dt>
          <dd class="gs-studio-tools-cards">
            <?php $renderProgressCard(); ?>
            <div class="ld-next-actions">
              <a class="ld-next-action" href="/apps/studio/tools/label-designer<?= $buildUrl(['workspace' => 'build']) ?>"><?= $ld('next_action_continue_build') ?></a>
              <?php if (!$wsProgress['has_context']): ?>
                <a class="ld-next-action ld-next-action-secondary" href="/apps/studio/tools/label-designer<?= $buildUrl(['workspace' => 'governance']) ?>#ld-workspace-governance-readiness"><?= $ld('next_action_create_context') ?></a>
              <?php elseif (!$wsProgress['has_template']): ?>
                <a class="ld-next-action ld-next-action-secondary" href="/apps/studio/tools/label-designer<?= $buildUrl(['workspace' => 'governance']) ?>#ld-workspace-governance-readiness"><?= $ld('next_action_create_template') ?></a>
              <?php endif; ?>
              <a class="ld-next-action<?= $wsProgress['has_preview'] ? '' : ' ld-next-action-secondary' ?>" href="/apps/studio/tools/label-designer<?= $buildUrl(['workspace' => 'preview']) ?>"><?= $ld('next_action_continue_preview') ?></a>
            </div>
          </dd>
          </div>
          <?php endif; ?>

          <div class="ld-workspace" data-workspace="rules">
          <!-- Rule Creation Preview (only for lifecycle owners) -->
          <?php if (!empty($selectedReadinessSummary['is_label_lifecycle_owner'])): ?>
          <dt><span><?= e($ld('rules_create_title')) ?></span></dt>
          <dd class="gs-studio-tools-cards">
            <div class="gs-studio-tool-card">
              <div class="gs-studio-tool-card-title-row">
                <span class="gs-studio-tool-card-title"><?= e($ld('rules_create_title')) ?></span>
              </div>
              <p class="gs-studio-tool-card-purpose"><?= e($ld('rules_create_note')) ?></p>

              <?php if (!$ruleCreateValidationStarted && $ruleCreateEmptyState): ?>
                <p class="gs-studio-tool-card-purpose"><strong><?= e($ld('rule_create_empty_owner')) ?></strong></p>
              <?php elseif (!empty($ruleCreateErrors)): ?>
                <div class="gs-studio-tool-card-purpose">
                  <strong><?= e($ld('common_preview_error')) ?></strong>
                  <ul>
                    <?php foreach ($ruleCreateErrors as $error): ?>
                      <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              <?php endif; ?>

              <form method="get" action="/apps/studio/tools/label-designer">
                <input type="hidden" name="workspace" value="<?= e($activeWorkspace) ?>">
                <input type="hidden" name="owner" value="<?= e($selectedDataSourceOwnerKey) ?>">
                <input type="hidden" name="rule_preview" value="1">
                <ol>
                  <li>
                    <?= e($ld('rule_create_context')) ?>:
                    <select name="rule_context_id" aria-label="<?= e($ld('rule_create_context')) ?>">
                      <?php foreach ($ruleCreateContexts as $contextOpt): ?>
                        <?php
                        $ctxId = (string)($contextOpt['context_id'] ?? '');
                        $ctxKey = (string)($contextOpt['context_key'] ?? '');
                        $ctxOwner = (string)($contextOpt['owner_key'] ?? '');
                        ?>
                        <option value="<?= e($ctxId) ?>"<?= $ctxId !== '' && hash_equals($ruleCreateSelectedContextId, $ctxId) ? ' selected' : '' ?>>
                          <?= e($ctxOwner) ?> :: <?= e($ctxKey) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </li>
                  <li>
                    <?= e($ld('rule_create_template')) ?>:
                    <select name="rule_template_id" aria-label="<?= e($ld('rule_create_template')) ?>">
                      <?php if (empty($ruleCreateTemplates)): ?>
                        <option value=""><?= e($ld('rule_create_no_templates')) ?></option>
                      <?php else: ?>
                        <?php foreach ($ruleCreateTemplates as $templateOpt): ?>
                          <?php
                          $tplId = (string)($templateOpt['template_id'] ?? '');
                          $tplKey = (string)($templateOpt['template_key'] ?? '');
                          ?>
                          <option value="<?= e($tplId) ?>"<?= $tplId !== '' && hash_equals($ruleCreateSelectedTemplateId, $tplId) ? ' selected' : '' ?>>
                            <?= e($tplKey) ?>
                          </option>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </select>
                  </li>
                  <li>
                    <?= e($ld('rule_create_rule_key')) ?>:
                    <input type="text" name="rule_key" value="<?= e($ruleCreateSelectedRuleKey) ?>" placeholder="manufacturing.product.high_quantity_warning">
                  </li>
                  <li>
                    <?= e($ld('rule_create_condition_field')) ?>:
                    <select name="rc_condition_field" aria-label="<?= e($ld('rule_create_condition_field')) ?>">
                      <option value="">— <?= e($ld('rule_create_condition_field')) ?> —</option>
                      <?php foreach ($ruleCreateFieldKeys as $fieldKey): ?>
                        <option value="<?= e($fieldKey) ?>"<?= hash_equals($ruleCreateSelectedConditionField, $fieldKey) ? ' selected' : '' ?>><?= e($fieldKey) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </li>
                  <li>
                    <?= e($ld('rule_create_operator')) ?>:
                    <select name="rc_operator" aria-label="<?= e($ld('rule_create_operator')) ?>">
                      <?php foreach (['equals', 'not_equals', 'empty', 'not_empty', 'greater_than', 'less_than', 'contains'] as $op): ?>
                        <option value="<?= e($op) ?>"<?= hash_equals($ruleCreateSelectedOperator, $op) ? ' selected' : '' ?>><?= e($ld('rule_sandbox_operator_' . $op)) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </li>
                  <li>
                    <?= e($ld('rule_create_condition_value')) ?>:
                    <input type="text" name="rc_condition_value" value="<?= e($ruleCreateSelectedConditionValue) ?>" placeholder="1000">
                  </li>
                  <li>
                    <?= e($ld('rule_create_effect_type')) ?>:
                    <select name="rc_effect_type" aria-label="<?= e($ld('rule_create_effect_type')) ?>">
                      <?php foreach (['show_badge', 'hide_field', 'show_warning', 'set_style_token'] as $effectType): ?>
                        <option value="<?= e($effectType) ?>"<?= hash_equals($ruleCreateSelectedEffectType, $effectType) ? ' selected' : '' ?>><?= e($ld('rule_sandbox_effect_' . $effectType)) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </li>
                  <li>
                    <?= e($ld('rule_create_effect_target')) ?>:
                    <select name="rc_effect_target" aria-label="<?= e($ld('rule_create_effect_target')) ?>">
                      <option value="">— <?= e($ld('rule_create_effect_target')) ?> —</option>
                      <?php $targetFields = isset($ruleCreateTargetOptions['fields']) && is_array($ruleCreateTargetOptions['fields']) ? $ruleCreateTargetOptions['fields'] : []; ?>
                      <?php $targetBlocks = isset($ruleCreateTargetOptions['blocks']) && is_array($ruleCreateTargetOptions['blocks']) ? $ruleCreateTargetOptions['blocks'] : []; ?>
                      <?php $targetStyles = isset($ruleCreateTargetOptions['style_tokens']) && is_array($ruleCreateTargetOptions['style_tokens']) ? $ruleCreateTargetOptions['style_tokens'] : []; ?>
                      <?php if (!empty($targetFields)): ?>
                        <optgroup label="<?= e($ld('rule_create_target_fields')) ?>">
                          <?php foreach ($targetFields as $target): ?>
                            <option value="<?= e((string)$target) ?>"<?= hash_equals($ruleCreateSelectedEffectTarget, (string)$target) ? ' selected' : '' ?>><?= e((string)$target) ?></option>
                          <?php endforeach; ?>
                        </optgroup>
                      <?php endif; ?>
                      <?php if (!empty($targetBlocks)): ?>
                        <optgroup label="<?= e($ld('rule_create_target_blocks')) ?>">
                          <?php foreach ($targetBlocks as $target): ?>
                            <option value="<?= e((string)$target) ?>"<?= hash_equals($ruleCreateSelectedEffectTarget, (string)$target) ? ' selected' : '' ?>><?= e((string)$target) ?></option>
                          <?php endforeach; ?>
                        </optgroup>
                      <?php endif; ?>
                      <?php if (!empty($targetStyles)): ?>
                        <optgroup label="<?= e($ld('rule_create_target_style_tokens')) ?>">
                          <?php foreach ($targetStyles as $target): ?>
                            <option value="<?= e((string)$target) ?>"<?= hash_equals($ruleCreateSelectedEffectTarget, (string)$target) ? ' selected' : '' ?>><?= e((string)$target) ?></option>
                          <?php endforeach; ?>
                        </optgroup>
                      <?php endif; ?>
                    </select>
                  </li>
                  <li>
                    <?= e($ld('rule_create_effect_value')) ?>:
                    <input type="text" name="rc_effect_value" value="<?= e($ruleCreateSelectedEffectValue) ?>" placeholder="HIGH VOLUME">
                  </li>
                  <li>
                    <?= e($ld('rule_create_enabled')) ?>:
                    <select name="rule_enabled" aria-label="<?= e($ld('rule_create_enabled')) ?>">
                      <option value="yes"<?= hash_equals($ruleCreateSelectedEnabled, 'yes') ? ' selected' : '' ?>><?= e($ld('yes')) ?></option>
                      <option value="no"<?= hash_equals($ruleCreateSelectedEnabled, 'no') ? ' selected' : '' ?>><?= e($ld('no')) ?></option>
                    </select>
                  </li>
                </ol>
                <button type="submit"><?= e($ld('rule_create_preview_button')) ?></button>
              </form>

              <h4 style="margin:12px 0 4px"><?= e($ld('rule_create_diagnostics_title')) ?></h4>
              <?php if (!$ruleCreateValidationStarted): ?>
                <p class="gs-studio-tool-card-purpose"><small><?= e($ld('rule_create_initial_note')) ?></small></p>
              <?php elseif (!empty($ruleCreateDiagnostics)): ?>
                <div style="overflow-x:auto;margin:8px 0">
                  <table style="width:100%;border-collapse:collapse;font-size:0.85em">
                    <thead>
                      <tr>
                        <th style="text-align:left;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)"><?= e($ld('rule_create_diagnostics_severity')) ?></th>
                        <th style="text-align:left;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)"><?= e($ld('rule_create_diagnostics_check')) ?></th>
                        <th style="text-align:left;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)"><?= e($ld('rule_create_diagnostics_message')) ?></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($ruleCreateDiagnostics as $diag): ?>
                        <?php
                        $severity = (string)($diag['severity'] ?? 'PASS');
                        $severityColor = 'var(--text-default,#333)';
                        $severityBg = 'transparent';
                        if ($severity === 'ERROR') {
                            $severityColor = 'var(--status-critical,#d32f2f)';
                            $severityBg = 'var(--bg-critical-subtle,#fff0f0)';
                        } elseif ($severity === 'FAIL') {
                            $severityColor = 'var(--status-negative,#e65100)';
                            $severityBg = 'var(--bg-warning-subtle,#fff8e1)';
                        } elseif ($severity === 'WARN') {
                            $severityColor = 'var(--status-warning,#f57c00)';
                            $severityBg = 'var(--bg-warning-subtle,#fff8e1)';
                        } elseif ($severity === 'PASS') {
                            $severityColor = 'var(--status-positive,#2e7d32)';
                        }
                        ?>
                        <tr style="background:<?= $severityBg ?>">
                          <td style="padding:3px 8px;border-bottom:1px solid var(--border-subtle,#eee);color:<?= $severityColor ?>;font-weight:600">
                            <?= e($severity) ?>
                          </td>
                          <td style="padding:3px 8px;border-bottom:1px solid var(--border-subtle,#eee)">
                            <code><?= e((string)($diag['check_key'] ?? '')) ?></code>
                            <small><?= e((string)($diag['label'] ?? '')) ?></small>
                          </td>
                          <td style="padding:3px 8px;border-bottom:1px solid var(--border-subtle,#eee)"><?= e((string)($diag['message'] ?? '')) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>

              <?php if ($ruleCreateValidationStarted): ?>
                <details class="ld-advanced ld-raw-json">
                  <summary><?= e($ld('rule_advanced_summary')) ?></summary>
                  <p class="gs-studio-tool-card-purpose"><?= e($ld('rule_create_json_label')) ?>:</p>
                  <pre><code><?= e($ruleCreateJson) ?></code></pre>
                  <?php if ($ruleCreateTargetPath !== ''): ?>
                    <p class="gs-studio-tool-card-purpose"><?= e($ld('common_target_path')) ?>: <code><?= e($ruleCreateTargetPath) ?></code></p>
                  <?php endif; ?>
                </details>
              <?php endif; ?>

              <?php
              $ruleCreateBlocked = false;
              foreach ($ruleCreateDiagnostics as $diag) {
                  $sev = (string)($diag['severity'] ?? 'PASS');
                  if ($sev === 'FAIL' || $sev === 'ERROR') {
                      $ruleCreateBlocked = true;
                      break;
                  }
              }
              ?>

              <?php if ($ruleCreateValidationStarted && $ruleCreateBlocked): ?>
                <p class="gs-studio-tool-card-purpose"><strong><?= e($ld('rule_create_blocked')) ?></strong></p>
              <?php endif; ?>

              <?php if ($ruleCreateValidationStarted): ?>
                <p class="gs-studio-tool-card-purpose"><?= e($ld('rule_create_warning')) ?></p>
                <form method="post" action="/apps/studio/tools/label-designer/rule/create" onsubmit="return confirm('<?= e($ld('rule_create_confirm')) ?>?');">
                  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="workspace" value="<?= e($activeWorkspace) ?>">
                  <input type="hidden" name="owner" value="<?= e($selectedDataSourceOwnerKey) ?>">
                  <input type="hidden" name="rule_context_id" value="<?= e($ruleCreateSelectedContextId) ?>">
                  <input type="hidden" name="rule_template_id" value="<?= e($ruleCreateSelectedTemplateId) ?>">
                  <input type="hidden" name="rule_key" value="<?= e($ruleCreateSelectedRuleKey) ?>">
                  <input type="hidden" name="rc_condition_field" value="<?= e($ruleCreateSelectedConditionField) ?>">
                  <input type="hidden" name="rc_operator" value="<?= e($ruleCreateSelectedOperator) ?>">
                  <input type="hidden" name="rc_condition_value" value="<?= e($ruleCreateSelectedConditionValue) ?>">
                  <input type="hidden" name="rc_effect_type" value="<?= e($ruleCreateSelectedEffectType) ?>">
                  <input type="hidden" name="rc_effect_target" value="<?= e($ruleCreateSelectedEffectTarget) ?>">
                  <input type="hidden" name="rc_effect_value" value="<?= e($ruleCreateSelectedEffectValue) ?>">
                  <input type="hidden" name="rule_enabled" value="<?= e($ruleCreateSelectedEnabled) ?>">
                  <label>
                    <input type="checkbox" name="confirm_create" value="yes" required>
                    <?= e($ld('rule_create_confirm')) ?>
                  </label>
                  <button type="submit"<?= $ruleCreateBlocked ? ' disabled' : '' ?>><?= e($ld('rule_create_submit')) ?></button>
                </form>
              <?php endif; ?>
            </div>
            </details>
          </dd>
          </div>
          <?php endif; ?>

          <?php
          $runtimeDryRunRequest = isset($runtimeDryRun['request']) && is_array($runtimeDryRun['request'])
              ? $runtimeDryRun['request']
              : [];
          $runtimeDryRunDiagnostics = isset($runtimeDryRun['diagnostics']) && is_array($runtimeDryRun['diagnostics'])
              ? array_values(array_filter($runtimeDryRun['diagnostics'], 'is_array'))
              : [];
          $runtimeDryRunSummary = isset($runtimeDryRun['summary']) && is_array($runtimeDryRun['summary'])
              ? $runtimeDryRun['summary']
              : [];
            $runtimeDryRunMatrixRows = isset($runtimeDryRunMatrix['rows']) && is_array($runtimeDryRunMatrix['rows'])
              ? array_values(array_filter($runtimeDryRunMatrix['rows'], 'is_array'))
              : [];
            $runtimeDryRunMatrixSummary = isset($runtimeDryRunMatrix['summary']) && is_array($runtimeDryRunMatrix['summary'])
              ? $runtimeDryRunMatrix['summary']
              : [];

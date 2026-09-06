          <?php if (!empty($selectedReadinessSummary['is_label_lifecycle_owner'])): ?>
          <div class="ld-workspace" data-workspace="build">
          <!-- Phase 12: Build workspace resource focus -->
          <dt><span><?= e($ld('build_title')) ?></span></dt>
          <dd class="gs-studio-tools-cards">

            <!-- Phase 12: Resource chain visual -->
            <?php if ($selectedOwnerHasResources): ?>
            <div class="gs-studio-tool-card">
              <div class="gs-studio-tool-card-title-row">
                <span class="gs-studio-tool-card-title"><?= e($ld('build_chain_title')) ?></span>
              </div>
              <div class="ld-build-chain">
                <span class="ld-chain-item ld-chain-item-active" title="Context"><?= e($overviewContextKey ?: '—') ?></span>
                <span class="ld-chain-arrow">→</span>
                <span class="ld-chain-item<?= $overviewTemplateKey !== '' ? ' ld-chain-item-active' : '' ?>" title="Template"><?= e($overviewTemplateKey ?: '—') ?></span>
                <span class="ld-chain-arrow">→</span>
                <span class="ld-chain-item<?= $overviewRuleCount > 0 ? ' ld-chain-item-active' : '' ?>" title="Rules"><?= e($overviewRuleCount) ?> Rules</span>
              </div>
            </div>
            <?php endif; ?>

            <!-- Phase 16.1: Existing Contexts table -->
            <div class="gs-studio-tool-card">
              <div class="gs-studio-tool-card-title-row">
                <span class="gs-studio-tool-card-title"><?= e($ld('build_existing_contexts_title')) ?></span>
                <div class="ld-status-row">
                  <span class="ld-badge ld-badge-ready"><?= e(count($existingContexts)) ?></span>
                </div>
              </div>
              <?php if ($existingContexts !== []): ?>
                <div class="ld-table-wrap"><table class="ld-existing-table">
                  <thead>
                    <tr>
                      <th><?= e($ld('build_existing_contexts_key')) ?></th>
                      <th><?= e($ld('build_existing_contexts_purpose')) ?></th>
                      <th><?= e($ld('build_existing_contexts_fields')) ?></th>
                      <th><?= e($ld('build_existing_action_view')) ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($existingContexts as $ctx): ?>
                      <?php
                      $ctxKey = (string)($ctx['context_key'] ?? '');
                      $ctxPurpose = (string)($ctx['purpose'] ?? '');
                      $ctxFields = isset($ctx['allowed_fields']) && is_array($ctx['allowed_fields'])
                          ? count($ctx['allowed_fields']) : 0;
                      $ctxViewUrl = '/apps/studio/tools/label-designer?workspace=build'
                          . '&owner=' . urlencode($selectedDataSourceOwnerKey)
                          . '&view_resource=context'
                          . '&context_key=' . urlencode($ctxKey);
                      ?>
                      <tr>
                        <td><code><?= e($ctxKey) ?></code></td>
                        <td><?= e($ctxPurpose) ?></td>
                        <td><?= e((string)$ctxFields) ?></td>
                        <td class="ld-actions-cell">
                          <a href="<?= e($ctxViewUrl) ?>" class="ld-action-link" title="<?= e($ld('build_existing_action_view')) ?>"><?= e($ld('build_existing_action_view')) ?></a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                    </tbody>
                  </table></div>
                <?php else: ?>
                  <p class="gs-studio-tool-card-purpose"><?= e($ld('build_existing_empty')) ?></p>
                  <p class="gs-studio-tool-card-purpose"><?= e($ld('build_no_contexts_detail')) ?></p>
              <?php endif; ?>
              <p class="ld-build-actions"><a href="#ld-build-create-context" class="gs-button"><?= e($ld('build_create_context')) ?></a></p>
            </div>

            <!-- Phase 16.1: Existing Templates table -->
            <div class="gs-studio-tool-card">
              <div class="gs-studio-tool-card-title-row">
                <span class="gs-studio-tool-card-title"><?= e($ld('build_existing_templates_title')) ?></span>
                <div class="ld-status-row">
                  <span class="ld-badge ld-badge-ready"><?= e(count($existingTemplates)) ?></span>
                </div>
              </div>
              <?php if ($existingTemplates !== []): ?>
                <div class="ld-table-wrap"><table class="ld-existing-table">
                  <thead>
                    <tr>
                      <th><?= e($ld('build_existing_templates_key')) ?></th>
                      <th><?= e($ld('build_existing_templates_context')) ?></th>
                      <th><?= e($ld('build_existing_templates_fields')) ?></th>
                      <th><?= e($ld('build_existing_templates_blocks')) ?></th>
                      <th><?= e($ld('build_existing_templates_size')) ?></th>
                      <th><?= e($ld('build_existing_action_view')) ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($existingTemplates as $tpl): ?>
                      <?php
                      $tplKey = (string)($tpl['template_key'] ?? '');
                      $tplContext = (string)($tpl['context_ref']['context_key'] ?? '');
                      $tplFields = isset($tpl['fields']) && is_array($tpl['fields'])
                          ? count($tpl['fields']) : 0;
                      $tplBlocks = isset($tpl['layout']['blocks']) && is_array($tpl['layout']['blocks'])
                          ? count($tpl['layout']['blocks']) : 0;
                      $tplSize = (string)($tpl['layout']['label_size'] ?? '');
                      $tplViewUrl = '/apps/studio/tools/label-designer?workspace=build'
                          . '&owner=' . urlencode($selectedDataSourceOwnerKey)
                          . '&view_resource=template'
                          . '&context_key=' . urlencode($tplContext)
                          . '&template_key=' . urlencode($tplKey);
                      ?>
                      <tr>
                        <td><code><?= e($tplKey) ?></code></td>
                        <td><code><?= e($tplContext) ?></code></td>
                        <td><?= e((string)$tplFields) ?></td>
                        <td><?= e((string)$tplBlocks) ?></td>
                        <td><code><?= e($tplSize) ?></code></td>
                        <td class="ld-actions-cell">
                          <a href="<?= e($tplViewUrl) ?>" class="ld-action-link" title="<?= e($ld('build_existing_action_view')) ?>"><?= e($ld('build_existing_action_view')) ?></a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                    </tbody>
                  </table></div>
                <?php else: ?>
                  <p class="gs-studio-tool-card-purpose"><?= e($ld('build_existing_empty')) ?></p>
                  <p class="gs-studio-tool-card-purpose"><?= e($ld('build_no_templates_detail')) ?></p>
              <?php endif; ?>
              <p class="ld-build-actions"><a href="#ld-build-create-template" class="gs-button"><?= e($ld('build_create_template')) ?></a></p>
            </div>

            <!-- Phase 16.2: Resource inspector (context or template) -->
            <?php if ($viewResource !== '' && $selectedContext === null && $selectedTemplate === null): ?>
              <div class="ld-inspector-not-found">
                <div class="ld-inspector-not-found-title"><?= e($ld('build_inspector_not_found')) ?></div>
                <p><a href="?workspace=build&amp;owner=<?= e(urlencode($selectedDataSourceOwnerKey)) ?>" class="ld-inspector-return ld-action-link"><?= e($ld('build_inspector_return_to_build')) ?></a></p>
              </div>
            <?php endif; ?>

            <?php if ($selectedContext !== null): ?>
            <div class="ld-inspector-card">
              <div class="ld-inspector-card-title"><?= e($ld('build_context_inspector_title')) ?></div>
              <?php $renderFlash(); ?>
              <div class="ld-inspector-meta">
                <span class="ld-inspector-meta-item"><span class="ld-inspector-meta-label"><?= e($ld('build_context_inspector_owner')) ?>:</span> <span class="ld-inspector-meta-value"><?= e($selectedDataSourceOwnerKey) ?></span></span>
                <span class="ld-inspector-meta-item"><span class="ld-inspector-meta-label"><?= e($ld('build_context_inspector_context_key')) ?>:</span> <span class="ld-inspector-meta-value"><?= e((string)($selectedContext['context_key'] ?? '')) ?></span></span>
                <span class="ld-inspector-meta-item"><span class="ld-inspector-meta-label"><?= e($ld('build_context_inspector_purpose')) ?>:</span> <?= e((string)($selectedContext['purpose'] ?? '')) ?></span>
                <span class="ld-inspector-meta-item"><span class="ld-inspector-meta-label"><?= e($ld('build_context_inspector_boundary')) ?>:</span> <span class="ld-inspector-meta-value"><?= e((string)($selectedContext['data_source_boundary'] ?? '—')) ?></span></span>
              </div>
              <?php
              $allowedFields = isset($selectedContext['allowed_fields']) && is_array($selectedContext['allowed_fields'])
                  ? array_values(array_filter($selectedContext['allowed_fields'], 'is_array'))
                  : [];
              ?>
              <?php if ($allowedFields !== []): ?>
              <div class="ld-inspector-section-title"><?= e($ld('build_context_inspector_allowed_fields')) ?></div>
              <div class="ld-table-wrap"><table class="ld-inspector-table">
                <thead>
                  <tr>
                    <th><?= e($ld('build_context_inspector_field_key')) ?></th>
                    <th><?= e($ld('build_context_inspector_field_label')) ?></th>
                    <th><?= e($ld('build_context_inspector_field_source')) ?></th>
                    <th><?= e($ld('build_context_inspector_field_type')) ?></th>
                    <th><?= e($ld('build_context_inspector_field_required')) ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($allowedFields as $af): ?>
                  <tr>
                    <td><code><?= e((string)($af['field_key'] ?? '')) ?></code></td>
                    <td><?= e((string)($af['label'] ?? '')) ?></td>
                    <td><code><?= e((string)($af['source_column'] ?? '')) ?></code></td>
                    <td><?php $afDt = (string)($af['data_type'] ?? ''); if ($afDt === ''): ?><span class="ld-missing-badge"><?= e($ld('context_edit_data_type_missing')) ?></span><?php else: ?><?= e($afDt) ?><?php endif; ?></td>
                    <td><?= e(!empty($af['required']) ? $ld('yes') : $ld('no')) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table></div>
              <?php else: ?>
              <p><em><?= e($ld('build_no_contexts_detail')) ?></em></p>
              <?php endif; ?>
              <?php $contextKeyForPreview = (string)($selectedContext['context_key'] ?? ''); ?>
              <p style="margin-top:6px">
                <a href="?workspace=preview&amp;owner=<?= e(urlencode($selectedDataSourceOwnerKey)) ?>&amp;context_key=<?= e(urlencode($contextKeyForPreview)) ?>" class="ld-action-link"><?= e($ld('build_inspector_preview_context')) ?></a>
                &middot;
                <a href="?workspace=build&amp;owner=<?= e(urlencode($selectedDataSourceOwnerKey)) ?>" class="ld-inspector-return ld-action-link"><?= e($ld('build_inspector_return_to_build')) ?></a>
              </p>
              <!-- Phase 16.5: Duplicate context form -->
              <details class="ld-advanced" style="margin-top:8px">
              <summary><?= e($ld('duplicate_title')) ?></summary>
              <form method="post" action="/apps/studio/tools/label-designer/duplicate-resource">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="workspace" value="build">
                <input type="hidden" name="owner_key" value="<?= e($selectedDataSourceOwnerKey) ?>">
                <input type="hidden" name="resource_type" value="context">
                <input type="hidden" name="source_key" value="<?= e($contextKeyForPreview) ?>">
                <label style="display:block;margin:4px 0">
                  <span class="ld-meta-label"><?= e($ld('duplicate_new_key_label')) ?>:</span>
                  <input type="text" name="new_key" value="<?= e($contextKeyForPreview) ?>.copy" style="width:100%;box-sizing:border-box" required>
                </label>
                <label style="display:block;margin:4px 0">
                  <input type="checkbox" name="confirm_duplicate" value="yes"> <?= e($ld('duplicate_confirm_text')) ?>
                </label>
                <button type="submit" class="gs-button" style="margin-top:4px"><?= e($ld('duplicate_button')) ?></button>
              </form>
              </details>
              <!-- Phase 17: Edit context form -->
              <details class="ld-advanced ld-edit-form" style="margin-top:8px">
              <summary><?= e($ld('context_edit_title')) ?></summary>
              <?php $renderDiagTable('context_edit'); ?>
              <form method="post" action="/apps/studio/tools/label-designer/context/edit">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="workspace" value="build">
                <input type="hidden" name="owner_key" value="<?= e($selectedDataSourceOwnerKey) ?>">
                <input type="hidden" name="context_key" value="<?= e($contextKeyForPreview) ?>">
                <label style="display:block;margin:4px 0">
                  <span class="ld-meta-label"><?= e($ld('context_edit_purpose_label')) ?>:</span>
                  <input type="text" name="purpose" value="<?= e((string)($selectedContext['purpose'] ?? '')) ?>" style="width:100%;box-sizing:border-box">
                </label>
                <?php if ($allowedFields !== []): ?>
                <div class="ld-table-wrap"><table class="ld-inspector-table" style="margin-top:6px">
                  <thead>
                    <tr>
                      <th><?= e($ld('build_context_inspector_field_key')) ?></th>
                      <th><?= e($ld('context_edit_field_label')) ?></th>
                      <th><?= e($ld('context_edit_data_type_label')) ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($allowedFields as $fi => $af): ?>
                    <tr>
                      <td><code><?= e((string)($af['field_key'] ?? '')) ?></code></td>
                      <td>
                        <input type="hidden" name="fields[<?= e((string)$fi) ?>][field_key]" value="<?= e((string)($af['field_key'] ?? '')) ?>">
                        <input type="text" name="fields[<?= e((string)$fi) ?>][label]" value="<?= e((string)($af['label'] ?? '')) ?>" style="width:100%;box-sizing:border-box">
                      </td>
                      <td>
                        <input type="text" name="fields[<?= e((string)$fi) ?>][data_type]" value="<?= e((string)($af['data_type'] ?? '')) ?>" style="width:100%;box-sizing:border-box" placeholder="e.g. varchar(255)">
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table></div>
                <?php endif; ?>
                <label style="display:block;margin:4px 0">
                  <input type="checkbox" name="confirm_edit" value="yes"> <?= e($ld('context_edit_confirm_text')) ?>
                </label>
                <button type="submit" class="gs-button" style="margin-top:4px"><?= e($ld('context_edit_button')) ?></button>
              </form>
              </details>
            </div>
            <?php endif; ?>

            <?php if ($selectedTemplate !== null): ?>
            <div class="ld-inspector-card">
              <div class="ld-inspector-card-title"><?= e($ld('build_template_inspector_title')) ?></div>
              <?php $renderFlash(); ?>
              <div class="ld-inspector-meta">
                <span class="ld-inspector-meta-item"><span class="ld-inspector-meta-label"><?= e($ld('build_template_inspector_owner')) ?>:</span> <span class="ld-inspector-meta-value"><?= e($selectedDataSourceOwnerKey) ?></span></span>
                <span class="ld-inspector-meta-item"><span class="ld-inspector-meta-label"><?= e($ld('build_template_inspector_template_key')) ?>:</span> <span class="ld-inspector-meta-value"><?= e((string)($selectedTemplate['template_key'] ?? '')) ?></span></span>
                <span class="ld-inspector-meta-item"><span class="ld-inspector-meta-label"><?= e($ld('build_template_inspector_context_ref')) ?>:</span> <span class="ld-inspector-meta-value"><?= e((string)($selectedTemplate['context_ref']['context_key'] ?? ($selectedTemplate['context_key'] ?? ''))) ?></span></span>
                <span class="ld-inspector-meta-item"><span class="ld-inspector-meta-label"><?= e($ld('build_template_inspector_label_size')) ?>:</span> <span class="ld-inspector-meta-value"><?= e((string)($selectedTemplate['layout']['label_size'] ?? '')) ?></span></span>
              </div>
              <?php
              $fields = isset($selectedTemplate['fields']) && is_array($selectedTemplate['fields'])
                  ? array_values(array_filter($selectedTemplate['fields'], 'is_array'))
                  : [];
              $layoutBlocks = isset($selectedTemplate['layout']['blocks']) && is_array($selectedTemplate['layout']['blocks'])
                  ? array_values(array_filter($selectedTemplate['layout']['blocks'], 'is_array'))
                  : [];
              ?>
              <?php if ($fields !== []): ?>
              <div class="ld-inspector-section-title"><?= e($ld('build_template_inspector_selected_fields')) ?></div>
              <div class="ld-table-wrap"><table class="ld-inspector-table">
                <thead>
                  <tr>
                    <th><?= e($ld('build_template_inspector_field_key')) ?></th>
                    <th><?= e($ld('build_template_inspector_field_label')) ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($fields as $f): ?>
                  <tr>
                    <td><code><?= e((string)($f['field_key'] ?? '')) ?></code></td>
                    <td><?= e((string)($f['label'] ?? '')) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table></div>
              <?php else: ?>
              <p><em><?= e($ld('build_no_templates_detail')) ?></em></p>
              <?php endif; ?>
              <?php if ($layoutBlocks !== []): ?>
              <div class="ld-inspector-section-title"><?= e($ld('build_template_inspector_layout_blocks')) ?></div>
              <div class="ld-table-wrap"><table class="ld-inspector-table">
                <thead>
                  <tr>
                    <th><?= e($ld('build_template_inspector_block_key')) ?></th>
                    <th><?= e($ld('build_template_inspector_block_type')) ?></th>
                    <th><?= e($ld('build_template_inspector_block_summary')) ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($layoutBlocks as $lb): ?>
                  <tr>
                    <td><code><?= e((string)($lb['key'] ?? $lb['block_key'] ?? '')) ?></code></td>
                    <td><?= e((string)($lb['type'] ?? '')) ?></td>
                    <?php
                    $blockSummary = '';
                    if (isset($lb['title']) && is_string($lb['title']) && $lb['title'] !== '') {
                      $blockSummary = $lb['title'];
                    } elseif (isset($lb['content']) && is_string($lb['content']) && $lb['content'] !== '') {
                      $blockSummary = mb_substr($lb['content'], 0, 60);
                    } else {
                      $blockSummary = (string)($lb['role'] ?? '');
                    }
                    ?>
                    <td><?= e($blockSummary) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table></div>
              <?php endif; ?>
              <?php
              $tplContextKey = (string)($selectedTemplate['context_ref']['context_key'] ?? $selectedTemplate['context_key'] ?? '');
              $tplKey = (string)($selectedTemplate['template_key'] ?? '');
              ?>
              <p style="margin-top:6px">
                <a href="?workspace=preview&amp;owner=<?= e(urlencode($selectedDataSourceOwnerKey)) ?>&amp;context_key=<?= e(urlencode($tplContextKey)) ?>&amp;template_key=<?= e(urlencode($tplKey)) ?>" class="ld-action-link"><?= e($ld('build_inspector_preview_template')) ?></a>
                &middot;
                <a href="?workspace=build&amp;owner=<?= e(urlencode($selectedDataSourceOwnerKey)) ?>" class="ld-inspector-return ld-action-link"><?= e($ld('build_inspector_return_to_build')) ?></a>
              </p>
              <!-- Phase 16.6: Template edit form -->
              <details class="ld-advanced ld-edit-form" style="margin-top:8px">
              <summary><?= e($ld('template_edit_title')) ?></summary>
              <?php $renderDiagTable('template_edit'); ?>
              <form method="post" action="/apps/studio/tools/label-designer/template/edit">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="workspace" value="build">
                <input type="hidden" name="owner_key" value="<?= e($selectedDataSourceOwnerKey) ?>">
                <input type="hidden" name="template_key" value="<?= e($tplKey) ?>">
                <label style="display:block;margin:4px 0">
                  <span class="ld-meta-label"><?= e($ld('template_edit_label_label')) ?>:</span>
                  <input type="text" name="label" value="<?= e((string)($selectedTemplate['label'] ?? '')) ?>" style="width:100%;box-sizing:border-box">
                </label>
                <label style="display:block;margin:4px 0">
                  <span class="ld-meta-label"><?= e($ld('template_edit_purpose_label')) ?>:</span>
                  <input type="text" name="purpose" value="<?= e((string)($selectedTemplate['purpose'] ?? '')) ?>" style="width:100%;box-sizing:border-box">
                </label>
                <?php if (isset($selectedTemplate['layout']['label_size']) && is_array($selectedTemplate['layout']['label_size'])): ?>
                <div class="ld-inspector-section-title" style="margin-top:6px"><?= e($ld('build_template_inspector_label_size')) ?></div>
                <label style="display:block;margin:4px 0">
                  <span class="ld-meta-label"><?= e($ld('template_edit_size_label_label')) ?>:</span>
                  <input type="text" name="size_label" value="<?= e((string)($selectedTemplate['layout']['label_size']['label'] ?? '')) ?>" style="width:100%;box-sizing:border-box">
                </label>
                <label style="display:inline-block;margin:4px 0;width:48%">
                  <span class="ld-meta-label"><?= e($ld('template_edit_size_width_label')) ?>:</span>
                  <input type="text" name="size_width" value="<?= e((string)($selectedTemplate['layout']['label_size']['width'] ?? '')) ?>" style="width:100%;box-sizing:border-box">
                </label>
                <label style="display:inline-block;margin:4px 0;width:48%;margin-left:4%">
                  <span class="ld-meta-label"><?= e($ld('template_edit_size_height_label')) ?>:</span>
                  <input type="text" name="size_height" value="<?= e((string)($selectedTemplate['layout']['label_size']['height'] ?? '')) ?>" style="width:100%;box-sizing:border-box">
                </label>
                <?php endif; ?>
                <?php if ($fields !== []): ?>
                <div class="ld-inspector-section-title" style="margin-top:6px"><?= e($ld('build_template_inspector_selected_fields')) ?></div>
                <div class="ld-table-wrap"><table class="ld-inspector-table">
                  <thead>
                    <tr>
                      <th><?= e($ld('build_template_inspector_field_key')) ?></th>
                      <th><?= e($ld('template_edit_field_label_label')) ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($fields as $f): ?>
                    <?php $fk = (string)($f['field_key'] ?? ''); ?>
                    <tr>
                      <td><code><?= e($fk) ?></code></td>
                      <td>
                        <input type="text" name="field_labels[<?= e($fk) ?>]" value="<?= e((string)($f['label'] ?? '')) ?>" style="width:100%;box-sizing:border-box">
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table></div>
                <?php endif; ?>
                <?php if ($layoutBlocks !== []): ?>
                <div class="ld-inspector-section-title" style="margin-top:6px"><?= e($ld('build_template_inspector_layout_blocks')) ?></div>
                <div class="ld-table-wrap"><table class="ld-inspector-table">
                  <thead>
                    <tr>
                      <th><?= e($ld('build_template_inspector_block_key')) ?></th>
                      <th><?= e($ld('build_template_inspector_block_type')) ?></th>
                      <th><?= e($ld('template_edit_block_summary_label')) ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($layoutBlocks as $lb): ?>
                    <?php
                      $lbKey = (string)($lb['key'] ?? $lb['block_key'] ?? '');
                      $lbSummaryField = isset($lb['title']) ? 'title' : (isset($lb['content']) ? 'content' : (isset($lb['text']) ? 'text' : ''));
                      $lbSummary = $lbSummaryField !== '' ? (string)($lb[$lbSummaryField] ?? '') : '';
                    ?>
                    <tr>
                      <td><code><?= e($lbKey) ?></code></td>
                      <td><?= e((string)($lb['type'] ?? '')) ?></td>
                      <td>
                        <?php if ($lbSummaryField !== ''): ?>
                        <input type="text" name="block_summaries[<?= e($lbKey) ?>]" value="<?= e($lbSummary) ?>" style="width:100%;box-sizing:border-box">
                        <?php else: ?>
                        <span class="ld-missing-badge">—</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                  </table></div>
                  <?php endif; ?>
                  <label style="display:block;margin:4px 0">
                    <input type="checkbox" name="confirm_edit" value="yes"> <?= e($ld('template_edit_confirm_text')) ?>
                </label>
                <button type="submit" class="gs-button" style="margin-top:4px"><?= e($ld('template_edit_button')) ?></button>
              </form>
              </details>
              <!-- Phase 16.5: Duplicate template form -->
              <details class="ld-advanced" style="margin-top:8px">
              <summary><?= e($ld('duplicate_title')) ?></summary>
              <form method="post" action="/apps/studio/tools/label-designer/duplicate-resource">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="workspace" value="build">
                <input type="hidden" name="owner_key" value="<?= e($selectedDataSourceOwnerKey) ?>">
                <input type="hidden" name="resource_type" value="template">
                <input type="hidden" name="source_key" value="<?= e($tplKey) ?>">
                <label style="display:block;margin:4px 0">
                  <span class="ld-meta-label"><?= e($ld('duplicate_new_key_label')) ?>:</span>
                  <input type="text" name="new_key" value="<?= e($tplKey) ?>.copy" style="width:100%;box-sizing:border-box" required>
                </label>
                <label style="display:block;margin:4px 0">
                  <input type="checkbox" name="confirm_duplicate" value="yes"> <?= e($ld('duplicate_confirm_text')) ?>
                </label>
                <button type="submit" class="gs-button" style="margin-top:4px"><?= e($ld('duplicate_button')) ?></button>
              </form>
              </details>
            </div>
            <?php endif; ?>

            <!-- Phase 12: Create context form (collapsible) -->
            <details class="ld-advanced" id="ld-build-create-context">
            <summary><?= e($ld('build_create_context')) ?></summary>
              <?php if (!empty($labelDesignerFlash)): ?>
                <div class="gs-studio-tool-card-purpose">
                  <strong><?= e((string)($labelDesignerFlash['type'] ?? 'info')) ?>:</strong>
                  <?php $flashDetails = isset($labelDesignerFlash['details']) && is_array($labelDesignerFlash['details']) ? $labelDesignerFlash['details'] : []; ?>
                  <?php if (!empty($flashDetails)): ?>
                    <ul>
                      <?php foreach ($flashDetails as $detail): ?>
                        <li><?= e((string)$detail) ?></li>
                      <?php endforeach; ?>
                    </ul>
                  <?php endif; ?>
                  <p><a href="/apps/studio/tools/label-designer"><?= e($ld('back_to_label_designer')) ?></a></p>
                </div>
              <?php endif; ?>

              <?php if (!empty($contextPreviewErrors)): ?>
                <div class="gs-studio-tool-card-purpose">
                  <strong><?= e($ld('common_preview_error')) ?></strong>
                  <ul>
                    <?php foreach ($contextPreviewErrors as $error): ?>
                      <li><?= e((string)$error) ?></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              <?php endif; ?>

              <form method="get" action="/apps/studio/tools/label-designer">
                <input type="hidden" name="workspace" value="build">
                <ol>
                  <li>
                    <?= e($ld('context_preview_step_owner')) ?>:
                    <select name="owner" aria-label="<?= e($ld('context_preview_step_owner')) ?>">
                      <?php foreach ($dataSourceOwners as $owner): ?>
                        <?php $optOwner = (string)($owner['owner_key'] ?? ''); ?>
                        <option value="<?= e($optOwner) ?>"<?= $optOwner !== '' && hash_equals($selectedCreateOwner, $optOwner) ? ' selected' : '' ?>>
                          <?= e((string)($owner['display_name'] ?? $optOwner)) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </li>
                  <li>
                    <?= e($ld('context_preview_step_purpose')) ?>:
                    <select name="purpose" aria-label="<?= e($ld('context_preview_step_purpose')) ?>">
                      <?php foreach ($contextPreviewPurposes as $purpose): ?>
                        <option value="<?= e($purpose) ?>"<?= hash_equals($selectedCreatePurpose, $purpose) ? ' selected' : '' ?>><?= e($purpose) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </li>
                  <li>
                    <?= e($ld('context_preview_step_source')) ?>:
                    <select name="source" aria-label="<?= e($ld('context_preview_step_source')) ?>">
                      <?php foreach ($candidateSources as $source): ?>
                        <?php $srcName = (string)($source['source_name'] ?? ''); ?>
                        <option value="<?= e($srcName) ?>"<?= $srcName !== '' && hash_equals($selectedCreateSource, $srcName) ? ' selected' : '' ?>><?= e($srcName) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </li>
                  <li>
                    <?= e($ld('context_preview_step_fields')) ?>:
                    <?php if (empty($selectedCandidateColumns)): ?>
                      <code><?= e($ld('common_fields_selected_later')) ?></code>
                    <?php else: ?>
                      <ul>
                        <?php foreach ($selectedCandidateColumns as $column): ?>
                          <?php $fieldKey = (string)($column['column_name'] ?? ''); ?>
                          <?php if ($fieldKey === '') { continue; } ?>
                          <li>
                            <label>
                              <input type="checkbox" name="fields[]" value="<?= e($fieldKey) ?>"<?= in_array($fieldKey, $selectedCreateFields, true) ? ' checked' : '' ?>>
                              <code><?= e($fieldKey) ?></code>
                            </label>
                          </li>
                        <?php endforeach; ?>
                      </ul>
                    <?php endif; ?>
                  </li>
                  <li>
                    <?= e($ld('context_preview_context_key')) ?>:
                    <input type="text" name="context_key" value="<?= e($selectedCreateContextKey) ?>" placeholder="manufacturing.pallet">
                  </li>
                  <li><?= e($ld('context_preview_step_json')) ?></li>
                </ol>
                <button type="submit"><?= e($ld('update_json_preview')) ?></button>
              </form>

              <details class="ld-advanced ld-raw-json">
                <summary><?= e($ld('context_advanced_summary')) ?></summary>
                <p class="gs-studio-tool-card-purpose"><?= e($ld('context_preview_json_label')) ?>:</p>
                <pre><code><?= e($contextPreviewJsonText) ?></code></pre>
                <?php if ($contextPreviewTargetPath !== ''): ?>
                  <p class="gs-studio-tool-card-purpose"><?= e($ld('context_preview_target_path')) ?>: <code><?= e($contextPreviewTargetPath) ?></code></p>
                <?php endif; ?>
              </details>

              <p class="gs-studio-tool-card-purpose">This will create an owner-owned label context file after snapshot, validation, and confirmation.</p>

              <form method="post" action="/apps/studio/tools/label-designer/context/create" onsubmit="return confirm('Create owner-owned label context now?');">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="workspace" value="<?= $activeWorkspace ?>">
                <input type="hidden" name="owner" value="<?= e($selectedCreateOwner) ?>">
                <input type="hidden" name="purpose" value="<?= e($selectedCreatePurpose) ?>">
                <input type="hidden" name="source" value="<?= e($selectedCreateSource) ?>">
                <input type="hidden" name="context_key" value="<?= e($selectedCreateContextKey) ?>">
                <?php foreach ($selectedCreateFields as $field): ?>
                  <input type="hidden" name="fields[]" value="<?= e((string)$field) ?>">
                <?php endforeach; ?>
                <label>
                  <input type="checkbox" name="confirm_create" value="yes" required>
                  <?= e($ld('context_create_confirm')) ?>
                </label>
                <button type="submit"><?= e($ld('context_create_submit')) ?></button>
              </form>
            </details>

            <!-- Phase 12: Create template form (collapsible) -->
            <details class="ld-advanced" id="ld-build-create-template">
            <summary><?= e($ld('build_create_template')) ?></summary>
              <p class="gs-studio-tool-card-purpose"><?= e($ld('template_preview_note')) ?></p>

              <?php if (!empty($templatePreviewErrors)): ?>
                <div class="gs-studio-tool-card-purpose">
                  <strong><?= e($ld('common_preview_error')) ?></strong>
                  <ul>
                    <?php foreach ($templatePreviewErrors as $error): ?>
                      <li><?= e((string)$error) ?></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              <?php endif; ?>

              <?php if ($templatePreviewTemplateKey !== ''): ?>
                <p class="gs-studio-tool-card-purpose">
                  <strong><?= e($ld('template_preview_key_display')) ?>:</strong>
                  <code><?= e($templatePreviewTemplateKey) ?></code>
                  <?php if ($templatePreviewTargetPath !== ''): ?>
                    <br>
                    <strong><?= e($ld('template_preview_target_path')) ?>:</strong>
                    <code><?= e($templatePreviewTargetPath) ?></code>
                  <?php endif; ?>
                </p>
              <?php endif; ?>

              <form method="get" action="/apps/studio/tools/label-designer">
                <input type="hidden" name="workspace" value="build">
                <input type="hidden" name="owner" value="<?= e($selectedDataSourceOwnerKey) ?>">
                <ol>
                  <li>
                    <?= e($ld('template_preview_step_context')) ?>:
                    <select name="template_context" aria-label="<?= e($ld('template_preview_step_context')) ?>">
                      <?php foreach ($templatePreviewContexts as $contextOption): ?>
                        <?php
                        $optionContextId = (string)($contextOption['context_id'] ?? '');
                        $optionContextKey = (string)($contextOption['context_key'] ?? '');
                        $optionOwnerKey = (string)($contextOption['owner_key'] ?? '');
                        $optionContextFile = (string)($contextOption['context_file'] ?? '');
                        ?>
                        <option value="<?= e($optionContextId) ?>"<?= $optionContextId !== '' && hash_equals($templatePreviewContextId, $optionContextId) ? ' selected' : '' ?>>
                          <?= e($optionOwnerKey) ?> :: <?= e($optionContextKey) ?> (<?= e($optionContextFile) ?>)
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </li>
                  <li>
                    <?= e($ld('template_preview_step_size')) ?>:
                    <select name="template_size" aria-label="<?= e($ld('template_preview_step_size')) ?>">
                      <?php foreach ($templatePreviewLabelSizes as $labelSize): ?>
                        <option value="<?= e($labelSize) ?>"<?= hash_equals($templatePreviewSize, $labelSize) ? ' selected' : '' ?>><?= e($labelSize) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </li>
                  <li>
                    <?= e($ld('template_preview_step_fields')) ?>:
                    <?php if (empty($templatePreviewContextFields)): ?>
                      <code><?= e($ld('common_fields_selected_later')) ?></code>
                    <?php else: ?>
                      <ul>
                        <?php foreach ($templatePreviewContextFields as $field): ?>
                          <?php $fieldKey = (string)($field['field_key'] ?? ''); ?>
                          <?php if ($fieldKey === '') { continue; } ?>
                          <li>
                            <label>
                              <input type="checkbox" name="template_fields[]" value="<?= e($fieldKey) ?>"<?= in_array($fieldKey, $templatePreviewFieldKeys, true) ? ' checked' : '' ?>>
                              <code><?= e($fieldKey) ?></code>
                            </label>
                          </li>
                        <?php endforeach; ?>
                      </ul>
                    <?php endif; ?>
                  </li>
                  <li><?= e($ld('template_preview_step_json')) ?></li>
                </ol>
                <button type="submit"><?= e($ld('template_preview_update')) ?></button>
              </form>

              <details class="ld-advanced ld-raw-json">
                <summary><?= e($ld('template_advanced_summary')) ?></summary>
                <p class="gs-studio-tool-card-purpose"><?= e($ld('template_preview_json_label')) ?>:</p>
                <pre><code><?= e($templatePreviewJsonText) ?></code></pre>
                <?php if ($templatePreviewTargetPath !== ''): ?>
                  <p class="gs-studio-tool-card-purpose"><?= e($ld('common_target_path')) ?>: <code><?= e($templatePreviewTargetPath) ?></code></p>
                <?php endif; ?>
              </details>

              <?php if (!empty($templatePreviewValidation)): ?>
                <div class="gs-studio-tool-card-purpose" style="margin-top:0.5rem">
                  <strong><?= e($ld('template_preview_validation_checks')) ?>:</strong>
                  <ul style="margin-top:0.25rem">
                    <?php foreach ($templatePreviewValidation as $checkKey => $checkPassed): ?>
                      <?php if (in_array($checkKey, ['read_only_preview'], true)) { continue; } ?>
                      <li style="color:<?= e($checkPassed ? 'var(--color-status-approval)' : 'var(--color-status-critical)') ?>">
                        <code><?= e($checkKey) ?></code>: <?= e($checkPassed ? 'PASS' : 'FAIL') ?>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              <?php endif; ?>

              <form method="post" action="/apps/studio/tools/label-designer/template/create" onsubmit="return confirm('<?= e($ld('template_create_confirm')) ?>?');">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="workspace" value="<?= $activeWorkspace ?>">
                <input type="hidden" name="owner" value="<?= e($selectedDataSourceOwnerKey) ?>">
                <input type="hidden" name="template_context" value="<?= e($templatePreviewContextId) ?>">
                <input type="hidden" name="template_size" value="<?= e($templatePreviewSize) ?>">
                <?php foreach ($templatePreviewFieldKeys as $fieldKey): ?>
                  <input type="hidden" name="template_fields[]" value="<?= e($fieldKey) ?>">
                <?php endforeach; ?>
                <label>
                  <input type="checkbox" name="confirm_create" value="yes" required>
                  <?= e($ld('template_create_confirm')) ?>
                </label>
                <button type="submit"><?= e($ld('template_create_submit')) ?></button>
              </form>
            </details>

          </dd>
          </div>
          <?php endif; ?>

          <?php if ($wsProgress['is_lifecycle']): ?>
          <div class="ld-workspace" data-workspace="build" id="ld-workspace-build-progress">
          <dt class="ld-workspace-hidden-heading"><span><?= e($ld('build_title')) ?></span></dt>
          <dd class="gs-studio-tools-cards">
            <?php $renderProgressCard(); ?>
            <div class="ld-next-actions">
              <?php if (!$wsProgress['has_context']): ?>
                <a class="ld-next-action" href="/apps/studio/tools/label-designer<?= $buildUrl(['workspace' => 'overview']) ?>"><?= $ld('next_action_create_context') ?></a>
              <?php elseif (!$wsProgress['has_template']): ?>
                <a class="ld-next-action" href="#ld-build-create-template"><?= $ld('next_action_create_template') ?></a>
              <?php elseif (!$wsProgress['has_rules']): ?>
                <a class="ld-next-action" href="/apps/studio/tools/label-designer<?= $buildUrl(['workspace' => 'rules']) ?>"><?= $ld('next_action_continue_rules') ?></a>
              <?php else: ?>
                <a class="ld-next-action" href="/apps/studio/tools/label-designer<?= $buildUrl(['workspace' => 'preview']) ?>"><?= $ld('next_action_continue_preview') ?></a>
              <?php endif; ?>
            </div>
          </dd>
          </div>
          <?php endif; ?>

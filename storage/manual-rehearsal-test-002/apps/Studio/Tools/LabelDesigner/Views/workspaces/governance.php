          <div class="ld-workspace" data-workspace="governance" id="ld-workspace-governance-summary">
          <dt><span><?= e($ld('governance_group_summary')) ?></span></dt>
          <dd class="gs-studio-tools-cards">
          <details class="ld-governance-group">
          <summary><strong><?= e($ld('governance_group_summary')) ?></strong></summary>
          <!-- Phase 8.2: Governance workspace clarity - Ownership summary -->
            <div class="gs-studio-tool-card">
              <div class="gs-studio-tool-card-title-row">
                <span class="gs-studio-tool-card-title"><?= e($ld('governance_ownership_title')) ?></span>
              </div>
              <div class="ld-status-row">
                <?php if ($selectedOwnerKey !== '' && !empty($selectedReadinessSummary['is_label_lifecycle_owner'])): ?>
                  <span class="ld-badge ld-badge-ready"><?= e($ld('governance_ownership_valid')) ?></span>
                <?php else: ?>
                  <span class="ld-badge" style="color:var(--status-warning,#f57c00);border-color:var(--status-warning,#f57c00)"><?= e($ld('governance_ownership_invalid')) ?></span>
                <?php endif; ?>
              </div>
              <div class="ld-owner-summary" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:8px;margin:8px 0">
                <p style="margin:0"><small><?= e($ld('governance_metadata_title')) ?></small><br><strong><?= e($overviewMetadataFirst ? $ld('governance_metadata_first') : $ld('governance_metadata_legacy')) ?></strong></p>
                <p style="margin:0"><small><?= e($ld('governance_readiness_title')) ?></small><br><strong><?= e(!empty($selectedReadinessSummary['all_exist']) ? $ld('governance_readiness_complete') : (!empty($selectedReadinessSummary['none_exist']) ? $ld('governance_readiness_absent') : $ld('governance_readiness_partial'))) ?></strong></p>
                <p style="margin:0"><small><?= e($ld('governance_migration_title')) ?></small><br><strong><?= e($overviewMetadataFirst ? $ld('governance_migration_complete') : $ld('governance_migration_needed')) ?></strong></p>
              </div>

              <?php
              $govAction = '';
              $govActionUrl = '';
              $govActionDesc = '';
              if (!empty($selectedReadinessSummary['is_label_lifecycle_owner'])) {
                if (!$overviewMetadataFirst) {
                  $govAction = $ld('governance_action_migrate');
                  $govActionUrl = '#ld-workspace-governance-metadata';
                  $govActionDesc = $ld('governance_action_migrate_desc');
                } elseif (empty($selectedReadinessSummary['all_exist'])) {
                  $govAction = $ld('governance_action_readiness');
                  $govActionUrl = '#ld-workspace-governance-readiness';
                  $govActionDesc = $ld('governance_action_readiness_desc');
                } elseif (empty($overviewDryRunValid)) {
                  $govAction = $ld('governance_action_dry_run');
                  $govActionUrl = '#ld-workspace-governance-dry-run';
                  $govActionDesc = $ld('governance_action_dry_run_desc');
                }
              }
              ?>
              <?php if ($govAction !== ''): ?>
              <div class="ld-gov-next">
                <a href="<?= e($govActionUrl) ?>"><?= e($govAction) ?></a>
                <p style="margin:4px 0 0;font-size:0.85em;color:var(--text-muted,#666)"><?= e($govActionDesc) ?></p>
              </div>
              <?php endif; ?>
            </div>

              <?php $renderProgressCard(); ?>
              <div class="ld-next-actions">
                <?php if (!$wsProgress['has_context']): ?>
                  <a class="ld-next-action" href="/apps/studio/tools/label-designer<?= $buildUrl(['workspace' => 'build']) ?>"><?= $ld('next_action_create_context') ?></a>
                <?php elseif (!$wsProgress['has_template']): ?>
                  <a class="ld-next-action" href="/apps/studio/tools/label-designer<?= $buildUrl(['workspace' => 'build']) ?>"><?= $ld('next_action_create_template') ?></a>
                <?php elseif (!$wsProgress['has_rules']): ?>
                  <a class="ld-next-action" href="/apps/studio/tools/label-designer<?= $buildUrl(['workspace' => 'rules']) ?>"><?= $ld('next_action_continue_rules') ?></a>
                <?php elseif (!$wsProgress['has_preview']): ?>
                  <a class="ld-next-action" href="/apps/studio/tools/label-designer<?= $buildUrl(['workspace' => 'preview']) ?>"><?= $ld('next_action_render_preview') ?></a>
                <?php else: ?>
                  <a class="ld-next-action ld-next-action-secondary" href="/apps/studio/tools/label-designer<?= $buildUrl(['workspace' => 'overview']) ?>"><?= $ld('overview_action_continue_build') ?></a>
                <?php endif; ?>
              </div>

          </details>
          </dd>
          </div>

          <div class="ld-workspace" data-workspace="governance" id="ld-workspace-governance-arch">
          <!-- Phase 19: Reference Documentation group -->
          <dt class="ld-gov-hidden"><span><?= e($ld('governance_group_reference_docs')) ?></span></dt>
          <dd class="gs-studio-tools-cards">
          <details class="ld-governance-group">
          <summary><strong><?= e($ld('governance_group_reference_docs')) ?></strong></summary>
            <details class="ld-advanced ld-governance-details">
            <summary><?= e($ld('arch_reference_summary')) ?></summary>
            <div class="gs-studio-tool-card" aria-disabled="true">
              <div class="gs-studio-tool-card-title-row">
                <span class="gs-studio-tool-card-title"><?= e($ld('arch_ownership')) ?></span>
              </div>
              <p class="gs-studio-tool-card-purpose"><?= e($ld('arch_context')) ?></p>
            </div>

            <?php if (!empty($ownerResourcePaths)): ?>
            <div class="gs-studio-tool-card" aria-disabled="true">
              <div class="gs-studio-tool-card-title-row">
                <span class="gs-studio-tool-card-title"><?= e($ld('arch_owner_paths')) ?></span>
              </div>
              <ul>
                <?php foreach ($ownerResourcePaths as $path): ?>
                  <li><code><?= e($path) ?></code></li>
                <?php endforeach; ?>
              </ul>
            </div>
            <?php endif; ?>

            <div class="gs-studio-tool-card" aria-disabled="true">
              <div class="gs-studio-tool-card-title-row">
                <span class="gs-studio-tool-card-title"><?= e($ld('arch_platform')) ?></span>
              </div>
              <p class="gs-studio-tool-card-purpose"><?= e($ld('arch_core')) ?></p>
            </div>
            </details>
            </details>
          </dd>
          </div>

          <div class="ld-workspace" data-workspace="governance" id="ld-workspace-governance-future">
          <!-- Phase 19: Reference Documentation group -->
          <dt class="ld-gov-hidden"><span><?= e($ld('governance_group_reference_docs')) ?></span></dt>
          <dd class="gs-studio-tools-cards">
          <details class="ld-governance-group">
          <summary><strong><?= e($ld('governance_group_reference_docs')) ?></strong></summary>
            <details class="ld-advanced ld-governance-details">
            <summary><?= e($ld('future_label_reference_summary')) ?></summary>
            <div class="gs-studio-tool-card" aria-disabled="true">
              <div class="gs-studio-tool-card-title-row">
                <span class="gs-studio-tool-card-title"><?= e($ld('future_note')) ?></span>
              </div>
              <?php if (!empty($futureExamples)): ?>
              <ul>
                <?php foreach ($futureExamples as $example): ?>
                  <li><?= e($example) ?></li>
                <?php endforeach; ?>
              </ul>
              <?php endif; ?>
            </div>
            </details>
            </details>
          </dd>
          </div>

          <div class="ld-workspace" data-workspace="governance" id="ld-workspace-governance-contracts">
          <!-- Phase 19: Reference Documentation group -->
          <dt class="ld-gov-hidden"><span><?= e($ld('governance_group_reference_docs')) ?></span></dt>
          <dd class="gs-studio-tools-cards">
          <details class="ld-governance-group">
          <summary><strong><?= e($ld('governance_group_reference_docs')) ?></strong></summary>
            <details class="ld-advanced ld-governance-details">
            <summary><?= e($ld('contract_reference_summary')) ?></summary>
            <div class="gs-studio-tool-card" aria-disabled="true">
              <div class="gs-studio-tool-card-title-row">
                <span class="gs-studio-tool-card-title"><?= e($ld('contracts_note')) ?></span>
              </div>
              <?php if (!empty($resourceContracts)): ?>
              <ul>
                <?php foreach ($resourceContracts as $contract): ?>
                  <li>
                    <strong><?= e((string)($contract['name'] ?? '')) ?></strong>:
                    <?= e((string)($contract['meaning'] ?? '')) ?>
                    <code><?= e((string)($contract['path'] ?? '')) ?></code>
                  </li>
                <?php endforeach; ?>
              </ul>
              <?php endif; ?>
            </div>

            <div class="gs-studio-tool-card" aria-disabled="true">
              <div class="gs-studio-tool-card-title-row">
                <span class="gs-studio-tool-card-title"><?= e($ld('examples_title')) ?></span>
              </div>
              <?php if (!empty($resourceExamples)): ?>
              <ul>
                <?php foreach ($resourceExamples as $example): ?>
                  <li><code><?= e($example) ?></code></li>
                <?php endforeach; ?>
              </ul>
              <?php endif; ?>
            </div>
            </details>
            </details>
          </dd>
          </div>

          <div class="ld-workspace" data-workspace="governance" id="ld-workspace-build-db-discovery">
          <!-- Phase 19: Bootstrap Discovery group -->
          <dt class="ld-gov-hidden"><span><?= e($ld('governance_group_discovery')) ?></span></dt>
          <dd class="gs-studio-tools-cards">
          <details class="ld-governance-group">
          <summary><strong><?= e($ld('governance_group_discovery')) ?></strong></summary>
            <details class="ld-advanced ld-governance-details">
            <summary><?= e($ld('discovery_db_summary')) ?></summary>
            <div class="gs-studio-tool-card" aria-disabled="true">
              <div class="gs-studio-tool-card-title-row">
                <span class="gs-studio-tool-card-title"><?= e($ld('db_discovery_mode')) ?></span>
              </div>
              <p class="gs-studio-tool-card-purpose"><?= e($ld('db_discovery_note')) ?></p>
              <p class="gs-studio-tool-card-purpose"><?= e($ld('db_discovery_later')) ?></p>

              <?php if ($dataSourceError !== ''): ?>
                <p class="gs-studio-tool-card-purpose"><?= e($ld('db_metadata_unavailable')) ?></p>
              <?php endif; ?>

              <?php if (!empty($dataSourceOwners)): ?>
                <p class="gs-studio-tool-card-purpose"><?= e($ld('db_owner_title')) ?>:</p>
                <ul>
                  <?php foreach ($dataSourceOwners as $owner): ?>
                    <?php
                    $ownerKey = (string)($owner['owner_key'] ?? '');
                    $isSelectedOwner = $ownerKey !== '' && hash_equals($selectedDataSourceOwnerKey, $ownerKey);
                    ?>
                    <li>
                      <?php if ($ownerKey !== ''): ?>
                         <a href="/apps/studio/tools/label-designer<?= $buildUrl(['owner' => $ownerKey]) ?>">
                          <?= e((string)($owner['display_name'] ?? $ownerKey)) ?>
                        </a>
                        <code><?= e($ownerKey) ?></code>
                      <?php else: ?>
                        <?= e((string)($owner['display_name'] ?? '')) ?>
                      <?php endif; ?>
                      <?php if ($isSelectedOwner): ?>
                        <strong><?= e($ld('db_selected_owner')) ?></strong>
                      <?php endif; ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>

              <p class="gs-studio-tool-card-purpose">
                <?= e($ld('db_candidate_summary')) ?>:
                <?= (int)($dataSourceDiscovery['source_count'] ?? 0) ?>
                /
                <?= (int)($dataSourceDiscovery['column_count'] ?? 0) ?>
              </p>

              <?php if (empty($candidateSources)): ?>
                <p class="gs-studio-tool-card-purpose"><?= e($ld('db_no_candidates')) ?></p>
              <?php else: ?>
                <?php foreach ($candidateSources as $source): ?>
                  <h5>
                    <code><?= e((string)($source['source_name'] ?? '')) ?></code>
                    <small><?= e((string)($source['source_type'] ?? '')) ?></small>
                  </h5>
                  <p class="gs-studio-tool-card-purpose">
                    <?= e($ld('db_candidate_status')) ?>:
                    <code><?= e((string)($source['approval_status'] ?? 'candidate_only')) ?></code>
                  </p>
                  <p class="gs-studio-tool-card-purpose">
                    <?= e($ld('db_candidate_reason')) ?>:
                    <?= e((string)($source['candidate_reason'] ?? '')) ?>
                  </p>
                  <?php $columns = isset($source['columns']) && is_array($source['columns']) ? $source['columns'] : []; ?>
                  <?php if (!empty($columns)): ?>
                    <p class="gs-studio-tool-card-purpose"><?= e($ld('db_columns')) ?>:</p>
                    <ul>
                      <?php foreach ($columns as $column): ?>
                        <li>
                          <code><?= e((string)($column['column_name'] ?? '')) ?></code>
                          <?= e((string)($column['data_type'] ?? '')) ?>
                          <?= e((string)($column['is_nullable'] ?? '')) ?>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                  <?php endif; ?>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
            </details>
            </details>
          </dd>
          </div>

          <div class="ld-workspace" data-workspace="governance" id="ld-workspace-governance-ownership">
          <!-- Phase 19: Resource Readiness group -->
          <dt class="ld-gov-hidden"><span><?= e($ld('governance_group_readiness')) ?></span></dt>
          <dd class="gs-studio-tools-cards">
          <details class="ld-governance-group">
          <summary><strong><?= e($ld('governance_group_readiness')) ?></strong></summary>
            <details class="ld-advanced ld-governance-details">
            <summary><?= e($ld('ownership_boundary_summary')) ?></summary>
            <div class="gs-studio-tool-card" aria-disabled="true">
              <div class="gs-studio-tool-card-title-row">
                <span class="gs-studio-tool-card-title"><?= e($ld('ownership_title')) ?></span>
              </div>
              <p class="gs-studio-tool-card-purpose"><?= e($ld('ownership_note')) ?></p>
            </div>
            </details>
            </details>
          </dd>
          </div>

          <div class="ld-workspace" data-workspace="governance" id="ld-workspace-governance-readiness">
          <!-- Phase 19: Resource Readiness group -->
          <dt class="ld-gov-hidden"><span><?= e($ld('governance_group_readiness')) ?></span></dt>
          <dd class="gs-studio-tools-cards">
          <details class="ld-governance-group">
          <summary><strong><?= e($ld('governance_group_readiness')) ?></strong></summary>
            <details class="ld-advanced ld-governance-details">
            <summary><?= e($ld('owner_readiness_summary')) ?></summary>
            <div class="gs-studio-tool-card" aria-disabled="true">
              <div class="gs-studio-tool-card-title-row">
                <span class="gs-studio-tool-card-title"><?= e($ld('readiness_title')) ?></span>
              </div>
              <p class="gs-studio-tool-card-purpose"><?= e($ld('readiness_note')) ?></p>
              <p class="gs-studio-tool-card-purpose">
                <?= e($ld('readiness_summary')) ?>:
                <?= (int)($resourceReadiness['owner_count'] ?? 0) ?> owners
                (<?= e($ld('readiness_complete_count')) ?>: <?= (int)($resourceReadiness['complete_count'] ?? 0) ?>,
                <?= e($ld('readiness_partial_count')) ?>: <?= (int)($resourceReadiness['partial_count'] ?? 0) ?>,
                <?= e($ld('readiness_absent_count')) ?>: <?= (int)($resourceReadiness['absent_count'] ?? 0) ?>)
              </p>

              <?php if (empty($readinessOwners)): ?>
                <p class="gs-studio-tool-card-purpose"><?= e($ld('readiness_no_owners')) ?></p>
              <?php else: ?>
                <details class="ld-advanced ld-governance-details">
                <summary><?= e($ld('owner_readiness_detail_summary')) ?></summary>
                <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;font-size:0.85em">
                  <thead>
                    <tr>
                      <th style="text-align:left;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)"><?= e($ld('readiness_owner')) ?></th>
                      <th style="text-align:left;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)"><?= e($ld('readiness_path')) ?></th>
                      <th style="text-align:center;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)"><?= e($ld('readiness_folder_labels')) ?></th>
                      <th style="text-align:center;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)"><?= e($ld('readiness_folder_contexts')) ?></th>
                      <th style="text-align:center;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)"><?= e($ld('readiness_folder_templates')) ?></th>
                      <th style="text-align:center;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)"><?= e($ld('readiness_folder_rules')) ?></th>
                      <th style="text-align:center;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)"><?= e($ld('readiness_ready')) ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($readinessOwners as $ro): ?>
                      <?php
                      $roReadiness = isset($ro['readiness']) && is_array($ro['readiness']) ? $ro['readiness'] : [];
                      $roLabels = $roReadiness[0] ?? [];
                      $roContexts = $roReadiness[1] ?? [];
                      $roTemplates = $roReadiness[2] ?? [];
                      $roRules = $roReadiness[3] ?? [];
                      $roAllExist = !empty($ro['all_exist']);
                      $roNoneExist = !empty($ro['none_exist']);
                      $roStatus = $roAllExist ? $ld('readiness_ready') : ($roNoneExist ? $ld('readiness_absent') : $ld('readiness_partial'));
                      $roStatusColor = $roAllExist ? 'var(--status-positive,#2e7d32)' : ($roNoneExist ? 'var(--status-muted,#999)' : 'var(--status-warning,#e65100)');
                      ?>
                      <tr>
                        <td style="padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ddd)">
                          <strong><?= e((string)($ro['display_name'] ?? $ro['owner_key'] ?? '')) ?></strong>
                          <?php if (!empty($ro['is_label_lifecycle_owner'])): ?>
                            <small><?= e((string)($ro['owner_type'] ?? '')) ?></small>
                          <?php else: ?>
                            <small><?= e((string)($ro['owner_type'] ?? '')) ?> — infrastructure</small>
                          <?php endif; ?>
                        </td>
                        <td style="padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ddd)"><code><?= e((string)($ro['root_path'] ?? '')) ?></code></td>
                        <td style="text-align:center;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ddd)"><?= !empty($roLabels['exists']) ? '✓' : '—' ?></td>
                        <td style="text-align:center;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ddd)"><?= !empty($roContexts['exists']) ? '✓' : '—' ?></td>
                        <td style="text-align:center;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ddd)"><?= !empty($roTemplates['exists']) ? '✓' : '—' ?></td>
                        <td style="text-align:center;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ddd)"><?= !empty($roRules['exists']) ? '✓' : '—' ?></td>
                        <td style="text-align:center;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ddd);color:<?= $roStatusColor ?>"><?= e($roStatus) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
                </div>
                </details>
              <?php endif; ?>
            </div>

            <div class="gs-studio-tool-card">
              <div class="gs-studio-tool-card-title-row">
                <span class="gs-studio-tool-card-title"><?= e($ld('readiness_create_title')) ?></span>
              </div>
              <p class="gs-studio-tool-card-purpose"><?= e($ld('readiness_create_note')) ?></p>

              <?php if (empty($readinessOwners)): ?>
                <p class="gs-studio-tool-card-purpose"><?= e($ld('readiness_no_owners')) ?></p>
              <?php else:
                $lifecycleIncomplete = array_filter($readinessOwners, static fn (array $ro): bool => !empty($ro['is_label_lifecycle_owner']) && empty($ro['all_exist']));
                $lifecycleComplete = array_filter($readinessOwners, static fn (array $ro): bool => !empty($ro['is_label_lifecycle_owner']) && !empty($ro['all_exist']));
                $nonLifecycleCount = count(array_filter($readinessOwners, static fn (array $ro): bool => empty($ro['is_label_lifecycle_owner'])));
              ?>
                <?php if (empty($lifecycleIncomplete)): ?>
                  <p class="gs-studio-tool-card-purpose"><?= e($ld('readiness_create_disabled')) ?></p>
                <?php else: ?>
                  <form method="post" action="/apps/studio/tools/label-designer/create-folders" onsubmit="return confirm('<?= e($ld('readiness_create_confirm')) ?>?');">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="workspace" value="<?= $activeWorkspace ?>">
                    <p class="gs-studio-tool-card-purpose">
                      <?= e($ld('readiness_create_select')) ?>:
                      <select name="owner_key" aria-label="<?= e($ld('readiness_create_select')) ?>">
                        <?php $firstIncomplete = null; ?>
                        <?php foreach ($lifecycleIncomplete as $ro): ?>
                          <?php $firstIncomplete ??= $ro; ?>
                        <?php endforeach; ?>
                        <?php foreach ($readinessOwners as $ro): ?>
                          <?php if (empty($ro['is_label_lifecycle_owner'])) { continue; } ?>
                          <?php $roAllExist = !empty($ro['all_exist']); ?>
                          <option value="<?= e((string)($ro['owner_key'] ?? '')) ?>"<?= $firstIncomplete !== null && hash_equals((string)$ro['owner_key'], (string)$firstIncomplete['owner_key']) && !$roAllExist ? ' selected' : '' ?>>
                            <?= e((string)($ro['display_name'] ?? $ro['owner_key'] ?? '')) ?>
                            <?= $roAllExist ? '(' . $ld('readiness_ready') . ')' : '' ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </p>
                    <label>
                      <input type="checkbox" name="confirm_create" value="yes" required>
                      <?= e($ld('readiness_create_confirm')) ?>
                    </label>
                    <button type="submit"><?= e($ld('readiness_create_submit')) ?></button>
                  </form>
                  <?php if (count($lifecycleComplete) > 0): ?>
                    <p class="gs-studio-tool-card-purpose">
                      <small><?= count($lifecycleComplete) ?> lifecycle owner(s) already complete — hidden from selector.</small>
                    </p>
                  <?php endif; ?>
                <?php endif; ?>
                <?php if ($nonLifecycleCount > 0): ?>
                  <p class="gs-studio-tool-card-purpose">
                    <small><?= $nonLifecycleCount ?> <?= e($tt('studio.infrastructure_owner_s_label')) ?> </small>
                  </p>
                <?php endif; ?>
              <?php endif; ?>
            </div>
            </details>
            </details>
          </dd>
          </div>

          <div class="ld-workspace" data-workspace="governance" id="ld-workspace-governance-metadata">
          <!-- Phase 19: Metadata & Migration group -->
          <dt class="ld-gov-hidden"><span><?= e($ld('governance_group_metadata')) ?></span></dt>
          <dd class="gs-studio-tools-cards">
          <details class="ld-governance-group">
          <summary><strong><?= e($ld('governance_group_metadata')) ?></strong></summary>
            <details class="ld-advanced ld-governance-details">
            <summary><?= e($ld('metadata_migration_summary')) ?></summary>
            <div class="gs-studio-tool-card" aria-disabled="true">
              <div class="gs-studio-tool-card-title-row">
                <span class="gs-studio-tool-card-title"><?= e($ld('metadata_title')) ?></span>
              </div>
              <p class="gs-studio-tool-card-purpose"><?= e($ld('metadata_note')) ?></p>

              <?php if (empty($metadataAnalysis)): ?>
                <p class="gs-studio-tool-card-purpose"><?= e($ld('preview_unavailable')) ?></p>
              <?php else: ?>
                <p class="gs-studio-tool-card-purpose">
                  <?= e($ld('metadata_analysis_summary')) ?>:
                  <?= (int)($metadataAnalysis['owner_count'] ?? 0) ?> <?= e($ld('metadata_owner_count')) ?>,
                  <?= (int)($metadataAnalysis['context_count'] ?? 0) ?> <?= e($ld('metadata_context_count')) ?>,
                  <?= (int)($metadataAnalysis['template_count'] ?? 0) ?> <?= e($ld('metadata_template_count')) ?>,
                  <?= (int)($metadataAnalysis['complete_count'] ?? 0) ?> <?= e($ld('metadata_complete_count')) ?>,
                  <?= (int)($metadataAnalysis['legacy_count'] ?? 0) ?> <?= e($ld('metadata_legacy_count')) ?>
                </p>
              <?php endif; ?>

              <?php
              $analysisOwners = isset($metadataAnalysis['owners']) && is_array($metadataAnalysis['owners'])
                  ? array_values(array_filter($metadataAnalysis['owners'], 'is_array'))
                  : [];
              ?>
              <?php if (!empty($analysisOwners)): ?>
                <details class="ld-advanced ld-governance-details">
                <summary><?= e($ld('metadata_owner_details_summary')) ?></summary>
                <div style="overflow-x:auto;margin:8px 0">
                <table style="width:100%;border-collapse:collapse;font-size:0.85em">
                  <thead>
                    <tr>
                      <th style="text-align:left;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)"><?= e($ld('metadata_table_owner')) ?></th>
                      <th style="text-align:left;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)"><?= e($ld('metadata_table_type')) ?></th>
                      <th style="text-align:left;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)"><?= e($ld('metadata_table_path')) ?></th>
                      <th style="text-align:center;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)"><?= e($ld('metadata_table_has_ownerkey')) ?></th>
                      <th style="text-align:left;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)"><?= e($ld('metadata_table_status')) ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($analysisOwners as $ao): ?>
                      <?php
                      $aoOwnerKey = (string)($ao['owner_key'] ?? '');
                      $aoOwnerType = (string)($ao['owner_type'] ?? '');
                      $aoRoot = (string)($ao['root_path'] ?? '');
                      $aoRoutes = isset($ao['routes']) && is_array($ao['routes']) ? $ao['routes'] : [];
                      $aoStatus = !empty($ao['metadata_complete']) ? 'metadata_status_complete' : 'metadata_status_legacy';
                      $aoColor = !empty($ao['metadata_complete']) ? 'var(--status-positive,#2e7d32)' : 'var(--status-warning,#e65100)';
                      ?>
                      <tr>
                        <td style="padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ddd)">
                          <strong><?= e($aoOwnerKey) ?></strong>
                          <small><?= e($aoOwnerType) ?></small>
                        </td>
                        <td style="padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ddd)">
                          <?php
                          $ctCount = count(array_filter($aoRoutes, static function (array $r): bool { return ($r['type'] ?? '') === 'context' && !empty($r['has_owner_key']); }));
                          $ltCount = count(array_filter($aoRoutes, static function (array $r): bool { return ($r['type'] ?? '') === 'template' && !empty($r['has_owner_key']); }));
                          ?>
                          <small>
                            C:<?= $ctCount ?>/T:<?= $ltCount ?>
                          </small>
                        </td>
                        <td style="padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ddd)"><code><?= e($aoRoot) ?></code></td>
                        <td style="text-align:center;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ddd)">
                          <?php if (!empty($ao['metadata_complete'])): ?>
                            <span style="color:var(--status-positive,#2e7d32)">✓</span>
                          <?php else: ?>
                            <span style="color:var(--status-warning,#e65100)">✗</span>
                          <?php endif; ?>
                        </td>
                        <td style="padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ddd);color:<?= $aoColor ?>">
                          <?= e($ld($aoStatus)) ?>
                        </td>
                      </tr>

                      <?php
                        $legacyRoutes = array_filter($aoRoutes, static function (array $r): bool { return empty($r['has_owner_key']); });
                      ?>
                      <?php if (!empty($legacyRoutes)): ?>
                        <tr>
                          <td colspan="5" style="padding:2px 8px 6px 24px;border-bottom:1px solid var(--border-subtle,#ddd);font-size:0.8em">
                            <span style="color:var(--status-warning,#e65100)"><?= e($ld('metadata_status_legacy')) ?>:</span>
                            <ul style="margin:2px 0">
                              <?php foreach ($legacyRoutes as $lr): ?>
                                <?php
                                $lrType = (string)($lr['type'] ?? '');
                                $lrLabel = (string)($lr['context_key'] ?? $lr['template_key'] ?? $lr['file'] ?? '');
                                $lrFile = (string)($lr['file'] ?? '');
                                ?>
                                <li>
                                  <code><?= e($lrType) ?></code>:
                                  <?= e($lrLabel) ?>
                                  <small><code><?= e($lrFile) ?></code></small>
                                </li>
                              <?php endforeach; ?>
                            </ul>
                          </td>
                        </tr>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </tbody>
                </table>
                </div>
                </details>

                <?php
                $legacyContextCount = count(array_filter($analysisOwners, static function (array $ao): bool {
                    $routes = isset($ao['routes']) && is_array($ao['routes']) ? $ao['routes'] : [];
                    return count(array_filter($routes, static function (array $r): bool { return ($r['type'] ?? '') === 'context' && empty($r['has_owner_key']); })) > 0;
                }));
                $legacyTemplateCount = count(array_filter($analysisOwners, static function (array $ao): bool {
                    $routes = isset($ao['routes']) && is_array($ao['routes']) ? $ao['routes'] : [];
                    return count(array_filter($routes, static function (array $r): bool { return ($r['type'] ?? '') === 'template' && empty($r['has_owner_key']); })) > 0;
                }));
                ?>
                <?php if ($legacyContextCount > 0 || $legacyTemplateCount > 0): ?>
                  <p class="gs-studio-tool-card-purpose" style="color:var(--status-warning,#e65100)">
                    <?php if ($legacyContextCount > 0): ?>
                      <?= e($ld('metadata_legacy_context')) ?>: <?= $legacyContextCount ?> owner(s) |
                    <?php endif; ?>
                    <?php if ($legacyTemplateCount > 0): ?>
                      <?= e($ld('metadata_legacy_template')) ?>: <?= $legacyTemplateCount ?> owner(s)
                    <?php endif; ?>
                    — <a href="#metadata-migration-preview"><?= e($ld('metadata_migration_title')) ?></a>
                  </p>
                <?php endif; ?>
              <?php else: ?>
                <p class="gs-studio-tool-card-purpose"><?= e($ld('readiness_no_owners')) ?></p>
              <?php endif; ?>
            </div>

            <!-- Migration Preview -->
            <div class="gs-studio-tool-card" id="metadata-migration-preview">
              <div class="gs-studio-tool-card-title-row">
                <span class="gs-studio-tool-card-title"><?= e($ld('metadata_migration_title')) ?></span>
              </div>
              <p class="gs-studio-tool-card-purpose"><?= e($ld('metadata_migration_note')) ?></p>

              <?php if (empty($analysisOwners)): ?>
                <p class="gs-studio-tool-card-purpose"><?= e($ld('preview_unavailable')) ?></p>
              <?php else: ?>
                <form method="post" action="/apps/studio/tools/label-designer/migration-preview">
                  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="workspace" value="<?= $activeWorkspace ?>">
                  <input type="hidden" name="owner" value="<?= e($selectedDataSourceOwnerKey) ?>">
                  <p class="gs-studio-tool-card-purpose">
                    <?= e($ld('metadata_migration_select')) ?>:
                    <select name="resource_path" aria-label="<?= e($ld('metadata_migration_select')) ?>">
                      <?php foreach ($analysisOwners as $ao): ?>
                        <?php
                        $aoRoutes = isset($ao['routes']) && is_array($ao['routes']) ? $ao['routes'] : [];
                      $legacyRoutes = array_filter($aoRoutes, static function (array $r): bool { return empty($r['has_owner_key']); });
                        ?>
                        <?php foreach ($legacyRoutes as $lr): ?>
                          <?php $lrFile = (string)($lr['file'] ?? ''); ?>
                          <?php if ($lrFile !== ''): ?>
                            <option value="<?= e($lrFile) ?>">
                              <?= e((string)($ao['owner_key'] ?? '')) ?> / <?= e((string)($lr['type'] ?? '')) ?> / <?= e((string)($lr['context_key'] ?? $lr['template_key'] ?? basename($lrFile))) ?>
                            </option>
                          <?php endif; ?>
                        <?php endforeach; ?>
                      <?php endforeach; ?>
                    </select>
                  </p>
                  <button type="submit"><?= e($ld('metadata_migration_button')) ?></button>
                </form>
              <?php endif; ?>

                <?php $mpValid = false; ?>
                <?php if (!empty($migrationPreview)): ?>
                <hr style="margin:12px 0;border:none;border-top:1px solid var(--border-subtle,#ddd)">

                <?php
                $mpOk = !empty($migrationPreview['ok']);
                $mpErrors = isset($migrationPreview['errors']) && is_array($migrationPreview['errors'])
                    ? array_values(array_filter(array_map(static fn ($v): string => trim((string)$v), $migrationPreview['errors']), static fn (string $v): bool => $v !== ''))
                    : [];
                $mpBlocked = !$mpOk || !empty($mpErrors);
                $mpErrorStr = $mpErrors !== [] ? $mpErrors[0] : '';
                $mpChecks = isset($migrationPreview['diagnostics']) && is_array($migrationPreview['diagnostics'])
                    ? array_values(array_filter($migrationPreview['diagnostics'], 'is_array'))
                    : [];
                $mpProposedChecks = isset($migrationPreview['proposed_diagnostics']) && is_array($migrationPreview['proposed_diagnostics'])
                    ? array_values(array_filter($migrationPreview['proposed_diagnostics'], 'is_array'))
                    : [];
                $mpProposed = isset($migrationPreview['proposed_metadata']) && is_array($migrationPreview['proposed_metadata'])
                    ? $migrationPreview['proposed_metadata']
                    : [];
                $mpCurrentJson = trim((string)($migrationPreview['current_json'] ?? ''));
                $mpResourceType = trim((string)($migrationPreview['resource_type'] ?? ''));
                $mpSchema = trim((string)($migrationPreview['schema'] ?? ''));
                $mpCurrentOwnerKey = trim((string)($migrationPreview['current_metadata']['owner_key'] ?? ''));
                $mpProposedOwnerKey = trim((string)($mpProposed['owner_key'] ?? ''));
                $mpNoChange = !empty($migrationPreview['no_change_needed']);
                $mpValid = !empty($migrationPreview['valid']);

                $mpAddedKeys = [];
                if ($mpValid && !$mpNoChange && !empty($mpProposed)) {
                    if ($mpProposedOwnerKey !== '' && $mpCurrentOwnerKey === '') {
                        $mpAddedKeys['owner_key'] = $mpProposedOwnerKey;
                    }
                    foreach (['owner_type', 'owner_root', 'resource_type'] as $metadataKey) {
                        $proposedValue = trim((string)($mpProposed[$metadataKey] ?? ''));
                        $currentValue = trim((string)($migrationPreview['current_metadata'][$metadataKey] ?? ''));
                        if ($proposedValue !== '' && $currentValue === '') {
                            $mpAddedKeys[$metadataKey] = $proposedValue;
                        }
                    }
                }
                ?>

                <?php if ($mpBlocked): ?>
                  <p class="gs-studio-tool-card-purpose" style="color:var(--status-error,#c62828)">
                    <strong><?= e($ld('metadata_migration_blocked')) ?>:</strong>
                    <?= e($mpErrorStr !== '' ? $mpErrorStr : 'Unknown') ?>
                  </p>
                <?php elseif ($mpNoChange): ?>
                  <p class="gs-studio-tool-card-purpose" style="color:var(--status-positive,#2e7d32)">
                    <?= e($ld('metadata_migration_no_change')) ?>
                  </p>
                <?php elseif ($mpValid): ?>
                  <p class="gs-studio-tool-card-purpose" style="color:var(--status-positive,#2e7d32)">
                    <?= e($ld('metadata_migration_change')) ?>
                  </p>

                  <p class="gs-studio-tool-card-purpose">
                    <strong><?= e($ld('metadata_migration_resource_type')) ?>:</strong>
                    <?= e($mpResourceType) ?>
                    <?php if ($mpSchema !== ''): ?>
                      <small><code><?= e($mpSchema) ?></code></small>
                    <?php endif; ?>
                  </p>

                  <?php if ($mpProposedOwnerKey !== ''): ?>
                    <p class="gs-studio-tool-card-purpose">
                      <strong><?= e($ld('metadata_migration_inferred_owner')) ?>:</strong>
                      <code><?= e($mpProposedOwnerKey) ?></code>
                    </p>
                  <?php endif; ?>

                  <?php if (!empty($mpAddedKeys)): ?>
                    <p class="gs-studio-tool-card-purpose"><strong><?= e($ld('metadata_migration_added_keys')) ?>:</strong></p>
                    <ul>
                      <?php foreach ($mpAddedKeys as $key => $value): ?>
                        <li><code><?= e($key) ?></code> → <code><?= e($value) ?></code></li>
                      <?php endforeach; ?>
                    </ul>
                  <?php endif; ?>

                  <?php if (!empty($mpChecks)): ?>
                    <p class="gs-studio-tool-card-purpose"><strong><?= e($ld('metadata_migration_section')) ?> before:</strong></p>
                    <div style="overflow-x:auto;margin:8px 0">
                    <table style="width:100%;border-collapse:collapse;font-size:0.85em">
                      <thead>
                        <tr>
                          <th style="text-align:left;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)">Severity</th>
                          <th style="text-align:left;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)">Check</th>
                          <th style="text-align:left;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)">Message</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($mpChecks as $check): ?>
                          <?php
                          $checkSeverity = (string)($check['severity'] ?? 'INFO');
                          $checkCheckKey = (string)($check['check_key'] ?? $check['check'] ?? '');
                          $checkMessage = (string)($check['message'] ?? '');
                          $sevColor = match ($checkSeverity) {
                              'ERROR' => 'var(--status-error,#c62828)',
                              'FAIL' => 'var(--status-error,#c62828)',
                              'WARN' => 'var(--status-warning,#e65100)',
                              'PASS' => 'var(--status-positive,#2e7d32)',
                              default => 'var(--text-muted,#666)',
                          };
                          ?>
                          <tr>
                            <td style="padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ddd);color:<?= $sevColor ?>"><?= e($checkSeverity) ?></td>
                            <td style="padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ddd)"><?= e($checkCheckKey) ?></td>
                            <td style="padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ddd)"><?= e($checkMessage) ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                    </div>
                  <?php endif; ?>

                  <?php if (!empty($mpProposedChecks)): ?>
                    <p class="gs-studio-tool-card-purpose"><strong><?= e($ld('metadata_migration_section')) ?> after:</strong></p>
                    <div style="overflow-x:auto;margin:8px 0">
                    <table style="width:100%;border-collapse:collapse;font-size:0.85em">
                      <thead>
                        <tr>
                          <th style="text-align:left;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)">Severity</th>
                          <th style="text-align:left;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)">Check</th>
                          <th style="text-align:left;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)">Message</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($mpProposedChecks as $check): ?>
                          <?php
                          $checkSeverity = (string)($check['severity'] ?? 'INFO');
                          $checkCheckKey = (string)($check['check_key'] ?? $check['check'] ?? '');
                          $checkMessage = (string)($check['message'] ?? '');
                          $sevColor = match ($checkSeverity) {
                              'ERROR' => 'var(--status-error,#c62828)',
                              'FAIL' => 'var(--status-error,#c62828)',
                              'WARN' => 'var(--status-warning,#e65100)',
                              'PASS' => 'var(--status-positive,#2e7d32)',
                              default => 'var(--text-muted,#666)',
                          };
                          ?>
                          <tr>
                            <td style="padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ddd);color:<?= $sevColor ?>"><?= e($checkSeverity) ?></td>
                            <td style="padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ddd)"><?= e($checkCheckKey) ?></td>
                            <td style="padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ddd)"><?= e($checkMessage) ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                    </div>
                  <?php endif; ?>

                  <?php if ($mpCurrentJson !== ''): ?>
                    <p class="gs-studio-tool-card-purpose"><strong><?= e($ld('metadata_migration_before_json')) ?>:</strong></p>
                    <pre style="max-height:200px;overflow-y:auto;background:var(--bg-subtle,#f5f5f5);padding:6px;border-radius:3px;font-size:0.82em"><code><?= e($mpCurrentJson) ?></code></pre>
                  <?php endif; ?>

                  <?php if (!empty($mpProposed)): ?>
                    <p class="gs-studio-tool-card-purpose"><strong><?= e($ld('metadata_migration_after_json')) ?>:</strong></p>
                    <pre style="max-height:200px;overflow-y:auto;background:var(--bg-subtle,#f5f5f5);padding:6px;border-radius:3px;font-size:0.82em"><code><?= e(json_encode($mpProposed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></code></pre>
                  <?php endif; ?>
                <?php else: ?>
                  <p class="gs-studio-tool-card-purpose" style="color:var(--status-error,#c62828)">
                    <?= e($ld('metadata_migration_blocked')) ?>
                  </p>
                <?php endif; ?>
              <?php else: ?>
                <p class="gs-studio-tool-card-purpose"><em><?= e($ld('metadata_migration_no_result')) ?></em></p>
              <?php endif; ?>

              <?php if (!empty($migrationPreview) && !empty($mpValid)): ?>
                <hr style="margin:12px 0;border:none;border-top:1px solid var(--border-subtle,#ddd)">

                <div class="gs-studio-tool-card-title-row">
                  <span class="gs-studio-tool-card-title"><?= e($ld('metadata_migration_apply_title')) ?></span>
                </div>
                <p class="gs-studio-tool-card-purpose"><?= e($ld('metadata_migration_apply_note')) ?></p>

                <form method="post" action="/apps/studio/tools/label-designer/migration-apply">
                  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="workspace" value="<?= $activeWorkspace ?>">
                  <input type="hidden" name="owner" value="<?= e($previewOwnerKey) ?>">

                  <?php
                  $mpResourcePath = (string)($migrationPreview['resource_path'] ?? '');
                  ?>
                  <input type="hidden" name="resource_path" value="<?= e($mpResourcePath) ?>">

                  <label style="display:block;margin:8px 0">
                    <input type="checkbox" name="confirm_apply" value="1" required>
                    <?= e($ld('metadata_migration_apply_checkbox')) ?>
                  </label>
                  <button type="submit" style="margin-top:8px"><?= e($ld('metadata_migration_apply_button')) ?></button>
                </form>

                <?php if (!empty($migrationApply)): ?>
                  <?php
                  $mApplOk = !empty($migrationApply['applied']);
                  $mApplDiag = isset($migrationApply['diagnostics']) && is_array($migrationApply['diagnostics'])
                      ? array_values(array_filter($migrationApply['diagnostics'], 'is_array'))
                      : [];
                  $mApplAllPass = !empty($migrationApply['all_pass']);
                  $mApplSnapshot = (string)($migrationApply['snapshot_path'] ?? '');
                  $mApplBackup = (string)($migrationApply['backup_path'] ?? '');
                  $mApplErrors = isset($migrationApply['errors']) && is_array($migrationApply['errors'])
                      ? array_values(array_filter(array_map(static fn ($v): string => trim((string)$v), $migrationApply['errors']), static fn (string $v): bool => $v !== ''))
                      : [];
                  ?>
                  <hr style="margin:12px 0;border:none;border-top:1px solid var(--border-subtle,#ddd)">

                  <?php if ($mApplOk): ?>
                    <p class="gs-studio-tool-card-purpose" style="color:var(--status-positive,#2e7d32)">
                      <?= e($ld('metadata_migration_apply_applied')) ?>
                    </p>
                  <?php else: ?>
                    <p class="gs-studio-tool-card-purpose" style="color:var(--status-error,#c62828)">
                      <?= e($ld('metadata_migration_apply_failed')) ?>
                    </p>
                    <?php if (!empty($mApplErrors)): ?>
                      <ul>
                        <?php foreach ($mApplErrors as $err): ?>
                          <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                      </ul>
                    <?php endif; ?>
                  <?php endif; ?>

                  <?php if (!empty($mApplDiag)): ?>
                    <p class="gs-studio-tool-card-purpose">
                      <strong><?= $mApplAllPass ? e($ld('metadata_migration_apply_result_all_pass')) : e($ld('metadata_migration_apply_result_with_issues')) ?></strong>
                    </p>
                    <div style="overflow-x:auto;margin:8px 0">
                    <table style="width:100%;border-collapse:collapse;font-size:0.85em">
                      <thead>
                        <tr>
                          <th style="text-align:left;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)">Rule</th>
                          <th style="text-align:left;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)">Severity</th>
                          <th style="text-align:left;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)">Check</th>
                          <th style="text-align:left;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)">Message</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($mApplDiag as $mD): ?>
                          <?php
                          $mDSeverity = (string)($mD['severity'] ?? 'PASS');
                          $mDCheck = (string)($mD['check_key'] ?? $mD['label'] ?? '');
                          $mDMessage = (string)($mD['message'] ?? '');
                          $mDRule = (string)($mD['rule_id'] ?? '');
                          $mDColor = match ($mDSeverity) {
                              'ERROR' => 'var(--status-error,#c62828)',
                              'FAIL' => 'var(--status-error,#c62828)',
                              'WARN' => 'var(--status-warning,#e65100)',
                              'PASS' => 'var(--status-positive,#2e7d32)',
                              default => 'var(--text-muted,#666)',
                          };
                          ?>
                          <tr>
                            <td style="padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ddd)"><?= e($mDRule) ?></td>
                            <td style="padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ddd);color:<?= $mDColor ?>"><?= e($mDSeverity) ?></td>
                            <td style="padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ddd)"><?= e($mDCheck) ?></td>
                            <td style="padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ddd)"><?= e($mDMessage) ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                    </div>
                  <?php endif; ?>

                  <?php if (!empty($migrationApply['global_diagnostics_before']) || !empty($migrationApply['global_diagnostics_after'])): ?>
                    <hr style="margin:12px 0;border:none;border-top:1px solid var(--border-subtle,#ddd)">
                    <p class="gs-studio-tool-card-purpose"><strong><?= e($ld('metadata_migration_global_after')) ?>:</strong></p>
                    <div style="overflow-x:auto;margin:8px 0">
                    <table style="width:100%;border-collapse:collapse;font-size:0.85em">
                      <thead>
                        <tr>
                          <th style="text-align:left;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)">Metric</th>
                          <th style="text-align:left;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)">Before</th>
                          <th style="text-align:left;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)">After</th>
                          <th style="text-align:left;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)">Change</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php
                        $gBefore = $migrationApply['global_diagnostics_before'] ?? [];
                        $gAfter = $migrationApply['global_diagnostics_after'] ?? [];
                        $gMets = [
                            ['key' => 'legacy_fallback_count', 'label' => 'Legacy fallback resources'],
                            ['key' => 'metadata_count', 'label' => 'Metadata-first resources'],
                        ];
                        ?>
                        <?php foreach ($gMets as $gMet): ?>
                          <?php
                          $gK = $gMet['key'];
                          $gL = $gMet['label'];
                          $gBV = (int)($gBefore[$gK] ?? 0);
                          $gAV = (int)($gAfter[$gK] ?? 0);
                          $gDelta = $gAV - $gBV;
                          $gDeltaStr = $gDelta === 0 ? '—' : ($gDelta > 0 ? '+' . $gDelta : (string)$gDelta);
                          $gDeltaColor = $gDelta < 0 ? 'var(--status-positive,#2e7d32)' : ($gDelta > 0 ? 'var(--status-warning,#e65100)' : 'inherit');
                          ?>
                          <tr>
                            <td style="padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ddd)"><?= e($gL) ?></td>
                            <td style="padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ddd)"><?= $gBV ?></td>
                            <td style="padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ddd)"><?= $gAV ?></td>
                            <td style="padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ddd);color:<?= $gDeltaColor ?>"><?= e($gDeltaStr) ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                    </div>
                  <?php endif; ?>

                  <?php if ($mApplSnapshot !== ''): ?>
                    <p class="gs-studio-tool-card-purpose"><strong><?= e($ld('metadata_migration_snapshot')) ?>:</strong> <?= e($mApplSnapshot) ?></p>
                  <?php endif; ?>
                  <?php if ($mApplBackup !== ''): ?>
                    <p class="gs-studio-tool-card-purpose"><strong><?= e($ld('metadata_migration_backup')) ?>:</strong> <?= e($mApplBackup) ?></p>
                  <?php endif; ?>
                <?php endif; ?>
              <?php elseif ($mpValid): ?>
                <?php if (!empty($migrationApply)): ?>
                  <?php
                  $mApplDiag = isset($migrationApply['diagnostics']) && is_array($migrationApply['diagnostics'])
                      ? array_values(array_filter($migrationApply['diagnostics'], 'is_array'))
                      : [];
                  $mApplAllPass = !empty($migrationApply['all_pass']);
                  $mApplSnapshot = (string)($migrationApply['snapshot_path'] ?? '');
                  ?>
                  <hr style="margin:12px 0;border:none;border-top:1px solid var(--border-subtle,#ddd)">
                  <p class="gs-studio-tool-card-purpose" style="color:var(--status-positive,#2e7d32)">
                    <?= e($ld('metadata_migration_apply_applied')) ?>
                  </p>
                  <?php if (!empty($mApplDiag)): ?>
                    <div style="overflow-x:auto;margin:8px 0">
                    <table style="width:100%;border-collapse:collapse;font-size:0.85em">
                      <thead>
                        <tr>
                          <th style="text-align:left;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)">Rule</th>
                          <th style="text-align:left;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)">Severity</th>
                          <th style="text-align:left;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)">Check</th>
                          <th style="text-align:left;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)">Message</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($mApplDiag as $mD): ?>
                          <?php
                          $mDSeverity = (string)($mD['severity'] ?? 'PASS');
                          $mDCheck = (string)($mD['check_key'] ?? $mD['label'] ?? '');
                          $mDMessage = (string)($mD['message'] ?? '');
                          $mDRule = (string)($mD['rule_id'] ?? '');
                          $mDColor = match ($mDSeverity) {
                              'ERROR' => 'var(--status-error,#c62828)',
                              'FAIL' => 'var(--status-error,#c62828)',
                              'WARN' => 'var(--status-warning,#e65100)',
                              'PASS' => 'var(--status-positive,#2e7d32)',
                              default => 'var(--text-muted,#666)',
                          };
                          ?>
                          <tr>
                            <td style="padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ddd)"><?= e($mDRule) ?></td>
                            <td style="padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ddd);color:<?= $mDColor ?>"><?= e($mDSeverity) ?></td>
                            <td style="padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ddd)"><?= e($mDCheck) ?></td>
                            <td style="padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ddd)"><?= e($mDMessage) ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                    </div>
                  <?php endif; ?>
                  <?php if ($mApplSnapshot !== ''): ?>
                    <p class="gs-studio-tool-card-purpose"><strong><?= e($ld('metadata_migration_snapshot')) ?>:</strong> <?= e($mApplSnapshot) ?></p>
                  <?php endif; ?>
                <?php endif; ?>
              <?php endif; ?>
            </div>
            </details>
          </dd>
          </div>

          <div class="ld-workspace" data-workspace="governance" id="ld-workspace-governance-dry-run">
          <!-- Phase 19: Dry-Run group -->
          <dt class="ld-gov-hidden"><span><?= e($ld('governance_group_dry_run')) ?></span></dt>
          <dd class="gs-studio-tools-cards">
          <details class="ld-governance-group">
          <summary><strong><?= e($ld('governance_group_dry_run')) ?></strong></summary>
            <div class="gs-studio-tool-card">
              <div class="gs-studio-tool-card-title-row">
                <span class="gs-studio-tool-card-title"><?= e($ld('runtime_dry_run_title')) ?></span>
              </div>
              <p class="gs-studio-tool-card-purpose"><?= e($ld('runtime_dry_run_note')) ?></p>

              <div style="display:flex;flex-wrap:wrap;gap:10px;margin:10px 0">
                <?php
                $dryRunMetrics = [
                    [$ld('runtime_dry_run_valid'), !empty($runtimeDryRunSummary['request_valid']) ? $ld('yes') : $ld('no')],
                    [$ld('runtime_dry_run_passes'), (string)(int)($runtimeDryRunSummary['passes'] ?? 0)],
                    [$ld('runtime_dry_run_warnings'), (string)(int)($runtimeDryRunSummary['warnings'] ?? 0)],
                    [$ld('runtime_dry_run_errors'), (string)(int)($runtimeDryRunSummary['errors'] ?? 0)],
                    [$ld('runtime_dry_run_matched_rules'), (string)(int)($runtimeDryRunSummary['matched_rules_count'] ?? 0)],
                    [$ld('runtime_dry_run_payload_fields'), (string)(int)($runtimeDryRunSummary['payload_fields_checked'] ?? 0)],
                    [$ld('runtime_dry_run_output_target'), (string)($runtimeDryRunSummary['output_target'] ?? '')],
                    [$ld('runtime_dry_run_dry_run'), !empty($runtimeDryRunSummary['dry_run']) ? $ld('yes') : $ld('no')],
                ];
                ?>
                <?php foreach ($dryRunMetrics as [$metricLabel, $metricValue]): ?>
                  <div style="padding:7px 12px;background:var(--bg-card,#fff);border:1px solid var(--border-subtle,#ccc);border-radius:4px">
                    <div style="font-size:0.78em;color:var(--text-muted,#666)"><?= e($metricLabel) ?></div>
                    <div style="font-weight:700"><code><?= e($metricValue) ?></code></div>
                  </div>
                <?php endforeach; ?>
              </div>

              <details style="margin:10px 0">
                <summary style="cursor:pointer;font-weight:600"><?= e($ld('runtime_dry_run_request')) ?></summary>
                <pre style="max-height:320px;overflow:auto;background:var(--bg-subtle,#f5f5f5);padding:8px;border-radius:4px"><code><?= e((string)json_encode($runtimeDryRunRequest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></code></pre>
              </details>

              <details class="ld-advanced ld-governance-details">
              <summary><?= e($ld('dry_run_advanced_summary')) ?></summary>
              <h4 style="margin:12px 0 4px"><?= e($ld('runtime_dry_run_validation_table')) ?></h4>
              <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;font-size:0.85em">
                  <thead>
                    <tr>
                      <th style="text-align:left;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)"><?= e($ld('runtime_dry_run_severity')) ?></th>
                      <th style="text-align:left;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)"><?= e($ld('runtime_dry_run_code')) ?></th>
                      <th style="text-align:left;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)"><?= e($ld('runtime_dry_run_message')) ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($runtimeDryRunDiagnostics as $dryRunDiagnostic): ?>
                      <?php
                      $dryRunSeverity = strtolower((string)($dryRunDiagnostic['severity'] ?? 'error'));
                      $dryRunColor = match ($dryRunSeverity) {
                          'pass' => 'var(--status-positive,#2e7d32)',
                          'info' => 'var(--status-info,#1565c0)',
                          'warning' => 'var(--status-warning,#f57c00)',
                          default => 'var(--status-error,#c62828)',
                      };
                      ?>
                      <tr>
                        <td style="padding:4px 8px;border-bottom:1px solid var(--border-subtle,#eee);color:<?= $dryRunColor ?>;font-weight:600"><?= e($dryRunSeverity) ?></td>
                        <td style="padding:4px 8px;border-bottom:1px solid var(--border-subtle,#eee)"><code><?= e((string)($dryRunDiagnostic['code'] ?? '')) ?></code></td>
                        <td style="padding:4px 8px;border-bottom:1px solid var(--border-subtle,#eee)"><?= e((string)($dryRunDiagnostic['message'] ?? '')) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <h4 style="margin:16px 0 4px"><?= e($ld('runtime_dry_run_matrix_title')) ?></h4>
              <p class="gs-studio-tool-card-purpose"><?= e($ld('runtime_dry_run_matrix_note')) ?></p>

              <div style="display:flex;flex-wrap:wrap;gap:10px;margin:10px 0">
                <?php
                $matrixMetrics = [
                    [$ld('runtime_dry_run_matrix_total'), (string)(int)($runtimeDryRunMatrixSummary['total'] ?? 0)],
                    [$ld('runtime_dry_run_matrix_passed'), (string)(int)($runtimeDryRunMatrixSummary['passed'] ?? 0)],
                    [$ld('runtime_dry_run_matrix_failed'), (string)(int)($runtimeDryRunMatrixSummary['failed'] ?? 0)],
                ];
                ?>
                <?php foreach ($matrixMetrics as [$metricLabel, $metricValue]): ?>
                  <div style="padding:7px 12px;background:var(--bg-card,#fff);border:1px solid var(--border-subtle,#ccc);border-radius:4px">
                    <div style="font-size:0.78em;color:var(--text-muted,#666)"><?= e($metricLabel) ?></div>
                    <div style="font-weight:700"><code><?= e($metricValue) ?></code></div>
                  </div>
                <?php endforeach; ?>
              </div>

              <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;font-size:0.85em">
                  <thead>
                    <tr>
                      <th style="text-align:left;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)"><?= e($ld('runtime_dry_run_matrix_scenario')) ?></th>
                      <th style="text-align:left;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)"><?= e($ld('runtime_dry_run_matrix_expected')) ?></th>
                      <th style="text-align:left;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)"><?= e($ld('runtime_dry_run_matrix_actual')) ?></th>
                      <th style="text-align:left;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)"><?= e($ld('runtime_dry_run_matrix_expectation')) ?></th>
                      <th style="text-align:left;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)"><?= e($ld('runtime_dry_run_matrix_warning_error')) ?></th>
                      <th style="text-align:left;padding:4px 8px;border-bottom:1px solid var(--border-subtle,#ccc)"><?= e($ld('runtime_dry_run_matrix_key_codes')) ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($runtimeDryRunMatrixRows as $matrixRow): ?>
                      <?php
                      $expectedResult = strtolower(trim((string)($matrixRow['expected_result'] ?? 'invalid')));
                      $actualResult = strtolower(trim((string)($matrixRow['actual_result'] ?? 'invalid')));
                      $expectedLabel = $expectedResult === 'valid' ? $ld('runtime_dry_run_matrix_valid') : $ld('runtime_dry_run_matrix_invalid');
                      $actualLabel = $actualResult === 'valid' ? $ld('runtime_dry_run_matrix_valid') : $ld('runtime_dry_run_matrix_invalid');
                      $expectationPassed = !empty($matrixRow['expectation_passed']);
                      $expectationColor = $expectationPassed ? 'var(--status-positive,#2e7d32)' : 'var(--status-error,#c62828)';
                      $keyCodes = isset($matrixRow['key_diagnostic_codes']) && is_array($matrixRow['key_diagnostic_codes'])
                          ? array_values(array_filter(array_map('strval', $matrixRow['key_diagnostic_codes']), static fn (string $v): bool => $v !== ''))
                          : [];
                      ?>
                      <tr>
                        <td style="padding:4px 8px;border-bottom:1px solid var(--border-subtle,#eee)"><?= e((string)($matrixRow['scenario_name'] ?? '')) ?></td>
                        <td style="padding:4px 8px;border-bottom:1px solid var(--border-subtle,#eee)"><code><?= e($expectedLabel) ?></code></td>
                        <td style="padding:4px 8px;border-bottom:1px solid var(--border-subtle,#eee)">
                          <code><?= e($actualLabel) ?></code>
                          <?php if (array_key_exists('expected_matched_rules_count', $matrixRow) && $matrixRow['expected_matched_rules_count'] !== null): ?>
                            <small style="color:var(--text-muted,#666)">
                              (<?= e($ld('runtime_dry_run_matched_rules')) ?>: <?= (int)($matrixRow['actual_matched_rules_count'] ?? 0) ?>)
                            </small>
                          <?php endif; ?>
                        </td>
                        <td style="padding:4px 8px;border-bottom:1px solid var(--border-subtle,#eee);color:<?= $expectationColor ?>;font-weight:600">
                          <?= e($expectationPassed ? $ld('runtime_dry_run_matrix_pass') : $ld('runtime_dry_run_matrix_fail')) ?>
                        </td>
                        <td style="padding:4px 8px;border-bottom:1px solid var(--border-subtle,#eee)">
                          <code><?= (int)($matrixRow['warning_count'] ?? 0) ?></code> / <code><?= (int)($matrixRow['error_count'] ?? 0) ?></code>
                        </td>
                        <td style="padding:4px 8px;border-bottom:1px solid var(--border-subtle,#eee)">
                          <code><?= e($keyCodes !== [] ? implode(', ', $keyCodes) : '—') ?></code>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              </details>
            </div>
            </details>
          </dd>
          </div>

          <?php
          $resourceDiagnostics = isset($model['label_resource_diagnostics']) && is_array($model['label_resource_diagnostics'])
              ? $model['label_resource_diagnostics']
              : [];
          $diagOk = (bool)($resourceDiagnostics['ok'] ?? false);
          $diagScanned = isset($resourceDiagnostics['scanned']) && is_array($resourceDiagnostics['scanned'])
              ? $resourceDiagnostics['scanned']
              : [];
          $diagCounts = isset($resourceDiagnostics['counts']) && is_array($resourceDiagnostics['counts'])
              ? $resourceDiagnostics['counts']
              : [];
          $diagLegacyFallback = (int)($resourceDiagnostics['legacy_fallback_count'] ?? 0);
          $diagByOwner = isset($resourceDiagnostics['by_owner']) && is_array($resourceDiagnostics['by_owner'])
              ? $resourceDiagnostics['by_owner']
              : [];
          ?>
          <div class="ld-workspace" data-workspace="governance" id="ld-workspace-governance-diagnostics">
          <!-- Phase 19: Resource Diagnostics group -->
          <dt class="ld-gov-hidden"><span><?= e($ld('governance_group_diagnostics')) ?></span></dt>
          <dd class="gs-studio-tools-cards">
          <details class="ld-governance-group">
          <summary><strong><?= e($ld('governance_group_diagnostics')) ?></strong></summary>
            <details class="ld-advanced ld-governance-details">
            <summary><?= e($ld('resource_diagnostics_title')) ?></summary>
            <div class="gs-studio-tool-card">
              <div class="gs-studio-tool-card-title-row">
                <span class="gs-studio-tool-card-title"><?= e($ld('resource_diagnostics_title')) ?></span>
              </div>
              <p class="gs-studio-tool-card-purpose"><?= e($ld('resource_diagnostics_note')) ?></p>
              <?php if (!$diagOk): ?>
                <p class="gs-studio-tool-card-purpose" style="color:var(--status-error,#c62828)">Diagnostics scan did not complete.</p>
              <?php elseif (empty($diagByOwner)): ?>
                <p class="gs-studio-tool-card-purpose"><?= e($ld('readiness_no_owners')) ?></p>
              <?php else: ?>
                <div style="display:flex;flex-wrap:wrap;gap:12px;margin:8px 0">
                  <div style="flex:0 0 auto;padding:8px 16px;background:var(--bg-card,#fff);border-radius:4px;border:1px solid var(--border-subtle,#ccc);text-align:center">
                    <div style="font-size:1.5em;font-weight:700"><?= (int)($diagScanned['owners'] ?? 0) ?></div>
                    <div style="font-size:0.8em"><?= e($ld('resource_diagnostics_owners')) ?></div>
                  </div>
                  <div style="flex:0 0 auto;padding:8px 16px;background:var(--bg-card,#fff);border-radius:4px;border:1px solid var(--border-subtle,#ccc);text-align:center">
                    <div style="font-size:1.5em;font-weight:700"><?= (int)($diagScanned['contexts'] ?? 0) ?></div>
                    <div style="font-size:0.8em"><?= e($ld('resource_diagnostics_contexts')) ?></div>
                  </div>
                  <div style="flex:0 0 auto;padding:8px 16px;background:var(--bg-card,#fff);border-radius:4px;border:1px solid var(--border-subtle,#ccc);text-align:center">
                    <div style="font-size:1.5em;font-weight:700"><?= (int)($diagScanned['templates'] ?? 0) ?></div>
                    <div style="font-size:0.8em"><?= e($ld('resource_diagnostics_templates')) ?></div>
                  </div>
                  <div style="flex:0 0 auto;padding:8px 16px;background:var(--bg-card,#fff);border-radius:4px;border:1px solid var(--border-subtle,#ccc);text-align:center">
                    <div style="font-size:1.5em;font-weight:700"><?= (int)($diagScanned['rules'] ?? 0) ?></div>
                    <div style="font-size:0.8em"><?= e($ld('resource_diagnostics_rules')) ?></div>
                  </div>
                  <div style="flex:0 0 auto;padding:8px 16px;background:var(--status-positive-subtle,#e8f5e9);border-radius:4px;border:1px solid var(--status-positive,#2e7d32);text-align:center">
                    <div style="font-size:1.5em;font-weight:700;color:var(--status-positive,#2e7d32)"><?= (int)($diagCounts['pass'] ?? 0) ?></div>
                    <div style="font-size:0.8em"><?= e($ld('resource_diagnostics_pass')) ?></div>
                  </div>
                  <div style="flex:0 0 auto;padding:8px 16px;background:var(--status-warning-subtle,#fff8e1);border-radius:4px;border:1px solid var(--status-warning,#f57c00);text-align:center">
                    <div style="font-size:1.5em;font-weight:700;color:var(--status-warning,#f57c00)"><?= (int)($diagCounts['warning'] ?? 0) ?></div>
                    <div style="font-size:0.8em"><?= e($ld('resource_diagnostics_warning')) ?></div>
                  </div>
                  <div style="flex:0 0 auto;padding:8px 16px;background:var(--status-error-subtle,#ffebee);border-radius:4px;border:1px solid var(--status-error,#c62828);text-align:center">
                    <div style="font-size:1.5em;font-weight:700;color:var(--status-error,#c62828)"><?= (int)($diagCounts['error'] ?? 0) ?></div>
                    <div style="font-size:0.8em"><?= e($ld('resource_diagnostics_error')) ?></div>
                  </div>
                  <?php if ($diagLegacyFallback > 0): ?>
                  <div style="flex:0 0 auto;padding:8px 16px;background:var(--status-warning-subtle,#fff8e1);border-radius:4px;border:1px solid var(--status-warning,#f57c00);text-align:center">
                    <div style="font-size:1.5em;font-weight:700;color:var(--status-warning,#f57c00)"><?= $diagLegacyFallback ?></div>
                    <div style="font-size:0.8em"><?= e($ld('resource_diagnostics_legacy_fallback')) ?></div>
                  </div>
                  <?php endif; ?>
                </div>
                <?php foreach ($diagByOwner as $ownerDiag): ?>
                  <?php
                  $odiagOwnerKey = (string)($ownerDiag['owner_key'] ?? '');
                  $odiagOwnerType = (string)($ownerDiag['owner_type'] ?? '');
                  $odiagRoot = (string)($ownerDiag['root_path'] ?? '');
                  $odiagResources = isset($ownerDiag['resources']) && is_array($ownerDiag['resources'])
                      ? $ownerDiag['resources'] : [];
                  ?>
                  <details style="margin:8px 0;border:1px solid var(--border-subtle,#ccc);border-radius:4px;padding:6px 12px">
                    <summary style="cursor:pointer;font-weight:600;padding:4px 0">
                      <?= e($odiagOwnerKey) ?>
                      <small style="font-weight:400;color:var(--text-muted,#666)">(<?= e($odiagOwnerType) ?>)</small>
                      <small style="font-weight:400;color:var(--text-muted,#666)"> — <?= count($odiagResources) ?> resource(s)</small>
                    </summary>
                    <p class="gs-studio-tool-card-purpose" style="margin-left:12px;font-size:0.85em">root: <code><?= e($odiagRoot) ?></code></p>
                    <?php foreach ($odiagResources as $resDiag): ?>
                      <?php
                      $resType = (string)($resDiag['resource_type'] ?? '');
                      $resKey = (string)($resDiag['resource_key'] ?? '');
                      $resPath = (string)($resDiag['path'] ?? '');
                      $resDiags = isset($resDiag['diagnostics']) && is_array($resDiag['diagnostics'])
                          ? array_values(array_filter($resDiag['diagnostics'], 'is_array')) : [];
                      $resMax = 'pass';
                      foreach ($resDiags as $rd) {
                          $s = (string)($rd['severity'] ?? 'pass');
                          if ($s === 'error') { $resMax = 'error'; break; }
                          if ($s === 'warning' && $resMax !== 'error') { $resMax = 'warning'; }
                          if ($s === 'info' && $resMax === 'pass') { $resMax = 'info'; }
                      }
                      $resLabel = $resKey !== '' ? $resKey : basename($resPath);
                      ?>
                      <div style="margin:8px 0 8px 16px;padding:6px 10px;border-left:3px solid <?= $resMax === 'error' ? 'var(--status-error,#c62828)' : ($resMax === 'warning' ? 'var(--status-warning,#f57c00)' : 'var(--status-positive,#2e7d32)') ?>;background:var(--bg-subtle,#f5f5f5);border-radius:2px">
                        <p style="margin:0;font-weight:600;font-size:0.9em">
                          <?= ucfirst(e($resType)) ?>: <?= e($resLabel) ?>
                          <small style="font-weight:400;color:var(--text-muted,#666)"> — <code><?= e($resPath) ?></code></small>
                        </p>
                        <?php if (!empty($resDiags)): ?>
                          <?php
                          $panelItems = [];
                          $panelTitle = '';
                          $panelClass = '';
                          $panelId = '';
                          $panelCollapsed = true;
                          $panelExtra = '';
                          foreach ($resDiags as $rd) {
                              $panelItems[] = [
                                  'severity' => (string)($rd['severity'] ?? 'pass'),
                                  'code' => (string)($rd['code'] ?? ''),
                                  'message' => (string)($rd['message'] ?? ''),
                              ];
                          }
                          ?>
                          <?php require __DIR__ . '/../partials/diagnostics-panel.php'; ?>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  </details>
                <?php endforeach; ?>
                <p class="gs-studio-tool-card-purpose" style="margin-top:8px;font-size:0.85em;color:var(--text-muted,#666)">
                  <?= e($ld('resource_diagnostics_disclaimer')) ?>
                </p>
              <?php endif; ?>
            </div>
            </details>
            </details>
          </dd>
          </div>

          <div class="ld-workspace" data-workspace="governance">
          <!-- Phase 19: Reference Implementation group -->
          <dt class="ld-gov-hidden"><span><?= e($ld('governance_group_reference')) ?></span></dt>
          <dd class="gs-studio-tools-cards">
          <details class="ld-governance-group">
          <summary><strong><?= e($ld('governance_group_reference')) ?></strong></summary>
            <details class="ld-advanced ld-governance-details">
            <summary><?= e($ld('ref_title')) ?><?= e($refImplName) ?></summary>
            <div class="gs-studio-tool-card" aria-disabled="true">
              <div class="gs-studio-tool-card-title-row">
                <span class="gs-studio-tool-card-title"><?= e($ld('ref_title')) ?><?= e($refImplName) ?></span>
              </div>
              <ul>
                <?php foreach ($sourceSurfaces as $sourceSurface): ?>
                  <li><?= e($sourceSurface) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>

            <div class="gs-studio-tool-card" aria-disabled="true">
              <div class="gs-studio-tool-card-title-row">
                <span class="gs-studio-tool-card-title"><?= e($ld('routes_title')) ?></span>
              </div>
              <p class="gs-studio-tool-card-purpose"><?= e($ld('print_route')) ?>: <code><?= e($printRoute) ?></code></p>
              <p class="gs-studio-tool-card-purpose"><?= e($ld('scan_route')) ?>: <code><?= e($scanRouteTemplate) ?></code></p>
            </div>

            <div class="gs-studio-tool-card" aria-disabled="true">
              <div class="gs-studio-tool-card-title-row">
                <span class="gs-studio-tool-card-title"><?= e($ld('defaults_title')) ?></span>
              </div>
              <p class="gs-studio-tool-card-purpose">
                <?= e($ld('copies')) ?>:
                <?= (int)($defaults['min_copies'] ?? 1) ?>-<?= (int)($defaults['max_copies'] ?? 48) ?>
                (default <?= (int)($defaults['copies'] ?? 8) ?>)
              </p>
              <p class="gs-studio-tool-card-purpose"><?= e($ld('include_flags')) ?>:</p>
              <ul>
                <?php foreach ($includeKeys as $includeKey): ?>
                  <li><?= e($ld($includeKey)) ?>: <?= e(!empty($defaults[$includeKey]) ? $ld('yes') : $ld('no')) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>

            <div class="gs-studio-tool-card" aria-disabled="true">
              <div class="gs-studio-tool-card-title-row">
                <span class="gs-studio-tool-card-title"><?= e($ld('field_order_title')) ?></span>
              </div>
              <ol>
                <?php foreach ($fieldOrder as $orderKey): ?>
                  <li><?= e($orderKey) ?></li>
                <?php endforeach; ?>
              </ol>
            </div>

            <div class="gs-studio-tool-card" aria-disabled="true">
              <div class="gs-studio-tool-card-title-row">
                <span class="gs-studio-tool-card-title"><?= e($ld('layout_title')) ?></span>
              </div>
              <p class="gs-studio-tool-card-purpose"><?= e($ld('layout_header')) ?></p>
              <p class="gs-studio-tool-card-purpose"><?= e($ld('layout_detail')) ?></p>
              <p class="gs-studio-tool-card-purpose"><?= e($ld('layout_footer')) ?></p>
            </div>
            </details>
            </details>
          </dd>
          </div>

      <div id="tab-changes" class="tab-panel">
        <!-- Navigation Strip (Read-Only) -->
        <div class="studio-tab-actions">
          <button class="u-style-1c13226961" type="button" id="btn-back-analyze">← <?= e($gs('tab.analyze')) ?></button>
          <button type="button" class="btn btn-primary-action" id="btn-to-apply"><?= e($gs('primary.changes_proceed_apply')) ?> →</button>
        </div>

        <section class="card" id="gs-live-change-intelligence">
          <h3><?= e($gs('change_summary_title')) ?></h3>
          <div id="gs-change-summary" class="stack">
            <div class="table-summary-badges">
              <span class="status-chip"><?= e($gs('artifact_count')) ?>: <strong id="gs-change-total">0</strong></span>
              <span class="status-chip danger"><?= e($gs('change_severity_breaking')) ?>: <strong id="gs-change-high">0</strong></span>
              <span class="status-chip warning"><?= e($gs('change_severity_additive')) ?>: <strong id="gs-change-medium">0</strong></span>
              <span class="status-chip success"><?= e($gs('change_severity_safe')) ?>: <strong id="gs-change-low">0</strong></span>
              <span class="status-chip" id="gs-no-changes-staged-chip"><?= e($gs('no_changes_staged_badge')) ?></span>
            </div>
            <div id="gs-breaking-changes-banner" class="note warning" style="display:none">
              <strong><?= e($gs('change_severity_breaking')) ?></strong>: <?= e($gs('breaking_changes_warning')) ?>
            </div>
            <div id="gs-change-risk-note" class="note warning u-style-c8be1ccba6"><?= e($gs('change_requires_ack')) ?></div>
            <div class="table-wrap">
              <table class="table">
                <thead>
                  <tr>
                    <th><?= e($gs('category')) ?></th>
                    <th><?= e($gs('change_type')) ?></th>
                    <th><?= e($gs('detail')) ?></th>
                    <th><?= e($gs('risk_level')) ?></th>
                    <th><?= e($gs('impact_severity')) ?></th>
                  </tr>
                </thead>
                <tbody id="gs-change-summary-body">
                  <tr>
                    <td colspan="5" class="muted"><?= e($gs('change_summary_empty')) ?></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <?php require __DIR__ . '/changes_migration_plan.php'; ?>

        <section class="card" id="gs-live-impact-analysis">
          <h3><?= e($gs('impact_analysis_title')) ?></h3>
          <div id="gs-impact-analysis" class="stack">
            <div id="gs-impact-warning" class="note warning u-style-c8be1ccba6"><?= e($gs('impact_warning_high')) ?></div>
            <div class="table-wrap">
              <table class="table">
                <thead>
                  <tr>
                    <th><?= e($gs('impact_change')) ?></th>
                    <th><?= e($gs('field_name')) ?></th>
                    <th><?= e($gs('impact_affected_components')) ?></th>
                    <th><?= e($gs('impact_severity')) ?></th>
                  </tr>
                </thead>
                <tbody id="gs-impact-analysis-body">
                  <tr>
                    <td colspan="4" class="muted"><?= e($gs('impact_empty')) ?></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <section class="card" id="gs-live-simulation-preview">
          <h3><?= e($gs('simulation_preview_title')) ?></h3>
          <div id="gs-simulation-preview" class="stack">
            <div id="gs-simulation-warning" class="note warning u-style-c8be1ccba6"><?= e($gs('simulation_warning')) ?></div>
            <div class="table-wrap">
              <table class="table">
                <thead>
                  <tr>
                    <th><?= e($gs('simulation_views_after')) ?></th>
                    <th><?= e($gs('simulation_broken_views')) ?></th>
                    <th><?= e($gs('simulation_removed_fields')) ?></th>
                    <th><?= e($gs('simulation_new_fields')) ?></th>
                  </tr>
                </thead>
                <tbody id="gs-simulation-preview-body">
                  <tr>
                    <td colspan="4" class="muted"><?= e($gs('simulation_empty')) ?></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <!-- Change Summary Section -->
        <?php if ($result !== null && isset($result['compile_plan'])): ?>
          <?php
            $compilePlan = isset($result['compile_plan']) && is_array($result['compile_plan']) ? $result['compile_plan'] : [];
            $compileArtifacts = is_array($compilePlan['artifacts'] ?? null) ? $compilePlan['artifacts'] : [];
            $artifactCount = count(array_filter($compileArtifacts, 'is_array'));
            $newCount = count(array_filter($compileArtifacts, function($a) {
              return is_array($a) && ($a['operation'] ?? '') === 'create';
            }));
            $modifiedCount = count(array_filter($compileArtifacts, function($a) {
              return is_array($a) && ($a['operation'] ?? '') === 'modify';
            }));
            $compileMeta = is_array($compilePlan['compile_meta'] ?? null) ? $compilePlan['compile_meta'] : [];
            $affectedRoutes = count(array_filter($compileArtifacts, function($a) {
              return is_array($a) && ($a['artifact_type'] ?? '') === 'routes';
            }));
            $affectedViews = count(array_filter($compileArtifacts, function($a) {
              return is_array($a) && ($a['artifact_type'] ?? '') === 'view';
            }));
          ?>
          <section class="card">
            <h3><?= e($gs('changes_summary_title')) ?></h3>
            <div class="u-style-5edcd40786">
              <div class="ui-block">
                <div class="u-style-8dd71a1183"><?= e($gs('changes_total_artifacts')) ?></div>
                <div class="u-style-f28c9e9b9e"><?= $artifactCount ?> <?= e($gs('artifacts')) ?></div>
              </div>
              <div class="ui-block">
                <div class="u-style-8dd71a1183"><?= e($gs('changes_new_files')) ?></div>
                <div class="u-style-f28c9e9b9e"><?= $newCount ?> <?= e($gs('created')) ?></div>
              </div>
              <div class="ui-block">
                <div class="u-style-8dd71a1183"><?= e($gs('changes_modified_files')) ?></div>
                <div class="u-style-f28c9e9b9e"><?= $modifiedCount ?> <?= e($gs('modified')) ?></div>
              </div>
              <div class="ui-block">
                <div class="u-style-8dd71a1183"><?= e($gs('changes_routes_affected')) ?></div>
                <div class="u-style-f28c9e9b9e"><?= $affectedRoutes ?> <?= e($gs('routes')) ?></div>
              </div>
              <div class="ui-block">
                <div class="u-style-8dd71a1183"><?= e($gs('changes_views_affected')) ?></div>
                <div class="u-style-f28c9e9b9e"><?= $affectedViews ?> <?= e($gs('views')) ?></div>
              </div>
            </div>
          </section>

          <!-- Artifacts List Section -->
          <section class="card">
            <h3><?= e($gs('changes_artifacts_title')) ?></h3>
            <div class="table-wrap">
              <table class="table">
                <thead>
                  <tr>
                    <th><?= e($gs('artifact_name')) ?></th>
                    <th><?= e($gs('artifact_type')) ?></th>
                    <th><?= e($gs('operation')) ?></th>
                    <th><?= e($gs('status')) ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach (array_slice($compileArtifacts, 0, 20) as $artifact): ?>
                    <?php if (!is_array($artifact)) { continue; } ?>
                    <?php
                      $artifactName = (string)($artifact['name'] ?? '');
                      $artifactType = (string)($artifact['artifact_type'] ?? '');
                      $artifactOp = (string)($artifact['operation'] ?? 'unknown');
                      $artifactStatus = (string)($artifact['status'] ?? 'pending');
                      $isCreate = $artifactOp === 'create';
                      $isModify = $artifactOp === 'modify';
                      $opClass = $isCreate ? 'success' : ($isModify ? 'warning' : '');
                    ?>
                    <tr>
                      <td><code><?= e($artifactName) ?></code></td>
                      <td><span class="u-style-5bdd418d93"><?= e($artifactType) ?></span></td>
                      <td><span class="status-chip <?= $opClass ?>"><?= e($gs('operation.' . $artifactOp)) ?></span></td>
                      <td><span class="status-chip <?= $artifactStatus === 'ready' ? 'success' : 'warning' ?>"><?= e($gs('status.' . $artifactStatus)) ?></span></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php if (count($compileArtifacts) > 20): ?>
              <div class="muted u-style-21d8fb5d75">+ <?= count($compileArtifacts) - 20 ?> more artifacts</div>
            <?php endif; ?>
          </section>

          <!-- Simple Diff Preview Section -->
          <section class="card">
            <h3><?= e($gs('changes_preview_title')) ?></h3>
            <p class="muted"><?= e($gs('changes_preview_subtitle')) ?></p>
            
            <?php
              $diffViewModel = isset($result['diff_view_model']) && is_array($result['diff_view_model']) ? $result['diff_view_model'] : [];
              $diffGroups = is_array($diffViewModel['groups'] ?? null) ? $diffViewModel['groups'] : [];
              $diffSummary = is_array($diffViewModel['summary'] ?? null) ? $diffViewModel['summary'] : [];
            ?>

            <?php if ($diffSummary !== []): ?>
              <div class="u-style-69f5a56d27">
                <strong><?= e($gs('changes_diff_summary')) ?>:</strong>
                <div class="u-style-4595f6f0c3">
                  <span><strong>+</strong> <?= (int)($diffSummary['additions'] ?? 0) ?> <?= e($gs('additions')) ?></span>
                  <span><strong>−</strong> <?= (int)($diffSummary['deletions'] ?? 0) ?> <?= e($gs('deletions')) ?></span>
                  <span><?= (int)($diffSummary['files_changed'] ?? 0) ?> <?= e($gs('files_changed')) ?></span>
                </div>
              </div>
            <?php endif; ?>

            <?php if ($diffGroups !== []): ?>
              <div class="u-style-0cf8f93b0e">
                <?php foreach (array_slice($diffGroups, 0, 3) as $group): ?>
                  <?php if (!is_array($group)) { continue; } ?>
                  <?php
                    $groupName = (string)($group['name'] ?? 'Unknown');
                    $groupOld = is_array($group['old_preview'] ?? null) ? $group['old_preview'] : [];
                    $groupNew = is_array($group['new_preview'] ?? null) ? $group['new_preview'] : [];
                    $groupOldText = is_string($groupOld) ? $groupOld : (isset($groupOld['content']) ? (string)$groupOld['content'] : 'No previous version');
                    $groupNewText = is_string($groupNew) ? $groupNew : (isset($groupNew['content']) ? (string)$groupNew['content'] : 'New file will be created');
                  ?>
                  <div class="u-style-edbf0190a7">
                    <div class="u-style-16111aaca0">
                      <?= e($groupName) ?>
                    </div>
                    <div class="u-style-bdd46a0369">
                      <div class="u-style-5d85c1bcc6">
                        <div class="u-style-898306b533">Before</div>
                        <pre class="u-style-2ab00ca722"><code><?= e(substr($groupOldText, 0, 500)) ?></code></pre>
                      </div>
                      <div class="u-style-6f445c2c7f">
                        <div class="u-style-898306b533">After</div>
                        <pre class="u-style-2ab00ca722"><code><?= e(substr($groupNewText, 0, 500)) ?></code></pre>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
              <?php if (count($diffGroups) > 3): ?>
                <div class="note info u-style-0cf8f93b0e">
                  + <?= count($diffGroups) - 3 ?> more file changes available
                </div>
              <?php endif; ?>
            <?php else: ?>
              <div class="note info">
                <?= e($gs('changes_no_preview')) ?>
              </div>
            <?php endif; ?>
          </section>
        <?php else: ?>
          <div class="note warning">
            <?= e($gs('changes_no_data')) ?>
          </div>
        <?php endif; ?>
      </div>

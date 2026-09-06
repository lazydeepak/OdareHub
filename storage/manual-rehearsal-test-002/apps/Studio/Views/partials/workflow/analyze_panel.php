      <div id="tab-analyze" class="tab-panel">
        <!-- Navigation Strip (Read-Only) -->
        <div class="studio-tab-actions">
          <button class="u-style-1c13226961" type="button" id="btn-back-edit">← <?= e($gs('tab.edit')) ?></button>
          <button type="button" class="btn btn-primary-action" id="btn-to-changes"><?= e($gs('primary.analyze_review_changes')) ?> →</button>
        </div>

        <!-- Analysis Summary Strip -->
        <?php
          $dataContractStatus = $studioDataContract === [] ? 'pending' : ($studioDataContract['confidence'] ?? 'unknown');
          $depGraphStatus = $studioDependencyGraph === [] ? 'pending' : 'complete';
          $impactStatus = 'pending';
          $hasStructureOk = $studioDataContract !== [] && $studioDependencyGraph !== [];
          $bindingIssues = array_filter($studioDataChecks, static function ($check): bool {
              if (!is_array($check)) {
                  return false;
              }
              $status = strtolower(trim((string)($check['status'] ?? '')));
              $key = strtolower(trim((string)($check['check'] ?? '')));
              return $status === 'fail' && strpos($key, 'binding') !== false;
          });
          $hasMissingBindings = $bindingIssues !== [];
            $analyzeRuntimeErrors = [];
            $analyzeRuntimeErrorGroups = [];
            if (isset($result['errors']) && is_array($result['errors'])) {
              $analyzeRuntimeErrorGroups[] = $result['errors'];
            }
            $analyzePublishGate = is_array($result['publish_gate'] ?? null) ? $result['publish_gate'] : [];
            if (isset($analyzePublishGate['errors']) && is_array($analyzePublishGate['errors'])) {
              $analyzeRuntimeErrorGroups[] = $analyzePublishGate['errors'];
            }
            $analyzePipelineGuard = is_array($result['pipeline_guard'] ?? null) ? $result['pipeline_guard'] : [];
            if (isset($analyzePipelineGuard['errors']) && is_array($analyzePipelineGuard['errors'])) {
              $analyzeRuntimeErrorGroups[] = $analyzePipelineGuard['errors'];
            }
            $analyzeApprovalValidation = is_array($result['approval_validation'] ?? null) ? $result['approval_validation'] : [];
            if (isset($analyzeApprovalValidation['errors']) && is_array($analyzeApprovalValidation['errors'])) {
              $analyzeRuntimeErrorGroups[] = $analyzeApprovalValidation['errors'];
            }
            $analyzeRiskEscalation = is_array($result['risk_escalation'] ?? null) ? $result['risk_escalation'] : [];
            if (isset($analyzeRiskEscalation['errors']) && is_array($analyzeRiskEscalation['errors'])) {
              $analyzeRuntimeErrorGroups[] = $analyzeRiskEscalation['errors'];
            }
            $analyzeMigrationGuard = is_array($result['migration_guard'] ?? null) ? $result['migration_guard'] : [];
            if (isset($analyzeMigrationGuard['errors']) && is_array($analyzeMigrationGuard['errors'])) {
              $analyzeRuntimeErrorGroups[] = $analyzeMigrationGuard['errors'];
            }
            $analyzeImpactGuard = is_array($result['impact_guard'] ?? null) ? $result['impact_guard'] : [];
            if (isset($analyzeImpactGuard['errors']) && is_array($analyzeImpactGuard['errors'])) {
              $analyzeRuntimeErrorGroups[] = $analyzeImpactGuard['errors'];
            }
            $analyzeSimulationGuard = is_array($result['simulation_guard'] ?? null) ? $result['simulation_guard'] : [];
            if (isset($analyzeSimulationGuard['errors']) && is_array($analyzeSimulationGuard['errors'])) {
              $analyzeRuntimeErrorGroups[] = $analyzeSimulationGuard['errors'];
            }
            foreach ($analyzeRuntimeErrorGroups as $runtimeErrorGroup) {
              foreach ($runtimeErrorGroup as $runtimeErrorKey) {
                  if (!is_string($runtimeErrorKey) || trim($runtimeErrorKey) === '') {
                      continue;
                  }
                  $runtimeErrorText = t($runtimeErrorKey);
                  if ($runtimeErrorText === $runtimeErrorKey) {
                      $runtimeFallbackKey = str_starts_with($runtimeErrorKey, 'ops.gui_studio.')
                          ? substr($runtimeErrorKey, strlen('ops.gui_studio.'))
                          : $runtimeErrorKey;
                      $runtimeFallbackText = $gs($runtimeFallbackKey);
                      $runtimeErrorText = $runtimeFallbackText !== $runtimeFallbackKey
                          ? $runtimeFallbackText
                          : $runtimeErrorKey;
                  }
                  $analyzeRuntimeErrors[] = $runtimeErrorText;
              }
          }
          $analyzeRuntimeErrors = array_values(array_unique($analyzeRuntimeErrors));
        ?>
        <div class="card analyze-top-summary u-style-2d1384f1ee">
          <strong><?= e($gs('analyze_summary_title')) ?></strong>
          <ul>
            <li class="<?= $hasStructureOk ? 'ok' : 'warn' ?>">
              <span><?= $hasStructureOk ? '✔' : '⚠' ?></span>
              <span><?= e($gs($hasStructureOk ? 'analyze_summary_structure_ok' : 'analyze_summary_structure_pending')) ?></span>
            </li>
            <li class="<?= $hasMissingBindings ? 'warn' : 'ok' ?>">
              <span><?= $hasMissingBindings ? '⚠' : '✔' ?></span>
              <span><?= e($gs($hasMissingBindings ? 'analyze_summary_missing_bindings' : 'analyze_summary_bindings_ok')) ?></span>
            </li>
            <?php if ($analyzeRuntimeErrors !== []): ?>
              <li class="warn">
                <span>⚠</span>
                <span><?= e($gs('analyze_summary_blockers')) ?>: <?= e(implode(' | ', array_slice($analyzeRuntimeErrors, 0, 3))) ?></span>
              </li>
            <?php endif; ?>
          </ul>
        </div>
        <details class="analyze-tech-details">
          <summary><?= e($gs('analyze_show_technical_details')) ?></summary>
        <div class="card u-style-fe0418ec26">
          <div class="u-style-3d8e8d426a">
            <div class="u-style-fe1c261ba1">
              <div class="u-style-8d245b55ee"><?= e($gs('data_contract_title')) ?></div>
              <span class="status-chip <?= $dataContractStatus === 'complete' || $dataContractStatus === 'full' ? 'success' : 'warning' ?>">
                <?= e($gs('data_contract_confidence.' . $dataContractStatus)) ?>
              </span>
            </div>
            <div class="u-style-fe1c261ba1">
              <div class="u-style-8d245b55ee"><?= e($gs('dependency_graph_title')) ?></div>
              <span class="status-chip <?= $depGraphStatus === 'complete' ? 'success' : 'warning' ?>">
                <?= $depGraphStatus === 'pending' ? e($gs('pending')) : e($gs('ready')) ?>
              </span>
            </div>
            <div class="u-style-fe1c261ba1">
              <div class="u-style-8d245b55ee"><?= e($gs('impact_analysis_title')) ?></div>
              <span class="status-chip warning">
                <?= e($gs('pending')) ?>
              </span>
            </div>
          </div>
        </div>

        <!-- Data Contract Analysis (Read-Only) -->
        <section class="card" id="gs-data-contract">
          <h3><?= e($gs('data_contract_title')) ?></h3>
          <p class="muted"><?= e($gs('data_contract_subtitle')) ?></p>

          <?php if ($studioDataContract === []): ?>
            <div class="note warning"><?= e($gs('data_contract_empty')) ?></div>
          <?php else: ?>
            <div class="u-style-16884469a8">
              <div class="u-style-6e300b8ebf">
                <div class="ui-block">
                  <div class="u-style-8dd71a1183"><?= e($gs('data_contract_confidence')) ?></div>
                  <div class="u-style-78e02d406e">
                    <?= e($gs('data_contract_confidence.' . (string)($studioDataContract['confidence'] ?? 'none'))) ?>
                  </div>
                </div>
                <div class="ui-block">
                  <div class="u-style-8dd71a1183"><?= e($gs('data_contract_fields')) ?></div>
                  <div class="u-style-78e02d406e">
                    <?= (int)($studioDataContract['summary']['fields'] ?? 0) ?> <?= e($gs('fields')) ?>
                  </div>
                </div>
                <div class="ui-block">
                  <div class="u-style-8dd71a1183"><?= e($gs('data_contract_bindings')) ?></div>
                  <div class="u-style-78e02d406e">
                    <?= (int)($studioDataContract['summary']['bindings'] ?? 0) ?> <?= e($gs('bindings')) ?>
                  </div>
                </div>
                <div class="ui-block">
                  <div class="u-style-8dd71a1183"><?= e($gs('data_contract_sources')) ?></div>
                  <div class="u-style-78e02d406e">
                    <?= (int)($studioDataContract['summary']['data_sources'] ?? 0) ?> <?= e($gs('sources')) ?>
                  </div>
                </div>
                <div class="ui-block">
                  <div class="u-style-8dd71a1183"><?= e($gs('data_contract_required_fields')) ?></div>
                  <div class="u-style-78e02d406e">
                    <?= (int)($studioDataContract['summary']['required_fields'] ?? 0) ?> <?= e($gs('required')) ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="table-wrap">
              <table class="table">
                <thead>
                  <tr>
                    <th><?= e($gs('data_contract_checks')) ?></th>
                    <th><?= e($gs('data_contract_check_status')) ?></th>
                    <th><?= e($gs('data_contract_check_detail')) ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($studioDataChecks as $check): ?>
                    <?php
                      $checkKey = trim((string)($check['key'] ?? ''));
                      $checkOk = !empty($check['ok']);
                      $checkDetail = is_array($check['detail'] ?? null)
                          ? (string)json_encode($check['detail'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                          : '';
                    ?>
                    <tr>
                      <td><?= e($gs('data_contract_check.' . $checkKey)) ?></td>
                      <td><span class="status-chip <?= $checkOk ? 'success' : 'warning' ?>"><?= e($gs('data_contract_status.' . ($checkOk ? 'pass' : 'fail'))) ?></span></td>
                      <td><code><?= e($checkDetail !== '' ? $checkDetail : '-') ?></code></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </section>

        <!-- Dependency Graph Analysis (Read-Only) -->
        <section class="card" id="gs-dependency-graph">
          <h3><?= e($gs('dependency_graph_title')) ?></h3>
          <p class="muted"><?= e($gs('dependency_graph_subtitle')) ?></p>

          <?php if ($studioDependencyGraph === []): ?>
            <div class="note warning"><?= e($gs('dependency_graph_none')) ?></div>
          <?php else: ?>
            <div class="u-style-16884469a8">
              <div class="u-style-6e300b8ebf">
                <div class="ui-block">
                  <div class="u-style-8dd71a1183"><?= e($gs('dependency_graph_nodes')) ?></div>
                  <div class="u-style-78e02d406e">
                    <?= (int)(count((array)($studioDependencyGraph['nodes'] ?? []))) ?> <?= e($gs('nodes')) ?>
                  </div>
                </div>
                <div class="ui-block">
                  <div class="u-style-8dd71a1183"><?= e($gs('dependency_graph_edges')) ?></div>
                  <div class="u-style-78e02d406e">
                    <?= (int)($studioDependencyGraph['summary']['edges'] ?? 0) ?> <?= e($gs('relations')) ?>
                  </div>
                </div>
                <div class="ui-block">
                  <div class="u-style-8dd71a1183"><?= e($gs('dependency_graph_depends_on')) ?></div>
                  <div class="u-style-78e02d406e">
                    <?= (int)($studioDependencyGraph['depends_on_edges'] ?? 0) ?> <?= e($gs('dependencies')) ?>
                  </div>
                </div>
                <div class="ui-block">
                  <div class="u-style-8dd71a1183"><?= e($gs('dependency_graph_affects')) ?></div>
                  <div class="u-style-78e02d406e">
                    <?= (int)($studioDependencyGraph['affects_edges'] ?? 0) ?> <?= e($gs('affected')) ?>
                  </div>
                </div>
              </div>
            </div>

            <?php $graphIssues = is_array($studioDependencyGraph['issues'] ?? null) ? $studioDependencyGraph['issues'] : []; ?>
            <?php if ($graphIssues !== []): ?>
              <div class="note warning u-style-16884469a8">
                <strong><?= e($gs('dependency_graph_issues')) ?>:</strong>
                <code><?= e((string)json_encode(array_values($graphIssues), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></code>
              </div>
            <?php endif; ?>

            <div class="table-wrap">
              <table class="table">
                <thead>
                  <tr>
                    <th><?= e($gs('dependency_graph_from')) ?></th>
                    <th><?= e($gs('dependency_graph_to')) ?></th>
                    <th><?= e($gs('dependency_graph_relation')) ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach (array_slice($studioGraphEdges, 0, 60) as $edge): ?>
                    <tr>
                      <td><code><?= e((string)($edge['from'] ?? '')) ?></code></td>
                      <td><code><?= e((string)($edge['to'] ?? '')) ?></code></td>
                      <td><span class="status-chip"><?= e((string)($edge['relation'] ?? '')) ?></span></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </section>

        <!-- Impact Analysis (Read-Only) -->
        <section class="card" id="gs-impact-analysis">
          <h3><?= e($gs('impact_analysis_title')) ?></h3>
          <p class="muted"><?= e($gs('impact_analysis_subtitle')) ?></p>
          <div class="note info u-style-e43d4d408d">
            <div class="u-style-ed0b001b25">
              <?= e($gs('impact_analysis_pending')) ?>
            </div>
          </div>
        </section>
        </details>
      </div>

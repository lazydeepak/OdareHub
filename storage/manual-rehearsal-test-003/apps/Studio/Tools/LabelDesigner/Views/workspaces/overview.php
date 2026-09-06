          <div class="ld-workspace" data-workspace="overview" id="ld-workspace-overview">
          <dt><span><?= e($ld('overview_title')) ?></span></dt>
          <dd class="gs-studio-tools-cards">
            <?php
            $ownerDisplayKey = static function (string $ownerKey): string {
                if ($ownerKey === '') {
                    return '';
                }
                return implode('/', array_map(static function (string $part): string {
                    return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $part)));
                }, explode('/', $ownerKey)));
            };
            $ownerReadinessLabel = static function (array $owner) use ($ld): string {
                if (!empty($owner['all_exist'])) {
                    return $ld('overview_owner_status_ready');
                }
                if (!empty($owner['none_exist'])) {
                    return $ld('overview_owner_status_not_started');
                }
                return $ld('overview_owner_status_partial');
            };
            $selectedOwnerIsLifecycle = !empty($selectedReadinessSummary['is_label_lifecycle_owner']);
            $recommendedOwnerKey = (string)($primaryRecommendedOwner['owner_key'] ?? '');
            $recommendedOwnerLabel = $ownerDisplayKey($recommendedOwnerKey);
            $selectedOwnerStatus = $selectedOwnerIsLifecycle
                ? ($selectedOwnerHasResources
                    ? ($overviewWorkspaceReady ? $ld('overview_owner_status_ready') : $ld('overview_owner_status_partial'))
                    : $ld('overview_owner_status_not_started'))
                : $ld('overview_parent_handoff_status');
            $previewReadinessLabel = $overviewContextKey !== '' && $overviewTemplateKey !== ''
                ? ($previewResult !== [] ? $ld('overview_preview_ready') : $ld('overview_preview_available'))
                : $ld('overview_preview_not_ready');
            $primaryNextHref = '/apps/studio/tools/label-designer' . $buildUrl(['workspace' => 'build']);
            $primaryNextLabel = $ld('next_action_continue_build');
            if ($selectedOwnerIsLifecycle) {
                if ($overviewContextKey === '') {
                    $primaryNextLabel = $ld('next_action_create_context');
                } elseif ($overviewTemplateKey === '') {
                    $primaryNextLabel = $ld('next_action_create_template');
                } elseif ($overviewRuleCount === 0) {
                    $primaryNextHref = '/apps/studio/tools/label-designer' . $buildUrl(['workspace' => 'rules']);
                    $primaryNextLabel = $ld('next_action_create_rule');
                } else {
                    $primaryNextHref = '/apps/studio/tools/label-designer' . $buildUrl(['workspace' => 'preview']);
                    $primaryNextLabel = $previewResult !== [] ? $ld('next_action_continue_preview') : $ld('next_action_render_preview');
                }
            }
            ?>

            <?php if (!$selectedOwnerIsLifecycle): ?>
            <div class="gs-studio-tool-card ld-parent-handoff" id="ld-overview-parent-handoff">
              <div class="gs-studio-tool-card-title-row">
                <span class="gs-studio-tool-card-title"><?= e($ld('overview_parent_handoff_title')) ?></span>
              </div>
              <div class="ld-owner-summary">
                <p><small><?= e($ld('overview_owner')) ?></small><br><strong><?= e($selectedOwnerDisplayName) ?></strong></p>
                <p><small><?= e($ld('overview_owner_type')) ?></small><br><strong><?= e($selectedOwnerType !== '' ? ucfirst($selectedOwnerType) : $ld('overview_unknown_type')) ?></strong></p>
                <p><small><?= e($ld('overview_lifecycle_status')) ?></small><br><strong><?= e($selectedOwnerStatus) ?></strong></p>
              </div>
              <p class="gs-studio-tool-card-purpose"><?= e($ld('overview_parent_handoff_reason')) ?></p>

              <?php if ($eligibleLifecycleOwners === []): ?>
                <p class="gs-studio-tool-card-purpose"><?= e($ld('overview_parent_no_children')) ?></p>
              <?php else: ?>
                <h4><?= e($ld('overview_parent_eligible_title')) ?></h4>
                <div class="ld-table-wrap">
                  <table class="ld-existing-table">
                    <thead>
                      <tr>
                        <th><?= e($ld('overview_owner_key')) ?></th>
                        <th><?= e($ld('overview_lifecycle_status')) ?></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($eligibleLifecycleOwners as $childOwner): ?>
                        <?php $childKey = (string)($childOwner['owner_key'] ?? ''); ?>
                        <tr>
                          <td><code><?= e($ownerDisplayKey($childKey)) ?></code></td>
                          <td><?= e($ownerReadinessLabel($childOwner)) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>

              <?php if ($recommendedOwnerKey !== ''): ?>
                <p class="gs-studio-tool-card-purpose">
                  <strong><?= e($ld('overview_parent_primary_target')) ?>:</strong>
                  <code><?= e($recommendedOwnerLabel) ?></code>
                </p>
                <div class="ld-next-actions">
                  <a class="ld-next-action" href="/apps/studio/tools/label-designer<?= $buildUrl(['workspace' => 'overview', 'owner' => $recommendedOwnerKey]) ?>"><?= e($ld('overview_parent_open_target')) ?></a>
                  <a class="ld-next-action ld-next-action-secondary" href="/apps/studio/tools/label-designer<?= $buildUrl(['workspace' => 'governance']) ?>"><?= e($ld('next_action_open_governance')) ?></a>
                </div>
              <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="gs-studio-tool-card ld-lifecycle-summary" id="ld-overview-lifecycle-summary">
              <div class="gs-studio-tool-card-title-row">
                <span class="gs-studio-tool-card-title"><?= e($ld('overview_lifecycle_summary_title')) ?></span>
              </div>
              <div class="ld-owner-summary">
                <p><small><?= e($ld('overview_owner')) ?></small><br><strong><?= e($selectedOwnerDisplayName) ?></strong></p>
                <p><small><?= e($ld('overview_lifecycle_status')) ?></small><br><strong><?= e($selectedOwnerStatus) ?></strong></p>
                <p><small><?= e($ld('overview_context_count')) ?></small><br><strong><?= e((string)$overviewResourceCounts['contexts']) ?></strong></p>
                <p><small><?= e($ld('overview_template_count')) ?></small><br><strong><?= e((string)$overviewResourceCounts['templates']) ?></strong></p>
                <p><small><?= e($ld('overview_rule_count')) ?></small><br><strong><?= e((string)$overviewResourceCounts['rules']) ?></strong></p>
                <p><small><?= e($ld('overview_preview_readiness')) ?></small><br><strong><?= e($previewReadinessLabel) ?></strong></p>
              </div>
              <p class="gs-studio-tool-card-purpose">
                <strong><?= e($ld('overview_primary_next_action')) ?>:</strong>
                <a href="<?= e($primaryNextHref) ?>"><?= e($primaryNextLabel) ?></a>
              </p>
              <div class="ld-next-actions">
                <a class="ld-next-action" href="/apps/studio/tools/label-designer<?= $buildUrl(['workspace' => 'build']) ?>"><?= e($ld('overview_open_build')) ?></a>
                <a class="ld-next-action" href="/apps/studio/tools/label-designer<?= $buildUrl(['workspace' => 'preview']) ?>"><?= e($ld('overview_open_preview')) ?></a>
                <a class="ld-next-action" href="/apps/studio/tools/label-designer<?= $buildUrl(['workspace' => 'rules']) ?>"><?= e($ld('overview_open_rules')) ?></a>
                <a class="ld-next-action ld-next-action-secondary" href="/apps/studio/tools/label-designer<?= $buildUrl(['workspace' => 'governance']) ?>"><?= e($ld('overview_open_governance')) ?></a>
              </div>
            </div>
            <?php endif; ?>
          </dd>
          </div>
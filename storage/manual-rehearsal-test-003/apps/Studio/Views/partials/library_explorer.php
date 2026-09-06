<aside class="studio-sidebar">
  <div class="library-header gs-library-header">
    <p class="muted gs-library-helper"><?= e($gs('library_role_helper')) ?></p>
    <div class="gs-library-control-group">
      <div class="library-filter-row" role="group" aria-label="<?= e($gs('library_filter_group_label')) ?>">
        <button type="button" class="library-filter-chip active" data-library-filter="views"><?= e($gs('library_filter_views')) ?></button>
        <button type="button" class="library-filter-chip" data-library-filter="modules"><?= e($gs('library_filter_modules')) ?></button>
        <button type="button" class="library-filter-chip" data-library-filter="apps"><?= e($gs('library_filter_apps')) ?></button>
        <button type="button" class="library-filter-chip" data-library-filter="routes"><?= e($gs('library_filter_routes')) ?></button>
      </div>
    </div>
    <div class="gs-library-control-group">
      <div class="library-filter-row" role="group" aria-label="<?= e($gs('studio_mode_label')) ?>">
        <button type="button" class="library-filter-chip" id="gs-switch-create"><?= e($gs('studio_mode_switch_create')) ?></button>
        <button type="button" class="library-filter-chip" id="gs-switch-upgrade"><?= e($gs('studio_mode_switch_upgrade')) ?></button>
      </div>
    </div>
    <input
      id="gs-library-search"
      class="gs-library-search"
      type="search"
      placeholder="<?= e($gs('library_search_placeholder')) ?>"
      autocomplete="off"
    >
    <div class="library-affordance-legend muted-soft" aria-label="<?= e($gs('library_affordance_legend_label')) ?>">
      <span class="library-affordance-legend-label"><?= e($gs('library_affordance_legend_label')) ?></span>
      <span class="library-affordance-legend-item">
        <span class="library-affordance-legend-dot library-affordance-legend-dot--loadable" aria-hidden="true">●</span>
        <strong><?= e($gs('library_affordance_legend_loadable')) ?></strong>
        <span class="muted-soft">(<?= e($gs('library_affordance_legend_loadable_kinds')) ?>)</span>
      </span>
      <span class="library-affordance-legend-item">
        <span class="library-affordance-legend-dot library-affordance-legend-dot--inspect" aria-hidden="true">○</span>
        <strong><?= e($gs('library_affordance_legend_inspect_only')) ?></strong>
        <span class="muted-soft">(<?= e($gs('library_affordance_legend_inspect_only_kinds')) ?>)</span>
      </span>
    </div>
  </div>

  <section id="gs-library-search-results" class="library-search-results is-hidden">
    <h4 class="gs-title"><?= e($gs('library_search_results_title')) ?></h4>
    <ul id="gs-library-search-results-list" class="library-recent-list">
      <li class="library-recent-empty"><?= e($gs('library_search_results_empty')) ?></li>
    </ul>
  </section>

  <?php
    // Build a flat list of all app-level nodes for search/filter (one entry per real app/plugin).
    // These are rendered hidden and discovered by JS via [data-library-item][data-library-kind=apps].
  ?>
  <ul id="gs-library-apps-index" aria-hidden="true" style="display:none">
    <?php foreach ($globalLibraryTree as $sourceNode): ?>
      <?php
        $sourceId = strtolower(trim((string)($sourceNode['id'] ?? '')));
        // Exclude route_authority pseudo-source
        if ($sourceId === 'source:route_authority') continue;
      ?>
      <?php foreach ((array)($sourceNode['children'] ?? []) as $appNode): ?>
        <?php if (!is_array($appNode)) continue; ?>
        <?php
          $appNodeId    = (string)($appNode['id'] ?? '');
          $appNodeLabel = (string)($appNode['label'] ?? $appNode['id'] ?? 'App');
          $appNodeType  = strtolower((string)($appNode['type'] ?? ''));
          $appSearchText = strtolower($appNodeLabel) . ' ' . strtolower($appNodeId) . ' app';
          $appMeta = is_array($appNode['meta'] ?? null) ? $appNode['meta'] : [];
          $appStatus = strtolower((string)($appMeta['status'] ?? 'installed'));
        ?>
        <li class="gs-library-item"
            data-library-item
            data-library-kind="apps"
            data-artifact-kind="app"
            data-node-id="<?= e($appNodeId) ?>"
            data-library-search="<?= e($appSearchText) ?>"
            data-library-app-status="<?= e($appStatus) ?>"
            style="display:none">
          <div class="library-item-row">
            <span><?= e($appNodeLabel) ?></span>
            <button type="button" class="btn gs-load-btn" data-load-library-node data-node-id="<?= e($appNodeId) ?>">
              <?= e($gs('library_load_app')) ?>
            </button>
          </div>
        </li>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </ul>

  <section id="gs-content-outline" class="library-content-outline is-hidden" aria-live="polite">
    <div class="library-outline-header">
      <h4 class="gs-title"><?= e($gs('content_outline_title')) ?></h4>
      <button type="button" class="btn" id="gs-content-outline-back"><?= e($gs('content_outline_back')) ?></button>
    </div>
    <div id="gs-content-outline-summary" class="library-outline-summary"><?= e($gs('content_outline_empty')) ?></div>
    <div id="gs-content-outline-body"></div>
  </section>

  <section id="gs-create-flow" class="library-content-outline" aria-live="polite">
    <div class="library-outline-header">
      <h4 class="gs-title"><?= e($gs('create_flow_title')) ?></h4>
    </div>
    <div id="gs-create-flow-summary" class="library-outline-summary"><?= e($gs('create_flow_empty')) ?></div>
    <div id="gs-create-flow-body"></div>
  </section>

  <section class="library-quick-load" id="gs-library-quick-load">
    <h4 class="gs-title"><?= e($gs('library_quick_load_title')) ?></h4>
    <details class="library-section" open>
      <summary><?= e($gs('library_quick_recent_views')) ?></summary>
      <ul id="gs-library-recent-list" class="library-recent-list">
        <li class="library-recent-empty"><?= e($gs('library_filter_recent')) ?></li>
      </ul>
    </details>
    <details class="library-section" open>
      <summary><?= e($gs('library_quick_most_used_views')) ?></summary>
      <ul id="gs-library-most-used-list" class="library-recent-list">
        <li class="library-recent-empty"><?= e($gs('library_filter_views')) ?></li>
      </ul>
    </details>
    <details class="library-section" open>
      <summary><?= e($gs('library_quick_last_edited_views')) ?></summary>
      <ul id="gs-library-last-edited-list" class="library-recent-list">
        <li class="library-recent-empty"><?= e($gs('library_filter_views')) ?></li>
      </ul>
    </details>
  </section>

  <details class="library-structure-secondary">
    <summary><?= e($gs('library_system_registry_title')) ?></summary>
    <div class="note gs-note-top"><?= e($gs('library_all_views_title')) ?> / <?= e($gs('library_all_modules_title')) ?></div>
    <ul class="library-tree gs-library-list">
    <?php foreach ($globalLibraryTree as $appNode): ?>
      <?php if (!is_array($appNode)) continue; ?>
      <li class="gs-library-app" data-library-app>
        <details class="library-section" data-library-app-section>
          <summary><?= e($appNode['label'] ?? $appNode['id'] ?? 'App') ?></summary>
        <?php if (is_array($appNode['children'] ?? null)): ?>
          <ul class="gs-library-sublist">
            <?php foreach ($appNode['children'] as $moduleNode): ?>
              <?php if (!is_array($moduleNode)) continue; ?>
              <li class="gs-library-module" data-library-module>
                <details class="library-section" data-library-module-section>
                  <summary><?= e($moduleNode['label'] ?? $moduleNode['id'] ?? 'Module') ?></summary>
                <?php if (is_array($moduleNode['children'] ?? null)): ?>
                  <?php
                    $moduleChildren = array_values(array_filter((array)($moduleNode['children'] ?? []), 'is_array'));
                    $standardItems = [];
                    $advancedRegistryItems = [];
                    foreach ($moduleChildren as $moduleChild) {
                        $childType = strtolower(trim((string)($moduleChild['type'] ?? '')));
                        $childId = strtolower(trim((string)($moduleChild['id'] ?? '')));
                        $isAdvancedRegistryNode = strpos($childType, 'route_authority') !== false
                            || strpos($childType, 'linked_routes') !== false
                            || strpos($childId, 'route_authority') !== false
                            || strpos($childId, 'linked_routes') !== false;
                        if ($isAdvancedRegistryNode) {
                            $advancedRegistryItems[] = $moduleChild;
                        } else {
                            $standardItems[] = $moduleChild;
                        }
                    }
                  ?>
                  <ul class="gs-library-sublist">
                    <?php foreach ($standardItems as $itemNode): ?>
                      <?php if (!is_array($itemNode)) continue; ?>
                      <?php
                        $itemChildren = array_values(array_filter((array)($itemNode['children'] ?? []), 'is_array'));
                        $renderNodes = $itemChildren !== [] ? $itemChildren : [$itemNode];
                      ?>
                      <?php foreach ($renderNodes as $renderNode): ?>
                        <?php if (!is_array($renderNode)) continue; ?>
                        <?php
                          $itemTypeRaw = strtolower(trim((string)($renderNode['type'] ?? '')));
                          $itemNodeIdRaw = strtolower(trim((string)($renderNode['id'] ?? '')));
                          $artifactKind = 'view';
                          if ($itemTypeRaw === 'dashboard' || str_starts_with($itemNodeIdRaw, 'dashboard:')) {
                            $artifactKind = 'dashboard';
                          } elseif (str_starts_with($itemNodeIdRaw, 'nav:') || strpos($itemTypeRaw, 'nav') !== false) {
                            $artifactKind = 'nav';
                          } elseif (str_starts_with($itemNodeIdRaw, 'route:') || str_starts_with($itemNodeIdRaw, 'route_file:') || strpos($itemTypeRaw, 'route') !== false) {
                            $artifactKind = 'route';
                          } elseif (str_starts_with($itemNodeIdRaw, 'module:') || strpos($itemTypeRaw, 'module') !== false) {
                            $artifactKind = 'module';
                          } elseif (str_starts_with($itemNodeIdRaw, 'app:') || $itemTypeRaw === 'app') {
                            $artifactKind = 'app';
                          }
                          $libraryKind = 'views';
                          if ($artifactKind === 'route' || $artifactKind === 'nav' || strpos($itemNodeIdRaw, '/apps/') !== false) {
                              $libraryKind = 'routes';
                          } elseif ($artifactKind === 'module') {
                              $libraryKind = 'modules';
                          }
                          $isLoadableArtifact = in_array($artifactKind, ['app', 'module', 'view', 'dashboard'], true);
                          $artifactKind    = \Apps\Studio\Services\GuiStudioService::resolveArtifactKindFromNode($renderNode);
                          $itemTypeRaw     = strtolower(trim((string)($renderNode['type'] ?? '')));
                          $itemNodeIdRaw   = strtolower(trim((string)($renderNode['id'] ?? '')));
                          $libraryKind     = \Apps\Studio\Services\GuiStudioService::resolveLibraryKind($artifactKind, $itemNodeIdRaw);
                          $isLoadableArtifact = \Apps\Studio\Services\GuiStudioService::isArtifactKindLoadable($artifactKind);
                          $searchHints = '';
                          if (strpos($itemTypeRaw, 'view') !== false || strpos($itemTypeRaw, 'dashboard') !== false) {
                            $isFormLike = strpos($itemNodeIdRaw, 'form') !== false
                              || strpos($itemNodeIdRaw, 'create') !== false
                              || strpos($itemNodeIdRaw, 'edit') !== false
                              || strpos($itemNodeIdRaw, 'new') !== false
                              || strpos($itemNodeIdRaw, 'entry') !== false;
                            $searchHints = $isFormLike ? ' form fields input' : ' table rows columns';
                          }
                          $searchText = strtolower(trim((string)($renderNode['label'] ?? ''))) . ' ' . $itemNodeIdRaw . $searchHints;
                          $itemUpdatedAt = trim((string)($renderNode['meta']['last_updated'] ?? ($renderNode['meta']['updated_at'] ?? '')));
                        ?>
                        <li class="gs-library-item" data-library-item data-library-kind="<?= e($libraryKind) ?>" data-artifact-kind="<?= e($artifactKind) ?>" data-node-id="<?= e((string)($renderNode['id'] ?? '')) ?>" data-library-search="<?= e($searchText) ?>" data-library-updated="<?= e($itemUpdatedAt) ?>">
                          <div class="library-item-row">
                            <span><?= e($renderNode['label'] ?? $renderNode['id'] ?? 'Item') ?></span>
                            <?php if ($isLoadableArtifact): ?>
                              <button class="btn gs-load-btn" data-load-library-node data-node-id="<?= e($renderNode['id'] ?? '') ?>">
                                <?= e($gs('global_library_load_into_studio')) ?>
                              </button>
                            <?php else: ?>
                              <button class="btn" data-inspect-library-node data-node-id="<?= e($renderNode['id'] ?? '') ?>">
                                <?= e($gs('inspect')) ?>
                              </button>
                            <?php endif; ?>
                          </div>
                        </li>
                      <?php endforeach; ?>
                    <?php endforeach; ?>
                  </ul>
                  <?php if ($advancedRegistryItems !== []): ?>
                    <details class="library-advanced" data-library-advanced-registry>
                      <summary><?= e($gs('library_advanced_registry_title')) ?></summary>
                      <ul class="gs-library-sublist">
                        <?php foreach ($advancedRegistryItems as $itemNode): ?>
                          <?php if (!is_array($itemNode)) continue; ?>
                          <?php
                            $itemChildren = array_values(array_filter((array)($itemNode['children'] ?? []), 'is_array'));
                            $renderNodes = $itemChildren !== [] ? $itemChildren : [$itemNode];
                          ?>
                          <?php foreach ($renderNodes as $renderNode): ?>
                            <?php if (!is_array($renderNode)) continue; ?>
                            <?php
                              $artifactKind    = \Apps\Studio\Services\GuiStudioService::resolveArtifactKindFromNode($renderNode);
                              $itemNodeIdRaw   = strtolower(trim((string)($renderNode['id'] ?? '')));
                              $itemTypeRaw     = strtolower(trim((string)($renderNode['type'] ?? '')));
                              $libraryKind     = \Apps\Studio\Services\GuiStudioService::resolveLibraryKind($artifactKind, $itemNodeIdRaw);
                              $isLoadableArtifact = \Apps\Studio\Services\GuiStudioService::isArtifactKindLoadable($artifactKind);
                              $searchHints = '';
                              if (strpos($itemTypeRaw, 'view') !== false || strpos($itemTypeRaw, 'dashboard') !== false) {
                                $isFormLike = strpos($itemNodeIdRaw, 'form') !== false
                                  || strpos($itemNodeIdRaw, 'create') !== false
                                  || strpos($itemNodeIdRaw, 'edit') !== false
                                  || strpos($itemNodeIdRaw, 'new') !== false
                                  || strpos($itemNodeIdRaw, 'entry') !== false;
                                $searchHints = $isFormLike ? ' form fields input' : ' table rows columns';
                              }
                              $searchText = strtolower(trim((string)($renderNode['label'] ?? ''))) . ' ' . $itemNodeIdRaw . $searchHints;
                              $itemUpdatedAt = trim((string)($renderNode['meta']['last_updated'] ?? ($renderNode['meta']['updated_at'] ?? '')));
                            ?>
                            <li class="gs-library-item" data-library-item data-library-kind="<?= e($libraryKind) ?>" data-artifact-kind="<?= e($artifactKind) ?>" data-node-id="<?= e((string)($renderNode['id'] ?? '')) ?>" data-library-search="<?= e($searchText) ?>" data-library-updated="<?= e($itemUpdatedAt) ?>">
                              <div class="library-item-row">
                                <span><?= e($renderNode['label'] ?? $renderNode['id'] ?? 'Item') ?></span>
                                <?php if ($isLoadableArtifact): ?>
                                  <button class="btn gs-load-btn" data-load-library-node data-node-id="<?= e($renderNode['id'] ?? '') ?>">
                                    <?= e($gs('global_library_load_into_studio')) ?>
                                  </button>
                                <?php else: ?>
                                  <button class="btn" data-inspect-library-node data-node-id="<?= e($renderNode['id'] ?? '') ?>">
                                    <?= e($gs('inspect')) ?>
                                  </button>
                                <?php endif; ?>
                              </div>
                            </li>
                          <?php endforeach; ?>
                        <?php endforeach; ?>
                      </ul>
                    </details>
                  <?php endif; ?>
                <?php endif; ?>
                </details>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        </details>
      </li>
    <?php endforeach; ?>
    </ul>
  </details>
</aside>

<div class="library-detail-backdrop" data-library-detail-close hidden></div>
<section class="library-detail-sheet" id="gs-library-detail-sheet" aria-modal="true" role="dialog" aria-labelledby="gs-library-detail-title" hidden>
  <h3 id="gs-library-detail-title"><?= e($gs('library_detail_title')) ?></h3>
  <div class="muted" data-gs-library-detail-kind id="gs-library-detail-kind"><?= e($gs('library_filter_views')) ?></div>
  <p data-gs-library-detail-label id="gs-library-detail-label" class="u-style-c9ef2a58eb"></p>
  <code data-gs-library-detail-node id="gs-library-detail-node"></code>
  <dl class="library-detail-meta" data-gs-library-detail-meta>
    <div>
      <dt><?= e($gs('library_detail_owner')) ?></dt>
      <dd data-gs-library-detail-owner><?= e($gs('library_detail_owner_unknown')) ?></dd>
    </div>
    <div>
      <dt><?= e($gs('library_detail_owner_path')) ?></dt>
      <dd data-gs-library-detail-owner-path><?= e($gs('not_loaded')) ?></dd>
    </div>
    <div>
      <dt><?= e($gs('library_detail_resource_type')) ?></dt>
      <dd data-gs-library-detail-resource-type><?= e($gs('pending')) ?></dd>
    </div>
  </dl>
  <section class="library-detail-tools" data-gs-library-detail-tools>
    <h4><?= e($gs('library_detail_tools_title')) ?></h4>
    <p class="muted" data-gs-library-tools-status><?= e($gs('library_detail_tools_loading')) ?></p>
    <ul class="library-detail-tools-list" data-gs-library-tools-list></ul>
  </section>
  <p class="muted" id="gs-library-detail-support-note" hidden></p>
  <div class="library-detail-actions">
    <button type="button" class="btn btn-primary-action" id="gs-library-detail-load"><?= e($gs('global_library_load_into_studio')) ?></button>
    <button type="button" class="btn" id="gs-library-detail-inspect"><?= e($gs('inspect')) ?></button>
    <button type="button" class="btn" data-library-detail-close><?= e($gs('library_detail_close')) ?></button>
  </div>
</section>

      <div id="tab-edit" class="tab-panel active">
        <section class="card">
          <?php require __DIR__ . '/editor_intro.php'; ?>
          <?php require __DIR__ . '/../../../Views/partials/loaded_resource_workbench.php'; ?>
          <?php require __DIR__ . '/../../../Views/partials/editor_workbench_shell.php'; ?>
          <section id="gs-surface-exposure-section" class="note gs-surface-exposure gs-surface-exposure-control" hidden>
            <h4><?= e($gs('surface_exposure_title')) ?></h4>
            <p class="muted"><?= e($gs('surface_exposure_subtitle')) ?></p>
            <div id="gs-surface-exposure-summary" class="status-chip gs-surface-exposure-summary"></div>
            <div class="table-wrap">
              <table class="table">
                <thead>
                  <tr>
                    <th><?= e($gs('surface_exposure_aspect')) ?></th>
                    <th><?= e($gs('surface_exposure_value')) ?></th>
                    <th><?= e($gs('surface_exposure_state')) ?></th>
                    <th></th>
                  </tr>
                </thead>
                <tbody id="gs-surface-exposure-body"></tbody>
              </table>
            </div>
            <ul id="gs-surface-exposure-diagnostics" class="gs-surface-exposure-diagnostics"></ul>
            <!-- Phase 1E: Route Link Proposal Panel -->
            <div id="gs-route-link-panel" class="gs-route-link-panel" hidden>
              <h5 id="gs-route-link-panel-title"><?= e($gs('route_link_title')) ?></h5>
              <p class="muted" id="gs-route-link-panel-subtitle"><?= e($gs('route_link_subtitle')) ?></p>
              <div id="gs-route-link-errors" class="gs-route-link-errors" hidden></div>
              <div class="gs-route-link-form-row">
                <label class="form-label" for="gs-route-link-input"><?= e($gs('route_link_label')) ?></label>
                <input type="text" id="gs-route-link-input" class="form-input" placeholder="<?= e($gs('route_link_placeholder')) ?>">
              </div>
              <div id="gs-route-link-upgrade-ack-row" class="gs-route-link-form-row" hidden>
                <label class="gs-route-link-ack-label">
                  <input type="checkbox" id="gs-route-link-upgrade-ack">
                  <?= e($gs('route_link_upgrade_acknowledge')) ?>
                </label>
              </div>
              <div class="gs-route-link-actions">
                <button type="button" id="gs-route-link-analyze-btn" class="btn btn-secondary btn-sm"><?= e($gs('route_link_analyze_btn')) ?></button>
                <button type="button" id="gs-route-link-cancel-btn" class="btn btn-ghost btn-sm"><?= e($gs('route_link_cancel_btn')) ?></button>
              </div>
              <!-- Changes section (shown after successful analyze) -->
              <div id="gs-route-link-changes-section" hidden>
                <h6><?= e($gs('route_link_changes_title')) ?></h6>
                <div class="table-wrap">
                  <table class="table">
                    <thead>
                      <tr>
                        <th><?= e($gs('route_link_changes_file')) ?></th>
                        <th><?= e($gs('route_link_changes_field')) ?></th>
                        <th><?= e($gs('route_link_changes_before')) ?></th>
                        <th><?= e($gs('route_link_changes_after')) ?></th>
                      </tr>
                    </thead>
                    <tbody id="gs-route-link-changes-body"></tbody>
                  </table>
                </div>
                <div id="gs-route-link-gate-status" class="status-chip gs-route-link-gate-chip"></div>
                <div class="gs-route-link-actions">
                  <button type="button" id="gs-route-link-apply-btn" class="btn btn-primary btn-sm" disabled><?= e($gs('route_link_apply_btn')) ?></button>
                </div>
              </div>
            </div>
            <!-- Phase 1F: Nav Link Proposal Panel -->
            <div id="gs-nav-link-panel" class="gs-nav-link-panel" hidden>
              <h5 id="gs-nav-link-panel-title"><?= e($gs('nav_link_title')) ?></h5>
              <p class="muted" id="gs-nav-link-panel-subtitle"><?= e($gs('nav_link_subtitle')) ?></p>
              <div id="gs-nav-link-errors" class="gs-route-link-errors" hidden></div>
              <div class="gs-route-link-form-row">
                <label class="form-label" for="gs-nav-link-url-input"><?= e($gs('nav_link_url_label')) ?></label>
                <input type="text" id="gs-nav-link-url-input" class="form-input" placeholder="<?= e($gs('nav_link_placeholder')) ?>">
              </div>
              <div class="gs-route-link-form-row">
                <label class="form-label" for="gs-nav-link-label-input"><?= e($gs('nav_link_label_label')) ?></label>
                <input type="text" id="gs-nav-link-label-input" class="form-input" placeholder="<?= e($gs('nav_link_label_placeholder')) ?>">
              </div>
              <div id="gs-nav-link-upgrade-ack-row" class="gs-route-link-form-row" hidden>
                <label class="gs-route-link-ack-label">
                  <input type="checkbox" id="gs-nav-link-upgrade-ack">
                  <?= e($gs('nav_link_upgrade_acknowledge')) ?>
                </label>
              </div>
              <div class="gs-route-link-actions">
                <button type="button" id="gs-nav-link-analyze-btn" class="btn btn-secondary btn-sm"><?= e($gs('nav_link_analyze_btn')) ?></button>
                <button type="button" id="gs-nav-link-cancel-btn" class="btn btn-ghost btn-sm"><?= e($gs('nav_link_cancel_btn')) ?></button>
              </div>
              <div id="gs-nav-link-changes-section" hidden>
                <h6><?= e($gs('nav_link_changes_title')) ?></h6>
                <div class="table-wrap">
                  <table class="table">
                    <thead>
                      <tr>
                        <th><?= e($gs('nav_link_changes_file')) ?></th>
                        <th><?= e($gs('nav_link_changes_field')) ?></th>
                        <th><?= e($gs('nav_link_changes_before')) ?></th>
                        <th><?= e($gs('nav_link_changes_after')) ?></th>
                      </tr>
                    </thead>
                    <tbody id="gs-nav-link-changes-body"></tbody>
                  </table>
                </div>
                <div id="gs-nav-link-gate-status" class="status-chip gs-route-link-gate-chip"></div>
                <div class="gs-route-link-actions">
                  <button type="button" id="gs-nav-link-apply-btn" class="btn btn-primary btn-sm" disabled><?= e($gs('nav_link_apply_btn')) ?></button>
                </div>
              </div>
            </div>
          </section>

          <form method="post" action="/apps/studio/compile-plan" class="stack" id="gs-structured-editor-form">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" id="gs_studio_mode" name="studio_mode" value="<?= e($studioMode === 'edit_existing' ? 'edit_existing' : 'create_new') ?>">
            <input type="hidden" name="se_editor_enabled" value="1">
            <input type="hidden" name="se_fields_state" id="gs_se_fields_state" value="[]">
            <input type="hidden" name="se_view_columns_state" id="gs_se_view_columns_state" value="[]">
            <input type="hidden" name="se_layout_state" id="gs_se_layout_state" value="[]">
            <input type="hidden" name="se_layout_relations_state" id="gs_se_layout_relations_state" value="[]">
            <input type="hidden" name="se_preview_mode" id="gs_se_preview_mode" value="0">
            <input type="hidden" name="se_previous_bundle" id="gs_se_previous_bundle" value="<?= e($getInput('se_previous_bundle', $inputs)) ?>">
            <input type="hidden" name="se_current_bundle" id="gs_se_current_bundle" value="">

            <details class="studio-advanced-debug" data-tier="advanced">
              <summary><?= e($gs('advanced_debug_title')) ?></summary>
              <p class="muted u-style-c9ef2a58eb"><?= e($gs('advanced_debug_help')) ?></p>
              <label class="form-label" for="gs_app_manifest"><?= e($gs('app_manifest')) ?></label>
              <textarea id="gs_app_manifest" name="app_manifest" class="form-input code-input" rows="8"><?= e($getInput('app_manifest', $inputs)) ?></textarea>

              <label class="form-label" for="gs_module_manifest"><?= e($gs('module_manifest')) ?></label>
              <textarea id="gs_module_manifest" name="module_manifest" class="form-input code-input" rows="6" readonly><?= e($getInput('module_manifest', $inputs)) ?></textarea>

              <label class="form-label" for="gs_view_definition"><?= e($gs('view_manifest')) ?></label>
              <textarea id="gs_view_definition" name="view_definition" class="form-input code-input" rows="6" readonly><?= e($getInput('view_definition', $inputs)) ?></textarea>

              <label class="form-label" for="gs_navigation_definition"><?= e($gs('navigation_manifest')) ?></label>
              <textarea id="gs_navigation_definition" name="navigation_definition" class="form-input code-input" rows="6" readonly><?= e($getInput('navigation_definition', $inputs)) ?></textarea>
            </details>

            <h4 class="gs-app-settings-control"><?= e($gs('app_settings')) ?></h4>
            <label class="form-label gs-app-settings-control" for="gs_se_app_key"><?= e($gs('app_key_label')) ?></label>
            <input class="form-input gs-app-settings-control" id="gs_se_app_key" name="se_app_key" type="text" value="">

            <label class="form-label gs-app-settings-control" for="gs_se_app_display_name"><?= e($gs('app_display_name_label')) ?></label>
            <input class="form-input gs-app-settings-control" id="gs_se_app_display_name" name="se_app_display_name" type="text" value="">

            <div class="form-actions gs-app-settings-control">
              <label class="form-label" for="gs_se_app_type"><?= e($gs('app_type_label')) ?></label>
              <select class="form-input" id="gs_se_app_type" name="se_app_type">
                <option value="business">business</option>
                <option value="extension">extension</option>
                <option value="shared">shared</option>
              </select>

              <label class="form-label" for="gs_se_app_version"><?= e($gs('app_version_label')) ?></label>
              <input class="form-input" id="gs_se_app_version" name="se_app_version" type="text" value="">
            </div>

            <h4 class="gs-module-settings-control"><?= e($gs('module_settings')) ?></h4>
            <div class="form-actions">
              <label class="form-label gs-module-settings-control" for="gs_se_module_key"><?= e($gs('module_key')) ?></label>
              <input class="form-input gs-module-settings-control" id="gs_se_module_key" name="se_module_key" type="text" value="">

              <label class="form-label gs-module-settings-control" for="gs_se_module_display_name"><?= e($gs('module_display_name')) ?></label>
              <input class="form-input gs-module-settings-control" id="gs_se_module_display_name" name="se_module_display_name" type="text" value="">

              <label class="form-label gs-module-details-control" for="gs_se_module_type" data-tier="guided"><?= e($gs('module_type')) ?></label>
              <select class="form-input gs-module-details-control" id="gs_se_module_type" name="se_module_type" data-tier="guided">
                <option value="crud">crud</option>
                <option value="dashboard">dashboard</option>
                <option value="queue_workflow">queue_workflow</option>
              </select>
            </div>

            <label class="form-label gs-module-details-control" for="gs_se_module_description" data-tier="guided"><?= e($gs('module_description')) ?></label>
            <input class="form-input gs-module-details-control" id="gs_se_module_description" name="se_module_description" type="text" value="" data-tier="guided">

            <label class="form-label gs-route-path-control" for="gs_se_route_path" data-tier="guided"><?= e($gs('route_path')) ?></label>
            <input class="form-input gs-route-path-control" id="gs_se_route_path" name="se_route_path" type="text" value="" data-tier="guided">

            <h4 class="gs-field-editor-control"><?= e($gs('field_editor')) ?></h4>
            <div class="table-wrap">
              <table class="table" id="gs-fields-table">
                <thead>
                  <tr>
                    <th><?= e($gs('field_name')) ?></th>
                    <th><?= e($gs('field_type')) ?></th>
                    <th><?= e($gs('field_required')) ?></th>
                    <th><?= e($gs('field_default')) ?></th>
                    <th><?= e($gs('action')) ?></th>
                  </tr>
                </thead>
                <tbody id="gs-fields-body"></tbody>
              </table>
            </div>
            <button type="button" class="btn" id="gs-add-field"><?= e($gs('add_field')) ?></button>

            <h4 data-flow-scope="create"><?= e($gs('view_config_editor')) ?></h4>
            <div class="form-actions">
              <label class="form-label gs-create-intent-control" for="gs_se_create_intent"><?= e($gs('create_intent_label')) ?></label>
              <select class="form-input gs-create-intent-control" id="gs_se_create_intent" name="se_create_intent">
                <option value="create_app"><?= e($gs('create_intent_app')) ?></option>
                <option value="create_module"><?= e($gs('create_intent_module')) ?></option>
                <option value="create_view" selected><?= e($gs('create_intent_view')) ?></option>
                <option value="create_navigation"><?= e($gs('create_intent_navigation')) ?></option>
                <option value="create_dashboard"><?= e($gs('create_intent_dashboard')) ?></option>
              </select>
              <label class="form-label gs-view-kind-control" for="gs_se_view_kind"><?= e($gs('view_type')) ?></label>
              <select class="form-input gs-view-kind-control" id="gs_se_view_kind" name="se_view_kind">
                <option value="table"><?= e($gs('view_type_table')) ?></option>
                <option value="form"><?= e($gs('view_type_form')) ?></option>
                <option value="dashboard"><?= e($gs('view_type_dashboard')) ?></option>
              </select>
              <label class="form-label gs-table-edit-mode-control" for="gs_se_table_edit_mode" data-tier="guided"><?= e($gs('table_edit_mode_label')) ?></label>
              <select class="form-input gs-table-edit-mode-control" id="gs_se_table_edit_mode" name="se_table_edit_mode" data-tier="guided">
                <option value="view_only"><?= e($gs('table_edit_mode_view_only')) ?></option>
                <option value="direct_db"><?= e($gs('table_edit_mode_direct_db')) ?></option>
              </select>
              <div class="note gs-table-edit-mode-note" data-tier="guided"></div>
              <label class="form-label u-style-18ae89f4a0" data-tier="guided">
                <input type="checkbox" id="gs_se_layout_compact" name="se_layout_compact" value="1">
                <?= e($gs('layout_compact')) ?>
              </label>
            </div>

            <!-- Direct DB Table Editor Panel (visible only when table_edit_mode=direct_db) -->
            <div id="gs-db-editor" class="card is-hidden" aria-label="<?= e($gs('db_editor_title')) ?>" data-tier="advanced">
              <h4><?= e($gs('db_editor_title')) ?></h4>
              <p class="muted"><?= e($gs('db_editor_note')) ?></p>
              <div class="form-actions">
                <label class="form-label" for="gs_db_table_type"><?= e($gs('db_editor_table_type_label')) ?></label>
                <select class="form-input" id="gs_db_table_type" style="max-width:180px">
                  <option value="orders"><?= e($gs('db_editor_orders_table')) ?></option>
                  <option value="parts"><?= e($gs('db_editor_parts_table')) ?></option>
                </select>
                <button type="button" class="btn" id="gs-db-refresh"><?= e($gs('validate')) ?></button>
              </div>
              <div id="gs-db-editor-status" class="note is-hidden"></div>
              <div id="gs-db-schema-wrap" class="is-hidden">
                <h5><?= e($gs('db_editor_schema_title')) ?></h5>
                <div class="table-wrap">
                  <table class="table" id="gs-db-schema-table">
                    <thead>
                      <tr>
                        <th><?= e($gs('db_editor_field_col')) ?></th>
                        <th><?= e($gs('db_editor_type_col')) ?></th>
                        <th><?= e($gs('db_editor_nullable_col')) ?></th>
                      </tr>
                    </thead>
                    <tbody id="gs-db-schema-body"></tbody>
                  </table>
                </div>
              </div>
              <div id="gs-db-rows-wrap" class="is-hidden">
                <h5><?= e($gs('db_editor_rows_title')) ?></h5>
                <div class="table-wrap">
                  <table class="table" id="gs-db-rows-table">
                    <thead><tr id="gs-db-rows-head-row"></tr></thead>
                    <tbody id="gs-db-rows-body"></tbody>
                  </table>
                </div>
                <button type="button" class="btn" id="gs-db-add-row"><?= e($gs('db_editor_add_row')) ?></button>
              </div>
              <input type="hidden" id="gs_db_editor_csrf" value="<?= e($csrf) ?>">
            </div>

            <div class="ui-block">
              <div class="form-label"><?= e($gs('visible_columns')) ?></div>
              <div id="gs-view-columns" class="form-actions"></div>
            </div>

            <h4 class="gs-view-layout-control" data-tier="guided"><?= e($gs('visual_builder_title')) ?></h4>
            <p class="muted gs-view-layout-control" data-tier="guided"><?= e($gs('visual_builder_phase1_note')) ?></p>
            <label class="form-label gs-row gs-view-layout-control" for="gs_visual_preview_toggle" data-tier="guided">
              <input type="checkbox" id="gs_visual_preview_toggle" value="1">
              <?= e($gs('visual_builder_preview_toggle')) ?>
            </label>
            <div class="form-actions gs-layout-split gs-view-layout-control" id="gs-visual-builder" data-tier="guided">
              <div class="component-palette">
                <h4><?= e($gs('visual_builder_add_component')) ?></h4>
                <div id="gs-component-palette" class="palette-actions">
                  <button type="button" class="btn" data-type="kpi">+ <?= e($gs('component.kpi_card')) ?></button>
                  <button type="button" class="btn" data-type="table">+ <?= e($gs('component.table')) ?></button>
                  <button type="button" class="btn" data-type="form">+ <?= e($gs('component.form')) ?></button>
                  <button type="button" class="btn" data-type="text">+ <?= e($gs('component.text_block')) ?></button>
                </div>
              </div>
              <div class="ui-block" id="visual-builder">
                <div class="form-label"><?= e($gs('visual_builder_canvas')) ?></div>
                <div id="gs-layout-warning" class="note warning is-hidden"></div>
                <div class="gs-canvas-scroll">
                  <div id="gs-layout-canvas" class="grid-canvas"></div>
                </div>
              </div>
            </div>

            <h4 class="gs-navigation-control"><?= e($gs('navigation_editor')) ?></h4>
            <div class="form-actions gs-navigation-control">
              <label class="form-label gs-navigation-control" for="gs_se_nav_section"><?= e($gs('navigation_section')) ?></label>
              <select class="form-input gs-navigation-control" id="gs_se_nav_section" name="se_nav_section">
                <option value="Operations"><?= e($gs('navigation_section_operations')) ?></option>
                <option value="Apps"><?= e($gs('navigation_section_apps')) ?></option>
                <option value="Admin / System"><?= e($gs('navigation_section_admin_system')) ?></option>
              </select>

              <label class="form-label gs-navigation-control" for="gs_se_nav_group"><?= e($gs('navigation_group')) ?></label>
              <input class="form-input gs-navigation-control" id="gs_se_nav_group" name="se_nav_group" type="text" value="Apps">

              <label class="form-label gs-navigation-control" for="gs_se_nav_label"><?= e($gs('navigation_label')) ?></label>
              <input class="form-input gs-navigation-control" id="gs_se_nav_label" name="se_nav_label" type="text" value="">

              <label class="form-label gs-navigation-control" for="gs_se_nav_target"><?= e($gs('navigation_target')) ?></label>
              <input class="form-input gs-navigation-control" id="gs_se_nav_target" name="se_nav_target" type="text" value="">

              <label class="form-label gs-navigation-control" for="gs_se_nav_icon"><?= e($gs('navigation_icon')) ?></label>
              <input class="form-input gs-navigation-control" id="gs_se_nav_icon" name="se_nav_icon" type="text" value="">

              <label class="form-label gs-navigation-control" for="gs_se_nav_order"><?= e($gs('navigation_order')) ?></label>
              <input class="form-input gs-navigation-control" id="gs_se_nav_order" name="se_nav_order" type="number" min="0" step="1" value="10">

              <label class="form-label gs-navigation-control" for="gs_se_nav_visible">
                <input type="checkbox" id="gs_se_nav_visible" name="se_nav_visible" value="1" checked>
                <?= e($gs('navigation_visibility')) ?>
              </label>
            </div>

            <div id="gs-approval-meta-section" data-tier="guided">
            <h4><?= e($gs('apply_metadata_title')) ?></h4>
            <label class="form-label" for="gs_compile_reason"><?= e($gs('reason')) ?></label>
            <textarea class="form-input" id="gs_compile_reason" name="reason" rows="3"><?= e($getInput('reason', $inputs)) ?></textarea>

            <label class="form-label u-style-18ae89f4a0" for="gs_compile_risk_ack" data-flow-scope="upgrade">
              <input type="checkbox" id="gs_compile_risk_ack" name="risk_acknowledged" value="1"<?= $getInput('risk_acknowledged', $inputs) === '1' ? ' checked' : '' ?>>
              <?= e($gs('risk_ack_label')) ?>
            </label>

            <label class="form-label u-style-18ae89f4a0" for="gs_migration_override" data-flow-scope="upgrade">
              <input type="checkbox" id="gs_migration_override" name="migration_override" value="1"<?= $getInput('migration_override', $inputs) === '1' ? ' checked' : '' ?>>
              <?= e($gs('migration_override_label')) ?>
            </label>

            <label class="form-label" for="gs_migration_override_reason" data-flow-scope="upgrade"><?= e($gs('migration_override_reason')) ?></label>
            <textarea class="form-input" id="gs_migration_override_reason" name="migration_override_reason" rows="2" data-flow-scope="upgrade"><?= e($getInput('migration_override_reason', $inputs)) ?></textarea>

            <label class="form-label u-style-18ae89f4a0" for="gs_impact_confirmation" data-flow-scope="upgrade">
              <input type="checkbox" id="gs_impact_confirmation" name="impact_confirmation" value="1"<?= $getInput('impact_confirmation', $inputs) === '1' ? ' checked' : '' ?>>
              <?= e($gs('impact_confirmation_label')) ?>
            </label>

            <label class="form-label u-style-18ae89f4a0" for="gs_impact_acknowledged" data-flow-scope="upgrade">
              <input type="checkbox" id="gs_impact_acknowledged" name="impact_acknowledged" value="1"<?= $getInput('impact_acknowledged', $inputs) === '1' ? ' checked' : '' ?>>
              <?= e($gs('impact_ack_label')) ?>
            </label>

            <label class="form-label u-style-18ae89f4a0" for="gs_simulation_override" data-flow-scope="upgrade">
              <input type="checkbox" id="gs_simulation_override" name="simulation_override" value="1"<?= $getInput('simulation_override', $inputs) === '1' ? ' checked' : '' ?>>
              <?= e($gs('simulation_override_label')) ?>
            </label>

            <label class="form-label" for="gs_simulation_override_reason" data-flow-scope="upgrade"><?= e($gs('simulation_override_reason')) ?></label>
            <textarea class="form-input" id="gs_simulation_override_reason" name="simulation_override_reason" rows="2" data-flow-scope="upgrade"><?= e($getInput('simulation_override_reason', $inputs)) ?></textarea>
            </div><!-- /#gs-approval-meta-section -->

            <div class="form-actions">
              <button type="button" class="btn btn-primary-action" id="btn-to-analyze"><?= e($gs('primary.edit_analyze')) ?> →</button>
              <button type="submit" class="btn btn-primary"><?= e($gs('validate')) ?></button>
              <button type="submit" class="btn" formaction="/apps/studio/compile-plan"><?= e($gs('compile')) ?></button>
            </div>
          </form>
        </section>
      </div>

<section class="gs-workbench-zone gs-workbench-zone-preview" data-gs-workbench-zone="preview">
  <header class="gs-workbench-zone-header">
    <h4 class="gs-workbench-zone-title"><?= e($gs('studio_tool_validation_preview_center')) ?></h4>
    <p class="gs-workbench-zone-helper"><?= e($gs('studio_tools_preview_helper')) ?></p>
    <div class="gs-zone-intent-row">
      <span class="gs-zone-intent-chip\"><?= e($gsBatch1('mode_read_only')) ?></span>
      <span class="gs-zone-intent-chip\"><?= e($gsBatch1('workflow_stage_preview')) ?></span>
      <span class="gs-zone-intent-chip\"><?= e($gsBatch1('workflow_state_no_apply')) ?></span>
    </div>
  </header>
  <aside class="gs-studio-tools-detail-rail">
    <div class="gs-preview-zone-stack">
      <section class="gs-studio-tool-preview gs-zone-block" data-gs-studio-tool-preview>
  <h5 class="gs-studio-tool-preview-title\"><?= e($gs('studio_tools_preview_title')) ?></h5>
  <p class="gs-studio-tool-preview-helper\"><?= e($gs('studio_tools_preview_helper')) ?></p>
  <dl class="gs-studio-tool-preview-grid">
    <div>
      <dt><?= e($gs('studio_tools_preview_field_name')) ?></dt>
      <dd data-gs-tool-preview-name><?= e($gs($studioDefaultPreviewNameKey)) ?></dd>
    </div>
    <div>
      <dt><?= e($gs('studio_tools_preview_field_group')) ?></dt>
      <dd data-gs-tool-preview-group><?= e($gs($studioDefaultPreviewGroupKey)) ?></dd>
    </div>
    <div>
      <dt><?= e($gs('studio_tools_preview_field_status')) ?></dt>
      <dd data-gs-tool-preview-status><?= e($gs($studioDefaultPreviewStatusKey)) ?></dd>
    </div>
    <div>
      <dt><?= e($gs('studio_tools_preview_field_purpose')) ?></dt>
      <dd data-gs-tool-preview-purpose><?= e($gs($studioDefaultPreviewPurposeKey)) ?></dd>
    </div>
    <div>
      <dt><?= e($gs('studio_tools_preview_field_works_on')) ?></dt>
      <dd data-gs-tool-preview-works-on><?= e($gs($studioDefaultPreviewWorksOnKey)) ?></dd>
    </div>
    <div>
      <dt><?= e($gs('studio_tools_preview_field_must_not_own')) ?></dt>
      <dd data-gs-tool-preview-must-not-own><?= e($gs($studioDefaultPreviewMustNotOwnKey)) ?></dd>
    </div>
    <div>
      <dt><?= e($gs('studio_tools_preview_field_first_safe')) ?></dt>
      <dd data-gs-tool-preview-first-safe><?= e($gs($studioDefaultPreviewFirstSafeKey)) ?></dd>
    </div>
    <div>
      <dt><?= e($gs('studio_tools_preview_field_backend')) ?></dt>
      <dd data-gs-tool-preview-backend><?= e($gs($studioDefaultPreviewBackendKey)) ?></dd>
    </div>
  </dl>
  <p class="gs-studio-tool-preview-helper" data-gs-tool-preview-default hidden><?= e($gs('studio_tools_preview_default')) ?></p>
      </section>
      <section class="gs-zone-block gs-preview-validation" data-gs-workflow-status-panel>
        <h5 class="gs-zone-block-title\"><?= e($gs('validation')) ?></h5>
        <p class="gs-zone-block-helper\"><?= e($gsBatch1('workflow_status_helper')) ?></p>
        <dl class="gs-preview-validation-grid">
          <div class="gs-preview-validation-item">
            <dt><?= e($gsBatch1('workflow_stage_analyze')) ?></dt>
            <dd data-gs-workflow-analyze><?= e($gsBatch1('workflow_state_load_before_analysis')) ?></dd>
          </div>
          <div class="gs-preview-validation-item">
            <dt><?= e($gsBatch1('workflow_stage_changes')) ?></dt>
            <dd data-gs-workflow-changes><?= e($gsBatch1('workflow_state_no_diff')) ?></dd>
          </div>
          <div class="gs-preview-validation-item">
            <dt><?= e($gsBatch1('workflow_stage_preview')) ?></dt>
            <dd data-gs-workflow-preview><?= e($gsBatch1('workflow_state_no_preview')) ?></dd>
          </div>
        </dl>
      </section>
      <section class="gs-zone-block">
        <h5 class="gs-zone-block-title\"><?= e($gs('validation_errors')) ?></h5>
        <p class="gs-zone-block-helper\"><?= e($gs('studio_tool_purpose_validation_preview_center')) ?></p>
        <ul class="gs-zone-risk-list">
          <li><strong><?= e($gs('blocked_items')) ?>:</strong> <?= e($gsBatch1('workflow_state_no_changes_staged')) ?></li>
          <li><strong><?= e($gs('precondition_failures')) ?>:</strong> <?= e($gsBatch1('workflow_state_no_apply')) ?></li>
          <li><strong><?= e($gs('risk_level')) ?>:</strong> <?= e($gsBatch1('workflow_state_ready_readonly')) ?></li>
        </ul>
      </section>
    </div>
    </section>
    <section class="gs-workbench-context" data-gs-workbench-context data-gs-no-resource-text="<?= e($gs('studio_workbench_context_no_resource')) ?>" data-gs-no-tool-text="<?= e($gs('studio_workbench_context_no_tool')) ?>" data-gs-backend-planned="<?= e($gs('studio_workbench_context_backend_planned')) ?>" data-gs-backend-linked="<?= e($gs('studio_workbench_context_backend_linked')) ?>" data-gs-no-execution="<?= e($gs('studio_workbench_context_no_execution')) ?>" data-gs-not-loaded="<?= e($gs('not_loaded')) ?>">
<h5 class="gs-workbench-context-title"><?= e($gs('studio_workbench_context_title')) ?></h5>
<dl class="gs-workbench-context-grid">
  <div>
    <dt><?= e($gs('studio_workbench_context_field_selected_tool')) ?></dt>
    <dd data-gs-bridge-selected-tool><?= e($gs('studio_tool_resource_explorer')) ?></dd>
  </div>
  <div>
    <dt><?= e($gs('studio_workbench_context_field_selected_resource')) ?></dt>
    <dd data-gs-bridge-selected-resource><?= e($gs('studio_workbench_context_no_resource')) ?></dd>
  </div>
  <div>
    <dt><?= e($gs('studio_workbench_context_field_owner_app')) ?></dt>
    <dd data-gs-bridge-owner-app><?= e($gs('not_loaded')) ?></dd>
  </div>
  <div>
    <dt><?= e($gs('studio_workbench_context_field_module')) ?></dt>
    <dd data-gs-bridge-module><?= e($gs('not_loaded')) ?></dd>
  </div>
  <div>
    <dt><?= e($gs('studio_workbench_context_field_resource_type')) ?></dt>
    <dd data-gs-bridge-resource-type><?= e($gs('not_loaded')) ?></dd>
  </div>
  <div>
    <dt><?= e($gs('studio_workbench_context_field_action_state')) ?></dt>
    <dd data-gs-bridge-action-state><?= e($gs('studio_workbench_context_action_preview_only')) ?> · <?= e($gs('studio_workbench_context_action_readonly_analysis')) ?> · <?= e($gs('studio_workbench_context_action_no_apply')) ?></dd>
  </div>
  <div>
    <dt><?= e($gs('studio_workbench_context_field_ownership_boundary')) ?></dt>
    <dd data-gs-bridge-ownership-boundary><?= e($gs('studio_workbench_context_boundary_text')) ?></dd>
  </div>
  <div>
    <dt><?= e($gs('studio_tools_preview_field_backend')) ?></dt>
    <dd data-gs-bridge-backend-status><?= e($gs('studio_workbench_context_backend_linked')) ?></dd>
    <p class="gs-workbench-context-helper" data-gs-bridge-execution-status><?= e($gs('studio_workbench_context_no_execution')) ?></p>
  </div>
</dl>
    </section>
  </aside>
</section>

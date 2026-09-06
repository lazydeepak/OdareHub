<section class="gs-workbench-body" data-gs-workbench-body>
  <p class="gs-workbench-load-state" data-gs-workbench-load-state><?= e($gs('content_outline_empty')) ?></p>
  <div class="gs-workbench-zones">
    <section class="gs-workbench-zone gs-workbench-zone-edit" data-gs-workbench-zone="edit">
      <header class="gs-workbench-zone-header">
        <h4 class="gs-workbench-zone-title"><?= e($gs('structured_editor_title')) ?></h4>
        <p class="gs-workbench-zone-helper"><?= e($gs('editor_role_helper')) ?></p>
        <div class="gs-edit-context-badges" data-gs-edit-context-badges>
          <span class="gs-edit-context-badge gs-edit-context-badge--resource-type">
            <span class="gs-edit-context-badge-label"><?= e($gsBatch1('loaded_resource_type_label')) ?></span>
            <span class="gs-edit-context-badge-value" data-gs-edit-badge-resource-type><?= e($gs('not_loaded')) ?></span>
          </span>
          <span class="gs-edit-context-badge gs-edit-context-badge--mode">
            <span class="gs-edit-context-badge-label"><?= e($gsBatch1('loaded_mode_label')) ?></span>
            <span class="gs-edit-context-badge-value" data-gs-edit-badge-mode><?= e($gsBatch1('loaded_mode_read_only')) ?></span>
          </span>
          <span class="gs-edit-context-badge gs-edit-context-badge--owner-app">
            <span class="gs-edit-context-badge-label"><?= e($gsBatch1('loaded_owner_app_label')) ?></span>
            <span class="gs-edit-context-badge-value" data-gs-edit-badge-owner-app><?= e($gs('not_loaded')) ?></span>
          </span>
          <span class="gs-edit-context-badge gs-edit-context-badge--module">
            <span class="gs-edit-context-badge-label"><?= e($gsBatch1('loaded_module_label')) ?></span>
            <span class="gs-edit-context-badge-value" data-gs-edit-badge-module><?= e($gs('not_loaded')) ?></span>
          </span>
        </div>
      </header>
      <?php require __DIR__ . '/tool_navigation.php'; ?>
    </section>
    <div class="gs-workbench-side-stack">
      <?php require __DIR__ . '/validation_preview_zone.php'; ?>
      <?php require __DIR__ . '/governance_apply_zone.php'; ?>
    </div>
  </div>
</section>

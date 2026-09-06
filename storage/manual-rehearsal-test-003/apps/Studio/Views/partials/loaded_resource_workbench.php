<section class="gs-loaded-identity-panel" data-gs-loaded-identity-panel>
  <div class="gs-loaded-identity-top">
    <div>
      <h4 class="gs-loaded-identity-title"><?= e($gsBatch1('loaded_identity_title')) ?></h4>
      <p class="gs-loaded-identity-helper"><?= e($gsBatch1('loaded_identity_helper')) ?></p>
    </div>
    <div class="gs-loaded-context-actions">
      <button type="button" class="btn gs-clear-loaded-context-btn" data-gs-clear-loaded-context><?= e($gsBatch1('clear_loaded_context_action')) ?></button>
      <p class="gs-clear-loaded-context-helper"><?= e($gsBatch1('clear_loaded_context_helper')) ?></p>
    </div>
  </div>
  <div class="gs-loaded-identity-summary">
    <div class="gs-loaded-identity-hero">
      <span class="gs-loaded-identity-label"><?= e($gsBatch1('loaded_resource_key_label')) ?></span>
      <span class="gs-loaded-identity-value" data-gs-loaded-resource-key><?= e($gs('not_loaded')) ?></span>
    </div>
    <div class="gs-loaded-identity-mode">
      <span class="gs-loaded-identity-label"><?= e($gsBatch1('loaded_mode_label')) ?></span>
      <span class="gs-loaded-identity-value" data-gs-loaded-mode><?= e($gsBatch1('loaded_mode_read_only')) ?></span>
    </div>
  </div>
  <dl class="gs-loaded-identity-grid">
    <div class="gs-loaded-identity-item">
      <dt><?= e($gsBatch1('loaded_owner_app_label')) ?></dt>
      <dd data-gs-loaded-owner-app><?= e($gs('not_loaded')) ?></dd>
    </div>
    <div class="gs-loaded-identity-item">
      <dt><?= e($gsBatch1('loaded_module_label')) ?></dt>
      <dd data-gs-loaded-module><?= e($gs('not_loaded')) ?></dd>
    </div>
    <div class="gs-loaded-identity-item">
      <dt><?= e($gsBatch1('loaded_resource_type_label')) ?></dt>
      <dd data-gs-loaded-resource-type><?= e($gs('not_loaded')) ?></dd>
    </div>
    <div class="gs-loaded-identity-item" data-gs-loaded-source-path-row hidden>
      <dt><?= e($gsBatch1('loaded_source_path_label')) ?></dt>
      <dd data-gs-loaded-source-path><?= e($gs('not_loaded')) ?></dd>
    </div>
  </dl>
  <div class="gs-loaded-identity-status">
    <p class="muted gs-loaded-context" data-gs-loaded-context hidden></p>
  </div>
</section>

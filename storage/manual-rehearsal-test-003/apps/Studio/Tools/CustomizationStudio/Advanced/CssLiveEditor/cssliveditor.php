<?php
$model = isset($cssLiveEditorModel) && is_array($cssLiveEditorModel)
    ? $cssLiveEditorModel
    : [];
$sanitizerPolicyJson = htmlspecialchars(
    json_encode($model['sanitizer_policy'] ?? [], JSON_UNESCAPED_SLASHES),
    ENT_QUOTES,
    'UTF-8'
);
$styleSourceCatalogJson = htmlspecialchars(
    json_encode($model['style_source_catalog'] ?? [], JSON_UNESCAPED_SLASHES),
    ENT_QUOTES,
    'UTF-8'
);
$cssSourceResolutionCatalogJson = htmlspecialchars(
    json_encode($model['css_source_resolution_catalog'] ?? [], JSON_UNESCAPED_SLASHES),
    ENT_QUOTES,
    'UTF-8'
);
$targetOptions = is_array($model['target_options'] ?? null) ? array_values($model['target_options']) : [];
$templateOptions = is_array($model['template_options'] ?? null) ? array_values($model['template_options']) : [];
$selectedTarget = is_array($model['selected_target'] ?? null) ? $model['selected_target'] : [];
$selectedTargetType = (string)($selectedTarget['target_type'] ?? '');
$targetOptionsJson = htmlspecialchars(
    json_encode($targetOptions, JSON_UNESCAPED_SLASHES),
    ENT_QUOTES,
    'UTF-8'
);
$templateOptionsJson = htmlspecialchars(
    json_encode($templateOptions, JSON_UNESCAPED_SLASHES),
    ENT_QUOTES,
    'UTF-8'
);
?>
<link rel="stylesheet" href="/assets/apps/studio/styles/gui_studio.css">
<style>
<?php require __DIR__ . '/assets/css_live_editor.css'; ?>
</style>

<section
  class="gui-studio css-live-editor"
  data-css-live-editor-placeholder
  data-preview-ready="<?= e((string)($model['preview_ready'] ?? '')) ?>"
  data-preview-failed="<?= e((string)($model['preview_failed'] ?? '')) ?>"
  data-preview-loading="<?= e((string)($model['preview_loading'] ?? '')) ?>"
  data-preview-feed-url="/apps/studio/tools/customization-studio/advanced/css-live-editor/preview-frame"
>
  <header class="css-live-editor__header">
    <div class="css-live-editor__heading">
      <h2><?= e((string)($model['title'] ?? '')) ?></h2>
      <p class="css-live-editor__message"><?= e((string)($model['message'] ?? '')) ?></p>
    </div>
    <span class="css-live-editor__status"><?= e((string)($model['status'] ?? '')) ?></span>
  </header>

  <section class="css-live-editor__target">
    <div class="css-live-editor__target-heading">
      <h3><?= e((string)($model['target_title'] ?? '')) ?></h3>
    </div>

    <form method="get" action="/apps/studio/tools/customization-studio/advanced/css-live-editor" data-css-live-editor-target-form>
      <div class="css-live-editor__target-fields">
        <label>
          <span><?= e((string)($model['target_selector_label'] ?? '')) ?></span>
          <select name="target_id" data-css-live-editor-target-select>
            <?php if (trim((string)($selectedTarget['id'] ?? '')) === ''): ?>
              <option value="" selected disabled><?= e((string)($model['target_none_selected'] ?? '')) ?></option>
            <?php endif; ?>
            <?php foreach ($targetOptions as $targetOption): ?>
              <?php
              $optionId = trim((string)($targetOption['id'] ?? ''));
              $optionEligible = !empty($targetOption['eligible']);
              $optionLabel = trim((string)($targetOption['title'] ?? $optionId));
              if (!$optionEligible) {
                  $optionLabel .= ' — ' . (string)($model['target_unavailable_label'] ?? '');
                  $optionReason = trim((string)($targetOption['reason'] ?? ''));
                  if ($optionReason !== '') {
                      $optionLabel .= ': ' . $optionReason;
                  }
              }
              ?>
              <option
                value="<?= e($optionId) ?>"
                data-target-route="<?= e((string)($targetOption['route'] ?? '')) ?>"
                data-target-feed="<?= e((string)($targetOption['adapter_id'] ?? '')) ?>"
                <?= $optionId === (string)($selectedTarget['id'] ?? '') ? 'selected' : '' ?>
                <?= $optionEligible ? '' : 'disabled' ?>
              ><?= e($optionLabel) ?></option>
            <?php endforeach; ?>
          </select>
          <small><?= e((string)($model['target_selector_hint'] ?? '')) ?></small>
        </label>
        <button type="submit"><?= e((string)($model['load_preview'] ?? '')) ?></button>
      </div>
      <details class="css-live-editor__advanced-target">
        <summary><?= e((string)($model['advanced_target_title'] ?? 'Advanced source target')) ?></summary>
        <fieldset>
          <legend><?= e((string)($model['target_mode_label'] ?? '')) ?></legend>
          <div class="css-live-editor__mode-options">
            <label>
              <input type="radio" name="css_live_editor_target_mode" value="provider" data-css-live-editor-mode-provider <?= $selectedTargetType === 'php_template' ? '' : 'checked' ?>>
              <span><?= e((string)($model['target_mode_route'] ?? '')) ?></span>
            </label>
            <label>
              <input type="radio" name="css_live_editor_target_mode" value="php_template" data-css-live-editor-mode-template <?= $selectedTargetType === 'php_template' ? 'checked' : '' ?>>
              <span><?= e((string)($model['target_mode_view'] ?? '')) ?></span>
            </label>
          </div>
        </fieldset>
        <div class="css-live-editor__route-fields" data-css-live-editor-route-fields <?= $selectedTargetType === 'php_template' ? 'hidden' : '' ?>>
          <label>
            <span><?= e((string)($model['route_label'] ?? '')) ?></span>
            <input type="text" name="target" value="<?= e((string)($model['target_value'] ?? '')) ?>" placeholder="<?= e((string)($model['route_placeholder'] ?? '')) ?>">
          </label>
          <button type="submit"><?= e((string)($model['load_preview'] ?? '')) ?></button>
        </div>
        <div class="css-live-editor__template-fields" data-css-live-editor-template-fields <?= $selectedTargetType === 'php_template' ? '' : 'hidden' ?>>
          <label>
            <span><?= e((string)($model['template_search_label'] ?? '')) ?></span>
            <input type="search" placeholder="<?= e((string)($model['template_search_placeholder'] ?? '')) ?>" autocomplete="off" data-css-live-editor-template-search>
            <small><?= e((string)($model['template_search_hint'] ?? '')) ?></small>
          </label>
          <label>
            <span><?= e((string)($model['template_results_label'] ?? '')) ?></span>
            <select name="template_target_id" data-css-live-editor-template-select>
              <option value=""><?= e((string)($model['template_none_selected'] ?? '')) ?></option>
              <?php foreach ($templateOptions as $templateOption): ?>
                <?php
                $templateId = trim((string)($templateOption['id'] ?? ''));
                $templateEnabled = !empty($templateOption['enabled']) && !empty($templateOption['eligible']);
                ?>
                <option
                  value="<?= e($templateId) ?>"
                  data-template-search="<?= e((string)($templateOption['search_text'] ?? '')) ?>"
                  <?= $templateId === (string)($selectedTarget['id'] ?? '') ? 'selected' : '' ?>
                  <?= $templateEnabled ? '' : 'disabled' ?>
                ><?= e((string)($templateOption['label'] ?? $templateId)) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <button type="submit"><?= e((string)($model['load_preview'] ?? '')) ?></button>
        </div>
      </details>
    </form>
  </section>

  <div class="css-live-editor__workspace">
    <section class="css-live-editor__canvas">
      <div class="css-live-editor__canvas-heading">
        <h3><?= e((string)($model['canvas_title'] ?? '')) ?></h3>
      </div>
      <?php
      $themeOptions = is_array($model['theme_options'] ?? null) ? $model['theme_options'] : [];
      $themeProviderAvailable = !empty($model['theme_option_provider_available']) && $themeOptions !== [];
      $runtimeThemePreference = trim((string)($model['runtime_theme_preference'] ?? ''));
      ?>
      <div class="css-live-editor__preview-status-bar">
        <div class="css-live-editor__theme-control">
          <label for="cssLiveEditorPreviewTheme"><?= e((string)($model['preview_theme_label'] ?? '')) ?></label>
          <select
            id="cssLiveEditorPreviewTheme"
            data-css-live-editor-preview-theme
            <?= $themeProviderAvailable ? '' : 'disabled' ?>
          >
            <?php foreach ($themeOptions as $themeKey => $themeLabel): ?>
              <option
                value="<?= e((string)$themeKey) ?>"
                <?= (string)$themeKey === $runtimeThemePreference ? 'selected' : '' ?>
              ><?= e((string)$themeLabel) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if (!$themeProviderAvailable): ?>
            <span><?= e((string)($model['preview_theme_unavailable'] ?? '')) ?></span>
          <?php endif; ?>
        </div>
        <span class="css-live-editor__status-item">Feed: <span data-css-live-editor-meta-adapter><?= e((string)($selectedTarget['adapter_name'] ?? '')) ?></span></span>
        <span class="css-live-editor__status-item">Owner: <span data-css-live-editor-meta-owner><?= e((string)($selectedTarget['owner'] ?? '')) ?></span></span>
        <span class="css-live-editor__status-item">Mode: <span data-css-live-editor-meta-mode><?= e((string)($selectedTarget['data_mode'] ?? '')) ?></span></span>
        <span class="css-live-editor__readonly-badge"><?= e((string)($model['status'] ?? '')) ?></span>
      </div>
      <details class="css-live-editor__preview-details">
        <summary><?= e((string)($model['preview_details_title'] ?? 'Preview details')) ?></summary>
        <dl class="css-live-editor__canvas-meta" aria-label="<?= e((string)($model['metadata_title'] ?? '')) ?>">
          <div><dt><?= e((string)($model['metadata_target'] ?? '')) ?></dt><dd data-css-live-editor-meta-target><?= e((string)($selectedTarget['title'] ?? '')) ?></dd></div>
          <div><dt><?= e((string)($model['metadata_owner'] ?? '')) ?></dt><dd data-css-live-editor-meta-owner><?= e((string)($selectedTarget['owner'] ?? '')) ?></dd></div>
          <div><dt data-css-live-editor-meta-source-label><?= e((string)($selectedTargetType === 'php_template' ? ($model['template_file_label'] ?? '') : ($model['metadata_source'] ?? ''))) ?></dt><dd data-css-live-editor-meta-source><?= e((string)($selectedTarget['source_hint'] ?? $selectedTarget['route'] ?? '')) ?></dd></div>
          <div><dt><?= e((string)($model['metadata_adapter'] ?? '')) ?></dt><dd data-css-live-editor-meta-adapter><?= e((string)($selectedTarget['adapter_name'] ?? '')) ?></dd></div>
          <div><dt><?= e((string)($model['metadata_data_mode'] ?? '')) ?></dt><dd data-css-live-editor-meta-mode><?= e((string)($selectedTarget['data_mode'] ?? '')) ?></dd></div>
          <div><dt><?= e((string)($model['metadata_theme'] ?? '')) ?></dt><dd data-css-live-editor-meta-theme><?= e((string)($model['runtime_theme_preference'] ?? '')) ?></dd></div>
        </dl>
      </details>
      <?php $previewFrameUrl = trim((string)($model['preview_frame_url'] ?? '')); ?>
      <?php if ($previewFrameUrl !== ''): ?>
        <div class="css-live-editor__preview-stage" data-css-live-editor-preview-stage>
          <iframe
            src="<?= e($previewFrameUrl) ?>"
            title="<?= e((string)($model['canvas_title'] ?? '')) ?>"
            sandbox="allow-same-origin"
            referrerpolicy="same-origin"
            data-css-live-editor-preview-frame
            data-target-id="<?= e((string)($selectedTarget['id'] ?? '')) ?>"
          ></iframe>
          <span class="css-live-editor__preview-status" data-css-live-editor-preview-status><?= e((string)($model['preview_loading'] ?? '')) ?></span>
        </div>
      <?php else: ?>
        <div class="css-live-editor__canvas-empty">
          <span aria-hidden="true"></span>
          <strong><?= e((string)($model['target_error'] ?? '')) !== '' ? e((string)($model['preview_error_invalid_target'] ?? '')) : e((string)($model['canvas_empty'] ?? '')) ?></strong>
        </div>
      <?php endif; ?>
    </section>

    <aside class="css-live-editor__inspector">
      <div class="css-live-editor__inspector-header">
        <h3><?= e((string)($model['inspector_title'] ?? '')) ?></h3>
        <span class="css-live-editor__inspector-hint"><?= e((string)($model['inspector_description'] ?? '')) ?></span>
      </div>
      <div class="css-live-editor__panel" data-csl-panel-wrapper>
        <button class="css-live-editor__panel-header" data-csl-panel="component" aria-expanded="false">
          <span class="css-live-editor__panel-title"><?= e((string)($model['panel_component'] ?? 'Selected component')) ?></span>
          <span class="css-live-editor__panel-arrow" aria-hidden="true"></span>
        </button>
        <div class="css-live-editor__panel-body" data-csl-panel-body="component" hidden>
          <dl>
            <div>
              <dt><?= e((string)($model['selection_label'] ?? '')) ?></dt>
              <dd data-css-live-editor-selected><?= e((string)($model['selection_empty'] ?? '')) ?></dd>
            </div>
            <div>
              <dt><?= e((string)($model['selection_tag_label'] ?? '')) ?></dt>
              <dd data-css-live-editor-selected-tag><?= e((string)($model['selection_empty'] ?? '')) ?></dd>
            </div>
            <div>
              <dt><?= e((string)($model['selection_id_label'] ?? '')) ?></dt>
              <dd data-css-live-editor-selected-id><?= e((string)($model['selection_empty'] ?? '')) ?></dd>
            </div>
            <div>
              <dt><?= e((string)($model['selection_classes_label'] ?? '')) ?></dt>
              <dd data-css-live-editor-selected-classes><?= e((string)($model['selection_empty'] ?? '')) ?></dd>
            </div>
            <div>
              <dt><?= e((string)($model['selection_feed_label'] ?? '')) ?></dt>
              <dd data-css-live-editor-selected-feed><?= e((string)($selectedTarget['adapter_name'] ?? '')) ?></dd>
            </div>
            <div>
              <dt><?= e((string)($model['selection_source_label'] ?? '')) ?></dt>
              <dd data-css-live-editor-selected-source><?= e((string)($selectedTarget['source_hint'] ?? $selectedTarget['route'] ?? '')) ?></dd>
            </div>
            <div>
              <dt><?= e((string)($model['owner_label'] ?? '')) ?></dt>
              <dd data-css-live-editor-owner><?= e((string)($model['owner_unknown'] ?? '')) ?></dd>
            </div>
            <div>
              <dt><?= e((string)($model['source_target_label'] ?? '')) ?></dt>
              <dd data-css-live-editor-source-target><?= e((string)($model['source_target_unresolved'] ?? '')) ?></dd>
            </div>
            <div>
              <dt><?= e((string)($model['css_target_label'] ?? '')) ?></dt>
              <dd data-css-live-editor-css-target><?= e((string)($model['css_target_unresolved'] ?? '')) ?></dd>
            </div>
            <div>
              <dt><?= e((string)($model['save_label'] ?? '')) ?></dt>
              <dd><?= e((string)($model['save_disabled'] ?? '')) ?></dd>
            </div>
          </dl>
        </div>
      </div>
      <div class="css-live-editor__panel" data-csl-panel-wrapper>
        <button class="css-live-editor__panel-header" data-csl-panel="resolution" aria-expanded="false">
          <span class="css-live-editor__panel-title"><?= e((string)($model['css_resolution_title'] ?? 'CSS source resolution')) ?></span>
          <span class="css-live-editor__panel-badge" data-csl-panel-badge="resolution"></span>
          <span class="css-live-editor__panel-arrow" aria-hidden="true"></span>
        </button>
        <div class="css-live-editor__panel-body" data-csl-panel-body="resolution" hidden>
          <dl>
            <div>
              <dt><?= e((string)($model['css_resolution_status_label'] ?? '')) ?></dt>
              <dd data-css-live-editor-resolution-status><?= e((string)($model['css_resolution_none'] ?? '')) ?></dd>
            </div>
            <div>
              <dt><?= e((string)($model['css_resolution_candidates_label'] ?? '')) ?></dt>
              <dd><div data-css-live-editor-resolution-candidates><?= e((string)($model['css_resolution_none'] ?? '')) ?></div></dd>
            </div>
            <div>
              <dt><?= e((string)($model['css_resolution_editable_label'] ?? '')) ?></dt>
              <dd data-css-live-editor-resolution-editable><?= e((string)($model['css_resolution_editing_disabled'] ?? '')) ?></dd>
            </div>
          </dl>
        </div>
      </div>
      <div class="css-live-editor__panel" data-csl-panel-wrapper>
        <button class="css-live-editor__panel-header" data-csl-panel="selectors" aria-expanded="false">
          <span class="css-live-editor__panel-title"><?= e((string)($model['panel_selectors'] ?? 'Selector matches')) ?></span>
          <span class="css-live-editor__panel-badge" data-csl-panel-badge="selectors"></span>
          <span class="css-live-editor__panel-arrow" aria-hidden="true"></span>
        </button>
        <div class="css-live-editor__panel-body" data-csl-panel-body="selectors" hidden>
          <div class="css-live-editor__selector-preview-bar" data-css-live-editor-selector-preview-bar hidden>
            <span class="css-live-editor__selector-preview-status" data-css-live-editor-selector-status><?= e((string)($model['selector_preview_status'] ?? '')) ?></span>
            <button type="button" class="css-live-editor__clear-highlight" data-css-live-editor-clear-highlight hidden><?= e((string)($model['clear_highlight'] ?? '')) ?></button>
          </div>
          <div class="css-live-editor__selector-chips" data-css-live-editor-resolution-selectors><?= e((string)($model['css_resolution_none'] ?? '')) ?></div>
        </div>
      </div>
      <div class="css-live-editor__panel" data-csl-panel-wrapper>
        <button class="css-live-editor__panel-header" data-csl-panel="declaration" aria-expanded="false">
          <span class="css-live-editor__panel-title"><?= e((string)($model['declaration_title'] ?? 'Declaration preview')) ?></span>
          <span class="css-live-editor__panel-badge" data-csl-panel-badge="declaration"></span>
          <span class="css-live-editor__panel-arrow" aria-hidden="true"></span>
        </button>
        <div class="css-live-editor__panel-body" data-csl-panel-body="declaration" hidden>
          <section class="css-live-editor__declaration-preview" aria-live="polite" data-css-live-editor-declaration-preview hidden>
            <div data-css-live-editor-declaration-content><?= e((string)($model['declaration_empty'] ?? '')) ?></div>
          </section>
        </div>
      </div>
      <div class="css-live-editor__panel" data-csl-panel-wrapper>
        <button class="css-live-editor__panel-header" data-csl-panel="computed" aria-expanded="false">
          <span class="css-live-editor__panel-title"><?= e((string)($model['computed_title'] ?? 'Computed style comparison')) ?></span>
          <span class="css-live-editor__panel-badge" data-csl-panel-badge="computed"></span>
          <span class="css-live-editor__panel-arrow" aria-hidden="true"></span>
        </button>
        <div class="css-live-editor__panel-body" data-csl-panel-body="computed" hidden>
          <section class="css-live-editor__computed-comparison" aria-live="polite" data-css-live-editor-computed-comparison hidden>
            <div data-css-live-editor-computed-content></div>
          </section>
        </div>
      </div>
      <div class="css-live-editor__panel" data-csl-panel-wrapper>
        <button class="css-live-editor__panel-header" data-csl-panel="cascade" aria-expanded="false">
          <span class="css-live-editor__panel-title"><?= e((string)($model['cascade_title'] ?? 'Cascade / source priority')) ?></span>
          <span class="css-live-editor__panel-badge" data-csl-panel-badge="cascade"></span>
          <span class="css-live-editor__panel-arrow" aria-hidden="true"></span>
        </button>
        <div class="css-live-editor__panel-body" data-csl-panel-body="cascade" hidden>
          <section class="css-live-editor__cascade" aria-live="polite" data-css-live-editor-cascade hidden>
            <div data-css-live-editor-cascade-content></div>
          </section>
        </div>
      </div>
      <div class="css-live-editor__panel" data-csl-panel-wrapper>
        <button class="css-live-editor__panel-header" data-csl-panel="tokens" aria-expanded="false">
          <span class="css-live-editor__panel-title"><?= e((string)($model['token_title'] ?? 'Matched source tokens')) ?></span>
          <span class="css-live-editor__panel-badge" data-csl-panel-badge="tokens"></span>
          <span class="css-live-editor__panel-arrow" aria-hidden="true"></span>
        </button>
        <div class="css-live-editor__panel-body" data-csl-panel-body="tokens" hidden>
          <p data-css-live-editor-token-empty><?= e((string)($model['token_empty'] ?? '')) ?></p>
          <div class="css-live-editor__token-list" data-css-live-editor-token-list hidden></div>
        </div>
      </div>
    </aside>
  </div>
</section>

<script
  data-css-live-editor-sanitizer="<?= $sanitizerPolicyJson ?>"
  data-css-live-editor-style-sources="<?= $styleSourceCatalogJson ?>"
  data-css-live-editor-source-resolution="<?= $cssSourceResolutionCatalogJson ?>"
  data-css-live-editor-targets="<?= $targetOptionsJson ?>"
  data-css-live-editor-template-targets="<?= $templateOptionsJson ?>"
  data-token-name-label="<?= e((string)($model['token_name_label'] ?? '')) ?>"
  data-token-value-label="<?= e((string)($model['token_value_label'] ?? '')) ?>"
  data-token-property-label="<?= e((string)($model['token_property_label'] ?? '')) ?>"
  data-token-owner-label="<?= e((string)($model['token_owner_label'] ?? '')) ?>"
  data-token-source-label="<?= e((string)($model['token_source_label'] ?? '')) ?>"
  data-provider-source-label="<?= e((string)($model['metadata_source'] ?? '')) ?>"
  data-template-source-label="<?= e((string)($model['template_file_label'] ?? '')) ?>"
  data-template-canvas-status="<?= e((string)($model['template_canvas_status'] ?? '')) ?>"
  data-resolution-found="<?= e((string)($model['css_resolution_found'] ?? '')) ?>"
  data-resolution-none="<?= e((string)($model['css_resolution_none'] ?? '')) ?>"
  data-resolution-candidate-label="<?= e((string)($model['css_resolution_candidate_label'] ?? '')) ?>"
  data-resolution-likely-selector="<?= e((string)($model['css_resolution_likely_selector'] ?? '')) ?>"
  data-resolution-class-match="<?= e((string)($model['css_resolution_class_match'] ?? '')) ?>"
  data-resolution-owner-stylesheet="<?= e((string)($model['css_resolution_owner_stylesheet'] ?? '')) ?>"
  data-selector-preview-status="<?= e((string)($model['selector_preview_status'] ?? '')) ?>"
  data-clear-highlight-label="<?= e((string)($model['clear_highlight'] ?? '')) ?>"
  data-matches-label="<?= e((string)($model['matches_label'] ?? '')) ?>"
  data-match-label="<?= e((string)($model['match_label'] ?? '')) ?>"
  data-broad-selector="<?= e((string)($model['broad_selector_label'] ?? '')) ?>"
  data-selector-unsafe="<?= e((string)($model['selector_unsafe'] ?? '')) ?>"
  data-declaration-status="<?= e((string)($model['declaration_status'] ?? '')) ?>"
  data-declaration-source-label="<?= e((string)($model['declaration_source_label'] ?? '')) ?>"
  data-declaration-selector-label="<?= e((string)($model['declaration_selector_label'] ?? '')) ?>"
  data-declaration-unresolved="<?= e((string)($model['declaration_unresolved'] ?? '')) ?>"
  data-computed-status="<?= e((string)($model['computed_status'] ?? 'Computed preview only · editing disabled')) ?>"
  data-computed-empty="<?= e((string)($model['computed_empty'] ?? 'No computed values available for this element.')) ?>"
  data-computed-no-declaration="<?= e((string)($model['computed_no_declaration'] ?? 'Computed values for selected element')) ?>"
  data-computed-resolved-label="<?= e((string)($model['computed_resolved_label'] ?? 'Resolved by browser cascade')) ?>"
  data-computed-matched-label="<?= e((string)($model['computed_matched_label'] ?? 'Declared value matches computed')) ?>"
  data-computed-differs-label="<?= e((string)($model['computed_differs_label'] ?? 'Declared value differs from computed')) ?>"
  data-cascade-title="<?= e((string)($model['cascade_title'] ?? 'Cascade / source priority')) ?>"
  data-cascade-winner="<?= e((string)($model['cascade_winner_label'] ?? 'Likely winning declaration')) ?>"
  data-cascade-candidate-label="<?= e((string)($model['cascade_candidate_label'] ?? 'Candidate declaration')) ?>"
  data-cascade-conservative="<?= e((string)($model['cascade_conservative'] ?? "Cascade explanation is conservative \u2014 CSS source resolution traces approved candidates only and does not replicate full browser cascade resolution.")) ?>"
  data-cascade-no-declaration="<?= e((string)($model['cascade_no_declaration'] ?? "No declaration sources match this element's identity.")) ?>"
  data-cascade-status="<?= e((string)($model['cascade_status'] ?? 'Cascade preview only \u00B7 editing disabled')) ?>"
  data-cascade-selector-label="<?= e((string)($model['cascade_selector_label'] ?? 'Selector')) ?>"
  data-cascade-source-label="<?= e((string)($model['cascade_source_label'] ?? 'Source')) ?>"
  data-cascade-declared-label="<?= e((string)($model['cascade_declared_label'] ?? 'Declared')) ?>"
  data-cascade-computed-label="<?= e((string)($model['cascade_computed_label'] ?? 'Computed')) ?>"
  data-cascade-exact-match="<?= e((string)($model['cascade_exact_match'] ?? 'Declared value matches computed')) ?>"
  data-cascade-differs="<?= e((string)($model['cascade_differs'] ?? 'Declared value differs from computed')) ?>"
  data-cascade-css-var-note="<?= e((string)($model['cascade_css_var_note'] ?? 'Resolved by browser cascade')) ?>"
  data-cascade-selector-no-match="<?= e((string)($model['cascade_selector_no_match'] ?? 'Selector does not match selected element')) ?>"
  data-cascade-var-candidate-label="<?= e((string)($model['cascade_var_candidate_label'] ?? 'Variable candidate · winner not proven')) ?>"
  data-cascade-no-match-label="<?= e((string)($model['cascade_no_match_label'] ?? 'No matching approved source declaration found for selected element.')) ?>"
>
<?php require __DIR__ . '/assets/css_live_editor.js'; ?>
</script>

<?php
$model = isset($cssTokenEditorModel) && is_array($cssTokenEditorModel) ? $cssTokenEditorModel : [];
$selectors = isset($model['selectors']) && is_array($model['selectors'])
    ? array_values(array_filter($model['selectors'], 'is_array'))
    : [];
$sourcePath = trim((string)($model['source_path'] ?? '/resources/themes/*'));
$sourceSnapshotUrl = trim((string)($model['source_snapshot_url'] ?? '/apps/studio/tools/customization-studio/design-system/tokens/source-snapshot'));
$availableStyles = isset($model['available_styles']) && is_array($model['available_styles'])
  ? $model['available_styles']
  : [];
$csrfToken = trim((string)($model['csrf'] ?? ''));
$flash = isset($model['flash']) && is_array($model['flash']) ? $model['flash'] : [];

$lang = [];
$langCode = function_exists('current_lang') ? (string)current_lang() : 'en';
$langPath = APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/DesignSystem/DesignTokenEditor/Resources/lang/' . $langCode . '.php';
$fallbackLangPath = APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/DesignSystem/DesignTokenEditor/Resources/lang/en.php';
if (is_file($langPath)) {
    $loaded = require $langPath;
    if (is_array($loaded)) {
        $lang = $loaded;
    }
}
if ($lang === [] && is_file($fallbackLangPath)) {
    $loaded = require $fallbackLangPath;
    if (is_array($loaded)) {
        $lang = $loaded;
    }
}

$cte = static function (string $key) use ($lang): string {
    return (string)($lang[$key] ?? $key);
};

foreach ($selectors as &$selectorRow) {
    $labelKey = trim((string)($selectorRow['label_key'] ?? ''));
    $labelValue = trim((string)($selectorRow['label_value'] ?? ''));
    if ($labelKey === '') {
        continue;
    }
    $labelTemplate = $cte($labelKey);
    $selectorRow['label'] = $labelValue !== ''
        ? sprintf($labelTemplate, $labelValue)
        : $labelTemplate;
}
unset($selectorRow);

$flashType = trim((string)($flash['type'] ?? ''));
$flashMessage = trim((string)($flash['message'] ?? ''));
$flashErrors = isset($flash['errors']) && is_array($flash['errors']) ? $flash['errors'] : [];

$selectorsJson = htmlspecialchars(json_encode($selectors), ENT_QUOTES, 'UTF-8');
$availableStylesJson = htmlspecialchars(json_encode($availableStyles), ENT_QUOTES, 'UTF-8');
$jsI18n = [
  'fetch_failed_source' => $cte('fetch_failed_source'),
  'loading' => $cte('loading'),
  'no_tokens' => $cte('no_tokens'),
  'no_tokens_desc' => $cte('no_tokens_desc'),
  'summary_no_selection' => $cte('summary_no_selection'),
  'summary_source_file_unknown' => $cte('summary_source_file_unknown'),
  'summary_source_layer_unknown' => $cte('summary_source_layer_unknown'),
  'source_layer_foundation' => $cte('source_layer_foundation'),
  'source_layer_semantic' => $cte('source_layer_semantic'),
  'source_layer_variant' => $cte('source_layer_variant'),
  'token_info_changed' => $cte('token_info_changed'),
  'token_info_unchanged' => $cte('token_info_unchanged'),
  'token_info_inherited' => $cte('token_info_inherited'),
  'token_inherited_note' => $cte('token_inherited_note'),
  'summary_tokens_ready' => $cte('summary_tokens_ready'),
  'summary_tokens_inherited_suffix' => $cte('summary_tokens_inherited_suffix'),
  'summary_tokens_modified_suffix' => $cte('summary_tokens_modified_suffix'),
  'group_frequently_edited' => $cte('group_frequently_edited'),
  'group_colors' => $cte('group_colors'),
  'group_semantic_colors' => $cte('group_semantic_colors'),
  'group_glass' => $cte('group_glass'),
  'group_notifications' => $cte('group_notifications'),
  'group_scan' => $cte('group_scan'),
  'group_spacing' => $cte('group_spacing'),
  'group_controls' => $cte('group_controls'),
  'group_radius' => $cte('group_radius'),
  'group_typography' => $cte('group_typography'),
  'group_cards' => $cte('group_cards'),
  'group_chrome_shell' => $cte('group_chrome_shell'),
  'group_tables' => $cte('group_tables'),
  'group_transitions_shadows' => $cte('group_transitions_shadows'),
  'group_other' => $cte('group_other'),
  'filter_changed' => $cte('filter_changed'),
  'diff_title' => $cte('diff_title'),
  'diff_title_empty' => $cte('diff_title_empty'),
  'diff_no_changes' => $cte('diff_no_changes'),
  'color_cat_brand' => $cte('color_cat_brand'),
  'color_cat_backgrounds' => $cte('color_cat_backgrounds'),
  'color_cat_text' => $cte('color_cat_text'),
  'color_cat_borders' => $cte('color_cat_borders'),
  'color_cat_status' => $cte('color_cat_status'),
  'color_cat_effects' => $cte('color_cat_effects'),
  'color_cat_other-colors' => $cte('color_cat_other-colors'),
  'color_details' => $cte('color_details'),
  'color_details_title' => $cte('color_details_title'),
  'current_prefix' => $cte('current_prefix'),
  'color_picker_title' => $cte('color_picker_title'),
  'resolved_label' => $cte('resolved_label'),
  'readability_good' => $cte('readability_good'),
  'readability_low' => $cte('readability_low'),
  'readability_unknown' => $cte('readability_unknown'),
  'validation_ok' => $cte('validation_ok'),
  'validation_warning' => $cte('validation_warning'),
  'validation_severe' => $cte('validation_severe'),
  'validation_unknown' => $cte('validation_unknown'),
  'mode_label' => $cte('mode_label'),
  'mode_simple' => $cte('mode_simple'),
  'mode_advanced' => $cte('mode_advanced'),
  'mode_simple_hint' => $cte('mode_simple_hint'),
  'mode_advanced_hint' => $cte('mode_advanced_hint'),
  'safety_panel_title' => $cte('safety_panel_title'),
  'safety_panel_ok' => $cte('safety_panel_ok'),
  'safety_panel_warning' => $cte('safety_panel_warning'),
  'safety_panel_severe' => $cte('safety_panel_severe'),
  'safety_panel_blocked' => $cte('safety_panel_blocked'),
  'safety_panel_override' => $cte('safety_panel_override'),
  'safety_panel_on' => $cte('safety_panel_on'),
  'safety_panel_review' => $cte('safety_panel_review'),
  'safety_panel_no_issues' => $cte('safety_panel_no_issues'),
  'safety_panel_summary' => $cte('safety_panel_summary'),
  'safety_issue_ratio' => $cte('safety_issue_ratio'),
  'safety_issue_pair' => $cte('safety_issue_pair'),
  'safety_issue_simple_blocked' => $cte('safety_issue_simple_blocked'),
  'safety_issue_simple_blocked_new' => $cte('safety_issue_simple_blocked_new'),
  'safety_issue_override_hint' => $cte('safety_issue_override_hint'),
  'safety_existing_debt_summary' => $cte('safety_existing_debt_summary'),
  'safety_existing_debt_note' => $cte('safety_existing_debt_note'),
  'save_warning_confirm' => $cte('save_warning_confirm'),
  'save_override_confirm' => $cte('save_override_confirm'),
  'save_blocked_severe' => $cte('save_blocked_severe'),
  'safety_details_show' => $cte('safety_details_show'),
  'safety_details_hide' => $cte('safety_details_hide'),
  'safety_unknown' => $cte('safety_unknown'),
  'simple_dashboard_title' => $cte('simple_dashboard_title'),
  'simple_tab_basics' => $cte('simple_tab_basics'),
  'simple_tab_layout' => $cte('simple_tab_layout'),
  'simple_tab_components' => $cte('simple_tab_components'),
  'simple_tab_theme_controls' => $cte('simple_tab_theme_controls'),
  'simple_theme_liquid_glass' => $cte('simple_theme_liquid_glass'),
  'simple_theme_paper' => $cte('simple_theme_paper'),
  'simple_theme_navy' => $cte('simple_theme_navy'),
  'simple_theme_obsidian' => $cte('simple_theme_obsidian'),
  'simple_no_controls' => $cte('simple_no_controls'),
  'simple_components_empty' => $cte('simple_components_empty'),
  'simple_theme_no_controls' => $cte('simple_theme_no_controls'),
  'simple_unit_px' => $cte('simple_unit_px'),
  'simple_control_unavailable' => $cte('simple_control_unavailable'),
  'simple_control_current_token' => $cte('simple_control_current_token'),
  'simple_theme_default_note' => $cte('simple_theme_default_note'),
  'simple_control_background' => $cte('simple_control_background'),
  'simple_control_panel' => $cte('simple_control_panel'),
  'simple_control_card' => $cte('simple_control_card'),
  'simple_control_main_text' => $cte('simple_control_main_text'),
  'simple_control_secondary_text' => $cte('simple_control_secondary_text'),
  'simple_control_accent' => $cte('simple_control_accent'),
  'simple_control_border' => $cte('simple_control_border'),
  'simple_control_text_size' => $cte('simple_control_text_size'),
  'simple_control_corner_roundness' => $cte('simple_control_corner_roundness'),
  'simple_control_control_size' => $cte('simple_control_control_size'),
  'simple_control_ui_density' => $cte('simple_control_ui_density'),
  'simple_control_top_bar' => $cte('simple_control_top_bar'),
  'simple_control_sidebar' => $cte('simple_control_sidebar'),
  'simple_control_buttons' => $cte('simple_control_buttons'),
  'simple_control_inputs' => $cte('simple_control_inputs'),
  'simple_control_tables' => $cte('simple_control_tables'),
  'simple_control_cards' => $cte('simple_control_cards'),
  'simple_control_glass_strength' => $cte('simple_control_glass_strength'),
  'simple_control_glass_transparency' => $cte('simple_control_glass_transparency'),
  'simple_control_glass_blur' => $cte('simple_control_glass_blur'),
  'simple_control_glass_reflection' => $cte('simple_control_glass_reflection'),
  'simple_control_glass_highlights' => $cte('simple_control_glass_highlights'),
  'simple_control_glass_depth' => $cte('simple_control_glass_depth'),
  'simple_control_paper_contrast' => $cte('simple_control_paper_contrast'),
  'simple_control_surface_separation' => $cte('simple_control_surface_separation'),
  'simple_control_paper_depth' => $cte('simple_control_paper_depth'),
  'simple_control_surface_contrast' => $cte('simple_control_surface_contrast'),
  'simple_control_accent_intensity' => $cte('simple_control_accent_intensity'),
  'simple_control_obsidian_contrast' => $cte('simple_control_obsidian_contrast'),
  'simple_control_highlight_intensity' => $cte('simple_control_highlight_intensity'),
  'simple_color_label_suffix' => $cte('simple_color_label_suffix'),
  'simple_color_word' => $cte('simple_color_word'),
  'simple_color_selected' => $cte('simple_color_selected'),
  'simple_color_inherited' => $cte('simple_color_inherited'),
];
$jsI18nJson = htmlspecialchars(json_encode($jsI18n), ENT_QUOTES, 'UTF-8');
?>
<style>
<?php require APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/DesignSystem/DesignTokenEditor/assets/css_token_editor.css'; ?>
</style>

<section class="gui-studio cte-tool">
  <?php if ($flashMessage !== '' || $flashErrors !== []): ?>
    <div class="cte-flash <?= $flashType === 'success' ? 'is-success' : 'is-error' ?>">
      <?php if ($flashMessage !== ''): ?>
        <p><?= e($cte($flashMessage)) ?></p>
      <?php endif; ?>
      <?php if ($flashErrors !== []): ?>
        <ul>
          <?php foreach ($flashErrors as $error): ?>
            <li><?= e(is_string($error) ? $cte($error) : (is_array($error) ? $cte($error['key'] ?? '') . (isset($error['token']) ? ': ' . $error['token'] : '') : '')) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
  </div>

  <?php endif; ?>

  <div class="cte-hero">
    <div>
      <h2><?= e($cte('workspace_title')) ?></h2>
      <p class="muted"><?= e($cte('workspace_subtitle')) ?></p>
      <p style="margin:8px 0 0;font-size:12px;color:var(--muted)"><?= e($cte('workspace_safety_note')) ?></p>
    </div>
    <div class="cte-hero-badges">
      <span class="cte-badge"><?= e($cte('hero_edit_mode')) ?></span>
      <span class="cte-badge is-ok"><?= e($cte('hero_backup_ready')) ?></span>
      <span class="cte-badge"><?= e($cte('hero_save_target')) ?></span>
    </div>
  </div>

  <?php if ($selectors === []): ?>
    <div class="cte-card">
      <div class="cte-empty">
        <strong><?= e($cte('no_selectors')) ?></strong>
        <span><?= e($sourcePath) ?></span>
      </div>
    </div>
  <?php else: ?>

  <div class="cte-top-controls">
    <div class="cte-selector-row">
      <label for="cte-selector-block"><strong><?= e($cte('selector_label')) ?></strong></label>
      <select id="cte-selector-block" data-cte-selector>
        <option value=""><?= e($cte('selector_placeholder')) ?></option>
        <?php
          $currentSourceId = null;
          foreach ($selectors as $sel):
            $sourceId = trim((string)($sel['source_id'] ?? ''));
            $sourceLabel = trim((string)($sel['source_label'] ?? ''));
            if ($sourceId !== $currentSourceId):
              if ($currentSourceId !== null):
        ?>
              </optgroup>
        <?php
              endif;
              if ($sourceId !== ''):
        ?>
              <optgroup label="<?= e($sourceLabel !== '' ? $sourceLabel : $sourceId) ?>">
        <?php
              endif;
              $currentSourceId = $sourceId;
            endif;
        ?>
            <option value="<?= e($sel['key'] ?? '') ?>" data-source-id="<?= e($sel['source_id'] ?? '') ?>" data-source-path="<?= e($sel['source_path'] ?? '') ?>" data-source-kind="<?= e($sel['source_kind'] ?? '') ?>"><?= e($sel['label'] ?? $sel['key'] ?? '') ?></option>
        <?php endforeach; ?>
        <?php if ($currentSourceId !== null && $currentSourceId !== ''): ?>
              </optgroup>
        <?php endif; ?>
      </select>
    </div>
    <div class="cte-mode-row" role="group" aria-label="<?= e($cte('mode_label')) ?>">
      <span class="cte-mode-label"><?= e($cte('mode_label')) ?></span>
      <button type="button" class="cte-mode-pill is-active" data-cte-mode="simple"><?= e($cte('mode_simple')) ?></button>
      <button type="button" class="cte-mode-pill" data-cte-mode="advanced"><?= e($cte('mode_advanced')) ?></button>
    </div>
    <div class="cte-token-search cte-token-search--top">
      <label for="cte-token-search"><strong><?= e($cte('search_label')) ?></strong></label>
      <input id="cte-token-search" type="text" data-cte-search placeholder="<?= e($cte('search_placeholder')) ?>" autocomplete="off" spellcheck="false">
    </div>
  </div>

  <div class="cte-workspace" data-cte-workspace data-cte-selectors="<?= $selectorsJson ?>" data-cte-available-styles="<?= $availableStylesJson ?>" data-cte-i18n="<?= $jsI18nJson ?>" data-cte-source-snapshot-url="<?= e($sourceSnapshotUrl) ?>">

    <div class="cte-card cte-card--editor">
      <div class="cte-card-header">
        <h4><?= e($cte('tokens_panel')) ?></h4>
        <p class="muted"><?= e($cte('tokens_subtitle')) ?></p>
      </div>

      <div class="cte-token-toolbar">
        <div class="cte-mode-guide" data-cte-mode-guide>
          <p class="cte-mode-guide__text" data-cte-mode-guide-simple><?= e($cte('mode_simple_hint')) ?></p>
          <p class="cte-mode-guide__text" data-cte-mode-guide-advanced hidden><?= e($cte('mode_advanced_hint')) ?></p>
        </div>
        <div class="cte-filter-row" role="group" aria-label="<?= e($cte('filter_label')) ?>">
          <span class="cte-filter-label"><?= e($cte('filter_label')) ?></span>
          <button type="button" class="cte-filter-pill is-active" data-cte-filter="all"><?= e($cte('filter_all')) ?></button>
          <button type="button" class="cte-filter-pill" data-cte-filter="frequent"><?= e($cte('filter_frequent')) ?></button>
          <button type="button" class="cte-filter-pill" data-cte-filter="colors"><?= e($cte('filter_colors')) ?></button>
          <button type="button" class="cte-filter-pill" data-cte-filter="changed"><?= e($cte('filter_changed')) ?></button>
          <button type="button" class="cte-filter-pill" data-cte-filter="advanced"><?= e($cte('filter_advanced')) ?></button>
        </div>
      </div>

      <div class="cte-diff-panel" data-cte-diff-panel style="display:none">
        <div class="cte-diff-header">
          <span class="cte-diff-title" data-cte-diff-title><?= e($cte('diff_title')) ?></span>
          <span class="cte-diff-count" data-cte-diff-count></span>
        </div>
        <div class="cte-diff-list" data-cte-diff-list></div>
      </div>

      <div data-cte-tokens>
        <div class="cte-empty">
          <strong><?= e($cte('no_tokens')) ?></strong>
          <span><?= e($cte('no_tokens_desc')) ?></span>
        </div>
      </div>

      <div class="cte-preview-action-row">
        <button type="button" class="btn" data-cte-test data-cte-preview-url="/apps/studio/tools/customization-studio/design-system/tokens/preview"><?= e($cte('action_test')) ?></button>
        <button type="button" class="btn" data-cte-verify><?= e($cte('action_verify')) ?></button>
        <button type="button" class="btn btn-primary" data-cte-save data-confirm-text="<?= e($cte('action_save_confirm')) ?>"><?= e($cte('action_save')) ?></button>
        <button type="button" class="btn" data-cte-reset><?= e($cte('action_reset')) ?></button>
      </div>

      <div class="cte-verify-results" data-cte-verify-results style="display:none"></div>

      <div class="cte-safety-panel" data-cte-safety-panel style="display:none"></div>

      <div class="cte-summary-grid" style="margin-top:10px">
        <article class="cte-summary-card">
          <span class="cte-summary-label"><?= e($cte('summary_source_file')) ?></span>
          <strong data-cte-source-file><?= e($sourcePath) ?></strong>
        </article>
        <article class="cte-summary-card">
          <span class="cte-summary-label"><?= e($cte('summary_source_layer')) ?></span>
          <strong data-cte-source-layer><?= e($cte('summary_source_layer_unknown')) ?></strong>
        </article>
        <article class="cte-summary-card">
          <span class="cte-summary-label"><?= e($cte('summary_selected_block')) ?></span>
          <strong data-cte-selection><?= e($cte('summary_no_selection')) ?></strong>
        </article>
        <article class="cte-summary-card">
          <span class="cte-summary-label"><?= e($cte('summary_tokens_editable')) ?></span>
          <strong data-cte-tokens-count>0</strong>
        </article>
        <article class="cte-summary-card">
          <span class="cte-summary-label"><?= e($cte('summary_environment')) ?></span>
          <strong><?= e($cte('summary_edit_ready')) ?></strong>
        </article>
      </div>
      </div>
      <div class="cte-simple-dashboard" data-cte-simple-dashboard style="display:none"></div>
    </div>

  <?php endif; ?>

  <div style="margin-top:10px;display:flex;gap:8px">
    <a class="btn" href="/apps/studio/legacy"><?= e($cte('action_open_legacy')) ?></a>
  </div>

  <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
</section>

<script>
<?php require APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/DesignSystem/DesignTokenEditor/assets/css_token_editor.js'; ?>
</script>

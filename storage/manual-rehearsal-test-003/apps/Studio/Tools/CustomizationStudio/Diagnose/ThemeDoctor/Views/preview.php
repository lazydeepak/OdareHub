<?php
$themeToolPreviewModel = isset($themeToolPreviewModel) && is_array($themeToolPreviewModel) ? $themeToolPreviewModel : [];
$tokenSelectors = isset($themeToolPreviewModel['token_selectors']) && is_array($themeToolPreviewModel['token_selectors'])
    ? array_values(array_filter($themeToolPreviewModel['token_selectors'], 'is_array'))
    : [];
$focusTokens = isset($themeToolPreviewModel['focus_tokens']) && is_array($themeToolPreviewModel['focus_tokens'])
    ? array_values(array_filter($themeToolPreviewModel['focus_tokens'], 'is_string'))
    : [];
$sourcePath = trim((string)($themeToolPreviewModel['source_path'] ?? '/assets/theme.css'));
$availableStyles = isset($themeToolPreviewModel['available_styles']) && is_array($themeToolPreviewModel['available_styles'])
  ? $themeToolPreviewModel['available_styles']
  : [];
$themeRegistry = isset($themeToolPreviewModel['theme_registry']) && is_array($themeToolPreviewModel['theme_registry'])
  ? $themeToolPreviewModel['theme_registry']
  : [];
$approvedThemes = isset($themeRegistry['approved_themes']) && is_array($themeRegistry['approved_themes'])
  ? array_values(array_filter($themeRegistry['approved_themes'], 'is_array'))
  : [];
$draftThemes = isset($themeRegistry['draft_themes']) && is_array($themeRegistry['draft_themes'])
  ? array_values(array_filter($themeRegistry['draft_themes'], 'is_array'))
  : [];
$runtimeDetectedThemes = isset($themeRegistry['runtime_detected_themes']) && is_array($themeRegistry['runtime_detected_themes'])
  ? array_values(array_filter($themeRegistry['runtime_detected_themes'], 'is_array'))
  : [];
$activeTheme = trim((string)($themeRegistry['active_theme'] ?? ''));
$activeThemeSource = trim((string)($themeRegistry['active_theme_source'] ?? 'unresolved'));
$defaultTheme = trim((string)($themeRegistry['default_theme'] ?? ''));
$defaultThemeSource = trim((string)($themeRegistry['default_theme_source'] ?? 'unresolved'));
$approvedRegistryFound = !empty($themeRegistry['approved_registry_found']);
$approvedRegistryPath = trim((string)($themeRegistry['approved_registry_path'] ?? '/storage/theme_registry/registry.json'));
$draftThemesPath = trim((string)($themeRegistry['draft_themes_path'] ?? '/apps/Studio/Tools/CustomizationStudio/Diagnose/ThemeDoctor/Themes'));
$isDegraded = !empty($themeRegistry['degraded']);
$degradedReasons = isset($themeRegistry['degraded_reasons']) && is_array($themeRegistry['degraded_reasons'])
  ? array_values(array_filter($themeRegistry['degraded_reasons'], 'is_string'))
  : [];

$themeToolLang = [];
$langCode = function_exists('current_lang') ? (string)current_lang() : 'en';
$langPath = APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/ThemeDoctor/Resources/lang/' . $langCode . '.php';
$fallbackLangPath = APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/ThemeDoctor/Resources/lang/en.php';
if (is_file($langPath)) {
    $loaded = require $langPath;
    if (is_array($loaded)) {
        $themeToolLang = $loaded;
    }
}
if ($themeToolLang === [] && is_file($fallbackLangPath)) {
    $loaded = require $fallbackLangPath;
    if (is_array($loaded)) {
        $themeToolLang = $loaded;
    }
}

$tt = static function (string $key) use ($themeToolLang): string {
    return (string)($themeToolLang[$key] ?? $key);
};

$themeOptions = [];
$styleOptions = [];
$styleLabels = [];
$normalizedStyleMap = [];
$hasAvailableStyles = false;

foreach ($availableStyles as $styleKey => $styleLabel) {
  if (!is_string($styleKey)) {
    continue;
  }
  $normalizedKey = strtolower(trim($styleKey));
  if ($normalizedKey === '') {
    continue;
  }
  $normalizedLabel = is_string($styleLabel) && trim($styleLabel) !== ''
    ? trim($styleLabel)
    : ucwords(str_replace(['-', '_', '.'], ' ', $normalizedKey));
  $normalizedStyleMap[$normalizedKey] = $normalizedLabel;
}

if ($normalizedStyleMap !== []) {
  $hasAvailableStyles = true;
  $themeOptions = array_keys($normalizedStyleMap);
  $styleOptions = array_keys($normalizedStyleMap);
  $styleLabels = $normalizedStyleMap;
}

$selectedSet = isset($tokenSelectors[0]) && is_array($tokenSelectors[0]) ? $tokenSelectors[0] : [];
foreach ($tokenSelectors as $set) {
  $themeName = trim((string)($set['theme'] ?? 'default'));
    $styleName = trim((string)($set['style'] ?? 'base'));
  if (!$hasAvailableStyles && $themeName !== '' && !in_array($themeName, $themeOptions, true)) {
        $themeOptions[] = $themeName;
    }
  if (!$hasAvailableStyles && $styleName !== '' && !in_array($styleName, $styleOptions, true)) {
        $styleOptions[] = $styleName;
    $styleLabels[$styleName] = $styleName;
    }
}
$runtimeThemeForDisplay = $activeTheme !== '' ? $activeTheme : $tt('unknown_value');
$runtimeSourceLabel = $tt('source_unresolved');
if ($activeThemeSource === 'approved_registry') {
  $runtimeSourceLabel = $tt('source_approved_registry');
} elseif ($activeThemeSource === 'runtime_detected_fallback') {
  $runtimeSourceLabel = $tt('source_runtime_fallback');
}

$previewThemeDefault = $runtimeThemeForDisplay !== $tt('unknown_value') ? $runtimeThemeForDisplay : ((isset($themeOptions[0]) && $themeOptions[0] !== '') ? (string)$themeOptions[0] : 'default');
$previewStyleDefault = isset($styleOptions[0]) && $styleOptions[0] !== '' ? (string)$styleOptions[0] : 'base';
$selectedSetTokens = isset($selectedSet['tokens']) && is_array($selectedSet['tokens']) ? $selectedSet['tokens'] : [];
$selectedDraftTokens = [];
foreach ($draftThemes as $draftThemeRow) {
  if (!is_array($draftThemeRow)) {
    continue;
  }
  $draftKey = strtolower(trim((string)($draftThemeRow['key'] ?? '')));
  if ($draftKey !== strtolower($previewThemeDefault)) {
    continue;
  }
  if (isset($draftThemeRow['tokens']) && is_array($draftThemeRow['tokens'])) {
    $selectedDraftTokens = $draftThemeRow['tokens'];
  }
  break;
}

$accentDefault = trim((string)($selectedDraftTokens['accent'] ?? $selectedSetTokens['accent'] ?? '#77a7ff'));
$bgDefault = trim((string)($selectedDraftTokens['bg'] ?? $selectedSetTokens['bg'] ?? $selectedSetTokens['style_content_bg'] ?? '#0f1a30'));
$textDefault = trim((string)($selectedDraftTokens['text'] ?? $selectedSetTokens['text'] ?? '#e8eefc'));
$fontDefault = trim((string)($selectedDraftTokens['font_sans'] ?? $selectedSetTokens['font_sans'] ?? 'system-ui, sans-serif'));

$themeSwatches = [];
foreach ($tokenSelectors as $set) {
    $themeName = strtolower(trim((string)($set['theme'] ?? '')));
    if ($themeName === '') {
        continue;
    }
    $setTokens = isset($set['tokens']) && is_array($set['tokens']) ? $set['tokens'] : [];
    $accent = trim((string)($setTokens['accent'] ?? ''));
    $bg = trim((string)($setTokens['bg'] ?? $setTokens['style_content_bg'] ?? ''));
    $text = trim((string)($setTokens['text'] ?? ''));
    if (!isset($themeSwatches[$themeName])) {
        $themeSwatches[$themeName] = [
            'accent' => $accent !== '' ? $accent : '#77a7ff',
            'bg' => $bg !== '' ? $bg : '#0f1a30',
            'text' => $text !== '' ? $text : '#e8eefc',
        ];
    }
}

$findings = isset($themeToolPreviewModel['findings']) && is_array($themeToolPreviewModel['findings'])
    ? array_values(array_filter($themeToolPreviewModel['findings'], 'is_array'))
    : [];
$healthScore = isset($themeToolPreviewModel['health_score']) ? (int)$themeToolPreviewModel['health_score'] : 100;

$degradedReasonLabels = [];
foreach ($degradedReasons as $reason) {
    $degradedReasonLabels[] = $tt('degraded_' . $reason);
}

$runtimeStatus = $activeThemeSource === 'approved_registry' ? 'ok' : ($activeThemeSource === 'runtime_detected_fallback' ? 'fallback' : 'blocked');
$registryStatus = $approvedRegistryFound ? ($approvedThemes !== [] ? 'ok' : 'warning') : 'blocked';
$compiledAssetExists = is_file(APP_ROOT . '/public/assets/theme.css');
$compiledAssetStatus = $compiledAssetExists ? 'ok' : 'warning';
$inventoryStatus = ($approvedThemes !== [] || $runtimeDetectedThemes !== [] || $draftThemes !== []) ? 'ok' : 'blocked';

$findingRecommendations = [];
$recommendationFindings = [];
foreach ($findings as $f) {
    $sev = (string)($f['severity'] ?? '');
    if ($sev === 'blocked' || $sev === 'warning') {
        $findingRecommendations[] = (string)($f['recommendation'] ?? '');
        $recommendationFindings[] = $f;
    }
}
if ($findingRecommendations === []) {
    $recommendationFindings[] = [
        'code' => 'TD000',
        'severity' => 'pass',
        'title' => 'All Systems Healthy',
        'description' => 'No issues detected across registry, assets, inventory, or token sets.',
        'recommendation' => 'No action required.',
        'evidence' => 'All diagnostic checks passed',
    ];
}

$healthScoreClass = $healthScore >= 90 ? 'is-ok' : ($healthScore >= 50 ? 'is-warning' : 'is-blocked');
$blockedCount = 0;
$warningCount = 0;
$infoCount = 0;
foreach ($findings as $f) {
    $s = (string)($f['severity'] ?? '');
    if ($s === 'blocked') { $blockedCount++; }
    elseif ($s === 'warning') { $warningCount++; }
    elseif ($s === 'info') { $infoCount++; }
}

$statusPill = static function (string $statusCode) use ($tt): string {
    $map = [
        'ok' => $tt('diagnostic_status_ok'),
        'warning' => $tt('diagnostic_status_warning'),
        'blocked' => $tt('diagnostic_status_blocked'),
        'fallback' => $tt('diagnostic_status_fallback'),
    ];
    return '<span class="st-theme-badge is-' . $statusCode . '">' . e($map[$statusCode] ?? $statusCode) . '</span>';
};

?>
<style>
<?php require APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/ThemeDoctor/assets/theme_tool.css'; ?>
</style>

<section class="gui-studio st-theme-tool">
  <div class="st-theme-hero <?= $isDegraded ? 'is-degraded' : '' ?>">
    <div>
      <h2><?= e($tt('workspace_title')) ?></h2>
      <p class="muted"><?= e($tt('workspace_subtitle')) ?></p>
      <p class="st-theme-hero-safety"><?= e($tt('workspace_safety_note')) ?></p>
      <p class="muted" style="margin-top:0.5rem"><?= e($tt('safety')) ?></p>
    </div>
    <div class="st-theme-hero-badges">
      <span class="st-theme-badge"><?= e($tt('hero_preview_mode')) ?></span>
      <span class="st-theme-badge <?= $approvedRegistryFound ? 'is-ok' : 'is-warning' ?>"><?= e($approvedRegistryFound ? $tt('hero_registry_found') : $tt('hero_registry_missing')) ?></span>
      <span class="st-theme-badge <?= $activeThemeSource === 'runtime_detected_fallback' ? 'is-warning' : 'is-ok' ?>"><?= e($activeThemeSource === 'runtime_detected_fallback' ? $tt('hero_runtime_fallback') : $tt('hero_runtime_resolved')) ?></span>
    </div>
  </div>

  <div class="st-theme-diagnostic-overview" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:1.5rem">
    <article class="st-summary-card st-diagnostic-card">
      <span class="st-summary-label"><?= e($tt('overview_runtime_status')) ?></span>
      <strong><?= e($activeTheme !== '' ? $activeTheme : $tt('unknown_value')) ?></strong>
      <?= $statusPill($runtimeStatus) ?>
    </article>
    <article class="st-summary-card st-diagnostic-card">
      <span class="st-summary-label"><?= e($tt('overview_registry_status')) ?></span>
      <strong><?= e($approvedRegistryFound ? $tt('overview_healthy') : $tt('overview_degraded')) ?></strong>
      <?= $statusPill($registryStatus) ?>
    </article>
    <article class="st-summary-card st-diagnostic-card">
      <span class="st-summary-label"><?= e($tt('overview_compiled_asset')) ?></span>
      <strong><?= e($compiledAssetExists ? $tt('overview_assets_compiled') : $tt('overview_assets_missing')) ?></strong>
      <?= $statusPill($compiledAssetStatus) ?>
    </article>
    <article class="st-summary-card st-diagnostic-card">
      <span class="st-summary-label"><?= e($tt('overview_theme_inventory')) ?></span>
      <strong><?= e($approvedThemes !== [] || $runtimeDetectedThemes !== [] || $draftThemes !== [] ? sprintf($tt('overview_inventory_ok'), count($approvedThemes), count($runtimeDetectedThemes), count($draftThemes)) : $tt('overview_inventory_none')) ?></strong>
      <?= $statusPill($inventoryStatus) ?>
    </article>
  </div>

  <?php /* Diagnostic Summary Card */ ?>
  <div class="st-theme-summary-section" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:0.75rem;margin-bottom:1.5rem">
    <article class="st-summary-card st-diagnostic-card">
      <span class="st-summary-label"><?= e($tt('diagnostic_summary_title')) ?></span>
      <strong style="font-size:1.4rem"><?= $healthScore ?>%</strong>
      <span class="st-theme-badge <?= $healthScoreClass ?>"><?= e($healthScore >= 90 ? $tt('diagnostic_healthy') : ($healthScore >= 50 ? $tt('diagnostic_degraded') : $tt('diagnostic_blocked_state'))) ?></span>
    </article>
    <article class="st-summary-card st-diagnostic-card">
      <span class="st-summary-label"><?= e($tt('findings_blocked_count')) ?></span>
      <strong style="font-size:1.4rem"><?= $blockedCount ?></strong>
    </article>
    <article class="st-summary-card st-diagnostic-card">
      <span class="st-summary-label"><?= e($tt('findings_warning_count')) ?></span>
      <strong style="font-size:1.4rem"><?= $warningCount ?></strong>
    </article>
    <article class="st-summary-card st-diagnostic-card">
      <span class="st-summary-label"><?= e($tt('findings_info_count')) ?></span>
      <strong style="font-size:1.4rem"><?= $infoCount ?></strong>
    </article>
    <article class="st-summary-card st-diagnostic-card">
      <span class="st-summary-label"><?= e($tt('diagnostic_runtime_theme')) ?></span>
      <strong><?= e($activeTheme !== '' ? $activeTheme : '—') ?></strong>
      <?= $statusPill($runtimeStatus) ?>
    </article>
    <article class="st-summary-card st-diagnostic-card">
      <span class="st-summary-label"><?= e($tt('diagnostic_registry_state')) ?></span>
      <strong><?= e($approvedRegistryFound ? $tt('diagnostic_registry_present') : $tt('diagnostic_registry_absent')) ?></strong>
      <?= $statusPill($registryStatus) ?>
    </article>
    <article class="st-summary-card st-diagnostic-card">
      <span class="st-summary-label"><?= e($tt('diagnostic_compiled_asset')) ?></span>
      <strong><?= e($compiledAssetExists ? $tt('diagnostic_assets_compiled') : $tt('diagnostic_assets_missing')) ?></strong>
      <?= $statusPill($compiledAssetStatus) ?>
    </article>
  </div>

  <div class="st-theme-findings-section" style="margin-bottom:1.5rem">
    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:0.75rem">
      <h3 style="margin:0"><?= e($tt('findings_title')) ?></h3>
      <span class="st-theme-badge st-health-score <?= $healthScoreClass ?>"><?= e($tt('findings_health_score')) ?>: <?= $healthScore ?>%</span>
    </div>

    <?php if ($findings === []): ?>
      <p class="muted"><?= e($tt('findings_none')) ?></p>
    <?php else: ?>
      <div class="st-findings-counts" style="display:flex;gap:0.75rem;margin-bottom:0.75rem;flex-wrap:wrap">
        <?php if ($blockedCount > 0): ?>
          <span class="st-status-pill st-severity-blocked"><?= e($tt('findings_blocked_count')) ?>: <?= $blockedCount ?></span>
        <?php endif; ?>
        <?php if ($warningCount > 0): ?>
          <span class="st-status-pill st-severity-warning"><?= e($tt('findings_warning_count')) ?>: <?= $warningCount ?></span>
        <?php endif; ?>
        <?php if ($infoCount > 0): ?>
          <span class="st-status-pill st-severity-info"><?= e($tt('findings_info_count')) ?>: <?= $infoCount ?></span>
        <?php endif; ?>
        <?php if ($blockedCount === 0 && $warningCount === 0 && $infoCount === 0): ?>
          <span class="st-status-pill st-severity-pass"><?= e($tt('findings_pass_count')) ?></span>
        <?php endif; ?>
      </div>

      <table class="st-findings-table" style="width:100%;border-collapse:collapse;font-size:0.875rem">
        <thead>
          <tr>
            <th style="text-align:left;padding:0.4rem 0.6rem;border-bottom:1px solid var(--line, #334)"><?= e($tt('findings_code')) ?></th>
            <th style="text-align:left;padding:0.4rem 0.6rem;border-bottom:1px solid var(--line, #334)"><?= e($tt('findings_severity')) ?></th>
            <th style="text-align:left;padding:0.4rem 0.6rem;border-bottom:1px solid var(--line, #334)"><?= e($tt('findings_title_label')) ?></th>
            <th style="text-align:left;padding:0.4rem 0.6rem;border-bottom:1px solid var(--line, #334)"><?= e($tt('findings_description')) ?></th>
            <th style="text-align:left;padding:0.4rem 0.6rem;border-bottom:1px solid var(--line, #334)"><?= e($tt('findings_recommendation')) ?></th>
          </tr>
        </thead>
        <tbody>
           <?php foreach ($findings as $f): ?>
             <?php
             $sev = (string)($f['severity'] ?? 'info');
             $sevClass = match ($sev) {
                 'blocked' => 'st-severity-blocked',
                 'warning' => 'st-severity-warning',
                 'info' => 'st-severity-info',
                 default => 'st-severity-pass',
             };
             $code = (string)($f['code'] ?? '');
             $localeKey = strtolower($code) . '_title';
             $title = $tt($localeKey) !== $localeKey ? $tt($localeKey) : ($f['title'] ?? '');
             $descKey = strtolower($code) . '_description';
             $description = $tt($descKey) !== $descKey ? $tt($descKey) : ($f['description'] ?? '');
             $recKey = strtolower($code) . '_recommendation';
             $recommendation = $tt($recKey) !== $recKey ? $tt($recKey) : ($f['recommendation'] ?? '');
             ?>
             <tr class="<?= $sevClass ?>">
               <td style="padding:0.4rem 0.6rem;border-bottom:1px solid var(--line, #334);font-family:monospace;white-space:nowrap"><strong><?= e($code) ?></strong></td>
               <td style="padding:0.4rem 0.6rem;border-bottom:1px solid var(--line, #334)"><span class="st-status-pill <?= $sevClass ?>"><?= e($sev) ?></span></td>
               <td style="padding:0.4rem 0.6rem;border-bottom:1px solid var(--line, #334)"><strong><?= e($title) ?></strong></td>
               <td style="padding:0.4rem 0.6rem;border-bottom:1px solid var(--line, #334)"><?= e($description) ?></td>
               <td style="padding:0.4rem 0.6rem;border-bottom:1px solid var(--line, #334)"><?= e($recommendation) ?></td>
             </tr>
            <?php $evidence = (string)($f['evidence'] ?? '');
            if ($evidence !== ''): ?>
              <tr class="<?= $sevClass ?>-evidence">
                <td style="padding:0.2rem 0.6rem 0.4rem 0.6rem;border-bottom:1px solid var(--line, #334);font-size:0.8rem;color:var(--muted, #999)" colspan="5">
                  <em><?= e($tt('findings_evidence')) ?>:</em> <?= e($evidence) ?>
                </td>
              </tr>
            <?php endif; ?>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <?php /* Recommendations Section */ ?>
  <div class="st-theme-recommendations-section" style="margin-bottom:1.5rem">
    <h3 style="margin:0 0 0.75rem 0"><?= e($tt('recommendations_generated')) ?></h3>
    <ul class="st-theme-warning-list" style="margin:0">
      <?php if ($recommendationFindings === []): ?>
        <li><?= e($tt('recommendation_healthy')) ?></li>
      <?php else: ?>
        <?php foreach ($recommendationFindings as $rf): ?>
          <li><strong>[<?= e($rf['code'] ?? 'TD000') ?>]</strong> <?= e($rf['recommendation'] ?? '') ?></li>
        <?php endforeach; ?>
      <?php endif; ?>
    </ul>
  </div>

  <?php /* Preview removed; moved to collapsed section below diagnostics */ ?>

  <details class="st-theme-card st-theme-details">
    <summary>
      <strong><?= e($tt('governance_title')) ?></strong>
      <span class="muted"><?= e($tt('governance_subtitle')) ?></span>
    </summary>
    <section class="st-theme-tech-section">
      <h4><?= e($tt('lifecycle_actions')) ?></h4>
      <p class="muted"><?= e($tt('lifecycle_actions_help')) ?></p>
      <div class="st-theme-action-grid">
        <button type="button" class="btn" aria-disabled="true" disabled><?= e($tt('action_create_theme')) ?></button>
        <button type="button" class="btn" aria-disabled="true" disabled><?= e($tt('action_duplicate_theme')) ?></button>
        <button type="button" class="btn" aria-disabled="true" disabled><?= e($tt('action_delete_theme')) ?></button>
        <button type="button" class="btn" aria-disabled="true" disabled><?= e($tt('action_set_default')) ?></button>
      </div>
      <p class="muted st-theme-note"><?= e($tt('lifecycle_blocked_note')) ?></p>
    </section>
  </details>

  <details class="st-theme-card st-theme-details" <?= $isDegraded ? 'open' : '' ?>>
    <summary>
      <strong><?= e($tt('technical_details_title')) ?></strong>
      <span class="muted"><?= e($tt('technical_details_subtitle')) ?></span>
    </summary>

    <section class="st-theme-tech-section">
      <h4><?= e($tt('theme_registry_status')) ?></h4>
      <dl class="st-theme-meta">
        <div>
          <dt><?= e($tt('approved_registry')) ?></dt>
          <dd>
            <span class="st-status-pill"><?= e($approvedRegistryFound ? $tt('registry_found') : $tt('registry_missing')) ?></span>
          </dd>
        </div>
        <div>
          <dt><?= e($tt('active_theme_label')) ?></dt>
          <dd>
            <?= e($activeTheme !== '' ? $activeTheme : $tt('unknown_value')) ?>
            <span class="st-status-pill"><?= e($activeThemeSource === 'approved_registry' ? $tt('source_approved_registry') : ($activeThemeSource === 'runtime_detected_fallback' ? $tt('source_runtime_fallback') : $tt('source_unresolved'))) ?></span>
          </dd>
        </div>
        <div>
          <dt><?= e($tt('default_theme_label')) ?></dt>
          <dd>
            <?= e($defaultTheme !== '' ? $defaultTheme : $tt('unknown_value')) ?>
            <span class="st-status-pill"><?= e($defaultThemeSource === 'approved_registry' ? $tt('source_approved_registry') : ($defaultThemeSource === 'runtime_detected_fallback' ? $tt('source_runtime_fallback') : $tt('source_unresolved'))) ?></span>
          </dd>
        </div>
        <div>
          <dt><?= e($tt('registry_path')) ?></dt>
          <dd class="st-token-value"><?= e($approvedRegistryPath) ?></dd>
        </div>
        <div>
          <dt><?= e($tt('draft_path')) ?></dt>
          <dd class="st-token-value"><?= e($draftThemesPath) ?></dd>
        </div>
      </dl>
      <?php if ($degradedReasonLabels !== []): ?>
        <ul class="st-theme-warning-list">
          <?php foreach ($degradedReasonLabels as $label): ?>
            <li><?= e($label) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="st-theme-tech-section">
      <h4><?= e($tt('approved_themes')) ?></h4>
      <div class="st-theme-list-grid">
        <?php if ($approvedThemes === []): ?>
          <p class="muted"><?= e($tt('no_approved_themes')) ?></p>
        <?php else: ?>
          <?php foreach ($approvedThemes as $theme): ?>
            <?php
              $themeKey = trim((string)($theme['key'] ?? ''));
              $swatch = isset($themeSwatches[$themeKey]) && is_array($themeSwatches[$themeKey]) ? $themeSwatches[$themeKey] : ['accent' => '#77a7ff', 'bg' => '#0f1a30', 'text' => '#e8eefc'];
            ?>
            <article class="st-theme-item-card">
              <div class="st-theme-swatch" style="--swatch-bg:<?= e((string)$swatch['bg']) ?>;--swatch-accent:<?= e((string)$swatch['accent']) ?>;--swatch-text:<?= e((string)$swatch['text']) ?>"></div>
              <div>
                <strong><?= e((string)($theme['label'] ?? $themeKey)) ?></strong>
                <p class="muted"><?= e($themeKey) ?> · <?= e((string)($theme['status'] ?? 'approved')) ?></p>
              </div>
            </article>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>

    <section class="st-theme-tech-section">
      <h4><?= e($tt('runtime_detected_themes')) ?></h4>
      <div class="st-theme-list-grid">
        <?php if ($runtimeDetectedThemes === []): ?>
          <p class="muted"><?= e($tt('no_runtime_detected_themes')) ?></p>
        <?php else: ?>
          <?php foreach ($runtimeDetectedThemes as $theme): ?>
            <?php
              $themeKey = trim((string)($theme['key'] ?? ''));
              $swatch = isset($themeSwatches[$themeKey]) && is_array($themeSwatches[$themeKey]) ? $themeSwatches[$themeKey] : ['accent' => '#77a7ff', 'bg' => '#0f1a30', 'text' => '#e8eefc'];
            ?>
            <article class="st-theme-item-card">
              <div class="st-theme-swatch" style="--swatch-bg:<?= e((string)$swatch['bg']) ?>;--swatch-accent:<?= e((string)$swatch['accent']) ?>;--swatch-text:<?= e((string)$swatch['text']) ?>"></div>
              <div>
                <strong><?= e((string)($theme['label'] ?? $themeKey)) ?></strong>
                <p class="muted"><?= e($themeKey) ?> · <?= e((string)($theme['status'] ?? 'runtime-detected')) ?></p>
              </div>
            </article>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>

    <section class="st-theme-tech-section">
      <h4><?= e($tt('draft_themes')) ?></h4>
      <div class="st-theme-list-grid">
        <?php if ($draftThemes === []): ?>
          <p class="muted"><?= e($tt('no_draft_themes')) ?></p>
        <?php else: ?>
          <?php foreach ($draftThemes as $theme): ?>
            <?php
              $themeKey = trim((string)($theme['key'] ?? ''));
              $swatch = isset($themeSwatches[$themeKey]) && is_array($themeSwatches[$themeKey]) ? $themeSwatches[$themeKey] : ['accent' => '#77a7ff', 'bg' => '#0f1a30', 'text' => '#e8eefc'];
            ?>
            <article class="st-theme-item-card">
              <div class="st-theme-swatch" style="--swatch-bg:<?= e((string)$swatch['bg']) ?>;--swatch-accent:<?= e((string)$swatch['accent']) ?>;--swatch-text:<?= e((string)$swatch['text']) ?>"></div>
              <div>
                <strong><?= e((string)($theme['label'] ?? $themeKey)) ?></strong>
                <p class="muted"><?= e($themeKey) ?> · <?= e((string)($theme['status'] ?? 'draft')) ?></p>
              </div>
            </article>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>

    <section class="st-theme-tech-section">
      <h4><?= e($tt('token_sets')) ?></h4>
      <table class="st-token-table">
        <thead>
          <tr>
            <th><?= e($tt('theme')) ?></th>
            <th><?= e($tt('style')) ?></th>
            <th><?= e($tt('selector')) ?></th>
            <th><?= e($tt('tokens')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($tokenSelectors as $set): ?>
            <?php
              $setTokens = isset($set['tokens']) && is_array($set['tokens']) ? $set['tokens'] : [];
              $displayTokens = [];
              foreach ($focusTokens as $tokenName) {
                  if (isset($setTokens[$tokenName])) {
                      $displayTokens[] = '--' . $tokenName . ': ' . (string)$setTokens[$tokenName];
                  }
              }
              if ($displayTokens === []) {
                  foreach ($setTokens as $tokenName => $tokenValue) {
                      $displayTokens[] = '--' . (string)$tokenName . ': ' . (string)$tokenValue;
                      if (count($displayTokens) >= 6) {
                          break;
                      }
                  }
              }
            ?>
            <tr>
              <td><?= e((string)($set['theme'] ?? 'default')) ?></td>
              <td><?= e((string)($set['style'] ?? 'base')) ?></td>
              <td class="st-token-value"><?= e((string)($set['selector'] ?? '')) ?></td>
              <td class="st-token-value"><?= e(implode("\n", $displayTokens)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  </details>

  <section class="st-theme-card">
    <div class="st-theme-controls">
      <a class="btn" href="/apps/studio/legacy"><?= e($tt('open_legacy')) ?></a>
    </div>
  </section>

  <?php /* Preview Inspection Sandbox — collapsed by default */ ?>
  <details class="st-theme-card st-theme-details">
    <summary>
      <strong><?= e($tt('preview_inspection_title')) ?></strong>
      <span class="muted"><?= e($tt('preview_inspection_subtitle')) ?> — <?= e($previewThemeDefault) ?> / <?= e($previewStyleDefault) ?><?= $previewThemeDefault !== $runtimeThemeForDisplay ? ' (Runtime: ' . e($runtimeThemeForDisplay) . ')' : '' ?></span>
    </summary>
    <section class="st-theme-card st-theme-card--controls">
      <h4><?= e($tt('preview_inspection_title')) ?></h4>
      <p class="muted"><?= e($tt('preview_inspection_help')) ?></p>
      <div class="st-theme-controls" data-theme-control-panel>
        <label class="st-theme-control-row">
          <span><?= e($tt('theme')) ?></span>
          <select data-theme-preview-theme name="theme_key">
            <?php foreach ($themeOptions as $option): ?>
              <option value="<?= e($option) ?>" <?= $option === $previewThemeDefault ? 'selected' : '' ?>><?= e($styleLabels[$option] ?? $option) ?></option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="st-theme-control-row">
          <span><?= e($tt('theme_label')) ?></span>
          <input type="text" name="theme_label" data-theme-preview-theme-label value="<?= e(ucwords(str_replace(['-', '_'], ' ', $previewThemeDefault))) ?>">
        </label>

        <label class="st-theme-control-row">
          <span><?= e($tt('style')) ?></span>
          <select data-theme-preview-style name="style">
            <?php foreach ($styleOptions as $option): ?>
              <option value="<?= e($option) ?>" <?= $option === $previewStyleDefault ? 'selected' : '' ?>><?= e($styleLabels[$option] ?? $option) ?></option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="st-theme-control-row">
          <span><?= e($tt('accent')) ?></span>
          <input type="color" value="<?= e($accentDefault) ?>" data-theme-preview-accent name="accent">
        </label>

        <label class="st-theme-control-row">
          <span><?= e($tt('background')) ?></span>
          <input type="color" value="<?= e($bgDefault) ?>" data-theme-preview-bg name="background">
        </label>

        <label class="st-theme-control-row">
          <span><?= e($tt('text')) ?></span>
          <input type="color" value="<?= e($textDefault) ?>" data-theme-preview-text name="text">
        </label>

        <label class="st-theme-control-row">
          <span><?= e($tt('font')) ?></span>
          <input type="text" data-theme-preview-font name="font_sans" value="<?= e($fontDefault) ?>" placeholder="<?= e($tt('font_placeholder')) ?>">
        </label>

        <div class="st-theme-control-row">
          <span></span>
          <div class="st-theme-action-row">
            <button type="button" class="btn" data-theme-preview-reset><?= e($tt('reset')) ?></button>
            <button
              type="button"
              class="btn"
              data-theme-preview-copy
              data-copy-label-default="<?= e($tt('copy_inspection_preset')) ?>"
              data-copy-label-success="<?= e($tt('copy_inspection_preset_done')) ?>"
              data-copy-label-failed="<?= e($tt('copy_inspection_preset_failed')) ?>"
            ><?= e($tt('copy_inspection_preset')) ?></button>
          </div>
        </div>
      </div>

      <div class="st-theme-summary-grid">
        <article class="st-summary-card">
          <span class="st-summary-label"><?= e($tt('summary_runtime_theme')) ?></span>
          <strong><span data-runtime-theme data-runtime-theme-initial="<?= e($runtimeThemeForDisplay) ?>"><?= e($runtimeThemeForDisplay) ?></span></strong>
          <span class="st-status-pill"><?= e($runtimeSourceLabel) ?></span>
        </article>
        <article class="st-summary-card">
          <span class="st-summary-label"><?= e($tt('summary_preview_selection')) ?></span>
          <strong data-preview-selection><?= e($previewThemeDefault . ' / ' . $previewStyleDefault) ?></strong>
          <span
            class="muted"
            data-preview-colors-summary
            data-label-accent="<?= e($tt('accent')) ?>"
            data-label-bg="<?= e($tt('background')) ?>"
            data-label-text="<?= e($tt('text')) ?>"
          ><?= e($tt('summary_colors_ready')) ?></span>
        </article>
        <article class="st-summary-card">
          <span class="st-summary-label"><?= e($tt('summary_token_source')) ?></span>
          <strong><?= e($sourcePath) ?></strong>
          <span class="muted"><?= e($tt('summary_delivery_note')) ?></span>
        </article>
        <article class="st-summary-card">
          <span class="st-summary-label"><?= e($tt('summary_environment')) ?></span>
          <strong><?= e($approvedRegistryFound ? $tt('summary_registry_ready') : $tt('summary_registry_fallback')) ?></strong>
          <span class="muted"><?= e($activeTheme !== '' ? $activeTheme : $tt('unknown_value')) ?> / <?= e($defaultTheme !== '' ? $defaultTheme : $tt('unknown_value')) ?></span>
        </article>
      </div>
    </section>

    <section class="st-theme-card st-theme-card--preview">
      <h4><?= e($tt('preview_stage_title')) ?></h4>
      <p class="muted"><?= e($tt('preview_stage_subtitle')) ?></p>
      <div class="st-theme-preview-stage">
        <div class="st-preview-shell" data-theme-preview-shell data-preview-theme="<?= e($previewThemeDefault) ?>" data-preview-style="<?= e($previewStyleDefault) ?>">
          <div class="st-preview-topbar">
            <strong><?= e($tt('preview_app_title')) ?></strong>
            <div class="st-preview-badges">
              <span class="st-preview-pill"><?= e($tt('preview_mode_chip')) ?></span>
              <span class="st-preview-pill st-preview-pill--subtle"><span data-runtime-style><?= e($previewStyleDefault) ?></span> /           <span data-runtime-mode><?= e($tt('preview_mode_label')) ?></span></span>
            </div>
          </div>
          <div class="st-preview-layout">
            <aside class="st-preview-sidebar">
              <span><?= e($tt('preview_nav_overview')) ?></span>
              <span><?= e($tt('preview_nav_orders')) ?></span>
              <span><?= e($tt('preview_nav_operations')) ?></span>
              <span><?= e($tt('preview_nav_reports')) ?></span>
            </aside>
            <div class="st-preview-main">
              <section class="st-preview-kpis">
                <article>
                  <label><?= e($tt('preview_kpi_quality')) ?></label>
                  <strong>98.4%</strong>
                </article>
                <article>
                  <label><?= e($tt('preview_kpi_cycle')) ?></label>
                  <strong>4.2h</strong>
                </article>
                <article>
                  <label><?= e($tt('preview_kpi_throughput')) ?></label>
                  <strong>312</strong>
                </article>
              </section>
              <section class="st-preview-card">
                <header>
                  <strong><?= e($tt('preview_section_controls')) ?></strong>
                  <span class="st-status-pill"><?= e($tt('preview_chip_read_only')) ?></span>
                </header>
                <div class="st-preview-actions">
                  <button type="button" class="st-preview-btn" aria-disabled="true"><?= e($tt('preview_button_primary')) ?></button>
                  <button type="button" class="st-preview-btn st-preview-btn--ghost" aria-disabled="true"><?= e($tt('preview_button_secondary')) ?></button>
                </div>
                <div class="st-preview-form-grid">
                  <label>
                    <span><?= e($tt('preview_form_theme')) ?></span>
                    <input type="text" value="<?= e($previewThemeDefault) ?>" readonly>
                  </label>
                  <label>
                    <span><?= e($tt('preview_form_style')) ?></span>
                    <input type="text" value="<?= e($previewStyleDefault) ?>" readonly>
                  </label>
                </div>
              </section>
              <section class="st-preview-card">
                <header>
                  <strong><?= e($tt('preview_section_table')) ?></strong>
                  <span class="st-status-pill"><?= e($tt('preview_chip_sample')) ?></span>
                </header>
                <div class="st-preview-table">
                  <div><span><?= e($tt('preview_table_header_item')) ?></span><span><?= e($tt('preview_table_header_status')) ?></span></div>
                  <div><span><?= e($tt('preview_table_row_one')) ?></span><span class="st-status-pill"><?= e($tt('preview_table_state_ok')) ?></span></div>
                  <div><span><?= e($tt('preview_table_row_two')) ?></span><span class="st-status-pill"><?= e($tt('preview_table_state_attention')) ?></span></div>
                </div>
              </section>
              <section class="st-preview-alert">
                <strong><?= e($tt('preview_alert_title')) ?></strong>
                <p><?= e($tt('preview_alert_text')) ?></p>
              </section>
            </div>
          </div>
        </div>
      </div>
    </section>
  </details>
</section>

<script>
<?php require APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/ThemeDoctor/assets/theme_tool.js'; ?>
</script>

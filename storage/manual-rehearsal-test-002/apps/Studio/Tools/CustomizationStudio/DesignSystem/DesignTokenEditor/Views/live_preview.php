<?php
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
?>
<style>
<?php require APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/DesignSystem/DesignTokenEditor/assets/css_token_editor.css'; ?>
</style>

<section class="gui-studio cte-tool cte-live-preview-tab" data-cte-live-preview-tab>
  <div class="cte-live-preview-header">
    <div>
      <h2><?= e($cte('preview_panel')) ?></h2>
      <p class="muted"><?= e($cte('preview_tab_subtitle')) ?></p>
    </div>
    <span class="cte-badge" data-cte-preview-connection data-connected-label="<?= e($cte('preview_live')) ?>"><?= e($cte('preview_waiting')) ?></span>
  </div>

  <?php require APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/DesignSystem/DesignTokenEditor/Views/partials/live_preview.php'; ?>
</section>

<script>
<?php require APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/DesignSystem/DesignTokenEditor/assets/css_token_preview.js'; ?>
</script>

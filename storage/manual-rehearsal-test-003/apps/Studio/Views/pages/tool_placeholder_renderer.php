<?php
$studioToolNavLabel = static function (string $key): string {
    $lang = function_exists('current_lang') ? current_lang() : 'en';
    $dict = [
        'en' => [
            'back_parent' => 'Back to Parent Tool',
            'back_studio' => 'Back to Studio',
        ],
        'ja' => [
            'back_parent' => '親ツールに戻る',
            'back_studio' => 'Studio に戻る',
        ],
        'ne' => [
            'back_parent' => 'Parent Tool मा फर्कनुहोस्',
            'back_studio' => 'Studio मा फर्कनुहोस्',
        ],
    ];

    return (string)($dict[$lang][$key] ?? $dict['en'][$key] ?? $key);
};

$studioToolCurrentPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
$studioToolCurrentPath = '/' . trim($studioToolCurrentPath, '/');
$studioToolParentPath = '';
$studioToolSegments = array_values(array_filter(explode('/', trim($studioToolCurrentPath, '/')), 'strlen'));
if (count($studioToolSegments) > 4
    && ($studioToolSegments[0] ?? '') === 'apps'
    && ($studioToolSegments[1] ?? '') === 'studio'
    && ($studioToolSegments[2] ?? '') === 'tools'
) {
    $studioToolParentPath = '/' . implode('/', array_slice($studioToolSegments, 0, -1));
}
$studioToolNamespaceOnlyParents = [
    '/apps/studio/tools/customization-studio/diagnose' => true,
];
$studioToolShowParentLink = $studioToolParentPath !== ''
    && $studioToolParentPath !== $studioToolCurrentPath
    && !isset($studioToolNamespaceOnlyParents[$studioToolParentPath]);

$renderStudioToolNav = static function () use ($studioToolNavLabel, $studioToolParentPath, $studioToolShowParentLink): void {
    ?>
    <nav class="gui-studio gs-tool-nav" aria-label="Studio tool navigation">
      <span class="gs-tool-nav-start">
        <?php if ($studioToolShowParentLink): ?>
          <a class="gs-tool-nav-link" href="<?= e($studioToolParentPath) ?>"><?= e($studioToolNavLabel('back_parent')) ?></a>
        <?php endif; ?>
      </span>
    </nav>
    <?php
};

$toolTemplatePath = trim((string)($toolTemplatePath ?? ''));
if ($toolTemplatePath !== '' && is_file($toolTemplatePath)) {
  $studioCssVersion = (string)(@filemtime(APP_ROOT . '/apps/Studio/styles/gui_studio.css') ?: '1');
  ?>
  <link rel="stylesheet" href="/assets/apps/studio/styles/gui_studio.css?v=<?= e($studioCssVersion) ?>">
  <?php $renderStudioToolNav(); ?>
  <?php
  require $toolTemplatePath;
  return;
}

$toolModel = isset($toolModel) && is_array($toolModel) ? $toolModel : [];
$studioToolLocale = isset($studioToolLocale) && is_array($studioToolLocale) ? $studioToolLocale : [];

if ($studioToolLocale !== [] && trim((string)($toolModel['key'] ?? '')) !== '') {
    $pt = static function (string $key, string $fallback = '') use ($studioToolLocale): string {
        return isset($studioToolLocale[$key])
            ? (string)$studioToolLocale[$key]
            : ($fallback !== '' ? $fallback : $key);
    };
    $toolName = trim((string)($toolModel['display_name'] ?? $toolModel['name'] ?? ''));
    $toolDescription = trim((string)($toolModel['description'] ?? ''));
    $toolStatus = trim((string)($toolModel['status'] ?? 'planned'));
    $toolRisk = trim((string)($toolModel['risk_level'] ?? 'medium'));
    $resourceTypes = is_array($toolModel['can_load'] ?? null) ? $toolModel['can_load'] : [];
    $migration = is_array($toolModel['migration'] ?? null) ? $toolModel['migration'] : [];
    $capabilities = [
        'create' => !empty($toolModel['can_modify']),
        'edit' => !empty($toolModel['can_modify']),
        'rename' => !empty($toolModel['can_modify']),
        'disable' => !empty($toolModel['can_disable']),
        'delete' => !empty($toolModel['can_modify']),
        'restore' => !empty($toolModel['supports_rollback']),
    ];
    ?>
    <?php $studioCssVersion = (string)(@filemtime(APP_ROOT . '/apps/Studio/styles/gui_studio.css') ?: '1'); ?>
    <link rel="stylesheet" href="/assets/apps/studio/styles/gui_studio.css?v=<?= e($studioCssVersion) ?>">
    <section class="gui-studio gs-tool-placeholder">
      <?php $renderStudioToolNav(); ?>

      <header class="gs-tool-placeholder-hero">
        <div>
          <span class="gs-tool-placeholder-eyebrow"><?= e($pt('studio.placeholder.eyebrow')) ?></span>
          <h2><?= e($toolName) ?></h2>
          <p><?= e($toolDescription) ?></p>
        </div>
        <div class="gs-tool-placeholder-badges">
          <span><?= e($pt('studio.status.' . $toolStatus, $toolStatus)) ?></span>
          <span><?= e($pt('studio.risk.' . $toolRisk, $toolRisk)) ?></span>
        </div>
      </header>

      <div class="gs-tool-placeholder-grid">
        <section class="gs-tool-placeholder-panel gs-tool-placeholder-primary">
          <div class="gs-tool-placeholder-panel-heading">
            <div>
              <h3><?= e($pt('studio.placeholder.lifecycle_title')) ?></h3>
              <p><?= e($pt('studio.placeholder.lifecycle_description')) ?></p>
            </div>
            <span><?= e($pt('studio.placeholder.read_only')) ?></span>
          </div>
          <div class="gs-tool-placeholder-actions">
            <?php foreach ($capabilities as $capability => $supported): ?>
              <div data-supported="<?= $supported ? 'true' : 'false' ?>">
                <strong><?= e($pt('studio.operation.' . $capability)) ?></strong>
                <small><?= e($pt($supported ? 'studio.placeholder.planned_action' : 'studio.placeholder.not_supported')) ?></small>
              </div>
            <?php endforeach; ?>
          </div>
          <p class="gs-tool-placeholder-notice"><?= e($pt('studio.placeholder.safety_notice')) ?></p>
        </section>

        <section class="gs-tool-placeholder-panel">
          <h3><?= e($pt('studio.placeholder.resources_title')) ?></h3>
          <p><?= e($pt('studio.placeholder.resources_description')) ?></p>
          <div class="gs-tool-placeholder-resources">
            <?php foreach ($resourceTypes as $resourceType): ?>
              <code><?= e((string)$resourceType) ?></code>
            <?php endforeach; ?>
          </div>
        </section>

        <section class="gs-tool-placeholder-panel">
          <h3><?= e($pt('studio.placeholder.governance_title')) ?></h3>
          <dl class="gs-tool-placeholder-facts">
            <div><dt><?= e($pt('studio.placeholder.diff')) ?></dt><dd><?= e($pt(!empty($toolModel['supports_diff']) ? 'studio.common.yes' : 'studio.common.no')) ?></dd></div>
            <div><dt><?= e($pt('studio.placeholder.approval')) ?></dt><dd><?= e($pt(!empty($toolModel['requires_approval']) ? 'studio.common.required' : 'studio.common.not_required')) ?></dd></div>
            <div><dt><?= e($pt('studio.placeholder.snapshot')) ?></dt><dd><?= e($pt(!empty($toolModel['supports_snapshot']) ? 'studio.common.yes' : 'studio.common.no')) ?></dd></div>
            <div><dt><?= e($pt('studio.placeholder.rollback')) ?></dt><dd><?= e($pt(!empty($toolModel['supports_rollback']) ? 'studio.common.yes' : 'studio.common.no')) ?></dd></div>
          </dl>
        </section>

        <?php if ($migration !== []): ?>
          <section class="gs-tool-placeholder-panel gs-tool-placeholder-migration">
            <h3><?= e($pt('studio.placeholder.migration_title')) ?></h3>
            <p><?= e($pt((string)($migration['message_key'] ?? ''), $pt('studio.placeholder.migration_default'))) ?></p>
          </section>
        <?php endif; ?>
      </div>
    </section>
    <?php
    return;
}

$tp = static function (string $key): string {
    $lang = function_exists('current_lang') ? current_lang() : 'en';
    $dict = [
        'en' => [
            'title_default' => 'Studio Tool',
            'desc_default' => 'Safe placeholder surface. No apply, mutation, or artifact write occurs here.',
            'status' => 'Read-only placeholder',
            'open_legacy' => 'Open Legacy All-in-One',
            'open_library' => 'Open Resource Library',
            'tool_view_editor_title' => 'View Editor',
            'tool_view_editor_desc' => 'Tool page entry for View Editor. Runtime mutation is disabled on this placeholder page.',
            'tool_menu_editor_title' => 'Menu Editor',
            'tool_menu_editor_desc' => 'Tool page entry for Menu Editor. Runtime mutation is disabled on this placeholder page.',
            'tool_theme_tool_title' => 'Theme Doctor',
            'tool_theme_tool_desc' => 'Read-only diagnostic surface for theme health: runtime, registry, compiled assets, inventory.',
            'tool_app_builder_title' => 'App Builder',
            'tool_app_builder_desc' => 'Tool page entry for App Builder. Runtime mutation is disabled on this placeholder page.',
            'tool_module_builder_title' => 'Module Builder',
            'tool_module_builder_desc' => 'Tool page entry for Module Builder. Runtime mutation is disabled on this placeholder page.',
            'tool_report_designer_title' => 'Report Designer',
            'tool_report_designer_desc' => 'Read-only Report Designer workbench surface. Report definitions remain app/module-owned artifacts.',
            'tool_label_designer_title' => 'Label Designer',
            'tool_label_designer_desc' => 'Governed Studio design workbench for app/module-owned label templates across Manufacturing, Inventory, POS, Dispatch, Warehouse, and future business apps.',
        ],
        'ja' => [
            'title_default' => 'Studio Tool',
            'desc_default' => '安全なプレースホルダー画面です。この画面では適用・更新・成果物書き込みは行いません。',
            'status' => '読み取り専用プレースホルダー',
            'open_legacy' => 'Legacy All-in-One を開く',
            'open_library' => 'Resource Library を開く',
            'tool_view_editor_title' => 'View Editor',
            'tool_view_editor_desc' => 'View Editor の入口ページです。このプレースホルダーでは実行時変更を行いません。',
            'tool_menu_editor_title' => 'Menu Editor',
            'tool_menu_editor_desc' => 'Menu Editor の入口ページです。このプレースホルダーでは実行時変更を行いません。',
            'tool_theme_tool_title' => 'Theme Doctor',
            'tool_theme_tool_desc' => 'テーマ健全性診断の読み取り専用画面：ランタイム、レジストリ、コンパイル済みアセット、インベントリ。',
            'tool_app_builder_title' => 'App Builder',
            'tool_app_builder_desc' => 'App Builder の入口ページです。このプレースホルダーでは実行時変更を行いません。',
            'tool_module_builder_title' => 'Module Builder',
            'tool_module_builder_desc' => 'Module Builder の入口ページです。このプレースホルダーでは実行時変更を行いません。',
            'tool_report_designer_title' => 'Report Designer',
            'tool_report_designer_desc' => '読み取り専用の Report Designer ワークベンチ画面です。レポート定義は app/module 所有アーティファクトのままです。',
            'tool_label_designer_title' => 'Label Designer',
            'tool_label_designer_desc' => 'アプリ・モジュール所有のラベルテンプレートに対応した統制 Studio 設計ワークベンチ（製造、在庫、POS、配送、倉庫、将来の業務アプリ向け）。',
        ],
        'ne' => [
            'title_default' => 'Studio Tool',
            'desc_default' => 'यो सुरक्षित placeholder सतह हो। यहाँ apply, mutation, वा artifact write हुँदैन।',
            'status' => 'रीड-अनली placeholder',
            'open_legacy' => 'Legacy All-in-One खोल्नुहोस्',
            'open_library' => 'Resource Library खोल्नुहोस्',
            'tool_view_editor_title' => 'View Editor',
            'tool_view_editor_desc' => 'View Editor को प्रवेश पृष्ठ। यस placeholder मा runtime mutation बन्द छ।',
            'tool_menu_editor_title' => 'Menu Editor',
            'tool_menu_editor_desc' => 'Menu Editor को प्रवेश पृष्ठ। यस placeholder मा runtime mutation बन्द छ।',
            'tool_theme_tool_title' => 'Theme Doctor',
            'tool_theme_tool_desc' => 'थिम स्वास्थ्यको पढ्न-मात्र निदान सतह: runtime, registry, compiled assets, inventory।',
            'tool_app_builder_title' => 'App Builder',
            'tool_app_builder_desc' => 'App Builder को प्रवेश पृष्ठ। यस placeholder मा runtime mutation बन्द छ।',
            'tool_module_builder_title' => 'Module Builder',
            'tool_module_builder_desc' => 'Module Builder को प्रवेश पृष्ठ। यस placeholder मा runtime mutation बन्द छ।',
            'tool_report_designer_title' => 'Report Designer',
            'tool_report_designer_desc' => 'Read-only Report Designer workbench सतह। Report definition हरू app/module-owned artifact नै रहन्छन्।',
            'tool_label_designer_title' => 'Label Designer',
            'tool_label_designer_desc' => 'Manufacturing, Inventory, POS, Dispatch, Warehouse र भविष्यका business apps मा app/module-owned label templates को लागि governed Studio design workbench।',
        ],
    ];

    $set = isset($dict[$lang]) && is_array($dict[$lang]) ? $dict[$lang] : $dict['en'];
    return (string)($set[$key] ?? ($dict['en'][$key] ?? $key));
};

$nameKey = trim((string)($toolModel['name_key'] ?? 'title_default'));
$descKey = trim((string)($toolModel['description_key'] ?? 'desc_default'));
?>
<link rel="stylesheet" href="/assets/apps/studio/styles/gui_studio.css">
<section class="gui-studio">
  <div class="gs-head">
    <h2><?= e($tp($nameKey)) ?></h2>
    <p class="muted"><?= e($tp($descKey)) ?></p>
  </div>

  <section class="gs-studio-tools-panel">
    <div class="gs-studio-tools-heading">
      <div>
        <h4 class="gs-studio-tools-title"><?= e($tp($nameKey)) ?></h4>
        <p class="gs-studio-tools-helper"><?= e($tp('status')) ?></p>
      </div>
    </div>
    <div class="gs-studio-tools-layout">
      <div class="gs-studio-tools-grid">
        <dl class="gs-studio-tools-group">
          <dt><span><?= e($tp('status')) ?></span></dt>
          <dd class="gs-studio-tools-cards">
            <a class="gs-studio-tool-card" href="/apps/studio/legacy">
              <div class="gs-studio-tool-card-title-row">
                <span class="gs-studio-tool-card-title"><?= e($tp('open_legacy')) ?></span>
              </div>
            </a>
            <a class="gs-studio-tool-card" href="/apps/studio/library">
              <div class="gs-studio-tool-card-title-row">
                <span class="gs-studio-tool-card-title"><?= e($tp('open_library')) ?></span>
              </div>
            </a>
          </dd>
        </dl>
      </div>
    </div>
  </section>
</section>

<?php
$studioHomeCards = isset($studioHomeCards) && is_array($studioHomeCards)
    ? array_values(array_filter($studioHomeCards, 'is_array'))
    : [];

$sh = static function (string $key): string {
    $lang = function_exists('current_lang') ? current_lang() : 'en';
    $dict = [
        'en' => [
            'title' => 'Studio Workbench Home',
            'subtitle' => 'Build, inspect, and hand off governed changes from one focused workspace.',
            'safe_label' => 'Governed Studio workspace',
            'eyebrow' => 'Susankhya Studio',
            'hero_note' => 'Studio edits owner resources through explicit tools and governed workflow stages. Runtime truth stays with the owning app or module.',
            'summary_tools' => 'Tools',
            'summary_available' => 'Available',
            'summary_limited' => 'Limited',
            'summary_disabled' => 'Disabled',
            'mode_simple' => 'Simple',
            'mode_detailed' => 'Detailed',
            'quickstart_title' => 'Quick Start',
            'quickstart_desc' => 'Common tasks to get started with Studio.',
            'quickstart_colors' => 'Edit theme colors',
            'quickstart_colors_desc' => 'Adjust CSS token values and preview changes instantly.',
            'quickstart_translate' => 'Translate text',
            'quickstart_translate_desc' => 'Find and edit localization strings across the system.',
            'quickstart_label' => 'Design a label',
            'quickstart_label_desc' => 'Create and manage label templates for products and shipping.',
            'quickstart_compliance' => 'Check style compliance',
            'quickstart_compliance_desc' => 'Scan CSS for token usage, migration needs, and repair opportunities.',
            'quickstart_customize' => 'Customize appearance',
            'quickstart_customize_desc' => 'Explore visual customization options for theme and layout.',
            'quickstart_theme' => 'Check theme health',
            'quickstart_theme_desc' => 'Diagnose runtime theme state, registry, and compiled assets.',
            'status_friendly_enabled' => 'Ready to use',
            'status_friendly_inspection' => 'Read only',
            'status_friendly_placeholder' => 'Coming soon',
            'status_friendly_migrating' => 'In migration',
            'status_friendly_disabled' => 'Not available',
            'card_advanced_label' => 'Tool details',
            'group_architecture' => 'Architecture',
            'group_architecture_desc' => 'Create and govern apps, modules, plugins, and packages.',
            'group_composition' => 'Composition',
            'group_composition_desc' => 'Shape routes, navigation, views, forms, widgets, and dashboards.',
            'group_data_logic' => 'Data & Logic',
            'group_data_logic_desc' => 'Design schemas, workflows, rules, integrations, and data mappings.',
            'group_design_content' => 'Design & Content',
            'group_design_content_desc' => 'Work with themes, CSS, reports, labels, notifications, and localization.',
            'group_governance' => 'Discover & Govern',
            'group_governance_desc' => 'Inspect resources, validate changes, review impact, and control handoff.',
            'group_workflow' => 'Governed Workflow',
            'group_workflow_desc' => 'Move deliberately from analysis to diff review and approved handoff.',
            'group_migration' => 'Migration Bridge',
            'group_migration_desc' => 'Compatibility access for work still moving into dedicated Studio tools.',
            'workflow_label' => 'Safe change path',
            'workflow_analyze_short' => 'Analyze',
            'workflow_changes_short' => 'Changes',
            'workflow_apply_short' => 'Apply',
            'open_tool' => 'Open tool',
            'unavailable_tool' => 'Not available',
            'studio_home_status_placeholder' => 'Placeholder',
            'studio_home_status_migrating' => 'Migrating',
            'studio_home_tool_view_editor' => 'View Editor',
            'studio_home_tool_view_editor_desc' => 'Open the View Editor workbench entry surface.',
            'studio_home_tool_menu_editor' => 'Menu Editor',
            'studio_home_tool_menu_editor_desc' => 'Open the Menu Editor entry surface.',
            'studio_home_tool_theme' => 'Theme Doctor',
            'studio_home_tool_theme_desc' => 'Read-only theme health diagnostics: runtime, registry, compiled assets, inventory.',
            'studio_home_tool_customization_studio' => 'Customization Studio',
            'studio_home_tool_customization_studio_desc' => 'Preview-only workspace for future theme, socket, component, layout, motion, chart, diagram, and print/export styling tools.',
            'studio_home_tool_app_builder' => 'App Builder',
            'studio_home_tool_app_builder_desc' => 'Open the App Builder entry surface.',
            'studio_home_tool_module_builder' => 'Module Builder',
            'studio_home_tool_module_builder_desc' => 'Open the Module Builder entry surface.',
            'studio_home_tool_report_designer' => 'Report Designer',
            'studio_home_tool_report_designer_desc' => 'Open the Report Designer read-only entry surface.',
            'studio_home_tool_label_designer' => 'Label Designer',
            'studio_home_tool_label_designer_desc' => 'Open the Label Designer — a governed Studio design workbench for app/module-owned label templates across Manufacturing, Inventory, POS, Dispatch, Warehouse, and future business apps.',
            'studio_home_tool_localization_studio' => 'Localization Studio',
            'studio_home_tool_localization_studio_desc' => 'Read-only discovery and inspection of locale resources across all apps and modules.',
            'studio_home_status_inspection_only' => 'Inspection only / read-only',
            'studio_home_tool_css_token_editor' => 'CSS Token Editor',
            'studio_home_tool_css_token_editor_desc' => 'Edit existing theme.css token values safely.',
            'studio_home_workflow_analyze' => 'Workflow / Analyze',
            'studio_home_workflow_analyze_desc' => 'Shared workflow stage for analysis and pre-checks.',
            'studio_home_workflow_changes' => 'Workflow / Changes',
            'studio_home_workflow_changes_desc' => 'Shared workflow stage for change and diff review.',
            'studio_home_workflow_apply' => 'Workflow / Apply',
            'studio_home_workflow_apply_desc' => 'Shared workflow stage for apply and governance handoff.',
            'studio_home_resource_library' => 'Resource Library',
            'studio_home_resource_library_desc' => 'Browse Studio global library resources.',
            'studio_home_legacy' => 'Legacy All-in-One Studio',
            'studio_home_legacy_desc' => 'Open the original all-in-one compatibility workbench.',
            'open' => 'Open',
            'studio_home_status_enabled' => 'Enabled',
            'studio_home_status_disabled' => 'Disabled',
            'studio_home_status_disabled_preview_skeleton' => 'Preview skeleton only / disabled',
        ],
        'ja' => [
            'title' => 'Studioワークベンチホーム',
            'subtitle' => '統制された変更の作成、検査、引き継ぎを一つのワークスペースで行います。',
            'safe_label' => '統制 Studio ワークスペース',
            'eyebrow' => 'Susankhya Studio',
            'hero_note' => 'Studio は専用ツールと統制ワークフローを通じて所有者リソースを編集します。ランタイムの正本は所有アプリまたはモジュールに残ります。',
            'summary_tools' => 'ツール',
            'summary_available' => '利用可能',
            'summary_limited' => '制限あり',
            'summary_disabled' => '無効',
            'mode_simple' => 'シンプル',
            'mode_detailed' => '詳細',
            'quickstart_title' => 'クイックスタート',
            'quickstart_desc' => 'Studio でよく使う一般的なタスク。',
            'quickstart_colors' => 'テーマカラーの編集',
            'quickstart_colors_desc' => 'CSS トークン値を調整し、変更を即座にプレビューします。',
            'quickstart_translate' => 'テキストの翻訳',
            'quickstart_translate_desc' => 'システム全体のローカライズ文字列を検索・編集します。',
            'quickstart_label' => 'ラベルの設計',
            'quickstart_label_desc' => '製品・出荷向けのラベルテンプレートを作成・管理します。',
            'quickstart_compliance' => 'スタイル準拠の確認',
            'quickstart_compliance_desc' => 'CSS のトークン使用状況、移行必要性、修復機会をスキャンします。',
            'quickstart_customize' => '外観のカスタマイズ',
            'quickstart_customize_desc' => 'テーマとレイアウトのビジュアルカスタマイズオプションを探索します。',
            'quickstart_theme' => 'テーマ健全性の確認',
            'quickstart_theme_desc' => 'ランタイムテーマ状態、レジストリ、コンパイル済みアセットを診断します。',
            'status_friendly_enabled' => '使用可能',
            'status_friendly_inspection' => '読み取り専用',
            'status_friendly_placeholder' => '準備中',
            'status_friendly_migrating' => '移行中',
            'status_friendly_disabled' => '利用不可',
            'card_advanced_label' => 'ツール詳細',
            'group_architecture' => 'アーキテクチャ',
            'group_architecture_desc' => 'アプリ、モジュール、プラグイン、パッケージを作成・統制します。',
            'group_composition' => '構成',
            'group_composition_desc' => 'ルート、ナビゲーション、ビュー、フォーム、ウィジェット、ダッシュボードを構成します。',
            'group_data_logic' => 'データ・ロジック',
            'group_data_logic_desc' => 'スキーマ、ワークフロー、ルール、連携、データマッピングを設計します。',
            'group_design_content' => 'デザイン・コンテンツ',
            'group_design_content_desc' => 'テーマ、CSS、レポート、ラベル、通知、ローカライズを編集します。',
            'group_governance' => '探索・統制',
            'group_governance_desc' => 'リソースの検査、変更の検証、影響確認、引き継ぎ統制を行います。',
            'group_workflow' => '統制ワークフロー',
            'group_workflow_desc' => '分析から差分確認、承認済み引き継ぎへ段階的に進みます。',
            'group_migration' => '移行ブリッジ',
            'group_migration_desc' => '専用 Studio ツールへ移行中の作業に互換アクセスを提供します。',
            'workflow_label' => '安全な変更パス',
            'workflow_analyze_short' => '分析',
            'workflow_changes_short' => '変更',
            'workflow_apply_short' => '適用',
            'open_tool' => 'ツールを開く',
            'unavailable_tool' => '利用不可',
            'studio_home_status_placeholder' => 'プレースホルダー',
            'studio_home_status_migrating' => '移行中',
            'studio_home_tool_view_editor' => 'View Editor',
            'studio_home_tool_view_editor_desc' => 'View Editor のエントリー画面を開きます。',
            'studio_home_tool_menu_editor' => 'Menu Editor',
            'studio_home_tool_menu_editor_desc' => 'Menu Editor のエントリー画面を開きます。',
            'studio_home_tool_theme' => 'Theme Doctor',
            'studio_home_tool_theme_desc' => 'テーマ健全性の読み取り専用診断：ランタイム、レジストリ、コンパイル済みアセット、インベントリ。',
            'studio_home_tool_customization_studio' => 'Customization Studio',
            'studio_home_tool_customization_studio_desc' => 'Preview-only workspace for future theme, socket, component, layout, motion, chart, diagram, and print/export styling tools.',
            'studio_home_tool_app_builder' => 'App Builder',
            'studio_home_tool_app_builder_desc' => 'App Builder のエントリー画面を開きます。',
            'studio_home_tool_module_builder' => 'Module Builder',
            'studio_home_tool_module_builder_desc' => 'Module Builder のエントリー画面を開きます。',
            'studio_home_tool_report_designer' => 'Report Designer',
            'studio_home_tool_report_designer_desc' => 'Report Designer の読み取り専用エントリー画面を開きます。',
            'studio_home_tool_label_designer' => 'Label Designer',
            'studio_home_tool_label_designer_desc' => 'Label Designer を開きます — アプリ・モジュール所有のラベルテンプレートに対応した統制 Studio 設計ワークベンチ（製造、在庫、POS、配送、倉庫、将来の業務アプリ向け）。',
            'studio_home_tool_localization_studio' => 'Localization Studio',
            'studio_home_tool_localization_studio_desc' => '全てのアプリとモジュールのロケールリソースを読み取り専用で検出・検査します。',
            'studio_home_status_inspection_only' => '検査のみ / 読み取り専用',
            'studio_home_tool_css_token_editor' => 'CSS Token Editor',
            'studio_home_tool_css_token_editor_desc' => 'theme.css の既存トークン値を安全に編集します。',
            'studio_home_workflow_analyze' => 'Workflow / Analyze',
            'studio_home_workflow_analyze_desc' => '分析と事前チェックの共有ワークフローステージです。',
            'studio_home_workflow_changes' => 'Workflow / Changes',
            'studio_home_workflow_changes_desc' => '変更と差分確認の共有ワークフローステージです。',
            'studio_home_workflow_apply' => 'Workflow / Apply',
            'studio_home_workflow_apply_desc' => '適用とガバナンス引き継ぎの共有ワークフローステージです。',
            'studio_home_resource_library' => 'Resource Library',
            'studio_home_resource_library_desc' => 'Studio のグローバルライブラリ資源を参照します。',
            'studio_home_legacy' => 'Legacy All-in-One Studio',
            'studio_home_legacy_desc' => '既存の互換オールインワン画面を開きます。',
            'open' => '開く',
            'studio_home_status_enabled' => '有効',
            'studio_home_status_disabled' => '無効',
            'studio_home_status_disabled_preview_skeleton' => 'Preview skeleton only / disabled',
        ],
        'ne' => [
            'title' => 'स्टुडियो वर्कबेन्च होम',
            'subtitle' => 'एउटै केन्द्रित workspace बाट governed परिवर्तनहरू बनाउनुहोस्, निरीक्षण गर्नुहोस् र handoff गर्नुहोस्।',
            'safe_label' => 'Governed Studio workspace',
            'eyebrow' => 'Susankhya Studio',
            'hero_note' => 'Studio ले स्पष्ट tools र governed workflow चरणहरू मार्फत owner resources सम्पादन गर्छ। Runtime truth सम्बन्धित app वा module मै रहन्छ।',
            'summary_tools' => 'उपकरण',
            'summary_available' => 'उपलब्ध',
            'summary_limited' => 'सीमित',
            'summary_disabled' => 'निष्क्रिय',
            'mode_simple' => 'सरल',
            'mode_detailed' => 'विस्तृत',
            'quickstart_title' => 'द्रुत सुरुवात',
            'quickstart_desc' => 'Studio मा सामान्य कार्यहरू सुरु गर्न।',
            'quickstart_colors' => 'थिम रङ सम्पादन',
            'quickstart_colors_desc' => 'CSS टोकन मान समायोजन र परिवर्तन तुरुन्त पूर्वावलोकन।',
            'quickstart_translate' => 'पाठ अनुवाद',
            'quickstart_translate_desc' => 'प्रणालीभर स्थानीयकरण स्ट्रिङ खोज र सम्पादन।',
            'quickstart_label' => 'लेबल डिजाइन',
            'quickstart_label_desc' => 'उत्पादन र ढुवानीको लागि लेबल टेम्पलेट सिर्जना र व्यवस्थापन।',
            'quickstart_compliance' => 'शैली अनुपालन जाँच',
            'quickstart_compliance_desc' => 'CSS टोकन प्रयोग, माइग्रेसन आवश्यकता, मर्मत अवसर स्क्यान।',
            'quickstart_customize' => 'उपस्थिति अनुकूलन',
            'quickstart_customize_desc' => 'थिम र लेआउटको लागि दृश्य अनुकूलन विकल्प अन्वेषण।',
            'quickstart_theme' => 'थिम स्वास्थ्य जाँच',
            'quickstart_theme_desc' => 'Runtime थिम अवस्था, रजिस्ट्री, compiled assets निदान।',
            'status_friendly_enabled' => 'प्रयोग गर्न तयार',
            'status_friendly_inspection' => 'पढ्न मात्र',
            'status_friendly_placeholder' => 'चाँडै उपलब्ध',
            'status_friendly_migrating' => 'माइग्रेसनमा',
            'status_friendly_disabled' => 'उपलब्ध छैन',
            'card_advanced_label' => 'उपकरण विवरण',
            'group_architecture' => 'आर्किटेक्चर',
            'group_architecture_desc' => 'Apps, modules, plugins र packages सिर्जना र govern गर्नुहोस्।',
            'group_composition' => 'कम्पोजिसन',
            'group_composition_desc' => 'Routes, navigation, views, forms, widgets र dashboards बनाउनुहोस्।',
            'group_data_logic' => 'डेटा र लजिक',
            'group_data_logic_desc' => 'Schemas, workflows, rules, integrations र data mappings डिजाइन गर्नुहोस्।',
            'group_design_content' => 'डिजाइन र सामग्री',
            'group_design_content_desc' => 'Themes, CSS, reports, labels, notifications र localization मा काम गर्नुहोस्।',
            'group_governance' => 'खोज र शासन',
            'group_governance_desc' => 'Resources निरीक्षण, changes validate, impact review र handoff control गर्नुहोस्।',
            'group_workflow' => 'Governed Workflow',
            'group_workflow_desc' => 'Analysis बाट diff review र approved handoff सम्म क्रमबद्ध रूपमा जानुहोस्।',
            'group_migration' => 'Migration Bridge',
            'group_migration_desc' => 'Dedicated Studio tools मा सर्दै गरेको कामका लागि compatibility access।',
            'workflow_label' => 'सुरक्षित परिवर्तन मार्ग',
            'workflow_analyze_short' => 'विश्लेषण',
            'workflow_changes_short' => 'परिवर्तन',
            'workflow_apply_short' => 'लागू',
            'open_tool' => 'उपकरण खोल्नुहोस्',
            'unavailable_tool' => 'उपलब्ध छैन',
            'studio_home_status_placeholder' => 'Placeholder',
            'studio_home_status_migrating' => 'Migration मा',
            'studio_home_tool_view_editor' => 'View Editor',
            'studio_home_tool_view_editor_desc' => 'View Editor को प्रवेश सतह खोल्नुहोस्।',
            'studio_home_tool_menu_editor' => 'Menu Editor',
            'studio_home_tool_menu_editor_desc' => 'Menu Editor को प्रवेश सतह खोल्नुहोस्।',
            'studio_home_tool_theme' => 'Theme Doctor',
            'studio_home_tool_theme_desc' => 'थिम स्वास्थ्यको पढ्न-मात्र निदान: runtime, registry, compiled assets, inventory।',
            'studio_home_tool_customization_studio' => 'Customization Studio',
            'studio_home_tool_customization_studio_desc' => 'Preview-only workspace for future theme, socket, component, layout, motion, chart, diagram, and print/export styling tools.',
            'studio_home_tool_app_builder' => 'App Builder',
            'studio_home_tool_app_builder_desc' => 'App Builder को प्रवेश सतह खोल्नुहोस्।',
            'studio_home_tool_module_builder' => 'Module Builder',
            'studio_home_tool_module_builder_desc' => 'Module Builder को प्रवेश सतह खोल्नुहोस्।',
            'studio_home_tool_report_designer' => 'Report Designer',
            'studio_home_tool_report_designer_desc' => 'Report Designer को read-only प्रवेश सतह खोल्नुहोस्।',
            'studio_home_tool_label_designer' => 'Label Designer',
            'studio_home_tool_label_designer_desc' => 'Label Designer खोल्नुहोस् — Manufacturing, Inventory, POS, Dispatch, Warehouse र भविष्यका business apps मा app/module-owned label templates को लागि governed Studio design workbench।',
            'studio_home_tool_localization_studio' => 'Localization Studio',
            'studio_home_tool_localization_studio_desc' => 'सबै एप र मोड्युलको स्थानीयकरण स्रोतहरूको रीड-अनली पत्ता लगाउने र निरीक्षण।',
            'studio_home_status_inspection_only' => 'निरीक्षण मात्र / रीड-अनली',
            'studio_home_tool_css_token_editor' => 'CSS Token Editor',
            'studio_home_tool_css_token_editor_desc' => 'theme.css टोकन मानहरू सुरक्षित रूपमा सम्पादन गर्नुहोस्।',
            'studio_home_workflow_analyze' => 'Workflow / Analyze',
            'studio_home_workflow_analyze_desc' => 'विश्लेषण र pre-check का लागि साझा workflow चरण।',
            'studio_home_workflow_changes' => 'Workflow / Changes',
            'studio_home_workflow_changes_desc' => 'परिवर्तन र diff समीक्षा का लागि साझा workflow चरण।',
            'studio_home_workflow_apply' => 'Workflow / Apply',
            'studio_home_workflow_apply_desc' => 'apply र governance handoff का लागि साझा workflow चरण।',
            'studio_home_resource_library' => 'Resource Library',
            'studio_home_resource_library_desc' => 'Studio global library स्रोतहरू हेर्नुहोस्।',
            'studio_home_legacy' => 'Legacy All-in-One Studio',
            'studio_home_legacy_desc' => 'पुरानो compatibility all-in-one workbench खोल्नुहोस्।',
            'open' => 'खोल्नुहोस्',
            'studio_home_status_enabled' => 'सक्रिय',
            'studio_home_status_disabled' => 'निष्क्रिय',
            'studio_home_status_disabled_preview_skeleton' => 'Preview skeleton only / disabled',
        ],
    ];

    $set = isset($dict[$lang]) && is_array($dict[$lang]) ? $dict[$lang] : $dict['en'];
    return (string)($set[$key] ?? ($dict['en'][$key] ?? $key));
};

$groups = [
    'architecture' => [
        'title_key' => 'group_architecture',
        'desc_key' => 'group_architecture_desc',
    ],
    'composition' => [
        'title_key' => 'group_composition',
        'desc_key' => 'group_composition_desc',
    ],
    'data_logic' => [
        'title_key' => 'group_data_logic',
        'desc_key' => 'group_data_logic_desc',
    ],
    'design_content' => [
        'title_key' => 'group_design_content',
        'desc_key' => 'group_design_content_desc',
    ],
    'governance' => [
        'title_key' => 'group_governance',
        'desc_key' => 'group_governance_desc',
    ],
    'workflow' => [
        'title_key' => 'group_workflow',
        'desc_key' => 'group_workflow_desc',
    ],
    'migration' => [
        'title_key' => 'group_migration',
        'desc_key' => 'group_migration_desc',
    ],
];

$availableCount = 0;
$limitedCount = 0;
$disabledCount = 0;
foreach ($studioHomeCards as $card) {
    $isEnabled = !array_key_exists('is_enabled', $card) || !empty($card['is_enabled']);
    $statusKey = trim((string)($card['status_key'] ?? 'studio_home_status_enabled'));
    if (!$isEnabled) {
        $disabledCount++;
    } elseif ($statusKey !== 'studio_home_status_enabled') {
        $limitedCount++;
    } else {
        $availableCount++;
    }
}
?>
<?php $studioCssVersion = (string)(@filemtime(APP_ROOT . '/apps/Studio/styles/gui_studio.css') ?: '1'); ?>
<link rel="stylesheet" href="/assets/apps/studio/styles/gui_studio.css?v=<?= e($studioCssVersion) ?>">
<section class="gui-studio gs-home">
  <header class="gs-home-hero">
    <div class="gs-home-hero-copy">
      <span class="gs-home-eyebrow"><?= e($sh('eyebrow')) ?></span>
      <h2><?= e($sh('title')) ?></h2>
      <p class="gs-home-lead"><?= e($sh('subtitle')) ?></p>
      <p class="gs-home-governance"><?= e($sh('hero_note')) ?></p>
    </div>
    <dl class="gs-home-summary" aria-label="<?= e($sh('safe_label')) ?>">
      <div>
        <dt><?= e($sh('summary_tools')) ?></dt>
        <dd><?= count($studioHomeCards) ?></dd>
      </div>
      <div>
        <dt><?= e($sh('summary_available')) ?></dt>
        <dd><?= $availableCount ?></dd>
      </div>
      <div>
        <dt><?= e($sh('summary_limited')) ?></dt>
        <dd><?= $limitedCount ?></dd>
      </div>
      <div>
        <dt><?= e($sh('summary_disabled')) ?></dt>
        <dd><?= $disabledCount ?></dd>
      </div>
      <div class="gs-mode-toggle-wrap">
        <fieldset class="gs-mode-toggle" role="radiogroup" aria-label="Display mode">
          <label class="gs-mode-option is-active" data-mode="simple">
            <input type="radio" name="gs-display-mode" value="simple" checked>
            <?= e($sh('mode_simple')) ?>
          </label>
          <label class="gs-mode-option" data-mode="detailed">
            <input type="radio" name="gs-display-mode" value="detailed">
            <?= e($sh('mode_detailed')) ?>
          </label>
        </fieldset>
      </div>
    </dl>
  </header>

  <details class="gs-quickstart" open>
    <summary><span><?= e($sh('quickstart_title')) ?></span><span class="gs-quickstart-desc"><?= e($sh('quickstart_desc')) ?></span></summary>
    <div class="gs-quickstart-grid">
      <a href="/apps/studio/tools/customization-studio/design-system/tokens" class="gs-quickstart-card" data-task="colors">
        <span class="gs-quickstart-icon gs-qi-colors"></span>
        <strong><?= e($sh('quickstart_colors')) ?></strong>
        <span><?= e($sh('quickstart_colors_desc')) ?></span>
      </a>
      <a href="/apps/studio/tools/localization-studio" class="gs-quickstart-card" data-task="translate">
        <span class="gs-quickstart-icon gs-qi-translate"></span>
        <strong><?= e($sh('quickstart_translate')) ?></strong>
        <span><?= e($sh('quickstart_translate_desc')) ?></span>
      </a>
      <a href="/apps/studio/tools/label-designer" class="gs-quickstart-card" data-task="label">
        <span class="gs-quickstart-icon gs-qi-label"></span>
        <strong><?= e($sh('quickstart_label')) ?></strong>
        <span><?= e($sh('quickstart_label_desc')) ?></span>
      </a>
      <a href="/apps/studio/tools/customization-studio/diagnose/style-compliance" class="gs-quickstart-card" data-task="compliance">
        <span class="gs-quickstart-icon gs-qi-compliance"></span>
        <strong><?= e($sh('quickstart_compliance')) ?></strong>
        <span><?= e($sh('quickstart_compliance_desc')) ?></span>
      </a>
      <a href="/apps/studio/tools/customization-studio" class="gs-quickstart-card" data-task="customize">
        <span class="gs-quickstart-icon gs-qi-customize"></span>
        <strong><?= e($sh('quickstart_customize')) ?></strong>
        <span><?= e($sh('quickstart_customize_desc')) ?></span>
      </a>
      <a href="/apps/studio/tools/customization-studio/diagnose/theme-doctor" class="gs-quickstart-card" data-task="theme">
        <span class="gs-quickstart-icon gs-qi-theme"></span>
        <strong><?= e($sh('quickstart_theme')) ?></strong>
        <span><?= e($sh('quickstart_theme_desc')) ?></span>
      </a>
    </div>
  </details>

  <nav class="gs-home-flow" aria-label="<?= e($sh('workflow_label')) ?>">
    <span class="gs-home-flow-label"><?= e($sh('workflow_label')) ?></span>
    <a href="/apps/studio/workflow/analyze"><span>1</span><?= e($sh('workflow_analyze_short')) ?></a>
    <i aria-hidden="true"></i>
    <a href="/apps/studio/workflow/changes"><span>2</span><?= e($sh('workflow_changes_short')) ?></a>
    <i aria-hidden="true"></i>
    <a href="/apps/studio/workflow/apply"><span>3</span><?= e($sh('workflow_apply_short')) ?></a>
  </nav>

  <div class="gs-home-sections">
    <?php foreach ($groups as $groupKey => $group): ?>
      <?php
        $groupCards = [];
        foreach ($studioHomeCards as $card) {
            if ((string)($card['home_group'] ?? '') === $groupKey) {
                $groupCards[] = $card;
            }
        }
        if ($groupCards === []) {
            continue;
        }
      ?>
      <section class="gs-home-section" data-studio-home-group="<?= e($groupKey) ?>">
        <div class="gs-home-section-heading">
          <div>
            <h3><?= e($sh($group['title_key'])) ?></h3>
            <p><?= e($sh($group['desc_key'])) ?></p>
          </div>
          <span><?= count($groupCards) ?></span>
        </div>
        <div class="gs-home-card-grid">
          <?php foreach ($groupCards as $card): ?>
            <?php
              $cardId = trim((string)($card['id'] ?? ''));
              $nameKey = trim((string)($card['name_key'] ?? ''));
              $descKey = trim((string)($card['desc_key'] ?? ''));
              $displayName = trim((string)($card['display_name'] ?? ''));
              $description = trim((string)($card['description'] ?? ''));
              $href = trim((string)($card['href'] ?? ''));
              $isEnabled = !array_key_exists('is_enabled', $card) || !empty($card['is_enabled']);
              $statusKey = trim((string)($card['status_key'] ?? 'studio_home_status_enabled'));
              $statusTone = !$isEnabled ? 'disabled' : ($statusKey === 'studio_home_status_enabled' ? 'available' : 'limited');
              $friendlyKey = 'status_friendly_enabled';
              if ($statusKey === 'studio_home_status_inspection_only' || $statusKey === 'studio_home_status_disabled_preview_skeleton') {
                  $friendlyKey = 'status_friendly_inspection';
              } elseif ($statusKey === 'studio_home_status_placeholder') {
                  $friendlyKey = 'status_friendly_placeholder';
              } elseif ($statusKey === 'studio_home_status_migrating') {
                  $friendlyKey = 'status_friendly_migrating';
              } elseif ($statusKey === 'studio_home_status_disabled') {
                  $friendlyKey = 'status_friendly_disabled';
              }
            ?>
            <?php if ($nameKey !== '' || $displayName !== ''): ?>
              <?php if ($href !== '' && $isEnabled): ?>
              <a class="gs-home-card" href="<?= e($href) ?>" data-card-id="<?= e($cardId) ?>" data-status-tone="<?= e($statusTone) ?>">
              <?php else: ?>
              <div class="gs-home-card" aria-disabled="true" data-card-id="<?= e($cardId) ?>" data-status-tone="<?= e($statusTone) ?>">
              <?php endif; ?>
                <div class="gs-home-card-topline">
                  <span class="gs-home-card-mark" aria-hidden="true"></span>
                  <span class="gs-home-status gs-home-status-tech"><?= e($sh($statusKey)) ?></span>
                  <span class="gs-home-status gs-home-status-friendly"><?= e($sh($friendlyKey)) ?></span>
                </div>
                <h4><?= e($displayName !== '' ? $displayName : $sh($nameKey)) ?></h4>
                <p><?= e($description !== '' ? $description : $sh($descKey)) ?></p>
                <span class="gs-home-card-action">
                  <?= e($sh($isEnabled ? 'open_tool' : 'unavailable_tool')) ?>
                  <?php if ($isEnabled): ?><b aria-hidden="true">→</b><?php endif; ?>
                </span>
                <?php if ($href !== ''): ?>
                <details class="gs-card-advanced">
                  <summary><?= e($sh('card_advanced_label')) ?></summary>
                  <dl class="gs-card-advanced-grid">
                    <dt>ID</dt><dd><?= e($cardId) ?></dd>
                    <dt>Route</dt><dd><?= e($href) ?></dd>
                    <dt>Status</dt><dd><?= e($sh($statusKey)) ?></dd>
                  </dl>
                </details>
                <?php endif; ?>
              <?php if ($href !== '' && $isEnabled): ?>
              </a>
              <?php else: ?>
              </div>
              <?php endif; ?>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
  </div>
</section>
<script>
(function(){
  var section = document.querySelector('.gs-home');
  if (!section) return;
  var toggle = section.querySelector('.gs-mode-toggle');
  if (!toggle) return;
  var stored = localStorage.getItem('gs-display-mode') || 'simple';
  var setMode = function(mode) {
    var opts = toggle.querySelectorAll('.gs-mode-option');
    for (var i = 0; i < opts.length; i++) {
      var opt = opts[i];
      var isActive = opt.getAttribute('data-mode') === mode;
      opt.classList.toggle('is-active', isActive);
      if (isActive) opt.querySelector('input').checked = true;
    }
    section.classList.toggle('gs-simple-mode', mode === 'simple');
    section.classList.toggle('gs-detailed-mode', mode === 'detailed');
    localStorage.setItem('gs-display-mode', mode);
  };
  setMode(stored);
  toggle.addEventListener('change', function(e) {
    if (e.target && e.target.name === 'gs-display-mode') {
      setMode(e.target.value);
    }
  });
})();
</script>

<?php
$landingModel = isset($customizationStudioLandingModel) && is_array($customizationStudioLandingModel)
    ? $customizationStudioLandingModel
    : [];
$subtools = isset($landingModel['subtools']) && is_array($landingModel['subtools'])
    ? array_values(array_filter($landingModel['subtools'], 'is_array'))
    : [];
$styleTools = isset($landingModel['style_tools']) && is_array($landingModel['style_tools'])
    ? array_values(array_filter($landingModel['style_tools'], 'is_array'))
    : [];

$cs = static function (string $key): string {
    $lang = function_exists('current_lang') ? current_lang() : 'en';
    $dict = [
        'en' => [
            'title' => 'Customization Studio',
            'description' => 'Gateway to CSS token editing, style compliance scanning, theme diagnostics, visual customization, and special-effects handoff. Also previews future socket, component, layout, motion, chart, diagram, and print/export styling tools.',
            'status' => 'Active gateway — sub-tools govern their own capability status',
            'safe_note' => 'Active gateway — sub-tools govern their own capability status',
            'open' => 'Preview areas',
            'open_subtool' => 'Open disabled preview',
            'disabled' => 'Disabled placeholder',
            'read_only_note' => 'This gateway page performs no edits, draft saves, apply actions, registry writes, Shell connection, or runtime style consumption. Sub-tools have individual capability statuses.',
            'subtool_special_effects' => 'Special Effects',
            'subtool_special_effects_desc' => 'Read-only registry, readiness, and handoff queue for optional visual effects.',
            'subtool_visual_customizer' => 'Visual Customizer',
            'subtool_visual_customizer_desc' => 'Disabled preview skeleton for future visual style inspection.',
            'subtool_theme_manager' => 'Theme Manager',
            'subtool_theme_manager_desc' => 'Placeholder for future governed theme draft previews.',
            'subtool_component_style_editor' => 'Component Style Editor',
            'subtool_component_style_editor_desc' => 'Placeholder for future component socket previews.',
            'subtool_layout_style_editor' => 'Layout Style Editor',
            'subtool_layout_style_editor_desc' => 'Placeholder for future layout socket previews.',
            'subtool_motion_style_editor' => 'Motion Style Editor',
            'subtool_motion_style_editor_desc' => 'Placeholder for future motion and transform previews.',
            'subtool_visualization_style_editor' => 'Visualization Style Editor',
            'subtool_visualization_style_editor_desc' => 'Placeholder for future chart and diagram styling previews.',
            'subtool_print_style_editor' => 'Print Style Editor',
            'subtool_print_style_editor_desc' => 'Placeholder for future print and export styling previews.',
            'subtool_advanced_css_tool' => 'Advanced CSS Tool',
            'subtool_advanced_css_tool_desc' => 'Placeholder for future technical CSS review mode.',
            'style_tools_section_title' => 'Available Style Tools',
            'style_tools_section_desc' => 'Open linking cards to CSS and styling tools available across Studio.',
            'style_tools_group_theme_system' => 'Theme System',
            'style_tools_group_css_authoring' => 'CSS Authoring & Inspection',
            'style_tools_group_visual_customization' => 'Visual Customization',
            'style_tool_theme_doctor' => 'Theme Doctor',
            'style_tool_theme_doctor_desc' => 'Check theme runtime, compiled assets, selectors, fallback availability, and theme health.',
            'style_tool_style_compliance' => 'Style Compliance',
            'style_tool_style_compliance_desc' => 'Inspect CSS and PHP style surfaces, classify token usage and literal styling as compliant, non-compliant, or repair-ready. Read-only.',
            'style_tool_css_token_editor' => 'CSS Token Editor',
            'style_tool_css_token_editor_desc' => 'Manage CSS token definitions and token contracts.',
            'style_tool_token_impact_explorer' => 'Token Impact Explorer',
            'style_tool_token_impact_explorer_desc' => 'Inspect token usage and the impact of token changes.',
            'style_tool_css_live_editor' => 'CSS Live Editor',
            'style_tool_css_live_editor_desc' => 'Safely preview and experiment with CSS changes.',
            'style_tool_css_selector_tool' => 'CSS Selector Tool',
            'style_tool_css_selector_tool_desc' => 'Inspect selectors, ownership, overrides, and cascade behavior.',
            'style_tool_visual_customizer' => 'Visual Customizer',
            'style_tool_visual_customizer_desc' => 'Inspect approved shell-style sockets and visual customization surfaces.',
            'style_tool_special_effects' => 'Special Effects',
            'style_tool_special_effects_desc' => 'Inspect optional glass, blur, glow, motion, and filter effects as a separate read-only runtime layer.',
            'style_tools_open_tool' => 'Open tool',
            'style_tools_status_readonly' => 'Read-only',
            'style_tools_status_available' => 'Available',
            'style_tools_status_governed' => 'Governed draft',
            'style_tools_status_planned' => 'Planned',
            'style_tools_status_disabled' => 'Disabled',
            'future_areas_title' => 'Future customization areas',
        ],
        'ja' => [
            'title' => 'Customization Studio',
            'description' => 'CSS トークン編集、スタイル準拠スキャン、テーマ診断、ビジュアルカスタマイズ、特殊効果ハンドオフへのゲートウェイ。将来のソケット、コンポーネント、レイアウト、モーション、チャート、図、印刷/エクスポートスタイリングツールもプレビューします。',
            'status' => 'アクティブゲートウェイ — サブツールが自身の機能ステータスを管理します',
            'safe_note' => 'アクティブゲートウェイ — サブツールが自身の機能ステータスを管理します',
            'open' => 'Preview areas',
            'open_subtool' => 'Open disabled preview',
            'disabled' => 'Disabled placeholder',
            'read_only_note' => 'このゲートウェイページでは編集、下書き保存、適用アクション、レジストリ書き込み、Shell接続、ランタイムスタイル消費は行われません。サブツールは個別の機能ステータスを持ちます。',
            'subtool_special_effects' => 'Special Effects',
            'subtool_special_effects_desc' => '任意の視覚効果向けの読み取り専用レジストリ、準備状況、引き継ぎキューです。',
            'subtool_visual_customizer' => 'Visual Customizer',
            'subtool_visual_customizer_desc' => 'Disabled preview skeleton for future visual style inspection.',
            'subtool_theme_manager' => 'Theme Manager',
            'subtool_theme_manager_desc' => 'Placeholder for future governed theme draft previews.',
            'subtool_component_style_editor' => 'Component Style Editor',
            'subtool_component_style_editor_desc' => 'Placeholder for future component socket previews.',
            'subtool_layout_style_editor' => 'Layout Style Editor',
            'subtool_layout_style_editor_desc' => 'Placeholder for future layout socket previews.',
            'subtool_motion_style_editor' => 'Motion Style Editor',
            'subtool_motion_style_editor_desc' => 'Placeholder for future motion and transform previews.',
            'subtool_visualization_style_editor' => 'Visualization Style Editor',
            'subtool_visualization_style_editor_desc' => 'Placeholder for future chart and diagram styling previews.',
            'subtool_print_style_editor' => 'Print Style Editor',
            'subtool_print_style_editor_desc' => 'Placeholder for future print and export styling previews.',
            'subtool_advanced_css_tool' => 'Advanced CSS Tool',
            'subtool_advanced_css_tool_desc' => 'Placeholder for future technical CSS review mode.',
            'style_tools_section_title' => '利用可能なスタイルツール',
            'style_tools_section_desc' => 'Studio 全体で利用可能な CSS およびスタイリングツールへのリンクカードを開きます。',
            'style_tools_group_theme_system' => 'テーマシステム',
            'style_tools_group_css_authoring' => 'CSS 作成・検査',
            'style_tools_group_visual_customization' => 'ビジュアルカスタマイズ',
            'style_tool_theme_doctor' => 'Theme Doctor',
            'style_tool_theme_doctor_desc' => 'テーマのランタイム、コンパイル済みアセット、セレクタ、フォールバック、テーマ健全性を確認します。',
            'style_tool_style_compliance' => 'Style Compliance',
            'style_tool_style_compliance_desc' => 'CSS および PHP スタイルサーフェスを検査し、トークン使用とリテラルスタイルを準拠/非準拠/修復準備完了として分類します。読み取り専用。',
            'style_tool_css_token_editor' => 'CSS トークンエディター',
            'style_tool_css_token_editor_desc' => 'CSS トークン定義とトークンコントラクトを管理します。',
            'style_tool_token_impact_explorer' => 'Token Impact Explorer',
            'style_tool_token_impact_explorer_desc' => 'トークンの使用状況と変更の影響を検査します。',
            'style_tool_css_live_editor' => 'CSS ライブエディター',
            'style_tool_css_live_editor_desc' => 'CSS 変更を安全にプレビューおよび実験します。',
            'style_tool_css_selector_tool' => 'CSS セレクターツール',
            'style_tool_css_selector_tool_desc' => 'セレクター、所有権、オーバーライド、カスケード動作を検査します。',
            'style_tool_visual_customizer' => 'Visual Customizer',
            'style_tool_visual_customizer_desc' => '承認済みシェルスタイルソケットとビジュアルカスタマイズ面を検査します。',
            'style_tool_special_effects' => 'Special Effects',
            'style_tool_special_effects_desc' => '任意の glass、blur、glow、motion、filter 効果を独立した読み取り専用ランタイムレイヤーとして検査します。',
            'style_tools_open_tool' => 'ツールを開く',
            'style_tools_status_readonly' => '読み取り専用',
            'style_tools_status_available' => '利用可能',
            'style_tools_status_governed' => '統制下',
            'style_tools_status_planned' => '計画中',
            'style_tools_status_disabled' => '無効',
            'future_areas_title' => '将来のカスタマイズ領域',
        ],
        'ne' => [
            'title' => 'Customization Studio',
            'description' => 'CSS टोकन सम्पादन, स्टाइल अनुपालन स्क्यानिङ, थिम निदान, दृश्य अनुकूलन, र विशेष प्रभाव ह्यान्डअफको प्रवेशद्वार। भविष्यका सकेट, कम्पोनेन्ट, लेआउट, मोशन, चार्ट, रेखाचित्र र मुद्रण/निर्यात स्टाइलिङ उपकरणहरू पनि पूर्वावलोकन गर्दछ।',
            'status' => 'सक्रिय प्रवेशद्वार — उप-उपकरणहरूले आफ्नो क्षमता स्थिति आफैं व्यवस्थापन गर्छन्',
            'safe_note' => 'सक्रिय प्रवेशद्वार — उप-उपकरणहरूले आफ्नो क्षमता स्थिति आफैं व्यवस्थापन गर्छन्',
            'open' => 'Preview areas',
            'open_subtool' => 'Open disabled preview',
            'disabled' => 'Disabled placeholder',
            'read_only_note' => 'यो प्रवेशद्वार पृष्ठले सम्पादन, मस्यौदा बचत, लागू कार्य, रजिस्ट्री लेखन, Shell जडान, वा रनटाइम शैली उपभोग गर्दैन। उप-उपकरणहरूसँग व्यक्तिगत क्षमता स्थितिहरू छन्।',
            'subtool_special_effects' => 'Special Effects',
            'subtool_special_effects_desc' => 'वैकल्पिक visual effects का लागि read-only registry, readiness, र handoff queue।',
            'subtool_visual_customizer' => 'Visual Customizer',
            'subtool_visual_customizer_desc' => 'Disabled preview skeleton for future visual style inspection.',
            'subtool_theme_manager' => 'Theme Manager',
            'subtool_theme_manager_desc' => 'Placeholder for future governed theme draft previews.',
            'subtool_component_style_editor' => 'Component Style Editor',
            'subtool_component_style_editor_desc' => 'Placeholder for future component socket previews.',
            'subtool_layout_style_editor' => 'Layout Style Editor',
            'subtool_layout_style_editor_desc' => 'Placeholder for future layout socket previews.',
            'subtool_motion_style_editor' => 'Motion Style Editor',
            'subtool_motion_style_editor_desc' => 'Placeholder for future motion and transform previews.',
            'subtool_visualization_style_editor' => 'Visualization Style Editor',
            'subtool_visualization_style_editor_desc' => 'Placeholder for future chart and diagram styling previews.',
            'subtool_print_style_editor' => 'Print Style Editor',
            'subtool_print_style_editor_desc' => 'Placeholder for future print and export styling previews.',
            'subtool_advanced_css_tool' => 'Advanced CSS Tool',
            'subtool_advanced_css_tool_desc' => 'Placeholder for future technical CSS review mode.',
            'style_tools_section_title' => 'उपलब्ध स्टाइल उपकरणहरू',
            'style_tools_section_desc' => 'Studio मा उपलब्ध CSS र स्टाइलिङ उपकरणहरूमा जडान कार्डहरू खोल्नुहोस्।',
            'style_tools_group_theme_system' => 'थिम प्रणाली',
            'style_tools_group_css_authoring' => 'CSS लेखन र निरीक्षण',
            'style_tools_group_visual_customization' => 'दृश्य अनुकूलन',
            'style_tool_theme_doctor' => 'Theme Doctor',
            'style_tool_theme_doctor_desc' => 'थिम runtime, compiled assets, selectors, fallback उपलब्धता, र थिम स्वास्थ्य जाँच गर्नुहोस्।',
            'style_tool_style_compliance' => 'Style Compliance',
            'style_tool_style_compliance_desc' => 'CSS र PHP स्टाइल सतहहरू निरीक्षण गर्नुहोस्, टोकन प्रयोग र शाब्दिक स्टाइलिङलाई अनुरूप/गैर-अनुरूप/मर्मत-तयार रूपमा वर्गीकरण गर्नुहोस्। पढ्न-मात्र।',
            'style_tool_css_token_editor' => 'CSS टोकन सम्पादक',
            'style_tool_css_token_editor_desc' => 'CSS टोकन परिभाषा र टोकन सम्झौताहरू व्यवस्थापन गर्नुहोस्।',
            'style_tool_token_impact_explorer' => 'Token Impact Explorer',
            'style_tool_token_impact_explorer_desc' => 'टोकन प्रयोग र टोकन परिवर्तनको प्रभाव निरीक्षण गर्नुहोस्।',
            'style_tool_css_live_editor' => 'CSS लाइभ सम्पादक',
            'style_tool_css_live_editor_desc' => 'CSS परिवर्तनहरू सुरक्षित रूपमा पूर्वावलोकन र प्रयोग गर्नुहोस्।',
            'style_tool_css_selector_tool' => 'CSS चयनकर्ता उपकरण',
            'style_tool_css_selector_tool_desc' => 'चयनकर्ता, स्वामित्व, ओभरराइड र क्यास्केड व्यवहार निरीक्षण गर्नुहोस्।',
            'style_tool_visual_customizer' => 'Visual Customizer',
            'style_tool_visual_customizer_desc' => 'स्वीकृत शेल-शैली सकेट र दृश्य अनुकूलन सतहहरू निरीक्षण गर्नुहोस्।',
            'style_tool_special_effects' => 'Special Effects',
            'style_tool_special_effects_desc' => 'वैकल्पिक glass, blur, glow, motion, र filter effects लाई छुट्टै read-only runtime layer का रूपमा निरीक्षण गर्नुहोस्।',
            'style_tools_open_tool' => 'उपकरण खोल्नुहोस्',
            'style_tools_status_readonly' => 'पढ्न-मात्र',
            'style_tools_status_available' => 'उपलब्ध',
            'style_tools_status_governed' => 'नियन्त्रित मस्यौदा',
            'style_tools_status_planned' => 'योजनाबद्ध',
            'style_tools_status_disabled' => 'असक्षम',
            'future_areas_title' => 'भविष्यका अनुकूलन क्षेत्रहरू',
        ],
    ];

    $set = isset($dict[$lang]) && is_array($dict[$lang]) ? $dict[$lang] : $dict['en'];
    return (string)($set[$key] ?? ($dict['en'][$key] ?? $key));
};

$groupOrder = ['theme_system', 'css_authoring', 'visual_customization'];
$groupedTools = [];
foreach ($groupOrder as $g) {
    $groupedTools[$g] = [];
}
foreach ($styleTools as $tool) {
    $g = (string)($tool['group'] ?? '');
    if ($g !== '' && isset($groupedTools[$g])) {
        $groupedTools[$g][] = $tool;
    }
}
?>
<link rel="stylesheet" href="/assets/apps/studio/styles/gui_studio.css">
<style>
<?php require __DIR__ . '/../assets/visual-customizer.css'; ?>
</style>
<section class="gui-studio cs-entry">
  <div class="gs-head">
    <h2><?= e($cs('title')) ?></h2>
    <p class="muted"><?= e($cs('description')) ?></p>
  </div>

  <?php if ($groupedTools !== []): ?>
  <section class="gs-studio-tools-panel" style="margin-top: 0.55rem;">
    <div class="gs-studio-tools-heading">
      <div>
        <h4 class="gs-studio-tools-title"><?= e($cs('style_tools_section_title')) ?></h4>
        <p class="gs-studio-tools-helper"><?= e($cs('style_tools_section_desc')) ?></p>
      </div>
    </div>
    <div class="gs-studio-tools-layout">
      <div class="gs-studio-tools-grid">
        <?php foreach ($groupOrder as $groupKey): ?>
          <?php $tools = $groupedTools[$groupKey] ?? []; if ($tools === []) { continue; } ?>
          <dl class="gs-studio-tools-group">
            <dt>
              <span><?= e($cs('style_tools_group_' . $groupKey)) ?></span>
              <span class="gs-studio-tools-group-count"><?= count($tools) ?></span>
            </dt>
            <dd class="gs-studio-tools-cards">
              <?php foreach ($tools as $tool): ?>
                <?php
                  $href = trim((string)($tool['href'] ?? ''));
                  $nameKey = trim((string)($tool['name_key'] ?? ''));
                  $descKey = trim((string)($tool['desc_key'] ?? ''));
                  $statusKey = trim((string)($tool['status_key'] ?? ''));
                ?>
                <a class="gs-studio-tool-card" href="<?= e($href) ?>">
                  <div class="gs-studio-tool-card-title-row">
                    <span class="gs-studio-tool-card-title"><?= e($cs($nameKey)) ?></span>
                    <?php if ($statusKey !== ''): ?>
                      <span class="gs-studio-tools-status"><?= e($cs($statusKey)) ?></span>
                    <?php endif; ?>
                  </div>
                  <p class="gs-studio-tool-card-purpose"><?= e($cs($descKey)) ?></p>
                  <span class="gs-studio-tool-card-link-hint"><?= e($cs('style_tools_open_tool')) ?></span>
                </a>
              <?php endforeach; ?>
            </dd>
          </dl>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <section class="gs-studio-tools-panel">
    <div class="gs-studio-tools-heading cs-entry__heading">
      <div>
        <h4 class="gs-studio-tools-title"><?= e($cs('title')) ?></h4>
        <p class="gs-studio-tools-helper"><?= e($cs('safe_note')) ?></p>
        <p class="cs-entry__note"><?= e($cs('read_only_note')) ?></p>
      </div>
      <span class="gs-studio-tools-status cs-entry__status"><?= e($cs('status')) ?></span>
    </div>
    <div class="gs-studio-tools-layout">
      <div class="gs-studio-tools-grid">
        <dl class="gs-studio-tools-group">
          <dt>
            <span><?= e($cs('open')) ?></span>
            <span class="gs-studio-tools-group-count"><?= count($subtools) ?></span>
          </dt>
          <dd class="gs-studio-tools-cards cs-entry__cards">
            <?php foreach ($subtools as $subtool): ?>
              <?php
                $titleKey = trim((string)($subtool['title_key'] ?? ''));
                $descriptionKey = trim((string)($subtool['description_key'] ?? ''));
                $href = trim((string)($subtool['href'] ?? ''));
                $isEnabled = !empty($subtool['is_enabled']) && $href !== '';
              ?>
              <?php if ($isEnabled): ?>
                <a class="gs-studio-tool-card cs-entry__card cs-entry__card--preview" href="<?= e($href) ?>">
                  <div class="gs-studio-tool-card-title-row">
                    <span class="gs-studio-tool-card-title"><?= e($cs($titleKey)) ?></span>
                    <span class="gs-studio-tools-status"><?= e($cs('status')) ?></span>
                  </div>
                  <p class="gs-studio-tool-card-purpose"><?= e($cs($descriptionKey)) ?></p>
                  <span class="cs-entry__action"><?= e($cs('open_subtool')) ?></span>
                </a>
              <?php endif; ?>
            <?php endforeach; ?>
          </dd>
        </dl>
        <details style="margin-top: 0.35rem; font-size: 0.78rem;">
          <summary style="cursor: pointer; color: var(--muted); font-weight: 600; text-transform: uppercase;"><?= e($cs('future_areas_title')) ?></summary>
          <dl class="gs-studio-tools-group" style="margin-top: 0.45rem;">
            <dd class="gs-studio-tools-cards cs-entry__cards">
              <?php foreach ($subtools as $subtool): ?>
                <?php
                  $titleKey = trim((string)($subtool['title_key'] ?? ''));
                  $descriptionKey = trim((string)($subtool['description_key'] ?? ''));
                  $href = trim((string)($subtool['href'] ?? ''));
                  $isEnabled = !empty($subtool['is_enabled']) && $href !== '';
                ?>
                <?php if (!$isEnabled): ?>
                  <div class="gs-studio-tool-card cs-entry__card cs-entry__card--disabled" aria-disabled="true">
                    <div class="gs-studio-tool-card-title-row">
                      <span class="gs-studio-tool-card-title"><?= e($cs($titleKey)) ?></span>
                      <span class="gs-studio-tools-status"><?= e($cs('disabled')) ?></span>
                    </div>
                    <p class="gs-studio-tool-card-purpose"><?= e($cs($descriptionKey)) ?></p>
                  </div>
                <?php endif; ?>
              <?php endforeach; ?>
            </dd>
          </dl>
        </details>
      </div>
    </div>
  </section>
</section>

<?php
$workflowStage = trim((string)($workflowStage ?? 'analyze'));

$wp = static function (string $key): string {
    $lang = function_exists('current_lang') ? current_lang() : 'en';
    $dict = [
        'en' => [
            'title' => 'Workflow Stage',
            'subtitle' => 'Shared lifecycle entry surface for Studio workflow stages.',
            'analyze' => 'Analyze',
            'changes' => 'Changes / Diff',
            'apply' => 'Apply',
            'readonly' => 'Read-only stage surface',
            'open_legacy' => 'Open Legacy All-in-One Workbench',
            'open_home' => 'Back to Studio Home',
            'hint' => 'Use legacy workbench for current end-to-end behavior while tool-based pages are being migrated.',
        ],
        'ja' => [
            'title' => 'Workflow Stage',
            'subtitle' => 'Studio ワークフローステージの共有ライフサイクル入口です。',
            'analyze' => 'Analyze',
            'changes' => 'Changes / Diff',
            'apply' => 'Apply',
            'readonly' => '読み取り専用ステージ画面',
            'open_legacy' => 'Legacy All-in-One Workbench を開く',
            'open_home' => 'Studio Home に戻る',
            'hint' => 'ツール別ページ移行中は、既存の一連動作には legacy workbench を使用してください。',
        ],
        'ne' => [
            'title' => 'Workflow Stage',
            'subtitle' => 'Studio workflow चरणहरूको साझा lifecycle entry सतह।',
            'analyze' => 'Analyze',
            'changes' => 'Changes / Diff',
            'apply' => 'Apply',
            'readonly' => 'रीड-अनली चरण सतह',
            'open_legacy' => 'Legacy All-in-One Workbench खोल्नुहोस्',
            'open_home' => 'Studio Home मा फर्कनुहोस्',
            'hint' => 'Tool-based page migration भइरहँदा end-to-end व्यवहारका लागि legacy workbench प्रयोग गर्नुहोस्।',
        ],
    ];

    $set = isset($dict[$lang]) && is_array($dict[$lang]) ? $dict[$lang] : $dict['en'];
    return (string)($set[$key] ?? ($dict['en'][$key] ?? $key));
};

$stageKey = in_array($workflowStage, ['analyze', 'changes', 'apply'], true) ? $workflowStage : 'analyze';
?>
<link rel="stylesheet" href="/assets/apps/studio/styles/gui_studio.css">
<section class="gui-studio">
  <div class="gs-head">
    <h2><?= e($wp('title')) ?>: <?= e($wp($stageKey)) ?></h2>
    <p class="muted"><?= e($wp('subtitle')) ?></p>
  </div>

  <section class="gs-studio-tools-panel">
    <div class="gs-studio-tools-heading">
      <div>
        <h4 class="gs-studio-tools-title"><?= e($wp($stageKey)) ?></h4>
        <p class="gs-studio-tools-helper"><?= e($wp('readonly')) ?></p>
      </div>
    </div>
    <div class="gs-studio-tools-layout">
      <div class="gs-studio-tools-grid">
        <dl class="gs-studio-tools-group">
          <dt><span><?= e($wp('hint')) ?></span></dt>
          <dd class="gs-studio-tools-cards">
            <a class="gs-studio-tool-card" href="/apps/studio/legacy">
              <div class="gs-studio-tool-card-title-row">
                <span class="gs-studio-tool-card-title"><?= e($wp('open_legacy')) ?></span>
              </div>
            </a>
          </dd>
        </dl>
      </div>
    </div>
  </section>
</section>

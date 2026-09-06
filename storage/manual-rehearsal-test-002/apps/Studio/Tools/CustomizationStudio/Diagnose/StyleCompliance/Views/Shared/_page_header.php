<?php
declare(strict_types=1);

$studioGuiCssVersion = (string)(@filemtime(APP_ROOT . '/apps/Studio/styles/gui_studio.css') ?: '1');
$styleComplianceCssVersion = (string)(@filemtime(APP_ROOT . '/apps/Studio/styles/style-compliance.css') ?: '1');
?>
<link rel="stylesheet" href="/assets/apps/studio/styles/gui_studio.css?v=<?= e($studioGuiCssVersion) ?>">
<link rel="stylesheet" href="/assets/apps/studio/styles/style-compliance.css?v=<?= e($styleComplianceCssVersion) ?>">

<header class="sc-hero gs-tool-header">
    <h2 class="gs-tool-header-title"><span class="sc-title-mark" aria-hidden="true">SC</span><a href="<?= e($selfPath) ?>"><?= e($sc('page_title')) ?></a> <span class="sc-hero-badge"><?= e($sc($guardedExecutionEnabled ? 'guarded_apply_badge' : 'read_only_badge')) ?></span></h2>
    <nav class="sc-hero-actions gs-tool-header-actions" aria-label="Style Compliance shortcuts">
        <a href="#scInvestigationDetails">Evidence</a>
        <a href="/apps/studio/tools/customization-studio/diagnose/style-compliance?scope=all_owners&amp;workspace=shell-inventory">Foundation Inventory</a>
        <a href="#scInvestigationDetails">Help</a>
    </nav>
</header>

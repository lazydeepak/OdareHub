<?php
declare(strict_types=1);

$moduleTitle = trim((string)($moduleTitle ?? 'SBAIO'));
$moduleDescription = trim((string)($moduleDescription ?? ''));
$suiteActions = is_array($suiteActions ?? null) ? $suiteActions : [];
$suiteLinks = is_array($suiteLinks ?? null) ? $suiteLinks : [];
?>
<div class="section-head module-page-intro">
  <div class="module-header-info">
    <h2 class="u-style-1169661891"><?= e($moduleTitle) ?></h2>
    <?php if ($moduleDescription !== ''): ?>
      <div class="muted u-style-fe7b4979fe"><?= e($moduleDescription) ?></div>
    <?php endif; ?>
  </div>
  <div class="module-header-actions">
    <?php foreach ($suiteActions as $action): ?>
      <a class="btn" href="<?= e((string)($action['url'] ?? '/apps/sbaio')) ?>"><?= e((string)($action['label'] ?? '')) ?></a>
    <?php endforeach; ?>
  </div>
</div>
<?php if ($suiteLinks !== []): ?>
  <div class="row u-style-567eded8a2">
    <?php foreach ($suiteLinks as $link): ?>
      <span class="pill"><?= e((string)($link['label'] ?? '')) ?><?php if (!empty($link['value'])): ?>: <?= e((string)$link['value']) ?><?php endif; ?></span>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

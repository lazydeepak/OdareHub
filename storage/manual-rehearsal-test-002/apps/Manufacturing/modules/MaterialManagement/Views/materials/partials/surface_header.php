<?php
declare(strict_types=1);

$surfaceKey = (string)($surfaceKey ?? 'dashboard');
$surfaceTitle = (string)($surfaceTitle ?? 'Material Management');
$surfaceSummary = (string)($surfaceSummary ?? '');
$v1NavItems = is_array($v1NavItems ?? null) ? $v1NavItems : [];
$flashOk = trim((string)($flashOk ?? ''));
$flashErr = trim((string)($flashErr ?? ''));
?>
<div class="ui-block">
  <section class="card">
    <div class="hero-kicker">Material Management</div>
    <div class="section-head">
      <div class="hero-headline">
        <h2 class="u-style-1169661891"><?= e($surfaceTitle) ?></h2>
        <?php if ($surfaceSummary !== ''): ?>
          <div class="muted u-mt-8"><?= e($surfaceSummary) ?></div>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($surfaceSummary !== ''): ?>
    <?php endif; ?>
    <div class="row u-mt-10">
      <?php foreach ($v1NavItems as $item): ?>
        <?php $itemKey = (string)($item['key'] ?? ''); ?>
        <a class="btn <?= $itemKey === $surfaceKey ? 'ok' : '' ?>" href="<?= e((string)($item['url'] ?? '/apps/manufacturing/materials')) ?>"><?= e((string)($item['label'] ?? 'Material Surface')) ?></a>
      <?php endforeach; ?>
    </div>
  </section>

  <?php if ($flashOk !== ''): ?>
    <section class="card ops-section-card ops-accent-green"><div class="ui-block"><?= e($flashOk) ?></div></section>
  <?php endif; ?>
  <?php if ($flashErr !== ''): ?>
    <section class="card ops-section-card ops-accent-red"><div class="ui-block"><?= e($flashErr) ?></div></section>
  <?php endif; ?>

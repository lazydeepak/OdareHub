<?php
declare(strict_types=1);

$path = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$path = is_string($path) ? $path : '';

$items = [
    ['url' => '/apps/manufacturing', 'label' => (string)t('mfg.nav.portal')],
    ['url' => '/apps/manufacturing/demands', 'label' => (string)t('mfg.nav.demands')],
    ['url' => '/apps/manufacturing/production-queue', 'label' => (string)t('mfg.nav.production_queue')],
    ['url' => '/apps/manufacturing/qc-queue', 'label' => (string)t('mfg.nav.qc_queue')],
    ['url' => '/apps/manufacturing/assembly-queue', 'label' => (string)t('mfg.nav.assembly_queue')],
    ['url' => '/apps/manufacturing/dispatch-ops', 'label' => (string)t('mfg.nav.dispatch_ops')],
    ['url' => '/apps/manufacturing/stage-board', 'label' => (string)t('mfg.nav.stage_board')],
    ['url' => '/apps/manufacturing/coverage', 'label' => (string)t('mfg.nav.coverage')],
    ['url' => '/apps/manufacturing/products', 'label' => (string)t('mfg.nav.products')],
    ['url' => '/apps/manufacturing/materials', 'label' => (string)t('mfg.nav.materials')],
    ['url' => '/apps/manufacturing/imports', 'label' => (string)t('mfg.nav.imports')],
    ['url' => '/apps/manufacturing/exports', 'label' => (string)t('mfg.nav.exports')],
    ['url' => '/apps/manufacturing/restores', 'label' => (string)t('mfg.nav.restores')],
];
?>

<div class="card mfg-admin-nav-band" role="navigation" aria-label="<?= e(t('mfg.nav.aria_label')) ?>">
  <div class="mfg-admin-nav-head">
    <h2><?= e(t('mfg.nav.title')) ?></h2>
    <div class="muted"><?= e(t('mfg.nav.subtitle')) ?></div>
  </div>
  <div class="mfg-admin-nav-links">
    <?php foreach ($items as $item): ?>
      <?php
      $url = (string)($item['url'] ?? '');
      $isActive = $path === $url || ($url !== '/apps/manufacturing' && str_starts_with($path, $url . '/'));
      ?>
      <a class="btn<?= $isActive ? ' ok' : '' ?>" href="<?= e($url) ?>"><?= e((string)($item['label'] ?? '')) ?></a>
    <?php endforeach; ?>
  </div>
</div>

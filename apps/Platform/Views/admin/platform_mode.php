<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$currentMode = (string)($currentMode ?? '');
$availableModes = is_array($availableModes ?? null) ? $availableModes : [];
$modeResult = strtolower(trim((string)($_GET['mode_result'] ?? '')));
$modeResult = in_array($modeResult, ['updated', 'csrf_invalid', 'invalid_mode', 'locked', 'failed', 'method_not_allowed'], true) ? $modeResult : '';
$platformModeReturnTo = '/admin/system-tools/platform-mode';
?>
<section class="card">
  <div class="module-header">
    <div class="module-header-info">
      <div class="mapping-label"><?= e(t('admin.platform_mode.candidate')) ?></div>
      <h2><?= e(t('admin.platform_mode.title')) ?></h2>
      <p class="muted"><?= e(t('admin.platform_mode.description')) ?></p>
    </div>
  </div>
</section>

<?php if ($modeResult !== ''): ?>
  <div class="card"><strong class="<?= $modeResult === 'updated' ? 'u-style-f80bc18837' : 'u-style-7d46839b06' ?>"><?= e(t('admin.platform_mode.result.' . $modeResult)) ?></strong></div>
<?php endif; ?>

<?php require APP_ROOT . '/plugins/Base/Views/ops/platform_mode_selector.php'; ?>

<section class="card">
  <h3><?= e(t('admin.platform_mode.parity_title')) ?></h3>
  <p class="muted"><?= e(t('admin.platform_mode.parity_description')) ?></p>
</section>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

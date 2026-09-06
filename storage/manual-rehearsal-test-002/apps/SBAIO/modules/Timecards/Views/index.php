<?php
declare(strict_types=1);

$isPrintMode = (bool)($isPrintMode ?? false);
?>
<?php if (!$isPrintMode): ?>
  <?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php endif; ?>
<?php require __DIR__ . '/_report.php'; ?>
<?php if (!$isPrintMode): ?>
  <?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
<?php endif; ?>

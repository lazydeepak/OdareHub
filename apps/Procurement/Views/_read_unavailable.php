<?php
declare(strict_types=1);
?>
<section class="card card--error" data-procurement-read-state="unavailable">
  <h2><?= e(t('proc.read.unavailable_title')) ?></h2>
  <p><?= e(t('proc.read.unavailable_message')) ?></p>
  <p class="muted"><?= e(t('proc.read.no_changes')) ?></p>
</section>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

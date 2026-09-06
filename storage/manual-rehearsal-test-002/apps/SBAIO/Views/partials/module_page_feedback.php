<?php
declare(strict_types=1);

$message = trim((string)($message ?? ''));
$error = trim((string)($error ?? ''));
?>
<?php if ($message !== '' || $error !== ''): ?>
  <section class="card u-style-2b583d7389">
    <?php if ($message !== ''): ?>
      <div class="notice success"><?= e($message) ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
      <div class="notice error"><?= e($error) ?></div>
    <?php endif; ?>
  </section>
<?php endif; ?>

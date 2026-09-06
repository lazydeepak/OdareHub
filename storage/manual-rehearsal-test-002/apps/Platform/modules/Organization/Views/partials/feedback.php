<?php if (!empty($flash ?? '')): ?>
  <div class="card notice-ok"><?= e((string)$flash) ?></div>
<?php endif; ?>
<?php if (!empty($error ?? '')): ?>
  <div class="card notice-err"><?= e((string)$error) ?></div>
<?php endif; ?>

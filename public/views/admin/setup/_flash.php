<?php
$flash = is_array($flash ?? null) ? $flash : [];
$flashOk = trim((string)($flash['ok'] ?? ''));
$flashErr = trim((string)($flash['err'] ?? ''));
$flashResult = $flash['result'] ?? null;
?>
<?php if ($flashOk !== ''): ?>
<div class="card notice-ok"><?= e($flashOk) ?></div>
<?php endif; ?>

<?php if ($flashErr !== ''): ?>
<div class="card notice-err"><?= e($flashErr) ?></div>
<?php endif; ?>

<?php if (is_array($flashResult)): ?>
<div class="card">
  <h3 style="margin:0 0 8px">Last Run</h3>
  <pre style="white-space:pre-wrap;margin:0"><?= e(json_encode($flashResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}') ?></pre>
</div>
<?php endif; ?>

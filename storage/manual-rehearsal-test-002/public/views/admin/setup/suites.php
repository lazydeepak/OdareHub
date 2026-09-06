<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$suiteCards = is_array($suiteCards ?? null) ? $suiteCards : [];
?>
<?php $setupNavCurrent = 'suites'; require __DIR__ . '/_nav.php'; ?>
<?php require __DIR__ . '/_flash.php'; ?>

<div class="card">
  <h2 style="margin:0 0 8px">Suite Setup</h2>
  <div class="muted">Install status, child module status, setup profiles, configure/defaults, and verify/health are kept together here at the suite level.</div>
</div>

<div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(300px,1fr))">
  <?php foreach ($suiteCards as $suite): ?>
    <div class="card" style="margin:0">
      <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start">
        <div>
          <h3 style="margin:0 0 6px"><?= e((string)($suite['label'] ?? '')) ?></h3>
          <div class="muted">Registry: <?= e((string)($suite['registry_status'] ?? 'uploaded')) ?></div>
        </div>
        <span class="pill" style="<?= e((string)($suite['status_tone'] ?? '')) ?>"><?= e((string)($suite['status_label'] ?? '')) ?></span>
      </div>
      <div style="margin-top:10px">Next: <?= e((string)($suite['next_action'] ?? '')) ?></div>
      <div class="muted" style="margin-top:8px">Configured profile: <?= e((string)($suite['configured_profile'] ?: 'not yet configured')) ?></div>
      <div class="muted" style="margin-top:6px">Modules active: <?= e((string)($suite['module_summary']['active'] ?? 0)) ?>/<?= e((string)($suite['module_summary']['total'] ?? 0)) ?></div>
      <div class="muted" style="margin-top:6px">Missing tables: <?= e((string)($suite['verification']['missing_tables'] ?? 0)) ?> · Failed hooks: <?= e((string)($suite['verification']['failed_hooks'] ?? 0)) ?> · Schema gaps: <?= e((string)($suite['verification']['schema_gaps'] ?? 0)) ?></div>
      <div style="margin-top:12px">
        <a class="btn ok" href="/admin/setup/suites/detail?suite_key=<?= urlencode((string)($suite['app_key'] ?? '')) ?>">Open Suite</a>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

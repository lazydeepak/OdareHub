<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$completion = is_array($completion ?? null) ? $completion : [];
?>
<?php $setupNavCurrent = 'complete'; require __DIR__ . '/_nav.php'; ?>
<?php require __DIR__ . '/_flash.php'; ?>

<div class="card">
  <h2 style="margin:0 0 8px">Setup Complete</h2>
  <div class="muted">The platform is ready for first use. Review the summary below, check any warnings, and then open the areas you need next.</div>
</div>

<div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
  <div class="card" style="margin:0">
    <strong>Installed suites</strong>
    <div class="muted"><?= e((string)count((array)($completion['installed_suites'] ?? []))) ?></div>
  </div>
  <div class="card" style="margin:0">
    <strong>Active modules</strong>
    <div class="muted"><?= e((string)count((array)($completion['active_modules'] ?? []))) ?></div>
  </div>
  <div class="card" style="margin:0">
    <strong>Warnings</strong>
    <div class="muted"><?= e((string)count((array)($completion['warnings'] ?? []))) ?></div>
  </div>
  <div class="card" style="margin:0">
    <strong>Errors</strong>
    <div class="muted"><?= e((string)count((array)($completion['errors'] ?? []))) ?></div>
  </div>
</div>

<div class="card">
  <h3 style="margin:0 0 8px">Installed Suites</h3>
  <?php if (empty($completion['installed_suites'])): ?>
    <div class="muted">No suites are installed yet. You can still use the Core platform and add suites later.</div>
  <?php else: ?>
    <div style="display:grid;gap:8px">
      <?php foreach ((array)($completion['installed_suites'] ?? []) as $suite): ?>
        <div class="card" style="margin:0">
          <strong><?= e((string)($suite['label'] ?? $suite['app_key'] ?? 'Suite')) ?></strong>
          <div class="muted" style="margin-top:6px">Status: <?= e((string)($suite['status_label'] ?? '')) ?> · Profile: <?= e((string)($suite['configured_profile'] ?: 'default')) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h3 style="margin:0 0 8px">Active Modules</h3>
  <?php if (empty($completion['active_modules'])): ?>
    <div class="muted">No active modules were detected yet.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Suite</th>
            <th>Module</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ((array)($completion['active_modules'] ?? []) as $module): ?>
            <tr>
              <td><?= e((string)($module['suite'] ?? '')) ?></td>
              <td><?= e((string)($module['name'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if (!empty($completion['warnings']) || !empty($completion['errors'])): ?>
<div class="card">
  <h3 style="margin:0 0 8px">Warnings To Review</h3>
  <?php foreach ((array)($completion['warnings'] ?? []) as $warning): ?>
    <div class="muted"><?= e((string)$warning) ?></div>
  <?php endforeach; ?>
  <?php foreach ((array)($completion['errors'] ?? []) as $error): ?>
    <div style="color:#ffb3b3"><?= e((string)$error) ?></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">
  <h3 style="margin:0 0 8px">Recommended Next Actions</h3>
  <div style="display:grid;gap:6px">
    <?php foreach ((array)($completion['recommended_next_actions'] ?? []) as $item): ?>
      <div><?= e((string)$item) ?></div>
    <?php endforeach; ?>
  </div>
</div>

<div class="card">
  <h3 style="margin:0 0 8px">Quick Links</h3>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php foreach ((array)($completion['links'] ?? []) as $link): ?>
      <a class="btn ok" href="<?= e((string)($link['url'] ?? '#')) ?>"><?= e((string)($link['label'] ?? 'Open')) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

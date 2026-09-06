<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$app_key = (string)($_GET['app'] ?? 'manufacturing');
if (!in_array($app_key, ['manufacturing', 'sbaio'], true)) {
    $app_key = 'manufacturing';
}

$app = is_array($app_data ?? null) ? $app_data : [];
$modules = is_array($modules_data ?? null) ? $modules_data : [];
$history = is_array($history_data ?? null) ? $history_data : [];
$csrf = (string)($csrf ?? '');

$app_label = $app_key === 'manufacturing' ? 'Manufacturing' : 'SBAIO';
?>

<div class="card">
  <div class="u-style-8a84800a49">
    <div class="ui-block">
      <h2 class="u-style-ad7f18b19e"><?= e(t('admin.system_tools.card.app_management')) ?></h2>
      <div class="muted"><?= e(t('admin.system_tools.card.app_management_desc')) ?></div>
    </div>
    <div class="ui-block">
      <a class="btn" href="/admin/system-tools"><?= e(t('admin.system_tools.nav.back')) ?></a>
    </div>
  </div>
</div>

<!-- App Selector -->
<div class="card">
  <div class="u-style-3de8f987ba">
    <?php foreach (['manufacturing' => t('app.manufacturing'), 'sbaio' => t('app.sbaio')] as $key => $label): ?>
      <a
        class="btn <?= $app_key === $key ? 'ok' : '' ?>"
        href="?app=<?= e($key) ?>"
        <?= $app_key === $key ? 'aria-current="page"' : '' ?>
      >
        <?= e((string)$label) ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<?php if (($flash_ok ?? '') !== ''): ?>
  <div class="card u-style-e640db1c6b"><?= e((string)$flash_ok) ?></div>
<?php endif; ?>
<?php if (($flash_err ?? '') !== ''): ?>
  <div class="card u-style-c2fa10bd43"><?= e((string)$flash_err) ?></div>
<?php endif; ?>

<!-- EXPORT SECTION -->
<div class="card">
  <h3 class="u-style-df671843ff"><?= e(t('admin.app_management.export_app')) ?></h3>
  <div class="muted u-style-761d3addb2"><?= e(t('admin.app_management.export_app_desc')) ?></div>
  <form class="u-style-97ded659e4" method="post" action="/admin/system-tools/app-management/export-app">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="app" value="<?= e($app_key) ?>">
    <label>
      <div class="muted u-style-4e420aff3f"><?= e(t('admin.app_management.export_type')) ?></div>
      <select name="export_type">
        <?php foreach ((array)($app['export_types'] ?? []) as $key => $label): ?>
          <option value="<?= e((string)$key) ?>"><?= e((string)$label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button class="btn ok" type="submit"><?= e(t('admin.app_management.create_app_export')) ?></button>
  </form>
</div>

<!-- MODULE EXPORTS SECTION -->
<div class="card">
  <h3 class="u-style-df671843ff"><?= e(t('admin.app_management.export_module')) ?></h3>
  <div class="muted u-style-761d3addb2"><?= e(t('admin.app_management.export_module_desc')) ?></div>
  <div class="u-style-e36f93d58f">
    <?php foreach ($modules as $module): ?>
      <form method="post" action="/admin/system-tools/app-management/export-module" class="card u-style-1169661891">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="app" value="<?= e($app_key) ?>">
        <input type="hidden" name="module_name" value="<?= e((string)($module['module_name'] ?? '')) ?>">
        <strong><?= e((string)($module['display_name'] ?? '')) ?></strong>
        <div class="muted u-style-c1fafc235b"><?= e(t('admin.app_management.status')) ?>: <?= e((string)($module['status'] ?? 'missing')) ?></div>
        <div class="muted u-style-761d3addb2"><?= e(t('admin.app_management.tables')) ?>: <?= e(implode(', ', array_map('strval', (array)($module['required_tables'] ?? [])))) ?></div>
        <label>
          <div class="muted u-style-4e420aff3f"><?= e(t('admin.app_management.export_type')) ?></div>
          <select name="export_type">
            <?php foreach ((array)($module['export_types'] ?? []) as $key => $label): ?>
              <option value="<?= e((string)$key) ?>"><?= e((string)$label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <button class="btn u-style-d2c171b18b" type="submit"><?= e(t('admin.app_management.create_module_export')) ?></button>
      </form>
    <?php endforeach; ?>
  </div>
</div>

<!-- IMPORT SECTION -->
<div class="card">
  <h3 class="u-style-df671843ff"><?= e(t('admin.app_management.import')) ?></h3>
  <div class="muted u-style-761d3addb2"><?= e(t('admin.app_management.import_desc')) ?></div>
  <p class="u-style-1169661891"><a class="btn" href="/apps/<?= $app_key === 'sbaio' ? 'sbaio' : 'manufacturing' ?>/imports"><?= e(t('admin.app_management.preview_import')) ?></a></p>
</div>

<!-- RESTORE SECTION -->
<div class="card">
  <h3 class="u-style-df671843ff"><?= e(t('admin.app_management.restore')) ?></h3>
  <div class="muted u-style-761d3addb2"><?= e(t('admin.app_management.restore_desc')) ?></div>
  <p class="u-style-1169661891"><a class="btn" href="/apps/<?= $app_key === 'sbaio' ? 'sbaio' : 'manufacturing' ?>/restores"><?= e(t('admin.app_management.preview_restore')) ?></a></p>
</div>

<!-- EXPORT HISTORY SECTION -->
<div class="card">
  <h3 class="u-style-df671843ff"><?= e(t('admin.app_management.history')) ?></h3>
  <?php if (count($history) === 0): ?>
    <div class="muted"><?= e(t('admin.app_management.no_history')) ?></div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th><?= e(t('admin.app_management.when')) ?></th>
            <th><?= e(t('admin.app_management.target')) ?></th>
            <th><?= e(t('admin.app_management.type')) ?></th>
            <th><?= e(t('admin.app_management.status')) ?></th>
            <th><?= e(t('admin.app_management.file')) ?></th>
            <th><?= e(t('admin.app_management.user')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($history as $row): ?>
            <tr>
              <td><?= e((string)($row['created_at'] ?? '')) ?></td>
              <td><?= e((string)($row['target_type'] ?? '')) ?>: <?= e((string)($row['target_key'] ?? '')) ?></td>
              <td><?= e((string)($row['export_type'] ?? '')) ?></td>
              <td><?= e((string)($row['status'] ?? '')) ?></td>
              <td>
                <?php if (!empty($row['file_name'])): ?>
                  <a href="/admin/system-tools/app-management/download?id=<?= (int)($row['id'] ?? 0) ?>"><?= e((string)($row['file_name'] ?? 'download')) ?></a>
                <?php else: ?>
                  <span class="muted">n/a</span>
                <?php endif; ?>
              </td>
              <td><?= e((string)($row['created_by'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

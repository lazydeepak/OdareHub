<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$suite = is_array($suite ?? null) ? $suite : [];
$modules = is_array($modules ?? null) ? $modules : [];
$history = is_array($history ?? null) ? $history : [];
$csrf = (string)($csrf ?? '');
?>

<div class="card">
  <div class="u-style-8a84800a49">
    <div class="ui-block">
      <h2 class="u-style-ad7f18b19e"><?= e(t('sbaio.exports.title')) ?></h2>
      <div class="muted"><?= e(t('sbaio.exports.description')) ?></div>
    </div>
    <div class="u-style-3de8f987ba">
      <a class="btn" href="/apps/sbaio"><?= e(t('sbaio.common.back_to_sbaio')) ?></a>
      <a class="btn" href="/apps/sbaio/imports"><?= e(t('sbaio.dashboard.legacy_imports')) ?></a>
    </div>
  </div>
</div>

<?php if (($flash_ok ?? '') !== ''): ?>
  <div class="card u-style-e640db1c6b"><?= e((string)$flash_ok) ?></div>
<?php endif; ?>
<?php if (($flash_err ?? '') !== ''): ?>
  <div class="card u-style-c2fa10bd43"><?= e((string)$flash_err) ?></div>
<?php endif; ?>

<div class="card">
  <h3 class="u-style-df671843ff"><?= e(t('sbaio.exports.suite')) ?></h3>
  <div class="muted u-style-761d3addb2"><?= e(t('sbaio.exports.suite_desc')) ?></div>
  <form class="u-style-97ded659e4" method="post" action="/apps/sbaio/exports/suite">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <label>
      <div class="muted u-style-4e420aff3f"><?= e(t('sbaio.exports.export_type')) ?></div>
      <select name="export_type">
        <?php foreach ((array)($suite['export_types'] ?? []) as $key => $label): ?>
          <option value="<?= e((string)$key) ?>"><?= e((string)$label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button class="btn ok" type="submit"><?= e(t('sbaio.exports.create_suite')) ?></button>
  </form>
</div>

<div class="card">
  <h3 class="u-style-df671843ff"><?= e(t('sbaio.exports.module')) ?></h3>
  <div class="muted u-style-761d3addb2"><?= e(t('sbaio.exports.module_desc')) ?></div>
  <div class="u-style-e36f93d58f">
    <?php foreach ($modules as $module): ?>
      <form method="post" action="/apps/sbaio/exports/module" class="card u-style-1169661891">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="module_name" value="<?= e((string)($module['module_name'] ?? '')) ?>">
        <strong><?= e((string)($module['display_name'] ?? '')) ?></strong>
        <div class="muted u-style-c1fafc235b"><?= e(t('common.status')) ?>: <?= e((string)($module['status'] ?? 'missing')) ?></div>
        <div class="muted u-style-761d3addb2"><?= e(t('sbaio.exports.tables')) ?>: <?= e(implode(', ', array_map('strval', (array)($module['required_tables'] ?? [])))) ?></div>
        <label>
          <div class="muted u-style-4e420aff3f"><?= e(t('sbaio.exports.export_type')) ?></div>
          <select name="export_type">
            <?php foreach ((array)($module['export_types'] ?? []) as $key => $label): ?>
              <option value="<?= e((string)$key) ?>"><?= e((string)$label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <button class="btn u-style-d2c171b18b" type="submit"><?= e(t('sbaio.exports.create_module')) ?></button>
      </form>
    <?php endforeach; ?>
  </div>
</div>

<div class="card">
  <h3 class="u-style-df671843ff"><?= e(t('sbaio.exports.history')) ?></h3>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>When</th>
          <th>Target</th>
          <th>Type</th>
          <th>Status</th>
          <th>File</th>
          <th>User</th>
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
                <a href="/apps/sbaio/exports/download?id=<?= (int)($row['id'] ?? 0) ?>"><?= e((string)($row['file_name'] ?? 'download')) ?></a>
              <?php else: ?>
                <span class="muted"><?= e(t('sbaio.common.na')) ?></span>
              <?php endif; ?>
            </td>
            <td><?= e((string)($row['created_by'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

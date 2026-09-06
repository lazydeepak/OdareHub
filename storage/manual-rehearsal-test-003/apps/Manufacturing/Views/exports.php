<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$suite = is_array($suite ?? null) ? $suite : [];
$modules = is_array($modules ?? null) ? $modules : [];
$history = is_array($history ?? null) ? $history : [];
$csrf = (string)($csrf ?? '');
$flashOk = (string)($flash_ok ?? '');
$flashErr = (string)($flash_err ?? '');
?>

<div class="card">
  <div class="mfg-page-header">
    <div class="ui-block">
      <h2 class="mfg-page-title"><?= e((string)t('mfg.io.exports.title')) ?></h2>
      <div class="muted"><?= e((string)t('mfg.io.exports.subtitle')) ?></div>
    </div>
    <div class="mfg-page-header-links">
      <a class="btn" href="/apps/manufacturing"><?= e((string)t('mfg.io.link.back_manufacturing')) ?></a>
      <a class="btn" href="/apps/manufacturing/imports"><?= e((string)t('mfg.io.link.imports')) ?></a>
      <a class="btn" href="/apps/manufacturing/restores"><?= e((string)t('mfg.io.link.restores')) ?></a>
    </div>
  </div>
</div>

<?php if ($flashOk !== ''): ?><div class="card dsp-flash-ok"><?= e($flashOk) ?></div><?php endif; ?>
<?php if ($flashErr !== ''): ?><div class="card dsp-flash-err"><?= e($flashErr) ?></div><?php endif; ?>

<div class="card">
  <div class="section-head"><h2><?= e((string)t('mfg.io.exports.suite')) ?></h2></div>
  <form method="post" action="/apps/manufacturing/exports/suite" class="mfg-filter-row">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <div class="ui-block">
      <label class="muted mfg-filter-label"><?= e((string)t('mfg.io.field.export_type')) ?></label>
      <select name="export_type">
        <?php foreach ((array)($suite['export_types'] ?? []) as $key => $label): ?>
          <option value="<?= e((string)$key) ?>"><?= e((string)$label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="row">
      <button class="btn ok" type="submit"><?= e((string)t('mfg.io.action.create_suite_export')) ?></button>
    </div>
  </form>
</div>

<div class="card">
  <div class="section-head"><h2><?= e((string)t('mfg.io.exports.modules')) ?></h2></div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th><?= e((string)t('common.module')) ?></th>
          <th><?= e((string)t('common.status')) ?></th>
          <th><?= e((string)t('mfg.io.col.tables')) ?></th>
          <th><?= e((string)t('mfg.io.field.export_type')) ?></th>
          <th><?= e((string)t('common.actions')) ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($modules as $module): ?>
        <tr>
          <td><?= e((string)($module['display_name'] ?? '')) ?></td>
          <td><?= e((string)($module['status'] ?? '')) ?></td>
          <td><?= e(implode(', ', array_map('strval', (array)($module['required_tables'] ?? [])))) ?></td>
          <td>
            <form method="post" action="/apps/manufacturing/exports/module" class="row u-style-482ada1d83">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="module_name" value="<?= e((string)($module['module_name'] ?? '')) ?>">
              <select name="export_type">
                <?php foreach ((array)($module['export_types'] ?? []) as $key => $label): ?>
                  <option value="<?= e((string)$key) ?>"><?= e((string)$label) ?></option>
                <?php endforeach; ?>
              </select>
          </td>
          <td>
              <button class="btn" type="submit"><?= e((string)t('mfg.io.action.create_module_export')) ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($modules === []): ?><tr><td colspan="5" class="muted"><?= e((string)t('mfg.io.empty.modules')) ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="section-head"><h2><?= e((string)t('mfg.io.history.exports')) ?></h2></div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th><?= e((string)t('common.when')) ?></th>
          <th><?= e((string)t('mfg.io.col.target')) ?></th>
          <th><?= e((string)t('common.type')) ?></th>
          <th><?= e((string)t('common.status')) ?></th>
          <th><?= e((string)t('mfg.io.col.file')) ?></th>
          <th><?= e((string)t('mfg.io.col.user')) ?></th>
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
              <a href="/apps/manufacturing/exports/download?id=<?= (int)($row['id'] ?? 0) ?>"><?= e((string)($row['file_name'] ?? '')) ?></a>
            <?php else: ?>
              <span class="muted"><?= e((string)t('common.none')) ?></span>
            <?php endif; ?>
          </td>
          <td><?= e((string)($row['created_by'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($history === []): ?><tr><td colspan="6" class="muted"><?= e((string)t('mfg.io.empty.history')) ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
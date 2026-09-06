<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2><?= e(t('module.part_machine_map.title')) ?></h2>
      <div class="muted"><?= e(t('module.part_machine_map.subtitle')) ?></div>
    </div>
    <div class="module-header-actions">
      <a class="btn ok" href="/part-machine-map/add"><?= e(t('module.part_machine_map.add')) ?></a>
    </div>
  </div>
</div>

<?php if (!empty($flash ?? '')): ?><div class="card notice-ok"><?= e((string)$flash) ?></div><?php endif; ?>
<?php if (!empty($error ?? '')): ?><div class="card notice-err"><?= e((string)$error) ?></div><?php endif; ?>

<div class="card">
  <form method="get" action="/part-machine-map" class="module-filterbar">
    <div class="module-filter-field-wide">
      <label class="module-filter-label"><?= e(t('common.search')) ?></label>
      <input class="input" name="q" value="<?= e((string)$q) ?>" placeholder="<?= e(t('module.part_machine_map.search_placeholder')) ?>">
    </div>
    <div class="module-filter-field">
      <label class="module-filter-label"><?= e(t('common.status')) ?></label>
      <select name="active">
        <option value="all" <?= $active === 'all' ? 'selected' : '' ?>><?= e(t('common.all')) ?></option>
        <option value="1" <?= $active === '1' ? 'selected' : '' ?>><?= e(t('common.active')) ?></option>
        <option value="0" <?= $active === '0' ? 'selected' : '' ?>><?= e(t('common.inactive')) ?></option>
      </select>
    </div>
    <div class="module-filterbar-actions">
      <button class="btn" type="submit"><?= e(t('common.filter')) ?></button>
      <a class="btn" href="/part-machine-map"><?= e(t('common.reset')) ?></a>
    </div>
  </form>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
      <tr>
        <th><?= e(t('common.part')) ?></th>
        <th><?= e(t('common.part_number')) ?></th>
        <th><?= e(t('module.machines.machine_no')) ?></th>
        <th><?= e(t('module.machines.machine_name')) ?></th>
        <th><?= e(t('common.status')) ?></th>
        <th><?= e(t('common.notes')) ?></th>
        <th><?= e(t('common.updated')) ?></th>
        <th><?= e(t('common.actions')) ?></th>
      </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= e((string)$r['parts_name']) ?></td>
          <td><code><?= e((string)$r['parts_number']) ?></code></td>
          <td><code><?= e((string)$r['machine_no']) ?></code></td>
          <td><?= e((string)$r['machine_name']) ?></td>
          <td><?= (int)($r['is_active'] ?? 1) === 1 ? e(t('common.active')) : e(t('common.inactive')) ?></td>
          <td><?= e((string)($r['notes'] ?? '')) ?></td>
          <td class="muted"><?= e((string)($r['updated_at'] ?? '')) ?></td>
          <td>
            <div class="row">
              <a class="btn" href="/part-machine-map/edit?id=<?= (int)$r['id'] ?>"><?= e(t('common.edit')) ?></a>
              <form method="post" action="/part-machine-map/delete" onsubmit="return confirm('<?= e(t('module.part_machine_map.delete_confirm')) ?>');">
                <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn danger" type="submit"><?= e(t('common.delete')) ?></button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($rows)): ?>
        <tr>
          <td colspan="8" class="muted u-style-91a87015f4"><?= e(t('module.part_machine_map.none')) ?></td>
        </tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="muted u-style-8314a564c6">
    <?= e(localized_records_summary(count($rows))) ?>
  </div>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

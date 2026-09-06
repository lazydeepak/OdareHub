<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2><?= e(t('module.machines.title')) ?></h2>
      <div class="muted"><?= e(t('module.machines.subtitle')) ?></div>
    </div>
    <div class="module-header-actions">
      <a class="btn ok" href="/machines/add"><?= e(t('module.machines.add')) ?></a>
    </div>
  </div>
</div>

<?php if (!empty($flash ?? '')): ?><div class="card notice-ok"><?= e((string)$flash) ?></div><?php endif; ?>
<?php if (!empty($error ?? '')): ?><div class="card notice-err"><?= e((string)$error) ?></div><?php endif; ?>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
      <tr>
        <th><?= e(t('module.machines.machine_no')) ?></th>
        <th><?= e(t('module.machines.machine_name')) ?></th>
        <th><?= e(t('module.machines.machine_type')) ?></th>
        <th><?= e(t('module.machines.clamping_force_ton')) ?></th>
        <th><?= e(t('module.machines.shot_capacity_g')) ?></th>
        <th><?= e(t('module.machines.preferred_materials')) ?></th>
      </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr onclick="window.location='/machines/detail?id=<?= (int)$r['id'] ?>'" style="cursor:pointer">
          <td><a href="/machines/detail?id=<?= (int)$r['id'] ?>"><code><?= e((string)$r['machine_no']) ?></code></a></td>
          <td><?= e((string)$r['machine_name']) ?></td>
          <td><?= e((string)($r['machine_type'] ?? '')) ?></td>
          <td><?= e((string)($r['clamping_force_ton'] ?? '')) ?></td>
          <td><?= e((string)($r['shot_capacity_g'] ?? '')) ?></td>
          <td><?= e((string)($r['preferred_materials'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($rows)): ?>
        <tr>
          <td colspan="6" class="muted u-style-91a87015f4"><?= e(t('module.machines.none')) ?></td>
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

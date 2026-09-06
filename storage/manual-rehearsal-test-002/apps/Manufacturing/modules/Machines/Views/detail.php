<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<?php
$partMappings = is_array($partMappings ?? null) ? $partMappings : [];
?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2><?= e((string)($row['machine_no'] ?? '')) ?> <?= e((string)($row['machine_name'] ?? '')) ?></h2>
      <div class="muted"><?= e((string)($row['section'] ?? '')) ?><?php if (!empty($row['machine_group'])): ?> · <?= e((string)$row['machine_group']) ?><?php endif; ?><?php if (!empty($row['machine_type'])): ?> · <?= e((string)$row['machine_type']) ?><?php endif; ?></div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/machines"><?= e(t('back_to_list')) ?></a>
      <a class="btn ok" href="/machines/edit?id=<?= (int)($row['id'] ?? 0) ?>"><?= e(t('common.edit')) ?></a>
    </div>
  </div>
</div>

<?php if (!empty($flash ?? '')): ?><div class="card notice-ok"><?= e((string)$flash) ?></div><?php endif; ?>
<?php if (!empty($error ?? '')): ?><div class="card notice-err"><?= e((string)$error) ?></div><?php endif; ?>

<div class="card">
  <div class="table-wrap">
    <table>
      <tbody>
        <tr><th class="u-style-30196daabe"><?= e(t('machine_no')) ?></th><td><?= e((string)($row['machine_no'] ?? '')) ?></td></tr>
        <tr><th><?= e(t('machine_name')) ?></th><td><?= e((string)($row['machine_name'] ?? '')) ?></td></tr>
        <tr><th><?= e(t('module.machines.machine_group')) ?></th><td><?= e((string)($row['machine_group'] ?? '')) ?></td></tr>
        <tr><th><?= e(t('module.machines.machine_type')) ?></th><td><?= e((string)($row['machine_type'] ?? '')) ?></td></tr>
        <tr><th><?= e(t('module.machines.section')) ?></th><td><?= e((string)($row['section'] ?? '')) ?></td></tr>
        <tr><th><?= e(t('common.status')) ?></th><td><?= e((string)($row['status'] ?? '')) ?><?php if ((int)($row['is_active'] ?? 1) !== 1): ?> <span class="muted">(<?= e(t('common.inactive')) ?>)</span><?php endif; ?></td></tr>
        <tr><th><?= e(t('module.machines.capacity_per_hour')) ?></th><td><?= e((string)($row['capacity_per_hour'] ?? '')) ?></td></tr>
        <tr><th><?= e(t('module.machines.clamping_force_ton')) ?></th><td><?= e((string)($row['clamping_force_ton'] ?? '')) ?></td></tr>
        <tr><th><?= e(t('module.machines.shot_capacity_g')) ?></th><td><?= e((string)($row['shot_capacity_g'] ?? '')) ?></td></tr>
        <tr><th><?= e(t('module.machines.tie_bar_spacing_mm')) ?></th><td><?= e((string)($row['tie_bar_spacing_mm'] ?? '')) ?></td></tr>
        <tr><th><?= e(t('module.machines.platen_size_mm')) ?></th><td><?= e((string)($row['platen_size_mm'] ?? '')) ?></td></tr>
        <tr><th><?= e(t('module.machines.min_mold_height_mm')) ?></th><td><?= e((string)($row['min_mold_height_mm'] ?? '')) ?></td></tr>
        <tr><th><?= e(t('module.machines.max_mold_height_mm')) ?></th><td><?= e((string)($row['max_mold_height_mm'] ?? '')) ?></td></tr>
        <tr><th><?= e(t('module.machines.preferred_materials')) ?></th><td><?= e((string)($row['preferred_materials'] ?? '')) ?></td></tr>
        <tr><th><?= e(t('notes')) ?></th><td><?= nl2br(e((string)($row['notes'] ?? ''))) ?></td></tr>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h3 class="u-style-1169661891">Mapped Parts</h3>
      <div class="muted">Approved part-machine mappings linked to this machine.</div>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th><?= e(t('part')) ?></th>
          <th><?= e(t('common.status')) ?></th>
          <th><?= e(t('module.machines.capacity_per_hour')) ?></th>
          <th><?= e(t('notes')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($partMappings as $mapping): ?>
          <tr>
            <td><?= e((string)($mapping['parts_name'] ?? '')) ?> <span class="muted"><?= e((string)($mapping['parts_number'] ?? '')) ?></span></td>
            <td><?= !empty($mapping['is_active']) ? e(t('common.active')) : e(t('common.inactive')) ?></td>
            <td><?= e((string)($mapping['capacity_per_hour'] ?? $mapping['max_daily_capacity'] ?? '')) ?></td>
            <td><?= e((string)($mapping['notes'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if ($partMappings === []): ?>
          <tr><td colspan="4" class="muted u-style-91a87015f4">No part-machine mappings found for this machine.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

<?php
$adminUpgradeWorkbench = is_array($admin_upgrade_workbench ?? null) ? $admin_upgrade_workbench : [];
if ($adminUpgradeWorkbench === []) { return; }
$workbenchLinks = array_values(array_filter((array)($adminUpgradeWorkbench['links'] ?? []), 'is_array'));
$snapshotCards = array_values(array_filter((array)($adminUpgradeWorkbench['snapshot_cards'] ?? []), 'is_array'));
$snapshotEnvironment = array_values(array_filter((array)($adminUpgradeWorkbench['snapshot_environment'] ?? []), 'is_array'));
$snapshotState = (string)($adminUpgradeWorkbench['snapshot_state'] ?? 'not_applicable');
$showMigrationDetails = !empty($adminUpgradeWorkbench['show_migration_details']);
?>
<section class="card admin-upgrade-workbench" aria-labelledby="admin-upgrade-workbench-title">
  <div class="admin-upgrade-workbench-head">
    <div>
      <div class="admin-upgrade-workbench-kicker"><?= e(t((string)($adminUpgradeWorkbench['kicker_key'] ?? 'admin.platform_status.kicker'))) ?></div>
      <h2 id="admin-upgrade-workbench-title"><?= e(t((string)($adminUpgradeWorkbench['title_key'] ?? ''))) ?></h2>
      <p class="muted"><?= e(t((string)($adminUpgradeWorkbench['description_key'] ?? ''))) ?></p>
    </div>
    <span class="mapping-label"><?= e(t((string)($adminUpgradeWorkbench['status_key'] ?? ''))) ?></span>
  </div>
  <?php if ($snapshotCards !== []): ?>
    <h3><?= e(t('admin.upgrade_workbench.snapshot_title')) ?></h3>
    <p class="muted"><?= e(t('admin.upgrade_workbench.snapshot_description')) ?></p>
    <div class="coverage-kpi-grid">
      <?php foreach ($snapshotCards as $card): ?><a class="coverage-kpi dashboard-link-card" href="<?= e((string)$card['target']) ?>"><div class="muted"><?= e(t((string)$card['label_key'])) ?></div><div class="coverage-kpi-value"><?= e((string)$card['value']) ?></div><small><?= e(t('admin.upgrade_workbench.open_specialist')) ?></small></a><?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php if ($snapshotState === 'degraded'): ?>
    <div class="admin-upgrade-workbench-notice" role="status">
      <strong><?= e(t('admin.upgrade_workbench.snapshot_degraded_title')) ?></strong>
      <span class="muted"><?= e(t('admin.upgrade_workbench.snapshot_degraded_description')) ?></span>
    </div>
  <?php elseif ($snapshotState === 'ready' && $snapshotCards === []): ?>
    <div class="admin-upgrade-workbench-notice" role="status">
      <strong><?= e(t('admin.upgrade_workbench.snapshot_empty_title')) ?></strong>
      <span class="muted"><?= e(t('admin.upgrade_workbench.snapshot_empty_description')) ?></span>
    </div>
  <?php endif; ?>
  <?php if ($snapshotEnvironment !== []): ?><div class="admin-upgrade-workbench-body">
    <?php foreach ($snapshotEnvironment as $item): ?><div><strong><?= e(t((string)$item['label_key'])) ?></strong><span><?= e((string)$item['value']) ?> · <a href="<?= e((string)$item['target']) ?>"><?= e(t('common.open')) ?></a></span></div><?php endforeach; ?>
  </div><?php endif; ?>
  <div class="admin-upgrade-workbench-body">
    <?php if ($showMigrationDetails): ?>
    <div><strong><?= e(t('admin.upgrade_workbench.method_label')) ?></strong><span><?= e(t((string)($adminUpgradeWorkbench['method_key'] ?? ''))) ?></span></div>
    <div><strong><?= e(t('admin.upgrade_workbench.next_label')) ?></strong><span><?= e(t((string)($adminUpgradeWorkbench['next_key'] ?? ''))) ?></span></div>
    <div><strong><?= e(t('admin.upgrade_workbench.candidate_label')) ?></strong><span><code><?= e((string)($adminUpgradeWorkbench['candidate_id'] ?? '')) ?></code></span></div>
    <?php endif; ?>
    <?php if (isset($adminUpgradeWorkbench['mode'])): ?><div><strong><?= e(t('admin.upgrade_workbench.mode_label')) ?></strong><span><?= e(t('admin.platform_mode.mode.' . (string)$adminUpgradeWorkbench['mode'])) ?> · <?= e(t(!empty($adminUpgradeWorkbench['mode_locked']) ? 'admin.platform_mode.locked' : 'admin.platform_mode.unlocked')) ?></span></div><?php endif; ?>
    <?php if (isset($adminUpgradeWorkbench['assigned_apps_count'])): ?><div><strong><?= e(t('admin.upgrade_workbench.assigned_apps_label')) ?></strong><span><?= (int)$adminUpgradeWorkbench['assigned_apps_count'] ?></span></div><?php endif; ?>
  </div>
  <?php if ($workbenchLinks !== []): ?>
    <nav class="admin-upgrade-workbench-links" aria-label="<?= e(t('admin.upgrade_workbench.links_label')) ?>">
      <?php foreach ($workbenchLinks as $link): ?>
        <a class="btn" href="<?= e((string)($link['url'] ?? '')) ?>" data-workbench-link-kind="<?= e((string)($link['kind'] ?? 'reference')) ?>"><?= e(t((string)($link['label_key'] ?? ''))) ?></a>
      <?php endforeach; ?>
    </nav>
  <?php endif; ?>
</section>

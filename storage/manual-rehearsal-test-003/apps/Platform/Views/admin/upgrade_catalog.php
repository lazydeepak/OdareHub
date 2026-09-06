<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$candidates = array_values(array_filter((array)($upgradeCandidates ?? []), 'is_array'));
$groups = [];
$statusCounts = [];
foreach ($candidates as $candidate) {
    $groups[(string)($candidate['group'] ?? 'Other')][] = $candidate;
    $status = (string)($candidate['status'] ?? 'discovered');
    $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
}
$groupKeys = ['System Tools' => 'system_tools', 'Apps & Config' => 'apps_config', 'Governance' => 'governance', 'Developer' => 'developer', 'Compatibility' => 'compatibility', 'Planned' => 'planned'];
?>
<section class="card">
  <div class="module-header">
    <div class="module-header-info">
      <div class="mapping-label"><?= e(t('admin.upgrade_catalog.count', ['count' => count($candidates)])) ?></div>
      <h2><?= e(t('admin.upgrade_catalog.title')) ?></h2>
      <p class="muted"><?= e(t('admin.upgrade_catalog.description')) ?></p>
    </div>
  </div>
</section>
<section class="card"><div class="coverage-kpi-grid">
  <?php foreach ($statusCounts as $status => $count): ?><div class="coverage-kpi"><div class="muted"><?= e(t('admin.upgrade_catalog.status.' . $status)) ?></div><div class="coverage-kpi-value"><?= (int)$count ?></div></div><?php endforeach; ?>
</div></section>
<?php foreach ($groups as $group => $items): ?>
<section class="card">
  <div class="section-head"><h3><?= e(t('admin.upgrade_catalog.group.' . ($groupKeys[$group] ?? 'planned'))) ?></h3></div>
  <div class="dashboard-grid">
    <?php foreach ($items as $candidate): ?>
    <article class="dashboard-link-card admin-upgrade-candidate">
      <div class="dashboard-link-top"><strong><?= e(t((string)$candidate['labelKey'])) ?></strong><code><?= e((string)$candidate['id']) ?></code></div>
      <div class="muted"><?= e(t('admin.upgrade_catalog.status.' . (string)$candidate['status'])) ?></div>
      <div class="row">
        <?php if ((string)$candidate['target'] !== ''): ?><a class="btn" href="<?= e((string)$candidate['target']) ?>"><?= e(t((string)$candidate['target'] === (string)$candidate['source'] ? 'admin.upgrade_catalog.open_specialist' : 'admin.upgrade_catalog.open_target')) ?></a><?php endif; ?>
        <?php if ((string)$candidate['source'] !== (string)$candidate['target']): ?><a class="btn" href="<?= e((string)$candidate['source']) ?>"><?= e(t('admin.upgrade_catalog.open_source')) ?></a><?php endif; ?>
      </div>
      <small class="muted"><?= e(t((string)$candidate['status'] === 'promoted' ? 'admin.upgrade_catalog.promoted' : 'admin.upgrade_catalog.preserved')) ?></small>
    </article>
    <?php endforeach; ?>
  </div>
</section>
<?php endforeach; ?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

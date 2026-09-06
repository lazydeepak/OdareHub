<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$resilienceLanes = [
    ['href' => '/admin/system-tools/app-management', 'title_key' => 'admin.resilience_map.app_exports', 'desc_key' => 'admin.resilience_map.app_exports_desc'],
    ['href' => '/admin/app-manager', 'title_key' => 'admin.resilience_map.inventory_exports', 'desc_key' => 'admin.resilience_map.inventory_exports_desc'],
    ['href' => '/admin/setup/environment', 'title_key' => 'admin.resilience_map.environment', 'desc_key' => 'admin.resilience_map.environment_desc'],
    ['href' => '/admin/setup/release', 'title_key' => 'admin.resilience_map.release', 'desc_key' => 'admin.resilience_map.release_desc'],
    ['href' => '/apps/manufacturing/imports', 'title_key' => 'admin.resilience_map.manufacturing_imports', 'desc_key' => 'admin.resilience_map.manufacturing_imports_desc'],
    ['href' => '/apps/manufacturing/restores', 'title_key' => 'admin.resilience_map.manufacturing_restores', 'desc_key' => 'admin.resilience_map.manufacturing_restores_desc'],
    ['href' => '/apps/sbaio/imports', 'title_key' => 'admin.resilience_map.sbaio_imports', 'desc_key' => 'admin.resilience_map.sbaio_imports_desc'],
    ['href' => '/apps/sbaio/restores', 'title_key' => 'admin.resilience_map.sbaio_restores', 'desc_key' => 'admin.resilience_map.sbaio_restores_desc'],
];
$monitor = is_array($monitor ?? null) ? $monitor : [];
$monitorActivity = is_array($monitor['activity'] ?? null) ? $monitor['activity'] : [];
$monitorCoverage = is_array($monitor['coverage'] ?? null) ? $monitor['coverage'] : [];
$monitorSummary = is_array($monitor['summary'] ?? null) ? $monitor['summary'] : [];
?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-style-1169661891"><?= e(t('admin.resilience_map.title')) ?></h2>
      <div class="muted"><?= e(t('admin.resilience_map.description')) ?></div>
    </div>
    <div class="module-header-actions"><a class="btn" href="/admin/system-tools"><?= e(t('admin.system_tools.nav.back')) ?></a></div>
  </div>
</div>

<div class="card">
  <div class="module-header-info u-style-da12f2858b">
    <h3 class="u-style-1169661891"><?= e(t('admin.resilience_map.implemented_title')) ?></h3>
    <div class="muted"><?= e(t('admin.resilience_map.implemented_desc')) ?></div>
  </div>
  <div class="dashboard-grid">
    <?php foreach ($resilienceLanes as $lane): ?>
      <a class="dashboard-link-card" href="<?= e((string)$lane['href']) ?>">
        <div class="dashboard-link-top"><strong><?= e(t((string)$lane['title_key'])) ?></strong></div>
        <div class="muted"><?= e(t((string)$lane['desc_key'])) ?></div>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<div class="card">
  <div class="module-header-info u-style-da12f2858b">
    <h3 class="u-style-1169661891"><?= e(t('admin.resilience_map.monitoring_title')) ?></h3>
    <div class="muted"><?= e(t('admin.resilience_map.monitoring_desc')) ?></div>
  </div>
  <div class="dashboard-grid">
    <div class="dashboard-link-card">
      <div class="dashboard-link-top"><strong><?= (int)($monitorSummary['total'] ?? 0) ?></strong></div>
      <div class="muted"><?= e(t('admin.resilience_map.summary.activity')) ?></div>
    </div>
    <div class="dashboard-link-card">
      <div class="dashboard-link-top"><strong><?= (int)($monitorSummary['successful'] ?? 0) ?></strong></div>
      <div class="muted"><?= e(t('admin.resilience_map.summary.successful')) ?></div>
    </div>
    <div class="dashboard-link-card">
      <div class="dashboard-link-top"><strong><?= (int)($monitorSummary['attention'] ?? 0) ?></strong></div>
      <div class="muted"><?= e(t('admin.resilience_map.summary.attention')) ?></div>
    </div>
    <div class="dashboard-link-card">
      <div class="dashboard-link-top"><strong><?= (int)($monitor['available_source_count'] ?? 0) ?>/<?= (int)($monitor['source_count'] ?? 0) ?></strong></div>
      <div class="muted"><?= e(t('admin.resilience_map.summary.sources')) ?></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="module-header-info u-style-da12f2858b">
    <h3 class="u-style-1169661891"><?= e(t('admin.resilience_map.activity_title')) ?></h3>
    <div class="muted"><?= e(t('admin.resilience_map.activity_desc')) ?></div>
  </div>
  <?php if (!empty($monitor['degraded'])): ?>
    <div class="notice warning"><?= e(t('admin.resilience_map.degraded')) ?></div>
  <?php endif; ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th><?= e(t('admin.resilience_map.col.when')) ?></th>
          <th><?= e(t('admin.resilience_map.col.source')) ?></th>
          <th><?= e(t('admin.resilience_map.col.activity')) ?></th>
          <th><?= e(t('admin.resilience_map.col.target')) ?></th>
          <th><?= e(t('admin.resilience_map.col.status')) ?></th>
          <th><?= e(t('admin.resilience_map.col.evidence')) ?></th>
          <th><?= e(t('admin.resilience_map.col.actor')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($monitorActivity as $item): ?>
          <tr>
            <td><?= e((string)($item['occurred_at'] ?? '')) ?></td>
            <td><a href="<?= e((string)($item['owner_url'] ?? '#')) ?>"><?= e(t((string)($item['source_label_key'] ?? ''))) ?></a></td>
            <td><?= e((string)($item['operation'] ?? '')) ?></td>
            <td><?= e((string)($item['target'] ?? '')) ?></td>
            <td><?= e((string)($item['status'] ?? '')) ?></td>
            <td class="muted"><?= e((string)($item['message'] ?? '')) ?: '—' ?></td>
            <td><?= e((string)($item['actor'] ?? '')) ?: '—' ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if ($monitorActivity === []): ?>
          <tr><td colspan="7" class="muted"><?= e(t('admin.resilience_map.no_activity')) ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="module-header-info u-style-da12f2858b">
    <h3 class="u-style-1169661891"><?= e(t('admin.resilience_map.coverage_title')) ?></h3>
    <div class="muted"><?= e(t('admin.resilience_map.coverage_desc')) ?></div>
  </div>
  <div class="dashboard-grid">
    <?php foreach ($monitorCoverage as $source): ?>
      <a class="dashboard-link-card" href="<?= e((string)($source['owner_url'] ?? '#')) ?>">
        <div class="dashboard-link-top"><strong><?= e(t((string)($source['label_key'] ?? ''))) ?></strong></div>
        <div class="muted"><?= !empty($source['available']) ? e(t('admin.resilience_map.coverage_available', ['count' => (string)($source['record_count'] ?? 0)])) : e(t('admin.resilience_map.coverage_unavailable')) ?></div>
      </a>
    <?php endforeach; ?>
  </div>
  <div class="muted u-style-da12f2858b"><?= e(t('admin.resilience_map.monitoring_requirements')) ?></div>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$diagnostics = is_array($routeDiagnostics ?? null) ? $routeDiagnostics : [];
$summary = is_array($diagnostics['summary'] ?? null) ? $diagnostics['summary'] : [];
$declaredRoutes = is_array($diagnostics['declared_routes'] ?? null) ? $diagnostics['declared_routes'] : [];
$linkedRoutes = is_array($diagnostics['linked_routes'] ?? null) ? $diagnostics['linked_routes'] : [];
$routeStatusClass = static function (string $status): string {
    return match ($status) {
        'loaded' => 'route-status--ready',
        'loaded_post_only', 'legacy_fallback' => 'route-status--warning',
        'disabled_by_app_status' => 'route-status--disabled',
        default => 'route-status--error',
    };
};
$routeStatusLabel = static function (string $status): string {
    $known = ['loaded', 'loaded_post_only', 'legacy_fallback', 'disabled_by_app_status', 'declared_missing', 'broken_missing'];
    return in_array($status, $known, true) ? t('admin.routes.status.' . $status) : $status;
};
?>

<section class="card u-style-ea75cdd499">
  <details class="routes-fold" open>
    <summary class="section-head u-style-ef0b7a1148">
      <h2 class="u-style-9a23aa2d2e"><?= e(t('admin.routes.runtime_title')) ?></h2>
      <div class="muted u-style-96ad6099e2"><?= e(t('admin.routes.runtime_subtitle')) ?></div>
    </summary>
    <div class="routes-fold-content coverage-kpi-grid">
      <div class="coverage-kpi"><div class="muted"><?= e(t('admin.routes.kpi_loaded_get')) ?></div><div class="coverage-kpi-value"><?= (int)($summary['loaded_get'] ?? 0) ?></div></div>
      <div class="coverage-kpi"><div class="muted"><?= e(t('admin.routes.kpi_loaded_post')) ?></div><div class="coverage-kpi-value"><?= (int)($summary['loaded_post'] ?? 0) ?></div></div>
      <div class="coverage-kpi"><div class="muted"><?= e(t('admin.routes.kpi_declared_loaded')) ?></div><div class="coverage-kpi-value u-style-ce8274176b"><?= (int)($summary['declared_loaded'] ?? 0) ?></div></div>
      <div class="coverage-kpi"><div class="muted"><?= e(t('admin.routes.kpi_declared_post_only')) ?></div><div class="coverage-kpi-value u-style-dfa7c0aeb5"><?= (int)($summary['declared_post_only'] ?? 0) ?></div></div>
      <div class="coverage-kpi"><div class="muted"><?= e(t('admin.routes.kpi_declared_missing')) ?></div><div class="coverage-kpi-value u-style-8ce6e50b5f"><?= (int)($summary['declared_missing'] ?? 0) ?></div></div>
      <div class="coverage-kpi"><div class="muted"><?= e(t('admin.routes.kpi_linked_loaded')) ?></div><div class="coverage-kpi-value u-style-ce8274176b"><?= (int)($summary['linked_loaded'] ?? 0) ?></div></div>
      <div class="coverage-kpi"><div class="muted"><?= e(t('admin.routes.kpi_linked_broken')) ?></div><div class="coverage-kpi-value u-style-8ce6e50b5f"><?= (int)($summary['linked_broken'] ?? 0) ?></div></div>
      <div class="coverage-kpi"><div class="muted"><?= e(t('admin.routes.kpi_legacy_fallback')) ?></div><div class="coverage-kpi-value u-style-dfa7c0aeb5"><?= (int)($summary['linked_fallback'] ?? 0) ?></div></div>
      <div class="coverage-kpi"><div class="muted"><?= e(t('admin.routes.kpi_disabled_by_status')) ?></div><div class="coverage-kpi-value u-style-5cdb0ba65f"><?= (int)($summary['linked_disabled'] ?? 0) ?></div></div>
    </div>
  </details>
</section>

<section class="card">
  <details class="routes-fold">
    <summary class="section-head"><h2 class="u-style-9a23aa2d2e"><?= e(t('admin.routes.declared_title')) ?></h2></summary>
    <div class="routes-fold-content table-wrap">
      <table>
        <thead><tr><th><?= e(t('admin.routes.col_app')) ?></th><th><?= e(t('admin.routes.col_app_status')) ?></th><th><?= e(t('admin.routes.col_path')) ?></th><th><?= e(t('admin.routes.col_module')) ?></th><th><?= e(t('admin.routes.col_mapping_status')) ?></th><th><?= e(t('admin.routes.col_studio_action')) ?></th><th><?= e(t('common.status')) ?></th></tr></thead>
        <tbody>
        <?php foreach ($declaredRoutes as $row): ?>
          <?php $status = (string)($row['status'] ?? 'declared_missing'); ?>
          <?php $mappingStatus = (string)($row['studio_mapping_status'] ?? 'mapping_pending'); ?>
          <?php $studioUrl = trim((string)($row['studio_open_url'] ?? '')); ?>
          <tr>
            <td><?= e((string)($row['app_key'] ?? '')) ?></td>
            <td><?= e((string)($row['app_status'] ?? 'unknown')) ?></td>
            <td><code><?= e((string)($row['path'] ?? '/')) ?></code></td>
            <td><code><?= e((string)($row['studio_app_key'] ?? '')) ?><?= (string)($row['studio_module_key'] ?? '') !== '' ? ':' . e((string)$row['studio_module_key']) : '' ?></code></td>
            <td><span class="status-chip"><?= e(t('admin.routes.mapping_status.' . $mappingStatus)) ?></span></td>
            <td>
              <?php if ($studioUrl !== ''): ?>
                <a class="btn" href="<?= e($studioUrl) ?>"><?= e(t('admin.routes.open_in_studio')) ?></a>
              <?php else: ?>
                <span class="muted"><?= e(t('admin.routes.open_unavailable')) ?></span>
              <?php endif; ?>
            </td>
            <td class="<?= e($routeStatusClass($status)) ?>"><?= e($routeStatusLabel($status)) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </details>
</section>

<section class="card">
  <details class="routes-fold">
    <summary class="section-head"><h2 class="u-style-9a23aa2d2e"><?= e(t('admin.routes.linked_title')) ?></h2></summary>
    <div class="routes-fold-content table-wrap">
      <table>
        <thead><tr><th><?= e(t('common.owner')) ?></th><th><?= e(t('admin.routes.col_source_key')) ?></th><th><?= e(t('admin.routes.col_nav_url')) ?></th><th><?= e(t('admin.routes.col_runtime_url')) ?></th><th><?= e(t('admin.routes.col_module')) ?></th><th><?= e(t('admin.routes.col_mapping_status')) ?></th><th><?= e(t('admin.routes.col_studio_action')) ?></th><th><?= e(t('common.status')) ?></th><th><?= e(t('admin.routes.col_visible_if')) ?></th></tr></thead>
        <tbody>
        <?php foreach ($linkedRoutes as $row): ?>
          <?php $status = (string)($row['status'] ?? 'broken_missing'); ?>
          <?php $mappingStatus = (string)($row['studio_mapping_status'] ?? 'mapping_pending'); ?>
          <?php $studioUrl = trim((string)($row['studio_open_url'] ?? '')); ?>
          <tr>
            <td><?= e((string)($row['owner_type'] ?? '')) ?>:<?= e((string)($row['owner_key'] ?? '')) ?><?php if ((string)($row['owner_status'] ?? '') !== 'n/a'): ?> (<?= e((string)$row['owner_status']) ?>)<?php endif; ?></td>
            <td><?= e((string)($row['source_key'] ?? '')) ?></td>
            <td><code><?= e((string)($row['url'] ?? '/')) ?></code></td>
            <td><code><?= e((string)($row['runtime_url'] ?? '/')) ?></code></td>
            <td><code><?= e((string)($row['studio_app_key'] ?? '')) ?><?= (string)($row['studio_module_key'] ?? '') !== '' ? ':' . e((string)$row['studio_module_key']) : '' ?></code></td>
            <td><span class="status-chip"><?= e(t('admin.routes.mapping_status.' . $mappingStatus)) ?></span></td>
            <td>
              <?php if ($studioUrl !== ''): ?>
                <a class="btn" href="<?= e($studioUrl) ?>"><?= e(t('admin.routes.open_in_studio')) ?></a>
              <?php else: ?>
                <span class="muted"><?= e(t('admin.routes.open_unavailable')) ?></span>
              <?php endif; ?>
            </td>
            <td class="<?= e($routeStatusClass($status)) ?>"><?= e($routeStatusLabel($status)) ?></td>
            <td><code><?= e((string)($row['visible_if'] ?? 'always')) ?></code></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </details>
</section>

<?php foreach ($routes as $method => $map): ?>
  <section class="card">
    <details class="routes-fold">
      <summary><h3 class="u-style-9a23aa2d2e"><?= htmlspecialchars($method) ?> <?= e(t('admin.routes.loaded_routes_suffix')) ?></h3></summary>
      <div class="routes-fold-content">
        <table>
          <thead><tr><th><?= e(t('admin.routes.col_path')) ?></th></tr></thead>
          <tbody>
            <?php foreach ($map as $path => $_h): ?>
              <tr><td><code><?= htmlspecialchars($path) ?></code></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </details>
  </section>
<?php endforeach; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

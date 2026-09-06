<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$routeSummary = is_array($routeDiagnostics['summary'] ?? null) ? $routeDiagnostics['summary'] : [];
$appDiagnostics = is_array($appDiagnostics ?? null) ? $appDiagnostics : [];
$missingEn = is_array($missingEn ?? null) ? $missingEn : [];
$missingJa = is_array($missingJa ?? null) ? $missingJa : [];
$missingNe = is_array($missingNe ?? null) ? $missingNe : [];
?>

<div class="card">
  <div class="architecture-health-head"><div><h2 class="architecture-health-title"><?= e(t('admin.architecture_health.title')) ?></h2><div class="muted"><?= e(t('admin.architecture_health.description')) ?></div></div><div class="architecture-health-actions"><a class="btn" href="/admin/routes"><?= e(t('admin.architecture_health.open_routes')) ?></a><a class="btn" href="/admin/system-tools"><?= e(t('admin.system_tools.nav.back')) ?></a></div></div>
</div>

<div class="card">
  <h3 class="architecture-health-title"><?= e(t('admin.architecture_health.routes_title')) ?></h3>
  <div class="architecture-health-grid architecture-health-grid--metrics">
    <?php foreach ([
      'total' => (int)($routeSummary['contract_route_total'] ?? 0),
      'canonical' => (int)($routeSummary['contract_route_total'] ?? 0) - (int)($routeSummary['contract_route_alias'] ?? 0),
      'aliases' => (int)($routeSummary['contract_route_alias'] ?? 0),
      'compatibility' => (int)($routeSummary['contract_route_compatibility'] ?? 0),
      'deprecated' => (int)($routeSummary['contract_route_deprecated'] ?? 0),
    ] as $key => $value): ?><div class="architecture-health-metric"><div class="muted"><?= e(t('admin.architecture_health.metric.' . $key)) ?></div><strong><?= $value ?></strong></div><?php endforeach; ?>
  </div>
</div>

<div class="card">
  <h3 class="architecture-health-title"><?= e(t('admin.architecture_health.contracts_title')) ?></h3>
  <div class="table-wrap"><table><thead><tr><th><?= e(t('admin.architecture_health.app')) ?></th><th><?= e(t('admin.architecture_health.status')) ?></th><th><?= e(t('admin.architecture_health.contract_warnings')) ?></th><th><?= e(t('admin.architecture_health.nav_items')) ?></th><th><?= e(t('admin.architecture_health.widgets')) ?></th><th><?= e(t('admin.architecture_health.dashboards')) ?></th></tr></thead><tbody>
    <?php foreach ($appDiagnostics as $app): ?><?php $uiDiag = is_array($app['ui_diag'] ?? null) ? $app['ui_diag'] : []; $ui = is_array($uiDiag['summary'] ?? null) ? $uiDiag['summary'] : []; $surfaceCounts = ['widget' => 0, 'dashboard' => 0]; foreach ((array)($uiDiag['surface_rows'] ?? []) as $surfaceRow) { $surfaceType = (string)($surfaceRow['surface_type'] ?? ''); if (array_key_exists($surfaceType, $surfaceCounts)) { $surfaceCounts[$surfaceType]++; } } ?><tr><td><?= e((string)($app['app_key'] ?? '')) ?></td><td><?= e((string)($app['status'] ?? '')) ?></td><td><?= count((array)($app['contract_warnings'] ?? [])) ?></td><td><?= (int)($ui['nav_contract_items'] ?? 0) ?></td><td><?= $surfaceCounts['widget'] ?></td><td><?= $surfaceCounts['dashboard'] ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</div>

<div class="card">
  <h3 class="architecture-health-title"><?= e(t('admin.architecture_health.localization_title')) ?></h3>
  <div class="architecture-health-grid architecture-health-grid--locales">
    <div class="architecture-health-metric"><div class="muted"><?= e(t('admin.architecture_health.missing_en')) ?></div><strong><?= count($missingEn) ?></strong></div>
    <div class="architecture-health-metric"><div class="muted"><?= e(t('admin.architecture_health.missing_ja')) ?></div><strong><?= count($missingJa) ?></strong></div>
    <div class="architecture-health-metric"><div class="muted"><?= e(t('admin.architecture_health.missing_ne')) ?></div><strong><?= count($missingNe) ?></strong></div>
  </div>
</div>

<div class="card">
  <h3 class="architecture-health-title"><?= e(t('admin.architecture_health.surfaces_title')) ?></h3>
  <div class="table-wrap"><table><thead><tr><th><?= e(t('admin.architecture_health.app')) ?></th><th><?= e(t('admin.architecture_health.widgets')) ?></th><th><?= e(t('admin.architecture_health.charts')) ?></th><th><?= e(t('admin.architecture_health.dashboards')) ?></th><th><?= e(t('admin.architecture_health.nav_items')) ?></th><th><?= e(t('admin.architecture_health.duplicates')) ?></th><th><?= e(t('admin.architecture_health.missing_locale')) ?></th></tr></thead><tbody>
    <?php foreach ($appDiagnostics as $app): ?><?php $ui = is_array($app['ui_diag'] ?? null) ? $app['ui_diag'] : []; $counts = ['widget' => 0, 'chart' => 0, 'dashboard' => 0, 'menu' => 0]; foreach ((array)($ui['surface_rows'] ?? []) as $row) { $type = (string)($row['surface_type'] ?? ''); if (array_key_exists($type, $counts)) { $counts[$type]++; } } ?><tr><td><?= e((string)($app['app_key'] ?? '')) ?></td><td><?= $counts['widget'] ?></td><td><?= $counts['chart'] ?></td><td><?= $counts['dashboard'] ?></td><td><?= $counts['menu'] ?></td><td><?= count((array)($ui['duplicate_declarations'] ?? [])) ?></td><td><?= count((array)($ui['missing_locale_keys'] ?? [])) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

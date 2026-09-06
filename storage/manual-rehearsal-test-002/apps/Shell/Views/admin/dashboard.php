<?php
// Admin Layer View: dashboard (Production Ready Enhanced Version)
// Enhanced with visual status indicators, improved layout, and better responsive design
$adminDashboardPanels = is_array($admin_dashboard_panels ?? null) ? array_values(array_filter($admin_dashboard_panels, 'is_array')) : [];
$businessDominantApp = strtolower(trim((string)($home_dominant_app ?? '')));

// Helper: check if a block should render (defaults to true if no config)
$shouldRenderBlock = static function(string $blockKey, array $enabledBlocks) use ($dashboardBlocksExplicitNone): bool {
  return !$dashboardBlocksExplicitNone && (count($enabledBlocks) === 0 || isset($enabledBlocks[$blockKey]));
};
$blockOrderStyle = static fn(string $blockKey): string => '';
$dashboardBlockOrder = static function(string $blockKey) use ($dashboardBlockOrderIndex): int {
  return isset($dashboardBlockOrderIndex[$blockKey]) ? (int)$dashboardBlockOrderIndex[$blockKey] : 1000;
};
$renderDashboardBlocks = [];
if ($dashboardItems !== []) {
  if ($shouldRenderBlock('operational_summary', $enabledDashboardBlocks)) {
    $renderDashboardBlocks['operational_summary'] = static fn(): string => \Apps\Manufacturing\Services\ManufacturingDashboardBlockService::renderOperationalSummary([
      'manufacturingKpi' => $manufacturingKpi,
      'kpiCards' => $kpiCards,
      'timelineRows' => $timelineRows,
      'experienceMode' => $experienceMode,
      'dashboardType' => $dashboardType,
      'authorityRole' => $authorityRole,
      'blockOrderStyle' => $blockOrderStyle,
    ]);
  }
  if ($shouldRenderBlock('primary_work_widgets', $enabledDashboardBlocks)) {
    $renderDashboardBlocks['primary_work_widgets'] = static fn(): string => \Apps\Manufacturing\Services\ManufacturingDashboardBlockService::renderPrimaryWorkWidgets([
      'preparedModules' => $preparedModules,
      'renderHostSection' => $renderHostSection,
      'blockOrderStyle' => $blockOrderStyle,
    ]);
  }
  if ($shouldRenderBlock('plugin_dashboards_charts', $enabledDashboardBlocks)) {
    $renderDashboardBlocks['plugin_dashboards_charts'] = static fn(): string => \Apps\Manufacturing\Services\ManufacturingDashboardBlockService::renderPluginDashboardCharts([
      'recentOrders' => $recentOrders,
      'allOrdersUrl' => $allOrdersUrl,
      'dispatchFeaturedItem' => $dispatchFeaturedItem,
      'quickLinkCards' => $quickLinkCards,
      'renderHostSection' => $renderHostSection,
      'blockOrderStyle' => $blockOrderStyle,
    ]);
  }
  if ($shouldRenderBlock('monitoring_widgets', $enabledDashboardBlocks)) {
    $renderDashboardBlocks['monitoring_widgets'] = static fn(): string => \Apps\Platform\Services\AdminActivityDashboardBlockService::render([
      'activityFeed' => $activityFeed,
      'blockOrderStyle' => $blockOrderStyle,
    ]);
  }
  uksort($renderDashboardBlocks, static function(string $a, string $b) use ($dashboardBlockOrder): int {
    return $dashboardBlockOrder($a) <=> $dashboardBlockOrder($b);
  });
}
?>
<div class="my-work-polish my-work-dashboard my-work-theme-enterprise">
  <?php include APP_ROOT . '/apps/Shell/Views/admin/upgrade_workbench.php'; ?>
  <?php include APP_ROOT . '/apps/Shell/Views/admin/unified_launcher.php'; ?>
  <?php $renderAdminDashboardPanels = $adminDashboardPanels !== [] && $shouldRenderBlock('admin_dashboard_panels', $enabledDashboardBlocks); ?>
  <?php if ($renderAdminDashboardPanels): ?>
    <?= \Apps\Platform\Services\AdminDashboardPanelBlockService::render([
      'adminDashboardPanels' => $adminDashboardPanels,
      'businessDominantApp' => $businessDominantApp,
      'experienceMode' => $experienceMode,
      'dashboardType' => $dashboardType,
      'authorityRole' => $authorityRole,
      'blockOrderStyle' => $blockOrderStyle,
    ]) ?>
  <?php endif; ?>
  <?php if ($dashboardItems !== []): ?>
    <header class="admin-assigned-apps-head">
      <h2><?= e(t('admin.assigned_apps.title')) ?></h2>
      <p class="muted"><?= e(t('admin.assigned_apps.description')) ?></p>
    </header>
    <?php foreach ($renderDashboardBlocks as $renderDashboardBlock): ?>
      <?= $renderDashboardBlock() ?>
    <?php endforeach; ?>
  <?php elseif (!$renderAdminDashboardPanels): ?>
    <section class="card">
      <div class="muted"><?= e(t('common.no_results_found')) ?></div>
    </section>
  <?php endif; ?>
</div>

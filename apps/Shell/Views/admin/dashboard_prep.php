<?php
$manufacturingDashboardBlock = \Apps\Manufacturing\Services\ManufacturingDashboardBlockService::prepareOperationalSummary($dashboardItems, $moduleLabelFromKey);
$dashboardModules = is_array($manufacturingDashboardBlock['dashboardModules'] ?? null) ? (array)$manufacturingDashboardBlock['dashboardModules'] : [];
$manufacturingKpi = is_array($manufacturingDashboardBlock['manufacturingKpi'] ?? null) ? (array)$manufacturingDashboardBlock['manufacturingKpi'] : [];
$kpiCards = is_array($manufacturingDashboardBlock['kpiCards'] ?? null) ? (array)$manufacturingDashboardBlock['kpiCards'] : [];
$timelineRows = is_array($manufacturingDashboardBlock['timelineRows'] ?? null) ? (array)$manufacturingDashboardBlock['timelineRows'] : [];
$cockpitPanelItemLimit = 5;
$manufacturingPluginDashboardBlock = \Apps\Manufacturing\Services\ManufacturingDashboardBlockService::preparePluginDashboardCharts(
  $dashboardItems,
  $dashboardModules,
  is_array($streamItems ?? null) ? (array)$streamItems : [],
  is_array($enabledPluginCardOrder ?? null) ? (array)$enabledPluginCardOrder : [],
  $moduleLabelFromKey
);
$recentOrders = is_array($manufacturingPluginDashboardBlock['recentOrders'] ?? null) ? (array)$manufacturingPluginDashboardBlock['recentOrders'] : [];
$allOrdersUrl = trim((string)($manufacturingPluginDashboardBlock['allOrdersUrl'] ?? '/apps/manufacturing/daily-orders'));
$dispatchFeaturedItem = is_array($manufacturingPluginDashboardBlock['dispatchFeaturedItem'] ?? null) ? (array)$manufacturingPluginDashboardBlock['dispatchFeaturedItem'] : null;
$quickLinkCards = is_array($manufacturingPluginDashboardBlock['quickLinkCards'] ?? null) ? (array)$manufacturingPluginDashboardBlock['quickLinkCards'] : [];
$adminLauncherUrls = [];
foreach (array_values(array_filter((array)($admin_launcher_groups ?? []), 'is_array')) as $adminLauncherGroup) {
  foreach (array_values(array_filter((array)($adminLauncherGroup['items'] ?? []), 'is_array')) as $adminLauncherItem) {
    $launcherPath = rtrim(trim((string)parse_url((string)($adminLauncherItem['url'] ?? ''), PHP_URL_PATH)), '/');
    if ($launcherPath !== '') {
      $adminLauncherUrls[strtolower($launcherPath)] = true;
    }
  }
}
$quickLinkCards = array_values(array_filter($quickLinkCards, static function(array $quickLink) use ($adminLauncherUrls): bool {
  $path = rtrim(trim((string)parse_url((string)($quickLink['url'] ?? ''), PHP_URL_PATH)), '/');
  return $path === '' || !isset($adminLauncherUrls[strtolower($path)]);
}));
$preparedModules = \Apps\Manufacturing\Services\ManufacturingDashboardBlockService::preparePrimaryWorkWidgets($dashboardModules);
$activityFeed = \Apps\Platform\Services\AdminActivityDashboardBlockService::prepare($cockpitPanelItemLimit);

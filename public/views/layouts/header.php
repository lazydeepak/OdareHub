<?php
declare(strict_types=1);

if (!function_exists('e')) {
  function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}

$loggedIn = \App\Core\Auth::isLoggedIn();
$user = $loggedIn ? \App\Core\Auth::user() : null;
$isAdmin = function_exists('base_is_admin_user') ? base_is_admin_user($user) : ($loggedIn && (string)($user['role'] ?? '') === 'Admin');
$canAccessBase = function_exists('base_can_access_builder') ? base_can_access_builder($user) : $isAdmin;
$notificationServicePath = APP_ROOT . '/plugins/Base/Services/NotificationService.php';
if (is_file($notificationServicePath)) {
  require_once $notificationServicePath;
}
$unreadNotifications = 0;
$recentHeaderNotifications = [];
if ($loggedIn && class_exists('\Plugins\\Base\\Services\\NotificationService')) {
  $unreadNotifications = (int)\Plugins\Base\Services\NotificationService::unreadCountForUser($user);
  $recentHeaderNotifications = (array)\Plugins\Base\Services\NotificationService::recentForUser($user, 6, true);
}
$headerNotificationGroups = [
  'action_required' => [],
  'approval_signals' => [],
  'escalations' => [],
  'system' => [],
];
if ($recentHeaderNotifications !== [] && class_exists('\\Plugins\\Base\\Services\\NotificationService')) {
  foreach ($recentHeaderNotifications as $note) {
    $group = \Plugins\Base\Services\NotificationService::signalGroup((array)$note);
    if (!isset($headerNotificationGroups[$group])) {
      $group = 'system';
    }
    $headerNotificationGroups[$group][] = $note;
  }
}
$devToolsEnabled = function_exists('app_dev_tools_enabled') ? app_dev_tools_enabled() : false;
$platformMode = function_exists('platform_mode') ? (string)platform_mode() : 'production';
$platformMode = in_array($platformMode, ['production', 'development', 'demo'], true) ? $platformMode : 'production';
$platformModeLabel = (string)t('admin.platform_mode.mode.' . $platformMode);
$pageTitle = $pageTitle ?? __('app.name');
$lang = current_lang();
$currency = current_currency();

$homeLauncherApps = [];
$userDashboardTypeGlobal = 'operator';
if ($loggedIn && function_exists('platform_user_runtime_facade')) {
  try {
    $ctx = \Apps\Platform\Services\UserAssignmentContext::context()->resolveUserContext($user);
    $homeLauncherApps = array_values((array)($ctx['active_assigned_apps'] ?? []));
    $authorityRoleGlobal = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    $userDashboardTypeGlobal = strtolower(trim((string)($ctx['dashboard_type'] ?? 'operator')));
    if ($authorityRoleGlobal === 'platform_admin' && !in_array('platform', $homeLauncherApps, true)) {
      array_unshift($homeLauncherApps, 'platform');
    }
  } catch (\Throwable $e) {
    $homeLauncherApps = [];
    $authorityRoleGlobal = 'app_user';
    $userDashboardTypeGlobal = 'operator';
  }
} else {
  $homeLauncherApps = [];
  $authorityRoleGlobal = 'app_user';
  $userDashboardTypeGlobal = 'operator';
}
$normalizeCssPath = __DIR__ . '/../../assets/normalize.css';
$appCssPath = __DIR__ . '/../../assets/app.css';
$appCssVersion = is_file($appCssPath) ? (string)filemtime($appCssPath) : '1';
$currentPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
// Three address spaces: operator (/u/*), display (/displays/*), admin (everything else).
// Admin wrapper applies to /apps/*, /ops/*, /admin/*, /account, /login, /me, etc.
// /displays/* is served by DisplayLayerService independently — it does not reach this file.
$isOperatorLayerRoute = preg_match('#^/u(/|$)#', $currentPath) === 1;
$isDisplayLayerRoute  = preg_match('#^/displays(/|$)#', $currentPath) === 1;
// Admin wrapper = every address that is NOT /u/* (operator) or /displays/* (kiosk).
// Matches WorkspaceWrapperRegistry, layout-footer.php, and AGENTS.md canonical table.
$isAdminLayerRoute = !$isOperatorLayerRoute && !$isDisplayLayerRoute;
// ─── Wrapper chrome visibility flags ─────────────────────────────────────────
// Both layers show sidebar, search, and alerts.
// To suppress a component on a specific layer, extend the logic here — never
// add per-view overrides; that creates untraceable chrome inconsistencies.
$showSidebar          = true;           // left sidebar — both admin + operator
$showTopbarSearch     = true;           // topbar search box — both admin + operator
$showAlerts           = true;           // notification bell — both admin + operator
// Admin mobile strip (.admin-bottom-nav* CSS, ≤760 px breakpoint).
// Operator layer has its own touch chrome via OperatorLayerWrapperComposer (.u-bottom-nav*).
// Display layer has no bottom nav (readonly kiosk, DisplayLayerService renders independently).
// Per AGENTS.md rule 2: /apps/* and /ops/* are admin territory and must not carry the
// mobile bottom strip — those surfaces are governance/system pages, not touch-first nav.
$isAdminTerritoryRoute = preg_match('#^/(apps|ops)(/|$)#', $currentPath) === 1;
$showAdminMobileStrip = $loggedIn && $isAdminLayerRoute && !$isAdminTerritoryRoute;
$defaultHomeUrl = function_exists('default_home_route') ? default_home_route() : '/';

$shellRuntimeMenuComposerPath = APP_ROOT . '/apps/Shell/Services/ShellRuntimeMenuComposer.php';
if (is_file($shellRuntimeMenuComposerPath)) {
  require_once $shellRuntimeMenuComposerPath;
}

$themePreferenceServicePath = APP_ROOT . '/apps/Shell/Services/ThemePreferenceService.php';
if (is_file($themePreferenceServicePath)) {
  require_once $themePreferenceServicePath;
}

$shellOverlayFrameworkPath = APP_ROOT . '/apps/Shell/Services/ShellOverlayFramework.php';
if (is_file($shellOverlayFrameworkPath)) {
  require_once $shellOverlayFrameworkPath;
}

$publicAccountOverflowAdapterPath = APP_ROOT . '/apps/Shell/Overlay/Compatibility/Adapters/PublicSurface/PublicAccountOverflowAdapter.php';
if (is_file($publicAccountOverflowAdapterPath)) {
  require_once $publicAccountOverflowAdapterPath;
}

$publicNotificationsDropdownAdapterPath = APP_ROOT . '/apps/Shell/Overlay/Compatibility/Adapters/PublicSurface/PublicNotificationsDropdownAdapter.php';
if (is_file($publicNotificationsDropdownAdapterPath)) {
  require_once $publicNotificationsDropdownAdapterPath;
}

$publicAdminActionDropdownAdapterPath = APP_ROOT . '/apps/Shell/Overlay/Compatibility/Adapters/PublicSurface/PublicAdminActionDropdownAdapter.php';
if (is_file($publicAdminActionDropdownAdapterPath)) {
  require_once $publicAdminActionDropdownAdapterPath;
}

$publicTopbarSearchResultsAdapterPath = APP_ROOT . '/apps/Shell/Overlay/Compatibility/Adapters/PublicSurface/PublicTopbarSearchResultsAdapter.php';
if (is_file($publicTopbarSearchResultsAdapterPath)) {
  require_once $publicTopbarSearchResultsAdapterPath;
}

$publicMobileSidebarDrawerAdapterPath = APP_ROOT . '/apps/Shell/Overlay/Compatibility/Adapters/PublicSurface/PublicMobileSidebarDrawerAdapter.php';
if (is_file($publicMobileSidebarDrawerAdapterPath)) {
  require_once $publicMobileSidebarDrawerAdapterPath;
}

$cameraScanOverlayAdapterPath = APP_ROOT . '/apps/Shell/Overlay/Compatibility/Adapters/Shared/CameraScanOverlayAdapter.php';
if (is_file($cameraScanOverlayAdapterPath)) {
  require_once $cameraScanOverlayAdapterPath;
}

$engineeringWorkspaceResolverPath = APP_ROOT . '/platform/Security/EngineeringWorkspaceResolver.php';
if (is_file($engineeringWorkspaceResolverPath)) {
  require_once $engineeringWorkspaceResolverPath;
}
$platformAuthorityPath = APP_ROOT . '/platform/Security/PlatformAuthority.php';
if (is_file($platformAuthorityPath)) {
  require_once $platformAuthorityPath;
}
$engineeringWorkspacePageContextResolverPath = APP_ROOT . '/platform/Security/EngineeringWorkspacePageContextResolver.php';
if (is_file($engineeringWorkspacePageContextResolverPath)) {
  require_once $engineeringWorkspacePageContextResolverPath;
}

$themeChoices = class_exists('\Apps\\Shell\\Services\\ThemePreferenceService')
  ? \Apps\Shell\Services\ThemePreferenceService::themeChoices()
  : ['system-liquid-glass' => 'System - Liquid Glass'];
$themeAllowedPreferences = array_values(array_keys($themeChoices));
$defaultThemePreference = class_exists('\Apps\\Shell\\Services\\ThemePreferenceService')
  ? \Apps\Shell\Services\ThemePreferenceService::defaultPreference()
  : (function_exists('default_theme_preference') ? default_theme_preference() : 'system-liquid-glass');
$defaultThemeMode = class_exists('\Apps\\Shell\\Services\\ThemePreferenceService')
  ? \Apps\Shell\Services\ThemePreferenceService::modeFromPreference($defaultThemePreference)
  : 'system';
$defaultColorStyle = class_exists('\Apps\\Shell\\Services\\ThemePreferenceService')
  ? \Apps\Shell\Services\ThemePreferenceService::colorStyleFromPreference($defaultThemePreference)
  : 'liquid-glass';
$defaultEffectiveTheme = $defaultThemeMode === 'light' ? 'light' : 'dark';

$sidebarSections = [];
$homeUrl = $loggedIn ? $defaultHomeUrl : '/';
if (class_exists('\\Apps\\Shell\\Services\\BrandIdentityService')) {
  $brandIdentity = \Apps\Shell\Services\BrandIdentityService::runtime();
  $instanceName = $brandIdentity['instance_name'];
} else {
  $instanceName = function_exists('app_display_name') ? app_display_name() : t('app.name');
  if (defined('APP_NAME') && strcasecmp(trim((string)$instanceName), 'ERP' . ' Engine') === 0) {
    $instanceName = (string)APP_NAME;
  }
}
$accountDisplayName = 'Guest';
if ($loggedIn && is_array($user)) {
  $accountDisplayName = trim((string)($user['display_name'] ?? ''));
  if ($accountDisplayName === '') {
    $accountDisplayName = trim((string)($user['full_name'] ?? ''));
  }
  if ($accountDisplayName === '') {
    $accountDisplayName = trim((string)($user['name'] ?? ''));
  }
  if ($accountDisplayName === '') {
    $accountDisplayName = trim((string)($user['email'] ?? 'User'));
  }
}
$accountSecondaryLabel = $loggedIn && is_array($user) ? trim((string)($user['email'] ?? '')) : '';
$showAccountSecondaryLabel = $accountSecondaryLabel !== ''
  && strcasecmp($accountSecondaryLabel, $accountDisplayName) !== 0;
$switchToOperatorUrl = '/u';
if ($loggedIn && is_array($user)) {
  $usernameForOperator = '';
  if (class_exists('\\Apps\\Shell\\Services\\WorkspaceWrapperRegistry')) {
    $usernameForOperator = \Apps\Shell\Services\WorkspaceWrapperRegistry::handleFromIdentity([
      'username' => (string)($user['username'] ?? $user['user_name'] ?? ''),
      'email' => (string)($user['email'] ?? ''),
    ]);
  } else {
    $usernameForOperator = trim((string)($user['username'] ?? $user['user_name'] ?? ''));
    if ($usernameForOperator === '') {
      $emailForOperator = trim((string)($user['email'] ?? ''));
      if ($emailForOperator !== '' && str_contains($emailForOperator, '@')) {
        $usernameForOperator = (string)strstr($emailForOperator, '@', true);
      }
    }
  }
  if ($usernameForOperator !== '') {
    $switchToOperatorUrl = '/u/' . rawurlencode($usernameForOperator) . '/dashboard';
  }
}
$accountInitials = 'GU';
if ($accountDisplayName !== '') {
  $initialParts = preg_split('/\s+/', trim($accountDisplayName)) ?: [];
  $letters = [];
  foreach ($initialParts as $part) {
    if ($part === '') {
      continue;
    }
    $letters[] = strtoupper(substr($part, 0, 1));
    if (count($letters) === 2) {
      break;
    }
  }
  if (count($letters) === 1) {
    $singleToken = preg_replace('/[^a-z0-9]/i', '', (string)$initialParts[0]) ?? '';
    $accountInitials = strtoupper(substr($singleToken !== '' ? $singleToken : $accountDisplayName, 0, 2));
  } else {
    $accountInitials = $letters === []
      ? strtoupper(substr($accountDisplayName, 0, 2))
      : implode('', $letters);
  }
}
$sidebarSlug = static function (string $value): string {
  $normalized = strtolower(trim($value));
  $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized) ?? '';
  $normalized = trim($normalized, '-');
  return $normalized !== '' ? $normalized : 'generic';
};
$sidebarUsageScope = $loggedIn
  ? 'user-' . substr(sha1((string)($user['email'] ?? ($user['id'] ?? 'logged-in'))), 0, 12)
  : 'guest';
if (class_exists('\Apps\Shell\Services\ShellRuntimeMenuComposer')) {
  $sidebarPayload = \Apps\Shell\Services\ShellRuntimeMenuComposer::compose([
    'loggedIn' => $loggedIn,
    'user' => $user,
    'isAdmin' => $isAdmin,
    'canAccessBase' => $canAccessBase,
    'devToolsEnabled' => $devToolsEnabled,
    'currentPath' => $currentPath,
  ]);
  $sidebarSections = (array)($sidebarPayload['sections'] ?? []);
}

$assignmentRoutePolicy = null;
if ($loggedIn && function_exists('platform_user_access_policy_contract')) {
  try {
    $candidate = platform_user_access_policy_contract();
    if (is_object($candidate)) {
      $assignmentRoutePolicy = $candidate;
    }
  } catch (\Throwable $e) {
    $assignmentRoutePolicy = null;
  }
}

$isSidebarRouteAllowed = static function ($policy, ?array $userCtx, string $url): bool {
  $path = trim((string)parse_url($url, PHP_URL_PATH));
  if ($path === '' || $path === '#') {
    return true;
  }

  if (!is_object($policy)) {
    return true;
  }

  try {
    $decision = $policy->routeAccessDecision($userCtx, $path, 'GET');
    return (bool)($decision['allowed'] ?? false);
  } catch (\Throwable $e) {
    return true;
  }
};

$manufacturingActionLinks = [];

$quickNavigationSpecs = [
  ['label' => 'Dashboard', 'url' => '/apps/manufacturing', 'icon' => '🏠'],
  ['label' => 'Procurement', 'url' => '/apps/procurement', 'icon' => '🛒'],
  ['label' => 'Products', 'url' => '/apps/manufacturing/products', 'icon' => '🧱'],
  ['label' => 'Plans', 'url' => '/apps/manufacturing/production-plans', 'icon' => '🗓️'],
  ['label' => 'Processing', 'url' => '/apps/manufacturing/processing-operation', 'icon' => '⚙️'],
  ['label' => 'Dispatch', 'url' => '/apps/manufacturing/dispatch-ops', 'icon' => '🚚'],
];
$quickNavigationLinks = [];
foreach ($quickNavigationSpecs as $spec) {
  if (!is_array($spec)) {
    continue;
  }

  $url = trim((string)($spec['url'] ?? ''));
  if ($url === '') {
    continue;
  }

  if (!$isSidebarRouteAllowed($assignmentRoutePolicy, is_array($user) ? $user : null, $url)) {
    continue;
  }

  $quickNavigationLinks[] = [
    'label' => trim((string)($spec['label'] ?? 'Link')),
    'url' => $url,
    'icon' => trim((string)($spec['icon'] ?? '🔗')),
  ];
}

$platformAdminPanelUrl = '/apps/platform';
$showAdminSystemShortcut = $authorityRoleGlobal === 'platform_admin'
  && $isSidebarRouteAllowed($assignmentRoutePolicy, is_array($user) ? $user : null, $platformAdminPanelUrl);

$filteredSidebarSections = [];
foreach ($sidebarSections as $section) {
  if (!is_array($section)) {
    continue;
  }

  $groups = [];
  foreach ((array)($section['groups'] ?? []) as $group) {
    if (!is_array($group)) {
      continue;
    }

    $visibleItems = [];
    foreach ((array)($group['items'] ?? []) as $item) {
      if (!is_array($item)) {
        continue;
      }

      $renderType = trim((string)($item['render_type'] ?? ''));
      if ($renderType !== '') {
        $visibleItems[] = $item;
        continue;
      }

      $url = trim((string)($item['url'] ?? ''));
      if ($url !== '' && !$isSidebarRouteAllowed($assignmentRoutePolicy, is_array($user) ? $user : null, $url)) {
        continue;
      }

      $visibleItems[] = $item;
    }

    if ($visibleItems === []) {
      continue;
    }

    $group['items'] = $visibleItems;
    $group['is_active'] = false;
    foreach ($visibleItems as $visibleItem) {
      if (!empty($visibleItem['is_active'])) {
        $group['is_active'] = true;
        break;
      }
    }
    $group['is_open'] = $group['is_active'] || !empty($group['is_open']);
    $groups[] = $group;
  }

  if ($groups === []) {
    continue;
  }

  $section['groups'] = $groups;
  $filteredSidebarSections[] = $section;
}

$sidebarSections = $filteredSidebarSections;

$currentAppFromPath = '';
if (preg_match('#^/apps/([^/]+)#i', $currentPath, $pathMatches) === 1) {
  $currentAppFromPath = strtolower(trim((string)($pathMatches[1] ?? '')));
}

$dynamicActionCandidates = [];
$dynamicActionSeen = [];
foreach ($sidebarSections as $section) {
  if (!is_array($section)) {
    continue;
  }

  $sectionGroups = (array)($section['groups'] ?? []);
  $sectionIsActive = false;
  foreach ($sectionGroups as $sectionGroup) {
    if (is_array($sectionGroup) && !empty($sectionGroup['is_active'])) {
      $sectionIsActive = true;
      break;
    }
  }

  foreach ($sectionGroups as $group) {
    if (!is_array($group)) {
      continue;
    }

    $groupIsActive = !empty($group['is_active']);
    foreach ((array)($group['items'] ?? []) as $item) {
      if (!is_array($item)) {
        continue;
      }

      if (trim((string)($item['render_type'] ?? '')) !== '') {
        continue;
      }

      $url = trim((string)($item['url'] ?? ''));
      $label = trim((string)($item['label'] ?? ''));
      if ($url === '' || $label === '') {
        continue;
      }

      if (!$isSidebarRouteAllowed($assignmentRoutePolicy, is_array($user) ? $user : null, $url)) {
        continue;
      }

      $path = strtolower(trim((string)parse_url($url, PHP_URL_PATH)));
      $style = (array)($item['style'] ?? []);
      $isActionItem = !empty($style['is_action']);
      $matchesCurrentApp = $currentAppFromPath !== '' && str_starts_with($path, '/apps/' . $currentAppFromPath);
      $looksLikeAction = preg_match('#/(add|create|new|import|export|approve|plan|dispatch|receipt|receive|entry|entries)(/|$)#i', $path) === 1;

      if (!$isActionItem && !$matchesCurrentApp && !$groupIsActive && !$sectionIsActive && !$looksLikeAction) {
        continue;
      }

      $seenKey = strtolower($label . '|' . $url);
      if ($seenKey !== '' && isset($dynamicActionSeen[$seenKey])) {
        continue;
      }
      if ($seenKey !== '') {
        $dynamicActionSeen[$seenKey] = true;
      }

      $score = 0;
      if (!empty($item['is_active'])) {
        $score += 60;
      }
      if ($matchesCurrentApp) {
        $score += 40;
      }
      if ($isActionItem) {
        $score += 30;
      }
      if ($groupIsActive || $sectionIsActive) {
        $score += 20;
      }
      if ($looksLikeAction) {
        $score += 10;
      }

      $dynamicActionCandidates[] = [
        'label' => $label,
        'hint' => trim((string)($item['description'] ?? $item['title'] ?? '')),
        'url' => $url,
        'icon' => trim((string)($item['icon'] ?? '')) !== '' ? trim((string)$item['icon']) : '⚡',
        '_score' => $score,
      ];
    }
  }
}

if ($dynamicActionCandidates !== []) {
  usort($dynamicActionCandidates, static function (array $a, array $b): int {
    $scoreA = (int)($a['_score'] ?? 0);
    $scoreB = (int)($b['_score'] ?? 0);
    if ($scoreA !== $scoreB) {
      return $scoreB <=> $scoreA;
    }
    return strcasecmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
  });

  foreach ($dynamicActionCandidates as $candidate) {
    $manufacturingActionLinks[] = [
      'label' => trim((string)($candidate['label'] ?? '')),
      'hint' => trim((string)($candidate['hint'] ?? '')),
      'url' => trim((string)($candidate['url'] ?? '')),
      'icon' => trim((string)($candidate['icon'] ?? '⚡')),
    ];
    if (count($manufacturingActionLinks) >= 8) {
      break;
    }
  }
}

if ($manufacturingActionLinks === [] && $quickNavigationLinks !== []) {
  foreach ($quickNavigationLinks as $fallbackLink) {
    if (!is_array($fallbackLink)) {
      continue;
    }
    $fallbackUrl = trim((string)($fallbackLink['url'] ?? ''));
    $fallbackLabel = trim((string)($fallbackLink['label'] ?? ''));
    if ($fallbackUrl === '' || $fallbackLabel === '') {
      continue;
    }

    $manufacturingActionLinks[] = [
      'label' => $fallbackLabel,
      'hint' => '',
      'url' => $fallbackUrl,
      'icon' => trim((string)($fallbackLink['icon'] ?? '⚡')),
    ];
    if (count($manufacturingActionLinks) >= 8) {
      break;
    }
  }
}

if ($loggedIn && function_exists('platform_user_runtime_facade')) {
  try {
    $homeUrl = platform_user_runtime_facade()->primaryLandingForUser($user);
  } catch (\Throwable $e) {
    $homeUrl = $defaultHomeUrl;
  }
}
?>
<!doctype html>
<html lang="<?= e($lang) ?>" data-theme="<?= e($defaultEffectiveTheme) ?>" data-theme-mode="<?= e($defaultThemeMode) ?>" data-color-style="<?= e($defaultColorStyle) ?>" data-theme-preference="<?= e($defaultThemePreference) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= e((string)$pageTitle) ?></title>
  <script>
    (function () {
      var storageKey = 'erp-theme-preference';
      var fallbackPreference = <?= json_encode($defaultThemePreference, JSON_UNESCAPED_SLASHES) ?>;
      var allowedPreferences = <?= json_encode($themeAllowedPreferences, JSON_UNESCAPED_SLASHES) ?>;

      function normalizeThemePreference(value) {
        var raw = (value || '').toString().trim().toLowerCase();
        var fallbackParts = (fallbackPreference || 'system-liquid-glass').split('-');
        var fallbackStyle = fallbackParts.slice(1).join('-') || 'liquid-glass';
        if (allowedPreferences.indexOf(raw) !== -1) {
          return raw;
        }
        if (raw === 'light') return 'light-' + fallbackStyle;
        if (raw === 'dark') return 'dark-' + fallbackStyle;
        if (raw === 'system') return 'system-' + fallbackStyle;
        return fallbackPreference || 'system-liquid-glass';
      }

      function resolveSystemTheme() {
        return (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)
          ? 'dark' : 'light';
      }

      function applyThemePreference(value) {
        var preference = normalizeThemePreference(value);
        var parts = preference.split('-');
        var mode = parts[0] === 'light' || parts[0] === 'dark' ? parts[0] : 'system';
        var colorStyle = parts.slice(1).join('-') || 'liquid-glass';
        var effectiveTheme = mode === 'system' ? resolveSystemTheme() : mode;
        document.documentElement.setAttribute('data-theme-mode', mode);
        document.documentElement.setAttribute('data-theme', effectiveTheme);
        document.documentElement.setAttribute('data-color-style', colorStyle);
        document.documentElement.setAttribute('data-theme-preference', preference);
        document.documentElement.style.colorScheme = effectiveTheme;
      }

      try {
        applyThemePreference(window.localStorage.getItem(storageKey) || fallbackPreference);
      } catch (_e) {
        applyThemePreference(fallbackPreference);
      }
    })();
  </script>
  <!-- All CSS managed by StyleRegistryService -->
  <?php
    if (class_exists('\Apps\Shell\Services\StyleRegistryService')) {
      $ctx = [];
      if ($loggedIn && function_exists('platform_user_runtime_facade')) {
        try {
          $ctx = \Apps\Platform\Services\UserAssignmentContext::context()->resolveUserContext($user);
        } catch (\Throwable) {
          $ctx = [];
        }
      }
      $surface = $isAdminLayerRoute ? 'admin' : 'auth';
      $allStyles = array_merge(
        \Apps\Shell\Services\StyleRegistryService::globals(),
        \Apps\Shell\Services\StyleRegistryService::forSurface($surface, $ctx)
      );
      foreach ($allStyles as $style) {
        $styleUrl = $style['url'] ?? '';
        $styleVersion = $style['version'] ?? '1';
        if ($styleUrl !== '') {
          echo '  <link rel="stylesheet" href="' . e($styleUrl) . '?v=' . e($styleVersion) . '">' . "\n";
        }
      }
    }
  ?>
  <link rel="stylesheet" href="/assets/branding/ipm-logo.css">
  <?php
    // Inject favicon dynamically from branding assets if available
    try {
      require_once APP_ROOT . '/apps/Platform/modules/Organization/Services/OrganizationService.php';
      $favicon = \Plugins\Organization\Services\OrganizationService::getFaviconAsset(0);
      if (!empty($favicon)) {
        $faviconUrl = trim((string)($favicon['preview_url'] ?? ''));
        if ($faviconUrl !== '') {
          echo '  <link rel="icon" type="' . e((string)($favicon['mime_type'] ?? 'image/png')) . '" href="' . e($faviconUrl) . '">' . "\n";
        }
      }
    } catch (\Throwable) {
      // Favicon is non-critical; silently ignore errors
    }
    $logoData = \Apps\Shell\Services\LogoResolverService::resolve();
    $headerCompanyLogo = $logoData['logo_url'];
    $headerInlineBrandLogo = $logoData['logo_svg'];
    $headerLogoSvgTheme = $logoData['logo_svg_theme'];
    $headerFallbackText = $logoData['fallback_text'];
    $headerFallbackTextCompact = $logoData['fallback_text_compact'];
  ?>
  </head>
  <body>
  <script>
    function switchLang(value) {
      const url = new URL(window.location.href);
      url.searchParams.set('lang', value);
      window.location.href = url.toString();
    }

    function switchCurrency(value) {
      const url = new URL(window.location.href);
      url.searchParams.set('currency', value);
      window.location.href = url.toString();
    }

    const THEME_STORAGE_KEY = 'erp-theme-preference';
    const OVERLAY_EFFECT_STRENGTH_STORAGE_KEY = 'shell-overlay-effect-strength';
    const DEFAULT_OVERLAY_EFFECT_STRENGTH = 40;
    const THEME_FALLBACK_PREFERENCE = <?= json_encode($defaultThemePreference, JSON_UNESCAPED_SLASHES) ?>;
    const THEME_ALLOWED_PREFERENCES = <?= json_encode($themeAllowedPreferences, JSON_UNESCAPED_SLASHES) ?>;
    const SIDEBAR_USAGE_SCOPE = <?= json_encode($sidebarUsageScope, JSON_UNESCAPED_SLASHES) ?>;
    const SIDEBAR_USAGE_STORAGE_KEY = 'erp-sidebar-usage-v1:' + SIDEBAR_USAGE_SCOPE;
    const QR_I18N = {
      prompt_manual: <?= json_encode((string)t('header.qr_scanner.prompt_manual'), JSON_UNESCAPED_SLASHES) ?>,
      panel_title: <?= json_encode((string)t('header.qr_scanner.panel_title'), JSON_UNESCAPED_SLASHES) ?>,
      helper_text: <?= json_encode((string)t('header.qr_scanner.helper_text'), JSON_UNESCAPED_SLASHES) ?>,
      starting_camera: <?= json_encode((string)t('header.qr_scanner.starting_camera'), JSON_UNESCAPED_SLASHES) ?>,
      btn_cancel: <?= json_encode((string)t('header.qr_scanner.btn_cancel'), JSON_UNESCAPED_SLASHES) ?>,
      btn_enter_value: <?= json_encode((string)t('header.qr_scanner.btn_enter_value'), JSON_UNESCAPED_SLASHES) ?>,
      scanning: <?= json_encode((string)t('header.qr_scanner.scanning'), JSON_UNESCAPED_SLASHES) ?>,
      no_code_detected: <?= json_encode((string)t('header.qr_scanner.no_code_detected'), JSON_UNESCAPED_SLASHES) ?>,
      scanner_unavailable: <?= json_encode((string)t('header.qr_scanner.scanner_unavailable'), JSON_UNESCAPED_SLASHES) ?>,
    };
    const SIDEBAR_OVERFLOW_STORAGE_KEY = 'erp-sidebar-overflow-v1:' + SIDEBAR_USAGE_SCOPE;
    const SIDEBAR_DISCLOSURE_STORAGE_KEY = 'erp-sidebar-disclosure-v1:' + SIDEBAR_USAGE_SCOPE;
    const SIDEBAR_RECENT_LIMIT = 12;
    const sidebarForcedCloseGroups = new WeakSet();
    let publicMobileSidebarAdapter = null;
    let systemThemeMediaQuery = null;

    function isSidebarMobileViewport() {
      return window.innerWidth <= 860;
    }

    function resolveSystemTheme() {
      if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
        return 'dark';
      }
      return 'light';
    }

    function normalizeThemePreference(value) {
      const raw = (value || '').toString().trim().toLowerCase();
      const fallbackParts = (THEME_FALLBACK_PREFERENCE || 'system-liquid-glass').split('-');
      const fallbackStyle = fallbackParts.slice(1).join('-') || 'liquid-glass';
      if (THEME_ALLOWED_PREFERENCES.includes(raw)) {
        return raw;
      }
      // Backward compatibility with old 3-option selector values.
      if (raw === 'light') {
        return 'light-' + fallbackStyle;
      }
      if (raw === 'dark') {
        return 'dark-' + fallbackStyle;
      }
      if (raw === 'system') {
        return 'system-' + fallbackStyle;
      }
      return 'system-liquid-glass';
    }

    function loadThemePreference() {
      try {
        const storedPreference = window.localStorage.getItem(THEME_STORAGE_KEY);
        if (!storedPreference) {
          return normalizeThemePreference(THEME_FALLBACK_PREFERENCE);
        }
        return normalizeThemePreference(storedPreference);
      } catch (_e) {
        return normalizeThemePreference(THEME_FALLBACK_PREFERENCE);
      }
    }

    function saveThemePreference(value) {
      try {
        window.localStorage.setItem(THEME_STORAGE_KEY, normalizeThemePreference(value));
      } catch (_e) {
        // Ignore storage errors and continue with in-memory behavior.
      }
    }

    function applyThemePreference(value) {
      const preference = normalizeThemePreference(value);
      const parts = preference.split('-');
      const mode = parts[0] === 'light' || parts[0] === 'dark' ? parts[0] : 'system';
      const colorStyle = parts.slice(1).join('-') || 'liquid-glass';
      const effectiveTheme = mode === 'system' ? resolveSystemTheme() : mode;
      document.documentElement.setAttribute('data-theme-mode', mode);
      document.documentElement.setAttribute('data-theme', effectiveTheme);
      document.documentElement.setAttribute('data-color-style', colorStyle);
      document.documentElement.setAttribute('data-theme-preference', preference);
      document.documentElement.style.colorScheme = effectiveTheme;
      return preference;
    }

    function clampOverlayEffectStrength(value) {
      const numeric = Number(value);
      if (!Number.isFinite(numeric)) {
        return DEFAULT_OVERLAY_EFFECT_STRENGTH;
      }
      return Math.max(0, Math.min(100, Math.round(numeric)));
    }

    function loadOverlayEffectStrength() {
      try {
        const storedStrength = window.localStorage.getItem(OVERLAY_EFFECT_STRENGTH_STORAGE_KEY);
        return storedStrength === null ? DEFAULT_OVERLAY_EFFECT_STRENGTH : clampOverlayEffectStrength(storedStrength);
      } catch (_e) {
        return DEFAULT_OVERLAY_EFFECT_STRENGTH;
      }
    }

    function saveOverlayEffectStrength(value) {
      const strength = clampOverlayEffectStrength(value);
      try {
        window.localStorage.setItem(OVERLAY_EFFECT_STRENGTH_STORAGE_KEY, String(strength));
      } catch (_e) {
        // Ignore storage errors and continue with in-memory behavior.
      }
      return strength;
    }

    function applyOverlayEffectStrength(value) {
      const strength = clampOverlayEffectStrength(value);
      const shellOverlay = window['OdareHubOS.ShellOverlay'];
      const controller = shellOverlay && shellOverlay.visualEffects;
      if (controller && typeof controller.setStrength === 'function') {
        controller.setStrength(strength);
      }
      document.querySelectorAll('[data-overlay-effect-strength]').forEach(function(slider) {
        slider.value = String(strength);
      });
      document.querySelectorAll('[data-overlay-effect-strength-value]').forEach(function(output) {
        output.textContent = String(strength);
      });
      return strength;
    }

    function initOverlayEffectStrengthControls() {
      const initialStrength = applyOverlayEffectStrength(loadOverlayEffectStrength());
      document.querySelectorAll('[data-overlay-effect-strength]').forEach(function(slider) {
        slider.value = String(initialStrength);
        slider.addEventListener('input', function() {
          applyOverlayEffectStrength(slider.value);
        });
        slider.addEventListener('change', function() {
          applyOverlayEffectStrength(saveOverlayEffectStrength(slider.value));
        });
      });
    }

    function handleSystemThemeChange() {
      const currentMode = document.documentElement.getAttribute('data-theme-mode');
      if (currentMode === 'system') {
        const currentPreference = document.documentElement.getAttribute('data-theme-preference') || THEME_FALLBACK_PREFERENCE;
        applyThemePreference(currentPreference);
      }
    }

    function initThemeSelector() {
      const themeSelects = Array.from(document.querySelectorAll('[data-theme-select]'));
      const initialPreference = applyThemePreference(loadThemePreference());
      if (themeSelects.length > 0) {
        themeSelects.forEach(function(themeSelect) {
          themeSelect.value = initialPreference;
          themeSelect.addEventListener('change', function() {
            const nextPreference = applyThemePreference(themeSelect.value);
            saveThemePreference(nextPreference);
            themeSelects.forEach(function(syncSelect) {
              syncSelect.value = nextPreference;
            });
          });
        });
      } else {
        // Backward-compatible fallback for legacy markup.
        const legacyThemeSelect = document.getElementById('erpThemeSelect');
        if (legacyThemeSelect) {
          legacyThemeSelect.value = initialPreference;
          legacyThemeSelect.addEventListener('change', function() {
            const nextPreference = applyThemePreference(legacyThemeSelect.value);
            saveThemePreference(nextPreference);
            legacyThemeSelect.value = nextPreference;
          });
        }
      }

      if (window.matchMedia) {
        systemThemeMediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        if (typeof systemThemeMediaQuery.addEventListener === 'function') {
          systemThemeMediaQuery.addEventListener('change', handleSystemThemeChange);
        } else if (typeof systemThemeMediaQuery.addListener === 'function') {
          systemThemeMediaQuery.addListener(handleSystemThemeChange);
        }
      }
    }

    function setSidebarOpen(open) {
      const sidebar = document.querySelector('.layout-sidebar');
      const layoutShell = document.querySelector('.layout-shell');
      const toggles = Array.from(document.querySelectorAll('[data-sidebar-toggle]'));
      const backdrop = document.getElementById('sidebarBackdrop');
      const shouldOpen = !!open;
      const isDesktop = window.matchMedia && window.matchMedia('(min-width: 901px)').matches;

      if (isDesktop && layoutShell) {
        layoutShell.classList.toggle('sidebar-collapsed', !shouldOpen);
        layoutShell.classList.remove('sidebar-peek');

        if (sidebar) {
          sidebar.classList.remove('sidebar-open');
        }

        toggles.forEach(function(toggle) {
          toggle.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
        });

        if (backdrop) {
          backdrop.hidden = true;
          backdrop.setAttribute('aria-hidden', 'true');
        }

        document.body.classList.remove('sidebar-mobile-open');
        return;
      }

      if (sidebar) {
        sidebar.classList.toggle('sidebar-open', shouldOpen);
      }

      toggles.forEach(function(toggle) {
        toggle.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
      });

      if (backdrop) {
        backdrop.hidden = !shouldOpen;
        backdrop.setAttribute('aria-hidden', shouldOpen ? 'false' : 'true');
      }

      document.body.classList.toggle('sidebar-mobile-open', shouldOpen);
    }

    function toggleSidebar() {
      const sidebar = document.querySelector('.layout-sidebar');
      const layoutShell = document.querySelector('.layout-shell');
      if (!sidebar) {
        return;
      }

      const isDesktop = window.matchMedia && window.matchMedia('(min-width: 901px)').matches;
      if (isDesktop && layoutShell) {
        setSidebarOpen(layoutShell.classList.contains('sidebar-collapsed'));
        return;
      }

      requestSidebarOpen(!sidebar.classList.contains('sidebar-open'), 'trigger');
    }

    function closeSidebarOnSmallScreen() {
      const isDesktop = window.matchMedia && window.matchMedia('(min-width: 901px)').matches;
      if (!isDesktop) {
        requestSidebarOpen(false, 'navigation');
      }
    }

    function requestSidebarOpen(open, reason) {
      const isDesktop = window.matchMedia && window.matchMedia('(min-width: 901px)').matches;
      if (!isDesktop && publicMobileSidebarAdapter && typeof publicMobileSidebarAdapter.setOpen === 'function') {
        publicMobileSidebarAdapter.setOpen(open === true, reason || (open ? 'open' : 'close'));
        return;
      }
      setSidebarOpen(open === true);
    }

    function syncSidebarViewportState() {
      const isDesktop = window.matchMedia && window.matchMedia('(min-width: 901px)').matches;
      // Desktop defaults to collapsed rail mode; mobile keeps drawer closed by default.
      setSidebarOpen(isDesktop ? false : false);
    }

    function normalizePath(path) {
      if (!path) {
        return '/';
      }
      const withSlash = path.startsWith('/') ? path : '/' + path;
      const compact = withSlash.replace(/\/+/g, '/');
      return compact.length > 1 ? compact.replace(/\/$/, '') : compact;
    }

    function normalizeSearchText(value) {
      if (!value) {
        return '';
      }
      return String(value)
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9\s/_-]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
    }

    function escapeHtml(value) {
      return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    function highlightMatch(value, query) {
      const raw = String(value || '');
      const source = raw.toLowerCase();
      const needle = String(query || '').toLowerCase();
      if (!needle || !source.includes(needle)) {
        return escapeHtml(raw);
      }
      const start = source.indexOf(needle);
      const end = start + needle.length;
      return escapeHtml(raw.slice(0, start)) + '<mark>' + escapeHtml(raw.slice(start, end)) + '</mark>' + escapeHtml(raw.slice(end));
    }

    function parseActiveRules(link) {
      const payload = link.getAttribute('data-active-rules');
      if (!payload) {
        return { exact: [], prefix: [], hash: '' };
      }

      try {
        const parsed = JSON.parse(payload);
        return {
          exact: Array.isArray(parsed.exact) ? parsed.exact : [],
          prefix: Array.isArray(parsed.prefix) ? parsed.prefix : [],
          hash: typeof parsed.hash === 'string' ? parsed.hash : '',
        };
      } catch (_e) {
        return { exact: [], prefix: [], hash: '' };
      }
    }

    function loadSidebarUsageState() {
      try {
        const raw = window.localStorage.getItem(SIDEBAR_USAGE_STORAGE_KEY);
        if (!raw) {
          return { visits: {}, recent: [] };
        }

        const parsed = JSON.parse(raw);
        return {
          visits: parsed && typeof parsed.visits === 'object' && parsed.visits !== null ? parsed.visits : {},
          recent: Array.isArray(parsed && parsed.recent) ? parsed.recent.map(String) : [],
        };
      } catch (_e) {
        return { visits: {}, recent: [] };
      }
    }

    function saveSidebarUsageState(state) {
      try {
        window.localStorage.setItem(SIDEBAR_USAGE_STORAGE_KEY, JSON.stringify(state));
      } catch (_e) {
        // Ignore storage failures.
      }
    }

    function pruneSidebarUsageState(state) {
      const recent = Array.isArray(state.recent) ? state.recent.slice(0, SIDEBAR_RECENT_LIMIT) : [];
      const keepKeys = new Set(recent);
      const visits = {};

      Object.entries(state.visits || {}).forEach(function(entry) {
        const usageKey = String(entry[0] || '').trim();
        const payload = entry[1];
        if (!usageKey || !payload || typeof payload !== 'object') {
          return;
        }

        const count = Number(payload.count || 0);
        const last = Number(payload.last || 0);
        if (keepKeys.has(usageKey) || count > 0) {
          visits[usageKey] = {
            count: Math.min(Math.max(count, 0), 999),
            last: Math.max(last, 0),
          };
        }
      });

      return { visits: visits, recent: recent };
    }

    function touchSidebarUsage(usageKey, incrementCount) {
      const key = String(usageKey || '').trim();
      if (!key) {
        return;
      }

      const state = loadSidebarUsageState();
      const visits = state.visits || {};
      const record = visits[key] && typeof visits[key] === 'object' ? visits[key] : { count: 0, last: 0 };

      if (incrementCount) {
        record.count = Math.min(Number(record.count || 0) + 1, 999);
      }

      record.last = Date.now();
      visits[key] = record;
      state.visits = visits;
      state.recent = [key].concat((state.recent || []).filter(function(existing) {
        return existing !== key;
      })).slice(0, SIDEBAR_RECENT_LIMIT);

      saveSidebarUsageState(pruneSidebarUsageState(state));
    }

    function loadSidebarOverflowState() {
      try {
        const raw = window.localStorage.getItem(SIDEBAR_OVERFLOW_STORAGE_KEY);
        if (!raw) {
          return {};
        }

        const parsed = JSON.parse(raw);
        return parsed && typeof parsed === 'object' ? parsed : {};
      } catch (_e) {
        return {};
      }
    }

    function saveSidebarOverflowState(state) {
      try {
        window.localStorage.setItem(SIDEBAR_OVERFLOW_STORAGE_KEY, JSON.stringify(state));
      } catch (_e) {
        // Ignore storage failures.
      }
    }

    function loadSidebarDisclosureState() {
      try {
        const raw = window.localStorage.getItem(SIDEBAR_DISCLOSURE_STORAGE_KEY);
        if (!raw) {
          return {};
        }

        const parsed = JSON.parse(raw);
        return parsed && typeof parsed === 'object' ? parsed : {};
      } catch (_e) {
        return {};
      }
    }

    function saveSidebarDisclosureState(state) {
      try {
        window.localStorage.setItem(SIDEBAR_DISCLOSURE_STORAGE_KEY, JSON.stringify(state));
      } catch (_e) {
        // Ignore storage failures.
      }
    }

    function sidebarRecentScore(state, usageKey) {
      const recent = Array.isArray(state.recent) ? state.recent : [];
      const index = recent.indexOf(usageKey);
      if (index === -1) {
        return 0;
      }

      return Math.max(0, 36 - (index * 6));
    }

    function sidebarFrequentScore(state, usageKey) {
      const visits = state.visits || {};
      const record = visits[usageKey];
      if (!record || typeof record !== 'object') {
        return 0;
      }

      return Math.min(Number(record.count || 0), 20) * 4;
    }

    function refreshSidebarOverflow() {
      const usageState = loadSidebarUsageState();
      const overflowState = loadSidebarOverflowState();

      document.querySelectorAll('.sidebar-group[data-overflow-enabled="1"]').forEach(function(group) {
        const toggle = group.querySelector('.sidebar-overflow-toggle');
        const links = Array.from(group.querySelectorAll('.sidebar-group-links > .sidebar-link'));
        const limit = Math.max(parseInt(group.dataset.overflowLimit || '0', 10) || 0, 0);
        const storageKey = String(group.dataset.overflowKey || '');

        if (!toggle || limit <= 0) {
          return;
        }

        const scored = links.map(function(link, index) {
          const usageKey = String(link.dataset.usageKey || link.getAttribute('href') || link.dataset.itemKey || '').trim();
          const priority = parseInt(link.dataset.priority || '0', 10) || 0;
          const order = parseInt(link.dataset.order || String((index + 1) * 10), 10) || ((index + 1) * 10);
          const usageWeight = parseFloat(link.dataset.usageWeight || '1') || 1;
          const isActive = link.classList.contains('is-active') || link.dataset.isActive === '1';
          const alwaysVisible = link.dataset.alwaysVisible === '1';
          const defaultVisible = link.dataset.defaultVisible === '1';
          const score =
            (isActive ? 100000 : 0) +
            (alwaysVisible ? 50000 : 0) +
            (defaultVisible ? 400 : 0) +
            (priority * 100) +
            ((sidebarRecentScore(usageState, usageKey) + sidebarFrequentScore(usageState, usageKey)) * usageWeight) -
            (order / 1000);

          return {
            key: String(link.dataset.itemKey || usageKey || index),
            usageKey: usageKey,
            node: link,
            index: index,
            order: order,
            score: score,
            isActive: isActive,
            alwaysVisible: alwaysVisible,
          };
        });

        const requiredKeys = new Set();
        scored.forEach(function(item) {
          if (item.isActive || item.alwaysVisible) {
            requiredKeys.add(item.key);
          }
        });

        const ranked = scored.slice().sort(function(left, right) {
          if (left.score !== right.score) {
            return right.score - left.score;
          }
          if (left.order !== right.order) {
            return left.order - right.order;
          }
          return left.index - right.index;
        });

        const visibleKeys = new Set(requiredKeys);
        ranked.forEach(function(item) {
          if (visibleKeys.has(item.key)) {
            return;
          }
          if (visibleKeys.size >= limit && visibleKeys.size >= requiredKeys.size) {
            return;
          }
          visibleKeys.add(item.key);
        });

        if (visibleKeys.size === 0 && ranked[0]) {
          visibleKeys.add(ranked[0].key);
        }

        let hiddenCount = 0;
        scored.forEach(function(item) {
          const shouldHide = !visibleKeys.has(item.key);
          item.node.classList.toggle('sidebar-link-overflow-hidden', shouldHide);
          if (shouldHide) {
            hiddenCount += 1;
          }
        });

        const expanded = hiddenCount > 0 && !!overflowState[storageKey];
        group.classList.toggle('sidebar-group-overflow-expanded', expanded);
        group.classList.toggle('sidebar-group-has-hidden-links', hiddenCount > 0);
        toggle.hidden = hiddenCount === 0;
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        toggle.textContent = expanded
          ? (toggle.dataset.lessLabel || '- Less')
          : ((toggle.dataset.moreLabel || '+ More') + (hiddenCount > 0 ? ' (' + hiddenCount + ')' : ''));

        if (toggle.dataset.bound !== '1') {
          toggle.addEventListener('click', function() {
            const state = loadSidebarOverflowState();
            const nextExpanded = !group.classList.contains('sidebar-group-overflow-expanded');
            state[storageKey] = nextExpanded;
            saveSidebarOverflowState(state);
            refreshSidebarOverflow();
          });
          toggle.dataset.bound = '1';
        }
      });
    }

    function syncSidebarDisclosureState() {
      const storedState = loadSidebarDisclosureState();
      const groups = Array.from(document.querySelectorAll('.sidebar-group'));
      const activeGroup = groups.find(function(group) {
        return group.classList.contains('sidebar-group-has-active');
      }) || null;
      const activeGroupKey = activeGroup ? String(activeGroup.dataset.groupKey || '').trim() : '';

      groups.forEach(function(group) {
        const groupKey = String(group.dataset.groupKey || '').trim();
        const toggle = group.querySelector('.sidebar-group-toggle');
        const hasActive = group.classList.contains('sidebar-group-has-active');
        const hasStoredState = groupKey !== '' && Object.prototype.hasOwnProperty.call(storedState, groupKey);
        const shouldOpen = hasActive
          ? true
          : (activeGroupKey !== ''
              ? false
              : (hasStoredState ? !!storedState[groupKey] : false));

        group.open = shouldOpen;
        group.dataset.open = shouldOpen ? '1' : '0';

        if (toggle) {
          toggle.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
        }

        if (group.dataset.disclosureBound !== '1') {
          group.addEventListener('toggle', function() {
            const nextOpen = !!group.open;
            const isActiveGroup = group.classList.contains('sidebar-group-has-active');
            const wasForcedClose = sidebarForcedCloseGroups.has(group);
            if (wasForcedClose) {
              sidebarForcedCloseGroups.delete(group);
            }

            if (isActiveGroup && !nextOpen && !wasForcedClose) {
              group.open = true;
              return;
            }

            if (nextOpen) {
              groups.forEach(function(otherGroup) {
                if (otherGroup === group) {
                  return;
                }

                sidebarForcedCloseGroups.add(otherGroup);
                otherGroup.open = false;
                otherGroup.dataset.open = '0';
                const otherToggle = otherGroup.querySelector('.sidebar-group-toggle');
                if (otherToggle) {
                  otherToggle.setAttribute('aria-expanded', 'false');
                }
              });
            }

            group.dataset.open = nextOpen ? '1' : '0';
            if (toggle) {
              toggle.setAttribute('aria-expanded', nextOpen ? 'true' : 'false');
            }

            if (groupKey !== '') {
              const nextState = loadSidebarDisclosureState();
              Object.keys(nextState).forEach(function(existingKey) {
                nextState[existingKey] = false;
              });
              nextState[groupKey] = nextOpen;
              saveSidebarDisclosureState(nextState);
            }

            refreshSidebarOverflow();
          });
          group.dataset.disclosureBound = '1';
        }
      });
    }

    function syncSidebarActiveState() {
      const currentUrl = new URL(window.location.href);
      const currentPath = normalizePath(currentUrl.pathname);
      const currentHash = currentUrl.hash.replace(/^#/, '');

      document.querySelectorAll('.sidebar-group').forEach(group => {
        group.classList.remove('sidebar-group-has-active');
      });

      document.querySelectorAll('.sidebar-link').forEach(link => {
        const rules = parseActiveRules(link);
        const exact = rules.exact.map(normalizePath);
        const prefix = rules.prefix.map(normalizePath).map(path => path.endsWith('/') ? path : path + '/');
        let isActive = false;

        if (rules.hash !== '') {
          isActive = exact.includes(currentPath) && currentHash === rules.hash;
        } else {
          isActive = exact.includes(currentPath) || prefix.some(path => currentPath.startsWith(path));
        }

        link.classList.toggle('is-active', isActive);

        if (isActive) {
          const group = link.closest('.sidebar-group');
          if (group) {
            group.classList.add('sidebar-group-has-active');
          }
        }
      });

      syncSidebarDisclosureState();
      refreshSidebarOverflow();
    }

    document.addEventListener('DOMContentLoaded', function() {
      initThemeSelector();
      initOverlayEffectStrengthControls();
      syncSidebarViewportState();

      const publicMobileSidebarAdapterFactory = window.OdareHubOS
        && window.OdareHubOS.ShellOverlayAdapters
        && window.OdareHubOS.ShellOverlayAdapters.createPublicMobileSidebarDrawerAdapter;
      publicMobileSidebarAdapter = typeof publicMobileSidebarAdapterFactory === 'function'
        ? publicMobileSidebarAdapterFactory({
          triggerSelector: '[data-sidebar-toggle]',
          surfaceSelector: '.layout-sidebar',
          backdropId: 'sidebarBackdrop',
          setLegacyOpen: setSidebarOpen
        })
        : null;

      const sidebarToggles = Array.from(document.querySelectorAll('[data-sidebar-toggle]'));
      sidebarToggles.forEach(function(sidebarToggle) {
        sidebarToggle.addEventListener('click', toggleSidebar);
      });

      const layoutShell = document.querySelector('.layout-shell');
      const layoutSidebar = document.querySelector('.layout-sidebar');
      if (layoutShell && layoutSidebar) {
        layoutSidebar.addEventListener('mouseenter', function() {
          const isDesktop = window.matchMedia && window.matchMedia('(min-width: 901px)').matches;
          if (isDesktop && layoutShell.classList.contains('sidebar-collapsed')) {
            layoutShell.classList.add('sidebar-peek');
          }
        });

        layoutSidebar.addEventListener('mouseleave', function() {
          layoutShell.classList.remove('sidebar-peek');
        });
      }

      document.querySelectorAll('.sidebar-link').forEach(link => {
        link.addEventListener('click', function() {
          touchSidebarUsage(link.dataset.usageKey || link.getAttribute('href') || link.dataset.itemKey || '', true);
          closeSidebarOnSmallScreen();
        });
      });
      const activeSidebarLink = document.querySelector('.sidebar-link.is-active');
      if (activeSidebarLink) {
        touchSidebarUsage(activeSidebarLink.dataset.usageKey || activeSidebarLink.getAttribute('href') || activeSidebarLink.dataset.itemKey || '', false);
      }

      // Topbar search setup
      const topbarSearchInput = document.getElementById('topbarSearchInput');
      const topbarSearchResults = document.getElementById('topbarSearchResults');
      const topbarSearchResultsList = document.getElementById('topbarSearchResultsList');
      const topbarSearchNoMatch = document.getElementById('topbarSearchNoMatch');
      const topbarSearchScanBtn = document.getElementById('topbarScanBtn') || document.getElementById('topbarSearchScanBtn');
      const topbarSearchClearBtn = document.getElementById('topbarSearchClear');
      const topbarSearchKbd = document.getElementById('topbarSearchKbd');
      const topbarSearchWrap = document.querySelector('.topbar-search');
      let topbarSearchDebounceTimer = null;
      let topbarSearchAttempted = false;
      let topbarSearchActiveIndex = -1;
      let topbarSearchInflight = 0;
      let topbarSearchResultsAdapter = null;

      // Adapt ⌘/Ctrl label to the platform
      if (topbarSearchKbd) {
        const isMac = /Mac|iPhone|iPad|iPod/i.test(navigator.platform || navigator.userAgent || '');
        const modKey = topbarSearchKbd.querySelector('.topbar-search-kbd-key');
        if (modKey && !isMac) {
          modKey.textContent = 'Ctrl';
        }
      }

      function syncTopbarSearchVisualState(rawValue) {
        if (!topbarSearchWrap) {
          return;
        }
        const value = String(rawValue || '').trim();
        const active = value.length >= 1;
        topbarSearchWrap.classList.toggle('is-query-active', active);
        if (topbarSearchClearBtn) {
          topbarSearchClearBtn.hidden = !active;
        }
      }

      function setSearchState(state) {
        if (state === 'idle') {
          topbarSearchAttempted = false;
          topbarSearchActiveIndex = -1;
        }
        if (topbarSearchWrap) topbarSearchWrap.dataset.searchState = state;
        if (topbarSearchInput) {
          topbarSearchInput.setAttribute('aria-expanded', state === 'results' ? 'true' : 'false');
          topbarSearchInput.removeAttribute('aria-activedescendant');
        }
        if ((state === 'idle' || state === 'empty') && topbarSearchResultsList) {
          topbarSearchResultsList.innerHTML = '';
        }
        if (topbarSearchNoMatch) {
          topbarSearchNoMatch.hidden = !(state === 'empty' && topbarSearchAttempted);
        }
        if (topbarSearchResultsAdapter && typeof topbarSearchResultsAdapter.syncState === 'function') {
          topbarSearchResultsAdapter.syncState(state, 'search-state');
        }
      }

      function updateActiveSearchResult() {
        if (!topbarSearchResultsList) {
          return;
        }
        const results = Array.from(topbarSearchResultsList.querySelectorAll('.topbar-search-result'));
        results.forEach(function(el, idx) {
          const isActive = idx === topbarSearchActiveIndex;
          el.classList.toggle('is-active', isActive);
          if (isActive) {
            el.setAttribute('aria-selected', 'true');
            if (el.id) { topbarSearchInput && topbarSearchInput.setAttribute('aria-activedescendant', el.id); }
            if (typeof el.scrollIntoView === 'function') {
              el.scrollIntoView({ block: 'nearest' });
            }
          } else {
            el.removeAttribute('aria-selected');
          }
        });
      }

      function moveActiveSearchResult(delta) {
        if (!topbarSearchResultsList) {
          return;
        }
        const results = topbarSearchResultsList.querySelectorAll('.topbar-search-result');
        if (results.length === 0) {
          return;
        }
        if (topbarSearchActiveIndex < 0) {
          topbarSearchActiveIndex = delta > 0 ? 0 : results.length - 1;
        } else {
          topbarSearchActiveIndex = (topbarSearchActiveIndex + delta + results.length) % results.length;
        }
        updateActiveSearchResult();
      }

      function renderTopbarSearchResults(items, query) {
        if (!topbarSearchResultsList || !topbarSearchResults) {
          return;
        }

        const normalizedQuery = String(query || '').trim();
        if (normalizedQuery.length < 2) {
          setSearchState('idle');
          syncSidebarActiveState();
          return;
        }

        topbarSearchResultsList.innerHTML = '';
        topbarSearchActiveIndex = -1;

        if (!items || items.length === 0) {
          topbarSearchAttempted = true;
          setSearchState('empty');
          return;
        }

        setSearchState('results');

        // Group items by type
        const grouped = {};
        items.forEach(function(item) {
          const type = String(item.type || 'other');
          if (!grouped[type]) {
            grouped[type] = [];
          }
          grouped[type].push(item);
        });

        let resultSeq = 0;
        // Render each group
        Object.keys(grouped).forEach(function(type) {
          const groupItems = grouped[type];

          // Add group title
          const groupTitle = document.createElement('div');
          groupTitle.className = 'topbar-search-group-title';
          groupTitle.textContent = String(groupItems[0].group_label || type);
          topbarSearchResultsList.appendChild(groupTitle);

          // Add group items
          const group = document.createElement('div');
          group.className = 'topbar-search-group';

          groupItems.forEach(function(item) {
            const result = document.createElement('a');
            result.className = 'topbar-search-result';
            result.href = String(item.url || '/');
            result.id = 'topbarSearchResult-' + (resultSeq++);
            result.setAttribute('role', 'option');

            const label = String(item.label || '');
            const status = String(item.status || '');
            const meta = String(item.meta || '');

            let html = '<span class="topbar-search-result-label">' + (query ? highlightMatch(label, query) : escapeHtml(label)) + '</span>';

            if (status || meta) {
              html += '<span class="topbar-search-result-meta">';
              if (status) {
                html += '<span class="topbar-search-result-status">' + escapeHtml(status) + '</span>';
              }
              if (meta) {
                html += '<span>' + escapeHtml(meta) + '</span>';
              }
              html += '</span>';
            }

            result.innerHTML = html;
            result.addEventListener('mouseenter', function() {
              const rows = Array.from(topbarSearchResultsList.querySelectorAll('.topbar-search-result'));
              topbarSearchActiveIndex = rows.indexOf(result);
              updateActiveSearchResult();
            });
            result.addEventListener('click', function() {
              if (topbarSearchInput) topbarSearchInput.value = '';
              syncTopbarSearchVisualState('');
              setSearchState('idle');
            });
            group.appendChild(result);
          });

          topbarSearchResultsList.appendChild(group);
        });
      }

      function fetchTopbarSearchResults(query) {
        if (!topbarSearchInput || !topbarSearchResults || query.trim().length < 2) {
          setSearchState('idle');
          syncSidebarActiveState();
          return;
        }

        const url = '/api/search?q=' + encodeURIComponent(query);

        topbarSearchInflight += 1;
        if (topbarSearchWrap) topbarSearchWrap.classList.add('is-loading');

        const settle = function() {
          topbarSearchInflight = Math.max(0, topbarSearchInflight - 1);
          if (topbarSearchInflight === 0 && topbarSearchWrap) {
            topbarSearchWrap.classList.remove('is-loading');
          }
        };

        fetch(url)
          .then(function(response) {
            if (!response.ok) {
              throw new Error('Search failed');
            }
            return response.json();
          })
          .then(function(data) {
            if (!topbarSearchInput || topbarSearchInput.value.trim().length < 2) {
              setSearchState('idle');
              syncSidebarActiveState();
              return;
            }
            if (data.success && data.groups && Array.isArray(data.groups)) {
              const allItems = [];
              data.groups.forEach(function(group) {
                // form_endpoints are POST-only handlers — not navigable, skip them
                if (group.type === 'form_endpoints') {
                  return;
                }
                group.items.forEach(function(item) {
                  item.group_label = group.label;
                  item.type = group.type;
                  allItems.push(item);
                });
              });
              renderTopbarSearchResults(allItems, query);
            } else {
              topbarSearchAttempted = true;
              setSearchState('empty');
            }
          })
          .catch(function(_err) {
            if (!topbarSearchInput || topbarSearchInput.value.trim().length < 2) {
              setSearchState('idle');
              syncSidebarActiveState();
              return;
            }
            topbarSearchAttempted = true;
            setSearchState('empty');
          })
          .then(settle, settle);
      }

      function runTopbarSearch(rawValue) {
        if (!topbarSearchInput) {
          return;
        }
        const value = String(rawValue || '').trim();
        topbarSearchInput.value = value;
        syncTopbarSearchVisualState(value);
        topbarSearchInput.focus();
        if (value.length >= 2) {
          fetchTopbarSearchResults(value);
        } else {
          setSearchState('idle');
        }
      }

      function promptManualScanValue() {
        const manual = window.prompt(QR_I18N.prompt_manual);
        if (manual !== null) {
          runTopbarSearch(manual);
          return true;
        }
        return false;
      }

      function tryClipboardScanValue() {
        if (!(navigator.clipboard && typeof navigator.clipboard.readText === 'function')) {
          return Promise.resolve(false);
        }

        return navigator.clipboard.readText()
          .then(function(text) {
            const value = String(text || '').trim();
            if (value.length >= 2) {
              runTopbarSearch(value);
              return true;
            }
            return false;
          })
          .catch(function() {
            return false;
          });
      }

      let jsQrLoaderPromise = null;
      function loadJsQrDecoder() {
        if (typeof window.jsQR === 'function') {
          return Promise.resolve(window.jsQR);
        }
        if (jsQrLoaderPromise) {
          return jsQrLoaderPromise;
        }

        jsQrLoaderPromise = new Promise(function(resolve, reject) {
          const script = document.createElement('script');
          script.src = 'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js';
          script.async = true;
          script.onload = function() {
            if (typeof window.jsQR === 'function') {
              resolve(window.jsQR);
              return;
            }
            reject(new Error('jsQR not available'));
          };
          script.onerror = function() {
            reject(new Error('Unable to load jsQR'));
          };
          document.head.appendChild(script);
        });

        return jsQrLoaderPromise;
      }

      function launchCameraScan() {
        if (!(navigator.mediaDevices && typeof navigator.mediaDevices.getUserMedia === 'function')) {
          return Promise.resolve({ ok: false, reason: 'camera_unavailable' });
        }

        return new Promise(function(resolve) {
          const cameraScanAdapterFactory = window.OdareHubOS
            && window.OdareHubOS.ShellOverlayAdapters
            && window.OdareHubOS.ShellOverlayAdapters.createCameraScanOverlayAdapter;
          const cameraScanAdapter = typeof cameraScanAdapterFactory === 'function'
            ? cameraScanAdapterFactory({
              sourceSurface: 'public',
              surfaceClass: 'camera-scan-overlay',
              onControllerClose: function() { cleanup({ ok: false, reason: 'outside-click' }); }
            })
            : null;
          if (cameraScanAdapter && typeof cameraScanAdapter.open === 'function') {
            cameraScanAdapter.open('launch');
          }

          let stream = null;
          let rafId = 0;
          let closed = false;
          let detector = null;
          const BarcodeDetectorCtor = window.BarcodeDetector;

          const frameCanvas = document.createElement('canvas');
          const frameCtx = frameCanvas.getContext('2d', { willReadFrequently: true });

          const overlay = document.createElement('div');
          overlay.className = 'camera-scan-overlay';
          overlay.style.cssText = 'position:fixed;inset:0;background:var(--scan-overlay-bg);z-index:10050;display:flex;align-items:center;justify-content:center;padding:18px;';

          const panel = document.createElement('div');
          panel.style.cssText = 'width:min(520px,92vw);background:var(--scan-panel-bg);border:1px solid var(--scan-panel-border);border-radius:12px;box-shadow:var(--scan-panel-shadow);padding:12px;';

          const title = document.createElement('div');
          title.textContent = QR_I18N.panel_title;
          title.style.cssText = 'font-weight:600;margin-bottom:8px;';

          const helper = document.createElement('div');
          helper.textContent = QR_I18N.helper_text;
          helper.style.cssText = 'font-size:12px;color:#9fb1d8;margin-bottom:10px;';

          const videoWrap = document.createElement('div');
          videoWrap.style.cssText = 'position:relative;border-radius:10px;overflow:hidden;background:var(--scan-video-wrap-bg);border:1px solid var(--glass-border);';

          const video = document.createElement('video');
          video.setAttribute('autoplay', '');
          video.setAttribute('playsinline', '');
          video.muted = true;
          video.style.cssText = 'width:100%;max-height:54vh;display:block;background:var(--scan-video-bg);';

          const status = document.createElement('div');
          status.textContent = QR_I18N.starting_camera;
          status.style.cssText = 'font-size:12px;color:#9fb1d8;margin-top:8px;min-height:16px;';

          const actions = document.createElement('div');
          actions.style.cssText = 'display:flex;gap:8px;justify-content:flex-end;margin-top:10px;';

          const cancelBtn = document.createElement('button');
          cancelBtn.type = 'button';
          cancelBtn.textContent = QR_I18N.btn_cancel;
          cancelBtn.style.cssText = 'padding:6px 10px;border-radius:8px;border:1px solid rgba(197,220,255,.25);background:rgba(255,255,255,.06);color:#e8eefc;cursor:pointer;';

          const manualBtn = document.createElement('button');
          manualBtn.type = 'button';
          manualBtn.textContent = QR_I18N.btn_enter_value;
          manualBtn.style.cssText = 'padding:6px 10px;border-radius:8px;border:1px solid rgba(197,220,255,.25);background:rgba(255,255,255,.06);color:#e8eefc;cursor:pointer;';

          videoWrap.appendChild(video);
          actions.appendChild(manualBtn);
          actions.appendChild(cancelBtn);
          panel.appendChild(title);
          panel.appendChild(helper);
          panel.appendChild(videoWrap);
          panel.appendChild(status);
          panel.appendChild(actions);
          overlay.appendChild(panel);
          document.body.appendChild(overlay);

          function cleanup(result) {
            if (closed) {
              return;
            }
            closed = true;

            if (rafId) {
              window.cancelAnimationFrame(rafId);
              rafId = 0;
            }
            if (stream) {
              stream.getTracks().forEach(function(track) {
                try { track.stop(); } catch (_e) {}
              });
            }
            if (overlay.parentNode) {
              overlay.parentNode.removeChild(overlay);
            }
            if (cameraScanAdapter && typeof cameraScanAdapter.close === 'function') {
              cameraScanAdapter.close(result && result.reason ? result.reason : 'cleanup');
            }
            resolve(result);
          }

          cancelBtn.addEventListener('click', function() {
            cleanup({ ok: false, reason: 'cancelled' });
          });

          manualBtn.addEventListener('click', function() {
            cleanup({ ok: false, reason: 'manual' });
          });

          navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false })
            .then(function(mediaStream) {
              stream = mediaStream;
              video.srcObject = stream;

              const runScanLoop = function(scanOneFrame) {
                status.textContent = QR_I18N.scanning;

                const startAt = Date.now();

                function scanFrame() {
                  if (closed) {
                    return;
                  }

                  if (Date.now() - startAt > 30000) {
                    status.textContent = QR_I18N.no_code_detected;
                    return;
                  }

                  scanOneFrame()
                    .then(function(raw) {
                      if (closed) {
                        return;
                      }
                      const value = String(raw || '').trim();
                      if (value.length >= 2) {
                        cleanup({ ok: true, value: value });
                        return;
                      }
                      rafId = window.requestAnimationFrame(scanFrame);
                    })
                    .catch(function() {
                      rafId = window.requestAnimationFrame(scanFrame);
                    });
                }

                rafId = window.requestAnimationFrame(scanFrame);
              };

              if (typeof BarcodeDetectorCtor === 'function') {
                detector = new BarcodeDetectorCtor({ formats: ['qr_code', 'code_128', 'code_39', 'ean_13', 'ean_8', 'upc_a', 'upc_e'] });
                runScanLoop(function() {
                  return detector.detect(video)
                    .then(function(codes) {
                      if (Array.isArray(codes) && codes.length > 0) {
                        return String((codes[0] && codes[0].rawValue) || '');
                      }
                      return '';
                    });
                });
                return;
              }

              loadJsQrDecoder()
                .then(function(jsQrFn) {
                  if (typeof jsQrFn !== 'function') {
                    status.textContent = QR_I18N.scanner_unavailable;
                    return;
                  }

                  runScanLoop(function() {
                    return new Promise(function(resolveFrame) {
                      if (!frameCtx || video.readyState < 2) {
                        resolveFrame('');
                        return;
                      }

                      const vw = video.videoWidth || 0;
                      const vh = video.videoHeight || 0;
                      if (vw <= 0 || vh <= 0) {
                        resolveFrame('');
                        return;
                      }

                      frameCanvas.width = vw;
                      frameCanvas.height = vh;
                      frameCtx.drawImage(video, 0, 0, vw, vh);

                      try {
                        const imageData = frameCtx.getImageData(0, 0, vw, vh);
                        const code = jsQrFn(imageData.data, imageData.width, imageData.height, { inversionAttempts: 'attemptBoth' });
                        resolveFrame(code && code.data ? String(code.data) : '');
                      } catch (_e) {
                        resolveFrame('');
                      }
                    });
                  });
                })
                .catch(function() {
                  status.textContent = QR_I18N.scanner_unavailable;
                });
            })
            .catch(function() {
              cleanup({ ok: false, reason: 'permission_or_device_error' });
            });
        });
      }

      if (topbarSearchInput) {
        const topbarSearchResultsAdapterFactory = window.OdareHubOS
          && window.OdareHubOS.ShellOverlayAdapters
          && window.OdareHubOS.ShellOverlayAdapters.createPublicTopbarSearchResultsAdapter;
        topbarSearchResultsAdapter = typeof topbarSearchResultsAdapterFactory === 'function'
          ? topbarSearchResultsAdapterFactory({
            triggerId: 'topbarSearchInput',
            surfaceId: 'topbarSearchResults',
            onControllerClose: function() { setSearchState('idle'); }
          })
          : null;
        setSearchState('idle');
        syncTopbarSearchVisualState(topbarSearchInput.value);

        topbarSearchInput.addEventListener('input', function() {
          clearTimeout(topbarSearchDebounceTimer);
          const query = topbarSearchInput.value.trim();
          syncTopbarSearchVisualState(query);

          if (query.length < 2) {
            setSearchState('idle');
            return;
          }

          topbarSearchDebounceTimer = setTimeout(function() {
            fetchTopbarSearchResults(query);
          }, 300);
        });

        topbarSearchInput.addEventListener('keydown', function(event) {
          if (event.key === 'Escape') {
            if (topbarSearchInput.value) {
              topbarSearchInput.value = '';
              syncTopbarSearchVisualState('');
              setSearchState('idle');
              return;
            }
            setSearchState('idle');
            topbarSearchInput.blur();
            return;
          }

          if (event.key === 'ArrowDown') {
            event.preventDefault();
            moveActiveSearchResult(1);
            return;
          }

          if (event.key === 'ArrowUp') {
            event.preventDefault();
            moveActiveSearchResult(-1);
            return;
          }

          if (event.key === 'Home' && topbarSearchResultsList && topbarSearchResultsList.querySelector('.topbar-search-result')) {
            event.preventDefault();
            topbarSearchActiveIndex = 0;
            updateActiveSearchResult();
            return;
          }

          if (event.key === 'End' && topbarSearchResultsList) {
            const rows = topbarSearchResultsList.querySelectorAll('.topbar-search-result');
            if (rows.length) {
              event.preventDefault();
              topbarSearchActiveIndex = rows.length - 1;
              updateActiveSearchResult();
              return;
            }
          }

          if (event.key === 'Enter') {
            const rows = topbarSearchResultsList ? topbarSearchResultsList.querySelectorAll('.topbar-search-result') : [];
            const target = topbarSearchActiveIndex >= 0 && rows[topbarSearchActiveIndex]
              ? rows[topbarSearchActiveIndex]
              : (rows[0] || null);
            if (target) {
              event.preventDefault();
              window.location.href = target.getAttribute('href') || '/';
            }
          }
        });

        topbarSearchInput.addEventListener('blur', function() {
          setTimeout(function() {
            // Only close if focus has truly left the search surface (e.g., not clicked a result).
            if (topbarSearchWrap && topbarSearchWrap.contains(document.activeElement)) {
              return;
            }
            setSearchState('idle');
          }, 150);
        });

        topbarSearchInput.addEventListener('focus', function() {
          const query = topbarSearchInput.value.trim();
          if (query.length >= 2) {
            fetchTopbarSearchResults(query);
          }
        });

        // Clear button
        if (topbarSearchClearBtn) {
          topbarSearchClearBtn.addEventListener('mousedown', function(event) {
            // Prevent input blur flicker while clearing.
            event.preventDefault();
          });
          topbarSearchClearBtn.addEventListener('click', function() {
            topbarSearchInput.value = '';
            syncTopbarSearchVisualState('');
            setSearchState('idle');
            topbarSearchInput.focus();
          });
        }

        // Global ⌘/Ctrl+K to focus search
        document.addEventListener('keydown', function(event) {
          const isMod = event.metaKey || event.ctrlKey;
          if (!isMod) return;
          if ((event.key || '').toLowerCase() !== 'k') return;
          const target = event.target;
          const isEditable = target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable);
          if (isEditable && target !== topbarSearchInput) {
            // Don't steal ⌘K from other editable fields.
            return;
          }
          event.preventDefault();
          topbarSearchInput.focus();
          topbarSearchInput.select();
        });
      }

      if (topbarSearchScanBtn) {
        topbarSearchScanBtn.addEventListener('click', function() {
          launchCameraScan()
            .then(function(result) {
              if (result && result.ok && result.value) {
                runTopbarSearch(result.value);
                return true;
              }

              if (result && result.reason === 'manual') {
                return promptManualScanValue();
              }

              if (result && result.reason === 'cancelled') {
                return false;
              }

              return tryClipboardScanValue().then(function(fromClipboard) {
                if (fromClipboard) {
                  return true;
                }
                return false;
              });
            })
            .catch(function() {
              tryClipboardScanValue().then(function(fromClipboard) {
                if (fromClipboard) {
                  return;
                }
              });
            });
        });
      }

      syncSidebarActiveState();
    syncSidebarViewportState();

      const topbarOverflowBtn = document.getElementById('topbarOverflowBtn');
      const topbarOverflowDropdown = document.getElementById('topbarOverflowDropdown');
      const topbarCanonicalBtn = document.getElementById('topbarCanonicalBtn');
      const topbarCanonicalDropdown = document.getElementById('topbarCanonicalDropdown');
      const topbarCanonicalClose = document.getElementById('topbarCanonicalClose');
      const topbarCanonicalBackdrop = document.getElementById('topbarCanonicalBackdrop');
      const topbarActionBtn = document.getElementById('topbarActionBtn');
      const topbarActionDropdown = document.getElementById('topbarActionDropdown');
      const topbarActionClose = document.getElementById('topbarActionClose');
      const notifButton = document.querySelector('[data-notif-toggle]');
      const notifDropdown = document.querySelector('[data-notif-dropdown]');
      const mobileScanToggle = document.getElementById('mobileScanToggle');
      const mobileSearchToggle = document.getElementById('mobileSearchToggle');

      function setOverflowOpen(open) {
        if (!topbarOverflowBtn || !topbarOverflowDropdown) {
          return;
        }
        topbarOverflowBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        topbarOverflowDropdown.hidden = !open;
        topbarOverflowDropdown.classList.toggle('open', open);
      }

      function setCanonicalOpen(open) {
        if (!topbarCanonicalBtn || !topbarCanonicalDropdown) {
          return;
        }
        topbarCanonicalBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        topbarCanonicalDropdown.hidden = !open;
        topbarCanonicalDropdown.classList.toggle('open', open);
        if (topbarCanonicalBackdrop) {
          topbarCanonicalBackdrop.hidden = !open;
        }
      }

      function setActionOpen(open) {
        if (!topbarActionBtn || !topbarActionDropdown) {
          return;
        }
        topbarActionBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        topbarActionDropdown.hidden = !open;
        topbarActionDropdown.classList.toggle('open', open);
      }

      function setNotifOpen(open) {
        if (!notifButton || !notifDropdown) {
          return;
        }
        notifButton.setAttribute('aria-expanded', open ? 'true' : 'false');
        notifDropdown.hidden = !open;
        notifDropdown.classList.toggle('open', open);
      }

      const topbarOverflowAdapterFactory = window.OdareHubOS
        && window.OdareHubOS.ShellOverlayAdapters
        && window.OdareHubOS.ShellOverlayAdapters.createPublicAccountOverflowAdapter;
      const topbarOverflowAdapter = typeof topbarOverflowAdapterFactory === 'function'
        ? topbarOverflowAdapterFactory({
          triggerId: 'topbarOverflowBtn',
          surfaceId: 'topbarOverflowDropdown',
          setLegacyOpen: setOverflowOpen
        })
        : null;

      function requestOverflowOpen(open, reason) {
        if (topbarOverflowAdapter && typeof topbarOverflowAdapter.setOpen === 'function') {
          topbarOverflowAdapter.setOpen(open, reason || (open ? 'open' : 'close'));
          return;
        }
        setOverflowOpen(open);
      }

      const notificationsDropdownAdapterFactory = window.OdareHubOS
        && window.OdareHubOS.ShellOverlayAdapters
        && window.OdareHubOS.ShellOverlayAdapters.createPublicNotificationsDropdownAdapter;
      const notificationsDropdownAdapter = typeof notificationsDropdownAdapterFactory === 'function'
        ? notificationsDropdownAdapterFactory({
          triggerSelector: '[data-notif-toggle]',
          surfaceId: 'topbarNotifDropdown',
          setLegacyOpen: setNotifOpen
        })
        : null;

      function requestNotifOpen(open, reason) {
        if (notificationsDropdownAdapter && typeof notificationsDropdownAdapter.setOpen === 'function') {
          notificationsDropdownAdapter.setOpen(open, reason || (open ? 'open' : 'close'));
          return;
        }
        setNotifOpen(open);
      }

      const adminActionDropdownAdapterFactory = window.OdareHubOS
        && window.OdareHubOS.ShellOverlayAdapters
        && window.OdareHubOS.ShellOverlayAdapters.createPublicAdminActionDropdownAdapter;
      const adminActionDropdownAdapter = typeof adminActionDropdownAdapterFactory === 'function'
        ? adminActionDropdownAdapterFactory({
          triggerId: 'topbarActionBtn',
          surfaceId: 'topbarActionDropdown',
          setLegacyOpen: setActionOpen
        })
        : null;

      function requestActionOpen(open, reason) {
        if (adminActionDropdownAdapter && typeof adminActionDropdownAdapter.setOpen === 'function') {
          adminActionDropdownAdapter.setOpen(open, reason || (open ? 'open' : 'close'));
          return;
        }
        setActionOpen(open);
      }

      if (topbarOverflowBtn && topbarOverflowDropdown) {
        requestOverflowOpen(false, 'initialize');
        topbarOverflowBtn.addEventListener('click', function(event) {
          event.stopPropagation();
          const nextState = topbarOverflowBtn.getAttribute('aria-expanded') !== 'true';
          requestOverflowOpen(nextState, 'trigger');
          if (nextState) {
            requestNotifOpen(false, 'peer-opened');
            setCanonicalOpen(false);
          }
        });
      }

      if (topbarCanonicalBtn && topbarCanonicalDropdown) {
        setCanonicalOpen(false);
        topbarCanonicalBtn.addEventListener('click', function(event) {
          event.preventDefault();
          event.stopPropagation();
          const nextState = topbarCanonicalBtn.getAttribute('aria-expanded') !== 'true';
          setCanonicalOpen(nextState);
          if (nextState) {
            requestOverflowOpen(false, 'peer-opened');
            requestNotifOpen(false, 'peer-opened');
            requestActionOpen(false, 'peer-opened');
          }
        });
      }

      if (topbarActionBtn && topbarActionDropdown) {
        requestActionOpen(false, 'initialize');
        topbarActionBtn.addEventListener('click', function(event) {
          event.preventDefault();
          event.stopPropagation();
          const nextState = topbarActionBtn.getAttribute('aria-expanded') !== 'true';
          requestActionOpen(nextState, 'trigger');
          if (nextState) {
            requestOverflowOpen(false, 'peer-opened');
            requestNotifOpen(false, 'peer-opened');
            setCanonicalOpen(false);
          }
        });
      }

      if (topbarActionClose) {
        topbarActionClose.addEventListener('click', function(event) {
          event.preventDefault();
          requestActionOpen(false, 'close-button');
        });
      }

      if (topbarCanonicalClose) {
        topbarCanonicalClose.addEventListener('click', function(event) {
          event.preventDefault();
          setCanonicalOpen(false);
        });
      }

      if (topbarCanonicalBackdrop) {
        topbarCanonicalBackdrop.addEventListener('click', function() {
          setCanonicalOpen(false);
        });
      }

      if (notifButton && notifDropdown) {
        requestNotifOpen(false, 'initialize');
        notifButton.addEventListener('click', function(event) {
          event.preventDefault();
          event.stopPropagation();
          const nextState = notifButton.getAttribute('aria-expanded') !== 'true';
          requestNotifOpen(nextState, 'trigger');
          if (nextState) {
            requestOverflowOpen(false, 'peer-opened');
            setCanonicalOpen(false);
            requestActionOpen(false, 'peer-opened');
          }
        });
      }

      if (mobileScanToggle) {
        mobileScanToggle.addEventListener('click', function(event) {
          event.preventDefault();
          closeSidebarOnSmallScreen();
          setCanonicalOpen(false);
          if (topbarSearchScanBtn) {
            topbarSearchScanBtn.click();
            requestActionOpen(false, 'scan');
            return;
          }
        });
      }

      if (mobileSearchToggle) {
        mobileSearchToggle.addEventListener('click', function(event) {
          event.preventDefault();
          closeSidebarOnSmallScreen();
          setCanonicalOpen(false);
          requestActionOpen(false, 'mobile-search');
          if (topbarSearchInput) {
            topbarSearchInput.focus();
            topbarSearchInput.select();
            if (window.innerWidth <= 720) {
              window.scrollTo({ top: 0, behavior: 'smooth' });
            }
          }
        });
      }

      document.addEventListener('click', function (event) {
        if (topbarCanonicalDropdown && !event.target.closest('.topbar-canonical-wrap')) {
          setCanonicalOpen(false);
        }

      });

      document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
          setSidebarOpen(false);
          setCanonicalOpen(false);
        }
      });
    });

    window.addEventListener('hashchange', syncSidebarActiveState);
    window.addEventListener('resize', syncSidebarViewportState);
  </script>
</head>
<body>
<?php
  if (class_exists('\Apps\Shell\Services\ShellOverlayFramework')) {
    echo \Apps\Shell\Services\ShellOverlayFramework::renderInfrastructureScript();
  }
  if (class_exists('\Apps\Shell\Overlay\Compatibility\Adapters\PublicSurface\PublicAccountOverflowAdapter')) {
    echo \Apps\Shell\Overlay\Compatibility\Adapters\PublicSurface\PublicAccountOverflowAdapter::renderScript();
  }
  if (class_exists('\Apps\Shell\Overlay\Compatibility\Adapters\PublicSurface\PublicNotificationsDropdownAdapter')) {
    echo \Apps\Shell\Overlay\Compatibility\Adapters\PublicSurface\PublicNotificationsDropdownAdapter::renderScript();
  }
  if (class_exists('\Apps\Shell\Overlay\Compatibility\Adapters\PublicSurface\PublicAdminActionDropdownAdapter')) {
    echo \Apps\Shell\Overlay\Compatibility\Adapters\PublicSurface\PublicAdminActionDropdownAdapter::renderScript();
  }
  if (class_exists('\Apps\Shell\Overlay\Compatibility\Adapters\PublicSurface\PublicTopbarSearchResultsAdapter')) {
    echo \Apps\Shell\Overlay\Compatibility\Adapters\PublicSurface\PublicTopbarSearchResultsAdapter::renderScript();
  }
  if (class_exists('\Apps\Shell\Overlay\Compatibility\Adapters\PublicSurface\PublicMobileSidebarDrawerAdapter')) {
    echo \Apps\Shell\Overlay\Compatibility\Adapters\PublicSurface\PublicMobileSidebarDrawerAdapter::renderScript();
  }
  if (class_exists('\Apps\Shell\Overlay\Compatibility\Adapters\Shared\CameraScanOverlayAdapter')) {
    echo \Apps\Shell\Overlay\Compatibility\Adapters\Shared\CameraScanOverlayAdapter::renderScript();
  }
?>
 
<div class="layout-shell">
    <header class="topbar">
      <div class="topbar-inner">
        <div class="topbar-brand">
          <?php if ($showSidebar): ?>
          <button type="button" class="btn icon-btn sidebar-toggle" id="sidebarToggle" data-sidebar-toggle aria-controls="layoutSidebar" aria-expanded="false" aria-label="<?= e(t('common.toggle_sidebar')) ?>">☰</button>
          <?php endif; ?>
          <?php if ($headerInlineBrandLogo !== ''): ?>
          <?php
            $headerLogoSvgTheme = ($headerLogoSvgTheme ?? '');
            $renderedInlineLogo = $headerInlineBrandLogo;
            if ($headerLogoSvgTheme !== '' && preg_match('/\bclass="ipm-logo\b/', $renderedInlineLogo)) {
              $safeTheme = preg_replace('/[^a-z0-9\-]/', '', $headerLogoSvgTheme);
              $renderedInlineLogo = preg_replace('/\bclass="ipm-logo"/', 'class="ipm-logo ' . $safeTheme . '"', $renderedInlineLogo, 1);
            }
            if (preg_match('/<svg\b/i', $renderedInlineLogo)) {
              if (preg_match('/<svg\b[^>]*\bclass="/i', $renderedInlineLogo)) {
                $renderedInlineLogo = preg_replace('/<svg\b([^>]*?)\bclass="([^"]*)"/i', '<svg$1class="$2 header-company-logo-svg"', $renderedInlineLogo, 1);
              } else {
                $renderedInlineLogo = preg_replace('/<svg\b/i', '<svg class="header-company-logo-svg"', $renderedInlineLogo, 1);
              }
            }
          ?>
          <a href="<?= e($homeUrl) ?>" class="brand-link brand-link--logo" aria-label="<?= e($instanceName) ?>">
            <?= $renderedInlineLogo ?>
            <strong><?= e($instanceName) ?></strong>
          </a>
          <?php elseif ($headerCompanyLogo !== ''): ?>
          <a href="<?= e($homeUrl) ?>" class="brand-link brand-link--logo" aria-label="<?= e($instanceName) ?>">
            <img src="<?= e($headerCompanyLogo) ?>" alt="<?= e($instanceName) ?>" class="header-company-logo">
            <strong><?= e($instanceName) ?></strong>
          </a>
          <?php else: ?>
          <a href="<?= e($homeUrl) ?>" class="brand-link brand-link--logo" aria-label="<?= e($instanceName) ?>">
            <span class="header-company-logo-fallback header-company-logo-fallback--desktop"><?= e($headerFallbackText) ?></span>
            <span class="header-company-logo-fallback header-company-logo-fallback--mobile"><?= e($headerFallbackTextCompact) ?></span>
          </a>
          <?php endif; ?>
        </div>

        <?php if ($showTopbarSearch): ?>
        <div class="topbar-search" role="search" data-search-state="idle" aria-label="<?= e((string)__('nav.topbar_search_placeholder')) ?>">
          <div class="topbar-search-input-wrap">
            <span class="topbar-search-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
            </span>
            <span class="topbar-search-spinner" aria-hidden="true"></span>
            <input
              id="topbarSearchInput"
              class="topbar-search-input"
              type="search"
              autocomplete="off"
              spellcheck="false"
              role="combobox"
              aria-expanded="false"
              aria-controls="topbarSearchResultsList"
              aria-autocomplete="list"
              placeholder="<?= e((string)__('nav.topbar_search_placeholder')) ?>"
              aria-label="<?= e((string)__('nav.topbar_search_placeholder')) ?>"
            >
            <button type="button" class="topbar-search-clear" id="topbarSearchClear" aria-label="Clear search" tabindex="-1" hidden>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" aria-hidden="true"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
            </button>
            <kbd class="topbar-search-kbd" id="topbarSearchKbd" aria-hidden="true"><span class="topbar-search-kbd-key" data-key-mac="⌘" data-key-other="Ctrl">⌘</span><span class="topbar-search-kbd-key">K</span></kbd>
            <button type="button" class="topbar-search-scan-btn" id="topbarScanBtn" aria-label="<?= e((string)__('nav.topbar_search_scan')) ?>">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" aria-hidden="true"><path d="M3 7V5a2 2 0 0 1 2-2h2"></path><path d="M17 3h2a2 2 0 0 1 2 2v2"></path><path d="M21 17v2a2 2 0 0 1-2 2h-2"></path><path d="M7 21H5a2 2 0 0 1-2-2v-2"></path><path d="M7 12h10"></path></svg>
            </button>
          </div>
          <div class="topbar-search-results" id="topbarSearchResults" role="listbox">
            <div class="topbar-search-results-list" id="topbarSearchResultsList"></div>
            <div class="topbar-search-empty muted" id="topbarSearchNoMatch" hidden><?= e((string)__('nav.topbar_search_no_matches')) ?></div>
          </div>
        </div>
        <?php endif; ?>

        <div class="topbar-spacer" aria-hidden="true"></div>

        <div class="topbar-actions topbar-right">
          <?php if ($loggedIn): ?>
            <?php if ($isAdminLayerRoute && in_array($authorityRoleGlobal, ['platform_admin', 'sysadmin'], true)): ?>
              <a
                class="platform-mode-indicator platform-mode-indicator--<?= e($platformMode) ?>"
                href="/admin/system-tools/platform-mode"
                aria-label="<?= e(t('admin.platform_mode.header_aria', ['mode' => $platformModeLabel])) ?>"
                title="<?= e(t('admin.platform_mode.header_aria', ['mode' => $platformModeLabel])) ?>"
              ><span class="platform-mode-indicator-dot" aria-hidden="true"></span><span><?= e($platformModeLabel) ?></span></a>
            <?php endif; ?>
            <?php if ($showAlerts): ?>
            <div class="notif-bell-wrap">
              <button
                type="button"
                class="btn icon-btn notif-bell-btn"
                data-notif-toggle
                aria-label="<?= e(t('nav.alerts')) ?>"
                aria-expanded="false"
                aria-controls="topbarNotifDropdown"
                title="<?= e(t('nav.alerts')) ?>"
              >
                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 1a5 5 0 0 0-5 5v2.586l-.707.707A1 1 0 0 0 3 11h10a1 1 0 0 0 .707-1.707L13 8.586V6A5 5 0 0 0 8 1zM6.5 13a1.5 1.5 0 0 0 3 0H6.5z"/></svg>
                <?php if ($unreadNotifications > 0): ?>
                  <span class="notif-dot" aria-hidden="true"></span>
                <?php endif; ?>
              </button>

              <div class="notif-dropdown" id="topbarNotifDropdown" data-notif-dropdown hidden>
                <div class="notif-dd-head">
                  <h4 class="notif-dd-title"><?= e(t('nav.alerts')) ?></h4>
                  <a class="btn" href="/ops/notifications"><?= e(t('common.open')) ?></a>
                </div>

                <?php if ($recentHeaderNotifications === []): ?>
                  <div class="notif-dd-empty"><?= e(t('ops.notifications.none_for_filter')) ?></div>
                <?php else: ?>
                  <div class="notif-dd-list">
                    <?php
                      $groupTitles = [
                        'action_required' => t('ops.notifications.group_action_required'),
                        'approval_signals' => t('ops.notifications.group_approval_signals'),
                        'escalations' => t('ops.notifications.group_escalations'),
                        'system' => t('ops.notifications.group_system'),
                      ];
                      $groupOrder = ['action_required', 'approval_signals', 'escalations', 'system'];
                    ?>
                    <?php foreach ($groupOrder as $groupKey): ?>
                      <?php $rows = (array)($headerNotificationGroups[$groupKey] ?? []); ?>
                      <?php if ($rows === []): ?>
                        <?php continue; ?>
                      <?php endif; ?>
                      <section class="notif-dd-group">
                        <h5 class="notif-dd-group-title"><?= e((string)($groupTitles[$groupKey] ?? $groupKey)) ?></h5>
                        <?php foreach ($rows as $note): ?>
                          <?php
                            $actions = \Plugins\Base\Services\NotificationService::recommendedActions((array)$note);
                            $openUrl = trim((string)($actions['primary_url'] ?? '')) !== '' ? (string)$actions['primary_url'] : '/ops/notifications';
                            $severity = strtolower(trim((string)($note['severity'] ?? 'info')));
                            if (!in_array($severity, ['critical', 'warning', 'action_required', 'approval_required'], true)) {
                              $severity = 'info';
                            }
                            $eventLabel = \Plugins\Base\Services\NotificationService::eventLabel((string)($note['event_type'] ?? ''));
                          ?>
                          <a class="notif-dd-item" href="<?= e($openUrl) ?>">
                            <div class="notif-dd-item-title"><?= e((string)($note['title'] ?? 'Notification')) ?></div>
                            <div class="notif-dd-item-msg"><?= e((string)($note['message'] ?? '')) ?></div>
                            <div class="notif-dd-item-meta">
                              <span class="notif-dd-chip notif-dd-chip-<?= e($severity) ?>"><?= e(\Plugins\Base\Services\NotificationService::severityLabel((string)($note['severity'] ?? 'info'))) ?></span>
                              <?= e($eventLabel) ?> · <?= e((string)($note['created_at'] ?? '')) ?>
                            </div>
                          </a>
                        <?php endforeach; ?>
                      </section>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
            <?php endif; ?>

            <div class="topbar-overflow-wrap">
              <button
                type="button"
                class="btn icon-btn topbar-overflow-btn"
                id="topbarOverflowBtn"
                aria-label="<?= e(t('common.my_account')) ?>"
                aria-expanded="false"
                aria-controls="topbarOverflowDropdown"
                title="<?= e(t('common.my_account')) ?>"
              ><svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 1.75a3.25 3.25 0 1 0 0 6.5 3.25 3.25 0 0 0 0-6.5zM3.75 5a4.25 4.25 0 1 1 8.5 0 4.25 4.25 0 0 1-8.5 0z"></path><path d="M8 9.25c-2.59 0-4.74 1.88-5.16 4.35a.5.5 0 0 0 .49.6h9.34a.5.5 0 0 0 .49-.6c-.42-2.47-2.57-4.35-5.16-4.35z"></path></svg></button>

              <div class="topbar-overflow-dropdown" id="topbarOverflowDropdown" hidden>
                <div class="topbar-overflow-summary">
                  <div class="topbar-overflow-instance"><?= e($instanceName) ?></div>
                  <div class="topbar-overflow-account"><?= e($accountDisplayName) ?></div>
                  <?php if ($showAccountSecondaryLabel): ?>
                    <div class="topbar-overflow-account-meta"><?= e($accountSecondaryLabel) ?></div>
                  <?php endif; ?>
                </div>

                <a class="topbar-overflow-item" href="/ops/notifications">
                  <span class="topbar-overflow-icon">🔔</span>
                  <?= e(t('nav.alerts')) ?>
                  <?php if ($unreadNotifications > 0): ?>
                    <span class="topbar-overflow-count"><?= (int)$unreadNotifications > 99 ? '99+' : (int)$unreadNotifications ?></span>
                  <?php endif; ?>
                </a>

                <a class="topbar-overflow-item" href="/account">
                  <span class="topbar-overflow-icon">👤</span>
                  <?= e(t('common.account')) ?>
                </a>

                <button type="button" class="topbar-overflow-item topbar-overflow-item-button" onclick="window.print()">
                  <span class="topbar-overflow-icon">🖨️</span>
                  <?= e(t('common.print_page')) ?>
                </button>

                <div class="topbar-overflow-section">
                  <label class="topbar-overflow-label"><?= e(t('common.language')) ?></label>
                  <select class="topbar-overflow-select" onchange="switchLang(this.value)">
                    <?php foreach (supported_language_labels() as $languageCode => $languageLabel): ?>
                      <option value="<?= e($languageCode) ?>" <?= $lang === $languageCode ? 'selected' : '' ?>><?= e($languageLabel) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="topbar-overflow-section">
                  <label class="topbar-overflow-label"><?= e(t('common.currency')) ?></label>
                  <select class="topbar-overflow-select" onchange="switchCurrency(this.value)">
                    <option value="usd" <?= $currency === 'usd' ? 'selected' : '' ?>><?= e(t('common.usd')) ?></option>
                    <option value="jpy" <?= $currency === 'jpy' ? 'selected' : '' ?>><?= e(t('common.jpy')) ?></option>
                    <option value="npr" <?= $currency === 'npr' ? 'selected' : '' ?>><?= e(t('common.npr')) ?></option>
                  </select>
                </div>

                <div class="topbar-overflow-section">
                  <label class="topbar-overflow-label"><?= e(t('common.theme')) ?></label>
                  <select class="topbar-overflow-select" id="erpThemeSelectTopbar" data-theme-select>
                    <?php foreach ($themeChoices as $themeValue => $themeLabel): ?>
                      <option value="<?= e($themeValue) ?>"><?= e($themeLabel) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="topbar-overflow-section">
                  <label class="topbar-overflow-label" for="shellOverlayEffectStrengthTopbar">Overlay effect</label>
                  <div class="shell-overlay-strength-control">
                    <input
                      class="shell-overlay-strength-slider"
                      id="shellOverlayEffectStrengthTopbar"
                      type="range"
                      min="0"
                      max="100"
                      step="1"
                      data-overlay-effect-strength
                      aria-label="Overlay effect strength"
                    >
                    <output class="shell-overlay-strength-value" data-overlay-effect-strength-value for="shellOverlayEffectStrengthTopbar">40</output>
                  </div>
                </div>

                <?php if ($isAdminLayerRoute): ?>
                <a class="topbar-overflow-item" href="<?= e($switchToOperatorUrl) ?>">
                  <span class="topbar-overflow-icon">↔️</span>
                  <?= e(t('common.switch_to_operator_workspace')) ?>
                </a>
                <?php endif; ?>

                <a class="topbar-overflow-item topbar-overflow-signout" href="/logout" onclick="return confirm('<?= e(t('messages.sign_out_confirm')) ?>');">
                  <span class="topbar-overflow-icon">🚪</span>
                  <?= e(t('common.sign_out')) ?>
                </a>
              </div>
            </div>

          <?php endif; ?>

        </div>
      </div>
    </header>

  <?php if ($showSidebar): ?>
  <?php require __DIR__ . '/sidebar.php'; ?>
  <?php endif; ?>

  <div class="layout-main">

    <?php
    $adminBottomNavIconSvg = static function (string $name): string {
      $icons = [
        'home' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11.5 12 4l9 7.5"></path><path d="M5.5 10.5V20h13V10.5"></path></svg>',
        'action' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2 4 14h7l-1 8 10-13h-7l0-7z"></path></svg>',
        'scan' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7V4h3"></path><path d="M3 17v3h3"></path><path d="M18 4h3v3"></path><path d="M18 20h3v-3"></path><line x1="3" y1="12" x2="21" y2="12"></line></svg>',
        'search' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="6"></circle><path d="M20 20l-3.5-3.5"></path></svg>',
      ];

      return $icons[$name] ?? $icons['action'];
    };
    ?>
    <?php if ($showAdminMobileStrip): ?>
    <nav class="admin-bottom-nav" aria-label="Mobile Navigation">
      <a class="admin-bottom-nav-item" href="<?= e($homeUrl) ?>"><span class="admin-bottom-nav-icon"><?= $adminBottomNavIconSvg('home') ?></span><span class="admin-bottom-nav-label"><?= e(t('nav.home')) ?></span></a>
      <button type="button" class="admin-bottom-nav-item" id="mobileScanToggle" aria-label="<?= e((string)__('nav.topbar_search_scan')) ?>"><span class="admin-bottom-nav-icon"><?= $adminBottomNavIconSvg('scan') ?></span><span class="admin-bottom-nav-label"><?= e(t('nav.scan')) ?></span></button>
      <div class="topbar-action-wrap">
        <button
          type="button"
          class="admin-bottom-nav-item topbar-action-btn"
          id="topbarActionBtn"
          aria-label="<?= e(t('nav.mfg_actions_title')) ?>"
          aria-expanded="false"
          aria-controls="topbarActionDropdown"
          title="<?= e(t('nav.mfg_actions_title')) ?>"
        ><span class="admin-bottom-nav-icon"><?= $adminBottomNavIconSvg('action') ?></span><span class="admin-bottom-nav-label"><?= e(t('nav.action')) ?></span></button>

        <div class="topbar-overflow-dropdown topbar-action-dropdown" id="topbarActionDropdown" hidden>
          <div class="topbar-overflow-summary topbar-action-summary">
            <div>
              <div class="topbar-overflow-instance"><?= e(t('nav.mfg_actions_title')) ?></div>
              <div class="topbar-overflow-account"><?= e(t('nav.mfg_actions_choose')) ?></div>
            </div>
            <button type="button" class="btn icon-btn topbar-action-close" id="topbarActionClose" aria-label="<?= e(t('nav.mfg_actions_close')) ?>">✕</button>
          </div>
          <?php if ($manufacturingActionLinks === []): ?>
            <div class="notif-dd-empty"><?= e(t('nav.mfg_actions_empty')) ?></div>
          <?php else: ?>
            <?php foreach ($manufacturingActionLinks as $action): ?>
              <a class="topbar-overflow-item" href="<?= e((string)($action['url'] ?? '#')) ?>">
                <span class="topbar-overflow-icon"><?= e((string)($action['icon'] ?? '⚡')) ?></span>
                <span style="display:grid;gap:2px;min-width:0">
                  <span><?= e((string)($action['label'] ?? 'Action')) ?></span>
                  <?php if (trim((string)($action['hint'] ?? '')) !== ''): ?>
                    <span class="muted" style="font-size:.74rem"><?= e((string)$action['hint']) ?></span>
                  <?php endif; ?>
                </span>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
      <button type="button" class="admin-bottom-nav-item" id="mobileSearchToggle" aria-label="<?= e(t('common.search')) ?>"><span class="admin-bottom-nav-icon"><?= $adminBottomNavIconSvg('search') ?></span><span class="admin-bottom-nav-label"><?= e(t('common.search')) ?></span></button>
    </nav>
    <?php endif; ?>

<main class="container shell-inactive-layer">
    <?php
    $developerStripContext = null;
    if (class_exists('\\Platform\\Security\\PlatformAuthority') && class_exists('\\Platform\\Security\\EngineeringWorkspacePageContextResolver')) {
      $developerStripActor = \Platform\Security\PlatformAuthority::resolveCurrentActor();
      $developerStripContext = \Platform\Security\EngineeringWorkspacePageContextResolver::resolveCurrentRequest($developerStripActor);
    }
    require APP_ROOT . '/apps/Shell/Views/partials/developer_strip.php';
    ?>

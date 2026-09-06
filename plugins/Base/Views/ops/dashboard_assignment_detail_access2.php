<?php
$row = is_array($row ?? null) ? $row : [];
$accessProfileRegistry = is_array($accessProfileRegistry ?? null) ? $accessProfileRegistry : [];
$assignmentUiConfig = is_array($assignmentUiConfig ?? null) ? $assignmentUiConfig : [];
$detailRedirectTo = trim((string)($assignmentDetailRedirectTo ?? ''));

// Capture workspace-profile data BEFORE local $profileOptions (access profiles) is built below.
$workspaceProfileOptions  = is_array($profileOptions ?? null) ? $profileOptions : [];
$workspaceResolvedProfile = is_array($resolvedProfile ?? null) ? $resolvedProfile : null;
$workspaceProfileOptionsByKey = [];
foreach ($workspaceProfileOptions as $workspaceProfileOption) {
  if (!is_array($workspaceProfileOption)) {
    continue;
  }
  $workspaceProfileOptionKey = strtolower(trim((string)($workspaceProfileOption['key'] ?? '')));
  if ($workspaceProfileOptionKey === '') {
    continue;
  }
  $workspaceProfileOptionsByKey[$workspaceProfileOptionKey] = $workspaceProfileOption;
}
$workspaceResolvedProfileDetailUrl = '';
$workspaceResolvedProfileKey = '';
if (is_array($workspaceResolvedProfile)) {
  $workspaceResolvedProfileId = (int)($workspaceResolvedProfile['id'] ?? 0);
  $workspaceResolvedProfileKey = strtolower(trim((string)($workspaceResolvedProfile['profile_key'] ?? '')));
  if ($workspaceResolvedProfileId <= 0 && $workspaceResolvedProfileKey !== '' && isset($workspaceProfileOptionsByKey[$workspaceResolvedProfileKey])) {
    $workspaceResolvedProfileId = (int)($workspaceProfileOptionsByKey[$workspaceResolvedProfileKey]['id'] ?? 0);
  }
  if ($workspaceResolvedProfileId > 0) {
    $workspaceResolvedProfileDetailUrl = '/ops/workspace-profiles/detail?id=' . $workspaceResolvedProfileId;
  }
}
$pinnedProfileKey = strtolower(trim((string)($row['workspace_profile_key'] ?? '')));
$workspaceProfileCatalog = [];
foreach ($workspaceProfileOptions as $workspaceProfileOption) {
  if (!is_array($workspaceProfileOption)) {
    continue;
  }
  $workspaceProfileKey = strtolower(trim((string)($workspaceProfileOption['key'] ?? '')));
  if ($workspaceProfileKey === '') {
    continue;
  }
  $workspaceProfileCatalog[$workspaceProfileKey] = [
    'key' => $workspaceProfileKey,
    'name' => (string)($workspaceProfileOption['name'] ?? ''),
    'authority_role' => strtolower(trim((string)($workspaceProfileOption['authority_role'] ?? ''))),
    'app_key' => strtolower(trim((string)($workspaceProfileOption['app_key'] ?? ''))),
    'owner_type' => strtolower(trim((string)($workspaceProfileOption['owner_type'] ?? 'app'))),
    'owner_key' => strtolower(trim((string)($workspaceProfileOption['owner_key'] ?? ''))),
    'governance_scope' => strtolower(trim((string)($workspaceProfileOption['governance_scope'] ?? 'app'))),
    'default_app' => strtolower(trim((string)($workspaceProfileOption['default_app'] ?? $workspaceProfileOption['app_key'] ?? ''))),
    'assigned_apps' => strtolower(trim((string)($workspaceProfileOption['assigned_apps'] ?? ''))),
    'access_profiles' => strtolower(trim((string)($workspaceProfileOption['access_profiles'] ?? ''))),
    'landing_route' => trim((string)($workspaceProfileOption['landing_route'] ?? '')),
  ];
}

$tokenList = static function (string $csv): array {
  $parts = preg_split('/\s*,\s*/', strtolower(trim($csv))) ?: [];
  $out = [];
  foreach ($parts as $part) {
    $value = trim($part);
    if ($value === '') {
      continue;
    }
    $out[$value] = true;
  }
  return $out;
};

$normalizeLabel = static function (string $label): string {
  $trimmed = trim($label);
  if ($trimmed === '') {
    return '';
  }
  $parts = preg_split('/\s+/', strtolower($trimmed)) ?: [];
  $words = [];
  foreach ($parts as $part) {
    $w = trim($part);
    if ($w === '') {
      continue;
    }
    $words[] = match ($w) {
      'qc' => 'QC',
      'sbaio' => 'SBAIO',
      'tv' => 'TV',
      default => ucfirst($w),
    };
  }
  return implode(' ', $words);
};

$uid = (int)($row['id'] ?? 0);
$accessFormId = 'assign_access2_form_' . $uid;

$accessAuthorities = is_array($assignmentUiConfig['accessAuthorities'] ?? null)
  ? (array)$assignmentUiConfig['accessAuthorities']
  : [
      'platform_admin' => 'Platform Admin',
      'app_admin' => 'App Admin',
      'app_user' => 'App User',
    ];

$interactionProfiles = is_array($assignmentUiConfig['interactionProfiles'] ?? null)
  ? (array)$assignmentUiConfig['interactionProfiles']
  : [
      'worker' => 'Worker',
      'leader' => 'Leader',
      'admin' => 'Admin',
      'read_only' => 'Read-only',
      'display' => 'Display',
    ];

$operationalFocuses = is_array($assignmentUiConfig['operationalFocuses'] ?? null)
  ? (array)$assignmentUiConfig['operationalFocuses']
  : [
      'production' => 'Production',
      'assembly' => 'Assembly',
      'qc' => 'QC',
      'dispatch' => 'Dispatch',
      'planner' => 'Planner',
      'office_ops' => 'Office Ops',
    ];

$canonicalDefaults = is_array($assignmentUiConfig['canonical'] ?? null)
  ? (array)$assignmentUiConfig['canonical']
  : [];

$appRegistry = [];
foreach ((array)($assignmentUiConfig['appRegistry'] ?? []) as $appDef) {
  if (!is_array($appDef)) {
    continue;
  }
  $appKey = strtolower(trim((string)($appDef['key'] ?? '')));
  if ($appKey === '' || !(bool)($appDef['enabled'] ?? false)) {
    continue;
  }
  if (strtolower(trim((string)($appDef['app_type'] ?? ''))) === 'framework') {
    continue;
  }
  // Platform is always included in the registry; JS controls visibility based on live authority selection.
  $appRegistry[$appKey] = [
    'key' => $appKey,
    'label' => $normalizeLabel((string)($appDef['label'] ?? $appKey)),
  ];
}
if (!isset($appRegistry['platform'])) {
  $appRegistry['platform'] = ['key' => 'platform', 'label' => 'Platform'];
}

$appFocusesByKey = is_array($assignmentUiConfig['appFocusesByKey'] ?? null)
  ? (array)$assignmentUiConfig['appFocusesByKey']
  : [];

$selectedApps = $tokenList((string)($row['assigned_apps'] ?? ''));
$selectedAppKeys = [];
foreach (array_keys($selectedApps) as $appKey) {
  if (isset($appRegistry[$appKey])) {
    $selectedAppKeys[$appKey] = true;
  }
}

$defaultApp = strtolower(trim((string)($row['default_app'] ?? '')));
if ($defaultApp === '' || !isset($appRegistry[$defaultApp])) {
  $defaultApp = (string)(array_key_first($appRegistry) ?? 'platform');
}
if ($defaultApp !== '') {
  $selectedAppKeys[$defaultApp] = true;
}

$currentAuthority = strtolower(trim((string)($row['authority_role'] ?? 'app_user')));
if (!isset($accessAuthorities[$currentAuthority])) {
  $currentAuthority = 'app_user';
}

$currentOperationalProfile = strtolower(trim((string)($row['operational_role'] ?? ($row['role'] ?? ''))));
$focusAliases = [
  'production' => 'production',
  'productionworker' => 'production',
  'productionleader' => 'production',
  'assembly' => 'assembly',
  'assemblyworker' => 'assembly',
  'assemblyleader' => 'assembly',
  'qc' => 'qc',
  'qcworker' => 'qc',
  'qcleader' => 'qc',
  'dispatch' => 'dispatch',
  'dispatchworker' => 'dispatch',
  'dispatchleader' => 'dispatch',
  'manufacturingplanner' => 'planner',
  'planner' => 'planner',
  'sbaio_operator' => 'office_ops',
  'readonlyobserver' => 'office_ops',
  'platformoperations' => 'office_ops',
  'platformsecurity' => 'office_ops',
  'appadministration' => 'office_ops',
];
$currentFocus = (string)($focusAliases[$currentOperationalProfile] ?? 'production');
if (!isset($operationalFocuses[$currentFocus])) {
  $currentFocus = 'production';
}

$currentInteraction = 'worker';
if ($currentAuthority === 'platform_admin' || $currentAuthority === 'app_admin') {
  $currentInteraction = 'admin';
} elseif (str_contains($currentOperationalProfile, 'leader') || str_contains($currentOperationalProfile, 'coordination') || str_contains($currentOperationalProfile, 'planner')) {
  $currentInteraction = 'leader';
} elseif (str_contains($currentOperationalProfile, 'readonly') || str_contains($currentOperationalProfile, 'observer')) {
  $currentInteraction = 'read_only';
}

// Account class — surfaced as an editable select in Stage 1
$accountClassOptions = is_array($assignmentUiConfig['accountClasses'] ?? null)
  ? (array)$assignmentUiConfig['accountClasses']
  : ['platform_operations' => 'Platform Operations', 'platform_security' => 'Platform Security', 'app_administration' => 'App Administration', 'app_user' => 'App User', 'tv_display' => 'TV / Display'];
$currentAccountClass = strtolower(trim((string)($row['account_class'] ?? '')));
if (!isset($accountClassOptions[$currentAccountClass])) {
  $currentAccountClass = match ($currentAuthority) {
    'platform_admin' => 'platform_operations',
    'app_admin' => 'app_administration',
    'tv_display' => 'tv_display',
    default => 'app_user',
  };
}

// Dashboard type — surfaced as an editable select in Stage 2
$dashboardsByAccount = is_array($assignmentUiConfig['dashboardsByAccount'] ?? null)
  ? (array)$assignmentUiConfig['dashboardsByAccount']
  : [];
$dashboardTypeLabels = [
  'platform_admin'    => 'Platform Admin',
  'app_admin'         => 'App Admin',
  'my_work'           => 'My Work',
  'operator'          => 'Operator',
  'production_leader' => 'Production Leader',
  'assembly_leader'   => 'Assembly Leader',
  'qc_leader'         => 'QC Leader',
  'dispatch_leader'   => 'Dispatch Leader',
  'display'           => 'TV / Display',
];
$dashboardTypeOptionsForAuthority = array_values((array)($dashboardsByAccount[$currentAuthority] ?? ['my_work']));
if ($currentAuthority === 'tv_display') {
  $dashboardTypeOptionsForAuthority = ['display'];
}
$currentDashboardType = strtolower(trim((string)($row['dashboard_type'] ?? 'my_work')));

// Operational constraint fields — surfaced in Stage 3 constraint sub-section
$currentDepartmentCode = trim((string)($row['scope_department_code'] ?? ''));
$currentBranchCode     = trim((string)($row['scope_branch_code'] ?? ''));
$currentOwnershipRole  = strtolower(trim((string)($row['scope_ownership_role'] ?? '')));
$ownershipRoleOptions  = ['' => '— None —', 'owner' => 'Owner', 'assignee' => 'Assignee', 'viewer' => 'Viewer'];

$currentControlScope = strtolower(trim((string)($row['governance_scope'] ?? '')));
if ($currentControlScope === '') {
  $currentControlScope = match (true) {
    $currentAuthority === 'platform_admin' => 'platform',
    $currentAuthority === 'app_admin' => 'app',
    $currentInteraction === 'display' || $currentInteraction === 'read_only' => 'shared',
    default => 'module',
  };
}

$controlScopeOptions = [
  'platform' => 'Platform Scope',
  'app' => 'App Scope',
  'module' => 'Module Scope',
  'shared' => 'Shared Surface Scope',
];

$dashboardType = $currentDashboardType;
$landingByDashboard = is_array($assignmentUiConfig['landingByDashboard'] ?? null)
  ? (array)$assignmentUiConfig['landingByDashboard']
  : [];
$homeSurfaceOptions = array_values(array_unique(array_filter(array_map('strval', (array)($landingByDashboard[$dashboardType] ?? ['/'])))));
if ($homeSurfaceOptions === []) {
  $homeSurfaceOptions = ['/'];
}
$homeSurface = trim((string)($row['default_landing_page'] ?? '/'));
if ($homeSurface === '') {
  $homeSurface = '/';
}
if (!in_array($homeSurface, $homeSurfaceOptions, true)) {
  $homeSurfaceOptions[] = $homeSurface;
}

$selectedProfiles = $tokenList((string)($row['access_profiles'] ?? ''));
$appAdminOnlyProfiles = $tokenList((string)implode(',', (array)($assignmentUiConfig['appAdminOnlyAccessProfiles'] ?? [])));
$leaderProfiles = $tokenList((string)implode(',', (array)($assignmentUiConfig['leaderAccessProfiles'] ?? [])));

$profileOptions = [];
$profileDisplayKeys = [];
foreach ($accessProfileRegistry as $profileKey => $cfg) {
  if (!is_array($cfg)) {
    continue;
  }
  $key = strtolower(trim((string)$profileKey));
  if ($key === '') {
    continue;
  }
  $profileApp = strtolower(trim((string)($cfg['app'] ?? 'platform')));
  if (!isset($appRegistry[$profileApp])) {
    continue;
  }

  $label = $normalizeLabel((string)($cfg['label'] ?? $key));
  $displayKey = strtolower(trim($profileApp . '|' . $label));
  $isSelected = isset($selectedProfiles[$key]);
  if (isset($profileDisplayKeys[$displayKey])) {
    $existingKey = $profileDisplayKeys[$displayKey];
    $existingSelected = isset($selectedProfiles[$existingKey]);
    if ($existingSelected || !$isSelected) {
      continue;
    }
  }
  $profileDisplayKeys[$displayKey] = $key;

  $dutyCodes = $tokenList((string)implode(',', (array)($cfg['duty_codes'] ?? [])));
  $isReadOnly = isset($dutyCodes['read_only']) || str_contains($key, 'readonly');
  $moduleVisibility = [];
  foreach ((array)($cfg['module_visibility'] ?? []) as $moduleKey) {
    $moduleKey = strtolower(trim((string)$moduleKey));
    if ($moduleKey !== '') {
      $moduleVisibility[] = $moduleKey;
    }
  }

  $profileOptions[$key] = [
    'key' => $key,
    'label' => $label,
    'app' => $profileApp,
    'default_app' => strtolower(trim((string)($cfg['default_app'] ?? $profileApp))),
    'authority_scope' => isset($appAdminOnlyProfiles[$key]) ? 'admin_only' : (isset($leaderProfiles[$key]) ? 'leader' : 'general'),
    'readonly' => $isReadOnly,
    'module_visibility' => $moduleVisibility,
  ];
}

uasort($profileOptions, static function (array $left, array $right): int {
  $byApp = strcmp((string)($left['app'] ?? ''), (string)($right['app'] ?? ''));
  if ($byApp !== 0) {
    return $byApp;
  }
  return strcmp((string)($left['label'] ?? ''), (string)($right['label'] ?? ''));
});

$moduleSet = $tokenList((string)($row['module_visibility'] ?? ''));
$moduleRegistry = [];
foreach ((array)($assignmentUiConfig['moduleRegistry'] ?? []) as $moduleDef) {
  if (!is_array($moduleDef)) {
    continue;
  }
  $moduleKey = strtolower(trim((string)($moduleDef['key'] ?? '')));
  if ($moduleKey === '') {
    continue;
  }
  $moduleApp = strtolower(trim((string)($moduleDef['app'] ?? 'platform')));
  if (!isset($appRegistry[$moduleApp])) {
    continue;
  }
  $moduleRegistry[$moduleKey] = [
    'key' => $moduleKey,
    'app' => $moduleApp,
    'label' => $normalizeLabel((string)($moduleDef['label'] ?? $moduleKey)),
    'levels' => array_values((array)($moduleDef['levels'] ?? ['none', 'view', 'work', 'approve', 'manage'])),
  ];
}

$moduleHintCsv = strtolower(trim((string)($row['module_access_hints'] ?? '')));
$moduleHints = [];
foreach (preg_split('/\s*,\s*/', $moduleHintCsv) ?: [] as $pair) {
  if ($pair === '') {
    continue;
  }
  $parts = explode(':', $pair, 2);
  $moduleKey = trim((string)($parts[0] ?? ''));
  $levelKey = trim((string)($parts[1] ?? 'view'));
  if ($moduleKey === '') {
    continue;
  }
  $moduleHints[$moduleKey] = $levelKey === '' ? 'view' : $levelKey;
}

$moduleGroups = [];
foreach ($moduleRegistry as $moduleKey => $moduleDef) {
  $moduleApp = (string)($moduleDef['app'] ?? 'platform');
  if (!isset($moduleGroups[$moduleApp])) {
    $moduleGroups[$moduleApp] = [];
  }
  $moduleGroups[$moduleApp][$moduleKey] = $moduleDef;
}
foreach ($moduleGroups as &$group) {
  uasort($group, static function (array $left, array $right): int {
    return strcmp((string)($left['label'] ?? ''), (string)($right['label'] ?? ''));
  });
}
unset($group);

$surfaceDefinitions = is_array($assignmentUiConfig['surfaceDefinitions'] ?? null)
  ? (array)$assignmentUiConfig['surfaceDefinitions']
  : [];
$viewSet = $tokenList((string)($row['view_access'] ?? ''));
$tableSet = $tokenList((string)($row['table_access'] ?? ''));
$chartSet = $tokenList((string)($row['chart_access'] ?? ''));
$pluginSet = $tokenList((string)($row['me_plugin_cards'] ?? ''));
$readOnlyProfilesSet = $tokenList((string)($row['readonly_profiles'] ?? ''));
$displaySurfaceSet = $tokenList((string)($row['display_surfaces'] ?? ''));

$displaySurfaceCatalog = [];
foreach ($surfaceDefinitions as $surfaceKey => $surfaceDef) {
  if (!is_array($surfaceDef)) {
    continue;
  }
  $interaction = array_map('strtolower', array_map('strval', (array)($surfaceDef['interaction_profiles'] ?? [])));
  $isDisplaySurface = in_array('display', $interaction, true) || in_array('read_only', $interaction, true);
  if (!$isDisplaySurface) {
    continue;
  }
  $tokenType = strtolower(trim((string)($surfaceDef['token_type'] ?? '')));
  $tokenKey = strtolower(trim((string)($surfaceDef['token_key'] ?? '')));
  if ($tokenType === '' || $tokenKey === '') {
    continue;
  }
  $surfaceApp = strtolower(trim((string)($surfaceDef['app'] ?? '')));
  if ($surfaceApp === '' && $tokenType === 'module' && isset($moduleRegistry[$tokenKey])) {
    $surfaceApp = (string)($moduleRegistry[$tokenKey]['app'] ?? '');
  }
  $displaySurfaceCatalog[(string)$surfaceKey] = [
    'key' => (string)$surfaceKey,
    'label' => $normalizeLabel((string)($surfaceDef['label'] ?? $surfaceKey)),
    'token_type' => $tokenType,
    'token_key' => $tokenKey,
    'app' => $surfaceApp,
  ];
}

uasort($displaySurfaceCatalog, static function (array $left, array $right): int {
  return strcmp((string)($left['label'] ?? ''), (string)($right['label'] ?? ''));
});

$hiddenCarryFields = [
  'dashboard_mode',
  'default_app_mode',
  'landing_mode',
  'suite_role_templates',
  'module_permission_templates',
  'permissions',
  'duty_notes',
  'me_dashboard_blocks',
  'duty_codes',
  'scope_machine_ids',
  'scope_part_ids',
  'scope_task_types',
];

// Cross-app authority provider — surfaced as an editable section (not a carry field)
$crossFunctionalBundles = is_array($assignmentUiConfig['crossFunctionalBundles'] ?? null)
  ? (array)$assignmentUiConfig['crossFunctionalBundles']
  : [];
$crossFunctionalPresets = is_array($assignmentUiConfig['crossFunctionalPresets'] ?? null)
  ? (array)$assignmentUiConfig['crossFunctionalPresets']
  : [];
$crossPermissionLevels = is_array($assignmentUiConfig['crossPermissionLevels'] ?? null)
  ? (array)$assignmentUiConfig['crossPermissionLevels']
  : ['view', 'work', 'approve', 'manage'];
// Parse current cross_functional_access CSV → bundle => level map
$crossFunctionalAccessRaw = strtolower(trim((string)($row['cross_functional_access'] ?? '')));
$crossFunctionalAccess = [];
foreach (preg_split('/\s*,\s*/', $crossFunctionalAccessRaw) ?: [] as $pair) {
  if ($pair === '') {
    continue;
  }
  $parts = explode(':', $pair, 2);
  $bundle = trim((string)($parts[0] ?? ''));
  $level = trim((string)($parts[1] ?? 'view'));
  if ($bundle !== '') {
    $crossFunctionalAccess[$bundle] = $level;
  }
}

$assignedAppsCsv = implode(',', array_keys($selectedAppKeys));
$selectedProfilesCsv = implode(',', array_keys($selectedProfiles));
$moduleVisibilityCsv = (string)($row['module_visibility'] ?? '');
$moduleHintsOut = (string)($row['module_access_hints'] ?? '');
$readOnlyProfilesCsv = (string)($row['readonly_profiles'] ?? '');
$displaySurfacesCsv = (string)($row['display_surfaces'] ?? '');
$viewAccessCsv = (string)($row['view_access'] ?? '');
$tableAccessCsv = (string)($row['table_access'] ?? '');
$chartAccessCsv = (string)($row['chart_access'] ?? '');
$pluginCardsCsv = (string)($row['me_plugin_cards'] ?? '');

$baselinePolicy = is_array($assignmentUiConfig['baselineVisibilityPolicy'] ?? null)
  ? (array)$assignmentUiConfig['baselineVisibilityPolicy']
  : [];
?>

<?php if ($baselinePolicy !== []): ?>
<div class="card u-style-1e77ad7501">
  <h4 class="u-style-02aaf7e067"><?= t('admin.access.detail.baseline_policy_title') ?></h4>
  <div class="muted u-style-761d3addb2"><?= e((string)($baselinePolicy['policy_note'] ?? '')) ?></div>
  <div class="preview-grid">
    <div class="preview-card">
      <h5 class="u-style-02aaf7e067"><?= t('admin.access.detail.baseline_view_title') ?></h5>
      <div class="muted u-style-8c93a3093c"><?= t('admin.access.detail.baseline_view_note') ?></div>
      <div class="mapping-labels">
        <?php foreach ((array)($baselinePolicy['baseline_view'] ?? []) as $bEntry): ?>
          <span class="mapping-label" title="Module: <?= e((string)($bEntry['module'] ?? '')) ?>"><?= e((string)($bEntry['label'] ?? '')) ?> <em class="u-style-6109bb5acb"><?= t('admin.access.detail.level_view') ?></em></span>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="preview-card">
      <h5 class="u-style-02aaf7e067"><?= t('admin.access.detail.baseline_restricted_title') ?></h5>
      <div class="muted u-style-8c93a3093c"><?= t('admin.access.detail.baseline_restricted_note') ?></div>
      <div class="mapping-labels">
        <?php foreach ((array)($baselinePolicy['management_restricted'] ?? []) as $rEntry): ?>
          <span class="mapping-label u-style-2a1f67ee37"><?= e((string)($rEntry['label'] ?? '')) ?> <em class="u-style-6109bb5acb"><?= t('admin.access.detail.level_none') ?></em></span>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php
// Build allPermissionApps: union of apps that have profiles or modules
$allPermissionApps = array_unique(array_merge(
  array_keys(array_reduce($profileOptions, static function (array $carry, array $pd): array {
    $a = strtolower(trim((string)($pd['app'] ?? 'platform')));
    $carry[$a] = true;
    return $carry;
  }, [])),
  array_keys($moduleGroups)
));
?>

<form method="post" action="/ops/access-control/save-access" id="<?= e($accessFormId) ?>" class="assignment-detail-form access2-form" data-governance-form="1">
  <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
  <input type="hidden" name="user_id" value="<?= $uid ?>">
  <?php if ($detailRedirectTo !== ''): ?>
    <input type="hidden" name="redirect_to" value="<?= e($detailRedirectTo) ?>">
  <?php endif; ?>

  <?php foreach ($hiddenCarryFields as $field): ?>
    <input type="hidden" name="<?= e($field) ?>" value="<?= e((string)($row[$field] ?? '')) ?>">
  <?php endforeach; ?>

  <input type="hidden" name="assigned_apps"      id="assigned_apps_<?= $uid ?>"      value="<?= e($assignedAppsCsv) ?>">
  <input type="hidden" name="access_profiles"    id="access_profiles_<?= $uid ?>"    value="<?= e($selectedProfilesCsv) ?>">
  <input type="hidden" name="module_visibility"  id="module_visibility_<?= $uid ?>"  value="<?= e($moduleVisibilityCsv) ?>">
  <input type="hidden" name="module_access_hints" id="module_access_hints_<?= $uid ?>" value="<?= e($moduleHintsOut) ?>">
  <input type="hidden" name="readonly_profiles"  id="readonly_profiles_<?= $uid ?>"  value="<?= e($readOnlyProfilesCsv) ?>">
  <input type="hidden" name="display_surfaces"   id="display_surfaces_<?= $uid ?>"   value="<?= e($displaySurfacesCsv) ?>">
  <input type="hidden" name="view_access"        id="view_access_<?= $uid ?>"        value="<?= e($viewAccessCsv) ?>">
  <input type="hidden" name="table_access"       id="table_access_<?= $uid ?>"       value="<?= e($tableAccessCsv) ?>">
  <input type="hidden" name="chart_access"       id="chart_access_<?= $uid ?>"       value="<?= e($chartAccessCsv) ?>">
  <input type="hidden" name="me_plugin_cards"    id="me_plugin_cards_<?= $uid ?>"    value="<?= e($pluginCardsCsv) ?>">
  <input type="hidden" name="cross_functional_access" id="cross_functional_access_<?= $uid ?>" value="<?= e($crossFunctionalAccessRaw) ?>">
  <input type="hidden" name="access_profiles_mode" value="manual">
  <input type="hidden" name="module_visibility_mode" value="manual">

  <!-- Normalize action bar -->
  <div class="a2-action-bar">
    <button type="button" class="btn a2-normalize-btn" id="normalize_btn_<?= $uid ?>"><?= t('admin.access.detail.normalize_btn') ?></button>
    <span class="a2-action-note muted"><?= t('admin.access.detail.normalize_note') ?></span>
  </div>

  <section class="detail-block access2-shell" data-access2-root="1">

    <!-- ══ Stage 1: Platform Identity ══════════════════════════════════════ -->
    <details class="access2-stage" data-stage="identity" open>
      <summary>
        <span class="access2-stage-index">1</span>
        <span class="access2-stage-title"><?= t('admin.access.detail.stage_identity') ?></span>
        <span class="access2-stage-summary" data-summary="identity"></span>
      </summary>
      <div class="access2-stage-body">
        <div class="a2-identity-grid">
          <label class="access2-field">
            <span><?= e(t('ops.access_control.workspace_profile.section_title')) ?></span>
            <select name="workspace_profile_key" id="workspace_profile_key_<?= $uid ?>">
              <option value=""><?= e(t('ops.access_control.workspace_profile.pin_auto')) ?></option>
              <?php foreach ($workspaceProfileOptions as $workspaceProfileOption): ?>
                <?php $wpOptionKey = strtolower(trim((string)($workspaceProfileOption['key'] ?? ''))); ?>
                <?php if ($wpOptionKey === '') { continue; } ?>
                <option value="<?= e($wpOptionKey) ?>" <?= $pinnedProfileKey === $wpOptionKey ? 'selected' : '' ?>>
                  <?= e((string)($workspaceProfileOption['name'] ?? $wpOptionKey)) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div class="a2-scope-note muted"><?= e(t('ops.access_control.workspace_profile.col_name')) ?></div>
          </label>
          <label class="access2-field access2-hidden">
            <span><?= t('admin.access.detail.label_authority_class') ?></span>
            <select name="authority_role" id="account_type_<?= $uid ?>">
              <?php foreach ($accessAuthorities as $authorityKey => $authorityLabel): ?>
                <option value="<?= e((string)$authorityKey) ?>" <?= $currentAuthority === (string)$authorityKey ? 'selected' : '' ?>><?= e((string)$authorityLabel) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="a2-scope-note muted" data-scope-note="authority"><?= t('admin.access.detail.scope_note_default') ?></div>
          </label>
          <label class="access2-field access2-hidden">
            <span><?= t('admin.access.detail.label_account_class') ?></span>
            <select name="account_class" id="account_class_<?= $uid ?>">
              <?php foreach ($accountClassOptions as $classKey => $classLabel): ?>
                <option value="<?= e((string)$classKey) ?>" <?= $currentAccountClass === (string)$classKey ? 'selected' : '' ?>><?= e((string)$classLabel) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="a2-scope-note muted" data-scope-note="account_class"><?= t('admin.access.detail.account_class_note') ?></div>
          </label>
        </div>
        <div class="access2-feedback" data-feedback="identity"></div>
      </div>
    </details>

    <!-- ══ Stage 2: App Assignment (step 3 → 8 in pipeline order) ══════════ -->
    <!-- hidden for tv_display -->
    <details class="access2-stage" data-stage="scope" data-operator-stage="1">
      <summary>
        <span class="access2-stage-index">2</span>
        <span class="access2-stage-title"><?= t('admin.access.detail.stage_app_assignment') ?></span>
        <span class="access2-stage-summary" data-summary="scope"></span>
      </summary>
      <div class="access2-stage-body">

        <!-- Step 3: Assigned Apps -->
        <div class="access2-subtitle"><?= t('admin.access.detail.stage_apps') ?></div>
        <div class="tg-wrap">
          <?php foreach ($appRegistry as $appKey => $appDef): ?>
            <?php $isOn = isset($selectedAppKeys[(string)$appKey]); ?>
            <button
              type="button"
              class="tg-btn access2-app-token <?= $isOn ? 'is-on' : '' ?><?= $appKey === 'platform' ? ' a2-platform-only-token' : '' ?>"
              data-target="assigned_apps_<?= $uid ?>"
              data-mode="multi"
              data-value="<?= e((string)$appKey) ?>"
              aria-pressed="<?= $isOn ? 'true' : 'false' ?>"
            ><?= e((string)($appDef['label'] ?? $appKey)) ?></button>
          <?php endforeach; ?>
        </div>

        <!-- Resource Constraints (step 3 sub) -->
        <div class="a2-constraints-sub" id="constraints_sub_<?= $uid ?>">
          <button type="button" class="a2-constraints-toggle" data-toggle-constraints="<?= $uid ?>"><?= t('admin.access.detail.constraints_toggle') ?> <span class="a2-constraints-chevron">▸</span></button>
          <div class="a2-constraints-body" id="constraints_body_<?= $uid ?>">
            <div class="a2-constraints-grid">
              <label class="access2-field">
                <span><?= t('admin.access.detail.label_scope_dept') ?></span>
                <input class="input access2-text-input" type="text" name="scope_department_code" value="<?= e($currentDepartmentCode) ?>" placeholder="<?= e(t('admin.access.detail.placeholder_dept_code')) ?>" maxlength="64">
              </label>
              <label class="access2-field">
                <span><?= t('admin.access.detail.label_scope_branch') ?></span>
                <input class="input access2-text-input" type="text" name="scope_branch_code" value="<?= e($currentBranchCode) ?>" placeholder="<?= e(t('admin.access.detail.placeholder_branch_code')) ?>" maxlength="64">
              </label>
              <label class="access2-field">
                <span><?= t('admin.access.detail.label_scope_ownership') ?></span>
                <select name="scope_ownership_role">
                  <?php foreach ($ownershipRoleOptions as $ownershipKey => $ownershipLabel): ?>
                    <option value="<?= e((string)$ownershipKey) ?>" <?= $currentOwnershipRole === (string)$ownershipKey ? 'selected' : '' ?>><?= e((string)$ownershipLabel) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
            </div>
            <div class="a2-constraints-note muted"><?= t('admin.access.detail.constraints_note') ?></div>
          </div>
        </div>

        <!-- Step 4: Default App -->
        <div class="access2-subtitle u-style-d6f2af6e0a"><?= t('admin.access.detail.label_default_app') ?></div>
        <div class="a2-workspace-grid">
          <label class="access2-field">
            <select name="default_app" id="default_app_<?= $uid ?>">
              <?php foreach ($appRegistry as $appKey => $appDef): ?>
                <option value="<?= e((string)$appKey) ?>" <?= $defaultApp === (string)$appKey ? 'selected' : '' ?>><?= e((string)($appDef['label'] ?? $appKey)) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>

        <!-- Steps 5–7: Control Scope, Interaction Profile, Operational Focus -->
        <div class="access2-grid u-style-d6f2af6e0a">
          <label class="access2-field">
            <span><?= t('admin.access.detail.label_control_scope') ?></span>
            <select name="governance_scope" id="governance_scope_<?= $uid ?>">
              <?php foreach ($controlScopeOptions as $scopeKey => $scopeLabel): ?>
                <option value="<?= e((string)$scopeKey) ?>" <?= $currentControlScope === (string)$scopeKey ? 'selected' : '' ?>><?= e((string)$scopeLabel) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="access2-field">
            <span><?= t('admin.access.detail.label_interaction_profile') ?></span>
            <select name="interaction_profile" id="interaction_profile_<?= $uid ?>">
              <?php foreach ($interactionProfiles as $profileKey => $profileLabel): ?>
                <option value="<?= e((string)$profileKey) ?>" <?= $currentInteraction === (string)$profileKey ? 'selected' : '' ?>><?= e((string)$profileLabel) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="access2-field" id="focus_field_<?= $uid ?>">
            <span><?= t('admin.access.detail.label_operational_focus') ?></span>
            <select name="operational_focus" id="operational_focus_<?= $uid ?>">
              <?php foreach ($operationalFocuses as $focusKey => $focusLabel): ?>
                <option value="<?= e((string)$focusKey) ?>" <?= $currentFocus === (string)$focusKey ? 'selected' : '' ?>><?= e((string)$focusLabel) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <div class="a2-focus-hint muted" data-focus-hint="1"></div>
        <div class="a2-scope-note muted" data-scope-note="scope"><?= t('admin.access.detail.scope_note_default') ?></div>

        <!-- Step 8: Home Surface -->
        <div class="access2-subtitle u-style-d6f2af6e0a"><?= t('admin.access.detail.label_home_surface') ?></div>
        <div class="a2-workspace-grid">
          <label class="access2-field">
            <select name="default_landing_page" id="default_landing_page_<?= $uid ?>">
              <?php foreach ($homeSurfaceOptions as $route): ?>
                <option value="<?= e((string)$route) ?>" <?= $homeSurface === (string)$route ? 'selected' : '' ?>><?= e((string)$route) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>

        <div class="access2-feedback" data-feedback="scope"></div>
      </div>
    </details>

    <!-- ══ Stage 3: Account Type (step 2) ═══════════════════════════════════ -->
    <details class="access2-stage" data-stage="workspace">
      <summary>
        <span class="access2-stage-index">3</span>
        <span class="access2-stage-title"><?= t('admin.access.detail.stage_account_type') ?></span>
        <span class="access2-stage-summary" data-summary="workspace"></span>
      </summary>
      <div class="access2-stage-body">
        <div class="a2-workspace-grid">
          <label class="access2-field">
            <span><?= t('admin.access.detail.label_dashboard_type') ?></span>
            <select name="dashboard_type" id="dashboard_type_<?= $uid ?>">
              <?php foreach ($dashboardTypeOptionsForAuthority as $dtKey): ?>
                <?php $dtLabel = $dashboardTypeLabels[$dtKey] ?? ucwords(str_replace('_', ' ', $dtKey)); ?>
                <option value="<?= e((string)$dtKey) ?>" <?= $currentDashboardType === (string)$dtKey ? 'selected' : '' ?>><?= e($dtLabel) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <div class="a2-scope-note muted" data-scope-note="workspace"><?= t('admin.access.detail.workspace_note') ?></div>
        <div class="access2-feedback" data-feedback="workspace"></div>
      </div>
    </details>

    <!-- ══ Stage 4: Permission Assignment ══════════════════════════════════ -->
    <!-- hidden for tv_display -->
    <details class="access2-stage" data-stage="permissions" data-operator-stage="1">
      <summary>
        <span class="access2-stage-index">4</span>
        <span class="access2-stage-title"><?= t('admin.access.detail.stage_permissions') ?></span>
        <span class="access2-stage-summary" data-summary="permissions"></span>
      </summary>
      <div class="access2-stage-body">
        <div class="access2-note"><?= t('admin.access.detail.modules_note') ?></div>
        <?php foreach ($allPermissionApps as $pApp): ?>
          <?php
            $appProfileOptions = [];
            foreach ($profileOptions as $pKey => $profileDef) {
              if (strtolower(trim((string)($profileDef['app'] ?? 'platform'))) === $pApp) {
                $appProfileOptions[$pKey] = $profileDef;
              }
            }
            $appModules = $moduleGroups[$pApp] ?? [];
          ?>
          <div class="a2-permissions-app-group" data-profile-group-app="<?= e((string)$pApp) ?>">
            <div class="access2-module-group-title"><?= e((string)($appRegistry[$pApp]['label'] ?? $pApp)) ?></div>
            <?php if ($appProfileOptions !== []): ?>
              <div class="tg-wrap a2-profile-row" data-profile-row-app="<?= e((string)$pApp) ?>" data-show-unavailable="0">
                <?php foreach ($appProfileOptions as $profileKey => $profileDef): ?>
                  <?php $isOn = isset($selectedProfiles[$profileKey]); ?>
                  <button
                    type="button"
                    class="tg-btn access2-profile-token <?= $isOn ? 'is-on' : '' ?>"
                    data-target="access_profiles_<?= $uid ?>"
                    data-mode="multi"
                    data-value="<?= e($profileKey) ?>"
                    data-app="<?= e((string)($profileDef['app'] ?? '')) ?>"
                    data-default-app="<?= e((string)($profileDef['default_app'] ?? '')) ?>"
                    data-authority-scope="<?= e((string)($profileDef['authority_scope'] ?? 'general')) ?>"
                    data-readonly="<?= !empty($profileDef['readonly']) ? '1' : '0' ?>"
                    data-modules="<?= e(implode(',', (array)($profileDef['module_visibility'] ?? []))) ?>"
                    aria-pressed="<?= $isOn ? 'true' : 'false' ?>"
                  ><?= e((string)($profileDef['label'] ?? $profileKey)) ?></button>
                <?php endforeach; ?>
              </div>
              <div class="a2-profile-unavailable-row" data-profile-unavailable-row="<?= e((string)$pApp) ?>" style="display:none">
                <button type="button" class="a2-show-unavailable-btn" data-profile-app="<?= e((string)$pApp) ?>"
                  data-show-label="<?= e(t('admin.access.detail.profiles_show_unavailable')) ?>"
                  data-hide-label="<?= e(t('admin.access.detail.profiles_hide_unavailable')) ?>"
                ><?= t('admin.access.detail.profiles_show_unavailable') ?></button>
                <span class="a2-unavailable-count muted" data-unavailable-count="<?= e((string)$pApp) ?>"></span>
              </div>
            <?php endif; ?>
            <?php if ($appModules !== []): ?>
              <div class="a2-module-inline" data-module-group-app="<?= e((string)$pApp) ?>">
                <div class="access2-module-grid">
                  <?php foreach ($appModules as $moduleKey => $moduleDef): ?>
                    <?php
                      $levels = array_values((array)($moduleDef['levels'] ?? ['none', 'view', 'work', 'approve', 'manage']));
                      $currentLevel = strtolower(trim((string)($moduleHints[$moduleKey] ?? '')));
                      if ($currentLevel === '') {
                        $currentLevel = isset($moduleSet[$moduleKey]) ? 'view' : 'none';
                      }
                    ?>
                    <label class="access2-module-row" data-module-row-app="<?= e((string)$pApp) ?>">
                      <span><?= e((string)($moduleDef['label'] ?? $moduleKey)) ?></span>
                      <select class="module-level-select" data-module="<?= e((string)$moduleKey) ?>" data-app="<?= e((string)$pApp) ?>" data-manual="0">
                        <?php foreach ($levels as $levelOpt): ?>
                          <?php $levelValue = strtolower(trim((string)$levelOpt)); ?>
                          <option value="<?= e($levelValue) ?>" <?= $currentLevel === $levelValue ? 'selected' : '' ?>><?= e(ucfirst($levelValue)) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <div class="access2-feedback" data-feedback="permissions"></div>
      </div>
    </details>

    <!-- ══ Stage 5: Cross-Department Authority ═════════════════════════════ -->
    <!-- hidden for tv_display -->
    <details class="access2-stage" data-stage="cross_app" data-operator-stage="1">
      <summary>
        <span class="access2-stage-index">5</span>
        <span class="access2-stage-title"><?= t('admin.access.detail.stage_cross_app') ?></span>
        <span class="access2-stage-summary" data-summary="cross_app"></span>
      </summary>
      <div class="access2-stage-body">
        <div class="access2-note"><?= t('admin.access.detail.cross_app_note') ?></div>
        <?php if ($crossFunctionalPresets !== []): ?>
          <div class="a2-cross-presets">
            <div class="access2-subtitle"><?= t('admin.access.detail.cross_app_presets') ?></div>
            <div class="tg-wrap">
              <?php foreach ($crossFunctionalPresets as $presetKey => $presetCfg): ?>
                <button type="button" class="tg-btn a2-cross-preset" data-preset-grants="<?= e(implode(',', (array)($presetCfg['grants'] ?? []))) ?>"><?= e((string)($presetCfg['label'] ?? $presetKey)) ?></button>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
        <div class="a2-cross-bundle-grid" id="cross_bundle_grid_<?= $uid ?>">
          <?php foreach ($crossFunctionalBundles as $bundleKey => $bundleCfg): ?>
            <?php
              $bundleLabel = (string)($bundleCfg['label'] ?? $bundleKey);
              $currentBundleLevel = (string)($crossFunctionalAccess[$bundleKey] ?? 'none');
              $bundleLevels = array_values((array)($bundleCfg['levels'] ?? $crossPermissionLevels));
            ?>
            <div class="a2-cross-bundle-row" data-bundle="<?= e((string)$bundleKey) ?>">
              <span class="a2-cross-bundle-label"><?= e($bundleLabel) ?></span>
              <select class="a2-cross-level-select" data-bundle="<?= e((string)$bundleKey) ?>">
                <option value="none" <?= $currentBundleLevel === 'none' ? 'selected' : '' ?>><?= t('admin.access.detail.cross_level_none') ?></option>
                <?php foreach ($bundleLevels as $levelKey): ?>
                  <?php $levelValue = strtolower(trim((string)$levelKey)); ?>
                  <option value="<?= e($levelValue) ?>" <?= $currentBundleLevel === $levelValue ? 'selected' : '' ?>><?= e(ucfirst($levelValue)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="access2-feedback" data-feedback="cross_app"></div>
      </div>
    </details>

    <!-- ══ Stage 6: Passive & Display Access ═══════════════════════════════ -->
    <details class="access2-stage" data-stage="passive">
      <summary>
        <span class="access2-stage-index">6</span>
        <span class="access2-stage-title"><?= t('admin.access.detail.stage_passive') ?></span>
        <span class="access2-stage-summary" data-summary="passive"></span>
      </summary>
      <div class="access2-stage-body">
        <!-- Display Config header — only visible when tv_display -->
        <div class="u-style-6b99de8b69" data-display-only-section="1">
          <div class="access2-note"><?= t('admin.access.detail.display_config_note') ?></div>
          <div class="access2-subtitle"><?= t('admin.access.detail.display_panels_title') ?></div>
        </div>
        <!-- Passive access header — hidden when tv_display -->
        <div class="ui-block" data-passive-only-section="1">
          <div class="access2-readonly-section" data-readonly-profile-panel="1">
            <div class="access2-subtitle"><?= t('admin.access.detail.readonly_profiles_title') ?></div>
            <div class="tg-wrap">
              <?php foreach ($profileOptions as $profileDef): ?>
                <?php if (empty($profileDef['readonly'])) { continue; } ?>
                <?php $profileKey = (string)($profileDef['key'] ?? ''); ?>
                <?php $isOn = isset($readOnlyProfilesSet[$profileKey]) || isset($selectedProfiles[$profileKey]); ?>
                <button
                  type="button"
                  class="tg-btn access2-readonly-profile <?= $isOn ? 'is-on' : '' ?>"
                  data-target="readonly_profiles_<?= $uid ?>"
                  data-mode="multi"
                  data-value="<?= e($profileKey) ?>"
                  data-app="<?= e((string)($profileDef['app'] ?? '')) ?>"
                  aria-pressed="<?= $isOn ? 'true' : 'false' ?>"
                ><?= e((string)($profileDef['label'] ?? $profileKey)) ?></button>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="access2-subtitle"><?= t('admin.access.detail.display_surfaces_title') ?></div>
        </div>
        <!-- Shared display/surface tokens (context changes by authority) -->
        <div class="tg-wrap" id="passive_surface_tokens_<?= $uid ?>">
          <?php foreach ($displaySurfaceCatalog as $surfaceDef): ?>
            <?php
              $surfaceKey = (string)($surfaceDef['key'] ?? '');
              $tokenType  = (string)($surfaceDef['token_type'] ?? '');
              $tokenKey   = (string)($surfaceDef['token_key'] ?? '');
              $isOn = isset($displaySurfaceSet[$surfaceKey]);
              if ($tokenType === 'view'        && isset($viewSet[$tokenKey]))       { $isOn = true; }
              if ($tokenType === 'table'       && isset($tableSet[$tokenKey]))      { $isOn = true; }
              if ($tokenType === 'chart'       && isset($chartSet[$tokenKey]))      { $isOn = true; }
              if ($tokenType === 'plugin_card' && isset($pluginSet[$tokenKey]))     { $isOn = true; }
              if ($tokenType === 'module'      && isset($moduleSet[$tokenKey]))     { $isOn = true; }
            ?>
            <button
              type="button"
              class="tg-btn access2-display-token <?= $isOn ? 'is-on' : '' ?>"
              data-target="display_surfaces_<?= $uid ?>"
              data-mode="multi"
              data-value="<?= e($surfaceKey) ?>"
              data-token-type="<?= e($tokenType) ?>"
              data-token-key="<?= e($tokenKey) ?>"
              data-app="<?= e((string)($surfaceDef['app'] ?? '')) ?>"
              aria-pressed="<?= $isOn ? 'true' : 'false' ?>"
            ><?= e((string)($surfaceDef['label'] ?? $surfaceKey)) ?></button>
          <?php endforeach; ?>
        </div>
        <div class="access2-feedback" data-feedback="passive"></div>
      </div>
    </details>

    <!-- ══ Stage 7: Effective Policy Preview ═══════════════════════════════ -->
    <details class="access2-stage" data-stage="preview">
      <summary>
        <span class="access2-stage-index">7</span>
        <span class="access2-stage-title"><?= t('admin.access.detail.stage_preview') ?></span>
        <span class="access2-stage-summary" data-summary="preview"></span>
      </summary>
      <div class="access2-stage-body">
        <div class="access2-preview-grid">
          <div class="access2-preview-card">
            <div class="access2-preview-label"><?= t('admin.access.detail.preview_authority') ?></div>
            <div class="access2-preview-value" data-preview="authority"></div>
          </div>
          <div class="access2-preview-card">
            <div class="access2-preview-label"><?= t('admin.access.detail.preview_account_class') ?></div>
            <div class="access2-preview-value" data-preview="account_class"></div>
          </div>
          <div class="access2-preview-card">
            <div class="access2-preview-label"><?= t('admin.access.detail.preview_workspace') ?></div>
            <div class="access2-preview-value" data-preview="workspace"></div>
          </div>
          <div class="access2-preview-card">
            <div class="access2-preview-label"><?= t('admin.access.detail.preview_scope') ?></div>
            <div class="access2-preview-value" data-preview="scope"></div>
          </div>
          <div class="access2-preview-card">
            <div class="access2-preview-label"><?= t('admin.access.detail.preview_apps') ?></div>
            <div class="access2-preview-value" data-preview="apps"></div>
          </div>
          <div class="access2-preview-card">
            <div class="access2-preview-label"><?= t('admin.access.detail.preview_interaction') ?></div>
            <div class="access2-preview-value" data-preview="interaction"></div>
          </div>
          <div class="access2-preview-card">
            <div class="access2-preview-label"><?= t('admin.access.detail.preview_profiles') ?></div>
            <div class="access2-preview-value" data-preview="profiles"></div>
          </div>
          <div class="access2-preview-card">
            <div class="access2-preview-label"><?= t('admin.access.detail.preview_cross_app') ?></div>
            <div class="access2-preview-value" data-preview="cross_app"></div>
          </div>
          <div class="access2-preview-card">
            <div class="access2-preview-label"><?= t('admin.access.detail.preview_constraints') ?></div>
            <div class="access2-preview-value" data-preview="constraints"></div>
          </div>
        </div>
        <div class="access2-warning-list" data-preview="warnings"></div>
      </div>
    </details>

  </section>

  <div class="row assignment-detail-actions">
    <button class="btn ok assignment-primary-action" type="submit"><?= t('admin.access.detail.save_btn') ?></button>
  </div>
</form>

<?php
// ── Effective Workspace Profile summary (selection now handled in Stage 1) ──
?>
<section class="card detail-block u-style-1b0f4999d2" data-access2-workspace-profile="1">
  <div class="u-style-7f5b67ce7b">
    <h4 class="u-style-1169661891"><?= e(t('ops.access_control.workspace_profile.section_title')) ?></h4>
    <div class="u-style-09397ca7f5">
      <?php if ($workspaceResolvedProfile !== null && trim((string)($workspaceResolvedProfile['profile_key'] ?? '')) !== ''): ?>
        <a href="/ops/access-control?workspace_profile=<?= e((string)$workspaceResolvedProfile['profile_key']) ?>" class="muted access-governance-compact-link"><?= e(t('common.open')) ?></a>
      <?php endif; ?>
      <a href="/ops/workspace-profiles" class="muted u-style-b09f128514"><?= e(t('ops.access_control.workspace_profile.manage_link')) ?> &rarr;</a>
    </div>
  </div>

  <?php if ($workspaceResolvedProfile !== null): ?>
  <div class="access2-preview-grid">
    <div class="access2-preview-card">
      <div class="access2-preview-label"><?= e(t('ops.access_control.workspace_profile.col_key')) ?></div>
      <div class="access2-preview-value">
        <?php if ($workspaceResolvedProfileDetailUrl !== ''): ?>
          <a href="<?= e($workspaceResolvedProfileDetailUrl) ?>"><code class="u-style-7a4e3aa359"><?= e($workspaceResolvedProfile['profile_key'] ?? '') ?></code></a>
        <?php else: ?>
          <code class="u-style-7a4e3aa359"><?= e($workspaceResolvedProfile['profile_key'] ?? '') ?></code>
        <?php endif; ?>
        <?php if (!empty($workspaceResolvedProfile['is_pinned'])): ?>
          <span class="badge u-style-e7d2b751a4"><?= e(t('ops.access_control.workspace_profile.pinned_badge')) ?></span>
        <?php endif; ?>
      </div>
    </div>
    <div class="access2-preview-card">
      <div class="access2-preview-label"><?= e(t('ops.access_control.workspace_profile.col_name')) ?></div>
      <div class="access2-preview-value">
        <?php if ($workspaceResolvedProfileDetailUrl !== ''): ?>
          <a href="<?= e($workspaceResolvedProfileDetailUrl) ?>"><?= e($workspaceResolvedProfile['name'] ?? '') ?></a>
        <?php else: ?>
          <?= e($workspaceResolvedProfile['name'] ?? '') ?>
        <?php endif; ?>
      </div>
    </div>
    <div class="access2-preview-card">
      <div class="access2-preview-label"><?= e(t('ops.access_control.workspace_profile.col_landing')) ?></div>
      <div class="access2-preview-value"><code class="u-style-7a4e3aa359"><?= e($workspaceResolvedProfile['landing_route'] ?? '') ?></code></div>
    </div>
    <div class="access2-preview-card">
      <div class="access2-preview-label"><?= e(t('ops.access_control.workspace_profile.col_widgets')) ?></div>
      <div class="access2-preview-value"><?= e($workspaceResolvedProfile['widget_discovery'] ?? 'auto') ?></div>
    </div>
  </div>
  <?php else: ?>
  <div class="muted u-style-7a4e3aa359"><?= e(t('ops.access_control.workspace_profile.no_profile')) ?></div>
  <?php endif; ?>

</section>

<?php
// ── Per-App Operational Roles (migrated from legacy Access tab) ────────────
$appRoles = is_array($appRoles ?? null) ? $appRoles : [];
$arUid = (int)($row['id'] ?? 0);
$arRedirectTo = '/ops/access-control/detail?user_id=' . $arUid . '&full=1&tab=access2';
$appRoleAppOptions = [];
foreach ($appRegistry as $arOptKey => $arOptDef) {
  $appRoleAppOptions[$arOptKey] = (string)($arOptDef['label'] ?? $arOptKey);
}
$appRoleRolesByApp = is_array($assignmentUiConfig['appRolesByKey'] ?? null)
  ? (array)$assignmentUiConfig['appRolesByKey']
  : [];
?>
<section class="card detail-block access-governance-app-roles" data-access2-app-roles="1" id="app-roles-section-<?= $arUid ?>">
  <div class="u-style-7f5b67ce7b">
    <h4 class="u-style-1169661891"><?= e(t('ops.access_control.app_role.section_title')) ?></h4>
    <span class="muted u-style-a4178a416c"><?= e(t('ops.access_control.app_role.section_note')) ?></span>
  </div>

  <?php if ($appRoles !== []): ?>
  <table class="data-table u-style-87c136dfd0">
    <thead>
      <tr>
        <th><?= e(t('ops.access_control.app_role.col_app')) ?></th>
        <th><?= e(t('ops.access_control.app_role.col_role')) ?></th>
        <th><?= e(t('ops.access_control.app_role.col_primary')) ?></th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($appRoles as $ar): ?>
        <?php
          $arAppKey = (string)($ar['app_key'] ?? '');
          $arRole = (string)($ar['operational_role'] ?? '');
          $arPrimary = (bool)($ar['is_primary'] ?? false);
          $arAppLabel = $appRoleAppOptions[$arAppKey] ?? $normalizeLabel($arAppKey);
          $arRoleKey = 'ops.access_control.app_role.role.' . $arRole;
          $arRoleLabel = (string)t($arRoleKey);
          if ($arRoleLabel === '' || $arRoleLabel === $arRoleKey) { $arRoleLabel = $normalizeLabel($arRole); }
        ?>
        <tr>
          <td><strong><?= e($arAppLabel) ?></strong> <small class="muted">(<?= e($arAppKey) ?>)</small></td>
          <td><?= e($arRoleLabel) ?></td>
          <td><?= $arPrimary ? '<span class="badge ok">✓</span>' : '<span class="muted">—</span>' ?></td>
          <td class="u-style-54c2afb7ba">
            <form method="post" action="/ops/access-control/app-role/delete" class="access-governance-inline-form" onsubmit="return confirm(<?= json_encode(t('ops.access_control.app_role.confirm_delete')) ?>)">
              <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
              <input type="hidden" name="user_id" value="<?= $arUid ?>">
              <input type="hidden" name="app_key" value="<?= e($arAppKey) ?>">
              <input type="hidden" name="redirect_to" value="<?= e($arRedirectTo) ?>">
              <button type="submit" class="btn err small"><?= e(t('ops.access_control.app_role.btn_remove')) ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
  <div class="muted u-style-2b583d7389"><?= e(t('ops.access_control.app_role.empty')) ?></div>
  <?php endif; ?>

  <details class="advanced-subsection" open>
    <summary><strong><?= e(t('ops.access_control.app_role.add_title')) ?></strong></summary>
    <form method="post" action="/ops/access-control/app-role/save" class="inline-form u-style-1253b03995" data-app-role-form="1">
      <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
      <input type="hidden" name="user_id" value="<?= $arUid ?>">
      <input type="hidden" name="redirect_to" value="<?= e($arRedirectTo) ?>">

      <div class="field-group">
        <label for="a2_app_role_app_<?= $arUid ?>"><?= e(t('ops.access_control.app_role.col_app')) ?></label>
        <select name="app_key" id="a2_app_role_app_<?= $arUid ?>" data-app-role-app-select="1">
          <option value=""><?= e(t('ops.access_control.app_role.pick_app')) ?></option>
          <?php foreach ($appRoleAppOptions as $optKey => $optLabel): ?>
            <option value="<?= e($optKey) ?>"><?= e($optLabel) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field-group">
        <label for="a2_app_role_role_<?= $arUid ?>"><?= e(t('ops.access_control.app_role.col_role')) ?></label>
        <select name="operational_role" id="a2_app_role_role_<?= $arUid ?>" data-app-role-role-select="1">
        </select>
      </div>

      <div class="field-group u-style-6ee0661ec0">
        <label class="u-style-896e5ae5ea">
          <input type="checkbox" name="is_primary" value="1">
          <?= e(t('ops.access_control.app_role.mark_primary')) ?>
        </label>
      </div>

      <div class="u-style-6ee0661ec0">
        <button type="submit" class="btn ok"><?= e(t('ops.access_control.app_role.btn_save')) ?></button>
      </div>
    </form>
  </details>
</section>

<script>
(function () {
  var section = document.getElementById(<?= json_encode('app-roles-section-' . $arUid) ?>);
  if (!section) { return; }

  var rolesByApp = <?= json_encode($appRoleRolesByApp, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  var form = section.querySelector('[data-app-role-form]');
  var appSelect = form ? form.querySelector('[data-app-role-app-select]') : null;
  var roleSelect = form ? form.querySelector('[data-app-role-role-select]') : null;
  if (!appSelect || !roleSelect) { return; }

  function replaceRoleOptions() {
    var appKey = String(appSelect.value || '').toLowerCase();
    var roles = appKey && rolesByApp[appKey] && typeof rolesByApp[appKey] === 'object'
      ? rolesByApp[appKey]
      : {};
    var current = String(roleSelect.value || '').toLowerCase();
    var nextSelected = '';
    roleSelect.innerHTML = '';
    Object.keys(roles).forEach(function (roleKey) {
      var option = document.createElement('option');
      option.value = roleKey;
      option.textContent = String(roles[roleKey] || roleKey);
      roleSelect.appendChild(option);
      if (nextSelected === '') {
        nextSelected = roleKey;
      }
      if (String(roleKey).toLowerCase() === current) {
        nextSelected = roleKey;
      }
    });
    if (nextSelected !== '') {
      roleSelect.value = nextSelected;
    }
    roleSelect.disabled = nextSelected === '';
  }

  appSelect.addEventListener('change', replaceRoleOptions);
  replaceRoleOptions();
})();
</script>

<script>
(function () {
  var form = document.getElementById(<?= json_encode($accessFormId) ?>);
  if (!form) { return; }

  // ── PHP-injected data ────────────────────────────────────────────────────
  var accessAuthorities      = <?= json_encode($accessAuthorities,      JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  var accountClassOptions    = <?= json_encode($accountClassOptions,    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  var interactionProfiles    = <?= json_encode($interactionProfiles,    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  var controlScopeOptions    = <?= json_encode($controlScopeOptions,    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  var appLabels              = <?= json_encode(array_map(static fn(array $r): string => (string)($r['label'] ?? ''), $appRegistry), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  var appFocusesByKey        = <?= json_encode($appFocusesByKey,        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  var canonicalDefaults      = <?= json_encode($canonicalDefaults,      JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  var profileLabels          = <?= json_encode(array_map(static fn(array $r): string => (string)($r['label'] ?? ''), $profileOptions), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  var dashboardsByAccount    = <?= json_encode($dashboardsByAccount,    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  var dashboardTypeLabels    = <?= json_encode($dashboardTypeLabels,    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  var landingByDashboard     = <?= json_encode($landingByDashboard,     JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  var crossFunctionalBundles = <?= json_encode(array_map(static fn(array $r): string => (string)($r['label'] ?? ''), $crossFunctionalBundles), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  var workspaceProfileCatalog = <?= json_encode($workspaceProfileCatalog, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  var resolvedWorkspaceProfileKey = <?= json_encode($workspaceResolvedProfileKey, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  var allEnabledAppsToken = <?= json_encode((string)($assignmentUiConfig['allEnabledAppsToken'] ?? '__all_enabled__'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

  // ── Locale strings injected from PHP ────────────────────────────────────
  var i18n = {
    noteAuthority: {
      platform_admin: <?= json_encode((string)t('admin.access.detail.note_authority_platform_admin'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
      app_admin:      <?= json_encode((string)t('admin.access.detail.note_authority_app_admin'),      JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
      app_user:       <?= json_encode((string)t('admin.access.detail.note_authority_app_user'),       JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
      tv_display:     <?= json_encode((string)t('admin.access.detail.note_authority_tv_display'),     JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
    },
    noteClass: {
      platform_operations: <?= json_encode((string)t('admin.access.detail.note_class_platform_operations'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
      platform_security:   <?= json_encode((string)t('admin.access.detail.note_class_platform_security'),   JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
      app_administration:  <?= json_encode((string)t('admin.access.detail.note_class_app_administration'),  JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
      app_user:            <?= json_encode((string)t('admin.access.detail.note_class_app_user'),            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
      tv_display:          <?= json_encode((string)t('admin.access.detail.note_class_tv_display'),          JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
    },
    noteScope: {
      platform: <?= json_encode((string)t('admin.access.detail.note_scope_platform'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
      app:      <?= json_encode((string)t('admin.access.detail.note_scope_app'),      JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
      module:   <?= json_encode((string)t('admin.access.detail.note_scope_module'),   JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
      shared:   <?= json_encode((string)t('admin.access.detail.note_scope_shared'),   JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
    },
    noteWorkspaceDisplay: <?= json_encode((string)t('admin.access.detail.note_workspace_display'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
    noteWorkspaceDefault: <?= json_encode((string)t('admin.access.detail.note_workspace_default'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
    warnAssignedAppsEmpty:  <?= json_encode((string)t('admin.access.detail.warn_assigned_apps_empty'),  JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
    warnNoProfiles:         <?= json_encode((string)t('admin.access.detail.warn_no_profiles'),          JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
    warnDefaultAppOutside:  <?= json_encode((string)t('admin.access.detail.warn_default_app_outside'),  JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
    warnReadonlyHighLevels: <?= json_encode((string)t('admin.access.detail.warn_readonly_high_levels'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
    warnLandingInvalid:     <?= json_encode((string)t('admin.access.detail.warn_landing_invalid'),      JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
    profilesShowUnavailable: <?= json_encode((string)t('admin.access.detail.profiles_show_unavailable'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
    profilesHideUnavailable: <?= json_encode((string)t('admin.access.detail.profiles_hide_unavailable'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
    profilesUnavailableCount: <?= json_encode((string)t('admin.access.detail.profiles_unavailable_count'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
    saveBlockedTitle: <?= json_encode((string)t('admin.access.detail.save_blocked_title'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
  };

  // ── DOM refs ─────────────────────────────────────────────────────────────
  var authoritySelect       = form.querySelector('#account_type_<?= $uid ?>');
  var accountClassSelect    = form.querySelector('#account_class_<?= $uid ?>');
  var workspaceProfileSelect = form.querySelector('#workspace_profile_key_<?= $uid ?>');
  var dashboardTypeSelect   = form.querySelector('#dashboard_type_<?= $uid ?>');
  var interactionSelect     = form.querySelector('#interaction_profile_<?= $uid ?>');
  var focusSelect           = form.querySelector('#operational_focus_<?= $uid ?>');
  var scopeSelect           = form.querySelector('#governance_scope_<?= $uid ?>');
  var defaultAppSelect      = form.querySelector('#default_app_<?= $uid ?>');
  var landingSelect         = form.querySelector('#default_landing_page_<?= $uid ?>');
  var assignedInput         = form.querySelector('#assigned_apps_<?= $uid ?>');
  var accessProfilesInput   = form.querySelector('#access_profiles_<?= $uid ?>');
  var moduleVisibilityInput = form.querySelector('#module_visibility_<?= $uid ?>');
  var moduleHintsInput      = form.querySelector('#module_access_hints_<?= $uid ?>');
  var readonlyProfilesInput = form.querySelector('#readonly_profiles_<?= $uid ?>');
  var displaySurfacesInput  = form.querySelector('#display_surfaces_<?= $uid ?>');
  var viewAccessInput       = form.querySelector('#view_access_<?= $uid ?>');
  var tableAccessInput      = form.querySelector('#table_access_<?= $uid ?>');
  var chartAccessInput      = form.querySelector('#chart_access_<?= $uid ?>');
  var pluginCardsInput      = form.querySelector('#me_plugin_cards_<?= $uid ?>');
  var crossAccessInput      = form.querySelector('#cross_functional_access_<?= $uid ?>');
  var suppressProfileAutoApply = false;
  var lastPermissionUpstreamSignature = null;

  // ── Authority → account_class compatibility ──────────────────────────────
  var authorityToAccountClass = {
    'platform_admin': ['platform_operations', 'platform_security'],
    'app_admin':      ['app_administration'],
    'app_user':       ['app_user'],
    'tv_display':     ['tv_display']
  };
  var authorityDefaultAccountClass = {
    'platform_admin': 'platform_operations',
    'app_admin':      'app_administration',
    'app_user':       'app_user',
    'tv_display':     'tv_display'
  };

  // ── Helpers ──────────────────────────────────────────────────────────────
  function normalizeCsv(value) {
    return String(value || '').split(',')
      .map(function (i) { return i.trim().toLowerCase(); })
      .filter(function (i, idx, arr) { return i !== '' && arr.indexOf(i) === idx; });
  }
  function writeCsv(input, values) {
    if (input) { input.value = values.join(','); }
  }
  function labelFor(map, key) { return map[key] || key || 'None'; }
  function allAssignableApps() {
    return Object.keys(appLabels).filter(function (key) {
      return String(key || '').trim() !== '';
    });
  }
  function expandAssignedAppTokens(values) {
    var expanded = [];
    values.forEach(function (value) {
      if (String(value || '').toLowerCase() === String(allEnabledAppsToken || '').toLowerCase()) {
        allAssignableApps().forEach(function (app) { expanded.push(app); });
      } else {
        expanded.push(value);
      }
    });
    return normalizeCsv(expanded.join(','));
  }
  function selectedAssignedApps() { return expandAssignedAppTokens(normalizeCsv(assignedInput ? assignedInput.value : '')); }
  function selectedProfiles()     { return normalizeCsv(accessProfilesInput ? accessProfilesInput.value : ''); }
  function selectedReadonly()     { return normalizeCsv(readonlyProfilesInput ? readonlyProfilesInput.value : ''); }
  function selectedDisplay()      { return normalizeCsv(displaySurfacesInput ? displaySurfacesInput.value : ''); }
  function isDisplayAccount() {
    return String(authoritySelect ? authoritySelect.value : '').toLowerCase() === 'tv_display';
  }
  function setSummary(stage, value) {
    var n = form.querySelector('[data-summary="' + stage + '"]');
    if (n) { n.textContent = value; }
  }
  function setFeedback(stage, messages) {
    var n = form.querySelector('[data-feedback="' + stage + '"]');
    if (n) { n.textContent = Array.isArray(messages) ? messages.join(' ') : (messages || ''); }
  }
  function setPreview(name, value) {
    var n = form.querySelector('[data-preview="' + name + '"]');
    if (n) { n.textContent = value || 'None'; n.classList.toggle('access2-empty', !value); }
  }

  function operationalProfileValue() {
    var authority = String(authoritySelect ? authoritySelect.value : 'app_user').toLowerCase();
    var accountClass = String(accountClassSelect ? accountClassSelect.value : '').toLowerCase();

    if (authority === 'platform_admin') {
      if (accountClass === 'platform_security') { return 'platformsecurity'; }
      return 'platformoperations';
    }

    var interaction = String(interactionSelect ? interactionSelect.value : 'worker').toLowerCase();
    var focus       = String(focusSelect       ? focusSelect.value       : 'production').toLowerCase();
    if (interaction === 'admin')                             { return 'appadministration'; }
    if (interaction === 'display' || interaction === 'read_only') { return 'readonlyobserver'; }
    var assignedApps = selectedAssignedApps();
    var hasSbaio = assignedApps.indexOf('sbaio') !== -1;
    var map = {
      production: interaction === 'leader' ? 'productionleader'  : 'productionworker',
      assembly:   interaction === 'leader' ? 'assemblyleader'    : 'assemblyworker',
      qc:         interaction === 'leader' ? 'qcleader'          : 'qcworker',
      dispatch:   interaction === 'leader' ? 'dispatchleader'    : 'dispatchworker',
      planner:    'manufacturingplanner',
      office_ops: hasSbaio ? 'sbaio_operator' : 'readonlyobserver'
    };
    return map[focus] || 'productionworker';
  }

  function syncOperationalProfile() {
    var input = form.querySelector('input[name="operational_role"]');
    if (!input) {
      input = document.createElement('input');
      input.type = 'hidden'; input.name = 'operational_role';
      form.appendChild(input);
    }
    input.value = operationalProfileValue();
  }

  function profileSeedFromUpstream() {
    var seeds = [];
    var profileKey = activeWorkspaceProfileKey();
    if (profileKey !== '' && workspaceProfileCatalog[profileKey]) {
      seeds = normalizeCsv(workspaceProfileCatalog[profileKey].access_profiles || '');
    }
    if (seeds.length === 0) {
      var canonical = canonicalForCurrentSelection();
      if (canonical && canonical.recommended_access_profiles) {
        seeds = normalizeCsv(canonical.recommended_access_profiles);
      }
    }
    return seeds;
  }

  function permissionUpstreamSignature() {
    return [
      String(authoritySelect ? authoritySelect.value : '').toLowerCase(),
      String(accountClassSelect ? accountClassSelect.value : '').toLowerCase(),
      activeWorkspaceProfileKey(),
      selectedAssignedApps().join(','),
      String(defaultAppSelect ? defaultAppSelect.value : '').toLowerCase(),
      String(dashboardTypeSelect ? dashboardTypeSelect.value : '').toLowerCase(),
      String(interactionSelect ? interactionSelect.value : '').toLowerCase(),
      String(focusSelect ? focusSelect.value : '').toLowerCase(),
      String(scopeSelect ? scopeSelect.value : '').toLowerCase()
    ].join('|');
  }

  function reconcilePermissionUpstream() {
    var signature = permissionUpstreamSignature();
    if (lastPermissionUpstreamSignature === null) {
      lastPermissionUpstreamSignature = signature;
      return;
    }
    if (lastPermissionUpstreamSignature === signature) {
      return;
    }
    lastPermissionUpstreamSignature = signature;
    writeCsv(accessProfilesInput, []);
    form.querySelectorAll('.access2-profile-token.is-on').forEach(function (btn) {
      btn.classList.remove('is-on');
      btn.setAttribute('aria-pressed', 'false');
    });
    form.querySelectorAll('.module-level-select[data-module]').forEach(function (sel) {
      if (String(sel.dataset.manual || '0') !== '1') {
        sel.value = 'none';
      }
    });
  }

  function setMultiTokenState(selector, activeValues) {
    var active = {};
    activeValues.forEach(function (v) { active[String(v || '').toLowerCase()] = true; });
    form.querySelectorAll(selector).forEach(function (btn) {
      var value = String(btn.getAttribute('data-value') || '').toLowerCase();
      var on = !!active[value];
      btn.classList.toggle('is-on', on);
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  }

  function pickFirstVisibleOption(selectEl) {
    if (!selectEl) { return; }
    for (var i = 0; i < selectEl.options.length; i++) {
      if (!selectEl.options[i].hidden) {
        selectEl.selectedIndex = i;
        return;
      }
    }
  }

  function inferFocusFromAssignedApps(apps) {
    if (apps.indexOf('manufacturing') !== -1) { return 'production'; }
    if (apps.indexOf('sbaio') !== -1) { return 'office_ops'; }
    return 'office_ops';
  }

  function activeWorkspaceProfileKey() {
    var explicitKey = String(workspaceProfileSelect ? workspaceProfileSelect.value : '').toLowerCase();
    if (explicitKey !== '' && workspaceProfileCatalog[explicitKey]) {
      return explicitKey;
    }
    var resolvedKey = String(resolvedWorkspaceProfileKey || '').toLowerCase();
    if (resolvedKey !== '' && workspaceProfileCatalog[resolvedKey]) {
      return resolvedKey;
    }
    return '';
  }

  function syncAuthorityFromWorkspaceProfile() {
    var profileKey = activeWorkspaceProfileKey();
    if (profileKey === '' || !workspaceProfileCatalog[profileKey] || !authoritySelect) { return; }
    var profileAuthority = String(workspaceProfileCatalog[profileKey].authority_role || '').toLowerCase();
    if (profileAuthority !== '' && accessAuthorities[profileAuthority]) {
      authoritySelect.value = profileAuthority;
    }
  }

  function syncWorkspaceProfileOptions() {
    if (!workspaceProfileSelect) { return; }

    var visibleCount = 0;

    Array.prototype.forEach.call(workspaceProfileSelect.options, function (option) {
      var key = String(option.value || '').toLowerCase();
      if (key === '') {
        option.hidden = false;
        option.disabled = false;
        return;
      }

      var compatible = !!workspaceProfileCatalog[key];
      option.hidden = !compatible;
      option.disabled = !compatible;
      if (compatible) { visibleCount++; }
    });

    var messages = [];
    if (visibleCount === 0) {
      messages.push(i18n.warnNoProfiles);
    }
    setFeedback('identity', messages);
  }

  function applyWorkspaceProfileDefaults() {
    if (!workspaceProfileSelect) { return; }
    var profileKey = String(workspaceProfileSelect.value || '').toLowerCase();
    if (profileKey === '' || !workspaceProfileCatalog[profileKey]) { return; }
    var profile = workspaceProfileCatalog[profileKey] || {};

    if (profile.authority_role && authoritySelect && accessAuthorities[profile.authority_role]) {
      authoritySelect.value = profile.authority_role;
    }

    var authority = String(authoritySelect ? authoritySelect.value : '').toLowerCase();
    if (accountClassSelect && authorityToAccountClass[authority] && authorityToAccountClass[authority].length) {
      accountClassSelect.value = authorityDefaultAccountClass[authority] || authorityToAccountClass[authority][0] || accountClassSelect.value;
    }

    var assignedApps = expandAssignedAppTokens(normalizeCsv(profile.assigned_apps || ''));
    if (assignedApps.length === 0 && profile.default_app) {
      assignedApps = [String(profile.default_app).toLowerCase()];
    }
    if (assignedApps.length === 0 && authority === 'platform_admin') {
      assignedApps = ['platform'];
    }
    if (assignedApps.length > 0) {
      setMultiTokenState('.access2-app-token[data-value]', assignedApps);
      writeCsv(assignedInput, assignedApps);
    }

    if (profile.default_app && defaultAppSelect) {
      defaultAppSelect.value = String(profile.default_app).toLowerCase();
      var defaultAppModeInput = form.querySelector('input[name="default_app_mode"]');
      if (defaultAppModeInput) {
        defaultAppModeInput.value = 'manual';
      }
    }

    var accessProfiles = normalizeCsv(profile.access_profiles || '');
    if (accessProfiles.length > 0) {
      setMultiTokenState('.access2-profile-token[data-value]', accessProfiles);
      writeCsv(accessProfilesInput, accessProfiles);
    }

    if (interactionSelect) {
      if (authority === 'platform_admin' || authority === 'app_admin') {
        interactionSelect.value = 'admin';
      } else {
        interactionSelect.value = 'worker';
      }
    }

    if (focusSelect && assignedApps.length > 0) {
      focusSelect.value = inferFocusFromAssignedApps(assignedApps);
    }

    if (scopeSelect) {
      scopeSelect.value = authority === 'platform_admin'
        ? 'platform'
        : (authority === 'app_admin' ? 'app' : 'module');
    }

    refreshComposer();

    if (dashboardTypeSelect) {
      pickFirstVisibleOption(dashboardTypeSelect);
      var dashboardModeInput = form.querySelector('input[name="dashboard_mode"]');
      if (dashboardModeInput) {
        dashboardModeInput.value = 'manual';
      }
      syncWorkspace();
    }

    var landingRoute = String(profile.landing_route || '');
    if (landingRoute !== '' && landingSelect) {
      var hasLanding = Array.prototype.some.call(landingSelect.options, function (opt) {
        return String(opt.value || '') === landingRoute;
      });
      if (hasLanding) {
        landingSelect.value = landingRoute;
        var landingModeInput = form.querySelector('input[name="landing_mode"]');
        if (landingModeInput) {
          landingModeInput.value = landingRoute === '/' ? 'auto' : 'manual';
        }
      }
    } else if (landingSelect) {
      pickFirstVisibleOption(landingSelect);
      var landingModeInputDefault = form.querySelector('input[name="landing_mode"]');
      if (landingModeInputDefault) {
        landingModeInputDefault.value = landingSelect.value === '/' ? 'auto' : 'manual';
      }
    }

    refreshComposer();
  }

  function syncAssignedAppsFromWorkspaceProfile() {
    var profileKey = activeWorkspaceProfileKey();
    if (profileKey === '' || !workspaceProfileCatalog[profileKey]) { return; }
    var profile = workspaceProfileCatalog[profileKey] || {};
    var assignedApps = normalizeCsv(profile.assigned_apps || '');
    var defaultApp = String(profile.default_app || '').toLowerCase();
    if (assignedApps.length === 0 && defaultApp !== '') {
      assignedApps = [defaultApp];
    }
    if (assignedApps.length > 0) {
      setMultiTokenState('.access2-app-token[data-value]', assignedApps);
      writeCsv(assignedInput, assignedApps);
    }
    if (defaultApp !== '' && defaultAppSelect) {
      defaultAppSelect.value = defaultApp;
    }
  }

  function resetProfileManagedState() {
    [assignedInput, accessProfilesInput, moduleVisibilityInput, moduleHintsInput, readonlyProfilesInput, displaySurfacesInput, viewAccessInput, tableAccessInput, chartAccessInput, pluginCardsInput, crossAccessInput].forEach(function (input) {
      writeCsv(input, []);
    });
    form.querySelectorAll('.tg-btn.is-on').forEach(function (btn) {
      btn.classList.remove('is-on');
      btn.setAttribute('aria-pressed', 'false');
    });
    form.querySelectorAll('.module-level-select[data-module]').forEach(function (sel) {
      sel.value = 'none';
      sel.dataset.manual = '0';
    });
    form.querySelectorAll('.a2-cross-level-select').forEach(function (sel) {
      sel.value = 'none';
    });
    form.querySelectorAll('input[name="dashboard_mode"], input[name="default_app_mode"], input[name="landing_mode"], input[name="access_profiles_mode"], input[name="module_visibility_mode"]').forEach(function (input) {
      input.value = 'manual';
    });
  }

  function canonicalForCurrentSelection() {
    var profile   = operationalProfileValue();
    var authority = String(authoritySelect ? authoritySelect.value : 'app_user').toLowerCase();
    return (canonicalDefaults[profile] && canonicalDefaults[profile][authority])
      ? canonicalDefaults[profile][authority]
      : null;
  }

  // ── Stage 1: Identity sync ───────────────────────────────────────────────
  function syncIdentity() {
    var authority = String(authoritySelect ? authoritySelect.value : 'app_user').toLowerCase();
    var display   = authority === 'tv_display';

    // Show/hide operator-only stages
    form.querySelectorAll('[data-operator-stage]').forEach(function (el) {
      el.classList.toggle('access2-hidden', display);
    });

    // Show/hide display config vs passive headers in Stage 6
    form.querySelectorAll('[data-display-only-section]').forEach(function (el) {
      el.style.display = display ? '' : 'none';
    });
    form.querySelectorAll('[data-passive-only-section]').forEach(function (el) {
      el.style.display = display ? 'none' : '';
    });

    // Authority → account_class auto-sync (only if current is incompatible)
    if (accountClassSelect) {
      var compatible = authorityToAccountClass[authority] || [];
      var cur = String(accountClassSelect.value || '').toLowerCase();
      if (compatible.length && compatible.indexOf(cur) === -1) {
        accountClassSelect.value = authorityDefaultAccountClass[authority] || compatible[0] || '';
      }
      // Grey out incompatible options
      Array.prototype.forEach.call(accountClassSelect.options, function (opt) {
        var key = String(opt.value || '').toLowerCase();
        opt.hidden = compatible.length > 0 && compatible.indexOf(key) === -1;
      });
    }

    // Authority scope note
    var noteEl = form.querySelector('[data-scope-note="authority"]');
    if (noteEl) { noteEl.textContent = i18n.noteAuthority[authority] || ''; }

    // Account class note
    var classNoteEl = form.querySelector('[data-scope-note="account_class"]');
    if (classNoteEl) {
      var classVal = accountClassSelect ? String(accountClassSelect.value || '') : '';
      classNoteEl.textContent = i18n.noteClass[classVal] || '';
    }

    var profileKey = String(workspaceProfileSelect ? workspaceProfileSelect.value : '').toLowerCase();
    var profileLabel = profileKey !== '' && workspaceProfileCatalog[profileKey]
      ? String(workspaceProfileCatalog[profileKey].name || profileKey)
      : '';
    setSummary(
      'identity',
      labelFor(accessAuthorities, authority)
        + (accountClassSelect ? ' · ' + labelFor(accountClassOptions, String(accountClassSelect.value || '')) : '')
        + (profileLabel !== '' ? ' · ' + profileLabel : '')
    );
  }

  // ── Stage 2: Workspace routing ───────────────────────────────────────────
  function syncWorkspace() {
    var authority = String(authoritySelect ? authoritySelect.value : 'app_user').toLowerCase();
    // TV/display authority always maps to 'display' dashboard type (mirrors PHP-side override)
    var available = authority === 'tv_display' ? ['display'] : (dashboardsByAccount[authority] || ['my_work']);

    if (dashboardTypeSelect) {
      var curDT = String(dashboardTypeSelect.value || '').toLowerCase();
      // Rebuild options
      while (dashboardTypeSelect.firstChild) { dashboardTypeSelect.removeChild(dashboardTypeSelect.firstChild); }
      available.forEach(function (dtKey) {
        var opt = document.createElement('option');
        opt.value = dtKey;
        opt.textContent = dashboardTypeLabels[dtKey] || dtKey.replace(/_/g, ' ');
        if (dtKey === curDT) { opt.selected = true; }
        dashboardTypeSelect.appendChild(opt);
      });
      if (available.indexOf(curDT) === -1 && dashboardTypeSelect.options.length > 0) {
        dashboardTypeSelect.selectedIndex = 0;
      }
    }

    // Rebuild landing page options from dashboard type
    if (landingSelect && dashboardTypeSelect) {
      var dtVal   = String(dashboardTypeSelect.value || 'my_work').toLowerCase();
      var routes  = landingByDashboard[dtVal] || ['/'];
      var curLand = String(landingSelect.value || '/');
      // Only preserve curLand if it is valid for the new dashboard type; otherwise reset to first valid route.
      var resolvedLand = routes.indexOf(curLand) !== -1 ? curLand : routes[0];
      // For TV/display accounts, prefer an explicit display route over the generic smart-landing '/'.
      if (authority === 'tv_display' && resolvedLand === '/' && routes.length > 1) {
        resolvedLand = routes[0];
      }
      while (landingSelect.firstChild) { landingSelect.removeChild(landingSelect.firstChild); }
      routes.forEach(function (route) {
        var opt = document.createElement('option');
        opt.value = route; opt.textContent = route;
        if (route === resolvedLand) { opt.selected = true; }
        landingSelect.appendChild(opt);
      });
    }

    // Sync landing_mode: 'manual' when non-root selected, 'auto' when root (/)
    var landingModeInput = form.querySelector('input[name="landing_mode"]');
    if (landingModeInput && landingSelect) {
      landingModeInput.value = (landingSelect.value === '/') ? 'auto' : 'manual';
    }

    // Filter default_app by authority / assigned apps
    if (defaultAppSelect) {
      var allowedApps = authority === 'platform_admin' ? ['platform']
        : authority === 'tv_display' ? (function () { var a = selectedAssignedApps(); return a.length ? a : null; }())
        : null; // all allowed for app_admin and app_user
      Array.prototype.forEach.call(defaultAppSelect.options, function (opt) {
        opt.hidden = allowedApps !== null && allowedApps.indexOf(String(opt.value || '').toLowerCase()) === -1;
      });
      if (allowedApps !== null && allowedApps.indexOf(String(defaultAppSelect.value || '').toLowerCase()) === -1) {
        defaultAppSelect.value = allowedApps[0] || '';
      }
    }

    var dtLabel = dashboardTypeSelect ? (dashboardTypeLabels[dashboardTypeSelect.value] || dashboardTypeSelect.value) : '';
    var appLabel = defaultAppSelect ? labelFor(appLabels, String(defaultAppSelect.value || '')) : '';
    var landing  = landingSelect ? String(landingSelect.value || '') : '';
    setSummary('workspace', dtLabel + ' · ' + appLabel + ' · ' + landing);

    var wNote = form.querySelector('[data-scope-note="workspace"]');
    if (wNote) {
      var display = authority === 'tv_display';
      wNote.textContent = display ? i18n.noteWorkspaceDisplay : i18n.noteWorkspaceDefault;
    }

    setFeedback('workspace', []);
  }

  // ── Stage 3: Scope visibility ────────────────────────────────────────────
  function normalizeScopeVisibility() {
    var scope       = String(scopeSelect       ? scopeSelect.value       : 'module').toLowerCase();
    var interaction = String(interactionSelect ? interactionSelect.value : 'worker').toLowerCase();
    var assigned    = selectedAssignedApps();

    var scopeNoteEl = form.querySelector('[data-scope-note="scope"]');
    if (scopeNoteEl) { scopeNoteEl.textContent = i18n.noteScope[scope] || ''; }

    // Show/hide focus field
    var focusField = form.querySelector('#focus_field_<?= $uid ?>');
    var focusHint  = form.querySelector('[data-focus-hint="1"]');
    if (focusField) {
      focusField.classList.toggle('access2-hidden', interaction === 'admin' || interaction === 'display' || interaction === 'read_only');
    }
    if (focusHint) {
      var hints = [];
      if (assigned.indexOf('manufacturing') !== -1) { hints.push('Manufacturing: production, assembly, qc, dispatch, planner'); }
      if (assigned.length && assigned.indexOf('manufacturing') === -1) { hints.push('Non-manufacturing apps: use office_ops focus'); }
      focusHint.textContent = hints.join(' · ');
    }
  }

  // ── Cross-app authority sync ─────────────────────────────────────────────
  function syncCrossAppAuthority() {
    if (isDisplayAccount()) {
      setSummary('cross_app', 'N/A');
      setFeedback('cross_app', []);
      return;
    }
    var pairs = [];
    form.querySelectorAll('.a2-cross-level-select').forEach(function (sel) {
      var bundle = String(sel.getAttribute('data-bundle') || '').toLowerCase();
      var level  = String(sel.value || 'none').toLowerCase();
      if (bundle !== '' && level !== 'none') { pairs.push(bundle + ':' + level); }
    });
    pairs.sort();
    if (crossAccessInput) { crossAccessInput.value = pairs.join(','); }
    var labels = pairs.map(function (p) {
      var pts = p.split(':');
      return (crossFunctionalBundles[pts[0]] || pts[0]) + ' (' + pts[1] + ')';
    });
    setSummary('cross_app', labels.length ? labels.join(', ') : 'None');
    setFeedback('cross_app', labels.length ? ['Cross-app authority extends permissions beyond primary role boundaries.'] : []);
  }

  // ── Interaction constraint cascade ───────────────────────────────────────
  function allowedInteractionsForAuthority(authority) {
    if (authority === 'platform_admin') { return ['admin', 'read_only']; }
    if (authority === 'app_admin')      { return ['admin', 'leader', 'read_only']; }
    return ['worker', 'leader', 'read_only'];
  }

  function filterInteractionOptions() {
    if (isDisplayAccount()) { return; } // tv_display skips interaction stage
    var authority = String(authoritySelect ? authoritySelect.value : 'app_user').toLowerCase();
    var allowed   = allowedInteractionsForAuthority(authority);
    Array.prototype.forEach.call(interactionSelect.options, function (opt) {
      opt.hidden = allowed.indexOf(String(opt.value || '').toLowerCase()) === -1;
    });
    if (allowed.indexOf(String(interactionSelect.value || '').toLowerCase()) === -1) {
      interactionSelect.value = allowed[0] || 'worker';
      setFeedback('scope', ['Interaction profile was adjusted to stay valid for the selected authority.']);
    }
  }

  function allowedScopes(authority, interaction) {
    var byAuth = authority === 'platform_admin' ? ['platform', 'app', 'module', 'shared']
      : authority === 'app_admin' ? ['app', 'module', 'shared']
      : ['module', 'shared'];
    if (interaction === 'read_only' || interaction === 'display') {
      return byAuth.filter(function (s) { return s === 'module' || s === 'shared'; });
    }
    return byAuth;
  }

  function filterScopeOptions() {
    if (isDisplayAccount()) { return; }
    var authority   = String(authoritySelect   ? authoritySelect.value   : 'app_user').toLowerCase();
    var interaction = String(interactionSelect ? interactionSelect.value : 'worker').toLowerCase();
    var allowed     = allowedScopes(authority, interaction);
    Array.prototype.forEach.call(scopeSelect.options, function (opt) {
      opt.hidden = allowed.indexOf(String(opt.value || '').toLowerCase()) === -1;
    });
    if (allowed.indexOf(String(scopeSelect.value || '').toLowerCase()) === -1) {
      scopeSelect.value = allowed[0] || 'module';
    }
  }

  // ── Apps + default app sync ──────────────────────────────────────────────
  function syncAssignedAppsAndDefaultApp() {
    if (isDisplayAccount()) {
      setSummary('scope', 'N/A');
      setFeedback('scope', []);
      return;
    }
    var authority = String(authoritySelect ? authoritySelect.value : 'app_user').toLowerCase();
    syncAssignedAppsFromWorkspaceProfile();

    // Platform token is exclusive to platform_admin — show/hide and deactivate as needed
    form.querySelectorAll('.a2-platform-only-token').forEach(function (btn) {
      var isPlatformAdmin = authority === 'platform_admin';
      btn.classList.toggle('access2-hidden', !isPlatformAdmin);
      if (!isPlatformAdmin && btn.classList.contains('is-on')) {
        btn.classList.remove('is-on');
        btn.setAttribute('aria-pressed', 'false');
      }
    });

    // ── Step 1: Filter OF options based on currently toggled apps FIRST ─────
    // Must happen before canonicalForCurrentSelection() so that removing an app
    // (e.g. SBAIO) updates the focus BEFORE the canonical is read — otherwise
    // the old focus (e.g. office_ops → sbaio_operator canonical) would force
    // the just-removed app back into selected apps.
    var selected = selectedAssignedApps();
    if (authority !== 'platform_admin') {
      selected = selected.filter(function (k) { return k !== 'platform'; });
    } else {
      selected = allAssignableApps();
      setMultiTokenState('.access2-app-token[data-value]', selected);
    }
    var focusSelect = form.querySelector('select[name="operational_focus"]');
    if (focusSelect) {
      var supportedFocuses = {};
      selected.forEach(function (appKey) {
        if (appFocusesByKey[appKey]) {
          Object.keys(appFocusesByKey[appKey]).forEach(function (focusKey) {
            supportedFocuses[focusKey] = true;
          });
        }
      });
      var currentFocusValue = String(focusSelect.value).toLowerCase();
      var hasCurrentFocus = currentFocusValue in supportedFocuses;
      Array.prototype.forEach.call(focusSelect.options, function (opt) {
        opt.hidden = !(String(opt.value || '').toLowerCase() in supportedFocuses);
      });
      if (!hasCurrentFocus) {
        for (var i = 0; i < focusSelect.options.length; i++) {
          if (!focusSelect.options[i].hidden) {
            focusSelect.value = focusSelect.options[i].value;
            break;
          }
        }
      }
    }

    // ── Step 2: Now compute canonical from the (possibly updated) focus ──────
    var canonical = canonicalForCurrentSelection();
    var messages  = [];

    var requiredApp = authority === 'platform_admin' ? 'platform'
      : (authority === 'app_user' && canonical && canonical.default_app ? String(canonical.default_app).toLowerCase() : '');

    if (requiredApp && selected.indexOf(requiredApp) === -1) {
      selected.push(requiredApp);
      var btn = form.querySelector('.access2-app-token[data-value="' + requiredApp + '"]');
      if (btn) { btn.classList.add('is-on'); btn.setAttribute('aria-pressed', 'true'); }
      messages.push(labelFor(appLabels, requiredApp) + ' kept in Assigned Apps — required by current authority.');
    }
    selected = selected.filter(function (k) { return !!appLabels[k]; });
    if (!selected.length && defaultAppSelect && defaultAppSelect.value) {
      var fallbackDefault = String(defaultAppSelect.value).toLowerCase();
      if (authority === 'platform_admin' || fallbackDefault !== 'platform') {
        selected.push(fallbackDefault);
      }
    }
    writeCsv(assignedInput, selected);

    // Filter default app options
    var allowedDefaults = selected.slice();
    if (authority === 'platform_admin') {
      allowedDefaults = ['platform'];
    } else if (authority === 'app_user' && requiredApp) {
      allowedDefaults = allowedDefaults.filter(function (k) { return k === requiredApp; });
      if (!allowedDefaults.length) { allowedDefaults = [requiredApp]; }
    } else {
      allowedDefaults = allowedDefaults.filter(function (k) { return k !== 'platform'; });
    }
    Array.prototype.forEach.call(defaultAppSelect.options, function (opt) {
      opt.hidden = allowedDefaults.indexOf(String(opt.value || '').toLowerCase()) === -1;
    });
    if (allowedDefaults.indexOf(String(defaultAppSelect.value || '').toLowerCase()) === -1) {
      defaultAppSelect.value = allowedDefaults[0] || '';
    }

    // Show/hide permission app groups
    var assignedNow = selectedAssignedApps();
    form.querySelectorAll('[data-profile-group-app]').forEach(function (group) {
      var app = String(group.getAttribute('data-profile-group-app') || '').toLowerCase();
      group.classList.toggle('access2-hidden', assignedNow.indexOf(app) === -1);
    });

    setSummary('scope', assignedNow.length ? assignedNow.map(function (a) { return labelFor(appLabels, a); }).join(', ') : 'No apps');
    setFeedback('scope', messages.length ? [messages[0]] : []);
  }

  // ── Profiles validation ──────────────────────────────────────────────────
  function validateProfiles() {
    if (isDisplayAccount()) {
      setSummary('permissions', 'N/A');
      setFeedback('permissions', []);
      return;
    }
    var assigned    = selectedAssignedApps();
    var authority   = String(authoritySelect   ? authoritySelect.value   : 'app_user').toLowerCase();
    var interaction = String(interactionSelect ? interactionSelect.value : 'worker').toLowerCase();
    var defaultApp  = String(defaultAppSelect  ? defaultAppSelect.value  : '').toLowerCase();
    var selected    = selectedProfiles();
    var next = []; var removed = [];

    form.querySelectorAll('.access2-profile-token[data-value]').forEach(function (button) {
      var key           = String(button.getAttribute('data-value') || '').toLowerCase();
      var profileApp    = String(button.getAttribute('data-app') || '').toLowerCase();
      var profileDef    = String(button.getAttribute('data-default-app') || '').toLowerCase();
      var authScope     = String(button.getAttribute('data-authority-scope') || 'general').toLowerCase();
      var readonly      = String(button.getAttribute('data-readonly') || '0') === '1';
      var compatible    = true;

      if (assigned.indexOf(profileApp) === -1) { compatible = false; }
      if (authority === 'app_user' && authScope === 'admin_only') { compatible = false; }
      if (interaction === 'worker' && authScope === 'leader') { compatible = false; }
      if ((interaction === 'display' || interaction === 'read_only') && !readonly) { compatible = false; }
      if (profileDef && defaultApp && profileDef !== defaultApp) { compatible = false; }

      var isSelected = selected.indexOf(key) !== -1;
      button.disabled = !compatible;
      button.classList.toggle('is-disabled', !compatible);
      // Hide incompatible unselected tokens; only show them if the per-group toggle is active.
      var groupRow = form.querySelector('.a2-profile-row[data-profile-row-app="' + profileApp + '"]');
      var showUnavailable = groupRow ? groupRow.getAttribute('data-show-unavailable') === '1' : false;
      button.hidden = !compatible && !isSelected && !showUnavailable;
      button.classList.toggle('is-on', compatible && isSelected);
      button.setAttribute('aria-pressed', compatible && isSelected ? 'true' : 'false');

      if (compatible && isSelected) { next.push(key); }
      else if (isSelected) { removed.push(profileLabels[key] || key); }
    });

    // If upstream choices cleared the old profile set, repopulate Stage 4 from
    // the active workspace profile first, then from canonical role defaults.
    if (next.length === 0) {
      profileSeedFromUpstream().forEach(function (seedKey) {
        var seed = form.querySelector('.access2-profile-token[data-value="' + seedKey + '"]:not(.is-disabled)');
        if (!seed) { return; }
        if (next.indexOf(seedKey) === -1) {
          next.push(seedKey);
          seed.classList.add('is-on');
          seed.setAttribute('aria-pressed', 'true');
        }
      });
    }

    // Keep platform-admin mapping sane even when no explicit seed exists.
    if (authority === 'platform_admin' && next.length === 0) {
      var fallback = form.querySelector('.access2-profile-token[data-value="platform_administration"][data-app="platform"]:not(.is-disabled)')
        || form.querySelector('.access2-profile-token[data-app="platform"]:not(.is-disabled)');
      if (fallback) {
        var fallbackKey = String(fallback.getAttribute('data-value') || '').toLowerCase();
        if (fallbackKey !== '') {
          next.push(fallbackKey);
          fallback.classList.add('is-on');
          fallback.setAttribute('aria-pressed', 'true');
        }
      }
    }

    writeCsv(accessProfilesInput, next);
    setFeedback('permissions', removed.length
      ? [removed.join(', ') + ' cleared — not compatible with current authority / interaction / app.']
      : ['Profiles filtered to valid choices for current upstream selections.']);
    setSummary('permissions', next.length ? String(next.length) + ' profiles selected' : 'Choose profiles');

    // Update per-app "show unavailable" toggle rows and counts
    form.querySelectorAll('.a2-profile-row[data-profile-row-app]').forEach(function (groupRow) {
      var app = String(groupRow.getAttribute('data-profile-row-app') || '');
      var hiddenCount = 0;
      groupRow.querySelectorAll('.access2-profile-token').forEach(function (btn) {
        if (btn.hidden) { hiddenCount++; }
      });
      var toggleRow = form.querySelector('.a2-profile-unavailable-row[data-profile-unavailable-row="' + app + '"]');
      var countEl   = form.querySelector('.a2-unavailable-count[data-unavailable-count="' + app + '"]');
      if (toggleRow) { toggleRow.style.display = hiddenCount > 0 ? '' : 'none'; }
      if (countEl)   { countEl.textContent = hiddenCount > 0 ? i18n.profilesUnavailableCount.replace('{n}', String(hiddenCount)) : ''; }
    });
  }

  // ── Module levels from profiles ──────────────────────────────────────────
  function syncModulesFromProfiles() {
    if (isDisplayAccount()) { return; }
    var authority   = String(authoritySelect ? authoritySelect.value : 'app_user').toLowerCase();
    var assigned    = selectedAssignedApps();
    var interaction = String(interactionSelect ? interactionSelect.value : 'worker').toLowerCase();
    var selected    = selectedProfiles();
    var recommended = {};

    form.querySelectorAll('.access2-profile-token.is-on[data-modules]').forEach(function (btn) {
      normalizeCsv(btn.getAttribute('data-modules') || '').forEach(function (mk) { recommended[mk] = true; });
    });

    var visibility = []; var hints = [];
    form.querySelectorAll('.module-level-select[data-module]').forEach(function (sel) {
      var mk  = String(sel.getAttribute('data-module') || '').toLowerCase();
      var app = String(sel.getAttribute('data-app') || '').toLowerCase();
      var row = sel.closest('[data-module-row-app]');
      var grp = sel.closest('[data-module-group-app]');
      var here = assigned.indexOf(app) !== -1;
      var level = String(sel.value || 'none').toLowerCase();

      if (row)  { row.classList.toggle('access2-hidden', !here); }
      if (grp) {
        var anyVis = Array.prototype.some.call(grp.querySelectorAll('[data-module-row-app]'), function (r) {
          return !r.classList.contains('access2-hidden');
        });
        grp.classList.toggle('access2-hidden', !anyVis);
      }

      if (!here && level !== 'none') { sel.value = 'none'; sel.dataset.manual = '0'; level = 'none'; }
      if (here && authority === 'platform_admin' && String(sel.dataset.manual || '0') !== '1') {
        var manageOption = Array.prototype.some.call(sel.options, function (opt) {
          return String(opt.value || '').toLowerCase() === 'manage';
        });
        sel.value = manageOption ? 'manage' : 'view';
        level = String(sel.value || 'view').toLowerCase();
      }
      if (here && recommended[mk] && level === 'none' && String(sel.dataset.manual || '0') !== '1') { sel.value = 'view'; level = 'view'; }
      if ((interaction === 'read_only' || interaction === 'display') && ['work', 'approve', 'manage'].indexOf(level) !== -1) { sel.value = 'view'; level = 'view'; }

      if (level !== 'none') { visibility.push(mk); }
      hints.push(mk + ':' + level);
    });

    writeCsv(moduleVisibilityInput, visibility);
    writeCsv(moduleHintsInput, hints);
    setSummary('permissions', (selectedProfiles().length ? selectedProfiles().length + ' profiles' : 'No profiles') + ' · ' + visibility.length + ' modules active');
  }

  // ── Passive / display access ─────────────────────────────────────────────
  function syncPassiveAccess() {
    var assigned        = selectedAssignedApps();
    var readonlySelected = selectedReadonly();
    var displaySelected  = selectedDisplay();
    var keptReadonly = []; var keptDisplay = [];
    var views  = normalizeCsv(viewAccessInput  ? viewAccessInput.value  : '');
    var tables = normalizeCsv(tableAccessInput ? tableAccessInput.value : '');
    var charts = normalizeCsv(chartAccessInput ? chartAccessInput.value : '');
    var cards  = normalizeCsv(pluginCardsInput ? pluginCardsInput.value : '');
    var messages = [];

    form.querySelectorAll('.access2-readonly-profile[data-value]').forEach(function (btn) {
      var key = String(btn.getAttribute('data-value') || '').toLowerCase();
      var app = String(btn.getAttribute('data-app') || '').toLowerCase();
      var ok  = app === '' || assigned.indexOf(app) !== -1;
      btn.disabled = !ok; btn.classList.toggle('is-disabled', !ok);
      if (ok && readonlySelected.indexOf(key) !== -1) { keptReadonly.push(key); }
    });

    form.querySelectorAll('.access2-display-token[data-value]').forEach(function (btn) {
      var key       = String(btn.getAttribute('data-value') || '').toLowerCase();
      var app       = String(btn.getAttribute('data-app') || '').toLowerCase();
      var tokenType = String(btn.getAttribute('data-token-type') || '').toLowerCase();
      var tokenKey  = String(btn.getAttribute('data-token-key') || '').toLowerCase();
      var isDisplay = isDisplayAccount();
      // For tv_display, all tokens are compatible; for operators, app must be assigned
      var ok = isDisplay || app === '' || assigned.indexOf(app) !== -1;
      btn.disabled = !ok; btn.classList.toggle('is-disabled', !ok);
      if (!ok || displaySelected.indexOf(key) === -1) { return; }
      keptDisplay.push(key);
      if (tokenType === 'view'        && views.indexOf(tokenKey)  === -1) { views.push(tokenKey); }
      if (tokenType === 'table'       && tables.indexOf(tokenKey) === -1) { tables.push(tokenKey); }
      if (tokenType === 'chart'       && charts.indexOf(tokenKey) === -1) { charts.push(tokenKey); }
      if (tokenType === 'plugin_card' && cards.indexOf(tokenKey)  === -1) { cards.push(tokenKey); }
      if (tokenType === 'module') {
        var mSel = form.querySelector('.module-level-select[data-module="' + tokenKey + '"]');
        if (mSel && String(mSel.value || 'none').toLowerCase() === 'none') { mSel.value = 'view'; }
      }
    });

    var ap = selectedProfiles();
    keptReadonly.forEach(function (k) { if (ap.indexOf(k) === -1) { ap.push(k); } });
    writeCsv(readonlyProfilesInput, keptReadonly);
    writeCsv(displaySurfacesInput, keptDisplay);
    writeCsv(accessProfilesInput, ap);
    writeCsv(viewAccessInput, views);
    writeCsv(tableAccessInput, tables);
    writeCsv(chartAccessInput, charts);
    writeCsv(pluginCardsInput, cards);

    var passiveCount = keptReadonly.length + keptDisplay.length;
    setSummary('passive', isDisplayAccount()
      ? (keptDisplay.length ? keptDisplay.length + ' panels active' : 'No panels selected')
      : (passiveCount ? passiveCount + ' passive selections' : 'Optional'));
    setFeedback('passive', isDisplayAccount()
      ? ['Select which data panels to show on this display.']
      : ['Passive access stays view-only — no write, approve, or manage rights.']);
  }

  // ── Stage locks ──────────────────────────────────────────────────────────
  function updateStageLocks() {
    var assigned    = selectedAssignedApps();
    var profiles    = selectedProfiles();
    var interaction = String(interactionSelect ? interactionSelect.value : 'worker').toLowerCase();
    var display     = isDisplayAccount();
    var readiness = {
      workspace:   true,
      scope:       !display,
      permissions: !display && assigned.length > 0,
      cross_app:   !display && assigned.length > 0,
      passive:     display || profiles.length > 0 || interaction === 'read_only',
      preview:     true
    };
    form.querySelectorAll('.access2-stage[data-stage]').forEach(function (el) {
      var stage = String(el.getAttribute('data-stage') || '');
      if (readiness.hasOwnProperty(stage)) {
        el.classList.toggle('is-locked', !readiness[stage]);
      } else {
        el.classList.remove('is-locked');
      }
    });
  }

  // ── Preview update ───────────────────────────────────────────────────────
  function updatePreview() {
    var authority    = String(authoritySelect    ? authoritySelect.value    : '').toLowerCase();
    var accountClass = String(accountClassSelect ? accountClassSelect.value : '').toLowerCase();
    var dtVal        = String(dashboardTypeSelect ? dashboardTypeSelect.value : '').toLowerCase();
    var assigned     = selectedAssignedApps();
    var profiles     = selectedProfiles();
    var modulePairs  = normalizeCsv(moduleHintsInput ? moduleHintsInput.value : '');
    var interaction  = String(interactionSelect ? interactionSelect.value : '').toLowerCase();
    var focus        = String(focusSelect       ? focusSelect.value       : '').toLowerCase();
    var warnings     = [];
    var display      = authority === 'tv_display';

    setPreview('authority',      labelFor(accessAuthorities, authority));
    setPreview('account_class',  labelFor(accountClassOptions, accountClass));
    setPreview('workspace',      (dashboardTypeLabels[dtVal] || dtVal) + (defaultAppSelect ? ' · ' + labelFor(appLabels, String(defaultAppSelect.value || '')) : '') + (landingSelect ? ' · ' + String(landingSelect.value || '') : ''));
    setPreview('scope',          display ? 'N/A (Display)' : labelFor(controlScopeOptions, String(scopeSelect ? scopeSelect.value : '')));
    setPreview('apps',           display ? 'N/A' : (assigned.length ? assigned.map(function (a) { return labelFor(appLabels, a); }).join(', ') : ''));
    setPreview('interaction',    display ? 'N/A (Read-only kiosk)' : (labelFor(interactionProfiles, interaction) + (focus ? ' · ' + focus : '')));
    setPreview('profiles',       display ? 'N/A' : (profiles.length ? profiles.map(function (k) { return profileLabels[k] || k; }).join(', ') : ''));
    setPreview('cross_app',      display ? 'N/A' : (crossAccessInput ? (crossAccessInput.value || '') : ''));

    var dept    = form.querySelector('input[name="scope_department_code"]');
    var branch  = form.querySelector('input[name="scope_branch_code"]');
    var owner   = form.querySelector('select[name="scope_ownership_role"]');
    var constraintParts = [];
    if (dept  && dept.value.trim())  { constraintParts.push('Dept: ' + dept.value.trim()); }
    if (branch && branch.value.trim()) { constraintParts.push('Branch: ' + branch.value.trim()); }
    if (owner && owner.value)        { constraintParts.push('Role: ' + owner.value); }
    setPreview('constraints', constraintParts.join(', ') || 'None');

    setSummary('preview', 'Ready to review');

    if (!display && !assigned.length) { warnings.push(i18n.warnAssignedAppsEmpty); }
    if (!display && !profiles.length) { warnings.push(i18n.warnNoProfiles); }
    if (defaultAppSelect && !display && authority !== 'platform_admin' && assigned.indexOf(String(defaultAppSelect.value || '').toLowerCase()) === -1) {
      warnings.push(i18n.warnDefaultAppOutside);
    }
    if (!display && (interaction === 'read_only' || interaction === 'display')
        && modulePairs.some(function (p) { return /:(work|approve|manage)$/.test(p); })) {
      warnings.push(i18n.warnReadonlyHighLevels);
    }

    var warnBox = form.querySelector('[data-preview="warnings"]');
    if (warnBox) {
      warnBox.innerHTML = warnings.map(function (w) {
        return '<div class="access2-warning">' + w.replace(/[<>&"]/g, '') + '</div>';
      }).join('');
    }
  }

  // ── Normalize from top ───────────────────────────────────────────────────
  function normalizeFromTopSelection() {
    resetProfileManagedState();
    syncAuthorityFromWorkspaceProfile();
    syncIdentity();
    syncWorkspace();
    syncAssignedAppsFromWorkspaceProfile();

    refreshComposer();

    profileSeedFromUpstream().forEach(function (pk) {
      var b = form.querySelector('.access2-profile-token[data-value="' + pk + '"]:not(.is-disabled)');
      if (b) { b.classList.add('is-on'); b.setAttribute('aria-pressed', 'true'); }
    });
    refreshComposer();

    var first = form.querySelector('.access2-stage');
    if (first) { first.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
  }

  // ── Full cascade ─────────────────────────────────────────────────────────
  function refreshComposer() {
    syncAuthorityFromWorkspaceProfile();
    syncIdentity();
    syncWorkspace();
    syncOperationalProfile();
    filterInteractionOptions();
    filterScopeOptions();
    normalizeScopeVisibility();
    syncWorkspaceProfileOptions();
    syncAssignedAppsAndDefaultApp();
    reconcilePermissionUpstream();
    validateProfiles();
    syncModulesFromProfiles();
    syncPassiveAccess();
    syncCrossAppAuthority();
    updateStageLocks();
    updatePreview();
  }

  // ── Event listeners ───────────────────────────────────────────────────────
  form.querySelectorAll('.module-level-select').forEach(function (sel) {
    sel.addEventListener('change', function () { sel.dataset.manual = '1'; refreshComposer(); });
  });

  form.querySelectorAll('.a2-cross-level-select').forEach(function (sel) {
    sel.addEventListener('change', refreshComposer);
  });

  if (accountClassSelect) {
    accountClassSelect.addEventListener('change', function () {
      var classVal = String(accountClassSelect.value || '');
      var noteEl2 = form.querySelector('[data-scope-note="account_class"]');
      if (noteEl2) { noteEl2.textContent = i18n.noteClass[classVal] || ''; }
    });
  }

  // Constraints toggle
  var constraintsToggle = form.querySelector('[data-toggle-constraints="<?= $uid ?>"]');
  var constraintsBody   = form.querySelector('#constraints_body_<?= $uid ?>');
  var constraintsChevron = form.querySelector('.a2-constraints-chevron');
  if (constraintsToggle && constraintsBody) {
    constraintsToggle.addEventListener('click', function () {
      var open = constraintsBody.style.display !== 'none';
      constraintsBody.style.display = open ? 'none' : '';
      if (constraintsChevron) { constraintsChevron.textContent = open ? '▸' : '▾'; }
    });
  }

  form.addEventListener('click', function (event) {
    var target = event.target;
    if (!(target instanceof HTMLElement)) { return; }

    if (target.classList.contains('tg-btn') && !target.classList.contains('a2-cross-preset') && !target.classList.contains('a2-normalize-btn')) {
      setTimeout(refreshComposer, 0);
      return;
    }
    if (target.classList.contains('a2-cross-preset')) {
      var grants = normalizeCsv(target.getAttribute('data-preset-grants') || '');
      grants.forEach(function (grant) {
        var parts = grant.split(':');
        var sel = form.querySelector('.a2-cross-level-select[data-bundle="' + parts[0] + '"]');
        if (sel) { sel.value = parts[1] || 'view'; }
      });
      syncCrossAppAuthority();
      updatePreview();
      return;
    }
    if (target.id === 'normalize_btn_<?= $uid ?>' || target.classList.contains('a2-normalize-btn')) {
      normalizeFromTopSelection();
    }

    // Show/hide unavailable profile tokens per app group
    if (target.classList.contains('a2-show-unavailable-btn')) {
      var appGroup = String(target.getAttribute('data-profile-app') || '');
      var groupRow = form.querySelector('.a2-profile-row[data-profile-row-app="' + appGroup + '"]');
      if (groupRow) {
        var nowShowing = groupRow.getAttribute('data-show-unavailable') === '1';
        groupRow.setAttribute('data-show-unavailable', nowShowing ? '0' : '1');
        target.textContent = nowShowing ? i18n.profilesShowUnavailable : i18n.profilesHideUnavailable;
        validateProfiles();
      }
    }
  });

  ['change', 'input'].forEach(function (ev) {
    if (authoritySelect) {
      authoritySelect.addEventListener(ev, refreshComposer);
    }
    [interactionSelect, focusSelect, scopeSelect, defaultAppSelect, landingSelect, dashboardTypeSelect].forEach(function (node) {
      if (node) { node.addEventListener(ev, refreshComposer); }
    });
    if (workspaceProfileSelect) {
      workspaceProfileSelect.addEventListener(ev, function () {
        if (suppressProfileAutoApply) { return; }
        applyWorkspaceProfileDefaults();
      });
    }
  });

  form.addEventListener('submit', function (e) {
    syncCrossAppAuthority();
    refreshComposer();

    // Collect blocking errors before allowing POST
    var authority   = String(authoritySelect    ? authoritySelect.value    : '').toLowerCase();
    var display     = authority === 'tv_display';
    var assigned    = selectedAssignedApps();
    var interaction = String(interactionSelect ? interactionSelect.value : '').toLowerCase();
    var dtVal       = String(dashboardTypeSelect ? dashboardTypeSelect.value : '').toLowerCase();
    var landing     = String(landingSelect ? landingSelect.value : '/');
    var allowedLandings = landingByDashboard[dtVal] || ['/'];
    var blockers = [];

    if (!display && !assigned.length) { blockers.push(i18n.warnAssignedAppsEmpty); }
    if (defaultAppSelect && !display && authority !== 'platform_admin' && assigned.indexOf(String(defaultAppSelect.value || '').toLowerCase()) === -1) {
      blockers.push(i18n.warnDefaultAppOutside);
    }
    if (!display && allowedLandings.indexOf(landing) === -1) {
      blockers.push(i18n.warnLandingInvalid);
    }

    if (blockers.length) {
      e.preventDefault();
      var warnBox = form.querySelector('[data-preview="warnings"]');
      if (warnBox) {
        warnBox.innerHTML = '<strong>' + i18n.saveBlockedTitle.replace(/[<>&"]/g, '') + '</strong>' +
          blockers.map(function (w) {
            return '<div class="access2-warning access2-warning--blocker">' + w.replace(/[<>&"]/g, '') + '</div>';
          }).join('');
      }
      var stage7 = form.querySelector('[data-stage="preview"]');
      if (stage7) { stage7.open = true; stage7.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    }
  });

  suppressProfileAutoApply = true;
  refreshComposer();
  suppressProfileAutoApply = false;
})();
</script>

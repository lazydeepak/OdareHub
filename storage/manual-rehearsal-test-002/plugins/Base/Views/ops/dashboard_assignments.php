<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php

// ── INPUT NORMALIZATION ───────────────────────────────────────────────────────
$assignmentRows        = is_array($assignmentRows        ?? null) ? $assignmentRows        : [];
$accessRegistryRows    = is_array($accessRegistryRows    ?? null) ? $accessRegistryRows    : [];
$accessProfileRegistry = is_array($accessProfileRegistry ?? null) ? $accessProfileRegistry : [];
$assignmentUiConfig    = is_array($assignmentUiConfig    ?? null) ? $assignmentUiConfig    : [];
$roleDefaultsMap       = is_array($roleDefaultsMap       ?? null) ? $roleDefaultsMap       : [];
$filters               = is_array($filters               ?? null) ? $filters               : [];
$workspaceProfileCatalog = is_array($workspaceProfileCatalog ?? null) ? $workspaceProfileCatalog : [];
$currentUserId         = (int)($currentUserId ?? 0);
$flash                 = (string)($flash ?? '');
$error                 = (string)($error ?? '');

$accountClasses = is_array($assignmentUiConfig['accountClasses'] ?? null)
    ? $assignmentUiConfig['accountClasses']
    : [
        'platform_operations' => 'Platform Operations',
        'platform_security'   => 'Platform Security',
        'app_administration'  => 'App Administration',
        'app_user'            => 'App User',
    ];

$appOptions     = array_values((array)($assignmentUiConfig['appOptions'] ?? [
    'platform', 'manufacturing', 'accounting', 'hr', 'inventory', 'sales', 'sbaio',
]));
$rolePacksByApp = is_array($assignmentUiConfig['rolePacksByApp'] ?? null)
    ? $assignmentUiConfig['rolePacksByApp'] : [];
$dashboardLabels = is_array($assignmentUiConfig['dashboardLabels'] ?? null)
    ? $assignmentUiConfig['dashboardLabels'] : [];
$modeledSurfaceDefinitions = is_array($assignmentUiConfig['surfaceDefinitions'] ?? null)
    ? $assignmentUiConfig['surfaceDefinitions'] : [];

$allRolePackOptions = [];
foreach ($rolePacksByApp as $packs) {
    foreach ((array)$packs as $k => $v) {
        $allRolePackOptions[(string)$k] = (string)$v;
    }
}

$manufacturingProfiles = [];
foreach ($accessProfileRegistry as $key => $cfg) {
    if (!is_array($cfg)) {
        continue;
    }
    if (strtolower((string)($cfg['app'] ?? '')) === 'manufacturing') {
        $manufacturingProfiles[(string)$key] = $cfg;
    }
}

// ── HELPERS ───────────────────────────────────────────────────────────────────
$csvValues = static function (string $csv): array {
    $parts = preg_split('/\s*,\s*/', strtolower(trim($csv))) ?: [];
    return array_values(array_filter(array_map('trim', $parts)));
};

$appLabels = [
    'platform'      => 'Platform',
    'manufacturing' => 'Manufacturing',
    'accounting'    => 'Accounting',
    'hr'            => 'HR',
    'inventory'     => 'Inventory',
    'sales'         => 'Sales',
    'sbaio'         => 'SBAIO',
];
$appLabel = static fn (string $app): string =>
    $appLabels[strtolower(trim($app))] ?? trim(str_replace('_', ' ', ucwords($app, '_')));

$accountTypeLabels = [
    'platform_admin' => 'Platform Admin',
    'app_admin'      => 'App Admin',
    'app_user'       => 'App User',
];
$accountTypeLabel = static fn (string $t): string =>
    $accountTypeLabels[strtolower(trim($t))] ?? trim(str_replace('_', ' ', ucwords($t, '_')));

$surfaceLabelMap = [
    'governance_board'         => 'Governance Board',
    'security_posture'         => 'Security Posture',
    'platform_health'          => 'Platform Health',
    'stage_board'              => 'Stage Board',
    'approval_inbox'           => 'Approval Inbox',
    'route_registry'           => 'Route Registry',
    'acl_overrides'            => 'Access Exceptions',
    'throughput_trend'         => 'Throughput Trend',
    'plan_vs_actual'           => 'Plan vs Actual',
    'schema_sync'              => 'Schema Sync',
    'security_alerts'          => 'Security Alerts',
    'masters'                  => 'Master Data',
    'ops'                      => 'Operations',
    'admin'                    => 'Administration',
    'access_control_board'     => 'Access Control Board',
    'user_control_board'       => 'User Control Board',
    'platform_admin_dashboard' => 'Admin Overview (migrated)',
    'platform_setup'           => 'Platform Setup',
    'admin_tools'              => 'Admin Tools',
    'route_diagnostics'        => 'Route Diagnostics',
    'navigation_tree'          => 'Navigation Tree',
    'user_dashboard'           => 'User Dashboard Compatibility',
];

// Load Manufacturing surface labels from contribution service
if (class_exists('Apps\\Manufacturing\\Services\\HostSurfaceContributionService')) {
  $mfgSurfaceLabels = \Apps\Manufacturing\Services\HostSurfaceContributionService::getAccessBoardSurfaceLabels();
  foreach ($mfgSurfaceLabels as $key => $label) {
    $surfaceLabelMap[(string)$key] = (string)$label;
  }
}
$surfaceLabel = static function (string $t) use ($surfaceLabelMap): string {
  $key = strtolower(trim($t));
  if ($key === '') {
    return '';
  }

  if (isset($surfaceLabelMap[$key])) {
    return (string)$surfaceLabelMap[$key];
  }

  $parts = preg_split('/[_\-\.\s]+/', $key) ?: [];
  $words = [];
  foreach ($parts as $part) {
    $word = trim((string)$part);
    if ($word === '') {
      continue;
    }
    $words[] = match ($word) {
      'qc' => 'QC',
      'acl' => 'ACL',
      'sbaio' => 'SBAIO',
      'tv' => 'TV',
      default => ucfirst($word),
    };
  }

  return implode(' ', $words);
};

$profileLabelsFromCsv = static function (string $csv) use ($csvValues, $accessProfileRegistry, $surfaceLabel): array {
    $labels = [];
    foreach ($csvValues($csv) as $key) {
        $labels[] = (string)($accessProfileRegistry[$key]['label'] ?? $surfaceLabel($key));
    }
    return $labels;
};

$interactionProfileLabel = static function (array $row): string {
    $at = strtolower(trim((string)($row['authority_role'] ?? 'app_user')));
    $dt = strtolower(trim((string)($row['dashboard_type'] ?? '')));
    $pr = strtolower((string)($row['access_profiles'] ?? ''));
    if (in_array($at, ['platform_admin', 'app_admin'], true)) {
        return t('ops.access_control.profile.admin');
    }
    if (str_contains($pr, 'readonly') || str_contains($pr, 'observer')) {
        return t('ops.access_control.profile.read_only');
    }
    if (str_contains($dt, 'leader')) {
        return t('ops.access_control.profile.leader');
    }
    return t('ops.access_control.profile.worker');
};

$controlScopeForType = static function (string $type, string $token): string {
    if ($type === 'app') {
        return t('ops.access_control.scope.app');
    }
    if ($type === 'module') {
        return t('ops.access_control.scope.module');
    }
    if ($type === 'plugin_card') {
        return t('ops.access_control.scope.personal');
    }
    $tok = strtolower($token);
    if (str_contains($tok, 'governance') || str_contains($tok, 'security')
        || str_contains($tok, 'platform') || str_contains($tok, 'admin')) {
        return t('ops.access_control.scope.platform');
    }
    return t('ops.access_control.scope.shared');
};

$resolveScopeLabel = static function (array $row) use ($accountClasses): string {
    $cls = trim((string)($row['account_class'] ?? ''));
    if ($cls === '') {
    return t('ops.access_control.scope.needs_norm');
    }
    return (string)($accountClasses[$cls] ?? ucwords(str_replace('_', ' ', $cls)));
};

$resolveHomeSurface = static function (array $row): string {
    $mode    = strtolower(trim((string)($row['landing_mode'] ?? 'auto')));
    $landing = trim((string)($row['default_landing_page'] ?? ''));
    if ($mode === 'manual' && $landing !== '') {
        return $landing;
    }
    return t('ops.access_control.home.my_work');
};

$statusChipClass = static fn (string $s): string => match (strtolower(trim($s))) {
    'disabled' => 'status-chip status-chip-neutral',
    'pending'  => 'status-chip status-chip-warning',
    default    => 'status-chip status-chip-success',
};

$statusLabel = static fn (string $s): string => match (strtolower(trim($s))) {
    'disabled' => t('ops.access_control.status_label.disabled'),
    'pending'  => t('ops.access_control.status_label.pending'),
    default    => t('ops.access_control.status_label.active'),
};

// ── BUILD USER ASSIGNMENT ROWS ────────────────────────────────────────────────
$userAssignmentRows = [];
$surfaceRegistryMap = [];

foreach ($assignmentRows as $row) {
    $uid          = (int)($row['id'] ?? 0);
    $dname        = trim((string)($row['display_name'] ?? ''));
    $userLabel    = $dname !== '' ? $dname : (string)($row['email'] ?? 'User');
    $authLabel    = $accountTypeLabel((string)($row['authority_role'] ?? 'app_user'));
    $scopeLabel   = $resolveScopeLabel($row);
  $profileSource = trim((string)($row['selected_role_packs'] ?? ''));
  if ($profileSource === '') {
    $profileSource = (string)($row['access_profiles'] ?? '');
  }
  $profilesList = $profileLabelsFromCsv($profileSource);
    $assignedApps = $csvValues((string)($row['assigned_apps'] ?? ''));
    $uStatus      = strtolower(trim((string)($row['account_status'] ?? 'active')));
    $needsNorm    = str_contains(strtolower($scopeLabel), 'normalization') || $scopeLabel === '';

    // Build surface registry map for secondary sections
    $tokenBuckets = [
        'app'         => $assignedApps,
        'module'      => $csvValues((string)($row['module_visibility'] ?? '')),
        'view'        => $csvValues((string)($row['view_access'] ?? '')),
        'table'       => $csvValues((string)($row['table_access'] ?? '')),
        'chart'       => $csvValues((string)($row['chart_access'] ?? '')),
        'plugin_card' => $csvValues((string)($row['me_plugin_cards'] ?? '')),
    ];
    foreach ($tokenBuckets as $type => $tokens) {
        foreach ($tokens as $token) {
            $bk = $type . ':' . $token;
            if (!isset($surfaceRegistryMap[$bk])) {
                $surfaceRegistryMap[$bk] = [
                    'type'          => $type,
                    'key'           => $token,
                    'label'         => $type === 'app' ? $appLabel($token) : $surfaceLabel($token),
                    'governance_scope' => $controlScopeForType($type, $token),
                    'users'         => [],
                    'authorities'   => [],
                ];
            }
            $surfaceRegistryMap[$bk]['users'][$uid]              = $userLabel;
            $surfaceRegistryMap[$bk]['authorities'][$authLabel]  = $authLabel;
        }
    }

    $userAssignmentRows[] = [
        'id'                  => $uid,
        'email'               => (string)($row['email'] ?? ''),
        'display_name'        => $dname,
        'access_authority'    => $authLabel,
        'interaction_profile' => $interactionProfileLabel($row),
        'governance_scope'       => $scopeLabel,
      'workspace_profile_key' => strtolower(trim((string)($row['workspace_profile_key'] ?? ''))),
        'default_app'         => $appLabel((string)($row['default_app'] ?? '')),
        'assigned_apps'       => array_map($appLabel, $assignedApps),
        'permission_profiles' => $profilesList,
        'home_surface'        => $resolveHomeSurface($row),
        'status'              => $uStatus,
        'needs_normalization' => $needsNorm,
    ];
}

usort($userAssignmentRows, static fn ($a, $b) =>
    strcmp((string)($a['email'] ?? ''), (string)($b['email'] ?? '')));

// ── BUILD SURFACE SECTIONS ────────────────────────────────────────────────────
$coveredBuckets     = [];
$primarySurfaceRows = [];

foreach ($modeledSurfaceDefinitions as $surfaceKey => $def) {
    if (!is_array($def)) {
        continue;
    }
    $tt  = strtolower(trim((string)($def['token_type'] ?? '')));
    $tk  = strtolower(trim((string)($def['token_key'] ?? '')));
    $bk  = ($tt !== '' && $tk !== '') ? ($tt . ':' . $tk) : '';
    $mat = $bk !== '' ? (array)($surfaceRegistryMap[$bk] ?? []) : [];
    if ($bk !== '') {
        $coveredBuckets[$bk] = true;
    }
    $primarySurfaceRows[] = [
        'key'           => (string)$surfaceKey,
        'token_key'     => $tk,
        'label'         => (string)($def['label'] ?? $surfaceLabel((string)$surfaceKey)),
        'view_suite'    => (string)($def['view_suite'] ?? 'mixed'),
        'governance_scope' => (string)($def['governance_scope'] ?? 'shared'),
        'users'         => (array)($mat['users'] ?? []),
        'authorities'   => (array)($mat['authorities'] ?? []),
        'eligible_auth' => array_map($accountTypeLabel, (array)($def['access_authorities'] ?? [])),
        'profiles'      => array_map($surfaceLabel, (array)($def['permission_profiles'] ?? [])),
        'primary'       => !empty($def['primary']),
        'priority'      => (int)($def['priority'] ?? 999),
    ];
}

usort($primarySurfaceRows, static fn ($a, $b) =>
    $a['priority'] !== $b['priority']
        ? $a['priority'] <=> $b['priority']
        : strcmp((string)($a['label'] ?? ''), (string)($b['label'] ?? '')));

$compatRows = [];
foreach ($surfaceRegistryMap as $bk => $row) {
    if (isset($coveredBuckets[$bk])) {
        continue;
    }
    $compatRows[] = $row;
}
usort($compatRows, static fn ($a, $b) =>
    strcmp((string)($a['type'] ?? ''), (string)($b['type'] ?? ''))
        ?: strcmp((string)($a['label'] ?? ''), (string)($b['label'] ?? '')));

// ── BUILD PERMISSION PROFILE ROWS ─────────────────────────────────────────────
$profileRows           = [];
$materialAdminProfiles = [];
$matWriteTokens        = [
    'materials.admin', 'materials.master.manage', 'materials.planning.manage',
    'materials.orders.manage', 'materials.stock.adjust',
    'materials.capacity.manage', 'materials.cost.manage',
];

foreach ($accessProfileRegistry as $profileKey => $cfg) {
    if (!is_array($cfg)) {
        continue;
    }
    $perms      = (array)($cfg['permissions'] ?? []);
    $matWrite   = !empty(array_intersect($perms, $matWriteTokens));
    $profileLbl = (string)($cfg['label'] ?? $surfaceLabel((string)$profileKey));
    if ($matWrite) {
        $materialAdminProfiles[] = $profileLbl;
    }
    $profileRows[] = [
        'key'           => (string)$profileKey,
        'label'         => $profileLbl,
        'app'           => $appLabel((string)($cfg['app'] ?? 'shared')),
        'description'   => (string)($cfg['description'] ?? ''),
        'perm_count'    => count($perms),
        'has_mat_write' => $matWrite,
        'has_mat_read'  => in_array('materials.stock.view', $perms, true)
                         || in_array('materials.coverage.view', $perms, true),
        'view_access'   => array_map($surfaceLabel, (array)($cfg['view_access'] ?? [])),
        'table_access'  => array_map($surfaceLabel, (array)($cfg['table_access'] ?? [])),
    ];
}
usort($profileRows, static fn ($a, $b) => strcmp((string)($a['label'] ?? ''), (string)($b['label'] ?? '')));

// ── GOVERNANCE SUMMARY ────────────────────────────────────────────────────────
$govSummary = [
    'total'     => count($userAssignmentRows),
    'active'    => 0,
    'pending'   => 0,
    'disabled'  => 0,
    'needs_norm'=> 0,
];
foreach ($userAssignmentRows as $ur) {
    $s = (string)($ur['status'] ?? 'active');
    if ($s === 'active')  { $govSummary['active']++; }
    if ($s === 'pending') { $govSummary['pending']++; }
    if ($s === 'disabled'){ $govSummary['disabled']++; }
    if (!empty($ur['needs_normalization'])) { $govSummary['needs_norm']++; }
}

// ── FILTER STATE ──────────────────────────────────────────────────────────────
$searchFilter       = trim((string)($filters['search'] ?? ''));
$accountClassFilter = trim((string)($filters['account_class'] ?? ''));
$assignedAppFilter  = trim((string)($filters['assigned_app'] ?? ''));
$rolePackFilter     = trim((string)($filters['role_pack'] ?? ''));
$workspaceProfileFilter = strtolower(trim((string)($filters['workspace_profile'] ?? '')));
$statusFilter       = trim((string)($filters['status'] ?? 'all'));

$workspaceProfileOptions = [];
if ($workspaceProfileCatalog !== []) {
  foreach ($workspaceProfileCatalog as $profileKey => $profileDef) {
    $profileKey = strtolower(trim((string)$profileKey));
    if ($profileKey === '') {
      continue;
    }
    $profileName = trim((string)($profileDef['name'] ?? ''));
    $workspaceProfileOptions[$profileKey] = $profileName !== ''
      ? ($profileName . ' (' . $profileKey . ')')
      : $profileKey;
  }
} else {
  foreach ($assignmentRows as $row) {
    $profileKey = strtolower(trim((string)($row['workspace_profile_key'] ?? '')));
    if ($profileKey !== '') {
      $workspaceProfileOptions[$profileKey] = $profileKey;
    }
  }
}
ksort($workspaceProfileOptions);
?>

<style>
/* ── ACL Governance Board ───────────────────────────────────────────────────── */
.acg-header-copy { display:grid; gap:5px; max-width:880px; }

.acg-summary-grid {
  display:grid;
  gap:10px;
  grid-template-columns:repeat(auto-fit, minmax(150px, 1fr));
  margin-top:10px;
}
.acg-summary-card {
  border:1px solid var(--style-border-soft);
  border-radius:12px;
  padding:10px 12px;
  background:var(--style-subtle-bg);
  display:grid;
  gap:3px;
}
.acg-summary-card.is-warn {
  border-color:var(--tone-warning-border);
  background:var(--tone-warning-bg);
}
.acg-summary-label {
  font-size:11px;
  font-weight:700;
  letter-spacing:.06em;
  text-transform:uppercase;
  color:var(--muted);
}
.acg-summary-value { font-size:22px; font-weight:700; line-height:1.1; }

.acg-filter-row { display:flex; flex-wrap:wrap; gap:var(--control-gap); align-items:flex-end; margin-bottom:14px; }
.acg-filter-field { display:flex; flex-direction:column; gap:5px; flex:1 1 155px; min-width:min(155px,100%); }
.acg-filter-field-wide { flex:2 1 220px; min-width:min(220px,100%); }
.acg-filter-label { font-size:11px; font-weight:700; letter-spacing:.05em; text-transform:uppercase; color:var(--muted); }
.acg-filter-actions { display:flex; gap:var(--control-gap-tight); align-items:flex-end; flex-wrap:wrap; margin-left:auto; }

.acg-table { min-width:960px; }
.acg-row { cursor:pointer; }
.acg-row:hover, .acg-row:focus-within { background:var(--style-table-row-hover); }
.acg-row:focus { outline:2px solid var(--accent); outline-offset:-2px; }
.acg-cell-user    { min-width:13rem; }
.acg-cell-apps    { min-width:9rem; }
.acg-cell-profile { min-width:11rem; }
.acg-cell-action  { min-width:9rem; white-space:nowrap; }

.acg-chip-row { display:flex; flex-wrap:wrap; gap:4px; margin-top:4px; }
.acg-chip {
  border:1px solid var(--style-border-soft);
  border-radius:999px;
  padding:2px 8px;
  font-size:.7em;
  background:var(--style-subtle-bg);
  color:var(--text);
  white-space:nowrap;
}
.acg-chip-muted { border-color:transparent; background:transparent; color:var(--muted); font-size:.7em; }
.acg-norm-flag {
  display:inline-block;
  border:1px solid var(--tone-warning-border);
  background:var(--tone-warning-bg);
  color:var(--tone-warning-text);
  border-radius:999px;
  padding:1px 7px;
  font-size:.68em;
  font-weight:700;
  margin-left:4px;
  vertical-align:middle;
}

/* Catalog collapsed sections */
.acg-catalog {
  overflow:hidden;
  padding:0;
}
.acg-catalog > summary {
  cursor:pointer;
  list-style:none;
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:10px;
  padding:14px 16px;
}
.acg-catalog > summary::-webkit-details-marker { display:none; }
.acg-catalog > summary h3 { margin:0; font-size:16px; }
.acg-catalog-caret {
  color:var(--muted);
  font-size:14px;
  transition:transform .16s ease;
  flex-shrink:0;
}
.acg-catalog[open] > summary .acg-catalog-caret { transform:rotate(90deg); }
.acg-catalog-body { padding:0 16px 16px; }
.acg-catalog-head { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:10px; }
.acg-catalog-count {
  display:inline-flex;
  align-items:center;
  padding:2px 8px;
  border-radius:999px;
  border:1px solid var(--style-border-soft);
  background:var(--style-subtle-bg);
  font-size:.72em;
  font-weight:700;
  color:var(--muted);
}

.acg-canonical-badge {
  display:inline-flex;
  align-items:center;
  padding:1px 7px;
  border-radius:999px;
  font-size:.67em;
  font-weight:700;
  text-transform:uppercase;
  letter-spacing:.04em;
  border:1px solid var(--style-active-border);
  background:var(--style-active-bg);
  color:var(--text);
}
.acg-compat-badge {
  display:inline-flex;
  align-items:center;
  padding:1px 7px;
  border-radius:999px;
  font-size:.67em;
  font-weight:700;
  text-transform:uppercase;
  letter-spacing:.04em;
  border:1px solid var(--style-border-soft);
  background:var(--style-subtle-bg);
  color:var(--muted);
}
.acg-compat-row td { opacity:.78; }

.acg-policy-note {
  border:1px solid var(--tone-info-border);
  background:var(--tone-info-bg);
  color:var(--tone-info-text);
  border-radius:10px;
  padding:8px 12px;
  font-size:.84em;
  line-height:1.55;
  margin-bottom:10px;
}
.acg-mat-admin-flag {
  display:inline-block;
  border:1px solid var(--tone-danger-border);
  background:var(--tone-danger-bg);
  color:var(--tone-danger-text);
  border-radius:999px;
  padding:1px 7px;
  font-size:.68em;
  font-weight:700;
  margin-left:4px;
  vertical-align:middle;
}
.acg-mat-read-flag {
  display:inline-block;
  border:1px solid var(--tone-info-border);
  background:var(--tone-info-bg);
  color:var(--tone-info-text);
  border-radius:999px;
  padding:1px 7px;
  font-size:.68em;
  font-weight:700;
  margin-left:4px;
  vertical-align:middle;
}

.acg-compat-mismatch-note {
  border:1px dashed var(--style-border-soft);
  border-radius:10px;
  background:var(--style-subtle-bg);
  padding:8px 12px;
  font-size:.84em;
  color:var(--muted);
  margin-top:12px;
  display:flex;
  align-items:center;
  gap:10px;
  flex-wrap:wrap;
}
.acg-mismatch-count {
  font-size:.7em;
  font-weight:700;
  border:1px solid var(--tone-warning-border);
  background:var(--tone-warning-bg);
  color:var(--tone-warning-text);
  border-radius:999px;
  padding:2px 8px;
}

@media (max-width:720px) {
  .acg-summary-grid { grid-template-columns:1fr 1fr; }
  .acg-filter-row { flex-direction:column; }
  .acg-filter-field, .acg-filter-field-wide { flex:1 1 100%; min-width:0; }
  .acg-filter-actions { width:100%; margin-left:0; }
  .acg-filter-actions .btn { flex:1 1 120px; text-align:center; }
}

/* ── Mobile filter collapse ─────────────────────────────────────────────────
   Below 760px the filter row eats most of the first viewport. Wrap it in a
   collapsible panel; on desktop the toggle is hidden and the panel is forced
   open. */
.acg-filters-toggle { display:none; }
@media (max-width:760px) {
  .acg-filters-toggle {
    display:inline-flex;
    align-items:center;
    gap:6px;
    margin-bottom:10px;
    font-size:.88em;
  }
  .acg-filters-collapsible[hidden] { display:none; }
}

/* ── Mobile user-card layout ────────────────────────────────────────────────
   Below 760px the wide governance table is hard to scan. Convert each row
   into a stacked card with the primary action (Open Detail) hoisted to the
   top, and label every cell using its data-mobile-label attribute. */
@media (max-width:760px) {
  .acg-table {
    min-width:0;
    border:0;
  }
  .acg-table thead { display:none; }
  .acg-table,
  .acg-table tbody,
  .acg-table tr,
  .acg-table td {
    display:block;
    width:100%;
  }
  .acg-table tr.acg-row {
    margin:0 0 12px 0;
    padding:14px 14px 12px 14px;
    border:1px solid var(--style-border-soft);
    border-radius:12px;
    background:var(--style-card-bg);
    box-shadow: var(--style-surface-shadow);
  }
  .acg-table tr.acg-row:hover,
  .acg-table tr.acg-row:focus-within {
    background:var(--style-card-bg);
    border-color:var(--accent);
  }
  .acg-table td {
    padding:6px 0;
    border:0;
    min-width:0 !important;
  }
  .acg-table td[data-mobile-label]::before {
    content:attr(data-mobile-label);
    display:block;
    font-size:10px;
    font-weight:700;
    letter-spacing:.06em;
    text-transform:uppercase;
    color:var(--muted);
    margin-bottom:2px;
  }
  /* Hoist the Governance action to the top of the card. */
  .acg-table td.acg-cell-action {
    order:-1;
    margin:0 0 10px 0;
    padding:0;
  }
  .acg-table td.acg-cell-action::before { display:none; }
  .acg-table td.acg-cell-action .btn {
    display:block;
    width:100%;
    text-align:center;
  }
  .acg-table tr.acg-row {
    display:flex;
    flex-direction:column;
  }
}
</style>

<?php if ($flash !== ''): ?>
  <section class="card notice-ok"><?= e($flash) ?></section>
<?php endif; ?>
<?php if ($error !== ''): ?>
  <section class="card notice-err"><?= e($error) ?></section>
<?php endif; ?>

<!-- ── PAGE HEADER ─────────────────────────────────────────────────────────── -->
<section class="card">
  <div class="acg-header-copy">
    <div class="hero-kicker"><?= t('ops.access_control.kicker') ?></div>
    <div class="section-head u-style-fdf33f2304">
      <div class="ui-block">
        <h2 class="u-style-1169661891"><?= t('ops.access_control.title') ?></h2>
        <div class="muted u-style-55cec09fd9"><?= t('ops.access_control.subtitle') ?></div>
      </div>
    </div>
  </div>
    <div class="admin-action-row u-style-33fcd4c359">
    <a class="btn" href="/ops/platform-operations"><?= t('ops.platform_operations.title') ?></a>
    <button class="btn u-style-1c36546128" type="button" id="acg-toggle-sections"><?= t('ops.access_control.toggle_sections') ?></button>
  </div>
</section>

<!-- ── SUMMARY COUNTERS ───────────────────────────────────────────────────────── -->
<section class="card">
  <div class="acg-summary-grid">
    <div class="acg-summary-card">
      <div class="acg-summary-label"><?= t('ops.access_control.summary.total_users') ?></div>
      <div class="acg-summary-value"><?= number_format((int)$govSummary['total']) ?></div>
    </div>
    <div class="acg-summary-card">
      <div class="acg-summary-label"><?= t('ops.access_control.status_label.active') ?></div>
      <div class="acg-summary-value"><?= number_format((int)$govSummary['active']) ?></div>
    </div>
    <div class="acg-summary-card">
      <div class="acg-summary-label"><?= t('ops.access_control.status_label.pending') ?></div>
      <div class="acg-summary-value"><?= number_format((int)$govSummary['pending']) ?></div>
    </div>
    <div class="acg-summary-card">
      <div class="acg-summary-label"><?= t('ops.access_control.status_label.disabled') ?></div>
      <div class="acg-summary-value"><?= number_format((int)$govSummary['disabled']) ?></div>
    </div>
    <?php if ($govSummary['needs_norm'] > 0): ?>
      <div class="acg-summary-card is-warn">
        <div class="acg-summary-label"><?= t('ops.access_control.summary.needs_norm') ?></div>
        <div class="acg-summary-value"><?= number_format((int)$govSummary['needs_norm']) ?></div>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- ── USER ASSIGNMENTS (PRIMARY) ───────────────────────────────────────────── -->
<section class="card">
  <div class="section-head">
    <div class="ui-block">
      <h3 class="u-style-1169661891"><?= t('ops.access_control.section.user_assignments') ?></h3>
      <div class="muted u-style-8a50001ddd"><?= t('ops.access_control.section.user_assignments_subtitle') ?></div>
    </div>
  </div>

  <button class="btn acg-filters-toggle" type="button" id="acg-filters-toggle" aria-expanded="false" aria-controls="acg-filters-form">
    <span aria-hidden="true">▾</span> <?= t('ops.access_control.filters_toggle') ?>
  </button>
  <form method="get" action="/ops/access-control" class="acg-filter-row acg-filters-collapsible" id="acg-filters-form" hidden>
    <div class="acg-filter-field acg-filter-field-wide">
      <label class="acg-filter-label" for="acg-search"><?= t('common.search') ?></label>
      <input class="input" type="text" id="acg-search" name="q" value="<?= e($searchFilter) ?>" placeholder="<?= t('ops.access_control.filter.search_placeholder') ?>">
    </div>
    <div class="acg-filter-field">
      <label class="acg-filter-label" for="acg-authority"><?= t('ops.access_control.filter.access_authority') ?></label>
      <select id="acg-authority" name="account_class">
        <option value=""><?= t('ops.access_control.filter.all_authorities') ?></option>
        <?php foreach ($accountClasses as $classKey => $classLbl): ?>
          <option value="<?= e((string)$classKey) ?>"<?= strtolower($accountClassFilter) === strtolower((string)$classKey) ? ' selected' : '' ?>><?= e((string)$classLbl) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="acg-filter-field">
      <label class="acg-filter-label" for="acg-app"><?= t('ops.access_control.filter.assigned_app') ?></label>
      <select id="acg-app" name="assigned_app">
        <option value=""><?= t('ops.access_control.filter.all_apps') ?></option>
        <?php foreach ($appOptions as $app): ?>
          <option value="<?= e((string)$app) ?>"<?= strtolower($assignedAppFilter) === strtolower((string)$app) ? ' selected' : '' ?>><?= e($appLabel((string)$app)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="acg-filter-field">
      <label class="acg-filter-label" for="acg-profile"><?= t('ops.access_control.filter.permission_profile') ?></label>
      <select id="acg-profile" name="role_pack">
        <option value=""><?= t('ops.access_control.filter.all_profiles') ?></option>
        <?php foreach ($allRolePackOptions as $packKey => $packLbl): ?>
          <option value="<?= e((string)$packKey) ?>"<?= strtolower($rolePackFilter) === strtolower((string)$packKey) ? ' selected' : '' ?>><?= e((string)$packLbl) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="acg-filter-field">
      <label class="acg-filter-label" for="acg-status"><?= t('common.status') ?></label>
      <select id="acg-status" name="status">
        <option value="all"<?= $statusFilter === 'all' ? ' selected' : '' ?>><?= t('ops.access_control.filter.all_statuses') ?></option>
        <option value="active"<?= $statusFilter === 'active' ? ' selected' : '' ?>><?= t('ops.access_control.status_label.active') ?></option>
        <option value="pending"<?= $statusFilter === 'pending' ? ' selected' : '' ?>><?= t('ops.access_control.status_label.pending') ?></option>
        <option value="disabled"<?= $statusFilter === 'disabled' ? ' selected' : '' ?>><?= t('ops.access_control.status_label.disabled') ?></option>
      </select>
    </div>
    <div class="acg-filter-field">
      <label class="acg-filter-label" for="acg-workspace-profile"><?= t('ops.access_control.workspace_profile.col_key') ?></label>
      <select id="acg-workspace-profile" name="workspace_profile">
        <option value=""><?= t('ops.access_control.filter.all_profiles') ?></option>
        <?php foreach ($workspaceProfileOptions as $wpKey => $wpLabel): ?>
          <option value="<?= e((string)$wpKey) ?>"<?= $workspaceProfileFilter === (string)$wpKey ? ' selected' : '' ?>><?= e((string)$wpLabel) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="acg-filter-actions">
      <button class="btn" type="submit"><?= t('common.filter') ?></button>
      <a class="btn" href="/ops/access-control"><?= t('common.clear') ?></a>
    </div>
  </form>

  <div class="table-wrap">
    <table class="acg-table">
      <thead>
        <tr>
          <th class="acg-cell-user"><?= t('ops.access_control.col.user') ?></th>
          <th><?= t('ops.access_control.col.access_authority') ?></th>
          <th class="acg-cell-apps"><?= t('ops.access_control.col.assigned_apps') ?></th>
          <th class="acg-cell-profile"><?= t('ops.access_control.col.permission_profiles') ?></th>
          <th><?= t('ops.access_control.col.default_app_home') ?></th>
          <th><?= t('ops.access_control.col.effective_access') ?></th>
          <th class="acg-cell-action"><?= t('ops.access_control.col.governance') ?></th>
        </tr>
      </thead>
      <tbody id="acg-user-tbody">
      <?php foreach ($userAssignmentRows as $ur): ?>
        <?php if (trim($searchFilter ?? '') !== '' && empty($userAssignmentRows)): ?>
          <tr><td colspan="7" class="muted u-style-e698a016b6"><?= t('ops.access_control.no_users') ?></td></tr>
        <?php endif; ?>

        <?php
          $uid       = (int)$ur['id'];
          $detailUrl = '/ops/access-control/detail?user_id=' . $uid . '&full=1';
          $isMe      = $uid === $currentUserId;
        ?>
        <tr class="acg-row table-link-row"
            data-href="<?= e($detailUrl) ?>"
            tabindex="0"
            role="link"
            aria-label="Open governance detail for <?= e((string)$ur['email']) ?>">

          <td class="acg-cell-user" data-mobile-label="<?= e(t('ops.access_control.col.user')) ?>">
            <a class="table-link-row-anchor" href="<?= e($detailUrl) ?>">
              <?= e((string)$ur['email']) ?>
              <?php if ($isMe): ?>
                <span class="table-link-row-meta"><?= t('ops.access_control.badge.you') ?></span>
              <?php endif; ?>
            </a>
            <?php if ((string)$ur['display_name'] !== ''): ?>
              <div class="muted u-style-8700987962"><?= e((string)$ur['display_name']) ?></div>
            <?php endif; ?>
            <div class="muted u-style-ba3d0719f9">
              <?= e((string)$ur['governance_scope']) ?>
              <?php if (!empty($ur['needs_normalization'])): ?>
                <span class="acg-norm-flag" title="<?= t('ops.access_control.badge.needs_norm') ?>"><?= t('ops.access_control.badge.needs_norm') ?></span>
              <?php endif; ?>
            </div>
          </td>

          <td data-mobile-label="<?= e(t('ops.access_control.col.access_authority')) ?>">
            <div class="u-style-eed0f8fb89"><?= e((string)$ur['access_authority']) ?></div>
            <div class="muted u-style-3995822e95"><?= e((string)$ur['interaction_profile']) ?></div>
          </td>

          <td class="acg-cell-apps" data-mobile-label="<?= e(t('ops.access_control.col.assigned_apps')) ?>">
            <?php if (!empty($ur['assigned_apps'])): ?>
              <div class="acg-chip-row">
                <?php foreach (array_slice((array)$ur['assigned_apps'], 0, 3) as $appName): ?>
                  <span class="acg-chip"><?= e((string)$appName) ?></span>
                <?php endforeach; ?>
                <?php if (count((array)$ur['assigned_apps']) > 3): ?>
                  <span class="acg-chip-muted">+<?= count((array)$ur['assigned_apps']) - 3 ?> more</span>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <span class="muted u-style-0772d0db65">—</span>
            <?php endif; ?>
          </td>

          <td class="acg-cell-profile" data-mobile-label="<?= e(t('ops.access_control.col.permission_profiles')) ?>">
            <?php if (!empty($ur['permission_profiles'])): ?>
              <div class="acg-chip-row">
                <?php foreach (array_slice((array)$ur['permission_profiles'], 0, 2) as $profLbl): ?>
                  <span class="acg-chip"><?= e((string)$profLbl) ?></span>
                <?php endforeach; ?>
                <?php if (count((array)$ur['permission_profiles']) > 2): ?>
                  <span class="acg-chip-muted">+<?= count((array)$ur['permission_profiles']) - 2 ?> more</span>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <span class="muted u-style-0772d0db65"><?= t('ops.access_control.no_profile') ?></span>
            <?php endif; ?>
            <?php if (trim((string)($ur['workspace_profile_key'] ?? '')) !== ''): ?>
              <?php
                $rowProfileKey = (string)$ur['workspace_profile_key'];
                $rowProfileId = (int)($workspaceProfileCatalog[$rowProfileKey]['id'] ?? 0);
                $rowProfileUrl = $rowProfileId > 0
                  ? ('/ops/workspace-profiles/detail?id=' . $rowProfileId)
                  : ('/ops/access-control?workspace_profile=' . rawurlencode($rowProfileKey));
              ?>
              <div class="u-style-8ae4a3b0dd">
                <a href="<?= e($rowProfileUrl) ?>" onclick="event.stopPropagation()">
                  <?= e($rowProfileKey) ?>
                </a>
              </div>
            <?php endif; ?>
          </td>

          <td data-mobile-label="<?= e(t('ops.access_control.col.default_app_home')) ?>">
            <div class="u-style-37b73dac14"><strong><?= e((string)$ur['default_app']) ?></strong></div>
            <div class="muted u-style-3995822e95"><?= e((string)$ur['home_surface']) ?></div>
          </td>

          <td data-mobile-label="<?= e(t('ops.access_control.col.effective_access')) ?>">
            <span class="<?= e($statusChipClass((string)$ur['status'])) ?>">
              <?= e($statusLabel((string)$ur['status'])) ?>
            </span>
          </td>

          <td class="acg-cell-action">
            <a class="btn" href="<?= e($detailUrl) ?>"><?= t('ops.access_control.open_detail') ?></a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($userAssignmentRows)): ?>
        <tr><td colspan="7" class="muted"><?= t('ops.access_control.no_users') ?></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<!-- ── SURFACE REGISTRY (COLLAPSED) ─────────────────────────────────────────── -->
<details class="card acg-catalog">
  <summary>
    <div class="u-style-015a5e7fbe">
      <h3><?= t('ops.access_control.section.surface_registry') ?></h3>
      <span class="acg-catalog-count"><?= count($primarySurfaceRows) ?> <?= t('ops.access_control.count.canonical') ?></span>
      <?php if (!empty($compatRows)): ?>
        <span class="acg-mismatch-count"><?= count($compatRows) ?> <?= t('ops.access_control.count.legacy') ?></span>
      <?php endif; ?>
    </div>
    <span class="acg-catalog-caret">▶</span>
  </summary>
  <div class="acg-catalog-body">
    <div class="acg-catalog-head">
      <strong><?= t('ops.access_control.canonical_surfaces_heading') ?></strong>
      <span class="acg-canonical-badge"><?= t('ops.access_control.badge.canonical') ?></span>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th><?= t('ops.access_control.col.surface') ?></th>
            <th><?= t('ops.access_control.col.type') ?></th>
            <th><?= t('ops.access_control.col.governance_scope') ?></th>
            <th><?= t('ops.access_control.col.users') ?></th>
            <th><?= t('ops.access_control.col.access_authorities') ?></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($primarySurfaceRows as $sr): ?>
          <tr>
            <td>
              <div class="u-style-eed0f8fb89"><?= e((string)$sr['label']) ?></div>
              <?php if (!empty($sr['token_key'])): ?>
                <div class="muted u-style-ba3d0719f9"><?= t('ops.access_control.diagnostic_token') ?> <?= e((string)$sr['token_key']) ?></div>
              <?php endif; ?>
              <?php if (!empty($sr['primary'])): ?>
                <span class="acg-canonical-badge u-style-96ad6099e2"><?= t('ops.access_control.badge.canonical') ?></span>
              <?php endif; ?>
            </td>
            <td><?= e((string)$sr['view_suite']) ?></td>
            <td><?= e((string)$sr['governance_scope']) ?></td>
            <td><?= e((string)count((array)$sr['users'])) ?></td>
            <td>
              <div class="acg-chip-row">
                <?php foreach (array_slice(array_values((array)(!empty($sr['authorities']) ? $sr['authorities'] : $sr['eligible_auth'])), 0, 3) as $authLbl): ?>
                  <span class="acg-chip"><?= e((string)$authLbl) ?></span>
                <?php endforeach; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if (!empty($compatRows)): ?>
      <div class="acg-compat-mismatch-note">
        <span class="acg-mismatch-count"><?= count($compatRows) ?> <?= t('ops.access_control.badge.canonical') ?> <?= t('ops.access_control.count.items') ?></span>
        <span><?= t('ops.access_control.compat_artifacts_note') ?></span>
      </div>
      <div class="table-wrap u-style-d2c171b18b">
        <table>
          <thead>
            <tr>
              <th>Surface</th>
              <th>Type</th>
              <th>Control Scope</th>
              <th>Users</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($compatRows as $cr): ?>
            <tr class="acg-compat-row">
              <td>
                <div class="u-style-eed0f8fb89"><?= e((string)$cr['label']) ?></div>
                <div class="muted u-style-ba3d0719f9"><?= e((string)$cr['key']) ?></div>
                <span class="acg-compat-badge"><?= t('ops.access_control.badge.legacy') ?></span>
              </td>
              <td><?= e((string)$cr['type']) ?></td>
              <td><?= e((string)$cr['governance_scope']) ?></td>
              <td><?= e((string)count((array)$cr['users'])) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</details>

<!-- ── VIEW DEFINITIONS & MATERIAL POLICY (COLLAPSED) ───────────────────────── -->
<details class="card acg-catalog">
  <summary>
    <div class="u-style-015a5e7fbe">
      <h3><?= t('ops.access_control.section.view_suite_defs') ?></h3>
      <span class="acg-catalog-count"><?= count($primarySurfaceRows) ?> <?= t('ops.access_control.count.surfaces') ?></span>
    </div>
    <span class="acg-catalog-caret">▶</span>
  </summary>
  <div class="acg-catalog-body">
    <div class="acg-policy-note">
      <?= t('ops.access_control.material_policy_text') ?>
      <?php if (!empty($materialAdminProfiles)): ?>

        <?= t('ops.access_control.material_profiles_prefix') ?>
        <?php foreach ($materialAdminProfiles as $mpLbl): ?>
          <span class="acg-mat-admin-flag"><?= e((string)$mpLbl) ?></span>
        <?php endforeach; ?>
        <?= t('ops.access_control.material_profiles_suffix') ?>
      <?php endif; ?>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th><?= t('ops.access_control.col.surface') ?></th>
            <th><?= t('ops.access_control.col.view_suite') ?></th>
            <th><?= t('ops.access_control.col.interaction_profile') ?></th>
            <th><?= t('ops.access_control.col.access_authority') ?></th>
            <th><?= t('ops.access_control.col.governance_scope') ?></th>
            <th><?= t('ops.access_control.col.permission_profiles') ?></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($primarySurfaceRows as $dr): ?>
          <tr>
            <td>
              <div class="u-style-eed0f8fb89"><?= e((string)$dr['label']) ?></div>
              <?php if (!empty($dr['token_key'])): ?>
                <div class="muted u-style-ba3d0719f9"><?= t('ops.access_control.diagnostic_token') ?> <?= e((string)$dr['token_key']) ?></div>
              <?php endif; ?>
            </td>
            <td><?= e((string)$dr['view_suite']) ?></td>
            <td>
              <?php $iProfiles = (array)($dr['modeled_interaction_profiles'] ?? []); ?>
              <?php if (!empty($iProfiles)): ?>
                <div class="acg-chip-row">
                  <?php foreach (array_slice($iProfiles, 0, 3) as $ip): ?>
                    <span class="acg-chip"><?= e((string)$ip) ?></span>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <span class="muted u-style-0772d0db65">—</span>
              <?php endif; ?>
            </td>
            <td>
              <?php $authList = array_values((array)(!empty($dr['authorities']) ? $dr['authorities'] : $dr['eligible_auth'])); ?>
              <div class="acg-chip-row">
                <?php foreach (array_slice($authList, 0, 3) as $al): ?>
                  <span class="acg-chip"><?= e((string)$al) ?></span>
                <?php endforeach; ?>
              </div>
            </td>
            <td><?= e((string)$dr['governance_scope']) ?></td>
            <td>
              <?php $pList = (array)($dr['profiles'] ?? []); ?>
              <?php if (!empty($pList)): ?>
                <div class="acg-chip-row">
                  <?php foreach (array_slice($pList, 0, 3) as $pl): ?>
                    <span class="acg-chip"><?= e((string)$pl) ?></span>
                  <?php endforeach; ?>
                  <?php if (count($pList) > 3): ?>
                    <span class="acg-chip-muted">+<?= count($pList) - 3 ?> more</span>
                  <?php endif; ?>
                </div>
              <?php else: ?>
                <span class="muted u-style-0772d0db65"><?= t('ops.access_control.badge.legacy') ?></span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</details>

<!-- ── PERMISSION PROFILES (COLLAPSED) ──────────────────────────────────────── -->
<details class="card acg-catalog">
  <summary>
    <div class="u-style-015a5e7fbe">
      <h3><?= t('ops.access_control.section.permission_profiles') ?></h3>
      <span class="acg-catalog-count"><?= count($profileRows) ?> <?= t('ops.access_control.count.profiles') ?></span>
    </div>
    <span class="acg-catalog-caret">▶</span>
  </summary>
  <div class="acg-catalog-body">
    <div class="acg-policy-note">
      <?= t('ops.access_control.perm_profile_policy') ?>
      Profiles marked
      <span class="acg-mat-admin-flag"><?= t('ops.access_control.badge.mat_write') ?></span> must only be assigned
      to App Admin or Platform Admin accounts.
      Profiles marked <span class="acg-mat-read-flag"><?= t('ops.access_control.badge.mat_read') ?></span> are safe
      for general users.
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th><?= t('ops.access_control.col.profile') ?></th>
            <th><?= t('ops.access_control.col.app') ?></th>
            <th><?= t('ops.access_control.col.description') ?></th>
            <th><?= t('ops.access_control.col.permissions') ?></th>
            <th><?= t('ops.access_control.col.surface_coverage') ?></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($profileRows as $pr): ?>
          <tr>
            <td>
              <div class="u-style-eed0f8fb89">
                <?= e((string)$pr['label']) ?>
                <?php if (!empty($pr['has_mat_write'])): ?>
                  <span class="acg-mat-admin-flag"><?= t('ops.access_control.badge.mat_write') ?></span>
                <?php elseif (!empty($pr['has_mat_read'])): ?>
                  <span class="acg-mat-read-flag"><?= t('ops.access_control.badge.mat_read') ?></span>
                <?php endif; ?>
              </div>
              <div class="muted u-style-ba3d0719f9"><?= e((string)$pr['key']) ?></div>
            </td>
            <td><?= e($appLabel((string)$pr['app'])) ?></td>
            <td class="u-style-c56b943310"><?= e((string)$pr['description']) ?></td>
            <td><?= (int)$pr['perm_count'] ?></td>
            <td>
              <div class="acg-chip-row">
                <?php foreach (array_slice((array)$pr['view_access'], 0, 2) as $vl): ?>
                  <span class="acg-chip"><?= t('ops.access_control.chip.board_prefix') ?> <?= e((string)$vl) ?></span>
                <?php endforeach; ?>
                <?php foreach (array_slice((array)$pr['table_access'], 0, 2) as $tl): ?>
                  <span class="acg-chip"><?= t('ops.access_control.chip.table_prefix') ?> <?= e((string)$tl) ?></span>
                <?php endforeach; ?>
                <?php if (empty($pr['view_access']) && empty($pr['table_access'])): ?>
                  <span class="muted u-style-0772d0db65">—</span>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($profileRows)): ?>
          <tr><td colspan="5" class="muted"><?= t('ops.access_control.no_permission_profiles') ?></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</details>

<!-- ── ADVANCED / LEGACY (COLLAPSED) ────────────────────────────────────────── -->
<details class="card acg-catalog">
  <summary>
    <div class="u-style-015a5e7fbe">
      <h3><?= t('ops.access_control.section.compat_diagnostics') ?></h3>
      <?php if (!empty($accessRegistryRows)): ?>
        <span class="acg-catalog-count"><?= count($accessRegistryRows) ?> <?= t('ops.access_control.count.items') ?></span>
      <?php endif; ?>
    </div>
    <span class="acg-catalog-caret">▶</span>
  </summary>
  <div class="acg-catalog-body">
    <div class="muted u-style-29de16e03d">
      <?= t('ops.access_control.compat_desc') ?>
    </div>

    <form class="u-style-05bb6b325d" method="post" action="/ops/access-control/normalize">
      <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
      <button class="btn" type="submit"><?= t('ops.access_control.normalize_btn') ?></button>
    </form>

    <?php if (!empty($manufacturingProfiles)): ?>
      <details class="u-style-f7d6465cc0">
        <summary class="u-style-951a35e543">
          <?= t('ops.access_control.mfg_compat_catalog') ?>
          <span class="acg-compat-badge u-style-391ef1246f"><?= count($manufacturingProfiles) ?> <?= t('ops.access_control.count.packs') ?></span>
        </summary>
        <div class="muted u-style-3dc36f6d90"><?= t('ops.access_control.compat_bundles_note') ?></div>
        <div class="table-wrap u-style-8a77e5a311">
          <table>
            <thead>
              <tr>
                <th><?= t('ops.access_control.col.profile') ?></th>
                <th><?= t('ops.access_control.col.description') ?></th>
                <th><?= t('ops.access_control.col.default_home_surface') ?></th>
                <th><?= t('ops.access_control.col.modules') ?></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($manufacturingProfiles as $profileKey => $profileCfg): ?>
              <tr>
                <td><strong><?= e((string)$profileKey) ?></strong></td>
                <td class="u-style-0772d0db65"><?= e((string)($profileCfg['description'] ?? '')) ?></td>
                <td><?= e((string)($profileCfg['default_dashboard_type'] ?? 'my_work')) ?></td>
                <td><?= e(implode(', ', array_map($surfaceLabel, (array)($profileCfg['module_visibility'] ?? [])))) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </details>
    <?php endif; ?>

    <?php if (!empty($accessRegistryRows)): ?>
      <h4 class="u-style-58130f2437"><?= t('ops.access_control.section.gov_coverage') ?></h4>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th><?= t('ops.access_control.col.governance_item') ?></th>
              <th><?= t('ops.access_control.col.type') ?></th>
              <th><?= t('ops.access_control.col.coverage') ?></th>
              <th><?= t('ops.access_control.col.detail') ?></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($accessRegistryRows as $item): ?>
            <tr class="acg-compat-row">
              <td>
                <div class="u-style-eed0f8fb89"><?= e((string)($item['label'] ?? 'Item')) ?></div>
                <div class="muted u-style-ba3d0719f9"><?= e((string)($item['key'] ?? '')) ?></div>
              </td>
              <td><span class="mapping-label"><?= e((string)($item['type_label'] ?? 'Item')) ?></span></td>
              <td>
                <div class="ui-block"><?= (int)($item['assigned_users_count'] ?? 0) ?> <?= t('ops.access_control.count.users_suffix') ?></div>
                <div class="acg-chip-row">
                  <?php foreach (array_slice((array)($item['role_preview'] ?? []), 0, 3) as $rl): ?>
                    <span class="acg-chip"><?= e((string)$rl) ?></span>
                  <?php endforeach; ?>
                </div>
              </td>
              <td><a class="btn" href="<?= e((string)($item['detail_url'] ?? '/ops/access-control')) ?>"><?= t('ops.access_control.open_detail_btn') ?></a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="muted u-style-0772d0db65"><?= t('ops.access_control.no_legacy') ?></div>
    <?php endif; ?>
  </div>
</details>


<script>
(function () {
  // Mobile filter collapse: toggle button shows/hides filter form below 760px.
  // Above 760px the toggle is hidden via CSS and the form is forced visible.
  var filtersForm   = document.getElementById('acg-filters-form');
  var filtersToggle = document.getElementById('acg-filters-toggle');
  function applyFiltersViewport() {
    if (!filtersForm || !filtersToggle) { return; }
    var isMobile = window.matchMedia('(max-width:760px)').matches;
    if (isMobile) {
      // Collapsed by default on mobile — unless user has filters applied.
      var qs = window.location.search;
      var hasFilters = /[?&](q|account_class|assigned_app|role_pack|status)=[^&]+/.test(qs);
      filtersForm.hidden = !hasFilters;
      filtersToggle.setAttribute('aria-expanded', hasFilters ? 'true' : 'false');
    } else {
      filtersForm.hidden = false;
      filtersToggle.setAttribute('aria-expanded', 'true');
    }
  }
  filtersToggle?.addEventListener('click', function () {
    if (!filtersForm) { return; }
    var nowOpen = filtersForm.hidden;
    filtersForm.hidden = !nowOpen;
    filtersToggle.setAttribute('aria-expanded', nowOpen ? 'true' : 'false');
  });
  window.addEventListener('resize', applyFiltersViewport);
  applyFiltersViewport();

  // Toggle all catalog sections
  document.getElementById('acg-toggle-sections')?.addEventListener('click', function() {
    const sections = document.querySelectorAll('.acg-catalog');
    const isOpen = sections[0]?.open ?? false;
    sections.forEach(s => s.open = !isOpen);
  });

  // Search empty-state: neutral on idle
  const searchInput = document.getElementById('acg-search');
  const tbody = document.getElementById('acg-user-tbody');
  function updateEmptyState() {
    if (searchInput && tbody && !tbody.querySelector('tr')) {
      const q = searchInput.value.trim();
      const msg = tbody.querySelector('td[colspan="7"]');
      if (msg && q === '') {
        msg.textContent = 'All users shown. Start typing to filter.';
        msg.style.fontStyle = 'italic';
        msg.style.color = 'var(--muted)';
      }
    }
  }
  searchInput?.addEventListener('input', updateEmptyState);

  // Row click → open access detail page
  document.querySelectorAll('tr.acg-row[data-href]').forEach(function (row) {
    var url = String(row.getAttribute('data-href') || '').trim();
    if (!url) { return; }

    row.addEventListener('click', function (ev) {
      if (ev.target && ev.target.closest('a, button, input, select, textarea, form')) {
        return;
      }
      window.location.assign(url);
    });

    row.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter' || ev.key === ' ') {
        ev.preventDefault();
        window.location.assign(url);
      }
    });
  });
})();
</script>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

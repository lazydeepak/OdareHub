<?php
require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php';

if (!function_exists('e')) {
  function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}

$csrf = \App\Core\Auth::csrfToken();
$tt = static function (string $key): string {
  return t($key);
};

// Package list (safe even if autoload misses, but you already have it)
$pmFile = APP_ROOT . '/app/Core/PackageManager.php';
if (!class_exists(\App\Core\PackageManager::class) && is_file($pmFile)) {
  require_once $pmFile;
}
$pkgEnabled = class_exists(\App\Core\PackageManager::class);
$packages = $pkgEnabled ? \App\Core\PackageManager::listPackages() : [];
$currentDbName = trim((string)($currentDbName ?? ''));
$activePluginCount = (int)($activePluginCount ?? 0);
$operationalModuleCount = (int)($operationalModuleCount ?? 0);
$appSuites = is_array($appSuites ?? null) ? $appSuites : [];
$appSuiteWarning = trim((string)($appSuiteWarning ?? ''));
$appsBoardBase = trim((string)($appsBoardBase ?? '/admin/apps')) ?: '/admin/apps';
$architecture = is_array($architecture ?? null) ? $architecture : [];
$archSummary = (array)($architecture['summary'] ?? []);
$strictMode = (bool)($archSummary['strict_mode'] ?? false);
$devToolsEnabled = function_exists('app_dev_tools_enabled') ? app_dev_tools_enabled() : false;
$appStatusChipClass = static function (string $status): string {
  return match ($status) {
    'active', 'enabled' => 'status-chip status-chip-success',
    'inactive', 'disabled', 'upgrade_pending', 'warning' => 'status-chip status-chip-warning',
    'broken', 'error' => 'status-chip status-chip-danger',
    'installed', 'uploaded', 'uninstalled', 'not-installed', 'not installed' => 'status-chip status-chip-neutral',
    default => 'status-chip status-chip-info',
  };
};
$appStatusLabel = static function (string $status): string {
  $normalized = trim(str_replace(['_', '-'], ' ', $status));
  return $normalized !== '' ? $normalized : 'unknown';
};
$appSectionId = static function (string $value): string {
  $normalized = strtolower((string)preg_replace('/[^a-z0-9]+/i', '-', $value));
  $normalized = trim($normalized, '-');
  return $normalized !== '' ? $normalized : 'section';
};
$assignmentRows = is_array($assignmentRows ?? null) ? $assignmentRows : [];
$targetProfiles = is_array($targetRoles ?? null) ? $targetRoles : [];
$studioApps = is_array($studioApps ?? null) ? $studioApps : [];
$mergedApps = is_array($mergedApps ?? null) ? $mergedApps : [];
$uiLocale = (string)(session_id() !== '' ? ($_SESSION['locale'] ?? 'en') : 'en');
$studioTr = static function (string $key) use ($uiLocale): string {
  $dict = [
    'en' => [
      'studio_section_title' => 'Studio Registry Bridge',
      'studio_section_subtitle' => 'Read-only reflection from storage/appstudio/registry.json with controlled activation.',
      'studio_apps' => 'Studio Apps',
      'merged_apps' => 'Merged Apps',
      'source' => 'Source',
      'route' => 'Route',
      'snapshot' => 'Snapshot',
      'version' => 'Version',
      'installed_at' => 'Installed',
      'activate' => 'Activate',
      'deactivate' => 'Deactivate',
      'none' => 'No Studio apps registered yet.',
      'status' => 'Status',
    ],
    'ja' => [
      'studio_section_title' => 'Studioレジストリ連携',
      'studio_section_subtitle' => 'storage/appstudio/registry.json からの読み取り反映（有効化は制御付き）。',
      'studio_apps' => 'Studioアプリ',
      'merged_apps' => 'マージ済みアプリ',
      'source' => 'ソース',
      'route' => 'ルート',
      'snapshot' => 'Snapshot',
      'version' => 'バージョン',
      'installed_at' => '登録日時',
      'activate' => '有効化',
      'deactivate' => '無効化',
      'none' => '登録されたStudioアプリはありません。',
      'status' => 'ステータス',
    ],
    'ne' => [
      'studio_section_title' => 'Studio रजिस्ट्री ब्रिज',
      'studio_section_subtitle' => 'storage/appstudio/registry.json बाट पढ्ने प्रतिविम्ब, नियन्त्रण गरिएको सक्रियता सहित।',
      'studio_apps' => 'Studio एपहरू',
      'merged_apps' => 'मर्ज भएका एपहरू',
      'source' => 'स्रोत',
      'route' => 'मार्ग',
      'snapshot' => 'Snapshot',
      'version' => 'संस्करण',
      'installed_at' => 'स्थापित',
      'activate' => 'सक्षम',
      'deactivate' => 'अक्षम',
      'none' => 'अहिलेसम्म कुनै Studio एप दर्ता गरिएको छैन।',
      'status' => 'स्थिति',
    ],
  ];
  return (string)($dict[$uiLocale][$key] ?? $dict['en'][$key] ?? $key);
};
$operationalProfileLabel = static function (string $profile): string {
  $normalized = strtolower(trim($profile));
  return match ($normalized) {
    'platform_admin' => 'Platform Admin',
    'app_admin' => 'App Admin',
    'my_work' => 'Home',
    'production_leader' => 'Production Leader',
    'assembly_leader' => 'Assembly Leader',
    'qc_leader' => 'QC Leader',
    'dispatch_leader' => 'Dispatch Leader',
    default => $profile,
  };
};
$pluginSummaryCounts = ['all' => 0, 'active' => 0, 'inactive' => 0, 'not_installed' => 0, 'warnings' => 0, 'errors' => 0];
?>

<style>
  .admin-apps-page {
    --admin-apps-surface: color-mix(in srgb, var(--color-surface) 94%, white 6%);
    --admin-apps-surface-soft: color-mix(in srgb, var(--style-subtle-bg) 86%, var(--color-surface) 14%);
    --admin-apps-border: color-mix(in srgb, var(--style-border-soft) 82%, var(--text) 18%);
    --admin-apps-accent: color-mix(in srgb, var(--color-primary, #4f7cff) 78%, var(--text) 22%);
    --admin-apps-accent-soft: color-mix(in srgb, var(--color-primary, #4f7cff) 13%, transparent);
    display:grid;
    gap:16px;
    max-width:1180px;
    margin:0 auto;
    padding:22px clamp(12px, 2vw, 24px) 40px;
  }
  .admin-apps-page > .card,
  .admin-apps-page > details.card {
    margin:0;
    border:1px solid var(--admin-apps-border);
    border-left-width:1px;
    border-bottom-width:1px;
    border-radius:14px;
    background:var(--admin-apps-surface);
    box-shadow:0 14px 34px rgba(15, 23, 42, .08);
    transform:none;
  }
  .admin-apps-page > .card:hover,
  .admin-apps-page > details.card:hover {
    border-color:var(--admin-apps-border);
    box-shadow:0 16px 38px rgba(15, 23, 42, .1);
    transform:none;
  }
  .admin-apps-page .card::before,
  .admin-apps-page .card::after,
  .admin-apps-page .dashboard-link-card::before,
  .admin-apps-page .dashboard-link-card::after,
  .admin-apps-page .hero-meta-card::before,
  .admin-apps-page .hero-meta-card::after {
    opacity:.34;
  }
  .admin-apps-page h2,
  .admin-apps-page h3 {
    margin:0;
    color:var(--text);
    letter-spacing:0;
  }
  .admin-apps-page .muted {
    color:color-mix(in srgb, var(--muted) 72%, var(--text) 28%);
  }
  .admin-apps-page .pill {
    border-color:var(--admin-apps-border);
    background:var(--admin-apps-surface-soft);
    color:color-mix(in srgb, var(--text) 78%, var(--muted) 22%);
    font-weight:650;
  }
  .apps-admin-hero {
    display:grid;
    grid-template-columns:minmax(0, 1fr) minmax(280px, 520px);
    gap:22px;
    align-items:end;
    padding:26px;
  }
  .apps-hero-copy {
    display:grid;
    gap:8px;
    min-width:0;
  }
  .apps-hero-eyebrow {
    display:inline-flex;
    width:max-content;
    align-items:center;
    border:1px solid var(--admin-apps-border);
    border-radius:999px;
    padding:5px 10px;
    background:var(--admin-apps-accent-soft);
    color:var(--admin-apps-accent);
    font-size:12px;
    font-weight:750;
  }
  .apps-admin-hero h2 {
    font-size:clamp(26px, 4vw, 42px);
    line-height:1;
    font-weight:820;
  }
  .apps-hero-subtitle {
    max-width:58ch;
    font-size:15px;
    line-height:1.55;
  }
  .apps-admin-hero .hero-meta {
    min-width:0;
    grid-template-columns:repeat(2, minmax(0, 1fr));
  }
  .apps-admin-hero .hero-meta-card {
    min-height:104px;
    padding:14px;
    border-color:var(--admin-apps-border);
    border-radius:12px;
    background:var(--admin-apps-surface-soft);
    box-shadow:none;
  }
  .apps-admin-hero .hero-meta-card:hover {
    border-color:var(--admin-apps-accent);
    box-shadow:0 10px 22px rgba(15, 23, 42, .08);
  }
  .apps-admin-hero .hero-meta-value {
    margin-top:8px;
    color:var(--text);
    font-size:32px;
    line-height:1;
    font-weight:850;
  }
  .apps-workspace-card {
    padding:20px;
  }
  .apps-workspace-head {
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:18px;
    margin-bottom:16px;
  }
  .apps-workspace-head .muted {
    max-width:72ch;
    line-height:1.5;
  }
  .apps-primary-actions {
    grid-template-columns:repeat(3, minmax(0, 1fr));
    gap:14px;
  }
  .apps-primary-actions .dashboard-link-card {
    min-height:132px;
    display:grid;
    align-content:space-between;
    gap:18px;
    border-color:var(--admin-apps-border);
    border-radius:12px;
    background:var(--admin-apps-surface-soft);
    box-shadow:none;
    text-decoration:none;
  }
  .apps-primary-actions .dashboard-link-card:hover {
    border-color:var(--admin-apps-accent);
    box-shadow:0 12px 28px rgba(15, 23, 42, .1);
  }
  .apps-primary-actions .dashboard-link-top strong {
    color:var(--text);
    font-size:15px;
  }
  .apps-link-kicker {
    color:var(--admin-apps-accent);
    font-size:12px;
    font-weight:750;
  }
  .apps-architecture-card {
    border-left:4px solid var(--admin-apps-accent) !important;
  }
  .apps-architecture-card > summary {
    padding:18px 20px;
  }
  .apps-architecture-card .apps-summary-row {
    align-items:center;
  }
  .apps-comms-card {
    display:flex;
    flex-direction:row;
    align-items:center;
    justify-content:space-between;
    gap:16px;
    padding:20px;
  }
  .apps-section { border: 1px solid var(--style-border-soft); border-radius:14px; background: var(--style-subtle-bg); overflow:hidden; scroll-margin-top:96px; }
  .apps-section > summary { cursor:pointer; list-style:none; padding:14px 16px; display:flex; align-items:flex-start; justify-content:space-between; gap:12px; }
  .apps-section > summary::-webkit-details-marker { display:none; }
  .apps-section-body { padding:0 16px 16px; }
  .apps-summary-row { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
  .apps-toolbar { display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-top:12px; }
  .apps-toolbar input, .apps-toolbar select { min-width:180px; }
  .apps-inline-details { margin-top:6px; }
  .apps-inline-details summary { cursor:pointer; color: var(--text); font-size:.82em; }
  .apps-plugin-row[data-hidden-by-filter="1"] { display:none; }
  .apps-section-summary { display:grid; gap:8px; min-width:0; flex:1 1 auto; }
  .apps-section-summary-head { display:flex; align-items:flex-start; gap:10px; min-width:0; }
  .apps-section-indicator { display:inline-flex; align-items:center; justify-content:center; width:18px; min-width:18px; color: var(--text); font-size:14px; line-height:1; padding-top:2px; }
  .apps-section-indicator::before { content:'▸'; }
  .apps-section[open] > summary .apps-section-indicator::before { content:'▾'; }
  .apps-section-titlecopy { min-width:0; }
  .apps-section-titlecopy strong { display:block; }
  .apps-section-right { display:grid; gap:8px; justify-items:end; text-align:right; flex:0 0 auto; }
  .apps-section-action-hints { display:flex; gap:8px; flex-wrap:wrap; }
  .apps-section-action-hint { display:inline-flex; align-items:center; border: 1px solid var(--style-border-soft); border-radius:999px; padding:5px 9px; font-size:12px; color: var(--text); background: var(--style-subtle-bg); white-space:nowrap; }
  .apps-section-action-hint[data-tone="warning"] { color: var(--color-warning-text); border-color: var(--color-warning-border); background: var(--color-warning-bg); }
  .apps-section-action-hint[data-tone="danger"] { color: var(--color-danger-text); border-color: var(--color-danger-border); background: var(--color-danger-bg); }
  .apps-module-table { min-width:1180px; }
  .apps-module-col-module { width:24%; }
  .apps-module-col-summary { width:38%; }
  .apps-module-col-actions { width:24%; }
  .apps-module-table .table-summary-cell { min-width:24rem; }
  .apps-module-table .table-actions-cell { min-width:20rem; }
  .apps-status-cell { width:1%; white-space:nowrap; }
  .apps-bundle-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(320px,1fr)); gap:14px; margin-top:14px; }
  .apps-bundle-card { margin:0; border-color:var(--style-border-soft); background:var(--style-subtle-bg); }
  .apps-bundle-card[data-tone="warning"] { border-color:var(--color-warning-border); background:var(--color-warning-bg); }
  .apps-bundle-card[data-tone="danger"] { border-color:var(--color-danger-border); background:var(--color-danger-bg); }
  .apps-bundle-card[data-tone="success"] { border-color:var(--color-success-border); background:var(--color-success-bg); }
  .apps-module-table td:nth-child(2),
  .apps-module-table td:nth-child(3) { white-space:nowrap; }
  @media (max-width: 720px) {
    .apps-module-table.table-mobile-cards tbody td[data-label="Summary"] .table-summary-meta-row {
      grid-template-columns:1fr;
      gap:4px;
    }
    .apps-module-table.table-mobile-cards tbody td[data-label="Module"] .muted {
      display:block;
      margin-top:4px;
    }
  }
  @media (max-width: 900px) {
    .apps-admin-hero {
      grid-template-columns:1fr;
    }
    .apps-primary-actions {
      grid-template-columns:1fr;
    }
    .apps-workspace-head,
    .apps-comms-card {
      flex-direction:column;
      align-items:stretch;
    }
    .apps-section > summary { flex-direction:column; }
    .apps-section-right { width:100%; justify-items:start; text-align:left; }
  }
  @media (max-width: 760px) {
    .admin-apps-page {
      padding-bottom: calc(96px + var(--safe-area-bottom));
    }
    .apps-admin-hero,
    .apps-workspace-card,
    .apps-comms-card {
      padding:16px;
    }
    .apps-admin-hero .hero-meta {
      grid-template-columns:1fr;
    }
    .admin-apps-page .apps-bundle-grid {
      grid-template-columns: minmax(0, 1fr);
    }
  }
</style>

<div class="admin-apps-page">

<?php if (!empty($_GET['pkg_ok'])): ?>
  <div class="card"><div class="muted"><?= e(t('admin.apps.package_uploaded')) ?>: <strong><?= e((string)$_GET['pkg_ok']) ?></strong></div></div>
<?php endif; ?>

<?php if (!empty($_GET['bundle_ok'])): ?>
  <div class="card">
    <div class="muted"><?= e(t('admin.apps.bundle_action_complete')) ?>: <strong><?= e((string)$_GET['bundle_ok']) ?></strong></div>
  </div>
<?php endif; ?>

<?php if (!empty($_GET['bundle_err'])): ?>
  <div class="card">
    <div class="muted u-style-002a0ee665"><?= e(t('admin.apps.bundle_action_failed')) ?>: <strong><?= e((string)$_GET['bundle_err']) ?></strong></div>
  </div>
<?php endif; ?>

<?php if (!empty($_GET['plugin_err'])): ?>
  <div class="card">
    <div class="muted u-style-002a0ee665"><?= e(t('admin.apps.module_action_failed')) ?>: <strong><?= e((string)$_GET['plugin_err']) ?></strong></div>
  </div>
<?php endif; ?>

<?php if ($appSuiteWarning !== ''): ?>
  <div class="card">
    <div class="muted u-style-6bd9ba116c"><?= e(t('admin.apps.bundle_summary_warning')) ?>: <strong><?= e($appSuiteWarning) ?></strong></div>
  </div>
<?php endif; ?>

<?php if (!empty($_GET['pkg_applied'])): ?>
  <div class="card">
    <div class="muted">
      <?= e(t('admin.apps.package_applied_to')) ?>: <strong><?= e((string)$_GET['pkg_applied']) ?></strong>.
      <?= e(t('admin.apps.backup_zip_saved')) ?> <code>/storage/plugin_backups</code>.
    </div>
  </div>
<?php endif; ?>

<?php if (isset($_GET['updated'])): ?>
  <div class="card">
    <div class="muted">
      <?= e(t('admin.apps.pending_migrations_executed')) ?> <strong><?= (int)($_GET['updated'] ?? 0) ?></strong>.
    </div>
  </div>
<?php endif; ?>

<?php if (!empty($_GET['pkg_sync'])): ?>
  <div class="card">
    <div class="muted">
      <?= e(t('admin.apps.package_sync_finished')) ?>: <?= e(t('admin.apps.applied')) ?> <strong><?= (int)($_GET['pkg_applied_count'] ?? 0) ?></strong>,
      <?= e(t('admin.apps.skipped_active')) ?> <strong><?= (int)($_GET['pkg_skipped_active'] ?? 0) ?></strong>,
      <?= e(t('admin.apps.skipped_invalid')) ?> <strong><?= (int)($_GET['pkg_skipped_invalid'] ?? 0) ?></strong>,
      <?= e(t('admin.apps.apply_errors')) ?> <strong><?= (int)($_GET['pkg_apply_errors'] ?? 0) ?></strong>,
      <?= e(t('admin.apps.migrations_updated')) ?> <strong><?= (int)($_GET['updated'] ?? 0) ?></strong>.
    </div>
  </div>
<?php endif; ?>

<?php if ($architecture): ?>
  <details class="card apps-section apps-architecture-card" <?= ((int)($archSummary['warnings'] ?? 0) > 0 || (int)($archSummary['errors'] ?? 0) > 0) ? 'open' : '' ?>>
    <summary>
      <div class="ui-block">
        <strong><?= e(t('admin.apps.architecture_validation')) ?></strong>
        <div class="muted u-style-37b73dac14">
          <?= $strictMode ? e(t('admin.apps.strict_mode_enabled')) : e(t('admin.apps.strict_mode_disabled')) ?>
        </div>
      </div>
      <div class="apps-summary-row">
        <span class="pill"><?= e(t('admin.apps.warnings')) ?>: <?= (int)($archSummary['warnings'] ?? 0) ?></span>
        <span class="pill"><?= e(t('admin.apps.errors')) ?>: <?= (int)($archSummary['errors'] ?? 0) ?></span>
        <span class="pill"><?= e(t('admin.apps.strict')) ?>: <?= $strictMode ? 'ON' : 'OFF' ?></span>
      </div>
    </summary>
    <div class="apps-section-body">
    <div class="row u-style-e9c3b08865">
      <div class="ui-block">
        <?php if ($strictMode): ?>
          <div class="u-style-fe7b4979fe">
            <span class="pill u-style-3bba001fa8">STRICT MODE ACTIVE</span>
          </div>
        <?php endif; ?>
      </div>
      <div class="row u-style-4725bb7117">
        <span class="pill">Modules: <?= (int)($archSummary['plugins'] ?? 0) ?></span>
        <span class="pill"> <?= e($tt('base.warnings_label')) ?> <?= (int)($archSummary['warnings'] ?? 0) ?></span>
        <span class="pill"> <?= e($tt('base.errors_label')) ?> <?= (int)($archSummary['errors'] ?? 0) ?></span>
        <span class="pill">Missing Metadata: <?= (int)($archSummary['missing_metadata'] ?? 0) ?></span>
        <span class="pill">Strict Mode: <?= $strictMode ? 'ON' : 'OFF' ?></span>
      </div>
    </div>
    </div>
  </details>
<?php endif; ?>

<div class="card apps-admin-hero">
  <div class="apps-hero-copy">
    <span class="apps-hero-eyebrow">Platform workspace</span>
    <h2 class="u-style-df671843ff"><?= e(t('dashboard.admin_tools')) ?></h2>
    <div class="muted apps-hero-subtitle"><?= e(t('dashboard.admin_tools_subtitle')) ?></div>
  </div>
  <div class="hero-meta u-style-56f4356299">
    <div class="hero-meta-card">
      <div class="muted"> <?= e($tt('base.active_modules_message')) ?> </div>
      <div class="hero-meta-value"><?= $activePluginCount ?></div>
    </div>
    <div class="hero-meta-card">
      <div class="muted"><?= e(t('dashboard.operational_modules')) ?></div>
      <div class="hero-meta-value"><?= $operationalModuleCount ?></div>
    </div>
  </div>
</div>

<div class="card apps-workspace-card">
  <div class="apps-workspace-head">
    <div class="ui-block">
      <h3 class="u-style-759e41d83d"><?= e(t('dashboard.admin_tools')) ?></h3>
      <div class="muted">
        Central workspace for system-level configuration, structure management, routing, and application packages.
      </div>
    </div>
  </div>
  <div class="dashboard-grid apps-primary-actions">
    <a class="dashboard-link-card" href="/admin/base">
      <div class="dashboard-link-top">
        <strong><?= e(t('common.base')) ?></strong>
      </div>
      <div class="muted"><?= e(t('dashboard.base_desc')) ?></div>
      <span class="apps-link-kicker">Open workspace</span>
    </a>
    <a class="dashboard-link-card" href="#apps-manager">
      <div class="dashboard-link-top">
        <strong><?= e(t('common.apps_manager')) ?></strong>
      </div>
      <div class="muted"><?= e(t('dashboard.apps_manager_desc')) ?></div>
      <span class="apps-link-kicker">Review modules</span>
    </a>
    <a class="dashboard-link-card" href="/admin/routes">
      <div class="dashboard-link-top">
        <strong><?= e(t('common.routes')) ?></strong>
      </div>
      <div class="muted"><?= e(t('dashboard.open_routes_manager')) ?></div>
      <span class="apps-link-kicker">Inspect routes</span>
    </a>
  </div>
</div>

<div class="card apps-comms-card" id="adminComms">
  <div class="section-head">
    <div class="ui-block">
      <h3 class="u-style-1169661891"><?= e(t('admin.apps.message_guide_center')) ?></h3>
      <div class="muted u-style-fe7b4979fe"><?= e(t('admin.apps.message_guide_center_desc')) ?></div>
    </div>
  </div>
  <div class="u-style-6af3c80b99">
    <a class="btn u-style-44c26a8e8e" href="/ops/notifications"><?= e(t('admin.apps.open_notification_center')) ?></a>
  </div>
</div>

<div class="card" id="apps-manager">
  <h3 class="u-style-759e41d83d"><?= e(t('common.apps_manager')) ?></h3>
  <div class="muted">
    Install, activate, deactivate, repair, and package business apps and child modules.
    <strong>Purge</strong> deletes DB tables/data and is blocked if depended by active modules.
    ZIP packages can be applied only when the target module is inactive (a ZIP backup is created before replace).
  </div>
  <form method="post" action="<?= e($appsBoardBase) ?>/action" style="margin-top:10px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="name" value="*">
    <button class="btn ok" name="action" value="update_all" type="submit"
      onclick="return confirm('Run pending SQL migrations for all installed modules?');">
      Run Pending SQL (All Installed Modules)
    </button>
    <button class="btn" name="action" value="apply_and_update_all" type="submit"
      onclick="return confirm('Apply all eligible uploaded packages, then run pending SQL migrations for installed modules?');">
      Apply Packages + Run SQL (One Click)
    </button>
    <span class="muted"><?= e(t('admin.apps.sync_hint_after_db_reset')) ?></span>
  </form>
</div>

<div class="card" id="studio-registry-bridge">
  <h3 class="u-style-759e41d83d"><?= e($studioTr('studio_section_title')) ?></h3>
  <div class="muted"><?= e($studioTr('studio_section_subtitle')) ?></div>
  <div class="row u-style-567eded8a2">
    <span class="pill"><?= e($studioTr('studio_apps')) ?>: <?= count($studioApps) ?></span>
    <span class="pill"><?= e($studioTr('merged_apps')) ?>: <?= count($mergedApps) ?></span>
  </div>

  <?php if ($studioApps === []): ?>
    <div class="muted u-style-d2c171b18b"><?= e($studioTr('none')) ?></div>
  <?php else: ?>
    <div class="table-wrap u-style-56f4356299">
      <table>
        <thead>
          <tr>
            <th><?= e(t('common.app')) ?></th>
            <th><?= e($studioTr('source')) ?></th>
            <th><?= e($studioTr('status')) ?></th>
            <th><?= e($studioTr('route')) ?></th>
            <th><?= e($studioTr('snapshot')) ?></th>
            <th><?= e($studioTr('version')) ?></th>
            <th><?= e($studioTr('installed_at')) ?></th>
            <th><?= e(t('common.actions')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($studioApps as $studioApp): ?>
            <?php if (!is_array($studioApp)) { continue; } ?>
            <?php
              $studioStatus = strtolower(trim((string)($studioApp['status'] ?? 'disabled')));
              $studioAppKey = trim((string)($studioApp['app_key'] ?? ''));
              $studioRoute = trim((string)($studioApp['route_path'] ?? ''));
            ?>
            <tr>
              <td>
                <strong><?= e((string)($studioApp['display_name'] ?? $studioAppKey)) ?></strong>
                <div class="muted u-style-3995822e95"><code><?= e($studioAppKey) ?></code></div>
              </td>
              <td><?= e((string)($studioApp['source'] ?? 'studio')) ?></td>
              <td><span class="<?= e($appStatusChipClass($studioStatus)) ?>"><?= e($appStatusLabel($studioStatus)) ?></span></td>
              <td>
                <?php if ($studioRoute !== ''): ?>
                  <a class="btn" href="<?= e($studioRoute) ?>"><?= e($studioRoute) ?></a>
                <?php else: ?>
                  <span class="muted">-</span>
                <?php endif; ?>
              </td>
              <td><code><?= e((string)($studioApp['snapshot_id'] ?? '')) ?></code></td>
              <td><?= e((string)($studioApp['version'] ?? '')) ?></td>
              <td><?= e((string)($studioApp['installed_at'] ?? '')) ?></td>
              <td>
                <form method="post" action="<?= e($appsBoardBase) ?>/action" style="display:inline-flex;gap:6px;flex-wrap:wrap;align-items:center">
                  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="app_source" value="studio">
                  <input type="hidden" name="app_key" value="<?= e($studioAppKey) ?>">
                  <?php if ($studioStatus === 'enabled'): ?>
                    <button class="btn" name="action" value="studio_disable" type="submit"><?= e($studioTr('deactivate')) ?></button>
                  <?php else: ?>
                    <button class="btn ok" name="action" value="studio_enable" type="submit"><?= e($studioTr('activate')) ?></button>
                  <?php endif; ?>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if ($mergedApps !== []): ?>
    <details class="apps-inline-details u-style-56f4356299">
      <summary><?= e($studioTr('merged_apps')) ?></summary>
      <div class="table-wrap u-style-8a77e5a311">
        <table>
          <thead>
            <tr>
              <th><?= e(t('common.app')) ?></th>
              <th><?= e($studioTr('source')) ?></th>
              <th><?= e($studioTr('status')) ?></th>
              <th><?= e($studioTr('installed_at')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($mergedApps as $mergedApp): ?>
              <?php if (!is_array($mergedApp)) { continue; } ?>
              <?php
                $mergedStatus = strtolower(trim((string)($mergedApp['status'] ?? 'unknown')));
                $mergedAppKey = trim((string)($mergedApp['app_key'] ?? ''));
              ?>
              <tr>
                <td><code><?= e($mergedAppKey) ?></code></td>
                <td><?= e((string)($mergedApp['source'] ?? 'core')) ?></td>
                <td><span class="<?= e($appStatusChipClass($mergedStatus)) ?>"><?= e($appStatusLabel($mergedStatus)) ?></span></td>
                <td><?= e((string)($mergedApp['installed_at'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </details>
  <?php endif; ?>
</div>

<?php if ($appSuites): ?>
  <?php
    $suiteBundleCount = count($appSuites);
    $suiteBundleInstalledCount = 0;
    $suiteBundleActiveCount = 0;
    $suiteBundleWarningCount = 0;
    $suiteBundleErrorCount = 0;
    $suiteBundleInstallActionCount = 0;
    $suiteBundleReviewActionCount = 0;
    foreach ($appSuites as $suiteMeta) {
      $suiteStatus = (string)($suiteMeta['status'] ?? 'uploaded');
      $bundleWarningCount = (int)($suiteMeta['arch_warning_count'] ?? 0);
      $bundleErrorCount = (int)($suiteMeta['arch_error_count'] ?? 0);
      if ($suiteStatus === 'upgrade_pending' || !empty($suiteMeta['bundle_degraded'])) {
        $bundleWarningCount++;
      } elseif ($suiteStatus === 'broken') {
        $bundleErrorCount++;
      }
      if (in_array($suiteStatus, ['installed', 'enabled', 'disabled', 'upgrade_pending', 'broken'], true)) {
        $suiteBundleInstalledCount++;
      }
      if ($suiteStatus === 'enabled') {
        $suiteBundleActiveCount++;
      }
      if (in_array($suiteStatus, ['uploaded', 'uninstalled'], true)) {
        $suiteBundleInstallActionCount++;
      }
      if ($bundleWarningCount > 0 || $bundleErrorCount > 0 || !empty($suiteMeta['bundle_degraded']) || in_array($suiteStatus, ['broken', 'upgrade_pending'], true)) {
        $suiteBundleReviewActionCount++;
      }
      $suiteBundleWarningCount += $bundleWarningCount;
      $suiteBundleErrorCount += $bundleErrorCount;
    }
    $suiteBundleIssueCount = $suiteBundleWarningCount + $suiteBundleErrorCount;
    $suiteBundleDefaultOpen = $suiteBundleIssueCount > 0;
  ?>
  <details
    class="card apps-section apps-manager-collapsible"
    id="apps-business-suites"
    data-section-name="business_suites"
    data-default-open="<?= $suiteBundleDefaultOpen ? '1' : '0' ?>"
    <?= $suiteBundleDefaultOpen ? 'open' : '' ?>
  >
    <summary>
      <div class="apps-section-summary">
        <div class="apps-section-summary-head">
          <span class="apps-section-indicator" aria-hidden="true"></span>
          <div class="apps-section-titlecopy">
            <strong>Business Suites / Bundles</strong>
            <div class="muted">Suite-level control surface for parent apps. Child module controls stay available below.</div>
          </div>
        </div>
        <div class="apps-section-action-hints">
          <?php if ($suiteBundleInstallActionCount > 0): ?>
            <span class="apps-section-action-hint">Install <?= $suiteBundleInstallActionCount ?></span>
          <?php endif; ?>
          <?php if ($suiteBundleReviewActionCount > 0): ?>
            <span class="apps-section-action-hint" data-tone="<?= $suiteBundleErrorCount > 0 ? 'danger' : 'warning' ?>">Review <?= $suiteBundleReviewActionCount ?></span>
          <?php endif; ?>
        </div>
      </div>
      <div class="apps-section-right">
        <div class="apps-summary-row">
          <span class="pill">Suites: <?= $suiteBundleCount ?></span>
          <span class="pill">Installed: <?= $suiteBundleInstalledCount ?></span>
          <span class="pill">Active: <?= $suiteBundleActiveCount ?></span>
          <span class="pill"> <?= e($tt('base.warnings_label')) ?> <?= $suiteBundleWarningCount ?></span>
          <span class="pill"> <?= e($tt('base.errors_label')) ?> <?= $suiteBundleErrorCount ?></span>
        </div>
        <div class="muted u-style-8700987962">
          <?= $suiteBundleIssueCount > 0 ? 'Suite attention items are expanded automatically.' : 'Summary stays visible while collapsed.' ?>
        </div>
      </div>
    </summary>
    <div class="apps-section-body">
    <div class="apps-bundle-grid">
      <?php foreach ($appSuites as $suite): ?>
        <?php
          $appKey = (string)($suite['app_key'] ?? '');
          $appName = (string)($suite['app_name'] ?? $appKey);
          $status = (string)($suite['status'] ?? 'uploaded');
          $manifest = is_array($suite['manifest'] ?? null) ? $suite['manifest'] : [];
          $childPlugins = array_values((array)($suite['child_plugins'] ?? []));
          $childTotal = (int)($suite['child_total'] ?? count($childPlugins));
          $childActive = (int)($suite['child_active'] ?? 0);
          $childInactive = (int)($suite['child_inactive'] ?? 0);
          $childMissing = (int)($suite['child_missing'] ?? 0);
          $archWarningCount = (int)($suite['arch_warning_count'] ?? 0);
          $archErrorCount = (int)($suite['arch_error_count'] ?? 0);
          $bundleDegraded = !empty($suite['bundle_degraded']);
          $dependencyHealth = (string)($suite['dependency_health'] ?? 'Healthy');
          $suiteWarning = trim((string)($suite['warning'] ?? ''));
          $appType = strtolower((string)($suite['app_type'] ?? 'business'));
          $canDisable = (bool)($manifest['can_disable'] ?? true);
          $canUninstall = (bool)($manifest['can_uninstall'] ?? true);
          $canExport = (bool)($manifest['can_export'] ?? false);
          $isInstalledState = in_array($status, ['installed','enabled','disabled','upgrade_pending','broken'], true);
          $installableState = in_array($status, ['uploaded','uninstalled'], true);
          $protectedCoreKeys = ['acl', 'bus', 'base', 'core', 'platform', 'shell'];
          $isProtected = in_array($appType, ['engine', 'framework', 'system'], true)
            || in_array(strtolower($appKey), $protectedCoreKeys, true);
          $disableReason = $isProtected ? 'Protected core/system app' : (!$canDisable ? 'Disable blocked by manifest' : '');
          $uninstallReason = $isProtected ? 'Protected core/system app' : (!$canUninstall ? 'Uninstall blocked by manifest' : '');
          $repairLabel = $bundleDegraded ? 'Repair Bundle' : 'Repair';
          $bundleTone = 'neutral';
          if ($status === 'broken' || $archErrorCount > 0) {
            $bundleTone = 'danger';
          } elseif ($bundleDegraded || $status === 'upgrade_pending' || $archWarningCount > 0) {
            $bundleTone = 'warning';
          } elseif ($status === 'enabled') {
            $bundleTone = 'success';
          }
        ?>
        <div id="apps-bundle-<?= e($appSectionId($appKey)) ?>" class="card ui-block apps-bundle-card" data-tone="<?= e($bundleTone) ?>">
          <div class="row u-style-d65402dd9c">
            <div class="ui-block">
              <div class="muted u-style-f0849d565c">Bundle</div>
              <h3 class="u-style-c532548333"><?= e($appName) ?></h3>
              <div class="muted u-style-1c36546128">Key: <?= e($appKey) ?><?php if (!empty($suite['version'])): ?> · v<?= e((string)$suite['version']) ?><?php endif; ?></div>
            </div>
            <span class="<?= e($appStatusChipClass($status)) ?>"><?= e($appStatusLabel($status)) ?></span>
          </div>

          <div class="row u-style-1246ed03dd">
            <span class="pill">Children: <?= $childTotal ?></span>
            <span class="pill">Active: <?= $childActive ?></span>
            <span class="pill"> <?= e($tt('base.inactive_label')) ?> <?= $childInactive ?></span>
            <span class="pill">Missing: <?= $childMissing ?></span>
            <span class="pill"> <?= e($tt('base.warnings_label')) ?> <?= $archWarningCount ?></span>
            <span class="pill"> <?= e($tt('base.errors_label')) ?> <?= $archErrorCount ?></span>
          </div>

          <div class="muted u-style-56f4356299">
            <strong class="u-style-f634a3cdcd">Dependency / Health:</strong> <?= e($dependencyHealth) ?>
            <?php if ($suiteWarning !== ''): ?>
              <div class="u-style-e054375552"><?= e($suiteWarning) ?></div>
            <?php endif; ?>
            <details class="apps-inline-details">
              <summary>Bundle contents</summary>
              <?php if ($childPlugins): ?>
                <div class="u-style-f196a449d8"><?= e(implode(', ', $childPlugins)) ?></div>
              <?php else: ?>
                <div class="u-style-f196a449d8">No child modules declared for this suite.</div>
              <?php endif; ?>
            </details>
          </div>

          <form method="post" action="<?= e($appsBoardBase) ?>/action" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:14px">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="app_key" value="<?= e($appKey) ?>">

            <?php if ($installableState): ?>
              <button class="btn ok" name="action" value="bundle_install" type="submit">Install Bundle</button>
            <?php endif; ?>

            <?php if (in_array($status, ['installed','disabled'], true)): ?>
              <button class="btn ok" name="action" value="bundle_enable" type="submit">Activate Bundle</button>
            <?php endif; ?>

            <?php if ($status === 'enabled'): ?>
              <?php if ($disableReason === ''): ?>
                <button class="btn" name="action" value="bundle_disable" type="submit">Deactivate Bundle</button>
              <?php else: ?>
                <button class="btn" type="button" disabled title="<?= e($disableReason) ?>">Deactivate Bundle</button>
              <?php endif; ?>
            <?php endif; ?>

            <?php if (in_array($status, ['installed','enabled','disabled','broken','upgrade_pending'], true)): ?>
              <button class="btn<?= $bundleDegraded ? ' ok' : '' ?>" name="action" value="bundle_repair" type="submit"><?= e($repairLabel) ?></button>
            <?php endif; ?>

            <?php if ($status === 'broken' || $status === 'upgrade_pending'): ?>
              <button class="btn" name="action" value="bundle_recover" type="submit">Recover Bundle</button>
            <?php endif; ?>

            <a class="btn" href="/admin/app-manager/detail?app_key=<?= urlencode($appKey) ?>">Bundle Details</a>

            <?php if ($canExport && $isInstalledState): ?>
              <a class="btn" href="/admin/app-manager/export?app_key=<?= urlencode($appKey) ?>">Export Bundle</a>
            <?php endif; ?>

            <?php if (in_array($status, ['installed','disabled','broken','upgrade_pending'], true)): ?>
              <?php if ($uninstallReason === ''): ?>
                <button class="btn" name="action" value="bundle_uninstall" type="submit" onclick="return confirm('Soft uninstall bundle <?= e($appName) ?>? Child plugin runtime will be removed together with the parent suite.');">Uninstall Bundle</button>
              <?php else: ?>
                <button class="btn" type="button" disabled title="<?= e($uninstallReason) ?>">Uninstall Bundle</button>
              <?php endif; ?>
            <?php endif; ?>

            <?php if (in_array($status, ['disabled','broken','upgrade_pending','uninstalled'], true)): ?>
              <?php if ($uninstallReason === ''): ?>
                <button class="btn danger" name="action" value="bundle_purge" type="submit" onclick="return confirm('Purge bundle <?= e($appName) ?> and its bundle-managed data? This cannot be undone.');">Purge Bundle Data</button>
              <?php else: ?>
                <button class="btn danger" type="button" disabled title="<?= e($uninstallReason) ?>">Purge Bundle Data</button>
              <?php endif; ?>
            <?php elseif ($status === 'enabled'): ?>
              <button class="btn danger" type="button" disabled title="Deactivate bundle first">Purge Bundle Data</button>
            <?php endif; ?>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
    </div>
  </details>
<?php endif; ?>

<?php if ($pkgEnabled): ?>
  <div class="card">
    <h2 class="u-style-759e41d83d">Package Uploads</h2>

    <form method="post" action="<?= e($appsBoardBase) ?>/upload" enctype="multipart/form-data"
          style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <label class="muted u-style-14b4b58a73">
        <span>Package Type</span>
        <select name="package_type">
          <option value="module">Upload Plugin / Module</option>
          <option value="bundle">Upload App / Bundle</option>
        </select>
      </label>
      <input class="input" type="file" name="zip" accept=".zip" required>
      <button class="btn ok" type="submit">Upload ZIP</button>
      <span class="muted">Modules install under their owner bundle when metadata provides `owner_app`. Bundles register as top-level apps under <code>apps/&lt;Bundle&gt;</code>.</span>
    </form>
  </div>

  <details class="card apps-section">
    <summary>
      <div class="ui-block">
        <strong> <?= e($tt('base.uploaded_packages_action')) ?> </strong>
        <div class="muted">Package inventory stays collapsed until needed.</div>
      </div>
      <div class="apps-summary-row">
        <span class="pill"><?= count($packages) ?> package<?= count($packages) === 1 ? '' : 's' ?></span>
      </div>
    </summary>
    <div class="apps-section-body">

    <?php if (!$packages): ?>
      <div class="muted"> <?= e($tt('base.apps_description')) ?> </div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>File</th>
              <th>Detected Package</th>
              <th>Type</th>
              <th>Owner</th>
              <th>Version</th>
              <th>Target</th>
              <th>Action</th>
            </tr>
          </thead>
        <tbody>
          <?php foreach ($packages as $p): ?>
            <?php
              $file = (string)($p['file'] ?? '');
              $m = $p['meta'] ?? [];
              $ok = (bool)($m['ok'] ?? false);
              $pname = $ok ? (string)$m['name'] : 'Invalid';
              $pver  = $ok ? (string)$m['version'] : '-';
              $packageType = $ok ? (string)($m['package_type'] ?? 'module') : '-';
              $ownerApp = $ok ? (string)($m['owner_app'] ?? '') : '';
              $targetPath = $ok ? (string)($p['target_path'] ?? '') : '';

              $inst = $installed[$pname] ?? null;
              $instVer = is_array($inst) ? (string)($inst['version'] ?? '') : '';
              $instStatus = is_array($inst) ? (string)($inst['status'] ?? 'not-installed') : 'not-installed';

              $suggest = '';
              if ($ok && $instVer !== '') {
                if (version_compare($pver, $instVer, '>')) $suggest = 'Upgrade suggested';
                elseif (version_compare($pver, $instVer, '==')) $suggest = 'Repair (same version)';
                else $suggest = 'Older than installed';
              }

              // Better mode decision: if not installed -> install
              $folderExists = $ok ? is_dir((string)($p['target_path'] ?? '')) : false;
              $isInstalled  = $ok ? isset($installed[$pname]) : false;

              $mode = $packageType === 'bundle'
                    ? ($folderExists ? 'upgrade' : 'install')
                    : ((!$folderExists || !$isInstalled) ? 'install'
                    : (($instVer !== '' && version_compare($pver, $instVer, '>')) ? 'upgrade' : 'repair'));
            ?>
            <tr>
              <td><?= e($file) ?></td>
              <td><?= e($pname) ?></td>
              <td><?= e($packageType) ?></td>
              <td><?= e($ownerApp !== '' ? $ownerApp : '-') ?></td>
              <td>
                <?= e($pver) ?>
                <?php if ($suggest): ?>
                  <span class="muted">(<?= e($suggest) ?>)</span>
                <?php endif; ?>
              </td>
              <td><code><?= e($targetPath !== '' ? str_replace(APP_ROOT . '/', '', $targetPath) : '-') ?></code></td>
              <td>
                <?php if (!$ok): ?>
                  <span class="muted">Cannot use</span>
                <?php else: ?>
                  <form method="post" action="<?= e($appsBoardBase) ?>/package/apply"
                        style="display:inline-flex;gap:8px;flex-wrap:wrap;align-items:center">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="file" value="<?= e($file) ?>">
                    <input type="hidden" name="mode" value="<?= e($mode) ?>">

                    <?php if ($packageType !== 'bundle' && $instStatus === 'active'): ?>
                      <span class="muted">Deactivate to apply</span>
                    <?php else: ?>
                      <button class="btn" type="submit"
                        onclick="return confirm('Apply package to <?= e($pname) ?> (type: <?= e($packageType) ?>, mode: <?= e($mode) ?>).\\n\\nA ZIP backup will be created before replace where applicable. Continue?');">
                        Apply (<?= e($mode) ?>)
                      </button>
                    <?php endif; ?>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    <?php endif; ?>
    </div>
  </details>
<?php else: ?>
  <div class="card">
    <div class="muted">
      Package system disabled: <code>App\Core\PackageManager</code> not found.
    </div>
  </div>
<?php endif; ?>

<?php
$suiteBuckets = is_array($suiteBuckets ?? null) ? (array)$suiteBuckets : [];
$groupedPlugins = is_array($groupedPlugins ?? null) ? (array)$groupedPlugins : [];

foreach ($groupedPlugins as $suiteKey => &$bucket) {
  if (!$bucket) continue;
  foreach ($bucket as $pluginName => $manifest) {
    $pluginSummaryCounts['all']++;
    $pluginStatus = (string)(($installed[$pluginName]['status'] ?? 'not-installed'));
    if ($pluginStatus === 'active') {
      $pluginSummaryCounts['active']++;
    } elseif ($pluginStatus === 'inactive') {
      $pluginSummaryCounts['inactive']++;
    } else {
      $pluginSummaryCounts['not_installed']++;
    }
    $archNode = (array)(($architecture['plugins'][$pluginName] ?? []) ?: []);
    $pluginSummaryCounts['warnings'] += count((array)($archNode['warnings'] ?? []));
    $pluginSummaryCounts['errors'] += count((array)($archNode['errors'] ?? []));
  }
}
unset($bucket);
?>

<div class="card">
  <div class="row u-style-d65402dd9c">
    <div class="ui-block">
      <h3 class="u-style-ad7f18b19e">Module Control Surface</h3>
      <div class="muted">Collapsed suite buckets with quick filtering for child module operations.</div>
    </div>
    <div class="apps-summary-row">
      <span class="pill">All: <?= $pluginSummaryCounts['all'] ?></span>
      <span class="pill">Active: <?= $pluginSummaryCounts['active'] ?></span>
      <span class="pill"> <?= e($tt('base.inactive_label')) ?> <?= $pluginSummaryCounts['inactive'] ?></span>
      <span class="pill">Not Installed: <?= $pluginSummaryCounts['not_installed'] ?></span>
      <span class="pill"> <?= e($tt('base.warnings_label')) ?> <?= $pluginSummaryCounts['warnings'] ?></span>
      <span class="pill"> <?= e($tt('base.errors_label')) ?> <?= $pluginSummaryCounts['errors'] ?></span>
    </div>
  </div>
  <div class="apps-toolbar">
    <input class="input" type="search" id="appsPluginSearch" placeholder="Filter module, suite, or group">
    <select id="appsPluginStatusFilter">
      <option value="all"> <?= e($tt('base.all_statuses_message')) ?> </option>
      <option value="active"> <?= e($tt('base.active_message')) ?> </option>
      <option value="inactive"> <?= e($tt('base.inactive_message')) ?> </option>
      <option value="not-installed">Not installed</option>
    </select>
  </div>
</div>

<?php foreach ($suiteBuckets as $suiteKey => $meta): ?>
  <?php $suitePlugins = $groupedPlugins[$suiteKey] ?? []; ?>
  <?php if (!$suitePlugins) continue; ?>
  <?php
    $suiteSectionId = 'apps-suite-' . $appSectionId((string)$suiteKey);
    $suiteInstalledCount = 0;
    $suiteActiveCount = 0;
    $suiteWarningCount = 0;
    $suiteErrorCount = 0;
    $suiteInstallActionCount = 0;
    $suiteActivateActionCount = 0;
    $suiteUpdateActionCount = 0;
    $suiteReviewActionCount = 0;
    foreach ($suitePlugins as $pluginName => $suiteManifest) {
      $pluginStatus = (string)(($installed[$pluginName]['status'] ?? 'not-installed'));
      if (in_array($pluginStatus, ['active', 'inactive'], true)) {
        $suiteInstalledCount++;
      }
      if ($pluginStatus === 'active') {
        $suiteActiveCount++;
      }
      $suiteArchNode = (array)(($architecture['plugins'][$pluginName] ?? []) ?: []);
      $pluginWarningCount = count((array)($suiteArchNode['warnings'] ?? []));
      $pluginErrorCount = count((array)($suiteArchNode['errors'] ?? []));
      if ($pluginStatus === 'not-installed') {
        $suiteInstallActionCount++;
      }
      if ($pluginStatus === 'inactive') {
        $suiteActivateActionCount++;
      }
      if ($pluginStatus !== 'not-installed' && (($installed[$pluginName]['version'] ?? null) !== null) && (string)($installed[$pluginName]['version'] ?? '') !== (string)($suiteManifest['version'] ?? '')) {
        $suiteUpdateActionCount++;
      }
      if ($pluginStatus === 'broken') {
        $pluginErrorCount++;
      } elseif ($pluginStatus === 'upgrade_pending') {
        $pluginWarningCount++;
      }
      if ($pluginWarningCount > 0 || $pluginErrorCount > 0) {
        $suiteReviewActionCount++;
      }
      $suiteWarningCount += $pluginWarningCount;
      $suiteErrorCount += $pluginErrorCount;
    }
    $suiteIssueCount = $suiteWarningCount + $suiteErrorCount;
    $suiteDefaultOpen = $suiteIssueCount > 0;
  ?>
  <details
    class="card apps-section apps-manager-collapsible"
    id="<?= e($suiteSectionId) ?>"
    data-section-name="<?= e($suiteKey) ?>"
    data-default-open="<?= $suiteDefaultOpen ? '1' : '0' ?>"
    <?= $suiteDefaultOpen ? 'open' : '' ?>
  >
    <summary>
      <div class="apps-section-summary">
        <div class="apps-section-summary-head">
          <span class="apps-section-indicator" aria-hidden="true"></span>
          <div class="apps-section-titlecopy">
            <strong><?= e($meta['title']) ?></strong>
            <div class="muted"><?= e($meta['subtitle']) ?></div>
          </div>
        </div>
        <div class="apps-section-action-hints">
          <?php if ($suiteInstallActionCount > 0): ?>
            <span class="apps-section-action-hint">Install <?= $suiteInstallActionCount ?></span>
          <?php endif; ?>
          <?php if ($suiteActivateActionCount > 0): ?>
            <span class="apps-section-action-hint">Activate <?= $suiteActivateActionCount ?></span>
          <?php endif; ?>
          <?php if ($suiteUpdateActionCount > 0): ?>
            <span class="apps-section-action-hint">Update <?= $suiteUpdateActionCount ?></span>
          <?php endif; ?>
          <?php if ($suiteReviewActionCount > 0): ?>
            <span class="apps-section-action-hint" data-tone="<?= $suiteErrorCount > 0 ? 'danger' : 'warning' ?>">Review <?= $suiteReviewActionCount ?></span>
          <?php endif; ?>
        </div>
      </div>
      <div class="apps-section-right">
        <div class="apps-summary-row">
          <span class="pill">Modules: <?= count($suitePlugins) ?></span>
          <span class="pill">Installed: <?= $suiteInstalledCount ?></span>
          <span class="pill">Active: <?= $suiteActiveCount ?></span>
          <span class="pill"> <?= e($tt('base.warnings_label')) ?> <?= $suiteWarningCount ?></span>
          <span class="pill"> <?= e($tt('base.errors_label')) ?> <?= $suiteErrorCount ?></span>
        </div>
        <div class="muted u-style-8700987962">
          <?= $suiteIssueCount > 0 ? 'Attention needed in this section.' : 'Summary stays visible while collapsed.' ?>
        </div>
      </div>
    </summary>
    <div class="apps-section-body">
    <div class="table-wrap">
      <table class="apps-module-table table-mobile-cards">
        <colgroup>
          <col class="apps-module-col-module">
          <col class="table-col-compact">
          <col class="table-col-compact">
          <col class="apps-module-col-summary table-col-summary">
          <col class="apps-module-col-actions table-col-actions">
        </colgroup>
        <thead>
          <tr>
            <th>Module</th>
            <th>Version</th>
            <th>Status</th>
            <th>Summary</th>
            <th>Actions</th>
          </tr>
        </thead>
      <tbody>
        <?php foreach ($suitePlugins as $name => $m):
          $inst = $installed[$name] ?? null;
          $status = $inst['status'] ?? 'not-installed';
          $type = $m['type'] ?? 'business';
          $ver = $m['version'] ?? '0.0.0';
          $installedVer = $inst['version'] ?? null;
          $hasUpdate = $installedVer !== null && $installedVer !== $ver;
          $req = $m['requires'] ?? [];
          $displayName = (string)($m['display_name'] ?? $name);
          $groupName = (string)($m['group'] ?? ($m['suite'] ?? ''));
          $suiteName = strtolower((string)($m['suite'] ?? ''));
          $isCoreSuite = $suiteName === 'core';
          $isEngineType = strtolower((string)$type) === 'engine';
          $isInstalledState = in_array((string)$status, ['active', 'inactive'], true);
          $canExport = ($name !== 'Base') && !$isCoreSuite && !$isEngineType && $isInstalledState;
          $archNode = (array)(($architecture['plugins'][$name] ?? []) ?: []);
          $archWarnings = (array)($archNode['warnings'] ?? []);
          $archErrors = (array)($archNode['errors'] ?? []);
          $requiresList = [];
          if (is_array($req)) {
            foreach ($req as $dep) {
              $depLabel = trim((string)$dep);
              if ($depLabel !== '') {
                $requiresList[] = $depLabel;
              }
            }
          } else {
            $depLabel = trim((string)$req);
            if ($depLabel !== '') {
              $requiresList[] = $depLabel;
            }
          }
          $archMessages = [];
          foreach ($archErrors as $msg) {
            $archMessages[] = ['tone' => 'danger', 'text' => (string)$msg];
          }
          foreach ($archWarnings as $msg) {
            $archMessages[] = ['tone' => 'warning', 'text' => (string)$msg];
          }
          $archPreview = array_slice($archMessages, 0, 2);
          $archOverflowCount = max(0, count($archMessages) - count($archPreview));
          $rowSearch = strtolower(implode(' ', [$name, $displayName, $groupName, $suiteKey, $status]));
        ?>
        <tr class="apps-plugin-row" data-plugin-search="<?= e($rowSearch) ?>" data-plugin-status="<?= e((string)$status) ?>">
          <td data-label="Module">
            <strong><?= e($displayName) ?></strong>
            <?php if ($displayName !== $name): ?>
              <span class="muted u-style-ba3d0719f9">(module: <?= e($name) ?>)</span>
            <?php endif; ?>
            <div class="muted"><?= e((string)($m['description'] ?? '')) ?></div>
          </td>
          <td data-label="Version">
            <?= e((string)$ver) ?>
            <?php if ($hasUpdate): ?>
              <div class="muted u-style-64ef25b191">installed: <?= e((string)$installedVer) ?></div>
            <?php endif; ?>
          </td>
          <td class="apps-status-cell" data-label="Status">
            <?php $statusLabel = $status === 'not-installed' ? 'not installed' : (string)$status; ?>
            <span class="<?= e($appStatusChipClass((string)$statusLabel)) ?>"><?= e($appStatusLabel((string)$statusLabel)) ?></span>
          </td>
          <td class="table-summary-cell" data-label="Summary">
            <div class="table-summary-card">
              <div class="table-summary-badges">
              <span class="pill"><?= e($groupName !== '' ? $groupName : 'none') ?></span>
              <span class="pill"><?= e((string)$type) ?></span>
              <?php if ($requiresList): ?>
                <span class="pill">Requires: <?= count($requiresList) ?></span>
              <?php endif; ?>
              <?php if ($archErrors): ?>
                <span class="pill u-style-cf43f6d004"> <?= e($tt('base.errors_label')) ?> <?= count($archErrors) ?></span>
              <?php endif; ?>
              <?php if ($archWarnings): ?>
                <span class="pill u-style-622de93d6c"> <?= e($tt('base.warnings_label')) ?> <?= count($archWarnings) ?></span>
              <?php endif; ?>
              </div>
              <div class="table-summary-meta">
                <div class="table-summary-meta-row">
                  <span class="table-summary-meta-label">Dependencies</span>
                  <span class="table-summary-meta-value"><?= e($requiresList ? implode(', ', $requiresList) : 'None') ?></span>
                </div>
                <div class="table-summary-meta-row">
                  <span class="table-summary-meta-label">Architecture</span>
                  <?php if ($archPreview): ?>
                    <div class="table-summary-notes">
                      <?php foreach ($archPreview as $archMessage): ?>
                        <div class="table-summary-note table-summary-note-<?= e((string)$archMessage['tone']) ?>"><?= e((string)$archMessage['text']) ?></div>
                      <?php endforeach; ?>
                      <?php if ($archOverflowCount > 0): ?>
                        <div class="table-summary-note table-summary-note-muted">+<?= $archOverflowCount ?> more issue<?= $archOverflowCount === 1 ? '' : 's' ?></div>
                      <?php endif; ?>
                    </div>
                  <?php else: ?>
                    <span class="table-summary-meta-value table-summary-note-muted">No architecture issues</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </td>
          <td class="table-actions-cell" data-label="Actions">
            <form method="post" action="<?= e($appsBoardBase) ?>/action" class="table-actions-wrap">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="name" value="<?= e($name) ?>">

              <?php if ($isInstalledState): ?>
                <a class="btn" href="<?= e($appsBoardBase) ?>/deps?name=<?= urlencode($name) ?>">Details</a>
                <?php if ($canExport): ?>
                  <a class="btn" href="<?= e($appsBoardBase) ?>/export?name=<?= urlencode($name) ?>">Export ZIP</a>
                <?php endif; ?>
              <?php endif; ?>

              <?php if ($status === 'not-installed'): ?>
                <button class="btn ok" name="action" value="install" type="submit">Install</button>

              <?php elseif ($status === 'inactive'): ?>
                <button class="btn ok" name="action" value="enable" type="submit">Activate</button>

                <button class="btn<?= $hasUpdate ? ' ok' : '' ?>" name="action" value="update" type="submit">Update</button>
                <span class="muted">Uninstall and purge move to Details for guarded module lifecycle work.</span>

              <?php else: ?>
                <?php if ($name !== 'Base'): ?>
                  <button class="btn" name="action" value="disable" type="submit">Deactivate</button>
                <?php else: ?>
                  <span class="muted">Base required</span>
                <?php endif; ?>
                <button class="btn<?= $hasUpdate ? ' ok' : '' ?>" name="action" value="update" type="submit">Update</button>
              <?php endif; ?>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    </div>
  </details>
<?php endforeach; ?>

<details class="card apps-section apps-manager-collapsible" id="apps-future-experimental" data-section-name="future_experimental" data-default-open="0">
  <summary>
    <div class="apps-section-summary">
      <div class="apps-section-summary-head">
        <span class="apps-section-indicator" aria-hidden="true"></span>
        <div class="apps-section-titlecopy">
          <strong>Future / Experimental</strong>
          <div class="muted">Planned suite separation for the next expansion wave.</div>
        </div>
      </div>
      <div class="apps-section-action-hints">
        <span class="apps-section-action-hint">Plan 7</span>
      </div>
    </div>
    <div class="apps-section-right">
      <div class="apps-summary-row">
        <span class="pill">Suites: 7</span>
        <span class="pill">Installed: 0</span>
        <span class="pill"> <?= e($tt('base.active_0_message')) ?> </span>
        <span class="pill"> <?= e($tt('base.warnings')) ?> </span>
        <span class="pill"> <?= e($tt('base.errors')) ?> </span>
      </div>
      <div class="muted u-style-8700987962">Roadmap placeholders only.</div>
    </div>
  </summary>
  <div class="apps-section-body">
    <div class="row u-style-4725bb7117">
      <span class="pill">HR</span>
      <span class="pill">Accounting</span>
      <span class="pill">CRM</span>
      <span class="pill">Procurement</span>
      <span class="pill">Sales</span>
      <span class="pill">Warehouse</span>
      <span class="pill">Reports / BI</span>
    </div>
  </div>
</details>

</div>

<script>
(function () {
  var storagePrefix = 'apps_manager.section.';
  var searchEl = document.getElementById('appsPluginSearch');
  var statusEl = document.getElementById('appsPluginStatusFilter');
  var sections = Array.prototype.map.call(document.querySelectorAll('.apps-manager-collapsible'), function (section) {
    var sectionName = String(section.getAttribute('data-section-name') || '').trim();
    return sectionName === '' ? null : {
      defaultOpen: String(section.getAttribute('data-default-open') || '0') === '1',
      section: section,
      storageKey: storagePrefix + sectionName
    };
  });
  var isSyncingSections = false;

  sections = sections.filter(function (record) {
    return !!record;
  });

  function getStoredSectionState(record) {
    try {
      return window.localStorage.getItem(record.storageKey);
    } catch (_err) {
      return null;
    }
  }

  function setStoredSectionState(record, value) {
    try {
      window.localStorage.setItem(record.storageKey, value);
    } catch (_err) {
      // Ignore localStorage failures and keep the UI usable.
    }
  }

  function hasActiveModuleFilters() {
    if (!searchEl || !statusEl) {
      return false;
    }
    return String(searchEl.value || '').trim() !== '' || String(statusEl.value || 'all').toLowerCase() !== 'all';
  }

  function sectionMatchesHash(section) {
    var hash = String(window.location.hash || '').replace(/^#/, '').trim();
    if (hash === '') {
      return false;
    }
    if (section.id === hash) {
      return true;
    }
    var target = document.getElementById(hash);
    return !!(target && section.contains(target));
  }

  function sectionHasPluginRows(section) {
    return !!section.querySelector('.apps-plugin-row');
  }

  function sectionHasVisibleMatches(section) {
    return !!section.querySelector('.apps-plugin-row[data-hidden-by-filter="0"]');
  }

  function resolveSectionOpenState(record) {
    if (sectionMatchesHash(record.section)) {
      return true;
    }

    if (hasActiveModuleFilters() && sectionHasPluginRows(record.section)) {
      return sectionHasVisibleMatches(record.section);
    }

    var storedValue = getStoredSectionState(record);
    if (storedValue === 'open') {
      return true;
    }
    if (storedValue === 'closed') {
      return false;
    }
    return record.defaultOpen;
  }

  function syncSectionStates() {
    isSyncingSections = true;
    sections.forEach(function (record) {
      record.section.open = resolveSectionOpenState(record);
    });
    isSyncingSections = false;
  }

  sections.forEach(function (record) {
    record.section.addEventListener('toggle', function () {
      if (isSyncingSections) {
        return;
      }
      setStoredSectionState(record, record.section.open ? 'open' : 'closed');
    });
  });

  function applyPluginFilters() {
    if (!searchEl || !statusEl) {
      syncSectionStates();
      return;
    }

    var query = String(searchEl.value || '').toLowerCase().trim();
    var status = String(statusEl.value || 'all').toLowerCase();
    document.querySelectorAll('.apps-plugin-row').forEach(function (row) {
      var haystack = String(row.getAttribute('data-plugin-search') || '');
      var rowStatus = String(row.getAttribute('data-plugin-status') || '');
      var visible = true;
      if (query !== '' && haystack.indexOf(query) === -1) {
        visible = false;
      }
      if (visible && status !== 'all' && rowStatus !== status) {
        visible = false;
      }
      row.setAttribute('data-hidden-by-filter', visible ? '0' : '1');
    });
    syncSectionStates();
  }

  if (searchEl && statusEl) {
    searchEl.addEventListener('input', applyPluginFilters);
    statusEl.addEventListener('change', applyPluginFilters);
  }

  window.addEventListener('hashchange', syncSectionStates);
  applyPluginFilters();
})();
</script>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

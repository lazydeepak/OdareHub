<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$app = is_array($app ?? null) ? $app : [];
$migrations = is_array($migrations ?? null) ? $migrations : [];
$snapshots = is_array($snapshots ?? null) ? $snapshots : [];
$hooks = is_array($hooks ?? null) ? $hooks : [];
$lifecycleLog = is_array($lifecycleLog ?? null) ? $lifecycleLog : [];
$manifest = is_array($manifest ?? null) ? $manifest : [];
$uiSurfaceDiagnostics = is_array($uiSurfaceDiagnostics ?? null) ? $uiSurfaceDiagnostics : [];
$uiSummary = is_array($uiSurfaceDiagnostics['summary'] ?? null) ? $uiSurfaceDiagnostics['summary'] : [];
$uiMissingLocale = is_array($uiSurfaceDiagnostics['missing_locale_keys'] ?? null) ? $uiSurfaceDiagnostics['missing_locale_keys'] : [];
$uiDeprecatedAliases = is_array($uiSurfaceDiagnostics['deprecated_aliases'] ?? null) ? $uiSurfaceDiagnostics['deprecated_aliases'] : [];
$uiDuplicates = is_array($uiSurfaceDiagnostics['duplicate_declarations'] ?? null) ? $uiSurfaceDiagnostics['duplicate_declarations'] : [];
$uiNavContract = is_array($uiSurfaceDiagnostics['nav_contract_items'] ?? null) ? $uiSurfaceDiagnostics['nav_contract_items'] : [];
$uiNavigationPhp = is_array($uiSurfaceDiagnostics['navigation_php_items'] ?? null) ? $uiSurfaceDiagnostics['navigation_php_items'] : [];
$uiSurfaces = is_array($uiSurfaceDiagnostics['surface_rows'] ?? null) ? $uiSurfaceDiagnostics['surface_rows'] : [];
$versionInfo = is_array($versionInfo ?? null) ? $versionInfo : [];
$dependencyDetail = is_array($dependencyDetail ?? null) ? $dependencyDetail : [];
$status = (string)($app['status'] ?? 'uploaded');
$canExport = (bool)($manifest['can_export'] ?? false);
$isInstalledState = in_array($status, ['installed','enabled','disabled','upgrade_pending','broken'], true);
$impactTone = static function (string $status): string {
    return match ($status) {
        'safe' => 'color:#6df2a6',
        'warning' => 'color:#ffd27d',
        'blocked' => 'color:#ff9b9b',
        'requires_prior_action' => 'color:#8ec1ff',
        default => 'color:#d7d7d7',
    };
};
?>

<div class="card">
  <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;align-items:center">
    <h2 style="margin:0">App Detail: <?= e((string)($app['app_name'] ?? $app['app_key'] ?? '')) ?></h2>
    <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
      <?php if ($canExport && $isInstalledState): ?>
        <a class="btn ok" href="/admin/app-manager/export?app_key=<?= urlencode((string)($app['app_key'] ?? '')) ?>">Export ZIP</a>
      <?php endif; ?>
      <a class="btn" href="/admin/app-manager">Back</a>
    </div>
  </div>
  <div class="muted" style="margin-top:6px">
    Key: <?= e((string)($app['app_key'] ?? '')) ?>
    | Current: <?= e((string)($versionInfo['current_version'] ?? ($app['version'] ?? ''))) ?>
    | Target: <?= e((string)($versionInfo['target_version'] ?? ($app['version'] ?? ''))) ?>
    | Status: <span class="pill"><?= e((string)($app['status'] ?? '')) ?></span>
  </div>
  <?php if (!empty($versionInfo['latest_entry']['summary'])): ?>
    <div class="muted" style="margin-top:8px">Latest changelog: <?= e((string)($versionInfo['latest_entry']['summary'] ?? '')) ?></div>
  <?php endif; ?>
  <?php if (!empty($app['error_text'])): ?>
    <div style="margin-top:8px;color:#ff9b9b">Last error: <?= e((string)$app['error_text']) ?></div>
  <?php endif; ?>
</div>

<div class="card">
  <h3 style="margin:0 0 8px">Version & Changelog</h3>
  <div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));margin-bottom:12px">
    <div class="card" style="margin:0">
      <strong>Current Version</strong>
      <div class="muted"><?= e((string)($versionInfo['current_version'] ?? '')) ?></div>
    </div>
    <div class="card" style="margin:0">
      <strong>Target Version</strong>
      <div class="muted"><?= e((string)($versionInfo['target_version'] ?? '')) ?></div>
    </div>
    <div class="card" style="margin:0">
      <strong>Upgrade Awareness</strong>
      <div class="muted"><?= !empty($versionInfo['upgrade_available']) ? 'Upgrade available' : 'Current version is aligned with target' ?></div>
    </div>
  </div>
  <?php if (!empty($versionInfo['entries'])): ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Version</th><th>Release Date</th><th>Summary</th><th>Migration Notes</th><th>Setup / Upgrade Notes</th></tr></thead>
        <tbody>
          <?php foreach ((array)($versionInfo['entries'] ?? []) as $entry): ?>
            <tr>
              <td><?= e((string)($entry['version'] ?? '')) ?></td>
              <td><?= e((string)($entry['release_date'] ?? '')) ?></td>
              <td><?= e((string)($entry['summary'] ?? '')) ?></td>
              <td><?= e(implode(' | ', array_map('strval', (array)($entry['migration_notes'] ?? [])))) ?></td>
              <td><?= e(implode(' | ', array_map('strval', (array)($entry['setup_upgrade_notes'] ?? [])))) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="muted">No changelog entries recorded yet.</div>
  <?php endif; ?>
</div>

<?php if ($dependencyDetail !== []): ?>
<div class="card">
  <h3 style="margin:0 0 8px">Dependency Detail</h3>
  <div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));margin-bottom:12px">
    <div class="card" style="margin:0">
      <strong>Direct Dependencies</strong>
      <div class="muted" style="margin-top:6px"><?= e(implode(', ', array_map('strval', (array)($dependencyDetail['direct_dependencies'] ?? []))) ?: 'None') ?></div>
    </div>
    <div class="card" style="margin:0">
      <strong>Reverse Dependencies</strong>
      <div class="muted" style="margin-top:6px"><?= e(implode(', ', array_map('strval', (array)($dependencyDetail['reverse_dependencies'] ?? []))) ?: 'None') ?></div>
    </div>
    <div class="card" style="margin:0">
      <strong>Shared Platform Dependencies</strong>
      <div class="muted" style="margin-top:6px"><?= e(implode(', ', array_map('strval', (array)($dependencyDetail['shared_platform_dependencies'] ?? []))) ?: 'None') ?></div>
    </div>
    <div class="card" style="margin:0">
      <strong>Dependency Health</strong>
      <?php $dependencyHealth = is_array($dependencyDetail['dependency_health'] ?? null) ? $dependencyDetail['dependency_health'] : []; ?>
      <div style="margin-top:6px"><span class="pill" style="<?= e($impactTone((string)($dependencyHealth['status'] ?? 'safe'))) ?>"><?= e(ucfirst(str_replace('_', ' ', (string)($dependencyHealth['status'] ?? 'safe')))) ?></span></div>
      <div class="muted" style="margin-top:6px"><?= e((string)($dependencyHealth['summary'] ?? '')) ?></div>
    </div>
  </div>
  <?php if (!empty($dependencyDetail['action_impacts'])): ?>
    <div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
      <?php foreach ((array)($dependencyDetail['action_impacts'] ?? []) as $impact): ?>
        <div class="card" style="margin:0">
          <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start">
            <strong><?= e(ucfirst((string)($impact['action'] ?? 'action'))) ?></strong>
            <span class="pill" style="<?= e($impactTone((string)($impact['status'] ?? 'safe'))) ?>"><?= e((string)($impact['label'] ?? 'Safe')) ?></span>
          </div>
          <div class="muted" style="margin-top:6px"><?= e((string)($impact['summary'] ?? '')) ?></div>
          <?php if (!empty($impact['blocking_dependencies'])): ?>
            <div class="muted" style="margin-top:6px">Blocking: <?= e(implode(' | ', array_map('strval', (array)($impact['blocking_dependencies'] ?? [])))) ?></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
  <h3 style="margin:0 0 8px">UI Surface Diagnostics</h3>
  <?php if (!$uiSummary): ?>
    <div class="muted">No UI diagnostics available.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Metric</th><th>Value</th></tr></thead>
        <tbody>
          <tr><td>App Status</td><td><?= e((string)($uiSummary['app_status'] ?? 'unknown')) ?></td></tr>
          <tr><td>Manifest Nav Contract Items</td><td><?= (int)($uiSummary['nav_contract_items'] ?? 0) ?></td></tr>
          <tr><td>navigation.php Contract Items</td><td><?= (int)($uiSummary['navigation_php_items'] ?? 0) ?></td></tr>
          <tr><td>Runtime Surface Rows</td><td><?= (int)($uiSummary['surface_rows'] ?? 0) ?></td></tr>
          <tr><td>Deprecated UI Aliases</td><td><?= (int)($uiSummary['deprecated_ui_aliases'] ?? 0) ?></td></tr>
          <tr><td>Missing Locale Keys</td><td><?= (int)($uiSummary['missing_locale_keys'] ?? 0) ?></td></tr>
          <tr><td>Duplicate Declarations</td><td><?= (int)($uiSummary['duplicate_declarations'] ?? 0) ?></td></tr>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h3 style="margin:0 0 8px">UI Localization Gaps (EN/JA)</h3>
  <?php if (!$uiMissingLocale): ?>
    <div class="muted">No missing localization keys detected for declared UI surfaces.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Domain</th><th>Type</th><th>Key</th><th>Label Key</th><th>EN</th><th>JA</th></tr></thead>
        <tbody>
        <?php foreach ($uiMissingLocale as $row): ?>
          <tr>
            <td><?= e((string)($row['domain'] ?? '')) ?></td>
            <td><?= e((string)($row['type'] ?? '')) ?></td>
            <td><?= e((string)($row['key'] ?? '')) ?></td>
            <td><?= e((string)($row['label_key'] ?? '')) ?></td>
            <td><?= !empty($row['missing_en']) ? 'Missing' : 'OK' ?></td>
            <td><?= !empty($row['missing_ja']) ? 'Missing' : 'OK' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h3 style="margin:0 0 8px">Deprecated UI Aliases</h3>
  <?php if (!$uiDeprecatedAliases): ?>
    <div class="muted">No deprecated UI aliases declared.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Alias Path</th><th>Canonical Target</th><th>Feature</th><th>Enabled</th></tr></thead>
        <tbody>
        <?php foreach ($uiDeprecatedAliases as $alias): ?>
          <tr>
            <td><?= e((string)($alias['path'] ?? '')) ?></td>
            <td><?= e((string)($alias['canonical_target'] ?? '')) ?></td>
            <td><?= e((string)($alias['feature_key'] ?? '')) ?></td>
            <td><?= !empty($alias['enabled']) ? 'Yes' : 'No' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h3 style="margin:0 0 8px">Duplicate UI Declarations</h3>
  <?php if (!$uiDuplicates): ?>
    <div class="muted">No duplicate nav/surface declarations detected.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Composite Key</th><th>Count</th><th>Entries</th></tr></thead>
        <tbody>
        <?php foreach ($uiDuplicates as $dup): ?>
          <tr>
            <td><?= e((string)($dup['composite_key'] ?? '')) ?></td>
            <td><?= (int)($dup['count'] ?? 0) ?></td>
            <td><pre style="margin:0;white-space:pre-wrap;word-break:break-word"><?= e(json_encode((array)($dup['entries'] ?? []), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '[]') ?></pre></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h3 style="margin:0 0 8px">Navigation Ownership (Contract vs navigation.php)</h3>
  <div class="muted" style="margin-bottom:8px">Manifest contract items are normalized runtime metadata. navigation.php items power sidebar runtime contract mapping.</div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Source</th><th>Key</th><th>Feature</th><th>Label Key</th><th>URL</th><th>Role Visibility</th></tr></thead>
      <tbody>
      <?php foreach (array_slice($uiNavContract, 0, 100) as $item): ?>
        <tr>
          <td>manifest</td>
          <td><?= e((string)($item['key'] ?? '')) ?></td>
          <td><?= e((string)($item['feature_key'] ?? '')) ?></td>
          <td><?= e((string)($item['label_key'] ?? '')) ?></td>
          <td><?= e((string)($item['url'] ?? '')) ?></td>
          <td><?= e(implode(', ', array_values((array)($item['role_visibility'] ?? [])))) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php foreach (array_slice($uiNavigationPhp, 0, 100) as $item): ?>
        <tr>
          <td>navigation.php</td>
          <td><?= e((string)($item['key'] ?? '')) ?></td>
          <td><?= e((string)($item['feature_key'] ?? '')) ?></td>
          <td><?= e((string)($item['label_key'] ?? '')) ?></td>
          <td><?= e((string)($item['url'] ?? '')) ?></td>
          <td><?= e(implode(', ', array_values((array)($item['role_visibility'] ?? [])))) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <h3 style="margin:0 0 8px">Runtime UI Surfaces</h3>
  <?php if (!$uiSurfaces): ?>
    <div class="muted">No runtime surfaces registered.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Type</th><th>Key</th><th>Feature</th><th>Label Key</th><th>URL</th><th>Enabled</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($uiSurfaces, 0, 250) as $surface): ?>
          <tr>
            <td><?= e((string)($surface['surface_type'] ?? '')) ?></td>
            <td><?= e((string)($surface['key'] ?? '')) ?></td>
            <td><?= e((string)($surface['feature_key'] ?? '')) ?></td>
            <td><?= e((string)($surface['label_key'] ?? '')) ?></td>
            <td><?= e((string)($surface['url'] ?? '')) ?></td>
            <td><?= !empty($surface['enabled']) ? 'Yes' : 'No' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h3 style="margin:0 0 8px">Manifest</h3>
  <pre style="white-space:pre-wrap;word-break:break-word;margin:0"><?= e(json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}') ?></pre>
</div>

<div class="card">
  <h3 style="margin:0 0 8px">Migrations</h3>
  <?php if (!$migrations): ?>
    <div class="muted">No migration history recorded.</div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Name</th><th>Version</th><th>Status</th><th>Applied At</th><th>Error</th></tr></thead>
      <tbody>
      <?php foreach ($migrations as $m): ?>
        <tr>
          <td><?= e((string)($m['migration_name'] ?? '')) ?></td>
          <td><?= e((string)($m['version'] ?? '')) ?></td>
          <td><span class="pill"><?= e((string)($m['status'] ?? '')) ?></span></td>
          <td><?= e((string)($m['applied_at'] ?? '')) ?></td>
          <td><?= e((string)($m['error_text'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<div class="card">
  <h3 style="margin:0 0 8px">Schema Snapshots</h3>
  <?php if (!$snapshots): ?>
    <div class="muted">No snapshots recorded.</div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Version</th><th>Checksum</th><th>Created By</th><th>Created At</th></tr></thead>
      <tbody>
      <?php foreach ($snapshots as $s): ?>
        <tr>
          <td><?= e((string)($s['app_version'] ?? '')) ?></td>
          <td><?= e((string)($s['checksum'] ?? '')) ?></td>
          <td><?= e((string)($s['created_by'] ?? '')) ?></td>
          <td><?= e((string)($s['created_at'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<div class="card">
  <h3 style="margin:0 0 8px">Runtime Hooks</h3>
  <?php if (!$hooks): ?>
    <div class="muted">No hooks registered.</div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Type</th><th>Hook Key</th><th>Enabled</th><th>Created</th></tr></thead>
      <tbody>
      <?php foreach ($hooks as $h): ?>
        <tr>
          <td><?= e((string)($h['hook_type'] ?? '')) ?></td>
          <td><?= e((string)($h['hook_key'] ?? '')) ?></td>
          <td><?= ((int)($h['is_enabled'] ?? 0)) === 1 ? 'Yes' : 'No' ?></td>
          <td><?= e((string)($h['created_at'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<div class="card">
  <h3 style="margin:0 0 8px">Lifecycle Log</h3>
  <?php if (!$lifecycleLog): ?>
    <div class="muted">No lifecycle log entries found for this app.</div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Time</th><th>Action</th><th>Context</th></tr></thead>
      <tbody>
      <?php foreach ($lifecycleLog as $entry): ?>
        <tr>
          <td><?= e((string)($entry['ts'] ?? '')) ?></td>
          <td><?= e((string)($entry['action'] ?? '')) ?></td>
          <td><pre style="margin:0;white-space:pre-wrap;word-break:break-word"><?= e(json_encode((array)($entry['context'] ?? []), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}') ?></pre></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

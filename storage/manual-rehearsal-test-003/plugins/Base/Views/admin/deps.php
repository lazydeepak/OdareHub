<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<?php
$appsBoardBase = trim((string)($appsBoardBase ?? '/admin/apps')) ?: '/admin/apps';
$manifest = is_array($manifest ?? null) ? $manifest : [];
$module = is_array($module ?? null) ? $module : [];
$ownerApp = is_array($ownerApp ?? null) ? $ownerApp : [];
$dependencyDetail = is_array($dependencyDetail ?? null) ? $dependencyDetail : [];
$dependencyRows = is_array($dependencyRows ?? null) ? $dependencyRows : ['direct' => [], 'reverse' => []];
$routeDiagnostics = is_array($routeDiagnostics ?? null) ? $routeDiagnostics : [];
$navigationSummary = is_array($navigationSummary ?? null) ? $navigationSummary : [];
$dataOwnership = is_array($dataOwnership ?? null) ? $dataOwnership : [];
$migrationSummary = is_array($migrationSummary ?? null) ? $migrationSummary : [];
$architectureNode = is_array($architectureNode ?? null) ? $architectureNode : [];
$actionState = is_array($actionState ?? null) ? $actionState : [];
$integrityNotes = array_values(array_map('strval', (array)($integrityNotes ?? [])));
$dependencyHealth = is_array($dependencyDetail['dependency_health'] ?? null) ? $dependencyDetail['dependency_health'] : [];
$ownerManifest = is_array($ownerApp['manifest'] ?? null) ? $ownerApp['manifest'] : [];
$ownerName = (string)($ownerApp['display_name'] ?? ($ownerManifest['name'] ?? ($manifest['owner_app'] ?? 'Unknown')));
$installed = (bool)($installed ?? false);
$status = (string)($status ?? 'not-installed');
$statusTone = static function (string $value): string {
    return match ($value) {
        'active', 'configured', 'safe' => 'border-color: var(--color-success-border);background: var(--color-success-bg);color: var(--color-success-text)',
        'inactive', 'installed', 'warning', 'requires_prior_action' => 'border-color: var(--color-warning-border);background: var(--color-warning-bg);color: var(--color-warning-text)',
        'blocked', 'broken', 'error', 'failed', 'not-installed' => 'border-color: var(--color-danger-border);background: var(--color-danger-bg);color: var(--color-danger-text)',
        default => 'border-color: var(--style-border-soft);background: var(--style-subtle-bg);color: var(--text)',
    };
};
$moduleLabel = trim((string)($manifest['display_name'] ?? $name)) ?: (string)$name;
$moduleKey = trim((string)($manifest['module_key'] ?? $name)) ?: (string)$name;
$moduleVersion = trim((string)($manifest['version'] ?? ($module['version'] ?? '')));
$description = trim((string)($manifest['description'] ?? ''));
$archErrors = array_values(array_map('strval', (array)($architectureNode['errors'] ?? [])));
$archWarnings = array_values(array_map('strval', (array)($architectureNode['warnings'] ?? [])));
$setupRun = is_array($module['setup_run'] ?? null) ? $module['setup_run'] : [];
$missingTables = array_values((array)($module['missing_tables'] ?? []));
$schemaGaps = array_values((array)($module['schema_gaps'] ?? []));
$dependencyErrors = array_values(array_map('strval', (array)($module['dependency_errors'] ?? [])));
$ownedTables = array_values(array_map('strval', (array)($dataOwnership['tables'] ?? [])));
$storageObjects = array_values(array_map('strval', (array)($dataOwnership['storage_objects'] ?? [])));
$routeRows = array_values((array)($routeDiagnostics['rows'] ?? []));
$navRows = array_values((array)($navigationSummary['rows'] ?? []));
$purgeState = is_array($actionState['purge'] ?? null) ? $actionState['purge'] : [];
?>

<style>
  .module-detail-grid { display:grid; gap:14px; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); }
  .module-detail-stat { border: 1px solid var(--style-border-soft); border-radius:12px; padding:14px; background: var(--style-subtle-bg); }
  .module-detail-stat .muted { font-size:.86em; }
  .module-detail-list { margin:8px 0 0; padding-left:18px; }
  .module-detail-list li { margin:4px 0; }
  .module-detail-actions { display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; }
  .module-detail-action-card { border: 1px solid var(--style-border-soft); border-radius:12px; padding:14px; background: var(--style-subtle-bg); }
  .module-detail-meta { display:grid; gap:8px; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); }
  .module-detail-meta-row { border-top: 1px solid var(--style-border-soft); padding-top:8px; }
  .module-detail-table { width:100%; border-collapse:collapse; }
  .module-detail-table th, .module-detail-table td { text-align:left; padding:10px 8px; border-bottom: 1px solid var(--style-border-soft); vertical-align:top; }
  .module-detail-danger { border-color: var(--color-danger-border); background: var(--color-danger-bg); }
  .module-detail-note { border-left: 1px solid var(--style-border-soft); padding-left:10px; margin:8px 0; }
</style>

<?php if (!empty($_GET['module_ok'])): ?>
  <div class="card"><div class="muted"> <?= e($tt('base.module_action_complete_label')) ?> <strong><?= e((string)$_GET['module_ok']) ?></strong></div></div>
<?php endif; ?>

<?php if (!empty($_GET['module_err'])): ?>
  <div class="card"><div class="muted u-style-8ec1503168"> <?= e($tt('base.module_action_failed_label')) ?> <strong><?= e((string)$_GET['module_err']) ?></strong></div></div>
<?php endif; ?>

<?php if ($manifest === []): ?>
  <div class="card">
    <h2 class="u-style-759e41d83d"><?= e(t('base.module_detail.not_found')) ?></h2>
    <div class="muted"><?= e(t('base.module_detail.no_manifest_for')) ?> <strong><?= e((string)$name) ?></strong>.</div>
    <div class="row u-style-d6f2af6e0a">
      <a class="btn" href="<?= e($appsBoardBase) ?>"><?= e(t('common.back') ?? 'Back') ?></a>
    </div>
  </div>
<?php else: ?>
  <div class="card">
    <div class="row u-style-8c9cc5d334">
      <div class="ui-block">
        <h2 class="u-style-df671843ff"><?= e($moduleLabel) ?></h2>
        <div class="muted"><?= e(t('base.module_detail.subtitle')) ?> for <strong><?= e((string)$name) ?></strong>.</div>
      </div>
      <div class="row u-style-4725bb7117">
        <span class="pill" style="<?= e($statusTone($installed ? $status : 'not-installed')) ?>"><?= e($installed ? ucfirst(str_replace('-', ' ', $status)) : t('setup.core.no_label')) ?></span>
        <span class="pill" style="<?= e($statusTone((string)($dependencyHealth['status'] ?? 'safe'))) ?>"><?= e(ucfirst(str_replace('_', ' ', (string)($dependencyHealth['status'] ?? 'safe')))) ?></span>
        <span class="pill" style="<?= e($statusTone($archErrors ? 'error' : ($archWarnings ? 'warning' : 'safe'))) ?>"><?= e(t('base.module_detail.architecture')) ?> <?= $archErrors ? e(t('base.db_control.error')) : ($archWarnings ? e(t('setup.core.status_warning')) : e(t('setup.core.status_ok'))) ?></span>
      </div>
    </div>
    <?php if ($description !== ''): ?>
      <div class="muted u-style-d2c171b18b"><?= e($description) ?></div>
    <?php endif; ?>
    <div class="module-detail-meta u-style-d6f2af6e0a">
      <div class="module-detail-meta-row"><strong>Module key</strong><div class="muted"><?= e($moduleKey) ?></div></div>
      <div class="module-detail-meta-row"><strong>Canonical name</strong><div class="muted"><?= e((string)$name) ?></div></div>
      <div class="module-detail-meta-row"><strong>Version</strong><div class="muted"><?= e($moduleVersion !== '' ? $moduleVersion : 'Unknown') ?></div></div>
      <div class="module-detail-meta-row"><strong>Owner bundle/app</strong><div class="muted"><?= e($ownerName) ?><?php if (!empty($manifest['owner_app'])): ?> · <?= e((string)$manifest['owner_app']) ?><?php endif; ?></div></div>
      <div class="module-detail-meta-row"><strong>Package type</strong><div class="muted"><?= e((string)($manifest['package_type'] ?? 'module')) ?></div></div>
      <div class="module-detail-meta-row"><strong>Setup run</strong><div class="muted"><?= e((string)($setupRun['status'] ?? 'No recorded lifecycle run')) ?></div></div>
    </div>
  </div>

  <div class="card">
    <h3 class="u-style-249d010502"><?= e(t('base.module_detail.overview')) ?></h3>
    <div class="module-detail-grid">
      <div class="module-detail-stat">
        <strong><?= e(t('base.module_detail.dependencies')) ?></strong>
        <div class="muted u-style-fe7b4979fe"><?= e((string)($dependencyHealth['summary'] ?? t('base.module_detail.no_dep_analysis'))) ?></div>
        <?php if ($dependencyRows['direct'] === []): ?>
          <div class="muted u-style-8a77e5a311"><?= e(t('base.module_detail.no_direct_deps')) ?></div>
        <?php else: ?>
          <ul class="module-detail-list">
            <?php foreach ($dependencyRows['direct'] as $row): ?>
              <li><strong><?= e((string)$row['display_name']) ?></strong> · <?= e((string)$row['status']) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
      <div class="module-detail-stat">
        <strong><?= e(t('base.module_detail.reverse_deps')) ?></strong>
        <?php if ($dependencyRows['reverse'] === []): ?>
          <div class="muted u-style-8a77e5a311"><?= e(t('base.module_detail.no_reverse_deps')) ?></div>
        <?php else: ?>
          <ul class="module-detail-list">
            <?php foreach ($dependencyRows['reverse'] as $row): ?>
              <li><strong><?= e((string)$row['display_name']) ?></strong> · <?= e((string)$row['status']) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <?php if ($blockedBy !== []): ?>
          <div class="module-detail-note muted"> <?= e($tt('base.dependency_guard_is_label')) ?> <?= e(implode(', ', array_map('strval', $blockedBy))) ?></div>
        <?php endif; ?>
      </div>
      <div class="module-detail-stat">
        <strong><?= e(t('base.module_detail.architecture')) ?></strong>
        <div class="muted u-style-8a77e5a311">Errors: <?= count($archErrors) ?> · Warnings: <?= count($archWarnings) ?></div>
        <?php foreach (array_slice($archErrors, 0, 3) as $message): ?>
          <div class="module-detail-note muted u-style-8ec1503168"><?= e($message) ?></div>
        <?php endforeach; ?>
        <?php foreach (array_slice($archWarnings, 0, 3) as $message): ?>
          <div class="module-detail-note muted u-style-432a0c2ec4"><?= e($message) ?></div>
        <?php endforeach; ?>
        <?php if ($archErrors === [] && $archWarnings === []): ?>
          <div class="muted u-style-8a77e5a311"> <?= e($tt('base.deps_description')) ?> </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="card">
    <h3 class="u-style-249d010502"><?= e(t('base.module_detail.runtime_integrity')) ?></h3>
    <div class="module-detail-grid">
      <div class="module-detail-stat">
        <strong><?= e(t('base.module_detail.routes')) ?></strong>
        <div class="muted u-style-8a77e5a311">Declared: <?= (int)($routeDiagnostics['declared_count'] ?? 0) ?> · Registered now: <?= (int)($routeDiagnostics['registered_count'] ?? 0) ?></div>
        <?php if ($routeRows === []): ?>
          <div class="muted u-style-8a77e5a311"><?= e(t('base.module_detail.no_routes')) ?></div>
        <?php else: ?>
          <ul class="module-detail-list">
            <?php foreach (array_slice($routeRows, 0, 8) as $row): ?>
              <li><?= e((string)($row['path'] ?? '')) ?> · <?= e(implode('/', array_map('strval', (array)($row['methods'] ?? [])))) ?> · <?= !empty($row['registered']) ? e(t('base.module_detail.registered')) : e(t('base.module_detail.not_registered')) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
      <div class="module-detail-stat">
        <strong><?= e(t('base.module_detail.nav_contribution')) ?></strong>
        <div class="muted u-style-8a77e5a311">Matched menu entries: <?= (int)($navigationSummary['count'] ?? 0) ?></div>
        <?php if ($navRows === []): ?>
          <div class="muted u-style-8a77e5a311"><?= e(t('base.module_detail.no_menu_entries')) ?></div>
        <?php else: ?>
          <ul class="module-detail-list">
            <?php foreach ($navRows as $row): ?>
              <li><strong><?= e((string)($row['label'] ?? '')) ?></strong> · <?= e((string)($row['url'] ?? '')) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
      <div class="module-detail-stat">
        <strong><?= e(t('base.module_detail.migration_install')) ?></strong>
        <div class="muted u-style-8a77e5a311">Install script: <?= !empty($migrationSummary['install_script']) ? 'Yes' : 'No' ?> · Uninstall script: <?= !empty($migrationSummary['uninstall_script']) ? 'Yes' : 'No' ?></div>
        <div class="muted u-style-fe7b4979fe">Bootstrap: <?= !empty($migrationSummary['bootstrap_script']) ? 'Yes' : 'No' ?> · Applied migrations: <?= count((array)($migrationSummary['applied'] ?? [])) ?></div>
        <div class="muted u-style-fe7b4979fe"> <?= e($tt('base.pending_migration_files_label')) ?> <?= count((array)($migrationSummary['pending'] ?? [])) ?></div>
      </div>
      <div class="module-detail-stat">
        <strong><?= e(t('base.module_detail.owned_data')) ?></strong>
        <div class="muted u-style-8a77e5a311">Owned tables: <?= count($ownedTables) ?></div>
        <?php if ($ownedTables !== []): ?>
          <ul class="module-detail-list">
            <?php foreach (array_slice($ownedTables, 0, 10) as $table): ?>
              <li><?= e($table) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <div class="muted u-style-8a77e5a311"><?= e(t('base.module_detail.no_owned_tables')) ?></div>
        <?php endif; ?>
        <?php if ($storageObjects !== []): ?>
          <div class="muted u-style-8a77e5a311">Storage objects: <?= e(implode(', ', array_slice($storageObjects, 0, 5))) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($dependencyErrors !== [] || $missingTables !== [] || $schemaGaps !== [] || $integrityNotes !== []): ?>
      <div class="module-detail-grid u-style-d6f2af6e0a">
        <div class="module-detail-stat">
          <strong><?= e(t('base.module_detail.integrity_notes')) ?></strong>
          <ul class="module-detail-list">
            <?php foreach ($integrityNotes as $note): ?>
              <li><?= e($note) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <div class="module-detail-stat">
          <strong><?= e(t('base.module_detail.validation_findings')) ?></strong>
          <div class="muted u-style-8a77e5a311">Runtime dependency issues: <?= count($dependencyErrors) ?> · Missing tables: <?= count($missingTables) ?> · Schema gaps: <?= count($schemaGaps) ?></div>
          <?php foreach (array_slice($dependencyErrors, 0, 4) as $error): ?>
            <div class="module-detail-note muted"><?= e($error) ?></div>
          <?php endforeach; ?>
          <?php foreach (array_slice($schemaGaps, 0, 4) as $gap): ?>
            <div class="module-detail-note muted"><?= e((string)json_encode($gap)) ?></div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3 class="u-style-249d010502"><?= e(t('base.module_detail.lifecycle_actions')) ?></h3>
    <div class="module-detail-grid">
      <div class="module-detail-action-card">
        <strong><?= e(t('base.module_detail.repair_module_title')) ?></strong>
        <div class="muted u-style-6b4b69f47d"><?= e(t('base.module_detail.repair_module_desc')) ?></div>
        <form method="post" action="<?= e($appsBoardBase) ?>/action">
          <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
          <input type="hidden" name="name" value="<?= e((string)$name) ?>">
          <input type="hidden" name="return_to" value="detail">
          <button class="btn ok" type="submit" name="action" value="repair_module" <?= empty($actionState['repair']['enabled']) ? 'disabled' : '' ?>><?= e(t('base.module_detail.btn_repair')) ?></button>
        </form>
      </div>
      <div class="module-detail-action-card">
        <strong><?= e(t('base.module_detail.recover_module_title')) ?></strong>
        <div class="muted u-style-6b4b69f47d"><?= e(t('base.module_detail.recover_module_desc')) ?></div>
        <form method="post" action="<?= e($appsBoardBase) ?>/action">
          <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
          <input type="hidden" name="name" value="<?= e((string)$name) ?>">
          <input type="hidden" name="return_to" value="detail">
          <button class="btn" type="submit" name="action" value="recover_module" <?= empty($actionState['recover']['enabled']) ? 'disabled' : '' ?> onclick="return confirm('<?= e(t('base.module_detail.btn_recover')) ?> <?= e((string)$name) ?>?');"><?= e(t('base.module_detail.btn_recover')) ?></button>
        </form>
      </div>
      <div class="module-detail-action-card">
        <strong><?= e(t('base.module_detail.runtime_participation_title')) ?></strong>
        <div class="muted u-style-6b4b69f47d">Activate or deactivate only. This does not uninstall or purge the module.</div>
        <form method="post" action="<?= e($appsBoardBase) ?>/action" class="module-detail-actions">
          <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
          <input type="hidden" name="name" value="<?= e((string)$name) ?>">
          <input type="hidden" name="return_to" value="detail">
          <?php if (!empty($actionState['activate']['visible'])): ?>
            <button class="btn ok" type="submit" name="action" value="module_activate"><?= e(t('base.module_detail.btn_activate')) ?></button>
          <?php endif; ?>
          <?php if (!empty($actionState['deactivate']['visible'])): ?>
            <button class="btn" type="submit" name="action" value="module_deactivate"><?= e(t('base.module_detail.btn_deactivate')) ?></button>
          <?php endif; ?>
          <?php if (empty($actionState['activate']['visible']) && empty($actionState['deactivate']['visible'])): ?>
            <span class="muted">No runtime toggle is available in the current state.</span>
          <?php endif; ?>
        </form>
      </div>
      <div class="module-detail-action-card">
        <strong><?= e(t('base.module_detail.update_export_title')) ?></strong>
        <div class="muted u-style-6b4b69f47d"> <?= e($tt('base.export_description')) ?> </div>
        <div class="module-detail-actions">
          <?php if (!empty($actionState['update']['visible'])): ?>
            <form method="post" action="<?= e($appsBoardBase) ?>/action">
              <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
              <input type="hidden" name="name" value="<?= e((string)$name) ?>">
              <input type="hidden" name="return_to" value="detail">
              <button class="btn" type="submit" name="action" value="module_update"><?= e(t('base.module_detail.btn_update')) ?></button>
            </form>
          <?php endif; ?>
          <?php if (!empty($actionState['export']['visible'])): ?>
            <a class="btn" href="<?= e($appsBoardBase) ?>/export?name=<?= urlencode((string)$name) ?>"><?= e(t('base.module_detail.btn_export')) ?></a>
          <?php else: ?>
            <span class="muted">Export is not available for this protected/runtime-only module state.</span>
          <?php endif; ?>
        </div>
      </div>
      <div class="module-detail-action-card">
        <strong><?= e(t('base.module_detail.uninstall_title')) ?></strong>
        <div class="muted u-style-6b4b69f47d">Removes runtime registration and participation without purging owned business data.</div>
        <form method="post" action="<?= e($appsBoardBase) ?>/action">
          <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
          <input type="hidden" name="name" value="<?= e((string)$name) ?>">
          <input type="hidden" name="return_to" value="detail">
          <button class="btn danger" type="submit" name="action" value="uninstall_module" <?= empty($actionState['uninstall']['enabled']) ? 'disabled' : '' ?> onclick="return confirm('<?= e(t('base.module_detail.btn_uninstall')) ?> <?= e((string)$name) ?>?');"><?= e(t('base.module_detail.btn_uninstall')) ?></button>
        </form>
        <?php if (!empty($actionState['uninstall']['guard_reason'])): ?>
          <div class="muted u-style-8a77e5a311"><?= e((string)$actionState['uninstall']['guard_reason']) ?></div>
        <?php endif; ?>
        <?php if ($blockedBy !== []): ?>
          <div class="muted u-style-8a77e5a311"> <?= e($tt('base.blocked_by_active_label')) ?> <?= e(implode(', ', array_map('strval', $blockedBy))) ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="card module-detail-danger">
    <h3 class="u-style-249d010502">Purge Module Data</h3>
    <div class="muted"> <?= e($tt('base.deps_active_message')) ?> </div>
    <div class="module-detail-grid u-style-d6f2af6e0a">
      <div class="module-detail-stat module-detail-danger">
        <strong>Safety Gates</strong>
        <ul class="module-detail-list">
          <li>Module must be inactive first.</li>
          <li>Reverse dependency warnings must be cleared first.</li>
          <li>Owned tables/data should be reviewed before confirmation.</li>
          <li>Type the exact module name to confirm.</li>
        </ul>
        <?php if (!empty($purgeState['guard_reason'])): ?>
          <div class="module-detail-note muted u-style-432a0c2ec4"><?= e((string)$purgeState['guard_reason']) ?></div>
        <?php endif; ?>
      </div>
      <div class="module-detail-stat module-detail-danger">
        <strong>Owned Data At Risk</strong>
        <?php if ($ownedTables === []): ?>
          <div class="muted u-style-8a77e5a311">No explicit table ownership could be inferred, so inspect module install/uninstall scripts before purging.</div>
        <?php else: ?>
          <ul class="module-detail-list">
            <?php foreach ($ownedTables as $table): ?>
              <li><?= e($table) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
    <form method="post" action="<?= e($appsBoardBase) ?>/action" style="margin-top:14px">
      <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
      <input type="hidden" name="name" value="<?= e((string)$name) ?>">
      <input type="hidden" name="return_to" value="detail">
      <label class="muted" for="purge-confirm">Type <strong><?= e((string)$name) ?></strong> to confirm purge</label>
      <div class="module-detail-actions u-style-8a77e5a311">
        <input class="input" id="purge-confirm" type="text" name="purge_confirm" value="" placeholder="<?= e((string)$name) ?>">
        <button class="btn danger" type="submit" name="action" value="purge" <?= empty($purgeState['enabled']) ? 'disabled' : '' ?> onclick="return confirm('Final confirmation: purge all governed module data for <?= e((string)$name) ?>?');">Purge Module Data</button>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="row u-style-e9c3b08865">
      <div class="muted">Bundle controls remain on the main Apps Manager board. This surface is only for module-level governance.</div>
      <a class="btn" href="<?= e($appsBoardBase) ?>"><?= e(t('common.back') ?? 'Back') ?></a>
    </div>
  </div>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

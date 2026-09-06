<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$coreStatus = is_array($coreStatus ?? null) ? $coreStatus : [];
$verification = is_array($verification ?? null) ? $verification : [];
$platformProfiles = is_array($platformProfiles ?? null) ? $platformProfiles : [];
$csrf = (string)($csrf ?? '');
$setupRun = is_array($coreStatus['setup_run'] ?? null) ? $coreStatus['setup_run'] : null;
?>
<?php $setupNavCurrent = 'core'; require __DIR__ . '/_nav.php'; ?>
<?php require __DIR__ . '/_flash.php'; ?>

<div class="card">
  <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start">
    <div>
      <h2 style="margin:0 0 6px"><?= e(t('setup.core.title')) ?></h2>
      <div class="muted"><?= e(t('setup.core.subtitle')) ?></div>
    </div>
    <span class="pill" style="<?= e((string)($coreStatus['status_tone'] ?? '')) ?>"><?= e((string)($coreStatus['status_label'] ?? '')) ?></span>
  </div>
  <div style="margin-top:10px"><?= e(t('setup.core.next_action_prefix')) ?> <?= e((string)($coreStatus['next_action'] ?? '')) ?></div>
</div>

<?php if (is_array($setupRun)): ?>
<div class="card">
  <h3 style="margin:0 0 8px"><?= e(t('setup.core.latest_state')) ?></h3>
  <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start">
    <div>
      <div><?= e(t('setup.core.action_label')) ?>: <?= e((string)($setupRun['action_key'] ?? '')) ?></div>
      <div class="muted"><?= e(t('setup.core.started_label')) ?>: <?= e((string)($setupRun['started_at'] ?? '')) ?> · <?= e(t('setup.core.finished_label')) ?>: <?= e((string)($setupRun['finished_at'] ?? '')) ?></div>
      <?php if (!empty($setupRun['failed_step']['step_label'])): ?>
        <div style="margin-top:6px"><?= e(t('setup.core.failed_step')) ?>: <?= e((string)($setupRun['failed_step']['step_label'] ?? '')) ?></div>
      <?php endif; ?>
      <div class="muted" style="margin-top:6px"><?= e(t('setup.core.rollback_performed')) ?>: <?= !empty($setupRun['rollback_performed']) ? e(t('setup.core.yes_label')) : e(t('setup.core.no_label')) ?></div>
      <?php if (trim((string)($setupRun['manual_attention_text'] ?? '')) !== ''): ?>
        <div class="muted" style="margin-top:6px"><?= e(t('setup.core.manual_attention')) ?>: <?= e((string)($setupRun['manual_attention_text'] ?? '')) ?></div>
      <?php endif; ?>
    </div>
    <span class="pill" style="<?= e((string)($setupRun['status_tone'] ?? '')) ?>"><?= e((string)($setupRun['status_label'] ?? '')) ?></span>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
    <form method="post" action="/admin/setup/recover">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="target_type" value="core">
      <input type="hidden" name="target_key" value="core">
      <input type="hidden" name="mode" value="resume">
      <button class="btn ok" type="submit"><?= e(t('setup.core.btn_resume')) ?></button>
    </form>
    <form method="post" action="/admin/setup/recover">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="target_type" value="core">
      <input type="hidden" name="target_key" value="core">
      <input type="hidden" name="mode" value="retry">
      <button class="btn" type="submit"><?= e(t('setup.core.btn_retry')) ?></button>
    </form>
    <a class="btn" href="/admin/setup/audit"><?= e(t('setup.core.open_audit')) ?></a>
  </div>
</div>
<?php endif; ?>

<div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
  <div class="card" style="margin:0">
    <strong><?= e(t('setup.core.db_status')) ?></strong>
    <div class="muted"><?= !empty($coreStatus['preflight']['database']['ok']) ? e(t('setup.core.db_ready')) : e(t('setup.core.db_pending')) ?></div>
    <div style="margin-top:6px"><?= e((string)($coreStatus['preflight']['database']['message'] ?? '')) ?></div>
  </div>
  <div class="card" style="margin:0">
    <strong><?= e(t('setup.core.core_schema')) ?></strong>
    <div class="muted"><?= e((string)($coreStatus['core_schema']['ready'] ?? 0)) ?>/<?= e((string)($coreStatus['core_schema']['total'] ?? 0)) ?> <?= e(t('setup.core.tables_ready')) ?></div>
    <div style="margin-top:6px"><?= e(t('setup.core.missing')) ?>: <?= e((string)($coreStatus['core_schema']['missing'] ?? 0)) ?></div>
  </div>
  <div class="card" style="margin:0">
    <strong><?= e(t('setup.core.core_platform_apps')) ?></strong>
    <div class="muted"><?= e((string)($coreStatus['platform_modules']['healthy'] ?? 0)) ?>/<?= e((string)($coreStatus['platform_modules']['total'] ?? 0)) ?> <?= e(t('setup.core.healthy')) ?></div>
    <div style="margin-top:6px"><?= e(t('setup.core.registry_driven_note')) ?></div>
  </div>
  <div class="card" style="margin:0">
    <strong><?= e(t('setup.core.admin_bootstrap')) ?></strong>
    <div class="muted"><?= e((string)($coreStatus['admin_bootstrap']['label'] ?? t('setup.core.db_pending'))) ?></div>
  </div>
</div>

<div class="card">
  <h3 style="margin:0 0 8px"><?= e(t('setup.core.core_actions')) ?></h3>
  <form method="post" action="/admin/setup/core" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <button class="btn ok" type="submit"><?= e(t('setup.core.btn_repair_bootstrap')) ?></button>
    <a class="btn" href="/setup"><?= e(t('setup.core.open_installer')) ?></a>
  </form>
</div>

<div class="card">
  <h3 style="margin:0 0 8px"><?= e(t('setup.core.checks_title')) ?></h3>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th><?= e(t('setup.core.area_col')) ?></th>
          <th><?= e(t('setup.core.item_col')) ?></th>
          <th><?= e(t('setup.core.status_col')) ?></th>
          <th><?= e(t('setup.core.detail_col')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ((array)($coreStatus['preflight']['environment'] ?? []) as $row): ?>
          <tr>
            <td><?= e(t('setup.core.area_environment')) ?></td>
            <td><?= e((string)($row['label'] ?? '')) ?></td>
            <td><?= !empty($row['ok']) ? e(t('setup.core.status_ok')) : e(t('setup.core.status_warning')) ?></td>
            <td><?= e((string)($row['detail'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php foreach ((array)($coreStatus['preflight']['writable_paths'] ?? []) as $row): ?>
          <tr>
            <td><?= e(t('setup.core.area_writable_path')) ?></td>
            <td><?= e((string)($row['path'] ?? '')) ?></td>
            <td><?= !empty($row['ok']) ? e(t('setup.core.status_writable')) : e(t('setup.core.status_blocked')) ?></td>
            <td><?= e(t('setup.core.check_filesystem')) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php foreach ((array)($coreStatus['core_schema']['tables'] ?? []) as $row): ?>
          <tr>
            <td><?= e(t('setup.core.area_schema')) ?></td>
            <td><?= e((string)($row['table'] ?? '')) ?></td>
            <td><?= !empty($row['exists']) ? e(t('setup.core.status_installed')) : e(t('setup.core.missing')) ?></td>
            <td><?= e(t('setup.core.check_core_table')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap">
    <div>
      <h3 style="margin:0 0 8px"><?= e(t('setup.core.platform_readiness')) ?></h3>
      <div class="muted"><?= e(t('setup.core.readiness_note')) ?></div>
    </div>
    <div class="pill"><?= e((string)($coreStatus['core_platform_apps']['healthy'] ?? 0)) ?>/<?= e((string)($coreStatus['core_platform_apps']['total'] ?? 0)) ?> <?= e(t('setup.core.healthy')) ?></div>
  </div>
  <div class="table-wrap" style="margin-top:12px">
    <table>
      <thead>
        <tr>
          <th><?= e(t('setup.core.module_col')) ?></th>
          <th><?= e(t('setup.core.installed_col')) ?></th>
          <th><?= e(t('setup.core.schema_col')) ?></th>
          <th><?= e(t('setup.core.active_col')) ?></th>
          <th><?= e(t('setup.core.healthy_col')) ?></th>
          <th><?= e(t('setup.core.action_col')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ((array)($coreStatus['core_platform_apps']['rows'] ?? []) as $row): ?>
          <?php $action = is_array($row['next_action'] ?? null) ? (array)$row['next_action'] : []; ?>
          <tr>
            <td>
              <strong><?= e((string)($row['display_name'] ?? $row['name'] ?? '')) ?></strong>
              <?php if (!empty($row['name'])): ?>
                <div class="muted" style="margin-top:4px"><?= e((string)$row['name']) ?></div>
              <?php endif; ?>
            </td>
            <td><?= !empty($row['installed']) ? e(t('setup.core.yes_label')) : e(t('setup.core.no_label')) ?></td>
            <td><?= !empty($row['schema_synced']) ? e(t('setup.core.synced')) : e(t('setup.core.needs_sync')) ?></td>
            <td><?= !empty($row['active']) ? e(t('base.db_control.active')) : e(t('base.db_control.inactive')) ?></td>
            <td>
              <?php if (!empty($row['success_tick'])): ?>
                <span class="pill" style="color:#6df2a6">&#10003; <?= e(t('setup.core.healthy_label')) ?></span>
              <?php else: ?>
                <span class="pill"><?= e((string)($row['healthy_label'] ?? t('setup.core.needs_attention'))) ?></span>
                <div class="muted" style="margin-top:4px">
                  <?= e(t('setup.core.missing_tables')) ?>: <?= (int)($row['missing_tables'] ?? 0) ?>
                  · <?= e(t('setup.core.schema_gaps')) ?>: <?= (int)($row['schema_gaps'] ?? 0) ?>
                  · <?= e(t('setup.core.dependency_errors')) ?>: <?= (int)($row['dependency_errors'] ?? 0) ?>
                </div>
              <?php endif; ?>
            </td>
            <td>
              <?php if (!empty($action['url']) && !empty($action['operation'])): ?>
                <form method="post" action="<?= e((string)$action['url']) ?>" style="display:inline-flex;gap:8px;align-items:center;flex-wrap:wrap">
                  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="module_name" value="<?= e((string)($row['name'] ?? '')) ?>">
                  <input type="hidden" name="operation" value="<?= e((string)$action['operation']) ?>">
                  <button class="btn" type="submit"><?= e((string)($action['label'] ?? t('setup.core.repair'))) ?></button>
                </form>
              <?php else: ?>
                <span class="pill" style="color:#6df2a6">&#10003; <?= e(t('setup.core.ready_label')) ?></span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <h3 style="margin:0 0 8px"><?= e(t('setup.core.profiles_title')) ?></h3>
  <div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
    <?php foreach ($platformProfiles as $profile): ?>
      <form method="post" action="/admin/setup/profile" class="card" style="margin:0">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="profile_key" value="<?= e((string)($profile['key'] ?? '')) ?>">
        <strong><?= e((string)($profile['label'] ?? '')) ?></strong>
        <div class="muted" style="margin:6px 0 10px"><?= e((string)($profile['description'] ?? '')) ?></div>
        <button class="btn ok" type="submit"><?= e(t('common.apply')) ?></button>
      </form>
    <?php endforeach; ?>
  </div>
</div>

<div class="card">
  <h3 style="margin:0 0 8px"><?= e(t('setup.core.verification_title')) ?></h3>
  <div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-bottom:12px">
    <div class="card" style="margin:0"><strong><?= e(t('setup.core.missing_tables')) ?></strong><div class="muted"><?= e((string)count((array)($verification['missing_tables'] ?? []))) ?></div></div>
    <div class="card" style="margin:0"><strong><?= e(t('setup.core.failed_hooks')) ?></strong><div class="muted"><?= e((string)count((array)($verification['failed_hooks'] ?? []))) ?></div></div>
    <div class="card" style="margin:0"><strong><?= e(t('setup.core.schema_gaps')) ?></strong><div class="muted"><?= e((string)count((array)($verification['schema_gaps'] ?? []))) ?></div></div>
    <div class="card" style="margin:0"><strong><?= e(t('setup.core.warnings_errors')) ?></strong><div class="muted"><?= e((string)count((array)($verification['warnings'] ?? []))) ?> / <?= e((string)count((array)($verification['errors'] ?? []))) ?></div></div>
  </div>
  <pre style="white-space:pre-wrap;margin:0"><?= e(json_encode($verification, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}') ?></pre>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

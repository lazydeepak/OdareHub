<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$apps = is_array($apps ?? null) ? $apps : [];
$pluginStatusMap = is_array($pluginStatusMap ?? null) ? $pluginStatusMap : [];
$appDependencyImpacts = is_array($appDependencyImpacts ?? null) ? $appDependencyImpacts : [];
$appVersionInfo = is_array($appVersionInfo ?? null) ? $appVersionInfo : [];
$csrf = (string)($csrf ?? '');
$flashOk = trim((string)($flash_ok ?? ''));
$flashErr = trim((string)($flash_err ?? ''));
$impactTone = static function (string $status): string {
    return match ($status) {
        'safe' => 'app-manager-tone--safe',
        'warning' => 'app-manager-tone--warning',
        'blocked' => 'app-manager-tone--blocked',
        'requires_prior_action' => 'app-manager-tone--action',
        default => 'app-manager-tone--neutral',
    };
};
$statusTone = static function (string $status): string {
    return match ($status) {
        'enabled', 'installed' => 'app-manager-tone--safe',
        'disabled' => 'app-manager-tone--warning',
        'broken' => 'app-manager-tone--blocked',
        'upgrade_pending' => 'app-manager-tone--action',
        default => 'app-manager-tone--neutral',
    };
};
$statusLabel = static function (string $status): string {
    $known = ['uploaded', 'installed', 'enabled', 'disabled', 'broken', 'upgrade_pending', 'uninstalled', 'purged'];
    return in_array($status, $known, true) ? t('app_manager.status.' . $status) : $status;
};
?>

<div class="card">
  <h2 class="app-manager-title"><?= e(t('app_manager.title')) ?></h2>
  <div class="muted"><?= e(t('app_manager.subtitle')) ?></div>
</div>

<?php if ($flashOk !== ''): ?>
<div class="card app-manager-flash app-manager-flash--success">
  <div><?= e($flashOk) ?></div>
</div>
<?php endif; ?>

<?php if ($flashErr !== ''): ?>
<div class="card app-manager-flash app-manager-flash--error">
  <div><?= e($flashErr) ?></div>
</div>
<?php endif; ?>

<div class="card">
  <h3 class="app-manager-title"><?= e(t('app_manager.upload_title')) ?></h3>
  <div class="muted app-manager-copy"><?= e(t('app_manager.upload_note')) ?> <?= e(t('app_manager.module_upload_note')) ?> <a href="/admin/apps"><?= e(t('app_manager.apps_workspace')) ?></a>.</div>
  <form method="post" action="/admin/app-manager/upload" enctype="multipart/form-data" class="app-manager-actions">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="file" name="zip" accept=".zip" required>
    <button class="btn ok" type="submit"><?= e(t('app_manager.btn_upload')) ?></button>
  </form>
</div>

<div class="card">
  <h3 class="app-manager-title"><?= e(t('app_manager.registered_apps')) ?></h3>
  <?php if (!$apps): ?>
    <div class="muted"><?= e(t('app_manager.no_apps')) ?></div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th><?= e(t('app_manager.app_key')) ?></th>
            <th><?= e(t('app_manager.name')) ?></th>
            <th><?= e(t('app_manager.type')) ?></th>
            <th><?= e(t('app_manager.version')) ?></th>
            <th><?= e(t('app_manager.status')) ?></th>
            <th><?= e(t('app_manager.updated')) ?></th>
            <th><?= e(t('app_manager.actions')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($apps as $app): ?>
            <?php
              $key = (string)($app['app_key'] ?? '');
              $status = (string)($app['status'] ?? 'uploaded');
              $versionInfo = is_array($appVersionInfo[$key] ?? null) ? $appVersionInfo[$key] : [];
              $impactMap = is_array($appDependencyImpacts[$key] ?? null) ? $appDependencyImpacts[$key] : [];
              $manifest = json_decode((string)($app['manifest_json'] ?? '{}'), true);
              $canExport = is_array($manifest) ? (bool)($manifest['can_export'] ?? false) : false;
              $isInstalledState = in_array($status, ['installed','enabled','disabled','upgrade_pending','broken'], true);
              $legacyBridgePlugins = is_array($manifest) && is_array($manifest['legacy_bridge_plugins'] ?? null)
                  ? array_values(array_filter(array_map('strval', (array)$manifest['legacy_bridge_plugins'])))
                  : [];
              $bundleInstalledCount = 0;
              $bundleActiveCount = 0;
              foreach ($legacyBridgePlugins as $pluginName) {
                  if (!isset($pluginStatusMap[$pluginName])) {
                      continue;
                  }
                  $bundleInstalledCount++;
                  if ((string)($pluginStatusMap[$pluginName]['status'] ?? '') === 'active') {
                      $bundleActiveCount++;
                  }
              }
            ?>
            <tr>
              <td><?= e($key) ?></td>
              <td><?= e((string)($app['app_name'] ?? '')) ?></td>
              <td><?= e((string)($app['app_type'] ?? '')) ?></td>
              <td>
                <div><?= e((string)($versionInfo['current_version'] ?? ($app['version'] ?? ''))) ?></div>
                <div class="muted app-manager-meta">
                  <?= e(t('app_manager.target_version')) ?> <?= e((string)($versionInfo['target_version'] ?? ($app['version'] ?? ''))) ?>
                  <?php if (!empty($versionInfo['upgrade_available'])): ?> · <?= e(t('app_manager.upgrade_available')) ?><?php endif; ?>
                </div>
              </td>
              <td><span class="pill <?= e($statusTone($status)) ?>"><?= e($statusLabel($status)) ?></span></td>
              <td><?= e((string)($app['updated_at'] ?? '')) ?></td>
              <td>
                <?php if ($legacyBridgePlugins !== []): ?>
                  <div class="muted app-manager-meta app-manager-meta--spaced">
                    <?= e(t('app_manager.bundle_count')) ?>: <?= e((string)count($legacyBridgePlugins)) ?> <?= e(t('app_manager.child_modules')) ?>
                    · <?= e(t('app_manager.installed_count')) ?> <?= e((string)$bundleInstalledCount) ?>/<?= e((string)count($legacyBridgePlugins)) ?>
                    · <?= e(t('app_manager.active_count')) ?> <?= e((string)$bundleActiveCount) ?>/<?= e((string)count($legacyBridgePlugins)) ?>
                  </div>
                  <div class="muted app-manager-meta app-manager-meta--spaced">
                    <?= e(implode(', ', $legacyBridgePlugins)) ?>
                  </div>
                <?php endif; ?>
                <?php if (!empty($versionInfo['latest_entry']['summary'])): ?>
                  <div class="muted app-manager-meta app-manager-meta--spaced">
                    <?= e(t('app_manager.changelog')) ?>: <?= e((string)($versionInfo['latest_entry']['summary'] ?? '')) ?>
                  </div>
                <?php endif; ?>
                <?php foreach (['disable' => t('app_manager.btn_disable'), 'uninstall' => t('app_manager.btn_soft_uninstall'), 'purge' => t('app_manager.btn_purge'), 'restore' => t('app_manager.btn_restore')] as $impactKey => $impactLabel): ?>
                  <?php $impact = is_array($impactMap[$impactKey] ?? null) ? $impactMap[$impactKey] : []; ?>
                  <?php if ($impact !== []): ?>
                    <div class="muted app-manager-meta app-manager-impact">
                      <?= e($impactLabel) ?>:
                      <span class="pill <?= e($impactTone((string)($impact['status'] ?? 'safe'))) ?>"><?= e((string)($impact['label'] ?? t('app_manager.impact_safe'))) ?></span>
                      <?= e((string)($impact['summary'] ?? '')) ?>
                    </div>
                  <?php endif; ?>
                <?php endforeach; ?>
                <form method="post" action="/admin/app-manager/action" class="app-manager-actions">
                  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="app_key" value="<?= e($key) ?>">
                  <?php if (in_array($status, ['uploaded','upgrade_pending','uninstalled'], true)): ?>
                    <button class="btn" type="submit" name="action" value="install"><?= e(t('app_manager.btn_install')) ?></button>
                  <?php endif; ?>
                  <?php if (in_array($status, ['installed','disabled'], true)): ?>
                    <button class="btn ok" type="submit" name="action" value="enable"><?= e(t('app_manager.btn_enable')) ?></button>
                  <?php endif; ?>
                  <?php if ($status === 'enabled'): ?>
                    <button class="btn" type="submit" name="action" value="disable"><?= e(t('app_manager.btn_disable')) ?></button>
                  <?php endif; ?>
                  <?php if (in_array($status, ['installed','enabled','disabled','broken','upgrade_pending'], true)): ?>
                    <button class="btn" type="submit" name="action" value="repair"><?= e(t('app_manager.btn_repair')) ?><?= $legacyBridgePlugins !== [] ? ' ' . e(t('app_manager.bundle_count')) : '' ?></button>
                  <?php endif; ?>
                  <?php if (in_array($status, ['broken','upgrade_pending'], true)): ?>
                    <button class="btn" type="submit" name="action" value="recover"><?= e(t('app_manager.btn_recover')) ?></button>
                  <?php endif; ?>
                  <?php if (in_array($status, ['installed','disabled','broken','upgrade_pending'], true)): ?>
                    <button class="btn" type="submit" name="action" value="uninstall" onclick="return confirm('<?= e(t('app_manager.btn_soft_uninstall')) ?>?');"><?= e(t('app_manager.btn_soft_uninstall')) ?></button>
                  <?php endif; ?>
                  <?php if (in_array($status, ['installed','disabled','broken','upgrade_pending','uninstalled'], true)): ?>
                    <button class="btn" type="submit" name="action" value="purge" onclick="return confirm('<?= e(t('app_manager.btn_purge')) ?>?');"><?= e(t('app_manager.btn_purge')) ?></button>
                  <?php endif; ?>
                  <button class="btn" type="submit" name="action" value="upgrade_pending"><?= e(t('app_manager.btn_mark_upgrade')) ?></button>
                  <a class="btn" href="/admin/app-manager/detail?app_key=<?= urlencode($key) ?>"><?= e(t('common.open')) ?></a>
                </form>
                <div class="app-manager-actions app-manager-export-actions">
                  <?php if ($canExport && $isInstalledState): ?>
                    <a class="btn ok" href="/admin/app-manager/export?app_key=<?= urlencode($key) ?>"><?= e(t('app_manager.btn_export')) ?> ZIP</a>
                  <?php elseif (!$canExport): ?>
                    <span class="muted"><?= e(t('app_manager.export_disabled')) ?></span>
                  <?php else: ?>
                    <span class="muted"><?= e(t('app_manager.install_to_export')) ?></span>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

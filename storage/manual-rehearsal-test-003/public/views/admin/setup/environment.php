<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$scopeOptions = is_array($scopeOptions ?? null) ? $scopeOptions : [];
$currentEnvironment = is_array($currentEnvironment ?? null) ? $currentEnvironment : [];
$history = is_array($history ?? null) ? $history : [];
$previewPayload = is_array($preview ?? null) ? $preview : [];
$preview = is_array($previewPayload['preview'] ?? null) ? $previewPayload['preview'] : [];
$availableScopes = array_values(array_map('strval', (array)($preview['available_apply_scopes'] ?? [])));
$defaultApplyScope = in_array('core_suites_data', $availableScopes, true)
    ? 'core_suites_data'
    : ($availableScopes[0] ?? 'metadata_only');
$scopeLabel = static function (string $key, string $fallback = ''): string {
    $known = ['metadata_only', 'config_only', 'core_only', 'core_suites', 'core_suites_data'];
    return in_array($key, $known, true) ? t('admin.setup_environment.scope.' . $key) : ($fallback !== '' ? $fallback : $key);
};
$itemTypeLabel = static function (string $type): string {
    $known = ['app', 'core', 'suite', 'module', 'plugin'];
    return in_array($type, $known, true) ? t('admin.setup_environment.type.' . $type) : $type;
};
$operationLabel = static function (string $operation): string {
    $known = ['snapshot_create', 'clone_preview', 'clone_apply'];
    return in_array($operation, $known, true) ? t('admin.setup_environment.operation.' . $operation) : $operation;
};
$statusLabel = static function (string $status): string {
    $known = ['created', 'previewed', 'applied', 'failed', 'cancelled'];
    return in_array($status, $known, true) ? t('admin.setup_environment.status.' . $status) : $status;
};
$joinedOrNone = static function (array $values, string $separator = ', '): string {
    $joined = implode($separator, array_map('strval', $values));
    return $joined !== '' ? $joined : t('admin.setup_environment.none');
};
?>
<?php $setupNavCurrent = 'environment'; require __DIR__ . '/_nav.php'; ?>
<?php require __DIR__ . '/_flash.php'; ?>

<div class="card">
  <h2 class="setup-environment-title"><?= e(t('admin.setup_environment.title')) ?></h2>
  <div class="muted"><?= e(t('admin.setup_environment.description')) ?></div>
</div>

<div class="setup-environment-grid setup-environment-grid--summary">
  <div class="card setup-environment-card">
    <div class="muted"><?= e(t('admin.setup_environment.current')) ?></div>
    <strong><?= e((string)($currentEnvironment['name'] ?? t('admin.setup_environment.unknown'))) ?></strong>
    <div class="muted setup-environment-detail"><?= e(t('app.name')) ?> <?= e((string)($currentEnvironment['app_version'] ?? '')) ?></div>
    <div class="muted"><?= e(t('admin.setup_environment.php')) ?> <?= e((string)($currentEnvironment['php_version'] ?? '')) ?></div>
    <div class="muted"><?= e(t('admin.setup_environment.database')) ?> <?= e((string)($currentEnvironment['database_name'] ?? '')) ?></div>
  </div>
  <div class="card setup-environment-card">
    <div class="muted"><?= e(t('admin.setup_environment.portable_scopes')) ?></div>
    <?php foreach ($scopeOptions as $key => $label): ?>
      <div class="muted setup-environment-detail"><?= e($scopeLabel((string)$key, (string)$label)) ?></div>
    <?php endforeach; ?>
  </div>
</div>

<div class="setup-environment-grid setup-environment-grid--actions">
  <div class="card setup-environment-card">
    <h3 class="setup-environment-title"><?= e(t('admin.setup_environment.create_title')) ?></h3>
    <div class="muted setup-environment-copy"><?= e(t('admin.setup_environment.create_description')) ?></div>
    <form method="post" action="/admin/setup/environment/snapshot" class="control-row">
      <input type="hidden" name="csrf" value="<?= e((string)($csrf ?? '')) ?>">
      <label class="control-field-compact">
        <span class="control-label"><?= e(t('admin.setup_environment.snapshot_scope')) ?></span>
        <select name="scope_key">
          <?php foreach ($scopeOptions as $key => $label): ?>
            <option value="<?= e((string)$key) ?>"><?= e($scopeLabel((string)$key, (string)$label)) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <div class="control-actions"><button class="btn ok" type="submit"><?= e(t('admin.setup_environment.create_action')) ?></button></div>
    </form>
  </div>

  <div class="card setup-environment-card">
    <h3 class="setup-environment-title"><?= e(t('admin.setup_environment.preview_title')) ?></h3>
    <div class="muted setup-environment-copy"><?= e(t('admin.setup_environment.preview_description')) ?></div>
    <form method="post" action="/admin/setup/environment/preview" enctype="multipart/form-data" class="control-row">
      <input type="hidden" name="csrf" value="<?= e((string)($csrf ?? '')) ?>">
      <label class="control-field">
        <span class="control-label"><?= e(t('admin.setup_environment.snapshot_file')) ?></span>
        <input type="file" name="snapshot_file" accept=".zip,.json" required>
      </label>
      <div class="control-actions"><button class="btn" type="submit"><?= e(t('admin.setup_environment.preview_action')) ?></button></div>
    </form>
  </div>
</div>

<?php if ($preview !== []): ?>
  <div class="card setup-environment-review">
    <div class="setup-environment-head">
      <div>
        <h3 class="setup-environment-title"><?= e(t('admin.setup_environment.verification_title')) ?></h3>
        <div class="muted"><?= e(t('admin.setup_environment.verification_description')) ?></div>
      </div>
      <span class="pill"><?= e($scopeLabel((string)($preview['scope_key'] ?? ''), (string)($preview['scope_label'] ?? ''))) ?></span>
    </div>

    <div class="setup-environment-grid setup-environment-grid--verification">
      <?php foreach (['source', 'target'] as $side): ?>
        <?php $environment = is_array($preview[$side . '_environment'] ?? null) ? $preview[$side . '_environment'] : []; ?>
        <div class="card setup-environment-card">
          <h4 class="setup-environment-title"><?= e(t('admin.setup_environment.' . $side)) ?></h4>
          <div class="muted"><?= e((string)($environment['name'] ?? '')) ?></div>
          <div class="muted"><?= e(t('app.name')) ?> <?= e((string)($environment['app_version'] ?? '')) ?></div>
          <div class="muted"><?= e(t('admin.setup_environment.database')) ?> <?= e((string)($environment['database_name'] ?? '')) ?></div>
        </div>
      <?php endforeach; ?>
      <div class="card setup-environment-card">
        <h4 class="setup-environment-title"><?= e(t('admin.setup_environment.summary')) ?></h4>
        <div class="muted"><?= e(t('admin.setup_environment.warnings')) ?>: <?= (int)($preview['conflict_summary']['warning_count'] ?? 0) ?></div>
        <div class="muted"><?= e(t('admin.setup_environment.errors')) ?>: <?= (int)($preview['conflict_summary']['error_count'] ?? 0) ?></div>
        <div class="muted"><?= e(t('admin.setup_environment.config_differences')) ?>: <?= (int)($preview['conflict_summary']['config_difference_count'] ?? 0) ?></div>
        <div class="muted"><?= e(t('admin.setup_environment.schema_drift')) ?>: <?= (int)($preview['conflict_summary']['schema_drift_count'] ?? 0) ?></div>
      </div>
    </div>

    <div class="setup-environment-grid setup-environment-grid--findings">
      <div class="card setup-environment-card">
        <h4 class="setup-environment-title"><?= e(t('admin.setup_environment.version_differences')) ?></h4>
        <?php if (!empty($preview['version_differences'])): ?>
          <?php foreach ((array)$preview['version_differences'] as $row): ?>
            <div class="muted setup-environment-finding"><?= e($itemTypeLabel((string)($row['type'] ?? ''))) ?> <?= e((string)($row['name'] ?? '')) ?>: <?= e(t('admin.setup_environment.source_lower')) ?> <?= e((string)($row['source_version'] ?? '')) ?> / <?= e(t('admin.setup_environment.target_lower')) ?> <?= e((string)($row['target_version'] ?? '')) ?></div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="muted"><?= e(t('admin.setup_environment.no_version_differences')) ?></div>
        <?php endif; ?>
      </div>
      <div class="card setup-environment-card">
        <h4 class="setup-environment-title"><?= e(t('admin.setup_environment.missing_dependencies')) ?></h4>
        <div class="muted"><?= e(t('admin.setup_environment.suites')) ?>: <?= e($joinedOrNone((array)($preview['missing_suites'] ?? []))) ?></div>
        <div class="muted setup-environment-detail"><?= e(t('admin.setup_environment.modules')) ?>: <?= e($joinedOrNone((array)($preview['missing_modules'] ?? []))) ?></div>
      </div>
      <div class="card setup-environment-card">
        <h4 class="setup-environment-title"><?= e(t('admin.setup_environment.schema_risk')) ?></h4>
        <div class="muted"><?= e(t('admin.setup_environment.schema_drift')) ?>: <?= e($joinedOrNone((array)($preview['schema_drift'] ?? []), ' | ')) ?></div>
        <div class="muted setup-environment-detail"><?= e(t('admin.setup_environment.overwrite_risk')) ?>: <?= e($joinedOrNone((array)($preview['overwrite_risks'] ?? []), ' | ')) ?></div>
      </div>
    </div>

    <div class="card setup-environment-card setup-environment-section">
      <h4 class="setup-environment-title"><?= e(t('admin.setup_environment.config_differences')) ?></h4>
      <?php if (!empty($preview['config_differences'])): ?>
        <div class="table-wrap"><table><thead><tr>
          <th><?= e(t('admin.setup_environment.setting')) ?></th>
          <th><?= e(t('admin.setup_environment.source')) ?></th>
          <th><?= e(t('admin.setup_environment.target')) ?></th>
        </tr></thead><tbody>
          <?php foreach ((array)$preview['config_differences'] as $row): ?><tr>
            <td><?= e((string)($row['key'] ?? '')) ?></td><td><?= e((string)($row['source_value'] ?? '')) ?></td><td><?= e((string)($row['target_value'] ?? '')) ?></td>
          </tr><?php endforeach; ?>
        </tbody></table></div>
      <?php else: ?>
        <div class="muted"><?= e(t('admin.setup_environment.no_config_differences')) ?></div>
      <?php endif; ?>
    </div>

    <div class="setup-environment-apply">
      <div class="setup-environment-warning"><strong><?= e(t('admin.setup_environment.apply_gate_title')) ?></strong><div><?= e(t('admin.setup_environment.apply_gate_description')) ?></div></div>
      <form method="post" action="/admin/setup/environment/apply">
        <input type="hidden" name="csrf" value="<?= e((string)($csrf ?? '')) ?>">
        <input type="hidden" name="preview_token" value="<?= e((string)($previewPayload['preview_token'] ?? '')) ?>">
        <div class="control-row">
          <label class="control-field-compact"><span class="control-label"><?= e(t('admin.setup_environment.apply_scope')) ?></span><select name="apply_scope">
            <?php foreach ($availableScopes as $scope): ?><option value="<?= e($scope) ?>" <?= $scope === $defaultApplyScope ? 'selected' : '' ?>><?= e($scopeLabel($scope, (string)($scopeOptions[$scope] ?? $scope))) ?></option><?php endforeach; ?>
          </select></label>
          <label class="control-field-compact"><span class="control-label"><?= e(t('admin.setup_environment.conflict_handling')) ?></span><select name="conflict_strategy">
            <option value="merge"><?= e(t('admin.setup_environment.strategy.merge')) ?></option><option value="overwrite"><?= e(t('admin.setup_environment.strategy.overwrite')) ?></option>
          </select></label>
          <div class="control-field-compact"><span class="control-label"><?= e(t('admin.setup_environment.dependencies')) ?></span><label class="checkbox-label"><input type="checkbox" name="install_missing_dependencies" value="1"><span><?= e(t('admin.setup_environment.install_dependencies')) ?></span></label></div>
        </div>
        <div class="muted setup-environment-copy"><?= e(t('admin.setup_environment.apply_note')) ?></div>
        <div class="control-actions"><button class="btn ok" type="submit"><?= e(t('admin.setup_environment.apply_action')) ?></button></div>
      </form>
      <form method="post" action="/admin/setup/environment/cancel" class="setup-environment-cancel">
        <input type="hidden" name="csrf" value="<?= e((string)($csrf ?? '')) ?>"><input type="hidden" name="preview_token" value="<?= e((string)($previewPayload['preview_token'] ?? '')) ?>">
        <button class="btn" type="submit"><?= e(t('admin.setup_environment.cancel_action')) ?></button>
      </form>
    </div>
  </div>
<?php endif; ?>

<div class="card">
  <h3 class="setup-environment-title"><?= e(t('admin.setup_environment.history_title')) ?></h3>
  <div class="muted setup-environment-copy"><?= e(t('admin.setup_environment.history_description')) ?></div>
  <div class="table-wrap"><table><thead><tr>
    <th><?= e(t('admin.setup_environment.when')) ?></th><th><?= e(t('admin.setup_environment.operation')) ?></th><th><?= e(t('admin.setup_environment.scope')) ?></th><th><?= e(t('admin.setup_environment.source_target')) ?></th><th><?= e(t('admin.setup_environment.status')) ?></th><th><?= e(t('admin.setup_environment.file')) ?></th><th><?= e(t('admin.setup_environment.user')) ?></th>
  </tr></thead><tbody>
    <?php foreach ($history as $row): ?>
      <?php $status = trim((string)($row['status'] ?? 'created')); $operation = (string)($row['operation_type'] ?? ''); ?>
      <tr>
        <td><?= e((string)($row['created_at'] ?? '')) ?></td><td><?= e($operationLabel($operation)) ?></td><td><?= e($scopeLabel((string)($row['scope_key'] ?? ''))) ?></td>
        <td><?= e((string)($row['source_environment'] ?? '')) ?><?php if (!empty($row['target_environment'])): ?> → <?= e((string)$row['target_environment']) ?><?php endif; ?></td><td><?= e($statusLabel($status)) ?></td>
        <td><?php if ($operation === 'snapshot_create' && !empty($row['snapshot_file_name'])): ?><a href="/admin/setup/environment/download?id=<?= (int)($row['id'] ?? 0) ?>"><?= e((string)$row['snapshot_file_name']) ?></a><?php else: ?><span class="muted"><?= e((string)($row['snapshot_file_name'] ?? t('admin.setup_environment.not_applicable'))) ?></span><?php endif; ?></td>
        <td><?= e((string)($row['created_by'] ?? '')) ?></td>
      </tr>
      <?php if (!empty($row['warning_text']) || !empty($row['error_text'])): ?><tr><td colspan="7" class="muted"><?= e(trim((string)($row['warning_text'] ?? '') . ' ' . (string)($row['error_text'] ?? ''))) ?></td></tr><?php endif; ?>
    <?php endforeach; ?>
    <?php if ($history === []): ?><tr><td colspan="7" class="muted"><?= e(t('admin.setup_environment.no_history')) ?></td></tr><?php endif; ?>
  </tbody></table></div>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

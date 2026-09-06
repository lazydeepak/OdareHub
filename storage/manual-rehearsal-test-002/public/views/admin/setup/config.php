<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$configDashboard = is_array($configDashboard ?? null) ? $configDashboard : [];
$environmentCards = is_array($configDashboard['environment_cards'] ?? null) ? $configDashboard['environment_cards'] : [];
$selectedEnvironment = (string)($configDashboard['selected_environment'] ?? 'local');
$currentEnvironment = (string)($configDashboard['current_environment'] ?? 'local');
$selectedLayerJson = (string)($configDashboard['selected_layer_json'] ?? '{}');
$selectedResolved = is_array($configDashboard['selected_resolved'] ?? null) ? $configDashboard['selected_resolved'] : [];
$selectedValidation = is_array($configDashboard['selected_validation'] ?? null) ? $configDashboard['selected_validation'] : [];
$globalValidation = is_array($configDashboard['global_validation'] ?? null) ? $configDashboard['global_validation'] : [];
$dbConfig = is_array($configDashboard['db_config'] ?? null) ? $configDashboard['db_config'] : [];
$dbConfigStatus = is_array($configDashboard['db_config_status'] ?? null) ? $configDashboard['db_config_status'] : [];
$baseSettings = is_array($configDashboard['base_settings'] ?? null) ? $configDashboard['base_settings'] : [];
$csrf = (string)($csrf ?? '');
$environmentLabel = static function (string $environment): string {
    $known = ['local', 'staging', 'production'];
    return in_array($environment, $known, true) ? t('admin.setup_config.environment.' . $environment) : ucfirst($environment);
};
$safeConfigValue = static function (string $key, mixed $value): string {
    if (preg_match('/password|passwd|secret|token|credential|(?:private|api|access|encryption)[_-]?key/i', $key) === 1) {
        return t('admin.setup_config.masked');
    }
    return is_scalar($value) ? (string)$value : '';
};
?>
<?php $setupNavCurrent = 'config'; require __DIR__ . '/_nav.php'; ?>
<?php require __DIR__ . '/_flash.php'; ?>

<div class="card">
  <h2 class="setup-config-title"><?= e(t('admin.setup_config.title')) ?></h2>
  <div class="muted"><?= e(t('admin.setup_config.description')) ?></div>
</div>

<div class="setup-config-grid setup-config-grid--summary">
  <div class="card setup-config-card"><div class="muted"><?= e(t('admin.setup_config.current_runtime')) ?></div><strong><?= e($environmentLabel($currentEnvironment)) ?></strong><div class="muted setup-config-detail"><?= e(t('admin.setup_config.selected_layer')) ?>: <?= e($environmentLabel($selectedEnvironment)) ?></div></div>
  <div class="card setup-config-card"><div class="muted"><?= e(t('admin.setup_config.base_settings')) ?></div><strong><?= e((string)count($baseSettings)) ?> <?= e(t('admin.setup_config.keys')) ?></strong><div class="muted setup-config-detail"><?= e(t('admin.setup_config.base_settings_description')) ?></div></div>
  <div class="card setup-config-card"><div class="muted"><?= e(t('admin.setup_config.db_config')) ?></div><strong class="<?= empty($dbConfigStatus['errors']) ? 'setup-config-status--ready' : 'setup-config-status--error' ?>"><?= e(t(empty($dbConfigStatus['errors']) ? 'admin.setup_config.ready' : 'admin.setup_config.needs_attention')) ?></strong><div class="muted setup-config-detail"><?= e(t('admin.setup_config.db_source_description')) ?></div></div>
</div>

<div class="card">
  <h3 class="setup-config-title"><?= e(t('admin.setup_config.visibility_title')) ?></h3>
  <div class="muted setup-config-copy"><?= e(t('admin.setup_config.visibility_description')) ?></div>
  <div class="setup-config-grid setup-config-grid--environments">
    <?php foreach ($environmentCards as $environment => $card): ?>
      <div class="card setup-config-card<?= !empty($card['is_current']) ? ' setup-config-card--current' : '' ?>">
        <div class="setup-config-head"><strong><?= e($environmentLabel((string)$environment)) ?></strong><?php if (!empty($card['is_current'])): ?><span class="pill setup-config-status--ready"><?= e(t('admin.setup_config.current')) ?></span><?php endif; ?></div>
        <div class="muted setup-config-detail"><?= e(t('admin.setup_config.layer_file')) ?>: <?= e(t(!empty($card['has_layer']) ? 'admin.setup_config.present' : 'admin.setup_config.missing')) ?></div>
        <div class="muted"><?= e(t('admin.setup_config.overrides')) ?>: <?= (int)($card['override_count'] ?? 0) ?></div>
        <div class="muted"><?= e(t('admin.setup_config.warnings_errors')) ?>: <?= (int)($card['warning_count'] ?? 0) ?> / <?= (int)($card['error_count'] ?? 0) ?></div>
        <div class="muted"><?= e(t('admin.setup_config.app_url')) ?>: <?= e((string)($card['app_url'] ?? '')) ?: e(t('admin.setup_config.not_set')) ?></div>
        <?php if (!empty($card['missing_keys'])): ?><div class="muted setup-config-detail"><?= e(t('admin.setup_config.missing_keys')) ?>: <?= e(implode(' | ', array_map('strval', (array)$card['missing_keys']))) ?></div><?php endif; ?>
        <div class="setup-config-action"><a class="btn<?= $environment === $selectedEnvironment ? ' ok' : '' ?>" href="/admin/setup/config?environment=<?= e((string)$environment) ?>"><?= e(t('admin.setup_config.open_layer')) ?></a></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="setup-config-grid setup-config-grid--validation">
  <div class="card setup-config-card">
    <h3 class="setup-config-title"><?= e(t('admin.setup_config.global_validation')) ?></h3><div class="muted setup-config-copy"><?= e(t('admin.setup_config.global_validation_description')) ?></div>
    <div class="muted"><?= e(t('admin.setup_config.warnings')) ?>: <?= count((array)($globalValidation['warnings'] ?? [])) ?></div><div class="muted"><?= e(t('admin.setup_config.errors')) ?>: <?= count((array)($globalValidation['errors'] ?? [])) ?></div>
    <?php if (!empty($globalValidation['warnings'])): ?><div class="muted setup-config-detail"><?= e(implode(' | ', array_map('strval', (array)$globalValidation['warnings']))) ?></div><?php endif; ?>
    <?php if (!empty($globalValidation['errors'])): ?><div class="setup-config-detail setup-config-status--error"><?= e(implode(' | ', array_map('strval', (array)$globalValidation['errors']))) ?></div><?php endif; ?>
  </div>
  <div class="card setup-config-card">
    <h3 class="setup-config-title"><?= e(t('admin.setup_config.db_status')) ?></h3><div class="muted setup-config-copy"><?= e(t('admin.setup_config.db_status_description')) ?></div>
    <div class="muted setup-config-mask-note"><?= e(t('admin.setup_config.mask_note')) ?></div>
    <?php foreach ($dbConfig as $key => $value): ?><div class="muted"><strong><?= e((string)$key) ?></strong>: <?= e($safeConfigValue((string)$key, $value)) ?></div><?php endforeach; ?>
    <?php if (!empty($dbConfigStatus['errors'])): ?><div class="setup-config-detail setup-config-status--error"><?= e(implode(' | ', array_map('strval', (array)$dbConfigStatus['errors']))) ?></div><?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="setup-config-head setup-config-head--wrap"><div><h3 class="setup-config-title"><?= e($environmentLabel($selectedEnvironment)) ?> <?= e(t('admin.setup_config.layer')) ?></h3><div class="muted"><?= e(t('admin.setup_config.layer_description')) ?></div></div><a class="btn" href="/admin/setup/config/export?environment=<?= e($selectedEnvironment) ?>"><?= e(t('admin.setup_config.export')) ?></a></div>
  <div class="setup-config-grid setup-config-grid--editor">
    <div class="card setup-config-card"><h4 class="setup-config-title"><?= e(t('admin.setup_config.validation')) ?></h4><div class="muted"><?= e(t('admin.setup_config.missing_keys')) ?>: <?= count((array)($selectedValidation['missing_keys'] ?? [])) ?></div><div class="muted"><?= e(t('admin.setup_config.warnings')) ?>: <?= count((array)($selectedValidation['warnings'] ?? [])) ?></div><div class="muted"><?= e(t('admin.setup_config.errors')) ?>: <?= count((array)($selectedValidation['errors'] ?? [])) ?></div><?php if (!empty($selectedValidation['warnings'])): ?><div class="muted setup-config-detail"><?= e(implode(' | ', array_map('strval', (array)$selectedValidation['warnings']))) ?></div><?php endif; ?><?php if (!empty($selectedValidation['errors'])): ?><div class="setup-config-detail setup-config-status--error"><?= e(implode(' | ', array_map('strval', (array)$selectedValidation['errors']))) ?></div><?php endif; ?></div>
    <div class="card setup-config-card"><h4 class="setup-config-title"><?= e(t('admin.setup_config.import_title')) ?></h4><div class="muted setup-config-copy"><?= e(t('admin.setup_config.import_description')) ?></div><form method="post" action="/admin/setup/config/import?environment=<?= e($selectedEnvironment) ?>" enctype="multipart/form-data" class="setup-config-form"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="environment" value="<?= e($selectedEnvironment) ?>"><label><span class="control-label"><?= e(t('admin.setup_config.import_mode')) ?></span><select name="import_mode"><option value="merge"><?= e(t('admin.setup_config.merge')) ?></option><option value="replace"><?= e(t('admin.setup_config.replace')) ?></option></select></label><label><span class="control-label"><?= e(t('admin.setup_config.config_file')) ?></span><input type="file" name="config_file" accept=".json" required></label><button class="btn" type="submit"><?= e(t('admin.setup_config.import')) ?></button></form></div>
  </div>
  <form method="post" action="/admin/setup/config/save" class="setup-config-editor"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="environment" value="<?= e($selectedEnvironment) ?>"><label><span class="control-label"><?= e(t('admin.setup_config.layer_json')) ?></span><textarea name="layer_json" rows="18" class="setup-config-textarea"><?= e($selectedLayerJson) ?></textarea></label><div class="muted setup-config-copy"><?= e(t('admin.setup_config.recommended_keys')) ?></div><button class="btn ok" type="submit"><?= e(t('admin.setup_config.save')) ?></button></form>
</div>

<div class="card">
  <h3 class="setup-config-title"><?= e(t('admin.setup_config.resolved_title')) ?></h3><div class="muted setup-config-copy"><?= e(t('admin.setup_config.resolved_description')) ?></div><div class="muted setup-config-mask-note"><?= e(t('admin.setup_config.mask_note')) ?></div>
  <div class="table-wrap"><table><thead><tr><th><?= e(t('admin.setup_config.key')) ?></th><th><?= e(t('admin.setup_config.value')) ?></th></tr></thead><tbody>
    <?php foreach ($selectedResolved as $key => $value): ?><tr><td><?= e((string)$key) ?></td><td><?= e($safeConfigValue((string)$key, $value)) ?></td></tr><?php endforeach; ?>
    <?php if ($selectedResolved === []): ?><tr><td colspan="2" class="muted"><?= e(t('admin.setup_config.no_resolved')) ?></td></tr><?php endif; ?>
  </tbody></table></div>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$scopeOptions = is_array($scopeOptions ?? null) ? $scopeOptions : [];
$componentChoices = is_array($componentChoices ?? null) ? $componentChoices : ['suites' => [], 'modules' => []];
$history = is_array($history ?? null) ? $history : [];
$previewPayload = is_array($preview ?? null) ? $preview : [];
$preview = is_array($previewPayload['preview'] ?? null) ? $previewPayload['preview'] : [];
$releaseScopeLabel = static function (string $scope, string $fallback = ''): string {
    $known = ['core_only', 'core_selected_suites', 'suite_only', 'module_only'];
    return in_array($scope, $known, true) ? t('admin.setup_release.scope.' . $scope) : ($fallback !== '' ? $fallback : $scope);
};
$releaseStatusLabel = static function (string $status): string {
    $known = ['previewed', 'generated', 'failed'];
    return in_array($status, $known, true) ? t('admin.setup_release.status.' . $status) : $status;
};
$releaseJoined = static function (array $values, string $separator = ', '): string {
    $joined = implode($separator, array_map('strval', $values));
    return $joined !== '' ? $joined : t('admin.setup_release.none');
};
?>
<?php $setupNavCurrent = 'release'; require __DIR__ . '/_nav.php'; ?>
<?php require __DIR__ . '/_flash.php'; ?>

<div class="card"><h2 class="setup-release-title"><?= e(t('admin.setup_release.title')) ?></h2><div class="muted"><?= e(t('admin.setup_release.description')) ?></div></div>

<div class="card">
  <h3 class="setup-release-title"><?= e(t('admin.setup_release.verification_title')) ?></h3><div class="muted setup-release-copy"><?= e(t('admin.setup_release.verification_description')) ?></div>
  <form method="post" action="/admin/setup/release/preview"><input type="hidden" name="csrf" value="<?= e((string)($csrf ?? '')) ?>">
    <div class="setup-release-grid setup-release-grid--fields">
      <label><span class="control-label"><?= e(t('admin.setup_release.release_scope')) ?></span><select name="scope_key"><?php foreach ($scopeOptions as $key => $label): ?><option value="<?= e((string)$key) ?>"><?= e($releaseScopeLabel((string)$key, (string)$label)) ?></option><?php endforeach; ?></select></label>
      <label><span class="control-label"><?= e(t('admin.setup_release.release_version')) ?></span><input type="text" name="release_version" placeholder="<?= e(t('admin.setup_release.version_placeholder')) ?>" required></label>
      <label><span class="control-label"><?= e(t('admin.setup_release.notes')) ?></span><textarea name="notes" rows="3" placeholder="<?= e(t('admin.setup_release.notes_placeholder')) ?>"></textarea></label>
    </div>
    <div class="setup-release-grid setup-release-grid--choices">
      <div class="card setup-release-card"><strong><?= e(t('admin.setup_release.suites')) ?></strong><div class="muted setup-release-detail"><?= e(t('admin.setup_release.suites_description')) ?></div><?php foreach ((array)($componentChoices['suites'] ?? []) as $suiteKey): ?><label class="setup-release-choice"><input type="checkbox" name="suite_keys[]" value="<?= e((string)$suiteKey) ?>"><span><?= e((string)$suiteKey) ?></span></label><?php endforeach; ?></div>
      <div class="card setup-release-card"><strong><?= e(t('admin.setup_release.modules')) ?></strong><div class="muted setup-release-detail"><?= e(t('admin.setup_release.modules_description')) ?></div><div class="setup-release-scroll"><?php foreach ((array)($componentChoices['modules'] ?? []) as $moduleName): ?><label class="setup-release-choice"><input type="checkbox" name="module_names[]" value="<?= e((string)$moduleName) ?>"><span><?= e((string)$moduleName) ?></span></label><?php endforeach; ?></div></div>
    </div>
    <button class="btn ok setup-release-action" type="submit"><?= e(t('admin.setup_release.run_check')) ?></button>
  </form>
</div>

<?php if ($preview !== []): ?>
  <div class="card setup-release-review">
    <div class="setup-release-head"><div><h3 class="setup-release-title"><?= e(t('admin.setup_release.result_title')) ?></h3><div class="muted"><?= e(t('admin.setup_release.result_description')) ?></div></div><span class="pill <?= !empty($preview['ready']) ? 'setup-release-status--ready' : 'setup-release-status--error' ?>"><?= e(t(!empty($preview['ready']) ? 'admin.setup_release.ready' : 'admin.setup_release.not_ready')) ?></span></div>
    <div class="setup-release-grid setup-release-grid--summary">
      <div class="card setup-release-card"><div class="muted"><?= e(t('admin.setup_release.scope_label')) ?></div><strong><?= e($releaseScopeLabel((string)($preview['scope_key'] ?? ''), (string)($preview['scope_label'] ?? ''))) ?></strong></div>
      <div class="card setup-release-card"><div class="muted"><?= e(t('admin.setup_release.blocking_issues')) ?></div><strong><?= (int)($preview['summary']['blocking_count'] ?? 0) ?></strong></div>
      <div class="card setup-release-card"><div class="muted"><?= e(t('admin.setup_release.warnings')) ?></div><strong><?= (int)($preview['summary']['warning_count'] ?? 0) ?></strong></div>
      <div class="card setup-release-card"><div class="muted"><?= e(t('admin.setup_release.included_components')) ?></div><strong><?= (int)($preview['summary']['suite_count'] ?? 0) ?> <?= e(t('admin.setup_release.suites_lower')) ?> / <?= (int)($preview['summary']['module_count'] ?? 0) ?> <?= e(t('admin.setup_release.modules_lower')) ?></strong></div>
    </div>
    <div class="setup-release-grid setup-release-grid--findings">
      <div class="card setup-release-card"><h4 class="setup-release-title"><?= e(t('admin.setup_release.blocking_issues')) ?></h4><?php if (!empty($preview['blocking_issues'])): ?><?php foreach ((array)$preview['blocking_issues'] as $issue): ?><div class="muted setup-release-finding">• <?= e((string)$issue) ?></div><?php endforeach; ?><?php else: ?><div class="muted"><?= e(t('admin.setup_release.no_blockers')) ?></div><?php endif; ?></div>
      <div class="card setup-release-card"><h4 class="setup-release-title"><?= e(t('admin.setup_release.non_blocking')) ?></h4><?php if (!empty($preview['non_blocking_warnings'])): ?><?php foreach ((array)$preview['non_blocking_warnings'] as $issue): ?><div class="muted setup-release-finding">• <?= e((string)$issue) ?></div><?php endforeach; ?><?php else: ?><div class="muted"><?= e(t('admin.setup_release.no_warnings')) ?></div><?php endif; ?></div>
      <div class="card setup-release-card"><h4 class="setup-release-title"><?= e(t('admin.setup_release.included_components')) ?></h4><div class="muted"><?= e(t('admin.setup_release.suites')) ?>: <?= e($releaseJoined((array)($preview['included_components']['suites'] ?? []))) ?></div><div class="muted setup-release-detail"><?= e(t('admin.setup_release.modules')) ?>: <?= e($releaseJoined((array)($preview['included_components']['modules'] ?? []))) ?></div></div>
    </div>
    <div class="card setup-release-card setup-release-section"><h4 class="setup-release-title"><?= e(t('admin.setup_release.technical_detail')) ?></h4><div class="muted"><?= e(t('admin.setup_release.pending_migrations')) ?>: <?= e($releaseJoined((array)($preview['pending_migrations'] ?? []), ' | ')) ?></div><div class="muted setup-release-detail"><?= e(t('admin.setup_release.config_issues')) ?>: <?= e($releaseJoined((array)($preview['config_env_issues'] ?? []), ' | ')) ?></div><div class="muted setup-release-detail"><?= e(t('admin.setup_release.failed_hooks')) ?>: <?= count((array)($preview['failed_hooks'] ?? [])) ?></div><div class="muted setup-release-detail"><?= e(t('admin.setup_release.schema_gaps')) ?>: <?= count((array)($preview['schema_gaps'] ?? [])) ?></div></div>
    <div class="card setup-release-card setup-release-section"><h4 class="setup-release-title"><?= e(t('admin.setup_release.version_context')) ?></h4><div class="muted setup-release-copy"><?= e(t('admin.setup_release.version_context_description')) ?></div><div class="table-wrap"><table><thead><tr><th><?= e(t('admin.setup_release.layer')) ?></th><th><?= e(t('admin.setup_release.name')) ?></th><th><?= e(t('admin.setup_release.current_target')) ?></th><th><?= e(t('admin.setup_release.summary')) ?></th><th><?= e(t('admin.setup_release.migration_notes')) ?></th></tr></thead><tbody>
      <?php $coreVersion = is_array($preview['version_context']['core'] ?? null) ? $preview['version_context']['core'] : []; if ($coreVersion !== []): ?><tr><td><?= e(t('admin.setup_release.core')) ?></td><td><?= e((string)($coreVersion['label'] ?? '')) ?></td><td><?= e((string)($coreVersion['current_version'] ?? '')) ?> / <?= e((string)($coreVersion['target_version'] ?? '')) ?></td><td><?= e((string)($coreVersion['latest_entry']['summary'] ?? '')) ?></td><td><?= e(implode(' | ', array_map('strval', (array)($coreVersion['latest_entry']['migration_notes'] ?? [])))) ?></td></tr><?php endif; ?>
      <?php foreach (['suites' => 'suite', 'modules' => 'module'] as $group => $type): ?><?php foreach ((array)($preview['version_context'][$group] ?? []) as $item): ?><tr><td><?= e(t('admin.setup_release.' . $type)) ?></td><td><?= e((string)($item['label'] ?? '')) ?></td><td><?= e((string)($item['current_version'] ?? '')) ?> / <?= e((string)($item['target_version'] ?? '')) ?></td><td><?= e((string)($item['latest_entry']['summary'] ?? '')) ?></td><td><?= e(implode(' | ', array_map('strval', (array)($item['latest_entry']['migration_notes'] ?? [])))) ?></td></tr><?php endforeach; ?><?php endforeach; ?>
    </tbody></table></div></div>
    <div class="setup-release-gate"><strong><?= e(t('admin.setup_release.gate_title')) ?></strong><div><?= e(t('admin.setup_release.gate_description')) ?></div></div>
    <?php if (!empty($preview['ready'])): ?><form method="post" action="/admin/setup/release/generate"><input type="hidden" name="csrf" value="<?= e((string)($csrf ?? '')) ?>"><input type="hidden" name="preview_token" value="<?= e((string)($previewPayload['preview_token'] ?? '')) ?>"><button class="btn ok" type="submit"><?= e(t('admin.setup_release.generate')) ?></button></form><?php endif; ?>
    <form method="post" action="/admin/setup/release/cancel" class="setup-release-cancel"><input type="hidden" name="csrf" value="<?= e((string)($csrf ?? '')) ?>"><input type="hidden" name="preview_token" value="<?= e((string)($previewPayload['preview_token'] ?? '')) ?>"><button class="btn" type="submit"><?= e(t('admin.setup_release.clear_preview')) ?></button></form>
  </div>
<?php endif; ?>

<div class="card"><h3 class="setup-release-title"><?= e(t('admin.setup_release.history_title')) ?></h3><div class="muted setup-release-copy"><?= e(t('admin.setup_release.history_description')) ?></div><div class="table-wrap"><table><thead><tr><th><?= e(t('admin.setup_release.when')) ?></th><th><?= e(t('admin.setup_release.package')) ?></th><th><?= e(t('admin.setup_release.scope_label')) ?></th><th><?= e(t('admin.setup_release.version')) ?></th><th><?= e(t('admin.setup_release.status_label')) ?></th><th><?= e(t('admin.setup_release.notes_label')) ?></th><th><?= e(t('admin.setup_release.outcome')) ?></th></tr></thead><tbody>
  <?php foreach ($history as $row): ?><?php $status = (string)($row['status'] ?? ''); ?><tr><td><?= e((string)($row['created_at'] ?? '')) ?></td><td><?php if (!empty($row['package_name']) && $status === 'generated'): ?><a href="/admin/setup/release/download?id=<?= (int)($row['id'] ?? 0) ?>"><?= e((string)$row['package_name']) ?></a><?php else: ?><span class="muted"><?= e((string)($row['package_name'] ?? t('admin.setup_release.not_applicable'))) ?></span><?php endif; ?></td><td><?= e($releaseScopeLabel((string)($row['scope_key'] ?? ''))) ?></td><td><?= e((string)($row['release_version'] ?? '')) ?></td><td><?= e($releaseStatusLabel($status)) ?></td><td><?= e((string)($row['notes_text'] ?? '')) ?></td><td><?= e(trim((string)($row['warning_text'] ?? '') . ' ' . (string)($row['error_text'] ?? ''))) ?: e(t('admin.setup_release.ok')) ?></td></tr><?php endforeach; ?>
  <?php if ($history === []): ?><tr><td colspan="7" class="muted"><?= e(t('admin.setup_release.no_history')) ?></td></tr><?php endif; ?>
  </tbody></table></div></div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$profiles        = is_array($profiles        ?? null) ? $profiles        : [];
$userCounts      = is_array($userCounts      ?? null) ? $userCounts      : [];
$appOptions      = is_array($appOptions      ?? null) ? $appOptions      : [];
$filterAuthority = (string)($filterAuthority ?? '');
$filterApp       = (string)($filterApp       ?? '');
$filterActive    = (string)($filterActive    ?? '');
$ownershipUiLabels = is_array($ownershipUiLabels ?? null) ? $ownershipUiLabels : [];
$flash           = (string)($flash           ?? '');
$error           = (string)($error           ?? '');
$csrf            = (string)($csrf            ?? '');

$authorityLabels = [
    'platform_admin' => t('ops.workspace_profiles.authority.platform_admin'),
    'app_admin'      => t('ops.workspace_profiles.authority.app_admin'),
    'app_user'       => t('ops.workspace_profiles.authority.app_user'),
    'tv_display'     => t('ops.workspace_profiles.authority.tv_display'),
];
$scopeLabels = [
    'platform' => t('ops.workspace_profiles.scope.platform'),
    'app'      => t('ops.workspace_profiles.scope.app'),
    'module'   => t('ops.workspace_profiles.scope.module'),
    'personal' => t('ops.workspace_profiles.scope.personal'),
    'shared'   => t('ops.workspace_profiles.scope.shared'),
];
$authorityLabel = static fn (string $v): string => (string)($authorityLabels[$v] ?? $v);
$scopeLabel     = static fn (string $v): string => (string)($scopeLabels[$v]     ?? $v);
?>

<?php if ($flash !== ''): ?>
<div class="flash ok"><?= e(t($flash)) ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
<div class="flash err"><?= e(t($error)) ?></div>
<?php endif; ?>

<div class="page-header">
  <h2><?= e(t('ops.workspace_profiles.page_title')) ?></h2>
  <div class="page-header-actions">
    <a href="/ops/workspace-profiles/detail" class="btn ok"><?= e(t('ops.workspace_profiles.btn_new')) ?></a>
  </div>
</div>

<div class="muted u-style-87c136dfd0"><?= e(t('ops.workspace_profiles.page_note')) ?></div>

<!-- Filter row -->
<form class="u-style-fc8728048f" method="get" action="/ops/workspace-profiles">
  <select class="u-style-4592c743a5" name="authority">
    <option value=""><?= e(t('ops.workspace_profiles.filter_authority_all')) ?></option>
    <?php foreach ($authorityLabels as $aVal => $aLbl): ?>
      <option value="<?= e($aVal) ?>" <?= $filterAuthority === $aVal ? 'selected' : '' ?>><?= e($aLbl) ?></option>
    <?php endforeach; ?>
  </select>

  <select class="u-style-4592c743a5" name="app">
    <option value=""><?= e(t('ops.workspace_profiles.filter_app_all')) ?></option>
    <?php foreach ($appOptions as $aKey => $aLabel): ?>
      <option value="<?= e($aKey) ?>" <?= $filterApp === $aKey ? 'selected' : '' ?>><?= e($aLabel) ?></option>
    <?php endforeach; ?>
    <?php if (!isset($appOptions[''])): ?>
      <option value="__none__" <?= $filterApp === '__none__' ? 'selected' : '' ?>><?= e(t('ops.workspace_profiles.filter_app_none')) ?></option>
    <?php endif; ?>
  </select>

  <select class="u-style-651945a073" name="active">
    <option value=""><?= e(t('ops.workspace_profiles.filter_active_all')) ?></option>
    <option value="1" <?= $filterActive === '1' ? 'selected' : '' ?>><?= e(t('ops.workspace_profiles.filter_active_yes')) ?></option>
    <option value="0" <?= $filterActive === '0' ? 'selected' : '' ?>><?= e(t('ops.workspace_profiles.filter_active_no')) ?></option>
  </select>

  <button type="submit" class="btn small"><?= e(t('ops.workspace_profiles.btn_filter')) ?></button>
  <?php if ($filterAuthority !== '' || $filterApp !== '' || $filterActive !== ''): ?>
    <a href="/ops/workspace-profiles" class="btn small muted"><?= e(t('ops.workspace_profiles.btn_clear_filter')) ?></a>
  <?php endif; ?>
</form>

<?php if ($profiles === []): ?>
<div class="card">
  <div class="muted"><?= e(t('ops.workspace_profiles.empty')) ?></div>
</div>
<?php else: ?>
<div class="table-wrap">
<table class="data-table">
  <thead>
    <tr>
      <th><?= e(t('ops.workspace_profiles.col_name')) ?></th>
      <th><?= e(t('ops.workspace_profiles.col_key')) ?></th>
      <th><?= e(t('ops.workspace_profiles.col_app')) ?></th>
      <th><?= e((string)($ownershipUiLabels['col_owner'] ?? 'Ownership')) ?></th>
      <th><?= e(t('ops.workspace_profiles.col_authority')) ?></th>
      <th><?= e(t('ops.workspace_profiles.col_scope')) ?></th>
      <th><?= e(t('ops.workspace_profiles.col_landing')) ?></th>
      <th><?= e(t('ops.workspace_profiles.col_users')) ?></th>
      <th><?= e(t('ops.workspace_profiles.col_active')) ?></th>
      <th></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($profiles as $p): ?>
      <?php
        $pid       = (int)($p['id'] ?? 0);
        $pKey      = (string)($p['profile_key'] ?? '');
        $pName     = (string)($p['name'] ?? '');
        $pApp      = (string)($p['app_key'] ?? '—');
        $pOwnerSummary = (string)($p['owner_summary_label'] ?? '');
        $pOwnerNote = (string)($p['owner_note'] ?? '');
        $pAuth     = (string)($p['authority_role'] ?? '');
        $pScope    = (string)($p['governance_scope'] ?? '');
        $pLanding  = (string)($p['landing_route'] ?? '/');
        $pActive   = (bool)($p['is_active'] ?? true);
        $pSystem   = (bool)($p['is_system'] ?? false);
        $pUpdated  = (string)($p['updated_at'] ?? '');
        $pUsers    = (int)($userCounts[$pKey] ?? 0);
      ?>
      <tr>
        <td>
          <a href="/ops/workspace-profiles/detail?id=<?= $pid ?>"><?= e($pName) ?></a>
          <?php if ($pSystem): ?>
            <span class="badge muted u-style-ba93e49de8"><?= e(t('ops.workspace_profiles.badge_system')) ?></span>
          <?php endif; ?>
          <?php if ($p['description'] ?? ''): ?>
            <div class="muted u-style-90cf4c51c7"><?= e((string)($p['description'] ?? '')) ?></div>
          <?php endif; ?>
        </td>
        <td><code><?= e($pKey) ?></code></td>
        <td><?= e($pApp !== '' && $pApp !== '—' ? $pApp : '—') ?></td>
        <td>
          <div class="ui-block"><?= e($pOwnerSummary !== '' ? $pOwnerSummary : '—') ?></div>
          <?php if ($pOwnerNote !== ''): ?>
            <div class="muted u-style-90cf4c51c7"><?= e($pOwnerNote) ?></div>
          <?php endif; ?>
        </td>
        <td><?= e($authorityLabel($pAuth)) ?></td>
        <td><?= e($scopeLabel($pScope)) ?></td>
        <td><code><?= e($pLanding) ?></code></td>
        <td>
          <?php if ($pUsers > 0): ?>
            <a href="/ops/access-control?workspace_profile=<?= e($pKey) ?>" class="workspace-profile-user-count"><?= $pUsers ?></a>
          <?php else: ?>
            <span class="muted">0</span>
          <?php endif; ?>
        </td>
        <td><?= $pActive ? '<span class="badge ok">'.e(t('ops.workspace_profiles.yes')).'</span>' : '<span class="muted">'.e(t('ops.workspace_profiles.no')).'</span>' ?></td>
        <td class="u-style-eb6e6f99e0">
          <a href="/ops/workspace-profiles/detail?id=<?= $pid ?>" class="btn small"><?= e(t('ops.workspace_profiles.btn_edit')) ?></a>
          <?php if (!$pSystem): ?>
          <form method="post" action="/ops/workspace-profiles/delete" class="workspace-profile-inline-form" onsubmit="return confirm(<?= json_encode(t('ops.workspace_profiles.confirm_delete')) ?>)">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="id" value="<?= $pid ?>">
            <button type="submit" class="btn err small"><?= e(t('ops.workspace_profiles.btn_delete')) ?></button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

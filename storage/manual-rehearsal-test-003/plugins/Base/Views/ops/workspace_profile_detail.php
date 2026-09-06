<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$profile    = is_array($profile    ?? null) ? $profile    : null;
$ownerMeta  = is_array($ownerMeta  ?? null) ? $ownerMeta  : [];
$ownershipUiLabels = is_array($ownershipUiLabels ?? null) ? $ownershipUiLabels : [];
$appOptions = is_array($appOptions ?? null) ? $appOptions : ['platform' => 'Platform'];
$pinnedUsers = is_array($pinnedUsers ?? null) ? $pinnedUsers : [];
$flash      = (string)($flash  ?? '');
$error      = (string)($error  ?? '');
$csrf       = (string)($csrf   ?? '');

$isNew      = ($profile === null);
$profileId  = (int)($profile['id'] ?? 0);
$isSystem   = (bool)($profile['is_system'] ?? false);
$redirectTo = '/ops/workspace-profiles/detail?id=' . $profileId;

// Field defaults
$fKey          = (string)($profile['profile_key']      ?? '');
$fName         = (string)($profile['name']             ?? '');
$fDesc         = (string)($profile['description']      ?? '');
$fAppKey       = (string)($profile['app_key']          ?? '');
$fAuthority    = (string)($profile['authority_role']   ?? 'app_user');
$fScope        = (string)($profile['governance_scope'] ?? 'app');
$fLanding      = (string)($profile['landing_route']    ?? '/');
$fDefaultApp   = (string)($profile['default_app']      ?? '');
$fAssignedApps = (string)($profile['assigned_apps']    ?? '');
$fAccessProfs  = (string)($profile['access_profiles']  ?? '');
$fWidgetDisc   = (string)($profile['widget_discovery'] ?? 'auto');
$fActive       = $isNew ? true : (bool)($profile['is_active'] ?? true);

$fNavSections    = (string)($profile['nav_sections_json']    ?? '');
$fQuickActions   = (string)($profile['quick_actions_json']   ?? '');
$fModVis         = (string)($profile['module_visibility_json'] ?? '');
$fPermissions    = (string)($profile['permissions_json']     ?? '');
$fDashBlocks     = (string)($profile['dashboard_blocks_json'] ?? '');
$ownerTypeLabel  = (string)($ownerMeta['type_label'] ?? '');
$ownerTargetLabel = (string)($ownerMeta['target_label'] ?? '');
$ownerSummary    = (string)($ownerMeta['summary'] ?? '');
$ownerNote       = (string)($ownerMeta['note'] ?? '');

$authorityOptions = [
    'platform_admin' => t('ops.workspace_profiles.authority.platform_admin'),
    'app_admin'      => t('ops.workspace_profiles.authority.app_admin'),
    'app_user'       => t('ops.workspace_profiles.authority.app_user'),
    'tv_display'     => t('ops.workspace_profiles.authority.tv_display'),
];
$scopeOptions = [
    'platform' => t('ops.workspace_profiles.scope.platform'),
    'app'      => t('ops.workspace_profiles.scope.app'),
    'module'   => t('ops.workspace_profiles.scope.module'),
    'personal' => t('ops.workspace_profiles.scope.personal'),
    'shared'   => t('ops.workspace_profiles.scope.shared'),
];
$widgetDiscOptions = [
    'auto'     => t('ops.workspace_profiles.widget_disc.auto'),
    'manual'   => t('ops.workspace_profiles.widget_disc.manual'),
    'disabled' => t('ops.workspace_profiles.widget_disc.disabled'),
];
?>

<div class="page-header">
  <h2>
    <a href="/ops/workspace-profiles"><?= e(t('ops.workspace_profiles.page_title')) ?></a>
    &rsaquo;
    <?= $isNew ? e(t('ops.workspace_profiles.new_title')) : e($fName) ?>
  </h2>
</div>

<?php if ($flash !== ''): ?>
<div class="flash ok"><?= e(t($flash)) ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
<div class="flash err"><?= e(t($error)) ?></div>
<?php endif; ?>
<?php if ($isSystem): ?>
<div class="flash muted"><?= e(t('ops.workspace_profiles.system_readonly')) ?></div>
<?php endif; ?>

<form method="post" action="/ops/workspace-profiles/save" class="assignment-detail-form" <?= $isSystem ? 'onsubmit="return false"' : '' ?>>
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
  <?php if (!$isNew): ?>
    <input type="hidden" name="id" value="<?= $profileId ?>">
    <input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>">
  <?php endif; ?>

  <div class="card u-style-87c136dfd0">
    <h4 class="u-style-868bc5d99f"><?= e(t('ops.workspace_profiles.section_identity')) ?></h4>

    <div class="preview-grid">
      <div class="field-group">
        <label for="wp_key"><?= e(t('ops.workspace_profiles.field_key')) ?> <span class="required">*</span></label>
        <input class="input" type="text" name="profile_key" id="wp_key" value="<?= e($fKey) ?>"
               pattern="[a-z0-9_]+" placeholder="e.g. manufacturing_leader"
               <?= $isSystem ? 'disabled' : 'required' ?>
               style="font-family:monospace">
        <div class="muted u-style-837fb33f88"><?= e(t('ops.workspace_profiles.field_key_hint')) ?></div>
      </div>

      <div class="field-group">
        <label for="wp_name"><?= e(t('ops.workspace_profiles.field_name')) ?> <span class="required">*</span></label>
        <input class="input" type="text" name="name" id="wp_name" value="<?= e($fName) ?>"
               placeholder="e.g. Manufacturing Leader"
               <?= $isSystem ? 'disabled' : 'required' ?>>
      </div>
    </div>

    <div class="field-group u-style-d2c171b18b">
      <label for="wp_desc"><?= e(t('ops.workspace_profiles.field_description')) ?></label>
      <textarea name="description" id="wp_desc" rows="2" <?= $isSystem ? 'disabled' : '' ?>><?= e($fDesc) ?></textarea>
    </div>

    <div class="preview-grid u-style-d2c171b18b">
      <div class="field-group">
        <label for="wp_app"><?= e(t('ops.workspace_profiles.field_app')) ?></label>
        <select name="app_key" id="wp_app" <?= $isSystem ? 'disabled' : '' ?>>
          <option value=""><?= e(t('ops.workspace_profiles.pick_app')) ?></option>
          <?php foreach ($appOptions as $aKey => $aLabel): ?>
            <option value="<?= e($aKey) ?>" <?= $fAppKey === $aKey ? 'selected' : '' ?>><?= e($aLabel) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field-group">
        <label for="wp_authority"><?= e(t('ops.workspace_profiles.field_authority')) ?></label>
        <select name="authority_role" id="wp_authority" <?= $isSystem ? 'disabled' : '' ?>>
          <?php foreach ($authorityOptions as $aVal => $aLabel): ?>
            <option value="<?= e($aVal) ?>" <?= $fAuthority === $aVal ? 'selected' : '' ?>><?= e($aLabel) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field-group">
        <label for="wp_scope"><?= e(t('ops.workspace_profiles.field_scope')) ?></label>
        <select name="governance_scope" id="wp_scope" <?= $isSystem ? 'disabled' : '' ?>>
          <?php foreach ($scopeOptions as $sVal => $sLabel): ?>
            <option value="<?= e($sVal) ?>" <?= $fScope === $sVal ? 'selected' : '' ?>><?= e($sLabel) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="preview-grid u-style-d2c171b18b">
      <div class="field-group">
        <label><?= e((string)($ownershipUiLabels['field_owner_type'] ?? 'Owner Type')) ?></label>
        <div class="ui-block"><strong><?= e($ownerTypeLabel !== '' ? $ownerTypeLabel : '—') ?></strong></div>
      </div>

      <div class="field-group">
        <label><?= e((string)($ownershipUiLabels['field_owner_target'] ?? 'Owner Target')) ?></label>
        <div class="ui-block"><strong><?= e($ownerTargetLabel !== '' ? $ownerTargetLabel : '—') ?></strong></div>
      </div>
    </div>

    <?php if ($ownerSummary !== '' || $ownerNote !== ''): ?>
    <div class="muted u-style-032f7ac763">
      <?php if ($ownerSummary !== ''): ?>
        <div class="ui-block"><strong><?= e($ownerSummary) ?></strong></div>
      <?php endif; ?>
      <?php if ($ownerNote !== ''): ?>
        <div class="u-style-7a21c6ac49"><?= e($ownerNote) ?></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <div class="card u-style-87c136dfd0">
    <h4 class="u-style-868bc5d99f"><?= e(t('ops.workspace_profiles.section_routing')) ?></h4>

    <div class="preview-grid">
      <div class="field-group">
        <label for="wp_landing"><?= e(t('ops.workspace_profiles.field_landing')) ?></label>
        <input class="input" type="text" name="landing_route" id="wp_landing" value="<?= e($fLanding) ?>"
               placeholder="/" <?= $isSystem ? 'disabled' : '' ?>>
        <div class="muted u-style-837fb33f88"><?= e(t('ops.workspace_profiles.field_landing_hint')) ?></div>
      </div>

      <div class="field-group">
        <label for="wp_default_app"><?= e(t('ops.workspace_profiles.field_default_app')) ?></label>
        <select name="default_app" id="wp_default_app" <?= $isSystem ? 'disabled' : '' ?>>
          <option value=""><?= e(t('ops.workspace_profiles.pick_app')) ?></option>
          <?php foreach ($appOptions as $aKey => $aLabel): ?>
            <option value="<?= e($aKey) ?>" <?= $fDefaultApp === $aKey ? 'selected' : '' ?>><?= e($aLabel) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field-group">
        <label for="wp_assigned_apps"><?= e(t('ops.workspace_profiles.field_assigned_apps')) ?></label>
        <input class="input" type="text" name="assigned_apps" id="wp_assigned_apps" value="<?= e($fAssignedApps) ?>"
               placeholder="platform,manufacturing" <?= $isSystem ? 'disabled' : '' ?>>
      </div>

      <div class="field-group">
        <label for="wp_access_profiles"><?= e(t('ops.workspace_profiles.field_access_profiles')) ?></label>
        <input class="input" type="text" name="access_profiles" id="wp_access_profiles" value="<?= e($fAccessProfs) ?>"
               placeholder="production_operations" <?= $isSystem ? 'disabled' : '' ?>>
      </div>
    </div>

    <div class="preview-grid u-style-d2c171b18b">
      <div class="field-group">
        <label for="wp_widget_disc"><?= e(t('ops.workspace_profiles.field_widget_discovery')) ?></label>
        <select name="widget_discovery" id="wp_widget_disc" <?= $isSystem ? 'disabled' : '' ?>>
          <?php foreach ($widgetDiscOptions as $wVal => $wLabel): ?>
            <option value="<?= e($wVal) ?>" <?= $fWidgetDisc === $wVal ? 'selected' : '' ?>><?= e($wLabel) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field-group u-style-ec9df3f809">
        <input type="checkbox" name="is_active" id="wp_active" value="1" <?= $fActive ? 'checked' : '' ?> <?= $isSystem ? 'disabled' : '' ?>>
        <label class="u-style-44fca8c7a2" for="wp_active"><?= e(t('ops.workspace_profiles.field_active')) ?></label>
      </div>
    </div>
  </div>

<?php
$allModules = is_array($allModules ?? null) ? $allModules : [];
// Decode existing module_visibility JSON into an array for the matrix.
$modVisMap = [];
if ($fModVis !== '') {
    $decoded = json_decode($fModVis, true);
    if (is_array($decoded)) {
        $modVisMap = $decoded;
    }
}
// Determine which apps are selected (from assigned_apps CSV).
$assignedAppsArr = array_filter(array_map('trim', explode(',', $fAssignedApps)));
$matrixApps = $assignedAppsArr !== []
    ? array_intersect_key($allModules, array_flip($assignedAppsArr))
    : $allModules;
$visLevels = ['none', 'view', 'work', 'approve', 'manage'];
$visLevelLabels = [
    'none'    => t('ops.workspace_profiles.vis.none'),
    'view'    => t('ops.workspace_profiles.vis.view'),
    'work'    => t('ops.workspace_profiles.vis.work'),
    'approve' => t('ops.workspace_profiles.vis.approve'),
    'manage'  => t('ops.workspace_profiles.vis.manage'),
];
?>

  <div class="card u-style-87c136dfd0">
    <h4 class="u-style-eecb702cd2"><?= e(t('ops.workspace_profiles.section_module_vis')) ?></h4>
    <div class="muted u-style-46653d95a4"><?= e(t('ops.workspace_profiles.section_module_vis_note')) ?></div>

    <!-- Hidden textarea that holds the JSON sent to the server. JS keeps it in sync. -->
    <textarea name="module_visibility_json" id="wp_modvis_json" style="display:none" <?= $isSystem ? 'disabled' : '' ?>><?= e($fModVis) ?></textarea>

    <?php if ($matrixApps !== []): ?>
    <div class="ui-block" id="wp_modvis_matrix" style="overflow-x:auto">
      <table class="data-table u-style-512bf91e30">
        <thead>
          <tr>
            <th><?= e(t('ops.workspace_profiles.vis.col_module')) ?></th>
            <?php foreach ($visLevels as $lvl): ?>
              <th class="u-style-54c0282ec0"><?= e($visLevelLabels[$lvl]) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($matrixApps as $appKey => $modules): ?>
            <tr>
              <td colspan="<?= count($visLevels) + 1 ?>" style="background:var(--panel-bg);font-weight:600;font-size:0.82em;padding:6px 12px;color:var(--muted-text)"><?= e(strtoupper($appKey)) ?></td>
            </tr>
            <?php foreach ($modules as $mod): ?>
              <?php
                $mk = (string)($mod['module_key'] ?? '');
                $ml = (string)($mod['module_name'] ?? $mk);
                $currentLevel = (string)($modVisMap[$mk] ?? 'view');
              ?>
              <tr data-module-key="<?= e($mk) ?>">
                <td><?= e($ml) ?> <span class="muted u-style-19dc7c1327"><?= e($mk) ?></span></td>
                <?php foreach ($visLevels as $lvl): ?>
                  <td class="u-style-91a87015f4">
                    <input type="radio" name="modvis_<?= e($mk) ?>" value="<?= e($lvl) ?>"
                           <?= $currentLevel === $lvl ? 'checked' : '' ?>
                           <?= $isSystem ? 'disabled' : '' ?>
                           data-modvis-key="<?= e($mk) ?>"
                           onchange="wpModVisUpdate()">
                  </td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <script>
    (function() {
      function wpModVisUpdate() {
        var map = {};
        document.querySelectorAll('[data-modvis-key]').forEach(function(r) {
          if (r.checked) { map[r.dataset.modvisKey] = r.value; }
        });
        var ta = document.getElementById('wp_modvis_json');
        if (ta) { ta.value = JSON.stringify(map); }
      }
      window.wpModVisUpdate = wpModVisUpdate;
      // Initialize hidden JSON from current radio state on page load.
      wpModVisUpdate();
    })();
    </script>
    <?php else: ?>
    <div class="muted u-style-7a4e3aa359"><?= e(t('ops.workspace_profiles.vis.empty')) ?></div>
    <details class="advanced-subsection u-style-d2c171b18b">
      <summary><strong><?= e(t('ops.workspace_profiles.field_module_vis')) ?></strong> <span class="muted">(<?= e(t('ops.workspace_profiles.vis.fallback_label')) ?>)</span></summary>
      <div class="muted u-style-115b199cd5"><?= e(t('ops.workspace_profiles.field_module_vis_hint')) ?></div>
      <textarea name="module_visibility_json" rows="6"
                style="font-family:monospace;font-size:0.85em;width:100%;box-sizing:border-box"
                placeholder="null"
                <?= $isSystem ? 'disabled' : '' ?>><?= e($fModVis) ?></textarea>
    </details>
    <?php endif; ?>
  </div>

  <div class="card" style="margin-bottom:16px">
    <h4 class="u-style-eecb702cd2"><?= e(t('ops.workspace_profiles.section_json')) ?></h4>
    <div class="muted u-style-46653d95a4"><?= e(t('ops.workspace_profiles.section_json_note')) ?></div>

    <?php
    $jsonFields = [
      'nav_sections'    => [$fNavSections,  'nav_sections_json',    t('ops.workspace_profiles.field_nav_sections'),    t('ops.workspace_profiles.field_nav_sections_hint')],
      'quick_actions'   => [$fQuickActions, 'quick_actions_json',   t('ops.workspace_profiles.field_quick_actions'),           t('ops.workspace_profiles.field_quick_actions_hint')],
      'permissions'     => [$fPermissions,  'permissions_json',     t('ops.workspace_profiles.field_permissions'),             t('ops.workspace_profiles.field_permissions_hint')],
      'dashboard_blocks'=> [$fDashBlocks,   'dashboard_blocks_json', t('ops.workspace_profiles.field_dashboard_blocks'),       t('ops.workspace_profiles.field_dashboard_blocks_hint')],
    ];
    foreach ($jsonFields as [$fieldVal, $fieldName, $fieldLabel, $fieldHint]):
    ?>
    <details class="advanced-subsection">
      <summary><strong><?= e($fieldLabel) ?></strong></summary>
      <div class="muted u-style-115b199cd5"><?= e($fieldHint) ?></div>
      <textarea name="<?= e($fieldName) ?>" rows="6"
                style="font-family:monospace;font-size:0.85em;width:100%;box-sizing:border-box"
                placeholder="null"
                <?= $isSystem ? 'disabled' : '' ?>><?= e($fieldVal) ?></textarea>
    </details>
    <?php endforeach; ?>
  </div>

  <?php if (!$isSystem): ?>
  <div class="row assignment-detail-actions">
    <button type="submit" class="btn ok">
      <?= e($isNew ? t('ops.workspace_profiles.btn_create') : t('ops.workspace_profiles.btn_save')) ?>
    </button>
    <a href="/ops/workspace-profiles" class="btn"><?= e(t('ops.workspace_profiles.btn_cancel')) ?></a>
  </div>
  <?php endif; ?>
</form>

<?php if (!$isNew): ?>
<section class="card u-style-1b0f4999d2">
  <div class="u-style-e6e66287bc">
    <h4 class="u-style-1169661891"><?= e(t('ops.workspace_profiles.col_users')) ?></h4>
    <a class="btn small" href="/ops/access-control?workspace_profile=<?= e($fKey) ?>"><?= e(t('common.open')) ?></a>
  </div>

  <?php if ($pinnedUsers === []): ?>
    <div class="muted u-style-d2c171b18b"><?= e(t('ops.access_control.no_users')) ?></div>
  <?php else: ?>
    <div class="table-wrap u-style-d2c171b18b">
      <table class="data-table">
        <thead>
          <tr>
            <th><?= e(t('ops.access_control.col.user')) ?></th>
            <th><?= e(t('ops.access_control.col.access_authority')) ?></th>
            <th><?= e(t('common.status')) ?></th>
            <th class="u-style-54c2afb7ba"><?= e(t('ops.access_control.col.governance')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pinnedUsers as $u): ?>
            <?php
              $uid = (int)($u['id'] ?? 0);
              $email = (string)($u['email'] ?? '');
              $displayName = trim((string)($u['display_name'] ?? ''));
              $authority = (string)($u['authority_role'] ?? '');
              $status = (string)($u['account_status'] ?? 'active');
              $detailUrl = '/ops/access-control/detail?user_id=' . $uid . '&full=1&tab=access2';
            ?>
            <tr>
              <td>
                <div class="ui-block"><strong><?= e($displayName !== '' ? $displayName : $email) ?></strong></div>
                <?php if ($displayName !== '' && $email !== ''): ?>
                  <div class="muted u-style-8700987962"><?= e($email) ?></div>
                <?php endif; ?>
              </td>
              <td><?= e($authority !== '' ? $authority : 'app_user') ?></td>
              <td><?= e($status) ?></td>
              <td class="u-style-54c2afb7ba">
                <a class="btn small" href="<?= e($detailUrl) ?>"><?= e(t('common.open')) ?></a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if (!$isNew && is_array($resolvedExperienceDiagnostics ?? null)): ?>
  <?php require __DIR__ . '/workspace_profile_diagnostics.php'; ?>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

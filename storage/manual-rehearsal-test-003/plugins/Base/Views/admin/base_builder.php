<?php
$tt = static function (string $key): string {
  return t($key);
};
require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php';

$modules = is_array($modules ?? null) ? $modules : [];
$module = is_array($module ?? null) ? $module : null;
$fields = is_array($fields ?? null) ? $fields : [];
$editingField = is_array($editingField ?? null) ? $editingField : null;
$dbColumns = is_array($dbColumns ?? null) ? $dbColumns : [];
$syncPlans = is_array($syncPlans ?? null) ? $syncPlans : [];
$allDbTables = is_array($allDbTables ?? null) ? $allDbTables : [];
$unregisteredTables = is_array($unregisteredTables ?? null) ? $unregisteredTables : [];
$auditRows = is_array($auditRows ?? null) ? $auditRows : [];
$ok = trim((string)($ok ?? ''));
$err = trim((string)($err ?? ''));
$csrf = \App\Core\Auth::csrfToken();
$selectedModule = (string)($module['module_key'] ?? '');
$fieldDefaults = [
  'id' => 0,
  'field_key' => '',
  'column_name' => '',
  'label' => '',
  'data_type' => 'varchar',
  'max_length' => '',
  'precision_value' => '',
  'scale_value' => '',
  'default_value' => '',
  'options_text' => '',
  'sort_order' => 100,
  'is_required' => 0,
  'is_visible' => 1,
];
$formField = array_merge($fieldDefaults, $editingField ?? []);
?>

<div class="card">
  <div class="row u-style-2d7d2729a4">
    <div class="ui-block">
      <h2 class="u-style-1169661891"><?= e(t('base.db_control.title')) ?></h2>
      <div class="muted"><?= e(t('base.db_control.subtitle')) ?></div>
    </div>
    <a class="btn" href="/admin/apps"><?= e(t('acl.role_detail.back_to_matrix') ?? 'Back') ?></a>
  </div>
</div>

<?php if ($ok !== ''): ?><div class="card notice-ok"><?= e($ok) ?></div><?php endif; ?>
<?php if ($err !== ''): ?><div class="card notice-err"><?= e($err) ?></div><?php endif; ?>

<div class="card">
  <div class="row u-style-1e50654fcb">
    <div class="ui-block">
      <h3 class="u-style-1169661891"><?= e(t('base.db_control.title')) ?></h3>
      <div class="muted"><?= e(t('base.db_control.subtitle')) ?></div>
    </div>
    <div class="muted"><?= e(t('base.db_control.total_tables')) ?>: <?= (int)count($allDbTables) ?> | <?= e(t('base.db_control.unregistered')) ?>: <?= (int)count($unregisteredTables) ?></div>
  </div>
  <form method="post" action="/admin/base/modules/register">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th><?= e(t('base.db_control.select')) ?></th>
            <th><?= e(t('base.db_control.table_name')) ?></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($unregisteredTables as $tbl): ?>
          <tr>
            <td class="u-style-8573bae381"><input type="checkbox" name="tables[]" value="<?= e((string)$tbl) ?>"></td>
            <td><strong><?= e((string)$tbl) ?></strong></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($unregisteredTables)): ?><tr><td colspan="2" class="muted"><?= e(t('base.db_control.module_registry')) ?></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="row u-style-d2c171b18b">
      <button class="btn ok" type="submit"><?= e(t('base.db_control.btn_register')) ?></button>
    </div>
  </form>
</div>

<div class="card">
  <h3 class="u-style-d462248a40"><?= e(t('base.db_control.module_registry')) ?></h3>
  <form method="post" action="/admin/base/schema-sync-bulk" onsubmit="return confirm('<?= e(t('base.db_control.confirm_sync_msg')) ?>');">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th><?= e(t('base.db_control.select')) ?></th>
            <th><?= e(t('base.db_control.module')) ?></th>
            <th><?= e(t('base.db_control.table')) ?></th>
            <th><?= e(t('base.db_control.fields')) ?></th>
            <th><?= e(t('base.db_control.pending_sync')) ?></th>
            <th><?= e(t('base.db_control.status')) ?></th>
            <th><?= e(t('base.db_control.open')) ?></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($modules as $m): ?>
          <?php $mKey = (string)($m['module_key'] ?? ''); ?>
          <?php $plan = is_array($syncPlans[$mKey] ?? null) ? $syncPlans[$mKey] : []; ?>
          <?php $pendingCount = (int)($plan['pending_count'] ?? 0); ?>
          <?php $syncError = trim((string)($plan['error'] ?? '')); ?>
          <tr>
            <td class="u-style-8573bae381"><input type="checkbox" name="module_keys[]" value="<?= e($mKey) ?>"></td>
            <td><strong><?= e((string)$m['label']) ?></strong><div class="muted"><?= e($mKey) ?></div></td>
            <td><?= e((string)$m['table_name']) ?></td>
            <td><?= (int)($m['active_fields'] ?? 0) ?> active / <?= (int)($m['total_fields'] ?? 0) ?> total</td>
            <td>
              <?php if ($syncError !== ''): ?>
                <span class="muted" title="<?= e($syncError) ?>"><?= e(t('base.db_control.error')) ?></span>
              <?php else: ?>
                <?= $pendingCount ?>
              <?php endif; ?>
            </td>
            <td><?= ((int)($m['is_active'] ?? 0) === 1) ? e(t('base.db_control.active')) : e(t('base.db_control.inactive')) ?></td>
            <td><a class="btn" href="/admin/base?module=<?= e($mKey) ?>"><?= e(t('base.db_control.manage')) ?></a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="row u-style-6b3b0bf0ac">
      <input class="u-style-66dbd5d0ad" name="confirm_sync" placeholder="<?= e(t('base.db_control.sync_placeholder')) ?>">
      <button class="btn" type="submit" name="sync_mode" value="dry_run"><?= e(t('base.db_control.btn_dry_run')) ?></button>
      <button class="btn ok" type="submit" name="sync_mode" value="apply"><?= e(t('base.db_control.btn_apply')) ?></button>
    </div>
  </form>
  </div>


<?php if ($module): ?>
<div class="card">
  <div class="row u-style-13c1049e8a">
    <div class="ui-block">
      <h3 class="u-style-1169661891"><?= e(t('base.db_control.field_registry')) ?>: <?= e((string)$module['label']) ?></h3>
      <div class="muted"><?= e(t('base.db_control.target_table')) ?>: <?= e((string)$module['table_name']) ?></div>
    </div>
    <form class="u-style-58703f152a" method="post" action="/admin/base/schema-sync" onsubmit="return confirm(<?= json_encode(t('base.db_control.confirm_schema_sync')) ?>);">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="module_key" value="<?= e($selectedModule) ?>">
      <input class="u-style-94253f99ba" name="confirm_sync" placeholder="<?= e(t('base.db_control.sync_placeholder')) ?>">
      <button class="btn" type="submit" name="sync_mode" value="dry_run"><?= e(t('base.db_control.btn_dry_run')) ?></button>
      <button class="btn ok" type="submit" name="sync_mode" value="apply"><?= e(t('base.db_control.btn_apply')) ?></button>
    </form>
  </div>

  <div class="table-wrap u-style-56f4356299">
    <table>
      <thead>
        <tr>
          <th><?= e(t('base.db_control.field_key')) ?></th>
          <th><?= e(t('base.db_control.column')) ?></th>
          <th><?= e(t('base.db_control.type')) ?></th>
          <th><?= e(t('base.db_control.required')) ?></th>
          <th><?= e(t('base.db_control.visible')) ?></th>
          <th><?= e(t('base.db_control.default')) ?></th>
          <th><?= e(t('base.db_control.status')) ?></th>
          <th><?= e(t('base.db_control.synced')) ?></th>
          <th><?= e(t('base.db_control.actions')) ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($fields as $f): ?>
        <tr>
          <td><strong><?= e((string)$f['field_key']) ?></strong><div class="muted"><?= e((string)$f['label']) ?></div></td>
          <td><?= e((string)$f['column_name']) ?></td>
          <td><?= e((string)$f['data_type']) ?></td>
          <td><?= ((int)($f['is_required'] ?? 0) === 1) ? e(t('setup.core.yes_label')) : e(t('setup.core.no_label')) ?></td>
          <td><?= ((int)($f['is_visible'] ?? 0) === 1) ? e(t('setup.core.yes_label')) : e(t('setup.core.no_label')) ?></td>
          <td><?= e((string)($f['default_value'] ?? '')) ?></td>
          <td><?= e((string)($f['status'] ?? '')) ?></td>
          <td><?= e((string)($f['last_synced_at'] ?? '')) ?></td>
          <td>
            <a class="btn" href="/admin/base?module=<?= e($selectedModule) ?>&field_id=<?= (int)$f['id'] ?>"><?= e(t('base.db_control.edit')) ?></a>
            <?php if ((string)($f['status'] ?? '') !== 'disabled'): ?>
            <form class="u-style-1169661891" method="post" action="/admin/base/field/disable" onsubmit="return confirm(<?= json_encode(t('base.db_control.confirm_disable_field')) ?>);">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="module_key" value="<?= e($selectedModule) ?>">
              <input type="hidden" name="field_id" value="<?= (int)$f['id'] ?>">
              <button class="btn danger" type="submit"><?= e(t('base.db_control.disable')) ?></button>
            </form>
            <?php else: ?>
              <span class="muted"><?= e(t('base.db_control.inactive')) ?></span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($fields)): ?><tr><td colspan="9" class="muted"> <?= e($tt('base.no_field_metadata_label')) ?> </td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="row u-style-f05af8e852">
    <h4 class="u-style-1169661891"><?= e(t(((int)($formField['id'] ?? 0) > 0) ? 'base.db_control.edit_field_metadata' : 'base.db_control.add_field_metadata')) ?></h4>
    <?php if ((int)($formField['id'] ?? 0) > 0): ?>
      <a class="btn" href="/admin/base?module=<?= e($selectedModule) ?>"><?= e(t('base.db_control.new_field')) ?></a>
    <?php endif; ?>
  </div>
  <form method="post" action="/admin/base/field/save" class="row">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="module_key" value="<?= e($selectedModule) ?>">
    <input type="hidden" name="field_id" value="<?= (int)($formField['id'] ?? 0) ?>">
    <div class="u-style-2f7ab6d1f3"><label class="muted u-style-98847c28df"><?= e(t('base.db_control.field_key')) ?> *</label><input class="input" name="field_key" required placeholder="parts.color" value="<?= e((string)($formField['field_key'] ?? '')) ?>"></div>
    <div class="u-style-2f7ab6d1f3"><label class="muted u-style-98847c28df"><?= e(t('base.db_control.column_name')) ?> *</label><input class="input" name="column_name" required placeholder="color" value="<?= e((string)($formField['column_name'] ?? '')) ?>"></div>
    <div class="u-style-2f7ab6d1f3"><label class="muted u-style-98847c28df"><?= e(t('base.db_control.label')) ?> *</label><input class="input" name="label" required placeholder="<?= e(t('base.db_control.label_placeholder')) ?>" value="<?= e((string)($formField['label'] ?? '')) ?>"></div>
    <div class="u-style-eb62184e30"><label class="muted u-style-98847c28df"><?= e(t('base.db_control.data_type')) ?> *</label>
      <select name="data_type">
        <?php foreach (['varchar', 'text', 'int', 'bigint', 'decimal', 'date', 'datetime', 'tinyint', 'json'] as $type): ?>
          <option value="<?= e($type) ?>" <?= ((string)($formField['data_type'] ?? 'varchar') === $type) ? 'selected' : '' ?>><?= e($type) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="u-style-f09611ef0f"><label class="muted u-style-98847c28df"><?= e(t('base.db_control.length')) ?></label><input class="input" type="number" min="1" name="max_length" placeholder="190" value="<?= e((string)($formField['max_length'] ?? '')) ?>"></div>
    <div class="u-style-f09611ef0f"><label class="muted u-style-98847c28df"><?= e(t('base.db_control.precision')) ?></label><input class="input" type="number" min="1" name="precision_value" placeholder="14" value="<?= e((string)($formField['precision_value'] ?? '')) ?>"></div>
    <div class="u-style-f09611ef0f"><label class="muted u-style-98847c28df"><?= e(t('base.db_control.scale')) ?></label><input class="input" type="number" min="0" name="scale_value" placeholder="2" value="<?= e((string)($formField['scale_value'] ?? '')) ?>"></div>
    <div class="u-style-eb62184e30"><label class="muted u-style-98847c28df"><?= e(t('base.db_control.default')) ?></label><input class="input" name="default_value" placeholder="<?= e(t('base.db_control.default_placeholder')) ?>" value="<?= e((string)($formField['default_value'] ?? '')) ?>"></div>
    <div class="u-style-6c0d09d5fa"><label class="muted u-style-98847c28df"><?= e(t('base.db_control.options')) ?></label><input class="input" name="options_text" placeholder="<?= e(t('base.db_control.options_placeholder')) ?>" value="<?= e((string)($formField['options_text'] ?? '')) ?>"></div>
    <div class="u-style-f09611ef0f"><label class="muted u-style-98847c28df"><?= e(t('base.db_control.sort')) ?></label><input class="input" type="number" min="1" name="sort_order" value="<?= (int)($formField['sort_order'] ?? 100) ?>"></div>
    <div class="u-style-f09611ef0f"><label class="muted u-style-98847c28df"><?= e(t('base.db_control.required')) ?></label><select name="is_required"><option value="0" <?= ((int)($formField['is_required'] ?? 0) === 0) ? 'selected' : '' ?>><?= e(t('setup.core.no_label')) ?></option><option value="1" <?= ((int)($formField['is_required'] ?? 0) === 1) ? 'selected' : '' ?>><?= e(t('setup.core.yes_label')) ?></option></select></div>
    <div class="u-style-f09611ef0f"><label class="muted u-style-98847c28df"><?= e(t('base.db_control.visible')) ?></label><select name="is_visible"><option value="1" <?= ((int)($formField['is_visible'] ?? 1) === 1) ? 'selected' : '' ?>><?= e(t('setup.core.yes_label')) ?></option><option value="0" <?= ((int)($formField['is_visible'] ?? 1) === 0) ? 'selected' : '' ?>><?= e(t('setup.core.no_label')) ?></option></select></div>
    <div class="row u-style-0466783d98">
      <button class="btn ok" type="submit"><?= e(t(((int)($formField['id'] ?? 0) > 0) ? 'base.db_control.update_field_metadata' : 'base.db_control.save_field_metadata')) ?></button>
    </div>
  </form>
</div>

<div class="card">
  <h3 class="u-style-d462248a40"><?= e(t('base.db_control.db_column_snapshot')) ?> (<?= e((string)$module['table_name']) ?>)</h3>
  <div class="table-wrap">
    <table>
      <thead><tr><th><?= e(t('base.db_control.column')) ?></th><th><?= e(t('base.db_control.type')) ?></th><th><?= e(t('base.db_control.null')) ?></th><th><?= e(t('base.db_control.default')) ?></th><th><?= e(t('base.db_control.extra')) ?></th></tr></thead>
      <tbody>
      <?php foreach ($dbColumns as $col): ?>
        <tr>
          <td><?= e((string)($col['Field'] ?? '')) ?></td>
          <td><?= e((string)($col['Type'] ?? '')) ?></td>
          <td><?= e((string)($col['Null'] ?? '')) ?></td>
          <td><?= e((string)($col['Default'] ?? '')) ?></td>
          <td><?= e((string)($col['Extra'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($dbColumns)): ?><tr><td colspan="5" class="muted"> <?= e($tt('base.no_db_column_label')) ?> </td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <h3 class="u-style-d462248a40"><?= e(t('base.db_control.audit_log')) ?></h3>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th><?= e(t('base.db_control.id')) ?></th>
          <th><?= e(t('base.db_control.action')) ?></th>
          <th><?= e(t('base.db_control.module')) ?></th>
          <th><?= e(t('base.db_control.field_id')) ?></th>
          <th><?= e(t('base.db_control.changed_by')) ?></th>
          <th><?= e(t('base.db_control.payload')) ?></th>
          <th><?= e(t('base.db_control.at')) ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($auditRows as $r): ?>
        <tr>
          <td><?= (int)($r['id'] ?? 0) ?></td>
          <td><?= e((string)($r['action_type'] ?? '')) ?></td>
          <td><?= e((string)($r['module_key'] ?? '')) ?></td>
          <td><?= e((string)($r['field_id'] ?? '')) ?></td>
          <td><?= e((string)($r['changed_by'] ?? '')) ?></td>
          <td><pre class="u-style-3e76f74824"><?= e((string)($r['payload_json'] ?? '')) ?></pre></td>
          <td><?= e((string)($r['created_at'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($auditRows)): ?><tr><td colspan="7" class="muted">No audit entries yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

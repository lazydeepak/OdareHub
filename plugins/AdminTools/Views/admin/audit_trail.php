<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="u-style-e2ff0fd1a7">
      <a class="u-style-e3220afdb1" href="/admin/system-tools/data-control">
        <?= e(t('system_tools.data_control.title')) ?>
      </a>
      <span class="u-style-4cb80da29d">›</span>
      <h2 class="u-style-1169661891"><?= e(t('system_tools.data_control.audit_trail_title')) ?></h2>
    </div>
    <div class="muted"><?= e(t('system_tools.data_control.audit_subtitle')) ?></div>
  </div>
</div>

<div class="card">
  <form class="u-style-9bf647d04d" method="GET" action="/admin/system-tools/data-control/audit-trail">
    <div class="ui-block">
      <label class="u-style-b64766877a"><?= e(t('system_tools.data_control.audit_filter_op')) ?></label>
      <select class="u-style-7876eca679" name="op">
        <option value=""><?= e(t('common.all')) ?></option>
        <option value="submit_for_approval"<?= ($filterOp === 'submit_for_approval' ? ' selected' : '') ?>><?= e(t('system_tools.data_control.audit_op_submit')) ?></option>
        <option value="approve_mutation"<?= ($filterOp === 'approve_mutation' ? ' selected' : '') ?>><?= e(t('system_tools.data_control.audit_op_approve')) ?></option>
        <option value="reject_mutation"<?= ($filterOp === 'reject_mutation' ? ' selected' : '') ?>><?= e(t('system_tools.data_control.audit_op_reject')) ?></option>
        <option value="execute_mutation"<?= ($filterOp === 'execute_mutation' ? ' selected' : '') ?>><?= e(t('system_tools.data_control.audit_op_execute')) ?></option>
      </select>
    </div>
    <div class="ui-block">
      <label class="u-style-b64766877a"><?= e(t('system_tools.data_control.audit_filter_from')) ?></label>
      <input class="input" type="date" name="from" value="<?= e($dateFrom) ?>" style="padding: 6px 8px; border: 1px solid var(--style-border-soft); border-radius: 4px;">
    </div>
    <div class="ui-block">
      <label class="u-style-b64766877a"><?= e(t('system_tools.data_control.audit_filter_to')) ?></label>
      <input class="input" type="date" name="to" value="<?= e($dateTo) ?>" style="padding: 6px 8px; border: 1px solid var(--style-border-soft); border-radius: 4px;">
    </div>
    <div class="ui-block">
      <button class="u-style-8844e7cfd8" type="submit"><?= e(t('common.filter')) ?></button>
      <?php if ($filterOp !== '' || $filterUser > 0 || $dateFrom !== '' || $dateTo !== ''): ?>
        <a class="u-style-943576fb89" href="/admin/system-tools/data-control/audit-trail"><?= e(t('common.clear')) ?></a>
      <?php endif; ?>
    </div>
  </form>
</div>

<div class="card">
  <h3 class="u-style-d462248a40"><?= e(t('system_tools.data_control.audit_records')) ?></h3>
  
  <?php if (!empty($logs)): ?>
    <div class="u-style-0082b38792">
      <table class="table">
        <thead>
          <tr>
            <th><?= e(t('system_tools.data_control.audit_timestamp')) ?></th>
            <th><?= e(t('system_tools.data_control.audit_operation')) ?></th>
            <th><?= e(t('system_tools.data_control.audit_table')) ?></th>
            <th><?= e(t('system_tools.data_control.audit_rows_affected')) ?></th>
            <th><?= e(t('system_tools.data_control.audit_user_id')) ?></th>
            <th><?= e(t('system_tools.data_control.audit_status')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $log): ?>
            <tr>
              <td><?= e((string)($log['created_at'] ?? '')) ?></td>
              <td>
                <?php
                  $op = (string)($log['operation'] ?? '');
                  if ($op === 'submit_for_approval') {
                    echo '<span class="u-style-01b0c10602">📤 ' . e(t('system_tools.data_control.audit_op_submit')) . '</span>';
                  } elseif ($op === 'approve_mutation') {
                    echo '<span class="u-style-4496b84eb3">✓ ' . e(t('system_tools.data_control.audit_op_approve')) . '</span>';
                  } elseif ($op === 'reject_mutation') {
                    echo '<span class="u-style-17472edabe">✕ ' . e(t('system_tools.data_control.audit_op_reject')) . '</span>';
                  } elseif ($op === 'execute_mutation') {
                    echo '<span class="u-style-4066493f51">⚡ ' . e(t('system_tools.data_control.audit_op_execute')) . '</span>';
                  }
                ?>
              </td>
              <td><?= e((string)($log['table_name'] ?? '')) ?></td>
              <td><?= number_format((int)($log['affected_rows'] ?? 0)) ?></td>
              <td><?= e((string)($log['user_email'] ?? (string)($log['user_id'] ?? ''))) ?></td>
              <td>
                <?php
                  $status = (string)($log['status'] ?? 'completed');
                  if ($status === 'submitted') {
                    echo '<span class="u-style-7ed8ea383b">⏳ ' . e(t('system_tools.data_control.status_submitted')) . '</span>';
                  } elseif ($status === 'completed') {
                    echo '<span class="u-style-e8a23e5da9">✓ ' . e(t('common.completed')) . '</span>';
                  } elseif ($status === 'approved') {
                    echo '<span class="u-style-e8a23e5da9">✓ ' . e(t('system_tools.data_control.status_approved')) . '</span>';
                  } elseif ($status === 'reject') {
                    echo '<span class="u-style-47447fe1b7">✕ ' . e(t('system_tools.data_control.status_rejected')) . '</span>';
                  } elseif ($status === 'executed') {
                    echo '<span class="u-style-f6e3d34814">⚡ ' . e(t('system_tools.data_control.status_executed')) . '</span>';
                  } elseif ($status === 'failed') {
                    echo '<span class="u-style-69c0b269d1">✗ ' . e(t('system_tools.data_control.status_failed')) . '</span>';
                  }
                ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="muted"><?= e(t('system_tools.data_control.no_audit_records')) ?></div>
  <?php endif; ?>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

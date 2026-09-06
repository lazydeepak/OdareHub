<?php
  $dcOk = trim((string)($_GET['dc_ok'] ?? ''));
  $dcErr = trim((string)($_GET['dc_err'] ?? ''));
  $dcFlashMap = [
      'submitted' => t('system_tools.data_control.flash_submitted'),
      'approved'  => t('system_tools.data_control.flash_approved'),
      'rejected'  => t('system_tools.data_control.flash_rejected'),
      'executed'  => t('system_tools.data_control.flash_executed'),
  ];
  $dcErrMap = [
      'exec_failed'   => t('system_tools.data_control.flash_exec_failed'),
      'duplicate'     => t('system_tools.data_control.flash_duplicate'),
      'missing_set'   => t('system_tools.data_control.flash_missing_set'),
      'invalid_input' => t('system_tools.data_control.flash_invalid_input'),
  ];
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="u-style-e2ff0fd1a7">
      <a class="u-style-e3220afdb1" href="/admin/system-tools/data-control">
        <?= e(t('system_tools.data_control.title')) ?>
      </a>
      <span class="u-style-4cb80da29d">›</span>
      <h2 class="u-style-1169661891"><?= e(t('system_tools.data_control.governance_title')) ?></h2>
    </div>
    <div class="muted"><?= e(t('system_tools.data_control.approvals_subtitle')) ?></div>
  </div>
</div>

<?php if ($dcOk !== '' && isset($dcFlashMap[$dcOk])): ?>
  <div class="card u-style-6041afda24">
    <div class="u-style-01b0c10602"><?= e(t('common.success')) ?></div>
    <div class="u-style-c71373e1e7"><?= e((string)$dcFlashMap[$dcOk]) ?></div>
  </div>
<?php elseif ($dcErr !== '' && isset($dcErrMap[$dcErr])): ?>
  <div class="card u-style-be3d6c3c87">
    <div class="u-style-17472edabe"><?= e(t('common.error')) ?></div>
    <div class="u-style-c71373e1e7"><?= e((string)$dcErrMap[$dcErr]) ?></div>
  </div>
<?php elseif (!empty($message)): ?>
  <div class="card u-style-6041afda24">
    <div class="u-style-01b0c10602"><?= e(t('common.success')) ?></div>
    <div class="u-style-36ca0aff10"><?= e($message) ?></div>
  </div>
<?php endif; ?>

<div class="card">
  <h3 class="u-style-d462248a40"><?= e(t('system_tools.data_control.pending_mutations')) ?></h3>
  
  <?php if (!empty($approvals)): ?>
    <div class="u-style-0082b38792">
      <table class="table">
        <thead>
          <tr>
            <th><?= e(t('system_tools.data_control.approval_table')) ?></th>
            <th><?= e(t('system_tools.data_control.approval_operation')) ?></th>
            <th><?= e(t('system_tools.data_control.approval_rows_affected')) ?></th>
            <th><?= e(t('system_tools.data_control.approval_requested_by')) ?></th>
            <th><?= e(t('system_tools.data_control.approval_requested_at')) ?></th>
            <th><?= e(t('system_tools.data_control.approval_status')) ?></th>
            <th><?= e(t('common.actions')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($approvals as $approval): ?>
            <tr>
              <td>
                <a href="/admin/system-tools/data-control/approval-detail?id=<?= (int)($approval['id'] ?? 0) ?>" style="text-decoration: none; color: var(--text);">
                  <?= e((string)($approval['table_name'] ?? '')) ?>
                </a>
              </td>
              <td>
                <?php
                  $op = (string)($approval['operation'] ?? '');
                  if ($op === 'delete') {
                    echo '<span class="u-style-58b279bf09">🗑 ' . e(t('system_tools.data_control.operation_delete')) . '</span>';
                  } elseif ($op === 'update') {
                    echo '<span class="u-style-4066493f51">✎ ' . e(t('system_tools.data_control.operation_update')) . '</span>';
                  }
                ?>
              </td>
              <td><?= number_format((int)($approval['affected_rows'] ?? 0)) ?></td>
              <td><?= e((string)($approval['requested_by_email'] ?? $approval['requested_by'] ?? '')) ?></td>
              <td><?= e((string)($approval['requested_at'] ?? '')) ?></td>
              <td>
                <?php
                  $status = (string)($approval['status'] ?? 'pending');
                  if ($status === 'pending') {
                    echo '<span class="u-style-a9750cf93a">⏳ ' . e(t('system_tools.data_control.status_pending')) . '</span>';
                  } elseif ($status === 'approved') {
                    echo '<span class="u-style-5e5c4b405c">✓ ' . e(t('system_tools.data_control.status_approved')) . '</span>';
                  } elseif ($status === 'rejected') {
                    echo '<span class="u-style-590f5b2801">✕ ' . e(t('system_tools.data_control.status_rejected')) . '</span>';
                  } elseif ($status === 'executed') {
                    echo '<span class="u-style-c1f1843640">✔ ' . e(t('system_tools.data_control.status_executed')) . '</span>';
                  } elseif ($status === 'failed') {
                    echo '<span class="u-style-1eb5ded020">✗ ' . e(t('system_tools.data_control.status_failed')) . '</span>';
                  }
                ?>
              </td>
              <td>
                <?php if ($status === 'pending'): ?>
                  <form class="u-style-434fc32ec2" method="POST" action="/admin/system-tools/data-control/approve-mutation">
                    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
                    <input type="hidden" name="approval_id" value="<?= (int)($approval['id'] ?? 0) ?>">
                    <input type="hidden" name="action" value="approve">
                    <button class="u-style-60206b0ec3" type="submit">
                      <?= e(t('system_tools.data_control.action_approve')) ?>
                    </button>
                  </form>
                  <form class="u-style-85de73fdbb" method="POST" action="/admin/system-tools/data-control/approve-mutation">
                    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
                    <input type="hidden" name="approval_id" value="<?= (int)($approval['id'] ?? 0) ?>">
                    <input type="hidden" name="action" value="reject">
                    <button class="u-style-e9c13f53d6" type="submit">
                      <?= e(t('system_tools.data_control.action_reject')) ?>
                    </button>
                  </form>
                <?php elseif ($status === 'approved'): ?>
                  <form class="u-style-434fc32ec2" method="POST" action="/admin/system-tools/data-control/execute-mutation">
                    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
                    <input type="hidden" name="approval_id" value="<?= (int)($approval['id'] ?? 0) ?>">
                    <button class="u-style-a9b137c9e9" type="submit">
                      <?= e(t('system_tools.data_control.action_execute')) ?>
                    </button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="muted"><?= e(t('system_tools.data_control.no_pending_approvals')) ?></div>
  <?php endif; ?>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

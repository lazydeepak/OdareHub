<?php
  $approvalId = (int)($record['id'] ?? 0);
  $tableName  = (string)($record['table_name'] ?? '');
  $operation  = (string)($record['operation'] ?? '');
  $whereClause = (string)($record['where_clause'] ?? '');
  $setClause  = (string)($record['set_clause'] ?? '');
  $affectedRows = (int)($record['affected_rows'] ?? 0);
  $requestedBy  = (string)($record['requested_by_email'] ?? $record['requested_by'] ?? '');
  $requestedAt  = (string)($record['requested_at'] ?? '');
  $status       = (string)($record['status'] ?? '');
  $approvedBy   = (string)($record['approved_by_email'] ?? $record['approved_by'] ?? '');
  $approvedAt   = (string)($record['approved_at'] ?? '');
  $executedBy   = (string)($record['executed_by_email'] ?? $record['executed_by'] ?? '');
  $executedAt   = (string)($record['executed_at'] ?? '');
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="u-style-c960f603da">
      <a class="u-style-e3220afdb1" href="/admin/system-tools/data-control">
        <?= e(t('system_tools.data_control.title')) ?>
      </a>
      <span class="u-style-4cb80da29d">›</span>
      <a class="u-style-e3220afdb1" href="/admin/system-tools/data-control/approvals">
        <?= e(t('system_tools.data_control.governance_title')) ?>
      </a>
      <span class="u-style-4cb80da29d">›</span>
      <h2 class="u-style-1169661891"><?= e(t('system_tools.data_control.approval_detail_title')) ?> #<?= $approvalId ?></h2>
    </div>
  </div>
</div>

<div class="card">
  <h3 class="u-style-d462248a40"><?= e(t('system_tools.data_control.approval_request_info')) ?></h3>
  <table class="table u-style-bde631ff20">
    <tbody>
      <tr>
        <th class="u-style-8506681389"><?= e(t('system_tools.data_control.approval_table')) ?></th>
        <td><code><?= e($tableName) ?></code></td>
      </tr>
      <tr>
        <th><?= e(t('system_tools.data_control.approval_operation')) ?></th>
        <td>
          <?php if ($operation === 'delete'): ?>
            <span class="u-style-58b279bf09"><?= e(t('system_tools.data_control.operation_delete')) ?></span>
          <?php elseif ($operation === 'update'): ?>
            <span class="u-style-4066493f51"><?= e(t('system_tools.data_control.operation_update')) ?></span>
          <?php else: ?>
            <?= e($operation) ?>
          <?php endif; ?>
        </td>
      </tr>
      <tr>
        <th><?= e(t('system_tools.data_control.filter_condition')) ?></th>
        <td>
          <?php if ($whereClause !== ''): ?>
            <code><?= e($whereClause) ?></code>
          <?php else: ?>
            <span class="u-style-272baef098"><?= e(t('system_tools.data_control.no_filter_warning')) ?></span>
          <?php endif; ?>
        </td>
      </tr>
      <?php if ($operation === 'update' && $setClause !== ''): ?>
        <tr>
          <th><?= e(t('system_tools.data_control.set_clause_label')) ?></th>
          <td><code><?= e($setClause) ?></code></td>
        </tr>
      <?php endif; ?>
      <tr>
        <th><?= e(t('system_tools.data_control.affected_rows_count')) ?></th>
        <td>
          <?= number_format($affectedRows) ?> <?= e(t('system_tools.data_control.approval_rows_stored')) ?>
          <?php if ($status === 'pending' || $status === 'approved'): ?>
            &nbsp;<span class="u-style-b2f262ccb5">(<?= e(t('system_tools.data_control.live_count_label')) ?>: <?= number_format($liveCount) ?>)</span>
          <?php endif; ?>
        </td>
      </tr>
      <tr>
        <th><?= e(t('system_tools.data_control.approval_requested_by')) ?></th>
        <td><?= e($requestedBy) ?></td>
      </tr>
      <tr>
        <th><?= e(t('system_tools.data_control.approval_requested_at')) ?></th>
        <td><?= e($requestedAt) ?></td>
      </tr>
      <tr>
        <th><?= e(t('system_tools.data_control.approval_status')) ?></th>
        <td>
          <?php
            if ($status === 'pending') {
              echo '<span class="u-style-4051dd3410">' . e(t('system_tools.data_control.status_pending')) . '</span>';
            } elseif ($status === 'approved') {
              echo '<span class="u-style-13fc269872">' . e(t('system_tools.data_control.status_approved')) . '</span>';
            } elseif ($status === 'rejected') {
              echo '<span class="u-style-6b2409e6c5">' . e(t('system_tools.data_control.status_rejected')) . '</span>';
            } elseif ($status === 'executed') {
              echo '<span class="u-style-87ae7d9a10">' . e(t('system_tools.data_control.status_executed')) . '</span>';
            } elseif ($status === 'failed') {
              echo '<span class="u-style-6b2409e6c5">' . e(t('system_tools.data_control.status_failed')) . '</span>';
            }
          ?>
        </td>
      </tr>
      <?php if ($approvedBy !== ''): ?>
        <tr>
          <th><?= e(t('system_tools.data_control.approval_reviewed_by')) ?></th>
          <td><?= e($approvedBy) ?> &mdash; <?= e($approvedAt) ?></td>
        </tr>
      <?php endif; ?>
      <?php if ($executedBy !== ''): ?>
        <tr>
          <th><?= e(t('system_tools.data_control.approval_executed_by')) ?></th>
          <td><?= e($executedBy) ?> &mdash; <?= e($executedAt) ?></td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if (!empty($sampleError)): ?>
  <div class="card u-style-6817fd7d1c">
    <div class="u-style-abf8e01596"><?= e(t('system_tools.data_control.live_sample_error')) ?>: <?= e($sampleError) ?></div>
  </div>
<?php elseif (!empty($liveSample)): ?>
  <div class="card">
    <h3 class="u-style-d462248a40"><?= e(t('system_tools.data_control.live_sample_title')) ?> <span class="u-style-511c2ae644">(<?= e(t('system_tools.data_control.showing_first_n_rows', ['n' => count($liveSample)])) ?>)</span></h3>
    <div class="u-style-0082b38792">
      <table class="table u-style-c4e3967ba4">
        <thead>
          <tr>
            <?php foreach (array_keys($liveSample[0]) as $col): ?>
              <th><?= e((string)$col) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($liveSample as $row): ?>
            <tr>
              <?php foreach ($row as $cell): ?>
                <td class="u-style-3a6f1ce15a"><?= e((string)$cell) ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php elseif ($status === 'pending' || $status === 'approved'): ?>
  <div class="card">
    <div class="muted"><?= e(t('system_tools.data_control.no_live_rows_found')) ?></div>
  </div>
<?php endif; ?>

<?php if ($status === 'pending'): ?>
  <div class="card">
    <h3 class="u-style-d462248a40"><?= e(t('system_tools.data_control.approve_or_reject')) ?></h3>
    <div class="u-style-d6316a7e0b">
      <form method="POST" action="/admin/system-tools/data-control/approve-mutation">
        <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
        <input type="hidden" name="approval_id" value="<?= $approvalId ?>">
        <input type="hidden" name="action" value="approve">
        <button class="u-style-729d3358ac" type="submit">
          <?= e(t('system_tools.data_control.action_approve')) ?>
        </button>
      </form>
      <form method="POST" action="/admin/system-tools/data-control/approve-mutation">
        <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
        <input type="hidden" name="approval_id" value="<?= $approvalId ?>">
        <input type="hidden" name="action" value="reject">
        <button class="u-style-bbb64f958f" type="submit">
          <?= e(t('system_tools.data_control.action_reject')) ?>
        </button>
      </form>
    </div>
  </div>
<?php elseif ($status === 'approved'): ?>
  <div class="card u-style-bcb7dd8818">
    <h3 class="u-style-de53de253d"><?= e(t('system_tools.data_control.execute_warning_title')) ?></h3>
    <p class="u-style-b2f262ccb5"><?= e(t('system_tools.data_control.execute_warning_body')) ?></p>
    <form method="POST" action="/admin/system-tools/data-control/execute-mutation">
      <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
      <input type="hidden" name="approval_id" value="<?= $approvalId ?>">
      <button class="u-style-6153dc52d9" type="submit">
        <?= e(t('system_tools.data_control.action_execute')) ?>
      </button>
    </form>
  </div>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

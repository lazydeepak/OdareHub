<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$runs = is_array($runs ?? null) ? $runs : [];
$csrf = (string)($csrf ?? '');
?>
<?php $setupNavCurrent = 'audit'; require __DIR__ . '/_nav.php'; ?>
<?php require __DIR__ . '/_flash.php'; ?>

<div class="card">
  <h2 style="margin:0 0 8px">Setup Audit</h2>
  <div class="muted">Recent setup history across Core, Suites, and Modules. This view is for status, rollback visibility, and recovery only. Destructive uninstall/purge actions stay elsewhere.</div>
</div>

<div style="display:grid;gap:12px">
  <?php foreach ($runs as $run): ?>
    <div class="card" style="margin:0">
      <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start">
        <div>
          <h3 style="margin:0 0 6px"><?= e((string)($run['target_type'] ?? 'target')) ?> / <?= e((string)($run['target_key'] ?? '')) ?></h3>
          <div class="muted">Action: <?= e((string)($run['action_key'] ?? '')) ?> · Started: <?= e((string)($run['started_at'] ?? '')) ?> · Finished: <?= e((string)($run['finished_at'] ?? '')) ?></div>
        </div>
        <span class="pill" style="<?= e((string)($run['status_tone'] ?? '')) ?>"><?= e((string)($run['status_label'] ?? '')) ?></span>
      </div>

      <?php if (trim((string)($run['error_text'] ?? '')) !== ''): ?>
        <div style="margin-top:10px">Error: <?= e((string)($run['error_text'] ?? '')) ?></div>
      <?php endif; ?>
      <?php if (!empty($run['failed_step']['step_label'])): ?>
        <div class="muted" style="margin-top:6px">Failed step: <?= e((string)($run['failed_step']['step_label'] ?? '')) ?></div>
      <?php endif; ?>
      <div class="muted" style="margin-top:6px">Rollback performed: <?= !empty($run['rollback_performed']) ? 'Yes' : 'No' ?></div>
      <?php if (trim((string)($run['manual_attention_text'] ?? '')) !== ''): ?>
        <div class="muted" style="margin-top:6px">Manual attention: <?= e((string)($run['manual_attention_text'] ?? '')) ?></div>
      <?php endif; ?>

      <div class="table-wrap" style="margin-top:12px">
        <table>
          <thead>
            <tr>
              <th>Step</th>
              <th>Status</th>
              <th>Started</th>
              <th>Finished</th>
              <th>Result / Error</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ((array)($run['steps'] ?? []) as $step): ?>
              <tr>
                <td><?= e((string)($step['step_label'] ?? $step['step_key'] ?? '')) ?></td>
                <td><?= e((string)($step['status'] ?? 'pending')) ?></td>
                <td><?= e((string)($step['started_at'] ?? '')) ?></td>
                <td><?= e((string)($step['finished_at'] ?? '')) ?></td>
                <td>
                  <?php if (trim((string)($step['error_text'] ?? '')) !== ''): ?>
                    <?= e((string)($step['error_text'] ?? '')) ?>
                  <?php else: ?>
                    <?= e((string)($step['result_text'] ?? '')) ?>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
        <form method="post" action="/admin/setup/recover">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="target_type" value="<?= e((string)($run['target_type'] ?? '')) ?>">
          <input type="hidden" name="target_key" value="<?= e((string)($run['target_key'] ?? '')) ?>">
          <input type="hidden" name="mode" value="resume">
          <button class="btn ok" type="submit">Resume Setup</button>
        </form>
        <form method="post" action="/admin/setup/recover">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="target_type" value="<?= e((string)($run['target_type'] ?? '')) ?>">
          <input type="hidden" name="target_key" value="<?= e((string)($run['target_key'] ?? '')) ?>">
          <input type="hidden" name="mode" value="retry">
          <button class="btn" type="submit">Retry Failed Step</button>
        </form>
        <form method="post" action="/admin/setup/recover">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="target_type" value="<?= e((string)($run['target_type'] ?? '')) ?>">
          <input type="hidden" name="target_key" value="<?= e((string)($run['target_key'] ?? '')) ?>">
          <input type="hidden" name="mode" value="verify">
          <button class="btn" type="submit">Run Verification Again</button>
        </form>
        <form method="post" action="/admin/setup/recover">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="target_type" value="<?= e((string)($run['target_type'] ?? '')) ?>">
          <input type="hidden" name="target_key" value="<?= e((string)($run['target_key'] ?? '')) ?>">
          <input type="hidden" name="mode" value="repair">
          <button class="btn" type="submit">Repair Current Target</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

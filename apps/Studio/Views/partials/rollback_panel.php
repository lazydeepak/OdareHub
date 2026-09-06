<?php
$tt = static function (string $key): string {
  return t($key);
};
$rollbackData = is_array($result['rollback'] ?? null) ? $result['rollback'] : [];
$rollbackArtifacts = is_array($rollbackData['artifacts'] ?? null) ? $rollbackData['artifacts'] : [];
$rollbackSummary = is_array($rollbackData['summary'] ?? null) ? $rollbackData['summary'] : [];
if ($rollbackData === []) {
    $rollbackSummary = ['total' => 0, 'reversible' => 0, 'non_reversible' => 0];
}
?>

<?php if ($result['mode'] === 'rollback_preview' || $rollbackData !== []): ?>
<section class="card">
  <h2><?= e($gsBatch1('rollback_preview')) ?></h2>

  <?php if ($rollbackData === []): ?>
    <div class="note warning"><?= e($gsBatch1('rollback_unavailable')) ?></div>
  <?php else: ?>
    <div class="table-summary-badges">
      <span class="status-chip"><?= e($gsBatch1('rollback_summary')) ?>: <?= (int)($rollbackSummary['total'] ?? 0) ?> total</span>
      <span class="status-chip success"><?= e($gsBatch1('reversible')) ?>: <?= (int)($rollbackSummary['reversible'] ?? 0) ?></span>
      <?php if ((int)($rollbackSummary['non_reversible'] ?? 0) > 0): ?>
        <span class="status-chip danger"><?= e($gsBatch1('non_reversible_label') ?: 'Non-Reversible') ?>: <?= (int)$rollbackSummary['non_reversible'] ?></span>
      <?php endif; ?>
    </div>

    <?php if ($rollbackArtifacts !== []): ?>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th><?= e($gs('artifact_id')) ?></th>
              <th><?= e($gsBatch1('planned_action')) ?></th>
              <th><?= e($gsBatch1('rollback_action')) ?></th>
              <th><?= e($gsBatch1('reversible')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rollbackArtifacts as $rbArtifact): ?>
              <?php if (!is_array($rbArtifact)) { continue; } ?>
              <?php $rbReversible = !empty($rbArtifact['reversible']); ?>
              <tr>
                <td><code><?= e((string)($rbArtifact['artifact'] ?? '')) ?></code></td>
                <td><?= e((string)($rbArtifact['planned_action'] ?? '')) ?></td>
                <td><?= e((string)($rbArtifact['rollback_action'] ?? '')) ?></td>
                <td><span class="status-chip <?= $rbReversible ? 'success' : 'danger' ?>"><?= $rbReversible ? e($gs('bool.true')) : e($gs('bool.false')) ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php
$rollbackExecData = is_array($result['rollback_execute'] ?? null) ? $result['rollback_execute'] : [];
$rollbackBlocked = !empty($result['rollback_blocked']);
$rollbackBlockReason = (string)($result['rollback_block_reason'] ?? '');
$rollbackNonRevWarning = !empty($result['rollback_non_reversible_warning']);
?>

<?php if (($result['mode'] ?? '') === 'rollback_execute'): ?>
<section class="card" id="gs-rollback-execution">
  <h2><?= e($gsBatch1('rollback_execute_title')) ?></h2>

  <?php if ($rollbackBlocked): ?>
    <div class="note warning">
      <?php if ($rollbackBlockReason === 'all_steps_non_reversible'): ?>
        <?= e($gsBatch1('rollback_execute_all_non_reversible')) ?>
      <?php elseif ($rollbackBlockReason === 'no_rollback_binding' || $rollbackBlockReason === 'apply_record_not_found'): ?>
        <?= e($gsBatch1('rollback_execute_no_binding')) ?>
      <?php else: ?>
        <?php $rbBlockKey = 'precond.' . $rollbackBlockReason; $rbBlockText = $gs($rbBlockKey); ?>
        <?= e($rbBlockText !== $rbBlockKey ? $rbBlockText : $rollbackBlockReason) ?>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <?php if ($rollbackNonRevWarning): ?>
      <div class="note warning"><?= e($gsBatch1('rollback_execute_non_reversible_warning')) ?></div>
    <?php endif; ?>

    <?php if ($rollbackExecData !== []): ?>
      <?php
        $rbExecStatus = strtoupper((string)($rollbackExecData['status'] ?? 'READY'));
        $rbExecStatusClass = match ($rbExecStatus) {
            'COMPLETED' => 'success',
            'FAILED' => 'danger',
            default => 'warning',
        };
        $rbExecSteps = is_array($rollbackExecData['steps'] ?? null) ? $rollbackExecData['steps'] : [];
        $rbExecSummary = is_array($rollbackExecData['summary'] ?? null) ? $rollbackExecData['summary'] : [];
      ?>
      <div class="rollback-status table-summary-badges">
        <span class="status-chip <?= e($rbExecStatusClass) ?>"><?= e($rbExecStatus) ?></span>
        <?php if ((string)($rollbackExecData['rollback_id'] ?? '') !== ''): ?>
          <span class="status-chip">ID: <code><?= e((string)$rollbackExecData['rollback_id']) ?></code></span>
        <?php endif; ?>
        <?php if (!empty($rbExecSummary)): ?>
          <span class="status-chip">Total: <?= (int)($rbExecSummary['total'] ?? 0) ?></span>
          <span class="status-chip success">Done: <?= (int)($rbExecSummary['done'] ?? 0) ?></span>
          <?php if ((int)($rbExecSummary['failed'] ?? 0) > 0): ?>
            <span class="status-chip danger"> <?= e($tt('studio.failed_label')) ?> <?= (int)$rbExecSummary['failed'] ?></span>
          <?php endif; ?>
          <?php if ((int)($rbExecSummary['skipped'] ?? 0) > 0): ?>
            <span class="status-chip">Skipped: <?= (int)$rbExecSummary['skipped'] ?></span>
          <?php endif; ?>
        <?php endif; ?>
      </div>

      <?php if ($rbExecSteps !== []): ?>
        <h3><?= e($gsBatch1('rollback_execute_steps')) ?></h3>
        <div class="rollback-results table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th><?= e($gs('artifact_id')) ?></th>
                <th><?= e($gsBatch1('rollback_action')) ?></th>
                <th><?= e($gsBatch1('reversible')) ?></th>
                <th><?= e($gsBatch1('step_result')) ?></th>
                <th><?= e($gsBatch1('rollback_execute_message')) ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rbExecSteps as $rbExecStep): ?>
                <?php if (!is_array($rbExecStep)) { continue; } ?>
                <?php
                  $rbStepResult = strtolower((string)($rbExecStep['result'] ?? 'done'));
                  $rbStepClass = match ($rbStepResult) {
                      'done' => 'success',
                      'failed' => 'danger',
                      default => '',
                  };
                  $rbStepReversible = !empty($rbExecStep['reversible']);
                ?>
                <tr>
                  <td><code><?= e((string)($rbExecStep['artifact'] ?? '')) ?></code></td>
                  <td><?= e((string)($rbExecStep['rollback_action'] ?? '')) ?></td>
                  <td><span class="status-chip <?= $rbStepReversible ? 'success' : 'danger' ?>"><?= $rbStepReversible ? e($gs('bool.true')) : e($gs('bool.false')) ?></span></td>
                  <td><span class="status-chip <?= e($rbStepClass) ?>"><?= e(strtoupper($rbStepResult)) ?></span></td>
                  <td><?= e((string)($rbExecStep['message'] ?? '')) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <div class="note warning"><?= e($gsBatch1('rollback_execute_no_binding')) ?></div>
    <?php endif; ?>
  <?php endif; ?>
</section>
<?php endif; ?>

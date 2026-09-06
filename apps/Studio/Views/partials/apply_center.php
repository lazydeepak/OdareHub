<?php if ($result !== null && ($result['mode'] ?? '') === 'apply'): ?>
<?php
  $apply = isset($result['apply']) && is_array($result['apply']) ? $result['apply'] : [];
  $applyStatus = strtoupper((string)($apply['status'] ?? 'READY'));
  $applyId = (string)($apply['apply_id'] ?? '');
  $applyCompileId = (string)($apply['compile_id'] ?? '');
  $applyStatusClass = match ($applyStatus) {
      'APPLIED' => 'success',
      'FAILED' => 'danger',
      default => 'warning',
  };
  $applyResults = is_array($apply['results'] ?? null) ? $apply['results'] : [];
  $applyPreconditions = is_array($apply['precondition_failures'] ?? null) ? $apply['precondition_failures'] : [];
  $applyIntegrity = is_array($apply['integrity'] ?? null) ? $apply['integrity'] : [];
  $applyRollbackBinding = is_array($apply['rollback_binding'] ?? null) ? $apply['rollback_binding'] : [];
  $postPublishVerification = is_array($apply['post_publish_verification'] ?? null) ? $apply['post_publish_verification'] : [];
  $postPublishVerificationStatus = strtoupper((string)($postPublishVerification['status'] ?? ''));
  $postPublishVerificationChecks = is_array($postPublishVerification['checks'] ?? null) ? $postPublishVerification['checks'] : [];
  $applyCurrentMode = (string)($result['apply_mode'] ?? \Apps\Studio\Services\GuiStudioService::APPLY_MODE);
  $applyModeLabelKey = match ($applyCurrentMode) {
      'simulation' => 'apply_mode_simulation',
      'real' => 'apply_mode_real',
      'first_apply' => 'apply_mode_first_apply',
      default => 'apply_mode_disabled',
  };
  $applyModeChipClass = match ($applyCurrentMode) {
      'real' => 'danger',
      'first_apply' => 'success',
      'simulation' => 'warning',
      default => '',
  };
?>
<section class="card" id="gs-apply-snapshot">
  <h2><?= e($gs('apply_snapshot_title')) ?></h2>

  <div class="apply-status table-summary-badges">
    <span class="status-chip <?= e($applyStatusClass) ?>"><?= e(strtoupper($applyStatus)) ?></span>
    <span class="status-chip <?= e($applyModeChipClass) ?>"><?= e($gsBatch1('apply_mode_label')) ?>: <?= e($gsBatch1($applyModeLabelKey)) ?></span>
    <?php if ($applyId !== ''): ?>
      <span class="status-chip"><?= e($gs('apply_id')) ?>: <code><?= e($applyId) ?></code></span>
    <?php endif; ?>
    <?php if ($applyCompileId !== ''): ?>
      <span class="status-chip"><?= e($gs('compile_id')) ?>: <code><?= e($applyCompileId) ?></code></span>
    <?php endif; ?>
    <?php if (!empty($applyIntegrity['snapshot_hash'])): ?>
      <span class="status-chip"><?= e($gs('hash_verified')) ?>: <?= !empty($applyIntegrity['verified']) ? e($gs('bool.true')) : e($gs('bool.false')) ?></span>
    <?php endif; ?>
    <?php if (array_key_exists('snapshot_persisted', $apply)): ?>
      <span class="status-chip <?= !empty($apply['snapshot_persisted']) ? 'success' : 'danger' ?>"><?= e($gsBatch1('snapshot_persisted')) ?>: <?= !empty($apply['snapshot_persisted']) ? e($gs('bool.true')) : e($gs('bool.false')) ?></span>
    <?php endif; ?>
    <?php if ($postPublishVerificationStatus !== ''): ?>
      <span class="status-chip <?= $postPublishVerificationStatus === 'VERIFIED' ? 'success' : 'danger' ?>"><?= e($gsBatch1('verification_status')) ?>: <?= e($postPublishVerificationStatus) ?></span>
    <?php endif; ?>
  </div>

  <?php if ($apply === [] || $applyStatus === 'READY'): ?>
    <div class="note warning"><?= e($gs('apply_unavailable')) ?></div>
  <?php endif; ?>

  <?php if ($applyPreconditions !== []): ?>
    <div class="note warning">
      <strong><?= e($gs('precondition_failures')) ?>:</strong>
      <ul class="u-style-4a5ac83ab2">
        <?php foreach ($applyPreconditions as $pc): ?>
          <?php $pcKey = 'precond.' . (string)$pc; $pcMsg = $gs($pcKey); ?>
          <li><?= e($pcMsg !== $pcKey ? $pcMsg : (string)$pc) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if ($applyResults !== []): ?>
    <h3><?= e($gsBatch1('transaction_steps')) ?></h3>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th><?= e($gs('artifact_id')) ?></th>
            <th><?= e($gs('action')) ?></th>
            <th><?= e($gsBatch1('step_result')) ?></th>
            <th><?= e($gs('reason_label')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($applyResults as $applyResult): ?>
            <?php if (!is_array($applyResult)) { continue; } ?>
            <?php $applyResultStatus = strtolower((string)($applyResult['status'] ?? 'failed')); ?>
            <tr>
              <td><code><?= e((string)($applyResult['artifact'] ?? '')) ?></code></td>
              <td><?= e((string)($applyResult['action'] ?? '')) ?></td>
              <td><span class="status-chip <?= e($applyResultStatus) ?>"><?= e(strtoupper((string)($applyResult['status'] ?? ''))) ?></span></td>
              <td><?= e((string)($applyResult['message'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if ($postPublishVerification !== []): ?>
    <h3><?= e($gsBatch1('post_publish_verification')) ?></h3>
    <div class="table-summary-badges">
      <span class="status-chip <?= $postPublishVerificationStatus === 'VERIFIED' ? 'success' : 'danger' ?>"><?= e($gsBatch1('verification_status')) ?>: <?= e($postPublishVerificationStatus) ?></span>
      <?php $verificationSummary = is_array($postPublishVerification['summary'] ?? null) ? $postPublishVerification['summary'] : []; ?>
      <?php if ($verificationSummary !== []): ?>
        <span class="status-chip"><?= e($gs('summary')) ?>: <?= (int)($verificationSummary['passed'] ?? 0) ?> / <?= (int)($verificationSummary['total'] ?? 0) ?></span>
      <?php endif; ?>
    </div>
    <?php if ($postPublishVerificationChecks !== []): ?>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th><?= e($gs('check')) ?></th>
              <th><?= e($gs('status')) ?></th>
              <th><?= e($gs('detail')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($postPublishVerificationChecks as $verificationCheck): ?>
              <?php if (!is_array($verificationCheck)) { continue; } ?>
              <tr>
                <td><?= e((string)($verificationCheck['name'] ?? '')) ?></td>
                <td><?= !empty($verificationCheck['pass']) ? e($gs('pass')) : e($gs('fail')) ?></td>
                <td><?= e((string)($verificationCheck['detail'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($applyRollbackBinding !== []): ?>
    <h3><?= e($gsBatch1('rollback_binding')) ?></h3>
    <div class="table-summary-badges">
      <span class="status-chip success"><?= e($gsBatch1('rollback_binding_status')) ?>: <?= e($gsBatch1('rollback_bound')) ?></span>
      <?php if ((string)($applyRollbackBinding['rollback_id'] ?? '') !== ''): ?>
        <span class="status-chip"><?= e($gsBatch1('rollback_action')) ?> ID: <code><?= e((string)$applyRollbackBinding['rollback_id']) ?></code></span>
      <?php endif; ?>
      <?php $rbBindSummary = is_array($applyRollbackBinding['summary'] ?? null) ? $applyRollbackBinding['summary'] : []; ?>
      <?php if (!empty($rbBindSummary)): ?>
        <span class="status-chip"><?= e($gsBatch1('reversible')) ?>: <?= (int)($rbBindSummary['reversible'] ?? 0) ?> / <?= (int)($rbBindSummary['total'] ?? 0) ?></span>
      <?php endif; ?>
    </div>
    <?php if ($applyId !== ''): ?>
    <form class="u-style-0cf8f93b0e" method="POST" action="/apps/studio/rollback-execute">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="apply_id" value="<?= e($applyId) ?>">
      <button class="btn danger" type="submit"><?= e($gsBatch1('rollback_execute_btn')) ?></button>
    </form>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($applyStatus === 'APPLIED' && $applyCompileId !== ''): ?>
    <h3><?= e($gs('rollback')) ?></h3>
    <form class="u-style-0cf8f93b0e" method="POST" action="/apps/studio/rollback">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="compile_id" value="<?= e($applyCompileId) ?>">
      <button class="btn danger" type="submit"><?= e($gsBatch1('rollback_execute_btn')) ?></button>
    </form>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php

$vcModel = isset($vcModel) && is_array($vcModel) ? $vcModel : [];

$found = !empty($vcModel['found']);
$request = isset($vcModel['request']) && is_array($vcModel['request']) ? $vcModel['request'] : [];
$requestId = (string)($vcModel['request_id'] ?? '');

$lang = function_exists('current_lang') ? current_lang() : 'en';
$dict = [
    'en' => [
        'title' => 'Approval Request Detail',
        'not_found' => 'Request not found',
        'back_link' => 'Back to Visual Customizer',
        'request_id' => 'Request ID',
        'requested_by' => 'Requested by',
        'created_at' => 'Created',
        'status' => 'Status',
        'socket' => 'Socket',
        'default_value' => 'Default',
        'current_value' => 'Current',
        'not_connected' => 'Not connected yet',
        'proposed_value' => 'Proposed',
        'diff' => 'Diff',
        'validation_title' => 'Validation Status',
        'valid' => 'Valid',
        'not_valid' => 'Not valid',
        'checks' => 'Checks',
        'errors' => 'Errors',
        'warnings' => 'Warnings',
        'non_runtime_flags' => 'Non-Runtime Flags',
        'flag_apply_enabled' => 'Apply enabled',
        'flag_registry_write' => 'Registry write',
        'flag_shell_consumption' => 'Shell consumption',
        'flag_public_assets' => 'Public assets output',
        'flag_runtime_activation' => 'Runtime activation',
        'all_disabled' => 'All disabled',
        'runtime' => 'Runtime',
        'not_applied' => 'Not applied',
        'apply' => 'Apply',
        'disabled' => 'Disabled',
        'none' => 'None',
        'decision_title' => 'Approval Decision Readiness',
        'decision_hint' => 'Review, approve, or reject the proposed change. Decisions are still separate from Apply.',
        'reviewer_eligibility' => 'Reviewer eligibility',
        'reviewer_eligibility_value' => 'Platform admin required',
        'current_status' => 'Current status',
        'approve' => 'Approve',
        'reject' => 'Reject',
        'cancel' => 'Cancel',
        'requester_only_later' => 'Requester only later',
        'registry' => 'Registry',
        'untouched' => 'Untouched',
        'decision_approve_action' => 'Approve this request',
        'decision_reject_action' => 'Reject this request',
        'decision_cancel_action' => 'Cancel this request',
        'decision_approve_confirm' => 'Approve proposed change?',
        'decision_reject_reason_prompt' => 'Reason for rejection (optional):',
        'decision_cancel_reason_prompt' => 'Reason for cancellation (optional):',
        'decision_error_prefix' => 'Decision failed:',
        'decision_processing' => 'Processing...',
        'approved_status_label' => 'Approved for future apply',
        'rejected_status_label' => 'Review rejected',
        'cancelled_status_label' => 'Review cancelled',
        'not_applied_to_runtime' => 'Not applied to runtime',
        'decision_result_title' => 'Decision Result',
        'decided_at' => 'Decided at',
        'reviewed_by' => 'Reviewed by',
        'cancelled_by' => 'Cancelled by',
        'reason' => 'Reason',
        'apply_readiness_title' => 'Apply Readiness',
        'apply_readiness_hint' => 'The request is approved but not yet applied. Apply is a separate future phase.',
        'platform_registry' => 'Platform registry',
        'shell' => 'Shell',
        'not_consuming' => 'Not consuming',
        'snapshot' => 'Snapshot',
        'not_created' => 'Not created',
        'rollback' => 'Rollback',
        'not_available_yet' => 'Not available yet',
        'snapshot_title' => 'Snapshot',
        'snapshot_hint' => 'Capture the current style state before it is changed. The snapshot stores the current value as a read-only record for future rollback.',
        'take_snapshot_btn' => 'Take Snapshot',
        'snapshot_taken' => 'Snapshot taken',
        'snapshot_id_label' => 'Snapshot ID',
        'snapshot_created_at' => 'Snapshot created',
        'snapshot_previous_value' => 'Captured current value',
        'snapshot_created_by' => 'Snapshot by',
        'snapshot_ready' => 'Ready',
        'snapshot_taken_a11y' => 'Snapshot has been taken for this request',
        'snapshot_error_prefix' => 'Snapshot failed:',
        'snapshot_processing' => 'Taking snapshot...',
        'snapshot_confirm' => 'Capture a snapshot of the current style state before Apply? This is a read-only record.',
        'apply_btn' => 'Apply to Platform Registry',
        'apply_confirm' => 'Write the approved value to the Platform StyleRegistry? This is irreversible without rollback.',
        'apply_processing' => 'Applying...',
        'apply_success_title' => 'Applied to Platform Registry',
        'apply_success_hint' => 'The approved value has been written to the Platform StyleRegistry. It is not yet consumed by Shell or runtime.',
        'registry_updated' => 'Updated',
        'applied_by_label' => 'Applied by',
        'applied_at_label' => 'Applied at',
        'apply_socket_value' => 'Registry value',
        'runtime' => 'Runtime',
        'not_consuming' => 'Not consuming',
        'apply_no_shell' => 'Shell not yet consuming',
        'apply_no_assets' => 'public/assets untouched',
        'apply_error_prefix' => 'Apply failed:',
        'post_apply_registry' => 'Platform registry',
        'post_apply_shell' => 'Shell',
        'post_apply_assets' => 'public/assets',
        'post_apply_runtime' => 'Runtime',
        'post_apply_rollback' => 'Rollback',
        'post_apply_rollback_value' => 'Not available (future)',

        'platform_registry_status_title' => 'Platform Registry Status',
        'applied_yes' => 'Yes',
        'applied_no' => 'No',
        'applied_value_label' => 'Applied value',
        'registry_path_label' => 'Registry path',
        'downstream_consumption' => 'Downstream consumption',
        'apply_status_label' => 'Apply status',
        'no_value_yet' => 'Apply first to see registry details',
    ],
];
$t = static function (string $key) use ($lang, $dict): string {
    $set = $dict['en'];
    return (string)($set[$key] ?? $key);
};
?>
<section class="cs-vc-detail">
  <header class="cs-vc-detail__header">
    <a href="/apps/studio/tools/customization-studio/visual-customizer" class="cs-vc-detail__back">&larr; <?= e($t('back_link')) ?></a>
    <h2><?= e($t('title')) ?></h2>
  </header>

<?php if (!$found || $request === []): ?>
  <div class="cs-vc-detail__not-found">
    <p><?= e($t('not_found')) ?>: <?= e($requestId) ?></p>
  </div>
<?php else: ?>
  <dl class="cs-vc-detail__summary">
    <dt><?= e($t('request_id')) ?></dt>
    <dd><?= e((string)($request['request_id'] ?? '')) ?></dd>

    <dt><?= e($t('requested_by')) ?></dt>
    <dd><?= e((string)($request['requested_by'] ?? '')) ?></dd>

    <dt><?= e($t('created_at')) ?></dt>
    <dd><?= e((string)($request['created_at'] ?? '')) ?></dd>

    <dt><?= e($t('status')) ?></dt>
    <dd><?= e((string)($request['status'] ?? '')) ?></dd>

    <dt><?= e($t('socket')) ?></dt>
    <dd><?= e((string)($request['selected_socket_id'] ?? '')) ?></dd>

    <dt><?= e($t('default_value')) ?></dt>
    <dd><?= e((string)($request['default_value'] ?? '')) ?></dd>

    <dt><?= e($t('current_value')) ?></dt>
    <dd><?= e($t('not_connected')) ?></dd>

    <dt><?= e($t('proposed_value')) ?></dt>
    <dd><?= e((string)($request['proposed_value'] ?? '')) ?></dd>

    <dt><?= e($t('diff')) ?></dt>
    <dd><?= e((string)($request['diff_summary'] ?? '')) ?></dd>
  </dl>

  <section class="cs-vc-detail__flags">
    <h3><?= e($t('non_runtime_flags')) ?></h3>
    <dl>
      <dt><?= e($t('flag_apply_enabled')) ?></dt>
      <dd><?= e($t('disabled')) ?></dd>
      <dt><?= e($t('flag_registry_write')) ?></dt>
      <dd><?= e($t('disabled')) ?></dd>
      <dt><?= e($t('flag_shell_consumption')) ?></dt>
      <dd><?= e($t('disabled')) ?></dd>
      <dt><?= e($t('flag_public_assets')) ?></dt>
      <dd><?= e($t('disabled')) ?></dd>
      <dt><?= e($t('flag_runtime_activation')) ?></dt>
      <dd><?= e($t('disabled')) ?></dd>
    </dl>
  </section>

  <section class="cs-vc-detail__validation">
    <h3><?= e($t('validation_title')) ?></h3>
<?php
$validationStatus = isset($request['validation_status']) && is_array($request['validation_status'])
    ? $request['validation_status']
    : [];
$isValid = !empty($validationStatus['valid']);
?>
    <p class="cs-vc-detail__validity <?= $isValid ? 'is-valid' : 'is-not-valid' ?>">
      <?= e($t('valid')) ?>: <?= $isValid ? 'true' : 'false' ?>
    </p>

<?php
$checks = isset($validationStatus['checks']) && is_array($validationStatus['checks'])
    ? $validationStatus['checks']
    : [];
?>
    <h4><?= e($t('checks')) ?></h4>
    <ul class="cs-vc-detail__check-list">
<?php foreach ($checks as $checkKey => $checkPassed): ?>
      <li class="<?= $checkPassed === true ? 'cs-vc-detail__check--pass' : 'cs-vc-detail__check--fail' ?>">
        <?= e((string)$checkKey) ?>: <?= $checkPassed === true ? 'true' : 'false' ?>
      </li>
<?php endforeach; ?>
    </ul>

<?php
$errors = isset($validationStatus['errors']) && is_array($validationStatus['errors'])
    ? $validationStatus['errors']
    : [];
$warnings = isset($validationStatus['warnings']) && is_array($validationStatus['warnings'])
    ? $validationStatus['warnings']
    : [];
?>
    <h4><?= e($t('errors')) ?></h4>
    <?php if ($errors === []): ?>
      <p><?= e($t('none')) ?></p>
    <?php else: ?>
      <ul class="cs-vc-detail__error-list">
        <?php foreach ($errors as $err): ?>
          <li><?= e((string)$err) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <h4><?= e($t('warnings')) ?></h4>
    <?php if ($warnings === []): ?>
      <p><?= e($t('none')) ?></p>
    <?php else: ?>
      <ul class="cs-vc-detail__warning-list">
        <?php foreach ($warnings as $warn): ?>
          <li><?= e((string)$warn) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

<?php
$isPending = $found && ((string)($request['status'] ?? '')) === 'pending_review';
$currentUserCanReview = !empty($vcModel['current_user_can_review']);
$currentUserIsRequester = !empty($vcModel['current_user_is_requester']);
$approveEndpoint = (string)($vcModel['approve_endpoint'] ?? '');
$rejectEndpoint = (string)($vcModel['reject_endpoint'] ?? '');
$cancelEndpoint = (string)($vcModel['cancel_endpoint'] ?? '');
$applyEndpoint = (string)($vcModel['apply_endpoint'] ?? '');
$canApply = !empty($vcModel['can_apply']);
$isApplied = !empty($vcModel['is_applied']);
$appliedAtValue = (string)($vcModel['applied_at'] ?? '');
$appliedByValue = (string)($vcModel['applied_by'] ?? '');
$registryTarget = isset($vcModel['registry_target']) && is_array($vcModel['registry_target'])
    ? $vcModel['registry_target'] : null;
$csrfToken = (string)($vcModel['csrf'] ?? '');
?>
<?php if ($isPending): ?>
  <section class="cs-vc-detail__decisions">
    <h3><?= e($t('decision_title')) ?></h3>
    <p class="cs-vc-detail__decisions-hint"><?= e($t('decision_hint')) ?></p>
    <dl class="cs-vc-detail__decisions-grid">
      <dt><?= e($t('reviewer_eligibility')) ?></dt>
      <dd><?= e($t('reviewer_eligibility_value')) ?></dd>

      <dt><?= e($t('current_status')) ?></dt>
      <dd><?= e((string)($request['status'] ?? '')) ?></dd>

      <dt><?= e($t('apply')) ?></dt>
      <dd class="cs-vc-detail__decision-disabled"><?= e($t('disabled')) ?></dd>

      <dt><?= e($t('runtime')) ?></dt>
      <dd><?= e($t('not_applied')) ?></dd>

      <dt><?= e($t('registry')) ?></dt>
      <dd><?= e($t('untouched')) ?></dd>
    </dl>

    <div class="cs-vc-detail__decisions-actions">
      <?php if ($currentUserCanReview): ?>
        <button type="button" class="cs-vc-detail__decision-btn cs-vc-detail__decision-btn--approve" data-action="approve"><?= e($t('approve')) ?></button>
        <button type="button" class="cs-vc-detail__decision-btn cs-vc-detail__decision-btn--reject" data-action="reject"><?= e($t('reject')) ?></button>
      <?php endif; ?>
      <?php if ($currentUserIsRequester): ?>
        <button type="button" class="cs-vc-detail__decision-btn cs-vc-detail__decision-btn--cancel" data-action="cancel"><?= e($t('cancel')) ?></button>
      <?php endif; ?>
    </div>
    <p id="cs-vc-detail-decision-status" class="cs-vc-detail__decision-status" aria-live="polite"></p>
  </section>
<?php endif; ?>

<?php
$hasDecision = $found && $request !== [] && isset($request['decision']) && is_array($request['decision']);
$decisionInfo = $hasDecision ? $request['decision'] : [];
$finalStatus = (string)($request['status'] ?? '');
$snapshot = isset($vcModel['snapshot']) && is_array($vcModel['snapshot']) ? $vcModel['snapshot'] : null;
$statusLabel = $finalStatus === 'approved_for_future_apply' ? $t('approved_status_label')
    : ($finalStatus === 'review_rejected' ? $t('rejected_status_label')
    : ($finalStatus === 'review_cancelled' ? $t('cancelled_status_label')
    : $finalStatus));
?>
<?php if ($hasDecision): ?>
  <section class="cs-vc-detail__decision-result">
    <h3><?= e($t('decision_result_title')) ?></h3>
    <dl class="cs-vc-detail__decisions-grid">
      <dt><?= e($t('status')) ?></dt>
      <dd><?= e($statusLabel) ?></dd>

      <dt><?= e($t('runtime')) ?></dt>
      <dd><?= e($t('not_applied_to_runtime')) ?></dd>

<?php if (!empty($decisionInfo['reviewed_by'])): ?>
      <dt><?= e($t('reviewed_by')) ?></dt>
      <dd><?= e((string)$decisionInfo['reviewed_by']) ?></dd>
<?php endif; ?>
<?php if (!empty($decisionInfo['cancelled_by'])): ?>
      <dt><?= e($t('cancelled_by')) ?></dt>
      <dd><?= e((string)$decisionInfo['cancelled_by']) ?></dd>
<?php endif; ?>

      <dt><?= e($t('decided_at')) ?></dt>
      <dd><?= e((string)($decisionInfo['decided_at'] ?? '')) ?></dd>

<?php if (!empty($decisionInfo['reason'])): ?>
      <dt><?= e($t('reason')) ?></dt>
      <dd><?= e((string)$decisionInfo['reason']) ?></dd>
<?php endif; ?>
    </dl>
  </section>
<?php endif; ?>

<?php $isApproved = $found && $finalStatus === 'approved_for_future_apply'; ?>
<?php if ($isApproved): ?>
  <section class="cs-vc-detail__platform-registry-status">
    <h3><?= e($t('platform_registry_status_title')) ?></h3>
    <dl class="cs-vc-detail__platform-registry-grid">
      <dt><?= e($t('platform_registry')) ?></dt>
      <dd class="<?= $isApplied ? 'cs-vc-detail__registry--ok' : 'cs-vc-detail__registry--na' ?>">
        <?= $isApplied ? e($t('applied_yes')) : e($t('applied_no')) ?>
      </dd>

      <dt><?= e($t('socket')) ?></dt>
      <dd><?= e((string)($request['selected_socket_id'] ?? '')) ?></dd>

<?php if ($isApplied): ?>
      <dt><?= e($t('applied_value_label')) ?></dt>
      <dd><?= e((string)($registryTarget['value'] ?? '')) ?></dd>

<?php if ($appliedAtValue !== ''): ?>
      <dt><?= e($t('applied_at_label')) ?></dt>
      <dd><?= e($appliedAtValue) ?></dd>
<?php endif; ?>

<?php if ($appliedByValue !== ''): ?>
      <dt><?= e($t('applied_by_label')) ?></dt>
      <dd><?= e($appliedByValue) ?></dd>
<?php endif; ?>

      <dt><?= e($t('registry_path_label')) ?></dt>
      <dd><code>storage/platform/style-registry/approved-values/<?= e((string)($registryTarget['socket_id'] ?? '')) ?>.json</code></dd>

      <dt><?= e($t('apply_status_label')) ?></dt>
      <dd class="cs-vc-detail__registry--ok"><?= e($t('apply_success_title')) ?></dd>
<?php else: ?>
      <dt><?= e($t('proposed_value')) ?></dt>
      <dd><?= e((string)($request['proposed_value'] ?? '')) ?> (<?= e($t('no_value_yet')) ?>)</dd>

      <dt><?= e($t('apply_status_label')) ?></dt>
      <dd class="cs-vc-detail__registry--na"><?= e($t('not_applied')) ?></dd>
<?php endif; ?>

      <dt><?= e($t('downstream_consumption')) ?></dt>
      <dd class="cs-vc-detail__registry--warn">
        <?= e($t('runtime')) ?>: <?= e($t('not_consuming')) ?>,
        <?= e($t('shell')) ?>: <?= e($t('not_consuming')) ?>,
        public/assets: <?= e($t('none')) ?>
      </dd>
    </dl>
  </section>
<?php endif; ?>

<?php if ($isApproved && !$isApplied): ?>
  <section class="cs-vc-detail__apply-readiness">
    <h3><?= e($t('apply_readiness_title')) ?></h3>
    <p class="cs-vc-detail__apply-readiness-hint"><?= e($t('apply_readiness_hint')) ?></p>
    <dl class="cs-vc-detail__apply-readiness-grid">
      <dt><?= e($t('runtime')) ?></dt>
      <dd><?= e($t('not_applied')) ?></dd>

      <dt><?= e($t('platform_registry')) ?></dt>
      <dd><?= e($t('untouched')) ?></dd>

      <dt><?= e($t('shell')) ?></dt>
      <dd><?= e($t('not_consuming')) ?></dd>

      <dt><?= e($t('apply')) ?></dt>
      <dd class="cs-vc-detail__decision-disabled"><?= e($t('disabled')) ?></dd>

      <dt><?= e($t('snapshot')) ?></dt>
      <dd class="<?= $snapshot !== null ? '' : 'cs-vc-detail__decision-disabled' ?>"><?= $snapshot !== null ? e($t('snapshot_ready')) : e($t('not_created')) ?></dd>

      <dt><?= e($t('rollback')) ?></dt>
      <dd class="cs-vc-detail__decision-disabled"><?= e($t('not_available_yet')) ?></dd>
    </dl>
  </section>
<?php endif; ?>

<?php if ($snapshot !== null): ?>
  <section class="cs-vc-detail__snapshot">
    <h3><?= e($t('snapshot_title')) ?> <span class="cs-vc-detail__snapshot-badge"><?= e($t('snapshot_taken')) ?></span></h3>
    <p class="cs-vc-detail__snapshot-hint" aria-live="polite"><?= e($t('snapshot_taken_a11y')) ?></p>
    <dl class="cs-vc-detail__snapshot-grid">
      <dt><?= e($t('snapshot_id_label')) ?></dt>
      <dd><?= e((string)($snapshot['snapshot_id'] ?? '')) ?></dd>

      <dt><?= e($t('snapshot_created_at')) ?></dt>
      <dd><?= e((string)($snapshot['created_at'] ?? '')) ?></dd>

      <dt><?= e($t('snapshot_created_by')) ?></dt>
      <dd><?= e((string)($snapshot['created_by_handle'] ?? '')) ?></dd>

      <dt><?= e($t('snapshot_previous_value')) ?></dt>
      <dd><?= e((string)($snapshot['previous_value'] ?? '')) ?></dd>

      <dt><?= e($t('proposed_value')) ?></dt>
      <dd><?= e((string)($snapshot['proposed_value'] ?? '')) ?></dd>
    </dl>
  </section>
<?php elseif ($isApproved && $currentUserIsRequester): ?>
  <section class="cs-vc-detail__snapshot">
    <h3><?= e($t('snapshot_title')) ?></h3>
    <p class="cs-vc-detail__snapshot-hint"><?= e($t('snapshot_hint')) ?></p>
    <div class="cs-vc-detail__snapshot-actions">
      <button type="button" class="cs-vc-detail__snapshot-btn" id="cs-vc-snapshot-take-btn"><?= e($t('take_snapshot_btn')) ?></button>
    </div>
    <p id="cs-vc-snapshot-status" class="cs-vc-detail__snapshot-status" aria-live="polite"></p>
  </section>
<?php endif; ?>

<?php if ($canApply): ?>
  <section class="cs-vc-detail__apply-section">
    <h3><?= e($t('apply_btn')) ?></h3>
    <p class="cs-vc-detail__apply-hint"><?= e($t('apply_confirm')) ?></p>
    <div class="cs-vc-detail__apply-actions">
      <button type="button" class="cs-vc-detail__apply-btn" id="cs-vc-apply-btn"><?= e($t('apply_btn')) ?></button>
    </div>
    <p id="cs-vc-apply-status" class="cs-vc-detail__apply-status" aria-live="polite"></p>
  </section>
<?php endif; ?>

<?php if ($isApplied): ?>
  <section class="cs-vc-detail__post-apply">
    <h3><?= e($t('apply_success_title')) ?></h3>
    <p class="cs-vc-detail__post-apply-hint"><?= e($t('apply_success_hint')) ?></p>
    <dl class="cs-vc-detail__post-apply-grid">
      <dt><?= e($t('platform_registry')) ?></dt>
      <dd class="cs-vc-detail__post-apply--ok"><?= e($t('registry_updated')) ?></dd>

<?php if ($registryTarget !== null): ?>
      <dt><?= e($t('apply_socket_value')) ?></dt>
      <dd><?= e((string)($registryTarget['socket_id'] ?? '') . ' = ' . (string)($registryTarget['value'] ?? '')) ?></dd>
<?php endif; ?>

<?php if ($appliedByValue !== ''): ?>
      <dt><?= e($t('applied_by_label')) ?></dt>
      <dd><?= e($appliedByValue) ?></dd>
<?php endif; ?>

<?php if ($appliedAtValue !== ''): ?>
      <dt><?= e($t('applied_at_label')) ?></dt>
      <dd><?= e($appliedAtValue) ?></dd>
<?php endif; ?>

      <dt><?= e($t('post_apply_runtime')) ?></dt>
      <dd class="cs-vc-detail__post-apply--warn"><?= e($t('not_consuming')) ?></dd>

      <dt><?= e($t('post_apply_shell')) ?></dt>
      <dd class="cs-vc-detail__post-apply--warn"><?= e($t('not_consuming')) ?></dd>

      <dt><?= e($t('post_apply_assets')) ?></dt>
      <dd class="cs-vc-detail__post-apply--warn"><?= e($t('untouched')) ?></dd>

      <dt><?= e($t('post_apply_rollback')) ?></dt>
      <dd class="cs-vc-detail__post-apply--warn"><?= e($t('post_apply_rollback_value')) ?></dd>
    </dl>
  </section>
<?php endif; ?>

  <footer class="cs-vc-detail__footer">
    <p><strong><?= e($t('runtime')) ?>:</strong> <?= e($t('not_applied')) ?></p>
    <p><strong><?= e($t('apply')) ?>:</strong> <?= $isApplied ? e($t('registry_updated')) : e($t('disabled')) ?></p>
  </footer>
<?php endif; ?>
</section>
<style>
<?php require __DIR__ . '/../assets/visual-customizer.css'; ?>
</style>
<script>
(function () {
  var takeSnapshotEndpoint = <?= json_encode((string)($vcModel['take_snapshot_endpoint'] ?? '')) ?>;
  var applyEndpoint = <?= json_encode($applyEndpoint) ?>;
  var endpoint = {
    approve: <?= json_encode($approveEndpoint) ?>,
    reject: <?= json_encode($rejectEndpoint) ?>,
    cancel: <?= json_encode($cancelEndpoint) ?>
  };
  var csrf = <?= json_encode($csrfToken) ?>;
  var requestId = <?= json_encode($requestId) ?>;
  var statusEl = document.getElementById('cs-vc-detail-decision-status');
  var t = {
    processing: <?= json_encode($t('decision_processing')) ?>,
    errorPrefix: <?= json_encode($t('decision_error_prefix')) ?>,
    approveConfirm: <?= json_encode($t('decision_approve_confirm')) ?>,
    rejectReason: <?= json_encode($t('decision_reject_reason_prompt')) ?>,
    cancelReason: <?= json_encode($t('decision_cancel_reason_prompt')) ?>
  };

  function doDecision(action, reason) {
    if (!endpoint[action]) return;
    statusEl.textContent = t.processing;
    statusEl.className = 'cs-vc-detail__decision-status';

    var payload = { csrf: csrf, request_id: requestId };
    if (reason) payload.reason = reason;

    fetch(endpoint[action], {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
    .then(function (r) { return r.json().then(function (d) { return { status: r.status, data: d }; }); })
    .then(function (result) {
      if (result.data.ok) {
        window.location.reload();
      } else {
        statusEl.textContent = t.errorPrefix + ' ' + (result.data.error || 'unknown');
        statusEl.className = 'cs-vc-detail__decision-status cs-vc-detail__decision-status--error';
      }
    })
    .catch(function () {
      statusEl.textContent = t.errorPrefix + ' network error';
      statusEl.className = 'cs-vc-detail__decision-status cs-vc-detail__decision-status--error';
    });
  }

  document.querySelector('.cs-vc-detail__decisions-actions') &&
  document.querySelector('.cs-vc-detail__decisions-actions').addEventListener('click', function (e) {
    var btn = e.target.closest('[data-action]');
    if (!btn) return;
    var action = btn.getAttribute('data-action');
    var reason = '';
    if (action === 'approve') {
      if (!confirm(t.approveConfirm)) return;
    } else if (action === 'reject') {
      reason = prompt(t.rejectReason) || '';
    } else if (action === 'cancel') {
      reason = prompt(t.cancelReason) || '';
    }
    doDecision(action, reason);
  });

  var snapBtn = document.getElementById('cs-vc-snapshot-take-btn');
  var snapStatus = document.getElementById('cs-vc-snapshot-status');
  if (snapBtn && snapStatus) {
    var st = {
      processing: <?= json_encode($t('snapshot_processing')) ?>,
      errorPrefix: <?= json_encode($t('snapshot_error_prefix')) ?>,
      confirm: <?= json_encode($t('snapshot_confirm')) ?>
    };
    snapBtn.addEventListener('click', function () {
      if (!confirm(st.confirm)) return;
      snapBtn.disabled = true;
      snapStatus.textContent = st.processing;
      snapStatus.className = 'cs-vc-detail__snapshot-status';
      fetch(takeSnapshotEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf: csrf, request_id: requestId })
      })
      .then(function (r) { return r.json().then(function (d) { return { status: r.status, data: d }; }); })
      .then(function (result) {
        if (result.data.ok) {
          window.location.reload();
        } else {
          snapBtn.disabled = false;
          snapStatus.textContent = st.errorPrefix + ' ' + (result.data.error || 'unknown');
          snapStatus.className = 'cs-vc-detail__snapshot-status cs-vc-detail__snapshot-status--error';
        }
      })
      .catch(function () {
        snapBtn.disabled = false;
        snapStatus.textContent = st.errorPrefix + ' network error';
        snapStatus.className = 'cs-vc-detail__snapshot-status cs-vc-detail__snapshot-status--error';
      });
    });
  }

  var applyBtn = document.getElementById('cs-vc-apply-btn');
  var applyStatus = document.getElementById('cs-vc-apply-status');
  if (applyBtn && applyStatus) {
    var at = {
      processing: <?= json_encode($t('apply_processing')) ?>,
      errorPrefix: <?= json_encode($t('apply_error_prefix')) ?>,
      confirm: <?= json_encode($t('apply_confirm')) ?>
    };
    applyBtn.addEventListener('click', function () {
      if (!confirm(at.confirm)) return;
      applyBtn.disabled = true;
      applyStatus.textContent = at.processing;
      applyStatus.className = 'cs-vc-detail__apply-status';
      fetch(applyEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf: csrf, request_id: requestId })
      })
      .then(function (r) { return r.json().then(function (d) { return { status: r.status, data: d }; }); })
      .then(function (result) {
        if (result.data.ok) {
          window.location.reload();
        } else {
          applyBtn.disabled = false;
          applyStatus.textContent = at.errorPrefix + ' ' + (result.data.error || 'unknown');
          applyStatus.className = 'cs-vc-detail__apply-status cs-vc-detail__apply-status--error';
        }
      })
      .catch(function () {
        applyBtn.disabled = false;
        applyStatus.textContent = at.errorPrefix + ' network error';
        applyStatus.className = 'cs-vc-detail__apply-status cs-vc-detail__apply-status--error';
      });
    });
  }
})();
</script>

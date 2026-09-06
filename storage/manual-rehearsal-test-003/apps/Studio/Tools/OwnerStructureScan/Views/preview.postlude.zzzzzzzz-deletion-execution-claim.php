<?php
declare(strict_types=1);

use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureDeletionExecutionClaimWorkspaceService;

require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionExecutionClaimWorkspaceService.php';

$executionClaimRequested = (string)($_GET['deletion_execution_claim'] ?? '') === '1';
$executionClaimReadiness = isset($executorReadinessWorkspace['assessment']) && is_array($executorReadinessWorkspace['assessment'])
    ? $executorReadinessWorkspace['assessment']
    : (isset($executorReadinessWorkspace['executor_readiness_result']) && is_array($executorReadinessWorkspace['executor_readiness_result'])
        ? $executorReadinessWorkspace['executor_readiness_result']
        : (isset($executorReadinessWorkspace) && is_array($executorReadinessWorkspace) ? $executorReadinessWorkspace : null));
$executionClaimFlash = isset($_SESSION['studio_owner_structure_deletion_execution_claim_result'])
    && is_array($_SESSION['studio_owner_structure_deletion_execution_claim_result'])
        ? $_SESSION['studio_owner_structure_deletion_execution_claim_result']
        : null;
unset($_SESSION['studio_owner_structure_deletion_execution_claim_result']);

$executionClaimWorkspace = OwnerStructureDeletionExecutionClaimWorkspaceService::build(
    $impactOwners,
    $impactSelectedOwnerKey,
    $executionClaimRequested,
    $executionClaimReadiness,
    $executionClaimFlash,
    APP_ROOT
);
$executionClaimCsrf = isset($ownerStructureScanModel['csrf']) ? (string)$ownerStructureScanModel['csrf'] : '';

$executionClaimText = static function (string $key): string {
    $lang = function_exists('current_lang') ? (string)current_lang() : 'en';
    $dictionary = [
        'en' => [
            'title' => 'Execution request claim',
            'description' => 'Atomically consume the single-use request as its named ready executor.',
            'open' => 'Prepare request claim',
            'refresh' => 'Refresh claim workspace',
            'hide' => 'Hide claim workspace',
            'idle' => 'Verify executor readiness before claiming the request.',
            'boundary' => 'Claim provenance only. This does not create a snapshot, edit references, archive files, delete the target, or execute anything.',
            'state' => 'Executor readiness',
            'eligible' => 'Executor eligible',
            'request_id' => 'Request ID',
            'request_fp' => 'Request fingerprint',
            'readiness_fp' => 'Executor-readiness fingerprint',
            'current_actor' => 'Current executor',
            'reason' => 'Claim reason',
            'confirm' => 'I confirm that I am consuming this single-use request as the named executor.',
            'submit' => 'Claim execution request',
            'blocked' => 'The request cannot be claimed by the current actor or has already been consumed.',
            'latest' => 'Claim evidence',
            'none' => 'No claim exists for this request.',
            'claim_id' => 'Claim ID',
            'claimed_at' => 'Claimed at',
            'executor' => 'Executor',
            'stored_at' => 'Stored at',
            'result' => 'Claim result',
            'recorded' => 'Execution request claimed successfully.',
            'failed' => 'Execution request was not claimed.',
            'diagnostics' => 'Diagnostics',
            'status_error' => 'The claim workspace could not be prepared safely.',
        ],
        'ja' => [
            'title' => '実行リクエストの取得', 'description' => '指定された準備済み実行者として単回使用リクエストを原子的に消費します。',
            'open' => '取得を準備', 'refresh' => '取得画面を更新', 'hide' => '取得画面を隠す',
            'idle' => 'リクエストを取得する前に実行者準備状態を検証してください。',
            'boundary' => '取得履歴のみです。スナップショット、参照編集、アーカイブ、削除、実行は行いません。',
            'state' => '実行者準備状態', 'eligible' => '実行者適格', 'request_id' => 'リクエストID', 'request_fp' => 'リクエストフィンガープリント',
            'readiness_fp' => '実行者準備フィンガープリント', 'current_actor' => '現在の実行者', 'reason' => '取得理由',
            'confirm' => '指定実行者としてこの単回使用リクエストを消費することを確認します。', 'submit' => '実行リクエストを取得',
            'blocked' => '現在の利用者は取得できないか、すでに消費されています。', 'latest' => '取得証拠', 'none' => '取得証拠はありません。',
            'claim_id' => '取得ID', 'claimed_at' => '取得日時', 'executor' => '実行者', 'stored_at' => '保存先',
            'result' => '結果', 'recorded' => '実行リクエストを取得しました。', 'failed' => '実行リクエストは取得されませんでした。',
            'diagnostics' => '診断', 'status_error' => '取得画面を安全に準備できませんでした。',
        ],
        'ne' => [
            'title' => 'Execution request claim', 'description' => 'Named ready executor का रूपमा single-use request atomically consume गर्नुहोस्।',
            'open' => 'Request claim तयार', 'refresh' => 'Claim workspace पुनःलोड', 'hide' => 'Claim workspace लुकाउनुहोस्',
            'idle' => 'Request claim अघि executor readiness जाँच गर्नुहोस्।',
            'boundary' => 'Claim provenance मात्र। यसले snapshot, reference edit, archive, delete वा execute गर्दैन।',
            'state' => 'Executor readiness', 'eligible' => 'Executor eligible', 'request_id' => 'Request ID', 'request_fp' => 'Request fingerprint',
            'readiness_fp' => 'Executor-readiness fingerprint', 'current_actor' => 'Current executor', 'reason' => 'Claim reason',
            'confirm' => 'Named executor का रूपमा यो single-use request consume गर्दैछु।', 'submit' => 'Execution request claim',
            'blocked' => 'Current actor ले claim गर्न सक्दैन वा request पहिले नै consumed छ।', 'latest' => 'Claim evidence', 'none' => 'यो request मा claim छैन।',
            'claim_id' => 'Claim ID', 'claimed_at' => 'Claimed at', 'executor' => 'Executor', 'stored_at' => 'Stored at',
            'result' => 'Claim result', 'recorded' => 'Execution request सफलतापूर्वक claimed भयो।', 'failed' => 'Execution request claim भएन।',
            'diagnostics' => 'Diagnostics', 'status_error' => 'Claim workspace सुरक्षित रूपमा तयार भएन।',
        ],
    ];
    $set = isset($dictionary[$lang]) ? $dictionary[$lang] : $dictionary['en'];
    return (string)($set[$key] ?? $dictionary['en'][$key] ?? $key);
};

$executionClaimStatus = (string)($executionClaimWorkspace['status'] ?? 'idle');
$executionClaimSource = isset($executionClaimWorkspace['source']) && is_array($executionClaimWorkspace['source']) ? $executionClaimWorkspace['source'] : [];
$executionClaimRequest = isset($executionClaimWorkspace['request_evidence']) && is_array($executionClaimWorkspace['request_evidence']) ? $executionClaimWorkspace['request_evidence'] : [];
$executionClaimActor = isset($executionClaimWorkspace['actor_evidence']) && is_array($executionClaimWorkspace['actor_evidence']) ? $executionClaimWorkspace['actor_evidence'] : [];
$executionClaimLatest = isset($executionClaimWorkspace['latest_claim']) && is_array($executionClaimWorkspace['latest_claim']) ? $executionClaimWorkspace['latest_claim'] : null;
$executionClaimResult = isset($executionClaimWorkspace['flash']) && is_array($executionClaimWorkspace['flash']) ? $executionClaimWorkspace['flash'] : null;
$executionClaimDiagnostics = isset($executionClaimWorkspace['diagnostics']) && is_array($executionClaimWorkspace['diagnostics']) ? $executionClaimWorkspace['diagnostics'] : [];
$executionClaimBaseUrl = '/apps/studio/tools/owner-structure-scan?owner=' . rawurlencode($impactSelectedOwnerKey)
    . '&scan=1&deletion_impact=1&deletion_plan=1&deletion_change_set=1&deletion_approval=1'
    . '&deletion_execution_readiness=1&deletion_execution_dry_run=1&deletion_execution_request=1&deletion_executor_readiness=1';
?>
<section class="oss-contract" aria-label="<?= e($executionClaimText('title')) ?>">
  <header class="oss-section-head">
    <div><h3><?= e($executionClaimText('title')) ?></h3><span><?= e($executionClaimText('description')) ?></span></div>
    <span><?= e($executionClaimText('boundary')) ?></span>
  </header>

  <form method="get" action="/apps/studio/tools/owner-structure-scan" class="oss-owner-form">
    <input type="hidden" name="owner" value="<?= e($impactSelectedOwnerKey) ?>">
    <input type="hidden" name="scan" value="1"><input type="hidden" name="deletion_impact" value="1"><input type="hidden" name="deletion_plan" value="1">
    <input type="hidden" name="deletion_change_set" value="1"><input type="hidden" name="deletion_approval" value="1">
    <input type="hidden" name="deletion_execution_readiness" value="1"><input type="hidden" name="deletion_execution_dry_run" value="1">
    <input type="hidden" name="deletion_execution_request" value="1"><input type="hidden" name="deletion_executor_readiness" value="1">
    <button type="submit" name="deletion_execution_claim" value="1"><?= e($executionClaimText($executionClaimRequested ? 'refresh' : 'open')) ?></button>
    <?php if ($executionClaimRequested): ?><a href="<?= e($executionClaimBaseUrl) ?>"><?= e($executionClaimText('hide')) ?></a><?php endif; ?>
  </form>

  <?php if ($executionClaimStatus === 'idle'): ?>
    <p class="oss-planner-note"><?= e($executionClaimText('idle')) ?></p>
  <?php elseif ($executionClaimStatus === 'error'): ?>
    <p class="oss-owner-error"><strong><?= e($executionClaimText('status_error')) ?></strong></p>
  <?php else: ?>
    <div class="oss-contract-summary">
      <div class="oss-summary-card"><dt><?= e($executionClaimText('state')) ?></dt><dd><?= e((string)($executionClaimReadiness['executor_readiness'] ?? 'unknown')) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($executionClaimText('eligible')) ?></dt><dd><?= e((string)($executionClaimReadiness['executor_eligible'] ?? 'no')) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($executionClaimText('current_actor')) ?></dt><dd><?= e((string)($executionClaimActor['display_name'] ?? $executionClaimActor['actor_id'] ?? '')) ?></dd></div>
    </div>
    <dl class="oss-reference-facts">
      <div><dt><?= e($executionClaimText('request_id')) ?></dt><dd><code><?= e((string)($executionClaimSource['request_id'] ?? '')) ?></code></dd></div>
      <div><dt><?= e($executionClaimText('request_fp')) ?></dt><dd><code><?= e((string)($executionClaimSource['request_fingerprint'] ?? '')) ?></code></dd></div>
      <div><dt><?= e($executionClaimText('readiness_fp')) ?></dt><dd><code><?= e((string)($executionClaimSource['executor_readiness_fingerprint'] ?? '')) ?></code></dd></div>
    </dl>

    <?php if ($executionClaimResult !== null): ?>
      <section class="oss-section-notice"><strong><?= e($executionClaimText('result')) ?>:</strong>
        <?= e((string)($executionClaimResult['status'] ?? '') === 'recorded' ? $executionClaimText('recorded') : $executionClaimText('failed')) ?>
        <?php if (isset($executionClaimResult['claim_id'])): ?><code><?= e((string)$executionClaimResult['claim_id']) ?></code><?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if ((string)($executionClaimWorkspace['can_claim'] ?? 'no') === 'yes'): ?>
      <form method="post" action="/apps/studio/tools/owner-structure-scan/deletion-execution-claim" class="oss-contract">
        <input type="hidden" name="csrf" value="<?= e($executionClaimCsrf) ?>">
        <input type="hidden" name="owner" value="<?= e($impactSelectedOwnerKey) ?>">
        <input type="hidden" name="request_id" value="<?= e((string)($executionClaimSource['request_id'] ?? '')) ?>">
        <input type="hidden" name="request_fingerprint" value="<?= e((string)($executionClaimSource['request_fingerprint'] ?? '')) ?>">
        <input type="hidden" name="executor_readiness_fingerprint" value="<?= e((string)($executionClaimSource['executor_readiness_fingerprint'] ?? '')) ?>">
        <label style="display:grid;gap:.35rem;"><strong><?= e($executionClaimText('reason')) ?></strong><textarea name="reason" rows="3" maxlength="1000" required></textarea></label>
        <label style="display:flex;gap:.5rem;align-items:flex-start;margin-top:.75rem;"><input type="checkbox" name="claim_confirmed" value="yes" required><span><?= e($executionClaimText('confirm')) ?></span></label>
        <button type="submit" style="margin-top:.75rem;"><?= e($executionClaimText('submit')) ?></button>
      </form>
    <?php else: ?>
      <p class="oss-owner-error"><?= e($executionClaimText('blocked')) ?></p>
    <?php endif; ?>

    <details class="oss-contract-findings"<?= $executionClaimLatest !== null ? ' open' : '' ?>><summary><?= e($executionClaimText('latest')) ?></summary>
      <?php if ($executionClaimLatest === null): ?><p class="oss-empty-group"><?= e($executionClaimText('none')) ?></p><?php else:
        $latestExecutor = isset($executionClaimLatest['executor']) && is_array($executionClaimLatest['executor']) ? $executionClaimLatest['executor'] : [];
        $latestStorage = isset($executionClaimLatest['storage']) && is_array($executionClaimLatest['storage']) ? $executionClaimLatest['storage'] : [];
      ?>
        <dl class="oss-reference-facts">
          <div><dt><?= e($executionClaimText('claim_id')) ?></dt><dd><code><?= e((string)($executionClaimLatest['claim_id'] ?? $executionClaimLatest['use_id'] ?? '')) ?></code></dd></div>
          <div><dt><?= e($executionClaimText('claimed_at')) ?></dt><dd><?= e((string)($executionClaimLatest['claimed_at_utc'] ?? $executionClaimLatest['recorded_at_utc'] ?? '')) ?></dd></div>
          <div><dt><?= e($executionClaimText('executor')) ?></dt><dd><?= e((string)($latestExecutor['display_name'] ?? $latestExecutor['actor_id'] ?? '')) ?></dd></div>
          <div><dt><?= e($executionClaimText('stored_at')) ?></dt><dd><code><?= e((string)($latestStorage['relative_path'] ?? '')) ?></code></dd></div>
        </dl>
      <?php endif; ?>
    </details>
  <?php endif; ?>

  <?php if ($executionClaimDiagnostics !== []): ?>
    <details class="oss-contract-findings"><summary><?= e($executionClaimText('diagnostics')) ?> (<?= e((string)count($executionClaimDiagnostics)) ?>)</summary><ul class="oss-domain-list"><?php foreach ($executionClaimDiagnostics as $diagnostic): if (!is_array($diagnostic)) { continue; } ?><li><code><?= e((string)($diagnostic['code'] ?? '')) ?></code> <?= e((string)($diagnostic['message'] ?? '')) ?></li><?php endforeach; ?></ul></details>
  <?php endif; ?>
</section>

<?php
declare(strict_types=1);

use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureDeletionExecutionRequestWorkspaceService;

require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionExecutionRequestWorkspaceService.php';

$executionRequestRequested = (string)($_GET['deletion_execution_request'] ?? '') === '1';
$executionRequestChangeSet = isset($executionDryRunWorkspace['change_set']) && is_array($executionDryRunWorkspace['change_set'])
    ? $executionDryRunWorkspace['change_set']
    : null;
$executionRequestReadiness = isset($executionDryRunWorkspace['readiness']) && is_array($executionDryRunWorkspace['readiness'])
    ? $executionDryRunWorkspace['readiness']
    : null;
$executionRequestDryRun = isset($executionDryRunWorkspace['dry_run']) && is_array($executionDryRunWorkspace['dry_run'])
    ? $executionDryRunWorkspace['dry_run']
    : null;
$executionRequestFlash = isset($_SESSION['studio_owner_structure_deletion_execution_request_result'])
    && is_array($_SESSION['studio_owner_structure_deletion_execution_request_result'])
        ? $_SESSION['studio_owner_structure_deletion_execution_request_result']
        : null;
unset($_SESSION['studio_owner_structure_deletion_execution_request_result']);

$executionRequestWorkspace = OwnerStructureDeletionExecutionRequestWorkspaceService::build(
    $impactOwners,
    $impactSelectedOwnerKey,
    $executionRequestRequested,
    $executionRequestChangeSet,
    $executionRequestReadiness,
    $executionRequestDryRun,
    $executionRequestFlash
);
$executionRequestCsrf = isset($ownerStructureScanModel['csrf']) ? (string)$ownerStructureScanModel['csrf'] : '';
$executionRequestDefaultExpiry = gmdate('Y-m-d\TH:i:s\Z', time() + 3600);

$executionRequestText = static function (string $key): string {
    $lang = function_exists('current_lang') ? (string)current_lang() : 'en';
    $dictionary = [
        'en' => [
            'title' => 'Deletion execution request',
            'description' => 'Record an immutable request bound to the exact approved readiness and dry-run evidence.',
            'open' => 'Prepare execution request',
            'refresh' => 'Refresh request workspace',
            'hide' => 'Hide request workspace',
            'idle' => 'Complete a ready execution dry run before creating an execution request.',
            'boundary' => 'Request provenance only. This does not create a snapshot, archive files, delete the target, or authorize execution.',
            'readiness' => 'Execution readiness',
            'dry_run' => 'Dry-run state',
            'change_set' => 'Change-set fingerprint',
            'readiness_fp' => 'Readiness fingerprint',
            'dry_run_fp' => 'Dry-run fingerprint',
            'executor_id' => 'Executor actor ID',
            'executor_name' => 'Executor display name',
            'expiry' => 'Expiry in UTC',
            'reason' => 'Request reason',
            'policy' => 'Executor must be a different platform administrator. Requests expire between 5 minutes and 24 hours and remain single-use provenance.',
            'submit' => 'Record execution request',
            'blocked' => 'The current packet is not ready for an execution request.',
            'latest' => 'Latest request for this exact dry run',
            'none' => 'No execution request exists for this exact dry run.',
            'request_id' => 'Request ID',
            'requested_at' => 'Requested at',
            'expires_at' => 'Expires at',
            'requester' => 'Requester',
            'executor' => 'Named executor',
            'stored_at' => 'Stored at',
            'result' => 'Request result',
            'recorded' => 'Execution request recorded successfully.',
            'failed' => 'Execution request was not recorded.',
            'diagnostics' => 'Diagnostics',
            'status_error' => 'The execution request workspace could not be prepared safely.',
        ],
        'ja' => [
            'title' => '削除実行リクエスト', 'description' => '承認済み準備状態とドライラン証拠に紐づく不変リクエストを記録します。',
            'open' => '実行リクエストを準備', 'refresh' => 'リクエスト画面を更新', 'hide' => 'リクエスト画面を隠す',
            'idle' => '実行リクエストの前に ready のドライランを完了してください。',
            'boundary' => 'リクエスト履歴のみです。スナップショット、アーカイブ、削除、実行承認は行いません。',
            'readiness' => '実行準備状態', 'dry_run' => 'ドライラン状態', 'change_set' => '変更セットフィンガープリント',
            'readiness_fp' => '準備状態フィンガープリント', 'dry_run_fp' => 'ドライランフィンガープリント',
            'executor_id' => '実行者ID', 'executor_name' => '実行者表示名', 'expiry' => 'UTC有効期限', 'reason' => 'リクエスト理由',
            'policy' => '実行者は別の platform administrator である必要があります。有効期間は5分から24時間で、単回使用の履歴です。',
            'submit' => '実行リクエストを記録', 'blocked' => '現在のパケットは実行リクエストの準備ができていません。',
            'latest' => 'このドライランの最新リクエスト', 'none' => 'このドライランには実行リクエストがありません。',
            'request_id' => 'リクエストID', 'requested_at' => '作成日時', 'expires_at' => '有効期限', 'requester' => '依頼者', 'executor' => '実行者', 'stored_at' => '保存先',
            'result' => '結果', 'recorded' => '実行リクエストを記録しました。', 'failed' => '実行リクエストは記録されませんでした。',
            'diagnostics' => '診断', 'status_error' => '実行リクエスト画面を安全に準備できませんでした。',
        ],
        'ne' => [
            'title' => 'Deletion execution request', 'description' => 'Exact approved readiness र dry-run evidence मा बाँधिएको immutable request रेकर्ड गर्नुहोस्।',
            'open' => 'Execution request तयार', 'refresh' => 'Request workspace पुनःलोड', 'hide' => 'Request workspace लुकाउनुहोस्',
            'idle' => 'Execution request अघि ready dry run पूरा गर्नुहोस्।',
            'boundary' => 'Request provenance मात्र। यसले snapshot, archive, delete वा execution authorize गर्दैन।',
            'readiness' => 'Execution readiness', 'dry_run' => 'Dry-run state', 'change_set' => 'Change-set fingerprint',
            'readiness_fp' => 'Readiness fingerprint', 'dry_run_fp' => 'Dry-run fingerprint',
            'executor_id' => 'Executor actor ID', 'executor_name' => 'Executor display name', 'expiry' => 'Expiry in UTC', 'reason' => 'Request reason',
            'policy' => 'Executor फरक platform administrator हुनुपर्छ। Request 5 मिनेटदेखि 24 घण्टासम्म valid र single-use provenance हुन्छ।',
            'submit' => 'Execution request रेकर्ड', 'blocked' => 'हालको packet execution request का लागि ready छैन।',
            'latest' => 'यो dry run को latest request', 'none' => 'यो exact dry run का लागि request छैन।',
            'request_id' => 'Request ID', 'requested_at' => 'Requested at', 'expires_at' => 'Expires at', 'requester' => 'Requester', 'executor' => 'Named executor', 'stored_at' => 'Stored at',
            'result' => 'Request result', 'recorded' => 'Execution request सफलतापूर्वक रेकर्ड भयो।', 'failed' => 'Execution request रेकर्ड भएन।',
            'diagnostics' => 'Diagnostics', 'status_error' => 'Execution request workspace सुरक्षित रूपमा तयार भएन।',
        ],
    ];
    $set = isset($dictionary[$lang]) ? $dictionary[$lang] : $dictionary['en'];
    return (string)($set[$key] ?? $dictionary['en'][$key] ?? $key);
};

$executionRequestStatus = (string)($executionRequestWorkspace['status'] ?? 'idle');
$executionRequestSource = isset($executionRequestWorkspace['source']) && is_array($executionRequestWorkspace['source']) ? $executionRequestWorkspace['source'] : [];
$executionRequestLatest = isset($executionRequestWorkspace['latest_request']) && is_array($executionRequestWorkspace['latest_request']) ? $executionRequestWorkspace['latest_request'] : null;
$executionRequestResult = isset($executionRequestWorkspace['flash']) && is_array($executionRequestWorkspace['flash']) ? $executionRequestWorkspace['flash'] : null;
$executionRequestDiagnostics = isset($executionRequestWorkspace['diagnostics']) && is_array($executionRequestWorkspace['diagnostics']) ? $executionRequestWorkspace['diagnostics'] : [];
$executionRequestBaseUrl = '/apps/studio/tools/owner-structure-scan?owner=' . rawurlencode($impactSelectedOwnerKey) . '&scan=1&deletion_impact=1&deletion_plan=1&deletion_change_set=1&deletion_approval=1&deletion_execution_readiness=1&deletion_execution_dry_run=1';
?>
<section class="oss-contract" aria-label="<?= e($executionRequestText('title')) ?>">
  <header class="oss-section-head">
    <div><h3><?= e($executionRequestText('title')) ?></h3><span><?= e($executionRequestText('description')) ?></span></div>
    <span><?= e($executionRequestText('boundary')) ?></span>
  </header>

  <form method="get" action="/apps/studio/tools/owner-structure-scan" class="oss-owner-form">
    <input type="hidden" name="owner" value="<?= e($impactSelectedOwnerKey) ?>">
    <input type="hidden" name="scan" value="1"><input type="hidden" name="deletion_impact" value="1"><input type="hidden" name="deletion_plan" value="1">
    <input type="hidden" name="deletion_change_set" value="1"><input type="hidden" name="deletion_approval" value="1">
    <input type="hidden" name="deletion_execution_readiness" value="1"><input type="hidden" name="deletion_execution_dry_run" value="1">
    <button type="submit" name="deletion_execution_request" value="1"><?= e($executionRequestText($executionRequestRequested ? 'refresh' : 'open')) ?></button>
    <?php if ($executionRequestRequested): ?><a href="<?= e($executionRequestBaseUrl) ?>"><?= e($executionRequestText('hide')) ?></a><?php endif; ?>
  </form>

  <?php if ($executionRequestStatus === 'idle'): ?>
    <p class="oss-planner-note"><?= e($executionRequestText('idle')) ?></p>
  <?php elseif ($executionRequestStatus === 'error'): ?>
    <p class="oss-owner-error"><strong><?= e($executionRequestText('status_error')) ?></strong></p>
  <?php else: ?>
    <div class="oss-contract-summary">
      <div class="oss-summary-card"><dt><?= e($executionRequestText('readiness')) ?></dt><dd><?= e((string)$executionRequestWorkspace['execution_readiness']) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($executionRequestText('dry_run')) ?></dt><dd><?= e((string)$executionRequestWorkspace['dry_run_state']) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($executionRequestText('change_set')) ?></dt><dd><code><?= e((string)($executionRequestSource['change_set_fingerprint'] ?? '')) ?></code></dd></div>
    </div>

    <?php if ($executionRequestResult !== null): ?>
      <section class="oss-section-notice"><strong><?= e($executionRequestText('result')) ?>:</strong> <?= e((string)($executionRequestResult['status'] ?? '') === 'recorded' ? $executionRequestText('recorded') : $executionRequestText('failed')) ?> <?php if (isset($executionRequestResult['request_id'])): ?><code><?= e((string)$executionRequestResult['request_id']) ?></code><?php endif; ?></section>
    <?php endif; ?>

    <dl class="oss-reference-facts">
      <div><dt><?= e($executionRequestText('readiness_fp')) ?></dt><dd><code><?= e((string)($executionRequestSource['readiness_fingerprint'] ?? '')) ?></code></dd></div>
      <div><dt><?= e($executionRequestText('dry_run_fp')) ?></dt><dd><code><?= e((string)($executionRequestSource['dry_run_fingerprint'] ?? '')) ?></code></dd></div>
    </dl>

    <?php if ((string)$executionRequestWorkspace['can_request'] !== 'yes'): ?>
      <p class="oss-owner-error"><?= e($executionRequestText('blocked')) ?></p>
    <?php else: ?>
      <form method="post" action="/apps/studio/tools/owner-structure-scan/deletion-execution-request" class="oss-contract">
        <input type="hidden" name="csrf" value="<?= e($executionRequestCsrf) ?>">
        <input type="hidden" name="owner" value="<?= e($impactSelectedOwnerKey) ?>">
        <?php foreach (['change_set_fingerprint', 'plan_fingerprint', 'readiness_fingerprint', 'dry_run_fingerprint', 'approval_record_id', 'approval_record_fingerprint'] as $field): ?>
          <input type="hidden" name="<?= e($field) ?>" value="<?= e((string)($executionRequestSource[$field] ?? '')) ?>">
        <?php endforeach; ?>
        <p class="oss-planner-note"><?= e($executionRequestText('policy')) ?></p>
        <label style="display:grid;gap:.35rem;"><strong><?= e($executionRequestText('executor_id')) ?></strong><input type="text" name="executor_actor_id" maxlength="160" required></label>
        <label style="display:grid;gap:.35rem;margin-top:.75rem;"><strong><?= e($executionRequestText('executor_name')) ?></strong><input type="text" name="executor_display_name" maxlength="200"></label>
        <label style="display:grid;gap:.35rem;margin-top:.75rem;"><strong><?= e($executionRequestText('expiry')) ?></strong><input type="text" name="expires_at_utc" value="<?= e($executionRequestDefaultExpiry) ?>" maxlength="40" required></label>
        <label style="display:grid;gap:.35rem;margin-top:.75rem;"><strong><?= e($executionRequestText('reason')) ?></strong><textarea name="reason" rows="4" maxlength="2000" required></textarea></label>
        <div class="oss-init-actions" style="gap:.5rem;margin-top:1rem;"><button type="submit"><?= e($executionRequestText('submit')) ?></button></div>
      </form>
    <?php endif; ?>

    <details class="oss-contract-findings" open>
      <summary><?= e($executionRequestText('latest')) ?></summary>
      <?php if ($executionRequestLatest === null): ?><p class="oss-empty-group"><?= e($executionRequestText('none')) ?></p><?php else:
        $latestRequester = isset($executionRequestLatest['requester']) && is_array($executionRequestLatest['requester']) ? $executionRequestLatest['requester'] : [];
        $latestExecutor = isset($executionRequestLatest['executor_policy']) && is_array($executionRequestLatest['executor_policy']) ? $executionRequestLatest['executor_policy'] : [];
        $latestStorage = isset($executionRequestLatest['storage']) && is_array($executionRequestLatest['storage']) ? $executionRequestLatest['storage'] : [];
      ?>
        <dl class="oss-reference-facts">
          <div><dt><?= e($executionRequestText('request_id')) ?></dt><dd><code><?= e((string)($executionRequestLatest['request_id'] ?? '')) ?></code></dd></div>
          <div><dt><?= e($executionRequestText('requested_at')) ?></dt><dd><?= e((string)($executionRequestLatest['requested_at_utc'] ?? '')) ?></dd></div>
          <div><dt><?= e($executionRequestText('expires_at')) ?></dt><dd><?= e((string)($executionRequestLatest['expires_at_utc'] ?? '')) ?></dd></div>
          <div><dt><?= e($executionRequestText('requester')) ?></dt><dd><?= e((string)($latestRequester['display_name'] ?? '')) ?></dd></div>
          <div><dt><?= e($executionRequestText('executor')) ?></dt><dd><?= e((string)($latestExecutor['executor_display_name'] ?? $latestExecutor['executor_actor_id'] ?? '')) ?></dd></div>
          <div><dt><?= e($executionRequestText('stored_at')) ?></dt><dd><code><?= e((string)($latestStorage['relative_path'] ?? '')) ?></code></dd></div>
        </dl>
      <?php endif; ?>
    </details>
  <?php endif; ?>

  <?php if ($executionRequestDiagnostics !== []): ?>
    <details class="oss-contract-findings"><summary><?= e($executionRequestText('diagnostics')) ?> (<?= e((string)count($executionRequestDiagnostics)) ?>)</summary><ul class="oss-domain-list"><?php foreach ($executionRequestDiagnostics as $diagnostic): if (!is_array($diagnostic)) { continue; } ?><li><code><?= e((string)($diagnostic['code'] ?? '')) ?></code> <?= e((string)($diagnostic['message'] ?? '')) ?></li><?php endforeach; ?></ul></details>
  <?php endif; ?>
</section>

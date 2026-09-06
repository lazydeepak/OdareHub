<?php
declare(strict_types=1);

use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureDeletionExecutorReadinessWorkspaceService;
use Platform\Security\PlatformAuthority;

require_once APP_ROOT . '/apps/Platform/bootstrap.php';
require_once APP_ROOT . '/platform/Security/PlatformAuthority.php';
require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionExecutorReadinessWorkspaceService.php';

$executorReadinessRequested = (string)($_GET['deletion_executor_readiness'] ?? '') === '1';
$executorReadinessActor = PlatformAuthority::resolveCurrentActor();
$executorReadinessChangeSet = isset($executionRequestChangeSet) && is_array($executionRequestChangeSet) ? $executionRequestChangeSet : null;
$executorReadinessApproval = isset($executionRequestReadiness) && is_array($executionRequestReadiness) ? $executionRequestReadiness : null;
$executorReadinessDryRun = isset($executionRequestDryRun) && is_array($executionRequestDryRun) ? $executionRequestDryRun : null;
$executorReadinessWorkspace = OwnerStructureDeletionExecutorReadinessWorkspaceService::build(
    $impactOwners,
    $impactSelectedOwnerKey,
    $executorReadinessRequested,
    $executorReadinessChangeSet,
    $executorReadinessApproval,
    $executorReadinessDryRun,
    is_array($executorReadinessActor) ? $executorReadinessActor : null,
    APP_ROOT
);

$executorReadinessText = static function (string $key): string {
    $lang = function_exists('current_lang') ? (string)current_lang() : 'en';
    $dictionary = [
        'en' => [
            'title' => 'Execution request validity and executor readiness',
            'description' => 'Validate the immutable request, expiry, named executor, and single-use evidence against the current packet.',
            'open' => 'Verify executor readiness', 'refresh' => 'Refresh executor readiness', 'hide' => 'Hide executor readiness',
            'idle' => 'Create an execution request before verifying the named executor.',
            'boundary' => 'Verification only. This workspace cannot claim the request, create a snapshot, archive files, delete the target, or execute anything.',
            'state' => 'Executor readiness', 'eligible' => 'Executor eligible', 'request_valid' => 'Request valid',
            'identity_valid' => 'Executor identity valid', 'single_use' => 'Single-use available', 'fingerprint' => 'Executor-readiness fingerprint',
            'request' => 'Execution request evidence', 'request_id' => 'Request ID', 'requested_at' => 'Requested at', 'expires_at' => 'Expires at',
            'requester' => 'Requester', 'named_executor' => 'Named executor', 'current_actor' => 'Current actor', 'authority' => 'Authority role',
            'exact_match' => 'Exact packet match', 'stored_at' => 'Stored at', 'use_evidence' => 'Single-use evidence',
            'blockers' => 'Blocking reasons', 'diagnostics' => 'Diagnostics', 'none' => 'None',
            'status_error' => 'Executor readiness could not be verified safely.',
        ],
        'ja' => [
            'title' => '実行リクエスト有効性と実行者準備状態', 'description' => '現在のパケットに対して不変リクエスト、有効期限、指定実行者、単回使用証拠を検証します。',
            'open' => '実行者準備状態を検証', 'refresh' => '準備状態を更新', 'hide' => '準備状態を隠す',
            'idle' => '指定実行者を検証する前に実行リクエストを作成してください。',
            'boundary' => '検証専用です。要求の取得、スナップショット、アーカイブ、削除、実行は行いません。',
            'state' => '実行者準備状態', 'eligible' => '実行適格', 'request_valid' => 'リクエスト有効', 'identity_valid' => '実行者本人確認',
            'single_use' => '単回使用可能', 'fingerprint' => '準備状態フィンガープリント', 'request' => '実行リクエスト証拠',
            'request_id' => 'リクエストID', 'requested_at' => '作成日時', 'expires_at' => '有効期限', 'requester' => '依頼者',
            'named_executor' => '指定実行者', 'current_actor' => '現在の利用者', 'authority' => '権限ロール', 'exact_match' => '完全一致',
            'stored_at' => '保存先', 'use_evidence' => '単回使用証拠', 'blockers' => '阻害理由', 'diagnostics' => '診断', 'none' => 'なし',
            'status_error' => '実行者準備状態を安全に検証できませんでした。',
        ],
        'ne' => [
            'title' => 'Execution request validity र executor readiness', 'description' => 'हालको packet सँग immutable request, expiry, named executor र single-use evidence जाँच गर्नुहोस्।',
            'open' => 'Executor readiness जाँच', 'refresh' => 'Readiness पुनः जाँच', 'hide' => 'Readiness लुकाउनुहोस्',
            'idle' => 'Named executor जाँच्नुअघि execution request बनाउनुहोस्।',
            'boundary' => 'Verification मात्र। यसले request claim, snapshot, archive, delete वा execute गर्दैन।',
            'state' => 'Executor readiness', 'eligible' => 'Executor eligible', 'request_valid' => 'Request valid', 'identity_valid' => 'Executor identity valid',
            'single_use' => 'Single-use available', 'fingerprint' => 'Executor-readiness fingerprint', 'request' => 'Execution request evidence',
            'request_id' => 'Request ID', 'requested_at' => 'Requested at', 'expires_at' => 'Expires at', 'requester' => 'Requester',
            'named_executor' => 'Named executor', 'current_actor' => 'Current actor', 'authority' => 'Authority role', 'exact_match' => 'Exact packet match',
            'stored_at' => 'Stored at', 'use_evidence' => 'Single-use evidence', 'blockers' => 'Blocking reasons', 'diagnostics' => 'Diagnostics',
            'none' => 'None', 'status_error' => 'Executor readiness सुरक्षित रूपमा जाँच्न सकिएन।',
        ],
    ];
    $set = isset($dictionary[$lang]) ? $dictionary[$lang] : $dictionary['en'];
    return (string)($set[$key] ?? $dictionary['en'][$key] ?? $key);
};

$executorReadinessStatus = (string)($executorReadinessWorkspace['status'] ?? 'idle');
$executorReadinessRequest = isset($executorReadinessWorkspace['request_evidence']) && is_array($executorReadinessWorkspace['request_evidence']) ? $executorReadinessWorkspace['request_evidence'] : [];
$executorReadinessRequester = isset($executorReadinessRequest['requester']) && is_array($executorReadinessRequest['requester']) ? $executorReadinessRequest['requester'] : [];
$executorReadinessPolicy = isset($executorReadinessRequest['executor_policy']) && is_array($executorReadinessRequest['executor_policy']) ? $executorReadinessRequest['executor_policy'] : [];
$executorReadinessActorEvidence = isset($executorReadinessWorkspace['actor_evidence']) && is_array($executorReadinessWorkspace['actor_evidence']) ? $executorReadinessWorkspace['actor_evidence'] : [];
$executorReadinessStorage = isset($executorReadinessRequest['storage']) && is_array($executorReadinessRequest['storage']) ? $executorReadinessRequest['storage'] : [];
$executorReadinessUse = isset($executorReadinessWorkspace['single_use_evidence']) && is_array($executorReadinessWorkspace['single_use_evidence']) ? $executorReadinessWorkspace['single_use_evidence'] : [];
$executorReadinessBlockers = isset($executorReadinessWorkspace['blocking_reasons']) && is_array($executorReadinessWorkspace['blocking_reasons']) ? $executorReadinessWorkspace['blocking_reasons'] : [];
$executorReadinessDiagnostics = isset($executorReadinessWorkspace['diagnostics']) && is_array($executorReadinessWorkspace['diagnostics']) ? $executorReadinessWorkspace['diagnostics'] : [];
$executorReadinessBaseUrl = '/apps/studio/tools/owner-structure-scan?owner=' . rawurlencode($impactSelectedOwnerKey) . '&scan=1&deletion_impact=1&deletion_plan=1&deletion_change_set=1&deletion_approval=1&deletion_execution_readiness=1&deletion_execution_dry_run=1&deletion_execution_request=1';
?>
<section class="oss-contract" aria-label="<?= e($executorReadinessText('title')) ?>">
  <header class="oss-section-head">
    <div><h3><?= e($executorReadinessText('title')) ?></h3><span><?= e($executorReadinessText('description')) ?></span></div>
    <span><?= e($executorReadinessText('boundary')) ?></span>
  </header>

  <form method="get" action="/apps/studio/tools/owner-structure-scan" class="oss-owner-form">
    <input type="hidden" name="owner" value="<?= e($impactSelectedOwnerKey) ?>">
    <input type="hidden" name="scan" value="1"><input type="hidden" name="deletion_impact" value="1"><input type="hidden" name="deletion_plan" value="1">
    <input type="hidden" name="deletion_change_set" value="1"><input type="hidden" name="deletion_approval" value="1">
    <input type="hidden" name="deletion_execution_readiness" value="1"><input type="hidden" name="deletion_execution_dry_run" value="1">
    <input type="hidden" name="deletion_execution_request" value="1">
    <button type="submit" name="deletion_executor_readiness" value="1"><?= e($executorReadinessText($executorReadinessRequested ? 'refresh' : 'open')) ?></button>
    <?php if ($executorReadinessRequested): ?><a href="<?= e($executorReadinessBaseUrl) ?>"><?= e($executorReadinessText('hide')) ?></a><?php endif; ?>
  </form>

  <?php if ($executorReadinessStatus === 'idle'): ?>
    <p class="oss-planner-note"><?= e($executorReadinessText('idle')) ?></p>
  <?php elseif ($executorReadinessStatus === 'error'): ?>
    <p class="oss-owner-error"><strong><?= e($executorReadinessText('status_error')) ?></strong></p>
  <?php else: ?>
    <div class="oss-contract-summary">
      <div class="oss-summary-card"><dt><?= e($executorReadinessText('state')) ?></dt><dd><?= e((string)$executorReadinessWorkspace['executor_readiness']) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($executorReadinessText('eligible')) ?></dt><dd><?= e((string)$executorReadinessWorkspace['executor_eligible']) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($executorReadinessText('request_valid')) ?></dt><dd><?= e((string)$executorReadinessWorkspace['request_valid']) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($executorReadinessText('identity_valid')) ?></dt><dd><?= e((string)$executorReadinessWorkspace['executor_identity_valid']) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($executorReadinessText('single_use')) ?></dt><dd><?= e((string)$executorReadinessWorkspace['single_use_available']) ?></dd></div>
    </div>
    <dl class="oss-reference-facts"><div><dt><?= e($executorReadinessText('fingerprint')) ?></dt><dd><code><?= e((string)$executorReadinessWorkspace['executor_readiness_fingerprint']) ?></code></dd></div></dl>

    <details class="oss-contract-findings" open>
      <summary><?= e($executorReadinessText('request')) ?></summary>
      <?php if ((string)($executorReadinessRequest['present'] ?? 'no') !== 'yes'): ?>
        <p class="oss-empty-group"><?= e($executorReadinessText('none')) ?></p>
      <?php else: ?>
        <dl class="oss-reference-facts">
          <div><dt><?= e($executorReadinessText('request_id')) ?></dt><dd><code><?= e((string)($executorReadinessRequest['request_id'] ?? '')) ?></code></dd></div>
          <div><dt><?= e($executorReadinessText('requested_at')) ?></dt><dd><?= e((string)($executorReadinessRequest['requested_at_utc'] ?? '')) ?></dd></div>
          <div><dt><?= e($executorReadinessText('expires_at')) ?></dt><dd><?= e((string)($executorReadinessRequest['expires_at_utc'] ?? '')) ?></dd></div>
          <div><dt><?= e($executorReadinessText('requester')) ?></dt><dd><?= e((string)($executorReadinessRequester['display_name'] ?? $executorReadinessRequester['actor_id'] ?? '')) ?></dd></div>
          <div><dt><?= e($executorReadinessText('named_executor')) ?></dt><dd><?= e((string)($executorReadinessPolicy['executor_display_name'] ?? $executorReadinessPolicy['executor_actor_id'] ?? '')) ?></dd></div>
          <div><dt><?= e($executorReadinessText('current_actor')) ?></dt><dd><?= e((string)($executorReadinessActorEvidence['display_name'] ?? $executorReadinessActorEvidence['actor_id'] ?? '')) ?></dd></div>
          <div><dt><?= e($executorReadinessText('authority')) ?></dt><dd><?= e((string)($executorReadinessActorEvidence['authority_role'] ?? '')) ?></dd></div>
          <div><dt><?= e($executorReadinessText('exact_match')) ?></dt><dd><?= e((string)($executorReadinessRequest['exact_packet_match'] ?? 'no')) ?></dd></div>
          <div><dt><?= e($executorReadinessText('stored_at')) ?></dt><dd><code><?= e((string)($executorReadinessStorage['relative_path'] ?? '')) ?></code></dd></div>
        </dl>
      <?php endif; ?>
    </details>

    <details class="oss-contract-findings"<?= $executorReadinessUse !== [] ? ' open' : '' ?>><summary><?= e($executorReadinessText('use_evidence')) ?></summary>
      <?php if ($executorReadinessUse === []): ?><p class="oss-empty-group"><?= e($executorReadinessText('none')) ?></p><?php else: ?><pre><?= e((string)json_encode($executorReadinessUse, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre><?php endif; ?>
    </details>
    <details class="oss-contract-findings"<?= $executorReadinessBlockers !== [] ? ' open' : '' ?>><summary><?= e($executorReadinessText('blockers')) ?> (<?= e((string)count($executorReadinessBlockers)) ?>)</summary>
      <?php if ($executorReadinessBlockers === []): ?><p class="oss-empty-group"><?= e($executorReadinessText('none')) ?></p><?php else: ?><ul class="oss-domain-list"><?php foreach ($executorReadinessBlockers as $reason): ?><li><code><?= e((string)$reason) ?></code></li><?php endforeach; ?></ul><?php endif; ?>
    </details>
  <?php endif; ?>

  <?php if ($executorReadinessDiagnostics !== []): ?><details class="oss-contract-findings"><summary><?= e($executorReadinessText('diagnostics')) ?> (<?= e((string)count($executorReadinessDiagnostics)) ?>)</summary><ul class="oss-domain-list"><?php foreach ($executorReadinessDiagnostics as $diagnostic): if (!is_array($diagnostic)) { continue; } ?><li><code><?= e((string)($diagnostic['code'] ?? '')) ?></code> <?= e((string)($diagnostic['message'] ?? '')) ?></li><?php endforeach; ?></ul></details><?php endif; ?>
</section>

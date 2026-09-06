<?php
declare(strict_types=1);

use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureDeletionSnapshotReadinessWorkspaceService;
use Platform\Security\PlatformAuthority;

require_once APP_ROOT . '/apps/Platform/bootstrap.php';
require_once APP_ROOT . '/platform/Security/PlatformAuthority.php';
require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionSnapshotReadinessWorkspaceService.php';

$snapshotReadinessRequested = (string)($_GET['deletion_snapshot_readiness'] ?? '') === '1';
$snapshotReadinessActor = PlatformAuthority::resolveCurrentActor();
$snapshotReadinessChangeSet = isset($executionRequestChangeSet) && is_array($executionRequestChangeSet) ? $executionRequestChangeSet : null;
$snapshotReadinessApproval = isset($executionRequestReadiness) && is_array($executionRequestReadiness) ? $executionRequestReadiness : null;
$snapshotReadinessDryRun = isset($executionRequestDryRun) && is_array($executionRequestDryRun) ? $executionRequestDryRun : null;
$snapshotReadinessExecutor = isset($executorReadinessWorkspace['assessment']) && is_array($executorReadinessWorkspace['assessment']) ? $executorReadinessWorkspace['assessment'] : null;
$snapshotReadinessWorkspace = OwnerStructureDeletionSnapshotReadinessWorkspaceService::build(
    $impactOwners,
    $impactSelectedOwnerKey,
    $snapshotReadinessRequested,
    $snapshotReadinessChangeSet,
    $snapshotReadinessApproval,
    $snapshotReadinessDryRun,
    $snapshotReadinessExecutor,
    is_array($snapshotReadinessActor) ? $snapshotReadinessActor : null,
    APP_ROOT
);

$snapshotReadinessText = static function (string $key): string {
    $lang = function_exists('current_lang') ? (string)current_lang() : 'en';
    $dictionary = [
        'en' => [
            'title' => 'Claim validity and snapshot readiness',
            'description' => 'Revalidate the immutable claim, current executor, rollback source, and deterministic snapshot destination.',
            'open' => 'Verify snapshot readiness', 'refresh' => 'Refresh snapshot readiness', 'hide' => 'Hide snapshot readiness',
            'idle' => 'Claim the execution request before verifying snapshot readiness.',
            'boundary' => 'Verification only. This workspace cannot create a snapshot, copy files, edit references, archive files, delete the target, or execute anything.',
            'state' => 'Snapshot readiness', 'ready' => 'Snapshot ready', 'claim_valid' => 'Claim valid',
            'executor_valid' => 'Executor identity valid', 'source_available' => 'Source available', 'destination_available' => 'Destination available',
            'fingerprint' => 'Snapshot-readiness fingerprint', 'claim' => 'Claim evidence', 'claim_id' => 'Claim ID',
            'claimed_at' => 'Claimed at', 'executor' => 'Claim executor', 'current_actor' => 'Current actor', 'stored_at' => 'Stored at',
            'snapshot' => 'Snapshot contract', 'source' => 'Source path', 'destination' => 'Destination path', 'archive_mode' => 'Archive mode',
            'overwrite' => 'Overwrite allowed', 'checks' => 'Preflight checks', 'blockers' => 'Blocking reasons', 'diagnostics' => 'Diagnostics',
            'none' => 'None', 'status_error' => 'Snapshot readiness could not be verified safely.',
        ],
        'ja' => [
            'title' => '取得有効性とスナップショット準備状態', 'description' => '不変取得、現在の実行者、ロールバック元、決定的な保存先を再検証します。',
            'open' => '準備状態を検証', 'refresh' => '準備状態を更新', 'hide' => '準備状態を隠す',
            'idle' => 'スナップショット準備状態を検証する前に実行リクエストを取得してください。',
            'boundary' => '検証専用です。スナップショット作成、コピー、参照編集、アーカイブ、削除、実行は行いません。',
            'state' => 'スナップショット準備状態', 'ready' => '準備完了', 'claim_valid' => '取得有効', 'executor_valid' => '実行者本人確認',
            'source_available' => '元データ利用可能', 'destination_available' => '保存先利用可能', 'fingerprint' => '準備状態フィンガープリント',
            'claim' => '取得証拠', 'claim_id' => '取得ID', 'claimed_at' => '取得日時', 'executor' => '取得実行者', 'current_actor' => '現在の利用者',
            'stored_at' => '保存先', 'snapshot' => 'スナップショット契約', 'source' => '元パス', 'destination' => '保存先パス',
            'archive_mode' => 'アーカイブ方式', 'overwrite' => '上書き許可', 'checks' => '事前確認', 'blockers' => '阻害理由',
            'diagnostics' => '診断', 'none' => 'なし', 'status_error' => 'スナップショット準備状態を安全に検証できませんでした。',
        ],
        'ne' => [
            'title' => 'Claim validity र snapshot readiness', 'description' => 'Immutable claim, current executor, rollback source र deterministic destination पुनः जाँच गर्नुहोस्।',
            'open' => 'Snapshot readiness जाँच', 'refresh' => 'Readiness पुनः जाँच', 'hide' => 'Readiness लुकाउनुहोस्',
            'idle' => 'Snapshot readiness जाँच्नुअघि execution request claim गर्नुहोस्।',
            'boundary' => 'Verification मात्र। यसले snapshot create, file copy, reference edit, archive, delete वा execute गर्दैन।',
            'state' => 'Snapshot readiness', 'ready' => 'Snapshot ready', 'claim_valid' => 'Claim valid', 'executor_valid' => 'Executor identity valid',
            'source_available' => 'Source available', 'destination_available' => 'Destination available', 'fingerprint' => 'Snapshot-readiness fingerprint',
            'claim' => 'Claim evidence', 'claim_id' => 'Claim ID', 'claimed_at' => 'Claimed at', 'executor' => 'Claim executor', 'current_actor' => 'Current actor',
            'stored_at' => 'Stored at', 'snapshot' => 'Snapshot contract', 'source' => 'Source path', 'destination' => 'Destination path',
            'archive_mode' => 'Archive mode', 'overwrite' => 'Overwrite allowed', 'checks' => 'Preflight checks', 'blockers' => 'Blocking reasons',
            'diagnostics' => 'Diagnostics', 'none' => 'None', 'status_error' => 'Snapshot readiness सुरक्षित रूपमा जाँच्न सकिएन।',
        ],
    ];
    $set = isset($dictionary[$lang]) ? $dictionary[$lang] : $dictionary['en'];
    return (string)($set[$key] ?? $dictionary['en'][$key] ?? $key);
};

$snapshotReadinessStatus = (string)($snapshotReadinessWorkspace['status'] ?? 'idle');
$snapshotReadinessClaim = isset($snapshotReadinessWorkspace['claim_evidence']) && is_array($snapshotReadinessWorkspace['claim_evidence']) ? $snapshotReadinessWorkspace['claim_evidence'] : [];
$snapshotReadinessClaimExecutor = isset($snapshotReadinessClaim['executor']) && is_array($snapshotReadinessClaim['executor']) ? $snapshotReadinessClaim['executor'] : [];
$snapshotReadinessActorEvidence = isset($snapshotReadinessWorkspace['actor_evidence']) && is_array($snapshotReadinessWorkspace['actor_evidence']) ? $snapshotReadinessWorkspace['actor_evidence'] : [];
$snapshotReadinessClaimStorage = isset($snapshotReadinessClaim['storage']) && is_array($snapshotReadinessClaim['storage']) ? $snapshotReadinessClaim['storage'] : [];
$snapshotReadinessContract = isset($snapshotReadinessWorkspace['snapshot_contract']) && is_array($snapshotReadinessWorkspace['snapshot_contract']) ? $snapshotReadinessWorkspace['snapshot_contract'] : [];
$snapshotReadinessChecks = isset($snapshotReadinessWorkspace['preflight_checks']) && is_array($snapshotReadinessWorkspace['preflight_checks']) ? $snapshotReadinessWorkspace['preflight_checks'] : [];
$snapshotReadinessBlockers = isset($snapshotReadinessWorkspace['blocking_reasons']) && is_array($snapshotReadinessWorkspace['blocking_reasons']) ? $snapshotReadinessWorkspace['blocking_reasons'] : [];
$snapshotReadinessDiagnostics = isset($snapshotReadinessWorkspace['diagnostics']) && is_array($snapshotReadinessWorkspace['diagnostics']) ? $snapshotReadinessWorkspace['diagnostics'] : [];
$snapshotReadinessBaseUrl = '/apps/studio/tools/owner-structure-scan?owner=' . rawurlencode($impactSelectedOwnerKey)
    . '&scan=1&deletion_impact=1&deletion_plan=1&deletion_change_set=1&deletion_approval=1'
    . '&deletion_execution_readiness=1&deletion_execution_dry_run=1&deletion_execution_request=1'
    . '&deletion_executor_readiness=1&deletion_execution_claim=1';
?>
<section class="oss-contract" aria-label="<?= e($snapshotReadinessText('title')) ?>">
  <header class="oss-section-head">
    <div><h3><?= e($snapshotReadinessText('title')) ?></h3><span><?= e($snapshotReadinessText('description')) ?></span></div>
    <span><?= e($snapshotReadinessText('boundary')) ?></span>
  </header>

  <form method="get" action="/apps/studio/tools/owner-structure-scan" class="oss-owner-form">
    <input type="hidden" name="owner" value="<?= e($impactSelectedOwnerKey) ?>">
    <input type="hidden" name="scan" value="1"><input type="hidden" name="deletion_impact" value="1"><input type="hidden" name="deletion_plan" value="1">
    <input type="hidden" name="deletion_change_set" value="1"><input type="hidden" name="deletion_approval" value="1">
    <input type="hidden" name="deletion_execution_readiness" value="1"><input type="hidden" name="deletion_execution_dry_run" value="1">
    <input type="hidden" name="deletion_execution_request" value="1"><input type="hidden" name="deletion_executor_readiness" value="1">
    <input type="hidden" name="deletion_execution_claim" value="1">
    <button type="submit" name="deletion_snapshot_readiness" value="1"><?= e($snapshotReadinessText($snapshotReadinessRequested ? 'refresh' : 'open')) ?></button>
    <?php if ($snapshotReadinessRequested): ?><a href="<?= e($snapshotReadinessBaseUrl) ?>"><?= e($snapshotReadinessText('hide')) ?></a><?php endif; ?>
  </form>

  <?php if ($snapshotReadinessStatus === 'idle'): ?>
    <p class="oss-planner-note"><?= e($snapshotReadinessText('idle')) ?></p>
  <?php elseif ($snapshotReadinessStatus === 'error'): ?>
    <p class="oss-owner-error"><strong><?= e($snapshotReadinessText('status_error')) ?></strong></p>
  <?php else: ?>
    <div class="oss-contract-summary">
      <div class="oss-summary-card"><dt><?= e($snapshotReadinessText('state')) ?></dt><dd><?= e((string)$snapshotReadinessWorkspace['snapshot_readiness']) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($snapshotReadinessText('ready')) ?></dt><dd><?= e((string)$snapshotReadinessWorkspace['snapshot_ready']) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($snapshotReadinessText('claim_valid')) ?></dt><dd><?= e((string)$snapshotReadinessWorkspace['claim_valid']) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($snapshotReadinessText('executor_valid')) ?></dt><dd><?= e((string)$snapshotReadinessWorkspace['executor_identity_valid']) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($snapshotReadinessText('source_available')) ?></dt><dd><?= e((string)$snapshotReadinessWorkspace['source_available']) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($snapshotReadinessText('destination_available')) ?></dt><dd><?= e((string)$snapshotReadinessWorkspace['destination_available']) ?></dd></div>
    </div>
    <dl class="oss-reference-facts"><div><dt><?= e($snapshotReadinessText('fingerprint')) ?></dt><dd><code><?= e((string)$snapshotReadinessWorkspace['snapshot_readiness_fingerprint']) ?></code></dd></div></dl>

    <details class="oss-contract-findings" open><summary><?= e($snapshotReadinessText('claim')) ?></summary>
      <?php if ((string)($snapshotReadinessClaim['present'] ?? 'no') !== 'yes'): ?><p class="oss-empty-group"><?= e($snapshotReadinessText('none')) ?></p><?php else: ?>
        <dl class="oss-reference-facts">
          <div><dt><?= e($snapshotReadinessText('claim_id')) ?></dt><dd><code><?= e((string)($snapshotReadinessClaim['claim_id'] ?? '')) ?></code></dd></div>
          <div><dt><?= e($snapshotReadinessText('claimed_at')) ?></dt><dd><?= e((string)($snapshotReadinessClaim['claimed_at_utc'] ?? '')) ?></dd></div>
          <div><dt><?= e($snapshotReadinessText('executor')) ?></dt><dd><?= e((string)($snapshotReadinessClaimExecutor['display_name'] ?? $snapshotReadinessClaimExecutor['actor_id'] ?? '')) ?></dd></div>
          <div><dt><?= e($snapshotReadinessText('current_actor')) ?></dt><dd><?= e((string)($snapshotReadinessActorEvidence['display_name'] ?? $snapshotReadinessActorEvidence['actor_id'] ?? '')) ?></dd></div>
          <div><dt><?= e($snapshotReadinessText('stored_at')) ?></dt><dd><code><?= e((string)($snapshotReadinessClaimStorage['relative_path'] ?? '')) ?></code></dd></div>
        </dl>
      <?php endif; ?>
    </details>

    <details class="oss-contract-findings" open><summary><?= e($snapshotReadinessText('snapshot')) ?></summary>
      <dl class="oss-reference-facts">
        <div><dt><?= e($snapshotReadinessText('source')) ?></dt><dd><code><?= e((string)($snapshotReadinessContract['source_path'] ?? '')) ?></code></dd></div>
        <div><dt><?= e($snapshotReadinessText('destination')) ?></dt><dd><code><?= e((string)($snapshotReadinessContract['destination_path'] ?? '')) ?></code></dd></div>
        <div><dt><?= e($snapshotReadinessText('archive_mode')) ?></dt><dd><?= e((string)($snapshotReadinessContract['archive_mode'] ?? '')) ?></dd></div>
        <div><dt><?= e($snapshotReadinessText('overwrite')) ?></dt><dd><?= e((string)($snapshotReadinessContract['overwrite_allowed'] ?? '')) ?></dd></div>
      </dl>
    </details>

    <details class="oss-contract-findings"><summary><?= e($snapshotReadinessText('checks')) ?> (<?= e((string)count($snapshotReadinessChecks)) ?>)</summary>
      <?php if ($snapshotReadinessChecks === []): ?><p class="oss-empty-group"><?= e($snapshotReadinessText('none')) ?></p><?php else: ?><pre><?= e((string)json_encode($snapshotReadinessChecks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre><?php endif; ?>
    </details>
    <details class="oss-contract-findings"<?= $snapshotReadinessBlockers !== [] ? ' open' : '' ?>><summary><?= e($snapshotReadinessText('blockers')) ?> (<?= e((string)count($snapshotReadinessBlockers)) ?>)</summary>
      <?php if ($snapshotReadinessBlockers === []): ?><p class="oss-empty-group"><?= e($snapshotReadinessText('none')) ?></p><?php else: ?><ul class="oss-domain-list"><?php foreach ($snapshotReadinessBlockers as $reason): ?><li><code><?= e((string)$reason) ?></code></li><?php endforeach; ?></ul><?php endif; ?>
    </details>
  <?php endif; ?>

  <?php if ($snapshotReadinessDiagnostics !== []): ?>
    <details class="oss-contract-findings"><summary><?= e($snapshotReadinessText('diagnostics')) ?> (<?= e((string)count($snapshotReadinessDiagnostics)) ?>)</summary><ul class="oss-domain-list"><?php foreach ($snapshotReadinessDiagnostics as $diagnostic): if (!is_array($diagnostic)) { continue; } ?><li><code><?= e((string)($diagnostic['code'] ?? '')) ?></code> <?= e((string)($diagnostic['message'] ?? '')) ?></li><?php endforeach; ?></ul></details>
  <?php endif; ?>
</section>

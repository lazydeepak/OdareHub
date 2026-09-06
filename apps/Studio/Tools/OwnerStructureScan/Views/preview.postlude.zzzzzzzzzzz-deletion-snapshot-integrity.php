<?php
declare(strict_types=1);

use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureDeletionSnapshotIntegrityWorkspaceService;
use Platform\Security\PlatformAuthority;

require_once APP_ROOT . '/apps/Platform/bootstrap.php';
require_once APP_ROOT . '/platform/Security/PlatformAuthority.php';
require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionSnapshotIntegrityWorkspaceService.php';

$snapshotIntegrityRequested = (string)($_GET['deletion_snapshot_integrity'] ?? '') === '1';
$snapshotIntegrityActor = PlatformAuthority::resolveCurrentActor();
$snapshotIntegrityChangeSet = isset($snapshotReadinessChangeSet) && is_array($snapshotReadinessChangeSet) ? $snapshotReadinessChangeSet : null;
$snapshotIntegrityApproval = isset($snapshotReadinessApproval) && is_array($snapshotReadinessApproval) ? $snapshotReadinessApproval : null;
$snapshotIntegrityDryRun = isset($snapshotReadinessDryRun) && is_array($snapshotReadinessDryRun) ? $snapshotReadinessDryRun : null;
$snapshotIntegrityExecutor = isset($snapshotReadinessExecutor) && is_array($snapshotReadinessExecutor) ? $snapshotReadinessExecutor : null;
$snapshotIntegrityWorkspace = OwnerStructureDeletionSnapshotIntegrityWorkspaceService::build(
    $impactOwners,
    $impactSelectedOwnerKey,
    $snapshotIntegrityRequested,
    $snapshotIntegrityChangeSet,
    $snapshotIntegrityApproval,
    $snapshotIntegrityDryRun,
    $snapshotIntegrityExecutor,
    is_array($snapshotIntegrityActor) ? $snapshotIntegrityActor : null,
    APP_ROOT
);

$snapshotIntegrityText = static function (string $key): string {
    $lang = function_exists('current_lang') ? (string)current_lang() : 'en';
    $dictionary = [
        'en' => [
            'title' => 'Snapshot integrity and execution readiness',
            'description' => 'Re-read the immutable manifest, verify every payload checksum, and confirm the live target still matches its rollback snapshot.',
            'open' => 'Verify snapshot integrity', 'refresh' => 'Refresh integrity verification', 'hide' => 'Hide integrity verification',
            'idle' => 'Create the rollback snapshot before verifying its integrity.',
            'boundary' => 'Verification only. This workspace does not edit references, archive or remove files, delete the target, or execute the deletion plan.',
            'state' => 'Snapshot integrity', 'valid' => 'Integrity valid', 'execution_ready' => 'Post-snapshot execution ready',
            'manifest_valid' => 'Manifest valid', 'checksums_valid' => 'Checksums valid', 'source_matches' => 'Source matches snapshot',
            'claim_valid' => 'Claim valid', 'executor_valid' => 'Executor identity valid', 'fingerprint' => 'Integrity fingerprint',
            'snapshot' => 'Snapshot evidence', 'snapshot_id' => 'Snapshot ID', 'manifest_fp' => 'Manifest fingerprint',
            'destination' => 'Destination', 'manifest_path' => 'Manifest path', 'file_count' => 'Files', 'directory_count' => 'Directories',
            'total_bytes' => 'Total bytes', 'tree_checksum' => 'Tree checksum', 'actual_snapshot_files' => 'Verified snapshot files',
            'actual_source_files' => 'Verified source files', 'snapshot_checks' => 'Snapshot checksum checks', 'source_checks' => 'Live source checks',
            'path' => 'Path', 'expected' => 'Expected SHA-256', 'actual' => 'Actual SHA-256', 'passed' => 'Passed',
            'blockers' => 'Blocking reasons', 'diagnostics' => 'Diagnostics', 'none' => 'None',
            'status_error' => 'Snapshot integrity could not be verified safely.',
        ],
        'ja' => [
            'title' => 'スナップショット整合性と実行準備状態', 'description' => '不変マニフェストを再読込し、全チェックサムと現在対象の一致を検証します。',
            'open' => '整合性を検証', 'refresh' => '検証を更新', 'hide' => '検証を隠す', 'idle' => '整合性検証前にロールバックスナップショットを作成してください。',
            'boundary' => '検証専用です。参照編集、ファイルのアーカイブや削除、対象削除、計画実行は行いません。',
            'state' => 'スナップショット整合性', 'valid' => '整合性有効', 'execution_ready' => 'スナップショット後実行準備',
            'manifest_valid' => 'マニフェスト有効', 'checksums_valid' => 'チェックサム有効', 'source_matches' => '元データ一致',
            'claim_valid' => '取得有効', 'executor_valid' => '実行者本人確認', 'fingerprint' => '整合性フィンガープリント',
            'snapshot' => 'スナップショット証拠', 'snapshot_id' => 'スナップショットID', 'manifest_fp' => 'マニフェストフィンガープリント',
            'destination' => '保存先', 'manifest_path' => 'マニフェストパス', 'file_count' => 'ファイル', 'directory_count' => 'ディレクトリ',
            'total_bytes' => '総バイト', 'tree_checksum' => 'ツリーチェックサム', 'actual_snapshot_files' => '検証済みスナップショットファイル',
            'actual_source_files' => '検証済み元ファイル', 'snapshot_checks' => 'スナップショットチェック', 'source_checks' => '元データチェック',
            'path' => 'パス', 'expected' => '期待SHA-256', 'actual' => '実際SHA-256', 'passed' => '合格',
            'blockers' => '阻害理由', 'diagnostics' => '診断', 'none' => 'なし', 'status_error' => 'スナップショット整合性を安全に検証できませんでした。',
        ],
        'ne' => [
            'title' => 'Snapshot integrity र execution readiness', 'description' => 'Immutable manifest पुनः पढी payload checksums र live target को rollback snapshot सँग मिलान जाँच गर्नुहोस्।',
            'open' => 'Snapshot integrity जाँच', 'refresh' => 'Integrity पुनः जाँच', 'hide' => 'Integrity लुकाउनुहोस्',
            'idle' => 'Integrity जाँच्नुअघि rollback snapshot बनाउनुहोस्।',
            'boundary' => 'Verification मात्र। यसले reference edit, archive/remove, target delete वा deletion plan execute गर्दैन।',
            'state' => 'Snapshot integrity', 'valid' => 'Integrity valid', 'execution_ready' => 'Post-snapshot execution ready',
            'manifest_valid' => 'Manifest valid', 'checksums_valid' => 'Checksums valid', 'source_matches' => 'Source matches snapshot',
            'claim_valid' => 'Claim valid', 'executor_valid' => 'Executor identity valid', 'fingerprint' => 'Integrity fingerprint',
            'snapshot' => 'Snapshot evidence', 'snapshot_id' => 'Snapshot ID', 'manifest_fp' => 'Manifest fingerprint',
            'destination' => 'Destination', 'manifest_path' => 'Manifest path', 'file_count' => 'Files', 'directory_count' => 'Directories',
            'total_bytes' => 'Total bytes', 'tree_checksum' => 'Tree checksum', 'actual_snapshot_files' => 'Verified snapshot files',
            'actual_source_files' => 'Verified source files', 'snapshot_checks' => 'Snapshot checksum checks', 'source_checks' => 'Live source checks',
            'path' => 'Path', 'expected' => 'Expected SHA-256', 'actual' => 'Actual SHA-256', 'passed' => 'Passed',
            'blockers' => 'Blocking reasons', 'diagnostics' => 'Diagnostics', 'none' => 'None',
            'status_error' => 'Snapshot integrity सुरक्षित रूपमा जाँच्न सकिएन।',
        ],
    ];
    $set = isset($dictionary[$lang]) ? $dictionary[$lang] : $dictionary['en'];
    return (string)($set[$key] ?? $dictionary['en'][$key] ?? $key);
};

$snapshotIntegrityStatus = (string)($snapshotIntegrityWorkspace['status'] ?? 'idle');
$snapshotIntegrityEvidence = isset($snapshotIntegrityWorkspace['snapshot_evidence']) && is_array($snapshotIntegrityWorkspace['snapshot_evidence']) ? $snapshotIntegrityWorkspace['snapshot_evidence'] : [];
$snapshotIntegrityStorage = isset($snapshotIntegrityEvidence['storage']) && is_array($snapshotIntegrityEvidence['storage']) ? $snapshotIntegrityEvidence['storage'] : [];
$snapshotIntegritySummary = isset($snapshotIntegrityEvidence['summary']) && is_array($snapshotIntegrityEvidence['summary']) ? $snapshotIntegrityEvidence['summary'] : [];
$snapshotIntegritySnapshotChecks = isset($snapshotIntegrityWorkspace['snapshot_checks']) && is_array($snapshotIntegrityWorkspace['snapshot_checks']) ? $snapshotIntegrityWorkspace['snapshot_checks'] : [];
$snapshotIntegritySourceChecks = isset($snapshotIntegrityWorkspace['source_checks']) && is_array($snapshotIntegrityWorkspace['source_checks']) ? $snapshotIntegrityWorkspace['source_checks'] : [];
$snapshotIntegrityBlockers = isset($snapshotIntegrityWorkspace['blocking_reasons']) && is_array($snapshotIntegrityWorkspace['blocking_reasons']) ? $snapshotIntegrityWorkspace['blocking_reasons'] : [];
$snapshotIntegrityDiagnostics = isset($snapshotIntegrityWorkspace['diagnostics']) && is_array($snapshotIntegrityWorkspace['diagnostics']) ? $snapshotIntegrityWorkspace['diagnostics'] : [];
$snapshotIntegrityBaseUrl = '/apps/studio/tools/owner-structure-scan?owner=' . rawurlencode($impactSelectedOwnerKey)
    . '&scan=1&deletion_impact=1&deletion_plan=1&deletion_change_set=1&deletion_approval=1'
    . '&deletion_execution_readiness=1&deletion_execution_dry_run=1&deletion_execution_request=1'
    . '&deletion_executor_readiness=1&deletion_execution_claim=1&deletion_snapshot_readiness=1&deletion_snapshot_creation=1';
?>
<section class="oss-contract" aria-label="<?= e($snapshotIntegrityText('title')) ?>">
  <header class="oss-section-head">
    <div><h3><?= e($snapshotIntegrityText('title')) ?></h3><span><?= e($snapshotIntegrityText('description')) ?></span></div>
    <span><?= e($snapshotIntegrityText('boundary')) ?></span>
  </header>

  <form method="get" action="/apps/studio/tools/owner-structure-scan" class="oss-owner-form">
    <input type="hidden" name="owner" value="<?= e($impactSelectedOwnerKey) ?>">
    <input type="hidden" name="scan" value="1"><input type="hidden" name="deletion_impact" value="1"><input type="hidden" name="deletion_plan" value="1">
    <input type="hidden" name="deletion_change_set" value="1"><input type="hidden" name="deletion_approval" value="1">
    <input type="hidden" name="deletion_execution_readiness" value="1"><input type="hidden" name="deletion_execution_dry_run" value="1">
    <input type="hidden" name="deletion_execution_request" value="1"><input type="hidden" name="deletion_executor_readiness" value="1">
    <input type="hidden" name="deletion_execution_claim" value="1"><input type="hidden" name="deletion_snapshot_readiness" value="1">
    <input type="hidden" name="deletion_snapshot_creation" value="1">
    <button type="submit" name="deletion_snapshot_integrity" value="1"><?= e($snapshotIntegrityText($snapshotIntegrityRequested ? 'refresh' : 'open')) ?></button>
    <?php if ($snapshotIntegrityRequested): ?><a href="<?= e($snapshotIntegrityBaseUrl) ?>"><?= e($snapshotIntegrityText('hide')) ?></a><?php endif; ?>
  </form>

  <?php if ($snapshotIntegrityStatus === 'idle'): ?>
    <p class="oss-planner-note"><?= e($snapshotIntegrityText('idle')) ?></p>
  <?php elseif ($snapshotIntegrityStatus === 'error'): ?>
    <p class="oss-owner-error"><strong><?= e($snapshotIntegrityText('status_error')) ?></strong></p>
  <?php else: ?>
    <div class="oss-contract-summary">
      <div class="oss-summary-card"><dt><?= e($snapshotIntegrityText('state')) ?></dt><dd><?= e((string)$snapshotIntegrityWorkspace['snapshot_integrity']) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($snapshotIntegrityText('valid')) ?></dt><dd><?= e((string)$snapshotIntegrityWorkspace['snapshot_integrity_valid']) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($snapshotIntegrityText('execution_ready')) ?></dt><dd><?= e((string)$snapshotIntegrityWorkspace['post_snapshot_execution_ready']) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($snapshotIntegrityText('manifest_valid')) ?></dt><dd><?= e((string)$snapshotIntegrityWorkspace['manifest_valid']) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($snapshotIntegrityText('checksums_valid')) ?></dt><dd><?= e((string)$snapshotIntegrityWorkspace['checksums_valid']) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($snapshotIntegrityText('source_matches')) ?></dt><dd><?= e((string)$snapshotIntegrityWorkspace['source_matches_snapshot']) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($snapshotIntegrityText('claim_valid')) ?></dt><dd><?= e((string)$snapshotIntegrityWorkspace['claim_valid']) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($snapshotIntegrityText('executor_valid')) ?></dt><dd><?= e((string)$snapshotIntegrityWorkspace['executor_identity_valid']) ?></dd></div>
    </div>
    <dl class="oss-reference-facts"><div><dt><?= e($snapshotIntegrityText('fingerprint')) ?></dt><dd><code><?= e((string)$snapshotIntegrityWorkspace['snapshot_integrity_fingerprint']) ?></code></dd></div></dl>

    <details class="oss-contract-findings" open><summary><?= e($snapshotIntegrityText('snapshot')) ?></summary>
      <?php if ((string)($snapshotIntegrityEvidence['present'] ?? 'no') !== 'yes'): ?><p class="oss-empty-group"><?= e($snapshotIntegrityText('none')) ?></p><?php else: ?>
        <dl class="oss-reference-facts">
          <div><dt><?= e($snapshotIntegrityText('snapshot_id')) ?></dt><dd><code><?= e((string)($snapshotIntegrityEvidence['snapshot_id'] ?? '')) ?></code></dd></div>
          <div><dt><?= e($snapshotIntegrityText('manifest_fp')) ?></dt><dd><code><?= e((string)($snapshotIntegrityEvidence['manifest_fingerprint'] ?? '')) ?></code></dd></div>
          <div><dt><?= e($snapshotIntegrityText('destination')) ?></dt><dd><code><?= e((string)($snapshotIntegrityEvidence['destination_path'] ?? '')) ?></code></dd></div>
          <div><dt><?= e($snapshotIntegrityText('manifest_path')) ?></dt><dd><code><?= e((string)($snapshotIntegrityStorage['manifest_relative_path'] ?? '')) ?></code></dd></div>
          <div><dt><?= e($snapshotIntegrityText('file_count')) ?></dt><dd><?= e((string)($snapshotIntegritySummary['file_count'] ?? 0)) ?></dd></div>
          <div><dt><?= e($snapshotIntegrityText('directory_count')) ?></dt><dd><?= e((string)($snapshotIntegritySummary['directory_count'] ?? 0)) ?></dd></div>
          <div><dt><?= e($snapshotIntegrityText('total_bytes')) ?></dt><dd><?= e((string)($snapshotIntegritySummary['total_bytes'] ?? 0)) ?></dd></div>
          <div><dt><?= e($snapshotIntegrityText('tree_checksum')) ?></dt><dd><code><?= e((string)($snapshotIntegritySummary['tree_checksum_sha256'] ?? '')) ?></code></dd></div>
          <div><dt><?= e($snapshotIntegrityText('actual_snapshot_files')) ?></dt><dd><?= e((string)($snapshotIntegrityEvidence['actual_snapshot_file_count'] ?? 0)) ?></dd></div>
          <div><dt><?= e($snapshotIntegrityText('actual_source_files')) ?></dt><dd><?= e((string)($snapshotIntegrityEvidence['actual_source_file_count'] ?? 0)) ?></dd></div>
        </dl>
      <?php endif; ?>
    </details>

    <?php foreach ([[$snapshotIntegrityText('snapshot_checks'), $snapshotIntegritySnapshotChecks], [$snapshotIntegrityText('source_checks'), $snapshotIntegritySourceChecks]] as [$checkTitle, $checks]): ?>
      <details class="oss-contract-findings"><summary><?= e((string)$checkTitle) ?> (<?= e((string)count($checks)) ?>)</summary>
        <?php if ($checks === []): ?><p class="oss-empty-group"><?= e($snapshotIntegrityText('none')) ?></p><?php else: ?>
          <div class="oss-table-wrap"><table class="oss-table"><thead><tr><th><?= e($snapshotIntegrityText('path')) ?></th><th><?= e($snapshotIntegrityText('expected')) ?></th><th><?= e($snapshotIntegrityText('actual')) ?></th><th><?= e($snapshotIntegrityText('passed')) ?></th></tr></thead><tbody>
          <?php foreach ($checks as $check): if (!is_array($check)) { continue; } ?><tr><td><code><?= e((string)($check['path'] ?? '')) ?></code></td><td><code><?= e((string)($check['expected_sha256'] ?? '')) ?></code></td><td><code><?= e((string)($check['actual_sha256'] ?? '')) ?></code></td><td><?= e((string)($check['passed'] ?? 'no')) ?></td></tr><?php endforeach; ?>
          </tbody></table></div>
        <?php endif; ?>
      </details>
    <?php endforeach; ?>

    <details class="oss-contract-findings"<?= $snapshotIntegrityBlockers !== [] ? ' open' : '' ?>><summary><?= e($snapshotIntegrityText('blockers')) ?> (<?= e((string)count($snapshotIntegrityBlockers)) ?>)</summary>
      <?php if ($snapshotIntegrityBlockers === []): ?><p class="oss-empty-group"><?= e($snapshotIntegrityText('none')) ?></p><?php else: ?><ul class="oss-domain-list"><?php foreach ($snapshotIntegrityBlockers as $reason): ?><li><code><?= e((string)$reason) ?></code></li><?php endforeach; ?></ul><?php endif; ?>
    </details>
  <?php endif; ?>

  <?php if ($snapshotIntegrityDiagnostics !== []): ?>
    <details class="oss-contract-findings"><summary><?= e($snapshotIntegrityText('diagnostics')) ?> (<?= e((string)count($snapshotIntegrityDiagnostics)) ?>)</summary><ul class="oss-domain-list"><?php foreach ($snapshotIntegrityDiagnostics as $diagnostic): if (!is_array($diagnostic)) { continue; } ?><li><code><?= e((string)($diagnostic['code'] ?? '')) ?></code> <?= e((string)($diagnostic['message'] ?? '')) ?></li><?php endforeach; ?></ul></details>
  <?php endif; ?>
</section>

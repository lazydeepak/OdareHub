<?php
declare(strict_types=1);

use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureDeletionSnapshotWorkspaceService;

require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionSnapshotWorkspaceService.php';

$snapshotCreationRequested = (string)($_GET['deletion_snapshot_creation'] ?? '') === '1';
$snapshotCreationReadiness = isset($snapshotReadinessWorkspace['assessment']) && is_array($snapshotReadinessWorkspace['assessment'])
    ? $snapshotReadinessWorkspace['assessment']
    : null;
$snapshotCreationFlash = isset($_SESSION['studio_owner_structure_deletion_snapshot_result'])
    && is_array($_SESSION['studio_owner_structure_deletion_snapshot_result'])
        ? $_SESSION['studio_owner_structure_deletion_snapshot_result']
        : null;
unset($_SESSION['studio_owner_structure_deletion_snapshot_result']);

$snapshotCreationWorkspace = OwnerStructureDeletionSnapshotWorkspaceService::build(
    $impactOwners,
    $impactSelectedOwnerKey,
    $snapshotCreationRequested,
    $snapshotCreationReadiness,
    $snapshotCreationFlash,
    APP_ROOT
);
$snapshotCreationCsrf = isset($ownerStructureScanModel['csrf']) ? (string)$ownerStructureScanModel['csrf'] : '';

$snapshotCreationText = static function (string $key): string {
    $lang = function_exists('current_lang') ? (string)current_lang() : 'en';
    $dictionary = [
        'en' => [
            'title' => 'Snapshot creation',
            'description' => 'Copy the exact claimed target into deterministic Studio snapshot storage and write a checksum manifest.',
            'open' => 'Prepare snapshot creation', 'refresh' => 'Refresh snapshot workspace', 'hide' => 'Hide snapshot workspace',
            'idle' => 'Verify snapshot readiness before creating the rollback snapshot.',
            'boundary' => 'Snapshot only. This copies the target and writes an immutable manifest; it does not edit references, archive or remove the source, delete the target, or execute the deletion plan.',
            'source' => 'Source path', 'destination' => 'Snapshot destination', 'executor' => 'Current executor',
            'claim_id' => 'Claim ID', 'readiness_fp' => 'Snapshot-readiness fingerprint', 'reason' => 'Snapshot reason',
            'confirm' => 'I confirm creation of this immutable rollback snapshot for the exact claimed packet.',
            'submit' => 'Create rollback snapshot', 'blocked' => 'The snapshot cannot be created because readiness is not current or the destination already exists.',
            'result' => 'Snapshot result', 'created' => 'Snapshot created successfully.', 'failed' => 'Snapshot was not created.',
            'evidence' => 'Snapshot evidence', 'none' => 'No snapshot exists at this destination.', 'snapshot_id' => 'Snapshot ID',
            'created_at' => 'Created at', 'manifest_fp' => 'Manifest fingerprint', 'manifest_path' => 'Manifest path',
            'file_count' => 'Files', 'directory_count' => 'Directories', 'total_bytes' => 'Total bytes', 'tree_checksum' => 'Tree checksum',
            'diagnostics' => 'Diagnostics', 'status_error' => 'The snapshot workspace could not be prepared safely.',
        ],
        'ja' => [
            'title' => 'スナップショット作成', 'description' => '取得済み対象を決定的なStudio保存先へコピーし、チェックサムマニフェストを作成します。',
            'open' => '作成を準備', 'refresh' => '画面を更新', 'hide' => '画面を隠す', 'idle' => '作成前にスナップショット準備状態を検証してください。',
            'boundary' => 'スナップショットのみです。対象コピーと不変マニフェスト作成だけを行い、参照編集、元データ削除、対象削除、計画実行は行いません。',
            'source' => '元パス', 'destination' => '保存先', 'executor' => '現在の実行者', 'claim_id' => '取得ID', 'readiness_fp' => '準備状態フィンガープリント',
            'reason' => '作成理由', 'confirm' => 'この取得済みパケットの不変ロールバックスナップショット作成を確認します。', 'submit' => 'ロールバックスナップショットを作成',
            'blocked' => '準備状態が最新でないか、保存先が既に存在するため作成できません。', 'result' => '作成結果', 'created' => 'スナップショットを作成しました。', 'failed' => '作成されませんでした。',
            'evidence' => 'スナップショット証拠', 'none' => 'この保存先にはスナップショットがありません。', 'snapshot_id' => 'スナップショットID', 'created_at' => '作成日時',
            'manifest_fp' => 'マニフェストフィンガープリント', 'manifest_path' => 'マニフェストパス', 'file_count' => 'ファイル', 'directory_count' => 'ディレクトリ',
            'total_bytes' => '総バイト', 'tree_checksum' => 'ツリーチェックサム', 'diagnostics' => '診断', 'status_error' => '画面を安全に準備できませんでした。',
        ],
        'ne' => [
            'title' => 'Snapshot creation', 'description' => 'Exact claimed target लाई deterministic Studio storage मा copy गरी checksum manifest लेख्नुहोस्।',
            'open' => 'Snapshot creation तयार', 'refresh' => 'Snapshot workspace पुनःलोड', 'hide' => 'Snapshot workspace लुकाउनुहोस्',
            'idle' => 'Rollback snapshot बनाउनु अघि snapshot readiness जाँच गर्नुहोस्।',
            'boundary' => 'Snapshot मात्र। यसले target copy र immutable manifest लेख्छ; reference edit, source remove, target delete वा deletion plan execute गर्दैन।',
            'source' => 'Source path', 'destination' => 'Snapshot destination', 'executor' => 'Current executor', 'claim_id' => 'Claim ID', 'readiness_fp' => 'Snapshot-readiness fingerprint',
            'reason' => 'Snapshot reason', 'confirm' => 'Exact claimed packet को immutable rollback snapshot बनाउने पुष्टि गर्छु।', 'submit' => 'Rollback snapshot बनाउनुहोस्',
            'blocked' => 'Readiness current छैन वा destination पहिले नै छ, त्यसैले snapshot बनाउन सकिँदैन।', 'result' => 'Snapshot result', 'created' => 'Snapshot सफलतापूर्वक बन्यो।', 'failed' => 'Snapshot बनेन।',
            'evidence' => 'Snapshot evidence', 'none' => 'यो destination मा snapshot छैन।', 'snapshot_id' => 'Snapshot ID', 'created_at' => 'Created at', 'manifest_fp' => 'Manifest fingerprint',
            'manifest_path' => 'Manifest path', 'file_count' => 'Files', 'directory_count' => 'Directories', 'total_bytes' => 'Total bytes', 'tree_checksum' => 'Tree checksum',
            'diagnostics' => 'Diagnostics', 'status_error' => 'Snapshot workspace सुरक्षित रूपमा तयार भएन।',
        ],
    ];
    $set = isset($dictionary[$lang]) ? $dictionary[$lang] : $dictionary['en'];
    return (string)($set[$key] ?? $dictionary['en'][$key] ?? $key);
};

$snapshotCreationStatus = (string)($snapshotCreationWorkspace['status'] ?? 'idle');
$snapshotCreationSource = isset($snapshotCreationWorkspace['source']) && is_array($snapshotCreationWorkspace['source']) ? $snapshotCreationWorkspace['source'] : [];
$snapshotCreationContract = isset($snapshotCreationWorkspace['snapshot_contract']) && is_array($snapshotCreationWorkspace['snapshot_contract']) ? $snapshotCreationWorkspace['snapshot_contract'] : [];
$snapshotCreationActor = isset($snapshotCreationWorkspace['actor_evidence']) && is_array($snapshotCreationWorkspace['actor_evidence']) ? $snapshotCreationWorkspace['actor_evidence'] : [];
$snapshotCreationExisting = isset($snapshotCreationWorkspace['existing_snapshot']) && is_array($snapshotCreationWorkspace['existing_snapshot']) ? $snapshotCreationWorkspace['existing_snapshot'] : null;
$snapshotCreationResult = isset($snapshotCreationWorkspace['flash']) && is_array($snapshotCreationWorkspace['flash']) ? $snapshotCreationWorkspace['flash'] : null;
$snapshotCreationDiagnostics = isset($snapshotCreationWorkspace['diagnostics']) && is_array($snapshotCreationWorkspace['diagnostics']) ? $snapshotCreationWorkspace['diagnostics'] : [];
$snapshotCreationBaseUrl = '/apps/studio/tools/owner-structure-scan?owner=' . rawurlencode($impactSelectedOwnerKey)
    . '&scan=1&deletion_impact=1&deletion_plan=1&deletion_change_set=1&deletion_approval=1'
    . '&deletion_execution_readiness=1&deletion_execution_dry_run=1&deletion_execution_request=1'
    . '&deletion_executor_readiness=1&deletion_execution_claim=1&deletion_snapshot_readiness=1';
?>
<section class="oss-contract" aria-label="<?= e($snapshotCreationText('title')) ?>">
  <header class="oss-section-head">
    <div><h3><?= e($snapshotCreationText('title')) ?></h3><span><?= e($snapshotCreationText('description')) ?></span></div>
    <span><?= e($snapshotCreationText('boundary')) ?></span>
  </header>

  <form method="get" action="/apps/studio/tools/owner-structure-scan" class="oss-owner-form">
    <input type="hidden" name="owner" value="<?= e($impactSelectedOwnerKey) ?>">
    <input type="hidden" name="scan" value="1"><input type="hidden" name="deletion_impact" value="1"><input type="hidden" name="deletion_plan" value="1">
    <input type="hidden" name="deletion_change_set" value="1"><input type="hidden" name="deletion_approval" value="1">
    <input type="hidden" name="deletion_execution_readiness" value="1"><input type="hidden" name="deletion_execution_dry_run" value="1">
    <input type="hidden" name="deletion_execution_request" value="1"><input type="hidden" name="deletion_executor_readiness" value="1">
    <input type="hidden" name="deletion_execution_claim" value="1"><input type="hidden" name="deletion_snapshot_readiness" value="1">
    <button type="submit" name="deletion_snapshot_creation" value="1"><?= e($snapshotCreationText($snapshotCreationRequested ? 'refresh' : 'open')) ?></button>
    <?php if ($snapshotCreationRequested): ?><a href="<?= e($snapshotCreationBaseUrl) ?>"><?= e($snapshotCreationText('hide')) ?></a><?php endif; ?>
  </form>

  <?php if ($snapshotCreationStatus === 'idle'): ?>
    <p class="oss-planner-note"><?= e($snapshotCreationText('idle')) ?></p>
  <?php elseif ($snapshotCreationStatus === 'error'): ?>
    <p class="oss-owner-error"><strong><?= e($snapshotCreationText('status_error')) ?></strong></p>
  <?php else: ?>
    <dl class="oss-reference-facts">
      <div><dt><?= e($snapshotCreationText('source')) ?></dt><dd><code><?= e((string)($snapshotCreationContract['source_path'] ?? '')) ?></code></dd></div>
      <div><dt><?= e($snapshotCreationText('destination')) ?></dt><dd><code><?= e((string)($snapshotCreationContract['destination_path'] ?? '')) ?></code></dd></div>
      <div><dt><?= e($snapshotCreationText('executor')) ?></dt><dd><?= e((string)($snapshotCreationActor['display_name'] ?? $snapshotCreationActor['actor_id'] ?? '')) ?></dd></div>
      <div><dt><?= e($snapshotCreationText('claim_id')) ?></dt><dd><code><?= e((string)($snapshotCreationSource['claim_id'] ?? '')) ?></code></dd></div>
      <div><dt><?= e($snapshotCreationText('readiness_fp')) ?></dt><dd><code><?= e((string)($snapshotCreationSource['snapshot_readiness_fingerprint'] ?? '')) ?></code></dd></div>
    </dl>

    <?php if ($snapshotCreationResult !== null): ?>
      <section class="oss-section-notice"><strong><?= e($snapshotCreationText('result')) ?>:</strong>
        <?= e((string)($snapshotCreationResult['status'] ?? '') === 'recorded' ? $snapshotCreationText('created') : $snapshotCreationText('failed')) ?>
        <?php if (isset($snapshotCreationResult['snapshot_id'])): ?><code><?= e((string)$snapshotCreationResult['snapshot_id']) ?></code><?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if ((string)($snapshotCreationWorkspace['can_create'] ?? 'no') === 'yes'): ?>
      <form method="post" action="/apps/studio/tools/owner-structure-scan/deletion-snapshot-create" class="oss-contract">
        <input type="hidden" name="csrf" value="<?= e($snapshotCreationCsrf) ?>">
        <input type="hidden" name="owner" value="<?= e($impactSelectedOwnerKey) ?>">
        <input type="hidden" name="snapshot_readiness_fingerprint" value="<?= e((string)($snapshotCreationSource['snapshot_readiness_fingerprint'] ?? '')) ?>">
        <input type="hidden" name="claim_id" value="<?= e((string)($snapshotCreationSource['claim_id'] ?? '')) ?>">
        <input type="hidden" name="claim_fingerprint" value="<?= e((string)($snapshotCreationSource['claim_fingerprint'] ?? '')) ?>">
        <label style="display:grid;gap:.35rem;"><strong><?= e($snapshotCreationText('reason')) ?></strong><textarea name="reason" rows="3" maxlength="1000" required></textarea></label>
        <label style="display:flex;gap:.5rem;align-items:flex-start;margin-top:.75rem;"><input type="checkbox" name="snapshot_confirmed" value="yes" required><span><?= e($snapshotCreationText('confirm')) ?></span></label>
        <button type="submit" style="margin-top:.75rem;"><?= e($snapshotCreationText('submit')) ?></button>
      </form>
    <?php else: ?>
      <p class="oss-owner-error"><?= e($snapshotCreationText('blocked')) ?></p>
    <?php endif; ?>

    <details class="oss-contract-findings"<?= $snapshotCreationExisting !== null ? ' open' : '' ?>><summary><?= e($snapshotCreationText('evidence')) ?></summary>
      <?php if ($snapshotCreationExisting === null): ?><p class="oss-empty-group"><?= e($snapshotCreationText('none')) ?></p><?php else:
        $snapshotSummary = isset($snapshotCreationExisting['summary']) && is_array($snapshotCreationExisting['summary']) ? $snapshotCreationExisting['summary'] : [];
        $snapshotStorage = isset($snapshotCreationExisting['storage']) && is_array($snapshotCreationExisting['storage']) ? $snapshotCreationExisting['storage'] : [];
      ?>
        <dl class="oss-reference-facts">
          <div><dt><?= e($snapshotCreationText('snapshot_id')) ?></dt><dd><code><?= e((string)($snapshotCreationExisting['snapshot_id'] ?? '')) ?></code></dd></div>
          <div><dt><?= e($snapshotCreationText('created_at')) ?></dt><dd><?= e((string)($snapshotCreationExisting['created_at_utc'] ?? '')) ?></dd></div>
          <div><dt><?= e($snapshotCreationText('manifest_fp')) ?></dt><dd><code><?= e((string)($snapshotCreationExisting['manifest_fingerprint'] ?? '')) ?></code></dd></div>
          <div><dt><?= e($snapshotCreationText('manifest_path')) ?></dt><dd><code><?= e((string)($snapshotStorage['manifest_relative_path'] ?? '')) ?></code></dd></div>
          <div><dt><?= e($snapshotCreationText('file_count')) ?></dt><dd><?= e((string)($snapshotSummary['file_count'] ?? 0)) ?></dd></div>
          <div><dt><?= e($snapshotCreationText('directory_count')) ?></dt><dd><?= e((string)($snapshotSummary['directory_count'] ?? 0)) ?></dd></div>
          <div><dt><?= e($snapshotCreationText('total_bytes')) ?></dt><dd><?= e((string)($snapshotSummary['total_bytes'] ?? 0)) ?></dd></div>
          <div><dt><?= e($snapshotCreationText('tree_checksum')) ?></dt><dd><code><?= e((string)($snapshotSummary['tree_checksum_sha256'] ?? '')) ?></code></dd></div>
        </dl>
      <?php endif; ?>
    </details>
  <?php endif; ?>

  <?php if ($snapshotCreationDiagnostics !== []): ?>
    <details class="oss-contract-findings"><summary><?= e($snapshotCreationText('diagnostics')) ?> (<?= e((string)count($snapshotCreationDiagnostics)) ?>)</summary><ul class="oss-domain-list"><?php foreach ($snapshotCreationDiagnostics as $diagnostic): if (!is_array($diagnostic)) { continue; } ?><li><code><?= e((string)($diagnostic['code'] ?? '')) ?></code> <?= e((string)($diagnostic['message'] ?? '')) ?></li><?php endforeach; ?></ul></details>
  <?php endif; ?>
</section>

<?php
declare(strict_types=1);

use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureDeletionChangeSetWorkspaceService;

require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionChangeSetWorkspaceService.php';

$changeSetRequested = (string)($_GET['deletion_change_set'] ?? '') === '1';
$changeSetPlan = isset($planWorkspace['plan']) && is_array($planWorkspace['plan'])
    ? $planWorkspace['plan']
    : null;
$changeSetWorkspace = OwnerStructureDeletionChangeSetWorkspaceService::build(
    $impactOwners,
    $impactSelectedOwnerKey,
    $changeSetRequested,
    $changeSetPlan
);

$changeSetText = static function (string $key): string {
    $lang = function_exists('current_lang') ? (string)current_lang() : 'en';
    $dictionary = [
        'en' => [
            'title' => 'Deletion change set',
            'description' => 'Immutable review packet bound to the current deletion-plan fingerprint.',
            'build' => 'Build review packet',
            'refresh' => 'Refresh review packet',
            'hide' => 'Hide review packet',
            'idle' => 'Build the deletion plan first, then generate its review packet.',
            'readonly' => 'Review packet only. No approval recording, POST, apply, archive, delete, or execution endpoint is available.',
            'state' => 'Change-set state',
            'fingerprint' => 'Change-set fingerprint',
            'plan_fingerprint' => 'Source plan fingerprint',
            'approval_readiness' => 'Approval readiness',
            'operations' => 'Operations',
            'file_changes' => 'File changes',
            'archive_changes' => 'Archive changes',
            'delete_changes' => 'Delete changes',
            'verification_checks' => 'Verification checks',
            'blocking_changes' => 'Blocking changes',
            'review_changes' => 'Review changes',
            'cleanup_changes' => 'Cleanup changes',
            'approval_contract' => 'Approval contract',
            'required_confirmations' => 'Required confirmations',
            'snapshot_contract' => 'Snapshot contract',
            'verification_contract' => 'Verification contract',
            'proposed_file_changes' => 'Proposed file changes',
            'operation_manifest' => 'Exact operation manifest',
            'blocking_reasons' => 'Blocking reasons',
            'diagnostics' => 'Diagnostics',
            'order' => 'Order',
            'phase' => 'Phase',
            'operation' => 'Operation',
            'path' => 'Path',
            'owner' => 'Owner',
            'severity' => 'Severity',
            'required' => 'Required',
            'decision' => 'Decision required',
            'depends_on' => 'Depends on',
            'expected_evidence' => 'Expected evidence',
            'none' => 'None',
            'status_error' => 'The review packet could not be produced safely.',
        ],
        'ja' => [
            'title' => '削除変更セット',
            'description' => '現在の削除計画フィンガープリントに固定された不変のレビューパケットです。',
            'build' => 'レビューパケットを作成',
            'refresh' => 'レビューパケットを更新',
            'hide' => 'レビューパケットを隠す',
            'idle' => '最初に削除計画を作成し、その後レビューパケットを生成します。',
            'readonly' => 'レビュー専用です。承認記録、POST、適用、アーカイブ、削除、実行エンドポイントはありません。',
            'state' => '変更セット状態',
            'fingerprint' => '変更セットフィンガープリント',
            'plan_fingerprint' => '元計画フィンガープリント',
            'approval_readiness' => '承認準備状態',
            'operations' => '操作',
            'file_changes' => 'ファイル変更',
            'archive_changes' => 'アーカイブ変更',
            'delete_changes' => '削除変更',
            'verification_checks' => '検証チェック',
            'blocking_changes' => '阻害変更',
            'review_changes' => '確認変更',
            'cleanup_changes' => 'クリーンアップ変更',
            'approval_contract' => '承認契約',
            'required_confirmations' => '必須確認',
            'snapshot_contract' => 'スナップショット契約',
            'verification_contract' => '検証契約',
            'proposed_file_changes' => '提案ファイル変更',
            'operation_manifest' => '正確な操作マニフェスト',
            'blocking_reasons' => '阻害理由',
            'diagnostics' => '診断',
            'order' => '順序',
            'phase' => 'フェーズ',
            'operation' => '操作',
            'path' => 'パス',
            'owner' => '所有者',
            'severity' => '重要度',
            'required' => '必須',
            'decision' => '判断必須',
            'depends_on' => '依存先',
            'expected_evidence' => '期待証拠',
            'none' => 'なし',
            'status_error' => 'レビューパケットを安全に生成できませんでした。',
        ],
        'ne' => [
            'title' => 'मेटाउने change set',
            'description' => 'हालको deletion-plan fingerprint सँग बाँधिएको immutable review packet।',
            'build' => 'Review packet बनाउनुहोस्',
            'refresh' => 'Review packet पुनः बनाउनुहोस्',
            'hide' => 'Review packet लुकाउनुहोस्',
            'idle' => 'पहिले deletion plan बनाउनुहोस्, त्यसपछि review packet तयार गर्नुहोस्।',
            'readonly' => 'Review मात्र। approval recording, POST, apply, archive, delete वा execution endpoint छैन।',
            'state' => 'Change-set अवस्था',
            'fingerprint' => 'Change-set fingerprint',
            'plan_fingerprint' => 'Source plan fingerprint',
            'approval_readiness' => 'Approval readiness',
            'operations' => 'Operations',
            'file_changes' => 'File changes',
            'archive_changes' => 'Archive changes',
            'delete_changes' => 'Delete changes',
            'verification_checks' => 'Verification checks',
            'blocking_changes' => 'Blocking changes',
            'review_changes' => 'Review changes',
            'cleanup_changes' => 'Cleanup changes',
            'approval_contract' => 'Approval contract',
            'required_confirmations' => 'Required confirmations',
            'snapshot_contract' => 'Snapshot contract',
            'verification_contract' => 'Verification contract',
            'proposed_file_changes' => 'Proposed file changes',
            'operation_manifest' => 'Exact operation manifest',
            'blocking_reasons' => 'Blocking reasons',
            'diagnostics' => 'Diagnostics',
            'order' => 'Order',
            'phase' => 'Phase',
            'operation' => 'Operation',
            'path' => 'Path',
            'owner' => 'Owner',
            'severity' => 'Severity',
            'required' => 'Required',
            'decision' => 'Decision required',
            'depends_on' => 'Depends on',
            'expected_evidence' => 'Expected evidence',
            'none' => 'None',
            'status_error' => 'Review packet सुरक्षित रूपमा तयार भएन।',
        ],
    ];
    $set = isset($dictionary[$lang]) ? $dictionary[$lang] : $dictionary['en'];
    return (string)($set[$key] ?? $dictionary['en'][$key] ?? $key);
};

$changeSetStatus = (string)($changeSetWorkspace['status'] ?? 'idle');
$changeSetSummary = isset($changeSetWorkspace['summary']) && is_array($changeSetWorkspace['summary']) ? $changeSetWorkspace['summary'] : [];
$changeSetSourcePlan = isset($changeSetWorkspace['source_plan']) && is_array($changeSetWorkspace['source_plan']) ? $changeSetWorkspace['source_plan'] : [];
$changeSetApproval = isset($changeSetWorkspace['approval_contract']) && is_array($changeSetWorkspace['approval_contract']) ? $changeSetWorkspace['approval_contract'] : [];
$changeSetSnapshot = isset($changeSetWorkspace['snapshot_contract']) && is_array($changeSetWorkspace['snapshot_contract']) ? $changeSetWorkspace['snapshot_contract'] : [];
$changeSetVerification = isset($changeSetWorkspace['verification_contract']) && is_array($changeSetWorkspace['verification_contract']) ? $changeSetWorkspace['verification_contract'] : [];
$changeSetProposed = isset($changeSetWorkspace['proposed_changes']) && is_array($changeSetWorkspace['proposed_changes']) ? $changeSetWorkspace['proposed_changes'] : [];
$changeSetFileChanges = isset($changeSetProposed['file_changes']) && is_array($changeSetProposed['file_changes']) ? $changeSetProposed['file_changes'] : [];
$changeSetArchiveChanges = isset($changeSetProposed['archive_changes']) && is_array($changeSetProposed['archive_changes']) ? $changeSetProposed['archive_changes'] : [];
$changeSetOperations = isset($changeSetWorkspace['operation_manifest']) && is_array($changeSetWorkspace['operation_manifest']) ? $changeSetWorkspace['operation_manifest'] : [];
$changeSetBlockers = isset($changeSetWorkspace['blocking_reasons']) && is_array($changeSetWorkspace['blocking_reasons']) ? $changeSetWorkspace['blocking_reasons'] : [];
$changeSetDiagnostics = isset($changeSetWorkspace['diagnostics']) && is_array($changeSetWorkspace['diagnostics']) ? $changeSetWorkspace['diagnostics'] : [];
$changeSetChecks = isset($changeSetVerification['checks']) && is_array($changeSetVerification['checks']) ? $changeSetVerification['checks'] : [];
$changeSetConfirmations = isset($changeSetApproval['required_confirmations']) && is_array($changeSetApproval['required_confirmations']) ? $changeSetApproval['required_confirmations'] : [];
$changeSetBaseUrl = '/apps/studio/tools/owner-structure-scan?owner=' . rawurlencode($impactSelectedOwnerKey) . '&scan=1&deletion_impact=1&deletion_plan=1';
?>
<section class="oss-contract" aria-label="<?= e($changeSetText('title')) ?>">
  <header class="oss-section-head">
    <div>
      <h3><?= e($changeSetText('title')) ?></h3>
      <span><?= e($changeSetText('description')) ?></span>
    </div>
    <span><?= e($changeSetText('readonly')) ?></span>
  </header>

  <form method="get" action="/apps/studio/tools/owner-structure-scan" class="oss-owner-form">
    <input type="hidden" name="owner" value="<?= e($impactSelectedOwnerKey) ?>">
    <input type="hidden" name="scan" value="1">
    <input type="hidden" name="deletion_impact" value="1">
    <input type="hidden" name="deletion_plan" value="1">
    <button type="submit" name="deletion_change_set" value="1"><?= e($changeSetText($changeSetRequested ? 'refresh' : 'build')) ?></button>
    <?php if ($changeSetRequested): ?><a href="<?= e($changeSetBaseUrl) ?>"><?= e($changeSetText('hide')) ?></a><?php endif; ?>
  </form>

  <?php if ($changeSetStatus === 'idle'): ?>
    <p class="oss-planner-note"><?= e($changeSetText('idle')) ?></p>
  <?php elseif ($changeSetStatus === 'error'): ?>
    <p class="oss-owner-error"><strong><?= e($changeSetText('status_error')) ?></strong></p>
  <?php else: ?>
    <div class="oss-contract-summary">
      <div class="oss-summary-card"><dt><?= e($changeSetText('state')) ?></dt><dd><?= e((string)($changeSetWorkspace['change_set_state'] ?? 'unknown')) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($changeSetText('approval_readiness')) ?></dt><dd><?= e((string)($changeSetApproval['approval_readiness'] ?? 'blocked')) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($changeSetText('operations')) ?></dt><dd><?= e((string)($changeSetSummary['operation_count'] ?? 0)) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($changeSetText('file_changes')) ?></dt><dd><?= e((string)($changeSetSummary['file_change_count'] ?? 0)) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($changeSetText('archive_changes')) ?></dt><dd><?= e((string)($changeSetSummary['archive_change_count'] ?? 0)) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($changeSetText('delete_changes')) ?></dt><dd><?= e((string)($changeSetSummary['delete_change_count'] ?? 0)) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($changeSetText('verification_checks')) ?></dt><dd><?= e((string)($changeSetSummary['verification_check_count'] ?? 0)) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($changeSetText('blocking_changes')) ?></dt><dd><?= e((string)($changeSetSummary['blocking_change_count'] ?? 0)) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($changeSetText('review_changes')) ?></dt><dd><?= e((string)($changeSetSummary['review_change_count'] ?? 0)) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($changeSetText('cleanup_changes')) ?></dt><dd><?= e((string)($changeSetSummary['cleanup_change_count'] ?? 0)) ?></dd></div>
    </div>

    <dl class="oss-reference-facts">
      <div><dt><?= e($changeSetText('fingerprint')) ?></dt><dd><code><?= e((string)($changeSetWorkspace['change_set_fingerprint'] ?? '')) ?></code></dd></div>
      <div><dt><?= e($changeSetText('plan_fingerprint')) ?></dt><dd><code><?= e((string)($changeSetSourcePlan['fingerprint'] ?? '')) ?></code></dd></div>
    </dl>

    <details class="oss-contract-findings" open>
      <summary><?= e($changeSetText('approval_contract')) ?></summary>
      <p><strong><?= e($changeSetText('approval_readiness')) ?>:</strong> <?= e((string)($changeSetApproval['approval_readiness'] ?? 'blocked')) ?></p>
      <h4><?= e($changeSetText('required_confirmations')) ?></h4>
      <?php if ($changeSetConfirmations === []): ?><p class="oss-empty-group"><?= e($changeSetText('none')) ?></p><?php else: ?>
        <ul class="oss-domain-list"><?php foreach ($changeSetConfirmations as $confirmation): ?><li><code><?= e((string)$confirmation) ?></code></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </details>

    <details class="oss-contract-findings" open>
      <summary><?= e($changeSetText('proposed_file_changes')) ?> (<?= e((string)count($changeSetFileChanges)) ?>)</summary>
      <?php if ($changeSetFileChanges === []): ?><p class="oss-empty-group"><?= e($changeSetText('none')) ?></p><?php else: ?>
        <div class="oss-table-wrap"><table class="oss-finding-table"><thead><tr><th><?= e($changeSetText('operation')) ?></th><th><?= e($changeSetText('owner')) ?></th><th><?= e($changeSetText('path')) ?></th><th><?= e($changeSetText('severity')) ?></th><th><?= e($changeSetText('required')) ?></th><th><?= e($changeSetText('decision')) ?></th></tr></thead><tbody>
          <?php foreach ($changeSetFileChanges as $change): if (!is_array($change)) { continue; } ?><tr>
            <td><code><?= e((string)($change['change_type'] ?? '')) ?></code></td><td><code><?= e((string)($change['owner_key'] ?? '@repository')) ?></code></td><td><code><?= e((string)($change['path'] ?? '')) ?></code></td><td><?= e((string)($change['severity'] ?? '')) ?></td><td><?= e((string)($change['required'] ?? 'no')) ?></td><td><?= e((string)($change['decision_required'] ?? 'no')) ?></td>
          </tr><?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </details>

    <details class="oss-contract-findings">
      <summary><?= e($changeSetText('snapshot_contract')) ?> (<?= e((string)count($changeSetArchiveChanges)) ?>)</summary>
      <p><strong><?= e($changeSetText('required')) ?>:</strong> <?= e((string)($changeSetSnapshot['required'] ?? 'yes')) ?></p>
      <ul class="oss-domain-list"><?php foreach ($changeSetArchiveChanges as $change): if (!is_array($change)) { continue; } ?><li><code><?= e((string)($change['path'] ?? '')) ?></code> — <?= e((string)($change['reason'] ?? '')) ?></li><?php endforeach; ?></ul>
    </details>

    <details class="oss-contract-findings">
      <summary><?= e($changeSetText('verification_contract')) ?> (<?= e((string)count($changeSetChecks)) ?>)</summary>
      <?php if ($changeSetChecks === []): ?><p class="oss-empty-group"><?= e($changeSetText('none')) ?></p><?php else: ?>
        <div class="oss-table-wrap"><table class="oss-finding-table"><thead><tr><th><?= e($changeSetText('operation')) ?></th><th><?= e($changeSetText('path')) ?></th><th><?= e($changeSetText('expected_evidence')) ?></th></tr></thead><tbody>
          <?php foreach ($changeSetChecks as $check): if (!is_array($check)) { continue; } ?><tr><td><code><?= e((string)($check['check_type'] ?? '')) ?></code></td><td><code><?= e((string)($check['path'] ?? '')) ?></code></td><td><code><?= e((string)($check['expected_evidence'] ?? '')) ?></code></td></tr><?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </details>

    <details class="oss-contract-findings">
      <summary><?= e($changeSetText('operation_manifest')) ?> (<?= e((string)count($changeSetOperations)) ?>)</summary>
      <div class="oss-table-wrap"><table class="oss-plan-table"><thead><tr><th><?= e($changeSetText('order')) ?></th><th><?= e($changeSetText('phase')) ?></th><th><?= e($changeSetText('operation')) ?></th><th><?= e($changeSetText('owner')) ?></th><th><?= e($changeSetText('path')) ?></th><th><?= e($changeSetText('depends_on')) ?></th></tr></thead><tbody>
        <?php foreach ($changeSetOperations as $operation): if (!is_array($operation)) { continue; } ?><tr><td><?= e((string)($operation['sequence'] ?? '')) ?></td><td><?= e((string)($operation['phase'] ?? '')) ?></td><td><code><?= e((string)($operation['operation_type'] ?? '')) ?></code></td><td><code><?= e((string)($operation['owner_key'] ?? '')) ?></code></td><td><code><?= e((string)($operation['path'] ?? '')) ?></code></td><td><?= e(implode(', ', array_map('strval', is_array($operation['depends_on'] ?? null) ? $operation['depends_on'] : []))) ?></td></tr><?php endforeach; ?>
      </tbody></table></div>
    </details>

    <details class="oss-contract-findings"><summary><?= e($changeSetText('blocking_reasons')) ?> (<?= e((string)count($changeSetBlockers)) ?>)</summary><ul class="oss-domain-list"><?php foreach ($changeSetBlockers as $reason): ?><li><code><?= e((string)$reason) ?></code></li><?php endforeach; ?></ul></details>
  <?php endif; ?>

  <?php if ($changeSetDiagnostics !== []): ?><details class="oss-contract-findings"><summary><?= e($changeSetText('diagnostics')) ?> (<?= e((string)count($changeSetDiagnostics)) ?>)</summary><ul class="oss-domain-list"><?php foreach ($changeSetDiagnostics as $diagnostic): if (!is_array($diagnostic)) { continue; } ?><li><code><?= e((string)($diagnostic['code'] ?? '')) ?></code> <?= e((string)($diagnostic['message'] ?? '')) ?></li><?php endforeach; ?></ul></details><?php endif; ?>
</section>

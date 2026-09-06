<?php
declare(strict_types=1);

use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureDeletionPlanWorkspaceService;

require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionPlanWorkspaceService.php';

$planRequested = (string)($_GET['deletion_plan'] ?? '') === '1';
$planImpactAssessment = isset($impactWorkspace['assessment']) && is_array($impactWorkspace['assessment'])
    ? $impactWorkspace['assessment']
    : null;
$planWorkspace = OwnerStructureDeletionPlanWorkspaceService::build(
    $impactOwners,
    $impactSelectedOwnerKey,
    $planRequested,
    $planImpactAssessment
);

$planText = static function (string $key): string {
    $lang = function_exists('current_lang') ? (string)current_lang() : 'en';
    $dictionary = [
        'en' => [
            'title' => 'Deletion plan',
            'description' => 'Deterministic read-only removal sequence derived from the current deletion-impact evidence.',
            'build' => 'Build deletion plan',
            'refresh' => 'Refresh plan',
            'hide' => 'Hide plan',
            'idle' => 'Build a plan after assessing deletion impact.',
            'readonly' => 'Planning only. No delete, apply, POST, filesystem write, or execution endpoint is available.',
            'state' => 'Plan state',
            'fingerprint' => 'Plan fingerprint',
            'operations' => 'Operations',
            'reference_changes' => 'Reference changes',
            'blocking_changes' => 'Blocking changes',
            'review_changes' => 'Review changes',
            'cleanup_changes' => 'Cleanup changes',
            'archive_items' => 'Archive items',
            'verification_checks' => 'Verification checks',
            'blocking_reasons' => 'Blocking reasons',
            'archive_scope' => 'Archive scope',
            'ordered_operations' => 'Ordered operations',
            'post_checks' => 'Post-deletion checks',
            'order' => 'Order',
            'phase' => 'Phase',
            'operation' => 'Operation',
            'path' => 'Path',
            'owner' => 'Owner',
            'severity' => 'Severity',
            'state_label' => 'Execution state',
            'required' => 'Required',
            'depends_on' => 'Depends on',
            'reason' => 'Reason',
            'diagnostics' => 'Diagnostics',
            'none' => 'None',
            'status_error' => 'The plan could not be produced safely.',
        ],
        'ja' => [
            'title' => '削除計画',
            'description' => '現在の削除影響証拠から生成された決定論的な読み取り専用の削除手順です。',
            'build' => '削除計画を作成',
            'refresh' => '計画を更新',
            'hide' => '計画を隠す',
            'idle' => '削除影響を評価した後に計画を作成します。',
            'readonly' => '計画専用です。削除、適用、POST、ファイル書き込み、実行エンドポイントはありません。',
            'state' => '計画状態',
            'fingerprint' => '計画フィンガープリント',
            'operations' => '操作',
            'reference_changes' => '参照変更',
            'blocking_changes' => '阻害変更',
            'review_changes' => '確認変更',
            'cleanup_changes' => 'クリーンアップ変更',
            'archive_items' => 'アーカイブ項目',
            'verification_checks' => '検証チェック',
            'blocking_reasons' => '阻害理由',
            'archive_scope' => 'アーカイブ範囲',
            'ordered_operations' => '順序付き操作',
            'post_checks' => '削除後チェック',
            'order' => '順序',
            'phase' => 'フェーズ',
            'operation' => '操作',
            'path' => 'パス',
            'owner' => '所有者',
            'severity' => '重要度',
            'state_label' => '実行状態',
            'required' => '必須',
            'depends_on' => '依存',
            'reason' => '理由',
            'diagnostics' => '診断',
            'none' => 'なし',
            'status_error' => '計画を安全に作成できませんでした。',
        ],
        'ne' => [
            'title' => 'मेटाउने योजना',
            'description' => 'हालको deletion-impact प्रमाणबाट बनेको deterministic read-only removal sequence।',
            'build' => 'मेटाउने योजना बनाउनुहोस्',
            'refresh' => 'योजना पुनः बनाउनुहोस्',
            'hide' => 'योजना लुकाउनुहोस्',
            'idle' => 'Deletion impact जाँच गरेपछि योजना बनाउनुहोस्।',
            'readonly' => 'यो योजना मात्र हो। Delete, apply, POST, filesystem write वा execution endpoint छैन।',
            'state' => 'Plan state',
            'fingerprint' => 'Plan fingerprint',
            'operations' => 'Operations',
            'reference_changes' => 'Reference changes',
            'blocking_changes' => 'Blocking changes',
            'review_changes' => 'Review changes',
            'cleanup_changes' => 'Cleanup changes',
            'archive_items' => 'Archive items',
            'verification_checks' => 'Verification checks',
            'blocking_reasons' => 'Blocking reasons',
            'archive_scope' => 'Archive scope',
            'ordered_operations' => 'Ordered operations',
            'post_checks' => 'Post-deletion checks',
            'order' => 'Order',
            'phase' => 'Phase',
            'operation' => 'Operation',
            'path' => 'Path',
            'owner' => 'Owner',
            'severity' => 'Severity',
            'state_label' => 'Execution state',
            'required' => 'Required',
            'depends_on' => 'Depends on',
            'reason' => 'Reason',
            'diagnostics' => 'Diagnostics',
            'none' => 'None',
            'status_error' => 'योजना सुरक्षित रूपमा बनाउन सकिएन।',
        ],
    ];
    $set = isset($dictionary[$lang]) ? $dictionary[$lang] : $dictionary['en'];
    return (string)($set[$key] ?? $dictionary['en'][$key] ?? $key);
};

$planStatus = (string)($planWorkspace['status'] ?? 'idle');
$planSummary = isset($planWorkspace['summary']) && is_array($planWorkspace['summary']) ? $planWorkspace['summary'] : [];
$planOperations = isset($planWorkspace['operations']) && is_array($planWorkspace['operations']) ? $planWorkspace['operations'] : [];
$planArchiveScope = isset($planWorkspace['archive_scope']) && is_array($planWorkspace['archive_scope']) ? $planWorkspace['archive_scope'] : [];
$planChecks = isset($planWorkspace['post_deletion_checks']) && is_array($planWorkspace['post_deletion_checks']) ? $planWorkspace['post_deletion_checks'] : [];
$planBlockingReasons = isset($planWorkspace['blocking_reasons']) && is_array($planWorkspace['blocking_reasons']) ? $planWorkspace['blocking_reasons'] : [];
$planDiagnostics = isset($planWorkspace['diagnostics']) && is_array($planWorkspace['diagnostics']) ? $planWorkspace['diagnostics'] : [];
$planBaseUrl = $impactBaseUrl . '&deletion_impact=1';
?>
<section class="oss-contract" aria-label="<?= e($planText('title')) ?>">
  <header class="oss-section-head">
    <div><h3><?= e($planText('title')) ?></h3><span><?= e($planText('description')) ?></span></div>
    <span><?= e($planText('readonly')) ?></span>
  </header>

  <form method="get" action="/apps/studio/tools/owner-structure-scan" class="oss-owner-form">
    <input type="hidden" name="owner" value="<?= e($impactSelectedOwnerKey) ?>">
    <input type="hidden" name="scan" value="1">
    <input type="hidden" name="deletion_impact" value="1">
    <button type="submit" name="deletion_plan" value="1"><?= e($planText($planRequested ? 'refresh' : 'build')) ?></button>
    <?php if ($planRequested): ?><a href="<?= e($planBaseUrl) ?>"><?= e($planText('hide')) ?></a><?php endif; ?>
  </form>

  <?php if ($planStatus === 'idle'): ?>
    <p class="oss-planner-note"><?= e($planText('idle')) ?></p>
  <?php elseif ($planStatus === 'error'): ?>
    <p class="oss-owner-error"><strong><?= e($planText('status_error')) ?></strong></p>
  <?php else: ?>
    <div class="oss-contract-summary">
      <div class="oss-summary-card"><dt><?= e($planText('state')) ?></dt><dd><?= e((string)($planWorkspace['plan_state'] ?? 'unknown')) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($planText('fingerprint')) ?></dt><dd><code><?= e((string)($planWorkspace['plan_fingerprint'] ?? '')) ?></code></dd></div>
      <div class="oss-summary-card"><dt><?= e($planText('operations')) ?></dt><dd><?= e((string)($planSummary['operation_count'] ?? 0)) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($planText('reference_changes')) ?></dt><dd><?= e((string)($planSummary['reference_change_count'] ?? 0)) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($planText('blocking_changes')) ?></dt><dd><?= e((string)($planSummary['blocking_reference_changes'] ?? 0)) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($planText('review_changes')) ?></dt><dd><?= e((string)($planSummary['review_reference_changes'] ?? 0)) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($planText('cleanup_changes')) ?></dt><dd><?= e((string)($planSummary['cleanup_reference_changes'] ?? 0)) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($planText('archive_items')) ?></dt><dd><?= e((string)($planSummary['archive_item_count'] ?? 0)) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($planText('verification_checks')) ?></dt><dd><?= e((string)($planSummary['verification_count'] ?? 0)) ?></dd></div>
    </div>

    <details class="oss-contract-findings" open>
      <summary><?= e($planText('blocking_reasons')) ?> (<?= e((string)count($planBlockingReasons)) ?>)</summary>
      <?php if ($planBlockingReasons === []): ?><p class="oss-empty-group"><?= e($planText('none')) ?></p><?php else: ?>
        <ul class="oss-domain-list"><?php foreach ($planBlockingReasons as $reason): ?><li><code><?= e((string)$reason) ?></code></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </details>

    <details class="oss-contract-findings" open>
      <summary><?= e($planText('archive_scope')) ?> (<?= e((string)count($planArchiveScope)) ?>)</summary>
      <div class="oss-table-wrap"><table class="oss-finding-table"><thead><tr><th><?= e($planText('owner')) ?></th><th><?= e($planText('path')) ?></th><th><?= e($planText('required')) ?></th><th><?= e($planText('reason')) ?></th></tr></thead><tbody>
      <?php foreach ($planArchiveScope as $archiveItem): ?><?php if (!is_array($archiveItem)) { continue; } ?><tr>
        <td><code><?= e((string)($archiveItem['owner_key'] ?? '')) ?></code></td><td><code><?= e((string)($archiveItem['path'] ?? '')) ?></code></td><td><?= e((string)($archiveItem['required'] ?? 'yes')) ?></td><td><?= e((string)($archiveItem['reason'] ?? '')) ?></td>
      </tr><?php endforeach; ?>
      </tbody></table></div>
    </details>

    <details class="oss-contract-findings" open>
      <summary><?= e($planText('ordered_operations')) ?> (<?= e((string)count($planOperations)) ?>)</summary>
      <div class="oss-table-wrap"><table class="oss-plan-table"><thead><tr><th><?= e($planText('order')) ?></th><th><?= e($planText('phase')) ?></th><th><?= e($planText('operation')) ?></th><th><?= e($planText('path')) ?></th><th><?= e($planText('owner')) ?></th><th><?= e($planText('severity')) ?></th><th><?= e($planText('state_label')) ?></th><th><?= e($planText('depends_on')) ?></th><th><?= e($planText('reason')) ?></th></tr></thead><tbody>
      <?php foreach ($planOperations as $index => $operation): ?><?php if (!is_array($operation)) { continue; } ?><tr>
        <td><?= e((string)($index + 1)) ?></td><td><?= e((string)($operation['phase'] ?? '')) ?></td><td><code><?= e((string)($operation['operation_type'] ?? '')) ?></code></td><td><code><?= e((string)($operation['path'] ?? '')) ?></code></td><td><code><?= e((string)($operation['owner_key'] ?? '@repository')) ?></code></td><td><?= e((string)($operation['severity'] ?? '')) ?></td><td><?= e((string)($operation['execution_state'] ?? '')) ?></td><td><?= e(implode(', ', array_map('strval', is_array($operation['depends_on'] ?? null) ? $operation['depends_on'] : []))) ?></td><td><?= e((string)($operation['reason'] ?? '')) ?></td>
      </tr><?php endforeach; ?>
      </tbody></table></div>
    </details>

    <details class="oss-contract-findings">
      <summary><?= e($planText('post_checks')) ?> (<?= e((string)count($planChecks)) ?>)</summary>
      <div class="oss-table-wrap"><table class="oss-finding-table"><thead><tr><th><?= e($planText('operation')) ?></th><th><?= e($planText('owner')) ?></th><th><?= e($planText('path')) ?></th><th><?= e($planText('required')) ?></th></tr></thead><tbody>
      <?php foreach ($planChecks as $check): ?><?php if (!is_array($check)) { continue; } ?><tr><td><code><?= e((string)($check['check_type'] ?? '')) ?></code></td><td><code><?= e((string)($check['owner_key'] ?? '')) ?></code></td><td><code><?= e((string)($check['path'] ?? '')) ?></code></td><td><?= e((string)($check['required'] ?? 'yes')) ?></td></tr><?php endforeach; ?>
      </tbody></table></div>
    </details>
  <?php endif; ?>

  <?php if ($planDiagnostics !== []): ?>
    <details class="oss-contract-findings"><summary><?= e($planText('diagnostics')) ?> (<?= e((string)count($planDiagnostics)) ?>)</summary><ul class="oss-domain-list">
    <?php foreach ($planDiagnostics as $diagnostic): ?><?php if (!is_array($diagnostic)) { continue; } ?><li><code><?= e((string)($diagnostic['code'] ?? '')) ?></code> <?= e((string)($diagnostic['message'] ?? '')) ?></li><?php endforeach; ?>
    </ul></details>
  <?php endif; ?>
</section>

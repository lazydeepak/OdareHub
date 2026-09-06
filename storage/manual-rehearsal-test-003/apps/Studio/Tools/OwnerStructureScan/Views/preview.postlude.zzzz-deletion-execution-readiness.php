<?php
declare(strict_types=1);

use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureDeletionExecutionReadinessWorkspaceService;

require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionExecutionReadinessWorkspaceService.php';

$executionReadinessRequested = (string)($_GET['deletion_execution_readiness'] ?? '') === '1';
$executionReadinessChangeSet = isset($changeSetWorkspace['change_set']) && is_array($changeSetWorkspace['change_set'])
    ? $changeSetWorkspace['change_set']
    : null;
$executionReadinessWorkspace = OwnerStructureDeletionExecutionReadinessWorkspaceService::build(
    $impactOwners,
    $impactSelectedOwnerKey,
    $executionReadinessRequested,
    $executionReadinessChangeSet
);

$executionReadinessText = static function (string $key): string {
    $lang = function_exists('current_lang') ? (string)current_lang() : 'en';
    $dictionary = [
        'en' => [
            'title' => 'Approval validity and execution readiness',
            'description' => 'Recompute the current packet and verify the latest immutable approval evidence.',
            'assess' => 'Verify execution readiness',
            'refresh' => 'Refresh readiness',
            'hide' => 'Hide readiness',
            'idle' => 'Build the deletion change set before verifying execution readiness.',
            'boundary' => 'Verification only. This workspace cannot apply, archive, delete, or execute the plan.',
            'state' => 'Execution readiness',
            'eligible' => 'Execution eligible',
            'approval_valid' => 'Approval valid',
            'fingerprint' => 'Readiness fingerprint',
            'change_set' => 'Current change set',
            'plan' => 'Current plan',
            'approval' => 'Approval evidence',
            'decision' => 'Decision',
            'record_id' => 'Record ID',
            'recorded_at' => 'Recorded at',
            'approver' => 'Approver',
            'exact_match' => 'Exact packet match',
            'confirmations' => 'Confirmations satisfied',
            'stored_at' => 'Stored at',
            'blockers' => 'Blocking reasons',
            'diagnostics' => 'Diagnostics',
            'none' => 'None',
            'status_error' => 'Execution readiness could not be verified safely.',
        ],
        'ja' => [
            'title' => '承認有効性と実行準備状態',
            'description' => '現在のパケットを再計算し、最新の不変承認証拠を検証します。',
            'assess' => '実行準備状態を検証',
            'refresh' => '準備状態を更新',
            'hide' => '準備状態を隠す',
            'idle' => '実行準備状態を検証する前に削除変更セットを作成してください。',
            'boundary' => '検証専用です。この画面は適用、アーカイブ、削除、実行を行いません。',
            'state' => '実行準備状態',
            'eligible' => '実行適格',
            'approval_valid' => '承認有効',
            'fingerprint' => '準備状態フィンガープリント',
            'change_set' => '現在の変更セット',
            'plan' => '現在の計画',
            'approval' => '承認証拠',
            'decision' => '判断',
            'record_id' => '記録ID',
            'recorded_at' => '記録日時',
            'approver' => '承認者',
            'exact_match' => '完全一致',
            'confirmations' => '確認充足',
            'stored_at' => '保存先',
            'blockers' => '阻害理由',
            'diagnostics' => '診断',
            'none' => 'なし',
            'status_error' => '実行準備状態を安全に検証できませんでした。',
        ],
        'ne' => [
            'title' => 'Approval validity र execution readiness',
            'description' => 'हालको packet पुनः गणना गरी latest immutable approval evidence जाँच गर्नुहोस्।',
            'assess' => 'Execution readiness जाँच',
            'refresh' => 'Readiness पुनः जाँच',
            'hide' => 'Readiness लुकाउनुहोस्',
            'idle' => 'Execution readiness जाँच्नुअघि deletion change set बनाउनुहोस्।',
            'boundary' => 'Verification मात्र। यो workspace ले apply, archive, delete वा execute गर्दैन।',
            'state' => 'Execution readiness',
            'eligible' => 'Execution eligible',
            'approval_valid' => 'Approval valid',
            'fingerprint' => 'Readiness fingerprint',
            'change_set' => 'Current change set',
            'plan' => 'Current plan',
            'approval' => 'Approval evidence',
            'decision' => 'Decision',
            'record_id' => 'Record ID',
            'recorded_at' => 'Recorded at',
            'approver' => 'Approver',
            'exact_match' => 'Exact packet match',
            'confirmations' => 'Confirmations satisfied',
            'stored_at' => 'Stored at',
            'blockers' => 'Blocking reasons',
            'diagnostics' => 'Diagnostics',
            'none' => 'None',
            'status_error' => 'Execution readiness सुरक्षित रूपमा जाँच्न सकिएन।',
        ],
    ];
    $set = isset($dictionary[$lang]) ? $dictionary[$lang] : $dictionary['en'];
    return (string)($set[$key] ?? $dictionary['en'][$key] ?? $key);
};

$executionReadinessStatus = (string)($executionReadinessWorkspace['status'] ?? 'idle');
$executionReadinessPacket = isset($executionReadinessWorkspace['current_packet']) && is_array($executionReadinessWorkspace['current_packet']) ? $executionReadinessWorkspace['current_packet'] : [];
$executionReadinessEvidence = isset($executionReadinessWorkspace['approval_evidence']) && is_array($executionReadinessWorkspace['approval_evidence']) ? $executionReadinessWorkspace['approval_evidence'] : [];
$executionReadinessApprover = isset($executionReadinessEvidence['approver']) && is_array($executionReadinessEvidence['approver']) ? $executionReadinessEvidence['approver'] : [];
$executionReadinessStorage = isset($executionReadinessEvidence['storage']) && is_array($executionReadinessEvidence['storage']) ? $executionReadinessEvidence['storage'] : [];
$executionReadinessBlockers = isset($executionReadinessWorkspace['blocking_reasons']) && is_array($executionReadinessWorkspace['blocking_reasons']) ? $executionReadinessWorkspace['blocking_reasons'] : [];
$executionReadinessDiagnostics = isset($executionReadinessWorkspace['diagnostics']) && is_array($executionReadinessWorkspace['diagnostics']) ? $executionReadinessWorkspace['diagnostics'] : [];
$executionReadinessBaseUrl = '/apps/studio/tools/owner-structure-scan?owner=' . rawurlencode($impactSelectedOwnerKey) . '&scan=1&deletion_impact=1&deletion_plan=1&deletion_change_set=1&deletion_approval=1';
?>
<section class="oss-contract" aria-label="<?= e($executionReadinessText('title')) ?>">
  <header class="oss-section-head">
    <div><h3><?= e($executionReadinessText('title')) ?></h3><span><?= e($executionReadinessText('description')) ?></span></div>
    <span><?= e($executionReadinessText('boundary')) ?></span>
  </header>

  <form method="get" action="/apps/studio/tools/owner-structure-scan" class="oss-owner-form">
    <input type="hidden" name="owner" value="<?= e($impactSelectedOwnerKey) ?>">
    <input type="hidden" name="scan" value="1">
    <input type="hidden" name="deletion_impact" value="1">
    <input type="hidden" name="deletion_plan" value="1">
    <input type="hidden" name="deletion_change_set" value="1">
    <input type="hidden" name="deletion_approval" value="1">
    <button type="submit" name="deletion_execution_readiness" value="1"><?= e($executionReadinessText($executionReadinessRequested ? 'refresh' : 'assess')) ?></button>
    <?php if ($executionReadinessRequested): ?><a href="<?= e($executionReadinessBaseUrl) ?>"><?= e($executionReadinessText('hide')) ?></a><?php endif; ?>
  </form>

  <?php if ($executionReadinessStatus === 'idle'): ?>
    <p class="oss-planner-note"><?= e($executionReadinessText('idle')) ?></p>
  <?php elseif ($executionReadinessStatus === 'error'): ?>
    <p class="oss-owner-error"><strong><?= e($executionReadinessText('status_error')) ?></strong></p>
  <?php else: ?>
    <div class="oss-contract-summary">
      <div class="oss-summary-card"><dt><?= e($executionReadinessText('state')) ?></dt><dd><?= e((string)$executionReadinessWorkspace['execution_readiness']) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($executionReadinessText('eligible')) ?></dt><dd><?= e((string)$executionReadinessWorkspace['execution_eligible']) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($executionReadinessText('approval_valid')) ?></dt><dd><?= e((string)$executionReadinessWorkspace['approval_valid']) ?></dd></div>
    </div>

    <dl class="oss-reference-facts">
      <div><dt><?= e($executionReadinessText('fingerprint')) ?></dt><dd><code><?= e((string)$executionReadinessWorkspace['readiness_fingerprint']) ?></code></dd></div>
      <div><dt><?= e($executionReadinessText('change_set')) ?></dt><dd><code><?= e((string)($executionReadinessPacket['change_set_fingerprint'] ?? '')) ?></code></dd></div>
      <div><dt><?= e($executionReadinessText('plan')) ?></dt><dd><code><?= e((string)($executionReadinessPacket['plan_fingerprint'] ?? '')) ?></code></dd></div>
    </dl>

    <details class="oss-contract-findings" open>
      <summary><?= e($executionReadinessText('approval')) ?></summary>
      <?php if ((string)($executionReadinessEvidence['present'] ?? 'no') !== 'yes'): ?>
        <p class="oss-empty-group"><?= e($executionReadinessText('none')) ?></p>
      <?php else: ?>
        <dl class="oss-reference-facts">
          <div><dt><?= e($executionReadinessText('decision')) ?></dt><dd><?= e((string)($executionReadinessEvidence['decision'] ?? '')) ?></dd></div>
          <div><dt><?= e($executionReadinessText('record_id')) ?></dt><dd><code><?= e((string)($executionReadinessEvidence['record_id'] ?? '')) ?></code></dd></div>
          <div><dt><?= e($executionReadinessText('recorded_at')) ?></dt><dd><?= e((string)($executionReadinessEvidence['recorded_at_utc'] ?? '')) ?></dd></div>
          <div><dt><?= e($executionReadinessText('approver')) ?></dt><dd><?= e((string)($executionReadinessApprover['display_name'] ?? '')) ?></dd></div>
          <div><dt><?= e($executionReadinessText('exact_match')) ?></dt><dd><?= e((string)($executionReadinessEvidence['exact_packet_match'] ?? 'no')) ?></dd></div>
          <div><dt><?= e($executionReadinessText('confirmations')) ?></dt><dd><?= e((string)($executionReadinessEvidence['confirmations_satisfied'] ?? '')) ?></dd></div>
          <div><dt><?= e($executionReadinessText('stored_at')) ?></dt><dd><code><?= e((string)($executionReadinessStorage['relative_path'] ?? '')) ?></code></dd></div>
        </dl>
      <?php endif; ?>
    </details>

    <details class="oss-contract-findings"<?= $executionReadinessBlockers !== [] ? ' open' : '' ?>>
      <summary><?= e($executionReadinessText('blockers')) ?> (<?= e((string)count($executionReadinessBlockers)) ?>)</summary>
      <?php if ($executionReadinessBlockers === []): ?><p class="oss-empty-group"><?= e($executionReadinessText('none')) ?></p><?php else: ?>
        <ul class="oss-domain-list"><?php foreach ($executionReadinessBlockers as $reason): ?><li><code><?= e((string)$reason) ?></code></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </details>
  <?php endif; ?>

  <?php if ($executionReadinessDiagnostics !== []): ?>
    <details class="oss-contract-findings"><summary><?= e($executionReadinessText('diagnostics')) ?> (<?= e((string)count($executionReadinessDiagnostics)) ?>)</summary><ul class="oss-domain-list"><?php foreach ($executionReadinessDiagnostics as $diagnostic): if (!is_array($diagnostic)) { continue; } ?><li><code><?= e((string)($diagnostic['code'] ?? '')) ?></code> <?= e((string)($diagnostic['message'] ?? '')) ?></li><?php endforeach; ?></ul></details>
  <?php endif; ?>
</section>

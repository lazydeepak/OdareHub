<?php
declare(strict_types=1);

use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureDeletionApprovalWorkspaceService;

require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionApprovalWorkspaceService.php';

$approvalRequested = (string)($_GET['deletion_approval'] ?? '') === '1';
$approvalChangeSet = isset($changeSetWorkspace['change_set']) && is_array($changeSetWorkspace['change_set'])
    ? $changeSetWorkspace['change_set']
    : null;
$approvalFlash = isset($_SESSION['studio_owner_structure_deletion_approval_result'])
    && is_array($_SESSION['studio_owner_structure_deletion_approval_result'])
        ? $_SESSION['studio_owner_structure_deletion_approval_result']
        : null;
unset($_SESSION['studio_owner_structure_deletion_approval_result']);
$approvalWorkspace = OwnerStructureDeletionApprovalWorkspaceService::build(
    $impactOwners,
    $impactSelectedOwnerKey,
    $approvalRequested,
    $approvalChangeSet,
    $approvalFlash
);
$approvalCsrf = isset($ownerStructureScanModel['csrf']) ? (string)$ownerStructureScanModel['csrf'] : '';

$approvalText = static function (string $key): string {
    $lang = function_exists('current_lang') ? (string)current_lang() : 'en';
    $dictionary = [
        'en' => [
            'title' => 'Deletion approval record',
            'description' => 'Record an immutable approval or rejection against the exact current change-set fingerprint.',
            'open' => 'Review approval decision',
            'refresh' => 'Refresh approval workspace',
            'hide' => 'Hide approval workspace',
            'idle' => 'Build the deletion change set before opening approval recording.',
            'boundary' => 'Decision record only. Approval does not apply changes, archive files, delete the target, or grant execution authority.',
            'change_set' => 'Change-set fingerprint',
            'plan' => 'Plan fingerprint',
            'readiness' => 'Approval readiness',
            'confirmations' => 'Required confirmations',
            'reason' => 'Decision reason',
            'reason_help' => 'Explain the approval or rejection. The reason becomes immutable provenance.',
            'approve' => 'Record approval',
            'reject' => 'Record rejection',
            'approve_blocked' => 'Approval cannot be recorded until the packet readiness is ready.',
            'latest' => 'Latest decision for this packet',
            'no_latest' => 'No decision has been recorded for this exact change-set fingerprint.',
            'decision' => 'Decision',
            'record_id' => 'Record ID',
            'recorded_at' => 'Recorded at',
            'approver' => 'Approver',
            'stored_at' => 'Stored at',
            'result' => 'Decision result',
            'recorded' => 'Decision recorded successfully.',
            'failed' => 'Decision was not recorded.',
            'diagnostics' => 'Diagnostics',
            'status_error' => 'The approval workspace could not be prepared safely.',
        ],
        'ja' => [
            'title' => '削除承認記録',
            'description' => '現在の変更セットフィンガープリントに対する不変の承認または却下を記録します。',
            'open' => '承認判断を確認',
            'refresh' => '承認画面を更新',
            'hide' => '承認画面を隠す',
            'idle' => '承認記録を開く前に削除変更セットを作成してください。',
            'boundary' => '判断記録のみです。承認は変更適用、アーカイブ、削除、実行権限の付与を行いません。',
            'change_set' => '変更セットフィンガープリント',
            'plan' => '計画フィンガープリント',
            'readiness' => '承認準備状態',
            'confirmations' => '必須確認',
            'reason' => '判断理由',
            'reason_help' => '承認または却下の理由を記載してください。理由は不変の履歴になります。',
            'approve' => '承認を記録',
            'reject' => '却下を記録',
            'approve_blocked' => 'パケットが ready になるまで承認を記録できません。',
            'latest' => 'このパケットの最新判断',
            'no_latest' => 'この変更セットフィンガープリントには判断記録がありません。',
            'decision' => '判断',
            'record_id' => '記録ID',
            'recorded_at' => '記録日時',
            'approver' => '承認者',
            'stored_at' => '保存先',
            'result' => '判断結果',
            'recorded' => '判断を記録しました。',
            'failed' => '判断は記録されませんでした。',
            'diagnostics' => '診断',
            'status_error' => '承認画面を安全に準備できませんでした。',
        ],
        'ne' => [
            'title' => 'मेटाउने approval record',
            'description' => 'हालको change-set fingerprint विरुद्ध immutable approval वा rejection रेकर्ड गर्नुहोस्।',
            'open' => 'Approval निर्णय समीक्षा',
            'refresh' => 'Approval workspace पुनःलोड',
            'hide' => 'Approval workspace लुकाउनुहोस्',
            'idle' => 'Approval रेकर्ड खोल्नुअघि deletion change set बनाउनुहोस्।',
            'boundary' => 'Decision record मात्र। Approval ले apply, archive, delete वा execution authority प्रदान गर्दैन।',
            'change_set' => 'Change-set fingerprint',
            'plan' => 'Plan fingerprint',
            'readiness' => 'Approval readiness',
            'confirmations' => 'Required confirmations',
            'reason' => 'Decision reason',
            'reason_help' => 'Approval वा rejection को कारण लेख्नुहोस्। यो immutable provenance बन्छ।',
            'approve' => 'Approval रेकर्ड',
            'reject' => 'Rejection रेकर्ड',
            'approve_blocked' => 'Packet readiness ready नभएसम्म approval रेकर्ड गर्न सकिँदैन।',
            'latest' => 'यो packet को पछिल्लो निर्णय',
            'no_latest' => 'यो exact change-set fingerprint का लागि निर्णय रेकर्ड छैन।',
            'decision' => 'Decision',
            'record_id' => 'Record ID',
            'recorded_at' => 'Recorded at',
            'approver' => 'Approver',
            'stored_at' => 'Stored at',
            'result' => 'Decision result',
            'recorded' => 'Decision सफलतापूर्वक रेकर्ड भयो।',
            'failed' => 'Decision रेकर्ड भएन।',
            'diagnostics' => 'Diagnostics',
            'status_error' => 'Approval workspace सुरक्षित रूपमा तयार भएन।',
        ],
    ];
    $set = isset($dictionary[$lang]) ? $dictionary[$lang] : $dictionary['en'];
    return (string)($set[$key] ?? $dictionary['en'][$key] ?? $key);
};

$approvalStatus = (string)($approvalWorkspace['status'] ?? 'idle');
$approvalConfirmations = isset($approvalWorkspace['required_confirmations']) && is_array($approvalWorkspace['required_confirmations']) ? $approvalWorkspace['required_confirmations'] : [];
$approvalLatest = isset($approvalWorkspace['latest_record']) && is_array($approvalWorkspace['latest_record']) ? $approvalWorkspace['latest_record'] : null;
$approvalResult = isset($approvalWorkspace['flash']) && is_array($approvalWorkspace['flash']) ? $approvalWorkspace['flash'] : null;
$approvalDiagnostics = isset($approvalWorkspace['diagnostics']) && is_array($approvalWorkspace['diagnostics']) ? $approvalWorkspace['diagnostics'] : [];
$approvalBaseUrl = '/apps/studio/tools/owner-structure-scan?owner=' . rawurlencode($impactSelectedOwnerKey) . '&scan=1&deletion_impact=1&deletion_plan=1&deletion_change_set=1';
?>
<section class="oss-contract" aria-label="<?= e($approvalText('title')) ?>">
  <header class="oss-section-head">
    <div><h3><?= e($approvalText('title')) ?></h3><span><?= e($approvalText('description')) ?></span></div>
    <span><?= e($approvalText('boundary')) ?></span>
  </header>

  <form method="get" action="/apps/studio/tools/owner-structure-scan" class="oss-owner-form">
    <input type="hidden" name="owner" value="<?= e($impactSelectedOwnerKey) ?>">
    <input type="hidden" name="scan" value="1">
    <input type="hidden" name="deletion_impact" value="1">
    <input type="hidden" name="deletion_plan" value="1">
    <input type="hidden" name="deletion_change_set" value="1">
    <button type="submit" name="deletion_approval" value="1"><?= e($approvalText($approvalRequested ? 'refresh' : 'open')) ?></button>
    <?php if ($approvalRequested): ?><a href="<?= e($approvalBaseUrl) ?>"><?= e($approvalText('hide')) ?></a><?php endif; ?>
  </form>

  <?php if ($approvalStatus === 'idle'): ?>
    <p class="oss-planner-note"><?= e($approvalText('idle')) ?></p>
  <?php elseif ($approvalStatus === 'error'): ?>
    <p class="oss-owner-error"><strong><?= e($approvalText('status_error')) ?></strong></p>
  <?php else: ?>
    <div class="oss-contract-summary">
      <div class="oss-summary-card"><dt><?= e($approvalText('change_set')) ?></dt><dd><code><?= e((string)$approvalWorkspace['change_set_fingerprint']) ?></code></dd></div>
      <div class="oss-summary-card"><dt><?= e($approvalText('plan')) ?></dt><dd><code><?= e((string)$approvalWorkspace['plan_fingerprint']) ?></code></dd></div>
      <div class="oss-summary-card"><dt><?= e($approvalText('readiness')) ?></dt><dd><?= e((string)$approvalWorkspace['approval_readiness']) ?></dd></div>
    </div>

    <?php if ($approvalResult !== null): ?>
      <section class="oss-section-notice">
        <strong><?= e($approvalText('result')) ?>:</strong>
        <?= e((string)($approvalResult['status'] ?? '') === 'recorded' ? $approvalText('recorded') : $approvalText('failed')) ?>
        <?php if (isset($approvalResult['record_id'])): ?><code><?= e((string)$approvalResult['record_id']) ?></code><?php endif; ?>
      </section>
    <?php endif; ?>

    <form method="post" action="/apps/studio/tools/owner-structure-scan/deletion-approval-record" class="oss-contract">
      <input type="hidden" name="csrf" value="<?= e($approvalCsrf) ?>">
      <input type="hidden" name="owner" value="<?= e($impactSelectedOwnerKey) ?>">
      <input type="hidden" name="change_set_fingerprint" value="<?= e((string)$approvalWorkspace['change_set_fingerprint']) ?>">
      <input type="hidden" name="plan_fingerprint" value="<?= e((string)$approvalWorkspace['plan_fingerprint']) ?>">

      <h4><?= e($approvalText('confirmations')) ?></h4>
      <div class="oss-domain-list">
        <?php foreach ($approvalConfirmations as $confirmation): ?>
          <label><input type="checkbox" name="confirmations[]" value="<?= e((string)$confirmation) ?>"> <code><?= e((string)$confirmation) ?></code></label>
        <?php endforeach; ?>
      </div>

      <label style="display:grid;gap:.35rem;margin-top:1rem;">
        <strong><?= e($approvalText('reason')) ?></strong>
        <textarea name="reason" rows="4" maxlength="2000" required></textarea>
        <span class="oss-muted"><?= e($approvalText('reason_help')) ?></span>
      </label>

      <?php if ((string)$approvalWorkspace['can_approve'] !== 'yes'): ?><p class="oss-owner-error"><?= e($approvalText('approve_blocked')) ?></p><?php endif; ?>
      <div class="oss-init-actions" style="gap:.5rem;">
        <button type="submit" name="decision" value="approved"<?= (string)$approvalWorkspace['can_approve'] === 'yes' ? '' : ' disabled' ?>><?= e($approvalText('approve')) ?></button>
        <button type="submit" name="decision" value="rejected"><?= e($approvalText('reject')) ?></button>
      </div>
    </form>

    <details class="oss-contract-findings" open>
      <summary><?= e($approvalText('latest')) ?></summary>
      <?php if ($approvalLatest === null): ?>
        <p class="oss-empty-group"><?= e($approvalText('no_latest')) ?></p>
      <?php else: ?>
        <dl class="oss-reference-facts">
          <div><dt><?= e($approvalText('decision')) ?></dt><dd><?= e((string)($approvalLatest['decision'] ?? '')) ?></dd></div>
          <div><dt><?= e($approvalText('record_id')) ?></dt><dd><code><?= e((string)($approvalLatest['record_id'] ?? '')) ?></code></dd></div>
          <div><dt><?= e($approvalText('recorded_at')) ?></dt><dd><?= e((string)($approvalLatest['recorded_at_utc'] ?? '')) ?></dd></div>
          <div><dt><?= e($approvalText('approver')) ?></dt><dd><?= e((string)($approvalLatest['approver']['display_name'] ?? '')) ?></dd></div>
          <div><dt><?= e($approvalText('reason')) ?></dt><dd><?= e((string)($approvalLatest['reason'] ?? '')) ?></dd></div>
          <div><dt><?= e($approvalText('stored_at')) ?></dt><dd><code><?= e((string)($approvalLatest['storage']['relative_path'] ?? '')) ?></code></dd></div>
        </dl>
      <?php endif; ?>
    </details>
  <?php endif; ?>

  <?php
    $allApprovalDiagnostics = $approvalDiagnostics;
    if ($approvalResult !== null && isset($approvalResult['diagnostics']) && is_array($approvalResult['diagnostics'])) {
        $allApprovalDiagnostics = array_merge($allApprovalDiagnostics, $approvalResult['diagnostics']);
    }
  ?>
  <?php if ($allApprovalDiagnostics !== []): ?>
    <details class="oss-contract-findings">
      <summary><?= e($approvalText('diagnostics')) ?> (<?= e((string)count($allApprovalDiagnostics)) ?>)</summary>
      <ul class="oss-domain-list">
        <?php foreach ($allApprovalDiagnostics as $diagnostic): ?>
          <?php if (!is_array($diagnostic)) { continue; } ?>
          <li><code><?= e((string)($diagnostic['code'] ?? '')) ?></code> <?= e((string)($diagnostic['message'] ?? '')) ?></li>
        <?php endforeach; ?>
      </ul>
    </details>
  <?php endif; ?>
</section>

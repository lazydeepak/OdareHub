<?php
declare(strict_types=1);

use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureDeletionImpactWorkspaceService;

require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionImpactWorkspaceService.php';

$impactRequested = (string)($_GET['deletion_impact'] ?? '') === '1';
$impactOwners = isset($owners) && is_array($owners)
    ? $owners
    : (isset($ossModel['owners']) && is_array($ossModel['owners']) ? $ossModel['owners'] : []);
$impactSelectedOwnerKey = isset($selectedOwnerKey)
    ? (string)$selectedOwnerKey
    : (string)($ossModel['selected_owner_key'] ?? '');
$impactWorkspace = OwnerStructureDeletionImpactWorkspaceService::build(
    $impactOwners,
    $impactSelectedOwnerKey,
    $impactRequested
);

$impactText = static function (string $key): string {
    $lang = function_exists('current_lang') ? (string)current_lang() : 'en';
    $dictionary = [
        'en' => [
            'title' => 'Deletion impact assessment',
            'description' => 'Read-only inbound-reference and dependent-owner evidence for the selected owner. No delete action is available.',
            'assess' => 'Assess deletion impact',
            'refresh' => 'Refresh assessment',
            'hide' => 'Hide assessment',
            'idle' => 'Run the assessment only when deletion impact evidence is needed.',
            'readiness' => 'Deletion readiness',
            'safe' => 'Safe to delete',
            'references' => 'Inbound references',
            'files' => 'Files referencing',
            'owners' => 'Dependent owners',
            'runtime' => 'Runtime blockers',
            'review' => 'Review required',
            'cleanup' => 'Cleanup only',
            'blocking_reasons' => 'Blocking reasons',
            'dependent_owners' => 'Dependent owner evidence',
            'owner' => 'Owner',
            'scope' => 'Scope',
            'severity' => 'Severity',
            'reference_count' => 'References',
            'reference_evidence' => 'Reference evidence',
            'file' => 'File',
            'line' => 'Line',
            'match' => 'Match',
            'relevance' => 'Relevance',
            'diagnostics' => 'Diagnostics',
            'no_reasons' => 'No blocking reasons were reported.',
            'no_dependents' => 'No dependent owners were discovered.',
            'no_references' => 'No inbound references were discovered outside the owner being assessed.',
            'status_error' => 'The assessment could not be completed safely.',
            'readonly' => 'Assessment only. This workspace has no POST endpoint, delete control, apply control, or mutation path.',
        ],
        'ja' => [
            'title' => '削除影響評価',
            'description' => '選択した所有者への受信参照と依存所有者を読み取り専用で確認します。削除操作はありません。',
            'assess' => '削除影響を評価',
            'refresh' => '評価を更新',
            'hide' => '評価を隠す',
            'idle' => '削除影響の証拠が必要な場合だけ評価を実行します。',
            'readiness' => '削除準備状態',
            'safe' => '削除可能',
            'references' => '受信参照',
            'files' => '参照ファイル',
            'owners' => '依存所有者',
            'runtime' => 'ランタイム阻害',
            'review' => '要確認',
            'cleanup' => 'クリーンアップのみ',
            'blocking_reasons' => '阻害理由',
            'dependent_owners' => '依存所有者の証拠',
            'owner' => '所有者',
            'scope' => '範囲',
            'severity' => '重要度',
            'reference_count' => '参照',
            'reference_evidence' => '参照の証拠',
            'file' => 'ファイル',
            'line' => '行',
            'match' => '一致',
            'relevance' => '関連性',
            'diagnostics' => '診断',
            'no_reasons' => '阻害理由は報告されていません。',
            'no_dependents' => '依存所有者は検出されませんでした。',
            'no_references' => '評価対象外からの受信参照は検出されませんでした。',
            'status_error' => '評価を安全に完了できませんでした。',
            'readonly' => '評価専用です。POST、削除、適用、変更経路はありません。',
        ],
        'ne' => [
            'title' => 'मेटाउने प्रभाव मूल्याङ्कन',
            'description' => 'छानिएको owner का inbound references र dependent owners को read-only प्रमाण। मेटाउने कार्य उपलब्ध छैन।',
            'assess' => 'मेटाउने प्रभाव जाँच्नुहोस्',
            'refresh' => 'मूल्याङ्कन पुनः चलाउनुहोस्',
            'hide' => 'मूल्याङ्कन लुकाउनुहोस्',
            'idle' => 'मेटाउने प्रभावको प्रमाण आवश्यक हुँदा मात्र मूल्याङ्कन चलाउनुहोस्।',
            'readiness' => 'मेटाउने तयारी',
            'safe' => 'मेटाउन सुरक्षित',
            'references' => 'Inbound references',
            'files' => 'Reference भएका फाइल',
            'owners' => 'Dependent owners',
            'runtime' => 'Runtime blockers',
            'review' => 'Review आवश्यक',
            'cleanup' => 'Cleanup मात्र',
            'blocking_reasons' => 'रोकावटका कारण',
            'dependent_owners' => 'Dependent owner प्रमाण',
            'owner' => 'Owner',
            'scope' => 'Scope',
            'severity' => 'Severity',
            'reference_count' => 'References',
            'reference_evidence' => 'Reference प्रमाण',
            'file' => 'फाइल',
            'line' => 'लाइन',
            'match' => 'Match',
            'relevance' => 'Relevance',
            'diagnostics' => 'Diagnostics',
            'no_reasons' => 'कुनै blocking reason रिपोर्ट भएन।',
            'no_dependents' => 'कुनै dependent owner भेटिएन।',
            'no_references' => 'Owner बाहिर कुनै inbound reference भेटिएन।',
            'status_error' => 'मूल्याङ्कन सुरक्षित रूपमा पूरा भएन।',
            'readonly' => 'यो assessment मात्र हो। POST, delete, apply वा mutation path छैन।',
        ],
    ];
    $set = isset($dictionary[$lang]) ? $dictionary[$lang] : $dictionary['en'];
    return (string)($set[$key] ?? $dictionary['en'][$key] ?? $key);
};

$impactStatus = (string)($impactWorkspace['status'] ?? 'idle');
$impactSummary = isset($impactWorkspace['summary']) && is_array($impactWorkspace['summary']) ? $impactWorkspace['summary'] : [];
$impactBlockingReasons = isset($impactWorkspace['blocking_reasons']) && is_array($impactWorkspace['blocking_reasons']) ? $impactWorkspace['blocking_reasons'] : [];
$impactDependentOwners = isset($impactWorkspace['dependent_owners']) && is_array($impactWorkspace['dependent_owners']) ? $impactWorkspace['dependent_owners'] : [];
$impactReferences = isset($impactWorkspace['references']) && is_array($impactWorkspace['references']) ? $impactWorkspace['references'] : [];
$impactDiagnostics = isset($impactWorkspace['diagnostics']) && is_array($impactWorkspace['diagnostics']) ? $impactWorkspace['diagnostics'] : [];
$impactBaseUrl = '/apps/studio/tools/owner-structure-scan?owner=' . rawurlencode($impactSelectedOwnerKey) . '&scan=1';
?>
<section class="oss-contract" aria-label="<?= e($impactText('title')) ?>">
  <header class="oss-section-head">
    <div>
      <h3><?= e($impactText('title')) ?></h3>
      <span><?= e($impactText('description')) ?></span>
    </div>
    <span><?= e($impactText('readonly')) ?></span>
  </header>

  <form method="get" action="/apps/studio/tools/owner-structure-scan" class="oss-owner-form">
    <input type="hidden" name="owner" value="<?= e($impactSelectedOwnerKey) ?>">
    <input type="hidden" name="scan" value="1">
    <button type="submit" name="deletion_impact" value="1">
      <?= e($impactText($impactRequested ? 'refresh' : 'assess')) ?>
    </button>
    <?php if ($impactRequested): ?>
      <a href="<?= e($impactBaseUrl) ?>"><?= e($impactText('hide')) ?></a>
    <?php endif; ?>
  </form>

  <?php if ($impactStatus === 'idle'): ?>
    <p class="oss-planner-note"><?= e($impactText('idle')) ?></p>
  <?php elseif ($impactStatus === 'error'): ?>
    <p class="oss-owner-error"><strong><?= e($impactText('status_error')) ?></strong></p>
  <?php else: ?>
    <div class="oss-contract-summary">
      <div class="oss-summary-card"><dt><?= e($impactText('readiness')) ?></dt><dd><?= e((string)($impactWorkspace['deletion_readiness'] ?? 'unknown')) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($impactText('safe')) ?></dt><dd><?= e((string)($impactWorkspace['safe_to_delete'] ?? 'unknown')) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($impactText('references')) ?></dt><dd><?= e((string)($impactSummary['reference_count'] ?? 0)) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($impactText('files')) ?></dt><dd><?= e((string)($impactSummary['files_referencing'] ?? 0)) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($impactText('owners')) ?></dt><dd><?= e((string)($impactSummary['dependent_owner_count'] ?? 0)) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($impactText('runtime')) ?></dt><dd><?= e((string)($impactSummary['runtime_blockers'] ?? 0)) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($impactText('review')) ?></dt><dd><?= e((string)($impactSummary['review_required'] ?? 0)) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($impactText('cleanup')) ?></dt><dd><?= e((string)($impactSummary['cleanup_only'] ?? 0)) ?></dd></div>
    </div>

    <details class="oss-contract-findings" open>
      <summary><?= e($impactText('blocking_reasons')) ?> (<?= e((string)count($impactBlockingReasons)) ?>)</summary>
      <?php if ($impactBlockingReasons === []): ?>
        <p class="oss-empty-group"><?= e($impactText('no_reasons')) ?></p>
      <?php else: ?>
        <ul class="oss-domain-list">
          <?php foreach ($impactBlockingReasons as $reason): ?>
            <li><?= e((string)$reason) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </details>

    <details class="oss-contract-findings" open>
      <summary><?= e($impactText('dependent_owners')) ?> (<?= e((string)count($impactDependentOwners)) ?>)</summary>
      <?php if ($impactDependentOwners === []): ?>
        <p class="oss-empty-group"><?= e($impactText('no_dependents')) ?></p>
      <?php else: ?>
        <div class="oss-table-wrap">
          <table class="oss-finding-table">
            <thead><tr><th><?= e($impactText('owner')) ?></th><th><?= e($impactText('scope')) ?></th><th><?= e($impactText('severity')) ?></th><th><?= e($impactText('reference_count')) ?></th><th><?= e($impactText('files')) ?></th></tr></thead>
            <tbody>
              <?php foreach ($impactDependentOwners as $dependentOwner): ?>
                <?php if (!is_array($dependentOwner)) { continue; } ?>
                <tr>
                  <td><code><?= e((string)($dependentOwner['owner_key'] ?? '@repository')) ?></code></td>
                  <td><?= e((string)($dependentOwner['scope'] ?? 'repository')) ?></td>
                  <td><?= e((string)($dependentOwner['severity'] ?? 'cleanup')) ?></td>
                  <td><?= e((string)($dependentOwner['reference_count'] ?? 0)) ?></td>
                  <td><?= e(implode(', ', array_map('strval', is_array($dependentOwner['files'] ?? null) ? $dependentOwner['files'] : []))) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </details>

    <details class="oss-contract-findings">
      <summary><?= e($impactText('reference_evidence')) ?> (<?= e((string)count($impactReferences)) ?>)</summary>
      <?php if ($impactReferences === []): ?>
        <p class="oss-empty-group"><?= e($impactText('no_references')) ?></p>
      <?php else: ?>
        <div class="oss-table-wrap">
          <table class="oss-reference-table">
            <thead><tr><th><?= e($impactText('file')) ?></th><th><?= e($impactText('line')) ?></th><th><?= e($impactText('owner')) ?></th><th><?= e($impactText('severity')) ?></th><th><?= e($impactText('match')) ?></th><th><?= e($impactText('relevance')) ?></th></tr></thead>
            <tbody>
              <?php foreach (array_slice($impactReferences, 0, 100) as $reference): ?>
                <?php if (!is_array($reference)) { continue; } ?>
                <tr>
                  <td><code><?= e((string)($reference['file_path'] ?? '')) ?></code></td>
                  <td><?= e((string)($reference['line_number'] ?? '')) ?></td>
                  <td><code><?= e((string)($reference['referencing_owner_key'] ?? '@repository')) ?></code></td>
                  <td><?= e((string)($reference['impact_severity'] ?? 'cleanup')) ?></td>
                  <td><code><?= e((string)($reference['matched_pattern'] ?? '')) ?></code></td>
                  <td><?= e((string)($reference['relevance'] ?? '')) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </details>
  <?php endif; ?>

  <?php if ($impactDiagnostics !== []): ?>
    <details class="oss-contract-findings">
      <summary><?= e($impactText('diagnostics')) ?> (<?= e((string)count($impactDiagnostics)) ?>)</summary>
      <ul class="oss-domain-list">
        <?php foreach ($impactDiagnostics as $diagnostic): ?>
          <?php if (!is_array($diagnostic)) { continue; } ?>
          <li><code><?= e((string)($diagnostic['code'] ?? '')) ?></code> <?= e((string)($diagnostic['message'] ?? '')) ?></li>
        <?php endforeach; ?>
      </ul>
    </details>
  <?php endif; ?>
</section>

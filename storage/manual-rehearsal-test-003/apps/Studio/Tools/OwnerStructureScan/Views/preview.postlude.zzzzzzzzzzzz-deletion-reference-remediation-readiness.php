<?php
declare(strict_types=1);

use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureDeletionReferenceRemediationReadinessWorkspaceService;

require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionReferenceRemediationReadinessWorkspaceService.php';

$referenceRemediationRequested = (string)($_GET['deletion_reference_remediation_readiness'] ?? '') === '1';
$referenceRemediationChangeSet = isset($snapshotIntegrityChangeSet) && is_array($snapshotIntegrityChangeSet) ? $snapshotIntegrityChangeSet : null;
$referenceRemediationIntegrity = isset($snapshotIntegrityWorkspace['assessment']) && is_array($snapshotIntegrityWorkspace['assessment'])
    ? $snapshotIntegrityWorkspace['assessment']
    : null;
$referenceRemediationWorkspace = OwnerStructureDeletionReferenceRemediationReadinessWorkspaceService::build(
    $impactOwners,
    $impactSelectedOwnerKey,
    $referenceRemediationRequested,
    $referenceRemediationChangeSet,
    $referenceRemediationIntegrity,
    APP_ROOT
);

$rrText = static function (string $key): string {
    $lang = function_exists('current_lang') ? (string)current_lang() : 'en';
    $dictionary = [
        'en' => [
            'title'=>'Reference remediation readiness','description'=>'Re-run current reference evidence and freeze exact file, line, and context hashes for deterministic patches.',
            'open'=>'Verify reference remediation','refresh'=>'Refresh remediation readiness','hide'=>'Hide remediation readiness',
            'idle'=>'Verify snapshot integrity before preparing reference patch preconditions.',
            'boundary'=>'Read-only preconditions only. This workspace does not edit files, apply patches, archive content, delete the target, or execute the plan.',
            'state'=>'Remediation readiness','preconditions'=>'Preconditions ready','ready'=>'Remediation ready','review'=>'Review decision required','fingerprint'=>'Readiness fingerprint',
            'summary'=>'Summary','planned'=>'Planned files','verified'=>'Verified files','references'=>'Current references','blocking'=>'Blocking','review_count'=>'Review','cleanup'=>'Cleanup',
            'files'=>'Patch preconditions','path'=>'Path','severity'=>'Severity','file_hash'=>'Expected file SHA-256','lines'=>'Lines','reference_count'=>'References','precondition_id'=>'Precondition ID',
            'line_evidence'=>'Line evidence','line'=>'Line','line_hash'=>'Line SHA-256','context_hash'=>'Context SHA-256','patterns'=>'Matched patterns',
            'blockers'=>'Blocking reasons','diagnostics'=>'Diagnostics','none'=>'None','status_error'=>'Reference-remediation readiness could not be verified safely.',
        ],
        'ja' => [
            'title'=>'参照修正準備状態','description'=>'現在の参照証拠を再検出し、決定的パッチ用のファイル・行・文脈ハッシュを固定します。',
            'open'=>'参照修正を検証','refresh'=>'準備状態を更新','hide'=>'準備状態を隠す','idle'=>'参照パッチ条件の前にスナップショット整合性を検証してください。',
            'boundary'=>'読み取り専用条件です。ファイル編集、パッチ適用、アーカイブ、対象削除、計画実行は行いません。',
            'state'=>'修正準備状態','preconditions'=>'条件準備','ready'=>'修正準備','review'=>'レビュー判断必要','fingerprint'=>'フィンガープリント',
            'summary'=>'概要','planned'=>'計画ファイル','verified'=>'検証ファイル','references'=>'現在参照','blocking'=>'阻害','review_count'=>'レビュー','cleanup'=>'整理',
            'files'=>'パッチ条件','path'=>'パス','severity'=>'重大度','file_hash'=>'期待ファイルSHA-256','lines'=>'行','reference_count'=>'参照','precondition_id'=>'条件ID',
            'line_evidence'=>'行証拠','line'=>'行','line_hash'=>'行SHA-256','context_hash'=>'文脈SHA-256','patterns'=>'一致パターン',
            'blockers'=>'阻害理由','diagnostics'=>'診断','none'=>'なし','status_error'=>'参照修正準備状態を安全に検証できませんでした。',
        ],
        'ne' => [
            'title'=>'Reference remediation readiness','description'=>'Current reference evidence पुनः चलाएर deterministic patch का file, line र context hashes freeze गर्नुहोस्।',
            'open'=>'Reference remediation जाँच','refresh'=>'Readiness पुनः जाँच','hide'=>'Readiness लुकाउनुहोस्','idle'=>'Patch preconditions अघि snapshot integrity जाँच गर्नुहोस्।',
            'boundary'=>'Read-only preconditions मात्र। यसले file edit, patch apply, archive, target delete वा plan execute गर्दैन।',
            'state'=>'Remediation readiness','preconditions'=>'Preconditions ready','ready'=>'Remediation ready','review'=>'Review decision required','fingerprint'=>'Readiness fingerprint',
            'summary'=>'Summary','planned'=>'Planned files','verified'=>'Verified files','references'=>'Current references','blocking'=>'Blocking','review_count'=>'Review','cleanup'=>'Cleanup',
            'files'=>'Patch preconditions','path'=>'Path','severity'=>'Severity','file_hash'=>'Expected file SHA-256','lines'=>'Lines','reference_count'=>'References','precondition_id'=>'Precondition ID',
            'line_evidence'=>'Line evidence','line'=>'Line','line_hash'=>'Line SHA-256','context_hash'=>'Context SHA-256','patterns'=>'Matched patterns',
            'blockers'=>'Blocking reasons','diagnostics'=>'Diagnostics','none'=>'None','status_error'=>'Reference-remediation readiness सुरक्षित रूपमा जाँच्न सकिएन।',
        ],
    ];
    $set=$dictionary[$lang]??$dictionary['en']; return (string)($set[$key]??$dictionary['en'][$key]??$key);
};
$status=(string)($referenceRemediationWorkspace['status']??'idle');
$summary=is_array($referenceRemediationWorkspace['summary']??null)?$referenceRemediationWorkspace['summary']:[];
$preconditions=is_array($referenceRemediationWorkspace['patch_preconditions']??null)?$referenceRemediationWorkspace['patch_preconditions']:[];
$blockers=is_array($referenceRemediationWorkspace['blocking_reasons']??null)?$referenceRemediationWorkspace['blocking_reasons']:[];
$diagnostics=is_array($referenceRemediationWorkspace['diagnostics']??null)?$referenceRemediationWorkspace['diagnostics']:[];
$baseUrl='/apps/studio/tools/owner-structure-scan?owner='.rawurlencode($impactSelectedOwnerKey).'&scan=1&deletion_impact=1&deletion_plan=1&deletion_change_set=1&deletion_approval=1&deletion_execution_readiness=1&deletion_execution_dry_run=1&deletion_execution_request=1&deletion_executor_readiness=1&deletion_execution_claim=1&deletion_snapshot_readiness=1&deletion_snapshot_creation=1&deletion_snapshot_integrity=1';
?>
<section class="oss-contract" aria-label="<?=e($rrText('title'))?>">
<header class="oss-section-head"><div><h3><?=e($rrText('title'))?></h3><span><?=e($rrText('description'))?></span></div><span><?=e($rrText('boundary'))?></span></header>
<form method="get" action="/apps/studio/tools/owner-structure-scan" class="oss-owner-form">
<input type="hidden" name="owner" value="<?=e($impactSelectedOwnerKey)?>"><input type="hidden" name="scan" value="1"><input type="hidden" name="deletion_impact" value="1"><input type="hidden" name="deletion_plan" value="1"><input type="hidden" name="deletion_change_set" value="1"><input type="hidden" name="deletion_approval" value="1"><input type="hidden" name="deletion_execution_readiness" value="1"><input type="hidden" name="deletion_execution_dry_run" value="1"><input type="hidden" name="deletion_execution_request" value="1"><input type="hidden" name="deletion_executor_readiness" value="1"><input type="hidden" name="deletion_execution_claim" value="1"><input type="hidden" name="deletion_snapshot_readiness" value="1"><input type="hidden" name="deletion_snapshot_creation" value="1"><input type="hidden" name="deletion_snapshot_integrity" value="1">
<button type="submit" name="deletion_reference_remediation_readiness" value="1"><?=e($rrText($referenceRemediationRequested?'refresh':'open'))?></button><?php if($referenceRemediationRequested):?><a href="<?=e($baseUrl)?>"><?=e($rrText('hide'))?></a><?php endif;?>
</form>
<?php if($status==='idle'):?><p class="oss-planner-note"><?=e($rrText('idle'))?></p>
<?php elseif($status==='error'):?><p class="oss-owner-error"><strong><?=e($rrText('status_error'))?></strong></p>
<?php else:?>
<div class="oss-contract-summary"><div class="oss-summary-card"><dt><?=e($rrText('state'))?></dt><dd><?=e((string)$referenceRemediationWorkspace['reference_remediation_readiness'])?></dd></div><div class="oss-summary-card"><dt><?=e($rrText('preconditions'))?></dt><dd><?=e((string)$referenceRemediationWorkspace['preconditions_ready'])?></dd></div><div class="oss-summary-card"><dt><?=e($rrText('ready'))?></dt><dd><?=e((string)$referenceRemediationWorkspace['reference_remediation_ready'])?></dd></div><div class="oss-summary-card"><dt><?=e($rrText('review'))?></dt><dd><?=e((string)$referenceRemediationWorkspace['requires_review_decision'])?></dd></div></div>
<dl class="oss-reference-facts"><div><dt><?=e($rrText('fingerprint'))?></dt><dd><code><?=e((string)$referenceRemediationWorkspace['reference_remediation_readiness_fingerprint'])?></code></dd></div><div><dt><?=e($rrText('planned'))?></dt><dd><?=e((string)($summary['planned_file_change_count']??0))?></dd></div><div><dt><?=e($rrText('verified'))?></dt><dd><?=e((string)($summary['verified_file_count']??0))?></dd></div><div><dt><?=e($rrText('references'))?></dt><dd><?=e((string)($summary['current_reference_count']??0))?></dd></div></dl>
<details class="oss-contract-findings" open><summary><?=e($rrText('files'))?> (<?=e((string)count($preconditions))?>)</summary><?php if($preconditions===[]):?><p class="oss-empty-group"><?=e($rrText('none'))?></p><?php else:?><?php foreach($preconditions as $item): if(!is_array($item))continue;?><article class="oss-reference-item"><dl class="oss-reference-facts"><div><dt><?=e($rrText('path'))?></dt><dd><code><?=e((string)($item['path']??''))?></code></dd></div><div><dt><?=e($rrText('severity'))?></dt><dd><?=e((string)($item['severity']??''))?></dd></div><div><dt><?=e($rrText('file_hash'))?></dt><dd><code><?=e((string)($item['expected_file_sha256']??''))?></code></dd></div><div><dt><?=e($rrText('lines'))?></dt><dd><?=e(implode(', ',array_map('strval',$item['expected_line_numbers']??[])))?></dd></div><div><dt><?=e($rrText('reference_count'))?></dt><dd><?=e((string)($item['expected_reference_count']??0))?></dd></div><div><dt><?=e($rrText('precondition_id'))?></dt><dd><code><?=e((string)($item['precondition_id']??''))?></code></dd></div></dl><?php $lineEvidence=is_array($item['line_evidence']??null)?$item['line_evidence']:[];?><details><summary><?=e($rrText('line_evidence'))?> (<?=e((string)count($lineEvidence))?>)</summary><ul class="oss-domain-list"><?php foreach($lineEvidence as $line):if(!is_array($line))continue;?><li><strong><?=e($rrText('line'))?> <?=e((string)($line['line_number']??0))?></strong> <code><?=e((string)($line['line_sha256']??''))?></code><br><small><?=e($rrText('context_hash'))?>: <code><?=e((string)($line['context_sha256']??''))?></code> · <?=e($rrText('patterns'))?>: <?=e(implode(', ',array_map('strval',$line['matched_patterns']??[])))?></small></li><?php endforeach;?></ul></details></article><?php endforeach;?><?php endif;?></details>
<?php endif;?>
<?php if($blockers!==[]):?><details class="oss-contract-findings" open><summary><?=e($rrText('blockers'))?> (<?=e((string)count($blockers))?>)</summary><ul class="oss-domain-list"><?php foreach($blockers as $reason):?><li><code><?=e((string)$reason)?></code></li><?php endforeach;?></ul></details><?php endif;?>
<?php if($diagnostics!==[]):?><details class="oss-contract-findings"><summary><?=e($rrText('diagnostics'))?> (<?=e((string)count($diagnostics))?>)</summary><ul class="oss-domain-list"><?php foreach($diagnostics as $diagnostic):if(!is_array($diagnostic))continue;?><li><code><?=e((string)($diagnostic['code']??''))?></code> <?=e((string)($diagnostic['message']??''))?></li><?php endforeach;?></ul></details><?php endif;?>
</section>

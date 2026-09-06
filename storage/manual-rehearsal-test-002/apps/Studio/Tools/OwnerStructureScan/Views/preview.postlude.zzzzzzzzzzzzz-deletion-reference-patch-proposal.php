<?php
declare(strict_types=1);

use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureDeletionReferenceRemediationPatchProposalWorkspaceService;

require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionReferenceRemediationPatchProposalWorkspaceService.php';

$referencePatchProposalRequested = (string)($_GET['deletion_reference_patch_proposal'] ?? '') === '1';
$referencePatchReadiness = isset($referenceRemediationWorkspace['assessment']) && is_array($referenceRemediationWorkspace['assessment'])
    ? $referenceRemediationWorkspace['assessment']
    : (isset($referenceRemediationWorkspace) && is_array($referenceRemediationWorkspace) ? $referenceRemediationWorkspace : null);
$referencePatchProposalWorkspace = OwnerStructureDeletionReferenceRemediationPatchProposalWorkspaceService::build(
    $impactOwners,
    $impactSelectedOwnerKey,
    $referencePatchProposalRequested,
    $referencePatchReadiness,
    APP_ROOT
);
$rpText = static function (string $key): string {
    $dictionary = [
        'title'=>'Reference remediation patch proposals','description'=>'Generate exact reviewable candidate diffs from verified reference preconditions.',
        'open'=>'Generate patch proposals','refresh'=>'Refresh patch proposals','hide'=>'Hide patch proposals',
        'idle'=>'Verify reference-remediation readiness before generating candidate diffs.',
        'boundary'=>'Proposal only. This workspace does not edit files, apply patches, archive content, delete the target, or execute the plan.',
        'state'=>'Proposal state','ready'=>'Proposals ready','decisions'=>'Decisions resolved','fingerprint'=>'Proposal fingerprint',
        'proposal_count'=>'Proposals','decision_count'=>'Decisions required','edit_count'=>'Edits','proposals'=>'Candidate diffs','decision_items'=>'Review decisions',
        'path'=>'Path','severity'=>'Severity','diff'=>'Unified diff','alternatives'=>'Alternatives','line'=>'Line','current'=>'Current line',
        'blockers'=>'Blocking reasons','diagnostics'=>'Diagnostics','none'=>'None','status_error'=>'Patch proposals could not be generated safely.',
    ];
    return (string)($dictionary[$key] ?? $key);
};
$status=(string)($referencePatchProposalWorkspace['status']??'idle');
$summary=is_array($referencePatchProposalWorkspace['summary']??null)?$referencePatchProposalWorkspace['summary']:[];
$proposals=is_array($referencePatchProposalWorkspace['patch_proposals']??null)?$referencePatchProposalWorkspace['patch_proposals']:[];
$decisions=is_array($referencePatchProposalWorkspace['decision_items']??null)?$referencePatchProposalWorkspace['decision_items']:[];
$blockers=is_array($referencePatchProposalWorkspace['blocking_reasons']??null)?$referencePatchProposalWorkspace['blocking_reasons']:[];
$diagnostics=is_array($referencePatchProposalWorkspace['diagnostics']??null)?$referencePatchProposalWorkspace['diagnostics']:[];
$baseUrl='/apps/studio/tools/owner-structure-scan?owner='.rawurlencode($impactSelectedOwnerKey).'&scan=1&deletion_impact=1&deletion_plan=1&deletion_change_set=1&deletion_approval=1&deletion_execution_readiness=1&deletion_execution_dry_run=1&deletion_execution_request=1&deletion_executor_readiness=1&deletion_execution_claim=1&deletion_snapshot_readiness=1&deletion_snapshot_creation=1&deletion_snapshot_integrity=1&deletion_reference_remediation_readiness=1';
?>
<section class="oss-contract" aria-label="<?=e($rpText('title'))?>">
<header class="oss-section-head"><div><h3><?=e($rpText('title'))?></h3><span><?=e($rpText('description'))?></span></div><span><?=e($rpText('boundary'))?></span></header>
<form method="get" action="/apps/studio/tools/owner-structure-scan" class="oss-owner-form">
<?php foreach(['scan','deletion_impact','deletion_plan','deletion_change_set','deletion_approval','deletion_execution_readiness','deletion_execution_dry_run','deletion_execution_request','deletion_executor_readiness','deletion_execution_claim','deletion_snapshot_readiness','deletion_snapshot_creation','deletion_snapshot_integrity','deletion_reference_remediation_readiness'] as $field):?><input type="hidden" name="<?=e($field)?>" value="1"><?php endforeach;?><input type="hidden" name="owner" value="<?=e($impactSelectedOwnerKey)?>">
<button type="submit" name="deletion_reference_patch_proposal" value="1"><?=e($rpText($referencePatchProposalRequested?'refresh':'open'))?></button><?php if($referencePatchProposalRequested):?><a href="<?=e($baseUrl)?>"><?=e($rpText('hide'))?></a><?php endif;?>
</form>
<?php if($status==='idle'):?><p class="oss-planner-note"><?=e($rpText('idle'))?></p>
<?php elseif($status==='error'):?><p class="oss-owner-error"><strong><?=e($rpText('status_error'))?></strong></p>
<?php else:?>
<div class="oss-contract-summary"><div class="oss-summary-card"><dt><?=e($rpText('state'))?></dt><dd><?=e((string)$referencePatchProposalWorkspace['patch_proposal_state'])?></dd></div><div class="oss-summary-card"><dt><?=e($rpText('ready'))?></dt><dd><?=e((string)$referencePatchProposalWorkspace['proposals_ready'])?></dd></div><div class="oss-summary-card"><dt><?=e($rpText('decisions'))?></dt><dd><?=e((string)$referencePatchProposalWorkspace['all_decisions_resolved'])?></dd></div></div>
<dl class="oss-reference-facts"><div><dt><?=e($rpText('fingerprint'))?></dt><dd><code><?=e((string)$referencePatchProposalWorkspace['patch_proposal_fingerprint'])?></code></dd></div><div><dt><?=e($rpText('proposal_count'))?></dt><dd><?=e((string)($summary['proposal_count']??0))?></dd></div><div><dt><?=e($rpText('decision_count'))?></dt><dd><?=e((string)($summary['decision_required_count']??0))?></dd></div><div><dt><?=e($rpText('edit_count'))?></dt><dd><?=e((string)($summary['edit_count']??0))?></dd></div></dl>
<details class="oss-contract-findings" open><summary><?=e($rpText('proposals'))?> (<?=e((string)count($proposals))?>)</summary><?php if($proposals===[]):?><p class="oss-empty-group"><?=e($rpText('none'))?></p><?php else:foreach($proposals as $proposal):if(!is_array($proposal))continue;?><article class="oss-reference-item"><strong><code><?=e((string)($proposal['path']??''))?></code></strong> · <?=e((string)($proposal['severity']??''))?><pre><code><?=e((string)($proposal['unified_diff']??''))?></code></pre></article><?php endforeach;endif;?></details>
<details class="oss-contract-findings"<?= $decisions!==[]?' open':''?>><summary><?=e($rpText('decision_items'))?> (<?=e((string)count($decisions))?>)</summary><?php if($decisions===[]):?><p class="oss-empty-group"><?=e($rpText('none'))?></p><?php else:foreach($decisions as $decision):if(!is_array($decision))continue;?><article class="oss-reference-item"><strong><code><?=e((string)($decision['path']??''))?></code></strong><?php foreach(($decision['line_items']??[]) as $line):if(!is_array($line))continue;?><p><?=e($rpText('line'))?> <?=e((string)($line['line_number']??0))?>: <code><?=e((string)($line['current_line']??''))?></code></p><?php endforeach;?><ul class="oss-domain-list"><?php foreach(($decision['alternatives']??[]) as $alternative):if(!is_array($alternative))continue;?><li><code><?=e((string)($alternative['decision']??''))?></code></li><?php endforeach;?></ul></article><?php endforeach;endif;?></details>
<?php endif;?>
<?php if($blockers!==[]):?><details class="oss-contract-findings" open><summary><?=e($rpText('blockers'))?> (<?=e((string)count($blockers))?>)</summary><ul class="oss-domain-list"><?php foreach($blockers as $reason):?><li><code><?=e((string)$reason)?></code></li><?php endforeach;?></ul></details><?php endif;?>
<?php if($diagnostics!==[]):?><details class="oss-contract-findings"><summary><?=e($rpText('diagnostics'))?> (<?=e((string)count($diagnostics))?>)</summary><ul class="oss-domain-list"><?php foreach($diagnostics as $diagnostic):if(!is_array($diagnostic))continue;?><li><code><?=e((string)($diagnostic['code']??''))?></code> <?=e((string)($diagnostic['message']??''))?></li><?php endforeach;?></ul></details><?php endif;?>
</section>

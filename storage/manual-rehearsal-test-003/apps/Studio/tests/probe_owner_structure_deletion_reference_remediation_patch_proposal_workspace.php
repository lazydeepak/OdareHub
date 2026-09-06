<?php
declare(strict_types=1);

use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureDeletionReferenceRemediationPatchProposalWorkspaceService;

require_once dirname(__DIR__) . '/Tools/OwnerStructureScan/Services/OwnerStructureDeletionReferenceRemediationPatchProposalWorkspaceService.php';

$pass=0;$fail=0;$assert=static function(bool $condition,string $message)use(&$pass,&$fail):void{if($condition){$pass++;echo"PASS: {$message}\n";}else{$fail++;echo"FAIL: {$message}\n";}};
$owners=[['owner_key'=>'Manufacturing/Products','root_path'=>'apps/Manufacturing/modules/Products']];
$readiness=['target'=>['owner_key'=>'Manufacturing/Products'],'reference_remediation_readiness_fingerprint'=>'reference-remediation:current'];
$proposal=[
 'status'=>'ok','patch_proposal_state'=>'decision_required','proposals_ready'=>'yes','all_decisions_resolved'=>'no',
 'summary'=>['proposal_count'=>1,'decision_required_count'=>1,'edit_count'=>1],
 'patch_proposals'=>[['proposal_id'=>'proposal-1','path'=>'config/test.php','unified_diff'=>'diff']],
 'decision_items'=>[['decision_id'=>'decision-1','path'=>'docs/test.md']],
 'blocking_reasons'=>[],'patch_proposal_fingerprint'=>'deletion-reference-patch-proposal:workspace','diagnostics'=>[],
];
$calls=0;$proposer=static function(array $owner,array $packet,?string $root)use(&$calls,$proposal):array{$calls++;if(($owner['owner_key']??'')!=='Manufacturing/Products'){throw new RuntimeException('wrong owner');}return $proposal;};
$idle=OwnerStructureDeletionReferenceRemediationPatchProposalWorkspaceService::build($owners,'Manufacturing/Products',false,$readiness,null,$proposer);
$assert(($idle['status']??'')==='idle','idle workspace does not generate proposals');
$assert($calls===0,'idle workspace invokes no proposer');
$ready=OwnerStructureDeletionReferenceRemediationPatchProposalWorkspaceService::build($owners,'Manufacturing/Products',true,$readiness,null,$proposer);
$assert(($ready['status']??'')==='ready','requested workspace becomes ready');
$assert($calls===1,'proposer is invoked once');
$assert(($ready['patch_proposal_state']??'')==='decision_required','proposal state is exposed');
$assert(($ready['proposals_ready']??'')==='yes','proposal readiness is exposed');
$assert(($ready['all_decisions_resolved']??'')==='no','decision state is exposed');
$assert(($ready['summary']['proposal_count']??0)===1,'summary is exposed');
$assert(count($ready['patch_proposals']??[])===1,'candidate proposal is exposed');
$assert(count($ready['decision_items']??[])===1,'decision item is exposed');
$assert(($ready['patch_proposal_fingerprint']??'')==='deletion-reference-patch-proposal:workspace','proposal fingerprint is exposed');
$missingOwner=OwnerStructureDeletionReferenceRemediationPatchProposalWorkspaceService::build($owners,'Missing/Owner',true,$readiness);
$assert(($missingOwner['status']??'')==='error','missing owner fails safely');
$assert(($missingOwner['diagnostics'][0]['code']??'')==='OSS_DELETION_REFERENCE_PATCH_PROPOSAL_OWNER_UNAVAILABLE','missing owner diagnostic is explicit');
$missingReadiness=OwnerStructureDeletionReferenceRemediationPatchProposalWorkspaceService::build($owners,'Manufacturing/Products',true,null);
$assert(($missingReadiness['status']??'')==='error','missing readiness fails safely');
$assert(($missingReadiness['diagnostics'][0]['code']??'')==='OSS_DELETION_REFERENCE_PATCH_PROPOSAL_READINESS_REQUIRED','missing readiness diagnostic is explicit');
$invalid=OwnerStructureDeletionReferenceRemediationPatchProposalWorkspaceService::build($owners,'Manufacturing/Products',true,$readiness,null,static fn():string=>'invalid');
$assert(($invalid['status']??'')==='error','invalid proposer result fails safely');
$assert(($invalid['diagnostics'][0]['code']??'')==='OSS_DELETION_REFERENCE_PATCH_PROPOSAL_RESULT_INVALID','invalid result diagnostic is explicit');
$thrown=OwnerStructureDeletionReferenceRemediationPatchProposalWorkspaceService::build($owners,'Manufacturing/Products',true,$readiness,null,static function():array{throw new RuntimeException('proposal failure');});
$assert(($thrown['status']??'')==='error','proposer exception fails safely');
$assert(($thrown['diagnostics'][0]['code']??'')==='OSS_DELETION_REFERENCE_PATCH_PROPOSAL_FAILED','exception diagnostic is explicit');
$source=file_get_contents(dirname(__DIR__).'/Tools/OwnerStructureScan/Services/OwnerStructureDeletionReferenceRemediationPatchProposalWorkspaceService.php');
$assert(is_string($source)&&!preg_match('/file_put_contents|fwrite|unlink\s*\(|rename\s*\(|copy\s*\(|mkdir\s*\(|rmdir\s*\(|PDO|INSERT\s+INTO|UPDATE\s+.+SET|DELETE\s+FROM/i',$source),'workspace presenter contains no mutation implementation');
echo"\nResults: {$pass} passed, {$fail} failed\n";exit($fail>0?1:0);

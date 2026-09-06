<?php
declare(strict_types=1);

use Apps\Studio\Services\StudioDeletionReferenceRemediationPatchProposalService;

require_once dirname(__DIR__) . '/Services/StudioDeletionReferenceRemediationPatchProposalService.php';

$pass=0;$fail=0;$assert=static function(bool $condition,string $message)use(&$pass,&$fail):void{if($condition){$pass++;echo"PASS: {$message}\n";}else{$fail++;echo"FAIL: {$message}\n";}};
$lines=['<?php',"require 'apps/Manufacturing/modules/Products/bootstrap.php';",'// apps/Manufacturing/modules/Products review reference'];
$contents=implode("\n",$lines);
$file=[
 'exists'=>'yes','is_file'=>'yes','is_link'=>'no','readable'=>'yes','text_file'=>'yes','sha256'=>hash('sha256',$contents),
 'size_bytes'=>strlen($contents),'line_count'=>3,'lines'=>$lines,
];
$evidence=static function(int $line,string $text,array $lines):array{$i=$line-1;$previous=$i>0?$lines[$i-1]:'';$next=$i+1<count($lines)?$lines[$i+1]:'';return[
 'line_number'=>$line,'line_sha256'=>hash('sha256',$text),'trimmed_line_sha256'=>hash('sha256',trim($text)),
 'context_sha256'=>hash('sha256',$previous."\n".$text."\n".$next),'matched_patterns'=>['apps/Manufacturing/modules/Products'],
 'match_types'=>['exact path'],'relevance'=>['runtime'],'confidence'=>['high'],
];};
$precondition=static function(string $id,string $severity,int $line,array $file,array $lines)use($evidence):array{return[
 'precondition_id'=>$id,'change_id'=>'change-'.$id,'operation_id'=>'operation-'.$id,'owner_key'=>'Platform/Test','path'=>'config/test.php',
 'severity'=>$severity,'required'=>$severity==='cleanup'?'no':'yes','decision_required'=>$severity==='review'?'yes':'no','change_type'=>'remove_reference',
 'expected_reference_count'=>1,'expected_line_numbers'=>[$line],'expected_file_sha256'=>$file['sha256'],'expected_size_bytes'=>$file['size_bytes'],
 'expected_line_count'=>$file['line_count'],'line_evidence'=>[$evidence($line,$lines[$line-1],$lines)],
];};
$readiness=[
 'effect'=>'verify','reference_remediation_readiness'=>'review_required','preconditions_ready'=>'yes','reference_remediation_ready'=>'no',
 'can_write'=>'no','can_apply'=>'no','can_execute'=>'no','can_archive'=>'no','can_delete'=>'no','grants_execution_authority'=>'no',
 'requires_separate_patch_capability'=>'yes','reference_remediation_readiness_fingerprint'=>'reference-remediation:current',
 'current_packet'=>['snapshot_integrity_fingerprint'=>'snapshot-integrity:current','change_set_fingerprint'=>'change-set:current','plan_fingerprint'=>'plan:current'],
 'target'=>['owner_key'=>'Manufacturing/Products','target_path'=>'apps/Manufacturing/modules/Products'],
 'patch_preconditions'=>[$precondition('blocking','blocking',2,$file,$lines),$precondition('review','review',3,$file,$lines)],
];
$reader=static fn(string $path,?string $root):array=>$file;
$result=StudioDeletionReferenceRemediationPatchProposalService::propose($readiness,null,$reader);
$assert(($result['status']??'')==='ok','proposal result is ok');
$assert(($result['effect']??'')==='plan','proposal declares plan effect');
$assert(($result['patch_proposal_state']??'')==='decision_required','review evidence requires a decision');
$assert(($result['proposals_ready']??'')==='yes','candidate proposals are reviewable');
$assert(($result['all_decisions_resolved']??'')==='no','review decision remains unresolved');
$assert(($result['summary']['proposal_count']??0)===1,'one automatic proposal is generated');
$assert(($result['summary']['decision_required_count']??0)===1,'one review decision item is generated');
$assert(($result['summary']['edit_count']??0)===1,'one exact edit is generated');
$proposal=$result['patch_proposals'][0]??[];
$assert(($proposal['path']??'')==='config/test.php','proposal preserves path');
$assert(($proposal['proposal_state']??'')==='candidate_ready','blocking reference has candidate');
$assert(str_contains((string)($proposal['unified_diff']??''),"-require 'apps/Manufacturing/modules/Products/bootstrap.php';"),'diff contains exact before line');
$assert(str_contains((string)($proposal['unified_diff']??''),"+require '/bootstrap.php';"),'diff contains minimal pattern removal');
$assert(($proposal['apply_policy']['fuzzy_apply_allowed']??'')==='no','fuzzy apply is forbidden');
$decision=$result['decision_items'][0]??[];
$assert(($decision['decision_state']??'')==='decision_required','review item is blocked pending decision');
$assert(count($decision['alternatives']??[])===3,'review item exposes three alternatives');
$assert(($result['can_write']??'')==='no'&&($result['can_apply']??'')==='no','proposal owns no write authority');
$assert(($result['grants_execution_authority']??'')==='no','proposal grants no execution authority');
$assert(str_starts_with((string)($result['patch_proposal_fingerprint']??''),'deletion-reference-patch-proposal:'),'proposal fingerprint is deterministic');

$drift=$file;$drift['sha256']=str_repeat('a',64);
$drifted=StudioDeletionReferenceRemediationPatchProposalService::propose($readiness,null,static fn():array=>$drift);
$assert(($drifted['patch_proposal_state']??'')==='drifted','file hash drift blocks proposal');
$assert(in_array('PATCH_FILE_PRECONDITION_DRIFTED:config/test.php',$drifted['blocking_reasons']??[],true),'file drift reason is explicit');

$bad=$readiness;$bad['can_apply']='yes';
$unknown=StudioDeletionReferenceRemediationPatchProposalService::propose($bad,null,$reader);
$assert(($unknown['patch_proposal_state']??'')==='unknown','over-authoritative readiness fails closed');

$noChanges=$readiness;$noChanges['reference_remediation_readiness']='no_changes';$noChanges['reference_remediation_ready']='yes';$noChanges['patch_preconditions']=[];
$empty=StudioDeletionReferenceRemediationPatchProposalService::propose($noChanges,null,$reader);
$assert(($empty['patch_proposal_state']??'')==='no_changes','empty preconditions produce no-changes state');

$source=file_get_contents(dirname(__DIR__).'/Services/StudioDeletionReferenceRemediationPatchProposalService.php');
$assert(is_string($source)&&!preg_match('/file_put_contents|fwrite|unlink\s*\(|rename\s*\(|copy\s*\(|mkdir\s*\(|rmdir\s*\(|PDO|INSERT\s+INTO|UPDATE\s+.+SET|DELETE\s+FROM/i',$source),'proposal capability contains no mutation implementation');
echo"\nResults: {$pass} passed, {$fail} failed\n";exit($fail>0?1:0);

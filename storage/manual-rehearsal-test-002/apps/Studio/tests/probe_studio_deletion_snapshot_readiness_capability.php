<?php
declare(strict_types=1);

use Apps\Studio\Services\StudioDeletionSnapshotReadinessService;
require_once dirname(__DIR__) . '/Services/StudioDeletionSnapshotReadinessService.php';

$passes=0;$fails=0;
$assert=static function(bool $c,string $m)use(&$passes,&$fails):void{if($c){$passes++;echo"PASS: $m\n";}else{$fails++;echo"FAIL: $m\n";}};
$sort=static function($v)use(&$sort){if(!is_array($v))return $v;if(array_is_list($v))return array_map($sort,$v);ksort($v);foreach($v as $k=>$i)$v[$k]=$sort($i);return $v;};
$fingerprintClaim=static function(array $claim)use($sort):string{
    $material=[
        'version'=>(string)($claim['version']??$claim['claim_version']??''),
        'claim_state'=>(string)($claim['claim_state']??''),
        'claimed_at_utc'=>(string)($claim['claimed_at_utc']??''),
        'target'=>is_array($claim['target']??null)?$claim['target']:[],
        'source'=>is_array($claim['source']??null)?$claim['source']:[],
        'executor'=>is_array($claim['executor']??null)?$claim['executor']:[],
        'reason'=>(string)($claim['reason']??''),
        'single_use_contract'=>is_array($claim['single_use_contract']??null)?$claim['single_use_contract']:[],
    ];
    return 'deletion-execution-claim:'.substr(sha1((string)json_encode($sort($material),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)),0,24);
};

$target=['owner_key'=>'Manufacturing/Products','target_type'=>'owner','target_path'=>'apps/Manufacturing/modules/Products'];
$changeSet=['immutable'=>'yes','can_execute'=>'no','can_apply'=>'no','change_set_fingerprint'=>'deletion-change-set:current','source_plan'=>['fingerprint'=>'deletion-plan:current'],'target'=>$target];
$approval=['effect'=>'verify','execution_readiness'=>'ready','execution_eligible'=>'yes','approval_valid'=>'yes','can_execute'=>'no','can_apply'=>'no','grants_execution_authority'=>'no','readiness_fingerprint'=>'deletion-execution-readiness:current','approval_evidence'=>['record_id'=>'approval-current','record_fingerprint'=>'approval-fp-current']];
$snapshotPath='storage/studio/deletion-execution-snapshots/Manufacturing__Products/deletion-change-set_current/apps__Manufacturing__modules__Products';
$dryRun=[
    'effect'=>'simulate','dry_run_state'=>'ready','simulation_complete'=>'yes','execution_eligible_at_simulation'=>'yes',
    'would_execute'=>'no','would_apply'=>'no','would_write'=>'no','would_archive'=>'no','would_delete'=>'no','can_execute'=>'no','can_apply'=>'no','grants_execution_authority'=>'no','blocking_reasons'=>[],
    'dry_run_fingerprint'=>'deletion-execution-dry-run:current',
    'source'=>['change_set_fingerprint'=>'deletion-change-set:current','plan_fingerprint'=>'deletion-plan:current','readiness_fingerprint'=>'deletion-execution-readiness:current','approval_record_id'=>'approval-current','approval_record_fingerprint'=>'approval-fp-current'],
    'snapshot_plan'=>['required'=>'yes','source_path'=>'apps/Manufacturing/modules/Products','destination_path'=>$snapshotPath,'destination_exists'=>'no','would_create'=>'yes','would_write'=>'no','rollback_source'=>'simulated_only'],
];
$claim=[
    'version'=>'studio.deletion-execution-claim.v1','status'=>'recorded','effect'=>'mutate','claim_version'=>'studio.deletion-execution-claim.v1',
    'claim_id'=>'deletion-execution-claim-current','use_id'=>'deletion-execution-claim-current','use_type'=>'claim','claim_state'=>'claimed','claimed_at_utc'=>'2026-07-17T01:30:00.000000Z','target'=>$target,
    'source'=>['request_id'=>'request-current','request_fingerprint'=>'deletion-execution-request:current','executor_readiness_fingerprint'=>'deletion-executor-readiness:ready-before-claim','change_set_fingerprint'=>'deletion-change-set:current','plan_fingerprint'=>'deletion-plan:current','readiness_fingerprint'=>'deletion-execution-readiness:current','dry_run_fingerprint'=>'deletion-execution-dry-run:current','approval_record_id'=>'approval-current','approval_record_fingerprint'=>'approval-fp-current'],
    'executor'=>['actor_id'=>'executor-42','display_name'=>'Executor 42','authority_role'=>'platform_admin'],
    'reason'=>'I accept responsibility for the governed execution sequence.',
    'single_use_contract'=>['consumes_request'=>'yes','atomic_claim_required'=>'yes','request_must_be_unclaimed'=>'yes','claim_is_execution_authority'=>'no'],
    'immutable'=>'yes','append_only'=>'yes','atomic_single_use'=>'yes','can_execute'=>'no','can_apply'=>'no','can_archive'=>'no','can_delete'=>'no','execution_authorized'=>'no','grants_execution_authority'=>'no','requires_separate_execution_capability'=>'yes',
    'storage'=>['storage_scope'=>'studio_provenance','relative_path'=>'storage/studio/deletion-execution-uses/request-current/claim.json','immutable'=>'yes','append_only'=>'yes','atomic_single_use'=>'yes'],
];
$claim['claim_fingerprint']=$fingerprintClaim($claim);
$executorReadiness=[
    'effect'=>'verify','executor_readiness'=>'consumed','executor_eligible'=>'no','request_valid'=>'yes','executor_identity_valid'=>'no','single_use_available'=>'no',
    'can_claim'=>'no','can_execute'=>'no','can_apply'=>'no','can_archive'=>'no','can_delete'=>'no','grants_execution_authority'=>'no',
    'current_packet'=>['change_set_fingerprint'=>'deletion-change-set:current','plan_fingerprint'=>'deletion-plan:current','readiness_fingerprint'=>'deletion-execution-readiness:current','dry_run_fingerprint'=>'deletion-execution-dry-run:current','approval_record_id'=>'approval-current','approval_record_fingerprint'=>'approval-fp-current','target'=>$target],
    'request_evidence'=>['present'=>'yes','exact_packet_match'=>'yes','request_id'=>'request-current','request_fingerprint'=>'deletion-execution-request:current','request_state'=>'requested','requested_at_utc'=>'2026-07-17T01:00:00.000000Z','expires_at_utc'=>'2026-07-17T02:00:00.000000Z'],
    'single_use_evidence'=>$claim,'blocking_reasons'=>['EXECUTION_REQUEST_ALREADY_USED'],
];
$actor=['user_id'=>'executor-42','display_name'=>'Executor 42','authority_role'=>'platform_admin'];
$clock=static fn()=>new DateTimeImmutable('2026-07-17T01:40:00Z');
$inspector=static function(string $path,string $kind)use($snapshotPath):array{
    if($path===$snapshotPath)return ['path'=>$path,'kind'=>$kind,'exists'=>'no','is_file'=>'no','is_dir'=>'no','readable'=>'no','writable_parent'=>'yes'];
    return ['path'=>$path,'kind'=>$kind,'exists'=>'yes','is_file'=>'no','is_dir'=>'yes','readable'=>'yes','writable_parent'=>'yes'];
};
$ready=StudioDeletionSnapshotReadinessService::assess($changeSet,$approval,$dryRun,$executorReadiness,$claim,$actor,null,$inspector,$clock);
$assert(($ready['status']??'')==='ok','ready result is ok');
$assert(($ready['effect']??'')==='verify','effect is verify');
$assert(($ready['snapshot_readiness']??'')==='ready','valid claim yields ready');
$assert(($ready['snapshot_ready']??'')==='yes','snapshot ready yes');
$assert(($ready['claim_valid']??'')==='yes','claim valid yes');
$assert(($ready['executor_identity_valid']??'')==='yes','executor identity valid');
$assert(($ready['source_available']??'')==='yes','source available');
$assert(($ready['destination_available']??'')==='yes','destination available');
$assert(($ready['can_snapshot']??'')==='no','verifier cannot snapshot');
$assert(($ready['would_write']??'')==='no','verifier would not write');
$assert(($ready['would_archive']??'')==='no','verifier would not archive');
$assert(($ready['would_delete']??'')==='no','verifier would not delete');
$assert(($ready['grants_execution_authority']??'')==='no','verifier grants no authority');
$assert(($ready['requires_separate_snapshot_capability']??'')==='yes','separate snapshot capability required');
$assert(($ready['claim_evidence']['claim_id']??'')==='deletion-execution-claim-current','claim evidence exposed');
$assert(($ready['snapshot_contract']['destination_path']??'')===$snapshotPath,'snapshot destination preserved');
$assert(($ready['snapshot_contract']['overwrite_allowed']??'')==='no','overwrite prohibited');
$assert(count($ready['preflight_checks']??[])===4,'four preflight checks emitted');
$assert(($ready['blocking_reasons']??[])===[],'ready has no blockers');
$assert(str_starts_with((string)($ready['snapshot_readiness_fingerprint']??''),'deletion-snapshot-readiness:'),'readiness fingerprint emitted');

$missing=StudioDeletionSnapshotReadinessService::assess($changeSet,$approval,$dryRun,$executorReadiness,null,$actor,null,$inspector,$clock);
$assert(($missing['snapshot_readiness']??'')==='not_claimed','missing claim yields not claimed');
$assert(in_array('EXECUTION_CLAIM_REQUIRED',$missing['blocking_reasons']??[],true),'missing claim blocker explicit');
$staleClaim=$claim;$staleClaim['source']['dry_run_fingerprint']='old';$staleClaim['claim_fingerprint']=$fingerprintClaim($staleClaim);$staleER=$executorReadiness;$staleER['single_use_evidence']=$staleClaim;
$stale=StudioDeletionSnapshotReadinessService::assess($changeSet,$approval,$dryRun,$staleER,$staleClaim,$actor,null,$inspector,$clock);
$assert(($stale['snapshot_readiness']??'')==='stale','stale claim yields stale');
$assert(in_array('CLAIM_SOURCE_MISMATCH:DRY_RUN_FINGERPRINT',$stale['blocking_reasons']??[],true),'stale dry-run binding explicit');
$tampered=$claim;$tampered['reason']='tampered';$blockedER=$executorReadiness;$blockedER['single_use_evidence']=$tampered;
$blocked=StudioDeletionSnapshotReadinessService::assess($changeSet,$approval,$dryRun,$blockedER,$tampered,$actor,null,$inspector,$clock);
$assert(($blocked['snapshot_readiness']??'')==='blocked','tampered claim blocked');
$assert(in_array('CLAIM_FINGERPRINT_INVALID',$blocked['blocking_reasons']??[],true),'claim fingerprint failure explicit');
$wrongActor=['user_id'=>'other','display_name'=>'Other','authority_role'=>'platform_admin'];
$wrong=StudioDeletionSnapshotReadinessService::assess($changeSet,$approval,$dryRun,$executorReadiness,$claim,$wrongActor,null,$inspector,$clock);
$assert(($wrong['snapshot_readiness']??'')==='wrong_executor','wrong actor state explicit');
$assert(($wrong['claim_valid']??'')==='yes','wrong actor does not invalidate claim');
$assert(in_array('CURRENT_ACTOR_NOT_CLAIM_EXECUTOR',$wrong['blocking_reasons']??[],true),'wrong executor blocker explicit');
$expired=StudioDeletionSnapshotReadinessService::assess($changeSet,$approval,$dryRun,$executorReadiness,$claim,$actor,null,$inspector,static fn()=>new DateTimeImmutable('2026-07-17T02:00:00Z'));
$assert(($expired['snapshot_readiness']??'')==='expired','expired request blocks snapshot');
$existsInspector=static fn(string $path,string $kind):array=>['path'=>$path,'kind'=>$kind,'exists'=>'yes','is_file'=>'no','is_dir'=>'yes','readable'=>'yes','writable_parent'=>'yes'];
$exists=StudioDeletionSnapshotReadinessService::assess($changeSet,$approval,$dryRun,$executorReadiness,$claim,$actor,null,$existsInspector,$clock);
$assert(($exists['snapshot_readiness']??'')==='snapshot_exists','existing destination gets dedicated state');
$assert(($exists['destination_available']??'')==='no','existing destination unavailable');
$missingInspector=static function(string $path,string $kind)use($snapshotPath):array{return ['path'=>$path,'kind'=>$kind,'exists'=>$path===$snapshotPath?'no':'no','is_file'=>'no','is_dir'=>'no','readable'=>'no','writable_parent'=>'yes'];};
$targetMissing=StudioDeletionSnapshotReadinessService::assess($changeSet,$approval,$dryRun,$executorReadiness,$claim,$actor,null,$missingInspector,$clock);
$assert(($targetMissing['snapshot_readiness']??'')==='target_missing','missing source gets dedicated state');
$assert(($targetMissing['source_available']??'')==='no','missing source unavailable');
$unsafeDry=$dryRun;$unsafeDry['snapshot_plan']['destination_path']='apps/Manufacturing';
$unknown=StudioDeletionSnapshotReadinessService::assess($changeSet,$approval,$unsafeDry,$executorReadiness,$claim,$actor,null,$inspector,$clock);
$assert(($unknown['snapshot_readiness']??'')==='unknown','unsafe snapshot plan fails packet');
$assert(in_array('SNAPSHOT_DESTINATION_UNSAFE',$unknown['blocking_reasons']??[],true),'unsafe destination blocker explicit');
$source=file_get_contents(dirname(__DIR__) . '/Services/StudioDeletionSnapshotReadinessService.php');
$assert(is_string($source)&&!preg_match('/file_put_contents|fwrite|unlink\s*\(|rename\s*\(|mkdir\s*\(|rmdir\s*\(|copy\s*\(|PDO|INSERT\s+INTO|UPDATE\s+.+SET|DELETE\s+FROM/i',$source),'service has no mutation implementation');

echo"\nResults: $passes passed, $fails failed\n";exit($fails?1:0);

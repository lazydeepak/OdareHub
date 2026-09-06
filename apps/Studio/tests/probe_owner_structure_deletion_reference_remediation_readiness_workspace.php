<?php
declare(strict_types=1);

use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureDeletionReferenceRemediationReadinessWorkspaceService;

require_once dirname(__DIR__) . '/Tools/OwnerStructureScan/Services/OwnerStructureDeletionReferenceRemediationReadinessWorkspaceService.php';

$pass = 0; $fail = 0;
$assert = static function (bool $condition, string $message) use (&$pass, &$fail): void {
    if ($condition) { $pass++; echo "PASS: {$message}\n"; return; }
    $fail++; echo "FAIL: {$message}\n";
};
$owners = [['owner_key'=>'Manufacturing/Products','owner_type'=>'module','root_path'=>'apps/Manufacturing/modules/Products']];
$changeSet = ['target'=>['owner_key'=>'Manufacturing/Products','target_path'=>'apps/Manufacturing/modules/Products']];
$integrity = ['snapshot_integrity'=>'ready','snapshot_integrity_fingerprint'=>'integrity-current'];
$assessment = [
    'status'=>'ok','effect'=>'verify','reference_remediation_readiness'=>'ready',
    'preconditions_ready'=>'yes','reference_remediation_ready'=>'yes','requires_review_decision'=>'no',
    'source'=>['snapshot_integrity_fingerprint'=>'integrity-current'],
    'target'=>$changeSet['target'],
    'summary'=>['planned_file_change_count'=>1],
    'patch_preconditions'=>[['precondition_id'=>'precondition-1','path'=>'apps/X/file.php']],
    'file_checks'=>[['path'=>'apps/X/file.php','sha256'=>str_repeat('a',64)]],
    'blocking_reasons'=>[],
    'reference_remediation_readiness_fingerprint'=>'remediation-ready-1',
    'diagnostics'=>[],
];
$calls = 0;
$assessor = static function (array $owner, array $packet, array $snapshot, ?string $root, ?callable $resolver, ?callable $inspector) use (&$calls, $assessment): array {
    $calls++;
    if (($owner['owner_key'] ?? '') !== 'Manufacturing/Products') { throw new RuntimeException('wrong owner'); }
    if (($packet['target']['owner_key'] ?? '') !== 'Manufacturing/Products') { throw new RuntimeException('wrong packet'); }
    if (($snapshot['snapshot_integrity_fingerprint'] ?? '') !== 'integrity-current') { throw new RuntimeException('wrong integrity'); }
    return $assessment;
};
$idle = OwnerStructureDeletionReferenceRemediationReadinessWorkspaceService::build($owners,'Manufacturing/Products',false,$changeSet,$integrity,null,null,null,$assessor);
$assert(($idle['status'] ?? '') === 'idle','idle workspace stays idle');
$assert(($idle['assessment'] ?? null) === null,'idle workspace has no assessment');
$assert($calls === 0,'idle workspace does not call assessor');
$ready = OwnerStructureDeletionReferenceRemediationReadinessWorkspaceService::build($owners,'Manufacturing/Products',true,$changeSet,$integrity,null,null,null,$assessor);
$assert(($ready['status'] ?? '') === 'ready','requested workspace renders ready');
$assert($calls === 1,'assessor called once');
$assert(($ready['reference_remediation_readiness'] ?? '') === 'ready','readiness state exposed');
$assert(($ready['preconditions_ready'] ?? '') === 'yes','precondition readiness exposed');
$assert(($ready['reference_remediation_ready'] ?? '') === 'yes','remediation readiness exposed');
$assert(($ready['requires_review_decision'] ?? '') === 'no','review decision state exposed');
$assert(($ready['source']['snapshot_integrity_fingerprint'] ?? '') === 'integrity-current','source binding exposed');
$assert(($ready['target']['owner_key'] ?? '') === 'Manufacturing/Products','target exposed');
$assert(($ready['summary']['planned_file_change_count'] ?? 0) === 1,'summary exposed');
$assert(($ready['patch_preconditions'][0]['precondition_id'] ?? '') === 'precondition-1','preconditions exposed');
$assert(($ready['file_checks'][0]['path'] ?? '') === 'apps/X/file.php','file checks exposed');
$assert(($ready['reference_remediation_readiness_fingerprint'] ?? '') === 'remediation-ready-1','fingerprint exposed');
$assert(($ready['blocking_reasons'] ?? []) === [],'ready workspace has no blockers');
$missingOwner = OwnerStructureDeletionReferenceRemediationReadinessWorkspaceService::build($owners,'Missing/Owner',true,$changeSet,$integrity);
$assert(($missingOwner['status'] ?? '') === 'error','missing owner fails safely');
$assert(($missingOwner['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_REFERENCE_REMEDIATION_OWNER_UNAVAILABLE','missing owner diagnostic explicit');
$missingPacket = OwnerStructureDeletionReferenceRemediationReadinessWorkspaceService::build($owners,'Manufacturing/Products',true,null,$integrity);
$assert(($missingPacket['status'] ?? '') === 'error','missing change set fails safely');
$assert(($missingPacket['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_REFERENCE_REMEDIATION_PREREQUISITES_REQUIRED','missing prerequisite diagnostic explicit');
$wrong = $changeSet; $wrong['target']['owner_key']='Other/Owner';
$wrongPacket = OwnerStructureDeletionReferenceRemediationReadinessWorkspaceService::build($owners,'Manufacturing/Products',true,$wrong,$integrity);
$assert(($wrongPacket['status'] ?? '') === 'error','wrong packet owner fails safely');
$assert(($wrongPacket['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_REFERENCE_REMEDIATION_PACKET_INVALID','wrong packet diagnostic explicit');
$invalid = OwnerStructureDeletionReferenceRemediationReadinessWorkspaceService::build($owners,'Manufacturing/Products',true,$changeSet,$integrity,null,null,null,static fn(): string => 'bad');
$assert(($invalid['status'] ?? '') === 'error','invalid assessor result fails safely');
$assert(($invalid['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_REFERENCE_REMEDIATION_RESULT_INVALID','invalid result diagnostic explicit');
$thrown = OwnerStructureDeletionReferenceRemediationReadinessWorkspaceService::build($owners,'Manufacturing/Products',true,$changeSet,$integrity,null,null,null,static function (): array { throw new RuntimeException('workspace failure'); });
$assert(($thrown['status'] ?? '') === 'error','assessor exception fails safely');
$assert(($thrown['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_REFERENCE_REMEDIATION_FAILED','exception diagnostic explicit');
$assert(($thrown['diagnostics'][0]['message'] ?? '') === 'workspace failure','exception message preserved');
$postludes=['preview.postlude.zzzzzzzzzzz-deletion-snapshot-integrity.php','preview.postlude.zzzzzzzzzzzz-deletion-reference-remediation-readiness.php'];
sort($postludes,SORT_NATURAL|SORT_FLAG_CASE);
$assert($postludes[1] === 'preview.postlude.zzzzzzzzzzzz-deletion-reference-remediation-readiness.php','remediation postlude loads after snapshot integrity');
$source=file_get_contents(dirname(__DIR__).'/Tools/OwnerStructureScan/Services/OwnerStructureDeletionReferenceRemediationReadinessWorkspaceService.php');
$assert(is_string($source) && !preg_match('/file_put_contents|fwrite|unlink\s*\(|rename\s*\(|mkdir\s*\(|rmdir\s*\(|copy\s*\(|PDO|INSERT\s+INTO|UPDATE\s+.+SET|DELETE\s+FROM/i',$source),'workspace contains no mutation implementation');
echo "\nResults: {$pass} passed, {$fail} failed\n"; exit($fail>0?1:0);

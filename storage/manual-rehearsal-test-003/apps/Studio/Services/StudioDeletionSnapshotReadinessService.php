<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

/** Read-only claim and deterministic snapshot preflight verifier. */
final class StudioDeletionSnapshotReadinessService
{
    public const EFFECT = 'verify';
    public const VERSION = 'studio.deletion-snapshot-readiness.v1';
    public const STATE_READY = 'ready';
    public const STATE_NOT_CLAIMED = 'not_claimed';
    public const STATE_STALE = 'stale';
    public const STATE_EXPIRED = 'expired';
    public const STATE_WRONG_EXECUTOR = 'wrong_executor';
    public const STATE_SNAPSHOT_EXISTS = 'snapshot_exists';
    public const STATE_TARGET_MISSING = 'target_missing';
    public const STATE_BLOCKED = 'blocked';
    public const STATE_UNKNOWN = 'unknown';

    /** @return array<string,mixed> */
    public static function assess(
        array $changeSet,
        array $approvalReadiness,
        array $dryRun,
        array $executorReadiness,
        ?array $claim,
        ?array $actor,
        ?string $root = null,
        ?callable $inspector = null,
        ?callable $clock = null
    ): array {
        $target = self::arr($changeSet, 'target');
        $plan = self::arr($dryRun, 'snapshot_plan');
        $request = self::arr($executorReadiness, 'request_evidence');
        $current = self::arr($executorReadiness, 'current_packet');
        $ownerKey = trim((string)($target['owner_key'] ?? ''));
        $targetPath = self::path((string)($target['target_path'] ?? ''));
        $snapshotPath = self::path((string)($plan['destination_path'] ?? ''));
        $source = [
            'change_set_fingerprint' => trim((string)($changeSet['change_set_fingerprint'] ?? '')),
            'plan_fingerprint' => trim((string)($changeSet['source_plan']['fingerprint'] ?? '')),
            'readiness_fingerprint' => trim((string)($approvalReadiness['readiness_fingerprint'] ?? '')),
            'dry_run_fingerprint' => trim((string)($dryRun['dry_run_fingerprint'] ?? '')),
            'approval_record_id' => trim((string)($approvalReadiness['approval_evidence']['record_id'] ?? '')),
            'approval_record_fingerprint' => trim((string)($approvalReadiness['approval_evidence']['record_fingerprint'] ?? '')),
            'request_id' => trim((string)($request['request_id'] ?? '')),
            'request_fingerprint' => trim((string)($request['request_fingerprint'] ?? '')),
        ];

        $packetProblems = self::packetProblems($changeSet, $approvalReadiness, $dryRun, $executorReadiness, $source, $ownerKey, $targetPath, $snapshotPath, $plan, $request, $current);
        if ($packetProblems !== []) {
            return self::result(self::STATE_UNKNOWN, $target, $source, $claim, $actor, $plan, [], [], $packetProblems, [self::diag('DELETION_SNAPSHOT_READINESS_PACKET_INVALID', 'Snapshot readiness requires the exact current approved, simulated, requested, and claimed deletion packet.', ['reasons' => $packetProblems])]);
        }
        if ($claim === null) {
            return self::result(self::STATE_NOT_CLAIMED, $target, $source, null, $actor, $plan, [], [], ['EXECUTION_CLAIM_REQUIRED'], [self::diag('DELETION_SNAPSHOT_READINESS_CLAIM_MISSING', 'No immutable claim exists for the current execution request.')]);
        }

        $claimProblems = self::claimProblems($claim, $source, $ownerKey, $targetPath);
        if ($claimProblems !== []) {
            $stale = self::hasPrefix($claimProblems, 'CLAIM_SOURCE_MISMATCH:') || in_array('CLAIM_TARGET_MISMATCH', $claimProblems, true);
            return self::result($stale ? self::STATE_STALE : self::STATE_BLOCKED, $target, $source, $claim, $actor, $plan, [], [], $claimProblems, [self::diag($stale ? 'DELETION_SNAPSHOT_READINESS_CLAIM_STALE' : 'DELETION_SNAPSHOT_READINESS_CLAIM_INVALID', $stale ? 'The claim is bound to an older deletion packet.' : 'The claim failed integrity, provenance, or authority-boundary validation.', ['reasons' => $claimProblems])]);
        }

        $use = self::arr($executorReadiness, 'single_use_evidence');
        if ($use === []
            || (string)($use['claim_id'] ?? $use['use_id'] ?? '') !== (string)($claim['claim_id'] ?? '')
            || (string)($use['claim_fingerprint'] ?? '') !== (string)($claim['claim_fingerprint'] ?? '')) {
            return self::result(self::STATE_BLOCKED, $target, $source, $claim, $actor, $plan, [], [], ['CLAIM_USE_EVIDENCE_MISMATCH'], [self::diag('DELETION_SNAPSHOT_READINESS_USE_EVIDENCE_INVALID', 'Executor readiness does not expose the exact immutable claim as its consumed single-use evidence.')]);
        }

        $now = self::now($clock);
        $claimedAt = self::utc((string)($claim['claimed_at_utc'] ?? ''));
        $expiresAt = self::utc((string)($request['expires_at_utc'] ?? ''));
        if ($claimedAt === null || $expiresAt === null || $claimedAt > $now || $claimedAt > $expiresAt) {
            return self::result(self::STATE_BLOCKED, $target, $source, $claim, $actor, $plan, [], [], ['CLAIM_TIME_CONTRACT_INVALID'], [self::diag('DELETION_SNAPSHOT_READINESS_CLAIM_TIME_INVALID', 'Claim time does not fit the execution request validity window.')], true);
        }
        if ($expiresAt <= $now) {
            return self::result(self::STATE_EXPIRED, $target, $source, $claim, $actor, $plan, [], [], ['EXECUTION_REQUEST_EXPIRED'], [self::diag('DELETION_SNAPSHOT_READINESS_REQUEST_EXPIRED', 'The execution request expired before snapshot preparation.')], true);
        }

        $claimExecutor = self::arr($claim, 'executor');
        $actorRecord = self::actor($actor ?? []);
        $matches = $actorRecord['actor_id'] !== ''
            && (string)($claimExecutor['actor_id'] ?? '') !== ''
            && hash_equals((string)$claimExecutor['actor_id'], $actorRecord['actor_id']);
        if (!self::isPlatformAdmin($actor) || !$matches) {
            $reasons = [];
            if (!self::isPlatformAdmin($actor)) { $reasons[] = 'CURRENT_ACTOR_NOT_PLATFORM_ADMIN'; }
            if (!$matches) { $reasons[] = 'CURRENT_ACTOR_NOT_CLAIM_EXECUTOR'; }
            return self::result(self::STATE_WRONG_EXECUTOR, $target, $source, $claim, $actor, $plan, [], [], $reasons, [self::diag('DELETION_SNAPSHOT_READINESS_WRONG_EXECUTOR', 'The current actor is not the platform-admin executor who owns the immutable claim.')], true);
        }

        $repo = self::root($root);
        $inspect = $inspector ?? static function (string $relativePath, string $kind) use ($repo): array {
            $absolute = $repo . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            return [
                'path' => $relativePath,
                'kind' => $kind,
                'exists' => file_exists($absolute) ? 'yes' : 'no',
                'is_file' => is_file($absolute) ? 'yes' : 'no',
                'is_dir' => is_dir($absolute) ? 'yes' : 'no',
                'readable' => is_readable($absolute) ? 'yes' : 'no',
                'writable_parent' => is_writable(dirname($absolute)) ? 'yes' : 'no',
            ];
        };
        $targetState = $inspect($targetPath, 'snapshot_source');
        $snapshotState = $inspect($snapshotPath, 'snapshot_destination');
        $checks = self::checks($targetPath, $snapshotPath, $targetState, $snapshotState);

        if ((string)($targetState['exists'] ?? 'no') !== 'yes') {
            return self::result(self::STATE_TARGET_MISSING, $target, $source, $claim, $actor, $plan, $targetState, $snapshotState, ['SNAPSHOT_SOURCE_MISSING'], [self::diag('DELETION_SNAPSHOT_READINESS_TARGET_MISSING', 'The claimed deletion target no longer exists, so a rollback snapshot cannot be prepared.')], true, true, $checks);
        }
        if ((string)($targetState['readable'] ?? 'no') !== 'yes') {
            return self::result(self::STATE_BLOCKED, $target, $source, $claim, $actor, $plan, $targetState, $snapshotState, ['SNAPSHOT_SOURCE_UNREADABLE'], [self::diag('DELETION_SNAPSHOT_READINESS_TARGET_UNREADABLE', 'The claimed deletion target is not readable for snapshot preparation.')], true, true, $checks);
        }
        if ((string)($snapshotState['exists'] ?? 'no') === 'yes') {
            return self::result(self::STATE_SNAPSHOT_EXISTS, $target, $source, $claim, $actor, $plan, $targetState, $snapshotState, ['SNAPSHOT_DESTINATION_ALREADY_EXISTS'], [self::diag('DELETION_SNAPSHOT_READINESS_DESTINATION_EXISTS', 'The deterministic snapshot destination already exists and cannot be overwritten.')], true, true, $checks);
        }
        return self::result(self::STATE_READY, $target, $source, $claim, $actor, $plan, $targetState, $snapshotState, [], [], true, true, $checks);
    }

    /** @return array<int,string> */
    private static function packetProblems(array $changeSet, array $approval, array $dryRun, array $executor, array $source, string $ownerKey, string $targetPath, string $snapshotPath, array $plan, array $request, array $current): array
    {
        $p = [];
        if ($ownerKey === '' || $targetPath === '' || $snapshotPath === '' || in_array('', array_values($source), true)) { $p[] = 'CURRENT_PACKET_IDENTITY_MISSING'; }
        if ((string)($changeSet['immutable'] ?? 'no') !== 'yes' || (string)($changeSet['can_execute'] ?? 'yes') !== 'no' || (string)($changeSet['can_apply'] ?? 'yes') !== 'no') { $p[] = 'CHANGE_SET_CONTRACT_INVALID'; }
        if ((string)($approval['effect'] ?? '') !== 'verify' || (string)($approval['execution_readiness'] ?? '') !== 'ready' || (string)($approval['execution_eligible'] ?? 'no') !== 'yes' || (string)($approval['approval_valid'] ?? 'no') !== 'yes') { $p[] = 'APPROVAL_READINESS_INVALID'; }
        foreach (['can_execute','can_apply','grants_execution_authority'] as $f) { if ((string)($approval[$f] ?? 'yes') !== 'no') { $p[] = 'APPROVAL_READINESS_AUTHORITY_INVALID:' . $f; } }
        if ((string)($dryRun['effect'] ?? '') !== 'simulate' || (string)($dryRun['dry_run_state'] ?? '') !== 'ready' || (string)($dryRun['simulation_complete'] ?? 'no') !== 'yes' || (string)($dryRun['execution_eligible_at_simulation'] ?? 'no') !== 'yes') { $p[] = 'DRY_RUN_INVALID'; }
        foreach (['would_execute','would_apply','would_write','would_archive','would_delete','can_execute','can_apply','grants_execution_authority'] as $f) { if ((string)($dryRun[$f] ?? 'yes') !== 'no') { $p[] = 'DRY_RUN_AUTHORITY_INVALID:' . $f; } }
        if (self::strings($dryRun['blocking_reasons'] ?? []) !== []) { $p[] = 'DRY_RUN_BLOCKERS_PRESENT'; }
        if ((string)($executor['effect'] ?? '') !== 'verify' || (string)($executor['executor_readiness'] ?? '') !== 'consumed' || (string)($executor['request_valid'] ?? 'no') !== 'yes' || (string)($executor['single_use_available'] ?? 'yes') !== 'no') { $p[] = 'CLAIM_CONSUMPTION_STATE_INVALID'; }
        foreach (['can_claim','can_execute','can_apply','can_archive','can_delete','grants_execution_authority'] as $f) { if ((string)($executor[$f] ?? 'yes') !== 'no') { $p[] = 'EXECUTOR_READINESS_AUTHORITY_INVALID:' . $f; } }
        if ((string)($request['present'] ?? 'no') !== 'yes' || (string)($request['exact_packet_match'] ?? 'no') !== 'yes' || (string)($request['request_state'] ?? '') !== 'requested') { $p[] = 'EXECUTION_REQUEST_EVIDENCE_INVALID'; }
        foreach (array_diff_key($source, ['request_id'=>true,'request_fingerprint'=>true]) as $field => $value) {
            if ((string)($current[$field] ?? '') !== $value) { $p[] = 'CURRENT_PACKET_MISMATCH:' . strtoupper($field); }
        }
        $drySource = self::arr($dryRun, 'source');
        foreach (['change_set_fingerprint','plan_fingerprint','readiness_fingerprint','approval_record_id','approval_record_fingerprint'] as $field) {
            if ((string)($drySource[$field] ?? '') !== $source[$field]) { $p[] = 'DRY_RUN_SOURCE_MISMATCH'; break; }
        }
        if ((string)($plan['required'] ?? 'no') !== 'yes' || self::path((string)($plan['source_path'] ?? '')) !== $targetPath || self::path((string)($plan['destination_path'] ?? '')) !== $snapshotPath || (string)($plan['would_create'] ?? 'no') !== 'yes' || (string)($plan['would_write'] ?? 'yes') !== 'no') { $p[] = 'SNAPSHOT_PLAN_INVALID'; }
        if (!str_starts_with($snapshotPath, 'storage/studio/deletion-execution-snapshots/') || $snapshotPath === $targetPath || str_starts_with($targetPath . '/', $snapshotPath . '/')) { $p[] = 'SNAPSHOT_DESTINATION_UNSAFE'; }
        return array_values(array_unique($p));
    }

    /** @return array<int,string> */
    private static function claimProblems(array $claim, array $source, string $ownerKey, string $targetPath): array
    {
        $p = [];
        $claimSource = self::arr($claim, 'source');
        $claimTarget = self::arr($claim, 'target');
        $executor = self::arr($claim, 'executor');
        $contract = self::arr($claim, 'single_use_contract');
        $storage = self::arr($claim, 'storage');
        if ((string)($claim['status'] ?? '') !== 'recorded' || (string)($claim['claim_state'] ?? '') !== 'claimed' || (string)($claim['use_type'] ?? '') !== 'claim') { $p[] = 'CLAIM_STATE_INVALID'; }
        if ((string)($claim['claim_version'] ?? $claim['version'] ?? '') !== 'studio.deletion-execution-claim.v1') { $p[] = 'CLAIM_VERSION_INVALID'; }
        if ((string)($claim['immutable'] ?? 'no') !== 'yes' || (string)($claim['append_only'] ?? 'no') !== 'yes' || (string)($claim['atomic_single_use'] ?? 'no') !== 'yes') { $p[] = 'CLAIM_MUTABILITY_INVALID'; }
        foreach (['can_execute','can_apply','can_archive','can_delete','execution_authorized','grants_execution_authority'] as $f) { if ((string)($claim[$f] ?? 'yes') !== 'no') { $p[] = 'CLAIM_AUTHORITY_INVALID:' . $f; } }
        if ((string)($claim['requires_separate_execution_capability'] ?? '') !== 'yes') { $p[] = 'CLAIM_EXECUTION_SEPARATION_INVALID'; }
        foreach ($source as $field => $value) { if ((string)($claimSource[$field] ?? '') !== $value) { $p[] = 'CLAIM_SOURCE_MISMATCH:' . strtoupper($field); } }
        if ((string)($claimTarget['owner_key'] ?? '') !== $ownerKey || self::path((string)($claimTarget['target_path'] ?? '')) !== $targetPath) { $p[] = 'CLAIM_TARGET_MISMATCH'; }
        if ((string)($executor['authority_role'] ?? '') !== 'platform_admin' || trim((string)($executor['actor_id'] ?? '')) === '') { $p[] = 'CLAIM_EXECUTOR_INVALID'; }
        if (trim((string)($claimSource['executor_readiness_fingerprint'] ?? '')) === '') { $p[] = 'CLAIM_EXECUTOR_READINESS_IDENTITY_MISSING'; }
        if ((string)($contract['consumes_request'] ?? '') !== 'yes' || (string)($contract['atomic_claim_required'] ?? '') !== 'yes' || (string)($contract['request_must_be_unclaimed'] ?? '') !== 'yes' || (string)($contract['claim_is_execution_authority'] ?? 'yes') !== 'no') { $p[] = 'CLAIM_SINGLE_USE_CONTRACT_INVALID'; }
        if ((string)($storage['storage_scope'] ?? '') !== 'studio_provenance' || (string)($storage['immutable'] ?? 'no') !== 'yes' || (string)($storage['append_only'] ?? 'no') !== 'yes' || (string)($storage['atomic_single_use'] ?? 'no') !== 'yes') { $p[] = 'CLAIM_STORAGE_INVALID'; }
        if (!hash_equals(self::claimFingerprint($claim), (string)($claim['claim_fingerprint'] ?? ''))) { $p[] = 'CLAIM_FINGERPRINT_INVALID'; }
        return array_values(array_unique($p));
    }

    private static function claimFingerprint(array $claim): string
    {
        $material = [
            'version' => (string)($claim['version'] ?? $claim['claim_version'] ?? ''),
            'claim_state' => (string)($claim['claim_state'] ?? ''),
            'claimed_at_utc' => (string)($claim['claimed_at_utc'] ?? ''),
            'target' => self::arr($claim, 'target'),
            'source' => self::arr($claim, 'source'),
            'executor' => self::arr($claim, 'executor'),
            'reason' => (string)($claim['reason'] ?? ''),
            'single_use_contract' => self::arr($claim, 'single_use_contract'),
        ];
        return 'deletion-execution-claim:' . substr(sha1(self::json($material)), 0, 24);
    }

    /** @return array<int,array<string,mixed>> */
    private static function checks(string $sourcePath, string $destinationPath, array $source, array $destination): array
    {
        return [
            ['check_id'=>'snapshot-readiness:claim-valid','check_type'=>'claim_valid','expected'=>'yes','actual'=>'yes','passed'=>'yes','blocking'=>'yes'],
            ['check_id'=>'snapshot-readiness:source-exists','check_type'=>'snapshot_source_exists','path'=>$sourcePath,'expected'=>'yes','actual'=>(string)($source['exists']??'unknown'),'passed'=>(string)($source['exists']??'no')==='yes'?'yes':'no','blocking'=>'yes'],
            ['check_id'=>'snapshot-readiness:source-readable','check_type'=>'snapshot_source_readable','path'=>$sourcePath,'expected'=>'yes','actual'=>(string)($source['readable']??'unknown'),'passed'=>(string)($source['readable']??'no')==='yes'?'yes':'no','blocking'=>'yes'],
            ['check_id'=>'snapshot-readiness:destination-available','check_type'=>'snapshot_destination_available','path'=>$destinationPath,'expected'=>'absent','actual'=>(string)($destination['exists']??'unknown')==='yes'?'exists':'absent','passed'=>(string)($destination['exists']??'no')==='yes'?'no':'yes','blocking'=>'yes'],
        ];
    }

    /** @return array<string,mixed> */
    private static function result(string $state, array $target, array $source, ?array $claim, ?array $actor, array $plan, array $targetState, array $snapshotState, array $reasons, array $diagnostics, bool $claimValid=false, bool $executorValid=false, array $checks=[]): array
    {
        $ready = $state === self::STATE_READY;
        $actorRecord = self::actor($actor ?? []);
        $contract = [
            'required'=>(string)($plan['required']??'yes'),
            'source_path'=>self::path((string)($plan['source_path']??$target['target_path']??'')),
            'destination_path'=>self::path((string)($plan['destination_path']??'')),
            'source_exists'=>(string)($targetState['exists']??'unknown'),
            'source_readable'=>(string)($targetState['readable']??'unknown'),
            'destination_exists'=>(string)($snapshotState['exists']??'unknown'),
            'archive_mode'=>'copy_only','overwrite_allowed'=>'no','requires_claim_fingerprint'=>'yes','requires_post_write_manifest'=>'yes',
        ];
        $material = ['version'=>self::VERSION,'state'=>$state,'source'=>$source,'target'=>$target,'claim_id'=>(string)($claim['claim_id']??''),'claim_fingerprint'=>(string)($claim['claim_fingerprint']??''),'actor'=>$actorRecord,'snapshot_contract'=>$contract,'blocking_reasons'=>array_values(array_unique($reasons))];
        return [
            'status'=>in_array($state,[self::STATE_UNKNOWN,self::STATE_BLOCKED],true)?'partial':'ok',
            'effect'=>self::EFFECT,'snapshot_readiness_version'=>self::VERSION,'snapshot_readiness'=>$state,'snapshot_ready'=>$ready?'yes':'no',
            'claim_valid'=>$claimValid?'yes':'no','executor_identity_valid'=>$executorValid?'yes':'no',
            'source_available'=>(string)($targetState['exists']??'no')==='yes'&&(string)($targetState['readable']??'no')==='yes'?'yes':'no',
            'destination_available'=>(string)($snapshotState['exists']??'yes')==='no'?'yes':'no',
            'can_snapshot'=>'no','would_write'=>'no','would_archive'=>'no','would_delete'=>'no','can_execute'=>'no','can_apply'=>'no','can_archive'=>'no','can_delete'=>'no','grants_execution_authority'=>'no',
            'requires_separate_snapshot_capability'=>'yes','requires_separate_execution_capability'=>'yes',
            'current_packet'=>array_merge($source,['target'=>$target]),
            'claim_evidence'=>['present'=>is_array($claim)?'yes':'no','claim_id'=>(string)($claim['claim_id']??''),'claim_fingerprint'=>(string)($claim['claim_fingerprint']??''),'claimed_at_utc'=>(string)($claim['claimed_at_utc']??''),'executor'=>is_array($claim)?self::arr($claim,'executor'):[],'storage'=>is_array($claim)?self::arr($claim,'storage'):[]],
            'actor_evidence'=>$actorRecord,'snapshot_contract'=>$contract,'preflight_checks'=>$checks,'blocking_reasons'=>array_values(array_unique($reasons)),
            'snapshot_readiness_fingerprint'=>'deletion-snapshot-readiness:'.substr(sha1(self::json($material)),0,24),'diagnostics'=>$diagnostics,
        ];
    }

    private static function hasPrefix(array $values,string $prefix):bool { foreach($values as $v){if(str_starts_with((string)$v,$prefix))return true;}return false; }
    private static function isPlatformAdmin(?array $actor):bool { return is_array($actor)&&strtolower(trim((string)($actor['authority_role']??'')))==='platform_admin'; }
    /** @return array<string,string> */
    private static function actor(array $a):array { $id=trim((string)($a['user_id']??$a['id']??$a['actor_id']??''));return ['actor_id'=>$id,'display_name'=>trim((string)($a['display_name']??$a['name']??$a['email']??$a['username']??$id)),'authority_role'=>strtolower(trim((string)($a['authority_role']??'')))]; }
    private static function now(?callable $clock):\DateTimeImmutable { $v=$clock!==null?$clock():new \DateTimeImmutable('now',new \DateTimeZone('UTC'));if($v instanceof \DateTimeImmutable)return $v->setTimezone(new \DateTimeZone('UTC'));if($v instanceof \DateTime)return \DateTimeImmutable::createFromMutable($v)->setTimezone(new \DateTimeZone('UTC'));try{return(new \DateTimeImmutable(trim((string)$v)))->setTimezone(new \DateTimeZone('UTC'));}catch(\Throwable){return new \DateTimeImmutable('now',new \DateTimeZone('UTC'));} }
    private static function utc(string $v):?\DateTimeImmutable { $v=trim($v);if($v==='')return null;try{$d=new \DateTimeImmutable($v);}catch(\Throwable){return null;}return $d->getOffset()===0?$d->setTimezone(new \DateTimeZone('UTC')):null; }
    private static function root(?string $root):string { return $root!==null&&trim($root)!==''?rtrim($root,DIRECTORY_SEPARATOR):(defined('APP_ROOT')?rtrim((string)APP_ROOT,DIRECTORY_SEPARATOR):dirname(__DIR__,3)); }
    private static function path(string $path):string { $parts=[];foreach(explode('/',ltrim(str_replace('\\','/',trim($path)),'/'))as$p){if($p===''||$p==='.')continue;if($p==='..')return'';$parts[]=$p;}return implode('/',$parts); }
    /** @return array<string,mixed> */
    private static function arr(array $a,string $k):array { return isset($a[$k])&&is_array($a[$k])?$a[$k]:[]; }
    /** @return array<int,string> */
    private static function strings($v):array { if(!is_array($v))return[];$r=[];foreach($v as$i){$i=trim((string)$i);if($i!=='')$r[$i]=true;}return array_map('strval',array_keys($r)); }
    private static function diag(string $code,string $message,array $extra=[]):array { return array_merge(['code'=>$code,'severity'=>'error','message'=>$message],$extra); }
    private static function json(array $m):string { $e=json_encode(self::sort($m),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);return is_string($e)?$e:serialize($m); }
    private static function sort($v){if(!is_array($v))return$v;if(array_is_list($v))return array_map([self::class,'sort'],$v);ksort($v);foreach($v as$k=>$i)$v[$k]=self::sort($i);return$v;}
}

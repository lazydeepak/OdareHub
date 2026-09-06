<?php
declare(strict_types=1);

use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureDeletionExecutorReadinessWorkspaceService;

require_once dirname(__DIR__) . '/Tools/OwnerStructureScan/Services/OwnerStructureDeletionExecutorReadinessWorkspaceService.php';

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    if ($condition) { $passes++; echo "PASS: {$message}\n"; return; }
    $fails++; echo "FAIL: {$message}\n";
};
$owners = [['owner_key' => 'Manufacturing/Products', 'owner_type' => 'module']];
$changeSet = ['change_set_fingerprint' => 'cs-current', 'source_plan' => ['fingerprint' => 'plan-current'], 'target' => ['owner_key' => 'Manufacturing/Products']];
$readiness = ['readiness_fingerprint' => 'ready-current'];
$dryRun = ['dry_run_fingerprint' => 'dry-current'];
$actor = ['actor_id' => 'executor-2', 'authority_role' => 'platform_admin'];
$exact = ['request_id' => 'request-current', 'request_fingerprint' => 'request-fp-current'];
$latest = $exact;
$use = ['use_id' => 'use-1'];
$assessment = [
    'status' => 'ok', 'executor_readiness' => 'ready', 'executor_eligible' => 'yes', 'request_valid' => 'yes',
    'executor_identity_valid' => 'yes', 'single_use_available' => 'yes',
    'current_packet' => ['change_set_fingerprint' => 'cs-current'],
    'request_evidence' => ['request_id' => 'request-current'], 'actor_evidence' => ['actor_id' => 'executor-2'],
    'single_use_evidence' => [], 'blocking_reasons' => [], 'executor_readiness_fingerprint' => 'executor-ready-fp', 'diagnostics' => [],
];
$exactCalls = 0; $ownerCalls = 0; $useCalls = 0; $assessorCalls = 0;
$exactReader = static function (string $ownerKey, string $changeSetFingerprint, string $dryRunFingerprint, ?string $root) use (&$exactCalls, $exact): array {
    $exactCalls++;
    if ($ownerKey !== 'Manufacturing/Products' || $changeSetFingerprint !== 'cs-current' || $dryRunFingerprint !== 'dry-current') { throw new RuntimeException('wrong exact lookup'); }
    return $exact;
};
$ownerReader = static function (string $ownerKey, ?string $root) use (&$ownerCalls, $latest): array { $ownerCalls++; return $latest; };
$useReader = static function (string $requestId, ?string $root) use (&$useCalls): ?array { $useCalls++; return null; };
$assessor = static function (array $owner, array $packet, array $ready, array $simulation, ?array $exactRequest, ?array $latestRequest, ?array $currentActor, ?array $useEvidence) use (&$assessorCalls, $assessment): array {
    $assessorCalls++;
    if (($owner['owner_key'] ?? '') !== 'Manufacturing/Products' || ($exactRequest['request_id'] ?? '') !== 'request-current' || ($currentActor['actor_id'] ?? '') !== 'executor-2' || $useEvidence !== null) { throw new RuntimeException('wrong assessor args'); }
    return $assessment;
};

$idle = OwnerStructureDeletionExecutorReadinessWorkspaceService::build($owners, 'Manufacturing/Products', false, $changeSet, $readiness, $dryRun, $actor, null, $exactReader, $ownerReader, $useReader, $assessor);
$assert(($idle['status'] ?? '') === 'idle', 'idle workspace stays idle');
$assert(($idle['assessment'] ?? null) === null, 'idle workspace has no assessment');
$assert($exactCalls === 0 && $ownerCalls === 0 && $useCalls === 0 && $assessorCalls === 0, 'idle workspace performs no reads');

$readyWorkspace = OwnerStructureDeletionExecutorReadinessWorkspaceService::build($owners, 'Manufacturing/Products', true, $changeSet, $readiness, $dryRun, $actor, null, $exactReader, $ownerReader, $useReader, $assessor);
$assert(($readyWorkspace['status'] ?? '') === 'ready', 'requested workspace is ready');
$assert($exactCalls === 1, 'exact request reader invoked once');
$assert($ownerCalls === 1, 'owner request reader invoked once');
$assert($useCalls === 1, 'single-use reader invoked once');
$assert($assessorCalls === 1, 'canonical assessor invoked once');
$assert(($readyWorkspace['executor_readiness'] ?? '') === 'ready', 'readiness state is exposed');
$assert(($readyWorkspace['executor_eligible'] ?? '') === 'yes', 'executor eligibility is exposed');
$assert(($readyWorkspace['request_valid'] ?? '') === 'yes', 'request validity is exposed');
$assert(($readyWorkspace['executor_identity_valid'] ?? '') === 'yes', 'identity validity is exposed');
$assert(($readyWorkspace['single_use_available'] ?? '') === 'yes', 'single-use availability is exposed');
$assert(($readyWorkspace['request_evidence']['request_id'] ?? '') === 'request-current', 'request evidence is exposed');
$assert(($readyWorkspace['actor_evidence']['actor_id'] ?? '') === 'executor-2', 'actor evidence is exposed');
$assert(($readyWorkspace['executor_readiness_fingerprint'] ?? '') === 'executor-ready-fp', 'readiness fingerprint is exposed');
$assert(($readyWorkspace['blocking_reasons'] ?? []) === [], 'ready workspace exposes no blockers');

$missingOwner = OwnerStructureDeletionExecutorReadinessWorkspaceService::build($owners, 'Missing/Owner', true, $changeSet, $readiness, $dryRun, $actor);
$assert(($missingOwner['status'] ?? '') === 'error', 'missing owner fails safely');
$assert(($missingOwner['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_EXECUTOR_READINESS_OWNER_UNAVAILABLE', 'missing owner diagnostic is explicit');

$missingPacket = OwnerStructureDeletionExecutorReadinessWorkspaceService::build($owners, 'Manufacturing/Products', true, null, $readiness, $dryRun, $actor);
$assert(($missingPacket['status'] ?? '') === 'error', 'missing packet fails safely');
$assert(($missingPacket['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_EXECUTOR_READINESS_PACKET_REQUIRED', 'missing packet diagnostic is explicit');

$wrongPacket = $changeSet;
$wrongPacket['target']['owner_key'] = 'Manufacturing/Other';
$wrong = OwnerStructureDeletionExecutorReadinessWorkspaceService::build($owners, 'Manufacturing/Products', true, $wrongPacket, $readiness, $dryRun, $actor);
$assert(($wrong['status'] ?? '') === 'error', 'wrong packet owner fails safely');
$assert(($wrong['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_EXECUTOR_READINESS_PACKET_INVALID', 'wrong packet diagnostic is explicit');

$invalid = OwnerStructureDeletionExecutorReadinessWorkspaceService::build($owners, 'Manufacturing/Products', true, $changeSet, $readiness, $dryRun, $actor, null, $exactReader, $ownerReader, $useReader, static fn(): string => 'invalid');
$assert(($invalid['status'] ?? '') === 'error', 'invalid assessor result fails safely');
$assert(($invalid['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_EXECUTOR_READINESS_RESULT_INVALID', 'invalid result diagnostic is explicit');

$thrown = OwnerStructureDeletionExecutorReadinessWorkspaceService::build($owners, 'Manufacturing/Products', true, $changeSet, $readiness, $dryRun, $actor, null, static function (): array { throw new RuntimeException('executor probe failure'); });
$assert(($thrown['status'] ?? '') === 'error', 'reader exception fails safely');
$assert(($thrown['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_EXECUTOR_READINESS_FAILED', 'reader exception diagnostic is explicit');
$assert(($thrown['diagnostics'][0]['message'] ?? '') === 'executor probe failure', 'reader exception message is preserved');

$noExactUseCalls = 0;
$notRequestedAssessment = $assessment;
$notRequestedAssessment['executor_readiness'] = 'not_requested';
$notRequestedAssessment['executor_eligible'] = 'no';
$notRequested = OwnerStructureDeletionExecutorReadinessWorkspaceService::build(
    $owners, 'Manufacturing/Products', true, $changeSet, $readiness, $dryRun, $actor, null,
    static fn(): ?array => null,
    $ownerReader,
    static function () use (&$noExactUseCalls): ?array { $noExactUseCalls++; return $use; },
    static fn(): array => $notRequestedAssessment
);
$assert(($notRequested['executor_readiness'] ?? '') === 'not_requested', 'missing exact request state is preserved');
$assert($noExactUseCalls === 0, 'single-use store is not queried without exact request id');

$postludes = ['preview.postlude.zzzzzz-deletion-execution-request.php', 'preview.postlude.zzzzzzz-deletion-executor-readiness.php'];
sort($postludes, SORT_NATURAL | SORT_FLAG_CASE);
$assert($postludes[1] === 'preview.postlude.zzzzzzz-deletion-executor-readiness.php', 'executor readiness loads after execution request');

$source = file_get_contents(dirname(__DIR__) . '/Tools/OwnerStructureScan/Services/OwnerStructureDeletionExecutorReadinessWorkspaceService.php');
$assert(is_string($source) && !preg_match('/file_put_contents|fwrite|unlink\s*\(|rename\s*\(|mkdir\s*\(|rmdir\s*\(|copy\s*\(|PDO|INSERT\s+INTO|UPDATE\s+.+SET|DELETE\s+FROM/i', $source), 'workspace presenter contains no mutation implementation');

echo "\nResults: {$passes} passed, {$fails} failed\n";
exit($fails > 0 ? 1 : 0);

<?php
declare(strict_types=1);

use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureDeletionExecutionClaimWorkspaceService;

require_once dirname(__DIR__) . '/Tools/OwnerStructureScan/Services/OwnerStructureDeletionExecutionClaimWorkspaceService.php';

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    if ($condition) { $passes++; echo "PASS: {$message}\n"; return; }
    $fails++; echo "FAIL: {$message}\n";
};

$owners = [[
    'owner_key' => 'Manufacturing/Products',
    'owner_type' => 'module',
    'root_path' => 'apps/Manufacturing/modules/Products',
]];
$readiness = [
    'status' => 'ok',
    'effect' => 'verify',
    'executor_readiness' => 'ready',
    'executor_eligible' => 'yes',
    'request_valid' => 'yes',
    'executor_identity_valid' => 'yes',
    'single_use_available' => 'yes',
    'current_packet' => [
        'target' => [
            'owner_key' => 'Manufacturing/Products',
            'target_path' => 'apps/Manufacturing/modules/Products',
        ],
    ],
    'request_evidence' => [
        'request_id' => 'request-current',
        'request_fingerprint' => 'deletion-execution-request:current',
    ],
    'actor_evidence' => [
        'actor_id' => 'executor-42',
        'display_name' => 'Executor 42',
        'authority_role' => 'platform_admin',
    ],
    'single_use_evidence' => [],
    'blocking_reasons' => [],
    'executor_readiness_fingerprint' => 'deletion-executor-readiness:current',
];

$calls = 0;
$reader = static function (string $requestId, ?string $root) use (&$calls): ?array {
    $calls++;
    if ($requestId !== 'request-current') { throw new RuntimeException('wrong request id'); }
    return null;
};

$idle = OwnerStructureDeletionExecutionClaimWorkspaceService::build(
    $owners, 'Manufacturing/Products', false, $readiness, null, null, $reader
);
$assert(($idle['status'] ?? '') === 'idle', 'idle workspace does not prepare claim');
$assert(($idle['can_claim'] ?? '') === 'no', 'idle workspace cannot claim');
$assert($calls === 0, 'idle workspace performs no history read');

$ready = OwnerStructureDeletionExecutionClaimWorkspaceService::build(
    $owners, 'Manufacturing/Products', true, $readiness, null, null, $reader
);
$assert(($ready['status'] ?? '') === 'ready', 'requested workspace becomes ready');
$assert(($ready['can_claim'] ?? '') === 'yes', 'ready named executor may claim');
$assert($calls === 1, 'claim history is read once');
$assert(($ready['source']['request_id'] ?? '') === 'request-current', 'workspace exposes request id');
$assert(($ready['source']['request_fingerprint'] ?? '') === 'deletion-execution-request:current', 'workspace exposes request fingerprint');
$assert(($ready['source']['executor_readiness_fingerprint'] ?? '') === 'deletion-executor-readiness:current', 'workspace exposes readiness fingerprint');
$assert(($ready['request_evidence']['request_id'] ?? '') === 'request-current', 'workspace exposes request evidence');
$assert(($ready['actor_evidence']['actor_id'] ?? '') === 'executor-42', 'workspace exposes actor evidence');
$assert(($ready['latest_claim'] ?? null) === null, 'ready workspace has no prior claim');
$assert(($ready['diagnostics'] ?? []) === [], 'ready workspace has no diagnostics');

$claim = [
    'claim_id' => 'claim-current',
    'use_id' => 'claim-current',
    'source' => [
        'request_id' => 'request-current',
        'request_fingerprint' => 'deletion-execution-request:current',
    ],
    'immutable' => 'yes',
    'append_only' => 'yes',
];
$claimed = OwnerStructureDeletionExecutionClaimWorkspaceService::build(
    $owners,
    'Manufacturing/Products',
    true,
    $readiness,
    ['status' => 'recorded', 'claim_id' => 'claim-current'],
    null,
    static fn(string $requestId, ?string $root): array => $claim
);
$assert(($claimed['status'] ?? '') === 'ready', 'claimed workspace still renders evidence');
$assert(($claimed['can_claim'] ?? '') === 'no', 'existing claim disables form');
$assert(($claimed['latest_claim']['claim_id'] ?? '') === 'claim-current', 'existing claim is exposed');
$assert(($claimed['flash']['claim_id'] ?? '') === 'claim-current', 'claim flash is preserved');
$assert(($claimed['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_EXECUTION_CLAIM_ALREADY_CONSUMED', 'consumed diagnostic is explicit');

$wrongState = $readiness;
$wrongState['executor_readiness'] = 'wrong_executor';
$wrongState['executor_eligible'] = 'no';
$blocked = OwnerStructureDeletionExecutionClaimWorkspaceService::build(
    $owners, 'Manufacturing/Products', true, $wrongState, null, null, static fn(): ?array => null
);
$assert(($blocked['can_claim'] ?? '') === 'no', 'wrong executor cannot claim');
$assert(($blocked['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_EXECUTION_CLAIM_NOT_READY', 'not-ready diagnostic is explicit');

$consumed = $readiness;
$consumed['single_use_available'] = 'no';
$consumed['single_use_evidence'] = $claim;
$blocked = OwnerStructureDeletionExecutionClaimWorkspaceService::build(
    $owners, 'Manufacturing/Products', true, $consumed, null, null, static fn(): ?array => $claim
);
$assert(($blocked['can_claim'] ?? '') === 'no', 'consumed readiness cannot claim');

$missingOwner = OwnerStructureDeletionExecutionClaimWorkspaceService::build(
    $owners, 'Missing/Owner', true, $readiness
);
$assert(($missingOwner['status'] ?? '') === 'error', 'missing owner fails safely');
$assert(($missingOwner['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_EXECUTION_CLAIM_OWNER_UNAVAILABLE', 'missing owner diagnostic is explicit');

$missingReadiness = OwnerStructureDeletionExecutionClaimWorkspaceService::build(
    $owners, 'Manufacturing/Products', true, null
);
$assert(($missingReadiness['status'] ?? '') === 'error', 'missing readiness fails safely');
$assert(($missingReadiness['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_EXECUTION_CLAIM_READINESS_REQUIRED', 'missing readiness diagnostic is explicit');

$wrongOwner = $readiness;
$wrongOwner['current_packet']['target']['owner_key'] = 'Manufacturing/Other';
$invalid = OwnerStructureDeletionExecutionClaimWorkspaceService::build(
    $owners, 'Manufacturing/Products', true, $wrongOwner
);
$assert(($invalid['status'] ?? '') === 'error', 'wrong packet owner fails safely');
$assert(($invalid['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_EXECUTION_CLAIM_PACKET_INVALID', 'wrong packet diagnostic is explicit');

$thrown = OwnerStructureDeletionExecutionClaimWorkspaceService::build(
    $owners,
    'Manufacturing/Products',
    true,
    $readiness,
    null,
    null,
    static function (): ?array { throw new RuntimeException('claim history failure'); }
);
$assert(($thrown['status'] ?? '') === 'error', 'history exception fails safely');
$assert(($thrown['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_EXECUTION_CLAIM_HISTORY_FAILED', 'history failure diagnostic is explicit');
$assert(($thrown['diagnostics'][0]['message'] ?? '') === 'claim history failure', 'history failure message is preserved');

$postludes = [
    'preview.postlude.zzzzzz-deletion-execution-request.php',
    'preview.postlude.zzzzzzz-deletion-executor-readiness.php',
    'preview.postlude.zzzzzzzz-deletion-execution-claim.php',
];
sort($postludes, SORT_NATURAL | SORT_FLAG_CASE);
$assert($postludes[2] === 'preview.postlude.zzzzzzzz-deletion-execution-claim.php', 'claim postlude loads after executor readiness');

$source = file_get_contents(dirname(__DIR__) . '/Tools/OwnerStructureScan/Services/OwnerStructureDeletionExecutionClaimWorkspaceService.php');
$assert(is_string($source) && !preg_match('/file_put_contents|fwrite|unlink\s*\(|rename\s*\(|mkdir\s*\(|rmdir\s*\(|copy\s*\(|PDO|INSERT\s+INTO|UPDATE\s+.+SET|DELETE\s+FROM/i', $source), 'workspace presenter contains no mutation implementation');

echo "\nResults: {$passes} passed, {$fails} failed\n";
exit($fails > 0 ? 1 : 0);

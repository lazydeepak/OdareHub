<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

/**
 * Canonical read-only verifier for one immutable deletion execution request.
 * It validates the current packet, request integrity, expiry, executor identity,
 * and single-use evidence. It never claims or executes the request.
 */
final class StudioDeletionExecutorReadinessService
{
    public const EFFECT = 'verify';
    public const VERSION = 'studio.deletion-executor-readiness.v1';

    public const STATE_READY = 'ready';
    public const STATE_NOT_REQUESTED = 'not_requested';
    public const STATE_STALE = 'stale';
    public const STATE_EXPIRED = 'expired';
    public const STATE_WRONG_EXECUTOR = 'wrong_executor';
    public const STATE_CONSUMED = 'consumed';
    public const STATE_BLOCKED = 'blocked';
    public const STATE_UNKNOWN = 'unknown';

    /**
     * @param array<string,mixed> $changeSet
     * @param array<string,mixed> $readiness
     * @param array<string,mixed> $dryRun
     * @param array<string,mixed>|null $exactRequest
     * @param array<string,mixed>|null $latestOwnerRequest
     * @param array<string,mixed>|null $actor
     * @param array<string,mixed>|null $useEvidence
     * @param callable():mixed|null $clock
     * @return array<string,mixed>
     */
    public static function assess(
        array $changeSet,
        array $readiness,
        array $dryRun,
        ?array $exactRequest,
        ?array $latestOwnerRequest,
        ?array $actor,
        ?array $useEvidence = null,
        ?callable $clock = null
    ): array {
        $target = self::arrayValue($changeSet, 'target');
        $currentPacket = self::arrayValue($readiness, 'current_packet');
        $approvalEvidence = self::arrayValue($readiness, 'approval_evidence');
        $dryRunSource = self::arrayValue($dryRun, 'source');
        $changeSetFingerprint = trim((string)($changeSet['change_set_fingerprint'] ?? ''));
        $planFingerprint = trim((string)($changeSet['source_plan']['fingerprint'] ?? ''));
        $readinessFingerprint = trim((string)($readiness['readiness_fingerprint'] ?? ''));
        $dryRunFingerprint = trim((string)($dryRun['dry_run_fingerprint'] ?? ''));
        $approvalRecordId = trim((string)($approvalEvidence['record_id'] ?? ''));
        $approvalRecordFingerprint = trim((string)($approvalEvidence['record_fingerprint'] ?? ''));
        $ownerKey = trim((string)($target['owner_key'] ?? ''));
        $targetPath = self::normalizePath((string)($target['target_path'] ?? ''));
        $source = [
            'change_set_fingerprint' => $changeSetFingerprint,
            'plan_fingerprint' => $planFingerprint,
            'readiness_fingerprint' => $readinessFingerprint,
            'dry_run_fingerprint' => $dryRunFingerprint,
            'approval_record_id' => $approvalRecordId,
            'approval_record_fingerprint' => $approvalRecordFingerprint,
        ];
        $blockingReasons = [];
        $diagnostics = [];

        $packetProblems = self::packetProblems($changeSet, $readiness, $dryRun, $source, $ownerKey, $targetPath, $currentPacket, $approvalEvidence, $dryRunSource);
        if ($packetProblems !== []) {
            $diagnostics[] = self::diagnostic('DELETION_EXECUTOR_READINESS_PACKET_INVALID', 'Executor readiness requires the exact current approved and successfully simulated deletion packet.', ['reasons' => $packetProblems]);
            return self::result(self::STATE_UNKNOWN, $target, $source, $exactRequest, $latestOwnerRequest, $actor, $useEvidence, $packetProblems, $diagnostics, false, false, false);
        }

        if ($exactRequest === null) {
            if ($latestOwnerRequest !== null) {
                $latestSource = self::arrayValue($latestOwnerRequest, 'source');
                foreach ($source as $field => $value) {
                    if ((string)($latestSource[$field] ?? '') !== $value) {
                        $blockingReasons[] = 'EXECUTION_REQUEST_STALE:' . strtoupper($field);
                    }
                }
                $diagnostics[] = self::diagnostic('DELETION_EXECUTOR_READINESS_REQUEST_STALE', 'The latest execution request for this owner is bound to an older deletion packet.');
                return self::result(self::STATE_STALE, $target, $source, null, $latestOwnerRequest, $actor, $useEvidence, $blockingReasons, $diagnostics, false, false, false);
            }
            $blockingReasons[] = 'EXECUTION_REQUEST_REQUIRED';
            $diagnostics[] = self::diagnostic('DELETION_EXECUTOR_READINESS_REQUEST_MISSING', 'No execution request exists for the current deletion dry run.');
            return self::result(self::STATE_NOT_REQUESTED, $target, $source, null, null, $actor, $useEvidence, $blockingReasons, $diagnostics, false, false, false);
        }

        $requestProblems = self::requestProblems($exactRequest, $source, $ownerKey, $targetPath);
        if ($requestProblems !== []) {
            $diagnostics[] = self::diagnostic('DELETION_EXECUTOR_READINESS_REQUEST_INVALID', 'The execution request failed integrity, provenance, or policy validation.', ['reasons' => $requestProblems]);
            return self::result(self::STATE_BLOCKED, $target, $source, $exactRequest, $latestOwnerRequest, $actor, $useEvidence, $requestProblems, $diagnostics, false, false, false);
        }

        $now = self::now($clock);
        $requestedAt = self::parseUtc((string)($exactRequest['requested_at_utc'] ?? ''));
        $expiresAt = self::parseUtc((string)($exactRequest['expires_at_utc'] ?? ''));
        if ($requestedAt === null || $expiresAt === null) {
            $blockingReasons[] = 'EXECUTION_REQUEST_TIME_INVALID';
            $diagnostics[] = self::diagnostic('DELETION_EXECUTOR_READINESS_TIME_INVALID', 'The execution request time contract is invalid.');
            return self::result(self::STATE_BLOCKED, $target, $source, $exactRequest, $latestOwnerRequest, $actor, $useEvidence, $blockingReasons, $diagnostics, false, false, false);
        }
        if ($requestedAt > $now) {
            $blockingReasons[] = 'EXECUTION_REQUEST_NOT_YET_VALID';
            $diagnostics[] = self::diagnostic('DELETION_EXECUTOR_READINESS_NOT_YET_VALID', 'The execution request creation time is in the future.');
            return self::result(self::STATE_BLOCKED, $target, $source, $exactRequest, $latestOwnerRequest, $actor, $useEvidence, $blockingReasons, $diagnostics, false, false, false);
        }
        if ($expiresAt <= $now) {
            $blockingReasons[] = 'EXECUTION_REQUEST_EXPIRED';
            $diagnostics[] = self::diagnostic('DELETION_EXECUTOR_READINESS_EXPIRED', 'The execution request has expired and cannot be claimed.');
            return self::result(self::STATE_EXPIRED, $target, $source, $exactRequest, $latestOwnerRequest, $actor, $useEvidence, $blockingReasons, $diagnostics, true, false, false);
        }

        $requestId = trim((string)($exactRequest['request_id'] ?? ''));
        $requestFingerprint = trim((string)($exactRequest['request_fingerprint'] ?? ''));
        if ($useEvidence !== null) {
            $useProblems = self::useEvidenceProblems($useEvidence, $requestId, $requestFingerprint);
            if ($useProblems !== []) {
                $diagnostics[] = self::diagnostic('DELETION_EXECUTOR_READINESS_USE_EVIDENCE_INVALID', 'Single-use evidence exists but does not bind safely to the request.', ['reasons' => $useProblems]);
                return self::result(self::STATE_BLOCKED, $target, $source, $exactRequest, $latestOwnerRequest, $actor, $useEvidence, $useProblems, $diagnostics, true, false, false);
            }
            $blockingReasons[] = 'EXECUTION_REQUEST_ALREADY_USED';
            $diagnostics[] = self::diagnostic('DELETION_EXECUTOR_READINESS_CONSUMED', 'Single-use evidence already exists for this execution request.');
            return self::result(self::STATE_CONSUMED, $target, $source, $exactRequest, $latestOwnerRequest, $actor, $useEvidence, $blockingReasons, $diagnostics, true, false, false);
        }

        $actorRecord = self::actorRecord((array)$actor);
        $executorPolicy = self::arrayValue($exactRequest, 'executor_policy');
        $namedExecutorId = trim((string)($executorPolicy['executor_actor_id'] ?? ''));
        $actorMatches = $namedExecutorId !== '' && $actorRecord['actor_id'] !== '' && hash_equals($namedExecutorId, $actorRecord['actor_id']);
        $actorAuthorized = self::isPlatformAdmin($actor) && $actorMatches;
        if (!$actorAuthorized) {
            if (!self::isPlatformAdmin($actor)) {
                $blockingReasons[] = 'CURRENT_ACTOR_NOT_PLATFORM_ADMIN';
            }
            if (!$actorMatches) {
                $blockingReasons[] = 'CURRENT_ACTOR_NOT_NAMED_EXECUTOR';
            }
            $diagnostics[] = self::diagnostic('DELETION_EXECUTOR_READINESS_WRONG_EXECUTOR', 'The current actor is not the named platform-admin executor for this request.');
            return self::result(self::STATE_WRONG_EXECUTOR, $target, $source, $exactRequest, $latestOwnerRequest, $actor, null, $blockingReasons, $diagnostics, true, false, true);
        }

        return self::result(self::STATE_READY, $target, $source, $exactRequest, $latestOwnerRequest, $actor, null, [], [], true, true, true);
    }

    /** @param array<string,string> $source @return array<int,string> */
    private static function packetProblems(array $changeSet, array $readiness, array $dryRun, array $source, string $ownerKey, string $targetPath, array $currentPacket, array $approvalEvidence, array $dryRunSource): array
    {
        $problems = [];
        if ($ownerKey === '' || $targetPath === '' || in_array('', array_values($source), true)) {
            $problems[] = 'CURRENT_PACKET_IDENTITY_MISSING';
        }
        if ((string)($changeSet['immutable'] ?? 'no') !== 'yes' || (string)($changeSet['can_execute'] ?? 'yes') !== 'no' || (string)($changeSet['can_apply'] ?? 'yes') !== 'no') {
            $problems[] = 'CHANGE_SET_CONTRACT_INVALID';
        }
        if ((string)($readiness['effect'] ?? '') !== 'verify' || (string)($readiness['execution_readiness'] ?? '') !== 'ready' || (string)($readiness['execution_eligible'] ?? 'no') !== 'yes' || (string)($readiness['approval_valid'] ?? 'no') !== 'yes') {
            $problems[] = 'APPROVAL_READINESS_INVALID';
        }
        if ((string)($dryRun['effect'] ?? '') !== 'simulate' || (string)($dryRun['dry_run_state'] ?? '') !== 'ready' || (string)($dryRun['simulation_complete'] ?? 'no') !== 'yes' || (string)($dryRun['execution_eligible_at_simulation'] ?? 'no') !== 'yes') {
            $problems[] = 'DRY_RUN_INVALID';
        }
        foreach (['can_execute', 'can_apply', 'grants_execution_authority'] as $field) {
            if ((string)($readiness[$field] ?? 'yes') !== 'no') {
                $problems[] = 'READINESS_AUTHORITY_INVALID:' . $field;
            }
        }
        foreach (['would_execute', 'would_apply', 'would_write', 'would_archive', 'would_delete', 'can_execute', 'can_apply', 'grants_execution_authority'] as $field) {
            if ((string)($dryRun[$field] ?? 'yes') !== 'no') {
                $problems[] = 'DRY_RUN_AUTHORITY_INVALID:' . $field;
            }
        }
        if (self::stringList($dryRun['blocking_reasons'] ?? []) !== []) {
            $problems[] = 'DRY_RUN_BLOCKERS_PRESENT';
        }
        if ((string)($currentPacket['change_set_fingerprint'] ?? '') !== $source['change_set_fingerprint'] || (string)($dryRunSource['change_set_fingerprint'] ?? '') !== $source['change_set_fingerprint']) {
            $problems[] = 'CHANGE_SET_FINGERPRINT_MISMATCH';
        }
        if ((string)($currentPacket['plan_fingerprint'] ?? '') !== $source['plan_fingerprint'] || (string)($dryRunSource['plan_fingerprint'] ?? '') !== $source['plan_fingerprint']) {
            $problems[] = 'PLAN_FINGERPRINT_MISMATCH';
        }
        if ((string)($dryRunSource['readiness_fingerprint'] ?? '') !== $source['readiness_fingerprint']) {
            $problems[] = 'READINESS_FINGERPRINT_MISMATCH';
        }
        if ((string)($dryRun['dry_run_fingerprint'] ?? '') !== $source['dry_run_fingerprint']) {
            $problems[] = 'DRY_RUN_FINGERPRINT_MISMATCH';
        }
        if ((string)($approvalEvidence['exact_packet_match'] ?? 'no') !== 'yes' || (string)($approvalEvidence['decision'] ?? '') !== 'approved') {
            $problems[] = 'EXACT_APPROVAL_REQUIRED';
        }
        if ((string)($dryRunSource['approval_record_id'] ?? '') !== $source['approval_record_id'] || (string)($dryRunSource['approval_record_fingerprint'] ?? '') !== $source['approval_record_fingerprint']) {
            $problems[] = 'APPROVAL_EVIDENCE_MISMATCH';
        }
        return array_values(array_unique($problems));
    }

    /** @param array<string,string> $source @return array<int,string> */
    private static function requestProblems(array $request, array $source, string $ownerKey, string $targetPath): array
    {
        $problems = [];
        $requestSource = self::arrayValue($request, 'source');
        $requestTarget = self::arrayValue($request, 'target');
        $executorPolicy = self::arrayValue($request, 'executor_policy');
        $executionContract = self::arrayValue($request, 'execution_contract');
        $storage = self::arrayValue($request, 'storage');
        if ((string)($request['status'] ?? '') !== 'recorded' || (string)($request['request_state'] ?? '') !== 'requested') {
            $problems[] = 'EXECUTION_REQUEST_STATE_INVALID';
        }
        if ((string)($request['request_version'] ?? $request['version'] ?? '') !== 'studio.deletion-execution-request.v1') {
            $problems[] = 'EXECUTION_REQUEST_VERSION_INVALID';
        }
        if ((string)($request['immutable'] ?? 'no') !== 'yes' || (string)($request['append_only'] ?? 'no') !== 'yes') {
            $problems[] = 'EXECUTION_REQUEST_MUTABILITY_INVALID';
        }
        foreach (['can_execute', 'can_apply', 'can_archive', 'can_delete', 'execution_authorized', 'grants_execution_authority'] as $field) {
            if ((string)($request[$field] ?? 'yes') !== 'no') {
                $problems[] = 'EXECUTION_REQUEST_AUTHORITY_INVALID:' . $field;
            }
        }
        foreach ($source as $field => $value) {
            if ((string)($requestSource[$field] ?? '') !== $value) {
                $problems[] = 'EXECUTION_REQUEST_SOURCE_MISMATCH:' . strtoupper($field);
            }
        }
        if ((string)($requestTarget['owner_key'] ?? '') !== $ownerKey || self::normalizePath((string)($requestTarget['target_path'] ?? '')) !== $targetPath) {
            $problems[] = 'EXECUTION_REQUEST_TARGET_MISMATCH';
        }
        if ((string)($executorPolicy['required_authority_role'] ?? '') !== 'platform_admin'
            || !in_array('platform_admin', self::stringList($executorPolicy['allowed_authority_roles'] ?? []), true)
            || (string)($executorPolicy['executor_must_differ_from_requester'] ?? '') !== 'yes'
            || (string)($executorPolicy['requires_identity_revalidation'] ?? '') !== 'yes'
            || (string)($executorPolicy['requires_current_packet_revalidation'] ?? '') !== 'yes'
            || (string)($executorPolicy['single_use'] ?? '') !== 'yes'
            || trim((string)($executorPolicy['executor_actor_id'] ?? '')) === '') {
            $problems[] = 'EXECUTION_REQUEST_EXECUTOR_POLICY_INVALID';
        }
        if ((string)($executionContract['requires_snapshot_before_execution'] ?? '') !== 'yes'
            || (string)($executionContract['requires_current_approval'] ?? '') !== 'yes'
            || (string)($executionContract['requires_current_readiness'] ?? '') !== 'yes'
            || (string)($executionContract['requires_current_dry_run'] ?? '') !== 'yes'
            || (string)($executionContract['request_is_authority'] ?? 'yes') !== 'no') {
            $problems[] = 'EXECUTION_REQUEST_EXECUTION_CONTRACT_INVALID';
        }
        if ((string)($storage['storage_scope'] ?? '') !== 'studio_provenance' || (string)($storage['immutable'] ?? 'no') !== 'yes' || (string)($storage['append_only'] ?? 'no') !== 'yes') {
            $problems[] = 'EXECUTION_REQUEST_STORAGE_INVALID';
        }
        $requester = self::arrayValue($request, 'requester');
        if ((string)($requester['authority_role'] ?? '') !== 'platform_admin' || trim((string)($requester['actor_id'] ?? '')) === '') {
            $problems[] = 'EXECUTION_REQUEST_REQUESTER_INVALID';
        }
        if (trim((string)($requester['actor_id'] ?? '')) === trim((string)($executorPolicy['executor_actor_id'] ?? ''))) {
            $problems[] = 'EXECUTION_REQUEST_ROLE_SEPARATION_INVALID';
        }
        $expectedFingerprint = self::requestFingerprint($request);
        if ($expectedFingerprint === '' || !hash_equals($expectedFingerprint, (string)($request['request_fingerprint'] ?? ''))) {
            $problems[] = 'EXECUTION_REQUEST_FINGERPRINT_INVALID';
        }
        return array_values(array_unique($problems));
    }

    /** @return array<int,string> */
    private static function useEvidenceProblems(array $evidence, string $requestId, string $requestFingerprint): array
    {
        $problems = [];
        $source = self::arrayValue($evidence, 'source');
        $storage = self::arrayValue($evidence, 'storage');
        if ((string)($source['request_id'] ?? '') !== $requestId || (string)($source['request_fingerprint'] ?? '') !== $requestFingerprint) {
            $problems[] = 'USE_EVIDENCE_REQUEST_MISMATCH';
        }
        if ((string)($evidence['immutable'] ?? 'no') !== 'yes' || (string)($evidence['append_only'] ?? 'no') !== 'yes') {
            $problems[] = 'USE_EVIDENCE_MUTABILITY_INVALID';
        }
        if ((string)($storage['storage_scope'] ?? '') !== 'studio_provenance' || (string)($storage['immutable'] ?? 'no') !== 'yes' || (string)($storage['append_only'] ?? 'no') !== 'yes') {
            $problems[] = 'USE_EVIDENCE_STORAGE_INVALID';
        }
        return $problems;
    }

    private static function requestFingerprint(array $request): string
    {
        $material = [
            'version' => (string)($request['version'] ?? $request['request_version'] ?? ''),
            'request_state' => (string)($request['request_state'] ?? ''),
            'requested_at_utc' => (string)($request['requested_at_utc'] ?? ''),
            'expires_at_utc' => (string)($request['expires_at_utc'] ?? ''),
            'target' => self::arrayValue($request, 'target'),
            'source' => self::arrayValue($request, 'source'),
            'requester' => self::arrayValue($request, 'requester'),
            'executor_policy' => self::arrayValue($request, 'executor_policy'),
            'reason' => (string)($request['reason'] ?? ''),
            'execution_contract' => self::arrayValue($request, 'execution_contract'),
        ];
        return 'deletion-execution-request:' . substr(sha1(self::canonicalJson($material)), 0, 24);
    }

    /** @return array<string,mixed> */
    private static function result(string $state, array $target, array $source, ?array $exactRequest, ?array $latestOwnerRequest, ?array $actor, ?array $useEvidence, array $blockingReasons, array $diagnostics, bool $requestValid, bool $executorValid, bool $singleUseAvailable): array
    {
        $request = $exactRequest ?? $latestOwnerRequest;
        $executorPolicy = is_array($request) ? self::arrayValue($request, 'executor_policy') : [];
        $actorRecord = self::actorRecord((array)$actor);
        $ready = $state === self::STATE_READY;
        $material = [
            'version' => self::VERSION,
            'state' => $state,
            'source' => $source,
            'request_id' => (string)($request['request_id'] ?? ''),
            'request_fingerprint' => (string)($request['request_fingerprint'] ?? ''),
            'actor' => $actorRecord,
            'use_id' => (string)($useEvidence['use_id'] ?? ''),
            'blocking_reasons' => array_values(array_unique($blockingReasons)),
        ];
        return [
            'status' => in_array($state, [self::STATE_UNKNOWN, self::STATE_BLOCKED], true) ? 'partial' : 'ok',
            'effect' => self::EFFECT,
            'executor_readiness_version' => self::VERSION,
            'executor_readiness' => $state,
            'executor_eligible' => $ready ? 'yes' : 'no',
            'request_valid' => $requestValid ? 'yes' : 'no',
            'executor_identity_valid' => $executorValid ? 'yes' : 'no',
            'single_use_available' => $singleUseAvailable ? 'yes' : 'no',
            'can_claim' => 'no',
            'can_execute' => 'no',
            'can_apply' => 'no',
            'can_archive' => 'no',
            'can_delete' => 'no',
            'grants_execution_authority' => 'no',
            'requires_separate_claim_capability' => 'yes',
            'requires_separate_execution_capability' => 'yes',
            'current_packet' => array_merge($source, ['target' => $target]),
            'request_evidence' => [
                'present' => is_array($request) ? 'yes' : 'no',
                'exact_packet_match' => $exactRequest !== null ? 'yes' : 'no',
                'request_id' => (string)($request['request_id'] ?? ''),
                'request_fingerprint' => (string)($request['request_fingerprint'] ?? ''),
                'request_state' => (string)($request['request_state'] ?? ''),
                'requested_at_utc' => (string)($request['requested_at_utc'] ?? ''),
                'expires_at_utc' => (string)($request['expires_at_utc'] ?? ''),
                'requester' => is_array($request) ? self::arrayValue($request, 'requester') : [],
                'executor_policy' => $executorPolicy,
                'storage' => is_array($request) ? self::arrayValue($request, 'storage') : [],
            ],
            'actor_evidence' => $actorRecord,
            'single_use_evidence' => is_array($useEvidence) ? $useEvidence : [],
            'blocking_reasons' => array_values(array_unique($blockingReasons)),
            'executor_readiness_fingerprint' => 'deletion-executor-readiness:' . substr(sha1(self::canonicalJson($material)), 0, 24),
            'diagnostics' => $diagnostics,
        ];
    }

    /** @param array<string,mixed>|null $actor */
    private static function isPlatformAdmin(?array $actor): bool
    {
        return is_array($actor) && strtolower(trim((string)($actor['authority_role'] ?? ''))) === 'platform_admin';
    }

    /** @param array<string,mixed> $actor @return array<string,string> */
    private static function actorRecord(array $actor): array
    {
        $id = trim((string)($actor['user_id'] ?? $actor['id'] ?? $actor['actor_id'] ?? ''));
        $label = trim((string)($actor['display_name'] ?? $actor['name'] ?? $actor['email'] ?? $actor['username'] ?? $id));
        return ['actor_id' => $id, 'display_name' => $label, 'authority_role' => strtolower(trim((string)($actor['authority_role'] ?? '')))];
    }

    /** @param callable():mixed|null $clock */
    private static function now(?callable $clock): \DateTimeImmutable
    {
        $value = $clock !== null ? $clock() : new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        if ($value instanceof \DateTimeImmutable) {
            return $value->setTimezone(new \DateTimeZone('UTC'));
        }
        if ($value instanceof \DateTime) {
            return \DateTimeImmutable::createFromMutable($value)->setTimezone(new \DateTimeZone('UTC'));
        }
        try {
            return (new \DateTimeImmutable(trim((string)$value)))->setTimezone(new \DateTimeZone('UTC'));
        } catch (\Throwable $exception) {
            return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        }
    }

    private static function parseUtc(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        try {
            $date = new \DateTimeImmutable($value);
        } catch (\Throwable $exception) {
            return null;
        }
        return $date->getOffset() === 0 ? $date->setTimezone(new \DateTimeZone('UTC')) : null;
    }

    private static function normalizePath(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', trim($path)), '/');
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                return '';
            }
            $parts[] = $part;
        }
        return implode('/', $parts);
    }

    private static function arrayValue(array $source, string $key): array
    {
        return isset($source[$key]) && is_array($source[$key]) ? $source[$key] : [];
    }

    private static function stringList($value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $items = [];
        foreach ($value as $item) {
            $item = trim((string)$item);
            if ($item !== '') {
                $items[$item] = true;
            }
        }
        return array_map('strval', array_keys($items));
    }

    private static function diagnostic(string $code, string $message, array $extra = []): array
    {
        return array_merge(['code' => $code, 'severity' => 'error', 'message' => $message], $extra);
    }

    private static function canonicalJson(array $material): string
    {
        $normalized = self::sortRecursively($material);
        $encoded = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return is_string($encoded) ? $encoded : serialize($normalized);
    }

    private static function sortRecursively($value)
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map([self::class, 'sortRecursively'], $value);
        }
        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = self::sortRecursively($item);
        }
        return $value;
    }
}

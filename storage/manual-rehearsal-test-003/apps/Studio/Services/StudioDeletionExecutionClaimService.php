<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

require_once __DIR__ . '/StudioDeletionExecutionClaimStore.php';

/**
 * Atomically consumes one exact execution request by writing an immutable claim.
 * Claiming grants no permission to snapshot, edit, archive, delete, or execute.
 */
final class StudioDeletionExecutionClaimService
{
    public const EFFECT = 'mutate';
    public const VERSION = 'studio.deletion-execution-claim.v1';

    /**
     * @param array<string,mixed> $executorReadiness
     * @param array<string,mixed> $input
     * @param array<string,mixed>|null $actor
     * @param callable(array<string,mixed>,?string):array<string,mixed>|null $writer
     * @param callable():mixed|null $clock
     * @return array<string,mixed>
     */
    public static function claim(
        array $executorReadiness,
        array $input,
        ?array $actor,
        ?string $root = null,
        ?callable $writer = null,
        ?callable $clock = null
    ): array {
        $diagnostics = [];
        $requestEvidence = self::arrayValue($executorReadiness, 'request_evidence');
        $actorEvidence = self::arrayValue($executorReadiness, 'actor_evidence');
        $executorPolicy = self::arrayValue($requestEvidence, 'executor_policy');
        $currentPacket = self::arrayValue($executorReadiness, 'current_packet');

        $requestId = trim((string)($requestEvidence['request_id'] ?? ''));
        $requestFingerprint = trim((string)($requestEvidence['request_fingerprint'] ?? ''));
        $executorReadinessFingerprint = trim((string)($executorReadiness['executor_readiness_fingerprint'] ?? ''));
        $ownerKey = trim((string)($currentPacket['target']['owner_key'] ?? ''));
        $targetPath = self::normalizePath((string)($currentPacket['target']['target_path'] ?? ''));
        $source = [
            'request_id' => $requestId,
            'request_fingerprint' => $requestFingerprint,
            'executor_readiness_fingerprint' => $executorReadinessFingerprint,
            'change_set_fingerprint' => trim((string)($currentPacket['change_set_fingerprint'] ?? '')),
            'plan_fingerprint' => trim((string)($currentPacket['plan_fingerprint'] ?? '')),
            'readiness_fingerprint' => trim((string)($currentPacket['readiness_fingerprint'] ?? '')),
            'dry_run_fingerprint' => trim((string)($currentPacket['dry_run_fingerprint'] ?? '')),
            'approval_record_id' => trim((string)($currentPacket['approval_record_id'] ?? '')),
            'approval_record_fingerprint' => trim((string)($currentPacket['approval_record_fingerprint'] ?? '')),
        ];

        $readinessProblems = self::readinessProblems(
            $executorReadiness,
            $requestEvidence,
            $actorEvidence,
            $executorPolicy,
            $source,
            $ownerKey,
            $targetPath
        );
        foreach ($readinessProblems as $reason) {
            $diagnostics[] = self::diagnostic(
                'DELETION_EXECUTION_CLAIM_READINESS_INVALID',
                'Claiming requires the exact current unexpired request and its named ready platform-admin executor.',
                ['reason' => $reason]
            );
        }

        $presented = [
            'request_id' => trim((string)($input['request_id'] ?? '')),
            'request_fingerprint' => trim((string)($input['request_fingerprint'] ?? '')),
            'executor_readiness_fingerprint' => trim((string)($input['executor_readiness_fingerprint'] ?? '')),
        ];
        foreach ($presented as $field => $value) {
            $expected = (string)($source[$field] ?? '');
            if ($expected === '' || $value === '' || !hash_equals($expected, $value)) {
                $diagnostics[] = self::diagnostic(
                    'DELETION_EXECUTION_CLAIM_BINDING_STALE',
                    'The submitted claim does not match the current server-side execution request.',
                    ['field' => $field]
                );
            }
        }

        $actorRecord = self::actorRecord((array)$actor);
        $namedExecutorId = trim((string)($executorPolicy['executor_actor_id'] ?? ''));
        if (!self::isPlatformAdmin($actor) || $actorRecord['actor_id'] === '') {
            $diagnostics[] = self::diagnostic('DELETION_EXECUTION_CLAIM_ACTOR_UNAUTHORIZED', 'Only an identified platform administrator may claim an execution request.');
        }
        if ($namedExecutorId === '' || $actorRecord['actor_id'] === '' || !hash_equals($namedExecutorId, $actorRecord['actor_id'])) {
            $diagnostics[] = self::diagnostic('DELETION_EXECUTION_CLAIM_ACTOR_MISMATCH', 'Only the named executor may claim this execution request.');
        }
        if ((string)($input['claim_confirmed'] ?? '') !== 'yes') {
            $diagnostics[] = self::diagnostic('DELETION_EXECUTION_CLAIM_CONFIRMATION_REQUIRED', 'Explicit claim confirmation is required.');
        }
        $reason = trim((string)($input['reason'] ?? ''));
        if ($reason === '') {
            $diagnostics[] = self::diagnostic('DELETION_EXECUTION_CLAIM_REASON_REQUIRED', 'A claim reason is required.');
        } elseif (strlen($reason) > 1000) {
            $diagnostics[] = self::diagnostic('DELETION_EXECUTION_CLAIM_REASON_TOO_LONG', 'Claim reason must not exceed 1000 bytes.');
        }

        $now = self::now($clock);
        $expiresAt = self::parseUtc((string)($requestEvidence['expires_at_utc'] ?? ''));
        if ($expiresAt === null || $expiresAt <= $now) {
            $diagnostics[] = self::diagnostic('DELETION_EXECUTION_CLAIM_REQUEST_EXPIRED', 'The execution request expired before the claim was recorded.');
        }

        if ($diagnostics !== []) {
            return self::errorResult($source, $diagnostics);
        }

        $claimedAt = $now->format('Y-m-d\TH:i:s.u\Z');
        $claimMaterial = [
            'version' => self::VERSION,
            'claim_state' => 'claimed',
            'claimed_at_utc' => $claimedAt,
            'target' => [
                'owner_key' => $ownerKey,
                'target_type' => (string)($currentPacket['target']['target_type'] ?? 'owner'),
                'target_path' => $targetPath,
            ],
            'source' => $source,
            'executor' => $actorRecord,
            'reason' => $reason,
            'single_use_contract' => [
                'consumes_request' => 'yes',
                'atomic_claim_required' => 'yes',
                'request_must_be_unclaimed' => 'yes',
                'claim_is_execution_authority' => 'no',
            ],
        ];
        $claimId = 'deletion-execution-claim-' . substr(sha1($requestId . '|' . $requestFingerprint), 0, 20);
        $record = array_merge($claimMaterial, [
            'status' => 'recorded',
            'effect' => self::EFFECT,
            'claim_version' => self::VERSION,
            'claim_id' => $claimId,
            'use_id' => $claimId,
            'use_type' => 'claim',
            'immutable' => 'yes',
            'append_only' => 'yes',
            'atomic_single_use' => 'yes',
            'can_execute' => 'no',
            'can_apply' => 'no',
            'can_archive' => 'no',
            'can_delete' => 'no',
            'execution_authorized' => 'no',
            'grants_execution_authority' => 'no',
            'requires_separate_execution_capability' => 'yes',
            'claim_fingerprint' => 'deletion-execution-claim:' . substr(sha1(self::canonicalJson($claimMaterial)), 0, 24),
            'provenance' => [
                'capability' => self::class,
                'source' => 'owner_structure_scan',
                'route' => '/apps/studio/tools/owner-structure-scan/deletion-execution-claim',
            ],
        ]);

        try {
            $storage = $writer !== null
                ? $writer($record, $root)
                : StudioDeletionExecutionClaimStore::claim($record, $root);
        } catch (\Throwable $exception) {
            $message = $exception->getMessage() !== '' ? $exception->getMessage() : 'Execution claim storage failed.';
            $code = str_contains(strtolower($message), 'already claimed')
                ? 'DELETION_EXECUTION_CLAIM_ALREADY_EXISTS'
                : 'DELETION_EXECUTION_CLAIM_STORAGE_FAILED';
            return self::errorResult($source, [self::diagnostic($code, $message)]);
        }

        $record['storage'] = is_array($storage) ? $storage : [];
        return $record;
    }

    /** @return array<int,string> */
    private static function readinessProblems(
        array $readiness,
        array $request,
        array $actorEvidence,
        array $executorPolicy,
        array $source,
        string $ownerKey,
        string $targetPath
    ): array {
        $problems = [];
        if ($ownerKey === '' || $targetPath === '' || in_array('', array_values($source), true)) {
            $problems[] = 'CLAIM_SOURCE_IDENTITY_MISSING';
        }
        if ((string)($readiness['effect'] ?? '') !== 'verify'
            || (string)($readiness['executor_readiness'] ?? '') !== 'ready'
            || (string)($readiness['executor_eligible'] ?? 'no') !== 'yes'
            || (string)($readiness['request_valid'] ?? 'no') !== 'yes'
            || (string)($readiness['executor_identity_valid'] ?? 'no') !== 'yes'
            || (string)($readiness['single_use_available'] ?? 'no') !== 'yes') {
            $problems[] = 'EXECUTOR_READINESS_NOT_READY';
        }
        foreach (['can_claim', 'can_execute', 'can_apply', 'can_archive', 'can_delete', 'grants_execution_authority'] as $field) {
            if ((string)($readiness[$field] ?? 'yes') !== 'no') {
                $problems[] = 'EXECUTOR_READINESS_AUTHORITY_INVALID:' . $field;
            }
        }
        if ((string)($readiness['requires_separate_claim_capability'] ?? '') !== 'yes'
            || (string)($readiness['requires_separate_execution_capability'] ?? '') !== 'yes') {
            $problems[] = 'EXECUTOR_READINESS_SEPARATION_INVALID';
        }
        if (self::stringList($readiness['blocking_reasons'] ?? []) !== []) {
            $problems[] = 'EXECUTOR_READINESS_BLOCKERS_PRESENT';
        }
        if ((string)($request['present'] ?? 'no') !== 'yes'
            || (string)($request['exact_packet_match'] ?? 'no') !== 'yes'
            || (string)($request['request_state'] ?? '') !== 'requested') {
            $problems[] = 'EXECUTION_REQUEST_EVIDENCE_INVALID';
        }
        if ((string)($executorPolicy['single_use'] ?? '') !== 'yes'
            || (string)($executorPolicy['required_authority_role'] ?? '') !== 'platform_admin'
            || trim((string)($executorPolicy['executor_actor_id'] ?? '')) === '') {
            $problems[] = 'EXECUTION_REQUEST_EXECUTOR_POLICY_INVALID';
        }
        if ((string)($actorEvidence['authority_role'] ?? '') !== 'platform_admin'
            || trim((string)($actorEvidence['actor_id'] ?? '')) === ''
            || trim((string)($actorEvidence['actor_id'] ?? '')) !== trim((string)($executorPolicy['executor_actor_id'] ?? ''))) {
            $problems[] = 'EXECUTOR_IDENTITY_EVIDENCE_INVALID';
        }
        if (self::arrayValue($readiness, 'single_use_evidence') !== []) {
            $problems[] = 'EXECUTION_REQUEST_ALREADY_CONSUMED';
        }
        return array_values(array_unique($problems));
    }

    /** @param array<string,string> $source @return array<string,mixed> */
    private static function errorResult(array $source, array $diagnostics): array
    {
        return [
            'status' => 'error',
            'effect' => self::EFFECT,
            'claim_version' => self::VERSION,
            'recorded' => 'no',
            'immutable' => 'yes',
            'append_only' => 'yes',
            'atomic_single_use' => 'yes',
            'can_execute' => 'no',
            'can_apply' => 'no',
            'can_archive' => 'no',
            'can_delete' => 'no',
            'execution_authorized' => 'no',
            'grants_execution_authority' => 'no',
            'requires_separate_execution_capability' => 'yes',
            'source' => $source,
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
        return [
            'actor_id' => $id,
            'display_name' => $label,
            'authority_role' => strtolower(trim((string)($actor['authority_role'] ?? ''))),
        ];
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

    /** @return array<string,mixed> */
    private static function arrayValue(array $source, string $key): array
    {
        return isset($source[$key]) && is_array($source[$key]) ? $source[$key] : [];
    }

    /** @return array<int,string> */
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

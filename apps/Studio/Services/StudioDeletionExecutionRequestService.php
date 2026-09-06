<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

require_once __DIR__ . '/StudioDeletionExecutionRequestStore.php';

/**
 * Records an immutable request to execute one exact approved deletion dry run.
 * The request is provenance only and never grants or performs execution.
 */
final class StudioDeletionExecutionRequestService
{
    public const EFFECT = 'mutate';
    public const VERSION = 'studio.deletion-execution-request.v1';
    public const MIN_TTL_SECONDS = 300;
    public const MAX_TTL_SECONDS = 86400;

    /**
     * @param array<string,mixed> $changeSet
     * @param array<string,mixed> $readiness
     * @param array<string,mixed> $dryRun
     * @param array<string,mixed> $input
     * @param array<string,mixed>|null $requester
     * @param callable(array<string,mixed>,?string):array<string,mixed>|null $writer
     * @param callable():mixed|null $clock
     * @param callable(array<string,mixed>):string|null $idFactory
     * @return array<string,mixed>
     */
    public static function record(
        array $changeSet,
        array $readiness,
        array $dryRun,
        array $input,
        ?array $requester,
        ?string $root = null,
        ?callable $writer = null,
        ?callable $clock = null,
        ?callable $idFactory = null
    ): array {
        $diagnostics = [];
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

        $packetProblems = self::packetProblems(
            $changeSet,
            $readiness,
            $dryRun,
            $changeSetFingerprint,
            $planFingerprint,
            $readinessFingerprint,
            $dryRunFingerprint,
            $approvalRecordId,
            $approvalRecordFingerprint,
            $ownerKey,
            $targetPath,
            $currentPacket,
            $approvalEvidence,
            $dryRunSource
        );
        if ($packetProblems !== []) {
            $diagnostics[] = self::diagnostic(
                'DELETION_EXECUTION_REQUEST_PREREQUISITE_FAILED',
                'Execution request recording requires an exact, approved, ready, successfully simulated deletion packet.',
                ['reasons' => $packetProblems]
            );
        }

        $presented = [
            'change_set_fingerprint' => trim((string)($input['change_set_fingerprint'] ?? '')),
            'plan_fingerprint' => trim((string)($input['plan_fingerprint'] ?? '')),
            'readiness_fingerprint' => trim((string)($input['readiness_fingerprint'] ?? '')),
            'dry_run_fingerprint' => trim((string)($input['dry_run_fingerprint'] ?? '')),
            'approval_record_id' => trim((string)($input['approval_record_id'] ?? '')),
            'approval_record_fingerprint' => trim((string)($input['approval_record_fingerprint'] ?? '')),
        ];
        $expected = [
            'change_set_fingerprint' => $changeSetFingerprint,
            'plan_fingerprint' => $planFingerprint,
            'readiness_fingerprint' => $readinessFingerprint,
            'dry_run_fingerprint' => $dryRunFingerprint,
            'approval_record_id' => $approvalRecordId,
            'approval_record_fingerprint' => $approvalRecordFingerprint,
        ];
        foreach ($expected as $field => $value) {
            if ($value === '' || $presented[$field] === '' || !hash_equals($value, $presented[$field])) {
                $diagnostics[] = self::diagnostic(
                    'DELETION_EXECUTION_REQUEST_BINDING_STALE',
                    'The submitted execution request does not match the current server-side deletion packet.',
                    ['field' => $field]
                );
            }
        }

        $requesterRecord = self::actorRecord((array)$requester);
        if (!self::isPlatformAdmin($requester) || $requesterRecord['actor_id'] === '') {
            $diagnostics[] = self::diagnostic('DELETION_EXECUTION_REQUEST_REQUESTER_UNAUTHORIZED', 'Only an identified platform administrator may create an execution request.');
        }

        $executorActorId = trim((string)($input['executor_actor_id'] ?? ''));
        $executorDisplayName = trim((string)($input['executor_display_name'] ?? $executorActorId));
        $executorAuthorityRole = strtolower(trim((string)($input['executor_authority_role'] ?? 'platform_admin')));
        if ($executorActorId === '') {
            $diagnostics[] = self::diagnostic('DELETION_EXECUTION_REQUEST_EXECUTOR_REQUIRED', 'A named executor actor id is required.');
        }
        if ($executorAuthorityRole !== 'platform_admin') {
            $diagnostics[] = self::diagnostic('DELETION_EXECUTION_REQUEST_EXECUTOR_ROLE_INVALID', 'The executor must hold platform_admin authority.');
        }
        if ($executorActorId !== '' && $requesterRecord['actor_id'] !== '' && hash_equals($requesterRecord['actor_id'], $executorActorId)) {
            $diagnostics[] = self::diagnostic('DELETION_EXECUTION_REQUEST_EXECUTOR_NOT_DISTINCT', 'The named executor must differ from the request creator.');
        }

        $reason = trim((string)($input['reason'] ?? ''));
        if ($reason === '') {
            $diagnostics[] = self::diagnostic('DELETION_EXECUTION_REQUEST_REASON_REQUIRED', 'An execution request reason is required.');
        } elseif (strlen($reason) > 2000) {
            $diagnostics[] = self::diagnostic('DELETION_EXECUTION_REQUEST_REASON_TOO_LONG', 'Execution request reason must not exceed 2000 bytes.');
        }

        $requestedAt = self::now($clock);
        $expiresAt = self::parseUtc((string)($input['expires_at_utc'] ?? ''));
        if ($expiresAt === null) {
            $diagnostics[] = self::diagnostic('DELETION_EXECUTION_REQUEST_EXPIRY_INVALID', 'Expiry must be a valid UTC date-time.');
        } else {
            $ttl = $expiresAt->getTimestamp() - $requestedAt->getTimestamp();
            if ($ttl < self::MIN_TTL_SECONDS) {
                $diagnostics[] = self::diagnostic('DELETION_EXECUTION_REQUEST_EXPIRY_TOO_SOON', 'Execution request expiry must be at least five minutes after creation.');
            }
            if ($ttl > self::MAX_TTL_SECONDS) {
                $diagnostics[] = self::diagnostic('DELETION_EXECUTION_REQUEST_EXPIRY_TOO_LATE', 'Execution request expiry must not exceed twenty-four hours.');
            }
        }

        if ($diagnostics !== []) {
            return self::errorResult($expected, $diagnostics);
        }

        $requestedAtText = $requestedAt->format('Y-m-d\TH:i:s.u\Z');
        $expiresAtText = $expiresAt->format('Y-m-d\TH:i:s.u\Z');
        $requestMaterial = [
            'version' => self::VERSION,
            'request_state' => 'requested',
            'requested_at_utc' => $requestedAtText,
            'expires_at_utc' => $expiresAtText,
            'target' => [
                'owner_key' => $ownerKey,
                'target_type' => (string)($target['target_type'] ?? 'owner'),
                'target_path' => $targetPath,
            ],
            'source' => $expected,
            'requester' => $requesterRecord,
            'executor_policy' => [
                'executor_actor_id' => $executorActorId,
                'executor_display_name' => $executorDisplayName !== '' ? $executorDisplayName : $executorActorId,
                'allowed_authority_roles' => ['platform_admin'],
                'required_authority_role' => 'platform_admin',
                'executor_must_differ_from_requester' => 'yes',
                'requires_identity_revalidation' => 'yes',
                'requires_current_packet_revalidation' => 'yes',
                'single_use' => 'yes',
            ],
            'reason' => $reason,
            'execution_contract' => [
                'requires_snapshot_before_execution' => 'yes',
                'requires_current_approval' => 'yes',
                'requires_current_readiness' => 'yes',
                'requires_current_dry_run' => 'yes',
                'request_is_authority' => 'no',
            ],
        ];
        $requestId = $idFactory !== null
            ? trim((string)$idFactory($requestMaterial))
            : self::requestId($requestedAtText, $requestMaterial);
        if ($requestId === '') {
            return self::errorResult($expected, [self::diagnostic('DELETION_EXECUTION_REQUEST_ID_INVALID', 'Execution request id generation failed.')]);
        }

        $record = array_merge($requestMaterial, [
            'status' => 'recorded',
            'effect' => self::EFFECT,
            'request_version' => self::VERSION,
            'request_id' => $requestId,
            'immutable' => 'yes',
            'append_only' => 'yes',
            'can_execute' => 'no',
            'can_apply' => 'no',
            'can_archive' => 'no',
            'can_delete' => 'no',
            'execution_authorized' => 'no',
            'grants_execution_authority' => 'no',
            'requires_separate_execution_capability' => 'yes',
            'request_fingerprint' => self::fingerprint($requestMaterial),
            'provenance' => [
                'capability' => self::class,
                'source' => 'owner_structure_scan',
                'route' => '/apps/studio/tools/owner-structure-scan/deletion-execution-request',
            ],
        ]);

        try {
            $storage = $writer !== null
                ? $writer($record, $root)
                : StudioDeletionExecutionRequestStore::append($record, $root);
        } catch (\Throwable $exception) {
            return self::errorResult($expected, [[
                'code' => 'DELETION_EXECUTION_REQUEST_STORAGE_FAILED',
                'severity' => 'error',
                'message' => $exception->getMessage() !== '' ? $exception->getMessage() : 'Execution request storage failed.',
            ]]);
        }
        $record['storage'] = is_array($storage) ? $storage : [];
        return $record;
    }

    /** @return array<string,mixed>|null */
    public static function latest(array $changeSet, array $dryRun, ?string $root = null): ?array
    {
        return StudioDeletionExecutionRequestStore::latest(
            trim((string)($changeSet['target']['owner_key'] ?? '')),
            trim((string)($changeSet['change_set_fingerprint'] ?? '')),
            trim((string)($dryRun['dry_run_fingerprint'] ?? '')),
            $root
        );
    }

    /** @return array<int,string> */
    private static function packetProblems(
        array $changeSet,
        array $readiness,
        array $dryRun,
        string $changeSetFingerprint,
        string $planFingerprint,
        string $readinessFingerprint,
        string $dryRunFingerprint,
        string $approvalRecordId,
        string $approvalRecordFingerprint,
        string $ownerKey,
        string $targetPath,
        array $currentPacket,
        array $approvalEvidence,
        array $dryRunSource
    ): array {
        $problems = [];
        if ($changeSetFingerprint === '' || $planFingerprint === '' || $readinessFingerprint === '' || $dryRunFingerprint === '' || $approvalRecordId === '' || $approvalRecordFingerprint === '' || $ownerKey === '' || $targetPath === '') {
            $problems[] = 'SOURCE_IDENTITY_MISSING';
        }
        if ((string)($changeSet['immutable'] ?? 'no') !== 'yes' || (string)($changeSet['can_execute'] ?? 'yes') !== 'no' || (string)($changeSet['can_apply'] ?? 'yes') !== 'no') {
            $problems[] = 'CHANGE_SET_CONTRACT_INVALID';
        }
        if ((string)($readiness['effect'] ?? '') !== 'verify' || (string)($readiness['execution_readiness'] ?? '') !== 'ready' || (string)($readiness['execution_eligible'] ?? 'no') !== 'yes' || (string)($readiness['approval_valid'] ?? 'no') !== 'yes') {
            $problems[] = 'READINESS_NOT_READY';
        }
        if ((string)($readiness['can_execute'] ?? 'yes') !== 'no' || (string)($readiness['can_apply'] ?? 'yes') !== 'no' || (string)($readiness['grants_execution_authority'] ?? 'yes') !== 'no') {
            $problems[] = 'READINESS_AUTHORITY_INVALID';
        }
        if ((string)($dryRun['effect'] ?? '') !== 'simulate' || (string)($dryRun['dry_run_state'] ?? '') !== 'ready' || (string)($dryRun['simulation_complete'] ?? 'no') !== 'yes' || (string)($dryRun['execution_eligible_at_simulation'] ?? 'no') !== 'yes') {
            $problems[] = 'DRY_RUN_NOT_READY';
        }
        foreach (['would_execute', 'would_apply', 'would_write', 'would_archive', 'would_delete', 'can_execute', 'can_apply', 'grants_execution_authority'] as $field) {
            if ((string)($dryRun[$field] ?? 'yes') !== 'no') {
                $problems[] = 'DRY_RUN_AUTHORITY_INVALID:' . $field;
            }
        }
        if (self::stringList($dryRun['blocking_reasons'] ?? []) !== []) {
            $problems[] = 'DRY_RUN_BLOCKERS_PRESENT';
        }
        if ((string)($currentPacket['change_set_fingerprint'] ?? '') !== $changeSetFingerprint || (string)($dryRunSource['change_set_fingerprint'] ?? '') !== $changeSetFingerprint) {
            $problems[] = 'CHANGE_SET_FINGERPRINT_MISMATCH';
        }
        if ((string)($currentPacket['plan_fingerprint'] ?? '') !== $planFingerprint || (string)($dryRunSource['plan_fingerprint'] ?? '') !== $planFingerprint) {
            $problems[] = 'PLAN_FINGERPRINT_MISMATCH';
        }
        if ((string)($dryRunSource['readiness_fingerprint'] ?? '') !== $readinessFingerprint) {
            $problems[] = 'READINESS_FINGERPRINT_MISMATCH';
        }
        if ((string)($approvalEvidence['exact_packet_match'] ?? 'no') !== 'yes' || (string)($approvalEvidence['decision'] ?? '') !== 'approved') {
            $problems[] = 'EXACT_APPROVAL_REQUIRED';
        }
        if ((string)($dryRunSource['approval_record_id'] ?? '') !== $approvalRecordId || (string)($dryRunSource['approval_record_fingerprint'] ?? '') !== $approvalRecordFingerprint) {
            $problems[] = 'APPROVAL_EVIDENCE_MISMATCH';
        }
        return array_values(array_unique($problems));
    }

    /** @param array<string,string> $source @return array<string,mixed> */
    private static function errorResult(array $source, array $diagnostics): array
    {
        return [
            'status' => 'error',
            'effect' => self::EFFECT,
            'request_version' => self::VERSION,
            'recorded' => 'no',
            'immutable' => 'yes',
            'append_only' => 'yes',
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
        if ($value instanceof \DateTimeInterface) {
            return new \DateTimeImmutable($value->format('c'));
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

    private static function requestId(string $requestedAt, array $material): string
    {
        $timestamp = preg_replace('/[^0-9TZ]/', '', $requestedAt);
        $nonce = bin2hex(random_bytes(5));
        return 'deletion-execution-request-' . substr((string)$timestamp, 0, 22) . '-' . substr(sha1(self::canonicalJson($material) . '|' . $nonce), 0, 14);
    }

    private static function fingerprint(array $material): string
    {
        return 'deletion-execution-request:' . substr(sha1(self::canonicalJson($material)), 0, 24);
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

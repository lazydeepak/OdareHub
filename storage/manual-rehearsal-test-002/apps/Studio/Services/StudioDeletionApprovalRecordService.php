<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

require_once __DIR__ . '/StudioDeletionApprovalRecordStore.php';

/**
 * Governed Studio capability that records an approval or rejection against one
 * exact deletion change-set fingerprint. It grants no execution authority.
 */
final class StudioDeletionApprovalRecordService
{
    public const EFFECT = 'mutate';
    public const VERSION = 'studio.deletion-approval-record.v1';
    public const DECISION_APPROVED = 'approved';
    public const DECISION_REJECTED = 'rejected';

    /**
     * @param array<string,mixed> $changeSet
     * @param array<string,mixed> $input
     * @param array<string,mixed>|null $actor
     * @param callable(array<string,mixed>,?string):array<string,mixed>|null $writer
     * @param callable():mixed|null $clock
     * @param callable(array<string,mixed>):string|null $idFactory
     * @return array<string,mixed>
     */
    public static function record(
        array $changeSet,
        array $input,
        ?array $actor,
        ?string $root = null,
        ?callable $writer = null,
        ?callable $clock = null,
        ?callable $idFactory = null
    ): array {
        $diagnostics = [];
        $changeSetFingerprint = trim((string)($changeSet['change_set_fingerprint'] ?? ''));
        $planFingerprint = trim((string)($changeSet['source_plan']['fingerprint'] ?? ''));
        $presentedChangeSetFingerprint = trim((string)($input['change_set_fingerprint'] ?? ''));
        $presentedPlanFingerprint = trim((string)($input['plan_fingerprint'] ?? ''));
        $decision = self::normalizeDecision((string)($input['decision'] ?? ''));
        $reason = trim((string)($input['reason'] ?? ''));
        $providedConfirmations = self::stringList($input['confirmations'] ?? []);
        $approvalContract = self::arrayValue($changeSet, 'approval_contract');
        $approvalBindsTo = self::arrayValue($approvalContract, 'approval_binds_to');
        $requiredConfirmations = self::stringList($approvalContract['required_confirmations'] ?? []);
        $approvalReadiness = trim((string)($approvalContract['approval_readiness'] ?? 'blocked'));
        $target = self::arrayValue($changeSet, 'target');

        if (!self::isPlatformAdmin($actor)) {
            $diagnostics[] = self::diagnostic('DELETION_APPROVAL_ACTOR_UNAUTHORIZED', 'Only a platform administrator may record a deletion approval decision.');
        }
        if ($decision === '') {
            $diagnostics[] = self::diagnostic('DELETION_APPROVAL_DECISION_INVALID', 'Decision must be approved or rejected.');
        }
        if ($reason === '') {
            $diagnostics[] = self::diagnostic('DELETION_APPROVAL_REASON_REQUIRED', 'A decision reason is required.');
        } elseif (strlen($reason) > 2000) {
            $diagnostics[] = self::diagnostic('DELETION_APPROVAL_REASON_TOO_LONG', 'Decision reason must not exceed 2000 bytes.');
        }

        $packetProblems = self::packetProblems($changeSet, $changeSetFingerprint, $planFingerprint, $approvalContract, $approvalBindsTo);
        if ($packetProblems !== []) {
            $diagnostics[] = [
                'code' => 'DELETION_APPROVAL_CHANGE_SET_INVALID',
                'severity' => 'error',
                'message' => 'Approval recording requires a valid immutable non-executable deletion change set.',
                'reasons' => $packetProblems,
            ];
        }
        if ($presentedChangeSetFingerprint === '' || !hash_equals($changeSetFingerprint, $presentedChangeSetFingerprint)) {
            $diagnostics[] = self::diagnostic('DELETION_APPROVAL_CHANGE_SET_STALE', 'The submitted change-set fingerprint does not match the current server-side packet.');
        }
        if ($presentedPlanFingerprint === '' || !hash_equals($planFingerprint, $presentedPlanFingerprint)) {
            $diagnostics[] = self::diagnostic('DELETION_APPROVAL_PLAN_STALE', 'The submitted plan fingerprint does not match the current server-side packet.');
        }

        $missingConfirmations = array_values(array_diff($requiredConfirmations, $providedConfirmations));
        $unexpectedConfirmations = array_values(array_diff($providedConfirmations, $requiredConfirmations));
        if ($decision === self::DECISION_APPROVED && $approvalReadiness !== 'ready') {
            $diagnostics[] = self::diagnostic('DELETION_APPROVAL_NOT_READY', 'This deletion change set is not ready for approval.');
        }
        if ($decision === self::DECISION_APPROVED && $missingConfirmations !== []) {
            $diagnostics[] = [
                'code' => 'DELETION_APPROVAL_CONFIRMATIONS_MISSING',
                'severity' => 'error',
                'message' => 'All required approval confirmations must be supplied.',
                'missing_confirmations' => $missingConfirmations,
            ];
        }
        if ($unexpectedConfirmations !== []) {
            $diagnostics[] = [
                'code' => 'DELETION_APPROVAL_CONFIRMATIONS_UNKNOWN',
                'severity' => 'error',
                'message' => 'Submitted confirmations are not part of the current approval contract.',
                'unexpected_confirmations' => $unexpectedConfirmations,
            ];
        }

        if ($diagnostics !== []) {
            return self::errorResult($changeSetFingerprint, $planFingerprint, $decision, $diagnostics);
        }

        $recordedAt = self::recordedAt($clock);
        $actorRecord = self::actorRecord((array)$actor);
        $recordMaterial = [
            'version' => self::VERSION,
            'decision' => $decision,
            'recorded_at_utc' => $recordedAt,
            'target' => $target,
            'source' => [
                'change_set_version' => (string)($changeSet['change_set_version'] ?? ''),
                'change_set_fingerprint' => $changeSetFingerprint,
                'plan_fingerprint' => $planFingerprint,
            ],
            'approver' => $actorRecord,
            'reason' => $reason,
            'confirmations' => [
                'required' => $requiredConfirmations,
                'provided' => $providedConfirmations,
                'satisfied' => $decision === self::DECISION_APPROVED ? 'yes' : ($missingConfirmations === [] ? 'yes' : 'not_required'),
            ],
            'approval_readiness_at_record' => $approvalReadiness,
        ];
        $recordId = $idFactory !== null
            ? trim((string)$idFactory($recordMaterial))
            : self::recordId($recordedAt, $recordMaterial);
        if ($recordId === '') {
            return self::errorResult($changeSetFingerprint, $planFingerprint, $decision, [
                self::diagnostic('DELETION_APPROVAL_RECORD_ID_INVALID', 'Approval record id generation failed.'),
            ]);
        }

        $record = array_merge($recordMaterial, [
            'status' => 'recorded',
            'effect' => self::EFFECT,
            'record_version' => self::VERSION,
            'record_id' => $recordId,
            'immutable' => 'yes',
            'append_only' => 'yes',
            'can_execute' => 'no',
            'can_apply' => 'no',
            'grants_execution_authority' => 'no',
            'record_fingerprint' => self::fingerprint($recordMaterial),
            'provenance' => [
                'capability' => self::class,
                'source' => 'owner_structure_scan',
                'route' => '/apps/studio/tools/owner-structure-scan/deletion-approval-record',
            ],
        ]);

        try {
            $storage = $writer !== null
                ? $writer($record, $root)
                : StudioDeletionApprovalRecordStore::append($record, $root);
        } catch (\Throwable $exception) {
            return self::errorResult($changeSetFingerprint, $planFingerprint, $decision, [[
                'code' => 'DELETION_APPROVAL_STORAGE_FAILED',
                'severity' => 'error',
                'message' => $exception->getMessage() !== '' ? $exception->getMessage() : 'Approval record storage failed.',
            ]]);
        }

        $record['storage'] = is_array($storage) ? $storage : [];
        return $record;
    }

    /** @return array<string,mixed>|null */
    public static function latest(array $changeSet, ?string $root = null): ?array
    {
        $ownerKey = trim((string)($changeSet['target']['owner_key'] ?? ''));
        $fingerprint = trim((string)($changeSet['change_set_fingerprint'] ?? ''));
        return StudioDeletionApprovalRecordStore::latest($ownerKey, $fingerprint, $root);
    }

    /** @param array<string,mixed> $changeSet @param array<string,mixed> $approvalContract @param array<string,mixed> $approvalBindsTo @return array<int,string> */
    private static function packetProblems(array $changeSet, string $changeSetFingerprint, string $planFingerprint, array $approvalContract, array $approvalBindsTo): array
    {
        $problems = [];
        if ($changeSetFingerprint === '' || $planFingerprint === '') {
            $problems[] = 'PACKET_IDENTITY_MISSING';
        }
        if ((string)($changeSet['immutable'] ?? 'no') !== 'yes') {
            $problems[] = 'PACKET_NOT_IMMUTABLE';
        }
        if ((string)($changeSet['can_execute'] ?? 'yes') !== 'no' || (string)($changeSet['can_apply'] ?? 'yes') !== 'no') {
            $problems[] = 'PACKET_EXECUTION_AUTHORITY_INVALID';
        }
        if ((string)($changeSet['requires_approval'] ?? 'no') !== 'yes' || (string)($approvalContract['required'] ?? 'no') !== 'yes') {
            $problems[] = 'APPROVAL_CONTRACT_MISSING';
        }
        if ((string)($approvalBindsTo['change_set_fingerprint'] ?? '') !== $changeSetFingerprint) {
            $problems[] = 'CHANGE_SET_BINDING_MISMATCH';
        }
        if ((string)($approvalBindsTo['plan_fingerprint'] ?? '') !== $planFingerprint) {
            $problems[] = 'PLAN_BINDING_MISMATCH';
        }
        return $problems;
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

    private static function normalizeDecision(string $decision): string
    {
        $decision = strtolower(trim($decision));
        return match ($decision) {
            'approve', 'approved' => self::DECISION_APPROVED,
            'reject', 'rejected' => self::DECISION_REJECTED,
            default => '',
        };
    }

    /** @param callable():mixed|null $clock */
    private static function recordedAt(?callable $clock): string
    {
        $value = $clock !== null ? $clock() : new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        if ($value instanceof \DateTimeImmutable) {
            return $value->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
        }
        if ($value instanceof \DateTime) {
            $copy = clone $value;
            return $copy->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d\TH:i:s.u\Z');
        }
        $text = trim((string)$value);
        return $text !== '' ? $text : gmdate('Y-m-d\TH:i:s\Z');
    }

    /** @param array<string,mixed> $material */
    private static function recordId(string $recordedAt, array $material): string
    {
        $timestamp = preg_replace('/[^0-9TZ]/', '', $recordedAt);
        $nonce = bin2hex(random_bytes(5));
        return 'deletion-approval-' . substr((string)$timestamp, 0, 22) . '-' . substr(sha1(self::canonicalJson($material) . '|' . $nonce), 0, 14);
    }

    /** @param array<string,mixed> $material */
    private static function fingerprint(array $material): string
    {
        return 'deletion-approval-record:' . substr(sha1(self::canonicalJson($material)), 0, 24);
    }

    /** @param array<string,mixed> $material */
    private static function canonicalJson(array $material): string
    {
        $normalized = self::sortRecursively($material);
        $encoded = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return is_string($encoded) ? $encoded : serialize($normalized);
    }

    /** @param mixed $value @return mixed */
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

    /** @param array<int,array<string,mixed>> $diagnostics @return array<string,mixed> */
    private static function errorResult(string $changeSetFingerprint, string $planFingerprint, string $decision, array $diagnostics): array
    {
        return [
            'status' => 'error',
            'effect' => self::EFFECT,
            'record_version' => self::VERSION,
            'recorded' => 'no',
            'decision' => $decision,
            'immutable' => 'yes',
            'can_execute' => 'no',
            'can_apply' => 'no',
            'grants_execution_authority' => 'no',
            'source' => [
                'change_set_fingerprint' => $changeSetFingerprint,
                'plan_fingerprint' => $planFingerprint,
            ],
            'diagnostics' => $diagnostics,
        ];
    }

    /** @return array<string,string> */
    private static function diagnostic(string $code, string $message): array
    {
        return ['code' => $code, 'severity' => 'error', 'message' => $message];
    }

    /** @param array<string,mixed> $source @return array<string,mixed> */
    private static function arrayValue(array $source, string $key): array
    {
        return isset($source[$key]) && is_array($source[$key]) ? $source[$key] : [];
    }

    /** @param mixed $value @return array<int,string> */
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
}

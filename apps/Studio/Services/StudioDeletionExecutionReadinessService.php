<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

/**
 * Canonical read-only verifier that binds the current deletion change set to
 * immutable approval provenance and reports execution eligibility. It never
 * executes, applies, archives, or deletes anything.
 */
final class StudioDeletionExecutionReadinessService
{
    public const EFFECT = 'verify';
    public const VERSION = 'studio.deletion-execution-readiness.v1';

    public const STATE_READY = 'ready';
    public const STATE_NOT_APPROVED = 'not_approved';
    public const STATE_REJECTED = 'rejected';
    public const STATE_STALE = 'stale';
    public const STATE_BLOCKED = 'blocked';
    public const STATE_UNKNOWN = 'unknown';

    /**
     * @param array<string,mixed> $changeSet
     * @param array<string,mixed>|null $exactApproval
     * @param array<string,mixed>|null $latestOwnerApproval
     * @return array<string,mixed>
     */
    public static function assess(array $changeSet, ?array $exactApproval, ?array $latestOwnerApproval = null): array
    {
        $target = self::arrayValue($changeSet, 'target');
        $approvalContract = self::arrayValue($changeSet, 'approval_contract');
        $changeSetFingerprint = trim((string)($changeSet['change_set_fingerprint'] ?? ''));
        $planFingerprint = trim((string)($changeSet['source_plan']['fingerprint'] ?? ''));
        $ownerKey = trim((string)($target['owner_key'] ?? ''));
        $targetPath = self::normalizePath((string)($target['target_path'] ?? ''));
        $requiredConfirmations = self::stringList($approvalContract['required_confirmations'] ?? []);
        $approvalReadiness = trim((string)($approvalContract['approval_readiness'] ?? 'blocked'));
        $blockingReasons = [];
        $diagnostics = [];

        $packetProblems = self::packetProblems($changeSet, $changeSetFingerprint, $planFingerprint, $ownerKey, $targetPath, $approvalContract);
        if ($packetProblems !== []) {
            $blockingReasons = array_merge($blockingReasons, $packetProblems);
            $diagnostics[] = self::diagnostic('DELETION_EXECUTION_READINESS_CHANGE_SET_INVALID', 'Execution readiness requires a current immutable non-executable deletion change set.', ['reasons' => $packetProblems]);
            return self::result(self::STATE_UNKNOWN, $changeSetFingerprint, $planFingerprint, $target, $exactApproval, $latestOwnerApproval, $approvalReadiness, $requiredConfirmations, $blockingReasons, $diagnostics);
        }

        if ($exactApproval === null) {
            if ($latestOwnerApproval !== null) {
                $latestSource = self::arrayValue($latestOwnerApproval, 'source');
                $latestFingerprint = trim((string)($latestSource['change_set_fingerprint'] ?? ''));
                $latestPlanFingerprint = trim((string)($latestSource['plan_fingerprint'] ?? ''));
                if ($latestFingerprint !== '' && !hash_equals($changeSetFingerprint, $latestFingerprint)) {
                    $blockingReasons[] = 'APPROVAL_CHANGE_SET_STALE';
                }
                if ($latestPlanFingerprint !== '' && !hash_equals($planFingerprint, $latestPlanFingerprint)) {
                    $blockingReasons[] = 'APPROVAL_PLAN_STALE';
                }
                $diagnostics[] = self::diagnostic('DELETION_EXECUTION_READINESS_APPROVAL_STALE', 'The latest owner approval decision is bound to an older deletion packet.');
                return self::result(self::STATE_STALE, $changeSetFingerprint, $planFingerprint, $target, null, $latestOwnerApproval, $approvalReadiness, $requiredConfirmations, $blockingReasons, $diagnostics);
            }

            $blockingReasons[] = 'APPROVAL_RECORD_REQUIRED';
            $diagnostics[] = self::diagnostic('DELETION_EXECUTION_READINESS_APPROVAL_MISSING', 'No approval decision exists for the current deletion change set.');
            return self::result(self::STATE_NOT_APPROVED, $changeSetFingerprint, $planFingerprint, $target, null, null, $approvalReadiness, $requiredConfirmations, $blockingReasons, $diagnostics);
        }

        $recordProblems = self::recordProblems($exactApproval, $changeSetFingerprint, $planFingerprint, $ownerKey, $targetPath);
        if ($recordProblems !== []) {
            $blockingReasons = array_merge($blockingReasons, $recordProblems);
            $diagnostics[] = self::diagnostic('DELETION_EXECUTION_READINESS_APPROVAL_INVALID', 'The approval record failed integrity or provenance validation.', ['reasons' => $recordProblems]);
            return self::result(self::STATE_BLOCKED, $changeSetFingerprint, $planFingerprint, $target, $exactApproval, $latestOwnerApproval, $approvalReadiness, $requiredConfirmations, $blockingReasons, $diagnostics);
        }

        $decision = strtolower(trim((string)($exactApproval['decision'] ?? '')));
        if ($decision === 'rejected') {
            $blockingReasons[] = 'APPROVAL_REJECTED';
            $diagnostics[] = self::diagnostic('DELETION_EXECUTION_READINESS_REJECTED', 'The latest exact-packet decision rejected deletion execution.');
            return self::result(self::STATE_REJECTED, $changeSetFingerprint, $planFingerprint, $target, $exactApproval, $latestOwnerApproval, $approvalReadiness, $requiredConfirmations, $blockingReasons, $diagnostics);
        }

        if ($approvalReadiness !== 'ready') {
            $blockingReasons[] = 'CURRENT_PACKET_NOT_READY';
            $diagnostics[] = self::diagnostic('DELETION_EXECUTION_READINESS_PACKET_BLOCKED', 'The current deletion change set is no longer ready for approval.');
            return self::result(self::STATE_STALE, $changeSetFingerprint, $planFingerprint, $target, $exactApproval, $latestOwnerApproval, $approvalReadiness, $requiredConfirmations, $blockingReasons, $diagnostics);
        }

        $recordConfirmations = self::arrayValue($exactApproval, 'confirmations');
        $recordRequired = self::stringList($recordConfirmations['required'] ?? []);
        $recordProvided = self::stringList($recordConfirmations['provided'] ?? []);
        $missingCurrentConfirmations = array_values(array_diff($requiredConfirmations, $recordProvided));
        if ($recordRequired !== $requiredConfirmations || $missingCurrentConfirmations !== [] || (string)($recordConfirmations['satisfied'] ?? '') !== 'yes') {
            $blockingReasons[] = 'APPROVAL_CONFIRMATION_CONTRACT_STALE';
            $diagnostics[] = self::diagnostic('DELETION_EXECUTION_READINESS_CONFIRMATIONS_STALE', 'The approval confirmations do not satisfy the current deletion packet contract.', ['missing_confirmations' => $missingCurrentConfirmations]);
            return self::result(self::STATE_STALE, $changeSetFingerprint, $planFingerprint, $target, $exactApproval, $latestOwnerApproval, $approvalReadiness, $requiredConfirmations, $blockingReasons, $diagnostics);
        }

        return self::result(self::STATE_READY, $changeSetFingerprint, $planFingerprint, $target, $exactApproval, $latestOwnerApproval, $approvalReadiness, $requiredConfirmations, [], []);
    }

    /** @param array<string,mixed> $changeSet @param array<string,mixed> $approvalContract @return array<int,string> */
    private static function packetProblems(array $changeSet, string $changeSetFingerprint, string $planFingerprint, string $ownerKey, string $targetPath, array $approvalContract): array
    {
        $problems = [];
        if ($changeSetFingerprint === '' || $planFingerprint === '' || $ownerKey === '' || $targetPath === '') {
            $problems[] = 'CURRENT_PACKET_IDENTITY_MISSING';
        }
        if ((string)($changeSet['immutable'] ?? 'no') !== 'yes') {
            $problems[] = 'CURRENT_PACKET_NOT_IMMUTABLE';
        }
        if ((string)($changeSet['can_execute'] ?? 'yes') !== 'no' || (string)($changeSet['can_apply'] ?? 'yes') !== 'no') {
            $problems[] = 'CURRENT_PACKET_EXECUTION_AUTHORITY_INVALID';
        }
        if ((string)($changeSet['requires_approval'] ?? 'no') !== 'yes' || (string)($approvalContract['required'] ?? 'no') !== 'yes') {
            $problems[] = 'CURRENT_PACKET_APPROVAL_CONTRACT_MISSING';
        }
        return $problems;
    }

    /** @param array<string,mixed> $record @return array<int,string> */
    private static function recordProblems(array $record, string $changeSetFingerprint, string $planFingerprint, string $ownerKey, string $targetPath): array
    {
        $problems = [];
        $source = self::arrayValue($record, 'source');
        $target = self::arrayValue($record, 'target');
        $storage = self::arrayValue($record, 'storage');
        $decision = strtolower(trim((string)($record['decision'] ?? '')));

        if ((string)($record['status'] ?? '') !== 'recorded') {
            $problems[] = 'APPROVAL_RECORD_STATUS_INVALID';
        }
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            $problems[] = 'APPROVAL_DECISION_INVALID';
        }
        if ((string)($record['immutable'] ?? 'no') !== 'yes' || (string)($record['append_only'] ?? 'no') !== 'yes') {
            $problems[] = 'APPROVAL_RECORD_MUTABILITY_INVALID';
        }
        if ((string)($record['can_execute'] ?? 'yes') !== 'no' || (string)($record['can_apply'] ?? 'yes') !== 'no' || (string)($record['grants_execution_authority'] ?? 'yes') !== 'no') {
            $problems[] = 'APPROVAL_RECORD_AUTHORITY_INVALID';
        }
        if ((string)($source['change_set_fingerprint'] ?? '') !== $changeSetFingerprint) {
            $problems[] = 'APPROVAL_CHANGE_SET_FINGERPRINT_MISMATCH';
        }
        if ((string)($source['plan_fingerprint'] ?? '') !== $planFingerprint) {
            $problems[] = 'APPROVAL_PLAN_FINGERPRINT_MISMATCH';
        }
        if ((string)($target['owner_key'] ?? '') !== $ownerKey) {
            $problems[] = 'APPROVAL_TARGET_OWNER_MISMATCH';
        }
        if (self::normalizePath((string)($target['target_path'] ?? '')) !== $targetPath) {
            $problems[] = 'APPROVAL_TARGET_PATH_MISMATCH';
        }
        if ((string)($storage['storage_scope'] ?? '') !== 'studio_provenance' || (string)($storage['immutable'] ?? 'no') !== 'yes' || (string)($storage['append_only'] ?? 'no') !== 'yes') {
            $problems[] = 'APPROVAL_STORAGE_PROVENANCE_INVALID';
        }
        $expectedFingerprint = self::approvalRecordFingerprint($record);
        if ($expectedFingerprint === '' || !hash_equals($expectedFingerprint, (string)($record['record_fingerprint'] ?? ''))) {
            $problems[] = 'APPROVAL_RECORD_FINGERPRINT_INVALID';
        }
        return $problems;
    }

    /** @param array<string,mixed> $record */
    private static function approvalRecordFingerprint(array $record): string
    {
        $material = [
            'version' => (string)($record['version'] ?? $record['record_version'] ?? ''),
            'decision' => (string)($record['decision'] ?? ''),
            'recorded_at_utc' => (string)($record['recorded_at_utc'] ?? ''),
            'target' => self::arrayValue($record, 'target'),
            'source' => self::arrayValue($record, 'source'),
            'approver' => self::arrayValue($record, 'approver'),
            'reason' => (string)($record['reason'] ?? ''),
            'confirmations' => self::arrayValue($record, 'confirmations'),
            'approval_readiness_at_record' => (string)($record['approval_readiness_at_record'] ?? ''),
        ];
        return 'deletion-approval-record:' . substr(sha1(self::canonicalJson($material)), 0, 24);
    }

    /** @return array<string,mixed> */
    private static function result(string $state, string $changeSetFingerprint, string $planFingerprint, array $target, ?array $exactApproval, ?array $latestOwnerApproval, string $approvalReadiness, array $requiredConfirmations, array $blockingReasons, array $diagnostics): array
    {
        $record = $exactApproval ?? $latestOwnerApproval;
        $recordSource = is_array($record) ? self::arrayValue($record, 'source') : [];
        $recordConfirmations = is_array($record) ? self::arrayValue($record, 'confirmations') : [];
        $ready = $state === self::STATE_READY;
        $material = [
            'version' => self::VERSION,
            'state' => $state,
            'current_change_set_fingerprint' => $changeSetFingerprint,
            'current_plan_fingerprint' => $planFingerprint,
            'target' => $target,
            'approval_record_id' => (string)($record['record_id'] ?? ''),
            'approval_record_fingerprint' => (string)($record['record_fingerprint'] ?? ''),
            'blocking_reasons' => array_values(array_unique($blockingReasons)),
        ];

        return [
            'status' => in_array($state, [self::STATE_UNKNOWN, self::STATE_BLOCKED], true) ? 'partial' : 'ok',
            'effect' => self::EFFECT,
            'readiness_version' => self::VERSION,
            'execution_readiness' => $state,
            'execution_eligible' => $ready ? 'yes' : 'no',
            'approval_valid' => $ready ? 'yes' : 'no',
            'can_execute' => 'no',
            'can_apply' => 'no',
            'grants_execution_authority' => 'no',
            'requires_separate_execution_capability' => 'yes',
            'current_packet' => [
                'change_set_fingerprint' => $changeSetFingerprint,
                'plan_fingerprint' => $planFingerprint,
                'approval_readiness' => $approvalReadiness,
                'target' => $target,
                'required_confirmations' => $requiredConfirmations,
            ],
            'approval_evidence' => [
                'present' => is_array($record) ? 'yes' : 'no',
                'exact_packet_match' => $exactApproval !== null ? 'yes' : 'no',
                'decision' => (string)($record['decision'] ?? ''),
                'record_id' => (string)($record['record_id'] ?? ''),
                'record_fingerprint' => (string)($record['record_fingerprint'] ?? ''),
                'recorded_at_utc' => (string)($record['recorded_at_utc'] ?? ''),
                'approver' => is_array($record) ? self::arrayValue($record, 'approver') : [],
                'source_change_set_fingerprint' => (string)($recordSource['change_set_fingerprint'] ?? ''),
                'source_plan_fingerprint' => (string)($recordSource['plan_fingerprint'] ?? ''),
                'confirmations_satisfied' => (string)($recordConfirmations['satisfied'] ?? ''),
                'storage' => is_array($record) ? self::arrayValue($record, 'storage') : [],
            ],
            'blocking_reasons' => array_values(array_unique($blockingReasons)),
            'readiness_fingerprint' => 'deletion-execution-readiness:' . substr(sha1(self::canonicalJson($material)), 0, 24),
            'diagnostics' => $diagnostics,
        ];
    }

    /** @return array<string,mixed> */
    private static function diagnostic(string $code, string $message, array $extra = []): array
    {
        return array_merge(['code' => $code, 'severity' => 'error', 'message' => $message], $extra);
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

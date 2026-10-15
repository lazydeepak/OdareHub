<?php
declare(strict_types=1);

namespace Platform\Updates;

use Platform\Health\PostUpdateHealthVerificationResult;
use RuntimeException;

/**
 * Disposable release transaction controller. It journals control transitions
 * only; it never applies packages, executes SQL, commits identity, or resumes
 * an ambiguous transaction.
 */
final class DisposableReleaseTransactionService
{
    private const ACTIVE = ['prepared', 'awaiting_approval', 'approved_for_disposable_rehearsal', 'executing', 'verifying'];
    private const TERMINAL = ['completed', 'blocked', 'recovery_required', 'indeterminate'];

    public function __construct(private readonly DisposableReleaseTransactionJournal $journal) {}

    public function prepare(TrustedReleasePreflightEvidence $preflight, string $environmentToken): array
    {
        $preflightValues = $preflight->toArray();
        if ($environmentToken !== 'disposable-release-rehearsal-v1') {
            throw new RuntimeException('explicit disposable environment authority is required');
        }
        $installation = $preflightValues['installation_identity_fingerprint'];
        foreach (['candidate_package_sha256', 'release_statement_fingerprint', 'trust_store_fingerprint', 'migration_plan_fingerprint', 'target_schema_identity', 'recovery_point_identity'] as $field) {
            if (trim((string)($preflightValues[$field] ?? '')) === '') {
                throw new RuntimeException('preflight is missing transaction binding: ' . $field);
            }
        }
        if ($installation === '') {
            throw new RuntimeException('preflight is missing installation identity fingerprint');
        }
        foreach ($this->journal->transactionIds() as $id) {
            $existing = $this->read($id);
            if (($existing['ok'] ?? false) !== true) {
                throw new RuntimeException('existing transaction journal is indeterminate');
            }
            if (($existing['ok'] ?? false) === true && in_array($existing['state'] ?? null, self::ACTIVE, true)
                && ($existing['record']['installation_identity_fingerprint'] ?? null) === $installation) {
                throw new RuntimeException('one active transaction per installation is enforced');
            }
        }
        $record = [
            'record_version' => 'susankhya.release-transaction.v1',
            'transaction_id' => bin2hex(random_bytes(16)),
            'environment' => 'disposable',
            'installation_identity_fingerprint' => $installation,
            'candidate_build_id' => (string)($preflightValues['candidate_build_id'] ?? ''),
            'candidate_release_version' => (string)($preflightValues['candidate_release_version'] ?? ''),
            'candidate_package_sha256' => $preflightValues['candidate_package_sha256'],
            'release_statement_fingerprint' => $preflightValues['release_statement_fingerprint'],
            'trust_store_fingerprint' => $preflightValues['trust_store_fingerprint'],
            'migration_plan_fingerprint' => $preflightValues['migration_plan_fingerprint'],
            'source_schema_identity' => (string)($preflightValues['source_schema_identity'] ?? 'unknown'),
            'target_schema_identity' => $preflightValues['target_schema_identity'],
            'recovery_point_id' => $preflightValues['recovery_point_identity'],
            'preflight_evidence_fingerprint' => $preflight->fingerprint(),
            'health_binding' => [
                'candidate_build_id' => (string)($preflightValues['candidate_build_id'] ?? ''),
                'candidate_release_version' => (string)($preflightValues['candidate_release_version'] ?? ''),
                'candidate_package_sha256' => $preflightValues['candidate_package_sha256'],
                'installation_identity_fingerprint' => $installation,
                'trust_store_fingerprint' => $preflightValues['trust_store_fingerprint'],
                'migration_plan_fingerprint' => $preflightValues['migration_plan_fingerprint'],
                'target_schema_identity' => $preflightValues['target_schema_identity'],
                'recovery_point_identity' => $preflightValues['recovery_point_identity'],
            ],
            'execution_authorized' => false,
            'live_mutation_performed' => false,
        ];
        $this->journal->create($record['transaction_id'], $record);
        $this->journal->append($record['transaction_id'], 'awaiting_approval', ['event' => 'awaiting_approval']);
        return $this->read($record['transaction_id']);
    }

    /** @return array<string,mixed> */
    public function read(string $transactionId): array
    {
        $journal = $this->journal->read($transactionId);
        if (($journal['ok'] ?? false) !== true) {
            return ['ok' => false, 'state' => 'indeterminate', 'error' => $journal['error'] ?? 'journal unavailable'];
        }
        $events = $journal['events'];
        if ($events === []) {
            return ['ok' => false, 'state' => 'missing'];
        }
        return ['ok' => true, 'state' => $events[count($events) - 1]['state'], 'record' => $events[0]['payload']['record'] ?? [], 'events' => $events];
    }

    public function markExecuting(string $transactionId, TrustedReleasePreflightEvidence $currentPreflight, string $environmentToken): array
    {
        return $this->advance($transactionId, 'executing', $currentPreflight, $environmentToken);
    }

    public function markVerifying(string $transactionId, TrustedReleasePreflightEvidence $currentPreflight, string $environmentToken): array
    {
        return $this->advance($transactionId, 'verifying', $currentPreflight, $environmentToken);
    }

    /** @param array<string,mixed> $expected */
    public function bindHealthExpectations(string $transactionId, array $expected, string $environmentToken): array
    {
        $this->assertEnvironment($environmentToken);
        $current = $this->read($transactionId);
        if (($current['state'] ?? null) !== 'approved_for_disposable_rehearsal') {
            throw new RuntimeException('health expectations must be bound after approval and before rehearsal');
        }
        return $this->journal->append($transactionId, 'approved_for_disposable_rehearsal', [
            'event' => 'health_expectations_bound',
            'expectations_digest' => self::fingerprint($expected),
        ]);
    }

    public function complete(string $transactionId, PostUpdateHealthVerificationResult $health, string $environmentToken): array
    {
        $this->assertEnvironment($environmentToken);
        $current = $this->read($transactionId);
        if (($current['state'] ?? null) !== 'verifying') {
            throw new RuntimeException('completion requires verifying state');
        }
        $record = $current['record'];
        $expectationsDigest = null;
        foreach ($current['events'] as $event) {
            if (($event['payload']['event'] ?? null) === 'health_expectations_bound') {
                $expectationsDigest = $event['payload']['expectations_digest'] ?? null;
            }
        }
        if ($health->transactionId() !== $transactionId || $health->status() !== 'verified'
            || $health->preflightFingerprint() !== ($record['preflight_evidence_fingerprint'] ?? null)
            || $health->bindingDigest() !== self::fingerprint((array)($record['health_binding'] ?? []))
            || $health->expectationsDigest() !== $expectationsDigest) {
            throw new RuntimeException('health evidence is not bound to this transaction');
        }
        return $this->journal->append($transactionId, 'completed', ['event' => 'health_verified', 'health' => $health->toArray()]);
    }

    public function markRecoveryRequired(string $transactionId, string $reason, string $environmentToken): array
    {
        $this->assertEnvironment($environmentToken);
        $current = $this->read($transactionId);
        if (!in_array($current['state'] ?? null, ['approved_for_disposable_rehearsal', 'executing', 'verifying'], true)) {
            throw new RuntimeException('recovery classification requires an active transaction');
        }
        return $this->journal->append($transactionId, 'recovery_required', ['event' => 'recovery_required', 'reason' => $reason]);
    }

    public function markBlocked(string $transactionId, string $reason, string $environmentToken): array
    {
        $this->assertEnvironment($environmentToken);
        $current = $this->read($transactionId);
        if (!in_array($current['state'] ?? null, self::ACTIVE, true)) {
            throw new RuntimeException('blocked classification requires an active transaction');
        }
        return $this->journal->append($transactionId, 'blocked', ['event' => 'blocked', 'reason' => $reason]);
    }

    public static function fingerprint(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function advance(string $transactionId, string $target, TrustedReleasePreflightEvidence $preflight, string $environmentToken): array
    {
        $this->assertEnvironment($environmentToken);
        $current = $this->read($transactionId);
        if (!in_array($current['state'] ?? null, ['approved_for_disposable_rehearsal', 'executing'], true)
            || ($target === 'executing' && $current['state'] !== 'approved_for_disposable_rehearsal')
            || ($target === 'verifying' && $current['state'] !== 'executing')) {
            throw new RuntimeException('invalid transaction state transition');
        }
        if (($current['record']['preflight_evidence_fingerprint'] ?? null) !== $preflight->fingerprint()) {
            throw new RuntimeException('fresh preflight binding is required');
        }
        $expectationsBound = false;
        foreach ($current['events'] as $event) {
            $expectationsBound = $expectationsBound || (($event['payload']['event'] ?? null) === 'health_expectations_bound');
        }
        if (!$expectationsBound) {
            throw new RuntimeException('health expectations must be bound before rehearsal');
        }
        return $this->journal->append($transactionId, $target, ['event' => $target]);
    }

    private function assertEnvironment(string $token): void
    {
        if ($token !== 'disposable-release-rehearsal-v1') {
            throw new RuntimeException('transaction operation requires disposable environment authority');
        }
    }
}

<?php
declare(strict_types=1);

namespace Platform\Updates;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/** Explicit simulator only; it does not authenticate a real administrator. */
final class DisposableAdministratorApprovalAuthority
{
    public function __construct(private readonly DisposableReleaseTransactionJournal $journal) {}

    /** @return array<string,mixed> */
    public function issue(string $transactionId, DateTimeImmutable $expiresAt): array
    {
        if ($expiresAt <= new DateTimeImmutable('now', new DateTimeZone('UTC'))) {
            throw new RuntimeException('approval expiry must be in the future');
        }
        $current = $this->journal->read($transactionId);
        if (($current['ok'] ?? false) !== true || $current['events'] === []
            || ($current['events'][count($current['events']) - 1]['state'] ?? null) !== 'awaiting_approval') {
            throw new RuntimeException('approval can only be issued while awaiting approval');
        }
        $token = bin2hex(random_bytes(24));
        $this->journal->append($transactionId, 'awaiting_approval', [
            'event' => 'approval_challenge_issued',
            'challenge_sha256' => hash('sha256', $token),
            'expires_at' => $expiresAt->format('Y-m-d\TH:i:s\Z'),
        ]);
        return ['transaction_id' => $transactionId, 'token' => $token, 'expires_at' => $expiresAt->format('Y-m-d\TH:i:s\Z')];
    }

    /** @return array<string,mixed> */
    public function revoke(string $transactionId): array
    {
        return $this->journal->append($transactionId, 'awaiting_approval', ['event' => 'approval_revoked']);
    }

    /** @return array<string,mixed> */
    public function approve(string $transactionId, string $approverId, string $token, array $currentPreflight, DateTimeImmutable $now): array
    {
        if (!str_starts_with($approverId, 'simulated:') || trim(substr($approverId, 9)) === '') {
            throw new RuntimeException('only explicitly simulated approvers are allowed');
        }
        $events = $this->journal->read($transactionId);
        if (($events['ok'] ?? false) !== true || $events['events'] === []) {
            throw new RuntimeException('approval journal is unavailable');
        }
        $last = $events['events'][count($events['events']) - 1];
        if (($last['state'] ?? null) !== 'awaiting_approval') {
            throw new RuntimeException('approval is stale, consumed, or transaction is not awaiting approval');
        }
        $challenge = null;
        $revoked = false;
        foreach ($events['events'] as $event) {
            if (($event['payload']['event'] ?? null) === 'approval_challenge_issued') {
                $challenge = $event['payload'];
            }
            if (($event['payload']['event'] ?? null) === 'approval_revoked') {
                $revoked = true;
            }
        }
        if ($challenge === null || $revoked || !hash_equals((string)$challenge['challenge_sha256'], hash('sha256', $token))) {
            throw new RuntimeException('approval token is invalid or revoked');
        }
        $expiry = new DateTimeImmutable((string)$challenge['expires_at'], new DateTimeZone('UTC'));
        if ($now >= $expiry) {
            throw new RuntimeException('approval token is expired');
        }
        $record = $events['events'][0]['payload']['record'] ?? null;
        if (!is_array($record) || !hash_equals((string)($record['preflight_evidence_fingerprint'] ?? ''), DisposableReleaseTransactionService::fingerprint($currentPreflight))) {
            throw new RuntimeException('approval evidence is stale');
        }
        return $this->journal->append($transactionId, 'approved_for_disposable_rehearsal', [
            'event' => 'approval_consumed',
            'approver_id' => $approverId,
            'simulator' => true,
            'evidence_fingerprint' => $record['preflight_evidence_fingerprint'],
        ]);
    }
}

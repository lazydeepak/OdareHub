<?php
declare(strict_types=1);

namespace Platform\Health;

final class PostUpdateHealthVerificationResult
{
    /** @param array<int,array<string,mixed>> $checks */
    private function __construct(
        private readonly string $transactionId,
        private readonly string $preflightFingerprint,
        private readonly string $status,
        private readonly array $checks,
        private readonly string $evidenceDigest,
        private readonly string $bindingDigest,
        private readonly string $expectationsDigest
    ) {}

    public function transactionId(): string { return $this->transactionId; }
    public function preflightFingerprint(): string { return $this->preflightFingerprint; }
    public function status(): string { return $this->status; }
    /** @return array<int,array<string,mixed>> */
    public function checks(): array { return $this->checks; }
    public function evidenceDigest(): string { return $this->evidenceDigest; }
    public function bindingDigest(): string { return $this->bindingDigest; }
    public function expectationsDigest(): string { return $this->expectationsDigest; }
    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['transaction_id' => $this->transactionId, 'preflight_fingerprint' => $this->preflightFingerprint, 'status' => $this->status, 'checks' => $this->checks, 'evidence_digest' => $this->evidenceDigest, 'binding_digest' => $this->bindingDigest, 'expectations_digest' => $this->expectationsDigest];
    }
}

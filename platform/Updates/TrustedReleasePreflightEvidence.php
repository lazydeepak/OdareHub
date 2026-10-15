<?php
declare(strict_types=1);

namespace Platform\Updates;

use RuntimeException;

/**
 * Immutable evidence envelope for a coordinator result.
 *
 * Transaction controls accept this object rather than a caller-supplied
 * preflight array. This value object does not authenticate its caller:
 * disposable execution must invoke the authoritative coordinator again and
 * compare this fingerprint to that fresh result before mutation.
 */
final class TrustedReleasePreflightEvidence
{
    /** @param array<string,mixed> $evidence */
    private function __construct(private readonly array $evidence) {}

    /** @param array<string,mixed> $evidence */
    public static function fromCoordinatorResult(array $evidence): self
    {
        foreach ([
            'state' => TrustedReleaseCoordinatorPreflightService::PREFLIGHT_VERIFIED,
            'execution_prohibited' => true,
            'release_authority_granted' => false,
            'update_eligible' => false,
            'apply_eligible' => false,
            'execution_authorized' => false,
            'live_mutation_performed' => false,
        ] as $field => $expected) {
            if (($evidence[$field] ?? null) !== $expected) {
                throw new RuntimeException('coordinator evidence is not verified and execution-prohibited');
            }
        }
        foreach ([
            'installation_identity_fingerprint', 'candidate_build_id', 'candidate_package_sha256',
            'release_statement_fingerprint', 'trust_store_fingerprint', 'migration_plan_fingerprint',
            'source_schema_identity', 'target_schema_identity', 'recovery_point_identity',
        ] as $field) {
            if (!is_string($evidence[$field] ?? null) || trim($evidence[$field]) === '') {
                throw new RuntimeException('coordinator evidence is missing ' . $field);
            }
        }
        if (!is_array($evidence['diagnostics'] ?? null) || !is_array($evidence['evidence_provenance'] ?? null)) {
            throw new RuntimeException('coordinator evidence provenance is missing');
        }
        return new self($evidence);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return $this->evidence;
    }

    public function fingerprint(): string
    {
        return DisposableReleaseTransactionService::fingerprint($this->evidence);
    }
}

<?php
declare(strict_types=1);

namespace Apps\Platform\Services;

/**
 * Opaque, read-only organization context produced by the pure membership
 * resolver. It is evidence, not an ACL grant or a Staff identity.
 */
final class OrganizationMembershipContext
{
    public function __construct(
        private readonly int $userId,
        private readonly int $organizationId,
        private readonly string $evidenceFingerprint
    ) {
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function organizationId(): int
    {
        return $this->organizationId;
    }

    public function evidenceFingerprint(): string
    {
        return $this->evidenceFingerprint;
    }

    /**
     * @return array{user_id:int,organization_id:int,evidence_fingerprint:string}
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'organization_id' => $this->organizationId,
            'evidence_fingerprint' => $this->evidenceFingerprint,
        ];
    }
}

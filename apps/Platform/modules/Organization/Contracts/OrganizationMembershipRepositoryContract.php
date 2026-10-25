<?php
declare(strict_types=1);

namespace Plugins\Organization\Contracts;

use DateTimeInterface;

/**
 * Persistence boundary for explicit user-to-organization membership evidence.
 *
 * Implementations do not resolve authentication, grant ACL access, or infer
 * Staff identity. They return evidence for the pure Platform resolver.
 */
interface OrganizationMembershipRepositoryContract
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public function listEvidenceForUser(int $userId, DateTimeInterface $observedAt): array;

    /**
     * @return array<string,mixed>|null
     */
    public function findById(int $membershipId): ?array;

    /**
     * @param array<string,mixed> $command
     * @return array{ok:bool,state:string,record:array<string,mixed>|null}
     */
    public function create(array $command): array;

    /**
     * @return array{ok:bool,state:string,record:array<string,mixed>|null}
     */
    public function activate(int $membershipId, int $actorUserId, DateTimeInterface $observedAt): array;

    /**
     * @return array{ok:bool,state:string,record:array<string,mixed>|null}
     */
    public function revoke(int $membershipId, int $actorUserId, DateTimeInterface $observedAt, string $reason): array;

    /**
     * @param array<string,mixed> $command
     * @return array{ok:bool,state:string,record:array<string,mixed>|null}
     */
    public function replaceActive(
        int $userId,
        int $organizationId,
        array $command,
        int $actorUserId,
        DateTimeInterface $observedAt
    ): array;
}

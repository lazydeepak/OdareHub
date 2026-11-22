<?php
declare(strict_types=1);

namespace Shared\Item\Foundation\Contracts;

/**
 * Persistence boundary for canonical Shared Item identity.
 *
 * The repository contains SQL mechanics only. Domain validation and lifecycle
 * policy remain in ItemIdentityService.
 */
interface ItemRepositoryContract
{
    /** @return array<string,mixed>|null */
    public function findById(int $itemId): ?array;

    /** @return array<string,mixed>|null */
    public function findByCode(string $codeRef): ?array;

    /** @return array<int,array<string,mixed>> */
    public function list(?string $status, ?string $categoryRef, int $limit, int $offset): array;

    /** @param array<string,mixed> $fields */
    public function insert(array $fields): int;

    /** @param array<string,mixed> $fields */
    public function update(int $itemId, array $fields): void;
}

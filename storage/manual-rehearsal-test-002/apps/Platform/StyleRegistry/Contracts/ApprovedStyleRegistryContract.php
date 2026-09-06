<?php
declare(strict_types=1);

namespace Apps\Platform\StyleRegistry\Contracts;

/**
 * Contract for reading and writing approved style values in the Platform
 * StyleRegistry. The registry is the source of truth for approved active
 * style values after the Apply phase.
 *
 * Studio Apply writes to this registry. Shell consumption reads from it.
 * Neither is connected in this slice.
 *
 * @see docs/architecture/resolved-style-consumer-contract.md For the
 *      consumer-side contract that defines how Shell reads registry values.
 */
interface ApprovedStyleRegistryContract
{
    /**
     * Read the current approved value for a socket.
     * Returns null if no approved value has been written, if the socket
     * is unknown, or if the value has not been set.
     *
     * @param string $socketId e.g. 'radius.scale'
     * @return string|null The approved value, or null
     */
    public function getValue(string $socketId): ?string;

    /**
     * Write an approved value for a socket.
     *
     * Preconditions enforced server-side:
     * - socketId must be in the allowlist
     * - value must be in the allowed values for that socket
     *
     * @param string $socketId e.g. 'radius.scale'
     * @param string $value The approved value to write
     * @param array<string,mixed> $context Provenance: request_id, snapshot_id, applied_by_user_id, applied_at
     * @return array{ok:bool,error?:string}
     */
    public function setValue(string $socketId, string $value, array $context = []): array;

    /**
     * Check whether a socket ID is registered and writable.
     *
     * @param string $socketId e.g. 'radius.scale'
     * @return bool True if the socket is in the allowlist and writable
     */
    public function isWritable(string $socketId): bool;
}

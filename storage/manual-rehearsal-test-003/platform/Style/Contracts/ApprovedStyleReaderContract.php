<?php
declare(strict_types=1);

namespace Platform\Style\Contracts;

/**
 * Segregated read-only contract for resolved style consumption.
 *
 * This is the ONLY contract the ResolvedStyleConsumer may depend on.
 * Access to ApprovedStyleRegistryContract (which includes setValue,
 * isWritable, and write provenance) is FORBIDDEN in consumer code.
 *
 * The adapter implementing this contract is the sole bridge to
 * apps/Platform/StyleRegistry. See ApprovedStyleReaderAdapter.
 *
 * @see docs/architecture/registry-read-contract.md
 */
interface ApprovedStyleReaderContract
{
    /**
     * Read a single approved value from the Platform Style Registry.
     *
     * Returns null when:
     *   - the socket has no stored approved value
     *   - the socket is not in the registry allowlist
     *   - the registry storage is unreachable
     *
     * Must not throw exceptions for missing values or unreachable storage.
     * Null return is the expected contract for absent or unavailable values.
     *
     * @param string $socketKey e.g. 'radius.scale'
     * @return string|null The approved value, or null.
     */
    public function readValue(string $socketKey): ?string;

    /**
     * Quick reachability check for the registry storage.
     *
     * Returns true when the storage directory exists and is readable.
     * Returns false when the directory is missing, inaccessible, or the
     * read check fails for any reason.
     *
     * This check must not write to storage, modify state, or produce
     * side effects.
     *
     * @return bool True when the registry storage is reachable.
     */
    public function isReachable(): bool;
}

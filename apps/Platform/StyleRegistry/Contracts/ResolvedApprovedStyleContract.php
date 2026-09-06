<?php
declare(strict_types=1);

namespace Apps\Platform\StyleRegistry\Contracts;

/**
 * Value object representing a resolved approved style value ready for
 * Shell consumption. Produced by the consumption preparation layer
 * but not yet consumed by Shell runtime in this phase.
 *
 * Immutable. Read-only after construction.
 */
final class ResolvedApprovedStyleContract
{
    /** @var string The socket identifier (e.g. 'radius.scale') */
    private string $socketId;

    /** @var string|null The approved value from registry, or null if unset */
    private ?string $value;

    /** @var bool Whether the registry was reachable */
    private bool $registryReachable;

    /** @var bool Whether the socket is in the registry allowlist */
    private bool $socketKnown;

    /** @var string The default value used when registry is unreachable or unset */
    private string $fallback;

    /** @var string|null The reason if resolution failed */
    private ?string $error;

    public function __construct(
        string $socketId,
        ?string $value,
        bool $registryReachable,
        bool $socketKnown,
        string $fallback,
        ?string $error = null
    ) {
        $this->socketId = $socketId;
        $this->value = $value;
        $this->registryReachable = $registryReachable;
        $this->socketKnown = $socketKnown;
        $this->fallback = $fallback;
        $this->error = $error;
    }

    public function socketId(): string { return $this->socketId; }

    /** The approved value from registry, or null if unset/invalid. */
    public function value(): ?string { return $this->value; }

    /** Whether the value was read from the registry (not fallback). */
    public function isApproved(): bool { return $this->value !== null; }

    /** The effective value to use: approved value if set, else fallback. */
    public function effectiveValue(): string { return $this->value ?? $this->fallback; }

    /** Whether the registry was reachable (file system readable). */
    public function isRegistryReachable(): bool { return $this->registryReachable; }

    /** Whether the socket is registered in the allowlist. */
    public function isSocketKnown(): bool { return $this->socketKnown; }

    public function fallback(): string { return $this->fallback; }

    public function error(): ?string { return $this->error; }

    /** Whether the effective value comes from 'registry' or 'fallback'. */
    public function source(): string { return $this->isApproved() ? 'registry' : 'fallback'; }

    /** Diagnostic snapshot for probe/audit use. */
    public function toArray(): array
    {
        return [
            'socket_id' => $this->socketId,
            'approved_value' => $this->value,
            'effective_value' => $this->effectiveValue(),
            'registry_reachable' => $this->registryReachable,
            'socket_known' => $this->socketKnown,
            'fallback' => $this->fallback,
            'error' => $this->error,
            'source' => $this->isApproved() ? 'registry' : 'fallback',
        ];
    }
}

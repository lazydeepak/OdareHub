<?php
declare(strict_types=1);

namespace Platform\Updates;

/**
 * Structural-only result of ReleaseMigrationPlanValidator.
 *
 * AppMigrationService accepts this object rather than a caller-supplied
 * position/array. The private constructor protects the exact-step API shape,
 * but the public factory is not an authority boundary. Coordinators must
 * re-run ReleaseMigrationPlanValidator and bind the contents independently.
 */
final class ReleaseMigrationPlanEvidence
{
    /** @param array<string,mixed> $plan */
    private function __construct(private readonly array $plan)
    {
    }

    /** @param array<string,mixed> $plan */
    public static function fromValidatedPlan(array $plan): self
    {
        return new self($plan);
    }

    /** Structural-only evidence; never release authorization. */
    public function isStructuralOnly(): bool
    {
        return true;
    }

    /** @return list<array<string,mixed>> */
    public function steps(): array
    {
        return $this->plan['steps'];
    }

    public function planId(): string
    {
        return (string)$this->plan['plan_id'];
    }

    public function fingerprint(): string
    {
        return (string)$this->plan['plan_fingerprint'];
    }
}

<?php
declare(strict_types=1);

namespace Platform\Updates;

/**
 * Opaque result of ReleaseMigrationPlanValidator.
 *
 * AppMigrationService accepts this object rather than a caller-supplied
 * position/array. The private constructor keeps arbitrary arrays from being
 * presented as independently validated plan evidence.
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

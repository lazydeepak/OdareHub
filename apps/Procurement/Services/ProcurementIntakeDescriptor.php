<?php
declare(strict_types=1);

namespace Apps\Procurement\Services;

/**
 * Immutable, owner-neutral evidence for a future Procurement intake call.
 *
 * This object deliberately does not read source tables, authenticate an actor,
 * write Procurement storage, or enforce persistence-level uniqueness. Those
 * responsibilities remain with the requesting app, the Procurement authority,
 * and a separately approved schema/concurrency change.
 */
final class ProcurementIntakeDescriptor
{
    private function __construct(
        private readonly string $sourceApp,
        private readonly string $sourceEntity,
        private readonly string $sourceRecordId,
        private readonly string $sourceRevision,
        private readonly string $actor,
        private readonly string $organizationScope,
        private readonly string $recordScope,
        private readonly string $capability,
    ) {
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function fromArray(array $input): self
    {
        $values = self::normalize($input);
        $errors = self::errors($values);
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode(' ', $errors));
        }

        return new self(
            $values['source_app'],
            $values['source_entity'],
            $values['source_record_id'],
            $values['source_revision'],
            $values['actor'],
            $values['organization_scope'],
            $values['record_scope'],
            $values['capability'],
        );
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'source_app' => $this->sourceApp,
            'source_entity' => $this->sourceEntity,
            'source_record_id' => $this->sourceRecordId,
            'source_revision' => $this->sourceRevision,
            'actor' => $this->actor,
            'organization_scope' => $this->organizationScope,
            'record_scope' => $this->recordScope,
            'capability' => $this->capability,
            'idempotency_key' => $this->idempotencyKey(),
        ];
    }

    public function idempotencyKey(): string
    {
        return hash('sha256', implode("\x1f", [
            $this->sourceApp,
            $this->sourceEntity,
            $this->sourceRecordId,
            $this->sourceRevision,
        ]));
    }

    /** @return array<string,string> */
    private static function normalize(array $input): array
    {
        $keys = [
            'source_app', 'source_entity', 'source_record_id', 'source_revision',
            'actor', 'organization_scope', 'record_scope', 'capability',
        ];
        $values = [];
        foreach ($keys as $key) {
            $value = $input[$key] ?? null;
            $values[$key] = is_string($value) ? trim($value) : '';
        }
        return $values;
    }

    /** @param array<string,string> $values @return array<int,string> */
    private static function errors(array $values): array
    {
        $errors = [];
        foreach ([
            'source_app' => 80,
            'source_entity' => 80,
            'source_record_id' => 80,
            'source_revision' => 80,
            'actor' => 190,
            'organization_scope' => 190,
            'record_scope' => 190,
            'capability' => 80,
        ] as $key => $maxLength) {
            if ($values[$key] === '') {
                $errors[] = $key . ' is required.';
            } elseif (strlen($values[$key]) > $maxLength) {
                $errors[] = $key . ' exceeds its maximum length.';
            } elseif (preg_match('/[\x00-\x1F\x7F]/', $values[$key]) === 1) {
                $errors[] = $key . ' contains control characters.';
            }
        }
        if ($values['source_app'] !== '' && preg_match('/^[a-z][a-z0-9_-]{0,79}$/', $values['source_app']) !== 1) {
            $errors[] = 'source_app must be a stable lowercase app key.';
        }
        if ($values['source_entity'] !== '' && preg_match('/^[a-z][a-z0-9_.-]{0,79}$/', $values['source_entity']) !== 1) {
            $errors[] = 'source_entity must be a stable lowercase entity key.';
        }
        if ($values['capability'] !== '' && $values['capability'] !== 'procurement.request.intake') {
            $errors[] = 'capability is not an accepted Procurement intake capability.';
        }
        return $errors;
    }
}

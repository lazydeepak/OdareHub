<?php
declare(strict_types=1);

namespace Platform\Updates;

/**
 * Read-only validator for the migration evidence already represented by the
 * application migration runner and schema snapshot ledger.
 */
final class MigrationCompatibilityPreflightService
{
    /**
     * @param array<string,mixed> $compatibility
     * @param array<string,mixed> $installedState
     * @return array<string,mixed>
     */
    public function validate(array $compatibility, array $installedState): array
    {
        $errors = [];
        $eligibilityBlockers = [];
        $migrations = is_array($installedState['migrations'] ?? null) ? $installedState['migrations'] : [];
        foreach (['pending', 'failed'] as $field) {
            if (!is_array($migrations[$field] ?? null)) {
                $errors[] = 'installed migration evidence requires ' . $field . ' list.';
            }
        }
        if (($migrations['pending'] ?? []) !== []) {
            $errors[] = 'installed migration state has unresolved pending migrations.';
        }
        if (($migrations['failed'] ?? []) !== []) {
            $errors[] = 'installed migration state has failed migrations.';
        }
        if (isset($migrations['order']) && !is_array($migrations['order'])) {
            $errors[] = 'installed migration ordering evidence is malformed.';
        }

        $evidence = is_array($migrations['evidence'] ?? null) ? $migrations['evidence'] : null;
        if ($evidence === null) {
            $eligibilityBlockers[] = 'authoritative migration ordering, ownership, and checksum evidence is unavailable.';
        } else {
            foreach (['identities_verified', 'ownership_verified', 'checksums_verified', 'ordering_verified'] as $field) {
                if (($evidence[$field] ?? false) !== true) {
                    $eligibilityBlockers[] = 'migration ' . $field . ' is not verified.';
                }
            }
            foreach ((array)($evidence['errors'] ?? []) as $error) {
                $errors[] = 'migration evidence: ' . (string)$error;
            }
        }

        $schemaRequirement = is_array($compatibility['schema'] ?? null) ? $compatibility['schema'] : [];
        $target = $schemaRequirement['target'] ?? null;
        if ($target !== null && (!is_string($target) || trim($target) === '')) {
            $errors[] = 'release schema target is malformed.';
        } elseif (is_string($target) && trim($target) !== '') {
            if ($evidence === null || ($evidence['target_schema_verified'] ?? false) !== true) {
                $eligibilityBlockers[] = 'release target schema cannot be verified by the existing migration contract.';
            } elseif ((string)($evidence['target_schema'] ?? '') !== $target) {
                $errors[] = 'migration target schema evidence does not match the release target.';
            }
        } else {
            $eligibilityBlockers[] = 'release target schema is not declared by the V1 metadata contract.';
        }

        return [
            'ok' => $errors === [],
            'errors' => array_values(array_unique($errors)),
            'eligibility_blockers' => array_values(array_unique($eligibilityBlockers)),
            'evidence' => $evidence ?? ['status' => 'unavailable'],
        ];
    }
}

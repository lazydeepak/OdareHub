<?php
declare(strict_types=1);

namespace Platform\Updates;

use JsonException;

/**
 * Validates the immutable shape binding supplied to an exact-step executor.
 *
 * This is not a release coordinator and does not authorize an update. It
 * proves that a caller supplied one structurally coherent, fingerprint-bound
 * ordered plan; an independently trusted coordinator remains responsible for
 * establishing the evidence's authority.
 */
final class ReleaseMigrationPlanValidator
{
    public const PLAN_VERSION = 'susankhya.release-migrations.v1';
    public const SOURCE = 'independently-validated-release-plan';

    /** @param array<string,mixed> $plan @return array<string,mixed> */
    public static function validate(array $plan): array
    {
        $required = ['plan_version', 'plan_id', 'source', 'validation_state', 'steps', 'plan_fingerprint'];
        if (array_diff($required, array_keys($plan)) !== [] || array_diff(array_keys($plan), $required) !== []) {
            return self::failure('migration_plan_malformed', 'Migration plan evidence shape is incomplete or contains unsupported fields.');
        }
        if ($plan['plan_version'] !== self::PLAN_VERSION
            || $plan['source'] !== self::SOURCE
            || $plan['validation_state'] !== 'validated'
            || !is_string($plan['plan_id'])
            || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,127}\z/D', $plan['plan_id']) !== 1
            || !is_string($plan['plan_fingerprint'])
            || preg_match('/\A[0-9a-f]{64}\z/D', $plan['plan_fingerprint']) !== 1
            || !is_array($plan['steps'])
            || $plan['steps'] === []) {
            return self::failure('migration_plan_malformed', 'Migration plan evidence contains unsupported identity or validation state.');
        }

        $steps = [];
        $identities = [];
        foreach (array_values($plan['steps']) as $index => $step) {
            if (!is_array($step)) {
                return self::failure('migration_plan_malformed', 'Migration plan step is not an object.');
            }
            $stepRequired = ['position', 'app_key', 'version', 'migration_name', 'checksum', 'requires'];
            if (array_diff($stepRequired, array_keys($step)) !== [] || array_diff(array_keys($step), $stepRequired) !== []) {
                return self::failure('migration_plan_malformed', 'Migration plan step shape is unsupported.');
            }
            $position = $step['position'];
            $appKey = $step['app_key'];
            $migrationName = $step['migration_name'];
            $checksum = $step['checksum'];
            if (!is_int($position) || $position !== $index + 1
                || !is_string($appKey) || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,119}\z/D', $appKey) !== 1
                || !is_string($step['version']) || preg_match('/\A\d+\.\d+\.\d+\z/D', $step['version']) !== 1
                || !is_string($migrationName) || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,254}\.sql\z/D', $migrationName) !== 1
                || !is_string($checksum) || preg_match('/\A[0-9a-f]{64}\z/D', $checksum) !== 1
                || !is_array($step['requires'])) {
                return self::failure('migration_plan_malformed', 'Migration plan step contains invalid position, owner, basename, version, or checksum.');
            }
            $identity = $appKey . ':' . $migrationName;
            if (isset($identities[$identity])) {
                return self::failure('migration_plan_duplicate', 'Migration plan contains a duplicate identity.');
            }
            $identities[$identity] = $position;
            $steps[] = [
                'position' => $position,
                'app_key' => $appKey,
                'version' => $step['version'],
                'migration_name' => $migrationName,
                'checksum' => $checksum,
                'requires' => array_values($step['requires']),
            ];
        }

        foreach ($steps as $step) {
            foreach ($step['requires'] as $requirement) {
                if (!is_string($requirement) || !isset($identities[$requirement]) || $identities[$requirement] >= $step['position']) {
                    return self::failure('migration_plan_order_contradiction', 'Migration plan dependency is missing or ordered after its consumer.');
                }
            }
        }

        $fingerprint = self::fingerprint((string)$plan['plan_version'], (string)$plan['plan_id'], $steps);
        if (!hash_equals((string)$plan['plan_fingerprint'], $fingerprint)) {
            return self::failure('migration_plan_fingerprint_mismatch', 'Migration plan fingerprint does not match its immutable ordered steps.');
        }

        return [
            'ok' => true,
            'state' => 'migration_plan_validated',
            'plan_id' => $plan['plan_id'],
            'plan_fingerprint' => $fingerprint,
            'steps' => $steps,
            'authority' => 'structural-evidence-only',
            'evidence' => ReleaseMigrationPlanEvidence::fromValidatedPlan([
                'plan_id' => $plan['plan_id'],
                'plan_fingerprint' => $fingerprint,
                'steps' => $steps,
            ]),
        ];
    }

    /** @param list<array<string,mixed>> $steps */
    public static function fingerprint(string $planVersion, string $planId, array $steps): string
    {
        try {
            return hash('sha256', json_encode([
                'plan_version' => $planVersion,
                'plan_id' => $planId,
                'steps' => $steps,
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            return str_repeat('0', 64);
        }
    }

    /** @return array<string,mixed> */
    private static function failure(string $state, string $message): array
    {
        return ['ok' => false, 'state' => $state, 'message' => $message, 'authority' => 'none'];
    }
}

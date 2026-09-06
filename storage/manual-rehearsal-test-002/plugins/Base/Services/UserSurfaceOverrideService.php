<?php
declare(strict_types=1);

namespace Plugins\Base\Services;

use App\Core\DB;

/**
 * Phase 6 canonical store for per-user surface overrides.
 *
 * The transitional CSV columns on `user_dashboard_assignments`
 * (`display_surfaces`, `operator_views`, `me_dashboard_blocks`,
 * `me_plugin_cards`) are being migrated into the `user_surface_overrides`
 * table so that the override layer has a dedicated artifact independent of
 * the assignment row. During the migration window writes are dual-tracked
 * and reads prefer the artifact when present, falling back to the inline
 * CSV column.
 */
final class UserSurfaceOverrideService
{
    public const FIELDS = [
        'display_surfaces',
        'operator_views',
        'me_dashboard_blocks',
        'me_plugin_cards',
    ];

    private static ?bool $tableAvailable = null;

    private static function tableAvailable(): bool
    {
        if (self::$tableAvailable !== null) {
            return self::$tableAvailable;
        }
        try {
            DB::fetchOne('SELECT 1 FROM user_surface_overrides LIMIT 1');
            return self::$tableAvailable = true;
        } catch (\Throwable $e) {
            return self::$tableAvailable = false;
        }
    }

    /**
     * Persist a single override field for a user. Pass `null` or `''` to clear.
     */
    public static function saveField(int $userId, string $field, ?string $rawValue): void
    {
        if ($userId <= 0 || !in_array($field, self::FIELDS, true)) {
            return;
        }
        if (!self::tableAvailable()) {
            return;
        }
        $normalized = $rawValue === null ? null : trim($rawValue);
        if ($normalized === '') {
            $normalized = null;
        }
        try {
            DB::query(
                'INSERT INTO user_surface_overrides (user_id, override_field, raw_value)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE raw_value = VALUES(raw_value)',
                [$userId, $field, $normalized]
            );
        } catch (\Throwable $e) {
            error_log('[user_surface_overrides.write] ' . $e->getMessage());
        }
    }

    /**
     * Load all override fields for a user, keyed by field name. Missing fields
     * are omitted (callers should fall back to the inline assignment row).
     *
     * @return array<string,string>
     */
    public static function loadForUser(int $userId): array
    {
        if ($userId <= 0 || !self::tableAvailable()) {
            return [];
        }
        try {
            $rows = DB::fetchAll(
                'SELECT override_field, raw_value FROM user_surface_overrides WHERE user_id = ?',
                [$userId]
            );
        } catch (\Throwable $e) {
            error_log('[user_surface_overrides.read] ' . $e->getMessage());
            return [];
        }
        $out = [];
        foreach ((array)$rows as $row) {
            $field = (string)($row['override_field'] ?? '');
            if (!in_array($field, self::FIELDS, true)) {
                continue;
            }
            $value = $row['raw_value'] ?? null;
            if ($value === null) {
                continue;
            }
            $out[$field] = (string)$value;
        }
        return $out;
    }

    /**
     * Merge the artifact onto an assignment row in-place. Returns the row with
     * artifact values overriding inline CSV columns where present.
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public static function applyArtifactToRow(array $row): array
    {
        $userId = (int)($row['user_id'] ?? $row['id'] ?? 0);
        if ($userId <= 0) {
            return $row;
        }
        $artifact = self::loadForUser($userId);
        foreach ($artifact as $field => $value) {
            $row[$field] = $value;
        }
        return $row;
    }

    /**
     * Copy inline assignment CSV values into the artifact table for any user
     * that does not already have an artifact row for the field. Returns the
     * number of rows written. Idempotent.
     */
    public static function backfillFromAssignments(): int
    {
        if (!self::tableAvailable()) {
            return 0;
        }
        $written = 0;
        try {
            $rows = DB::fetchAll(
                'SELECT user_id, display_surfaces, operator_views, me_dashboard_blocks, me_plugin_cards FROM user_dashboard_assignments WHERE user_id IS NOT NULL'
            );
        } catch (\Throwable $e) {
            error_log('[user_surface_overrides.backfill] ' . $e->getMessage());
            return 0;
        }
        foreach ((array)$rows as $row) {
            $userId = (int)($row['user_id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }
            $existing = self::loadForUser($userId);
            foreach (self::FIELDS as $field) {
                if (array_key_exists($field, $existing)) {
                    continue;
                }
                $value = $row[$field] ?? null;
                if ($value === null || trim((string)$value) === '') {
                    continue;
                }
                self::saveField($userId, $field, (string)$value);
                $written++;
            }
        }
        return $written;
    }

    /**
     * Compute artifact coverage by field. Returns counts of users with an
     * artifact row vs users with a non-empty inline CSV but no artifact row,
     * for each transitional field. Used to decide when the transitional CSV
     * write paths can safely be retired.
     *
     * @return array<string,array{artifact_rows:int,inline_only:int,inline_total:int}>
     */
    public static function coverageSummary(): array
    {
        $summary = [];
        foreach (self::FIELDS as $field) {
            $summary[$field] = ['artifact_rows' => 0, 'inline_only' => 0, 'inline_total' => 0];
        }
        if (!self::tableAvailable()) {
            return $summary;
        }
        try {
            foreach (self::FIELDS as $field) {
                $artifactCount = DB::fetchOne(
                    'SELECT COUNT(*) AS c FROM user_surface_overrides WHERE override_field = ? AND raw_value IS NOT NULL',
                    [$field]
                );
                $summary[$field]['artifact_rows'] = (int)($artifactCount['c'] ?? 0);

                $inlineTotal = DB::fetchOne(
                    "SELECT COUNT(*) AS c FROM user_dashboard_assignments WHERE {$field} IS NOT NULL AND TRIM({$field}) <> ''"
                );
                $summary[$field]['inline_total'] = (int)($inlineTotal['c'] ?? 0);

                $inlineOnly = DB::fetchOne(
                    "SELECT COUNT(*) AS c
                     FROM user_dashboard_assignments uda
                     LEFT JOIN user_surface_overrides uso
                       ON uso.user_id = uda.user_id AND uso.override_field = ?
                     WHERE uda.{$field} IS NOT NULL AND TRIM(uda.{$field}) <> ''
                       AND (uso.raw_value IS NULL OR uso.raw_value = '')",
                    [$field]
                );
                $summary[$field]['inline_only'] = (int)($inlineOnly['c'] ?? 0);
            }
        } catch (\Throwable $e) {
            error_log('[user_surface_overrides.coverage] ' . $e->getMessage());
        }
        return $summary;
    }

    /**
     * Read-only retirement diagnostic for the transitional inline CSV write
     * paths. All fields are safe only when there are no inline-only values.
     *
     * @param array<string,array{artifact_rows:int,inline_only:int,inline_total:int}>|null $coverage
     * @return array{version:string,can_retire_all:bool,fields:array<string,array{can_retire:bool,reason:string,artifact_rows:int,inline_only:int,inline_total:int}>}
     */
    public static function retirementReadiness(?array $coverage = null): array
    {
        $coverage = $coverage ?? self::coverageSummary();
        $fields = [];
        $canRetireAll = true;

        foreach (self::FIELDS as $field) {
            $row = is_array($coverage[$field] ?? null) ? (array)$coverage[$field] : [];
            $inlineOnly = (int)($row['inline_only'] ?? 0);
            $inlineTotal = (int)($row['inline_total'] ?? 0);
            $artifactRows = (int)($row['artifact_rows'] ?? 0);
            $canRetire = $inlineOnly === 0;
            if (!$canRetire) {
                $canRetireAll = false;
            }

            $fields[$field] = [
                'can_retire' => $canRetire,
                'reason' => $canRetire ? 'artifact_coverage_complete' : 'inline_only_values_remain',
                'artifact_rows' => $artifactRows,
                'inline_only' => $inlineOnly,
                'inline_total' => $inlineTotal,
            ];
        }

        return [
            'version' => 'user_surface_overrides.retirement_readiness.v1',
            'can_retire_all' => $canRetireAll,
            'fields' => $fields,
        ];
    }

    /**
     * Decide whether transitional inline CSV writes should remain enabled per
     * field. A field is writable inline only while retirement readiness is not
     * complete for that specific field.
     *
     * @param array{version?:string,can_retire_all?:bool,fields?:array<string,array{can_retire?:bool}>}|null $readiness
     * @return array<string,bool>
     */
    public static function inlineWritePolicy(?array $readiness = null): array
    {
        $policy = [];
        foreach (self::FIELDS as $field) {
            $policy[$field] = true;
        }

        if ($readiness === null && !self::tableAvailable()) {
            return $policy;
        }

        $readiness = $readiness ?? self::retirementReadiness();
        $fields = is_array($readiness['fields'] ?? null) ? (array)$readiness['fields'] : [];

        foreach (self::FIELDS as $field) {
            $row = is_array($fields[$field] ?? null) ? (array)$fields[$field] : [];
            $canRetire = (bool)($row['can_retire'] ?? false);
            $policy[$field] = !$canRetire;
        }

        return $policy;
    }
}

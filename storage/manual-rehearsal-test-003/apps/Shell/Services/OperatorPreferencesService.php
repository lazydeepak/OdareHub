<?php
declare(strict_types=1);

namespace Apps\Shell\Services;

use App\Core\DB;
use Apps\Shell\Services\ThemePreferenceService;

/**
 * OperatorPreferencesService
 *
 * Stores per-user key-value preferences for the operator workspace.
 * Values are always strings; callers cast to the desired type.
 *
 * Supported keys and their allowed values:
 *   date_range         — "today" | "7d" | "30d"               (default: "today")
 *   refresh_rate       — "0" | "60" | "300" | "600"           (default: "0")
 *   notification_level — "all" | "critical" | "none"          (default: "all")
 *   default_view       — any known operator view slug          (default: "dashboard")
 *   realtime_enabled   — "0" | "1"                            (default: "1")
 */
final class OperatorPreferencesService
{
    // -------------------------------------------------------------------------
    // Allowed values — enforce at save time
    // -------------------------------------------------------------------------

    public const ALLOWED = [
        'date_range'         => ['today', '7d', '30d'],
        'refresh_rate'       => ['0', '60', '300', '600'],
        'notification_level' => ['all', 'critical', 'none'],
        'realtime_enabled'   => ['0', '1'],
        'default_view'       => [
            'dashboard', 'production', 'processing', 'preparation',
            'dispatch', 'coverage', 'qc', 'machines', 'assembly',
            'materials', 'handoff', 'notifications',
        ],
        'theme'              => [], // populated at runtime via ThemePreferenceService
    ];

    public const DEFAULTS = [
        'date_range'         => 'today',
        'refresh_rate'       => '0',
        'notification_level' => 'all',
        'realtime_enabled'   => '1',
        'default_view'       => 'dashboard',
        'theme'              => 'system-liquid-glass',
    ];

    // -------------------------------------------------------------------------
    // Read
    // -------------------------------------------------------------------------

    /**
     * Return all preferences for a user as a flat string map.
     * Missing keys are filled with defaults.
     *
     * @return array<string,string>
     */
    public static function getAll(int $userId): array
    {
        $prefs = self::DEFAULTS;

        if ($userId <= 0) {
            return $prefs;
        }

        try {
            $rows = DB::fetchAll(
                "SELECT pref_key, pref_value FROM operator_preferences WHERE user_id = ?",
                [$userId]
            );
            foreach ($rows as $row) {
                $key = (string)($row['pref_key'] ?? '');
                if (isset(self::DEFAULTS[$key])) {
                    $prefs[$key] = (string)($row['pref_value'] ?? self::DEFAULTS[$key]);
                }
            }
        } catch (\Throwable $e) {
            // Non-fatal: return defaults
        }

        return $prefs;
    }

    /**
     * Get a single preference value, returning the default if not set.
     */
    public static function get(int $userId, string $key): string
    {
        if ($userId <= 0 || !isset(self::DEFAULTS[$key])) {
            return self::DEFAULTS[$key] ?? '';
        }

        try {
            $row = DB::fetchOne(
                "SELECT pref_value FROM operator_preferences WHERE user_id = ? AND pref_key = ?",
                [$userId, $key]
            );
            return $row !== null && $row !== false
                ? (string)($row['pref_value'] ?? self::DEFAULTS[$key])
                : self::DEFAULTS[$key];
        } catch (\Throwable $e) {
            return self::DEFAULTS[$key];
        }
    }

    // -------------------------------------------------------------------------
    // Write
    // -------------------------------------------------------------------------

    /**
     * Save a batch of preferences. Unknown or invalid values are silently skipped.
     *
     * @param array<string,string> $values
     * @return array{ok: bool, error: string}
     */
    public static function saveAll(int $userId, array $values): array
    {
        if ($userId <= 0) {
            return ['ok' => false, 'error' => 'Invalid user.'];
        }

        try {
            foreach ($values as $key => $value) {
                if (!isset(self::ALLOWED[$key])) {
                    continue; // unknown key — skip
                }
                // 'theme' uses ThemePreferenceService for its allowed list
                $allowed = $key === 'theme'
                    ? ThemePreferenceService::allowedPreferences()
                    : self::ALLOWED[$key];
                if (!in_array($value, $allowed, true)) {
                    continue; // invalid value — skip
                }

                DB::query(
                    "INSERT INTO operator_preferences (user_id, pref_key, pref_value, updated_at)
                     VALUES (?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE pref_value = VALUES(pref_value), updated_at = NOW()",
                    [$userId, $key, $value]
                );
            }
            return ['ok' => true, 'error' => ''];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Failed to save preferences.'];
        }
    }

    // -------------------------------------------------------------------------
    // Helpers for views
    // -------------------------------------------------------------------------

    /**
     * Return date_range as an integer number of days for SQL INTERVAL queries.
     * today → 0, 7d → 7, 30d → 30
     */
    public static function dateRangeDays(int $userId): int
    {
        $val = self::get($userId, 'date_range');
        return match($val) {
            '7d'    => 7,
            '30d'   => 30,
            default => 0,
        };
    }

    /**
     * Return refresh_rate as an integer seconds. 0 = disabled.
     */
    public static function refreshRateSeconds(int $userId): int
    {
        return (int)self::get($userId, 'refresh_rate');
    }

    // -------------------------------------------------------------------------
    // Per-view filter persistence
    // -------------------------------------------------------------------------

    /**
     * Return saved filter values for a specific view slug (e.g. 'orders').
     * Returns an empty array when nothing has been saved yet.
     *
     * @return array<string,string>
     */
    public static function getFilters(int $userId, string $view): array
    {
        if ($userId <= 0) {
            return [];
        }

        try {
            $row = DB::fetchOne(
                "SELECT pref_value FROM operator_preferences WHERE user_id = ? AND pref_key = 'view_filters' LIMIT 1",
                [$userId]
            );
            if (!$row || empty($row['pref_value'])) {
                return [];
            }
            $all = json_decode((string)$row['pref_value'], true);
            if (!is_array($all)) {
                return [];
            }
            $viewData = $all[$view] ?? [];
            return is_array($viewData) ? $viewData : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Persist filter values for a specific view slug. Merges into the shared
     * 'view_filters' JSON blob so other views are unaffected.
     *
     * Keys and values are sanitised (alphanumeric keys, max 64 char values).
     *
     * @param array<string,string> $filters
     */
    public static function saveFilters(int $userId, string $view, array $filters): void
    {
        if ($userId <= 0) {
            return;
        }

        try {
            $existing = [];
            $row = DB::fetchOne(
                "SELECT pref_value FROM operator_preferences WHERE user_id = ? AND pref_key = 'view_filters' LIMIT 1",
                [$userId]
            );
            if ($row && !empty($row['pref_value'])) {
                $decoded = json_decode((string)$row['pref_value'], true);
                if (is_array($decoded)) {
                    $existing = $decoded;
                }
            }

            $clean = [];
            foreach ($filters as $k => $v) {
                $k = substr((string)preg_replace('/[^a-z_]/', '', strtolower((string)$k)), 0, 32);
                $v = substr(trim((string)$v), 0, 64);
                if ($k !== '') {
                    $clean[$k] = $v;
                }
            }

            $view = substr((string)preg_replace('/[^a-z_\/]/', '', strtolower($view)), 0, 32);
            $existing[$view] = $clean;

            $json = (string)json_encode($existing, JSON_UNESCAPED_UNICODE);
            DB::query(
                "INSERT INTO operator_preferences (user_id, pref_key, pref_value, updated_at)
                 VALUES (?, 'view_filters', ?, NOW())
                 ON DUPLICATE KEY UPDATE pref_value = VALUES(pref_value), updated_at = NOW()",
                [$userId, $json]
            );
        } catch (\Throwable $e) {
            // Non-fatal — silently ignore
        }
    }
}

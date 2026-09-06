<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;

/**
 * PlatformModeService
 *
 * SINGLE AUTHORITATIVE SOURCE for platform operational mode.
 *
 * Manages three explicit platform behaviors:
 * - production: Live operation. Locked-down. Dev tools hidden. Default and safest.
 * - development: Active development. Admin utilities + diagnostics visible. Full transparency.
 * - demo: Presentation mode. Polished UI. Technical noise suppressed. Safe demo features.
 *
 * Architecture:
 * - Single source of truth: core_settings table (platform.mode key)
 * - Per-request static caching (avoids repeated DB hits)
 * - Strict validation (only 3 allowed modes)
 * - Optional config lock (PLATFORM_MODE_LOCK_ENABLED)
 * - Audit logging on changes
 * - All external access via static methods or helpers
 *
 * Usage:
 *   $mode = \App\Services\PlatformModeService::currentMode();
 *   if (\App\Services\PlatformModeService::isDevelopmentMode()) { ... }
 *
 * Or via helpers (simpler):
 *   if (is_development_mode()) { ... }
 *
 * Distinction:
 * - Environment = infrastructure layer (PHP version, hosting, env vars)
 * - Platform Mode = behavior layer (feature gating, UI simplification, safety)
 */
final class PlatformModeService
{
    // ========== Mode Constants ==========

    public const MODE_PRODUCTION = 'production';
    public const MODE_DEVELOPMENT = 'development';
    public const MODE_DEMO = 'demo';

    public const VALID_MODES = [
        self::MODE_PRODUCTION,
        self::MODE_DEVELOPMENT,
        self::MODE_DEMO,
    ];

    // ========== Storage & Caching ==========

    private const SETTING_KEY = 'platform.mode';

    /** Per-request cache. Avoids repeated DB queries. */
    private static ?string $requestCache = null;
    
    /** Cache validity flag. */
    private static bool $cacheLoaded = false;

    /**
     * Get the current platform mode.
     *
     * Returns one of: production, development, demo
     * Default (safe fallback): production
     *
     * Uses per-request static cache to avoid repeated DB queries.
     *
     * @return string One of MODE_PRODUCTION, MODE_DEVELOPMENT, MODE_DEMO
     */
    public static function currentMode(): string
    {
        // Use per-request cache
        if (self::$cacheLoaded) {
            return self::$requestCache ?? self::MODE_PRODUCTION;
        }

        self::$cacheLoaded = true;
        $mode = self::rawMode();

        // Strict validation: only 3 allowed modes
        if (!in_array($mode, self::VALID_MODES, true)) {
            self::$requestCache = self::MODE_PRODUCTION;
            return self::MODE_PRODUCTION;
        }

        self::$requestCache = $mode;
        return $mode;
    }

    /**
     * Check if current mode is production.
     */
    public static function isProductionMode(): bool
    {
        return self::currentMode() === self::MODE_PRODUCTION;
    }

    /**
     * Check if current mode is development.
     */
    public static function isDevelopmentMode(): bool
    {
        return self::currentMode() === self::MODE_DEVELOPMENT;
    }

    /**
     * Check if current mode is demo.
     */
    public static function isDemoMode(): bool
    {
        return self::currentMode() === self::MODE_DEMO;
    }

    /**
     * Set the platform mode.
     *
     * IMPORTANT: Respect optional PLATFORM_MODE_LOCK config.
     * If locked, only allow changes via direct config (not via this method).
     *
     * @param string $mode One of MODE_PRODUCTION, MODE_DEVELOPMENT, MODE_DEMO
     * @throws \RuntimeException If mode is invalid or if mode switching is locked
     */
    public static function setMode(string $mode): void
    {
        // Check if mode switching is locked (production safety)
        if (self::isModeLockedViaConfig()) {
            throw new \RuntimeException(
                'Platform mode switching is locked via configuration. Contact system administrator.'
            );
        }

        $mode = strtolower(trim($mode));

        // Strict validation: reject anything not in VALID_MODES
        if (!in_array($mode, self::VALID_MODES, true)) {
            throw new \RuntimeException(
                'Invalid platform mode. Must be one of: ' . implode(', ', self::VALID_MODES)
            );
        }

        $userLabel = (string)(Auth::user()['email'] ?? 'system');

        // Persist to core_settings (single source of truth)
        DB::query(
            'INSERT INTO core_settings (setting_key, setting_value, updated_at) VALUES (?,?,NOW())
             ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_at=NOW()',
            [self::SETTING_KEY, $mode]
        );

        // Add audit log entry
        try {
            if (class_exists('\App\Core\AuditLogService')) {
                \App\Core\AuditLogService::log(
                    'platform_mode_change',
                    'Platform mode changed to: ' . $mode,
                    ['new_mode' => $mode, 'previous_mode' => self::currentMode()],
                    $userLabel
                );
            }
        } catch (\Throwable) {
            // Silently ignore audit failures - don't block mode change
        }

        // Clear cache so next read gets fresh value
        self::clearCache();
    }

    /**
     * Get a descriptive label for a mode.
     */
    public static function modeLabel(string $mode): string
    {
        return match (strtolower(trim($mode))) {
            self::MODE_PRODUCTION => 'Production',
            self::MODE_DEVELOPMENT => 'Development',
            self::MODE_DEMO => 'Demo',
            default => 'Unknown',
        };
    }

    /**
     * Get a description for a mode.
     */
    public static function modeDescription(string $mode): string
    {
        return match (strtolower(trim($mode))) {
            self::MODE_PRODUCTION => 'Live operation. Safe and locked-down. Development tools hidden.',
            self::MODE_DEVELOPMENT => 'Active building and debugging. Admin utilities and diagnostic tools visible.',
            self::MODE_DEMO => 'Presentation-friendly mode. Emphasizes polished UI and safe demo features.',
            default => '',
        };
    }

    /**
     * Get available modes with labels and descriptions.
     *
     * @return array<string,array<string,string>>
     */
    public static function availableModes(): array
    {
        return [
            self::MODE_PRODUCTION => [
                'label' => self::modeLabel(self::MODE_PRODUCTION),
                'description' => self::modeDescription(self::MODE_PRODUCTION),
            ],
            self::MODE_DEVELOPMENT => [
                'label' => self::modeLabel(self::MODE_DEVELOPMENT),
                'description' => self::modeDescription(self::MODE_DEVELOPMENT),
            ],
            self::MODE_DEMO => [
                'label' => self::modeLabel(self::MODE_DEMO),
                'description' => self::modeDescription(self::MODE_DEMO),
            ],
        ];
    }

    /** Read-only lock state for governed admin presentation. */
    public static function isModeSwitchLocked(): bool
    {
        return self::isModeLockedViaConfig();
    }

    // ========== Private Helpers ==========

    /**
     * Check if mode switching is locked via configuration.
     *
     * If PLATFORM_MODE_LOCK_ENABLED env var or constant is set to true,
     * mode switching is disabled via this service (can only be changed via config file).
     *
     * @return bool True if mode switching is locked
     */
    private static function isModeLockedViaConfig(): bool
    {
        // Check environment variable
        if (getenv('PLATFORM_MODE_LOCK_ENABLED') === 'true') {
            return true;
        }

        // Check constant (if defined)
        if (defined('PLATFORM_MODE_LOCK_ENABLED') && PLATFORM_MODE_LOCK_ENABLED === true) {
            return true;
        }

        return false;
    }

    /**
     * Fetch raw mode value from database.
     *
     * Does NOT use cache. Used internally to load fresh value from DB.
     *
     * @return string Raw mode value or empty string if not set
     */
    private static function rawMode(): string
    {
        try {
            if (!class_exists('\App\Core\DB')) {
                return '';
            }

            $row = DB::fetchOne(
                'SELECT setting_value FROM core_settings WHERE setting_key = ? LIMIT 1',
                [self::SETTING_KEY]
            );

            if (is_array($row)) {
                $value = trim((string)($row['setting_value'] ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }
        } catch (\Throwable) {
            // Database error - return empty string (will use default)
        }

        return '';
    }

    /**
     * Clear the per-request cache.
     *
     * Called after mode is changed to ensure next read gets fresh value.
     */
    private static function clearCache(): void
    {
        self::$requestCache = null;
        self::$cacheLoaded = false;
    }
}

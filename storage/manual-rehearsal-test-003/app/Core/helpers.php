<?php
declare(strict_types=1);

if (!defined('APP_NAME')) {
    define('APP_NAME', 'Susankhya OS');
}

if (!defined('APP_VERSION')) {
    define('APP_VERSION', '0.5.0');
}

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function dd($v): void {
    echo "<pre>";
    var_dump($v);
    echo "</pre>";
    exit;
}

function supported_language_metadata(): array {
    return [
        'en' => [
            'code' => 'en',
            'flag' => '🇬🇧',
            'autonym' => 'English',
            'label' => '🇬🇧 English',
        ],
        'ja' => [
            'code' => 'ja',
            'flag' => '🇯🇵',
            'autonym' => '日本語',
            'label' => '🇯🇵 日本語',
        ],
        'ne' => [
            'code' => 'ne',
            'flag' => '🇳🇵',
            'autonym' => 'नेपाली',
            'label' => '🇳🇵 नेपाली',
        ],
    ];
}

function supported_language_labels(): array {
    $labels = [];
    foreach (supported_language_metadata() as $code => $meta) {
        $labels[(string)$code] = (string)($meta['label'] ?? $code);
    }
    return $labels;
}

function supported_langs(): array {
    return array_keys(supported_language_metadata());
}

function supported_currencies(): array {
    return ['usd', 'jpy', 'npr'];
}

function supported_theme_preferences(): array {
    $serviceClass = '\\Apps\\Shell\\Services\\ThemePreferenceService';
    if (!class_exists($serviceClass) && defined('APP_ROOT')) {
        $servicePath = rtrim((string)APP_ROOT, '/') . '/apps/Shell/Services/ThemePreferenceService.php';
        if (is_file($servicePath)) {
            require_once $servicePath;
        }
    }

    if (class_exists($serviceClass)) {
        return $serviceClass::allowedPreferences();
    }

    // Minimal safe fallback when ThemePreferenceService is unavailable.
    return ['system-obsidian'];
}

function core_settings_map(): array {
    static $cache = null;

    if (is_array($cache)) {
        return $cache;
    }

    $cache = [];
    if (!class_exists('\App\Core\DB')) {
        return $cache;
    }

    try {
        $rows = \App\Core\DB::fetchAll('SELECT setting_key, setting_value FROM core_settings ORDER BY setting_key ASC');
        foreach ($rows as $row) {
            $key = trim((string)($row['setting_key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $cache[$key] = (string)($row['setting_value'] ?? '');
        }
    } catch (\Throwable) {
        $cache = [];
    }

    return $cache;
}

function core_setting(string $key, ?string $default = ''): string {
    $value = trim((string)(core_settings_map()[$key] ?? ''));
    if ($value !== '') {
        return $value;
    }

    return (string)$default;
}

function app_display_name(): string {
    $name = core_setting('company.name', core_setting('system.name', APP_NAME));
    if (strcasecmp(trim($name), 'ERP Engine') === 0) {
        return APP_NAME;
    }
    return $name !== '' ? $name : APP_NAME;
}

function default_home_route(): string {
    $home = trim(core_setting('ui.default_home', core_setting('system.default_dashboard', '/')));
    if ($home === '' || $home[0] !== '/') {
        return '/';
    }

    return $home;
}

function default_theme_preference(): string {
    $theme = strtolower(trim(core_setting('ui.theme', core_setting('system.theme', 'system-obsidian'))));
    if ($theme === 'system') {
        $theme = 'system-obsidian';
    }
    if ($theme === 'light') {
        $theme = 'light-paper';
    }
    if ($theme === 'dark') {
        $theme = 'dark-obsidian';
    }

    return in_array($theme, supported_theme_preferences(), true) ? $theme : 'system-obsidian';
}

function set_lang(?string $lang): void {
    $next = strtolower(trim((string)$lang));
    if (in_array($next, supported_langs(), true)) {
        $_SESSION['lang'] = $next;
    }
}

function set_currency(?string $currency): void {
    $next = strtolower(trim((string)$currency));
    if (in_array($next, supported_currencies(), true)) {
        $_SESSION['currency'] = $next;
    }
}

function apply_global_preferences_from_request(): void {
    if (!empty($_GET['lang'])) {
        set_lang((string)$_GET['lang']);
    }
    if (!empty($_GET['currency'])) {
        set_currency((string)$_GET['currency']);
    }
}

function current_lang(): string {
    $lang = strtolower((string)($_SESSION['lang'] ?? ''));
    if ($lang === '') {
        $lang = strtolower(core_setting('system.default_language', core_setting('system.locale', 'en')));
    }
    return in_array($lang, supported_langs(), true) ? $lang : 'en';
}

function current_currency(): string {
    $currency = strtolower((string)($_SESSION['currency'] ?? ''));
    if ($currency === '') {
        $currency = strtolower(core_setting('system.default_currency', core_setting('system.currency', 'jpy')));
    }
    return in_array($currency, supported_currencies(), true) ? $currency : 'jpy';
}

function locale_path(string $lang): string {
    return APP_ROOT . '/app/Locale/' . $lang . '.php';
}

/**
 * Discover all per-owner locale files for a given language.
 * Checks canonical Resources/lang/ paths first, then legacy lang/ paths.
 * Results are cached in a static variable (once per request per language).
 */
function discover_owner_locale_files(string $lang): array {
    static $cache = [];

    if (isset($cache[$lang])) {
        return $cache[$lang];
    }

    $root = APP_ROOT;
    $found = [];

    // Canonical Resources/lang/ paths
    $patterns = [
        "$root/apps/*/Resources/lang/$lang.php",
        "$root/apps/*/modules/*/Resources/lang/$lang.php",
        "$root/plugins/*/Resources/lang/$lang.php",
        "$root/apps/Studio/Tools/*/Resources/lang/$lang.php",
    ];
    foreach ($patterns as $pattern) {
        $matches = glob($pattern);
        if (is_array($matches)) {
            foreach ($matches as $m) {
                $found[$m] = true;
            }
        }
    }

    $cache[$lang] = array_keys($found);
    return $cache[$lang];
}

function load_locale(string $lang): array {
    static $cache = [];

    if (isset($cache[$lang])) {
        return $cache[$lang];
    }

    // 1. Load Core base locale file
    $file = locale_path($lang);
    $result = [];
    if (is_file($file)) {
        $loaded = require $file;
        $result = is_array($loaded) ? $loaded : [];
    }

    // 2. Overlay per-owner locale files
    // Owner files supplement/override Core keys.
    // For en, always merge (owner en files are source-of-truth from code extraction).
    // For ja/ne, only merge if the file contains actual translated content
    // (not empty scaffolding keys that would blank out Core translations).
    $ownerFiles = discover_owner_locale_files($lang);
    foreach ($ownerFiles as $f) {
        if (!is_file($f)) {
            continue;
        }
        $data = require $f;
        if (!is_array($data)) {
            continue;
        }
        // Skip ja/ne files that only contain empty values (scaffolding-only)
        if ($lang !== 'en') {
            $hasContent = false;
            foreach ($data as $v) {
                if ((string)$v !== '') {
                    $hasContent = true;
                    break;
                }
            }
            if (!$hasContent) {
                continue;
            }
        }
        $result = array_merge($result, $data);
    }

    return $cache[$lang] = $result;
}

function interpolate_locale_string(string $text, array $params = []): string {
    if ($params === []) {
        return $text;
    }

    $replace = [];
    foreach ($params as $key => $value) {
        $replace['{' . $key . '}'] = (string)$value;
    }

    return strtr($text, $replace);
}

function __(string $key, array $params = []): string {
    if ($key === 'app.name') {
        return interpolate_locale_string(app_display_name(), $params);
    }

    $lang = current_lang();
    $current = load_locale($lang);
    $english = $lang === 'en' ? $current : load_locale('en');
    $text = $current[$key] ?? $english[$key] ?? $key;
    return interpolate_locale_string((string)$text, $params);
}

function t(string $key, array $params = []): string {
    return __($key, $params);
}

function localized_record_label(int $count): string {
    return $count === 1 ? t('common.record_one') : t('common.record_other');
}

function localized_records_summary(int $count): string {
    return t('common.showing_records', [
        'count' => $count,
        'records_label' => localized_record_label($count),
    ]);
}

function localized_count_phrase(string $labelKey, int|float $count): string {
    return t('common.count_suffix', [
        'label' => t($labelKey),
        'count' => is_float($count) ? rtrim(rtrim(number_format($count, 2, '.', ''), '0'), '.') : $count,
    ]);
}

function localized_total_phrase(int|float $count): string {
    return t('common.count_total', [
        'count' => is_float($count) ? rtrim(rtrim(number_format($count, 2, '.', ''), '0'), '.') : $count,
    ]);
}

function currency_symbol(?string $currency = null): string {
    $currency = strtoupper($currency ?? current_currency());
    return match ($currency) {
        'USD' => '$',
        'JPY' => '¥',
        'NPR' => 'Rs',
        default => '$',
    };
}

function convert_currency(float $amount, ?string $fromCurrency = null, ?string $toCurrency = null): float {
    $from = strtoupper($fromCurrency ?? 'USD');
    $to = strtoupper($toCurrency ?? current_currency());
    if ($from === $to) {
        return $amount;
    }

    $rates = [
        'USD' => ['JPY' => 135.0, 'NPR' => 129.0],
        'JPY' => ['USD' => 1 / 135.0, 'NPR' => 129.0 / 135.0],
        'NPR' => ['USD' => 1 / 129.0, 'JPY' => 135.0 / 129.0],
    ];

    if (isset($rates[$from][$to])) {
        return $amount * $rates[$from][$to];
    }

    if ($from !== 'USD' && $to !== 'USD' && isset($rates[$from]['USD']) && isset($rates['USD'][$to])) {
        return $amount * $rates[$from]['USD'] * $rates['USD'][$to];
    }

    return $amount;
}

function format_money(float $amount, ?string $fromCurrency = null, ?string $toCurrency = null, int $decimals = 2): string {
    $to = strtoupper($toCurrency ?? current_currency());
    $converted = convert_currency($amount, $fromCurrency, $to);
    $symbol = currency_symbol($to);
    $formatted = number_format($converted, $decimals, '.', ',');
    return $symbol . $formatted;
}

function app_env(): string {
    $env = strtolower(trim((string)getenv('ERP_APP_ENV')));
    if ($env === '') {
        $env = strtolower(trim((string)getenv('APP_ENV')));
    }
    if (!in_array($env, ['production', 'staging', 'development', 'dev', 'local', 'test'], true)) {
        return 'production';
    }
    return $env;
}

/**
 * Convert internal role identities to clean UI display labels.
 * Internal values ('Machine Leader', 'QC Leader', etc.) remain unchanged in database/routing.
 * This function is used only for UI display to eliminate "Leader" wording.
 *
 * @param string $role The internal role identity
 * @return string The clean display label for UI
 */
function displayRole(string $role): string {
    return match (trim($role)) {
        'Machine Leader' => 'Production Control',
        'QC Leader' => 'Quality Control',
        'Dispatch Leader' => 'Dispatch Control',
        'Assembly Leader' => 'Assembly Control',
        default => $role,
    };
}

function app_is_production(): bool {
    return app_env() === 'production';
}

function app_debug_enabled(): bool {
    $flag = strtolower(trim((string)getenv('ERP_DEBUG')));
    if ($flag === '') {
        return !app_is_production();
    }
    return in_array($flag, ['1', 'true', 'yes', 'on'], true);
}

function app_dev_tools_enabled(): bool {
    $flag = strtolower(trim((string)getenv('ERP_ENABLE_DEV_TOOLS')));

    // Platform Mode is the canonical behavior switch. The deployment flag is
    // retained only as an explicit kill switch, not as a way to expose dev
    // tooling in Production or Demo.
    if (in_array($flag, ['0', 'false', 'no', 'off'], true)) {
        return false;
    }

    if (!class_exists('\App\Services\PlatformModeService') && defined('APP_ROOT')) {
        $platformModeServicePath = APP_ROOT . '/app/Services/PlatformModeService.php';
        if (is_file($platformModeServicePath)) {
            require_once $platformModeServicePath;
        }
    }

    if (class_exists('\App\Services\PlatformModeService')) {
        try {
            return \App\Services\PlatformModeService::currentMode() === \App\Services\PlatformModeService::MODE_DEVELOPMENT;
        } catch (\Throwable) {
            return false;
        }
    }

    if ($flag !== '') {
        return in_array($flag, ['1', 'true', 'yes', 'on'], true);
    }

    return !app_is_production();
}

function app_db_config_path(): string {
    return APP_ROOT . '/storage/db_config.php';
}

/**
 * @return array<string,mixed>|null
 */
function app_db_config_from_env(): ?array {
    $host = trim((string)(getenv('ERP_DB_HOST') !== false ? getenv('ERP_DB_HOST') : getenv('DB_HOST')));
    $name = trim((string)(getenv('ERP_DB_NAME') !== false ? getenv('ERP_DB_NAME') : getenv('DB_NAME')));
    $user = trim((string)(getenv('ERP_DB_USER') !== false ? getenv('ERP_DB_USER') : getenv('DB_USER')));
    $pass = (string)(getenv('ERP_DB_PASS') !== false ? getenv('ERP_DB_PASS') : getenv('DB_PASS'));
    $portRaw = trim((string)(getenv('ERP_DB_PORT') !== false ? getenv('ERP_DB_PORT') : getenv('DB_PORT')));
    $charset = trim((string)(getenv('ERP_DB_CHARSET') !== false ? getenv('ERP_DB_CHARSET') : getenv('DB_CHARSET')));
    $timezone = trim((string)(getenv('ERP_DB_TIMEZONE') !== false ? getenv('ERP_DB_TIMEZONE') : getenv('DB_TIMEZONE')));

    if ($host === '' && $name === '' && $user === '' && $pass === '' && $portRaw === '' && $charset === '' && $timezone === '') {
        return null;
    }

    return [
        'host' => $host !== '' ? $host : 'localhost',
        'name' => $name,
        'user' => $user,
        'pass' => $pass,
        'port' => ctype_digit($portRaw) ? (int)$portRaw : 3306,
        'charset' => $charset !== '' ? $charset : 'utf8mb4',
        'timezone' => $timezone !== '' ? $timezone : 'Asia/Tokyo',
        '_source' => 'env',
    ];
}

/**
 * @return array<string,mixed>|null
 */
function app_db_config(): ?array {
    $configFile = app_db_config_path();
    if (is_file($configFile)) {
        $config = require $configFile;
        if (is_array($config)) {
            $config['_source'] = 'file';
            return $config;
        }
    }

    return app_db_config_from_env();
}

/**
 * Get the current platform mode.
 *
 * Returns one of: production, development, demo
 * Default: production
 */
function platform_mode(): string {
    if (!class_exists('\App\Services\PlatformModeService')) {
        return 'production';
    }

    return \App\Services\PlatformModeService::currentMode();
}

/**
 * Check if platform is in production mode.
 */
function is_production_mode(): bool {
    if (!class_exists('\App\Services\PlatformModeService')) {
        return true;
    }

    return \App\Services\PlatformModeService::isProductionMode();
}

/**
 * Check if platform is in development mode.
 */
function is_development_mode(): bool {
    if (!class_exists('\App\Services\PlatformModeService')) {
        return false;
    }

    return \App\Services\PlatformModeService::isDevelopmentMode();
}

/**
 * Check if platform is in demo mode.
 */
function is_demo_mode(): bool {
    if (!class_exists('\App\Services\PlatformModeService')) {
        return false;
    }

    return \App\Services\PlatformModeService::isDemoMode();
}

/**
 * Check if a feature should be visible in the current platform mode.
 *
 * Common usage:
 *   if (should_show_feature('dev_tools')) { ... }
 *   if (should_show_feature('admin_diagnostics')) { ... }
 *   if (should_show_feature('destructive_actions')) { ... }
 *
 * Features:
 * - admin_tools: Admin tools (apps, routes, diagnostics)
 * - admin_diagnostics: Runtime diagnostics, debug panels
 * - dev_helpers: Schema sync, repair utilities
 * - destructive_actions: Delete, reset, dangerous operations
 * - demo_mode_indicator: Show demo mode watermark/badge
 *
 * Visibility by mode:
 *   Production:    admin_tools=no, diagnostics=no, dev_helpers=no, destructive=soft-gate
 *   Development:   admin_tools=yes, diagnostics=yes, dev_helpers=yes, destructive=yes
 *   Demo:          admin_tools=no, diagnostics=no, dev_helpers=no, destructive=no
 */
function should_show_feature(string $feature): bool {
    $mode = platform_mode();
    $feature = strtolower(trim($feature));

    // Map: feature -> visibility by mode
    $visibilityMatrix = [
        // Admin utilities
        'admin_tools' => [
            'production' => false,
            'development' => true,
            'demo' => false,
        ],
        'admin_diagnostics' => [
            'production' => false,
            'development' => true,
            'demo' => false,
        ],
        // Development helpers
        'dev_helpers' => [
            'production' => false,
            'development' => true,
            'demo' => false,
        ],
        // Destructive/dangerous actions
        'destructive_actions' => [
            'production' => false,  // Soft-gate (require extra confirmation)
            'development' => true,
            'demo' => false,        // Hide completely
        ],
        // Demo mode indicator
        'demo_mode_indicator' => [
            'production' => false,
            'development' => false,
            'demo' => true,
        ],
    ];

    // Unknown feature: default to false (safe)
    if (!isset($visibilityMatrix[$feature])) {
        return false;
    }

    return (bool)($visibilityMatrix[$feature][$mode] ?? false);
}

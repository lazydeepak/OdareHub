<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;

final class CompanySettingsService
{
    /**
     * @return array<string,mixed>
     */
    public function dashboard(): array
    {
        $settings = $this->currentSettings();
        $missing = [];
        foreach (['company.name', 'system.default_language', 'system.default_currency', 'system.timezone', 'ui.theme', 'ui.default_home'] as $key) {
            if (trim((string)($settings[$key] ?? '')) === '') {
                $missing[] = $key;
            }
        }

        return [
            'settings' => $settings,
            'status' => $missing === [] ? 'configured' : 'installed',
            'missing_keys' => $missing,
            'language_options' => $this->languageOptions(),
            'currency_options' => $this->currencyOptions(),
            'theme_options' => $this->themeOptions(),
            'home_route_options' => $this->homeRouteOptions(),
        ];
    }

    /**
     * @return array<string,string>
     */
    public function save(array $input): array
    {
        $systemName = trim((string)($input['system_name'] ?? ''));
        $companyName = trim((string)($input['company_name'] ?? ''));
        $displayName = $companyName !== '' ? $companyName : ($systemName !== '' ? $systemName : APP_NAME);

        $email = strtolower(trim((string)($input['company_email'] ?? '')));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Company email must be a valid email address.');
        }

        $website = trim((string)($input['company_website'] ?? ''));
        if ($website !== '' && !preg_match('#^https?://#i', $website)) {
            $website = 'https://' . ltrim($website, '/');
        }
        if ($website !== '' && filter_var($website, FILTER_VALIDATE_URL) === false) {
            throw new \RuntimeException('Company website must be a valid URL.');
        }

        $language = $this->normalizeLanguage((string)($input['default_language'] ?? ''));
        $currency = $this->normalizeCurrency((string)($input['default_currency'] ?? ''));
        $timezone = $this->normalizeTimezone((string)($input['timezone'] ?? ''));
        $theme = $this->normalizeThemePreference((string)($input['theme_preference'] ?? ''));
        $homeRoute = $this->normalizeHomeRoute((string)($input['default_home'] ?? ''));
        $userLabel = (string)(Auth::user()['email'] ?? 'system');

        $settings = [
            'system.name' => $systemName !== '' ? $systemName : $displayName,
            'company.name' => $displayName,
            'company.legal_name' => trim((string)($input['company_legal_name'] ?? '')),
            'company.code' => strtoupper(trim((string)($input['company_code'] ?? ''))),
            'company.email' => $email,
            'company.phone' => trim((string)($input['company_phone'] ?? '')),
            'company.website' => $website,
            'company.tax_id' => trim((string)($input['company_tax_id'] ?? '')),
            'company.registration_number' => trim((string)($input['company_registration_number'] ?? '')),
            'company.address_line1' => trim((string)($input['company_address_line1'] ?? '')),
            'company.address_line2' => trim((string)($input['company_address_line2'] ?? '')),
            'company.city' => trim((string)($input['company_city'] ?? '')),
            'company.state' => trim((string)($input['company_state'] ?? '')),
            'company.postal_code' => trim((string)($input['company_postal_code'] ?? '')),
            'company.country' => trim((string)($input['company_country'] ?? '')),
            'system.default_language' => $language,
            'system.locale' => $language,
            'system.default_currency' => $currency,
            'system.currency' => $currency,
            'system.timezone' => $timezone,
            'ui.theme' => $theme,
            'system.theme' => $theme,
            'ui.default_home' => $homeRoute,
            'system.default_dashboard' => $homeRoute,
            'company.updated_at' => date('Y-m-d H:i:s'),
            'company.updated_by' => $userLabel,
        ];

        $this->persistSettings($settings);

        return $settings;
    }

    /**
     * Keep legacy core_settings keys aligned with the canonical Organization module.
     *
     * @param array<string,mixed> $company
     * @return array<string,string>
     */
    public function syncFromOrganizationProfile(array $company): array
    {
        $displayName = trim((string)($company['company_name'] ?? ''));
        if ($displayName === '') {
            $displayName = trim((string)($company['legal_name'] ?? ''));
        }
        if ($displayName === '') {
            $displayName = APP_NAME;
        }

        $website = trim((string)($company['website'] ?? ''));
        if ($website !== '' && !preg_match('#^https?://#i', $website)) {
            $website = 'https://' . ltrim($website, '/');
        }

        $currency = $this->normalizeCurrency((string)($company['base_currency'] ?? 'JPY'));
        $timezone = $this->normalizeTimezone((string)($company['timezone'] ?? 'Asia/Tokyo'));
        $userLabel = (string)(Auth::user()['email'] ?? 'system');

        $settings = [
            'system.name' => $displayName,
            'company.name' => $displayName,
            'company.legal_name' => trim((string)($company['legal_name'] ?? '')),
            'company.code' => strtoupper(trim((string)($company['company_code'] ?? ''))),
            'company.email' => strtolower(trim((string)($company['email'] ?? ''))),
            'company.phone' => trim((string)($company['phone'] ?? '')),
            'company.website' => $website,
            'company.tax_id' => trim((string)($company['tax_no'] ?? '')),
            'company.registration_number' => trim((string)($company['registration_no'] ?? '')),
            'company.address_line1' => trim((string)($company['address_line_1'] ?? '')),
            'company.address_line2' => trim((string)($company['address_line_2'] ?? '')),
            'company.city' => trim((string)($company['city'] ?? '')),
            'company.state' => trim((string)($company['state'] ?? '')),
            'company.postal_code' => trim((string)($company['postal_code'] ?? '')),
            'company.country' => trim((string)($company['country'] ?? '')),
            'system.default_currency' => $currency,
            'system.currency' => $currency,
            'system.timezone' => $timezone,
            'company.updated_at' => date('Y-m-d H:i:s'),
            'company.updated_by' => $userLabel,
        ];

        $this->persistSettings($settings);

        return $settings;
    }

    /**
     * @return array<int,string>
     */
    public function ensureCanonicalSettings(): array
    {
        $raw = $this->rawSettings();
        $current = $this->currentSettings();
        $canonical = [
            'system.name' => (string)($current['system.name'] ?? APP_NAME),
            'company.name' => (string)($current['company.name'] ?? APP_NAME),
            'system.default_language' => (string)($current['system.default_language'] ?? 'en'),
            'system.locale' => (string)($current['system.default_language'] ?? 'en'),
            'system.default_currency' => (string)($current['system.default_currency'] ?? 'JPY'),
            'system.currency' => (string)($current['system.default_currency'] ?? 'JPY'),
            'system.timezone' => (string)($current['system.timezone'] ?? 'Asia/Tokyo'),
            'ui.theme' => (string)($current['ui.theme'] ?? 'system-liquid-glass'),
            'system.theme' => (string)($current['ui.theme'] ?? 'system-liquid-glass'),
            'ui.default_home' => (string)($current['ui.default_home'] ?? '/'),
            'system.default_dashboard' => (string)($current['ui.default_home'] ?? '/'),
        ];

        $toPersist = [];
        foreach ($canonical as $key => $value) {
            if (trim((string)($raw[$key] ?? '')) === '') {
                $toPersist[$key] = $value;
            }
        }

        if ($toPersist !== []) {
            $this->persistSettings($toPersist);
        }

        return array_keys($toPersist);
    }

    /**
     * @return array<string,string>
     */
    public function currentSettings(): array
    {
        $raw = $this->rawSettings();
        $companyName = trim((string)($raw['company.name'] ?? $raw['system.name'] ?? APP_NAME));
        $systemName = trim((string)($raw['system.name'] ?? $companyName ?: APP_NAME));

        return [
            'system.name' => $systemName !== '' ? $systemName : APP_NAME,
            'company.name' => $companyName !== '' ? $companyName : APP_NAME,
            'company.legal_name' => trim((string)($raw['company.legal_name'] ?? '')),
            'company.code' => strtoupper(trim((string)($raw['company.code'] ?? ''))),
            'company.email' => strtolower(trim((string)($raw['company.email'] ?? ''))),
            'company.phone' => trim((string)($raw['company.phone'] ?? '')),
            'company.website' => trim((string)($raw['company.website'] ?? '')),
            'company.tax_id' => trim((string)($raw['company.tax_id'] ?? '')),
            'company.registration_number' => trim((string)($raw['company.registration_number'] ?? '')),
            'company.address_line1' => trim((string)($raw['company.address_line1'] ?? '')),
            'company.address_line2' => trim((string)($raw['company.address_line2'] ?? '')),
            'company.city' => trim((string)($raw['company.city'] ?? '')),
            'company.state' => trim((string)($raw['company.state'] ?? '')),
            'company.postal_code' => trim((string)($raw['company.postal_code'] ?? '')),
            'company.country' => trim((string)($raw['company.country'] ?? '')),
            'system.default_language' => $this->normalizeLanguage((string)($raw['system.default_language'] ?? $raw['system.locale'] ?? 'en')),
            'system.default_currency' => $this->normalizeCurrency((string)($raw['system.default_currency'] ?? $raw['system.currency'] ?? 'JPY')),
            'system.timezone' => $this->normalizeTimezone((string)($raw['system.timezone'] ?? 'Asia/Tokyo')),
            'ui.theme' => $this->normalizeThemePreference((string)($raw['ui.theme'] ?? $raw['system.theme'] ?? 'system-liquid-glass')),
            'ui.default_home' => $this->normalizeHomeRoute((string)($raw['ui.default_home'] ?? $raw['system.default_dashboard'] ?? '/')),
            'company.updated_at' => trim((string)($raw['company.updated_at'] ?? '')),
            'company.updated_by' => trim((string)($raw['company.updated_by'] ?? '')),
        ];
    }

    /**
     * @return array<string,string>
     */
    public function languageOptions(): array
    {
        return supported_language_labels();
    }

    /**
     * @return array<string,string>
     */
    public function currencyOptions(): array
    {
        return [
            'JPY' => 'JPY',
            'USD' => 'USD',
            'NPR' => 'NPR',
        ];
    }

    /**
     * @return array<string,string>
     */
    public function themeOptions(): array
    {
        $serviceClass = '\\Apps\\Shell\\Services\\ThemePreferenceService';
        if (!class_exists($serviceClass) && defined('APP_ROOT')) {
            $servicePath = rtrim((string)APP_ROOT, '/') . '/apps/Shell/Services/ThemePreferenceService.php';
            if (is_file($servicePath)) {
                require_once $servicePath;
            }
        }

        if (class_exists($serviceClass)) {
            return $serviceClass::themeChoices();
        }

        // Minimal safe fallback when ThemePreferenceService is unavailable.
        return ['system-obsidian' => 'System - Obsidian'];
    }

    /**
     * @return array<string,string>
     */
    public function homeRouteOptions(): array
    {
        return [
            '/' => 'Home',
            '/apps/manufacturing' => 'Manufacturing Portal',
            '/apps/sbaio' => 'SBAIO Dashboard',
            '/admin/setup' => 'Setup Wizard',
            '/admin/apps' => 'Admin Tools',
        ];
    }

    private function normalizeLanguage(string $language): string
    {
        $language = strtolower(trim($language));
        if (!in_array($language, supported_langs(), true)) {
            return 'en';
        }

        return $language;
    }

    private function normalizeCurrency(string $currency): string
    {
        $currency = strtoupper(trim($currency));
        if (!in_array(strtolower($currency), supported_currencies(), true)) {
            return 'JPY';
        }

        return $currency;
    }

    private function normalizeTimezone(string $timezone): string
    {
        $timezone = trim($timezone);
        if ($timezone === '' || !in_array($timezone, timezone_identifiers_list(), true)) {
            return 'Asia/Tokyo';
        }

        return $timezone;
    }

    private function normalizeThemePreference(string $theme): string
    {
        $serviceClass = '\\Apps\\Shell\\Services\\ThemePreferenceService';
        if (!class_exists($serviceClass) && defined('APP_ROOT')) {
            $servicePath = rtrim((string)APP_ROOT, '/') . '/apps/Shell/Services/ThemePreferenceService.php';
            if (is_file($servicePath)) {
                require_once $servicePath;
            }
        }

        if (class_exists($serviceClass)) {
            return $serviceClass::normalizePreference($theme);
        }

        $theme = strtolower(trim($theme));
        if (!in_array($theme, ['system-obsidian'], true)) {
            return 'system-obsidian';
        }

        return $theme;
    }

    private function normalizeHomeRoute(string $homeRoute): string
    {
        $homeRoute = trim($homeRoute);
        if ($homeRoute === '' || $homeRoute[0] !== '/') {
            return '/';
        }

        return $homeRoute;
    }

    /**
     * @return array<string,string>
     */
    private function rawSettings(): array
    {
        $rows = DB::fetchAll(
            'SELECT setting_key, setting_value
             FROM core_settings
             WHERE setting_key LIKE ? OR setting_key IN (?,?,?,?,?,?,?,?,?,?)
             ORDER BY setting_key ASC',
            [
                'company.%',
                'system.name',
                'system.default_language',
                'system.locale',
                'system.default_currency',
                'system.currency',
                'system.timezone',
                'ui.theme',
                'system.theme',
                'ui.default_home',
                'system.default_dashboard',
            ]
        );

        $map = [];
        foreach ($rows as $row) {
            $key = trim((string)($row['setting_key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $map[$key] = (string)($row['setting_value'] ?? '');
        }

        return $map;
    }

    /**
     * @param array<string,string> $settings
     */
    private function persistSettings(array $settings): void
    {
        foreach ($settings as $key => $value) {
            DB::query(
                'INSERT INTO core_settings (setting_key, setting_value, updated_at) VALUES (?,?,NOW())
                 ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_at=NOW()',
                [$key, $value]
            );
        }
    }
}

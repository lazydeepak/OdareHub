<?php
declare(strict_types=1);

namespace App\Core\Company;

class CompanyConfig {
    private static ?array $config = null;

    public static function load(): array {
        if (self::$config !== null) {
            return self::$config;
        }
        $name = getenv('COMPANY_NAME') ?: 'Default Company';
        $locale = getenv('COMPANY_LOCALE') ?: 'en_US';
        $country = getenv('COMPANY_COUNTRY') ?: 'US';
        self::$config = [
            'name' => $name,
            'locale' => $locale,
            'country' => $country,
        ];
        return self::$config;
    }

    public static function get(string $key, $default = null) {
        $cfg = self::load();
        return $cfg[$key] ?? $default;
    }

    public static function set(string $key, $value): void {
        $cfg = self::load();
        $cfg[$key] = $value;
        self::$config = $cfg;
    }
}

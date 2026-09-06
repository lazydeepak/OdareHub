<?php
declare(strict_types=1);

namespace App\Core\Localization;

class LocalizationService {
    private static ?array $translations = null;
    private static string $currentLocale = 'en_US';

    public static function load(string $locale, ?string $path = null): void {
        self::$currentLocale = $locale;
        $dir = $path ?? __DIR__ . '/../../locales';
        $file = rtrim($dir, DIRECTORY_SEPARATOR) . '/' . $locale . '.php';
        if (file_exists($file)) {
            $translations = include $file;
            if (is_array($translations)) {
                self::$translations = $translations;
            } else {
                self::$translations = [];
            }
        } else {
            self::$translations = [];
        }
    }

    public static function translate(string $key, ?string $locale = null, string $default = ''): string {
        $locale = $locale ?? self::$currentLocale;
        if (self::$translations === null || $locale !== self::$currentLocale) {
            self::load($locale);
        }
        $parts = explode('.', $key);
        $value = self::$translations;
        foreach ($parts as $part) {
            if (is_array($value) && array_key_exists($part, $value)) {
                $value = $value[$part];
            } else {
                return $default ?: $key;
            }
        }
        return (string)$value;
    }
}

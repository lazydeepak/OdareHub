<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

final class StudioToolPresentationService
{
    /**
     * @param array<int,array<string,mixed>> $tools
     * @return array<int,array<string,mixed>>
     */
    public static function decorateTools(array $tools): array
    {
        $locale = self::locale();

        foreach ($tools as &$tool) {
            $nameKey = trim((string)($tool['name_key'] ?? ''));
            $descriptionKey = trim((string)($tool['description_key'] ?? ''));
            $tool['display_name'] = self::text($locale, $nameKey, (string)($tool['name'] ?? 'Studio Tool'));
            $tool['description'] = self::text($locale, $descriptionKey, '');
        }
        unset($tool);

        return $tools;
    }

    /**
     * @return array<string,string>
     */
    public static function locale(): array
    {
        $lang = function_exists('current_lang') ? current_lang() : 'en';
        $english = self::loadLocale('en');
        if ($lang === 'en') {
            return $english;
        }

        return array_replace($english, self::loadLocale($lang));
    }

    /**
     * @param array<string,string> $locale
     */
    public static function text(array $locale, string $key, string $fallback = ''): string
    {
        if ($key !== '' && isset($locale[$key])) {
            return (string)$locale[$key];
        }
        return $fallback !== '' ? $fallback : $key;
    }

    /**
     * @return array<string,string>
     */
    private static function loadLocale(string $lang): array
    {
        $safeLang = in_array($lang, ['en', 'ja', 'ne'], true) ? $lang : 'en';
        $path = APP_ROOT . '/apps/Studio/lang/' . $safeLang . '.php';
        if (!is_file($path)) {
            return [];
        }

        $loaded = require $path;
        return is_array($loaded) ? $loaded : [];
    }
}

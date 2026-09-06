<?php
declare(strict_types=1);

namespace Apps\Shell\Services;

final class ThemePreferenceService
{
    /** @var array<string,string>|null */
    private static ?array $cachedThemeChoices = null;

    /**
     * @return array<string,string>
     */
    public static function themeChoices(): array
    {
        if (self::$cachedThemeChoices !== null) {
            return self::$cachedThemeChoices;
        }

        $styles = self::availableColorStyles();
        if ($styles === []) {
            $styleKey = self::preferredStyleKey();
            $styles = [
                $styleKey => self::humanizeStyleKey($styleKey),
            ];
        }

        $choices = [];
        foreach (['system', 'dark', 'light'] as $mode) {
            $modeLabel = ucfirst($mode);
            foreach ($styles as $styleKey => $styleLabel) {
                $prefKey = $mode . '-' . $styleKey;
                $choices[$prefKey] = $modeLabel . ' - ' . $styleLabel;
            }
        }

        self::$cachedThemeChoices = $choices;
        return self::$cachedThemeChoices;
    }

    /**
     * @return array<string,string>
     */
    public static function availableColorStyles(): array
    {
        $catalogPath = dirname(__DIR__) . '/Resources/published-theme-options.json';
        $raw = @file_get_contents($catalogPath);
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $catalog = json_decode($raw, true);
        $published = is_array($catalog) && is_array($catalog['styles'] ?? null)
            ? $catalog['styles']
            : [];

        $styles = [];
        foreach ($published as $styleKey => $styleLabel) {
            $key = strtolower(trim((string)$styleKey));
            $label = trim((string)$styleLabel);
            if ($key !== '' && $label !== '') {
                $styles[$key] = $label;
            }
        }
        ksort($styles);
        return $styles;
    }

    /**
     * @return string[]
     */
    public static function allowedPreferences(): array
    {
        return array_keys(self::themeChoices());
    }

    public static function defaultPreference(): string
    {
        $safeFallback = 'system-' . self::preferredStyleKey();
        $configuredPreference = $safeFallback;
        if (function_exists('core_setting')) {
            $configuredPreference = (string)core_setting('ui.theme', core_setting('system.theme', $safeFallback));
        } elseif (function_exists('default_theme_preference')) {
            $configuredPreference = (string)default_theme_preference();
        }

        return self::normalizePreference($configuredPreference, $safeFallback);
    }

    public static function normalizePreference(?string $value, ?string $fallback = null): string
    {
        $raw = strtolower(trim((string)$value));
        $preferredStyle = self::preferredStyleKey();
        if ($raw === 'light') {
            $raw = 'light-' . $preferredStyle;
        } elseif ($raw === 'dark') {
            $raw = 'dark-' . $preferredStyle;
        } elseif ($raw === 'system') {
            $raw = 'system-' . $preferredStyle;
        }

        if (in_array($raw, self::allowedPreferences(), true)) {
            return $raw;
        }

        $fallbackPreference = strtolower(trim((string)($fallback ?? ('system-' . self::preferredStyleKey()))));
        if (in_array($fallbackPreference, self::allowedPreferences(), true)) {
            return $fallbackPreference;
        }

        return 'system-' . $preferredStyle;
    }

    public static function modeFromPreference(string $preference): string
    {
        $normalized = self::normalizePreference($preference);
        $parts = explode('-', $normalized);
        $mode = $parts[0] ?? 'system';
        if ($mode !== 'light' && $mode !== 'dark') {
            return 'system';
        }

        return $mode;
    }

    public static function colorStyleFromPreference(string $preference): string
    {
        $normalized = self::normalizePreference($preference);
        $parts = explode('-', $normalized);
        $style = implode('-', array_slice($parts, 1));
        return $style !== '' ? $style : self::preferredStyleKey();
    }

    private static function preferredStyleKey(): string
    {
        $styles = self::availableColorStyles();
        $first = array_key_first($styles);
        return is_string($first) && $first !== '' ? $first : 'liquid-glass';
    }

    private static function humanizeStyleKey(string $styleKey): string
    {
        $label = str_replace(['-', '_', '.'], ' ', strtolower(trim($styleKey)));
        return $label !== '' ? ucwords($label) : 'Theme';
    }
}

<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\CustomizationStudio\Diagnose\ThemeDoctor\Services;

final class ThemeRegistryReaderService
{
    private const APPROVED_REGISTRY_FILE = APP_ROOT . '/storage/theme_registry/registry.json';
    private const APPROVED_THEMES_DIR = APP_ROOT . '/storage/theme_registry/themes';
    private const DRAFT_THEMES_DIR = APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/ThemeDoctor/Themes';

    /**
     * @param array<int,array<string,mixed>> $tokenSelectors
     * @return array<string,mixed>
     */
    public static function readSummary(array $tokenSelectors): array
    {
        $approvedRegistry = self::readJsonFile(self::APPROVED_REGISTRY_FILE);
        $approvedThemes = self::readApprovedThemes($approvedRegistry);
        $draftThemes = self::readDraftThemes();
        $runtimeDetectedThemes = self::deriveRuntimeThemes($tokenSelectors);
        $registryFound = is_file(self::APPROVED_REGISTRY_FILE);

        $activeTheme = '';
        $defaultTheme = '';
        $activeThemeSource = 'unresolved';
        $defaultThemeSource = 'unresolved';
        if ($registryFound) {
            $activeTheme = trim((string)($approvedRegistry['active_theme'] ?? ''));
            $defaultTheme = trim((string)($approvedRegistry['default_theme'] ?? ''));
            if ($activeTheme !== '') {
                $activeThemeSource = 'approved_registry';
            }
            if ($defaultTheme !== '') {
                $defaultThemeSource = 'approved_registry';
            }
        }

        if ($activeTheme === '' && isset($runtimeDetectedThemes[0]['key'])) {
            $activeTheme = (string)$runtimeDetectedThemes[0]['key'];
            $activeThemeSource = 'runtime_detected_fallback';
        }
        if ($defaultTheme === '' && $activeTheme !== '') {
            $defaultTheme = $activeTheme;
            $defaultThemeSource = $activeThemeSource;
        }

        $degradedReasons = [];
        if (!$registryFound) {
            $degradedReasons[] = 'approved_registry_missing';
        }
        if ($registryFound && $approvedThemes === []) {
            $degradedReasons[] = 'approved_registry_empty';
        }
        if (!$registryFound && $runtimeDetectedThemes !== []) {
            $degradedReasons[] = 'runtime_fallback_in_use';
        }

        return [
            'approved_registry_found' => $registryFound,
            'approved_registry_path' => '/storage/theme_registry/registry.json',
            'approved_themes_path' => '/storage/theme_registry/themes',
            'draft_themes_path' => '/apps/Studio/Tools/CustomizationStudio/Diagnose/ThemeDoctor/Themes',
            'active_theme' => $activeTheme,
            'active_theme_source' => $activeThemeSource,
            'default_theme' => $defaultTheme,
            'default_theme_source' => $defaultThemeSource,
            'approved_themes' => $approvedThemes,
            'runtime_detected_themes' => $runtimeDetectedThemes,
            'draft_themes' => $draftThemes,
            'degraded' => $degradedReasons !== [],
            'degraded_reasons' => $degradedReasons,
            'controls' => [
                'create' => ['enabled' => false, 'mode' => 'draft_only_placeholder'],
                'duplicate' => ['enabled' => false, 'mode' => 'draft_only_placeholder'],
                'delete' => ['enabled' => false, 'mode' => 'blocked_until_v2'],
                'set_default' => ['enabled' => false, 'mode' => 'blocked_until_v2'],
            ],
        ];
    }

    /**
     * @param array<string,mixed> $approvedRegistry
     * @return array<int,array<string,string>>
     */
    private static function readApprovedThemes(array $approvedRegistry): array
    {
        $themes = [];
        $registryThemes = $approvedRegistry['themes'] ?? [];
        if (is_array($registryThemes)) {
            foreach ($registryThemes as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $theme = self::normalizeThemeItem($item, 'approved');
                if ($theme !== null) {
                    $themes[] = $theme;
                }
            }
        }

        if ($themes !== []) {
            return self::uniqueByKey($themes);
        }

        if (!is_dir(self::APPROVED_THEMES_DIR)) {
            return [];
        }

        $paths = glob(self::APPROVED_THEMES_DIR . '/*.json');
        if ($paths === false) {
            return [];
        }

        foreach ($paths as $path) {
            $json = self::readJsonFile($path);
            if ($json === []) {
                continue;
            }
            $theme = self::normalizeThemeItem($json, 'approved');
            if ($theme !== null) {
                $themes[] = $theme;
            }
        }

        return self::uniqueByKey($themes);
    }

    /**
     * @return array<int,array<string,string>>
     */
    private static function readDraftThemes(): array
    {
        if (!is_dir(self::DRAFT_THEMES_DIR)) {
            return [];
        }

        $paths = glob(self::DRAFT_THEMES_DIR . '/*.json');
        if ($paths === false) {
            return [];
        }

        $themes = [];
        foreach ($paths as $path) {
            $json = self::readJsonFile($path);
            if ($json === []) {
                continue;
            }
            $theme = self::normalizeThemeItem($json, 'draft');
            if ($theme !== null) {
                $themes[] = $theme;
            }
        }

        return self::uniqueByKey($themes);
    }

    /**
     * @param array<int,array<string,mixed>> $tokenSelectors
     * @return array<int,array<string,string>>
     */
    private static function deriveRuntimeThemes(array $tokenSelectors): array
    {
        $themes = [];
        foreach ($tokenSelectors as $selector) {
            $key = strtolower(trim((string)($selector['theme'] ?? '')));
            if ($key === '') {
                continue;
            }
            $themes[] = [
                'key' => $key,
                'label' => self::humanizeKey($key),
                'status' => 'runtime-detected',
                'source' => '/assets/theme.css',
            ];
        }

        return self::uniqueByKey($themes);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,string>|null
     */
    private static function normalizeThemeItem(array $payload, string $fallbackStatus): ?array
    {
        $key = strtolower(trim((string)($payload['key'] ?? $payload['theme_key'] ?? '')));
        if ($key === '') {
            return null;
        }

        $label = trim((string)($payload['label'] ?? ''));
        if ($label === '') {
            $label = self::humanizeKey($key);
        }

        $status = strtolower(trim((string)($payload['status'] ?? $fallbackStatus)));
        if ($status === '') {
            $status = $fallbackStatus;
        }

        $source = trim((string)($payload['source'] ?? ''));
        if ($source === '') {
            $source = ($status === 'draft')
                ? '/apps/Studio/Tools/CustomizationStudio/Diagnose/ThemeDoctor/Themes/' . $key . '.json'
                : '/storage/theme_registry/themes/' . $key . '.json';
        }

        $tokensPayload = isset($payload['tokens']) && is_array($payload['tokens']) ? $payload['tokens'] : [];
        $tokens = [];
        foreach (['accent', 'bg', 'text', 'font_sans'] as $tokenName) {
            $tokenValue = trim((string)($tokensPayload[$tokenName] ?? ''));
            if ($tokenValue !== '') {
                $tokens[$tokenName] = $tokenValue;
            }
        }

        $result = [
            'key' => $key,
            'label' => $label,
            'status' => $status,
            'source' => $source,
        ];
        if ($tokens !== []) {
            $result['tokens'] = $tokens;
        }

        return $result;
    }

    /**
     * @param array<string,mixed>|mixed $json
     * @return array<string,mixed>
     */
    private static function readJsonFile(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $raw = @file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    /**
     * @param array<int,array<string,string>> $items
     * @return array<int,array<string,string>>
     */
    private static function uniqueByKey(array $items): array
    {
        $indexed = [];
        foreach ($items as $item) {
            $key = strtolower(trim((string)($item['key'] ?? '')));
            if ($key === '') {
                continue;
            }
            if (!array_key_exists($key, $indexed)) {
                $indexed[$key] = $item;
            }
        }

        ksort($indexed, SORT_STRING);

        return array_values($indexed);
    }

    private static function humanizeKey(string $key): string
    {
        $normalized = trim(str_replace(['_', '-'], ' ', $key));
        return ucwords($normalized === '' ? 'Theme' : $normalized);
    }
}

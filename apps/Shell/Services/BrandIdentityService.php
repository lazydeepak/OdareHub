<?php
declare(strict_types=1);

namespace Apps\Shell\Services;

/**
 * Shell-owned runtime brand identity resolver.
 *
 * Technical constants, routes, folders, and namespaces keep their existing names.
 * This service is only for presentation-facing identity values.
 */
final class BrandIdentityService
{
    private const PLATFORM_NAME = 'OdareHub';
    private const APP_STUDIO_NAME = 'ERP App Studio';
    private const DEFAULT_APP_NAME = 'Workspace';
    private const DEFAULT_VERSION_LABEL = 'Identity Stabilization';

    /**
     * @return array{
     *   platform_name:string,
     *   instance_name:string,
     *   app_name:string,
     *   version_label:string,
     *   app_studio_name:string
     * }
     */
    public static function runtime(array $overrides = []): array
    {
        $platformName = self::normalizeText((string)($overrides['platform_name'] ?? self::env('ODAREHUB_PLATFORM_NAME', self::env('SUSANKHYA_PLATFORM_NAME', self::PLATFORM_NAME))));
        if ($platformName === '') {
            $platformName = self::PLATFORM_NAME;
        }

        $instanceName = self::normalizeText((string)($overrides['instance_name'] ?? self::resolveInstanceName($platformName)));
        if ($instanceName === '') {
            $instanceName = $platformName;
        }

        $appName = self::normalizeText((string)($overrides['app_name'] ?? self::env('ODAREHUB_APP_NAME', self::env('SUSANKHYA_APP_NAME', self::DEFAULT_APP_NAME))));
        if ($appName === '') {
            $appName = self::DEFAULT_APP_NAME;
        }

        $versionLabel = self::normalizeText((string)($overrides['version_label'] ?? self::env('ODAREHUB_VERSION_LABEL', self::env('SUSANKHYA_VERSION_LABEL', self::DEFAULT_VERSION_LABEL))));
        if ($versionLabel === '') {
            $versionLabel = self::DEFAULT_VERSION_LABEL;
        }

        return [
            'platform_name' => $platformName,
            'instance_name' => $instanceName,
            'app_name' => $appName,
            'version_label' => $versionLabel,
            'app_studio_name' => self::APP_STUDIO_NAME,
        ];
    }

    public static function platformName(): string
    {
        return self::runtime()['platform_name'];
    }

    public static function instanceName(array $overrides = []): string
    {
        return self::runtime($overrides)['instance_name'];
    }

    public static function normalizeText(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $replacements = [
            '/\bERP Engine\b/i' => self::PLATFORM_NAME,
            '/\bGUI Studio\b/i' => self::APP_STUDIO_NAME,
        ];

        foreach ($replacements as $pattern => $replacement) {
            $value = (string)preg_replace($pattern, $replacement, $value);
        }

        return $value;
    }

    private static function resolveInstanceName(string $fallback): string
    {
        if (function_exists('app_display_name')) {
            return (string)app_display_name();
        }

        if (function_exists('t')) {
            $translated = (string)t('app.name');
            if ($translated !== '' && $translated !== 'app.name') {
                return $translated;
            }
        }

        return $fallback;
    }

    private static function env(string $key, string $default): string
    {
        $value = getenv($key);
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        return $default;
    }
}

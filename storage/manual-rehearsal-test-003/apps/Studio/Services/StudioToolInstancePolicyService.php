<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

final class StudioToolInstancePolicyService
{
    private const POLICY_FILE = APP_ROOT . '/apps/Studio/config/studio_tool_policy.php';

    /**
     * @return array<string,mixed>
     */
    public static function resolvePolicy(): array
    {
        $default = [
            'studio_tools' => self::defaultPolicyMap(),
        ];

        $path = self::POLICY_FILE;
        if (!is_file($path)) {
            return $default;
        }

        $loaded = require $path;
        if (!is_array($loaded)) {
            return $default;
        }

        $map = self::normalizePolicyMap((array)($loaded['studio_tools'] ?? []));
        if ($map === []) {
            return $default;
        }

        return [
            'studio_tools' => array_replace(self::defaultPolicyMap(), $map),
        ];
    }

    public static function isEnabled(string $toolKey, array $manifest = []): bool
    {
        $state = self::stateFor($toolKey, $manifest);
        return $state === 'enabled';
    }

    public static function stateFor(string $toolKey, array $manifest = []): string
    {
        $safeKey = self::normalizeKey($toolKey);
        if ($safeKey === '') {
            return 'disabled';
        }

        $policy = self::resolvePolicy();
        $map = (array)($policy['studio_tools'] ?? []);
        if (array_key_exists($safeKey, $map)) {
            return ((string)$map[$safeKey] === 'enabled') ? 'enabled' : 'disabled';
        }

        if ($manifest !== []) {
            $defaultEnabled = !empty($manifest['default_enabled']);
            return $defaultEnabled ? 'enabled' : 'disabled';
        }

        // Deterministic fallback required by lifecycle contract.
        return 'disabled';
    }

    /**
     * @return array<string,string>
     */
    private static function defaultPolicyMap(): array
    {
        return [
            'view_editor' => 'enabled',
            'menu_editor' => 'enabled',
            'theme_tool' => 'enabled',
            'customization_studio' => 'disabled',
            'app_builder' => 'disabled',
            'module_builder' => 'disabled',
            'report_designer' => 'disabled',
            'label_designer' => 'disabled',
            'db_schema_tool' => 'disabled',
            'token_impact_explorer' => 'enabled',
            'style_compliance' => 'enabled',
            'helper_tool' => 'enabled',
            'engineering_workspaces' => 'enabled',
            'owner_structure_scan' => 'enabled',
        ];
    }

    /**
     * @param array<string,mixed> $raw
     * @return array<string,string>
     */
    private static function normalizePolicyMap(array $raw): array
    {
        $normalized = [];
        foreach ($raw as $key => $value) {
            $safeKey = self::normalizeKey((string)$key);
            if ($safeKey === '') {
                continue;
            }
            $state = strtolower(trim((string)$value));
            $normalized[$safeKey] = ($state === 'enabled') ? 'enabled' : 'disabled';
        }
        return $normalized;
    }

    private static function normalizeKey(string $value): string
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return '';
        }
        return (string)preg_replace('/[^a-z0-9_]+/', '_', $normalized);
    }
}

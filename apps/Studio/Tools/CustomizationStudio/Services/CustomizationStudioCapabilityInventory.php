<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\CustomizationStudio\Services;

use Apps\Studio\Services\StudioGovernedToolRegistryService;

final class CustomizationStudioCapabilityInventory
{
    private const STUDIO_TOOLS_ROOT = 'apps/Studio/Tools/CustomizationStudio';

    /**
     * Known keys for CustomizationStudio sub-tools.
     */
    private const KNOWN_STYLE_TOOL_KEYS = [
        'css_token_editor',
        'css_live_editor',
        'css_selector_tool',
        'style_compliance',
        'theme_tool',
        'theme_doctor',
        'token_impact_explorer',
        'visual_customizer',
        'special_effects',
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function inventory(): array
    {
        $registry = self::loadRegistryTools();
        $inventory = [];

        foreach (self::KNOWN_STYLE_TOOL_KEYS as $key) {
            $manifest = $registry[$key] ?? null;
            $inventory[$key] = self::capabilityRow($key, $manifest);
        }

        return $inventory;
    }

    /**
     * @return array<string, array{manifest: ?array<string,mixed>, has_manifest: bool, is_placeholder: bool, can_mutate: bool, has_snapshot: bool, has_rollback: bool, has_diff: bool, writes_artifact: bool, requires_approval: bool, status: string, registered: bool}>
     */
    public static function inventoryWithDiagnostics(): array
    {
        $registry = self::loadRegistryTools();
        $inventory = [];

        foreach (self::KNOWN_STYLE_TOOL_KEYS as $key) {
            $manifest = $registry[$key] ?? null;
            $inventory[$key] = self::capabilityRowWithDiagnostics($key, $manifest);
        }

        return $inventory;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function registryOnly(): array
    {
        $registry = self::loadRegistryTools();
        $result = [];

        foreach ($registry as $key => $tool) {
            $manifestPath = (string)($tool['_manifest_path'] ?? '');
            if ($manifestPath !== '' && str_contains($manifestPath, self::STUDIO_TOOLS_ROOT)) {
                $result[$key] = $tool;
            }
        }

        return $result;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private static function loadRegistryTools(): array
    {
        $tools = StudioGovernedToolRegistryService::listTools();
        $indexed = [];
        foreach ($tools as $tool) {
            $key = (string)($tool['key'] ?? '');
            if ($key !== '') {
                $indexed[$key] = $tool;
            }
        }
        return $indexed;
    }

    /**
     * @return array{manifest: ?array<string,mixed>, has_manifest: bool, is_placeholder: bool, can_mutate: bool, has_snapshot: bool, has_rollback: bool, has_diff: bool, writes_artifact: bool, requires_approval: bool, status: string, registered: bool}
     */
    private static function capabilityRowWithDiagnostics(string $key, ?array $manifest): array
    {
        $registered = $manifest !== null;
        return [
            'manifest' => $manifest,
            'has_manifest' => $registered,
            'is_placeholder' => $registered && !empty($manifest['placeholder']),
            'can_mutate' => $registered && !empty($manifest['can_modify']),
            'has_snapshot' => $registered && !empty($manifest['supports_snapshot']),
            'has_rollback' => $registered && !empty($manifest['supports_rollback']),
            'has_diff' => $registered && !empty($manifest['supports_diff']),
            'writes_artifact' => $registered && !empty($manifest['writes_to_owner_artifact']),
            'requires_approval' => $registered && !empty($manifest['requires_approval']),
            'status' => $registered ? (string)($manifest['status'] ?? 'unknown') : 'unregistered',
            'registered' => $registered,
        ];
    }

    /**
     * @return array{key: string, name: string, status: string, can_modify: bool, is_placeholder: bool, has_manifest: bool}
     */
    private static function capabilityRow(string $key, ?array $manifest): array
    {
        if ($manifest === null) {
            return [
                'key' => $key,
                'name' => $key,
                'status' => 'unregistered',
                'can_modify' => false,
                'is_placeholder' => false,
                'has_manifest' => false,
            ];
        }

        return [
            'key' => $key,
            'name' => (string)($manifest['name'] ?? $key),
            'status' => (string)($manifest['status'] ?? 'unknown'),
            'can_modify' => !empty($manifest['can_modify']),
            'is_placeholder' => !empty($manifest['placeholder']),
            'has_manifest' => true,
        ];
    }
}

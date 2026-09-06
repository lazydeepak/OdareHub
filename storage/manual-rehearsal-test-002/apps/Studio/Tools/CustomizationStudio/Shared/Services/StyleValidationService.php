<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\CustomizationStudio\Shared\Services;

use Apps\Studio\Tools\CustomizationStudio\Services\VisualCustomizerMetadataService;
use Apps\Studio\Tools\CustomizationStudio\Diagnose\TokenImpactExplorer\Services\TokenImpactDiscoveryService;

final class StyleValidationService
{
    private function __construct()
    {
    }

    public static function discoverSockets(): array
    {
        $map = VisualCustomizerMetadataService::discoverSocketCatalogsMap();
        $catalogs = [];
        $sockets = [];
        foreach ($map as $catalogId => $catalog) {
            $catalogs[$catalogId] = [
                'id' => $catalogId,
                'name' => $catalog['name'],
                'file' => $catalog['file'],
                'socket_count' => $catalog['socket_count'],
            ];
            foreach ($catalog['sockets'] as $socket) {
                $socket['catalog_id'] = $catalogId;
                $sockets[] = $socket;
            }
        }
        return [
            'catalogs' => $catalogs,
            'sockets' => $sockets,
            'total_sockets' => count($sockets),
            'total_catalogs' => count($catalogs),
        ];
    }

    public static function resolveSocket(string $socketId): ?array
    {
        $trimmed = trim($socketId);
        if ($trimmed === '') {
            return null;
        }

        $map = VisualCustomizerMetadataService::discoverSocketCatalogsMap();
        foreach ($map as $catalogId => $catalog) {
            foreach ($catalog['sockets'] as $socket) {
                if ($socket['id'] === $trimmed) {
                    $socket['catalog_id'] = $catalogId;
                    $socket['catalog_name'] = $catalog['name'];
                    return $socket;
                }
            }
        }

        return null;
    }

    public static function exploreToken(string $tokenName): array
    {
        $explored = TokenImpactDiscoveryService::exploreToken($tokenName);
        $catalog = $explored['catalog_match'] ?? null;

        $sourcePath = null;
        $editable = false;
        if ($catalog !== null) {
            $advancedToken = (string)($catalog['advanced_token'] ?? '');
            $sourceToken = $advancedToken !== '' ? $advancedToken : ($catalog['token_name'] ?? $tokenName);
            $found = CssPathResolver::canonicalSourcePath($sourceToken);
            if ($found !== null) {
                $sourcePath = CssPathResolver::relativePath($found);
                $editable = str_contains($sourcePath, 'resources/themes');
            }
        }

        $owner = 'other';
        if ($catalog !== null && !empty($catalog['owner'])) {
            $owner = $catalog['owner'];
        } elseif (!empty($explored['owners'])) {
            $owner = $explored['owners'][0]['name'] ?? 'other';
        }

        $runtimeConsumed = false;
        if ($catalog !== null) {
            $runtimeConsumed = ($catalog['runtime_consumption'] ?? 'catalog_only_not_consumed') !== 'catalog_only_not_consumed';
        }

        return [
            'ok' => true,
            'token_name' => $explored['token_name'] ?? $tokenName,
            'owner' => $owner,
            'source_path' => $sourcePath,
            'editable' => $editable,
            'runtime_consumed' => $runtimeConsumed,
            'total_occurrences' => $explored['total_occurrences'] ?? 0,
            'definitions' => $explored['definitions'] ?? [],
            'references' => $explored['references'] ?? [],
            'catalog_match' => $catalog,
            'owners' => $explored['owners'] ?? [],
        ];
    }

    public static function resolveSourcePath(string $tokenName): ?string
    {
        $found = CssPathResolver::canonicalSourcePath($tokenName);
        if ($found === null) {
            return null;
        }
        return CssPathResolver::relativePath($found);
    }

    public static function validateTokenValue(string $value): array
    {
        $trimmed = trim($value);
        $blockers = [];

        if ($trimmed === '') {
            return [
                'valid' => false,
                'sanitized' => null,
                'blockers' => ['empty_value'],
            ];
        }

        if (preg_match('/[{}<>]/', $trimmed) === 1) {
            return [
                'valid' => false,
                'sanitized' => null,
                'blockers' => ['contains_invalid_characters'],
            ];
        }

        if (preg_match('/expression\s*\(/i', $trimmed) === 1) {
            return [
                'valid' => false,
                'sanitized' => null,
                'blockers' => ['contains_expression'],
            ];
        }

        if (preg_match('/javascript\s*:/i', $trimmed) === 1) {
            return [
                'valid' => false,
                'sanitized' => null,
                'blockers' => ['contains_javascript'],
            ];
        }

        if (preg_match('/url\s*\([^)]*\)/i', $trimmed) === 1) {
            if (preg_match('/url\s*\(\s*["\']?data:/i', $trimmed) !== 1) {
                return [
                    'valid' => false,
                    'sanitized' => null,
                    'blockers' => ['non_data_url_not_allowed'],
                ];
            }
        }

        return [
            'valid' => true,
            'sanitized' => $trimmed,
            'blockers' => [],
        ];
    }

    public static function diff(string $tokenName, ?string $currentValue, string $proposedValue): array
    {
        $validation = self::validateTokenValue($proposedValue);
        $diff = StyleDiffService::compute($tokenName, $currentValue, $proposedValue);

        $socket = self::resolveSocketByTokenName($tokenName);

        return [
            'ok' => $validation['valid'],
            'socket_id' => $socket['id'] ?? null,
            'token_name' => $diff['token_name'],
            'owner' => $socket['owner'] ?? 'other',
            'source_path' => $socket !== null ? (self::resolveSourcePath($diff['token_name']) ?? null) : null,
            'current_value' => $diff['current_value'],
            'proposed_value' => $diff['proposed_value'],
            'changed' => $diff['changed'],
            'value_valid' => $validation['valid'],
            'sanitized_value' => $validation['sanitized'],
            'editable' => $socket !== null && str_contains($socket['scope'] ?? '', 'editable'),
            'runtime_consumed' => $socket !== null
                && ($socket['runtime_consumption'] ?? 'catalog_only_not_consumed') !== 'catalog_only_not_consumed',
            'blockers' => $validation['blockers'],
            'diagnostics' => [],
        ];
    }

    public static function readiness(): array
    {
        $catalogsAccessible = is_dir(APP_ROOT . '/apps/Shell/DesignSystem/Resources/socket-catalog');
        $themeSourcesAccessible = is_dir(APP_ROOT . '/resources/themes');
        $registryAccessible = is_file(
            APP_ROOT . '/platform/Style/Contracts/ApprovedStyleReaderContract.php'
        );

        $blockers = [];
        if (!$catalogsAccessible) {
            $blockers[] = 'socket_catalog_unavailable';
        }
        if (!$themeSourcesAccessible) {
            $blockers[] = 'theme_sources_unavailable';
        }

        return [
            'ready' => $catalogsAccessible && $themeSourcesAccessible,
            'catalogs_accessible' => $catalogsAccessible,
            'theme_sources_accessible' => $themeSourcesAccessible,
            'registry_accessible' => $registryAccessible,
            'blockers' => $blockers,
            'runtime_consumption_enabled' => false,
            'runtime_application_enabled' => false,
            'shell_insertion_enabled' => false,
            'rendered_proof_enabled' => false,
        ];
    }

    private static function resolveSocketByTokenName(string $tokenName): ?array
    {
        $normalized = strtolower(trim($tokenName));
        if ($normalized === '') {
            return null;
        }
        if (!str_starts_with($normalized, '--')) {
            $normalized = '--' . ltrim($normalized, '-');
        }

        $map = VisualCustomizerMetadataService::discoverSocketCatalogsMap();
        foreach ($map as $catalog) {
            foreach ($catalog['sockets'] as $socket) {
                $advancedToken = strtolower(trim((string)($socket['advanced_token'] ?? '')));
                if ($advancedToken === $normalized) {
                    $socket['catalog_id'] = $catalog['id'];
                    return $socket;
                }
                $socketToken = '--' . str_replace('.', '-', $socket['id']);
                if ($socketToken === $normalized) {
                    $socket['catalog_id'] = $catalog['id'];
                    return $socket;
                }
            }
        }

        return null;
    }
}

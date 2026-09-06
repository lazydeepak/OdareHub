<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

final class StudioResourceTypeRegistryService
{
    /**
     * @var array<string,array<string,mixed>>
     */
    private const RESOURCE_TYPES = [
        'app' => ['name' => 'App', 'kind' => 'owner_artifact'],
        'module' => ['name' => 'Module', 'kind' => 'owner_artifact'],
        'view' => ['name' => 'View', 'kind' => 'owner_artifact'],
        'dashboard' => ['name' => 'Dashboard', 'kind' => 'owner_artifact'],
        'route' => ['name' => 'Route', 'kind' => 'owner_artifact'],
        'nav' => ['name' => 'Navigation', 'kind' => 'owner_artifact'],
        'db_schema' => ['name' => 'DB Schema', 'kind' => 'owner_contract'],
        'widget' => ['name' => 'Widget', 'kind' => 'owner_artifact'],
        'report' => ['name' => 'Report', 'kind' => 'owner_artifact'],
        'theme' => ['name' => 'Theme', 'kind' => 'owner_artifact'],
        'css_selector' => ['name' => 'CSS Selector', 'kind' => 'owner_artifact'],
        'permission_profile' => ['name' => 'Permission Profile', 'kind' => 'governed_contract'],
        'package' => ['name' => 'Package', 'kind' => 'owner_artifact'],
        'surface' => ['name' => 'Surface', 'kind' => 'owner_artifact'],
        'component' => ['name' => 'Component', 'kind' => 'owner_artifact'],
        'unknown' => ['name' => 'Unknown', 'kind' => 'inspect_only'],
    ];

    /**
     * @return array<string,array<string,mixed>>
     */
    public static function listTypes(): array
    {
        return self::RESOURCE_TYPES;
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function findType(string $type): ?array
    {
        $safeType = self::normalizeKey($type);
        if ($safeType === '') {
            return null;
        }
        $typeDef = self::RESOURCE_TYPES[$safeType] ?? null;
        return is_array($typeDef) ? $typeDef : null;
    }

    /**
     * @param array<string,mixed>|null $node
     * @return array<string,mixed>
     */
    public static function describeLibraryNode(?array $node): array
    {
        if (!is_array($node)) {
            return [
                'resource_type' => 'unknown',
                'resource_type_meta' => self::RESOURCE_TYPES['unknown'],
                'owner' => [
                    'type' => 'unknown',
                    'key' => '',
                    'path' => '',
                ],
                'node_id' => '',
                'node_type' => 'unknown',
                'label' => '',
            ];
        }

        $nodeType = self::normalizeKey((string)($node['type'] ?? 'unknown'));
        $resourceType = isset(self::RESOURCE_TYPES[$nodeType]) ? $nodeType : 'unknown';
        $meta = is_array($node['meta'] ?? null) ? $node['meta'] : [];
        $path = trim((string)($meta['path'] ?? ''));

        $ownerType = self::normalizeKey((string)($meta['owner_type'] ?? ''));
        $ownerKey = self::normalizeKey((string)($meta['owner_key'] ?? ''));
        if ($ownerType === '' || $ownerKey === '') {
            [$ownerType, $ownerKey] = self::inferOwnerFromPath($path, (string)($meta['source'] ?? ''));
        }

        return [
            'resource_type' => $resourceType,
            'resource_type_meta' => self::RESOURCE_TYPES[$resourceType],
            'owner' => [
                'type' => $ownerType !== '' ? $ownerType : 'unknown',
                'key' => $ownerKey,
                'path' => $path,
            ],
            'node_id' => trim((string)($node['id'] ?? '')),
            'node_type' => $nodeType,
            'label' => trim((string)($node['label'] ?? '')),
        ];
    }

    /**
     * @return array{0:string,1:string}
     */
    private static function inferOwnerFromPath(string $path, string $source): array
    {
        $normalizedPath = trim($path, '/');
        if ($normalizedPath === '') {
            return [self::normalizeKey($source), ''];
        }

        $parts = explode('/', $normalizedPath);
        if (count($parts) >= 2 && $parts[0] === 'apps') {
            if (isset($parts[1]) && strtolower($parts[1]) === 'generated' && isset($parts[2])) {
                return ['generated_app', self::normalizeKey((string)$parts[2])];
            }
            return ['app', self::normalizeKey((string)$parts[1])];
        }

        if (count($parts) >= 2 && $parts[0] === 'plugins') {
            return ['plugin', self::normalizeKey((string)$parts[1])];
        }

        return [self::normalizeKey($source), ''];
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

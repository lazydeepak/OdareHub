<?php
declare(strict_types=1);

namespace App\Core;

/**
 * ModuleRegistry — Module Architecture v1
 *
 * Central registry for all platform modules.
 * Modules are defined in app/Navigation/modules.php.
 *
 * Each entry carries:
 *   key, label_key, domain, type, owner_plugin, entry_url,
 *   visible_if, order, icon, status, is_placeholder, nav_groups, description
 *
 * Domains:  platform | manufacturing | extension | admin | developer
 * Types:    core | business_app | extension | admin | developer
 * Statuses: active | planned | placeholder
 *
 * Usage:
 *   ModuleRegistry::all()                     — all modules keyed by module key
 *   ModuleRegistry::byDomain('manufacturing') — all manufacturing modules
 *   ModuleRegistry::byKey('platform')         — single module or null
 *   ModuleRegistry::forNavGroup('master_data')— parent module for a nav group
 *   ModuleRegistry::getActive()               — active (non-placeholder) modules
 */
final class ModuleRegistry
{
    private static ?array $registry = null;

    /**
     * Returns all modules keyed by module key.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return self::load();
    }

    /**
     * Returns a single module by key, or null if not found.
     *
     * @return array<string, mixed>|null
     */
    public static function byKey(string $key): ?array
    {
        return self::load()[$key] ?? null;
    }

    /**
     * Returns all modules in the given domain.
     * Domains: platform, manufacturing, extension, admin, developer
     *
     * @return array<string, array<string, mixed>>
     */
    public static function byDomain(string $domain): array
    {
        return array_filter(
            self::load(),
            static fn(array $m): bool => ($m['domain'] ?? '') === $domain
        );
    }

    /**
     * Returns all modules of the given type.
     * Types: core, business_app, extension, admin, developer
     *
     * @return array<string, array<string, mixed>>
     */
    public static function byType(string $type): array
    {
        return array_filter(
            self::load(),
            static fn(array $m): bool => ($m['type'] ?? '') === $type
        );
    }

    /**
     * Returns all active (non-placeholder) modules, sorted by order.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getActive(): array
    {
        $active = array_filter(
            self::load(),
            static fn(array $m): bool => ($m['status'] ?? '') === 'active' && !($m['is_placeholder'] ?? false)
        );

        uasort($active, static fn(array $a, array $b): int => ((int)($a['order'] ?? 99)) <=> ((int)($b['order'] ?? 99)));

        return $active;
    }

    /**
     * Returns the parent module for a given nav group key, or null.
     * Useful for enriching sidebar groups with domain/owner metadata.
     *
     * @return array<string, mixed>|null
     */
    public static function forNavGroup(string $groupKey): ?array
    {
        foreach (self::load() as $module) {
            if (in_array($groupKey, (array)($module['nav_groups'] ?? []), true)) {
                return $module;
            }
        }

        return null;
    }

    /**
     * Returns all planned/placeholder modules — useful for admin tooling.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getPlanned(): array
    {
        return array_filter(
            self::load(),
            static fn(array $m): bool => (bool)($m['is_placeholder'] ?? false) || ($m['status'] ?? '') === 'planned'
        );
    }

    /**
     * Clears the internal cache. Call when module config has been hot-reloaded.
     */
    public static function flush(): void
    {
        self::$registry = null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function load(): array
    {
        if (self::$registry !== null) {
            return self::$registry;
        }

        $path = APP_ROOT . '/app/Navigation/modules.php';

        if (!is_file($path)) {
            self::$registry = [];
            return [];
        }

        $data = require $path;
        self::$registry = is_array($data) ? $data : [];

        return self::$registry;
    }
}

<?php
declare(strict_types=1);

namespace App\Core;

use Plugins\Base\Services\UserDashboardAssignmentService;

final class SidebarBuilder
{
    private const INTERNAL_ROOT_KEYS = ['admin.root', 'apps.root'];

    /**
     * @param array{loggedIn?:bool,user?:?array,isAdmin?:bool,canAccessBase?:bool,devToolsEnabled?:bool,currentPath?:string} $context
     * @return array{sections: array<int, array<string, mixed>>, hasSidebarItems: bool}
     */
    public static function build(array $context): array
    {
        $config = self::loadConfig();
        $loggedIn = (bool)($context['loggedIn'] ?? false);
        $user = is_array($context['user'] ?? null) ? $context['user'] : null;
        $isAdmin = (bool)($context['isAdmin'] ?? false);
        $canAccessBase = (bool)($context['canAccessBase'] ?? false);
        $devToolsEnabled = (bool)($context['devToolsEnabled'] ?? false);
        $currentPath = self::normalizePath((string)($context['currentPath'] ?? '/'));

        $role = strtolower(trim((string)($user['role'] ?? '')));
        $menuData = self::fetchMenuData();
        $dashboardRoute = '/ops/dashboard';
        $allowedModules = [];
        $hasModuleRestrictions = false;
        $authorityRole = 'app_user';
        $assignedApps = [];
        if ($loggedIn && class_exists(UserDashboardAssignmentService::class)) {
            try {
                $assignmentContext = UserDashboardAssignmentService::resolveUserContext($user);
                $dashboardType = UserDashboardAssignmentService::dashboardTypeForUser($user);
                $dashboardRoute = UserDashboardAssignmentService::routeForDashboardType($dashboardType);
                $allowedModules = UserDashboardAssignmentService::enabledModulesForUser($user);
                $hasModuleRestrictions = !empty($allowedModules);
                $authorityRole = (string)($assignmentContext['authority_role'] ?? 'app_user');
                $assignedApps = array_values((array)($assignmentContext['assigned_apps'] ?? []));
            } catch (\Throwable $e) {
                $dashboardRoute = '/ops/dashboard';
                $allowedModules = [];
                $hasModuleRestrictions = false;
                $authorityRole = 'app_user';
                $assignedApps = [];
            }
        }

        $visibilityContext = [
            'logged_in' => $loggedIn,
            'is_admin' => $isAdmin,
            'can_access_base' => $canAccessBase,
            'developer_tools' => $devToolsEnabled && ($isAdmin || self::hasAdminToolsAccess($user) || $canAccessBase),
            'role' => $role,
            'authority_role' => $authorityRole,
            'assigned_apps' => $assignedApps,
            'has_admin_tools_access' => self::hasAdminToolsAccess($user),
            'has_supervisor_access' => self::hasSupervisorAccess($user),
        ];

        $knownUrls = self::knownUrls($config);
        $groupsBySection = [];
        $moduleMap = self::buildModuleMap();
        $sidebarRegistry = self::loadSidebarRegistry();
        $groupConfigs = self::buildRegistryGroupConfigs($sidebarRegistry, $config);
        if ($groupConfigs === []) {
            $groupConfigs = array_values(array_filter((array)($config['groups'] ?? []), 'is_array'));
        }

        foreach ($groupConfigs as $groupConfig) {
            $group = self::buildGroup($groupConfig, $visibilityContext, $menuData, $knownUrls, $currentPath, $moduleMap, $dashboardRoute, $allowedModules, $hasModuleRestrictions, $sidebarRegistry);
            if ($group === null || empty($group['items'])) {
                continue;
            }

            $sectionKey = (string)($group['section_key'] ?? 'platform');
            $groupsBySection[$sectionKey][] = $group;
        }

        $sections = [];
        foreach ((array)($config['sections'] ?? []) as $sectionKey => $sectionConfig) {
            if (!is_array($sectionConfig)) {
                continue;
            }

            $groups = $groupsBySection[(string)$sectionKey] ?? [];
            if (empty($groups)) {
                continue;
            }

            usort($groups, static function (array $left, array $right): int {
                return ((int)($left['order'] ?? 100)) <=> ((int)($right['order'] ?? 100));
            });

            $sections[] = [
                'key' => (string)$sectionKey,
                'label' => trim((string)($sectionConfig['label'] ?? '')) !== ''
                    ? (string)$sectionConfig['label']
                    : t((string)($sectionConfig['label_key'] ?? 'nav.modules')),
                'order' => (int)($sectionConfig['order'] ?? 100),
                'groups' => $groups,
            ];
        }

        usort($sections, static function (array $left, array $right): int {
            return ((int)($left['order'] ?? 100)) <=> ((int)($right['order'] ?? 100));
        });

        $sections = self::dedupeSections($sections);
        $sections = self::appendCatchAllSection(
            $sections,
            $sidebarRegistry,
            $visibilityContext,
            $menuData,
            $currentPath,
            $dashboardRoute,
            $allowedModules,
            $hasModuleRestrictions
        );

        return [
            'sections' => $sections,
            'hasSidebarItems' => !empty($sections),
        ];
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $sidebarRegistry
     * @param array<string, mixed> $config
     * @return array<int, array<string, mixed>>
     */
    private static function buildRegistryGroupConfigs(array $sidebarRegistry, array $config): array
    {
        $sourceHints = self::sourceKeyHints($config);
        $groupBuckets = [];

        foreach ($sidebarRegistry as $sourceKey => $items) {
            $normalizedSourceKey = trim((string)$sourceKey);
            foreach ($items as $itemConfig) {
                if (!is_array($itemConfig)) {
                    continue;
                }

                $sectionLabel = self::registrySectionLabel($itemConfig, $normalizedSourceKey, $sourceHints);
                $groupLabel = self::registryGroupLabel($itemConfig, $normalizedSourceKey, $sectionLabel, $sourceHints);
                if ($sectionLabel === '' || $groupLabel === '') {
                    continue;
                }

                $sectionKey = self::sectionKeyFromLabel($sectionLabel, $config);
                $bucketKey = $sectionKey . '::' . self::slugify($groupLabel);
                $sourceHint = $sourceHints[$normalizedSourceKey] ?? null;
                $itemOrder = (int)($itemConfig['order'] ?? 100);
                $groupOrder = $sourceHint !== null
                    ? min((int)($sourceHint['order'] ?? $itemOrder), $itemOrder)
                    : $itemOrder;

                if (!isset($groupBuckets[$bucketKey])) {
                    $groupBuckets[$bucketKey] = [
                        'key' => 'registry_' . substr(md5($bucketKey), 0, 12),
                        'section' => $sectionKey,
                        'label' => $groupLabel,
                        'order' => $groupOrder,
                        'auto_open' => (bool)($sourceHint['auto_open'] ?? false),
                        'items' => [],
                    ];
                } else {
                    $groupBuckets[$bucketKey]['order'] = min((int)$groupBuckets[$bucketKey]['order'], $groupOrder);
                    $groupBuckets[$bucketKey]['auto_open'] = (bool)$groupBuckets[$bucketKey]['auto_open'] || (bool)($sourceHint['auto_open'] ?? false);
                }

                $groupBuckets[$bucketKey]['items'][] = $itemConfig;
            }
        }

        $groups = array_values($groupBuckets);
        usort($groups, static function (array $left, array $right): int {
            if (((int)($left['order'] ?? 100)) !== ((int)($right['order'] ?? 100))) {
                return ((int)($left['order'] ?? 100)) <=> ((int)($right['order'] ?? 100));
            }

            return strcasecmp((string)($left['label'] ?? ''), (string)($right['label'] ?? ''));
        });

        return $groups;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, array<string, mixed>>
     */
    private static function sourceKeyHints(array $config): array
    {
        $hints = [];
        foreach ((array)($config['groups'] ?? []) as $groupConfig) {
            if (!is_array($groupConfig)) {
                continue;
            }

            $sourceKeys = array_values(array_filter(array_map('strval', (array)($groupConfig['source_keys'] ?? []))));
            $singleSourceKey = trim((string)($groupConfig['source_key'] ?? ''));
            if ($singleSourceKey !== '') {
                $sourceKeys[] = $singleSourceKey;
            }

            foreach ($sourceKeys as $sourceKey) {
                $key = trim($sourceKey);
                if ($key === '') {
                    continue;
                }

                $hints[$key] = [
                    'section' => (string)($groupConfig['section'] ?? ''),
                    'label' => (string)($groupConfig['label'] ?? ''),
                    'order' => (int)($groupConfig['order'] ?? 100),
                    'auto_open' => (bool)($groupConfig['auto_open'] ?? false),
                ];
            }
        }

        return $hints;
    }

    /**
     * @param array<string, mixed> $itemConfig
     * @param array<string, array<string, mixed>> $sourceHints
     */
    private static function registrySectionLabel(array $itemConfig, string $sourceKey, array $sourceHints): string
    {
        $label = trim((string)($itemConfig['group'] ?? ''));
        if ($label !== '') {
            return $label;
        }

        $hint = $sourceHints[$sourceKey] ?? null;
        if ($hint !== null) {
            $hintSection = trim((string)($hint['section'] ?? ''));
            if ($hintSection !== '') {
                return self::sectionLabelFromKey($hintSection);
            }
        }

        $owner = strtolower(trim((string)($itemConfig['owner'] ?? '')));
        if ($owner === 'platform' || $owner === 'shell' || $owner === 'admin') {
            return 'Operations';
        }

        return 'Apps';
    }

    /**
     * @param array<string, mixed> $itemConfig
     * @param array<string, array<string, mixed>> $sourceHints
     */
    private static function registryGroupLabel(array $itemConfig, string $sourceKey, string $sectionLabel, array $sourceHints): string
    {
        $label = trim((string)($itemConfig['section'] ?? ''));
        if ($label !== '') {
            return $label;
        }

        $hint = $sourceHints[$sourceKey] ?? null;
        if ($hint !== null) {
            $hintLabel = trim((string)($hint['label'] ?? ''));
            if ($hintLabel !== '') {
                return $hintLabel;
            }
        }

        $module = trim((string)($itemConfig['module'] ?? ''));
        if ($module !== '') {
            return self::displayLabelFromToken($module);
        }

        $owner = trim((string)($itemConfig['owner'] ?? ''));
        if ($owner !== '') {
            return self::displayLabelFromToken($owner);
        }

        return $sectionLabel;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function sectionKeyFromLabel(string $sectionLabel, array $config): string
    {
        $normalizedTarget = strtolower(trim($sectionLabel));
        foreach ((array)($config['sections'] ?? []) as $sectionKey => $sectionConfig) {
            if (!is_array($sectionConfig)) {
                continue;
            }

            $candidateLabel = trim((string)($sectionConfig['label'] ?? ''));
            if ($candidateLabel !== '' && strtolower($candidateLabel) === $normalizedTarget) {
                return (string)$sectionKey;
            }

            if (strtolower((string)$sectionKey) === $normalizedTarget) {
                return (string)$sectionKey;
            }
        }

        return self::slugify($sectionLabel);
    }

    private static function sectionLabelFromKey(string $sectionKey): string
    {
        $label = str_replace(['_', '-'], ' ', trim($sectionKey));
        return ucwords($label);
    }

    private static function displayLabelFromToken(string $token): string
    {
        $label = trim(str_replace(['_', '-'], ' ', $token));
        return $label === '' ? '' : ucwords($label);
    }

    private static function slugify(string $value): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $value) ?? ''));
        return trim($slug, '_') ?: 'group';
    }

    /**
     * @param array<int, array<string, mixed>> $sections
     * @return array<int, array<string, mixed>>
     */
    private static function dedupeSections(array $sections): array
    {
        $seen = [];
        $dedupedSections = [];

        foreach ($sections as $section) {
            $groups = (array)($section['groups'] ?? []);
            $dedupedGroups = [];

            foreach ($groups as $group) {
                $items = (array)($group['items'] ?? []);
                $dedupedItems = [];

                foreach ($items as $item) {
                    if (!is_array($item)) {
                        continue;
                    }

                    $identity = self::itemIdentity($item);
                    if ($identity === '') {
                        continue;
                    }

                    if (isset($seen[$identity])) {
                        continue;
                    }

                    $seen[$identity] = true;
                    $dedupedItems[] = $item;
                }

                if (empty($dedupedItems)) {
                    continue;
                }

                $group['items'] = $dedupedItems;
                $group['is_active'] = self::groupHasActiveItem($dedupedItems);
                $group['is_open'] = $group['is_active'] || !empty($group['is_open']);
                $group['overflow'] = self::finalizeGroupOverflow($group, $dedupedItems);
                $dedupedGroups[] = $group;
            }

            if (empty($dedupedGroups)) {
                continue;
            }

            $section['groups'] = $dedupedGroups;
            $dedupedSections[] = $section;
        }

        return $dedupedSections;
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function itemIdentity(array $item): string
    {
        $url = trim((string)($item['url'] ?? ''));
        if ($url !== '') {
            return 'url:' . self::normalizePath($url);
        }

        $label = trim((string)($item['label'] ?? ''));
        if ($label !== '') {
            return 'label:' . strtolower($label);
        }

        return '';
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private static function groupHasActiveItem(array $items): bool
    {
        foreach ($items as $item) {
            if (!empty($item['is_active'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $sections
     * @param array<string, array<int, array<string, mixed>>> $sidebarRegistry
     * @param array<string, mixed> $visibilityContext
     * @param array{menuByKey: array<string, array<string, mixed>>, menuByUrl: array<string, array<string, mixed>>, dynamicExtensionItems: array<int, array<string, mixed>>} $menuData
     * @param array<int,string> $allowedModules
     * @return array<int, array<string, mixed>>
     */
    private static function appendCatchAllSection(
        array $sections,
        array $sidebarRegistry,
        array $visibilityContext,
        array $menuData,
        string $currentPath,
        string $dashboardRoute,
        array $allowedModules,
        bool $hasModuleRestrictions
    ): array {
        $seen = self::collectSeenItemIdentities($sections);
        $groupBuckets = [];
        $groupOrder = [];

        foreach ($sidebarRegistry as $items) {
            foreach ((array)$items as $itemConfig) {
                if (!is_array($itemConfig)) {
                    continue;
                }

                $item = self::buildItem(
                    $itemConfig,
                    $visibilityContext,
                    $menuData,
                    $currentPath,
                    $dashboardRoute,
                    $allowedModules,
                    $hasModuleRestrictions
                );
                if ($item === null) {
                    continue;
                }

                $url = trim((string)($item['url'] ?? ''));
                if ($url === '') {
                    continue;
                }

                $identity = self::itemIdentity($item);
                if ($identity === '' || isset($seen[$identity])) {
                    continue;
                }

                $seen[$identity] = true;
                $groupMeta = self::catchAllGroupMeta($itemConfig, $item);
                $groupKey = (string)$groupMeta['key'];
                if ($groupKey === '') {
                    continue;
                }

                $groupBuckets[$groupKey][] = $item;
                if (!isset($groupOrder[$groupKey])) {
                    $groupOrder[$groupKey] = $groupMeta;
                }
            }
        }

        if ($groupBuckets === []) {
            return $sections;
        }

        $groups = [];
        foreach ($groupBuckets as $groupKey => $items) {
            usort($items, static function (array $left, array $right): int {
                $priorityCompare = ((int)($right['priority'] ?? 0)) <=> ((int)($left['priority'] ?? 0));
                if ($priorityCompare !== 0) {
                    return $priorityCompare;
                }

                $orderCompare = ((int)($left['order'] ?? 100)) <=> ((int)($right['order'] ?? 100));
                if ($orderCompare !== 0) {
                    return $orderCompare;
                }

                return strcmp((string)($left['label'] ?? ''), (string)($right['label'] ?? ''));
            });

            $meta = $groupOrder[$groupKey];
            $groups[] = [
                'key' => $groupKey,
                'section_key' => 'more',
                'domain' => (string)($meta['domain'] ?? ''),
                'module_key' => '',
                'type' => 'catch_all',
                'label' => (string)($meta['label'] ?? 'Additional Links'),
                'order' => (int)($meta['order'] ?? 999),
                'is_open' => self::groupHasActiveItem($items),
                'is_active' => self::groupHasActiveItem($items),
                'overflow' => self::prepareGroupOverflowMetadata(false, 0, $groupKey, $items),
                'items' => $items,
            ];
        }

        usort($groups, static function (array $left, array $right): int {
            $orderCompare = ((int)($left['order'] ?? 999)) <=> ((int)($right['order'] ?? 999));
            if ($orderCompare !== 0) {
                return $orderCompare;
            }

            return strcmp((string)($left['label'] ?? ''), (string)($right['label'] ?? ''));
        });

        $sections[] = [
            'key' => 'more',
            'label' => 'More',
            'order' => 999,
            'groups' => $groups,
        ];

        usort($sections, static function (array $left, array $right): int {
            return ((int)($left['order'] ?? 100)) <=> ((int)($right['order'] ?? 100));
        });

        return $sections;
    }

    /**
     * @param array<int, array<string, mixed>> $sections
     * @return array<string, bool>
     */
    private static function collectSeenItemIdentities(array $sections): array
    {
        $seen = [];
        foreach ($sections as $section) {
            foreach ((array)($section['groups'] ?? []) as $group) {
                foreach ((array)($group['items'] ?? []) as $item) {
                    if (!is_array($item)) {
                        continue;
                    }

                    $identity = self::itemIdentity($item);
                    if ($identity !== '') {
                        $seen[$identity] = true;
                    }
                }
            }
        }

        return $seen;
    }

    /**
     * @param array<string, mixed> $itemConfig
     * @param array<string, mixed> $item
     * @return array{key:string,label:string,order:int,domain:string}
     */
    private static function catchAllGroupMeta(array $itemConfig, array $item): array
    {
        $section = trim((string)($itemConfig['section'] ?? ''));
        $module = trim((string)($itemConfig['module'] ?? ''));
        $owner = trim((string)($itemConfig['owner'] ?? ''));
        $domain = self::inferCatchAllDomain($itemConfig, $item);

        $label = $section;
        if ($label === '') {
            $label = $module;
        }
        if ($label === '') {
            $label = $owner;
        }
        if ($label === '') {
            $label = $domain !== '' ? ucfirst($domain) : 'Additional Links';
        }

        $normalizedKey = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $label) ?? 'additional_links', '_'));
        if ($normalizedKey === '') {
            $normalizedKey = 'additional_links';
        }

        return [
            'key' => 'more_' . $normalizedKey,
            'label' => $label,
            'order' => (int)($itemConfig['order'] ?? 999),
            'domain' => $domain,
        ];
    }

    /**
     * @param array<string, mixed> $itemConfig
     * @param array<string, mixed> $item
     */
    private static function inferCatchAllDomain(array $itemConfig, array $item): string
    {
        $url = strtolower(trim((string)($item['url'] ?? ($itemConfig['url'] ?? ''))));
        if (preg_match('#^/apps/([^/]+)#', $url, $matches) === 1) {
            return strtolower(trim((string)($matches[1] ?? '')));
        }

        $owner = strtolower(trim((string)($itemConfig['owner'] ?? '')));
        if (in_array($owner, ['manufacturing', 'sbaio', 'platform'], true)) {
            return $owner;
        }

        return '';
    }

    private static function loadConfig(): array
    {
        $path = APP_ROOT . '/app/Navigation/sidebar.php';
        if (!is_file($path)) {
            return ['sections' => [], 'groups' => []];
        }

        $config = require $path;
        return is_array($config) ? $config : ['sections' => [], 'groups' => []];
    }

    /**
     * @return array{menuByKey: array<string, array<string, mixed>>, menuByUrl: array<string, array<string, mixed>>, dynamicExtensionItems: array<int, array<string, mixed>>}
     */
    private static function fetchMenuData(): array
    {
        $menuByKey = [];
        $menuByUrl = [];
        $dynamicExtensionItems = [];

        try {
            $rows = DB::fetchAll('SELECT menu_key,label,url,parent_key,display_order FROM menus ORDER BY COALESCE(parent_key,\'\'), display_order ASC, label ASC');
        } catch (\Throwable $e) {
            return [
                'menuByKey' => [],
                'menuByUrl' => [],
                'dynamicExtensionItems' => [],
            ];
        }

        $childrenByParent = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $menuKey = trim((string)($row['menu_key'] ?? ''));
            $url = trim((string)($row['url'] ?? ''));
            $parent = trim((string)($row['parent_key'] ?? ''));

            if ($menuKey !== '') {
                $menuByKey[$menuKey] = $row;
            }
            if ($url !== '' && str_starts_with($url, '/')) {
                $menuByUrl[$url] = $row;
            }
            if ($parent !== '') {
                $childrenByParent[$parent][] = $row;
            }
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $parent = trim((string)($row['parent_key'] ?? ''));
            if ($parent !== '') {
                continue;
            }

            $rootKey = trim((string)($row['menu_key'] ?? ''));
            if ($rootKey === '' || in_array($rootKey, self::INTERNAL_ROOT_KEYS, true)) {
                continue;
            }

            foreach ((array)($childrenByParent[$rootKey] ?? []) as $child) {
                $url = trim((string)($child['url'] ?? ''));
                if ($url === '' || !str_starts_with($url, '/') || str_starts_with($url, '/admin')) {
                    continue;
                }

                $dynamicExtensionItems[] = [
                    'key' => trim((string)($child['menu_key'] ?? '')),
                    'label' => trim((string)($child['label'] ?? $url)),
                    'url' => $url,
                    'order' => (int)($child['display_order'] ?? 999),
                ];
            }
        }

        return [
            'menuByKey' => $menuByKey,
            'menuByUrl' => $menuByUrl,
            'dynamicExtensionItems' => $dynamicExtensionItems,
        ];
    }

    private static function hasAdminToolsAccess(?array $user): bool
    {
        $authorityRole = strtolower(trim((string)($user['authority_role'] ?? '')));
        if ($authorityRole !== '') {
            return $authorityRole === 'platform_admin';
        }

        if (function_exists('base_can_access_admin_tools')) {
            return base_can_access_admin_tools($user);
        }

        $role = strtolower(trim((string)($user['role'] ?? '')));
        return in_array($role, ['platform_admin', 'admin', 'it admin', 'it-admin', 'it_admin'], true);
    }

    private static function hasSupervisorAccess(?array $user): bool
    {
        if (function_exists('base_can_access_supervisor_cockpit')) {
            return base_can_access_supervisor_cockpit($user);
        }

        $role = strtolower(trim((string)($user['role'] ?? '')));
        return in_array($role, ['admin', 'manager', 'supervisor', 'gm', 'general manager', 'general_manager', 'generalmanager'], true);
    }

    /**
     * @param array<string, mixed> $groupConfig
     * @param array<string, mixed> $visibilityContext
     * @param array{menuByKey: array<string, array<string, mixed>>, menuByUrl: array<string, array<string, mixed>>, dynamicExtensionItems: array<int, array<string, mixed>>} $menuData
     * @param array<string, bool> $knownUrls
     * @param array<string, array<string, mixed>> $moduleMap group-key → module
     * @param array<int, string> $allowedModules
     * @param array<string, array<int, array<string, mixed>>> $sidebarRegistry
     */
    private static function buildGroup(
        array $groupConfig,
        array $visibilityContext,
        array $menuData,
        array $knownUrls,
        string $currentPath,
        array $moduleMap,
        string $dashboardRoute,
        array $allowedModules,
        bool $hasModuleRestrictions,
        array $sidebarRegistry
    ): ?array
    {

        $groupKey = (string)($groupConfig['key'] ?? '');
        $module = $moduleMap[$groupKey] ?? null;
        $groupDomain = strtolower(trim((string)($groupConfig['domain'] ?? '')));
        if (!self::canAccessSidebarDomain($groupDomain, $visibilityContext)) {
            return null;
        }
        if ($hasModuleRestrictions && !self::isModuleAllowed($module, $allowedModules)) {
            return null;
        }

        $items = [];
        foreach (self::resolveGroupItemConfigs($groupConfig, $sidebarRegistry) as $itemConfig) {
            if (!is_array($itemConfig)) {
                continue;
            }

            $item = self::buildItem($itemConfig, $visibilityContext, $menuData, $currentPath, $dashboardRoute, $allowedModules, $hasModuleRestrictions);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        if (!empty($groupConfig['allow_dynamic_menu_items'])) {
            foreach ((array)($menuData['dynamicExtensionItems'] ?? []) as $dynamicItem) {
                $url = (string)($dynamicItem['url'] ?? '');
                if ($url === '' || isset($knownUrls[$url])) {
                    continue;
                }

                $items[] = [
                    'key' => (string)($dynamicItem['key'] ?? md5($url)),
                    'label' => (string)($dynamicItem['label'] ?? $url),
                    'url' => $url,
                    'type' => (string)($groupConfig['type'] ?? 'extension'),
                    'style' => ['is_secondary' => true],
                    'active_rules' => [
                        'exact' => [$url],
                        'prefix' => [rtrim($url, '/') . '/'],
                    ],
                    'is_active' => self::isItemActive([
                        'exact' => [$url],
                        'prefix' => [rtrim($url, '/') . '/'],
                    ], $currentPath),
                    'order' => (int)($dynamicItem['order'] ?? 999),
                ];
            }
        }

        if (empty($items)) {
            return null;
        }

        usort($items, static function (array $left, array $right): int {
            return ((int)($left['order'] ?? 100)) <=> ((int)($right['order'] ?? 100));
        });

        $groupActive = false;
        foreach ($items as $item) {
            if (!empty($item['is_active'])) {
                $groupActive = true;
                break;
            }
        }

        return [
            'key'         => $groupKey,
            'section_key' => (string)($groupConfig['section'] ?? 'platform'),
            'domain'      => (string)($groupConfig['domain'] ?? ($module['domain'] ?? 'platform')),
            'module_key'  => (string)($module['key'] ?? ''),
            'type'        => (string)($groupConfig['type'] ?? 'core'),
            'label'       => trim((string)($groupConfig['label'] ?? '')) !== ''
                ? (string)$groupConfig['label']
                : t((string)($groupConfig['label_key'] ?? 'nav.modules')),
            'order'       => (int)($groupConfig['order'] ?? 100),
            'is_open'     => $groupActive || !empty($groupConfig['auto_open']),
            'is_active'   => $groupActive,
            'overflow'    => self::prepareGroupOverflowMetadata(
                (bool)($groupConfig['collapsible_overflow'] ?? false),
                (int)($groupConfig['overflow_limit'] ?? 0),
                $groupKey,
                $items
            ),
            'items'       => $items,
        ];
    }

    /**
     * @param array<string, mixed> $group
     * @param array<int, array<string, mixed>> $items
     * @return array<string, mixed>
     */
    private static function finalizeGroupOverflow(array $group, array $items): array
    {
        $overflow = (array)($group['overflow'] ?? []);
        return self::prepareGroupOverflowMetadata(
            (bool)($overflow['requested'] ?? false),
            (int)($overflow['limit'] ?? 0),
            (string)($group['key'] ?? ''),
            $items
        );
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, mixed>
     */
    private static function prepareGroupOverflowMetadata(bool $requested, int $limit, string $groupKey, array $items): array
    {
        $limit = max(0, $limit);
        $eligibleItems = array_values(array_filter($items, static function (array $item): bool {
            return trim((string)($item['render_type'] ?? '')) === '' && trim((string)($item['url'] ?? '')) !== '';
        }));

        if (!$requested || $limit <= 0 || count($eligibleItems) <= $limit) {
            return [
                'requested' => $requested,
                'enabled' => false,
                'limit' => $limit,
                'storage_key' => $groupKey,
                'default_visible_keys' => array_values(array_map('strval', array_column($eligibleItems, 'key'))),
            ];
        }

        return [
            'requested' => $requested,
            'enabled' => true,
            'limit' => $limit,
            'storage_key' => $groupKey,
            'default_visible_keys' => self::selectCompactItemKeys($eligibleItems, $limit),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, string>
     */
    private static function selectCompactItemKeys(array $items, int $limit): array
    {
        $mustShow = [];
        $scored = [];

        foreach ($items as $index => $item) {
            $key = trim((string)($item['key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $score = ((int)($item['priority'] ?? 0) * 100)
                + (!empty($item['is_active']) ? 100000 : 0)
                + (!empty($item['always_visible']) ? 50000 : 0)
                + max(0, 1000 - (int)($item['order'] ?? 1000));

            $scored[] = [
                'key' => $key,
                'score' => $score,
                'order' => (int)($item['order'] ?? (($index + 1) * 10)),
                'index' => $index,
            ];

            if (!empty($item['is_active']) || !empty($item['always_visible'])) {
                $mustShow[$key] = true;
            }
        }

        usort($scored, static function (array $left, array $right): int {
            if ($left['score'] !== $right['score']) {
                return $right['score'] <=> $left['score'];
            }

            if ($left['order'] !== $right['order']) {
                return $left['order'] <=> $right['order'];
            }

            return $left['index'] <=> $right['index'];
        });

        $selected = $mustShow;
        foreach ($scored as $row) {
            if (count($selected) >= $limit && count($selected) >= count($mustShow)) {
                break;
            }
            $selected[$row['key']] = true;
        }

        if ($selected === [] && isset($scored[0]['key'])) {
            $selected[$scored[0]['key']] = true;
        }

        return array_keys($selected);
    }

    /**
     * @param array<string, mixed> $groupConfig
     * @param array<string, array<int, array<string, mixed>>> $sidebarRegistry
     * @return array<int, array<string, mixed>>
     */
    private static function resolveGroupItemConfigs(array $groupConfig, array $sidebarRegistry): array
    {
        $resolved = [];

        foreach ((array)($groupConfig['items'] ?? []) as $itemConfig) {
            if (is_array($itemConfig)) {
                $resolved[] = $itemConfig;
            }
        }

        $sourceKeys = (array)($groupConfig['source_keys'] ?? []);
        $singleSourceKey = trim((string)($groupConfig['source_key'] ?? ''));
        if ($singleSourceKey !== '') {
            $sourceKeys[] = $singleSourceKey;
        }

        foreach ($sourceKeys as $sourceKey) {
            $key = trim((string)$sourceKey);
            if ($key === '' || empty($sidebarRegistry[$key])) {
                continue;
            }

            foreach ((array)$sidebarRegistry[$key] as $registeredItem) {
                if (is_array($registeredItem)) {
                    $resolved[] = $registeredItem;
                }
            }
        }

        return $resolved;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private static function loadSidebarRegistry(): array
    {
        $registry = [];
        foreach (self::legacySidebarSourceFiles() as $path) {
            $payload = require $path;
            if (!is_array($payload)) {
                continue;
            }

            foreach ($payload as $groupKey => $items) {
                $key = trim((string)$groupKey);
                if ($key === '') {
                    continue;
                }

                foreach ((array)$items as $itemConfig) {
                    if (!is_array($itemConfig)) {
                        continue;
                    }

                    $registry[$key][] = $itemConfig;
                }
            }
        }

        foreach (self::navigationContractFiles() as $contractFile) {
            $payload = require $contractFile;
            if (!is_array($payload)) {
                continue;
            }

            foreach (self::normalizeNavigationContractPayload($payload) as $groupKey => $items) {
                foreach ($items as $itemConfig) {
                    $registry[$groupKey][] = $itemConfig;
                }
            }
        }

        $registry = self::autoDiscoverRouteItems($registry);

        return $registry;
    }

    /**
     * Auto-discover registered GET routes and attach them as children of the
     * nearest declared parent nav item by URL-prefix. Opt-out by pattern and by
     * requiring a declared parent so orphan/internal routes do not pollute nav.
     *
     * @param array<string, array<int, array<string, mixed>>> $registry
     * @return array<string, array<int, array<string, mixed>>>
     */
    private static function autoDiscoverRouteItems(array $registry): array
    {
        if (!class_exists(RouteRuntimeAuthority::class)) {
            return $registry;
        }

        // Build URL -> (source_key, parent_item) from existing registry entries.
        $declaredByUrl = [];
        foreach ($registry as $groupKey => $items) {
            foreach ((array)$items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $url = self::normalizePath((string)($item['url'] ?? ''));
                if ($url === '' || isset($declaredByUrl[$url])) {
                    continue;
                }
                $declaredByUrl[$url] = ['source_key' => $groupKey, 'item' => $item];
            }
        }

        // Include URLs from the menus table so DB-seeded items also dedupe.
        try {
            $menuData = self::fetchMenuData();
            foreach ((array)($menuData['menuByUrl'] ?? []) as $url => $_row) {
                $normalized = self::normalizePath((string)$url);
                if ($normalized !== '' && !isset($declaredByUrl[$normalized])) {
                    $declaredByUrl[$normalized] = ['source_key' => '', 'item' => []];
                }
            }
        } catch (\Throwable $e) {
            // Best-effort; dedupe only against registry if DB unavailable.
        }

        try {
            $loadedRoutes = RouteRuntimeAuthority::loadedRoutes('GET');
        } catch (\Throwable $e) {
            return $registry;
        }

        // Deny patterns: login/auth, APIs, callback endpoints, form/action leaves.
        $denyPatterns = [
            '#^/(login|logout|register|2fa|forgot|password-reset|password|reset|health|healthz|ok|ping)(/.*)?$#i',
            '#^/api(/|$)#i',
            '#^/ajax(/|$)#i',
            '#^/webhooks?(/|$)#i',
            // Mutation / action leaves (callback endpoints reachable via GET).
            '#/(save|delete|update|action|export|import|upload|download|callback|reset|apply|repair|install|uninstall|activate|deactivate|purge|bulk|toggle|duplicate|clone|run|preview|test|send|finalize|submit|cancel|confirm|ack|retry|rollback|mark-[a-z-]+)$#i',
            // Form / detail leaves that are not meaningful sidebar destinations.
            '#/(add|edit|new|create|remove|destroy|detail|view|show|360|print|print-[a-z0-9-]+|pdf|csv|xlsx|download-[a-z0-9-]+|print-pdf|template)$#i',
        ];

        // Sort routes so shorter (parent) URLs are processed before longer children,
        // allowing newly added auto items to anchor even deeper descendants.
        usort($loadedRoutes, static function (string $a, string $b): int {
            $lenCmp = strlen($a) <=> strlen($b);
            return $lenCmp !== 0 ? $lenCmp : strcmp($a, $b);
        });

        foreach ($loadedRoutes as $url) {
            $url = self::normalizePath((string)$url);
            if ($url === '' || $url === '/' || isset($declaredByUrl[$url])) {
                continue;
            }
            // Skip parametric routes defensively.
            if (str_contains($url, '{') || str_contains($url, '<') || str_contains($url, '*')) {
                continue;
            }
            $denied = false;
            foreach ($denyPatterns as $pat) {
                if (preg_match($pat, $url) === 1) {
                    $denied = true;
                    break;
                }
            }
            if ($denied) {
                continue;
            }

            // Locate nearest declared parent by longest URL-prefix.
            $parent = null;
            $probe = $url;
            while (($slash = strrpos($probe, '/')) !== false && $slash > 0) {
                $probe = substr($probe, 0, $slash);
                if (isset($declaredByUrl[$probe]) && !empty($declaredByUrl[$probe]['item'])) {
                    $parent = $declaredByUrl[$probe];
                    break;
                }
            }
            if ($parent === null) {
                continue; // No anchor -> skip to keep nav focused.
            }

            $parentItem = $parent['item'];
            $sourceKey = (string)$parent['source_key'];
            if ($sourceKey === '') {
                continue;
            }

            $leaf = (string)substr($url, (int)strrpos($url, '/') + 1);
            $label = ucwords(trim(str_replace(['-', '_'], ' ', $leaf)));
            if ($label === '') {
                $label = $leaf;
            }
            $parentLabel = trim((string)($parentItem['label'] ?? ''));
            if ($parentLabel !== '' && $parentLabel !== $label && stripos($label, $parentLabel) === false) {
                $label = $parentLabel . ' · ' . $label;
            }

            $keySlug = preg_replace('#[^a-z0-9]+#i', '_', trim($url, '/')) ?: 'root';
            $autoItem = [
                'source_key' => $sourceKey,
                'key' => 'auto.' . strtolower((string)$keySlug),
                'label' => $label,
                'url' => $url,
                'group' => (string)($parentItem['group'] ?? ''),
                'section' => (string)($parentItem['section'] ?? ''),
                'module' => (string)($parentItem['module'] ?? ''),
                'module_token' => (string)($parentItem['module_token'] ?? ''),
                'owner' => (string)($parentItem['owner'] ?? ''),
                'visible_if' => (string)($parentItem['visible_if'] ?? 'logged_in'),
                'order' => ((int)($parentItem['order'] ?? 100)) + 5,
                'priority' => max(1, ((int)($parentItem['priority'] ?? 50)) - 20),
                'style' => (array)($parentItem['style'] ?? []),
                'app_visibility' => (array)($parentItem['app_visibility'] ?? []),
                'active_patterns' => ['exact' => [$url], 'prefix' => [$url . '/']],
                '_auto_discovered' => true,
            ];

            $registry[$sourceKey][] = $autoItem;
            $declaredByUrl[$url] = ['source_key' => $sourceKey, 'item' => $autoItem];
        }

        return $registry;
    }

    /**
     * @return array<int, string>
     */
    private static function legacySidebarSourceFiles(): array
    {
        $files = glob(APP_ROOT . '/app/Navigation/sidebar_sources/*.php') ?: [];
        sort($files, SORT_STRING);
        return array_values(array_filter(array_map('strval', $files), static fn(string $p): bool => is_file($p)));
    }

    /**
     * @return array<int, string>
     */
    private static function navigationContractFiles(): array
    {
        $moduleFiles = glob(APP_ROOT . '/apps/*/modules/*/navigation.php') ?: [];
        $appFiles = glob(APP_ROOT . '/apps/*/navigation.php') ?: [];
        $pluginFiles = glob(APP_ROOT . '/plugins/*/navigation.php') ?: [];
        return self::uniqueExistingFiles(array_merge($appFiles, $moduleFiles, $pluginFiles));
    }

    /**
     * @param array<int,string> $files
     * @return array<int,string>
     */
    private static function uniqueExistingFiles(array $files): array
    {
        $unique = [];
        foreach ($files as $file) {
            $path = trim((string)$file);
            if ($path === '' || !is_file($path)) {
                continue;
            }

            $resolved = realpath($path);
            $key = $resolved !== false ? $resolved : $path;
            $unique[$key] = $path;
        }

        $paths = array_values($unique);
        sort($paths, SORT_STRING);
        return $paths;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, array<int, array<string, mixed>>>
     */
    private static function normalizeNavigationContractPayload(array $payload): array
    {
        $registry = [];
        $items = (array)($payload['items'] ?? []);
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $sourceKey = trim((string)($item['source_key'] ?? ''));
            if ($sourceKey === '') {
                continue;
            }

            $registry[$sourceKey][] = [
                'key' => (string)($item['key'] ?? ''),
                'label' => (string)($item['label'] ?? ''),
                'label_key' => (string)($item['label_key'] ?? ''),
                'url' => (string)($item['url'] ?? ''),
                'canonical_target' => (string)($item['canonical_target'] ?? ''),
                'alias_target' => (string)($item['alias_target'] ?? ''),
                'menu_key' => (string)($item['menu_key'] ?? ''),
                'source_url' => (string)($item['source_url'] ?? ''),
                'route_fallback_file' => (string)($item['route_fallback_file'] ?? ''),
                'visible_if' => (string)($item['visible_if'] ?? 'always'),
                'app_visibility' => (array)($item['app_visibility'] ?? []),
                'nav_visible' => array_key_exists('nav_visible', $item) ? (bool)$item['nav_visible'] : true,
                'order' => (int)($item['order'] ?? 100),
                'icon' => (string)($item['icon'] ?? ''),
                'active_patterns' => (array)($item['active_patterns'] ?? []),
                'runtime_fallback_urls' => array_values(array_map('strval', (array)($item['runtime_fallback_urls'] ?? []))),
                'style' => (array)($item['style'] ?? []),
                'priority' => (int)($item['priority'] ?? 0),
                'always_visible' => array_key_exists('always_visible', $item) ? (bool)$item['always_visible'] : null,
                'usage_weight' => (float)($item['usage_weight'] ?? 1),
                'usage_key' => (string)($item['usage_key'] ?? ''),
                'feature_key' => (string)($item['feature_key'] ?? ''),
                'owner' => (string)($item['owner'] ?? ''),
                'group' => (string)($item['group'] ?? ''),
                'section' => (string)($item['section'] ?? ''),
                'module' => (string)($item['module'] ?? ''),
                'module_token' => (string)($item['module_token'] ?? ''),
                'type' => (string)($item['type'] ?? ''),
                'source_type' => 'navigation_contract',
            ];
        }

        return $registry;
    }

    /**
     * @param array<string, mixed> $visibilityContext
     */
    private static function canAccessSidebarDomain(string $domain, array $visibilityContext): bool
    {
        $authorityRole = strtolower(trim((string)($visibilityContext['authority_role'] ?? 'app_user')));
        if ($authorityRole === 'platform_admin') {
            return true;
        }

        if (in_array($domain, ['platform', 'admin'], true)) {
            return false;
        }

        $assignedApps = array_values(array_map('strval', (array)($visibilityContext['assigned_apps'] ?? [])));
        if (empty($assignedApps) || $domain === '') {
            return true;
        }

        return in_array($domain, $assignedApps, true);
    }

    /**
     * @param array<string, mixed> $itemConfig
     * @param array<string, mixed> $visibilityContext
     * @param array{menuByKey: array<string, array<string, mixed>>, menuByUrl: array<string, array<string, mixed>>, dynamicExtensionItems: array<int, array<string, mixed>>} $menuData
     * @param array<int, string> $allowedModules
     */
    private static function buildItem(
        array $itemConfig,
        array $visibilityContext,
        array $menuData,
        string $currentPath,
        string $dashboardRoute,
        array $allowedModules,
        bool $hasModuleRestrictions
    ): ?array
    {
        $renderType = trim((string)($itemConfig['render_type'] ?? ''));

        if (array_key_exists('nav_visible', $itemConfig) && !(bool)$itemConfig['nav_visible']) {
            return null;
        }

        if (!self::isVisible((string)($itemConfig['visible_if'] ?? 'always'), $visibilityContext)) {
            return null;
        }

        if ($hasModuleRestrictions && !self::isNavItemAllowedByModule($itemConfig, $allowedModules)) {
            return null;
        }

        if ($renderType !== '') {
            $labelKey = trim((string)($itemConfig['label_key'] ?? ''));
            $label = $labelKey !== '' ? t($labelKey) : trim((string)($itemConfig['label'] ?? (string)($itemConfig['key'] ?? '')));
            $priority = self::resolveItemPriority($itemConfig, $renderType);
            $alwaysVisible = self::resolveItemAlwaysVisible($itemConfig, $renderType);
            $usageWeight = self::resolveItemUsageWeight($itemConfig);
            $usageKey = self::resolveItemUsageKey($itemConfig, '');

            return [
                'key' => (string)($itemConfig['key'] ?? md5($renderType . ':' . $label)),
                'label' => $label,
                'url' => '',
                'icon' => (string)($itemConfig['icon'] ?? ''),
                'owner' => (string)($itemConfig['owner'] ?? ''),
                'feature_key' => (string)($itemConfig['feature_key'] ?? ''),
                'group' => (string)($itemConfig['group'] ?? ''),
                'section' => (string)($itemConfig['section'] ?? ''),
                'module' => (string)($itemConfig['module'] ?? ''),
                'source_type' => (string)($itemConfig['source_type'] ?? 'sidebar_config'),
                'canonical_target' => (string)($itemConfig['canonical_target'] ?? ''),
                'alias_target' => (string)($itemConfig['alias_target'] ?? ''),
                'type' => (string)($itemConfig['type'] ?? ''),
                'render_type' => $renderType,
                'search_keywords' => self::normalizeSearchKeywords($itemConfig),
                'style' => (array)($itemConfig['style'] ?? []),
                'priority' => $priority,
                'always_visible' => $alwaysVisible,
                'usage_weight' => $usageWeight,
                'usage_key' => $usageKey,
                'active_rules' => [
                    'exact' => [],
                    'prefix' => [],
                    'hash' => '',
                ],
                'is_active' => false,
                'order' => (int)($itemConfig['order'] ?? 100),
            ];
        }

        $resolvedUrl = self::resolveItemUrl($itemConfig, $menuData);
        if ((string)($itemConfig['key'] ?? '') === 'role_dashboard') {
            $resolvedUrl = $dashboardRoute;
        }

        $resolvedUrl = (string)(RouteRuntimeAuthority::resolveNavigableUrl(
            $resolvedUrl,
            array_values(array_map('strval', (array)($itemConfig['runtime_fallback_urls'] ?? []))),
            'GET'
        ) ?? '');

        if ($resolvedUrl === '') {
            return null;
        }

        $activeRules = self::normalizeActiveRules((array)($itemConfig['active_patterns'] ?? []), $resolvedUrl);
        $labelKey = trim((string)($itemConfig['label_key'] ?? ''));
        $label = $labelKey !== '' ? t($labelKey) : trim((string)($itemConfig['label'] ?? $resolvedUrl));
        $priority = self::resolveItemPriority($itemConfig, $renderType);
        $alwaysVisible = self::resolveItemAlwaysVisible($itemConfig, $renderType);
        $usageWeight = self::resolveItemUsageWeight($itemConfig);
        $usageKey = self::resolveItemUsageKey($itemConfig, $resolvedUrl);

        return [
            'key' => (string)($itemConfig['key'] ?? md5($resolvedUrl)),
            'label' => $label,
            'url' => $resolvedUrl,
            'icon' => (string)($itemConfig['icon'] ?? ''),
            'owner' => (string)($itemConfig['owner'] ?? ''),
            'feature_key' => (string)($itemConfig['feature_key'] ?? ''),
            'group' => (string)($itemConfig['group'] ?? ''),
            'section' => (string)($itemConfig['section'] ?? ''),
            'module' => (string)($itemConfig['module'] ?? ''),
            'source_type' => (string)($itemConfig['source_type'] ?? 'sidebar_config'),
            'canonical_target' => (string)($itemConfig['canonical_target'] ?? ''),
            'alias_target' => (string)($itemConfig['alias_target'] ?? ''),
            'type' => (string)($itemConfig['type'] ?? ''),
            'search_keywords' => self::normalizeSearchKeywords($itemConfig),
            'style' => (array)($itemConfig['style'] ?? []),
            'priority' => $priority,
            'always_visible' => $alwaysVisible,
            'usage_weight' => $usageWeight,
            'usage_key' => $usageKey,
            'active_rules' => $activeRules,
            'is_active' => self::isItemActive($activeRules, $currentPath),
            'order' => (int)($itemConfig['order'] ?? 100),
        ];
    }

    /**
     * @param array<string, mixed> $itemConfig
     */
    private static function resolveItemPriority(array $itemConfig, string $renderType): int
    {
        if (array_key_exists('priority', $itemConfig) && $itemConfig['priority'] !== null) {
            return (int)$itemConfig['priority'];
        }

        $style = (array)($itemConfig['style'] ?? []);
        $key = strtolower(trim((string)($itemConfig['key'] ?? '')));
        $url = strtolower(trim((string)($itemConfig['url'] ?? '')));

        if ($renderType !== '') {
            return 100;
        }
        if (!empty($style['is_dashboard'])) {
            return 95;
        }
        if (!empty($style['is_action'])) {
            return 90;
        }
        if (str_contains($key, 'home') || str_contains($key, 'portal') || str_contains($url, '/apps/')) {
            return 80;
        }
        if (!empty($style['is_secondary'])) {
            return 40;
        }

        return 55;
    }

    /**
     * @param array<string, mixed> $itemConfig
     */
    private static function resolveItemAlwaysVisible(array $itemConfig, string $renderType): bool
    {
        if (array_key_exists('always_visible', $itemConfig) && $itemConfig['always_visible'] !== null) {
            return (bool)$itemConfig['always_visible'];
        }

        $style = (array)($itemConfig['style'] ?? []);
        $key = strtolower(trim((string)($itemConfig['key'] ?? '')));

        if ($renderType !== '') {
            return true;
        }

        return !empty($style['is_dashboard']) || !empty($style['is_action']) || str_contains($key, 'home');
    }

    /**
     * @param array<string, mixed> $itemConfig
     */
    private static function resolveItemUsageWeight(array $itemConfig): float
    {
        $weight = (float)($itemConfig['usage_weight'] ?? 1.0);
        return $weight > 0 ? $weight : 1.0;
    }

    /**
     * @param array<string, mixed> $itemConfig
     */
    private static function resolveItemUsageKey(array $itemConfig, string $resolvedUrl): string
    {
        $explicit = trim((string)($itemConfig['usage_key'] ?? ''));
        if ($explicit !== '') {
            return $explicit;
        }

        $canonical = trim((string)($itemConfig['canonical_target'] ?? ''));
        if ($canonical !== '') {
            return $canonical;
        }

        if ($resolvedUrl !== '') {
            return $resolvedUrl;
        }

        return trim((string)($itemConfig['key'] ?? ''));
    }

    /**
     * @param array<string, mixed> $itemConfig
     * @param array{menuByKey: array<string, array<string, mixed>>, menuByUrl: array<string, array<string, mixed>>, dynamicExtensionItems: array<int, array<string, mixed>>} $menuData
     */
    private static function resolveItemUrl(array $itemConfig, array $menuData): string
    {
        $menuKey = trim((string)($itemConfig['menu_key'] ?? ''));
        if ($menuKey !== '') {
            $row = $menuData['menuByKey'][$menuKey] ?? null;
            $menuUrl = trim((string)($row['url'] ?? ''));
            if ($menuUrl !== '' && str_starts_with($menuUrl, '/')) {
                return $menuUrl;
            }

            $fallbackFile = trim((string)($itemConfig['route_fallback_file'] ?? ''));
            if ($fallbackFile === '' || !is_file(APP_ROOT . '/' . $fallbackFile)) {
                return '';
            }
        }

        $sourceUrl = trim((string)($itemConfig['source_url'] ?? ''));
        if ($sourceUrl !== '' && $sourceUrl !== '/') {
            if (!isset($menuData['menuByUrl'][$sourceUrl])) {
                $fallbackFile = trim((string)($itemConfig['route_fallback_file'] ?? ''));
                if ($fallbackFile === '' || !is_file(APP_ROOT . '/' . $fallbackFile)) {
                    return '';
                }
            }
        }

        $url = trim((string)($itemConfig['url'] ?? ''));
        if ($url !== '') {
            return $url;
        }

        return '';
    }

    /**
     * @param array<string, mixed> $activePatterns
     * @return array<string, mixed>
     */
    private static function normalizeActiveRules(array $activePatterns, string $resolvedUrl): array
    {
        $exact = array_values(array_filter(array_map([self::class, 'normalizePath'], (array)($activePatterns['exact'] ?? []))));
        $prefix = array_values(array_filter(array_map([self::class, 'normalizePrefix'], (array)($activePatterns['prefix'] ?? []))));
        $hash = trim((string)($activePatterns['hash'] ?? ''));
        if ($hash !== '' && $hash[0] === '#') {
            $hash = substr($hash, 1);
        }

        if (empty($exact)) {
            $exact[] = self::normalizePath(parse_url($resolvedUrl, PHP_URL_PATH) ?: '/');
        }

        return [
            'exact' => $exact,
            'prefix' => $prefix,
            'hash' => $hash,
        ];
    }

    /**
     * @param array<string, mixed> $rules
     */
    private static function isItemActive(array $rules, string $currentPath): bool
    {
        $hashRule = trim((string)($rules['hash'] ?? ''));
        if ($hashRule !== '') {
            // Server-side matching cannot reliably inspect URL fragments.
            return false;
        }

        foreach ((array)($rules['exact'] ?? []) as $path) {
            if ($currentPath === self::normalizePath((string)$path)) {
                return true;
            }
        }

        foreach ((array)($rules['prefix'] ?? []) as $prefix) {
            $normalizedPrefix = self::normalizePrefix((string)$prefix);
            if ($normalizedPrefix !== '' && str_starts_with($currentPath, $normalizedPrefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, bool>
     */
    /**
     * Builds a map from nav group key → module record, using ModuleRegistry.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function buildModuleMap(): array
    {
        $map = [];
        if (class_exists(ModuleRegistry::class)) {
            foreach (ModuleRegistry::all() as $module) {
                foreach ((array)($module['nav_groups'] ?? []) as $groupKey) {
                    $map[(string)$groupKey] = $module;
                }
            }
        }
        return $map;
    }

    private static function knownUrls(array $config): array
    {
        $known = [];
        foreach ((array)($config['groups'] ?? []) as $groupConfig) {
            if (!is_array($groupConfig)) {
                continue;
            }

            foreach ((array)($groupConfig['items'] ?? []) as $itemConfig) {
                if (!is_array($itemConfig)) {
                    continue;
                }

                $url = trim((string)($itemConfig['url'] ?? ''));
                if ($url !== '') {
                    $known[$url] = true;
                }
            }
        }

        return $known;
    }

    /**
     * @param array<string, mixed> $visibilityContext
     */
    private static function isVisible(string $rule, array $visibilityContext): bool
    {
        return AclPolicy::allowsVisibilityRule($rule, $visibilityContext);
    }

    private static function normalizePath(string $path): string
    {
        $parsed = parse_url($path, PHP_URL_PATH);
        $normalized = '/' . ltrim((string)($parsed ?: '/'), '/');
        return rtrim($normalized, '/') ?: '/';
    }

    private static function normalizePrefix(string $prefix): string
    {
        $normalized = self::normalizePath($prefix);
        return rtrim($normalized, '/') . '/';
    }

    /**
     * @param array<string,mixed>|null $module
     * @param array<int,string> $allowedModules
     */
    private static function isModuleAllowed(?array $module, array $allowedModules): bool
    {
        if (empty($allowedModules)) {
            return true;
        }
        if ($module === null) {
            return true;
        }

        $moduleKey = (string)($module['key'] ?? '');
        if ($moduleKey === 'platform') {
            return true;
        }
        if ($moduleKey === 'admin_tools') {
            return in_array('admin', $allowedModules, true);
        }
        if ($moduleKey === 'developer_tools') {
            return in_array('admin', $allowedModules, true) || in_array('ops', $allowedModules, true);
        }
        if ($moduleKey === 'manufacturing_ipm') {
            foreach (['production', 'qc', 'assembly', 'dispatch', 'demands', 'coverage'] as $k) {
                if (in_array($k, $allowedModules, true)) {
                    return true;
                }
            }
            return false;
        }

        return true;
    }

    /**
     * @param array<string,mixed> $itemConfig
     * @param array<int,string> $allowedModules
     */
    private static function isNavItemAllowedByModule(array $itemConfig, array $allowedModules): bool
    {
        if (empty($allowedModules)) {
            return true;
        }

        $moduleToken = self::resolveModuleTokenForNavItem($itemConfig);
        if ($moduleToken === '') {
            return true;
        }

        return in_array($moduleToken, $allowedModules, true);
    }

    /**
     * @param array<string,mixed> $itemConfig
     */
    private static function resolveModuleTokenForNavItem(array $itemConfig): string
    {
        $declaredToken = self::declaredModuleTokenForNavItem($itemConfig);
        if ($declaredToken !== '') {
            return $declaredToken;
        }

        return self::inferModuleTokenFromNavItem($itemConfig);
    }

    /**
     * @param array<string,mixed> $itemConfig
     */
    private static function declaredModuleTokenForNavItem(array $itemConfig): string
    {
        $token = strtolower(trim((string)($itemConfig['module_token'] ?? '')));
        return $token;
    }

    /**
     * @param array<string,mixed> $itemConfig
     */
    private static function inferModuleTokenFromNavItem(array $itemConfig): string
    {
        $key = strtolower(trim((string)($itemConfig['key'] ?? '')));
        $url = strtolower(trim((string)($itemConfig['url'] ?? '')));

        if ($key === 'role_dashboard' || str_starts_with($url, '/ops/')) {
            return 'ops';
        }
        if (str_contains($key, 'qc') || str_contains($url, '/qc')) {
            return 'qc';
        }
        if (str_contains($key, 'assembly') || str_contains($url, '/assembly')) {
            return 'assembly';
        }
        if (str_contains($key, 'dispatch') || str_contains($url, '/dispatch')) {
            return 'dispatch';
        }
        if (str_contains($key, 'coverage') || str_contains($url, '/coverage')) {
            return 'coverage';
        }
        if (str_contains($key, 'demand') || str_contains($url, '/demands')) {
            return 'demands';
        }
        if (str_contains($key, 'admin') || str_starts_with($url, '/admin')) {
            return 'admin';
        }
        if (str_contains($key, 'production') || str_contains($key, 'daily') || str_contains($url, '/production') || str_contains($url, '/daily')) {
            return 'production';
        }

        return '';
    }

    /**
     * @param array<string,mixed> $itemConfig
     * @return array<int,string>
     */
    private static function normalizeSearchKeywords(array $itemConfig): array
    {
        $keywords = [];
        foreach ((array)($itemConfig['keywords'] ?? []) as $keyword) {
            $value = trim((string)$keyword);
            if ($value !== '') {
                $keywords[] = $value;
            }
        }
        foreach ((array)($itemConfig['aliases'] ?? []) as $alias) {
            $value = trim((string)$alias);
            if ($value !== '') {
                $keywords[] = $value;
            }
        }

        return array_values(array_unique($keywords));
    }
}

<?php
declare(strict_types=1);

namespace Apps\Shell\Services;

/** Projects the authorized Shell runtime menu into admin-home launcher groups. */
final class AdminDashboardLauncherComposer
{
    private const GROUP_KEYS = [
        'Governance' => 'governance',
        'Apps & Config' => 'apps_config',
        'Organization' => 'apps_config',
        'System Tools' => 'system_tools',
        'Developer' => 'developer',
    ];

    /**
     * Build the compatibility menu context without treating every admin-wrapper
     * account as a platform administrator.
     *
     * @param array<string,mixed> $user
     * @param array<string,mixed> $assignmentContext
     * @return array<string,mixed>
     */
    public static function menuContext(
        array $user,
        array $assignmentContext,
        bool $canAccessBase,
        bool $devToolsEnabled,
        string $currentPath
    ): array {
        $authorityRole = strtolower(trim((string)($assignmentContext['authority_role'] ?? $user['authority_role'] ?? 'app_user')));
        return [
            'loggedIn' => true,
            'user' => $user,
            'isAdmin' => $authorityRole === 'platform_admin',
            'canAccessBase' => $canAccessBase,
            'devToolsEnabled' => $devToolsEnabled,
            'currentPath' => $currentPath,
        ];
    }

    /** @param array<string,mixed> $menuPayload @return array<int,array<string,mixed>> */
    public static function compose(array $menuPayload, bool $developerToolsVisible = true): array
    {
        $groups = [];
        foreach (array_values(array_filter((array)($menuPayload['sections'] ?? []), 'is_array')) as $section) {
            foreach (array_values(array_filter((array)($section['groups'] ?? []), 'is_array')) as $group) {
                $label = trim((string)($group['label'] ?? ''));
                $key = self::GROUP_KEYS[$label] ?? null;
                if ($key === null) {
                    continue;
                }
                if ($key === 'developer' && !$developerToolsVisible) {
                    continue;
                }
                $items = self::items((array)($group['items'] ?? []));
                if ($items === []) {
                    continue;
                }
                if (!isset($groups[$key])) {
                    $groups[$key] = [
                        'key' => $key,
                        'label_key' => 'admin.launcher.group.' . $key,
                        'description_key' => 'admin.launcher.group.' . $key . '.description',
                        'items' => [],
                    ];
                }
                $groups[$key]['items'] = self::mergeItems((array)$groups[$key]['items'], $items);
            }
        }
        return array_values($groups);
    }

    /** @param array<int,mixed> $items @return array<int,array<string,string>> */
    private static function items(array $items): array
    {
        $resolved = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $url = trim((string)($item['url'] ?? ''));
            $label = trim((string)($item['label'] ?? ''));
            $itemKey = trim((string)($item['key'] ?? ''));
            $path = trim((string)parse_url($url, PHP_URL_PATH));
            if ($path === '' || $path === '#' || $label === '' || str_starts_with($itemKey, 'auto.')) {
                continue;
            }
            $dedupeKey = strtolower(rtrim($path, '/'));
            if (isset($resolved[$dedupeKey])) {
                continue;
            }
            $resolved[$dedupeKey] = ['key' => $itemKey !== '' ? $itemKey : $dedupeKey, 'label' => $label, 'url' => $url];
        }
        return array_values($resolved);
    }

    /**
     * @param array<int,array<string,string>> $left
     * @param array<int,array<string,string>> $right
     * @return array<int,array<string,string>>
     */
    private static function mergeItems(array $left, array $right): array
    {
        $merged = [];
        foreach (array_merge($left, $right) as $item) {
            $path = strtolower(rtrim(trim((string)parse_url((string)($item['url'] ?? ''), PHP_URL_PATH)), '/'));
            if ($path === '' || isset($merged[$path])) {
                continue;
            }
            $merged[$path] = $item;
        }
        return array_values($merged);
    }
}

<?php
declare(strict_types=1);
namespace Plugins\AdminTools\SectionCards;

final class PlatformAdminLinksCard {
    /** @return array<int,array{title_key:string,description_key:string,links:array<int,array{url:string,label_key:string}>}> */
    public static function getGroups(): array {
        return [
            [
                'title_key' => 'admin.platform_links.group.governance',
                'description_key' => 'admin.platform_links.group.governance_desc',
                'links' => [
                    ['url' => '/ops/access-control', 'label_key' => 'admin.platform_links.access_control'],
                    ['url' => '/ops/workspace-profiles', 'label_key' => 'admin.platform_links.workspace_profiles'],
                    ['url' => '/ops/user-dashboard', 'label_key' => 'admin.platform_links.user_dashboard'],
                    ['url' => '/ops/audit-log', 'label_key' => 'admin.platform_links.audit_log'],
                ],
            ],
            [
                'title_key' => 'admin.platform_links.group.apps',
                'description_key' => 'admin.platform_links.group.apps_desc',
                'links' => [
                    ['url' => '/admin/apps', 'label_key' => 'admin.platform_links.apps'],
                    ['url' => '/admin/base', 'label_key' => 'admin.platform_links.base'],
                ],
            ],
            [
                'title_key' => 'admin.platform_links.group.developer',
                'description_key' => 'admin.platform_links.group.developer_desc',
                'links' => [
                    ['url' => '/admin/routes', 'label_key' => 'admin.platform_links.routes'],
                ],
            ],
        ];
    }

    /** @deprecated Retained for callers outside the route; the canonical route now uses a governed view. */
    public static function renderCard(): string {
        $groups = self::getGroups();
        $out = '<div class="card"><div class="card-header">Platform Admin Links</div><div class="card-body"><ul>';
        foreach ($groups as $group) {
            foreach ($group['links'] as $link) {
                $label = function_exists('t') ? (string)t($link['label_key']) : $link['label_key'];
                $out .= '<li><a href="' . htmlspecialchars($link['url']) . '">' . htmlspecialchars($label) . '</a></li>';
            }
        }
        $out .= '</ul></div></div>';
        return $out;
    }
}

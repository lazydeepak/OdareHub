<?php
declare(strict_types=1);

$tr = static function (string $key, string $fallback): string {
    if (function_exists('t')) {
        $translated = (string)t($key);
        if ($translated !== '' && $translated !== $key) {
            return $translated;
        }
    }

    return $fallback;
};
$adminSection = $tr('admin.launcher.group.apps_config', 'Apps & Config');

return [
    'contract' => 'navigation.v1',
    'owner' => 'organization',
    'items' => [
        [
            'source_key' => 'admin.organization',
            'group' => 'Admin',
            'section' => $adminSection,
            'module' => 'organization',
            'owner' => 'organization',
            'key' => 'organization_overview',
            'module_token' => 'admin',
            'label' => $tr('organization.nav.admin_overview', 'Organization Overview'),
            'url' => '/ops/organization',
            'visible_if' => 'organization_access',
            'order' => 10,
            'priority' => 56,
            'style' => ['is_secondary' => true],
            'active_patterns' => [
                'exact' => ['/ops/organization'],
                'prefix' => [],
            ],
        ],
        [
            'source_key' => 'admin.organization',
            'group' => 'Admin',
            'section' => $adminSection,
            'module' => 'organization',
            'owner' => 'organization',
            'key' => 'organization_company_profile',
            'module_token' => 'admin',
            'label' => $tr('organization.nav.company', 'Company Profile'),
            'url' => '/ops/organization/company',
            'visible_if' => 'organization_access',
            'order' => 20,
            'priority' => 66,
            'style' => ['is_secondary' => true],
            'active_patterns' => [
                'exact' => ['/ops/organization/company'],
                'prefix' => ['/ops/organization/company/'],
            ],
        ],
        [
            'source_key' => 'admin.organization',
            'group' => 'Admin',
            'section' => $adminSection,
            'module' => 'organization',
            'owner' => 'organization',
            'key' => 'organization_branches',
            'module_token' => 'admin',
            'label' => $tr('organization.nav.branches', 'Branches'),
            'url' => '/ops/organization/branches',
            'visible_if' => 'organization_access',
            'order' => 30,
            'style' => ['is_secondary' => true],
            'active_patterns' => [
                'exact' => ['/ops/organization/branches'],
                'prefix' => ['/ops/organization/branches/'],
            ],
        ],
        [
            'source_key' => 'admin.organization',
            'group' => 'Admin',
            'section' => $adminSection,
            'module' => 'organization',
            'owner' => 'organization',
            'key' => 'organization_fiscal',
            'module_token' => 'admin',
            'label' => $tr('organization.nav.fiscal', 'Fiscal Settings'),
            'url' => '/ops/organization/fiscal',
            'visible_if' => 'organization_access',
            'order' => 40,
            'style' => ['is_secondary' => true],
            'active_patterns' => [
                'exact' => ['/ops/organization/fiscal'],
                'prefix' => ['/ops/organization/fiscal/'],
            ],
        ],
        [
            'source_key' => 'admin.organization',
            'group' => 'Admin',
            'section' => $adminSection,
            'module' => 'organization',
            'owner' => 'organization',
            'key' => 'organization_branding',
            'module_token' => 'admin',
            'label' => $tr('organization.nav.branding', 'Branding'),
            'url' => '/ops/organization/branding',
            'visible_if' => 'organization_access',
            'order' => 50,
            'style' => ['is_secondary' => true],
            'active_patterns' => [
                'exact' => ['/ops/organization/branding'],
                'prefix' => ['/ops/organization/branding/'],
            ],
        ],
    ],
];

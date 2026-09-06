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

return [
    ['key' => 'admin.organization.root', 'label' => $tr('organization.title', 'Organization'), 'url' => '/ops/organization', 'parent' => 'admin.root', 'order' => 32, 'perm' => 'organization.view'],
    ['key' => 'admin.organization.company', 'label' => $tr('organization.nav.company', 'Company Profile'), 'url' => '/ops/organization/company', 'parent' => 'admin.organization.root', 'order' => 10, 'perm' => 'organization.view'],
    ['key' => 'admin.organization.branches', 'label' => $tr('organization.nav.branches', 'Branches'), 'url' => '/ops/organization/branches', 'parent' => 'admin.organization.root', 'order' => 20, 'perm' => 'organization.view'],
    ['key' => 'admin.organization.fiscal', 'label' => $tr('organization.nav.fiscal', 'Fiscal Settings'), 'url' => '/ops/organization/fiscal', 'parent' => 'admin.organization.root', 'order' => 30, 'perm' => 'organization.view'],
    ['key' => 'admin.organization.branding', 'label' => $tr('organization.nav.branding', 'Branding'), 'url' => '/ops/organization/branding', 'parent' => 'admin.organization.root', 'order' => 40, 'perm' => 'organization.view'],
];

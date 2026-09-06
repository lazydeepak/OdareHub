<?php
declare(strict_types=1);

namespace Apps\Procurement\Services;

final class HostSurfaceContributionService
{
    /**
     * @return array<string,string>
     */
    public static function operationalFocusValues(): array
    {
        return [
            'office_ops' => t('ops.operational_focus.office_ops'),
        ];
    }

    /**
     * @return array<string,string>
     */
    public static function operationalRoleValues(): array
    {
        return [
            'my_work' => t('ops.access_control.app_role.role.my_work'),
        ];
    }
}

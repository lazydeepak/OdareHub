<?php
declare(strict_types=1);

namespace Apps\Platform\Services;

use Apps\Platform\Contracts\UserAccessPolicyContract;
use Apps\Platform\Contracts\UserContextContract;
use Apps\Platform\Contracts\UserScopeContract;
use Plugins\Base\Services\UserDashboardAssignmentService;

require_once __DIR__ . '/../Contracts/UserAccessPolicyContract.php';
require_once __DIR__ . '/../Contracts/UserContextContract.php';
require_once __DIR__ . '/../Contracts/UserScopeContract.php';

final class PlatformUserAssignmentAdapter implements UserContextContract, UserAccessPolicyContract, UserScopeContract
{
    public function resolveUserContext(?array $user): array
    {
        return UserDashboardAssignmentService::resolveUserContext($user);
    }

    public function dashboardTypeForUser(?array $user): string
    {
        return UserDashboardAssignmentService::dashboardTypeForUser($user);
    }

    public function primaryLandingForContext(array $context): string
    {
        return UserDashboardAssignmentService::primaryLandingForContext($context);
    }

    public function routeForDashboardType(string $dashboardType): string
    {
        return UserDashboardAssignmentService::routeForDashboardType($dashboardType);
    }

    public function routeAccessDecision(?array $user, string $path, string $method = 'GET'): array
    {
        return UserDashboardAssignmentService::routeAccessDecision($user, $path, $method);
    }

    public function enabledModulesForUser(?array $user): array
    {
        return UserDashboardAssignmentService::enabledModulesForUser($user);
    }

    public function canAccessModule(?array $user, string $moduleKey): bool
    {
        return UserDashboardAssignmentService::canAccessModule($user, $moduleKey);
    }

    public function isAppEnabled(string $appKey): bool
    {
        return UserDashboardAssignmentService::isAppEnabled($appKey);
    }

    public function scopeFiltersForTable(array $scope, string $table, array $columnToScopeKey): array
    {
        return UserDashboardAssignmentService::scopeFiltersForTable($scope, $table, $columnToScopeKey);
    }

    public function meCoreQuickLinks(array $context): array
    {
        return UserDashboardAssignmentService::meCoreQuickLinks($context);
    }
}

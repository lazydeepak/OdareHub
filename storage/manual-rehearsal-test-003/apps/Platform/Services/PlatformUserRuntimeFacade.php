<?php
declare(strict_types=1);

namespace Apps\Platform\Services;

final class PlatformUserRuntimeFacade
{
    /**
     * @param array<string,mixed>|null $user
     * @return array<string,mixed>
     */
    public function resolveUserContext(?array $user): array
    {
        return platform_user_context_contract()->resolveUserContext($user);
    }

    /**
     * @param array<string,mixed>|null $user
     * @return array{allowed:bool,reason:string}
     */
    public function routeAccessDecision(?array $user, string $path, string $method = 'GET'): array
    {
        return platform_user_access_policy_contract()->routeAccessDecision($user, $path, $method);
    }

    /**
     * @param array<string,mixed>|null $user
     * @return array<int,string>
     */
    public function enabledModulesForUser(?array $user): array
    {
        return platform_user_access_policy_contract()->enabledModulesForUser($user);
    }

    /**
     * @param array<string,mixed>|null $user
     * @param array<int,string> $modules
     * @return array<int,string>
     */
    public function filterModulesForUser(?array $user, array $modules): array
    {
        $enabledModules = $this->enabledModulesForUser($user);
        if ($enabledModules === []) {
            return $modules;
        }

        $enabledSet = [];
        foreach ($enabledModules as $moduleKey) {
            $enabledSet[(string)$moduleKey] = true;
        }

        $filtered = [];
        foreach ($modules as $module) {
            $moduleKey = (string)$module;
            if (isset($enabledSet[$moduleKey])) {
                $filtered[] = $moduleKey;
            }
        }

        return $filtered;
    }

    public function isAppEnabled(string $appKey): bool
    {
        return platform_user_access_policy_contract()->isAppEnabled($appKey);
    }

    /**
     * @param array<string,mixed>|null $user
     */
    public function primaryLandingForUser(?array $user): string
    {
        $context = $this->resolveUserContext($user);
        return platform_user_context_contract()->primaryLandingForContext($context);
    }

    /**
     * @param array<string,mixed> $scope
     * @param array<string,string> $columnToScopeKey
     * @return array{sql:string,params:array<int,int|string>}
     */
    public function scopeFiltersForTable(array $scope, string $table, array $columnToScopeKey): array
    {
        return platform_user_scope_contract()->scopeFiltersForTable($scope, $table, $columnToScopeKey);
    }
}

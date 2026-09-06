<?php
declare(strict_types=1);

namespace Apps\Platform\Contracts;

interface UserAccessPolicyContract
{
    /**
     * @param array<string,mixed>|null $user
     * @return array{allowed:bool,reason:string}
     */
    public function routeAccessDecision(?array $user, string $path, string $method = 'GET'): array;

    /**
     * @param array<string,mixed>|null $user
     * @return array<int,string>
     */
    public function enabledModulesForUser(?array $user): array;

    /**
     * @param array<string,mixed>|null $user
     */
    public function canAccessModule(?array $user, string $moduleKey): bool;

    public function isAppEnabled(string $appKey): bool;
}

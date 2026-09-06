<?php
declare(strict_types=1);

namespace Apps\Platform\Contracts;

interface UserContextContract
{
    /**
     * @param array<string,mixed>|null $user
     * @return array<string,mixed>
     */
    public function resolveUserContext(?array $user): array;

    /**
     * @param array<string,mixed>|null $user
     */
    public function dashboardTypeForUser(?array $user): string;

    /**
     * @param array<string,mixed> $context
     */
    public function primaryLandingForContext(array $context): string;

    public function routeForDashboardType(string $dashboardType): string;

    /**
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    public function meCoreQuickLinks(array $context): array;
}

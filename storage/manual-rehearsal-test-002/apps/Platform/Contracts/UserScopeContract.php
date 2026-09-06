<?php
declare(strict_types=1);

namespace Apps\Platform\Contracts;

interface UserScopeContract
{
    /**
     * @param array<string,mixed> $scope
     * @param array<string,string> $columnToScopeKey
     * @return array{sql:string,params:array<int,int|string>}
     */
    public function scopeFiltersForTable(array $scope, string $table, array $columnToScopeKey): array;
}

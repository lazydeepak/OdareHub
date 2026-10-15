<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services;

use App\Core\DB;

/**
 * Manufacturing-owned read boundary for the operator Parts workspace.
 *
 * Selection is authorized before LIMIT and before Part360Service is invoked.
 * This service intentionally does not initialize schema.
 */
final class OperatorPartsWorkspaceService
{
    /**
     * @param array<string,mixed>|null $user
     * @return array{rows:array<int,array<string,mixed>>,total_count:int,empty_label:string}
     */
    public static function getPartsTable(?array $user): array
    {
        $defaults = ['rows' => [], 'total_count' => 0, 'empty_label' => 'No products found.'];
        $scope = self::authorizedScope($user);
        if ($scope === null || $scope['denied']) {
            return $defaults;
        }

        try {
            [$where, $params] = self::partRestrictionSql($scope['part_ids']);
            $rows = DB::fetchAll(
                "SELECT
                    p.id,
                    COALESCE(NULLIF(TRIM(p.parts_name), ''), CONCAT('Part #', p.id)) AS parts_name,
                    COALESCE(NULLIF(TRIM(p.parts_number), ''), CONCAT('P-', p.id)) AS parts_number,
                    COALESCE(NULLIF(TRIM(p.model), ''), '') AS model,
                    0 AS stock,
                    0 AS today_demand,
                    '-' AS coverage,
                    '' AS next_plan_date,
                    0 AS next_plan_qty
                 FROM products p
                 WHERE {$where}
                 ORDER BY p.id ASC
                 LIMIT 500",
                $params
            );

            $formatted = [];
            foreach ((array)$rows as $row) {
                $formatted[] = self::formatRow($row);
            }
            return ['rows' => $formatted, 'total_count' => count($formatted), 'empty_label' => 'No products found.'];
        } catch (\Throwable) {
            return ['rows' => [], 'total_count' => 0, 'empty_label' => 'Unable to load parts table.'];
        }
    }

    /**
     * Resolve an operator-selected part only after applying the same scope
     * predicate used by the list. Search and direct IDs cannot bypass it.
     *
     * @param array<string,mixed> $query
     * @param array<string,mixed>|null $user
     */
    public static function resolveAuthorizedPartId(array $query, ?array $user): int
    {
        $scope = self::authorizedScope($user);
        if ($scope === null || $scope['denied']) {
            return 0;
        }

        $direct = self::positiveId($query['part_id'] ?? $query['product_id'] ?? $query['id'] ?? null);
        $partNumber = is_string($query['part_number'] ?? null) ? trim((string)$query['part_number']) : '';
        $search = is_string($query['q'] ?? null)
            ? trim((string)$query['q'])
            : (is_string($query['part_q'] ?? null) ? trim((string)$query['part_q']) : '');
        if ($direct === null && (($query['part_id'] ?? $query['product_id'] ?? $query['id'] ?? null) !== null)) {
            return 0;
        }
        if ($direct === null && $partNumber === '' && $search === '') {
            return 0;
        }

        try {
            [$scopeWhere, $scopeParams] = self::partRestrictionSql($scope['part_ids'], 'p.');
            $where = [$scopeWhere];
            $params = $scopeParams;
            if ($direct !== null) {
                $where[] = 'p.id = ?';
                $params[] = $direct;
            } elseif ($partNumber !== '') {
                $where[] = 'TRIM(p.parts_number) = ?';
                $params[] = $partNumber;
            } else {
                $like = '%' . substr($search, 0, 80) . '%';
                $where[] = '(p.parts_name LIKE ? OR p.parts_number LIKE ? OR COALESCE(p.model, \'\') LIKE ?)';
                array_push($params, $like, $like, $like);
            }
            $row = DB::fetchOne(
                'SELECT p.id FROM products p WHERE ' . implode(' AND ', $where) . ' ORDER BY p.is_active DESC, p.parts_name ASC, p.id ASC LIMIT 1',
                $params
            );
            return is_array($row) ? (int)($row['id'] ?? 0) : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Pure projection used by isolated checks to prove list/detail parity.
     * Runtime list authorization still happens in SQL before LIMIT.
     *
     * @param array<int,array<string,mixed>> $rows
     * @param array<string,mixed> $scope
     * @return array<int,array<string,mixed>>|null
     */
    public static function projectAuthorizedRows(array $rows, array $scope): ?array
    {
        $normalized = self::normalizeScope($scope);
        if ($normalized === null) {
            return null;
        }
        if ($normalized['denied']) {
            return [];
        }
        if ($normalized['part_ids'] === []) {
            return array_values(array_filter($rows, static fn($row): bool => is_array($row)));
        }
        return array_values(array_filter($rows, static function ($row) use ($normalized): bool {
            return is_array($row) && in_array((int)($row['id'] ?? $row['part_id'] ?? 0), $normalized['part_ids'], true);
        }));
    }

    /**
     * @param array<string,mixed> $scope
     */
    public static function isPartAuthorized(int $partId, array $scope): bool
    {
        if ($partId <= 0) {
            return false;
        }
        $normalized = self::normalizeScope($scope);
        return $normalized !== null && !$normalized['denied']
            && ($normalized['part_ids'] === [] || in_array($partId, $normalized['part_ids'], true));
    }

    /**
     * Pure fixture-facing scope decision. Empty is unrestricted only when the
     * caller has already established valid operator authority.
     *
     * @param array<string,mixed> $scope
     * @return array{part_ids:array<int,int>,denied:bool}|null
     */
    public static function normalizeScope(array $scope): ?array
    {
        if (!array_key_exists('part_ids', $scope) || !is_array($scope['part_ids'])) {
            return null;
        }
        $values = $scope['part_ids'];
        if ($values === []) {
            return ['part_ids' => [], 'denied' => false];
        }
        if (count($values) === 1 && ($values[0] ?? null) === -1) {
            return ['part_ids' => [], 'denied' => true];
        }
        if (count($values) === 1 && ($values[0] ?? null) === '-1') {
            return ['part_ids' => [], 'denied' => true];
        }
        if (array_keys($values) !== range(0, count($values) - 1)) {
            return null;
        }
        $ids = [];
        foreach ($values as $value) {
            if (is_int($value) && $value > 0) {
                $id = $value;
            } elseif (is_string($value) && preg_match('/^[1-9][0-9]*$/', $value) === 1) {
                $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($id === false) {
                    return null;
                }
            } else {
                return null;
            }
            $ids[$id] = $id;
        }
        $ids = array_values($ids);
        sort($ids);
        return ['part_ids' => $ids, 'denied' => false];
    }

    /**
     * @param array<string,mixed>|null $user
     * @return array{part_ids:array<int,int>,denied:bool}|null
     */
    private static function authorizedScope(?array $user): ?array
    {
        if (!is_array($user) || (int)($user['id'] ?? 0) <= 0
            || !function_exists('platform_user_context_contract')
            || !function_exists('platform_user_access_policy_contract')) {
            return null;
        }
        try {
            $context = platform_user_context_contract()->resolveUserContext($user);
            $assignedApps = array_map(static fn($value): string => strtolower(trim((string)$value)), (array)($context['active_assigned_apps'] ?? []));
            if (!in_array('manufacturing', $assignedApps, true)) {
                return null;
            }
            $policy = platform_user_access_policy_contract();
            if (!$policy->isAppEnabled('manufacturing') || !$policy->canAccessModule($user, 'products')) {
                return null;
            }
            return self::normalizeScope((array)($context['scope'] ?? []));
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{0:string,1:array<int,int|string>} */
    private static function partRestrictionSql(array $partIds, string $prefix = ''): array
    {
        if ($partIds === []) {
            return ['1=1', []];
        }
        return [
            $prefix . 'id IN (' . implode(',', array_fill(0, count($partIds), '?')) . ')',
            $partIds,
        ];
    }

    private static function positiveId(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }
        if (is_string($value) && preg_match('/^[1-9][0-9]*$/', $value) === 1) {
            $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            return $id === false ? null : $id;
        }
        return null;
    }

    /** @return array<string,mixed> */
    private static function formatRow(array $row): array
    {
        return [
            'id' => (int)($row['id'] ?? 0),
            'parts_name' => trim((string)($row['parts_name'] ?? '')),
            'parts_number' => trim((string)($row['parts_number'] ?? '')),
            'model' => trim((string)($row['model'] ?? '')),
            'stock' => (float)($row['stock'] ?? 0),
            'today_demand' => (float)($row['today_demand'] ?? 0),
            'coverage' => trim((string)($row['coverage'] ?? '-')),
            'next_plan_date' => trim((string)($row['next_plan_date'] ?? '')),
            'next_plan_qty' => (float)($row['next_plan_qty'] ?? 0),
        ];
    }
}

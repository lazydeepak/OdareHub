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
     * @param array<string,mixed> $query
     * @return array{rows:array<int,array<string,mixed>>,total_count:int,empty_label:string}
     */
    public static function getPartsTable(?array $user, array $query = []): array
    {
        $defaults = ['rows' => [], 'total_count' => 0, 'empty_label' => 'No products found.'];
        $scope = self::authorizedScope($user);
        if ($scope === null || $scope['denied']) {
            return $defaults;
        }

        $search = self::normalizeListSearch($query);
        if ($search === null) {
            return $defaults;
        }

        try {
            [$where, $params] = self::partRestrictionSql($scope['part_ids']);
            if ($search !== '') {
                $like = '%' . self::escapeLike($search) . '%';
                $where .= ' AND (LOWER(COALESCE(p.parts_name, \'\')) LIKE ? ESCAPE \'\\\\\''
                    . ' OR LOWER(COALESCE(p.parts_number, \'\')) LIKE ? ESCAPE \'\\\\\''
                    . ' OR LOWER(COALESCE(p.model, \'\')) LIKE ? ESCAPE \'\\\\\')';
                array_push($params, $like, $like, $like);
            }
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
     * Apply the same list-search semantics to fixture rows without database
     * access. Authorization is projected before matching and before limiting.
     *
     * @param array<string,mixed> $query
     * @param array<string,mixed> $scope
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>|null
     */
    public static function projectFilteredRows(array $query, array $scope, array $rows): ?array
    {
        $normalizedScope = self::normalizeScope($scope);
        $search = self::normalizeListSearch($query);
        if ($normalizedScope === null || $search === null) {
            return null;
        }

        $authorized = self::projectAuthorizedRows($rows, $scope);
        if ($authorized === null) {
            return null;
        }
        if ($search === '') {
            return array_slice($authorized, 0, 500);
        }

        $needle = strtolower($search);
        $filtered = array_values(array_filter($authorized, static function ($row) use ($needle): bool {
            if (!is_array($row)) {
                return false;
            }
            $haystack = strtolower(implode(' ', [
                trim((string)($row['parts_name'] ?? '')),
                trim((string)($row['parts_number'] ?? '')),
                trim((string)($row['model'] ?? '')),
            ]));
            return str_contains($haystack, $needle);
        }));

        return array_slice($filtered, 0, 500);
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

        $selection = self::normalizeSelection($query);
        if ($selection === null) {
            return 0;
        }

        try {
            [$scopeWhere, $scopeParams] = self::partRestrictionSql($scope['part_ids'], 'p.');
            $where = [$scopeWhere];
            $params = $scopeParams;
            if ($selection['direct'] !== null) {
                $where[] = 'p.id = ?';
                $params[] = $selection['direct'];
            } elseif ($selection['part_number'] !== '') {
                $where[] = 'TRIM(p.parts_number) = ?';
                $params[] = $selection['part_number'];
            } else {
                $like = '%' . substr($selection['search'], 0, 80) . '%';
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
     * Isolated fake-provider harness for the same strict selector semantics
     * used by the runtime boundary. No database or schema authority is used.
     *
     * @param array<string,mixed> $query
     * @param array<string,mixed> $scope
     * @param array<int,array<string,mixed>> $rows
     */
    public static function resolveAuthorizedPartIdFromRows(array $query, array $scope, array $rows): int
    {
        $normalized = self::normalizeScope($scope);
        $selection = self::normalizeSelection($query);
        if ($normalized === null || $normalized['denied'] || $selection === null) {
            return 0;
        }

        $candidates = self::projectAuthorizedRows($rows, $scope) ?? [];
        if ($selection['direct'] !== null) {
            foreach ($candidates as $row) {
                if ((int)($row['id'] ?? $row['part_id'] ?? 0) === $selection['direct']) {
                    return $selection['direct'];
                }
            }
            return 0;
        }

        usort($candidates, static function (array $left, array $right): int {
            $active = ((int)($right['is_active'] ?? 1)) <=> ((int)($left['is_active'] ?? 1));
            if ($active !== 0) {
                return $active;
            }
            $name = strcasecmp((string)($left['parts_name'] ?? ''), (string)($right['parts_name'] ?? ''));
            return $name !== 0 ? $name : ((int)($left['id'] ?? 0) <=> (int)($right['id'] ?? 0));
        });
        foreach ($candidates as $row) {
            $id = (int)($row['id'] ?? $row['part_id'] ?? 0);
            if ($selection['part_number'] !== '' && trim((string)($row['parts_number'] ?? '')) === $selection['part_number']) {
                return $id;
            }
            if ($selection['search'] !== '') {
                $haystack = strtolower(trim((string)($row['parts_name'] ?? '') . ' ' . (string)($row['parts_number'] ?? '') . ' ' . (string)($row['model'] ?? '')));
                if (str_contains($haystack, strtolower($selection['search']))) {
                    return $id;
                }
            }
        }
        return 0;
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

    /**
     * @param array<string,mixed> $query
     * @return array{direct:?int,part_number:string,search:string}|null
     */
    private static function normalizeSelection(array $query): ?array
    {
        $directValues = [];
        foreach (['part_id', 'product_id', 'id'] as $key) {
            if (!array_key_exists($key, $query)) {
                continue;
            }
            $value = self::positiveId($query[$key]);
            if ($value === null) {
                return null;
            }
            $directValues[] = $value;
        }
        if ($directValues !== [] && count(array_unique($directValues)) !== 1) {
            return null;
        }
        $partNumber = array_key_exists('part_number', $query) ? self::textSelector($query['part_number']) : '';
        $q = array_key_exists('q', $query) ? self::textSelector($query['q']) : '';
        $partQ = array_key_exists('part_q', $query) ? self::textSelector($query['part_q']) : '';
        if (($partNumber === null) || ($q === null) || ($partQ === null)) {
            return null;
        }
        if ($q !== '' && $partQ !== '' && $q !== $partQ) {
            return null;
        }
        $search = $q !== '' ? $q : $partQ;
        if ($partNumber !== '' && $search !== '') {
            return null;
        }
        $direct = $directValues[0] ?? null;
        if ($direct !== null && ($partNumber !== '' || $search !== '')) {
            return null;
        }
        if ($direct === null && $partNumber === '' && $search === '') {
            return null;
        }
        return ['direct' => $direct, 'part_number' => $partNumber, 'search' => $search];
    }

    private static function textSelector(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        return trim(substr($value, 0, 120));
    }

    private static function normalizeListSearch(array $query): ?string
    {
        if (!array_key_exists('q', $query)) {
            return '';
        }
        if (!is_string($query['q'])) {
            return null;
        }

        $raw = $query['q'];
        $value = trim($raw);
        if ($value === '' && $raw !== '') {
            return null;
        }
        if (strlen($value) > 80) {
            return null;
        }
        return $value;
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
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

<?php
declare(strict_types=1);

namespace App\Core {
    final class DB
    {
        public static array $reads = [];

        public static function fetchOne(string $sql, array $params = []): ?array
        {
            self::$reads[] = [$sql, $params];
            if (str_contains($sql, 'FROM production_plans')) {
                $id = (int)($params[0] ?? 0);
                return match ($id) {
                    1 => ['id' => 1, 'status' => 'Planned', 'locked_at' => '', 'product_id' => 10, 'machine_id' => 1],
                    2 => ['id' => 2, 'status' => 'InProgress', 'locked_at' => '', 'product_id' => 10, 'machine_id' => 1],
                    3 => ['id' => 3, 'status' => 'In Progress', 'locked_at' => '', 'product_id' => 10, 'machine_id' => 1],
                    4 => ['id' => 4, 'status' => 'Planned', 'locked_at' => '2026-10-25 10:00:00', 'product_id' => 10, 'machine_id' => 1],
                    default => null,
                };
            }
            if (str_contains($sql, 'FROM machines')) return ['id' => 1];
            if (str_contains($sql, 'FROM products')) return ['id' => 10];
            if (str_contains($sql, 'SUM(CASE')) return ['low_coverage' => 1, 'shortage_parts' => 1, 'at_risk_today' => 1];
            if (str_contains($sql, 'overstock_sub')) return ['c' => 1];
            return null;
        }

        public static function fetchAll(string $sql, array $params = []): array
        {
            self::$reads[] = [$sql, $params];
            if (str_contains($sql, 'FROM daily_orders')) return [['part_id' => 10, 'part_name' => 'Part X', 'part_number' => 'PX-10', 'coverage_pct' => 20, 'shortage_qty' => 2]];
            if (str_contains($sql, 'FROM production_plans')) return [['id' => 1, 'plan_date' => '2026-10-25', 'planned_qty' => 5, 'status' => 'Planned', 'part_name' => 'Part X', 'part_number' => 'PX-10', 'machine_label' => 'M-01']];
            if (str_contains($sql, 'FROM products')) return [['id' => 10, 'parts_name' => 'Part X', 'parts_number' => 'PX-10']];
            if (str_contains($sql, 'FROM machines')) return [['id' => 1, 'machine_no' => 'M-01', 'machine_name' => 'Cutting']];
            return [];
        }
    }
}

namespace ProductionAuthorizationProbe {
    final class Context
    {
        public static array $value = [];
        public function resolveUserContext(?array $user): array { return self::$value; }
    }

    final class Policy
    {
        public static bool $production = true;
        public function isAppEnabled(string $app): bool { return $app === 'manufacturing'; }
        public function canAccessModule(?array $user, string $module): bool { return $module === 'production' && self::$production; }
    }
}

namespace {
    use Apps\Manufacturing\Services\ProductionOperationService;
    use App\Core\DB;

    define('APP_ROOT', dirname(__DIR__, 3));
    require_once APP_ROOT . '/apps/Manufacturing/Services/ProductionOperationService.php';

    function platform_user_context_contract(): object { return new \ProductionAuthorizationProbe\Context(); }
    function platform_user_access_policy_contract(): object { return new \ProductionAuthorizationProbe\Policy(); }

    $pass = 0;
    $fail = 0;
    $assert = static function (bool $ok, string $label) use (&$pass, &$fail): void {
        echo ($ok ? 'PASS ' : 'FAIL ') . $label . "\n";
        $ok ? $pass++ : $fail++;
    };

    $user = ['id' => 7, 'email' => 'worker@example.test'];
    \ProductionAuthorizationProbe\Context::$value = [
        'active_assigned_apps' => ['manufacturing'],
        'access_profiles' => ['production_operations'],
        'app_roles' => [],
        'duty_codes' => ['prod_plan_execute'],
        'authority_role' => 'app_user',
        'account_class' => 'app_user',
        'scope' => ['machine_ids' => [1], 'part_ids' => [10]],
    ];

    $focus = ProductionOperationService::operatorFocus('2026-10-25', $user);
    $assert($focus['authorized'] === true && $focus['can_mutate'] === true, 'assigned production operator receives authorized mutable projection');
    $assert(count($focus['queue_rows']) === 1 && !empty($focus['queue_rows'][0]['can_start']), 'scoped queue is projected before limit with canonical start action');
    $assert(count($focus['products']) === 1 && count($focus['machines']) === 1, 'product and machine selectors remain scope-aligned');
    $scopeSql = implode(' ', array_map(static fn(array $read): string => $read[0], DB::$reads));
    $assert(str_contains($scopeSql, 'pp.product_id IN') && str_contains($scopeSql, 'pp.machine_id IN'), 'Production plan query carries both scope predicates');

    $assert(ProductionOperationService::authorizeOperatorMutation('production_status', 1, 'InProgress', $user)['allowed'] === true, 'Planned to canonical InProgress is allowed');
    $assert(ProductionOperationService::authorizeOperatorMutation('production_status', 2, 'Completed', $user)['allowed'] === true, 'InProgress to Completed is allowed');
    $assert(ProductionOperationService::authorizeOperatorMutation('production_status', 3, 'Completed', $user)['allowed'] === false, 'spaced In Progress legacy status is not silently treated as canonical');
    $assert(ProductionOperationService::authorizeOperatorMutation('production_status', 4, 'InProgress', $user)['allowed'] === false, 'locked plan is denied');
    $assert(ProductionOperationService::authorizeOperatorMutation('production_status', 99, 'InProgress', $user)['allowed'] === false, 'missing plan is denied');
    $assert(ProductionOperationService::authorizeOperatorMutation('production_status', 1, 'In Progress', $user)['allowed'] === false, 'legacy target alias is denied');

    \ProductionAuthorizationProbe\Policy::$production = false;
    DB::$reads = [];
    $moduleDenied = ProductionOperationService::operatorFocus('2026-10-25', $user);
    $assert($moduleDenied['authorized'] === false && DB::$reads === [], 'missing Production module authority fails before reads');
    \ProductionAuthorizationProbe\Policy::$production = true;

    \ProductionAuthorizationProbe\Context::$value['access_profiles'] = ['readonly_observer'];
    $readOnly = ProductionOperationService::authorizeOperatorMutation('production_status', 1, 'InProgress', $user);
    $assert($readOnly['allowed'] === false, 'read-only profile cannot mutate an in-scope plan');
    \ProductionAuthorizationProbe\Context::$value['access_profiles'] = ['production_operations'];

    \ProductionAuthorizationProbe\Context::$value['scope'] = ['machine_ids' => [-1], 'part_ids' => [10]];
    DB::$reads = [];
    $deniedScope = ProductionOperationService::operatorFocus('2026-10-25', $user);
    $assert($deniedScope['authorized'] === false && DB::$reads === [], 'denied scope fails closed before reads');

    $create = ProductionOperationService::authorizeOperatorCreate(1, 11, $user);
    $assert($create['allowed'] === false, 'out-of-scope plan creation is denied');

    $assert(ProductionOperationService::normalizeOperatorId('7') === 7, 'scalar decimal identifier normalizes exactly');
    foreach (['1abc', '1.5', '1e2', '+1', '-1', '0', '9223372036854775808'] as $invalidId) {
        $assert(ProductionOperationService::normalizeOperatorId($invalidId) === null, 'malformed or overflowing identifier is rejected: ' . $invalidId);
    }
    $assert(ProductionOperationService::normalizeOperatorId([]) === null, 'array identifier is rejected');
    $assert(ProductionOperationService::normalizeOperatorId(null) === null, 'missing identifier is rejected');

    $assert(ProductionOperationService::normalizeOperatorQuantity('1.5') === 1.5, 'valid decimal quantity normalizes to existing precision');
    $assert(ProductionOperationService::normalizeOperatorQuantity('1e2') === 100.0, 'finite numeric quantity remains compatible with existing float contract');
    $assert(ProductionOperationService::normalizeOperatorQuantity('0') === 0.0, 'zero quantity remains available for existing route-level rejection');
    foreach (['1abc', 'NaN', 'INF', '-1', '1e309', '999999999999.999'] as $invalidQuantity) {
        $assert(ProductionOperationService::normalizeOperatorQuantity($invalidQuantity) === null, 'malformed, non-finite, negative, or overflowing quantity is rejected: ' . $invalidQuantity);
    }
    $assert(ProductionOperationService::normalizeOperatorQuantity([]) === null, 'array quantity is rejected');
    $assert(ProductionOperationService::normalizeOperatorQuantity(null) === null, 'missing quantity is rejected');

    $routesSource = (string)file_get_contents(APP_ROOT . '/apps/Shell/routes.php');
    $assert(str_contains($routesSource, 'normalizeOperatorId($rawPlanId)'), 'status route normalizes raw plan ID before mutation authorization');
    $assert(str_contains($routesSource, 'normalizeOperatorId($rawMachineId)') && str_contains($routesSource, 'normalizeOperatorId($rawProductId)'), 'create route normalizes raw IDs before create authorization');
    $assert(str_contains($routesSource, 'normalizeOperatorQuantity($_POST[\'planned_qty\'] ?? null)'), 'create route normalizes raw quantity before create authorization');
    $assert(!str_contains($routesSource, '(int)$_POST[\'plan_id\']') && !str_contains($routesSource, '(int)$_POST[\'machine_id\']'), 'Production routes contain no direct POST integer coercion');

    if ($fail > 0) {
        fwrite(STDERR, "RESULT: {$pass}/" . ($pass + $fail) . " assertions passed; {$fail} failed\n");
        exit(1);
    }
    echo "RESULT: {$pass}/{$pass} assertions passed\n";
}

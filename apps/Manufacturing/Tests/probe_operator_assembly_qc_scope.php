<?php
declare(strict_types=1);

namespace App\Core {
    final class DB
    {
        /** @var array<int,array{sql:string,params:array<int,mixed>}> */
        public static array $reads = [];

        public static function fetchOne(string $sql, array $params = []): ?array
        {
            self::$reads[] = ['sql' => $sql, 'params' => $params];
            if (str_contains($sql, 'mfg_assembly_entries')) {
                return [
                    'today_total' => 1,
                    'in_progress' => 1,
                    'completed_today' => 0,
                    'pending_approval' => 0,
                    'blocked' => 0,
                    'planned_qty' => 5,
                    'completed_qty' => 1,
                ];
            }
            return null;
        }

        public static function fetchAll(string $sql, array $params = []): array
        {
            self::$reads[] = ['sql' => $sql, 'params' => $params];
            if (str_contains($sql, 'mfg_assembly_entries')) {
                return [[
                    'id' => 7,
                    'product_id' => 10,
                    'product_name' => 'Part X',
                    'assembly_date' => '2026-10-25',
                    'planned_qty' => 5,
                    'completed_qty' => 1,
                    'rejected_qty' => 0,
                    'status' => 'in_progress',
                ]];
            }
            return [];
        }
    }
}

namespace AssemblyQcScopeProbe {
    final class ContextContract
    {
        public function resolveUserContext(?array $user): array
        {
            return self::$context;
        }

        /** @var array<string,mixed> */
        public static array $context = [];
    }

    final class PolicyContract
    {
        /** @var array<int,string>|null */
        public static ?array $allowedModules = null;

        public function isAppEnabled(string $app): bool
        {
            return $app === 'manufacturing';
        }

        public function canAccessModule(?array $user, string $module): bool
        {
            return self::$allowedModules === null || in_array($module, self::$allowedModules, true);
        }
    }
}

namespace {
    use Apps\Manufacturing\Services\OperatorLayerAdapters\AssemblyAdapter;
    use Apps\Manufacturing\Services\OperatorLayerAdapters\QcAdapter;
    use Apps\Manufacturing\Services\ProcessingOperationService;
    use App\Core\DB;

    define('APP_ROOT', dirname(__DIR__, 3));
    require_once APP_ROOT . '/apps/Manufacturing/Services/OperatorLayerAdapters/AssemblyAdapter.php';
    require_once APP_ROOT . '/apps/Manufacturing/Services/OperatorLayerAdapters/QcAdapter.php';

    function platform_user_context_contract(): object
    {
        return new \AssemblyQcScopeProbe\ContextContract();
    }

    function platform_user_access_policy_contract(): object
    {
        return new \AssemblyQcScopeProbe\PolicyContract();
    }

    $pass = 0;
    $fail = 0;
    $assert = static function (bool $ok, string $label) use (&$pass, &$fail): void {
        echo ($ok ? 'PASS ' : 'FAIL ') . $label . "\n";
        $ok ? $pass++ : $fail++;
    };

    $user = ['id' => 7, 'email' => 'operator@example.com'];
    \AssemblyQcScopeProbe\ContextContract::$context = [
        'active_assigned_apps' => ['manufacturing'],
        'access_profiles' => ['assembly_operations', 'qc_operations'],
        'duty_codes' => [],
        'app_roles' => [],
        'scope' => ['part_ids' => [10]],
    ];
    \AssemblyQcScopeProbe\PolicyContract::$allowedModules = ['assembly', 'qc'];

    $scope = ProcessingOperationService::operatorReadScope($user);
    $assert(is_array($scope) && $scope['assembly_allowed'] && $scope['qc_allowed'] && $scope['part_ids'] === [10], 'operator read scope retains existing lane and part authority');

    $assembly = AssemblyAdapter::getData(0, $user);
    $assemblyRead = DB::$reads[count(DB::$reads) - 1] ?? ['sql' => '', 'params' => []];
    $assert(count($assembly['today_entries']) === 1, 'authorized Assembly operator receives projected rows');
    $assert(str_contains($assemblyRead['sql'], 'ae.product_id IN (?)') && $assemblyRead['params'] === ['2026-10-25', 10], 'Assembly query applies part scope before result limit');

    \AssemblyQcScopeProbe\PolicyContract::$allowedModules = ['qc'];
    DB::$reads = [];
    $assemblyDenied = AssemblyAdapter::getData(0, $user);
    $assert($assemblyDenied['today_entries'] === [] && DB::$reads === [], 'Assembly lane denial fails closed before reads');

    \AssemblyQcScopeProbe\ContextContract::$context['scope'] = ['part_ids' => [-1]];
    $qcDenied = QcAdapter::getData(0, $user);
    $assert($qcDenied['run_now'] === [] && $qcDenied['pending_qc'] === [] && $qcDenied['error'] === '', 'denied QC scope fails closed before provider reads');

    $composer = (string)file_get_contents(APP_ROOT . '/apps/Shell/Composers/OperatorSurfaceComposer.php');
    $assert(str_contains($composer, 'QcAdapter::getData($days, is_array($user) ? $user : null)'), 'active QC composer passes authenticated operator to adapter');
    $assert(str_contains($composer, 'AssemblyAdapter::getData($days, is_array($user) ? $user : null)'), 'active Assembly composer passes authenticated operator to adapter');
    $assert(str_contains($composer, "in_array(\$requestedRange, ['today', '7d', '30d'], true)"), 'operator date-range override is restricted to existing values');
    $assert(str_contains($composer, "'7d' => 7") && str_contains($composer, "'30d' => 30"), 'operator date-range override maps existing ranges to adapter days');

    $assemblyView = (string)file_get_contents(APP_ROOT . '/apps/Manufacturing/Views/operator/assembly.php');
    $qcView = (string)file_get_contents(APP_ROOT . '/apps/Manufacturing/Views/operator/qc.php');
    foreach ([['Assembly', $assemblyView, '/assembly'], ['QC', $qcView, '/qc']] as [$label, $view, $path]) {
        $assert(str_contains($view, 'name="date_range"') && str_contains($view, 'method="get"'), "{$label} exposes a GET date-range control");
        $assert(str_contains($view, 'value="today"') && str_contains($view, 'value="7d"') && str_contains($view, 'value="30d"'), "{$label} exposes all existing date-range values");
        $assert(str_contains($view, $path), "{$label} date-range control remains on its same-user route");
    }

    $qcAdapter = (string)file_get_contents(APP_ROOT . '/apps/Manufacturing/Services/OperatorLayerAdapters/QcAdapter.php');
    $assert(str_contains($qcAdapter, "QcLeaderDashboardService::build(['date' => \$refDate], \$user)"), 'QC adapter forwards authenticated operator to existing provider contract');

    if ($fail > 0) {
        fwrite(STDERR, "RESULT: {$pass}/" . ($pass + $fail) . " assertions passed; {$fail} failed\n");
        exit(1);
    }
    echo "RESULT: {$pass}/{$pass} assertions passed\n";
}

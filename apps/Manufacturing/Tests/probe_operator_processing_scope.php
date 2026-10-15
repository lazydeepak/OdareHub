<?php
declare(strict_types=1);

namespace App\Core {
    final class Auth {}

    final class DB
    {
        /** @var array<int,string> */
        public static array $reads = [];
        /** @var array<int,string> */
        public static array $mutations = [];

        public static function fetchAll(string $sql, array $params = []): array
        {
            self::$reads[] = $sql;
            if (str_contains($sql, 'FROM products')) {
                return [['id' => 10, 'parts_name' => 'Part X', 'parts_number' => 'PX-10']];
            }
            if (str_contains($sql, 'FROM mfg_part_demands')) {
                return [['id' => 31, 'product_id' => 10, 'demand_date' => '2026-10-15', 'status' => 'adjusted', 'planned_qty' => 5, 'part_name' => 'Part X', 'part_number' => 'PX-10']];
            }
            if (str_contains($sql, 'FROM qc_plans')) {
                return [['id' => 41, 'product_id' => 10, 'plan_date' => '2026-10-15', 'status' => 'System Generated', 'priority' => 'High', 'planned_qty' => 5, 'part_name' => 'Part X', 'part_number' => 'PX-10']];
            }
            return [];
        }

        public static function fetchOne(string $sql, array $params = []): ?array
        {
            self::$reads[] = $sql;
            $recordId = (int)($params[0] ?? 0);
            if (str_contains($sql, 'FROM products')) return [['id' => 10]][0];
            if (str_contains($sql, 'FROM mfg_part_demands')) {
                return ['product_id' => $recordId === 32 ? 11 : 10, 'status' => 'adjusted'];
            }
            if (str_contains($sql, 'FROM qc_plans')) {
                return ['product_id' => $recordId === 42 ? 11 : 10, 'status' => 'System Generated'];
            }
            return null;
        }

        public static function query(string $sql, array $params = []): void
        {
            self::$mutations[] = $sql;
            throw new \RuntimeException('mutation call reached isolated processing probe');
        }
    }
}

namespace ProcessingProbe {
    final class ContextContract
    {
        public function resolveUserContext(?array $user): array
        {
            return \ProcessingProbe::$context;
        }
    }

    final class PolicyContract
    {
        public static ?array $allowedModules = null;
        public function isAppEnabled(string $app): bool { return $app === 'manufacturing'; }
        public function canAccessModule(?array $user, string $module): bool
        {
            return self::$allowedModules === null || in_array($module, self::$allowedModules, true);
        }
    }
}

namespace {
    use Apps\Manufacturing\Services\ProcessingOperationService;
    use App\Core\DB;

    define('APP_ROOT', dirname(__DIR__, 3));
    require_once APP_ROOT . '/apps/Manufacturing/Services/ProcessingOperationService.php';

    class ProcessingProbe
    {
        public static array $context = [];
    }

    function platform_user_context_contract(): object { return new \ProcessingProbe\ContextContract(); }
    function platform_user_access_policy_contract(): object { return new \ProcessingProbe\PolicyContract(); }

    $pass = 0;
    $fail = 0;
    $assert = static function (bool $ok, string $label) use (&$pass, &$fail): void {
        echo ($ok ? 'PASS ' : 'FAIL ') . $label . "\n";
        $ok ? $pass++ : $fail++;
    };
    $user = ['id' => 7, 'email' => 'worker@example.test'];
    ProcessingProbe::$context = [
        'active_assigned_apps' => ['manufacturing'],
        'access_profiles' => ['assembly_operations', 'qc_operations'],
        'duty_codes' => [],
        'dashboard_type' => 'my_work', // resolved assignment evidence
        'authority_role' => 'app_user',
        'account_class' => 'app_user',
        'app_roles' => [],
        'scope' => ['part_ids' => [10]],
    ];

    $focus = ProcessingOperationService::operatorFocus('2026-10-15', $user, 0, 'operator');
    $assert($focus['authorized'] === true && count($focus['products']) === 1 && (int)$focus['products'][0]['id'] === 10, 'GET projection exposes only scoped Part X');
    $assert($focus['assembly_rows'][0]['product_id'] === 10 && $focus['qc_rows'][0]['product_id'] === 10, 'assembly and QC queues are scoped before result delivery');
    $assert($focus['assembly_allowed'] === true && $focus['qc_allowed'] === true, 'GET projection exposes separate Assembly and QC authority');
    $assert($focus['can_mutate'] === true, 'authoritative worker assignment retains mutation eligibility');
    $assert(DB::$mutations === [], 'scoped Processing GET makes no mutation calls');

    \ProcessingProbe\PolicyContract::$allowedModules = ['qc'];
    $qcOnlyFocus = ProcessingOperationService::operatorFocus('2026-10-15', $user, 0, 'operator');
    $assert($qcOnlyFocus['assembly_allowed'] === false && $qcOnlyFocus['qc_allowed'] === true
        && $qcOnlyFocus['assembly_rows'] === [] && count($qcOnlyFocus['qc_rows']) === 1,
        'QC-only authority projects no Assembly lane or rows');
    \ProcessingProbe\PolicyContract::$allowedModules = null;

    \ProcessingProbe\PolicyContract::$allowedModules = ['assembly'];
    $assemblyOnlyFocus = ProcessingOperationService::operatorFocus('2026-10-15', $user, 0, 'operator');
    $assert($assemblyOnlyFocus['assembly_allowed'] === true && $assemblyOnlyFocus['qc_allowed'] === false
        && count($assemblyOnlyFocus['assembly_rows']) === 1 && $assemblyOnlyFocus['qc_rows'] === [],
        'Assembly-only authority projects no QC lane or rows');
    \ProcessingProbe\PolicyContract::$allowedModules = [];
    DB::$reads = [];
    $neitherFocus = ProcessingOperationService::operatorFocus('2026-10-15', $user, 0, 'operator');
    $assert($neitherFocus['authorized'] === false && $neitherFocus['products'] === []
        && $neitherFocus['assembly_rows'] === [] && $neitherFocus['qc_rows'] === [] && DB::$reads === [],
        'Neither-module authority fails closed before Processing reads');
    \ProcessingProbe\PolicyContract::$allowedModules = null;

    DB::$reads = [];
    $outOfScopeCreate = ProcessingOperationService::authorizeOperatorMutation('assembly_create', 0, 11, '', $user);
    $assert($outOfScopeCreate['allowed'] === false && DB::$reads === [], 'out-of-scope assembly create fails before product lookup');
    $outOfScopeQc = ProcessingOperationService::authorizeOperatorMutation('qc_create', 0, 11, '', $user);
    $assert($outOfScopeQc['allowed'] === false && DB::$reads === [], 'out-of-scope QC create fails before product lookup');

    $assemblyCreate = ProcessingOperationService::authorizeOperatorMutation('assembly_create', 0, 10, '', $user);
    $assert($assemblyCreate['allowed'] === true, 'authorized assembly create retains product authority');
    $qcCreate = ProcessingOperationService::authorizeOperatorMutation('qc_create', 0, 10, '', $user);
    $assert($qcCreate['allowed'] === true, 'authorized QC create retains product authority');
    \ProcessingProbe\PolicyContract::$allowedModules = ['qc_plans'];
    $moduleDenied = ProcessingOperationService::authorizeOperatorMutation('assembly_create', 0, 10, '', $user);
    $assert($moduleDenied['allowed'] === false, 'missing assembly module authority denies assembly creation');
    \ProcessingProbe\PolicyContract::$allowedModules = null;
    $assemblyStatus = ProcessingOperationService::authorizeOperatorMutation('assembly_status', 31, 0, 'approved', $user);
    $assert($assemblyStatus['allowed'] === true, 'authorized assembly transition retains adjusted-to-approved semantics');
    $qcStatus = ProcessingOperationService::authorizeOperatorMutation('qc_status', 41, 0, 'verified by qc', $user);
    $assert($qcStatus['allowed'] === true, 'authorized QC transition retains generated-to-verified semantics');
    $mixedAssemblyStatus = ProcessingOperationService::authorizeOperatorMutation('assembly_status', 32, 0, 'approved', $user);
    $mixedQcStatus = ProcessingOperationService::authorizeOperatorMutation('qc_status', 42, 0, 'verified by qc', $user);
    $assert(!$mixedAssemblyStatus['allowed'] && !$mixedQcStatus['allowed'], 'mixed authorized and unauthorized records fail closed by part scope');

    $mutations = static function () use ($user): array {
        return [
            ProcessingOperationService::authorizeOperatorMutation('assembly_create', 0, 10, '', $user),
            ProcessingOperationService::authorizeOperatorMutation('assembly_status', 31, 0, 'approved', $user),
            ProcessingOperationService::authorizeOperatorMutation('qc_create', 0, 10, '', $user),
            ProcessingOperationService::authorizeOperatorMutation('qc_status', 41, 0, 'verified by qc', $user),
        ];
    };
    $readOnlyContext = [
        'active_assigned_apps' => ['manufacturing'],
        'access_profiles' => ['readonly_observer'],
        'duty_codes' => [],
        'dashboard_type' => 'my_work', // resolved assignment evidence
        'authority_role' => 'app_user',
        'account_class' => 'app_user',
        'app_roles' => [],
        'scope' => ['part_ids' => [10]],
    ];
    ProcessingProbe::$context = $readOnlyContext;
    $readOnlyFocus = ProcessingOperationService::operatorFocus('2026-10-15', $user);
    $readOnlyMutations = $mutations();
    $assert($readOnlyFocus['can_mutate'] === false && array_filter($readOnlyMutations, static fn(array $decision): bool => $decision['allowed']) === [], 'readonly_observer denies GET mutation eligibility and all four POST authorities without interaction_profile');

    ProcessingProbe::$context['access_profiles'] = ['materials_readonly'];
    $materialsMutations = $mutations();
    $assert(array_filter($materialsMutations, static fn(array $decision): bool => $decision['allowed']) === [], 'materials_readonly denies all four Processing mutations');

    ProcessingProbe::$context = $readOnlyContext;
    ProcessingProbe::$context['access_profiles'] = ['assembly_operations', 'qc_operations'];
    ProcessingProbe::$context['duty_codes'] = ['read_only'];
    $dutyReadOnlyMutations = $mutations();
    $assert(array_filter($dutyReadOnlyMutations, static fn(array $decision): bool => $decision['allowed']) === [], 'read_only duty evidence overrides worker operation profiles');

    ProcessingProbe::$context = $readOnlyContext;
    ProcessingProbe::$context['access_profiles'] = ['assembly_operations', 'qc_operations'];
    ProcessingProbe::$context['dashboard_type'] = 'display'; // resolved assignment evidence
    $displayMutations = $mutations();
    $assert(array_filter($displayMutations, static fn(array $decision): bool => $decision['allowed']) === [], 'display dashboard authority denies all four Processing mutations');

    ProcessingProbe::$context = $readOnlyContext;
    ProcessingProbe::$context['access_profiles'] = ['assembly_operations', 'readonly_observer'];
    $mixedProfileMutations = $mutations();
    $assert(array_filter($mixedProfileMutations, static fn(array $decision): bool => $decision['allowed']) === [], 'mixed worker and read-only evidence fails closed');

    ProcessingProbe::$context = $readOnlyContext;
    unset(ProcessingProbe::$context['access_profiles']);
    $missingProfileMutations = $mutations();
    $assert(array_filter($missingProfileMutations, static fn(array $decision): bool => $decision['allowed']) === [], 'missing profile evidence cannot grant mutation');

    ProcessingProbe::$context['dashboard_type'] = 'assembly_leader'; // resolved assignment evidence
    ProcessingProbe::$context['app_roles'] = ['assembly_leader'];
    $missingProfileWithPositiveHints = $mutations();
    $assert(array_filter($missingProfileWithPositiveHints, static fn(array $decision): bool => $decision['allowed']) === [], 'missing profiles cannot be overridden by dashboard or app-role hints');

    ProcessingProbe::$context = $readOnlyContext;
    ProcessingProbe::$context['access_profiles'] = 'assembly_operations';
    $malformedProfileMutations = $mutations();
    $assert(array_filter($malformedProfileMutations, static fn(array $decision): bool => $decision['allowed']) === [], 'malformed profile evidence cannot grant mutation');

    ProcessingProbe::$context = $readOnlyContext;
    ProcessingProbe::$context['access_profiles'] = ['assembly_operations'];
    \ProcessingProbe\PolicyContract::$allowedModules = ['assembly'];
    $assemblyWorkerMutations = $mutations();
    $assert($assemblyWorkerMutations[0]['allowed'] === true && $assemblyWorkerMutations[1]['allowed'] === true
        && !$assemblyWorkerMutations[2]['allowed'] && !$assemblyWorkerMutations[3]['allowed'],
        'legitimate Assembly worker retains only Assembly mutation authority');

    ProcessingProbe::$context['access_profiles'] = ['qc_operations'];
    \ProcessingProbe\PolicyContract::$allowedModules = ['qc'];
    $qcWorkerMutations = $mutations();
    $assert(!$qcWorkerMutations[0]['allowed'] && !$qcWorkerMutations[1]['allowed']
        && $qcWorkerMutations[2]['allowed'] === true && $qcWorkerMutations[3]['allowed'] === true,
        'legitimate QC worker retains only QC mutation authority');
    \ProcessingProbe\PolicyContract::$allowedModules = null;

    ProcessingProbe::$context = [
        'active_assigned_apps' => ['manufacturing'],
        'access_profiles' => ['assembly_operations', 'qc_operations'],
        'duty_codes' => [],
        'dashboard_type' => 'my_work', // resolved assignment evidence
        'authority_role' => 'app_user',
        'account_class' => 'app_user',
        'app_roles' => [],
        'scope' => ['part_ids' => ['10junk']],
    ];
    $malformed = ProcessingOperationService::operatorFocus('2026-10-15', $user);
    $assert($malformed['authorized'] === false && $malformed['products'] === [], 'malformed scope fails closed');
    ProcessingProbe::$context['scope'] = ['part_ids' => [-1, 10]];
    $mixed = ProcessingOperationService::operatorFocus('2026-10-15', $user);
    $assert($mixed['authorized'] === false && $mixed['products'] === [], 'mixed denied scope fails closed');

    $routes = file_get_contents(APP_ROOT . '/apps/Shell/routes.php');
    $assert(is_string($routes) && substr_count($routes, "ProcessingOperationService::authorizeOperatorMutation") >= 4, 'all four operator POST handlers invoke Manufacturing authority');
    $assert(is_string($routes) && strpos($routes, "ProcessingOperationService::authorizeOperatorMutation('assembly_create'") < strpos($routes, 'DemandEngineService::ensureSchema()'), 'assembly creation authorizes before schema initialization');
    $assemblyRouteStart = is_string($routes) ? strpos($routes, "\$router->post('/u/processing/assembly/create'") : false;
    $assemblyAuthority = is_string($routes) ? strpos($routes, "ProcessingOperationService::authorizeOperatorMutation('assembly_create'", (int)$assemblyRouteStart) : false;
    $assemblyMutation = is_string($routes) ? strpos($routes, 'mfg_part_demands', (int)$assemblyRouteStart) : false;
    $assert(is_int($assemblyRouteStart) && is_int($assemblyAuthority) && is_int($assemblyMutation) && $assemblyAuthority < $assemblyMutation, 'assembly creation authority precedes its mutation path');
    $assert(is_string($routes) && (strpos($routes, "ProcessingOperationService::authorizeOperatorMutation('qc_create'") !== false)
        && strpos($routes, "ProcessingOperationService::authorizeOperatorMutation('qc_create'") < strpos($routes, 'INSERT INTO qc_plans'),
        'QC creation authority precedes its mutation path');
    $assert(is_string($routes) && !str_contains($routes, 'ProcessingOperationService::build('), 'operator routes do not use unrelated admin projection');

    $view = file_get_contents(APP_ROOT . '/apps/Shell/Views/operator/processing.php');
    $assert(is_string($view) && str_contains($view, '$processingAssemblyAllowed') && str_contains($view, '$processingQcAllowed'), 'Processing view receives separate lane authority flags');
    $assert(is_string($view) && substr_count($view, '$processingAssemblyAllowed && $processingCanMutate') >= 2
        && substr_count($view, '$processingQcAllowed && $processingCanMutate') >= 3,
        'Processing view suppresses unauthorized lane actions and forms');

    if ($fail > 0) {
        fwrite(STDERR, "RESULT: {$pass}/" . ($pass + $fail) . " assertions passed; {$fail} failed\n");
        exit(1);
    }
    echo "RESULT: {$pass}/{$pass} assertions passed\n";
}

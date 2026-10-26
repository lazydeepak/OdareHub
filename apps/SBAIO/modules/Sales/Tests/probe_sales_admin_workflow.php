<?php
declare(strict_types=1);

/**
 * Database-free OH-AUTO3-SBAIO-003 Sales contract probe.
 *
 * This probe uses in-memory Auth/DB doubles and source-contract checks. It
 * never loads application bootstrap and never connects to a database.
 */

namespace App\Core {
    final class Auth
    {
        public static ?array $user = null;
        public static array $calls = [];

        public static function requireAppAccess(string $appKey): void
        {
            self::$calls[] = ['method' => 'requireAppAccess', 'app' => $appKey];
            if (self::$user === null) {
                throw new \RuntimeException('unauthenticated');
            }
        }

        public static function user(): ?array
        {
            return self::$user;
        }

        public static function requireCsrf(string $token): void
        {
            self::$calls[] = ['method' => 'requireCsrf', 'token' => $token];
            if ($token !== 'valid-csrf') {
                throw new \RuntimeException('csrf denied');
            }
        }
    }

    final class DB
    {
        public static array $queries = [];
        public static array $rows = [];
        public static array $fetchOneRows = [];
        public static array $fetchAllRows = [];
        public static bool $throw = false;

        public static function fetchOne(string $sql, array $params = []): ?array
        {
            if (self::$throw) {
                throw new \RuntimeException('report read failed');
            }
            self::$queries[] = ['method' => 'fetchOne', 'sql' => $sql, 'params' => $params];
            $row = array_shift(self::$fetchOneRows);
            return is_array($row) ? $row : null;
        }

        public static function fetchAll(string $sql, array $params = []): array
        {
            if (self::$throw) {
                throw new \RuntimeException('report read failed');
            }
            self::$queries[] = ['method' => 'fetchAll', 'sql' => $sql, 'params' => $params];
            if (self::$fetchAllRows !== []) {
                return (array)array_shift(self::$fetchAllRows);
            }
            return self::$rows;
        }

        public static function query(string $sql, array $params = []): void
        {
            self::$queries[] = ['method' => 'query', 'sql' => $sql, 'params' => $params];
        }
    }
}

namespace {
    function t(string $key): string
    {
        return [
            'sbaio.sales.admin_required' => 'Administrator access is required.',
            'sbaio.sales.invalid' => 'Please correct the sales details.',
            'sbaio.sales.sale_ref_required' => 'A sale reference is required.',
            'sbaio.sales.sale_ref_invalid' => 'Enter a valid sale reference.',
            'sbaio.sales.sale_ref_too_long' => 'The sale reference is too long.',
            'sbaio.sales.customer_name_invalid' => 'Enter a valid customer name.',
            'sbaio.sales.customer_name_too_long' => 'The customer name is too long.',
            'sbaio.sales.amount_required' => 'An amount is required.',
            'sbaio.sales.amount_invalid' => 'Enter a valid amount.',
            'sbaio.sales.status_invalid' => 'Select a supported sales status.',
            'sbaio.sales.sale_date_invalid' => 'Enter a valid calendar date.',
        ][$key] ?? $key;
    }

    require_once __DIR__ . '/../Services/SalesService.php';
    require_once __DIR__ . '/../Controllers/SalesController.php';

    use App\Core\Auth;
    use App\Core\DB;
    use Plugins\Sales\Controllers\SalesController;
    use Plugins\Sales\Services\SalesService;

    $assertions = 0;
    $failures = 0;
    $assert = static function (bool $condition, string $message) use (&$assertions, &$failures): void {
        $assertions++;
        if (!$condition) {
            $failures++;
            fwrite(STDERR, "FAIL: {$message}\n");
        }
    };

    if (($argv[1] ?? '') === 'denied') {
        Auth::$user = ['id' => 7, 'authority_role' => 'app_user', 'role' => 'user'];
        SalesController::requireAdministrator();
        exit(2);
    }

    $serviceSource = file_get_contents(__DIR__ . '/../Services/SalesService.php') ?: '';
    $controllerSource = file_get_contents(__DIR__ . '/../Controllers/SalesController.php') ?: '';
    $routesSource = file_get_contents(__DIR__ . '/../routes.php') ?: '';
    $reportSource = file_get_contents(__DIR__ . '/../Views/report.php') ?: '';
    $exportSource = file_get_contents(__DIR__ . '/../Views/export.php') ?: '';
    $registrySource = file_get_contents(dirname(__DIR__, 3) . '/Services/ModuleReportRegistryService.php') ?: '';

    // Existing administrator contract is exercised for accepted roles and
    // denied in a child process because the production guard terminates.
    Auth::$user = ['id' => 1, 'authority_role' => 'platform_admin', 'role' => 'admin'];
    Auth::$calls = [];
    SalesController::requireAdministrator();
    $assert(Auth::$calls === [['method' => 'requireAppAccess', 'app' => 'sbaio']], 'platform administrator passes existing app access gate');

    Auth::$user = ['id' => 2, 'authority_role' => 'app_admin', 'role' => 'user'];
    Auth::$calls = [];
    SalesController::requireAdministrator();
    $assert(Auth::$calls === [['method' => 'requireAppAccess', 'app' => 'sbaio']], 'app administrator passes existing app access gate');

    $command = [PHP_BINARY, __FILE__, 'denied'];
    $descriptorSpec = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($command, $descriptorSpec, $pipes);
    $status = -1;
    if (is_resource($process)) {
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
    }
    $assert($status === 0, 'ordinary app user is denied by administrator guard');

    // Scalar, length, and optional-field validation.
    $valid = SalesService::validateCreateInput([
        'sale_ref' => '  SALE-001  ',
        'customer_name' => null,
        'amount' => '-00012.5',
        'sale_status' => '',
        'sale_date' => '2024-02-29',
    ]);
    $assert($valid['valid'], 'valid scalar sales input is accepted');
    $assert($valid['values'] === [
        'sale_ref' => 'SALE-001',
        'customer_name' => '',
        'amount' => '-12.50',
        'sale_status' => 'open',
        'sale_date' => '2024-02-29',
    ], 'valid input is normalized without changing decimal sign or scale');

    $missing = SalesService::validateCreateInput([]);
    $assert(!$missing['valid'] && in_array('sale_ref_required', $missing['errors'], true), 'missing sale reference is rejected');
    $assert(in_array('amount_required', $missing['errors'], true), 'missing amount is rejected');

    $arrayInput = SalesService::validateCreateInput([
        'sale_ref' => ['SALE-ARRAY'],
        'customer_name' => 'Acme',
        'amount' => ['10.00'],
        'sale_status' => 'open',
        'sale_date' => '2024-01-01',
    ]);
    $assert(!$arrayInput['valid'] && in_array('sale_ref_invalid', $arrayInput['errors'], true), 'array-shaped reference is rejected');
    $assert(in_array('amount_invalid', $arrayInput['errors'], true), 'array-shaped amount is rejected');

    $longReference = SalesService::validateCreateInput([
        'sale_ref' => str_repeat('R', 81),
        'amount' => '1.00',
    ]);
    $assert(in_array('sale_ref_too_long', $longReference['errors'], true), 'oversized reference is rejected');

    $longCustomer = SalesService::validateCreateInput([
        'sale_ref' => 'SALE-002',
        'customer_name' => str_repeat('C', 191),
        'amount' => '1.00',
    ]);
    $assert(in_array('customer_name_too_long', $longCustomer['errors'], true), 'oversized customer name is rejected');

    // Exact DECIMAL(12,2) string boundaries. No float is used by the service.
    foreach (['9999999999.99', '0', '0.00', '-1.25', '.5', '+12.3'] as $amount) {
        $result = SalesService::validateCreateInput(['sale_ref' => 'SALE-DEC', 'amount' => $amount]);
        $assert($result['valid'], "decimal amount {$amount} is accepted");
    }
    foreach (['99999999999.99', '1.234', '1e2', 'INF', 'NaN', '12,50', '1.'] as $amount) {
        $result = SalesService::validateCreateInput(['sale_ref' => 'SALE-BAD', 'amount' => $amount]);
        $assert(!$result['valid'] && in_array('amount_invalid', $result['errors'], true), "unsupported amount {$amount} is rejected");
    }
    $assert(!str_contains($serviceSource, 'decimalOrZero'), 'silent decimal-to-zero coercion is removed');
    $assert(!str_contains($serviceSource, '(float)'), 'Sales validation contains no float conversion');
    $assert(str_contains($serviceSource, "\$values['amount']"), 'persistence receives normalized decimal string');

    // Every established status remains valid; unsupported values fail closed.
    foreach (['draft', 'open', 'pending', 'won', 'completed', 'closed'] as $statusValue) {
        $result = SalesService::validateCreateInput(['sale_ref' => 'SALE-STATUS', 'amount' => '1.00', 'sale_status' => $statusValue]);
        $assert($result['valid'], "established status {$statusValue} remains valid");
    }
    $badStatus = SalesService::validateCreateInput(['sale_ref' => 'SALE-BAD-STATUS', 'amount' => '1.00', 'sale_status' => 'cancelled']);
    $assert(!$badStatus['valid'] && in_array('status_invalid', $badStatus['errors'], true), 'unsupported status is rejected');

    // Calendar validation is strict and timezone-free.
    $goodDate = SalesService::validateCreateInput(['sale_ref' => 'SALE-DATE', 'amount' => '1.00', 'sale_date' => '2024-02-29']);
    $badDate = SalesService::validateCreateInput(['sale_ref' => 'SALE-DATE', 'amount' => '1.00', 'sale_date' => '2023-02-29']);
    $assert($goodDate['valid'], 'valid leap-year calendar date is accepted');
    $assert(!$badDate['valid'] && in_array('sale_date_invalid', $badDate['errors'], true), 'impossible calendar date is rejected');

    // Persistence receives strings for valid decimals and is untouched on failure.
    DB::$queries = [];
    SalesService::create($valid['values']);
    $assert(count(DB::$queries) === 1 && DB::$queries[0]['method'] === 'query', 'valid create performs one insert');
    $assert(DB::$queries[0]['params'][2] === '-12.50' && is_string(DB::$queries[0]['params'][2]), 'insert receives exact decimal string');

    DB::$queries = [];
    try {
        SalesService::create(['sale_ref' => 'SALE-INVALID', 'amount' => '1e2']);
        $assert(false, 'invalid create unexpectedly succeeded');
    } catch (\InvalidArgumentException) {
        $assert(DB::$queries === [], 'invalid create performs no persistence');
    }

    // CSRF remains before service dispatch for controller create.
    Auth::$user = ['id' => 1, 'authority_role' => 'platform_admin', 'role' => 'admin'];
    Auth::$calls = [];
    $_POST = ['csrf' => 'invalid-csrf'];
    try {
        SalesController::create();
        $assert(false, 'invalid CSRF unexpectedly succeeded');
    } catch (\RuntimeException $e) {
        $assert($e->getMessage() === 'csrf denied', 'invalid CSRF is rejected');
        $assert(DB::$queries === [], 'CSRF failure performs no persistence');
    }

    // List/report/export and lifecycle contracts remain bounded and guarded.
    DB::$queries = [];
    DB::$fetchOneRows = [];
    DB::$fetchAllRows = [];
    DB::$throw = false;
    DB::$rows = [['id' => 9, 'sale_ref' => 'NEWEST']];
    $rows = SalesService::recent(1000);
    $assert($rows === DB::$rows, 'recent returns repository rows unchanged');
    $assert(str_contains(DB::$queries[0]['sql'], 'ORDER BY id DESC LIMIT 25'), 'recent remains deterministically bounded');

    // Search accepts historical records, combines all supported filters, and
    // binds every caller-provided value rather than concatenating input.
    DB::$queries = [];
    DB::$fetchAllRows = [array_merge(
        [['id' => 100, 'sale_ref' => 'SALE-HIST', 'customer_name' => 'Acme', 'amount' => '10.00', 'sale_status' => 'completed', 'sale_date' => '2022-01-15']],
        array_fill(0, 50, ['id' => 99, 'sale_ref' => 'OTHER', 'customer_name' => 'Other', 'amount' => '1.00', 'sale_status' => 'open', 'sale_date' => '2022-01-14'])
    )];
    $search = SalesService::search([
        'sale_ref' => ' SALE-HIST ',
        'status' => 'COMPLETED',
        'from' => '2022-01-01',
        'to' => '2022-01-31',
    ]);
    $assert($search['valid'] === true && $search['available'] === true, 'valid search reports a usable result');
    $assert(count($search['rows']) === 50 && $search['truncated'] === true && $search['limit'] === 50, 'search returns at most 50 rows and indicates truncation');
    $assert($search['filters'] === ['sale_ref' => 'SALE-HIST', 'status' => 'completed', 'from' => '2022-01-01', 'to' => '2022-01-31'], 'search normalizes supported filters deterministically');
    $assert(str_contains(DB::$queries[0]['sql'], 'sale_ref = ?') && str_contains(DB::$queries[0]['sql'], 'sale_status = ?') && str_contains(DB::$queries[0]['sql'], 'sale_date >= ?') && str_contains(DB::$queries[0]['sql'], 'sale_date <= ?'), 'combined search uses exact and inclusive parameterized predicates');
    $assert(DB::$queries[0]['params'] === ['SALE-HIST', 'completed', '2022-01-01', '2022-01-31'], 'search binds normalized filter values in predicate order');
    $assert(str_contains(DB::$queries[0]['sql'], 'ORDER BY sale_date DESC, id DESC LIMIT 51'), 'search uses deterministic ordering and one-row overflow bound');
    $assert($search['rows'][0]['sale_date'] === '2022-01-15', 'search can retrieve a historical sale outside the recent listing window');

    DB::$queries = [];
    DB::$fetchAllRows = [[]];
    $statusOnly = SalesService::search(['status' => 'won']);
    $assert($statusOnly['filters']['status'] === 'won' && DB::$queries[0]['params'] === ['won'], 'status-only search remains supported');
    $assert(str_contains(DB::$queries[0]['sql'], 'sale_status = ?') && !str_contains(DB::$queries[0]['sql'], 'sale_ref = ?'), 'search emits only predicates for supplied filters');

    foreach ([
        ['status' => 'cancelled'],
        ['from' => '2023-02-29'],
        ['from' => '2024-03-01', 'to' => '2024-02-01'],
        ['sale_ref' => str_repeat('R', 81)],
        ['sale_ref' => ['SALE-ARRAY']],
        ['unexpected' => 'value'],
    ] as $invalidFilters) {
        DB::$queries = [];
        try {
            SalesService::search($invalidFilters);
            $assert(false, 'invalid search filters unexpectedly succeeded');
        } catch (\InvalidArgumentException) {
            $assert(DB::$queries === [], 'invalid search filters perform no database read');
        }
    }
    $assert(str_contains($controllerSource, 'self::requireAdministrator();') && strpos($controllerSource, 'self::requireAdministrator();') < strpos($controllerSource, 'SalesService::search'), 'Sales search authorization precedes service/database access');

    DB::$queries = [];
    DB::$fetchOneRows = [[
        'total' => 4,
        'amount' => '125.50',
        'month' => 2,
        'month_amount' => '75.50',
        'closed' => 1,
        'open' => 2,
    ]];
    DB::$fetchAllRows = [
        [['sale_status' => 'open', 'n' => 2, 'amount' => '50.00']],
        [['customer_name' => 'Acme', 'n' => 2, 'amount' => '75.50']],
        [['id' => 9, 'sale_ref' => 'NEWEST', 'sale_date' => '2024-01-01', 'customer_name' => 'Acme', 'amount' => '25.50', 'sale_status' => 'open']],
    ];
    $reportData = SalesService::reportData();
    $assert($reportData['summary']['total'] === 4 && $reportData['summary']['amount'] === '125.50', 'report summary output preserves prepared aggregate values');
    $assert($reportData['byStatus'][0]['sale_status'] === 'open' && $reportData['topCustomers'][0]['customer_name'] === 'Acme' && $reportData['recent'][0]['id'] === 9, 'report collections preserve status/customer/recent output shape');
    $assert(count(DB::$queries) === 4 && DB::$queries[0]['method'] === 'fetchOne', 'report performs exactly the existing four reads in order');
    $assert(str_contains(DB::$queries[0]['sql'], 'DATE_SUB(CURDATE(), INTERVAL 90 DAY)') && str_contains(DB::$queries[1]['sql'], 'GROUP BY sale_status ORDER BY n DESC'), 'report summary/status predicates and ordering are preserved');
    $assert(str_contains(DB::$queries[2]['sql'], 'GROUP BY customer_name ORDER BY amount DESC LIMIT 15') && str_contains(DB::$queries[3]['sql'], 'ORDER BY sale_date DESC, id DESC LIMIT 25'), 'report customer/recent bounds and ordering are preserved');
    DB::$throw = true;
    $unavailableData = SalesService::reportData();
    $assert($unavailableData['summary']['total'] === 0 && $unavailableData['byStatus'] === [] && $unavailableData['topCustomers'] === [] && $unavailableData['recent'] === [], 'report read failure preserves existing default output behavior');
    DB::$throw = false;

    $assert(substr_count($routesSource, 'SalesController::requireAdministrator()') === 4, 'all four Sales routes require administrator authority');
    $assert(str_contains($routesSource, 'SalesController::report($view, $report)'), 'report route supplies admitted report data through the controller');
    $assert(strpos($routesSource, 'SalesController::requireAdministrator()') < strpos($routesSource, 'ModuleReportRegistryService::findActiveReport') && strpos($routesSource, 'ModuleReportRegistryService::findActiveReport') < strpos($routesSource, 'SalesController::report'), 'report authorization and admission precede data preparation');
    $assert(str_contains($controllerSource, 'Auth::requireCsrf'), 'create controller retains CSRF enforcement');
    $assert(str_contains($controllerSource, "'reportData' => SalesService::reportData()"), 'controller supplies prepared Sales report data');
    $assert(str_contains($serviceSource, 'public static function reportData'), 'Sales service owns the report read contract');
    $assert(!str_contains($reportSource, 'DB::') && !str_contains($reportSource, 'sbaio_sales') && !str_contains($reportSource, 'ModuleReportRegistryService'), 'report view performs no database or registry operations');
    $assert(str_contains($exportSource, 'sbaio.sales.overview'), 'export remains bound to the existing report key');
    $assert(str_contains($registrySource, "'sbaio.sales.overview'"), 'report registry retains Sales source');

    foreach (['en.php', 'ja.php', 'ne.php'] as $locale) {
        $localeValues = require __DIR__ . '/../Resources/lang/' . $locale;
        foreach ([
            'sbaio.sales.admin_required',
            'sbaio.sales.sale_ref_required',
            'sbaio.sales.amount_invalid',
            'sbaio.sales.status_invalid',
            'sbaio.sales.sale_date_invalid',
            'sbaio.sales.search_title',
            'sbaio.sales.search_reference',
            'sbaio.sales.search_status',
            'sbaio.sales.search_from',
            'sbaio.sales.search_to',
            'sbaio.sales.search_submit',
            'sbaio.sales.search_invalid',
            'sbaio.sales.search_unavailable',
            'sbaio.sales.search_no_results',
            'sbaio.sales.search_limited',
            'sbaio.sales.search_customer',
            'sbaio.sales.search_sale_date',
            'sbaio.sales.search_amount',
        ] as $key) {
            $assert(is_array($localeValues) && trim((string)($localeValues[$key] ?? '')) !== '', "{$locale} localizes {$key}");
        }
    }

    $assert(!str_contains($routesSource . $controllerSource . $serviceSource, 'related_staff_id'), 'Sales has no staff-reference coupling');
    $assert(!str_contains($routesSource . $controllerSource . $serviceSource, 'Leave'), 'Sales has no Leave coupling');
    $assert(str_contains($controllerSource, "['platform_admin', 'app_admin']"), 'Sales uses the accepted administrator role contract');

    echo "Sales admin workflow probe: {$assertions} assertions, {$failures} failures\n";
    exit($failures === 0 ? 0 : 1);
}

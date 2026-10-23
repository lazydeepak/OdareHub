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

        public static function fetchAll(string $sql, array $params = []): array
        {
            self::$queries[] = ['method' => 'fetchAll', 'sql' => $sql, 'params' => $params];
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
    DB::$rows = [['id' => 9, 'sale_ref' => 'NEWEST']];
    $rows = SalesService::recent(1000);
    $assert($rows === DB::$rows, 'recent returns repository rows unchanged');
    $assert(str_contains(DB::$queries[0]['sql'], 'ORDER BY id DESC LIMIT 25'), 'recent remains deterministically bounded');
    $assert(substr_count($routesSource, 'SalesController::requireAdministrator()') === 4, 'all four Sales routes require administrator authority');
    $assert(str_contains($controllerSource, 'Auth::requireCsrf'), 'create controller retains CSRF enforcement');
    $assert(str_contains($reportSource, 'sbaio_sales') && str_contains($reportSource, 'GROUP BY sale_status'), 'report remains Sales-owned and status-grouped');
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

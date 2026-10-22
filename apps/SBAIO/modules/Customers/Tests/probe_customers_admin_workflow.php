<?php
declare(strict_types=1);

/**
 * Database-free OH-AUTO3-004 Customers contract probe.
 *
 * This probe uses in-memory Auth/DB/view doubles only. It never loads the
 * application bootstrap and never connects to a database.
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
            'sbaio.customers.admin_required' => 'Administrator access is required.',
            'sbaio.customers.required' => 'Customer name is required.',
            'sbaio.customers.invalid' => 'Please correct the customer details.',
            'sbaio.customers.email_invalid' => 'Enter a valid email address.',
            'sbaio.customers.status_invalid' => 'Only active customers can be created here.',
            'sbaio.customers.created' => 'Customer created.',
        ][$key] ?? $key;
    }

    require_once __DIR__ . '/../Services/CustomersService.php';
    require_once __DIR__ . '/../Controllers/CustomersController.php';

    use App\Core\Auth;
    use App\Core\DB;
    use Plugins\Customers\Controllers\CustomersController;
    use Plugins\Customers\Services\CustomersService;

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
        CustomersController::requireAdministrator();
        exit(2);
    }

    // Validation is pure and must not touch the fake database.
    $beforeQueries = count(DB::$queries);
    $missing = CustomersService::validateCreateInput([]);
    $assert(!$missing['valid'] && in_array('required', $missing['errors'], true), 'missing customer name is rejected');
    $assert(count(DB::$queries) === $beforeQueries, 'missing input performs no persistence');

    $badEmail = CustomersService::validateCreateInput(['customer_name' => 'Acme', 'email' => 'not-an-email']);
    $assert(!$badEmail['valid'] && in_array('email_invalid', $badEmail['errors'], true), 'invalid email is rejected');

    $existingStatus = CustomersService::validateCreateInput(['customer_name' => 'Acme', 'status' => 'prospect']);
    $assert($existingStatus['valid'] && $existingStatus['values']['status'] === 'prospect', 'existing arbitrary status values remain supported');

    $badStatus = CustomersService::validateCreateInput(['customer_name' => 'Acme', 'status' => str_repeat('x', 41)]);
    $assert(!$badStatus['valid'] && in_array('status_too_long', $badStatus['errors'], true), 'overlong status is rejected');

    $valid = CustomersService::validateCreateInput([
        'customer_name' => '  Acme  ',
        'contact_name' => '  Jane  ',
        'email' => 'jane@example.test',
        'phone' => '  +1 555 0100 ',
        'status' => '',
    ]);
    $assert($valid['valid'], 'valid customer input is accepted');
    $assert($valid['values'] === [
        'customer_name' => 'Acme',
        'contact_name' => 'Jane',
        'email' => 'jane@example.test',
        'phone' => '+1 555 0100',
        'status' => 'active',
    ], 'valid customer input is normalized with active default');

    // Existing persistence contract is invoked only with normalized values.
    DB::$queries = [];
    CustomersService::create($valid['values']);
    $assert(count(DB::$queries) === 1 && DB::$queries[0]['method'] === 'query', 'valid create invokes one insert query');
    $assert(DB::$queries[0]['params'] === ['Acme', 'Jane', 'jane@example.test', '+1 555 0100', 'active'], 'insert receives normalized existing fields');

    DB::$queries = [];
    try {
        CustomersService::create(['customer_name' => '', 'status' => 'active']);
        $assert(false, 'invalid create throws before persistence');
    } catch (\InvalidArgumentException) {
        $assert(count(DB::$queries) === 0, 'invalid create performs no persistence');
    }

    // Admin authority is server-side and retains the existing app-access gate.
    Auth::$user = ['id' => 1, 'authority_role' => 'platform_admin', 'role' => 'admin'];
    Auth::$calls = [];
    CustomersController::requireAdministrator();
    $assert(Auth::$calls === [['method' => 'requireAppAccess', 'app' => 'sbaio']], 'platform admin passes existing SBAIO access gate');

    Auth::$user = ['id' => 2, 'authority_role' => 'app_admin', 'role' => 'user'];
    Auth::$calls = [];
    CustomersController::requireAdministrator();
    $assert(Auth::$calls === [['method' => 'requireAppAccess', 'app' => 'sbaio']], 'app admin passes existing SBAIO access gate');

    // List remains deterministic and bounded at the existing 25-record contract.
    DB::$queries = [];
    DB::$rows = [['id' => 9, 'customer_name' => 'Newest']];
    $rows = CustomersService::recent(1000);
    $assert($rows === DB::$rows, 'recent returns repository rows unchanged');
    $assert(str_contains(DB::$queries[0]['sql'], 'ORDER BY id DESC LIMIT 25'), 'recent uses deterministic bounded ordering');

    $source = file_get_contents(__DIR__ . '/../Views/index.php') ?: '';
    $routes = file_get_contents(__DIR__ . '/../routes.php') ?: '';
    $assert(str_contains($source, 'e((string)($row[\'customer_name\'] ?? \'\'))'), 'list customer values are escaped');
    $assert(substr_count($routes, 'CustomersController::requireAdministrator()') === 4, 'all customer record routes require administrator authority');
    $assert(!str_contains($source . $routes, 'staff_id') && !str_contains($source . $routes, 'leave_status'), 'Customers surface has no Staff or Leave coupling');
    foreach (['en.php', 'ja.php', 'ne.php'] as $locale) {
        $localeSource = file_get_contents(__DIR__ . '/../Resources/lang/' . $locale) ?: '';
        $assert(str_contains($localeSource, 'sbaio.customers.admin_required'), $locale . ' has administrator localization');
        $assert(str_contains($localeSource, 'sbaio.customers.customer_name'), $locale . ' has customer field localization');
    }

    $command = [PHP_BINARY, __FILE__, 'denied'];
    $descriptorSpec = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($command, $descriptorSpec, $pipes);
    $deniedOutput = '';
    $status = -1;
    if (is_resource($process)) {
        $deniedOutput = (string)stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
    }
    $assert($status === 0, 'non-admin controller authorization exits with denial response');

    echo "Customers admin workflow probe: {$assertions} assertions, {$failures} failures\n";
    exit($failures === 0 ? 0 : 1);
}

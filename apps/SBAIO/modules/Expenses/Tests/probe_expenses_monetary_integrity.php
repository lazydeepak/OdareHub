<?php
declare(strict_types=1);

/**
 * Database-free Expense monetary-integrity probe.
 *
 * This probe uses an in-memory DB double and never loads application bootstrap
 * or connects to an installed/configured database.
 */
namespace App\Core {
    final class DB
    {
        public static array $queries = [];

        public static function query(string $sql, array $params = []): void
        {
            self::$queries[] = ['sql' => $sql, 'params' => $params];
        }
    }
}

namespace {
    require_once __DIR__ . '/../Services/ExpensesService.php';

    use App\Core\DB;
    use Plugins\Expenses\Services\ExpensesService;

    $assertions = 0;
    $failures = 0;
    $assert = static function (bool $condition, string $message) use (&$assertions, &$failures): void {
        $assertions++;
        if (!$condition) {
            $failures++;
            fwrite(STDERR, "FAIL: {$message}\n");
        }
    };

    $serviceSource = file_get_contents(__DIR__ . '/../Services/ExpensesService.php') ?: '';
    $controllerSource = file_get_contents(__DIR__ . '/../Controllers/ExpensesController.php') ?: '';

    foreach ([
        ['9999999999.99', '9999999999.99'],
        ['0', '0.00'],
        ['0.00', '0.00'],
        ['-1.25', '-1.25'],
        ['.5', '0.50'],
        ['+12.3', '12.30'],
    ] as [$input, $expected]) {
        $assert(ExpensesService::validateAmount($input) === $expected, "decimal amount {$input} is normalized without float conversion");
    }

    foreach (['99999999999.99', '1.234', '1e2', 'INF', 'NaN', '12,50', '1.', '', null, ['10.00'], true, 10] as $input) {
        $assert(ExpensesService::validateAmount($input) === null, 'unsupported expense amount is rejected before persistence');
    }

    $assert(!str_contains($serviceSource, 'decimalOrZero'), 'silent decimal-to-zero coercion is removed');
    $assert(!str_contains($serviceSource, '(float)'), 'Expense monetary validation contains no float conversion');
    $assert(str_contains($serviceSource, 'DECIMAL(12,2)') || str_contains(file_get_contents(__DIR__ . '/../install.php') ?: '', 'DECIMAL(12,2)'), 'validation is aligned with the existing DECIMAL(12,2) schema');
    $assert(str_contains($controllerSource, 'ExpensesService::validateAmount') && strpos($controllerSource, 'ExpensesService::validateAmount') < strpos($controllerSource, 'ExpensesService::create($_POST)'), 'controller validates amount before persistence dispatch');

    DB::$queries = [];
    ExpensesService::create([
        'expense_ref' => 'EXP-001',
        'category_name' => 'Travel',
        'amount' => '1234.50',
        'expense_status' => 'submitted',
        'expense_date' => '2026-10-26',
    ]);
    $assert(count(DB::$queries) === 1, 'valid expense performs one insert');
    $assert(DB::$queries[0]['params'][2] === '1234.50' && is_string(DB::$queries[0]['params'][2]), 'insert receives the exact normalized decimal string');

    foreach (['12.345', '1e2', 'not-money', ['10.00']] as $invalid) {
        DB::$queries = [];
        try {
            ExpensesService::create(['expense_ref' => 'EXP-BAD', 'amount' => $invalid]);
            $assert(false, 'invalid expense amount unexpectedly reached create success');
        } catch (\InvalidArgumentException) {
            $assert(DB::$queries === [], 'invalid expense amount performs no SQL');
        }
    }

    $assert(str_contains($controllerSource, "Auth::requireCsrf") && str_contains($controllerSource, 'expense_ref'), 'existing CSRF and reference validation remain in the controller');

    echo 'Expenses monetary integrity probe: ' . $assertions . ' assertions, ' . $failures . " failures\n";
    exit($failures === 0 ? 0 : 1);
}

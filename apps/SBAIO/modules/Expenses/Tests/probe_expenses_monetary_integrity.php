<?php
declare(strict_types=1);

/**
 * Database-free Expense monetary-integrity probe.
 *
 * This probe uses an in-memory DB double and never loads application bootstrap
 * or connects to an installed/configured database.
 */
namespace App\Core {
    final class ExpenseConnectionDouble
    {
        public int|string|null $insert_id = 0;
        public bool $beginResult = true;
        public bool $commitResult = true;
        public bool $rollbackResult = true;

        public function begin_transaction(): bool
        {
            DB::$transactionEvents[] = 'begin';
            return $this->beginResult;
        }

        public function commit(): bool
        {
            DB::$transactionEvents[] = 'commit';
            return $this->commitResult;
        }

        public function rollback(): bool
        {
            DB::$transactionEvents[] = 'rollback';
            return $this->rollbackResult;
        }
    }

    final class DB
    {
        public static array $queries = [];
        public static array $transactionEvents = [];
        public static ?ExpenseConnectionDouble $connection = null;
        public static bool $queryShouldFail = false;

        public static function conn(): ExpenseConnectionDouble
        {
            return self::$connection ??= new ExpenseConnectionDouble();
        }

        public static function query(string $sql, array $params = []): void
        {
            if (self::$queryShouldFail) {
                throw new \RuntimeException('Simulated insert failure.');
            }
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
    $assert(str_contains($serviceSource, 'public static function validateCreateInput') && str_contains($controllerSource, 'ExpensesService::validateCreateInput($_POST)'), 'service and controller share one complete input contract');

    $validInput = ExpensesService::validateCreateInput([
        'expense_ref' => '  EXP-001  ',
        'category_name' => '  Travel  ',
        'amount' => '.5',
        'expense_status' => 'approved',
        'expense_date' => '2024-02-29',
    ]);
    $assert($validInput['valid'], 'valid Expense scalar input is accepted');
    $assert($validInput['values'] === [
        'expense_ref' => 'EXP-001',
        'category_name' => 'Travel',
        'amount' => '0.50',
        'expense_status' => 'approved',
        'expense_date' => '2024-02-29',
    ], 'valid Expense input is normalized without changing legitimate status behavior');
    $defaults = ExpensesService::validateCreateInput(['expense_ref' => 'EXP-DEFAULT', 'amount' => '1']);
    $assert($defaults['valid'] && $defaults['values']['category_name'] === '' && $defaults['values']['expense_status'] === 'submitted' && $defaults['values']['expense_date'] === '', 'optional blank fields retain existing defaults');

    foreach (['expense_ref', 'category_name', 'expense_status', 'expense_date'] as $field) {
        foreach ([['bad'], new \stdClass(), true, 17, 12.5] as $malformedValue) {
            $malformed = ['expense_ref' => 'EXP-TYPE', 'amount' => '1.00', $field => $malformedValue];
            $result = ExpensesService::validateCreateInput($malformed);
            $assert(!$result['valid'], $field . ' rejects unsupported input types');
        }
    }
    $assert(in_array('expense_ref_too_long', ExpensesService::validateCreateInput(['expense_ref' => str_repeat('R', 81), 'amount' => '1.00'])['errors'], true), 'overlong expense reference is rejected');
    $assert(in_array('category_too_long', ExpensesService::validateCreateInput(['expense_ref' => 'EXP-LONG', 'category_name' => str_repeat('C', 121), 'amount' => '1.00'])['errors'], true), 'overlong expense category is rejected');
    $assert(in_array('status_too_long', ExpensesService::validateCreateInput(['expense_ref' => 'EXP-LONG', 'expense_status' => str_repeat('S', 41), 'amount' => '1.00'])['errors'], true), 'overlong expense status is rejected');
    $assert(in_array('expense_date_invalid', ExpensesService::validateCreateInput(['expense_ref' => 'EXP-DATE', 'amount' => '1.00', 'expense_date' => '2023-02-29'])['errors'], true), 'impossible expense date is rejected');
    $assert(!str_contains($serviceSource, '(string)($input['), 'Expense persistence has no pre-validation string coercion');

    DB::$connection = new \App\Core\ExpenseConnectionDouble();
    DB::$connection->insert_id = 417;
    DB::$queries = [];
    DB::$transactionEvents = [];
    $createdId = ExpensesService::create([
        'expense_ref' => 'EXP-001',
        'category_name' => 'Travel',
        'amount' => '1234.50',
        'expense_status' => 'submitted',
        'expense_date' => '2026-10-26',
    ]);
    $assert($createdId === 417, 'valid expense returns the exact positive generated ID');
    $assert(count(DB::$queries) === 1, 'valid expense performs one insert');
    $assert(DB::$transactionEvents === ['begin', 'commit'], 'successful expense creation commits after identity verification');
    $assert(DB::$queries[0]['params'][2] === '1234.50' && is_string(DB::$queries[0]['params'][2]), 'insert receives the exact normalized decimal string');
    $assert(DB::$queries[0]['params'] === ['EXP-001', 'Travel', '1234.50', 'submitted', '2026-10-26'], 'insert receives the complete normalized Expense contract');

    DB::$connection->insert_id = 0;
    DB::$queries = [];
    DB::$transactionEvents = [];
    try {
        ExpensesService::create(['expense_ref' => 'EXP-NO-ID', 'amount' => '1.00']);
        $assert(false, 'missing generated expense identity unexpectedly succeeded');
    } catch (\RuntimeException $e) {
        $assert($e->getMessage() === 'Expense insert identity was not available.', 'missing identity is reported explicitly');
        $assert(DB::$transactionEvents === ['begin', 'rollback'], 'missing identity rolls back before failure escapes');
        $assert(DB::$queries !== [], 'identity failure occurs after the insert attempt within the transaction');
    }

    DB::$connection->insert_id = 418;
    DB::$connection->commitResult = false;
    DB::$queries = [];
    DB::$transactionEvents = [];
    try {
        ExpensesService::create(['expense_ref' => 'EXP-COMMIT', 'amount' => '1.00']);
        $assert(false, 'commit failure unexpectedly succeeded');
    } catch (\RuntimeException $e) {
        $assert($e->getMessage() === 'Expense transaction commit failed.', 'commit failure cannot produce false success');
        $assert(DB::$transactionEvents === ['begin', 'commit', 'rollback'], 'commit failure attempts rollback and never returns an ID');
    }
    DB::$connection->commitResult = true;

    DB::$connection->beginResult = false;
    DB::$queries = [];
    DB::$transactionEvents = [];
    try {
        ExpensesService::create(['expense_ref' => 'EXP-BEGIN', 'amount' => '1.00']);
        $assert(false, 'transaction begin failure unexpectedly succeeded');
    } catch (\RuntimeException $e) {
        $assert($e->getMessage() === 'Expense transaction could not start.', 'transaction begin failure is surfaced');
        $assert(DB::$transactionEvents === ['begin'], 'transaction begin failure does not claim a rollback for an unstarted transaction');
        $assert(DB::$queries === [], 'transaction begin failure performs no insert');
    }
    DB::$connection->beginResult = true;

    DB::$connection->commitResult = false;
    DB::$connection->rollbackResult = false;
    DB::$queries = [];
    DB::$transactionEvents = [];
    try {
        ExpensesService::create(['expense_ref' => 'EXP-ROLLBACK', 'amount' => '1.00']);
        $assert(false, 'rollback failure unexpectedly succeeded');
    } catch (\RuntimeException $e) {
        $assert($e->getMessage() === 'Expense transaction rollback failed.', 'rollback failure is surfaced rather than reported as success');
        $assert(DB::$transactionEvents === ['begin', 'commit', 'rollback'], 'rollback is attempted after commit failure even when rollback itself fails');
    }
    DB::$connection->commitResult = true;
    DB::$connection->rollbackResult = true;

    DB::$queryShouldFail = true;
    DB::$queries = [];
    DB::$transactionEvents = [];
    try {
        ExpensesService::create(['expense_ref' => 'EXP-INSERT', 'amount' => '1.00']);
        $assert(false, 'insert failure unexpectedly succeeded');
    } catch (\RuntimeException $e) {
        $assert($e->getMessage() === 'Simulated insert failure.', 'insert failure is surfaced');
        $assert(DB::$transactionEvents === ['begin', 'rollback'], 'insert failure rolls back without commit');
    }
    DB::$queryShouldFail = false;

    foreach (['12.345', '1e2', 'not-money', ['10.00']] as $invalid) {
        DB::$queries = [];
        try {
            ExpensesService::create(['expense_ref' => 'EXP-BAD', 'amount' => $invalid]);
            $assert(false, 'invalid expense amount unexpectedly reached create success');
        } catch (\InvalidArgumentException) {
            $assert(DB::$queries === [], 'invalid expense amount performs no SQL');
        }
    }

    foreach ([
        ['expense_ref' => ['EXP-ARRAY'], 'amount' => '1.00'],
        ['expense_ref' => 'EXP-BAD-CATEGORY', 'category_name' => ['Travel'], 'amount' => '1.00'],
        ['expense_ref' => 'EXP-BAD-STATUS', 'expense_status' => ['submitted'], 'amount' => '1.00'],
        ['expense_ref' => 'EXP-BAD-DATE', 'expense_date' => ['2026-10-26'], 'amount' => '1.00'],
    ] as $invalidInput) {
        DB::$queries = [];
        try {
            ExpensesService::create($invalidInput);
            $assert(false, 'malformed non-monetary Expense input unexpectedly reached create success');
        } catch (\InvalidArgumentException) {
            $assert(DB::$queries === [], 'malformed non-monetary Expense input performs no SQL');
        }
    }

    $assert(str_contains($controllerSource, "Auth::requireCsrf") && str_contains($controllerSource, 'expense_ref'), 'existing CSRF and reference validation remain in the controller');

    $assert(str_contains($serviceSource, 'DB::conn()') && str_contains($serviceSource, 'begin_transaction') && str_contains($serviceSource, 'insert_id') && str_contains($serviceSource, 'rollback'), 'Expense creation owns same-connection transaction and identity verification');
    $assert(str_contains($controllerSource, '$createdId = ExpensesService::create') && str_contains($controllerSource, 'sbaio.expenses.created_with_id') && str_contains($controllerSource, "'expense_id' => \$createdId"), 'controller confirmation uses returned identity and carries it forward');

    echo 'Expenses creation integrity probe: ' . $assertions . ' assertions, ' . $failures . " failures\n";
    exit($failures === 0 ? 0 : 1);
}

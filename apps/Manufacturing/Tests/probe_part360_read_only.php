<?php
declare(strict_types=1);

namespace App\Core {
    final class FakePart360Connection
    {
        public function real_escape_string(string $value): string
        {
            return addslashes($value);
        }
    }

    final class DB
    {
        /** @var array<int,string> */
        public static array $selects = [];
        /** @var array<int,string> */
        public static array $mutations = [];
        public static bool $missingPartSchema = false;

        public static function conn(): FakePart360Connection
        {
            return new FakePart360Connection();
        }

        public static function query(string $sql, array $params = []): mixed
        {
            self::$mutations[] = $sql;
            throw new \RuntimeException('mutation attempted by isolated Part360 harness');
        }

        public static function fetchAll(string $sql, array $params = []): array
        {
            self::$selects[] = $sql;
            if (self::$missingPartSchema && str_contains($sql, 'FROM part_molds')) {
                throw new \RuntimeException('missing part schema');
            }
            if (str_contains($sql, 'FROM products p') && str_contains($sql, 'LEFT JOIN')) {
                return [[
                    'id' => 42,
                    'parts_name' => 'Harness Part',
                    'parts_number' => 'H-42',
                    'model' => 'Read-only fixture',
                    'is_active' => 1,
                    'supply_mode' => 'in_house',
                    'fulfillment_mode' => 'company_to_destination',
                    'requires_assembly' => 0,
                    'requires_ipm_qc' => 0,
                    'dispatch_as_is' => 1,
                    'stock_balance' => 0,
                ]];
            }
            return [];
        }

        public static function fetchOne(string $sql, array $params = []): ?array
        {
            $rows = self::fetchAll($sql, $params);
            return $rows[0] ?? null;
        }

        public static function reset(): void
        {
            self::$selects = [];
            self::$mutations = [];
            self::$missingPartSchema = false;
        }
    }
}

namespace {
    define('APP_ROOT', dirname(__DIR__, 3));
    require_once APP_ROOT . '/apps/Manufacturing/modules/Products/Services/Part360Service.php';
    require_once APP_ROOT . '/apps/Manufacturing/Services/OperatorPartDetailContributionService.php';

    use App\Core\DB;
    use Plugins\Products\Services\Part360Service;

    $passed = 0;
    $failed = 0;
    $assert = static function (bool $condition, string $label) use (&$passed, &$failed): void {
        echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
        $condition ? $passed++ : $failed++;
    };

    DB::reset();
    $projection = Part360Service::buildReadOnly(42);
    $assert(is_array($projection) && (int)($projection['product']['id'] ?? 0) === 42, 'authorized read-only Part360 returns expected projection');
    $assert(DB::$mutations === [], 'authorized read-only Part360 performs no DB mutation calls');
    $assert(DB::$selects !== [] && count(array_filter(DB::$selects, static fn(string $sql): bool => preg_match('/\b(CREATE|ALTER|INSERT|UPDATE|DELETE)\b/i', $sql) === 1)) === 0, 'authorized read-only SQL is select/show only');

    DB::reset();
    $adminThrew = false;
    try {
        Part360Service::build(42);
    } catch (\Throwable) {
        $adminThrew = true;
    }
    $assert($adminThrew && DB::$mutations !== [], 'existing admin Part360 path still retains schema initialization');

    DB::reset();
    DB::$missingPartSchema = true;
    $missingThrew = false;
    try {
        Part360Service::buildReadOnly(42);
    } catch (\Throwable) {
        $missingThrew = true;
    }
    $assert($missingThrew, 'missing required schema produces unavailable read failure');
    $assert(DB::$mutations === [], 'missing schema does not trigger repair mutation');

    $contribution = file_get_contents(APP_ROOT . '/apps/Manufacturing/Services/OperatorPartDetailContributionService.php');
    $assert(is_string($contribution) && str_contains($contribution, 'Part360Service::buildReadOnly($productId)'), 'operator detail contribution uses read-only Part360 entry point');
    $assert(is_string($contribution) && !str_contains($contribution, 'Part360Service::build($productId)'), 'operator detail contribution does not call mutating admin entry point');

    if ($failed > 0) {
        fwrite(STDERR, "RESULT: {$passed}/" . ($passed + $failed) . " assertions passed; {$failed} failed\n");
        exit(1);
    }
    echo "RESULT: {$passed}/{$passed} assertions passed\n";
}

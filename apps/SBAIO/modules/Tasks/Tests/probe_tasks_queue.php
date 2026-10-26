<?php
declare(strict_types=1);

/**
 * Database-free OH-AUTO3-SBAIO-TASKS-QUEUE-001 probe.
 *
 * Uses an in-memory DB double. No application bootstrap or configured DB is
 * loaded, and no Task mutation is exercised.
 */
namespace App\Core {
    final class DB
    {
        public static array $queries = [];
        public static array $fetchAllRows = [];

        public static function fetchAll(string $sql, array $params = []): array
        {
            self::$queries[] = ['method' => 'fetchAll', 'sql' => $sql, 'params' => $params];
            $rows = array_shift(self::$fetchAllRows);
            return is_array($rows) ? $rows : [];
        }
    }
}

namespace {
    require_once __DIR__ . '/../Services/TasksService.php';

    use App\Core\DB;
    use Plugins\Tasks\Services\TasksService;

    $assertions = 0;
    $failures = 0;
    $assert = static function (bool $condition, string $message) use (&$assertions, &$failures): void {
        $assertions++;
        if (!$condition) {
            $failures++;
            fwrite(STDERR, "FAIL: {$message}\n");
        }
    };

    $serviceSource = file_get_contents(__DIR__ . '/../Services/TasksService.php') ?: '';
    $controllerSource = file_get_contents(__DIR__ . '/../Controllers/TasksController.php') ?: '';
    $viewSource = file_get_contents(__DIR__ . '/../Views/index.php') ?: '';
    $routesSource = file_get_contents(__DIR__ . '/../routes.php') ?: '';

    DB::$queries = [];
    DB::$fetchAllRows = [[
        ['id' => 30, 'task_title' => 'Oldest overdue', 'task_status' => 'open', 'priority' => 'urgent', 'due_date' => '2026-01-03', 'queue_state' => 'overdue'],
        ['id' => 29, 'task_title' => 'Older overdue', 'task_status' => 'in_progress', 'priority' => 'normal', 'due_date' => '2026-01-02', 'queue_state' => 'overdue'],
        ['id' => 28, 'task_title' => 'Overflow', 'task_status' => 'open', 'priority' => 'normal', 'due_date' => '2026-01-01', 'queue_state' => 'overdue'],
    ]];
    $overdue = TasksService::search([
        'queue' => 'overdue',
        'status' => 'open',
        'priority' => 'urgent',
        'task_bucket' => 'follow-up',
        'from' => '2026-01-01',
        'to' => '2026-01-31',
    ], 2);
    $assert($overdue['valid'] === true && $overdue['truncated'] === true && count($overdue['rows']) === 2, 'overdue search is bounded at the requested page size with overflow detection');
    $assert($overdue['rows'][0]['id'] === 30 && $overdue['rows'][1]['id'] === 29, 'overdue rows preserve due_date DESC and id DESC ordering');
    $assert(is_string($overdue['next_cursor']) && $overdue['next_cursor'] !== '', 'bounded search emits an opaque cursor');
    $assert(str_contains(DB::$queries[0]['sql'], 'due_date IS NOT NULL AND due_date < CURDATE()') && str_contains(DB::$queries[0]['sql'], "LOWER(COALESCE(task_status,'')) NOT IN ('done','completed','closed')"), 'overdue SQL excludes established terminal statuses without adding lifecycle states');
    $assert(str_contains(DB::$queries[0]['sql'], 'task_status = ?') && str_contains(DB::$queries[0]['sql'], 'priority = ?') && str_contains(DB::$queries[0]['sql'], 'task_bucket = ?') && str_contains(DB::$queries[0]['sql'], 'due_date >= ?') && str_contains(DB::$queries[0]['sql'], 'due_date <= ?'), 'overdue search uses exact filters and inclusive due-date bounds');
    $assert(DB::$queries[0]['params'] === ['open', 'urgent', 'follow-up', '2026-01-01', '2026-01-31'], 'overdue filters are normalized and parameter-bound');
    $assert(str_contains(DB::$queries[0]['sql'], 'ORDER BY due_date DESC, id DESC LIMIT 3'), 'search fetches one overflow row with deterministic ordering');

    DB::$queries = [];
    DB::$fetchAllRows = [[
        ['id' => 27, 'task_title' => 'Next overdue', 'task_status' => 'open', 'priority' => 'urgent', 'due_date' => '2025-12-31', 'queue_state' => 'overdue'],
    ]];
    $next = TasksService::search([
        'queue' => 'overdue',
        'status' => 'open',
        'priority' => 'urgent',
        'task_bucket' => 'follow-up',
        'from' => '2026-01-01',
        'to' => '2026-01-31',
    ], 2, $overdue['next_cursor']);
    $assert($next['rows'][0]['id'] === 27, 'valid cursor advances to the next page');
    $assert(str_contains(DB::$queries[0]['sql'], '(due_date < ? OR due_date IS NULL OR (due_date = ? AND id < ?))'), 'cursor uses strict due-date/id keyset continuation');
    $assert(count(DB::$queries[0]['params']) === 8 && DB::$queries[0]['params'][5] === '2026-01-02' && DB::$queries[0]['params'][7] === 29, 'cursor continuation binds the prior row boundary after exact filters');

    DB::$queries = [];
    DB::$fetchAllRows = [[
        ['id' => 40, 'task_title' => 'Upcoming', 'task_status' => 'open', 'priority' => 'normal', 'due_date' => '2026-10-30', 'queue_state' => 'upcoming'],
    ]];
    $upcoming = TasksService::search(['queue' => 'upcoming']);
    $assert($upcoming['rows'][0]['queue_state'] === 'upcoming', 'upcoming result carries the queue classification');
    $assert(str_contains(DB::$queries[0]['sql'], 'due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)'), 'upcoming SQL uses the existing seven-day operational window');

    DB::$queries = [];
    DB::$fetchAllRows = [[
        ['id' => 50, 'task_title' => 'Historical', 'task_status' => 'completed', 'priority' => 'normal', 'due_date' => '2025-12-15', 'queue_state' => 'historical'],
    ]];
    $historical = TasksService::search(['queue' => 'historical', 'from' => '2025-12-01', 'to' => '2025-12-31']);
    $assert($historical['rows'][0]['queue_state'] === 'historical', 'historical search preserves service-provided classification');
    $assert(str_contains(DB::$queries[0]['sql'], 'WHERE due_date >= ? AND due_date <= ?') && DB::$queries[0]['params'] === ['2025-12-01', '2025-12-31'], 'historical search uses exact date bounds without an operational status mutation');

    foreach ([
        ['queue' => 'unknown'],
        ['queue' => ['overdue']],
        ['status' => ['open']],
        ['unexpected' => 'value'],
        ['from' => '2026-02-30'],
        ['from' => '2026-02-02', 'to' => '2026-02-01'],
    ] as $invalidFilters) {
        DB::$queries = [];
        try {
            TasksService::search($invalidFilters);
            $assert(false, 'invalid Task search filter unexpectedly succeeded');
        } catch (\InvalidArgumentException) {
            $assert(DB::$queries === [], 'invalid Task search filter is rejected before database access');
        }
    }
    foreach ([$overdue['next_cursor'] . 'tampered', '!', 'eyJ2IjoyfQ'] as $invalidCursor) {
        DB::$queries = [];
        try {
            TasksService::search(['queue' => 'overdue'], 2, $invalidCursor);
            $assert(false, 'invalid Task cursor unexpectedly succeeded');
        } catch (\InvalidArgumentException) {
            $assert(DB::$queries === [], 'invalid Task cursor is rejected before database access');
        }
    }
    DB::$queries = [];
    try {
        TasksService::search(['queue' => 'upcoming'], 2, $overdue['next_cursor']);
        $assert(false, 'filter-mismatched Task cursor unexpectedly succeeded');
    } catch (\InvalidArgumentException) {
        $assert(DB::$queries === [], 'filter-mismatched Task cursor is rejected before database access');
    }

    $assert(str_contains($serviceSource, 'public static function search') && str_contains($serviceSource, 'MAX_SEARCH_LIMIT'), 'TasksService owns bounded historical discovery');
    $assert(str_contains($serviceSource, 'searchFilterFingerprint') && str_contains($serviceSource, 'decodeSearchCursor') && str_contains($serviceSource, 'encodeSearchCursor'), 'Task cursors are opaque and filter-bound');
    $assert(str_contains($controllerSource, 'TasksService::search(') && str_contains($controllerSource, "'task_cursor'"), 'controller carries Task search filters and cursor state');
    $assert(str_contains($viewSource, 'data-sbaio-tasks-search') && str_contains($viewSource, 'data-sbaio-tasks-search-result') && str_contains($viewSource, 'task_queue'), 'existing Task queue renders bounded discovery controls and results');
    $assert(str_contains($routesSource, "Auth::requireAppAccess('sbaio')") && strpos($routesSource, "Auth::requireAppAccess('sbaio')") < strpos($routesSource, 'TasksController::index'), 'existing SBAIO app authorization remains before queue reads');
    $searchSection = substr($serviceSource, strpos($serviceSource, 'public static function search'), strpos($serviceSource, 'private static function normalizeSearchFilters') - strpos($serviceSource, 'public static function search'));
    $assert(!str_contains($searchSection . $controllerSource, 'UPDATE sbaio_tasks') && !str_contains($searchSection . $controllerSource, 'INSERT INTO sbaio_tasks'), 'queue discovery adds no Task mutation path');

    echo 'Tasks queue probe: ' . $assertions . ' assertions, ' . $failures . " failures\n";
    exit($failures === 0 ? 0 : 1);
}

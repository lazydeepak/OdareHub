<?php
declare(strict_types=1);

/**
 * Database-free OH-AUTO3-SBAIO-TASKS-REPORT-001 probe.
 *
 * Uses an in-memory DB double and source-contract checks. It never loads
 * application bootstrap and never connects to an installed/configured DB.
 */
namespace App\Core {
    final class DB
    {
        public static array $queries = [];
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
    $routeSource = file_get_contents(__DIR__ . '/../routes.php') ?: '';
    $viewSource = file_get_contents(__DIR__ . '/../Views/report.php') ?: '';
    $registrySource = file_get_contents(__DIR__ . '/../../../Services/ModuleReportRegistryService.php') ?: '';

    DB::$queries = [];
    DB::$fetchOneRows = [[
        'total' => 7,
        'open' => 3,
        'done' => 2,
        'overdue' => 1,
        'due_7d' => 2,
    ]];
    DB::$fetchAllRows = [
        [['task_status' => 'open', 'n' => 3]],
        [['priority' => 'urgent', 'n' => 2]],
        [['id' => 9, 'task_title' => 'Newest', 'owner_name' => 'Ops', 'task_type' => 'follow-up', 'task_status' => 'open', 'priority' => 'urgent', 'due_date' => '2026-10-27']],
    ];
    $data = TasksService::reportData();
    $assert($data['summary']['total'] === 7 && $data['summary']['overdue'] === 1, 'report summary preserves prepared aggregate values');
    $assert($data['byStatus'][0]['task_status'] === 'open' && $data['byPriority'][0]['priority'] === 'urgent' && $data['recent'][0]['id'] === 9, 'report collections preserve existing output shape');
    $assert(count(DB::$queries) === 4 && DB::$queries[0]['method'] === 'fetchOne', 'report performs exactly the existing four reads in order');
    $assert(str_contains(DB::$queries[0]['sql'], "LOWER(COALESCE(task_status,'')) IN ('open','in_progress','pending','todo')") && str_contains(DB::$queries[0]['sql'], 'due_date IS NOT NULL AND due_date < CURDATE() AND LOWER(COALESCE(task_status,\'\')) NOT IN (\'done\',\'completed\',\'closed\')'), 'summary SQL preserves the actionable overdue predicate');
    $assert(str_contains(DB::$queries[0]['sql'], "due_date IS NOT NULL AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND LOWER(COALESCE(task_status,'')) NOT IN ('done','completed','closed')"), 'report due_7d predicate matches the actionable upcoming queue at both inclusive boundaries and excludes terminal tasks');
    $assert(str_contains(DB::$queries[1]['sql'], 'GROUP BY task_status ORDER BY n DESC') && str_contains(DB::$queries[2]['sql'], 'GROUP BY priority ORDER BY n DESC'), 'distribution SQL preserves grouping and ordering');
    $assert(str_contains(DB::$queries[3]['sql'], 'ORDER BY due_date ASC, id DESC LIMIT 25'), 'recent SQL preserves ordering and limit');

    DB::$queries = [];
    DB::$fetchOneRows = [];
    DB::$fetchAllRows = [];
    DB::$throw = true;
    $unavailable = TasksService::reportData();
    $assert($unavailable['summary']['total'] === 0 && $unavailable['byStatus'] === [] && $unavailable['byPriority'] === [] && $unavailable['recent'] === [], 'read failure preserves the existing default report payload');
    DB::$throw = false;

    $assert(str_contains($serviceSource, 'public static function reportData'), 'TasksService owns the report read contract');
    $assert(str_contains($controllerSource, "TasksController::report") === false && str_contains($controllerSource, "'reportData' => TasksService::reportData()"), 'controller prepares report data for presentation');
    $assert(str_contains($routeSource, 'TasksController::report($view, $report)'), 'route delegates admitted report rendering to the controller');
    $assert(strpos($routeSource, "Auth::requireAppAccess('sbaio')") < strpos($routeSource, 'ModuleReportRegistryService::findActiveReport') && strpos($routeSource, 'ModuleReportRegistryService::findActiveReport') < strpos($routeSource, 'TasksController::report'), 'authorization and active registry admission precede data preparation and rendering');
    $assert(!str_contains($viewSource, 'DB::') && !str_contains($viewSource, 'sbaio_tasks') && !str_contains($viewSource, 'ModuleReportRegistryService'), 'report view performs no database or registry operations');
    $assert(str_contains($viewSource, '$reportData') && str_contains($viewSource, '$byPriority'), 'report view consumes prepared report data');
    $assert(str_contains($registrySource, "'sbaio.tasks.overview'") && str_contains($routeSource, "'sbaio.tasks.overview'"), 'existing report registry key remains unchanged');
    $assert(!str_contains($routeSource, 'reportData') && !str_contains($routeSource, 'DB::'), 'route retains admission only and does not duplicate report SQL');

    echo 'Tasks report ownership probe: ' . $assertions . ' assertions, ' . $failures . " failures\n";
    exit($failures === 0 ? 0 : 1);
}

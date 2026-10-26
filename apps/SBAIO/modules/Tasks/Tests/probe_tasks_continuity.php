<?php
declare(strict_types=1);

/**
 * Database-free OH-AUTO3-SBAIO-TASKS-CONTINUITY-001 probe.
 *
 * Exercises transaction/identity and exact-ID retrieval contracts with an
 * in-memory DB double. No application bootstrap or installed DB is loaded.
 */
namespace App\Core {
    final class TaskDbConnection
    {
        public mixed $insert_id = 0;
        public array $events = [];
        public bool $beginResult = true;
        public bool $commitResult = true;
        public bool $rollbackResult = true;

        public function begin_transaction(): bool
        {
            $this->events[] = 'begin';
            return $this->beginResult;
        }

        public function commit(): bool
        {
            $this->events[] = 'commit';
            return $this->commitResult;
        }

        public function rollback(): bool
        {
            $this->events[] = 'rollback';
            return $this->rollbackResult;
        }
    }

    final class DB
    {
        public static TaskDbConnection $connection;
        public static array $queries = [];
        public static ?array $fetchOneRow = null;
        public static bool $throwOnQuery = false;

        public static function conn(): TaskDbConnection
        {
            return self::$connection;
        }

        public static function query(string $sql, array $params = []): void
        {
            if (self::$throwOnQuery) {
                throw new \RuntimeException('insert failed');
            }
            self::$queries[] = ['method' => 'query', 'sql' => $sql, 'params' => $params];
        }

        public static function fetchOne(string $sql, array $params = []): ?array
        {
            self::$queries[] = ['method' => 'fetchOne', 'sql' => $sql, 'params' => $params];
            return self::$fetchOneRow;
        }
    }
}

namespace {
    require_once __DIR__ . '/../Services/TasksService.php';

    use App\Core\DB;
    use Plugins\Tasks\Services\TasksService;

    DB::$connection = new \App\Core\TaskDbConnection();
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
    $schemaSource = file_get_contents(__DIR__ . '/../install.php') ?: '';

    $input = [
        'task_title' => 'Continuity task',
        'owner_name' => 'Operations',
        'task_type' => 'follow-up',
        'task_bucket' => 'weekly',
        'related_staff_id' => '42',
        'related_date' => '2026-10-26',
        'task_status' => 'open',
        'priority' => 'normal',
        'due_date' => '2026-12-31',
    ];

    DB::$queries = [];
    DB::$connection->events = [];
    DB::$connection->insert_id = 417;
    $createdId = TasksService::create($input);
    $assert($createdId === 417, 'creation returns the same connection positive generated ID');
    $assert(DB::$connection->events === ['begin', 'commit'], 'successful creation commits after insert identity verification');
    $assert(count(DB::$queries) === 1 && DB::$queries[0]['method'] === 'query', 'successful creation performs one insert');
    $assert(DB::$queries[0]['params'][0] === 'Continuity task' && DB::$queries[0]['params'][4] === 42, 'existing INSERT receives normalized task values');

    foreach ([0, '0', -4, '4x', '1.5', '1e2', ['417'], true] as $identity) {
        DB::$queries = [];
        DB::$connection->events = [];
        DB::$connection->insert_id = $identity;
        try {
            TasksService::create($input);
            $assert(false, 'invalid generated identity unexpectedly reported success');
        } catch (\Throwable) {
            $assert(DB::$queries !== [] && DB::$connection->events === ['begin', 'rollback'], 'invalid generated identity rolls back before success');
        }
    }

    DB::$queries = [];
    DB::$connection->events = [];
    DB::$connection->insert_id = 418;
    DB::$connection->commitResult = false;
    try {
        TasksService::create($input);
        $assert(false, 'commit failure unexpectedly reported success');
    } catch (\Throwable) {
        $assert(DB::$connection->events === ['begin', 'commit', 'rollback'], 'commit failure rolls back the same transaction');
    }
    DB::$connection->commitResult = true;

    DB::$queries = [];
    DB::$connection->events = [];
    DB::$connection->insert_id = 419;
    DB::$throwOnQuery = true;
    try {
        TasksService::create($input);
        $assert(false, 'insert failure unexpectedly reported success');
    } catch (\Throwable) {
        $assert(DB::$connection->events === ['begin', 'rollback'], 'insert failure rolls back the same transaction');
    }
    DB::$throwOnQuery = false;

    DB::$queries = [];
    DB::$fetchOneRow = [
        'id' => 417,
        'task_title' => 'Continuity task',
        'owner_name' => 'Operations',
        'task_type' => 'follow-up',
        'task_bucket' => 'weekly',
        'related_staff_id' => 42,
        'related_date' => '2026-10-26',
        'task_status' => 'open',
        'priority' => 'normal',
        'due_date' => '2026-12-31',
    ];
    $record = TasksService::findById('417');
    $assert($record['id'] === 417 && $record['task_title'] === 'Continuity task', 'exact retrieval returns the selected task record');
    $assert(count(DB::$queries) === 1 && DB::$queries[0]['params'] === [417], 'exact retrieval binds the positive ID as a parameter');
    $assert(str_contains(DB::$queries[0]['sql'], 'FROM sbaio_tasks WHERE id = ? LIMIT 1'), 'exact retrieval uses bounded identity SQL');

    DB::$queries = [];
    DB::$fetchOneRow = null;
    $assert(TasksService::findById(999999) === null && count(DB::$queries) === 1, 'missing exact record returns null without latest-row inference');
    foreach (['0', '-1', '417x', '1.5', '1e2', ['417'], true, 0, -2] as $invalidId) {
        DB::$queries = [];
        try {
            TasksService::findById($invalidId);
            $assert(false, 'malformed task ID unexpectedly succeeded');
        } catch (\InvalidArgumentException) {
            $assert(DB::$queries === [], 'malformed task ID performs no database read');
        }
    }

    $assert(str_contains($serviceSource, 'DB::conn()') && str_contains($serviceSource, 'begin_transaction') && str_contains($serviceSource, 'insert_id') && str_contains($serviceSource, 'rollback'), 'Task creation owns transaction and generated-identity guarantees');
    $assert(str_contains($serviceSource, 'public static function findById') && str_contains($serviceSource, 'WHERE id = ? LIMIT 1'), 'Task service owns exact-ID retrieval');
    $assert(str_contains($controllerSource, 'TasksService::findById') && str_contains($controllerSource, "'task_id' => \$createdId"), 'controller carries exact created ID into the existing Task surface');
    $assert(str_contains($viewSource, 'data-sbaio-tasks-created-record') && str_contains($viewSource, 'data-sbaio-tasks-exact-record'), 'administrator Task interface renders created and exact-record continuity states');
    $assert(str_contains($routesSource, "Auth::requireAppAccess('sbaio')") && strpos($routesSource, "Auth::requireAppAccess('sbaio')") < strpos($routesSource, 'TasksController::create'), 'existing SBAIO app authorization precedes Task creation');
    $assert(str_contains($schemaSource, 'id INT AUTO_INCREMENT PRIMARY KEY') && str_contains($schemaSource, 'related_staff_id INT NULL'), 'continuity uses the existing generated-ID schema and Staff reference shape');
    $assert(!str_contains($serviceSource . $controllerSource, 'ORDER BY id DESC LIMIT 1') && !str_contains($serviceSource . $controllerSource, 'task_title ='), 'Task identity never falls back to latest-row or title inference');

    echo 'Tasks continuity probe: ' . $assertions . ' assertions, ' . $failures . " failures\n";
    exit($failures === 0 ? 0 : 1);
}

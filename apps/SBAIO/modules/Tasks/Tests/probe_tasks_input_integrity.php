<?php
declare(strict_types=1);

/**
 * Database-free Task input-integrity probe.
 *
 * This probe uses an in-memory DB double and never loads application bootstrap
 * or connects to an installed/configured database.
 */
namespace App\Core {
    final class TaskDbConnection
    {
        public int|string|null $insert_id = 417;
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
        public static array $queries = [];
        public static TaskDbConnection $connection;

        public static function conn(): TaskDbConnection
        {
            return self::$connection;
        }

        public static function query(string $sql, array $params = []): void
        {
            self::$queries[] = ['sql' => $sql, 'params' => $params];
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
    $routesSource = file_get_contents(__DIR__ . '/../routes.php') ?: '';
    $schemaSource = file_get_contents(__DIR__ . '/../install.php') ?: '';

    $valid = TasksService::validateCreateInput([
        'task_title' => '  Prepare report  ',
        'owner_name' => '  Operations  ',
        'task_type' => 'follow-up',
        'task_bucket' => 'weekly',
        'related_date' => '2024-02-29',
        'task_status' => 'legacy-status',
        'priority' => 'urgent',
        'due_date' => '2026-12-31',
    ]);
    $assert($valid['valid'], 'valid unassigned Task scalar input is accepted');
    $assert($valid['values'] === [
        'task_title' => 'Prepare report',
        'owner_name' => 'Operations',
        'task_type' => 'follow-up',
        'task_bucket' => 'weekly',
        'related_staff_id' => null,
        'related_date' => '2024-02-29',
        'task_status' => 'legacy-status',
        'priority' => 'urgent',
        'due_date' => '2026-12-31',
    ], 'valid Task input is normalized without changing arbitrary status or priority');

    $defaults = TasksService::validateCreateInput(['task_title' => 'Default task']);
    $assert($defaults['valid'], 'optional Task fields retain valid defaults');
    $assert($defaults['values'] === [
        'task_title' => 'Default task',
        'owner_name' => '',
        'task_type' => '',
        'task_bucket' => '',
        'related_staff_id' => null,
        'related_date' => '',
        'task_status' => 'open',
        'priority' => 'normal',
        'due_date' => '',
    ], 'blank optional Task fields preserve existing null/default behavior');
    $assert(TasksService::validateCreateInput(['task_title' => 'Zero staff', 'related_staff_id' => '000'])['values']['related_staff_id'] === null, 'zero staff sentinel remains unassigned');

    foreach (['task_title', 'owner_name', 'task_type', 'task_bucket', 'related_date', 'task_status', 'priority', 'due_date'] as $field) {
        foreach ([['bad'], new \stdClass(), true, 17, 12.5] as $malformedValue) {
            $input = ['task_title' => 'Type test', $field => $malformedValue];
            $result = TasksService::validateCreateInput($input);
            $assert(!$result['valid'], $field . ' rejects unsupported input types');
        }
    }
    foreach (['-1', '1.5', '1e2', '2147483648', ['42'], true, 42.5] as $staffId) {
        $result = TasksService::validateCreateInput(['task_title' => 'Staff test', 'related_staff_id' => $staffId]);
        $assert(!$result['valid'] && in_array('related_staff_id_invalid', $result['errors'], true), 'malformed staff ID is rejected without relationship inference');
    }
    foreach (['42', '00042', 42] as $staffId) {
        $result = TasksService::validateCreateInput(['task_title' => 'Staff association', 'related_staff_id' => $staffId]);
        $assert(!$result['valid'] && in_array('related_staff_id_unavailable', $result['errors'], true) && $result['values']['related_staff_id'] === null, 'positive Staff association is rejected without authorization');
    }

    $assert(in_array('task_title_too_long', TasksService::validateCreateInput(['task_title' => str_repeat('T', 191)])['errors'], true), 'task title respects VARCHAR(190)');
    $assert(in_array('owner_name_too_long', TasksService::validateCreateInput(['task_title' => 'T', 'owner_name' => str_repeat('O', 191)])['errors'], true), 'owner name respects VARCHAR(190)');
    $assert(in_array('task_type_too_long', TasksService::validateCreateInput(['task_title' => 'T', 'task_type' => str_repeat('Y', 81)])['errors'], true), 'task type respects VARCHAR(80)');
    $assert(in_array('task_bucket_too_long', TasksService::validateCreateInput(['task_title' => 'T', 'task_bucket' => str_repeat('B', 81)])['errors'], true), 'task bucket respects VARCHAR(80)');
    $assert(in_array('task_status_too_long', TasksService::validateCreateInput(['task_title' => 'T', 'task_status' => str_repeat('S', 41)])['errors'], true), 'task status respects VARCHAR(40)');
    $assert(in_array('priority_too_long', TasksService::validateCreateInput(['task_title' => 'T', 'priority' => str_repeat('P', 41)])['errors'], true), 'priority respects VARCHAR(40)');
    $assert(in_array('related_date_invalid', TasksService::validateCreateInput(['task_title' => 'T', 'related_date' => '2023-02-29'])['errors'], true), 'impossible related date is rejected');
    $assert(in_array('due_date_invalid', TasksService::validateCreateInput(['task_title' => 'T', 'due_date' => '2026-02-30'])['errors'], true), 'impossible due date is rejected');
    $assert(in_array('task_title_required', TasksService::validateCreateInput([])['errors'], true), 'missing task title is rejected');

    $assert(!str_contains($serviceSource, '(string)($input[') && !str_contains($serviceSource, '(int)($input['), 'Task persistence has no pre-validation scalar coercion');
    $assert(str_contains($serviceSource, 'public static function validateCreateInput') && str_contains($controllerSource, 'TasksService::validateCreateInput($_POST)'), 'service and controller share one complete Task input contract');
    $assert(str_contains($schemaSource, 'VARCHAR(190)') && str_contains($schemaSource, 'VARCHAR(80)') && str_contains($schemaSource, 'VARCHAR(40)'), 'validation contract is aligned to the existing Task schema');
    $assert(str_contains($routesSource, "Auth::requireAppAccess('sbaio')") && strpos($routesSource, "Auth::requireAppAccess('sbaio')") < strpos($routesSource, 'TasksController::create'), 'existing SBAIO app authorization remains before Task creation');

    DB::$queries = [];
    DB::$connection->events = [];
    DB::$connection->insert_id = 417;
    TasksService::create([
        'task_title' => 'Prepare report',
        'owner_name' => 'Operations',
        'task_type' => 'follow-up',
        'task_bucket' => 'weekly',
        'related_staff_id' => null,
        'related_date' => '2026-10-26',
        'task_status' => 'legacy-status',
        'priority' => 'urgent',
        'due_date' => '2026-12-31',
    ]);
    $assert(count(DB::$queries) === 1, 'valid Task performs one insert');
    $assert(DB::$queries[0]['params'] === ['Prepare report', 'Operations', 'follow-up', 'weekly', null, '2026-10-26', 'legacy-status', 'urgent', '2026-12-31'], 'unassigned Task INSERT receives the existing NULL Staff sentinel');
    $assert(DB::$connection->events === ['begin', 'commit'], 'valid Task commits the same-connection transaction after identity capture');

    foreach ([
        ['task_title' => ['bad'], 'related_staff_id' => '42'],
        ['task_title' => 'Unauthorized staff', 'related_staff_id' => '42'],
        ['task_title' => 'Bad staff', 'related_staff_id' => ['42']],
        ['task_title' => 'Bad date', 'related_date' => '2026-02-30'],
        ['task_title' => 'Bad status', 'task_status' => ['open']],
    ] as $invalidInput) {
        DB::$queries = [];
        try {
            TasksService::create($invalidInput);
            $assert(false, 'malformed Task input unexpectedly reached create success');
        } catch (\InvalidArgumentException) {
            $assert(DB::$queries === [], 'malformed Task input performs no SQL');
        }
    }

    echo 'Tasks input integrity probe: ' . $assertions . ' assertions, ' . $failures . " failures\n";
    exit($failures === 0 ? 0 : 1);
}

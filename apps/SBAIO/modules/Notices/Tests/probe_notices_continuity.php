<?php
declare(strict_types=1);

/**
 * Database-free OH-AUTO3-SBAIO-NOTICES-CONTINUITY-001 probe.
 *
 * Exercises transaction/identity and exact-ID retrieval contracts with an
 * in-memory DB double. No application bootstrap or installed DB is loaded.
 */
namespace App\Core {
    final class NoticeDbConnection
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
        public static NoticeDbConnection $connection;
        public static array $queries = [];
        public static ?array $fetchOneRow = null;
        public static bool $throwOnQuery = false;

        public static function conn(): NoticeDbConnection
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
    require_once __DIR__ . '/../Services/NoticesService.php';

    use App\Core\DB;
    use Plugins\Notices\Services\NoticesService;

    DB::$connection = new \App\Core\NoticeDbConnection();
    $assertions = 0;
    $failures = 0;
    $assert = static function (bool $condition, string $message) use (&$assertions, &$failures): void {
        $assertions++;
        if (!$condition) {
            $failures++;
            fwrite(STDERR, "FAIL: {$message}\n");
        }
    };

    $serviceSource = file_get_contents(__DIR__ . '/../Services/NoticesService.php') ?: '';
    $controllerSource = file_get_contents(__DIR__ . '/../Controllers/NoticesController.php') ?: '';
    $viewSource = file_get_contents(__DIR__ . '/../Views/index.php') ?: '';
    $routesSource = file_get_contents(__DIR__ . '/../routes.php') ?: '';
    $schemaSource = file_get_contents(__DIR__ . '/../install.php') ?: '';

    $input = [
        'title' => 'Continuity notice',
        'body' => 'Restricted body text.',
        'notice_type' => 'reminder',
        'target_period' => '2026-10',
        'related_staff_id' => null,
        'requires_ack' => '1',
        'notice_level' => 'warning',
        'is_active' => '1',
    ];

    DB::$queries = [];
    DB::$connection->events = [];
    DB::$connection->insert_id = 417;
    $createdId = NoticesService::create($input);
    $assert($createdId === 417, 'creation returns the same connection positive generated ID');
    $assert(DB::$connection->events === ['begin', 'commit'], 'successful creation commits after identity verification');
    $assert(count(DB::$queries) === 1 && DB::$queries[0]['method'] === 'query', 'successful creation performs one insert');
    $assert(DB::$queries[0]['params'] === [
        'Continuity notice',
        'Restricted body text.',
        'reminder',
        '2026-10',
        null,
        1,
        'warning',
        1,
    ], 'existing Notice INSERT receives normalized values in unchanged column order');

    foreach ([0, '0', -4, '4x', '1.5', '1e2', ['417'], true] as $identity) {
        DB::$queries = [];
        DB::$connection->events = [];
        DB::$connection->insert_id = $identity;
        try {
            NoticesService::create($input);
            $assert(false, 'invalid generated Notice identity unexpectedly reported success');
        } catch (\Throwable) {
            $assert(DB::$queries !== [] && DB::$connection->events === ['begin', 'rollback'], 'invalid generated Notice identity rolls back before success');
        }
    }

    DB::$queries = [];
    DB::$connection->events = [];
    DB::$connection->insert_id = 418;
    DB::$connection->commitResult = false;
    try {
        NoticesService::create($input);
        $assert(false, 'Notice commit failure unexpectedly reported success');
    } catch (\Throwable) {
        $assert(DB::$connection->events === ['begin', 'commit', 'rollback'], 'Notice commit failure rolls back the same transaction');
    }
    DB::$connection->commitResult = true;

    DB::$queries = [];
    DB::$connection->events = [];
    DB::$connection->insert_id = 419;
    DB::$throwOnQuery = true;
    try {
        NoticesService::create($input);
        $assert(false, 'Notice insert failure unexpectedly reported success');
    } catch (\Throwable) {
        $assert(DB::$connection->events === ['begin', 'rollback'], 'Notice insert failure rolls back the same transaction');
    }
    DB::$throwOnQuery = false;

    DB::$queries = [];
    DB::$fetchOneRow = [
        'id' => 417,
        'title' => 'Continuity notice',
        'notice_type' => 'reminder',
        'target_period' => '2026-10',
        'notice_level' => 'warning',
        'requires_ack' => 1,
        'is_active' => 1,
        'created_at' => '2026-10-26 12:00:00',
    ];
    $record = NoticesService::findById('417');
    $assert($record['id'] === 417 && $record['title'] === 'Continuity notice', 'exact retrieval returns the selected Notice record');
    $assert(!array_key_exists('body', $record) && !array_key_exists('related_staff_id', $record), 'exact retrieval excludes body and Staff linkage fields');
    $assert(count(DB::$queries) === 1 && DB::$queries[0]['params'] === [417], 'exact retrieval binds the positive ID as a parameter');
    $assert(str_contains(DB::$queries[0]['sql'], 'FROM sbaio_notices WHERE id = ? LIMIT 1'), 'exact retrieval uses bounded identity SQL');
    $assert(!str_contains(DB::$queries[0]['sql'], 'body') && !str_contains(DB::$queries[0]['sql'], 'related_staff_id'), 'exact retrieval SQL does not select restricted fields');

    DB::$queries = [];
    DB::$fetchOneRow = null;
    $assert(NoticesService::findById(999999) === null && count(DB::$queries) === 1, 'missing exact Notice returns null without latest-row inference');
    foreach (['0', '-1', '417x', '1.5', '1e2', ['417'], true, 0, -2] as $invalidId) {
        DB::$queries = [];
        try {
            NoticesService::findById($invalidId);
            $assert(false, 'malformed Notice ID unexpectedly succeeded');
        } catch (\InvalidArgumentException) {
            $assert(DB::$queries === [], 'malformed Notice ID performs no database read');
        }
    }

    $assert(str_contains($serviceSource, 'DB::conn()') && str_contains($serviceSource, 'begin_transaction') && str_contains($serviceSource, 'insert_id') && str_contains($serviceSource, 'rollback'), 'Notice creation owns transaction and generated-identity guarantees');
    $assert(str_contains($serviceSource, 'public static function findById') && str_contains($serviceSource, 'WHERE id = ? LIMIT 1'), 'Notice service owns exact-ID retrieval');
    $assert(str_contains($controllerSource, 'NoticesService::findById') && str_contains($controllerSource, "'notice_id' => \$createdId"), 'controller carries exact created ID through the existing Notice surface');
    $assert(str_contains($viewSource, 'data-sbaio-notices-created-record') && str_contains($viewSource, 'data-sbaio-notices-exact-record'), 'Notice interface renders created and exact-record continuity states');
    $assert(str_contains($viewSource, 'record_scope_note') && !str_contains($viewSource, '$exactNotice[\'body\']') && !str_contains($viewSource, '$exactNotice[\'related_staff_id\']'), 'Notice view documents and enforces restricted-field omission');
    $assert(str_contains($routesSource, "Auth::requireAppAccess('sbaio')") && strpos($routesSource, "Auth::requireAppAccess('sbaio')") < strpos($routesSource, 'NoticesController::create'), 'existing SBAIO app authorization precedes Notice creation');
    $assert(str_contains($schemaSource, 'id INT AUTO_INCREMENT PRIMARY KEY') && str_contains($schemaSource, 'body TEXT NULL') && str_contains($schemaSource, 'related_staff_id INT NULL'), 'continuity uses the existing generated-ID schema and restricted fields');
    $assert(!str_contains($serviceSource . $controllerSource, 'ORDER BY id DESC LIMIT 1') && !str_contains($serviceSource . $controllerSource, 'WHERE title ='), 'Notice identity never falls back to latest-row or title inference');

    echo 'Notices continuity probe: ' . $assertions . ' assertions, ' . $failures . " failures\n";
    exit($failures === 0 ? 0 : 1);
}

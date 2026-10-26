<?php
declare(strict_types=1);

/**
 * Database-free OH-AUTO3-SBAIO-NOTICES-INTEGRITY-001 probe.
 *
 * Uses an in-memory DB double and never loads application bootstrap or an
 * installed/configured database.
 */
namespace App\Core {
    final class NoticeInputDbConnection
    {
        public int $insert_id = 1;

        public function begin_transaction(): bool
        {
            return true;
        }

        public function commit(): bool
        {
            return true;
        }

        public function rollback(): bool
        {
            return true;
        }
    }

    final class DB
    {
        public static NoticeInputDbConnection $connection;
        public static array $queries = [];

        public static function conn(): NoticeInputDbConnection
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
    require_once __DIR__ . '/../Services/NoticesService.php';

    use App\Core\DB;
    use Plugins\Notices\Services\NoticesService;

    DB::$connection = new \App\Core\NoticeInputDbConnection();
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
    $routeSource = file_get_contents(__DIR__ . '/../routes.php') ?: '';
    $schemaSource = file_get_contents(__DIR__ . '/../install.php') ?: '';

    $valid = [
        'title' => 'Payroll close reminder',
        'body' => 'Please review the close checklist.',
        'notice_type' => 'reminder',
        'target_period' => '2026-10',
        'requires_ack' => '1',
        'notice_level' => 'warning',
        'is_active' => '1',
    ];
    $normalized = NoticesService::validateCreateInput($valid);
    $assert($normalized['valid'] === true && $normalized['errors'] === [], 'valid Notice input is accepted');
    $assert($normalized['values']['related_staff_id'] === null, 'unassigned Notice input preserves the NULL Staff sentinel');
    $assert($normalized['values']['requires_ack'] === 1 && $normalized['values']['is_active'] === 1, 'string 1 flags normalize to enabled integer flags');

    DB::$queries = [];
    NoticesService::create($valid);
    $assert(count(DB::$queries) === 1, 'valid Notice performs exactly one INSERT');
    $assert(DB::$queries[0]['params'] === [
        'Payroll close reminder',
        'Please review the close checklist.',
        'reminder',
        '2026-10',
        null,
        1,
        'warning',
        1,
    ], 'valid Notice INSERT receives exact normalized values');

    $defaults = NoticesService::validateCreateInput(['title' => 'Default flags']);
    $assert($defaults['valid'] === true && $defaults['values']['requires_ack'] === 0 && $defaults['values']['is_active'] === 0, 'missing checkbox fields preserve existing unchecked POST semantics');
    $assert($defaults['values']['notice_level'] === 'info' && $defaults['values']['related_staff_id'] === null, 'blank optional values preserve the existing info and unassigned defaults');

    foreach (['title', 'body', 'notice_type', 'target_period', 'notice_level'] as $field) {
        foreach ([[], ['value' => true], ['value' => 7], ['value' => new \stdClass()]] as $case) {
            $input = ['title' => 'Valid title'];
            if ($field === 'title') {
                $input['title'] = $case['value'] ?? $case;
            } else {
                $input[$field] = $case['value'] ?? $case;
            }
            DB::$queries = [];
            $result = NoticesService::validateCreateInput($input);
            $assert($result['valid'] === false && $result['errors'] !== [], $field . ' rejects non-string supplied values');
            try {
                NoticesService::create($input);
                $assert(false, $field . ' malformed input unexpectedly wrote a Notice');
            } catch (\InvalidArgumentException) {
                $assert(DB::$queries === [], $field . ' malformed input is rejected before database write');
            }
        }
    }

    foreach (['3x', '1.5', '1e2', '-4', true, false, 3.5, ['42'], new \stdClass()] as $staffId) {
        DB::$queries = [];
        $result = NoticesService::validateCreateInput(['title' => 'Staff boundary', 'related_staff_id' => $staffId]);
        $assert($result['valid'] === false && in_array('related_staff_id_invalid', $result['errors'], true), 'malformed Staff ID is rejected before SQL');
        try {
            NoticesService::create(['title' => 'Staff boundary', 'related_staff_id' => $staffId]);
            $assert(false, 'malformed Staff ID unexpectedly wrote a Notice');
        } catch (\InvalidArgumentException) {
            $assert(DB::$queries === [], 'malformed Staff ID causes zero database writes');
        }
    }
    foreach (['42', '00042', 42] as $staffId) {
        DB::$queries = [];
        $result = NoticesService::validateCreateInput(['title' => 'Staff association', 'related_staff_id' => $staffId]);
        $assert($result['valid'] === false && in_array('related_staff_id_unavailable', $result['errors'], true) && $result['values']['related_staff_id'] === null, 'positive Staff association is rejected before SQL');
        try {
            NoticesService::create(['title' => 'Staff association', 'related_staff_id' => $staffId]);
            $assert(false, 'unauthorized Staff association unexpectedly wrote a Notice');
        } catch (\InvalidArgumentException) {
            $assert(DB::$queries === [], 'unauthorized Staff association causes zero database writes');
        }
    }
    foreach (['', '0', '000', 0] as $unassigned) {
        $result = NoticesService::validateCreateInput(['title' => 'Unassigned', 'related_staff_id' => $unassigned]);
        $assert($result['valid'] === true && $result['values']['related_staff_id'] === null, 'blank and zero Staff sentinels remain unassigned');
    }

    foreach (['requires_ack', 'is_active'] as $flag) {
        foreach (['false', 'true', 'yes', '2', '-1', true, false, 2, -1, 1.0, [], ['1']] as $malformed) {
            DB::$queries = [];
            $result = NoticesService::validateCreateInput(['title' => 'Flag boundary', $flag => $malformed]);
            $assert($result['valid'] === false && in_array($flag . '_invalid', $result['errors'], true), $flag . ' rejects malformed flag value');
            try {
                NoticesService::create(['title' => 'Flag boundary', $flag => $malformed]);
                $assert(false, $flag . ' malformed value unexpectedly wrote a Notice');
            } catch (\InvalidArgumentException) {
                $assert(DB::$queries === [], $flag . ' malformed value causes zero database writes');
            }
        }
        foreach (['0', '1', 0, 1] as $flagValue) {
            $result = NoticesService::validateCreateInput(['title' => 'Valid flag', $flag => $flagValue]);
            $assert($result['valid'] === true && $result['values'][$flag] === (int)$flagValue, $flag . ' accepts only exact 0/1 scalar values');
        }
    }

    $lengthCases = [
        'title' => 191,
        'notice_type' => 81,
        'target_period' => 41,
        'notice_level' => 41,
    ];
    foreach ($lengthCases as $field => $length) {
        $input = ['title' => 'Valid title', $field => str_repeat('x', $length)];
        $result = NoticesService::validateCreateInput($input);
        $assert($result['valid'] === false && $result['errors'] !== [], $field . ' enforces the existing schema length boundary');
    }
    $emptyTitle = NoticesService::validateCreateInput(['title' => '   ']);
    $assert($emptyTitle['valid'] === false && in_array('title_required', $emptyTitle['errors'], true), 'blank title remains required');

    $assert(str_contains($serviceSource, 'public static function validateCreateInput') && str_contains($serviceSource, 'public static function create'), 'NoticesService owns validation and persistence entry points');
    $assert(!str_contains($serviceSource, "(int)(\$input['related_staff_id']") && !str_contains($serviceSource, '!empty($input[\'requires_ack\'])') && !str_contains($serviceSource, '!empty($input[\'is_active\'])'), 'silent Staff and flag coercions are removed');
    $assert(str_contains($controllerSource, 'NoticesService::validateCreateInput') && str_contains($controllerSource, 'Auth::requireCsrf'), 'controller validates raw POST after the existing CSRF boundary');
    $assert(str_contains($viewSource, 'name="requires_ack" value="1"') && str_contains($viewSource, 'name="is_active" value="1" checked'), 'existing form checkbox contract remains exact 1/checked');
    $assert(str_contains($routeSource, "Auth::requireAppAccess('sbaio')") && strpos($routeSource, "Auth::requireAppAccess('sbaio')") < strpos($routeSource, 'NoticesController::create'), 'existing SBAIO app authorization remains before Notice creation');
    $assert(str_contains($schemaSource, 'related_staff_id INT NULL') && str_contains($schemaSource, 'requires_ack TINYINT(1) NOT NULL DEFAULT 0') && str_contains($schemaSource, 'is_active TINYINT(1) NOT NULL DEFAULT 1'), 'validation preserves the existing Notice schema flags and Staff reference');

    echo 'Notices input integrity probe: ' . $assertions . ' assertions, ' . $failures . " failures\n";
    exit($failures === 0 ? 0 : 1);
}

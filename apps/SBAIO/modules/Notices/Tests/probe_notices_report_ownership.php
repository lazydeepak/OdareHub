<?php
declare(strict_types=1);

/**
 * Database-free OH-AUTO3-SBAIO-NOTICES-REPORT-001 probe.
 *
 * Verifies report data ownership and presentation boundaries with an in-memory
 * DB double. No application bootstrap or installed DB is loaded.
 */
namespace App\Core {
    final class DB
    {
        public static array $queries = [];
        public static mixed $fetchOneRow = null;
        public static array $fetchAllRows = [];
        public static bool $throwOnRead = false;

        public static function fetchOne(string $sql, array $params = []): ?array
        {
            if (self::$throwOnRead) {
                throw new \RuntimeException('report read failed');
            }
            self::$queries[] = ['method' => 'fetchOne', 'sql' => $sql, 'params' => $params];
            return self::$fetchOneRow;
        }

        public static function fetchAll(string $sql, array $params = []): array
        {
            if (self::$throwOnRead) {
                throw new \RuntimeException('report read failed');
            }
            self::$queries[] = ['method' => 'fetchAll', 'sql' => $sql, 'params' => $params];
            return array_shift(self::$fetchAllRows) ?? [];
        }
    }
}

namespace {
    require_once __DIR__ . '/../Services/NoticesService.php';

    use App\Core\DB;
    use Plugins\Notices\Services\NoticesService;

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
    $routeSource = file_get_contents(__DIR__ . '/../routes.php') ?: '';
    $viewSource = file_get_contents(__DIR__ . '/../Views/report.php') ?: '';
    $registrySource = file_get_contents(__DIR__ . '/../../../Services/ModuleReportRegistryService.php') ?: '';

    DB::$queries = [];
    DB::$fetchOneRow = [
        'total' => 4,
        'active' => 3,
        'ack_required' => 2,
        'new_30d' => 1,
    ];
    DB::$fetchAllRows = [
        [
            ['notice_type' => 'reminder', 'n' => 2],
            ['notice_type' => 'alert', 'n' => 1],
        ],
        [
            ['notice_level' => 'info', 'n' => 3],
        ],
        [
            [
                'id' => 9,
                'title' => 'Newest notice',
                'notice_type' => 'reminder',
                'notice_level' => 'info',
                'is_active' => 1,
                'requires_ack' => 0,
                'created_at' => '2026-10-26 12:00:00',
            ],
        ],
    ];
    $reportData = NoticesService::reportData();
    $assert($reportData['summary']['total'] === 4 && $reportData['summary']['active'] === 3, 'report summary preserves prepared aggregate values');
    $assert($reportData['byType'][0]['notice_type'] === 'reminder' && $reportData['byLevel'][0]['notice_level'] === 'info' && $reportData['recent'][0]['id'] === 9, 'report collections preserve existing output shape');
    $assert(count(DB::$queries) === 4 && DB::$queries[0]['method'] === 'fetchOne', 'report service performs exactly the existing four reads in order');
    $assert(
        str_contains(DB::$queries[0]['sql'], 'DATE_SUB(CURDATE(), INTERVAL 30 DAY)')
        && str_contains(DB::$queries[1]['sql'], 'GROUP BY notice_type ORDER BY n DESC')
        && str_contains(DB::$queries[3]['sql'], 'ORDER BY created_at DESC, id DESC LIMIT 25'),
        'report service preserves exact aggregate, distribution, and recent SQL contracts'
    );

    DB::$queries = [];
    DB::$fetchOneRow = null;
    DB::$fetchAllRows = [];
    DB::$throwOnRead = true;
    $unavailable = NoticesService::reportData();
    $assert($unavailable['summary']['total'] === 0 && $unavailable['summary']['active'] === 0, 'temporary report read failure preserves default summary payload');
    $assert($unavailable['byType'] === [] && $unavailable['byLevel'] === [] && $unavailable['recent'] === [], 'temporary report read failure preserves empty collections');
    DB::$throwOnRead = false;

    $assert(str_contains($serviceSource, 'public static function reportData'), 'Notices service owns report data preparation');
    $assert(str_contains($controllerSource, "'reportData' => NoticesService::reportData()"), 'controller supplies prepared Notice report data');
    $assert(str_contains($routeSource, 'NoticesController::report($view, $report)'), 'route delegates admitted report rendering through the controller');
    $assert(!str_contains($viewSource, 'DB::') && !str_contains($viewSource, 'sbaio_notices') && !str_contains($viewSource, 'ModuleReportRegistryService'), 'Notice report view performs zero database and registry operations');
    $assert(str_contains($viewSource, '$reportData') && str_contains($viewSource, '$byType') && str_contains($viewSource, '$byLevel') && str_contains($viewSource, '$recent'), 'Notice report view consumes prepared report data');
    $assert(str_contains($routeSource, "Auth::requireAppAccess('sbaio')") && strpos($routeSource, "Auth::requireAppAccess('sbaio')") < strpos($routeSource, 'ModuleReportRegistryService::findActiveReport'), 'existing SBAIO app authorization precedes report admission');
    $assert(str_contains($routeSource, "ModuleReportRegistryService::findActiveReport('sbaio.notices.overview')") && strpos($routeSource, 'ModuleReportRegistryService::findActiveReport') < strpos($routeSource, 'NoticesController::report($view, $report)'), 'active registry admission precedes report data preparation and rendering');
    $assert(str_contains($registrySource, "'sbaio.notices.overview'") && str_contains($registrySource, "'table' => 'sbaio_notices'"), 'existing registry descriptor remains the report and export source of truth');
    $reportStart = strpos($serviceSource, 'public static function reportData');
    $reportEnd = strpos($serviceSource, '/**', $reportStart + 1);
    $reportMethod = substr($serviceSource, $reportStart, $reportEnd === false ? null : $reportEnd - $reportStart);
    $assert(str_contains($reportMethod, 'SELECT id, title, notice_type, notice_level, is_active, requires_ack, created_at') && !str_contains($reportMethod, 'related_staff_id'), 'report recent projection preserves the existing privacy boundary');
    $assert(!preg_match('/\bbody\b/i', $viewSource) && !str_contains($viewSource, 'related_staff_id'), 'report presentation does not expose Notice body or Staff linkage');
    $assert(!str_contains($routeSource, 'reportData') && !str_contains($routeSource, 'DB::'), 'report route retains admission only and does not duplicate SQL');
    $assert(!str_contains($controllerSource, 'ModuleReportRegistryService') && !str_contains($controllerSource, 'DB::'), 'report controller prepares owner data without registry or direct SQL operations');

    echo 'Notices report ownership probe: ' . $assertions . ' assertions, ' . $failures . " failures\n";
    exit($failures === 0 ? 0 : 1);
}

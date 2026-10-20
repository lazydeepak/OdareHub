<?php
declare(strict_types=1);

/**
 * Static/mock-only proof of Hospitality reservation modification boundaries.
 * No application bootstrap, database connection, or SQL execution occurs.
 */

$root = dirname(__DIR__, 3);
$servicePath = $root . '/apps/Hospitality/Services/ReservationsService.php';
$frontDeskPath = $root . '/apps/Hospitality/Services/FrontDeskService.php';
$controllerPath = $root . '/apps/Hospitality/Controllers/ReservationsController.php';
$operatorPath = $root . '/apps/Hospitality/Controllers/OperatorActions.php';
$routesPath = $root . '/apps/Hospitality/routes.php';
$service = (string)file_get_contents($servicePath);
$frontDesk = (string)file_get_contents($frontDeskPath);
$controller = (string)file_get_contents($controllerPath);
$operator = (string)file_get_contents($operatorPath);
$routes = (string)file_get_contents($routesPath);

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

$check(str_contains($service, 'public static function create('), 'canonical reservation create method exists');
$check(str_contains($service, 'public static function transition('), 'canonical reservation transition method exists');
$check(str_contains($service, 'self::assertNoOverlap($roomId, $checkIn, $checkOut);'), 'assigned create uses canonical overlap validation');
$check(str_contains($service, "DB::query('START TRANSACTION');"), 'assigned create starts a transaction');
$check(str_contains($service, 'FOR UPDATE'), 'assigned create locks the room row before overlap evaluation');
$check(str_contains($service, "DB::query('COMMIT');"), 'create commits the reservation transaction');
$check(str_contains($service, "DB::query('ROLLBACK');"), 'create rolls back failed reservation work');
$check(str_contains($service, 'DB::conn()->insert_id'), 'create reads inserted ID from the current database connection');
$check(!str_contains($service, 'SELECT id FROM hosp_reservations ORDER BY id DESC LIMIT 1'), 'create does not use global latest-row identity lookup');
$check(str_contains($service, 'if ($reservationId <= 0)'), 'create rejects a missing or invalid generated ID');
$check(str_contains($service, "DB::query('START TRANSACTION');") && str_contains($service, '$transactionStarted = true;'), 'assigned and unassigned creation enter the same transaction boundary');
$insertIdPosition = strpos($service, 'DB::conn()->insert_id');
$commitPosition = strpos($service, "DB::query('COMMIT');");
$check($insertIdPosition !== false && $commitPosition !== false && $insertIdPosition < $commitPosition, 'generated ID is validated before transaction commit');
$check(str_contains($service, '$roomAssigned ? $roomId : null'), 'unassigned-room inserts remain explicitly nullable');
$check(str_contains($service, "reservation_status IN ('booked','checked_in')"), 'overlap considers only active reservation states');
$check(str_contains($service, 'check_in_date < ? AND check_out_date > ?'), 'overlap uses adjacent-range-safe half-open predicate');
$check(str_contains($service, "'booked' => ['checked_in', 'cancelled', 'no_show']"), 'booked lifecycle transitions are explicit');
$check(str_contains($service, "'checked_in' => ['checked_out']"), 'checked-in lifecycle transition is explicit');
$check(str_contains($service, 'AND reservation_status=\'booked\''), 'check-in uses compare-and-set status guard');
$check(str_contains($service, 'AND reservation_status=\'checked_in\''), 'checkout uses compare-and-set status guard');
$check(str_contains($service, 'WHERE id=? AND reservation_status=? LIMIT 1'), 'terminal transitions use compare-and-set status guard');
$check(!preg_match('/public static function\s+(update|reassign|extend|reactivate)\w*\s*\(/i', $service), 'no unsupported reservation modification API exists');
$check(substr_count($controller, 'Auth::requireCsrf') === 2, 'both reservation POST handlers require CSRF');
$check(substr_count($controller, 'HospitalityAccess::requireManage') === 2, 'both reservation POST handlers require manage access');
$check(str_contains($controller, 'ReservationsService::create($_POST)'), 'reservation create controller delegates to service');
$check(str_contains($controller, 'ReservationsService::transition('), 'reservation transition controller delegates to service');
$check(str_contains($frontDesk, 'ReservationsService::transition($reservationId, \'checked_in\')'), 'FrontDesk check-in delegates to lifecycle service');
$check(str_contains($frontDesk, 'ReservationsService::transition($reservationId, \'checked_out\')'), 'FrontDesk checkout delegates to lifecycle service');
$check(str_contains($frontDesk, 'ReservationsService::transition($reservationId, \'cancelled\')'), 'FrontDesk cancellation delegates to lifecycle service');
$check(str_contains($frontDesk, 'ReservationsService::transition($reservationId, \'no_show\')'), 'FrontDesk no-show delegates to lifecycle service');
$check(str_contains($operator, 'FrontDeskService::cancelReservation(')
    && str_contains($operator, 'FrontDeskService::markReservationNoShow('), 'operator cancellation/no-show uses FrontDesk delegation');
$check(str_contains($routes, '/apps/hospitality/reservations/create') && str_contains($routes, '/apps/hospitality/reservations/transition'), 'only create and transition reservation POST routes are registered');
$check(!str_contains($routes, '/apps/hospitality/reservations/update') && !str_contains($routes, '/apps/hospitality/reservations/reassign'), 'no unsupported reservation modification routes are registered');

$sourceRoot = $root . '/apps/Hospitality';
$directBypass = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    if (str_contains($path, '/Tests/') || $path === $servicePath) {
        continue;
    }
    $contents = (string)file_get_contents($path);
    if (str_contains($contents, 'UPDATE hosp_reservations') || str_contains($contents, 'INSERT INTO hosp_reservations')) {
        $directBypass[] = $path;
    }
}
$check($directBypass === [], 'no alternate Hospitality source directly writes reservations');

echo "RESULT passed={$passed} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);

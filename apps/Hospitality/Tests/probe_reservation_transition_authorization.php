<?php
declare(strict_types=1);

/**
 * Static/mock-only proof of Hospitality reservation transition authorization.
 * No application bootstrap, database connection, or SQL execution occurs.
 */

$root = dirname(__DIR__, 3);
$accessPath = $root . '/apps/Hospitality/Controllers/HospitalityAccess.php';
$reservationControllerPath = $root . '/apps/Hospitality/Controllers/ReservationsController.php';
$frontDeskControllerPath = $root . '/apps/Hospitality/Controllers/FrontDeskController.php';
$operatorActionsPath = $root . '/apps/Hospitality/Controllers/OperatorActions.php';
$hospitalityRoutesPath = $root . '/apps/Hospitality/routes.php';
$shellRoutesPath = $root . '/apps/Shell/routes.php';
$servicePath = $root . '/apps/Hospitality/Services/ReservationsService.php';
$frontDeskServicePath = $root . '/apps/Hospitality/Services/FrontDeskService.php';

$read = static fn (string $path): string => (string)file_get_contents($path);
$access = $read($accessPath);
$reservationController = $read($reservationControllerPath);
$frontDeskController = $read($frontDeskControllerPath);
$operatorActions = $read($operatorActionsPath);
$hospitalityRoutes = $read($hospitalityRoutesPath);
$shellRoutes = $read($shellRoutesPath);
$service = $read($servicePath);
$frontDeskService = $read($frontDeskServicePath);

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

$methodBody = static function (string $source, string $method): string {
    $quoted = preg_quote($method, '/');
    if (preg_match('/public static function ' . $quoted . '\\s*\\([^)]*\\):?[^\\{]*\\{(.*?)\\n    \\}/s', $source, $match) !== 1) {
        return '';
    }
    return (string)$match[1];
};

$check(str_contains($access, "AclPolicy::can('hospitality.manage', Auth::user())"), 'manage guard checks the canonical Hospitality permission');
$check(str_contains($access, 'http_response_code(403)') && str_contains($access, 'exit;'), 'manage denial is server-side 403 and terminates execution');

$reservationTransition = $methodBody($reservationController, 'transition');
$check($reservationTransition !== '', 'admin reservation transition handler exists');
$check(str_contains($reservationTransition, 'HospitalityAccess::requireManage($view)'), 'admin transition requires manage authorization');
$check(str_contains($reservationTransition, 'Auth::requireCsrf('), 'admin transition requires CSRF');
$check(str_contains($reservationTransition, 'ReservationsService::transition('), 'admin transition delegates to ReservationsService');
$check(strpos($reservationTransition, 'HospitalityAccess::requireManage') < strpos($reservationTransition, 'ReservationsService::transition'), 'admin authorization precedes transition dispatch');

foreach ([
    'checkin' => 'FrontDeskService::checkIn(',
    'checkout' => 'FrontDeskService::checkout(',
] as $method => $delegate) {
    $body = $methodBody($frontDeskController, $method);
    $check($body !== '', "FrontDesk {$method} handler exists");
    $check(str_contains($body, 'HospitalityAccess::requireManage($view'), "FrontDesk {$method} requires manage authorization");
    $check(str_contains($body, 'Auth::requireCsrf('), "FrontDesk {$method} requires CSRF");
    $check(str_contains($body, $delegate), "FrontDesk {$method} delegates to its owning service");
    $check(strpos($body, 'HospitalityAccess::requireManage') < strpos($body, $delegate), "FrontDesk {$method} authorizes before dispatch");
}

$operatorMethods = [
    'frontDeskCheckIn' => 'FrontDeskService::checkIn(',
    'frontDeskCheckOut' => 'FrontDeskService::checkout(',
    'frontDeskCancelReservation' => 'FrontDeskService::cancelReservation(',
    'frontDeskMarkNoShow' => 'FrontDeskService::markReservationNoShow(',
];
foreach ($operatorMethods as $method => $delegate) {
    $body = $methodBody($operatorActions, $method);
    $check($body !== '', "operator {$method} handler exists");
    $check(str_contains($body, "AclPolicy::can('hospitality.manage', Auth::user())"), "operator {$method} re-checks manage authorization");
    $check(str_contains($body, 'Auth::requireCsrf('), "operator {$method} requires CSRF");
    $check(str_contains($body, $delegate), "operator {$method} delegates to FrontDeskService");
    $check(strpos($body, "AclPolicy::can('hospitality.manage'") < strpos($body, $delegate), "operator {$method} authorizes before dispatch");
}

$check(str_contains($hospitalityRoutes, "'/apps/hospitality/reservations/transition'"), 'admin transition route is registered only through the app controller');
$check(str_contains($hospitalityRoutes, 'ReservationsController::transition($view)'), 'admin route dispatches the guarded controller');

foreach ([
    '/u/hospitality/front-desk/check-in' => 'OperatorActions::frontDeskCheckIn',
    '/u/hospitality/front-desk/check-out' => 'OperatorActions::frontDeskCheckOut',
    '/u/hospitality/front-desk/cancel' => 'OperatorActions::frontDeskCancelReservation',
    '/u/hospitality/front-desk/no-show' => 'OperatorActions::frontDeskMarkNoShow',
] as $route => $handler) {
    $position = strpos($shellRoutes, "'$route'");
    $tail = $position === false ? '' : substr($shellRoutes, $position, 1800);
    $check($position !== false, "operator route {$route} exists");
    $check(str_contains($tail, '$resolveOperatorPostRoute'), "operator route {$route} resolves authenticated post context");
    $check(str_contains($tail, 'hash_equals('), "operator route {$route} enforces self-workspace identity binding");
    $check(str_contains($tail, $handler), "operator route {$route} dispatches the app action");
}

$check(str_contains($service, 'public static function transition('), 'transition remains owned by ReservationsService');
$check(str_contains($service, 'WHERE id=? AND reservation_status=\'booked\''), 'checked-in transition retains CAS state guard');
$check(str_contains($service, 'WHERE id=? AND reservation_status=\'checked_in\''), 'checked-out transition retains CAS state guard');
$check(str_contains($service, 'WHERE id=? AND reservation_status=? LIMIT 1'), 'terminal transitions retain CAS state guard');
$check(!str_contains($service, 'AclPolicy::can('), 'domain service does not create a competing ACL policy');
$check(!str_contains($frontDeskService, 'AclPolicy::can('), 'FrontDesk domain service does not create a competing ACL policy');

$runtimeCallers = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/apps', FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    if (str_contains($path, '/Tests/') || $path === $servicePath || $path === $frontDeskServicePath) {
        continue;
    }
    $contents = $read($path);
    if (str_contains($contents, 'ReservationsService::transition(')) {
        $runtimeCallers[] = str_replace($root . '/', '', $path);
    }
}
$expectedCallers = [
    'apps/Hospitality/Controllers/ReservationsController.php',
];
sort($runtimeCallers);
sort($expectedCallers);
$check($runtimeCallers === $expectedCallers, 'no alternate application runtime caller invokes transition directly');

echo "RESULT passed={$passed} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);

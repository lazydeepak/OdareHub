<?php
declare(strict_types=1);

use App\Core\Auth;
use Apps\Hospitality\Controllers\FrontDeskController;
use Apps\Hospitality\Controllers\GuestsController;
use Apps\Hospitality\Controllers\HospitalityHomeController;
use Apps\Hospitality\Controllers\HousekeepingController;
use Apps\Hospitality\Controllers\ReservationsController;
use Apps\Hospitality\Controllers\RoomsController;

require_once __DIR__ . '/Controllers/HospitalityHomeController.php';
require_once __DIR__ . '/Controllers/HospitalityAccess.php';
require_once __DIR__ . '/Controllers/HospitalityFlash.php';
require_once __DIR__ . '/Controllers/HospitalityTables.php';
require_once __DIR__ . '/Services/RoomsService.php';
require_once __DIR__ . '/Services/GuestsService.php';
require_once __DIR__ . '/Services/HousekeepingService.php';
require_once __DIR__ . '/Controllers/RoomsController.php';
require_once __DIR__ . '/Controllers/GuestsController.php';
require_once __DIR__ . '/Controllers/ReservationsController.php';
require_once __DIR__ . '/Controllers/FrontDeskController.php';
require_once __DIR__ . '/Controllers/HousekeepingController.php';
require_once __DIR__ . '/bootstrap.php';

$view->addNamespace('hospitality', __DIR__ . '/Views');

$router->get('/apps/hospitality', function () use ($view) {
    Auth::requireAppAccess('hospitality');
    Auth::bootSession();
    HospitalityHomeController::index($view);
    return null;
});

$router->get('/apps/hospitality/rooms', function () use ($view) {
    Auth::requireAppAccess('hospitality');
    Auth::bootSession();
    RoomsController::index($view);
    return null;
});

$router->get('/apps/hospitality/guests', function () use ($view) {
    Auth::requireAppAccess('hospitality');
    Auth::bootSession();
    GuestsController::index($view);
    return null;
});

$router->get('/apps/hospitality/reservations', function () use ($view) {
    Auth::requireAppAccess('hospitality');
    Auth::bootSession();
    ReservationsController::index($view);
    return null;
});

$router->get('/apps/hospitality/front-desk', function () use ($view) {
    Auth::requireAppAccess('hospitality');
    Auth::bootSession();
    FrontDeskController::index($view);
    return null;
});

$router->get('/apps/hospitality/housekeeping', function () use ($view) {
    Auth::requireAppAccess('hospitality');
    Auth::bootSession();
    HousekeepingController::index($view);
    return null;
});

$router->post('/apps/hospitality/rooms/create', function () use ($view) {
    RoomsController::create($view);
    return null;
});

$router->post('/apps/hospitality/rooms/update', function () use ($view) {
    RoomsController::update($view);
    return null;
});

$router->post('/apps/hospitality/rooms/status', function () use ($view) {
    RoomsController::status($view);
    return null;
});

$router->post('/apps/hospitality/guests/create', function () use ($view) {
    GuestsController::create($view);
    return null;
});

$router->post('/apps/hospitality/guests/update', function () use ($view) {
    GuestsController::update($view);
    return null;
});

$router->post('/apps/hospitality/guests/status', function () use ($view) {
    GuestsController::status($view);
    return null;
});

$router->post('/apps/hospitality/reservations/create', function () use ($view) {
    ReservationsController::create($view);
    return null;
});

$router->post('/apps/hospitality/reservations/transition', function () use ($view) {
    ReservationsController::transition($view);
    return null;
});

$router->post('/apps/hospitality/front-desk/check-in', function () use ($view) {
    FrontDeskController::checkin($view);
    return null;
});

$router->post('/apps/hospitality/front-desk/check-out', function () use ($view) {
    FrontDeskController::checkout($view);
    return null;
});

$router->post('/apps/hospitality/front-desk/charges/add', function () use ($view) {
    FrontDeskController::addCharge($view);
    return null;
});

$router->post('/apps/hospitality/front-desk/charges/void', function () use ($view) {
    FrontDeskController::voidCharge($view);
    return null;
});

$router->post('/apps/hospitality/housekeeping/status', function () use ($view) {
    HousekeepingController::status($view);
    return null;
});

<?php
declare(strict_types=1);

use App\Core\Auth;
use Plugins\QRCode\Controllers\QRCodeController;

require_once __DIR__ . '/Controllers/QRCodeController.php';

$router->get('/qr/product/label', function () use ($view) {
    Auth::requireAdmin();
    Auth::bootSession();
    QRCodeController::productLabel($view, (int)($_GET['product_id'] ?? 0), [
        'copies' => (int)($_GET['copies'] ?? 8),
        'serial_number' => trim((string)($_GET['serial_number'] ?? '')),
        'machine_no' => trim((string)($_GET['machine_no'] ?? '')),
        'production_date' => trim((string)($_GET['production_date'] ?? '')),
        'case_number' => trim((string)($_GET['case_number'] ?? '')),
        'include_date' => ((string)($_GET['include_date'] ?? '0') === '1'),
        'include_serial_number' => ((string)($_GET['include_serial_number'] ?? '0') === '1'),
        'include_machine_no' => ((string)($_GET['include_machine_no'] ?? '0') === '1'),
        'include_case_number' => ((string)($_GET['include_case_number'] ?? '0') === '1'),
        'include_qty_per_case' => ((string)($_GET['include_qty_per_case'] ?? '0') === '1'),
        'include_case_spec' => ((string)($_GET['include_case_spec'] ?? '0') === '1'),
        'include_cases_per_pallet' => ((string)($_GET['include_cases_per_pallet'] ?? '0') === '1'),
    ]);
    return null;
});

$router->get('/qr/product/scan', function () use ($view) {
    Auth::bootSession();
    QRCodeController::scanProduct($view, (int)($_GET['product_id'] ?? 0), (string)($_GET['part_number'] ?? ''));
    return null;
});

$router->get('/qr/stock', function () use ($view) {
    Auth::requireAdmin();
    Auth::bootSession();
    QRCodeController::stockIndex($view);
    return null;
});

$router->get('/qr/stock/add', function () use ($view) {
    Auth::requireAdmin();
    Auth::bootSession();
    QRCodeController::stockAddForm($view);
    return null;
});

$router->post('/qr/stock/add', function () {
    Auth::requireAdmin();
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    QRCodeController::stockCreate($_POST);
    header('Location: /qr/stock');
    exit;
});

$router->get('/qr/report', function () use ($view) {
    Auth::requireAdmin();
    Auth::bootSession();
    $view->render('QRCode::report.php');
    return null;
});

$router->get('/qr/export', function () use ($view) {
    Auth::requireAdmin();
    Auth::bootSession();
    $view->render('QRCode::export.php');
    return null;
});

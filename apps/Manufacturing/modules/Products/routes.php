<?php
declare(strict_types=1);

use App\Core\Auth;
use Apps\Manufacturing\Services\ModuleReportRegistryService;
use Plugins\Products\Controllers\ProductsController;

require_once __DIR__ . '/Controllers/ProductsController.php';

$renderProducts = function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    ProductsController::index($view);
    return null;
};
$router->get('/apps/manufacturing/products', $renderProducts);
$router->get('/products', function() {
    $query = $_SERVER['QUERY_STRING'] ?? '';
    $target = '/apps/manufacturing/products' . ($query !== '' ? ('?' . $query) : '');
    header('Location: ' . $target, true, 302);
    exit;
});

$renderProduct360 = function() use ($view) {
    acl_require('products.360.view', '/products/360?id=' . (int)($_GET['id'] ?? 0));

    ProductsController::part360($view, (int)($_GET['id'] ?? 0));
    return null;
};
$router->get('/apps/manufacturing/products/360', $renderProduct360);
$router->get('/products/360', function() {
    $query = $_SERVER['QUERY_STRING'] ?? '';
    $target = '/apps/manufacturing/products/360' . ($query !== '' ? ('?' . $query) : '');
    header('Location: ' . $target, true, 302);
    exit;
});

$renderProductAdd = function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    ProductsController::addForm($view);
    return null;
};
$router->get('/apps/manufacturing/products/add', $renderProductAdd);
$router->get('/products/add', function() {
    header('Location: /apps/manufacturing/products/add', true, 302);
    exit;
});

$createProduct = function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    ProductsController::create($_POST);
    header('Location: /apps/manufacturing/products');
    exit;
};
$router->post('/apps/manufacturing/products/add', $createProduct);
$router->post('/products/add', $createProduct);

$createProductMold = function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    ProductsController::createMold($_POST);
    return null;
};
$router->post('/apps/manufacturing/products/molds/add', $createProductMold);
$router->post('/products/molds/add', $createProductMold);

$deleteProductMold = function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    ProductsController::deleteMold((int)($_POST['mold_id'] ?? 0), (int)($_POST['product_id'] ?? 0));
    return null;
};
$router->post('/apps/manufacturing/products/molds/delete', $deleteProductMold);
$router->post('/products/molds/delete', $deleteProductMold);

$createProductMaterialMap = function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    ProductsController::createMaterialMap($_POST);
    return null;
};
$router->post('/apps/manufacturing/products/materials/add', $createProductMaterialMap);
$router->post('/products/materials/add', $createProductMaterialMap);

$deleteProductMaterialMap = function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    ProductsController::deleteMaterialMap((int)($_POST['map_id'] ?? 0), (int)($_POST['product_id'] ?? 0));
    return null;
};
$router->post('/apps/manufacturing/products/materials/delete', $deleteProductMaterialMap);
$router->post('/products/materials/delete', $deleteProductMaterialMap);

$assignProductActiveMachine = function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    ProductsController::assignActiveMachine($_POST);
    return null;
};
$router->post('/apps/manufacturing/products/active-machine', $assignProductActiveMachine);
$router->post('/products/active-machine', $assignProductActiveMachine);

$assignProductResponsibleUser = function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    ProductsController::assignResponsibleUser($_POST);
    return null;
};
$router->post('/apps/manufacturing/products/responsible-user', $assignProductResponsibleUser);
$router->post('/products/responsible-user', $assignProductResponsibleUser);

$renderProductEdit = function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    ProductsController::editForm($view, (int)($_GET['id'] ?? 0));
    return null;
};
$router->get('/apps/manufacturing/products/edit', $renderProductEdit);
$router->get('/products/edit', function() {
    $query = $_SERVER['QUERY_STRING'] ?? '';
    $target = '/apps/manufacturing/products/edit' . ($query !== '' ? ('?' . $query) : '');
    header('Location: ' . $target, true, 302);
    exit;
});

$updateProduct = function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    ProductsController::update($_POST);
    header('Location: /apps/manufacturing/products');
    exit;
};
$router->post('/apps/manufacturing/products/edit', $updateProduct);
$router->post('/products/edit', $updateProduct);

$deleteProduct = function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    ProductsController::delete((int)($_POST['id'] ?? 0));
    header('Location: /apps/manufacturing/products');
    exit;
};
$router->post('/apps/manufacturing/products/delete', $deleteProduct);
$router->post('/products/delete', $deleteProduct);

$activateProduct = function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    ProductsController::setActiveState((int)($_POST['id'] ?? 0), true);
    return null;
};
$router->post('/apps/manufacturing/products/activate', $activateProduct);
$router->post('/products/activate', $activateProduct);

$deactivateProduct = function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    ProductsController::setActiveState((int)($_POST['id'] ?? 0), false);
    return null;
};
$router->post('/apps/manufacturing/products/deactivate', $deactivateProduct);
$router->post('/products/deactivate', $deactivateProduct);

$assignProductItemRef = function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    ProductsController::assignItemRef($_POST);
    return null;
};
$router->post('/apps/manufacturing/products/item-ref', $assignProductItemRef);
$router->post('/products/item-ref', $assignProductItemRef);

$renderProductImport = function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    ProductsController::importForm($view);
    return null;
};
$router->get('/apps/manufacturing/products/import', $renderProductImport);
$router->get('/products/import', function() {
    header('Location: /apps/manufacturing/products/import', true, 302);
    exit;
});

$importProducts = function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    ProductsController::importCsv($_FILES['csv'] ?? null);
    header('Location: /apps/manufacturing/products/import');
    exit;
};
$router->post('/apps/manufacturing/products/import', $importProducts);
$router->post('/products/import', $importProducts);

$router->get('/apps/manufacturing/products/report', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    $report = ModuleReportRegistryService::findActiveReport('manufacturing.products.overview');
    if (!is_array($report)) {
        http_response_code(404);
        echo t('common.report_unavailable');
        return null;
    }
    $view->render('Products::report.php', ['report' => $report]);
    return null;
});

$router->get('/apps/manufacturing/products/export', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    $report = ModuleReportRegistryService::findActiveReport('manufacturing.products.overview');
    if (!is_array($report)) {
        http_response_code(404);
        echo t('common.export_unavailable');
        return null;
    }
    $view->render('Products::export.php', ['report' => $report]);
    return null;
});


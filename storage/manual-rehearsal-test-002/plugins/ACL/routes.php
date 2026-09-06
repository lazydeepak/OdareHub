<?php
declare(strict_types=1);

use App\Core\Auth;
use Plugins\ACL\Services\AclManagementService;

require_once __DIR__ . '/Services/AclManagementService.php';

$router->get('/admin/acl', function() use ($view) {
    AclManagementService::requireManagementAccess('/admin/acl');

    $overview = AclManagementService::buildOverview();
    $view->render('ACL::overview.php', [
        'pageTitle' => 'ACL / RBAC Overview',
        'overview' => $overview,
        'roles' => AclManagementService::roleKeys(),
    ]);
    return null;
});

$router->get('/admin/acl/matrix', function() use ($view) {
    AclManagementService::requireManagementAccess('/admin/acl/matrix');

    $group = trim((string)($_GET['group'] ?? ''));
    $search = trim((string)($_GET['q'] ?? ''));

    $matrix = AclManagementService::matrixData($group, $search);
    $view->render('ACL::matrix.php', [
        'pageTitle' => 'ACL Role Matrix',
        'matrix' => $matrix,
        'ok' => (string)($_GET['ok'] ?? ''),
        'err' => (string)($_GET['err'] ?? ''),
    ]);
    return null;
});

$router->get('/admin/acl/roles', function() use ($view) {
    AclManagementService::requireManagementAccess('/admin/acl/roles');

    $role = trim((string)($_GET['role'] ?? 'admin'));
    $detail = AclManagementService::roleDetail($role);

    $view->render('ACL::role_detail.php', [
        'pageTitle' => 'ACL Role Detail',
        'detail' => $detail,
        'roles' => AclManagementService::roleKeys(),
        'ok' => (string)($_GET['ok'] ?? ''),
        'err' => (string)($_GET['err'] ?? ''),
    ]);
    return null;
});

$router->get('/admin/acl/permissions', function() use ($view) {
    AclManagementService::requireManagementAccess('/admin/acl/permissions');

    $catalog = AclManagementService::permissionCatalog();
    $groups = AclManagementService::permissionGroups();

    $view->render('ACL::permissions.php', [
        'pageTitle' => 'ACL Permission Catalog',
        'catalog' => $catalog,
        'groups' => $groups,
    ]);
    return null;
});

$router->post('/admin/acl/roles/update', function() {
    AclManagementService::requireManagementAccess('/admin/acl/roles');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

    $action = trim((string)($_POST['action'] ?? ''));
    $role = trim((string)($_POST['role'] ?? ''));
    $permission = trim((string)($_POST['permission'] ?? ''));
    $reason = trim((string)($_POST['reason'] ?? ''));

    $result = AclManagementService::applyPermissionChange($action, $role, $permission, Auth::user(), $reason);

    $redirect = '/admin/acl/roles?role=' . urlencode($role);
    if (!empty($result['ok'])) {
        $redirect .= '&ok=' . urlencode((string)($result['message'] ?? 'ACL updated.'));
    } else {
        $redirect .= '&err=' . urlencode((string)($result['message'] ?? 'ACL update failed.'));
    }

    header('Location: ' . $redirect);
    exit;
});

<?php
declare(strict_types=1);

namespace Plugins\ACL\Controllers;

use App\Core\Auth;
use App\Core\AccessGuard;
use App\Core\View;
use Plugins\ACL\Services\AclManagementService;

/**
 * ACL Controller
 *
 * Handles ACL and RBAC management routes.
 * Provides access control matrix, role detail, permission catalog interfaces.
 */
final class AclController
{
    /**
     * Display ACL/RBAC overview.
     *
     * @param View $view
     * @return void
     */
    public static function overview(View $view): void
    {
        AccessGuard::require('acl_manager');

        $overview = AclManagementService::buildOverview();
        Auth::bootSession();
        $view->render('ACL::overview.php', [
            'pageTitle' => 'ACL / RBAC Overview',
            'overview' => $overview,
            'roles' => AclManagementService::roleKeys(),
            'csrf' => Auth::csrfToken(),
        ]);
    }

    /**
     * Display ACL role matrix.
     *
     * @param View $view
     * @return void
     */
    public static function matrix(View $view): void
    {
        AccessGuard::require('acl_manager');

        $group = trim((string)($_GET['group'] ?? ''));
        $search = trim((string)($_GET['q'] ?? ''));

        $matrix = AclManagementService::matrixData($group, $search);
        Auth::bootSession();
        $view->render('ACL::matrix.php', [
            'pageTitle' => 'ACL Role Matrix',
            'matrix' => $matrix,
            'ok' => (string)($_GET['ok'] ?? ''),
            'err' => (string)($_GET['err'] ?? ''),
            'csrf' => Auth::csrfToken(),
        ]);
    }

    /**
     * Display role detail and permissions.
     *
     * @param View $view
     * @return void
     */
    public static function roleDetail(View $view): void
    {
        AccessGuard::require('acl_manager');

        $role = trim((string)($_GET['role'] ?? 'admin'));
        $detail = AclManagementService::roleDetail($role);

        Auth::bootSession();
        $view->render('ACL::role_detail.php', [
            'pageTitle' => 'ACL Role Detail',
            'detail' => $detail,
            'roles' => AclManagementService::roleKeys(),
            'ok' => (string)($_GET['ok'] ?? ''),
            'err' => (string)($_GET['err'] ?? ''),
            'csrf' => Auth::csrfToken(),
        ]);
    }

    /**
     * Display permission catalog.
     *
     * @param View $view
     * @return void
     */
    public static function permissions(View $view): void
    {
        AccessGuard::require('acl_manager');

        $catalog = AclManagementService::permissionCatalog();
        $groups = AclManagementService::permissionGroups();

        Auth::bootSession();
        $view->render('ACL::permissions.php', [
            'pageTitle' => 'ACL Permission Catalog',
            'catalog' => $catalog,
            'groups' => $groups,
            'csrf' => Auth::csrfToken(),
        ]);
    }

    /**
     * Update role permissions.
     *
     * @return void
     */
    public static function updateRolePermissions(): void
    {
        AccessGuard::require('acl_manager');
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

        header('Location: ' . $redirect, true, 302);
        exit;
    }

    /**
     * Add permission to role.
     *
     * @return void
     */
    public static function addPermission(): void
    {
        AccessGuard::require('acl_manager');
        Auth::bootSession();
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $role = trim((string)($_POST['role'] ?? ''));
        $permission = trim((string)($_POST['permission'] ?? ''));
        $reason = trim((string)($_POST['reason'] ?? ''));

        $result = AclManagementService::applyPermissionChange('add', $role, $permission, Auth::user(), $reason);

        header('Location: /admin/acl/roles?role=' . urlencode($role) . 
            (!empty($result['ok']) ? '&ok=' . urlencode((string)($result['message'] ?? '')) : '&err=' . urlencode((string)($result['message'] ?? ''))), 
            true, 302);
        exit;
    }

    /**
     * Remove permission from role.
     *
     * @return void
     */
    public static function removePermission(): void
    {
        AccessGuard::require('acl_manager');
        Auth::bootSession();
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $role = trim((string)($_POST['role'] ?? ''));
        $permission = trim((string)($_POST['permission'] ?? ''));
        $reason = trim((string)($_POST['reason'] ?? ''));

        $result = AclManagementService::applyPermissionChange('remove', $role, $permission, Auth::user(), $reason);

        header('Location: /admin/acl/roles?role=' . urlencode($role) . 
            (!empty($result['ok']) ? '&ok=' . urlencode((string)($result['message'] ?? '')) : '&err=' . urlencode((string)($result['message'] ?? ''))), 
            true, 302);
        exit;
    }
}

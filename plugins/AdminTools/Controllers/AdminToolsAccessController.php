<?php
declare(strict_types=1);

namespace Plugins\AdminTools\Controllers;

use App\Core\Auth;
use App\Core\AccessGuard;
use App\Core\View;
use App\Core\DB;

/**
 * Admin Tools Access Controller
 *
 * Handles user access control configuration for admin tools interface.
 */
final class AdminToolsAccessController
{
    /**
     * Display access control configuration page.
     *
     * @param View $view
     * @return void
     */
    public static function detail(View $view): void
    {
        AccessGuard::require('admin_tools');
        Auth::bootSession();

        $userId = (int)($_GET['user_id'] ?? 0);
        $full = (bool)($_GET['full'] ?? false);

        if ($userId <= 0) {
            http_response_code(400);
            echo 'User ID required.';
            exit;
        }

        $user = DB::fetchOne('SELECT * FROM users WHERE id=? LIMIT 1', [$userId]);
        if (!is_array($user)) {
            http_response_code(404);
            echo 'User not found.';
            exit;
        }

        $assignment = DB::fetchOne(
            'SELECT * FROM user_dashboard_assignments WHERE user_id=? LIMIT 1',
            [$userId]
        );

        if (!is_array($assignment)) {
            $assignment = [
                'user_id' => $userId,
                'account_type' => (string)($user['authority_role'] ?? $user['role'] ?? 'operator'),
                'dashboard_type' => 'auto',
                'default_app' => 'platform',
                'default_landing_page' => '/',
                'dashboard_mode' => 'auto',
                'default_app_mode' => 'auto',
                'landing_mode' => 'auto',
                'assigned_apps' => '',
                'account_class' => '',
                'operational_profile' => '',
            ];
        }

        Auth::bootSession();
        $view->render('AdminTools::access_control/detail.php', [
            'pageTitle' => 'User Access Control',
            'user' => $user,
            'assignment' => $assignment,
            'full' => $full,
            'csrf' => Auth::csrfToken(),
        ]);
    }

    /**
     * Save access control configuration.
     *
     * @return void
     */
    public static function save(): void
    {
        AccessGuard::require('admin_tools');
        Auth::bootSession();
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $userId = (int)($_POST['user_id'] ?? 0);
        $redirectTo = (string)($_POST['redirect_to'] ?? '/admin/system-tools/access-control');

        if ($userId <= 0) {
            $_SESSION['access_control_error'] = 'User ID required.';
            header('Location: ' . $redirectTo, true, 302);
            exit;
        }

        $user = DB::fetchOne('SELECT id FROM users WHERE id=? LIMIT 1', [$userId]);
        if (!is_array($user)) {
            $_SESSION['access_control_error'] = 'User not found.';
            header('Location: ' . $redirectTo, true, 302);
            exit;
        }

        try {
            $accountType = (string)($_POST['account_type'] ?? 'operator');
            $dashboardType = (string)($_POST['dashboard_type'] ?? 'auto');
            $defaultApp = (string)($_POST['default_app'] ?? 'platform');
            $defaultLandingPage = (string)($_POST['default_landing_page'] ?? '/');
            $dashboardMode = (string)($_POST['dashboard_mode'] ?? 'auto');
            $defaultAppMode = (string)($_POST['default_app_mode'] ?? 'auto');
            $landingMode = (string)($_POST['landing_mode'] ?? 'auto');
            $assignedApps = (string)($_POST['assigned_apps'] ?? '');
            $accountClass = (string)($_POST['account_class'] ?? '');
            $operationalProfile = (string)($_POST['operational_profile'] ?? '');

            DB::query(
                'INSERT INTO user_dashboard_assignments 
                (user_id, account_type, dashboard_type, default_app, default_landing_page, 
                 dashboard_mode, default_app_mode, landing_mode, assigned_apps, account_class, operational_profile)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                account_type=VALUES(account_type), dashboard_type=VALUES(dashboard_type), 
                default_app=VALUES(default_app), default_landing_page=VALUES(default_landing_page),
                dashboard_mode=VALUES(dashboard_mode), default_app_mode=VALUES(default_app_mode),
                landing_mode=VALUES(landing_mode), assigned_apps=VALUES(assigned_apps),
                account_class=VALUES(account_class), operational_profile=VALUES(operational_profile)',
                [
                    $userId, $accountType, $dashboardType, $defaultApp, $defaultLandingPage,
                    $dashboardMode, $defaultAppMode, $landingMode, $assignedApps, $accountClass, $operationalProfile
                ]
            );

            $_SESSION['access_control_ok'] = 'User access control updated successfully.';
            header('Location: ' . $redirectTo, true, 302);
            exit;
        } catch (\Throwable $e) {
            $_SESSION['access_control_error'] = 'Failed to save access control: ' . $e->getMessage();
            header('Location: ' . $redirectTo, true, 302);
            exit;
        }
    }
}

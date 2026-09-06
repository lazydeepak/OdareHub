<?php
declare(strict_types=1);

namespace Plugins\Base\Controllers;

use App\Core\Auth;
use App\Core\AccessGuard;
use App\Core\View;
use Apps\Platform\Services\DashboardService;

/**
 * Dashboard Controller
 *
 * Handles dashboard and operational display routes.
 * Delegates business logic to DashboardService.
 */
final class DashboardController
{
    /**
     * Render the main landing dashboard for the current user.
     *
     * @return void
     */
    public static function landing(View $view): void
    {
        AccessGuard::require('logged_in');

        $user = AccessGuard::user();
        if (!is_array($user)) {
            header('Location: /login', true, 302);
            exit;
        }

        $landing = \Apps\Shell\Services\LandingPageService::getLandingOrLogin();
        header('Location: ' . $landing, true, 302);
        exit;
    }

    /**
     * Render operational dashboard.
     *
     * @param View $view
     * @return void
     */
    public static function operational(View $view): void
    {
        AccessGuard::require('admin_tools');

        $user = AccessGuard::user();
        $dashboardData = DashboardService::getDashboardData($user);

        Auth::bootSession();
        $view->render('Base::admin/operational_dashboard.php', [
            'pageTitle' => (string)t('dashboard.operational.title'),
            'dashboardType' => $dashboardData['dashboard_type'],
            'dashboardData' => $dashboardData,
            'user' => $user,
            'csrf' => Auth::csrfToken(),
        ]);
    }

    /**
     * Render next best action dashboard.
     *
     * @param View $view
     * @return void
     */
    public static function nextAction(View $view): void
    {
        AccessGuard::require('admin_tools');

        $user = AccessGuard::user();
        $dashboardData = DashboardService::getDashboardData($user);
        $nextAction = is_array($dashboardData['next_action'] ?? null)
            ? (array)$dashboardData['next_action']
            : DashboardService::getNextBestAction($user);

        Auth::bootSession();
        $view->render('Base::admin/next_action_dashboard.php', [
            'pageTitle' => (string)t('dashboard.next_action.title'),
            'dashboardType' => $dashboardData['dashboard_type'],
            'nextAction' => $nextAction,
            'dashboardData' => $dashboardData,
            'user' => $user,
            'csrf' => Auth::csrfToken(),
        ]);
    }
}

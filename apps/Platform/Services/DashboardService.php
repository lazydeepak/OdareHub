<?php
declare(strict_types=1);

namespace Apps\Platform\Services;

require_once __DIR__ . '/DashboardAggregatorService.php';
require_once __DIR__ . '/DashboardKpiService.php';
require_once __DIR__ . '/DashboardPriorityService.php';
require_once __DIR__ . '/DashboardActionService.php';

/**
 * Dashboard Service Orchestrator
 *
 * Coordinates specialized dashboard services:
 * - DashboardAggregatorService (task loading + normalization)
 * - DashboardKpiService (KPI + SLA summaries)
 * - DashboardPriorityService (task sorting + priority computation)
 * - DashboardActionService (next best action + blocking detection)
 */
final class DashboardService
{
    /** @param array<string,mixed> $user @return array<string,mixed> */
    public static function getDashboardData(array $user): array
    {
        $dashboardType = DashboardAggregatorService::mapUserRoleToDashboardType((string)($user['account_type'] ?? 'platform_admin'));
        $userId = (string)($user['id'] ?? '');
        $userEmail = (string)($user['email'] ?? '');
        $nowTs = time();

        $tasks = DashboardAggregatorService::getTasksForUser($user);
        $kpis = DashboardKpiService::compute($tasks);
        $slaSummary = DashboardKpiService::computeSlaSummary($tasks);
        $priorityTasks = DashboardPriorityService::sortTasks($tasks, $nowTs);
        $enrichedTasks = DashboardPriorityService::enrichTasksForDashboard($priorityTasks, $dashboardType, $nowTs);
        $nextAction = DashboardActionService::getNext($enrichedTasks, $dashboardType);

        return [
            'dashboard_type' => $dashboardType,
            'user_id' => $userId,
            'user_email' => $userEmail,
            'timestamp' => gmdate('c'),
            'kpis' => $kpis,
            'sla_summary' => $slaSummary,
            'task_count' => count($enrichedTasks),
            'tasks' => $enrichedTasks,
            'next_action' => $nextAction,
            'has_overdue' => $kpis['overdue_count'] > 0,
            'has_delayed' => $kpis['near_sla_count'] > 0,
        ];
    }

    /** @param array<string,mixed> $user @return array<string,int> */
    public static function getKpis(array $user): array
    {
        return DashboardKpiService::compute(DashboardAggregatorService::getTasksForUser($user));
    }

    /** @param array<string,mixed> $user @return array<int,array<string,mixed>> */
    public static function getTasks(array $user): array
    {
        return DashboardAggregatorService::getTasksForUser($user);
    }

    /** @param array<string,mixed> $user @return array<int,array<string,mixed>> */
    public static function getPriorityTasks(array $user): array
    {
        $dashboardType = DashboardAggregatorService::mapUserRoleToDashboardType((string)($user['account_type'] ?? 'platform_admin'));
        $tasks = DashboardAggregatorService::getTasksForUser($user);
        $sorted = DashboardPriorityService::sortTasks($tasks, time());
        return DashboardPriorityService::enrichTasksForDashboard($sorted, $dashboardType, time());
    }

    /** @param array<string,mixed> $user @return array<string,mixed> */
    public static function getNextBestAction(array $user): array
    {
        $dashboardType = DashboardAggregatorService::mapUserRoleToDashboardType((string)($user['account_type'] ?? 'platform_admin'));
        $tasks = self::getPriorityTasks($user);
        return DashboardActionService::getNext($tasks, $dashboardType);
    }

    public static function mapUserRoleToDashboardType(string $accountType): string
    {
        return DashboardAggregatorService::mapUserRoleToDashboardType($accountType);
    }
}

<?php
declare(strict_types=1);

namespace Apps\Platform\Services;

final class DashboardPriorityService
{
    /** @param array<int,array<string,mixed>> $tasks @return array<int,array<string,mixed>> */
    public static function sortTasks(array $tasks, ?int $nowTs = null): array
    {
        $nowTs = $nowTs ?? time();

        usort($tasks, static function (array $a, array $b) use ($nowTs): int {
            $aScore = self::computePriorityScore($a);
            $bScore = self::computePriorityScore($b);
            if ($aScore !== $bScore) {
                return $bScore - $aScore;
            }

            $aTimeInState = (int)($a['time_in_state_seconds'] ?? 0);
            $bTimeInState = (int)($b['time_in_state_seconds'] ?? 0);
            if ($aTimeInState !== $bTimeInState) {
                return $bTimeInState - $aTimeInState;
            }

            $aCreated = strtotime((string)($a['created_at'] ?? '')) ?: $nowTs;
            $bCreated = strtotime((string)($b['created_at'] ?? '')) ?: $nowTs;
            return $aCreated - $bCreated;
        });

        return $tasks;
    }

    /** @param array<int,array<string,mixed>> $tasks @return array<int,array<string,mixed>> */
    public static function enrichTasksForDashboard(array $tasks, string $dashboardType, ?int $nowTs = null): array
    {
        $nowTs = $nowTs ?? time();
        return array_values(array_map(static function (array $task) use ($dashboardType, $nowTs): array {
            return self::enrichTask($task, $dashboardType, $nowTs);
        }, $tasks));
    }

    /** @param array<string,mixed> $task */
    public static function computePriorityScore(array $task): int
    {
        if (!empty($task['is_overdue'])) {
            return 300;
        }
        if (!empty($task['is_delayed'])) {
            return 200;
        }
        return 100;
    }

    /** @param array<string,mixed> $task @return array<string,mixed> */
    private static function enrichTask(array $task, string $dashboardType, int $nowTs): array
    {
        $status = (string)($task['status'] ?? 'draft');
        $task['dashboard_type'] = $dashboardType;
        $task['priority_score'] = self::computePriorityScore($task);
        $task['is_high_priority'] = !empty($task['is_overdue']);
        $task['is_attention_needed'] = !empty($task['is_delayed']);

        $task['visible_actions'] = [];
        if ($dashboardType === 'operator' && $status === 'in_progress') {
            $task['visible_actions'] = ['mark_completed'];
        } elseif ($dashboardType === 'qc' && $status === 'completed') {
            $task['visible_actions'] = ['approve', 'show_history'];
        } elseif ($dashboardType === 'dispatch' && $status === 'approved') {
            $task['visible_actions'] = ['dispatch', 'show_history'];
        } elseif ($dashboardType === 'admin') {
            $task['visible_actions'] = ['mark_in_progress', 'mark_completed', 'approve', 'dispatch', 'show_history'];
        }

        $task['delay_indicator'] = 'on_track';
        if (!empty($task['is_overdue'])) {
            $task['delay_indicator'] = 'overdue';
        } elseif (!empty($task['is_delayed'])) {
            $task['delay_indicator'] = 'near_breach';
        }

        return $task;
    }
}

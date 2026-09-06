<?php
declare(strict_types=1);

namespace Apps\Platform\Services;

final class DashboardKpiService
{
    /** @param array<int,array<string,mixed>> $tasks @return array<string,int> */
    public static function compute(array $tasks): array
    {
        $inProgress = 0;
        $overdue = 0;
        $nearSla = 0;

        foreach ($tasks as $task) {
            if (($task['status'] ?? '') === 'in_progress') {
                $inProgress++;
            }
            if (!empty($task['is_overdue'])) {
                $overdue++;
            }
            if (!empty($task['is_delayed']) && empty($task['is_overdue'])) {
                $nearSla++;
            }
        }

        $total = count($tasks);
        return [
            'total_count' => $total,
            'in_progress_count' => $inProgress,
            'overdue_count' => $overdue,
            'near_sla_count' => $nearSla,
            'on_track_count' => $total - $overdue - $nearSla,
        ];
    }

    /** @param array<int,array<string,mixed>> $tasks @return array<string,mixed> */
    public static function computeSlaSummary(array $tasks): array
    {
        $total = max(1, count($tasks));
        $overdue = 0;
        $nearBreach = 0;

        foreach ($tasks as $task) {
            if (!empty($task['is_overdue'])) {
                $overdue++;
                continue;
            }
            if (!empty($task['is_delayed'])) {
                $nearBreach++;
            }
        }

        return [
            'overdue_count' => $overdue,
            'near_breach_count' => $nearBreach,
            'on_track_count' => max(0, count($tasks) - $overdue - $nearBreach),
            'overdue_ratio' => round($overdue / $total, 4),
            'near_breach_ratio' => round($nearBreach / $total, 4),
        ];
    }
}

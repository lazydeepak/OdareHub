<?php
declare(strict_types=1);

namespace Apps\Platform\Services;

final class DashboardActionService
{
    /** @param array<int,array<string,mixed>> $tasks @return array<string,mixed> */
    public static function getNext(array $tasks, string $dashboardType): array
    {
        if ($tasks === []) {
            return [
                'has_next_action' => false,
                'next_action' => null,
                'reason' => 'no_tasks',
                'explanation' => 'No tasks requiring your attention.',
                'blocked_downstream_count' => 0,
            ];
        }

        foreach ($tasks as $task) {
            if (!empty($task['is_overdue'])) {
                return self::buildAction($task, 'overdue', 'This task is overdue and requires immediate attention.', $tasks, $dashboardType);
            }
        }

        foreach ($tasks as $task) {
            $blocked = self::countBlockedDownstreamTasks($task, $tasks, $dashboardType);
            if ($blocked > 0) {
                return self::buildAction(
                    $task,
                    'blocking_others',
                    'This task is blocking ' . $blocked . ' downstream task' . ($blocked !== 1 ? 's' : '') . '.',
                    $tasks,
                    $dashboardType
                );
            }
        }

        foreach ($tasks as $task) {
            if (!empty($task['is_delayed'])) {
                return self::buildAction($task, 'near_sla_breach', 'This task is approaching SLA breach and should be prioritized.', $tasks, $dashboardType);
            }
        }

        return self::buildAction($tasks[0], 'normal_priority', 'This is the next task in your queue.', $tasks, $dashboardType);
    }

    /** @param array<string,mixed> $task @param array<int,array<string,mixed>> $allTasks @return array<string,mixed> */
    public static function detectBlocking(array $task, array $allTasks, string $dashboardType): array
    {
        $blockedCount = self::countBlockedDownstreamTasks($task, $allTasks, $dashboardType);
        return [
            'blocked_downstream_count' => $blockedCount,
            'is_blocking' => $blockedCount > 0,
            'blocking_explanation' => $blockedCount > 0
                ? 'Completing this task will unblock ' . $blockedCount . ' downstream task' . ($blockedCount !== 1 ? 's' : '') . '.'
                : null,
        ];
    }

    /** @param array<string,mixed> $task @param array<int,array<string,mixed>> $allTasks */
    private static function countBlockedDownstreamTasks(array $task, array $allTasks, string $dashboardType): int
    {
        $taskStatus = (string)($task['status'] ?? 'draft');
        $blockedCount = 0;

        foreach ($allTasks as $downstreamTask) {
            $downstreamStatus = (string)($downstreamTask['status'] ?? 'draft');

            foreach (self::taskBlockingRelationships() as $rule) {
                if ($taskStatus === $rule['blocker_status'] && $downstreamStatus === $rule['blocked_status']) {
                    if ((string)($task['id'] ?? '') !== (string)($downstreamTask['id'] ?? '')) {
                        $blockedCount++;
                    }
                }
            }
        }

        return $blockedCount;
    }

    /** @return array<int,array<string,string>> */
    private static function taskBlockingRelationships(): array
    {
        return [
            ['blocker_status' => 'in_progress', 'blocked_status' => 'completed', 'unblocks_to' => 'completed'],
            ['blocker_status' => 'completed', 'blocked_status' => 'approved', 'unblocks_to' => 'approved'],
            ['blocker_status' => 'in_progress', 'blocked_status' => 'draft', 'unblocks_to' => 'in_progress'],
        ];
    }

    /** @param array<string,mixed> $task @param array<int,array<string,mixed>> $allTasks @return array<string,mixed> */
    private static function buildAction(array $task, string $reason, string $explanation, array $allTasks, string $dashboardType): array
    {
        $blocking = self::detectBlocking($task, $allTasks, $dashboardType);
        return [
            'has_next_action' => true,
            'next_action' => $task,
            'reason' => $reason,
            'explanation' => $explanation,
            'blocked_downstream_count' => (int)$blocking['blocked_downstream_count'],
            'blocking_explanation' => $blocking['blocking_explanation'],
        ];
    }
}

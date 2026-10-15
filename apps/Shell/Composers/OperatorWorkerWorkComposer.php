<?php
declare(strict_types=1);

namespace Apps\Shell\Composers;

/**
 * Converts the existing assigned-task read projection into worker-home lanes.
 *
 * This class owns no task meaning or authorization. The provider owns the
 * records and statuses; this composer only preserves them and separates active
 * records by explicit due-date evidence for presentation.
 */
final class OperatorWorkerWorkComposer
{
    /**
     * @param array<string,mixed> $taskResult
     * @param array<string,mixed> $context
     * @return array{available:bool,error:string,current:array<int,array<string,mixed>>,upcoming:array<int,array<string,mixed>>,attention:array<int,array<string,mixed>>,summary:array<string,int>}
     */
    public static function compose(array $taskResult, array $context, string $username): array
    {
        $error = trim((string)($taskResult['error'] ?? ''));
        $empty = [
            'available' => $error === '',
            'error' => $error,
            'current' => [],
            'upcoming' => [],
            'attention' => [],
            'summary' => ['current' => 0, 'upcoming' => 0, 'attention' => 0],
        ];
        if (($context['operator_identity_bound'] ?? true) !== true) {
            $empty['available'] = false;
            $empty['error'] = 'operator.worker_work.identity_unavailable';
            return $empty;
        }
        if ($error !== '') {
            return $empty;
        }

        $authenticatedUserId = (int)($context['authenticated_user_id'] ?? $context['user_id'] ?? 0);
        $scope = is_array($context['scope'] ?? null) ? $context['scope'] : [];
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $current = [];
        $upcoming = [];
        $attention = [];

        foreach ((array)($taskResult['tasks'] ?? []) as $task) {
            if (!is_array($task)) {
                continue;
            }
            $taskId = (int)($task['id'] ?? 0);
            $title = trim((string)($task['title'] ?? ''));
            $status = strtolower(trim((string)($task['status'] ?? '')));
            $assignedTo = (int)($task['assigned_to'] ?? 0);
            if ($taskId <= 0 || $title === '' || $assignedTo <= 0) {
                continue;
            }
            if ($authenticatedUserId > 0 && $assignedTo !== $authenticatedUserId) {
                continue;
            }
            $isBlocked = in_array($status, ['blocked', 'on_hold', 'hold'], true);
            if (!$isBlocked && !in_array($status, ['open', 'in_progress'], true)) {
                continue;
            }

            $dueDate = trim((string)($task['due_date'] ?? ''));
            $sourceDestination = trim((string)($task['destination'] ?? ''));
            $operatorDestination = '/u/' . rawurlencode($username) . '/tasks';
            if ($sourceDestination !== '') {
                $candidateDestination = str_replace('{user}', rawurlencode($username), $sourceDestination);
                $candidatePath = trim((string)parse_url($candidateDestination, PHP_URL_PATH));
                $expectedPrefix = '/u/' . rawurlencode($username) . '/';
                if (str_starts_with($candidatePath, $expectedPrefix)) {
                    $operatorDestination = $candidateDestination;
                }
            }
            $isOverdue = !$isBlocked && (!empty($task['is_overdue']) || ($dueDate !== '' && $dueDate < $today));
            $item = [
                'source_owner' => 'operator_tasks',
                'item_id' => $taskId,
                'item_ref' => 'operator-task:' . $taskId,
                'title' => $title,
                'description' => (string)($task['description'] ?? ''),
                'status' => (string)($task['status'] ?? ''),
                'priority' => (string)($task['priority'] ?? ''),
                'due_date' => $dueDate,
                'due_shift' => (string)($task['due_shift'] ?? ''),
                'assigned_to' => $assignedTo,
                'assigned_by' => (int)($task['assigned_by'] ?? 0),
                'operational_scope' => $scope,
                'item_scope' => is_array($task['scope'] ?? null) ? $task['scope'] : [],
                'source_destination' => $sourceDestination,
                'destination' => $operatorDestination,
                'attention_reason' => $isBlocked ? 'blocked' : ($isOverdue ? 'overdue' : ''),
            ];

            if ($isBlocked || $isOverdue) {
                $attention[] = $item;
            } elseif ($dueDate !== '' && $dueDate > $today) {
                $upcoming[] = $item;
            } else {
                $current[] = $item;
            }
        }

        return [
            'available' => true,
            'error' => '',
            'current' => $current,
            'upcoming' => $upcoming,
            'attention' => $attention,
            'summary' => [
                'current' => count($current),
                'upcoming' => count($upcoming),
                'attention' => count($attention),
            ],
        ];
    }
}

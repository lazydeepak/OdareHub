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
        $authenticatedUserId = (int)($context['authenticated_user_id'] ?? $context['user_id'] ?? 0);
        $empty = [
            'available' => $error === '',
            'error' => $error,
            'current' => [],
            'upcoming' => [],
            'attention' => [],
            'summary' => ['current' => 0, 'upcoming' => 0, 'attention' => 0],
        ];
        if ($authenticatedUserId <= 0 || ($context['operator_identity_bound'] ?? false) !== true) {
            $empty['available'] = false;
            $empty['error'] = 'operator.worker_work.identity_unavailable';
            return $empty;
        }
        if ($error !== '') {
            return $empty;
        }

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
            if (!in_array($status, ['open', 'in_progress'], true)) {
                continue;
            }

            $dueDate = trim((string)($task['due_date'] ?? ''));
            $sourceDestination = trim((string)($task['destination'] ?? ''));
            $operatorDestination = self::resolveDestination($sourceDestination, $username, $context, $taskId);
            $isOverdue = !empty($task['is_overdue']) || ($dueDate !== '' && $dueDate < $today);
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
                'effective_operational_scope' => $scope,
                'item_scope' => is_array($task['scope'] ?? null) ? $task['scope'] : null,
                'source_destination' => $sourceDestination,
                'destination' => $operatorDestination,
                'attention_reason' => $isOverdue ? 'overdue' : '',
            ];

            if ($isOverdue) {
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

    /**
     * Accept only an owner destination that is a same-user relative operator
     * route and has passed the existing route-access authority. The fallback
     * is also authority checked; an unavailable authority produces no link.
     *
     * @param array<string,mixed> $context
     */
    private static function resolveDestination(string $source, string $username, array $context, int $taskId): string
    {
        $fallback = '/u/' . rawurlencode($username) . '/tasks?task_id=' . $taskId;
        $resolver = $context['operator_route_access_resolver'] ?? null;
        if (!is_callable($resolver)) {
            return '';
        }

        $candidate = $source !== '' ? str_replace('{user}', rawurlencode($username), $source) : $fallback;
        if (!self::isSafeOperatorUrl($candidate, $username)) {
            $candidate = $fallback;
        }

        $boundCandidate = self::bindTaskSelector($candidate, $username, $taskId);
        if ($boundCandidate === null) {
            $candidate = $fallback;
        } else {
            $candidate = $boundCandidate;
        }

        $path = (string)parse_url($candidate, PHP_URL_PATH);
        try {
        return $resolver($path) === true ? $candidate : '';
        } catch (\Throwable) {
            return '';
        }
    }

    private static function bindTaskSelector(string $url, string $username, int $taskId): ?string
    {
        $parts = parse_url($url);
        $taskPath = '/u/' . rawurlencode($username) . '/tasks';
        if (!is_array($parts) || (string)($parts['path'] ?? '') !== $taskPath) {
            return $url;
        }

        $queryValues = [];
        parse_str((string)($parts['query'] ?? ''), $queryValues);
        foreach ($queryValues as $key => $value) {
            if (in_array((string)$key, ['task_id', 'id'], true) && is_array($value)) {
                return null;
            }
        }
        unset($queryValues['task_id'], $queryValues['id']);
        $query = http_build_query($queryValues, '', '&', PHP_QUERY_RFC3986);
        $query .= ($query === '' ? '' : '&') . 'task_id=' . $taskId;
        return $taskPath . '?' . $query;
    }

    private static function isSafeOperatorUrl(string $url, string $username): bool
    {
        if ($url === '' || str_contains($url, '\\') || preg_match('/[\x00-\x20]/', $url)) {
            return false;
        }
        if (preg_match('/%(?![0-9a-fA-F]{2})/', $url)) {
            return false;
        }
        $parts = parse_url($url);
        foreach (['scheme', 'host', 'user', 'pass', 'port', 'fragment'] as $forbiddenPart) {
            if (is_array($parts) && array_key_exists($forbiddenPart, $parts)) {
                return false;
            }
        }
        if (!is_array($parts)) {
            return false;
        }
        $path = trim((string)($parts['path'] ?? ''));
        $expectedPrefix = '/u/' . rawurlencode($username) . '/';
        if ($path === '' || !str_starts_with($path, $expectedPrefix) || str_contains($path, '//')) {
            return false;
        }
        if (preg_match('/%(?:2e|2f|5c)/i', $path)) {
            return false;
        }
        foreach (explode('/', trim($path, '/')) as $segment) {
            if ($segment === '.' || $segment === '..') {
                return false;
            }
        }
        $query = (string)($parts['query'] ?? '');
        if ($query === '') {
            return true;
        }
        parse_str($query, $queryValues);
        foreach ($queryValues as $key => $value) {
            $valueString = (string)$value;
            if (!in_array((string)$key, ['focus', 'tab', 'task_id', 'id'], true) || is_array($value) || str_starts_with($valueString, '//') || str_contains($valueString, '://') || preg_match('/[\x00-\x20]/', $valueString)) {
                return false;
            }
        }
        return true;
    }
}

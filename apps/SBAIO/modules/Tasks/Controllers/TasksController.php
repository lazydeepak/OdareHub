<?php
declare(strict_types=1);

namespace Plugins\Tasks\Controllers;

use App\Core\Auth;
use Plugins\Tasks\Services\TasksService;

require_once __DIR__ . '/../Services/TasksService.php';

final class TasksController
{
    /**
     * Supply the admitted report descriptor and owner-prepared data to the
     * presentation view. Route authorization and report admission remain in
     * the module route.
     *
     * @param array<string,mixed> $report
     */
    public static function report($view, array $report): void
    {
        $view->render('Tasks::report.php', [
            'report' => $report,
            'reportData' => TasksService::reportData(),
        ]);
    }

    public static function index($view): void
    {
        $currentQuery = is_array($_GET ?? null) ? $_GET : [];
        $exactTask = null;
        $exactTaskId = null;
        $exactTaskState = null;
        if (array_key_exists('task_id', $currentQuery)) {
            $rawTaskId = $currentQuery['task_id'];
            if (is_string($rawTaskId) && preg_match('/^[1-9][0-9]*$/D', $rawTaskId)) {
                $parsedTaskId = filter_var($rawTaskId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($parsedTaskId !== false) {
                    $exactTaskId = (int)$parsedTaskId;
                    try {
                        $exactTask = TasksService::findById($exactTaskId);
                        $exactTaskState = is_array($exactTask) ? 'found' : 'not_found';
                    } catch (\Throwable) {
                        $exactTaskState = 'unavailable';
                    }
                } else {
                    $exactTaskState = 'invalid';
                }
            } else {
                $exactTaskState = 'invalid';
            }
        }

        $searchRequested = ($currentQuery['task_search'] ?? '') === '1';
        $searchResult = null;
        if ($searchRequested) {
            try {
                $searchResult = TasksService::search([
                    'queue' => $currentQuery['task_queue'] ?? 'all',
                    'status' => $currentQuery['task_status_exact'] ?? '',
                    'priority' => $currentQuery['task_priority_exact'] ?? '',
                    'task_bucket' => $currentQuery['task_bucket_exact'] ?? '',
                    'from' => $currentQuery['task_from'] ?? '',
                    'to' => $currentQuery['task_to'] ?? '',
                ], 50, is_string($currentQuery['task_cursor'] ?? null)
                    ? $currentQuery['task_cursor']
                    : (($currentQuery['task_cursor'] ?? null) === null ? null : '[invalid]'));
            } catch (\InvalidArgumentException) {
                $searchResult = ['valid' => false, 'available' => true, 'rows' => [], 'truncated' => false, 'limit' => 50, 'filters' => [], 'next_cursor' => null];
            } catch (\Throwable) {
                $searchResult = ['valid' => true, 'available' => false, 'rows' => [], 'truncated' => false, 'limit' => 50, 'filters' => [], 'next_cursor' => null];
            }
            $rows = (array)($searchResult['rows'] ?? []);
        } else {
            $rows = TasksService::recent();
        }

        $nextSearchUrl = null;
        if (is_string($searchResult['next_cursor'] ?? null) && $searchResult['next_cursor'] !== '') {
            $nextParams = ['task_search' => '1', 'task_cursor' => $searchResult['next_cursor']];
            foreach ([
                'queue' => 'task_queue',
                'status' => 'task_status_exact',
                'priority' => 'task_priority_exact',
                'task_bucket' => 'task_bucket_exact',
                'from' => 'task_from',
                'to' => 'task_to',
            ] as $filterKey => $queryKey) {
                if (is_string($searchResult['filters'][$filterKey] ?? null) && $searchResult['filters'][$filterKey] !== '') {
                    $nextParams[$queryKey] = $searchResult['filters'][$filterKey];
                }
            }
            $nextSearchUrl = '/apps/sbaio/tasks?' . http_build_query($nextParams, '', '&', PHP_QUERY_RFC3986);
        }

        $view->render('Tasks::index.php', [
            'pageTitle' => t('sbaio.tasks.title'),
            'moduleTitle' => t('sbaio.tasks.title'),
            'moduleDescription' => t('sbaio.tasks.description'),
            'rows' => $rows,
            'message' => (string)($_GET['ok'] ?? ''),
            'error' => (string)($_GET['err'] ?? ''),
            'exactTask' => $exactTask,
            'exactTaskId' => $exactTaskId,
            'exactTaskState' => $exactTaskState,
            'createdRecordId' => ($_GET['ok'] ?? '') !== '' ? $exactTaskId : null,
            'currentQuery' => $currentQuery,
            'searchResult' => $searchResult,
            'nextSearchUrl' => $nextSearchUrl,
        ]);
    }

    public static function create(): void
    {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $validated = TasksService::validateCreateInput($_POST);
        if (!$validated['valid']) {
            $messages = [
                'task_title_required' => 'sbaio.tasks.required',
                'task_title_invalid' => 'sbaio.tasks.title_invalid',
                'task_title_too_long' => 'sbaio.tasks.title_too_long',
                'owner_name_invalid' => 'sbaio.tasks.owner_invalid',
                'owner_name_too_long' => 'sbaio.tasks.owner_too_long',
                'task_type_invalid' => 'sbaio.tasks.type_invalid',
                'task_type_too_long' => 'sbaio.tasks.type_too_long',
                'task_bucket_invalid' => 'sbaio.tasks.bucket_invalid',
                'task_bucket_too_long' => 'sbaio.tasks.bucket_too_long',
                'related_staff_id_invalid' => 'sbaio.tasks.staff_id_invalid',
                'related_staff_id_unavailable' => 'sbaio.tasks.related_staff_write_unavailable',
                'related_date_invalid' => 'sbaio.tasks.related_date_invalid',
                'task_status_invalid' => 'sbaio.tasks.status_invalid',
                'task_status_too_long' => 'sbaio.tasks.status_too_long',
                'priority_invalid' => 'sbaio.tasks.priority_invalid',
                'priority_too_long' => 'sbaio.tasks.priority_too_long',
                'due_date_invalid' => 'sbaio.tasks.due_date_invalid',
            ];
            $errorKey = (string)($validated['errors'][0] ?? 'invalid');
            self::redirect('err', t($messages[$errorKey] ?? 'sbaio.tasks.invalid'));
        }

        $createdId = TasksService::create($validated['values']);

        self::redirect('ok', t('sbaio.tasks.created_with_id', ['id' => (string)$createdId]), ['task_id' => $createdId]);
    }

    /** @param array<string,int|string> $params */
    private static function redirect(string $key, string $message, array $params = []): void
    {
        $query = [$key => $message];
        foreach ($params as $paramKey => $paramValue) {
            $query[$paramKey] = (string)$paramValue;
        }
        header('Location: /apps/sbaio/tasks?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));
        exit;
    }
}

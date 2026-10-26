<?php
declare(strict_types=1);

namespace Plugins\Tasks\Controllers;

use App\Core\Auth;
use Plugins\Tasks\Services\TasksService;

require_once __DIR__ . '/../Services/TasksService.php';

final class TasksController
{
    public static function index($view): void
    {
        $view->render('Tasks::index.php', [
            'pageTitle' => t('sbaio.tasks.title'),
            'moduleTitle' => t('sbaio.tasks.title'),
            'moduleDescription' => t('sbaio.tasks.description'),
            'rows' => TasksService::recent(),
            'staffRows' => TasksService::activeStaff(),
            'message' => (string)($_GET['ok'] ?? ''),
            'error' => (string)($_GET['err'] ?? ''),
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

        TasksService::create($validated['values']);

        self::redirect('ok', t('sbaio.tasks.created'));
    }

    private static function redirect(string $key, string $message): void
    {
        header('Location: /apps/sbaio/tasks?' . $key . '=' . rawurlencode($message));
        exit;
    }
}

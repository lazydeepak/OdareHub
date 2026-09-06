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

        $taskTitle = trim((string)($_POST['task_title'] ?? ''));
        if ($taskTitle === '') {
            self::redirect('err', t('sbaio.tasks.required'));
        }

        TasksService::create($_POST);

        self::redirect('ok', t('sbaio.tasks.created'));
    }

    private static function redirect(string $key, string $message): void
    {
        header('Location: /apps/sbaio/tasks?' . $key . '=' . rawurlencode($message));
        exit;
    }
}

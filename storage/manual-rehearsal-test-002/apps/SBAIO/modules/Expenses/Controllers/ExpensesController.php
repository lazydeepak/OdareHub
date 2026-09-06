<?php
declare(strict_types=1);

namespace Plugins\Expenses\Controllers;

use App\Core\Auth;
use Plugins\Expenses\Services\ExpensesService;

require_once __DIR__ . '/../Services/ExpensesService.php';

final class ExpensesController
{
    public static function index($view): void
    {
        $view->render('Expenses::index.php', [
            'pageTitle' => t('sbaio.expenses.title'),
            'moduleTitle' => t('sbaio.expenses.title'),
            'moduleDescription' => t('sbaio.expenses.description'),
            'rows' => ExpensesService::recent(),
            'message' => (string)($_GET['ok'] ?? ''),
            'error' => (string)($_GET['err'] ?? ''),
        ]);
    }

    public static function create(): void
    {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $expenseRef = trim((string)($_POST['expense_ref'] ?? ''));
        if ($expenseRef === '') {
            self::redirect('err', t('sbaio.expenses.required'));
        }

        ExpensesService::create($_POST);

        self::redirect('ok', t('sbaio.expenses.created'));
    }

    private static function redirect(string $key, string $message): void
    {
        header('Location: /apps/sbaio/expenses?' . $key . '=' . rawurlencode($message));
        exit;
    }
}

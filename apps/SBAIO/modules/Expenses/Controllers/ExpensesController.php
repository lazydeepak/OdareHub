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

        $validated = ExpensesService::validateCreateInput($_POST);
        if (!$validated['valid']) {
            $messages = [
                'required' => 'sbaio.expenses.required',
                'expense_ref_invalid' => 'sbaio.expenses.reference_invalid',
                'expense_ref_too_long' => 'sbaio.expenses.reference_too_long',
                'category_invalid' => 'sbaio.expenses.category_invalid',
                'category_too_long' => 'sbaio.expenses.category_too_long',
                'amount_required' => 'sbaio.expenses.amount_required',
                'amount_invalid' => 'sbaio.expenses.amount_invalid',
                'status_invalid' => 'sbaio.expenses.status_invalid',
                'status_too_long' => 'sbaio.expenses.status_too_long',
                'expense_date_invalid' => 'sbaio.expenses.expense_date_invalid',
            ];
            $errorKey = (string)($validated['errors'][0] ?? 'invalid');
            self::redirect('err', t($messages[$errorKey] ?? 'sbaio.expenses.invalid'));
        }

        ExpensesService::create($validated['values']);

        self::redirect('ok', t('sbaio.expenses.created'));
    }

    private static function redirect(string $key, string $message): void
    {
        header('Location: /apps/sbaio/expenses?' . $key . '=' . rawurlencode($message));
        exit;
    }
}

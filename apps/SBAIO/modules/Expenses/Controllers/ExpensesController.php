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
        $currentQuery = $_GET;
        $rows = ExpensesService::recent();
        $searchResult = null;
        $exactExpense = null;
        $exactExpenseId = null;
        $exactExpenseState = null;

        if (array_key_exists('expense_id', $_GET)) {
            $rawId = $_GET['expense_id'];
            if (!is_int($rawId) && !is_string($rawId)) {
                $exactExpenseState = 'invalid';
            } else {
                try {
                    $exactExpenseId = is_int($rawId) ? $rawId : (int)$rawId;
                    $exactExpense = ExpensesService::findById($rawId);
                    $exactExpenseState = $exactExpense === null ? 'not_found' : 'found';
                } catch (\InvalidArgumentException) {
                    $exactExpenseState = 'invalid';
                    $exactExpenseId = null;
                } catch (\Throwable) {
                    $exactExpenseState = 'unavailable';
                }
            }
        }

        if (array_key_exists('expenses_search', $_GET)) {
            if ($_GET['expenses_search'] !== '1') {
                $searchResult = self::invalidSearchResult();
            } else {
                try {
                    $cursor = $_GET['expenses_cursor'] ?? null;
                    if ($cursor !== null && !is_string($cursor)) {
                        throw new \InvalidArgumentException('Invalid Expense search cursor.');
                    }
                    $searchResult = ExpensesService::search([
                        'expense_ref' => $_GET['expense_ref'] ?? '',
                        'status' => $_GET['expense_status'] ?? '',
                        'from' => $_GET['expense_from'] ?? '',
                        'to' => $_GET['expense_to'] ?? '',
                    ], 50, $cursor);
                    $rows = (array)($searchResult['rows'] ?? []);
                } catch (\InvalidArgumentException) {
                    $searchResult = self::invalidSearchResult();
                    $rows = [];
                } catch (\Throwable) {
                    $searchResult = self::unavailableSearchResult();
                    $rows = [];
                }
            }
        }

        $view->render('Expenses::index.php', [
            'pageTitle' => t('sbaio.expenses.title'),
            'moduleTitle' => t('sbaio.expenses.title'),
            'moduleDescription' => t('sbaio.expenses.description'),
            'rows' => $rows,
            'currentQuery' => $currentQuery,
            'searchResult' => $searchResult,
            'exactExpense' => $exactExpense,
            'exactExpenseId' => $exactExpenseId,
            'exactExpenseState' => $exactExpenseState,
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

        $createdId = ExpensesService::create($validated['values']);

        self::redirect('ok', t('sbaio.expenses.created_with_id', ['id' => (string)$createdId]), ['expense_id' => $createdId]);
    }

    /** @return array<string,mixed> */
    private static function invalidSearchResult(): array
    {
        return ['valid' => false, 'available' => true, 'rows' => [], 'truncated' => false, 'limit' => 50, 'filters' => [], 'next_cursor' => null];
    }

    /** @return array<string,mixed> */
    private static function unavailableSearchResult(): array
    {
        return ['valid' => true, 'available' => false, 'rows' => [], 'truncated' => false, 'limit' => 50, 'filters' => [], 'next_cursor' => null];
    }

    /** @param array<string,int|string> $params */
    private static function redirect(string $key, string $message, array $params = []): void
    {
        $query = array_merge([$key => $message], $params);
        header('Location: /apps/sbaio/expenses?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));
        exit;
    }
}

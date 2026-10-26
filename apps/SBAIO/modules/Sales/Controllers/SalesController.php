<?php
declare(strict_types=1);

namespace Plugins\Sales\Controllers;

use App\Core\Auth;
use Plugins\Sales\Services\SalesService;

require_once __DIR__ . '/../Services/SalesService.php';

final class SalesController
{
    public static function requireAdministrator(): void
    {
        Auth::requireAppAccess('sbaio');
        $user = Auth::user();
        $authorityRole = strtolower(trim((string)(is_array($user) ? ($user['authority_role'] ?? '') : '')));
        $legacyRole = strtolower(trim((string)(is_array($user) ? ($user['role'] ?? '') : '')));
        $legacyAdmin = preg_replace('/[^a-z0-9]+/', '', $legacyRole) ?? '';
        if (!in_array($authorityRole, ['platform_admin', 'app_admin'], true)
            && !in_array($legacyAdmin, ['admin', 'itadmin', 'itadministrator', 'sysadmin', 'systemadmin', 'systemadministrator'], true)
        ) {
            http_response_code(403);
            echo t('sbaio.sales.admin_required');
            exit;
        }
    }

    public static function index($view): void
    {
        self::requireAdministrator();
        $currentQuery = is_array($_GET ?? null) ? $_GET : [];
        $exactSale = null;
        $exactSaleId = null;
        $exactSaleState = null;
        if (array_key_exists('sale_id', $currentQuery)) {
            $rawSaleId = $currentQuery['sale_id'];
            if (is_string($rawSaleId) && preg_match('/^[1-9][0-9]*$/D', $rawSaleId)) {
                $parsedSaleId = filter_var($rawSaleId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($parsedSaleId !== false) {
                    $exactSaleId = (int)$parsedSaleId;
                    try {
                        $exactSale = SalesService::findById($exactSaleId);
                        $exactSaleState = is_array($exactSale) ? 'found' : 'not_found';
                    } catch (\Throwable) {
                        $exactSaleState = 'unavailable';
                    }
                } else {
                    $exactSaleState = 'invalid';
                }
            } else {
                $exactSaleState = 'invalid';
            }
        }
        $searchRequested = ($currentQuery['sales_search'] ?? '') === '1';
        $searchResult = null;
        if ($searchRequested) {
            try {
                $searchResult = SalesService::search([
                    'sale_ref' => $currentQuery['sale_ref'] ?? '',
                    'status' => $currentQuery['sale_status'] ?? '',
                    'from' => $currentQuery['sale_from'] ?? '',
                    'to' => $currentQuery['sale_to'] ?? '',
                ], 50, is_string($currentQuery['sales_cursor'] ?? null) ? $currentQuery['sales_cursor'] : (($currentQuery['sales_cursor'] ?? null) === null ? null : '[invalid]'));
            } catch (\InvalidArgumentException) {
                $searchResult = ['valid' => false, 'available' => true, 'rows' => [], 'truncated' => false, 'limit' => 50, 'filters' => [], 'next_cursor' => null];
            } catch (\Throwable) {
                $searchResult = ['valid' => true, 'available' => false, 'rows' => [], 'truncated' => false, 'limit' => 50, 'filters' => [], 'next_cursor' => null];
            }
            $rows = (array)($searchResult['rows'] ?? []);
        } else {
            $rows = SalesService::recent();
        }
        $view->render('Sales::index.php', [
            'pageTitle' => t('sbaio.sales.title'),
            'moduleTitle' => t('sbaio.sales.title'),
            'moduleDescription' => t('sbaio.sales.description'),
            'rows' => $rows,
            'currentQuery' => $currentQuery,
            'searchResult' => $searchResult,
            'saleStatusOptions' => SalesService::STATUSES,
            'message' => (string)($_GET['ok'] ?? ''),
            'error' => (string)($_GET['err'] ?? ''),
            'exactSale' => $exactSale,
            'exactSaleId' => $exactSaleId,
            'exactSaleState' => $exactSaleState,
            'createdRecordId' => ($_GET['ok'] ?? '') !== '' ? $exactSaleId : null,
        ]);
    }

    /**
     * Supply the admitted report descriptor and owner-prepared data to the
     * presentation view. Route authorization and report admission remain
     * unchanged in the module route.
     *
     * @param array<string,mixed> $report
     */
    public static function report($view, array $report): void
    {
        $view->render('Sales::report.php', [
            'report' => $report,
            'reportData' => SalesService::reportData(),
        ]);
    }

    public static function create(): void
    {
        self::requireAdministrator();
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $validated = SalesService::validateCreateInput($_POST);
        if (!$validated['valid']) {
            $messages = [
                'sale_ref_required' => 'sbaio.sales.sale_ref_required',
                'sale_ref_invalid' => 'sbaio.sales.sale_ref_invalid',
                'sale_ref_too_long' => 'sbaio.sales.sale_ref_too_long',
                'customer_name_invalid' => 'sbaio.sales.customer_name_invalid',
                'customer_name_too_long' => 'sbaio.sales.customer_name_too_long',
                'amount_required' => 'sbaio.sales.amount_required',
                'amount_invalid' => 'sbaio.sales.amount_invalid',
                'status_invalid' => 'sbaio.sales.status_invalid',
                'sale_date_invalid' => 'sbaio.sales.sale_date_invalid',
            ];
            $errorKey = (string)($validated['errors'][0] ?? 'invalid');
            self::redirect('err', t($messages[$errorKey] ?? 'sbaio.sales.invalid'));
        }

        $createdId = SalesService::create($validated['values']);

        self::redirect('ok', t('sbaio.sales.created_with_id', ['id' => (string)$createdId]), ['sale_id' => $createdId]);
    }

    /** @param array<string,int|string> $params */
    private static function redirect(string $key, string $message, array $params = []): void
    {
        $query = array_merge([$key => $message], $params);
        header('Location: /apps/sbaio/sales?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));
        exit;
    }
}

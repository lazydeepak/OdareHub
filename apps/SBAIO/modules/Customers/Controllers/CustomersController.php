<?php
declare(strict_types=1);

namespace Plugins\Customers\Controllers;

use App\Core\Auth;
use Plugins\Customers\Services\CustomersService;

require_once __DIR__ . '/../Services/CustomersService.php';

final class CustomersController
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
            echo t('sbaio.customers.admin_required');
            exit;
        }
    }

    public static function index($view): void
    {
        self::requireAdministrator();
        $currentQuery = is_array($_GET ?? null) ? $_GET : [];
        $exactCustomer = null;
        $exactCustomerId = null;
        $exactCustomerState = null;
        if (array_key_exists('customer_id', $currentQuery)) {
            $rawCustomerId = $currentQuery['customer_id'];
            if (is_string($rawCustomerId) && preg_match('/^[1-9][0-9]*$/D', $rawCustomerId)) {
                $parsedCustomerId = filter_var($rawCustomerId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($parsedCustomerId !== false) {
                    $exactCustomerId = (int)$parsedCustomerId;
                    try {
                        $exactCustomer = CustomersService::findById($exactCustomerId);
                        $exactCustomerState = is_array($exactCustomer) ? 'found' : 'not_found';
                    } catch (\Throwable) {
                        $exactCustomerState = 'unavailable';
                    }
                } else {
                    $exactCustomerState = 'invalid';
                }
            } else {
                $exactCustomerState = 'invalid';
            }
        }
        $view->render('Customers::index.php', [
            'pageTitle' => t('sbaio.customers.title'),
            'moduleTitle' => t('sbaio.customers.title'),
            'moduleDescription' => t('sbaio.customers.description'),
            'rows' => CustomersService::recent(),
            'currentQuery' => $currentQuery,
            'message' => (string)($currentQuery['ok'] ?? ''),
            'error' => (string)($currentQuery['err'] ?? ''),
            'exactCustomer' => $exactCustomer,
            'exactCustomerId' => $exactCustomerId,
            'exactCustomerState' => $exactCustomerState,
            'createdCustomerId' => ($currentQuery['ok'] ?? '') !== '' ? $exactCustomerId : null,
        ]);
    }

    public static function create(): void
    {
        self::requireAdministrator();
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $validated = CustomersService::validateCreateInput($_POST);
        if (!$validated['valid']) {
            $errorKey = (string)($validated['errors'][0] ?? 'invalid');
            $messages = [
                'required' => 'sbaio.customers.required',
                'email_invalid' => 'sbaio.customers.email_invalid',
                'status_too_long' => 'sbaio.customers.status_invalid',
            ];
            self::redirect('err', t($messages[$errorKey] ?? 'sbaio.customers.invalid'));
        }

        $createdId = CustomersService::create($validated['values']);

        self::redirect('ok', t('sbaio.customers.created'), ['customer_id' => $createdId]);
    }

    /**
     * Supply admitted report metadata and owner-prepared data to the view.
     *
     * @param array<string,mixed> $report
     */
    public static function report($view, array $report): void
    {
        $view->render('Customers::report.php', [
            'report' => $report,
            'reportData' => CustomersService::reportData(),
        ]);
    }

    /** @param array<string,int|string> $params */
    private static function redirect(string $key, string $message, array $params = []): void
    {
        $query = array_merge([$key => $message], $params);
        header('Location: /apps/sbaio/customers?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));
        exit;
    }
}

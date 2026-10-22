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
        $view->render('Customers::index.php', [
            'pageTitle' => t('sbaio.customers.title'),
            'moduleTitle' => t('sbaio.customers.title'),
            'moduleDescription' => t('sbaio.customers.description'),
            'rows' => CustomersService::recent(),
            'message' => (string)($_GET['ok'] ?? ''),
            'error' => (string)($_GET['err'] ?? ''),
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

        CustomersService::create($validated['values']);

        self::redirect('ok', t('sbaio.customers.created'));
    }

    private static function redirect(string $key, string $message): void
    {
        header('Location: /apps/sbaio/customers?' . $key . '=' . rawurlencode($message));
        exit;
    }
}

<?php
declare(strict_types=1);

namespace Plugins\Customers\Controllers;

use App\Core\Auth;
use Plugins\Customers\Services\CustomersService;

require_once __DIR__ . '/../Services/CustomersService.php';

final class CustomersController
{
    public static function index($view): void
    {
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
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $customerName = trim((string)($_POST['customer_name'] ?? ''));
        if ($customerName === '') {
            self::redirect('err', t('sbaio.customers.required'));
        }

        CustomersService::create($_POST);

        self::redirect('ok', t('sbaio.customers.created'));
    }

    private static function redirect(string $key, string $message): void
    {
        header('Location: /apps/sbaio/customers?' . $key . '=' . rawurlencode($message));
        exit;
    }
}

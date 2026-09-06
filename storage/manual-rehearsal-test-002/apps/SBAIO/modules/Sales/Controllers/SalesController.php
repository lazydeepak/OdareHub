<?php
declare(strict_types=1);

namespace Plugins\Sales\Controllers;

use App\Core\Auth;
use Plugins\Sales\Services\SalesService;

require_once __DIR__ . '/../Services/SalesService.php';

final class SalesController
{
    public static function index($view): void
    {
        $view->render('Sales::index.php', [
            'pageTitle' => t('sbaio.sales.title'),
            'moduleTitle' => t('sbaio.sales.title'),
            'moduleDescription' => t('sbaio.sales.description'),
            'rows' => SalesService::recent(),
            'message' => (string)($_GET['ok'] ?? ''),
            'error' => (string)($_GET['err'] ?? ''),
        ]);
    }

    public static function create(): void
    {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $saleRef = trim((string)($_POST['sale_ref'] ?? ''));
        if ($saleRef === '') {
            self::redirect('err', t('sbaio.sales.required'));
        }

        SalesService::create($_POST);

        self::redirect('ok', t('sbaio.sales.created'));
    }

    private static function redirect(string $key, string $message): void
    {
        header('Location: /apps/sbaio/sales?' . $key . '=' . rawurlencode($message));
        exit;
    }
}

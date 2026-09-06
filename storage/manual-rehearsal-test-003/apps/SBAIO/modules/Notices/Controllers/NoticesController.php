<?php
declare(strict_types=1);

namespace Plugins\Notices\Controllers;

use App\Core\Auth;
use Plugins\Notices\Services\NoticesService;

require_once __DIR__ . '/../Services/NoticesService.php';

final class NoticesController
{
    public static function index($view): void
    {
        $view->render('Notices::index.php', [
            'pageTitle' => t('sbaio.notices.title'),
            'moduleTitle' => t('sbaio.notices.title'),
            'moduleDescription' => t('sbaio.notices.description'),
            'rows' => NoticesService::recent(),
            'staffRows' => NoticesService::activeStaff(),
            'message' => (string)($_GET['ok'] ?? ''),
            'error' => (string)($_GET['err'] ?? ''),
        ]);
    }

    public static function create(): void
    {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $title = trim((string)($_POST['title'] ?? ''));
        if ($title === '') {
            self::redirect('err', t('sbaio.notices.required'));
        }

        NoticesService::create($_POST);

        self::redirect('ok', t('sbaio.notices.created'));
    }

    private static function redirect(string $key, string $message): void
    {
        header('Location: /apps/sbaio/notices?' . $key . '=' . rawurlencode($message));
        exit;
    }
}

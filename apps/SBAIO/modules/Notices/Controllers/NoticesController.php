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
        $currentQuery = is_array($_GET ?? null) ? $_GET : [];
        $exactNotice = null;
        $exactNoticeId = null;
        $exactNoticeState = null;
        if (array_key_exists('notice_id', $currentQuery)) {
            $rawNoticeId = $currentQuery['notice_id'];
            if (is_string($rawNoticeId) && preg_match('/^[1-9][0-9]*$/D', $rawNoticeId)) {
                $parsedNoticeId = filter_var($rawNoticeId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($parsedNoticeId !== false) {
                    $exactNoticeId = (int)$parsedNoticeId;
                    try {
                        $exactNotice = NoticesService::findById($exactNoticeId);
                        $exactNoticeState = is_array($exactNotice) ? 'found' : 'not_found';
                    } catch (\Throwable) {
                        $exactNoticeState = 'unavailable';
                    }
                } else {
                    $exactNoticeState = 'invalid';
                }
            } else {
                $exactNoticeState = 'invalid';
            }
        }

        $view->render('Notices::index.php', [
            'pageTitle' => t('sbaio.notices.title'),
            'moduleTitle' => t('sbaio.notices.title'),
            'moduleDescription' => t('sbaio.notices.description'),
            'rows' => NoticesService::recent(),
            'staffRows' => NoticesService::activeStaff(),
            'message' => (string)($_GET['ok'] ?? ''),
            'error' => (string)($_GET['err'] ?? ''),
            'exactNotice' => $exactNotice,
            'exactNoticeId' => $exactNoticeId,
            'exactNoticeState' => $exactNoticeState,
            'createdRecordId' => ($_GET['ok'] ?? '') !== '' ? $exactNoticeId : null,
        ]);
    }

    public static function create(): void
    {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $validated = NoticesService::validateCreateInput($_POST);
        if (!$validated['valid']) {
            $messageKey = in_array('title_required', $validated['errors'], true)
                ? 'sbaio.notices.required'
                : 'sbaio.notices.invalid';
            self::redirect('err', t($messageKey));
        }

        $createdId = NoticesService::create($validated['values']);

        self::redirect('ok', t('sbaio.notices.created_with_id', ['id' => (string)$createdId]), ['notice_id' => $createdId]);
    }

    /** @param array<string,int|string> $params */
    private static function redirect(string $key, string $message, array $params = []): void
    {
        $query = [$key => $message];
        foreach ($params as $paramKey => $paramValue) {
            $query[$paramKey] = (string)$paramValue;
        }
        header('Location: /apps/sbaio/notices?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));
        exit;
    }
}

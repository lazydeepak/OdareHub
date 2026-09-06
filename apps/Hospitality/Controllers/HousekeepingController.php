<?php
declare(strict_types=1);

namespace Apps\Hospitality\Controllers;

use App\Core\Auth;
use Apps\Hospitality\Services\HousekeepingService;
use Throwable;

final class HousekeepingController
{
    public static function index($view): void
    {
        HospitalityAccess::requireView($view);
        $rows = null;
        try {
            $rows = HousekeepingService::board();
        } catch (Throwable) {
            $rows = null;
        }

        $view->render('hospitality::housekeeping.php', [
            'pageTitle' => t('hospitality.nav.housekeeping'),
            'rowCount' => is_array($rows) ? count($rows) : null,
            'rows' => $rows,
            'ok' => self::flashKey('ok'),
            'err' => self::flashKey('err'),
        ]);
    }

    public static function status($view): void
    {
        HospitalityAccess::requireManage($view);
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
        try {
            HousekeepingService::updateStatus((int)($_POST['room_id'] ?? 0), $_POST);
            HospitalityFlash::redirectOk('/apps/hospitality/housekeeping', 'HOSPITALITY_HK_UPDATED');
        } catch (Throwable $e) {
            HospitalityFlash::redirectErr('/apps/hospitality/housekeeping', $e->getMessage());
        }
    }

    private static function flashKey(string $param): string
    {
        $raw = trim((string)($_GET[$param] ?? ''));
        return preg_match('/^[a-z0-9_.]+$/', strtolower($raw)) === 1 ? strtolower($raw) : '';
    }
}

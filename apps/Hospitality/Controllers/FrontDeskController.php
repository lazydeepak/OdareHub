<?php
declare(strict_types=1);

namespace Apps\Hospitality\Controllers;

use App\Core\Auth;
use Apps\Hospitality\Services\FrontDeskService;
use Throwable;

final class FrontDeskController
{
    public static function index($view): void
    {
        HospitalityAccess::requireView($view);

        $board = null;
        $folios = [];
        try {
            $board = FrontDeskService::board();
            $folios = is_array($board) ? FrontDeskService::folioSummaries() : [];
        } catch (Throwable) {
            $board = null; // schema not installed yet: honest read-only state
        }

        $view->render('hospitality::front_desk.php', [
            'pageTitle' => t('hospitality.nav.front_desk'),
            'board' => $board,
            'folios' => $folios,
            'ok' => self::flashKey('ok'),
            'err' => self::flashKey('err'),
        ]);
    }

    public static function checkin($view): void
    {
        HospitalityAccess::requireManage($view);
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
        try {
            FrontDeskService::checkIn((int)($_POST['id'] ?? 0));
            HospitalityFlash::redirectOk('/apps/hospitality/front-desk', 'HOSPITALITY_FD_CHECKED_IN');
        } catch (Throwable $e) {
            HospitalityFlash::redirectErr('/apps/hospitality/front-desk', $e->getMessage());
        }
    }

    public static function checkout($view): void
    {
        HospitalityAccess::requireManage($view);
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
        try {
            FrontDeskService::checkout((int)($_POST['id'] ?? 0));
            HospitalityFlash::redirectOk('/apps/hospitality/front-desk', 'HOSPITALITY_FD_CHECKED_OUT');
        } catch (Throwable $e) {
            HospitalityFlash::redirectErr('/apps/hospitality/front-desk', $e->getMessage());
        }
    }

    public static function addCharge($view): void
    {
        HospitalityAccess::requireManage($view);
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
        try {
            FrontDeskService::addChargeForReservation((int)($_POST['reservation_id'] ?? 0), $_POST);
            HospitalityFlash::redirectOk('/apps/hospitality/front-desk', 'HOSPITALITY_FD_CHARGE_ADDED');
        } catch (Throwable $e) {
            HospitalityFlash::redirectErr('/apps/hospitality/front-desk', $e->getMessage());
        }
    }

    public static function voidCharge($view): void
    {
        HospitalityAccess::requireManage($view);
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
        try {
            FrontDeskService::voidCharge((int)($_POST['charge_id'] ?? 0));
            HospitalityFlash::redirectOk('/apps/hospitality/front-desk', 'HOSPITALITY_FD_CHARGE_VOIDED');
        } catch (Throwable $e) {
            HospitalityFlash::redirectErr('/apps/hospitality/front-desk', $e->getMessage());
        }
    }

    private static function flashKey(string $param): string
    {
        $raw = trim((string)($_GET[$param] ?? ''));
        return preg_match('/^[a-z0-9_.]+$/', strtolower($raw)) === 1 ? strtolower($raw) : '';
    }
}

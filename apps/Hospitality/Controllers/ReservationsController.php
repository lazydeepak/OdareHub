<?php
declare(strict_types=1);

namespace Apps\Hospitality\Controllers;

use App\Core\Auth;
use Apps\Hospitality\Services\ReservationsService;
use Throwable;

final class ReservationsController
{
    public static function index($view): void
    {
        HospitalityAccess::requireView($view);

        $rows = null;
        $options = ['guests' => [], 'rooms' => []];
        try {
            $rows = ReservationsService::listReservations();
            $options = ReservationsService::formOptions();
        } catch (Throwable) {
            $rows = null; // schema not installed yet: honest read-only state
        }

        $view->render('hospitality::reservations.php', [
            'pageTitle' => t('hospitality.nav.reservations'),
            'rowCount' => is_array($rows) ? count($rows) : null,
            'rows' => $rows,
            'guestOptions' => $options['guests'],
            'roomOptions' => $options['rooms'],
            'ok' => self::flashKey('ok'),
            'err' => self::flashKey('err'),
        ]);
    }

    public static function create($view): void
    {
        HospitalityAccess::requireManage($view);
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
        try {
            ReservationsService::create($_POST);
            HospitalityFlash::redirectOk('/apps/hospitality/reservations', 'HOSPITALITY_RES_CREATED');
        } catch (Throwable $e) {
            HospitalityFlash::redirectErr('/apps/hospitality/reservations', $e->getMessage());
        }
    }

    public static function transition($view): void
    {
        HospitalityAccess::requireManage($view);
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
        try {
            ReservationsService::transition((int)($_POST['id'] ?? 0), (string)($_POST['to_status'] ?? ''));
            HospitalityFlash::redirectOk('/apps/hospitality/reservations', 'HOSPITALITY_RES_TRANSITION_DONE');
        } catch (Throwable $e) {
            HospitalityFlash::redirectErr('/apps/hospitality/reservations', $e->getMessage());
        }
    }

    private static function flashKey(string $param): string
    {
        $raw = trim((string)($_GET[$param] ?? ''));
        return preg_match('/^[a-z0-9_.]+$/', strtolower($raw)) === 1 ? strtolower($raw) : '';
    }
}

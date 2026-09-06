<?php
declare(strict_types=1);

namespace Apps\Hospitality\Controllers;

use App\Core\Auth;
use Apps\Hospitality\Services\RoomsService;
use Throwable;

final class RoomsController
{
    public static function index($view): void
    {
        HospitalityAccess::requireView($view);
        $rows = null;
        try {
            $rows = RoomsService::listRooms();
        } catch (Throwable) {
            $rows = null; // schema not installed yet: honest read-only state
        }

        $view->render('hospitality::rooms.php', [
            'pageTitle' => t('hospitality.nav.rooms'),
            'rowCount' => is_array($rows) ? count($rows) : null,
            'rows' => $rows,
            'ok' => self::flashKey('ok'),
            'err' => self::flashKey('err'),
        ]);
    }

    public static function create($view): void
    {
        HospitalityAccess::requireManage($view);
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
        try {
            RoomsService::create($_POST);
            HospitalityFlash::redirectOk('/apps/hospitality/rooms', 'HOSPITALITY_ROOMS_CREATED');
        } catch (Throwable $e) {
            HospitalityFlash::redirectErr('/apps/hospitality/rooms', $e->getMessage());
        }
    }

    public static function update($view): void
    {
        HospitalityAccess::requireManage($view);
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
        try {
            RoomsService::update((int)($_POST['id'] ?? 0), $_POST);
            HospitalityFlash::redirectOk('/apps/hospitality/rooms', 'HOSPITALITY_ROOMS_UPDATED');
        } catch (Throwable $e) {
            HospitalityFlash::redirectErr('/apps/hospitality/rooms', $e->getMessage());
        }
    }

    public static function status($view): void
    {
        HospitalityAccess::requireManage($view);
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
        try {
            RoomsService::setStatus((int)($_POST['id'] ?? 0), (string)($_POST['room_status'] ?? ''));
            HospitalityFlash::redirectOk('/apps/hospitality/rooms', 'HOSPITALITY_ROOMS_STATUS_CHANGED');
        } catch (Throwable $e) {
            HospitalityFlash::redirectErr('/apps/hospitality/rooms', $e->getMessage());
        }
    }

    private static function flashKey(string $param): string
    {
        $raw = trim((string)($_GET[$param] ?? ''));
        return preg_match('/^[a-z0-9_.]+$/', strtolower($raw)) === 1 ? strtolower($raw) : '';
    }
}

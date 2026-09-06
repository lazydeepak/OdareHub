<?php
declare(strict_types=1);

namespace Apps\Hospitality\Controllers;

use App\Core\Auth;
use Apps\Hospitality\Services\GuestsService;
use Throwable;

final class GuestsController
{
    public static function index($view): void
    {
        HospitalityAccess::requireView($view);
        $rows = null;
        try {
            $rows = GuestsService::listGuests();
        } catch (Throwable) {
            $rows = null; // schema not installed yet: honest read-only state
        }

        $view->render('hospitality::guests.php', [
            'pageTitle' => t('hospitality.nav.guests'),
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
            GuestsService::create($_POST);
            HospitalityFlash::redirectOk('/apps/hospitality/guests', 'HOSPITALITY_GUESTS_CREATED');
        } catch (Throwable $e) {
            HospitalityFlash::redirectErr('/apps/hospitality/guests', $e->getMessage());
        }
    }

    public static function update($view): void
    {
        HospitalityAccess::requireManage($view);
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
        try {
            GuestsService::update((int)($_POST['id'] ?? 0), $_POST);
            HospitalityFlash::redirectOk('/apps/hospitality/guests', 'HOSPITALITY_GUESTS_UPDATED');
        } catch (Throwable $e) {
            HospitalityFlash::redirectErr('/apps/hospitality/guests', $e->getMessage());
        }
    }

    public static function status($view): void
    {
        HospitalityAccess::requireManage($view);
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
        try {
            GuestsService::setStatus((int)($_POST['id'] ?? 0), (string)($_POST['guest_status'] ?? ''));
            HospitalityFlash::redirectOk('/apps/hospitality/guests', 'HOSPITALITY_GUESTS_STATUS_CHANGED');
        } catch (Throwable $e) {
            HospitalityFlash::redirectErr('/apps/hospitality/guests', $e->getMessage());
        }
    }

    private static function flashKey(string $param): string
    {
        $raw = trim((string)($_GET[$param] ?? ''));
        return preg_match('/^[a-z0-9_.]+$/', strtolower($raw)) === 1 ? strtolower($raw) : '';
    }
}

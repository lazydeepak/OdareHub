<?php
declare(strict_types=1);

namespace Apps\Hospitality\Controllers;

use App\Core\Auth;
use App\Core\AclPolicy;
use Apps\Hospitality\Services\FrontDeskService;
use Apps\Hospitality\Services\HousekeepingService;
use Throwable;


/**
 * Operator-layer action edge for jailed /u/{username}/hospitality mutations.
 *
 * Performs only: hospitality.manage check, CSRF validation, input forwarding to
 * the single domain services (HousekeepingService::updateStatus,
 * FrontDeskService::checkIn/checkout), and a jailed success/error redirect.
 * It implements no business workflow of its own.
 */
final class OperatorActions
{
    public static function housekeepingStatus(array $operatorRoute): void
    {
        $username = rawurlencode((string)($operatorRoute['username'] ?? ''));
        $redirectTo = '/u/' . $username . '/hospitality';

        try {
            if (!AclPolicy::can('hospitality.manage', Auth::user())) {
                throw new \RuntimeException('HOSPITALITY_FORBIDDEN_MANAGE');
            }

            Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $redirectTo);

            HousekeepingService::updateStatus(self::normalizePositiveId($_POST['room_id'] ?? null), $_POST);

            $_SESSION['operator_hospitality_flash_ok'] = 'HOSPITALITY_HK_UPDATED';
        } catch (Throwable $e) {
            $_SESSION['operator_hospitality_flash_err'] = $e->getMessage();
        }

        header('Location: ' . $redirectTo, true, 302);
        exit;
    }

    public static function frontDeskAddCharge(array $operatorRoute): void
    {
        $username = rawurlencode((string)($operatorRoute['username'] ?? ''));
        $redirectTo = '/u/' . $username . '/hospitality';

        try {
            if (!AclPolicy::can('hospitality.manage', Auth::user())) {
                throw new \RuntimeException('HOSPITALITY_FORBIDDEN_MANAGE');
            }

            Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $redirectTo);

            FrontDeskService::addChargeForReservation(self::normalizePositiveId($_POST['reservation_id'] ?? null), $_POST);

            $_SESSION['operator_hospitality_flash_ok'] = 'HOSPITALITY_FD_CHARGE_ADDED';
        } catch (Throwable $e) {
            $_SESSION['operator_hospitality_flash_err'] = $e->getMessage();
        }

        header('Location: ' . $redirectTo, true, 302);
        exit;
    }

    public static function frontDeskMarkNoShow(array $operatorRoute): void
    {
        $username = rawurlencode((string)($operatorRoute['username'] ?? ''));
        $redirectTo = '/u/' . $username . '/hospitality';
        try {
            if (!AclPolicy::can('hospitality.manage', Auth::user())) {
                throw new \RuntimeException('HOSPITALITY_FORBIDDEN_MANAGE');
            }
            Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $redirectTo);
            FrontDeskService::markReservationNoShow(self::normalizePositiveId($_POST['reservation_id'] ?? null));
            $_SESSION['operator_hospitality_flash_ok'] = 'HOSPITALITY_RES_NO_SHOW';
        } catch (Throwable $e) {
            $_SESSION['operator_hospitality_flash_err'] = $e->getMessage();
        }
        header('Location: ' . $redirectTo, true, 302);
        exit;
    }

    public static function frontDeskVoidCharge(array $operatorRoute): void
    {
        $username = rawurlencode((string)($operatorRoute['username'] ?? ''));
        $redirectTo = '/u/' . $username . '/hospitality';

        try {
            if (!AclPolicy::can('hospitality.manage', Auth::user())) {
                throw new \RuntimeException('HOSPITALITY_FORBIDDEN_MANAGE');
            }
            Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $redirectTo);

            FrontDeskService::voidCharge(self::normalizePositiveId($_POST['charge_id'] ?? null));
            $_SESSION['operator_hospitality_flash_ok'] = 'HOSPITALITY_FD_CHARGE_VOIDED';
        } catch (Throwable $e) {
            $_SESSION['operator_hospitality_flash_err'] = $e->getMessage();
        }
        header('Location: ' . $redirectTo, true, 302);
        exit;
    }

    public static function frontDeskCancelReservation(array $operatorRoute): void
    {
        $username = rawurlencode((string)($operatorRoute['username'] ?? ''));
        $redirectTo = '/u/' . $username . '/hospitality';

        try {
            if (!AclPolicy::can('hospitality.manage', Auth::user())) {
                throw new \RuntimeException('HOSPITALITY_FORBIDDEN_MANAGE');
            }

            Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $redirectTo);

            FrontDeskService::cancelReservation(self::normalizePositiveId($_POST['reservation_id'] ?? null));

            $_SESSION['operator_hospitality_flash_ok'] = 'HOSPITALITY_RES_CANCELLED';
        } catch (Throwable $e) {
            $_SESSION['operator_hospitality_flash_err'] = $e->getMessage();
        }

        header('Location: ' . $redirectTo, true, 302);
        exit;
    }

    public static function frontDeskCheckOut(array $operatorRoute): void
    {
        $username = rawurlencode((string)($operatorRoute['username'] ?? ''));
        $redirectTo = '/u/' . $username . '/hospitality';

        try {
            if (!AclPolicy::can('hospitality.manage', Auth::user())) {
                throw new \RuntimeException('HOSPITALITY_FORBIDDEN_MANAGE');
            }

            Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $redirectTo);

            FrontDeskService::checkout(self::normalizePositiveId($_POST['reservation_id'] ?? null));

            $_SESSION['operator_hospitality_flash_ok'] = 'HOSPITALITY_FD_CHECKED_OUT';
        } catch (Throwable $e) {
            $_SESSION['operator_hospitality_flash_err'] = $e->getMessage();
        }

        header('Location: ' . $redirectTo, true, 302);
        exit;
    }

    public static function frontDeskCheckIn(array $operatorRoute): void
    {
        $username = rawurlencode((string)($operatorRoute['username'] ?? ''));
        $redirectTo = '/u/' . $username . '/hospitality';

        try {
            if (!AclPolicy::can('hospitality.manage', Auth::user())) {
                throw new \RuntimeException('HOSPITALITY_FORBIDDEN_MANAGE');
            }

            Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $redirectTo);

            FrontDeskService::checkIn(self::normalizePositiveId($_POST['reservation_id'] ?? null));

            $_SESSION['operator_hospitality_flash_ok'] = 'HOSPITALITY_FD_CHECKED_IN';
        } catch (Throwable $e) {
            $_SESSION['operator_hospitality_flash_err'] = $e->getMessage();
        }

        header('Location: ' . $redirectTo, true, 302);
        exit;
    }

    /**
     * Preserve an exact positive record identity from an untrusted POST value.
     * PHP integer coercion can turn malformed or overflowing input into a
     * different valid identifier before the domain service validates it.
     *
     * @param mixed $value
     */
    private static function normalizePositiveId(mixed $value): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (!is_string($value) || preg_match('/^[1-9][0-9]*$/D', $value) !== 1) {
            throw new \InvalidArgumentException('HOSPITALITY_ID_INVALID');
        }

        $maximum = (string)PHP_INT_MAX;
        if (strlen($value) > strlen($maximum)
            || (strlen($value) === strlen($maximum) && strcmp($value, $maximum) > 0)) {
            throw new \InvalidArgumentException('HOSPITALITY_ID_INVALID');
        }

        return (int)$value;
    }
}

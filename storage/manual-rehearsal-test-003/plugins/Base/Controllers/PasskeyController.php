<?php
declare(strict_types=1);

namespace Plugins\Base\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\View;
use App\Services\PasskeyService;

final class PasskeyController
{
    // ----------------------------------------------------------------
    // Management page
    // ----------------------------------------------------------------

    public static function renderManage(View $view): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            header('Location: /login');
            exit;
        }

        $user   = Auth::user();
        $userId = (int)($user['id'] ?? 0);

        $passkeys = PasskeyService::listPasskeys($userId);

        $view->render('Base::account/passkey_manage.php', [
            'pageTitle' => (string)__('account.passkey.manage_title') ?: 'Passkeys',
            'passkeys'  => $passkeys,
            'flash'     => self::pullFlash('ok'),
            'error'     => self::pullFlash('err'),
        ]);
    }

    // ----------------------------------------------------------------
    // JSON: registration challenge (authenticated)
    // ----------------------------------------------------------------

    public static function apiRegistrationChallenge(): void
    {
        Auth::bootSession();
        header('Content-Type: application/json');

        if (!Auth::isLoggedIn()) {
            echo json_encode(['error' => 'Not authenticated']);
            exit;
        }

        $user        = Auth::user();
        $userId      = (int)($user['id'] ?? 0);
        $userName    = (string)($user['email'] ?? '');
        $displayName = (string)($user['display_name'] ?? $userName);

        try {
            $json = PasskeyService::registrationChallenge($userId, $userName, $displayName);
            echo $json;
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    // ----------------------------------------------------------------
    // JSON: complete registration (authenticated)
    // ----------------------------------------------------------------

    public static function apiRegistrationComplete(): void
    {
        Auth::bootSession();
        header('Content-Type: application/json');

        if (!Auth::isLoggedIn()) {
            echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
            exit;
        }

        $user   = Auth::user();
        $userId = (int)($user['id'] ?? 0);

        $raw  = (string)file_get_contents('php://input');
        $body = json_decode($raw);

        if (!$body instanceof \stdClass) {
            echo json_encode(['ok' => false, 'error' => 'Invalid request body']);
            exit;
        }

        $deviceName = mb_substr(trim((string)($body->device_name ?? '')), 0, 190);

        try {
            PasskeyService::completeRegistration($userId, $body, $deviceName);
            $_SESSION['passkey_flash_ok'] = (string)__('account.passkey.registered_success') ?: 'Passkey registered successfully.';
            echo json_encode(['ok' => true]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ----------------------------------------------------------------
    // Form POST: delete a passkey (authenticated)
    // ----------------------------------------------------------------

    public static function apiDelete(array $post): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            header('Location: /login');
            exit;
        }

        Auth::requireCsrf((string)($post['csrf'] ?? ''));

        $user   = Auth::user();
        $userId = (int)($user['id'] ?? 0);
        $id     = (int)($post['passkey_id'] ?? 0);

        if ($id > 0) {
            PasskeyService::deletePasskey($userId, $id);
            $_SESSION['passkey_flash_ok'] = (string)__('account.passkey.deleted_success') ?: 'Passkey removed.';
        }

        header('Location: /account/passkey/manage');
        exit;
    }

    // ----------------------------------------------------------------
    // JSON: login challenge (no auth required)
    // ----------------------------------------------------------------

    public static function apiLoginChallenge(): void
    {
        Auth::bootSession();
        header('Content-Type: application/json');

        $raw   = (string)file_get_contents('php://input');
        $body  = json_decode($raw);
        $email = trim((string)($body->email ?? ''));

        try {
            echo PasskeyService::loginChallenge($email);
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    // ----------------------------------------------------------------
    // JSON: complete login (no auth required)
    // ----------------------------------------------------------------

    public static function apiLoginComplete(): void
    {
        Auth::bootSession();
        header('Content-Type: application/json');

        if (Auth::isLoggedIn()) {
            $next = Auth::consumeIntendedUrl('/');
            echo json_encode(['ok' => true, 'redirect' => $next]);
            exit;
        }

        $raw  = (string)file_get_contents('php://input');
        $body = json_decode($raw);

        if (!$body instanceof \stdClass) {
            echo json_encode(['ok' => false, 'error' => 'Invalid request body']);
            exit;
        }

        try {
            $userId = PasskeyService::completeLogin($body);

            // If the user has TOTP 2FA enabled, passkey acts as the first factor only.
            // Route through the standard /2fa challenge instead of completing login.
            $userRow = DB::fetchOne(
                'SELECT twofa_enabled, email FROM users WHERE id = ? LIMIT 1',
                [$userId]
            );
            if (!empty($userRow['twofa_enabled'])) {
                $_SESSION['pending_2fa_user_id'] = $userId;
                $_SESSION['pending_2fa_email']   = (string)($userRow['email'] ?? '');
                echo json_encode(['ok' => true, 'redirect' => '/2fa']);
                exit;
            }

            self::completeLoginWithoutRedirect($userId);
            $next = Auth::consumeIntendedUrl(self::defaultPostLoginLanding());            echo json_encode(['ok' => true, 'redirect' => $next]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private static function pullFlash(string $key): string
    {
        $sessionKey = 'passkey_flash_' . $key;
        $val        = (string)($_SESSION[$sessionKey] ?? '');
        unset($_SESSION[$sessionKey]);
        return $val;
    }

    private static function completeLoginWithoutRedirect(int $userId): void
    {
        // Delegates to Auth::completeLoginInPlace() — single source of truth for
        // session mutation. No drift risk if Auth::completeLogin() is ever changed.
        Auth::completeLoginInPlace($userId);
    }

    private static function defaultPostLoginLanding(): string
    {
        if (class_exists('\\Plugins\\Base\\Services\\UserDashboardAssignmentService')) {
            $user = Auth::user();
            if ($user) {
                return \Plugins\Base\Services\UserDashboardAssignmentService::safePostLoginLanding($user);
            }
        }

        return '/';
    }
}

<?php
declare(strict_types=1);

namespace Plugins\Base\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Services\InviteLifecycleService;
use App\Services\MyAccountService;
use App\Services\TwoFactorQrService;

final class MyAccountController
{
    public static function render(View $view): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl('/account');
            header('Location: /login');
            exit;
        }

        $service = new MyAccountService();
        $user = Auth::user();
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) {
            header('Location: /login');
            exit;
        }

        $account = $service->accountForUser($userId);
        $view->render('Base::account/my_account.php', [
            'pageTitle' => 'My Account',
            'account' => $account,
            'policy' => $service->passwordPolicy(),
            'flash' => self::pullFlash('ok'),
            'error' => self::pullFlash('err'),
        ]);
    }

    public static function renderSetup(View $view): void
    {
        Auth::bootSession();
        $token = trim((string)($_GET['token'] ?? ''));
        $service = new InviteLifecycleService();
        $record = $service->validateSetupToken($token);

        $view->render('Base::auth/account_setup.php', [
            'pageTitle' => 'Account Setup',
            'token' => $token,
            'tokenValid' => is_array($record),
            'record' => is_array($record) ? $record : [],
            'policy' => (new MyAccountService())->passwordPolicy(),
            'message' => (string)($_SESSION['account_setup_notice'] ?? ''),
            'error' => (string)($_SESSION['account_setup_error'] ?? ''),
        ]);

        unset($_SESSION['account_setup_notice'], $_SESSION['account_setup_error']);
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function updateProfile(array $input): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl('/account');
            header('Location: /login');
            exit;
        }

        try {
            Auth::requireCsrf((string)($input['csrf'] ?? ''), '/account');
            $userId = (int)(Auth::user()['id'] ?? 0);
            if ($userId <= 0) {
                throw new \RuntimeException('Could not resolve the current user.');
            }

            $service = new MyAccountService();
            $service->updateProfile($userId, $input);
            self::flash('ok', 'Profile updated.');
        } catch (\Throwable $e) {
            self::flash('err', $e->getMessage());
        }

        header('Location: /account');
        exit;
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function changePassword(array $input): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl('/account');
            header('Location: /login');
            exit;
        }

        try {
            Auth::requireCsrf((string)($input['csrf'] ?? ''), '/account');
            $userId = (int)(Auth::user()['id'] ?? 0);
            if ($userId <= 0) {
                throw new \RuntimeException('Could not resolve the current user.');
            }

            $service = new MyAccountService();
            $service->changePassword($userId, $input);
            self::flash('ok', 'Password changed successfully.');
        } catch (\Throwable $e) {
            self::flash('err', $e->getMessage());
        }

        header('Location: /account');
        exit;
    }

    public static function renderTwoFactorSetup(View $view): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl('/account/2fa/setup');
            header('Location: /login');
            exit;
        }

        $userId = (int)(Auth::user()['id'] ?? 0);
        if ($userId <= 0) {
            header('Location: /login');
            exit;
        }

        $service = new MyAccountService();
        $account = $service->accountForUser($userId);
        $isReplacement = ((int)($_GET['replace'] ?? 0)) === 1;

        if (!empty($account['twofa_enabled']) && !$isReplacement) {
            header('Location: /account/2fa/manage');
            exit;
        }

        try {
            $secretInSession = trim((string)($_SESSION['my_account_2fa_secret'] ?? ''));
            if ($secretInSession === '' || $isReplacement) {
                $pending = $service->beginTwoFactorEnrollment($userId, $isReplacement);
                $_SESSION['my_account_2fa_secret'] = (string)$pending['secret'];
                $_SESSION['my_account_2fa_email'] = (string)$pending['email'];
            }
        } catch (\Throwable $e) {
            self::flash('err', $e->getMessage());
            header('Location: /account');
            exit;
        }

        $secret = trim((string)($_SESSION['my_account_2fa_secret'] ?? ''));
        $email = trim((string)($_SESSION['my_account_2fa_email'] ?? ''));
        $uri = '';
        if ($secret !== '' && $email !== '') {
            $uri = \App\Core\TOTP::provisioningUri(\App\Core\TOTP::defaultIssuer(), $email, $secret);
        }
        $qrPayload = TwoFactorQrService::buildPayload($uri);

        $view->render('Base::account/twofa_setup.php', [
            'pageTitle' => 'Account 2FA Setup',
            'email' => $email,
            'secret' => $secret,
            'uri' => $uri,
            'qr_payload' => $qrPayload,
            'is_replacement' => $isReplacement,
            'flash' => self::pullFlash('ok'),
            'error' => self::pullFlash('err'),
        ]);
    }

    public static function renderTwoFactorManage(View $view): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl('/account/2fa/manage');
            header('Location: /login');
            exit;
        }

        $userId = (int)(Auth::user()['id'] ?? 0);
        if ($userId <= 0) {
            header('Location: /login');
            exit;
        }

        $service = new MyAccountService();
        $account = $service->accountForUser($userId);
        if (empty($account['twofa_enabled'])) {
            header('Location: /account/2fa/setup');
            exit;
        }

        $view->render('Base::account/twofa_manage.php', [
            'pageTitle' => 'Manage 2FA',
            'account' => $account,
            'remaining' => $service->remainingRecoveryCodeCount($userId),
            'flash' => self::pullFlash('ok'),
            'error' => self::pullFlash('err'),
        ]);
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function completeTwoFactorSetup(array $input): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl('/account/2fa/setup');
            header('Location: /login');
            exit;
        }

        try {
            Auth::requireCsrf((string)($input['csrf'] ?? ''), '/account/2fa/setup');
            $userId = (int)(Auth::user()['id'] ?? 0);
            if ($userId <= 0) {
                throw new \RuntimeException('Could not resolve the current user.');
            }

            $secret = trim((string)($_SESSION['my_account_2fa_secret'] ?? ''));
            if ($secret === '') {
                throw new \RuntimeException('2FA setup session expired. Start setup again.');
            }

            $service = new MyAccountService();
            $service->completeTwoFactorEnrollment($userId, $secret, (string)($input['code'] ?? ''));

            unset($_SESSION['my_account_2fa_secret'], $_SESSION['my_account_2fa_email']);
            // Recovery codes were stashed in session by completeTwoFactorEnrollment; show them now.
            header('Location: /account/2fa/recovery-codes');
            exit;
        } catch (\Throwable $e) {
            self::flash('err', $e->getMessage());
            header('Location: /account/2fa/setup');
            exit;
        }
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function disableTwoFactor(array $input): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl('/account/2fa/manage');
            header('Location: /login');
            exit;
        }

        try {
            Auth::requireCsrf((string)($input['csrf'] ?? ''), '/account/2fa/manage');
            $userId = (int)(Auth::user()['id'] ?? 0);
            if ($userId <= 0) {
                throw new \RuntimeException('Could not resolve the current user.');
            }

            $service = new MyAccountService();
            $service->disableTwoFactor($userId, (string)($input['code'] ?? ''));
            unset($_SESSION['my_account_2fa_secret'], $_SESSION['my_account_2fa_email']);

            self::flash('ok', self::tr('account.security.twofa_disabled_success', '2FA has been disabled for your account.'));
            header('Location: /account');
            exit;
        } catch (\Throwable $e) {
            self::flash('err', $e->getMessage());
            header('Location: /account/2fa/manage');
            exit;
        }
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function regenerateTwoFactorSetup(array $input): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl('/account/2fa/manage');
            header('Location: /login');
            exit;
        }

        try {
            Auth::requireCsrf((string)($input['csrf'] ?? ''), '/account/2fa/manage');
            $userId = (int)(Auth::user()['id'] ?? 0);
            if ($userId <= 0) {
                throw new \RuntimeException('Could not resolve the current user.');
            }

            $service = new MyAccountService();
            $service->authorizeTwoFactorRegeneration($userId, (string)($input['code'] ?? ''));

            $pending = $service->beginTwoFactorEnrollment($userId, true);
            $_SESSION['my_account_2fa_secret'] = (string)$pending['secret'];
            $_SESSION['my_account_2fa_email'] = (string)$pending['email'];

            self::flash('ok', self::tr('account.security.twofa_regenerate_ready', 'Enter a code from the newly added authenticator entry to complete 2FA key rotation.'));
            header('Location: /account/2fa/setup?replace=1');
            exit;
        } catch (\Throwable $e) {
            self::flash('err', $e->getMessage());
            header('Location: /account/2fa/manage');
            exit;
        }
    }

    public static function renderRecoveryCodes(View $view): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl('/account/2fa/recovery-codes');
            header('Location: /login');
            exit;
        }

        $userId = (int)(Auth::user()['id'] ?? 0);
        if ($userId <= 0) {
            header('Location: /login');
            exit;
        }

        $service = new MyAccountService();
        $account = $service->accountForUser($userId);
        if (empty($account['twofa_enabled'])) {
            header('Location: /account/2fa/setup');
            exit;
        }

        // Pull one-time plaintext codes from session (set by completeTwoFactorEnrollment or regenerateRecoveryCodes)
        $codes = $_SESSION['my_account_new_recovery_codes'] ?? [];
        unset($_SESSION['my_account_new_recovery_codes']);

        // If no new codes in session but user navigated here directly, show remaining count only (no codes shown again)
        $remaining = $service->remainingRecoveryCodeCount($userId);

        $view->render('Base::account/twofa_recovery_codes.php', [
            'pageTitle' => self::tr('account.twofa.recovery_codes_title', 'Recovery Codes'),
            'codes' => is_array($codes) ? $codes : [],
            'remaining' => $remaining,
            'flash' => self::pullFlash('ok'),
            'error' => self::pullFlash('err'),
        ]);
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function regenerateRecoveryCodes(array $input): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl('/account/2fa/manage');
            header('Location: /login');
            exit;
        }

        try {
            Auth::requireCsrf((string)($input['csrf'] ?? ''), '/account/2fa/manage');
            $userId = (int)(Auth::user()['id'] ?? 0);
            if ($userId <= 0) {
                throw new \RuntimeException('Could not resolve the current user.');
            }

            $service = new MyAccountService();
            $account = $service->accountForUser($userId);
            if (empty($account['twofa_enabled'])) {
                throw new \RuntimeException('2FA is not enabled. Enable 2FA before managing recovery codes.');
            }

            // Requires current TOTP code or existing recovery code to authorize
            $authCode = trim((string)($input['code'] ?? ''));
            if ($authCode === '') {
                throw new \RuntimeException('Enter your current authenticator code to regenerate recovery codes.');
            }

            $row = \App\Core\DB::fetchOne(
                'SELECT COALESCE(twofa_secret, "") AS twofa_secret FROM users WHERE id = ? LIMIT 1',
                [$userId]
            );
            if (!is_array($row) || trim((string)$row['twofa_secret']) === '') {
                throw new \RuntimeException('2FA secret not found.');
            }

            if (!$service->verifyTotpOrRecoveryCode($userId, (string)$row['twofa_secret'], $authCode)) {
                throw new \RuntimeException('Invalid authenticator code or recovery code.');
            }

            $codes = $service->generateRecoveryCodes($userId);
            $_SESSION['my_account_new_recovery_codes'] = $codes;

            header('Location: /account/2fa/recovery-codes');
            exit;
        } catch (\Throwable $e) {
            self::flash('err', $e->getMessage());
            header('Location: /account/2fa/manage');
            exit;
        }
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function completeSetup(array $input): void
    {
        Auth::bootSession();

        $token = trim((string)($input['token'] ?? ''));
        try {
            Auth::requireCsrf((string)($input['csrf'] ?? ''), '/account/setup?token=' . urlencode($token));
            $service = new InviteLifecycleService();
            $service->completeSetup($token, $input);
            $_SESSION['auth_notice'] = 'Account setup complete. You can sign in now.';
            header('Location: /login');
            exit;
        } catch (\Throwable $e) {
            $_SESSION['account_setup_error'] = $e->getMessage();
            header('Location: /account/setup?token=' . urlencode($token));
            exit;
        }
    }

    private static function flash(string $key, string $message): void
    {
        $_SESSION['my_account_flash_' . $key] = $message;
    }

    private static function pullFlash(string $key): string
    {
        $sessionKey = 'my_account_flash_' . $key;
        $message = (string)($_SESSION[$sessionKey] ?? '');
        unset($_SESSION[$sessionKey]);
        return $message;
    }

    private static function tr(string $key, string $fallback): string
    {
        if (!function_exists('t')) {
            return $fallback;
        }

        $translated = (string)t($key);
        if ($translated === '' || $translated === $key) {
            return $fallback;
        }

        return $translated;
    }
}

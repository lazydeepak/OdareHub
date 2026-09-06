<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\TOTP;

final class MyAccountService
{
    public function __construct(
        private ?PasswordPolicyService $passwordPolicy = null,
        private ?MailService $mailService = null,
    ) {
        $this->passwordPolicy = $this->passwordPolicy ?? new PasswordPolicyService();
        $this->mailService = $this->mailService ?? new MailService();
    }

    public function ensureSchema(): void
    {
        PasswordResetService::ensureSchema();
        IdentitySecurityLogService::ensureSchema();
        InviteLifecycleService::ensureSchema();
    }

    /**
     * @return array<string,mixed>
     */
    public function accountForUser(int $userId): array
    {
        $this->ensureSchema();

        $row = DB::fetchOne(
            "SELECT id,
                    email,
                    COALESCE(display_name, '') AS display_name,
                    COALESCE(username, '') AS username,
                    COALESCE(department, '') AS department,
                    COALESCE(role, '') AS role,
                    COALESCE(authority_role, '') AS authority_role,
                    COALESCE(account_status, 'active') AS account_status,
                    COALESCE(verification_status, 'ready') AS verification_status,
                    COALESCE(security_status, 'standard') AS security_status,
                    COALESCE(twofa_enabled, 0) AS twofa_enabled,
                    COALESCE(auth_session_version, 1) AS auth_session_version,
                    COALESCE(DATE_FORMAT(password_changed_at, '%Y-%m-%d %H:%i:%s'), '') AS password_changed_at,
                    COALESCE(DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s'), '') AS created_at,
                    COALESCE(DATE_FORMAT(updated_at, '%Y-%m-%d %H:%i:%s'), '') AS updated_at
             FROM users
             WHERE id = ?
             LIMIT 1",
            [$userId]
        );

        if (!is_array($row)) {
            throw new \RuntimeException('Account not found.');
        }

        $verification = $this->verificationStatus($row);
        $security = $this->securityStatus($row);

        $row['display_label'] = trim((string)($row['display_name'] ?? '')) !== ''
            ? (string)$row['display_name']
            : (string)($row['email'] ?? 'User');
        $row['account_status_label'] = $this->accountStatusLabel((string)($row['account_status'] ?? 'active'));
        $row['verification_status_key'] = $verification['key'];
        $row['verification_status_label'] = $verification['label'];
        $row['verification_status_note'] = $verification['note'];
        $row['security_status_key'] = $security['key'];
        $row['security_status_label'] = $security['label'];
        $row['security_status_note'] = $security['note'];
        $row['password_status_label'] = trim((string)($row['password_changed_at'] ?? '')) !== ''
            ? self::tr('identity.password_status.updated', 'Updated')
            : self::tr('identity.password_status.none', 'No password change recorded yet');
        $row['password_status_note'] = trim((string)($row['password_changed_at'] ?? '')) !== ''
            ? self::tr('identity.password_status.updated_note', 'Last changed at {at}', ['at' => (string)$row['password_changed_at']])
            : self::tr('identity.password_status.none_note', 'Your password has not recorded a change timestamp yet.');
        $row['twofa_status_label'] = !empty($row['twofa_enabled'])
            ? self::tr('identity.twofa.short_enabled', 'Enabled')
            : self::tr('identity.twofa.short_disabled', 'Not enabled');
        $row['twofa_status_note'] = !empty($row['twofa_enabled'])
            ? self::tr('identity.twofa.note_enabled', 'Two-factor authentication is active for your sign-in.')
            : self::tr('identity.twofa.note_disabled', 'Two-factor authentication is not enabled yet. You can enable it from this page.');
        $row['twofa_action_label'] = !empty($row['twofa_enabled'])
            ? self::tr('account.action.manage_2fa', 'Manage 2FA')
            : self::tr('account.action.enable_2fa', 'Enable 2FA');
        $row['twofa_action_note'] = !empty($row['twofa_enabled'])
            ? self::tr('account.security.twofa_action_enabled_note', '2FA is active for this account.')
            : self::tr('account.security.twofa_action_disabled_note', 'Enable 2FA to add an extra sign-in verification step.');
        $row['twofa_action_available'] = true;
        $row['twofa_action_url'] = !empty($row['twofa_enabled']) ? '/account/2fa/manage' : '/account/2fa/setup';

        $inviteService = new InviteLifecycleService();
        $row['passkey_count'] = $inviteService->passkeyCountForUser($userId);
        $row['passkey_status_label'] = (int)$row['passkey_count'] > 0
            ? self::tr('identity.passkey.label_registered', 'Registered')
            : self::tr('identity.passkey.label_none', 'No passkeys registered');
        $row['passkey_status_note'] = (int)$row['passkey_count'] > 0
            ? self::tr('identity.passkey.note_registered', '{count} passkey(s) are registered for this account.', ['count' => (string)((int)$row['passkey_count'])])
            : self::tr('identity.passkey.note_none', 'Passkeys are not registered yet. This section is ready for future biometric sign-in support.');
        $row['passkey_action_label'] = (int)$row['passkey_count'] > 0
            ? self::tr('account.action.manage_passkeys', 'Manage Passkeys')
            : self::tr('account.action.add_passkey', 'Add Passkey');
        $row['passkey_action_available'] = true;

        $sessionId = session_id();
        $userAgent = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $recentTwoFactorRaw = !empty($_SESSION['last_2fa_at']) ? date('Y-m-d H:i:s', (int)$_SESSION['last_2fa_at']) : '';
        $row['session_summary'] = [
            'session_id' => $sessionId,
            'session_reference' => $this->sessionReference($sessionId),
            'ip' => trim((string)($_SERVER['REMOTE_ADDR'] ?? '')),
            'user_agent' => $userAgent,
            'device_summary' => $this->summarizeUserAgent($userAgent),
            'recent_2fa_at' => $recentTwoFactorRaw,
            'recent_2fa_label' => $recentTwoFactorRaw !== ''
                ? $recentTwoFactorRaw
                : self::tr('account.session.not_recorded', 'Not recorded in this session'),
        ];

        return $row;
    }

    /**
     * @param array<string,mixed> $input
     */
    public function updateProfile(int $userId, array $input): array
    {
        $this->ensureSchema();

        $displayName = trim((string)($input['display_name'] ?? ''));
        $username = trim((string)($input['username'] ?? ''));
        $department = trim((string)($input['department'] ?? ''));
        $length = static fn(string $value): int => function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);

        if ($displayName === '') {
            throw new \RuntimeException('Display name is required.');
        }
        if ($length($displayName) > 120) {
            throw new \RuntimeException('Display name is too long.');
        }
        if ($username !== '' && $length($username) > 80) {
            throw new \RuntimeException('Username is too long.');
        }
        if ($department !== '' && $length($department) > 120) {
            throw new \RuntimeException('Department is too long.');
        }

        DB::query(
            'UPDATE users
             SET display_name = ?, username = ?, department = ?
             WHERE id = ?
             LIMIT 1',
            [$displayName, $username, $department, $userId]
        );

        return $this->accountForUser($userId);
    }

    /**
     * @param array<string,mixed> $input
     * @return array{account:array<string,mixed>,policy:array<string,mixed>,strength:array<string,mixed>}
     */
    public function changePassword(int $userId, array $input): array
    {
        $this->ensureSchema();

        $currentPassword = (string)($input['current_password'] ?? '');
        $password = (string)($input['password'] ?? '');
        $confirm = (string)($input['password_confirm'] ?? '');

        if ($currentPassword === '') {
            throw new \RuntimeException('Current password is required.');
        }

        $row = DB::fetchOne(
            'SELECT id, email, password_hash, auth_session_version
             FROM users
             WHERE id = ?
             LIMIT 1',
            [$userId]
        );
        if (!is_array($row)) {
            throw new \RuntimeException('Account not found.');
        }

        if (!password_verify($currentPassword, (string)($row['password_hash'] ?? ''))) {
            IdentitySecurityLogService::log(
                'password_change_self_service',
                $userId,
                (string)($row['email'] ?? ''),
                trim((string)($_SERVER['REMOTE_ADDR'] ?? '')),
                'invalid_current_password'
            );
            throw new \RuntimeException('Current password is incorrect.');
        }

        $validation = $this->passwordPolicy->validate($password, $confirm);
        if (empty($validation['ok'])) {
            throw new \RuntimeException((string)($validation['errors'][0] ?? 'Password does not meet the policy.'));
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($hash === false) {
            throw new \RuntimeException('Could not secure the new password.');
        }

        DB::query(
            'UPDATE users
             SET password_hash = ?, auth_session_version = COALESCE(auth_session_version, 1) + 1, password_changed_at = NOW()
             WHERE id = ?
             LIMIT 1',
            [$hash, $userId]
        );

        $versionRow = DB::fetchOne('SELECT auth_session_version FROM users WHERE id = ? LIMIT 1', [$userId]);
        $nextVersion = (int)($versionRow['auth_session_version'] ?? ((int)($row['auth_session_version'] ?? 1) + 1));
        $_SESSION['auth_session_version'] = $nextVersion;

        IdentitySecurityLogService::log(
            'password_change_self_service',
            $userId,
            (string)($row['email'] ?? ''),
            trim((string)($_SERVER['REMOTE_ADDR'] ?? '')),
            'success'
        );

        try {
            $this->mailService->send((string)($row['email'] ?? ''), '', 'password_changed', [
                'app_name' => app_display_name(),
                'changed_at' => date('Y-m-d H:i:s'),
            ]);
            IdentitySecurityLogService::log(
                'password_changed_notice',
                $userId,
                (string)($row['email'] ?? ''),
                trim((string)($_SERVER['REMOTE_ADDR'] ?? '')),
                'sent'
            );
        } catch (\Throwable $e) {
            IdentitySecurityLogService::log(
                'password_changed_notice',
                $userId,
                (string)($row['email'] ?? ''),
                trim((string)($_SERVER['REMOTE_ADDR'] ?? '')),
                'failed',
                ['reason' => $e->getMessage()]
            );
            error_log('Self-service password change notification failed for user #' . $userId . ': ' . $e->getMessage());
        }

        return [
            'account' => $this->accountForUser($userId),
            'policy' => $this->passwordPolicy->describePolicy(),
            'strength' => (array)($validation['strength'] ?? []),
        ];
    }

    /**
     * @return array{secret:string,uri:string,email:string}
     */
    public function beginTwoFactorEnrollment(int $userId, bool $allowReplacement = false): array
    {
        $this->ensureSchema();

        $row = DB::fetchOne(
            'SELECT id, email, COALESCE(twofa_enabled, 0) AS twofa_enabled
             FROM users
             WHERE id = ?
             LIMIT 1',
            [$userId]
        );
        if (!is_array($row)) {
            throw new \RuntimeException('Account not found.');
        }
        if (!empty($row['twofa_enabled']) && !$allowReplacement) {
            throw new \RuntimeException('2FA is already enabled for this account.');
        }

        $secret = TOTP::randomSecret();
        $email = (string)($row['email'] ?? '');
        $uri = TOTP::provisioningUri(TOTP::defaultIssuer(), $email, $secret);

        return [
            'secret' => $secret,
            'uri' => $uri,
            'email' => $email,
        ];
    }

    public function completeTwoFactorEnrollment(int $userId, string $secret, string $code): void
    {
        $this->ensureSchema();

        $normalizedSecret = strtoupper(trim($secret));
        if ($normalizedSecret === '' || !preg_match('/^[A-Z2-7]+$/', $normalizedSecret)) {
            throw new \RuntimeException('2FA setup key is invalid. Start setup again.');
        }
        if (!TOTP::verify($normalizedSecret, $code)) {
            throw new \RuntimeException('Invalid verification code.');
        }

        $row = DB::fetchOne('SELECT id, email FROM users WHERE id = ? LIMIT 1', [$userId]);
        if (!is_array($row)) {
            throw new \RuntimeException('Account not found.');
        }

        DB::query(
            "UPDATE users
             SET twofa_enabled = 1,
                 twofa_secret = ?,
                 security_status = CASE
                     WHEN COALESCE(security_status, 'standard') = 'password_setup_pending' THEN security_status
                     ELSE '2fa_enabled'
                 END
             WHERE id = ?
             LIMIT 1",
            [$normalizedSecret, $userId]
        );

        $recoveryCodes = $this->generateRecoveryCodes($userId);
        $_SESSION['my_account_new_recovery_codes'] = $recoveryCodes;

        IdentitySecurityLogService::log(
            'my_account_twofa_enabled',
            $userId,
            (string)($row['email'] ?? ''),
            trim((string)($_SERVER['REMOTE_ADDR'] ?? '')),
            'success'
        );
    }

    public function authorizeTwoFactorRegeneration(int $userId, string $code): void
    {
        $this->ensureSchema();

        $row = DB::fetchOne(
            'SELECT id, email, COALESCE(twofa_enabled, 0) AS twofa_enabled, COALESCE(twofa_secret, "") AS twofa_secret
             FROM users
             WHERE id = ?
             LIMIT 1',
            [$userId]
        );
        if (!is_array($row)) {
            throw new \RuntimeException('Account not found.');
        }
        if (empty($row['twofa_enabled']) || trim((string)($row['twofa_secret'] ?? '')) === '') {
            throw new \RuntimeException('2FA is not enabled for this account.');
        }

        if (!$this->verifyTotpOrRecoveryCode($userId, (string)$row['twofa_secret'], $code)) {
            throw new \RuntimeException('Invalid verification code or recovery code.');
        }

        IdentitySecurityLogService::log(
            'my_account_twofa_regenerate_authorized',
            $userId,
            (string)($row['email'] ?? ''),
            trim((string)($_SERVER['REMOTE_ADDR'] ?? '')),
            'success'
        );
    }

    public function disableTwoFactor(int $userId, string $code): void
    {
        $this->ensureSchema();

        $row = DB::fetchOne(
            'SELECT id, email, COALESCE(twofa_enabled, 0) AS twofa_enabled, COALESCE(twofa_secret, "") AS twofa_secret
             FROM users
             WHERE id = ?
             LIMIT 1',
            [$userId]
        );
        if (!is_array($row)) {
            throw new \RuntimeException('Account not found.');
        }
        if (empty($row['twofa_enabled']) || trim((string)($row['twofa_secret'] ?? '')) === '') {
            throw new \RuntimeException('2FA is already disabled.');
        }

        if (!$this->verifyTotpOrRecoveryCode($userId, (string)$row['twofa_secret'], $code)) {
            throw new \RuntimeException('Invalid verification code or recovery code.');
        }

        DB::query(
            "UPDATE users
             SET twofa_enabled = 0,
                 twofa_secret = NULL,
                 security_status = CASE
                     WHEN COALESCE(security_status, 'standard') = 'password_setup_pending' THEN security_status
                     ELSE 'standard'
                 END
             WHERE id = ?
             LIMIT 1",
            [$userId]
        );

        $this->deleteRecoveryCodes($userId);

        IdentitySecurityLogService::log(
            'my_account_twofa_disabled',
            $userId,
            (string)($row['email'] ?? ''),
            trim((string)($_SERVER['REMOTE_ADDR'] ?? '')),
            'success'
        );
    }

    // -------------------------------------------------------
    // Recovery Codes
    // -------------------------------------------------------

    private function ensureRecoveryCodesSchema(): void
    {
        DB::query(
            "CREATE TABLE IF NOT EXISTS user_2fa_recovery_codes (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                code_hash CHAR(64) NOT NULL,
                used_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_recovery_hash (code_hash),
                KEY idx_recovery_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            []
        );
    }

    /**
     * Generate 10 one-time recovery codes for the user.
     * Invalidates any existing codes and stores new hashed codes.
     * Returns the plaintext codes formatted as XXXXX-XXXXX.
     *
     * @return list<string>
     */
    public function generateRecoveryCodes(int $userId): array
    {
        $this->ensureRecoveryCodesSchema();

        DB::query('DELETE FROM user_2fa_recovery_codes WHERE user_id = ?', [$userId]);

        $plainCodes = [];
        for ($i = 0; $i < 10; $i++) {
            $raw = strtoupper(bin2hex(random_bytes(5)));  // 10 uppercase hex chars
            $formatted = substr($raw, 0, 5) . '-' . substr($raw, 5, 5);
            $hash = hash('sha256', $raw);
            DB::query(
                'INSERT INTO user_2fa_recovery_codes (user_id, code_hash) VALUES (?, ?)',
                [$userId, $hash]
            );
            $plainCodes[] = $formatted;
        }

        IdentitySecurityLogService::log(
            'my_account_recovery_codes_generated',
            $userId,
            '',
            trim((string)($_SERVER['REMOTE_ADDR'] ?? '')),
            'success'
        );

        return $plainCodes;
    }

    /**
     * Consume a single recovery code (mark used). Throws if invalid or already used.
     * Uses an atomic conditional UPDATE to prevent TOCTOU race conditions.
     */
    public function consumeRecoveryCode(int $userId, string $rawCode): void
    {
        $this->ensureRecoveryCodesSchema();

        $normalized = strtoupper(str_replace(['-', ' '], '', trim($rawCode)));
        if (strlen($normalized) !== 10 || !ctype_xdigit($normalized)) {
            throw new \RuntimeException('Invalid recovery code format.');
        }

        $hash = hash('sha256', $normalized);

        // Atomic: only updates if the code exists AND has not been used yet.
        // This eliminates a TOCTOU race where two simultaneous requests both
        // read used_at=NULL and both proceed to update.
        DB::query(
            'UPDATE user_2fa_recovery_codes SET used_at = NOW() WHERE code_hash = ? AND user_id = ? AND used_at IS NULL LIMIT 1',
            [$hash, $userId]
        );

        $affected = DB::conn()->affected_rows;

        if ($affected < 1) {
            // Distinguish "already used" from "not found" to give the right error message.
            $exists = DB::fetchOne(
                'SELECT id FROM user_2fa_recovery_codes WHERE code_hash = ? AND user_id = ? LIMIT 1',
                [$hash, $userId]
            );
            if ($exists !== null) {
                throw new \RuntimeException('This recovery code has already been used.');
            }
            throw new \RuntimeException('Invalid recovery code.');
        }

        IdentitySecurityLogService::log(
            'my_account_recovery_code_used',
            $userId,
            '',
            trim((string)($_SERVER['REMOTE_ADDR'] ?? '')),
            'success'
        );
    }

    public function remainingRecoveryCodeCount(int $userId): int
    {
        $this->ensureRecoveryCodesSchema();
        $row = DB::fetchOne(
            'SELECT COUNT(*) AS cnt FROM user_2fa_recovery_codes WHERE user_id = ? AND used_at IS NULL',
            [$userId]
        );
        return (int)($row['cnt'] ?? 0);
    }

    private function deleteRecoveryCodes(int $userId): void
    {
        $this->ensureRecoveryCodesSchema();
        DB::query('DELETE FROM user_2fa_recovery_codes WHERE user_id = ?', [$userId]);
    }

    /**
     * Try TOTP first; if the code looks like a recovery code, fall back to that.
     * Returns true if authentication succeeded.
     */
    public function verifyTotpOrRecoveryCode(int $userId, string $totpSecret, string $code): bool
    {
        $normalized = strtoupper(str_replace(['-', ' '], '', trim($code)));

        // Recovery code: exactly 10 hex chars (possibly formatted as XXXXX-XXXXX)
        if (strlen($normalized) === 10 && ctype_xdigit($normalized)) {
            try {
                $this->consumeRecoveryCode($userId, $normalized);
                return true;
            } catch (\Throwable $e) {
                return false;
            }
        }

        // TOTP: 6 digits
        return TOTP::verify($totpSecret, $code);
    }

    /**
     * @return array<string,mixed>
     */
    public function passwordPolicy(): array
    {
        return $this->passwordPolicy->describePolicy();
    }

    /**
     * @param array<string,mixed> $row
     * @return array{key:string,label:string,note:string}
     */
    private function verificationStatus(array $row): array
    {
        $status = strtolower(trim((string)($row['account_status'] ?? 'active')));
        $verificationStatus = strtolower(trim((string)($row['verification_status'] ?? 'ready')));
        if ($verificationStatus === 'invite_pending') {
            return [
                'key' => 'invite_pending',
                'label' => self::tr('identity.verification.invite_pending.label', 'Invite Pending'),
                'note' => self::tr('identity.verification.invite_pending.note', 'A setup link has been issued and is waiting to be completed.'),
            ];
        }
        if ($verificationStatus === 'setup_pending' || $status === 'pending') {
            return [
                'key' => 'setup_pending',
                'label' => self::tr('identity.verification.setup_pending.label', 'Setup Pending'),
                'note' => self::tr('identity.verification.setup_pending.note', 'Your account is still in a pending setup state.'),
            ];
        }
        if ($status === 'disabled') {
            return [
                'key' => 'review_needed',
                'label' => self::tr('identity.verification.review_needed.label', 'Review Needed'),
                'note' => self::tr('identity.verification.review_needed.note', 'Your account is disabled and requires administrator review.'),
            ];
        }

        return [
            'key' => 'ready',
            'label' => self::tr('identity.verification.ready.label', 'Ready'),
            'note' => self::tr('identity.verification.ready.note', 'Your account is ready to use.'),
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array{key:string,label:string,note:string}
     */
    private function securityStatus(array $row): array
    {
        $status = strtolower(trim((string)($row['account_status'] ?? 'active')));
        $securityStatus = strtolower(trim((string)($row['security_status'] ?? 'standard')));
        if ($status === 'disabled') {
            return [
                'key' => 'suspended',
                'label' => self::tr('identity.security.suspended.label', 'Suspended'),
                'note' => self::tr('identity.security.suspended.note', 'Your account is disabled, so security-sensitive actions are limited.'),
            ];
        }
        if (!empty($row['twofa_enabled'])) {
            return [
                'key' => 'twofa_enabled',
                'label' => self::tr('identity.security.twofa_enabled.label', '2FA Enabled'),
                'note' => self::tr('identity.security.twofa_enabled.note', 'Two-factor authentication is active for your account.'),
            ];
        }
        if ($securityStatus === 'password_setup_pending') {
            return [
                'key' => 'password_setup_pending',
                'label' => self::tr('identity.security.password_setup_pending.label', 'Password Setup Pending'),
                'note' => self::tr('identity.security.password_setup_pending.note', 'Your password setup must be completed before the account reaches its standard security state.'),
            ];
        }
        if ($securityStatus === 'security_attention') {
            return [
                'key' => 'security_attention',
                'label' => self::tr('identity.security.security_attention.label', 'Security Attention Needed'),
                'note' => self::tr('identity.security.security_attention.note', 'There is a security event that needs attention before the account is fully settled.'),
            ];
        }

        return [
            'key' => 'standard',
            'label' => self::tr('identity.security.standard.label', 'Standard'),
            'note' => self::tr('identity.security.standard.note', 'Password-based sign-in is active. You can strengthen this later with 2FA.'),
        ];
    }

    private function accountStatusLabel(string $status): string
    {
        return match (strtolower(trim($status))) {
            'pending' => self::tr('identity.account_status.pending', 'Pending'),
            'disabled' => self::tr('identity.account_status.disabled', 'Disabled'),
            default => self::tr('identity.account_status.active', 'Active'),
        };
    }

    private function sessionReference(string $sessionId): string
    {
        $normalized = trim($sessionId);
        if ($normalized === '') {
            return '';
        }

        $length = strlen($normalized);
        if ($length <= 10) {
            return $normalized;
        }

        return substr($normalized, 0, 6) . '...' . substr($normalized, -4);
    }

    private function summarizeUserAgent(string $userAgent): string
    {
        $ua = trim($userAgent);
        if ($ua === '') {
            return self::tr('account.session.device_unknown', 'Unknown device');
        }

        $browser = 'Browser';
        if (stripos($ua, 'edg/') !== false) {
            $browser = 'Edge';
        } elseif (stripos($ua, 'chrome/') !== false && stripos($ua, 'edg/') === false) {
            $browser = 'Chrome';
        } elseif (stripos($ua, 'safari/') !== false && stripos($ua, 'chrome/') === false) {
            $browser = 'Safari';
        } elseif (stripos($ua, 'firefox/') !== false) {
            $browser = 'Firefox';
        }

        $platform = 'Desktop';
        if (stripos($ua, 'android') !== false) {
            $platform = 'Android';
        } elseif (stripos($ua, 'iphone') !== false || stripos($ua, 'ipad') !== false || stripos($ua, 'ios') !== false) {
            $platform = 'iOS';
        } elseif (stripos($ua, 'mac os x') !== false || stripos($ua, 'macintosh') !== false) {
            $platform = 'macOS';
        } elseif (stripos($ua, 'windows') !== false) {
            $platform = 'Windows';
        } elseif (stripos($ua, 'linux') !== false) {
            $platform = 'Linux';
        }

        return $platform . ' / ' . $browser;
    }

    /**
     * @param array<string,string> $replacements
     */
    private static function tr(string $key, string $fallback, array $replacements = []): string
    {
        if (!function_exists('t')) {
            return $fallback;
        }

        $translated = (string)t($key, $replacements);
        if ($translated === '' || $translated === $key) {
            return $fallback;
        }

        return $translated;
    }
}

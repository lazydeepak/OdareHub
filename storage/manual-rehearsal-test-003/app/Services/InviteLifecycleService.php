<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

final class InviteLifecycleService
{
    private const TOKEN_TTL_SECONDS = 172800; // 48 hours

    public function __construct(
        private ?MailService $mailService = null,
        private ?PasswordPolicyService $passwordPolicy = null,
    ) {
        $this->mailService = $this->mailService ?? new MailService();
        $this->passwordPolicy = $this->passwordPolicy ?? new PasswordPolicyService();
    }

    public static function ensureSchema(): void
    {
        IdentitySecurityLogService::ensureSchema();
        PasswordResetService::ensureSchema();

        DB::query(
            'CREATE TABLE IF NOT EXISTS user_account_setup_tokens (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                email VARCHAR(190) NOT NULL,
                token_hash CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_account_setup_token_hash (token_hash),
                KEY idx_account_setup_user (user_id, created_at),
                KEY idx_account_setup_expires (expires_at),
                CONSTRAINT fk_account_setup_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        DB::query(
            'CREATE TABLE IF NOT EXISTS user_passkeys (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                credential_id VARCHAR(255) NOT NULL,
                public_key LONGTEXT NOT NULL,
                device_name VARCHAR(190) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_used_at DATETIME NULL,
                UNIQUE KEY uniq_user_passkey_credential (credential_id),
                KEY idx_user_passkeys_user (user_id, created_at),
                CONSTRAINT fk_user_passkeys_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /**
     * @return array{setup_url:string,expires_at:string,token_sent:bool}
     */
    public function issueSetupLinkForUser(int $userId, bool $sendEmail = true): array
    {
        self::ensureSchema();

        $user = DB::fetchOne(
            'SELECT id, email, display_name, account_status, verification_status, security_status
             FROM users
             WHERE id = ?
             LIMIT 1',
            [$userId]
        );
        if (!is_array($user)) {
            throw new \RuntimeException('User not found.');
        }

        $status = strtolower(trim((string)($user['account_status'] ?? 'active')));
        if ($status === 'disabled') {
            throw new \RuntimeException('Cannot send a setup link for a disabled user.');
        }

        $verificationStatus = strtolower(trim((string)($user['verification_status'] ?? 'ready')));
        $securityStatus = strtolower(trim((string)($user['security_status'] ?? 'standard')));
        $hasCompletedSetup = $verificationStatus === 'ready'
            && !in_array($securityStatus, ['password_setup_pending'], true);
        if ($hasCompletedSetup) {
            throw new \RuntimeException('This user has already completed account setup. Use password reset if they need access help.');
        }

        DB::query('UPDATE user_account_setup_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$userId]);

        $token = $this->generateToken();
        $tokenHash = $this->hashToken($token);
        $expiresAt = date('Y-m-d H:i:s', time() + self::TOKEN_TTL_SECONDS);

        DB::query(
            'INSERT INTO user_account_setup_tokens (user_id, email, token_hash, expires_at, created_at)
             VALUES (?,?,?,?,?)',
            [
                $userId,
                (string)($user['email'] ?? ''),
                $tokenHash,
                $expiresAt,
                date('Y-m-d H:i:s'),
            ]
        );

        DB::query(
            "UPDATE users
             SET verification_status = 'invite_pending',
                 security_status = 'password_setup_pending',
                 account_status = CASE
                     WHEN LOWER(COALESCE(account_status, 'active')) = 'disabled' THEN account_status
                     ELSE 'pending'
                 END
             WHERE id = ?
             LIMIT 1",
            [$userId]
        );

        $setupUrl = $this->publicUrl('/account/setup?token=' . rawurlencode($token));

        IdentitySecurityLogService::log(
            'account_setup_invite',
            $userId,
            (string)($user['email'] ?? ''),
            trim((string)($_SERVER['REMOTE_ADDR'] ?? '')),
            $sendEmail ? 'issued' : 'created'
        );

        if ($sendEmail) {
            try {
                $this->mailService->send((string)($user['email'] ?? ''), '', 'account_invite', [
                    'app_name' => app_display_name(),
                    'display_name' => trim((string)($user['display_name'] ?? '')),
                    'setup_url' => $setupUrl,
                    'expires_in_hours' => (int)(self::TOKEN_TTL_SECONDS / 3600),
                ]);
                IdentitySecurityLogService::log(
                    'account_setup_invite_email',
                    $userId,
                    (string)($user['email'] ?? ''),
                    trim((string)($_SERVER['REMOTE_ADDR'] ?? '')),
                    'sent'
                );
            } catch (\Throwable $e) {
                IdentitySecurityLogService::log(
                    'account_setup_invite_email',
                    $userId,
                    (string)($user['email'] ?? ''),
                    trim((string)($_SERVER['REMOTE_ADDR'] ?? '')),
                    'failed',
                    ['reason' => $e->getMessage()]
                );
                throw $e;
            }
        }

        return [
            'setup_url' => $setupUrl,
            'expires_at' => $expiresAt,
            'token_sent' => $sendEmail,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function validateSetupToken(string $token): ?array
    {
        self::ensureSchema();
        $hash = $this->hashToken($token);
        if ($hash === '') {
            return null;
        }

        $row = DB::fetchOne(
            'SELECT t.id,
                    t.user_id,
                    t.email,
                    t.expires_at,
                    t.used_at,
                    COALESCE(u.display_name, "") AS display_name,
                    COALESCE(u.account_status, "active") AS account_status,
                    COALESCE(u.verification_status, "setup_pending") AS verification_status,
                    COALESCE(u.security_status, "password_setup_pending") AS security_status
             FROM user_account_setup_tokens t
             INNER JOIN users u ON u.id = t.user_id
             WHERE t.token_hash = ?
             LIMIT 1',
            [$hash]
        );

        if (!is_array($row)) {
            return null;
        }
        if (trim((string)($row['used_at'] ?? '')) !== '') {
            return null;
        }
        if (strtotime((string)($row['expires_at'] ?? '')) < time()) {
            return null;
        }
        if (strtolower(trim((string)($row['account_status'] ?? 'active'))) === 'disabled') {
            return null;
        }

        return $row;
    }

    /**
     * @return array<string,mixed>
     */
    public function completeSetup(string $token, array $input): array
    {
        self::ensureSchema();

        $record = $this->validateSetupToken($token);
        if (!is_array($record)) {
            IdentitySecurityLogService::log('account_setup_completed', null, '', trim((string)($_SERVER['REMOTE_ADDR'] ?? '')), 'invalid_token');
            throw new \RuntimeException('That setup link is invalid or has expired.');
        }

        $displayName = trim((string)($input['display_name'] ?? ''));
        if ($displayName === '') {
            $displayName = trim((string)($record['display_name'] ?? ''));
        }
        if ($displayName === '') {
            throw new \RuntimeException('Display name is required.');
        }

        $password = (string)($input['password'] ?? '');
        $confirm = (string)($input['password_confirm'] ?? '');
        $this->passwordPolicy->assertValid($password, $confirm);

        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($hash === false) {
            throw new \RuntimeException('Could not secure the new password.');
        }

        $userId = (int)($record['user_id'] ?? 0);
        DB::query('UPDATE user_account_setup_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$userId]);
        DB::query(
            "UPDATE users
             SET display_name = ?,
                 password_hash = ?,
                 password_changed_at = NOW(),
                 auth_session_version = COALESCE(auth_session_version, 1) + 1,
                 verification_status = 'ready',
                 security_status = CASE
                     WHEN COALESCE(twofa_enabled, 0) = 1 THEN '2fa_enabled'
                     ELSE 'standard'
                 END,
                 account_status = CASE
                     WHEN LOWER(COALESCE(account_status, 'active')) = 'disabled' THEN account_status
                     ELSE 'active'
                 END
             WHERE id = ?
             LIMIT 1",
            [$displayName, $hash, $userId]
        );

        IdentitySecurityLogService::log(
            'account_setup_completed',
            $userId,
            (string)($record['email'] ?? ''),
            trim((string)($_SERVER['REMOTE_ADDR'] ?? '')),
            'success'
        );

        try {
            $this->mailService->send((string)($record['email'] ?? ''), '', 'password_changed', [
                'app_name' => app_display_name(),
                'changed_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            error_log('Account setup completion notice failed for user #' . $userId . ': ' . $e->getMessage());
        }

        return DB::fetchOne(
            'SELECT id, email, display_name, verification_status, security_status, account_status
             FROM users
             WHERE id = ?
             LIMIT 1',
            [$userId]
        ) ?? [];
    }

    public function passkeyCountForUser(int $userId): int
    {
        self::ensureSchema();
        if ($userId <= 0) {
            return 0;
        }

        $row = DB::fetchOne('SELECT COUNT(*) AS c FROM user_passkeys WHERE user_id = ?', [$userId]);
        return (int)($row['c'] ?? 0);
    }

    private function generateToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function hashToken(string $token): string
    {
        $trimmed = trim($token);
        return $trimmed === '' ? '' : hash('sha256', $trimmed);
    }

    private function publicUrl(string $path): string
    {
        $base = rtrim(core_setting('app.url', ''), '/');
        if ($base !== '') {
            return $base . $path;
        }

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443)
            || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
        $scheme = $https ? 'https' : 'http';
        $host = trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));

        return $scheme . '://' . $host . $path;
    }
}

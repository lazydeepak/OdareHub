<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

final class PasswordResetService
{
    private const TOKEN_TTL_SECONDS = 3600;
    private const REQUEST_WINDOW_SECONDS = 900;
    private const MAX_REQUESTS_PER_EMAIL = 3;
    private const MAX_REQUESTS_PER_IP = 8;

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

        DB::query(
            'CREATE TABLE IF NOT EXISTS user_password_reset_tokens (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                email VARCHAR(190) NOT NULL,
                token_hash CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                request_ip_hash CHAR(64) NULL,
                request_user_agent VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_password_reset_token_hash (token_hash),
                KEY idx_password_reset_user (user_id, created_at),
                KEY idx_password_reset_expires (expires_at),
                CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $exists = DB::fetchOne("SHOW COLUMNS FROM users LIKE 'auth_session_version'");
        if (!$exists) {
            DB::query("ALTER TABLE users ADD COLUMN auth_session_version INT NOT NULL DEFAULT 1 AFTER password_hash");
        }

        $exists = DB::fetchOne("SHOW COLUMNS FROM users LIKE 'password_changed_at'");
        if (!$exists) {
            DB::query("ALTER TABLE users ADD COLUMN password_changed_at DATETIME NULL AFTER auth_session_version");
        }
    }

    public function requestReset(string $email, string $ip, string $userAgent): void
    {
        self::ensureSchema();

        $normalizedEmail = strtolower(trim($email));
        $rate = IdentitySecurityLogService::countRecent('password_reset_requested', $normalizedEmail, $ip, self::REQUEST_WINDOW_SECONDS);
        $throttled = $rate['email'] >= self::MAX_REQUESTS_PER_EMAIL || $rate['ip'] >= self::MAX_REQUESTS_PER_IP;

        $user = null;
        if ($normalizedEmail !== '' && filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
            $user = DB::fetchOne(
                'SELECT id, email, display_name, account_status
                 FROM users
                 WHERE email = ?
                 LIMIT 1',
                [$normalizedEmail]
            );
        }

        $userId = (int)($user['id'] ?? 0);
        if ($throttled) {
            IdentitySecurityLogService::log('password_reset_requested', $userId > 0 ? $userId : null, $normalizedEmail, $ip, 'throttled');
            return;
        }

        IdentitySecurityLogService::log('password_reset_requested', $userId > 0 ? $userId : null, $normalizedEmail, $ip, $userId > 0 ? 'accepted' : 'unknown');

        if (!$user || strtolower(trim((string)($user['account_status'] ?? 'active'))) === 'disabled') {
            return;
        }

        DB::query('UPDATE user_password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$userId]);

        $token = $this->generateToken();
        $tokenHash = $this->hashToken($token);
        $expiresAt = date('Y-m-d H:i:s', time() + self::TOKEN_TTL_SECONDS);
        DB::query(
            'INSERT INTO user_password_reset_tokens (user_id, email, token_hash, expires_at, request_ip_hash, request_user_agent, created_at)
             VALUES (?,?,?,?,?,?,?)',
            [
                $userId,
                $normalizedEmail,
                $tokenHash,
                $expiresAt,
                $this->hashIp($ip),
                substr(trim($userAgent), 0, 255) ?: null,
                date('Y-m-d H:i:s'),
            ]
        );

        try {
            $this->mailService->send($normalizedEmail, '', 'password_reset', [
                'app_name' => app_display_name(),
                'reset_url' => $this->publicUrl('/reset-password?token=' . rawurlencode($token)),
                'display_name' => trim((string)($user['display_name'] ?? '')),
                'expires_in_minutes' => (int)(self::TOKEN_TTL_SECONDS / 60),
            ]);
            IdentitySecurityLogService::log('password_reset_email', $userId, $normalizedEmail, $ip, 'sent');
        } catch (\Throwable $e) {
            IdentitySecurityLogService::log('password_reset_email', $userId, $normalizedEmail, $ip, 'failed', [
                'reason' => $e->getMessage(),
            ]);
            error_log('Password reset mail send failed for user #' . $userId . ': ' . $e->getMessage());
        }
    }

    public function validateToken(string $token): ?array
    {
        self::ensureSchema();
        $hash = $this->hashToken($token);
        if ($hash === '') {
            return null;
        }

        $row = DB::fetchOne(
            'SELECT t.id, t.user_id, t.email, t.expires_at, t.used_at, u.account_status, u.display_name
             FROM user_password_reset_tokens t
             INNER JOIN users u ON u.id = t.user_id
             WHERE t.token_hash = ?
             LIMIT 1',
            [$hash]
        );

        if (!$row) {
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

        return [
            'token_id' => (int)($row['id'] ?? 0),
            'user_id' => (int)($row['user_id'] ?? 0),
            'email' => (string)($row['email'] ?? ''),
            'display_name' => (string)($row['display_name'] ?? ''),
            'expires_at' => (string)($row['expires_at'] ?? ''),
        ];
    }

    public function completeReset(string $token, string $password, string $confirm, string $ip): array
    {
        self::ensureSchema();

        $record = $this->validateToken($token);
        if (!$record) {
            IdentitySecurityLogService::log('password_reset_completed', null, '', $ip, 'invalid_token');
            throw new \RuntimeException('That reset link is invalid or has expired.');
        }

        $this->passwordPolicy->assertValid($password, $confirm);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($hash === false) {
            throw new \RuntimeException('Could not secure the new password.');
        }

        $userId = (int)$record['user_id'];
        $email = (string)$record['email'];

        DB::query('UPDATE user_password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$userId]);
        DB::query(
            'UPDATE users
             SET password_hash = ?, auth_session_version = COALESCE(auth_session_version, 1) + 1, password_changed_at = NOW()
             WHERE id = ?
             LIMIT 1',
            [$hash, $userId]
        );

        IdentitySecurityLogService::log('password_reset_completed', $userId, $email, $ip, 'success');

        try {
            $this->mailService->send($email, '', 'password_changed', [
                'app_name' => app_display_name(),
                'changed_at' => date('Y-m-d H:i:s'),
            ]);
            IdentitySecurityLogService::log('password_changed_notice', $userId, $email, $ip, 'sent');
        } catch (\Throwable $e) {
            IdentitySecurityLogService::log('password_changed_notice', $userId, $email, $ip, 'failed', [
                'reason' => $e->getMessage(),
            ]);
            error_log('Password change notification mail failed for user #' . $userId . ': ' . $e->getMessage());
        }

        return $record;
    }

    public function policy(): array
    {
        return $this->passwordPolicy->describePolicy();
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

    private function hashIp(string $ip): ?string
    {
        $trimmed = trim($ip);
        return $trimmed === '' ? null : hash('sha256', $trimmed);
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

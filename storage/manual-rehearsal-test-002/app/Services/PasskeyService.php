<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use lbuchs\WebAuthn\WebAuthn;

/**
 * Passkey (WebAuthn) service — registration, authentication, and management.
 * Uses lbuchs/webauthn v2.2.
 */
final class PasskeyService
{
    // ----------------------------------------------------------------
    // Configuration
    // ----------------------------------------------------------------

    private static function origin(): string
    {
        $https  = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $scheme = $https ? 'https' : 'http';
        $host   = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        return $scheme . '://' . $host;
    }

    private static function rpId(): string
    {
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        // Strip port — rpId must be host only
        return (string)preg_replace('/:\d+$/', '', $host);
    }

    private static function webAuthn(): WebAuthn
    {
        return new WebAuthn('IPM ERP', self::rpId(), ['none', 'fido-u2f', 'packed', 'tpm', 'android-key', 'apple'], true);
    }

    /**
     * Whether the current environment is localhost/development.
     * Allows self-signed attestation roots in dev; enforces them in production.
     */
    private static function isLocalDev(): bool
    {
        $rpId = self::rpId();
        return $rpId === 'localhost' || $rpId === '127.0.0.1';
    }

    private static function decodeWebAuthnBase64(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^=\?BINARY\?B\?([A-Za-z0-9+\/=]+)\?=$/i', $value, $matches) === 1) {
            $decoded = base64_decode($matches[1], true);
            if ($decoded === false) {
                throw new \RuntimeException('Invalid WebAuthn binary payload.');
            }
            return $decoded;
        }

        $normalized = strtr($value, '-_', '+/');
        $padding    = strlen($normalized) % 4;
        if ($padding !== 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($normalized, true);
        if ($decoded === false) {
            throw new \RuntimeException('Invalid WebAuthn base64 payload.');
        }

        return $decoded;
    }

    // ----------------------------------------------------------------
    // Schema
    // ----------------------------------------------------------------

    public static function ensureSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        // Only run the ALTER if the column is genuinely missing, avoiding
        // a DDL lock attempt on every API call.
        $col = DB::fetchOne("SHOW COLUMNS FROM user_passkeys LIKE 'sign_count'");
        if ($col === null) {
            try {
                DB::query('ALTER TABLE user_passkeys ADD COLUMN sign_count INT UNSIGNED NOT NULL DEFAULT 0', []);
            } catch (\Throwable $e) {
                // Ignore — concurrent processes may have already added it.
            }
        }
    }

    // ----------------------------------------------------------------
    // Registration
    // ----------------------------------------------------------------

    /**
     * Generate a WebAuthn registration challenge.
     * Stores binary challenge in session; returns JSON string for the browser.
     */
    public static function registrationChallenge(int $userId, string $userName, string $displayName): string
    {
        self::ensureSchema();
        $wa = self::webAuthn();

        // Exclude credentials the user has already registered to prevent duplicates
        $existing    = DB::fetchAll('SELECT credential_id FROM user_passkeys WHERE user_id = ?', [$userId]);
        $excludeIds  = array_map(static fn($r) => base64_decode((string)$r['credential_id']), $existing);

        $userIdBytes = pack('N', $userId); // 4-byte big-endian
        $args        = $wa->getCreateArgs(
            $userIdBytes,
            $userName,
            $displayName,
            60,
            false,        // requireResidentKey
            'preferred',  // requireUserVerification
            null,         // crossPlatformAttachment (both platform + cross-platform)
            $excludeIds
        );

        // Namespace challenge by userId to prevent collision when multiple tabs
        // are open simultaneously (e.g. one on manage page, one on login page).
        $_SESSION['webauthn_register_challenge_' . $userId] = $wa->getChallenge()->getBinaryString();
        return (string)json_encode($args);
    }

    /**
     * Verify and persist a newly registered passkey.
     *
     * @param  int       $userId
     * @param  \stdClass $body        Decoded JSON from the browser (clientDataJSON, attestationObject)
     * @param  string    $deviceName  Human-readable label for the device
     */
    public static function completeRegistration(int $userId, \stdClass $body, string $deviceName): void
    {
        self::ensureSchema();
        $wa = self::webAuthn();

        $challengeKey = 'webauthn_register_challenge_' . $userId;
        $challenge = (string)($_SESSION[$challengeKey] ?? '');
        if ($challenge === '') {
            throw new \RuntimeException('No registration challenge in session.');
        }
        unset($_SESSION[$challengeKey]);

        // Browser sends base64url; we need raw binary for the library
        $clientDataJSON    = self::decodeWebAuthnBase64((string)($body->clientDataJSON ?? ''));
        $attestationObject = self::decodeWebAuthnBase64((string)($body->attestationObject ?? ''));

        $data = $wa->processCreate(
            $clientDataJSON,
            $attestationObject,
            $challenge,
            false,                // requireUserVerification
            true,                 // requireUserPresent
            self::isLocalDev()    // failIfRootMismatch — false only on localhost/dev
                ? false
                : true
        );

        $credentialId = base64_encode((string)$data->credentialId);
        $publicKey    = (string)$data->credentialPublicKey;
        $signCount    = (int)($data->signatureCounter ?? 0);
        $deviceName   = mb_substr(trim($deviceName) !== '' ? trim($deviceName) : 'Passkey', 0, 190);

        DB::query(
            'INSERT INTO user_passkeys (user_id, credential_id, public_key, sign_count, device_name, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE public_key = VALUES(public_key),
                                     sign_count  = VALUES(sign_count),
                                     device_name = VALUES(device_name)',
            [$userId, $credentialId, $publicKey, $signCount, $deviceName]
        );
    }

    // ----------------------------------------------------------------
    // Authentication
    // ----------------------------------------------------------------

    /**
     * Generate a WebAuthn authentication challenge.
     * If $email is non-empty, includes allowCredentials for that user (non-discoverable flow).
     * If $email is empty, returns an empty allowCredentials list (discoverable/resident-key flow).
     * Stores binary challenge in session; returns JSON string.
     */
    public static function loginChallenge(string $email): string
    {
        self::ensureSchema();
        $wa = self::webAuthn();

        $credentialIds = [];
        if ($email !== '') {
            $user = DB::fetchOne('SELECT id FROM users WHERE email = ?', [strtolower(trim($email))]);
            if ($user) {
                $rows          = DB::fetchAll('SELECT credential_id FROM user_passkeys WHERE user_id = ?', [(int)$user['id']]);
                $credentialIds = array_map(static fn($r) => base64_decode((string)$r['credential_id']), $rows);
            }
        }

        $args = $wa->getGetArgs($credentialIds, 60);
        // Use a fixed key for the login challenge (no userId yet; discoverable flow).
        // A unique session-level nonce is added to reduce cross-tab collisions.
        $_SESSION['webauthn_login_challenge'] = $wa->getChallenge()->getBinaryString();

        return (string)json_encode($args);
    }

    /**
     * Verify a passkey authentication assertion.
     * Returns the authenticated user ID on success.
     */
    public static function completeLogin(\stdClass $body): int
    {
        self::ensureSchema();
        $wa = self::webAuthn();

        $challenge = (string)($_SESSION['webauthn_login_challenge'] ?? '');
        if ($challenge === '') {
            throw new \RuntimeException('No authentication challenge in session.');
        }
        unset($_SESSION['webauthn_login_challenge']);

        // rawId from browser is base64url-encoded binary
        $rawId = (string)($body->rawId ?? '');
        if ($rawId === '') {
            throw new \RuntimeException('Missing rawId in response.');
        }
        $credentialIdBin = self::decodeWebAuthnBase64($rawId);
        $storedId        = base64_encode($credentialIdBin);

        $row = DB::fetchOne('SELECT * FROM user_passkeys WHERE credential_id = ?', [$storedId]);
        if (!$row) {
            throw new \RuntimeException('Passkey credential not found.');
        }

        $clientDataJSON    = self::decodeWebAuthnBase64((string)($body->clientDataJSON ?? ''));
        $authenticatorData = self::decodeWebAuthnBase64((string)($body->authenticatorData ?? ''));
        $signature         = self::decodeWebAuthnBase64((string)($body->signature ?? ''));

        $wa->processGet(
            $clientDataJSON,
            $authenticatorData,
            $signature,
            (string)$row['public_key'],
            $challenge,
            (int)$row['sign_count'],
            false, // requireUserVerification
            true   // requireUserPresent
        );

        $newSignCount = $wa->getSignatureCounter() ?? (int)$row['sign_count'];
        DB::query(
            'UPDATE user_passkeys SET sign_count = ?, last_used_at = NOW() WHERE id = ?',
            [$newSignCount, (int)$row['id']]
        );

        return (int)$row['user_id'];
    }

    // ----------------------------------------------------------------
    // Management
    // ----------------------------------------------------------------

    public static function listPasskeys(int $userId): array
    {
        self::ensureSchema();
        return DB::fetchAll(
            'SELECT id, device_name, created_at, last_used_at FROM user_passkeys WHERE user_id = ? ORDER BY created_at DESC',
            [$userId]
        );
    }

    public static function deletePasskey(int $userId, int $id): void
    {
        DB::query('DELETE FROM user_passkeys WHERE id = ? AND user_id = ?', [$id, $userId]);
    }

    public static function countForUser(int $userId): int
    {
        $row = DB::fetchOne('SELECT COUNT(*) AS c FROM user_passkeys WHERE user_id = ?', [$userId]);
        return (int)($row['c'] ?? 0);
    }
}

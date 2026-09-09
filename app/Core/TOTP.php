<?php
declare(strict_types=1);

namespace App\Core;

final class TOTP
{
    public static function defaultIssuer(): string
    {
        $issuer = '';

        if (function_exists('app_display_name')) {
            $issuer = trim((string)app_display_name());
        }

        if ($issuer === '' && defined('APP_NAME')) {
            $issuer = trim((string)APP_NAME);
        }

        if ($issuer === '') {
            $issuer = 'OdareHub';
        }

        return $issuer;
    }

    public static function randomSecret(int $bytes = 20): string
    {
        $raw = random_bytes($bytes);
        return self::base32Encode($raw);
    }

    public static function verify(string $base32Secret, string $code, int $period = 30, int $window = 1): bool
    {
        $code = preg_replace('/\s+/', '', $code ?? '');
        if (!preg_match('/^\d{6}$/', $code)) return false;

        $time = time();
        $counter = intdiv($time, $period);

        for ($i = -$window; $i <= $window; $i++) {
            $expected = self::hotp($base32Secret, $counter + $i);
            if (hash_equals($expected, $code)) return true;
        }
        return false;
    }

    public static function provisioningUri(string $issuer, string $account, string $base32Secret): string
    {
        $issuerEnc = rawurlencode($issuer);
        $label = rawurlencode($issuer . ':' . $account);
        $secret = rawurlencode($base32Secret);

        return "otpauth://totp/{$label}?secret={$secret}&issuer={$issuerEnc}&algorithm=SHA1&digits=6&period=30";
    }

    private static function hotp(string $base32Secret, int $counter): string
    {
        $key = self::base32Decode($base32Secret);
        if ($key === '') return '000000';

        $binCounter = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $binCounter, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0F;

        $part = substr($hash, $offset, 4);
        $value = unpack('N', $part)[1] & 0x7FFFFFFF;

        $otp = $value % 1000000;
        return str_pad((string)$otp, 6, '0', STR_PAD_LEFT);
    }

    private static function base32Decode(string $b32): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $b32 = strtoupper($b32);
        $b32 = preg_replace('/[^A-Z2-7]/', '', $b32);

        $bits = '';
        $out = '';

        for ($i = 0; $i < strlen($b32); $i++) {
            $val = strpos($alphabet, $b32[$i]);
            if ($val === false) continue;
            $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
        }

        for ($i = 0; $i + 8 <= strlen($bits); $i += 8) {
            $out .= chr(bindec(substr($bits, $i, 8)));
        }

        return $out;
    }

    private static function base32Encode(string $raw): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        for ($i = 0; $i < strlen($raw); $i++) {
            $bits .= str_pad(decbin(ord($raw[$i])), 8, '0', STR_PAD_LEFT);
        }

        $out = '';
        for ($i = 0; $i < strlen($bits); $i += 5) {
            $chunk = substr($bits, $i, 5);
            if (strlen($chunk) < 5) $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $out .= $alphabet[bindec($chunk)];
        }

        return $out;
    }
}

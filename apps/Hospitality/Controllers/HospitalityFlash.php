<?php
declare(strict_types=1);

namespace Apps\Hospitality\Controllers;

/**
 * Flash redirect helper. ok/err carry translation keys; views translate them.
 */
final class HospitalityFlash
{
    public static function redirectOk(string $path, string $key): void
    {
        self::redirect($path, 'ok', $key);
    }

    public static function redirectErr(string $path, string $key): void
    {
        self::redirect($path, 'err', $key);
    }

    private static function redirect(string $path, string $type, string $key): void
    {
        header('Location: ' . $path . '?' . $type . '=' . urlencode(strtolower($key)), true, 302);
        exit;
    }
}

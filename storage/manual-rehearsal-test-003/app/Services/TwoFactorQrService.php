<?php
declare(strict_types=1);

namespace App\Services;

final class TwoFactorQrService
{
    /**
     * @return array{ok:bool,png_bytes:string,data_uri:string,error:string}
     */
    public static function buildPayload(string $uri): array
    {
        $uri = trim($uri);
        if ($uri === '') {
            return self::failure('Missing TOTP provisioning URI.');
        }

        if (!class_exists(\Endroid\QrCode\QrCode::class) || !class_exists(\Endroid\QrCode\Writer\PngWriter::class)) {
            return self::failure('QR code library is not available on this server.');
        }

        if (!extension_loaded('gd')) {
            return self::failure('QR image generation requires the GD PHP extension.');
        }

        $previousDisplayErrors = ini_get('display_errors');
        $previousErrorReporting = error_reporting();
        $bufferLevel = ob_get_level();

        @ini_set('display_errors', '0');
        error_reporting($previousErrorReporting & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
        ob_start();

        try {
            $qrCode = new \Endroid\QrCode\QrCode(
                data: $uri,
                size: 260,
                margin: 12
            );

            $writer = new \Endroid\QrCode\Writer\PngWriter();
            $result = $writer->write($qrCode);
            $pngBytes = $result->getString();
            $noise = (string)ob_get_clean();

            if (trim($noise) !== '') {
                error_log('Setup 2FA QR generation emitted unexpected output: ' . trim($noise));
            }

            return [
                'ok' => true,
                'png_bytes' => $pngBytes,
                'data_uri' => 'data:image/png;base64,' . base64_encode($pngBytes),
                'error' => '',
            ];
        } catch (\Throwable $e) {
            $noise = (string)ob_get_clean();
            if (trim($noise) !== '') {
                error_log('Setup 2FA QR generation emitted unexpected output before failure: ' . trim($noise));
            }
            error_log('Setup 2FA QR generation failed: ' . $e->getMessage());

            return self::failure($e->getMessage());
        } finally {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            @ini_set('display_errors', (string)$previousDisplayErrors);
            error_reporting($previousErrorReporting);
        }
    }

    /**
     * @return array{ok:bool,png_bytes:string,data_uri:string,error:string}
     */
    private static function failure(string $message): array
    {
        return [
            'ok' => false,
            'png_bytes' => '',
            'data_uri' => '',
            'error' => trim($message),
        ];
    }
}

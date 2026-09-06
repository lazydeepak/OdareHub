<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Logo Upload Service
 * 
 * Handles logo file uploads, validation, and storage for branding.
 */
final class LogoUploadService
{
    private const STORAGE_DIR = 'storage/branding';
    private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
    private const ALLOWED_TYPES = ['image/png', 'image/svg+xml'];
    private const ALLOWED_EXTENSIONS = ['png', 'svg'];

    public static function maxFileSizeBytes(): int
    {
        return self::MAX_FILE_SIZE;
    }

    public static function maxFileSizeLabel(): string
    {
        return self::formatBytes(self::MAX_FILE_SIZE);
    }

    /**
     * Process uploaded logo file
     * 
     * @param array<string,mixed> $file File from $_FILES
     * @return array<string,string> ['success' => bool, 'path' => string|null, 'error' => string|null]
     */
    public static function processUpload(array $file): array
    {
        if (empty($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return [
                'success' => false,
                'path' => null,
                'error' => self::getUploadErrorMessage($file['error'] ?? UPLOAD_ERR_NO_FILE),
            ];
        }

        // Validate file size
        if ($file['size'] > self::MAX_FILE_SIZE) {
            return [
                'success' => false,
                'path' => null,
                'error' => self::tr(
                    'organization.error.logo_file_too_large',
                    'Logo file must be smaller than {size}. Current size: {current}',
                    [
                        '{size}' => self::maxFileSizeLabel(),
                        '{current}' => self::formatBytes((int)($file['size'] ?? 0)),
                    ]
                ),
            ];
        }

        // Validate MIME type
        $mimeType = self::getMimeType($file['tmp_name']);
        if (!in_array($mimeType, self::ALLOWED_TYPES, true)) {
            return [
                'success' => false,
                'path' => null,
                'error' => self::tr(
                    'organization.error.logo_invalid_type',
                    'Logo must be PNG or SVG format. Detected: {type}',
                    ['{type}' => $mimeType]
                ),
            ];
        }

        // Validate file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return [
                'success' => false,
                'path' => null,
                'error' => self::tr(
                    'organization.error.logo_invalid_extension',
                    'Logo file extension must be .png or .svg'
                ),
            ];
        }

        // Generate safe filename
        $filename = self::generateFilename($extension);
        $filepath = self::STORAGE_DIR . '/' . $filename;
        $fullpath = dirname(__DIR__, 2) . '/' . $filepath;

        // Ensure directory exists
        if (!is_dir(dirname($fullpath))) {
            mkdir(dirname($fullpath), 0755, true);
        }

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $fullpath)) {
            return [
                'success' => false,
                'path' => null,
                'error' => self::tr(
                    'organization.error.logo_save_failed',
                    'Failed to save uploaded logo file'
                ),
            ];
        }

        // Set proper permissions
        chmod($fullpath, 0644);

        return [
            'success' => true,
            'path' => '/' . $filepath,
            'error' => null,
        ];
    }

    /**
     * Delete a logo file
     */
    public static function deleteLogo(string $logoPath): bool
    {
        if (empty($logoPath)) {
            return false;
        }

        // Security: ensure path is within branding directory
        $realPath = realpath(dirname(__DIR__, 2) . $logoPath);
        $brandinDir = realpath(dirname(__DIR__, 2) . '/' . self::STORAGE_DIR);
        
        if ($realPath === false || $brandinDir === false || strpos($realPath, $brandinDir) !== 0) {
            return false;
        }

        if (file_exists($realPath)) {
            return unlink($realPath);
        }

        return true;
    }

    /**
     * Get MIME type of file
     */
    private static function getMimeType(string $filepath): string
    {
        // Try mime_content_type first
        if (function_exists('mime_content_type')) {
            return mime_content_type($filepath);
        }

        // Fallback: use fileinfo
        if (function_exists('finfo_file')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $type = finfo_file($finfo, $filepath);
            finfo_close($finfo);
            return $type ?: 'application/octet-stream';
        }

        // Final fallback: check magic bytes
        $handle = fopen($filepath, 'rb');
        if ($handle === false) {
            return 'application/octet-stream';
        }

        $bytes = fread($handle, 8);
        fclose($handle);

        // PNG: 89 50 4E 47
        if (str_starts_with($bytes, "\x89PNG")) {
            return 'image/png';
        }

        // SVG: starts with < and contains svg tag
        if (str_starts_with(ltrim($bytes), '<')) {
            return 'image/svg+xml';
        }

        return 'application/octet-stream';
    }

    /**
     * Generate safe filename
     */
    private static function generateFilename(string $extension): string
    {
        // company_id_timestamp_random.ext
        $timestamp = (int)microtime(true) * 1000;
        $random = bin2hex(random_bytes(4));
        return 'logo_' . $timestamp . '_' . $random . '.' . $extension;
    }

    /**
     * Get upload error message
     */
    private static function getUploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE => self::tr('organization.error.upload_ini_size', 'The uploaded file exceeds the server upload size limit.'),
            UPLOAD_ERR_FORM_SIZE => self::tr('organization.error.upload_form_size', 'The uploaded file exceeds the allowed form upload size limit.'),
            UPLOAD_ERR_PARTIAL => self::tr('organization.error.upload_partial', 'The file was only partially uploaded.'),
            UPLOAD_ERR_NO_FILE => self::tr('organization.error.upload_no_file', 'No file was uploaded.'),
            UPLOAD_ERR_NO_TMP_DIR => self::tr('organization.error.upload_no_tmp_dir', 'Missing temporary folder.'),
            UPLOAD_ERR_CANT_WRITE => self::tr('organization.error.upload_cant_write', 'Failed to write file to disk.'),
            UPLOAD_ERR_EXTENSION => self::tr('organization.error.upload_extension', 'A PHP extension stopped the file upload.'),
            default => self::tr('organization.error.upload_unknown', 'Unknown upload error.'),
        };
    }

    /**
     * Format bytes to human readable
     */
    private static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Get public logo URL
     */
    public static function getLogoUrl(?string $logoPath): ?string
    {
        if (empty($logoPath)) {
            return null;
        }

        // If already a URL, return as-is
        if (filter_var($logoPath, FILTER_VALIDATE_URL)) {
            return $logoPath;
        }

        if (preg_match('#^/storage/branding/([^/]+)$#', $logoPath, $matches) === 1) {
            return '/file/branding?f=' . urlencode($matches[1]);
        }

        // If already public path, keep it as-is.
        if (str_starts_with($logoPath, '/')) {
            return $logoPath;
        }

        return null;
    }

    /**
     * Check if logo file exists
     */
    public static function logoExists(?string $logoPath): bool
    {
        if (empty($logoPath)) {
            return false;
        }

        $fullpath = dirname(__DIR__, 2) . $logoPath;
        return file_exists($fullpath) && is_file($fullpath);
    }

    /**
     * @param array<string,string> $replacements
     */
    private static function tr(string $key, string $fallback, array $replacements = []): string
    {
        $message = function_exists('t') ? (string)t($key) : $fallback;
        if ($message === '' || $message === $key) {
            $message = $fallback;
        }

        if ($replacements !== []) {
            $message = strtr($message, $replacements);
        }

        return $message;
    }
}

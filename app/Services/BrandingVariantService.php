<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Branding Variant Service
 *
 * Uses PHP GD to generate standard raster variants (display, icon) from an uploaded
 * PNG logo.  SVG and non-PNG sources are skipped gracefully — no error is thrown;
 * the caller receives an empty result set and should treat that as "no variants".
 *
 * Generated variants are stored alongside the original in storage/branding/ and
 * are named with a deterministic suffix so they can be re-created without conflicts.
 */
final class BrandingVariantService
{
    private const STORAGE_DIR = 'storage/branding';

    /**
     * Human-readable surface annotation for each variant key.
     * Used by the branding browser UI to show where each variant is consumed.
     *
     * @var array<string, string>  variant_key → locale key for recommended surface label
     */
    public const VARIANT_SURFACES = [
        'original' => 'organization.branding.surface_original',
        'display'  => 'organization.branding.surface_display',
        'icon'     => 'organization.branding.surface_icon',
    ];

    /**
     * Variant definitions.
     *
     * max_w  – maximum output width in pixels
     * max_h  – maximum output height in pixels
     * square – if true, center-crop source to a square before resizing;
     *           if false, fit within the bounding box while preserving aspect ratio
     *
     * @var array<string, array{max_w:int, max_h:int, square:bool}>
     */
    private const VARIANTS = [
        'display' => ['max_w' => 400, 'max_h' => 140, 'square' => false],
        'icon'    => ['max_w' => 128, 'max_h' => 128, 'square' => true],
    ];

    /**
     * Generate standard raster variants from a stored logo file.
     *
     * Returns an array of variant descriptors suitable for passing directly to
     * OrganizationService::insertVariantAssetRecord() — one entry per generated file.
     * An empty array is returned when the source is an SVG or when GD is unavailable.
     *
     * @param  string $storagePath  Storage-relative path, e.g. /storage/branding/logo-abc123.png
     * @return array<int, array{variant_key:string, file_path:string, mime_type:string, pixel_width:int, pixel_height:int, file_size_bytes:int}>
     */
    public static function generateVariants(string $storagePath): array
    {
        if (!extension_loaded('gd')) {
            return [];
        }

        $absSource = rtrim(APP_ROOT, '/') . '/' . ltrim($storagePath, '/');
        if (!is_file($absSource)) {
            return [];
        }

        // Only raster PNG is processed; SVG is resolution-independent, no variants needed.
        $mime = function_exists('mime_content_type') ? (string)(mime_content_type($absSource) ?: '') : '';
        if ($mime !== 'image/png') {
            return [];
        }

        $src = @imagecreatefrompng($absSource);
        if ($src === false) {
            return [];
        }

        $origW = imagesx($src);
        $origH = imagesy($src);
        $stem  = pathinfo($storagePath, PATHINFO_FILENAME);

        $results = [];

        foreach (self::VARIANTS as $variantKey => $spec) {
            $maxW   = $spec['max_w'];
            $maxH   = $spec['max_h'];
            $square = $spec['square'];

            if ($square) {
                // Center-crop to a square, then resize to maxW × maxH.
                $srcSide = min($origW, $origH);
                $srcX    = (int)(($origW - $srcSide) / 2);
                $srcY    = (int)(($origH - $srcSide) / 2);
                $destW   = $maxW;
                $destH   = $maxH;
            } else {
                // Fit within bounding box, never upscale.
                $scale = min(1.0, $maxW / max(1, $origW), $maxH / max(1, $origH));
                $destW = max(1, (int)round($origW * $scale));
                $destH = max(1, (int)round($origH * $scale));
                $srcX  = 0;
                $srcY  = 0;
                $srcSide = 0; // unused path
            }

            $dst = imagecreatetruecolor($destW, $destH);
            if ($dst === false) {
                continue;
            }

            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            if ($transparent !== false) {
                imagefill($dst, 0, 0, $transparent);
            }

            if ($square) {
                imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, $destW, $destH, $srcSide, $srcSide);
            } else {
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $destW, $destH, $origW, $origH);
            }

            $outFilename = $stem . '-' . $variantKey . '.png';
            $relPath     = '/' . self::STORAGE_DIR . '/' . $outFilename;
            $absOut      = rtrim(APP_ROOT, '/') . $relPath;

            if (!is_dir(dirname($absOut))) {
                mkdir(dirname($absOut), 0755, true);
            }

            // Write to a temp file first, then move into place atomically.
            $tmpFile = tempnam(sys_get_temp_dir(), 'bv_');
            if ($tmpFile === false) {
                continue;
            }

            $saved = imagepng($dst, $tmpFile, 7);

            if (!$saved || !is_file($tmpFile)) {
                @unlink($tmpFile);
                continue;
            }

            $fileSize = (int)(filesize($tmpFile) ?: 0);

            if (!rename($tmpFile, $absOut)) {
                @unlink($tmpFile);
                continue;
            }

            @chmod($absOut, 0644);

            $results[] = [
                'variant_key'     => $variantKey,
                'file_path'       => $relPath,
                'mime_type'       => 'image/png',
                'pixel_width'     => $destW,
                'pixel_height'    => $destH,
                'file_size_bytes' => $fileSize,
            ];
        }

        return $results;
    }

    /**
     * Delete on-disk variant files that belong to a given original stem.
     * Safe to call even if the files do not exist.
     *
     * @param string $storagePath  The original file path, e.g. /storage/branding/logo-abc123.png
     */
    public static function deleteVariantsFor(string $storagePath): void
    {
        $stem = pathinfo($storagePath, PATHINFO_FILENAME);
        $root = rtrim(APP_ROOT, '/');

        foreach (array_keys(self::VARIANTS) as $variantKey) {
            $relPath = '/' . self::STORAGE_DIR . '/' . $stem . '-' . $variantKey . '.png';
            $absPath = $root . $relPath;
            if (is_file($absPath)) {
                @unlink($absPath);
            }
        }
    }
}

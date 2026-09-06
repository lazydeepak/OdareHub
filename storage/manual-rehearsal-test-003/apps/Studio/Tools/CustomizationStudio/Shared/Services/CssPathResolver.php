<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\CustomizationStudio\Shared\Services;

final class CssPathResolver
{
    private const APPROVED_ROOTS = [
        'apps/Shell/DesignSystem/Resources/socket-catalog',
        'resources/themes',
        'public/assets',
        'apps',
        'platform/Style',
    ];

    private function __construct()
    {
    }

    public static function resolve(string $relativePath): ?string
    {
        $trimmed = trim($relativePath);
        if ($trimmed === '' || str_contains($trimmed, '..')) {
            return null;
        }

        $absolute = APP_ROOT . '/' . ltrim($trimmed, '/');
        $real = realpath($absolute);
        if ($real === false || !is_file($real) && !is_dir($real)) {
            return null;
        }

        if (!self::isUnderApprovedRoot($real)) {
            return null;
        }

        return $real;
    }

    public static function isUnderApprovedRoot(string $realPath): bool
    {
        $normalized = rtrim($realPath, '/') . '/';
        $root = rtrim(APP_ROOT, '/') . '/';

        if (!str_starts_with($normalized, $root)) {
            return false;
        }

        $relative = substr($normalized, strlen($root));

        foreach (self::APPROVED_ROOTS as $approved) {
            if (str_starts_with($relative, $approved)) {
                return true;
            }
        }

        return false;
    }

    public static function canonicalSourcePath(string $tokenName): ?string
    {
        $normalized = strtolower(trim($tokenName));
        if ($normalized === '') {
            return null;
        }
        if (!str_starts_with($normalized, '--')) {
            $normalized = '--' . ltrim($normalized, '-');
        }

        $themeDirs = [
            APP_ROOT . '/resources/themes/semantic',
            APP_ROOT . '/resources/themes',
        ];

        foreach ($themeDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'css') {
                    continue;
                }
                $contents = @file_get_contents($file->getRealPath());
                if ($contents === false) {
                    continue;
                }
                if (preg_match('/\b' . preg_quote($normalized, '/') . '\s*:/i', $contents)) {
                    return $file->getRealPath();
                }
            }
        }

        return null;
    }

    public static function relativePath(string $realPath): string
    {
        $root = rtrim(APP_ROOT, '/') . '/';
        if (str_starts_with($realPath, $root)) {
            return substr($realPath, strlen($root));
        }
        return $realPath;
    }
}

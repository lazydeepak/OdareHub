<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\LocalizationScanExtraction\Services;

final class FileIntegrityValidator
{
    public static function validatePhpSyntax(string $path): array
    {
        if (!is_file($path)) {
            return ['valid' => false, 'error' => 'File not found: ' . $path];
        }

        $phpBinary = self::resolvePhpBinary();
        if ($phpBinary === null) {
            return [
                'valid' => true,
                'warning' => 'PHP CLI binary is unavailable; syntax lint was skipped.',
            ];
        }

        $lintLines = null;
        $exitCode = -1;
        $cmd = escapeshellarg($phpBinary) . ' -l ' . escapeshellarg($path) . ' 2>&1';
        exec($cmd, $lintLines, $exitCode);

        $lintText = implode("\n", is_array($lintLines) ? $lintLines : []);

        if ($exitCode === 0 && str_contains($lintText, 'No syntax errors detected')) {
            return ['valid' => true];
        }

        $error = 'PHP syntax validation failed.';
        if (preg_match('/Parse error:\s*(.+?)(\n|$)/', $lintText, $m)) {
            $error = trim($m[1]);
        } elseif (preg_match('/Fatal error:\s*(.+?)(\n|$)/', $lintText, $m)) {
            $error = trim($m[1]);
        }

        return ['valid' => false, 'error' => self::sanitizeDiagnosticMessage($error)];
    }

    public static function validatePhpSyntaxOnLocaleKeys(string $path): array
    {
        $syntax = self::validatePhpSyntax($path);
        if (!$syntax['valid']) {
            return $syntax;
        }

        try {
            self::invalidatePhpCache($path);
            $loaded = @require $path;
            if (!is_array($loaded)) {
                return ['valid' => false, 'error' => 'File did not return a valid array.'];
            }
        } catch (\Throwable $e) {
            return ['valid' => false, 'error' => 'Locale file parse failed: ' . $e->getMessage()];
        }

        $issues = [];
        foreach ($loaded as $key => $value) {
            if (!is_string($key) || $key === '') {
                $issues[] = 'Non-string or empty key detected.';
            }
            if (!is_string($value)) {
                $issues[] = 'Non-string value for key: ' . (is_string($key) ? $key : '(non-string)');
            }
            if (is_string($key) && (str_contains($key, "'") || str_contains($key, '"'))) {
                $issues[] = 'Key contains quotes: ' . $key;
            }
        }

        if ($issues !== []) {
            return ['valid' => false, 'error' => 'File integrity issues: ' . implode('; ', $issues), 'issues' => $issues];
        }

        return ['valid' => true, 'key_count' => count($loaded)];
    }

    public static function validateSourceFileIntegrity(string $path): array
    {
        if (!is_file($path)) {
            return ['valid' => false, 'error' => 'File not found'];
        }

        if (!str_ends_with($path, '.php')) {
            return ['valid' => true, 'note' => 'Non-PHP file, skipped syntax check'];
        }

        return self::validatePhpSyntax($path);
    }

    private static function invalidatePhpCache(string $path): void
    {
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }
        clearstatcache(true, $path);
    }

    private static function sanitizeDiagnosticMessage(string $message): string
    {
        if (preg_match('/(^|\n)\s*sh:\s*[^\\n]*php:\s*command not found/i', $message) === 1) {
            return 'PHP CLI binary is unavailable; syntax lint was skipped.';
        }

        return trim($message) !== '' ? trim($message) : 'PHP syntax validation failed.';
    }

    private static function resolvePhpBinary(): ?string
    {
        $binary = defined('PHP_BINARY') ? (string)PHP_BINARY : '';
        if ($binary !== '' && is_file($binary) && is_executable($binary) && !str_contains(basename($binary), 'php-fpm')) {
            return $binary;
        }

        if (!function_exists('exec')) {
            return null;
        }

        $lookupLines = [];
        $exitCode = -1;
        @exec('command -v php 2>/dev/null', $lookupLines, $exitCode);
        $candidate = $exitCode === 0 && isset($lookupLines[0]) ? trim((string)$lookupLines[0]) : '';
        if ($candidate !== '' && is_file($candidate) && is_executable($candidate)) {
            return $candidate;
        }

        return null;
    }
}

<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\LocalizationScanExtraction\Services;

final class LocalizationScanExtractionService
{
    private const SUPPORTED_LOCALES = ['en', 'ja', 'ne'];

    public static function generatePreview(array $finding, string $ownerKey, string $locale): array
    {
        $type = $finding['type'] ?? '';
        $detected = $finding['detected'] ?? '';
        $suggestedKey = $finding['suggested_key'] ?? '';

        if ($suggestedKey === '' && $type === 'inline_text') {
            return self::noPreview('No suggested key available');
        }

        $langAddition = '';
        $sourceReplacement = '';
        $riskNotes = [];

        if ($type === 'inline_text') {
            $detectedEscaped = var_export($detected, true);
            $langAddition = "'{$suggestedKey}' => {$detectedEscaped},";

            $sourceReplacement = self::generateReplacement($finding, $suggestedKey);
            $riskNotes = self::assessRisk($finding, $detected);
        }

        if ($type === 'missing_key') {
            $langAddition = "'{$detected}' => '',";
            $sourceReplacement = null;
            $riskNotes = ['Missing key only — no inline text to replace. Add translation value manually.'];
        }

        if (in_array($type, ['loc_key_usage', 'unused_key'])) {
            return self::noPreview('Key reference — no extraction needed');
        }

        $langFilePath = self::resolveLocalePath($ownerKey, $locale);

        return [
            'preview_ok' => true,
            'finding_type' => $type,
            'owner_key' => $ownerKey,
            'locale' => $locale,
            'detected' => $detected,
            'suggested_key' => $suggestedKey,
            'file_path' => $finding['file'] ?? '',
            'line' => $finding['line'] ?? 0,
            'lang_file_path' => $langFilePath ? str_replace(APP_ROOT . '/', '', $langFilePath) : null,
            'lang_addition_code' => $langAddition,
            'source_replacement_code' => $sourceReplacement,
            'risk_notes' => $riskNotes,
        ];
    }

    public static function applyExtraction(array $finding, string $ownerKey, string $locale, array $preview): array
    {
        $diagnostics = [];
        $filesModified = [];

        $suggestedKey = $finding['suggested_key'] ?? '';
        if ($suggestedKey === '') {
            return [
                'success' => false,
                'error' => 'No suggested key available',
                'diagnostics' => [],
                'files_modified' => [],
            ];
        }

        $localePath = self::resolveLocalePath($ownerKey, $locale);
        if ($localePath === null) {
            return [
                'success' => false,
                'error' => 'Locale file not found: ' . $ownerKey . '/' . $locale,
                'diagnostics' => [],
                'files_modified' => [],
            ];
        }

        self::snapshot($localePath);

        $localeWritten = self::appendToLocaleFile($localePath, $suggestedKey, $finding['detected'] ?? '');
        if ($localeWritten) {
            $diagnostics[] = 'Locale file updated: ' . str_replace(APP_ROOT . '/', '', $localePath);
            $filesModified[] = $localePath;
        } else {
            $diagnostics[] = 'WARN: Could not write to locale file';
        }

        $sourcePath = self::resolveSourcePath($finding, $ownerKey);
        if ($sourcePath !== null && is_file($sourcePath)) {
            self::snapshot($sourcePath);

            $sourceWritten = self::replaceInSourceFile($sourcePath, $finding, $suggestedKey);
            if ($sourceWritten) {
                $diagnostics[] = 'Source file updated: ' . str_replace(APP_ROOT . '/', '', $sourcePath);
                $filesModified[] = $sourcePath;
            } else {
                $diagnostics[] = 'INFO: No inline text replacement performed (cross-reference finding)';
            }
        } elseif ($finding['type'] === 'inline_text' && $sourcePath !== null) {
            $diagnostics[] = 'WARN: Source file not found: ' . $sourcePath;
        }

        return [
            'success' => count($filesModified) > 0,
            'error' => null,
            'diagnostics' => $diagnostics,
            'files_modified' => $filesModified,
        ];
    }

    public static function snapshot(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        $dir = dirname($path) . '/.backups';
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
                return null;
            }
        }

        $ts = date('Ymd_His');
        $base = basename($path);
        $snap = $dir . '/' . $base . '.' . $ts . '.bak';

        return @copy($path, $snap) ? $snap : null;
    }

    private static function generateReplacement(array $finding, string $suggestedKey): ?string
    {
        $context = trim($finding['context'] ?? '');
        $detected = $finding['detected'] ?? '';

        if ($context === '' || $detected === '') {
            return null;
        }

        if (str_contains($context, 'echo ') && str_contains($context, '"' . $detected . '"')) {
            $replacement = str_replace('"' . $detected . '"', "tr('{$suggestedKey}')", $context);
            return $replacement;
        }

        if (str_contains($context, "=> ") && str_contains($context, '"' . $detected . '"')) {
            $replacement = str_replace('"' . $detected . '"', "tr('{$suggestedKey}')", $context);
            return $replacement;
        }

        return "<?= tr('{$suggestedKey}') ?>";
    }

    private static function assessRisk(array $finding, string $detected): array
    {
        $risks = [];

        $type = $finding['type'] ?? '';
        $confidence = $finding['confidence'] ?? '';
        $context = $finding['context'] ?? '';

        if ($type !== 'inline_text') {
            return [];
        }

        if ($confidence === 'low') {
            $risks[] = 'Low confidence — HTML text-node extraction may need manual review';
        }

        if (str_contains($context, 'echo ')) {
        } elseif (str_contains($context, '=> ')) {
            $risks[] = 'Array value — verify this is display text, not a configuration value';
        }

        if (mb_strlen($detected) > 100) {
            $risks[] = 'Long text (>100 chars) — may contain dynamic content';
        }

        if (preg_match('/%(s|d|u|f|x|s)/', $detected)) {
            $risks[] = 'Contains sprintf format specifiers — use with vsprintf or handle in translation';
        }

        return $risks;
    }

    private static function appendToLocaleFile(string $path, string $key, string $value): bool
    {
        $data = @require $path;
        if (!is_array($data)) {
            return false;
        }

        $parts = explode('.', $key);
        $current = &$data;
        for ($i = 0; $i < count($parts) - 1; $i++) {
            if (!isset($current[$parts[$i]]) || !is_array($current[$parts[$i]])) {
                $current[$parts[$i]] = [];
            }
            $current = &$current[$parts[$i]];
        }
        $lastPart = $parts[count($parts) - 1];
        if (!isset($current[$lastPart])) {
            $current[$lastPart] = $value;
        } else {
            return false;
        }

        $php = self::renderPhpArray($data);

        $tmp = $path . '.tmp.' . getmypid();
        $written = @file_put_contents($tmp, $php);
        if ($written === false) {
            @unlink($tmp);
            return false;
        }

        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            return false;
        }

        return true;
    }

    private static function replaceInSourceFile(string $path, array $finding, string $suggestedKey): bool
    {
        $content = @file_get_contents($path);
        if ($content === false) {
            return false;
        }

        $detected = $finding['detected'] ?? '';
        if ($detected === '') {
            return false;
        }

        $quoted = '"' . $detected . '"';
        $replacement = "tr('{$suggestedKey}')";

        if (!str_contains($content, $quoted)) {
            return false;
        }

        $newContent = str_replace($quoted, $replacement, $content);

        if ($newContent === $content) {
            return false;
        }

        $tmp = $path . '.tmp.' . getmypid();
        $written = @file_put_contents($tmp, $newContent);
        if ($written === false) {
            @unlink($tmp);
            return false;
        }

        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            return false;
        }

        return true;
    }

    private static function renderPhpArray(array $data, int $depth = 0): string
    {
        $indent = str_repeat('    ', $depth + 1);
        $closeIndent = str_repeat('    ', $depth);
        $php = "[\n";
        foreach ($data as $key => $value) {
            $k = var_export((string) $key, true);
            if (is_array($value)) {
                $php .= "{$indent}{$k} => " . self::renderPhpArray($value, $depth + 1) . ",\n";
            } else {
                $v = var_export((string) $value, true);
                $php .= "{$indent}{$k} => {$v},\n";
            }
        }
        $php .= "{$closeIndent}]";
        return $php;
    }

    private static function resolveLocalePath(string $ownerKey, string $locale): ?string
    {
        $base = self::resolveOwnerPath($ownerKey);
        if ($base === null) return null;

        $candidates = [
            $base . '/Resources/lang/' . $locale . '.php',
            $base . '/lang/' . $locale . '.php',
        ];

        foreach ($candidates as $p) {
            if (is_file($p)) {
                return $p;
            }
        }

        return null;
    }

    private static function resolveSourcePath(array $finding, string $ownerKey): ?string
    {
        $file = $finding['file'] ?? '';
        if ($file === '' || $file === '(cross-reference)' || $file === '(locale file)') {
            return null;
        }

        $candidate = APP_ROOT . '/' . $file;
        if (is_file($candidate)) {
            return $candidate;
        }

        $base = self::resolveOwnerPath($ownerKey);
        if ($base === null) return null;

        $candidate2 = $base . '/' . $file;
        if (is_file($candidate2)) {
            return $candidate2;
        }

        return null;
    }

    private static function resolveOwnerPath(string $ownerKey): ?string
    {
        if (preg_match('#^(Plugin/)?(.+)$#', $ownerKey, $m)) {
            $remainder = $m[2];
        } else {
            $remainder = $ownerKey;
        }
        if (str_starts_with($remainder, 'Studio/Tools/')) {
            return APP_ROOT . '/apps/Studio/Tools/' . substr($remainder, 13);
        }
        if (str_contains($remainder, '/')) {
            $parts = explode('/', $remainder, 2);
            return APP_ROOT . '/apps/' . $parts[0] . '/modules/' . $parts[1];
        }
        if (str_starts_with($ownerKey, 'Plugin/')) {
            return APP_ROOT . '/plugins/' . substr($ownerKey, 7);
        }
        return APP_ROOT . '/apps/' . $remainder;
    }

    private static function noPreview(string $reason): array
    {
        return [
            'preview_ok' => false,
            'reason' => $reason,
        ];
    }
}

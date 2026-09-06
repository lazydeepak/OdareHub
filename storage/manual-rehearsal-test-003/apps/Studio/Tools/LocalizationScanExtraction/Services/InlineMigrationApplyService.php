<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\LocalizationScanExtraction\Services;

use Apps\Studio\Tools\LocalizationScanExtraction\ValueObjects\CorrectionReport;

final class InlineMigrationApplyService
{
    private const SAFE_ATTRIBUTES = ['title', 'alt', 'placeholder', 'aria-label'];
    private const SAFE_ELEMENT_TYPES = ['heading', 'button', 'link', 'description', 'empty_state', 'table_header', 'label', 'status', 'confirmation', 'error_message'];

    public static function apply(array $candidates, string $ownerKey, string $scope, string $locale): CorrectionReport
    {
        if ($locale !== 'en') {
            return new CorrectionReport(
                action: 'inline_migration',
                ownerKey: $ownerKey,
                locale: $locale,
                success: false,
                error: 'Inline migration is only supported for English (en) locale.',
            );
        }

        if ($candidates === []) {
            return new CorrectionReport(
                action: 'inline_migration',
                ownerKey: $ownerKey,
                locale: $locale,
                success: false,
                error: 'No ready-to-migrate candidates provided.',
            );
        }

        $snapshotPaths = [];
        $enPath = self::resolveLocalePath($ownerKey, 'en');
        if ($enPath === null) {
            return new CorrectionReport(
                action: 'inline_migration',
                ownerKey: $ownerKey,
                locale: $locale,
                success: false,
                error: 'Could not resolve en.php path for owner: ' . $ownerKey,
            );
        }

        if (is_file($enPath)) {
            $enSnapshot = self::snapshotFile($enPath, 'inline-migration-en', $ownerKey);
            if ($enSnapshot !== null) {
                $snapshotPaths['en.php'] = $enSnapshot;
            }
        }

        $existing = [];
        if (is_file($enPath)) {
            $loaded = require $enPath;
            if (is_array($loaded)) {
                $existing = $loaded;
            }
        }

        $addedKeys = [];
        $skippedKeys = [];
        $modifiedFiles = [];
        $filesToSnapshot = [];

        foreach ($candidates as $candidate) {
            $text = trim((string)($candidate['text'] ?? ''));
            $suggestedKey = trim((string)($candidate['suggested_key'] ?? ''));
            $semanticCategory = (string)($candidate['semantic_category'] ?? '');
            $elementType = (string)($candidate['element_type'] ?? '');
            $file = (string)($candidate['file'] ?? '');
            $line = (int)($candidate['line'] ?? 0);

            if ($text === '' || $suggestedKey === '') {
                $skippedKeys[] = ['key' => $suggestedKey, 'text' => $text, 'reason' => 'Empty text or key'];
                continue;
            }

            if (!in_array($elementType, self::SAFE_ELEMENT_TYPES, true)) {
                $skippedKeys[] = ['key' => $suggestedKey, 'text' => $text, 'reason' => 'Unsafe element type: ' . $elementType];
                continue;
            }

            if (!self::isSafeReplacementTarget($text)) {
                $skippedKeys[] = ['key' => $suggestedKey, 'text' => $text, 'reason' => 'Contains unsafe content (variable, path, HTML, or fragment)'];
                continue;
            }

            if (array_key_exists($suggestedKey, $existing)) {
                $skippedKeys[] = ['key' => $suggestedKey, 'text' => $text, 'reason' => 'Key already exists in en.php'];
                continue;
            }

            $englishValue = self::computeEnglishValue($text, $candidate);
            $existing[$suggestedKey] = $englishValue;
            $addedKeys[] = ['key' => $suggestedKey, 'value' => $englishValue, 'text' => $text, 'file' => $file, 'line' => $line];

            $sourcePath = self::resolveSourcePath($file, $ownerKey);
            if ($sourcePath !== null && is_file($sourcePath)) {
                if (!isset($filesToSnapshot[$sourcePath])) {
                    $filesToSnapshot[$sourcePath] = $file;
                }
            }
        }

        if ($addedKeys === []) {
            return new CorrectionReport(
                action: 'inline_migration',
                ownerKey: $ownerKey,
                locale: $locale,
                success: false,
                error: 'No new keys to add; all candidates were skipped.',
                addedKeys: $skippedKeys,
            );
        }

        foreach ($filesToSnapshot as $absPath => $relPath) {
            $snap = self::snapshotFile($absPath, 'inline-migration-source-' . basename($relPath), $ownerKey);
            if ($snap !== null) {
                $snapshotPaths[$relPath] = $snap;
            }
        }

        ksort($existing, SORT_STRING);
        $localeWritten = self::writeLocaleFile($enPath, $existing);
        if (!$localeWritten) {
            self::rollbackSnapshots($snapshotPaths);
            return new CorrectionReport(
                action: 'inline_migration',
                ownerKey: $ownerKey,
                locale: $locale,
                success: false,
                error: 'Failed to write locale file. All changes rolled back.',
                snapshotPaths: $snapshotPaths,
                addedKeys: $skippedKeys,
            );
        }

        $replacements = [];
        foreach ($addedKeys as $ak) {
            $sourcePath = self::resolveSourcePath($ak['file'], $ownerKey);
            if ($sourcePath === null || !is_file($sourcePath)) {
                continue;
            }
            $replaced = self::replaceInSourceFile($sourcePath, $ak['text'], $ak['key']);
            if ($replaced !== false) {
                $replacements[] = ['file' => $ak['file'], 'key' => $ak['key'], 'text' => $ak['text'], 'replaced' => $replaced];
            }
        }

        $reScanResults = LocalizationScanService::scan($ownerKey, $scope, $locale);
        $reScanOk = !empty($reScanResults['scan_ok']);
        $reScanDiags = [];
        if ($reScanOk) {
            $reScanDiags = [
                'scan_ok' => true,
                'total_findings' => (int)($reScanResults['total_findings'] ?? 0),
                'inline_text' => (int)($reScanResults['summary']['human_facing_candidates'] ?? 0),
                'missing_owner_keys' => (int)($reScanResults['summary']['missing_owner_keys'] ?? 0),
                'pending_missing_keys' => (int)($reScanResults['summary']['pending_missing_keys'] ?? 0),
                'all_clear' => false,
            ];
        } else {
            $reScanDiags = [
                'scan_ok' => false,
                'error' => $reScanResults['error'] ?? 'Re-scan failed',
            ];
            self::rollbackSnapshots($snapshotPaths);
            return new CorrectionReport(
                action: 'inline_migration',
                ownerKey: $ownerKey,
                locale: $locale,
                success: false,
                error: 'Re-scan failed after write. Changes rolled back.',
                snapshotPaths: $snapshotPaths,
                addedKeys: $addedKeys,
                addedCount: count($addedKeys),
                reScanDiagnostics: $reScanDiags,
                rollbackStatus: 'completed',
            );
        }

        $success = true;
        $report = new CorrectionReport(
            action: 'inline_migration',
            ownerKey: $ownerKey,
            locale: $locale,
            success: $success,
            addedCount: count($addedKeys),
            skippedCount: count($skippedKeys),
            addedKeys: $addedKeys,
            message: 'Inline migration completed. Added ' . count($addedKeys) . ' key(s), replaced in ' . count($replacements) . ' file(s).',
            snapshotPaths: $snapshotPaths,
            reScanDiagnostics: $reScanDiags,
            reScanConfirmed: $reScanOk,
        );

        return $report;
    }

    private static function isSafeReplacementTarget(string $text): bool
    {
        if (str_contains($text, '$') || str_contains($text, '{') || str_contains($text, '}')) {
            return false;
        }
        if (str_contains($text, '<?') || str_contains($text, '%>')) {
            return false;
        }
        if (str_contains($text, '<') && (str_contains($text, '>') || preg_match('/<\w+/', $text))) {
            return false;
        }
        if (preg_match('#https?://|/apps/|/ops/#', $text)) {
            return false;
        }
        if (preg_match('/[\'"]/', $text)) {
            return false;
        }
        return true;
    }

    private static function replaceInSourceFile(string $path, string $text, string $key): int
    {
        $content = @file_get_contents($path);
        if ($content === false) {
            return 0;
        }

        $replacement = '<?= e($tt(\'' . $key . '\')) ?>';
        $count = 0;

        $quotedText = preg_quote($text, '/');

        $patterns = [
            '/>\s*' . $quotedText . '\s*</s',
            '/>\s*' . $quotedText . '\s*$/m',
        ];

        $replaced = false;
        foreach ($patterns as $pattern) {
            $newContent = @preg_replace($pattern, '> ' . $replacement . ' <', $content, -1, $count);
            if ($count > 0) {
                $content = $newContent;
                $replaced = true;
                break;
            }
        }

        if (!$replaced) {
            $pattern2 = '/=\s*"' . preg_quote($text, '/') . '"/s';
            $newContent = @preg_replace($pattern2, '= "' . $replacement . '"', $content, -1, $count);
            if ($count > 0) {
                $content = $newContent;
                $replaced = true;
            }
        }

        if (!$replaced) {
            return 0;
        }

        $tmp = $path . '.tmp.' . getmypid();
        $written = @file_put_contents($tmp, $content);
        if ($written === false) {
            @unlink($tmp);
            return 0;
        }

        $lintOk = self::checkPhpLint($tmp);
        if (!$lintOk) {
            @unlink($tmp);
            return 0;
        }

        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            return 0;
        }

        self::invalidatePhpCache($path);

        return $count;
    }

    private static function checkPhpLint(string $path): bool
    {
        $output = [];
        $exitCode = 0;
        $cmd = 'php -l ' . escapeshellarg($path) . ' 2>&1';
        exec($cmd, $output, $exitCode);
        return $exitCode === 0;
    }

    private static function computeEnglishValue(string $text, array $candidate): string
    {
        $suggested = trim((string)($candidate['suggested_english'] ?? ''));
        if ($suggested !== '') {
            return $suggested;
        }
        return $text;
    }

    private static function resolveLocalePath(string $ownerKey, string $locale): ?string
    {
        $base = self::resolveOwnerPath($ownerKey);
        if ($base === null) {
            return null;
        }
        $candidates = [
            $base . '/Resources/lang/' . $locale . '.php',
            $base . '/lang/' . $locale . '.php',
        ];
        foreach ($candidates as $p) {
            if (is_file($p)) {
                return $p;
            }
        }
        return $base . '/Resources/lang/' . $locale . '.php';
    }

    private static function resolveSourcePath(string $relativeFile, string $ownerKey): ?string
    {
        if ($relativeFile === '(cross-reference)' || $relativeFile === '(locale file)') {
            return null;
        }
        $candidate = APP_ROOT . '/' . $relativeFile;
        if (is_file($candidate)) {
            return $candidate;
        }
        $base = self::resolveOwnerPath($ownerKey);
        if ($base !== null) {
            $candidate2 = $base . '/' . $relativeFile;
            if (is_file($candidate2)) {
                return $candidate2;
            }
        }
        return null;
    }

    private static function resolveOwnerPath(string $ownerKey): ?string
    {
        if (str_starts_with($ownerKey, 'Plugin/')) {
            return APP_ROOT . '/plugins/' . substr($ownerKey, 7);
        }
        preg_match('#^(Plugin/)?(.+)$#', $ownerKey, $m);
        $remainder = $m[2] ?? $ownerKey;
        if (str_starts_with($remainder, 'Studio/Tools/')) {
            return APP_ROOT . '/apps/Studio/Tools/' . substr($remainder, 13);
        }
        if (str_contains($remainder, '/')) {
            $parts = explode('/', $remainder, 2);
            return APP_ROOT . '/apps/' . $parts[0] . '/modules/' . $parts[1];
        }
        return APP_ROOT . '/apps/' . $remainder;
    }

    private static function snapshotFile(string $path, string $label, ?string $ownerKey = null): ?string
    {
        if (!is_file($path)) {
            return null;
        }
        $baseDir = APP_ROOT . '/storage/studio-snapshots/localization-scan-extraction';
        if ($ownerKey !== null && $ownerKey !== '') {
            $snapshotDir = $baseDir . '/' . $ownerKey;
        } else {
            $snapshotDir = $baseDir;
        }
        if (!is_dir($snapshotDir)) {
            @mkdir($snapshotDir, 0755, true);
        }
        if (!is_dir($snapshotDir)) {
            return null;
        }
        $snapshotPath = $snapshotDir . '/' . $label . '-' . basename($path) . '-' . date('Ymd_His') . '_' . rand(1000, 9999) . '.bak';
        $copied = @copy($path, $snapshotPath);
        if (!$copied) {
            return null;
        }
        $manifest = [
            'owner_key' => $ownerKey,
            'label' => $label,
            'target_path' => $path,
            'target_relative_path' => str_starts_with($path, APP_ROOT . '/') ? substr($path, strlen(APP_ROOT) + 1) : $path,
            'snapshot_path' => $snapshotPath,
            'created_at' => date('c'),
            'sha256' => hash_file('sha256', $path) ?: null,
        ];
        @file_put_contents($snapshotPath . '.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $snapshotPath;
    }

    private static function writeLocaleFile(string $path, array $keys): bool
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $php = "<?php\nreturn [\n";
        foreach ($keys as $k => $v) {
            $ek = var_export((string)$k, true);
            $ev = var_export((string)$v, true);
            $php .= "    {$ek} => {$ev},\n";
        }
        $php .= "];\n";
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
        self::invalidatePhpCache($path);
        return true;
    }

    private static function invalidatePhpCache(string $path): void
    {
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }
        clearstatcache(true, $path);
    }

    private static function rollbackSnapshots(array $snapshotPaths): void
    {
        $result = RollbackService::rollbackFromReport($snapshotPaths);
    }

    public static function getReadyCandidatesFromFindings(array $findings, string $ownerKey): array
    {
        $planner = new InlineMigrationPlannerService($ownerKey);
        $plan = $planner->buildPlan($findings);
        $ready = [];
        foreach ($plan['candidates'] as $c) {
            if (($c['state'] ?? '') === 'ready_to_migrate') {
                $ready[] = $c;
            }
        }
        return $ready;
    }
}

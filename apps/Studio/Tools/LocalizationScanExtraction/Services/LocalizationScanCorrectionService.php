<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\LocalizationScanExtraction\Services;

use Apps\Studio\Tools\LocalizationScanExtraction\ValueObjects\CorrectionReport;

final class LocalizationScanCorrectionService
{
    private const OVERRIDE_SUGGESTIONS = [
        'open_orders' => 'Open Orders',
        'avg_coverage_pct' => 'Avg Coverage %',
    ];

    public static function addMissingEnglishKeys(string $ownerKey, string $scope, string $locale): array
    {
        if ($locale !== 'en') {
            return ['success' => false, 'error' => 'Missing key addition is only supported for English (en) locale.'];
        }

        $enPath = self::resolveLocalePath($ownerKey, 'en');
        if ($enPath === null) {
            return ['success' => false, 'error' => 'Could not resolve en.php path for owner: ' . $ownerKey];
        }

        $scanResults = LocalizationScanService::scan($ownerKey, $scope, $locale);
        if (empty($scanResults['scan_ok'])) {
            $err = $scanResults['error'] ?? 'Re-scan failed';
            return ['success' => false, 'error' => $err];
        }

        $reviewPlan = $scanResults['missing_key_review_plan'] ?? [];
        $rows = $reviewPlan['rows'] ?? [];
        if ($rows === []) {
            return ['success' => false, 'error' => 'No missing owner keys found in re-scan.'];
        }

        $existing = [];
        if (is_file($enPath)) {
            $loaded = require $enPath;
            if (is_array($loaded)) {
                $existing = $loaded;
            }
        }

        $added = [];
        $skipped = 0;
        foreach ($rows as $row) {
            $key = (string)($row['key'] ?? '');
            if ($key === '') {
                continue;
            }
            if (array_key_exists($key, $existing)) {
                $skipped++;
                continue;
            }
            $value = self::computeEnglishValue($key, $row);
            $existing[$key] = $value;
            $added[] = ['key' => $key, 'value' => $value];
        }

        if ($added === []) {
            return ['success' => true, 'added_count' => 0, 'skipped' => $skipped, 'message' => count($existing) > 0 ? 'All missing keys already exist in locale file.' : 'No new keys to add.'];
        }

        ksort($existing, SORT_STRING);

        $snapshotPath = self::snapshotFile($enPath, 'add-missing-keys', $ownerKey);
        if ($snapshotPath === null) {
            return ['success' => false, 'error' => 'Snapshot failed before write.'];
        }

        $written = self::writeLocaleFile($enPath, $existing);
        if (!$written) {
            return ['success' => false, 'error' => 'Failed to write locale file.'];
        }

        return [
            'success' => true,
            'added_count' => count($added),
            'skipped' => $skipped,
            'added' => $added,
            'snapshot' => $snapshotPath,
        ];
    }

    public static function extractInlineText(string $ownerKey, int $findingIndex, string $scope, string $locale): array
    {
        if ($locale !== 'en') {
            return ['success' => false, 'error' => 'Extraction is only supported for English (en) locale.'];
        }

        $enPath = self::resolveLocalePath($ownerKey, 'en');
        if ($enPath === null) {
            $enDir = self::resolveLangDir($ownerKey);
            if ($enDir === null) {
                return ['success' => false, 'error' => 'Could not resolve locale directory for owner: ' . $ownerKey];
            }
            if (!is_dir($enDir)) {
                @mkdir($enDir, 0755, true);
            }
            $enPath = $enDir . '/en.php';
        }

        $scanResults = LocalizationScanService::scan($ownerKey, $scope, $locale);
        if (empty($scanResults['scan_ok'])) {
            $err = $scanResults['error'] ?? 'Re-scan failed';
            return ['success' => false, 'error' => $err];
        }

        $findings = $scanResults['findings'] ?? [];
        if ($findingIndex < 0 || $findingIndex >= count($findings)) {
            return ['success' => false, 'error' => 'Finding index out of range after re-scan.'];
        }

        $finding = $findings[$findingIndex];

        if (empty($finding['extractable'])) {
            return ['success' => false, 'error' => 'This finding is not extractable.'];
        }

        if ($finding['type'] !== 'inline_text') {
            return ['success' => false, 'error' => 'Only inline text findings can be extracted.'];
        }

        $detected = (string)($finding['detected'] ?? '');
        $file = (string)($finding['file'] ?? '');
        if ($detected === '' || $file === '') {
            return ['success' => false, 'error' => 'Finding is missing detected text or source file.'];
        }

        $sourcePath = self::resolveSourcePath($file, $ownerKey);
        if ($sourcePath === null || !is_file($sourcePath)) {
            return ['success' => false, 'error' => 'Source file not found: ' . $file];
        }

        $key = (string)($finding['suggested_key'] ?? '');
        if ($key === '') {
            $key = self::generateSuggestedKey($detected, $ownerKey, $file);
        }

        $existing = [];
        if (is_file($enPath)) {
            $loaded = require $enPath;
            if (is_array($loaded)) {
                $existing = $loaded;
            }
        }

        if (array_key_exists($key, $existing)) {
            $proposedKey = $key . '_' . mb_strtolower(preg_replace('/[^A-Za-z0-9]/', '_', mb_substr($detected, 0, 16)));
            $proposedKey = preg_replace('/_+/', '_', $proposedKey);
            $proposedKey = trim($proposedKey, '_');
            if (!array_key_exists($proposedKey, $existing)) {
                $key = $proposedKey;
            } else {
                $key = $key . '_' . time();
            }
        }

        $replacement = self::determineReplacement($sourcePath, (int)($finding['line'] ?? 0), $detected, $key);
        if ($replacement === null) {
            return ['success' => false, 'error' => 'Cannot determine safe replacement context for: ' . $detected];
        }

        $fullContent = file_get_contents($sourcePath);
        if ($fullContent === false) {
            return ['success' => false, 'error' => 'Failed to read source file.'];
        }

        $search = $replacement['search'];
        $replace = $replacement['replace'];
        $newContent = str_replace($search, $replace, $fullContent, $count);

        if ($count !== 1) {
            return ['success' => false, 'error' => "Expected exactly 1 occurrence of pattern, found {$count}. Aborting to avoid unintended changes."];
        }

        if ($newContent === $fullContent) {
            return ['success' => false, 'error' => 'Replacement produced no change.'];
        }

        $existing[$key] = $detected;

        $snapshotEn = self::snapshotFile($enPath, 'extract-locale', $ownerKey);
        $snapshotSource = self::snapshotFile($sourcePath, 'extract-source', $ownerKey);

        if ($snapshotEn === null && is_file($enPath)) {
            return ['success' => false, 'error' => 'Locale file snapshot failed before write.'];
        }

        ksort($existing, SORT_STRING);
        $written = self::writeLocaleFile($enPath, $existing);
        if (!$written) {
            return ['success' => false, 'error' => 'Failed to write locale file.'];
        }

        $sourceDir = dirname($sourcePath);
        if (!is_dir($sourceDir)) {
            @mkdir($sourceDir, 0755, true);
        }
        $tmpPath = $sourcePath . '.tmp.' . getmypid();
        $writtenBytes = @file_put_contents($tmpPath, $newContent);
        if ($writtenBytes === false) {
            @unlink($tmpPath);
            return ['success' => false, 'error' => 'Failed to write updated source file.'];
        }
        if (!@rename($tmpPath, $sourcePath)) {
            @unlink($tmpPath);
            return ['success' => false, 'error' => 'Failed to replace source file.'];
        }

        return [
            'success' => true,
            'key' => $key,
            'value' => $detected,
            'file' => $file,
            'source_snapshot' => $snapshotSource,
            'locale_snapshot' => $snapshotEn,
        ];
    }

    public static function addMissingEnglishKeysSelected(string $ownerKey, string $scope, string $locale, array $selectedKeys): array
    {
        if ($locale !== 'en') {
            return ['success' => false, 'error' => 'Missing key addition is only supported for English (en) locale.'];
        }

        if ($selectedKeys === []) {
            return ['success' => false, 'error' => 'No keys selected for addition.'];
        }

        $enPath = self::resolveLocalePath($ownerKey, 'en');
        if ($enPath === null) {
            return ['success' => false, 'error' => 'Could not resolve en.php path for owner: ' . $ownerKey];
        }

        $scanResults = LocalizationScanService::scan($ownerKey, $scope, $locale);
        if (empty($scanResults['scan_ok'])) {
            $err = $scanResults['error'] ?? 'Re-scan failed';
            return ['success' => false, 'error' => $err];
        }

        $reviewPlan = $scanResults['missing_key_review_plan'] ?? [];
        $rows = $reviewPlan['rows'] ?? [];
        if ($rows === []) {
            return ['success' => false, 'error' => 'No missing owner keys found in re-scan.'];
        }

        $selectedKeysIndexed = array_flip($selectedKeys);

        $existing = [];
        if (is_file($enPath)) {
            $loaded = require $enPath;
            if (is_array($loaded)) {
                $existing = $loaded;
            }
        }

        $added = [];
        $skipped = 0;
        foreach ($rows as $row) {
            $key = (string)($row['key'] ?? '');
            if ($key === '') {
                continue;
            }
            if (!isset($selectedKeysIndexed[$key])) {
                continue;
            }
            if (array_key_exists($key, $existing)) {
                $skipped++;
                continue;
            }
            $value = self::computeEnglishValue($key, $row);
            $existing[$key] = $value;
            $added[] = ['key' => $key, 'value' => $value];
        }

        if ($added === []) {
            return ['success' => true, 'added_count' => 0, 'skipped' => $skipped, 'message' => 'No selected keys were new. They may already exist.'];
        }

        ksort($existing, SORT_STRING);

        $snapshotPath = self::snapshotFile($enPath, 'add-selected-keys', $ownerKey);
        if ($snapshotPath === null) {
            return ['success' => false, 'error' => 'Snapshot failed before write.'];
        }

        $written = self::writeLocaleFile($enPath, $existing);
        if (!$written) {
            return ['success' => false, 'error' => 'Failed to write locale file.'];
        }

        return [
            'success' => true,
            'added_count' => count($added),
            'skipped' => $skipped,
            'added' => $added,
            'snapshot' => $snapshotPath,
        ];
    }

    public static function addMissingEnglishKeysSelectedWithReport(string $ownerKey, string $scope, string $locale, array $selectedKeys): CorrectionReport
    {
        $beforeDiags = self::performReScan($ownerKey, $scope, $locale);
        $beforePending = $beforeDiags['pending_missing_keys'] ?? null;

        $raw = self::addMissingEnglishKeysSelected($ownerKey, $scope, $locale, $selectedKeys);

        if (!empty($raw['success'])) {
            $enPath = self::resolveLocalePath($ownerKey, 'en');
            $integrityError = null;
            if ($enPath !== null) {
                $integrityCheck = FileIntegrityValidator::validatePhpSyntaxOnLocaleKeys($enPath);
                if (!$integrityCheck['valid']) {
                    $integrityError = $integrityCheck['error'] ?? 'File integrity check failed after write';
                }
            }
            $diags = self::performReScan($ownerKey, $scope, $locale);
            $afterPending = $diags['pending_missing_keys'] ?? null;
            $diags['before_pending_missing_keys'] = $beforePending;
            $diags['after_pending_missing_keys'] = $afterPending;
            $reScanConfirmed = $beforePending !== null && $afterPending !== null && $afterPending < $beforePending;
            if ($integrityError !== null) {
                return new CorrectionReport(
                    action: 'add_selected_keys',
                    ownerKey: $ownerKey,
                    locale: $locale,
                    success: false,
                    addedCount: 0,
                    skippedCount: 0,
                    addedKeys: [],
                    message: null,
                    error: $integrityError,
                    snapshotPaths: ['locale' => $raw['snapshot'] ?? null],
                    reScanDiagnostics: $diags,
                    rollbackStatus: null,
                    reScanConfirmed: false,
                );
            }
            return new CorrectionReport(
                action: 'add_selected_keys',
                ownerKey: $ownerKey,
                locale: $locale,
                success: true,
                addedCount: (int)($raw['added_count'] ?? 0),
                skippedCount: (int)($raw['skipped'] ?? 0),
                addedKeys: (array)($raw['added'] ?? []),
                message: $raw['message'] ?? null,
                error: null,
                snapshotPaths: ['locale' => $raw['snapshot'] ?? null],
                reScanDiagnostics: $diags,
                rollbackStatus: null,
                reScanConfirmed: $reScanConfirmed,
            );
        }

        $usedSnapshot = null;
        if (!empty($raw['snapshot'])) {
            $usedSnapshot = $raw['snapshot'];
        }

        return new CorrectionReport(
            action: 'add_selected_keys',
            ownerKey: $ownerKey,
            locale: $locale,
            success: false,
            addedCount: 0,
            skippedCount: 0,
            addedKeys: [],
            message: null,
            error: $raw['error'] ?? 'Unknown error',
            snapshotPaths: ['locale' => $usedSnapshot],
            reScanDiagnostics: $beforeDiags,
            rollbackStatus: null,
        );
    }

    public static function addMissingEnglishKeysWithReport(string $ownerKey, string $scope, string $locale): CorrectionReport
    {
        $beforeDiags = self::performReScan($ownerKey, $scope, $locale);
        $beforePending = $beforeDiags['pending_missing_keys'] ?? null;

        $raw = self::addMissingEnglishKeys($ownerKey, $scope, $locale);

        if (!empty($raw['success'])) {
            $enPath = self::resolveLocalePath($ownerKey, 'en');
            $integrityError = null;
            if ($enPath !== null) {
                $integrityCheck = FileIntegrityValidator::validatePhpSyntaxOnLocaleKeys($enPath);
                if (!$integrityCheck['valid']) {
                    $integrityError = $integrityCheck['error'] ?? 'File integrity check failed after write';
                }
            }
            $diags = self::performReScan($ownerKey, $scope, $locale);
            $afterPending = $diags['pending_missing_keys'] ?? null;
            $diags['before_pending_missing_keys'] = $beforePending;
            $diags['after_pending_missing_keys'] = $afterPending;
            $reScanConfirmed = $beforePending !== null && $afterPending !== null && $afterPending < $beforePending;
            if ($integrityError !== null) {
                return new CorrectionReport(
                    action: 'add_missing_keys',
                    ownerKey: $ownerKey,
                    locale: $locale,
                    success: false,
                    addedCount: 0,
                    skippedCount: 0,
                    addedKeys: [],
                    message: null,
                    error: $integrityError,
                    snapshotPaths: ['locale' => $raw['snapshot'] ?? null],
                    reScanDiagnostics: $diags,
                    rollbackStatus: null,
                    reScanConfirmed: false,
                );
            }
            return new CorrectionReport(
                action: 'add_missing_keys',
                ownerKey: $ownerKey,
                locale: $locale,
                success: true,
                addedCount: (int)($raw['added_count'] ?? 0),
                skippedCount: (int)($raw['skipped'] ?? 0),
                addedKeys: (array)($raw['added'] ?? []),
                message: $raw['message'] ?? null,
                error: null,
                snapshotPaths: ['locale' => $raw['snapshot'] ?? null],
                reScanDiagnostics: $diags,
                rollbackStatus: null,
                reScanConfirmed: $reScanConfirmed,
            );
        }

        $usedSnapshot = null;
        if (!empty($raw['snapshot'])) {
            $usedSnapshot = $raw['snapshot'];
        }

        return new CorrectionReport(
            action: 'add_missing_keys',
            ownerKey: $ownerKey,
            locale: $locale,
            success: false,
            addedCount: 0,
            skippedCount: 0,
            addedKeys: [],
            message: null,
            error: $raw['error'] ?? 'Unknown error',
            snapshotPaths: ['locale' => $usedSnapshot],
            reScanDiagnostics: $beforeDiags,
            rollbackStatus: null,
        );
    }

    public static function extractInlineTextWithReport(string $ownerKey, int $findingIndex, string $scope, string $locale): CorrectionReport
    {
        $beforeDiags = self::performReScan($ownerKey, $scope, $locale);
        $beforeInline = $beforeDiags['inline_text'] ?? null;

        $raw = self::extractInlineText($ownerKey, $findingIndex, $scope, $locale);

        if (!empty($raw['success'])) {
            $enPath = self::resolveLocalePath($ownerKey, 'en');
            $integrityError = null;
            if ($enPath !== null) {
                $integrityCheck = FileIntegrityValidator::validatePhpSyntaxOnLocaleKeys($enPath);
                if (!$integrityCheck['valid']) {
                    $integrityError = $integrityCheck['error'] ?? 'File integrity check failed after write';
                }
            }
            $sourcePath = self::resolveSourcePath($raw['file'] ?? '', $ownerKey);
            if ($sourcePath !== null && $integrityError === null) {
                $sourceIntegrity = FileIntegrityValidator::validateSourceFileIntegrity($sourcePath);
                if (!$sourceIntegrity['valid']) {
                    $integrityError = 'Source file integrity: ' . ($sourceIntegrity['error'] ?? 'unknown error');
                }
            }
            if ($integrityError !== null) {
                return new CorrectionReport(
                    action: 'extract_inline_text',
                    ownerKey: $ownerKey,
                    locale: $locale,
                    success: false,
                    addedCount: 0,
                    skippedCount: 0,
                    addedKeys: [],
                    message: null,
                    error: $integrityError,
                    snapshotPaths: [
                        'locale' => $raw['locale_snapshot'] ?? null,
                        'source' => $raw['source_snapshot'] ?? null,
                    ],
                    reScanDiagnostics: [],
                    rollbackStatus: null,
                    reScanConfirmed: false,
                );
            }
            $diags = self::performReScan($ownerKey, $scope, $locale);
            $afterInline = $diags['inline_text'] ?? null;
            $reScanConfirmed = $beforeInline !== null && $afterInline !== null && $afterInline < $beforeInline;
            return new CorrectionReport(
                action: 'extract_inline_text',
                ownerKey: $ownerKey,
                locale: $locale,
                success: true,
                addedCount: 1,
                skippedCount: 0,
                addedKeys: [['key' => $raw['key'] ?? '', 'value' => $raw['value'] ?? '']],
                message: 'Extracted: ' . ($raw['key'] ?? ''),
                error: null,
                snapshotPaths: [
                    'locale' => $raw['locale_snapshot'] ?? null,
                    'source' => $raw['source_snapshot'] ?? null,
                ],
                reScanDiagnostics: $diags,
                rollbackStatus: null,
                reScanConfirmed: $reScanConfirmed,
            );
        }

        $snapshots = [];
        if (!empty($raw['locale_snapshot'])) {
            $snapshots['locale'] = $raw['locale_snapshot'];
        }
        if (!empty($raw['source_snapshot'])) {
            $snapshots['source'] = $raw['source_snapshot'];
        }

        return new CorrectionReport(
            action: 'extract_inline_text',
            ownerKey: $ownerKey,
            locale: $locale,
            success: false,
            addedCount: 0,
            skippedCount: 0,
            addedKeys: [],
            message: null,
            error: $raw['error'] ?? 'Unknown error',
            snapshotPaths: $snapshots,
            reScanDiagnostics: [],
            rollbackStatus: null,
        );
    }

    private static function performReScan(string $ownerKey, string $scope, string $locale): array
    {
        try {
            $scanResults = LocalizationScanService::scan($ownerKey, $scope, $locale);
            if (empty($scanResults['scan_ok'])) {
                return ['scan_ok' => false, 'error' => $scanResults['error'] ?? 'Re-scan failed'];
            }

            $findings = $scanResults['findings'] ?? [];
            $inlineCount = 0;
            $missingCount = 0;
            $humanFacingCount = 0;
            foreach ($findings as $f) {
                $type = $f['type'] ?? '';
                if ($type === 'inline_text') {
                    $inlineCount++;
                } elseif ($type === 'missing_owner_key') {
                    $missingCount++;
                }
                if (!empty($f['human_facing'])) {
                    $humanFacingCount++;
                }
            }

            $reviewPlan = $scanResults['missing_key_review_plan'] ?? [];
            $pendingMissingKeys = count($reviewPlan['rows'] ?? []);

            return [
                'scan_ok' => true,
                'total_findings' => count($findings),
                'inline_text' => $inlineCount,
                'missing_owner_keys' => $missingCount,
                'human_facing' => $humanFacingCount,
                'pending_missing_keys' => $pendingMissingKeys,
                'all_clear' => $pendingMissingKeys === 0 && $inlineCount === 0,
            ];
        } catch (\Throwable $e) {
            return ['scan_ok' => false, 'error' => $e->getMessage()];
        }
    }

    private static function determineReplacement(string $sourcePath, int $lineNum, string $detected, string $key): ?array
    {
        $lines = file($sourcePath);
        if ($lines === false || $lineNum < 1 || $lineNum > count($lines)) {
            return null;
        }

        $line = $lines[$lineNum - 1];
        $search = null;
        $replace = null;

        if (str_contains($line, '>' . $detected . '<')) {
            $search = '>' . $detected . '<';
            $replace = '><?= e(t(\'' . $key . '\')) ?><';
        } elseif (str_contains($line, '"' . $detected . '"')) {
            $search = '"' . $detected . '"';
            $replace = '"<?= e(t(\'' . $key . '\')) ?>"';
        } elseif (str_contains($line, "'" . $detected . "'")) {
            $search = "'" . $detected . "'";
            $replace = "'<?= e(t('" . $key . "')) ?>'";
        } else {
            $count = 0;
            $fullContent = file_get_contents($sourcePath);
            if ($fullContent === false) {
                return null;
            }
            $tryReplace = '>' . $detected . '<';
            $found = substr_count($fullContent, $tryReplace);
            if ($found === 1) {
                $search = $tryReplace;
                $replace = '><?= e(t(\'' . $key . '\')) ?><';
            } else {
                $tryReplace = '"' . $detected . '"';
                $found = substr_count($fullContent, $tryReplace);
                if ($found === 1) {
                    $search = $tryReplace;
                    $replace = '"<?= e(t(\'' . $key . '\')) ?>"';
                }
            }
            if ($search === null) {
                return null;
            }
        }

        return ['search' => $search, 'replace' => $replace];
    }

    private static function computeEnglishValue(string $key, array $row): string
    {
        $suggested = (string)($row['suggested_english_value'] ?? '');
        if ($suggested !== '') {
            return $suggested;
        }

        $parts = explode('.', $key);
        $tail = end($parts);

        if (isset(self::OVERRIDE_SUGGESTIONS[$tail])) {
            return self::OVERRIDE_SUGGESTIONS[$tail];
        }

        return self::humanizeKeyTail($tail);
    }

    private static function humanizeKeyTail(string $tail): string
    {
        $tail = preg_replace('/^[0-9_]+/', '', $tail);
        $tail = preg_replace('/_+/', ' ', $tail);
        $tail = preg_replace('/\s+/', ' ', $tail);
        $tail = trim($tail);

        $tail = preg_replace('/\bqty\b/i', 'Qty', $tail);
        $tail = preg_replace('/\bpct\b/i', '%', $tail);
        $tail = preg_replace('/\bdesc\b/i', 'Description', $tail);
        $tail = preg_replace('/\bid\b/i', 'ID', $tail);
        $tail = preg_replace('/\burl\b/i', 'URL', $tail);
        $tail = preg_replace('/\bemail\b/i', 'Email', $tail);
        $tail = preg_replace('/(\d)(day|month|year)/i', '$1 $2', $tail);

        $tail = mb_convert_case($tail, MB_CASE_TITLE, 'UTF-8');

        if (preg_match('/^[A-Za-z]+$/', $tail) && strlen($tail) <= 3) {
            $tail = mb_strtoupper($tail, 'UTF-8');
        }

        return $tail;
    }

    private static function generateSuggestedKey(string $text, string $ownerKey, string $file): string
    {
        $safe = mb_strtolower(trim($text), 'UTF-8');
        $safe = preg_replace('/[^a-z0-9]+/', '_', $safe);
        $safe = trim($safe, '_');
        $safe = mb_substr($safe, 0, 40);

        $prefix = str_replace('/', '.', $ownerKey);
        $prefix = mb_strtolower($prefix, 'UTF-8');
        $prefix = preg_replace('/[^a-z0-9.]+/', '.', $prefix);
        $prefix = trim($prefix, '.');

        return $prefix . '.' . $safe;
    }

    private static function snapshotFile(string $path, string $label, ?string $ownerKey = null): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        $baseDir = APP_ROOT . '/storage/studio-snapshots/localization-scan-extraction';

        // Use owner subdirectory so RollbackService can infer the target owner
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

    private static function resolveLangDir(string $ownerKey): ?string
    {
        $base = self::resolveOwnerPath($ownerKey);
        if ($base === null) {
            return null;
        }
        return $base . '/Resources/lang';
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
        return APP_ROOT . '/apps/' . $remainder;
    }
}

<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\CustomizationStudio\DesignSystem\DesignTokenEditor\Services;

final class CssTokenEditorSaveService
{
    private const BACKUP_DIR = '/storage/css_token_editor_backups';
    private const SOURCE_ROOT = '/resources/themes';
    private const THEME_CSS_PATH = '/public/assets/theme.css';
    private const THEME_COMPILER_SCRIPT = '/scripts/assets/compile_theme_sources.php';

    private const COMPOUND_SEPARATOR = '::';

    /**
     * @return array<string,mixed>
     */
    public static function save(array $payload): array
    {
        $compoundKey = trim((string)($payload['selector_key'] ?? ''));
        $sourcePath = trim((string)($payload['source_path'] ?? ''));
        $tokens = isset($payload['tokens']) && is_array($payload['tokens'])
            ? $payload['tokens']
            : [];
        $mode = strtolower(trim((string)($payload['mode'] ?? 'simple')));
        $safetyOverride = trim((string)($payload['safety_override'] ?? ''));

        if ($compoundKey === '') {
            return ['ok' => false, 'errors' => ['selector_required']];
        }

        if ($tokens === []) {
            return ['ok' => false, 'errors' => ['tokens_required']];
        }

        if ($sourcePath === '') {
            return ['ok' => false, 'errors' => ['source_path_required']];
        }

        $sourceAbsolutePath = self::resolveSourceAbsolutePath($sourcePath);
        if ($sourceAbsolutePath === null) {
            return ['ok' => false, 'errors' => ['source_path_invalid']];
        }

        if (!is_file($sourceAbsolutePath)) {
            return ['ok' => false, 'errors' => ['source_file_not_found']];
        }

        $css = @file_get_contents($sourceAbsolutePath);
        if (!is_string($css) || $css === '') {
            return ['ok' => false, 'errors' => ['source_file_unreadable']];
        }

        $parsed = self::parseCompoundKey($compoundKey);
        if ($parsed === null) {
            return ['ok' => false, 'errors' => ['selector_invalid']];
        }

        $selectors = self::parseSelectors($css);
        $selectorData = self::findByCompoundKey($selectors, $parsed['selector'], $parsed['index']);

        if ($selectorData === null) {
            return ['ok' => false, 'errors' => ['selector_not_found']];
        }

        $changedTokens = [];
        $errors = [];
        foreach ($tokens as $tokenName => $newValue) {
            $normalizedName = strtolower(trim((string)$tokenName));
            if ($normalizedName === '') {
                continue;
            }

            $tokenKey = '--' . $normalizedName;
            $currentValue = trim((string)($selectorData['tokens'][$normalizedName] ?? ''));

            if ($currentValue === '') {
                $errors[] = [
                    'key' => 'token_not_found',
                    'token' => $tokenKey,
                ];
                continue;
            }

            $safeValue = self::validateValue((string)$newValue);
            if ($safeValue === null) {
                $errors[] = [
                    'key' => 'value_invalid',
                    'token' => $tokenKey,
                ];
                continue;
            }

            if ($safeValue !== $currentValue) {
                $changedTokens[$normalizedName] = $safeValue;
            }
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        if ($changedTokens === []) {
            return ['ok' => true, 'message_key' => 'no_changes', 'changes' => 0];
        }

        $effectiveValues = $selectorData['tokens'];
        foreach ($changedTokens as $name => $value) {
            $effectiveValues[$name] = $value;
        }

        $baselineSafety = self::evaluateSafety($selectorData['tokens']);
        $safety = self::evaluateSafety($effectiveValues);
        $hasNewSevere = self::hasNewSevereIssues($baselineSafety, $safety);
        if ($hasNewSevere && !($mode === 'advanced' && $safetyOverride === '1')) {
            return ['ok' => false, 'errors' => ['save_error_safety_severe']];
        }

        $backupResult = self::createBackup($css, $sourcePath);
        if (!$backupResult['ok']) {
            return ['ok' => false, 'errors' => ['backup_failed']];
        }

        $updatedCss = self::applyChanges($css, $selectorData, $changedTokens);
        if ($updatedCss === null) {
            return ['ok' => false, 'errors' => ['apply_failed']];
        }

        $written = @file_put_contents($sourceAbsolutePath, $updatedCss);
        if ($written === false) {
            return ['ok' => false, 'errors' => ['write_failed']];
        }

        $compileResult = self::compileThemeRuntime();
        if (!$compileResult['ok']) {
            @file_put_contents($sourceAbsolutePath, $css);
            return ['ok' => false, 'errors' => ['compile_failed']];
        }

        $runtimeThemePath = APP_ROOT . self::THEME_CSS_PATH;
        if (!self::runtimeHasUpdatedTokens($runtimeThemePath, $changedTokens)) {
            @file_put_contents($sourceAbsolutePath, $css);
            self::compileThemeRuntime();
            return ['ok' => false, 'errors' => ['runtime_verify_failed']];
        }

        return [
            'ok' => true,
            'message_key' => 'saved',
            'changes' => count($changedTokens),
            'backup' => $backupResult['path'],
            'source_path' => $sourcePath,
            'changed_tokens' => array_keys($changedTokens),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function verify(array $payload): array
    {
        $compoundKey = trim((string)($payload['selector_key'] ?? ''));
        $sourcePath = trim((string)($payload['source_path'] ?? ''));
        $tokens = isset($payload['tokens']) && is_array($payload['tokens'])
            ? $payload['tokens']
            : [];

        if ($sourcePath === '') {
            return ['ok' => false, 'errors' => ['source_path_required']];
        }

        $sourceAbsolutePath = self::resolveSourceAbsolutePath($sourcePath);
        if ($sourceAbsolutePath === null) {
            return ['ok' => false, 'errors' => ['source_path_invalid']];
        }

        if (!is_file($sourceAbsolutePath)) {
            return ['ok' => false, 'errors' => ['source_file_not_found']];
        }

        $css = @file_get_contents($sourceAbsolutePath);
        if (!is_string($css) || $css === '') {
            return ['ok' => false, 'errors' => ['source_file_unreadable']];
        }

        $selectors = self::parseSelectors($css);

        $parsed = self::parseCompoundKey($compoundKey);
        $selectorData = null;
        if ($parsed !== null) {
            $selectorData = self::findByCompoundKey($selectors, $parsed['selector'], $parsed['index']);
        }

        $results = [];
        foreach ($tokens as $tokenName => $newValue) {
            $normalizedName = strtolower(trim((string)$tokenName));
            if ($normalizedName === '') {
                continue;
            }

            $tokenKey = '--' . $normalizedName;
            $currentValue = trim((string)($selectorData['tokens'][$normalizedName] ?? ''));

            $safeValue = self::validateValue((string)$newValue);

            $results[] = [
                'token' => $tokenKey,
                'current_value' => $currentValue,
                'new_value' => (string)($safeValue ?? $newValue),
                'selector_exists' => $selectorData !== null,
                'token_exists' => $currentValue !== '',
                'value_valid' => $safeValue !== null,
                'changed' => $safeValue !== null && $safeValue !== $currentValue,
            ];
        }

        return [
            'ok' => true,
            'results' => $results,
            'all_valid' => self::allValid($results),
        ];
    }

    /**
     * @param array<string,string> $payload
     * @return array<string,mixed>
     */
    public static function preview(array $payload): array
    {
        $selectorKey = trim((string)($payload['selector_key'] ?? ''));
        $tokens = isset($payload['tokens']) && is_array($payload['tokens'])
            ? $payload['tokens']
            : [];

        return [
            'ok' => true,
            'selector_key' => $selectorKey,
            'tokens' => $tokens,
            'css_preview' => self::buildPreviewCss($selectorKey, $tokens),
        ];
    }

    /**
     * @return array{ok:bool,path?:string}
     */
    private static function createBackup(string $css, string $sourcePath): array
    {
        $backupDir = APP_ROOT . self::BACKUP_DIR;
        if (!is_dir($backupDir)) {
            $created = @mkdir($backupDir, 0755, true);
            if (!$created) {
                return ['ok' => false];
            }
        }

        $timestamp = date('Ymd_His');
        $relative = trim(str_replace('\\', '/', $sourcePath), '/');
        $slug = preg_replace('/[^a-z0-9._-]+/i', '_', $relative);
        $slug = trim((string)$slug, '_');
        if ($slug === '') {
            $slug = 'theme_source';
        }
        $backupPath = $backupDir . '/source_' . $timestamp . '_' . $slug . '.css';

        $written = @file_put_contents($backupPath, $css);
        if ($written === false) {
            return ['ok' => false];
        }

        return ['ok' => true, 'path' => self::BACKUP_DIR . '/source_' . $timestamp . '_' . $slug . '.css'];
    }

    private static function resolveSourceAbsolutePath(string $sourcePath): ?string
    {
        $normalized = trim(str_replace('\\', '/', $sourcePath));
        if ($normalized === '') {
            return null;
        }

        $normalized = ltrim($normalized, '/');
        if (!str_starts_with($normalized, 'resources/themes/')) {
            return null;
        }
        if (str_contains($normalized, '..')) {
            return null;
        }
        if (!str_ends_with(strtolower($normalized), '.css')) {
            return null;
        }

        $absolute = APP_ROOT . '/' . $normalized;
        $root = realpath(APP_ROOT . self::SOURCE_ROOT);
        $resolved = realpath($absolute);
        if (!is_string($root) || !is_string($resolved)) {
            return null;
        }

        $rootNorm = rtrim(str_replace('\\', '/', $root), '/') . '/';
        $resolvedNorm = str_replace('\\', '/', $resolved);
        if (!str_starts_with($resolvedNorm, $rootNorm)) {
            return null;
        }

        return $resolved;
    }

    /**
     * @return array{ok:bool,output:string}
     */
    private static function compileThemeRuntime(): array
    {
        $scriptPath = APP_ROOT . self::THEME_COMPILER_SCRIPT;
        if (!is_file($scriptPath)) {
            return ['ok' => false, 'output' => 'compiler_not_found'];
        }

        $phpBin = defined('PHP_BINARY') && is_string(PHP_BINARY) && PHP_BINARY !== ''
            ? PHP_BINARY
            : '/opt/homebrew/bin/php';

        $command = escapeshellarg($phpBin)
            . ' ' . escapeshellarg($scriptPath)
            . ' --apply --json 2>&1';

        $output = [];
        $exitCode = 1;
        @exec($command, $output, $exitCode);
        $stdout = trim(implode("\n", $output));
        if ($exitCode !== 0) {
            return ['ok' => false, 'output' => $stdout];
        }

        if ($stdout !== '') {
            $decoded = json_decode($stdout, true);
            if (is_array($decoded) && empty($decoded['ok'])) {
                return ['ok' => false, 'output' => $stdout];
            }
        }

        return ['ok' => true, 'output' => $stdout];
    }

    /**
     * @param array<string,string> $changedTokens
     */
    private static function runtimeHasUpdatedTokens(string $runtimeThemePath, array $changedTokens): bool
    {
        if (!is_file($runtimeThemePath)) {
            return false;
        }

        $runtimeCss = @file_get_contents($runtimeThemePath);
        if (!is_string($runtimeCss) || $runtimeCss === '') {
            return false;
        }

        foreach ($changedTokens as $tokenName => $value) {
            $pattern = '/--' . preg_quote($tokenName, '/') . '\s*:\s*' . preg_quote(trim($value), '/') . '\s*;/i';
            if (preg_match($pattern, $runtimeCss) !== 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int,array{raw_selector:string,compound_key:string,tokens:array<string,string>,start:int,end:int,body:string,full_match:string,block_index:int}>
     */
    private static function parseSelectors(string $css): array
    {
        $selectors = [];
        $pattern = '/(*NO_JIT)(?P<selector>(?::root|\[[^\]]+\](?:\[[^\]]+\])?))\s*\{(?P<body>(?:[^{}]|(?:\{[^{}]*\}))*)\}/s';

        if (preg_match_all($pattern, $css, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === false) {
            return [];
        }

        $countsBySelector = [];
        foreach ($matches as $match) {
            $selector = trim((string)($match['selector'][0] ?? ''));
            $body = (string)($match['body'][0] ?? '');
            $startOffset = (int)($match[0][1] ?? 0);

            if ($selector === '' || $body === '') {
                continue;
            }

            if (str_starts_with($selector, '@supports')) {
                continue;
            }

            $normalizedSelector = strtolower(trim(preg_replace('/\s+/', ' ', $selector)));
            if (!isset($countsBySelector[$normalizedSelector])) {
                $countsBySelector[$normalizedSelector] = 0;
            }
            $countsBySelector[$normalizedSelector]++;
            $blockIndex = $countsBySelector[$normalizedSelector];

            $tokenMap = [];
            $tokenMatches = [];
            preg_match_all('/--([a-z0-9_-]+)\s*:\s*([^;]+);/i', $body, $tokenMatches, PREG_SET_ORDER);
            foreach ($tokenMatches as $tokenMatch) {
                $tokenName = strtolower(trim((string)($tokenMatch[1] ?? '')));
                $tokenValue = trim((string)($tokenMatch[2] ?? ''));
                if ($tokenName !== '' && $tokenValue !== '') {
                    $tokenMap[$tokenName] = $tokenValue;
                }
            }

            if ($tokenMap === []) {
                continue;
            }

            $endOffset = $startOffset + strlen((string)($match[0][0] ?? ''));
            $fullMatch = (string)($match[0][0] ?? '');

            $selectors[] = [
                'raw_selector' => $selector,
                'compound_key' => $normalizedSelector . self::COMPOUND_SEPARATOR . $blockIndex,
                'tokens' => $tokenMap,
                'start' => $startOffset,
                'end' => $endOffset,
                'body' => $body,
                'full_match' => $fullMatch,
                'block_index' => $blockIndex,
            ];
        }

        return $selectors;
    }

    /**
     * @param array<int,array<string,mixed>> $selectors
     * @return array<string,mixed>|null
     */
    private static function findByCompoundKey(array $selectors, string $targetSelector, int $targetIndex): ?array
    {
        $target = strtolower(trim(preg_replace('/\s+/', ' ', $targetSelector)));
        foreach ($selectors as $s) {
            $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $s['raw_selector'])));
            $index = (int)($s['block_index'] ?? 1);
            if ($normalized === $target && $index === $targetIndex) {
                return $s;
            }
        }
        return null;
    }

    /**
     * @return array{selector:string,index:int}|null
     */
    private static function parseCompoundKey(string $compoundKey): ?array
    {
        $parts = explode(self::COMPOUND_SEPARATOR, $compoundKey, 2);
        if (count($parts) !== 2) {
            return null;
        }

        $selectorPart = trim($parts[0]);
        if ($selectorPart === '') {
            return null;
        }
        if (str_contains($selectorPart, '|')) {
            $selectorBits = explode('|', $selectorPart, 2);
            $selectorPart = trim((string)($selectorBits[1] ?? ''));
        }

        $selector = $selectorPart;
        $index = (int)$parts[1];

        if ($selector === '' || $index < 1) {
            return null;
        }

        return ['selector' => $selector, 'index' => $index];
    }

    /**
     * @param array<string,string> $changedTokens
     */
    private static function applyChanges(string $css, array $selectorData, array $changedTokens): ?string
    {
        $fullMatch = (string)($selectorData['full_match'] ?? '');
        if ($fullMatch === '') {
            return null;
        }

        $modified = $fullMatch;

        foreach ($changedTokens as $tokenName => $newValue) {
            $escapedToken = preg_quote($tokenName, '/');
            $pattern = '/(' . $escapedToken . '\s*:\s*)[^;]+;/i';
            $replacement = '$1' . $newValue . ';';
            $modified = preg_replace($pattern, $replacement, $modified, 1, $count);
            if ($count !== 1) {
                return null;
            }
        }

        return str_replace($fullMatch, $modified, $css);
    }

    private static function validateValue(string $value): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/[{}<>]/', $trimmed) === 1) {
            return null;
        }

        if (preg_match('/expression\s*\(/i', $trimmed) === 1) {
            return null;
        }

        if (preg_match('/javascript\s*:/i', $trimmed) === 1) {
            return null;
        }

        if (preg_match('/url\s*\([^)]*\)/i', $trimmed) === 1) {
            if (preg_match('/url\s*\(\s*["\']?data:/i', $trimmed) !== 1) {
                if (preg_match('/url\s*\(\s*["\']?data:/i', $trimmed) !== 1) {
                    return null;
                }
            }
        }

        if (strlen($trimmed) > 4096) {
            return null;
        }

        return $trimmed;
    }

    /**
     * @param array<string,string> $tokens
     */
    private static function buildPreviewCss(string $selectorKey, array $tokens): string
    {
        $lines = [];
        $lines[] = $selectorKey . ' {';
        foreach ($tokens as $name => $value) {
            $safeName = trim((string)$name);
            $safeValue = trim((string)$value);
            if ($safeName !== '' && $safeValue !== '') {
                $lines[] = '  --' . $safeName . ': ' . $safeValue . ';';
            }
        }
        $lines[] = '}';
        return implode("\n", $lines);
    }

    /**
     * @param array<int,array<string,mixed>> $verificationResults
     */
    private static function allValid(array $verificationResults): bool
    {
        foreach ($verificationResults as $r) {
            if (!$r['selector_exists'] || !$r['token_exists'] || !$r['value_valid']) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array<string,string> $values
     * @return array{overall:string,issues:array<int,array<string,mixed>>}
     */
    private static function evaluateSafety(array $values): array
    {
        $issues = [];
        $overall = 'ok';
        $seen = [];

        foreach (self::safetyPairs($values) as $pair) {
            $sourceToken = (string)($pair['source'] ?? '');
            $bgToken = (string)($pair['bg'] ?? '');
            if ($sourceToken === '' || $bgToken === '') {
                continue;
            }
            if (isset($seen[$sourceToken])) {
                continue;
            }

            $sourceValue = trim((string)($values[$sourceToken] ?? ''));
            $bgValue = trim((string)($values[$bgToken] ?? ''));
            if ($sourceValue === '' || $bgValue === '') {
                continue;
            }

            $result = self::computeReadability($sourceValue, $bgValue, $values);
            $kind = (string)($pair['kind'] ?? 'text');
            $thresholds = self::safetyThresholds($kind);
            $status = 'warning';
            $ratio = $result['ratio'];

            if (($result['status'] ?? 'unknown') === 'good' && $ratio !== null) {
                $status = 'ok';
            } elseif (($result['status'] ?? 'unknown') === 'low' && $ratio !== null) {
                $status = $ratio >= $thresholds['warning'] ? 'warning' : 'severe';
            } else {
                $status = 'warning';
            }

            if ($status === 'severe') {
                $overall = 'severe';
            } elseif ($overall !== 'severe' && $status === 'warning') {
                $overall = 'warning';
            }

            $issues[] = [
                'token' => $sourceToken,
                'bg_token' => $bgToken,
                'kind' => $kind,
                'label' => (string)($pair['label'] ?? $sourceToken),
                'ratio' => $ratio,
                'status' => $status,
            ];
            $seen[$sourceToken] = true;
        }

        return ['overall' => $overall, 'issues' => $issues];
    }

    /**
     * @param array{overall:string,issues:array<int,array<string,mixed>>} $baseline
     * @param array{overall:string,issues:array<int,array<string,mixed>>} $current
     */
    private static function hasNewSevereIssues(array $baseline, array $current): bool
    {
        $baselineSevere = [];
        foreach (($baseline['issues'] ?? []) as $issue) {
            if (!is_array($issue) || (string)($issue['status'] ?? '') !== 'severe') {
                continue;
            }
            $token = (string)($issue['token'] ?? '');
            $bgToken = (string)($issue['bg_token'] ?? '');
            if ($token === '' || $bgToken === '') {
                continue;
            }
            $baselineSevere[$token . '|' . $bgToken] = true;
        }

        foreach (($current['issues'] ?? []) as $issue) {
            if (!is_array($issue) || (string)($issue['status'] ?? '') !== 'severe') {
                continue;
            }
            $token = (string)($issue['token'] ?? '');
            $bgToken = (string)($issue['bg_token'] ?? '');
            if ($token === '' || $bgToken === '') {
                continue;
            }
            $key = $token . '|' . $bgToken;
            if (!isset($baselineSevere[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,string> $values
     * @return array<int,array{source:string,bg:string,kind:string,label:string}>
     */
    private static function safetyPairs(array $values): array
    {
        $pairs = [];
        $hasBg = array_key_exists('bg', $values) ? 'bg' : (array_key_exists('background', $values) ? 'background' : null);

        if ($hasBg !== null) {
            $pairs[] = ['source' => 'text', 'bg' => $hasBg, 'kind' => 'text', 'label' => 'Text on Background'];
            $pairs[] = ['source' => 'muted', 'bg' => $hasBg, 'kind' => 'text', 'label' => 'Muted on Background'];
            $pairs[] = ['source' => 'accent', 'bg' => $hasBg, 'kind' => 'text', 'label' => 'Accent on Background'];
            $pairs[] = ['source' => 'color-text', 'bg' => 'background', 'kind' => 'text', 'label' => 'Text on Background'];
            $pairs[] = ['source' => 'color-text-muted', 'bg' => 'background', 'kind' => 'text', 'label' => 'Muted on Background'];
        }

        foreach ($values as $name => $value) {
            $normalized = strtolower(trim((string)$name));
            if ($normalized === '') {
                continue;
            }

            if (preg_match('/^tone-([a-z0-9_-]+)-text$/', $normalized, $m)) {
                $bg = 'tone-' . $m[1] . '-bg';
                if (array_key_exists($bg, $values)) {
                    $pairs[] = ['source' => $normalized, 'bg' => $bg, 'kind' => 'text', 'label' => ucfirst(str_replace('-', ' ', $m[1])) . ' tone'];
                }
                continue;
            }

            if (preg_match('/^notif-chip-([a-z0-9_-]+)-color$/', $normalized, $m)) {
                $bg = 'notif-chip-' . $m[1] . '-bg';
                if (array_key_exists($bg, $values)) {
                    $pairs[] = ['source' => $normalized, 'bg' => $bg, 'kind' => 'text', 'label' => 'Notif: ' . $m[1]];
                }
                continue;
            }

            if (preg_match('/^(success|warning|danger|info)$/', $normalized)) {
                $bg = array_key_exists($normalized . '-bg', $values) ? $normalized . '-bg' : $hasBg;
                if ($bg !== null) {
                    $pairs[] = ['source' => $normalized, 'bg' => $bg, 'kind' => 'status', 'label' => ucfirst($normalized) . ' state'];
                }
                continue;
            }

            if (preg_match('/^(.*)-(border|line|edge|focus|ring|outline)$/', $normalized, $m)) {
                if ($hasBg !== null) {
                    $pairs[] = ['source' => $normalized, 'bg' => $hasBg, 'kind' => 'border', 'label' => ucfirst(str_replace('-', ' ', $m[1])) . ' ' . $m[2]];
                }
            }
        }

        return $pairs;
    }

    /**
     * @return array{warning:float,severe:float}
     */
    private static function safetyThresholds(string $kind): array
    {
        if ($kind === 'border') {
            return ['warning' => 1.5, 'severe' => 1.2];
        }

        return ['warning' => 4.5, 'severe' => 3.0];
    }

    /**
     * @param array<string,string> $allValues
     * @return array{status:string,ratio:float|null}
     */
    private static function computeReadability(string $textValue, string $bgValue, array $allValues = []): array
    {
        $textColor = self::parseColor(self::resolveColorValue($textValue));
        $bgColor = self::parseColor(self::resolveColorValue($bgValue));

        if ($textColor === null || $bgColor === null) {
            return ['status' => 'unknown', 'ratio' => null];
        }

        $needsComposite = (isset($textColor['a']) && $textColor['a'] < 0.999)
            || (isset($bgColor['a']) && $bgColor['a'] < 0.999);

        if ($needsComposite && $allValues !== []) {
            $surfaceKey = array_key_exists('bg', $allValues) ? 'bg' : (array_key_exists('background', $allValues) ? 'background' : null);
            if ($surfaceKey !== null) {
                $surfaceValue = trim((string)($allValues[$surfaceKey] ?? ''));
                if ($surfaceValue !== '') {
                    $surfaceResolved = self::resolveColorValue($surfaceValue);
                    $surface = self::parseColor($surfaceResolved ?: $surfaceValue);
                    if ($surface !== null) {
                        if (isset($bgColor['a']) && $bgColor['a'] < 0.999) {
                            $bgColor = self::compositeOver($bgColor, $surface);
                        }
                        if (isset($textColor['a']) && $textColor['a'] < 0.999) {
                            $textColor = self::compositeOver($textColor, $surface);
                        }
                    }
                }
            }
        }

        $ratio = self::contrastRatio($textColor, $bgColor);
        if ($ratio >= 4.5) {
            return ['status' => 'good', 'ratio' => $ratio];
        }

        return ['status' => 'low', 'ratio' => $ratio];
    }

    private static function resolveColorValue(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        if (preg_match('/^var\(--([^,)]+)(?:\s*,\s*([^)]+))?\)$/', $trimmed, $matches)) {
            return trim((string)($matches[2] ?? ''));
        }

        return $trimmed;
    }

    /**
     * @return array{r:int,g:int,b:int,a?:float}|null
     */
    private static function parseColor(string $value): ?array
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value, $matches)) {
            $hex = strtolower($matches[1]);
            if (strlen($hex) === 3) {
                $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
            }
            if (strlen($hex) === 4) {
                $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2] . $hex[3] . $hex[3];
            }

            $result = [
                'r' => hexdec(substr($hex, 0, 2)),
                'g' => hexdec(substr($hex, 2, 2)),
                'b' => hexdec(substr($hex, 4, 2)),
            ];
            if (strlen($hex) === 8) {
                $result['a'] = hexdec(substr($hex, 6, 2)) / 255;
            }
            return $result;
        }

        if (preg_match('/^rgba?\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)(?:\s*,\s*([\d.]+))?\)/i', $value, $matches)) {
            return [
                'r' => (int)$matches[1],
                'g' => (int)$matches[2],
                'b' => (int)$matches[3],
                'a' => isset($matches[4]) ? (float)$matches[4] : 1.0,
            ];
        }

        return null;
    }

    /**
     * @param array{r:int,g:int,b:int,a?:float} $fg
     * @param array{r:int,g:int,b:int} $bg
     * @return array{r:int,g:int,b:int}
     */
    private static function compositeOver(array $fg, array $bg): array
    {
        $a = isset($fg['a']) ? $fg['a'] : 1.0;
        if ($a >= 0.999) {
            return ['r' => $fg['r'], 'g' => $fg['g'], 'b' => $fg['b']];
        }
        return [
            'r' => (int)round($a * $fg['r'] + (1 - $a) * $bg['r']),
            'g' => (int)round($a * $fg['g'] + (1 - $a) * $bg['g']),
            'b' => (int)round($a * $fg['b'] + (1 - $a) * $bg['b']),
        ];
    }

    private static function linearize(int $value): float
    {
        $scaled = $value / 255;
        if ($scaled <= 0.03928) {
            return $scaled / 12.92;
        }

        return pow(($scaled + 0.055) / 1.055, 2.4);
    }

    /**
     * @param array{r:int,g:int,b:int} $first
     * @param array{r:int,g:int,b:int} $second
     */
    private static function contrastRatio(array $first, array $second): float
    {
        $l1 = 0.2126 * self::linearize($first['r']) + 0.7152 * self::linearize($first['g']) + 0.0722 * self::linearize($first['b']);
        $l2 = 0.2126 * self::linearize($second['r']) + 0.7152 * self::linearize($second['g']) + 0.0722 * self::linearize($second['b']);
        $lighter = max($l1, $l2);
        $darker = min($l1, $l2);

        return ($lighter + 0.05) / ($darker + 0.05);
    }
}

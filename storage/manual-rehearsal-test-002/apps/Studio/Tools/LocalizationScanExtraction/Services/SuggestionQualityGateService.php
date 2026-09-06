<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\LocalizationScanExtraction\Services;

final class SuggestionQualityGateService
{
    private static ?array $canonicalEn = null;

    public static function canonicalEnglishValue(string $key): ?string
    {
        $key = trim($key);
        if ($key === '') {
            return null;
        }

        $values = self::canonicalEnglishValues();
        $value = $values[$key] ?? null;
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        return $value !== '' ? $value : null;
    }

    public static function intentionalTailValue(string $tail): ?string
    {
        $tail = strtolower(trim($tail));
        $map = [
            'btn_apply' => 'Apply',
            'btn_register' => 'Register',
            'mode_qc' => 'QC',
            'mode_dispatch' => 'Dispatch',
            'mode_operator' => 'Operator',
            'mode_read_only' => 'Read Only',
        ];

        return $map[$tail] ?? null;
    }

    public static function rejectionReason(string $key, string $value): string
    {
        $key = trim($key);
        $value = trim($value);
        $probe = $value !== '' ? $value : $key;

        if ($probe === '') {
            return 'empty_value';
        }

        if (self::containsCssFragment($probe) || self::containsCssFragment($key)) {
            return 'css_fragment';
        }

        if (self::containsHtmlTag($probe) || self::containsHtmlTag($key)) {
            return 'html_tag';
        }

        if (self::containsPathUrlOrQuery($probe) || self::containsPathUrlOrQuery($key)) {
            return 'path_url';
        }

        if (self::containsCodeFragment($probe) || self::containsCodeFragment($key)) {
            return 'expression_fragment';
        }

        if (self::containsAttributeFragment($probe) || self::containsAttributeFragment($key)) {
            return 'attribute_fragment';
        }

        if (self::isPlaceholderish($probe)) {
            return 'placeholder_value';
        }

        return '';
    }

    public static function generatedJunkReason(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'empty_value';
        }

        if (preg_match('/^Btn\s+[A-Z0-9]/', $value) === 1) {
            return 'identifier_derived_label';
        }

        if (preg_match('/^Mode\s+(Qc|Read Only|Admin|Dispatch|Operator)$/i', $value) === 1) {
            return 'identifier_derived_label';
        }

        if (preg_match('/\b(Title|Description|Text)$/', $value) === 1
            && preg_match('/\b(Module|Recover|Repair|Confirm|Sync|Default|Current|Owner|Role)\b/', $value) === 1) {
            return 'placeholder_value';
        }

        return '';
    }

    public static function readyBlockReason(string $key, string $value): string
    {
        $rejection = self::rejectionReason($key, $value);
        if ($rejection !== '') {
            return $rejection;
        }

        return self::generatedJunkReason($value);
    }

    private static function canonicalEnglishValues(): array
    {
        if (self::$canonicalEn !== null) {
            return self::$canonicalEn;
        }

        $path = (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 5)) . '/app/Locale/en.php';
        if (!is_file($path)) {
            self::$canonicalEn = [];
            return self::$canonicalEn;
        }

        $source = (string)file_get_contents($path);
        $values = [];
        if (preg_match_all('/^\s*([\'"])([^\'"]+)\1\s*=>\s*([\'"])((?:\\\\.|(?!\3).)*)\3\s*,/m', $source, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $values[(string)$match[2]] = stripcslashes((string)$match[4]);
            }
        }

        self::$canonicalEn = $values;
        return self::$canonicalEn;
    }

    private static function containsCssFragment(string $value): bool
    {
        return preg_match('/(?:^|[;{]\s*)(?:font(?:-size|-weight|-family)?|padding|margin|border(?:-radius)?|letter-spacing|text-transform|display|position|color|background)\s*:/i', $value) === 1
            || preg_match('/(?:;\s*(?:font|padding|border|text-transform)|\{|\})/i', $value) === 1;
    }

    private static function containsHtmlTag(string $value): bool
    {
        return preg_match('/<\s*\/?\s*[A-Za-z][^>]*>/', $value) === 1;
    }

    private static function containsPathUrlOrQuery(string $value): bool
    {
        $trimmed = trim($value);
        return preg_match('#(?:https?://|^//|^/apps/|^apps/|^/admin/|^admin/|^storage/|/storage/|^public/|^resources/|^vendor/|[?&][A-Za-z0-9_.-]+=|\.php(?:\b|$)|\.js(?:\b|$)|\.css(?:\b|$))#i', $trimmed) === 1;
    }

    private static function containsCodeFragment(string $value): bool
    {
        return preg_match('/(?:<\?=|<\?php|\$[A-Za-z_][A-Za-z0-9_]*|[\'"]\s*\+\s*[A-Za-z_$]|[A-Za-z_$][A-Za-z0-9_$]*\s*\+\s*[\'"]|=>|::|->|\{\{|\}\}|function\s*\(|return\s+)/i', $value) === 1;
    }

    private static function containsAttributeFragment(string $value): bool
    {
        return preg_match('/(?:^|\s)(?:class|id|style|href|src|data-[A-Za-z0-9_-]+|aria-[A-Za-z0-9_-]+|name|value|type|role)=["\'][^"\']*["\']/i', $value) === 1
            || preg_match('/^(?:class|id|style|href|src|data-[A-Za-z0-9_-]+|aria-[A-Za-z0-9_-]+)$/i', trim($value)) === 1;
    }

    private static function isPlaceholderish(string $value): bool
    {
        $lower = strtolower(trim($value));
        return in_array($lower, ['todo', 'tbd', 'n/a', 'na', 'null', 'undefined', 'placeholder', 'lorem ipsum', 'empty'], true);
    }
}

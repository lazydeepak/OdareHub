<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\LabelDesigner\Services;

/**
 * Read-only in-memory rule preview sandbox.
 *
 * Accepts a temporary rule definition (condition + effect),
 * validates inputs against the resolved context/template,
 * evaluates the condition against sample data,
 * and returns rule diagnostics + modified preview HTML.
 *
 * No file writes. No rule persistence. No runtime execution.
 */
final class LabelDesignerRuleSandboxService
{
    private const ALLOWED_OPERATORS = [
        'equals',
        'not_equals',
        'empty',
        'not_empty',
        'greater_than',
        'less_than',
        'contains',
    ];

    private const ALLOWED_EFFECTS = [
        'show_badge',
        'hide_field',
        'show_warning',
        'set_style_token',
    ];

    private const SEVERITY_RANK = [
        'PASS' => 0,
        'WARN' => 1,
        'FAIL' => 2,
        'ERROR' => 3,
    ];

    /**
     * Evaluate a temporary rule against the resolved label preview.
     *
     * @param array<string,mixed> $previewResult Result from LabelDesignerPreviewRendererService::buildResolvedPreview()
     * @param array<string,mixed> $rule Temporary rule: condition_field, operator, compare_value, effect_type, effect_target, effect_value
     * @return array<string,mixed> Rule diagnostics + modified preview html + condition/effect status
     */
    public static function evaluateTemporaryRule(array $previewResult, array $rule): array
    {
        $diagnostics = [];
        $renderAllowed = !empty($previewResult['render_allowed']);

        // Extract rule params
        $conditionField = trim((string)($rule['condition_field'] ?? ''));
        $operator = trim((string)($rule['operator'] ?? ''));
        $compareValue = trim((string)($rule['compare_value'] ?? ''));
        $effectType = trim((string)($rule['effect_type'] ?? ''));
        $effectTarget = trim((string)($rule['effect_target'] ?? ''));
        $effectValue = trim((string)($rule['effect_value'] ?? ''));

        // Validate condition field
        $resolvedFields = isset($previewResult['resolved_fields']) && is_array($previewResult['resolved_fields'])
            ? array_values(array_filter($previewResult['resolved_fields'], 'is_array'))
            : [];
        $sampleData = isset($previewResult['sample_data']) && is_array($previewResult['sample_data'])
            ? $previewResult['sample_data']
            : [];

        $fieldKeys = array_map(static fn (array $f): string => (string)($f['field_key'] ?? ''), $resolvedFields);
        $contextFieldExists = in_array($conditionField, $fieldKeys, true);

        // Validate operator
        $operatorValid = in_array($operator, self::ALLOWED_OPERATORS, true);

        // Validate effect type
        $effectTypeValid = in_array($effectType, self::ALLOWED_EFFECTS, true);

        // Validate effect target
        $effectTargetValid = self::validateEffectTarget($effectType, $effectTarget, $resolvedFields);

        // Build rule-level diagnostics
        $diagnostics[] = self::makeCheck('RS001', 'condition_field', 'Condition field in context', $contextFieldExists ? 'PASS' : 'FAIL', $contextFieldExists ? $conditionField : 'Unknown field: ' . $conditionField);
        $diagnostics[] = self::makeCheck('RS002', 'operator', 'Operator supported', $operatorValid ? 'PASS' : 'FAIL', $operatorValid ? $operator : 'Unsupported operator: ' . $operator);
        $diagnostics[] = self::makeCheck('RS003', 'effect_type', 'Effect type supported', $effectTypeValid ? 'PASS' : 'FAIL', $effectTypeValid ? $effectType : 'Unsupported effect: ' . $effectType);
        $diagnostics[] = self::makeCheck('RS004', 'effect_target', 'Effect target exists', $effectTargetValid ? 'PASS' : 'FAIL', $effectTargetValid ? $effectTarget : 'Invalid target for ' . $effectType);

        // Evaluate condition
        $conditionMatched = false;
        $conditionReason = '';

        if (!$contextFieldExists || !$operatorValid) {
            $conditionReason = 'Condition cannot be evaluated — field or operator invalid.';
            $diagnostics[] = self::makeCheck('RS005', 'condition_eval', 'Condition evaluated', 'ERROR', $conditionReason);
        } else {
            $fieldValue = (string)($sampleData[$conditionField] ?? '');
            $conditionMatched = self::evaluateCondition($fieldValue, $operator, $compareValue);
            $conditionReason = $conditionMatched
                ? 'Condition matched (' . $conditionField . ' ' . $operator . ' "' . $compareValue . '")'
                : 'Condition did not match (' . $conditionField . ' = "' . $fieldValue . '" vs "' . $compareValue . '")';
            $diagnostics[] = self::makeCheck('RS005', 'condition_eval', 'Condition evaluated', 'PASS', $conditionReason);
        }

        // Apply effect
        $effectApplied = false;
        $effectReason = '';

        if (!$renderAllowed) {
            $effectReason = 'Preview is blocked — cannot apply effect.';
            $diagnostics[] = self::makeCheck('RS006', 'effect_applied', 'Effect applied', 'ERROR', $effectReason);
        } elseif (!$effectTypeValid || !$effectTargetValid) {
            $effectReason = 'Effect cannot be applied — effect type or target invalid.';
            $diagnostics[] = self::makeCheck('RS006', 'effect_applied', 'Effect applied', 'FAIL', $effectReason);
        } elseif (!$conditionMatched) {
            $effectReason = 'Effect not applied — condition did not match.';
            $diagnostics[] = self::makeCheck('RS006', 'effect_applied', 'Effect applied', 'PASS', $effectReason);
        } else {
            $effectApplied = true;
            $effectReason = 'Effect applied (' . $effectType . ' on ' . $effectTarget . ')';
            $diagnostics[] = self::makeCheck('RS006', 'effect_applied', 'Effect applied', 'PASS', $effectReason);
        }

        // Generate modified HTML
        $baseHtml = trim((string)($previewResult['html'] ?? ''));
        $modifiedHtml = $baseHtml;

        if ($effectApplied && $baseHtml !== '') {
            $modifiedHtml = self::injectEffectHtml($baseHtml, $effectType, $effectTarget, $effectValue, $resolvedFields);
        }

        // Determine blocked reason
        $blockedReason = '';
        foreach ($diagnostics as $d) {
            $sev = (string)($d['severity'] ?? 'PASS');
            if ($sev === 'ERROR' || $sev === 'FAIL') {
                if ($blockedReason === '') {
                    $blockedReason = (string)($d['message'] ?? 'Rule validation blocked');
                }
            }
        }

        // Compute overall severity
        $worstSeverity = 'PASS';
        foreach ($diagnostics as $d) {
            $sev = (string)($d['severity'] ?? 'PASS');
            if ((self::SEVERITY_RANK[$sev] ?? 0) > (self::SEVERITY_RANK[$worstSeverity] ?? 0)) {
                $worstSeverity = $sev;
            }
        }

        return [
            'diagnostics' => $diagnostics,
            'condition_matched' => $conditionMatched,
            'effect_applied' => $effectApplied,
            'condition_reason' => $conditionReason,
            'effect_reason' => $effectReason,
            'blocked_reason' => $blockedReason,
            'modified_html' => $modifiedHtml,
            'html' => $modifiedHtml,
            'overall_severity' => $worstSeverity,
        ];
    }

    /**
     * Evaluate a single condition against a field value.
     */
    private static function evaluateCondition(string $fieldValue, string $operator, string $compareValue): bool
    {
        return match ($operator) {
            'equals' => $fieldValue === $compareValue,
            'not_equals' => $fieldValue !== $compareValue,
            'empty' => $fieldValue === '' || $fieldValue === '[]' || $fieldValue === '[Field Key]' || $fieldValue === '{}',
            'not_empty' => $fieldValue !== '' && $fieldValue !== '[]' && $fieldValue !== '[Field Key]' && $fieldValue !== '{}',
            'greater_than' => is_numeric($fieldValue) && is_numeric($compareValue) && (float)$fieldValue > (float)$compareValue,
            'less_than' => is_numeric($fieldValue) && is_numeric($compareValue) && (float)$fieldValue < (float)$compareValue,
            'contains' => str_contains(mb_strtolower($fieldValue), mb_strtolower($compareValue)),
            default => false,
        };
    }

    /**
     * Validate that the effect target exists.
     */
    private static function validateEffectTarget(string $effectType, string $effectTarget, array $resolvedFields): bool
    {
        if ($effectTarget === '') {
            return false;
        }

        $fieldKeys = array_map(static fn (array $f): string => (string)($f['field_key'] ?? ''), $resolvedFields);

        return match ($effectType) {
            'hide_field' => in_array($effectTarget, $fieldKeys, true),
            'show_badge' => $effectTarget === 'preview_header' || $effectTarget === 'preview_footer' || in_array($effectTarget, $fieldKeys, true),
            'show_warning' => true,
            'set_style_token' => str_starts_with($effectTarget, '--'),
            default => false,
        };
    }

    /**
     * Inject rule effect HTML into the base preview HTML.
     */
    private static function injectEffectHtml(string $baseHtml, string $effectType, string $effectTarget, string $effectValue, array $resolvedFields): string
    {
        return match ($effectType) {
            'show_badge' => self::injectBadgeHtml($baseHtml, $effectTarget, $effectValue),
            'show_warning' => self::injectWarningHtml($baseHtml, $effectValue),
            'hide_field' => self::injectHideFieldHtml($baseHtml, $effectTarget),
            'set_style_token' => self::injectStyleTokenHtml($baseHtml, $effectTarget, $effectValue),
            default => $baseHtml,
        };
    }

    private static function injectBadgeHtml(string $baseHtml, string $target, string $label): string
    {
        $badgeHtml = '<span class="rule-effect-badge" style="display:inline-block;margin-left:8px;padding:2px 8px;border-radius:3px;background:var(--status-warning,#e65100);color:#fff;font-size:0.7em;font-weight:600;line-height:1.5">'
            . htmlspecialchars($label ?: 'Badge', ENT_QUOTES | ENT_HTML5, 'UTF-8')
            . '</span>';

        if ($target === 'preview_header') {
            $pos = strpos($baseHtml, 'label-preview-header');
            if ($pos !== false) {
                $insertPos = strpos($baseHtml, '</div>', $pos);
                if ($insertPos !== false) {
                    return substr_replace($baseHtml, $badgeHtml, $insertPos, 0);
                }
            }
        } elseif ($target === 'preview_footer') {
            $pos = strpos($baseHtml, 'Authorized Signatory');
            if ($pos !== false) {
                $insertPos = strpos($baseHtml, '<div', $pos - 20);
                if ($insertPos === false || $insertPos < 0) {
                    $insertPos = $pos;
                }
                return substr_replace($baseHtml, $badgeHtml . ' ', $insertPos, 0);
            }
        } else {
            $searchKey = htmlspecialchars(self::labelFromField($target), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($searchKey !== '') {
                $pos = strpos($baseHtml, '>' . $searchKey . '</td>');
                if ($pos !== false) {
                    return substr_replace($baseHtml, $badgeHtml, $pos + strlen('>' . $searchKey . '</td>'), 0);
                }
            }
        }

        return $baseHtml;
    }

    private static function injectWarningHtml(string $baseHtml, string $message): string
    {
        $warningHtml = '<div class="rule-effect-warning" style="margin:8px 12px;padding:8px 12px;border-radius:4px;background:var(--bg-warning-subtle,#fff3e0);border:1px solid var(--border-warning,#ffcc80);color:var(--text-warning,#e65100);font-size:0.8em;font-weight:500">'
            . htmlspecialchars($message ?: 'Warning', ENT_QUOTES | ENT_HTML5, 'UTF-8')
            . '</div>';

        $pos = strpos($baseHtml, 'label-preview-meta');
        if ($pos !== false) {
            $insertPos = strpos($baseHtml, '<div', $pos + 100);
            if ($insertPos !== false) {
                return substr_replace($baseHtml, $warningHtml, $insertPos, 0);
            }
        }

        return $baseHtml;
    }

    private static function injectHideFieldHtml(string $baseHtml, string $fieldKey): string
    {
        $escapedKey = htmlspecialchars(self::labelFromField($fieldKey), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($escapedKey !== '') {
            $pattern = '/(<tr[^>]*>.*?' . preg_quote($escapedKey, '/') . '.*?<\/tr>)/is';
            $replacement = '<tr style="display:none" class="rule-effect-hidden" data-hidden-field="' . htmlspecialchars($fieldKey, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '">$1</tr>';
            $modified = preg_replace($pattern, $replacement, $baseHtml, 1);
            if (is_string($modified)) {
                return $modified;
            }
        }

        return $baseHtml;
    }

    private static function injectStyleTokenHtml(string $baseHtml, string $token, string $value): string
    {
        $styleOverride = '<div class="rule-effect-style" style="' . htmlspecialchars($token, ENT_QUOTES | ENT_HTML5, 'UTF-8') . ': ' . htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '"></div>';
        $pos = strpos($baseHtml, 'label-preview">');
        if ($pos !== false) {
            return substr_replace($baseHtml, $styleOverride, $pos + strlen('label-preview">'), 0);
        }

        return $baseHtml;
    }

    /**
     * @return array{rule_id:string,check_key:string,label:string,severity:string,message:string}
     */
    private static function makeCheck(
        string $ruleId,
        string $checkKey,
        string $label,
        string $severity = 'PASS',
        string $message = ''
    ): array {
        return [
            'rule_id' => $ruleId,
            'check_key' => $checkKey,
            'label' => $label,
            'severity' => $severity,
            'message' => $message,
        ];
    }

    private static function labelFromField(string $field): string
    {
        return ucwords(str_replace('_', ' ', $field));
    }
}

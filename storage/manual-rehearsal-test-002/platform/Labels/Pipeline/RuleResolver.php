<?php
declare(strict_types=1);

namespace Platform\Labels\Pipeline;

final class RuleResolver
{
    private const OPERATORS = [
        'equals', 'not_equals', 'empty', 'not_empty',
        'greater_than', 'less_than', 'contains',
    ];

    private const EFFECT_TYPES = [
        'show_badge', 'hide_field', 'show_warning', 'set_style_token',
    ];

    public static function resolve(array $rules, array $dataPayload): array
    {
        $activeEffects = [];
        $evaluated = [];

        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $ruleKey = $rule['rule_key'] ?? 'unknown';
            $conditions = $rule['conditions'] ?? [];
            $effects = $rule['effects'] ?? [];
            $scope = $rule['scope'] ?? 'label';

            $conditionResults = [];
            $allPass = true;

            foreach ($conditions as $i => $condition) {
                $field = $condition['field'] ?? '';
                $operator = $condition['operator'] ?? '';
                $compareValue = $condition['value'] ?? '';

                $fieldValue = $dataPayload[$field] ?? '';
                $result = self::evaluateCondition($fieldValue, $operator, $compareValue);

                $conditionResults[] = [
                    'index' => $i,
                    'field' => $field,
                    'operator' => $operator,
                    'compare_value' => $compareValue,
                    'field_value' => $fieldValue,
                    'passed' => $result,
                ];

                if (!$result) {
                    $allPass = false;
                }
            }

            $ruleEffects = [];
            if ($allPass && count($conditions) > 0) {
                foreach ($effects as $effect) {
                    $type = $effect['type'] ?? '';
                    if (!in_array($type, self::EFFECT_TYPES, true)) {
                        continue;
                    }
                    $ruleEffects[] = [
                        'type' => $type,
                        'target' => $effect['target'] ?? '',
                        'value' => $effect['value'] ?? '',
                        'scope' => $scope,
                        'rule_key' => $ruleKey,
                    ];
                    $activeEffects[] = end($ruleEffects);
                }
            }

            $evaluated[] = [
                'rule_key' => $ruleKey,
                'scope' => $scope,
                'conditions_passed' => $allPass && count($conditions) > 0,
                'condition_count' => count($conditions),
                'conditions' => $conditionResults,
                'active_effects' => $ruleEffects,
            ];
        }

        return [
            'active_effects' => $activeEffects,
            'evaluated' => $evaluated,
            'rule_count' => count($rules),
            'active_rule_count' => count(array_filter($evaluated, fn($e) => $e['conditions_passed'])),
        ];
    }

    private static function evaluateCondition(mixed $fieldValue, string $operator, string $compareValue): bool
    {
        return match ($operator) {
            'equals' => (string)$fieldValue === $compareValue,
            'not_equals' => (string)$fieldValue !== $compareValue,
            'empty' => $fieldValue === '' || $fieldValue === null || $fieldValue === [],
            'not_empty' => $fieldValue !== '' && $fieldValue !== null && $fieldValue !== [],
            'greater_than' => is_numeric($fieldValue) && is_numeric($compareValue) && (float)$fieldValue > (float)$compareValue,
            'less_than' => is_numeric($fieldValue) && is_numeric($compareValue) && (float)$fieldValue < (float)$compareValue,
            'contains' => str_contains((string)$fieldValue, $compareValue),
            default => false,
        };
    }
}

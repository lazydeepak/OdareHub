<?php
declare(strict_types=1);

namespace Platform\Labels\Pipeline;

final class ModelBuilder
{
    public static function build(LabelRuntimeRequest $request, array $resolution): ResolvedLabelModel
    {
        $context = $resolution['context'] ?? [];
        $template = $resolution['template'] ?? [];
        $rules = $resolution['rules'] ?? [];
        $dataPayload = $request->dataPayload;

        $ruleResult = RuleResolver::resolve($rules, $dataPayload);

        $blocks = self::buildBlocks($template, $context, $ruleResult['active_effects'], $dataPayload);

        $model = ResolvedLabelModel::fromArray([
            'owner_key' => $request->ownerKey,
            'context' => $context,
            'template' => $template,
            'rules' => $ruleResult,
            'data' => $dataPayload,
            'resolved_blocks' => $blocks,
        ]);

        $model = $model->withDiagnostics(self::buildDiagnostics($resolution, $ruleResult));

        return $model;
    }

    private static function buildBlocks(array $template, array $context, array $activeEffects, array $dataPayload): array
    {
        $layoutBlocks = $template['layout']['blocks'] ?? [];
        $templateFields = $template['fields'] ?? [];
        $contextFields = $context['allowed_fields'] ?? [];

        $fieldMap = [];
        foreach ($contextFields as $cf) {
            $key = $cf['field_key'] ?? '';
            if ($key !== '') {
                $fieldMap[$key] = $cf;
            }
        }

        $hiddenFields = [];
        foreach ($activeEffects as $effect) {
            if ($effect['type'] === 'hide_field') {
                $hiddenFields[$effect['target']] = true;
            }
        }

        $blocks = [];
        foreach ($layoutBlocks as $block) {
            $blockKey = $block['block_key'] ?? 'unknown';
            $role = $block['role'] ?? 'unknown';

            $resolvedFields = [];
            if ($role === 'field_rows') {
                foreach ($templateFields as $tf) {
                    $fk = $tf['field_key'] ?? '';
                    if ($fk === '' || isset($hiddenFields[$fk])) {
                        continue;
                    }
                    $contextDef = $fieldMap[$fk] ?? [];
                    $resolvedFields[] = [
                        'field_key' => $fk,
                        'label' => $tf['label'] ?? $contextDef['label'] ?? $fk,
                        'source_column' => $contextDef['source_column'] ?? $fk,
                        'value' => $dataPayload[$fk] ?? '',
                    ];
                }
            }

            $blockEffects = array_values(array_filter(
                $activeEffects,
                fn(array $e) => $e['scope'] === 'label' || $e['target'] === $blockKey
            ));

            $blocks[] = [
                'block_key' => $blockKey,
                'role' => $role,
                'fields' => $resolvedFields,
                'effects' => $blockEffects,
            ];
        }

        if (count($hiddenFields) > 0) {
            foreach ($blocks as $bi => &$block) {
                if ($block['role'] !== 'field_rows') {
                    continue;
                }
                foreach ($block['fields'] as $fi => $field) {
                    if (isset($hiddenFields[$field['field_key']])) {
                        $block['fields'][$fi]['hidden'] = true;
                    }
                }
            }
            unset($block);
        }

        return $blocks;
    }

    private static function buildDiagnostics(array $resolution, array $ruleResult): array
    {
        $diagnostics = $resolution['diagnostics'] ?? [];

        $ruleCount = $ruleResult['rule_count'] ?? 0;
        $activeCount = $ruleResult['active_rule_count'] ?? 0;

        if ($ruleCount > 0) {
            $diagnostics[] = [
                'stage' => 'model_building',
                'severity' => 'PASS',
                'code' => 'MB-001',
                'message' => "{$ruleCount} rule(s) evaluated, {$activeCount} active",
                'field' => '',
            ];
        }

        $diagnostics[] = [
            'stage' => 'model_building',
            'severity' => 'INFO',
            'code' => 'MB-002',
            'message' => 'Model built successfully',
            'field' => '',
        ];

        return $diagnostics;
    }
}

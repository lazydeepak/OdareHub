<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\LabelDesigner\Services;

/**
 * Read-only preview renderer for owner-owned label resources.
 *
 * Loads context + template JSON files from the owner resource tree,
 * validates per the Label Validation Contract, generates sample data,
 * and renders an HTML preview. No files written, no DB access, no
 * runtime print/export/QR generation.
 */
final class LabelDesignerPreviewRendererService
{
    private const SCHEMA_CONTEXT = 'susankhya.label.context.v1';
    private const SCHEMA_TEMPLATE = 'susankhya.label.template.v1';
    private const SCHEMA_RULE = 'susankhya.label.rule.v1';

    private const ALLOWED_OPERATORS = [
        'equals',
        'not_equals',
        'empty',
        'not_empty',
        'greater_than',
        'less_than',
        'contains',
    ];

    private const ALLOWED_EFFECT_TYPES = [
        'show_badge',
        'hide_field',
        'show_warning',
        'set_style_token',
    ];

    private const KNOWN_LABEL_SIZES = [
        '100x50_mm' => ['width' => 100, 'height' => 50, 'unit' => 'mm'],
        '80x40_mm' => ['width' => 80, 'height' => 40, 'unit' => 'mm'],
        '60x30_mm' => ['width' => 60, 'height' => 30, 'unit' => 'mm'],
        '50x25_mm' => ['width' => 50, 'height' => 25, 'unit' => 'mm'],
        'A6_portrait' => ['width' => 105, 'height' => 148, 'unit' => 'mm'],
    ];

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public static function buildResolvedPreview(array $input): array
    {
        $contextId = trim((string)($input['context_id'] ?? ''));
        $templateId = trim((string)($input['template_id'] ?? ''));
        $loadRules = !empty($input['load_rules']);

        if ($contextId === '') {
            return [
                'ok' => false,
                'errors' => ['context_id is required.'],
                'html' => '',
                'diagnostics' => [],
                'sample_data' => [],
                'resolved_fields' => [],
                'layout_blocks' => [],
                'render_allowed' => false,
                'active_rules' => [],
                'rule_evaluation' => [],
            ];
        }

        $context = self::loadContextById($contextId);
        if ($context === null) {
            return [
                'ok' => false,
                'errors' => ['Label context not found.'],
                'html' => '',
                'diagnostics' => [],
                'sample_data' => [],
                'resolved_fields' => [],
                'layout_blocks' => [],
                'render_allowed' => false,
                'active_rules' => [],
                'rule_evaluation' => [],
            ];
        }

        $template = null;
        if ($templateId !== '') {
            $template = self::loadTemplateById($templateId);
            if ($template === null) {
                return [
                    'ok' => false,
                    'errors' => ['Label template not found.'],
                    'html' => '',
                    'diagnostics' => [],
                    'sample_data' => [],
                    'resolved_fields' => [],
                    'layout_blocks' => [],
                    'render_allowed' => false,
                    'active_rules' => [],
                    'rule_evaluation' => [],
                ];
            }
        }

        $diagnostics = self::validatePreviewPreconditions($context, $template);

        $hasError = false;
        $renderAllowed = true;
        foreach ($diagnostics as $check) {
            $severity = (string)($check['severity'] ?? 'PASS');
            if ($severity === 'ERROR') {
                $hasError = true;
                $renderAllowed = false;
            }
            if ($severity === 'FAIL') {
                $renderAllowed = false;
            }
        }

        $sampleData = self::generateSampleData($context);
        $resolvedFields = self::resolveFields($context, $template, $sampleData);
        $layoutBlocks = self::buildLayoutBlocks($context, $template, $sampleData, $resolvedFields);

        // Rule loading
        $activeRules = [];
        $ruleEvaluation = [];
        $ruleCount = 0;
        $matchedCount = 0;

        if ($loadRules && $template !== null) {
            $contextKey = trim((string)($context['context_key'] ?? ''));
            $templateKey = trim((string)($template['template_key'] ?? ''));
            $ownerKey = trim((string)($context['owner_key'] ?? $context['owner']['owner_key'] ?? $context['_discovery']['owner_key'] ?? ''));

            $rules = self::resolveRules($ownerKey, $contextKey, $templateKey);
            $ruleCount = count($rules);

            foreach ($rules as $rule) {
                $conditions = isset($rule['conditions']) && is_array($rule['conditions'])
                    ? array_values(array_filter($rule['conditions'], 'is_array'))
                    : [];
                $effects = isset($rule['effects']) && is_array($rule['effects'])
                    ? array_values(array_filter($rule['effects'], 'is_array'))
                    : [];

                $allMatched = true;
                $evalDetails = [];

                foreach ($conditions as $cond) {
                    $fieldKey = trim((string)($cond['field_key'] ?? ''));
                    $operator = trim((string)($cond['operator'] ?? ''));
                    $compareValue = trim((string)($cond['value'] ?? ''));
                    $fieldValue = (string)($sampleData[$fieldKey] ?? '');
                    $matched = self::evaluateConditionValue($fieldValue, $operator, $compareValue);
                    if (!$matched) {
                        $allMatched = false;
                    }
                    $evalDetails[] = [
                        'field_key' => $fieldKey,
                        'operator' => $operator,
                        'compare_value' => $compareValue,
                        'field_value' => $fieldValue,
                        'matched' => $matched,
                    ];
                }

                if ($allMatched) {
                    $matchedCount++;
                    foreach ($effects as $eff) {
                        $effType = trim((string)($eff['type'] ?? ''));
                        $effTarget = trim((string)($eff['target'] ?? ''));
                        $effValue = trim((string)($eff['value'] ?? ''));
                        $resolvedFields = self::applyRuleEffect($resolvedFields, $effType, $effTarget, $effValue);
                    }
                }

                $ruleKey = trim((string)($rule['rule_key'] ?? 'unknown'));
                $activeRules[] = $ruleKey;
                $ruleEvaluation[] = [
                    'rule_key' => $ruleKey,
                    'condition_evaluations' => $evalDetails,
                    'all_conditions_matched' => $allMatched,
                ];
            }
        }

        $html = self::renderHtmlPreview($context, $template, $sampleData, $resolvedFields, $layoutBlocks, $renderAllowed);

        return [
            'ok' => !$hasError,
            'errors' => [],
            'html' => $html,
            'diagnostics' => $diagnostics,
            'sample_data' => $sampleData,
            'resolved_fields' => $resolvedFields,
            'layout_blocks' => $layoutBlocks,
            'render_allowed' => $renderAllowed,
            'active_rules' => $activeRules,
            'rule_evaluation' => $ruleEvaluation,
            'rule_count' => $ruleCount,
            'rule_matched_count' => $matchedCount,
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed>|null $template
     * @return array<int,array<string,mixed>>
     */
    public static function validatePreviewPreconditions(array $context, ?array $template): array
    {
        $checks = [];

        $checks[] = self::makeCheck('C001', 'context_schema', 'Context schema', 'susankhya.label.context.v1');
        $contextSchema = (string)($context['schema'] ?? '');
        if ($contextSchema === '') {
            $checks[] = self::makeCheck('C001.1', 'context_schema_present', 'Context schema field present', 'ERROR', 'Missing schema field.');
        } elseif ($contextSchema !== self::SCHEMA_CONTEXT) {
            $checks[] = self::makeCheck('C001.2', 'context_schema_match', 'Context schema matches expected', 'FAIL', 'Expected ' . self::SCHEMA_CONTEXT . ', got ' . $contextSchema);
        } else {
            $checks[] = self::makeCheck('C001', 'context_schema', 'Context schema', 'PASS');
        }

        $contextKey = trim((string)($context['context_key'] ?? ''));
        if ($contextKey === '') {
            $checks[] = self::makeCheck('C002', 'context_key', 'Context key present', 'ERROR', 'Missing context_key.');
        } else {
            $checks[] = self::makeCheck('C002', 'context_key', 'Context key present', 'PASS', $contextKey);
        }

        $ownerKey = trim((string)($context['owner']['owner_key'] ?? $context['owner_key'] ?? ''));
        if ($ownerKey === '') {
            $checks[] = self::makeCheck('C003', 'context_owner', 'Context owner present', 'ERROR', 'Missing owner.');
        } else {
            $checks[] = self::makeCheck('C003', 'context_owner', 'Context owner present', 'PASS', $ownerKey);
        }

        $allowedFields = isset($context['allowed_fields']) && is_array($context['allowed_fields'])
            ? array_values(array_filter($context['allowed_fields'], 'is_array'))
            : [];
        if ($allowedFields === []) {
            $checks[] = self::makeCheck('C004', 'context_fields', 'Context allowed fields', 'FAIL', 'No allowed_fields defined.');
        } else {
            $checks[] = self::makeCheck('C004', 'context_fields', 'Context allowed fields', 'PASS', (string)count($allowedFields) . ' fields');
        }

        // Owner metadata hardening
        $ownerMeta = isset($context['owner']) && is_array($context['owner']) ? $context['owner'] : [];
        $ownerKeyMeta = trim((string)($ownerMeta['owner_key'] ?? ''));
        $ownerTypeMeta = trim((string)($ownerMeta['owner_type'] ?? ''));
        $ownerRootMeta = trim((string)($ownerMeta['root_path'] ?? ''));
        if ($ownerKeyMeta !== '' && $ownerTypeMeta !== '' && $ownerRootMeta !== '') {
            $checks[] = self::makeCheck('M001', 'owner_metadata_complete', 'Owner metadata complete', 'PASS', $ownerKeyMeta);
        } else {
            $missing = [];
            if ($ownerKeyMeta === '') { $missing[] = 'owner_key'; }
            if ($ownerTypeMeta === '') { $missing[] = 'owner_type'; }
            if ($ownerRootMeta === '') { $missing[] = 'root_path'; }
            $checks[] = self::makeCheck('M001', 'owner_metadata_complete', 'Owner metadata complete', 'WARN', 'Missing: ' . implode(', ', $missing));
        }

        // Legacy fallback detection: if resource has no top-level owner_key but exists in discovery
        $resourceOwnerKey = trim((string)($context['owner_key'] ?? ''));
        if ($resourceOwnerKey === '' && $discoveryMeta['owner_key'] ?? '' !== '') {
            $checks[] = self::makeCheck('M003', 'metadata_first_ownership', 'Metadata-first ownership', 'WARN', 'Legacy resource — using discovery fallback ownership resolution. Context lacks top-level owner_key.');
        } elseif ($resourceOwnerKey !== '') {
            $checks[] = self::makeCheck('M003', 'metadata_first_ownership', 'Metadata-first ownership', 'PASS', 'Owner key declared in resource metadata.');
        }

        $discoveryMeta = isset($context['_discovery']) && is_array($context['_discovery']) ? $context['_discovery'] : [];
        $discoveryPath = trim((string)($discoveryMeta['context_path'] ?? ''));
        if ($discoveryPath !== '') {
            $absDiscoPath = defined('APP_ROOT') ? APP_ROOT . '/' . ltrim($discoveryPath, '/') : '';
            if (is_file($absDiscoPath)) {
                $checks[] = self::makeCheck('M002', 'resource_file_exists', 'Resource file exists', 'PASS', $discoveryPath);
            } else {
                $checks[] = self::makeCheck('M002', 'resource_file_exists', 'Resource file exists', 'ERROR', 'Resource file not found at: ' . $discoveryPath);
            }
        } else {
            $checks[] = self::makeCheck('M002', 'resource_file_exists', 'Resource file exists', 'WARN', 'Discovery path metadata is missing.');
        }

        if ($template === null) {
            $checks[] = self::makeCheck('T000', 'template_optional', 'Template selected', 'PASS', 'No template — context-only preview');
            return $checks;
        }

        $templateSchema = (string)($template['schema'] ?? '');
        if ($templateSchema === '') {
            $checks[] = self::makeCheck('T001', 'template_schema_present', 'Template schema field present', 'ERROR', 'Missing schema field.');
        } elseif ($templateSchema !== self::SCHEMA_TEMPLATE) {
            $checks[] = self::makeCheck('T001', 'template_schema_match', 'Template schema matches expected', 'FAIL', 'Expected ' . self::SCHEMA_TEMPLATE . ', got ' . $templateSchema);
        } else {
            $checks[] = self::makeCheck('T001', 'template_schema', 'Template schema', 'PASS');
        }

        $templateKey = trim((string)($template['template_key'] ?? ''));
        if ($templateKey === '') {
            $checks[] = self::makeCheck('T002', 'template_key', 'Template key present', 'ERROR', 'Missing template_key.');
        } else {
            $checks[] = self::makeCheck('T002', 'template_key', 'Template key present', 'PASS', $templateKey);
        }

        $contextRef = isset($template['context_ref']) && is_array($template['context_ref'])
            ? $template['context_ref']
            : [];
        $refContextKey = trim((string)($contextRef['context_key'] ?? ''));
        if ($refContextKey === '') {
            $checks[] = self::makeCheck('T003', 'template_context_ref', 'Template context_ref present', 'FAIL', 'Missing context_ref.');
        } elseif ($refContextKey !== $contextKey) {
            $checks[] = self::makeCheck('T003', 'template_context_ref', 'Template context_ref matches context', 'FAIL', 'context_ref ' . $refContextKey . ' does not match context_key ' . $contextKey);
        } else {
            $checks[] = self::makeCheck('T003', 'template_context_ref', 'Template context_ref matches context', 'PASS', $refContextKey);
        }

        $layout = isset($template['layout']) && is_array($template['layout']) ? $template['layout'] : [];
        if ($layout === []) {
            $checks[] = self::makeCheck('T004', 'template_layout', 'Template layout present', 'FAIL', 'Missing layout.');
        } else {
            $checks[] = self::makeCheck('T004', 'template_layout', 'Template layout present', 'PASS');
        }

        $labelSize = trim((string)($layout['label_size'] ?? ''));
        if ($labelSize === '') {
            $checks[] = self::makeCheck('T005', 'template_label_size', 'Template label size present', 'FAIL', 'Missing label_size in layout.');
        } elseif (!isset(self::KNOWN_LABEL_SIZES[$labelSize])) {
            $checks[] = self::makeCheck('T005', 'template_label_size', 'Template label size known', 'WARN', 'Unknown label size: ' . $labelSize);
        } else {
            $checks[] = self::makeCheck('T005', 'template_label_size', 'Template label size present', 'PASS', $labelSize);
        }

        $blocks = isset($layout['blocks']) && is_array($layout['blocks'])
            ? array_values(array_filter($layout['blocks'], 'is_array'))
            : [];
        if ($blocks === []) {
            $checks[] = self::makeCheck('T006', 'template_blocks', 'Template layout blocks present', 'WARN', 'No layout blocks defined.');
        } else {
            $checks[] = self::makeCheck('T006', 'template_blocks', 'Template layout blocks present', 'PASS', (string)count($blocks) . ' blocks');
        }

        $templateFields = isset($template['fields']) && is_array($template['fields'])
            ? array_values(array_filter($template['fields'], 'is_array'))
            : [];
        if ($templateFields === []) {
            $checks[] = self::makeCheck('T007', 'template_fields', 'Template fields present', 'FAIL', 'No fields in template.');
        } else {
            $fieldKeys = array_map(static fn (array $f): string => (string)($f['field_key'] ?? ''), $templateFields);
            $allowedKeys = array_map(static fn (array $f): string => (string)($f['field_key'] ?? ''), $allowedFields);
            $unknownFields = array_diff($fieldKeys, $allowedKeys);
            if ($unknownFields !== []) {
                $checks[] = self::makeCheck('T008', 'template_fields_in_context', 'Template fields belong to context', 'FAIL', 'Fields not in context: ' . implode(', ', $unknownFields));
            } else {
                $checks[] = self::makeCheck('T008', 'template_fields_in_context', 'Template fields belong to context', 'PASS', (string)count($templateFields) . ' fields');
            }
        }

        // Legacy fallback detection for templates
        $tplOwnerKey = trim((string)($template['owner_key'] ?? ''));
        $tplDiscoveryOwner = trim((string)($template['_discovery']['owner_key'] ?? ''));
        if ($tplOwnerKey === '' && $tplDiscoveryOwner !== '') {
            $checks[] = self::makeCheck('M004', 'template_metadata_first_ownership', 'Template metadata-first ownership', 'WARN', 'Legacy template resource — using discovery fallback. Template lacks top-level owner_key.');
        } elseif ($tplOwnerKey !== '') {
            $checks[] = self::makeCheck('M004', 'template_metadata_first_ownership', 'Template metadata-first ownership', 'PASS', 'Template owner key declared in resource metadata.');
        }

        $checks[] = self::makeCheck('Z001', 'owner_runtime_boundary', 'No runtime print/export/QR coupling', 'PASS', 'Read-only preview renderer');
        $checks[] = self::makeCheck('Z002', 'db_free', 'No DB access or SQL', 'PASS', 'Static file validation only');

        return $checks;
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public static function generateSampleData(array $context): array
    {
        $allowedFields = isset($context['allowed_fields']) && is_array($context['allowed_fields'])
            ? array_values(array_filter($context['allowed_fields'], 'is_array'))
            : [];

        $sampleData = [];
        foreach ($allowedFields as $field) {
            $fieldKey = trim((string)($field['field_key'] ?? ''));
            if ($fieldKey === '') {
                continue;
            }
            $dataType = trim((string)($field['data_type'] ?? ''));
            $sampleData[$fieldKey] = self::generateFieldValue($fieldKey, $dataType);
        }

        return $sampleData;
    }

    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed>|null $template
     * @param array<string,mixed> $sampleData
     * @return array<int,array<string,mixed>>
     */
    public static function resolveFields(array $context, ?array $template, array $sampleData): array
    {
        $resolved = [];

        $allowedFields = isset($context['allowed_fields']) && is_array($context['allowed_fields'])
            ? array_values(array_filter($context['allowed_fields'], 'is_array'))
            : [];

        $templateFieldMap = [];
        if ($template !== null) {
            $templateFields = isset($template['fields']) && is_array($template['fields'])
                ? array_values(array_filter($template['fields'], 'is_array'))
                : [];
            foreach ($templateFields as $tf) {
                $tfKey = trim((string)($tf['field_key'] ?? ''));
                if ($tfKey !== '') {
                    $templateFieldMap[$tfKey] = $tf;
                }
            }
        }

        foreach ($allowedFields as $field) {
            $fieldKey = trim((string)($field['field_key'] ?? ''));
            if ($fieldKey === '') {
                continue;
            }

            $templateField = $templateFieldMap[$fieldKey] ?? [];
            $label = (string)($templateField['label'] ?? $field['label'] ?? self::labelFromField($fieldKey));
            $sourceColumn = (string)($templateField['source_column'] ?? $field['source_column'] ?? $fieldKey);

            $resolved[] = [
                'field_key' => $fieldKey,
                'label' => $label,
                'source_column' => $sourceColumn,
                'data_type' => (string)($field['data_type'] ?? 'string'),
                'sample_value' => (string)($sampleData[$fieldKey] ?? ''),
                'in_template' => isset($templateFieldMap[$fieldKey]),
            ];
        }

        return $resolved;
    }

    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed>|null $template
     * @param array<string,mixed> $sampleData
     * @param array<int,array<string,mixed>> $resolvedFields
     * @return array<int,array<string,mixed>>
     */
    public static function buildLayoutBlocks(
        array $context,
        ?array $template,
        array $sampleData,
        array $resolvedFields
    ): array {
        $blocks = [];

        if ($template === null) {
            $blocks[] = [
                'block_key' => 'default',
                'role' => 'field_rows',
                'label' => 'All Fields',
                'content' => $resolvedFields,
            ];
            return $blocks;
        }

        $layout = isset($template['layout']) && is_array($template['layout']) ? $template['layout'] : [];
        $templateBlocks = isset($layout['blocks']) && is_array($layout['blocks'])
            ? array_values(array_filter($layout['blocks'], 'is_array'))
            : [];

        if ($templateBlocks === []) {
            $blocks[] = [
                'block_key' => 'default',
                'role' => 'field_rows',
                'label' => 'All Fields',
                'content' => $resolvedFields,
            ];
            return $blocks;
        }

        $contextKey = trim((string)($context['context_key'] ?? ''));
        $purpose = trim((string)($context['purpose'] ?? ''));

        $templateFieldMap = [];
        $templateFields = isset($template['fields']) && is_array($template['fields'])
            ? array_values(array_filter($template['fields'], 'is_array'))
            : [];
        foreach ($templateFields as $tf) {
            $tfKey = trim((string)($tf['field_key'] ?? ''));
            if ($tfKey !== '') {
                $templateFieldMap[$tfKey] = $tf;
            }
        }

        foreach ($templateBlocks as $tb) {
            $blockKey = trim((string)($tb['block_key'] ?? 'block_' . (count($blocks) + 1)));
            $role = trim((string)($tb['role'] ?? 'field_rows'));

            if ($role === 'title_and_identity') {
                $titleValues = [];
                foreach ($resolvedFields as $rf) {
                    $sampleVal = (string)($rf['sample_value'] ?? '');
                    if ($sampleVal !== '' && !str_starts_with($sampleVal, '[')) {
                        $titleValues[] = $sampleVal;
                    }
                    if (count($titleValues) >= 3) {
                        break;
                    }
                }
                $blocks[] = [
                    'block_key' => $blockKey,
                    'role' => $role,
                    'label' => $purpose !== '' ? $purpose : $contextKey,
                    'owner' => (string)($context['owner']['owner_key'] ?? $context['owner_key'] ?? ''),
                    'preview_title' => implode(' — ', $titleValues !== [] ? $titleValues : ['[Sample Label]']),
                ];
            } elseif ($role === 'field_rows') {
                $blockFields = [];
                foreach ($resolvedFields as $rf) {
                    if (!empty($rf['in_template'])) {
                        $blockFields[] = $rf;
                    }
                }
                if ($blockFields === []) {
                    $blockFields = $resolvedFields;
                }
                $blocks[] = [
                    'block_key' => $blockKey,
                    'role' => $role,
                    'label' => 'Fields',
                    'content' => $blockFields,
                ];
            } elseif ($role === 'owner_signoff_qr_placeholder') {
                $blocks[] = [
                    'block_key' => $blockKey,
                    'role' => $role,
                    'label' => 'Owner Signoff / QR',
                ];
            } elseif ($role === 'barcode_slot') {
                $blocks[] = [
                    'block_key' => $blockKey,
                    'role' => $role,
                    'label' => 'Barcode',
                ];
            } elseif ($role === 'image_slot') {
                $blocks[] = [
                    'block_key' => $blockKey,
                    'role' => $role,
                    'label' => 'Image',
                ];
            } else {
                $blocks[] = [
                    'block_key' => $blockKey,
                    'role' => $role,
                    'label' => $role,
                ];
            }
        }

        return $blocks;
    }

    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed>|null $template
     * @param array<string,mixed> $sampleData
     * @param array<int,array<string,mixed>> $resolvedFields
     * @param array<int,array<string,mixed>> $layoutBlocks
     */
    public static function renderHtmlPreview(
        array $context,
        ?array $template,
        array $sampleData,
        array $resolvedFields,
        array $layoutBlocks,
        bool $renderAllowed
    ): string {
        if (!$renderAllowed) {
            return '<div class="label-preview-unavailable" style="padding:24px;text-align:center;color:var(--text-muted,#999);border:1px dashed var(--border-subtle,#ccc);border-radius:6px;background:var(--bg-subtle,#fafafa)"><p>Preview unavailable — validation failed. Review diagnostics above.</p></div>';
        }

        if ($template === null) {
            return self::renderContextOnlyPreview($context, $sampleData, $resolvedFields);
        }

        $layout = isset($template['layout']) && is_array($template['layout']) ? $template['layout'] : [];
        $labelSize = trim((string)($layout['label_size'] ?? ''));
        $sizeMeta = self::KNOWN_LABEL_SIZES[$labelSize] ?? ['width' => 100, 'height' => 50, 'unit' => 'mm'];

        $contextKey = trim((string)($context['context_key'] ?? ''));
        $purpose = trim((string)($context['purpose'] ?? ''));

        $html = '<div class="label-preview" style="border:1px solid var(--border-default,#ccc);border-radius:6px;overflow:hidden;font-family:monospace;max-width:600px;background:var(--bg-surface,#fff);box-shadow:0 1px 4px rgba(0,0,0,0.08)">';

        $html .= '<div class="label-preview-meta" style="padding:6px 12px;background:var(--bg-subtle,#f5f5f5);border-bottom:1px solid var(--border-subtle,#ddd);font-size:0.75em;color:var(--text-muted,#888);display:flex;justify-content:space-between">';
        $html .= '<span>' . self::escapeHtml($labelSize !== '' ? $labelSize : 'unknown size') . '</span>';
        $html .= '<span>' . self::escapeHtml($sizeMeta['width'] . '×' . $sizeMeta['height'] . $sizeMeta['unit']) . '</span>';
        $html .= '<span>' . self::escapeHtml($contextKey) . '</span>';
        $html .= '</div>';

        foreach ($layoutBlocks as $block) {
            $role = (string)($block['role'] ?? 'field_rows');

            if ($role === 'title_and_identity') {
                $html .= '<div class="label-preview-header" style="padding:16px 16px 8px;border-bottom:1px dashed var(--border-subtle,#ddd)">';
                $html .= '<h4 style="margin:0 0 4px;font-size:1em;font-weight:600">' . self::escapeHtml((string)($block['preview_title'] ?? '[Label Title]')) . '</h4>';
                $owner = (string)($block['owner'] ?? '');
                if ($owner !== '') {
                    $html .= '<p style="margin:0;font-size:0.8em;color:var(--text-muted,#888)">' . self::escapeHtml($owner) . '</p>';
                }
                $html .= '</div>';
            } elseif ($role === 'field_rows') {
                $fields = isset($block['content']) && is_array($block['content']) ? $block['content'] : [];
                if ($fields !== []) {
                    $html .= '<div class="label-preview-fields" style="padding:12px 16px;border-bottom:1px dashed var(--border-subtle,#ddd)">';
                    $html .= '<table style="width:100%;border-collapse:collapse;font-size:0.85em">';
                    foreach ($fields as $field) {
                        $label = self::escapeHtml((string)($field['label'] ?? $field['field_key'] ?? ''));
                        $value = self::escapeHtml((string)($field['sample_value'] ?? ''));
                        $html .= '<tr>';
                        $html .= '<td style="padding:3px 8px 3px 0;color:var(--text-muted,#666);white-space:nowrap;font-weight:500">' . $label . '</td>';
                        $html .= '<td style="padding:3px 0;font-weight:600">' . $value . '</td>';
                        $html .= '</tr>';
                    }
                    $html .= '</table>';
                    $html .= '</div>';
                }
            } elseif ($role === 'owner_signoff_qr_placeholder') {
                $html .= '<div class="label-preview-footer" style="padding:12px 16px;display:flex;justify-content:space-between;align-items:center;font-size:0.8em">';
                $html .= '<div style="display:flex;flex-direction:column;gap:4px">';
                $html .= '<span style="border-bottom:1px solid #999;padding:0 24px">Authorized Signatory</span>';
                $html .= '<span style="font-size:0.75em;color:var(--text-muted,#999)">' . self::escapeHtml($purpose !== '' ? $purpose : $contextKey) . '</span>';
                $html .= '</div>';
                $html .= '<div class="label-preview-placeholder" style="border:1px dashed var(--border-subtle,#ccc);border-radius:4px;padding:8px 12px;color:var(--text-muted,#999);text-align:center;font-size:0.75em">[QR Placeholder]</div>';
                $html .= '</div>';
            } elseif ($role === 'barcode_slot') {
                $html .= '<div class="label-preview-barcode" style="padding:8px 16px;text-align:center;border-bottom:1px dashed var(--border-subtle,#ddd)">';
                $html .= '<div class="label-preview-placeholder" style="border:1px dashed var(--border-subtle,#ccc);border-radius:4px;padding:8px 12px;color:var(--text-muted,#999);text-align:center;font-size:0.75em;margin:0 auto;display:inline-block">[Barcode Placeholder]</div>';
                $html .= '</div>';
            } elseif ($role === 'image_slot') {
                $html .= '<div class="label-preview-image" style="padding:8px 16px;text-align:center;border-bottom:1px dashed var(--border-subtle,#ddd)">';
                $html .= '<div class="label-preview-placeholder" style="border:1px dashed var(--border-subtle,#ccc);border-radius:4px;padding:12px 20px;color:var(--text-muted,#999);text-align:center;font-size:0.75em;margin:0 auto;display:inline-block">[Image Placeholder]</div>';
                $html .= '</div>';
            } else {
                $html .= '<div class="label-preview-generic" style="padding:8px 16px;border-bottom:1px dashed var(--border-subtle,#ddd);font-size:0.8em;color:var(--text-muted,#999)">';
                $html .= '<em>[' . self::escapeHtml(ucfirst(str_replace('_', ' ', $role))) . ']</em>';
                $html .= '</div>';
            }
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed> $sampleData
     * @param array<int,array<string,mixed>> $resolvedFields
     */
    private static function renderContextOnlyPreview(array $context, array $sampleData, array $resolvedFields): string
    {
        $contextKey = self::escapeHtml((string)($context['context_key'] ?? ''));
        $purpose = self::escapeHtml((string)($context['purpose'] ?? 'No purpose defined'));

        $html = '<div class="label-preview context-only" style="border:1px solid var(--border-default,#ccc);border-radius:6px;overflow:hidden;font-family:monospace;max-width:600px;background:var(--bg-surface,#fff);box-shadow:0 1px 4px rgba(0,0,0,0.08)">';

        $html .= '<div class="label-preview-meta" style="padding:6px 12px;background:var(--bg-subtle,#f5f5f5);border-bottom:1px solid var(--border-subtle,#ddd);font-size:0.75em;color:var(--text-muted,#888);display:flex;justify-content:space-between">';
        $html .= '<span>Context-only preview</span>';
        $html .= '<span>' . $contextKey . '</span>';
        $html .= '</div>';

        $html .= '<div class="label-preview-header" style="padding:16px 16px 8px;border-bottom:1px dashed var(--border-subtle,#ddd)">';
        $html .= '<h4 style="margin:0 0 4px;font-size:1em;font-weight:600">' . $purpose . '</h4>';
        $html .= '<p style="margin:0;font-size:0.8em;color:var(--text-muted,#888)">' . $contextKey . '</p>';
        $html .= '</div>';

        if ($resolvedFields !== []) {
            $html .= '<div class="label-preview-fields" style="padding:12px 16px">';
            $html .= '<table style="width:100%;border-collapse:collapse;font-size:0.85em">';
            foreach ($resolvedFields as $field) {
                $label = self::escapeHtml((string)($field['label'] ?? $field['field_key'] ?? ''));
                $value = self::escapeHtml((string)($field['sample_value'] ?? ''));
                $html .= '<tr>';
                $html .= '<td style="padding:3px 8px 3px 0;color:var(--text-muted,#666);white-space:nowrap;font-weight:500">' . $label . '</td>';
                $html .= '<td style="padding:3px 0;font-weight:600">' . $value . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    private static function generateFieldValue(string $fieldKey, string $dataType): string
    {
        $lowerKey = strtolower($fieldKey);

        if ($dataType === 'int' || $dataType === 'integer' || $dataType === 'bigint' || $dataType === 'smallint') {
            if (str_contains($lowerKey, 'qty') || str_contains($lowerKey, 'quantity') || str_contains($lowerKey, 'count')) {
                return '100';
            }
            if (str_contains($lowerKey, 'year')) {
                return '2026';
            }
            return '42';
        }

        if ($dataType === 'decimal' || $dataType === 'float' || $dataType === 'double') {
            return '99.50';
        }

        if (str_contains($lowerKey, 'name')) {
            return 'Sample Product';
        }
        if (str_contains($lowerKey, 'code') || str_contains($lowerKey, 'sku') || str_contains($lowerKey, 'id') || str_contains($lowerKey, 'number')) {
            return 'SAMPLE-001';
        }
        if (str_contains($lowerKey, 'batch') || str_contains($lowerKey, 'lot')) {
            return 'B20260609';
        }
        if (str_contains($lowerKey, 'date')) {
            return '2026-06-09';
        }
        if (str_contains($lowerKey, 'time')) {
            return '14:30:00';
        }
        if (str_contains($lowerKey, 'email') || str_contains($lowerKey, 'mail')) {
            return 'sample@example.com';
        }
        if (str_contains($lowerKey, 'phone') || str_contains($lowerKey, 'tel') || str_contains($lowerKey, 'mobile')) {
            return '+1-555-0123';
        }
        if (str_contains($lowerKey, 'url') || str_contains($lowerKey, 'link') || str_contains($lowerKey, 'website')) {
            return 'https://example.com/label';
        }
        if (str_contains($lowerKey, 'barcode') || str_contains($lowerKey, 'bar_code') || $lowerKey === 'barcode') {
            return '[Barcode Placeholder]';
        }
        if (str_contains($lowerKey, 'qr') || $lowerKey === 'qr_code' || $lowerKey === 'qrcode') {
            return '[QR Placeholder]';
        }
        if (str_contains($lowerKey, 'image') || str_contains($lowerKey, 'photo') || str_contains($lowerKey, 'picture') || str_contains($lowerKey, 'logo')) {
            return '[Image Placeholder]';
        }
        if (str_contains($lowerKey, 'desc') || str_contains($lowerKey, 'note') || str_contains($lowerKey, 'remark') || str_contains($lowerKey, 'comment')) {
            return 'Sample description text for preview purposes.';
        }
        if (str_contains($lowerKey, 'status') || str_contains($lowerKey, 'state')) {
            return 'Active';
        }
        if (str_contains($lowerKey, 'type') || str_contains($lowerKey, 'category') || str_contains($lowerKey, 'class')) {
            return 'Standard';
        }
        if (str_contains($lowerKey, 'color') || str_contains($lowerKey, 'colour')) {
            return 'Blue';
        }
        if (str_contains($lowerKey, 'size') || str_contains($lowerKey, 'dimension')) {
            return 'M';
        }
        if (str_contains($lowerKey, 'weight') || str_contains($lowerKey, 'mass')) {
            return '1.5 kg';
        }
        if (str_contains($lowerKey, 'price') || str_contains($lowerKey, 'cost') || str_contains($lowerKey, 'amount')) {
            return '$12.99';
        }
        if (str_contains($lowerKey, 'address') || str_contains($lowerKey, 'location') || str_contains($lowerKey, 'place')) {
            return '123 Sample Street, Unit 4';
        }
        if (str_contains($lowerKey, 'serial') || $lowerKey === 'serial_no' || $lowerKey === 'serial_number') {
            return 'SN-2026-00042';
        }
        if (str_contains($lowerKey, 'machine') || $lowerKey === 'machine_no' || $lowerKey === 'machine_number') {
            return 'MCH-03';
        }
        if (str_contains($lowerKey, 'case') || $lowerKey === 'case_no' || $lowerKey === 'case_number') {
            return 'CASE-042';
        }
        if (str_contains($lowerKey, 'pallet') || $lowerKey === 'pallet_no' || $lowerKey === 'pallet_id') {
            return 'PAL-007';
        }
        if (str_contains($lowerKey, 'part') || $lowerKey === 'part_no' || $lowerKey === 'part_number') {
            return 'PRT-2026-A2';
        }

        return '[' . ucwords(str_replace('_', ' ', $fieldKey)) . ']';
    }

    /**
     * @param array<string,mixed> $discovery
     * @return array<int,array<string,mixed>>
     */
    public static function resolveContextOptionsFromDiscovery(array $discovery): array
    {
        $contexts = [];
        $owners = isset($discovery['owners']) && is_array($discovery['owners'])
            ? array_values(array_filter($discovery['owners'], 'is_array'))
            : [];

        foreach ($owners as $owner) {
            $ownerKey = trim((string)($owner['owner_key'] ?? ''));
            $ownerType = trim((string)($owner['owner_type'] ?? ''));
            $resources = isset($owner['resources']) && is_array($owner['resources']) ? $owner['resources'] : [];
            $contextGroup = isset($resources['contexts']) && is_array($resources['contexts']) ? $resources['contexts'] : [];
            $contextFiles = isset($contextGroup['files']) && is_array($contextGroup['files'])
                ? array_values(array_filter($contextGroup['files'], 'is_array'))
                : [];

            foreach ($contextFiles as $cf) {
                $relPath = trim((string)($cf['path'] ?? ''));
                if ($relPath === '') {
                    continue;
                }
                $absPath = defined('APP_ROOT') ? APP_ROOT . '/' . ltrim($relPath, '/') : '';
                if ($absPath === '' || !is_file($absPath)) {
                    continue;
                }

                $raw = @file_get_contents($absPath);
                if (!is_string($raw) || $raw === '') {
                    continue;
                }

                $decoded = json_decode($raw, true);
                if (!is_array($decoded) || (string)($decoded['schema'] ?? '') !== self::SCHEMA_CONTEXT) {
                    continue;
                }

                $contextKey = trim((string)($decoded['context_key'] ?? ''));
                if ($contextKey === '') {
                    continue;
                }

                $contexts[] = [
                    'context_id' => sha1($relPath),
                    'context_key' => $contextKey,
                    'context_file' => trim((string)($cf['name'] ?? basename($relPath))),
                    'context_path' => $relPath,
                    'owner_key' => $ownerKey,
                    'owner_type' => $ownerType,
                    'purpose' => trim((string)($decoded['purpose'] ?? '')),
                ];
            }
        }

        usort($contexts, static fn (array $a, array $b): int => strcmp((string)($a['context_key'] ?? ''), (string)($b['context_key'] ?? '')));

        return $contexts;
    }

    /**
     * @param array<string,mixed> $discovery
     * @return array<int,array<string,mixed>>
     */
    public static function resolveTemplateOptionsFromDiscovery(array $discovery): array
    {
        $templates = [];
        $owners = isset($discovery['owners']) && is_array($discovery['owners'])
            ? array_values(array_filter($discovery['owners'], 'is_array'))
            : [];

        foreach ($owners as $owner) {
            $ownerKey = trim((string)($owner['owner_key'] ?? ''));
            $ownerType = trim((string)($owner['owner_type'] ?? ''));
            $resources = isset($owner['resources']) && is_array($owner['resources']) ? $owner['resources'] : [];
            $templateGroup = isset($resources['templates']) && is_array($resources['templates']) ? $resources['templates'] : [];
            $templateFiles = isset($templateGroup['files']) && is_array($templateGroup['files'])
                ? array_values(array_filter($templateGroup['files'], 'is_array'))
                : [];

            foreach ($templateFiles as $tf) {
                $relPath = trim((string)($tf['path'] ?? ''));
                if ($relPath === '') {
                    continue;
                }
                $absPath = defined('APP_ROOT') ? APP_ROOT . '/' . ltrim($relPath, '/') : '';
                if ($absPath === '' || !is_file($absPath)) {
                    continue;
                }

                $raw = @file_get_contents($absPath);
                if (!is_string($raw) || $raw === '') {
                    continue;
                }

                $decoded = json_decode($raw, true);
                if (!is_array($decoded) || (string)($decoded['schema'] ?? '') !== self::SCHEMA_TEMPLATE) {
                    continue;
                }

                $templateKey = trim((string)($decoded['template_key'] ?? ''));
                if ($templateKey === '') {
                    continue;
                }

                $contextRef = isset($decoded['context_ref']) && is_array($decoded['context_ref']) ? $decoded['context_ref'] : [];
                $layout = isset($decoded['layout']) && is_array($decoded['layout']) ? $decoded['layout'] : [];

                $templates[] = [
                    'template_id' => sha1($relPath),
                    'template_key' => $templateKey,
                    'template_file' => trim((string)($tf['name'] ?? basename($relPath))),
                    'template_path' => $relPath,
                    'owner_key' => $ownerKey,
                    'owner_type' => $ownerType,
                    'context_key' => trim((string)($contextRef['context_key'] ?? '')),
                    'label_size' => trim((string)($layout['label_size'] ?? '')),
                ];
            }
        }

        usort($templates, static fn (array $a, array $b): int => strcmp((string)($a['template_key'] ?? ''), (string)($b['template_key'] ?? '')));

        return $templates;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function loadContextById(string $contextId): ?array
    {
        $contexts = self::resolveContextOptionsFromDiscovery(LabelDesignerDiscoveryService::discover());
        foreach ($contexts as $ctx) {
            if (hash_equals((string)($ctx['context_id'] ?? ''), $contextId)) {
                $relPath = (string)($ctx['context_path'] ?? '');
                $absPath = defined('APP_ROOT') ? APP_ROOT . '/' . ltrim($relPath, '/') : '';
                if ($absPath === '' || !is_file($absPath)) {
                    return null;
                }
                $raw = @file_get_contents($absPath);
                if (!is_string($raw) || $raw === '') {
                    return null;
                }
                $decoded = json_decode($raw, true);
                if (!is_array($decoded)) {
                    return null;
                }
                $decoded['_discovery'] = [
                    'owner_key' => $ctx['owner_key'],
                    'owner_type' => $ctx['owner_type'],
                    'context_file' => $ctx['context_file'],
                    'context_path' => $relPath,
                ];
                return $decoded;
            }
        }
        return null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function loadTemplateById(string $templateId): ?array
    {
        $templates = self::resolveTemplateOptionsFromDiscovery(LabelDesignerDiscoveryService::discover());
        foreach ($templates as $tmpl) {
            if (hash_equals((string)($tmpl['template_id'] ?? ''), $templateId)) {
                $relPath = (string)($tmpl['template_path'] ?? '');
                $absPath = defined('APP_ROOT') ? APP_ROOT . '/' . ltrim($relPath, '/') : '';
                if ($absPath === '' || !is_file($absPath)) {
                    return null;
                }
                $raw = @file_get_contents($absPath);
                if (!is_string($raw) || $raw === '') {
                    return null;
                }
                $decoded = json_decode($raw, true);
                if (!is_array($decoded)) {
                    return null;
                }
                $decoded['_discovery'] = [
                    'owner_key' => $tmpl['owner_key'],
                    'owner_type' => $tmpl['owner_type'],
                    'template_file' => $tmpl['template_file'],
                    'template_path' => $relPath,
                ];
                return $decoded;
            }
        }
        return null;
    }

    /**
     * @param array<int,array<string,mixed>> $resolvedFields
     * @return array<int,array<string,mixed>>
     */
    private static function applyRuleEffect(array $resolvedFields, string $effectType, string $effectTarget, string $effectValue): array
    {
        if ($effectType === 'hide_field') {
            foreach ($resolvedFields as $i => $field) {
                if ((string)($field['field_key'] ?? '') === $effectTarget) {
                    $resolvedFields[$i]['_hidden'] = true;
                    $resolvedFields[$i]['_hidden_by_rule'] = $effectTarget;
                }
            }
        } elseif ($effectType === 'show_badge') {
            foreach ($resolvedFields as $i => $field) {
                if ((string)($field['field_key'] ?? '') === $effectTarget) {
                    $resolvedFields[$i]['_badge'] = $effectValue !== '' ? $effectValue : '!';
                }
            }
        } elseif ($effectType === 'show_warning') {
            foreach ($resolvedFields as $i => $field) {
                $resolvedFields[$i]['_warning'] = $effectValue !== '' ? $effectValue : 'Warning';
            }
        } elseif ($effectType === 'set_style_token') {
            foreach ($resolvedFields as $i => $field) {
                $resolvedFields[$i]['_style_override_' . $effectTarget] = $effectValue;
            }
        }

        return $resolvedFields;
    }

    /**
     * Evaluate a single condition against a field value.
     */
    private static function evaluateConditionValue(string $fieldValue, string $operator, string $compareValue): bool
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
     * Load rules matching the given context_key and template_key from discovery.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function resolveRules(string $ownerKey, string $contextKey, string $templateKey): array
    {
        if ($ownerKey === '' || $contextKey === '' || $templateKey === '') {
            return [];
        }

        $discovery = LabelDesignerDiscoveryService::discover();
        $owners = isset($discovery['owners']) && is_array($discovery['owners'])
            ? array_values(array_filter($discovery['owners'], 'is_array'))
            : [];

        $rules = [];
        foreach ($owners as $owner) {
            if (!self::ownerKeyMatch((string)($owner['owner_key'] ?? ''), $ownerKey)) {
                continue;
            }

            $resources = isset($owner['resources']) && is_array($owner['resources']) ? $owner['resources'] : [];
            $rulesGroup = isset($resources['rules']) && is_array($resources['rules']) ? $resources['rules'] : [];
            $ruleFiles = isset($rulesGroup['files']) && is_array($rulesGroup['files'])
                ? array_values(array_filter($rulesGroup['files'], 'is_array'))
                : [];

            foreach ($ruleFiles as $rf) {
                $relPath = trim((string)($rf['path'] ?? ''));
                if ($relPath === '') {
                    continue;
                }

                $absPath = defined('APP_ROOT') ? APP_ROOT . '/' . ltrim($relPath, '/') : '';
                if ($absPath === '' || !is_file($absPath)) {
                    continue;
                }

                $raw = @file_get_contents($absPath);
                if (!is_string($raw) || $raw === '') {
                    continue;
                }

                $decoded = json_decode($raw, true);
                if (!is_array($decoded) || (string)($decoded['schema'] ?? '') !== self::SCHEMA_RULE) {
                    continue;
                }

                if (empty($decoded['enabled'])) {
                    continue;
                }

                $decodedContextKey = trim((string)($decoded['context_key'] ?? ''));
                $decodedTemplateKey = trim((string)($decoded['template_key'] ?? ''));

                if ($decodedContextKey !== $contextKey || $decodedTemplateKey !== $templateKey) {
                    continue;
                }

                $rules[] = $decoded;
            }
        }

        return $rules;
    }

    private static function ownerKeyMatch(string $a, string $b): bool
    {
        $normalize = static fn (string $key): string => strtolower(str_replace('\\', '/', trim($key)));
        $left = $normalize($a);
        $right = $normalize($b);
        return $left !== '' && $right !== '' && hash_equals($left, $right);
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

    private static function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private static function labelFromField(string $field): string
    {
        return ucwords(str_replace('_', ' ', $field));
    }
}

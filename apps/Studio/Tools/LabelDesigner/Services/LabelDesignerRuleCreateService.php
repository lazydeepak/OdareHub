<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\LabelDesigner\Services;

final class LabelDesignerRuleCreateService
{
    private const SNAPSHOT_ROOT = APP_ROOT . '/storage/studio-snapshots/label-designer';

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

    private const SEVERITY_RANK = [
        'PASS' => 0,
        'WARN' => 1,
        'FAIL' => 2,
        'ERROR' => 3,
    ];

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public static function buildPreview(array $input): array
    {
        $validationRequested = !array_key_exists('validation_requested', $input)
            || !empty($input['validation_requested']);
        $selectedOwnerKey = trim((string)($input['owner_key'] ?? ''));
        $contextId = trim((string)($input['rule_context_id'] ?? ''));
        $templateId = trim((string)($input['rule_template_id'] ?? ''));
        $rawRuleKey = trim((string)($input['rule_key'] ?? ''));
        $conditionField = trim((string)($input['rc_condition_field'] ?? ''));
        $operator = trim((string)($input['rc_operator'] ?? ''));
        $conditionValue = trim((string)($input['rc_condition_value'] ?? ''));
        $effectType = trim((string)($input['rc_effect_type'] ?? ''));
        $effectTarget = trim((string)($input['rc_effect_target'] ?? ''));
        $effectValue = trim((string)($input['rc_effect_value'] ?? ''));
        $enabledInput = trim((string)($input['rule_enabled'] ?? 'yes'));
        $enabled = !in_array(strtolower($enabledInput), ['0', 'false', 'no', 'off'], true);

        $discovery = LabelDesignerDiscoveryService::discover();
        $contextOptions = LabelDesignerPreviewRendererService::resolveContextOptionsFromDiscovery($discovery);
        $templateOptions = LabelDesignerPreviewRendererService::resolveTemplateOptionsFromDiscovery($discovery);

        if (!$validationRequested) {
            return self::buildInitialState(
                $selectedOwnerKey,
                $contextId,
                $templateId,
                $rawRuleKey,
                $conditionField,
                $operator,
                $conditionValue,
                $effectType,
                $effectTarget,
                $effectValue,
                $enabled,
                $contextOptions,
                $templateOptions
            );
        }

        $checks = [];

        $selectedContext = self::findContext($contextOptions, $contextId);

        $context = null;
        if ($selectedContext !== null) {
            $context = self::readJsonFile((string)($selectedContext['context_path'] ?? ''));
            if ($context !== null) {
                self::addCheck($checks, 'RC01', 'context_exists', 'Context exists', 'PASS', (string)($selectedContext['context_key'] ?? ''));
            } else {
                self::addCheck($checks, 'RC01', 'context_exists', 'Context exists', 'ERROR', 'Selected context file is missing or invalid JSON.');
            }
        } else {
            self::addCheck($checks, 'RC01', 'context_exists', 'Context exists', 'ERROR', 'No context selected.');
        }

        $compatibleTemplates = [];
        if ($selectedContext !== null) {
            $contextOwnerKey = (string)($selectedContext['owner_key'] ?? '');
            $contextKey = (string)($selectedContext['context_key'] ?? '');
            $compatibleTemplates = array_values(array_filter(
                $templateOptions,
                static fn (array $tpl): bool =>
                    (string)($tpl['owner_key'] ?? '') === $contextOwnerKey
                    && (string)($tpl['context_key'] ?? '') === $contextKey
            ));
        }

        $selectedTemplate = self::findTemplate($compatibleTemplates, $templateId);

        $template = null;
        if ($selectedTemplate !== null) {
            $template = self::readJsonFile((string)($selectedTemplate['template_path'] ?? ''));
            if ($template !== null) {
                self::addCheck($checks, 'RC02', 'template_exists', 'Template exists', 'PASS', (string)($selectedTemplate['template_key'] ?? ''));
            } else {
                self::addCheck($checks, 'RC02', 'template_exists', 'Template exists', 'ERROR', 'Selected template file is missing or invalid JSON.');
            }
        } else {
            self::addCheck($checks, 'RC02', 'template_exists', 'Template exists', 'ERROR', 'No compatible template selected.');
        }

        $ownerKey = '';
        $contextKey = '';
        $templateKey = '';

        if ($context !== null) {
            $ownerKey = trim((string)($context['owner_key'] ?? $context['owner']['owner_key'] ?? $selectedContext['owner_key'] ?? ''));
            $contextKey = trim((string)($context['context_key'] ?? $selectedContext['context_key'] ?? ''));
        }
        if ($template !== null) {
            $templateKey = trim((string)($template['template_key'] ?? $selectedTemplate['template_key'] ?? ''));
        }

        $ownerRecord = self::findOwnerRecord($discovery, $ownerKey);
        if ($ownerRecord !== null) {
            self::addCheck($checks, 'RC03', 'owner_exists', 'Owner exists', 'PASS', $ownerKey);
        } else {
            self::addCheck($checks, 'RC03', 'owner_exists', 'Owner exists', 'FAIL', 'Owner key from context was not found in discovery.');
        }

        $lifecycleOwner = $ownerKey !== '' && LabelDesignerResourceReadinessService::isLabelLifecycleOwner($ownerKey);
        self::addCheck(
            $checks,
            'RC04',
            'owner_lifecycle',
            'Owner is label-lifecycle owner',
            $lifecycleOwner ? 'PASS' : 'FAIL',
            $lifecycleOwner ? $ownerKey : 'Selected owner cannot own label rule resources.'
        );

        if ($context !== null && $selectedContext !== null) {
            $contextOwner = trim((string)($context['owner_key'] ?? $context['owner']['owner_key'] ?? ''));
            if ($contextOwner === '') {
                self::addCheck(
                    $checks,
                    'RC05',
                    'context_owner_match',
                    'Context belongs to selected owner',
                    'WARN',
                    'Context owner metadata missing; using discovery owner binding for this slice.'
                );
            } else {
                $contextOwnerOk = self::ownerKeysEqual($contextOwner, (string)($selectedContext['owner_key'] ?? ''));
                self::addCheck(
                    $checks,
                    'RC05',
                    'context_owner_match',
                    'Context belongs to selected owner',
                    $contextOwnerOk ? 'PASS' : 'FAIL',
                    $contextOwnerOk ? $contextOwner : 'Context owner does not match discovery owner.'
                );
            }
        } else {
            self::addCheck($checks, 'RC05', 'context_owner_match', 'Context belongs to selected owner', 'ERROR', 'Context metadata unavailable.');
        }

        if ($template !== null && $selectedTemplate !== null) {
            $templateOwnerOk = self::ownerKeysEqual((string)($selectedTemplate['owner_key'] ?? ''), $ownerKey);
            self::addCheck(
                $checks,
                'RC06',
                'template_owner_match',
                'Template belongs to selected owner',
                $templateOwnerOk ? 'PASS' : 'FAIL',
                $templateOwnerOk ? $ownerKey : 'Template owner does not match context owner.'
            );
        } else {
            self::addCheck($checks, 'RC06', 'template_owner_match', 'Template belongs to selected owner', 'ERROR', 'Template metadata unavailable.');
        }

        if ($template !== null && $context !== null) {
            $templateContextRef = trim((string)($template['context_ref']['context_key'] ?? ''));
            $compatOk = $templateContextRef !== '' && hash_equals($templateContextRef, $contextKey);
            self::addCheck(
                $checks,
                'RC07',
                'context_template_compatibility',
                'Context/template compatibility',
                $compatOk ? 'PASS' : 'FAIL',
                $compatOk ? $contextKey : 'Template context_ref does not match selected context key.'
            );
        } else {
            self::addCheck($checks, 'RC07', 'context_template_compatibility', 'Context/template compatibility', 'ERROR', 'Missing context or template.');
        }

        $contextFieldKeys = self::contextFieldKeys($context);
        $conditionFieldValid = $conditionField !== '' && in_array($conditionField, $contextFieldKeys, true);
        self::addCheck(
            $checks,
            'RC08',
            'condition_field_exists',
            'Condition field exists in context',
            $conditionFieldValid ? 'PASS' : 'FAIL',
            $conditionFieldValid ? $conditionField : 'Condition field is missing from context allowed_fields.'
        );

        $operatorValid = in_array($operator, self::ALLOWED_OPERATORS, true);
        self::addCheck(
            $checks,
            'RC09',
            'operator_allowed',
            'Operator is allowed',
            $operatorValid ? 'PASS' : 'FAIL',
            $operatorValid ? $operator : 'Unsupported operator.'
        );

        $effectTypeValid = in_array($effectType, self::ALLOWED_EFFECT_TYPES, true);
        self::addCheck(
            $checks,
            'RC10',
            'effect_type_allowed',
            'Effect type is allowed',
            $effectTypeValid ? 'PASS' : 'FAIL',
            $effectTypeValid ? $effectType : 'Unsupported effect type.'
        );

        $effectTargetOptions = self::buildEffectTargetOptions($template, $contextFieldKeys);
        $effectTargetValid = self::isEffectTargetValid($effectType, $effectTarget, $effectTargetOptions);
        self::addCheck(
            $checks,
            'RC11',
            'effect_target_exists',
            'Effect target exists in template',
            $effectTargetValid ? 'PASS' : 'FAIL',
            $effectTargetValid ? $effectTarget : 'Effect target is invalid for selected template/effect type.'
        );

        $ruleKey = self::safeRuleKey($rawRuleKey, $ownerKey, $contextKey, $templateKey, $effectType);
        $ruleKeyValid = $ruleKey !== '';
        self::addCheck(
            $checks,
            'RC12',
            'rule_key_valid',
            'Rule key valid',
            $ruleKeyValid ? 'PASS' : 'FAIL',
            $ruleKeyValid ? $ruleKey : 'Rule key is invalid.'
        );

        $ownerRoot = self::resolveOwnerRootByKey($ownerKey);
        if ($ownerRoot === '') {
            self::addCheck($checks, 'RC13', 'owner_root_valid', 'Owner root resolved', 'FAIL', 'Owner root path could not be resolved.');
            self::addCheck($checks, 'RC14', 'canonical_path_valid', 'Canonical rule path valid', 'ERROR', 'Cannot validate rule path without owner root.');
            self::addCheck($checks, 'RC15', 'owner_containment', 'Owner containment', 'ERROR', 'Cannot validate containment without owner root.');
            self::addCheck($checks, 'RC16', 'rule_key_unique', 'Rule key unique', 'ERROR', 'Cannot validate duplicates without owner root.');
            self::addCheck($checks, 'RC17', 'target_path_unique', 'Target path unique', 'ERROR', 'Cannot validate target path without owner root.');
            self::addCheck($checks, 'RC18', 'no_existing_overwrite', 'Existing file overwrite blocked', 'ERROR', 'Cannot validate overwrite without owner root.');

            return self::previewResult(
                $checks,
                [
                    'rule_context_id' => $contextId,
                    'rule_template_id' => $templateId,
                    'rule_key' => $ruleKey,
                    'rc_condition_field' => $conditionField,
                    'rc_operator' => $operator,
                    'rc_condition_value' => $conditionValue,
                    'rc_effect_type' => $effectType,
                    'rc_effect_target' => $effectTarget,
                    'rc_effect_value' => $effectValue,
                    'rule_enabled' => $enabled ? 'yes' : 'no',
                ],
                $contextOptions,
                $compatibleTemplates,
                $contextFieldKeys,
                $effectTargetOptions,
                null,
                '',
                '{}'
            );
        }

        self::addCheck(
            $checks,
            'RC13',
            'owner_root_valid',
            'Owner root resolved',
            'PASS',
            self::toRelativePath($ownerRoot)
        );

        $rulesDir = $ownerRoot . '/Resources/labels/rules';
        $targetPath = $rulesDir . '/' . $ruleKey . '.json';

        $canonicalPathValid = self::isPathInside($rulesDir, $ownerRoot) && self::isPathInside($targetPath, $ownerRoot);
        self::addCheck(
            $checks,
            'RC14',
            'canonical_path_valid',
            'Canonical rule path valid',
            $canonicalPathValid ? 'PASS' : 'FAIL',
            $canonicalPathValid ? self::toRelativePath($targetPath) : 'Rule path is outside owner boundary.'
        );

        self::addCheck(
            $checks,
            'RC15',
            'owner_containment',
            'Owner containment',
            $canonicalPathValid ? 'PASS' : 'FAIL',
            $canonicalPathValid ? $ownerKey : 'Target path containment check failed.'
        );

        $existingRuleKeys = self::discoverExistingRuleKeys($discovery, $ownerKey);
        $ruleKeyUnique = $ruleKey !== '' && !isset($existingRuleKeys[strtolower($ruleKey)]);
        self::addCheck(
            $checks,
            'RC16',
            'rule_key_unique',
            'Rule key unique',
            $ruleKeyUnique ? 'PASS' : 'FAIL',
            $ruleKeyUnique ? $ruleKey : 'Rule key already exists for this owner.'
        );

        $targetPathUnique = $targetPath !== '' && !is_file($targetPath);
        self::addCheck(
            $checks,
            'RC17',
            'target_path_unique',
            'Target path unique',
            $targetPathUnique ? 'PASS' : 'FAIL',
            $targetPathUnique ? self::toRelativePath($targetPath) : 'Target path already exists.'
        );

        self::addCheck(
            $checks,
            'RC18',
            'no_existing_overwrite',
            'Existing file overwrite blocked',
            $targetPathUnique ? 'PASS' : 'FAIL',
            $targetPathUnique ? 'Create-only path available.' : 'Create flow does not overwrite existing files.'
        );

        $ruleResource = self::buildRuleResource(
            $ruleKey,
            $ownerKey,
            trim((string)($ownerRecord['owner_type'] ?? '')),
            trim((string)($ownerRecord['root_path'] ?? '')),
            $contextKey,
            $templateKey,
            $enabled,
            $conditionField,
            $operator,
            $conditionValue,
            $effectType,
            $effectTarget,
            $effectValue
        );

        $ruleJson = json_encode($ruleResource, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($ruleJson) || $ruleJson === '') {
            self::addCheck($checks, 'RC19', 'json_valid', 'Rule JSON valid', 'ERROR', 'Failed to encode rule JSON.');
            $ruleJson = '{}';
        } else {
            self::addCheck($checks, 'RC19', 'json_valid', 'Rule JSON valid', 'PASS', 'JSON encoded successfully.');
        }

        return self::previewResult(
            $checks,
            [
                'rule_context_id' => $contextId,
                'rule_template_id' => $templateId,
                'rule_key' => $ruleKey,
                'rc_condition_field' => $conditionField,
                'rc_operator' => $operator,
                'rc_condition_value' => $conditionValue,
                'rc_effect_type' => $effectType,
                'rc_effect_target' => $effectTarget,
                'rc_effect_value' => $effectValue,
                'rule_enabled' => $enabled ? 'yes' : 'no',
            ],
            $contextOptions,
            $compatibleTemplates,
            $contextFieldKeys,
            $effectTargetOptions,
            $ruleResource,
            self::toRelativePath($targetPath),
            $ruleJson
        );
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array<string,mixed>
     */
    public static function createRule(array $input, array $actor = []): array
    {
        $preview = self::buildPreview($input);
        if (empty($preview['ok'])) {
            return $preview;
        }

        if (trim((string)($input['confirm_create'] ?? '')) !== 'yes') {
            $preview['ok'] = false;
            $preview['errors'] = ['Confirmation is required before writing.'];
            return $preview;
        }

        $targetPathRel = trim((string)($preview['target_path_rel'] ?? ''));
        $targetPath = $targetPathRel !== '' ? APP_ROOT . '/' . ltrim($targetPathRel, '/') : '';
        $ruleResource = isset($preview['rule']) && is_array($preview['rule']) ? $preview['rule'] : [];
        $ruleJson = trim((string)($preview['rule_json'] ?? ''));

        if ($targetPath === '' || $ruleResource === [] || $ruleJson === '') {
            return [
                'ok' => false,
                'errors' => ['Rule create preview is incomplete.'],
                'diagnostics' => $preview['diagnostics'] ?? [],
            ];
        }

        $ownerRoot = self::resolveOwnerRootByKey(trim((string)($ruleResource['owner_key'] ?? '')));
        $expectedRulesDir = $ownerRoot !== '' ? $ownerRoot . '/Resources/labels/rules' : '';
        if (
            $ownerRoot === ''
            || !self::isPathInside($targetPath, $ownerRoot)
            || self::normalizePath(dirname($targetPath)) !== self::normalizePath($expectedRulesDir)
        ) {
            return [
                'ok' => false,
                'errors' => ['Target rule path is outside the owner-contained Resources/labels/rules directory.'],
                'diagnostics' => $preview['diagnostics'] ?? [],
            ];
        }

        if (is_file($targetPath)) {
            return [
                'ok' => false,
                'errors' => ['Target rule file already exists. Create flow does not overwrite existing files.'],
                'diagnostics' => $preview['diagnostics'] ?? [],
            ];
        }

        $targetDir = dirname($targetPath);
        $snapshotResult = self::writeSnapshot([
            'resource_type' => 'rule',
            'action' => 'rule-create',
            'owner_key' => (string)($ruleResource['owner_key'] ?? ''),
            'context_key' => (string)($ruleResource['context_key'] ?? ''),
            'template_key' => (string)($ruleResource['template_key'] ?? ''),
            'rule_key' => (string)($ruleResource['rule_key'] ?? ''),
            'target_path' => $targetPathRel,
            'previous_content' => null,
            'proposed_content' => $ruleResource,
            'validation_result' => $preview['diagnostics'] ?? [],
            'timestamp' => gmdate('c'),
            'user' => self::actorSummary($actor),
            'rollback_hint' => 'Delete ' . $targetPathRel . ' to rollback rule-create.',
        ]);

        if (empty($snapshotResult['ok'])) {
            return [
                'ok' => false,
                'errors' => ['Failed to create snapshot metadata before write.'],
                'diagnostics' => $preview['diagnostics'] ?? [],
            ];
        }

        if (!is_dir($targetDir)) {
            if (!@mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                return [
                    'ok' => false,
                    'errors' => ['Failed to create owner rules directory.'],
                    'diagnostics' => $preview['diagnostics'] ?? [],
                    'snapshot_path' => (string)($snapshotResult['path_rel'] ?? ''),
                ];
            }
        }

        $tmpPath = $targetPath . '.tmp.' . getmypid();
        $written = @file_put_contents($tmpPath, $ruleJson . "\n");
        if ($written === false) {
            @unlink($tmpPath);
            return [
                'ok' => false,
                'errors' => ['Failed to write temporary rule file.'],
                'diagnostics' => $preview['diagnostics'] ?? [],
                'snapshot_path' => (string)($snapshotResult['path_rel'] ?? ''),
            ];
        }

        if (!@rename($tmpPath, $targetPath)) {
            @unlink($tmpPath);
            return [
                'ok' => false,
                'errors' => ['Failed to finalize rule file write.'],
                'diagnostics' => $preview['diagnostics'] ?? [],
                'snapshot_path' => (string)($snapshotResult['path_rel'] ?? ''),
            ];
        }

        $postDiagnostics = self::runPostWriteDiagnostics($targetPath, $ruleResource, (string)($snapshotResult['path_rel'] ?? ''));

        return [
            'ok' => true,
            'errors' => [],
            'diagnostics' => $postDiagnostics,
            'created_path' => $targetPathRel,
            'snapshot_path' => (string)($snapshotResult['path_rel'] ?? ''),
            'rule_key' => (string)($ruleResource['rule_key'] ?? ''),
        ];
    }

    /**
     * @param array<string,mixed> $discovery
     */
    private static function findOwnerRecord(array $discovery, string $ownerKey): ?array
    {
        $owners = isset($discovery['owners']) && is_array($discovery['owners'])
            ? array_values(array_filter($discovery['owners'], 'is_array'))
            : [];

        foreach ($owners as $owner) {
            if (self::ownerKeysEqual((string)($owner['owner_key'] ?? ''), $ownerKey)) {
                return $owner;
            }
        }

        return null;
    }

    /**
     * @param array<int,array<string,mixed>> $contextOptions
     */
    private static function findContext(array $contextOptions, string $contextId): ?array
    {
        if ($contextId === '') {
            return null;
        }

        foreach ($contextOptions as $context) {
            if (hash_equals((string)($context['context_id'] ?? ''), $contextId)) {
                return $context;
            }
        }

        return null;
    }

    /**
     * @param array<int,array<string,mixed>> $templateOptions
     */
    private static function findTemplate(array $templateOptions, string $templateId): ?array
    {
        if ($templateId === '') {
            return null;
        }

        foreach ($templateOptions as $template) {
            if (hash_equals((string)($template['template_id'] ?? ''), $templateId)) {
                return $template;
            }
        }

        return null;
    }

    /**
     * @param array<int,array<string,mixed>> $contextOptions
     * @param array<int,array<string,mixed>> $templateOptions
     * @return array<string,mixed>
     */
    private static function buildInitialState(
        string $ownerKey,
        string $contextId,
        string $templateId,
        string $ruleKey,
        string $conditionField,
        string $operator,
        string $conditionValue,
        string $effectType,
        string $effectTarget,
        string $effectValue,
        bool $enabled,
        array $contextOptions,
        array $templateOptions
    ): array {
        $ownerContexts = array_values(array_filter(
            $contextOptions,
            static fn (array $context): bool =>
                $ownerKey === ''
                || self::ownerKeysEqual((string)($context['owner_key'] ?? ''), $ownerKey)
        ));

        $selectedContext = self::findContext($ownerContexts, $contextId) ?? ($ownerContexts[0] ?? null);
        $compatibleTemplates = [];
        $contextFieldKeys = [];
        $effectTargetOptions = ['fields' => [], 'blocks' => [], 'style_tokens' => []];

        if (is_array($selectedContext)) {
            $contextId = (string)($selectedContext['context_id'] ?? '');
            $contextKey = (string)($selectedContext['context_key'] ?? '');
            $contextOwnerKey = (string)($selectedContext['owner_key'] ?? '');
            $compatibleTemplates = array_values(array_filter(
                $templateOptions,
                static fn (array $template): bool =>
                    self::ownerKeysEqual((string)($template['owner_key'] ?? ''), $contextOwnerKey)
                    && (string)($template['context_key'] ?? '') === $contextKey
            ));

            $context = self::readJsonFile((string)($selectedContext['context_path'] ?? ''));
            $contextFieldKeys = self::contextFieldKeys($context);
            $selectedTemplate = self::findTemplate($compatibleTemplates, $templateId) ?? ($compatibleTemplates[0] ?? null);
            if (is_array($selectedTemplate)) {
                $templateId = (string)($selectedTemplate['template_id'] ?? '');
                $template = self::readJsonFile((string)($selectedTemplate['template_path'] ?? ''));
                $effectTargetOptions = self::buildEffectTargetOptions($template, $contextFieldKeys);
            }
        }

        $result = self::previewResult(
            [],
            [
                'rule_context_id' => $contextId,
                'rule_template_id' => $templateId,
                'rule_key' => $ruleKey,
                'rc_condition_field' => $conditionField,
                'rc_operator' => $operator !== '' ? $operator : 'equals',
                'rc_condition_value' => $conditionValue,
                'rc_effect_type' => $effectType !== '' ? $effectType : 'show_warning',
                'rc_effect_target' => $effectTarget,
                'rc_effect_value' => $effectValue,
                'rule_enabled' => $enabled ? 'yes' : 'no',
            ],
            $ownerContexts,
            $compatibleTemplates,
            $contextFieldKeys,
            $effectTargetOptions,
            null,
            '',
            '{}',
            false
        );
        $result['empty_state'] = $ownerContexts === [] || $compatibleTemplates === [];

        return $result;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function readJsonFile(string $relativePath): ?array
    {
        $relativePath = trim($relativePath);
        if ($relativePath === '' || str_contains($relativePath, '..')) {
            return null;
        }

        $absolutePath = APP_ROOT . '/' . ltrim($relativePath, '/');
        if (!is_file($absolutePath)) {
            return null;
        }

        $raw = @file_get_contents($absolutePath);
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string,mixed>|null $context
     * @return array<int,string>
     */
    private static function contextFieldKeys(?array $context): array
    {
        if ($context === null) {
            return [];
        }

        $allowedFields = isset($context['allowed_fields']) && is_array($context['allowed_fields'])
            ? array_values(array_filter($context['allowed_fields'], 'is_array'))
            : [];

        $keys = [];
        foreach ($allowedFields as $field) {
            $key = trim((string)($field['field_key'] ?? ''));
            if ($key !== '') {
                $keys[$key] = $key;
            }
        }

        return array_values($keys);
    }

    /**
     * @param array<string,mixed>|null $template
     * @param array<int,string> $contextFieldKeys
     * @return array<string,array<int,string>>
     */
    private static function buildEffectTargetOptions(?array $template, array $contextFieldKeys): array
    {
        $fieldTargets = [];
        foreach ($contextFieldKeys as $fieldKey) {
            if ($fieldKey !== '') {
                $fieldTargets[$fieldKey] = $fieldKey;
            }
        }

        $blockTargets = [];
        $layout = is_array($template) && isset($template['layout']) && is_array($template['layout'])
            ? $template['layout']
            : [];
        $blocks = isset($layout['blocks']) && is_array($layout['blocks'])
            ? array_values(array_filter($layout['blocks'], 'is_array'))
            : [];

        foreach ($blocks as $block) {
            $blockKey = trim((string)($block['block_key'] ?? ''));
            $role = trim((string)($block['role'] ?? ''));
            if ($blockKey !== '') {
                $blockTargets[$blockKey] = $blockKey;
            }
            if ($role !== '') {
                $blockTargets[$role] = $role;
                if (str_contains($role, 'title') || str_contains($role, 'header')) {
                    $blockTargets['preview_header'] = 'preview_header';
                }
                if (str_contains($role, 'footer') || str_contains($role, 'signoff')) {
                    $blockTargets['preview_footer'] = 'preview_footer';
                }
            }
        }

        $styleTokenTargets = [];
        $styleTokens = is_array($template) && isset($template['style_tokens']) && is_array($template['style_tokens'])
            ? array_values(array_filter($template['style_tokens'], 'is_string'))
            : [];
        foreach ($styleTokens as $token) {
            $token = trim($token);
            if ($token !== '' && str_starts_with($token, '--')) {
                $styleTokenTargets[$token] = $token;
            }
        }

        return [
            'fields' => array_values($fieldTargets),
            'blocks' => array_values($blockTargets),
            'style_tokens' => array_values($styleTokenTargets),
        ];
    }

    /**
     * @param array<string,array<int,string>> $targetOptions
     */
    private static function isEffectTargetValid(string $effectType, string $effectTarget, array $targetOptions): bool
    {
        if ($effectTarget === '') {
            return false;
        }

        $fields = $targetOptions['fields'] ?? [];
        $blocks = $targetOptions['blocks'] ?? [];
        $styleTokens = $targetOptions['style_tokens'] ?? [];

        return match ($effectType) {
            'hide_field' => in_array($effectTarget, $fields, true),
            'show_badge' => in_array($effectTarget, $fields, true) || in_array($effectTarget, $blocks, true),
            'show_warning' => in_array($effectTarget, $blocks, true),
            'set_style_token' => in_array($effectTarget, $styleTokens, true),
            default => false,
        };
    }

    private static function resolveOwnerRootByKey(string $ownerKey): string
    {
        if ($ownerKey === '' || str_contains($ownerKey, '..')) {
            return '';
        }

        $parts = explode('/', $ownerKey);
        if ($parts[0] === '') {
            return '';
        }

        $appPath = APP_ROOT . '/apps/' . $parts[0];
        $appReal = realpath($appPath);
        if (!is_string($appReal) || !is_dir($appReal)) {
            return '';
        }

        if (count($parts) === 1) {
            return $appReal;
        }

        $modulePath = $appReal . '/modules/' . $parts[1];
        $moduleReal = realpath($modulePath);
        if (!is_string($moduleReal) || !is_dir($moduleReal)) {
            return '';
        }

        return $moduleReal;
    }

    private static function safeRuleKey(string $raw, string $ownerKey, string $contextKey, string $templateKey, string $effectType): string
    {
        $candidate = trim($raw);
        if ($candidate === '') {
            $ownerPart = str_replace('/', '.', strtolower($ownerKey));
            $contextPart = str_replace('/', '.', strtolower($contextKey));
            $templatePart = str_replace('/', '.', strtolower($templateKey));
            $effectPart = strtolower($effectType !== '' ? $effectType : 'rule');
            $candidate = trim($ownerPart . '.' . $contextPart . '.' . $templatePart . '.' . $effectPart, '.');
        }

        $candidate = strtolower($candidate);
        $candidate = preg_replace('/[^a-z0-9._-]+/', '.', $candidate) ?? '';
        $candidate = preg_replace('/\.+/', '.', $candidate) ?? '';
        $candidate = trim($candidate, '.');

        if ($candidate === '' || str_contains($candidate, '..')) {
            return '';
        }

        if (preg_match('/^[a-z0-9][a-z0-9._-]*$/', $candidate) !== 1) {
            return '';
        }

        return $candidate;
    }

    /**
     * @param array<string,mixed> $discovery
     * @return array<string,string>
     */
    private static function discoverExistingRuleKeys(array $discovery, string $ownerKey): array
    {
        $owners = isset($discovery['owners']) && is_array($discovery['owners'])
            ? array_values(array_filter($discovery['owners'], 'is_array'))
            : [];

        $keys = [];
        foreach ($owners as $owner) {
            if (!self::ownerKeysEqual((string)($owner['owner_key'] ?? ''), $ownerKey)) {
                continue;
            }

            $resources = isset($owner['resources']) && is_array($owner['resources']) ? $owner['resources'] : [];
            $rules = isset($resources['rules']) && is_array($resources['rules']) ? $resources['rules'] : [];
            $files = isset($rules['files']) && is_array($rules['files']) ? array_values(array_filter($rules['files'], 'is_array')) : [];

            foreach ($files as $file) {
                $path = trim((string)($file['path'] ?? ''));
                if ($path === '') {
                    continue;
                }

                $decoded = self::readJsonFile($path);
                if (!is_array($decoded)) {
                    continue;
                }

                $key = strtolower(trim((string)($decoded['rule_key'] ?? '')));
                if ($key !== '') {
                    $keys[$key] = $path;
                }
            }
        }

        return $keys;
    }

    /**
     * @return array<string,mixed>
     */
    private static function buildRuleResource(
        string $ruleKey,
        string $ownerKey,
        string $ownerType,
        string $ownerRoot,
        string $contextKey,
        string $templateKey,
        bool $enabled,
        string $conditionField,
        string $operator,
        string $conditionValue,
        string $effectType,
        string $effectTarget,
        string $effectValue
    ): array {
        $valueType = is_numeric($conditionValue) ? 'number' : 'string';

        return [
            'schema' => 'odarehub.label.rule.v1',
            'rule_key' => $ruleKey,
            'owner_key' => $ownerKey,
            'owner_type' => $ownerType,
            'owner_root' => $ownerRoot,
            'context_key' => $contextKey,
            'template_key' => $templateKey,
            'enabled' => $enabled,
            'conditions' => [[
                'source' => 'field',
                'field_key' => $conditionField,
                'operator' => $operator,
                'value' => $conditionValue,
                'value_type' => $valueType,
            ]],
            'effects' => [[
                'type' => $effectType,
                'target' => $effectTarget,
                'value' => $effectValue,
            ]],
            'write_status' => 'active',
            'resource_type' => 'rule',
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{ok:bool,path_rel?:string}
     */
    private static function writeSnapshot(array $payload): array
    {
        if (!is_dir(self::SNAPSHOT_ROOT)) {
            if (!@mkdir(self::SNAPSHOT_ROOT, 0755, true) && !is_dir(self::SNAPSHOT_ROOT)) {
                return ['ok' => false];
            }
        }

        $ruleKey = (string)($payload['rule_key'] ?? 'rule');
        $safeRuleKey = preg_replace('/[^a-z0-9._-]+/i', '-', strtolower($ruleKey)) ?: 'rule';
        $snapshotId = gmdate('Ymd_His') . '_' . substr(sha1($safeRuleKey . microtime(true)), 0, 10);
        $snapshotPath = self::SNAPSHOT_ROOT . '/rule-create-' . $safeRuleKey . '-' . $snapshotId . '.json';

        $payload['snapshot_id'] = $snapshotId;
        $payload['created_at'] = gmdate('c');

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') {
            return ['ok' => false];
        }

        $written = @file_put_contents($snapshotPath, $json . "\n");
        if ($written === false) {
            return ['ok' => false];
        }

        return [
            'ok' => true,
            'path_rel' => self::toRelativePath($snapshotPath),
        ];
    }

    /**
     * @param array<string,mixed> $ruleResource
     * @return array<string,mixed>
     */
    private static function runPostWriteDiagnostics(string $absolutePath, array $ruleResource, string $snapshotPath): array
    {
        $checks = [];

        $exists = is_file($absolutePath);
        self::addCheck($checks, 'RD01', 'rule_file_exists', 'Rule file path', $exists ? 'PASS' : 'FAIL', self::toRelativePath($absolutePath));

        $ownerKey = trim((string)($ruleResource['owner_key'] ?? ''));
        $ownerRoot = self::resolveOwnerRootByKey($ownerKey);
        $containmentOk = $ownerRoot !== '' && self::isPathInside($absolutePath, $ownerRoot);
        self::addCheck($checks, 'RD02', 'owner_containment', 'Owner containment', $containmentOk ? 'PASS' : 'FAIL', $ownerKey);

        $contextTemplateOk = trim((string)($ruleResource['context_key'] ?? '')) !== ''
            && trim((string)($ruleResource['template_key'] ?? '')) !== '';
        self::addCheck($checks, 'RD03', 'context_template_compatibility', 'Context/template compatibility', $contextTemplateOk ? 'PASS' : 'FAIL', 'Rule metadata references context and template keys.');

        $operator = trim((string)($ruleResource['conditions'][0]['operator'] ?? ''));
        $operatorValid = in_array($operator, self::ALLOWED_OPERATORS, true);
        self::addCheck($checks, 'RD04', 'operator_validity', 'Operator validity', $operatorValid ? 'PASS' : 'FAIL', $operator);

        $effectType = trim((string)($ruleResource['effects'][0]['type'] ?? ''));
        $effectValid = in_array($effectType, self::ALLOWED_EFFECT_TYPES, true);
        self::addCheck($checks, 'RD05', 'effect_validity', 'Effect validity', $effectValid ? 'PASS' : 'FAIL', $effectType);

        $duplicatePathBlocked = !is_file($absolutePath . '.tmp');
        self::addCheck($checks, 'RD06', 'duplicate_detection', 'Duplicate detection', $duplicatePathBlocked ? 'PASS' : 'WARN', 'Create-only path policy enforced.');

        $snapshotOk = $snapshotPath !== '';
        self::addCheck($checks, 'RD07', 'snapshot_created', 'Snapshot creation', $snapshotOk ? 'PASS' : 'FAIL', $snapshotPath);

        return [
            'checks' => $checks,
            'status' => self::overallSeverity($checks),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $checks
     * @param array<string,mixed> $selected
     * @param array<int,array<string,mixed>> $contexts
     * @param array<int,array<string,mixed>> $templates
     * @param array<int,string> $fieldKeys
     * @param array<string,array<int,string>> $effectTargetOptions
     * @param array<string,mixed>|null $rule
     * @return array<string,mixed>
     */
    private static function previewResult(
        array $checks,
        array $selected,
        array $contexts,
        array $templates,
        array $fieldKeys,
        array $effectTargetOptions,
        ?array $rule,
        string $targetPathRel,
        string $ruleJson,
        bool $validationStarted = true
    ): array {
        $errors = [];
        foreach ($checks as $check) {
            $severity = (string)($check['severity'] ?? 'PASS');
            if ($severity === 'FAIL' || $severity === 'ERROR') {
                $errors[] = (string)($check['message'] ?? 'Validation failed.');
            }
        }

        return [
            'ok' => !self::hasBlockingFailures($checks),
            'errors' => $errors,
            'diagnostics' => $checks,
            'diagnostics_overall' => self::overallSeverity($checks),
            'validation_started' => $validationStarted,
            'selected' => $selected,
            'contexts' => $contexts,
            'templates' => $templates,
            'context_field_keys' => $fieldKeys,
            'effect_target_options' => $effectTargetOptions,
            'target_path_rel' => $targetPathRel,
            'rule' => $rule,
            'rule_json' => $ruleJson,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private static function hasBlockingFailures(array $checks): bool
    {
        foreach ($checks as $check) {
            $severity = (string)($check['severity'] ?? 'PASS');
            if ($severity === 'FAIL' || $severity === 'ERROR') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private static function overallSeverity(array $checks): string
    {
        $worst = 'PASS';
        foreach ($checks as $check) {
            $severity = (string)($check['severity'] ?? 'PASS');
            if ((self::SEVERITY_RANK[$severity] ?? 0) > (self::SEVERITY_RANK[$worst] ?? 0)) {
                $worst = $severity;
            }
        }

        return $worst;
    }

    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private static function addCheck(array &$checks, string $ruleId, string $checkKey, string $label, string $severity, string $message = ''): void
    {
        $checks[] = [
            'rule_id' => $ruleId,
            'check_key' => $checkKey,
            'label' => $label,
            'severity' => $severity,
            'message' => $message,
        ];
    }

    private static function isPathInside(string $path, string $root): bool
    {
        $normalizedRoot = self::normalizePath($root);
        $normalizedPath = self::normalizePath($path);

        return $normalizedPath === $normalizedRoot || str_starts_with($normalizedPath, $normalizedRoot . '/');
    }

    private static function normalizePath(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }

    private static function toRelativePath(string $path): string
    {
        $root = realpath(APP_ROOT);
        $realPath = realpath($path);
        if (!is_string($root)) {
            return $path;
        }

        $normalized = is_string($realPath) ? $realPath : $path;
        $rootNormalized = str_replace('\\', '/', $root);
        $pathNormalized = str_replace('\\', '/', $normalized);

        if ($pathNormalized === $rootNormalized) {
            return '.';
        }

        if (str_starts_with($pathNormalized, $rootNormalized . '/')) {
            return substr($pathNormalized, strlen($rootNormalized) + 1);
        }

        return $path;
    }

    /**
     * @param array<string,mixed> $actor
     */
    private static function actorSummary(array $actor): array
    {
        return [
            'id' => (int)($actor['id'] ?? 0),
            'name' => (string)($actor['name'] ?? ''),
            'email' => (string)($actor['email'] ?? ''),
        ];
    }

    private static function ownerKeysEqual(string $a, string $b): bool
    {
        $normalize = static fn (string $key): string => strtolower(str_replace('\\', '/', trim($key)));
        $left = $normalize($a);
        $right = $normalize($b);
        return $left !== '' && $right !== '' && hash_equals($left, $right);
    }
}

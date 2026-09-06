<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\LabelDesigner\Services;

/**
 * Read-only validation proof for the future LabelRuntimeRequest contract.
 *
 * This service resolves existing owner resources, validates a fixed sample
 * request, and returns arrays only. It has no mutation or output pipeline role.
 */
final class LabelDesignerRuntimeDryRunValidator
{
    private const SCENARIO_TEMPLATE_FIELD_TO_REMOVE = 'product_name';

    private const ALLOWED_OUTPUT_TARGETS = ['preview_html'];

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

    private const FORBIDDEN_BEHAVIOR_PATTERNS = [
        '/\b(ex' . 'ec|shell_' . 'exec|sys' . 'tem|pass' . 'thru)\s*\(/i',
        '/\b(file_' . 'put_contents|fw' . 'rite|un' . 'link|re' . 'name|rm' . 'dir)\s*\(/i',
        '/\b(curl_exec|curl_init)\s*\(/i',
        '/\b(D' . 'B::|mysqli' . '_|P' . 'DO\s*\()/i',
        '/\b(print_label|execute_print|generate_qr|render_qr)\s*\(/i',
        '/\b(IN' . 'SERT\s+INTO|UP' . 'DATE\s+\S+\s+SET|DE' . 'LETE\s+FROM)\b/i',
    ];

    /**
     * @return array<string,mixed>
     */
    public static function sampleRequest(): array
    {
        return [
            'owner_key' => 'Manufacturing/Products',
            'context_key' => 'manufacturing.product.label',
            'template_key' => 'manufacturing.product.label.100x50_mm',
            'rules_enabled' => true,
            'data_payload' => [
                'product_name' => 'Phase 7 Sample Product',
                'product_code' => 'PHASE7-001',
                'batch_number' => 'BATCH-DRY-RUN',
                'manufacturing_date' => '2026-06-13',
                'expiry_date' => '2027-06-13',
                'quantity' => '100',
                'barcode' => 'PHASE7-001-BATCH-DRY-RUN',
            ],
            'output_target' => 'preview_html',
            'requested_by' => 'studio_dry_run',
            'request_source' => 'label_designer',
            'trace_id' => 'label-runtime-dry-run-manufacturing-products-v1',
            'dry_run' => true,
            'preview_mode' => true,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function validateSample(): array
    {
        return self::validate(self::sampleRequest());
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function sampleScenarios(): array
    {
        $base = self::sampleRequest();

        $missingContext = $base;
        $missingContext['context_key'] = 'manufacturing.product.missing';

        $missingTemplate = $base;
        $missingTemplate['template_key'] = 'manufacturing.product.label.missing';

        $ownerMismatch = $base;
        $ownerMismatch['owner_key'] = 'Manufacturing/Coverage';

        $missingRequiredPayloadField = $base;
        if (isset($missingRequiredPayloadField['data_payload']) && is_array($missingRequiredPayloadField['data_payload'])) {
            unset($missingRequiredPayloadField['data_payload'][self::SCENARIO_TEMPLATE_FIELD_TO_REMOVE]);
        }

        $payloadFieldNotAllowed = $base;
        if (!isset($payloadFieldNotAllowed['data_payload']) || !is_array($payloadFieldNotAllowed['data_payload'])) {
            $payloadFieldNotAllowed['data_payload'] = [];
        }
        $payloadFieldNotAllowed['data_payload']['secret_cost'] = '99999.99';

        $outputTargetNotAllowed = $base;
        $outputTargetNotAllowed['output_target'] = 'direct_print';

        $rulesDisabled = $base;
        $rulesDisabled['rules_enabled'] = false;

        $forbiddenBehaviorMarker = $base;
        $forbiddenBehaviorMarker['forbidden_marker'] = [
            'simulated_behavior' => 'execute_' . 'print' . '(' . '$payload)',
            'note' => 'in-memory fixture only',
        ];

        return [
            [
                'scenario_key' => 'missing_context',
                'name' => 'Missing context',
                'expected_result' => 'invalid',
                'expected_diagnostic_codes' => ['LRD005'],
                'request' => $missingContext,
            ],
            [
                'scenario_key' => 'missing_template',
                'name' => 'Missing template',
                'expected_result' => 'invalid',
                'expected_diagnostic_codes' => ['LRD006'],
                'request' => $missingTemplate,
            ],
            [
                'scenario_key' => 'owner_mismatch',
                'name' => 'Owner mismatch',
                'expected_result' => 'invalid',
                'expected_diagnostic_codes' => ['LRD007'],
                'request' => $ownerMismatch,
            ],
            [
                'scenario_key' => 'missing_required_payload_field',
                'name' => 'Missing required payload field',
                'expected_result' => 'invalid',
                'expected_diagnostic_codes' => ['LRD009'],
                'request' => $missingRequiredPayloadField,
            ],
            [
                'scenario_key' => 'payload_field_not_allowed',
                'name' => 'Payload field not allowed',
                'expected_result' => 'invalid',
                'expected_diagnostic_codes' => ['LRD010'],
                'request' => $payloadFieldNotAllowed,
            ],
            [
                'scenario_key' => 'output_target_not_allowed',
                'name' => 'Output target not allowed',
                'expected_result' => 'invalid',
                'expected_diagnostic_codes' => ['LRD011'],
                'request' => $outputTargetNotAllowed,
            ],
            [
                'scenario_key' => 'rules_disabled',
                'name' => 'Rules disabled',
                'expected_result' => 'valid',
                'expected_diagnostic_codes' => ['LRD012'],
                'expected_matched_rules_count' => 0,
                'request' => $rulesDisabled,
            ],
            [
                'scenario_key' => 'forbidden_behavior_marker',
                'name' => 'Forbidden behavior marker',
                'expected_result' => 'invalid',
                'expected_diagnostic_codes' => ['LRD014'],
                'request' => $forbiddenBehaviorMarker,
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function validateScenarioMatrix(): array
    {
        $rows = [];
        $passed = 0;
        $failed = 0;

        foreach (self::sampleScenarios() as $scenario) {
            if (!is_array($scenario)) {
                continue;
            }

            $request = isset($scenario['request']) && is_array($scenario['request'])
                ? $scenario['request']
                : [];
            $validation = self::validate($request);
            $summary = isset($validation['summary']) && is_array($validation['summary'])
                ? $validation['summary']
                : [];
            $diagnostics = isset($validation['diagnostics']) && is_array($validation['diagnostics'])
                ? array_values(array_filter($validation['diagnostics'], 'is_array'))
                : [];

            $actualResult = !empty($summary['request_valid']) ? 'valid' : 'invalid';
            $expectedResult = trim((string)($scenario['expected_result'] ?? 'invalid'));
            $expectedCodes = isset($scenario['expected_diagnostic_codes']) && is_array($scenario['expected_diagnostic_codes'])
                ? array_values(array_filter(array_map('strval', $scenario['expected_diagnostic_codes']), static fn(string $v): bool => $v !== ''))
                : [];
            $actualCodes = self::nonPassDiagnosticCodes($diagnostics);

            $expectedResultMet = $actualResult === $expectedResult;
            $expectedCodesMet = self::containsAllCodes($actualCodes, $expectedCodes);

            $matchedRulesExpected = array_key_exists('expected_matched_rules_count', $scenario)
                ? (int)$scenario['expected_matched_rules_count']
                : null;
            $matchedRulesActual = (int)($summary['matched_rules_count'] ?? 0);
            $matchedRulesMet = $matchedRulesExpected === null || $matchedRulesActual === $matchedRulesExpected;

            $expectationPassed = $expectedResultMet && $expectedCodesMet && $matchedRulesMet;
            if ($expectationPassed) {
                $passed++;
            } else {
                $failed++;
            }

            $rows[] = [
                'scenario_key' => (string)($scenario['scenario_key'] ?? ''),
                'scenario_name' => (string)($scenario['name'] ?? ''),
                'expected_result' => $expectedResult,
                'actual_result' => $actualResult,
                'expectation_passed' => $expectationPassed,
                'warning_count' => (int)($summary['warnings'] ?? 0),
                'error_count' => (int)($summary['errors'] ?? 0),
                'expected_diagnostic_codes' => $expectedCodes,
                'key_diagnostic_codes' => $actualCodes,
                'expected_matched_rules_count' => $matchedRulesExpected,
                'actual_matched_rules_count' => $matchedRulesActual,
            ];
        }

        return [
            'rows' => $rows,
            'summary' => [
                'total' => count($rows),
                'passed' => $passed,
                'failed' => $failed,
                'matrix_valid' => $failed === 0,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $request
     * @return array<string,mixed>
     */
    public static function validate(array $request): array
    {
        $diagnostics = [];
        $discovery = LabelDesignerDiscoveryService::discover();
        $ownerKey = trim((string)($request['owner_key'] ?? ''));
        $contextKey = trim((string)($request['context_key'] ?? ''));
        $templateKey = trim((string)($request['template_key'] ?? ''));
        $payload = isset($request['data_payload']) && is_array($request['data_payload'])
            ? $request['data_payload']
            : [];

        $requiredFields = [
            'owner_key',
            'context_key',
            'template_key',
            'rules_enabled',
            'data_payload',
            'output_target',
            'requested_by',
            'request_source',
            'trace_id',
            'dry_run',
            'preview_mode',
        ];
        $missingRequestFields = [];
        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $request) || $request[$field] === '' || $request[$field] === null) {
                $missingRequestFields[] = $field;
            }
        }
        self::addDiagnostic(
            $diagnostics,
            'LRD001',
            $missingRequestFields === [] ? 'pass' : 'error',
            $missingRequestFields === []
                ? 'LabelRuntimeRequest contains all required fields.'
                : 'Missing required request fields: ' . implode(', ', $missingRequestFields) . '.'
        );

        $dryRunMode = ($request['dry_run'] ?? null) === true && ($request['preview_mode'] ?? null) === true;
        self::addDiagnostic(
            $diagnostics,
            'LRD002',
            $dryRunMode ? 'pass' : 'error',
            $dryRunMode
                ? 'dry_run and preview_mode are both true; external output is disabled.'
                : 'dry_run and preview_mode must both be true.'
        );

        $owner = self::findOwner($discovery, $ownerKey);
        self::addDiagnostic(
            $diagnostics,
            'LRD003',
            $owner !== null ? 'pass' : 'error',
            $owner !== null ? 'Owner exists: ' . $ownerKey . '.' : 'Owner was not found: ' . $ownerKey . '.'
        );

        $lifecycleAllowed = $owner !== null && LabelDesignerResourceReadinessService::isLabelLifecycleOwner($ownerKey);
        self::addDiagnostic(
            $diagnostics,
            'LRD004',
            $lifecycleAllowed ? 'pass' : 'error',
            $lifecycleAllowed
                ? 'Owner lifecycle permits label resources.'
                : 'Owner lifecycle does not permit label resources.'
        );

        $contextRecord = $owner !== null
            ? self::findResource($owner, 'contexts', 'context_key', $contextKey)
            : null;
        $context = $contextRecord !== null ? self::readJson((string)$contextRecord['path']) : null;
        self::addDiagnostic(
            $diagnostics,
            'LRD005',
            $context !== null ? 'pass' : 'error',
            $context !== null ? 'Context resolved from the owner resource path.' : 'Context could not be resolved.'
        );

        $templateRecord = $owner !== null
            ? self::findResource($owner, 'templates', 'template_key', $templateKey)
            : null;
        $template = $templateRecord !== null ? self::readJson((string)$templateRecord['path']) : null;
        self::addDiagnostic(
            $diagnostics,
            'LRD006',
            $template !== null ? 'pass' : 'error',
            $template !== null ? 'Template resolved from the owner resource path.' : 'Template could not be resolved.'
        );

        $contextOwner = is_array($context) ? trim((string)($context['owner_key'] ?? '')) : '';
        $templateOwner = is_array($template) ? trim((string)($template['owner_key'] ?? '')) : '';
        $templateContext = is_array($template) && isset($template['context_ref']) && is_array($template['context_ref'])
            ? trim((string)($template['context_ref']['context_key'] ?? ''))
            : '';
        $ownerCompatible = self::ownerKeysEqual($contextOwner, $ownerKey)
            && self::ownerKeysEqual($templateOwner, $ownerKey)
            && $templateContext === $contextKey;
        self::addDiagnostic(
            $diagnostics,
            'LRD007',
            $ownerCompatible ? 'pass' : 'error',
            $ownerCompatible
                ? 'Context, template, and request ownership/context references are compatible.'
                : 'Context/template ownership or context reference is incompatible.'
        );

        $contextFields = self::fieldKeys(is_array($context) ? ($context['allowed_fields'] ?? []) : []);
        $templateFields = self::fieldKeys(is_array($template) ? ($template['fields'] ?? []) : []);
        $templateOutsideContext = array_values(array_diff($templateFields, $contextFields));
        self::addDiagnostic(
            $diagnostics,
            'LRD008',
            $templateOutsideContext === [] ? 'pass' : 'error',
            $templateOutsideContext === []
                ? 'All template fields are allowed by the context.'
                : 'Template fields outside context allowed_fields: ' . implode(', ', $templateOutsideContext) . '.'
        );

        $payloadKeys = array_values(array_map('strval', array_keys($payload)));
        $missingPayloadFields = array_values(array_diff($templateFields, $payloadKeys));
        self::addDiagnostic(
            $diagnostics,
            'LRD009',
            $missingPayloadFields === [] ? 'pass' : 'error',
            $missingPayloadFields === []
                ? 'Payload contains every template-selected field.'
                : 'Payload is missing selected fields: ' . implode(', ', $missingPayloadFields) . '.'
        );

        $payloadOutsideContext = array_values(array_diff($payloadKeys, $contextFields));
        self::addDiagnostic(
            $diagnostics,
            'LRD010',
            $payloadOutsideContext === [] ? 'pass' : 'error',
            $payloadOutsideContext === []
                ? 'All payload fields are allowed by the context.'
                : 'Payload fields outside context allowed_fields: ' . implode(', ', $payloadOutsideContext) . '.'
        );

        $outputTarget = trim((string)($request['output_target'] ?? ''));
        $targetAllowed = in_array($outputTarget, self::ALLOWED_OUTPUT_TARGETS, true);
        self::addDiagnostic(
            $diagnostics,
            'LRD011',
            $targetAllowed ? 'pass' : 'error',
            $targetAllowed
                ? 'Output target is allowed for validation-only preview: ' . $outputTarget . '.'
                : 'Output target is not allowed for this dry run: ' . $outputTarget . '.'
        );

        $rulesEnabled = ($request['rules_enabled'] ?? false) === true;
        $matchingRules = [];
        $ruleResourcesValid = true;
        if ($rulesEnabled && $owner !== null) {
            foreach (self::resourceFiles($owner, 'rules') as $ruleFile) {
                $rule = self::readJson((string)($ruleFile['path'] ?? ''));
                if ($rule === null) {
                    $ruleResourcesValid = false;
                    continue;
                }
                if (($rule['enabled'] ?? true) !== true) {
                    continue;
                }
                if (
                    self::ownerKeysEqual((string)($rule['owner_key'] ?? ''), $ownerKey)
                    && trim((string)($rule['context_key'] ?? '')) === $contextKey
                    && trim((string)($rule['template_key'] ?? '')) === $templateKey
                ) {
                    $matchingRules[] = $rule;
                }
            }
        }
        $rulesResolved = !$rulesEnabled
            || ($ruleResourcesValid && $matchingRules !== []);
        $rulesSeverity = $rulesEnabled ? ($rulesResolved ? 'pass' : 'error') : 'info';
        self::addDiagnostic(
            $diagnostics,
            'LRD012',
            $rulesSeverity,
            $rulesEnabled
                ? ($rulesResolved
                    ? count($matchingRules) . ' enabled compatible rule resource(s) resolved.'
                    : 'Enabled rule resources did not resolve cleanly.')
                : 'Rule resolution is intentionally disabled for this request.'
        );

        $rulesPresentationOnly = true;
        $matchedRuleCount = 0;
        foreach ($matchingRules as $rule) {
            $conditions = isset($rule['conditions']) && is_array($rule['conditions']) ? $rule['conditions'] : [];
            $effects = isset($rule['effects']) && is_array($rule['effects']) ? $rule['effects'] : [];
            if ($conditions === [] || $effects === []) {
                $rulesPresentationOnly = false;
                continue;
            }

            foreach ($conditions as $condition) {
                if (!is_array($condition)) {
                    $rulesPresentationOnly = false;
                    continue;
                }
                $operator = trim((string)($condition['operator'] ?? ''));
                $fieldKey = trim((string)($condition['field_key'] ?? ''));
                if (!in_array($operator, self::ALLOWED_OPERATORS, true) || !in_array($fieldKey, $contextFields, true)) {
                    $rulesPresentationOnly = false;
                }
            }
            foreach ($effects as $effect) {
                if (!is_array($effect) || !in_array((string)($effect['type'] ?? ''), self::ALLOWED_EFFECT_TYPES, true)) {
                    $rulesPresentationOnly = false;
                }
            }

            if ($rulesPresentationOnly && self::conditionsMatch($conditions, $payload)) {
                $matchedRuleCount++;
            }
        }
        self::addDiagnostic(
            $diagnostics,
            'LRD013',
            $rulesPresentationOnly ? 'pass' : 'error',
            $rulesPresentationOnly
                ? 'Rule operators and effects are within the presentation-only allowlists.'
                : 'A rule contains an unsupported operator, field, or effect.'
        );

        $resources = array_values(array_filter([$context, $template, ...$matchingRules], 'is_array'));
        if (isset($request['forbidden_marker']) && is_array($request['forbidden_marker'])) {
            $resources[] = $request['forbidden_marker'];
        }
        $forbiddenBehaviorAbsent = self::forbiddenBehaviorAbsent($resources);
        self::addDiagnostic(
            $diagnostics,
            'LRD014',
            $forbiddenBehaviorAbsent ? 'pass' : 'error',
            $forbiddenBehaviorAbsent
                ? 'No forbidden DB, HTTP, write, print, QR, or executable behavior was found.'
                : 'Forbidden runtime behavior was detected in the request resources.'
        );

        $auditFieldsPresent = trim((string)($request['requested_by'] ?? '')) !== ''
            && trim((string)($request['request_source'] ?? '')) !== ''
            && trim((string)($request['trace_id'] ?? '')) !== '';
        self::addDiagnostic(
            $diagnostics,
            'LRD015',
            $auditFieldsPresent ? 'pass' : 'error',
            $auditFieldsPresent
                ? 'requested_by, request_source, and trace_id are present.'
                : 'Trace/audit request fields are incomplete.'
        );

        self::addDiagnostic(
            $diagnostics,
            'LRD016',
            'info',
            'Validation only: no label output, external integration, database access, or file mutation occurred.'
        );

        $counts = ['pass' => 0, 'info' => 0, 'warning' => 0, 'error' => 0];
        foreach ($diagnostics as $diagnostic) {
            $severity = (string)($diagnostic['severity'] ?? 'error');
            if (array_key_exists($severity, $counts)) {
                $counts[$severity]++;
            }
        }

        return [
            'request' => $request,
            'diagnostics' => $diagnostics,
            'summary' => [
                'request_valid' => $counts['warning'] === 0 && $counts['error'] === 0,
                'passes' => $counts['pass'],
                'info' => $counts['info'],
                'warnings' => $counts['warning'],
                'errors' => $counts['error'],
                'matched_rules_count' => $matchedRuleCount,
                'payload_fields_checked' => count($payloadKeys),
                'output_target' => $outputTarget,
                'dry_run' => ($request['dry_run'] ?? false) === true,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $discovery
     * @return array<string,mixed>|null
     */
    private static function findOwner(array $discovery, string $ownerKey): ?array
    {
        $owners = isset($discovery['owners']) && is_array($discovery['owners']) ? $discovery['owners'] : [];
        foreach ($owners as $owner) {
            if (is_array($owner) && self::ownerKeysEqual((string)($owner['owner_key'] ?? ''), $ownerKey)) {
                return $owner;
            }
        }
        return null;
    }

    /**
     * @param array<string,mixed> $owner
     * @return array<string,mixed>|null
     */
    private static function findResource(array $owner, string $type, string $keyField, string $key): ?array
    {
        foreach (self::resourceFiles($owner, $type) as $file) {
            $decoded = self::readJson((string)($file['path'] ?? ''));
            if ($decoded !== null && trim((string)($decoded[$keyField] ?? '')) === $key) {
                return $file;
            }
        }
        return null;
    }

    /**
     * @param array<string,mixed> $owner
     * @return array<int,array<string,mixed>>
     */
    private static function resourceFiles(array $owner, string $type): array
    {
        $resources = isset($owner['resources']) && is_array($owner['resources']) ? $owner['resources'] : [];
        return isset($resources[$type]['files']) && is_array($resources[$type]['files'])
            ? array_values(array_filter($resources[$type]['files'], 'is_array'))
            : [];
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function readJson(string $relativePath): ?array
    {
        $root = realpath(APP_ROOT);
        $absolute = realpath(APP_ROOT . '/' . ltrim($relativePath, '/'));
        if (
            !is_string($root)
            || !is_string($absolute)
            || !str_starts_with($absolute, $root . '/')
            || !is_file($absolute)
            || strtolower(pathinfo($absolute, PATHINFO_EXTENSION)) !== 'json'
        ) {
            return null;
        }

        $raw = file_get_contents($absolute);
        if (!is_string($raw)) {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<int,string>
     */
    private static function fieldKeys(mixed $fields): array
    {
        if (!is_array($fields)) {
            return [];
        }
        $keys = [];
        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }
            $key = trim((string)($field['field_key'] ?? ''));
            if ($key !== '') {
                $keys[$key] = $key;
            }
        }
        return array_values($keys);
    }

    /**
     * @param array<int,mixed> $conditions
     * @param array<string,mixed> $payload
     */
    private static function conditionsMatch(array $conditions, array $payload): bool
    {
        foreach ($conditions as $condition) {
            if (!is_array($condition)) {
                return false;
            }
            $fieldKey = trim((string)($condition['field_key'] ?? ''));
            $operator = trim((string)($condition['operator'] ?? ''));
            $actual = (string)($payload[$fieldKey] ?? '');
            $expected = (string)($condition['value'] ?? '');

            $matches = match ($operator) {
                'equals' => $actual === $expected,
                'not_equals' => $actual !== $expected,
                'empty' => $actual === '',
                'not_empty' => $actual !== '',
                'greater_than' => is_numeric($actual) && is_numeric($expected) && (float)$actual > (float)$expected,
                'less_than' => is_numeric($actual) && is_numeric($expected) && (float)$actual < (float)$expected,
                'contains' => str_contains($actual, $expected),
                default => false,
            };
            if (!$matches) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array<int,array<string,mixed>> $resources
     */
    private static function forbiddenBehaviorAbsent(array $resources): bool
    {
        $serialized = json_encode($resources, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($serialized)) {
            return false;
        }
        foreach (self::FORBIDDEN_BEHAVIOR_PATTERNS as $pattern) {
            if (preg_match($pattern, $serialized) === 1) {
                return false;
            }
        }
        return true;
    }

    private static function ownerKeysEqual(string $left, string $right): bool
    {
        return strtolower(trim($left)) === strtolower(trim($right));
    }

    /**
     * @param array<int,array<string,mixed>> $diagnostics
     * @return array<int,string>
     */
    private static function nonPassDiagnosticCodes(array $diagnostics): array
    {
        $codes = [];
        foreach ($diagnostics as $diagnostic) {
            $severity = strtolower(trim((string)($diagnostic['severity'] ?? '')));
            if ($severity === '' || $severity === 'pass') {
                continue;
            }
            $code = trim((string)($diagnostic['code'] ?? ''));
            if ($code === '') {
                continue;
            }
            $codes[$code] = $code;
        }

        return array_values($codes);
    }

    /**
     * @param array<int,string> $actualCodes
     * @param array<int,string> $expectedCodes
     */
    private static function containsAllCodes(array $actualCodes, array $expectedCodes): bool
    {
        if ($expectedCodes === []) {
            return true;
        }

        $actualMap = [];
        foreach ($actualCodes as $code) {
            $actualMap[$code] = true;
        }

        foreach ($expectedCodes as $code) {
            if (!isset($actualMap[$code])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int,array{severity:string,code:string,message:string}> $diagnostics
     */
    private static function addDiagnostic(array &$diagnostics, string $code, string $severity, string $message): void
    {
        $diagnostics[] = [
            'severity' => $severity,
            'code' => $code,
            'message' => $message,
        ];
    }
}

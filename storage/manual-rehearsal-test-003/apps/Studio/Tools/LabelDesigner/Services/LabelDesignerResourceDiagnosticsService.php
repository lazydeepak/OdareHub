<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\LabelDesigner\Services;

final class LabelDesignerResourceDiagnosticsService
{
    public const SCHEMA_CONTEXT = 'susankhya.label.context.v1';
    public const SCHEMA_TEMPLATE = 'susankhya.label.template.v1';
    public const SCHEMA_RULE = 'susankhya.label.rule.v1';

    private const ALLOWED_OPERATORS = [
        'equals', 'not_equals', 'empty', 'not_empty',
        'greater_than', 'less_than', 'contains',
    ];

    private const ALLOWED_EFFECT_TYPES = [
        'show_badge', 'hide_field', 'show_warning', 'set_style_token',
    ];

    private const FORBIDDEN_RUNTIME_PATTERNS = [
        '/exec\(/i', '/shell_exec\(/i', '/system\(/i', '/passthru\(/i',
        '/file_put_contents\(/i', '/fwrite\(/i', '/fopen\(.*[^r\s]/i',
        '/unlink\(/i', '/rename\(/i', '/rmdir\(/i',
        '/curl_exec\(/i', '/curl_init\(/i',
        '/DB::/i', '/db_select\(/i', '/db_insert\(/i', '/db_update\(/i', '/db_delete\(/i',
        '/mysqli_/i', '/PDO\(/i',
        '/print_label\(/i', '/generate_qr\(/i', '/render_qr\(/i',
        '/header\(.*Content-Type.*image/i',
        '/imagecreate\(/i', '/imagettftext\(/i',
    ];

    public static function scanAll(): array
    {
        $discovery = LabelDesignerDiscoveryService::discover();
        $owners = $discovery['owners'] ?? [];
        $allDiagnostics = [];
        $scanned = ['owners' => 0, 'contexts' => 0, 'templates' => 0, 'rules' => 0];
        $counts = ['pass' => 0, 'info' => 0, 'warning' => 0, 'error' => 0];
        $legacyFallbackCount = 0;
        $byOwner = [];

        foreach ($owners as $owner) {
            $ownerKey = (string)($owner['owner_key'] ?? '');
            $ownerRoot = (string)($owner['root_path'] ?? '');
            $scanned['owners']++;

            $ownerGroup = [
                'owner_key' => $ownerKey,
                'owner_type' => (string)($owner['owner_type'] ?? ''),
                'root_path' => $ownerRoot,
                'resources' => [],
            ];

            $resources = isset($owner['resources']) && is_array($owner['resources']) ? $owner['resources'] : [];

            $contextFiles = isset($resources['contexts']['files']) && is_array($resources['contexts']['files'])
                ? $resources['contexts']['files'] : [];
            foreach ($contextFiles as $file) {
                $path = (string)($file['path'] ?? '');
                $diags = self::diagnoseContext(APP_ROOT . '/' . ltrim($path, '/'), $path, $ownerKey);
                $resourceKey = '';
                foreach ($diags as $d) {
                    if ($d['code'] === 'LD_CTX_KEY_VALID') {
                        $resourceKey = (string)($d['resource_key'] ?? '');
                        break;
                    }
                }
                $legacyFallbackCount += self::countLegacyFallback($diags);
                self::tallySeverity($diags, $counts);
                $scanned['contexts']++;
                $ownerGroup['resources'][] = [
                    'resource_type' => 'context',
                    'resource_key' => $resourceKey,
                    'path' => $path,
                    'diagnostics' => $diags,
                ];
                $allDiagnostics = array_merge($allDiagnostics, $diags);
            }

            $templateFiles = isset($resources['templates']['files']) && is_array($resources['templates']['files'])
                ? $resources['templates']['files'] : [];
            $contextIndex = self::buildContextIndex($contextFiles);
            foreach ($templateFiles as $file) {
                $path = (string)($file['path'] ?? '');
                $diags = self::diagnoseTemplate(APP_ROOT . '/' . ltrim($path, '/'), $path, $ownerKey, $contextIndex);
                $resourceKey = '';
                foreach ($diags as $d) {
                    if ($d['code'] === 'LD_TPL_KEY_VALID') {
                        $resourceKey = (string)($d['resource_key'] ?? '');
                        break;
                    }
                }
                $legacyFallbackCount += self::countLegacyFallback($diags);
                self::tallySeverity($diags, $counts);
                $scanned['templates']++;
                $ownerGroup['resources'][] = [
                    'resource_type' => 'template',
                    'resource_key' => $resourceKey,
                    'path' => $path,
                    'diagnostics' => $diags,
                ];
                $allDiagnostics = array_merge($allDiagnostics, $diags);
            }

            $ruleFiles = isset($resources['rules']['files']) && is_array($resources['rules']['files'])
                ? $resources['rules']['files'] : [];
            $templateIndex = self::buildTemplateIndex($templateFiles);
            foreach ($ruleFiles as $file) {
                $path = (string)($file['path'] ?? '');
                $diags = self::diagnoseRule(APP_ROOT . '/' . ltrim($path, '/'), $path, $ownerKey, $contextIndex, $templateIndex);
                $resourceKey = '';
                foreach ($diags as $d) {
                    if ($d['code'] === 'LD_RULE_KEY_VALID') {
                        $resourceKey = (string)($d['resource_key'] ?? '');
                        break;
                    }
                }
                $legacyFallbackCount += self::countLegacyFallback($diags);
                self::tallySeverity($diags, $counts);
                $scanned['rules']++;
                $ownerGroup['resources'][] = [
                    'resource_type' => 'rule',
                    'resource_key' => $resourceKey,
                    'path' => $path,
                    'diagnostics' => $diags,
                ];
                $allDiagnostics = array_merge($allDiagnostics, $diags);
            }

            $byOwner[] = $ownerGroup;
        }

        $maxSeverity = 'pass';
        foreach ($allDiagnostics as $d) {
            $s = (string)($d['severity'] ?? 'pass');
            if ($s === 'error') { $maxSeverity = 'error'; break; }
            if ($s === 'warning' && $maxSeverity !== 'error') { $maxSeverity = 'warning'; }
            if ($s === 'info' && $maxSeverity === 'pass') { $maxSeverity = 'info'; }
        }

        return [
            'ok' => true,
            'scanned' => $scanned,
            'counts' => $counts,
            'legacy_fallback_count' => $legacyFallbackCount,
            'by_owner' => $byOwner,
            'all_diagnostics' => $allDiagnostics,
            'max_severity' => $maxSeverity,
            'severity_order' => ['pass', 'info', 'warning', 'error'],
        ];
    }

    private static function diagnoseContext(string $absPath, string $relPath, string $ownerKey): array
    {
        $diags = [];
        $raw = self::safeRead($absPath);
        if ($raw === null) {
            $diags[] = self::d('LD_CTX_JSON_INVALID', 'error', 'context', $relPath, $ownerKey, 'Cannot read or parse file — not valid JSON.');
            return $diags;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $diags[] = self::d('LD_CTX_JSON_INVALID', 'error', 'context', $relPath, $ownerKey, 'File content is not valid JSON.');
            return $diags;
        }

        $schema = (string)($decoded['schema'] ?? '');
        if ($schema !== self::SCHEMA_CONTEXT) {
            $diags[] = self::d('LD_CTX_SCHEMA_INVALID', 'error', 'context', $relPath, $ownerKey, 'Schema is "' . $schema . '", expected "' . self::SCHEMA_CONTEXT . '".');
            return $diags;
        }
        $diags[] = self::d('LD_CTX_SCHEMA_VALID', 'pass', 'context', $relPath, $ownerKey, 'Schema is "' . self::SCHEMA_CONTEXT . '".');

        $ctxKey = trim((string)($decoded['context_key'] ?? ''));
        if ($ctxKey === '') {
            $diags[] = self::d('LD_CTX_KEY_MISSING', 'error', 'context', $relPath, $ownerKey, 'context_key is missing or empty.');
        } else {
            if (preg_match('/^[a-z][a-z0-9._-]+$/', $ctxKey) !== 1) {
                $diags[] = self::d('LD_CTX_KEY_FORMAT_INVALID', 'warning', 'context', $ctxKey, $relPath, 'context_key "' . $ctxKey . '" does not match expected format (lowercase alphanumeric with dots/hyphens).');
            } else {
                $diags[] = self::d('LD_CTX_KEY_VALID', 'pass', 'context', $ctxKey, $relPath, 'context_key is valid: "' . $ctxKey . '".');
            }
        }

        $hasOwnerKey = !empty($decoded['owner_key']);
        if (!$hasOwnerKey) {
            $diags[] = self::d('LD_CTX_OWNER_LEGACY_FALLBACK', 'warning', 'context', $ctxKey ?: $relPath, $relPath, 'No owner_key metadata — legacy fallback mode. Add owner_key to align with architecture contract.');
        } else {
            $diags[] = self::d('LD_CTX_OWNER_PRESENT', 'pass', 'context', $ctxKey ?: $relPath, $relPath, 'owner_key metadata present.');
        }

        $fields = isset($decoded['allowed_fields']) && is_array($decoded['allowed_fields'])
            ? array_values(array_filter($decoded['allowed_fields'], 'is_array'))
            : [];
        if (!isset($decoded['allowed_fields']) || !is_array($decoded['allowed_fields'])) {
            $diags[] = self::d('LD_CTX_ALLOWED_FIELDS_MISSING', 'error', 'context', $ctxKey ?: $relPath, $relPath, 'allowed_fields is missing or not an array.');
        } elseif (empty($fields)) {
            $diags[] = self::d('LD_CTX_ALLOWED_FIELDS_EMPTY', 'warning', 'context', $ctxKey ?: $relPath, $relPath, 'allowed_fields exists but is empty.');
        } else {
            $diags[] = self::d('LD_CTX_ALLOWED_FIELDS_PRESENT', 'pass', 'context', $ctxKey ?: $relPath, $relPath, 'allowed_fields has ' . count($fields) . ' field(s).');

            $seenKeys = [];
            foreach ($fields as $fi => $field) {
                $fieldKey = trim((string)($field['field_key'] ?? ''));
                if ($fieldKey === '') {
                    $diags[] = self::d('LD_CTX_FIELD_KEY_MISSING', 'warning', 'context', $ctxKey ?: $relPath, $relPath, 'Field at index ' . $fi . ' has no field_key.');
                } elseif (isset($seenKeys[$fieldKey])) {
                    $diags[] = self::d('LD_CTX_FIELD_DUPLICATE', 'error', 'context', $ctxKey ?: $relPath, $relPath, 'Duplicate field_key "' . $fieldKey . '" at index ' . $fi . '.');
                } else {
                    $seenKeys[$fieldKey] = true;
                }
            }

            if (count($seenKeys) === count($fields)) {
                $diags[] = self::d('LD_CTX_FIELD_KEYS_UNIQUE', 'pass', 'context', $ctxKey ?: $relPath, $relPath, 'All ' . count($fields) . ' field_key values are unique.');
            }

            $noSourceCol = 0;
            $noDataType = 0;
            foreach ($fields as $field) {
                if (empty($field['source_column'])) { $noSourceCol++; }
                if (empty($field['data_type'])) { $noDataType++; }
            }
            if ($noSourceCol > 0) {
                $diags[] = self::d('LD_CTX_SOURCE_COLUMN_MISSING', 'info', 'context', $ctxKey ?: $relPath, $relPath, $noSourceCol . ' field(s) missing source_column metadata.');
            } else {
                $diags[] = self::d('LD_CTX_SOURCE_COLUMN_PRESENT', 'pass', 'context', $ctxKey ?: $relPath, $relPath, 'All fields have source_column metadata.');
            }
            if ($noDataType > 0) {
                $diags[] = self::d('LD_CTX_DATA_TYPE_MISSING', 'info', 'context', $ctxKey ?: $relPath, $relPath, $noDataType . ' field(s) missing data_type metadata.');
            } else {
                $diags[] = self::d('LD_CTX_DATA_TYPE_PRESENT', 'pass', 'context', $ctxKey ?: $relPath, $relPath, 'All fields have data_type metadata.');
            }
        }

        $hasBoundary = isset($decoded['data_source_boundary']) && is_array($decoded['data_source_boundary']);
        if (!$hasBoundary) {
            $diags[] = self::d('LD_CTX_DATA_SOURCE_BOUNDARY_MISSING', 'info', 'context', $ctxKey ?: $relPath, $relPath, 'data_source_boundary is missing — expected for Phase 2+ data binding.');
        } else {
            $diags[] = self::d('LD_CTX_DATA_SOURCE_BOUNDARY_PRESENT', 'pass', 'context', $ctxKey ?: $relPath, $relPath, 'data_source_boundary present.');
        }

        $diags = array_merge($diags, self::checkForbiddenBehavior($decoded, 'context', $ctxKey ?: $relPath, $relPath));

        return $diags;
    }

    private static function diagnoseTemplate(string $absPath, string $relPath, string $ownerKey, array $contextIndex): array
    {
        $diags = [];
        $raw = self::safeRead($absPath);
        if ($raw === null) {
            $diags[] = self::d('LD_TPL_JSON_INVALID', 'error', 'template', $relPath, $ownerKey, 'Cannot read or parse file — not valid JSON.');
            return $diags;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $diags[] = self::d('LD_TPL_JSON_INVALID', 'error', 'template', $relPath, $ownerKey, 'File content is not valid JSON.');
            return $diags;
        }

        $schema = (string)($decoded['schema'] ?? '');
        if ($schema !== self::SCHEMA_TEMPLATE) {
            $diags[] = self::d('LD_TPL_SCHEMA_INVALID', 'error', 'template', $relPath, $ownerKey, 'Schema is "' . $schema . '", expected "' . self::SCHEMA_TEMPLATE . '".');
            return $diags;
        }
        $diags[] = self::d('LD_TPL_SCHEMA_VALID', 'pass', 'template', $relPath, $ownerKey, 'Schema is "' . self::SCHEMA_TEMPLATE . '".');

        $tplKey = trim((string)($decoded['template_key'] ?? ''));
        if ($tplKey === '') {
            $diags[] = self::d('LD_TPL_KEY_MISSING', 'error', 'template', $relPath, $ownerKey, 'template_key is missing or empty.');
        } else {
            if (preg_match('/^[a-z][a-z0-9._-]+$/', $tplKey) !== 1) {
                $diags[] = self::d('LD_TPL_KEY_FORMAT_INVALID', 'warning', 'template', $tplKey, $relPath, 'template_key "' . $tplKey . '" does not match expected format.');
            } else {
                $diags[] = self::d('LD_TPL_KEY_VALID', 'pass', 'template', $tplKey, $relPath, 'template_key is valid: "' . $tplKey . '".');
            }
        }

        if (empty($decoded['owner_key'])) {
            $diags[] = self::d('LD_TPL_OWNER_LEGACY_FALLBACK', 'warning', 'template', $tplKey ?: $relPath, $relPath, 'No top-level owner_key metadata — legacy fallback mode. Add owner_key to align with architecture contract.');
        } else {
            $diags[] = self::d('LD_TPL_OWNER_PRESENT', 'pass', 'template', $tplKey ?: $relPath, $relPath, 'Top-level owner_key metadata present.');
        }

        $contextRef = isset($decoded['context_ref']) && is_array($decoded['context_ref'])
            ? $decoded['context_ref'] : [];
        $refOwnerKey = (string)($contextRef['owner_key'] ?? '');
        $refContextKey = (string)($contextRef['context_key'] ?? '');
        $refContextFile = (string)($contextRef['context_file'] ?? '');

        if (empty($contextRef)) {
            $diags[] = self::d('LD_TPL_CONTEXT_REF_MISSING', 'error', 'template', $tplKey ?: $relPath, $relPath, 'context_ref is missing or empty.');
        } else {
            $diags[] = self::d('LD_TPL_CONTEXT_REF_PRESENT', 'pass', 'template', $tplKey ?: $relPath, $relPath, 'context_ref references "' . $refContextKey . '" owner "' . $refOwnerKey . '".');
        }

        $resolved = self::resolveContextRef($contextIndex, $refOwnerKey, $refContextKey);
        if ($resolved === null) {
            $diags[] = self::d('LD_TPL_CONTEXT_UNRESOLVED', 'warning', 'template', $tplKey ?: $relPath, $relPath, 'Referenced context "' . $refContextKey . '" for owner "' . $refOwnerKey . '" could not be resolved among scanned contexts.');
        } else {
            $diags[] = self::d('LD_TPL_CONTEXT_RESOLVED', 'pass', 'template', $tplKey ?: $relPath, $relPath, 'Referenced context "' . $refContextKey . '" resolved.');

            if ($refOwnerKey !== '' && $refOwnerKey !== $ownerKey) {
                $diags[] = self::d('LD_TPL_OWNER_MISMATCH', 'warning', 'template', $tplKey ?: $relPath, $relPath, 'Template owner "' . $ownerKey . '" differs from context owner "' . $refOwnerKey . '".');
            } else {
                $diags[] = self::d('LD_TPL_OWNER_COMPATIBLE', 'pass', 'template', $tplKey ?: $relPath, $relPath, 'Template and context have compatible owners.');
            }
        }

        $fields = isset($decoded['fields']) && is_array($decoded['fields'])
            ? array_values(array_filter($decoded['fields'], 'is_array')) : [];
        if (!isset($decoded['fields']) || !is_array($decoded['fields'])) {
            $diags[] = self::d('LD_TPL_FIELDS_MISSING', 'error', 'template', $tplKey ?: $relPath, $relPath, 'fields is missing or not an array.');
        } elseif (empty($fields)) {
            $diags[] = self::d('LD_TPL_FIELDS_EMPTY', 'warning', 'template', $tplKey ?: $relPath, $relPath, 'fields array is empty.');
        } else {
            $contextFields = $resolved !== null && isset($resolved['allowed_fields']) && is_array($resolved['allowed_fields'])
                ? array_values(array_filter($resolved['allowed_fields'], 'is_array')) : [];
            $contextFieldKeys = [];
            foreach ($contextFields as $cf) {
                $contextFieldKeys[] = trim((string)($cf['field_key'] ?? ''));
            }

            $unknownFields = [];
            foreach ($fields as $field) {
                $fk = trim((string)($field['field_key'] ?? ''));
                if ($fk !== '' && !empty($contextFieldKeys) && !in_array($fk, $contextFieldKeys, true)) {
                    $unknownFields[] = $fk;
                }
            }
            if (!empty($unknownFields)) {
                $diags[] = self::d('LD_TPL_FIELD_UNKNOWN', 'warning', 'template', $tplKey ?: $relPath, $relPath, count($unknownFields) . ' field(s) not found in context allowed_fields: ' . implode(', ', $unknownFields));
            } else {
                $diags[] = self::d('LD_TPL_FIELDS_VALID', 'pass', 'template', $tplKey ?: $relPath, $relPath, 'All template fields reference valid context fields.');
            }

            $diags[] = self::d('LD_TPL_FIELDS_PRESENT', 'pass', 'template', $tplKey ?: $relPath, $relPath, 'fields has ' . count($fields) . ' field(s).');
        }

        $layout = isset($decoded['layout']) && is_array($decoded['layout']) ? $decoded['layout'] : [];
        if (empty($layout)) {
            $diags[] = self::d('LD_TPL_LAYOUT_MISSING', 'error', 'template', $tplKey ?: $relPath, $relPath, 'layout is missing or empty.');
        } else {
            $diags[] = self::d('LD_TPL_LAYOUT_PRESENT', 'pass', 'template', $tplKey ?: $relPath, $relPath, 'layout section present.');

            $blocks = isset($layout['blocks']) && is_array($layout['blocks'])
                ? array_values(array_filter($layout['blocks'], 'is_array')) : [];
            if (empty($blocks)) {
                $diags[] = self::d('LD_TPL_BLOCKS_MISSING', 'error', 'template', $tplKey ?: $relPath, $relPath, 'layout.blocks is missing or empty.');
            } else {
                $diags[] = self::d('LD_TPL_BLOCKS_PRESENT', 'pass', 'template', $tplKey ?: $relPath, $relPath, 'layout.blocks has ' . count($blocks) . ' block(s).');

                $seenBlockKeys = [];
                foreach ($blocks as $bi => $block) {
                    $bk = trim((string)($block['block_key'] ?? ''));
                    if ($bk === '') { continue; }
                    if (isset($seenBlockKeys[$bk])) {
                        $diags[] = self::d('LD_TPL_BLOCK_DUPLICATE', 'error', 'template', $tplKey ?: $relPath, $relPath, 'Duplicate block_key "' . $bk . '" at index ' . $bi . '.');
                    }
                    $seenBlockKeys[$bk] = true;
                }
                if (count($seenBlockKeys) === count($blocks)) {
                    $diags[] = self::d('LD_TPL_BLOCK_KEYS_UNIQUE', 'pass', 'template', $tplKey ?: $relPath, $relPath, 'All ' . count($blocks) . ' block_key values are unique.');
                }
            }
        }

        $diags = array_merge($diags, self::checkForbiddenBehavior($decoded, 'template', $tplKey ?: $relPath, $relPath));

        return $diags;
    }

    private static function diagnoseRule(string $absPath, string $relPath, string $ownerKey, array $contextIndex, array $templateIndex): array
    {
        $diags = [];
        $raw = self::safeRead($absPath);
        if ($raw === null) {
            $diags[] = self::d('LD_RULE_JSON_INVALID', 'error', 'rule', $relPath, $ownerKey, 'Cannot read or parse file — not valid JSON.');
            return $diags;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $diags[] = self::d('LD_RULE_JSON_INVALID', 'error', 'rule', $relPath, $ownerKey, 'File content is not valid JSON.');
            return $diags;
        }

        $schema = (string)($decoded['schema'] ?? '');
        if ($schema !== self::SCHEMA_RULE) {
            $diags[] = self::d('LD_RULE_SCHEMA_INVALID', 'error', 'rule', $relPath, $ownerKey, 'Schema is "' . $schema . '", expected "' . self::SCHEMA_RULE . '".');
            return $diags;
        }
        $diags[] = self::d('LD_RULE_SCHEMA_VALID', 'pass', 'rule', $relPath, $ownerKey, 'Schema is "' . self::SCHEMA_RULE . '".');

        $ruleKey = trim((string)($decoded['rule_key'] ?? ''));
        if ($ruleKey === '') {
            $diags[] = self::d('LD_RULE_KEY_MISSING', 'error', 'rule', $relPath, $ownerKey, 'rule_key is missing or empty.');
        } else {
            if (preg_match('/^[a-z][a-z0-9._-]+$/', $ruleKey) !== 1) {
                $diags[] = self::d('LD_RULE_KEY_FORMAT_INVALID', 'warning', 'rule', $ruleKey, $relPath, 'rule_key "' . $ruleKey . '" does not match expected format.');
            } else {
                $diags[] = self::d('LD_RULE_KEY_VALID', 'pass', 'rule', $ruleKey, $relPath, 'rule_key is valid: "' . $ruleKey . '".');
            }
        }

        $decodedOwnerKey = trim((string)($decoded['owner_key'] ?? ''));
        if ($decodedOwnerKey === '') {
            $diags[] = self::d('LD_RULE_OWNER_LEGACY_FALLBACK', 'warning', 'rule', $ruleKey ?: $relPath, $relPath, 'No owner_key in rule — legacy fallback mode. Add owner_key to align with architecture contract.');
        } else {
            $diags[] = self::d('LD_RULE_OWNER_PRESENT', 'pass', 'rule', $ruleKey ?: $relPath, $relPath, 'owner_key "' . $decodedOwnerKey . '" present.');
        }

        $refContextKey = trim((string)($decoded['context_key'] ?? ''));
        $refTemplateKey = trim((string)($decoded['template_key'] ?? ''));
        $refOwnerKey = $decodedOwnerKey !== '' ? $decodedOwnerKey : $ownerKey;

        if ($refContextKey === '') {
            $diags[] = self::d('LD_RULE_CONTEXT_KEY_MISSING', 'error', 'rule', $ruleKey ?: $relPath, $relPath, 'context_key is missing or empty.');
        } else {
            $ctxResolved = self::resolveContextRef($contextIndex, $refOwnerKey, $refContextKey);
            if ($ctxResolved === null) {
                $diags[] = self::d('LD_RULE_CONTEXT_UNRESOLVED', 'warning', 'rule', $ruleKey ?: $relPath, $relPath, 'Referenced context "' . $refContextKey . '" not found among scanned contexts.');
            } else {
                $diags[] = self::d('LD_RULE_CONTEXT_RESOLVED', 'pass', 'rule', $ruleKey ?: $relPath, $relPath, 'Referenced context "' . $refContextKey . '" resolved.');
            }
        }

        if ($refTemplateKey === '') {
            $diags[] = self::d('LD_RULE_TEMPLATE_KEY_MISSING', 'error', 'rule', $ruleKey ?: $relPath, $relPath, 'template_key is missing or empty.');
        } else {
            $tplResolved = self::resolveTemplateRef($templateIndex, $refOwnerKey, $refTemplateKey);
            if ($tplResolved === null) {
                $diags[] = self::d('LD_RULE_TEMPLATE_UNRESOLVED', 'warning', 'rule', $ruleKey ?: $relPath, $relPath, 'Referenced template "' . $refTemplateKey . '" not found among scanned templates.');
            } else {
                $diags[] = self::d('LD_RULE_TEMPLATE_RESOLVED', 'pass', 'rule', $ruleKey ?: $relPath, $relPath, 'Referenced template "' . $refTemplateKey . '" resolved.');
            }
        }

        $conditions = isset($decoded['conditions']) && is_array($decoded['conditions'])
            ? array_values(array_filter($decoded['conditions'], 'is_array')) : [];
        if (empty($conditions)) {
            $diags[] = self::d('LD_RULE_CONDITIONS_MISSING', 'error', 'rule', $ruleKey ?: $relPath, $relPath, 'conditions is missing or empty.');
        } else {
            foreach ($conditions as $ci => $cond) {
                $field = trim((string)($cond['field_key'] ?? $cond['field'] ?? ''));
                $operator = trim((string)($cond['operator'] ?? ''));

                if ($field === '') {
                    $diags[] = self::d('LD_RULE_CONDITION_FIELD_MISSING', 'warning', 'rule', $ruleKey ?: $relPath, $relPath, 'Condition at index ' . $ci . ' has no field_key.');
                }

                if ($operator === '') {
                    $diags[] = self::d('LD_RULE_OPERATOR_MISSING', 'warning', 'rule', $ruleKey ?: $relPath, $relPath, 'Condition at index ' . $ci . ' has no operator.');
                } elseif (!in_array($operator, self::ALLOWED_OPERATORS, true)) {
                    $diags[] = self::d('LD_RULE_OPERATOR_UNSUPPORTED', 'warning', 'rule', $ruleKey ?: $relPath, $relPath, 'Operator "' . $operator . '" at index ' . $ci . ' is not in allowed list: ' . implode(', ', self::ALLOWED_OPERATORS) . '.');
                } else {
                    $diags[] = self::d('LD_RULE_OPERATOR_VALID', 'pass', 'rule', $ruleKey ?: $relPath, $relPath, 'Operator "' . $operator . '" at index ' . $ci . ' is valid.');
                }
            }
        }

        $effects = isset($decoded['effects']) && is_array($decoded['effects'])
            ? array_values(array_filter($decoded['effects'], 'is_array')) : [];
        if (empty($effects)) {
            $diags[] = self::d('LD_RULE_EFFECTS_MISSING', 'error', 'rule', $ruleKey ?: $relPath, $relPath, 'effects is missing or empty.');
        } else {
            foreach ($effects as $ei => $eff) {
                $effType = trim((string)($eff['type'] ?? ''));
                $effTarget = trim((string)($eff['target'] ?? ''));

                if ($effType === '') {
                    $diags[] = self::d('LD_RULE_EFFECT_TYPE_MISSING', 'warning', 'rule', $ruleKey ?: $relPath, $relPath, 'Effect at index ' . $ei . ' has no type.');
                } elseif (!in_array($effType, self::ALLOWED_EFFECT_TYPES, true)) {
                    $diags[] = self::d('LD_RULE_EFFECT_UNSUPPORTED', 'warning', 'rule', $ruleKey ?: $relPath, $relPath, 'Effect type "' . $effType . '" at index ' . $ei . ' is not in allowed list: ' . implode(', ', self::ALLOWED_EFFECT_TYPES) . '.');
                } else {
                    $diags[] = self::d('LD_RULE_EFFECT_TYPE_VALID', 'pass', 'rule', $ruleKey ?: $relPath, $relPath, 'Effect type "' . $effType . '" at index ' . $ei . ' is valid.');
                }

                if ($effTarget === '') {
                    $diags[] = self::d('LD_RULE_TARGET_MISSING', 'info', 'rule', $ruleKey ?: $relPath, $relPath, 'Effect at index ' . $ei . ' has no target set.');
                }
            }
        }

        $diags = array_merge($diags, self::checkForbiddenBehavior($decoded, 'rule', $ruleKey ?: $relPath, $relPath));

        return $diags;
    }

    private static function checkForbiddenBehavior(array $decoded, string $type, string $label, string $path): array
    {
        $diags = [];
        $json = json_encode($decoded);
        if (!is_string($json)) {
            return $diags;
        }

        $foundAny = false;
        foreach (self::FORBIDDEN_RUNTIME_PATTERNS as $pattern) {
            if (preg_match($pattern, $json) === 1) {
                if (!$foundAny) {
                    $diags[] = self::d('LD_FORBIDDEN_RUNTIME_BEHAVIOR', 'error', $type, $label, $path, 'Resource declares or embeds forbidden runtime behavior (shell, DB, write, print, QR generation, image creation).');
                    $foundAny = true;
                    break;
                }
            }
        }

        if (!$foundAny) {
            $diags[] = self::d('LD_NO_FORBIDDEN_BEHAVIOR', 'pass', $type, $label, $path, 'No forbidden runtime behavior detected.');
        }

        return $diags;
    }

    private static function buildContextIndex(array $contextFiles): array
    {
        $index = [];
        foreach ($contextFiles as $file) {
            $path = (string)($file['path'] ?? '');
            $abs = APP_ROOT . '/' . ltrim($path, '/');
            $raw = self::safeRead($abs);
            if ($raw === null) { continue; }
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) { continue; }
            if ((string)($decoded['schema'] ?? '') !== self::SCHEMA_CONTEXT) { continue; }
            $ownerKey = (string)($decoded['owner_key'] ?? '');
            $contextKey = trim((string)($decoded['context_key'] ?? ''));
            if ($contextKey === '') { continue; }
            $index[] = [
                'owner_key' => $ownerKey,
                'context_key' => $contextKey,
                'path' => $path,
                'allowed_fields' => isset($decoded['allowed_fields']) && is_array($decoded['allowed_fields'])
                    ? array_values(array_filter($decoded['allowed_fields'], 'is_array')) : [],
            ];
        }
        return $index;
    }

    private static function buildTemplateIndex(array $templateFiles): array
    {
        $index = [];
        foreach ($templateFiles as $file) {
            $path = (string)($file['path'] ?? '');
            $abs = APP_ROOT . '/' . ltrim($path, '/');
            $raw = self::safeRead($abs);
            if ($raw === null) { continue; }
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) { continue; }
            if ((string)($decoded['schema'] ?? '') !== self::SCHEMA_TEMPLATE) { continue; }
            $templateKey = trim((string)($decoded['template_key'] ?? ''));
            if ($templateKey === '') { continue; }
            $contextRef = isset($decoded['context_ref']) && is_array($decoded['context_ref'])
                ? $decoded['context_ref'] : [];
            $index[] = [
                'owner_key' => (string)($contextRef['owner_key'] ?? ''),
                'template_key' => $templateKey,
                'path' => $path,
            ];
        }
        return $index;
    }

    private static function resolveContextRef(array $contextIndex, string $ownerKey, string $contextKey): ?array
    {
        foreach ($contextIndex as $entry) {
            if ((string)($entry['context_key'] ?? '') === $contextKey) {
                $eo = (string)($entry['owner_key'] ?? '');
                if ($eo === '' || $eo === $ownerKey || $ownerKey === '') {
                    return $entry;
                }
            }
        }
        return null;
    }

    private static function resolveTemplateRef(array $templateIndex, string $ownerKey, string $templateKey): ?array
    {
        foreach ($templateIndex as $entry) {
            if ((string)($entry['template_key'] ?? '') === $templateKey) {
                $eo = (string)($entry['owner_key'] ?? '');
                if ($eo === '' || $eo === $ownerKey || $ownerKey === '') {
                    return $entry;
                }
            }
        }
        return null;
    }

    private static function safeRead(string $absPath): ?string
    {
        $real = realpath($absPath);
        if (!is_string($real) || !is_file($real)) {
            return null;
        }
        $appsRoot = realpath(APP_ROOT . '/apps');
        $pluginsRoot = realpath(APP_ROOT . '/plugins');
        if (!is_string($appsRoot) && !is_string($pluginsRoot)) {
            return null;
        }
        $inside = (is_string($appsRoot) && ($real === $appsRoot || str_starts_with($real, $appsRoot . '/')))
            || (is_string($pluginsRoot) && ($real === $pluginsRoot || str_starts_with($real, $pluginsRoot . '/')));
        if (!$inside) {
            return null;
        }
        $content = file_get_contents($real);
        return is_string($content) ? $content : null;
    }

    private static function d(string $code, string $severity, string $resourceType, string $keyOrLabel, string $path, string $message): array
    {
        return [
            'code' => $code,
            'severity' => $severity,
            'resource_type' => $resourceType,
            'resource_key' => $keyOrLabel,
            'path' => $path,
            'message' => $message,
        ];
    }

    private static function tallySeverity(array $diags, array &$counts): void
    {
        foreach ($diags as $d) {
            $s = (string)($d['severity'] ?? 'pass');
            if (isset($counts[$s])) { $counts[$s]++; }
        }
    }

    private static function countLegacyFallback(array $diags): int
    {
        $count = 0;
        foreach ($diags as $d) {
            $code = (string)($d['code'] ?? '');
            if (
                $code === 'LD_CTX_OWNER_LEGACY_FALLBACK'
                || $code === 'LD_TPL_OWNER_LEGACY_FALLBACK'
                || $code === 'LD_RULE_OWNER_LEGACY_FALLBACK'
            ) {
                $count++;
            }
        }
        return $count;
    }
}

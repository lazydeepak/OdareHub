<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\LabelDesigner\Services;

/**
 * Read-only template preview flow for owner-owned label templates.
 *
 * This service must not write files, mutate DB state, or trigger runtime print logic.
 */
final class LabelDesignerTemplatePreviewService
{
    private const LABEL_SIZES = [
        '100x50_mm',
        '80x40_mm',
        '60x30_mm',
        '50x25_mm',
        'A6_portrait',
    ];

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public static function buildPreview(array $input): array
    {
        $selectedContextId = trim((string)($input['template_context'] ?? ''));
        $selectedSize = trim((string)($input['template_size'] ?? '100x50_mm'));
        $requestedFields = self::normalizeFields($input['template_fields'] ?? []);

        $contextOptions = self::discoverContextOptions();
        $errors = [];
        $validation = [
            'context_selected' => false,
            'size_valid' => false,
            'fields_valid' => false,
            'json_valid' => false,
            'read_only_preview' => true,
        ];

        $selectedContext = self::findContext($contextOptions, $selectedContextId);
        if ($selectedContext === null && !empty($contextOptions)) {
            $selectedContext = $contextOptions[0];
            $selectedContextId = (string)($selectedContext['context_id'] ?? '');
        }

        if ($selectedContext === null) {
            $errors[] = 'No owner-owned label contexts are available for template preview.';
            return [
                'ok' => false,
                'errors' => $errors,
                'validation' => $validation,
                'contexts' => $contextOptions,
                'label_sizes' => self::LABEL_SIZES,
                'selected' => [
                    'template_context' => $selectedContextId,
                    'template_size' => $selectedSize,
                    'template_fields' => $requestedFields,
                ],
                'template_json' => '{}',
            ];
        }

        $validation['context_selected'] = true;

        if (!in_array($selectedSize, self::LABEL_SIZES, true)) {
            $selectedSize = self::LABEL_SIZES[0];
        }
        $validation['size_valid'] = true;

        $allowedFieldKeys = [];
        $allowedFieldMap = [];
        $contextFields = isset($selectedContext['allowed_fields']) && is_array($selectedContext['allowed_fields'])
            ? array_values(array_filter($selectedContext['allowed_fields'], 'is_array'))
            : [];

        foreach ($contextFields as $field) {
            $fieldKey = trim((string)($field['field_key'] ?? ''));
            if ($fieldKey === '') {
                continue;
            }
            $allowedFieldKeys[] = $fieldKey;
            $allowedFieldMap[$fieldKey] = $field;
        }

        if ($requestedFields === []) {
            $requestedFields = array_slice($allowedFieldKeys, 0, 8);
        }

        $selectedFields = [];
        foreach ($requestedFields as $fieldKey) {
            if (!isset($allowedFieldMap[$fieldKey])) {
                continue;
            }
            $selectedFields[] = [
                'field_key' => $fieldKey,
                'label' => (string)($allowedFieldMap[$fieldKey]['label'] ?? self::labelFromField($fieldKey)),
                'source_column' => (string)($allowedFieldMap[$fieldKey]['source_column'] ?? $fieldKey),
            ];
        }

        if ($selectedFields === []) {
            $errors[] = 'Select at least one field from the selected context.';
            return [
                'ok' => false,
                'errors' => $errors,
                'validation' => $validation,
                'contexts' => $contextOptions,
                'label_sizes' => self::LABEL_SIZES,
                'selected' => [
                    'template_context' => $selectedContextId,
                    'template_size' => $selectedSize,
                    'template_fields' => $requestedFields,
                ],
                'selected_context' => $selectedContext,
                'template_json' => '{}',
            ];
        }

        $validation['fields_valid'] = true;

        $contextKey = trim((string)($selectedContext['context_key'] ?? 'context'));
        $templateKey = self::safeTemplateKey($contextKey . '.' . $selectedSize);

        $template = [
            'schema' => 'odarehub.label.template.v1',
            'preview_only' => true,
            'write_status' => 'disabled_in_this_slice',
            'template_key' => $templateKey,
            'context_ref' => [
                'context_key' => $contextKey,
                'context_file' => (string)($selectedContext['context_path'] ?? ''),
                'owner_key' => (string)($selectedContext['owner_key'] ?? ''),
                'owner_type' => (string)($selectedContext['owner_type'] ?? ''),
            ],
            'layout' => [
                'label_size' => $selectedSize,
                'orientation' => str_contains($selectedSize, 'portrait') ? 'portrait' : 'landscape',
                'blocks' => [
                    ['block_key' => 'header', 'role' => 'title_and_identity'],
                    ['block_key' => 'details', 'role' => 'field_rows'],
                    ['block_key' => 'footer', 'role' => 'owner_signoff_qr_placeholder'],
                ],
            ],
            'fields' => $selectedFields,
            'notes' => [
                'illustrative_preview_only',
                'owner_template_write_disabled',
                'enable_snapshot_validation_confirmation_before_template_write',
            ],
        ];

        $json = json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') {
            $errors[] = 'Failed to encode template preview JSON.';
            return [
                'ok' => false,
                'errors' => $errors,
                'validation' => $validation,
                'contexts' => $contextOptions,
                'label_sizes' => self::LABEL_SIZES,
                'selected' => [
                    'template_context' => $selectedContextId,
                    'template_size' => $selectedSize,
                    'template_fields' => $requestedFields,
                ],
                'selected_context' => $selectedContext,
                'template_json' => '{}',
            ];
        }

        $validation['json_valid'] = true;

        return [
            'ok' => true,
            'errors' => [],
            'validation' => $validation,
            'contexts' => $contextOptions,
            'label_sizes' => self::LABEL_SIZES,
            'selected' => [
                'template_context' => $selectedContextId,
                'template_size' => $selectedSize,
                'template_fields' => array_map(
                    static fn (array $field): string => (string)$field['field_key'],
                    $selectedFields
                ),
            ],
            'selected_context' => $selectedContext,
            'template_json' => $json,
            'template' => $template,
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function discoverContextOptions(): array
    {
        $discovery = LabelDesignerDiscoveryService::discover();
        $owners = isset($discovery['owners']) && is_array($discovery['owners'])
            ? array_values(array_filter($discovery['owners'], 'is_array'))
            : [];

        $contexts = [];
        foreach ($owners as $owner) {
            $ownerKey = trim((string)($owner['owner_key'] ?? ''));
            $ownerType = trim((string)($owner['owner_type'] ?? ''));
            $resources = isset($owner['resources']) && is_array($owner['resources']) ? $owner['resources'] : [];
            $contextGroup = isset($resources['contexts']) && is_array($resources['contexts']) ? $resources['contexts'] : [];
            $contextFiles = isset($contextGroup['files']) && is_array($contextGroup['files'])
                ? array_values(array_filter($contextGroup['files'], 'is_array'))
                : [];

            foreach ($contextFiles as $contextFile) {
                $relativePath = trim((string)($contextFile['path'] ?? ''));
                if ($relativePath === '') {
                    continue;
                }

                $absolutePath = APP_ROOT . '/' . ltrim($relativePath, '/');
                if (!is_file($absolutePath)) {
                    continue;
                }

                $raw = @file_get_contents($absolutePath);
                if (!is_string($raw) || $raw === '') {
                    continue;
                }

                $decoded = json_decode($raw, true);
                if (!is_array($decoded)) {
                    continue;
                }

                if ((string)($decoded['schema'] ?? '') !== 'odarehub.label.context.v1') {
                    continue;
                }

                $contextKey = trim((string)($decoded['context_key'] ?? ''));
                if ($contextKey === '') {
                    continue;
                }

                $allowedFields = isset($decoded['allowed_fields']) && is_array($decoded['allowed_fields'])
                    ? array_values(array_filter($decoded['allowed_fields'], 'is_array'))
                    : [];

                $contexts[] = [
                    'context_id' => sha1($relativePath),
                    'context_key' => $contextKey,
                    'context_file' => trim((string)($contextFile['name'] ?? basename($relativePath))),
                    'context_path' => $relativePath,
                    'owner_key' => $ownerKey,
                    'owner_type' => $ownerType,
                    'purpose' => trim((string)($decoded['purpose'] ?? '')),
                    'source_name' => trim((string)($decoded['data_source_boundary']['source_name'] ?? '')),
                    'allowed_fields' => $allowedFields,
                ];
            }
        }

        usort(
            $contexts,
            static fn (array $a, array $b): int => strcmp((string)($a['context_key'] ?? ''), (string)($b['context_key'] ?? ''))
        );

        return $contexts;
    }

    /**
     * @param array<int,array<string,mixed>> $contexts
     * @return array<string,mixed>|null
     */
    private static function findContext(array $contexts, string $contextId): ?array
    {
        foreach ($contexts as $context) {
            if (hash_equals((string)($context['context_id'] ?? ''), $contextId)) {
                return $context;
            }
        }

        return null;
    }

    /**
     * @param array<int|string,mixed> $fields
     * @return array<int,string>
     */
    private static function normalizeFields($fields): array
    {
        if (!is_array($fields)) {
            return [];
        }

        $normalized = [];
        foreach ($fields as $field) {
            $key = trim((string)$field);
            if ($key === '' || preg_match('/^[A-Za-z0-9_]+$/', $key) !== 1) {
                continue;
            }
            $normalized[$key] = $key;
        }

        return array_values($normalized);
    }

    private static function safeTemplateKey(string $raw): string
    {
        $candidate = strtolower(trim($raw));
        $candidate = preg_replace('/[^a-z0-9._-]+/', '.', $candidate) ?? '';
        $candidate = preg_replace('/\.+/', '.', $candidate) ?? '';
        $candidate = trim($candidate, '.');

        if ($candidate === '') {
            return 'label.template.preview';
        }

        return $candidate;
    }

    private static function labelFromField(string $field): string
    {
        return ucwords(str_replace('_', ' ', $field));
    }
}

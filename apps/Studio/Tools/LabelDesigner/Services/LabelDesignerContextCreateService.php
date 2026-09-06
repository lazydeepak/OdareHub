<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\LabelDesigner\Services;

use RuntimeException;

/**
 * Guarded create-only flow for owner-owned label context JSON resources.
 */
final class LabelDesignerContextCreateService
{
    private const SNAPSHOT_ROOT = APP_ROOT . '/storage/studio-snapshots/label-designer';

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public static function buildPreview(array $input): array
    {
        $ownerKey = trim((string)($input['owner'] ?? ''));
        $purpose = trim((string)($input['purpose'] ?? 'Product Label'));
        $sourceName = trim((string)($input['source'] ?? ''));
        $rawContextKey = trim((string)($input['context_key'] ?? ''));
        $requestedFields = self::normalizeFields($input['fields'] ?? []);

        $validation = [
            'owner_exists' => false,
            'owner_root_valid' => false,
            'resource_path_valid' => false,
            'source_valid' => false,
            'fields_valid' => false,
            'context_key_valid' => false,
            'key_unique' => false,
            'json_valid' => false,
            'path_traversal_blocked' => false,
        ];

        $errors = [];
        $discovery = LabelDesignerDataSourceDiscoveryService::discover($ownerKey !== '' ? $ownerKey : null);
        $owners = isset($discovery['owners']) && is_array($discovery['owners']) ? $discovery['owners'] : [];
        $candidateSources = isset($discovery['candidate_sources']) && is_array($discovery['candidate_sources']) ? $discovery['candidate_sources'] : [];

        // When no owner is provided, use the resolved owner from discovery fallback
        if ($ownerKey === '' && isset($discovery['selected_owner_key']) && $discovery['selected_owner_key'] !== '') {
            $ownerKey = (string)$discovery['selected_owner_key'];
        }

        $selectedOwner = self::findOwner($owners, $ownerKey);
        if ($selectedOwner === null) {
            $errors[] = 'Owner does not exist.';
            return self::previewError($errors, $validation, $discovery, $purpose, $rawContextKey, $requestedFields, $sourceName);
        }

        $validation['owner_exists'] = true;
        $ownerRoot = self::resolveOwnerRoot((string)$selectedOwner['root_path']);
        if ($ownerRoot === '') {
            $errors[] = 'Selected owner root path is invalid.';
            return self::previewError($errors, $validation, $discovery, $purpose, $rawContextKey, $requestedFields, $sourceName);
        }

        $validation['owner_root_valid'] = true;
        $contextsDir = $ownerRoot . '/Resources/labels/contexts';
        if (!self::isPathInside($contextsDir, $ownerRoot)) {
            $errors[] = 'Target context path is outside the selected owner root.';
            return self::previewError($errors, $validation, $discovery, $purpose, $rawContextKey, $requestedFields, $sourceName);
        }

        $validation['resource_path_valid'] = true;

        if ($sourceName === '' && $candidateSources !== []) {
            $sourceName = (string)($candidateSources[0]['source_name'] ?? '');
        }

        $selectedSource = self::findSource($candidateSources, $sourceName);
        if ($selectedSource === null) {
            $errors[] = 'Selected data source is not a discovered candidate source for this owner.';
            return self::previewError($errors, $validation, $discovery, $purpose, $rawContextKey, $requestedFields, $sourceName);
        }

        $validation['source_valid'] = true;
        $columns = isset($selectedSource['columns']) && is_array($selectedSource['columns'])
            ? array_values(array_filter($selectedSource['columns'], 'is_array'))
            : [];
        $columnIndex = [];
        foreach ($columns as $column) {
            $name = trim((string)($column['column_name'] ?? ''));
            if ($name !== '') {
                $columnIndex[$name] = $column;
            }
        }

        if ($requestedFields === []) {
            $requestedFields = array_slice(array_keys($columnIndex), 0, 8);
        }

        $resolvedFields = [];
        foreach ($requestedFields as $field) {
            if (!isset($columnIndex[$field])) {
                $errors[] = 'Selected field is not available in the selected source: ' . $field;
                continue;
            }
            $col = $columnIndex[$field];
            $resolvedFields[] = [
                'field_key' => $field,
                'label' => self::labelFromField($field),
                'source_column' => $field,
                'data_type' => (string)($col['data_type'] ?? ''),
                'is_nullable' => (string)($col['is_nullable'] ?? ''),
            ];
        }

        if ($resolvedFields === [] || $errors !== []) {
            $errors[] = 'At least one valid field is required.';
            return self::previewError($errors, $validation, $discovery, $purpose, $rawContextKey, $requestedFields, $sourceName);
        }

        $validation['fields_valid'] = true;

        $contextKey = self::safeContextKey($rawContextKey, (string)$selectedOwner['owner_key'], $purpose);
        if ($contextKey === '') {
            $errors[] = 'Context key is invalid after sanitization.';
            return self::previewError($errors, $validation, $discovery, $purpose, $rawContextKey, $requestedFields, $sourceName);
        }

        $validation['context_key_valid'] = true;
        $validation['path_traversal_blocked'] = true;

        $targetFileName = $contextKey . '.label-context.json';
        $targetPath = $contextsDir . '/' . $targetFileName;
        if (!self::isPathInside($targetPath, $ownerRoot)) {
            $errors[] = 'Target file path failed owner-root validation.';
            return self::previewError($errors, $validation, $discovery, $purpose, $rawContextKey, $requestedFields, $sourceName);
        }

        if (is_file($targetPath)) {
            $errors[] = 'Context file already exists. Create flow does not overwrite existing resources.';
            return self::previewError($errors, $validation, $discovery, $purpose, $rawContextKey, $requestedFields, $sourceName);
        }

        $validation['key_unique'] = true;

        $context = [
            'schema' => 'susankhya.label.context.v1',
            'context_key' => $contextKey,
            'owner' => [
                'owner_key' => (string)$selectedOwner['owner_key'],
                'owner_type' => (string)$selectedOwner['owner_type'],
                'root_path' => (string)$selectedOwner['root_path'],
                'resource_path' => '{OwnerRoot}/Resources/labels/contexts/',
            ],
            'purpose' => $purpose !== '' ? $purpose : 'Label Purpose',
            'allowed_fields' => $resolvedFields,
            'data_source_boundary' => [
                'source_name' => (string)$selectedSource['source_name'],
                'source_type' => (string)($selectedSource['source_type'] ?? ''),
                'status' => 'owner_scoped_candidate_selected',
            ],
            'print_locations' => [
                'status' => 'owner_defined_later',
            ],
            'template_ref' => [
                'status' => 'owner_template_selected_later',
            ],
            'rules_refs' => [],
            'permissions' => [],
        ];

        $json = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') {
            $errors[] = 'Failed to encode context JSON.';
            return self::previewError($errors, $validation, $discovery, $purpose, $rawContextKey, $requestedFields, $sourceName);
        }

        $validation['json_valid'] = true;

        return [
            'ok' => true,
            'errors' => [],
            'validation' => $validation,
            'selected' => [
                'owner' => (string)$selectedOwner['owner_key'],
                'purpose' => $purpose,
                'source' => (string)$selectedSource['source_name'],
                'fields' => array_map(static fn (array $f): string => (string)$f['field_key'], $resolvedFields),
                'context_key' => $contextKey,
            ],
            'owner' => $selectedOwner,
            'target_path' => $targetPath,
            'target_path_rel' => self::toRelativePath($targetPath),
            'contexts_dir' => $contextsDir,
            'source' => $selectedSource,
            'context' => $context,
            'context_json' => $json,
            'discovery' => $discovery,
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array<string,mixed>
     */
    public static function createContext(array $input, array $actor = []): array
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

        $targetPath = (string)$preview['target_path'];
        $contextsDir = (string)$preview['contexts_dir'];
        $json = (string)$preview['context_json'];

        $snapshotResult = self::writeSnapshot([
            'owner' => $preview['owner'],
            'resource_type' => 'context',
            'target_path' => self::toRelativePath($targetPath),
            'previous_content' => null,
            'proposed_content' => json_decode($json, true),
            'action' => 'create',
            'user' => self::actorSummary($actor),
            'timestamp' => gmdate('c'),
            'validation_result' => $preview['validation'],
            'rollback_hint' => 'Delete ' . self::toRelativePath($targetPath) . ' for create rollback.',
        ]);

        if (empty($snapshotResult['ok'])) {
            return [
                'ok' => false,
                'errors' => ['Failed to create snapshot metadata before write.'],
                'validation' => $preview['validation'],
            ];
        }

        if (!is_dir($contextsDir)) {
            if (!@mkdir($contextsDir, 0755, true) && !is_dir($contextsDir)) {
                return [
                    'ok' => false,
                    'errors' => ['Failed to create owner contexts directory.'],
                    'validation' => $preview['validation'],
                    'snapshot_path' => (string)$snapshotResult['path_rel'],
                ];
            }
        }

        $tmpPath = $targetPath . '.tmp.' . getmypid();
        $written = @file_put_contents($tmpPath, $json . "\n");
        if ($written === false) {
            @unlink($tmpPath);
            return [
                'ok' => false,
                'errors' => ['Failed to write context file temporary artifact.'],
                'validation' => $preview['validation'],
                'snapshot_path' => (string)$snapshotResult['path_rel'],
            ];
        }

        if (!@rename($tmpPath, $targetPath)) {
            @unlink($tmpPath);
            return [
                'ok' => false,
                'errors' => ['Failed to finalize context file write.'],
                'validation' => $preview['validation'],
                'snapshot_path' => (string)$snapshotResult['path_rel'],
            ];
        }

        $diagnostics = self::runPostWriteDiagnostics($targetPath, $preview['context']);

        return [
            'ok' => true,
            'errors' => [],
            'validation' => $preview['validation'],
            'diagnostics' => $diagnostics,
            'context_key' => (string)$preview['selected']['context_key'],
            'created_path' => self::toRelativePath($targetPath),
            'snapshot_path' => (string)$snapshotResult['path_rel'],
            'owner_key' => (string)$preview['selected']['owner'],
        ];
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
            $name = trim((string)$field);
            if ($name === '' || preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
                continue;
            }
            $normalized[$name] = $name;
        }

        return array_values($normalized);
    }

    /**
     * @param array<int,array<string,mixed>> $owners
     * @return array<string,mixed>|null
     */
    private static function findOwner(array $owners, string $ownerKey): ?array
    {
        foreach ($owners as $owner) {
            if (hash_equals((string)($owner['owner_key'] ?? ''), $ownerKey)) {
                return $owner;
            }
        }

        return null;
    }

    /**
     * @param array<int,array<string,mixed>> $sources
     * @return array<string,mixed>|null
     */
    private static function findSource(array $sources, string $sourceName): ?array
    {
        foreach ($sources as $source) {
            if (hash_equals((string)($source['source_name'] ?? ''), $sourceName)) {
                return $source;
            }
        }

        return null;
    }

    private static function resolveOwnerRoot(string $ownerRelativeRoot): string
    {
        $ownerRelativeRoot = trim($ownerRelativeRoot);
        if ($ownerRelativeRoot === '' || str_contains($ownerRelativeRoot, '..')) {
            return '';
        }

        $absolute = APP_ROOT . '/' . ltrim($ownerRelativeRoot, '/');
        $real = realpath($absolute);
        if (!is_string($real) || !is_dir($real)) {
            return '';
        }

        $appsRoot = realpath(APP_ROOT . '/apps');
        $pluginsRoot = realpath(APP_ROOT . '/plugins');

        $insideApps = is_string($appsRoot) && ($real === $appsRoot || str_starts_with($real, $appsRoot . '/'));
        $insidePlugins = is_string($pluginsRoot) && ($real === $pluginsRoot || str_starts_with($real, $pluginsRoot . '/'));

        if (!$insideApps && !$insidePlugins) {
            return '';
        }

        return $real;
    }

    private static function safeContextKey(string $raw, string $ownerKey, string $purpose): string
    {
        $candidate = trim($raw);
        if ($candidate === '') {
            $candidate = str_replace('/', '.', strtolower($ownerKey)) . '.' . strtolower($purpose);
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

    private static function labelFromField(string $field): string
    {
        return ucwords(str_replace('_', ' ', $field));
    }

    private static function isPathInside(string $path, string $root): bool
    {
        $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/');
        $normalizedPath = str_replace('\\', '/', $path);

        return $normalizedPath === $normalizedRoot || str_starts_with($normalizedPath, $normalizedRoot . '/');
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

        $contextKey = (string)($payload['proposed_content']['context_key'] ?? 'context');
        $safeKey = preg_replace('/[^a-z0-9._-]+/i', '-', strtolower($contextKey)) ?: 'context';
        $snapshotId = gmdate('Ymd_His') . '_' . substr(sha1($safeKey . microtime(true)), 0, 10);
        $snapshotPath = self::SNAPSHOT_ROOT . '/context-create-' . $safeKey . '-' . $snapshotId . '.json';

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
     * @param array<string,mixed> $actor
     * @return array<string,mixed>
     */
    private static function actorSummary(array $actor): array
    {
        return [
            'id' => isset($actor['id']) ? (string)$actor['id'] : '',
            'email' => isset($actor['email']) ? (string)$actor['email'] : '',
            'username' => isset($actor['username']) ? (string)$actor['username'] : '',
            'name' => isset($actor['name']) ? (string)$actor['name'] : '',
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private static function runPostWriteDiagnostics(string $targetPath, array $context): array
    {
        $result = [
            'json_valid' => false,
            'schema_valid' => false,
            'owner_path_valid' => false,
            'runtime_side_effects' => 'not_executed_in_label_designer',
            'status' => 'FAIL',
        ];

        if (!is_file($targetPath)) {
            return $result;
        }

        $json = @file_get_contents($targetPath);
        if (!is_string($json) || $json === '') {
            return $result;
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return $result;
        }

        $result['json_valid'] = true;
        $result['schema_valid'] = ((string)($decoded['schema'] ?? '') === 'susankhya.label.context.v1');

        $ownerRoot = (string)($decoded['owner']['root_path'] ?? '');
        $resolvedOwnerRoot = self::resolveOwnerRoot($ownerRoot);
        $result['owner_path_valid'] = $resolvedOwnerRoot !== '';

        if ($result['json_valid'] && $result['schema_valid'] && $result['owner_path_valid']) {
            $result['status'] = 'PASS';
        }

        return $result;
    }

    private static function toRelativePath(string $path): string
    {
        $root = rtrim((string)APP_ROOT, '/');
        $normalized = str_replace('\\', '/', $path);
        if (str_starts_with($normalized, $root . '/')) {
            return substr($normalized, strlen($root) + 1);
        }

        return $path;
    }

    /**
     * @param array<string> $errors
     * @param array<string,bool> $validation
     * @param array<string,mixed> $discovery
     * @param array<int,string> $fields
     * @return array<string,mixed>
     */
    private static function previewError(array $errors, array $validation, array $discovery, string $purpose, string $rawContextKey, array $fields, string $sourceName): array
    {
        return [
            'ok' => false,
            'errors' => array_values(array_unique($errors)),
            'validation' => $validation,
            'selected' => [
                'owner' => (string)($discovery['selected_owner_key'] ?? ''),
                'purpose' => $purpose,
                'source' => $sourceName,
                'fields' => $fields,
                'context_key' => $rawContextKey,
            ],
            'discovery' => $discovery,
            'context' => null,
            'context_json' => '{}',
            'target_path' => '',
            'target_path_rel' => '',
        ];
    }
}

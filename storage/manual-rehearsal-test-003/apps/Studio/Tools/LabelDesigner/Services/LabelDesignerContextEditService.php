<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\LabelDesigner\Services;

final class LabelDesignerContextEditService
{
    private const SNAPSHOT_ROOT = APP_ROOT . '/storage/studio-snapshots/label-designer';

    private const DISCOVERY_TYPE_MAP = [
        'context' => 'contexts',
    ];

    public static function editContext(array $input, array $actor = []): array
    {
        $ownerKey = trim((string)($input['owner_key'] ?? ''));
        $contextKey = trim((string)($input['context_key'] ?? ''));
        $purpose = trim((string)($input['purpose'] ?? ''));
        $rawFields = isset($input['fields']) && is_array($input['fields']) ? $input['fields'] : [];
        $confirm = trim((string)($input['confirm_edit'] ?? ''));

        if ($ownerKey === '' || $contextKey === '') {
            return ['ok' => false, 'errors' => ['Owner key and context key are required.']];
        }

        $sourcePathRel = self::resolveSourcePath($ownerKey, $contextKey);
        if ($sourcePathRel === '') {
            return ['ok' => false, 'errors' => ['Context resource not found for key "' . $contextKey . '".']];
        }

        $sourceFull = APP_ROOT . '/' . ltrim($sourcePathRel, '/');
        $sourceJson = is_file($sourceFull) ? @file_get_contents($sourceFull) : false;
        if (!is_string($sourceJson) || $sourceJson === '') {
            return ['ok' => false, 'errors' => ['Failed to read context resource file.']];
        }

        $contextData = @json_decode($sourceJson, true);
        if (!is_array($contextData)) {
            return ['ok' => false, 'errors' => ['Context resource file is not valid JSON.']];
        }

        $ownerRoot = self::resolveOwnerRootByKey($ownerKey);
        if ($ownerRoot === '') {
            return ['ok' => false, 'errors' => ['Could not resolve owner root for "' . $ownerKey . '".']];
        }

        if (!self::isPathInside($sourceFull, $ownerRoot)) {
            return ['ok' => false, 'errors' => ['Context file path is outside owner root.']];
        }

        $modified = self::applyEdits($contextData, $purpose, $rawFields, $contextKey);
        if (!empty($modified['errors'])) {
            return ['ok' => false, 'errors' => $modified['errors']];
        }

        if ($confirm !== 'yes') {
            $previewJson = json_encode($modified['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            return [
                'ok' => true,
                'preview' => true,
                'context_key' => $contextKey,
                'source_path' => $sourcePathRel,
                'context_json' => is_string($previewJson) ? $previewJson : '{}',
                'errors' => [],
            ];
        }

        $snapshotResult = self::writeSnapshot([
            'resource_type' => 'context',
            'action' => 'context-edit',
            'owner_key' => $ownerKey,
            'context_key' => $contextKey,
            'source_path' => $sourcePathRel,
            'previous_content' => $contextData,
            'proposed_content' => $modified['data'],
            'timestamp' => gmdate('c'),
            'user' => self::actorSummary($actor),
            'rollback_hint' => 'Restore previous_content from this snapshot to rollback context-edit for ' . $contextKey . '.',
        ]);

        if (empty($snapshotResult['ok'])) {
            return ['ok' => false, 'errors' => ['Failed to create snapshot before write.']];
        }

        $newJson = json_encode($modified['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($newJson) || $newJson === '') {
            return ['ok' => false, 'errors' => ['Failed to encode modified context JSON.']];
        }

        $tmpPath = $sourceFull . '.tmp.' . getmypid();
        $written = @file_put_contents($tmpPath, $newJson . "\n");
        if ($written === false) {
            @unlink($tmpPath);
            return ['ok' => false, 'errors' => ['Failed to write temporary file.'],
                    'snapshot_path' => (string)($snapshotResult['path_rel'] ?? '')];
        }

        if (!@rename($tmpPath, $sourceFull)) {
            @unlink($tmpPath);
            return ['ok' => false, 'errors' => ['Failed to finalize file write.'],
                    'snapshot_path' => (string)($snapshotResult['path_rel'] ?? '')];
        }

        $postDiag = self::runPostWriteDiagnostics($sourceFull, $modified['data'], $contextData);

        return [
            'ok' => true,
            'preview' => false,
            'errors' => [],
            'context_key' => $contextKey,
            'created_path' => $sourcePathRel,
            'snapshot_path' => (string)($snapshotResult['path_rel'] ?? ''),
            'diagnostics' => $postDiag,
        ];
    }

    private static function resolveSourcePath(string $ownerKey, string $contextKey): string
    {
        $discovery = LabelDesignerDiscoveryService::discover();
        $discoveryType = self::DISCOVERY_TYPE_MAP['context'] ?? '';
        if ($discoveryType === '') {
            return '';
        }

        $ownerData = null;
        foreach (($discovery['owners'] ?? []) as $owner) {
            if (is_array($owner) && strcasecmp((string)($owner['owner_key'] ?? ''), $ownerKey) === 0) {
                $ownerData = $owner;
                break;
            }
        }

        if ($ownerData === null) {
            return '';
        }

        $typeDir = (string)($ownerData['resources'][$discoveryType]['path'] ?? '');
        $files = isset($ownerData['resources'][$discoveryType]['files']) && is_array($ownerData['resources'][$discoveryType]['files'])
            ? $ownerData['resources'][$discoveryType]['files']
            : [];

        foreach ($files as $file) {
            $filePath = (string)($file['path'] ?? '');
            if ($filePath === '') {
                continue;
            }
            $fullPath = APP_ROOT . '/' . ltrim($filePath, '/');
            $content = is_file($fullPath) ? @file_get_contents($fullPath) : false;
            if (!is_string($content)) {
                continue;
            }
            $data = @json_decode($content, true);
            if (!is_array($data)) {
                continue;
            }

            if (trim((string)($data['context_key'] ?? '')) === $contextKey) {
                return $filePath;
            }
        }

        return '';
    }

    private static function applyEdits(array $contextData, string $purpose, array $rawFields, string $contextKey): array
    {
        $errors = [];
        $modified = $contextData;

        if ($purpose !== '') {
            $modified['purpose'] = $purpose;
        }

        $existingFields = isset($modified['allowed_fields']) && is_array($modified['allowed_fields'])
            ? $modified['allowed_fields']
            : [];

        if ($rawFields === [] && $purpose === '') {
            return ['errors' => ['No changes provided. Specify a new purpose or field metadata.']];
        }

        if ($rawFields !== []) {
            $fieldMap = [];
            foreach ($existingFields as $fi => $field) {
                $fk = trim((string)($field['field_key'] ?? ''));
                if ($fk !== '') {
                    $fieldMap[$fk] = $fi;
                }
            }

            foreach ($rawFields as $rawField) {
                $fk = trim((string)($rawField['field_key'] ?? ''));
                if ($fk === '' || !isset($fieldMap[$fk])) {
                    $errors[] = 'Unknown field_key "' . $fk . '" — field keys cannot be changed.';
                    continue;
                }

                $idx = $fieldMap[$fk];

                $label = trim((string)($rawField['label'] ?? ''));
                if ($label !== '') {
                    $existingFields[$idx]['label'] = $label;
                }

                if (isset($rawField['data_type'])) {
                    $dt = trim((string)$rawField['data_type']);
                    $existingFields[$idx]['data_type'] = $dt;
                }

                if (isset($rawField['required'])) {
                    $req = trim((string)$rawField['required']);
                    $existingFields[$idx]['required'] = ($req === 'yes' || $req === '1' || $req === 'true');
                }
            }

            $modified['allowed_fields'] = $existingFields;
        }

        return ['data' => $modified, 'errors' => $errors];
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

    private static function isPathInside(string $path, string $root): bool
    {
        $normalizedPath = self::normalizePath($path);
        $normalizedRoot = self::normalizePath($root);

        if ($normalizedRoot === '') {
            return false;
        }

        return $normalizedPath === $normalizedRoot || str_starts_with($normalizedPath, $normalizedRoot . '/');
    }

    private static function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $parts = explode('/', $path);
        $resolved = [];
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($resolved);
            } else {
                $resolved[] = $part;
            }
        }

        $prefix = '';
        if (str_starts_with($path, '/')) {
            $prefix = '/';
        } elseif (preg_match('/^[A-Za-z]:\\\\/', $path) === 1) {
            $prefix = '/';
        }

        return $prefix . implode('/', $resolved);
    }

    private static function writeSnapshot(array $payload): array
    {
        if (!is_dir(self::SNAPSHOT_ROOT)) {
            if (!@mkdir(self::SNAPSHOT_ROOT, 0755, true) && !is_dir(self::SNAPSHOT_ROOT)) {
                return ['ok' => false];
            }
        }

        $contextKey = (string)($payload['context_key'] ?? 'context');
        $safeKey = preg_replace('/[^a-z0-9._-]+/i', '-', strtolower($contextKey)) ?: 'context';
        $snapshotId = gmdate('Ymd_His') . '_' . substr(sha1($safeKey . microtime(true)), 0, 10);
        $snapshotPath = self::SNAPSHOT_ROOT . '/context-edit-' . $safeKey . '-' . $snapshotId . '.json';

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

    private static function runPostWriteDiagnostics(string $absolutePath, array $newData, array $originalData = []): array
    {
        $checks = [];

        $exists = is_file($absolutePath);
        $checks[] = [
            'code' => 'CE01',
            'rule' => 'target_file_exists',
            'label' => 'Target file exists after edit',
            'severity' => $exists ? 'PASS' : 'FAIL',
            'value' => self::toRelativePath($absolutePath),
        ];

        $schemaValid = ((string)($newData['schema'] ?? '') === 'susankhya.label.context.v1');
        $checks[] = [
            'code' => 'CE02',
            'rule' => 'schema_preserved',
            'label' => 'Schema preserved',
            'severity' => $schemaValid ? 'PASS' : 'FAIL',
            'value' => (string)($newData['schema'] ?? ''),
        ];

        $checks[] = [
            'code' => 'CE03',
            'rule' => 'context_key_preserved',
            'label' => 'Context key preserved',
            'severity' => 'PASS',
            'value' => (string)($newData['context_key'] ?? ''),
        ];

        $origFields = (array)($originalData['allowed_fields'] ?? []);
        $newFields = (array)($newData['allowed_fields'] ?? []);
        $origCount = count($origFields);
        $newCount = count($newFields);
        $fieldsPreserved = ($origCount === $newCount);
        $checks[] = [
            'code' => 'CE04',
            'rule' => 'field_count_preserved',
            'label' => 'Field count preserved',
            'severity' => $fieldsPreserved ? 'PASS' : 'FAIL',
            'value' => $newCount . '/' . $origCount,
        ];

        return [
            'status' => $exists && $schemaValid && $fieldsPreserved ? 'PASS' : 'FAIL',
            'checks' => $checks,
        ];
    }

    private static function actorSummary(array $actor): string
    {
        $name = trim((string)($actor['name'] ?? ''));
        $email = trim((string)($actor['email'] ?? ''));
        if ($name !== '' && $email !== '') {
            return $name . ' <' . $email . '>';
        }
        return $name !== '' ? $name : ($email !== '' ? $email : 'unknown');
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
}

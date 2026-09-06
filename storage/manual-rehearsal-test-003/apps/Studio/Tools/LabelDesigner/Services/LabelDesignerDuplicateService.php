<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\LabelDesigner\Services;

final class LabelDesignerDuplicateService
{
    private const SNAPSHOT_ROOT = APP_ROOT . '/storage/studio-snapshots/label-designer';

    private const ALLOWED_TYPES = ['context', 'template', 'rule'];

    private const DISCOVERY_TYPE_MAP = [
        'context' => 'contexts',
        'template' => 'templates',
        'rule' => 'rules',
    ];

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array<string,mixed>
     */
    public static function duplicate(array $input, array $actor = []): array
    {
        $resourceType = trim((string)($input['resource_type'] ?? ''));
        $sourceKey = trim((string)($input['source_key'] ?? ''));
        $newKey = trim((string)($input['new_key'] ?? ''));
        $ownerKey = trim((string)($input['owner_key'] ?? ''));
        $confirm = trim((string)($input['confirm_duplicate'] ?? ''));

        if (!in_array($resourceType, self::ALLOWED_TYPES, true)) {
            return ['ok' => false, 'errors' => ['Invalid resource type. Allowed: context, template, rule.']];
        }

        if ($sourceKey === '' || $newKey === '') {
            return ['ok' => false, 'errors' => ['Source key and new key are required.']];
        }

        if ($sourceKey === $newKey) {
            return ['ok' => false, 'errors' => ['New key must differ from source key.']];
        }

        if ($ownerKey === '') {
            return ['ok' => false, 'errors' => ['Owner key is required.']];
        }

        if ($ownerKey === '' || $resourceType === '' || $sourceKey === '' || $newKey === '') {
            return ['ok' => false, 'errors' => ['Missing required fields.']];
        }

        $sourcePathRel = self::resolveSourcePath($ownerKey, $resourceType, $sourceKey);
        if ($sourcePathRel === '') {
            return ['ok' => false, 'errors' => ['Source resource not found for key "' . $sourceKey . '".']];
        }

        $sourceFull = APP_ROOT . '/' . ltrim($sourcePathRel, '/');
        $sourceJson = is_file($sourceFull) ? @file_get_contents($sourceFull) : false;
        if (!is_string($sourceJson) || $sourceJson === '') {
            return ['ok' => false, 'errors' => ['Failed to read source resource file.']];
        }

        $sourceData = @json_decode($sourceJson, true);
        if (!is_array($sourceData)) {
            return ['ok' => false, 'errors' => ['Source resource file is not valid JSON.']];
        }

        // Validate the new key format per resource type
        $keyValidation = self::validateNewKey($newKey, $resourceType);
        if (!$keyValidation['ok']) {
            return $keyValidation;
        }

        // Determine owner root and target directory
        $ownerRoot = self::resolveOwnerRootByKey($ownerKey);
        if ($ownerRoot === '') {
            return ['ok' => false, 'errors' => ['Could not resolve owner root for "' . $ownerKey . '".']];
        }

        $discoveryType = self::DISCOVERY_TYPE_MAP[$resourceType] ?? '';
        $targetDir = $ownerRoot . '/Resources/labels/' . $discoveryType;
        $targetFileName = $newKey . '.json';
        $targetPath = $targetDir . '/' . $targetFileName;
        $targetPathRel = self::toRelativePath($targetPath);

        if (is_file($targetPath)) {
            return ['ok' => false, 'errors' => ['Target file already exists: ' . $targetPathRel]];
        }

        if (!self::isPathInside($targetPath, $ownerRoot)) {
            return ['ok' => false, 'errors' => ['Target path is outside owner root.']];
        }

        // Build the new resource payload with updated identity fields
        $newData = self::buildNewResource($sourceData, $resourceType, $sourceKey, $newKey, $ownerKey);

        if ($confirm !== 'yes') {
            // Return preview without writing
            $previewJson = json_encode($newData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            return [
                'ok' => true,
                'preview' => true,
                'resource_type' => $resourceType,
                'source_key' => $sourceKey,
                'new_key' => $newKey,
                'source_path' => $sourcePathRel,
                'target_path' => $targetPathRel,
                'resource_json' => is_string($previewJson) ? $previewJson : '{}',
                'errors' => [],
            ];
        }

        // Snapshot before write
        $snapshotResult = self::writeSnapshot([
            'resource_type' => $resourceType,
            'action' => $resourceType . '-duplicate',
            'owner_key' => $ownerKey,
            'source_key' => $sourceKey,
            'new_key' => $newKey,
            'source_path' => $sourcePathRel,
            'target_path' => $targetPathRel,
            'previous_content' => null,
            'proposed_content' => $newData,
            'timestamp' => gmdate('c'),
            'user' => self::actorSummary($actor),
            'rollback_hint' => 'Delete ' . $targetPathRel . ' to rollback ' . $resourceType . '-duplicate.',
        ]);

        if (empty($snapshotResult['ok'])) {
            return ['ok' => false, 'errors' => ['Failed to create snapshot before write.']];
        }

        if (!is_dir($targetDir)) {
            if (!@mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                return ['ok' => false, 'errors' => ['Failed to create target directory.'],
                        'snapshot_path' => (string)($snapshotResult['path_rel'] ?? '')];
            }
        }

        $newJson = json_encode($newData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($newJson) || $newJson === '') {
            return ['ok' => false, 'errors' => ['Failed to encode new resource JSON.']];
        }

        $tmpPath = $targetPath . '.tmp.' . getmypid();
        $written = @file_put_contents($tmpPath, $newJson . "\n");
        if ($written === false) {
            @unlink($tmpPath);
            return ['ok' => false, 'errors' => ['Failed to write temporary file.'],
                    'snapshot_path' => (string)($snapshotResult['path_rel'] ?? '')];
        }

        if (!@rename($tmpPath, $targetPath)) {
            @unlink($tmpPath);
            return ['ok' => false, 'errors' => ['Failed to finalize file write.'],
                    'snapshot_path' => (string)($snapshotResult['path_rel'] ?? '')];
        }

        $postDiag = self::runPostWriteDiagnostics($targetPath, $newData);

        return [
            'ok' => true,
            'preview' => false,
            'errors' => [],
            'resource_type' => $resourceType,
            'source_key' => $sourceKey,
            'new_key' => $newKey,
            'created_path' => $targetPathRel,
            'snapshot_path' => (string)($snapshotResult['path_rel'] ?? ''),
            'diagnostics' => $postDiag,
        ];
    }

    /**
     * @return array{ok:bool,errors?:array<int,string>}
     */
    private static function validateNewKey(string $key, string $resourceType): array
    {
        if ($key === '' || $key !== trim($key)) {
            return ['ok' => false, 'errors' => ['Key must not be empty or have leading/trailing whitespace.']];
        }

        if (str_contains($key, '..') || str_contains($key, '/') || str_contains($key, '\\')) {
            return ['ok' => false, 'errors' => ['Key must not contain path traversal sequences.']];
        }

        // Resource keys: allow [a-zA-Z0-9._-]
        if (preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]*$/', $key) !== 1) {
            return ['ok' => false, 'errors' => ['Key must start with alphanumeric and contain only letters, numbers, dots, underscores, or hyphens.']];
        }

        return ['ok' => true];
    }

    private static function resolveSourcePath(string $ownerKey, string $resourceType, string $sourceKey): string
    {
        $discovery = LabelDesignerDiscoveryService::discover();
        $discoveryType = self::DISCOVERY_TYPE_MAP[$resourceType] ?? '';
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

            $keyField = $resourceType === 'rule' ? 'rule_key' : $resourceType . '_key';
            if (trim((string)($data[$keyField] ?? '')) === $sourceKey) {
                return $filePath;
            }
        }

        return '';
    }

    /**
     * @param array<string,mixed> $sourceData
     * @return array<string,mixed>
     */
    private static function buildNewResource(array $sourceData, string $resourceType, string $sourceKey, string $newKey, string $ownerKey): array
    {
        $newData = $sourceData;

        if ($resourceType === 'context') {
            $newData['context_key'] = $newKey;
            $newData['purpose'] = ($newData['purpose'] ?? '') . ' (copy of ' . $sourceKey . ')';
        } elseif ($resourceType === 'template') {
            $newData['template_key'] = $newKey;
        } elseif ($resourceType === 'rule') {
            $newData['rule_key'] = $newKey;
        }

        $newData['owner_key'] = $ownerKey;
        $newData['write_status'] = 'active';
        $newData['resource_type'] = $resourceType;
        unset($newData['snapshot_id'], $newData['created_at'], $newData['updated_at']);

        return $newData;
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

        $resourceType = (string)($payload['resource_type'] ?? 'resource');
        $action = (string)($payload['action'] ?? $resourceType . '-duplicate');
        $newKey = (string)($payload['new_key'] ?? 'resource');
        $safeKey = preg_replace('/[^a-z0-9._-]+/i', '-', strtolower($newKey)) ?: 'resource';
        $snapshotId = gmdate('Ymd_His') . '_' . substr(sha1($safeKey . microtime(true)), 0, 10);
        $snapshotPath = self::SNAPSHOT_ROOT . '/' . $action . '-' . $safeKey . '-' . $snapshotId . '.json';

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
     * @param array<string,mixed> $newData
     * @return array<string,mixed>
     */
    private static function runPostWriteDiagnostics(string $absolutePath, array $newData): array
    {
        $checks = [];

        $exists = is_file($absolutePath);
        $checks[] = [
            'code' => 'DD01',
            'rule' => 'target_file_exists',
            'label' => 'Target file exists',
            'severity' => $exists ? 'PASS' : 'FAIL',
            'value' => self::toRelativePath($absolutePath),
        ];

        return [
            'status' => $exists ? 'PASS' : 'FAIL',
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
        $root = realpath(APP_ROOT);
        $realPath = realpath($path) ?: $path;
        if (!is_string($root)) {
            return $path;
        }

        if ($realPath === $root) {
            return '.';
        }

        if (str_starts_with($realPath, $root . '/')) {
            return substr($realPath, strlen($root) + 1);
        }

        return $path;
    }
}

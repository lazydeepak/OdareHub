<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\LabelDesigner\Services;

final class LabelDesignerTemplateEditService
{
    private const SNAPSHOT_ROOT = APP_ROOT . '/storage/studio-snapshots/label-designer';

    public static function editTemplate(array $input, array $actor = []): array
    {
        $ownerKey = trim((string)($input['owner_key'] ?? ''));
        $templateKey = trim((string)($input['template_key'] ?? ''));
        $label = trim((string)($input['label'] ?? ''));
        $purpose = trim((string)($input['purpose'] ?? ''));
        $sizeLabel = trim((string)($input['size_label'] ?? ''));
        $sizeWidth = trim((string)($input['size_width'] ?? ''));
        $sizeHeight = trim((string)($input['size_height'] ?? ''));
        $confirm = trim((string)($input['confirm_edit'] ?? ''));

        if ($ownerKey === '' || $templateKey === '') {
            return ['ok' => false, 'errors' => ['Owner key and template key are required.']];
        }

        $sourcePathRel = self::resolveSourcePath($ownerKey, $templateKey);
        if ($sourcePathRel === '') {
            return ['ok' => false, 'errors' => ['Template resource not found for key "' . $templateKey . '".']];
        }

        $sourceFull = APP_ROOT . '/' . ltrim($sourcePathRel, '/');
        $sourceJson = is_file($sourceFull) ? @file_get_contents($sourceFull) : false;
        if (!is_string($sourceJson) || $sourceJson === '') {
            return ['ok' => false, 'errors' => ['Failed to read template resource file.']];
        }

        $templateData = @json_decode($sourceJson, true);
        if (!is_array($templateData)) {
            return ['ok' => false, 'errors' => ['Template resource file is not valid JSON.']];
        }

        $ownerRoot = self::resolveOwnerRootByKey($ownerKey);
        if ($ownerRoot === '') {
            return ['ok' => false, 'errors' => ['Could not resolve owner root for "' . $ownerKey . '".']];
        }

        if (!self::isPathInside($sourceFull, $ownerRoot)) {
            return ['ok' => false, 'errors' => ['Template file path is outside owner root.']];
        }

        $fieldLabels = isset($input['field_labels']) && is_array($input['field_labels'])
            ? $input['field_labels']
            : [];
        $blockSummaries = isset($input['block_summaries']) && is_array($input['block_summaries'])
            ? $input['block_summaries']
            : [];

        $modified = self::applyEdits($templateData, $templateKey, $label, $purpose, $sizeLabel, $sizeWidth, $sizeHeight, $fieldLabels, $blockSummaries);
        if (!empty($modified['errors'])) {
            return ['ok' => false, 'errors' => $modified['errors']];
        }

        if ($confirm !== 'yes') {
            $previewJson = json_encode($modified['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            return [
                'ok' => true,
                'preview' => true,
                'template_key' => $templateKey,
                'source_path' => $sourcePathRel,
                'template_json' => is_string($previewJson) ? $previewJson : '{}',
                'errors' => [],
            ];
        }

        $snapshotResult = self::writeSnapshot([
            'resource_type' => 'template',
            'action' => 'template-edit',
            'owner_key' => $ownerKey,
            'template_key' => $templateKey,
            'source_path' => $sourcePathRel,
            'previous_content' => $templateData,
            'proposed_content' => $modified['data'],
            'timestamp' => gmdate('c'),
            'user' => self::actorSummary($actor),
            'rollback_hint' => 'Restore previous_content from this snapshot to rollback template-edit for ' . $templateKey . '.',
        ]);

        if (empty($snapshotResult['ok'])) {
            return ['ok' => false, 'errors' => ['Failed to create snapshot before write.']];
        }

        $newJson = json_encode($modified['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($newJson) || $newJson === '') {
            return ['ok' => false, 'errors' => ['Failed to encode modified template JSON.']];
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

        $postDiag = self::runPostWriteDiagnostics($sourceFull, $modified['data'], $templateData);

        return [
            'ok' => true,
            'preview' => false,
            'errors' => [],
            'template_key' => $templateKey,
            'created_path' => $sourcePathRel,
            'snapshot_path' => (string)($snapshotResult['path_rel'] ?? ''),
            'diagnostics' => $postDiag,
        ];
    }

    private static function resolveSourcePath(string $ownerKey, string $templateKey): string
    {
        $discovery = LabelDesignerDiscoveryService::discover();
        $discoveryType = 'templates';

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

            if (trim((string)($data['template_key'] ?? '')) === $templateKey) {
                return $filePath;
            }
        }

        return '';
    }

    private static function applyEdits(array $templateData, string $templateKey, string $label, string $purpose, string $sizeLabel, string $sizeWidth, string $sizeHeight, array $fieldLabels, array $blockSummaries): array
    {
        $errors = [];
        $modified = $templateData;

        // Forbidden structural mutation checks
        if ((string)($templateData['template_key'] ?? '') !== $templateKey) {
            $errors[] = 'template_key mismatch — template_key cannot be changed.';
        }

        $originalCtxRef = (string)($templateData['context_ref']['context_key'] ?? $templateData['context_key'] ?? '');
        if ($originalCtxRef === '') {
            $errors[] = 'Template missing context reference — structural integrity check failed.';
        }

        // Apply metadata edits (safe, non-structural)
        if ($label !== '') {
            $modified['label'] = $label;
        }

        if ($purpose !== '') {
            $modified['purpose'] = $purpose;
        }

        // Size metadata - only if already present
        if (isset($modified['layout']['label_size']) || $sizeLabel !== '' || $sizeWidth !== '' || $sizeHeight !== '') {
            if (!isset($modified['layout'])) {
                $modified['layout'] = [];
            }
            if (!isset($modified['layout']['label_size']) && is_array($modified['layout'])) {
                $modified['layout']['label_size'] = [];
            }
            if ($sizeLabel !== '') {
                $modified['layout']['label_size']['label'] = $sizeLabel;
            }
            if ($sizeWidth !== '') {
                $modified['layout']['label_size']['width'] = $sizeWidth;
            }
            if ($sizeHeight !== '') {
                $modified['layout']['label_size']['height'] = $sizeHeight;
            }
        }

        // Field display labels only
        $existingFields = isset($modified['fields']) && is_array($modified['fields'])
            ? $modified['fields']
            : [];

        if ($fieldLabels !== []) {
            $fieldMap = [];
            foreach ($existingFields as $fi => $field) {
                $fk = trim((string)($field['field_key'] ?? ''));
                if ($fk !== '') {
                    $fieldMap[$fk] = $fi;
                }
            }

            foreach ($fieldLabels as $fk => $newLabel) {
                $fk = trim((string)$fk);
                $newLabel = trim((string)$newLabel);
                if ($fk === '') {
                    continue;
                }
                if (!isset($fieldMap[$fk])) {
                    $errors[] = 'Unknown field_key "' . $fk . '" — field keys cannot be changed or added.';
                    continue;
                }
                if ($newLabel !== '') {
                    $existingFields[$fieldMap[$fk]]['label'] = $newLabel;
                }
            }

            $modified['fields'] = $existingFields;
        }

        // Block display/title/text/content summaries only
        $existingBlocks = isset($modified['layout']['blocks']) && is_array($modified['layout']['blocks'])
            ? $modified['layout']['blocks']
            : [];

        if ($blockSummaries !== []) {
            $blockMap = [];
            foreach ($existingBlocks as $bi => $block) {
                $bk = (string)($block['key'] ?? $block['block_key'] ?? '');
                if ($bk !== '') {
                    $blockMap[$bk] = $bi;
                }
            }

            foreach ($blockSummaries as $bk => $summary) {
                $bk = trim((string)$bk);
                $summary = trim((string)$summary);
                if ($bk === '') {
                    continue;
                }
                if (!isset($blockMap[$bk])) {
                    $errors[] = 'Unknown block "' . $bk . '" — block keys cannot be changed or added.';
                    continue;
                }

                $idx = $blockMap[$bk];
                // Allow only simple scalar fields that already exist
                if ($summary !== '' && isset($existingBlocks[$idx])) {
                    if (isset($existingBlocks[$idx]['title'])) {
                        $existingBlocks[$idx]['title'] = $summary;
                    } elseif (isset($existingBlocks[$idx]['content'])) {
                        $existingBlocks[$idx]['content'] = $summary;
                    } elseif (isset($existingBlocks[$idx]['text'])) {
                        $existingBlocks[$idx]['text'] = $summary;
                    }
                }
            }

            $modified['layout']['blocks'] = $existingBlocks;
        }

        if ($errors === [] && $label === '' && $purpose === '' && $sizeLabel === '' && $sizeWidth === '' && $sizeHeight === '' && $fieldLabels === [] && $blockSummaries === []) {
            return ['errors' => ['No changes provided.']];
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

        $templateKey = (string)($payload['template_key'] ?? 'template');
        $safeKey = preg_replace('/[^a-z0-9._-]+/i', '-', strtolower($templateKey)) ?: 'template';
        $snapshotId = gmdate('Ymd_His') . '_' . substr(sha1($safeKey . microtime(true)), 0, 10);
        $snapshotPath = self::SNAPSHOT_ROOT . '/template-edit-' . $safeKey . '-' . $snapshotId . '.json';

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

    private static function runPostWriteDiagnostics(string $absolutePath, array $newData, array $originalData): array
    {
        $checks = [];

        // TE01: source resolved
        $checks[] = [
            'code' => 'TE01',
            'rule' => 'source_resolved',
            'label' => 'Source template resolved',
            'severity' => 'PASS',
            'value' => self::toRelativePath($absolutePath),
        ];

        // TE02: snapshot written
        $checks[] = [
            'code' => 'TE02',
            'rule' => 'snapshot_written',
            'label' => 'Snapshot written before edit',
            'severity' => 'PASS',
            'value' => 'pre-write snapshot created',
        ];

        // TE03: write completed
        $exists = is_file($absolutePath);
        $checks[] = [
            'code' => 'TE03',
            'rule' => 'write_completed',
            'label' => 'Write completed successfully',
            'severity' => $exists ? 'PASS' : 'FAIL',
            'value' => self::toRelativePath($absolutePath),
        ];

        // TE04: forbidden structural mutation absent
        $structuralOk = true;
        if ((string)($newData['template_key'] ?? '') !== (string)($originalData['template_key'] ?? '')) {
            $structuralOk = false;
        }
        $newCtxRef = (string)($newData['context_ref']['context_key'] ?? $newData['context_key'] ?? '');
        $origCtxRef = (string)($originalData['context_ref']['context_key'] ?? $originalData['context_key'] ?? '');
        if ($newCtxRef !== $origCtxRef) {
            $structuralOk = false;
        }
        $newFieldCount = isset($newData['fields']) && is_array($newData['fields']) ? count($newData['fields']) : 0;
        $origFieldCount = isset($originalData['fields']) && is_array($originalData['fields']) ? count($originalData['fields']) : 0;
        if ($newFieldCount !== $origFieldCount) {
            $structuralOk = false;
        }
        $newBlockCount = isset($newData['layout']['blocks']) && is_array($newData['layout']['blocks']) ? count($newData['layout']['blocks']) : 0;
        $origBlockCount = isset($originalData['layout']['blocks']) && is_array($originalData['layout']['blocks']) ? count($originalData['layout']['blocks']) : 0;
        if ($newBlockCount !== $origBlockCount) {
            $structuralOk = false;
        }

        $checks[] = [
            'code' => 'TE04',
            'rule' => 'forbidden_structural_mutation_absent',
            'label' => 'No forbidden structural mutations',
            'severity' => $structuralOk ? 'PASS' : 'FAIL',
            'value' => $structuralOk ? 'template_key/context_ref/field_count/block_count unchanged' : 'Structural mutation detected',
        ];

        return [
            'status' => $exists && $structuralOk ? 'PASS' : 'FAIL',
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

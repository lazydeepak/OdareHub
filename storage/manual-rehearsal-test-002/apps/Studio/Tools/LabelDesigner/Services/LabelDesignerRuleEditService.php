<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\LabelDesigner\Services;

final class LabelDesignerRuleEditService
{
    private const SNAPSHOT_ROOT = APP_ROOT . '/storage/studio-snapshots/label-designer';

    public static function editRule(array $input, array $actor = []): array
    {
        $ownerKey = trim((string)($input['owner_key'] ?? ''));
        $ruleKey = trim((string)($input['rule_key'] ?? ''));
        $label = trim((string)($input['label'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $priority = trim((string)($input['priority'] ?? ''));
        $enabledInput = trim((string)($input['enabled'] ?? ''));
        $confirm = trim((string)($input['confirm_edit'] ?? ''));

        if ($ownerKey === '' || $ruleKey === '') {
            return ['ok' => false, 'errors' => ['Owner key and rule key are required.']];
        }

        $sourcePathRel = self::resolveSourcePath($ownerKey, $ruleKey);
        if ($sourcePathRel === '') {
            return ['ok' => false, 'errors' => ['Rule resource not found for key "' . $ruleKey . '".']];
        }

        $sourceFull = APP_ROOT . '/' . ltrim($sourcePathRel, '/');
        $sourceJson = is_file($sourceFull) ? @file_get_contents($sourceFull) : false;
        if (!is_string($sourceJson) || $sourceJson === '') {
            return ['ok' => false, 'errors' => ['Failed to read rule resource file.']];
        }

        $ruleData = @json_decode($sourceJson, true);
        if (!is_array($ruleData)) {
            return ['ok' => false, 'errors' => ['Rule resource file is not valid JSON.']];
        }

        $ownerRoot = self::resolveOwnerRootByKey($ownerKey);
        if ($ownerRoot === '') {
            return ['ok' => false, 'errors' => ['Could not resolve owner root for "' . $ownerKey . '".']];
        }

        if (!self::isPathInside($sourceFull, $ownerRoot)) {
            return ['ok' => false, 'errors' => ['Rule file path is outside owner root.']];
        }

        $conditionValues = isset($input['condition_values']) && is_array($input['condition_values'])
            ? $input['condition_values']
            : [];
        $effectLabels = isset($input['effect_labels']) && is_array($input['effect_labels'])
            ? $input['effect_labels']
            : [];

        $modified = self::applyEdits($ruleData, $ruleKey, $label, $description, $priority, $enabledInput, $conditionValues, $effectLabels);
        if (!empty($modified['errors'])) {
            return ['ok' => false, 'errors' => $modified['errors']];
        }

        if ($confirm !== 'yes') {
            $previewJson = json_encode($modified['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            return [
                'ok' => true,
                'preview' => true,
                'rule_key' => $ruleKey,
                'source_path' => $sourcePathRel,
                'rule_json' => is_string($previewJson) ? $previewJson : '{}',
                'errors' => [],
            ];
        }

        $snapshotResult = self::writeSnapshot([
            'resource_type' => 'rule',
            'action' => 'rule-edit',
            'owner_key' => $ownerKey,
            'rule_key' => $ruleKey,
            'source_path' => $sourcePathRel,
            'previous_content' => $ruleData,
            'proposed_content' => $modified['data'],
            'timestamp' => gmdate('c'),
            'user' => self::actorSummary($actor),
            'rollback_hint' => 'Restore previous_content from this snapshot to rollback rule-edit for ' . $ruleKey . '.',
        ]);

        if (empty($snapshotResult['ok'])) {
            return ['ok' => false, 'errors' => ['Failed to create snapshot before write.']];
        }

        $newJson = json_encode($modified['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($newJson) || $newJson === '') {
            return ['ok' => false, 'errors' => ['Failed to encode modified rule JSON.']];
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

        $postDiag = self::runPostWriteDiagnostics($sourceFull, $modified['data'], $ruleData);

        return [
            'ok' => true,
            'preview' => false,
            'errors' => [],
            'rule_key' => $ruleKey,
            'created_path' => $sourcePathRel,
            'snapshot_path' => (string)($snapshotResult['path_rel'] ?? ''),
            'diagnostics' => $postDiag,
        ];
    }

    private static function resolveSourcePath(string $ownerKey, string $ruleKey): string
    {
        $discovery = LabelDesignerDiscoveryService::discover();
        $discoveryType = 'rules';

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

            if (trim((string)($data['rule_key'] ?? '')) === $ruleKey) {
                return $filePath;
            }
        }

        return '';
    }

    private static function applyEdits(array $ruleData, string $ruleKey, string $label, string $description, string $priority, string $enabledInput, array $conditionValues, array $effectLabels): array
    {
        $errors = [];
        $modified = $ruleData;

        // Forbidden structural mutation checks
        if ((string)($ruleData['rule_key'] ?? '') !== $ruleKey) {
            $errors[] = 'rule_key mismatch — rule_key cannot be changed.';
        }

        $origCtxKey = (string)($ruleData['context_key'] ?? '');
        if ($origCtxKey === '') {
            $errors[] = 'Rule missing context_key — structural integrity check failed.';
        }

        $origTplKey = (string)($ruleData['template_key'] ?? '');
        if ($origTplKey === '') {
            $errors[] = 'Rule missing template_key — structural integrity check failed.';
        }

        // Apply metadata edits (safe, non-structural)
        if ($label !== '') {
            $modified['label'] = $label;
        }

        if ($description !== '') {
            $modified['description'] = $description;
        }

        if ($priority !== '') {
            $modified['priority'] = $priority;
        }

        // Enabled flag
        if ($enabledInput !== '') {
            $modified['enabled'] = !in_array(strtolower($enabledInput), ['0', 'false', 'no', 'off'], true);
        }

        // Condition value edits only (field_key, operator must not change)
        $existingConditions = isset($ruleData['conditions']) && is_array($ruleData['conditions'])
            ? array_values(array_filter($ruleData['conditions'], 'is_array'))
            : [];

        if ($conditionValues !== []) {
            if (count($conditionValues) !== count($existingConditions)) {
                $errors[] = 'Condition count mismatch — conditions cannot be added or removed.';
            } else {
                foreach ($conditionValues as $idx => $cv) {
                    $idx = (int)$idx;
                    if (!isset($existingConditions[$idx])) {
                        $errors[] = 'Condition index ' . $idx . ' does not exist.';
                        continue;
                    }
                    $value = trim((string)($cv['value'] ?? ''));
                    if ($value !== '') {
                        $modified['conditions'][$idx]['value'] = $value;
                    }
                }
            }
        }

        // Effect label/value edits only (type, target must not change)
        $existingEffects = isset($ruleData['effects']) && is_array($ruleData['effects'])
            ? array_values(array_filter($ruleData['effects'], 'is_array'))
            : [];

        if ($effectLabels !== []) {
            if (count($effectLabels) !== count($existingEffects)) {
                $errors[] = 'Effect count mismatch — effects cannot be added or removed.';
            } else {
                foreach ($effectLabels as $idx => $el) {
                    $idx = (int)$idx;
                    if (!isset($existingEffects[$idx])) {
                        $errors[] = 'Effect index ' . $idx . ' does not exist.';
                        continue;
                    }
                    $newLabel = trim((string)($el['label'] ?? ''));
                    $newValue = trim((string)($el['value'] ?? ''));
                    if ($newLabel !== '') {
                        $modified['effects'][$idx]['label'] = $newLabel;
                    }
                    if ($newValue !== '') {
                        $modified['effects'][$idx]['value'] = $newValue;
                    }
                }
            }
        }

        if ($errors === [] && $label === '' && $description === '' && $priority === '' && $enabledInput === '' && $conditionValues === [] && $effectLabels === []) {
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

        $ruleKey = (string)($payload['rule_key'] ?? 'rule');
        $safeKey = preg_replace('/[^a-z0-9._-]+/i', '-', strtolower($ruleKey)) ?: 'rule';
        $snapshotId = gmdate('Ymd_His') . '_' . substr(sha1($safeKey . microtime(true)), 0, 10);
        $snapshotPath = self::SNAPSHOT_ROOT . '/rule-edit-' . $safeKey . '-' . $snapshotId . '.json';

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

        // RE01: source resolved
        $checks[] = [
            'code' => 'RE01',
            'rule' => 'source_resolved',
            'label' => 'Source rule resolved',
            'severity' => 'PASS',
            'value' => self::toRelativePath($absolutePath),
        ];

        // RE02: snapshot written
        $checks[] = [
            'code' => 'RE02',
            'rule' => 'snapshot_written',
            'label' => 'Snapshot written before edit',
            'severity' => 'PASS',
            'value' => 'pre-write snapshot created',
        ];

        // RE03: write completed
        $exists = is_file($absolutePath);
        $checks[] = [
            'code' => 'RE03',
            'rule' => 'write_completed',
            'label' => 'Write completed successfully',
            'severity' => $exists ? 'PASS' : 'FAIL',
            'value' => self::toRelativePath($absolutePath),
        ];

        // RE04: forbidden structural mutation absent
        $structuralOk = true;
        if ((string)($newData['rule_key'] ?? '') !== (string)($originalData['rule_key'] ?? '')) {
            $structuralOk = false;
        }
        if ((string)($newData['context_key'] ?? '') !== (string)($originalData['context_key'] ?? '')) {
            $structuralOk = false;
        }
        if ((string)($newData['template_key'] ?? '') !== (string)($originalData['template_key'] ?? '')) {
            $structuralOk = false;
        }
        $newCondCount = isset($newData['conditions']) && is_array($newData['conditions']) ? count($newData['conditions']) : 0;
        $origCondCount = isset($originalData['conditions']) && is_array($originalData['conditions']) ? count($originalData['conditions']) : 0;
        if ($newCondCount !== $origCondCount) {
            $structuralOk = false;
        }
        $newEffCount = isset($newData['effects']) && is_array($newData['effects']) ? count($newData['effects']) : 0;
        $origEffCount = isset($originalData['effects']) && is_array($originalData['effects']) ? count($originalData['effects']) : 0;
        if ($newEffCount !== $origEffCount) {
            $structuralOk = false;
        }
        // Verify no condition field_key or operator changed
        if ($newCondCount === $origCondCount && $origCondCount > 0) {
            $origConds = array_values(array_filter($originalData['conditions'] ?? [], 'is_array'));
            $newConds = array_values(array_filter($newData['conditions'] ?? [], 'is_array'));
            foreach ($origConds as $idx => $oc) {
                if (isset($newConds[$idx])) {
                    if ((string)($newConds[$idx]['field_key'] ?? '') !== (string)($oc['field_key'] ?? '')) {
                        $structuralOk = false;
                    }
                    if ((string)($newConds[$idx]['operator'] ?? '') !== (string)($oc['operator'] ?? '')) {
                        $structuralOk = false;
                    }
                }
            }
        }
        // Verify no effect type or target changed
        if ($newEffCount === $origEffCount && $origEffCount > 0) {
            $origEffs = array_values(array_filter($originalData['effects'] ?? [], 'is_array'));
            $newEffs = array_values(array_filter($newData['effects'] ?? [], 'is_array'));
            foreach ($origEffs as $idx => $oe) {
                if (isset($newEffs[$idx])) {
                    if ((string)($newEffs[$idx]['type'] ?? '') !== (string)($oe['type'] ?? '')) {
                        $structuralOk = false;
                    }
                    if ((string)($newEffs[$idx]['target'] ?? '') !== (string)($oe['target'] ?? '')) {
                        $structuralOk = false;
                    }
                }
            }
        }

        $checks[] = [
            'code' => 'RE04',
            'rule' => 'forbidden_structural_mutation_absent',
            'label' => 'No forbidden structural mutations',
            'severity' => $structuralOk ? 'PASS' : 'FAIL',
            'value' => $structuralOk ? 'rule_key/context_key/template_key/condition_field_operator/effect_type_target/counts unchanged' : 'Structural mutation detected',
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

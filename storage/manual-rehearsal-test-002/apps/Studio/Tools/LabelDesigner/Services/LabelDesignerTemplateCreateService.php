<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\LabelDesigner\Services;

final class LabelDesignerTemplateCreateService
{
    private const SNAPSHOT_ROOT = APP_ROOT . '/storage/studio-snapshots/label-designer';

    public static function buildPreview(array $input): array
    {
        $confirmCreate = trim((string)($input['confirm_create'] ?? ''));
        $actorSummary = null;

        $preview = LabelDesignerTemplatePreviewService::buildPreview($input);
        if (empty($preview['ok']) || !isset($preview['template'])) {
            return $preview;
        }

        $template = $preview['template'];
        $selectedContext = isset($preview['selected_context']) && is_array($preview['selected_context'])
            ? $preview['selected_context']
            : null;

        if ($selectedContext === null) {
            return [
                'ok' => false,
                'errors' => ['No context selected for template creation.'],
                'validation' => $preview['validation'] ?? [],
            ];
        }

        $ownerKey = trim((string)($selectedContext['owner_key'] ?? ''));
        $ownerRoot = self::resolveOwnerRootByKey($ownerKey);
        if ($ownerRoot === '') {
            return [
                'ok' => false,
                'errors' => ['Could not resolve owner root for: ' . $ownerKey],
                'validation' => $preview['validation'] ?? [],
            ];
        }

        $templateKey = trim((string)($template['template_key'] ?? ''));
        if ($templateKey === '') {
            return [
                'ok' => false,
                'errors' => ['Template key is empty after preview generation.'],
                'validation' => $preview['validation'] ?? [],
            ];
        }

        $templatesDir = $ownerRoot . '/Resources/labels/templates';
        if (!self::isPathInside($templatesDir, $ownerRoot)) {
            return [
                'ok' => false,
                'errors' => ['Target templates path is outside owner root.'],
                'validation' => $preview['validation'] ?? [],
            ];
        }

        $targetFileName = $templateKey . '.json';
        $targetPath = $templatesDir . '/' . $targetFileName;

        if (!self::isPathInside($targetPath, $ownerRoot)) {
            return [
                'ok' => false,
                'errors' => ['Target template file path failed owner-root validation.'],
                'validation' => $preview['validation'] ?? [],
            ];
        }

        if (is_file($targetPath)) {
            return [
                'ok' => false,
                'errors' => ['Template file already exists. Create flow does not overwrite existing templates.'],
                'validation' => $preview['validation'] ?? [],
            ];
        }

        $previewValidation = $preview['validation'] ?? [];
        $previewValidation['template_key_unique'] = true;
        $previewValidation['target_path_valid'] = true;

        $writeTemplate = $template;
        unset($writeTemplate['preview_only']);
        $writeTemplate['write_status'] = 'created';
        $writeTemplate['notes'] = [
            'created_via_studio_label_designer',
            'resource_canonical',
        ];

        $json = json_encode($writeTemplate, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') {
            return [
                'ok' => false,
                'errors' => ['Failed to encode template JSON.'],
                'validation' => $previewValidation,
            ];
        }

        return [
            'ok' => true,
            'errors' => [],
            'validation' => $previewValidation,
            'selected' => $preview['selected'] ?? [],
            'selected_context' => $selectedContext,
            'target_path' => $targetPath,
            'target_path_rel' => self::toRelativePath($targetPath),
            'templates_dir' => $templatesDir,
            'template' => $writeTemplate,
            'template_json' => $json,
            'owner_key' => $ownerKey,
            'confirm_create' => $confirmCreate,
            'actor_summary' => $actorSummary,
        ];
    }

    public static function createTemplate(array $input, array $actor = []): array
    {
        $preview = self::buildPreview($input);
        if (empty($preview['ok'])) {
            return $preview;
        }

        if (trim((string)($input['confirm_create'] ?? '')) !== 'yes') {
            return [
                'ok' => false,
                'errors' => ['Confirmation is required before writing.'],
                'validation' => $preview['validation'] ?? [],
            ];
        }

        $targetPath = (string)$preview['target_path'];
        $templatesDir = (string)$preview['templates_dir'];
        $json = (string)$preview['template_json'];

        $snapshotResult = self::writeSnapshot([
            'owner_key' => $preview['owner_key'],
            'context_key' => (string)($preview['selected_context']['context_key'] ?? ''),
            'template_key' => (string)($preview['template']['template_key'] ?? ''),
            'resource_type' => 'template',
            'target_path' => (string)$preview['target_path_rel'],
            'previous_content' => null,
            'proposed_content' => $preview['template'],
            'action' => 'create',
            'user' => self::actorSummary($actor),
            'timestamp' => gmdate('c'),
            'validation_result' => $preview['validation'],
            'rollback_hint' => 'Delete ' . $preview['target_path_rel'] . ' for create rollback.',
        ]);

        if (empty($snapshotResult['ok'])) {
            return [
                'ok' => false,
                'errors' => ['Failed to create snapshot metadata before write.'],
                'validation' => $preview['validation'],
            ];
        }

        if (!is_dir($templatesDir)) {
            if (!@mkdir($templatesDir, 0755, true) && !is_dir($templatesDir)) {
                return [
                    'ok' => false,
                    'errors' => ['Failed to create owner templates directory.'],
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
                'errors' => ['Failed to write template file temporary artifact.'],
                'validation' => $preview['validation'],
                'snapshot_path' => (string)$snapshotResult['path_rel'],
            ];
        }

        if (!@rename($tmpPath, $targetPath)) {
            @unlink($tmpPath);
            return [
                'ok' => false,
                'errors' => ['Failed to finalize template file write.'],
                'validation' => $preview['validation'],
                'snapshot_path' => (string)$snapshotResult['path_rel'],
            ];
        }

        $diagnostics = self::runPostWriteDiagnostics($targetPath, $preview['template']);

        return [
            'ok' => true,
            'errors' => [],
            'validation' => $preview['validation'],
            'diagnostics' => $diagnostics,
            'template_key' => (string)($preview['template']['template_key'] ?? ''),
            'created_path' => (string)$preview['target_path_rel'],
            'snapshot_path' => (string)$snapshotResult['path_rel'],
            'owner_key' => (string)$preview['owner_key'],
        ];
    }

    private static function resolveOwnerRootByKey(string $ownerKey): string
    {
        if ($ownerKey === '' || str_contains($ownerKey, '..')) {
            return '';
        }

        $parts = explode('/', $ownerKey);
        if (count($parts) < 1 || $parts[0] === '') {
            return '';
        }

        $appName = $parts[0];
        $appPath = APP_ROOT . '/apps/' . $appName;
        $appReal = realpath($appPath);
        if (!is_string($appReal) || !is_dir($appReal)) {
            $pluginPath = APP_ROOT . '/plugins/' . $appName;
            $pluginReal = realpath($pluginPath);
            return is_string($pluginReal) && is_dir($pluginReal) ? $pluginReal : '';
        }

        if (count($parts) === 1) {
            return $appReal;
        }

        $modulePath = $appReal . '/modules/' . implode('/', array_slice($parts, 1));
        $moduleReal = realpath($modulePath);
        return is_string($moduleReal) && is_dir($moduleReal) ? $moduleReal : '';
    }

    private static function isPathInside(string $path, string $root): bool
    {
        $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/');
        $normalizedPath = str_replace('\\', '/', $path);
        return $normalizedPath === $normalizedRoot || str_starts_with($normalizedPath, $normalizedRoot . '/');
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
        $snapshotPath = self::SNAPSHOT_ROOT . '/template-create-' . $safeKey . '-' . $snapshotId . '.json';

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

    private static function runPostWriteDiagnostics(string $targetPath, array $template): array
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
        $result['schema_valid'] = ((string)($decoded['schema'] ?? '') === 'susankhya.label.template.v1');

        $contextRef = isset($decoded['context_ref']) && is_array($decoded['context_ref']) ? $decoded['context_ref'] : [];
        $ownerKey = (string)($contextRef['owner_key'] ?? '');
        $resolvedRoot = self::resolveOwnerRootByKey($ownerKey);
        $result['owner_path_valid'] = $resolvedRoot !== '';

        if ($result['json_valid'] && $result['schema_valid'] && $result['owner_path_valid']) {
            $result['status'] = 'PASS';
        }

        return $result;
    }

    private static function actorSummary(array $actor): array
    {
        return [
            'id' => isset($actor['id']) ? (string)$actor['id'] : '',
            'email' => isset($actor['email']) ? (string)$actor['email'] : '',
            'username' => isset($actor['username']) ? (string)$actor['username'] : '',
            'name' => isset($actor['name']) ? (string)$actor['name'] : '',
        ];
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

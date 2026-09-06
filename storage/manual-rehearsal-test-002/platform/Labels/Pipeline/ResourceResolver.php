<?php
declare(strict_types=1);

namespace Platform\Labels\Pipeline;

final class ResourceResolver
{
    private const CONTEXT_GLOB = '*.label-context.json';
    private const TEMPLATE_EXT = '.json';
    private const RULES_GLOB = '*.label-rule.json';

    public static function resolve(LabelRuntimeRequest $request): array
    {
        $diagnostics = [];
        $ownerRoot = self::resolveOwnerRoot($request->ownerKey);

        if ($ownerRoot === '') {
            $diagnostics[] = self::diag('FAIL', 'RS-001', "Owner root not found for owner_key '{$request->ownerKey}'", 'owner_key');
            return self::result('', [], [], [], $diagnostics);
        }

        $labelsDir = $ownerRoot . '/Resources/labels';

        $context = self::resolveContext($labelsDir, $request->contextKey, $diagnostics);
        $template = self::resolveTemplate($labelsDir, $request->templateKey, $diagnostics);
        $rules = self::resolveRules($labelsDir, $diagnostics);

        return self::result($ownerRoot, $context, $template, $rules, $diagnostics);
    }

    private static function result(string $ownerRoot, array $context, array $template, array $rules, array $diagnostics): array
    {
        return [
            'owner_root' => $ownerRoot,
            'context' => $context,
            'template' => $template,
            'rules' => $rules,
            'diagnostics' => $diagnostics,
        ];
    }

    private static function diag(string $severity, string $code, string $message, string $field = ''): array
    {
        return [
            'stage' => 'resource_resolution',
            'severity' => $severity,
            'code' => $code,
            'message' => $message,
            'field' => $field,
        ];
    }

    private static function resolveOwnerRoot(string $ownerKey): string
    {
        if ($ownerKey === '' || str_contains($ownerKey, '..')) {
            return '';
        }

        $parts = explode('/', $ownerKey);
        if ($parts[0] === '') {
            return '';
        }

        $appName = $parts[0];
        $root = self::projectRoot();

        $appPath = $root . '/apps/' . $appName;
        $appReal = realpath($appPath);
        if (!is_string($appReal) || !is_dir($appReal)) {
            $pluginPath = $root . '/plugins/' . $appName;
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

    private static function resolveContext(string $labelsDir, string $contextKey, array &$diagnostics): array
    {
        $contextsDir = $labelsDir . '/contexts';

        if (!is_dir($contextsDir)) {
            $diagnostics[] = self::diag('ERROR', 'RS-003', "Contexts directory not found: {$contextsDir}", 'context_key');
            return [];
        }

        $files = glob($contextsDir . '/' . self::CONTEXT_GLOB);
        if ($files === false || count($files) === 0) {
            $diagnostics[] = self::diag('ERROR', 'RS-003', "No context files found in {$contextsDir}", 'context_key');
            return [];
        }

        foreach ($files as $file) {
            $data = self::loadJson($file);
            if ($data === null) {
                continue;
            }
            if (($data['context_key'] ?? '') === $contextKey) {
                return $data;
            }
        }

        $diagnostics[] = self::diag('ERROR', 'RS-003', "Context '{$contextKey}' not found in {$contextsDir}", 'context_key');
        return [];
    }

    private static function resolveTemplate(string $labelsDir, string $templateKey, array &$diagnostics): array
    {
        $templatesDir = $labelsDir . '/templates';

        if (!is_dir($templatesDir)) {
            $diagnostics[] = self::diag('ERROR', 'RS-004', "Templates directory not found: {$templatesDir}", 'template_key');
            return [];
        }

        $file = $templatesDir . '/' . $templateKey . self::TEMPLATE_EXT;
        $data = self::loadJson($file);

        if ($data === null) {
            $diagnostics[] = self::diag('ERROR', 'RS-004', "Template '{$templateKey}' not found at {$file}", 'template_key');
            return [];
        }

        return $data;
    }

    private static function resolveRules(string $labelsDir, array &$diagnostics): array
    {
        $rulesDir = $labelsDir . '/rules';

        if (!is_dir($rulesDir)) {
            return [];
        }

        $files = glob($rulesDir . '/' . self::RULES_GLOB);
        if ($files === false || count($files) === 0) {
            return [];
        }

        $rules = [];
        foreach ($files as $file) {
            $data = self::loadJson($file);
            if ($data !== null) {
                $rules[] = $data;
            }
        }

        if (count($rules) === 0) {
            $diagnostics[] = self::diag('WARN', 'RS-005', "Rule files found but none could be parsed in {$rulesDir}", 'rules');
        }

        return $rules;
    }

    private static function loadJson(string $path): ?array
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        $decoded = json_decode($contents, true);
        return is_array($decoded) ? $decoded : null;
    }

    private static function projectRoot(): string
    {
        if (defined('APP_ROOT')) {
            return APP_ROOT;
        }
        return dirname(__DIR__, 3);
    }
}

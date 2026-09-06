<?php
declare(strict_types=1);

namespace Platform\Security;

final class EngineeringWorkspaceContentContract
{
    private const ENGINEERING_ROOT = APP_ROOT . '/engineering';
    private const TEMPLATE_ROOT = APP_ROOT . '/engineering/_templates';

    /**
     * @var array<string,array{filename:string,label:string,headings:string[]}>
     */
    private const DOCUMENTS = [
        'overview' => [
            'filename' => 'overview.md',
            'label' => 'Overview',
            'headings' => [
                'Purpose',
                'Target State',
                'Responsibilities',
                'Boundaries',
                'Canonical Source Areas',
                'Dependencies',
                'Related Workspaces',
                'Non-goals',
            ],
        ],
        'work' => [
            'filename' => 'work.md',
            'label' => 'Work',
            'headings' => [
                'Current Focus',
                'In Progress',
                'Next',
                'Blocked',
                'Completed',
                'Evidence',
            ],
        ],
        'rules' => [
            'filename' => 'rules.md',
            'label' => 'Rules',
            'headings' => [
                'Working Rules',
                'Safety Rules',
                'Validation Rules',
                'Change Rules',
                'Escalation',
            ],
        ],
        'decisions' => [
            'filename' => 'decisions.md',
            'label' => 'Decisions',
            'headings' => [
                'Decision Log',
            ],
        ],
    ];

    /**
     * @var string[]
     */
    private const SUPPORTED_WORKSPACES = [
        'Studio',
        'Studio/tools/CustomizationStudio',
        'Studio/tools/LocalizationStudio',
        'Studio/tools/LabelDesigner',
        'Studio/tools/ReportDesigner',
        'Manufacturing/Products',
        'Platform/Organization',
        'Plugin/Base',
    ];

    /**
     * @return string[]
     */
    public static function allowedDocumentKeys(): array
    {
        return array_keys(self::DOCUMENTS);
    }

    /**
     * @return string[]
     */
    public static function supportedWorkspaceKeys(): array
    {
        $workspaceKeys = self::SUPPORTED_WORKSPACES;
        sort($workspaceKeys, SORT_STRING);
        return $workspaceKeys;
    }

    public static function isAllowedDocumentKey(string $documentKey): bool
    {
        return isset(self::DOCUMENTS[$documentKey]);
    }

    public static function isSupportedWorkspaceKey(string $workspaceKey): bool
    {
        return in_array(trim($workspaceKey), self::supportedWorkspaceKeys(), true);
    }

    /**
     * Check if a workspace key matches a reserved template directory.
     *
     * The canonical template root is derived from TEMPLATE_ROOT (engineering/_templates).
     * The legacy directory _template (singular) is also reserved so it never appears
     * as a deploy target or accept writes.
     */
    public static function isReservedTemplateWorkspaceKey(string $workspaceKey): bool
    {
        $trimmed = trim($workspaceKey, "/ \t\n\r\0\x0B");
        if ($trimmed === '') {
            return false;
        }

        $canonicalName = basename(rtrim(self::TEMPLATE_ROOT, '/'));
        $legacyName = '_template';

        foreach ([$canonicalName, $legacyName] as $name) {
            if ($trimmed === $name || str_starts_with($trimmed, $name . '/')) {
                return true;
            }
        }

        return false;
    }

    public static function canonicalFilename(string $documentKey): ?string
    {
        return self::DOCUMENTS[$documentKey]['filename'] ?? null;
    }

    public static function documentLabel(string $documentKey): ?string
    {
        return self::DOCUMENTS[$documentKey]['label'] ?? null;
    }

    /**
     * @return string[]
     */
    public static function requiredHeadings(string $documentKey): array
    {
        return self::DOCUMENTS[$documentKey]['headings'] ?? [];
    }

    public static function templateSource(string $documentKey): ?string
    {
        $filename = self::canonicalFilename($documentKey);
        if ($filename === null) {
            return null;
        }

        return 'engineering/_templates/' . $filename;
    }

    public static function templatePath(string $documentKey): ?string
    {
        $filename = self::canonicalFilename($documentKey);
        if ($filename === null) {
            return null;
        }

        return self::TEMPLATE_ROOT . '/' . $filename;
    }

    public static function documentPath(string $workspaceKey, string $documentKey): ?string
    {
        if (!self::isSupportedWorkspaceKey($workspaceKey) || !self::isAllowedDocumentKey($documentKey)) {
            return null;
        }

        $workspaceDir = self::workspaceDir($workspaceKey);
        if ($workspaceDir === null) {
            return null;
        }

        return $workspaceDir . '/' . self::canonicalFilename($documentKey);
    }

    /**
     * @return array<string,mixed>
     */
    public static function initializeMissingDocument(string $workspaceKey, string $documentKey): array
    {
        $path = self::documentPath($workspaceKey, $documentKey);
        if ($path === null) {
            return ['ok' => false, 'created' => false, 'error' => 'Unsupported workspace or document'];
        }

        if (is_file($path)) {
            return ['ok' => true, 'created' => false, 'path' => self::relativePath($path)];
        }

        $workspaceDir = dirname($path);
        if (!is_dir($workspaceDir) && !@mkdir($workspaceDir, 0755, true)) {
            return ['ok' => false, 'created' => false, 'error' => 'Could not create workspace directory'];
        }

        if (!self::pathIsInsideEngineeringRoot($workspaceDir)) {
            return ['ok' => false, 'created' => false, 'error' => 'Workspace path escapes engineering root'];
        }

        $content = self::initialContent($workspaceKey, $documentKey);
        if (@file_put_contents($path, $content, LOCK_EX) === false) {
            return ['ok' => false, 'created' => false, 'error' => 'Could not initialize document'];
        }

        return ['ok' => true, 'created' => true, 'path' => self::relativePath($path)];
    }

    public static function initialContent(string $workspaceKey, string $documentKey): string
    {
        $templatePath = self::templatePath($documentKey);
        $template = $templatePath !== null && is_file($templatePath) ? file_get_contents($templatePath) : false;
        $content = $template !== false ? (string)$template : '# {{workspace_name}}' . "\n";

        $content = str_replace('<Workspace Name>', self::displayWorkspaceName($workspaceKey), $content);
        $content = str_replace('{{workspace_name}}', self::displayWorkspaceName($workspaceKey), $content);
        $content = str_replace('{{workspace_key}}', $workspaceKey, $content);

        return $content;
    }

    public static function markdownForDisplay(string $documentKey, string $content): string
    {
        if (!self::isAllowedDocumentKey($documentKey)) {
            return $content;
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $content);
        foreach (self::requiredHeadings($documentKey) as $heading) {
            $pattern = '/(^## ' . preg_quote($heading, '/') . '\s*$)(.*?)(?=^## |\z)/ms';
            if (preg_match($pattern, $normalized, $match) !== 1) {
                continue;
            }
            $body = trim((string)$match[2]);
            if ($body !== '') {
                continue;
            }
            $replacement = rtrim((string)$match[1]) . "\n\n_Not documented yet._\n\n";
            $normalized = preg_replace($pattern, $replacement, $normalized, 1) ?? $normalized;
        }

        return $normalized;
    }

    /**
     * @return array{ok:bool,missing_headings:string[],error:string}
     */
    public static function validateDocumentContent(string $documentKey, string $content): array
    {
        if (!self::isAllowedDocumentKey($documentKey)) {
            return [
                'ok' => false,
                'missing_headings' => [],
                'error' => 'Unsupported document key',
            ];
        }

        if (trim($content) === '') {
            return [
                'ok' => false,
                'missing_headings' => self::requiredHeadings($documentKey),
                'error' => 'Document is empty',
            ];
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $content);
        if ($documentKey === 'rules') {
            $allNew = self::containsAllHeadings($normalized, self::requiredHeadings($documentKey));
            $legacyHeadings = ['Read First', 'Change Rules', 'Validation Rules', 'Ownership Boundaries', 'Completion Rule'];
            if ($allNew || self::containsAnyHeading($normalized, $legacyHeadings)) {
                return [
                    'ok' => true,
                    'missing_headings' => [],
                    'error' => '',
                ];
            }
        }
        if ($documentKey === 'decisions') {
            if (self::containsAnyHeading($normalized, ['Decision Log', 'Decision Record Format'])) {
                return [
                    'ok' => true,
                    'missing_headings' => [],
                    'error' => '',
                ];
            }
        }

        $missing = [];
        foreach (self::requiredHeadings($documentKey) as $heading) {
            $pattern = '/^## ' . preg_quote($heading, '/') . '\s*$/m';
            if (preg_match($pattern, $normalized) !== 1) {
                $missing[] = $heading;
            }
        }

        return [
            'ok' => $missing === [],
            'missing_headings' => $missing,
            'error' => $missing === [] ? '' : 'Document is missing required headings',
        ];
    }

    /**
     * @param string[] $headings
     */
    private static function containsAllHeadings(string $content, array $headings): bool
    {
        foreach ($headings as $heading) {
            if (!self::containsHeading($content, $heading)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param string[] $headings
     */
    private static function containsAnyHeading(string $content, array $headings): bool
    {
        foreach ($headings as $heading) {
            if (self::containsHeading($content, $heading)) {
                return true;
            }
        }
        return false;
    }

    private static function containsHeading(string $content, string $heading): bool
    {
        return preg_match('/^## ' . preg_quote($heading, '/') . '\s*$/m', $content) === 1;
    }

    public static function pathIsInsideEngineeringRoot(string $path): bool
    {
        $root = realpath(self::ENGINEERING_ROOT);
        $realPath = realpath($path);
        if ($root === false || $realPath === false) {
            return false;
        }

        $root = rtrim(str_replace('\\', '/', $root), '/');
        $realPath = rtrim(str_replace('\\', '/', $realPath), '/');

        return $realPath === $root || str_starts_with($realPath, $root . '/');
    }

    public static function displayWorkspaceName(string $workspaceKey): string
    {
        return trim($workspaceKey, "/ \t\n\r\0\x0B");
    }

    private static function workspaceDir(string $workspaceKey): ?string
    {
        $trimmed = trim($workspaceKey, "/ \t\n\r\0\x0B");
        if ($trimmed === '' || str_contains($trimmed, '..') || str_starts_with($trimmed, '/')) {
            return null;
        }

        return self::ENGINEERING_ROOT . '/' . $trimmed;
    }

    private static function isDiscoverableWorkspaceKey(string $workspaceKey): bool
    {
        $trimmed = trim($workspaceKey, "/ \t\n\r\0\x0B");
        if ($trimmed === '' || self::isReservedTemplateWorkspaceKey($trimmed)) {
            return false;
        }
        if (!preg_match('#^[A-Za-z0-9_./-]+$#', $trimmed) || str_contains($trimmed, '..')) {
            return false;
        }

        return true;
    }

    private static function directoryHasWorkspaceDocument(string $dir): bool
    {
        foreach (self::DOCUMENTS as $document) {
            if (is_file($dir . '/' . $document['filename'])) {
                return true;
            }
        }

        return false;
    }

    private static function relativePath(string $absolutePath): string
    {
        $root = rtrim(APP_ROOT, '/');
        if (strncmp($absolutePath, $root, strlen($root)) === 0) {
            return ltrim(substr($absolutePath, strlen($root)), '/');
        }

        return $absolutePath;
    }
}

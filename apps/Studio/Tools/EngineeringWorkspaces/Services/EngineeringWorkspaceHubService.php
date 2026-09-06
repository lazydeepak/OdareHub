<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\EngineeringWorkspaces\Services;

use Platform\Security\EngineeringWorkspaceContentContract;
use Platform\Security\MarkdownRenderer;

final class EngineeringWorkspaceHubService
{
    private const VIEWER_ROUTE = '/apps/studio/engineering-workspaces';

    /**
     * @return array<string,mixed>
     */
    public static function buildModel(string $tab = 'workspaces'): array
    {
        $activeTab = in_array($tab, ['templates', 'provision', 'deploy'], true) ? $tab : 'workspaces';

        return [
            'active_tab' => $activeTab,
            'workspaces' => self::workspaceRows(),
            'templates' => self::templateRows(),
            'document_labels' => self::documentLabels(),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function workspaceRows(): array
    {
        $rows = [];
        foreach (EngineeringWorkspaceContentContract::supportedWorkspaceKeys() as $workspaceKey) {
            $rows[] = self::safeWorkspaceRow($workspaceKey);
        }

        return $rows;
    }

    /**
     * @return array<string,mixed>
     */
    private static function safeWorkspaceRow(string $workspaceKey): array
    {
        try {
            return self::workspaceRow($workspaceKey);
        } catch (\Throwable $e) {
            error_log(
                'EngineeringWorkspaceHub workspace failed [' . $workspaceKey . ']: '
                . get_class($e) . ': ' . $e->getMessage()
                . ' in ' . $e->getFile() . ':' . (string)$e->getLine()
                . "\n" . $e->getTraceAsString()
            );

            return [
                'workspace_key' => $workspaceKey,
                'workspace_label' => EngineeringWorkspaceContentContract::displayWorkspaceName($workspaceKey),
                'status' => 'status_unavailable',
                'status_label' => 'Status unavailable',
                'documents' => [],
                'message' => 'Workspace status could not be resolved.',
            ];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private static function workspaceRow(string $workspaceKey): array
    {
        $documents = [];
        $missing = 0;
        $invalid = 0;

        foreach (EngineeringWorkspaceContentContract::allowedDocumentKeys() as $documentKey) {
            $doc = self::documentState($workspaceKey, $documentKey);
            $documents[] = $doc;

            if (($doc['status'] ?? '') === 'missing') {
                $missing++;
            } elseif (($doc['status'] ?? '') === 'needs_repair') {
                $invalid++;
            } elseif (($doc['status'] ?? '') === 'status_unavailable') {
                throw new \RuntimeException('Document status unavailable for ' . $workspaceKey . '/' . $documentKey);
            }
        }

        if ($invalid > 0) {
            $status = 'needs_repair';
            $label = 'Needs repair';
        } elseif ($missing > 0) {
            $status = 'incomplete';
            $label = 'Incomplete';
        } else {
            $status = 'ready';
            $label = 'Ready';
        }

        return [
            'workspace_key' => $workspaceKey,
            'workspace_label' => EngineeringWorkspaceContentContract::displayWorkspaceName($workspaceKey),
            'status' => $status,
            'status_label' => $label,
            'documents' => $documents,
            'message' => '',
        ];
    }

    /**
     * @return array<string,string>
     */
    private static function documentLabels(): array
    {
        $labels = [];
        foreach (EngineeringWorkspaceContentContract::allowedDocumentKeys() as $documentKey) {
            $labels[$documentKey] = EngineeringWorkspaceContentContract::documentLabel($documentKey) ?? ucfirst($documentKey);
        }

        return $labels;
    }

    /**
     * @return array<string,mixed>
     */
    private static function documentState(string $workspaceKey, string $documentKey): array
    {
        try {
            $path = EngineeringWorkspaceContentContract::documentPath($workspaceKey, $documentKey);
            $label = EngineeringWorkspaceContentContract::documentLabel($documentKey) ?? ucfirst($documentKey);
            if ($path === null || !is_file($path) || !is_readable($path)) {
                return [
                    'document_key' => $documentKey,
                    'label' => $label,
                    'status' => 'missing',
                    'status_label' => 'Missing',
                    'url' => '',
                ];
            }

            $content = file_get_contents($path);
            if (!is_string($content)) {
                return [
                    'document_key' => $documentKey,
                    'label' => $label,
                    'status' => 'status_unavailable',
                    'status_label' => 'Status unavailable',
                    'url' => '',
                ];
            }

            $validation = EngineeringWorkspaceContentContract::validateDocumentContent($documentKey, $content);
            if (empty($validation['ok'])) {
                return [
                    'document_key' => $documentKey,
                    'label' => $label,
                    'status' => 'needs_repair',
                    'status_label' => 'Needs repair',
                    'url' => '',
                ];
            }

            return [
                'document_key' => $documentKey,
                'label' => $label,
                'status' => 'valid',
                'status_label' => 'Valid',
                'url' => self::viewerUrl($workspaceKey, $documentKey),
            ];
        } catch (\Throwable $e) {
            error_log(
                'EngineeringWorkspaceHub document failed [' . $workspaceKey . '/' . $documentKey . ']: '
                . get_class($e) . ': ' . $e->getMessage()
                . ' in ' . $e->getFile() . ':' . (string)$e->getLine()
                . "\n" . $e->getTraceAsString()
            );

            return [
                'document_key' => $documentKey,
                'label' => EngineeringWorkspaceContentContract::documentLabel($documentKey) ?? ucfirst($documentKey),
                'status' => 'status_unavailable',
                'status_label' => 'Status unavailable',
                'url' => '',
            ];
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function templateRows(): array
    {
        $rows = [];
        foreach (EngineeringWorkspaceContentContract::allowedDocumentKeys() as $documentKey) {
            $rows[] = self::safeTemplateRow($documentKey);
        }

        return $rows;
    }

    /**
     * @return array<string,mixed>
     */
    private static function safeTemplateRow(string $documentKey): array
    {
        try {
            return self::templateRow($documentKey);
        } catch (\Throwable $e) {
            error_log(
                'EngineeringWorkspaceHub template failed [' . $documentKey . ']: '
                . get_class($e) . ': ' . $e->getMessage()
                . ' in ' . $e->getFile() . ':' . (string)$e->getLine()
                . "\n" . $e->getTraceAsString()
            );

            return [
                'document_key' => $documentKey,
                'document_label' => EngineeringWorkspaceContentContract::documentLabel($documentKey) ?? ucfirst($documentKey),
                'filename' => EngineeringWorkspaceContentContract::canonicalFilename($documentKey) ?? $documentKey . '.md',
                'source_status' => 'unavailable',
                'source_status_label' => 'Unavailable',
                'heading_compatibility' => 'Unavailable',
                'rendered_preview' => '',
                'message' => 'Template unavailable.',
            ];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private static function templateRow(string $documentKey): array
    {
        $filename = EngineeringWorkspaceContentContract::canonicalFilename($documentKey) ?? $documentKey . '.md';
        $path = EngineeringWorkspaceContentContract::templatePath($documentKey);
        if ($path === null || !is_file($path) || !is_readable($path)) {
            return [
                'document_key' => $documentKey,
                'document_label' => EngineeringWorkspaceContentContract::documentLabel($documentKey) ?? ucfirst($documentKey),
                'filename' => $filename,
                'source_status' => 'unavailable',
                'source_status_label' => 'Unavailable',
                'heading_compatibility' => 'Unavailable',
                'rendered_preview' => '',
                'message' => 'Template unavailable.',
            ];
        }

        $content = file_get_contents($path);
        if (!is_string($content)) {
            throw new \RuntimeException('Template content could not be read');
        }

        $validation = EngineeringWorkspaceContentContract::validateDocumentContent($documentKey, $content);
        $displayContent = EngineeringWorkspaceContentContract::markdownForDisplay($documentKey, self::normalizeTemplatePlaceholders($content));

        return [
            'document_key' => $documentKey,
            'document_label' => EngineeringWorkspaceContentContract::documentLabel($documentKey) ?? ucfirst($documentKey),
            'filename' => $filename,
            'source_status' => !empty($validation['ok']) ? 'valid' : 'unavailable',
            'source_status_label' => !empty($validation['ok']) ? 'Valid' : 'Unavailable',
            'heading_compatibility' => !empty($validation['ok']) ? 'Compatible' : 'Needs contract review',
            'rendered_preview' => MarkdownRenderer::render($displayContent),
            'message' => !empty($validation['ok']) ? '' : 'Template unavailable.',
        ];
    }

    private static function viewerUrl(string $workspaceKey, string $documentKey): string
    {
        return self::VIEWER_ROUTE
            . '?workspace_key=' . rawurlencode($workspaceKey)
            . '&document=' . rawurlencode($documentKey);
    }

    private static function normalizeTemplatePlaceholders(string $content): string
    {
        $content = str_replace('<Workspace Name>', 'Workspace Name', $content);
        $content = str_replace(['{{workspace_name}}', '{{workspace_key}}'], ['Workspace Name', 'workspace-key'], $content);
        return $content;
    }
}

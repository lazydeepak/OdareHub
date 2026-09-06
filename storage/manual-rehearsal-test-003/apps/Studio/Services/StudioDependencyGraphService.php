<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

final class StudioDependencyGraphService
{
    /**
     * @param array<string,mixed> $bundle
     * @param array<string,mixed> $dataContract
     * @return array<string,mixed>
     */
    public static function build(array $bundle, array $dataContract): array
    {
        $viewDefinition = is_array($bundle['view_definition'] ?? null) ? $bundle['view_definition'] : [];
        $moduleManifest = is_array($bundle['module_manifest'] ?? null) ? $bundle['module_manifest'] : [];

        $module = is_array($moduleManifest['module'] ?? null) ? $moduleManifest['module'] : [];
        $view = is_array($viewDefinition['view'] ?? null) ? $viewDefinition['view'] : [];

        $moduleType = strtolower(trim((string)($module['module_type'] ?? 'crud')));
        $moduleKey = trim((string)($module['module_key'] ?? 'module'));
        $viewKey = trim((string)($view['view_key'] ?? 'index'));
        $viewKind = strtolower(trim((string)($view['view_kind'] ?? 'table')));

        $fieldNodes = [];
        $fields = is_array($dataContract['fields'] ?? null)
            ? array_values(array_filter($dataContract['fields'], 'is_array'))
            : [];
        foreach ($fields as $field) {
            $fieldKey = trim((string)($field['key'] ?? ''));
            if ($fieldKey === '') {
                continue;
            }
            $fieldNodes[] = [
                'id' => 'field:' . $fieldKey,
                'type' => 'field',
                'label' => $fieldKey,
            ];
        }

        $viewNode = [
            'id' => 'view:' . $viewKey,
            'type' => 'view',
            'label' => $viewKey,
            'meta' => [
                'kind' => $viewKind,
                'module' => $moduleKey,
            ],
        ];

        $dashboardNodes = [];
        if ($moduleType === 'dashboard' || $viewKind === 'dashboard') {
            $dashboardNodes[] = [
                'id' => 'dashboard:' . $moduleKey,
                'type' => 'dashboard',
                'label' => $moduleKey,
            ];
        }

        $workflowNodes = [];
        if ($moduleType === 'queue_workflow') {
            $workflowNodes[] = [
                'id' => 'workflow:' . $moduleKey,
                'type' => 'workflow',
                'label' => $moduleKey,
            ];
        }

        $edges = [];
        foreach ($fieldNodes as $fieldNode) {
            $edges[] = [
                'from' => (string)$fieldNode['id'],
                'to' => (string)$viewNode['id'],
                'relation' => 'depends_on',
            ];
        }

        foreach ($dashboardNodes as $dashboardNode) {
            $edges[] = [
                'from' => (string)$viewNode['id'],
                'to' => (string)$dashboardNode['id'],
                'relation' => 'affects',
            ];
        }

        foreach ($workflowNodes as $workflowNode) {
            if ($dashboardNodes !== []) {
                foreach ($dashboardNodes as $dashboardNode) {
                    $edges[] = [
                        'from' => (string)$dashboardNode['id'],
                        'to' => (string)$workflowNode['id'],
                        'relation' => 'affects',
                    ];
                }
            } else {
                $edges[] = [
                    'from' => (string)$viewNode['id'],
                    'to' => (string)$workflowNode['id'],
                    'relation' => 'affects',
                ];
            }
        }

        $nodes = array_merge($fieldNodes, [$viewNode], $dashboardNodes, $workflowNodes);

        return [
            'nodes' => $nodes,
            'edges' => $edges,
            'summary' => [
                'fields' => count($fieldNodes),
                'views' => 1,
                'dashboards' => count($dashboardNodes),
                'workflows' => count($workflowNodes),
                'edges' => count($edges),
            ],
            'depends_on_edges' => count(array_filter($edges, static fn(array $edge): bool => (string)($edge['relation'] ?? '') === 'depends_on')),
            'affects_edges' => count(array_filter($edges, static fn(array $edge): bool => (string)($edge['relation'] ?? '') === 'affects')),
            'issues' => self::computeIssues($fieldNodes, $dashboardNodes, $workflowNodes, $edges),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $fieldNodes
     * @param array<int,array<string,mixed>> $dashboardNodes
     * @param array<int,array<string,mixed>> $workflowNodes
     * @param array<int,array<string,string>> $edges
     * @return array<int,string>
     */
    private static function computeIssues(array $fieldNodes, array $dashboardNodes, array $workflowNodes, array $edges): array
    {
        $issues = [];
        if ($fieldNodes === []) {
            $issues[] = 'missing_field_nodes';
        }
        if ($edges === []) {
            $issues[] = 'missing_edges';
        }
        if ($workflowNodes !== [] && $dashboardNodes === []) {
            $issues[] = 'workflow_without_dashboard';
        }
        return $issues;
    }
}

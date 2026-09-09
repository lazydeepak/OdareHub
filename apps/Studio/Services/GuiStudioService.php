<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

final class GuiStudioService
{
    public const APPLY_MODE = 'simulation'; // disabled | simulation | real

    private const TEMPLATE_DIR = '/apps/Studio/Templates/';
    private const GENERATOR_ID = 'erp_app_studio.compile_plan.v1';
    private const FIRST_APPLY_GENERATOR_ID = 'erp_app_studio.first_apply.v1';
    private const TEMPLATE_VERSION = 'studio.template.v1';
    private const GENERATED_ROOT = 'apps/Generated';
    private const GENERATED_TMP_ROOT = 'apps/Generated/tmp';
    private const APPSTUDIO_STORAGE_ROOT = 'storage/appstudio';
    private const APPSTUDIO_SNAPSHOT_SCHEMA = 'studio.generated-snapshot.v1';
    private const APPSTUDIO_APPLY_LOG_SCHEMA = 'studio.apply-log-entry.v1';
    private const APPSTUDIO_APPS_REGISTRY_SCHEMA = 'studio.apps-registry.v1';
    /** @var array<int,string> */
    private const GENERATED_APP_STATUSES = ['installed', 'enabled', 'disabled', 'removed'];
    /** @var array<int,string> */
    private const FIRST_APPLY_TEMPLATES = [
        'business_app',
        'system_app',
        'crud_module',
        'dashboard_module',
        'queue_workflow_module',
        'table_view',
        'form_view',
        'detail_view',
        'navigation_entry',
    ];
    /** @var array<int,string> */
    private const ARTIFACT_TYPES = [
        'app',
        'module',
        'route',
        'controller',
        'service',
        'view',
        'navigation',
        'permission',
        'translation',
        'dashboard_surface',
        'workflow_surface',
        'migration_file',
        'package_manifest',
    ];

    /** @return array<string,string> */
    public static function loadTemplates(): array
    {
        return [
            'app_manifest' => self::loadStudioTemplateFile('app_manifest.placeholder.json'),
            'module_manifest' => self::loadStudioTemplateFile('module_manifest.placeholder.json'),
            'view_definition' => self::loadStudioTemplateFile('view_manifest.placeholder.json'),
            'navigation_definition' => self::loadStudioTemplateFile('navigation_manifest.placeholder.json'),
            'studio_project' => self::loadStudioTemplateFile('studio_project.placeholder.json'),
            'artifact_manifest' => self::loadStudioTemplateFile('artifact_manifest.placeholder.json'),
        ];
    }

    /** @param array<string,mixed> $post @return array<string,string> */
    public static function mapStructuredEditorPayload(array $post): array
    {
        $payload = [
            'app_manifest' => trim((string)($post['app_manifest'] ?? '')),
            'module_manifest' => trim((string)($post['module_manifest'] ?? '')),
            'view_definition' => trim((string)($post['view_definition'] ?? '')),
            'navigation_definition' => trim((string)($post['navigation_definition'] ?? '')),
            'studio_mode' => (string)($post['studio_mode'] ?? '') === 'edit_existing' ? 'edit_existing' : 'create_new',
        ];

        if ((string)($post['se_editor_enabled'] ?? '0') !== '1') {
            return $payload;
        }

        $appManifest = is_array(json_decode($payload['app_manifest'], true)) ? (array)json_decode($payload['app_manifest'], true) : [];
        $moduleManifest = is_array(json_decode($payload['module_manifest'], true)) ? (array)json_decode($payload['module_manifest'], true) : [];
        $viewDefinition = is_array(json_decode($payload['view_definition'], true)) ? (array)json_decode($payload['view_definition'], true) : [];
        $navigationDefinition = is_array(json_decode($payload['navigation_definition'], true)) ? (array)json_decode($payload['navigation_definition'], true) : [];

        $existingApp = is_array($appManifest['app'] ?? null) ? $appManifest['app'] : [];
        $existingModule = is_array($moduleManifest['module'] ?? null) ? $moduleManifest['module'] : [];
        $existingView = is_array($viewDefinition['view'] ?? null) ? $viewDefinition['view'] : [];
        $existingNav = is_array($navigationDefinition['navigation'] ?? null) ? $navigationDefinition['navigation'] : [];

        $appKey = self::generatedKey((string)($existingApp['app_key'] ?? ''));
        $moduleKey = self::generatedKey((string)($post['se_module_key'] ?? $existingModule['module_key'] ?? ''));
        if ($moduleKey === '') {
            return $payload;
        }
        if ($appKey === '') {
            $appKey = self::generatedKey((string)($existingModule['app_key'] ?? $existingView['app_key'] ?? 'generated_app'));
            if ($appKey === '') {
                $appKey = 'generated_app';
            }
        }

        $moduleTypeRaw = strtolower(trim((string)($post['se_module_type'] ?? $existingModule['module_type'] ?? 'crud')));
        $moduleType = in_array($moduleTypeRaw, ['crud', 'dashboard', 'queue_workflow'], true) ? $moduleTypeRaw : 'crud';

        $appSlug = str_replace('_', '-', $appKey);
        $moduleSlug = str_replace('_', '-', $moduleKey);
        $defaultRoute = '/apps/' . $appSlug . '/' . $moduleSlug;
        $routePath = trim((string)($post['se_route_path'] ?? $existingView['route_path'] ?? $existingModule['route_base'] ?? $defaultRoute));
        if (!preg_match('#^/[a-z0-9/_\-]+$#i', $routePath)) {
            $routePath = $defaultRoute;
        }

        $fieldsState = trim((string)($post['se_fields_state'] ?? '[]'));
        $fieldsRaw = json_decode($fieldsState, true);
        $fields = self::normalizeGeneratedFields(is_array($fieldsRaw) ? $fieldsRaw : []);

        $viewKindRaw = strtolower(trim((string)($post['se_view_kind'] ?? $existingView['view_kind'] ?? 'table')));
        $viewKind = in_array($viewKindRaw, ['table', 'form', 'kpi', 'chart', 'list', 'dashboard', 'queue', 'report', 'detail'], true) ? $viewKindRaw : 'table';
        $layoutStateRaw = trim((string)($post['se_layout_state'] ?? ''));
        $layoutState = json_decode($layoutStateRaw, true);
        if (is_array($layoutState) && !array_key_exists('items', $layoutState) && array_key_exists(0, $layoutState)) {
            $layoutState = ['items' => $layoutState];
        }
        $layoutRelationsStateRaw = trim((string)($post['se_layout_relations_state'] ?? ''));
        $layoutRelationsState = json_decode($layoutRelationsStateRaw, true);
        if (is_array($layoutState) && is_array($layoutRelationsState)) {
            $layoutState['relations'] = $layoutRelationsState;
        }
        $existingLayout = is_array($viewDefinition['layout'] ?? null) ? $viewDefinition['layout'] : [];
        $normalizedLayout = self::normalizeVisualLayout(is_array($layoutState) ? $layoutState : [], $viewKind);
        if ($normalizedLayout['items'] === []) {
            $normalizedLayout = self::normalizeVisualLayout($existingLayout, $viewKind);
        }
        $columnsState = trim((string)($post['se_view_columns_state'] ?? '[]'));
        $columnsRaw = json_decode($columnsState, true);
        $allowedFieldKeys = array_map(static fn(array $field): string => (string)($field['key'] ?? ''), $fields);
        $selectedColumns = [];
        if (is_array($columnsRaw)) {
            foreach ($columnsRaw as $col) {
                $colKey = self::generatedKey((string)$col);
                if ($colKey !== '' && in_array($colKey, $allowedFieldKeys, true)) {
                    $selectedColumns[] = $colKey;
                }
            }
        }
        $selectedColumns = array_values(array_unique($selectedColumns));
        if ($selectedColumns === [] && $allowedFieldKeys !== []) {
            $selectedColumns = $allowedFieldKeys;
        }
        $layoutCompact = !empty($post['se_layout_compact']);

        $navSection = trim((string)($post['se_nav_section'] ?? $existingNav['section'] ?? self::displayName($appKey)));
        $navGroup = trim((string)($post['se_nav_group'] ?? $existingNav['group'] ?? 'Apps'));
        $navLabel = trim((string)($post['se_nav_label'] ?? $existingNav['label'] ?? self::displayName($moduleKey)));
        if ($navLabel === '') {
            $navLabel = self::displayName($moduleKey);
        }
        $navKey = self::generatedKey($appKey . '_' . $moduleKey);
        if ($navKey === '') {
            $navKey = $moduleKey;
        }

        $moduleManifest['schema_version'] = 'studio.module-manifest.v1';
        $moduleManifest['status'] = 'draft';
        $moduleManifest['module'] = [
            'app_key' => $appKey,
            'module_key' => $moduleKey,
            'display_name' => trim((string)($post['se_module_display_name'] ?? $existingModule['display_name'] ?? self::displayName($moduleKey))),
            'title_key' => (string)($existingModule['title_key'] ?? ('app.' . $appKey . '.' . $moduleKey . '.title')),
            'display_name_key' => (string)($existingModule['display_name_key'] ?? ('app.' . $appKey . '.' . $moduleKey . '.display_name')),
            'description' => trim((string)($post['se_module_description'] ?? $existingModule['description'] ?? ('studio.' . $moduleKey . '.description'))),
            'module_type' => $moduleType,
            'target_maturity_level' => (string)($existingModule['target_maturity_level'] ?? 'L1'),
            'declared_capabilities' => is_array($existingModule['declared_capabilities'] ?? null) ? $existingModule['declared_capabilities'] : [],
            'route_base' => $routePath,
        ];
        $moduleManifest['fields'] = $fields;

        $viewKey = (string)($existingView['view_key'] ?? 'index');
        $viewDefinition['schema_version'] = 'studio.view-manifest.v1';
        $viewDefinition['status'] = 'draft';
        $viewDefinition['view'] = [
            'app_key' => $appKey,
            'module_key' => $moduleKey,
            'view_key' => $viewKey,
            'view_kind' => $viewKind,
            'route_path' => $routePath,
            'surface' => (string)($existingView['surface'] ?? 'admin'),
            'wrapper' => (string)($existingView['wrapper'] ?? 'admin'),
            'required_role' => (string)($existingView['required_role'] ?? 'platform_admin'),
            'title_key' => (string)($existingView['title_key'] ?? ('app.' . $appKey . '.' . $moduleKey . '.' . $viewKey . '.' . $viewKind . '.title')),
            'display_name_key' => (string)($existingView['display_name_key'] ?? ('app.' . $appKey . '.' . $moduleKey . '.' . $viewKey . '.' . $viewKind . '.display_name')),
            'description_key' => (string)($existingView['description_key'] ?? ('app.' . $appKey . '.' . $moduleKey . '.' . $viewKey . '.' . $viewKind . '.description')),
            'fields' => $selectedColumns,
        ];
        $viewDefinition['layout'] = [
            'kind' => $viewKind,
            'type' => 'grid',
            'columns' => 12,
            'rows' => 'auto',
            'items' => $normalizedLayout['items'],
            'relations' => $normalizedLayout['relations'],
            'preview_mode' => !empty($post['se_preview_mode']),
            'uses_shared_tokens' => true,
            'local_style_system' => false,
            'compact' => $layoutCompact,
        ];
        $componentSchema = self::visualComponentSchema();
        $viewDefinition['component_registry'] = [
            'version' => 'visual-builder.v1',
            'components' => array_map(
                static fn(string $key, array $schema): array => [
                    'key' => $key,
                    'required_props' => array_values(array_map('strval', (array)($schema['required_props'] ?? []))),
                    'default_config' => is_array($schema['default_config'] ?? null) ? $schema['default_config'] : [],
                ],
                array_keys($componentSchema),
                array_values($componentSchema)
            ),
        ];
        $viewDefinition['component_bindings'] = array_map(
            static fn(array $item): array => [
                'item_id' => (string)($item['id'] ?? ''),
                'component' => (string)($item['component'] ?? ''),
                'data_binding' => (string)($item['data_binding'] ?? ''),
            ],
            $normalizedLayout['items']
        );
        $viewDefinition['data_contract'] = is_array($viewDefinition['data_contract'] ?? null) ? $viewDefinition['data_contract'] : [];
        $viewDefinition['data_contract']['required_fields'] = $selectedColumns;
        $viewDefinition['security'] = is_array($viewDefinition['security'] ?? null) ? $viewDefinition['security'] : [];
        if (!array_key_exists('csrf_for_mutations', $viewDefinition['security'])) {
            $viewDefinition['security']['csrf_for_mutations'] = true;
        }
        $viewDefinition['fields'] = $fields;

        $navigationDefinition['schema_version'] = 'studio.navigation-manifest.v1';
        $navigationDefinition['status'] = 'draft';
        $navigationDefinition['navigation'] = [
            'scope' => (string)($existingNav['scope'] ?? 'admin'),
            'owner_app' => $appKey,
            'key' => $navKey,
            'label_key' => (string)($existingNav['label_key'] ?? ('app.' . $appKey . '.' . $moduleKey . '.nav')),
            'label' => $navLabel,
            'url' => $routePath,
            'section' => $navSection,
            'group' => $navGroup,
            'order' => (int)($existingNav['order'] ?? 10),
            'priority' => (int)($existingNav['priority'] ?? 100),
            'visible_if' => (string)($existingNav['visible_if'] ?? 'role_platform_admin_or_sysadmin'),
        ];
        $navigationDefinition['active_patterns'] = [
            'exact' => [$routePath],
            'prefix' => ['/apps/' . $appSlug],
        ];
        $navigationDefinition['governance'] = [
            'wrapper_confinement_valid' => true,
            'no_cross_layer_escape' => true,
            'publish_locked' => true,
        ];

        $payload['module_manifest'] = (string)json_encode($moduleManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $payload['view_definition'] = (string)json_encode($viewDefinition, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $payload['navigation_definition'] = (string)json_encode($navigationDefinition, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $payload;
    }

    /** @param array<string,string> $payload @return array<string,array<string,mixed>> */
    public static function decodeStructuredBundlePayload(array $payload): array
    {
        $bundle = [];
        foreach (['app_manifest', 'module_manifest', 'view_definition', 'navigation_definition'] as $key) {
            $decoded = json_decode((string)($payload[$key] ?? ''), true);
            $bundle[$key] = is_array($decoded) ? $decoded : [];
        }
        return $bundle;
    }

    /**
     * @param array<string,mixed> $post
     * @param array<string,array<string,mixed>> $fallbackCurrentBundle
     * @return array<string,array<string,mixed>>
     */
    public static function resolveStructuredPreviousBundle(array $post, array $fallbackCurrentBundle): array
    {
        $studioMode = (string)($post['studio_mode'] ?? '') === 'edit_existing' ? 'edit_existing' : 'create_new';
        if ($studioMode !== 'edit_existing') {
            return [
                'app_manifest' => [],
                'module_manifest' => [],
                'view_definition' => [],
                'navigation_definition' => [],
            ];
        }

        $raw = trim((string)($post['se_previous_bundle'] ?? ''));
        if ($raw === '') {
            return $fallbackCurrentBundle;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $fallbackCurrentBundle;
        }

        $bundle = [];
        foreach (['app_manifest', 'module_manifest', 'view_definition', 'navigation_definition'] as $key) {
            $value = $decoded[$key] ?? null;
            $bundle[$key] = is_array($value) ? $value : [];
        }

        return $bundle;
    }

    /**
     * @param array<string,mixed> $post
     * @return array{valid:bool,mode:string,errors:array<int,string>}
     */
    public static function validateStudioFlowMode(array $post): array
    {
        $mode = (string)($post['studio_mode'] ?? '') === 'edit_existing' ? 'edit_existing' : 'create_new';
        $errors = [];
        if ($mode === 'edit_existing') {
            $raw = trim((string)($post['se_previous_bundle'] ?? ''));
            $decoded = $raw !== '' ? json_decode($raw, true) : null;
            $hasBaseline = is_array($decoded) && $decoded !== [];
            if ($hasBaseline) {
                $hasBaseline = false;
                foreach (['app_manifest', 'module_manifest', 'view_definition', 'navigation_definition'] as $key) {
                    if (is_array($decoded[$key] ?? null) && $decoded[$key] !== []) {
                        $hasBaseline = true;
                        break;
                    }
                }
            }
            if (!$hasBaseline) {
                $errors[] = 'ops.gui_studio.error.upgrade_baseline_required';
            }
        }

        return [
            'valid' => $errors === [],
            'mode' => $mode,
            'errors' => $errors,
        ];
    }

    /**
     * @param array<string,array<string,mixed>> $previousBundle
     * @param array<string,array<string,mixed>> $currentBundle
     * @return array{changes:array<int,array{type:string,path:string,before:mixed,after:mixed,impact:string}>,summary:array{total:int,high:int,medium:int,low:int}}
     */
    public static function computeStructuredDiff(array $previousBundle, array $currentBundle): array
    {
        $changes = [];

        $previousFields = self::structuredFieldMap($previousBundle);
        $currentFields = self::structuredFieldMap($currentBundle);
        $fieldKeys = array_values(array_unique(array_merge(array_keys($previousFields), array_keys($currentFields))));
        sort($fieldKeys);

        foreach ($fieldKeys as $fieldKey) {
            $beforeField = $previousFields[$fieldKey] ?? null;
            $afterField = $currentFields[$fieldKey] ?? null;
            $path = 'module.fields.' . $fieldKey;

            if ($beforeField === null && $afterField !== null) {
                $changes[] = [
                    'type' => 'field_added',
                    'path' => $path,
                    'before' => null,
                    'after' => $afterField,
                    'impact' => 'low',
                ];
                continue;
            }

            if ($beforeField !== null && $afterField === null) {
                $changes[] = [
                    'type' => 'field_removed',
                    'path' => $path,
                    'before' => $beforeField,
                    'after' => null,
                    'impact' => 'high',
                ];
                continue;
            }

            if ($beforeField === null || $afterField === null) {
                continue;
            }

            $beforeType = strtolower((string)($beforeField['type'] ?? 'string'));
            $afterType = strtolower((string)($afterField['type'] ?? 'string'));
            $beforeRequired = !empty($beforeField['required']);
            $afterRequired = !empty($afterField['required']);
            $impact = null;
            if ($beforeType !== $afterType) {
                $impact = 'high';
            } elseif ($beforeRequired !== $afterRequired) {
                $impact = 'medium';
            } elseif (self::stableJson($beforeField) !== self::stableJson($afterField)) {
                $impact = 'low';
            }

            if ($impact !== null) {
                $changes[] = [
                    'type' => 'field_modified',
                    'path' => $path,
                    'before' => $beforeField,
                    'after' => $afterField,
                    'impact' => $impact,
                ];
            }
        }

        $beforeView = self::structuredViewMap($previousBundle);
        $afterView = self::structuredViewMap($currentBundle);
        if ((string)($beforeView['view_kind'] ?? '') !== (string)($afterView['view_kind'] ?? '')) {
            $changes[] = [
                'type' => 'view_changed',
                'path' => 'view.view_kind',
                'before' => (string)($beforeView['view_kind'] ?? ''),
                'after' => (string)($afterView['view_kind'] ?? ''),
                'impact' => 'medium',
            ];
        }
        if ((string)($beforeView['route_path'] ?? '') !== (string)($afterView['route_path'] ?? '')) {
            $changes[] = [
                'type' => 'view_changed',
                'path' => 'view.route_path',
                'before' => (string)($beforeView['route_path'] ?? ''),
                'after' => (string)($afterView['route_path'] ?? ''),
                'impact' => 'high',
            ];
        }

        $beforeNav = self::structuredNavigationMap($previousBundle);
        $afterNav = self::structuredNavigationMap($currentBundle);
        if ((string)($beforeNav['url'] ?? '') !== (string)($afterNav['url'] ?? '')) {
            $changes[] = [
                'type' => 'nav_changed',
                'path' => 'navigation.url',
                'before' => (string)($beforeNav['url'] ?? ''),
                'after' => (string)($afterNav['url'] ?? ''),
                'impact' => 'high',
            ];
        }
        if ((string)($beforeNav['label'] ?? '') !== (string)($afterNav['label'] ?? '')) {
            $changes[] = [
                'type' => 'nav_changed',
                'path' => 'navigation.label',
                'before' => (string)($beforeNav['label'] ?? ''),
                'after' => (string)($afterNav['label'] ?? ''),
                'impact' => 'low',
            ];
        }

        $summary = ['total' => count($changes), 'high' => 0, 'medium' => 0, 'low' => 0];
        foreach ($changes as $change) {
            $impact = strtolower((string)($change['impact'] ?? 'low'));
            if (!array_key_exists($impact, $summary)) {
                $impact = 'low';
            }
            $summary[$impact]++;
        }

        return [
            'changes' => $changes,
            'summary' => $summary,
        ];
    }

    /**
     * @param array{changes?:array<int,array{type?:string,path?:string,impact?:string}>} $changeIntelligence
     * @param array<string,mixed> $input
     * @return array{requires_escalation:bool,triggers:array<int,string>,reason:string,risk_acknowledged:bool,valid:bool,errors:array<int,string>}
     */
    public static function buildStructuredRiskEscalation(array $changeIntelligence, array $input = []): array
    {
        $changes = is_array($changeIntelligence['changes'] ?? null) ? $changeIntelligence['changes'] : [];
        $triggers = [];
        foreach ($changes as $change) {
            if (!is_array($change)) {
                continue;
            }
            $type = (string)($change['type'] ?? '');
            $path = (string)($change['path'] ?? '');
            if ($type === 'field_removed') {
                $triggers[] = 'field_removed:' . $path;
            }
            if ($type === 'view_changed' && $path === 'view.route_path') {
                $triggers[] = 'route_changed:' . $path;
            }
        }

        $requiresEscalation = $triggers !== [];
        $reason = trim((string)($input['reason'] ?? ''));
        $riskAcknowledged = !empty($input['risk_acknowledged']);
        $errors = [];
        if ($requiresEscalation && !$riskAcknowledged) {
            $errors[] = 'ops.gui_studio.approval.error.risk_ack_required';
        }
        if ($requiresEscalation && $reason === '') {
            $errors[] = 'ops.gui_studio.approval.error.reason_required';
        }

        return [
            'requires_escalation' => $requiresEscalation,
            'triggers' => array_values(array_unique($triggers)),
            'reason' => $reason,
            'risk_acknowledged' => $riskAcknowledged,
            'valid' => $errors === [],
            'errors' => array_values(array_unique($errors)),
        ];
    }

    /**
     * @param array{changes?:array<int,array{type?:string,path?:string,before?:mixed,after?:mixed,impact?:string}>} $diff
     * @return array<int,array{action:string,field:string,strategy:string,risk:string}>
     */
    public static function buildMigrationPlan(array $diff): array
    {
        $plan = [];
        $changes = is_array($diff['changes'] ?? null) ? $diff['changes'] : [];

        foreach ($changes as $change) {
            if (!is_array($change)) {
                continue;
            }
            $type = (string)($change['type'] ?? '');
            $path = (string)($change['path'] ?? '');

            if (strpos($path, 'module.fields.') !== 0) {
                continue;
            }

            $field = trim(substr($path, strlen('module.fields.')));
            if ($field === '') {
                continue;
            }

            if ($type === 'field_added') {
                $plan[] = [
                    'action' => 'add_column',
                    'field' => $field,
                    'strategy' => 'safe',
                    'risk' => 'low',
                ];
                continue;
            }

            if ($type === 'field_removed') {
                $plan[] = [
                    'action' => 'remove_column',
                    'field' => $field,
                    'strategy' => 'destructive',
                    'risk' => 'high',
                ];
                continue;
            }

            if ($type !== 'field_modified') {
                continue;
            }

            $before = is_array($change['before'] ?? null) ? $change['before'] : [];
            $after = is_array($change['after'] ?? null) ? $change['after'] : [];
            $beforeType = strtolower((string)($before['type'] ?? 'string'));
            $afterType = strtolower((string)($after['type'] ?? 'string'));
            $beforeRequired = !empty($before['required']);
            $afterRequired = !empty($after['required']);

            if ($beforeType !== $afterType) {
                $plan[] = [
                    'action' => 'modify_column',
                    'field' => $field,
                    'strategy' => 'requires_migration',
                    'risk' => 'high',
                ];
                continue;
            }

            if ($beforeRequired !== $afterRequired) {
                $plan[] = [
                    'action' => 'modify_column',
                    'field' => $field,
                    'strategy' => 'safe',
                    'risk' => 'medium',
                ];
            }
        }

        return array_values($plan);
    }

    /**
     * @param array<string,array<string,mixed>> $bundle
     * @return array<string,mixed>
     */
    public static function buildDependencyGraph(array $bundle): array
    {
        $fieldMap = self::structuredFieldMap($bundle);
        $viewDefinition = is_array($bundle['view_definition'] ?? null) ? $bundle['view_definition'] : [];
        $moduleManifest = is_array($bundle['module_manifest'] ?? null) ? $bundle['module_manifest'] : [];
        $navigationDefinition = is_array($bundle['navigation_definition'] ?? null) ? $bundle['navigation_definition'] : [];

        $view = is_array($viewDefinition['view'] ?? null) ? $viewDefinition['view'] : [];
        $module = is_array($moduleManifest['module'] ?? null) ? $moduleManifest['module'] : [];
        $navigation = is_array($navigationDefinition['navigation'] ?? null) ? $navigationDefinition['navigation'] : [];
        $dataContract = is_array($viewDefinition['data_contract'] ?? null) ? $viewDefinition['data_contract'] : [];

        $moduleKey = self::generatedKey((string)($module['module_key'] ?? ''));
        if ($moduleKey === '') {
            $moduleKey = 'generated_module';
        }
        $viewKey = self::generatedKey((string)($view['view_key'] ?? 'index'));
        if ($viewKey === '') {
            $viewKey = 'index';
        }
        $viewRef = 'view:' . $viewKey;

        $routePath = trim((string)($view['route_path'] ?? $module['route_base'] ?? ''));
        $routeRef = 'route:' . ($routePath !== '' ? $routePath : '/apps/' . str_replace('_', '-', $moduleKey));

        $navigationKey = self::generatedKey((string)($navigation['key'] ?? ($moduleKey . '_nav')));
        if ($navigationKey === '') {
            $navigationKey = 'navigation';
        }
        $navigationRef = 'navigation:' . $navigationKey;

        $adapterRef = 'adapter:' . self::structuredAdapterName($bundle);

        $viewFieldsRaw = is_array($view['fields'] ?? null) ? $view['fields'] : [];
        $viewFields = [];
        foreach ($viewFieldsRaw as $fieldKey) {
            $normalized = self::generatedKey((string)$fieldKey);
            if ($normalized !== '') {
                $viewFields[$normalized] = true;
            }
        }
        if ($viewFields === []) {
            foreach (array_keys($fieldMap) as $fieldKey) {
                $viewFields[$fieldKey] = true;
            }
        }

        $filterFields = [];
        $allowedFilters = is_array($dataContract['allowed_filters'] ?? null) ? $dataContract['allowed_filters'] : [];
        foreach ($allowedFilters as $filterKey) {
            $normalized = self::generatedKey((string)$filterKey);
            if ($normalized !== '') {
                $filterFields[$normalized] = true;
            }
        }
        if ($filterFields === [] && $fieldMap !== []) {
            foreach (array_keys($fieldMap) as $fieldKey) {
                $filterFields[$fieldKey] = true;
            }
        }

        $navLabel = strtolower(trim((string)($navigation['label'] ?? '')));
        $navUrl = strtolower(trim((string)($navigation['url'] ?? '')));
        $fields = [];
        $edges = [];

        foreach (array_keys($fieldMap) as $fieldKey) {
            $viewRefs = [];
            if (!empty($viewFields[$fieldKey])) {
                $viewRefs[] = $viewRef;
            }

            $filterRefs = [];
            if (!empty($filterFields[$fieldKey])) {
                $filterRefs[] = 'filter:search';
            }

            $navigationRefs = [];
            if (($navLabel !== '' && str_contains($navLabel, $fieldKey)) || ($navUrl !== '' && str_contains($navUrl, $fieldKey))) {
                $navigationRefs[] = $navigationRef;
            }

            $adapterRefs = [$adapterRef];

            $fields[$fieldKey] = [
                'views' => array_values(array_unique($viewRefs)),
                'filters' => array_values(array_unique($filterRefs)),
                'navigation' => array_values(array_unique($navigationRefs)),
                'adapters' => array_values(array_unique($adapterRefs)),
            ];

            foreach ($fields[$fieldKey]['views'] as $target) {
                $edges[] = ['from' => 'field:' . $fieldKey, 'to' => $target, 'relation' => 'used_in_view'];
            }
            foreach ($fields[$fieldKey]['filters'] as $target) {
                $edges[] = ['from' => 'field:' . $fieldKey, 'to' => $target, 'relation' => 'used_in_filter'];
            }
            foreach ($fields[$fieldKey]['navigation'] as $target) {
                $edges[] = ['from' => 'field:' . $fieldKey, 'to' => $target, 'relation' => 'used_in_navigation'];
            }
            foreach ($fields[$fieldKey]['adapters'] as $target) {
                $edges[] = ['from' => 'field:' . $fieldKey, 'to' => $target, 'relation' => 'used_in_adapter'];
            }
        }

        $moduleRef = 'module:' . $moduleKey;
        $edges[] = ['from' => $moduleRef, 'to' => $routeRef, 'relation' => 'module_route'];
        $edges[] = ['from' => $routeRef, 'to' => $navigationRef, 'relation' => 'route_navigation'];

        $studioDataContract = StudioDataContractService::extract($bundle, []);
        $studioDependencyGraph = StudioDependencyGraphService::build($bundle, $studioDataContract);
        $fieldDownstream = self::buildFieldDownstreamMap($studioDependencyGraph);

        return [
            'fields' => $fields,
            'module_route_navigation' => [
                'module' => $moduleRef,
                'route' => $routeRef,
                'navigation_refs' => [$navigationRef],
            ],
            'edges' => $edges,
            'field_downstream' => $fieldDownstream,
            'studio_graph_summary' => is_array($studioDependencyGraph['summary'] ?? null)
                ? $studioDependencyGraph['summary']
                : [],
        ];
    }

    /**
     * @param array<string,mixed> $studioDependencyGraph
     * @return array<string,array<int,string>>
     */
    private static function buildFieldDownstreamMap(array $studioDependencyGraph): array
    {
        $edges = is_array($studioDependencyGraph['edges'] ?? null)
            ? array_values(array_filter($studioDependencyGraph['edges'], 'is_array'))
            : [];
        if ($edges === []) {
            return [];
        }

        $affectsAdjacency = [];
        $fieldToView = [];

        foreach ($edges as $edge) {
            $from = trim((string)($edge['from'] ?? ''));
            $to = trim((string)($edge['to'] ?? ''));
            $relation = trim((string)($edge['relation'] ?? ''));
            if ($from === '' || $to === '' || $relation === '') {
                continue;
            }

            if ($relation === 'depends_on' && str_starts_with($from, 'field:')) {
                $fieldKey = self::generatedKey(substr($from, strlen('field:')));
                if ($fieldKey !== '') {
                    $fieldToView[$fieldKey][] = $to;
                }
            }

            if ($relation === 'affects') {
                $affectsAdjacency[$from][] = $to;
            }
        }

        $result = [];
        foreach ($fieldToView as $fieldKey => $starts) {
            $queue = array_values(array_unique(array_map('strval', $starts)));
            $visited = [];
            $downstream = [];

            while ($queue !== []) {
                $node = array_shift($queue);
                if (!is_string($node) || $node === '' || isset($visited[$node])) {
                    continue;
                }
                $visited[$node] = true;
                foreach ((array)($affectsAdjacency[$node] ?? []) as $nextNode) {
                    $next = trim((string)$nextNode);
                    if ($next === '' || isset($visited[$next])) {
                        continue;
                    }
                    $downstream[$next] = true;
                    $queue[] = $next;
                }
            }

            $result[$fieldKey] = array_values(array_keys($downstream));
        }

        return $result;
    }

    /**
     * @param array{changes?:array<int,array{type?:string,path?:string,before?:mixed,after?:mixed,impact?:string}>} $diff
     * @param array<string,mixed> $graph
     * @return array<int,array<string,mixed>>
     */
    public static function computeImpact(array $diff, array $graph): array
    {
        $impacts = [];
        $changes = is_array($diff['changes'] ?? null) ? $diff['changes'] : [];
        $fieldGraph = is_array($graph['fields'] ?? null) ? $graph['fields'] : [];
        $fieldDownstream = is_array($graph['field_downstream'] ?? null) ? $graph['field_downstream'] : [];
        $moduleRouteNav = is_array($graph['module_route_navigation'] ?? null) ? $graph['module_route_navigation'] : [];
        $routeRef = (string)($moduleRouteNav['route'] ?? 'route:unknown');
        $navigationRefs = is_array($moduleRouteNav['navigation_refs'] ?? null) ? $moduleRouteNav['navigation_refs'] : [];
        $defaultViews = [];
        $defaultFilters = [];
        $defaultAdapters = [];
        foreach ($fieldGraph as $usage) {
            if (!is_array($usage)) {
                continue;
            }
            foreach ((array)($usage['views'] ?? []) as $ref) {
                $refStr = trim((string)$ref);
                if ($refStr !== '') {
                    $defaultViews[] = $refStr;
                }
            }
            foreach ((array)($usage['filters'] ?? []) as $ref) {
                $refStr = trim((string)$ref);
                if ($refStr !== '') {
                    $defaultFilters[] = $refStr;
                }
            }
            foreach ((array)($usage['adapters'] ?? []) as $ref) {
                $refStr = trim((string)$ref);
                if ($refStr !== '') {
                    $defaultAdapters[] = $refStr;
                }
            }
        }
        $defaultViews = array_values(array_unique($defaultViews));
        $defaultFilters = array_values(array_unique($defaultFilters));
        $defaultAdapters = array_values(array_unique($defaultAdapters));

        foreach ($changes as $change) {
            if (!is_array($change)) {
                continue;
            }

            $type = (string)($change['type'] ?? '');
            $path = (string)($change['path'] ?? '');
            $severity = strtolower((string)($change['impact'] ?? 'low'));
            if (!in_array($severity, ['high', 'medium', 'low'], true)) {
                $severity = 'low';
            }

            $entry = [
                'change' => $type,
                'path' => $path,
                'affects' => [],
                'severity' => $severity,
                'warning' => '',
            ];

            if (strpos($path, 'module.fields.') === 0) {
                $field = trim(substr($path, strlen('module.fields.')));
                $entry['field'] = $field;
                $usage = is_array($fieldGraph[$field] ?? null) ? $fieldGraph[$field] : [];
                $downstreamRefs = is_array($fieldDownstream[$field] ?? null) ? $fieldDownstream[$field] : [];
                $affects = [];
                foreach (['views', 'filters', 'navigation', 'adapters'] as $key) {
                    $refs = is_array($usage[$key] ?? null) ? $usage[$key] : [];
                    foreach ($refs as $ref) {
                        $refStr = trim((string)$ref);
                        if ($refStr !== '') {
                            $affects[] = $refStr;
                        }
                    }
                }
                foreach ($downstreamRefs as $downstreamRef) {
                    $downstreamRef = trim((string)$downstreamRef);
                    if ($downstreamRef !== '') {
                        $affects[] = $downstreamRef;
                    }
                }
                $entry['affects'] = array_values(array_unique($affects));
                if ($type === 'field_removed') {
                    if ($entry['affects'] === []) {
                        $entry['affects'] = array_values(array_unique(array_merge(
                            $defaultViews !== [] ? $defaultViews : ['view:index'],
                            $defaultFilters !== [] ? $defaultFilters : ['filter:search'],
                            $defaultAdapters !== [] ? $defaultAdapters : ['adapter:ModuleAdapter']
                        )));
                    }
                    $entry['severity'] = 'high';
                    $entry['warning'] = 'field_dependency_break_risk';
                } elseif ($type === 'field_modified' && $severity === 'high') {
                    $entry['warning'] = 'field_contract_change_risk';
                }
                $impacts[] = $entry;
                continue;
            }

            if ($type === 'view_changed' && $path === 'view.route_path') {
                $entry['affects'] = array_values(array_unique(array_merge([$routeRef], array_map('strval', $navigationRefs))));
                $entry['severity'] = 'high';
                $entry['warning'] = 'route_navigation_impact';
                $impacts[] = $entry;
                continue;
            }

            if ($type === 'nav_changed') {
                $entry['affects'] = array_values(array_map('strval', $navigationRefs));
                $entry['severity'] = $path === 'navigation.url' ? 'high' : 'medium';
                $entry['warning'] = $path === 'navigation.url' ? 'navigation_link_impact' : 'navigation_display_impact';
                $impacts[] = $entry;
                continue;
            }
        }

        return $impacts;
    }

    /**
     * @param array<int,array<string,mixed>> $impactAnalysis
     * @param array<string,mixed> $input
     * @return array{requires_acknowledgment:bool,has_high_impact:bool,confirmed:bool,acknowledged:bool,valid:bool,errors:array<int,string>}
     */
    public static function validateImpactAcknowledgment(array $impactAnalysis, array $input = []): array
    {
        $hasHighImpact = false;
        foreach ($impactAnalysis as $impact) {
            if (!is_array($impact)) {
                continue;
            }
            if (strtolower((string)($impact['severity'] ?? 'low')) === 'high') {
                $hasHighImpact = true;
                break;
            }
        }

        $confirmed = !empty($input['impact_confirmation']);
        $acknowledged = !empty($input['impact_acknowledged']);
        $errors = [];
        if ($hasHighImpact && !$confirmed) {
            $errors[] = 'ops.gui_studio.impact.error.confirmation_required';
        }
        if ($hasHighImpact && !$acknowledged) {
            $errors[] = 'ops.gui_studio.impact.error.ack_required';
        }

        return [
            'requires_acknowledgment' => $hasHighImpact,
            'has_high_impact' => $hasHighImpact,
            'confirmed' => $confirmed,
            'acknowledged' => $acknowledged,
            'valid' => $errors === [],
            'errors' => array_values(array_unique($errors)),
        ];
    }

    /**
     * @param array<string,array<string,mixed>> $previousBundle
     * @param array<string,array<string,mixed>> $currentBundle
     * @param array{changes?:array<int,array{type?:string,path?:string,before?:mixed,after?:mixed,impact?:string}>} $diff
     * @return array<string,mixed>
     */
    public static function simulateFutureState(array $previousBundle, array $currentBundle, array $diff): array
    {
        $previousFields = self::structuredFieldMap($previousBundle);
        $currentFields = self::structuredFieldMap($currentBundle);
        $previousView = self::structuredViewMap($previousBundle);
        $currentView = self::structuredViewMap($currentBundle);

        $previousViewDefinition = is_array($previousBundle['view_definition'] ?? null) ? $previousBundle['view_definition'] : [];
        $currentViewDefinition = is_array($currentBundle['view_definition'] ?? null) ? $currentBundle['view_definition'] : [];
        $previousNavigationDefinition = is_array($previousBundle['navigation_definition'] ?? null) ? $previousBundle['navigation_definition'] : [];
        $currentNavigationDefinition = is_array($currentBundle['navigation_definition'] ?? null) ? $currentBundle['navigation_definition'] : [];

        $previousViewConfig = is_array($previousViewDefinition['view'] ?? null) ? $previousViewDefinition['view'] : [];
        $currentViewConfig = is_array($currentViewDefinition['view'] ?? null) ? $currentViewDefinition['view'] : [];
        $currentDataContract = is_array($currentViewDefinition['data_contract'] ?? null) ? $currentViewDefinition['data_contract'] : [];
        $previousNavigation = is_array($previousNavigationDefinition['navigation'] ?? null) ? $previousNavigationDefinition['navigation'] : [];
        $currentNavigation = is_array($currentNavigationDefinition['navigation'] ?? null) ? $currentNavigationDefinition['navigation'] : [];

        $newFields = array_values(array_diff(array_keys($currentFields), array_keys($previousFields)));
        sort($newFields);
        $removedFields = array_values(array_diff(array_keys($previousFields), array_keys($currentFields)));
        sort($removedFields);
        $remainingFields = array_values(array_intersect(array_keys($previousFields), array_keys($currentFields)));
        sort($remainingFields);

        $viewKey = self::generatedKey((string)($currentViewConfig['view_key'] ?? $previousViewConfig['view_key'] ?? 'index'));
        if ($viewKey === '') {
            $viewKey = 'index';
        }
        $routePath = trim((string)($currentView['route_path'] ?? ''));
        $viewFields = [];
        foreach ((array)($currentViewConfig['fields'] ?? []) as $fieldKey) {
            $normalized = self::generatedKey((string)$fieldKey);
            if ($normalized !== '') {
                $viewFields[$normalized] = true;
            }
        }
        if ($viewFields === []) {
            foreach (array_keys($currentFields) as $fieldKey) {
                $viewFields[$fieldKey] = true;
            }
        }

        $viewsAfter = [[
            'view' => 'view:' . $viewKey,
            'route' => $routePath,
            'fields' => array_values(array_keys($viewFields)),
        ]];

        $brokenViews = [];
        $warnings = [];

        foreach ((array)($currentViewConfig['fields'] ?? []) as $fieldKey) {
            $normalized = self::generatedKey((string)$fieldKey);
            if ($normalized === '') {
                continue;
            }
            if (!isset($currentFields[$normalized])) {
                $brokenViews[] = [
                    'view' => 'view:' . $viewKey,
                    'reason' => 'missing_required_field',
                    'field' => $normalized,
                ];
                $warnings[] = 'view_missing_field:' . $normalized;
            }
        }

        $allowedFilters = is_array($currentDataContract['allowed_filters'] ?? null) ? $currentDataContract['allowed_filters'] : [];
        foreach ($allowedFilters as $filterField) {
            $normalized = self::generatedKey((string)$filterField);
            if ($normalized === '') {
                continue;
            }
            if (!isset($currentFields[$normalized])) {
                $brokenViews[] = [
                    'view' => 'view:' . $viewKey,
                    'reason' => 'filter_references_removed_field',
                    'field' => $normalized,
                ];
                $warnings[] = 'filter_removed_field:' . $normalized;
            }
        }

        $navigationUrl = trim((string)($currentNavigation['url'] ?? ''));
        if ($navigationUrl !== '' && $routePath !== '' && $navigationUrl !== $routePath) {
            $brokenViews[] = [
                'view' => 'navigation:' . self::generatedKey((string)($currentNavigation['key'] ?? 'navigation')),
                'reason' => 'navigation_invalid_route',
                'expected_route' => $routePath,
                'actual_route' => $navigationUrl,
            ];
            $warnings[] = 'navigation_invalid_route';
        }

        $changes = is_array($diff['changes'] ?? null) ? $diff['changes'] : [];
        foreach ($changes as $change) {
            if (!is_array($change)) {
                continue;
            }
            $type = (string)($change['type'] ?? '');
            $path = (string)($change['path'] ?? '');
            if ($type === 'field_removed' && strpos($path, 'module.fields.') === 0) {
                $warnings[] = 'field_removed:' . substr($path, strlen('module.fields.'));
            }
            if ($type === 'view_changed' && $path === 'view.route_path') {
                $warnings[] = 'route_changed';
            }
        }

        if ($removedFields !== [] && !empty($previousNavigation['url']) && !empty($currentNavigation['url']) && $previousNavigation['url'] !== $currentNavigation['url']) {
            $warnings[] = 'route_changed_with_removed_fields';
        }

        return [
            'views_after' => $viewsAfter,
            'broken_views' => array_values(array_unique($brokenViews, SORT_REGULAR)),
            'removed_fields' => $removedFields,
            'new_fields' => $newFields,
            'remaining_fields' => $remainingFields,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /**
     * @param array<string,mixed> $simulationPreview
     * @param array<string,mixed> $input
     * @return array{requires_override:bool,has_broken_views:bool,override:bool,reason:string,valid:bool,errors:array<int,string>}
     */
    public static function validateSimulationOverride(array $simulationPreview, array $input = []): array
    {
        $brokenViews = is_array($simulationPreview['broken_views'] ?? null) ? $simulationPreview['broken_views'] : [];
        $hasBrokenViews = $brokenViews !== [];
        $override = !empty($input['simulation_override']);
        $reason = trim((string)($input['simulation_override_reason'] ?? ''));

        $errors = [];
        if ($hasBrokenViews && !$override) {
            $errors[] = 'ops.gui_studio.simulation.error.override_required';
        }
        if ($hasBrokenViews && $reason === '') {
            $errors[] = 'ops.gui_studio.simulation.error.reason_required';
        }

        return [
            'requires_override' => $hasBrokenViews,
            'has_broken_views' => $hasBrokenViews,
            'override' => $override,
            'reason' => $reason,
            'valid' => $errors === [],
            'errors' => array_values(array_unique($errors)),
        ];
    }

    /**
     * @param array<int,array{strategy?:string}> $migrationPlan
     * @param array<string,mixed> $input
     * @return array{requires_override:bool,has_destructive:bool,override:bool,reason:string,valid:bool,errors:array<int,string>}
     */
    public static function validateMigrationPlanOverride(array $migrationPlan, array $input = []): array
    {
        $hasDestructive = false;
        foreach ($migrationPlan as $step) {
            if (!is_array($step)) {
                continue;
            }
            if (strtolower((string)($step['strategy'] ?? '')) === 'destructive') {
                $hasDestructive = true;
                break;
            }
        }

        $override = !empty($input['migration_override']);
        $reason = trim((string)($input['migration_override_reason'] ?? ''));
        $errors = [];
        if ($hasDestructive && !$override) {
            $errors[] = 'ops.gui_studio.migration.error.override_required';
        }
        if ($hasDestructive && $reason === '') {
            $errors[] = 'ops.gui_studio.migration.error.override_reason_required';
        }

        return [
            'requires_override' => $hasDestructive,
            'has_destructive' => $hasDestructive,
            'override' => $override,
            'reason' => $reason,
            'valid' => $errors === [],
            'errors' => array_values(array_unique($errors)),
        ];
    }

    /** @return array<string,mixed> */
    public static function studioProjectModel(): array
    {
        $project = json_decode(self::loadStudioTemplateFile('studio_project.placeholder.json'), true);
        if (!is_array($project)) {
            $project = [];
        }

        return [
            'project' => $project,
            'status' => 'draft',
            'storage' => 'session_only',
            'writes_enabled' => false,
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public static function templateLibrary(): array
    {
        $registry = json_decode(self::loadStudioTemplateFile('template_registry.json'), true);
        $templates = is_array($registry['templates'] ?? null) ? $registry['templates'] : [];

        return array_values(array_filter($templates, 'is_array'));
    }

    /** @return array<int,array{key:string,label:string,status:string,implemented:bool}> */
    public static function draftLifecycle(): array
    {
        return [
            ['key' => 'draft',        'label' => 'Draft',        'status' => 'active',       'implemented' => true],
            ['key' => 'validate',     'label' => 'Validate',     'status' => 'active',        'implemented' => true],
            ['key' => 'preflight',    'label' => 'Preflight',    'status' => 'active',        'implemented' => true],
            ['key' => 'compile_plan', 'label' => 'Compile Plan', 'status' => 'active',        'implemented' => true],
            ['key' => 'diff_preview', 'label' => 'Diff Preview', 'status' => 'active',        'implemented' => true],
            ['key' => 'approval',     'label' => 'Approval',     'status' => 'active',        'implemented' => true],
            ['key' => 'snapshot',     'label' => 'Snapshot',     'status' => 'active',        'implemented' => true],
            ['key' => 'publish',      'label' => 'Publish',      'status' => 'locked_future', 'implemented' => false],
            ['key' => 'verify',       'label' => 'Verify',       'status' => 'future',        'implemented' => false],
        ];
    }

    /** @return array<int,string> */
    public static function artifactTypes(): array
    {
        return self::ARTIFACT_TYPES;
    }

    /**
     * Artifact kinds that can be loaded into the Studio editor (loadable affordance).
     * All other known kinds are inspect-only.
     */
    public const LOADABLE_KINDS = ['app', 'module', 'view', 'dashboard'];

    /**
     * Resolve the normalized artifact kind from a library node array.
     *
     * @param array{type?:string,id?:string} $node
     * @return string One of: 'app'|'module'|'view'|'dashboard'|'nav'|'route'|'unknown'
     */
    public static function resolveArtifactKindFromNode(array $node): string
    {
        $typeRaw = strtolower(trim((string)($node['type'] ?? '')));
        $idRaw   = strtolower(trim((string)($node['id'] ?? '')));

        if ($typeRaw === 'dashboard' || str_starts_with($idRaw, 'dashboard:')) {
            return 'dashboard';
        }
        if (str_starts_with($idRaw, 'nav:') || str_contains($typeRaw, 'nav')) {
            return 'nav';
        }
        if (str_starts_with($idRaw, 'route:') || str_starts_with($idRaw, 'route_file:') || str_contains($typeRaw, 'route')) {
            return 'route';
        }
        if (str_starts_with($idRaw, 'module:') || str_contains($typeRaw, 'module')) {
            return 'module';
        }
        if (str_starts_with($idRaw, 'app:') || $typeRaw === 'app') {
            return 'app';
        }
        return 'view';
    }

    /**
     * Return the library_kind string ('views'|'modules'|'routes') for a resolved artifact kind and node id.
     *
     * @param string $artifactKind Result of resolveArtifactKindFromNode()
     * @param string $nodeId       Raw node id (lowercase)
     */
    public static function resolveLibraryKind(string $artifactKind, string $nodeId = ''): string
    {
        if ($artifactKind === 'route' || $artifactKind === 'nav' || str_contains($nodeId, '/apps/')) {
            return 'routes';
        }
        if ($artifactKind === 'module') {
            return 'modules';
        }
        return 'views';
    }

    /**
     * Return true when an artifact kind is loadable into the Studio editor.
     */
    public static function isArtifactKindLoadable(string $artifactKind): bool
    {
        return in_array($artifactKind, self::LOADABLE_KINDS, true);
    }

    /**
     * @param array<string,string> $payload
     * @return array{ok:bool,errors:array<int,string>,checks:array<int,array<string,mixed>>,decoded:array<string,array<string,mixed>>}
     */
    public static function validateBundle(array $payload): array
    {
        $errors = [];
        $checks = [];
        $decoded = [];

        $required = [
            'app_manifest' => 'studio.app-manifest.v1',
            'module_manifest' => 'studio.module-manifest.v1',
            'view_definition' => 'studio.view-manifest.v1',
            'navigation_definition' => 'studio.navigation-manifest.v1',
        ];

        foreach ($required as $key => $schema) {
            $jsonText = trim((string)($payload[$key] ?? ''));
            if ($jsonText === '') {
                $errors[] = 'ops.gui_studio.error.missing_json_' . $key;
                continue;
            }
            $obj = json_decode($jsonText, true);
            if (!is_array($obj)) {
                $errors[] = 'ops.gui_studio.error.invalid_json_' . $key;
                continue;
            }
            $decoded[$key] = $obj;

            $actualSchema = trim((string)($obj['schema_version'] ?? ''));
            $pass = $actualSchema === $schema;
            $checks[] = [
                'name' => 'schema:' . $key,
                'pass' => $pass,
                'message_key' => $pass ? 'ops.gui_studio.check.schema_ok' : 'ops.gui_studio.check.schema_mismatch',
                'context' => $key,
            ];
            if (!$pass) {
                $errors[] = 'ops.gui_studio.error.schema_mismatch';
            }
        }

        $rawBundle = implode("\n", array_map(static fn($value): string => (string)$value, $payload));
        $containsPhp = stripos($rawBundle, '<?php') !== false || stripos($rawBundle, '<?=') !== false;
        $checks[] = [
            'name' => 'no_arbitrary_php',
            'pass' => !$containsPhp,
            'message_key' => !$containsPhp ? 'ops.gui_studio.check.no_php_ok' : 'ops.gui_studio.check.no_php_invalid',
            'context' => 'draft_bundle',
        ];
        if ($containsPhp) {
            $errors[] = 'ops.gui_studio.error.no_php';
        }

        if (isset($decoded['view_definition']['view']) && is_array($decoded['view_definition']['view'])) {
            $view = $decoded['view_definition']['view'];
            $routePath = trim((string)($view['route_path'] ?? ''));
            $surface = trim((string)($view['surface'] ?? ''));

            $routeValid = (bool)preg_match('#^/(ops|admin|u|displays|apps)/#', $routePath);
            $checks[] = [
                'name' => 'route_prefix',
                'pass' => $routeValid,
                'message_key' => $routeValid ? 'ops.gui_studio.check.route_prefix_ok' : 'ops.gui_studio.check.route_prefix_invalid',
                'context' => $routePath,
            ];
            if (!$routeValid) {
                $errors[] = 'ops.gui_studio.error.route_prefix';
            }

            $surfaceRouteValid = true;
            if ($surface === 'operator' && strpos($routePath, '/u/') !== 0) {
                $surfaceRouteValid = false;
            }
            if ($surface === 'admin' && strpos($routePath, '/u/') === 0) {
                $surfaceRouteValid = false;
            }
            $checks[] = [
                'name' => 'surface_route_confinement',
                'pass' => $surfaceRouteValid,
                'message_key' => $surfaceRouteValid ? 'ops.gui_studio.check.surface_route_ok' : 'ops.gui_studio.check.surface_route_invalid',
                'context' => $surface,
            ];
            if (!$surfaceRouteValid) {
                $errors[] = 'ops.gui_studio.error.surface_route';
            }

            $titleKey = trim((string)($view['title_key'] ?? ''));
            $descKey = trim((string)($view['description_key'] ?? ''));
            $i18nValid = $titleKey !== '' && $descKey !== '';
            $checks[] = [
                'name' => 'view_i18n',
                'pass' => $i18nValid,
                'message_key' => $i18nValid ? 'ops.gui_studio.check.i18n_ok' : 'ops.gui_studio.check.i18n_missing',
                'context' => 'view',
            ];
            if (!$i18nValid) {
                $errors[] = 'ops.gui_studio.error.i18n_missing';
            }

            $csrfFlag = (bool)($decoded['view_definition']['security']['csrf_for_mutations'] ?? false);
            $checks[] = [
                'name' => 'csrf_for_mutations',
                'pass' => $csrfFlag,
                'message_key' => $csrfFlag ? 'ops.gui_studio.check.csrf_ok' : 'ops.gui_studio.check.csrf_missing',
                'context' => 'view.security',
            ];
            if (!$csrfFlag) {
                $errors[] = 'ops.gui_studio.error.csrf_missing';
            }

            $layout = is_array($decoded['view_definition']['layout'] ?? null) ? $decoded['view_definition']['layout'] : [];
            $layoutErrors = self::validateVisualLayoutSchema($layout);
            $checks[] = [
                'name' => 'layout_schema',
                'pass' => $layoutErrors === [],
                'message_key' => $layoutErrors === [] ? 'ops.gui_studio.check.schema_ok' : 'ops.gui_studio.check.schema_mismatch',
                'context' => 'view.layout',
            ];
            if ($layoutErrors !== []) {
                foreach ($layoutErrors as $layoutError) {
                    $errors[] = $layoutError;
                }
            }
        }

        if (isset($decoded['navigation_definition']['navigation']) && is_array($decoded['navigation_definition']['navigation'])) {
            $nav = $decoded['navigation_definition']['navigation'];
            $scope = trim((string)($nav['scope'] ?? ''));
            $url = trim((string)($nav['url'] ?? ''));

            $navScopeValid = !($scope === 'admin' && strpos($url, '/u/') === 0);
            $checks[] = [
                'name' => 'nav_scope_confinement',
                'pass' => $navScopeValid,
                'message_key' => $navScopeValid ? 'ops.gui_studio.check.nav_scope_ok' : 'ops.gui_studio.check.nav_scope_invalid',
                'context' => $scope,
            ];
            if (!$navScopeValid) {
                $errors[] = 'ops.gui_studio.error.nav_scope';
            }
        }

        return [
            'ok' => $errors === [],
            'errors' => array_values(array_unique($errors)),
            'checks' => $checks,
            'decoded' => $decoded,
        ];
    }

    /**
     * @param array<string,array<string,mixed>> $decoded
     * @return array{
     *   schema_version:string,
     *   generated_by:string,
     *   studio_project_id:string,
     *   studio_draft_id:string,
     *   artifact_taxonomy:array<int,string>,
     *   compile_snapshot:array<string,mixed>,
     *   summary_groups:array<string,array<int,string>>,
     *   layers:array<string,array<int,array<string,mixed>>>,
     *   dependency_checks:array<int,array<string,mixed>>,
     *   conflict_checks:array<int,array<string,mixed>>,
     *   drift_checks:array<int,array<string,mixed>>,
     *   diff_readiness:array<int,array<string,mixed>>,
     *   artifacts:array<int,array{
     *   artifact_id:string,
     *   artifact_type:string,
     *   owning_app:string,
     *   owning_module:string,
     *   target_path:string,
     *   source_template:string,
     *   template_version:string,
     *   change_type:string,
     *   risk_level:string,
     *   dependencies:array<int,string>,
     *   generated_by:string,
     *   studio_project_id:string,
     *   studio_draft_id:string,
     *   ownership_scope:string,
     *   upgrade_safe:bool,
     *   customization_zone:string,
     *   content_hash:string,
     *   before_hash:string,
     *   after_hash:string,
     *   target_exists:bool,
     *   drift_status:string,
     *   diff_status:string,
     *   diff_summary:string,
     *   human_diff_summary:string,
     *   machine_diff_summary:array<string,mixed>,
     *   human_summary:string,
     *   machine_summary:array<string,mixed>
     * }>}
     */
    public static function buildCompilePlan(array $decoded): array
    {
        $appKey = trim((string)($decoded['app_manifest']['app']['app_key'] ?? ''));
        $moduleKey = trim((string)($decoded['module_manifest']['module']['module_key'] ?? ''));
        $routePath = trim((string)($decoded['view_definition']['view']['route_path'] ?? ''));
        $navUrl = trim((string)($decoded['navigation_definition']['navigation']['url'] ?? ''));
        $appPathKey = self::studlyPathSegment($appKey !== '' ? $appKey : 'DraftApp');
        $modulePathKey = $moduleKey !== '' ? $moduleKey : 'DraftModule';
        $viewKey = trim((string)($decoded['view_definition']['view']['view_key'] ?? 'draft_view'));
        $navKey = trim((string)($decoded['navigation_definition']['navigation']['key'] ?? 'draft_navigation'));
        $studioProjectId = self::studioProjectId($decoded, $appKey, $moduleKey);
        $moduleTemplate = self::inferModuleTemplate($decoded);
        $viewTemplate = self::inferViewTemplate($decoded);
        $studioDraftId = self::studioDraftId($decoded, $studioProjectId);

        $seedArtifacts = [
            'app_layer' => [
                ['app', 'apps/' . $appPathKey . '/manifest.json', self::inferAppTemplate($decoded), [], 'Plan app manifest metadata for ' . ($appKey !== '' ? $appKey : 'draft app') . '.'],
                ['route', 'apps/' . $appPathKey . '/routes.php', self::inferAppTemplate($decoded), ['app'], 'Plan app route declarations for ' . ($routePath !== '' ? $routePath : 'draft route') . '.'],
            ],
            'module_layer' => [
                ['module', 'apps/' . $appPathKey . '/modules/' . $modulePathKey . '/plugin.json', $moduleTemplate, ['app'], 'Plan module manifest metadata for ' . ($moduleKey !== '' ? $moduleKey : 'draft module') . '.'],
                ['controller', 'apps/' . $appPathKey . '/modules/' . $modulePathKey . '/Controllers/' . self::studlyPathSegment($moduleKey) . 'Controller.php', $moduleTemplate, ['module', 'route'], 'Plan controller target for module routing.'],
                ['service', 'apps/' . $appPathKey . '/modules/' . $modulePathKey . '/Services/' . self::studlyPathSegment($moduleKey) . 'Service.php', $moduleTemplate, ['module'], 'Plan service target for module-owned behavior.'],
                ['migration_file', 'apps/' . $appPathKey . '/modules/' . $modulePathKey . '/migrations/000000_000000_draft.sql', $moduleTemplate, ['module'], 'Plan migration file placeholder only; migrations are not executed.'],
            ],
            'view_layer' => [
                ['view', 'apps/' . $appPathKey . '/modules/' . $modulePathKey . '/Views/' . $viewKey . '.php', $viewTemplate, ['module', 'route'], 'Plan view target for route ' . ($routePath !== '' ? $routePath : '[missing route]') . '.'],
                [self::surfaceArtifactType($moduleTemplate), 'apps/' . $appPathKey . '/modules/' . $modulePathKey . '/Views/surfaces/' . $viewKey . '.json', $viewTemplate, ['view'], 'Plan surface metadata for future UI composition.'],
                ['translation', 'apps/' . $appPathKey . '/modules/' . $modulePathKey . '/lang/en.php', $viewTemplate, ['view'], 'Plan localization target for view labels and empty states.'],
            ],
            'navigation_layer' => [
                ['navigation', 'apps/' . $appPathKey . '/navigation.php', 'navigation_entry', ['app', 'route', 'view'], 'Plan navigation entry ' . ($navKey !== '' ? $navKey : 'draft navigation') . '.'],
            ],
            'permission_layer' => [
                ['permission', 'apps/' . $appPathKey . '/modules/' . $modulePathKey . '/Policies/' . self::studlyPathSegment($moduleKey) . 'Policy.php', $moduleTemplate, ['module', 'view'], 'Plan permission policy target for view and action gates.'],
            ],
            'package_layer' => [
                ['package_manifest', 'packages/dry-run/' . ($appKey !== '' ? $appKey : 'draft-app') . '/artifact-manifest.json', 'artifact_manifest', ['app', 'module'], 'Plan package metadata only; publish remains locked.'],
            ],
        ];

        $layers = [];
        $artifacts = [];
        foreach ($seedArtifacts as $layer => $rows) {
            $layers[$layer] = [];
            foreach ($rows as $row) {
                [$type, $targetPath, $template, $dependencyTypes, $summary] = $row;
                $artifact = self::normalizeArtifact(
                    (string)$type,
                    (string)$targetPath,
                    (string)$template,
                    array_map('strval', (array)$dependencyTypes),
                    (string)$summary,
                    $appKey,
                    $moduleKey,
                    $studioProjectId,
                    $studioDraftId,
                    $decoded
                );
                $layers[$layer][] = $artifact;
                $artifacts[] = $artifact;
            }
        }

        $dependencyChecks = self::dependencyChecks($artifacts, $appKey, $moduleKey);
        $conflictChecks = self::conflictChecks($artifacts, $appKey, $moduleKey);
        $normalizedConflicts = self::normalizeConflicts($conflictChecks);
        $driftChecks = self::driftChecks($artifacts);
        $diffReadiness = array_map(static fn(array $artifact): array => [
            'artifact_id' => $artifact['artifact_id'],
            'target_path' => $artifact['target_path'],
            'target_exists' => $artifact['target_exists'],
            'before_hash' => $artifact['before_hash'],
            'after_hash' => $artifact['after_hash'],
            'drift_status' => $artifact['drift_status'],
            'diff_status' => $artifact['diff_status'],
            'diff_summary' => $artifact['diff_summary'],
            'human_diff_summary' => $artifact['human_diff_summary'],
            'machine_diff_summary' => $artifact['machine_diff_summary'],
        ], $artifacts);
        $summaryGroups = self::summaryGroups($artifacts, $dependencyChecks, $conflictChecks, $driftChecks);
        $compileSnapshot = self::compileSnapshot($studioProjectId, $studioDraftId, $artifacts, $dependencyChecks, $conflictChecks, $driftChecks);
        $compileMeta = [
            'compile_id' => (string)($compileSnapshot['compile_id'] ?? ''),
            'parent_compile_id' => null,
            'bundle_hash' => (string)($compileSnapshot['bundle_hash'] ?? ''),
            'schema_version' => 'v1',
        ];

        return [
            'schema_version' => 'studio.compile-plan.v1',
            'generated_by' => self::GENERATOR_ID,
            'studio_project_id' => $studioProjectId,
            'studio_draft_id' => $studioDraftId,
            'artifact_taxonomy' => self::ARTIFACT_TYPES,
            'compile_snapshot' => $compileSnapshot,
            'compile_meta' => $compileMeta,
            'summary_groups' => $summaryGroups,
            'layers' => $layers,
            'dependency_checks' => $dependencyChecks,
            'conflict_checks' => $conflictChecks,
            'normalized_conflicts' => $normalizedConflicts,
            'drift_checks' => $driftChecks,
            'diff_readiness' => $diffReadiness,
            'artifacts' => $artifacts,
        ];
    }

    /** @return array<string,mixed> */
    public static function buildDiffViewModel(array $compilePlan): array
    {
        $groups = [];
        $riskLevels = [];
        $ownership = [];
        $driftStatuses = [];
        $summary = [
            'total_artifacts' => 0,
            'created' => 0,
            'updated' => 0,
            'blocked' => 0,
            'risky' => 0,
        ];

        $artifacts = is_array($compilePlan['artifacts'] ?? null) ? $compilePlan['artifacts'] : [];
        foreach ($artifacts as $artifact) {
            if (!is_array($artifact)) {
                continue;
            }

            $app = self::displayName((string)($artifact['owning_app'] ?? 'draft_app'));
            $module = self::displayName((string)($artifact['owning_module'] ?? 'app'));
            $type = strtolower((string)($artifact['artifact_type'] ?? 'artifact'));
            $risk = strtoupper((string)($artifact['risk_level'] ?? 'medium'));
            $changeType = strtoupper((string)($artifact['change_type'] ?? 'create'));
            $drift = (string)($artifact['drift_status'] ?? 'unknown');
            $ownershipFilter = self::diffOwnershipFilter($artifact);
            $targetPath = (string)($artifact['target_path'] ?? '');
            $name = basename($targetPath) !== '' ? basename($targetPath) : (string)($artifact['artifact_id'] ?? 'artifact');

            $riskLevels[$risk] = true;
            $ownership[$ownershipFilter] = true;
            $driftStatuses[$drift] = true;

            $summary['total_artifacts']++;
            if ($changeType === 'CREATE') {
                $summary['created']++;
            }
            if ($changeType === 'UPDATE' || $changeType === 'CONFLICT') {
                $summary['updated']++;
            }
            if ($risk === 'BLOCKED') {
                $summary['blocked']++;
            }
            if ($risk === 'HIGH' || $risk === 'BLOCKED') {
                $summary['risky']++;
            }

            $groups[$app][$module][$type][] = [
                'artifact' => (string)($artifact['artifact_id'] ?? ''),
                'artifact_name' => $name,
                'target_path' => $targetPath,
                'type' => $type,
                'change_type' => $changeType,
                'risk' => $risk,
                'risk_score' => (int)($artifact['risk_meta']['risk_score'] ?? self::riskScore(strtolower($risk))),
                'dominant_reason' => (string)($artifact['risk_meta']['dominant_reason'] ?? ''),
                'ownership' => $ownershipFilter,
                'ownership_scope' => (string)($artifact['ownership_scope'] ?? 'unknown'),
                'drift' => $drift,
                'drift_interpretation' => is_array($artifact['drift_interpretation'] ?? null) ? $artifact['drift_interpretation'] : self::interpretDrift($drift),
                'summary' => (string)($artifact['human_diff_summary'] ?? $artifact['human_summary'] ?? ''),
                'symbol' => self::diffSymbol($changeType, $risk),
            ];
        }

        ksort($groups);
        foreach ($groups as &$modules) {
            ksort($modules);
            foreach ($modules as &$types) {
                ksort($types);
            }
        }
        unset($modules, $types);

        return [
            'groups' => $groups,
            'filters' => [
                'risk_levels' => array_values(array_unique(array_merge(['ALL', 'HIGH', 'BLOCKED'], array_keys($riskLevels)))),
                'ownership' => array_values(array_unique(array_merge(['ALL', 'APP', 'SYSTEM', 'EXTERNAL'], array_keys($ownership)))),
                'drift_status' => array_values(array_unique(array_merge(['ALL', 'modified', 'external', 'conflict'], array_keys($driftStatuses)))),
            ],
            'summary' => $summary,
        ];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public static function buildApprovalPayload(array $compilePlan, array $input, array $changeIntelligence = [], array $migrationPlan = [], array $impactAnalysis = [], array $simulationPreview = []): array
    {
        $compileMeta = is_array($compilePlan['compile_meta'] ?? null) ? $compilePlan['compile_meta'] : [];
        $compileSnapshot = is_array($compilePlan['compile_snapshot'] ?? null) ? $compilePlan['compile_snapshot'] : [];
        $compileId = (string)($compileMeta['compile_id'] ?? $compileSnapshot['compile_id'] ?? '');
        $decision = strtolower(trim((string)($input['decision'] ?? 'rejected')));
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            $decision = 'rejected';
        }

        $reason = trim((string)($input['reason'] ?? ''));
        $riskAcknowledged = !empty($input['risk_acknowledged']);
        $approvedBy = trim((string)($input['approved_by'] ?? ''));
        $approvedAt = trim((string)($input['approved_at'] ?? ''));
        if ($approvedAt === '') {
            $approvedAt = gmdate('c');
        }

        $highRiskItems = [];
        $blockedItems = [];
        $artifacts = is_array($compilePlan['artifacts'] ?? null) ? $compilePlan['artifacts'] : [];
        foreach ($artifacts as $artifact) {
            if (!is_array($artifact)) {
                continue;
            }

            $riskLevel = strtoupper((string)($artifact['risk_level'] ?? ''));
            $summary = self::approvalArtifactSummary($artifact);
            if ($riskLevel === 'HIGH') {
                $highRiskItems[] = $summary;
            }
            if ($riskLevel === 'BLOCKED') {
                $blockedItems[] = $summary;
            }
        }

        $changes = is_array($changeIntelligence['changes'] ?? null) ? $changeIntelligence['changes'] : [];
        foreach ($changes as $change) {
            if (!is_array($change)) {
                continue;
            }
            $type = (string)($change['type'] ?? '');
            $path = (string)($change['path'] ?? '');
            if ($type === 'field_removed' || ($type === 'view_changed' && $path === 'view.route_path')) {
                $highRiskItems[] = [
                    'artifact_id' => 'structured:' . ($path !== '' ? $path : $type),
                    'artifact_type' => 'structured_change',
                    'target_path' => $path,
                    'risk_level' => 'HIGH',
                    'change_type' => strtoupper($type),
                    'summary' => $type . ' at ' . $path,
                ];
            }
        }
        $highRiskItems = array_values(array_unique($highRiskItems, SORT_REGULAR));

        return [
            'approval_id' => self::approvalId($compileId, $approvedBy, $decision, $reason),
            'compile_id' => $compileId,
            'approved_by' => $approvedBy,
            'approved_at' => $approvedAt,
            'decision' => $decision,
            'reason' => $reason,
            'risk_acknowledged' => $riskAcknowledged,
            'high_risk_items' => $highRiskItems,
            'blocked_items' => $blockedItems,
            'total_artifacts' => count($artifacts),
            'change_intelligence' => $changeIntelligence,
            'migration_plan' => $migrationPlan,
            'impact_analysis' => $impactAnalysis,
            'simulation_preview' => $simulationPreview,
        ];
    }

    /** @param array<string,array<string,mixed>> $bundle */
    private static function structuredAdapterName(array $bundle): string
    {
        $viewDefinition = is_array($bundle['view_definition'] ?? null) ? $bundle['view_definition'] : [];
        $moduleManifest = is_array($bundle['module_manifest'] ?? null) ? $bundle['module_manifest'] : [];
        $dataContract = is_array($viewDefinition['data_contract'] ?? null) ? $viewDefinition['data_contract'] : [];
        $adapterClass = trim((string)($dataContract['adapter_class'] ?? ''));
        if ($adapterClass !== '') {
            $parts = explode('\\', $adapterClass);
            $last = trim((string)end($parts));
            if ($last !== '') {
                return $last;
            }
        }

        $module = is_array($moduleManifest['module'] ?? null) ? $moduleManifest['module'] : [];
        $moduleKey = self::generatedKey((string)($module['module_key'] ?? 'module'));
        if ($moduleKey === '') {
            $moduleKey = 'module';
        }
        $segments = explode('_', $moduleKey);
        $studly = '';
        foreach ($segments as $segment) {
            $segment = trim((string)$segment);
            if ($segment === '') {
                continue;
            }
            $studly .= ucfirst($segment);
        }
        if ($studly === '') {
            $studly = 'Module';
        }
        return $studly . 'Adapter';
    }

    /** @param array<string,array<string,mixed>> $bundle @return array<string,array<string,mixed>> */
    private static function structuredFieldMap(array $bundle): array
    {
        $moduleManifest = is_array($bundle['module_manifest'] ?? null) ? $bundle['module_manifest'] : [];
        $viewDefinition = is_array($bundle['view_definition'] ?? null) ? $bundle['view_definition'] : [];
        $source = [];
        if (is_array($moduleManifest['fields'] ?? null)) {
            $source = $moduleManifest['fields'];
        } elseif (is_array($viewDefinition['fields'] ?? null)) {
            $source = $viewDefinition['fields'];
        }

        $map = [];
        foreach ($source as $field) {
            if (!is_array($field)) {
                continue;
            }
            $key = self::generatedKey((string)($field['key'] ?? $field['name'] ?? ''));
            if ($key === '') {
                continue;
            }
            $map[$key] = [
                'key' => $key,
                'type' => strtolower((string)($field['type'] ?? 'string')),
                'required' => !empty($field['required']),
                'default' => (string)($field['default'] ?? ''),
            ];
        }
        ksort($map);
        return $map;
    }

    /** @param array<string,array<string,mixed>> $bundle @return array{view_kind:string,route_path:string} */
    private static function structuredViewMap(array $bundle): array
    {
        $viewDefinition = is_array($bundle['view_definition'] ?? null) ? $bundle['view_definition'] : [];
        $view = is_array($viewDefinition['view'] ?? null) ? $viewDefinition['view'] : [];
        return [
            'view_kind' => strtolower(trim((string)($view['view_kind'] ?? ''))),
            'route_path' => trim((string)($view['route_path'] ?? '')),
        ];
    }

    /** @param array<string,array<string,mixed>> $bundle @return array{url:string,label:string} */
    private static function structuredNavigationMap(array $bundle): array
    {
        $navigationDefinition = is_array($bundle['navigation_definition'] ?? null) ? $bundle['navigation_definition'] : [];
        $navigation = is_array($navigationDefinition['navigation'] ?? null) ? $navigationDefinition['navigation'] : [];
        return [
            'url' => trim((string)($navigation['url'] ?? '')),
            'label' => trim((string)($navigation['label'] ?? '')),
        ];
    }

    /** @param mixed $value */
    private static function stableJson($value): string
    {
        return (string)json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** @return array{valid:bool,errors:array<int,string>} */
    public static function validateApproval(array $approvalPayload): array
    {
        $errors = [];
        $decision = strtolower((string)($approvalPayload['decision'] ?? ''));
        $highRiskItems = is_array($approvalPayload['high_risk_items'] ?? null) ? $approvalPayload['high_risk_items'] : [];
        $blockedItems = is_array($approvalPayload['blocked_items'] ?? null) ? $approvalPayload['blocked_items'] : [];

        if (!in_array($decision, ['approved', 'rejected'], true)) {
            $errors[] = 'ops.gui_studio.approval.error.invalid_decision';
        }

        if ($decision === 'approved' && $blockedItems !== []) {
            $errors[] = 'ops.gui_studio.approval.error.blocked_present';
        }

        if ($decision === 'approved' && $highRiskItems !== []) {
            if (empty($approvalPayload['risk_acknowledged'])) {
                $errors[] = 'ops.gui_studio.approval.error.risk_ack_required';
            }
            if (trim((string)($approvalPayload['reason'] ?? '')) === '') {
                $errors[] = 'ops.gui_studio.approval.error.reason_required';
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => array_values(array_unique($errors)),
        ];
    }

    /** @return array{total_artifacts:int,high_risk_count:int,blocked_count:int,decision:string} */
    public static function summarizeApproval(array $approvalPayload): array
    {
        $highRiskItems = is_array($approvalPayload['high_risk_items'] ?? null) ? $approvalPayload['high_risk_items'] : [];
        $blockedItems = is_array($approvalPayload['blocked_items'] ?? null) ? $approvalPayload['blocked_items'] : [];

        return [
            'total_artifacts' => (int)($approvalPayload['total_artifacts'] ?? 0),
            'high_risk_count' => count($highRiskItems),
            'blocked_count' => count($blockedItems),
            'decision' => strtolower((string)($approvalPayload['decision'] ?? 'rejected')),
        ];
    }

    /** @return array<string,mixed> */
    public static function buildSnapshot(array $compilePlan, array $approvalPayload): array
    {
        $compileMeta = is_array($compilePlan['compile_meta'] ?? null) ? $compilePlan['compile_meta'] : [];
        $compileSnapshot = is_array($compilePlan['compile_snapshot'] ?? null) ? $compilePlan['compile_snapshot'] : [];
        $compileId = (string)($compileMeta['compile_id'] ?? $compileSnapshot['compile_id'] ?? '');
        $approvalId = (string)($approvalPayload['approval_id'] ?? '');
        $artifacts = [];
        $riskSummary = [
            'low' => 0,
            'medium' => 0,
            'high' => 0,
            'blocked' => 0,
        ];

        $compileArtifacts = is_array($compilePlan['artifacts'] ?? null) ? $compilePlan['artifacts'] : [];
        foreach ($compileArtifacts as $artifact) {
            if (!is_array($artifact)) {
                continue;
            }

            $riskLevel = strtolower((string)($artifact['risk_level'] ?? 'low'));
            if (!array_key_exists($riskLevel, $riskSummary)) {
                $riskLevel = 'low';
            }
            $riskSummary[$riskLevel]++;

            $artifacts[] = [
                'artifact_id' => (string)($artifact['artifact_id'] ?? ''),
                'artifact_type' => (string)($artifact['artifact_type'] ?? ''),
                'owning_app' => (string)($artifact['owning_app'] ?? ''),
                'owning_module' => (string)($artifact['owning_module'] ?? ''),
                'target_path' => (string)($artifact['target_path'] ?? ''),
                'change_type' => (string)($artifact['change_type'] ?? ''),
                'risk_level' => strtoupper((string)($artifact['risk_level'] ?? '')),
                'ownership_scope' => (string)($artifact['ownership_scope'] ?? ''),
                'customization_zone' => (string)($artifact['customization_zone'] ?? ''),
                'drift_status' => (string)($artifact['drift_status'] ?? ''),
                'generated_by' => (string)($artifact['generated_by'] ?? ''),
                'content_hash' => (string)($artifact['content_hash'] ?? $artifact['after_hash'] ?? ''),
                'before_hash' => (string)($artifact['before_hash'] ?? ''),
                'after_hash' => (string)($artifact['after_hash'] ?? ''),
                'summary' => (string)($artifact['human_diff_summary'] ?? $artifact['human_summary'] ?? ''),
            ];
        }

        usort($artifacts, static fn(array $a, array $b): int => strcmp((string)($a['artifact_id'] ?? ''), (string)($b['artifact_id'] ?? '')));
        $migrationPlan = is_array($compilePlan['migration_plan'] ?? null)
            ? $compilePlan['migration_plan']
            : (is_array($approvalPayload['migration_plan'] ?? null) ? $approvalPayload['migration_plan'] : []);
        $impactAnalysis = is_array($compilePlan['impact_analysis'] ?? null)
            ? $compilePlan['impact_analysis']
            : (is_array($approvalPayload['impact_analysis'] ?? null) ? $approvalPayload['impact_analysis'] : []);
        $simulationPreview = is_array($compilePlan['simulation_preview'] ?? null)
            ? $compilePlan['simulation_preview']
            : (is_array($approvalPayload['simulation_preview'] ?? null) ? $approvalPayload['simulation_preview'] : []);

        $snapshot = [
            'snapshot_id' => self::previewUuid('snapshot:' . $compileId . ':' . $approvalId),
            'compile_id' => $compileId,
            'approval_id' => $approvalId,
            'snapshot_hash' => '',
            'created_at' => gmdate('c'),
            'approval_valid' => true,
            'publish_approved' => !empty($approvalPayload['publish_approved']),
            'publish_gate_status' => (string)($approvalPayload['publish_gate_status'] ?? 'PENDING'),
            'publish_decision_id' => (string)($approvalPayload['publish_decision_id'] ?? ''),
            'approved_by' => (string)($approvalPayload['approved_by'] ?? ''),
            'approval_timestamp' => (string)($approvalPayload['approval_timestamp'] ?? $approvalPayload['approved_at'] ?? gmdate('c')),
            'artifacts' => $artifacts,
            'migration_plan' => $migrationPlan,
            'impact_analysis' => $impactAnalysis,
            'simulation_preview' => $simulationPreview,
            'risk_summary' => $riskSummary,
            'approval_summary' => self::summarizeApproval($approvalPayload),
            'integrity' => [
                'artifact_count' => count($artifacts),
                'hash_verified' => false,
            ],
        ];
        $snapshot['snapshot_hash'] = self::computeSnapshotHash($snapshot);
        $snapshot['integrity']['hash_verified'] = hash_equals($snapshot['snapshot_hash'], self::computeSnapshotHash($snapshot));

        return $snapshot;
    }

    /** @param array<string,mixed> $snapshot @return array<int,string> */
    private static function publishGatePreconditionFailures(array $snapshot): array
    {
        $failures = [];
        if (empty($snapshot['publish_approved'])) {
            $failures[] = 'publish_not_approved';
        }
        if (strtoupper(trim((string)($snapshot['publish_gate_status'] ?? ''))) !== 'PASSED') {
            $failures[] = 'publish_gate_not_passed';
        }
        if (trim((string)($snapshot['publish_decision_id'] ?? '')) === '') {
            $failures[] = 'publish_decision_missing';
        }
        if (trim((string)($snapshot['approved_by'] ?? '')) === '') {
            $failures[] = 'approved_by_missing';
        }
        if (trim((string)($snapshot['approval_timestamp'] ?? '')) === '') {
            $failures[] = 'approval_timestamp_missing';
        }
        return array_values(array_unique($failures));
    }

    /** @return array<string,mixed> */
    public static function simulateExecution(array $snapshot): array
    {
        $artifacts = is_array($snapshot['artifacts'] ?? null) ? $snapshot['artifacts'] : [];
        $approvalSummary = is_array($snapshot['approval_summary'] ?? null) ? $snapshot['approval_summary'] : [];
        $approvalValid = !empty($snapshot['approval_valid']);
        $approved = strtolower((string)($approvalSummary['decision'] ?? '')) === 'approved';
        $blockedItems = (int)($approvalSummary['blocked_count'] ?? 0);
        $canExecute = $approvalValid && $approved && $blockedItems === 0;
        $results = [];

        foreach ($artifacts as $artifact) {
            if (!is_array($artifact)) {
                continue;
            }

            $action = self::determineExecutionAction($artifact);
            if (!$canExecute && $action['action'] !== 'blocked') {
                $action = [
                    'action' => 'blocked',
                    'reason' => 'execution_precondition_failed',
                ];
            }

            $status = match ($action['action']) {
                'create', 'update' => 'ok',
                'skip' => 'skipped',
                default => 'blocked',
            };

            $results[] = [
                'artifact' => (string)($artifact['artifact_id'] ?? ''),
                'target_path' => (string)($artifact['target_path'] ?? ''),
                'artifact_type' => (string)($artifact['artifact_type'] ?? ''),
                'action' => $action['action'],
                'status' => $status,
                'reason' => $action['reason'],
            ];
        }

        $summary = self::summarizeExecution($results);
        $status = $canExecute ? 'SIMULATED' : 'BLOCKED';
        if ($canExecute && $summary['blocked'] > 0) {
            $status = 'BLOCKED';
        }

        return [
            'execution_id' => self::previewUuid('execution:' . (string)($snapshot['snapshot_id'] ?? '')),
            'snapshot_id' => (string)($snapshot['snapshot_id'] ?? ''),
            'compile_id' => (string)($snapshot['compile_id'] ?? ''),
            'approval_id' => (string)($snapshot['approval_id'] ?? ''),
            'status' => $status,
            'can_execute' => $canExecute && $summary['blocked'] === 0,
            'artifacts' => $artifacts,
            'results' => $results,
            'summary' => $summary,
        ];
    }

    /** @return array{total_artifacts:int,approved:bool,high_risk:int,blocked:int,hash:string} */
    public static function summarizeSnapshot(array $snapshot): array
    {
        $riskSummary = is_array($snapshot['risk_summary'] ?? null) ? $snapshot['risk_summary'] : [];
        $approvalSummary = is_array($snapshot['approval_summary'] ?? null) ? $snapshot['approval_summary'] : [];

        return [
            'total_artifacts' => (int)($snapshot['integrity']['artifact_count'] ?? count((array)($snapshot['artifacts'] ?? []))),
            'approved' => strtolower((string)($approvalSummary['decision'] ?? '')) === 'approved',
            'high_risk' => (int)($riskSummary['high'] ?? 0),
            'blocked' => (int)($riskSummary['blocked'] ?? 0),
            'hash' => (string)($snapshot['snapshot_hash'] ?? ''),
        ];
    }

    /** @param array<int,array<string,mixed>> $results @return array{total:int,ok:int,skipped:int,blocked:int} */
    public static function summarizeExecution(array $results): array
    {
        $summary = [
            'total' => 0,
            'ok' => 0,
            'skipped' => 0,
            'blocked' => 0,
        ];

        foreach ($results as $result) {
            if (!is_array($result)) {
                continue;
            }

            $summary['total']++;
            $status = strtolower((string)($result['status'] ?? 'blocked'));
            if ($status === 'ok') {
                $summary['ok']++;
                continue;
            }
            if ($status === 'skipped') {
                $summary['skipped']++;
                continue;
            }
            $summary['blocked']++;
        }

        return $summary;
    }

    /** @return array{action:string,reason:string} */
    private static function determineExecutionAction(array $artifact): array
    {
        $owner = strtolower((string)($artifact['owning_app'] ?? ''));
        $riskLevel = strtoupper((string)($artifact['risk_level'] ?? ''));
        $drift = strtolower((string)($artifact['drift_status'] ?? ''));
        $type = (string)($artifact['artifact_type'] ?? '');
        $changeType = strtolower((string)($artifact['change_type'] ?? ''));

        if ($owner === 'core' || str_starts_with(strtolower((string)($artifact['target_path'] ?? '')), 'app/')) {
            return ['action' => 'blocked', 'reason' => 'core_artifact_blocked'];
        }
        if ($riskLevel === 'BLOCKED') {
            return ['action' => 'blocked', 'reason' => 'blocked_risk_level'];
        }
        if ($drift === 'unmanaged') {
            return ['action' => 'skip', 'reason' => 'unmanaged_drift_skipped'];
        }
        if (!in_array($type, self::ARTIFACT_TYPES, true)) {
            return ['action' => 'skip', 'reason' => 'unsupported_artifact_type_skipped'];
        }
        if ($changeType === 'create') {
            return ['action' => 'create', 'reason' => 'simulation_create_only'];
        }
        if ($changeType === 'update' || $changeType === 'conflict') {
            return ['action' => 'update', 'reason' => 'simulation_update_only'];
        }

        return ['action' => 'skip', 'reason' => 'noop_or_unknown_change'];
    }

    private static function computeSnapshotHash(array $snapshot): string
    {
        $artifactHashes = [];
        $artifacts = is_array($snapshot['artifacts'] ?? null) ? $snapshot['artifacts'] : [];
        foreach ($artifacts as $artifact) {
            if (!is_array($artifact)) {
                continue;
            }
            $artifactHashes[] = (string)($artifact['content_hash'] ?? $artifact['after_hash'] ?? '');
        }

        return 'snapshot:' . hash('sha256', implode('|', array_merge([
            (string)($snapshot['compile_id'] ?? ''),
            (string)($snapshot['approval_id'] ?? ''),
        ], $artifactHashes)));
    }

    private static function loadStudioTemplateFile(string $filename): string
    {
        $path = rtrim((string)APP_ROOT, '/') . self::TEMPLATE_DIR . $filename;
        if (!is_file($path)) {
            return '';
        }
        return (string)file_get_contents($path);
    }

    private static function studlyPathSegment(string $value): string
    {
        $normalized = preg_replace('/[^a-z0-9]+/i', ' ', $value) ?? '';
        $parts = preg_split('/\s+/', trim($normalized)) ?: [];
        $studly = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $studly .= ucfirst(strtolower($part));
        }

        return $studly !== '' ? $studly : 'DraftApp';
    }

    /**
     * @param array<int,string> $dependencyTypes
     * @param array<string,array<string,mixed>> $decoded
     * @return array<string,mixed>
     */
    private static function normalizeArtifact(
        string $type,
        string $targetPath,
        string $template,
        array $dependencyTypes,
        string $summary,
        string $appKey,
        string $moduleKey,
        string $studioProjectId,
        string $studioDraftId,
        array $decoded
    ): array {
        $safeTarget = self::isSafeTargetPath($targetPath);
        $supported = in_array($type, self::ARTIFACT_TYPES, true);
        $targetExists = $safeTarget && (is_file(APP_ROOT . '/' . $targetPath) || is_dir(APP_ROOT . '/' . $targetPath));
        $ownershipScope = self::ownershipScope($targetPath, $type, $appKey, $moduleKey, $safeTarget, $supported, $targetExists);
        $customizationZone = self::customizationZone($type, $safeTarget, $supported);
        $driftStatus = self::driftStatus($targetExists, $safeTarget, $supported, $ownershipScope, $customizationZone);
        $artifactId = self::artifactId($type, $appKey, $moduleKey, '');
        $dependencies = array_map(
            static fn(string $dependencyType): string => self::artifactId($dependencyType, $appKey, $moduleKey, ''),
            $dependencyTypes
        );
        $missingDependency = self::hasMissingRequiredDraftDependency($type, $dependencyTypes, $appKey, $moduleKey);
        $hasHardConflict = !$safeTarget || !$supported || $ownershipScope === 'external' || $missingDependency;
        $changeType = $hasHardConflict || $targetExists ? 'conflict' : 'create';
        $riskLevel = self::escalatedRiskLevel($type, $safeTarget, $supported, $ownershipScope, $driftStatus, $missingDependency);
        $riskMeta = self::riskMeta($type, $riskLevel, $safeTarget, $supported, $ownershipScope, $driftStatus, $missingDependency);
        $diffStatus = self::diffStatus($safeTarget, $supported, $targetExists, $ownershipScope, $driftStatus, $missingDependency);
        $upgradeSafe = $riskLevel !== 'blocked' && $ownershipScope === 'managed' && $driftStatus === 'clean';
        $diffSummary = self::diffSummary($changeType, $driftStatus, $ownershipScope, $targetExists);
        $driftInterpretation = self::interpretDrift($driftStatus);
        $machineSummary = [
            'route_path' => (string)($decoded['view_definition']['view']['route_path'] ?? ''),
            'navigation_url' => (string)($decoded['navigation_definition']['navigation']['url'] ?? ''),
            'target_exists' => $targetExists,
            'safe_target_path' => $safeTarget,
            'supported_artifact_type' => $supported,
            'ownership_scope' => $ownershipScope,
            'customization_zone' => $customizationZone,
            'drift_status' => $driftStatus,
            'missing_dependency' => $missingDependency,
            'upgrade_safe' => $upgradeSafe,
            'risk_meta' => $riskMeta,
            'drift_interpretation' => $driftInterpretation,
        ];
        $hashSeed = json_encode([$artifactId, $targetPath, $template, $summary, $machineSummary], JSON_UNESCAPED_SLASHES);
        $afterHash = 'placeholder:after:' . substr(sha1((string)$hashSeed), 0, 16);
        $machineDiffSummary = [
            'artifact_id' => $artifactId,
            'before_hash' => $targetExists ? 'placeholder:before:target-exists' : 'placeholder:before:empty',
            'after_hash' => $afterHash,
            'drift_status' => $driftStatus,
            'change_type' => $changeType,
            'risk_level' => $riskLevel,
            'risk_meta' => $riskMeta,
        ];

        return [
            'artifact_id' => $artifactId,
            'artifact_type' => $type,
            'owning_app' => $appKey !== '' ? $appKey : 'draft_app',
            'owning_module' => $moduleKey !== '' ? $moduleKey : '',
            'ownership_scope' => $ownershipScope,
            'upgrade_safe' => $upgradeSafe,
            'customization_zone' => $customizationZone,
            'target_path' => $targetPath,
            'source_template' => $template,
            'template_version' => self::TEMPLATE_VERSION,
            'change_type' => $changeType,
            'risk_level' => $riskLevel,
            'risk_meta' => $riskMeta,
            'dependencies' => array_values($dependencies),
            'generated_by' => self::GENERATOR_ID,
            'studio_project_id' => $studioProjectId,
            'studio_draft_id' => $studioDraftId,
            'content_hash' => $afterHash,
            'before_hash' => $targetExists ? 'placeholder:before:target-exists' : 'placeholder:before:empty',
            'after_hash' => $afterHash,
            'target_exists' => $targetExists,
            'drift_status' => $driftStatus,
            'drift_interpretation' => $driftInterpretation,
            'diff_status' => $diffStatus,
            'diff_summary' => $diffSummary,
            'human_diff_summary' => $diffSummary,
            'machine_diff_summary' => $machineDiffSummary,
            'human_summary' => $summary,
            'machine_summary' => $machineSummary,
        ];
    }

    private static function artifactId(string $type, string $appKey, string $moduleKey, string $targetPath): string
    {
        $base = implode(':', array_filter([
            'artifact',
            $type,
            $appKey !== '' ? $appKey : 'draft_app',
            $moduleKey !== '' ? $moduleKey : null,
        ], static fn($value): bool => $value !== null && $value !== ''));

        if ($targetPath === '') {
            return $base;
        }

        return $base . ':' . substr(sha1($targetPath), 0, 10);
    }

    private static function isSafeTargetPath(string $targetPath): bool
    {
        if ($targetPath === '' || str_starts_with($targetPath, '/') || str_contains($targetPath, '\\') || str_contains($targetPath, '..')) {
            return false;
        }

        return str_starts_with($targetPath, 'apps/') || str_starts_with($targetPath, 'packages/dry-run/');
    }

    private static function defaultRiskLevel(string $type): string
    {
        return match ($type) {
            'navigation', 'translation' => 'low',
            'controller', 'service', 'permission', 'route', 'migration_file' => 'high',
            'package_manifest' => 'blocked',
            default => 'medium',
        };
    }

    private static function escalatedRiskLevel(
        string $type,
        bool $safeTarget,
        bool $supported,
        string $ownershipScope,
        string $driftStatus,
        bool $missingDependency
    ): string {
        if (!$safeTarget || !$supported || $missingDependency || $ownershipScope === 'external' || $driftStatus === 'conflict') {
            return 'blocked';
        }
        if ($ownershipScope === 'unmanaged' || $driftStatus === 'unmanaged' || $driftStatus === 'modified') {
            return 'blocked';
        }
        if ($ownershipScope === 'unknown' || $driftStatus === 'unknown') {
            return 'high';
        }

        return self::defaultRiskLevel($type);
    }

    private static function riskMeta(
        string $type,
        string $riskLevel,
        bool $safeTarget,
        bool $supported,
        string $ownershipScope,
        string $driftStatus,
        bool $missingDependency
    ): array {
        $reasons = [];
        $addReason = static function (array &$items, string $level, string $reason): void {
            $items[] = [
                'risk_level' => strtoupper($level),
                'risk_score' => self::riskScore($level),
                'reason' => $reason,
            ];
        };

        $addReason($reasons, $riskLevel, 'artifact_type:' . $type);
        if (!$safeTarget) {
            $addReason($reasons, 'blocked', 'unsafe_path');
        }
        if (!$supported) {
            $addReason($reasons, 'blocked', 'blocked_artifact_type');
        }
        if ($missingDependency) {
            $addReason($reasons, 'blocked', 'missing_dependency');
        }
        if ($ownershipScope === 'external') {
            $addReason($reasons, 'blocked', 'ownership_conflict');
        }
        if ($ownershipScope === 'unmanaged') {
            $addReason($reasons, 'blocked', 'unmanaged_target');
        }
        if ($driftStatus === 'modified') {
            $addReason($reasons, 'blocked', 'modified_generated_artifact');
        }
        if ($driftStatus === 'external') {
            $addReason($reasons, 'blocked', 'external_artifact');
        }
        if ($driftStatus === 'conflict') {
            $addReason($reasons, 'blocked', 'drift_conflict');
        }
        if ($ownershipScope === 'unknown' || $driftStatus === 'unknown') {
            $addReason($reasons, 'high', 'unknown_ownership_or_drift');
        }

        usort($reasons, static fn(array $a, array $b): int => (int)($b['risk_score'] ?? 0) <=> (int)($a['risk_score'] ?? 0));
        $dominant = is_array($reasons[0] ?? null) ? $reasons[0] : ['risk_level' => strtoupper($riskLevel), 'risk_score' => self::riskScore($riskLevel), 'reason' => 'artifact_type:' . $type];

        return [
            'risk_level' => (string)$dominant['risk_level'],
            'risk_score' => (int)$dominant['risk_score'],
            'dominant_reason' => (string)$dominant['reason'],
            'reasons' => $reasons,
        ];
    }

    private static function riskScore(string $riskLevel): int
    {
        return match (strtolower($riskLevel)) {
            'blocked' => 100,
            'high' => 70,
            'medium' => 40,
            default => 10,
        };
    }

    /** @return array<string,mixed> */
    private static function approvalArtifactSummary(array $artifact): array
    {
        return [
            'artifact_id' => (string)($artifact['artifact_id'] ?? ''),
            'artifact_type' => (string)($artifact['artifact_type'] ?? ''),
            'owning_app' => (string)($artifact['owning_app'] ?? ''),
            'owning_module' => (string)($artifact['owning_module'] ?? ''),
            'target_path' => (string)($artifact['target_path'] ?? ''),
            'risk_level' => strtoupper((string)($artifact['risk_level'] ?? '')),
            'dominant_reason' => (string)($artifact['risk_meta']['dominant_reason'] ?? ''),
            'summary' => (string)($artifact['human_diff_summary'] ?? $artifact['human_summary'] ?? ''),
        ];
    }

    private static function approvalId(string $compileId, string $approvedBy, string $decision, string $reason): string
    {
        $hash = sha1(json_encode([$compileId, $approvedBy, $decision, $reason], JSON_UNESCAPED_SLASHES) ?: '');
        return self::uuidFromHash($hash);
    }

    private static function previewUuid(string $seed): string
    {
        return self::uuidFromHash(sha1($seed . ':' . bin2hex(random_bytes(8))));
    }

    private static function uuidFromHash(string $hash): string
    {
        return sprintf(
            '%s-%s-5%s-%s%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 13, 3),
            dechex((hexdec($hash[16]) & 0x3) | 0x8),
            substr($hash, 17, 3),
            substr($hash, 20, 12)
        );
    }

    private static function interpretDrift(string $driftStatus): array
    {
        return match ($driftStatus) {
            'clean' => ['origin' => 'studio', 'control' => 'managed', 'state' => 'clean'],
            'modified' => ['origin' => 'runtime', 'control' => 'managed', 'state' => 'diverged'],
            'external' => ['origin' => 'runtime', 'control' => 'unmanaged', 'state' => 'diverged'],
            'unmanaged' => ['origin' => 'unknown', 'control' => 'unmanaged', 'state' => 'unknown'],
            'conflict' => ['origin' => 'mixed', 'control' => 'managed', 'state' => 'conflict'],
            default => ['origin' => 'unknown', 'control' => 'unmanaged', 'state' => 'unknown'],
        };
    }

    private static function customizationZone(string $type, bool $safeTarget, bool $supported): string
    {
        if (!$safeTarget || !$supported) {
            return 'none';
        }

        return match ($type) {
            'migration_file', 'package_manifest', 'permission' => 'protected',
            'view', 'translation' => 'user_editable',
            default => 'generated',
        };
    }

    private static function ownershipScope(
        string $targetPath,
        string $type,
        string $appKey,
        string $moduleKey,
        bool $safeTarget,
        bool $supported,
        bool $targetExists
    ): string {
        if (!$safeTarget || !$supported) {
            return 'unknown';
        }
        if (!self::targetOwnedByScope($targetPath, $appKey, $moduleKey)) {
            return 'external';
        }
        if ($targetExists && in_array(self::customizationZone($type, $safeTarget, $supported), ['user_editable'], true)) {
            return 'unmanaged';
        }

        return 'managed';
    }

    private static function driftStatus(
        bool $targetExists,
        bool $safeTarget,
        bool $supported,
        string $ownershipScope,
        string $customizationZone
    ): string {
        if (!$safeTarget || !$supported) {
            return 'conflict';
        }
        if ($ownershipScope === 'external') {
            return 'external';
        }
        if ($ownershipScope === 'unmanaged') {
            return 'unmanaged';
        }
        if (!$targetExists) {
            return 'clean';
        }
        if (in_array($customizationZone, ['generated', 'protected'], true)) {
            return 'modified';
        }

        return 'unknown';
    }

    private static function diffStatus(
        bool $safeTarget,
        bool $supported,
        bool $targetExists,
        string $ownershipScope,
        string $driftStatus,
        bool $missingDependency
    ): string {
        if (!$safeTarget || !$supported || $ownershipScope === 'external' || $missingDependency || in_array($driftStatus, ['conflict', 'modified', 'unmanaged', 'external'], true)) {
            return 'conflict-ready';
        }
        if ($targetExists || $driftStatus === 'unknown') {
            return 'unavailable';
        }

        return 'pending';
    }

    private static function diffSummary(string $changeType, string $driftStatus, string $ownershipScope, bool $targetExists): string
    {
        if ($changeType === 'conflict') {
            return 'Diff preview requires human review because ownership or drift checks found a conflict.';
        }
        if ($targetExists) {
            return 'Target exists; future diff preview must compare the current target with the planned artifact.';
        }
        if ($ownershipScope === 'managed' && $driftStatus === 'clean') {
            return 'New managed artifact target is ready for future diff preview.';
        }

        return 'Diff preview metadata is available, but visual diff remains locked for a later phase.';
    }

    /** @param array<int,string> $dependencyTypes */
    private static function hasMissingRequiredDraftDependency(string $type, array $dependencyTypes, string $appKey, string $moduleKey): bool
    {
        if (in_array('app', $dependencyTypes, true) && $appKey === '') {
            return true;
        }
        if (in_array('module', $dependencyTypes, true) && $moduleKey === '') {
            return true;
        }

        return $type === 'package_manifest' && ($appKey === '' || $moduleKey === '');
    }

    /** @param array<int,array<string,mixed>> $artifacts */
    private static function dependencyChecks(array $artifacts, string $appKey, string $moduleKey): array
    {
        $ids = array_fill_keys(array_map(static fn(array $artifact): string => (string)$artifact['artifact_id'], $artifacts), true);
        $checks = [
            self::dependencyCheck('module_requires_app', $appKey !== '', 'Module layer requires app metadata.'),
            self::dependencyCheck('view_requires_module', $moduleKey !== '', 'View layer requires module metadata.'),
            self::dependencyCheck('navigation_requires_route_view', self::hasType($artifacts, 'route') && self::hasType($artifacts, 'view'), 'Navigation requires route and view targets.'),
            self::dependencyCheck('permission_requires_surface_view_action', self::hasType($artifacts, 'view') && self::hasType($artifacts, 'permission'), 'Permission requires view/action target metadata.'),
            self::dependencyCheck('package_manifest_requires_app_module', $appKey !== '' && $moduleKey !== '', 'Package manifest requires app and module metadata.'),
        ];

        foreach ($artifacts as $artifact) {
            foreach ((array)($artifact['dependencies'] ?? []) as $dependencyId) {
                $checks[] = self::dependencyCheck(
                    'artifact_dependency:' . (string)$artifact['artifact_id'],
                    isset($ids[(string)$dependencyId]),
                    'Artifact dependency ' . (string)$dependencyId . ' is declared.'
                );
            }
        }

        return $checks;
    }

    /** @param array<int,array<string,mixed>> $artifacts */
    private static function conflictChecks(array $artifacts, string $appKey, string $moduleKey): array
    {
        $checks = [];
        foreach ($artifacts as $artifact) {
            $type = (string)($artifact['artifact_type'] ?? '');
            $targetPath = (string)($artifact['target_path'] ?? '');
            $checks[] = self::conflictCheck('target_path_exists', !(bool)($artifact['target_exists'] ?? false), $artifact, 'Target path already exists.');
            $checks[] = self::conflictCheck('unsafe_target_path', self::isSafeTargetPath($targetPath), $artifact, 'Target path is safe and inside an allowed dry-run root.');
            $checks[] = self::conflictCheck('unsupported_artifact_type', in_array($type, self::ARTIFACT_TYPES, true), $artifact, 'Artifact type is supported.');
            $checks[] = self::conflictCheck('target_owned_by_declared_scope', self::targetOwnedByScope($targetPath, $appKey, $moduleKey), $artifact, 'Target path stays inside declared app/module ownership.');
            $checks[] = self::conflictCheck('missing_dependency', empty(array_filter((array)($artifact['dependencies'] ?? []), static fn($dep): bool => trim((string)$dep) === '')), $artifact, 'Dependencies are explicit.');
            $checks[] = self::conflictCheck('ownership_scope_managed', (string)($artifact['ownership_scope'] ?? 'unknown') === 'managed', $artifact, 'Artifact has managed Studio ownership scope.');
            $checks[] = self::conflictCheck('unmanaged_target', (string)($artifact['ownership_scope'] ?? '') !== 'unmanaged', $artifact, 'Target is not an unmanaged user-editable artifact.');
            $checks[] = self::conflictCheck('external_artifact', (string)($artifact['ownership_scope'] ?? '') !== 'external', $artifact, 'Target is not owned by another app/module scope.');
            $checks[] = self::conflictCheck('modified_generated_artifact', (string)($artifact['drift_status'] ?? '') !== 'modified', $artifact, 'Generated artifact is not modified from Studio ownership metadata.');
        }

        return $checks;
    }

    private static function dependencyCheck(string $key, bool $pass, string $summary): array
    {
        return [
            'check_key' => $key,
            'pass' => $pass,
            'status' => $pass ? 'pass' : 'missing_dependency',
            'summary' => $summary,
        ];
    }

    /** @param array<string,mixed> $artifact */
    private static function conflictCheck(string $key, bool $pass, array $artifact, string $summary): array
    {
        return [
            'check_key' => $key,
            'artifact_id' => (string)($artifact['artifact_id'] ?? ''),
            'target_path' => (string)($artifact['target_path'] ?? ''),
            'pass' => $pass,
            'status' => $pass ? 'pass' : 'conflict',
            'summary' => $summary,
        ];
    }

    private static function normalizeConflicts(array $conflicts): array
    {
        $grouped = [];
        $total = 0;

        foreach ($conflicts as $conflict) {
            if (!is_array($conflict) || !empty($conflict['pass'])) {
                continue;
            }

            $total++;
            $type = self::conflictType((string)($conflict['check_key'] ?? 'unknown_conflict'));
            $artifactId = (string)($conflict['artifact_id'] ?? '');
            if (!isset($grouped[$type])) {
                $grouped[$type] = [
                    'type' => $type,
                    'count' => 0,
                    'artifacts' => [],
                ];
            }
            if ($artifactId !== '' && !in_array($artifactId, $grouped[$type]['artifacts'], true)) {
                $grouped[$type]['artifacts'][] = $artifactId;
            }
        }

        foreach ($grouped as &$group) {
            $group['count'] = count($group['artifacts']);
        }
        unset($group);

        ksort($grouped);

        return [
            'total_instances' => $total,
            'unique_conflicts' => count($grouped),
            'grouped' => array_values($grouped),
        ];
    }

    private static function conflictType(string $checkKey): string
    {
        return match ($checkKey) {
            'target_owned_by_declared_scope', 'ownership_scope_managed', 'external_artifact' => 'ownership_conflict',
            'target_path_exists', 'modified_generated_artifact', 'unmanaged_target' => 'drift_conflict',
            'unsafe_target_path' => 'unsafe_target_path',
            'unsupported_artifact_type' => 'unsupported_artifact_type',
            'missing_dependency' => 'missing_dependency',
            default => $checkKey !== '' ? $checkKey : 'unknown_conflict',
        };
    }

    /** @param array<int,array<string,mixed>> $artifacts */
    private static function driftChecks(array $artifacts): array
    {
        $checks = [];
        foreach ($artifacts as $artifact) {
            $status = (string)($artifact['drift_status'] ?? 'unknown');
            $checks[] = [
                'check_key' => 'drift_status',
                'artifact_id' => (string)($artifact['artifact_id'] ?? ''),
                'target_path' => (string)($artifact['target_path'] ?? ''),
                'pass' => $status === 'clean',
                'status' => $status,
                'summary' => self::driftSummary($status),
            ];
        }

        return $checks;
    }

    private static function driftSummary(string $status): string
    {
        return match ($status) {
            'clean' => 'No existing target or ownership drift detected.',
            'modified' => 'Generated or protected target already exists and needs human diff review.',
            'external' => 'Target path is outside the declared app/module ownership scope.',
            'conflict' => 'Target cannot be classified because path or artifact type is blocked.',
            'unmanaged' => 'Target exists in a user-editable zone without Studio ownership metadata.',
            default => 'Drift status is unknown until ownership metadata is available.',
        };
    }

    /**
     * @param array<int,array<string,mixed>> $artifacts
     * @param array<int,array<string,mixed>> $dependencyChecks
     * @param array<int,array<string,mixed>> $conflictChecks
     * @param array<int,array<string,mixed>> $driftChecks
     * @return array<string,array<int,string>>
     */
    private static function summaryGroups(array $artifacts, array $dependencyChecks, array $conflictChecks, array $driftChecks): array
    {
        $groups = [
            'added_artifacts' => [],
            'changed_artifacts' => [],
            'noop_artifacts' => [],
            'conflicts' => [],
            'blocked_items' => [],
            'ownership_warnings' => [],
            'drift_warnings' => [],
        ];

        foreach ($artifacts as $artifact) {
            $summary = (string)($artifact['artifact_id'] ?? '') . ' -> ' . (string)($artifact['target_path'] ?? '');
            $changeType = (string)($artifact['change_type'] ?? '');
            if ($changeType === 'create') {
                $groups['added_artifacts'][] = $summary;
            } elseif ($changeType === 'update') {
                $groups['changed_artifacts'][] = $summary;
            } elseif ($changeType === 'noop') {
                $groups['noop_artifacts'][] = $summary;
            } elseif ($changeType === 'conflict') {
                $groups['conflicts'][] = $summary;
            }

            if ((string)($artifact['risk_level'] ?? '') === 'blocked') {
                $groups['blocked_items'][] = $summary;
            }
            if ((string)($artifact['ownership_scope'] ?? 'unknown') !== 'managed') {
                $groups['ownership_warnings'][] = $summary . ' ownership=' . (string)($artifact['ownership_scope'] ?? 'unknown');
            }
            if ((string)($artifact['drift_status'] ?? 'unknown') !== 'clean') {
                $groups['drift_warnings'][] = $summary . ' drift=' . (string)($artifact['drift_status'] ?? 'unknown');
            }
        }

        foreach ([$dependencyChecks, $conflictChecks, $driftChecks] as $checkSet) {
            foreach ($checkSet as $check) {
                if (!empty($check['pass'])) {
                    continue;
                }
                $groups['conflicts'][] = (string)($check['check_key'] ?? 'check') . ': ' . (string)($check['summary'] ?? '');
            }
        }

        return $groups;
    }

    /**
     * @param array<int,array<string,mixed>> $artifacts
     * @param array<int,array<string,mixed>> $dependencyChecks
     * @param array<int,array<string,mixed>> $conflictChecks
     * @param array<int,array<string,mixed>> $driftChecks
     * @return array<string,mixed>
     */
    private static function compileSnapshot(
        string $studioProjectId,
        string $studioDraftId,
        array $artifacts,
        array $dependencyChecks,
        array $conflictChecks,
        array $driftChecks
    ): array {
        $bundleSeed = [
            'schema_version' => 'studio.compile-plan.v1',
            'generated_by' => self::GENERATOR_ID,
            'studio_project_id' => $studioProjectId,
            'studio_draft_id' => $studioDraftId,
            'artifacts' => array_map(static fn(array $artifact): array => [
                'artifact_id' => (string)($artifact['artifact_id'] ?? ''),
                'artifact_type' => (string)($artifact['artifact_type'] ?? ''),
                'target_path' => (string)($artifact['target_path'] ?? ''),
                'change_type' => (string)($artifact['change_type'] ?? ''),
                'risk_level' => (string)($artifact['risk_level'] ?? ''),
                'drift_status' => (string)($artifact['drift_status'] ?? ''),
                'after_hash' => (string)($artifact['after_hash'] ?? ''),
            ], $artifacts),
        ];
        $bundleHash = 'placeholder:bundle:' . substr(hash('sha256', (string)json_encode($bundleSeed, JSON_UNESCAPED_SLASHES)), 0, 24);

        return [
            'schema_version' => 'studio.compile-plan.v1',
            'compile_id' => 'compile:' . substr(sha1($bundleHash), 0, 16),
            'bundle_hash' => $bundleHash,
            'artifact_count' => count($artifacts),
            'dependency_check_count' => count($dependencyChecks),
            'conflict_check_count' => count($conflictChecks),
            'drift_check_count' => count($driftChecks),
            'generated_at' => 'deterministic:dry-run-not-persisted',
        ];
    }

    /** @param array<string,mixed> $artifact */
    private static function diffOwnershipFilter(array $artifact): string
    {
        if ((string)($artifact['ownership_scope'] ?? '') === 'external') {
            return 'EXTERNAL';
        }
        $template = strtolower((string)($artifact['source_template'] ?? ''));
        $owningApp = strtolower((string)($artifact['owning_app'] ?? ''));
        if ($template === 'system_app' || in_array($owningApp, ['platform', 'shell', 'odarehub'], true)) {
            return 'SYSTEM';
        }

        return 'APP';
    }

    private static function diffSymbol(string $changeType, string $risk): string
    {
        if ($risk === 'BLOCKED') {
            return '✕';
        }
        if ($risk === 'HIGH') {
            return '!';
        }

        return match ($changeType) {
            'UPDATE', 'CONFLICT' => '~',
            default => '+',
        };
    }

    private static function displayName(string $key): string
    {
        $normalized = preg_replace('/[^a-z0-9]+/i', ' ', $key) ?? '';
        $parts = preg_split('/\s+/', trim($normalized)) ?: [];
        $name = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $name .= ucfirst(strtolower($part));
        }

        return $name !== '' ? $name : 'Draft';
    }

    /** @param array<int,array<string,mixed>> $artifacts */
    private static function hasType(array $artifacts, string $type): bool
    {
        foreach ($artifacts as $artifact) {
            if (($artifact['artifact_type'] ?? '') === $type) {
                return true;
            }
        }

        return false;
    }

    private static function targetOwnedByScope(string $targetPath, string $appKey, string $moduleKey): bool
    {
        $appSegment = self::studlyPathSegment($appKey !== '' ? $appKey : 'DraftApp');
        if (str_starts_with($targetPath, 'packages/dry-run/')) {
            return true;
        }
        if (!str_starts_with($targetPath, 'apps/' . $appSegment . '/')) {
            return false;
        }
        if ($moduleKey === '' || !str_contains($targetPath, '/modules/')) {
            return true;
        }

        return str_contains($targetPath, '/modules/' . $moduleKey . '/');
    }

    /** @param array<string,array<string,mixed>> $decoded */
    private static function studioProjectId(array $decoded, string $appKey, string $moduleKey): string
    {
        $draftId = trim((string)($decoded['app_manifest']['audit']['draft_id'] ?? ''));
        if ($draftId !== '') {
            return $draftId;
        }

        return 'studio_project:' . substr(sha1(($appKey !== '' ? $appKey : 'draft_app') . ':' . ($moduleKey !== '' ? $moduleKey : 'draft_module')), 0, 12);
    }

    /** @param array<string,array<string,mixed>> $decoded */
    private static function studioDraftId(array $decoded, string $studioProjectId): string
    {
        $draftId = trim((string)($decoded['app_manifest']['audit']['draft_id'] ?? ''));
        if ($draftId !== '') {
            return $draftId;
        }

        return 'studio_draft:' . substr(sha1($studioProjectId . ':draft'), 0, 12);
    }

    private static function surfaceArtifactType(string $moduleTemplate): string
    {
        return $moduleTemplate === 'queue_workflow_module' ? 'workflow_surface' : 'dashboard_surface';
    }

    /** @param array<string,array<string,mixed>> $decoded */
    private static function inferAppTemplate(array $decoded): string
    {
        $taxonomy = strtolower(trim((string)($decoded['app_manifest']['app']['app_taxonomy'] ?? '')));
        return $taxonomy === 'system_app' ? 'system_app' : 'business_app';
    }

    /** @param array<string,array<string,mixed>> $decoded */
    private static function inferModuleTemplate(array $decoded): string
    {
        $type = strtolower(trim((string)($decoded['module_manifest']['module']['module_type'] ?? '')));
        return match ($type) {
            'dashboard' => 'dashboard_module',
            'queue', 'workflow', 'queue_workflow' => 'queue_workflow_module',
            default => 'crud_module',
        };
    }

    /** @param array<string,array<string,mixed>> $decoded */
    private static function inferViewTemplate(array $decoded): string
    {
        $kind = strtolower(trim((string)($decoded['view_definition']['view']['view_kind'] ?? $decoded['view_definition']['layout']['kind'] ?? '')));
        return match ($kind) {
            'form' => 'form_view',
            'detail' => 'detail_view',
            default => 'table_view',
        };
    }

    /** @param array<string,array<string,mixed>> $decoded @return array<string,mixed> */
    public static function buildGeneratedApplyPlan(array $decoded): array
    {
        $context = self::generatedApplyContext($decoded);
        $parentCompileId = self::findGeneratedParentCompileId($context);
        if ($context['errors'] !== []) {
            return [
                'schema_version' => 'studio.generated-apply-plan.v1',
                'generated_by' => self::FIRST_APPLY_GENERATOR_ID,
                'compile_meta' => [
                    'compile_id' => 'compile:generated:first-apply:invalid',
                    'parent_compile_id' => $parentCompileId,
                    'bundle_hash' => 'generated:first-apply:invalid',
                    'schema_version' => 'v1',
                ],
                'compile_snapshot' => [
                    'compile_id' => 'compile:generated:first-apply:invalid',
                    'bundle_hash' => 'generated:first-apply:invalid',
                ],
                'artifacts' => [],
                'generated_context' => $context,
            ];
        }

        $appKey = (string)$context['app_key'];
        $moduleKey = (string)$context['module_key'];
        $basePath = (string)$context['relative_path'];
        $routePath = (string)$context['route_path'];
        $templates = (array)$context['templates'];
        $artifacts = [
            self::generatedApplyArtifact('app', $appKey, $moduleKey, $basePath . '/manifest.json', (string)$templates['app'], 'medium', 'Generated app/module manifest for controlled Studio output.'),
            self::generatedApplyArtifact('module', $appKey, $moduleKey, $basePath . '/module.json', (string)$templates['module'], 'medium', 'Generated module contract for controlled Studio output.'),
            self::generatedApplyArtifact('route', $appKey, $moduleKey, $basePath . '/routes.php', (string)$templates['app'], 'high', 'Generated route registration contract for ' . $routePath . '.'),
            self::generatedApplyArtifact('controller', $appKey, $moduleKey, $basePath . '/Controllers/' . (string)$context['controller_class'] . '.php', (string)$templates['module'], 'high', 'Generated safe controller placeholder for the module route.'),
            self::generatedApplyArtifact('service', $appKey, $moduleKey, $basePath . '/Providers/' . (string)$context['provider_class'] . '.php', (string)$templates['module'], 'high', 'Generated provider for validation and JSON-backed data access.'),
            self::generatedApplyArtifact('view', $appKey, $moduleKey, $basePath . '/Views/' . (string)$context['view_file'], (string)$templates['view'], 'medium', 'Generated read-only placeholder view for first apply.'),
            self::generatedApplyArtifact('navigation', $appKey, $moduleKey, $basePath . '/navigation.php', 'navigation_entry', 'low', 'Generated navigation contract for the admin sidebar.'),
        ];

        $hashArtifacts = array_map(static fn(array $artifact): array => [
            'artifact_id' => (string)($artifact['artifact_id'] ?? ''),
            'artifact_type' => (string)($artifact['artifact_type'] ?? ''),
            'owning_app' => (string)($artifact['owning_app'] ?? ''),
            'owning_module' => (string)($artifact['owning_module'] ?? ''),
            'target_path' => (string)($artifact['target_path'] ?? ''),
            'source_template' => (string)($artifact['source_template'] ?? ''),
            'template_version' => (string)($artifact['template_version'] ?? ''),
            'change_type' => (string)($artifact['change_type'] ?? ''),
            'risk_level' => (string)($artifact['risk_level'] ?? ''),
        ], $artifacts);
        $hashSeed = json_encode([$context, $hashArtifacts], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
        $bundleHash = 'generated:first-apply:' . hash('sha256', $hashSeed);
        $compileId = 'compile:generated:' . substr(hash('sha256', $bundleHash), 0, 16);

        return [
            'schema_version' => 'studio.generated-apply-plan.v1',
            'generated_by' => self::FIRST_APPLY_GENERATOR_ID,
            'studio_project_id' => 'studio_project:generated:' . $appKey,
            'studio_draft_id' => 'studio_draft:generated:' . $appKey . ':' . $moduleKey,
            'compile_meta' => [
                'compile_id' => $compileId,
                'parent_compile_id' => $parentCompileId,
                'bundle_hash' => $bundleHash,
                'schema_version' => 'v1',
            ],
            'compile_snapshot' => [
                'compile_id' => $compileId,
                'bundle_hash' => $bundleHash,
                'artifact_count' => count($artifacts),
                'schema_version' => 'studio.generated-apply-plan.v1',
            ],
            'artifacts' => $artifacts,
            'generated_context' => $context,
        ];
    }

    /** @param array<string,mixed> $snapshot @param array<string,array<string,mixed>> $decoded @return array<string,mixed> */
    public static function applyGeneratedSnapshot(array $snapshot, array $decoded): array
    {
        $context = self::generatedApplyContext($decoded);
        $snapshot = self::bindGeneratedSnapshotContext($snapshot, $context);
        $applyId = self::previewUuid('first-apply:' . (string)($snapshot['snapshot_id'] ?? '') . ':' . (string)($context['route_path'] ?? ''));
        $startedAt = gmdate('c');
        $compileId = (string)($snapshot['compile_id'] ?? '');
        $tmpRelativePath = self::generatedTempRelativePath($compileId);
        $backupRelativePath = self::generatedTempRelativePath('apply_backup_' . $compileId);
        $tmpAbsolutePath = self::absolutePath($tmpRelativePath);
        $backupAbsolutePath = self::absolutePath($backupRelativePath);
        $liveRelativePath = (string)($context['relative_path'] ?? '');
        $liveAbsolutePath = self::absolutePath($liveRelativePath);
        $approvalSummary = is_array($snapshot['approval_summary'] ?? null) ? $snapshot['approval_summary'] : [];
        $snapshotHash = (string)($snapshot['snapshot_hash'] ?? '');
        $hashVerified = $snapshotHash !== '' && hash_equals($snapshotHash, self::computeSnapshotHash($snapshot));
        $preconditions = $context['errors'];
        $publishFailures = self::publishGatePreconditionFailures($snapshot);
        $liveManifest = self::loadGeneratedLiveManifest($liveRelativePath);
        $liveCompileId = (string)($liveManifest['compile_id'] ?? '');
        $sameGeneratedTarget = $liveManifest !== []
            && (string)($liveManifest['generator_id'] ?? '') === self::FIRST_APPLY_GENERATOR_ID
            && (string)($liveManifest['app_key'] ?? '') === (string)($context['app_key'] ?? '')
            && (string)($liveManifest['module_key'] ?? '') === (string)($context['module_key'] ?? '');

        if (empty($snapshot['approval_valid'])) {
            $preconditions[] = 'approval_invalid';
        }
        if (strtolower((string)($approvalSummary['decision'] ?? '')) !== 'approved') {
            $preconditions[] = 'decision_not_approved';
        }
        if ((int)($approvalSummary['blocked_count'] ?? 0) > 0) {
            $preconditions[] = 'blocked_items_present';
        }
        if (!$hashVerified) {
            $preconditions[] = 'snapshot_hash_mismatch';
        }
        foreach ($publishFailures as $failure) {
            $preconditions[] = $failure;
        }
        if ($liveCompileId !== '' && $liveCompileId === $compileId) {
            $preconditions[] = 'snapshot_already_applied';
        }
        if ((is_dir($liveAbsolutePath) || is_file($liveAbsolutePath)) && !$sameGeneratedTarget) {
            $preconditions[] = 'upgrade_required';
        }
        if (self::generatedRouteExists((string)($context['route_path'] ?? ''), $liveRelativePath)) {
            $preconditions[] = 'route_conflict';
        }

        if ($preconditions !== []) {
            $apply = [
                'apply_id' => $applyId,
                'snapshot_id' => (string)($snapshot['snapshot_id'] ?? ''),
                'compile_id' => $compileId,
                'approval_id' => (string)($snapshot['approval_id'] ?? ''),
                'status' => 'FAILED',
                'started_at' => $startedAt,
                'completed_at' => gmdate('c'),
                'results' => [],
                'steps' => [],
                'integrity' => ['snapshot_hash' => $snapshotHash, 'verified' => $hashVerified],
                'precondition_failures' => array_values(array_unique($preconditions)),
                'generated_module' => [],
            ];
            self::persistApplyRecord($apply);
            self::appendApplyLog($apply, $context);
            return $apply;
        }

        self::deleteGeneratedTempTree($tmpRelativePath);
        self::deleteGeneratedTempTree($backupRelativePath);
        $files = self::generatedModuleFiles($context, $snapshot, $tmpRelativePath);
        $steps = [];
        $failed = false;
        foreach ($files as $relativePath => $content) {
            $writeResult = self::writeGeneratedTempFile((string)$relativePath, (string)$content);
            $written = $writeResult === 'created';
            $steps[] = [
                'artifact' => (string)$relativePath,
                'action' => 'create',
                'result' => $written ? 'applied' : 'failed',
                'message' => $written ? 'generated_file_' . $writeResult : 'generated_file_create_failed',
            ];
            if (!$written) {
                $failed = true;
                break;
            }
        }

        $tempValidation = $failed
            ? ['valid' => false, 'errors' => ['temp_generation_failed']]
            : self::validateGeneratedTempTree($tmpRelativePath);
        if (!$failed && empty($tempValidation['valid'])) {
            $failed = true;
            foreach ((array)($tempValidation['errors'] ?? []) as $error) {
                $steps[] = [
                    'artifact' => $tmpRelativePath,
                    'action' => 'validate',
                    'result' => 'failed',
                    'message' => (string)$error,
                ];
            }
        }

        $backupCreated = false;
        if (!$failed && is_dir($liveAbsolutePath)) {
            $backupParent = dirname($backupAbsolutePath);
            if (!is_dir($backupParent)) {
                mkdir($backupParent, 0775, true);
            }
            if (!rename($liveAbsolutePath, $backupAbsolutePath)) {
                $failed = true;
                $steps[] = [
                    'artifact' => $liveRelativePath,
                    'action' => 'backup_current',
                    'result' => 'failed',
                    'message' => 'current_module_backup_failed',
                ];
            } else {
                $backupCreated = true;
                $steps[] = [
                    'artifact' => $liveRelativePath,
                    'action' => 'backup_current',
                    'result' => 'applied',
                    'message' => 'current_module_backed_up',
                ];
            }
        }

        if (!$failed) {
            $parentDir = dirname($liveAbsolutePath);
            if ((file_exists($parentDir) && !is_dir($parentDir)) || (!is_dir($parentDir) && !mkdir($parentDir, 0775, true)) || !rename($tmpAbsolutePath, $liveAbsolutePath)) {
                $failed = true;
                $steps[] = [
                    'artifact' => $liveRelativePath,
                    'action' => 'commit',
                    'result' => 'failed',
                    'message' => 'live_move_failed',
                ];
                if ($backupCreated && !is_dir($liveAbsolutePath) && is_dir($backupAbsolutePath)) {
                    rename($backupAbsolutePath, $liveAbsolutePath);
                }
            } else {
                $steps[] = [
                    'artifact' => $liveRelativePath,
                    'action' => 'commit',
                    'result' => 'applied',
                    'message' => 'temp_moved_to_live',
                ];
                self::ensureGeneratedAppFiles($context);
            }
        }

        $snapshotPersisted = false;
        if (!$failed) {
            $snapshotPersisted = self::persistGeneratedSnapshot($snapshot, $context);
            if (!$snapshotPersisted) {
                self::deleteGeneratedLiveTree($liveRelativePath);
                $failed = true;
                $steps[] = [
                    'artifact' => self::snapshotPathForCompileId($compileId),
                    'action' => 'persist_snapshot',
                    'result' => 'failed',
                    'message' => 'snapshot_persist_failed',
                ];
            } else {
                $steps[] = [
                    'artifact' => self::snapshotPathForCompileId($compileId),
                    'action' => 'persist_snapshot',
                    'result' => 'applied',
                    'message' => 'snapshot_persisted',
                ];
            }
        }

        if ($failed) {
            self::deleteGeneratedTempTree($tmpRelativePath);
        } else {
            self::deleteGeneratedTempTree($backupRelativePath);
        }

        $status = $failed ? 'FAILED' : 'APPLIED';

        $apply = [
            'apply_id' => $applyId,
            'snapshot_id' => (string)($snapshot['snapshot_id'] ?? ''),
            'compile_id' => $compileId,
            'approval_id' => (string)($snapshot['approval_id'] ?? ''),
            'publish_approved' => !empty($snapshot['publish_approved']),
            'publish_gate_status' => (string)($snapshot['publish_gate_status'] ?? ''),
            'publish_decision_id' => (string)($snapshot['publish_decision_id'] ?? ''),
            'approved_by' => (string)($snapshot['approved_by'] ?? ''),
            'approval_timestamp' => (string)($snapshot['approval_timestamp'] ?? ''),
            'status' => $status,
            'started_at' => $startedAt,
            'completed_at' => gmdate('c'),
            'results' => array_map(static fn(array $step): array => [
                'artifact' => (string)($step['artifact'] ?? ''),
                'action' => (string)($step['action'] ?? ''),
                'status' => (string)($step['result'] ?? ''),
                'message' => (string)($step['message'] ?? ''),
            ], $steps),
            'steps' => $steps,
            'integrity' => ['snapshot_hash' => $snapshotHash, 'verified' => $hashVerified],
            'precondition_failures' => $failed ? ['apply_failed'] : [],
            'snapshot_persisted' => $snapshotPersisted,
            'generated_module' => [
                'app_key' => (string)$context['app_key'],
                'module_key' => (string)$context['module_key'],
                'route_path' => (string)$context['route_path'],
                'relative_path' => $liveRelativePath,
                'temp_path' => $tmpRelativePath,
            ],
        ];
        if ($status === 'APPLIED') {
            $apply['registry_updated'] = self::registerGeneratedAppVersion($context, $compileId, $snapshot);
            $apply['post_publish_verification'] = self::verifyPostPublishState($snapshot, $apply, $context);
        }
        self::persistApplyRecord($apply);
        self::appendApplyLog($apply, $context);
        return $apply;
    }

    /** @return array<string,mixed> */
    public static function rollbackGeneratedSnapshot(string $compileId): array
    {
        if (!self::acquireGeneratedRollbackLock()) {
            return [
                'rollback_id' => self::previewUuid('generated-rollback:' . $compileId . ':locked'),
                'compile_id' => $compileId,
                'snapshot_id' => '',
                'status' => 'FAILED',
                'started_at' => gmdate('c'),
                'completed_at' => gmdate('c'),
                'steps' => [],
                'summary' => ['total' => 0, 'done' => 0, 'failed' => 0, 'skipped' => 0],
                'precondition_failures' => ['rollback_in_progress'],
                'message' => 'rollback_blocked',
            ];
        }

        try {
        $snapshotRecord = self::loadGeneratedSnapshotByCompileId($compileId);
        $rollbackId = self::previewUuid('generated-rollback:' . $compileId . ':' . gmdate('c'));
        $startedAt = gmdate('c');
        $steps = [];
        $preconditions = [];

        if ($snapshotRecord === []) {
            $preconditions[] = 'snapshot_not_found';
        }

        $context = is_array($snapshotRecord['generated_context'] ?? null) ? $snapshotRecord['generated_context'] : [];
        $modulePaths = is_array($snapshotRecord['module_paths'] ?? null) ? $snapshotRecord['module_paths'] : [];
        $storedCompileId = (string)($snapshotRecord['compile_id'] ?? $compileId);
        $snapshotId = (string)($snapshotRecord['snapshot_id'] ?? '');
        $generatorId = (string)($snapshotRecord['generated_by'] ?? '');
        $liveRelativePath = (string)($modulePaths['live_path'] ?? $context['relative_path'] ?? '');
        $liveAbsolutePath = self::absolutePath($liveRelativePath);
        $restoreTmpRelativePath = self::generatedTempRelativePath('rollback_restore_' . $storedCompileId);
        $backupTmpRelativePath = self::generatedTempRelativePath('rollback_backup_' . $storedCompileId);
        $restoreTmpAbsolutePath = self::absolutePath($restoreTmpRelativePath);
        $backupTmpAbsolutePath = self::absolutePath($backupTmpRelativePath);

        if ($snapshotRecord !== [] && $generatorId !== self::FIRST_APPLY_GENERATOR_ID) {
            $preconditions[] = 'generator_mismatch';
        }
        if (!is_array($context) || $context === [] || !empty($context['errors'])) {
            $preconditions[] = 'invalid_snapshot_context';
        }
        if (!self::isSafeGeneratedLivePath($liveRelativePath)) {
            $preconditions[] = 'unsafe_live_path';
        }
        if (!self::isSafeGeneratedRoutePath((string)($context['route_path'] ?? ''))) {
            $preconditions[] = 'unsafe_route_path';
        }

        if ($preconditions !== []) {
            $result = [
                'rollback_id' => $rollbackId,
                'compile_id' => $storedCompileId,
                'snapshot_id' => $snapshotId,
                'status' => 'FAILED',
                'started_at' => $startedAt,
                'completed_at' => gmdate('c'),
                'steps' => [],
                'summary' => ['total' => 0, 'done' => 0, 'failed' => 0, 'skipped' => 0],
                'precondition_failures' => array_values(array_unique($preconditions)),
                'message' => 'rollback_blocked',
            ];
            self::appendStructuredApplyLog('rollback_failed', $result, $context);
            return $result;
        }

        self::deleteGeneratedTempTree($restoreTmpRelativePath);
        self::deleteGeneratedTempTree($backupTmpRelativePath);

        $files = self::snapshotModuleFilesForRestore($snapshotRecord, $restoreTmpRelativePath, $liveRelativePath);
        $failed = false;
        foreach ($files as $relativePath => $content) {
            $writeResult = self::writeGeneratedTempFile((string)$relativePath, (string)$content);
            $ok = $writeResult === 'created';
            $steps[] = [
                'artifact' => (string)$relativePath,
                'rollback_action' => 'restore',
                'reversible' => true,
                'result' => $ok ? 'done' : 'failed',
                'message' => $ok ? 'restore_file_staged' : 'restore_file_stage_failed',
            ];
            if (!$ok) {
                $failed = true;
                break;
            }
        }

        $tempValidation = $failed
            ? ['valid' => false, 'errors' => ['restore_stage_failed']]
            : self::validateGeneratedTempTree($restoreTmpRelativePath);
        if (!$failed && empty($tempValidation['valid'])) {
            $failed = true;
            foreach ((array)($tempValidation['errors'] ?? []) as $error) {
                $steps[] = [
                    'artifact' => $restoreTmpRelativePath,
                    'rollback_action' => 'validate',
                    'reversible' => true,
                    'result' => 'failed',
                    'message' => (string)$error,
                ];
            }
        }

        if (!$failed && $files === []) {
            $failed = true;
            $steps[] = [
                'artifact' => $restoreTmpRelativePath,
                'rollback_action' => 'validate',
                'reversible' => true,
                'result' => 'failed',
                'message' => 'snapshot_files_missing',
            ];
        }

        $backupCreated = false;
        if (!$failed && is_dir($liveAbsolutePath)) {
            $backupParent = dirname($backupTmpAbsolutePath);
            if (!is_dir($backupParent)) {
                mkdir($backupParent, 0775, true);
            }
            if (!rename($liveAbsolutePath, $backupTmpAbsolutePath)) {
                $failed = true;
                $steps[] = [
                    'artifact' => $liveRelativePath,
                    'rollback_action' => 'backup_current',
                    'reversible' => true,
                    'result' => 'failed',
                    'message' => 'current_module_backup_failed',
                ];
            } else {
                $backupCreated = true;
                $steps[] = [
                    'artifact' => $liveRelativePath,
                    'rollback_action' => 'backup_current',
                    'reversible' => true,
                    'result' => 'done',
                    'message' => 'current_module_backed_up',
                ];
            }
        }

        if (!$failed) {
            $parentDir = dirname($liveAbsolutePath);
            if ((file_exists($parentDir) && !is_dir($parentDir)) || (!is_dir($parentDir) && !mkdir($parentDir, 0775, true)) || !rename($restoreTmpAbsolutePath, $liveAbsolutePath)) {
                $failed = true;
                $steps[] = [
                    'artifact' => $liveRelativePath,
                    'rollback_action' => 'restore_snapshot',
                    'reversible' => true,
                    'result' => 'failed',
                    'message' => 'snapshot_restore_failed',
                ];
                if ($backupCreated && !is_dir($liveAbsolutePath) && is_dir($backupTmpAbsolutePath)) {
                    rename($backupTmpAbsolutePath, $liveAbsolutePath);
                }
            } else {
                $steps[] = [
                    'artifact' => $liveRelativePath,
                    'rollback_action' => 'restore_snapshot',
                    'reversible' => true,
                    'result' => 'done',
                    'message' => 'snapshot_restored',
                ];
            }
        }

        if ($failed) {
            self::deleteGeneratedTempTree($restoreTmpRelativePath);
        } else {
            self::deleteGeneratedTempTree($backupTmpRelativePath);
        }

        $summary = self::summarizeGeneratedRollbackSteps($steps);
        $result = [
            'rollback_id' => $rollbackId,
            'compile_id' => $storedCompileId,
            'snapshot_id' => $snapshotId,
            'status' => $failed ? 'FAILED' : 'COMPLETED',
            'started_at' => $startedAt,
            'completed_at' => gmdate('c'),
            'steps' => $steps,
            'summary' => $summary,
            'precondition_failures' => $failed ? ['rollback_failed'] : [],
            'message' => $failed ? 'rollback_failed' : 'rollback_completed',
            'generated_module' => [
                'app_key' => (string)($context['app_key'] ?? ''),
                'module_key' => (string)($context['module_key'] ?? ''),
                'route_path' => (string)($context['route_path'] ?? ''),
                'relative_path' => $liveRelativePath,
            ],
        ];
        if (!$failed) {
            self::registerGeneratedAppVersion($context, $storedCompileId, $snapshotRecord);
        }
        self::appendStructuredApplyLog($failed ? 'rollback_failed' : 'rollback_completed', $result, $context);
        return $result;
        } finally {
            self::releaseGeneratedRollbackLock();
        }
    }

    /** @return array<int,array<string,mixed>> */
    public static function generatedModuleDefinitions(bool $enabledOnly = true): array
    {
        $root = rtrim((string)(defined('APP_ROOT') ? APP_ROOT : ''), '/') . '/' . self::GENERATED_ROOT;
        if (!is_dir($root)) {
            return [];
        }
        $appRegistry = self::loadGeneratedAppRegistry();

        $definitions = [];
        foreach (glob($root . '/*/*/manifest.json') ?: [] as $manifestFile) {
            if (!is_file($manifestFile)) {
                continue;
            }
            if (basename(dirname(dirname($manifestFile))) === 'tmp') {
                continue;
            }
            $raw = @file_get_contents($manifestFile);
            if ($raw === false || $raw === '') {
                continue;
            }
            $manifest = json_decode($raw, true);
            if (!is_array($manifest) || (string)($manifest['schema_version'] ?? '') !== 'studio.generated-module.v1') {
                continue;
            }
            $generatedBy = strtolower(trim((string)($manifest['generated_by'] ?? '')));
            $managed = $generatedBy === 'studio';
            $appKey = self::generatedKey((string)($manifest['app_key'] ?? ''));
            $moduleKey = self::generatedKey((string)($manifest['module_key'] ?? ''));
            $routePath = trim((string)($manifest['route_path'] ?? ''));
            if ($appKey === '' || $moduleKey === '' || !self::isSafeGeneratedRoutePath($routePath)) {
                continue;
            }
            $registryEntry = is_array($appRegistry[$appKey] ?? null) ? $appRegistry[$appKey] : [];
            if ($enabledOnly) {
                if ((string)($registryEntry['status'] ?? '') !== 'enabled') {
                    continue;
                }
                if (empty($registryEntry['publish_approved'])) {
                    continue;
                }
                $registryModules = is_array($registryEntry['modules'] ?? null) ? $registryEntry['modules'] : [];
                if (!in_array($moduleKey, array_map('strval', $registryModules), true)) {
                    continue;
                }
            }
            $moduleDir = dirname($manifestFile);
            $viewFile = basename((string)($manifest['view_file'] ?? 'index.php'));
            if (!preg_match('/^[a-z0-9_\\-]+\\.php$/i', $viewFile)) {
                $viewFile = 'index.php';
            }
            $statusLabel = (string)($registryEntry['status'] ?? '');
            if (!$managed) {
                $statusLabel = 'unmanaged';
            } elseif ($statusLabel === 'enabled' && empty($registryEntry['publish_approved'])) {
                $statusLabel = 'pending_publish';
            }
            $definitions[] = [
                'app_key' => $appKey,
                'module_key' => $moduleKey,
                'display_name' => (string)($manifest['display_name'] ?? self::displayName($moduleKey)),
                'section' => (string)($manifest['navigation']['section'] ?? self::displayName($appKey)),
                'label' => (string)($manifest['navigation']['label'] ?? $manifest['display_name'] ?? self::displayName($moduleKey)),
                'route_path' => $routePath,
                'namespace' => 'generated_' . $appKey . '_' . $moduleKey,
                'views_path' => $moduleDir . '/Views',
                'view_file' => $viewFile,
                'manifest_path' => str_replace(rtrim((string)(defined('APP_ROOT') ? APP_ROOT : ''), '/') . '/', '', $manifestFile),
                'status' => $statusLabel,
                'current_version' => (string)($registryEntry['current_version'] ?? ''),
                'managed' => $managed,
                'origin' => $generatedBy !== '' ? $generatedBy : 'unmanaged',
                'publish_approved' => !empty($registryEntry['publish_approved']),
                'publish_decision_id' => (string)($registryEntry['publish_decision_id'] ?? ''),
                'fields' => self::normalizeGeneratedFields(is_array($manifest['fields'] ?? null) ? $manifest['fields'] : []),
            ];
        }

        usort($definitions, static fn(array $a, array $b): int => strcmp((string)$a['route_path'], (string)$b['route_path']));
        return $definitions;
    }

    /** @return array<string,mixed>|null */
    public static function generatedModuleDefinitionByKeys(string $appKey, string $moduleKey, bool $enabledOnly = true): ?array
    {
        $safeAppKey = self::generatedKey($appKey);
        $safeModuleKey = self::generatedKey($moduleKey);
        if ($safeAppKey === '' || $safeModuleKey === '') {
            return null;
        }

        foreach (self::generatedModuleDefinitions($enabledOnly) as $definition) {
            if (!is_array($definition)) {
                continue;
            }
            if ((string)($definition['app_key'] ?? '') === $safeAppKey && (string)($definition['module_key'] ?? '') === $safeModuleKey) {
                return $definition;
            }
        }

        return null;
    }

    /** @param array<string,mixed> $user */
    public static function resolveGeneratedRuntimeRole(array $user): string
    {
        $authorityRole = strtolower(trim((string)($user['authority_role'] ?? $user['account_type'] ?? '')));
        $role = strtolower(trim((string)($user['role'] ?? '')));

        if (in_array($authorityRole, ['platform_admin', 'app_admin', 'sysadmin'], true) || in_array($role, ['admin', 'manager'], true)) {
            return 'admin';
        }

        if (str_contains($authorityRole, 'dispatch') || str_contains($role, 'dispatch')) {
            return 'dispatch';
        }

        if (str_contains($authorityRole, 'qc') || str_contains($authorityRole, 'quality') || str_contains($role, 'qc') || str_contains($role, 'quality')) {
            return 'qc';
        }

        if (in_array($authorityRole, ['app_user', 'operator', 'leader'], true) || str_contains($authorityRole, 'operator') || str_contains($role, 'operator')) {
            return 'operator';
        }

        return 'read_only';
    }

    /** @return array<string,mixed> */
    public static function generatedRuntimeWorkflow(array $module = []): array
    {
        $sla = self::generatedRuntimeSlaConfig($module);
        return [
            'status_field' => 'status',
            'states' => ['draft', 'in_progress', 'completed', 'approved', 'dispatched'],
            'transitions' => [
                'draft' => ['in_progress'],
                'in_progress' => ['completed'],
                'completed' => ['approved'],
                'approved' => ['dispatched'],
                'dispatched' => [],
            ],
            'transition_roles' => [
                'draft->in_progress' => ['operator', 'admin'],
                'in_progress->completed' => ['admin'],
                'completed->approved' => ['qc', 'admin'],
                'approved->dispatched' => ['dispatch', 'admin'],
            ],
            'editable_roles' => ['operator', 'admin'],
            'max_time_per_state' => $sla,
        ];
    }

    public static function generatedRuntimeCanEdit(string $runtimeRole): bool
    {
        $role = strtolower(trim($runtimeRole));
        $workflow = self::generatedRuntimeWorkflow();
        $editableRoles = array_map('strval', (array)($workflow['editable_roles'] ?? []));
        return in_array($role, $editableRoles, true);
    }

    /** @return array<int,string> */
    public static function generatedRuntimeAllowedTransitions(string $runtimeRole, string $fromStatus): array
    {
        $role = strtolower(trim($runtimeRole));
        $from = self::normalizeGeneratedWorkflowStatus($fromStatus);
        $workflow = self::generatedRuntimeWorkflow();
        $transitions = is_array($workflow['transitions'] ?? null) ? $workflow['transitions'] : [];
        $transitionRoles = is_array($workflow['transition_roles'] ?? null) ? $workflow['transition_roles'] : [];
        $candidates = array_values(array_filter((array)($transitions[$from] ?? []), 'is_string'));

        $allowed = [];
        foreach ($candidates as $candidate) {
            $candidateState = self::normalizeGeneratedWorkflowStatus((string)$candidate);
            if ($candidateState === '' || $candidateState === $from) {
                continue;
            }
            $mapKey = $from . '->' . $candidateState;
            $roles = array_values(array_filter((array)($transitionRoles[$mapKey] ?? []), 'is_string'));
            if ($roles === [] || in_array($role, $roles, true)) {
                $allowed[] = $candidateState;
            }
        }

        return array_values(array_unique($allowed));
    }

    /**
     * Build runtime layout/components contract for generated module execution pages.
     *
     * @param array<string,mixed> $module
     * @param array<string,mixed> $runtimeData
     * @return array<string,mixed>
     */
    public static function generatedRuntimeContract(array $module, array $runtimeData): array
    {
        $fields = self::normalizeGeneratedFields(is_array($runtimeData['fields'] ?? null) ? $runtimeData['fields'] : []);
        $rows = is_array($runtimeData['rows'] ?? null) ? array_values(array_filter($runtimeData['rows'], 'is_array')) : [];
        $primaryField = (string)($fields[0]['key'] ?? '');

        $layout = [
            'type' => 'grid',
            'columns' => 12,
            'rows' => 'auto',
            'items' => [
                [
                    'id' => 'filter_1',
                    'component' => 'filter',
                    'x' => 0,
                    'y' => 0,
                    'w' => 3,
                    'h' => 3,
                    'group' => 'controls',
                    'props' => [
                        'label' => 'Filter',
                        'field' => $primaryField,
                        'placeholder' => 'Type to filter',
                    ],
                    'data_binding' => 'module.rows',
                ],
                [
                    'id' => 'kpi_card_1',
                    'component' => 'kpi_card',
                    'x' => 3,
                    'y' => 0,
                    'w' => 3,
                    'h' => 3,
                    'group' => 'summary',
                    'props' => [
                        'label' => 'Records',
                        'value' => (string)count($rows),
                        'delta' => '',
                    ],
                    'data_binding' => 'module.metrics.total',
                ],
                [
                    'id' => 'form_1',
                    'component' => 'form',
                    'x' => 6,
                    'y' => 0,
                    'w' => 6,
                    'h' => 4,
                    'group' => 'actions',
                    'props' => [
                        'title' => 'Create Record',
                        'submit_label' => 'Save',
                        'show_required' => true,
                    ],
                    'data_binding' => 'module.fields',
                ],
                [
                    'id' => 'table_main',
                    'component' => 'table',
                    'x' => 0,
                    'y' => 4,
                    'w' => 12,
                    'h' => 7,
                    'group' => 'data',
                    'props' => [
                        'title' => (string)($module['display_name'] ?? 'Data Table'),
                        'sample_rows' => 8,
                        'density' => 'comfortable',
                    ],
                    'data_binding' => 'module.rows',
                ],
            ],
            'relations' => [
                ['source_id' => 'filter_1', 'target_id' => 'table_main', 'type' => 'filter_to_table'],
                ['source_id' => 'filter_1', 'target_id' => 'kpi_card_1', 'type' => 'filter_to_kpi'],
                ['source_id' => 'form_1', 'target_id' => 'table_main', 'type' => 'form_refresh_table'],
                ['source_id' => 'table_main', 'target_id' => 'kpi_card_1', 'type' => 'table_to_kpi_derived'],
            ],
        ];

        return [
            'layout' => $layout,
            'component_registry' => [
                'version' => 'runtime.generated.v1',
                'components' => ['filter', 'table', 'form', 'kpi_card'],
            ],
            'workflow' => self::generatedRuntimeWorkflow($module),
        ];
    }

    /** @return array<string,array<string,mixed>> */
    public static function loadGeneratedAppRegistry(): array
    {
        $path = self::generatedAppsRegistryPath();
        if (!is_file($path)) {
            return [];
        }
        $raw = @file_get_contents($path);
        $decoded = $raw !== false ? json_decode((string)$raw, true) : null;
        if (!is_array($decoded)) {
            return [];
        }

        $entries = is_array($decoded['apps'] ?? null) ? $decoded['apps'] : $decoded;
        $registry = [];
        foreach ($entries as $appKey => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $safeAppKey = self::generatedKey((string)$appKey);
            if ($safeAppKey === '' || $safeAppKey === 'tmp') {
                continue;
            }
            $status = strtolower((string)($entry['status'] ?? 'disabled'));
            if (!in_array($status, self::GENERATED_APP_STATUSES, true)) {
                $status = 'disabled';
            }
            $modules = [];
            foreach ((array)($entry['modules'] ?? []) as $moduleKey) {
                $safeModuleKey = self::generatedKey((string)$moduleKey);
                if ($safeModuleKey !== '') {
                    $modules[] = $safeModuleKey;
                }
            }
            $registry[$safeAppKey] = [
                'current_version' => (string)($entry['current_version'] ?? ''),
                'status' => $status,
                'modules' => array_values(array_unique($modules)),
                'publish_approved' => !empty($entry['publish_approved']),
                'publish_gate_status' => (string)($entry['publish_gate_status'] ?? ''),
                'publish_decision_id' => (string)($entry['publish_decision_id'] ?? ''),
                'approved_by' => (string)($entry['approved_by'] ?? ''),
                'approval_timestamp' => (string)($entry['approval_timestamp'] ?? ''),
                'module_origin' => (string)($entry['module_origin'] ?? ''),
                'created_at' => (string)($entry['created_at'] ?? ''),
                'updated_at' => (string)($entry['updated_at'] ?? ''),
            ];
        }

        return $registry;
    }

    /** @return array<int,array<string,mixed>> */
    public static function generatedAppLifecycleEntries(): array
    {
        $registry = self::loadGeneratedAppRegistry();
        $entries = [];
        foreach ($registry as $appKey => $entry) {
            $modules = is_array($entry['modules'] ?? null) ? $entry['modules'] : [];
            $moduleDetails = [];
            foreach ($modules as $moduleKey) {
                $manifest = self::loadGeneratedLiveManifest(self::GENERATED_ROOT . '/' . $appKey . '/' . (string)$moduleKey);
                $moduleDetails[] = [
                    'module_key' => (string)$moduleKey,
                    'display_name' => (string)($manifest['display_name'] ?? self::displayName((string)$moduleKey)),
                    'route_path' => (string)($manifest['route_path'] ?? ''),
                    'files_present' => $manifest !== [],
                ];
            }
            $entries[] = [
                'app_key' => (string)$appKey,
                'display_name' => self::displayName((string)$appKey),
                'current_version' => (string)($entry['current_version'] ?? ''),
                'status' => (string)($entry['status'] ?? 'disabled'),
                'modules' => $modules,
                'module_details' => $moduleDetails,
                'created_at' => (string)($entry['created_at'] ?? ''),
                'updated_at' => (string)($entry['updated_at'] ?? ''),
            ];
        }
        usort($entries, static fn(array $a, array $b): int => strcmp((string)($a['app_key'] ?? ''), (string)($b['app_key'] ?? '')));
        return $entries;
    }

    /** @return array<int,array<string,mixed>> */
    public static function listGeneratedAppsWithModules(): array
    {
        $registry = self::loadGeneratedAppRegistry();
        if ($registry === []) {
            return [];
        }

        $list = [];
        foreach ($registry as $appKey => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $safeAppKey = self::generatedKey((string)$appKey);
            if ($safeAppKey === '' || $safeAppKey === 'tmp') {
                continue;
            }

            $moduleKeys = [];
            foreach ((array)($entry['modules'] ?? []) as $moduleKey) {
                $safeModuleKey = self::generatedKey((string)$moduleKey);
                if ($safeModuleKey !== '') {
                    $moduleKeys[] = $safeModuleKey;
                }
            }
            $moduleKeys = array_values(array_unique($moduleKeys));

            $modules = [];
            foreach ($moduleKeys as $moduleKey) {
                $baseRelativePath = self::GENERATED_ROOT . '/' . $safeAppKey . '/' . $moduleKey;
                if (!self::isSafeGeneratedLivePath($baseRelativePath)) {
                    continue;
                }
                $baseAbsolutePath = self::absolutePath($baseRelativePath);
                $manifest = self::readJsonFileSafely($baseAbsolutePath . '/manifest.json');
                if (!is_array($manifest) || (string)($manifest['schema_version'] ?? '') !== 'studio.generated-module.v1') {
                    continue;
                }

                $moduleManifest = self::readJsonFileSafely($baseAbsolutePath . '/module.json');
                $routePath = trim((string)($manifest['route_path'] ?? $moduleManifest['route_path'] ?? ''));
                if ($routePath === '') {
                    $routePath = self::extractGeneratedRoutePathFromFile($baseAbsolutePath . '/routes.php');
                }

                $compileId = trim((string)($moduleManifest['compile_id'] ?? $manifest['compile_id'] ?? $entry['current_version'] ?? ''));
                $snapshotId = trim((string)($manifest['snapshot_id'] ?? $moduleManifest['snapshot_id'] ?? ''));
                $snapshotRecord = $compileId !== '' ? self::loadGeneratedSnapshotByCompileId($compileId) : [];
                if (!is_array($snapshotRecord) || $snapshotRecord === []) {
                    $snapshotRecord = self::findGeneratedSnapshotByModule($safeAppKey, $moduleKey);
                }
                if ($snapshotId === '') {
                    $snapshotId = trim((string)($snapshotRecord['snapshot_id'] ?? ''));
                }

                $generatedAt = trim((string)($manifest['created_at'] ?? $moduleManifest['created_at'] ?? $snapshotRecord['created_at'] ?? $snapshotRecord['timestamp'] ?? ''));
                $lastUpdated = trim((string)($entry['updated_at'] ?? $snapshotRecord['timestamp'] ?? $generatedAt));
                if ($lastUpdated === '') {
                    $mtime = @filemtime($baseAbsolutePath . '/manifest.json');
                    if ($mtime !== false) {
                        $lastUpdated = gmdate('c', (int)$mtime);
                    }
                }

                $modules[] = [
                    'module_key' => $moduleKey,
                    'display_name' => (string)($manifest['display_name'] ?? self::displayName($moduleKey)),
                    'route_path' => $routePath,
                    'version' => $compileId !== '' ? $compileId : null,
                    'snapshot_id' => $snapshotId !== '' ? $snapshotId : null,
                    'last_updated' => $lastUpdated !== '' ? $lastUpdated : null,
                ];
            }

            usort($modules, static fn(array $a, array $b): int => strcmp((string)($a['module_key'] ?? ''), (string)($b['module_key'] ?? '')));
            $list[] = [
                'app_key' => $safeAppKey,
                'status' => (string)($entry['status'] ?? 'disabled'),
                'publish_approved' => !empty($entry['publish_approved']),
                'modules' => $modules,
            ];
        }

        usort($list, static fn(array $a, array $b): int => strcmp((string)($a['app_key'] ?? ''), (string)($b['app_key'] ?? '')));
        return $list;
    }

    /** @return array<string,mixed>|null */
    public static function loadGeneratedModuleDefinition(string $appKey, string $moduleKey): ?array
    {
        $safeAppKey = self::generatedKey($appKey);
        $safeModuleKey = self::generatedKey($moduleKey);
        if ($safeAppKey === '' || $safeModuleKey === '') {
            return null;
        }

        $baseRelativePath = self::GENERATED_ROOT . '/' . $safeAppKey . '/' . $safeModuleKey;
        if (!self::isSafeGeneratedLivePath($baseRelativePath)) {
            return null;
        }
        $baseAbsolutePath = self::absolutePath($baseRelativePath);
        $manifest = self::readJsonFileSafely($baseAbsolutePath . '/manifest.json');
        $moduleManifest = self::readJsonFileSafely($baseAbsolutePath . '/module.json');
        if (!is_array($manifest) && !is_array($moduleManifest)) {
            return null;
        }

        $registry = self::loadGeneratedAppRegistry();
        $registryEntry = is_array($registry[$safeAppKey] ?? null) ? $registry[$safeAppKey] : [];
        $routePath = trim((string)($manifest['route_path'] ?? $moduleManifest['route_path'] ?? ''));
        if ($routePath === '') {
            $routePath = self::extractGeneratedRoutePathFromFile($baseAbsolutePath . '/routes.php');
        }

        $compileId = trim((string)($moduleManifest['compile_id'] ?? $manifest['compile_id'] ?? $registryEntry['current_version'] ?? ''));
        $snapshotRecord = $compileId !== '' ? self::loadGeneratedSnapshotByCompileId($compileId) : [];
        if (!is_array($snapshotRecord) || $snapshotRecord === []) {
            $snapshotRecord = self::findGeneratedSnapshotByModule($safeAppKey, $safeModuleKey);
        }
        $snapshotId = trim((string)($manifest['snapshot_id'] ?? $moduleManifest['snapshot_id'] ?? $snapshotRecord['snapshot_id'] ?? ''));
        $generatedAt = trim((string)($manifest['created_at'] ?? $snapshotRecord['created_at'] ?? $snapshotRecord['timestamp'] ?? ''));
        $displayName = (string)($manifest['display_name'] ?? $moduleManifest['display_name'] ?? self::displayName($safeModuleKey));
        $appDisplayName = (string)($manifest['app_display_name'] ?? self::displayName($safeAppKey));

        $navContract = self::extractGeneratedNavigationContractFromFile($baseAbsolutePath . '/navigation.php');
        $manifestNav = is_array($manifest['navigation'] ?? null) ? $manifest['navigation'] : [];
        $navSection = trim((string)($navContract['section'] ?? $manifestNav['section'] ?? $appDisplayName));
        $navLabel = trim((string)($navContract['label'] ?? $manifestNav['label'] ?? $displayName));
        $navUrl = trim((string)($navContract['url'] ?? $routePath));
        $navKey = trim((string)($navContract['key'] ?? $manifestNav['key'] ?? ($safeAppKey . '.' . $safeModuleKey)));
        $navVisibleIf = trim((string)($navContract['visible_if'] ?? 'role_platform_admin_or_sysadmin'));
        $navOrder = (int)($navContract['order'] ?? $manifestNav['order'] ?? 10);
        $navPriority = (int)($navContract['priority'] ?? $manifestNav['priority'] ?? 100);

        $template = trim((string)($moduleManifest['template'] ?? $manifest['templates']['module'] ?? 'crud_module'));
        $moduleType = self::inferStudioModuleTypeFromTemplate($template);
        $viewKind = self::inferStudioViewKindFromTemplate((string)($manifest['templates']['view'] ?? 'table_view'));

        $defaultRoutePrefix = self::generatedAppRoutePrefix($safeAppKey);
        $defaultLandingPage = $routePath !== '' ? $routePath : $defaultRoutePrefix;
        $createdBy = trim((string)($snapshotRecord['approved_by'] ?? $registryEntry['approved_by'] ?? ''));

        $appManifestBundle = [
            'schema_version' => 'studio.app-manifest.v1',
            'status' => 'draft',
            'app' => [
                'app_key' => $safeAppKey,
                'display_name' => $appDisplayName,
                'app_taxonomy' => 'business_app',
                'owner' => 'platform',
                'description' => 'studio.' . $safeAppKey . '.library.description',
                'route_prefix' => $defaultRoutePrefix,
                'default_landing_page' => $defaultLandingPage,
                'is_active' => (string)($registryEntry['status'] ?? 'disabled') === 'enabled',
            ],
            'governance' => [
                'created_from' => 'erp-app-studio-library',
                'requires_platform_admin_approval' => true,
                'requires_i18n_keys' => true,
                'requires_schema_check' => true,
                'publish_locked' => true,
            ],
            'i18n' => [
                'title_key' => 'app.' . $safeAppKey . '.title',
                'display_name_key' => 'app.' . $safeAppKey . '.display_name',
                'description_key' => 'app.' . $safeAppKey . '.description',
                'supported_locales' => ['en', 'ja', 'ne'],
            ],
            'audit' => [
                'draft_id' => (string)($compileId !== '' ? $compileId : ''),
                'created_by' => $createdBy,
                'created_at' => $generatedAt,
            ],
        ];

        $moduleManifestBundle = [
            'schema_version' => 'studio.module-manifest.v1',
            'status' => 'draft',
            'module' => [
                'app_key' => $safeAppKey,
                'module_key' => $safeModuleKey,
                'display_name' => $displayName,
                'title_key' => 'app.' . $safeAppKey . '.' . $safeModuleKey . '.title',
                'display_name_key' => 'app.' . $safeAppKey . '.' . $safeModuleKey . '.display_name',
                'description' => 'studio.' . $safeModuleKey . '.library.description',
                'module_type' => $moduleType,
                'target_maturity_level' => 'L1',
                'declared_capabilities' => [],
                'route_base' => $routePath !== '' ? $routePath : ($moduleManifest['route_path'] ?? ''),
            ],
            'data_policy' => [
                'surface_mode' => 'readonly_first',
                'mutations_require_csrf' => true,
                'direct_db_mutation_allowed' => false,
            ],
            'contracts' => [
                'owns_routes' => true,
                'owns_views' => true,
                'requires_health_check' => true,
                'requires_localization' => true,
                'requires_permission_policy' => true,
            ],
            'audit' => [
                'draft_id' => (string)($compileId !== '' ? $compileId : ''),
                'created_by' => $createdBy,
                'created_at' => $generatedAt,
            ],
        ];

        $viewManifestBundle = [
            'schema_version' => 'studio.view-manifest.v1',
            'status' => 'draft',
            'view' => [
                'app_key' => $safeAppKey,
                'module_key' => $safeModuleKey,
                'view_key' => 'index',
                'view_kind' => $viewKind,
                'route_path' => $routePath,
                'surface' => 'admin',
                'wrapper' => 'admin',
                'required_role' => 'platform_admin',
                'title_key' => 'app.' . $safeAppKey . '.' . $safeModuleKey . '.index.' . $viewKind . '.title',
                'display_name_key' => 'app.' . $safeAppKey . '.' . $safeModuleKey . '.index.' . $viewKind . '.display_name',
                'description_key' => 'app.' . $safeAppKey . '.' . $safeModuleKey . '.index.' . $viewKind . '.description',
            ],
            'layout' => [
                'kind' => $viewKind,
                'uses_shared_tokens' => true,
                'local_style_system' => false,
            ],
            'data_contract' => [
                'adapter_class' => '',
                'allowed_filters' => [],
                'required_fields' => [],
                'empty_state_key' => 'app.' . $safeAppKey . '.' . $safeModuleKey . '.index.' . $viewKind . '.empty',
            ],
            'security' => [
                'requires_auth' => true,
                'required_role' => 'platform_admin',
                'allowed_roles' => ['platform_admin'],
                'csrf_for_mutations' => true,
                'direct_db_mutation_allowed' => false,
            ],
            'audit' => [
                'draft_id' => (string)($compileId !== '' ? $compileId : ''),
                'created_by' => $createdBy,
                'created_at' => $generatedAt,
            ],
        ];

        $navigationManifestBundle = [
            'schema_version' => 'studio.navigation-manifest.v1',
            'status' => 'draft',
            'navigation' => [
                'scope' => 'admin',
                'owner_app' => $safeAppKey,
                'key' => $navKey,
                'label_key' => 'app.' . $safeAppKey . '.' . $safeModuleKey . '.nav',
                'url' => $navUrl,
                'section' => $navSection,
                'order' => $navOrder,
                'priority' => $navPriority,
                'visible_if' => $navVisibleIf !== '' ? $navVisibleIf : 'role_platform_admin_or_sysadmin',
            ],
            'active_patterns' => [
                'exact' => $navUrl !== '' ? [$navUrl] : [],
                'prefix' => $defaultRoutePrefix !== '' ? [$defaultRoutePrefix] : [],
            ],
            'governance' => [
                'wrapper_confinement_valid' => true,
                'no_cross_layer_escape' => true,
                'publish_locked' => true,
            ],
            'audit' => [
                'draft_id' => (string)($compileId !== '' ? $compileId : ''),
                'created_by' => $createdBy,
                'created_at' => $generatedAt,
            ],
        ];

        return [
            'app_manifest' => $appManifestBundle,
            'module_manifest' => $moduleManifestBundle,
            'view_definition' => $viewManifestBundle,
            'navigation_definition' => $navigationManifestBundle,
            'metadata' => [
                'compile_id' => $compileId,
                'snapshot_id' => $snapshotId,
                'version' => $compileId,
                'generated_at' => $generatedAt,
            ],
        ];
    }

    /** @param array<string,mixed> $module @param array<string,mixed> $query @return array<string,mixed> */
    public static function generatedModuleRuntimeData(array $module, array $query = []): array
    {
        $appKey = self::generatedKey((string)($module['app_key'] ?? ''));
        $moduleKey = self::generatedKey((string)($module['module_key'] ?? ''));
        $fields = self::normalizeGeneratedFields(is_array($module['fields'] ?? null) ? $module['fields'] : []);
        $provider = self::generatedModuleProvider($module);
        $providerResult = $provider !== null
            ? $provider::records($query)
            : ['rows' => self::loadGeneratedModuleRows($appKey, $moduleKey), 'filters' => [], 'sort' => []];
        $rows = is_array($providerResult['rows'] ?? null) ? array_values(array_filter($providerResult['rows'], 'is_array')) : [];
        $workflow = self::generatedRuntimeWorkflow($module);
        $slaConfig = is_array($workflow['max_time_per_state'] ?? null) ? $workflow['max_time_per_state'] : [];
        $nowTs = time();
        $rows = array_map(static function (array $row) use ($slaConfig, $nowTs): array {
            return self::enrichGeneratedRuntimeTimeIntelligence($row, $slaConfig, $nowTs);
        }, $rows);
        $search = trim((string)($query['q'] ?? ''));
        if ($provider === null && $search !== '') {
            $needle = strtolower($search);
            $rows = array_values(array_filter($rows, static function (array $row) use ($needle): bool {
                foreach ($row as $value) {
                    if (str_contains(strtolower((string)$value), $needle)) {
                        return true;
                    }
                }
                return false;
            }));
        }

        $editId = trim((string)($query['edit'] ?? ''));
        $editRow = [];
        if ($editId !== '') {
            foreach ($rows as $row) {
                if ((string)($row['id'] ?? '') === $editId) {
                    $editRow = $row;
                    break;
                }
            }
        }

        $flash = [];
        if (isset($_SESSION['generated_module_flash']) && is_array($_SESSION['generated_module_flash'])) {
            $sessionFlash = $_SESSION['generated_module_flash'];
            if ((string)($sessionFlash['route_path'] ?? '') === (string)($module['route_path'] ?? '')) {
                $flash = [
                    'status' => (string)($sessionFlash['status'] ?? ''),
                    'message' => (string)($sessionFlash['message'] ?? ''),
                ];
                unset($_SESSION['generated_module_flash']);
            }
        }

        return [
            'module' => [
                'app_key' => $appKey,
                'module_key' => $moduleKey,
                'route_path' => (string)($module['route_path'] ?? ''),
                'manifest_path' => (string)($module['manifest_path'] ?? ''),
                'display_name' => (string)($module['display_name'] ?? self::displayName($moduleKey)),
                'fields' => $fields,
            ],
            'fields' => $fields,
            'rows' => $rows,
            'edit_row' => $editRow,
            'search' => $search,
            'filters' => is_array($providerResult['filters'] ?? null) ? $providerResult['filters'] : [],
            'sort' => is_array($providerResult['sort'] ?? null) ? $providerResult['sort'] : [],
            'flash' => $flash,
        ];
    }

    /** @param array<string,mixed> $module @param array<string,mixed> $post @param array<string,mixed> $context @return array<string,mixed> */
    public static function handleGeneratedModuleSubmission(array $module, array $post, array $context = []): array
    {
        $appKey = self::generatedKey((string)($module['app_key'] ?? ''));
        $moduleKey = self::generatedKey((string)($module['module_key'] ?? ''));
        $runtimeRole = strtolower(trim((string)($context['runtime_role'] ?? 'read_only')));
        $runtimeActor = self::safeGeneratedAuditActor((string)($context['runtime_actor'] ?? 'system'));
        $action = self::generatedKey((string)($post['runtime_action'] ?? ''));
        $fields = self::normalizeGeneratedFields(is_array($module['fields'] ?? null) ? $module['fields'] : []);
        if ($appKey === '' || $moduleKey === '' || $fields === []) {
            return ['ok' => false, 'message' => 'generated_module_not_editable'];
        }

        if ($action === 'workflow_transition') {
            return self::applyGeneratedWorkflowTransition($appKey, $moduleKey, $post, $runtimeRole, $runtimeActor);
        }

        if (!self::generatedRuntimeCanEdit($runtimeRole)) {
            return ['ok' => false, 'message' => 'runtime_read_only'];
        }

        $provider = self::generatedModuleProvider($module);
        if ($provider !== null) {
            $result = $provider::save($post);
            if (!empty($result['ok'])) {
                self::ensureGeneratedWorkflowStatusForRecord($appKey, $moduleKey, (string)($result['row_id'] ?? ''));
            }
            return $result;
        }

        $rows = self::loadGeneratedModuleRows($appKey, $moduleKey);
        $rowId = self::generatedRecordId((string)($post['row_id'] ?? ''));
        $row = $rowId !== '' ? self::findGeneratedModuleRow($rows, $rowId) : [];
        $row['id'] = $rowId !== '' ? $rowId : self::previewUuid('generated-row:' . $appKey . ':' . $moduleKey . ':' . microtime(true));
        $row['updated_at'] = gmdate('c');
        if (!isset($row['created_at'])) {
            $row['created_at'] = gmdate('c');
        }
        $row['status'] = self::normalizeGeneratedWorkflowStatus((string)($row['status'] ?? 'draft'));
        $row['history'] = self::normalizeGeneratedWorkflowHistory($row['history'] ?? []);

        $errors = [];
        foreach ($fields as $field) {
            $key = (string)($field['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $value = trim((string)($post[$key] ?? ''));
            if ($key === 'status') {
                continue;
            }
            if (!empty($field['required']) && $value === '') {
                $errors[] = 'missing_' . $key;
            }
            if ((string)($field['type'] ?? '') === 'integer') {
                $row[$key] = $value === '' ? '' : (string)max(0, (int)$value);
                continue;
            }
            if ((string)($field['type'] ?? '') === 'select') {
                $options = array_map('strval', (array)($field['options'] ?? []));
                $row[$key] = in_array($value, $options, true) ? $value : (string)($options[0] ?? '');
                continue;
            }
            $row[$key] = self::safeGeneratedCellValue($value);
        }
        if ($errors !== []) {
            return ['ok' => false, 'message' => implode(',', array_values(array_unique($errors)))];
        }

        $upserted = false;
        foreach ($rows as $index => $existing) {
            if ((string)($existing['id'] ?? '') === (string)$row['id']) {
                $rows[$index] = $row;
                $upserted = true;
                break;
            }
        }
        if (!$upserted) {
            $rows[] = $row;
        }

        return self::persistGeneratedModuleRows($appKey, $moduleKey, $rows)
            ? ['ok' => true, 'message' => 'generated_row_saved', 'row_id' => (string)$row['id']]
            : ['ok' => false, 'message' => 'generated_row_save_failed'];
    }

    /** @param array<string,mixed> $post @return array<string,mixed> */
    private static function applyGeneratedWorkflowTransition(string $appKey, string $moduleKey, array $post, string $runtimeRole, string $runtimeActor): array
    {
        $rowId = self::generatedRecordId((string)($post['row_id'] ?? ''));
        $targetStatus = self::normalizeGeneratedWorkflowStatus((string)($post['target_status'] ?? ''));
        if ($rowId === '' || $targetStatus === '') {
            return ['ok' => false, 'message' => 'generated_workflow_invalid_payload'];
        }

        $rows = self::loadGeneratedModuleRows($appKey, $moduleKey);
        if ($rows === []) {
            return ['ok' => false, 'message' => 'generated_workflow_row_not_found'];
        }

        $index = -1;
        $currentStatus = 'draft';
        foreach ($rows as $rowIndex => $row) {
            if (!is_array($row) || (string)($row['id'] ?? '') !== $rowId) {
                continue;
            }
            $index = (int)$rowIndex;
            $currentStatus = self::normalizeGeneratedWorkflowStatus((string)($row['status'] ?? 'draft'));
            break;
        }
        if ($index < 0) {
            return ['ok' => false, 'message' => 'generated_workflow_row_not_found'];
        }

        $workflow = self::generatedRuntimeWorkflow();
        $transitions = is_array($workflow['transitions'] ?? null) ? $workflow['transitions'] : [];
        $allowedTargets = array_values(array_filter((array)($transitions[$currentStatus] ?? []), 'is_string'));
        if (!in_array($targetStatus, $allowedTargets, true)) {
            return ['ok' => false, 'message' => 'generated_workflow_transition_invalid'];
        }

        $roleAllowedTargets = self::generatedRuntimeAllowedTransitions($runtimeRole, $currentStatus);
        if (!in_array($targetStatus, $roleAllowedTargets, true)) {
            return ['ok' => false, 'message' => 'generated_workflow_transition_unauthorized'];
        }

        $history = self::normalizeGeneratedWorkflowHistory($rows[$index]['history'] ?? []);
        $currentRow = is_array($rows[$index] ?? null) ? $rows[$index] : [];
        $enteredAtTs = self::inferGeneratedStateEnteredAt($currentRow, $currentStatus, $history);
        $nowTs = time();
        $durationSeconds = max(0, $nowTs - $enteredAtTs);
        $history[] = [
            'from_status' => $currentStatus,
            'to_status' => $targetStatus,
            'role' => strtolower(trim($runtimeRole)) !== '' ? strtolower(trim($runtimeRole)) : 'read_only',
            'timestamp' => gmdate('c'),
            'actor' => self::safeGeneratedAuditActor($runtimeActor),
            'duration_in_previous_state_seconds' => $durationSeconds,
            'duration_in_previous_state_label' => self::formatGeneratedDurationLabel($durationSeconds),
        ];

        $rows[$index]['status'] = $targetStatus;
        $rows[$index]['history'] = array_values($history);
        $rows[$index]['updated_at'] = gmdate('c');
        return self::persistGeneratedModuleRows($appKey, $moduleKey, $rows)
            ? ['ok' => true, 'message' => 'generated_workflow_transition_saved', 'row_id' => $rowId]
            : ['ok' => false, 'message' => 'generated_row_save_failed'];
    }

    private static function ensureGeneratedWorkflowStatusForRecord(string $appKey, string $moduleKey, string $rowId): void
    {
        $safeRowId = self::generatedRecordId($rowId);
        if ($safeRowId === '') {
            return;
        }

        $rows = self::loadGeneratedModuleRows($appKey, $moduleKey);
        $updated = false;
        foreach ($rows as $index => $row) {
            if (!is_array($row) || (string)($row['id'] ?? '') !== $safeRowId) {
                continue;
            }
            $normalized = self::normalizeGeneratedWorkflowStatus((string)($row['status'] ?? 'draft'));
            if ((string)($row['status'] ?? '') !== $normalized) {
                $rows[$index]['status'] = $normalized;
                $rows[$index]['updated_at'] = gmdate('c');
                $updated = true;
            }
            $history = self::normalizeGeneratedWorkflowHistory($row['history'] ?? []);
            if (($row['history'] ?? null) !== $history) {
                $rows[$index]['history'] = $history;
                $rows[$index]['updated_at'] = gmdate('c');
                $updated = true;
            }
            break;
        }

        if ($updated) {
            self::persistGeneratedModuleRows($appKey, $moduleKey, $rows);
        }
    }

    private static function generatedModuleProvider(array $module): ?string
    {
        $manifestPath = (string)($module['manifest_path'] ?? '');
        if ($manifestPath === '' || str_starts_with($manifestPath, '/') || str_contains($manifestPath, '\\') || str_contains($manifestPath, '..')) {
            return null;
        }
        if (!preg_match('#^apps/Generated/[a-z0-9_]+/[a-z0-9_]+/manifest\\.json$#', $manifestPath)) {
            return null;
        }

        $baseRelativePath = dirname($manifestPath);
        $providerFiles = glob(self::absolutePath($baseRelativePath . '/Providers/*Provider.php')) ?: [];
        sort($providerFiles);
        foreach ($providerFiles as $providerFile) {
            if (!is_file($providerFile)) {
                continue;
            }
            $before = get_declared_classes();
            require_once $providerFile;
            $after = array_values(array_diff(get_declared_classes(), $before));
            foreach ($after as $class) {
                if (is_string($class) && str_starts_with($class, 'Generated\\') && method_exists($class, 'records') && method_exists($class, 'save')) {
                    return $class;
                }
            }
        }

        $appKey = self::studlyPathSegment((string)($module['app_key'] ?? ''));
        $moduleKey = self::studlyPathSegment((string)($module['module_key'] ?? ''));
        $class = 'Generated\\' . $appKey . '\\Providers\\' . $moduleKey . 'Provider';
        return class_exists($class) && method_exists($class, 'records') && method_exists($class, 'save') ? $class : null;
    }

    /** @return array<string,mixed> */
    public static function setGeneratedAppLifecycleStatus(string $appKey, string $status): array
    {
        $appKey = self::generatedKey($appKey);
        $status = strtolower(trim($status));
        if ($appKey === '' || !in_array($status, ['enabled', 'disabled'], true)) {
            return ['ok' => false, 'status' => 'FAILED', 'message' => 'invalid_lifecycle_request'];
        }

        $registry = self::loadGeneratedAppRegistry();
        if (!isset($registry[$appKey]) || !is_array($registry[$appKey])) {
            return ['ok' => false, 'status' => 'FAILED', 'message' => 'app_not_found'];
        }
        if ($status === 'enabled' && !self::generatedAppFilesPresent($appKey, $registry[$appKey])) {
            return ['ok' => false, 'status' => 'FAILED', 'message' => 'module_files_missing'];
        }

        $registry[$appKey]['status'] = $status;
        $registry[$appKey]['updated_at'] = gmdate('c');
        $persisted = self::persistGeneratedAppRegistry($registry);
        $result = [
            'ok' => $persisted,
            'status' => $persisted ? 'UPDATED' : 'FAILED',
            'message' => $persisted ? 'lifecycle_' . $status : 'registry_write_failed',
            'app_key' => $appKey,
            'lifecycle_status' => $status,
        ];
        self::appendStructuredApplyLog('lifecycle_' . ($persisted ? $status : 'failed'), [
            'compile_id' => (string)($registry[$appKey]['current_version'] ?? ''),
            'snapshot_id' => '',
            'status' => $result['status'],
            'completed_at' => gmdate('c'),
        ], ['app_key' => $appKey, 'module_key' => implode(',', (array)($registry[$appKey]['modules'] ?? []))]);
        return $result;
    }

    /** @return array<string,mixed> */
    public static function uninstallGeneratedApp(string $appKey): array
    {
        $appKey = self::generatedKey($appKey);
        if ($appKey === '') {
            return ['ok' => false, 'status' => 'FAILED', 'message' => 'invalid_lifecycle_request'];
        }
        if (self::generatedRollbackInProgress()) {
            return ['ok' => false, 'status' => 'FAILED', 'message' => 'rollback_in_progress'];
        }

        $registry = self::loadGeneratedAppRegistry();
        if (!isset($registry[$appKey]) || !is_array($registry[$appKey])) {
            return ['ok' => false, 'status' => 'FAILED', 'message' => 'app_not_found'];
        }
        $entry = $registry[$appKey];
        $modules = is_array($entry['modules'] ?? null) ? $entry['modules'] : [];
        $preconditions = [];
        foreach ($modules as $moduleKey) {
            $relativePath = self::GENERATED_ROOT . '/' . $appKey . '/' . self::generatedKey((string)$moduleKey);
            if (!self::isSafeGeneratedLivePath($relativePath)) {
                $preconditions[] = 'unsafe_live_path';
            }
        }
        if ($preconditions !== []) {
            return ['ok' => false, 'status' => 'FAILED', 'message' => 'uninstall_blocked', 'preconditions' => array_values(array_unique($preconditions))];
        }

        $registry[$appKey]['status'] = 'disabled';
        $registry[$appKey]['updated_at'] = gmdate('c');
        if (!self::persistGeneratedAppRegistry($registry)) {
            return ['ok' => false, 'status' => 'FAILED', 'message' => 'registry_write_failed'];
        }

        $removed = [];
        foreach ($modules as $moduleKey) {
            $relativePath = self::GENERATED_ROOT . '/' . $appKey . '/' . self::generatedKey((string)$moduleKey);
            self::deleteGeneratedLiveTree($relativePath);
            $removed[] = $relativePath;
        }

        unset($registry[$appKey]);
        $persisted = self::persistGeneratedAppRegistry($registry);
        $result = [
            'ok' => $persisted,
            'status' => $persisted ? 'REMOVED' : 'FAILED',
            'message' => $persisted ? 'lifecycle_removed' : 'registry_write_failed',
            'app_key' => $appKey,
            'removed_paths' => $removed,
        ];
        self::appendStructuredApplyLog($persisted ? 'lifecycle_removed' : 'lifecycle_failed', [
            'compile_id' => (string)($entry['current_version'] ?? ''),
            'snapshot_id' => '',
            'status' => $result['status'],
            'completed_at' => gmdate('c'),
        ], ['app_key' => $appKey, 'module_key' => implode(',', $modules)]);
        return $result;
    }

    /** @param array<string,array<string,mixed>> $decoded @return array<string,mixed> */
    private static function generatedApplyContext(array $decoded): array
    {
        $appRaw = (string)($decoded['app_manifest']['app']['app_key'] ?? '');
        $moduleRaw = (string)($decoded['module_manifest']['module']['module_key'] ?? '');
        $appKey = self::generatedKey($appRaw);
        $moduleKey = self::generatedKey($moduleRaw);
        $viewKey = self::generatedKey((string)($decoded['view_definition']['view']['view_key'] ?? 'index'));
        $appTemplate = self::inferAppTemplate($decoded);
        $moduleTemplate = self::inferModuleTemplate($decoded);
        $viewTemplate = self::inferViewTemplate($decoded);
        $routePath = trim((string)($decoded['view_definition']['view']['route_path'] ?? ''));
        if ($routePath === '') {
            $routePath = '/apps/generated/' . $appKey . '/' . $moduleKey;
        }
        $displayName = trim((string)($decoded['module_manifest']['module']['display_name'] ?? ''));
        if ($displayName === '') {
            $displayName = self::displayName($moduleKey);
        }
        $appDisplayName = trim((string)($decoded['app_manifest']['app']['display_name'] ?? ''));
        if ($appDisplayName === '') {
            $appDisplayName = self::displayName($appKey);
        }
        $navigation = is_array($decoded['navigation_definition']['navigation'] ?? null) ? $decoded['navigation_definition']['navigation'] : [];
        $navLabel = trim((string)($navigation['label'] ?? ''));
        if ($navLabel === '') {
            $navLabel = $displayName;
        }
        $navSection = trim((string)($navigation['section'] ?? ''));
        if ($navSection === '') {
            $navSection = $appDisplayName;
        }
        $fields = self::extractGeneratedFields($decoded);

        $errors = [];
        if ($appKey === '' || $appKey !== strtolower(str_replace('-', '_', trim($appRaw)))) {
            $errors[] = 'invalid_generated_app_key';
        }
        if ($appKey === 'tmp') {
            $errors[] = 'reserved_generated_app_key';
        }
        if ($moduleKey === '' || $moduleKey !== strtolower(str_replace('-', '_', trim($moduleRaw)))) {
            $errors[] = 'invalid_generated_module_key';
        }
        foreach ([$appTemplate, $moduleTemplate, $viewTemplate, 'navigation_entry'] as $template) {
            if (!in_array($template, self::FIRST_APPLY_TEMPLATES, true)) {
                $errors[] = 'unsupported_template';
            }
        }
        if (!self::isSafeGeneratedRoutePath($routePath)) {
            $errors[] = 'unsafe_generated_route_path';
        }

        return [
            'errors' => array_values(array_unique($errors)),
            'app_key' => $appKey,
            'module_key' => $moduleKey,
            'view_key' => $viewKey !== '' ? $viewKey : 'index',
            'view_file' => ($viewKey !== '' ? $viewKey : 'index') . '.php',
            'route_path' => $routePath,
            'relative_path' => self::GENERATED_ROOT . '/' . $appKey . '/' . $moduleKey,
            'controller_class' => 'Generated' . self::studlyPathSegment($appKey) . self::studlyPathSegment($moduleKey) . 'Controller',
            'provider_namespace' => 'Generated\\' . self::studlyPathSegment($appKey) . '\\Providers',
            'provider_class' => self::studlyPathSegment($moduleKey) . 'Provider',
            'display_name' => self::safeDisplayText($displayName),
            'app_display_name' => self::safeDisplayText($appDisplayName),
            'description' => self::safeDisplayText((string)($decoded['view_definition']['view']['description_key'] ?? $decoded['module_manifest']['module']['description'] ?? '')),
            'fields' => $fields,
            'navigation' => [
                'label' => self::safeDisplayText($navLabel),
                'section' => self::safeDisplayText($navSection),
                'key' => self::generatedKey((string)($navigation['key'] ?? $appKey . '_' . $moduleKey)),
                'order' => (int)($navigation['order'] ?? 50),
                'priority' => (int)($navigation['priority'] ?? 80),
            ],
            'templates' => [
                'app' => $appTemplate,
                'module' => $moduleTemplate,
                'view' => $viewTemplate,
            ],
        ];
    }

    /** @param array<string,array<string,mixed>> $decoded @return array<int,array<string,mixed>> */
    private static function extractGeneratedFields(array $decoded): array
    {
        $sources = [
            $decoded['module_manifest']['fields'] ?? null,
            $decoded['view_definition']['fields'] ?? null,
            $decoded['view_definition']['view']['fields'] ?? null,
        ];
        foreach ($sources as $source) {
            if (is_array($source) && $source !== []) {
                return self::normalizeGeneratedFields($source);
            }
        }
        return [];
    }

    /** @param array<int|string,mixed> $fields @return array<int,array<string,mixed>> */
    private static function normalizeGeneratedFields(array $fields): array
    {
        $normalized = [];
        foreach ($fields as $key => $field) {
            if (is_string($field)) {
                $field = ['key' => $field];
            }
            if (!is_array($field)) {
                continue;
            }

            $fieldKey = self::generatedKey((string)($field['key'] ?? $field['name'] ?? (is_string($key) ? $key : '')));
            if ($fieldKey === '' || isset($normalized[$fieldKey])) {
                continue;
            }
            $type = strtolower(trim((string)($field['type'] ?? 'string')));
            if ($type === 'int') {
                $type = 'integer';
            }
            if (!in_array($type, ['string', 'integer', 'select'], true)) {
                $type = 'string';
            }
            $options = [];
            foreach ((array)($field['options'] ?? []) as $option) {
                $safeOption = self::generatedKey((string)$option);
                if ($safeOption !== '') {
                    $options[] = $safeOption;
                }
            }
            if ($type === 'select' && $options === []) {
                $options = ['active', 'inactive'];
            }
            $label = self::safeDisplayText((string)($field['label'] ?? self::displayName($fieldKey)));
            $normalized[$fieldKey] = [
                'key' => $fieldKey,
                'label' => $label !== '' ? $label : self::displayName($fieldKey),
                'type' => $type,
                'required' => !empty($field['required']),
                'default' => self::safeGeneratedCellValue((string)($field['default'] ?? ($type === 'select' ? ($options[0] ?? '') : ''))),
                'options' => array_values(array_unique($options)),
            ];
        }
        return array_values($normalized);
    }

    private static function generatedApplyArtifact(string $type, string $appKey, string $moduleKey, string $targetPath, string $template, string $riskLevel, string $summary): array
    {
        $artifactId = self::artifactId($type, $appKey, $moduleKey, $targetPath);
        $hash = 'generated:first-apply:' . substr(sha1($artifactId . ':' . $targetPath . ':' . $template), 0, 16);

        return [
            'artifact_id' => $artifactId,
            'artifact_type' => $type,
            'owning_app' => $appKey,
            'owning_module' => $moduleKey,
            'target_path' => $targetPath,
            'source_template' => $template,
            'template_version' => self::TEMPLATE_VERSION,
            'change_type' => 'create',
            'risk_level' => strtoupper($riskLevel),
            'risk_meta' => [
                'risk_level' => strtoupper($riskLevel),
                'risk_score' => self::riskScore($riskLevel),
                'dominant_reason' => 'first_apply_generated_artifact',
                'reasons' => [[
                    'risk_level' => strtoupper($riskLevel),
                    'risk_score' => self::riskScore($riskLevel),
                    'reason' => 'first_apply_generated_artifact',
                ]],
            ],
            'dependencies' => [],
            'generated_by' => self::FIRST_APPLY_GENERATOR_ID,
            'studio_project_id' => 'studio_project:generated:' . $appKey,
            'studio_draft_id' => 'studio_draft:generated:' . $appKey . ':' . $moduleKey,
            'ownership_scope' => 'managed',
            'upgrade_safe' => true,
            'customization_zone' => $type === 'view' ? 'user_editable' : 'generated',
            'content_hash' => $hash,
            'before_hash' => 'placeholder:before:empty',
            'after_hash' => $hash,
            'target_exists' => is_file(rtrim((string)(defined('APP_ROOT') ? APP_ROOT : ''), '/') . '/' . $targetPath),
            'drift_status' => 'clean',
            'diff_status' => 'pending',
            'diff_summary' => $summary,
            'human_diff_summary' => $summary,
            'machine_diff_summary' => ['artifact_id' => $artifactId, 'change_type' => 'create', 'risk_level' => strtoupper($riskLevel)],
            'human_summary' => $summary,
            'machine_summary' => ['safe_target_path' => true, 'first_apply' => true],
        ];
    }

    /** @return array<string,string> */
    private static function generatedModuleFiles(array $context, array $snapshot, ?string $baseOverride = null): array
    {
        $base = $baseOverride !== null ? $baseOverride : (string)$context['relative_path'];
        $compileId = (string)($snapshot['compile_id'] ?? '');
        $manifest = [
            'schema_version' => 'studio.generated-module.v1',
            'generated_by' => 'studio',
            'generator_id' => self::FIRST_APPLY_GENERATOR_ID,
            'compile_id' => $compileId,
            'app_key' => (string)$context['app_key'],
            'module_key' => (string)$context['module_key'],
            'display_name' => (string)$context['display_name'],
            'app_display_name' => (string)$context['app_display_name'],
            'route_path' => (string)$context['route_path'],
            'view_file' => (string)$context['view_file'],
            'provider' => [
                'namespace' => (string)$context['provider_namespace'],
                'class' => (string)$context['provider_class'],
                'file' => 'Providers/' . (string)$context['provider_class'] . '.php',
            ],
            'snapshot_id' => (string)($snapshot['snapshot_id'] ?? ''),
            'snapshot_hash' => (string)($snapshot['snapshot_hash'] ?? ''),
            'created_at' => gmdate('c'),
            'live_path' => (string)$context['relative_path'],
            'navigation' => $context['navigation'],
            'fields' => is_array($context['fields'] ?? null) ? array_values($context['fields']) : [],
            'templates' => $context['templates'],
            'safety' => [
                'output_root' => self::GENERATED_ROOT,
                'temp_root' => self::GENERATED_TMP_ROOT,
                'raw_php_injection_allowed' => false,
                'db_schema_execution_allowed' => false,
            ],
            'styles' => [[
                'key' => 'generated.' . (string)$context['app_key'] . '.' . (string)$context['module_key'],
                'path' => 'styles.css',
                'scope' => 'module',
                'module' => (string)$context['module_key'],
                'surfaces' => ['admin', 'operator'],
                'order' => 300,
            ]],
        ];
        $module = [
            'schema_version' => 'studio.generated-module-contract.v1',
            'app_key' => (string)$context['app_key'],
            'module_key' => (string)$context['module_key'],
            'display_name' => (string)$context['display_name'],
            'route_path' => (string)$context['route_path'],
            'template' => (string)$context['templates']['module'],
            'compile_id' => $compileId,
            'snapshot_id' => (string)($snapshot['snapshot_id'] ?? ''),
            'fields' => is_array($context['fields'] ?? null) ? array_values($context['fields']) : [],
        ];

        return [
            $base . '/manifest.json' => (string)json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n",
            $base . '/module.json' => (string)json_encode($module, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n",
            $base . '/routes.php' => self::generatedRoutesFile($context),
            $base . '/navigation.php' => self::generatedNavigationFile($context),
            $base . '/styles.css' => self::generatedModuleStylesFile($context),
            $base . '/Controllers/' . (string)$context['controller_class'] . '.php' => self::generatedControllerFile($context),
            $base . '/Providers/' . (string)$context['provider_class'] . '.php' => self::generatedProviderFile($context),
            $base . '/Views/' . (string)$context['view_file'] => self::generatedViewFile($context),
            $base . '/lang/en.php' => self::generatedLocaleFile($context, 'en'),
            $base . '/lang/ja.php' => self::generatedLocaleFile($context, 'ja'),
            $base . '/lang/ne.php' => self::generatedLocaleFile($context, 'ne'),
        ];
    }

    private static function generatedRoutesFile(array $context): string
    {
        return "<?php\n"
            . "declare(strict_types=1);\n\n"
            . "return [\n"
            . "    'contract' => 'studio.generated-route.v1',\n"
            . "    'route_path' => " . var_export((string)$context['route_path'], true) . ",\n"
            . "    'controller' => " . var_export((string)$context['controller_class'], true) . ",\n"
            . "    'view' => " . var_export((string)$context['view_file'], true) . ",\n"
            . "];\n";
    }

    private static function generatedModuleStylesFile(array $context): string
    {
        $appKey = (string)$context['app_key'];
        $moduleKey = (string)$context['module_key'];
        $viewKey = (string)($context['view_key'] ?? 'index');
        $generated = "/* studio:generated-start  app={$appKey} module={$moduleKey}  do-not-edit */\n"
            . "[data-studio-app=\"{$appKey}\"][data-studio-module=\"{$moduleKey}\"] {\n"
            . "    /* module root scope */\n"
            . "}\n"
            . "[data-studio-app=\"{$appKey}\"][data-studio-module=\"{$moduleKey}\"] [data-view-id=\"{$viewKey}\"] {\n"
            . "    /* view: {$viewKey} */\n"
            . "}\n"
            . "/* studio:generated-end */\n";

        $userBlock = self::extractUserStylesBlock(self::absolutePath((string)$context['relative_path'] . '/styles.css'));
        return $generated . "\n" . $userBlock;
    }

    private static function extractUserStylesBlock(string $liveAbsolutePath): string
    {
        $default = "/* studio:user-start */\n/* Hand-edited styles below this line are preserved across Studio re-applies. */\n/* studio:user-end */\n";
        if (!is_file($liveAbsolutePath)) {
            return $default;
        }
        $existing = @file_get_contents($liveAbsolutePath);
        if (!is_string($existing) || $existing === '') {
            return $default;
        }
        if (preg_match('#/\\*\\s*studio:user-start\\s*\\*/.*?/\\*\\s*studio:user-end\\s*\\*/#s', $existing, $matches)) {
            return $matches[0] . "\n";
        }
        return $default;
    }

    private static function generatedAppStylesFile(string $appKey): string
    {
        return "/* studio:generated-start  app={$appKey}  do-not-edit */\n"
            . "[data-studio-app=\"{$appKey}\"] {\n"
            . "    /* app-scope root */\n"
            . "}\n"
            . "/* studio:generated-end */\n\n"
            . "/* studio:user-start */\n"
            . "/* Hand-edited app-level styles below this line are preserved. */\n"
            . "/* studio:user-end */\n";
    }

    private static function generatedAppManifestFile(string $appKey, string $appDisplayName): string
    {
        $manifest = [
            'schema_version' => 'studio.generated-app.v1',
            'generated_by' => 'studio',
            'app_key' => $appKey,
            'display_name' => $appDisplayName,
            'created_at' => gmdate('c'),
            'styles' => [[
                'key' => 'generated.' . $appKey . '.app',
                'path' => 'styles.css',
                'scope' => 'app',
                'surfaces' => ['admin', 'operator'],
                'order' => 200,
            ]],
        ];
        return (string)json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }

    private static function isSafeGeneratedAppPath(string $path): bool
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\') || str_contains($path, '..')) {
            return false;
        }
        return (bool)preg_match('#^apps/Generated/[a-z0-9_]+$#', $path);
    }

    private static function ensureGeneratedAppFiles(array $context): void
    {
        $appKey = (string)$context['app_key'];
        if ($appKey === '' || !preg_match('#^[a-z0-9_]+$#', $appKey)) {
            return;
        }
        $appRelative = self::GENERATED_ROOT . '/' . $appKey;
        if (!self::isSafeGeneratedAppPath($appRelative)) {
            return;
        }
        $appAbsolute = self::absolutePath($appRelative);
        if (!is_dir($appAbsolute)) {
            return;
        }
        $manifestAbsolute = $appAbsolute . '/manifest.json';
        if (!is_file($manifestAbsolute)) {
            $appDisplayName = (string)($context['app_display_name'] ?? $appKey);
            file_put_contents($manifestAbsolute, self::generatedAppManifestFile($appKey, $appDisplayName), LOCK_EX);
        }
        $stylesAbsolute = $appAbsolute . '/styles.css';
        if (!is_file($stylesAbsolute)) {
            file_put_contents($stylesAbsolute, self::generatedAppStylesFile($appKey), LOCK_EX);
        }
    }

    private static function generatedNavigationFile(array $context): string
    {
        $navigation = is_array($context['navigation'] ?? null) ? $context['navigation'] : [];
        return "<?php\n"
            . "declare(strict_types=1);\n\n"
            . "return [\n"
            . "    'contract' => 'navigation.v1',\n"
            . "    'owner' => " . var_export((string)$context['app_key'], true) . ",\n"
            . "    'items' => [[\n"
            . "        'source_key' => 'studio.generated." . (string)$context['app_key'] . "." . (string)$context['module_key'] . "',\n"
            . "        'group' => 'Apps',\n"
            . "        'section' => " . var_export((string)($navigation['section'] ?? ''), true) . ",\n"
            . "        'module' => " . var_export((string)$context['module_key'], true) . ",\n"
            . "        'owner' => " . var_export((string)$context['app_key'], true) . ",\n"
            . "        'key' => " . var_export((string)($navigation['key'] ?? ''), true) . ",\n"
            . "        'label' => " . var_export((string)($navigation['label'] ?? ''), true) . ",\n"
            . "        'url' => " . var_export((string)$context['route_path'], true) . ",\n"
            . "        'visible_if' => 'role_platform_admin_or_sysadmin',\n"
            . "        'nav_visible' => true,\n"
            . "        'order' => " . (int)($navigation['order'] ?? 50) . ",\n"
            . "        'priority' => " . (int)($navigation['priority'] ?? 80) . ",\n"
            . "    ]],\n"
            . "];\n";
    }

    private static function generatedControllerFile(array $context): string
    {
        return "<?php\n"
            . "declare(strict_types=1);\n\n"
            . "final class " . (string)$context['controller_class'] . "\n"
            . "{\n"
            . "    public static function viewData(): array\n"
            . "    {\n"
            . "        return [\n"
            . "            'pageTitle' => " . var_export((string)$context['display_name'], true) . ",\n"
            . "            'generatedModule' => [\n"
            . "                'app_key' => " . var_export((string)$context['app_key'], true) . ",\n"
            . "                'module_key' => " . var_export((string)$context['module_key'], true) . ",\n"
            . "                'route_path' => " . var_export((string)$context['route_path'], true) . ",\n"
            . "            ],\n"
            . "        ];\n"
            . "    }\n"
            . "}\n";
    }

    private static function generatedProviderFile(array $context): string
    {
        $fields = is_array($context['fields'] ?? null) ? array_values($context['fields']) : [];
        return "<?php\n"
            . "declare(strict_types=1);\n\n"
            . "namespace " . (string)$context['provider_namespace'] . ";\n\n"
            . "final class " . (string)$context['provider_class'] . "\n"
            . "{\n"
            . "    /** @return array<int,array<string,mixed>> */\n"
            . "    public static function fields(): array\n"
            . "    {\n"
            . "        return " . var_export($fields, true) . ";\n"
            . "    }\n\n"
            . "    /** @param array<string,mixed> \$query @return array<string,mixed> */\n"
            . "    public static function records(array \$query = []): array\n"
            . "    {\n"
            . "        \$rows = self::readRows();\n"
            . "        \$search = strtolower(trim((string)(\$query['q'] ?? '')));\n"
            . "        if (\$search !== '') {\n"
            . "            \$rows = array_values(array_filter(\$rows, static function (array \$row) use (\$search): bool {\n"
            . "                foreach (\$row as \$value) {\n"
            . "                    if (str_contains(strtolower((string)\$value), \$search)) {\n"
            . "                        return true;\n"
            . "                    }\n"
            . "                }\n"
            . "                return false;\n"
            . "            }));\n"
            . "        }\n"
            . "        \$filters = [];\n"
            . "        foreach (self::fields() as \$field) {\n"
            . "            \$key = (string)(\$field['key'] ?? '');\n"
            . "            if (\$key === '' || (string)(\$field['type'] ?? '') !== 'select') {\n"
            . "                continue;\n"
            . "            }\n"
            . "            \$filterKey = 'filter_' . \$key;\n"
            . "            \$filterValue = trim((string)(\$query[\$filterKey] ?? ''));\n"
            . "            if (\$filterValue === '') {\n"
            . "                continue;\n"
            . "            }\n"
            . "            \$filters[\$key] = \$filterValue;\n"
            . "            \$rows = array_values(array_filter(\$rows, static fn(array \$row): bool => (string)(\$row[\$key] ?? '') === \$filterValue));\n"
            . "        }\n"
            . "        \$sort = self::fieldKey((string)(\$query['sort'] ?? ''));\n"
            . "        \$dir = strtolower((string)(\$query['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';\n"
            . "        if (\$sort !== '' && self::fieldByKey(\$sort) !== null) {\n"
            . "            usort(\$rows, static function (array \$a, array \$b) use (\$sort, \$dir): int {\n"
            . "                \$cmp = strnatcasecmp((string)(\$a[\$sort] ?? ''), (string)(\$b[\$sort] ?? ''));\n"
            . "                return \$dir === 'desc' ? -\$cmp : \$cmp;\n"
            . "            });\n"
            . "        }\n"
            . "        return ['rows' => array_map([self::class, 'formatRow'], \$rows), 'filters' => \$filters, 'sort' => ['field' => \$sort, 'dir' => \$dir]];\n"
            . "    }\n\n"
            . "    /** @param array<string,mixed> \$input @return array<string,mixed> */\n"
            . "    public static function save(array \$input): array\n"
            . "    {\n"
            . "        \$validation = self::validate(\$input);\n"
            . "        if (empty(\$validation['valid'])) {\n"
            . "            return ['ok' => false, 'message' => 'validation_failed', 'errors' => \$validation['errors'] ?? []];\n"
            . "        }\n"
            . "        \$rows = self::readRows();\n"
            . "        \$rowId = self::recordId((string)(\$input['row_id'] ?? ''));\n"
            . "        \$row = \$rowId !== '' ? self::findRow(\$rows, \$rowId) : [];\n"
            . "        \$row['id'] = \$rowId !== '' ? \$rowId : self::newId();\n"
            . "        \$row['updated_at'] = gmdate('c');\n"
            . "        if (!isset(\$row['created_at'])) {\n"
            . "            \$row['created_at'] = gmdate('c');\n"
            . "        }\n"
            . "        foreach (self::fields() as \$field) {\n"
            . "            \$key = (string)(\$field['key'] ?? '');\n"
            . "            if (\$key === '') {\n"
            . "                continue;\n"
            . "            }\n"
            . "            \$row[\$key] = \$validation['values'][\$key] ?? '';\n"
            . "        }\n"
            . "        \$updated = false;\n"
            . "        foreach (\$rows as \$index => \$existing) {\n"
            . "            if ((string)(\$existing['id'] ?? '') === (string)\$row['id']) {\n"
            . "                \$rows[\$index] = \$row;\n"
            . "                \$updated = true;\n"
            . "                break;\n"
            . "            }\n"
            . "        }\n"
            . "        if (!\$updated) {\n"
            . "            \$rows[] = \$row;\n"
            . "        }\n"
            . "        return self::writeRows(\$rows) ? ['ok' => true, 'message' => 'generated_row_saved', 'row_id' => (string)\$row['id']] : ['ok' => false, 'message' => 'generated_row_save_failed'];\n"
            . "    }\n\n"
            . "    /** @param array<string,mixed> \$input @return array<string,mixed> */\n"
            . "    public static function validate(array \$input): array\n"
            . "    {\n"
            . "        \$errors = [];\n"
            . "        \$values = [];\n"
            . "        foreach (self::fields() as \$field) {\n"
            . "            \$key = (string)(\$field['key'] ?? '');\n"
            . "            if (\$key === '') {\n"
            . "                continue;\n"
            . "            }\n"
            . "            \$value = trim((string)(\$input[\$key] ?? (\$field['default'] ?? '')));\n"
            . "            if (!empty(\$field['required']) && \$value === '') {\n"
            . "                \$errors[] = 'required:' . \$key;\n"
            . "            }\n"
            . "            \$type = (string)(\$field['type'] ?? 'string');\n"
            . "            if (\$type === 'integer') {\n"
            . "                if (\$value !== '' && filter_var(\$value, FILTER_VALIDATE_INT) === false) {\n"
            . "                    \$errors[] = 'type_integer:' . \$key;\n"
            . "                }\n"
            . "                \$values[\$key] = \$value === '' ? '' : (string)(int)\$value;\n"
            . "                continue;\n"
            . "            }\n"
            . "            if (\$type === 'select') {\n"
            . "                \$options = array_map('strval', (array)(\$field['options'] ?? []));\n"
            . "                if (\$value === '' && \$options !== []) {\n"
            . "                    \$value = (string)(\$field['default'] ?? \$options[0]);\n"
            . "                }\n"
            . "                if (\$value !== '' && !in_array(\$value, \$options, true)) {\n"
            . "                    \$errors[] = 'option:' . \$key;\n"
            . "                    \$value = (string)(\$field['default'] ?? (\$options[0] ?? ''));\n"
            . "                }\n"
            . "                \$values[\$key] = \$value;\n"
            . "                continue;\n"
            . "            }\n"
            . "            \$values[\$key] = substr(preg_replace('/\\\\s+/', ' ', strip_tags(\$value)) ?? '', 0, 240);\n"
            . "        }\n"
            . "        return ['valid' => \$errors === [], 'errors' => array_values(array_unique(\$errors)), 'values' => \$values];\n"
            . "    }\n\n"
            . "    /** @param array<string,mixed> \$row @return array<string,mixed> */\n"
            . "    public static function formatRow(array \$row): array\n"
            . "    {\n"
            . "        foreach (self::fields() as \$field) {\n"
            . "            \$key = (string)(\$field['key'] ?? '');\n"
            . "            if (\$key !== '' && !array_key_exists(\$key, \$row)) {\n"
            . "                \$row[\$key] = (string)(\$field['default'] ?? '');\n"
            . "            }\n"
            . "        }\n"
            . "        return \$row;\n"
            . "    }\n\n"
            . "    private static function dataPath(): string\n"
            . "    {\n"
            . "        return rtrim((string)(defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 5)), '/') . '/storage/appstudio/generated_data/" . (string)$context['app_key'] . "/" . (string)$context['module_key'] . ".json';\n"
            . "    }\n\n"
            . "    /** @return array<int,array<string,mixed>> */\n"
            . "    private static function readRows(): array\n"
            . "    {\n"
            . "        \$path = self::dataPath();\n"
            . "        if (!is_file(\$path)) {\n"
            . "            return [];\n"
            . "        }\n"
            . "        \$raw = @file_get_contents(\$path);\n"
            . "        \$decoded = \$raw !== false ? json_decode((string)\$raw, true) : null;\n"
            . "        \$rows = is_array(\$decoded['rows'] ?? null) ? \$decoded['rows'] : [];\n"
            . "        return array_values(array_filter(\$rows, 'is_array'));\n"
            . "    }\n\n"
            . "    /** @param array<int,array<string,mixed>> \$rows */\n"
            . "    private static function writeRows(array \$rows): bool\n"
            . "    {\n"
            . "        \$path = self::dataPath();\n"
            . "        \$dir = dirname(\$path);\n"
            . "        if (!is_dir(\$dir) && !mkdir(\$dir, 0775, true)) {\n"
            . "            return false;\n"
            . "        }\n"
            . "        \$payload = ['schema_version' => 'studio.generated-data.v1', 'app_key' => '" . (string)$context['app_key'] . "', 'module_key' => '" . (string)$context['module_key'] . "', 'updated_at' => gmdate('c'), 'rows' => array_values(\$rows)];\n"
            . "        \$json = json_encode(\$payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);\n"
            . "        if (\$json === false || json_decode(\$json, true) === null) {\n"
            . "            return false;\n"
            . "        }\n"
            . "        \$tmp = \$dir . '/." . (string)$context['module_key'] . ".' . bin2hex(random_bytes(4)) . '.tmp';\n"
            . "        return file_put_contents(\$tmp, \$json, LOCK_EX) !== false && rename(\$tmp, \$path);\n"
            . "    }\n\n"
            . "    /** @param array<int,array<string,mixed>> \$rows @return array<string,mixed> */\n"
            . "    private static function findRow(array \$rows, string \$rowId): array\n"
            . "    {\n"
            . "        foreach (\$rows as \$row) {\n"
            . "            if ((string)(\$row['id'] ?? '') === \$rowId) {\n"
            . "                return \$row;\n"
            . "            }\n"
            . "        }\n"
            . "        return [];\n"
            . "    }\n\n"
            . "    private static function recordId(string \$value): string\n"
            . "    {\n"
            . "        return preg_match('/^[a-f0-9\\\\-]{16,64}$/i', \$value) ? \$value : '';\n"
            . "    }\n\n"
            . "    private static function newId(): string\n"
            . "    {\n"
            . "        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));\n"
            . "    }\n\n"
            . "    private static function fieldKey(string \$value): string\n"
            . "    {\n"
            . "        \$value = strtolower(trim(str_replace('-', '_', \$value)));\n"
            . "        \$value = preg_replace('/[^a-z0-9_]+/', '_', \$value) ?? '';\n"
            . "        return trim(\$value, '_');\n"
            . "    }\n\n"
            . "    private static function fieldByKey(string \$key): ?array\n"
            . "    {\n"
            . "        foreach (self::fields() as \$field) {\n"
            . "            if ((string)(\$field['key'] ?? '') === \$key) {\n"
            . "                return \$field;\n"
            . "            }\n"
            . "        }\n"
            . "        return null;\n"
            . "    }\n"
            . "}\n";
    }

    private static function generatedViewFile(array $context): string
    {
        return "<?php\n"
            . "declare(strict_types=1);\n"
            . "\$module = is_array(\$generatedModule ?? null) ? \$generatedModule : [];\n"
            . "\$fields = is_array(\$generatedFields ?? null) ? \$generatedFields : [];\n"
            . "\$rows = is_array(\$generatedRows ?? null) ? \$generatedRows : [];\n"
            . "\$editRow = is_array(\$generatedEditRow ?? null) ? \$generatedEditRow : [];\n"
            . "\$search = (string)(\$generatedSearch ?? '');\n"
            . "\$filters = is_array(\$generatedFilters ?? null) ? \$generatedFilters : [];\n"
            . "\$sort = is_array(\$generatedSort ?? null) ? \$generatedSort : [];\n"
            . "\$flash = is_array(\$generatedFlash ?? null) ? \$generatedFlash : [];\n"
            . "\$routePath = (string)(\$module['route_path'] ?? " . var_export((string)$context['route_path'], true) . ");\n"
            . "\$locale = strtolower((string)(\$_SESSION['locale'] ?? 'en'));\n"
            . "\$labels = [\n"
            . "    'en' => ['search' => 'Search', 'search_placeholder' => 'Search parts', 'all' => 'All', 'new_record' => 'New record', 'edit_record' => 'Edit record', 'save' => 'Save', 'reset' => 'Reset', 'actions' => 'Actions', 'edit' => 'Edit', 'no_rows' => 'No records yet.', 'saved' => 'Saved.', 'failed' => 'Save failed.', 'metadata' => 'Module metadata', 'app' => 'App', 'module' => 'Module', 'route' => 'Route'],\n"
            . "    'ja' => ['search' => '検索', 'search_placeholder' => '部品を検索', 'all' => 'すべて', 'new_record' => '新規レコード', 'edit_record' => 'レコード編集', 'save' => '保存', 'reset' => 'リセット', 'actions' => '操作', 'edit' => '編集', 'no_rows' => 'レコードはまだありません。', 'saved' => '保存しました。', 'failed' => '保存に失敗しました。', 'metadata' => 'モジュール情報', 'app' => 'アプリ', 'module' => 'モジュール', 'route' => 'ルート'],\n"
            . "    'ne' => ['search' => 'खोज', 'search_placeholder' => 'Parts खोज्नुहोस्', 'all' => 'सबै', 'new_record' => 'नयाँ रेकर्ड', 'edit_record' => 'रेकर्ड सम्पादन', 'save' => 'सेभ', 'reset' => 'रिसेट', 'actions' => 'कार्यहरू', 'edit' => 'सम्पादन', 'no_rows' => 'अहिलेसम्म रेकर्ड छैन।', 'saved' => 'सेभ भयो।', 'failed' => 'सेभ असफल भयो।', 'metadata' => 'Module metadata', 'app' => 'App', 'module' => 'Module', 'route' => 'Route'],\n"
            . "];\n"
            . "\$tr = \$labels[\$locale] ?? \$labels['en'];\n"
            . "?>\n"
            . "<section class=\"card\">\n"
            . "  <h1><?= e((string)(\$pageTitle ?? " . var_export((string)$context['display_name'], true) . ")) ?></h1>\n"
            . "  <p class=\"muted\"><?= e(" . var_export((string)$context['app_display_name'], true) . ") ?> / <?= e((string)(\$module['display_name'] ?? " . var_export((string)$context['display_name'], true) . ")) ?></p>\n"
            . "  <?php if (\$flash !== []): ?><div class=\"notice <?= e((string)(\$flash['status'] ?? '')) ?>\"><?= e((string)(\$tr[(string)(\$flash['status'] ?? '')] ?? \$flash['message'] ?? '')) ?></div><?php endif; ?>\n"
            . "  <?php if (\$fields !== []): ?>\n"
            . "    <form method=\"get\" action=\"<?= e(\$routePath) ?>\" class=\"toolbar\">\n"
            . "      <label><?= e((string)\$tr['search']) ?><input class=\"input\" type=\"search\" name=\"q\" value=\"<?= e(\$search) ?>\" placeholder=\"<?= e((string)\$tr['search_placeholder']) ?>\"></label>\n"
            . "      <?php foreach (\$fields as \$field): if ((string)(\$field['type'] ?? '') !== 'select') { continue; } \$key = (string)(\$field['key'] ?? ''); \$filterKey = 'filter_' . \$key; ?><label><?= e((string)(\$field['label'] ?? \$key)) ?><select name=\"<?= e(\$filterKey) ?>\"><option value=\"\"><?= e((string)\$tr['all']) ?></option><?php foreach ((array)(\$field['options'] ?? []) as \$option): ?><option value=\"<?= e((string)\$option) ?>\" <?= (string)(\$filters[\$key] ?? '') === (string)\$option ? 'selected' : '' ?>><?= e((string)\$option) ?></option><?php endforeach; ?></select></label><?php endforeach; ?>\n"
            . "      <button class=\"btn\" type=\"submit\"><?= e((string)\$tr['search']) ?></button>\n"
            . "      <a class=\"button secondary\" href=\"<?= e(\$routePath) ?>\"><?= e((string)\$tr['reset']) ?></a>\n"
            . "    </form>\n"
            . "    <div class=\"table-wrap\">\n"
            . "      <table class=\"table\">\n"
            . "        <thead><tr><?php foreach (\$fields as \$field): \$key = (string)(\$field['key'] ?? ''); \$nextDir = ((string)(\$sort['field'] ?? '') === \$key && (string)(\$sort['dir'] ?? 'asc') === 'asc') ? 'desc' : 'asc'; \$sortUrl = \$routePath . '?' . http_build_query(array_merge(\$_GET, ['sort' => \$key, 'dir' => \$nextDir])); ?><th><a href=\"<?= e(\$sortUrl) ?>\"><?= e((string)(\$field['label'] ?? \$key)) ?></a></th><?php endforeach; ?><th><?= e((string)\$tr['actions']) ?></th></tr></thead>\n"
            . "        <tbody>\n"
            . "          <?php if (\$rows === []): ?><tr><td colspan=\"<?= count(\$fields) + 1 ?>\"><?= e((string)\$tr['no_rows']) ?></td></tr><?php endif; ?>\n"
            . "          <?php foreach (\$rows as \$row): ?><tr><?php foreach (\$fields as \$field): \$key = (string)(\$field['key'] ?? ''); ?><td><?= e((string)(\$row[\$key] ?? '')) ?></td><?php endforeach; ?><td><a href=\"<?= e(\$routePath . '?edit=' . rawurlencode((string)(\$row['id'] ?? ''))) ?>\"><?= e((string)\$tr['edit']) ?></a></td></tr><?php endforeach; ?>\n"
            . "        </tbody>\n"
            . "      </table>\n"
            . "    </div>\n"
            . "    <form method=\"post\" action=\"<?= e(\$routePath) ?>\" class=\"form-panel\">\n"
            . "      <h2><?= e(\$editRow !== [] ? (string)\$tr['edit_record'] : (string)\$tr['new_record']) ?></h2>\n"
            . "      <input class=\"input\" type=\"hidden\" name=\"csrf\" value=\"<?= e((string)(\$csrf ?? '')) ?>\">\n"
            . "      <input class=\"input\" type=\"hidden\" name=\"row_id\" value=\"<?= e((string)(\$editRow['id'] ?? '')) ?>\">\n"
            . "      <?php foreach (\$fields as \$field): \$key = (string)(\$field['key'] ?? ''); \$type = (string)(\$field['type'] ?? 'string'); ?>\n"
            . "        <label><?= e((string)(\$field['label'] ?? \$key)) ?>\n"
            . "          <?php if (\$type === 'select'): ?><select name=\"<?= e(\$key) ?>\"><?php foreach ((array)(\$field['options'] ?? []) as \$option): ?><option value=\"<?= e((string)\$option) ?>\" <?= (string)(\$editRow[\$key] ?? '') === (string)\$option ? 'selected' : '' ?>><?= e((string)\$option) ?></option><?php endforeach; ?></select>\n"
            . "          <?php else: ?><input class=\"input\" type=\"<?= \$type === 'integer' ? 'number' : 'text' ?>\" name=\"<?= e(\$key) ?>\" value=\"<?= e((string)(\$editRow[\$key] ?? '')) ?>\"><?php endif; ?>\n"
            . "        </label>\n"
            . "      <?php endforeach; ?>\n"
            . "      <button class=\"btn\" type=\"submit\"><?= e((string)\$tr['save']) ?></button>\n"
            . "    </form>\n"
            . "  <?php else: ?>\n"
            . "    <h2><?= e((string)\$tr['metadata']) ?></h2>\n"
            . "    <div class=\"table-wrap\"><table class=\"table\"><tbody><tr><td><?= e((string)\$tr['app']) ?></td><td><?= e((string)(\$module['app_key'] ?? " . var_export((string)$context['app_key'], true) . ")) ?></td></tr><tr><td><?= e((string)\$tr['module']) ?></td><td><?= e((string)(\$module['module_key'] ?? " . var_export((string)$context['module_key'], true) . ")) ?></td></tr><tr><td><?= e((string)\$tr['route']) ?></td><td><code><?= e(\$routePath) ?></code></td></tr></tbody></table></div>\n"
            . "  <?php endif; ?>\n"
            . "</section>\n";
    }

    private static function generatedLocaleFile(array $context, string $locale): string
    {
        $appKey = (string)$context['app_key'];
        $moduleKey = (string)$context['module_key'];
        $displayName = (string)($context['display_name'] ?? self::displayName($moduleKey));
        $appDisplayName = (string)($context['app_display_name'] ?? self::displayName($appKey));

        // Default English placeholders
        $titleMap = [
            'en' => $displayName,
            'ja' => $displayName,
            'ne' => $displayName,
        ];
        $displayNameMap = [
            'en' => $appDisplayName . ' ' . $displayName,
            'ja' => $appDisplayName . ' ' . $displayName,
            'ne' => $appDisplayName . ' ' . $displayName,
        ];

        $localizedTitle = $titleMap[$locale] ?? $titleMap['en'];
        $localizedDisplayName = $displayNameMap[$locale] ?? $displayNameMap['en'];

        // Generate locale entries following the new convention: app.{app}.{module}.{view}.{type}.title/display_name
        $entries = [
            'app.' . $appKey . '.' . $moduleKey . '.index.table.title' => $localizedTitle,
            'app.' . $appKey . '.' . $moduleKey . '.index.table.display_name' => $localizedDisplayName,
            'app.' . $appKey . '.' . $moduleKey . '.title' => $localizedTitle,
            'app.' . $appKey . '.' . $moduleKey . '.display_name' => $localizedDisplayName,
        ];

        $php = "<?php\nreturn [\n";
        foreach ($entries as $key => $value) {
            $php .= "    " . var_export($key, true) . " => " . var_export($value, true) . ",\n";
        }
        $php .= "];\n";

        return $php;
    }

    private static function writeGeneratedTempFile(string $relativePath, string $content): string
    {
        if (!self::isSafeGeneratedTempFilePath($relativePath)) {
            return 'failed';
        }
        $root = rtrim((string)(defined('APP_ROOT') ? APP_ROOT : ''), '/');
        $absolutePath = $root . '/' . $relativePath;
        if (is_file($absolutePath)) {
            return 'failed';
        }
        $dir = dirname($absolutePath);
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
            return 'failed';
        }
        return file_put_contents($absolutePath, $content, LOCK_EX) !== false ? 'created' : 'failed';
    }

    private static function isSafeGeneratedTempFilePath(string $path): bool
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\') || str_contains($path, '..')) {
            return false;
        }
        return (bool)preg_match('#^apps/Generated/tmp/[a-zA-Z0-9_\\-]+/(manifest\\.json|module\\.json|routes\\.php|navigation\\.php|styles\\.css|lang/[a-z]{2}\\.php|Controllers/[A-Za-z0-9_]+\\.php|Providers/[A-Za-z0-9_]+Provider\\.php|Views/[a-z0-9_]+\\.php)$#', $path);
    }

    private static function isSafeGeneratedLivePath(string $path): bool
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\') || str_contains($path, '..')) {
            return false;
        }
        return (bool)preg_match('#^apps/Generated/[a-z0-9_]+/[a-z0-9_]+$#', $path);
    }

    private static function isSafeGeneratedRoutePath(string $routePath): bool
    {
        if ($routePath === '' || str_contains($routePath, '..') || str_contains($routePath, '\\')) {
            return false;
        }
        return (bool)preg_match('#^/apps/[a-z0-9][a-z0-9_\\-]*(/[a-z0-9][a-z0-9_\\-]*){1,3}$#', $routePath);
    }

    private static function generatedKey(string $value): string
    {
        $key = strtolower(trim($value));
        $key = str_replace('-', '_', $key);
        $key = preg_replace('/[^a-z0-9_]+/', '_', $key) ?? '';
        $key = trim($key, '_');
        return $key;
    }

    /** @param array<string,mixed> $layout @return array<string,mixed> */
    private static function normalizeVisualLayout(array $layout, string $viewKind): array
    {
        $itemsRaw = is_array($layout['items'] ?? null) ? $layout['items'] : [];
        $relationsRaw = is_array($layout['relations'] ?? null) ? $layout['relations'] : [];
        $items = [];
        foreach ($itemsRaw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $id = self::generatedKey((string)($item['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $component = self::generatedKey((string)($item['component'] ?? 'table'));
            if (!in_array($component, ['table', 'form', 'kpi_card', 'text_block'], true)) {
                $component = 'text_block';
            }
            $x = max(0, min(11, (int)($item['x'] ?? 0)));
            $y = max(0, (int)($item['y'] ?? 0));
            $w = max(1, min(12, (int)($item['w'] ?? 12)));
            if ($x + $w > 12) {
                $w = 12 - $x;
            }
            $h = max(1, min(24, (int)($item['h'] ?? 4)));
            $group = self::generatedKey((string)($item['group'] ?? ''));
            if ($group === '') {
                $group = 'default';
            }

            $items[] = [
                'id' => $id,
                'x' => $x,
                'y' => $y,
                'w' => $w,
                'h' => $h,
                'group' => $group,
                'component' => $component,
                'props' => self::normalizeVisualComponentProps($component, is_array($item['props'] ?? null) ? $item['props'] : []),
                'data_binding' => self::normalizeVisualDataBinding($component, (string)($item['data_binding'] ?? '')),
            ];
        }

        if ($items === []) {
            $defaultComponent = $viewKind === 'form' ? 'form' : 'table';
            $items[] = [
                'id' => $defaultComponent . '_main',
                'x' => 0,
                'y' => 0,
                'w' => 12,
                'h' => 6,
                'group' => 'default',
                'component' => $defaultComponent,
                'props' => self::normalizeVisualComponentProps($defaultComponent, []),
                'data_binding' => self::normalizeVisualDataBinding($defaultComponent, ''),
            ];
            if ($viewKind === 'dashboard') {
                $items[] = [
                    'id' => 'kpi_card_1',
                    'x' => 0,
                    'y' => 6,
                    'w' => 3,
                    'h' => 3,
                    'group' => 'summary',
                    'component' => 'kpi_card',
                    'props' => self::normalizeVisualComponentProps('kpi_card', []),
                    'data_binding' => self::normalizeVisualDataBinding('kpi_card', ''),
                ];
            }
        }

        $itemIds = array_values(array_map(static fn(array $item): string => (string)($item['id'] ?? ''), $items));
        $relations = [];
        foreach ($relationsRaw as $relation) {
            if (!is_array($relation)) {
                continue;
            }
            $sourceId = self::generatedKey((string)($relation['source_id'] ?? ''));
            $targetId = self::generatedKey((string)($relation['target_id'] ?? ''));
            $type = self::generatedKey((string)($relation['type'] ?? 'affects_table'));
            if ($sourceId === '' || $targetId === '' || !in_array($sourceId, $itemIds, true) || !in_array($targetId, $itemIds, true)) {
                continue;
            }
            if (!in_array($type, ['affects_table', 'filter_to_table', 'filter_to_kpi', 'form_refresh_table', 'table_to_kpi_derived'], true)) {
                $type = 'affects_table';
            }
            $relations[] = [
                'source_id' => $sourceId,
                'target_id' => $targetId,
                'type' => $type,
            ];
        }

        return [
            'type' => 'grid',
            'columns' => 12,
            'rows' => 'auto',
            'items' => $items,
            'relations' => array_values($relations),
        ];
    }

    /** @return array<string,array{required_props:array<int,string>,default_config:array<string,mixed>}> */
    private static function visualComponentSchema(): array
    {
        return [
            'filter' => [
                'required_props' => ['label', 'field', 'placeholder'],
                'default_config' => ['label' => 'Filter', 'field' => '', 'placeholder' => 'Type to filter'],
            ],
            'table' => [
                'required_props' => ['title', 'sample_rows', 'density'],
                'default_config' => ['title' => 'Table', 'sample_rows' => 3, 'density' => 'comfortable'],
            ],
            'form' => [
                'required_props' => ['title', 'submit_label', 'show_required'],
                'default_config' => ['title' => 'Form', 'submit_label' => 'Submit', 'show_required' => true],
            ],
            'kpi_card' => [
                'required_props' => ['label', 'value', 'delta'],
                'default_config' => ['label' => 'KPI Card', 'value' => '42', 'delta' => '+4.8%'],
            ],
            'text_block' => [
                'required_props' => ['title', 'body', 'align'],
                'default_config' => ['title' => 'Text Block', 'body' => 'Text Block', 'align' => 'left'],
            ],
        ];
    }

    /** @param array<string,mixed> $rawProps @return array<string,mixed> */
    private static function normalizeVisualComponentProps(string $component, array $rawProps): array
    {
        $schema = self::visualComponentSchema();
        $componentSchema = $schema[$component] ?? $schema['text_block'];
        $defaults = is_array($componentSchema['default_config'] ?? null) ? $componentSchema['default_config'] : [];

        $props = [];
        foreach ($defaults as $key => $defaultValue) {
            $value = array_key_exists($key, $rawProps) ? $rawProps[$key] : $defaultValue;
            if (is_bool($defaultValue)) {
                $props[$key] = (bool)$value;
                continue;
            }
            if (is_int($defaultValue)) {
                $props[$key] = (int)$value;
                continue;
            }
            $props[$key] = trim((string)$value);
            if ($props[$key] === '') {
                $props[$key] = (string)$defaultValue;
            }
        }

        return $props;
    }

    private static function normalizeVisualDataBinding(string $component, string $binding): string
    {
        $binding = trim($binding);
        if ($binding !== '') {
            return $binding;
        }

        return match ($component) {
            'filter' => 'module.rows',
            'table' => 'module.rows',
            'form' => 'module.fields',
            'kpi_card' => 'module.metrics.total',
            default => 'module.description',
        };
    }

    /** @param array<string,mixed> $layout @return array<int,string> */
    private static function validateVisualLayoutSchema(array $layout): array
    {
        $errors = [];
        $componentSchema = self::visualComponentSchema();
        $type = strtolower(trim((string)($layout['type'] ?? 'grid')));
        $columns = (int)($layout['columns'] ?? 12);
        $rows = trim((string)($layout['rows'] ?? 'auto'));
        $items = is_array($layout['items'] ?? null) ? $layout['items'] : [];
        $relations = is_array($layout['relations'] ?? null) ? $layout['relations'] : [];

        if ($type !== 'grid') {
            $errors[] = 'ops.gui_studio.error.layout_invalid_type';
        }
        if ($columns !== 12) {
            $errors[] = 'ops.gui_studio.error.layout_invalid_columns';
        }
        if ($rows === '') {
            $errors[] = 'ops.gui_studio.error.layout_invalid_rows';
        }
        if ($items === []) {
            $errors[] = 'ops.gui_studio.error.layout_missing_items';
            return array_values(array_unique($errors));
        }

        $seenIds = [];
        $gridOccupancy = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                $errors[] = 'ops.gui_studio.error.layout_invalid_item';
                continue;
            }

            $id = self::generatedKey((string)($item['id'] ?? ''));
            $component = self::generatedKey((string)($item['component'] ?? ''));
            $x = (int)($item['x'] ?? 0);
            $y = (int)($item['y'] ?? 0);
            $w = (int)($item['w'] ?? 0);
            $h = (int)($item['h'] ?? 0);
            $group = self::generatedKey((string)($item['group'] ?? ''));
            $props = is_array($item['props'] ?? null) ? $item['props'] : [];
            $dataBinding = trim((string)($item['data_binding'] ?? ''));

            if ($id === '') {
                $errors[] = 'ops.gui_studio.error.layout_item_id_required';
                continue;
            }
            if (isset($seenIds[$id])) {
                $errors[] = 'ops.gui_studio.error.layout_item_duplicate_id';
                continue;
            }
            $seenIds[$id] = true;

            if ($group === '') {
                $errors[] = 'ops.gui_studio.error.layout_invalid_group';
            }

            if (!in_array($component, ['filter', 'table', 'form', 'kpi_card', 'text_block'], true)) {
                $errors[] = 'ops.gui_studio.error.layout_invalid_component';
                continue;
            }

            $requiredProps = is_array($componentSchema[$component]['required_props'] ?? null)
                ? $componentSchema[$component]['required_props']
                : [];
            foreach ($requiredProps as $requiredProp) {
                if (!array_key_exists($requiredProp, $props)) {
                    $errors[] = 'ops.gui_studio.error.layout_missing_component_props';
                    break;
                }
            }
            if ($dataBinding === '') {
                $errors[] = 'ops.gui_studio.error.layout_missing_data_binding';
            } elseif (!preg_match('/^[a-z0-9_]+(\.[a-z0-9_]+)*$/i', $dataBinding)) {
                $errors[] = 'ops.gui_studio.error.layout_invalid_data_binding';
            } else {
                $bindingType = '';
                if ($dataBinding === 'module.rows') {
                    $bindingType = 'array_rows';
                } elseif ($dataBinding === 'module.fields') {
                    $bindingType = 'array_fields';
                } elseif ($dataBinding === 'module.description') {
                    $bindingType = 'string';
                } elseif (str_starts_with($dataBinding, 'module.metrics.')) {
                    $bindingType = 'number';
                }

                if ($bindingType === '') {
                    $errors[] = 'ops.gui_studio.error.layout_invalid_data_binding';
                } elseif ($component === 'filter' && $bindingType !== 'array_rows') {
                    $errors[] = 'ops.gui_studio.error.layout_binding_type_filter';
                } elseif ($component === 'table' && $bindingType !== 'array_rows') {
                    $errors[] = 'ops.gui_studio.error.layout_binding_type_table';
                } elseif ($component === 'form' && $bindingType !== 'array_fields') {
                    $errors[] = 'ops.gui_studio.error.layout_binding_type_form';
                } elseif ($component === 'kpi_card' && !in_array($bindingType, ['number', 'string'], true)) {
                    $errors[] = 'ops.gui_studio.error.layout_binding_type_kpi';
                } elseif ($component === 'text_block' && $bindingType !== 'string') {
                    $errors[] = 'ops.gui_studio.error.layout_binding_type_text';
                }
            }

            if ($x < 0 || $y < 0 || $w < 1 || $h < 1 || ($x + $w) > 12) {
                $errors[] = 'ops.gui_studio.error.layout_out_of_bounds';
                continue;
            }

            for ($row = $y; $row < $y + $h; $row++) {
                for ($col = $x; $col < $x + $w; $col++) {
                    $cell = $row . ':' . $col;
                    if (isset($gridOccupancy[$cell])) {
                        $errors[] = 'ops.gui_studio.error.layout_overlap';
                        break 2;
                    }
                    $gridOccupancy[$cell] = $id;
                }
            }
        }

        foreach ($relations as $relation) {
            if (!is_array($relation)) {
                $errors[] = 'ops.gui_studio.error.layout_invalid_relation';
                continue;
            }
            $sourceId = self::generatedKey((string)($relation['source_id'] ?? ''));
            $targetId = self::generatedKey((string)($relation['target_id'] ?? ''));
            $type = self::generatedKey((string)($relation['type'] ?? 'affects_table'));
            if ($sourceId === '' || $targetId === '' || !isset($seenIds[$sourceId]) || !isset($seenIds[$targetId])) {
                $errors[] = 'ops.gui_studio.error.layout_invalid_relation';
                continue;
            }
            if (!in_array($type, ['affects_table', 'filter_to_table', 'filter_to_kpi', 'form_refresh_table', 'table_to_kpi_derived'], true)) {
                $errors[] = 'ops.gui_studio.error.layout_invalid_relation';
            }
        }

        return array_values(array_unique($errors));
    }

    private static function safeDisplayText(string $value): string
    {
        $value = trim(strip_tags($value));
        $value = preg_replace('/\\s+/', ' ', $value) ?? '';
        return substr($value, 0, 120);
    }

    private static function safeGeneratedCellValue(string $value): string
    {
        $value = trim(strip_tags($value));
        $value = preg_replace('/\\s+/', ' ', $value) ?? '';
        return substr($value, 0, 240);
    }

    private static function normalizeGeneratedWorkflowStatus(string $status): string
    {
        $normalized = strtolower(trim($status));
        $workflow = self::generatedRuntimeWorkflow();
        $states = array_values(array_filter((array)($workflow['states'] ?? []), 'is_string'));
        return in_array($normalized, $states, true) ? $normalized : 'draft';
    }

    /** @param mixed $historyRaw @return array<int,array<string,mixed>> */
    private static function normalizeGeneratedWorkflowHistory(mixed $historyRaw): array
    {
        $history = [];
        if (!is_array($historyRaw)) {
            return $history;
        }

        foreach ($historyRaw as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $fromStatus = self::normalizeGeneratedWorkflowStatus((string)($entry['from_status'] ?? 'draft'));
            $toStatus = self::normalizeGeneratedWorkflowStatus((string)($entry['to_status'] ?? 'draft'));
            $role = self::generatedKey((string)($entry['role'] ?? 'read_only'));
            if ($role === '') {
                $role = 'read_only';
            }
            $timestamp = trim((string)($entry['timestamp'] ?? ''));
            if ($timestamp === '') {
                $timestamp = gmdate('c');
            }
            $history[] = [
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'role' => $role,
                'timestamp' => $timestamp,
                'actor' => self::safeGeneratedAuditActor((string)($entry['actor'] ?? 'system')),
                'duration_in_previous_state_seconds' => max(0, (int)($entry['duration_in_previous_state_seconds'] ?? 0)),
                'duration_in_previous_state_label' => self::formatGeneratedDurationLabel(max(0, (int)($entry['duration_in_previous_state_seconds'] ?? 0))),
            ];
        }

        return array_values($history);
    }

    private static function safeGeneratedAuditActor(string $value): string
    {
        $value = trim(strip_tags($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? '';
        $value = substr($value, 0, 120);
        return $value !== '' ? $value : 'system';
    }

    /** @param array<string,mixed> $module @return array<string,int> */
    private static function generatedRuntimeSlaConfig(array $module): array
    {
        $defaults = [
            'in_progress' => 4 * 3600,
            'completed' => 2 * 3600,
        ];

        $raw = [];
        if (is_array($module['max_time_per_state'] ?? null)) {
            $raw = $module['max_time_per_state'];
        }

        if ($raw === []) {
            $manifestPath = (string)($module['manifest_path'] ?? '');
            if ($manifestPath !== '' && !str_starts_with($manifestPath, '/') && !str_contains($manifestPath, '..') && !str_contains($manifestPath, '\\')) {
                $manifest = self::readJsonFileSafely(self::absolutePath($manifestPath));
                if (is_array($manifest['max_time_per_state'] ?? null)) {
                    $raw = $manifest['max_time_per_state'];
                } elseif (is_array($manifest['workflow']['max_time_per_state'] ?? null)) {
                    $raw = $manifest['workflow']['max_time_per_state'];
                } elseif (is_array($manifest['sla']['max_time_per_state'] ?? null)) {
                    $raw = $manifest['sla']['max_time_per_state'];
                } elseif (is_array($manifest['runtime']['max_time_per_state'] ?? null)) {
                    $raw = $manifest['runtime']['max_time_per_state'];
                }
            }
        }

        $states = ['draft', 'in_progress', 'completed', 'approved', 'dispatched'];
        $normalized = $defaults;
        foreach ($raw as $state => $value) {
            $safeState = self::normalizeGeneratedWorkflowStatus((string)$state);
            if ($safeState === '' || !in_array($safeState, $states, true)) {
                continue;
            }
            $seconds = self::parseGeneratedDurationSeconds($value);
            if ($seconds > 0) {
                $normalized[$safeState] = $seconds;
            }
        }

        return $normalized;
    }

    /** @param mixed $value */
    private static function parseGeneratedDurationSeconds(mixed $value): int
    {
        if (is_int($value) || is_float($value)) {
            $numeric = (float)$value;
            return $numeric > 0 ? (int)round($numeric * 3600) : 0;
        }

        $raw = strtolower(trim((string)$value));
        if ($raw === '') {
            return 0;
        }
        if (!preg_match('/^(\d+(?:\.\d+)?)\s*([hms])?$/', $raw, $matches)) {
            return 0;
        }
        $amount = (float)($matches[1] ?? 0.0);
        $unit = (string)($matches[2] ?? 'h');
        if ($amount <= 0) {
            return 0;
        }
        return match ($unit) {
            's' => (int)round($amount),
            'm' => (int)round($amount * 60),
            default => (int)round($amount * 3600),
        };
    }

    private static function parseGeneratedIsoTimestamp(string $value): int
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return 0;
        }
        $ts = strtotime($trimmed);
        return $ts === false ? 0 : max(0, (int)$ts);
    }

    private static function formatGeneratedDurationLabel(int $seconds): string
    {
        $safe = max(0, $seconds);
        $hours = intdiv($safe, 3600);
        $minutes = intdiv($safe % 3600, 60);
        if ($hours > 0 && $minutes > 0) {
            return $hours . 'h ' . $minutes . 'm';
        }
        if ($hours > 0) {
            return $hours . 'h';
        }
        if ($minutes > 0) {
            return $minutes . 'm';
        }
        return $safe . 's';
    }

    /** @param array<string,mixed> $row @param array<int,array<string,mixed>> $history */
    private static function inferGeneratedStateEnteredAt(array $row, string $currentStatus, array $history): int
    {
        $enteredAtTs = self::parseGeneratedIsoTimestamp((string)($row['created_at'] ?? ''));
        if ($enteredAtTs <= 0) {
            $enteredAtTs = time();
        }

        foreach ($history as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $toState = self::normalizeGeneratedWorkflowStatus((string)($entry['to_status'] ?? 'draft'));
            $entryTs = self::parseGeneratedIsoTimestamp((string)($entry['timestamp'] ?? ''));
            if ($entryTs <= 0) {
                continue;
            }
            if ($toState === $currentStatus) {
                $enteredAtTs = $entryTs;
            }
        }

        return $enteredAtTs;
    }

    /** @param array<string,mixed> $row @param array<string,int> $slaConfig @return array<string,mixed> */
    private static function enrichGeneratedRuntimeTimeIntelligence(array $row, array $slaConfig, int $nowTs): array
    {
        $status = self::normalizeGeneratedWorkflowStatus((string)($row['status'] ?? 'draft'));
        $history = self::normalizeGeneratedWorkflowHistory($row['history'] ?? []);

        $stateEnteredAtTs = self::parseGeneratedIsoTimestamp((string)($row['created_at'] ?? ''));
        if ($stateEnteredAtTs <= 0) {
            $stateEnteredAtTs = $nowTs;
        }

        $delayedTransitions = 0;
        foreach ($history as $index => $entry) {
            $entryTs = self::parseGeneratedIsoTimestamp((string)($entry['timestamp'] ?? ''));
            $durationSeconds = max(0, (int)($entry['duration_in_previous_state_seconds'] ?? 0));
            if ($durationSeconds <= 0 && $entryTs > 0 && $stateEnteredAtTs > 0) {
                $durationSeconds = max(0, $entryTs - $stateEnteredAtTs);
            }

            $fromState = self::normalizeGeneratedWorkflowStatus((string)($entry['from_status'] ?? 'draft'));
            $slaForFrom = max(0, (int)($slaConfig[$fromState] ?? 0));
            $isDelayed = $slaForFrom > 0 && $durationSeconds > $slaForFrom;
            if ($isDelayed) {
                $delayedTransitions++;
            }

            $history[$index]['duration_in_previous_state_seconds'] = $durationSeconds;
            $history[$index]['duration_in_previous_state_label'] = self::formatGeneratedDurationLabel($durationSeconds);
            $history[$index]['is_delayed'] = $isDelayed;
            $history[$index]['delay_by_seconds'] = $isDelayed ? ($durationSeconds - $slaForFrom) : 0;
            $history[$index]['delay_by_label'] = self::formatGeneratedDurationLabel(max(0, (int)($history[$index]['delay_by_seconds'] ?? 0)));
            $history[$index]['sla_max_seconds'] = $slaForFrom;

            if ($entryTs > 0) {
                $stateEnteredAtTs = $entryTs;
            }
        }

        $timeInStateSeconds = max(0, $nowTs - $stateEnteredAtTs);
        $slaMaxSeconds = max(0, (int)($slaConfig[$status] ?? 0));
        $isOverdue = $slaMaxSeconds > 0 && $timeInStateSeconds > $slaMaxSeconds;
        $overdueBySeconds = $isOverdue ? max(0, $timeInStateSeconds - $slaMaxSeconds) : 0;

        $row['status'] = $status;
        $row['history'] = $history;
        $row['time_in_state_seconds'] = $timeInStateSeconds;
        $row['time_in_state_label'] = self::formatGeneratedDurationLabel($timeInStateSeconds);
        $row['sla_max_seconds'] = $slaMaxSeconds;
        $row['sla_max_label'] = $slaMaxSeconds > 0 ? self::formatGeneratedDurationLabel($slaMaxSeconds) : '';
        $row['is_overdue'] = $isOverdue;
        $row['overdue_by_seconds'] = $overdueBySeconds;
        $row['overdue_by_label'] = self::formatGeneratedDurationLabel($overdueBySeconds);
        $row['delayed_transition_count'] = $delayedTransitions;
        $row['has_delayed_transition'] = $delayedTransitions > 0;

        return $row;
    }

    private static function generatedRecordId(string $value): string
    {
        $value = trim($value);
        return preg_match('/^[a-f0-9\\-]{16,64}$/i', $value) ? $value : '';
    }

    /** @param array<int,array<string,mixed>> $rows @return array<string,mixed> */
    private static function findGeneratedModuleRow(array $rows, string $rowId): array
    {
        foreach ($rows as $row) {
            if (is_array($row) && (string)($row['id'] ?? '') === $rowId) {
                return $row;
            }
        }
        return [];
    }

    private static function generatedModuleDataPath(string $appKey, string $moduleKey): string
    {
        $safeAppKey = self::generatedKey($appKey);
        $safeModuleKey = self::generatedKey($moduleKey);
        if ($safeAppKey === '' || $safeModuleKey === '') {
            return '';
        }
        return self::APPSTUDIO_STORAGE_ROOT . '/generated_data/' . $safeAppKey . '/' . $safeModuleKey . '.json';
    }

    /** @return array<int,array<string,mixed>> */
    private static function loadGeneratedModuleRows(string $appKey, string $moduleKey): array
    {
        $relativePath = self::generatedModuleDataPath($appKey, $moduleKey);
        if ($relativePath === '') {
            return [];
        }
        $path = self::storageAbsolutePath($relativePath);
        if (!is_file($path)) {
            return [];
        }
        $raw = @file_get_contents($path);
        $decoded = $raw !== false ? json_decode((string)$raw, true) : null;
        $rows = is_array($decoded['rows'] ?? null) ? $decoded['rows'] : (is_array($decoded) ? $decoded : []);
        return array_values(array_map(static function (array $row): array {
            $row['status'] = self::normalizeGeneratedWorkflowStatus((string)($row['status'] ?? 'draft'));
            $row['history'] = self::normalizeGeneratedWorkflowHistory($row['history'] ?? []);
            return $row;
        }, array_values(array_filter($rows, 'is_array'))));
    }

    /** @param array<int,array<string,mixed>> $rows */
    private static function persistGeneratedModuleRows(string $appKey, string $moduleKey, array $rows): bool
    {
        $relativePath = self::generatedModuleDataPath($appKey, $moduleKey);
        if ($relativePath === '') {
            return false;
        }
        $path = self::storageAbsolutePath($relativePath);
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
            return false;
        }
        $payload = [
            'schema_version' => 'studio.generated-data.v1',
            'app_key' => self::generatedKey($appKey),
            'module_key' => self::generatedKey($moduleKey),
            'updated_at' => gmdate('c'),
            'rows' => array_values($rows),
        ];
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || json_decode($json, true) === null) {
            return false;
        }
        $tmp = $dir . '/.' . self::generatedKey($moduleKey) . '.' . bin2hex(random_bytes(4)) . '.tmp';
        return file_put_contents($tmp, $json, LOCK_EX) !== false && rename($tmp, $path);
    }

    private static function generatedTempRelativePath(string $compileId): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_\\-]/', '_', $compileId) ?? '';
        $safe = trim($safe, '_-');
        if ($safe === '') {
            $safe = 'compile_unknown';
        }
        return self::GENERATED_TMP_ROOT . '/' . $safe;
    }

    private static function absolutePath(string $relativePath): string
    {
        return rtrim((string)(defined('APP_ROOT') ? APP_ROOT : ''), '/') . '/' . ltrim($relativePath, '/');
    }

    /** @return array{valid:bool,errors:array<int,string>} */
    private static function validateGeneratedTempTree(string $tmpRelativePath): array
    {
        $errors = [];
        if (!self::isSafeGeneratedTempDir($tmpRelativePath)) {
            return ['valid' => false, 'errors' => ['unsafe_temp_path']];
        }
        $base = self::absolutePath($tmpRelativePath);
        $required = [
            'manifest.json',
            'module.json',
            'routes.php',
            'navigation.php',
        ];
        foreach ($required as $file) {
            if (!is_file($base . '/' . $file)) {
                $errors[] = 'missing_temp_file:' . $file;
            }
        }
        foreach (['manifest.json', 'module.json'] as $file) {
            $raw = is_file($base . '/' . $file) ? @file_get_contents($base . '/' . $file) : false;
            if ($raw === false || !is_array(json_decode((string)$raw, true))) {
                $errors[] = 'invalid_json:' . $file;
            }
        }
        foreach (glob($base . '/{routes.php,navigation.php,Controllers/*.php,Providers/*.php,Views/*.php}', GLOB_BRACE) ?: [] as $file) {
            $content = @file_get_contents($file);
            if ($content === false || str_contains($content, 'eval(') || str_contains($content, 'shell_exec') || str_contains($content, 'proc_open')) {
                $errors[] = 'unsafe_php_template:' . basename($file);
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => array_values(array_unique($errors)),
        ];
    }

    private static function deleteGeneratedTempTree(string $tmpRelativePath): void
    {
        if (!self::isSafeGeneratedTempDir($tmpRelativePath)) {
            return;
        }
        $base = self::absolutePath($tmpRelativePath);
        if (!is_dir($base)) {
            return;
        }
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileInfo) {
            $path = $fileInfo->getPathname();
            if ($fileInfo->isDir()) {
                @rmdir($path);
            } elseif ($fileInfo->isFile()) {
                @unlink($path);
            }
        }
        @rmdir($base);
    }

    private static function isSafeGeneratedTempDir(string $path): bool
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\') || str_contains($path, '..')) {
            return false;
        }
        return (bool)preg_match('#^apps/Generated/tmp/[a-zA-Z0-9_\\-]+$#', $path);
    }

    private static function generatedRouteExists(string $routePath, string $liveRelativePath): bool
    {
        $sameManifestPath = rtrim($liveRelativePath, '/') . '/manifest.json';
        foreach (self::generatedModuleDefinitions(false) as $module) {
            if (!is_array($module)) {
                continue;
            }
            if ((string)($module['route_path'] ?? '') === $routePath) {
                if ((string)($module['manifest_path'] ?? '') === $sameManifestPath) {
                    continue;
                }
                return true;
            }
        }
        foreach (self::studioRegistryApps() as $app) {
            if (is_array($app) && (string)($app['route_path'] ?? '') === $routePath) {
                return true;
            }
        }
        return false;
    }

    /** @return array<string,mixed> */
    private static function loadGeneratedLiveManifest(string $liveRelativePath): array
    {
        if (!self::isSafeGeneratedLivePath($liveRelativePath)) {
            return [];
        }
        $manifestFile = self::absolutePath(rtrim($liveRelativePath, '/') . '/manifest.json');
        if (!is_file($manifestFile)) {
            return [];
        }
        $raw = @file_get_contents($manifestFile);
        $manifest = $raw !== false ? json_decode((string)$raw, true) : null;
        return is_array($manifest) ? $manifest : [];
    }

    private static function findGeneratedParentCompileId(array $context): ?string
    {
        $relativePath = (string)($context['relative_path'] ?? '');
        if (!self::isSafeGeneratedLivePath($relativePath)) {
            return null;
        }
        $manifestFile = self::absolutePath($relativePath . '/manifest.json');
        if (!is_file($manifestFile)) {
            return null;
        }
        $raw = @file_get_contents($manifestFile);
        $manifest = $raw !== false ? json_decode((string)$raw, true) : null;
        if (!is_array($manifest)) {
            return null;
        }
        $compileId = trim((string)($manifest['compile_id'] ?? ''));
        return $compileId !== '' ? $compileId : null;
    }

    private static function registerGeneratedAppVersion(array $context, string $compileId, array $snapshot = []): bool
    {
        $appKey = self::generatedKey((string)($context['app_key'] ?? ''));
        $moduleKey = self::generatedKey((string)($context['module_key'] ?? ''));
        if ($appKey === '' || $moduleKey === '' || $compileId === '') {
            return false;
        }
        $registry = self::loadGeneratedAppRegistry();
        $now = gmdate('c');
        $entry = is_array($registry[$appKey] ?? null) ? $registry[$appKey] : [
            'current_version' => '',
            'status' => 'installed',
            'modules' => [],
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $modules = is_array($entry['modules'] ?? null) ? $entry['modules'] : [];
        $modules[] = $moduleKey;
        $entry['current_version'] = $compileId;
        $entry['status'] = 'enabled';
        $entry['modules'] = array_values(array_unique(array_map('strval', $modules)));
        $entry['publish_approved'] = !empty($snapshot['publish_approved']);
        $entry['publish_gate_status'] = (string)($snapshot['publish_gate_status'] ?? '');
        $entry['publish_decision_id'] = (string)($snapshot['publish_decision_id'] ?? '');
        $entry['approved_by'] = (string)($snapshot['approved_by'] ?? '');
        $entry['approval_timestamp'] = (string)($snapshot['approval_timestamp'] ?? '');
        $entry['module_origin'] = (string)($snapshot['generated_by'] ?? self::FIRST_APPLY_GENERATOR_ID);
        $entry['created_at'] = (string)($entry['created_at'] ?? $now);
        $entry['updated_at'] = $now;
        $registry[$appKey] = $entry;
        return self::persistGeneratedAppRegistry($registry);
    }

    private static function generatedAppsRegistryPath(): string
    {
        return self::storageAbsolutePath(self::APPSTUDIO_STORAGE_ROOT . '/apps_registry.json');
    }

    /** @param array<string,array<string,mixed>> $registry */
    private static function persistGeneratedAppRegistry(array $registry): bool
    {
        $normalized = [];
        foreach ($registry as $appKey => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $safeAppKey = self::generatedKey((string)$appKey);
            if ($safeAppKey === '' || $safeAppKey === 'tmp') {
                continue;
            }
            $status = strtolower((string)($entry['status'] ?? 'disabled'));
            if (!in_array($status, self::GENERATED_APP_STATUSES, true)) {
                $status = 'disabled';
            }
            $modules = [];
            foreach ((array)($entry['modules'] ?? []) as $moduleKey) {
                $safeModuleKey = self::generatedKey((string)$moduleKey);
                if ($safeModuleKey !== '') {
                    $modules[] = $safeModuleKey;
                }
            }
            $normalized[$safeAppKey] = [
                'current_version' => (string)($entry['current_version'] ?? ''),
                'status' => $status,
                'modules' => array_values(array_unique($modules)),
                'publish_approved' => !empty($entry['publish_approved']),
                'publish_gate_status' => (string)($entry['publish_gate_status'] ?? ''),
                'publish_decision_id' => (string)($entry['publish_decision_id'] ?? ''),
                'approved_by' => (string)($entry['approved_by'] ?? ''),
                'approval_timestamp' => (string)($entry['approval_timestamp'] ?? ''),
                'module_origin' => (string)($entry['module_origin'] ?? ''),
                'created_at' => (string)($entry['created_at'] ?? gmdate('c')),
                'updated_at' => (string)($entry['updated_at'] ?? gmdate('c')),
            ];
        }

        $path = self::generatedAppsRegistryPath();
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
            return false;
        }
        $json = json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || json_decode($json, true) === null) {
            return false;
        }
        $tmp = $dir . '/.apps_registry.' . bin2hex(random_bytes(4)) . '.tmp';
        return file_put_contents($tmp, $json, LOCK_EX) !== false && rename($tmp, $path);
    }

    private static function generatedAppFilesPresent(string $appKey, array $entry): bool
    {
        $modules = is_array($entry['modules'] ?? null) ? $entry['modules'] : [];
        if ($modules === []) {
            return false;
        }
        foreach ($modules as $moduleKey) {
            $relativePath = self::GENERATED_ROOT . '/' . self::generatedKey($appKey) . '/' . self::generatedKey((string)$moduleKey);
            if (!self::isSafeGeneratedLivePath($relativePath)) {
                return false;
            }
            $base = self::absolutePath($relativePath);
            foreach (['manifest.json', 'module.json', 'routes.php', 'navigation.php'] as $file) {
                if (!is_file($base . '/' . $file)) {
                    return false;
                }
            }
            if (glob($base . '/Controllers/*.php') === [] || glob($base . '/Providers/*Provider.php') === [] || glob($base . '/Views/*.php') === []) {
                return false;
            }
        }
        return true;
    }

    private static function generatedRollbackLockPath(): string
    {
        return self::storageAbsolutePath(self::APPSTUDIO_STORAGE_ROOT . '/.generated_rollback.lock');
    }

    private static function generatedRollbackInProgress(): bool
    {
        $path = self::generatedRollbackLockPath();
        if (!is_file($path)) {
            return false;
        }
        $mtime = @filemtime($path);
        if ($mtime !== false && (time() - $mtime) > 300) {
            @unlink($path);
            return false;
        }
        return true;
    }

    private static function acquireGeneratedRollbackLock(): bool
    {
        if (self::generatedRollbackInProgress()) {
            return false;
        }
        $path = self::generatedRollbackLockPath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $handle = @fopen($path, 'x');
        if ($handle === false) {
            return false;
        }
        fwrite($handle, (string)getmypid());
        fclose($handle);
        return true;
    }

    private static function releaseGeneratedRollbackLock(): void
    {
        $path = self::generatedRollbackLockPath();
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private static function bindGeneratedSnapshotContext(array $snapshot, array $context): array
    {
        $snapshot['generated_by'] = self::FIRST_APPLY_GENERATOR_ID;
        $snapshot['parent_compile_id'] = self::findGeneratedParentCompileId($context);
        $snapshot['generated_context'] = $context;
        $snapshot['module_paths'] = [
            'live_path' => (string)($context['relative_path'] ?? ''),
            'route_path' => (string)($context['route_path'] ?? ''),
            'manifest_path' => (string)($context['relative_path'] ?? '') . '/manifest.json',
            'module_path' => (string)($context['relative_path'] ?? '') . '/module.json',
            'routes_path' => (string)($context['relative_path'] ?? '') . '/routes.php',
            'navigation_path' => (string)($context['relative_path'] ?? '') . '/navigation.php',
            'view_path' => (string)($context['relative_path'] ?? '') . '/Views/' . (string)($context['view_file'] ?? 'index.php'),
            'controller_path' => (string)($context['relative_path'] ?? '') . '/Controllers/' . (string)($context['controller_class'] ?? '') . '.php',
        ];
        return $snapshot;
    }

    private static function snapshotPathForCompileId(string $compileId): string
    {
        $safeCompileId = self::safeStorageId($compileId);
        if ($safeCompileId === '') {
            $safeCompileId = 'compile_unknown';
        }
        return self::APPSTUDIO_STORAGE_ROOT . '/snapshots/' . $safeCompileId . '.json';
    }

    private static function safeStorageId(string $value): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_\\-]/', '_', $value) ?? '';
        return trim($safe, '_-');
    }

    private static function storageAbsolutePath(string $relativePath): string
    {
        return rtrim((string)(defined('APP_ROOT') ? APP_ROOT : ''), '/') . '/' . ltrim($relativePath, '/');
    }

    private static function appendStructuredApplyLog(string $event, array $payload, array $context): void
    {
        $storageDir = self::storageAbsolutePath(self::APPSTUDIO_STORAGE_ROOT);
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0775, true);
        }
        $entry = [
            'schema_version' => self::APPSTUDIO_APPLY_LOG_SCHEMA,
            'event' => $event,
            'compile_id' => (string)($payload['compile_id'] ?? ''),
            'snapshot_id' => (string)($payload['snapshot_id'] ?? ''),
            'apply_id' => (string)($payload['apply_id'] ?? ''),
            'rollback_id' => (string)($payload['rollback_id'] ?? ''),
            'publish_approved' => !empty($payload['publish_approved']),
            'publish_gate_status' => (string)($payload['publish_gate_status'] ?? ''),
            'publish_decision_id' => (string)($payload['publish_decision_id'] ?? ''),
            'approved_by' => (string)($payload['approved_by'] ?? ''),
            'approval_timestamp' => (string)($payload['approval_timestamp'] ?? ''),
            'verification_status' => (string)($payload['post_publish_verification']['status'] ?? ''),
            'app_key' => (string)($context['app_key'] ?? ''),
            'module_key' => (string)($context['module_key'] ?? ''),
            'timestamp' => (string)($payload['completed_at'] ?? gmdate('c')),
            'status' => (string)($payload['status'] ?? 'UNKNOWN'),
        ];
        $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($line === false) {
            return;
        }
        file_put_contents($storageDir . '/apply_log.ndjson', $line . "\n", FILE_APPEND | LOCK_EX);
    }

    private static function appendApplyLog(array $apply, array $context): void
    {
        self::appendStructuredApplyLog('apply_' . strtolower((string)($apply['status'] ?? 'unknown')), $apply, $context);
    }

    private static function persistGeneratedSnapshot(array $snapshot, array $context): bool
    {
        $compileId = (string)($snapshot['compile_id'] ?? '');
        if ($compileId === '' || !self::isSafeGeneratedLivePath((string)($context['relative_path'] ?? ''))) {
            return false;
        }

        $relativePath = self::snapshotPathForCompileId($compileId);
        $targetFile = self::storageAbsolutePath($relativePath);
        $dir = dirname($targetFile);
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
            return false;
        }

        $record = [
            'schema_version' => self::APPSTUDIO_SNAPSHOT_SCHEMA,
            'compile_id' => $compileId,
            'parent_compile_id' => $snapshot['parent_compile_id'] ?? null,
            'snapshot_id' => (string)($snapshot['snapshot_id'] ?? ''),
            'approval_id' => (string)($snapshot['approval_id'] ?? ''),
            'snapshot_hash' => (string)($snapshot['snapshot_hash'] ?? ''),
            'generated_by' => self::FIRST_APPLY_GENERATOR_ID,
            'timestamp' => gmdate('c'),
            'created_at' => (string)($snapshot['created_at'] ?? gmdate('c')),
            'approval_valid' => !empty($snapshot['approval_valid']),
            'publish_approved' => !empty($snapshot['publish_approved']),
            'publish_gate_status' => (string)($snapshot['publish_gate_status'] ?? ''),
            'publish_decision_id' => (string)($snapshot['publish_decision_id'] ?? ''),
            'approved_by' => (string)($snapshot['approved_by'] ?? ''),
            'approval_timestamp' => (string)($snapshot['approval_timestamp'] ?? ''),
            'approval_summary' => is_array($snapshot['approval_summary'] ?? null) ? $snapshot['approval_summary'] : [],
            'risk_summary' => is_array($snapshot['risk_summary'] ?? null) ? $snapshot['risk_summary'] : [],
            'integrity' => is_array($snapshot['integrity'] ?? null) ? $snapshot['integrity'] : [],
            'artifacts' => is_array($snapshot['artifacts'] ?? null) ? array_values(array_filter($snapshot['artifacts'], 'is_array')) : [],
            'module_paths' => is_array($snapshot['module_paths'] ?? null) ? $snapshot['module_paths'] : [],
            'generated_context' => $context,
            'module_files' => self::generatedModuleFiles($context, $snapshot),
        ];

        $json = json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }
        $tmpFile = $dir . '/.' . self::safeStorageId($compileId) . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $persisted = file_put_contents($tmpFile, $json, LOCK_EX) !== false && rename($tmpFile, $targetFile);
        if ($persisted) {
            self::recordHistoryEvent([
                'event' => 'generated_snapshot_persisted',
                'snapshot_id' => (string)($snapshot['snapshot_id'] ?? ''),
                'compile_id' => $compileId,
                'parent_compile_id' => $snapshot['parent_compile_id'] ?? null,
                'snapshot_hash' => (string)($snapshot['snapshot_hash'] ?? ''),
                'occurred_at' => gmdate('c'),
            ]);
        }
        return $persisted;
    }

    /** @return array<string,mixed> */
    public static function loadGeneratedSnapshotByCompileId(string $compileId): array
    {
        $targetFile = self::storageAbsolutePath(self::snapshotPathForCompileId($compileId));
        if (!is_file($targetFile)) {
            return [];
        }
        $raw = @file_get_contents($targetFile);
        $record = $raw !== false ? json_decode((string)$raw, true) : null;
        return is_array($record) ? $record : [];
    }

    /** @return array<string,mixed> */
    private static function findGeneratedSnapshotByModule(string $appKey, string $moduleKey): array
    {
        $snapshotDir = dirname(self::storageAbsolutePath(self::snapshotPathForCompileId('sample')));
        if (!is_dir($snapshotDir)) {
            return [];
        }

        $matched = [];
        foreach (glob($snapshotDir . '/*.json') ?: [] as $snapshotPath) {
            if (!is_file($snapshotPath)) {
                continue;
            }
            $record = self::readJsonFileSafely($snapshotPath);
            if (!is_array($record)) {
                continue;
            }
            $context = is_array($record['generated_context'] ?? null) ? $record['generated_context'] : [];
            $contextAppKey = self::generatedKey((string)($context['app_key'] ?? ''));
            $contextModuleKey = self::generatedKey((string)($context['module_key'] ?? ''));
            if ($contextAppKey !== $appKey || $contextModuleKey !== $moduleKey) {
                continue;
            }
            $timestamp = trim((string)($record['timestamp'] ?? $record['created_at'] ?? ''));
            $matched[] = ['timestamp' => $timestamp, 'record' => $record];
        }

        if ($matched === []) {
            return [];
        }

        usort($matched, static function (array $a, array $b): int {
            return strcmp((string)($b['timestamp'] ?? ''), (string)($a['timestamp'] ?? ''));
        });
        return is_array($matched[0]['record'] ?? null) ? $matched[0]['record'] : [];
    }

    /** @return array<string,mixed>|null */
    private static function readJsonFileSafely(string $filePath): ?array
    {
        if (!is_file($filePath)) {
            return null;
        }
        $raw = @file_get_contents($filePath);
        if ($raw === false || trim($raw) === '') {
            return null;
        }
        $decoded = json_decode((string)$raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    private static function extractGeneratedRoutePathFromFile(string $filePath): string
    {
        if (!is_file($filePath)) {
            return '';
        }
        $raw = @file_get_contents($filePath);
        if ($raw === false || $raw === '') {
            return '';
        }
        if (preg_match("/'route_path'\\s*=>\\s*'([^']+)'/", $raw, $matches) === 1) {
            return trim((string)($matches[1] ?? ''));
        }
        if (preg_match('/"route_path"\\s*=>\\s*"([^"]+)"/', $raw, $matches) === 1) {
            return trim((string)($matches[1] ?? ''));
        }
        return '';
    }

    /** @return array<string,mixed> */
    private static function extractGeneratedNavigationContractFromFile(string $filePath): array
    {
        if (!is_file($filePath)) {
            return [];
        }
        $raw = @file_get_contents($filePath);
        if ($raw === false || $raw === '') {
            return [];
        }

        $extractString = static function (string $pattern) use ($raw): string {
            return preg_match($pattern, $raw, $matches) === 1 ? trim((string)($matches[1] ?? '')) : '';
        };
        $extractInt = static function (string $pattern) use ($raw): int {
            return preg_match($pattern, $raw, $matches) === 1 ? (int)($matches[1] ?? 0) : 0;
        };

        return [
            'key' => $extractString("/'key'\\s*=>\\s*'([^']+)'/"),
            'label' => $extractString("/'label'\\s*=>\\s*'([^']+)'/"),
            'url' => $extractString("/'url'\\s*=>\\s*'([^']+)'/"),
            'section' => $extractString("/'section'\\s*=>\\s*'([^']+)'/"),
            'visible_if' => $extractString("/'visible_if'\\s*=>\\s*'([^']+)'/"),
            'order' => $extractInt("/'order'\\s*=>\\s*([0-9]+)/"),
            'priority' => $extractInt("/'priority'\\s*=>\\s*([0-9]+)/"),
        ];
    }

    private static function generatedAppRoutePrefix(string $appKey): string
    {
        $safeAppKey = self::generatedKey($appKey);
        if ($safeAppKey === '') {
            return '';
        }
        return '/apps/' . str_replace('_', '-', $safeAppKey);
    }

    private static function inferStudioModuleTypeFromTemplate(string $template): string
    {
        $normalized = strtolower(trim($template));
        return match ($normalized) {
            'dashboard_module' => 'dashboard',
            'queue_workflow_module' => 'queue_workflow',
            default => 'crud',
        };
    }

    private static function inferStudioViewKindFromTemplate(string $template): string
    {
        $normalized = strtolower(trim($template));
        return match ($normalized) {
            'form_view' => 'form',
            'detail_view' => 'detail',
            default => 'table',
        };
    }

    /** @return array<string,string> */
    private static function snapshotModuleFilesForRestore(array $snapshotRecord, string $restoreTmpRelativePath, string $liveRelativePath): array
    {
        $storedFiles = is_array($snapshotRecord['module_files'] ?? null) ? $snapshotRecord['module_files'] : [];
        $files = [];
        foreach ($storedFiles as $relativePath => $content) {
            $sourcePath = (string)$relativePath;
            if (!is_string($content) || !str_starts_with($sourcePath, rtrim($liveRelativePath, '/') . '/')) {
                continue;
            }
            $suffix = substr($sourcePath, strlen(rtrim($liveRelativePath, '/') . '/'));
            $files[rtrim($restoreTmpRelativePath, '/') . '/' . $suffix] = $content;
        }
        return $files;
    }

    private static function deleteGeneratedLiveTree(string $liveRelativePath): void
    {
        if (!self::isSafeGeneratedLivePath($liveRelativePath)) {
            return;
        }
        $base = self::absolutePath($liveRelativePath);
        if (!is_dir($base)) {
            return;
        }
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileInfo) {
            $path = $fileInfo->getPathname();
            if ($fileInfo->isDir()) {
                @rmdir($path);
            } elseif ($fileInfo->isFile()) {
                @unlink($path);
            }
        }
        @rmdir($base);
    }

    /** @param array<int,array<string,mixed>> $steps @return array{total:int,done:int,failed:int,skipped:int} */
    private static function summarizeGeneratedRollbackSteps(array $steps): array
    {
        $summary = ['total' => 0, 'done' => 0, 'failed' => 0, 'skipped' => 0];
        foreach ($steps as $step) {
            if (!is_array($step)) {
                continue;
            }
            $summary['total']++;
            $result = strtolower((string)($step['result'] ?? 'failed'));
            if ($result === 'done') {
                $summary['done']++;
            } elseif ($result === 'skipped') {
                $summary['skipped']++;
            } else {
                $summary['failed']++;
            }
        }
        return $summary;
    }

    // -------------------------------------------------------------------------
    // Phase 9 — Controlled Apply
    // -------------------------------------------------------------------------

    /** @return array<string,mixed> */
    public static function applySnapshot(array $snapshot): array
    {
        if (self::APPLY_MODE === 'disabled') {
            return [
                'status' => 'DISABLED',
                'message' => 'Apply is disabled. Set APPLY_MODE to simulation or real to enable.',
                'can_apply' => false,
                'results' => [],
                'summary' => ['total' => 0, 'applied' => 0, 'failed' => 0],
            ];
        }

        if (self::APPLY_MODE === 'simulation') {
            $publishFailures = self::publishGatePreconditionFailures($snapshot);
            if ($publishFailures !== []) {
                return [
                    'status' => 'BLOCKED',
                    'message' => 'Publish gate not passed. Apply requires publish approval.',
                    'can_apply' => false,
                    'results' => [],
                    'summary' => ['total' => 0, 'applied' => 0, 'failed' => 0],
                    'precondition_failures' => $publishFailures,
                ];
            }
            return self::simulateExecution($snapshot);
        }

        // self::APPLY_MODE === 'real'
        return self::applyWithTransaction($snapshot);
    }

    /** @return array<string,mixed> */
    public static function applyWithTransaction(array $snapshot): array
    {
        $applyId = self::previewUuid('apply:' . (string)($snapshot['snapshot_id'] ?? '') . ':' . gmdate('c'));
        $startedAt = gmdate('c');

        // --- Precondition checks ---
        $approvalSummary = is_array($snapshot['approval_summary'] ?? null) ? $snapshot['approval_summary'] : [];
        $approvalValid = !empty($snapshot['approval_valid']);
        $decision = strtolower((string)($approvalSummary['decision'] ?? ''));
        $blockedItems = (int)($approvalSummary['blocked_count'] ?? 0);
        $snapshotHash = (string)($snapshot['snapshot_hash'] ?? '');
        $recomputedHash = self::computeSnapshotHash($snapshot);
        $hashVerified = $snapshotHash !== '' && hash_equals($snapshotHash, $recomputedHash);
        $publishFailures = self::publishGatePreconditionFailures($snapshot);

        $preconditionFailed = !$approvalValid || $decision !== 'approved' || $blockedItems > 0 || !$hashVerified || $publishFailures !== [];

        if ($preconditionFailed) {
            $reasons = [];
            if (!$approvalValid) {
                $reasons[] = 'approval_invalid';
            }
            if ($decision !== 'approved') {
                $reasons[] = 'decision_not_approved';
            }
            if ($blockedItems > 0) {
                $reasons[] = 'blocked_items_present';
            }
            if (!$hashVerified) {
                $reasons[] = 'snapshot_hash_mismatch';
            }
            foreach ($publishFailures as $failure) {
                $reasons[] = $failure;
            }

            return [
                'apply_id' => $applyId,
                'snapshot_id' => (string)($snapshot['snapshot_id'] ?? ''),
                'compile_id' => (string)($snapshot['compile_id'] ?? ''),
                'approval_id' => (string)($snapshot['approval_id'] ?? ''),
                'status' => 'FAILED',
                'started_at' => $startedAt,
                'completed_at' => gmdate('c'),
                'steps' => [],
                'results' => [],
                'integrity' => [
                    'snapshot_hash' => $snapshotHash,
                    'verified' => $hashVerified,
                ],
                'precondition_failures' => $reasons,
                'rollback_binding' => [],
            ];
        }

        // --- Phase 13: Idempotency guard ---
        $snapshotIdForHardening = (string)($snapshot['snapshot_id'] ?? '');
        $alreadyApplied = self::isSnapshotAlreadyApplied($snapshotIdForHardening);
        if ($alreadyApplied['applied']) {
            return [
                'apply_id' => $applyId,
                'snapshot_id' => $snapshotIdForHardening,
                'compile_id' => (string)($snapshot['compile_id'] ?? ''),
                'approval_id' => (string)($snapshot['approval_id'] ?? ''),
                'status' => 'FAILED',
                'started_at' => $startedAt,
                'completed_at' => gmdate('c'),
                'steps' => [],
                'results' => [],
                'integrity' => ['snapshot_hash' => $snapshotHash, 'verified' => $hashVerified],
                'precondition_failures' => ['snapshot_already_applied'],
                'duplicate_of_apply_id' => $alreadyApplied['apply_id'],
                'rollback_binding' => [],
            ];
        }

        // --- Phase 13: Concurrency lock ---
        if (!self::acquireApplyLock($snapshotIdForHardening)) {
            return [
                'apply_id' => $applyId,
                'snapshot_id' => $snapshotIdForHardening,
                'compile_id' => (string)($snapshot['compile_id'] ?? ''),
                'approval_id' => (string)($snapshot['approval_id'] ?? ''),
                'status' => 'FAILED',
                'started_at' => $startedAt,
                'completed_at' => gmdate('c'),
                'steps' => [],
                'results' => [],
                'integrity' => ['snapshot_hash' => $snapshotHash, 'verified' => $hashVerified],
                'precondition_failures' => ['concurrent_apply_in_progress'],
                'rollback_binding' => [],
            ];
        }

        // --- Build rollback plan upfront (no side effects) ---
        $rollbackPlan = self::buildRollbackPlan($snapshot);

        // --- Persist snapshot record (history) ---
        self::persistSnapshot($snapshot);
        $artifacts = is_array($snapshot['artifacts'] ?? null) ? $snapshot['artifacts'] : [];
        $steps = [];
        $overallFailed = false;

        try {
        foreach ($artifacts as $artifact) {
            if (!is_array($artifact)) {
                continue;
            }
            $step = self::executeArtifactSafely($artifact);
            $steps[] = $step;
            if ($step['result'] === 'failed') {
                $overallFailed = true;
                break; // stop immediately on failure
            }
        }

        $status = $overallFailed ? 'FAILED' : 'APPLIED';

        $apply = [
            'apply_id' => $applyId,
            'snapshot_id' => (string)($snapshot['snapshot_id'] ?? ''),
            'compile_id' => (string)($snapshot['compile_id'] ?? ''),
            'approval_id' => (string)($snapshot['approval_id'] ?? ''),
            'status' => $status,
            'started_at' => $startedAt,
            'completed_at' => gmdate('c'),
            'steps' => $steps,
            'results' => array_map(static fn(array $s): array => [
                'artifact' => (string)($s['artifact'] ?? ''),
                'action' => (string)($s['action'] ?? ''),
                'status' => $s['result'] === 'applied' ? 'applied' : 'failed',
                'message' => (string)($s['message'] ?? ''),
            ], $steps),
            'integrity' => [
                'snapshot_hash' => $snapshotHash,
                'verified' => $hashVerified,
            ],
        ];

        $apply = self::bindRollbackToApply($apply, $rollbackPlan);

        if ($status === 'APPLIED') {
            $apply['post_publish_verification'] = self::verifyPostPublishState($snapshot, $apply);
        }

        self::persistApplyRecord($apply);

        self::recordHistoryEvent([
            'event' => 'apply_' . strtolower($status),
            'apply_id' => $applyId,
            'snapshot_id' => (string)($snapshot['snapshot_id'] ?? ''),
            'status' => $status,
            'occurred_at' => gmdate('c'),
        ]);

        } finally {
            self::releaseApplyLock($snapshotIdForHardening);
        }

        return $apply;
    }

    /** @return array{artifact:string,action:string,status:string,message:string} */
    private static function executeArtifact(array $artifact): array
    {
        $artifactId = (string)($artifact['artifact_id'] ?? '');
        $targetPath = (string)($artifact['target_path'] ?? '');
        $riskLevel = strtoupper((string)($artifact['risk_level'] ?? ''));
        $ownershipScope = strtolower((string)($artifact['ownership_scope'] ?? 'unknown'));
        $changeType = strtolower((string)($artifact['change_type'] ?? 'create'));

        // Block core
        if (
            str_starts_with($targetPath, 'app/')
            || str_starts_with($targetPath, '/app/')
            || strtolower((string)($artifact['owning_app'] ?? '')) === 'core'
        ) {
            return [
                'artifact' => $artifactId,
                'action' => 'blocked',
                'status' => 'failed',
                'message' => 'core_artifact_blocked',
            ];
        }

        // Block BLOCKED risk level
        if ($riskLevel === 'BLOCKED') {
            return [
                'artifact' => $artifactId,
                'action' => 'blocked',
                'status' => 'failed',
                'message' => 'blocked_risk_level',
            ];
        }

        // Block unknown ownership
        if ($ownershipScope === 'unknown' || $ownershipScope === 'external') {
            return [
                'artifact' => $artifactId,
                'action' => 'blocked',
                'status' => 'failed',
                'message' => 'unknown_or_external_ownership_blocked',
            ];
        }

        // Block unmanaged artifacts
        if ($ownershipScope === 'unmanaged') {
            return [
                'artifact' => $artifactId,
                'action' => 'blocked',
                'status' => 'failed',
                'message' => 'unmanaged_artifact_blocked',
            ];
        }

        // Enforce allowed zones: /apps/*, /plugins/*, /storage/appstudio/*
        $allowedPrefix = (
            str_starts_with($targetPath, 'apps/')
            || str_starts_with($targetPath, 'plugins/')
            || str_starts_with($targetPath, 'storage/appstudio/')
        );
        if (!$allowedPrefix) {
            return [
                'artifact' => $artifactId,
                'action' => 'blocked',
                'status' => 'failed',
                'message' => 'path_outside_allowed_zones',
            ];
        }

        $action = match ($changeType) {
            'create' => 'create',
            'update', 'conflict' => 'update',
            default => 'skip',
        };

        return [
            'artifact' => $artifactId,
            'action' => $action,
            'status' => 'applied',
            'message' => 'artifact_' . $action . '_recorded',
        ];
    }

    private static function isSafePath(string $path): bool
    {
        return (
            str_starts_with($path, 'apps/')
            || str_starts_with($path, '/apps/')
            || str_starts_with($path, 'plugins/')
            || str_starts_with($path, '/plugins/')
            || str_starts_with($path, 'storage/appstudio/')
            || str_starts_with($path, '/storage/appstudio/')
        );
    }

    /** @return array{artifact:string,action:string,result:string,message:string,rollback:array<string,mixed>} */
    private static function executeArtifactSafely(array $artifact): array
    {
        $artifactId = (string)($artifact['artifact_id'] ?? '');
        $targetPath = (string)($artifact['target_path'] ?? '');
        $riskLevel = strtoupper((string)($artifact['risk_level'] ?? ''));
        $ownershipScope = strtolower((string)($artifact['ownership_scope'] ?? 'unknown'));
        $changeType = strtolower((string)($artifact['change_type'] ?? 'create'));

        // Block Core (/app/*)
        if (
            str_starts_with($targetPath, 'app/')
            || str_starts_with($targetPath, '/app/')
            || strtolower((string)($artifact['owning_app'] ?? '')) === 'core'
        ) {
            return [
                'artifact' => $artifactId,
                'action' => 'blocked',
                'result' => 'failed',
                'message' => 'core_artifact_blocked',
                'rollback' => [],
            ];
        }

        // Block BLOCKED risk level
        if ($riskLevel === 'BLOCKED') {
            return [
                'artifact' => $artifactId,
                'action' => 'blocked',
                'result' => 'failed',
                'message' => 'blocked_risk_level',
                'rollback' => [],
            ];
        }

        // Block unsafe ownership
        if (in_array($ownershipScope, ['unknown', 'external', 'unmanaged'], true)) {
            return [
                'artifact' => $artifactId,
                'action' => 'blocked',
                'result' => 'failed',
                'message' => 'unsafe_ownership_blocked',
                'rollback' => [],
            ];
        }

        // Enforce safe path zones
        if (!self::isSafePath($targetPath)) {
            return [
                'artifact' => $artifactId,
                'action' => 'blocked',
                'result' => 'failed',
                'message' => 'path_outside_safe_zones',
                'rollback' => [],
            ];
        }

        $action = match ($changeType) {
            'create' => 'create',
            'update', 'conflict' => 'update',
            default => 'skip',
        };

        $rb = self::determineRollbackAction(array_merge($artifact, ['planned_action' => $action]));

        return [
            'artifact' => $artifactId,
            'action' => $action,
            'result' => 'applied',
            'message' => 'artifact_' . $action . '_applied',
            'rollback' => [
                'rollback_action' => $rb['rollback_action'],
                'reversible' => $rb['reversible'],
            ],
        ];
    }

    /** @return array<string,mixed> */
    private static function bindRollbackToApply(array $apply, array $rollbackPlan): array
    {
        $apply['rollback_binding'] = [
            'rollback_id' => (string)($rollbackPlan['rollback_id'] ?? ''),
            'snapshot_id' => (string)($rollbackPlan['snapshot_id'] ?? ''),
            'bound_at' => gmdate('c'),
            'status' => 'bound_not_executed',
            'artifacts' => is_array($rollbackPlan['artifacts'] ?? null) ? $rollbackPlan['artifacts'] : [],
            'summary' => is_array($rollbackPlan['summary'] ?? null) ? $rollbackPlan['summary'] : [],
        ];
        return $apply;
    }

    private static function persistApplyRecord(array $apply): void
    {
        $applyId = preg_replace('/[^a-zA-Z0-9\-]/', '_', (string)($apply['apply_id'] ?? 'unknown'));
        $storageDir = rtrim((string)(defined('APP_ROOT') ? APP_ROOT : ''), '/') . '/storage/appstudio/applies';

        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0775, true);
        }

        $filename = 'apply_' . $applyId . '.json';
        $targetFile = $storageDir . '/' . $filename;
        $tmpFile = $storageDir . '/.' . $applyId . '.tmp.' . bin2hex(random_bytes(4));

        $payload = [
            'apply_id' => (string)($apply['apply_id'] ?? ''),
            'snapshot_id' => (string)($apply['snapshot_id'] ?? ''),
            'compile_id' => (string)($apply['compile_id'] ?? ''),
            'approval_id' => (string)($apply['approval_id'] ?? ''),
            'status' => (string)($apply['status'] ?? 'UNKNOWN'),
            'started_at' => (string)($apply['started_at'] ?? ''),
            'completed_at' => (string)($apply['completed_at'] ?? ''),
            'integrity' => is_array($apply['integrity'] ?? null) ? $apply['integrity'] : [],
            'results' => is_array($apply['results'] ?? null) ? $apply['results'] : [],
            'steps' => is_array($apply['steps'] ?? null) ? $apply['steps'] : [],
            'rollback_binding' => is_array($apply['rollback_binding'] ?? null) ? $apply['rollback_binding'] : [],
            'post_publish_verification' => is_array($apply['post_publish_verification'] ?? null) ? $apply['post_publish_verification'] : [],
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return;
        }

        // Atomic write: temp file → rename
        if (file_put_contents($tmpFile, $json, LOCK_EX) !== false) {
            rename($tmpFile, $targetFile);
        }
    }

    // -------------------------------------------------------------------------
    // Phase 11 — Rollback Execution Engine
    // -------------------------------------------------------------------------

    /** @return array<string,mixed> */
    public static function loadApplyRecord(string $applyId): array
    {
        $safeId = preg_replace('/[^a-zA-Z0-9\-]/', '', $applyId);
        if ($safeId === '') {
            return [];
        }
        $storageDir = rtrim((string)(defined('APP_ROOT') ? APP_ROOT : ''), '/') . '/storage/appstudio/applies';
        $targetFile = $storageDir . '/apply_' . $safeId . '.json';
        if (!is_file($targetFile)) {
            self::backfillLegacyApplyRecords($safeId);
        }
        if (!is_file($targetFile)) {
            return [];
        }
        $content = file_get_contents($targetFile);
        if ($content === false) {
            return [];
        }
        $record = json_decode($content, true);
        return is_array($record) ? $record : [];
    }

    /** @return array<string,mixed> */
    public static function executeRollback(array $applyRecord): array
    {
        $rollbackId = self::previewUuid('rollback_exec:' . (string)($applyRecord['apply_id'] ?? ''));
        $startedAt = gmdate('c');
        $applyId = (string)($applyRecord['apply_id'] ?? '');
        $snapshotId = (string)($applyRecord['snapshot_id'] ?? '');

        if ($applyId === '') {
            return [
                'rollback_id' => $rollbackId,
                'apply_id' => '',
                'snapshot_id' => $snapshotId,
                'status' => 'FAILED',
                'started_at' => $startedAt,
                'completed_at' => gmdate('c'),
                'steps' => [],
                'summary' => ['total' => 0, 'done' => 0, 'failed' => 0, 'skipped' => 0],
                'message' => 'invalid_apply_record',
            ];
        }

        $rollbackBinding = is_array($applyRecord['rollback_binding'] ?? null) ? $applyRecord['rollback_binding'] : [];
        if ($rollbackBinding === []) {
            return [
                'rollback_id' => $rollbackId,
                'apply_id' => $applyId,
                'snapshot_id' => $snapshotId,
                'status' => 'FAILED',
                'started_at' => $startedAt,
                'completed_at' => gmdate('c'),
                'steps' => [],
                'summary' => ['total' => 0, 'done' => 0, 'failed' => 0, 'skipped' => 0],
                'message' => 'no_rollback_binding',
            ];
        }

        $bindingArtifacts = is_array($rollbackBinding['artifacts'] ?? null) ? $rollbackBinding['artifacts'] : [];

        // Safety guard: block if any unsafe path detected in rollback binding
        foreach ($bindingArtifacts as $step) {
            if (!is_array($step)) {
                continue;
            }
            $targetPath = (string)($step['target_path'] ?? '');
            if ($targetPath !== '' && !self::isSafePath($targetPath)) {
                return [
                    'rollback_id' => $rollbackId,
                    'apply_id' => $applyId,
                    'snapshot_id' => $snapshotId,
                    'status' => 'FAILED',
                    'started_at' => $startedAt,
                    'completed_at' => gmdate('c'),
                    'steps' => [],
                    'summary' => ['total' => 0, 'done' => 0, 'failed' => 0, 'skipped' => 0],
                    'message' => 'unsafe_path_detected',
                ];
            }
        }

        // Execute steps in REVERSE order; stop on first failure
        $reversedSteps = array_reverse($bindingArtifacts);
        $steps = [];
        $overallFailed = false;

        foreach ($reversedSteps as $step) {
            if (!is_array($step)) {
                continue;
            }
            $stepResult = self::executeRollbackStep($step);
            $steps[] = $stepResult;
            if ($stepResult['result'] === 'failed') {
                $overallFailed = true;
                break; // stop immediately on failure
            }
        }

        $done = count(array_filter($steps, static fn(array $s): bool => ($s['result'] ?? '') === 'done'));
        $failed = count(array_filter($steps, static fn(array $s): bool => ($s['result'] ?? '') === 'failed'));
        $skipped = count(array_filter($steps, static fn(array $s): bool => ($s['result'] ?? '') === 'skipped'));

        $result = [
            'rollback_id' => $rollbackId,
            'apply_id' => $applyId,
            'snapshot_id' => $snapshotId,
            'status' => $overallFailed ? 'FAILED' : 'COMPLETED',
            'started_at' => $startedAt,
            'completed_at' => gmdate('c'),
            'steps' => $steps,
            'summary' => [
                'total' => count($steps),
                'done' => $done,
                'failed' => $failed,
                'skipped' => $skipped,
            ],
            'message' => $overallFailed ? 'rollback_failed' : 'rollback_completed',
        ];

        self::recordHistoryEvent([
            'event' => 'rollback_' . strtolower($overallFailed ? 'FAILED' : 'COMPLETED'),
            'rollback_id' => $rollbackId,
            'apply_id' => $applyId,
            'snapshot_id' => $snapshotId,
            'status' => $overallFailed ? 'FAILED' : 'COMPLETED',
            'occurred_at' => gmdate('c'),
        ]);

        return $result;
    }

    /** @return array{artifact:string,rollback_action:string,reversible:bool,result:string,message:string} */
    private static function executeRollbackStep(array $step): array
    {
        $artifact = (string)($step['artifact'] ?? '');
        $rollbackAction = strtolower((string)($step['rollback_action'] ?? 'noop'));
        $reversible = !empty($step['reversible']);

        // Non-reversible steps are skipped, not failed
        if (!$reversible) {
            return [
                'artifact' => $artifact,
                'rollback_action' => $rollbackAction,
                'reversible' => false,
                'result' => 'done',
                'message' => 'non_reversible_skipped',
            ];
        }

        if ($rollbackAction === 'noop') {
            return [
                'artifact' => $artifact,
                'rollback_action' => 'noop',
                'reversible' => $reversible,
                'result' => 'done',
                'message' => 'noop',
            ];
        }

        // delete: undo a create — remove the file
        if ($rollbackAction === 'delete') {
            $targetPath = (string)($step['target_path'] ?? '');
            $deleted = self::deleteFileSafe($targetPath);
            return [
                'artifact' => $artifact,
                'rollback_action' => 'delete',
                'reversible' => $reversible,
                'result' => $deleted ? 'done' : 'failed',
                'message' => $deleted ? 'file_deleted' : 'file_delete_failed',
            ];
        }

        // restore: undo an update — write back the backup content
        if ($rollbackAction === 'restore') {
            $backup = is_array($step['backup'] ?? null) ? $step['backup'] : [];
            if ($backup === []) {
                // No backup captured; treat as non-reversible, skip without failure
                return [
                    'artifact' => $artifact,
                    'rollback_action' => 'restore',
                    'reversible' => $reversible,
                    'result' => 'done',
                    'message' => 'no_backup_non_reversible',
                ];
            }
            $restored = self::restoreFileSafe($backup);
            return [
                'artifact' => $artifact,
                'rollback_action' => 'restore',
                'reversible' => $reversible,
                'result' => $restored ? 'done' : 'failed',
                'message' => $restored ? 'file_restored' : 'file_restore_failed',
            ];
        }

        return [
            'artifact' => $artifact,
            'rollback_action' => $rollbackAction,
            'reversible' => $reversible,
            'result' => 'done',
            'message' => 'noop_unknown_action',
        ];
    }

    private static function deleteFileSafe(string $path): bool
    {
        if ($path === '' || !self::isSafePath($path)) {
            return false;
        }
        $absPath = rtrim((string)(defined('APP_ROOT') ? APP_ROOT : ''), '/') . '/' . ltrim($path, '/');
        // Only delete regular files; never recursive directory removal
        if (!is_file($absPath)) {
            return false;
        }
        return unlink($absPath);
    }

    private static function restoreFileSafe(array $backup): bool
    {
        $path = (string)($backup['path'] ?? '');
        $content = $backup['content'] ?? null;
        if ($path === '' || !is_string($content) || $content === '' || !self::isSafePath($path)) {
            return false;
        }
        $absPath = rtrim((string)(defined('APP_ROOT') ? APP_ROOT : ''), '/') . '/' . ltrim($path, '/');
        $dir = dirname($absPath);
        if (!is_dir($dir)) {
            return false;
        }
        // Atomic write: temp file → rename
        $tmpFile = $dir . '/.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($tmpFile, $content, LOCK_EX) === false) {
            return false;
        }
        return rename($tmpFile, $absPath);
    }

    // -------------------------------------------------------------------------
    // Phase 9 Correction — Rollback Foundation (preview only, no execution)
    // -------------------------------------------------------------------------

    /** @return array<string,mixed> */
    public static function buildRollbackPlan(array $snapshot): array
    {
        $snapshotId = (string)($snapshot['snapshot_id'] ?? '');
        $rollbackId = self::previewUuid('rollback:' . $snapshotId);
        $artifacts = is_array($snapshot['artifacts'] ?? null) ? $snapshot['artifacts'] : [];
        $rollbackArtifacts = [];

        foreach ($artifacts as $artifact) {
            if (!is_array($artifact)) {
                continue;
            }
            $plannedAction = strtolower((string)($artifact['change_type'] ?? $artifact['action'] ?? 'unknown'));
            $rb = self::determineRollbackAction(array_merge($artifact, ['planned_action' => $plannedAction]));
            $targetPath = (string)($artifact['target_path'] ?? '');
            $backup = [];
            // For restore action: capture current file content before any writes
            if ($rb['rollback_action'] === 'restore' && $targetPath !== '' && self::isSafePath($targetPath)) {
                $absPath = rtrim((string)(defined('APP_ROOT') ? APP_ROOT : ''), '/') . '/' . ltrim($targetPath, '/');
                if (is_file($absPath)) {
                    $content = @file_get_contents($absPath);
                    if ($content !== false) {
                        $backup = [
                            'path' => $targetPath,
                            'content' => $content,
                            'captured_at' => gmdate('c'),
                        ];
                    }
                }
            }
            $rollbackArtifacts[] = [
                'artifact' => (string)($artifact['artifact_id'] ?? ''),
                'target_path' => $targetPath,
                'planned_action' => $plannedAction,
                'rollback_action' => $rb['rollback_action'],
                'reversible' => $rb['reversible'],
                'backup' => $backup,
            ];
        }

        return [
            'rollback_id' => $rollbackId,
            'snapshot_id' => $snapshotId,
            'artifacts' => $rollbackArtifacts,
            'summary' => self::summarizeRollback(['artifacts' => $rollbackArtifacts]),
        ];
    }

    /** @return array{rollback_action:string,reversible:bool} */
    private static function determineRollbackAction(array $artifact): array
    {
        $plannedAction = strtolower((string)($artifact['planned_action'] ?? $artifact['change_type'] ?? 'unknown'));
        $ownershipScope = strtolower((string)($artifact['ownership_scope'] ?? 'unknown'));

        // Non-reversible conditions
        $nonReversible = in_array($ownershipScope, ['unmanaged', 'external'], true) || $plannedAction === 'unknown';

        $rollbackAction = match ($plannedAction) {
            'create' => 'delete',
            'update', 'conflict' => 'restore',
            default => 'noop',
        };

        return [
            'rollback_action' => $rollbackAction,
            'reversible' => !$nonReversible,
        ];
    }

    /** @return array{total:int,reversible:int,non_reversible:int} */
    public static function summarizeRollback(array $plan): array
    {
        $artifacts = is_array($plan['artifacts'] ?? null) ? $plan['artifacts'] : [];
        $reversible = 0;
        $nonReversible = 0;

        foreach ($artifacts as $artifact) {
            if (!is_array($artifact)) {
                continue;
            }
            if (!empty($artifact['reversible'])) {
                $reversible++;
            } else {
                $nonReversible++;
            }
        }

        return [
            'total' => count($artifacts),
            'reversible' => $reversible,
            'non_reversible' => $nonReversible,
        ];
    }

    // -------------------------------------------------------------------------
    // Phase 12 — Snapshot History
    // -------------------------------------------------------------------------

    public static function persistSnapshot(array $snapshot): void
    {
        $snapshotId = preg_replace('/[^a-zA-Z0-9\-:_]/', '_', (string)($snapshot['snapshot_id'] ?? 'unknown'));
        $storageDir = rtrim((string)(defined('APP_ROOT') ? APP_ROOT : ''), '/') . '/storage/appstudio/snapshots';

        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0775, true);
        }

        $filename = 'snapshot_' . preg_replace('/[^a-zA-Z0-9\-_]/', '_', $snapshotId) . '.json';
        $targetFile = $storageDir . '/' . $filename;
        $tmpFile = $storageDir . '/.' . bin2hex(random_bytes(4)) . '.tmp';

        $payload = [
            'snapshot_id' => (string)($snapshot['snapshot_id'] ?? ''),
            'compile_id' => (string)($snapshot['compile_id'] ?? ''),
            'approval_id' => (string)($snapshot['approval_id'] ?? ''),
            'snapshot_hash' => (string)($snapshot['snapshot_hash'] ?? ''),
            'created_at' => (string)($snapshot['created_at'] ?? gmdate('c')),
            'approval_valid' => !empty($snapshot['approval_valid']),
            'risk_summary' => is_array($snapshot['risk_summary'] ?? null) ? $snapshot['risk_summary'] : [],
            'approval_summary' => is_array($snapshot['approval_summary'] ?? null) ? $snapshot['approval_summary'] : [],
            'integrity' => is_array($snapshot['integrity'] ?? null) ? $snapshot['integrity'] : [],
            'artifact_ids' => array_values(array_map(
                static fn(array $a): string => (string)($a['artifact_id'] ?? ''),
                array_filter(is_array($snapshot['artifacts'] ?? null) ? $snapshot['artifacts'] : [], 'is_array')
            )),
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return;
        }

        if (file_put_contents($tmpFile, $json, LOCK_EX) !== false) {
            rename($tmpFile, $targetFile);
        }

        self::recordHistoryEvent([
            'event' => 'snapshot_persisted',
            'snapshot_id' => (string)($snapshot['snapshot_id'] ?? ''),
            'compile_id' => (string)($snapshot['compile_id'] ?? ''),
            'snapshot_hash' => (string)($snapshot['snapshot_hash'] ?? ''),
            'occurred_at' => gmdate('c'),
        ]);
    }

    public static function loadSnapshotRecord(string $snapshotId): array
    {
        $safeId = preg_replace('/[^a-zA-Z0-9\-:_]/', '_', $snapshotId);
        if ($safeId === '') {
            return [];
        }
        $storageDir = rtrim((string)(defined('APP_ROOT') ? APP_ROOT : ''), '/') . '/storage/appstudio/snapshots';
        $filename = 'snapshot_' . preg_replace('/[^a-zA-Z0-9\-_]/', '_', $safeId) . '.json';
        $targetFile = $storageDir . '/' . $filename;
        if (!is_file($targetFile)) {
            foreach (glob($storageDir . '/*.json') ?: [] as $candidate) {
                $candidateContent = @file_get_contents($candidate);
                $candidateRecord = $candidateContent !== false ? json_decode((string)$candidateContent, true) : null;
                if (
                    is_array($candidateRecord)
                    && (
                        (string)($candidateRecord['snapshot_id'] ?? '') === $snapshotId
                        || (string)($candidateRecord['compile_id'] ?? '') === $snapshotId
                    )
                ) {
                    return $candidateRecord;
                }
            }
            return [];
        }
        $content = file_get_contents($targetFile);
        if ($content === false) {
            return [];
        }
        $record = json_decode($content, true);
        return is_array($record) ? $record : [];
    }

    /** @return array<int,array<string,mixed>> */
    public static function loadSnapshotHistory(): array
    {
        $storageDir = rtrim((string)(defined('APP_ROOT') ? APP_ROOT : ''), '/') . '/storage/appstudio/snapshots';
        if (!is_dir($storageDir)) {
            return [];
        }
        $results = [];
        foreach (glob($storageDir . '/*.json') ?: [] as $file) {
            $content = @file_get_contents($file);
            if ($content === false) {
                continue;
            }
            $record = json_decode($content, true);
            if (is_array($record)) {
                $results[] = $record;
            }
        }
        usort($results, static fn(array $a, array $b): int => strcmp(
            (string)($b['created_at'] ?? ''),
            (string)($a['created_at'] ?? '')
        ));
        return $results;
    }

    public static function recordHistoryEvent(array $event): void
    {
        $storageDir = rtrim((string)(defined('APP_ROOT') ? APP_ROOT : ''), '/') . '/storage/appstudio';
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0775, true);
        }

        $historyFile = $storageDir . '/history.json';
        $tmpFile = $storageDir . '/.history.' . bin2hex(random_bytes(4)) . '.tmp';

        // Load existing history (single JSON array)
        $history = [];
        if (is_file($historyFile)) {
            $raw = @file_get_contents($historyFile);
            if ($raw !== false) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $history = $decoded;
                }
            }
        }

        $history[] = array_merge(['event' => 'unknown', 'occurred_at' => gmdate('c')], $event);

        // Keep last 500 events
        if (count($history) > 500) {
            $history = array_slice($history, -500);
        }

        $json = json_encode(array_values($history), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return;
        }
        if (file_put_contents($tmpFile, $json, LOCK_EX) !== false) {
            rename($tmpFile, $historyFile);
        }
    }

    /** @return array<int,array<string,mixed>> */
    public static function loadHistory(): array
    {
        $historyFile = rtrim((string)(defined('APP_ROOT') ? APP_ROOT : ''), '/') . '/storage/appstudio/history.json';
        if (!is_file($historyFile)) {
            return [];
        }
        $raw = @file_get_contents($historyFile);
        if ($raw === false) {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? array_values($decoded) : [];
    }

    /** @return array<int,array<string,mixed>> */
    public static function loadApplyHistory(): array
    {
        self::backfillLegacyApplyRecords();
        $storageDir = rtrim((string)(defined('APP_ROOT') ? APP_ROOT : ''), '/') . '/storage/appstudio/applies';
        $resultsById = [];
        if (is_dir($storageDir)) {
            foreach (glob($storageDir . '/apply_*.json') ?: [] as $file) {
                $content = @file_get_contents($file);
                if ($content === false) {
                    continue;
                }
                $record = json_decode($content, true);
                if (is_array($record)) {
                    $normalized = [
                    'apply_id' => (string)($record['apply_id'] ?? ''),
                    'snapshot_id' => (string)($record['snapshot_id'] ?? ''),
                    'status' => (string)($record['status'] ?? ''),
                    'started_at' => (string)($record['started_at'] ?? ''),
                    'completed_at' => (string)($record['completed_at'] ?? ''),
                    'has_rollback_binding' => !empty($record['rollback_binding']),
                    'verification_status' => (string)($record['post_publish_verification']['status'] ?? ''),
                    ];
                    $applyId = $normalized['apply_id'];
                    if ($applyId !== '') {
                        $resultsById[$applyId] = $normalized;
                    }
                }
            }
        }
        $results = array_values($resultsById);
        usort($results, static fn(array $a, array $b): int => strcmp(
            (string)($b['started_at'] ?? ''),
            (string)($a['started_at'] ?? '')
        ));
        return $results;
    }

    private static function backfillLegacyApplyRecords(?string $targetApplyId = null): void
    {
        $logPath = self::storageAbsolutePath(self::APPSTUDIO_STORAGE_ROOT . '/apply_log.ndjson');
        if (!is_file($logPath)) {
            return;
        }
        $lines = @file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines) || $lines === []) {
            return;
        }

        $storageDir = rtrim((string)(defined('APP_ROOT') ? APP_ROOT : ''), '/') . '/storage/appstudio/applies';
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0775, true);
        }

        foreach ($lines as $line) {
            $decoded = json_decode((string)$line, true);
            if (!is_array($decoded)) {
                continue;
            }
            $event = strtolower((string)($decoded['event'] ?? ''));
            if (!in_array($event, ['apply_applied', 'apply_failed'], true)) {
                continue;
            }
            $applyId = trim((string)($decoded['apply_id'] ?? ''));
            if ($applyId === '') {
                continue;
            }
            if ($targetApplyId !== null && $applyId !== $targetApplyId) {
                continue;
            }

            $targetFile = $storageDir . '/apply_' . preg_replace('/[^a-zA-Z0-9\-]/', '_', $applyId) . '.json';
            if (is_file($targetFile)) {
                if ($targetApplyId !== null) {
                    return;
                }
                continue;
            }

            self::persistApplyRecord(self::legacyApplyRecordFromLogEntry($decoded));

            if ($targetApplyId !== null) {
                return;
            }
        }
    }

    /** @param array<string,mixed> $entry @return array<string,mixed> */
    private static function legacyApplyRecordFromLogEntry(array $entry): array
    {
        $timestamp = (string)($entry['timestamp'] ?? '');
        $verificationStatus = trim((string)($entry['verification_status'] ?? ''));

        return [
            'apply_id' => (string)($entry['apply_id'] ?? ''),
            'snapshot_id' => (string)($entry['snapshot_id'] ?? ''),
            'compile_id' => (string)($entry['compile_id'] ?? ''),
            'approval_id' => '',
            'status' => (string)($entry['status'] ?? 'UNKNOWN'),
            'started_at' => $timestamp,
            'completed_at' => $timestamp,
            'integrity' => [],
            'results' => [],
            'steps' => [],
            'rollback_binding' => [],
            'post_publish_verification' => $verificationStatus === '' ? [] : [
                'status' => $verificationStatus,
                'checks' => [],
                'source' => 'legacy_apply_log',
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Phase 13 — Apply Hardening
    // -------------------------------------------------------------------------

    /** @return array{applied:bool,apply_id:string} */
    public static function isSnapshotAlreadyApplied(string $snapshotId): array
    {
        if ($snapshotId === '') {
            return ['applied' => false, 'apply_id' => ''];
        }
        $applyHistory = self::loadApplyHistory();
        foreach ($applyHistory as $record) {
            if (
                is_array($record)
                && (string)($record['snapshot_id'] ?? '') === $snapshotId
                && strtoupper((string)($record['status'] ?? '')) === 'APPLIED'
            ) {
                return ['applied' => true, 'apply_id' => (string)($record['apply_id'] ?? '')];
            }
        }
        return ['applied' => false, 'apply_id' => ''];
    }

    private static function applyLockPath(string $snapshotId): string
    {
        $safeSid = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $snapshotId);
        return rtrim((string)(defined('APP_ROOT') ? APP_ROOT : ''), '/')
            . '/storage/appstudio/.apply_lock_' . $safeSid . '.lock';
    }

    /** @param array<string,mixed> $snapshot @param array<string,mixed> $apply @param array<string,mixed> $context @return array<string,mixed> */
    private static function verifyPostPublishState(array $snapshot, array $apply, array $context = []): array
    {
        $checks = [];
        $applyStatus = strtoupper((string)($apply['status'] ?? 'UNKNOWN'));
        $snapshotId = (string)($snapshot['snapshot_id'] ?? $apply['snapshot_id'] ?? '');
        $compileId = (string)($snapshot['compile_id'] ?? $apply['compile_id'] ?? '');
        $snapshotHash = (string)($snapshot['snapshot_hash'] ?? ($apply['integrity']['snapshot_hash'] ?? ''));
        $publishDecisionId = (string)($snapshot['publish_decision_id'] ?? $apply['publish_decision_id'] ?? '');
        $scope = $context !== [] || !empty($apply['generated_module']) ? 'generated' : 'generic';

        $checks[] = self::verificationCheck('apply_status_applied', $applyStatus === 'APPLIED', $applyStatus);
        $checks[] = self::verificationCheck(
            'publish_decision_record_present',
            $publishDecisionId !== '' && StudioGovernanceService::hasPublishDecisionRecord($publishDecisionId),
            $publishDecisionId
        );

        if ($scope === 'generated') {
            $generatedModule = is_array($apply['generated_module'] ?? null) ? $apply['generated_module'] : [];
            $liveRelativePath = (string)($context['relative_path'] ?? $generatedModule['relative_path'] ?? '');
            $liveManifest = self::loadGeneratedLiveManifest($liveRelativePath);
            $storedSnapshot = self::loadGeneratedSnapshotByCompileId($compileId);
            $registry = self::loadGeneratedAppRegistry();
            $appKey = self::generatedKey((string)($context['app_key'] ?? $generatedModule['app_key'] ?? ''));
            $registryEntry = is_array($registry[$appKey] ?? null) ? $registry[$appKey] : [];

            $checks[] = self::verificationCheck('live_manifest_present', $liveManifest !== [], $liveRelativePath);
            $checks[] = self::verificationCheck(
                'live_compile_matches',
                $liveManifest !== [] && (string)($liveManifest['compile_id'] ?? '') === $compileId,
                (string)($liveManifest['compile_id'] ?? '')
            );
            $checks[] = self::verificationCheck(
                'live_snapshot_matches',
                $liveManifest !== [] && (string)($liveManifest['snapshot_id'] ?? '') === $snapshotId,
                (string)($liveManifest['snapshot_id'] ?? '')
            );
            $checks[] = self::verificationCheck(
                'stored_snapshot_present',
                $storedSnapshot !== [],
                self::snapshotPathForCompileId($compileId)
            );
            $checks[] = self::verificationCheck(
                'stored_snapshot_hash_matches',
                $storedSnapshot !== [] && (string)($storedSnapshot['snapshot_hash'] ?? '') === $snapshotHash,
                (string)($storedSnapshot['snapshot_hash'] ?? '')
            );
            $checks[] = self::verificationCheck(
                'stored_publish_decision_matches',
                $storedSnapshot !== [] && (string)($storedSnapshot['publish_decision_id'] ?? '') === $publishDecisionId,
                (string)($storedSnapshot['publish_decision_id'] ?? '')
            );
            $checks[] = self::verificationCheck(
                'registry_version_matches',
                $registryEntry !== [] && (string)($registryEntry['current_version'] ?? '') === $compileId,
                (string)($registryEntry['current_version'] ?? '')
            );
            $checks[] = self::verificationCheck(
                'registry_publish_decision_matches',
                $registryEntry !== [] && (string)($registryEntry['publish_decision_id'] ?? '') === $publishDecisionId,
                (string)($registryEntry['publish_decision_id'] ?? '')
            );
        } else {
            $storedSnapshot = self::loadSnapshotRecord($snapshotId);
            $applyResults = is_array($apply['results'] ?? null) ? $apply['results'] : [];
            $allApplied = count(array_filter(
                $applyResults,
                static fn($result): bool => is_array($result) && strtolower((string)($result['status'] ?? '')) !== 'applied'
            )) === 0;

            $checks[] = self::verificationCheck('stored_snapshot_present', $storedSnapshot !== [], $snapshotId);
            $checks[] = self::verificationCheck(
                'stored_snapshot_hash_matches',
                $storedSnapshot !== [] && (string)($storedSnapshot['snapshot_hash'] ?? '') === $snapshotHash,
                (string)($storedSnapshot['snapshot_hash'] ?? '')
            );
            $checks[] = self::verificationCheck(
                'apply_results_applied',
                $applyResults === [] || $allApplied,
                (string)count($applyResults)
            );
        }

        $failedChecks = array_values(array_filter($checks, static fn(array $check): bool => empty($check['pass'])));

        return [
            'scope' => $scope,
            'status' => $failedChecks === [] ? 'VERIFIED' : 'FAILED',
            'verified_at' => gmdate('c'),
            'checks' => $checks,
            'summary' => [
                'total' => count($checks),
                'passed' => count($checks) - count($failedChecks),
                'failed' => count($failedChecks),
            ],
        ];
    }

    /** @return array{name:string,pass:bool,detail:string} */
    private static function verificationCheck(string $name, bool $pass, string $detail = ''): array
    {
        return [
            'name' => $name,
            'pass' => $pass,
            'detail' => $detail,
        ];
    }

    /** Returns true if lock was acquired; false if another apply is in progress */
    private static function acquireApplyLock(string $snapshotId): bool
    {
        $lockFile = self::applyLockPath($snapshotId);
        $storageDir = dirname($lockFile);
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0775, true);
        }
        // Stale lock: older than 5 minutes → release automatically
        if (is_file($lockFile)) {
            $mtime = @filemtime($lockFile);
            if ($mtime !== false && (time() - $mtime) > 300) {
                @unlink($lockFile);
            }
        }
        // O_EXCL: atomic create; fails if file already exists
        $fd = @fopen($lockFile, 'x');
        if ($fd === false) {
            return false;
        }
        fwrite($fd, (string)getmypid());
        fclose($fd);
        return true;
    }

    private static function releaseApplyLock(string $snapshotId): void
    {
        $lockFile = self::applyLockPath($snapshotId);
        if (is_file($lockFile)) {
            @unlink($lockFile);
        }
    }

    // -------------------------------------------------------------------------
    // Phase 14 — Packaging
    // -------------------------------------------------------------------------

    private static function packagesDir(): string
    {
        return rtrim((string)(defined('APP_ROOT') ? APP_ROOT : ''), '/')
            . '/storage/appstudio/packages';
    }

    private static function packageSigningKeyPath(): string
    {
        return rtrim((string)(defined('APP_ROOT') ? APP_ROOT : ''), '/')
            . '/storage/appstudio/.package_signing.key';
    }

    private static function packageSigningKey(): ?string
    {
        $keyPath = self::packageSigningKeyPath();
        $dir = dirname($keyPath);
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
            return null;
        }

        if (is_file($keyPath)) {
            $stored = trim((string)@file_get_contents($keyPath));
            if ($stored !== '') {
                $decoded = base64_decode($stored, true);
                if (is_string($decoded) && $decoded !== '') {
                    return $decoded;
                }
            }
        }

        try {
            $key = random_bytes(32);
        } catch (\Throwable $e) {
            return null;
        }

        $encoded = base64_encode($key);
        if (@file_put_contents($keyPath, $encoded, LOCK_EX) === false) {
            return null;
        }
        @chmod($keyPath, 0600);
        return $key;
    }

    private static function packageSignaturePath(string $zipPath): string
    {
        return preg_replace('/\.zip$/', '.sig.json', $zipPath) ?: ($zipPath . '.sig.json');
    }

    /** @param array<string,mixed> $manifest @return array<string,mixed>|null */
    private static function signPackageFile(string $zipPath, array $manifest): ?array
    {
        if (!is_file($zipPath)) {
            return null;
        }

        $key = self::packageSigningKey();
        if ($key === null || $key === '') {
            return null;
        }

        $packageHash = hash_file('sha256', $zipPath);
        if (!is_string($packageHash) || $packageHash === '') {
            return null;
        }

        $manifestJson = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($manifestJson)) {
            return null;
        }

        $record = [
            'schema_version' => 'studio.package-signature.v1',
            'package_filename' => basename($zipPath),
            'package_sha256' => $packageHash,
            'manifest_sha256' => hash('sha256', $manifestJson),
            'signature_algorithm' => 'hmac-sha256',
            'signed_at' => gmdate('c'),
        ];
        $record['signature'] = base64_encode(hash_hmac(
            'sha256',
            json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '',
            $key,
            true
        ));

        $signaturePath = self::packageSignaturePath($zipPath);
        $written = @file_put_contents(
            $signaturePath,
            json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
        if ($written === false || $written <= 0) {
            return null;
        }

        self::recordHistoryEvent([
            'event' => 'package_signed',
            'snapshot_id' => (string)($manifest['snapshot_id'] ?? ''),
            'apply_id' => '',
            'status' => 'SIGNED',
            'occurred_at' => gmdate('c'),
        ]);

        return $record;
    }

    /** @return array<string,mixed> */
    private static function verifyPackageSignature(string $zipPath): array
    {
        $signaturePath = self::packageSignaturePath($zipPath);
        $signatureRecord = self::readJsonFileSafely($signaturePath);
        if (!is_array($signatureRecord)) {
            return [
                'status' => 'UNSIGNED',
                'signature_path' => basename($signaturePath),
                'signed_at' => '',
            ];
        }

        $key = self::packageSigningKey();
        $packageHash = is_file($zipPath) ? hash_file('sha256', $zipPath) : false;
        $expectedPackageHash = (string)($signatureRecord['package_sha256'] ?? '');
        $signature = (string)($signatureRecord['signature'] ?? '');
        $signedAt = (string)($signatureRecord['signed_at'] ?? '');
        $recordForSigning = $signatureRecord;
        unset($recordForSigning['signature']);
        $signingPayload = json_encode($recordForSigning, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $expectedSignature = is_string($signingPayload) && is_string($key) && $key !== ''
            ? base64_encode(hash_hmac('sha256', $signingPayload, $key, true))
            : '';

        $valid = is_string($packageHash)
            && $packageHash !== ''
            && hash_equals($expectedPackageHash, $packageHash)
            && $expectedSignature !== ''
            && hash_equals($signature, $expectedSignature);

        return [
            'status' => $valid ? 'VALID' : 'INVALID',
            'signature_path' => basename($signaturePath),
            'signed_at' => $signedAt,
        ];
    }

    /**
     * Build a package manifest from a snapshot (no file content, metadata only).
     * Safe to call without isSafePath — reads no filesystem paths.
     *
     * @return array<string,mixed>
     */
    public static function exportSnapshotPackage(array $snapshot): array
    {
        $snapshotId = (string)($snapshot['snapshot_id'] ?? '');
        $compileId = (string)($snapshot['compile_id'] ?? '');
        $snapshotHash = (string)($snapshot['snapshot_hash'] ?? '');
        $artifacts = is_array($snapshot['artifacts'] ?? null) ? $snapshot['artifacts'] : [];

        $artifactMeta = array_map(static function (array $a): array {
            return [
                'artifact_id' => (string)($a['artifact_id'] ?? ''),
                'target_path' => (string)($a['target_path'] ?? ''),
                'change_type' => (string)($a['change_type'] ?? ''),
                'risk_level' => (string)($a['risk_level'] ?? ''),
                'ownership_scope' => (string)($a['ownership_scope'] ?? ''),
                'owning_app' => (string)($a['owning_app'] ?? ''),
            ];
        }, array_filter($artifacts, 'is_array'));

        $manifest = [
            'package_version' => '1.0',
            'package_type' => 'gui_studio_snapshot',
            'snapshot_id' => $snapshotId,
            'compile_id' => $compileId,
            'snapshot_hash' => $snapshotHash,
            'artifact_count' => count($artifactMeta),
            'created_at' => gmdate('c'),
            'approval_valid' => !empty($snapshot['approval_valid']),
            'approval_decision' => (string)(
                ($snapshot['approval_summary'] ?? [])['decision'] ?? ''
            ),
        ];

        return [
            'manifest' => $manifest,
            'artifacts' => array_values($artifactMeta),
        ];
    }

    /**
     * Persist a snapshot package ZIP to storage/appstudio/packages/.
     * Returns the relative package path or null on failure.
     * Does NOT embed raw file content — only metadata is packaged.
     */
    public static function persistSnapshotPackage(array $snapshot): ?string
    {
        if (!class_exists('ZipArchive')) {
            return null;
        }

        $packDir = self::packagesDir();
        if (!is_dir($packDir)) {
            mkdir($packDir, 0775, true);
        }

        $package = self::exportSnapshotPackage($snapshot);
        $snapshotId = (string)($package['manifest']['snapshot_id'] ?? '');
        if ($snapshotId === '') {
            return null;
        }
        $safeId = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $snapshotId);
        $zipPath = $packDir . '/package_' . $safeId . '.zip';

        $zip = new \ZipArchive();
        $result = $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if ($result !== true) {
            return null;
        }

        $zip->addFromString('manifest.json', (string)json_encode($package['manifest'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->addFromString('artifacts.json', (string)json_encode($package['artifacts'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->addFromString('snapshot_summary.json', (string)json_encode([
            'snapshot_id' => $snapshotId,
            'compile_id' => (string)($snapshot['compile_id'] ?? ''),
            'snapshot_hash' => (string)($snapshot['snapshot_hash'] ?? ''),
            'approval_valid' => !empty($snapshot['approval_valid']),
            'created_at' => gmdate('c'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->close();

        $signatureRecord = self::signPackageFile($zipPath, is_array($package['manifest'] ?? null) ? $package['manifest'] : []);
        if ($signatureRecord === null) {
            @unlink($zipPath);
            @unlink(self::packageSignaturePath($zipPath));
            return null;
        }

        // Record packaging event in history
        self::recordHistoryEvent([
            'event' => 'package_created',
            'snapshot_id' => $snapshotId,
            'apply_id' => '',
            'status' => 'OK',
            'package_path' => 'storage/appstudio/packages/package_' . $safeId . '.zip',
            'occurred_at' => gmdate('c'),
        ]);

        return 'storage/appstudio/packages/package_' . $safeId . '.zip';
    }

    /** @return array<int,array<string,mixed>> */
    public static function loadPackageHistory(): array
    {
        $packDir = self::packagesDir();
        if (!is_dir($packDir)) {
            return [];
        }
        $files = glob($packDir . '/package_*.zip');
        if ($files === false || $files === []) {
            return [];
        }
        $results = [];
        foreach ($files as $filePath) {
            $filename = basename($filePath);
            $signature = self::verifyPackageSignature($filePath);
            $results[] = [
                'filename' => $filename,
                'path' => 'storage/appstudio/packages/' . $filename,
                'size_bytes' => (int)filesize($filePath),
                'modified_at' => gmdate('c', (int)filemtime($filePath)),
                'signature_status' => (string)($signature['status'] ?? 'UNSIGNED'),
                'signature_path' => (string)($signature['signature_path'] ?? ''),
                'signed_at' => (string)($signature['signed_at'] ?? ''),
            ];
        }
        usort($results, static fn(array $a, array $b): int => strcmp(
            (string)($b['modified_at'] ?? ''),
            (string)($a['modified_at'] ?? '')
        ));
        return $results;
    }

    // -------------------------------------------------------------------------
    // Phase 15 — Registry Integration
    // -------------------------------------------------------------------------

    private static function registryPath(): string
    {
        return rtrim((string)(defined('APP_ROOT') ? APP_ROOT : ''), '/')
            . '/storage/appstudio/registry.json';
    }

    /** @return array<int,array<string,mixed>> */
    public static function loadRegistry(): array
    {
        $path = self::registryPath();
        if (!is_file($path)) {
            return [];
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function persistRegistry(array $registry): void
    {
        $path = self::registryPath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $tmp = $path . '.tmp.' . bin2hex(random_bytes(4));
        file_put_contents($tmp, (string)json_encode(array_values($registry), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        rename($tmp, $path);
    }

    /**
     * Register a package into the local registry.
     *
     * @return array<string,mixed> registry entry
     */
    public static function registerPackage(array $manifest, string $packagePath): array
    {
        $snapshotId = (string)($manifest['snapshot_id'] ?? '');
        $packageId = 'pkg_' . preg_replace('/[^a-zA-Z0-9\-_]/', '_', $snapshotId);
        $registry = self::loadRegistry();
        $displayName = trim((string)($manifest['display_name'] ?? ''));
        if ($displayName === '') {
            $displayName = 'Studio App ' . substr($snapshotId, 0, 8);
        }
        $version = trim((string)($manifest['version'] ?? ''));
        if ($version === '') {
            $version = 'snapshot-' . substr($snapshotId, 0, 8);
        }

        $routePath = trim((string)($manifest['route_path'] ?? ''));
        if ($routePath === '') {
            $slug = self::studioSlugFromAppKey($packageId);
            $routePath = '/apps/' . $slug;
        }

        // Prevent duplicate registration
        foreach ($registry as $entry) {
            if (is_array($entry) && (string)($entry['package_id'] ?? '') === $packageId) {
                return $entry; // already registered
            }
        }

        $entry = [
            'package_id' => $packageId,
            'app_key' => $packageId,
            'display_name' => $displayName,
            'source' => 'studio',
            'snapshot_id' => $snapshotId,
            'compile_id' => (string)($manifest['compile_id'] ?? ''),
            'snapshot_hash' => (string)($manifest['snapshot_hash'] ?? ''),
            'version' => $version,
            'route_path' => $routePath,
            'package_path' => $packagePath,
            'status' => 'enabled',
            'installed_at' => gmdate('c'),
            'artifact_count' => (int)($manifest['artifact_count'] ?? 0),
            'approval_valid' => !empty($manifest['approval_valid']),
            'view_manifest' => [
                'view_key' => 'studio_table_' . substr($snapshotId, 0, 8),
                'view_kind' => 'table',
                'data_source' => 'provider',
                'data_provider' => 'OrdersProvider',
                'adapter' => 'TableAdapter',
                'fields' => [
                    ['key' => 'order_no'],
                    ['key' => 'customer'],
                    ['key' => 'state'],
                    ['key' => 'status'],
                    ['key' => 'next_actions_text'],
                ],
                'actions' => [
                    ['action_key' => 'submit_order', 'handler' => 'OrderSubmitHandler'],
                    ['action_key' => 'transition_order', 'handler' => 'OrderTransitionHandler'],
                ],
                'data' => [
                    'rows' => [],
                ],
            ],
        ];

        $registry[] = $entry;
        self::persistRegistry($registry);

        self::recordHistoryEvent([
            'event' => 'package_registered',
            'snapshot_id' => $snapshotId,
            'apply_id' => '',
            'status' => 'enabled',
            'package_id' => $packageId,
            'occurred_at' => gmdate('c'),
        ]);

        return $entry;
    }

    public static function enablePackage(string $packageId): bool
    {
        return self::setPackageStatus($packageId, 'enabled');
    }

    public static function disablePackage(string $packageId): bool
    {
        return self::setPackageStatus($packageId, 'disabled');
    }

    private static function setPackageStatus(string $packageId, string $status): bool
    {
        $registry = self::loadRegistry();
        $found = false;
        foreach ($registry as &$entry) {
            if (is_array($entry) && (string)($entry['package_id'] ?? '') === $packageId) {
                $entry['status'] = $status;
                $found = true;
                break;
            }
        }
        unset($entry);
        if ($found) {
            self::persistRegistry($registry);
        }
        return $found;
    }

    private static function studioSlugFromAppKey(string $appKey): string
    {
        $slug = strtolower((string)preg_replace('/[^a-z0-9]+/i', '-', $appKey));
        $slug = trim($slug, '-');
        return $slug !== '' ? $slug : 'studio-app';
    }

    /**
     * Normalize studio registry entries for runtime/UI usage.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function studioRegistryApps(): array
    {
        $registry = self::loadRegistry();
        $apps = [];

        foreach ($registry as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $appKey = trim((string)($entry['app_key'] ?? $entry['package_id'] ?? ''));
            if ($appKey === '') {
                continue;
            }

            $status = strtolower(trim((string)($entry['status'] ?? 'disabled')));
            $status = $status === 'enabled' ? 'enabled' : 'disabled';

            $displayName = trim((string)($entry['display_name'] ?? ''));
            if ($displayName === '') {
                $displayName = 'Studio App ' . substr((string)($entry['snapshot_id'] ?? ''), 0, 8);
            }

            $routePath = trim((string)($entry['route_path'] ?? ''));
            if ($routePath === '') {
                $routePath = '/apps/' . self::studioSlugFromAppKey($appKey);
            }

            $apps[] = [
                'app_key' => $appKey,
                'display_name' => $displayName,
                'source' => 'studio',
                'status' => $status,
                'installed_at' => (string)($entry['installed_at'] ?? ''),
                'snapshot_id' => (string)($entry['snapshot_id'] ?? ''),
                'version' => (string)($entry['version'] ?? ''),
                'route_path' => $routePath,
                'view_manifest' => is_array($entry['view_manifest'] ?? null) ? $entry['view_manifest'] : [],
            ];
        }

        usort($apps, static fn(array $a, array $b): int => strcmp(
            (string)($a['display_name'] ?? ''),
            (string)($b['display_name'] ?? '')
        ));

        return $apps;
    }

    public static function isStudioAppEnabled(string $appKey): bool
    {
        foreach (self::studioRegistryApps() as $app) {
            if ((string)($app['app_key'] ?? '') === $appKey) {
                return (string)($app['status'] ?? 'disabled') === 'enabled';
            }
        }
        return false;
    }

    public static function setStudioAppEnabled(string $appKey, bool $enabled): bool
    {
        return $enabled ? self::enablePackage($appKey) : self::disablePackage($appKey);
    }

    /**
     * @param array<int,array<string,mixed>> $coreApps
     * @return array<int,array<string,mixed>>
     */
    public static function mergeAppsForManager(array $coreApps): array
    {
        $merged = [];
        $seen = [];

        foreach ($coreApps as $row) {
            if (!is_array($row)) {
                continue;
            }
            $appKey = trim((string)($row['app_key'] ?? ''));
            if ($appKey === '') {
                continue;
            }
            $seen[$appKey] = true;
            $row['source'] = (string)($row['source'] ?? 'core');
            $merged[] = $row;
        }

        foreach (self::studioRegistryApps() as $studioApp) {
            $appKey = (string)($studioApp['app_key'] ?? '');
            if ($appKey === '' || isset($seen[$appKey])) {
                continue;
            }
            $merged[] = $studioApp;
            $seen[$appKey] = true;
        }

        return $merged;
    }

    /** @return array<int,array<string,string>> */
    public static function adminSidebarStudioEntries(): array
    {
        $entries = [];
        foreach (self::generatedModuleDefinitions() as $module) {
            if (!is_array($module)) {
                continue;
            }
            $entries[] = [
                'key' => (string)($module['app_key'] ?? '') . '.' . (string)($module['module_key'] ?? ''),
                'label' => (string)($module['label'] ?? $module['display_name'] ?? ''),
                'url' => (string)($module['route_path'] ?? ''),
            ];
        }

        foreach (self::studioRegistryApps() as $app) {
            if ((string)($app['status'] ?? 'disabled') !== 'enabled') {
                continue;
            }
            $entries[] = [
                'key' => (string)($app['app_key'] ?? ''),
                'label' => (string)($app['display_name'] ?? ''),
                'url' => (string)($app['route_path'] ?? ''),
            ];
        }
        return $entries;
    }

    /**
     * Generate role-based operational dashboard data with aggregation and prioritization.
     * @param string $dashboardType operator|qc|dispatch|admin
     * @param array<string,mixed> $user Current user context
     * @return array<string,mixed>
     */
    public static function generatedDashboardData(string $dashboardType, array $user = []): array
    {
        $dashboardType = strtolower(trim($dashboardType));
        if (!in_array($dashboardType, ['operator', 'qc', 'dispatch', 'admin'], true)) {
            $dashboardType = 'operator';
        }

        $userId = (string)($user['id'] ?? '');
        $userEmail = (string)($user['email'] ?? '');
        $nowTs = time();

        // Collect all tasks from all generated modules
        $allTasks = self::aggregateDashboardTasks($dashboardType, $userId, $userEmail);

        // Compute KPI counts
        $kpis = self::computeDashboardKpis($allTasks);

        // Prioritize tasks: overdue > near SLA breach > normal
        $prioritizedTasks = self::prioritizeDashboardTasks($allTasks, $nowTs);

        // Add delay indicators and action visibility
        $enhancedTasks = array_map(static function (array $task) use ($dashboardType, $nowTs): array {
            return self::enrichDashboardTaskUI($task, $dashboardType, $nowTs);
        }, $prioritizedTasks);

        return [
            'dashboard_type' => $dashboardType,
            'user_id' => $userId,
            'user_email' => $userEmail,
            'timestamp' => gmdate('c'),
            'kpis' => $kpis,
            'task_count' => count($enhancedTasks),
            'tasks' => array_values($enhancedTasks),
            'has_overdue' => $kpis['overdue_count'] > 0,
            'has_delayed' => $kpis['near_sla_count'] > 0,
        ];
    }

    /**
     * Aggregate tasks across all modules for a specific role and user.
     * @return array<int,array<string,mixed>>
     */
    private static function aggregateDashboardTasks(string $dashboardType, string $userId, string $userEmail): array
    {
        $tasks = [];

        foreach (self::generatedModuleDefinitions(false) as $module) {
            if (!is_array($module)) {
                continue;
            }

            $appKey = self::generatedKey((string)($module['app_key'] ?? ''));
            $moduleKey = self::generatedKey((string)($module['module_key'] ?? ''));
            if ($appKey === '' || $moduleKey === '') {
                continue;
            }

            $rows = self::loadGeneratedModuleRows($appKey, $moduleKey);
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                // Apply role-based visibility filtering
                if (!self::shouldShowTaskInDashboard($row, $dashboardType, $userId)) {
                    continue;
                }

                $slaConfig = is_array($row['_sla_config'] ?? null) ? $row['_sla_config'] : [];
                $tasks[] = [
                    'id' => (string)($row['id'] ?? ''),
                    'app_key' => $appKey,
                    'module_key' => $moduleKey,
                    'app_display_name' => (string)($module['display_name'] ?? self::displayName($appKey)),
                    'module_display_name' => (string)($module['module_key'] ?? ''),
                    'status' => self::normalizeGeneratedWorkflowStatus((string)($row['status'] ?? 'draft')),
                    'title' => (string)($row['title'] ?? $row['name'] ?? ''),
                    'description' => (string)($row['description'] ?? ''),
                    'created_at' => (string)($row['created_at'] ?? gmdate('c')),
                    'updated_at' => (string)($row['updated_at'] ?? gmdate('c')),
                    'time_in_state_seconds' => (int)($row['time_in_state_seconds'] ?? 0),
                    'time_in_state_label' => (string)($row['time_in_state_label'] ?? ''),
                    'max_time_in_state_seconds' => (int)($row['max_time_in_state_seconds'] ?? 0),
                    'is_overdue' => !empty($row['is_overdue']),
                    'is_delayed' => !empty($row['is_delayed']),
                    'history' => self::normalizeGeneratedWorkflowHistory($row['history'] ?? []),
                ];
            }
        }

        return $tasks;
    }

    /**
     * Determine if task should appear in dashboard based on role and user.
     */
    private static function shouldShowTaskInDashboard(array $row, string $dashboardType, string $userId): bool
    {
        $status = self::normalizeGeneratedWorkflowStatus((string)($row['status'] ?? 'draft'));

        // operator: only in_progress tasks
        if ($dashboardType === 'operator') {
            return $status === 'in_progress';
        }

        // qc: only completed and awaiting approval
        if ($dashboardType === 'qc') {
            return in_array($status, ['completed', 'approved'], true);
        }

        // dispatch: only approved and not yet dispatched
        if ($dashboardType === 'dispatch') {
            return in_array($status, ['approved', 'dispatched'], true);
        }

        // admin: all tasks
        return true;
    }

    /**
     * Compute KPI aggregates from task list.
     * @param array<int,array<string,mixed>> $tasks
     * @return array<string,int>
     */
    private static function computeDashboardKpis(array $tasks): array
    {
        $inProgress = 0;
        $overdue = 0;
        $nearSla = 0;

        foreach ($tasks as $task) {
            if ($task['status'] === 'in_progress') {
                $inProgress++;
            }
            if ($task['is_overdue']) {
                $overdue++;
            }
            if ($task['is_delayed'] && !$task['is_overdue']) {
                $nearSla++;
            }
        }

        return [
            'total_count' => count($tasks),
            'in_progress_count' => $inProgress,
            'overdue_count' => $overdue,
            'near_sla_count' => $nearSla,
            'on_track_count' => count($tasks) - $overdue - $nearSla,
        ];
    }

    /**
     * Sort and prioritize tasks: overdue > near SLA breach > normal.
     * @param array<int,array<string,mixed>> $tasks
     * @return array<int,array<string,mixed>>
     */
    private static function prioritizeDashboardTasks(array $tasks, int $nowTs): array
    {
        usort($tasks, static function (array $a, array $b) use ($nowTs): int {
            // Overdue tasks first
            if ($a['is_overdue'] && !$b['is_overdue']) {
                return -1;
            }
            if (!$a['is_overdue'] && $b['is_overdue']) {
                return 1;
            }

            // Near SLA breach next
            if ($a['is_delayed'] && !$b['is_delayed']) {
                return -1;
            }
            if (!$a['is_delayed'] && $b['is_delayed']) {
                return 1;
            }

            // Within same priority, sort by time in state (longest first)
            $aTimeInState = (int)($a['time_in_state_seconds'] ?? 0);
            $bTimeInState = (int)($b['time_in_state_seconds'] ?? 0);
            if ($aTimeInState !== $bTimeInState) {
                return $bTimeInState - $aTimeInState;
            }

            // Fallback: by creation time (oldest first)
            $aCreated = strtotime($a['created_at']) ?: $nowTs;
            $bCreated = strtotime($b['created_at']) ?: $nowTs;
            return $aCreated - $bCreated;
        });

        return $tasks;
    }

    /**
     * Enrich task with UI rendering context (actions, highlights, display hints).
     */
    private static function enrichDashboardTaskUI(array $task, string $dashboardType, int $nowTs): array
    {
        $task['dashboard_type'] = $dashboardType;
        $task['is_high_priority'] = $task['is_overdue'];
        $task['is_attention_needed'] = $task['is_delayed'];

        // Set visible actions based on role
        $task['visible_actions'] = [];
        $currentStatus = $task['status'];

        if ($dashboardType === 'operator' && $currentStatus === 'in_progress') {
            $task['visible_actions'] = ['mark_completed'];
        } elseif ($dashboardType === 'qc' && $currentStatus === 'completed') {
            $task['visible_actions'] = ['approve', 'show_history'];
        } elseif ($dashboardType === 'dispatch' && $currentStatus === 'approved') {
            $task['visible_actions'] = ['dispatch', 'show_history'];
        } elseif ($dashboardType === 'admin') {
            $task['visible_actions'] = ['mark_in_progress', 'mark_completed', 'approve', 'dispatch', 'show_history'];
        }

        // Add delay badge/highlight class
        $task['delay_indicator'] = 'on_track';
        if ($task['is_overdue']) {
            $task['delay_indicator'] = 'overdue';
        } elseif ($task['is_delayed']) {
            $task['delay_indicator'] = 'near_breach';
        }

        return $task;
    }

    /**
     * Compute the next best action for a user based on blocking relationships and SLA.
     * @param string $dashboardType operator|qc|dispatch|admin
     * @param array<int,array<string,mixed>> $tasks Current task list
     * @return array<string,mixed>
     */
    public static function computeNextBestAction(string $dashboardType, array $tasks): array
    {
        if (count($tasks) === 0) {
            return [
                'has_next_action' => false,
                'next_action' => null,
                'reason' => 'no_tasks',
                'explanation' => 'No tasks requiring your attention.',
                'blocked_downstream_count' => 0,
            ];
        }

        // Priority 1: Overdue tasks
        foreach ($tasks as $task) {
            if ($task['is_overdue']) {
                $blockedCount = self::countBlockedDownstreamTasks($task, $tasks, $dashboardType);
                return [
                    'has_next_action' => true,
                    'next_action' => $task,
                    'reason' => 'overdue',
                    'explanation' => 'This task is overdue and requires immediate attention.',
                    'blocked_downstream_count' => $blockedCount,
                    'blocking_explanation' => $blockedCount > 0
                        ? 'Completing this task will unblock ' . $blockedCount . ' downstream task' . ($blockedCount !== 1 ? 's' : '') . '.'
                        : null,
                ];
            }
        }

        // Priority 2: Blocking tasks (tasks that unblock downstream work)
        foreach ($tasks as $task) {
            $blockedCount = self::countBlockedDownstreamTasks($task, $tasks, $dashboardType);
            if ($blockedCount > 0) {
                return [
                    'has_next_action' => true,
                    'next_action' => $task,
                    'reason' => 'blocking_others',
                    'explanation' => 'This task is blocking ' . $blockedCount . ' downstream task' . ($blockedCount !== 1 ? 's' : '') . '.',
                    'blocked_downstream_count' => $blockedCount,
                    'blocking_explanation' => 'Completing this task will unblock ' . $blockedCount . ' downstream task' . ($blockedCount !== 1 ? 's' : '') . '.',
                ];
            }
        }

        // Priority 3: Near SLA breach tasks
        foreach ($tasks as $task) {
            if ($task['is_delayed']) {
                $blockedCount = self::countBlockedDownstreamTasks($task, $tasks, $dashboardType);
                return [
                    'has_next_action' => true,
                    'next_action' => $task,
                    'reason' => 'near_sla_breach',
                    'explanation' => 'This task is approaching SLA breach and should be prioritized.',
                    'blocked_downstream_count' => $blockedCount,
                    'blocking_explanation' => $blockedCount > 0
                        ? 'Completing this task will unblock ' . $blockedCount . ' downstream task' . ($blockedCount !== 1 ? 's' : '') . '.'
                        : null,
                ];
            }
        }

        // Priority 4: First task in normal priority (oldest created)
        if (count($tasks) > 0) {
            $nextTask = $tasks[0];
            $blockedCount = self::countBlockedDownstreamTasks($nextTask, $tasks, $dashboardType);
            return [
                'has_next_action' => true,
                'next_action' => $nextTask,
                'reason' => 'normal_priority',
                'explanation' => 'This is the next task in your queue.',
                'blocked_downstream_count' => $blockedCount,
                'blocking_explanation' => $blockedCount > 0
                    ? 'Completing this task will unblock ' . $blockedCount . ' downstream task' . ($blockedCount !== 1 ? 's' : '') . '.'
                    : null,
            ];
        }

        return [
            'has_next_action' => false,
            'next_action' => null,
            'reason' => 'no_suitable_task',
            'explanation' => 'No suitable next action found.',
            'blocked_downstream_count' => 0,
        ];
    }

    /**
     * Count downstream tasks blocked by completion of this task.
     * @param array<string,mixed> $task
     * @param array<int,array<string,mixed>> $allTasks
     * @return int
     */
    private static function countBlockedDownstreamTasks(array $task, array $allTasks, string $dashboardType): int
    {
        $taskStatus = $task['status'] ?? 'draft';
        $blockedCount = 0;

        // Define blocking relationships: QC blocks Dispatch, operator blocks QC, etc.
        $blockingMap = self::taskBlockingRelationships();

        // Get tasks that are blocked by this status transition
        foreach ($allTasks as $downstreamTask) {
            $downstreamStatus = $downstreamTask['status'] ?? 'draft';

            // Check if completing this task would unblock the downstream task
            foreach ($blockingMap as $blockRule) {
                $blockerStatus = $blockRule['blocker_status'];
                $blockedStatus = $blockRule['blocked_status'];
                $unblocksTo = $blockRule['unblocks_to'];

                if ($taskStatus === $blockerStatus && $downstreamStatus === $blockedStatus) {
                    // This task blocks the downstream task
                    if ((string)($task['id'] ?? '') !== (string)($downstreamTask['id'] ?? '')) {
                        $blockedCount++;
                    }
                }
            }
        }

        return $blockedCount;
    }

    /**
     * Define blocking relationships between task statuses.
     * Simple v1: QC blocks dispatch, production blocks QC
     * @return array<int,array<string,string>>
     */
    private static function taskBlockingRelationships(): array
    {
        return [
            // in_progress → completed blocks QC from approving
            ['blocker_status' => 'in_progress', 'blocked_status' => 'completed', 'unblocks_to' => 'completed'],
            // completed → approved blocks dispatch from dispatching
            ['blocker_status' => 'completed', 'blocked_status' => 'approved', 'unblocks_to' => 'approved'],
            // in_progress blocks completed
            ['blocker_status' => 'in_progress', 'blocked_status' => 'draft', 'unblocks_to' => 'in_progress'],
        ];
    }

    /**
     * Generate explanation text for why a task is the next best action.
     * @param array<string,mixed> $nextAction
     * @return string
     */
    public static function computeTaskExplanation(array $nextAction): string
    {
        $reason = $nextAction['reason'] ?? 'normal_priority';
        $blockedCount = $nextAction['blocked_downstream_count'] ?? 0;
        $title = $nextAction['title'] ?? 'Untitled Task';

        $baseExplanation = match ($reason) {
            'overdue' => 'This task has exceeded its SLA deadline.',
            'blocking_others' => 'This task is blocking ' . $blockedCount . ' downstream operation' . ($blockedCount !== 1 ? 's' : '') . '.',
            'near_sla_breach' => 'This task is approaching its SLA deadline.',
            default => 'This is your next task in the queue.',
        };

        $unblocksExplanation = '';
        if ($blockedCount > 0) {
            $unblocksExplanation = ' Completing it will unblock ' . $blockedCount . ' downstream task' . ($blockedCount !== 1 ? 's' : '') . '.';
        }

        return $baseExplanation . $unblocksExplanation;
    }

    /**
     * Map user account type to dashboard type.
     * Used to determine which dashboard interface a user sees.
     *
     * @param string $accountType The user's account type (platform_admin, app_admin, operator, etc.)
     * @return string The dashboard type (admin, operator, qc, dispatch, etc.)
     */
    public static function mapUserRoleToDashboardType(string $accountType): string
    {
        $mapping = [
            'platform_admin' => 'admin',
            'app_admin' => 'admin',
            'operator' => 'operator',
            'qc_inspector' => 'qc',
            'dispatch_manager' => 'dispatch',
        ];
        return $mapping[strtolower($accountType)] ?? 'operator';
    }
}

<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

final class StudioViewIntrospectionService
{
    /**
     * @param array<string,mixed> $node
     * @return array<string,mixed>
     */
    public static function importLibraryNode(array $node): array
    {
        $nodeType = strtolower(trim((string)($node['type'] ?? '')));
        $nodeMeta = is_array($node['meta'] ?? null) ? $node['meta'] : [];
        $source = strtolower(trim((string)($nodeMeta['source'] ?? '')));

        if (!in_array($nodeType, ['module', 'view', 'dashboard', 'app'], true)) {
            return [
                'ok' => false,
                'code' => 'unsupported_library_node',
                'message' => 'unsupported_library_node',
                'bundle' => null,
                'source_detection' => [],
                'selected_source' => '',
                'partial_import' => false,
                'import_model' => [],
            ];
        }

        // App-level import: load manifest.json and build an app-only bundle.
        if ($nodeType === 'app') {
            return self::importAppNode($node);
        }

        // Keep generated modules on the existing import path.
        if ($source === 'generated') {
            $derived = self::deriveGeneratedKeysFromNode($node);
            if ($derived['app_key'] !== '' && $derived['module_key'] !== '') {
                return self::importExistingView($derived['app_key'], $derived['module_key'], $derived['view_key']);
            }
        }

        $derived = self::deriveNodeKeys($node);
        $routeData = self::extractRouteData($derived['module_path'], $derived['app_path']);
        $navigationData = self::extractNavigationData($derived['module_path'], $derived['app_path'], $routeData['route_path']);
        $layoutModel = self::extractLayoutModel($derived['view_path']);
        $fields = self::extractFieldsFromViewFile($derived['view_path']);
        if ($fields === []) {
            $fields = ['name', 'status'];
        }

        $templates = GuiStudioService::loadTemplates();
        $appManifest = self::decodeTemplateJson($templates['app_manifest'] ?? '');
        $moduleManifest = self::decodeTemplateJson($templates['module_manifest'] ?? '');
        $viewDefinition = self::decodeTemplateJson($templates['view_definition'] ?? '');
        $navigationDefinition = self::decodeTemplateJson($templates['navigation_definition'] ?? '');

        $sourceAppKey = $derived['app_key'] !== '' ? $derived['app_key'] : 'imported_app';
        $sourceModuleKey = $derived['module_key'] !== '' ? $derived['module_key'] : 'imported_module';
        $appKey = self::safeKey($sourceAppKey . '_studio');
        $moduleKey = self::safeKey($sourceModuleKey . '_studio');
        if ($appKey === '') {
            $appKey = 'imported_app_studio';
        }
        if ($moduleKey === '') {
            $moduleKey = 'imported_module_studio';
        }
        $viewKey = $derived['view_key'] !== '' ? $derived['view_key'] : 'index';
        $sourceRoutePath = $routeData['route_path'] !== ''
            ? $routeData['route_path']
            : '/apps/' . str_replace('_', '-', $sourceAppKey) . '/' . str_replace('_', '-', $sourceModuleKey);
        $routePath = '/apps/' . str_replace('_', '-', $appKey) . '/' . str_replace('_', '-', $moduleKey);

        $moduleType = 'crud';
        if ($nodeType === 'dashboard' || in_array('kpi_card', $layoutModel['components'], true)) {
            $moduleType = 'dashboard';
        }

        $appManifest['app'] = is_array($appManifest['app'] ?? null) ? $appManifest['app'] : [];
        $appManifest['app']['app_key'] = $appKey;
        $appManifest['app']['display_name'] = self::displayName($sourceAppKey) . ' Studio';
        $appManifest['app']['description'] = 'studio.' . $appKey . '.description';
        $appManifest['app']['route_prefix'] = self::routePrefixForPath($routePath, $appKey);
        $appManifest['app']['default_landing_page'] = $routePath;
        $appManifest['i18n'] = is_array($appManifest['i18n'] ?? null) ? $appManifest['i18n'] : [];
        $appManifest['i18n']['title_key'] = 'studio.' . $appKey . '.title';
        $appManifest['i18n']['description_key'] = 'studio.' . $appKey . '.description';

        $moduleManifest['module'] = is_array($moduleManifest['module'] ?? null) ? $moduleManifest['module'] : [];
        $moduleManifest['module']['app_key'] = $appKey;
        $moduleManifest['module']['module_key'] = $moduleKey;
        $moduleManifest['module']['display_name'] = self::displayName($moduleKey);
        $moduleManifest['module']['description'] = 'studio.' . $moduleKey . '.description';
        $moduleManifest['module']['module_type'] = $moduleType;
        $moduleManifest['module']['route_base'] = $routePath;
        $moduleManifest['fields'] = array_map(static function (string $fieldKey, int $index): array {
            return [
                'key' => $fieldKey,
                'type' => 'string',
                'required' => $index === 0,
                'label_key' => 'studio.fields.' . $fieldKey,
            ];
        }, $fields, array_keys($fields));

        $viewDefinition['view'] = is_array($viewDefinition['view'] ?? null) ? $viewDefinition['view'] : [];
        $viewDefinition['view']['app_key'] = $appKey;
        $viewDefinition['view']['module_key'] = $moduleKey;
        $viewDefinition['view']['view_key'] = $viewKey;
        $viewDefinition['view']['view_kind'] = $layoutModel['view_kind'];
        $viewDefinition['view']['route_path'] = $routePath;
        $viewDefinition['view']['title_key'] = 'studio.' . $moduleKey . '.' . $viewKey . '.title';
        $viewDefinition['view']['description_key'] = 'studio.' . $moduleKey . '.' . $viewKey . '.description';
        $viewDefinition['view']['fields'] = $fields;
        $viewDefinition['layout'] = [
            'kind' => $layoutModel['view_kind'],
            'type' => 'grid',
            'columns' => 12,
            'rows' => 'auto',
            'items' => $layoutModel['items'],
            'relations' => [],
            'uses_shared_tokens' => true,
            'local_style_system' => false,
        ];
        $viewDefinition['component_bindings'] = $layoutModel['bindings'];
        $viewDefinition['data_contract'] = is_array($viewDefinition['data_contract'] ?? null) ? $viewDefinition['data_contract'] : [];
        $viewDefinition['data_contract']['allowed_filters'] = array_slice($fields, 0, 2);
        $viewDefinition['data_contract']['required_fields'] = [$fields[0]];

        $navigationDefinition['navigation'] = is_array($navigationDefinition['navigation'] ?? null) ? $navigationDefinition['navigation'] : [];
        $navigationDefinition['navigation']['owner_app'] = $appKey;
        $navigationDefinition['navigation']['key'] = $appKey . '.' . $moduleKey;
        $navigationDefinition['navigation']['label_key'] = $navigationData['label'] !== '' ? $navigationData['label'] : ('studio.' . $moduleKey . '.nav');
        $navigationDefinition['navigation']['url'] = $navigationData['url'];
        $navigationDefinition['navigation']['icon'] = $navigationData['icon'];
        $navigationDefinition['navigation']['order'] = $navigationData['order'];
        $navigationDefinition['navigation']['visible_if'] = $navigationData['visible_if'];
        $navigationDefinition['navigation']['section'] = $navigationData['section'];
        $navigationDefinition['navigation']['scope'] = 'admin';
        $navigationDefinition['active_patterns'] = is_array($navigationDefinition['active_patterns'] ?? null) ? $navigationDefinition['active_patterns'] : [];
        $navigationDefinition['active_patterns']['exact'] = $navigationData['url'] !== '' ? [$navigationData['url']] : [];
        $navigationDefinition['active_patterns']['prefix'] = $navigationData['url'] !== '' ? [$navigationData['url']] : [];

        $compositionContract = self::buildViewCompositionContract([
            'view_key' => $viewKey,
            'display_label' => self::displayName($viewKey),
            'owning_app' => $sourceAppKey,
            'owning_module' => $sourceModuleKey,
            'route_path' => $sourceRoutePath,
            'route_key' => '',
            'route_file' => (string)($routeData['route_file'] ?? ''),
            'route_found' => !empty($routeData['found']),
            'nav_key' => $navigationData['key'],
            'nav_label' => $navigationData['label'],
            'nav_icon' => $navigationData['icon'],
            'nav_order' => $navigationData['order'],
            'nav_target_route' => $navigationData['url'],
            'nav_target_view' => '',
            'nav_file' => $navigationData['nav_file'],
            'nav_found' => !empty($navigationData['found']),
            'visibility' => $navigationData['visible_if'] !== '' ? $navigationData['visible_if'] : 'always',
            'source_kind' => 'library_existing_system',
        ]);

        $bundle = [
            'app_manifest' => $appManifest,
            'module_manifest' => $moduleManifest,
            'view_definition' => $viewDefinition,
            'navigation_definition' => $navigationDefinition,
            'metadata' => [
                'import' => [
                    'source' => 'library_existing_system',
                    'partial_import' => !empty($layoutModel['partial']),
                    'node_id' => (string)($node['id'] ?? ''),
                    'node_type' => $nodeType,
                    'source_app_key' => $sourceAppKey,
                    'source_module_key' => $sourceModuleKey,
                    'source_route_path' => $sourceRoutePath,
                    'source_route_file' => (string)($routeData['route_file'] ?? ''),
                    'source_navigation_file' => $navigationData['nav_file'],
                    'composition' => $compositionContract,
                ],
            ],
        ];

        return [
            'ok' => true,
            'code' => 'import_ready',
            'message' => 'import_ready',
            'bundle' => $bundle,
            'source_detection' => [
                [
                    'source' => 'library_node',
                    'available' => true,
                    'evidence' => (string)($node['id'] ?? ''),
                ],
                [
                    'source' => 'view_file_layout',
                    'available' => !empty($layoutModel['items']),
                    'evidence' => $derived['view_path'] !== '' ? $derived['view_path'] : 'fallback_layout',
                ],
                [
                    'source' => 'route_navigation',
                    'available' => true,
                    'evidence' => $navigationData['url'],
                ],
            ],
            'selected_source' => 'library_existing_system',
            'partial_import' => !empty($layoutModel['partial']),
            'import_model' => [
                'layout' => $viewDefinition['layout'],
                'bindings' => $viewDefinition['component_bindings'],
                'route' => $routeData,
                'navigation' => $navigationData,
                'composition' => $compositionContract,
            ],
        ];
    }

    /**
     * Import an app-type library node, reading the app's manifest.json if available.
     *
     * @param array<string,mixed> $node
     * @return array<string,mixed>
     */
    private static function importAppNode(array $node): array
    {
        $meta = is_array($node['meta'] ?? null) ? $node['meta'] : [];
        $relPath = trim((string)($meta['path'] ?? ''));
        $absPath = $relPath !== '' ? self::absolutePath($relPath) : '';

        $foundOnDisk = false;
        $rawManifestData = [];
        if ($absPath !== '' && is_dir($absPath)) {
            $manifestFile = rtrim($absPath, '/\\') . '/manifest.json';
            if (is_readable($manifestFile)) {
                $decoded = json_decode((string)file_get_contents($manifestFile), true);
                if (is_array($decoded)) {
                    $rawManifestData = $decoded;
                    $foundOnDisk = true;
                }
            }
        }

        // Extract the real app key from the node id ('app:apps:manufacturing' → 'manufacturing')
        $nodeIdRaw = strtolower(trim((string)($node['id'] ?? '')));
        $idParts = explode(':', $nodeIdRaw);
        $realAppKey = (string)(end($idParts) ?: '');
        if ($realAppKey === '') {
            $realAppKey = self::safeKey((string)($node['label'] ?? 'app'));
        }

        // Build the app entry in studio format: { app_key, display_name, type }
        $appInner = [
            'app_key'      => (string)($rawManifestData['app_key'] ?? $realAppKey),
            'display_name' => (string)($rawManifestData['name'] ?? $rawManifestData['display_name'] ?? (string)($node['label'] ?? $realAppKey)),
            'type'         => (string)($rawManifestData['type'] ?? 'business'),
        ];

        // Wrap in studio app-manifest envelope: { schema_version, app: {...}, version }
        $appManifestBundle = [
            'schema_version' => 'studio.app-manifest.v1',
            'status'         => 'loaded',
            'app'            => $appInner,
            'version'        => (string)($rawManifestData['version'] ?? '1.0.0'),
        ];

        $bundle = [
            'app_manifest'          => $appManifestBundle,
            'module_manifest'       => (object)[],
            'view_definition'       => (object)[],
            'navigation_definition' => (object)[],
        ];

        return [
            'ok' => true,
            'code' => 'app_loaded',
            'message' => 'app_loaded',
            'bundle' => $bundle,
            'source_detection' => [
                'type' => 'app',
                'source' => (string)($meta['source'] ?? 'apps'),
                'app_key' => $appInner['app_key'],
            ],
            'selected_source' => 'library',
            'partial_import' => !$foundOnDisk,
            'import_model' => ['type' => 'app'],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function importExistingView(string $appKey, string $moduleKey, string $viewKey = 'index'): array
    {
        $safeAppKey = self::safeKey($appKey);
        $safeModuleKey = self::safeKey($moduleKey);
        $safeViewKey = self::safeKey($viewKey);
        if ($safeAppKey === '' || $safeModuleKey === '') {
            return [
                'ok' => false,
                'code' => 'invalid_module_reference',
                'message' => 'invalid_module_reference',
                'bundle' => null,
                'source_detection' => [],
                'selected_source' => '',
                'partial_import' => false,
                'import_model' => [],
            ];
        }
        if ($safeViewKey === '') {
            $safeViewKey = 'index';
        }

        $bundle = GuiStudioService::loadGeneratedModuleDefinition($safeAppKey, $safeModuleKey);
        if (!is_array($bundle)) {
            return [
                'ok' => false,
                'code' => 'generated_module_not_found',
                'message' => 'generated_module_not_found',
                'bundle' => null,
                'source_detection' => [],
                'selected_source' => '',
                'partial_import' => false,
                'import_model' => [],
            ];
        }

        $manifestSource = self::loadManifestSource($safeAppKey, $safeModuleKey);
        $viewDefinitionSource = self::extractViewDefinitionSource($manifestSource, $bundle);
        $runtimeSource = self::loadRuntimeDashboardSource($safeAppKey, $safeModuleKey);

        $sourceDetection = [
            [
                'source' => 'generated_module_manifest',
                'available' => true,
                'evidence' => 'manifest.json + module.json',
            ],
            [
                'source' => 'view_definition',
                'available' => !empty($viewDefinitionSource),
                'evidence' => !empty($viewDefinitionSource) ? 'view_definition.layout/components' : 'missing_or_unstructured',
            ],
            [
                'source' => 'runtime_dashboard_structure',
                'available' => !empty($runtimeSource),
                'evidence' => !empty($runtimeSource) ? 'generatedRuntimeContract' : 'runtime_contract_unavailable',
            ],
        ];

        $selectedSource = 'generated_module_manifest';
        $partialImport = false;

        if (!empty($viewDefinitionSource)) {
            $selectedSource = 'view_definition';
            $importModel = self::reconstructFromViewDefinition($viewDefinitionSource);
            $partialImport = false;
        } elseif (!empty($runtimeSource)) {
            $selectedSource = 'runtime_dashboard_structure';
            $importModel = self::reconstructFromRuntime($runtimeSource);
            $partialImport = true;
        } else {
            $selectedSource = 'generated_module_manifest';
            $importModel = self::reconstructFromFallback($bundle);
            $partialImport = true;
        }

        $enrichedBundle = self::applyImportModelToBundle($bundle, $importModel, $selectedSource, $partialImport, $safeViewKey);

        return [
            'ok' => true,
            'code' => 'import_ready',
            'message' => 'import_ready',
            'bundle' => $enrichedBundle,
            'source_detection' => $sourceDetection,
            'selected_source' => $selectedSource,
            'partial_import' => $partialImport,
            'import_model' => array_merge($importModel, [
                'composition' => is_array($enrichedBundle['metadata']['import']['composition'] ?? null)
                    ? $enrichedBundle['metadata']['import']['composition']
                    : [],
            ]),
        ];
    }

    /** @return array<string,mixed> */
    private static function loadManifestSource(string $appKey, string $moduleKey): array
    {
        $basePath = self::generatedModuleBasePath($appKey, $moduleKey);
        if ($basePath === '') {
            return [];
        }

        return [
            'manifest' => self::readJsonFile($basePath . '/manifest.json'),
            'module' => self::readJsonFile($basePath . '/module.json'),
        ];
    }

    /** @return array<string,mixed> */
    private static function loadRuntimeDashboardSource(string $appKey, string $moduleKey): array
    {
        foreach (GuiStudioService::generatedModuleDefinitions(false) as $definition) {
            if (!is_array($definition)) {
                continue;
            }
            if ((string)($definition['app_key'] ?? '') !== $appKey || (string)($definition['module_key'] ?? '') !== $moduleKey) {
                continue;
            }

            $runtime = GuiStudioService::generatedModuleRuntimeData($definition, []);
            $contract = GuiStudioService::generatedRuntimeContract($definition, $runtime);
            $layout = is_array($contract['layout'] ?? null) ? $contract['layout'] : [];
            if ($layout === []) {
                return [];
            }

            return [
                'layout' => $layout,
                'workflow' => is_array($contract['workflow'] ?? null) ? $contract['workflow'] : [],
                'runtime' => $runtime,
            ];
        }

        return [];
    }

    /** @return array<string,mixed> */
    private static function extractViewDefinitionSource(array $manifestSource, array $bundle): array
    {
        $bundleView = is_array($bundle['view_definition'] ?? null) ? $bundle['view_definition'] : [];
        if (self::hasStructuredViewDefinition($bundleView)) {
            return $bundleView;
        }

        $manifestView = $manifestSource['manifest']['view_definition'] ?? null;
        if (is_string($manifestView)) {
            $decoded = json_decode($manifestView, true);
            if (is_array($decoded) && self::hasStructuredViewDefinition($decoded)) {
                return $decoded;
            }
        } elseif (is_array($manifestView) && self::hasStructuredViewDefinition($manifestView)) {
            return $manifestView;
        }

        $moduleView = $manifestSource['module']['view_definition'] ?? null;
        if (is_string($moduleView)) {
            $decoded = json_decode($moduleView, true);
            if (is_array($decoded) && self::hasStructuredViewDefinition($decoded)) {
                return $decoded;
            }
        } elseif (is_array($moduleView) && self::hasStructuredViewDefinition($moduleView)) {
            return $moduleView;
        }

        return [];
    }

    /** @return array{layout:array<string,mixed>,components:array<int,array<string,mixed>>,bindings:array<string,string>,relations:array<int,array<string,string>>} */
    private static function reconstructFromViewDefinition(array $viewDefinition): array
    {
        $layout = is_array($viewDefinition['layout'] ?? null) ? $viewDefinition['layout'] : [];
        $items = is_array($layout['items'] ?? null) ? array_values(array_filter($layout['items'], 'is_array')) : [];
        $relations = is_array($layout['relations'] ?? null) ? array_values(array_filter($layout['relations'], 'is_array')) : [];
        $bindings = [];

        $bindingRows = is_array($viewDefinition['component_bindings'] ?? null)
            ? array_values(array_filter($viewDefinition['component_bindings'], 'is_array'))
            : [];
        foreach ($bindingRows as $binding) {
            $itemId = trim((string)($binding['item_id'] ?? ''));
            $bindingPath = trim((string)($binding['data_binding'] ?? ''));
            if ($itemId !== '' && $bindingPath !== '') {
                $bindings[$itemId] = $bindingPath;
            }
        }

        $components = [];
        foreach ($items as $item) {
            $itemId = trim((string)($item['id'] ?? ''));
            if ($itemId === '') {
                continue;
            }
            $components[] = [
                'id' => $itemId,
                'type' => (string)($item['component'] ?? 'text_block'),
                'group' => (string)($item['group'] ?? 'data'),
                'props' => is_array($item['props'] ?? null) ? $item['props'] : [],
            ];

            if (!isset($bindings[$itemId])) {
                $fallbackBinding = trim((string)($item['data_binding'] ?? ''));
                if ($fallbackBinding !== '') {
                    $bindings[$itemId] = $fallbackBinding;
                }
            }
        }

        $normalizedLayout = [
            'type' => (string)($layout['type'] ?? 'grid'),
            'columns' => (int)($layout['columns'] ?? 12),
            'rows' => (string)($layout['rows'] ?? 'auto'),
            'items' => $items,
            'relations' => $relations,
        ];

        return [
            'layout' => $normalizedLayout,
            'components' => $components,
            'bindings' => $bindings,
            'relations' => array_map(static function (array $row): array {
                return [
                    'source_id' => (string)($row['source_id'] ?? ''),
                    'target_id' => (string)($row['target_id'] ?? ''),
                    'type' => (string)($row['type'] ?? ''),
                ];
            }, $relations),
        ];
    }

    /** @return array{layout:array<string,mixed>,components:array<int,array<string,mixed>>,bindings:array<string,string>,relations:array<int,array<string,string>>} */
    private static function reconstructFromRuntime(array $runtimeSource): array
    {
        $layout = is_array($runtimeSource['layout'] ?? null) ? $runtimeSource['layout'] : [];
        $items = is_array($layout['items'] ?? null) ? array_values(array_filter($layout['items'], 'is_array')) : [];
        $relations = is_array($layout['relations'] ?? null) ? array_values(array_filter($layout['relations'], 'is_array')) : [];

        $components = [];
        $bindings = [];
        foreach ($items as $item) {
            $itemId = trim((string)($item['id'] ?? ''));
            if ($itemId === '') {
                continue;
            }
            $components[] = [
                'id' => $itemId,
                'type' => (string)($item['component'] ?? 'text_block'),
                'group' => (string)($item['group'] ?? 'data'),
                'props' => is_array($item['props'] ?? null) ? $item['props'] : [],
            ];
            $bindingPath = trim((string)($item['data_binding'] ?? ''));
            if ($bindingPath !== '') {
                $bindings[$itemId] = $bindingPath;
            }
        }

        return [
            'layout' => [
                'type' => (string)($layout['type'] ?? 'grid'),
                'columns' => (int)($layout['columns'] ?? 12),
                'rows' => (string)($layout['rows'] ?? 'auto'),
                'items' => $items,
                'relations' => $relations,
            ],
            'components' => $components,
            'bindings' => $bindings,
            'relations' => array_map(static function (array $row): array {
                return [
                    'source_id' => (string)($row['source_id'] ?? ''),
                    'target_id' => (string)($row['target_id'] ?? ''),
                    'type' => (string)($row['type'] ?? ''),
                ];
            }, $relations),
        ];
    }

    /** @return array{layout:array<string,mixed>,components:array<int,array<string,mixed>>,bindings:array<string,string>,relations:array<int,array<string,string>>} */
    private static function reconstructFromFallback(array $bundle): array
    {
        $fields = is_array($bundle['module_manifest']['fields'] ?? null)
            ? array_values(array_filter($bundle['module_manifest']['fields'], 'is_array'))
            : [];
        $module = is_array($bundle['module_manifest']['module'] ?? null) ? $bundle['module_manifest']['module'] : [];
        $displayName = (string)($module['display_name'] ?? 'Data');

        $items = [
            [
                'id' => 'table_main',
                'component' => 'table',
                'x' => 0,
                'y' => 0,
                'w' => 12,
                'h' => 8,
                'group' => 'data',
                'props' => [
                    'title' => $displayName,
                    'sample_rows' => 8,
                    'density' => 'comfortable',
                ],
                'data_binding' => 'module.rows',
            ],
        ];

        if ($fields !== []) {
            $primaryField = (string)($fields[0]['key'] ?? '');
            $items[] = [
                'id' => 'filter_1',
                'component' => 'filter',
                'x' => 0,
                'y' => 8,
                'w' => 4,
                'h' => 3,
                'group' => 'controls',
                'props' => [
                    'label' => 'Filter',
                    'field' => $primaryField,
                    'placeholder' => 'Type to filter',
                ],
                'data_binding' => 'module.rows',
            ];
        }

        $components = [];
        $bindings = [];
        foreach ($items as $item) {
            $itemId = (string)$item['id'];
            $components[] = [
                'id' => $itemId,
                'type' => (string)$item['component'],
                'group' => (string)$item['group'],
                'props' => is_array($item['props'] ?? null) ? $item['props'] : [],
            ];
            $bindings[$itemId] = (string)($item['data_binding'] ?? 'module.rows');
        }

        return [
            'layout' => [
                'type' => 'grid',
                'columns' => 12,
                'rows' => 'auto',
                'items' => $items,
                'relations' => [],
            ],
            'components' => $components,
            'bindings' => $bindings,
            'relations' => [],
        ];
    }

    /** @return array<string,mixed> */
    private static function buildViewCompositionContract(array $seed): array
    {
        $viewKey = trim((string)($seed['view_key'] ?? ''));
        $displayLabel = trim((string)($seed['display_label'] ?? ''));
        $owningApp = trim((string)($seed['owning_app'] ?? ''));
        $owningModule = trim((string)($seed['owning_module'] ?? ''));
        $routePath = trim((string)($seed['route_path'] ?? ''));
        $routeKey = trim((string)($seed['route_key'] ?? ''));
        $routeFile = trim((string)($seed['route_file'] ?? ''));
        $routeFound = !empty($seed['route_found']);

        $navKey = trim((string)($seed['nav_key'] ?? ''));
        $navLabel = trim((string)($seed['nav_label'] ?? ''));
        $navIcon = trim((string)($seed['nav_icon'] ?? ''));
        $navOrder = (int)($seed['nav_order'] ?? 10);
        $navTargetRoute = trim((string)($seed['nav_target_route'] ?? ''));
        $navTargetView = trim((string)($seed['nav_target_view'] ?? ''));
        $navFile = trim((string)($seed['nav_file'] ?? ''));
        $navFound = !empty($seed['nav_found']);
        $visibility = trim((string)($seed['visibility'] ?? 'always'));
        $sourceKind = trim((string)($seed['source_kind'] ?? 'library'));

        $diagnostics = [];
        $relationshipState = 'connected';
        if ($routePath === '') {
            $relationshipState = 'missing_route';
            $diagnostics[] = 'missing_route';
        }

        if (!$navFound || ($navTargetRoute === '' && $navTargetView === '')) {
            if ($relationshipState === 'connected') {
                $relationshipState = 'missing_nav';
            }
            $diagnostics[] = 'missing_nav';
        }

        if ($routePath !== '' && $navTargetRoute !== '' && $navTargetRoute !== $routePath) {
            $relationshipState = 'ambiguous_conflicting';
            $diagnostics[] = 'ambiguous_conflicting';
        }

        if ($routePath === '' && $navTargetRoute !== '') {
            $relationshipState = 'nav_unknown_route';
            $diagnostics[] = 'nav_unknown_route';
        }

        if ($diagnostics === []) {
            $diagnostics[] = 'connected';
        }

        return [
            'version' => 'studio.composition.v1',
            'artifact_kind' => 'view',
            'view_exposure' => [
                'view_key' => $viewKey,
                'display_label' => $displayLabel,
                'owning_app' => $owningApp,
                'owning_module' => $owningModule,
                'route_path' => $routePath,
                'route_key' => $routeKey,
            ],
            'navigation_exposure' => [
                'nav_key' => $navKey,
                'label' => $navLabel,
                'icon' => $navIcon,
                'order' => $navOrder,
                'target_route' => $navTargetRoute,
                'target_view' => $navTargetView,
                'source_app' => $owningApp,
                'source_module' => $owningModule,
                'visibility' => $visibility,
            ],
            'relationship_state' => [
                'state' => $relationshipState,
                'route_status' => $routePath !== '' ? 'connected' : 'missing_route',
                'navigation_status' => ($navFound && ($navTargetRoute !== '' || $navTargetView !== '')) ? 'connected' : 'missing_nav',
                'inspect_only' => true,
                'diagnostics' => array_values(array_unique($diagnostics)),
            ],
            'source' => [
                'kind' => $sourceKind,
                'route_file' => $routeFile,
                'navigation_file' => $navFile,
                'route_found' => $routeFound,
                'navigation_found' => $navFound,
            ],
        ];
    }

    /** @return array<string,mixed> */
    private static function applyImportModelToBundle(array $bundle, array $importModel, string $selectedSource, bool $partialImport, string $viewKey): array
    {
        $viewDefinition = is_array($bundle['view_definition'] ?? null) ? $bundle['view_definition'] : [];
        $view = is_array($viewDefinition['view'] ?? null) ? $viewDefinition['view'] : [];

        $layout = is_array($importModel['layout'] ?? null) ? $importModel['layout'] : [];
        $items = is_array($layout['items'] ?? null) ? array_values(array_filter($layout['items'], 'is_array')) : [];
        $relations = is_array($layout['relations'] ?? null) ? array_values(array_filter($layout['relations'], 'is_array')) : [];
        $bindings = is_array($importModel['bindings'] ?? null) ? $importModel['bindings'] : [];

        $componentBindingRows = [];
        foreach ($bindings as $itemId => $bindingPath) {
            $safeItemId = trim((string)$itemId);
            $safeBindingPath = trim((string)$bindingPath);
            if ($safeItemId === '' || $safeBindingPath === '') {
                continue;
            }
            $componentBindingRows[] = [
                'item_id' => $safeItemId,
                'component' => self::componentForItem($items, $safeItemId),
                'data_binding' => $safeBindingPath,
            ];
        }

        $view['view_key'] = trim((string)($view['view_key'] ?? '')) !== '' ? (string)$view['view_key'] : $viewKey;
        $view['view_kind'] = trim((string)($view['view_kind'] ?? '')) !== '' ? (string)$view['view_kind'] : 'dashboard';
        $viewDefinition['view'] = $view;
        $viewDefinition['layout'] = [
            'kind' => (string)($view['view_kind'] ?? 'dashboard'),
            'type' => (string)($layout['type'] ?? 'grid'),
            'columns' => (int)($layout['columns'] ?? 12),
            'rows' => (string)($layout['rows'] ?? 'auto'),
            'items' => $items,
            'relations' => $relations,
            'uses_shared_tokens' => true,
            'local_style_system' => false,
        ];
        $viewDefinition['component_registry'] = [
            'version' => 'visual-builder.v1',
            'components' => array_values(array_unique(array_map(static function (array $item): string {
                return (string)($item['component'] ?? 'text_block');
            }, $items))),
        ];
        $viewDefinition['component_bindings'] = $componentBindingRows;
        $viewDefinition['import_meta'] = [
            'source' => $selectedSource,
            'partial_import' => $partialImport,
        ];

        $bundle['view_definition'] = $viewDefinition;
        $bundle['metadata'] = is_array($bundle['metadata'] ?? null) ? $bundle['metadata'] : [];
        $existingImportMeta = is_array($bundle['metadata']['import'] ?? null) ? $bundle['metadata']['import'] : [];
        $bundle['metadata']['import'] = [
            'source' => $selectedSource,
            'partial_import' => $partialImport,
        ];
        if (is_array($existingImportMeta)) {
            foreach ($existingImportMeta as $metaKey => $metaValue) {
                if (!array_key_exists((string)$metaKey, $bundle['metadata']['import'])) {
                    $bundle['metadata']['import'][(string)$metaKey] = $metaValue;
                }
            }
        }

        if (!is_array($bundle['metadata']['import']['composition'] ?? null)) {
            $bundle['metadata']['import']['composition'] = self::buildViewCompositionContract([
                'view_key' => (string)($view['view_key'] ?? $viewKey),
                'display_label' => self::displayName((string)($view['view_key'] ?? $viewKey)),
                'owning_app' => (string)($view['app_key'] ?? ''),
                'owning_module' => (string)($view['module_key'] ?? ''),
                'route_path' => (string)($view['route_path'] ?? ''),
                'route_key' => '',
                'route_file' => '',
                'route_found' => trim((string)($view['route_path'] ?? '')) !== '',
                'nav_key' => (string)($bundle['navigation_definition']['navigation']['key'] ?? ''),
                'nav_label' => (string)($bundle['navigation_definition']['navigation']['label_key'] ?? ''),
                'nav_icon' => (string)($bundle['navigation_definition']['navigation']['icon'] ?? ''),
                'nav_order' => (int)($bundle['navigation_definition']['navigation']['order'] ?? 10),
                'nav_target_route' => (string)($bundle['navigation_definition']['navigation']['url'] ?? ''),
                'nav_target_view' => '',
                'nav_file' => '',
                'nav_found' => trim((string)($bundle['navigation_definition']['navigation']['url'] ?? '')) !== '',
                'visibility' => (string)($bundle['navigation_definition']['navigation']['visible_if'] ?? 'always'),
                'source_kind' => $selectedSource,
            ]);
        }

        return $bundle;
    }

    /** @param array<int,array<string,mixed>> $items */
    private static function componentForItem(array $items, string $itemId): string
    {
        foreach ($items as $item) {
            if ((string)($item['id'] ?? '') === $itemId) {
                return (string)($item['component'] ?? '');
            }
        }

        return '';
    }

    private static function generatedModuleBasePath(string $appKey, string $moduleKey): string
    {
        $base = rtrim((string)(defined('APP_ROOT') ? APP_ROOT : ''), '/');
        if ($base === '') {
            return '';
        }

        $relative = 'apps/Generated/' . $appKey . '/' . $moduleKey;
        $absolute = $base . '/' . $relative;
        if (!is_dir($absolute)) {
            return '';
        }

        return $absolute;
    }

    /** @return array<string,mixed> */
    private static function readJsonFile(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $raw = @file_get_contents($path);
        if ($raw === false) {
            return [];
        }
        $decoded = json_decode((string)$raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function hasStructuredViewDefinition(array $viewDefinition): bool
    {
        $layout = is_array($viewDefinition['layout'] ?? null) ? $viewDefinition['layout'] : [];
        $items = is_array($layout['items'] ?? null) ? array_values(array_filter($layout['items'], 'is_array')) : [];

        return $items !== [];
    }

    private static function safeKey(string $value): string
    {
        $value = strtolower(trim(str_replace('-', '_', $value)));
        $value = preg_replace('/[^a-z0-9_]+/', '_', $value) ?? '';
        return trim($value, '_');
    }

    /** @param array<string,mixed> $node @return array{app_key:string,module_key:string,view_key:string} */
    private static function deriveGeneratedKeysFromNode(array $node): array
    {
        $meta = is_array($node['meta'] ?? null) ? $node['meta'] : [];
        $path = trim((string)($meta['path'] ?? ''));
        if ($path === '') {
            return ['app_key' => '', 'module_key' => '', 'view_key' => 'index'];
        }

        $parts = array_values(array_filter(explode('/', str_replace('\\', '/', $path)), static fn(string $v): bool => $v !== ''));
        $generatedIndex = array_search('Generated', $parts, true);
        if ($generatedIndex === false || !isset($parts[$generatedIndex + 1], $parts[$generatedIndex + 2])) {
            return ['app_key' => '', 'module_key' => '', 'view_key' => 'index'];
        }

        $viewKey = 'index';
        $nodeType = strtolower(trim((string)($node['type'] ?? '')));
        if (in_array($nodeType, ['view', 'dashboard'], true)) {
            $viewPath = trim((string)($meta['path'] ?? ''));
            $viewName = pathinfo($viewPath, PATHINFO_FILENAME);
            $viewKey = self::safeKey($viewName !== '' ? $viewName : 'index');
            if ($viewKey === '') {
                $viewKey = 'index';
            }
        }

        return [
            'app_key' => self::safeKey((string)$parts[$generatedIndex + 1]),
            'module_key' => self::safeKey((string)$parts[$generatedIndex + 2]),
            'view_key' => $viewKey,
        ];
    }

    /** @param array<string,mixed> $node @return array{app_key:string,module_key:string,view_key:string,app_path:string,module_path:string,view_path:string} */
    private static function deriveNodeKeys(array $node): array
    {
        $meta = is_array($node['meta'] ?? null) ? $node['meta'] : [];
        $path = trim((string)($meta['path'] ?? ''));
        $absolutePath = self::absolutePath($path);
        $nodeType = strtolower(trim((string)($node['type'] ?? '')));

        $appKey = '';
        $moduleKey = '';
        $viewKey = 'index';
        $appPath = '';
        $modulePath = '';
        $viewPath = '';

        $parts = array_values(array_filter(explode('/', str_replace('\\', '/', $path)), static fn(string $v): bool => $v !== ''));
        foreach ($parts as $index => $segment) {
            if ($segment === 'apps' && isset($parts[$index + 1])) {
                $appKey = self::safeKey((string)$parts[$index + 1]);
                $appPath = implode('/', array_slice($parts, 0, $index + 2));
            }
            if ($segment === 'plugins' && isset($parts[$index + 1])) {
                $appKey = self::safeKey((string)$parts[$index + 1]);
                $appPath = implode('/', array_slice($parts, 0, $index + 2));
            }
            if ($segment === 'modules' && isset($parts[$index + 1])) {
                $moduleKey = self::safeKey((string)$parts[$index + 1]);
                $modulePath = implode('/', array_slice($parts, 0, $index + 2));
            }
            if ($segment === 'Views' && isset($parts[$index + 1])) {
                $viewKey = self::safeKey(pathinfo((string)$parts[$index + 1], PATHINFO_FILENAME));
                $viewPath = implode('/', array_slice($parts, 0, $index + 2));
            }
        }

        if ($nodeType === 'module') {
            $modulePath = $path;
        }
        if (in_array($nodeType, ['view', 'dashboard'], true)) {
            $viewPath = $path;
            $viewKey = self::safeKey(pathinfo($path, PATHINFO_FILENAME));
            if ($modulePath === '' && str_contains($path, '/Views/')) {
                $modulePath = substr($path, 0, (int)strpos($path, '/Views/'));
            }
        }

        if ($moduleKey === '' && $modulePath !== '') {
            $moduleKey = self::safeKey(basename($modulePath));
        }
        if ($appKey === '' && $appPath !== '') {
            $appKey = self::safeKey(basename($appPath));
        }
        if ($viewKey === '') {
            $viewKey = 'index';
        }

        if ($viewPath === '' && $modulePath !== '') {
            $candidateIndex = $modulePath . '/Views/index.php';
            if (is_file(self::absolutePath($candidateIndex))) {
                $viewPath = $candidateIndex;
                $viewKey = 'index';
            }
        }

        if ($appPath === '' && $path !== '') {
            $appPath = str_starts_with($path, 'apps/') || str_starts_with($path, 'plugins/')
                ? implode('/', array_slice($parts, 0, 2))
                : '';
        }

        return [
            'app_key' => $appKey,
            'module_key' => $moduleKey,
            'view_key' => $viewKey,
            'app_path' => $appPath,
            'module_path' => $modulePath,
            'view_path' => is_file($absolutePath) ? $path : $viewPath,
        ];
    }

    /** @return array<string,mixed> */
    private static function decodeTemplateJson(string $raw): array
    {
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function displayName(string $key): string
    {
        $parts = array_values(array_filter(explode('_', self::safeKey($key)), static fn(string $v): bool => $v !== ''));
        if ($parts === []) {
            return 'Imported';
        }
        return implode(' ', array_map(static fn(string $part): string => ucfirst($part), $parts));
    }

    private static function routePrefixForPath(string $routePath, string $appKey): string
    {
        if (str_starts_with($routePath, '/apps/')) {
            $parts = array_values(array_filter(explode('/', $routePath), static fn(string $v): bool => $v !== ''));
            if (isset($parts[1])) {
                return '/apps/' . $parts[1];
            }
        }
        return '/apps/' . str_replace('_', '-', $appKey);
    }

    /** @return array{route_path:string,route_file:string,found:bool} */
    private static function extractRouteData(string $modulePath, string $appPath): array
    {
        $candidates = [];
        if ($modulePath !== '') {
            $candidates[] = $modulePath . '/routes.php';
        }
        if ($appPath !== '') {
            $candidates[] = $appPath . '/routes.php';
        }

        foreach ($candidates as $relativePath) {
            $absolutePath = self::absolutePath($relativePath);
            if (!is_file($absolutePath)) {
                continue;
            }
            $raw = @file_get_contents($absolutePath);
            if (!is_string($raw) || trim($raw) === '') {
                continue;
            }
            if (preg_match('/[\"\'](\/(?:apps|ops|admin|u|displays)\/[a-z0-9_\/-]*)[\"\']/i', $raw, $matches) === 1) {
                return ['route_path' => trim((string)$matches[1]), 'route_file' => $relativePath, 'found' => true];
            }
        }

        return ['route_path' => '', 'route_file' => '', 'found' => false];
    }

    /** @return array{url:string,label:string,section:string,group:string,key:string,icon:string,order:int,visible_if:string,nav_file:string,found:bool} */
    private static function extractNavigationData(string $modulePath, string $appPath, string $fallbackRoute): array
    {
        $candidates = [];
        if ($modulePath !== '') {
            $candidates[] = $modulePath . '/navigation.php';
        }
        if ($appPath !== '') {
            $candidates[] = $appPath . '/navigation.php';
        }

        foreach ($candidates as $relativePath) {
            $absolutePath = self::absolutePath($relativePath);
            if (!is_file($absolutePath)) {
                continue;
            }
            $raw = @file_get_contents($absolutePath);
            if (!is_string($raw) || trim($raw) === '') {
                continue;
            }

            $url = $fallbackRoute;
            $label = 'Imported';
            $section = 'Apps';
            $group = 'Apps';
            $key = '';
            $icon = '';
            $order = 10;
            $visibleIf = 'always';

            if (preg_match('/[\"\']url[\"\']\s*=>\s*[\"\']([^\"\']+)[\"\']/i', $raw, $m) === 1) {
                $url = trim((string)$m[1]);
            }
            if (preg_match('/[\"\']label(?:_key)?[\"\']\s*=>\s*[\"\']([^\"\']+)[\"\']/i', $raw, $m) === 1) {
                $label = trim((string)$m[1]);
            }
            if (preg_match('/[\"\']section[\"\']\s*=>\s*[\"\']([^\"\']+)[\"\']/i', $raw, $m) === 1) {
                $section = trim((string)$m[1]);
            }
            if (preg_match('/[\"\']group[\"\']\s*=>\s*[\"\']([^\"\']+)[\"\']/i', $raw, $m) === 1) {
                $group = trim((string)$m[1]);
            }
            if (preg_match('/[\"\']key[\"\']\s*=>\s*[\"\']([^\"\']+)[\"\']/i', $raw, $m) === 1) {
                $key = trim((string)$m[1]);
            }
            if (preg_match('/[\"\']icon[\"\']\s*=>\s*[\"\']([^\"\']+)[\"\']/i', $raw, $m) === 1) {
                $icon = trim((string)$m[1]);
            }
            if (preg_match('/[\"\']order[\"\']\s*=>\s*([0-9]+)/i', $raw, $m) === 1) {
                $order = max(0, (int)$m[1]);
            }
            if (preg_match('/[\"\']visible_if[\"\']\s*=>\s*[\"\']([^\"\']+)[\"\']/i', $raw, $m) === 1) {
                $visibleIf = trim((string)$m[1]);
            }

            if ($url === '') {
                $url = $fallbackRoute;
            }

            return [
                'url' => $url !== '' ? $url : '/apps/imported/module',
                'label' => $label !== '' ? $label : 'Imported',
                'section' => $section !== '' ? $section : 'Apps',
                'group' => $group !== '' ? $group : 'Apps',
                'key' => $key,
                'icon' => $icon,
                'order' => $order,
                'visible_if' => $visibleIf,
                'nav_file' => $relativePath,
                'found' => true,
            ];
        }

        return [
            'url' => '',
            'label' => '',
            'section' => 'Apps',
            'group' => 'Apps',
            'key' => '',
            'icon' => '',
            'order' => 10,
            'visible_if' => 'always',
            'nav_file' => '',
            'found' => false,
        ];
    }

    /** @return array{view_kind:string,items:array<int,array<string,mixed>>,bindings:array<int,array<string,string>>,components:array<int,string>,partial:bool} */
    private static function extractLayoutModel(string $viewPath): array
    {
        $absolutePath = self::absolutePath($viewPath);
        $raw = is_file($absolutePath) ? (string)@file_get_contents($absolutePath) : '';
        $lower = strtolower($raw);

        $components = [];
        if (str_contains($lower, '<table') || str_contains($lower, 'class="table')) {
            $components[] = 'table';
        }
        if (str_contains($lower, '<form') || str_contains($lower, 'type="submit"')) {
            $components[] = 'form';
        }
        if (str_contains($lower, 'kpi') || str_contains($lower, 'metric') || str_contains($lower, 'summary')) {
            $components[] = 'kpi_card';
        }
        if ($components === []) {
            $components[] = 'text_block';
        }

        $items = [];
        $bindings = [];
        $y = 0;
        foreach ($components as $index => $component) {
            $id = $component . '_' . (string)($index + 1);
            $binding = match ($component) {
                'table' => 'module.rows',
                'form' => 'module.fields',
                'kpi_card' => 'module.metrics.total',
                default => 'module.description',
            };
            $props = match ($component) {
                'table' => ['title' => 'Table', 'sample_rows' => 3, 'density' => 'comfortable'],
                'form' => ['title' => 'Form', 'submit_label' => 'Submit', 'show_required' => true],
                'kpi_card' => ['label' => 'KPI', 'value' => '0', 'delta' => '+0%'],
                default => ['title' => 'Text', 'body' => 'Imported from existing view', 'align' => 'left'],
            };
            $h = $component === 'table' ? 6 : 3;
            $w = $component === 'kpi_card' ? 3 : 12;
            $items[] = [
                'id' => $id,
                'component' => $component,
                'group' => 'default',
                'x' => 0,
                'y' => $y,
                'w' => $w,
                'h' => $h,
                'props' => $props,
                'data_binding' => $binding,
            ];
            $bindings[] = [
                'item_id' => $id,
                'component' => $component,
                'data_binding' => $binding,
            ];
            $y += $h;
        }

        $viewKind = in_array('kpi_card', $components, true) ? 'dashboard' : (in_array('form', $components, true) ? 'form' : 'table');

        return [
            'view_kind' => $viewKind,
            'items' => $items,
            'bindings' => $bindings,
            'components' => array_values(array_unique($components)),
            'partial' => true,
        ];
    }

    /** @return array<int,string> */
    private static function extractFieldsFromViewFile(string $viewPath): array
    {
        $absolutePath = self::absolutePath($viewPath);
        if (!is_file($absolutePath)) {
            return [];
        }

        $raw = @file_get_contents($absolutePath);
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        preg_match_all('/\[[\"\']([a-z][a-z0-9_]*)[\"\']\]/i', $raw, $matches);
        $fields = [];
        foreach ((array)($matches[1] ?? []) as $candidate) {
            $key = self::safeKey((string)$candidate);
            if ($key === '' || in_array($key, ['id', 'key', 'label', 'title', 'name', 'class', 'type', 'value'], true)) {
                continue;
            }
            $fields[$key] = true;
        }

        $resolved = array_slice(array_values(array_keys($fields)), 0, 8);
        return $resolved;
    }

    private static function absolutePath(string $relativePath): string
    {
        $root = rtrim((string)(defined('APP_ROOT') ? APP_ROOT : ''), '/');
        if ($root === '') {
            return $relativePath;
        }
        return $root . '/' . ltrim(str_replace('\\', '/', $relativePath), '/');
    }
}

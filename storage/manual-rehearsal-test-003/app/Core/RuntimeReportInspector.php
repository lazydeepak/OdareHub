<?php
declare(strict_types=1);

namespace App\Core;

final class RuntimeReportInspector
{
    public const MANUFACTURING_ENTITIES = [
        'DailyOrder',
        'ProductionEntry',
        'ProductionPlan',
        'QCEntry',
        'DispatchEntry',
        'AssemblyPlan',
        'AssemblyEntry',
    ];

    public static function generateReport(): array
    {
        $entities = EntityRegistry::all();
        $runtimeEntities = [];
        $legacyEntities = [];
        $controllerMigrationStatus = self::getControllerMigrationStatus();
        $mfgGatewayTables = self::getMfgGatewayTables();

        foreach (self::MANUFACTURING_ENTITIES as $key) {
            $registered = isset($entities[$key]) && is_array($entities[$key]);
            $hasService = EntityRuntimeInspector::hasEntityService($key);
            $hasDefinition = self::entityHasDefinition($key);
            $hasPolicies = self::entityHasPolicies($key);
            $hasHooks = self::entityHasHooks($key);
            $slaConfig = ManufacturingSlaConfig::get($key);
            $myWorkSupported = self::isMyWorkSupported($key);
            $actionClassification = EntityRuntimeInspector::classifyActions($key);

            $entityInfo = [
                'key' => $key,
                'label' => EntityRuntimeInspector::getEntityKeyLabel($key),
                'module' => EntityRuntimeInspector::detectModule($key),
                'namespace' => self::getEntityNamespace($key),
                'workflow_field' => null,
                'states_count' => 0,
                'transitions_count' => 0,
                'has_service' => $hasService,
                'has_definition' => $hasDefinition,
                'has_policies' => $hasPolicies,
                'has_hooks' => $hasHooks,
                'sla_config' => $slaConfig,
                'my_work_supported' => $myWorkSupported,
                'action_classification' => $actionClassification,
                'controller_status' => $controllerMigrationStatus[$key] ?? 'unknown',
                'gateway_table' => $mfgGatewayTables[$key] ?? null,
                'runtime_backed' => $registered && $hasDefinition,
                'is_registered' => $registered,
            ];

            if ($registered && is_array($entities[$key])) {
                $definition = $entities[$key];
                $workflow = (array)($definition['workflow'] ?? []);
                $states = (array)($workflow['states'] ?? []);
                $transitions = (array)($workflow['transitions'] ?? []);

                $entityInfo['workflow_field'] = (string)($workflow['field'] ?? 'status');
                $entityInfo['states_count'] = count($states);
                $entityInfo['transitions_count'] = count($transitions);
                $entityInfo['fields'] = array_keys((array)($definition['fields'] ?? []));
                $entityInfo['hooks'] = array_keys((array)($definition['hooks'] ?? []));
                $entityInfo['policies'] = array_keys((array)($definition['policies'] ?? []));

                $runtimeEntities[$key] = $entityInfo;
            } else {
                $entityInfo['fields'] = [];
                $entityInfo['hooks'] = [];
                $entityInfo['policies'] = [];
                $legacyEntities[$key] = $entityInfo;
            }
        }

        $summary = [
            'total_entities' => count(self::MANUFACTURING_ENTITIES),
            'runtime_backed_count' => count($runtimeEntities),
            'legacy_count' => count($legacyEntities),
            'fully_migrated_count' => self::countFullyMigrated($runtimeEntities),
            'partially_migrated_count' => self::countPartiallyMigrated($runtimeEntities),
        ];

        return [
            'generated_at' => date('Y-m-d H:i:s'),
            'summary' => $summary,
            'runtime_entities' => $runtimeEntities,
            'legacy_entities' => $legacyEntities,
            'dispatch_notes' => self::getDispatchSpecialNotes(),
            'controller_migration' => $controllerMigrationStatus,
            'gateway_tables' => $mfgGatewayTables,
            'migration_matrix' => self::generateMigrationMatrix(),
            'route_migration_matrix' => self::generateRouteMigrationMatrix(),
        ];
    }

    public const MIGRATION_ENTITY_RUNTIME = 'entity_runtime';
    public const MIGRATION_SPLIT = 'split';
    public const MIGRATION_LEGACY = 'legacy';
    public const MIGRATION_NOT_APPLICABLE = 'not_applicable';

    public static function generateRouteMigrationMatrix(): array
    {
        return [
            'DailyOrder' => self::getDailyOrderRouteMatrix(),
            'ProductionEntry' => self::getProductionEntryRouteMatrix(),
            'ProductionPlan' => self::getProductionPlanRouteMatrix(),
            'QCEntry' => self::getQCEntryRouteMatrix(),
            'DispatchEntry' => self::getDispatchEntryRouteMatrix(),
            'AssemblyPlan' => self::getAssemblyPlanRouteMatrix(),
            'AssemblyEntry' => self::getAssemblyEntryRouteMatrix(),
        ];
    }

    private static function getDailyOrderRouteMatrix(): array
    {
        return [
            [
                'route' => '/daily-orders/add',
                'method' => 'POST',
                'action_type' => 'create',
                'backing' => self::MIGRATION_ENTITY_RUNTIME,
                'notes' => ['Controller calls DailyOrderService::create() -> EntityStore -> DBDailyOrderGateway'],
                'next_step' => 'Complete - create path uses EntityStore via service',
            ],
            [
                'route' => '/daily-orders/edit',
                'method' => 'POST',
                'action_type' => 'update',
                'backing' => self::MIGRATION_ENTITY_RUNTIME,
                'notes' => ['Controller calls DailyOrderService::update() -> EntityStore -> DBDailyOrderGateway'],
                'next_step' => 'Complete - update path uses EntityStore via service',
            ],
            [
                'route' => '/daily-orders/delete',
                'method' => 'POST',
                'action_type' => 'delete',
                'backing' => self::MIGRATION_ENTITY_RUNTIME,
                'notes' => ['Controller calls DailyOrderService::delete() -> EntityStore -> DBDailyOrderGateway'],
                'next_step' => 'Complete - delete path uses EntityStore via service',
            ],
            [
                'route' => '/daily-orders/import',
                'method' => 'POST',
                'action_type' => 'batch_import',
                'backing' => self::MIGRATION_LEGACY,
                'notes' => ['Batch import uses direct DB INSERT - legacy'],
                'next_step' => 'Consider migrating to service-based batch operations',
            ],
            [
                'route' => '/daily-orders/approval-action',
                'method' => 'POST',
                'action_type' => 'approval_action',
                'backing' => self::MIGRATION_NOT_APPLICABLE,
                'notes' => ['DailyOrder has no approval workflow'],
                'next_step' => 'N/A - no approval workflow exists for this entity',
            ],
        ];
    }

    private static function getProductionEntryRouteMatrix(): array
    {
        return [
            [
                'route' => '/production-entries/add',
                'method' => 'POST',
                'action_type' => 'create',
                'backing' => self::MIGRATION_ENTITY_RUNTIME,
                'notes' => ['Controller calls ProductionEntryService::create() -> EntityStore -> DBProductionEntryGateway'],
                'next_step' => 'Complete - create path uses EntityStore via service',
            ],
            [
                'route' => '/production-entries/edit',
                'method' => 'POST',
                'action_type' => 'update',
                'backing' => self::MIGRATION_ENTITY_RUNTIME,
                'notes' => ['Controller calls ProductionEntryService::update() -> EntityStore -> DBProductionEntryGateway'],
                'next_step' => 'Complete - update path uses EntityStore via service',
            ],
            [
                'route' => '/production-entries/delete',
                'method' => 'POST',
                'action_type' => 'delete',
                'backing' => self::MIGRATION_ENTITY_RUNTIME,
                'notes' => ['Controller calls ProductionEntryService::delete() -> EntityStore -> DBProductionEntryGateway'],
                'next_step' => 'Complete - delete path uses EntityStore via service',
            ],
            [
                'route' => '/production-entries/approval-action',
                'method' => 'POST',
                'action_type' => 'approval_action',
                'backing' => self::MIGRATION_NOT_APPLICABLE,
                'notes' => ['ProductionEntry has no approval workflow'],
                'next_step' => 'N/A - no approval workflow exists for this entity',
            ],
        ];
    }

    private static function getProductionPlanRouteMatrix(): array
    {
        return [
            [
                'route' => '/production-plans/add',
                'method' => 'POST',
                'action_type' => 'create',
                'backing' => self::MIGRATION_ENTITY_RUNTIME,
                'notes' => ['Controller calls ProductionPlanService::create() -> EntityStore -> ManufacturingGateway'],
                'next_step' => 'Complete - create path uses EntityStore via service',
            ],
            [
                'route' => '/production-plans/start-draft',
                'method' => 'POST',
                'action_type' => 'start_draft',
                'backing' => self::MIGRATION_ENTITY_RUNTIME,
                'notes' => ['Controller calls ProductionPlanService::create() for Draft creation -> EntityStore -> ManufacturingGateway'],
                'next_step' => 'Complete - draft creation uses EntityStore via service',
            ],
            [
                'route' => '/production-plans/edit',
                'method' => 'POST',
                'action_type' => 'update',
                'backing' => self::MIGRATION_ENTITY_RUNTIME,
                'notes' => ['Controller calls ProductionPlanService::update() -> EntityStore -> ManufacturingGateway', 'Role-based permission checks preserved in controller', 'Governance lock check preserved before service call', 'Coverage recalculation via ManufacturingPostPersistHelper'],
                'next_step' => 'Complete - update path uses EntityStore via service',
            ],
            [
                'route' => '/production-plans/approval-action',
                'method' => 'POST',
                'action_type' => 'workflow_transition',
                'backing' => self::MIGRATION_ENTITY_RUNTIME,
                'notes' => ['Controller uses ProductionPlanService for approval workflow transitions'],
                'next_step' => 'Complete - workflow transitions use runtime-backed service',
            ],
            [
                'route' => '/production-plans/delete',
                'method' => 'POST',
                'action_type' => 'delete',
                'backing' => self::MIGRATION_ENTITY_RUNTIME,
                'notes' => ['Controller calls ProductionPlanService::delete() -> EntityStore -> ManufacturingGateway', 'Governance lock check preserved before deletion'],
                'next_step' => 'Complete - delete path uses EntityStore via service',
            ],
        ];
    }

    private static function getQCEntryRouteMatrix(): array
    {
        return [
            [
                'route' => '/qc-entries/add',
                'method' => 'POST',
                'action_type' => 'create',
                'backing' => self::MIGRATION_ENTITY_RUNTIME,
                'notes' => ['Controller calls QCEntryService::create() -> EntityStore -> ManufacturingGateway', 'Ledger sync preserved in same transaction', 'Coverage recalculation preserved', 'Handoff sync and audit logging preserved'],
                'next_step' => 'Complete - create path uses EntityStore via service',
            ],
            [
                'route' => '/qc-entries/start-draft',
                'method' => 'POST',
                'action_type' => 'start_draft',
                'backing' => self::MIGRATION_ENTITY_RUNTIME,
                'notes' => ['Controller calls QCEntryService::create() for Draft creation -> EntityStore -> ManufacturingGateway'],
                'next_step' => 'Complete - draft creation uses EntityStore via service',
            ],
            [
                'route' => '/qc-entries/edit',
                'method' => 'POST',
                'action_type' => 'update',
                'backing' => self::MIGRATION_ENTITY_RUNTIME,
                'notes' => ['Controller calls QCEntryService::update() -> EntityStore -> ManufacturingGateway', 'Ledger management (delete/sync/recalculate) preserved in controller within same transaction', 'Governance lock check preserved before service call', 'Coverage recalculation preserved', 'Handoff sync and audit logging preserved'],
                'next_step' => 'Complete - update path uses EntityStore via service',
            ],
            [
                'route' => '/qc-entries/approval-action',
                'method' => 'POST',
                'action_type' => 'workflow_transition',
                'backing' => self::MIGRATION_ENTITY_RUNTIME,
                'notes' => ['Controller uses QCEntryService for approval workflow transitions'],
                'next_step' => 'Complete - workflow transitions use runtime-backed service',
            ],
            [
                'route' => '/qc-entries/delete',
                'method' => 'POST',
                'action_type' => 'delete',
                'backing' => self::MIGRATION_ENTITY_RUNTIME,
                'notes' => ['Controller calls QCEntryService::delete() -> EntityStore -> ManufacturingGateway', 'Governance lock check preserved before deletion'],
                'next_step' => 'Complete - delete path uses EntityStore via service',
            ],
        ];
    }

    private static function getDispatchEntryRouteMatrix(): array
    {
        return [
            [
                'route' => '/dispatch-entries/add',
                'method' => 'POST',
                'action_type' => 'create',
                'backing' => self::MIGRATION_ENTITY_RUNTIME,
                'notes' => ['Controller calls DispatchEntryService::create() -> EntityStore -> ManufacturingGateway', 'DispatchWorkflow::prepareForSave() for dispatch-specific validation preserved', 'Ledger management and transition recording preserved in same transaction', 'Coverage recalculation, handoff sync, and audit logging preserved'],
                'next_step' => 'Complete - create path uses EntityStore via service',
            ],
            [
                'route' => '/dispatch-entries/start-draft',
                'method' => 'POST',
                'action_type' => 'start_draft',
                'backing' => self::MIGRATION_ENTITY_RUNTIME,
                'notes' => ['Controller calls DispatchEntryService::create() for Draft creation -> EntityStore -> ManufacturingGateway'],
                'next_step' => 'Complete - draft creation uses EntityStore via service',
            ],
            [
                'route' => '/dispatch-entries/edit',
                'method' => 'POST',
                'action_type' => 'update',
                'backing' => self::MIGRATION_ENTITY_RUNTIME,
                'notes' => ['Controller calls DispatchEntryService::update() -> EntityStore -> ManufacturingGateway', 'DispatchWorkflow::prepareForSave() for dispatch-specific validation preserved', 'Ledger management and transition recording preserved in same transaction', 'Governance lock check and workflow action validation preserved', 'Coverage recalculation, handoff sync, and audit logging preserved'],
                'next_step' => 'Complete - update path uses EntityStore via service',
            ],
            [
                'route' => '/dispatch-entries/approval-action',
                'method' => 'POST',
                'action_type' => 'governance_transition',
                'backing' => self::MIGRATION_SPLIT,
                'notes' => [
                    'Dual status: dispatch_status (lifecycle) and approval_status (governance)',
                    'Approval-action affects approval_status field only',
                    'Uses DispatchEntryService for governance transitions',
                    'Intentional architecture: governance transition path is intentionally separated from lifecycle transitions',
                ],
                'next_step' => 'Intentional split architecture - governance transitions are complete',
            ],
            [
                'route' => '/dispatch-entries/transition',
                'method' => 'POST',
                'action_type' => 'lifecycle_transition',
                'backing' => self::MIGRATION_ENTITY_RUNTIME,
                'notes' => [
                    'Lifecycle transition affects dispatch_status field',
                    'Uses DispatchEntryService for lifecycle transitions',
                    'Runtime-backed workflow validation applies',
                ],
                'next_step' => 'Complete - lifecycle transitions use runtime-backed service',
            ],
            [
                'route' => '/dispatch-entries/quick-status',
                'method' => 'POST',
                'action_type' => 'quick_status_update',
                'backing' => self::MIGRATION_SPLIT,
                'notes' => [
                    'Quick status is an operational lifecycle shortcut',
                    'Implementation delegates to transition()/DispatchEntryService via mapped actions',
                    'Intentional architecture: kept separate from governance action route',
                ],
                'next_step' => 'Intentional split architecture - operational quick status flow is complete',
            ],
            [
                'route' => '/dispatch-entries/delete',
                'method' => 'POST',
                'action_type' => 'delete',
                'backing' => self::MIGRATION_ENTITY_RUNTIME,
                'notes' => ['Controller calls DispatchEntryService::delete() -> EntityStore -> ManufacturingGateway', 'Ledger cleanup and balance recalculation preserved in same transaction', 'Governance lock check preserved before deletion', 'Coverage recalculation, handoff sync, and audit logging preserved'],
                'next_step' => 'Complete - delete path uses EntityStore via service',
            ],
        ];
    }

    private static function getAssemblyPlanRouteMatrix(): array
    {
        return [
            [
                'route' => 'N/A',
                'method' => 'N/A',
                'action_type' => 'no_routes',
                'backing' => self::MIGRATION_NOT_APPLICABLE,
                'notes' => ['No controller routes exist for AssemblyPlan'],
                'next_step' => 'Create controller routes and use AssemblyPlanService -> EntityStore',
            ],
        ];
    }

    private static function getAssemblyEntryRouteMatrix(): array
    {
        return [
            [
                'route' => 'N/A',
                'method' => 'N/A',
                'action_type' => 'no_routes',
                'backing' => self::MIGRATION_NOT_APPLICABLE,
                'notes' => ['No controller routes exist for AssemblyEntry'],
                'next_step' => 'Create controller routes and use AssemblyEntryService -> EntityStore',
            ],
        ];
    }

    public static function getBackingLabel(string $backing): string
    {
        return match ($backing) {
            self::MIGRATION_ENTITY_RUNTIME => 'Entity Runtime',
            self::MIGRATION_SPLIT => 'Split (Runtime + Side Effects)',
            self::MIGRATION_LEGACY => 'Legacy/Manual',
            self::MIGRATION_NOT_APPLICABLE => 'N/A',
            default => ucfirst(str_replace('_', ' ', $backing)),
        };
    }

    public static function getBackingBadgeClass(string $backing): string
    {
        return match ($backing) {
            self::MIGRATION_ENTITY_RUNTIME => 'badge-success',
            self::MIGRATION_SPLIT => 'badge-warning',
            self::MIGRATION_LEGACY => 'badge-danger',
            self::MIGRATION_NOT_APPLICABLE => 'badge-secondary',
            default => 'badge-info',
        };
    }

    public static function generateMigrationMatrix(): array
    {
        $matrix = [];

        foreach (self::MANUFACTURING_ENTITIES as $key) {
            $migrationData = self::getEntityMigrationData($key);
            $controllerStatus = self::getControllerMigrationStatus()[$key] ?? 'unknown';

            $matrix[$key] = [
                'key' => $key,
                'label' => EntityRuntimeInspector::getEntityKeyLabel($key),
                'module' => EntityRuntimeInspector::detectModule($key),
                'service_wrapper' => EntityRuntimeInspector::hasEntityService($key),
                'controller_present' => self::controllerPresent($key),
                'create_path' => $migrationData['create_path'],
                'update_path' => $migrationData['update_path'],
                'transition_path' => $migrationData['transition_path'],
                'my_work_support' => self::isMyWorkSupported($key),
                'debug_tools_support' => self::hasDebugToolsSupport($key),
                'legacy_notes' => $migrationData['legacy_notes'],
                'next_migration_step' => self::getNextMigrationStep($key, $controllerStatus),
                'controller_status' => $controllerStatus,
                'has_dispatch_split' => $key === 'DispatchEntry',
            ];
        }

        return $matrix;
    }

    private static function getEntityMigrationData(string $key): array
    {
        $controllerStatus = self::getControllerMigrationStatus()[$key] ?? 'unknown';
        $hasService = EntityRuntimeInspector::hasEntityService($key);

        if ($controllerStatus === 'runtime_backed') {
            return self::getRuntimeBackedMigrationData($key);
        }

        if ($controllerStatus === 'service_only') {
            return self::getServiceOnlyMigrationData($key);
        }

        return [
            'create_path' => 'no',
            'update_path' => 'no',
            'transition_path' => 'no',
            'legacy_notes' => ['Entity not registered in EntityRegistry'],
        ];
    }

    private static function getRuntimeBackedMigrationData(string $key): array
    {
        $notes = [];
        $transitionPath = 'yes';
        $createPath = 'partial';
        $updatePath = 'partial';

        if ($key === 'DispatchEntry') {
            $notes[] = 'DispatchEntry uses dual status: dispatch_status (lifecycle) and approval_status (governance)';
            $notes[] = 'Controller routes: /dispatch-entries/transition (lifecycle) and /dispatch-entries/approval-action (governance)';
            $notes[] = 'create path uses runtime-backed DispatchEntryService::create() with ledger management in same transaction';
            $notes[] = 'update path uses runtime-backed DispatchEntryService::update() with ledger management in same transaction';
            $notes[] = 'delete path uses runtime-backed DispatchEntryService::delete() with ledger cleanup in same transaction';
            $notes[] = 'handoff action is operational (routing/assignment only, no status change)';
            $notes[] = 'DispatchWorkflow::prepareForSave() for dispatch-specific validation preserved in controller';
            $createPath = 'yes';
            $updatePath = 'yes';
        } elseif ($key === 'QCEntry') {
            $notes[] = 'Controller routes: /qc-entries/approval-action for workflow transitions';
            $notes[] = 'create path uses runtime-backed QCEntryService::create() with ledger management in same transaction';
            $notes[] = 'update path uses runtime-backed QCEntryService::update() with ledger management in same transaction';
            $notes[] = 'Ledger management preserved in controller; governance lock check preserved';
            $notes[] = 'Coverage recalculation, handoff sync, and audit logging preserved';
            $createPath = 'yes';
            $updatePath = 'yes';
        } elseif ($key === 'ProductionPlan') {
            $notes[] = 'Controller routes: /production-plans/approval-action for workflow transitions';
            $notes[] = 'create path uses runtime-backed ProductionPlanService::create()';
            $notes[] = 'update path uses runtime-backed ProductionPlanService::update()';
            $notes[] = 'Role-based permission checks and governance lock checks preserved in controller';
            $notes[] = 'Coverage recalculation via ManufacturingPostPersistHelper';
            $createPath = 'yes';
            $updatePath = 'yes';
        }

        return [
            'create_path' => $createPath,
            'update_path' => $updatePath,
            'transition_path' => $transitionPath,
            'legacy_notes' => $notes,
        ];
    }

    private static function getServiceOnlyMigrationData(string $key): array
    {
        $notes = [];
        $notes[] = 'No runtime-backed transitions in controller';
        $notes[] = 'Service methods (create/update) use EntityStore and are called from controller';

        if ($key === 'DailyOrder') {
            $notes[] = 'Controller: /daily-orders/add, /daily-orders/edit - create/update use DailyOrderService -> EntityStore';
            $notes[] = 'Transition path: not applicable (DailyOrder has no workflow transitions)';
        } elseif ($key === 'ProductionEntry') {
            $notes[] = 'Controller: /production-entries/add, /production-entries/edit - create/update use ProductionEntryService -> EntityStore';
            $notes[] = 'Transition path: not applicable (ProductionEntry has no workflow transitions)';
        } elseif ($key === 'AssemblyPlan') {
            $notes[] = 'AssemblyPlan service exists but no active controller routes for transitions';
            $notes[] = 'No controller routes for create/edit/update';
        } elseif ($key === 'AssemblyEntry') {
            $notes[] = 'AssemblyEntry service exists but no active controller routes for transitions';
            $notes[] = 'No controller routes for create/edit/update';
        }

        return [
            'create_path' => 'partial',
            'update_path' => 'partial',
            'transition_path' => 'no',
            'legacy_notes' => $notes,
        ];
    }

    public static function controllerPresent(string $key): bool
    {
        $paths = [
            'DailyOrder' => APP_ROOT . '/apps/Manufacturing/modules/DailyOrders/Controllers/DailyOrdersController.php',
            'ProductionEntry' => APP_ROOT . '/apps/Manufacturing/modules/ProductionEntries/Controllers/ProductionEntriesController.php',
            'ProductionPlan' => APP_ROOT . '/apps/Manufacturing/modules/ProductionPlans/Controllers/ProductionPlansController.php',
            'QCEntry' => APP_ROOT . '/apps/Manufacturing/modules/QCEntries/Controllers/QCEntriesController.php',
            'DispatchEntry' => APP_ROOT . '/apps/Manufacturing/modules/DispatchEntries/Controllers/DispatchEntriesController.php',
            'AssemblyPlan' => APP_ROOT . '/apps/Manufacturing/modules/AssemblyPlans/AssemblyPlanService.php',
            'AssemblyEntry' => APP_ROOT . '/apps/Manufacturing/modules/AssemblyEntries/AssemblyEntryService.php',
        ];

        return isset($paths[$key]) && is_file($paths[$key]);
    }

    public static function hasDebugToolsSupport(string $key): bool
    {
        return self::isMyWorkSupported($key);
    }

    public static function getNextMigrationStep(string $key, string $controllerStatus): string
    {
        if ($controllerStatus === 'runtime_backed') {
            if ($key === 'DispatchEntry') {
                return 'DispatchEntry fully runtime-backed; split transitions are intentional (lifecycle, governance, operational)';
            }
            return 'Controller CRUD and workflow paths are runtime-backed via service';
        }

        if ($controllerStatus === 'service_only') {
            if ($key === 'DailyOrder') {
                return 'Create/update paths now use EntityStore; no workflow transitions exist for this entity';
            }
            if ($key === 'ProductionEntry') {
                return 'Create/update paths now use EntityStore; no workflow transitions exist for this entity';
            }
            if ($key === 'AssemblyPlan' || $key === 'AssemblyEntry') {
                return 'Create controller routes and use EntityStore for create/update paths';
            }
            return 'Add entity definition and runtime transitions to controller routes';
        }

        return 'Register entity in EntityRegistry via bootstrap';
    }

    public static function getControllerMigrationStatus(): array
    {
        return [
            'DailyOrder' => 'service_only',
            'ProductionEntry' => 'service_only',
            'ProductionPlan' => 'runtime_backed',
            'QCEntry' => 'runtime_backed',
            'DispatchEntry' => 'runtime_backed',
            'AssemblyPlan' => 'service_only',
            'AssemblyEntry' => 'service_only',
        ];
    }

    public static function getMfgGatewayTables(): array
    {
        return [
            'DailyOrder' => 'daily_orders',
            'ProductionEntry' => 'production_entries',
            'ProductionPlan' => 'production_plans',
            'QCEntry' => 'qc_entries',
            'DispatchEntry' => 'dispatch_entries',
            'AssemblyPlan' => 'mfg_assembly_plans',
            'AssemblyEntry' => 'mfg_assembly_entries',
        ];
    }

    public static function getDispatchSpecialNotes(): array
    {
        return [
            'DispatchEntry uses dual status fields: dispatch_status (entity lifecycle) and approval_status (governance-only)',
            'Dispatch split transitions are intentional architecture: lifecycle, governance, and operational routing are separate paths',
            'Actions are classified into three categories: entity_lifecycle, governance_only, and operational',
            'reject is a governance-only action that affects approval_status without changing dispatch_status',
            'handoff is an operational action for routing/assignment that does not affect lifecycle or governance',
        ];
    }

    public static function getMigrationStatusLabel(string $status): string
    {
        return match ($status) {
            'runtime_backed' => 'Runtime-backed (Controller + Service)',
            'service_only' => 'Service-only (Legacy Controller)',
            'legacy' => 'Legacy (No Runtime)',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    public static function getMigrationStatusBadgeClass(string $status): string
    {
        return match ($status) {
            'runtime_backed' => 'badge-success',
            'service_only' => 'badge-warning',
            'legacy' => 'badge-danger',
            default => 'badge-info',
        };
    }

    private static function entityHasDefinition(string $key): bool
    {
        $paths = [
            'DailyOrder' => APP_ROOT . '/apps/Manufacturing/modules/DailyOrders/EntityDefinition.php',
            'ProductionEntry' => APP_ROOT . '/apps/Manufacturing/modules/ProductionEntries/EntityDefinition.php',
            'ProductionPlan' => APP_ROOT . '/apps/Manufacturing/modules/ProductionPlans/EntityDefinition.php',
            'QCEntry' => APP_ROOT . '/plugins/QCEntries/EntityDefinition.php',
            'DispatchEntry' => APP_ROOT . '/plugins/DispatchEntries/EntityDefinition.php',
            'AssemblyPlan' => APP_ROOT . '/apps/Manufacturing/modules/AssemblyPlans/EntityDefinition.php',
            'AssemblyEntry' => APP_ROOT . '/apps/Manufacturing/modules/AssemblyEntries/EntityDefinition.php',
        ];

        return isset($paths[$key]) && is_file($paths[$key]);
    }

    private static function entityHasPolicies(string $key): bool
    {
        $paths = [
            'DailyOrder' => APP_ROOT . '/apps/Manufacturing/modules/DailyOrders/DailyOrderPolicies.php',
            'ProductionEntry' => APP_ROOT . '/apps/Manufacturing/modules/ProductionEntries/ProductionEntryPolicies.php',
            'ProductionPlan' => APP_ROOT . '/apps/Manufacturing/modules/ProductionPlans/ProductionPlanPolicies.php',
            'QCEntry' => APP_ROOT . '/plugins/QCEntries/QCEntryPolicies.php',
            'DispatchEntry' => APP_ROOT . '/plugins/DispatchEntries/DispatchEntryPolicies.php',
            'AssemblyPlan' => APP_ROOT . '/apps/Manufacturing/modules/AssemblyPlans/AssemblyPlanPolicies.php',
            'AssemblyEntry' => APP_ROOT . '/apps/Manufacturing/modules/AssemblyEntries/AssemblyEntryPolicies.php',
        ];

        return isset($paths[$key]) && is_file($paths[$key]);
    }

    private static function entityHasHooks(string $key): bool
    {
        $paths = [
            'DailyOrder' => APP_ROOT . '/apps/Manufacturing/modules/DailyOrders/DailyOrderHooks.php',
            'ProductionEntry' => APP_ROOT . '/apps/Manufacturing/modules/ProductionEntries/ProductionEntryHooks.php',
            'ProductionPlan' => APP_ROOT . '/apps/Manufacturing/modules/ProductionPlans/ProductionPlanHooks.php',
            'QCEntry' => APP_ROOT . '/plugins/QCEntries/QCEntryHooks.php',
            'DispatchEntry' => APP_ROOT . '/plugins/DispatchEntries/DispatchEntryHooks.php',
            'AssemblyPlan' => APP_ROOT . '/apps/Manufacturing/modules/AssemblyPlans/AssemblyPlanHooks.php',
            'AssemblyEntry' => APP_ROOT . '/apps/Manufacturing/modules/AssemblyEntries/AssemblyEntryHooks.php',
        ];

        return isset($paths[$key]) && is_file($paths[$key]);
    }

    private static function isMyWorkSupported(string $key): bool
    {
        $supported = [
            'DailyOrder',
            'ProductionEntry',
            'ProductionPlan',
            'QCEntry',
            'DispatchEntry',
        ];

        return in_array($key, $supported, true);
    }

    private static function countFullyMigrated(array $runtimeEntities): int
    {
        $count = 0;
        foreach ($runtimeEntities as $entity) {
            if (
                ($entity['has_definition'] ?? false) &&
                ($entity['has_service'] ?? false) &&
                ($entity['controller_status'] ?? '') === 'runtime_backed'
            ) {
                $count++;
            }
        }
        return $count;
    }

    private static function countPartiallyMigrated(array $runtimeEntities): int
    {
        $count = 0;
        foreach ($runtimeEntities as $entity) {
            if (
                ($entity['has_definition'] ?? false) &&
                ($entity['controller_status'] ?? '') !== 'runtime_backed'
            ) {
                $count++;
            }
        }
        return $count;
    }

    public static function getEntityNamespace(string $key): string
    {
        $namespaces = [
            'DailyOrder' => 'Apps\\Manufacturing\\Modules\\DailyOrders',
            'ProductionEntry' => 'Apps\\Manufacturing\\Modules\\ProductionEntries',
            'ProductionPlan' => 'Apps\\Manufacturing\\Modules\\ProductionPlans',
            'QCEntry' => 'Plugins\\QCEntries',
            'DispatchEntry' => 'Plugins\\DispatchEntries',
            'AssemblyPlan' => 'Apps\\Manufacturing\\Modules\\AssemblyPlans',
            'AssemblyEntry' => 'Apps\\Manufacturing\\Modules\\AssemblyEntries',
        ];

        return $namespaces[$key] ?? 'Unknown';
    }

    public static function generateExportText(array $report): string
    {
        $lines = [];
        $lines[] = '=== ENTITY RUNTIME DIAGNOSTICS REPORT ===';
        $lines[] = 'Generated: ' . ($report['generated_at'] ?? 'N/A');
        $lines[] = '';
        $lines[] = 'SUMMARY';
        $lines[] = '-------';
        $lines[] = 'Total Entities: ' . ($report['summary']['total_entities'] ?? 0);
        $lines[] = 'Runtime-backed: ' . ($report['summary']['runtime_backed_count'] ?? 0);
        $lines[] = 'Legacy: ' . ($report['summary']['legacy_count'] ?? 0);
        $lines[] = 'Fully Migrated: ' . ($report['summary']['fully_migrated_count'] ?? 0);
        $lines[] = 'Partially Migrated: ' . ($report['summary']['partially_migrated_count'] ?? 0);
        $lines[] = '';

        $lines[] = 'RUNTIME-BACKED ENTITIES';
        $lines[] = '-----------------------';
        foreach (($report['runtime_entities'] ?? []) as $key => $entity) {
            $lines[] = '';
            $lines[] = "[{$key}]";
            $lines[] = "  Module: " . ($entity['module'] ?? 'Unknown');
            $lines[] = "  Workflow Field: " . ($entity['workflow_field'] ?? 'N/A');
            $lines[] = "  States: " . ($entity['states_count'] ?? 0);
            $lines[] = "  Transitions: " . ($entity['transitions_count'] ?? 0);
            $lines[] = "  Service: " . ($entity['has_service'] ? 'Yes' : 'No');
            $lines[] = "  Policies: " . ($entity['has_policies'] ? 'Yes' : 'No');
            $lines[] = "  Hooks: " . ($entity['has_hooks'] ? 'Yes' : 'No');
            $lines[] = "  My Work: " . ($entity['my_work_supported'] ? 'Supported' : 'Not Supported');
            $lines[] = "  Controller: " . self::getMigrationStatusLabel($entity['controller_status'] ?? 'unknown');
            $lines[] = "  Gateway Table: " . ($entity['gateway_table'] ?? 'N/A');
        }

        if (!empty($report['legacy_entities'] ?? [])) {
            $lines[] = '';
            $lines[] = 'LEGACY ENTITIES';
            $lines[] = '----------------';
            foreach ($report['legacy_entities'] as $key => $entity) {
                $lines[] = "[{$key}] - Not runtime-backed (entity not registered in EntityRegistry)";
            }
        }

        if (!empty($report['dispatch_notes'] ?? [])) {
            $lines[] = '';
            $lines[] = 'DISPATCH SPECIAL NOTES';
            $lines[] = '----------------------';
            foreach ($report['dispatch_notes'] as $note) {
                $lines[] = "- {$note}";
            }
        }

        $lines[] = '';
        $lines[] = 'CONTROLLER/SERVICE MIGRATION MATRIX';
        $lines[] = '--------------------------------';
        foreach (($report['migration_matrix'] ?? []) as $key => $row) {
            $lines[] = '';
            $lines[] = "[{$key}] ({$row['label']})";
            $lines[] = "  Module: " . ($row['module'] ?? 'Unknown');
            $lines[] = "  Service: " . ($row['service_wrapper'] ? 'Yes' : 'No');
            $lines[] = "  Controller: " . ($row['controller_present'] ? 'Present' : 'Missing');
            $lines[] = "  Create Path: " . ($row['create_path'] ?? 'no');
            $lines[] = "  Update Path: " . ($row['update_path'] ?? 'no');
            $lines[] = "  Transition Path: " . ($row['transition_path'] ?? 'no');
            $lines[] = "  My Work Support: " . ($row['my_work_support'] ? 'Yes' : 'No');
            $lines[] = "  Debug Tools: " . ($row['debug_tools_support'] ? 'Yes' : 'No');
            $lines[] = "  Controller Status: " . self::getMigrationStatusLabel($row['controller_status'] ?? 'unknown');
            if (!empty($row['legacy_notes'])) {
                foreach ($row['legacy_notes'] as $note) {
                    $lines[] = "  - {$note}";
                }
            }
            $lines[] = "  Next Step: " . ($row['next_migration_step'] ?? 'N/A');
        }

        $lines[] = '';
        $lines[] = 'ROUTE-LEVEL MIGRATION MATRIX';
        $lines[] = '--------------------------';
        foreach (($report['route_migration_matrix'] ?? []) as $entityKey => $routes) {
            $lines[] = '';
            $lines[] = "[{$entityKey}]";
            foreach ($routes as $route) {
                $lines[] = "  Route: {$route['method']} {$route['route']}";
                $lines[] = "  Action: {$route['action_type']}";
                $lines[] = "  Backing: " . self::getBackingLabel($route['backing']);
                if (!empty($route['notes'])) {
                    foreach ($route['notes'] as $note) {
                        $lines[] = "    - {$note}";
                    }
                }
                $lines[] = "  Next Step: {$route['next_step']}";
            }
        }

        $lines[] = '';
        $lines[] = '=== END OF REPORT ===';

        return implode("\n", $lines);
    }
}

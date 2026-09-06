<?php
declare(strict_types=1);

namespace Tests;

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Stubs.php';
require_once __DIR__ . '/../app/Core/RuntimeReportInspector.php';
require_once __DIR__ . '/../app/Core/EntityRuntimeInspector.php';
require_once __DIR__ . '/../app/Core/EntityRegistry.php';
require_once __DIR__ . '/../app/Core/ManufacturingSlaConfig.php';
require_once __DIR__ . '/../app/Core/EntityWorkflowGuard.php';
require_once __DIR__ . '/../app/Core/EntityPolicyResolver.php';
require_once __DIR__ . '/../app/Core/EntityHookRunner.php';
require_once __DIR__ . '/../app/Core/MyWorkInspector.php';
require_once __DIR__ . '/../app/Core/MyWorkEntityAdapter.php';
require_once __DIR__ . '/../app/Core/EntityContext.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/QCEntries/EntityDefinition.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/QCEntries/QCEntryPolicies.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/QCEntries/QCEntryHooks.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/QCEntries/QCEntryService.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/DispatchEntries/EntityDefinition.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/DispatchEntries/DispatchEntryPolicies.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/DispatchEntries/DispatchEntryHooks.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/DispatchEntries/DispatchEntryService.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/ProductionPlans/EntityDefinition.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/ProductionPlans/ProductionPlanPolicies.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/ProductionPlans/ProductionPlanHooks.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/ProductionPlans/ProductionPlanService.php';

use PHPUnit\Framework\TestCase;
use App\Core\RuntimeReportInspector;
use App\Core\EntityRegistry;

final class RuntimeReportInspectorTest extends TestCase
{
    protected function setUp(): void
    {
        EntityRegistry::reset();
        \Plugins\QCEntries\register_qc_entry_entity();
        \Plugins\DispatchEntries\register_dispatch_entry_entity();
        \Plugins\ProductionPlans\register_production_plan_entity();
    }

    public function testGenerateReportReturnsArray(): void
    {
        $result = RuntimeReportInspector::generateReport();
        $this->assertIsArray($result);
    }

    public function testReportContainsGeneratedAt(): void
    {
        $result = RuntimeReportInspector::generateReport();
        $this->assertArrayHasKey('generated_at', $result);
        $this->assertNotEmpty($result['generated_at']);
    }

    public function testReportContainsSummary(): void
    {
        $result = RuntimeReportInspector::generateReport();
        $this->assertArrayHasKey('summary', $result);
        $this->assertIsArray($result['summary']);

        $summary = $result['summary'];
        $this->assertArrayHasKey('total_entities', $summary);
        $this->assertArrayHasKey('runtime_backed_count', $summary);
        $this->assertArrayHasKey('legacy_count', $summary);
        $this->assertArrayHasKey('fully_migrated_count', $summary);
        $this->assertArrayHasKey('partially_migrated_count', $summary);
    }

    public function testReportContainsRuntimeEntities(): void
    {
        $result = RuntimeReportInspector::generateReport();
        $this->assertArrayHasKey('runtime_entities', $result);
        $this->assertIsArray($result['runtime_entities']);

        $this->assertArrayHasKey('QCEntry', $result['runtime_entities']);
        $this->assertArrayHasKey('DispatchEntry', $result['runtime_entities']);
        $this->assertArrayHasKey('ProductionPlan', $result['runtime_entities']);
    }

    public function testReportContainsLegacyEntities(): void
    {
        $result = RuntimeReportInspector::generateReport();
        $this->assertArrayHasKey('legacy_entities', $result);
        $this->assertIsArray($result['legacy_entities']);
    }

    public function testReportContainsDispatchNotes(): void
    {
        $result = RuntimeReportInspector::generateReport();
        $this->assertArrayHasKey('dispatch_notes', $result);
        $this->assertIsArray($result['dispatch_notes']);
        $this->assertNotEmpty($result['dispatch_notes']);
    }

    public function testReportContainsControllerMigration(): void
    {
        $result = RuntimeReportInspector::generateReport();
        $this->assertArrayHasKey('controller_migration', $result);
        $this->assertIsArray($result['controller_migration']);
    }

    public function testReportContainsGatewayTables(): void
    {
        $result = RuntimeReportInspector::generateReport();
        $this->assertArrayHasKey('gateway_tables', $result);
        $this->assertIsArray($result['gateway_tables']);
    }

    public function testRuntimeEntitiesHaveRequiredFields(): void
    {
        $result = RuntimeReportInspector::generateReport();

        foreach ($result['runtime_entities'] as $key => $entity) {
            $this->assertArrayHasKey('key', $entity);
            $this->assertEquals($key, $entity['key']);

            $this->assertArrayHasKey('label', $entity);
            $this->assertArrayHasKey('module', $entity);
            $this->assertArrayHasKey('namespace', $entity);
            $this->assertArrayHasKey('workflow_field', $entity);
            $this->assertArrayHasKey('states_count', $entity);
            $this->assertArrayHasKey('transitions_count', $entity);
            $this->assertArrayHasKey('has_service', $entity);
            $this->assertArrayHasKey('has_definition', $entity);
            $this->assertArrayHasKey('has_policies', $entity);
            $this->assertArrayHasKey('has_hooks', $entity);
            $this->assertArrayHasKey('sla_config', $entity);
            $this->assertArrayHasKey('my_work_supported', $entity);
            $this->assertArrayHasKey('action_classification', $entity);
            $this->assertArrayHasKey('controller_status', $entity);
            $this->assertArrayHasKey('gateway_table', $entity);
            $this->assertArrayHasKey('runtime_backed', $entity);
            $this->assertArrayHasKey('is_registered', $entity);
        }
    }

    public function testManufacturingEntitiesAllCovered(): void
    {
        $expectedEntities = RuntimeReportInspector::MANUFACTURING_ENTITIES;
        $this->assertContains('DailyOrder', $expectedEntities);
        $this->assertContains('ProductionEntry', $expectedEntities);
        $this->assertContains('ProductionPlan', $expectedEntities);
        $this->assertContains('QCEntry', $expectedEntities);
        $this->assertContains('DispatchEntry', $expectedEntities);
        $this->assertContains('AssemblyPlan', $expectedEntities);
        $this->assertContains('AssemblyEntry', $expectedEntities);
        $this->assertCount(7, $expectedEntities);
    }

    public function testGetControllerMigrationStatusReturnsArray(): void
    {
        $status = RuntimeReportInspector::getControllerMigrationStatus();
        $this->assertIsArray($status);

        foreach (RuntimeReportInspector::MANUFACTURING_ENTITIES as $key) {
            $this->assertArrayHasKey($key, $status);
        }
    }

    public function testGetMfgGatewayTablesReturnsArray(): void
    {
        $tables = RuntimeReportInspector::getMfgGatewayTables();
        $this->assertIsArray($tables);

        foreach (RuntimeReportInspector::MANUFACTURING_ENTITIES as $key) {
            $this->assertArrayHasKey($key, $tables);
        }
    }

    public function testGetDispatchSpecialNotesReturnsArray(): void
    {
        $notes = RuntimeReportInspector::getDispatchSpecialNotes();
        $this->assertIsArray($notes);
        $this->assertNotEmpty($notes);
    }

    public function testGetMigrationStatusLabelReturnsString(): void
    {
        $labels = [
            'runtime_backed' => 'Runtime-backed (Controller + Service)',
            'service_only' => 'Service-only (Legacy Controller)',
            'legacy' => 'Legacy (No Runtime)',
            'unknown' => 'Unknown',
        ];

        foreach ($labels as $status => $expectedLabel) {
            $label = RuntimeReportInspector::getMigrationStatusLabel($status);
            $this->assertEquals($expectedLabel, $label);
        }
    }

    public function testGetMigrationStatusBadgeClassReturnsString(): void
    {
        $badgeClasses = [
            'runtime_backed' => 'badge-success',
            'service_only' => 'badge-warning',
            'legacy' => 'badge-danger',
            'unknown' => 'badge-info',
        ];

        foreach ($badgeClasses as $status => $expectedClass) {
            $class = RuntimeReportInspector::getMigrationStatusBadgeClass($status);
            $this->assertEquals($expectedClass, $class);
        }
    }

    public function testGetEntityNamespaceReturnsString(): void
    {
        foreach (RuntimeReportInspector::MANUFACTURING_ENTITIES as $key) {
            $namespace = RuntimeReportInspector::getEntityNamespace($key);
            $this->assertNotEmpty($namespace);
            $this->assertNotEquals('Unknown', $namespace);
        }
    }

    public function testGenerateExportTextReturnsString(): void
    {
        $report = RuntimeReportInspector::generateReport();
        $export = RuntimeReportInspector::generateExportText($report);

        $this->assertIsString($export);
        $this->assertNotEmpty($export);
        $this->assertStringContainsString('ENTITY RUNTIME DIAGNOSTICS REPORT', $export);
        $this->assertStringContainsString('SUMMARY', $export);
        $this->assertStringContainsString('Total Entities:', $export);
        $this->assertStringContainsString('Runtime-backed:', $export);
    }

    public function testExportTextContainsRuntimeEntities(): void
    {
        $report = RuntimeReportInspector::generateReport();
        $export = RuntimeReportInspector::generateExportText($report);

        $this->assertStringContainsString('RUNTIME-BACKED ENTITIES', $export);

        foreach ($report['runtime_entities'] as $key => $entity) {
            $this->assertStringContainsString('[' . $key . ']', $export);
        }
    }

    public function testExportTextContainsDispatchNotes(): void
    {
        $report = RuntimeReportInspector::generateReport();
        $export = RuntimeReportInspector::generateExportText($report);

        $this->assertStringContainsString('DISPATCH SPECIAL NOTES', $export);
        $this->assertStringContainsString('END OF REPORT', $export);
    }

    public function testSummaryCountsMatchRuntimeEntities(): void
    {
        $report = RuntimeReportInspector::generateReport();

        $runtimeCount = count($report['runtime_entities']);
        $this->assertEquals($runtimeCount, $report['summary']['runtime_backed_count']);

        $legacyCount = count($report['legacy_entities']);
        $this->assertEquals($legacyCount, $report['summary']['legacy_count']);

        $totalCount = $report['summary']['total_entities'];
        $this->assertEquals($totalCount, $runtimeCount + $legacyCount);
    }

    public function testRuntimeEntitiesHaveWorkflowInfo(): void
    {
        $result = RuntimeReportInspector::generateReport();

        $qcEntry = $result['runtime_entities']['QCEntry'] ?? null;
        $this->assertNotNull($qcEntry);
        $this->assertEquals('status', $qcEntry['workflow_field']);
        $this->assertGreaterThan(0, $qcEntry['states_count']);
        $this->assertGreaterThan(0, $qcEntry['transitions_count']);

        $dispatchEntry = $result['runtime_entities']['DispatchEntry'] ?? null;
        $this->assertNotNull($dispatchEntry);
        $this->assertEquals('dispatch_status', $dispatchEntry['workflow_field']);
        $this->assertNotNull($dispatchEntry['action_classification']);
    }

    public function testRuntimeEntitiesControllerStatus(): void
    {
        $result = RuntimeReportInspector::generateReport();

        $productionPlan = $result['runtime_entities']['ProductionPlan'] ?? null;
        $this->assertNotNull($productionPlan);
        $this->assertEquals('runtime_backed', $productionPlan['controller_status']);

        $qcEntry = $result['runtime_entities']['QCEntry'] ?? null;
        $this->assertNotNull($qcEntry);
        $this->assertEquals('runtime_backed', $qcEntry['controller_status']);

        $dispatchEntry = $result['runtime_entities']['DispatchEntry'] ?? null;
        $this->assertNotNull($dispatchEntry);
        $this->assertEquals('runtime_backed', $dispatchEntry['controller_status']);
    }

    public function testDispatchEntryActionClassification(): void
    {
        $result = RuntimeReportInspector::generateReport();

        $dispatchEntry = $result['runtime_entities']['DispatchEntry'] ?? null;
        $this->assertNotNull($dispatchEntry);

        $classification = $dispatchEntry['action_classification'];
        $this->assertNotNull($classification);

        $this->assertArrayHasKey('entity_lifecycle', $classification);
        $this->assertContains('submit', $classification['entity_lifecycle']);
        $this->assertContains('approve', $classification['entity_lifecycle']);
        $this->assertContains('finalize', $classification['entity_lifecycle']);

        $this->assertArrayHasKey('governance_only', $classification);
        $this->assertContains('reject', $classification['governance_only']);

        $this->assertArrayHasKey('operational', $classification);
        $this->assertContains('handoff', $classification['operational']);
    }

    public function testMyWorkSupportedEntities(): void
    {
        $result = RuntimeReportInspector::generateReport();

        $myWorkSupported = ['DailyOrder', 'ProductionEntry', 'ProductionPlan', 'QCEntry', 'DispatchEntry'];

        foreach ($myWorkSupported as $key) {
            $entity = $result['runtime_entities'][$key] ?? null;
            if ($entity !== null) {
                $this->assertTrue($entity['my_work_supported'], "{$key} should support My Work");
            }
        }

        $assemblyPlan = $result['runtime_entities']['AssemblyPlan'] ?? null;
        $this->assertFalse($assemblyPlan['my_work_supported'] ?? false, 'AssemblyPlan should not support My Work');
    }

    public function testReportContainsMigrationMatrix(): void
    {
        $result = RuntimeReportInspector::generateReport();
        $this->assertArrayHasKey('migration_matrix', $result);
        $this->assertIsArray($result['migration_matrix']);

        foreach (RuntimeReportInspector::MANUFACTURING_ENTITIES as $key) {
            $this->assertArrayHasKey($key, $result['migration_matrix']);
        }
    }

    public function testMigrationMatrixHasRequiredFields(): void
    {
        $matrix = RuntimeReportInspector::generateMigrationMatrix();

        foreach ($matrix as $key => $row) {
            $this->assertArrayHasKey('key', $row);
            $this->assertEquals($key, $row['key']);
            $this->assertArrayHasKey('label', $row);
            $this->assertArrayHasKey('module', $row);
            $this->assertArrayHasKey('service_wrapper', $row);
            $this->assertArrayHasKey('controller_present', $row);
            $this->assertArrayHasKey('create_path', $row);
            $this->assertArrayHasKey('update_path', $row);
            $this->assertArrayHasKey('transition_path', $row);
            $this->assertArrayHasKey('my_work_support', $row);
            $this->assertArrayHasKey('debug_tools_support', $row);
            $this->assertArrayHasKey('legacy_notes', $row);
            $this->assertArrayHasKey('next_migration_step', $row);
            $this->assertArrayHasKey('controller_status', $row);
            $this->assertArrayHasKey('has_dispatch_split', $row);
        }
    }

    public function testMigrationMatrixRuntimeBackedEntities(): void
    {
        $matrix = RuntimeReportInspector::generateMigrationMatrix();

        $runtimeBacked = ['ProductionPlan', 'QCEntry', 'DispatchEntry'];
        foreach ($runtimeBacked as $key) {
            $row = $matrix[$key];
            $this->assertEquals('runtime_backed', $row['controller_status']);
            $this->assertTrue($row['service_wrapper']);
            $this->assertTrue($row['controller_present']);
            $this->assertEquals('yes', $row['transition_path']);
            $this->assertIsArray($row['legacy_notes']);
        }
    }

    public function testMigrationMatrixServiceOnlyEntities(): void
    {
        $matrix = RuntimeReportInspector::generateMigrationMatrix();

        $serviceOnly = ['DailyOrder', 'ProductionEntry', 'AssemblyPlan', 'AssemblyEntry'];
        foreach ($serviceOnly as $key) {
            $row = $matrix[$key];
            $this->assertEquals('service_only', $row['controller_status']);
            $this->assertEquals('no', $row['transition_path']);
            $this->assertIsArray($row['legacy_notes']);
            $this->assertNotEmpty($row['legacy_notes']);
        }
    }

    public function testDispatchEntryHasDispatchSplit(): void
    {
        $matrix = RuntimeReportInspector::generateMigrationMatrix();

        $dispatchEntry = $matrix['DispatchEntry'];
        $this->assertTrue($dispatchEntry['has_dispatch_split']);
        $hasDispatchNote = false;
        foreach ($dispatchEntry['legacy_notes'] as $note) {
            if (strpos($note, 'DispatchEntry uses dual status') !== false) {
                $hasDispatchNote = true;
                break;
            }
        }
        $this->assertTrue($hasDispatchNote);
    }

    public function testMigrationMatrixCreateUpdatePaths(): void
    {
        $matrix = RuntimeReportInspector::generateMigrationMatrix();

        $runtimeBacked = ['ProductionPlan', 'QCEntry', 'DispatchEntry'];
        foreach ($runtimeBacked as $key) {
            $row = $matrix[$key];
            $this->assertContains($row['create_path'], ['yes', 'partial']);
            $this->assertContains($row['update_path'], ['yes', 'partial']);
        }

        $serviceOnly = ['DailyOrder', 'ProductionEntry', 'AssemblyPlan', 'AssemblyEntry'];
        foreach ($serviceOnly as $key) {
            $row = $matrix[$key];
            $this->assertContains($row['create_path'], ['yes', 'partial']);
            $this->assertContains($row['update_path'], ['yes', 'partial']);
        }
    }

    public function testMigrationMatrixMyWorkSupport(): void
    {
        $matrix = RuntimeReportInspector::generateMigrationMatrix();

        $supported = ['DailyOrder', 'ProductionEntry', 'ProductionPlan', 'QCEntry', 'DispatchEntry'];
        foreach ($supported as $key) {
            $row = $matrix[$key];
            $this->assertTrue($row['my_work_support']);
            $this->assertTrue($row['debug_tools_support']);
        }

        $unsupported = ['AssemblyPlan', 'AssemblyEntry'];
        foreach ($unsupported as $key) {
            $row = $matrix[$key];
            $this->assertFalse($row['my_work_support']);
            $this->assertFalse($row['debug_tools_support']);
        }
    }

    public function testNextMigrationStepForAllEntities(): void
    {
        $matrix = RuntimeReportInspector::generateMigrationMatrix();

        foreach ($matrix as $key => $row) {
            $this->assertNotEmpty($row['next_migration_step']);
            $this->assertIsString($row['next_migration_step']);
        }
    }

    public function testExportTextContainsMigrationMatrix(): void
    {
        $report = RuntimeReportInspector::generateReport();
        $export = RuntimeReportInspector::generateExportText($report);

        $this->assertStringContainsString('CONTROLLER/SERVICE MIGRATION MATRIX', $export);

        foreach (RuntimeReportInspector::MANUFACTURING_ENTITIES as $key) {
            $this->assertStringContainsString('[' . $key . ']', $export);
        }
    }

    public function testExportTextContainsMigrationDetails(): void
    {
        $report = RuntimeReportInspector::generateReport();
        $export = RuntimeReportInspector::generateExportText($report);

        $this->assertStringContainsString('Service:', $export);
        $this->assertStringContainsString('Controller:', $export);
        $this->assertStringContainsString('Create Path:', $export);
        $this->assertStringContainsString('Update Path:', $export);
        $this->assertStringContainsString('Transition Path:', $export);
        $this->assertStringContainsString('Next Step:', $export);
    }

    public function testControllerPresentForEntitiesWithControllers(): void
    {
        $entitiesWithControllers = ['DailyOrder', 'ProductionEntry', 'ProductionPlan', 'QCEntry', 'DispatchEntry'];
        foreach ($entitiesWithControllers as $key) {
            $present = RuntimeReportInspector::controllerPresent($key);
            $this->assertTrue($present, "Controller/service should be present for {$key}");
        }
    }

    public function testGenerateMigrationMatrixReturnsArray(): void
    {
        $matrix = RuntimeReportInspector::generateMigrationMatrix();
        $this->assertIsArray($matrix);
        $this->assertCount(7, $matrix);
    }

    public function testReportContainsRouteMigrationMatrix(): void
    {
        $report = RuntimeReportInspector::generateReport();
        $this->assertArrayHasKey('route_migration_matrix', $report);
        $this->assertIsArray($report['route_migration_matrix']);

        foreach (RuntimeReportInspector::MANUFACTURING_ENTITIES as $key) {
            $this->assertArrayHasKey($key, $report['route_migration_matrix']);
        }
    }

    public function testRouteMigrationMatrixHasRequiredFields(): void
    {
        $matrix = RuntimeReportInspector::generateRouteMigrationMatrix();

        foreach ($matrix as $entityKey => $routes) {
            $this->assertIsArray($routes);
            foreach ($routes as $route) {
                $this->assertArrayHasKey('route', $route);
                $this->assertArrayHasKey('method', $route);
                $this->assertArrayHasKey('action_type', $route);
                $this->assertArrayHasKey('backing', $route);
                $this->assertArrayHasKey('notes', $route);
                $this->assertArrayHasKey('next_step', $route);
            }
        }
    }

    public function testDailyOrderRoutesHaveCorrectBacking(): void
    {
        $matrix = RuntimeReportInspector::generateRouteMigrationMatrix();
        $routes = $matrix['DailyOrder'];

        $createRoute = null;
        $updateRoute = null;
        $deleteRoute = null;
        foreach ($routes as $route) {
            if ($route['action_type'] === 'create') {
                $createRoute = $route;
            }
            if ($route['action_type'] === 'update') {
                $updateRoute = $route;
            }
            if ($route['action_type'] === 'delete') {
                $deleteRoute = $route;
            }
        }
        $this->assertNotNull($createRoute);
        $this->assertEquals(RuntimeReportInspector::MIGRATION_ENTITY_RUNTIME, $createRoute['backing']);
        
        $this->assertNotNull($updateRoute);
        $this->assertEquals(RuntimeReportInspector::MIGRATION_ENTITY_RUNTIME, $updateRoute['backing']);
        
        $this->assertNotNull($deleteRoute);
        $this->assertEquals(RuntimeReportInspector::MIGRATION_ENTITY_RUNTIME, $deleteRoute['backing']);
    }

    public function testProductionEntryRoutesHaveCorrectBacking(): void
    {
        $matrix = RuntimeReportInspector::generateRouteMigrationMatrix();
        $routes = $matrix['ProductionEntry'];

        $createRoute = null;
        foreach ($routes as $route) {
            if ($route['action_type'] === 'create') {
                $createRoute = $route;
                break;
            }
        }
        $this->assertNotNull($createRoute);
        $this->assertEquals(RuntimeReportInspector::MIGRATION_ENTITY_RUNTIME, $createRoute['backing']);
    }

    public function testProductionPlanUpdateRouteIsEntityRuntime(): void
    {
        $matrix = RuntimeReportInspector::generateRouteMigrationMatrix();
        $routes = $matrix['ProductionPlan'];

        $updateRoute = null;
        foreach ($routes as $route) {
            if ($route['action_type'] === 'update') {
                $updateRoute = $route;
                break;
            }
        }
        $this->assertNotNull($updateRoute);
        $this->assertEquals(RuntimeReportInspector::MIGRATION_ENTITY_RUNTIME, $updateRoute['backing']);
        $this->assertStringContainsString('ProductionPlanService::update()', $updateRoute['notes'][0]);
    }

    public function testProductionPlanRoutesAreFullyMigrated(): void
    {
        $matrix = RuntimeReportInspector::generateRouteMigrationMatrix();
        $routes = $matrix['ProductionPlan'];

        $fullyMigratedRoutes = ['create', 'update', 'workflow_transition', 'delete'];
        foreach ($routes as $route) {
            $actionType = $route['action_type'];
            if (in_array($actionType, $fullyMigratedRoutes, true)) {
                $this->assertEquals(
                    RuntimeReportInspector::MIGRATION_ENTITY_RUNTIME,
                    $route['backing'],
                    "ProductionPlan route '{$actionType}' should be entity_runtime"
                );
            }
        }
    }

    public function testDispatchEntryHasSplitBackingForApprovalAction(): void
    {
        $matrix = RuntimeReportInspector::generateRouteMigrationMatrix();
        $routes = $matrix['DispatchEntry'];

        $approvalRoute = null;
        foreach ($routes as $route) {
            if ($route['action_type'] === 'governance_transition') {
                $approvalRoute = $route;
                break;
            }
        }
        $this->assertNotNull($approvalRoute);
        $this->assertEquals(RuntimeReportInspector::MIGRATION_SPLIT, $approvalRoute['backing']);
    }

    public function testDispatchEntryHasEntityRuntimeForLifecycleTransition(): void
    {
        $matrix = RuntimeReportInspector::generateRouteMigrationMatrix();
        $routes = $matrix['DispatchEntry'];

        $lifecycleRoute = null;
        foreach ($routes as $route) {
            if ($route['action_type'] === 'lifecycle_transition') {
                $lifecycleRoute = $route;
                break;
            }
        }
        $this->assertNotNull($lifecycleRoute);
        $this->assertEquals(RuntimeReportInspector::MIGRATION_ENTITY_RUNTIME, $lifecycleRoute['backing']);
    }

    public function testAssemblyPlanHasNoRoutes(): void
    {
        $matrix = RuntimeReportInspector::generateRouteMigrationMatrix();
        $routes = $matrix['AssemblyPlan'];

        $this->assertCount(1, $routes);
        $this->assertEquals('no_routes', $routes[0]['action_type']);
        $this->assertEquals(RuntimeReportInspector::MIGRATION_NOT_APPLICABLE, $routes[0]['backing']);
    }

    public function testGetBackingLabelReturnsCorrectLabels(): void
    {
        $this->assertEquals('Entity Runtime', RuntimeReportInspector::getBackingLabel(RuntimeReportInspector::MIGRATION_ENTITY_RUNTIME));
        $this->assertEquals('Split (Runtime + Side Effects)', RuntimeReportInspector::getBackingLabel(RuntimeReportInspector::MIGRATION_SPLIT));
        $this->assertEquals('Legacy/Manual', RuntimeReportInspector::getBackingLabel(RuntimeReportInspector::MIGRATION_LEGACY));
        $this->assertEquals('N/A', RuntimeReportInspector::getBackingLabel(RuntimeReportInspector::MIGRATION_NOT_APPLICABLE));
    }

    public function testGetBackingBadgeClassReturnsCorrectClasses(): void
    {
        $this->assertEquals('badge-success', RuntimeReportInspector::getBackingBadgeClass(RuntimeReportInspector::MIGRATION_ENTITY_RUNTIME));
        $this->assertEquals('badge-warning', RuntimeReportInspector::getBackingBadgeClass(RuntimeReportInspector::MIGRATION_SPLIT));
        $this->assertEquals('badge-danger', RuntimeReportInspector::getBackingBadgeClass(RuntimeReportInspector::MIGRATION_LEGACY));
        $this->assertEquals('badge-secondary', RuntimeReportInspector::getBackingBadgeClass(RuntimeReportInspector::MIGRATION_NOT_APPLICABLE));
    }

    public function testExportTextContainsRouteMigrationMatrix(): void
    {
        $report = RuntimeReportInspector::generateReport();
        $export = RuntimeReportInspector::generateExportText($report);

        $this->assertStringContainsString('ROUTE-LEVEL MIGRATION MATRIX', $export);
    }

    public function testProductionPlanAndQCEntryWorkflowTransitionsAreEntityRuntime(): void
    {
        $matrix = RuntimeReportInspector::generateRouteMigrationMatrix();

        $ppRoutes = $matrix['ProductionPlan'];
        foreach ($ppRoutes as $route) {
            if ($route['action_type'] === 'workflow_transition') {
                $this->assertEquals(RuntimeReportInspector::MIGRATION_ENTITY_RUNTIME, $route['backing']);
            }
        }

        $qcRoutes = $matrix['QCEntry'];
        foreach ($qcRoutes as $route) {
            if ($route['action_type'] === 'workflow_transition') {
                $this->assertEquals(RuntimeReportInspector::MIGRATION_ENTITY_RUNTIME, $route['backing']);
            }
        }
    }

    public function testDailyOrderAndProductionEntryDeleteAreEntityRuntime(): void
    {
        $matrix = RuntimeReportInspector::generateRouteMigrationMatrix();

        $doRoutes = $matrix['DailyOrder'];
        foreach ($doRoutes as $route) {
            if ($route['action_type'] === 'delete') {
                $this->assertEquals(RuntimeReportInspector::MIGRATION_ENTITY_RUNTIME, $route['backing']);
            }
        }

        $peRoutes = $matrix['ProductionEntry'];
        foreach ($peRoutes as $route) {
            if ($route['action_type'] === 'delete') {
                $this->assertEquals(RuntimeReportInspector::MIGRATION_ENTITY_RUNTIME, $route['backing']);
            }
        }
    }

    public function testQCEntryDeleteIsEntityRuntime(): void
    {
        $matrix = RuntimeReportInspector::generateRouteMigrationMatrix();
        $qcRoutes = $matrix['QCEntry'];

        foreach ($qcRoutes as $route) {
            if ($route['action_type'] === 'delete') {
                $this->assertEquals(RuntimeReportInspector::MIGRATION_ENTITY_RUNTIME, $route['backing']);
            }
        }
    }

    public function testQCEntryUpdateRouteIsEntityRuntime(): void
    {
        $matrix = RuntimeReportInspector::generateRouteMigrationMatrix();
        $qcRoutes = $matrix['QCEntry'];

        $updateRoute = null;
        foreach ($qcRoutes as $route) {
            if ($route['action_type'] === 'update') {
                $updateRoute = $route;
                break;
            }
        }
        $this->assertNotNull($updateRoute);
        $this->assertEquals(RuntimeReportInspector::MIGRATION_ENTITY_RUNTIME, $updateRoute['backing']);
        $this->assertStringContainsString('QCEntryService::update()', $updateRoute['notes'][0]);
    }

    public function testQCEntryCreateRouteIsEntityRuntime(): void
    {
        $matrix = RuntimeReportInspector::generateRouteMigrationMatrix();
        $qcRoutes = $matrix['QCEntry'];

        $createRoute = null;
        foreach ($qcRoutes as $route) {
            if ($route['action_type'] === 'create') {
                $createRoute = $route;
                break;
            }
        }
        $this->assertNotNull($createRoute);
        $this->assertEquals(RuntimeReportInspector::MIGRATION_ENTITY_RUNTIME, $createRoute['backing']);
        $this->assertStringContainsString('QCEntryService::create()', $createRoute['notes'][0]);
    }

    public function testProductionPlanDeleteIsEntityRuntime(): void
    {
        $matrix = RuntimeReportInspector::generateRouteMigrationMatrix();
        $ppRoutes = $matrix['ProductionPlan'];

        foreach ($ppRoutes as $route) {
            if ($route['action_type'] === 'delete') {
                $this->assertEquals(RuntimeReportInspector::MIGRATION_ENTITY_RUNTIME, $route['backing']);
            }
        }
    }

    public function testProductionPlanCreateIsEntityRuntime(): void
    {
        $matrix = RuntimeReportInspector::generateRouteMigrationMatrix();
        $ppRoutes = $matrix['ProductionPlan'];

        foreach ($ppRoutes as $route) {
            if ($route['action_type'] === 'create') {
                $this->assertEquals(RuntimeReportInspector::MIGRATION_ENTITY_RUNTIME, $route['backing']);
            }
        }
    }

    public function testDispatchEntryUpdateRouteIsEntityRuntime(): void
    {
        $matrix = RuntimeReportInspector::generateRouteMigrationMatrix();
        $deRoutes = $matrix['DispatchEntry'];

        $updateRoute = null;
        foreach ($deRoutes as $route) {
            if ($route['action_type'] === 'update') {
                $updateRoute = $route;
                break;
            }
        }
        $this->assertNotNull($updateRoute);
        $this->assertEquals(RuntimeReportInspector::MIGRATION_ENTITY_RUNTIME, $updateRoute['backing']);
        $this->assertStringContainsString('DispatchEntryService::update()', $updateRoute['notes'][0]);
    }

    public function testDispatchEntryCreateRouteIsEntityRuntime(): void
    {
        $matrix = RuntimeReportInspector::generateRouteMigrationMatrix();
        $deRoutes = $matrix['DispatchEntry'];

        $createRoute = null;
        foreach ($deRoutes as $route) {
            if ($route['action_type'] === 'create') {
                $createRoute = $route;
                break;
            }
        }
        $this->assertNotNull($createRoute);
        $this->assertEquals(RuntimeReportInspector::MIGRATION_ENTITY_RUNTIME, $createRoute['backing']);
        $this->assertStringContainsString('DispatchEntryService::create()', $createRoute['notes'][0]);
    }

    public function testDispatchEntryDeleteRouteIsEntityRuntime(): void
    {
        $matrix = RuntimeReportInspector::generateRouteMigrationMatrix();
        $deRoutes = $matrix['DispatchEntry'];

        $deleteRoute = null;
        foreach ($deRoutes as $route) {
            if ($route['action_type'] === 'delete') {
                $deleteRoute = $route;
                break;
            }
        }
        $this->assertNotNull($deleteRoute);
        $this->assertEquals(RuntimeReportInspector::MIGRATION_ENTITY_RUNTIME, $deleteRoute['backing']);
        $this->assertStringContainsString('DispatchEntryService::delete()', $deleteRoute['notes'][0]);
    }

    public function testStartDraftRoutesAreEntityRuntimeForMigratedEntities(): void
    {
        $matrix = RuntimeReportInspector::generateRouteMigrationMatrix();

        $productionPlanDraftRoute = null;
        foreach ($matrix['ProductionPlan'] as $route) {
            if ($route['action_type'] === 'start_draft') {
                $productionPlanDraftRoute = $route;
                break;
            }
        }
        $this->assertNotNull($productionPlanDraftRoute);
        $this->assertEquals(RuntimeReportInspector::MIGRATION_ENTITY_RUNTIME, $productionPlanDraftRoute['backing']);

        $qcDraftRoute = null;
        foreach ($matrix['QCEntry'] as $route) {
            if ($route['action_type'] === 'start_draft') {
                $qcDraftRoute = $route;
                break;
            }
        }
        $this->assertNotNull($qcDraftRoute);
        $this->assertEquals(RuntimeReportInspector::MIGRATION_ENTITY_RUNTIME, $qcDraftRoute['backing']);

        $dispatchDraftRoute = null;
        foreach ($matrix['DispatchEntry'] as $route) {
            if ($route['action_type'] === 'start_draft') {
                $dispatchDraftRoute = $route;
                break;
            }
        }
        $this->assertNotNull($dispatchDraftRoute);
        $this->assertEquals(RuntimeReportInspector::MIGRATION_ENTITY_RUNTIME, $dispatchDraftRoute['backing']);
    }

    public function testDispatchQuickStatusIsIntentionalSplit(): void
    {
        $matrix = RuntimeReportInspector::generateRouteMigrationMatrix();
        $quickStatusRoute = null;

        foreach ($matrix['DispatchEntry'] as $route) {
            if ($route['action_type'] === 'quick_status_update') {
                $quickStatusRoute = $route;
                break;
            }
        }

        $this->assertNotNull($quickStatusRoute);
        $this->assertEquals(RuntimeReportInspector::MIGRATION_SPLIT, $quickStatusRoute['backing']);
        $this->assertStringContainsString('Intentional split architecture', $quickStatusRoute['next_step']);
    }
}

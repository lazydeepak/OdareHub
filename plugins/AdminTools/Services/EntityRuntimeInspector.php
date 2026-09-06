<?php
declare(strict_types=1);

namespace Plugins\AdminTools\Services;

use App\Core\EntityRegistry;

final class EntityRuntimeInspector
{
    public static function inspect(): array
    {
        $entities = EntityRegistry::all();
        $result = [];

        foreach ($entities as $key => $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $entityInfo = [
                'key' => $key,
                'module' => self::detectModule($key),
                'workflow' => self::extractWorkflowInfo($definition),
                'fields' => self::extractFields($definition),
                'hooks' => self::extractHooks($definition),
                'permissions' => self::extractPermissions($definition),
                'sla_config' => self::detectSlaConfig($key),
                'has_service' => self::hasEntityService($key),
                'action_classification' => self::classifyActions($key),
            ];

            $result[$key] = $entityInfo;
        }

        return $result;
    }

    private static function detectModule(string $key): string
    {
        $moduleMap = [
            'DailyOrder' => 'DailyOrders',
            'ProductionEntry' => 'ProductionEntries',
            'ProductionPlan' => 'ProductionPlans',
            'QCEntry' => 'QCEntries',
            'DispatchEntry' => 'DispatchEntries',
            'AssemblyPlan' => 'AssemblyPlans',
            'AssemblyEntry' => 'AssemblyEntries',
        ];

        return $moduleMap[$key] ?? 'Unknown';
    }

    private static function extractWorkflowInfo(array $definition): array
    {
        $workflow = (array)($definition['workflow'] ?? []);

        return [
            'field' => (string)($workflow['field'] ?? 'status'),
            'states' => (array)($workflow['states'] ?? []),
            'transitions' => (array)($workflow['transitions'] ?? []),
        ];
    }

    private static function extractFields(array $definition): array
    {
        $fields = (array)($definition['fields'] ?? []);
        $fieldNames = array_keys($fields);

        $statusFields = array_filter($fieldNames, static function (string $name): bool {
            return in_array(strtolower($name), ['status', 'dispatch_status', 'approval_status', 'workflow_state'], true);
        });

        return [
            'count' => count($fieldNames),
            'status_fields' => array_values($statusFields),
        ];
    }

    private static function extractHooks(array $definition): array
    {
        $hooks = (array)($definition['hooks'] ?? []);
        $hookNames = array_keys($hooks);

        return [
            'count' => count($hookNames),
            'types' => array_values($hookNames),
        ];
    }

    private static function extractPermissions(array $definition): array
    {
        $permissions = (array)($definition['permissions'] ?? []);
        $rules = (array)($permissions['rules'] ?? []);

        return [
            'has_can_edit' => isset($rules['can_edit_row']),
        ];
    }

    private static function detectSlaConfig(string $key): ?string
    {
        $slaMap = [
            'DailyOrder' => 'ENTITY_DAILY_ORDER',
            'ProductionEntry' => 'ENTITY_PRODUCTION_ENTRY',
            'ProductionPlan' => 'ENTITY_PRODUCTION_PLAN',
            'QCEntry' => 'ENTITY_QC_ENTRY',
            'DispatchEntry' => 'ENTITY_DISPATCH_ENTRY',
            'AssemblyPlan' => 'ENTITY_ASSEMBLY_PLAN',
            'AssemblyEntry' => 'ENTITY_ASSEMBLY_ENTRY',
        ];

        return $slaMap[$key] ?? null;
    }

    private static function hasEntityService(string $key): bool
    {
        $serviceMap = [
            'DailyOrder' => '\Plugins\DailyOrders\DailyOrderService',
            'ProductionEntry' => '\Plugins\ProductionEntries\ProductionEntryService',
            'ProductionPlan' => '\Plugins\ProductionPlans\ProductionPlanService',
            'QCEntry' => '\Plugins\QCEntries\QCEntryService',
            'DispatchEntry' => '\Plugins\DispatchEntries\DispatchEntryService',
            'AssemblyPlan' => '\Apps\Manufacturing\Modules\AssemblyPlans\AssemblyPlanService',
            'AssemblyEntry' => '\Apps\Manufacturing\Modules\AssemblyEntries\AssemblyEntryService',
        ];

        if (!isset($serviceMap[$key])) {
            return false;
        }

        $serviceClass = $serviceMap[$key];
        return class_exists($serviceClass);
    }

    private static function classifyActions(string $key): ?array
    {
        if ($key !== 'DispatchEntry') {
            return null;
        }

        return [
            'entity_lifecycle' => ['submit', 'approve', 'finalize', 'hold', 'resume', 'cancel', 'reopen'],
            'governance_only' => ['reject'],
            'operational' => ['handoff'],
        ];
    }
}

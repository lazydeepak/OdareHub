<?php
declare(strict_types=1);

namespace App\Core;

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

    public static function detectModule(string $key): string
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

    public static function extractWorkflowInfo(array $definition): array
    {
        $workflow = (array)($definition['workflow'] ?? []);

        return [
            'field' => (string)($workflow['field'] ?? 'status'),
            'states' => (array)($workflow['states'] ?? []),
            'transitions' => (array)($workflow['transitions'] ?? []),
        ];
    }

    public static function extractFields(array $definition): array
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

    public static function extractHooks(array $definition): array
    {
        $hooks = (array)($definition['hooks'] ?? []);
        $hookNames = array_keys($hooks);

        return [
            'count' => count($hookNames),
            'types' => array_values($hookNames),
        ];
    }

    public static function extractPermissions(array $definition): array
    {
        $permissions = (array)($definition['permissions'] ?? []);
        $rules = (array)($permissions['rules'] ?? []);

        return [
            'has_can_edit' => isset($rules['can_edit_row']),
        ];
    }

    public static function detectSlaConfig(string $key): ?string
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

    public static function hasEntityService(string $key): bool
    {
        $serviceMap = [
            'DailyOrder' => 'Plugins\\DailyOrders\\DailyOrderService',
            'ProductionEntry' => 'Plugins\\ProductionEntries\\ProductionEntryService',
            'ProductionPlan' => 'Plugins\\ProductionPlans\\ProductionPlanService',
            'QCEntry' => 'Plugins\\QCEntries\\QCEntryService',
            'DispatchEntry' => 'Plugins\\DispatchEntries\\DispatchEntryService',
            'AssemblyPlan' => 'Apps\\Manufacturing\\Modules\\AssemblyPlans\\AssemblyPlanService',
            'AssemblyEntry' => 'Apps\\Manufacturing\\Modules\\AssemblyEntries\\AssemblyEntryService',
        ];

        if (!isset($serviceMap[$key])) {
            return false;
        }

        $serviceClass = $serviceMap[$key];
        return class_exists('\\' . $serviceClass);
    }

    public static function classifyActions(string $key): ?array
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

    public static function dryRunTransition(string $entityKey, string $currentState, string $targetState, ?string $action = null): array
    {
        $guard = new EntityWorkflowGuard();

        $workflowField = $guard->getWorkflowField($entityKey);
        $allowedStates = $guard->getAllowedTransitions($entityKey, $currentState);
        $allStates = array_keys($guard->getStates($entityKey));
        $canTransition = $guard->canTransition($entityKey, $currentState, $targetState);

        $actionCategory = null;
        if ($action !== null && $entityKey === 'DispatchEntry') {
            $classification = self::classifyActions($entityKey);
            if ($classification !== null) {
                foreach ($classification as $category => $actions) {
                    if (in_array($action, $actions, true)) {
                        $actionCategory = $category;
                        break;
                    }
                }
            }
        }

        $reason = null;
        if (!EntityRegistry::has($entityKey)) {
            $reason = "Unknown entity key: {$entityKey}";
        } elseif ($canTransition) {
            $reason = "Transition is allowed";
        } else {
            $reason = $targetState === $currentState
                ? "Target state is same as current state"
                : "Target state '{$targetState}' is not in allowed transitions from '{$currentState}'";
        }

        return [
            'entity_key' => $entityKey,
            'workflow_field' => $workflowField,
            'current_state' => $currentState,
            'target_state' => $targetState,
            'action' => $action,
            'action_category' => $actionCategory,
            'allowed_next_states' => $allowedStates,
            'all_states' => $allStates,
            'allowed' => $canTransition,
            'reason' => $reason,
        ];
    }

    public static function getRegisteredEntityKeys(): array
    {
        return array_keys(EntityRegistry::all());
    }

    public static function inspectAction(string $entityKey, string $action, ?string $currentState = null): array
    {
        $guard = new EntityWorkflowGuard();
        $workflowField = $guard->getWorkflowField($entityKey);
        $allStates = array_keys($guard->getStates($entityKey));

        $allowedNextStates = [];
        if ($currentState !== null) {
            $allowedNextStates = $guard->getAllowedTransitions($entityKey, $currentState);
        }

        $mappedStatus = self::getMappedStatus($entityKey, $action);
        $actionCategory = self::getActionCategory($entityKey, $action);
        $recognized = $mappedStatus !== null;

        $allowed = false;
        $reason = null;
        if (!EntityRegistry::has($entityKey)) {
            $reason = "Unknown entity key: {$entityKey}";
        } elseif (!$recognized) {
            $reason = "Action '{$action}' is not mapped for {$entityKey}";
        } elseif ($currentState === null) {
            $reason = "No current state provided to validate transition";
        } else {
            $allowed = $guard->canTransition($entityKey, $currentState, $mappedStatus);
            $reason = $allowed
                ? "Transition allowed"
                : "Target '{$mappedStatus}' is not in allowed transitions from '{$currentState}'";
        }

        return [
            'entity_key' => $entityKey,
            'action' => $action,
            'action_recognized' => $recognized,
            'action_category' => $actionCategory,
            'mapped_status' => $mappedStatus,
            'workflow_field' => $workflowField,
            'current_state' => $currentState,
            'allowed_next_states' => $allowedNextStates,
            'all_states' => $allStates,
            'allowed_from_current' => $allowed,
            'reason' => $reason,
        ];
    }

    public static function getMappedStatus(string $entityKey, string $action): ?string
    {
        $serviceClass = match ($entityKey) {
            'DispatchEntry' => '\\Plugins\\DispatchEntries\\DispatchEntryService',
            'QCEntry' => '\\Plugins\\QCEntries\\QCEntryService',
            'ProductionPlan' => '\\Plugins\\ProductionPlans\\ProductionPlanService',
            default => null,
        };

        if ($serviceClass === null || !class_exists($serviceClass)) {
            return null;
        }

        return $serviceClass::actionToStatus($action);
    }

    public static function getActionCategory(string $entityKey, string $action): ?string
    {
        if ($entityKey !== 'DispatchEntry') {
            return null;
        }

        $classification = self::classifyActions($entityKey);
        if ($classification === null) {
            return null;
        }

        foreach ($classification as $category => $actions) {
            if (in_array(strtolower($action), $actions, true)) {
                return $category;
            }
        }

        return null;
    }

    public static function inspectRecord(string $entityKey, int $id, EntityContext $context, array $options = []): array
    {
        $entityKey = self::normalizeEntityKey($entityKey);
        $entityType = self::entityKeyToEntityType($entityKey);

        if ($entityKey === '' || $entityType === '') {
            return [
                'found' => false,
                'record' => null,
                'error' => 'unsupported_entity',
                'error_message' => "Entity key '{$entityKey}' is not supported for inspection.",
            ];
        }

        $record = self::fetchRecord($entityType, $id);

        if ($record === null) {
            return [
                'found' => false,
                'record' => null,
                'error' => 'not_found',
                'error_message' => "Record #{$id} not found for entity '{$entityKey}'.",
            ];
        }

        $guard = new EntityWorkflowGuard();
        $definition = EntityRegistry::get($entityKey);
        $workflow = $definition !== null ? self::extractWorkflowInfo($definition) : null;

        $workflowField = $workflow !== null ? ($workflow['field'] ?? 'status') : 'status';
        $lifecycleStatus = self::extractLifecycleStatus($record, $entityType, $workflowField);
        $approvalStatus = self::extractApprovalStatus($record, $entityType);
        $routingState = self::extractRoutingState($record, $entityType);

        $allStates = $workflow !== null ? array_keys($workflow['states'] ?? []) : [];
        $allowedNextStates = [];
        if ($lifecycleStatus !== null) {
            $allowedNextStates = $guard->getAllowedTransitions($entityKey, $lifecycleStatus);
        }

        $mappedActions = self::getMappedActionsForEntity($entityKey);

        $approvalAlignment = self::analyzeApprovalAlignment($lifecycleStatus, $approvalStatus, $entityType);
        $routingAlignment = self::analyzeRoutingAlignment($lifecycleStatus, $routingState, $entityType);

        $slaResult = null;
        $slaConfig = ManufacturingSlaConfig::get($entityKey);
        if (!empty($slaConfig)) {
            $slaConfigMerged = array_merge(['enabled' => true], $slaConfig);
            $slaResult = EntitySlaEngine::evaluate($slaConfigMerged, $record, $context);
        }

        $myWorkExplanation = null;
        if ($entityType !== '') {
            $myWorkExplanation = MyWorkInspector::explainItem(
                array_merge(['entity_type' => $entityType], $record),
                $context,
                $options
            );
        }

        return [
            'found' => true,
            'record' => $record,
            'error' => null,
            'error_message' => null,
            'entity_key' => $entityKey,
            'entity_type' => $entityType,
            'entity_id' => $id,
            'workflow_field' => $workflowField,
            'lifecycle_status' => $lifecycleStatus,
            'approval_status' => $approvalStatus,
            'routing_state' => $routingState,
            'all_workflow_states' => $allStates,
            'allowed_next_states' => $allowedNextStates,
            'mapped_actions' => $mappedActions,
            'action_classification' => self::classifyActions($entityKey),
            'approval_alignment' => $approvalAlignment,
            'routing_alignment' => $routingAlignment,
            'sla_result' => $slaResult,
            'sla_config' => !empty($slaConfig) ? $slaConfig : null,
            'my_work' => $myWorkExplanation,
            'detail_url' => self::buildRecordDetailUrl($entityType, $id),
        ];
    }

    private static function normalizeEntityKey(string $key): string
    {
        $map = [
            'DailyOrder' => 'DailyOrder',
            'ProductionEntry' => 'ProductionEntry',
            'ProductionPlan' => 'ProductionPlan',
            'QCEntry' => 'QCEntry',
            'DispatchEntry' => 'DispatchEntry',
            'AssemblyPlan' => 'AssemblyPlan',
            'AssemblyEntry' => 'AssemblyEntry',
            'daily_order' => 'DailyOrder',
            'production_entry' => 'ProductionEntry',
            'production_plan' => 'ProductionPlan',
            'qc_entry' => 'QCEntry',
            'dispatch_entry' => 'DispatchEntry',
            'assembly_plan' => 'AssemblyPlan',
            'assembly_entry' => 'AssemblyEntry',
        ];

        return $map[$key] ?? '';
    }

    private static function entityKeyToEntityType(string $entityKey): string
    {
        return match ($entityKey) {
            'DailyOrder' => 'daily_order',
            'ProductionEntry' => 'production_entry',
            'ProductionPlan' => 'production_plan',
            'QCEntry' => 'qc_entry',
            'DispatchEntry' => 'dispatch_entry',
            'AssemblyPlan' => 'assembly_plan',
            'AssemblyEntry' => 'assembly_entry',
            default => '',
        };
    }

    private static function fetchRecord(string $entityType, int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $table = match ($entityType) {
            'daily_order' => 'daily_orders',
            'production_entry' => 'production_entries',
            'production_plan' => 'production_plans',
            'qc_entry' => 'qc_entries',
            'dispatch_entry' => 'dispatch_entries',
            'assembly_plan' => 'assembly_plans',
            'assembly_entry' => 'assembly_entries',
            default => '',
        };

        if ($table === '') {
            return null;
        }

        try {
            $fields = self::getFieldsForEntity($entityType);
            $selectFields = implode(', ', $fields);
            $rows = DB::fetchAll(
                "SELECT {$selectFields} FROM {$table} WHERE id = ? LIMIT 1",
                [$id]
            );
            return $rows[0] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function getFieldsForEntity(string $entityType): array
    {
        return match ($entityType) {
            'daily_order' => ['id', 'qty', 'status', 'required_date', 'order_date', 'customer_name', 'product_id', 'updated_at', 'approval_status', 'locked_at'],
            'production_entry' => ['id', 'qty_produced', 'good_qty', 'rejected_qty', 'status', 'production_date', 'machine_id', 'product_id', 'shift', 'updated_at', 'approval_status', 'locked_at'],
            'production_plan' => ['id', 'planned_qty', 'status', 'plan_date', 'approval_status', 'locked_at', 'machine_id', 'product_id', 'sequence_no', 'runtime', 'plan_type', 'updated_at'],
            'qc_entry' => ['id', 'checked_qty', 'pass_qty', 'fail_qty', 'status', 'qc_type', 'approval_status', 'locked_at', 'product_id', 'remarks', 'updated_at'],
            'dispatch_entry' => ['id', 'dispatch_status', 'dispatchable_qty', 'dispatch_date', 'approval_status', 'locked_at', 'destination', 'dispatch_type', 'product_id', 'updated_at', 'status'],
            'assembly_plan' => ['id', 'planned_qty', 'status', 'demand_date', 'approval_status', 'locked_at', 'product_id', 'updated_at'],
            'assembly_entry' => ['id', 'status', 'assembly_date', 'approval_status', 'locked_at', 'product_id', 'updated_at'],
            default => ['id', 'status', 'updated_at'],
        };
    }

    private static function extractLifecycleStatus(array $record, string $entityType, string $workflowField): ?string
    {
        if ($workflowField === 'dispatch_status' || $entityType === 'dispatch_entry') {
            $val = $record['dispatch_status'] ?? $record['status'] ?? null;
            return $val !== null ? (string)$val : null;
        }

        if ($workflowField === 'status') {
            return isset($record['status']) ? (string)$record['status'] : null;
        }

        return isset($record[$workflowField]) ? (string)$record[$workflowField] : null;
    }

    private static function extractApprovalStatus(array $record, string $entityType): ?array
    {
        $approvalStatus = isset($record['approval_status']) ? (string)$record['approval_status'] : null;
        $lockedAt = isset($record['locked_at']) ? (string)$record['locked_at'] : null;

        if ($approvalStatus === null && $lockedAt === null) {
            return [
                'value' => null,
                'exists' => false,
                'locked' => false,
                'label' => 'No approval field',
            ];
        }

        return [
            'value' => $approvalStatus ?: null,
            'exists' => true,
            'locked' => $lockedAt !== null && $lockedAt !== '',
            'locked_at' => $lockedAt ?: null,
            'label' => self::normalizeApprovalLabel($approvalStatus),
        ];
    }

    private static function normalizeApprovalLabel(?string $status): string
    {
        if ($status === null || $status === '') {
            return 'None';
        }

        return match (strtolower(trim($status))) {
            'approved' => 'Approved',
            'pending approval', 'pending' => 'Pending Approval',
            'reopened' => 'Reopened',
            'draft' => 'Draft',
            'rejected' => 'Rejected',
            default => ucfirst(trim($status)),
        };
    }

    private static function extractRoutingState(array $record, string $entityType): ?array
    {
        $routingFields = ['routing_state', 'ownership_state', 'handoff_state', 'assignment_state'];

        foreach ($routingFields as $field) {
            if (isset($record[$field]) && $record[$field] !== null && (string)$record[$field] !== '') {
                return [
                    'field' => $field,
                    'value' => (string)$record[$field],
                    'exists' => true,
                ];
            }
        }

        return [
            'field' => null,
            'value' => null,
            'exists' => false,
        ];
    }

    private static function getMappedActionsForEntity(string $entityKey): array
    {
        $actions = [];
        $testActions = ['submit', 'approve', 'reject', 'finalize', 'hold', 'resume', 'cancel', 'reopen', 'handoff'];

        foreach ($testActions as $action) {
            $mappedStatus = self::getMappedStatus($entityKey, $action);
            if ($mappedStatus !== null) {
                $category = self::getActionCategory($entityKey, $action);
                $actions[$action] = [
                    'target_status' => $mappedStatus,
                    'category' => $category,
                ];
            }
        }

        return $actions;
    }

    private static function analyzeApprovalAlignment(?string $lifecycleStatus, ?array $approvalStatus, string $entityType): array
    {
        if ($approvalStatus === null || !$approvalStatus['exists']) {
            return [
                'has_approval' => false,
                'aligned' => true,
                'notes' => 'No approval field present for this entity type.',
            ];
        }

        $approval = strtolower(trim((string)($approvalStatus['value'] ?? '')));
        $lifecycle = strtolower(trim((string)($lifecycleStatus ?? '')));

        $aligned = true;
        $notes = [];

        if ($approval === 'approved' && !in_array($lifecycle, ['dispatched', 'completed', 'closed', 'finalized'], true)) {
            $aligned = false;
            $notes[] = "Entity is 'Approved' but lifecycle status is '{$lifecycle}' - may need lifecycle progression";
        }

        if (in_array($approval, ['pending approval', 'pending'], true) && in_array($lifecycle, ['submitted', 'ready'], true)) {
            $aligned = true;
            $notes[] = "Awaiting approval for lifecycle status '{$lifecycle}'";
        }

        if ($approval === 'rejected') {
            $aligned = false;
            $notes[] = "Record has been rejected - governance state conflicts with lifecycle";
        }

        return [
            'has_approval' => true,
            'aligned' => $aligned,
            'notes' => $notes ?: ['Lifecycle and approval states appear aligned.'],
        ];
    }

    private static function analyzeRoutingAlignment(?string $lifecycleStatus, ?array $routingState, string $entityType): array
    {
        if ($routingState === null || !$routingState['exists']) {
            return [
                'has_routing' => false,
                'aligned' => true,
                'notes' => 'No routing/handoff state field present for this entity.',
            ];
        }

        $routing = strtolower(trim((string)($routingState['value'] ?? '')));
        $lifecycle = strtolower(trim((string)($lifecycleStatus ?? '')));

        return [
            'has_routing' => true,
            'routing_field' => $routingState['field'],
            'routing_value' => $routingState['value'],
            'aligned' => true,
            'notes' => ['Routing state present but alignment check is informational only.'],
        ];
    }

    private static function buildRecordDetailUrl(string $entityType, int $id): string
    {
        if ($id <= 0) {
            return '#';
        }

        return match ($entityType) {
            'daily_order' => "/daily-orders/360?id={$id}",
            'production_entry' => "/production-entries/edit?id={$id}",
            'production_plan' => "/production-plans/edit?id={$id}",
            'qc_entry' => "/qc-entries/edit?id={$id}",
            'dispatch_entry' => "/dispatch-entries/edit?id={$id}",
            default => "#",
        };
    }

    public static function getSupportedEntityKeys(): array
    {
        return [
            'DailyOrder',
            'ProductionEntry',
            'ProductionPlan',
            'QCEntry',
            'DispatchEntry',
            'AssemblyPlan',
            'AssemblyEntry',
        ];
    }

    public static function getEntityKeyLabel(string $entityKey): string
    {
        return match ($entityKey) {
            'DailyOrder' => 'Daily Order',
            'ProductionEntry' => 'Production Entry',
            'ProductionPlan' => 'Production Plan',
            'QCEntry' => 'QC Entry',
            'DispatchEntry' => 'Dispatch Entry',
            'AssemblyPlan' => 'Assembly Plan',
            'AssemblyEntry' => 'Assembly Entry',
            default => ucfirst($entityKey),
        };
    }
}

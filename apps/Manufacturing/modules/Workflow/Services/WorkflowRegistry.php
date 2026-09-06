<?php
declare(strict_types=1);

namespace Plugins\Workflow\Services;

final class WorkflowRegistry
{
    public const ENTITY_PRODUCTION_PLAN = 'production_plan';
    public const ENTITY_ASSEMBLY_PLAN = 'assembly_plan';
    public const ENTITY_QC_ENTRY = 'qc_entry';
    public const ENTITY_DISPATCH_ENTRY = 'dispatch_entry';

    public const ACTION_CREATE = 'create';
    public const ACTION_SUBMIT = 'submit';
    public const ACTION_APPROVE = 'approve';
    public const ACTION_REJECT = 'reject';
    public const ACTION_REOPEN = 'reopen';
    public const ACTION_HOLD = 'hold';
    public const ACTION_RESUME = 'resume';
    public const ACTION_FINALIZE = 'finalize';
    public const ACTION_CANCEL = 'cancel';
    public const ACTION_HANDOFF = 'handoff';

    /**
     * @return array<int,string>
     */
    public static function entities(): array
    {
        return [
            self::ENTITY_PRODUCTION_PLAN,
            self::ENTITY_ASSEMBLY_PLAN,
            self::ENTITY_QC_ENTRY,
            self::ENTITY_DISPATCH_ENTRY,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function entity(string $entity): ?array
    {
        $registry = self::registry();
        return $registry[$entity] ?? null;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public static function registry(): array
    {
        return [
            self::ENTITY_PRODUCTION_PLAN => [
                'label' => 'Production Plan',
                'table' => 'production_plans',
                'id_column' => 'id',
                'state_column' => 'workflow_state',
                'app' => 'manufacturing',
                'module' => 'production',
                'audit_required' => true,
                'transitions' => [
                    self::ACTION_SUBMIT => ['from' => ['draft', 'reopened', 'rejected'], 'to' => 'submitted', 'permission' => 'workflow.production_plan.submit', 'approval_required' => false, 'reason_required' => false, 'note_required' => false, 'notifications' => true],
                    self::ACTION_APPROVE => ['from' => ['submitted'], 'to' => 'approved', 'permission' => 'workflow.production_plan.approve', 'approval_required' => true, 'reason_required' => false, 'note_required' => false, 'notifications' => true],
                    self::ACTION_REJECT => ['from' => ['submitted'], 'to' => 'rejected', 'permission' => 'workflow.production_plan.reject', 'approval_required' => true, 'reason_required' => true, 'note_required' => false, 'notifications' => true],
                    self::ACTION_REOPEN => ['from' => ['approved', 'rejected', 'finalized', 'cancelled', 'completed'], 'to' => 'reopened', 'permission' => 'workflow.production_plan.reopen', 'approval_required' => true, 'reason_required' => true, 'note_required' => false, 'notifications' => true],
                    self::ACTION_HOLD => ['from' => ['approved', 'in_progress'], 'to' => 'hold', 'permission' => 'workflow.production_plan.hold', 'approval_required' => false, 'reason_required' => true, 'note_required' => false, 'notifications' => true],
                    self::ACTION_RESUME => ['from' => ['hold'], 'to' => 'in_progress', 'permission' => 'workflow.production_plan.resume', 'approval_required' => false, 'reason_required' => false, 'note_required' => false, 'notifications' => true],
                    self::ACTION_FINALIZE => ['from' => ['approved', 'in_progress', 'completed'], 'to' => 'finalized', 'permission' => 'workflow.production_plan.finalize', 'approval_required' => true, 'reason_required' => false, 'note_required' => false, 'notifications' => true],
                    self::ACTION_CANCEL => ['from' => ['draft', 'submitted', 'hold'], 'to' => 'cancelled', 'permission' => 'workflow.production_plan.cancel', 'approval_required' => true, 'reason_required' => true, 'note_required' => false, 'notifications' => true],
                ],
            ],
            self::ENTITY_ASSEMBLY_PLAN => [
                'label' => 'Assembly Plan',
                'table' => 'mfg_part_demands',
                'id_column' => 'id',
                'state_column' => 'workflow_state',
                'app' => 'manufacturing',
                'module' => 'assembly',
                'audit_required' => true,
                'record_where_sql' => 'demand_type = \'assembly\'',
                'transitions' => [
                    self::ACTION_SUBMIT => ['from' => ['draft', 'reopened', 'rejected'], 'to' => 'submitted', 'permission' => 'workflow.assembly_plan.submit', 'approval_required' => false, 'reason_required' => false, 'note_required' => false, 'notifications' => true],
                    self::ACTION_APPROVE => ['from' => ['submitted'], 'to' => 'approved', 'permission' => 'workflow.assembly_plan.approve', 'approval_required' => true, 'reason_required' => false, 'note_required' => false, 'notifications' => true],
                    self::ACTION_REJECT => ['from' => ['submitted'], 'to' => 'rejected', 'permission' => 'workflow.assembly_plan.reject', 'approval_required' => true, 'reason_required' => true, 'note_required' => false, 'notifications' => true],
                    self::ACTION_REOPEN => ['from' => ['approved', 'rejected', 'finalized', 'completed'], 'to' => 'reopened', 'permission' => 'workflow.assembly_plan.reopen', 'approval_required' => true, 'reason_required' => true, 'note_required' => false, 'notifications' => true],
                    self::ACTION_HOLD => ['from' => ['approved', 'in_progress'], 'to' => 'hold', 'permission' => 'workflow.assembly_plan.hold', 'approval_required' => false, 'reason_required' => true, 'note_required' => false, 'notifications' => true],
                    self::ACTION_RESUME => ['from' => ['hold'], 'to' => 'in_progress', 'permission' => 'workflow.assembly_plan.resume', 'approval_required' => false, 'reason_required' => false, 'note_required' => false, 'notifications' => true],
                    self::ACTION_FINALIZE => ['from' => ['approved', 'in_progress', 'completed'], 'to' => 'finalized', 'permission' => 'workflow.assembly_plan.finalize', 'approval_required' => true, 'reason_required' => false, 'note_required' => false, 'notifications' => true],
                    self::ACTION_CANCEL => ['from' => ['draft', 'submitted', 'hold'], 'to' => 'cancelled', 'permission' => 'workflow.assembly_plan.cancel', 'approval_required' => true, 'reason_required' => true, 'note_required' => false, 'notifications' => true],
                ],
            ],
            self::ENTITY_QC_ENTRY => [
                'label' => 'QC Entry',
                'table' => 'qc_entries',
                'id_column' => 'id',
                'state_column' => 'workflow_state',
                'app' => 'manufacturing',
                'module' => 'qc',
                'audit_required' => true,
                'transitions' => [
                    self::ACTION_SUBMIT => ['from' => ['draft', 'reopened', 'rejected'], 'to' => 'submitted', 'permission' => 'workflow.qc_entry.submit', 'approval_required' => false, 'reason_required' => false, 'note_required' => false, 'notifications' => true],
                    self::ACTION_APPROVE => ['from' => ['submitted'], 'to' => 'approved', 'permission' => 'workflow.qc_entry.approve', 'approval_required' => true, 'reason_required' => false, 'note_required' => false, 'notifications' => true],
                    self::ACTION_REJECT => ['from' => ['submitted'], 'to' => 'rejected', 'permission' => 'workflow.qc_entry.reject', 'approval_required' => true, 'reason_required' => true, 'note_required' => false, 'notifications' => true],
                    self::ACTION_REOPEN => ['from' => ['approved', 'rejected', 'finalized'], 'to' => 'reopened', 'permission' => 'workflow.qc_entry.reopen', 'approval_required' => true, 'reason_required' => true, 'note_required' => false, 'notifications' => true],
                    self::ACTION_FINALIZE => ['from' => ['approved'], 'to' => 'finalized', 'permission' => 'workflow.qc_entry.finalize', 'approval_required' => true, 'reason_required' => false, 'note_required' => false, 'notifications' => true],
                    self::ACTION_CANCEL => ['from' => ['draft', 'submitted', 'hold'], 'to' => 'cancelled', 'permission' => 'workflow.qc_entry.cancel', 'approval_required' => true, 'reason_required' => true, 'note_required' => false, 'notifications' => true],
                ],
            ],
            self::ENTITY_DISPATCH_ENTRY => [
                'label' => 'Dispatch Entry',
                'table' => 'dispatch_entries',
                'id_column' => 'id',
                'state_column' => 'workflow_state',
                'app' => 'manufacturing',
                'module' => 'dispatch',
                'audit_required' => true,
                'transitions' => [
                    self::ACTION_SUBMIT => ['from' => ['draft', 'reopened', 'rejected'], 'to' => 'submitted', 'permission' => 'workflow.dispatch_entry.submit', 'approval_required' => false, 'reason_required' => false, 'note_required' => false, 'notifications' => true],
                    self::ACTION_APPROVE => ['from' => ['submitted'], 'to' => 'approved', 'permission' => 'workflow.dispatch_entry.approve', 'approval_required' => true, 'reason_required' => false, 'note_required' => false, 'notifications' => true],
                    self::ACTION_REJECT => ['from' => ['submitted'], 'to' => 'rejected', 'permission' => 'workflow.dispatch_entry.reject', 'approval_required' => true, 'reason_required' => true, 'note_required' => false, 'notifications' => true],
                    self::ACTION_REOPEN => ['from' => ['approved', 'rejected', 'finalized'], 'to' => 'reopened', 'permission' => 'workflow.dispatch_entry.reopen', 'approval_required' => true, 'reason_required' => true, 'note_required' => false, 'notifications' => true],
                    self::ACTION_HOLD => ['from' => ['submitted', 'approved'], 'to' => 'hold', 'permission' => 'workflow.dispatch_entry.hold', 'approval_required' => false, 'reason_required' => true, 'note_required' => false, 'notifications' => true],
                    self::ACTION_RESUME => ['from' => ['hold'], 'to' => 'approved', 'permission' => 'workflow.dispatch_entry.resume', 'approval_required' => false, 'reason_required' => false, 'note_required' => false, 'notifications' => true],
                    self::ACTION_FINALIZE => ['from' => ['approved'], 'to' => 'finalized', 'permission' => 'workflow.dispatch_entry.finalize', 'approval_required' => true, 'reason_required' => false, 'note_required' => false, 'notifications' => true],
                    self::ACTION_CANCEL => ['from' => ['draft', 'submitted', 'approved', 'hold'], 'to' => 'cancelled', 'permission' => 'workflow.dispatch_entry.cancel', 'approval_required' => true, 'reason_required' => true, 'note_required' => false, 'notifications' => true],
                    self::ACTION_HANDOFF => ['from' => ['approved', 'in_progress'], 'to' => 'approved', 'permission' => 'workflow.dispatch_entry.handoff', 'approval_required' => false, 'reason_required' => false, 'note_required' => true, 'notifications' => true],
                ],
            ],
        ];
    }
}

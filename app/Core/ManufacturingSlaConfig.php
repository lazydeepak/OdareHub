<?php
declare(strict_types=1);

namespace App\Core;

class ManufacturingSlaConfig
{
    public const ENTITY_DAILY_ORDER = [
        'status_field' => 'status',
        'deadline_field' => 'required_date',
        'due_soon_minutes' => 120,
        'breach_labels' => [
            'ok' => 'On Track',
            'due_soon' => 'Due Soon',
            'breached' => 'Overdue',
        ],
        'rules' => [
            'Open' => ['escalation' => false],
            'InProgress' => ['escalation' => true],
        ],
    ];

    public const ENTITY_PRODUCTION_ENTRY = [
        'status_field' => 'status',
        'deadline_field' => 'production_date',
        'due_soon_minutes' => 240,
        'breach_labels' => [
            'ok' => 'On Track',
            'due_soon' => 'Due Soon',
            'breached' => 'Breached',
        ],
        'rules' => [
            'Draft' => ['escalation' => false],
            'Open' => ['escalation' => true],
        ],
    ];

    public const ENTITY_PRODUCTION_PLAN = [
        'status_field' => 'status',
        'deadline_field' => 'plan_date',
        'due_soon_minutes' => 180,
        'breach_labels' => [
            'ok' => 'On Track',
            'due_soon' => 'Due Soon',
            'breached' => 'Overdue',
        ],
        'rules' => [
            'Planned' => ['escalation' => false],
            'InProgress' => ['escalation' => true],
        ],
    ];

    public const ENTITY_QC_ENTRY = [
        'status_field' => 'status',
        'deadline_field' => 'updated_at',
        'due_soon_minutes' => 60,
        'breach_labels' => [
            'ok' => 'On Track',
            'due_soon' => 'Due Soon',
            'breached' => 'Overdue',
        ],
        'rules' => [
            'Draft' => ['escalation' => false],
            'Open' => ['escalation' => true],
        ],
    ];

    public const ENTITY_DISPATCH_ENTRY = [
        'status_field' => 'status',
        'deadline_field' => 'dispatch_date',
        'due_soon_minutes' => 90,
        'breach_labels' => [
            'ok' => 'On Track',
            'due_soon' => 'Due Soon',
            'breached' => 'Overdue',
        ],
        'rules' => [
            'Draft' => ['escalation' => false],
            'Ready' => ['escalation' => true],
        ],
    ];

    public const ENTITY_ASSEMBLY_PLAN = [
        'status_field' => 'status',
        'deadline_field' => 'demand_date',
        'due_soon_minutes' => 120,
        'breach_labels' => [
            'ok' => 'On Track',
            'due_soon' => 'Due Soon',
            'breached' => 'Overdue',
        ],
        'rules' => [
            'calculated' => ['escalation' => false],
            'adjusted' => ['escalation' => true],
            'approved' => ['escalation' => true],
        ],
    ];

    public const ENTITY_ASSEMBLY_ENTRY = [
        'status_field' => 'status',
        'deadline_field' => 'assembly_date',
        'due_soon_minutes' => 60,
        'breach_labels' => [
            'ok' => 'On Track',
            'due_soon' => 'Due Soon',
            'breached' => 'Overdue',
        ],
        'rules' => [
            'draft' => ['escalation' => false],
            'in_progress' => ['escalation' => true],
        ],
    ];

    public static function get(string $entityKey): array
    {
        return match ($entityKey) {
            'DailyOrder' => self::ENTITY_DAILY_ORDER,
            'ProductionEntry' => self::ENTITY_PRODUCTION_ENTRY,
            'ProductionPlan' => self::ENTITY_PRODUCTION_PLAN,
            'QCEntry' => self::ENTITY_QC_ENTRY,
            'DispatchEntry' => self::ENTITY_DISPATCH_ENTRY,
            'AssemblyPlan' => self::ENTITY_ASSEMBLY_PLAN,
            'AssemblyEntry' => self::ENTITY_ASSEMBLY_ENTRY,
            default => [],
        };
    }

    public static function enrichItem(string $entityKey, array $item, EntityPolicyResolver $policyResolver, EntityContext $context): array
    {
        $config = self::get($entityKey);
        if (empty($config)) {
            return $item;
        }

        $slaConfig = array_merge(['enabled' => true], $config);
        $slaResult = EntitySlaEngine::evaluate($slaConfig, $item, $context);

        $item['sla_state'] = $slaResult['state'];
        $item['sla_label'] = $slaResult['label'] ?? 'On Track';
        $item['sla_deadline'] = $slaResult['deadline_at'];
        $item['sla_escalated'] = $slaResult['escalated'];
        $item['sla_age_seconds'] = $slaResult['age_seconds'];

        $canView = $policyResolver->canViewRow($entityKey, $item, $context);
        $item['can_view'] = $canView;
        $item['visibility_source'] = 'entity_runtime';

        return $item;
    }
}

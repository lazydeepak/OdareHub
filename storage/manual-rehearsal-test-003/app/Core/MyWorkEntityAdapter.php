<?php
declare(strict_types=1);

namespace App\Core;

class MyWorkEntityAdapter
{
    private static ?EntityPolicyResolver $policyResolver = null;

    private static function getPolicyResolver(): EntityPolicyResolver
    {
        if (self::$policyResolver === null) {
            self::$policyResolver = new EntityPolicyResolver();
        }
        return self::$policyResolver;
    }

    public static function getDailyOrderSlaConfig(): array
    {
        return array_merge(['enabled' => true], ManufacturingSlaConfig::ENTITY_DAILY_ORDER);
    }

    public static function getProductionEntrySlaConfig(): array
    {
        return array_merge(['enabled' => true], ManufacturingSlaConfig::ENTITY_PRODUCTION_ENTRY);
    }

    public static function getProductionPlanSlaConfig(): array
    {
        return array_merge(['enabled' => true], ManufacturingSlaConfig::ENTITY_PRODUCTION_PLAN);
    }

    public static function getQCEntrySlaConfig(): array
    {
        return array_merge(['enabled' => true], ManufacturingSlaConfig::ENTITY_QC_ENTRY);
    }

    public static function getDispatchEntrySlaConfig(): array
    {
        return array_merge(['enabled' => true], ManufacturingSlaConfig::ENTITY_DISPATCH_ENTRY);
    }

    public static function getAssemblyPlanSlaConfig(): array
    {
        return array_merge(['enabled' => true], ManufacturingSlaConfig::ENTITY_ASSEMBLY_PLAN);
    }

    public static function getAssemblyEntrySlaConfig(): array
    {
        return array_merge(['enabled' => true], ManufacturingSlaConfig::ENTITY_ASSEMBLY_ENTRY);
    }

    private static function enrichEntityItem(string $entityKey, array $item, EntityContext $context): array
    {
        return ManufacturingSlaConfig::enrichItem($entityKey, $item, self::getPolicyResolver(), $context);
    }

    public static function enrichDailyOrderItem(array $item, EntityContext $context): array
    {
        return self::enrichEntityItem('DailyOrder', $item, $context);
    }

    public static function enrichProductionEntryItem(array $item, EntityContext $context): array
    {
        return self::enrichEntityItem('ProductionEntry', $item, $context);
    }

    public static function enrichProductionPlanItem(array $item, EntityContext $context): array
    {
        return self::enrichEntityItem('ProductionPlan', $item, $context);
    }

    public static function enrichQCEntryItem(array $item, EntityContext $context): array
    {
        return self::enrichEntityItem('QCEntry', $item, $context);
    }

    public static function enrichDispatchEntryItem(array $item, EntityContext $context): array
    {
        return self::enrichEntityItem('DispatchEntry', $item, $context);
    }

    public static function enrichAssemblyPlanItem(array $item, EntityContext $context): array
    {
        return self::enrichEntityItem('AssemblyPlan', $item, $context);
    }

    public static function enrichAssemblyEntryItem(array $item, EntityContext $context): array
    {
        return self::enrichEntityItem('AssemblyEntry', $item, $context);
    }

    public static function enrichDailyOrderItems(array $items, EntityContext $context): array
    {
        foreach ($items as &$item) {
            if (($item['entity_type'] ?? '') === 'daily_order') {
                $item = self::enrichDailyOrderItem($item, $context);
            }
        }
        return $items;
    }

    public static function enrichProductionEntryItems(array $items, EntityContext $context): array
    {
        foreach ($items as &$item) {
            if (($item['entity_type'] ?? '') === 'production_entry') {
                $item = self::enrichProductionEntryItem($item, $context);
            }
        }
        return $items;
    }

    public static function enrichProductionPlanItems(array $items, EntityContext $context): array
    {
        foreach ($items as &$item) {
            if (($item['entity_type'] ?? '') === 'production_plan') {
                $item = self::enrichProductionPlanItem($item, $context);
            }
        }
        return $items;
    }

    public static function enrichQCEntryItems(array $items, EntityContext $context): array
    {
        foreach ($items as &$item) {
            if (($item['entity_type'] ?? '') === 'qc_entry') {
                $item = self::enrichQCEntryItem($item, $context);
            }
        }
        return $items;
    }

    public static function enrichDispatchEntryItems(array $items, EntityContext $context): array
    {
        foreach ($items as &$item) {
            if (($item['entity_type'] ?? '') === 'dispatch_entry') {
                $item = self::enrichDispatchEntryItem($item, $context);
            }
        }
        return $items;
    }

    public static function enrichAssemblyPlanItems(array $items, EntityContext $context): array
    {
        foreach ($items as &$item) {
            if (($item['entity_type'] ?? '') === 'assembly_plan') {
                $item = self::enrichAssemblyPlanItem($item, $context);
            }
        }
        return $items;
    }

    public static function enrichAssemblyEntryItems(array $items, EntityContext $context): array
    {
        foreach ($items as &$item) {
            if (($item['entity_type'] ?? '') === 'assembly_entry') {
                $item = self::enrichAssemblyEntryItem($item, $context);
            }
        }
        return $items;
    }

    public static function enrichItems(array $items, EntityContext $context): array
    {
        foreach ($items as &$item) {
            $entityType = strtolower((string)($item['entity_type'] ?? ''));
            if ($entityType === 'daily_order') {
                $item = self::enrichDailyOrderItem($item, $context);
            } elseif ($entityType === 'production_entry') {
                $item = self::enrichProductionEntryItem($item, $context);
            } elseif ($entityType === 'production_plan') {
                $item = self::enrichProductionPlanItem($item, $context);
            } elseif ($entityType === 'qc_entry') {
                $item = self::enrichQCEntryItem($item, $context);
            } elseif ($entityType === 'dispatch_entry') {
                $item = self::enrichDispatchEntryItem($item, $context);
            } elseif ($entityType === 'assembly_plan') {
                $item = self::enrichAssemblyPlanItem($item, $context);
            } elseif ($entityType === 'assembly_entry') {
                $item = self::enrichAssemblyEntryItem($item, $context);
            }
        }
        return $items;
    }

    public static function isSlaBreached(array $item): bool
    {
        return ($item['sla_state'] ?? '') === 'breached';
    }

    public static function isSlaEscalated(array $item): bool
    {
        return ($item['sla_escalated'] ?? false) === true;
    }

    public static function isVisible(array $item): bool
    {
        if (!isset($item['visibility_source'])) {
            return true;
        }
        return ($item['can_view'] ?? true) === true;
    }

    public static function filterBreachedItems(array $items): array
    {
        return array_values(array_filter($items, [self::class, 'isSlaBreached']));
    }

    public static function filterVisibleItems(array $items): array
    {
        return array_values(array_filter($items, [self::class, 'isVisible']));
    }

    public static function getSlaUrgencyRank(array $item): int
    {
        $state = $item['sla_state'] ?? '';
        if (($item['sla_escalated'] ?? false) === true) {
            return 0;
        }
        if ($state === 'breached') {
            return 1;
        }
        if ($state === 'due_soon') {
            return 2;
        }
        return 9;
    }
}

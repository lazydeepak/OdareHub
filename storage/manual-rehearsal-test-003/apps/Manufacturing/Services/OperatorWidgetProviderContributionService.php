<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services;

final class OperatorWidgetProviderContributionService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function providerCatalog(): array
    {
        return [
            ['provider_name' => 'Coverage', 'module_key' => 'Coverage', 'provider_key' => 'coverage', 'class' => 'Plugins\\Coverage\\Services\\CoverageWidgetRegistry', 'file' => APP_ROOT . '/apps/Manufacturing/modules/Coverage/Services/CoverageWidgetRegistry.php'],
            ['provider_name' => 'Daily Orders', 'module_key' => 'DailyOrders', 'provider_key' => 'daily_orders', 'class' => 'Plugins\\DailyOrders\\Services\\DailyOrdersWidgetRegistry', 'file' => APP_ROOT . '/apps/Manufacturing/modules/DailyOrders/Services/DailyOrdersWidgetRegistry.php'],
            ['provider_name' => 'Products', 'module_key' => 'Products', 'provider_key' => 'products', 'class' => 'Plugins\\Products\\Services\\ProductWidgetRegistry', 'file' => APP_ROOT . '/apps/Manufacturing/modules/Products/Services/ProductWidgetRegistry.php'],
            ['provider_name' => 'Production Plans', 'module_key' => 'ProductionPlans', 'provider_key' => 'production_plans', 'class' => 'Plugins\\ProductionPlans\\Services\\ProductionPlanWidgetRegistry', 'file' => APP_ROOT . '/apps/Manufacturing/modules/ProductionPlans/Services/ProductionPlanWidgetRegistry.php'],
            ['provider_name' => 'Production Queue', 'module_key' => 'ProductionQueue', 'provider_key' => 'production_queue', 'class' => 'Plugins\\ProductionQueue\\Services\\ProductionQueueWidgetRegistry', 'file' => APP_ROOT . '/apps/Manufacturing/modules/ProductionQueue/Services/ProductionQueueWidgetRegistry.php'],
            ['provider_name' => 'Production Entries', 'module_key' => 'ProductionEntries', 'provider_key' => 'production_entries', 'class' => 'Plugins\\ProductionEntries\\Services\\ProductionEntryWidgetRegistry', 'file' => APP_ROOT . '/apps/Manufacturing/modules/ProductionEntries/Services/ProductionEntryWidgetRegistry.php'],
            ['provider_name' => 'Machines', 'module_key' => 'Machines', 'provider_key' => 'machines', 'class' => 'Plugins\\Machines\\Services\\MachinesWidgetRegistry', 'file' => APP_ROOT . '/apps/Manufacturing/modules/Machines/Services/MachinesWidgetRegistry.php'],
            ['provider_name' => 'Assembly Plans', 'module_key' => 'AssemblyPlans', 'provider_key' => 'assembly_plans', 'class' => 'Apps\\Manufacturing\\Modules\\AssemblyPlans\\Services\\AssemblyPlanWidgetRegistry', 'file' => APP_ROOT . '/apps/Manufacturing/modules/AssemblyPlans/Services/AssemblyPlanWidgetRegistry.php'],
            ['provider_name' => 'Assembly Entries', 'module_key' => 'AssemblyEntries', 'provider_key' => 'assembly_entries', 'class' => 'Apps\\Manufacturing\\Modules\\AssemblyEntries\\Services\\AssemblyEntryWidgetRegistry', 'file' => APP_ROOT . '/apps/Manufacturing/modules/AssemblyEntries/Services/AssemblyEntryWidgetRegistry.php'],
            ['provider_name' => 'QC Plans', 'module_key' => 'QCPlans', 'provider_key' => 'qc_plans', 'class' => 'Plugins\\QCPlans\\Services\\QCPlanWidgetRegistry', 'file' => APP_ROOT . '/apps/Manufacturing/modules/QCPlans/Services/QCPlanWidgetRegistry.php'],
            ['provider_name' => 'QC Entries', 'module_key' => 'QCEntries', 'provider_key' => 'qc_entries', 'class' => 'Plugins\\QCEntries\\Services\\QCEntryWidgetRegistry', 'file' => APP_ROOT . '/apps/Manufacturing/modules/QCEntries/Services/QCEntryWidgetRegistry.php'],
            ['provider_name' => 'Dispatch', 'module_key' => 'DispatchEntries', 'provider_key' => 'dispatch_entries', 'class' => 'Plugins\\DispatchEntries\\Services\\DispatchEntryWidgetRegistry', 'file' => APP_ROOT . '/apps/Manufacturing/modules/DispatchEntries/Services/DispatchEntryWidgetRegistry.php'],
            ['provider_name' => 'Materials', 'module_key' => 'MaterialManagement', 'provider_key' => 'materials', 'class' => 'Plugins\\MaterialManagement\\Services\\MaterialManagementWidgetRegistry', 'file' => APP_ROOT . '/apps/Manufacturing/modules/MaterialManagement/Services/MaterialManagementWidgetRegistry.php'],
            ['provider_name' => 'Part-Machine Map', 'module_key' => 'PartMachineMap', 'provider_key' => 'part_machine_map', 'class' => 'Plugins\\PartMachineMap\\Services\\PartMachineMapWidgetRegistry', 'file' => APP_ROOT . '/apps/Manufacturing/modules/PartMachineMap/Services/PartMachineMapWidgetRegistry.php'],
            ['provider_name' => 'Pre Orders', 'module_key' => 'PreOrders', 'provider_key' => 'pre_orders', 'class' => 'Plugins\\PreOrders\\Services\\PreOrderWidgetRegistry', 'file' => APP_ROOT . '/apps/Manufacturing/modules/PreOrders/Services/PreOrderWidgetRegistry.php'],
            ['provider_name' => 'Ledger', 'module_key' => 'Ledger', 'provider_key' => 'ledger', 'class' => 'Plugins\\Ledger\\Services\\LedgerWidgetRegistry', 'file' => APP_ROOT . '/apps/Manufacturing/modules/Ledger/Services/LedgerWidgetRegistry.php'],
        ];
    }
}

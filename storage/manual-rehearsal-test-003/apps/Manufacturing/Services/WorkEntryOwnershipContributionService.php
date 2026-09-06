<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services;

final class WorkEntryOwnershipContributionService
{
    /**
     * @return array<string,array{app:string,modules:array<int,string>}>
     */
    public static function typeOwnershipMap(): array
    {
        return [
            'production_entry' => ['app' => 'manufacturing', 'modules' => ['production']],
            'material_consumption' => ['app' => 'manufacturing', 'modules' => ['materials']],
            'dispatch_entry' => ['app' => 'manufacturing', 'modules' => ['dispatch']],
            'plan_status' => ['app' => 'manufacturing', 'modules' => ['production']],
            'order_status' => ['app' => 'manufacturing', 'modules' => ['demands']],
            'dispatch_status' => ['app' => 'manufacturing', 'modules' => ['dispatch']],
            'production_plan' => ['app' => 'manufacturing', 'modules' => ['production']],
            'assembly_plan' => ['app' => 'manufacturing', 'modules' => ['assembly']],
            'qc_report' => ['app' => 'manufacturing', 'modules' => ['qc']],
            'dispatch_request' => ['app' => 'manufacturing', 'modules' => ['dispatch']],
            'material' => ['app' => 'manufacturing', 'modules' => ['materials']],
            'defect' => ['app' => 'manufacturing', 'modules' => ['qc']],
            'waste' => ['app' => 'manufacturing', 'modules' => ['production']],
            'machine_issue' => ['app' => 'manufacturing', 'modules' => ['machines']],
            'delay' => ['app' => 'manufacturing', 'modules' => ['production', 'dispatch']],
        ];
    }
}

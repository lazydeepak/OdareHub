<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services;

require_once APP_ROOT . '/apps/Manufacturing/modules/AssemblyPlans/AssemblyPlanService.php';

class_alias(
    \Apps\Manufacturing\Modules\AssemblyPlans\AssemblyPlanService::class,
    __NAMESPACE__ . '\\AssemblyPlanService'
);

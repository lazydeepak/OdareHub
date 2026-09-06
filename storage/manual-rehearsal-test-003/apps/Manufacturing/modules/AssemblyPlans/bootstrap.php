<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../app/Core/ManufacturingBootstrapHelper.php';

\App\Core\ManufacturingBootstrapHelper::registerEntity(
    __DIR__,
    'AssemblyPlan',
    '\Apps\Manufacturing\Modules\AssemblyPlans\register_assembly_plan_entity'
);

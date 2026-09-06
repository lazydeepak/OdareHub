<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Controllers;

require_once APP_ROOT . '/apps/Manufacturing/modules/AssemblyPlans/Controllers/AssemblyPlansController.php';

class_alias(
    \Apps\Manufacturing\Modules\AssemblyPlans\Controllers\AssemblyPlansController::class,
    __NAMESPACE__ . '\\AssemblyPlansController'
);

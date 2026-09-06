<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Controllers;

// Compatibility shim only. Normal runtime should resolve to
// Plugins\Coverage\Controllers\CoverageController inside the Manufacturing Coverage module.

require_once APP_ROOT . '/apps/Manufacturing/modules/Coverage/Controllers/CoverageController.php';

if (!class_exists(__NAMESPACE__ . '\\CoverageController', false) && class_exists(\Plugins\Coverage\Controllers\CoverageController::class)) {
    class_alias(\Plugins\Coverage\Controllers\CoverageController::class, __NAMESPACE__ . '\\CoverageController');
}

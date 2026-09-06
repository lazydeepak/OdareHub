<?php
declare(strict_types=1);

namespace App\Core;

// Compatibility bridge only. Normal runtime should resolve to
// Plugins\Workflow\Services\* inside Manufacturing.

$workflowBase = APP_ROOT . '/apps/Manufacturing/modules/Workflow/Services';
foreach (['WorkflowPolicy.php', 'WorkflowRegistry.php', 'WorkflowGovernance.php', 'WorkflowTransitionEngine.php'] as $workflowFile) {
    $workflowPath = $workflowBase . '/' . $workflowFile;
    if (is_file($workflowPath)) {
        require_once $workflowPath;
    }
}

if (!class_exists(__NAMESPACE__ . '\\WorkflowGovernance', false) && class_exists(\Plugins\Workflow\Services\WorkflowGovernance::class)) {
    class_alias(\Plugins\Workflow\Services\WorkflowGovernance::class, __NAMESPACE__ . '\\WorkflowGovernance');
}

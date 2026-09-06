<?php
declare(strict_types=1);

require_once __DIR__ . '/Services/DispatchWorkflow.php';

\Plugins\DispatchEntries\Services\DispatchWorkflow::ensureSchema();
\Plugins\Workflow\Services\WorkflowGovernance::ensureSchema();

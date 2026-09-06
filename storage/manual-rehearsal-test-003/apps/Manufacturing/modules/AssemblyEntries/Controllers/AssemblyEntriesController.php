<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Modules\AssemblyEntries\Controllers;

use App\Core\View;
use Apps\Manufacturing\Controllers\DemandExecutionController;

$appControllerPath = APP_ROOT . '/apps/Manufacturing/Controllers/DemandExecutionController.php';
if (!class_exists(DemandExecutionController::class) && is_file($appControllerPath)) {
    require_once $appControllerPath;
}

final class AssemblyEntriesController
{
    public static function queue(View $view): void
    {
        DemandExecutionController::assemblyQueue($view);
    }
}

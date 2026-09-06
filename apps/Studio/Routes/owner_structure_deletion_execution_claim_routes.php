<?php
declare(strict_types=1);

use Apps\Studio\Services\StudioToolInstancePolicyService;
use Apps\Studio\Tools\OwnerStructureScan\Controllers\OwnerStructureDeletionExecutionClaimController;

require_once APP_ROOT . '/apps/Studio/Services/StudioToolInstancePolicyService.php';
require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Controllers/OwnerStructureDeletionExecutionClaimController.php';

$router->post('/apps/studio/tools/owner-structure-scan/deletion-execution-claim', function () {
    if (!StudioToolInstancePolicyService::isEnabled('owner_structure_scan')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    OwnerStructureDeletionExecutionClaimController::claim();
    return null;
});

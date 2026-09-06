<?php
declare(strict_types=1);

use Apps\Studio\Services\StudioToolInstancePolicyService;
use Apps\Studio\Tools\OwnerStructureScan\Controllers\OwnerStructureDeletionApprovalController;

require_once APP_ROOT . '/apps/Studio/Services/StudioToolInstancePolicyService.php';
require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Controllers/OwnerStructureDeletionApprovalController.php';

$router->post('/apps/studio/tools/owner-structure-scan/deletion-approval-record', function () {
    if (!StudioToolInstancePolicyService::isEnabled('owner_structure_scan')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    OwnerStructureDeletionApprovalController::record();
    return null;
});

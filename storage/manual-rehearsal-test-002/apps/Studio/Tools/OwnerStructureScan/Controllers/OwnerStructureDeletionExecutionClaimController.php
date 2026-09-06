<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\OwnerStructureScan\Controllers;

use App\Core\Auth;
use Apps\Studio\Services\StudioDeletionApprovalRecordService;
use Apps\Studio\Services\StudioDeletionApprovalRecordStore;
use Apps\Studio\Services\StudioDeletionChangeSetService;
use Apps\Studio\Services\StudioDeletionExecutionDryRunService;
use Apps\Studio\Services\StudioDeletionExecutionReadinessService;
use Apps\Studio\Services\StudioDeletionExecutionRequestService;
use Apps\Studio\Services\StudioDeletionExecutionRequestStore;
use Apps\Studio\Services\StudioDeletionExecutionRequestUseEvidenceStore;
use Apps\Studio\Services\StudioDeletionExecutorReadinessService;
use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureDeletionExecutionClaimService;
use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureOwnerDiscoveryService;
use Platform\Security\PlatformAuthority;

require_once APP_ROOT . '/apps/Platform/bootstrap.php';
require_once APP_ROOT . '/apps/Studio/Services/StudioDeletionApprovalRecordService.php';
require_once APP_ROOT . '/apps/Studio/Services/StudioDeletionApprovalRecordStore.php';
require_once APP_ROOT . '/apps/Studio/Services/StudioDeletionChangeSetService.php';
require_once APP_ROOT . '/apps/Studio/Services/StudioDeletionExecutionDryRunService.php';
require_once APP_ROOT . '/apps/Studio/Services/StudioDeletionExecutionReadinessService.php';
require_once APP_ROOT . '/apps/Studio/Services/StudioDeletionExecutionRequestService.php';
require_once APP_ROOT . '/apps/Studio/Services/StudioDeletionExecutionRequestStore.php';
require_once APP_ROOT . '/apps/Studio/Services/StudioDeletionExecutionRequestUseEvidenceStore.php';
require_once APP_ROOT . '/apps/Studio/Services/StudioDeletionExecutorReadinessService.php';
require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionExecutionClaimService.php';
require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureOwnerDiscoveryService.php';
require_once APP_ROOT . '/platform/Security/PlatformAuthority.php';

/** POST-only controller for atomic, immutable execution request claims. */
final class OwnerStructureDeletionExecutionClaimController
{
    public static function claim(): void
    {
        $returnPath = '/apps/studio/tools/owner-structure-scan';
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $returnPath);

        $actor = PlatformAuthority::resolveCurrentActor();
        if (!PlatformAuthority::canManageOwnerWorkspace($actor)) {
            http_response_code(403);
            echo 'Forbidden';
            return;
        }

        $ownerKey = trim((string)($_POST['owner'] ?? ''));
        $selectedOwner = self::selectedOwner($ownerKey);
        $changeSet = StudioDeletionChangeSetService::build([
            'owner_key' => $ownerKey,
            'target_type' => 'owner',
        ], APP_ROOT);
        $exactApproval = StudioDeletionApprovalRecordService::latest($changeSet, APP_ROOT);
        $latestOwnerApproval = StudioDeletionApprovalRecordStore::latestForOwner($ownerKey, APP_ROOT);
        $approvalReadiness = StudioDeletionExecutionReadinessService::assess($changeSet, $exactApproval, $latestOwnerApproval);
        $dryRun = StudioDeletionExecutionDryRunService::simulate($changeSet, $approvalReadiness, APP_ROOT);
        $exactRequest = StudioDeletionExecutionRequestService::latest($changeSet, $dryRun, APP_ROOT);
        $latestOwnerRequest = StudioDeletionExecutionRequestStore::latestForOwner($ownerKey, APP_ROOT);
        $requestId = trim((string)($exactRequest['request_id'] ?? ''));
        $useEvidence = $requestId !== ''
            ? StudioDeletionExecutionRequestUseEvidenceStore::latest($requestId, APP_ROOT)
            : null;
        $executorReadiness = StudioDeletionExecutorReadinessService::assess(
            $changeSet,
            $approvalReadiness,
            $dryRun,
            $exactRequest,
            $latestOwnerRequest,
            is_array($actor) ? $actor : null,
            $useEvidence
        );

        $input = [
            'request_id' => (string)($_POST['request_id'] ?? ''),
            'request_fingerprint' => (string)($_POST['request_fingerprint'] ?? ''),
            'executor_readiness_fingerprint' => (string)($_POST['executor_readiness_fingerprint'] ?? ''),
            'claim_confirmed' => isset($_POST['claim_confirmed']) ? 'yes' : 'no',
            'reason' => (string)($_POST['reason'] ?? ''),
        ];
        $result = OwnerStructureDeletionExecutionClaimService::claim(
            $selectedOwner,
            $executorReadiness,
            $input,
            is_array($actor) ? $actor : null,
            APP_ROOT
        );
        $_SESSION['studio_owner_structure_deletion_execution_claim_result'] = $result;

        $target = $returnPath;
        if ($ownerKey !== '') {
            $target .= '?owner=' . rawurlencode($ownerKey)
                . '&scan=1&deletion_impact=1&deletion_plan=1&deletion_change_set=1&deletion_approval=1'
                . '&deletion_execution_readiness=1&deletion_execution_dry_run=1&deletion_execution_request=1'
                . '&deletion_executor_readiness=1&deletion_execution_claim=1';
        }
        header('Location: ' . $target, true, 302);
        exit;
    }

    /** @return array<string,mixed> */
    private static function selectedOwner(string $ownerKey): array
    {
        foreach (OwnerStructureOwnerDiscoveryService::discover() as $owner) {
            if (is_array($owner) && (string)($owner['owner_key'] ?? '') === $ownerKey) {
                return $owner;
            }
        }
        return ['owner_key' => $ownerKey];
    }
}

<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\OwnerStructureScan\Controllers;

use App\Core\Auth;
use Apps\Studio\Services\StudioDeletionApprovalRecordService;
use Apps\Studio\Services\StudioDeletionApprovalRecordStore;
use Apps\Studio\Services\StudioDeletionChangeSetService;
use Apps\Studio\Services\StudioDeletionExecutionDryRunService;
use Apps\Studio\Services\StudioDeletionExecutionReadinessService;
use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureDeletionExecutionRequestService;
use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureOwnerDiscoveryService;
use Platform\Security\PlatformAuthority;

require_once APP_ROOT . '/apps/Platform/bootstrap.php';
require_once APP_ROOT . '/apps/Studio/Services/StudioDeletionApprovalRecordService.php';
require_once APP_ROOT . '/apps/Studio/Services/StudioDeletionApprovalRecordStore.php';
require_once APP_ROOT . '/apps/Studio/Services/StudioDeletionChangeSetService.php';
require_once APP_ROOT . '/apps/Studio/Services/StudioDeletionExecutionDryRunService.php';
require_once APP_ROOT . '/apps/Studio/Services/StudioDeletionExecutionReadinessService.php';
require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionExecutionRequestService.php';
require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureOwnerDiscoveryService.php';
require_once APP_ROOT . '/platform/Security/PlatformAuthority.php';

/** POST-only controller for immutable deletion execution request provenance. */
final class OwnerStructureDeletionExecutionRequestController
{
    public static function record(): void
    {
        $returnPath = '/apps/studio/tools/owner-structure-scan';
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $returnPath);

        $requester = PlatformAuthority::resolveCurrentActor();
        if (!PlatformAuthority::canManageOwnerWorkspace($requester)) {
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
        $readiness = StudioDeletionExecutionReadinessService::assess($changeSet, $exactApproval, $latestOwnerApproval);
        $dryRun = StudioDeletionExecutionDryRunService::simulate($changeSet, $readiness, APP_ROOT);

        $input = [
            'change_set_fingerprint' => (string)($_POST['change_set_fingerprint'] ?? ''),
            'plan_fingerprint' => (string)($_POST['plan_fingerprint'] ?? ''),
            'readiness_fingerprint' => (string)($_POST['readiness_fingerprint'] ?? ''),
            'dry_run_fingerprint' => (string)($_POST['dry_run_fingerprint'] ?? ''),
            'approval_record_id' => (string)($_POST['approval_record_id'] ?? ''),
            'approval_record_fingerprint' => (string)($_POST['approval_record_fingerprint'] ?? ''),
            'executor_actor_id' => (string)($_POST['executor_actor_id'] ?? ''),
            'executor_display_name' => (string)($_POST['executor_display_name'] ?? ''),
            'executor_authority_role' => 'platform_admin',
            'expires_at_utc' => (string)($_POST['expires_at_utc'] ?? ''),
            'reason' => (string)($_POST['reason'] ?? ''),
        ];

        $result = OwnerStructureDeletionExecutionRequestService::record(
            $selectedOwner,
            $changeSet,
            $readiness,
            $dryRun,
            $input,
            $requester,
            APP_ROOT
        );
        $_SESSION['studio_owner_structure_deletion_execution_request_result'] = $result;

        $target = $returnPath;
        if ($ownerKey !== '') {
            $target .= '?owner=' . rawurlencode($ownerKey)
                . '&scan=1&deletion_impact=1&deletion_plan=1&deletion_change_set=1&deletion_approval=1'
                . '&deletion_execution_readiness=1&deletion_execution_dry_run=1&deletion_execution_request=1';
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

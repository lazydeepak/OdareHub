<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\OwnerStructureScan\Controllers;

use App\Core\Auth;
use Apps\Studio\Services\StudioDeletionChangeSetService;
use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureDeletionApprovalRecordService;
use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureOwnerDiscoveryService;
use Platform\Security\PlatformAuthority;

require_once APP_ROOT . '/apps/Platform/bootstrap.php';
require_once APP_ROOT . '/apps/Studio/Services/StudioDeletionChangeSetService.php';
require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionApprovalRecordService.php';
require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureOwnerDiscoveryService.php';
require_once APP_ROOT . '/platform/Security/PlatformAuthority.php';

/** POST-only controller for immutable approval/rejection provenance records. */
final class OwnerStructureDeletionApprovalController
{
    public static function record(): void
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
        $input = [
            'decision' => (string)($_POST['decision'] ?? ''),
            'reason' => (string)($_POST['reason'] ?? ''),
            'change_set_fingerprint' => (string)($_POST['change_set_fingerprint'] ?? ''),
            'plan_fingerprint' => (string)($_POST['plan_fingerprint'] ?? ''),
            'confirmations' => isset($_POST['confirmations']) && is_array($_POST['confirmations'])
                ? array_map('strval', $_POST['confirmations'])
                : [],
        ];

        $result = OwnerStructureDeletionApprovalRecordService::record(
            $selectedOwner,
            $changeSet,
            $input,
            $actor,
            APP_ROOT
        );
        $_SESSION['studio_owner_structure_deletion_approval_result'] = $result;

        $target = $returnPath;
        if ($ownerKey !== '') {
            $target .= '?owner=' . rawurlencode($ownerKey)
                . '&scan=1&deletion_impact=1&deletion_plan=1&deletion_change_set=1&deletion_approval=1';
        }
        header('Location: ' . $target, true, 302);
        exit;
    }

    /** @return array<string,mixed> */
    private static function selectedOwner(string $ownerKey): array
    {
        $discovery = OwnerStructureOwnerDiscoveryService::discover();
        foreach ($discovery as $owner) {
            if (is_array($owner) && (string)($owner['owner_key'] ?? '') === $ownerKey) {
                return $owner;
            }
        }
        return ['owner_key' => $ownerKey];
    }
}

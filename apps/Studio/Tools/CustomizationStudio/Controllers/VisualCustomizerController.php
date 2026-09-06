<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\CustomizationStudio\Controllers;

use App\Core\Auth;
use Apps\Studio\Tools\CustomizationStudio\Services\VisualCustomizerMetadataService;
use Apps\Studio\Tools\CustomizationStudio\Services\VisualCustomizerDraftStorageService;
use Apps\Studio\Tools\CustomizationStudio\Services\VisualCustomizerRequestValidationService;
use Apps\Studio\Tools\CustomizationStudio\Services\VisualCustomizerApprovalRequestService;
use Apps\Studio\Tools\CustomizationStudio\Services\VisualCustomizerSnapshotService;

require_once __DIR__ . '/../Services/VisualCustomizerMetadataService.php';
require_once __DIR__ . '/../Services/VisualCustomizerDraftStorageService.php';
require_once __DIR__ . '/../Services/VisualCustomizerRequestValidationService.php';
require_once __DIR__ . '/../Services/VisualCustomizerApprovalRequestService.php';
require_once __DIR__ . '/../Services/VisualCustomizerSnapshotService.php';

final class VisualCustomizerController
{
    public static function viewPath(): string
    {
        return __DIR__ . '/../Views/visual-customizer.php';
    }

    public static function requestDetailViewPath(): string
    {
        return __DIR__ . '/../Views/visual-customizer-request-detail.php';
    }

    /**
     * @return array<string,mixed>
     */
    public static function placeholderModel(): array
    {
        $user = Auth::user();
        $metadata = VisualCustomizerMetadataService::discover();
        $persistedDraft = VisualCustomizerDraftStorageService::readDraftForUser(is_array($user) ? $user : []);

        $radiusScaleConfig = self::findRadiusScaleConfig($metadata);
        $validationResult = $radiusScaleConfig !== []
            ? VisualCustomizerRequestValidationService::validate($persistedDraft, $radiusScaleConfig)
            : [
                'valid' => false,
                'status' => 'not_eligible',
                'checks' => [
                    'socket_allowed' => false,
                    'proposed_value_exists' => false,
                    'proposed_value_allowed' => false,
                    'studio_local_draft' => false,
                    'draft_shape_valid' => false,
                    'diff_exists' => false,
                    'apply_disabled' => false,
                    'runtime_untouched' => false,
                ],
                'errors' => ['radius.scale socket configuration not found'],
                'warnings' => [],
            ];

        return [
            'runtime_status' => 'preview_skeleton_only_not_connected',
            'actions_enabled' => false,
            'draft_writes_enabled' => true,
            'registry_writes_enabled' => false,
            'shell_connection_enabled' => false,
            'metadata_read_only_enabled' => true,
            'metadata' => $metadata,
            'all_sockets' => VisualCustomizerMetadataService::discoverAllSocketsFlat(),
            'persisted_draft' => $persistedDraft,
            'draft_update_endpoint' => '/apps/studio/tools/customization-studio/visual-customizer/draft/update',
            'recheck_readiness_endpoint' => '/apps/studio/tools/customization-studio/visual-customizer/draft/recheck-readiness',
            'create_approval_request_endpoint' => '/apps/studio/tools/customization-studio/visual-customizer/draft/create-approval-request',
            'csrf' => Auth::csrfToken(),
            'validation_result' => $validationResult,
            'pending_approval_requests' => VisualCustomizerApprovalRequestService::listRequestsForUser(is_array($user) ? $user : []),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function requestDetailModel(): array
    {
        $user = Auth::user();
        $actor = is_array($user) ? $user : [];

        $requestId = trim((string)($_GET['request_id'] ?? ''));
        $request = VisualCustomizerApprovalRequestService::getRequestById($actor, $requestId);

        // Fallback to global search for reviewer access
        if ($request === null) {
            $globalResult = VisualCustomizerApprovalRequestService::findRequestGlobally($requestId);
            if ($globalResult !== null) {
                $request = $globalResult['request'];
            }
        }

        $actorHandle = (string)($actor['handle'] ?? $actor['username'] ?? '');
        $requesterByHandle = (string)($request['requested_by'] ?? '');
        $requesterUserId = (int)($request['requested_by_user_id'] ?? 0);
        $actorUserId = (int)($actor['id'] ?? 0);

        $isRequester = $request !== null && (
            ($actorHandle !== '' && $actorHandle === $requesterByHandle)
            || ($requesterUserId > 0 && $actorUserId > 0 && $actorUserId === $requesterUserId)
        );

        $snapshot = $request !== null
            ? VisualCustomizerSnapshotService::getSnapshotByRequestId($requestId)
            : null;

        $requestStatus = $request['status'] ?? '';
        $isApproved = $request !== null && $requestStatus === 'approved_for_future_apply';
        $snapshotStatus = $snapshot['status'] ?? '';
        $canApply = $isApproved && $isRequester
            && $snapshot !== null && $snapshotStatus === 'snapshot_taken'
            && !isset($request['applied_at']);

        return [
            'found' => $request !== null,
            'request_id' => $requestId,
            'request' => $request ?? [],
            'current_user_id' => (int)($actor['id'] ?? 0),
            'current_user_handle' => $actorHandle,
            'current_user_is_requester' => $isRequester,
            'current_user_can_review' => !$isRequester,
            'csrf' => Auth::csrfToken(),
            'approve_endpoint' => '/apps/studio/tools/customization-studio/visual-customizer/request/approve',
            'reject_endpoint' => '/apps/studio/tools/customization-studio/visual-customizer/request/reject',
            'cancel_endpoint' => '/apps/studio/tools/customization-studio/visual-customizer/request/cancel',
            'take_snapshot_endpoint' => '/apps/studio/tools/customization-studio/visual-customizer/request/take-snapshot',
            'apply_endpoint' => '/apps/studio/tools/customization-studio/visual-customizer/request/apply',
            'snapshot' => $snapshot,
            'can_apply' => $canApply,
            'is_applied' => isset($request['applied_at']),
            'applied_at' => $request['applied_at'] ?? null,
            'applied_by' => $request['applied_by'] ?? null,
            'registry_target' => $request['registry_target'] ?? null,
        ];
    }

    /**
     * @param array<string,mixed> $metadata
     * @return array<string,mixed>
     */
    private static function findRadiusScaleConfig(array $metadata): array
    {
        foreach ($metadata['socket_catalogs'] ?? [] as $catalog) {
            if (!is_array($catalog)) {
                continue;
            }
            foreach ($catalog['sockets'] ?? [] as $socket) {
                if (is_array($socket) && ($socket['id'] ?? '') === 'radius.scale') {
                    return $socket;
                }
            }
        }
        return [];
    }

    public static function handleRecheckReadiness(): void
    {
        $user = Auth::user();
        $actor = is_array($user) ? $user : [];
        $persistedDraft = VisualCustomizerDraftStorageService::readDraftForUser($actor);
        $metadata = VisualCustomizerMetadataService::discover();

        $radiusScaleConfig = self::findRadiusScaleConfig($metadata);
        $result = $radiusScaleConfig !== []
            ? VisualCustomizerRequestValidationService::validate($persistedDraft, $radiusScaleConfig)
            : [
                'valid' => false,
                'status' => 'not_eligible',
                'checks' => [
                    'socket_allowed' => false,
                    'proposed_value_exists' => false,
                    'proposed_value_allowed' => false,
                    'studio_local_draft' => false,
                    'draft_shape_valid' => false,
                    'diff_exists' => false,
                    'apply_disabled' => false,
                    'runtime_untouched' => false,
                ],
                'errors' => ['radius.scale socket configuration not found'],
                'warnings' => [],
            ];

        self::emitJson($result, 200);
    }

    public static function handleCreateApprovalRequest(): void
    {
        $payload = self::decodeJsonInput();
        Auth::requireCsrf((string)($payload['csrf'] ?? ''), '/apps/studio/tools/customization-studio/visual-customizer');

        $user = Auth::user();
        $actor = is_array($user) ? $user : [];
        $persistedDraft = VisualCustomizerDraftStorageService::readDraftForUser($actor);
        $metadata = VisualCustomizerMetadataService::discover();

        $radiusScaleConfig = self::findRadiusScaleConfig($metadata);
        if ($radiusScaleConfig === []) {
            self::emitJson(['ok' => false, 'error' => 'socket_config_not_found'], 422);
            return;
        }

        $validationResult = VisualCustomizerRequestValidationService::validate($persistedDraft, $radiusScaleConfig);
        if (!$validationResult['valid']) {
            self::emitJson([
                'ok' => false,
                'error' => 'validation_failed',
                'validation_result' => $validationResult,
            ], 422);
            return;
        }

        $result = VisualCustomizerApprovalRequestService::createRequest($actor, $persistedDraft, $radiusScaleConfig, $validationResult);

        if (!empty($result['ok'])) {
            self::emitJson($result, 201);
        } elseif (isset($result['existing_request_id'])) {
            self::emitJson($result, 409);
        } else {
            self::emitJson($result, 422);
        }
    }

    public static function handleApproveRequest(): void
    {
        $payload = self::decodeJsonInput();
        Auth::requireCsrf((string)($payload['csrf'] ?? ''), '/apps/studio/tools/customization-studio/visual-customizer/request');

        $user = Auth::user();
        $actor = is_array($user) ? $user : [];
        $requestId = trim((string)($payload['request_id'] ?? ''));

        $result = VisualCustomizerApprovalRequestService::approveRequest($requestId, $actor);
        self::emitJson($result, !empty($result['ok']) ? 200 : 422);
    }

    public static function handleRejectRequest(): void
    {
        $payload = self::decodeJsonInput();
        Auth::requireCsrf((string)($payload['csrf'] ?? ''), '/apps/studio/tools/customization-studio/visual-customizer/request');

        $user = Auth::user();
        $actor = is_array($user) ? $user : [];
        $requestId = trim((string)($payload['request_id'] ?? ''));
        $reason = trim((string)($payload['reason'] ?? ''));

        $result = VisualCustomizerApprovalRequestService::rejectRequest($requestId, $actor, $reason);
        self::emitJson($result, !empty($result['ok']) ? 200 : 422);
    }

    public static function handleCancelRequest(): void
    {
        $payload = self::decodeJsonInput();
        Auth::requireCsrf((string)($payload['csrf'] ?? ''), '/apps/studio/tools/customization-studio/visual-customizer/request');

        $user = Auth::user();
        $actor = is_array($user) ? $user : [];
        $requestId = trim((string)($payload['request_id'] ?? ''));
        $reason = trim((string)($payload['reason'] ?? ''));

        $result = VisualCustomizerApprovalRequestService::cancelRequest($requestId, $actor, $reason);
        self::emitJson($result, !empty($result['ok']) ? 200 : 422);
    }

    public static function handleTakeSnapshot(): void
    {
        $payload = self::decodeJsonInput();
        Auth::requireCsrf((string)($payload['csrf'] ?? ''), '/apps/studio/tools/customization-studio/visual-customizer/request');

        $user = Auth::user();
        $actor = is_array($user) ? $user : [];
        $requestId = trim((string)($payload['request_id'] ?? ''));

        if ($requestId === '') {
            self::emitJson(['ok' => false, 'error' => 'missing_request_id'], 422);
            return;
        }

        $globalResult = VisualCustomizerApprovalRequestService::findRequestGlobally($requestId);
        if ($globalResult === null) {
            self::emitJson(['ok' => false, 'error' => 'request_not_found'], 422);
            return;
        }

        $request = $globalResult['request'];
        $result = VisualCustomizerSnapshotService::takeSnapshot($request, $actor);
        self::emitJson($result, !empty($result['ok']) ? 201 : 422);
    }

    public static function handleApplyRequest(): void
    {
        $payload = self::decodeJsonInput();
        Auth::requireCsrf((string)($payload['csrf'] ?? ''), '/apps/studio/tools/customization-studio/visual-customizer/request');

        $user = Auth::user();
        $actor = is_array($user) ? $user : [];
        $requestId = trim((string)($payload['request_id'] ?? ''));

        $result = VisualCustomizerApprovalRequestService::applyRequest($requestId, $actor);
        self::emitJson($result, !empty($result['ok']) ? 200 : 422);
    }

    public static function handleDraftUpdate(): void
    {
        $payload = self::decodeJsonInput();
        Auth::requireCsrf((string)($payload['csrf'] ?? ''), '/apps/studio/tools/customization-studio/visual-customizer');

        $action = trim((string)($payload['action'] ?? ''));
        $socketId = trim((string)($payload['socket_id'] ?? ''));
        $proposedValue = isset($payload['proposed_value']) ? trim((string)$payload['proposed_value']) : null;
        $defaultValue = trim((string)($payload['default_value'] ?? ''));
        $user = Auth::user();
        $actor = is_array($user) ? $user : [];

        $result = ['ok' => false, 'error' => 'invalid_action'];
        if ($action === 'set') {
            $result = VisualCustomizerDraftStorageService::saveProposedValueForUser($actor, $socketId, $defaultValue, $proposedValue ?? '');
        } elseif ($action === 'reset') {
            $result = VisualCustomizerDraftStorageService::resetProposedToDefaultForUser($actor, $socketId, $defaultValue);
        } elseif ($action === 'discard') {
            $result = VisualCustomizerDraftStorageService::discardProposedValueForUser($actor, $socketId, $defaultValue);
        }

        self::emitJson($result, !empty($result['ok']) ? 200 : 422);
    }

    /**
     * @return array<string,mixed>
     */
    private static function decodeJsonInput(): array
    {
        $raw = @file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string,mixed> $payload
     */
    private static function emitJson(array $payload, int $statusCode): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo (string)json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

<?php
declare(strict_types=1);

namespace Apps\Studio\ActionHandlers;

use App\Core\DB;
use Apps\Studio\Authorization\StudioAuthorizationService;
use Apps\Studio\Repositories\OrdersRepository;
use Apps\Studio\Repositories\StudioSchemaGovernanceService;
use Apps\Studio\Services\StudioNotificationService;
use Apps\Studio\Workflow\WorkflowService;

require_once APP_ROOT . '/app/Core/DB.php';
require_once __DIR__ . '/StudioActionHandler.php';
require_once __DIR__ . '/../Authorization/StudioAuthorizationService.php';
require_once __DIR__ . '/../Repositories/OrdersRepository.php';
require_once __DIR__ . '/../Repositories/StudioSchemaGovernanceService.php';
require_once __DIR__ . '/../Services/StudioNotificationService.php';
require_once __DIR__ . '/../Workflow/WorkflowService.php';

final class OrderTransitionHandler implements StudioActionHandler
{
    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function handle(array $payload, array $context): array
    {
        $action = strtolower(trim((string)($payload['action'] ?? '')));
        if (in_array($action, ['claim_task', 'assign_task', 'release_task'], true)) {
            try {
                return $this->handleOwnershipAction($payload, $context, $action);
            } catch (\Throwable) {
                return ['ok' => false, 'code' => 'db_error', 'message' => 'db_error', 'errors' => [], 'data' => []];
            }
        }

        if (!StudioAuthorizationService::can('orders', 'transition', $context)) {
            return ['ok' => false, 'code' => 'forbidden', 'message' => 'forbidden', 'errors' => [], 'data' => []];
        }

        $orderNo = trim((string)($payload['order_no'] ?? ''));
        $toState = strtolower(trim((string)($payload['to_state'] ?? '')));
        if ($orderNo === '' || $toState === '') {
            $errors = [];
            if ($orderNo === '') {
                $errors['order_no'] = 'required';
            }
            if ($toState === '') {
                $errors['to_state'] = 'required';
            }
            return [
                'ok' => false,
                'code' => 'validation_failed',
                'message' => 'validation_failed',
                'errors' => $errors,
                'data' => [],
            ];
        }

        $appKey = StudioSchemaGovernanceService::sanitizeAppKey((string)($context['app_key'] ?? 'default'));
        StudioSchemaGovernanceService::ensureSchema($appKey);

        try {
            return $this->withTransaction(function () use ($appKey, $context, $orderNo, $toState): array {
                $authorizedContext = $context;
                $authorizedContext['_studio_authorized'] = true;

                $repo = new OrdersRepository($appKey, $authorizedContext);
                $current = $repo->findOneByOrderNo($orderNo);
                if (!is_array($current)) {
                    return ['ok' => false, 'code' => 'not_found', 'message' => 'not_found', 'errors' => [], 'data' => []];
                }

                $lifecycleStatus = strtolower(trim((string)($current['status'] ?? '')));
                if ($lifecycleStatus !== 'active') {
                    return ['ok' => false, 'code' => 'lifecycle_blocked', 'message' => 'lifecycle_blocked', 'errors' => [], 'data' => []];
                }

                $fromState = strtolower(trim((string)($current['state'] ?? 'draft')));
                if ($fromState === 'completed' || $fromState === 'cancelled') {
                    return ['ok' => false, 'code' => 'terminal_state', 'message' => 'terminal_state', 'errors' => [], 'data' => []];
                }

                if (!WorkflowService::canTransition('orders', $fromState, $toState)) {
                    return ['ok' => false, 'code' => 'invalid_transition', 'message' => 'invalid_transition', 'errors' => [], 'data' => []];
                }

                if (!WorkflowService::canUserTransition('orders', $fromState, $toState, $context)) {
                    return ['ok' => false, 'code' => 'forbidden_transition', 'message' => 'forbidden_transition', 'errors' => [], 'data' => []];
                }

                $affected = $repo->updateState($orderNo, $toState);
                if ($affected <= 0) {
                    return ['ok' => false, 'code' => 'transition_blocked', 'message' => 'transition_blocked', 'errors' => [], 'data' => []];
                }

                $entityId = (int)($current['id'] ?? 0);
                StudioSchemaGovernanceService::logAudit($appKey, 'workflow_transition', 'orders', [
                    'id' => $entityId,
                    'entity_id' => $entityId,
                    'order_no' => $orderNo,
                    'from_state' => $fromState,
                    'to_state' => $toState,
                    'user_id' => (int)($context['user']['id'] ?? $context['user_id'] ?? 0),
                ], $context);

                $this->dispatchTransitionNotifications($orderNo, $entityId, $fromState, $toState, $context);

                return [
                    'ok' => true,
                    'code' => 'workflow_transitioned',
                    'message' => 'workflow_transitioned',
                    'errors' => [],
                    'data' => [
                        'order_no' => $orderNo,
                        'from_state' => $fromState,
                        'to_state' => $toState,
                        'affected' => $affected,
                    ],
                ];
            });
        } catch (\Throwable) {
            return ['ok' => false, 'code' => 'db_error', 'message' => 'db_error', 'errors' => [], 'data' => []];
        }
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private function handleOwnershipAction(array $payload, array $context, string $action): array
    {
        if (!StudioAuthorizationService::can('orders', 'transition', $context)) {
            return ['ok' => false, 'code' => 'forbidden', 'message' => 'forbidden', 'errors' => [], 'data' => []];
        }

        $orderNo = trim((string)($payload['order_no'] ?? ''));
        if ($orderNo === '') {
            return ['ok' => false, 'code' => 'validation_failed', 'message' => 'validation_failed', 'errors' => ['order_no' => 'required'], 'data' => []];
        }

        $appKey = StudioSchemaGovernanceService::sanitizeAppKey((string)($context['app_key'] ?? 'default'));
        StudioSchemaGovernanceService::ensureSchema($appKey);

        return $this->withTransaction(function () use ($payload, $context, $action, $orderNo, $appKey): array {
            $authorizedContext = $context;
            $authorizedContext['_studio_authorized'] = true;
            $repo = new OrdersRepository($appKey, $authorizedContext);

            $current = $repo->findOneByOrderNo($orderNo);
            if (!is_array($current)) {
                return ['ok' => false, 'code' => 'not_found', 'message' => 'not_found', 'errors' => [], 'data' => []];
            }

            $lifecycleStatus = strtolower(trim((string)($current['status'] ?? '')));
            if ($lifecycleStatus !== 'active') {
                return ['ok' => false, 'code' => 'lifecycle_blocked', 'message' => 'lifecycle_blocked', 'errors' => [], 'data' => []];
            }

            $actorRole = StudioAuthorizationService::resolveRole($context);
            $actorUserId = (int)($context['user']['id'] ?? $context['user_id'] ?? 0);
            $assignedTo = (int)($current['assigned_to'] ?? 0);
            $entityId = (int)($current['id'] ?? 0);
            $affected = 0;

            if ($action === 'claim_task') {
                if ($assignedTo > 0) {
                    return ['ok' => false, 'code' => 'already_assigned', 'message' => 'already_assigned', 'errors' => [], 'data' => []];
                }
                $affected = $repo->claimTask($orderNo, $actorUserId);
                if ($affected > 0) {
                    StudioSchemaGovernanceService::logAudit($appKey, 'claim_task', 'orders', [
                        'id' => $entityId,
                        'entity_id' => $entityId,
                        'order_no' => $orderNo,
                        'assigned_to' => $actorUserId,
                    ], $context);
                    $this->notifyAssignedUser($actorUserId, $orderNo, $entityId, 'claim_task');
                }
            } elseif ($action === 'assign_task') {
                if (!in_array($actorRole, ['manager', 'admin'], true)) {
                    return ['ok' => false, 'code' => 'forbidden_assignment', 'message' => 'forbidden_assignment', 'errors' => [], 'data' => []];
                }

                $assigneeUserId = (int)($payload['assigned_to'] ?? 0);
                if ($assigneeUserId <= 0) {
                    return ['ok' => false, 'code' => 'validation_failed', 'message' => 'validation_failed', 'errors' => ['assigned_to' => 'required'], 'data' => []];
                }

                $affected = $repo->assignTask($orderNo, $assigneeUserId);
                if ($affected > 0) {
                    StudioSchemaGovernanceService::logAudit($appKey, 'assign_task', 'orders', [
                        'id' => $entityId,
                        'entity_id' => $entityId,
                        'order_no' => $orderNo,
                        'assigned_to' => $assigneeUserId,
                    ], $context);
                    $this->notifyAssignedUser($assigneeUserId, $orderNo, $entityId, 'assign_task');
                }
            } elseif ($action === 'release_task') {
                if ($assignedTo <= 0) {
                    return ['ok' => false, 'code' => 'not_assigned', 'message' => 'not_assigned', 'errors' => [], 'data' => []];
                }
                if (!in_array($actorRole, ['manager', 'admin'], true) && $actorUserId !== $assignedTo) {
                    return ['ok' => false, 'code' => 'forbidden_release', 'message' => 'forbidden_release', 'errors' => [], 'data' => []];
                }

                $affected = $repo->releaseTask($orderNo);
                if ($affected > 0) {
                    StudioSchemaGovernanceService::logAudit($appKey, 'release_task', 'orders', [
                        'id' => $entityId,
                        'entity_id' => $entityId,
                        'order_no' => $orderNo,
                        'released_from' => $assignedTo,
                    ], $context);
                }
            }

            if ($affected <= 0) {
                return ['ok' => false, 'code' => 'ownership_update_blocked', 'message' => 'ownership_update_blocked', 'errors' => [], 'data' => []];
            }

            return [
                'ok' => true,
                'code' => $action . '_ok',
                'message' => $action . '_ok',
                'errors' => [],
                'data' => [
                    'order_no' => $orderNo,
                    'action' => $action,
                    'affected' => $affected,
                ],
            ];
        });
    }

    /**
     * @template T
     * @param callable():T $fn
     * @return T
     */
    private function withTransaction(callable $fn)
    {
        $db = DB::conn();
        $db->begin_transaction();
        try {
            $result = $fn();
            $db->commit();
            return $result;
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * @param array<string,mixed> $context
     */
    private function dispatchTransitionNotifications(string $orderNo, int $entityId, string $fromState, string $toState, array $context): void
    {
        $actorUserId = (int)($context['user']['id'] ?? $context['user_id'] ?? 0);

        $targetRole = '';
        if ($toState === 'approved') {
            $targetRole = 'operator';
        } elseif ($toState === 'processing' || $toState === 'completed') {
            $targetRole = 'manager';
        }

        if ($targetRole === '') {
            return;
        }

        $recipients = StudioNotificationService::recipientsForRole($targetRole, $actorUserId);
        if ($recipients === [] && $actorUserId > 0) {
            $recipients = [$actorUserId];
        }

        if ($recipients === []) {
            return;
        }

        $message = 'Order ' . $orderNo . ' moved ' . $fromState . ' -> ' . $toState;
        foreach ($recipients as $recipientId) {
            StudioNotificationService::notify((int)$recipientId, $message, 'orders', $entityId, 'workflow_transition');
        }
    }

    private function notifyAssignedUser(int $userId, string $orderNo, int $entityId, string $type): void
    {
        if ($userId <= 0) {
            return;
        }

        $message = 'Task assigned for order ' . $orderNo;
        StudioNotificationService::notify($userId, $message, 'orders', $entityId, $type);
    }
}

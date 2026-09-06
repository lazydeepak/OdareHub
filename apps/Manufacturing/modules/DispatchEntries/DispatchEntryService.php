<?php
declare(strict_types=1);

namespace Plugins\DispatchEntries;

use App\Core\EntityContext;
use App\Core\EntityRegistry;
use App\Core\EntityStore;
use App\Core\EntityHookRunner;
use App\Core\EntityPolicyResolver;
use App\Core\EntitySlaEngine;
use App\Core\EntityWorkflowGuard;
use App\Core\EntityDefinitionValidator;
use App\Core\ManufacturingGateway;

class DispatchEntryService
{
    private static ?EntityStore $store = null;
    private static ?EntityHookRunner $hookRunner = null;
    private static ?EntityPolicyResolver $policyResolver = null;
    private static ?EntitySlaEngine $slaEngine = null;
    private static ?EntityWorkflowGuard $workflowGuard = null;
    private static ?EntityDefinitionValidator $validator = null;

    public static function setStore(object $store): void
    {
        self::$store = $store instanceof EntityStore ? $store : null;
    }

    public static function getStore(): EntityStore
    {
        if (self::$store === null) {
            self::ensureStore();
        }
        return self::$store;
    }

    private static function ensureStore(): void
    {
        if (self::$hookRunner === null) {
            self::$hookRunner = new EntityHookRunner();
        }
        if (self::$policyResolver === null) {
            self::$policyResolver = new EntityPolicyResolver();
        }
        if (self::$slaEngine === null) {
            self::$slaEngine = new EntitySlaEngine();
        }
        if (self::$workflowGuard === null) {
            self::$workflowGuard = new EntityWorkflowGuard();
        }
        if (self::$validator === null) {
            self::$validator = new EntityDefinitionValidator();
        }

        self::$store = new EntityStore(
            new EntityRegistry(),
            ManufacturingGateway::getInstance(),
            self::$hookRunner,
            self::$policyResolver,
            self::$slaEngine,
            self::$workflowGuard,
            self::$validator
        );
    }

    public static function reset(): void
    {
        self::$store = null;
        self::$hookRunner = null;
        self::$policyResolver = null;
        self::$slaEngine = null;
        self::$workflowGuard = null;
        self::$validator = null;
    }

    public static function create(array $data, EntityContext $context): int
    {
        return self::getStore()->create('DispatchEntry', $data, $context);
    }

    public static function update(int $id, array $data, EntityContext $context, ?array $original = null): bool
    {
        return self::getStore()->update('DispatchEntry', $id, $data, $context, $original);
    }

    public static function transitionStatus(int $id, string $newStatus, EntityContext $context, string $reason = '', string $note = ''): bool
    {
        $row = self::find($id, $context);
        if (!$row) {
            throw new \RuntimeException('Dispatch entry not found.');
        }

        $data = [
            'dispatch_status' => $newStatus,
            'workflow_state' => self::mapStatusToWorkflowState($newStatus),
        ];

        if ($reason !== '') {
            $data['status_reason'] = $reason;
        }
        if ($note !== '') {
            $data['status_note'] = $note;
        }

        $timestampFields = self::buildTimestampFields($newStatus, $row);
        $data = array_merge($data, $timestampFields);

        return self::getStore()->update('DispatchEntry', $id, $data, $context, $row);
    }

    public static function delete(int $id, EntityContext $context): bool
    {
        return self::getStore()->delete('DispatchEntry', $id, $context);
    }

    public static function find(int $id, EntityContext $context): ?array
    {
        return self::getStore()->find('DispatchEntry', $id, $context);
    }

    public static function canEdit(array $row, EntityContext $context): bool
    {
        return self::getStore()->canEdit('DispatchEntry', $row, $context);
    }

    public static function canView(array $row, EntityContext $context): bool
    {
        return self::getStore()->canView('DispatchEntry', $row, $context);
    }

    public static function actionToStatus(string $action): ?string
    {
        return match (strtolower($action)) {
            'submit' => 'Ready',
            'approve' => 'Ready',
            'finalize' => 'Dispatched',
            'hold' => 'Hold',
            'resume' => 'Ready',
            'cancel' => 'Cancelled',
            'reopen' => 'Ready',
            default => null,
        };
    }

    public static function isEntityLifecycleAction(string $action): bool
    {
        return self::actionToStatus($action) !== null;
    }

    public static function isGovernanceOnlyAction(string $action): bool
    {
        return in_array(strtolower($action), ['reject'], true);
    }

    public static function isOperationalAction(string $action): bool
    {
        return in_array(strtolower($action), ['handoff'], true);
    }

    public static function rejectDispatch(int $id, EntityContext $context, string $reason = '', string $note = ''): bool
    {
        $row = self::find($id, $context);
        if (!$row) {
            throw new \RuntimeException('Dispatch entry not found.');
        }

        $data = [
            'approval_status' => 'Rejected',
            'status_reason' => $reason !== '' ? $reason : 'Rejected',
            'status_note' => $note,
        ];

        return self::getStore()->update('DispatchEntry', $id, $data, $context, $row);
    }

    private static function mapStatusToWorkflowState(string $status): string
    {
        return match (strtolower($status)) {
            'draft' => 'draft',
            'ready' => 'submitted',
            'partial' => 'submitted',
            'hold' => 'hold',
            'blocked' => 'hold',
            'dispatched' => 'finalized',
            'cancelled' => 'cancelled',
            default => 'draft',
        };
    }

    private static function buildTimestampFields(string $status, array $existingRow): array
    {
        $now = date('Y-m-d H:i:s');
        $fields = [];

        $releasedStatuses = ['Partial', 'Dispatched', 'Completed', 'Closed', 'Delivered'];
        $holdStatuses = ['Hold', 'Blocked'];
        $blockedStatuses = ['Hold', 'Blocked', 'Cancelled'];

        if (in_array($status, $releasedStatuses, true)) {
            $fields['released_at'] = $existingRow['released_at'] ?? $now;
            if ($status === 'Dispatched') {
                $fields['dispatched_at'] = $now;
            }
        }

        if (in_array($status, $blockedStatuses, true)) {
            $fields['blocked_at'] = $existingRow['blocked_at'] ?? $now;
        }

        $fields['last_transition_at'] = $now;

        return $fields;
    }
}

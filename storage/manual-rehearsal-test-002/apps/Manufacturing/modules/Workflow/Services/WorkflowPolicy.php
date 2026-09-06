<?php
declare(strict_types=1);

namespace Plugins\Workflow\Services;

use App\Core\AclPolicy;
use App\Core\Auth;

final class WorkflowPolicy
{
    public const MODULE_PRODUCTION_PLAN = 'production_plan';
    public const MODULE_ASSEMBLY_PLAN = 'assembly_plan';
    public const MODULE_QC_ENTRY = 'qc_entry';
    public const MODULE_DISPATCH_ENTRY = 'dispatch_entry';

    public const APPROVAL_DRAFT = 'Draft';
    public const APPROVAL_PENDING = 'Pending Approval';
    public const APPROVAL_APPROVED = 'Approved';
    public const APPROVAL_REJECTED = 'Rejected';
    public const APPROVAL_REOPENED = 'Reopened';

    /**
     * @return array<int,string>
     */
    public static function modules(): array
    {
        return [
            self::MODULE_PRODUCTION_PLAN,
            self::MODULE_ASSEMBLY_PLAN,
            self::MODULE_QC_ENTRY,
            self::MODULE_DISPATCH_ENTRY,
        ];
    }

    public static function normalizeApprovalStatus(string $status, string $fallback = self::APPROVAL_DRAFT): string
    {
        $v = strtolower(trim($status));
        return match ($v) {
            'draft' => self::APPROVAL_DRAFT,
            'pending', 'pending approval', 'pending_approval', 'submitted' => self::APPROVAL_PENDING,
            'approved' => self::APPROVAL_APPROVED,
            'rejected' => self::APPROVAL_REJECTED,
            'reopened' => self::APPROVAL_REOPENED,
            default => $fallback,
        };
    }

    public static function roleSlug(?array $user = null): string
    {
        return AclPolicy::roleSlug($user ?? Auth::user() ?? []);
    }

    public static function canSubmit(string $module, ?array $user = null): bool
    {
        return AclPolicy::can('workflow.' . $module . '.submit', $user ?? Auth::user());
    }

    public static function canApprove(string $module, ?array $user = null): bool
    {
        return AclPolicy::can('workflow.' . $module . '.approve', $user ?? Auth::user());
    }

    public static function canFinalize(string $module, ?array $user = null): bool
    {
        return AclPolicy::can('workflow.' . $module . '.finalize', $user ?? Auth::user());
    }

    public static function canReopen(string $module, ?array $user = null): bool
    {
        return AclPolicy::can('workflow.' . $module . '.reopen', $user ?? Auth::user());
    }

    public static function canOverrideLock(string $module, ?array $user = null): bool
    {
        return AclPolicy::can('workflow.' . $module . '.override_lock', $user ?? Auth::user());
    }

    /**
     * @param array<string,mixed> $row
     */
    public static function isLocked(string $module, array $row): bool
    {
        if (trim((string)($row['locked_at'] ?? '')) !== '') {
            return true;
        }

        $approval = self::normalizeApprovalStatus((string)($row['approval_status'] ?? ''), self::APPROVAL_DRAFT);

        if ($module === self::MODULE_PRODUCTION_PLAN) {
            if ($approval === self::APPROVAL_APPROVED) {
                return true;
            }

            $status = strtolower(trim((string)($row['status'] ?? '')));
            return in_array($status, ['running', 'active', 'in progress', 'in_progress', 'executing', 'completed', 'closed'], true);
        }

        if ($module === self::MODULE_QC_ENTRY) {
            if ($approval === self::APPROVAL_APPROVED) {
                return true;
            }

            $status = strtolower(trim((string)($row['status'] ?? '')));
            return in_array($status, ['approved', 'closed', 'final', 'rejected'], true);
        }

        if ($module === self::MODULE_DISPATCH_ENTRY) {
            $dispatch = strtolower(trim((string)($row['dispatch_status'] ?? '')));
            if ($dispatch === 'dispatched') {
                return true;
            }
            return $approval === self::APPROVAL_APPROVED && $dispatch === 'dispatched';
        }

        return false;
    }

    /**
     * @param array<string,mixed> $row
     */
    public static function canEditRecord(string $module, array $row, ?array $user = null): bool
    {
        if (!self::isLocked($module, $row)) {
            return true;
        }
        return self::canOverrideLock($module, $user);
    }

    /**
     * @param array<string,mixed> $row
     */
    public static function canTransitionApproval(string $module, array $row, string $action, ?array $user = null): bool
    {
        $action = strtolower(trim($action));
        $mappedAction = $action === 'unlock_override' ? 'reopen' : $action;

        $validation = WorkflowTransitionEngine::validateTransition($module, $row, $mappedAction, $user);
        if ((bool)($validation['ok'] ?? false)) {
            return true;
        }

        if ($action === 'unlock_override') {
            return self::canOverrideLock($module, $user) && self::isLocked($module, $row);
        }

        return false;
    }

    /**
     * @param array<string,mixed> $row
     */
    public static function validateForApproval(string $module, array $row): ?string
    {
        if ($module === self::MODULE_PRODUCTION_PLAN) {
            if (trim((string)($row['plan_date'] ?? '')) === '') {
                return 'Plan date is required before approval.';
            }
            if ((int)($row['machine_id'] ?? 0) <= 0 || (int)($row['product_id'] ?? 0) <= 0) {
                return 'Machine and part must be set before approval.';
            }
            if ((float)($row['planned_qty'] ?? 0) <= 0) {
                return 'Planned quantity must be greater than zero before approval.';
            }
            return null;
        }

        if ($module === self::MODULE_QC_ENTRY) {
            if ((int)($row['product_id'] ?? 0) <= 0) {
                return 'Part is required before QC approval.';
            }
            $checked = (float)($row['checked_qty'] ?? 0);
            $pass = (float)($row['pass_qty'] ?? 0);
            $fail = (float)($row['fail_qty'] ?? 0);
            if ($checked <= 0) {
                return 'Checked quantity must be greater than zero before QC approval.';
            }
            if ($pass + $fail - $checked > 0.0001) {
                return 'Pass + fail quantity cannot exceed checked quantity.';
            }
            return null;
        }

        if ($module === self::MODULE_DISPATCH_ENTRY) {
            if ((int)($row['product_id'] ?? 0) <= 0) {
                return 'Part is required before dispatch approval.';
            }
            if ((float)($row['dispatchable_qty'] ?? 0) <= 0) {
                return 'Dispatchable quantity must be greater than zero before dispatch approval.';
            }
            if (trim((string)($row['dispatch_date'] ?? '')) === '') {
                return 'Dispatch date is required before dispatch approval.';
            }
            return null;
        }

        return null;
    }
}

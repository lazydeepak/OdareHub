<?php
declare(strict_types=1);

namespace Plugins\Base\Services;

use Apps\Manufacturing\Services\MyWorkContributionService;

final class MyWorkService
{
    public const SECTION_NEEDS_ACTION = 'needs_action';
    public const SECTION_AWAITING_APPROVAL = 'awaiting_approval';
    public const SECTION_OVERDUE = 'overdue_escalated';
    public const SECTION_BLOCKED = 'blocked_followup';

    /**
     * @param array<string,mixed>|null $user
     * @param array<string,string> $input
     * @return array<string,mixed>
     */
    public static function build(?array $user, array $input = []): array
    {
        if (class_exists(MyWorkContributionService::class)) {
            return MyWorkContributionService::build($user, $input);
        }

        $user = is_array($user) ? $user : [];

        return [
            'user_name' => self::displayName($user),
            'user_role' => self::displayRole($user),
            'authority_role' => strtolower(trim((string)($user['authority_role'] ?? 'app_user'))),
            'dashboard_type' => 'operator',
            'kpi' => [
                'total' => 0,
                'needs_action' => 0,
                'awaiting_approval' => 0,
                'overdue_escalated' => 0,
                'blocked' => 0,
                'sla_breached' => 0,
            ],
            'sections' => [
                self::SECTION_NEEDS_ACTION => [],
                self::SECTION_AWAITING_APPROVAL => [],
                self::SECTION_OVERDUE => [],
                self::SECTION_BLOCKED => [],
            ],
            'grouped_sections' => [
                'primary_work' => [],
                'cross_functional_work' => [],
                'visibility_monitoring' => [],
            ],
            'all_items' => [],
            'has_approvals' => false,
            'has_overdue' => false,
            'has_blocked' => false,
            'role_links' => [],
            'cross_functional_access' => [],
            'primary_work_area' => '',
            'cross_work_areas' => [],
            'approval_modules' => [],
            'notification_summary' => [],
            'recent_notifications' => [],
            'unread_notifications' => 0,
            'active_assigned_apps' => [],
        ];
    }

    /**
     * @param array<string,mixed> $user
     */
    private static function displayName(array $user): string
    {
        $name = trim((string)($user['full_name'] ?? ''));
        if ($name === '') {
            $name = trim((string)($user['name'] ?? ''));
        }
        if ($name === '') {
            $name = trim((string)($user['email'] ?? 'User'));
        }
        return $name ?: 'User';
    }

    /**
     * @param array<string,mixed> $user
     */
    private static function displayRole(array $user): string
    {
        $authorityRole = strtolower(trim((string)($user['authority_role'] ?? '')));
        if ($authorityRole === 'platform_admin') {
            return 'Platform Admin';
        }

        $role = trim((string)($user['role'] ?? ''));
        $flat = strtolower(preg_replace('/[^a-z0-9]+/', '', $role) ?? '');
        if (in_array($flat, ['itadmin', 'itadministrator', 'admin', 'sysadmin', 'systemadmin', 'systemadministrator', 'platformadmin'], true)) {
            return 'Platform Admin';
        }

        return $role;
    }
}

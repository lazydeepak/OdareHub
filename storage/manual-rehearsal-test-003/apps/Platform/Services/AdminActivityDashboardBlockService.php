<?php
declare(strict_types=1);

namespace Apps\Platform\Services;

use App\Core\DB;

final class AdminActivityDashboardBlockService
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public static function prepare(int $limit = 5): array
    {
        $activityFeed = [];

        if (self::tableExists('acl_audit_log')) {
            self::appendAclActivity($activityFeed);
        }

        if (self::tableExists('workflow_approval_events')) {
            self::appendWorkflowActivity($activityFeed);
        }

        if (self::tableExists('identity_security_events')) {
            self::appendIdentityActivity($activityFeed);
        }

        usort($activityFeed, static function (array $a, array $b): int {
            return ((int)($b['ts'] ?? 0)) <=> ((int)($a['ts'] ?? 0));
        });

        return array_slice($activityFeed, 0, max(0, $limit));
    }

    /**
     * @param array<string,mixed> $context
     */
    public static function render(array $context): string
    {
        $viewPath = APP_ROOT . '/apps/Platform/Views/admin_blocks/activity.php';
        if (!is_file($viewPath)) {
            return '';
        }

        $bufferLevel = ob_get_level();
        ob_start();
        try {
            extract($context, EXTR_SKIP);
            include $viewPath;
            return (string)ob_get_clean();
        } finally {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
        }
    }

    private static function tableExists(string $table): bool
    {
        try {
            $escaped = DB::conn()->real_escape_string($table);
            return DB::fetchOne("SHOW TABLES LIKE '{$escaped}'") !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param array<int,array<string,mixed>> $feed
     */
    private static function appendActivity(
        array &$feed,
        string $source,
        string $title,
        string $detail,
        string $status,
        string $tone,
        string $timestamp,
        string $url = ''
    ): void {
        $ts = strtotime($timestamp);
        $feed[] = [
            'module' => t('admin.dashboard.activity.module'),
            'source' => $source,
            'title' => trim($title) !== '' ? trim($title) : t('admin.dashboard.activity.fallback_item'),
            'subtitle' => trim($detail),
            'status' => trim($status) !== '' ? trim($status) : t('admin.dashboard.activity.fallback_recorded'),
            'status_tone' => in_array($tone, ['success', 'warning', 'danger', 'info', 'neutral'], true) ? $tone : 'info',
            'url' => trim($url),
            'ts' => $ts !== false ? $ts : 0,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $feed
     */
    private static function appendAclActivity(array &$feed): void
    {
        try {
            $aclRows = (array)(DB::fetchAll('SELECT actor_email, actor_role, target_role, perm_key, new_state, created_at FROM acl_audit_log ORDER BY id DESC LIMIT 8') ?? []);
            foreach ($aclRows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $actor = trim((string)($row['actor_email'] ?? t('admin.dashboard.activity.system')));
                $targetRole = trim((string)($row['target_role'] ?? t('admin.dashboard.activity.role')));
                $permKey = trim((string)($row['perm_key'] ?? t('admin.dashboard.activity.permission')));
                $newState = trim((string)($row['new_state'] ?? t('admin.dashboard.activity.updated')));
                $when = (string)($row['created_at'] ?? date('Y-m-d H:i:s'));
                $tone = strtolower($newState) === 'deny' ? 'warning' : 'info';

                self::appendActivity(
                    $feed,
                    t('admin.dashboard.activity.source_acl'),
                    sprintf((string)t('admin.dashboard.activity.acl_change_format'), $actor, $targetRole),
                    $permKey,
                    strtoupper($newState),
                    $tone,
                    $when
                );
            }
        } catch (\Throwable $e) {
        }
    }

    /**
     * @param array<int,array<string,mixed>> $feed
     */
    private static function appendWorkflowActivity(array &$feed): void
    {
        try {
            $wfRows = (array)(DB::fetchAll('SELECT module_name, record_id, action_name, new_approval_status, acted_by, acted_at FROM workflow_approval_events ORDER BY id DESC LIMIT 8') ?? []);
            foreach ($wfRows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $actorId = (int)($row['acted_by'] ?? 0);
                $actor = $actorId > 0 ? sprintf((string)t('admin.dashboard.activity.user_id_format'), $actorId) : t('admin.dashboard.activity.system');
                $moduleName = trim((string)($row['module_name'] ?? t('admin.dashboard.activity.workflow')));
                $action = trim((string)($row['action_name'] ?? t('admin.dashboard.activity.updated')));
                $status = trim((string)($row['new_approval_status'] ?? t('admin.dashboard.activity.updated')));
                $recordId = (int)($row['record_id'] ?? 0);
                $when = (string)($row['acted_at'] ?? date('Y-m-d H:i:s'));
                $tone = in_array(strtolower($status), ['approved', 'release', 'released', 'done'], true) ? 'success' : (in_array(strtolower($status), ['rejected', 'blocked', 'hold'], true) ? 'danger' : 'info');

                self::appendActivity(
                    $feed,
                    t('admin.dashboard.activity.source_workflow'),
                    sprintf((string)t('admin.dashboard.activity.workflow_action_format'), $actor, $action),
                    $moduleName . ($recordId > 0 ? ' #' . $recordId : ''),
                    strtoupper($status),
                    $tone,
                    $when
                );
            }
        } catch (\Throwable $e) {
        }
    }

    /**
     * @param array<int,array<string,mixed>> $feed
     */
    private static function appendIdentityActivity(array &$feed): void
    {
        try {
            $secRows = (array)(DB::fetchAll('SELECT event_type, user_id, email_mask, outcome, created_at FROM identity_security_events ORDER BY id DESC LIMIT 8') ?? []);
            foreach ($secRows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $eventType = trim((string)($row['event_type'] ?? t('admin.dashboard.activity.security_event')));
                $userId = (int)($row['user_id'] ?? 0);
                $emailMask = trim((string)($row['email_mask'] ?? ''));
                $actor = $emailMask !== '' ? $emailMask : ($userId > 0 ? sprintf((string)t('admin.dashboard.activity.user_id_format'), $userId) : t('admin.dashboard.activity.unknown'));
                $outcome = trim((string)($row['outcome'] ?? t('admin.dashboard.activity.fallback_recorded')));
                $when = (string)($row['created_at'] ?? date('Y-m-d H:i:s'));
                $tone = in_array(strtolower($outcome), ['failed', 'denied', 'blocked'], true) ? 'warning' : 'success';

                self::appendActivity(
                    $feed,
                    t('admin.dashboard.activity.source_identity'),
                    sprintf((string)t('admin.dashboard.activity.identity_event_format'), $actor, str_replace('_', ' ', $eventType)),
                    t('admin.dashboard.activity.identity_detail'),
                    strtoupper($outcome),
                    $tone,
                    $when
                );
            }
        } catch (\Throwable $e) {
        }
    }
}

<?php
require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php';

if (!function_exists('e')) {
    function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$dashboardType = isset($dashboardType) ? (string)$dashboardType : 'operator';
$dashboardData = isset($dashboardData) && is_array($dashboardData) ? $dashboardData : [];
$user = isset($user) && is_array($user) ? $user : [];
$csrf = isset($csrf) ? (string)$csrf : '';
$locale = (string)(session_id() !== '' ? ($_SESSION['locale'] ?? 'en') : 'en');

$tr = static function (string $key) use ($locale): string {
    $dict = [
        'en' => [
            'dashboard.title' => 'Operational Dashboard',
            'dashboard.subtitle' => 'Your tasks requiring attention',
            'dashboard.kpi.total' => 'Total Tasks',
            'dashboard.kpi.in_progress' => 'In Progress',
            'dashboard.kpi.overdue' => 'Overdue',
            'dashboard.kpi.near_sla' => 'Near SLA Breach',
            'dashboard.kpi.on_track' => 'On Track',
            'dashboard.type.operator' => 'Operator Workboard',
            'dashboard.type.qc' => 'QC Inspection Workboard',
            'dashboard.type.dispatch' => 'Dispatch Workboard',
            'dashboard.type.admin' => 'Admin Dashboard',
            'task.col.app' => 'App',
            'task.col.title' => 'Task',
            'task.col.status' => 'Status',
            'task.col.time_in_state' => 'Time in State',
            'task.col.priority' => 'Priority',
            'task.col.actions' => 'Actions',
            'task.status.draft' => 'Draft',
            'task.status.in_progress' => 'In Progress',
            'task.status.completed' => 'Completed',
            'task.status.approved' => 'Approved',
            'task.status.dispatched' => 'Dispatched',
            'task.priority.high' => 'Overdue',
            'task.priority.medium' => 'Near SLA',
            'task.priority.low' => 'On Track',
            'task.empty' => 'No tasks requiring attention.',
            'action.start' => 'Start',
            'action.complete' => 'Complete',
            'action.approve' => 'Approve',
            'action.dispatch' => 'Dispatch',
            'action.view_history' => 'History',
            'action.view_details' => 'Details',
            'delay.overdue' => 'OVERDUE',
            'delay.near_breach' => 'Near Breach',
            'delay.on_track' => 'On Track',
        ],
        'ja' => [
            'dashboard.title' => '操作ダッシュボード',
            'dashboard.subtitle' => '対応が必要なタスク',
            'dashboard.kpi.total' => 'タスク合計',
            'dashboard.kpi.in_progress' => '進行中',
            'dashboard.kpi.overdue' => '期限超過',
            'dashboard.kpi.near_sla' => 'SLA接近',
            'dashboard.kpi.on_track' => '予定通り',
            'dashboard.type.operator' => 'オペレーター作業板',
            'dashboard.type.qc' => 'QC検査作業板',
            'dashboard.type.dispatch' => '出荷作業板',
            'dashboard.type.admin' => '管理者ダッシュボード',
            'task.col.app' => 'アプリ',
            'task.col.title' => 'タスク',
            'task.col.status' => 'ステータス',
            'task.col.time_in_state' => '状態継続時間',
            'task.col.priority' => '優先度',
            'task.col.actions' => 'アクション',
            'task.status.draft' => '下書き',
            'task.status.in_progress' => '進行中',
            'task.status.completed' => '完了',
            'task.status.approved' => '承認済',
            'task.status.dispatched' => '出荷済',
            'task.priority.high' => '期限超過',
            'task.priority.medium' => 'SLA接近',
            'task.priority.low' => '予定通り',
            'task.empty' => '対応が必要なタスクがありません。',
            'action.start' => '開始',
            'action.complete' => '完了',
            'action.approve' => '承認',
            'action.dispatch' => '出荷',
            'action.view_history' => '履歴',
            'action.view_details' => '詳細',
            'delay.overdue' => '期限超過',
            'delay.near_breach' => 'SLA接近',
            'delay.on_track' => '予定通り',
        ],
    ];
    return $dict[$locale][$key] ?? $key;
};

$kpis = is_array($dashboardData['kpis'] ?? null) ? $dashboardData['kpis'] : [];
$tasks = is_array($dashboardData['tasks'] ?? null) ? $dashboardData['tasks'] : [];
$hasOverdue = !empty($dashboardData['has_overdue']);
$hasDelayed = !empty($dashboardData['has_delayed']);
?>

<div class="u-style-a4812596aa">
    <div class="u-style-008a31c6f7">
        <h1 class="u-style-ee3743377b">
            <?php echo e($tr('dashboard.title')); ?>
        </h1>
        <p class="u-style-31f2a4b8a9">
            <?php echo e($tr('dashboard.subtitle')); ?> — 
            <span class="u-style-af5bb0afde"><?php echo e($tr('dashboard.type.' . $dashboardType)); ?></span>
        </p>
    </div>

    <!-- KPI Cards -->
    <div class="u-style-6918dc1ad8">
        <!-- Total Tasks KPI -->
        <div class="u-style-cd1d8c17e9">
            <div class="u-style-2b42d8e243">
                <?php echo e($tr('dashboard.kpi.total')); ?>
            </div>
            <div class="u-style-978eed0648">
                <?php echo (int)($kpis['total_count'] ?? 0); ?>
            </div>
        </div>

        <!-- In Progress KPI -->
        <div class="u-style-cd1d8c17e9">
            <div class="u-style-2b42d8e243">
                <?php echo e($tr('dashboard.kpi.in_progress')); ?>
            </div>
            <div class="u-style-a1c6d104b1">
                <?php echo (int)($kpis['in_progress_count'] ?? 0); ?>
            </div>
        </div>

        <!-- Overdue KPI -->
        <div class="ui-block" style="background: var(--color-danger-bg); border: 1px solid var(--color-danger-border); border-radius: 8px; padding: 1.5rem; box-shadow: var(--style-surface-shadow);">
            <div class="u-style-2b42d8e243">
                <?php echo e($tr('dashboard.kpi.overdue')); ?>
            </div>
            <div class="ui-block" style="font-size: 2rem; font-weight: 700; color: var(--color-danger-text);">
                <?php echo (int)($kpis['overdue_count'] ?? 0); ?>
            </div>
        </div>

        <!-- Near SLA KPI -->
        <div class="ui-block" style="background: var(--color-warning-bg); border: 1px solid var(--color-warning-border); border-radius: 8px; padding: 1.5rem; box-shadow: var(--style-surface-shadow);">
            <div class="u-style-2b42d8e243">
                <?php echo e($tr('dashboard.kpi.near_sla')); ?>
            </div>
            <div class="ui-block" style="font-size: 2rem; font-weight: 700; color: var(--color-warning-text);">
                <?php echo (int)($kpis['near_sla_count'] ?? 0); ?>
            </div>
        </div>

        <!-- On Track KPI -->
        <div class="u-style-9eea4d7cea">
            <div class="u-style-2b42d8e243">
                <?php echo e($tr('dashboard.kpi.on_track')); ?>
            </div>
            <div class="u-style-d4b24c5572">
                <?php echo (int)($kpis['on_track_count'] ?? 0); ?>
            </div>
        </div>
    </div>

    <!-- Tasks Table/List -->
    <div class="u-style-a1eb0d456d">
        <?php if (count($tasks) > 0): ?>
            <table class="u-style-8f1d301502">
                <thead>
                    <tr class="u-style-8a424bd062">
                        <th class="u-style-c9689f079d">
                            <?php echo e($tr('task.col.priority')); ?>
                        </th>
                        <th class="u-style-c9689f079d">
                            <?php echo e($tr('task.col.app')); ?>
                        </th>
                        <th class="u-style-c9689f079d">
                            <?php echo e($tr('task.col.title')); ?>
                        </th>
                        <th class="u-style-c9689f079d">
                            <?php echo e($tr('task.col.status')); ?>
                        </th>
                        <th class="u-style-c9689f079d">
                            <?php echo e($tr('task.col.time_in_state')); ?>
                        </th>
                        <th class="u-style-c9689f079d">
                            <?php echo e($tr('task.col.actions')); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tasks as $task): ?>
                        <?php
                        $isOverdue = !empty($task['is_overdue']);
                        $isDelayed = !empty($task['is_delayed']) && !$isOverdue;
                        $delayIndicator = $task['delay_indicator'] ?? 'on_track';
                        $priorityLabel = $task['is_high_priority'] ? 'high' : ($task['is_attention_needed'] ? 'medium' : 'low');
                        $rowBg = $isOverdue ? 'var(--color-danger-bg)' : ($isDelayed ? 'var(--color-warning-bg)' : 'var(--style-bg)');
                        $borderLeft = $isOverdue ? '4px solid var(--color-danger-border)' : ($isDelayed ? '4px solid var(--color-warning-border)' : '4px solid var(--style-border-soft)');
                        ?>
                        <tr style="border-bottom: 1px solid var(--style-border-soft); background: <?php echo $rowBg; ?>; border-left: <?php echo $borderLeft; ?>;">
                            <td class="u-style-e595f8134e">
                                <span style="display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 600; 
                                    <?php if ($isOverdue): ?>
                                        background: var(--style-subtle-bg); color: var(--text);
                                    <?php elseif ($isDelayed): ?>
                                        background: var(--style-subtle-bg); color: var(--text);
                                    <?php else: ?>
                                        background: var(--style-subtle-bg); color: var(--text);
                                    <?php endif; ?>
                                ">
                                    <?php echo e($tr('task.priority.' . $priorityLabel)); ?>
                                </span>
                            </td>
                            <td class="u-style-85dc90245f">
                                <?php echo e(substr($task['app_display_name'], 0, 20)); ?>
                            </td>
                            <td class="u-style-e595f8134e">
                                <div class="u-style-92c3894dbf">
                                    <?php echo e(substr($task['title'], 0, 40)); ?>
                                </div>
                                <?php if (!empty($task['description'])): ?>
                                    <div class="u-style-1b7efdfd92">
                                        <?php echo e(substr($task['description'], 0, 60)); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="u-style-e595f8134e">
                                <span class="u-style-148ce1280f">
                                    <?php echo e($tr('task.status.' . $task['status'])); ?>
                                </span>
                            </td>
                            <td class="u-style-85dc90245f">
                                <?php echo e($task['time_in_state_label']); ?>
                            </td>
                            <td class="u-style-e595f8134e">
                                <div class="u-style-d25a0ac8e3">
                                    <?php if (!empty($task['visible_actions']) && is_array($task['visible_actions'])): ?>
                                        <?php foreach (array_slice($task['visible_actions'], 0, 2) as $action): ?>
                                            <a href="javascript:void(0);" onclick="dashboardAction('<?php echo e($task['id']); ?>', '<?php echo e($action); ?>')" 
                                               style="display: inline-block; padding: 0.4rem 0.8rem; border: 1px solid var(--style-border-soft); background: var(--style-subtle-bg); color: var(--text); border-radius: 4px; font-size: 0.8rem; text-decoration: none; cursor: pointer; transition: all 0.2s;">
                                                <?php echo e($tr('action.' . str_replace('mark_', '', $action))); ?>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="u-style-44b07eb58b">
                <p class="u-style-694841380d">
                    <?php echo e($tr('task.empty')); ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function dashboardAction(taskId, action) {
    // Placeholder for task action handler
    console.log('Dashboard action:', taskId, action);
    // In a full implementation, this would trigger the appropriate workflow transition
}
</script>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php';

<?php
// Operator Layer View: tasks
// Shows tasks assigned to this operator. Operator can advance status (open → in progress → done).
// All composer scope variables available via include.
?>
<?php
    use Apps\Shell\Services\OperatorTaskService;

    $td        = (array)($data['tasks_focus'] ?? []);
    $tasks     = (array)($td['tasks']      ?? []);
    $openCount = (int)  ($td['open_count'] ?? 0);
    $tdError   = (string)($td['error']     ?? '');
    $tdUser    = rawurlencode((string)($data['username'] ?? ''));
    $tdBase    = '/u/' . $tdUser . '/tasks';
    $tdSaved   = (isset($_GET['saved'])  && $_GET['saved']  === '1');
    $tdDone    = (isset($_GET['done'])   && $_GET['done']   === '1');
    $tdFormErr = htmlspecialchars(trim((string)($_GET['error'] ?? '')));

    // Group by status for display
    $grouped = ['open' => [], 'in_progress' => [], 'done' => [], 'cancelled' => []];
    foreach ($tasks as $t) {
        $s = (string)($t['status'] ?? 'open');
        if (isset($grouped[$s])) {
            $grouped[$s][] = $t;
        }
    }

    $priorityClass = static function (string $p): string {
        return match ($p) {
            'urgent' => 'task-priority--urgent',
            'high'   => 'task-priority--high',
            'low'    => 'task-priority--low',
            default  => 'task-priority--medium',
        };
    };
?>
<section class="coverage-focus-section" aria-label="<?php echo htmlspecialchars($this->tr('operator.tasks.page_title', 'My Tasks')); ?>">

    <!-- Page header -->
    <div class="coverage-focus-header">
        <h2 class="surface-title"><?php echo htmlspecialchars($this->tr('operator.tasks.page_title', 'My Tasks')); ?></h2>
    </div>

    <!-- Flash states -->
    <?php if ($tdDone): ?>
        <p class="coverage-focus-saved"><?php echo htmlspecialchars($this->tr('operator.tasks.flash.done', 'Task marked as done.')); ?></p>
    <?php elseif ($tdSaved): ?>
        <p class="coverage-focus-saved"><?php echo htmlspecialchars($this->tr('operator.tasks.flash.advanced', 'Task status updated.')); ?></p>
    <?php endif; ?>
    <?php if ($tdFormErr !== ''): ?>
        <p class="coverage-focus-error"><?php echo $tdFormErr; ?></p>
    <?php endif; ?>
    <?php if ($tdError !== '' && $tdFormErr === ''): ?>
        <p class="coverage-focus-error"><?php echo htmlspecialchars($tdError); ?></p>
    <?php endif; ?>

    <!-- KPI strip -->
    <div class="coverage-kpi-strip">
        <div class="coverage-kpi-card<?php echo $openCount > 0 ? ' coverage-kpi-card--warn' : ''; ?>">
            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.tasks.kpi.open', 'Open Tasks')); ?></span>
            <span class="coverage-kpi-value"><?php echo $openCount; ?></span>
        </div>
        <div class="coverage-kpi-card">
            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.tasks.kpi.total', 'Total Assigned')); ?></span>
            <span class="coverage-kpi-value"><?php echo count($tasks); ?></span>
        </div>
        <div class="coverage-kpi-card">
            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.tasks.kpi.done', 'Completed')); ?></span>
            <span class="coverage-kpi-value"><?php echo count($grouped['done']); ?></span>
        </div>
    </div>

    <!-- ── Active tasks (open + in_progress) ──────────────────────────────── -->
    <?php
    $activeTasks = array_merge($grouped['open'], $grouped['in_progress']);
    ?>
    <?php if (count($activeTasks) === 0 && $tdError === ''): ?>
        <div class="coverage-orders-section">
            <p class="coverage-focus-empty"><?php echo htmlspecialchars($this->tr('operator.tasks.empty.active', 'No active tasks right now.')); ?></p>
        </div>
    <?php else: ?>
        <div class="coverage-orders-section">
            <h3 class="coverage-orders-title"><?php echo htmlspecialchars($this->tr('operator.tasks.section.active', 'Active Tasks')); ?></h3>
            <div class="operator-tasks-list">
            <?php foreach ($activeTasks as $task): ?>
                <?php
                $taskId   = (int)$task['id'];
                $status   = (string)$task['status'];
                $priority = (string)$task['priority'];
                $isOverdue = !empty($task['is_overdue']);
                $canAdvance = in_array($status, [OperatorTaskService::STATUS_OPEN, OperatorTaskService::STATUS_IN_PROGRESS], true);
                $nextLabel  = $status === OperatorTaskService::STATUS_OPEN
                    ? $this->tr('operator.tasks.action.start', 'Start')
                    : $this->tr('operator.tasks.action.done', 'Mark Done');
                $statusLabel = $status === OperatorTaskService::STATUS_OPEN
                    ? $this->tr('operator.tasks.status.open', 'Open')
                    : $this->tr('operator.tasks.status.in_progress', 'In Progress');
                ?>
                <div class="operator-task-card<?php echo $isOverdue ? ' operator-task-card--overdue' : ''; ?>"
                     data-task-id="<?php echo $taskId; ?>">
                    <div class="operator-task-card__header">
                        <span class="operator-task-priority <?php echo htmlspecialchars($priorityClass($priority)); ?>">
                            <?php echo htmlspecialchars($this->tr('operator.tasks.priority.' . $priority, ucfirst($priority))); ?>
                        </span>
                        <span class="operator-task-status operator-task-status--<?php echo htmlspecialchars($status); ?>">
                            <?php echo htmlspecialchars($statusLabel); ?>
                        </span>
                        <?php if ($isOverdue): ?>
                            <span class="operator-task-overdue-badge"><?php echo htmlspecialchars($this->tr('operator.tasks.badge.overdue', 'Overdue')); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="operator-task-card__body">
                        <p class="operator-task-title"><?php echo htmlspecialchars((string)$task['title']); ?></p>
                        <?php if (!empty($task['description'])): ?>
                            <div class="operator-task-desc"><?php echo $task['description_safe']; ?></div>
                        <?php endif; ?>
                        <div class="operator-task-meta">
                            <?php if (!empty($task['due_date'])): ?>
                                <span class="operator-task-meta-item">
                                    <?php echo htmlspecialchars($this->tr('operator.tasks.meta.due', 'Due:')); ?>
                                    <?php echo htmlspecialchars((string)$task['due_date']); ?>
                                    <?php if (!empty($task['due_shift'])): ?>
                                        &mdash; <?php echo htmlspecialchars((string)$task['due_shift']); ?>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($task['assigned_by_name'])): ?>
                                <span class="operator-task-meta-item">
                                    <?php echo htmlspecialchars($this->tr('operator.tasks.meta.assigned_by', 'Assigned by:')); ?>
                                    <?php echo htmlspecialchars((string)$task['assigned_by_name']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($canAdvance): ?>
                        <div class="operator-task-card__actions">
                            <form method="post" action="<?php echo htmlspecialchars($tdBase . '/advance'); ?>">
                                <input type="hidden" name="csrf"    value="<?php echo htmlspecialchars((string)\App\Core\Auth::csrfToken()); ?>">
                                <input type="hidden" name="task_id" value="<?php echo $taskId; ?>">
                                <button type="submit" class="btn-sm<?php echo $status === OperatorTaskService::STATUS_IN_PROGRESS ? ' btn-primary' : ''; ?>">
                                    <?php echo htmlspecialchars($nextLabel); ?>
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ── Completed tasks (collapsible) ──────────────────────────────────── -->
    <?php if (count($grouped['done']) > 0): ?>
        <div class="coverage-orders-section">
            <h3 class="coverage-orders-title"><?php echo htmlspecialchars($this->tr('operator.tasks.section.done', 'Completed Tasks')); ?></h3>
            <div class="operator-tasks-list operator-tasks-list--done">
            <?php foreach ($grouped['done'] as $task): ?>
                <div class="operator-task-card operator-task-card--done">
                    <div class="operator-task-card__header">
                        <span class="operator-task-priority <?php echo htmlspecialchars($priorityClass((string)$task['priority'])); ?>">
                            <?php echo htmlspecialchars($this->tr('operator.tasks.priority.' . (string)$task['priority'], ucfirst((string)$task['priority']))); ?>
                        </span>
                        <span class="operator-task-status operator-task-status--done">
                            <?php echo htmlspecialchars($this->tr('operator.tasks.status.done', 'Done')); ?>
                        </span>
                    </div>
                    <div class="operator-task-card__body">
                        <p class="operator-task-title"><?php echo htmlspecialchars((string)$task['title']); ?></p>
                        <?php if (!empty($task['completed_at'])): ?>
                            <span class="operator-task-meta-item">
                                <?php echo htmlspecialchars($this->tr('operator.tasks.meta.completed', 'Completed:')); ?>
                                <?php echo htmlspecialchars((string)$task['completed_at']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

</section>

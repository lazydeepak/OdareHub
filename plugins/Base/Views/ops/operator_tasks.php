<?php
// Admin view: Operator Task Management
// /ops/operator-tasks  — list, create, edit, cancel tasks
use Apps\Shell\Services\OperatorTaskService;

$tasks        = is_array($tasks ?? null) ? $tasks : [];
$operators    = is_array($operators ?? null) ? $operators : [];
$flashKey     = trim((string)($flash ?? ''));
$errorKey     = trim((string)($error ?? ''));
$csrf         = (string)($csrf ?? '');
$statusFilter = trim((string)($statusFilter ?? ''));
$editTask     = is_array($editTask ?? null) ? $editTask : null;

$flashText = $flashKey !== '' ? t($flashKey) : '';
$errorText = $errorKey !== '' ? t($errorKey) : '';

$statusOptions = [
    ''            => t('ops.operator_tasks.filter.all'),
    'open'        => t('ops.operator_tasks.status.open'),
    'in_progress' => t('ops.operator_tasks.status.in_progress'),
    'done'        => t('ops.operator_tasks.status.done'),
    'cancelled'   => t('ops.operator_tasks.status.cancelled'),
];

$priorityOptions = [
    'low'    => t('ops.operator_tasks.priority.low'),
    'medium' => t('ops.operator_tasks.priority.medium'),
    'high'   => t('ops.operator_tasks.priority.high'),
    'urgent' => t('ops.operator_tasks.priority.urgent'),
];

$statusBadgeClass = static function (string $s): string {
    return match ($s) {
        'open'        => 'badge-info',
        'in_progress' => 'badge-warn',
        'done'        => 'badge-ok',
        'cancelled'   => 'badge-muted',
        default       => '',
    };
};

$priorityBadgeClass = static function (string $p): string {
    return match ($p) {
        'urgent' => 'badge-error',
        'high'   => 'badge-warn',
        'medium' => 'badge-info',
        'low'    => 'badge-muted',
        default  => '',
    };
};
?>

<!-- Flash messages -->
<?php if ($flashText !== ''): ?>
    <div class="note success u-style-a0612bbb59"><?= e($flashText) ?></div>
<?php endif; ?>
<?php if ($errorText !== ''): ?>
    <div class="note warning u-style-a0612bbb59"><?= e($errorText) ?></div>
<?php endif; ?>

<!-- ── Create / Edit form ──────────────────────────────────────────────────── -->
<section class="card">
    <h2 class="u-style-a353e69c3e">
        <?= e($editTask !== null ? t('ops.operator_tasks.form.title_edit') : t('ops.operator_tasks.form.title_create')) ?>
    </h2>
    <form class="u-style-c317fec12a" method="post" action="/ops/operator-tasks/save">
        <input type="hidden" name="csrf"    value="<?= e($csrf) ?>">
        <?php if ($editTask !== null): ?>
            <input type="hidden" name="task_id" value="<?= (int)($editTask['id'] ?? 0) ?>">
        <?php endif; ?>

        <!-- Row 1: assignee + title -->
        <div class="u-style-2e7aa5866c">
            <div class="ui-block">
                <label for="ot_assigned_to" class="form-label"><?= e(t('ops.operator_tasks.form.assigned_to')) ?> <span class="u-style-791aae7650">*</span></label>
                <select id="ot_assigned_to" name="assigned_to" required class="form-select">
                    <option value=""><?= e(t('ops.operator_tasks.form.select_operator')) ?></option>
                    <?php foreach ($operators as $op): ?>
                        <option value="<?= (int)$op['id'] ?>"
                            <?= ($editTask !== null && (int)$op['id'] === (int)($editTask['assigned_to'] ?? 0)) ? 'selected' : '' ?>>
                            <?= e($op['display_name'] ?: $op['email']) ?> &lt;<?= e($op['email']) ?>&gt;
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ui-block">
                <label for="ot_title" class="form-label"><?= e(t('ops.operator_tasks.form.title_field')) ?> <span class="u-style-791aae7650">*</span></label>
                <input id="ot_title" name="title" type="text" maxlength="255" required class="form-input"
                       value="<?= e((string)($editTask['title'] ?? '')) ?>"
                       placeholder="<?= e(t('ops.operator_tasks.form.title_placeholder')) ?>">
            </div>
        </div>

        <!-- Row 2: description -->
        <div class="ui-block">
            <label for="ot_description" class="form-label"><?= e(t('ops.operator_tasks.form.description')) ?></label>
            <textarea id="ot_description" name="description" rows="3" maxlength="3000" class="form-textarea"
                      placeholder="<?= e(t('ops.operator_tasks.form.description_placeholder')) ?>"><?= e((string)($editTask['description'] ?? '')) ?></textarea>
        </div>

        <!-- Row 3: priority + due date + due shift -->
        <div class="u-style-7936f1e61f">
            <div class="ui-block">
                <label for="ot_priority" class="form-label"><?= e(t('ops.operator_tasks.form.priority')) ?></label>
                <select id="ot_priority" name="priority" class="form-select">
                    <?php foreach ($priorityOptions as $val => $lbl): ?>
                        <option value="<?= e($val) ?>"
                            <?= ((string)($editTask['priority'] ?? 'medium') === $val) ? 'selected' : '' ?>>
                            <?= e($lbl) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ui-block">
                <label for="ot_due_date" class="form-label"><?= e(t('ops.operator_tasks.form.due_date')) ?></label>
                <input id="ot_due_date" name="due_date" type="date" class="form-input"
                       value="<?= e((string)($editTask['due_date'] ?? '')) ?>">
            </div>
            <div class="ui-block">
                <label for="ot_due_shift" class="form-label"><?= e(t('ops.operator_tasks.form.due_shift')) ?></label>
                <select id="ot_due_shift" name="due_shift" class="form-select">
                    <option value=""><?= e(t('ops.operator_tasks.form.shift.any')) ?></option>
                    <option value="morning"   <?= ((string)($editTask['due_shift'] ?? '') === 'morning')   ? 'selected' : '' ?>><?= e(t('ops.operator_tasks.form.shift.morning')) ?></option>
                    <option value="afternoon" <?= ((string)($editTask['due_shift'] ?? '') === 'afternoon') ? 'selected' : '' ?>><?= e(t('ops.operator_tasks.form.shift.afternoon')) ?></option>
                    <option value="night"     <?= ((string)($editTask['due_shift'] ?? '') === 'night')     ? 'selected' : '' ?>><?= e(t('ops.operator_tasks.form.shift.night')) ?></option>
                </select>
            </div>
        </div>

        <!-- Row 4: status (edit only) -->
        <?php if ($editTask !== null): ?>
        <div class="ui-block">
            <label for="ot_status" class="form-label"><?= e(t('ops.operator_tasks.form.status')) ?></label>
            <select id="ot_status" name="status" class="form-select">
                <?php foreach ($statusOptions as $val => $lbl): ?>
                    <?php if ($val === '') continue; ?>
                    <option value="<?= e($val) ?>"
                        <?= ((string)($editTask['status'] ?? 'open') === $val) ? 'selected' : '' ?>>
                        <?= e($lbl) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="u-style-78cead6503">
            <button type="submit" class="btn btn-primary">
                <?= e($editTask !== null ? t('ops.operator_tasks.form.btn_save') : t('ops.operator_tasks.form.btn_create')) ?>
            </button>
            <?php if ($editTask !== null): ?>
                <a href="/ops/operator-tasks" class="btn"><?= e(t('ops.operator_tasks.form.btn_cancel_edit')) ?></a>
            <?php endif; ?>
        </div>
    </form>
</section>

<!-- ── Filter bar ──────────────────────────────────────────────────────────── -->
<section class="card u-style-e17e344930">
    <div class="u-style-bdd0d371f4">
        <strong><?= e(t('ops.operator_tasks.filter.label')) ?></strong>
        <?php foreach ($statusOptions as $val => $lbl): ?>
            <a href="/ops/operator-tasks<?= $val !== '' ? '?status=' . rawurlencode($val) : '' ?>"
               class="btn btn-sm<?= $statusFilter === $val ? ' btn-primary' : '' ?>">
                <?= e($lbl) ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- ── Task list ───────────────────────────────────────────────────────────── -->
<section class="card">
    <h2 class="u-style-a353e69c3e"><?= e(t('ops.operator_tasks.list.title')) ?></h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th><?= e(t('ops.operator_tasks.col.id')) ?></th>
                    <th><?= e(t('ops.operator_tasks.col.title')) ?></th>
                    <th><?= e(t('ops.operator_tasks.col.assigned_to')) ?></th>
                    <th><?= e(t('ops.operator_tasks.col.priority')) ?></th>
                    <th><?= e(t('ops.operator_tasks.col.status')) ?></th>
                    <th><?= e(t('ops.operator_tasks.col.due')) ?></th>
                    <th><?= e(t('ops.operator_tasks.col.created')) ?></th>
                    <th><?= e(t('ops.operator_tasks.col.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tasks)): ?>
                    <tr><td colspan="8" class="muted"><?= e(t('ops.operator_tasks.list.empty')) ?></td></tr>
                <?php else: ?>
                    <?php foreach ($tasks as $task): ?>
                        <?php
                        $tid     = (int)($task['id'] ?? 0);
                        $tstatus = (string)($task['status'] ?? 'open');
                        $tprio   = (string)($task['priority'] ?? 'medium');
                        $isActive = in_array($tstatus, ['open', 'in_progress'], true);
                        $isOverdue = !empty($task['is_overdue']);
                        ?>
                        <tr<?= $isOverdue ? ' style="background:rgba(var(--color-error-rgb),.06)"' : '' ?>>
                            <td><?= $tid ?></td>
                            <td>
                                <div class="ui-block"><?= e((string)($task['title'] ?? '')) ?></div>
                                <?php if (!empty($task['description'])): ?>
                                    <div class="muted u-style-f6727ac187"><?= e(mb_strimwidth((string)($task['description'] ?? ''), 0, 80, '…')) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="ui-block"><?= e((string)($task['assigned_to_name'] ?? '—')) ?></div>
                            </td>
                            <td>
                                <span class="badge <?= e($priorityBadgeClass($tprio)) ?>">
                                    <?= e(t('ops.operator_tasks.priority.' . $tprio)) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= e($statusBadgeClass($tstatus)) ?>">
                                    <?= e(t('ops.operator_tasks.status.' . $tstatus)) ?>
                                </span>
                                <?php if ($isOverdue): ?>
                                    <span class="badge badge-error u-style-647a51d75c"><?= e(t('ops.operator_tasks.badge.overdue')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= e(!empty($task['due_date']) ? (string)$task['due_date'] : '—') ?><?= !empty($task['due_shift']) ? '<br><span class="muted u-style-41b1f51b79">' . e((string)$task['due_shift']) . '</span>' : '' ?></td>
                            <td class="muted u-style-f6727ac187"><?= e((string)($task['created_at'] ?? '')); ?></td>
                            <td>
                                <div class="u-style-c8d67009fe">
                                    <?php if ($isActive): ?>
                                        <a href="/ops/operator-tasks?edit=<?= $tid ?>" class="btn btn-sm"><?= e(t('ops.operator_tasks.action.edit')) ?></a>
                                        <form method="post" action="/ops/operator-tasks/cancel" onsubmit="return confirm('<?= e(t('ops.operator_tasks.confirm.cancel')) ?>');">
                                            <input type="hidden" name="csrf"    value="<?= e($csrf) ?>">
                                            <input type="hidden" name="task_id" value="<?= $tid ?>">
                                            <button type="submit" class="btn btn-sm btn-warn"><?= e(t('ops.operator_tasks.action.cancel')) ?></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (!$isActive): ?>
                                        <form method="post" action="/ops/operator-tasks/delete" onsubmit="return confirm('<?= e(t('ops.operator_tasks.confirm.delete')) ?>');">
                                            <input type="hidden" name="csrf"    value="<?= e($csrf) ?>">
                                            <input type="hidden" name="task_id" value="<?= $tid ?>">
                                            <button type="submit" class="btn btn-sm btn-error"><?= e(t('ops.operator_tasks.action.delete')) ?></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

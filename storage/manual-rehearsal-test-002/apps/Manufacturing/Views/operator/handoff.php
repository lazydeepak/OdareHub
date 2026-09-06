<?php
// Operator Layer View: handoff
// Shift handoff notes — read notes from previous shift, write notes for next shift.
// All composer scope variables available via include.
?>
<?php
    $hfo         = (array)($data['handoff_focus'] ?? []);
    $hfoNotes    = (array)($hfo['notes']          ?? []);
    $hfoUnacked  = (int)  ($hfo['unacked_count']  ?? 0);
    $hfoEmpty    = (count($hfoNotes) === 0);
    $hfoError    = (string)($hfo['error']         ?? '');
    $hfoUser     = rawurlencode((string)($data['username'] ?? ''));
    $hfoRedirect = '/u/' . $hfoUser . '/handoff';
    $hfoSaved    = (isset($_GET['saved']) && $_GET['saved'] === '1');
    $hfoFormErr  = htmlspecialchars(trim((string)($_GET['error'] ?? '')));

    // Current operator user id (needed to distinguish own notes)
    $hfoCurrentUserId = (int)(\App\Core\Auth::user()['id'] ?? 0);

    $shiftOptions = [
        ''          => $this->tr('operator.handoff.shift.unspecified', '(not specified)'),
        'morning'   => $this->tr('operator.handoff.shift.morning',     'Morning'),
        'afternoon' => $this->tr('operator.handoff.shift.afternoon',   'Afternoon'),
        'night'     => $this->tr('operator.handoff.shift.night',       'Night'),
    ];
?>
<section class="coverage-focus-section" aria-label="<?php echo htmlspecialchars($this->tr('operator.handoff.page_title', 'Shift Handoff')); ?>">

    <!-- Page header -->
    <div class="coverage-focus-header">
        <h2 class="surface-title"><?php echo htmlspecialchars($this->tr('operator.handoff.page_title', 'Shift Handoff')); ?></h2>
        <?php if ($hfoUnacked > 0): ?>
            <form method="post" action="<?php echo htmlspecialchars('/u/' . $hfoUser . '/handoff/ack'); ?>" class="operator-ml-auto">
                <input type="hidden" name="csrf"    value="<?php echo htmlspecialchars((string)\App\Core\Auth::csrfToken()); ?>">
                <input type="hidden" name="ack_all" value="1">
                <button type="submit" class="btn-sm"><?php echo htmlspecialchars($this->tr('operator.handoff.ack_all', 'Acknowledge all')); ?></button>
            </form>
        <?php endif; ?>
    </div>

    <!-- Flash states -->
    <?php if ($hfoSaved): ?>
        <p class="coverage-focus-saved"><?php echo htmlspecialchars($this->tr('operator.handoff.saved', 'Handoff note saved.')); ?></p>
    <?php endif; ?>
    <?php if ($hfoFormErr !== ''): ?>
        <p class="coverage-focus-error"><?php echo $hfoFormErr; ?></p>
    <?php endif; ?>
    <?php if ($hfoError !== '' && $hfoFormErr === ''): ?>
        <p class="coverage-focus-error"><?php echo htmlspecialchars($hfoError); ?></p>
    <?php endif; ?>

    <!-- KPI strip -->
    <div class="coverage-kpi-strip">
        <div class="coverage-kpi-card<?php echo $hfoUnacked > 0 ? ' coverage-kpi-card--warn' : ''; ?>">
            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.handoff.kpi.unread', 'Unread Notes')); ?></span>
            <span class="coverage-kpi-value"><?php echo $hfoUnacked; ?></span>
        </div>
        <div class="coverage-kpi-card">
            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.handoff.kpi.total', 'Total Today')); ?></span>
            <span class="coverage-kpi-value"><?php echo count($hfoNotes); ?></span>
        </div>
    </div>

    <!-- Write a note form -->
    <div class="coverage-orders-section">
        <h3 class="coverage-orders-title"><?php echo htmlspecialchars($this->tr('operator.handoff.form.title', 'Leave a note for next shift')); ?></h3>
        <form method="post"
              action="<?php echo htmlspecialchars('/u/' . $hfoUser . '/handoff/create'); ?>"
              class="handoff-write-form">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string)\App\Core\Auth::csrfToken()); ?>">

            <div class="handoff-form-row">
                <label for="hfo_shift_label" class="handoff-form-label">
                    <?php echo htmlspecialchars($this->tr('operator.handoff.form.shift', 'Shift')); ?>
                </label>
                <select id="hfo_shift_label" name="shift_label" class="handoff-form-select">
                    <?php foreach ($shiftOptions as $val => $lbl): ?>
                        <option value="<?php echo htmlspecialchars($val); ?>"><?php echo htmlspecialchars($lbl); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="handoff-form-row">
                <label for="hfo_note_text" class="handoff-form-label">
                    <?php echo htmlspecialchars($this->tr('operator.handoff.form.note', 'Note')); ?>
                    <span class="handoff-form-hint"><?php echo htmlspecialchars($this->tr('operator.handoff.form.note_hint', '(max 5000 characters)')); ?></span>
                </label>
                <textarea id="hfo_note_text"
                          name="note_text"
                          rows="5"
                          maxlength="5000"
                          required
                          placeholder="<?php echo htmlspecialchars($this->tr('operator.handoff.form.placeholder', 'Describe any issues, alerts, or status for the next shift...')); ?>"
                          class="handoff-form-textarea"></textarea>
            </div>

            <div class="handoff-form-actions">
                <button type="submit" class="btn-sm btn-primary">
                    <?php echo htmlspecialchars($this->tr('operator.handoff.form.submit', 'Save handoff note')); ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Existing notes for today -->
    <div class="coverage-orders-section">
        <h3 class="coverage-orders-title">
            <?php echo htmlspecialchars($this->tr('operator.handoff.notes.title', "Today's Notes")); ?>
            <span class="coverage-orders-count"><?php echo count($hfoNotes); ?></span>
        </h3>

        <?php if ($hfoEmpty): ?>
            <p class="coverage-focus-empty"><?php echo htmlspecialchars($this->tr('operator.handoff.notes.empty', 'No notes for today yet.')); ?></p>
        <?php else: ?>
        <div class="coverage-orders-table-wrap">
            <table class="coverage-orders-table handoff-notes-table">
                <thead>
                    <tr>
                        <th><?php echo htmlspecialchars($this->tr('operator.handoff.col.shift', 'Shift')); ?></th>
                        <th><?php echo htmlspecialchars($this->tr('operator.handoff.col.author', 'Author')); ?></th>
                        <th><?php echo htmlspecialchars($this->tr('operator.handoff.col.note', 'Note')); ?></th>
                        <th><?php echo htmlspecialchars($this->tr('operator.handoff.col.time', 'Time')); ?></th>
                        <th><?php echo htmlspecialchars($this->tr('operator.handoff.col.status', 'Status')); ?></th>
                        <th><?php echo htmlspecialchars($this->tr('operator.handoff.col.actions', 'Actions')); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($hfoNotes as $note):
                    $noteId      = (int)($note['id'] ?? 0);
                    $noteShift   = htmlspecialchars((string)($note['shift_label'] ?? ''));
                    $noteAuthor  = htmlspecialchars((string)($note['author_display'] ?? ''));
                    $noteSafe    = (string)($note['note_safe'] ?? htmlspecialchars((string)($note['note_text'] ?? '')));
                    $noteTime    = htmlspecialchars(substr((string)($note['created_at'] ?? ''), 0, 16));
                    $noteAcked   = (bool)($note['acked'] ?? false);
                    $noteIsOwn   = ((int)($note['author_user_id'] ?? 0) === $hfoCurrentUserId);
                    $rowCls      = !$noteAcked && !$noteIsOwn ? ' coverage-orders-row--warn' : '';
                ?>
                    <tr class="coverage-orders-row<?php echo $rowCls; ?>">
                        <td><?php echo $noteShift !== '' ? $noteShift : '<em>' . htmlspecialchars($this->tr('operator.handoff.shift.unspecified', '-')) . '</em>'; ?></td>
                        <td><?php echo $noteAuthor; ?></td>
                        <td class="handoff-note-cell"><?php echo $noteSafe; ?></td>
                        <td class="handoff-time-cell"><?php echo $noteTime; ?></td>
                        <td>
                            <?php if ($noteIsOwn): ?>
                                <span class="handoff-badge handoff-badge--own"><?php echo htmlspecialchars($this->tr('operator.handoff.status.own', 'Your note')); ?></span>
                            <?php elseif ($noteAcked): ?>
                                <span class="handoff-badge handoff-badge--read"><?php echo htmlspecialchars($this->tr('operator.handoff.status.read', 'Read')); ?></span>
                            <?php else: ?>
                                <span class="handoff-badge handoff-badge--unread"><?php echo htmlspecialchars($this->tr('operator.handoff.status.unread', 'Unread')); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="operator-action-cell">
                            <?php if (!$noteIsOwn && !$noteAcked): ?>
                            <form method="post" action="<?php echo htmlspecialchars('/u/' . $hfoUser . '/handoff/ack'); ?>">
                                <input type="hidden" name="csrf"       value="<?php echo htmlspecialchars((string)\App\Core\Auth::csrfToken()); ?>">
                                <input type="hidden" name="handoff_id" value="<?php echo $noteId; ?>">
                                <button type="submit" class="btn-sm"><?php echo htmlspecialchars($this->tr('operator.handoff.ack', 'Acknowledge')); ?></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</section>

<style>
.handoff-write-form { padding: var(--spacing-4) 0; }
.handoff-form-row   { display: flex; flex-direction: column; gap: var(--spacing-1); margin-bottom: var(--spacing-3); }
.handoff-form-label { font-size: .85rem; font-weight: 600; color: var(--text-secondary); }
.handoff-form-hint  { font-weight: 400; font-size: .75rem; color: var(--text-tertiary); margin-left: .4rem; }
.handoff-form-select,
.handoff-form-textarea {
    width: 100%; max-width: 640px; padding: .5rem .75rem;
    border: 1px solid var(--border-default); border-radius: 6px;
    font-size: .9rem; background: var(--bg-input); color: var(--text-primary);
}
.handoff-form-textarea { resize: vertical; }
.handoff-form-actions  { display: flex; gap: .5rem; margin-top: .5rem; }
.handoff-notes-table .handoff-note-cell { max-width: 360px; white-space: pre-wrap; word-break: break-word; }
.handoff-notes-table .handoff-time-cell { white-space: nowrap; font-size: .8rem; color: var(--text-secondary); }
.handoff-badge        { display: inline-block; padding: .15rem .5rem; border-radius: 99px; font-size: .75rem; font-weight: 600; }
.handoff-badge--own   { background: var(--badge-info-bg); color: var(--badge-info-fg); }
.handoff-badge--read  { background: var(--badge-ok-bg); color: var(--badge-ok-fg); }
.handoff-badge--unread{ background: var(--badge-warn-bg); color: var(--badge-warn-fg); }
.coverage-focus-saved { background: var(--badge-ok-bg); color: var(--badge-ok-fg); border-radius: 6px; padding: .5rem 1rem; margin-bottom: .75rem; font-size: .9rem; }
@media (max-width: 640px) {
    .handoff-notes-table .handoff-note-cell { max-width: 160px; }
    .handoff-notes-table th:nth-child(4),
    .handoff-notes-table td:nth-child(4) { display: none; }
}
</style>

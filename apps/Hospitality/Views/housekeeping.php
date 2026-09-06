<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<?php
$flashOk = is_string($ok ?? null) && $ok !== '' ? t($ok) : '';
$flashErr = is_string($err ?? null) && $err !== '' ? t($err) : '';
$csrf = \App\Core\Auth::csrfToken();
?>

<section class="card">
    <h1><?= e(t('hospitality.nav.housekeeping')) ?></h1>
    <?php if ($flashOk !== ''): ?><p class="hosp-chip hosp-chip--ok"><?= e($flashOk) ?></p><?php endif; ?>
    <?php if ($flashErr !== ''): ?><p class="hosp-chip hosp-chip--danger"><?= e($flashErr) ?></p><?php endif; ?>
</section>

<?php if (!is_array($rows)): ?>
<section class="card">
    <p class="text-muted"><?= e(t('hospitality.module.schema_pending')) ?></p>
    <p class="text-muted"><?= e(t('hospitality.module.l1_note')) ?></p>
</section>
<?php else: ?>
<section class="card">
    <h2><?= e(t('hospitality.hk.board_title')) ?> (<?= count($rows) ?>)</h2>
    <p class="text-muted"><?= e(t('hospitality.hk.current_status_note')) ?></p>
    <?php if (count($rows) === 0): ?>
        <p class="text-muted"><?= e(t('hospitality.hk.no_active_rooms')) ?></p>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th><?= e(t('hospitality.field.room_number')) ?></th>
                    <th><?= e(t('hospitality.field.room_type')) ?></th>
                    <th><?= e(t('hospitality.field.status')) ?></th>
                    <th><?= e(t('hospitality.hk.last_cleaned')) ?></th>
                    <th><?= e(t('hospitality.hk.assigned_to')) ?></th>
                    <th><?= e(t('hospitality.field.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <?php
                    $status = (string)($row['hk_status'] ?? '');
                    $statusKey = $status !== '' ? 'hospitality.hk_status.' . $status : 'hospitality.hk_status.pending';
                ?>
                <tr>
                    <td><?= e((string)$row['room_number']) ?></td>
                    <td><?= e(t('hospitality.room_type.' . (string)$row['room_type'])) ?></td>
                    <td>
                        <span class="hosp-chip <?= in_array($status, ['clean','inspected'], true) ? 'hosp-chip--ok' : ($status === '' ? 'hosp-chip--neutral' : 'hosp-chip--warning') ?>">
                            <?= e(t($statusKey)) ?>
                        </span>
                    </td>
                    <td><?= e((string)($row['last_cleaned_at'] ?? '')) ?></td>
                    <td><?= e((string)($row['assigned_to'] ?? '')) ?></td>
                    <td>
                        <details>
                            <summary><?= e(t('hospitality.hk.update_status')) ?></summary>
                            <?php if ($status === ''): ?>
                                <p class="text-muted"><?= e(t('hospitality.hk.pending_note')) ?></p>
                            <?php endif; ?>
                            <form method="post" action="/apps/hospitality/housekeeping/status" class="form-grid">
                                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                <input type="hidden" name="room_id" value="<?= (int)$row['room_id'] ?>">
                                <label class="form-field"><?= e(t('hospitality.field.status')) ?>
                                    <select name="hk_status">
                                        <?php foreach (['clean','dirty','inspected','maintenance','out_of_service'] as $hk): ?>
                                            <option value="<?= e($hk) ?>" <?= $status === $hk ? 'selected' : '' ?>>
                                                <?= e(t('hospitality.hk_status.' . $hk)) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label class="form-field"><?= e(t('hospitality.hk.assigned_to')) ?>
                                    <input type="text" name="assigned_to" maxlength="190" value="<?= e((string)($row['assigned_to'] ?? '')) ?>">
                                </label>
                                <label class="form-field form-field-wide"><?= e(t('hospitality.field.note')) ?>
                                    <input type="text" name="note" maxlength="1000" value="<?= e((string)($row['note'] ?? '')) ?>">
                                </label>
                                <div class="form-actions">
                                    <button type="submit" class="btn ok"><?= e(t('hospitality.action.save_changes')) ?></button>
                                </div>
                            </form>
                        </details>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<?php
$flashOk = is_string($ok ?? null) && $ok !== '' ? t($ok) : '';
$flashErr = is_string($err ?? null) && $err !== '' ? t($err) : '';
$csrf = \App\Core\Auth::csrfToken();
?>

<section class="card">
    <h1><?= e(t('hospitality.nav.rooms')) ?></h1>
    <?php if ($flashOk !== ''): ?><p class="hosp-chip hosp-chip--ok"><?= e($flashOk) ?></p><?php endif; ?>
    <?php if ($flashErr !== ''): ?><p class="hosp-chip hosp-chip--danger"><?= e($flashErr) ?></p><?php endif; ?>
</section>

<?php if (!is_array($rows)): ?>
<section class="card">
    <p class="text-muted"><?= e(t('hospitality.module.schema_pending')) ?></p>
    <p class="text-muted"><?= e(t('hospitality.module.l1_note')) ?></p>
</section>
<?php else: ?>

<?php if (count($rows) === 0): ?>
<section class="card">
    <p><strong><?= e(t('hospitality.module.records_count', ['count' => 0])) ?></strong></p>
</section>
<?php endif; ?>

<section class="card">
    <h2><?= e(t('hospitality.action.create_room')) ?></h2>
    <form method="post" action="/apps/hospitality/rooms/create" class="form-grid">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <label class="form-field"><?= e(t('hospitality.field.room_number')) ?>
            <input type="text" name="room_number" maxlength="40" required>
        </label>
        <label class="form-field"><?= e(t('hospitality.field.room_type')) ?>
            <select name="room_type">
                <?php foreach (['standard','single','double','suite','family'] as $rt): ?>
                    <option value="<?= e($rt) ?>"><?= e(t('hospitality.room_type.' . $rt)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="form-field"><?= e(t('hospitality.field.floor')) ?>
            <input type="number" name="floor" min="-9" max="999">
        </label>
        <label class="form-field"><?= e(t('hospitality.field.note')) ?>
            <input type="text" name="note" maxlength="1000">
        </label>
        <div class="form-actions">
            <button type="submit" class="btn ok"><?= e(t('hospitality.action.create')) ?></button>
        </div>
    </form>
</section>

<section class="card">
    <h2><?= e(t('hospitality.module.records_count', ['count' => count($rows)])) ?></h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th><?= e(t('hospitality.field.room_number')) ?></th>
                    <th><?= e(t('hospitality.field.room_type')) ?></th>
                    <th><?= e(t('hospitality.field.floor')) ?></th>
                    <th><?= e(t('hospitality.field.status')) ?></th>
                    <th><?= e(t('hospitality.field.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $room): ?>
                <tr>
                    <td><?= e((string)$room['room_number']) ?></td>
                    <td><?= e(t('hospitality.room_type.' . (string)$room['room_type'])) ?></td>
                    <td><?= e((string)($room['floor'] ?? '')) ?></td>
                    <td>
                        <span class="hosp-chip <?= ($room['room_status'] ?? '') === 'active' ? 'hosp-chip--ok' : 'hosp-chip--neutral' ?>">
                            <?= e(t('hospitality.status.' . (string)$room['room_status'])) ?>
                        </span>
                    </td>
                    <td>
                        <details>
                            <summary><?= e(t('hospitality.action.edit')) ?></summary>
                            <form method="post" action="/apps/hospitality/rooms/update" class="form-grid">
                                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                <input type="hidden" name="id" value="<?= (int)$room['id'] ?>">
                                <label class="form-field"><?= e(t('hospitality.field.room_number')) ?>
                                    <input type="text" name="room_number" maxlength="40" value="<?= e((string)$room['room_number']) ?>" required>
                                </label>
                                <label class="form-field"><?= e(t('hospitality.field.room_type')) ?>
                                    <select name="room_type">
                                        <?php foreach (['standard','single','double','suite','family'] as $rt): ?>
                                            <option value="<?= e($rt) ?>" <?= $room['room_type'] === $rt ? 'selected' : '' ?>><?= e(t('hospitality.room_type.' . $rt)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label class="form-field"><?= e(t('hospitality.field.floor')) ?>
                                    <input type="number" name="floor" min="-9" max="999" value="<?= e((string)($room['floor'] ?? '')) ?>">
                                </label>
                                <label class="form-field"><?= e(t('hospitality.field.note')) ?>
                                    <input type="text" name="note" maxlength="1000" value="<?= e((string)($room['note'] ?? '')) ?>">
                                </label>
                                <div class="form-actions">
                                    <button type="submit" class="btn ok"><?= e(t('hospitality.action.save_changes')) ?></button>
                                </div>
                            </form>
                            <form method="post" action="/apps/hospitality/rooms/status">
                                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                <input type="hidden" name="id" value="<?= (int)$room['id'] ?>">
                                <input type="hidden" name="room_status" value="<?= ($room['room_status'] ?? '') === 'active' ? 'inactive' : 'active' ?>">
                                <button type="submit" class="btn danger"><?= e(t(($room['room_status'] ?? '') === 'active' ? 'hospitality.action.deactivate' : 'hospitality.action.activate')) ?></button>
                            </form>
                        </details>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="text-muted"><?= e(t('hospitality.crud.no_hard_delete_note')) ?></p>
</section>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

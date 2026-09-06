<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<?php
$flashOk = is_string($ok ?? null) && $ok !== '' ? t($ok) : '';
$flashErr = is_string($err ?? null) && $err !== '' ? t($err) : '';
$csrf = \App\Core\Auth::csrfToken();
?>

<section class="card">
    <h1><?= e(t('hospitality.nav.guests')) ?></h1>
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
    <h2><?= e(t('hospitality.action.create_guest')) ?></h2>
    <form method="post" action="/apps/hospitality/guests/create" class="form-grid">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <label class="form-field"><?= e(t('hospitality.field.full_name')) ?>
            <input type="text" name="full_name" maxlength="190" required>
        </label>
        <label class="form-field"><?= e(t('hospitality.field.email')) ?>
            <input type="email" name="email" maxlength="190">
        </label>
        <label class="form-field"><?= e(t('hospitality.field.phone')) ?>
            <input type="text" name="phone" maxlength="80">
        </label>
        <label class="form-field"><?= e(t('hospitality.field.id_document_ref')) ?>
            <input type="text" name="id_document_ref" maxlength="190">
        </label>
        <label class="form-field"><?= e(t('hospitality.field.note')) ?>
            <input type="text" name="note" maxlength="1000">
        </label>
        <div class="form-actions">
            <button type="submit" class="btn ok"><?= e(t('hospitality.action.create')) ?></button>
        </div>
    </form>
</section>

<?php if (count($rows) > 0): ?>
<section class="card">
    <h2><?= e(t('hospitality.module.records_count', ['count' => count($rows)])) ?></h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th><?= e(t('hospitality.field.full_name')) ?></th>
                    <th><?= e(t('hospitality.field.email')) ?></th>
                    <th><?= e(t('hospitality.field.phone')) ?></th>
                    <th><?= e(t('hospitality.field.status')) ?></th>
                    <th><?= e(t('hospitality.field.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $guest): ?>
                <tr>
                    <td><?= e((string)$guest['full_name']) ?></td>
                    <td><?= e((string)($guest['email'] ?? '')) ?></td>
                    <td><?= e((string)($guest['phone'] ?? '')) ?></td>
                    <td>
                        <span class="hosp-chip <?= ($guest['guest_status'] ?? '') === 'active' ? 'hosp-chip--ok' : 'hosp-chip--neutral' ?>">
                            <?= e(t('hospitality.status.' . (string)$guest['guest_status'])) ?>
                        </span>
                    </td>
                    <td>
                        <details>
                            <summary><?= e(t('hospitality.action.edit')) ?></summary>
                            <form method="post" action="/apps/hospitality/guests/update" class="form-grid">
                                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                <input type="hidden" name="id" value="<?= (int)$guest['id'] ?>">
                                <label class="form-field"><?= e(t('hospitality.field.full_name')) ?>
                                    <input type="text" name="full_name" maxlength="190" value="<?= e((string)$guest['full_name']) ?>" required>
                                </label>
                                <label class="form-field"><?= e(t('hospitality.field.email')) ?>
                                    <input type="email" name="email" maxlength="190" value="<?= e((string)($guest['email'] ?? '')) ?>">
                                </label>
                                <label class="form-field"><?= e(t('hospitality.field.phone')) ?>
                                    <input type="text" name="phone" maxlength="80" value="<?= e((string)($guest['phone'] ?? '')) ?>">
                                </label>
                                <label class="form-field"><?= e(t('hospitality.field.id_document_ref')) ?>
                                    <input type="text" name="id_document_ref" maxlength="190" value="<?= e((string)($guest['id_document_ref'] ?? '')) ?>">
                                </label>
                                <label class="form-field form-field-wide"><?= e(t('hospitality.field.note')) ?>
                                    <input type="text" name="note" maxlength="1000" value="<?= e((string)($guest['note'] ?? '')) ?>">
                                </label>
                                <div class="form-actions">
                                    <button type="submit" class="btn ok"><?= e(t('hospitality.action.save_changes')) ?></button>
                                </div>
                            </form>
                            <form method="post" action="/apps/hospitality/guests/status">
                                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                <input type="hidden" name="id" value="<?= (int)$guest['id'] ?>">
                                <input type="hidden" name="guest_status" value="<?= ($guest['guest_status'] ?? '') === 'active' ? 'inactive' : 'active' ?>">
                                <button type="submit" class="btn danger"><?= e(t(($guest['guest_status'] ?? '') === 'active' ? 'hospitality.action.deactivate' : 'hospitality.action.activate')) ?></button>
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
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

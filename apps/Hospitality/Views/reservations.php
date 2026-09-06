<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<?php
$flashOk = is_string($ok ?? null) && $ok !== '' ? t($ok) : '';
$flashErr = is_string($err ?? null) && $err !== '' ? t($err) : '';
$csrf = \App\Core\Auth::csrfToken();

/** allowed next statuses per current status (mirrors ReservationsService::TRANSITIONS) */
$nextByStatus = [
    'booked' => ['checked_in', 'cancelled', 'no_show'],
    'checked_in' => ['checked_out'],
];
$statusLabelKeys = [
    'booked' => 'hospitality.res_status.booked',
    'checked_in' => 'hospitality.res_status.checked_in',
    'checked_out' => 'hospitality.res_status.checked_out',
    'cancelled' => 'hospitality.res_status.cancelled',
    'no_show' => 'hospitality.res_status.no_show',
];
$transitionActionKeys = [
    'checked_in' => 'hospitality.res_action.check_in',
    'checked_out' => 'hospitality.res_action.check_out',
    'cancelled' => 'hospitality.res_action.cancel',
    'no_show' => 'hospitality.res_action.no_show',
];
$transitionButtonClass = [
    'checked_in' => 'btn ok',
    'checked_out' => 'btn',
    'cancelled' => 'btn danger',
    'no_show' => 'btn danger',
];
?>

<section class="card">
    <h1><?= e(t('hospitality.nav.reservations')) ?></h1>
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
    <h2><?= e(t('hospitality.action.create_reservation')) ?></h2>
    <?php if (count($guestOptions) === 0 || count($roomOptions) === 0): ?>
        <p class="text-muted"><?= e(t('hospitality.res_need_active_guest_room')) ?></p>
    <?php else: ?>
        <form method="post" action="/apps/hospitality/reservations/create" class="form-grid">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <label class="form-field"><?= e(t('hospitality.field.guest')) ?>
                <select name="guest_id" required>
                    <?php foreach ($guestOptions as $g): ?>
                        <option value="<?= (int)$g['id'] ?>"><?= e((string)$g['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="form-field"><?= e(t('hospitality.field.room')) ?>
                <select name="room_id">
                    <option value="0"><?= e(t('hospitality.res_unassigned_room')) ?></option>
                    <?php foreach ($roomOptions as $r): ?>
                        <option value="<?= (int)$r['id'] ?>"><?= e((string)$r['room_number']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="form-field"><?= e(t('hospitality.field.check_in_date')) ?>
                <input type="date" name="check_in_date" required>
            </label>
            <label class="form-field"><?= e(t('hospitality.field.check_out_date')) ?>
                <input type="date" name="check_out_date" required>
            </label>
            <label class="form-field"><?= e(t('hospitality.field.adults')) ?>
                <input type="number" name="adults" min="1" max="99" value="1">
            </label>
            <label class="form-field"><?= e(t('hospitality.field.children')) ?>
                <input type="number" name="children" min="0" max="99" value="0">
            </label>
            <label class="form-field"><?= e(t('hospitality.field.rate')) ?>
                <input type="text" name="rate" inputmode="decimal">
            </label>
            <label class="form-field form-field-wide"><?= e(t('hospitality.field.note')) ?>
                <input type="text" name="note" maxlength="1000">
            </label>
            <div class="form-actions">
                <button type="submit" class="btn ok"><?= e(t('hospitality.action.create')) ?></button>
            </div>
        </form>
    <?php endif; ?>
</section>

<section class="card">
    <h2><?= e(t('hospitality.module.records_count', ['count' => count($rows)])) ?></h2>
    <?php if (count($rows) > 0): ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th><?= e(t('hospitality.field.guest')) ?></th>
                    <th><?= e(t('hospitality.field.room')) ?></th>
                    <th><?= e(t('hospitality.field.check_in_date')) ?></th>
                    <th><?= e(t('hospitality.field.check_out_date')) ?></th>
                    <th><?= e(t('hospitality.field.status')) ?></th>
                    <th><?= e(t('hospitality.field.actual_check_in')) ?></th>
                    <th><?= e(t('hospitality.field.actual_check_out')) ?></th>
                    <th><?= e(t('hospitality.field.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $res): ?>
                <?php
                    $status = (string)$res['reservation_status'];
                    $nexts = $nextByStatus[$status] ?? [];
                ?>
                <tr>
                    <td><?= (int)$res['id'] ?></td>
                    <td><?= e((string)($res['guest_label'] ?? '?')) ?></td>
                    <td><?= e(($res['room_label'] ?? '') !== '' ? (string)$res['room_label'] : t('hospitality.res_unassigned_room')) ?></td>
                    <td><?= e((string)$res['check_in_date']) ?></td>
                    <td><?= e((string)$res['check_out_date']) ?></td>
                    <td><span class="hosp-chip hosp-chip--neutral"><?= e(t($statusLabelKeys[$status] ?? $status)) ?></span></td>
                    <td><?= e((string)($res['actual_check_in_at'] ?? '')) ?></td>
                    <td><?= e((string)($res['actual_check_out_at'] ?? '')) ?></td>
                    <td>
                        <?php foreach ($nexts as $next): ?>
                            <form method="post" action="/apps/hospitality/reservations/transition" class="hosp-inline-form">
                                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                <input type="hidden" name="id" value="<?= (int)$res['id'] ?>">
                                <input type="hidden" name="to_status" value="<?= e($next) ?>">
                                <button type="submit" class="<?= e($transitionButtonClass[$next] ?? 'btn') ?>"><?= e(t($transitionActionKeys[$next])) ?></button>
                            </form>
                        <?php endforeach; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <p class="text-muted"><?= e(t('hospitality.res_lifecycle_note')) ?></p>
</section>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

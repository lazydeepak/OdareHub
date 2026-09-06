<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<?php
$flashOk = is_string($ok ?? null) && $ok !== '' ? t($ok) : '';
$flashErr = is_string($err ?? null) && $err !== '' ? t($err) : '';
$csrf = \App\Core\Auth::csrfToken();
?>

<section class="card">
    <h1><?= e(t('hospitality.nav.front_desk')) ?></h1>
    <?php if ($flashOk !== ''): ?><p class="hosp-chip hosp-chip--ok"><?= e($flashOk) ?></p><?php endif; ?>
    <?php if ($flashErr !== ''): ?><p class="hosp-chip hosp-chip--danger"><?= e($flashErr) ?></p><?php endif; ?>
</section>

<?php if (!is_array($board)): ?>
<section class="card">
    <p class="text-muted"><?= e(t('hospitality.module.schema_pending')) ?></p>
    <p class="text-muted"><?= e(t('hospitality.module.l1_note')) ?></p>
</section>
<?php else: ?>

<section class="card">
    <h2><?= e(t('hospitality.fd.arrivals_title')) ?> (<?= count($board['arrivals']) ?>)</h2>
    <?php if (count($board['arrivals']) === 0): ?>
        <p class="text-muted"><?= e(t('hospitality.fd.no_arrivals')) ?></p>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th><?= e(t('hospitality.field.guest')) ?></th>
                    <th><?= e(t('hospitality.field.room')) ?></th>
                    <th><?= e(t('hospitality.field.check_in_date')) ?></th>
                    <th><?= e(t('hospitality.field.check_out_date')) ?></th>
                    <th><?= e(t('hospitality.field.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($board['arrivals'] as $res): ?>
                <tr>
                    <td><?= (int)$res['id'] ?></td>
                    <td><?= e((string)($res['guest_label'] ?? '?')) ?></td>
                    <td><?= e(($res['room_label'] ?? '') !== '' ? (string)$res['room_label'] : t('hospitality.res_unassigned_room')) ?></td>
                    <td><?= e((string)$res['check_in_date']) ?></td>
                    <td><?= e((string)$res['check_out_date']) ?></td>
                    <td>
                        <form method="post" action="/apps/hospitality/front-desk/check-in" class="hosp-inline-form">
                            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                            <input type="hidden" name="id" value="<?= (int)$res['id'] ?>">
                            <button type="submit" class="btn"><?= e(t('hospitality.res_action.check_in')) ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<section class="card">
    <h2><?= e(t('hospitality.fd.inhouse_title')) ?> (<?= count($board['inhouse']) ?>)</h2>
    <?php if (count($board['inhouse']) === 0): ?>
        <p class="text-muted"><?= e(t('hospitality.fd.no_inhouse')) ?></p>
    <?php else: ?>
    <?php foreach ($board['inhouse'] as $stay): ?>
        <?php
            $rid = (int)$stay['id'];
            $summary = $folios[$rid] ?? ['folio' => ['id' => 0, 'folio_status' => 'open'], 'charges' => [], 'total' => 0.0];
            $folioId = (int)$summary['folio']['id'];
            $folioOpen = ($summary['folio']['folio_status'] ?? 'open') === 'open';
        ?>
        <details>
            <summary>
                #<?= $rid ?> - <?= e((string)($stay['guest_label'] ?? '?')) ?>
                [<?= e(($stay['room_label'] ?? '') !== '' ? (string)$stay['room_label'] : t('hospitality.res_unassigned_room')) ?>]
                <?= e((string)($stay['actual_check_in_at'] ?? '')) ?>
            </summary>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th><?= e(t('hospitality.field.charge_type')) ?></th>
                            <th><?= e(t('hospitality.field.charge_description')) ?></th>
                            <th><?= e(t('hospitality.field.qty')) ?></th>
                            <th><?= e(t('hospitality.field.unit_amount')) ?></th>
                            <th><?= e(t('hospitality.field.status')) ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach (($summary['charges'] ?? []) as $charge): ?>
                        <tr>
                            <td><?= e(t('hospitality.charge_type.' . (string)$charge['charge_type'])) ?></td>
                            <td><?= e((string)($charge['description'] ?? '')) ?></td>
                            <td><?= (int)$charge['qty'] ?></td>
                            <td><?= e((string)$charge['unit_amount']) ?></td>
                            <td><?= e(t('hospitality.charge_status.' . (string)$charge['charge_status'])) ?></td>
                            <td>
                                <?php if (($charge['charge_status'] ?? '') === 'posted' && $folioOpen): ?>
                                <form method="post" action="/apps/hospitality/front-desk/charges/void" class="hosp-inline-form">
                                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="charge_id" value="<?= (int)$charge['id'] ?>">
                                    <button type="submit"><?= e(t('hospitality.action.void_charge')) ?></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <p><strong><?= e(t('hospitality.folio.total', ['total' => number_format((float)$summary['total'], 2)])) ?></strong></p>
            <p class="text-muted"><?= e(t('hospitality.folio.local_note')) ?></p>

            <?php if ($folioOpen): ?>
            <form method="post" action="/apps/hospitality/front-desk/charges/add" class="form-grid">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="reservation_id" value="<?= $rid ?>">
                <label class="form-field"><?= e(t('hospitality.field.charge_type')) ?>
                    <select name="charge_type">
                        <?php foreach (['room_rate','fnb','misc'] as $ct): ?>
                            <option value="<?= e($ct) ?>"><?= e(t('hospitality.charge_type.' . $ct)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="form-field form-field-wide"><?= e(t('hospitality.field.charge_description')) ?>
                    <input type="text" name="description" maxlength="255" required>
                </label>
                <label class="form-field"><?= e(t('hospitality.field.qty')) ?>
                    <input type="number" name="qty" min="1" max="9999" value="1">
                </label>
                <label class="form-field"><?= e(t('hospitality.field.unit_amount')) ?>
                    <input type="text" name="unit_amount" inputmode="decimal" required>
                </label>
                <div class="form-actions">
                    <button type="submit" class="btn ok"><?= e(t('hospitality.action.add_charge')) ?></button>
                </div>
            </form>
            <?php endif; ?>

            <form method="post" action="/apps/hospitality/front-desk/check-out">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="id" value="<?= $rid ?>">
                <button type="submit" class="btn"><?= e(t('hospitality.res_action.check_out')) ?></button>
            </form>
        </details>
    <?php endforeach; ?>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

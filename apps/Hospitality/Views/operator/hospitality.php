<?php
// Hospitality operator focus view (read-only).
// Included by the Shell operator composer with the resolved view data scope
// available. Data comes ONLY from the app-owned board adapter;
// there are no forms, buttons, links, or writes on this surface.

use App\Core\Auth;
use App\Core\AclPolicy;
use Apps\Hospitality\Services\OperatorLayerAdapters\HospitalityBoardAdapter;

$esc = static fn ($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$translate = static function (string $key, string $fallback, array $params = []): string {
    if (function_exists('t')) {
        $translated = (string)t($key, $params);
        if ($translated !== '' && $translated !== $key) {
            return $translated;
        }
    }

    if ($params === []) {
        return $fallback;
    }

    $replace = [];
    foreach ($params as $paramKey => $paramValue) {
        $replace['{' . $paramKey . '}'] = (string)$paramValue;
    }

    return strtr($fallback, $replace);
};
$tr = static fn (string $key, string $fallback): string => htmlspecialchars($translate($key, $fallback), ENT_QUOTES, 'UTF-8');
?>
<section class="recent-focus" aria-label="<?php echo $tr('hospitality.operator.sidebar', 'Hospitality'); ?>">
    <h2 class="dashboard-top-title"><?php echo $tr('hospitality.operator.nav.focus', 'Hospitality Focus'); ?></h2>

    <?php if (!AclPolicy::can('hospitality.view', Auth::user())): ?>
    <p class="text-muted"><?php echo $tr('hospitality.forbidden.message', 'You do not have the required hospitality.view permission for this surface.'); ?></p>
    <?php else: ?>

    <?php
        $canManage = AclPolicy::can('hospitality.manage', Auth::user());
        $opUsername = rawurlencode((string)($data['username'] ?? ''));
        $flashOk = isset($_SESSION['operator_hospitality_flash_ok']) ? (string)$_SESSION['operator_hospitality_flash_ok'] : '';
        $flashErr = isset($_SESSION['operator_hospitality_flash_err']) ? (string)$_SESSION['operator_hospitality_flash_err'] : '';
        $createdReservationId = isset($_SESSION['operator_hospitality_created_reservation_id'])
            ? (int)$_SESSION['operator_hospitality_created_reservation_id']
            : 0;
        unset($_SESSION['operator_hospitality_flash_ok'], $_SESSION['operator_hospitality_flash_err'], $_SESSION['operator_hospitality_created_reservation_id']);
        $csrfToken = Auth::csrfToken();
        $reservationOptions = $canManage ? HospitalityBoardAdapter::reservationFormOptions() : null;
        $createdReservation = $createdReservationId > 0
            ? HospitalityBoardAdapter::createdReservation($createdReservationId)
            : null;
    ?>
    <?php if ($flashOk !== ''): ?>
    <?php
        $okMsgKey = match ($flashOk) {
            'HOSPITALITY_RES_CREATED' => 'hospitality.operator.flash.reservation_created',
            'HOSPITALITY_FD_CHECKED_IN' => 'hospitality.operator.flash.checked_in',
            'HOSPITALITY_FD_CHECKED_OUT' => 'hospitality.operator.flash.checked_out',
            'HOSPITALITY_FD_CHARGE_ADDED' => 'hospitality.operator.flash.charge_added',
            'HOSPITALITY_RES_CANCELLED' => 'hospitality.operator.flash.cancelled',
            'HOSPITALITY_RES_NO_SHOW' => 'hospitality.operator.flash.no_show',
            default => 'hospitality.operator.flash.updated',
        };
    ?>
    <?php if ($flashOk === 'HOSPITALITY_RES_CREATED' && $createdReservationId > 0): ?>
    <p class="hosp-chip hosp-chip--ok"><?php echo $esc($translate('hospitality.operator.flash.reservation_created_with_id', 'Reservation created. ID: {id}', ['id' => $createdReservationId])); ?></p>
    <?php else: ?>
    <p class="hosp-chip hosp-chip--ok"><?php echo $esc($translate($okMsgKey, 'Status updated.')); ?></p>
    <?php endif; ?>
    <?php endif; ?>
    <?php if ($flashErr !== ''): ?>
    <p class="hosp-chip hosp-chip--danger"><?php echo $esc($translate(str_starts_with($flashErr, 'HOSPITALITY_RES_') ? 'hospitality.operator.flash.reservation_create_failed' : 'hospitality.operator.flash.update_failed', 'Unable to update the Hospitality action.')); ?></p>
    <?php endif; ?>

    <?php if ($flashOk === 'HOSPITALITY_RES_CREATED' && is_array($createdReservation)): ?>
    <div class="card">
        <h3><?php echo $tr('hospitality.operator.reservation.created_title', 'Created reservation'); ?></h3>
        <p class="text-muted"><?php echo $tr('hospitality.operator.reservation.created_note', 'This reservation is shown here so you can continue with it even when its planned arrival is not in the upcoming-arrivals list.'); ?></p>
        <dl>
            <dt><?php echo $tr('hospitality.field.reservation_id', 'Reservation ID'); ?></dt>
            <dd><?php echo $esc((int)($createdReservation['id'] ?? 0)); ?></dd>
            <dt><?php echo $tr('hospitality.field.guest', 'Guest'); ?></dt>
            <dd><?php echo $esc($createdReservation['guest_label'] ?? ''); ?></dd>
            <dt><?php echo $tr('hospitality.field.room', 'Room'); ?></dt>
            <dd><?php echo $esc(($createdReservation['room_label'] ?? '') !== '' ? $createdReservation['room_label'] : $translate('hospitality.res_unassigned_room', 'Room unassigned')); ?></dd>
            <dt><?php echo $tr('hospitality.field.check_in_date', 'Arrival (planned)'); ?></dt>
            <dd><?php echo $esc($createdReservation['check_in_date'] ?? ''); ?></dd>
            <dt><?php echo $tr('hospitality.field.check_out_date', 'Departure (planned)'); ?></dt>
            <dd><?php echo $esc($createdReservation['check_out_date'] ?? ''); ?></dd>
            <dt><?php echo $tr('hospitality.field.status', 'Status'); ?></dt>
            <dd><?php echo $esc($translate('hospitality.res_status.' . (string)($createdReservation['reservation_status'] ?? ''), (string)($createdReservation['reservation_status'] ?? ''))); ?></dd>
        </dl>
        <?php if ($canManage && (string)($createdReservation['reservation_status'] ?? '') === 'booked'): ?>
        <div class="hosp-inline-form">
            <form method="post" action="/u/<?php echo $esc($opUsername); ?>/hospitality/front-desk/no-show">
                <input type="hidden" name="csrf" value="<?php echo $esc($csrfToken); ?>">
                <input type="hidden" name="reservation_id" value="<?php echo $esc((int)($createdReservation['id'] ?? 0)); ?>">
                <button class="btn" type="submit"><?php echo $esc($translate('hospitality.res_action.no_show', 'No Show')); ?></button>
            </form>
            <form method="post" action="/u/<?php echo $esc($opUsername); ?>/hospitality/front-desk/cancel">
                <input type="hidden" name="csrf" value="<?php echo $esc($csrfToken); ?>">
                <input type="hidden" name="reservation_id" value="<?php echo $esc((int)($createdReservation['id'] ?? 0)); ?>">
                <button class="btn btn-danger" type="submit"><?php echo $esc($translate('hospitality.res_action.cancel', 'Cancel')); ?></button>
            </form>
            <form method="post" action="/u/<?php echo $esc($opUsername); ?>/hospitality/front-desk/check-in">
                <input type="hidden" name="csrf" value="<?php echo $esc($csrfToken); ?>">
                <input type="hidden" name="reservation_id" value="<?php echo $esc((int)($createdReservation['id'] ?? 0)); ?>">
                <button class="btn" type="submit"><?php echo $esc($translate('hospitality.res_action.check_in', 'Check in')); ?></button>
            </form>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php $summary = HospitalityBoardAdapter::summary(); ?>
    <?php if (!is_array($summary)): ?>
    <p class="text-muted"><?php echo $tr('hospitality.operator.schema_pending', 'Hospitality operator data appears after the app schema is installed.'); ?></p>
    <?php else: ?>

    <?php if ($canManage && is_array($reservationOptions)): ?>
    <div class="card">
        <h3><?php echo $tr('hospitality.operator.reservation.create_title', 'Create reservation'); ?></h3>
        <?php if ((array)($reservationOptions['guests'] ?? []) === []): ?>
        <p class="text-muted"><?php echo $tr('hospitality.operator.reservation.no_active_guests', 'No active guests are available for reservation creation.'); ?></p>
        <?php else: ?>
        <form method="post" action="/u/<?php echo $esc($opUsername); ?>/hospitality/reservations/create" class="hosp-form">
            <input type="hidden" name="csrf" value="<?php echo $esc($csrfToken); ?>">
            <label><?php echo $tr('hospitality.field.guest', 'Guest'); ?>
                <select name="guest_id" required>
                    <option value=""><?php echo $tr('hospitality.operator.reservation.choose_guest', 'Choose guest'); ?></option>
                    <?php foreach ((array)$reservationOptions['guests'] as $guest): ?>
                    <option value="<?php echo $esc((int)($guest['id'] ?? 0)); ?>"><?php echo $esc($guest['full_name'] ?? ''); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><?php echo $tr('hospitality.field.room', 'Room'); ?>
                <select name="room_id">
                    <option value=""><?php echo $tr('hospitality.operator.reservation.unassigned_room', 'Leave room unassigned'); ?></option>
                    <?php foreach ((array)($reservationOptions['rooms'] ?? []) as $room): ?>
                    <option value="<?php echo $esc((int)($room['id'] ?? 0)); ?>"><?php echo $esc($room['room_number'] ?? ''); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><?php echo $tr('hospitality.field.check_in_date', 'Arrival (planned)'); ?>
                <input type="date" name="check_in_date" required>
            </label>
            <label><?php echo $tr('hospitality.field.check_out_date', 'Departure (planned)'); ?>
                <input type="date" name="check_out_date" required>
            </label>
            <label><?php echo $tr('hospitality.field.adults', 'Adults'); ?>
                <input type="number" name="adults" min="0" max="99" value="1" required>
            </label>
            <label><?php echo $tr('hospitality.field.children', 'Children'); ?>
                <input type="number" name="children" min="0" max="99" value="0" required>
            </label>
            <label><?php echo $tr('hospitality.field.rate', 'Nightly rate (optional)'); ?>
                <input type="text" name="rate" inputmode="decimal">
            </label>
            <label><?php echo $tr('hospitality.field.note', 'Note'); ?>
                <textarea name="note" maxlength="1000" rows="2"></textarea>
            </label>
            <button class="btn" type="submit"><?php echo $tr('hospitality.operator.reservation.create_submit', 'Create reservation'); ?></button>
        </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php
        $kpis = (array)($summary['kpis'] ?? []);
        $arrivals = array_values((array)($summary['arrivals'] ?? []));
        $hkRooms = array_values((array)(($summary['housekeeping'] ?? [])['rooms'] ?? []));
        $statusLabelKeys = [
            'pending' => 'hospitality.hk_status.pending',
            'clean' => 'hospitality.hk_status.clean',
            'dirty' => 'hospitality.hk_status.dirty',
            'inspected' => 'hospitality.hk_status.inspected',
            'maintenance' => 'hospitality.hk_status.maintenance',
            'out_of_service' => 'hospitality.hk_status.out_of_service',
        ];
    ?>

    <div class="dashboard-top-grid">
        <div class="card">
            <p class="text-muted"><?php echo $tr('hospitality.operator.kpi.arrivals_today', 'Arrivals today'); ?></p>
            <h3><?php echo $esc((int)($kpis['arrivals_today'] ?? 0)); ?></h3>
        </div>
        <div class="card">
            <p class="text-muted"><?php echo $tr('hospitality.operator.kpi.inhouse', 'In-house'); ?></p>
            <h3><?php echo $esc((int)($kpis['inhouse'] ?? 0)); ?></h3>
        </div>
        <div class="card">
            <p class="text-muted"><?php echo $tr('hospitality.operator.kpi.rooms_attention', 'Rooms needing attention'); ?></p>
            <h3><?php echo $esc((int)($kpis['rooms_attention'] ?? 0)); ?></h3>
        </div>
        <div class="card">
            <p class="text-muted"><?php echo $tr('hospitality.operator.kpi.open_folio_total', 'Open folio total'); ?></p>
            <h3><?php echo $esc(number_format((float)($kpis['open_folio_total'] ?? 0), 2)); ?></h3>
            <p class="text-muted"><?php echo $tr('hospitality.operator.local_folio_note', 'Local Hospitality folio records only; no payments or invoices.'); ?></p>
        </div>
    </div>

    <div class="card">
        <h3><?php echo $tr('hospitality.operator.arrivals.title', 'Arrivals'); ?></h3>
        <?php if ($arrivals === []): ?>
        <p class="text-muted"><?php echo $tr('hospitality.fd.no_arrivals', 'No upcoming arrivals.'); ?></p>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th><?php echo $tr('hospitality.field.guest', 'Guest'); ?></th>
                        <th><?php echo $tr('hospitality.field.room', 'Room'); ?></th>
                        <th><?php echo $tr('hospitality.field.check_in_date', 'Arrival (planned)'); ?></th>
                        <th><?php echo $tr('hospitality.field.check_out_date', 'Departure (planned)'); ?></th>
                        <?php if ($canManage): ?>
                        <th><?php echo $tr('hospitality.field.actions', 'Actions'); ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($arrivals as $arrival): ?>
                    <tr>
                        <td><?php echo $esc($arrival['guest_label'] ?? ''); ?></td>
                        <td><?php echo $esc(($arrival['room_label'] ?? '') !== '' ? $arrival['room_label'] : $translate('hospitality.res_unassigned_room', 'Room unassigned')); ?></td>
                        <td><?php echo $esc($arrival['check_in_date'] ?? ''); ?></td>
                        <td><?php echo $esc($arrival['check_out_date'] ?? ''); ?></td>
                        <?php if ($canManage): ?>
                        <td>
                            <form method="post" action="/u/<?php echo $esc($opUsername); ?>/hospitality/front-desk/no-show" class="hosp-inline-form">
                                <input type="hidden" name="csrf" value="<?php echo $esc($csrfToken); ?>">
                                <input type="hidden" name="reservation_id" value="<?php echo $esc((int)($arrival['id'] ?? 0)); ?>">
                                <button class="btn" type="submit"><?php echo $esc($translate('hospitality.res_action.no_show', 'No Show')); ?></button>
                            </form>
                            <form method="post" action="/u/<?php echo $esc($opUsername); ?>/hospitality/front-desk/cancel" class="hosp-inline-form">
                                <input type="hidden" name="csrf" value="<?php echo $esc($csrfToken); ?>">
                                <input type="hidden" name="reservation_id" value="<?php echo $esc((int)($arrival['id'] ?? 0)); ?>">
                                <button class="btn btn-danger" type="submit"><?php echo $esc($translate('hospitality.res_action.cancel', 'Cancel')); ?></button>
                            </form>
                            <form method="post" action="/u/<?php echo $esc($opUsername); ?>/hospitality/front-desk/check-in" class="hosp-inline-form">
                                <input type="hidden" name="csrf" value="<?php echo $esc($csrfToken); ?>">
                                <input type="hidden" name="reservation_id" value="<?php echo $esc((int)($arrival['id'] ?? 0)); ?>">
                                <button class="btn" type="submit"><?php echo $esc($translate('hospitality.res_action.check_in', 'Check in')); ?></button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3><?php echo $tr('hospitality.fd.inhouse_title', 'In-house stays'); ?></h3>
        <?php $inhouse = array_values((array)($summary['inhouse'] ?? [])); ?>
        <?php if ($inhouse === []): ?>
        <p class="text-muted"><?php echo $tr('hospitality.fd.no_inhouse', 'No in-house stays.'); ?></p>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th><?php echo $tr('hospitality.field.guest', 'Guest'); ?></th>
                        <th><?php echo $tr('hospitality.field.room', 'Room'); ?></th>
                        <th><?php echo $tr('hospitality.field.actual_check_in', 'Checked in at'); ?></th>
                        <?php if ($canManage): ?>
                        <th><?php echo $tr('hospitality.field.actions', 'Actions'); ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($inhouse as $stay): ?>
                    <tr>
                        <td><?php echo $esc($stay['guest_label'] ?? ''); ?></td>
                        <td><?php echo $esc(($stay['room_label'] ?? '') !== '' ? $stay['room_label'] : $translate('hospitality.res_unassigned_room', 'Room unassigned')); ?></td>
                        <td><?php echo $esc($stay['actual_check_in_at'] ?? ''); ?></td>
                        <?php if ($canManage): ?>
                        <td>
                            <form method="post" action="/u/<?php echo $esc($opUsername); ?>/hospitality/front-desk/check-out" class="hosp-inline-form">
                                <input type="hidden" name="csrf" value="<?php echo $esc($csrfToken); ?>">
                                <input type="hidden" name="reservation_id" value="<?php echo $esc((int)($stay['id'] ?? 0)); ?>">
                                <button class="btn" type="submit"><?php echo $esc($translate('hospitality.res_action.check_out', 'Check out')); ?></button>
                            </form>
                            <form method="post" action="/u/<?php echo $esc($opUsername); ?>/hospitality/front-desk/charges/add" class="hosp-inline-form">
                                <input type="hidden" name="csrf" value="<?php echo $esc($csrfToken); ?>">
                                <input type="hidden" name="reservation_id" value="<?php echo $esc((int)($stay['id'] ?? 0)); ?>">
                                <select name="charge_type">
                                    <?php foreach (\Apps\Hospitality\Services\FrontDeskService::CHARGE_TYPES as $ct): ?>
                                    <option value="<?php echo $esc($ct); ?>"><?php echo $esc($translate('hospitality.charge_type.' . $ct, $ct)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="description" maxlength="255" required placeholder="<?php echo $esc($translate('hospitality.field.charge_description', 'Description')); ?>">
                                <input type="number" name="qty" min="1" max="9999" value="1">
                                <input type="text" name="unit_amount" inputmode="decimal" required placeholder="<?php echo $esc($translate('hospitality.field.unit_amount', 'Amount')); ?>">
                                <button class="btn" type="submit"><?php echo $esc($translate('hospitality.operator.charge.add', 'Add charge')); ?></button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <?php
    $folioSummariesMap = [];
    foreach ((array)($summary['folio_summaries'] ?? []) as $fs) {
        $folioSummariesMap[(int)($fs['folio']['reservation_id'] ?? 0)] = $fs;
    }
    ?>

    <?php foreach ($inhouse as $stay): ?>
        <?php $stayFolio = $folioSummariesMap[(int)($stay['id'] ?? 0)] ?? ['folio' => ['folio_status' => 'open', 'id' => 0], 'charges' => [], 'total' => 0.0]; ?>
        <?php if (!empty($stayFolio['charges'])): ?>
        <div class="card">
            <h3><?= $esc($stay['guest_label'] ?? '?') ?> — <?= $esc(($stay['room_label'] ?? '') !== '' ? $stay['room_label'] : $translate('hospitality.res_unassigned_room', 'Room unassigned')) ?></h3>
            <p class="text-muted"><?= $esc(t('hospitality.folio.total', ['total' => number_format((float)($stayFolio['total'] ?? 0), 2)])) ?></p>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th><?= $esc(t('hospitality.field.charge_type')) ?></th><th><?= $esc(t('hospitality.field.charge_description')) ?></th><th><?= $esc(t('hospitality.field.qty')) ?></th><th><?= $esc(t('hospitality.field.unit_amount')) ?></th><th><?= $esc(t('hospitality.field.status')) ?></th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach (($stayFolio['charges'] ?? []) as $charge): ?>
                        <tr>
                            <td><?= $esc(t('hospitality.charge_type.' . (string)($charge['charge_type'] ?? '')) ?? (string)($charge['charge_type'] ?? '')) ?></td>
                            <td><?= $esc((string)($charge['description'] ?? '')) ?></td>
                            <td><?= (int)($charge['qty'] ?? 0) ?></td>
                            <td><?= $esc((string)($charge['unit_amount'] ?? '')) ?></td>
                            <td><?= $esc(t('hospitality.charge_status.' . (string)($charge['charge_status'] ?? '') ?? '')) ?></td>
                            <td><?php if (($charge['charge_status'] ?? '') === 'posted' && ($stayFolio['folio']['folio_status'] ?? 'open') === 'open' && $canManage): ?>
                                <form method="post" action="/u/<?= $esc($opUsername) ?>/hospitality/front-desk/charges/void" class="hosp-inline-form">
                                    <input type="hidden" name="csrf" value="<?= $esc($csrfToken) ?>">
                                    <input type="hidden" name="charge_id" value="<?= (int)($charge['id'] ?? 0) ?>">
                                    <button type="submit" class="btn btn-danger"><?= $esc($translate('hospitality.res_action.void', 'Void charge')) ?></button>
                                </form>
                                <?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <div class="card">
        <h3><?php echo $tr('hospitality.operator.housekeeping.title', 'Housekeeping snapshot'); ?></h3>
        <?php if ($hkRooms === []): ?>
        <p class="text-muted"><?php echo $tr('hospitality.hk.no_active_rooms', 'No active rooms to inspect.'); ?></p>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th><?php echo $tr('hospitality.field.room_number', 'Room number'); ?></th>
                        <th><?php echo $tr('hospitality.field.status', 'Status'); ?></th>
                        <?php if ($canManage): ?>
                        <th><?php echo $tr('hospitality.operator.hk.save', 'Save status'); ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($hkRooms as $room): ?>
                    <?php
                        $status = strtolower(trim((string)($room['hk_status'] ?? '')));
                        $statusKey = $status !== '' ? ($statusLabelKeys[$status] ?? 'hospitality.hk_status.pending') : 'hospitality.hk_status.pending';
                    ?>
                    <tr>
                        <td><?php echo $esc($room['room_number'] ?? ''); ?></td>
                        <td><span class="hosp-chip <?php echo in_array($status, ['clean', 'inspected'], true) ? 'hosp-chip--ok' : ($status === '' ? 'hosp-chip--neutral' : 'hosp-chip--warning'); ?>"><?php echo $esc($translate($statusKey, $status !== '' ? $status : 'Pending')); ?></span></td>
                        <?php if ($canManage): ?>
                        <td>
                            <form method="post" action="/u/<?php echo $esc($opUsername); ?>/hospitality/housekeeping/status" class="hosp-inline-form">
                                <input type="hidden" name="csrf" value="<?php echo $esc($csrfToken); ?>">
                                <input type="hidden" name="room_id" value="<?php echo $esc((int)($room['room_id'] ?? 0)); ?>">
                                <select name="hk_status">
                                    <?php foreach (\Apps\Hospitality\Services\HousekeepingService::STATUSES as $hkOption): ?>
                                    <option value="<?php echo $esc($hkOption); ?>" <?php echo $status === $hkOption ? 'selected' : ''; ?>><?php echo $esc($translate($statusLabelKeys[$hkOption] ?? 'hospitality.hk_status.' . $hkOption, $hkOption)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn" type="submit"><?php echo $esc($translate('hospitality.action.save_changes', 'Save changes')); ?></button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <?php endif; ?>
    <?php endif; ?>
</section>

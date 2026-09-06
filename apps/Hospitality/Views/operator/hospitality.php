<?php
// Hospitality operator focus view (read-only).
// Included by the Shell operator composer with full composer scope available
// ($data, $this->tr()). Data comes ONLY from the app-owned board adapter;
// there are no forms, buttons, links, or writes on this surface.

use App\Core\Auth;
use App\Core\AclPolicy;
use Apps\Hospitality\Services\OperatorLayerAdapters\HospitalityBoardAdapter;

$esc = static fn ($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$tr = static fn (string $key, string $fallback): string => htmlspecialchars($this->tr($key, $fallback), ENT_QUOTES, 'UTF-8');
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
        unset($_SESSION['operator_hospitality_flash_ok'], $_SESSION['operator_hospitality_flash_err']);
        $csrfToken = Auth::csrfToken();
    ?>
    <?php if ($flashOk !== ''): ?>
    <?php
        $okMsgKey = match ($flashOk) {
            'HOSPITALITY_FD_CHECKED_IN' => 'hospitality.operator.flash.checked_in',
            'HOSPITALITY_FD_CHECKED_OUT' => 'hospitality.operator.flash.checked_out',
            'HOSPITALITY_FD_CHARGE_ADDED' => 'hospitality.operator.flash.charge_added',
            'HOSPITALITY_RES_CANCELLED' => 'hospitality.operator.flash.cancelled',
            'HOSPITALITY_RES_NO_SHOW' => 'hospitality.operator.flash.no_show',
            default => 'hospitality.operator.flash.updated',
        };
    ?>
    <p class="hosp-chip hosp-chip--ok"><?php echo $esc($this->tr($okMsgKey, 'Status updated.')); ?></p>
    <?php endif; ?>
    <?php if ($flashErr !== ''): ?>
    <p class="hosp-chip hosp-chip--danger"><?php echo $esc($this->tr('hospitality.operator.flash.update_failed', 'Unable to update status.')); ?></p>
    <?php endif; ?>

    <?php $summary = HospitalityBoardAdapter::summary(); ?>
    <?php if (!is_array($summary)): ?>
    <p class="text-muted"><?php echo $tr('hospitality.operator.schema_pending', 'Hospitality operator data appears after the app schema is installed.'); ?></p>
    <?php else: ?>

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
                        <td><?php echo $esc(($arrival['room_label'] ?? '') !== '' ? $arrival['room_label'] : $this->tr('hospitality.res_unassigned_room', 'Room unassigned')); ?></td>
                        <td><?php echo $esc($arrival['check_in_date'] ?? ''); ?></td>
                        <td><?php echo $esc($arrival['check_out_date'] ?? ''); ?></td>
                        <?php if ($canManage): ?>
                        <td>
                            <form method="post" action="/u/<?php echo $esc($opUsername); ?>/hospitality/front-desk/no-show" class="hosp-inline-form">
                                <input type="hidden" name="csrf" value="<?php echo $esc($csrfToken); ?>">
                                <input type="hidden" name="reservation_id" value="<?php echo $esc((int)($arrival['id'] ?? 0)); ?>">
                                <button class="btn" type="submit"><?php echo $esc($this->tr('hospitality.res_action.no_show', 'No Show')); ?></button>
                            </form>
                            <form method="post" action="/u/<?php echo $esc($opUsername); ?>/hospitality/front-desk/cancel" class="hosp-inline-form">
                                <input type="hidden" name="csrf" value="<?php echo $esc($csrfToken); ?>">
                                <input type="hidden" name="reservation_id" value="<?php echo $esc((int)($arrival['id'] ?? 0)); ?>">
                                <button class="btn btn-danger" type="submit"><?php echo $esc($this->tr('hospitality.res_action.cancel', 'Cancel')); ?></button>
                            </form>
                            <form method="post" action="/u/<?php echo $esc($opUsername); ?>/hospitality/front-desk/check-in" class="hosp-inline-form">
                                <input type="hidden" name="csrf" value="<?php echo $esc($csrfToken); ?>">
                                <input type="hidden" name="reservation_id" value="<?php echo $esc((int)($arrival['id'] ?? 0)); ?>">
                                <button class="btn" type="submit"><?php echo $esc($this->tr('hospitality.res_action.check_in', 'Check in')); ?></button>
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
                        <td><?php echo $esc(($stay['room_label'] ?? '') !== '' ? $stay['room_label'] : $this->tr('hospitality.res_unassigned_room', 'Room unassigned')); ?></td>
                        <td><?php echo $esc($stay['actual_check_in_at'] ?? ''); ?></td>
                        <?php if ($canManage): ?>
                        <td>
                            <form method="post" action="/u/<?php echo $esc($opUsername); ?>/hospitality/front-desk/check-out" class="hosp-inline-form">
                                <input type="hidden" name="csrf" value="<?php echo $esc($csrfToken); ?>">
                                <input type="hidden" name="reservation_id" value="<?php echo $esc((int)($stay['id'] ?? 0)); ?>">
                                <button class="btn" type="submit"><?php echo $esc($this->tr('hospitality.res_action.check_out', 'Check out')); ?></button>
                            </form>
                            <form method="post" action="/u/<?php echo $esc($opUsername); ?>/hospitality/front-desk/charges/add" class="hosp-inline-form">
                                <input type="hidden" name="csrf" value="<?php echo $esc($csrfToken); ?>">
                                <input type="hidden" name="reservation_id" value="<?php echo $esc((int)($stay['id'] ?? 0)); ?>">
                                <select name="charge_type">
                                    <?php foreach (\Apps\Hospitality\Services\FrontDeskService::CHARGE_TYPES as $ct): ?>
                                    <option value="<?php echo $esc($ct); ?>"><?php echo $esc($this->tr('hospitality.charge_type.' . $ct, $ct)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="description" maxlength="255" required placeholder="<?php echo $esc($this->tr('hospitality.field.charge_description', 'Description')); ?>">
                                <input type="number" name="qty" min="1" max="9999" value="1">
                                <input type="text" name="unit_amount" inputmode="decimal" required placeholder="<?php echo $esc($this->tr('hospitality.field.unit_amount', 'Amount')); ?>">
                                <button class="btn" type="submit"><?php echo $esc($this->tr('hospitality.operator.charge.add', 'Add charge')); ?></button>
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
                        <td><span class="hosp-chip <?php echo in_array($status, ['clean', 'inspected'], true) ? 'hosp-chip--ok' : ($status === '' ? 'hosp-chip--neutral' : 'hosp-chip--warning'); ?>"><?php echo $esc($this->tr($statusKey, $status !== '' ? $status : 'Pending')); ?></span></td>
                        <?php if ($canManage): ?>
                        <td>
                            <form method="post" action="/u/<?php echo $esc($opUsername); ?>/hospitality/housekeeping/status" class="hosp-inline-form">
                                <input type="hidden" name="csrf" value="<?php echo $esc($csrfToken); ?>">
                                <input type="hidden" name="room_id" value="<?php echo $esc((int)($room['room_id'] ?? 0)); ?>">
                                <select name="hk_status">
                                    <?php foreach (\Apps\Hospitality\Services\HousekeepingService::STATUSES as $hkOption): ?>
                                    <option value="<?php echo $esc($hkOption); ?>" <?php echo $status === $hkOption ? 'selected' : ''; ?>><?php echo $esc($this->tr($statusLabelKeys[$hkOption] ?? 'hospitality.hk_status.' . $hkOption, $hkOption)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn" type="submit"><?php echo $esc($this->tr('hospitality.action.save_changes', 'Save changes')); ?></button>
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

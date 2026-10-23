<?php
declare(strict_types=1);

use Apps\Procurement\Services\ProcurementOverviewService;

require_once APP_ROOT . '/apps/Procurement/Services/ProcurementOverviewService.php';

$currentQuery = is_array($data['current_query'] ?? null) ? $data['current_query'] : [];
$queueFilters = array_intersect_key($currentQuery, array_flip(['request_ref', 'status', 'needed_date', 'needed_from', 'needed_to']));
$queue = ProcurementOverviewService::ownedOperatorRequests((string)($data['user_email'] ?? ''), 40, $queueFilters);
$rows = (array)($queue['rows'] ?? []);
$normalizedFilters = (array)($queue['filters'] ?? []);
$invalidFilters = (array)($queue['invalid_filters'] ?? []);
$tr = static function (string $key, string $fallback): string {
    if (function_exists('t')) {
        $translated = (string)t($key);
        if ($translated !== '' && $translated !== $key) {
            return $translated;
        }
    }
    return $fallback;
};
$statusClass = static function (string $status): string {
    return match (strtolower(trim($status))) {
        'approved', 'issued', 'closed' => 'chip chip--ok',
        'cancelled', 'rejected' => 'chip chip--danger',
        default => 'chip chip--neutral',
    };
};
?>
<section class="card" data-procurement-operator-queue="1">
    <div class="section-head">
        <div class="ui-block">
            <h2><?= e($tr('proc.operator.queue.title', 'My Procurement Requests')) ?></h2>
            <div class="muted"><?= e($tr('proc.operator.queue.subtitle', 'Read-only requests created by your authenticated account.')) ?></div>
        </div>
    </div>

    <form method="get" action="/u/<?= e(rawurlencode((string)($data['username'] ?? ''))) ?>/procurement" aria-label="<?= e($tr('proc.operator.queue.filter.title', 'Filter my requests')) ?>">
        <label><?= e($tr('proc.operator.queue.filter.reference', 'Reference')) ?>
            <input type="search" name="request_ref" maxlength="60" value="<?= e((string)($normalizedFilters['request_ref'] ?? '')) ?>">
        </label>
        <label><?= e($tr('proc.operator.queue.filter.status', 'Status')) ?>
            <select name="status">
                <option value=""><?= e($tr('proc.operator.queue.filter.all_statuses', 'All statuses')) ?></option>
                <?php foreach (ProcurementOverviewService::OPERATOR_REQUEST_STATUSES as $status): ?>
                    <option value="<?= e($status) ?>"<?= (($normalizedFilters['status'] ?? '') === $status) ? ' selected' : '' ?>><?= e($tr('proc.operator.queue.status.' . $status, ucfirst($status))) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><?= e($tr('proc.operator.queue.filter.needed_date', 'Needed date')) ?>
            <input type="date" name="needed_date" value="<?= e((string)($normalizedFilters['needed_date'] ?? '')) ?>">
        </label>
        <label><?= e($tr('proc.operator.queue.filter.needed_from', 'Needed from')) ?>
            <input type="date" name="needed_from" value="<?= e((string)($normalizedFilters['needed_from'] ?? '')) ?>">
        </label>
        <label><?= e($tr('proc.operator.queue.filter.needed_to', 'Needed to')) ?>
            <input type="date" name="needed_to" value="<?= e((string)($normalizedFilters['needed_to'] ?? '')) ?>">
        </label>
        <button type="submit"><?= e($tr('proc.operator.queue.filter.apply', 'Apply filters')) ?></button>
        <a href="/u/<?= e(rawurlencode((string)($data['username'] ?? ''))) ?>/procurement"><?= e($tr('proc.operator.queue.filter.clear', 'Clear')) ?></a>
    </form>

    <?php if ($invalidFilters !== []): ?>
        <div class="empty-state" role="alert">
            <p><?= e($tr('proc.operator.queue.filter.invalid', 'The supplied filter is invalid. No requests are shown.')) ?></p>
        </div>
    <?php endif; ?>

    <?php if ($invalidFilters !== []): ?>
    <?php elseif (($queue['available'] ?? false) !== true): ?>
        <div class="empty-state">
            <h3><?= e($tr('proc.operator.queue.unavailable_title', 'Requests unavailable')) ?></h3>
            <p><?= e($tr('proc.operator.queue.unavailable_message', 'Procurement request storage is unavailable or ownership cannot be verified.')) ?></p>
        </div>
    <?php elseif ($rows === []): ?>
        <div class="empty-state">
            <h3><?= e($tr('proc.operator.queue.empty_title', 'No requests found')) ?></h3>
            <p><?= e($tr('proc.operator.queue.empty_message', 'No Procurement requests match the current filters.')) ?></p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th><?= e($tr('proc.operator.queue.reference', 'Reference')) ?></th>
                        <th><?= e($tr('proc.operator.queue.status', 'Status')) ?></th>
                        <th><?= e($tr('proc.operator.queue.quantity', 'Quantity')) ?></th>
                        <th><?= e($tr('proc.operator.queue.needed_date', 'Needed date')) ?></th>
                        <th><?= e($tr('proc.operator.queue.created_at', 'Created')) ?></th>
                        <th><?= e($tr('proc.operator.queue.purchase_order', 'Purchase order')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= e((string)($row['request_ref'] ?? '')) ?></td>
                            <td><span class="<?= e($statusClass((string)($row['request_status'] ?? ''))) ?>"><?= e((string)($row['request_status'] ?? '')) ?></span></td>
                            <td><?= e((string)($row['requested_qty'] ?? '0')) ?></td>
                            <td><?= e((string)($row['needed_date'] ?? '—')) ?></td>
                            <td><?= e((string)($row['created_at'] ?? '—')) ?></td>
                            <td><?= e((string)($row['po_ref'] ?? $tr('proc.operator.queue.not_linked', 'Not linked'))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

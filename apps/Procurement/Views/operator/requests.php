<?php
declare(strict_types=1);

use Apps\Procurement\Services\ProcurementOverviewService;

require_once APP_ROOT . '/apps/Procurement/Services/ProcurementOverviewService.php';

$currentQuery = is_array($data['current_query'] ?? null) ? $data['current_query'] : [];
$traceReference = is_string($currentQuery['view_ref'] ?? null) ? (string)$currentQuery['view_ref'] : '';
$queueFilters = array_intersect_key($currentQuery, array_flip(['request_ref', 'status', 'receipt_status', 'needed_date', 'needed_from', 'needed_to']));
$queue = ProcurementOverviewService::ownedOperatorRequests((string)($data['user_email'] ?? ''), 40, $queueFilters);
$traceResult = ProcurementOverviewService::ownedOperatorRequestTrace((string)($data['user_email'] ?? ''), $traceReference);
$trace = is_array($traceResult['trace'] ?? null) ? $traceResult['trace'] : null;
$traceQuery = [];
foreach (['request_ref', 'status', 'receipt_status', 'needed_date', 'needed_from', 'needed_to'] as $traceFilterKey) {
    if (is_string($queueFilters[$traceFilterKey] ?? null) && trim((string)$queueFilters[$traceFilterKey]) !== '') {
        $traceQuery[$traceFilterKey] = (string)$queueFilters[$traceFilterKey];
    }
}
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
        'approved', 'issued', 'closed', 'received', 'posted' => 'chip chip--ok',
        'partial' => 'chip chip--warn',
        'cancelled', 'rejected' => 'chip chip--danger',
        default => 'chip chip--neutral',
    };
};
$statusLabel = static function (string $status) use ($tr): string {
    $normalized = strtolower(trim($status));
    if ($normalized === '') {
        return $tr('proc.operator.queue.status.unknown', 'Unknown');
    }
    return $tr('proc.operator.queue.status.' . $normalized, ucfirst($normalized));
};
$traceUrl = static function (string $reference, string $username, array $filters): string {
    $filters['view_ref'] = $reference;
    return '/u/' . rawurlencode($username) . '/procurement?' . http_build_query($filters, '', '&', PHP_QUERY_RFC3986);
};
?>
<section class="card" data-procurement-operator-queue="1">
    <div class="section-head">
        <div class="ui-block">
            <h2><?= e($tr('proc.operator.queue.title', 'My Procurement Requests')) ?></h2>
            <div class="muted"><?= e($tr('proc.operator.queue.subtitle', 'Read-only requests created by your authenticated account.')) ?></div>
        </div>
    </div>

    <?php if ($traceReference !== ''): ?>
        <section class="card" data-procurement-request-trace="1">
            <h3><?= e($tr('proc.operator.queue.trace.title', 'Request trace')) ?></h3>
            <?php if (($traceResult['invalid_reference'] ?? false) === true): ?>
                <p role="alert"><?= e($tr('proc.operator.queue.trace.invalid_reference', 'The request reference is invalid.')) ?></p>
            <?php elseif (($traceResult['available'] ?? false) !== true): ?>
                <p role="alert"><?= e($tr('proc.operator.queue.unavailable_message', 'Procurement request storage is unavailable or ownership cannot be verified.')) ?></p>
            <?php elseif ($trace === null): ?>
                <p><?= e($tr('proc.operator.queue.trace.not_found', 'No request with that reference is owned by your account.')) ?></p>
            <?php else: ?>
                <?php $traceRequest = (array)($trace['request'] ?? []); ?>
                <?php $tracePo = is_array($trace['purchase_order'] ?? null) ? $trace['purchase_order'] : null; ?>
                <p class="muted"><?= e($tr('proc.operator.queue.trace.request', 'Request')) ?>: <?= e((string)($traceRequest['request_ref'] ?? '')) ?></p>
                <div class="table-wrap">
                    <table>
                        <thead><tr>
                            <th><?= e($tr('proc.operator.queue.trace.status', 'Status')) ?></th>
                            <th><?= e($tr('proc.operator.queue.trace.quantity', 'Quantity')) ?></th>
                            <th><?= e($tr('proc.operator.queue.trace.date', 'Date')) ?></th>
                        </tr></thead>
                        <tbody><tr>
                            <td><span class="<?= e($statusClass((string)($traceRequest['request_status'] ?? ''))) ?>"><?= e($statusLabel((string)($traceRequest['request_status'] ?? ''))) ?></span></td>
                            <td><?= e((string)($traceRequest['requested_qty'] ?? '0')) ?></td>
                            <td><?= e((string)($traceRequest['created_at'] ?? '')) ?></td>
                        </tr></tbody>
                    </table>
                </div>
                <h4><?= e($tr('proc.operator.queue.trace.purchase_order', 'Purchase order')) ?></h4>
                <?php if ($tracePo === null): ?>
                    <p><?= e($tr('proc.operator.queue.trace.unlinked', 'No purchase order is linked.')) ?></p>
                <?php else: ?>
                    <p><?= e((string)($tracePo['po_ref'] ?? '')) ?> · <?= e($statusLabel((string)($tracePo['po_status'] ?? ''))) ?></p>
                    <p class="muted"><?= e($tr('proc.operator.queue.trace.date', 'Date')) ?>: <?= e((string)($tracePo['order_date'] ?? '')) ?>; <?= e($tr('proc.operator.queue.trace.expected_date', 'Expected')) ?>: <?= e((string)($tracePo['expected_date'] ?? '')) ?></p>
                <?php endif; ?>
                <h4><?= e($tr('proc.operator.queue.trace.receipts', 'Receipts')) ?></h4>
                <?php $traceReceipts = (array)($trace['receipts'] ?? []); ?>
                <?php if ($traceReceipts === []): ?>
                    <p><?= e($tr('proc.operator.queue.no_receipts', 'No receipts')) ?></p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead><tr>
                                <th><?= e($tr('proc.operator.queue.reference', 'Reference')) ?></th>
                                <th><?= e($tr('proc.operator.queue.trace.status', 'Status')) ?></th>
                                <th><?= e($tr('proc.operator.queue.trace.quantity', 'Quantity')) ?></th>
                                <th><?= e($tr('proc.operator.queue.trace.date', 'Date')) ?></th>
                            </tr></thead>
                            <tbody><?php foreach ($traceReceipts as $receipt): ?><tr>
                                <td><?= e((string)($receipt['receipt_ref'] ?? '')) ?></td>
                                <td><span class="<?= e($statusClass((string)($receipt['receipt_status'] ?? ''))) ?>"><?= e($statusLabel((string)($receipt['receipt_status'] ?? ''))) ?></span></td>
                                <td><?= e((string)($receipt['received_qty'] ?? '0')) ?></td>
                                <td><?= e((string)($receipt['receipt_date'] ?? '')) ?></td>
                            </tr><?php endforeach; ?></tbody>
                        </table>
                    </div>
                <?php endif; ?>
                <p><?= e($tr('proc.operator.queue.trace.posted_total', 'Posted quantity')) ?>: <?= e((string)($trace['posted_received_qty'] ?? '0')) ?></p>
                <?php if (($trace['receipts_truncated'] ?? false) === true): ?>
                    <p class="muted"><?= e($tr('proc.operator.queue.trace.truncated', 'Only the bounded receipt history is shown.')) ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>

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
        <label><?= e($tr('proc.operator.queue.filter.receipt_status', 'Receipt status')) ?>
            <select name="receipt_status">
                <option value=""><?= e($tr('proc.operator.queue.filter.all_receipt_statuses', 'All receipt statuses')) ?></option>
                <?php foreach (ProcurementOverviewService::OPERATOR_RECEIPT_FILTERS as $receiptFilter): ?>
                    <?php $receiptLabel = $receiptFilter === 'none'
                        ? $tr('proc.operator.queue.no_receipts', 'No receipts')
                        : $tr('proc.operator.queue.status.' . $receiptFilter, ucfirst($receiptFilter)); ?>
                    <option value="<?= e($receiptFilter) ?>"<?= (($normalizedFilters['receipt_status'] ?? '') === $receiptFilter) ? ' selected' : '' ?>><?= e($receiptLabel) ?></option>
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
                        <th><?= e($tr('proc.operator.queue.receipt', 'Receipt')) ?></th>
                        <th><?= e($tr('proc.operator.queue.trace.view', 'View trace')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= e((string)($row['request_ref'] ?? '')) ?></td>
                            <?php $requestStatus = (string)($row['request_status'] ?? ''); ?>
                            <td><span class="<?= e($statusClass($requestStatus)) ?>"><?= e($statusLabel($requestStatus)) ?></span></td>
                            <td><?= e((string)($row['requested_qty'] ?? '0')) ?></td>
                            <td><?= e((string)($row['needed_date'] ?? '—')) ?></td>
                            <td><?= e((string)($row['created_at'] ?? '—')) ?></td>
                            <?php $poRef = trim((string)($row['po_ref'] ?? '')); ?>
                            <?php $poStatus = (string)($row['po_status'] ?? ''); ?>
                            <td>
                                <?php if ($poRef === ''): ?>
                                    <?= e($tr('proc.operator.queue.not_linked', 'Not linked')) ?>
                                <?php else: ?>
                                    <div><?= e($poRef) ?></div>
                                    <?php if (trim($poStatus) !== ''): ?>
                                        <span class="<?= e($statusClass($poStatus)) ?>"><?= e($tr('proc.operator.queue.purchase_order_status', 'PO status')) ?>: <?= e($statusLabel($poStatus)) ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <?php $receiptRef = trim((string)($row['receipt_ref'] ?? '')); ?>
                            <?php $receiptStatus = strtolower(trim((string)($row['latest_receipt_status'] ?? ''))); ?>
                            <?php $receiptCount = (int)($row['receipt_count'] ?? 0); ?>
                            <?php $postedReceivedQty = (float)($row['posted_received_qty'] ?? 0); ?>
                            <td>
                                <?php if ($poRef === ''): ?>
                                    <?= e($tr('proc.operator.queue.not_linked', 'Not linked')) ?>
                                <?php elseif ($receiptCount === 0): ?>
                                    <?= e($tr('proc.operator.queue.no_receipts', 'No receipts')) ?>
                                <?php else: ?>
                                    <?php if ($receiptRef !== ''): ?><div><?= e($receiptRef) ?></div><?php endif; ?>
                                    <span class="<?= e($statusClass($receiptStatus)) ?>"><?= e($tr('proc.operator.queue.receipt_status', 'Receipt status')) ?>: <?= e($statusLabel($receiptStatus)) ?></span>
                                    <div><?= e($tr('proc.operator.queue.receipt_count', 'Receipts')) ?>: <?= e((string)$receiptCount) ?></div>
                                    <div><?= e($tr('proc.operator.queue.received_quantity', 'Posted quantity')) ?>: <?= e(number_format($postedReceivedQty, 2, '.', '')) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><a href="<?= e($traceUrl((string)($row['request_ref'] ?? ''), (string)($data['username'] ?? ''), $traceQuery)) ?>"><?= e($tr('proc.operator.queue.trace.view', 'View trace')) ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

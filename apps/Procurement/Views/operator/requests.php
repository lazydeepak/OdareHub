<?php
declare(strict_types=1);

use Apps\Procurement\Services\ProcurementOverviewService;

require_once APP_ROOT . '/apps/Procurement/Services/ProcurementOverviewService.php';

$queue = ProcurementOverviewService::ownedOperatorRequests((string)($data['user_email'] ?? ''), 40);
$rows = (array)($queue['rows'] ?? []);
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

    <?php if (($queue['available'] ?? false) !== true): ?>
        <div class="empty-state">
            <h3><?= e($tr('proc.operator.queue.unavailable_title', 'Requests unavailable')) ?></h3>
            <p><?= e($tr('proc.operator.queue.unavailable_message', 'Procurement request storage is unavailable or ownership cannot be verified.')) ?></p>
        </div>
    <?php elseif ($rows === []): ?>
        <div class="empty-state">
            <h3><?= e($tr('proc.operator.queue.empty_title', 'No requests found')) ?></h3>
            <p><?= e($tr('proc.operator.queue.empty_message', 'No Procurement requests are owned by your authenticated account.')) ?></p>
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

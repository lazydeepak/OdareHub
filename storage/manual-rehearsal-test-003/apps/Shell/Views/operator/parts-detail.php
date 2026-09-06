<?php
// Operator Layer View: parts-detail
// All composer scope variables available via include.
?>
<?php
    $_pdSubUser = htmlspecialchars(rawurlencode((string)($data['username'] ?? '')));
    $partDetail = (array)($data['part_detail_focus'] ?? []);
    $partProduct = is_array($partDetail['product'] ?? null) ? (array)$partDetail['product'] : [];
    $partState = is_array($partDetail['state'] ?? null) ? (array)$partDetail['state'] : [];
    $partMetrics = (array)($partDetail['metrics'] ?? []);
    $partContext = is_array($partDetail['context'] ?? null) ? (array)$partDetail['context'] : [];
    $partActions = (array)($partDetail['actions'] ?? []);
    $partOrders = (array)($partDetail['orders'] ?? []);
    $partActivity = (array)($partDetail['activity'] ?? []);
    $partError = trim((string)($partDetail['error'] ?? ''));
    $partEmpty = (bool)($partDetail['empty'] ?? true);
    $partTone = match ((string)($partState['tone'] ?? 'info')) {
        'danger' => 'account-pill-danger',
        'warning' => 'account-pill-warn',
        'success' => 'account-pill-ok',
        default => 'account-pill-info',
    };
    $metricClass = static function (string $tone): string {
        return match ($tone) {
            'danger' => ' coverage-kpi-card--danger',
            'warning' => ' coverage-kpi-card--warn',
            default => '',
        };
    };
    $metricFallback = static function (string $key): string {
        return match ($key) {
            'operator.parts.detail.metric.open_demand' => 'Open Demand',
            'operator.parts.detail.metric.shortage' => 'Shortage',
            'operator.parts.detail.metric.stock' => 'Stock',
            'operator.parts.detail.metric.planned' => 'Planned Supply',
            default => '-',
        };
    };
    $actionFallback = static function (string $key): string {
        return match ($key) {
            'operator.parts.detail.action.plan' => 'Plan',
            'operator.parts.detail.action.orders' => 'Orders',
            'operator.parts.detail.action.coverage' => 'Coverage',
            'operator.parts.detail.action.dispatch' => 'Dispatch',
            default => 'Open',
        };
    };
?>
<nav class="operator-subnav" aria-label="<?php echo htmlspecialchars($this->tr('operator.production.subnav.label', 'Production sections')); ?>">
    <a class="operator-subnav-item" href="/u/<?php echo $_pdSubUser; ?>/production"><?php echo htmlspecialchars($this->tr('operator.production.subnav.production', 'Production')); ?></a>
    <a class="operator-subnav-item operator-subnav-item--active" href="/u/<?php echo $_pdSubUser; ?>/parts" aria-current="page"><?php echo htmlspecialchars($this->tr('operator.production.subnav.parts', 'Parts')); ?></a>
    <a class="operator-subnav-item" href="/u/<?php echo $_pdSubUser; ?>/orders"><?php echo htmlspecialchars($this->tr('operator.production.subnav.orders', 'Orders')); ?></a>
    <a class="operator-subnav-item" href="/u/<?php echo $_pdSubUser; ?>/demand"><?php echo htmlspecialchars($this->tr('operator.production.subnav.demand', 'Demand')); ?></a>
    <a class="operator-subnav-item" href="/u/<?php echo $_pdSubUser; ?>/coverage"><?php echo htmlspecialchars($this->tr('operator.production.subnav.coverage', 'Coverage')); ?></a>
    <a class="operator-subnav-item" href="/u/<?php echo $_pdSubUser; ?>/machines"><?php echo htmlspecialchars($this->tr('operator.production.subnav.machines', 'Machines')); ?></a>
    <a class="operator-subnav-item" href="/u/<?php echo $_pdSubUser; ?>/materials"><?php echo htmlspecialchars($this->tr('operator.production.subnav.materials', 'Materials')); ?></a>
</nav>
<section class="coverage-focus-section" aria-label="<?php echo htmlspecialchars($this->tr('operator.parts.detail.aria', 'Part Detail')); ?>">
    <div class="coverage-focus-header">
        <div class="ui-block">
            <div class="u-flex-wrap u-items-center u-gap-8 u-mb-10">
                <a class="btn" href="<?php echo htmlspecialchars($partsBaseUrl); ?>"><?php echo htmlspecialchars($this->tr('operator.parts.detail.back', 'Back to Parts')); ?></a>
                <?php if (!$partEmpty): ?>
                    <span class="account-pill <?php echo htmlspecialchars($partTone); ?>"><?php echo htmlspecialchars(trim((string)($partState['state_label'] ?? '')) !== '' ? (string)$partState['state_label'] : $this->tr('operator.parts.detail.state.healthy', 'Healthy')); ?></span>
                <?php endif; ?>
            </div>
            <h2 class="surface-title">
                <?php echo htmlspecialchars($partEmpty ? $this->tr('operator.parts.detail.title', 'Part Detail') : (string)($partProduct['name'] ?? '-')); ?>
            </h2>
            <?php if (!$partEmpty): ?>
                <p class="recent-focus-subtitle">
                    <?php echo htmlspecialchars(trim((string)($partProduct['number'] ?? '') . ' | ' . (string)($partProduct['model'] ?? ''), ' |')); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php if (!$partEmpty): ?>
            <div class="production-focus-actions">
                <?php foreach ($partActions as $action): ?>
                    <?php if (!is_array($action)) { continue; } ?>
                    <a class="btn<?php echo (string)($action['tone'] ?? '') === 'ok' ? ' ok' : ''; ?>" href="<?php echo htmlspecialchars((string)($action['url'] ?? '#')); ?>">
                        <?php $actionKey = (string)($action['key'] ?? ''); ?>
                        <?php echo htmlspecialchars($this->tr($actionKey, $actionFallback($actionKey))); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($partError !== '' && $partEmpty): ?>
        <p class="coverage-focus-empty"><?php echo htmlspecialchars($this->tr($partError, 'Part not found.')); ?></p>
    <?php elseif (!$partEmpty): ?>
        <div class="coverage-kpi-strip">
            <?php foreach ($partMetrics as $metric): ?>
                <?php if (!is_array($metric)) { continue; } ?>
                <div class="coverage-kpi-card<?php echo htmlspecialchars($metricClass((string)($metric['tone'] ?? ''))); ?>">
                    <?php $metricKey = (string)($metric['key'] ?? ''); ?>
                    <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr($metricKey, $metricFallback($metricKey))); ?></span>
                    <span class="coverage-kpi-value"><?php echo htmlspecialchars((string)($metric['value'] ?? '0.00')); ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <section class="surface-card" aria-label="<?php echo htmlspecialchars($this->tr('operator.parts.detail.next_move', 'Next Move')); ?>">
            <h3 class="work-entry-embed-title"><?php echo htmlspecialchars($this->tr('operator.parts.detail.next_move', 'Next Move')); ?></h3>
            <div class="account-meta-grid">
                <div class="account-meta-item">
                    <div class="account-meta-label"><?php echo htmlspecialchars($this->tr('operator.parts.detail.stage', 'Stage')); ?></div>
                    <div class="account-meta-value"><?php echo htmlspecialchars((string)($partState['stage'] ?? '-')); ?></div>
                </div>
                <div class="account-meta-item">
                    <div class="account-meta-label"><?php echo htmlspecialchars($this->tr('operator.parts.detail.next_due', 'Next Due')); ?></div>
                    <div class="account-meta-value"><?php echo htmlspecialchars(trim((string)($partContext['next_due'] ?? '')) !== '' ? (string)$partContext['next_due'] : '-'); ?></div>
                </div>
                <div class="account-meta-item">
                    <div class="account-meta-label"><?php echo htmlspecialchars($this->tr('operator.parts.detail.due_48h', 'Due Within 48h')); ?></div>
                    <div class="account-meta-value"><?php echo (int)($partContext['due_within_48h'] ?? 0); ?></div>
                </div>
                <div class="account-meta-item">
                    <div class="account-meta-label"><?php echo htmlspecialchars($this->tr('operator.parts.detail.dispatch_ready', 'Dispatch Ready')); ?></div>
                    <div class="account-meta-value"><?php echo htmlspecialchars((string)($partContext['ready_dispatch_qty'] ?? '0.00')); ?></div>
                </div>
            </div>
        </section>

        <section class="surface-card" aria-label="<?php echo htmlspecialchars($this->tr('operator.parts.detail.context', 'Context')); ?>">
            <h3 class="work-entry-embed-title"><?php echo htmlspecialchars($this->tr('operator.parts.detail.context', 'Context')); ?></h3>
            <div class="account-meta-grid">
                <div class="account-meta-item">
                    <div class="account-meta-label"><?php echo htmlspecialchars($this->tr('operator.parts.detail.machine', 'Machine')); ?></div>
                    <div class="account-meta-value"><?php echo htmlspecialchars(trim((string)($partContext['machine'] ?? '')) !== '' ? (string)$partContext['machine'] : '-'); ?></div>
                    <div class="u-muted-compact"><?php echo htmlspecialchars((string)($partContext['machine_meta'] ?? '')); ?></div>
                </div>
                <div class="account-meta-item">
                    <div class="account-meta-label"><?php echo htmlspecialchars($this->tr('operator.parts.detail.owner', 'Owner')); ?></div>
                    <div class="account-meta-value"><?php echo htmlspecialchars(trim((string)($partContext['owner'] ?? '')) !== '' ? (string)$partContext['owner'] : '-'); ?></div>
                    <div class="u-muted-compact"><?php echo htmlspecialchars((string)($partContext['owner_meta'] ?? '')); ?></div>
                </div>
                <div class="account-meta-item">
                    <div class="account-meta-label"><?php echo htmlspecialchars($this->tr('operator.parts.detail.supply', 'Supply')); ?></div>
                    <div class="account-meta-value"><?php echo htmlspecialchars((string)($partContext['supply_mode'] ?? '-')); ?></div>
                    <div class="u-muted-compact"><?php echo htmlspecialchars((string)($partContext['fulfillment_mode'] ?? '')); ?></div>
                </div>
                <div class="account-meta-item">
                    <div class="account-meta-label"><?php echo htmlspecialchars($this->tr('operator.parts.detail.packaging', 'Packaging')); ?></div>
                    <div class="account-meta-value"><?php echo htmlspecialchars((string)((int)($partProduct['qty_per_case'] ?? 0) > 0 ? ((int)$partProduct['qty_per_case'] . ' / case') : '-')); ?></div>
                    <div class="u-muted-compact"><?php echo htmlspecialchars(trim((string)($partProduct['case_type'] ?? '') . ' ' . (string)($partProduct['case_spec'] ?? ''))); ?></div>
                </div>
            </div>
        </section>

        <section class="surface-card" aria-label="<?php echo htmlspecialchars($this->tr('operator.parts.detail.orders', 'Orders')); ?>">
            <h3 class="work-entry-embed-title"><?php echo htmlspecialchars($this->tr('operator.parts.detail.orders', 'Orders')); ?></h3>
            <div class="recent-focus-table-wrap">
                <table class="recent-focus-table">
                    <thead>
                        <tr>
                            <th><?php echo htmlspecialchars($this->tr('operator.orders.col.required', 'Required')); ?></th>
                            <th><?php echo htmlspecialchars($this->tr('operator.orders.col.customer', 'Customer')); ?></th>
                            <th><?php echo htmlspecialchars($this->tr('operator.orders.col.open_demand', 'Open Demand')); ?></th>
                            <th><?php echo htmlspecialchars($this->tr('operator.orders.col.coverage_signal', 'Coverage Signal')); ?></th>
                            <th><?php echo htmlspecialchars($this->tr('operator.orders.col.status', 'Status')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($partOrders === []): ?>
                            <tr><td colspan="5"><?php echo htmlspecialchars($this->tr('operator.parts.detail.orders_empty', 'No active order context for this part.')); ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($partOrders as $order): ?>
                                <?php if (!is_array($order)) { continue; } ?>
                                <tr>
                                    <td><?php echo htmlspecialchars((string)($order['date'] ?? '-')); ?></td>
                                    <td><?php echo htmlspecialchars((string)($order['customer'] ?? '-')); ?></td>
                                    <td><?php echo htmlspecialchars((string)($order['qty'] ?? '0.00')); ?></td>
                                    <td><?php echo htmlspecialchars((string)($order['coverage'] ?? '0.00%')); ?></td>
                                    <td><?php echo htmlspecialchars((string)($order['status'] ?? '-')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="surface-card" aria-label="<?php echo htmlspecialchars($this->tr('operator.parts.detail.recent_work', 'Recent Work')); ?>">
            <h3 class="work-entry-embed-title"><?php echo htmlspecialchars($this->tr('operator.parts.detail.recent_work', 'Recent Work')); ?></h3>
            <?php if ($partActivity === []): ?>
                <div class="recent-focus-empty"><?php echo htmlspecialchars($this->tr('operator.parts.detail.recent_empty', 'No recent work activity for this part.')); ?></div>
            <?php else: ?>
                <div class="recent-focus-table-wrap">
                    <table class="recent-focus-table">
                        <thead>
                            <tr>
                                <th><?php echo htmlspecialchars($this->tr('common.when', 'When')); ?></th>
                                <th><?php echo htmlspecialchars($this->tr('common.type', 'Type')); ?></th>
                                <th><?php echo htmlspecialchars($this->tr('common.notes', 'Notes')); ?></th>
                                <th><?php echo htmlspecialchars($this->tr('operator.orders.col.status', 'Status')); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($partActivity as $activity): ?>
                                <?php if (!is_array($activity)) { continue; } ?>
                                <tr>
                                    <td><?php echo htmlspecialchars((string)($activity['when'] ?? '-')); ?></td>
                                    <td><?php echo htmlspecialchars((string)($activity['type'] ?? '-')); ?></td>
                                    <td><?php echo htmlspecialchars((string)($activity['note'] ?? '-')); ?></td>
                                    <td><?php echo htmlspecialchars((string)($activity['status'] ?? '-')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</section>

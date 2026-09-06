<?php
// Operator Layer View: orders (Daily Orders)
// All composer scope variables available via include.
?>
<?php
    $_ordSubUser = htmlspecialchars(rawurlencode((string)($data['username'] ?? '')));
    $orders = (array)($data['orders_focus'] ?? []);
    $summary = (array)($orders['summary'] ?? []);
    $rows = (array)($orders['rows'] ?? []);
    $anchorDate = (string)($orders['date'] ?? date('Y-m-d'));
    $statusFilter = (string)($orders['status'] ?? 'open');
    $coverageFilter = (string)($orders['coverage'] ?? 'all');
    $search = (string)($orders['q'] ?? '');
    $rangeMode = (string)($orders['range_mode'] ?? 'day');
    $empty = (bool)($orders['empty'] ?? true);
    $error = (string)($orders['error'] ?? '');
    $fmt = static fn(float $v): string => number_format($v, 2, '.', ',');
?>
<nav class="operator-subnav" aria-label="<?php echo htmlspecialchars($this->tr('operator.production.subnav.label', 'Production sections')); ?>">
    <a class="operator-subnav-item" href="/u/<?php echo $_ordSubUser; ?>/production"><?php echo htmlspecialchars($this->tr('operator.production.subnav.production', 'Production')); ?></a>
    <a class="operator-subnav-item" href="/u/<?php echo $_ordSubUser; ?>/parts"><?php echo htmlspecialchars($this->tr('operator.production.subnav.parts', 'Parts')); ?></a>
    <a class="operator-subnav-item operator-subnav-item--active" href="/u/<?php echo $_ordSubUser; ?>/orders" aria-current="page"><?php echo htmlspecialchars($this->tr('operator.production.subnav.orders', 'Orders')); ?></a>
    <a class="operator-subnav-item" href="/u/<?php echo $_ordSubUser; ?>/demand"><?php echo htmlspecialchars($this->tr('operator.production.subnav.demand', 'Demand')); ?></a>
    <a class="operator-subnav-item" href="/u/<?php echo $_ordSubUser; ?>/coverage"><?php echo htmlspecialchars($this->tr('operator.production.subnav.coverage', 'Coverage')); ?></a>
    <a class="operator-subnav-item" href="/u/<?php echo $_ordSubUser; ?>/machines"><?php echo htmlspecialchars($this->tr('operator.production.subnav.machines', 'Machines')); ?></a>
    <a class="operator-subnav-item" href="/u/<?php echo $_ordSubUser; ?>/materials"><?php echo htmlspecialchars($this->tr('operator.production.subnav.materials', 'Materials')); ?></a>
</nav>

<section class="coverage-focus-section" aria-label="<?php echo htmlspecialchars($this->tr('operator.orders.page_title', 'Daily Orders')); ?>">
    <div class="coverage-focus-header">
        <div class="ui-block">
            <h2 class="surface-title"><?php echo htmlspecialchars($this->tr('operator.orders.page_title', 'Daily Orders')); ?></h2>
            <p class="recent-focus-subtitle"><?php echo htmlspecialchars($this->tr('operator.orders.subtitle', 'Operational order lines by customer, part, demand, supply, QC, and dispatch state.')); ?></p>
        </div>
        <form method="get" action="/u/<?php echo $_ordSubUser; ?>/orders" class="production-focus-actions">
            <input type="hidden" name="save_filters" value="1">
            <input
                class="daily-order-search-input"
                type="search"
                name="q"
                value="<?php echo htmlspecialchars($search); ?>"
                placeholder="<?php echo htmlspecialchars($this->tr('operator.orders.filter.search_placeholder', 'Search customer, part, or order #')); ?>"
                aria-label="<?php echo htmlspecialchars($this->tr('operator.orders.filter.search', 'Search orders')); ?>"
            >
            <input
                class="daily-order-date-input"
                type="date"
                name="date"
                value="<?php echo htmlspecialchars($anchorDate); ?>"
                aria-label="<?php echo htmlspecialchars($this->tr('operator.orders.filter.date', 'Order Date')); ?>"
            >
            <select class="daily-order-select" name="range" aria-label="<?php echo htmlspecialchars($this->tr('operator.orders.filter.range', 'Range')); ?>">
                <option value="day"<?php echo $rangeMode === 'day' ? ' selected' : ''; ?>><?php echo htmlspecialchars($this->tr('operator.orders.range.day', 'This Day')); ?></option>
                <option value="week"<?php echo $rangeMode === 'week' ? ' selected' : ''; ?>><?php echo htmlspecialchars($this->tr('operator.orders.range.week', 'This Week')); ?></option>
            </select>
            <select class="daily-order-select" name="status" aria-label="<?php echo htmlspecialchars($this->tr('operator.orders.filter.status', 'Status')); ?>">
                <option value="open"<?php echo $statusFilter === 'open' ? ' selected' : ''; ?>><?php echo htmlspecialchars($this->tr('operator.orders.status.open', 'Open')); ?></option>
                <option value="all"<?php echo $statusFilter === 'all' ? ' selected' : ''; ?>><?php echo htmlspecialchars($this->tr('operator.orders.status.all', 'All')); ?></option>
                <option value="dispatched"<?php echo $statusFilter === 'dispatched' ? ' selected' : ''; ?>><?php echo htmlspecialchars($this->tr('operator.orders.status.dispatched', 'Dispatched')); ?></option>
            </select>
            <select class="daily-order-select" name="coverage" aria-label="<?php echo htmlspecialchars($this->tr('operator.orders.filter.coverage', 'Coverage')); ?>">
                <option value="all"<?php echo $coverageFilter === 'all' ? ' selected' : ''; ?>><?php echo htmlspecialchars($this->tr('operator.orders.coverage.all', 'All Coverage')); ?></option>
                <option value="low"<?php echo $coverageFilter === 'low' ? ' selected' : ''; ?>><?php echo htmlspecialchars($this->tr('operator.orders.coverage.low', 'Low')); ?></option>
                <option value="partial"<?php echo $coverageFilter === 'partial' ? ' selected' : ''; ?>><?php echo htmlspecialchars($this->tr('operator.orders.coverage.partial', 'Partial')); ?></option>
                <option value="full"<?php echo $coverageFilter === 'full' ? ' selected' : ''; ?>><?php echo htmlspecialchars($this->tr('operator.orders.coverage.full', 'Full')); ?></option>
            </select>
            <button class="btn ok" type="submit"><?php echo htmlspecialchars($this->tr('operator.orders.apply', 'Apply')); ?></button>
            <a class="btn" href="/u/<?php echo $_ordSubUser; ?>/orders"><?php echo htmlspecialchars($this->tr('common.reset', 'Reset')); ?></a>
        </form>
    </div>

    <?php if ($error !== ''): ?>
        <p class="coverage-focus-error"><?php echo htmlspecialchars($this->tr($error, 'Orders could not be loaded.')); ?></p>
    <?php endif; ?>

    <?php if ($empty && $error === ''): ?>
        <p class="coverage-focus-empty"><?php echo htmlspecialchars($this->tr('operator.orders.empty', 'No orders found for this selection.')); ?></p>
    <?php else: ?>

    <div class="coverage-kpi-strip">
        <div class="coverage-kpi-card">
            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.orders.kpi.total_orders', 'Orders')); ?></span>
            <span class="coverage-kpi-value"><?php echo (int)($summary['total_orders'] ?? 0); ?></span>
        </div>
        <div class="coverage-kpi-card">
            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.orders.kpi.total_qty', 'Total Qty')); ?></span>
            <span class="coverage-kpi-value"><?php echo $fmt((float)($summary['total_qty'] ?? 0)); ?></span>
        </div>
        <div class="coverage-kpi-card">
            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.orders.kpi.open_demand', 'Open Demand')); ?></span>
            <span class="coverage-kpi-value"><?php echo $fmt((float)($summary['total_open_demand'] ?? 0)); ?></span>
        </div>
        <div class="coverage-kpi-card">
            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.orders.kpi.plan_supply', 'Plan Supply')); ?></span>
            <span class="coverage-kpi-value"><?php echo $fmt((float)($summary['total_planned_supply'] ?? 0)); ?></span>
        </div>
        <div class="coverage-kpi-card">
            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.orders.kpi.dispatched_qty', 'Dispatched Qty')); ?></span>
            <span class="coverage-kpi-value"><?php echo $fmt((float)($summary['total_dispatched'] ?? 0)); ?></span>
        </div>
    </div>

    <div class="coverage-orders-section">
        <h3 class="coverage-orders-title">
            <?php echo htmlspecialchars($this->tr('operator.orders.table.title', 'Order Lines')); ?>
            <span class="badge badge--active"><?php echo count($rows); ?></span>
        </h3>
        <div class="coverage-orders-table-wrap">
            <table class="coverage-orders-table">
                <thead>
                    <tr>
                        <th><?php echo htmlspecialchars($this->tr('operator.orders.col.order', 'Order')); ?></th>
                        <th><?php echo htmlspecialchars($this->tr('operator.orders.col.date', 'Date')); ?></th>
                        <th><?php echo htmlspecialchars($this->tr('operator.orders.col.required', 'Required')); ?></th>
                        <th><?php echo htmlspecialchars($this->tr('operator.orders.col.customer', 'Customer')); ?></th>
                        <th><?php echo htmlspecialchars($this->tr('operator.orders.col.part', 'Part')); ?></th>
                        <th class="num"><?php echo htmlspecialchars($this->tr('operator.orders.col.qty', 'Qty')); ?></th>
                        <th class="num"><?php echo htmlspecialchars($this->tr('operator.orders.col.open_demand', 'Open Demand')); ?></th>
                        <th class="num"><?php echo htmlspecialchars($this->tr('operator.orders.col.usable_supply', 'Usable Supply')); ?></th>
                        <th class="num"><?php echo htmlspecialchars($this->tr('operator.orders.col.plan_supply', 'Plan Supply')); ?></th>
                        <th class="num"><?php echo htmlspecialchars($this->tr('operator.orders.col.qc_pass', 'QC Pass')); ?></th>
                        <th class="num"><?php echo htmlspecialchars($this->tr('operator.orders.col.dispatched', 'Dispatched')); ?></th>
                        <th class="num"><?php echo htmlspecialchars($this->tr('operator.orders.col.coverage_signal', 'Coverage Signal')); ?></th>
                        <th class="num"><?php echo htmlspecialchars($this->tr('operator.orders.col.forecast_pressure', 'Forecast Pressure')); ?></th>
                        <th><?php echo htmlspecialchars($this->tr('operator.orders.col.last_recalc', 'Last Recalc')); ?></th>
                        <th><?php echo htmlspecialchars($this->tr('operator.orders.col.status', 'Status')); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                            $tone = (string)($row['tone'] ?? '');
                            $rowClass = $tone === 'danger' ? ' coverage-orders-row--danger' : ($tone === 'warning' ? ' coverage-orders-row--warn' : '');
                            $partId = (int)($row['product_id'] ?? 0);
                            $statusLabel = match($row['status'] ?? '') {
                                'dispatched' => $this->tr('operator.orders.status.dispatched', 'Dispatched'),
                                'partial'    => $this->tr('operator.orders.status.partial', 'Partial'),
                                'cancelled'  => $this->tr('operator.orders.status.cancelled', 'Cancelled'),
                                default      => $this->tr('operator.orders.status.open', 'Open'),
                            };
                            $coverageStatusLabel = match($row['coverage_status'] ?? '') {
                                'full'    => $this->tr('operator.orders.coverage.full', 'Full'),
                                'partial' => $this->tr('operator.orders.coverage.partial', 'Partial'),
                                default   => $this->tr('operator.orders.coverage.low', 'Low'),
                            };
                            $coveragePct = (float)($row['coverage_pct'] ?? 0);
                            $lastRecalc = trim((string)($row['coverage_last_recalculated_at'] ?? ''));
                        ?>
                        <tr class="coverage-orders-row<?php echo $rowClass; ?>" data-href="/u/<?php echo $_ordSubUser; ?>/parts/detail?part_id=<?php echo $partId; ?>" tabindex="0">
                            <td>
                                <span class="orders-id">#<?php echo (int)($row['id'] ?? 0); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars((string)($row['order_date'] ?? '-')); ?></td>
                            <td><?php echo htmlspecialchars((string)($row['required_date'] ?? '-')); ?></td>
                            <td><?php echo htmlspecialchars((string)($row['customer_name'] ?? '-')); ?></td>
                            <td>
                                <span><?php echo htmlspecialchars((string)($row['part_name'] ?? '-')); ?></span>
                                <?php if (($row['part_number'] ?? '-') !== '-'): ?>
                                <span class="u-muted-compact"><?php echo htmlspecialchars((string)($row['part_number'] ?? '')); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="num"><?php echo $fmt((float)($row['qty'] ?? 0)); ?></td>
                            <td class="num"><?php echo $fmt((float)($row['open_demand_qty'] ?? 0)); ?></td>
                            <td class="num"><?php echo $fmt((float)($row['usable_supply_qty'] ?? 0)); ?></td>
                            <td class="num"><?php echo $fmt((float)($row['planned_supply_qty'] ?? 0)); ?></td>
                            <td class="num"><?php echo $fmt((float)($row['qc_pass_qty'] ?? 0)); ?></td>
                            <td class="num"><?php echo $fmt((float)($row['dispatched_qty'] ?? 0)); ?></td>
                            <td class="num<?php echo (float)($row['shortage_qty'] ?? 0) > 0 ? ' coverage-orders-cell--danger' : ''; ?>">
                                <?php echo number_format($coveragePct, 1); ?>%
                                <span class="u-muted-compact"><?php echo htmlspecialchars($coverageStatusLabel); ?> / <?php echo $fmt((float)($row['shortage_qty'] ?? 0)); ?></span>
                            </td>
                            <td class="num"><?php echo $fmt((float)($row['forecast_pressure_qty'] ?? 0)); ?></td>
                            <td>
                                <?php if ($lastRecalc !== ''): ?>
                                    <?php echo htmlspecialchars($lastRecalc); ?>
                                <?php else: ?>
                                    <span class="u-muted-compact"><?php echo htmlspecialchars($this->tr('operator.orders.last_recalc.none', 'Not yet')); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="orders-status-badge orders-status-badge--<?php echo htmlspecialchars($row['status'] ?? 'open'); ?>">
                                    <?php echo htmlspecialchars($statusLabel); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php endif; ?>
</section>

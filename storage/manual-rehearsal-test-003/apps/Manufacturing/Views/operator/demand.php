<?php
// Operator Layer View: demand
// All composer scope variables available via include.
?>
<?php
    $_demSubUser = htmlspecialchars(rawurlencode((string)($data['username'] ?? '')));
    $demand = (array)($data['demand_focus'] ?? []);
    $summary = (array)($demand['summary'] ?? []);
    $rows = (array)($demand['rows'] ?? []);
    $anchorDate = (string)($demand['anchor_date'] ?? date('Y-m-d'));
    $scopeMode = (string)($demand['scope_mode'] ?? 'my');
    $empty = (bool)($demand['empty'] ?? true);
    $error = (string)($demand['error'] ?? '');
    $fmt = static fn(float $value): string => number_format($value, 2, '.', ',');
?>
<nav class="operator-subnav" aria-label="<?php echo htmlspecialchars($this->tr('operator.production.subnav.label', 'Production sections')); ?>">
    <a class="operator-subnav-item" href="/u/<?php echo $_demSubUser; ?>/production"><?php echo htmlspecialchars($this->tr('operator.production.subnav.production', 'Production')); ?></a>
    <a class="operator-subnav-item" href="/u/<?php echo $_demSubUser; ?>/parts"><?php echo htmlspecialchars($this->tr('operator.production.subnav.parts', 'Parts')); ?></a>
    <a class="operator-subnav-item" href="/u/<?php echo $_demSubUser; ?>/orders"><?php echo htmlspecialchars($this->tr('operator.production.subnav.orders', 'Orders')); ?></a>
    <a class="operator-subnav-item operator-subnav-item--active" href="/u/<?php echo $_demSubUser; ?>/demand" aria-current="page"><?php echo htmlspecialchars($this->tr('operator.production.subnav.demand', 'Demand')); ?></a>
    <a class="operator-subnav-item" href="/u/<?php echo $_demSubUser; ?>/coverage"><?php echo htmlspecialchars($this->tr('operator.production.subnav.coverage', 'Coverage')); ?></a>
    <a class="operator-subnav-item" href="/u/<?php echo $_demSubUser; ?>/machines"><?php echo htmlspecialchars($this->tr('operator.production.subnav.machines', 'Machines')); ?></a>
    <a class="operator-subnav-item" href="/u/<?php echo $_demSubUser; ?>/materials"><?php echo htmlspecialchars($this->tr('operator.production.subnav.materials', 'Materials')); ?></a>
</nav>

<section class="coverage-focus-section" aria-label="<?php echo htmlspecialchars($this->tr('operator.demand.page_title', 'Demand')); ?>">
    <div class="coverage-focus-header">
        <div class="ui-block">
            <h2 class="surface-title"><?php echo htmlspecialchars($this->tr('operator.demand.page_title', 'Demand')); ?></h2>
            <p class="recent-focus-subtitle"><?php echo htmlspecialchars($this->tr('operator.demand.subtitle', 'Daily orders plus safety stock, shifted by each part lead time.')); ?></p>
        </div>
        <form method="get" action="/u/<?php echo $_demSubUser; ?>/demand" class="production-focus-actions">
            <input class="daily-order-date-input" type="date" name="date" value="<?php echo htmlspecialchars($anchorDate); ?>" aria-label="<?php echo htmlspecialchars($this->tr('operator.demand.anchor_date', 'Anchor Date')); ?>">
            <select class="daily-order-select" name="scope" aria-label="<?php echo htmlspecialchars($this->tr('operator.demand.scope', 'Scope')); ?>">
                <option value="my"<?php echo $scopeMode === 'my' ? ' selected' : ''; ?>><?php echo htmlspecialchars($this->tr('operator.demand.scope.my', 'My Parts')); ?></option>
                <option value="all"<?php echo $scopeMode === 'all' ? ' selected' : ''; ?>><?php echo htmlspecialchars($this->tr('operator.demand.scope.all', 'All Parts')); ?></option>
            </select>
            <button class="btn ok" type="submit"><?php echo htmlspecialchars($this->tr('operator.demand.apply', 'Apply')); ?></button>
        </form>
    </div>

    <?php if ($error !== ''): ?>
        <p class="coverage-focus-error"><?php echo htmlspecialchars($this->tr($error, 'Demand data could not be loaded.')); ?></p>
    <?php endif; ?>

    <?php if ($empty && $error === ''): ?>
        <p class="coverage-focus-empty"><?php echo htmlspecialchars($this->tr('operator.demand.empty', 'No demand rows found.')); ?></p>
    <?php else: ?>
        <div class="coverage-kpi-strip">
            <div class="coverage-kpi-card<?php echo (int)($summary['shortfall_count'] ?? 0) > 0 ? ' coverage-kpi-card--danger' : ''; ?>">
                <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.demand.kpi.short_parts', 'Short Parts')); ?></span>
                <span class="coverage-kpi-value"><?php echo (int)($summary['shortfall_count'] ?? 0); ?></span>
            </div>
            <div class="coverage-kpi-card<?php echo (float)($summary['total_shortfall_qty'] ?? 0) > 0 ? ' coverage-kpi-card--danger' : ''; ?>">
                <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.demand.kpi.shortfall_qty', 'Shortfall Qty')); ?></span>
                <span class="coverage-kpi-value"><?php echo $fmt((float)($summary['total_shortfall_qty'] ?? 0)); ?></span>
            </div>
            <div class="coverage-kpi-card">
                <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.demand.kpi.daily_demand', 'Daily Demand')); ?></span>
                <span class="coverage-kpi-value"><?php echo $fmt((float)($summary['total_demand_qty'] ?? 0)); ?></span>
            </div>
            <div class="coverage-kpi-card">
                <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.demand.kpi.safety_stock', 'Safety Stock')); ?></span>
                <span class="coverage-kpi-value"><?php echo $fmt((float)($summary['total_safety_stock_qty'] ?? 0)); ?></span>
            </div>
            <div class="coverage-kpi-card">
                <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.demand.kpi.target_qty', 'Target Qty')); ?></span>
                <span class="coverage-kpi-value"><?php echo $fmt((float)($summary['total_target_qty'] ?? 0)); ?></span>
            </div>
        </div>

        <div class="coverage-orders-section">
            <h3 class="coverage-orders-title">
                <?php echo htmlspecialchars($this->tr('operator.demand.table.title', 'Lead-Day Demand Queue')); ?>
                <span class="badge badge--active"><?php echo count($rows); ?></span>
            </h3>
            <div class="coverage-orders-table-wrap">
                <table class="coverage-orders-table">
                    <thead>
                        <tr>
                            <th><?php echo htmlspecialchars($this->tr('operator.demand.col.part', 'Part')); ?></th>
                            <th class="num"><?php echo htmlspecialchars($this->tr('operator.demand.col.lead_days', 'Lead Days')); ?></th>
                            <th><?php echo htmlspecialchars($this->tr('operator.demand.col.target_date', 'Target Date')); ?></th>
                            <th class="num"><?php echo htmlspecialchars($this->tr('operator.demand.col.daily_order', 'Daily Order')); ?></th>
                            <th class="num"><?php echo htmlspecialchars($this->tr('operator.demand.col.safety_stock', 'Safety Stock')); ?></th>
                            <th class="num"><?php echo htmlspecialchars($this->tr('operator.demand.col.target', 'Target')); ?></th>
                            <th class="num"><?php echo htmlspecialchars($this->tr('operator.demand.col.stock', 'Stock')); ?></th>
                            <th class="num"><?php echo htmlspecialchars($this->tr('operator.demand.col.balance', 'Balance')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php
                                $tone = (string)($row['tone'] ?? '');
                                $rowClass = $tone === 'danger' ? ' coverage-orders-row--danger' : ($tone === 'warning' ? ' coverage-orders-row--warn' : '');
                                $partId = (int)($row['part_id'] ?? 0);
                            ?>
                            <tr class="coverage-orders-row<?php echo $rowClass; ?>" data-href="/u/<?php echo $_demSubUser; ?>/parts/detail?part_id=<?php echo $partId; ?>" tabindex="0">
                                <td><?php echo htmlspecialchars(trim((string)($row['part_name'] ?? '-')) . ' (' . trim((string)($row['part_number'] ?? '-')) . ')'); ?></td>
                                <td class="num"><?php echo (int)($row['lead_workdays'] ?? 0); ?></td>
                                <td><?php echo htmlspecialchars((string)($row['target_date'] ?? '-')); ?></td>
                                <td class="num"><?php echo $fmt((float)($row['demand_qty'] ?? 0)); ?></td>
                                <td class="num"><?php echo $fmt((float)($row['safety_stock_qty'] ?? 0)); ?></td>
                                <td class="num"><?php echo $fmt((float)($row['target_qty'] ?? 0)); ?></td>
                                <td class="num"><?php echo $fmt((float)($row['stock_qty'] ?? 0)); ?></td>
                                <td class="num"><?php echo $fmt((float)($row['balance_qty'] ?? 0)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</section>

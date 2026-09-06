<?php
// Operator Layer View: materials
// All composer scope variables available via include.
?>
<?php
                    $mat = (array)($data['materials_focus'] ?? []);
                    $matKpi = (array)($mat['kpi'] ?? []);
                    $matLowStock = (array)($mat['low_stock'] ?? []);
                    $matOpenOrders = (array)($mat['open_orders'] ?? []);
                    $matCritical = (array)($mat['critical_shortage'] ?? []);
                    $matEmpty = (bool)($mat['empty'] ?? true);
                    $matError = (string)($mat['error'] ?? '');
?>
<?php $_matSubUser = htmlspecialchars(rawurlencode((string)($data['username'] ?? ''))); ?>
<nav class="operator-subnav" aria-label="<?php echo htmlspecialchars($this->tr('operator.production.subnav.label', 'Production sections')); ?>">
    <a class="operator-subnav-item" href="/u/<?php echo $_matSubUser; ?>/production"><?php echo htmlspecialchars($this->tr('operator.production.subnav.production', 'Production')); ?></a>
    <a class="operator-subnav-item" href="/u/<?php echo $_matSubUser; ?>/parts"><?php echo htmlspecialchars($this->tr('operator.production.subnav.parts', 'Parts')); ?></a>
    <a class="operator-subnav-item" href="/u/<?php echo $_matSubUser; ?>/orders"><?php echo htmlspecialchars($this->tr('operator.production.subnav.orders', 'Orders')); ?></a>
    <a class="operator-subnav-item" href="/u/<?php echo $_matSubUser; ?>/demand"><?php echo htmlspecialchars($this->tr('operator.production.subnav.demand', 'Demand')); ?></a>
    <a class="operator-subnav-item" href="/u/<?php echo $_matSubUser; ?>/coverage"><?php echo htmlspecialchars($this->tr('operator.production.subnav.coverage', 'Coverage')); ?></a>
    <a class="operator-subnav-item" href="/u/<?php echo $_matSubUser; ?>/machines"><?php echo htmlspecialchars($this->tr('operator.production.subnav.machines', 'Machines')); ?></a>
    <a class="operator-subnav-item operator-subnav-item--active" href="/u/<?php echo $_matSubUser; ?>/materials" aria-current="page"><?php echo htmlspecialchars($this->tr('operator.production.subnav.materials', 'Materials')); ?></a>
</nav>
<section class="coverage-focus-section" aria-label="<?php echo htmlspecialchars($this->tr('operator.materials.page_title', 'Materials')); ?>">
                    <div class="coverage-focus-header">
                        <h2 class="surface-title"><?php echo htmlspecialchars($this->tr('operator.materials.page_title', 'Materials')); ?></h2>
                    </div>
                    <?php if ($matError !== ''): ?><p class="coverage-focus-error"><?php echo htmlspecialchars($matError); ?></p><?php endif; ?>
                    <?php if ($matEmpty && $matError === ''): ?>
                        <p class="coverage-focus-empty"><?php echo htmlspecialchars($this->tr('operator.materials.empty', 'No materials found.')); ?></p>
                    <?php else: ?>
                    <div class="coverage-kpi-strip">
                        <div class="coverage-kpi-card">
                            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.materials.kpi.total', 'Total Materials')); ?></span>
                            <span class="coverage-kpi-value"><?php echo (int)($matKpi['total_materials'] ?? 0); ?></span>
                        </div>
                        <div class="coverage-kpi-card<?php echo (int)($matKpi['low_stock_count'] ?? 0) > 0 ? ' coverage-kpi-card--warn' : ''; ?>">
                            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.materials.kpi.low_stock', 'Low Stock')); ?></span>
                            <span class="coverage-kpi-value"><?php echo (int)($matKpi['low_stock_count'] ?? 0); ?></span>
                        </div>
                        <div class="coverage-kpi-card<?php echo (int)($matKpi['zero_stock_count'] ?? 0) > 0 ? ' coverage-kpi-card--danger' : ''; ?>">
                            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.materials.kpi.zero_stock', 'Zero Stock')); ?></span>
                            <span class="coverage-kpi-value"><?php echo (int)($matKpi['zero_stock_count'] ?? 0); ?></span>
                        </div>
                        <div class="coverage-kpi-card<?php echo (int)($matKpi['critical_shortage'] ?? 0) > 0 ? ' coverage-kpi-card--danger' : ''; ?>">
                            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.materials.kpi.critical', 'Critical Shortage')); ?></span>
                            <span class="coverage-kpi-value"><?php echo (int)($matKpi['critical_shortage'] ?? 0); ?></span>
                        </div>
                        <div class="coverage-kpi-card">
                            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.materials.kpi.open_orders', 'Open Orders')); ?></span>
                            <span class="coverage-kpi-value"><?php echo (int)($matKpi['open_orders_count'] ?? 0); ?></span>
                        </div>
                    </div>
                    <?php if (!empty($matCritical)): ?>
                    <div class="coverage-orders-section">
                        <h3 class="coverage-orders-title"><?php echo htmlspecialchars($this->tr('operator.materials.critical_shortage', 'Critical Shortage')); ?> <span class="badge badge--danger"><?php echo count($matCritical); ?></span></h3>
                        <div class="coverage-orders-table-wrap">
                            <table class="coverage-orders-table">
                                <thead><tr><th><?php echo htmlspecialchars($this->tr('operator.materials.col.material', 'Material')); ?></th><th><?php echo htmlspecialchars($this->tr('operator.materials.col.code', 'Code')); ?></th><th class="num"><?php echo htmlspecialchars($this->tr('operator.materials.col.on_hand', 'On Hand')); ?></th><th class="num"><?php echo htmlspecialchars($this->tr('operator.materials.col.reserved', 'Reserved')); ?></th><th class="num"><?php echo htmlspecialchars($this->tr('operator.materials.col.net', 'Net')); ?></th></tr></thead>
                                <tbody>
                                    <?php foreach ($matCritical as $mRow): ?>
                                    <tr class="coverage-orders-row coverage-orders-row--danger"><td><?php echo htmlspecialchars((string)($mRow['material_name'] ?? '-')); ?></td><td><?php echo htmlspecialchars((string)($mRow['material_code'] ?? '-')); ?></td><td class="num"><?php echo number_format((float)($mRow['on_hand_qty'] ?? 0), 2); ?></td><td class="num"><?php echo number_format((float)($mRow['reserved_qty'] ?? 0), 2); ?></td><td class="num"><?php echo number_format((float)($mRow['net_qty'] ?? 0), 2); ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($matLowStock)): ?>
                    <div class="coverage-orders-section">
                        <h3 class="coverage-orders-title"><?php echo htmlspecialchars($this->tr('operator.materials.low_stock', 'Low Stock')); ?> <span class="badge badge--warn"><?php echo count($matLowStock); ?></span></h3>
                        <div class="coverage-orders-table-wrap">
                            <table class="coverage-orders-table">
                                <thead><tr><th><?php echo htmlspecialchars($this->tr('operator.materials.col.material', 'Material')); ?></th><th><?php echo htmlspecialchars($this->tr('operator.materials.col.unit', 'Unit')); ?></th><th class="num"><?php echo htmlspecialchars($this->tr('operator.materials.col.on_hand', 'On Hand')); ?></th><th class="num"><?php echo htmlspecialchars($this->tr('operator.materials.col.reorder', 'Reorder Point')); ?></th></tr></thead>
                                <tbody>
                                    <?php foreach ($matLowStock as $mRow): ?>
                                    <tr class="coverage-orders-row coverage-orders-row--warn"><td><?php echo htmlspecialchars((string)($mRow['material_name'] ?? '-')); ?></td><td><?php echo htmlspecialchars((string)($mRow['unit'] ?? '-')); ?></td><td class="num"><?php echo number_format((float)($mRow['on_hand_qty'] ?? 0), 2); ?></td><td class="num"><?php echo number_format((float)($mRow['reorder_point'] ?? 0), 2); ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endif; /* !$matEmpty */ ?>
                </section>

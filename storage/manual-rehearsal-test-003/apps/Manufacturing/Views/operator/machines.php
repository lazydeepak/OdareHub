<?php
// Operator Layer View: machines
// All composer scope variables available via include.
?>
<?php
                    $_mchSubUser = htmlspecialchars(rawurlencode((string)($data['username'] ?? '')));
                ?>
                    <nav class="operator-subnav" aria-label="<?php echo htmlspecialchars($this->tr('operator.production.subnav.label', 'Production sections')); ?>">
                        <a class="operator-subnav-item" href="/u/<?php echo $_mchSubUser; ?>/production"><?php echo htmlspecialchars($this->tr('operator.production.subnav.production', 'Production')); ?></a>
                        <a class="operator-subnav-item" href="/u/<?php echo $_mchSubUser; ?>/parts"><?php echo htmlspecialchars($this->tr('operator.production.subnav.parts', 'Parts')); ?></a>
                        <a class="operator-subnav-item" href="/u/<?php echo $_mchSubUser; ?>/orders"><?php echo htmlspecialchars($this->tr('operator.production.subnav.orders', 'Orders')); ?></a>
                        <a class="operator-subnav-item" href="/u/<?php echo $_mchSubUser; ?>/demand"><?php echo htmlspecialchars($this->tr('operator.production.subnav.demand', 'Demand')); ?></a>
                        <a class="operator-subnav-item" href="/u/<?php echo $_mchSubUser; ?>/coverage"><?php echo htmlspecialchars($this->tr('operator.production.subnav.coverage', 'Coverage')); ?></a>
                        <a class="operator-subnav-item operator-subnav-item--active" href="/u/<?php echo $_mchSubUser; ?>/machines" aria-current="page"><?php echo htmlspecialchars($this->tr('operator.production.subnav.machines', 'Machines')); ?></a>
                        <a class="operator-subnav-item" href="/u/<?php echo $_mchSubUser; ?>/materials"><?php echo htmlspecialchars($this->tr('operator.production.subnav.materials', 'Materials')); ?></a>
                    </nav>
                    <?php
                    $mch = (array)($data['machines_focus'] ?? []);
                    $mchKpi = (array)($mch['kpi'] ?? []);
                    $mchRunNow = (array)($mch['run_now'] ?? []);
                    $mchDelayed = (array)($mch['delayed_jobs'] ?? []);
                    $mchWaitingQc = (array)($mch['waiting_qc'] ?? []);
                    $mchEmpty = (bool)($mch['empty'] ?? true);
                    $mchError = (string)($mch['error'] ?? '');
                    ?>
<section class="coverage-focus-section" aria-label="<?php echo htmlspecialchars($this->tr('operator.machines.page_title', 'Machines')); ?>">
                    <div class="coverage-focus-header">
                        <h2 class="surface-title"><?php echo htmlspecialchars($this->tr('operator.machines.page_title', 'Machines')); ?></h2>
                    </div>
                    <?php if ($mchError !== ''): ?><p class="coverage-focus-error"><?php echo htmlspecialchars($mchError); ?></p><?php endif; ?>
                    <?php if ($mchEmpty && $mchError === ''): ?>
                        <p class="coverage-focus-empty"><?php echo htmlspecialchars($this->tr('operator.machines.empty', 'No machine jobs found for today.')); ?></p>
                    <?php else: ?>
                    <div class="coverage-kpi-strip">
                        <div class="coverage-kpi-card">
                            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.machines.kpi.run_now', 'Running')); ?></span>
                            <span class="coverage-kpi-value"><?php echo (int)($mchKpi['run_now'] ?? 0); ?></span>
                        </div>
                        <div class="coverage-kpi-card">
                            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.machines.kpi.next_queue', 'Next in Queue')); ?></span>
                            <span class="coverage-kpi-value"><?php echo (int)($mchKpi['next_queue'] ?? 0); ?></span>
                        </div>
                        <div class="coverage-kpi-card<?php echo (int)($mchKpi['delayed_jobs'] ?? 0) > 0 ? ' coverage-kpi-card--warn' : ''; ?>">
                            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.machines.kpi.delayed', 'Delayed')); ?></span>
                            <span class="coverage-kpi-value"><?php echo (int)($mchKpi['delayed_jobs'] ?? 0); ?></span>
                        </div>
                        <div class="coverage-kpi-card">
                            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.machines.kpi.waiting_qc', 'Waiting QC')); ?></span>
                            <span class="coverage-kpi-value"><?php echo (int)($mchKpi['waiting_qc'] ?? 0); ?></span>
                        </div>
                        <div class="coverage-kpi-card">
                            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.machines.kpi.planned_qty', 'Planned Qty')); ?></span>
                            <span class="coverage-kpi-value"><?php echo number_format((float)($mchKpi['planned_qty'] ?? 0)); ?></span>
                        </div>
                        <div class="coverage-kpi-card">
                            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.machines.kpi.produced_qty', 'Produced')); ?></span>
                            <span class="coverage-kpi-value"><?php echo number_format((float)($mchKpi['produced_qty'] ?? 0)); ?></span>
                        </div>
                    </div>
                    <?php if (!empty($mchRunNow)): ?>
                    <div class="coverage-orders-section">
                        <h3 class="coverage-orders-title"><?php echo htmlspecialchars($this->tr('operator.machines.running', 'Currently Running')); ?> <span class="badge badge--active"><?php echo count($mchRunNow); ?></span></h3>
                        <div class="coverage-orders-table-wrap">
                            <table class="coverage-orders-table">
                                <thead><tr><th><?php echo htmlspecialchars($this->tr('operator.machines.col.machine', 'Machine')); ?></th><th><?php echo htmlspecialchars($this->tr('operator.machines.col.product', 'Product')); ?></th><th class="num"><?php echo htmlspecialchars($this->tr('operator.machines.col.planned', 'Planned')); ?></th><th class="num"><?php echo htmlspecialchars($this->tr('operator.machines.col.produced', 'Produced')); ?></th><th><?php echo htmlspecialchars($this->tr('operator.machines.col.status', 'Status')); ?></th></tr></thead>
                                <tbody>
                                    <?php foreach (array_slice($mchRunNow, 0, 20) as $mRow): ?>
                                    <tr><td><?php echo htmlspecialchars((string)($mRow['machine_no'] ?? $mRow['machine_name'] ?? '-')); ?></td><td><?php echo htmlspecialchars((string)($mRow['product_name'] ?? $mRow['parts_name'] ?? '-')); ?></td><td class="num"><?php echo number_format((float)($mRow['planned_qty'] ?? 0)); ?></td><td class="num"><?php echo number_format((float)($mRow['produced_qty'] ?? 0)); ?></td><td><?php echo htmlspecialchars((string)($mRow['status'] ?? '-')); ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($mchDelayed)): ?>
                    <div class="coverage-orders-section">
                        <h3 class="coverage-orders-title"><?php echo htmlspecialchars($this->tr('operator.machines.delayed', 'Delayed Jobs')); ?> <span class="badge badge--warn"><?php echo count($mchDelayed); ?></span></h3>
                        <div class="coverage-orders-table-wrap">
                            <table class="coverage-orders-table">
                                <thead><tr><th><?php echo htmlspecialchars($this->tr('operator.machines.col.machine', 'Machine')); ?></th><th><?php echo htmlspecialchars($this->tr('operator.machines.col.product', 'Product')); ?></th><th class="num"><?php echo htmlspecialchars($this->tr('operator.machines.col.planned', 'Planned')); ?></th><th><?php echo htmlspecialchars($this->tr('operator.machines.col.status', 'Status')); ?></th></tr></thead>
                                <tbody>
                                    <?php foreach (array_slice($mchDelayed, 0, 20) as $mRow): ?>
                                    <tr><td><?php echo htmlspecialchars((string)($mRow['machine_no'] ?? $mRow['machine_name'] ?? '-')); ?></td><td><?php echo htmlspecialchars((string)($mRow['product_name'] ?? $mRow['parts_name'] ?? '-')); ?></td><td class="num"><?php echo number_format((float)($mRow['planned_qty'] ?? 0)); ?></td><td><?php echo htmlspecialchars((string)($mRow['status'] ?? '-')); ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endif; /* !$mchEmpty */ ?>
                </section>

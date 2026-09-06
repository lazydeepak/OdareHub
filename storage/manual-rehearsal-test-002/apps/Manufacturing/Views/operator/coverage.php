<?php
// Operator Layer View: coverage
// All composer scope variables available via include.
?>
<?php
                    $_cvgSubUser = htmlspecialchars(rawurlencode((string)($data['username'] ?? '')));
                ?>
                    <nav class="operator-subnav" aria-label="<?php echo htmlspecialchars($this->tr('operator.production.subnav.label', 'Production sections')); ?>">
                        <a class="operator-subnav-item" href="/u/<?php echo $_cvgSubUser; ?>/production"><?php echo htmlspecialchars($this->tr('operator.production.subnav.production', 'Production')); ?></a>
                        <a class="operator-subnav-item" href="/u/<?php echo $_cvgSubUser; ?>/parts"><?php echo htmlspecialchars($this->tr('operator.production.subnav.parts', 'Parts')); ?></a>
                        <a class="operator-subnav-item" href="/u/<?php echo $_cvgSubUser; ?>/orders"><?php echo htmlspecialchars($this->tr('operator.production.subnav.orders', 'Orders')); ?></a>
                        <a class="operator-subnav-item" href="/u/<?php echo $_cvgSubUser; ?>/demand"><?php echo htmlspecialchars($this->tr('operator.production.subnav.demand', 'Demand')); ?></a>
                        <a class="operator-subnav-item operator-subnav-item--active" href="/u/<?php echo $_cvgSubUser; ?>/coverage" aria-current="page"><?php echo htmlspecialchars($this->tr('operator.production.subnav.coverage', 'Coverage')); ?></a>
                        <a class="operator-subnav-item" href="/u/<?php echo $_cvgSubUser; ?>/machines"><?php echo htmlspecialchars($this->tr('operator.production.subnav.machines', 'Machines')); ?></a>
                        <a class="operator-subnav-item" href="/u/<?php echo $_cvgSubUser; ?>/materials"><?php echo htmlspecialchars($this->tr('operator.production.subnav.materials', 'Materials')); ?></a>
                    </nav>
                    <?php
                    $cvg = (array)($data['coverage_focus'] ?? []);
                    $cvgSummary = (array)($cvg['summary'] ?? []);
                    $cvgRiskBands = (array)($cvg['risk_bands'] ?? []);
                    $cvgWindow = (array)($cvg['demand_window'] ?? []);
                    $cvgCritical = (array)($cvg['critical_orders'] ?? []);
                    $cvgLow = (array)($cvg['low_coverage_orders'] ?? []);
                    $cvgEmpty = (bool)($cvg['empty'] ?? true);
                    $cvgError = (string)($cvg['error'] ?? '');
                    $cvgUser = htmlspecialchars((string)($data['username'] ?? ''));
                    ?>
<section class="coverage-focus-section" aria-label="<?php echo htmlspecialchars($this->tr('operator.coverage.page_title', 'Coverage')); ?>">
                    <div class="coverage-focus-header">
                        <div class="ui-block">
                            <h2 class="surface-title"><?php echo htmlspecialchars($this->tr('operator.coverage.page_title', 'Coverage')); ?></h2>
                            <p class="recent-focus-subtitle"><?php echo htmlspecialchars($this->tr('operator.coverage.subtitle', 'Risk, shortage, and due-window coverage across open orders.')); ?></p>
                        </div>
                        <div class="production-focus-actions">
                            <a class="btn-sm" href="/u/<?php echo $_cvgSubUser; ?>/orders?coverage=low">
                                <?php echo htmlspecialchars($this->tr('operator.coverage.open_low_orders', 'Open Low Orders')); ?>
                            </a>
                            <a class="btn-sm" href="/u/<?php echo $_cvgSubUser; ?>/orders">
                                <?php echo htmlspecialchars($this->tr('operator.coverage.open_orders_view', 'Orders View')); ?>
                            </a>
                        </div>
                    </div>

                    <?php if ($cvgError !== ''): ?>
                        <p class="coverage-focus-error"><?php echo htmlspecialchars($cvgError); ?></p>
                    <?php endif; ?>

                    <?php if ($cvgEmpty && $cvgError === ''): ?>
                        <p class="coverage-focus-empty"><?php echo htmlspecialchars($this->tr('operator.coverage.empty', 'No open orders found.')); ?></p>
                    <?php else: ?>

                    <!-- KPI strip -->
                    <div class="coverage-kpi-strip">
                        <div class="coverage-kpi-card">
                            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.coverage.kpi.open_orders', 'Open Orders')); ?></span>
                            <span class="coverage-kpi-value"><?php echo (int)($cvgSummary['open_orders'] ?? 0); ?></span>
                        </div>
                        <div class="coverage-kpi-card">
                            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.coverage.kpi.demand_qty', 'Demand Qty')); ?></span>
                            <span class="coverage-kpi-value"><?php echo number_format((float)($cvgSummary['demand_qty'] ?? 0), 2, '.', ','); ?></span>
                        </div>
                        <div class="coverage-kpi-card<?php echo (float)($cvgSummary['shortage_qty'] ?? 0) > 0 ? ' coverage-kpi-card--danger' : ''; ?>">
                            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.coverage.kpi.shortage_qty', 'Shortage Qty')); ?></span>
                            <span class="coverage-kpi-value"><?php echo number_format((float)($cvgSummary['shortage_qty'] ?? 0), 2, '.', ','); ?></span>
                        </div>
                        <div class="coverage-kpi-card<?php echo (int)($cvgSummary['critical_orders_count'] ?? 0) > 0 ? ' coverage-kpi-card--danger' : ''; ?>">
                            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.coverage.kpi.critical', 'Critical')); ?></span>
                            <span class="coverage-kpi-value"><?php echo (int)($cvgSummary['critical_orders_count'] ?? 0); ?></span>
                        </div>
                        <div class="coverage-kpi-card<?php echo (int)($cvgSummary['low_coverage_orders_count'] ?? 0) > 0 ? ' coverage-kpi-card--warn' : ''; ?>">
                            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.coverage.kpi.low_coverage', 'Low Coverage')); ?></span>
                            <span class="coverage-kpi-value"><?php echo (int)($cvgSummary['low_coverage_orders_count'] ?? 0); ?></span>
                        </div>
                        <div class="coverage-kpi-card">
                            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.coverage.kpi.avg_coverage', 'Avg Coverage')); ?></span>
                            <span class="coverage-kpi-value"><?php echo number_format((float)($cvgSummary['coverage_pct'] ?? 0), 1); ?>%</span>
                        </div>
                        <div class="coverage-kpi-card<?php echo (int)($cvgSummary['window_today_count'] ?? 0) > 0 ? ' coverage-kpi-card--danger' : ''; ?>">
                            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.coverage.kpi.due_today', 'Due Today')); ?></span>
                            <span class="coverage-kpi-value"><?php echo (int)($cvgSummary['window_today_count'] ?? 0); ?></span>
                        </div>
                    </div>

                    <!-- Chart + Demand Window side by side -->
                    <div class="coverage-charts-row">
                        <div class="coverage-chart-card">
                            <h3 class="coverage-chart-title"><?php echo htmlspecialchars($this->tr('operator.coverage.chart.risk_bands', 'Risk Bands')); ?></h3>
                            <canvas id="coverageRiskChart" width="320" height="220" aria-label="<?php echo htmlspecialchars($this->tr('operator.coverage.chart.risk_bands', 'Risk Bands')); ?>" role="img"></canvas>
                            <?php if (empty($cvgRiskBands) || array_sum(array_column($cvgRiskBands, 'count')) === 0): ?>
                            <p class="coverage-chart-empty"><?php echo htmlspecialchars($this->tr('operator.coverage.chart.no_data', 'No data available')); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="coverage-chart-card">
                            <h3 class="coverage-chart-title"><?php echo htmlspecialchars($this->tr('operator.coverage.chart.demand_window', 'Demand Window')); ?></h3>
                            <canvas id="coverageDemandChart" width="320" height="220" aria-label="<?php echo htmlspecialchars($this->tr('operator.coverage.chart.demand_window', 'Demand Window')); ?>" role="img"></canvas>
                            <?php if (empty($cvgWindow) || array_sum(array_column($cvgWindow, 'count')) === 0): ?>
                            <p class="coverage-chart-empty"><?php echo htmlspecialchars($this->tr('operator.coverage.chart.no_data', 'No data available')); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Critical orders table -->
                    <?php if (!empty($cvgCritical)): ?>
                    <div class="coverage-orders-section">
                        <h3 class="coverage-orders-title">
                            <?php echo htmlspecialchars($this->tr('operator.coverage.critical_orders', 'Critical Orders')); ?>
                            <span class="badge badge--danger"><?php echo count($cvgCritical); ?></span>
                        </h3>
                        <div class="coverage-orders-table-wrap">
                            <table class="coverage-orders-table">
                                <thead>
                                    <tr>
                                        <th><?php echo htmlspecialchars($this->tr('operator.coverage.col.order', 'Order')); ?></th>
                                        <th><?php echo htmlspecialchars($this->tr('operator.coverage.col.product', 'Product')); ?></th>
                                        <th class="num"><?php echo htmlspecialchars($this->tr('operator.coverage.col.qty', 'Qty')); ?></th>
                                        <th class="num"><?php echo htmlspecialchars($this->tr('operator.coverage.col.shortage', 'Shortage')); ?></th>
                                        <th class="num"><?php echo htmlspecialchars($this->tr('operator.coverage.col.coverage_pct', 'Coverage')); ?></th>
                                        <th><?php echo htmlspecialchars($this->tr('operator.coverage.col.due', 'Due')); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cvgCritical as $ord): ?>
                                    <?php $ordProductId = (int)($ord['product_id'] ?? 0); $ordId = (int)($ord['id'] ?? 0); ?>
                                    <tr class="coverage-orders-row coverage-orders-row--danger" data-href="/u/<?php echo $_cvgSubUser; ?>/parts/detail?part_id=<?php echo $ordProductId; ?>" tabindex="0">
                                        <td><a href="/u/<?php echo $_cvgSubUser; ?>/orders?q=<?php echo $ordId; ?>&coverage=low">#<?php echo $ordId; ?></a></td>
                                        <td><?php echo htmlspecialchars((string)($ord['product_name'] ?? '-')); ?></td>
                                        <td class="num"><?php echo number_format((float)($ord['qty'] ?? 0)); ?></td>
                                        <td class="num"><?php echo number_format((float)($ord['shortage_qty'] ?? 0)); ?></td>
                                        <td class="num"><?php echo number_format((float)($ord['coverage_pct'] ?? 0), 1); ?>%</td>
                                        <td><?php echo htmlspecialchars((string)($ord['due_date'] ?? '-')); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Low-coverage orders table -->
                    <?php if (!empty($cvgLow)): ?>
                    <div class="coverage-orders-section">
                        <h3 class="coverage-orders-title">
                            <?php echo htmlspecialchars($this->tr('operator.coverage.low_orders', 'Low Coverage Orders')); ?>
                            <span class="badge badge--warn"><?php echo count($cvgLow); ?></span>
                        </h3>
                        <div class="coverage-orders-table-wrap">
                            <table class="coverage-orders-table">
                                <thead>
                                    <tr>
                                        <th><?php echo htmlspecialchars($this->tr('operator.coverage.col.order', 'Order')); ?></th>
                                        <th><?php echo htmlspecialchars($this->tr('operator.coverage.col.product', 'Product')); ?></th>
                                        <th class="num"><?php echo htmlspecialchars($this->tr('operator.coverage.col.qty', 'Qty')); ?></th>
                                        <th class="num"><?php echo htmlspecialchars($this->tr('operator.coverage.col.shortage', 'Shortage')); ?></th>
                                        <th class="num"><?php echo htmlspecialchars($this->tr('operator.coverage.col.coverage_pct', 'Coverage')); ?></th>
                                        <th><?php echo htmlspecialchars($this->tr('operator.coverage.col.due', 'Due')); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cvgLow as $ord): ?>
                                    <?php $ordProductId = (int)($ord['product_id'] ?? 0); $ordId = (int)($ord['id'] ?? 0); ?>
                                    <tr class="coverage-orders-row coverage-orders-row--warn" data-href="/u/<?php echo $_cvgSubUser; ?>/parts/detail?part_id=<?php echo $ordProductId; ?>" tabindex="0">
                                        <td><a href="/u/<?php echo $_cvgSubUser; ?>/orders?q=<?php echo $ordId; ?>&coverage=low">#<?php echo $ordId; ?></a></td>
                                        <td><?php echo htmlspecialchars((string)($ord['product_name'] ?? '-')); ?></td>
                                        <td class="num"><?php echo number_format((float)($ord['qty'] ?? 0)); ?></td>
                                        <td class="num"><?php echo number_format((float)($ord['shortage_qty'] ?? 0)); ?></td>
                                        <td class="num"><?php echo number_format((float)($ord['coverage_pct'] ?? 0), 1); ?>%</td>
                                        <td><?php echo htmlspecialchars((string)($ord['due_date'] ?? '-')); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php endif; /* !$cvgEmpty */ ?>
                </section>

                <script>
                (function () {
                    if (typeof Chart === 'undefined') return;
                    var riskBands = <?php echo json_encode(array_values((array)($cvgRiskBands ?? [])), JSON_UNESCAPED_SLASHES); ?>;
                    var demandWindow = <?php echo json_encode(array_values((array)($cvgWindow ?? [])), JSON_UNESCAPED_SLASHES); ?>;
                    var toneColor = {danger:'#e74c3c', warning:'#f39c12', warn:'#f39c12', success:'#27ae60', info:'#2980b9'};

                    var riskCtx = document.getElementById('coverageRiskChart');
                    if (riskCtx && riskBands.length) {
                        new Chart(riskCtx, {
                            type: 'doughnut',
                            data: {
                                labels: riskBands.map(function(b){return b.label;}),
                                datasets: [{
                                    data: riskBands.map(function(b){return b.count;}),
                                    backgroundColor: riskBands.map(function(b){return toneColor[b.tone]||'#bdc3c7';})
                                }]
                            },
                            options: {responsive:true, plugins:{legend:{position:'bottom'}}}
                        });
                    }

                    var demandCtx = document.getElementById('coverageDemandChart');
                    if (demandCtx && demandWindow.length) {
                        new Chart(demandCtx, {
                            type: 'bar',
                            data: {
                                labels: demandWindow.map(function(d){return d.label;}),
                                datasets: [{
                                    label: 'Orders',
                                    data: demandWindow.map(function(d){return d.count;}),
                                    backgroundColor: demandWindow.map(function(d){return toneColor[d.tone]||'#bdc3c7';})
                                }]
                            },
                            options: {responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}}}
                        });
                    }
                }());
                </script>

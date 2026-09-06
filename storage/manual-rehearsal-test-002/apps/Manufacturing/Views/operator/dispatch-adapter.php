<?php
// Operator Layer View: dispatch-adapter
// All composer scope variables available via include.
?>
<?php
                    $dsp = (array)($data['dispatch_adapter_focus'] ?? []);
                    $dspKpi = (array)($dsp['kpi'] ?? []);
                    $dspReady = (array)($dsp['ready_now'] ?? []);
                    $dspBlocked = (array)($dsp['blocked_hold'] ?? []);
                    $dspAging = (array)($dsp['aging_overdue'] ?? []);
                    $dspEmpty = (bool)($dsp['empty'] ?? true);
                    $dspError = (string)($dsp['error'] ?? '');
?>
<section class="coverage-focus-section" aria-label="<?php echo htmlspecialchars($this->tr('operator.dispatch_adapter.page_title', 'Dispatch')); ?>">
                    <div class="coverage-focus-header">
                        <h2 class="surface-title"><?php echo htmlspecialchars($this->tr('operator.dispatch_adapter.page_title', 'Dispatch Queue')); ?></h2>
                        <a class="btn-sm" href="/u/<?php echo urlencode((string)($data['username'] ?? '')); ?>/fulfillment?tab=dispatch"><?php echo htmlspecialchars($this->tr('operator.dispatch_adapter.open_full', 'Full Dispatch Log')); ?></a>
                    </div>
                    <?php if ($dspError !== ''): ?><p class="coverage-focus-error"><?php echo htmlspecialchars($dspError); ?></p><?php endif; ?>
                    <?php if ($dspEmpty && $dspError === ''): ?>
                        <p class="coverage-focus-empty"><?php echo htmlspecialchars($this->tr('operator.dispatch_adapter.empty', 'No dispatch entries found.')); ?></p>
                    <?php else: ?>
                    <div class="coverage-kpi-strip">
                        <div class="coverage-kpi-card">
                            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.dispatch_adapter.kpi.ready_now', 'Ready Now')); ?></span>
                            <span class="coverage-kpi-value"><?php echo (int)($dspKpi['ready_now'] ?? 0); ?></span>
                        </div>
                        <div class="coverage-kpi-card<?php echo (int)($dspKpi['blocked_hold'] ?? 0) > 0 ? ' coverage-kpi-card--warn' : ''; ?>">
                            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.dispatch_adapter.kpi.blocked', 'Blocked/Hold')); ?></span>
                            <span class="coverage-kpi-value"><?php echo (int)($dspKpi['blocked_hold'] ?? 0); ?></span>
                        </div>
                        <div class="coverage-kpi-card<?php echo (int)($dspKpi['aging_overdue'] ?? 0) > 0 ? ' coverage-kpi-card--danger' : ''; ?>">
                            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.dispatch_adapter.kpi.overdue', 'Aging/Overdue')); ?></span>
                            <span class="coverage-kpi-value"><?php echo (int)($dspKpi['aging_overdue'] ?? 0); ?></span>
                        </div>
                        <div class="coverage-kpi-card">
                            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.dispatch_adapter.kpi.partial', 'Partial Queue')); ?></span>
                            <span class="coverage-kpi-value"><?php echo (int)($dspKpi['partial_queue'] ?? 0); ?></span>
                        </div>
                        <div class="coverage-kpi-card<?php echo (int)($dspKpi['urgent'] ?? 0) > 0 ? ' coverage-kpi-card--danger' : ''; ?>">
                            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.dispatch_adapter.kpi.urgent', 'Urgent')); ?></span>
                            <span class="coverage-kpi-value"><?php echo (int)($dspKpi['urgent'] ?? 0); ?></span>
                        </div>
                    </div>
                    <?php if (!empty($dspReady)): ?>
                    <div class="coverage-orders-section">
                        <h3 class="coverage-orders-title"><?php echo htmlspecialchars($this->tr('operator.dispatch_adapter.ready_now', 'Ready to Dispatch')); ?> <span class="coverage-orders-count"><?php echo count($dspReady); ?></span></h3>
                        <div class="coverage-orders-table-wrap">
                            <table class="coverage-orders-table">
                                <thead><tr><th><?php echo htmlspecialchars($this->tr('operator.dispatch_adapter.col.product', 'Product')); ?></th><th class="num"><?php echo htmlspecialchars($this->tr('operator.dispatch_adapter.col.qty', 'Qty')); ?></th><th><?php echo htmlspecialchars($this->tr('operator.dispatch_adapter.col.destination', 'Destination')); ?></th><th><?php echo htmlspecialchars($this->tr('operator.dispatch_adapter.col.due', 'Due')); ?></th></tr></thead>
                                <tbody>
                                    <?php foreach (array_slice($dspReady, 0, 20) as $dRow): ?>
                                    <tr><td><?php echo htmlspecialchars((string)($dRow['product_name'] ?? $dRow['parts_name'] ?? '-')); ?></td><td class="num"><?php echo number_format((float)($dRow['qty'] ?? 0)); ?></td><td><?php echo htmlspecialchars((string)($dRow['destination'] ?? '-')); ?></td><td><?php echo htmlspecialchars((string)($dRow['required_date'] ?? $dRow['order_date'] ?? '-')); ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($dspBlocked)): ?>
                    <div class="coverage-orders-section">
                        <h3 class="coverage-orders-title coverage-orders-title--warn"><?php echo htmlspecialchars($this->tr('operator.dispatch_adapter.blocked', 'Blocked / Hold')); ?> <span class="coverage-orders-count"><?php echo count($dspBlocked); ?></span></h3>
                        <div class="coverage-orders-table-wrap">
                            <table class="coverage-orders-table">
                                <thead><tr><th><?php echo htmlspecialchars($this->tr('operator.dispatch_adapter.col.product', 'Product')); ?></th><th class="num"><?php echo htmlspecialchars($this->tr('operator.dispatch_adapter.col.qty', 'Qty')); ?></th><th><?php echo htmlspecialchars($this->tr('operator.dispatch_adapter.col.status', 'Status')); ?></th></tr></thead>
                                <tbody>
                                    <?php foreach (array_slice($dspBlocked, 0, 20) as $dRow): ?>
                                    <tr><td><?php echo htmlspecialchars((string)($dRow['product_name'] ?? $dRow['parts_name'] ?? '-')); ?></td><td class="num"><?php echo number_format((float)($dRow['qty'] ?? 0)); ?></td><td><?php echo htmlspecialchars((string)($dRow['status'] ?? '-')); ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endif; /* !$dspEmpty */ ?>
                </section>

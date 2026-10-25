<?php
// Operator Layer View: qc
// All composer scope variables available via include.
?>
<?php
                    $_qcSubUser = htmlspecialchars(rawurlencode((string)($data['username'] ?? '')));
                    $qc = (array)($data['qc_focus'] ?? []);
                    $qcKpi = (array)($qc['kpi'] ?? []);
                    $qcRunNow = (array)($qc['run_now'] ?? []);
                    $qcPending = (array)($qc['pending_qc'] ?? []);
                    $qcFailed = (array)($qc['failed_recheck'] ?? []);
                    $qcReady = (array)($qc['ready_dispatch'] ?? []);
                    $qcEmpty = (bool)($qc['empty'] ?? true);
                    $qcError = (string)($qc['error'] ?? '');
                    $qcDays = (int)($qc['days'] ?? 0);
                    $qcEmptyMsg = $qcDays > 0
                        ? $this->tr('operator.qc.empty.range', 'No QC items found in the selected period.')
                        : $this->tr('operator.qc.empty', 'No QC entries found for today.');
?>
                    <nav class="operator-subnav" aria-label="<?php echo htmlspecialchars($this->tr('operator.processing.subnav.label', 'Processing sections')); ?>">
                        <a class="operator-subnav-item" href="/u/<?php echo $_qcSubUser; ?>/processing"><?php echo htmlspecialchars($this->tr('operator.processing.subnav.hub', 'Overview')); ?></a>
                        <a class="operator-subnav-item" href="/u/<?php echo $_qcSubUser; ?>/assembly"><?php echo htmlspecialchars($this->tr('operator.processing.subnav.assembly', 'Assembly')); ?></a>
                        <a class="operator-subnav-item operator-subnav-item--active" href="/u/<?php echo $_qcSubUser; ?>/qc" aria-current="page"><?php echo htmlspecialchars($this->tr('operator.processing.subnav.qc', 'QC')); ?></a>
                    </nav>
<section class="coverage-focus-section" aria-label="<?php echo htmlspecialchars($this->tr('operator.qc.page_title', 'QC')); ?>">
                     <div class="coverage-focus-header">
                         <h2 class="surface-title"><?php echo htmlspecialchars($this->tr('operator.qc.page_title', 'Quality Control')); ?></h2>
                         <form method="get" action="/u/<?php echo $_qcSubUser; ?>/qc" class="prefs-select-group operator-date-range-form">
                             <label for="qc-date-range" class="prefs-profile-label"><?php echo htmlspecialchars($this->tr('operator.workflow.date_range.label', 'View range')); ?></label>
                             <select id="qc-date-range" name="date_range" class="prefs-select" aria-label="<?php echo htmlspecialchars($this->tr('operator.workflow.date_range.label', 'View range')); ?>">
                                 <option value="today"<?php echo $qcDays === 0 ? ' selected' : ''; ?>><?php echo htmlspecialchars($this->tr('operator.workflow.date_range.today', 'Today only')); ?></option>
                                 <option value="7d"<?php echo $qcDays === 7 ? ' selected' : ''; ?>><?php echo htmlspecialchars($this->tr('operator.workflow.date_range.7d', 'Last 7 days')); ?></option>
                                 <option value="30d"<?php echo $qcDays === 30 ? ' selected' : ''; ?>><?php echo htmlspecialchars($this->tr('operator.workflow.date_range.30d', 'Last 30 days')); ?></option>
                             </select>
                             <button type="submit" class="btn-primary"><?php echo htmlspecialchars($this->tr('operator.workflow.date_range.apply', 'Apply')); ?></button>
                         </form>
                     </div>
                    <?php if ($qcError !== ''): ?><p class="coverage-focus-error"><?php echo htmlspecialchars($qcError); ?></p><?php endif; ?>
                    <?php if ($qcEmpty && $qcError === ''): ?>
                        <p class="coverage-focus-empty"><?php echo htmlspecialchars($qcEmptyMsg); ?></p>
                    <?php else: ?>
                    <div class="coverage-kpi-strip">
                        <div class="coverage-kpi-card<?php echo (int)($qcKpi['urgent'] ?? 0) > 0 ? ' coverage-kpi-card--danger' : ''; ?>">
                            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.qc.kpi.urgent', 'Urgent')); ?></span>
                            <span class="coverage-kpi-value"><?php echo (int)($qcKpi['urgent'] ?? 0); ?></span>
                        </div>
                        <div class="coverage-kpi-card">
                            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.qc.kpi.run_now', 'Run Now')); ?></span>
                            <span class="coverage-kpi-value"><?php echo (int)($qcKpi['run_now'] ?? 0); ?></span>
                        </div>
                        <div class="coverage-kpi-card">
                            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.qc.kpi.pending', 'Pending')); ?></span>
                            <span class="coverage-kpi-value"><?php echo (int)($qcKpi['pending_qc'] ?? 0); ?></span>
                        </div>
                        <div class="coverage-kpi-card<?php echo (int)($qcKpi['failed_recheck'] ?? 0) > 0 ? ' coverage-kpi-card--warn' : ''; ?>">
                            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.qc.kpi.failed', 'Failed/Recheck')); ?></span>
                            <span class="coverage-kpi-value"><?php echo (int)($qcKpi['failed_recheck'] ?? 0); ?></span>
                        </div>
                        <div class="coverage-kpi-card">
                            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.qc.kpi.ready_dispatch', 'Ready to Dispatch')); ?></span>
                            <span class="coverage-kpi-value"><?php echo (int)($qcKpi['ready_dispatch'] ?? 0); ?></span>
                        </div>
                        <div class="coverage-kpi-card<?php echo (int)($qcKpi['overdue'] ?? 0) > 0 ? ' coverage-kpi-card--danger' : ''; ?>">
                            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.qc.kpi.overdue', 'Overdue')); ?></span>
                            <span class="coverage-kpi-value"><?php echo (int)($qcKpi['overdue'] ?? 0); ?></span>
                        </div>
                    </div>
                    <?php if (!empty($qcRunNow)): ?>
                    <div class="coverage-orders-section">
                        <h3 class="coverage-orders-title"><?php echo htmlspecialchars($this->tr('operator.qc.run_now', 'Run Now')); ?> <span class="badge badge--danger"><?php echo count($qcRunNow); ?></span></h3>
                        <div class="coverage-orders-table-wrap">
                            <table class="coverage-orders-table">
                                <thead><tr><th><?php echo htmlspecialchars($this->tr('operator.qc.col.product', 'Product')); ?></th><th class="num"><?php echo htmlspecialchars($this->tr('operator.qc.col.qty', 'Qty')); ?></th><th><?php echo htmlspecialchars($this->tr('operator.qc.col.status', 'Status')); ?></th><th><?php echo htmlspecialchars($this->tr('operator.qc.col.plan_date', 'Plan Date')); ?></th></tr></thead>
                                <tbody>
                                    <?php foreach (array_slice($qcRunNow, 0, 20) as $qcRow): ?>
                                    <tr><td><?php echo htmlspecialchars((string)($qcRow['product_name'] ?? $qcRow['parts_name'] ?? '-')); ?></td><td class="num"><?php echo number_format((float)($qcRow['planned_qty'] ?? $qcRow['qty'] ?? 0)); ?></td><td><?php echo htmlspecialchars((string)($qcRow['status'] ?? '-')); ?></td><td><?php echo htmlspecialchars((string)($qcRow['plan_date'] ?? $qcRow['qc_date'] ?? '-')); ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($qcFailed)): ?>
                    <div class="coverage-orders-section">
                        <h3 class="coverage-orders-title"><?php echo htmlspecialchars($this->tr('operator.qc.failed_recheck', 'Failed / Recheck')); ?> <span class="badge badge--warn"><?php echo count($qcFailed); ?></span></h3>
                        <div class="coverage-orders-table-wrap">
                            <table class="coverage-orders-table">
                                <thead><tr><th><?php echo htmlspecialchars($this->tr('operator.qc.col.product', 'Product')); ?></th><th class="num"><?php echo htmlspecialchars($this->tr('operator.qc.col.fail_qty', 'Fail Qty')); ?></th><th><?php echo htmlspecialchars($this->tr('operator.qc.col.status', 'Status')); ?></th></tr></thead>
                                <tbody>
                                    <?php foreach (array_slice($qcFailed, 0, 20) as $qcRow): ?>
                                    <tr><td><?php echo htmlspecialchars((string)($qcRow['product_name'] ?? $qcRow['parts_name'] ?? '-')); ?></td><td class="num"><?php echo number_format((float)($qcRow['fail_qty'] ?? 0)); ?></td><td><?php echo htmlspecialchars((string)($qcRow['status'] ?? '-')); ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endif; /* !$qcEmpty */ ?>
                </section>

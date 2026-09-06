<?php
// Operator Layer View: assembly
// All composer scope variables available via include.
?>
<?php
                    $_asmSubUser = htmlspecialchars(rawurlencode((string)($data['username'] ?? '')));
                    $asm = (array)($data['assembly_focus'] ?? []);
                    $asmKpi = (array)($asm['kpi'] ?? []);
                    $asmEntries = (array)($asm['today_entries'] ?? []);
                    $asmInProgress = (array)($asm['in_progress'] ?? []);
                    $asmPending = (array)($asm['pending_approval'] ?? []);
                    $asmEmpty = (bool)($asm['empty'] ?? true);
                    $asmError = (string)($asm['error'] ?? '');
                    $asmToday = (string)($asm['today'] ?? date('Y-m-d'));
                    $asmDays = (int)($asm['days'] ?? 0);
                    // Period label for KPI headings — today vs N-day range
                    $asmPeriodLabel = $asmDays > 0
                        ? $this->tr('operator.assembly.kpi.period_total', 'Period Total', ['days' => $asmDays])
                        : $this->tr('operator.assembly.kpi.total', "Today's Total");
                    $asmEmptyMsg = $asmDays > 0
                        ? $this->tr('operator.assembly.empty.range', 'No assembly entries in the selected period.')
                        : $this->tr('operator.assembly.empty', 'No assembly entries for today.');
                    $asmEntriesTitle = $asmDays > 0
                        ? $this->tr('operator.assembly.entries.range', 'Entries in Period')
                        : $this->tr('operator.assembly.entries', "Today's Entries");
?>
                    <nav class="operator-subnav" aria-label="<?php echo htmlspecialchars($this->tr('operator.processing.subnav.label', 'Processing sections')); ?>">
                        <a class="operator-subnav-item" href="/u/<?php echo $_asmSubUser; ?>/processing"><?php echo htmlspecialchars($this->tr('operator.processing.subnav.hub', 'Overview')); ?></a>
                        <a class="operator-subnav-item operator-subnav-item--active" href="/u/<?php echo $_asmSubUser; ?>/assembly" aria-current="page"><?php echo htmlspecialchars($this->tr('operator.processing.subnav.assembly', 'Assembly')); ?></a>
                        <a class="operator-subnav-item" href="/u/<?php echo $_asmSubUser; ?>/qc"><?php echo htmlspecialchars($this->tr('operator.processing.subnav.qc', 'QC')); ?></a>
                    </nav>
<section class="coverage-focus-section" aria-label="<?php echo htmlspecialchars($this->tr('operator.assembly.page_title', 'Assembly')); ?>">
                    <div class="coverage-focus-header">
                        <h2 class="surface-title"><?php echo htmlspecialchars($this->tr('operator.assembly.page_title', 'Assembly')); ?></h2>
                    </div>
                    <?php if ($asmError !== ''): ?><p class="coverage-focus-error"><?php echo htmlspecialchars($asmError); ?></p><?php endif; ?>
                    <?php if ($asmEmpty && $asmError === ''): ?>
                        <p class="coverage-focus-empty"><?php echo htmlspecialchars($asmEmptyMsg); ?></p>
                    <?php else: ?>
                    <div class="coverage-kpi-strip">
                        <div class="coverage-kpi-card">
                            <span class="coverage-kpi-label"><?php echo htmlspecialchars($asmPeriodLabel); ?></span>
                            <span class="coverage-kpi-value"><?php echo (int)($asmKpi['today_total'] ?? 0); ?></span>
                        </div>
                        <div class="coverage-kpi-card<?php echo (int)($asmKpi['in_progress'] ?? 0) > 0 ? ' coverage-kpi-card--active' : ''; ?>">
                            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.assembly.kpi.in_progress', 'In Progress')); ?></span>
                            <span class="coverage-kpi-value"><?php echo (int)($asmKpi['in_progress'] ?? 0); ?></span>
                        </div>
                        <div class="coverage-kpi-card">
                            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.assembly.kpi.completed', 'Completed')); ?></span>
                            <span class="coverage-kpi-value"><?php echo (int)($asmKpi['completed_today'] ?? 0); ?></span>
                        </div>
                        <div class="coverage-kpi-card<?php echo (int)($asmKpi['blocked'] ?? 0) > 0 ? ' coverage-kpi-card--danger' : ''; ?>">
                            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.assembly.kpi.blocked', 'Blocked')); ?></span>
                            <span class="coverage-kpi-value"><?php echo (int)($asmKpi['blocked'] ?? 0); ?></span>
                        </div>
                        <div class="coverage-kpi-card">
                            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.assembly.kpi.planned_qty', 'Planned Qty')); ?></span>
                            <span class="coverage-kpi-value"><?php echo number_format((float)($asmKpi['planned_qty'] ?? 0)); ?></span>
                        </div>
                        <div class="coverage-kpi-card">
                            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.assembly.kpi.completed_qty', 'Completed Qty')); ?></span>
                            <span class="coverage-kpi-value"><?php echo number_format((float)($asmKpi['completed_qty'] ?? 0)); ?></span>
                        </div>
                    </div>
                    <?php if (!empty($asmInProgress)): ?>
                    <div class="coverage-orders-section">
                        <h3 class="coverage-orders-title"><?php echo htmlspecialchars($this->tr('operator.assembly.section.in_progress', 'In Progress')); ?> <span class="badge badge--active"><?php echo count($asmInProgress); ?></span></h3>
                        <div class="coverage-orders-table-wrap">
                            <table class="coverage-orders-table">
                                <thead><tr>
                                    <th><?php echo htmlspecialchars($this->tr('operator.assembly.col.product', 'Product')); ?></th>
                                    <th class="num"><?php echo htmlspecialchars($this->tr('operator.assembly.col.planned', 'Planned')); ?></th>
                                    <th class="num"><?php echo htmlspecialchars($this->tr('operator.assembly.col.completed', 'Completed')); ?></th>
                                    <th><?php echo htmlspecialchars($this->tr('operator.assembly.col.status', 'Status')); ?></th>
                                </tr></thead>
                                <tbody>
                                    <?php foreach (array_slice($asmInProgress, 0, 20) as $aRow): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string)($aRow['product_name'] ?? '-')); ?></td>
                                        <td class="num"><?php echo number_format((float)($aRow['planned_qty'] ?? 0)); ?></td>
                                        <td class="num"><?php echo number_format((float)($aRow['completed_qty'] ?? 0)); ?></td>
                                        <td><?php echo htmlspecialchars((string)($aRow['status'] ?? '-')); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($asmPending)): ?>
                    <div class="coverage-orders-section">
                        <h3 class="coverage-orders-title"><?php echo htmlspecialchars($this->tr('operator.assembly.section.pending_approval', 'Pending Approval')); ?> <span class="badge badge--warn"><?php echo count($asmPending); ?></span></h3>
                        <div class="coverage-orders-table-wrap">
                            <table class="coverage-orders-table">
                                <thead><tr>
                                    <th><?php echo htmlspecialchars($this->tr('operator.assembly.col.product', 'Product')); ?></th>
                                    <th class="num"><?php echo htmlspecialchars($this->tr('operator.assembly.col.planned', 'Planned')); ?></th>
                                    <th class="num"><?php echo htmlspecialchars($this->tr('operator.assembly.col.completed', 'Completed')); ?></th>
                                    <th class="num"><?php echo htmlspecialchars($this->tr('operator.assembly.col.rejected', 'Rejected')); ?></th>
                                </tr></thead>
                                <tbody>
                                    <?php foreach (array_slice($asmPending, 0, 20) as $aRow): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string)($aRow['product_name'] ?? '-')); ?></td>
                                        <td class="num"><?php echo number_format((float)($aRow['planned_qty'] ?? 0)); ?></td>
                                        <td class="num"><?php echo number_format((float)($aRow['completed_qty'] ?? 0)); ?></td>
                                        <td class="num"><?php echo number_format((float)($aRow['rejected_qty'] ?? 0)); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($asmEntries)): ?>
                    <div class="coverage-orders-section">
                        <h3 class="coverage-orders-title"><?php echo htmlspecialchars($asmEntriesTitle); ?> <span class="badge badge--active"><?php echo count($asmEntries); ?></span></h3>
                        <div class="coverage-orders-table-wrap">
                            <table class="coverage-orders-table">
                                <thead><tr>
                                    <th><?php echo htmlspecialchars($this->tr('operator.assembly.col.product', 'Product')); ?></th>
                                    <th class="num"><?php echo htmlspecialchars($this->tr('operator.assembly.col.planned', 'Planned')); ?></th>
                                    <th class="num"><?php echo htmlspecialchars($this->tr('operator.assembly.col.completed', 'Completed')); ?></th>
                                    <th><?php echo htmlspecialchars($this->tr('operator.assembly.col.status', 'Status')); ?></th>
                                </tr></thead>
                                <tbody>
                                    <?php foreach (array_slice($asmEntries, 0, 20) as $aRow): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string)($aRow['product_name'] ?? '-')); ?></td>
                                        <td class="num"><?php echo number_format((float)($aRow['planned_qty'] ?? 0)); ?></td>
                                        <td class="num"><?php echo number_format((float)($aRow['completed_qty'] ?? 0)); ?></td>
                                        <td><?php echo htmlspecialchars((string)($aRow['status'] ?? '-')); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endif; /* !$asmEmpty */ ?>
                </section>

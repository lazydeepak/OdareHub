<?php
// Operator Layer View: dispatch-detail
// All composer scope variables available via include.
?>
<?php $dispatchDetail = (array)($data['dispatch_detail_focus'] ?? []); ?>
                    <?php $dispatchDetailHeader = $dispatchDetail['header'] ?? null; ?>
                    <?php $dispatchDetailLines = (array)($dispatchDetail['lines'] ?? []); ?>
                    <section class="recent-focus" aria-label="<?php echo htmlspecialchars($this->tr('operator.dispatch.detail.title', 'Dispatch Detail')); ?>">
                        <h2 class="dashboard-top-title"><?php echo htmlspecialchars($this->tr('operator.dispatch.detail.title', 'Dispatch Detail')); ?></h2>
                        <p class="recent-focus-subtitle"><?php echo htmlspecialchars($this->tr('operator.dispatch.detail.subtitle', 'Bundle-level dispatch detail with line statuses.')); ?></p>
                        <div class="production-focus-actions u-mb-10">
                            <a class="btn" href="/u/<?php echo urlencode((string)($data['username'] ?? '')); ?>/dispatch?focus=dispatch"><?php echo htmlspecialchars($this->tr('operator.dispatch.detail.back', 'Back to Dispatch')); ?></a>
                        </div>

                        <?php if (!is_array($dispatchDetailHeader) || $dispatchDetailHeader === []): ?>
                            <div class="surface-card">
                                <p class="u-m-0"><?php echo htmlspecialchars($this->tr('operator.dispatch.detail.empty', 'Dispatch detail not found.')); ?></p>
                            </div>
                        <?php else: ?>
                            <section class="surface-card" aria-label="<?php echo htmlspecialchars($this->tr('operator.dispatch.detail.bundle_info', 'Bundle Info')); ?>">
                                <h3 class="work-entry-embed-title"><?php echo htmlspecialchars($this->tr('operator.dispatch.detail.bundle_info', 'Bundle Info')); ?></h3>
                                <div class="recent-focus-table-wrap">
                                    <table class="recent-focus-table">
                                        <tbody>
                                            <tr><th><?php echo htmlspecialchars($this->tr('operator.dispatch.table.load_reference', 'Load Ref')); ?></th><td><?php echo htmlspecialchars((string)($dispatchDetailHeader['bundle_code'] ?? '-')); ?></td></tr>
                                            <tr><th><?php echo htmlspecialchars($this->tr('operator.dispatch.table.destination', 'Destination')); ?></th><td><?php echo htmlspecialchars((string)($dispatchDetailHeader['destination'] ?? '-')); ?></td></tr>
                                            <tr><th><?php echo htmlspecialchars($this->tr('operator.dispatch.table.driver', 'Driver')); ?></th><td><?php echo htmlspecialchars((string)($dispatchDetailHeader['driver_name'] ?? '-')); ?></td></tr>
                                            <tr><th><?php echo htmlspecialchars($this->tr('operator.dispatch.table.truck', 'Truck')); ?></th><td><?php echo htmlspecialchars((string)($dispatchDetailHeader['truck_no'] ?? '-')); ?></td></tr>
                                            <tr><th><?php echo htmlspecialchars($this->tr('operator.dispatch.table.status', 'Status')); ?></th><td><?php echo htmlspecialchars((string)($dispatchDetailHeader['bundle_status'] ?? '-')); ?></td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </section>

                            <section class="surface-card" aria-label="<?php echo htmlspecialchars($this->tr('operator.dispatch.detail.lines', 'Dispatch Lines')); ?>">
                                <h3 class="work-entry-embed-title"><?php echo htmlspecialchars($this->tr('operator.dispatch.detail.lines', 'Dispatch Lines')); ?></h3>
                                <div class="recent-focus-table-wrap">
                                    <table class="recent-focus-table">
                                        <thead>
                                            <tr>
                                                <th><?php echo htmlspecialchars($this->tr('operator.dispatch.table.date', 'Dispatch Date')); ?></th>
                                                <th><?php echo htmlspecialchars($this->tr('operator.dispatch.table.part', 'Part')); ?></th>
                                                <th><?php echo htmlspecialchars($this->tr('operator.dispatch.table.qty', 'Qty')); ?></th>
                                                <th><?php echo htmlspecialchars($this->tr('operator.dispatch.table.status', 'Status')); ?></th>
                                                <th><?php echo htmlspecialchars($this->tr('operator.dispatch.table.load_reference', 'Load Ref')); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($dispatchDetailLines === []): ?>
                                                <tr><td colspan="5"><?php echo htmlspecialchars($this->tr('operator.dispatch.detail.lines.empty', 'No dispatch lines found for this bundle.')); ?></td></tr>
                                            <?php else: ?>
                                                <?php foreach ($dispatchDetailLines as $line): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars((string)($line['dispatch_date'] ?? '')); ?></td>
                                                        <td><?php echo htmlspecialchars(trim((string)($line['part_name'] ?? '-')) . ' (' . trim((string)($line['part_number'] ?? '-')) . ')'); ?></td>
                                                        <td><?php echo htmlspecialchars((string)($line['qty'] ?? '0')); ?></td>
                                                        <td><?php echo htmlspecialchars((string)($line['dispatch_status'] ?? '-') . ' / ' . (string)($line['completion_status'] ?? '-')); ?></td>
                                                        <td><?php echo htmlspecialchars((string)($line['load_reference'] ?? '-')); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        <?php endif; ?>
                    </section>

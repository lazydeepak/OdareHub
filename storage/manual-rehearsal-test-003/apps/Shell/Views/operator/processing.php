<?php
// Operator Layer View: processing
// All composer scope variables available via include.
?>
<?php $processing = (array)($data['processing_focus'] ?? []); ?>
                    <?php $processingProducts = (array)($processing['products'] ?? []); ?>
                    <?php $processingAssemblyRows = (array)($processing['assembly_rows'] ?? []); ?>
                    <?php $processingQcRows = (array)($processing['qc_rows'] ?? []); ?>
                    <?php $processingDate = (string)($processing['selected_date'] ?? date('Y-m-d')); ?>
                    <?php $processingProductId = (int)($processing['selected_product_id'] ?? 0); ?>
                    <?php $processingFlash = trim((string)($processing['flash'] ?? '')); ?>
                    <?php $processingError = trim((string)($processing['error'] ?? '')); ?>
                    <?php $_procSubUser = htmlspecialchars(rawurlencode((string)($data['username'] ?? ''))); ?>
                    <nav class="operator-subnav" aria-label="<?php echo htmlspecialchars($this->tr('operator.processing.subnav.label', 'Processing sections')); ?>">
                        <a class="operator-subnav-item operator-subnav-item--active" href="/u/<?php echo $_procSubUser; ?>/processing" aria-current="page"><?php echo htmlspecialchars($this->tr('operator.processing.subnav.hub', 'Overview')); ?></a>
                        <a class="operator-subnav-item" href="/u/<?php echo $_procSubUser; ?>/assembly"><?php echo htmlspecialchars($this->tr('operator.processing.subnav.assembly', 'Assembly')); ?></a>
                        <a class="operator-subnav-item" href="/u/<?php echo $_procSubUser; ?>/qc"><?php echo htmlspecialchars($this->tr('operator.processing.subnav.qc', 'QC')); ?></a>
                    </nav>
                    <section class="recent-focus" aria-label="<?php echo htmlspecialchars((string)($processing['title'] ?? $this->tr('operator.processing.title', 'Processing'))); ?>">
                        <h2 class="dashboard-top-title"><?php echo htmlspecialchars((string)($processing['title'] ?? $this->tr('operator.processing.title', 'Processing'))); ?></h2>
                        <p class="recent-focus-subtitle"><?php echo htmlspecialchars((string)($processing['subtitle'] ?? $this->tr('operator.processing.subtitle', 'Minimal workbench for assembly and QC planning queues.'))); ?></p>

                        <?php if ($processingFlash !== ''): ?>
                            <div class="flash-ok" role="status"><?php echo htmlspecialchars($processingFlash); ?></div>
                        <?php endif; ?>
                        <?php if ($processingError !== ''): ?>
                            <div class="flash-err" role="alert"><?php echo htmlspecialchars($processingError); ?></div>
                        <?php endif; ?>

                        <section class="surface-card" aria-label="<?php echo htmlspecialchars($this->tr('operator.processing.assembly.title', 'Assembly Plan')); ?>">
                            <h3 class="work-entry-embed-title"><?php echo htmlspecialchars($this->tr('operator.processing.assembly.title', 'Assembly Plan')); ?></h3>
                            <div class="production-focus-actions u-mb-10">
                                <button
                                    class="btn ok"
                                    type="button"
                                    data-processing-toggle="processingAssemblyCreatePanel"
                                    aria-expanded="false"
                                >
                                    <?php echo htmlspecialchars($this->tr('operator.processing.assembly.create', 'Create Assembly Plan')); ?>
                                </button>
                            </div>
                            <div class="ui-block" id="processingAssemblyCreatePanel" hidden>
                                <form method="post" action="/u/<?php echo urlencode((string)($data['username'] ?? '')); ?>/processing/assembly/create" class="production-focus-form">
                                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string)\App\Core\Auth::csrfToken()); ?>">
                                    <label class="production-focus-field">
                                        <span><?php echo htmlspecialchars($this->tr('operator.processing.form.date', 'Plan Date')); ?></span>
                                        <input class="daily-order-date-input" type="date" name="plan_date" value="<?php echo htmlspecialchars($processingDate); ?>" required>
                                    </label>
                                    <label class="production-focus-field production-focus-field-wide">
                                        <span><?php echo htmlspecialchars($this->tr('operator.processing.form.part', 'Part')); ?></span>
                                        <select class="daily-order-select" name="product_id" required>
                                            <option value="0"><?php echo htmlspecialchars($this->tr('operator.processing.form.select_part', 'Select Part')); ?></option>
                                            <?php foreach ($processingProducts as $product): ?>
                                                <option value="<?php echo (int)($product['id'] ?? 0); ?>"<?php if ($processingProductId > 0 && (int)($product['id'] ?? 0) === $processingProductId): ?> selected<?php endif; ?>><?php echo htmlspecialchars(trim((string)($product['parts_name'] ?? '')) . ' (' . trim((string)($product['parts_number'] ?? '-')) . ')'); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label class="production-focus-field">
                                        <span><?php echo htmlspecialchars($this->tr('operator.processing.form.qty', 'Planned Qty')); ?></span>
                                        <input class="daily-order-date-input" type="number" step="0.01" min="0.01" name="planned_qty" required>
                                    </label>
                                    <label class="production-focus-field production-focus-field-wide">
                                        <span><?php echo htmlspecialchars($this->tr('notes', 'Notes')); ?></span>
                                        <input class="header-search" type="text" name="notes" value="">
                                    </label>
                                    <div class="production-focus-actions">
                                        <button class="btn ok" type="submit"><?php echo htmlspecialchars($this->tr('operator.processing.assembly.save', 'Save Assembly Plan')); ?></button>
                                    </div>
                                </form>
                            </div>

                            <div class="recent-focus-table-wrap">
                                <table class="recent-focus-table">
                                    <thead>
                                        <tr>
                                            <th><?php echo htmlspecialchars($this->tr('operator.processing.queue.part', 'Part')); ?></th>
                                            <th><?php echo htmlspecialchars($this->tr('operator.processing.queue.date', 'Plan Date')); ?></th>
                                            <th><?php echo htmlspecialchars($this->tr('operator.processing.queue.qty', 'Planned Qty')); ?></th>
                                            <th><?php echo htmlspecialchars($this->tr('common.status', 'Status')); ?></th>
                                            <th><?php echo htmlspecialchars($this->tr('common.actions', 'Actions')); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($processingAssemblyRows === []): ?>
                                            <tr>
                                                <td colspan="5"><?php echo htmlspecialchars($this->tr('operator.processing.assembly.empty', 'No assembly plans for selected filter.')); ?></td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($processingAssemblyRows as $row): ?>
                                                <?php
                                                    $assemblyStatus = strtolower(trim((string)($row['status'] ?? '')));
                                                    $demandId = (int)($row['id'] ?? 0);
                                                    $processingReturnUrl = '/u/' . urlencode((string)($data['username'] ?? '')) . '/processing';
                                                ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars(trim((string)($row['part_name'] ?? '-')) . ' (' . trim((string)($row['part_number'] ?? '-')) . ')'); ?></td>
                                                    <td><?php echo htmlspecialchars((string)($row['demand_date'] ?? '')); ?></td>
                                                    <td><?php echo htmlspecialchars((string)($row['planned_qty'] ?? '0')); ?></td>
                                                    <td><?php echo htmlspecialchars((string)($row['status'] ?? '-')); ?></td>
                                                    <td>
                                                        <?php if (in_array($assemblyStatus, ['calculated', 'adjusted'], true) && $demandId > 0): ?>
                                                            <form method="post" action="/u/<?php echo urlencode((string)($data['username'] ?? '')); ?>/processing/assembly/status" class="u-inline-form">
                                                                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string)\App\Core\Auth::csrfToken()); ?>">
                                                                <input type="hidden" name="demand_id" value="<?php echo $demandId; ?>">
                                                                <input type="hidden" name="new_status" value="approved">
                                                                <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($processingReturnUrl); ?>">
                                                                <button class="btn ok" type="submit"><?php echo htmlspecialchars($this->tr('operator.processing.action.approve', 'Approve')); ?></button>
                                                            </form>
                                                        <?php else: ?>
                                                            <span class="u-muted-compact">—</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section class="surface-card" aria-label="<?php echo htmlspecialchars($this->tr('operator.processing.qc.title', 'QC Plan')); ?>">
                            <h3 class="work-entry-embed-title"><?php echo htmlspecialchars($this->tr('operator.processing.qc.title', 'QC Plan')); ?></h3>
                            <div class="production-focus-actions u-mb-10">
                                <button
                                    class="btn ok"
                                    type="button"
                                    data-processing-toggle="processingQcCreatePanel"
                                    aria-expanded="false"
                                >
                                    <?php echo htmlspecialchars($this->tr('operator.processing.qc.create', 'Create QC Plan')); ?>
                                </button>
                            </div>
                            <div class="ui-block" id="processingQcCreatePanel" hidden>
                                <form method="post" action="/u/<?php echo urlencode((string)($data['username'] ?? '')); ?>/processing/qc/create" class="production-focus-form">
                                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string)\App\Core\Auth::csrfToken()); ?>">
                                    <label class="production-focus-field">
                                        <span><?php echo htmlspecialchars($this->tr('operator.processing.form.date', 'Plan Date')); ?></span>
                                        <input class="daily-order-date-input" type="date" name="plan_date" value="<?php echo htmlspecialchars($processingDate); ?>" required>
                                    </label>
                                    <label class="production-focus-field production-focus-field-wide">
                                        <span><?php echo htmlspecialchars($this->tr('operator.processing.form.part', 'Part')); ?></span>
                                        <select class="daily-order-select" name="product_id" required>
                                            <option value="0"><?php echo htmlspecialchars($this->tr('operator.processing.form.select_part', 'Select Part')); ?></option>
                                            <?php foreach ($processingProducts as $product): ?>
                                                <option value="<?php echo (int)($product['id'] ?? 0); ?>"<?php if ($processingProductId > 0 && (int)($product['id'] ?? 0) === $processingProductId): ?> selected<?php endif; ?>><?php echo htmlspecialchars(trim((string)($product['parts_name'] ?? '')) . ' (' . trim((string)($product['parts_number'] ?? '-')) . ')'); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label class="production-focus-field">
                                        <span><?php echo htmlspecialchars($this->tr('operator.processing.form.qty', 'Planned Qty')); ?></span>
                                        <input class="daily-order-date-input" type="number" step="0.01" min="0.01" name="planned_qty" required>
                                    </label>
                                    <label class="production-focus-field">
                                        <span><?php echo htmlspecialchars($this->tr('operator.processing.form.priority', 'Priority')); ?></span>
                                        <select class="daily-order-select" name="priority">
                                            <option value="Normal"><?php echo htmlspecialchars($this->tr('operator.processing.priority.normal', 'Normal')); ?></option>
                                            <option value="High"><?php echo htmlspecialchars($this->tr('operator.processing.priority.high', 'High')); ?></option>
                                            <option value="Critical"><?php echo htmlspecialchars($this->tr('operator.processing.priority.critical', 'Critical')); ?></option>
                                            <option value="Medium"><?php echo htmlspecialchars($this->tr('operator.processing.priority.medium', 'Medium')); ?></option>
                                            <option value="Low"><?php echo htmlspecialchars($this->tr('operator.processing.priority.low', 'Low')); ?></option>
                                        </select>
                                    </label>
                                    <label class="production-focus-field production-focus-field-wide">
                                        <span><?php echo htmlspecialchars($this->tr('notes', 'Notes')); ?></span>
                                        <input class="header-search" type="text" name="notes" value="">
                                    </label>
                                    <div class="production-focus-actions">
                                        <button class="btn ok" type="submit"><?php echo htmlspecialchars($this->tr('operator.processing.qc.save', 'Save QC Plan')); ?></button>
                                    </div>
                                </form>
                            </div>

                            <div class="recent-focus-table-wrap">
                                <table class="recent-focus-table">
                                    <thead>
                                        <tr>
                                            <th><?php echo htmlspecialchars($this->tr('operator.processing.queue.part', 'Part')); ?></th>
                                            <th><?php echo htmlspecialchars($this->tr('operator.processing.queue.date', 'Plan Date')); ?></th>
                                            <th><?php echo htmlspecialchars($this->tr('operator.processing.queue.qty', 'Planned Qty')); ?></th>
                                            <th><?php echo htmlspecialchars($this->tr('operator.processing.queue.priority', 'Priority')); ?></th>
                                            <th><?php echo htmlspecialchars($this->tr('common.status', 'Status')); ?></th>
                                            <th><?php echo htmlspecialchars($this->tr('common.actions', 'Actions')); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($processingQcRows === []): ?>
                                            <tr>
                                                <td colspan="6"><?php echo htmlspecialchars($this->tr('operator.processing.qc.empty', 'No QC plans for selected filter.')); ?></td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($processingQcRows as $row): ?>
                                                <?php
                                                    $qcStatus = strtolower(trim((string)($row['status'] ?? '')));
                                                    $qcPlanId = (int)($row['id'] ?? 0);
                                                    $qcReturnUrl = '/u/' . urlencode((string)($data['username'] ?? '')) . '/processing';
                                                ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars(trim((string)($row['part_name'] ?? '-')) . ' (' . trim((string)($row['part_number'] ?? '-')) . ')'); ?></td>
                                                    <td><?php echo htmlspecialchars((string)($row['plan_date'] ?? '')); ?></td>
                                                    <td><?php echo htmlspecialchars((string)($row['planned_qty'] ?? '0')); ?></td>
                                                    <td><?php echo htmlspecialchars((string)($row['priority'] ?? '-')); ?></td>
                                                    <td><?php echo htmlspecialchars((string)($row['status'] ?? '-')); ?></td>
                                                    <td>
                                                        <?php if ($qcStatus === 'system generated' && $qcPlanId > 0): ?>
                                                            <form method="post" action="/u/<?php echo urlencode((string)($data['username'] ?? '')); ?>/processing/qc/status" class="u-inline-form">
                                                                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string)\App\Core\Auth::csrfToken()); ?>">
                                                                <input type="hidden" name="qc_plan_id" value="<?php echo $qcPlanId; ?>">
                                                                <input type="hidden" name="new_status" value="verified by qc">
                                                                <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($qcReturnUrl); ?>">
                                                                <button class="btn ok" type="submit"><?php echo htmlspecialchars($this->tr('operator.processing.action.verify', 'Verify')); ?></button>
                                                            </form>
                                                        <?php elseif (in_array($qcStatus, ['verified by qc', 'adjusted by qc'], true) && $qcPlanId > 0): ?>
                                                            <form method="post" action="/u/<?php echo urlencode((string)($data['username'] ?? '')); ?>/processing/qc/status" class="u-inline-form">
                                                                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string)\App\Core\Auth::csrfToken()); ?>">
                                                                <input type="hidden" name="qc_plan_id" value="<?php echo $qcPlanId; ?>">
                                                                <input type="hidden" name="new_status" value="approved by authority">
                                                                <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($qcReturnUrl); ?>">
                                                                <button class="btn ok" type="submit"><?php echo htmlspecialchars($this->tr('operator.processing.action.approve', 'Approve')); ?></button>
                                                            </form>
                                                        <?php else: ?>
                                                            <span class="u-muted-compact">—</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </section>

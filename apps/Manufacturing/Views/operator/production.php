<?php
// Operator Layer View: production
// All composer scope variables available via include.
?>
<?php $production = (array)($data['production_focus'] ?? []); ?>
                    <?php
                        $_prodSubUser = htmlspecialchars(rawurlencode((string)($data['username'] ?? '')));
                        $_prodSubActive = 'plans';
                    ?>
                    <nav class="operator-subnav" aria-label="<?php echo htmlspecialchars($this->tr('operator.production.subnav.label', 'Production sections')); ?>">
                        <a class="operator-subnav-item operator-subnav-item--active" href="/u/<?php echo $_prodSubUser; ?>/production" aria-current="page"><?php echo htmlspecialchars($this->tr('operator.production.subnav.production', 'Production')); ?></a>
                        <a class="operator-subnav-item" href="/u/<?php echo $_prodSubUser; ?>/parts"><?php echo htmlspecialchars($this->tr('operator.production.subnav.parts', 'Parts')); ?></a>
                        <a class="operator-subnav-item" href="/u/<?php echo $_prodSubUser; ?>/orders"><?php echo htmlspecialchars($this->tr('operator.production.subnav.orders', 'Orders')); ?></a>
                        <a class="operator-subnav-item" href="/u/<?php echo $_prodSubUser; ?>/demand"><?php echo htmlspecialchars($this->tr('operator.production.subnav.demand', 'Demand')); ?></a>
                        <a class="operator-subnav-item" href="/u/<?php echo $_prodSubUser; ?>/coverage"><?php echo htmlspecialchars($this->tr('operator.production.subnav.coverage', 'Coverage')); ?></a>
                        <a class="operator-subnav-item" href="/u/<?php echo $_prodSubUser; ?>/machines"><?php echo htmlspecialchars($this->tr('operator.production.subnav.machines', 'Machines')); ?></a>
                        <a class="operator-subnav-item" href="/u/<?php echo $_prodSubUser; ?>/materials"><?php echo htmlspecialchars($this->tr('operator.production.subnav.materials', 'Materials')); ?></a>
                    </nav>
                    <?php $productionSummary = (array)($production['summary'] ?? []); ?>
                    <?php $productionCoverageRows = (array)($production['coverage_rows'] ?? []); ?>
                    <?php $productionQueueRows = (array)($production['queue_rows'] ?? []); ?>
                    <?php $productionProducts = (array)($production['products'] ?? []); ?>
                    <?php $productionMachines = (array)($production['machines'] ?? []); ?>
                    <section class="recent-focus" aria-label="<?php echo htmlspecialchars((string)($production['title'] ?? $this->tr('operator.production.title', 'Production'))); ?>">
                        <h2 class="dashboard-top-title"><?php echo htmlspecialchars((string)($production['title'] ?? $this->tr('operator.production.title', 'Production'))); ?></h2>
                        <p class="recent-focus-subtitle"><?php echo htmlspecialchars((string)($production['subtitle'] ?? $this->tr('operator.production.subtitle', 'Plans queue, coverage, and quick plan creation in one workspace.'))); ?></p>
                        <?php if (($production['flash'] ?? '') !== ''): ?><div class="flash-ok" role="status"><?php echo htmlspecialchars((string)$production['flash']); ?></div><?php endif; ?>
                        <?php if (($production['error'] ?? '') !== ''): ?><div class="flash-err" role="alert"><?php echo htmlspecialchars((string)$production['error']); ?></div><?php endif; ?>

                        <div class="recent-focus-summary">
                            <div class="recent-focus-kpi">
                                <span class="recent-focus-kpi-label"><?php echo htmlspecialchars($this->tr('operator.production.coverage.low_count', 'Low Coverage')); ?></span>
                                <span class="recent-focus-kpi-value"><?php echo (int)($productionSummary['low_coverage'] ?? 0); ?></span>
                            </div>
                            <div class="recent-focus-kpi">
                                <span class="recent-focus-kpi-label"><?php echo htmlspecialchars($this->tr('operator.production.coverage.shortage_count', 'Shortage Parts')); ?></span>
                                <span class="recent-focus-kpi-value"><?php echo (int)($productionSummary['shortage_parts'] ?? 0); ?></span>
                            </div>
                            <div class="recent-focus-kpi">
                                <span class="recent-focus-kpi-label"><?php echo htmlspecialchars($this->tr('operator.production.coverage.risk_count', 'At Risk Today')); ?></span>
                                <span class="recent-focus-kpi-value"><?php echo (int)($productionSummary['at_risk_today'] ?? 0); ?></span>
                            </div>
                            <div class="recent-focus-kpi">
                                <span class="recent-focus-kpi-label"><?php echo htmlspecialchars($this->tr('operator.production.coverage.overstock_count', 'Overstocked Parts')); ?></span>
                                <span class="recent-focus-kpi-value"><?php echo (int)($productionSummary['overstock_parts'] ?? 0); ?></span>
                            </div>
                        </div>

                        <section class="surface-card" aria-label="<?php echo htmlspecialchars($this->tr('operator.production.create.title', 'Create Production Plan')); ?>">
                            <h3 class="work-entry-embed-title"><?php echo htmlspecialchars($this->tr('operator.production.create.title', 'Create Production Plan')); ?></h3>
                            <div class="production-focus-actions u-mb-10">
                                <button class="btn ok" type="button" data-panel-toggle="productionCreatePanel" aria-expanded="false"><?php echo htmlspecialchars($this->tr('operator.production.create.button', 'Create Production Plan')); ?></button>
                            </div>
                            <div class="ui-block" id="productionCreatePanel" hidden>
                            <p class="work-entry-embed-subtitle"><?php echo htmlspecialchars($this->tr('operator.production.create.subtitle', 'Create a plan quickly and continue from the same queue view.')); ?></p>
                            <?php $prefillProductId = (int)($currentQuery['prefill_product_id'] ?? 0); ?>
                            <form method="post" action="<?php echo htmlspecialchars((string)($production['create_action'] ?? '')); ?>" class="production-focus-form">
                                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string)\App\Core\Auth::csrfToken()); ?>">

                                <label class="production-focus-field">
                                    <span><?php echo htmlspecialchars($this->tr('module.production_plans.plan_date_required', 'Plan Date *')); ?></span>
                                    <input class="daily-order-date-input" type="date" name="plan_date" value="<?php echo htmlspecialchars((string)($production['today'] ?? '')); ?>" required>
                                </label>

                                <label class="production-focus-field">
                                    <span><?php echo htmlspecialchars($this->tr('machine', 'Machine')); ?> *</span>
                                    <select class="daily-order-select" name="machine_id" required>
                                        <option value=""><?php echo htmlspecialchars($this->tr('select_machine', 'Select Machine')); ?></option>
                                        <?php foreach ($productionMachines as $machine): ?>
                                            <option value="<?php echo (int)($machine['id'] ?? 0); ?>"><?php echo htmlspecialchars(trim((string)($machine['machine_no'] ?? '')) . ' - ' . trim((string)($machine['machine_name'] ?? ''))); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>

                                <label class="production-focus-field">
                                    <span><?php echo htmlspecialchars($this->tr('part', 'Part')); ?> *</span>
                                    <select class="daily-order-select" name="product_id" required>
                                        <option value=""><?php echo htmlspecialchars($this->tr('select_part', 'Select Part')); ?></option>
                                        <?php foreach ($productionProducts as $product): ?>
                                            <option value="<?php echo (int)($product['id'] ?? 0); ?>"<?php if ($prefillProductId > 0 && (int)($product['id'] ?? 0) === $prefillProductId): ?> selected<?php endif; ?>><?php echo htmlspecialchars(trim((string)($product['parts_name'] ?? '')) . ' (' . trim((string)($product['parts_number'] ?? '-')) . ')'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>

                                <label class="production-focus-field">
                                    <span><?php echo htmlspecialchars($this->tr('module.production_plans.planned_qty_required', 'Planned Qty *')); ?></span>
                                    <input class="daily-order-date-input" type="number" step="0.01" min="0.01" name="planned_qty" required>
                                </label>

                                <label class="production-focus-field production-focus-field-wide">
                                    <span><?php echo htmlspecialchars($this->tr('notes', 'Notes')); ?></span>
                                    <input class="header-search" type="text" name="notes" value="">
                                </label>

                                <div class="production-focus-actions">
                                    <button class="btn ok" type="submit"><?php echo htmlspecialchars($this->tr('save_production_plan', 'Save Production Plan')); ?></button>
                                    <a class="btn" href="<?php echo htmlspecialchars((string)($production['queue_url'] ?? '/u/' . urlencode((string)($data['username'] ?? '')) . '/production')); ?>"><?php echo htmlspecialchars($this->tr('module.production_queue.title', 'Production Queue')); ?></a>
                                    <a class="btn" href="<?php echo htmlspecialchars((string)($production['list_url'] ?? '/u/' . urlencode((string)($data['username'] ?? '')) . '/production')); ?>"><?php echo htmlspecialchars($this->tr('module.production_plans.title', 'Production Plans')); ?></a>
                                </div>
                            </form>
                            </div>
                        </section>

                        <div class="recent-focus-table-wrap">
                            <table class="recent-focus-table">
                                <thead>
                                    <tr>
                                        <th><?php echo htmlspecialchars($this->tr('operator.production.plan.part', 'Part')); ?></th>
                                        <th><?php echo htmlspecialchars($this->tr('operator.production.plan.date', 'Plan Date')); ?></th>
                                        <th><?php echo htmlspecialchars($this->tr('operator.production.plan.qty', 'Planned Qty')); ?></th>
                                        <th><?php echo htmlspecialchars($this->tr('operator.production.plan.machine', 'Machine')); ?></th>
                                        <th><?php echo htmlspecialchars($this->tr('common.status', 'Status')); ?></th>
                                        <th><?php echo htmlspecialchars($this->tr('common.coverage', 'Coverage')); ?></th>
                                        <th><?php echo htmlspecialchars($this->tr('common.actions', 'Actions')); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($productionQueueRows === []): ?>
                                        <tr>
                                            <td colspan="7"><?php echo htmlspecialchars($this->tr('operator.production.plan.empty', 'No production plans in queue.')); ?></td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($productionQueueRows as $planRow): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars(trim((string)($planRow['part_name'] ?? '-')) . ' (' . trim((string)($planRow['part_number'] ?? '-')) . ')'); ?></td>
                                                <td><?php echo htmlspecialchars((string)($planRow['plan_date'] ?? '')); ?></td>
                                                <td><?php echo htmlspecialchars((string)($planRow['planned_qty'] ?? '0')); ?></td>
                                                <td><?php echo htmlspecialchars((string)($planRow['machine_label'] ?? '-')); ?></td>
                                                <td><?php echo htmlspecialchars((string)($planRow['status'] ?? '-')); ?></td>
                                                <td><?php echo htmlspecialchars((string)($planRow['coverage_pct'] ?? '0')); ?>%</td>
                                                <td>
                                                    <?php
                                                        $planStatus = strtolower(trim((string)($planRow['status'] ?? '')));
                                                        $planId = (int)($planRow['id'] ?? 0);
                                                        $productionReturnUrl = '/u/' . urlencode((string)($data['username'] ?? '')) . '/production';
                                                    ?>
                                                    <?php if ($planStatus === 'planned' || $planStatus === 'pending'): ?>
                                                        <form method="post" action="/u/<?php echo urlencode((string)($data['username'] ?? '')); ?>/production/plan/status" class="u-inline-form">
                                                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string)\App\Core\Auth::csrfToken()); ?>">
                                                            <input type="hidden" name="plan_id" value="<?php echo $planId; ?>">
                                                            <input type="hidden" name="new_status" value="In Progress">
                                                            <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($productionReturnUrl); ?>">
                                                            <button class="btn ok" type="submit"><?php echo htmlspecialchars($this->tr('operator.production.action.start', 'Start')); ?></button>
                                                        </form>
                                                    <?php elseif ($planStatus === 'in progress' || $planStatus === 'in_progress'): ?>
                                                        <form method="post" action="/u/<?php echo urlencode((string)($data['username'] ?? '')); ?>/production/plan/status" class="u-inline-form">
                                                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string)\App\Core\Auth::csrfToken()); ?>">
                                                            <input type="hidden" name="plan_id" value="<?php echo $planId; ?>">
                                                            <input type="hidden" name="new_status" value="Completed">
                                                            <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($productionReturnUrl); ?>">
                                                            <button class="btn ok" type="submit"><?php echo htmlspecialchars($this->tr('operator.production.action.complete', 'Complete')); ?></button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="u-muted-compact"><?php echo htmlspecialchars((string)($planRow['status'] ?? '-')); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="recent-focus-table-wrap">
                            <table class="recent-focus-table">
                                <thead>
                                    <tr>
                                        <th><?php echo htmlspecialchars($this->tr('operator.production.coverage.part', 'Coverage Part')); ?></th>
                                        <th><?php echo htmlspecialchars($this->tr('common.coverage', 'Coverage')); ?></th>
                                        <th><?php echo htmlspecialchars($this->tr('common.shortage', 'Shortage')); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($productionCoverageRows === []): ?>
                                        <tr>
                                            <td colspan="3"><?php echo htmlspecialchars($this->tr('operator.production.coverage.empty', 'No coverage risk items for today.')); ?></td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($productionCoverageRows as $coverageRow): ?>
                                            <?php $cvRowPartId = (int)($coverageRow['part_id'] ?? 0); $cvRowHref = $cvRowPartId > 0 ? '/u/' . urlencode((string)($data['username'] ?? '')) . '/parts/detail?part_id=' . $cvRowPartId : ''; ?>
                                            <tr<?php if ($cvRowHref !== ''): ?> data-href="<?php echo htmlspecialchars($cvRowHref); ?>" tabindex="0"<?php endif; ?>>
                                                <td><?php echo htmlspecialchars(trim((string)($coverageRow['part_name'] ?? '-')) . ' (' . trim((string)($coverageRow['part_number'] ?? '-')) . ')'); ?></td>
                                                <td><?php echo htmlspecialchars((string)($coverageRow['coverage_pct'] ?? '0')); ?>%</td>
                                                <td><?php echo htmlspecialchars((string)($coverageRow['shortage_qty'] ?? '0')); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                    </section>

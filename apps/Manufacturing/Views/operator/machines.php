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
                     $mchFilters = (array)($mch['filters'] ?? []);
                     $mchFilterMachines = (array)($mchFilters['machines'] ?? []);
                     $mchFilterSections = (array)($mchFilters['sections'] ?? []);
                     $mchInvalidFilters = (array)($mch['invalid_filters'] ?? []);
                     $mchSelectedMachine = (string)($mch['scope']['machine_id'] ?? '0');
                     $mchSelectedSection = (string)($mch['scope']['section'] ?? '');
                     $mchEmpty = (bool)($mch['empty'] ?? true);
                    $mchError = (string)($mch['error'] ?? '');
                    ?>
<section class="coverage-focus-section" aria-label="<?php echo htmlspecialchars($this->tr('operator.machines.page_title', 'Machines')); ?>">
                     <div class="coverage-focus-header">
                         <h2 class="surface-title"><?php echo htmlspecialchars($this->tr('operator.machines.page_title', 'Machines')); ?></h2>
                     </div>
                     <form method="get" action="/u/<?php echo $_mchSubUser; ?>/machines" class="operator-filter-form" aria-label="<?php echo htmlspecialchars($this->tr('operator.machines.filter.title', 'Filter machines')); ?>">
                         <label>
                             <span><?php echo htmlspecialchars($this->tr('operator.machines.filter.machine', 'Machine')); ?></span>
                             <select name="machine_id">
                                 <option value=""><?php echo htmlspecialchars($this->tr('operator.machines.filter.all_machines', 'All machines')); ?></option>
                                 <?php foreach ($mchFilterMachines as $mchFilterMachine): ?>
                                     <?php if (!is_array($mchFilterMachine)) { continue; } ?>
                                     <?php $mchFilterMachineId = (string)(int)($mchFilterMachine['id'] ?? 0); ?>
                                     <?php if ($mchFilterMachineId === '0') { continue; } ?>
                                     <option value="<?php echo htmlspecialchars($mchFilterMachineId, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $mchSelectedMachine === $mchFilterMachineId ? ' selected' : ''; ?>><?php echo htmlspecialchars((string)($mchFilterMachine['machine_no'] ?? $mchFilterMachine['machine_name'] ?? $mchFilterMachineId)); ?></option>
                                 <?php endforeach; ?>
                             </select>
                         </label>
                         <label>
                             <span><?php echo htmlspecialchars($this->tr('operator.machines.filter.section', 'Section')); ?></span>
                             <select name="section">
                                 <option value=""><?php echo htmlspecialchars($this->tr('operator.machines.filter.all_sections', 'All sections')); ?></option>
                                 <?php foreach ($mchFilterSections as $mchFilterSection): ?>
                                     <?php $mchFilterSection = trim((string)$mchFilterSection); if ($mchFilterSection === '') { continue; } ?>
                                     <option value="<?php echo htmlspecialchars($mchFilterSection, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $mchSelectedSection === $mchFilterSection ? ' selected' : ''; ?>><?php echo htmlspecialchars($mchFilterSection); ?></option>
                                 <?php endforeach; ?>
                             </select>
                         </label>
                         <button type="submit"><?php echo htmlspecialchars($this->tr('operator.machines.filter.apply', 'Apply')); ?></button>
                         <a href="/u/<?php echo $_mchSubUser; ?>/machines"><?php echo htmlspecialchars($this->tr('operator.machines.filter.clear', 'Clear')); ?></a>
                     </form>
                     <?php if ($mchInvalidFilters !== []): ?><p class="coverage-focus-error"><?php echo htmlspecialchars($this->tr('operator.machines.filter.invalid', 'The selected machine filter is invalid. No work is shown.')); ?></p><?php endif; ?>
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
                            <span class="coverage-kpi-value"><?php echo is_numeric($mchKpi['planned_qty'] ?? null) ? number_format((float)$mchKpi['planned_qty']) : '-'; ?></span>
                        </div>
                        <div class="coverage-kpi-card">
                            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.machines.kpi.produced_qty', 'Produced')); ?></span>
                            <span class="coverage-kpi-value"><?php echo is_numeric($mchKpi['produced_qty'] ?? null) ? number_format((float)$mchKpi['produced_qty']) : '-'; ?></span>
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

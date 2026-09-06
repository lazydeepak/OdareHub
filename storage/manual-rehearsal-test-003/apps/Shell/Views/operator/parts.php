<?php
// Operator Layer View: parts
// All composer scope variables available via include.
?>
<?php
    $_partsSubUser = htmlspecialchars(rawurlencode((string)($data['username'] ?? '')));
?>
                    <nav class="operator-subnav" aria-label="<?php echo htmlspecialchars($this->tr('operator.production.subnav.label', 'Production sections')); ?>">
                        <a class="operator-subnav-item" href="/u/<?php echo $_partsSubUser; ?>/production"><?php echo htmlspecialchars($this->tr('operator.production.subnav.production', 'Production')); ?></a>
                        <a class="operator-subnav-item operator-subnav-item--active" href="/u/<?php echo $_partsSubUser; ?>/parts" aria-current="page"><?php echo htmlspecialchars($this->tr('operator.production.subnav.parts', 'Parts')); ?></a>
                        <a class="operator-subnav-item" href="/u/<?php echo $_partsSubUser; ?>/orders"><?php echo htmlspecialchars($this->tr('operator.production.subnav.orders', 'Orders')); ?></a>
                        <a class="operator-subnav-item" href="/u/<?php echo $_partsSubUser; ?>/demand"><?php echo htmlspecialchars($this->tr('operator.production.subnav.demand', 'Demand')); ?></a>
                        <a class="operator-subnav-item" href="/u/<?php echo $_partsSubUser; ?>/coverage"><?php echo htmlspecialchars($this->tr('operator.production.subnav.coverage', 'Coverage')); ?></a>
                        <a class="operator-subnav-item" href="/u/<?php echo $_partsSubUser; ?>/machines"><?php echo htmlspecialchars($this->tr('operator.production.subnav.machines', 'Machines')); ?></a>
                        <a class="operator-subnav-item" href="/u/<?php echo $_partsSubUser; ?>/materials"><?php echo htmlspecialchars($this->tr('operator.production.subnav.materials', 'Materials')); ?></a>
                    </nav>
<section class="surface-card" aria-label="Parts Table">
                        <h2 class="surface-title"><?php echo htmlspecialchars($this->tr('operator.parts.table.title', 'Parts Master Data')); ?></h2>
                        <?php if (!empty($partsTable['rows'])): ?>
                            <div class="recent-focus-table-wrap">
                                <table class="recent-focus-table">
                                    <thead>
                                        <tr>
                                            <th><?php echo htmlspecialchars($this->tr('common.id', 'ID')); ?></th>
                                            <th><?php echo htmlspecialchars($this->tr('operator.parts.field_name', 'Part Name')); ?></th>
                                            <th><?php echo htmlspecialchars($this->tr('operator.parts.field_number', 'Part Number')); ?></th>
                                            <th><?php echo htmlspecialchars($this->tr('common.model', 'Model')); ?></th>
                                            <th><?php echo htmlspecialchars($this->tr('operator.parts.field_stock', 'Stock')); ?></th>
                                            <th><?php echo htmlspecialchars($this->tr('operator.parts.field_today_demand', 'Today\'s Demand')); ?></th>
                                            <th><?php echo htmlspecialchars($this->tr('operator.parts.field_coverage', 'Coverage')); ?></th>
                                            <th><?php echo htmlspecialchars($this->tr('operator.parts.field_next_plan_date', 'Next Plan Date')); ?></th>
                                            <th><?php echo htmlspecialchars($this->tr('operator.parts.field_next_plan_qty', 'Next Plan Qty')); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ((array)($partsTable['rows'] ?? []) as $partRow): ?>
                                            <?php
                                                $partSearchTokens = [
                                                    trim((string)($partRow['id'] ?? '')),
                                                    trim((string)($partRow['parts_name'] ?? '')),
                                                    trim((string)($partRow['parts_number'] ?? '')),
                                                    trim((string)($partRow['model'] ?? '')),
                                                ];
                                                $partSearchValue = trim(implode(' ', array_filter($partSearchTokens, static fn ($token): bool => $token !== '')));
                                            ?>
                                            <tr
                                                data-part-id="<?php echo htmlspecialchars((string)($partRow['id'] ?? '')); ?>"
                                                data-part-number="<?php echo htmlspecialchars((string)($partRow['parts_number'] ?? '')); ?>"
                                                data-part-name="<?php echo htmlspecialchars((string)($partRow['parts_name'] ?? '')); ?>"
                                                data-part-search="<?php echo htmlspecialchars($partSearchValue); ?>"
                                                data-part-detail-url="<?php echo htmlspecialchars($partsDetailBaseUrl . '?part_id=' . rawurlencode((string)($partRow['id'] ?? ''))); ?>"
                                                class="parts-row-link"
                                                tabindex="0"
                                                role="link"
                                            >
                                                <td><?php echo htmlspecialchars((string)($partRow['id'] ?? '')); ?></td>
                                                <td>
                                                    <a href="<?php echo htmlspecialchars($partsDetailBaseUrl . '?part_id=' . rawurlencode((string)($partRow['id'] ?? ''))); ?>">
                                                        <?php echo htmlspecialchars((string)($partRow['parts_name'] ?? '')); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo htmlspecialchars((string)($partRow['parts_number'] ?? '')); ?></td>
                                                <td><?php echo htmlspecialchars((string)($partRow['model'] ?? '')); ?></td>
                                                <td class="operator-text-right"><?php echo number_format((float)($partRow['stock'] ?? 0), 2, '.', ''); ?></td>
                                                <td class="operator-text-right"><?php echo number_format((float)($partRow['today_demand'] ?? 0), 2, '.', ''); ?></td>
                                                <td><?php echo htmlspecialchars((string)($partRow['coverage'] ?? '100%')); ?></td>
                                                <td><?php echo htmlspecialchars((string)($partRow['next_plan_date'] ?? '-')); ?></td>
                                                <td class="operator-text-right"><?php echo number_format((float)($partRow['next_plan_qty'] ?? 0), 2, '.', ''); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="recent-focus-empty"><?php echo htmlspecialchars((string)($partsTable['empty_label'] ?? $this->tr('operator.parts.table.empty', 'No parts found.'))); ?></div>
                        <?php endif; ?>
                    </section>

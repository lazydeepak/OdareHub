<?php
// Operator Layer View: dispatch
// All composer scope variables available via include.
?>
<?php $dispatch = (array)($data['dispatch_focus'] ?? []); ?>
                    <?php $dispatchRows = (array)($dispatch['rows'] ?? []); ?>
                    <?php $dispatchProducts = (array)($dispatch['products'] ?? []); ?>
                    <?php $dispatchDestinations = (array)($dispatch['destinations'] ?? []); ?>
                    <?php $dispatchDate = (string)($dispatch['selected_date'] ?? date('Y-m-d')); ?>
                    <?php $dispatchToDate = (string)($dispatch['selected_to_date'] ?? $dispatchDate); ?>
                    <?php $dispatchProductId = (int)($dispatch['selected_product_id'] ?? 0); ?>
                    <?php $dispatchDefaultDriver = trim((string)($dispatch['default_driver_name'] ?? '')); ?>
                    <?php $dispatchDefaultTruck = trim((string)($dispatch['default_truck_no'] ?? '')); ?>
                    <?php $dispatchFlash = trim((string)($dispatch['flash'] ?? '')); ?>
                    <?php $dispatchError = trim((string)($dispatch['error'] ?? '')); ?>

                    <section class="recent-focus" aria-label="<?php echo htmlspecialchars((string)($dispatch['title'] ?? $this->tr('operator.dispatch.title', 'Dispatch'))); ?>">
                        <h2 class="dashboard-top-title"><?php echo htmlspecialchars((string)($dispatch['title'] ?? $this->tr('operator.dispatch.title', 'Dispatch'))); ?></h2>
                        <p class="recent-focus-subtitle"><?php echo htmlspecialchars((string)($dispatch['subtitle'] ?? $this->tr('operator.dispatch.subtitle', 'Dispatch queue is populated from Preparation ready bundles and sorted by next schedule.'))); ?></p>
                        <p class="recent-focus-subtitle"><?php echo htmlspecialchars($this->tr('operator.dispatch.window.subtitle', 'Showing 7-day window: {from} to {to}', ['from' => $dispatchDate, 'to' => $dispatchToDate])); ?></p>

                        <?php if ($dispatchFlash !== ''): ?>
                            <div class="surface-card">
                                <p class="u-m-0"><?php echo htmlspecialchars($dispatchFlash); ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if ($dispatchError !== ''): ?>
                            <div class="surface-card">
                                <p class="u-m-0"><?php echo htmlspecialchars($dispatchError); ?></p>
                            </div>
                        <?php endif; ?>

                        <section class="surface-card" aria-label="<?php echo htmlspecialchars($this->tr('operator.dispatch.form.title', 'Dispatch Entry')); ?>">
                            <h3 class="work-entry-embed-title"><?php echo htmlspecialchars($this->tr('operator.dispatch.form.title', 'Dispatch Entry')); ?></h3>
                            <div class="production-focus-actions u-mb-10">
                                <button class="btn ok" type="button" data-panel-toggle="dispatchCreatePanel" aria-expanded="false"><?php echo htmlspecialchars($this->tr('operator.dispatch.form.create', 'Add Dispatch Entry')); ?></button>
                            </div>
                            <div class="ui-block" id="dispatchCreatePanel" hidden>
                            <form method="post" action="/u/<?php echo urlencode((string)($data['username'] ?? '')); ?>/dispatch/create" class="production-focus-form">
                                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string)\App\Core\Auth::csrfToken()); ?>">

                                <label class="production-focus-field">
                                    <span><?php echo htmlspecialchars($this->tr('operator.dispatch.form.dispatch_date', 'Dispatch Date')); ?></span>
                                    <input class="daily-order-date-input" type="date" name="dispatch_date" value="<?php echo htmlspecialchars($dispatchDate); ?>" required>
                                </label>

                                <label class="production-focus-field production-focus-field-wide">
                                    <span><?php echo htmlspecialchars($this->tr('operator.dispatch.form.destination', 'Destination')); ?></span>
                                    <input class="header-search" type="text" name="destination" list="dispatchDestinationList" value="" required>
                                    <datalist id="dispatchDestinationList">
                                        <?php foreach ($dispatchDestinations as $destination): ?>
                                            <option value="<?php echo htmlspecialchars((string)$destination); ?>"></option>
                                        <?php endforeach; ?>
                                    </datalist>
                                </label>

                                <label class="production-focus-field production-focus-field-wide">
                                    <span><?php echo htmlspecialchars($this->tr('operator.dispatch.form.part', 'Part')); ?></span>
                                    <select class="daily-order-select" name="product_id" required>
                                        <option value="0"><?php echo htmlspecialchars($this->tr('operator.dispatch.form.select_part', 'Select Part')); ?></option>
                                        <?php foreach ($dispatchProducts as $product): ?>
                                            <option value="<?php echo (int)($product['id'] ?? 0); ?>"<?php if ($dispatchProductId > 0 && (int)($product['id'] ?? 0) === $dispatchProductId): ?> selected<?php endif; ?>><?php echo htmlspecialchars(trim((string)($product['parts_name'] ?? '')) . ' (' . trim((string)($product['parts_number'] ?? '-')) . ')'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>

                                <label class="production-focus-field">
                                    <span><?php echo htmlspecialchars($this->tr('operator.dispatch.form.qty', 'Dispatch Qty')); ?></span>
                                    <input class="daily-order-date-input" type="number" step="0.01" min="0.01" name="dispatchable_qty" required>
                                </label>

                                <label class="production-focus-field">
                                    <span><?php echo htmlspecialchars($this->tr('operator.dispatch.form.eta_load_at', 'Estimated Load Time')); ?></span>
                                    <input class="daily-order-date-input" type="datetime-local" name="eta_load_at" value="">
                                </label>

                                <label class="production-focus-field">
                                    <span><?php echo htmlspecialchars($this->tr('operator.dispatch.form.driver', 'Driver')); ?></span>
                                    <input class="header-search" type="text" name="driver_name" value="<?php echo htmlspecialchars($dispatchDefaultDriver); ?>">
                                </label>

                                <label class="production-focus-field">
                                    <span><?php echo htmlspecialchars($this->tr('operator.dispatch.form.truck', 'Truck')); ?></span>
                                    <input class="header-search" type="text" name="truck_no" value="<?php echo htmlspecialchars($dispatchDefaultTruck); ?>">
                                </label>

                                <label class="production-focus-field">
                                    <span><?php echo htmlspecialchars($this->tr('operator.dispatch.form.load_reference', 'Load Reference')); ?></span>
                                    <input class="header-search" type="text" name="load_reference" value="">
                                </label>

                                <label class="production-focus-field production-focus-field-wide">
                                    <span><?php echo htmlspecialchars($this->tr('notes', 'Notes')); ?></span>
                                    <input class="header-search" type="text" name="remarks" value="">
                                </label>

                                <div class="production-focus-actions">
                                    <button class="btn ok" type="submit"><?php echo htmlspecialchars($this->tr('operator.dispatch.form.save', 'Save Dispatch')); ?></button>
                                </div>
                            </form>
                            </div>
                        </section>

                        <section class="surface-card" aria-label="<?php echo htmlspecialchars($this->tr('operator.dispatch.table.title', 'Dispatch Queue')); ?>">
                            <h3 class="work-entry-embed-title"><?php echo htmlspecialchars($this->tr('operator.dispatch.table.title', 'Dispatch Queue')); ?></h3>
                            <div class="recent-focus-table-wrap operator-table-scroll">
                                <table class="recent-focus-table">
                                    <thead>
                                        <tr>
                                            <th><?php echo htmlspecialchars($this->tr('operator.dispatch.table.date', 'Dispatch Date')); ?></th>
                                            <th><?php echo htmlspecialchars($this->tr('operator.dispatch.table.destination', 'Destination')); ?></th>
                                            <th><?php echo htmlspecialchars($this->tr('operator.dispatch.table.part', 'Part')); ?></th>
                                            <th><?php echo htmlspecialchars($this->tr('operator.dispatch.table.qty', 'Qty')); ?></th>
                                            <th><?php echo htmlspecialchars($this->tr('operator.dispatch.table.driver', 'Driver')); ?></th>
                                            <th><?php echo htmlspecialchars($this->tr('operator.dispatch.table.truck', 'Truck')); ?></th>
                                            <th><?php echo htmlspecialchars($this->tr('operator.dispatch.table.status', 'Status')); ?></th>
                                            <th><?php echo htmlspecialchars($this->tr('operator.dispatch.table.load_reference', 'Load Ref')); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($dispatchRows === []): ?>
                                            <tr>
                                                <td colspan="8"><?php echo htmlspecialchars($this->tr('operator.dispatch.table.empty', 'No dispatch entries for selected filter.')); ?></td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($dispatchRows as $row): ?>
                                                <tr
                                                    class="operator-clickable-row"
                                                    data-href="/u/<?php echo urlencode((string)($data['username'] ?? '')); ?>/dispatch/detail?bundle_id=<?php echo (int)($row['bundle_id'] ?? 0); ?>"
                                                    tabindex="0"
                                                >
                                                    <td><?php echo htmlspecialchars((string)($row['dispatch_date'] ?? '')); ?></td>
                                                    <td><?php echo htmlspecialchars((string)($row['destination'] ?? '-')); ?></td>
                                                    <td><?php echo htmlspecialchars(trim((string)($row['part_name'] ?? '-')) . ' (' . trim((string)($row['part_number'] ?? '-')) . ')'); ?></td>
                                                    <td><?php echo htmlspecialchars((string)($row['dispatchable_qty'] ?? '0')); ?></td>
                                                    <td><?php echo htmlspecialchars((string)($row['driver_name'] ?? '-')); ?></td>
                                                    <td><?php echo htmlspecialchars((string)($row['truck_no'] ?? '-')); ?></td>
                                                    <td><?php echo htmlspecialchars(trim((string)($row['bundle_status'] ?? 'draft') . ' | ' . (string)($row['dispatch_statuses'] ?? '-') . ' / ' . (string)($row['completion_statuses'] ?? '-'))); ?></td>
                                                    <td><?php echo htmlspecialchars((string)($row['load_reference'] ?? '-')); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </section>

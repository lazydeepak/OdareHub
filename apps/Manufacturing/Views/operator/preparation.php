<?php
// Operator Layer View: preparation
// All composer scope variables available via include.
?>
<?php $preparation = (array)($data['preparation_focus'] ?? []); ?>
                    <?php $readyRows = (array)($preparation['ready_rows'] ?? []); ?>
                    <?php $readyIndex = (array)($preparation['ready_index'] ?? []); ?>
                    <?php $bundleRows = (array)($preparation['bundle_rows'] ?? []); ?>
                    <?php $preparationDate = (string)($preparation['selected_date'] ?? date('Y-m-d')); ?>
                    <?php $preparationProductId = (int)($preparation['selected_product_id'] ?? 0); ?>
                    <?php $preparationDestinations = (array)($preparation['destinations'] ?? []); ?>
                    <?php $preparationDestinationDefaults = (array)($preparation['destination_defaults'] ?? []); ?>
                    <?php $preparationDefaultDriver = trim((string)($preparation['default_driver_name'] ?? '')); ?>
                    <?php $preparationDefaultTruck = trim((string)($preparation['default_truck_no'] ?? '')); ?>
                    <?php $preparationFlash = trim((string)($preparation['flash'] ?? '')); ?>
                    <?php $preparationError = trim((string)($preparation['error'] ?? '')); ?>

                    <section class="recent-focus" aria-label="<?php echo htmlspecialchars((string)($preparation['title'] ?? $this->tr('operator.preparation.title', 'Preparation'))); ?>">
                        <h2 class="dashboard-top-title"><?php echo htmlspecialchars((string)($preparation['title'] ?? $this->tr('operator.preparation.title', 'Preparation'))); ?></h2>
                        <p class="recent-focus-subtitle"><?php echo htmlspecialchars((string)($preparation['subtitle'] ?? $this->tr('operator.preparation.subtitle', 'Bundle dispatch-ready parts into pallet-destination loads.'))); ?></p>

                        <?php if ($preparationFlash !== ''): ?>
                            <div class="surface-card">
                                <p class="u-m-0"><?php echo htmlspecialchars($preparationFlash); ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if ($preparationError !== ''): ?>
                            <div class="surface-card">
                                <p class="u-m-0"><?php echo htmlspecialchars($preparationError); ?></p>
                            </div>
                        <?php endif; ?>

                        <section class="surface-card" aria-label="<?php echo htmlspecialchars($this->tr('operator.preparation.form.title', 'Prepare Order')); ?>">
                            <h3 class="work-entry-embed-title"><?php echo htmlspecialchars($this->tr('operator.preparation.form.title', 'Prepare Order')); ?></h3>
                            <div class="production-focus-actions u-mb-10">
                                <button
                                    class="btn ok"
                                    type="button"
                                    data-panel-toggle="preparationCreatePanel"
                                    aria-expanded="false"
                                >
                                    <?php echo htmlspecialchars($this->tr('operator.preparation.form.create', 'Prepare Order')); ?>
                                </button>
                            </div>
                            <div class="ui-block" id="preparationCreatePanel" hidden>
                                <form method="post" action="/u/<?php echo urlencode((string)($data['username'] ?? '')); ?>/preparation/bundle/create" class="production-focus-form" id="preparationReadyForm">
                                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string)\App\Core\Auth::csrfToken()); ?>">
                                    <input type="hidden" name="product_id" id="preparationFallbackProductId" value="<?php echo (int)$preparationProductId; ?>">
                                    <input type="hidden" name="bundle_qty" id="preparationFallbackQty" value="0">
                                    <div class="ui-block" id="preparationLineInputHolder"></div>

                                    <label class="production-focus-field">
                                        <span><?php echo htmlspecialchars($this->tr('operator.preparation.form.dispatch_date', 'Dispatch Date')); ?></span>
                                        <input class="daily-order-date-input" type="date" name="dispatch_date" id="preparationDispatchDate" value="<?php echo htmlspecialchars($preparationDate); ?>" required>
                                    </label>

                                    <label class="production-focus-field production-focus-field-wide">
                                        <span><?php echo htmlspecialchars($this->tr('operator.preparation.form.destination', 'Destination')); ?></span>
                                        <input class="header-search" type="text" name="destination" id="preparationDestination" list="prepDestinationList" value="" required>
                                        <datalist id="prepDestinationList">
                                            <?php foreach ($preparationDestinations as $destination): ?>
                                                <option value="<?php echo htmlspecialchars((string)$destination); ?>"></option>
                                            <?php endforeach; ?>
                                        </datalist>
                                    </label>

                                    <label class="production-focus-field">
                                        <span><?php echo htmlspecialchars($this->tr('operator.preparation.form.pallet_code', 'Pallet Code')); ?></span>
                                        <input class="header-search" type="text" name="pallet_code" value="" required>
                                    </label>

                                    <label class="production-focus-field">
                                        <span><?php echo htmlspecialchars($this->tr('operator.preparation.form.bundle_code', 'Bundle Code')); ?></span>
                                        <input class="header-search" type="text" name="bundle_code" value="">
                                    </label>

                                    <div class="production-focus-field production-focus-field-wide">
                                        <span><?php echo htmlspecialchars($this->tr('operator.preparation.form.parts_selection', 'Parts Selection')); ?></span>
                                        <p class="u-muted-compact u-m-0" id="preparationPartsHint"><?php echo htmlspecialchars($this->tr('operator.preparation.form.parts_selection_hint', 'Select dispatch date and destination to auto-load parts and order quantities.')); ?></p>
                                        <div id="preparationPartsRows" class="u-mt-6"></div>
                                    </div>

                                    <label class="production-focus-field">
                                        <span><?php echo htmlspecialchars($this->tr('operator.preparation.form.eta_load_at', 'Estimated Load Time')); ?></span>
                                        <input class="daily-order-date-input" type="datetime-local" name="eta_load_at" value="">
                                    </label>

                                    <label class="production-focus-field">
                                        <span><?php echo htmlspecialchars($this->tr('operator.preparation.form.driver', 'Driver')); ?></span>
                                        <input class="header-search" type="text" name="driver_name" id="preparationDriverName" value="<?php echo htmlspecialchars($preparationDefaultDriver); ?>">
                                    </label>

                                    <label class="production-focus-field">
                                        <span><?php echo htmlspecialchars($this->tr('operator.preparation.form.truck', 'Truck')); ?></span>
                                        <input class="header-search" type="text" name="truck_no" id="preparationTruckNo" value="<?php echo htmlspecialchars($preparationDefaultTruck); ?>">
                                    </label>

                                    <label class="production-focus-field production-focus-field-wide">
                                        <span><?php echo htmlspecialchars($this->tr('notes', 'Notes')); ?></span>
                                        <input class="header-search" type="text" name="notes" value="">
                                    </label>

                                    <div class="production-focus-actions">
                                        <button class="btn ok" type="submit" id="preparationReadySaveButton"><?php echo htmlspecialchars($this->tr('operator.preparation.form.save', 'Ready / Save')); ?></button>
                                    </div>
                                </form>
                                <script type="application/json" id="preparationFlowDataset"><?php echo (string)json_encode([
                                    'ready_index' => $readyIndex,
                                    'destination_defaults' => $preparationDestinationDefaults,
                                    'default_driver_name' => $preparationDefaultDriver,
                                    'default_truck_no' => $preparationDefaultTruck,
                                    'labels' => [
                                        'no_parts' => $this->tr('operator.preparation.form.no_parts', 'No ready parts found for selected date and destination.'),
                                        'select_prompt' => $this->tr('operator.preparation.form.select_prompt', 'Select at least one part to save preparation.'),
                                        'order_qty' => $this->tr('operator.preparation.form.order_qty', 'Order Qty'),
                                        'bundle_qty' => $this->tr('operator.preparation.form.bundle_qty', 'Bundle Qty'),
                                        'status_ok' => $this->tr('operator.preparation.form.qty_ok', 'Qty OK'),
                                        'status_adjust' => $this->tr('operator.preparation.form.qty_adjust', 'Adjusted'),
                                        'invalid_qty' => $this->tr('operator.preparation.form.qty_invalid', 'Qty must be greater than zero.'),
                                    ],
                                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
                            </div>
                        </section>

                        <section class="surface-card" aria-label="<?php echo htmlspecialchars($this->tr('operator.preparation.ready_table.title', 'Ready to Dispatch')); ?>">
                            <h3 class="work-entry-embed-title"><?php echo htmlspecialchars($this->tr('operator.preparation.ready_table.title', 'Ready to Dispatch')); ?></h3>
                            <div class="recent-focus-table-wrap">
                                <table class="recent-focus-table">
                                    <thead>
                                        <tr>
                                            <th><?php echo htmlspecialchars($this->tr('operator.preparation.table.destination', 'Destination')); ?></th>
                                            <th><?php echo htmlspecialchars($this->tr('operator.preparation.table.dispatch_date', 'Dispatch Date')); ?></th>
                                            <th><?php echo htmlspecialchars($this->tr('operator.preparation.table.part', 'Part')); ?></th>
                                            <th><?php echo htmlspecialchars($this->tr('operator.preparation.table.qty', 'Ready Qty')); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($readyRows === []): ?>
                                            <tr>
                                                <td colspan="4"><?php echo htmlspecialchars($this->tr('operator.preparation.ready_table.empty', 'No dispatch-ready orders found in current window.')); ?></td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($readyRows as $row): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars((string)($row['destination'] ?? '-')); ?></td>
                                                    <td><?php echo htmlspecialchars((string)($row['dispatch_date'] ?? '')); ?></td>
                                                    <td><?php echo htmlspecialchars(trim((string)($row['part_name'] ?? '-')) . ' (' . trim((string)($row['part_number'] ?? '-')) . ')'); ?></td>
                                                    <td><?php echo htmlspecialchars((string)($row['ready_qty'] ?? '0')); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section class="surface-card" aria-label="<?php echo htmlspecialchars($this->tr('operator.preparation.bundle_table.title', 'Dispatch Ready Bundles')); ?>">
                            <h3 class="work-entry-embed-title"><?php echo htmlspecialchars($this->tr('operator.preparation.bundle_table.title', 'Dispatch Ready Bundles')); ?></h3>
                            <div class="recent-focus-table-wrap">
                                <table class="recent-focus-table">
                                    <thead>
                                        <tr>
                                            <th><?php echo htmlspecialchars($this->tr('operator.preparation.bundle.bundle_code', 'Bundle')); ?></th>
                                            <th><?php echo htmlspecialchars($this->tr('operator.preparation.bundle.pallet_code', 'Pallet')); ?></th>
                                            <th><?php echo htmlspecialchars($this->tr('operator.preparation.table.destination', 'Destination')); ?></th>
                                            <th><?php echo htmlspecialchars($this->tr('operator.preparation.bundle.parts', 'Parts')); ?></th>
                                            <th><?php echo htmlspecialchars($this->tr('operator.preparation.bundle.total_qty', 'Total Qty')); ?></th>
                                            <th><?php echo htmlspecialchars($this->tr('operator.preparation.form.eta_load_at', 'Estimated Load Time')); ?></th>
                                            <th><?php echo htmlspecialchars($this->tr('operator.preparation.form.driver', 'Driver')); ?></th>
                                            <th><?php echo htmlspecialchars($this->tr('operator.preparation.form.truck', 'Truck')); ?></th>
                                            <th><?php echo htmlspecialchars($this->tr('common.status', 'Status')); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($bundleRows === []): ?>
                                            <tr>
                                                <td colspan="9"><?php echo htmlspecialchars($this->tr('operator.preparation.bundle_table.empty', 'No preparation bundles found in current window.')); ?></td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($bundleRows as $row): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars((string)($row['bundle_code'] ?? '-')); ?></td>
                                                    <td><?php echo htmlspecialchars((string)($row['pallet_code'] ?? '-')); ?></td>
                                                    <td><?php echo htmlspecialchars((string)($row['destination'] ?? '-')); ?></td>
                                                    <td><?php echo htmlspecialchars((string)($row['parts_summary'] ?? '-')); ?></td>
                                                    <td><?php echo htmlspecialchars((string)($row['total_qty'] ?? '0')); ?></td>
                                                    <td><?php echo htmlspecialchars((string)($row['eta_load_at'] ?? '-')); ?></td>
                                                    <td><?php echo htmlspecialchars((string)($row['driver_name'] ?? '-')); ?></td>
                                                    <td><?php echo htmlspecialchars((string)($row['truck_no'] ?? '-')); ?></td>
                                                    <td><?php echo htmlspecialchars((string)($row['status'] ?? 'draft')); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </section>

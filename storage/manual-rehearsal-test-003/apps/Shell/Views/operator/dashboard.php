<?php
// Operator Layer View: dashboard
// All composer scope variables available via include.
?>
                    <section class="dashboard-top" aria-label="<?php echo htmlspecialchars((string)($data['dashboard_title'] ?? 'Dashboard')); ?>">
                        <h2 class="dashboard-top-title"><?php echo htmlspecialchars((string)($data['dashboard_title'] ?? 'Dashboard')); ?></h2>
                        <?php if (!empty($data['common_home_tiles']) && is_array($data['common_home_tiles'])): ?>
                        <div class="dashboard-top-grid">
                            <?php foreach ((array)($data['common_home_tiles'] ?? []) as $tile): ?>
                                <article class="dashboard-tile<?php echo !empty($tile['primary']) ? ' dashboard-tile-primary' : ''; ?>" data-dashboard-key="<?php echo htmlspecialchars((string)($tile['key'] ?? '')); ?>">
                                    <div class="dashboard-tile-head">
                                        <div class="dashboard-tile-label"><?php echo htmlspecialchars((string)($tile['label'] ?? '')); ?></div>
                                        <div class="dashboard-tile-value"><?php echo htmlspecialchars((string)($tile['value'] ?? '--')); ?></div>
                                    </div>
                                    <?php if (!empty($tile['subtitle'])): ?>
                                        <div class="dashboard-tile-subtitle"><?php echo htmlspecialchars((string)$tile['subtitle']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($tile['items']) && is_array($tile['items'])): ?>
                                        <ul class="dashboard-tile-list">
                                            <?php foreach ($tile['items'] as $itemName): ?>
                                                <li title="<?php echo htmlspecialchars((string)$itemName); ?>">
                                                    <span class="dashboard-item-text"><?php echo htmlspecialchars((string)$itemName); ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($data['dashboard_tiles']) && is_array($data['dashboard_tiles'])): ?>
                        <div class="dashboard-top-grid u-style-8a359a76eb">
                            <?php foreach ((array)($data['dashboard_tiles'] ?? []) as $tile): ?>
                                <article class="dashboard-tile<?php echo !empty($tile['primary']) ? ' dashboard-tile-primary' : ''; ?>" data-dashboard-key="<?php echo htmlspecialchars((string)($tile['key'] ?? '')); ?>">
                                    <div class="dashboard-tile-head">
                                        <div class="dashboard-tile-label"><?php echo htmlspecialchars((string)($tile['label'] ?? '')); ?></div>
                                        <div class="dashboard-tile-value"><?php echo htmlspecialchars((string)($tile['value'] ?? '--')); ?></div>
                                    </div>
                                    <?php if (!empty($tile['subtitle'])): ?>
                                        <div class="dashboard-tile-subtitle"><?php echo htmlspecialchars((string)$tile['subtitle']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($tile['items']) && is_array($tile['items'])): ?>
                                        <ul class="dashboard-tile-list">
                                            <?php foreach ($tile['items'] as $itemName): ?>
                                                <?php
                                                    $rawItem = trim((string)$itemName);
                                                    $itemLabel = $rawItem;
                                                    $itemCount = '';
                                                    if (preg_match('/^(.*)\(([^()]*)\)\s*$/', $rawItem, $matches)) {
                                                        $itemLabel = trim((string)($matches[1] ?? ''));
                                                        $itemCount = trim((string)($matches[2] ?? ''));
                                                    }
                                                ?>
                                                <li title="<?php echo htmlspecialchars($rawItem); ?>">
                                                    <span class="dashboard-item-text"><?php echo htmlspecialchars($itemLabel !== '' ? $itemLabel : $rawItem); ?></span>
                                                    <?php if ($itemCount !== ''): ?>
                                                        <span class="dashboard-item-count"><?php echo htmlspecialchars($itemCount); ?></span>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                    <?php if (!empty($tile['trends']) && is_array($tile['trends'])): ?>
                                        <div class="dashboard-trend-grid">
                                            <?php foreach ($tile['trends'] as $trend): ?>
                                                <div class="dashboard-trend-chip dashboard-trend-<?php echo htmlspecialchars((string)($trend['tone'] ?? 'flat')); ?>">
                                                    <span class="dashboard-trend-period"><?php echo htmlspecialchars((string)($trend['period'] ?? '')); ?></span>
                                                    <span class="dashboard-trend-delta"><?php echo htmlspecialchars((string)($trend['delta_label'] ?? '0')); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($data['manufacturing_available'])): ?>
                        <?php $dailyOrderChart = (array)($data['daily_order_chart'] ?? []); ?>
                        <?php
                            $dailyOrderDates = array_values((array)($dailyOrderChart['dates'] ?? []));
                            $dailyOrderMinDate = $dailyOrderDates !== [] ? (string)$dailyOrderDates[0] : '';
                            $dailyOrderMaxDate = $dailyOrderDates !== [] ? (string)$dailyOrderDates[count($dailyOrderDates) - 1] : '';
                        ?>
                        <section class="daily-order-card" aria-label="<?php echo htmlspecialchars((string)($dailyOrderChart['title'] ?? $this->tr('nav.daily_orders', 'Daily Orders'))); ?>">
                            <div class="daily-order-head">
                                <h3 class="daily-order-title"><?php echo htmlspecialchars((string)($dailyOrderChart['title'] ?? $this->tr('nav.daily_orders', 'Daily Orders'))); ?></h3>
                                <div class="daily-order-controls">
                                    <label class="daily-order-filter" for="dailyOrderModelFilter">
                                        <span><?php echo htmlspecialchars((string)($dailyOrderChart['model_label'] ?? $this->tr('common.model', 'Model'))); ?></span>
                                        <select id="dailyOrderModelFilter" class="daily-order-select">
                                            <option value="all"><?php echo htmlspecialchars((string)($dailyOrderChart['all_label'] ?? $this->tr('common.all', 'All'))); ?></option>
                                            <?php foreach ((array)($dailyOrderChart['models'] ?? []) as $chartModel): ?>
                                                <option value="<?php echo htmlspecialchars((string)($chartModel['key'] ?? '')); ?>"><?php echo htmlspecialchars((string)($chartModel['label'] ?? '')); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label class="daily-order-filter" for="dailyOrderProductFilter">
                                        <span><?php echo htmlspecialchars((string)($dailyOrderChart['filter_label'] ?? $this->tr('operator.dashboard.parts', 'Parts'))); ?></span>
                                        <select id="dailyOrderProductFilter" class="daily-order-select">
                                            <option value="all"><?php echo htmlspecialchars((string)($dailyOrderChart['all_label'] ?? $this->tr('common.all', 'All'))); ?></option>
                                            <?php foreach ((array)($dailyOrderChart['products'] ?? []) as $chartProduct): ?>
                                                <option value="<?php echo htmlspecialchars((string)($chartProduct['key'] ?? '')); ?>"><?php echo htmlspecialchars((string)($chartProduct['label'] ?? '')); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <div class="daily-order-date-range">
                                        <label class="daily-order-date-input-wrap" for="dailyOrderFromDate">
                                            <span><?php echo htmlspecialchars((string)($dailyOrderChart['date_from_label'] ?? $this->tr('common.date_range_from', 'From'))); ?></span>
                                            <input id="dailyOrderFromDate" class="daily-order-date-input" type="date" value="<?php echo htmlspecialchars($dailyOrderMinDate); ?>" min="<?php echo htmlspecialchars($dailyOrderMinDate); ?>" max="<?php echo htmlspecialchars($dailyOrderMaxDate); ?>">
                                        </label>
                                        <label class="daily-order-date-input-wrap" for="dailyOrderToDate">
                                            <span><?php echo htmlspecialchars((string)($dailyOrderChart['date_to_label'] ?? $this->tr('common.date_range_to', 'To'))); ?></span>
                                            <input id="dailyOrderToDate" class="daily-order-date-input" type="date" value="<?php echo htmlspecialchars($dailyOrderMaxDate); ?>" min="<?php echo htmlspecialchars($dailyOrderMinDate); ?>" max="<?php echo htmlspecialchars($dailyOrderMaxDate); ?>">
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="daily-order-axis-meta">
                                <span class="daily-order-axis-chip">X: <?php echo htmlspecialchars((string)($dailyOrderChart['date_label'] ?? $this->tr('common.date', 'Date'))); ?></span>
                                <span class="daily-order-axis-chip">Y: <?php echo htmlspecialchars((string)($dailyOrderChart['qty_label'] ?? $this->tr('common.qty', 'Qty'))); ?></span>
                            </div>
                            <div class="daily-order-chart-shell">
                                <div class="daily-order-y-axis" id="dailyOrderYAxis"></div>
                                <div class="daily-order-plot">
                                    <div class="daily-order-bars" id="dailyOrderBars"></div>
                                </div>
                            </div>
                            <div class="daily-order-legend" id="dailyOrderLegend"></div>
                        </section>
                        <?php if (!empty($data['overview_cards']) && is_array($data['overview_cards'])): ?>
                            <details class="overview-details-disclosure">
                                <summary class="overview-details-summary"><?php echo htmlspecialchars($this->tr('operator.overview.more_details', 'More Details')); ?></summary>
                                <div class="overview-detail-grid" aria-label="<?php echo htmlspecialchars($this->tr('operator.overview.detail.aria', 'Overview Details')); ?>">
                                    <?php foreach ((array)$data['overview_cards'] as $overviewCard): ?>
                                        <article class="overview-card" data-tone="<?php echo htmlspecialchars((string)($overviewCard['tone'] ?? 'neutral')); ?>">
                                            <h3 class="overview-card-title"><?php echo htmlspecialchars((string)($overviewCard['title'] ?? '')); ?></h3>
                                            <?php if ((string)($overviewCard['kind'] ?? '') === 'bar_chart'): ?>
                                                <div class="overview-bar-chart">
                                                    <?php foreach ((array)($overviewCard['bars'] ?? []) as $bar): ?>
                                                        <?php
                                                            $barPercent = (float)($bar['percent'] ?? 0.0);
                                                            if ($barPercent < 0) {
                                                                $barPercent = 0.0;
                                                            }
                                                            if ($barPercent > 100) {
                                                                $barPercent = 100.0;
                                                            }
                                                            $barWidth = number_format($barPercent, 2, '.', '');
                                                            $metricLabel = trim((string)($overviewCard['metric_label'] ?? ''));
                                                            $valueText = trim((string)($bar['value_label'] ?? '0'));
                                                            if ($metricLabel !== '') {
                                                                $valueText .= ' ' . $metricLabel;
                                                            }
                                                        ?>
                                                        <div class="overview-bar-row">
                                                            <span class="overview-bar-period"><?php echo htmlspecialchars((string)($bar['period'] ?? '')); ?></span>
                                                            <div class="overview-bar-track" role="img" aria-label="<?php echo htmlspecialchars((string)($bar['period'] ?? '') . ' ' . $valueText); ?>">
                                                                <div class="overview-bar-fill" data-bar-width="<?php echo htmlspecialchars($barWidth); ?>"></div>
                                                            </div>
                                                            <span class="overview-bar-value"><?php echo htmlspecialchars($valueText); ?></span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <ul class="overview-card-rows">
                                                    <?php foreach ((array)($overviewCard['rows'] ?? []) as $row): ?>
                                                        <li class="overview-card-row">
                                                            <span class="overview-card-label"><?php echo htmlspecialchars((string)($row['label'] ?? '')); ?></span>
                                                            <span class="overview-card-value"><?php echo htmlspecialchars((string)($row['value'] ?? '')); ?></span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            </details>
                        <?php endif; ?>
                        <?php endif; ?>
                    </section>

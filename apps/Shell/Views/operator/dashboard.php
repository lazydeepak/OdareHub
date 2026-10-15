<?php
// Operator Layer View: dashboard
// All composer scope variables available via include.
?>
                    <section class="dashboard-top" aria-label="<?php echo htmlspecialchars((string)($data['dashboard_title'] ?? 'Dashboard')); ?>">
                        <h2 class="dashboard-top-title"><?php echo htmlspecialchars((string)($data['dashboard_title'] ?? 'Dashboard')); ?></h2>
                        <?php
                        $roleHomeEnabled = !empty($data['operator_role_home_enabled']);
                        $roleHomeMode = strtolower(trim((string)($data['operator_role_home_mode'] ?? '')));
                        $roleHomeTitle = trim((string)($data['operator_role_home_title'] ?? ''));
                        $roleRegions = is_array($data['operator_role_regions'] ?? null) ? $data['operator_role_regions'] : [];
                        $homeRegionLabels = [
                            'monitoring_sections' => ['operator.home.monitoring', 'Monitoring'],
                            'summary_cards' => ['operator.home.summary', 'Summary'],
                            'quick_links' => ['operator.home.supporting', 'Supporting information'],
                            'header_actions' => ['operator.home.supporting', 'Supporting information'],
                        ];
                        ?>
                        <?php if ($roleHomeEnabled && $roleRegions !== []): ?>
                            <section class="operator-role-home operator-role-home--<?php echo htmlspecialchars($roleHomeMode); ?>" aria-labelledby="operator-role-home-title">
                                <h3 id="operator-role-home-title"><?php echo htmlspecialchars($roleHomeTitle); ?></h3>
                                <?php foreach ($roleRegions as $region => $items): ?>
                                    <?php $regionLabel = $homeRegionLabels[$region] ?? ['operator.home.supporting', 'Supporting information']; ?>
                                    <section class="operator-role-region operator-role-region--<?php echo htmlspecialchars((string)$region); ?>" aria-label="<?php echo htmlspecialchars($this->tr($regionLabel[0], $regionLabel[1])); ?>">
                                        <h4><?php echo htmlspecialchars($this->tr($regionLabel[0], $regionLabel[1])); ?></h4>
                                        <div class="operator-role-widgets">
                                            <?php foreach ((array)$items as $widget): ?>
                                                <?php if (!is_array($widget)) { continue; } ?>
                                                <?php
                                                $widgetTitle = trim((string)($widget['title'] ?? $widget['label'] ?? ''));
                                                $widgetUrl = trim((string)($widget['url'] ?? ''));
                                                ?>
                                                <article class="operator-role-widget" data-widget-key="<?php echo htmlspecialchars((string)($widget['widget_key'] ?? '')); ?>">
                                                    <div class="operator-role-widget__head">
                                                        <?php if ($widgetUrl !== ''): ?><a href="<?php echo htmlspecialchars($widgetUrl); ?>"><?php echo htmlspecialchars($widgetTitle); ?></a><?php else: ?><strong><?php echo htmlspecialchars($widgetTitle); ?></strong><?php endif; ?>
                                                        <?php if (array_key_exists('value', $widget)): ?><span class="operator-role-widget__value"><?php echo htmlspecialchars((string)$widget['value']); ?></span><?php endif; ?>
                                                    </div>
                                                    <?php if (trim((string)($widget['description'] ?? $widget['meta'] ?? '')) !== ''): ?><p class="operator-role-widget__meta"><?php echo htmlspecialchars((string)($widget['description'] ?? $widget['meta'])); ?></p><?php endif; ?>
                                                    <?php if (is_array($widget['rows'] ?? null) && $widget['rows'] !== []): ?>
                                                        <ul class="operator-role-widget__rows">
                                                            <?php foreach ($widget['rows'] as $row): ?>
                                                                <?php if (is_array($row)): ?><li><?php echo htmlspecialchars(implode(' · ', array_map('strval', (array)($row['cells'] ?? [$row['label'] ?? '', $row['value'] ?? ''])))); ?></li><?php endif; ?>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php endif; ?>
                                                </article>
                                            <?php endforeach; ?>
                                        </div>
                                    </section>
                                <?php endforeach; ?>
                            </section>
                        <?php endif; ?>
                        <?php $showWorkerWork = !$roleHomeEnabled && !in_array($roleHomeMode, ['leader', 'read_only'], true); ?>
                        <?php $workerWork = is_array($data['worker_work'] ?? null) ? $data['worker_work'] : []; ?>
                        <?php if ($showWorkerWork): ?>
                        <section class="operator-worker-work" aria-labelledby="operator-worker-work-title">
                            <div class="operator-worker-work__heading">
                                <h3 id="operator-worker-work-title"><?php echo htmlspecialchars($this->tr('operator.worker_work.title', 'Your Work')); ?></h3>
                                <span class="operator-worker-work__source"><?php echo htmlspecialchars($this->tr('operator.worker_work.source', 'Assigned work')); ?></span>
                            </div>
                            <?php if (($workerWork['available'] ?? true) === false): ?>
                                <p class="operator-worker-work__empty"><?php echo htmlspecialchars($this->tr('operator.worker_work.unavailable', 'Assigned work is temporarily unavailable.')); ?></p>
                            <?php else: ?>
                                <?php foreach ([
                                    ['key' => 'current', 'title' => 'operator.worker_work.current', 'fallback' => 'Current work'],
                                    ['key' => 'upcoming', 'title' => 'operator.worker_work.upcoming', 'fallback' => 'Upcoming work'],
                                    ['key' => 'attention', 'title' => 'operator.worker_work.attention', 'fallback' => 'Needs attention'],
                                ] as $lane): ?>
                                    <?php $laneItems = is_array($workerWork[$lane['key']] ?? null) ? $workerWork[$lane['key']] : []; ?>
                                    <section class="operator-worker-lane operator-worker-lane--<?php echo htmlspecialchars($lane['key']); ?>" aria-labelledby="operator-worker-<?php echo htmlspecialchars($lane['key']); ?>-title">
                                        <h4 id="operator-worker-<?php echo htmlspecialchars($lane['key']); ?>-title"><?php echo htmlspecialchars($this->tr($lane['title'], $lane['fallback'])); ?></h4>
                                        <?php if ($laneItems === []): ?>
                                            <p class="operator-worker-work__empty"><?php echo htmlspecialchars($this->tr('operator.worker_work.empty', 'No verified items in this lane.')); ?></p>
                                        <?php else: ?>
                                            <div class="operator-worker-items">
                                                <?php foreach ($laneItems as $item): ?>
                                                    <article class="operator-worker-item" data-work-ref="<?php echo htmlspecialchars((string)($item['item_ref'] ?? '')); ?>">
                                                        <div class="operator-worker-item__topline">
                                                            <h5><?php echo htmlspecialchars((string)($item['title'] ?? '')); ?></h5>
                                                            <span class="operator-worker-item__status"><?php echo htmlspecialchars((string)($item['status'] ?? '')); ?></span>
                                                        </div>
                                                        <dl class="operator-worker-item__meta">
                                                            <div><dt><?php echo htmlspecialchars($this->tr('operator.worker_work.id', 'ID')); ?></dt><dd><?php echo htmlspecialchars((string)($item['item_ref'] ?? '')); ?></dd></div>
                                                            <div><dt><?php echo htmlspecialchars($this->tr('operator.worker_work.priority', 'Priority')); ?></dt><dd><?php echo htmlspecialchars((string)($item['priority'] ?? '')); ?></dd></div>
                                                            <?php if (trim((string)($item['due_date'] ?? '')) !== ''): ?><div><dt><?php echo htmlspecialchars($this->tr('operator.worker_work.due', 'Due')); ?></dt><dd><?php echo htmlspecialchars((string)$item['due_date']); ?></dd></div><?php endif; ?>
                                                        </dl>
                                                        <?php if (trim((string)($item['destination'] ?? '')) !== ''): ?><a class="operator-worker-item__link" href="<?php echo htmlspecialchars((string)$item['destination']); ?>"><?php echo htmlspecialchars($this->tr('operator.worker_work.open', 'Open assigned work')); ?></a><?php endif; ?>
                                                    </article>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </section>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </section>
                        <?php endif; ?>
                        <?php $workProcessSummary = is_array($data['work_process_summary'] ?? null) ? $data['work_process_summary'] : []; ?>
                        <?php if ($workProcessSummary !== []): ?>
                            <section class="operator-role-home operator-work-process-summary" aria-labelledby="operator-work-process-summary-title">
                                <div class="operator-role-home__head">
                                    <h3 id="operator-work-process-summary-title"><?php echo htmlspecialchars($this->tr('operator.work_process.title', 'Work and process summary')); ?></h3>
                                    <span class="operator-role-widget__meta"><?php echo htmlspecialchars($this->tr('operator.work_process.source', 'Authorized Processing records')); ?></span>
                                </div>
                                <?php if (($workProcessSummary['available'] ?? false) !== true): ?>
                                    <p class="operator-worker-work__empty">
                                        <?php echo htmlspecialchars($this->tr('operator.work_process.unavailable', 'Work and process information is unavailable.')); ?>
                                    </p>
                                <?php else: ?>
                                    <div class="operator-role-widgets">
                                        <?php foreach ((array)($workProcessSummary['lanes'] ?? []) as $lane): ?>
                                            <?php if (!is_array($lane)) { continue; } ?>
                                            <article class="operator-role-widget" data-work-process-lane="<?php echo htmlspecialchars((string)($lane['key'] ?? '')); ?>">
                                                <div class="operator-role-widget__head">
                                                    <strong><?php echo htmlspecialchars((string)($lane['label'] ?? '')); ?></strong>
                                                    <span class="operator-role-widget__value"><?php echo htmlspecialchars((string)((int)($lane['count'] ?? 0))); ?></span>
                                                </div>
                                                <p class="operator-role-widget__meta">
                                                    <?php echo htmlspecialchars($this->tr('operator.work_process.records', 'Authorized records')); ?>
                                                    <?php if (trim((string)($lane['presentation_area'] ?? '')) !== ''): ?>
                                                        · <?php echo htmlspecialchars((string)$lane['presentation_area']); ?>
                                                    <?php endif; ?>
                                                </p>
                                                <?php if ((array)($lane['records'] ?? []) === []): ?>
                                                    <p class="operator-worker-work__empty"><?php echo htmlspecialchars($this->tr('operator.work_process.empty', 'No activity in this authorized lane.')); ?></p>
                                                <?php else: ?>
                                                    <ul class="operator-role-widget__rows">
                                                        <?php foreach ((array)($lane['records'] ?? []) as $record): ?>
                                                            <?php if (!is_array($record)) { continue; } ?>
                                                            <li>
                                                                <?php $recordPartName = trim((string)($record['part_name'] ?? '')); ?>
                                                                <?php echo htmlspecialchars($recordPartName !== '' ? $recordPartName : (string)($record['part_number'] ?? '')); ?>
                                                                <?php if (trim((string)($record['status'] ?? '')) !== ''): ?>
                                                                    · <?php echo htmlspecialchars((string)$record['status']); ?>
                                                                <?php endif; ?>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php endif; ?>
                                                <?php if (trim((string)($lane['destination'] ?? '')) !== ''): ?>
                                                    <a href="<?php echo htmlspecialchars((string)$lane['destination']); ?>"><?php echo htmlspecialchars($this->tr('operator.work_process.open', 'Open Processing')); ?></a>
                                                <?php endif; ?>
                                            </article>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </section>
                        <?php endif; ?>
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

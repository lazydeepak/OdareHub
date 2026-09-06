<?php
// Operator Layer View: recent
// All composer scope variables available via include.
?>
<?php $recentActivity = (array)($data['recent_activity'] ?? []); ?>
<?php $_recSubUser = htmlspecialchars(rawurlencode((string)($data['username'] ?? ''))); ?>
<nav class="operator-subnav" aria-label="<?php echo htmlspecialchars($this->tr('operator.mytasks.subnav.label', 'My Tasks sections')); ?>">
    <a class="operator-subnav-item operator-subnav-item--active" href="/u/<?php echo $_recSubUser; ?>/recent" aria-current="page"><?php echo htmlspecialchars($this->tr('operator.mytasks.subnav.recent', 'Recent Activity')); ?></a>
</nav>
</nav>
                    <section class="recent-focus" aria-label="<?php echo htmlspecialchars((string)($recentActivity['title'] ?? $this->tr('operator.recent.title', 'Recent User Activity'))); ?>">
                        <h2 class="dashboard-top-title"><?php echo htmlspecialchars((string)($recentActivity['title'] ?? $this->tr('operator.recent.title', 'Recent User Activity'))); ?></h2>
                        <p class="recent-focus-subtitle"><?php echo htmlspecialchars((string)($recentActivity['subtitle'] ?? '')); ?></p>

                        <div class="recent-focus-summary">
                            <div class="recent-focus-kpi">
                                <span class="recent-focus-kpi-label"><?php echo htmlspecialchars($this->tr('operator.recent.total_entries', 'Total Entries')); ?></span>
                                <span class="recent-focus-kpi-value"><?php echo (int)($recentActivity['total'] ?? 0); ?></span>
                            </div>
                            <div class="recent-focus-kpi">
                                <span class="recent-focus-kpi-label"><?php echo htmlspecialchars($this->tr('operator.recent.unique_events', 'Unique Events')); ?></span>
                                <span class="recent-focus-kpi-value"><?php echo (int)($recentActivity['unique_events'] ?? 0); ?></span>
                            </div>
                        </div>

                        <?php if (!empty($recentActivity['event_bars']) && is_array($recentActivity['event_bars'])): ?>
                            <div class="recent-focus-bars">
                                <?php foreach ((array)$recentActivity['event_bars'] as $bar): ?>
                                    <?php
                                        $barPercent = (float)($bar['percent'] ?? 0.0);
                                        if ($barPercent < 0) {
                                            $barPercent = 0.0;
                                        }
                                        if ($barPercent > 100) {
                                            $barPercent = 100.0;
                                        }
                                        $barWidth = number_format($barPercent, 2, '.', '');
                                    ?>
                                    <div class="recent-focus-bar-row">
                                        <span class="recent-focus-bar-label"><?php echo htmlspecialchars((string)($bar['label'] ?? '')); ?></span>
                                        <div class="recent-focus-bar-track" role="img" aria-label="<?php echo htmlspecialchars((string)($bar['label'] ?? '') . ' ' . (int)($bar['count'] ?? 0)); ?>">
                                            <div class="recent-focus-bar-fill" data-bar-width="<?php echo htmlspecialchars($barWidth); ?>"></div>
                                        </div>
                                        <span class="recent-focus-bar-value"><?php echo (int)($bar['count'] ?? 0); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($recentActivity['rows']) && is_array($recentActivity['rows'])): ?>
                            <div class="recent-focus-table-wrap">
                                <table class="recent-focus-table">
                                    <thead>
                                        <tr>
                                            <th><?php echo htmlspecialchars($this->tr('common.when', 'When')); ?></th>
                                            <th><?php echo htmlspecialchars($this->tr('common.type', 'Type')); ?></th>
                                            <th><?php echo htmlspecialchars($this->tr('common.actions', 'Actions')); ?></th>
                                            <th><?php echo htmlspecialchars($this->tr('common.module', 'Module')); ?></th>
                                            <th><?php echo htmlspecialchars($this->tr('common.notes', 'Notes')); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ((array)$recentActivity['rows'] as $logRow): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars((string)($logRow['when'] ?? '')); ?></td>
                                                <td><?php echo htmlspecialchars((string)($logRow['event_type'] ?? '')); ?></td>
                                                <td><?php echo htmlspecialchars((string)($logRow['action'] ?? '')); ?></td>
                                                <td><?php echo htmlspecialchars((string)($logRow['module'] ?? '')); ?></td>
                                                <td><?php echo htmlspecialchars((string)($logRow['note'] ?? '')); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="recent-focus-empty"><?php echo htmlspecialchars((string)($recentActivity['empty_label'] ?? $this->tr('operator.recent.empty', 'No activity log entries found.'))); ?></div>
                        <?php endif; ?>
                    </section>

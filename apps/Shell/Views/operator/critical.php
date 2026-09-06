<?php
// Operator Layer View: critical
// All composer scope variables available via include.
?>
<?php $_critSubUser = htmlspecialchars(rawurlencode((string)($data['username'] ?? ''))); ?>

<section class="critical-focus" aria-label="<?php echo htmlspecialchars((string)($data['critical_title'] ?? $this->tr('operator.critical.title', 'Critical Items'))); ?>">
                        <h2 class="dashboard-top-title"><?php echo htmlspecialchars((string)($data['critical_title'] ?? $this->tr('operator.critical.title', 'Critical Items'))); ?></h2>
                        <div class="critical-focus-grid">
                            <?php foreach ((array)($data['critical_cards'] ?? []) as $criticalCard): ?>
                                <article class="critical-focus-card" data-critical-key="<?php echo htmlspecialchars((string)($criticalCard['key'] ?? '')); ?>">
                                    <header class="critical-focus-head">
                                        <h3 class="critical-focus-title"><?php echo htmlspecialchars((string)($criticalCard['title'] ?? '')); ?></h3>
                                        <p class="critical-focus-subtitle"><?php echo htmlspecialchars((string)($criticalCard['subtitle'] ?? '')); ?></p>
                                    </header>
                                    <?php if (empty($criticalCard['rows']) || !is_array($criticalCard['rows'])): ?>
                                        <div class="critical-focus-empty"><?php echo htmlspecialchars((string)($criticalCard['empty_label'] ?? $this->tr('operator.critical.no_data', 'No critical records found.'))); ?></div>
                                    <?php else: ?>
                                        <div class="critical-focus-rows">
                                            <?php foreach ((array)$criticalCard['rows'] as $criticalRow): ?>
                                                <?php
                                                    $barPercent = (float)($criticalRow['bar_percent'] ?? 0.0);
                                                    if ($barPercent < 0) {
                                                        $barPercent = 0.0;
                                                    }
                                                    if ($barPercent > 100) {
                                                        $barPercent = 100.0;
                                                    }
                                                    $barWidth = number_format($barPercent, 2, '.', '');
                                                ?>
                                                <div class="critical-focus-row">
                                                    <div class="critical-focus-row-head">
                                                        <span class="critical-focus-row-name"><?php echo htmlspecialchars((string)($criticalRow['name'] ?? '')); ?></span>
                                                        <span class="critical-focus-row-value"><?php echo htmlspecialchars((string)($criticalRow['value_label'] ?? '')); ?></span>
                                                    </div>
                                                    <div class="critical-focus-bar-track" role="img" aria-label="<?php echo htmlspecialchars((string)($criticalRow['name'] ?? '') . ' ' . (string)($criticalRow['value_label'] ?? '')); ?>">
                                                        <div class="critical-focus-bar-fill" data-bar-width="<?php echo htmlspecialchars($barWidth); ?>"></div>
                                                    </div>
                                                    <div class="critical-focus-row-meta">
                                                        <span><?php echo htmlspecialchars((string)($criticalRow['detail_left'] ?? '')); ?></span>
                                                        <span><?php echo htmlspecialchars((string)($criticalRow['detail_right'] ?? '')); ?></span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>

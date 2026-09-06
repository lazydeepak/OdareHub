<?php
// Operator Layer View: notifications
// All composer scope variables available via include.
?>
<?php
    $ntf = (array)($data['notifications_focus'] ?? []);
    $ntfAlerts   = (array)($ntf['alerts']         ?? []);
    $ntfCritical = (int)  ($ntf['total_critical']  ?? 0);
    $ntfWarning  = (int)  ($ntf['total_warning']   ?? 0);
    $ntfInfo     = (int)  ($ntf['total_info']       ?? 0);
    $ntfEmpty    = (bool) ($ntf['empty']            ?? true);
    $ntfError    = (string)($ntf['error']           ?? '');
    $ntfSuppressed = (bool)($ntf['suppressed']      ?? false);
    $ntfUser     = htmlspecialchars((string)($data['username'] ?? ''));
    $ntfHistory  = (array)($ntf['history']          ?? []);
?>
<?php
    $ntfUsername  = rawurlencode((string)($data['username'] ?? ''));
    $ntfRedirect  = '/u/' . $ntfUsername . '/notifications';
    $ntfUnread    = (int)($ntf['unread'] ?? count($ntfAlerts));
?>
<section class="coverage-focus-section" aria-label="<?php echo htmlspecialchars($this->tr('operator.notifications.page_title', 'Notifications')); ?>">
    <div class="coverage-focus-header">
        <h2 class="surface-title"><?php echo htmlspecialchars($this->tr('operator.notifications.page_title', 'Notifications')); ?></h2>
        <?php if ($ntfUnread > 0): ?>
            <form method="post" action="<?php echo htmlspecialchars('/u/' . $ntfUsername . '/notifications/read-all'); ?>" class="operator-ml-auto">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string)\App\Core\Auth::csrfToken()); ?>">
                <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($ntfRedirect); ?>">
                <button type="submit" class="btn-sm"><?php echo htmlspecialchars($this->tr('operator.notifications.mark_all_read', 'Mark all read')); ?></button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($ntfError !== ''): ?>
        <p class="coverage-focus-error"><?php echo htmlspecialchars($ntfError); ?></p>
    <?php endif; ?>

    <?php if ($ntfSuppressed): ?>
        <p class="coverage-focus-empty"><?php echo htmlspecialchars($this->tr('operator.notifications.suppressed', 'Notifications are muted. Update your preferences to receive alerts.')); ?></p>
    <?php elseif ($ntfEmpty && $ntfError === ''): ?>
        <p class="coverage-focus-empty"><?php echo htmlspecialchars($this->tr('operator.notifications.empty', 'All clear! No critical alerts.')); ?></p>
    <?php else: ?>

    <!-- KPI strip -->
    <div class="coverage-kpi-strip">
        <div class="coverage-kpi-card<?php echo $ntfCritical > 0 ? ' coverage-kpi-card--danger' : ''; ?>">
            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.notifications.kpi.critical', 'Critical Alerts')); ?></span>
            <span class="coverage-kpi-value"><?php echo $ntfCritical; ?></span>
        </div>
        <div class="coverage-kpi-card<?php echo $ntfWarning > 0 ? ' coverage-kpi-card--warn' : ''; ?>">
            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.notifications.kpi.warning', 'Warnings')); ?></span>
            <span class="coverage-kpi-value"><?php echo $ntfWarning; ?></span>
        </div>
        <div class="coverage-kpi-card">
            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.notifications.kpi.info', 'Info')); ?></span>
            <span class="coverage-kpi-value"><?php echo $ntfInfo; ?></span>
        </div>
    </div>

    <?php
    // Group alerts by severity for sectioned display
    $alertsBySeverity = ['critical' => [], 'warning' => [], 'info' => []];
    foreach ($ntfAlerts as $alert) {
        $sev = (string)($alert['severity'] ?? 'info');
        if (!isset($alertsBySeverity[$sev])) $sev = 'info';
        $alertsBySeverity[$sev][] = $alert;
    }
    $severityConfig = [
        'critical' => ['label' => $this->tr('operator.notifications.critical_title', 'Critical Alerts'), 'cls' => 'coverage-orders-title--danger', 'rowCls' => 'coverage-orders-row--danger'],
        'warning'  => ['label' => $this->tr('operator.notifications.warning_title', 'Warnings'),        'cls' => 'coverage-orders-title--warn',   'rowCls' => 'coverage-orders-row--warn'],
        'info'     => ['label' => $this->tr('operator.notifications.info_title', 'Information'),        'cls' => '',                               'rowCls' => ''],
    ];
    ?>

    <?php foreach ($severityConfig as $sev => $cfg): ?>
    <?php if (!empty($alertsBySeverity[$sev])): ?>
    <div class="coverage-orders-section">
        <h3 class="coverage-orders-title <?php echo $cfg['cls']; ?>">
            <?php echo htmlspecialchars($cfg['label']); ?>
            <span class="coverage-orders-count"><?php echo count($alertsBySeverity[$sev]); ?></span>
        </h3>
        <div class="coverage-orders-table-wrap">
            <table class="coverage-orders-table">
                <thead>
                    <tr>
                        <th></th>
                        <th><?php echo htmlspecialchars($this->tr('operator.notifications.col.alert', 'Alert')); ?></th>
                        <th class="num"><?php echo htmlspecialchars($this->tr('operator.notifications.col.count', 'Count')); ?></th>
                        <th><?php echo htmlspecialchars($this->tr('operator.notifications.col.action', 'Action')); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($alertsBySeverity[$sev] as $alert): ?>
                    <?php
                        $alertId    = (int)($alert['id'] ?? 0);
                        $alertIcon  = htmlspecialchars((string)($alert['icon']  ?? ''));
                        $alertTitle = htmlspecialchars((string)($alert['title'] ?? '-'));
                        $alertCount = (int)($alert['count'] ?? 0);
                        $alertLink  = str_replace('{operator}', $ntfUser, (string)($alert['link'] ?? '#'));
                        $alertLink  = htmlspecialchars($alertLink);
                        $alertStatus = strtolower(trim((string)($alert['status'] ?? 'new')));
                        $isUnread   = ($alertStatus === 'new');
                    ?>
                    <tr class="coverage-orders-row <?php echo $cfg['rowCls']; ?>">
                        <td class="operator-icon-cell"><?php echo $alertIcon; ?></td>
                        <td><?php echo $alertTitle; ?></td>
                        <td class="num"><?php echo $alertCount; ?></td>
                        <td class="operator-action-cell">
                            <a href="<?php echo $alertLink; ?>" class="btn-sm"><?php echo htmlspecialchars($this->tr('operator.notifications.view_all', 'View')); ?></a>
                            <?php if ($alertId > 0 && $isUnread): ?>
                            <form method="post" action="<?php echo htmlspecialchars('/u/' . $ntfUsername . '/notifications/read'); ?>">
                                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string)\App\Core\Auth::csrfToken()); ?>">
                                <input type="hidden" name="id" value="<?php echo $alertId; ?>">
                                <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($ntfRedirect); ?>">
                                <button type="submit" class="btn-sm"><?php echo htmlspecialchars($this->tr('operator.notifications.mark_read', 'Mark read')); ?></button>
                            </form>
                            <?php endif; ?>
                            <?php if ($alertId > 0): ?>
                            <form method="post" action="<?php echo htmlspecialchars('/u/' . $ntfUsername . '/notifications/dismiss'); ?>">
                                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string)\App\Core\Auth::csrfToken()); ?>">
                                <input type="hidden" name="id" value="<?php echo $alertId; ?>">
                                <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($ntfRedirect); ?>">
                                <button type="submit" class="btn-sm"><?php echo htmlspecialchars($this->tr('operator.notifications.dismiss', 'Dismiss')); ?></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>

    <?php endif; /* !$ntfEmpty */ ?>

    <?php if (!empty($ntfHistory)): ?>
    <details class="coverage-orders-section ntf-history-details">
        <summary class="coverage-orders-title">
            <?php echo htmlspecialchars($this->tr('operator.notifications.history_title', 'Recent Dismissed / Read')); ?>
            <span class="coverage-orders-count"><?php echo count($ntfHistory); ?></span>
        </summary>
        <div class="coverage-orders-table-wrap">
            <table class="coverage-orders-table">
                <thead>
                    <tr>
                        <th></th>
                        <th><?php echo htmlspecialchars($this->tr('operator.notifications.col.alert', 'Alert')); ?></th>
                        <th><?php echo htmlspecialchars($this->tr('operator.notifications.col.status', 'Status')); ?></th>
                        <th class="num"><?php echo htmlspecialchars($this->tr('operator.notifications.col.when', 'When')); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($ntfHistory as $hItem): ?>
                    <?php
                        $hIcon    = htmlspecialchars((string)($hItem['icon']     ?? ''));
                        $hTitle   = htmlspecialchars((string)($hItem['title']    ?? '-'));
                        $hStatus  = (string)($hItem['status']  ?? '');
                        $hTs      = (int)($hItem['ts'] ?? 0);
                        $hAge     = '-';
                        if ($hTs > 0) {
                            $diff = max(0, time() - $hTs);
                            if ($diff < 3600) {
                                $hAge = (int)($diff / 60) . $this->tr('operator.notifications.time.m', 'm ago');
                            } elseif ($diff < 86400) {
                                $hAge = (int)($diff / 3600) . $this->tr('operator.notifications.time.h', 'h ago');
                            } else {
                                $hAge = (int)($diff / 86400) . $this->tr('operator.notifications.time.d', 'd ago');
                            }
                        }
                        $hStatusLabel = $hStatus === 'dismissed'
                            ? $this->tr('operator.notifications.status.dismissed', 'Dismissed')
                            : $this->tr('operator.notifications.status.read', 'Read');
                    ?>
                    <tr class="coverage-orders-row ntf-history-row">
                        <td class="operator-icon-cell"><?php echo $hIcon; ?></td>
                        <td><?php echo $hTitle; ?></td>
                        <td><span class="ntf-history-badge"><?php echo htmlspecialchars($hStatusLabel); ?></span></td>
                        <td class="num"><?php echo htmlspecialchars($hAge); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </details>
    <?php endif; ?>

</section>

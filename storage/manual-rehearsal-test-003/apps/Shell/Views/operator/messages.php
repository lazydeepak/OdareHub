<?php
// Operator Layer View: message center
// All composer scope variables available via include.
?>
<?php
    $msg = (array)($data['messages_focus'] ?? []);
    $msgItems = (array)($msg['items'] ?? []);
    $msgTotal = (int)($msg['total'] ?? 0);
    $msgUnread = (int)($msg['unread'] ?? 0);
    $msgWarning = (int)($msg['total_warning'] ?? 0);
    $msgInfo = (int)($msg['total_info'] ?? 0);
    $msgEmpty = (bool)($msg['empty'] ?? true);
    $msgError = (string)($msg['error'] ?? '');
    $msgUsername = rawurlencode((string)($data['username'] ?? ''));
    $msgRedirect = '/u/' . $msgUsername . '/messages';
?>
<section class="coverage-focus-section" aria-label="<?php echo htmlspecialchars($this->tr('operator.messages.page_title', 'Message Center')); ?>">
    <div class="coverage-focus-header">
        <h2 class="surface-title"><?php echo htmlspecialchars($this->tr('operator.messages.page_title', 'Message Center')); ?></h2>
        <?php if ($msgUnread > 0): ?>
            <form method="post" action="<?php echo htmlspecialchars('/u/' . $msgUsername . '/messages/read-all'); ?>" class="operator-ml-auto">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string)\App\Core\Auth::csrfToken()); ?>">
                <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($msgRedirect); ?>">
                <button type="submit" class="btn-sm"><?php echo htmlspecialchars($this->tr('operator.messages.mark_all_read', 'Mark all read')); ?></button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($msgError !== ''): ?>
        <p class="coverage-focus-error"><?php echo htmlspecialchars($msgError); ?></p>
    <?php endif; ?>

    <?php if ($msgEmpty && $msgError === ''): ?>
        <p class="coverage-focus-empty"><?php echo htmlspecialchars($this->tr('operator.messages.empty', 'No new messages.')); ?></p>
    <?php else: ?>

    <div class="coverage-kpi-strip">
        <div class="coverage-kpi-card">
            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.messages.kpi.total', 'Total Messages')); ?></span>
            <span class="coverage-kpi-value"><?php echo $msgTotal; ?></span>
        </div>
        <div class="coverage-kpi-card">
            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.messages.kpi.unread', 'Unread Messages')); ?></span>
            <span class="coverage-kpi-value"><?php echo $msgUnread; ?></span>
        </div>
        <div class="coverage-kpi-card coverage-kpi-card--warn">
            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.messages.kpi.warning', 'Warning Messages')); ?></span>
            <span class="coverage-kpi-value"><?php echo $msgWarning; ?></span>
        </div>
        <div class="coverage-kpi-card">
            <span class="coverage-kpi-label"><?php echo htmlspecialchars($this->tr('operator.messages.kpi.info', 'Info Messages')); ?></span>
            <span class="coverage-kpi-value"><?php echo $msgInfo; ?></span>
        </div>
    </div>

    <div class="coverage-orders-section">
        <h3 class="coverage-orders-title">
            <?php echo htmlspecialchars($this->tr('operator.messages.table_title', 'Message Queue')); ?>
            <span class="coverage-orders-count"><?php echo count($msgItems); ?></span>
        </h3>
        <div class="coverage-orders-table-wrap">
            <table class="coverage-orders-table">
                <thead>
                    <tr>
                        <th></th>
                        <th><?php echo htmlspecialchars($this->tr('operator.messages.col.message', 'Message')); ?></th>
                        <th class="num"><?php echo htmlspecialchars($this->tr('operator.messages.col.count', 'Count')); ?></th>
                        <th><?php echo htmlspecialchars($this->tr('operator.messages.col.status', 'Status')); ?></th>
                        <th><?php echo htmlspecialchars($this->tr('operator.messages.col.action', 'Action')); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($msgItems as $item): ?>
                    <?php
                        $itemId = (int)($item['id'] ?? 0);
                        $itemIcon = htmlspecialchars((string)($item['icon'] ?? ''));
                        $itemTitle = htmlspecialchars((string)($item['title'] ?? '-'));
                        $itemMessage = trim((string)($item['message'] ?? ''));
                        $itemMessageEscaped = htmlspecialchars($itemMessage);
                        $itemCount = (int)($item['count'] ?? 0);
                        $itemLink = htmlspecialchars((string)($item['link'] ?? '#'));
                        $itemStatus = strtolower(trim((string)($item['status'] ?? 'new')));
                        $isUnread = ($itemStatus === 'new');
                        $statusLabel = $isUnread
                            ? $this->tr('operator.messages.status.unread', 'Unread')
                            : $this->tr('operator.messages.status.read', 'Read');
                    ?>
                    <tr class="coverage-orders-row<?php echo $isUnread ? ' coverage-orders-row--warn' : ''; ?>">
                        <td class="operator-icon-cell"><?php echo $itemIcon; ?></td>
                        <td>
                            <div class="ui-block"><?php echo $itemTitle; ?></div>
                            <?php if ($itemMessage !== ''): ?>
                                <div class="queue-meta"><?php echo $itemMessageEscaped; ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="num"><?php echo $itemCount; ?></td>
                        <td><?php echo htmlspecialchars((string)$statusLabel); ?></td>
                        <td class="operator-action-cell">
                            <a href="<?php echo $itemLink; ?>" class="btn-sm"><?php echo htmlspecialchars($this->tr('operator.messages.view', 'Open')); ?></a>
                            <?php if ($itemId > 0 && $isUnread): ?>
                            <form method="post" action="<?php echo htmlspecialchars('/u/' . $msgUsername . '/messages/read'); ?>">
                                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string)\App\Core\Auth::csrfToken()); ?>">
                                <input type="hidden" name="id" value="<?php echo (int)$itemId; ?>">
                                <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($msgRedirect); ?>">
                                <button type="submit" class="btn-sm"><?php echo htmlspecialchars($this->tr('operator.messages.mark_read', 'Mark read')); ?></button>
                            </form>
                            <?php endif; ?>
                            <?php if ($itemId > 0): ?>
                            <form method="post" action="<?php echo htmlspecialchars('/u/' . $msgUsername . '/messages/dismiss'); ?>">
                                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string)\App\Core\Auth::csrfToken()); ?>">
                                <input type="hidden" name="id" value="<?php echo (int)$itemId; ?>">
                                <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($msgRedirect); ?>">
                                <button type="submit" class="btn-sm"><?php echo htmlspecialchars($this->tr('operator.messages.dismiss', 'Dismiss')); ?></button>
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
</section>

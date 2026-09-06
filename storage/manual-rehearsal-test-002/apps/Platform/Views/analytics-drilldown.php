<?php
/**
 * Analytics Drill-down View
 * Displays filtered orders list for drill-down from KPI cards
 */
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $this->tr('analytics.drilldown.page_title'); ?> - <?php echo ucfirst($type); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }

        .header h1 {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .header p {
            color: var(--muted);
            font-size: 14px;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            font-size: 13px;
        }

        .breadcrumb a {
            color: var(--link);
            text-decoration: none;
            cursor: pointer;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .breadcrumb-sep {
            color: var(--muted);
        }

        .controls {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
        }

        .type-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            background: var(--badge-bg);
            color: var(--badge-text);
        }

        .type-badge.overdue {
            background: var(--style-subtle-bg);
            color: var(--text);
        }

        .type-badge.completed {
            background: var(--style-subtle-bg);
            color: var(--text);
        }

        .type-badge.processing {
            background: var(--style-subtle-bg);
            color: var(--text);
        }

        .type-badge.draft {
            background: var(--style-subtle-bg);
            color: var(--text);
        }

        .info-text {
            font-size: 13px;
            color: var(--muted);
        }

        .table-wrapper {
            background: var(--card-bg);
            border-radius: 8px;
            border: 1px solid var(--border);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: var(--table-header-bg);
            border-bottom: 1px solid var(--border);
        }

        th {
            padding: 12px 16px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--muted);
        }

        tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background-color 0.2s;
        }

        tbody tr:hover {
            background: var(--row-hover-bg);
        }

        td {
            padding: 14px 16px;
            font-size: 13px;
        }

        .order-no {
            font-weight: 600;
            color: var(--link);
        }

        .priority-high {
            color: var(--text);
            font-weight: 600;
        }

        .priority-medium {
            color: var(--text);
            font-weight: 600;
        }

        .priority-low {
            color: var(--text);
            font-weight: 600;
        }

        .state-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .state-completed {
            background: var(--style-subtle-bg);
            color: var(--text);
        }

        .state-processing {
            background: var(--style-subtle-bg);
            color: var(--text);
        }

        .state-approved {
            background: var(--style-subtle-bg);
            color: var(--text);
        }

        .state-draft {
            background: var(--style-subtle-bg);
            color: var(--text);
        }

        .state-unknown {
            background: var(--style-subtle-bg);
            color: var(--text);
        }

        .due-overdue {
            color: var(--text);
            font-weight: 600;
        }

        .due-ok {
            color: var(--text);
        }

        .user-id {
            color: var(--muted);
            font-size: 12px;
        }

        .empty-state {
            padding: 60px 20px;
            text-align: center;
            color: var(--muted);
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 18px;
            margin-bottom: 8px;
            color: var(--text);
        }

        .pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        .pagination a,
        .pagination button {
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid var(--border);
            background: var(--card-bg);
            color: var(--text);
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
            transition: all 0.2s;
        }

        .pagination a:hover,
        .pagination button:hover {
            background: var(--link);
            border-color: var(--link);
            color: var(--text);
        }

        .pagination .current {
            padding: 8px 12px;
            color: var(--link);
            font-weight: 600;
        }

        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination .disabled:hover {
            background: var(--card-bg);
            border-color: var(--border);
            color: var(--text);
        }

        .back-link {
            display: inline-block;
            margin-bottom: 16px;
            color: var(--link);
            text-decoration: none;
            font-size: 13px;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            body {
                padding: 12px;
            }

            .container {
                max-width: 100%;
            }

            .header h1 {
                font-size: 22px;
            }

            .controls {
                flex-direction: column;
                align-items: flex-start;
            }

            .table-wrapper {
                overflow-x: auto;
            }

            th,
            td {
                padding: 10px 12px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="breadcrumb">
            <a href="/ops/analytics" class="back-link">← <?php echo $this->tr('analytics.drilldown.back_to_dashboard'); ?></a>
        </div>

        <div class="header">
            <h1>
                <?php
                $typeLabel = match ($type) {
                    'overdue' => $this->tr('analytics.drilldown.title_overdue'),
                    'completed' => $this->tr('analytics.drilldown.title_completed'),
                    'processing' => $this->tr('analytics.drilldown.title_processing'),
                    'draft' => $this->tr('analytics.drilldown.title_draft'),
                    default => $this->tr('analytics.drilldown.title_all'),
                };
                echo $typeLabel;
                ?>
            </h1>
            <p>
                <?php
                printf(
                    $this->tr('analytics.drilldown.count_total'),
                    (int)($drilldown['total_count'] ?? 0)
                );
                ?>
            </p>
        </div>

        <div class="controls">
            <span class="type-badge <?php echo htmlspecialchars($type, ENT_QUOTES); ?>">
                <?php echo ucfirst($type); ?>
            </span>
            <span class="info-text">
                <?php
                printf(
                    $this->tr('analytics.drilldown.showing_page'),
                    $page,
                    max(1, ceil((int)($drilldown['total_count'] ?? 0) / (int)($drilldown['limit'] ?? 50)))
                );
                ?>
            </span>
        </div>

        <?php if (!empty($drilldown['orders'])): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th><?php echo $this->tr('analytics.table.order_no'); ?></th>
                            <th><?php echo $this->tr('analytics.table.customer'); ?></th>
                            <th><?php echo $this->tr('analytics.table.priority'); ?></th>
                            <th><?php echo $this->tr('analytics.table.state'); ?></th>
                            <th><?php echo $this->tr('analytics.table.qty'); ?></th>
                            <th><?php echo $this->tr('analytics.table.created_at'); ?></th>
                            <th><?php echo $this->tr('analytics.table.due_at'); ?></th>
                            <th><?php echo $this->tr('analytics.table.assigned_to'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($drilldown['orders'] as $order): ?>
                            <?php if (!is_array($order)) continue; ?>
                            <tr>
                                <td class="order-no"><?php echo htmlspecialchars((string)($order['order_no'] ?? ''), ENT_QUOTES); ?></td>
                                <td><?php echo htmlspecialchars((string)($order['customer'] ?? ''), ENT_QUOTES); ?></td>
                                <td class="<?php echo htmlspecialchars((string)($order['priority_class'] ?? ''), ENT_QUOTES); ?>">
                                    <?php echo ucfirst((string)($order['priority'] ?? '')); ?>
                                </td>
                                <td>
                                    <span class="state-badge <?php echo htmlspecialchars((string)($order['state_class'] ?? ''), ENT_QUOTES); ?>">
                                        <?php echo ucfirst((string)($order['state'] ?? '')); ?>
                                    </span>
                                </td>
                                <td><?php echo number_format((float)($order['qty'] ?? 0), 2); ?></td>
                                <td><?php echo date('M d, Y', strtotime((string)($order['created_at'] ?? 'now'))); ?></td>
                                <td>
                                    <?php
                                    $dueAt = (string)($order['due_at'] ?? '');
                                    if ($dueAt && $dueAt !== '0000-00-00' && $dueAt !== '') {
                                        $dueDateClass = strtotime($dueAt) < time() ? 'due-overdue' : 'due-ok';
                                        echo '<span class="' . htmlspecialchars($dueDateClass, ENT_QUOTES) . '">';
                                        echo date('M d, Y', strtotime($dueAt));
                                        echo '</span>';
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </td>
                                <td class="user-id">
                                    <?php echo (int)($order['assigned_to'] ?? 0) > 0 ? '#' . (int)($order['assigned_to'] ?? 0) : '—'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ((bool)($drilldown['has_more'] ?? false) || $page > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?type=<?php echo rawurlencode($type); ?>&page=1&limit=<?php echo (int)($drilldown['limit'] ?? 50); ?>">
                            &laquo; <?php echo $this->tr('analytics.drilldown.first'); ?>
                        </a>
                        <a href="?type=<?php echo rawurlencode($type); ?>&page=<?php echo $page - 1; ?>&limit=<?php echo (int)($drilldown['limit'] ?? 50); ?>">
                            &lsaquo; <?php echo $this->tr('analytics.drilldown.prev'); ?>
                        </a>
                    <?php endif; ?>

                    <span class="current">
                        <?php echo $page; ?>
                    </span>

                    <?php if ((bool)($drilldown['has_more'] ?? false)): ?>
                        <a href="?type=<?php echo rawurlencode($type); ?>&page=<?php echo $page + 1; ?>&limit=<?php echo (int)($drilldown['limit'] ?? 50); ?>">
                            <?php echo $this->tr('analytics.drilldown.next'); ?> &rsaquo;
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">📋</div>
                <h3><?php echo $this->tr('analytics.drilldown.empty_title'); ?></h3>
                <p><?php echo $this->tr('analytics.drilldown.empty_description'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

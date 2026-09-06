<?php
/**
 * Production Metrics Drill-down View
 * Displays production entries with shift and machine details
 */
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $this->tr('analytics.drilldown.production.title'); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
            padding: 20px;
        }
        .container { max-width: 1400px; margin: 0 auto; }
        .header { margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid var(--border); }
        .header h1 { font-size: 28px; font-weight: 600; margin-bottom: 8px; }
        .header p { color: var(--muted); font-size: 14px; }
        .breadcrumb { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; font-size: 13px; }
        .breadcrumb a { color: var(--link); text-decoration: none; cursor: pointer; }
        .breadcrumb a:hover { text-decoration: underline; }
        .controls { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; align-items: center; }
        .type-badge { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 13px; font-weight: 600; background: var(--badge-bg); color: var(--text); }
        .info-text { font-size: 13px; color: var(--muted); }
        .table-wrapper { background: var(--card-bg); border-radius: 8px; border: 1px solid var(--border); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        thead { background: var(--table-header-bg); border-bottom: 1px solid var(--border); }
        th { padding: 12px 16px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--muted); }
        tbody tr { border-bottom: 1px solid var(--border); transition: background-color 0.2s; }
        tbody tr:hover { background: var(--row-hover-bg); }
        td { padding: 14px 16px; font-size: 13px; }
        .status-good { color: var(--text); font-weight: 600; }
        .status-warn { color: var(--color-warning-text); font-weight: 600; }
        .status-danger { color: var(--color-danger-text); font-weight: 600; }
        .qty-numeric { text-align: right; font-family: 'Monaco', monospace; }
        .shift-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; background: var(--style-subtle-bg); color: var(--text); }
        .empty-state { padding: 60px 20px; text-align: center; color: var(--muted); }
        .empty-state-icon { font-size: 48px; margin-bottom: 16px; opacity: 0.5; }
        .empty-state h3 { font-size: 18px; margin-bottom: 8px; color: var(--text); }
        .pagination { display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border); }
        .pagination a, .pagination button { padding: 8px 12px; border-radius: 4px; border: 1px solid var(--border); background: var(--card-bg); color: var(--text); cursor: pointer; font-size: 13px; text-decoration: none; transition: all 0.2s; }
        .pagination a:hover, .pagination button:hover { background: var(--link); border-color: var(--link); color: var(--text); }
        .pagination .current { padding: 8px 12px; color: var(--link); font-weight: 600; }
        .pagination .disabled { opacity: 0.5; cursor: not-allowed; }
        .back-link { display: inline-block; margin-bottom: 16px; color: var(--link); text-decoration: none; font-size: 13px; }
        .back-link:hover { text-decoration: underline; }
        @media (max-width: 768px) {
            body { padding: 12px; }
            .header h1 { font-size: 22px; }
            .controls { flex-direction: column; align-items: flex-start; }
            th, td { padding: 10px 12px; font-size: 12px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="breadcrumb">
            <a href="/ops/analytics" class="back-link">← <?php echo $this->tr('analytics.drilldown.back_to_dashboard'); ?></a>
        </div>

        <div class="header">
            <h1><?php echo $this->tr('analytics.drilldown.production.title'); ?></h1>
            <p><?php printf($this->tr('analytics.drilldown.count_total'), (int)($drilldown['total_count'] ?? 0)); ?></p>
        </div>

        <div class="controls">
            <span class="type-badge"><?php echo ucfirst($type); ?></span>
            <span class="info-text">
                <?php printf($this->tr('analytics.drilldown.showing_page'), $page, max(1, ceil((int)($drilldown['total_count'] ?? 0) / (int)($drilldown['limit'] ?? 50)))); ?>
            </span>
        </div>

        <?php if (!empty($drilldown['entries'])): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th><?php echo $this->tr('analytics.drilldown.production.id'); ?></th>
                            <th><?php echo $this->tr('analytics.drilldown.production.date'); ?></th>
                            <th><?php echo $this->tr('analytics.drilldown.production.shift'); ?></th>
                            <th><?php echo $this->tr('analytics.drilldown.production.machine_id'); ?></th>
                            <th><?php echo $this->tr('analytics.drilldown.production.produced_qty'); ?></th>
                            <th><?php echo $this->tr('analytics.drilldown.production.good_qty'); ?></th>
                            <th><?php echo $this->tr('analytics.drilldown.production.rejected_qty'); ?></th>
                            <th><?php echo $this->tr('analytics.drilldown.production.quality_rate'); ?></th>
                            <th><?php echo $this->tr('analytics.drilldown.production.created_at'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($drilldown['entries'] as $entry): ?>
                            <?php if (!is_array($entry)) continue; ?>
                            <tr>
                                <td><?php echo (int)($entry['id'] ?? 0); ?></td>
                                <td><?php echo htmlspecialchars((string)($entry['production_date'] ?? ''), ENT_QUOTES); ?></td>
                                <td><span class="shift-badge"><?php echo ucfirst((string)($entry['shift'] ?? '')); ?></span></td>
                                <td class="qty-numeric"><?php echo (int)($entry['machine_id'] ?? 0); ?></td>
                                <td class="qty-numeric"><?php echo number_format((float)($entry['produced_qty'] ?? 0), 2); ?></td>
                                <td class="qty-numeric status-good"><?php echo number_format((float)($entry['good_qty'] ?? 0), 2); ?></td>
                                <td class="qty-numeric status-danger"><?php echo number_format((float)($entry['rejected_qty'] ?? 0), 2); ?></td>
                                <td class="<?php echo htmlspecialchars((string)($entry['quality_class'] ?? ''), ENT_QUOTES); ?>">
                                    <?php echo htmlspecialchars((string)($entry['quality_rate_text'] ?? '0%'), ENT_QUOTES); ?>
                                </td>
                                <td><?php echo date('M d, Y H:i', strtotime((string)($entry['created_at'] ?? ''))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ((int)($drilldown['total_count'] ?? 0) > (int)($drilldown['limit'] ?? 50)): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?type=<?php echo htmlspecialchars($type, ENT_QUOTES); ?>&page=1"><?php echo $this->tr('analytics.drilldown.first'); ?></a>
                        <a href="?type=<?php echo htmlspecialchars($type, ENT_QUOTES); ?>&page=<?php echo $page - 1; ?>"><?php echo $this->tr('analytics.drilldown.prev'); ?></a>
                    <?php endif; ?>
                    <span class="current">Page <?php echo $page; ?></span>
                    <?php if ($drilldown['has_more'] ?? false): ?>
                        <a href="?type=<?php echo htmlspecialchars($type, ENT_QUOTES); ?>&page=<?php echo $page + 1; ?>"><?php echo $this->tr('analytics.drilldown.next'); ?></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">⚙️</div>
                <h3><?php echo $this->tr('analytics.drilldown.no_data'); ?></h3>
                <p><?php echo $this->tr('analytics.drilldown.no_entries'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

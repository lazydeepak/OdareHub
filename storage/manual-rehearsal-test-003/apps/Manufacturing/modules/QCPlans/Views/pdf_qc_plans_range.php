<?php
/** @var string $from_date */
/** @var string $to_date */
/** @var int $total_rows */
/** @var array<int,array<string,mixed>> $rows */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>QC Plans Print</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
            color: #111;
            font-size: 11px;
            line-height: 1.35;
        }

        .header {
            margin-bottom: 10px;
            border-bottom: 1px solid #cfd3d8;
            padding-bottom: 8px;
        }

        .title {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
        }

        .muted {
            color: #555;
            font-size: 11px;
            margin-top: 3px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th,
        td {
            border: 1px solid #d9dee4;
            padding: 5px 6px;
            vertical-align: top;
            word-wrap: break-word;
        }

        thead th {
            background: #eef1f5;
            font-weight: 700;
        }

        .right {
            text-align: right;
        }

        .part-number {
            color: #555;
            font-size: 10px;
            margin-top: 2px;
        }

        .empty {
            margin-top: 12px;
            padding: 10px;
            border: 1px dashed #cfd3d8;
            color: #555;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="title">QC Plans</h1>
        <div class="muted">Window: <?= e((string)$from_date) ?> to <?= e((string)$to_date) ?></div>
        <div class="muted">Total Plans: <?= (int)$total_rows ?></div>
        <div class="muted">Workflow: System Generated -> QC Verification/Adjustment -> Higher Authority Approval</div>
    </div>

    <?php if (empty($rows ?? [])): ?>
        <div class="empty">No QC plans found for the selected timeframe.</div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th class="u-style-9ce9ae9d43">ID</th>
                    <th class="u-style-477d664a08">Plan Date</th>
                    <th class="u-style-477d664a08">Required Date</th>
                    <th class="u-style-55b2e17218">Part</th>
                    <th class="u-style-8c8f533b00">Qty</th>
                    <th class="u-style-8c8f533b00">Est Min</th>
                    <th class="u-style-8c8f533b00">Priority</th>
                    <th class="u-style-c28f1b1511">Status</th>
                    <th class="u-style-477d664a08">Assigned to</th>
                    <th class="u-style-c28f1b1511">Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= (int)($row['id'] ?? 0) ?></td>
                        <td><?= e((string)($row['plan_date'] ?? '')) ?></td>
                        <td><?= e((string)($row['required_date'] ?? '')) ?></td>
                        <td>
                            <?= e((string)($row['parts_name'] ?? '')) ?>
                            <div class="part-number"><?= e((string)($row['parts_number'] ?? '')) ?></div>
                        </td>
                        <td class="right"><?= e((string)($row['planned_qty'] ?? '0')) ?></td>
                        <td class="right"><?= (int)($row['estimated_time_minutes'] ?? 0) ?></td>
                        <td><?= e((string)($row['priority'] ?? '')) ?></td>
                        <td><?= e((string)($row['status'] ?? '')) ?></td>
                        <td><?= e((string)($row['assigned_to'] ?? '-')) ?></td>
                        <td><?= e((string)($row['notes'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>

<?php
$tt = static function (string $key): string {
  return t($key);
};
/** @var string $start_date */
/** @var string $end_date */
/** @var int $total_rows */
/** @var array<int,array<string,mixed>> $groups */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Production Plans - Next 2 Weeks</title>
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

        .machine-block {
            margin-top: 12px;
            page-break-inside: avoid;
        }

        .machine-title {
            margin: 0 0 6px;
            font-size: 13px;
            font-weight: 700;
            background: #f2f4f7;
            padding: 5px 7px;
            border: 1px solid #d9dee4;
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
        <h1 class="title">Production Plans by Machine</h1>
        <div class="muted">Window: <?= e((string)$start_date) ?> to <?= e((string)$end_date) ?> (next 2 weeks)</div>
        <div class="muted">Total Plans: <?= (int)$total_rows ?></div>
    </div>

    <?php if (empty($groups ?? [])): ?>
        <div class="empty">No production plans found for the next 2 weeks.</div>
    <?php else: ?>
        <?php foreach ($groups as $group): ?>
            <div class="machine-block">
                <h2 class="machine-title"><?= e((string)($group['machine_label'] ?? 'Machine')) ?></h2>
                <table>
                    <thead>
                        <tr>
                            <th class="u-style-3d7d7015fc">ID</th>
                            <th class="u-style-6bbafabf21">Plan Date</th>
                            <th class="u-style-8db5008c7a">Seq</th>
                            <th class="u-style-f54eceeaa1">Part</th>
                            <th class="u-style-8c8f533b00">Qty</th>
                            <th class="u-style-477d664a08">Status</th>
                            <th class="u-style-fd14ca83c7"> <?= e($tt('production_plans.added_by_column')) ?> </th>
                            <th class="u-style-ffe23b7ae4">Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($group['rows'] ?? []) as $row): ?>
                            <tr>
                                <td><?= (int)($row['id'] ?? 0) ?></td>
                                <td><?= e((string)($row['plan_date'] ?? '')) ?></td>
                                <td><?= (int)($row['sequence_no'] ?? 0) ?></td>
                                <td>
                                    <?= e((string)($row['parts_name'] ?? '')) ?>
                                    <div class="part-number"><?= e((string)($row['parts_number'] ?? '')) ?></div>
                                </td>
                                <td class="right"><?= e((string)($row['planned_qty'] ?? '0')) ?></td>
                                <td><?= e((string)($row['status'] ?? '')) ?></td>
                                <td><?= e((string)($row['added_by'] ?? '-')) ?></td>
                                <td>
                                    <?= e((string)($row['reference_doctype'] ?? '')) ?>
                                    <?= e((string)($row['reference_name'] ?? '')) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>

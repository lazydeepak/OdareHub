<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Production Plan</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #333;
        }
        @page {
            margin: 24px;
            size: A4 portrait;
        }
        .page {
            max-width: 100%;
        }
        h1 {
            font-size: 16px;
            margin-bottom: 8px;
            text-align: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }
        table th, table td {
            border: 1px solid #333;
            padding: 6px 4px;
            text-align: left;
        }
        table th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        .header-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            <table>
                <thead>
                    <tr>
                        <th> <?= e($tt('production_plans.field_column')) ?> </th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <th>Added by</th>
                        <td><?= e((string)($row['added_by'] ?? '-')) ?></td>
                    </tr>
                </tbody>
            </table>
            margin-bottom: 12px;
        }
        .header-item {
            flex: 1;
            min-width: 150px;
        }
        .label {
            font-weight: bold;
            color: #555;
            font-size: 10px;
        }
        .value {
            color: #333;
            font-size: 11px;
        }
        .footer {
            margin-top: 12px;
            text-align: center;
            color: #888;
            font-size: 9px;
        }
    </style>
</head>
<body>
<div class="page">
    <h1>Production Plan</h1>
    
    <div class="header-row">
        <div class="header-item">
            <div class="label">Plan ID:</div>
            <div class="value"><?= (int)$row['id'] ?></div>
        </div>
        <div class="header-item">
            <div class="label">Plan Date:</div>
            <div class="value"><?= e((string)$row['plan_date']) ?></div>
        </div>
        <div class="header-item">
            <div class="label"> <?= e($tt('production_plans.status_label')) ?> </div>
            <div class="value"><?= e((string)$row['status']) ?></div>
        </div>
        <div class="header-item">
            <div class="label">Plan Type:</div>
            <div class="value"><?= e((string)$row['plan_type']) ?></div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Machine</th>
                <th>Part / Product</th>
                <th>Part Number</th>
                <th>Planned Qty</th>
                <th>Sequence</th>
                <th>Runtime (hrs)</th>
                <th>Coverage %</th>
                <th>Shortage Qty</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?= e((string)$row['machine_no']) ?> - <?= e((string)$row['machine_name']) ?></td>
                <td><?= e((string)$row['parts_name']) ?></td>
                <td><?= e((string)$row['parts_number']) ?></td>
                <td><?= e((string)$row['planned_qty']) ?></td>
                <td><?= (int)$row['sequence_no'] ?></td>
                <td><?= e((string)($row['runtime'] ?? '—')) ?></td>
                <td><?= e((string)$row['coverage_pct']) ?></td>
                <td><?= e((string)$row['shortage_qty']) ?></td>
            </tr>
        </tbody>
    </table>

    <table>
        <thead>
            <tr>
                <th> <?= e($tt('production_plans.field_column')) ?> </th>
                <th>Value</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Auto Created</td>
                <td><?= (int)$row['auto_created'] === 1 ? 'Yes' : 'No' ?></td>
            </tr>
            <tr>
                <td>Reference Doctype</td>
                <td><?= e((string)($row['reference_doctype'] ?? '—')) ?></td>
            </tr>
            <tr>
                <td>Reference Name</td>
                <td><?= e((string)($row['reference_name'] ?? '—')) ?></td>
            </tr>
            <tr>
                <td>Notes</td>
                <td><?= nl2br(e((string)($row['notes'] ?? '—'))) ?></td>
            </tr>
            <tr>
                <td>Created At</td>
                <td><?= e((string)$row['created_at']) ?></td>
            </tr>
            <tr>
                <td>Updated At</td>
                <td><?= e((string)($row['updated_at'] ?? '—')) ?></td>
            </tr>
        </tbody>
    </table>

    <div class="u-style-a3f2aa21fb">
        <p>English | 日本語 | नेपाली</p>
    </div>

    <div class="footer">
        <p> <?= e($tt('production_plans.this_document_is_label')) ?> <?= date('Y-m-d H:i:s') ?></p>
    </div>
</div>
</body>
</html>

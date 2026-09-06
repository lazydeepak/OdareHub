<?php
/** @var array $entry */
/** @var string $generatedAt */
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <style>
    @page { margin: 24px; }
    body { font-family: 'DejaVu Sans', sans-serif; color: #111; font-size: 12px; }
    h1 { margin: 0 0 12px 0; font-size: 20px; }
    .meta { margin-bottom: 14px; color: #444; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #bbb; padding: 8px; text-align: left; vertical-align: top; }
    th { background: #f1f1f1; width: 28%; }
    .remarks { min-height: 60px; white-space: pre-wrap; }
    .footer { margin-top: 16px; font-size: 11px; color: #666; }
  </style>
</head>
<body>
  <h1><?= e(t('module.dispatch_entries.title')) ?> PDF</h1>
  <div class="meta">Generated: <?= e($generatedAt) ?></div>

  <table>
    <tr><th><?= e(t('common.id')) ?></th><td>#<?= (int)$entry['id'] ?></td></tr>
    <tr><th><?= e(t('common.date')) ?></th><td><?= e((string)$entry['dispatch_date']) ?></td></tr>
    <tr><th><?= e(t('common.part')) ?></th><td><?= e((string)($entry['parts_name'] ?? '')) ?></td></tr>
    <tr><th><?= e(t('common.part_number')) ?></th><td><?= e((string)($entry['parts_number'] ?? '')) ?></td></tr>
    <tr><th><?= e(t('module.dispatch_entries.dispatchable_qty')) ?></th><td><?= e((string)$entry['dispatchable_qty']) ?></td></tr>
    <tr><th><?= e(t('module.dispatch_entries.destination')) ?></th><td><?= e((string)($entry['destination'] ?? '')) ?></td></tr>
    <tr><th><?= e(t('module.dispatch_entries.dispatch_type')) ?></th><td><?= e((string)($entry['dispatch_type'] ?? '')) ?></td></tr>
    <tr><th><?= e(t('module.dispatch_entries.dispatch_status')) ?></th><td><?= e((string)($entry['dispatch_status'] ?? '')) ?></td></tr>
    <tr><th><?= e(t('common.remarks')) ?></th><td class="remarks"><?= e((string)($entry['remarks'] ?? '')) ?></td></tr>
    <tr>
      <th><?= e(t('module.qc_entries.title')) ?></th>
      <td>
        <?php if ((int)($entry['qc_entry_id'] ?? 0) > 0): ?>
          #<?= (int)$entry['qc_entry_id'] ?>
          (<?= e(t('module.qc_entries.checked_qty')) ?>: <?= e((string)($entry['qc_checked_qty'] ?? '')) ?>,
          <?= e(t('module.qc_entries.pass_qty')) ?>: <?= e((string)($entry['qc_pass_qty'] ?? '')) ?>,
          <?= e(t('module.qc_entries.fail_qty')) ?>: <?= e((string)($entry['qc_fail_qty'] ?? '')) ?>)
        <?php else: ?>
          -
        <?php endif; ?>
      </td>
    </tr>
  </table>

  <div class="footer">
    Sample multilingual text: English | 日本語 | नेपाली
  </div>
</body>
</html>

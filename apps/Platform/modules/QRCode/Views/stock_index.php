<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2>QR Stock Updates</h2>
      <div class="muted">Quick stock movement logging from scanned product QR codes.</div>
    </div>
    <div class="module-header-actions">
      <a class="btn ok" href="/qr/stock/add"> <?= e($tt('q_r_code.add_link')) ?> </a>
    </div>
  </div>
</div>
<?php if (!empty($flash ?? '')): ?><div class="card notice-ok"><?= e((string)$flash) ?></div><?php endif; ?>
<?php if (!empty($error ?? '')): ?><div class="card notice-err"><?= e((string)$error) ?></div><?php endif; ?>
<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Part</th>
          <th>Movement</th>
          <th>Qty</th>
          <th>Reference</th>
          <th>Notes</th>
          <th> <?= e($tt('q_r_code.created_column')) ?> </th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td><?= (int)$row['id'] ?></td>
            <td><?= e((string)$row['parts_name']) ?> <span class="muted">(<?= e((string)$row['parts_number']) ?>)</span></td>
            <td><span class="pill"><?= e((string)$row['movement_type']) ?></span></td>
            <td><?= e((string)$row['qty']) ?></td>
            <td><?= e((string)($row['reference_no'] ?? '')) ?></td>
            <td><?= e((string)($row['notes'] ?? '')) ?></td>
            <td class="muted"><?= e((string)$row['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
          <tr><td colspan="7" class="muted">No stock updates logged yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

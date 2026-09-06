<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2><?= e(t('stock_reconciliation')) ?></h2>
      <div class="muted"><?= e(t('stock_reconciliation_subtitle')) ?></div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/ledger"><?= e(t('stock_ledger')) ?></a>
    </div>
  </div>
</div>
<?php if (!empty($flash ?? '')): ?><div class="card notice-ok"><?= e((string)$flash) ?></div><?php endif; ?>
<?php if (!empty($error ?? '')): ?><div class="card notice-err"><?= e((string)$error) ?></div><?php endif; ?>
<div class="card">
  <form method="get" action="/ledger/reconcile" class="module-filterbar">
    <label class="row u-style-d57ae4c45f">
      <input type="checkbox" name="mismatches_only" value="1" <?= !empty($mismatches_only) ? 'checked' : '' ?>>
      <span><?= e(t('show_mismatches_only')) ?></span>
    </label>
    <div class="module-filterbar-actions">
      <button class="btn" type="submit"><?= e(t('filter')) ?></button>
      <a class="btn" href="/ledger/reconcile"><?= e(t('reset')) ?></a>
    </div>
  </form>
</div>
<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th><?= e(t('part')) ?></th>
          <th><?= e(t('part_number')) ?></th>
          <th><?= e(t('ledger_balance')) ?></th>
          <th><?= e(t('production_in')) ?></th>
          <th><?= e(t('dispatch_out')) ?></th>
          <th><?= e(t('qc_out')) ?></th>
          <th><?= e(t('qr_delta')) ?></th>
          <th><?= e(t('expected_balance')) ?></th>
          <th><?= e(t('variance')) ?></th>
          <th><?= e(t('actions')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <?php $variance = (float)($row['variance'] ?? 0); ?>
          <tr>
            <td><?= e((string)$row['parts_name']) ?></td>
            <td><code><?= e((string)$row['parts_number']) ?></code></td>
            <td><?= e((string)$row['ledger_balance']) ?></td>
            <td><?= e((string)$row['production_in']) ?></td>
            <td><?= e((string)$row['dispatch_out']) ?></td>
            <td><?= e((string)$row['qc_out']) ?></td>
            <td><?= e((string)$row['qr_delta']) ?></td>
            <td><strong><?= e((string)$row['expected_balance']) ?></strong></td>
            <td>
              <?php if (abs($variance) > 0.0001): ?>
                <span class="pill bad"><?= e(number_format($variance, 2, '.', '')) ?></span>
              <?php else: ?>
                <span class="pill ok">0.00</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="row">
                <a class="btn" href="/ledger?product_id=<?= (int)$row['id'] ?>"><?= e(t('ledger')) ?></a>
                <?php if (abs($variance) > 0.0001): ?>
                  <form method="post" action="/ledger/reconcile/repair" onsubmit="return confirm('<?= e(t('repair_product_ledger_confirm')) ?>');" style="margin:0">
                    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
                    <input type="hidden" name="product_id" value="<?= (int)$row['id'] ?>">
                    <button class="btn ok" type="submit"><?= e(t('repair')) ?></button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
          <tr><td colspan="10" class="muted"><?= e(t('no_reconciliation_rows_found')) ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
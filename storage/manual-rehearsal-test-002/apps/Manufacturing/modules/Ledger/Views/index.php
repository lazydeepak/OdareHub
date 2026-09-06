<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2><?= e(t('stock_ledger')) ?></h2>
      <div class="muted"><?= e(t('stock_ledger_subtitle')) ?></div>
    </div>
    <div class="module-header-actions">
      <form method="post" action="/ledger/rebuild" onsubmit="return confirm('<?= e(t('rebuild_ledger_confirm')) ?>');" style="margin:0">
        <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
        <button class="btn" type="submit"><?= e(t('rebuild_ledger')) ?></button>
      </form>
      <a class="btn ok" href="/ledger/add"><?= e(t('add_stock_entry')) ?></a>
    </div>
  </div>
</div>
<?php if (!empty($flash ?? '')): ?><div class="card notice-ok"><?= e((string)$flash) ?></div><?php endif; ?>
<?php if (!empty($error ?? '')): ?><div class="card notice-err"><?= e((string)$error) ?></div><?php endif; ?>
<?php if (!empty($source_module ?? '') || (int)($source_id ?? 0) > 0): ?>
  <div class="card">
    <div class="row u-style-2d7d2729a4">
      <div class="muted">
        <?= e(t('source_filter_active')) ?>:
        <?= e((string)($source_module ?? '')) ?>
        <?= (int)($source_id ?? 0) > 0 ? '#' . (int)$source_id : '' ?>
      </div>
      <a class="btn" href="/ledger"><?= e(t('clear_source_filter')) ?></a>
    </div>
  </div>
<?php endif; ?>
<div class="card">
  <form method="get" action="/ledger" class="module-filterbar">
    <?php if (!empty($source_module ?? '')): ?>
      <input type="hidden" name="source_module" value="<?= e((string)$source_module) ?>">
    <?php endif; ?>
    <?php if ((int)($source_id ?? 0) > 0): ?>
      <input type="hidden" name="source_id" value="<?= (int)$source_id ?>">
    <?php endif; ?>
    <div class="module-filter-field-wide">
      <label class="module-filter-label"><?= e(t('part')) ?></label>
      <select name="product_id">
        <option value="0"><?= e(t('all_parts')) ?></option>
        <?php foreach ($products as $product): ?>
          <option value="<?= (int)$product['id'] ?>" <?= ((int)$product_id === (int)$product['id']) ? 'selected' : '' ?>><?= e((string)$product['parts_name']) ?> (<?= e((string)$product['parts_number']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="module-filterbar-actions">
      <button class="btn" type="submit"><?= e(t('filter')) ?></button>
      <a class="btn" href="/ledger"><?= e(t('reset')) ?></a>
    </div>
  </form>
</div>
<div class="card">
  <h3 class="u-style-d462248a40"><?= e(t('current_stock_balances')) ?></h3>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Part</th>
          <th><?= e(t('part_number')) ?></th>
          <th><?= e(t('balance')) ?></th>
          <th><?= e(t('last_movement')) ?></th>
          <th><?= e(t('actions')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($summaryRows as $row): ?>
          <tr>
            <td><?= e((string)$row['parts_name']) ?></td>
            <td><code><?= e((string)$row['parts_number']) ?></code></td>
            <td><strong><?= e((string)$row['balance']) ?></strong></td>
            <td class="muted"><?= e((string)($row['last_movement'] ?? '')) ?></td>
            <td><a class="btn" href="/ledger/add?product_id=<?= (int)$row['id'] ?>"><?= e(t('post_entry')) ?></a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($summaryRows)): ?>
          <tr><td colspan="5" class="muted"><?= e(t('no_stock_balances_available')) ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<div class="card">
  <h3 class="u-style-d462248a40"><?= e(t('recent_ledger_entries')) ?></h3>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Part</th>
          <th><?= e(t('movement')) ?></th>
          <th><?= e(t('qty_delta')) ?></th>
          <th><?= e(t('balance_after')) ?></th>
          <th><?= e(t('reference')) ?></th>
          <th><?= e(t('source')) ?></th>
          <th><?= e(t('notes')) ?></th>
          <th><?= e(t('created')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td><?= (int)$row['id'] ?></td>
            <td><?= e((string)$row['parts_name']) ?> <span class="muted">(<?= e((string)$row['parts_number']) ?>)</span></td>
            <td><span class="pill"><?= e((string)$row['movement_type']) ?></span></td>
            <td><?= e((string)$row['qty_delta']) ?></td>
            <td><strong><?= e((string)$row['balance_after']) ?></strong></td>
            <td><?= e((string)($row['reference_no'] ?? '')) ?></td>
            <td><?= e((string)($row['source_module'] ?? '')) ?><?= !empty($row['source_id']) ? ' #' . (int)$row['source_id'] : '' ?></td>
            <td><?= e((string)($row['notes'] ?? '')) ?></td>
            <td class="muted"><?= e((string)$row['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
          <tr><td colspan="9" class="muted"><?= e(t('no_ledger_entries_found')) ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

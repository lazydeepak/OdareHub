<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<?php $suppliers = is_array($suppliers ?? null) ? $suppliers : []; ?>
<?php $requests = is_array($requests ?? null) ? $requests : []; ?>

<?php
$chipClass = static function (string $state): string {
  $s = strtolower(trim($state));
  return match ($s) {
    'approved', 'issued', 'posted', 'closed' => 'chip chip--ok',
    'partial' => 'chip chip--warn',
    'cancelled', 'rejected', 'blocked', 'inactive' => 'chip chip--danger',
    default => 'chip chip--neutral',
  };
};
?>

<?php if (!empty($message)): ?>
  <section class="card card--success"><?= e((string)$message) ?></section>
<?php endif; ?>
<?php if (!empty($error)): ?>
  <section class="card card--error"><?= e((string)$error) ?></section>
<?php endif; ?>

<section class="card">
  <div class="section-head">
    <div class="ui-block">
      <h2 class="u-style-1169661891"><?= e(t('proc.orders.title')) ?></h2>
      <div class="muted u-style-fe7b4979fe"><?= e(t('proc.orders.subtitle')) ?></div>
    </div>
    <div class="row u-style-4725bb7117">
      <a class="btn" href="/apps/procurement"><?= e(t('proc.orders.link_dashboard')) ?></a>
      <a class="btn" href="/apps/procurement/requests"><?= e(t('proc.orders.link_requests')) ?></a>
      <a class="btn" href="/apps/procurement/receipts"><?= e(t('proc.orders.link_receipts')) ?></a>
    </div>
  </div>
</section>

<section class="card">
  <h3 class="u-style-d462248a40"><?= e(t('proc.orders.create_title')) ?></h3>
  <form method="post" action="/apps/procurement/orders/create" class="grid u-style-1a42931a01">
    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
    <label><?= e(t('proc.orders.form_ref')) ?><input class="input" type="text" name="po_ref"></label>
    <label><?= e(t('proc.orders.form_supplier')) ?>
      <select name="supplier_id" required>
        <option value=""><?= e(t('proc.orders.form_select_supplier')) ?></option>
        <?php foreach ($suppliers as $s): ?>
          <option value="<?= (int)($s['id'] ?? 0) ?>"><?= e((string)($s['supplier_name'] ?? '')) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label><?= e(t('proc.orders.form_request')) ?>
      <select name="request_id">
        <option value=""><?= e(t('proc.orders.form_select_request')) ?></option>
        <?php foreach ($requests as $r): ?>
          <option value="<?= (int)($r['id'] ?? 0) ?>"><?= e((string)($r['request_ref'] ?? '')) ?> (Qty <?= number_format((float)($r['requested_qty'] ?? 0), 2) ?>)</option>
        <?php endforeach; ?>
      </select>
    </label>
    <input type="hidden" name="po_status" value="draft">
    <input type="hidden" name="order_date" value="<?= date('Y-m-d') ?>">
    <input type="hidden" name="line_product_id" value="">
    <input type="hidden" name="ordered_qty" value="">
    <input type="hidden" name="unit_price" value="">
    <input type="hidden" name="line_status" value="open">
    <input type="hidden" name="line_description" value="">
    <div class="u-style-068a8ddc17">
      <button class="btn ok" type="submit"><?= e(t('proc.orders.btn_create')) ?></button>
    </div>
  </form>
</section>

<section class="card">
  <div class="hero-meta u-style-da12f2858b">
    <div class="hero-meta-card"><div class="muted"><?= e(t('proc.orders.total')) ?></div><div class="hero-meta-value"><?= (int)(($summary ?? [])['orders'] ?? 0) ?></div></div>
    <div class="hero-meta-card"><div class="muted"><?= e(t('proc.orders.open_orders')) ?></div><div class="hero-meta-value"><?= (int)(($summary ?? [])['open_orders'] ?? 0) ?></div></div>
  </div>

  <?php if (empty($rows)): ?>
    <p class="muted u-style-1169661891"><?= e(t('proc.orders.no_rows')) ?></p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th><?= e(t('proc.orders.col_ref')) ?></th><th><?= e(t('proc.orders.col_supplier')) ?></th><th><?= e(t('common.status')) ?></th><th><?= e(t('proc.orders.col_expected_date')) ?></th><th><?= e(t('common.actions')) ?></th></tr></thead>
        <tbody>
          <?php foreach ((array)$rows as $r): ?>
            <?php $poStatus = strtolower(trim((string)($r['po_status'] ?? 'draft'))); ?>
            <tr>
              <td><?= e((string)($r['po_ref'] ?? '')) ?></td>
              <td>
                <?php $supplierName = trim((string)($r['supplier_name'] ?? '')); ?>
                <?= $supplierName !== '' ? e($supplierName) : ('#' . (int)($r['supplier_id'] ?? 0)) ?>
              </td>
              <td><span class="<?= e($chipClass((string)($r['po_status'] ?? ''))) ?>"><?= e((string)($r['po_status'] ?? '')) ?></span></td>
              <td><?= e((string)($r['expected_date'] ?? '')) ?></td>
              <td>
                <?php if (in_array($poStatus, ['draft', 'approved'], true)): ?>
                  <form class="u-style-1169661891" method="post" action="/apps/procurement/orders/issue">
                    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
                    <input type="hidden" name="id" value="<?= (int)($r['id'] ?? 0) ?>">
                    <button class="btn" type="submit"><?= e(t('proc.orders.btn_issue')) ?></button>
                  </form>
                <?php else: ?>
                  <span class="muted">-</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

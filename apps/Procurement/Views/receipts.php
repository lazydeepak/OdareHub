<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<?php $orders = is_array($orders ?? null) ? $orders : []; ?>

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
      <h2 class="u-style-1169661891"><?= e(t('proc.receipts.title')) ?></h2>
      <div class="muted u-style-fe7b4979fe"><?= e(t('proc.receipts.subtitle')) ?></div>
    </div>
    <div class="row u-style-4725bb7117">
      <a class="btn" href="/apps/procurement"><?= e(t('proc.receipts.link_dashboard')) ?></a>
      <a class="btn" href="/apps/procurement/orders"><?= e(t('proc.receipts.link_orders')) ?></a>
    </div>
  </div>
</section>

<section class="card">
  <h3 class="u-style-d462248a40"><?= e(t('proc.receipts.create_title')) ?></h3>
  <form method="post" action="/apps/procurement/receipts/create" class="grid u-style-1a42931a01">
    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
    <label><?= e(t('proc.receipts.form_po_ref')) ?>
      <select name="po_id" required>
        <option value="">-</option>
        <?php foreach ($orders as $o): ?>
          <option value="<?= (int)($o['id'] ?? 0) ?>"><?= e((string)($o['po_ref'] ?? '')) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label><?= e(t('proc.receipts.form_received_qty')) ?><input class="input" type="number" name="received_qty" min="0.01" step="0.01" required></label>
    <label><?= e(t('proc.receipts.form_received_date')) ?><input class="input" type="date" name="receipt_date"></label>
    <input type="hidden" name="receipt_ref" value="">
    <input type="hidden" name="po_line_id" value="">
    <input type="hidden" name="receipt_status" value="received">
    <input type="hidden" name="note" value="">
    <div class="u-style-068a8ddc17">
      <button class="btn ok" type="submit"><?= e(t('proc.receipts.btn_create')) ?></button>
    </div>
  </form>
</section>

<section class="card">
  <div class="hero-meta u-style-da12f2858b">
    <div class="hero-meta-card"><div class="muted"><?= e(t('proc.receipts.total')) ?></div><div class="hero-meta-value"><?= (int)(($summary ?? [])['receipts'] ?? 0) ?></div></div>
  </div>

  <?php if (empty($rows)): ?>
    <p class="muted u-style-1169661891"><?= e(t('proc.receipts.no_rows')) ?></p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th><?= e(t('proc.receipts.col_po_ref')) ?></th><th class="u-style-54c2afb7ba"><?= e(t('proc.receipts.col_qty')) ?></th><th><?= e(t('proc.receipts.col_received_date')) ?></th><th><?= e(t('common.status')) ?></th><th><?= e(t('common.actions')) ?></th></tr></thead>
        <tbody>
          <?php foreach ((array)$rows as $r): ?>
            <?php $receiptStatus = strtolower(trim((string)($r['receipt_status'] ?? 'received'))); ?>
            <tr>
              <td>
                <?php $poRef = trim((string)($r['po_ref'] ?? '')); ?>
                <?= $poRef !== '' ? e($poRef) : ('#' . (int)($r['po_id'] ?? 0)) ?>
              </td>
              <td class="u-style-54c2afb7ba"><?= number_format((float)($r['received_qty'] ?? 0), 2) ?></td>
              <td><?= e((string)($r['receipt_date'] ?? '')) ?></td>
              <td><span class="<?= e($chipClass((string)($r['receipt_status'] ?? ''))) ?>"><?= e((string)($r['receipt_status'] ?? '')) ?></span></td>
              <td>
                <?php if ($receiptStatus === 'received'): ?>
                  <form class="u-style-1169661891" method="post" action="/apps/procurement/receipts/post">
                    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
                    <input type="hidden" name="id" value="<?= (int)($r['id'] ?? 0) ?>">
                    <button class="btn" type="submit"><?= e(t('proc.receipts.btn_post')) ?></button>
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

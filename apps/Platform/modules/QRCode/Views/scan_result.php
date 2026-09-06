<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$isLoggedIn = \App\Core\Auth::isLoggedIn();
$actionLinks = [];
if (!empty($product)) {
  $productId = (int)$product['id'];
  $actionLinks = [
    ['label' => 'Production Update', 'url' => '/production-entries/add?product_id=' . $productId . '&scan=1', 'class' => 'btn ok'],
    ['label' => 'Stock Update', 'url' => '/qr/stock/add?product_id=' . $productId . '&scan=1', 'class' => 'btn'],
    ['label' => 'Ledger Entry', 'url' => '/ledger/add?product_id=' . $productId . '&scan=1', 'class' => 'btn'],
    ['label' => 'QC Plan', 'url' => '/qc-plans/add?product_id=' . $productId . '&scan=1', 'class' => 'btn'],
    ['label' => 'Edit Product', 'url' => '/products/edit?id=' . $productId, 'class' => 'btn'],
  ];
}
?>
<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2>QR Scan Result</h2>
      <div class="muted">Review the scanned product and choose the next operation.</div>
    </div>
  </div>
</div>
<?php if (!empty($error ?? '')): ?>
  <div class="card notice-err"><?= e((string)$error) ?></div>
<?php elseif (!empty($product)): ?>
  <div class="card">
    <h3 class="u-style-d462248a40"><?= e((string)$product['parts_name']) ?></h3>
    <div class="muted">Part No: <strong><?= e((string)$product['parts_number']) ?></strong></div>
    <?php if (!empty($product['model'])): ?><div class="muted">Model: <?= e((string)$product['model']) ?></div><?php endif; ?>
    <?php if (!empty($product['producer'])): ?><div class="muted">Producer: <?= e((string)$product['producer']) ?></div><?php endif; ?>
  </div>
  <div class="card">
    <?php if (!$isLoggedIn): ?>
      <div class="muted u-style-761d3addb2">Sign in only when you are ready to perform an operation. The selected action will continue after login.</div>
    <?php endif; ?>
    <div class="row">
      <?php foreach ($actionLinks as $action): ?>
        <?php $target = $isLoggedIn ? (string)$action['url'] : '/login?redirect=' . rawurlencode((string)$action['url']); ?>
        <a class="<?= e((string)$action['class']) ?>" href="<?= e($target) ?>"><?= e((string)$action['label']) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

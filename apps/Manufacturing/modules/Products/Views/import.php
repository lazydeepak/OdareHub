<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2> <?= e($tt('products.import_title')) ?> </h2>
      <div class="muted"> <?= e($tt('products.columns_parts_name_label')) ?> </div>
    </div>
    <div class="module-header-actions"><a class="btn" href="/products">Back to List</a></div>
  </div>
</div>

<?php if (!empty($flash ?? '')): ?><div class="card notice-ok"><?= e((string)$flash) ?></div><?php endif; ?>
<?php if (!empty($error ?? '')): ?><div class="card notice-err"><?= e((string)$error) ?></div><?php endif; ?>

<div class="card">
  <form method="post" action="/products/import" enctype="multipart/form-data" class="form-grid">
    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
    <div class="form-field-wide">
      <label class="field-label">CSV File</label>
      <input class="input" type="file" name="csv" accept=".csv,text/csv" required>
    </div>
    <div class="form-actions">
      <button class="btn ok" type="submit"> <?= e($tt('common.import_action')) ?> </button>
      <a class="btn" href="/products"> <?= e($tt('common.cancel_action')) ?> </a>
    </div>
  </form>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

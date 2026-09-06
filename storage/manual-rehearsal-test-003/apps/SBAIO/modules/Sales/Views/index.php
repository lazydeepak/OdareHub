<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<?php
$rows = is_array($rows ?? null) ? $rows : [];
$suiteActions = [
  ['label' => t('nav.sbaio_dashboard'), 'url' => '/apps/sbaio'],
  ['label' => t('nav.sbaio_customers'), 'url' => '/apps/sbaio/customers'],
  ['label' => t('sbaio.common.export'), 'url' => '/apps/sbaio/sales/export'],
];
$suiteLinks = [['label' => t('sbaio.common.records'), 'value' => (string)count($rows)]];
$moduleFilters = [
  ['label' => t('sbaio.common.view'), 'value' => t('sbaio.sales.records')],
  ['label' => t('sbaio.common.related'), 'value' => 'Customer activity'],
];
require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_intro.php';
require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_feedback.php';
require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_filters.php';
?>
<section class="card">
  <div class="card u-mb-14">
    <h3 class="u-mt-0"><?= e(t('sbaio.sales.form_title')) ?></h3>
    <div class="muted mb-10"><?= e(t('sbaio.sales.form_desc')) ?></div>
    <form method="post" action="/apps/sbaio/sales/create" class="grid form-grid-4">
      <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
      <label>Reference<input class="input" type="text" name="sale_ref" required></label>
      <label>Customer<input class="input" type="text" name="customer_name"></label>
      <label>Amount<input class="input" type="number" name="amount" step="0.01" min="0" required></label>
      <label><?= e(t('common.status')) ?><input class="input" type="text" name="sale_status" value="open"></label>
      <label>Sale Date<input class="input" type="date" name="sale_date"></label>
      <div class="col-span-full">
        <button class="btn btn-primary" type="submit"><?= e(t('sbaio.sales.create')) ?></button>
      </div>
    </form>
  </div>
  <?php if ($rows === []): ?>
    <?php
    $emptyTitle = t('sbaio.sales.records');
    $emptyMessage = t('sbaio.sales.empty_desc');
    require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_empty.php';
    ?>
  <?php else: ?>
    <h3 class="u-mt-0"><?= e(t('sbaio.sales.records')) ?></h3>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Reference</th><th>Customer</th><th>Amount</th><th> <?= e($tt('common.status_label')) ?> </th></tr></thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><?= e((string)($row['sale_ref'] ?? '')) ?></td>
              <td><?= e((string)($row['customer_name'] ?? '')) ?></td>
              <td><?= e(number_format((float)($row['amount'] ?? 0), 2, '.', ',')) ?></td>
              <td><?= e((string)($row['sale_status'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

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
  ['label' => t('nav.sbaio_sales'), 'url' => '/apps/sbaio/sales'],
  ['label' => t('sbaio.common.export'), 'url' => '/apps/sbaio/expenses/export'],
];
$suiteLinks = [['label' => t('sbaio.common.records'), 'value' => (string)count($rows)]];
$moduleFilters = [
  ['label' => t('sbaio.common.view'), 'value' => t('sbaio.expenses.records')],
  ['label' => t('sbaio.common.sort'), 'value' => 'Newest first'],
];
require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_intro.php';
require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_feedback.php';
require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_filters.php';
?>
<section class="card">
  <div class="card u-mb-14">
    <h3 class="u-mt-0"><?= e(t('sbaio.expenses.form_title')) ?></h3>
    <div class="muted mb-10"><?= e(t('sbaio.expenses.form_desc')) ?></div>
    <form method="post" action="/apps/sbaio/expenses/create" class="grid form-grid-4">
      <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
      <label>Reference<input class="input" type="text" name="expense_ref" required></label>
      <label>Category<input class="input" type="text" name="category_name"></label>
      <label>Amount<input class="input" type="number" name="amount" step="0.01" min="0" required></label>
      <label><?= e(t('common.status')) ?><input class="input" type="text" name="expense_status" value="submitted"></label>
      <label>Expense Date<input class="input" type="date" name="expense_date"></label>
      <div class="col-span-full">
        <button class="btn btn-primary" type="submit"><?= e(t('sbaio.expenses.create')) ?></button>
      </div>
    </form>
  </div>
  <?php if ($rows === []): ?>
    <?php
    $emptyTitle = t('sbaio.expenses.records');
    $emptyMessage = t('sbaio.expenses.empty_desc');
    require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_empty.php';
    ?>
  <?php else: ?>
    <h3 class="u-mt-0"><?= e(t('sbaio.expenses.records')) ?></h3>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Reference</th><th>Category</th><th>Amount</th><th> <?= e($tt('common.status_label')) ?> </th></tr></thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><?= e((string)($row['expense_ref'] ?? '')) ?></td>
              <td><?= e((string)($row['category_name'] ?? '')) ?></td>
              <td><?= e(number_format((float)($row['amount'] ?? 0), 2, '.', ',')) ?></td>
              <td><?= e((string)($row['expense_status'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

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
  ['label' => t('sbaio.common.export'), 'url' => '/apps/sbaio/customers/export'],
];
$suiteLinks = [['label' => t('sbaio.common.records'), 'value' => (string)count($rows)]];
$moduleFilters = [
  ['label' => t('sbaio.common.view'), 'value' => t('sbaio.customers.records')],
  ['label' => t('sbaio.common.sort'), 'value' => 'Newest first'],
];
require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_intro.php';
require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_feedback.php';
require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_filters.php';
?>
<section class="card">
  <div class="card u-mb-14">
    <h3 class="u-mt-0"><?= e(t('sbaio.customers.form_title')) ?></h3>
    <div class="muted mb-10"><?= e(t('sbaio.customers.form_desc')) ?></div>
    <form method="post" action="/apps/sbaio/customers/create" class="grid form-grid-4">
      <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
      <label>Customer Name<input class="input" type="text" name="customer_name" required></label>
      <label>Contact Name<input class="input" type="text" name="contact_name"></label>
      <label><?= e(t('auth.email')) ?><input class="input" type="email" name="email"></label>
      <label>Phone<input class="input" type="text" name="phone"></label>
      <label><?= e(t('common.status')) ?><input class="input" type="text" name="status" value="active"></label>
      <div class="col-span-full">
        <button class="btn btn-primary" type="submit"><?= e(t('sbaio.customers.create')) ?></button>
      </div>
    </form>
  </div>
  <?php if ($rows === []): ?>
    <?php
    $emptyTitle = t('sbaio.customers.records');
    $emptyMessage = t('sbaio.customers.empty_desc');
    require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_empty.php';
    ?>
  <?php else: ?>
    <h3 class="u-mt-0"><?= e(t('sbaio.customers.records')) ?></h3>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Customer</th><th>Contact</th><th>Email</th><th> <?= e($tt('common.status_label')) ?> </th></tr></thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><?= e((string)($row['customer_name'] ?? '')) ?></td>
              <td><?= e((string)($row['contact_name'] ?? '')) ?></td>
              <td><?= e((string)($row['email'] ?? '')) ?></td>
              <td><?= e((string)($row['status'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<?php
$rows = is_array($rows ?? null) ? $rows : [];
$exactCustomer = is_array($exactCustomer ?? null) ? $exactCustomer : null;
$exactCustomerId = is_int($exactCustomerId ?? null) ? $exactCustomerId : null;
$exactCustomerState = is_string($exactCustomerState ?? null) ? $exactCustomerState : null;
$createdCustomerId = is_int($createdCustomerId ?? null) ? $createdCustomerId : null;
$suiteActions = [
  ['label' => t('nav.sbaio_dashboard'), 'url' => '/apps/sbaio'],
  ['label' => t('nav.sbaio_sales'), 'url' => '/apps/sbaio/sales'],
  ['label' => t('sbaio.common.export'), 'url' => '/apps/sbaio/customers/export'],
];
$suiteLinks = [['label' => t('sbaio.common.records'), 'value' => (string)count($rows)]];
$moduleFilters = [
  ['label' => t('sbaio.common.view'), 'value' => t('sbaio.customers.records')],
   ['label' => t('sbaio.common.sort'), 'value' => t('sbaio.customers.newest_first')],
];
require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_intro.php';
require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_feedback.php';
?>
<?php if ($createdCustomerId !== null): ?>
  <section class="card u-mb-14" data-sbaio-customers-created-record="1">
    <a class="btn btn-primary" href="/apps/sbaio/customers?customer_id=<?= e((string)$createdCustomerId) ?>"><?= e(t('sbaio.customers.open_created_record')) ?></a>
  </section>
<?php endif; ?>
<?php if ($exactCustomerState !== null): ?>
  <section class="card u-mb-14" data-sbaio-customers-exact-record="1">
    <?php if ($exactCustomerState === 'invalid'): ?>
      <p class="muted" role="alert"><?= e(t('sbaio.customers.record_id_invalid')) ?></p>
    <?php elseif ($exactCustomerState === 'unavailable'): ?>
      <p class="muted" role="alert"><?= e(t('sbaio.customers.record_unavailable')) ?></p>
    <?php elseif ($exactCustomerState === 'not_found'): ?>
      <p class="muted" role="alert"><?= e(t('sbaio.customers.record_not_found')) ?></p>
    <?php elseif ($exactCustomer !== null): ?>
      <h3 class="u-mt-0"><?= e(t('sbaio.customers.record_title')) ?></h3>
      <dl>
        <dt><?= e(t('sbaio.customers.record_id')) ?></dt><dd><?= e((string)$exactCustomerId) ?></dd>
        <dt><?= e(t('sbaio.customers.record_customer_name')) ?></dt><dd><?= e((string)($exactCustomer['customer_name'] ?? '')) ?></dd>
        <dt><?= e(t('sbaio.customers.record_contact_name')) ?></dt><dd><?= e((string)($exactCustomer['contact_name'] ?? '')) ?></dd>
        <dt><?= e(t('sbaio.customers.record_email')) ?></dt><dd><?= e((string)($exactCustomer['email'] ?? '')) ?></dd>
        <dt><?= e(t('sbaio.customers.record_phone')) ?></dt><dd><?= e((string)($exactCustomer['phone'] ?? '')) ?></dd>
        <dt><?= e(t('sbaio.customers.record_status')) ?></dt><dd><?= e((string)($exactCustomer['status'] ?? '')) ?></dd>
      </dl>
      <a class="btn" href="/apps/sbaio/customers?customer_id=<?= e((string)$exactCustomerId) ?>"><?= e(t('sbaio.customers.open_record')) ?></a>
    <?php endif; ?>
  </section>
<?php endif; ?>
<?php require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_filters.php'; ?>
<section class="card">
  <div class="card u-mb-14">
    <h3 class="u-mt-0"><?= e(t('sbaio.customers.form_title')) ?></h3>
    <div class="muted mb-10"><?= e(t('sbaio.customers.form_desc')) ?></div>
    <form method="post" action="/apps/sbaio/customers/create" class="grid form-grid-4">
      <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
      <label><?= e(t('sbaio.customers.customer_name')) ?><input class="input" type="text" name="customer_name" required></label>
      <label><?= e(t('sbaio.customers.contact_name')) ?><input class="input" type="text" name="contact_name"></label>
      <label><?= e(t('auth.email')) ?><input class="input" type="email" name="email"></label>
      <label><?= e(t('sbaio.customers.phone')) ?><input class="input" type="text" name="phone"></label>
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
       <thead><tr><th><?= e(t('sbaio.customers.customer_name')) ?></th><th><?= e(t('sbaio.customers.contact_name')) ?></th><th><?= e(t('auth.email')) ?></th><th> <?= e($tt('common.status_label')) ?> </th></tr></thead>
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

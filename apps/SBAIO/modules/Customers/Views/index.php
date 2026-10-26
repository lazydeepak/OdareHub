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
$currentQuery = is_array($currentQuery ?? null) ? $currentQuery : [];
$searchResult = is_array($searchResult ?? null) ? $searchResult : null;
$nextSearchUrl = null;
if (is_string($searchResult['next_cursor'] ?? null) && $searchResult['next_cursor'] !== '') {
  $nextParams = ['customer_search' => '1', 'customer_cursor' => $searchResult['next_cursor']];
  foreach ([
    'customer_name' => 'customer_name_exact',
    'email' => 'customer_email_exact',
    'phone' => 'customer_phone_exact',
    'status' => 'customer_status_exact',
    'from' => 'customer_from',
    'to' => 'customer_to',
  ] as $filterKey => $queryKey) {
    if (is_string($searchResult['filters'][$filterKey] ?? null) && $searchResult['filters'][$filterKey] !== '') {
      $nextParams[$queryKey] = $searchResult['filters'][$filterKey];
    }
  }
  $nextSearchUrl = '/apps/sbaio/customers?' . http_build_query($nextParams, '', '&', PHP_QUERY_RFC3986);
}
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
<section class="card u-mb-14" data-sbaio-customers-search="1">
  <h3 class="u-mt-0"><?= e(t('sbaio.customers.search_title')) ?></h3>
  <div class="muted mb-10"><?= e(t('sbaio.customers.search_note')) ?></div>
  <form method="get" action="/apps/sbaio/customers" class="grid form-grid-4">
    <input type="hidden" name="customer_search" value="1">
    <label><?= e(t('sbaio.customers.search_name')) ?><input class="input" type="text" name="customer_name_exact" maxlength="160" value="<?= e(is_string($currentQuery['customer_name_exact'] ?? null) ? $currentQuery['customer_name_exact'] : '') ?>"></label>
    <label><?= e(t('sbaio.customers.search_email')) ?><input class="input" type="email" name="customer_email_exact" maxlength="254" value="<?= e(is_string($currentQuery['customer_email_exact'] ?? null) ? $currentQuery['customer_email_exact'] : '') ?>"></label>
    <label><?= e(t('sbaio.customers.search_phone')) ?><input class="input" type="text" name="customer_phone_exact" maxlength="80" value="<?= e(is_string($currentQuery['customer_phone_exact'] ?? null) ? $currentQuery['customer_phone_exact'] : '') ?>"></label>
    <label><?= e(t('sbaio.customers.search_status')) ?><input class="input" type="text" name="customer_status_exact" maxlength="40" value="<?= e(is_string($currentQuery['customer_status_exact'] ?? null) ? $currentQuery['customer_status_exact'] : '') ?>"></label>
    <label><?= e(t('sbaio.customers.search_from')) ?><input class="input" type="date" name="customer_from" value="<?= e(is_string($currentQuery['customer_from'] ?? null) ? $currentQuery['customer_from'] : '') ?>"></label>
    <label><?= e(t('sbaio.customers.search_to')) ?><input class="input" type="date" name="customer_to" value="<?= e(is_string($currentQuery['customer_to'] ?? null) ? $currentQuery['customer_to'] : '') ?>"></label>
    <div class="col-span-full"><button class="btn" type="submit"><?= e(t('sbaio.customers.search_submit')) ?></button></div>
  </form>
  <?php if ($searchResult !== null): ?>
    <?php if (($searchResult['valid'] ?? false) !== true): ?>
      <p class="muted" role="alert"><?= e(t('sbaio.customers.search_invalid')) ?></p>
    <?php elseif (($searchResult['available'] ?? false) !== true): ?>
      <p class="muted" role="alert"><?= e(t('sbaio.customers.search_unavailable')) ?></p>
    <?php elseif ($rows === []): ?>
      <p class="muted"><?= e(t('sbaio.customers.search_no_results')) ?></p>
    <?php else: ?>
      <div class="table-wrap"><table><thead><tr><th><?= e(t('sbaio.customers.record_id')) ?></th><th><?= e(t('sbaio.customers.customer_name')) ?></th><th><?= e(t('sbaio.customers.contact_name')) ?></th><th><?= e(t('auth.email')) ?></th><th><?= e(t('sbaio.customers.phone')) ?></th><th><?= e(t('common.status_label')) ?></th><th><?= e(t('sbaio.customers.search_created')) ?></th></tr></thead><tbody>
        <?php foreach ($rows as $row): ?><tr>
          <td><a href="/apps/sbaio/customers?customer_id=<?= e((string)($row['id'] ?? '')) ?>"><?= e((string)($row['id'] ?? '')) ?></a></td>
          <td><?= e((string)($row['customer_name'] ?? '')) ?></td>
          <td><?= e((string)($row['contact_name'] ?? '')) ?></td>
          <td><?= e((string)($row['email'] ?? '')) ?></td>
          <td><?= e((string)($row['phone'] ?? '')) ?></td>
          <td><?= e((string)($row['status'] ?? '')) ?></td>
          <td><?= e((string)($row['created_at'] ?? '')) ?></td>
        </tr><?php endforeach; ?>
      </tbody></table></div>
      <?php if (($searchResult['truncated'] ?? false) === true): ?>
        <p class="muted"><?= e(str_replace('{limit}', (string)($searchResult['limit'] ?? 50), t('sbaio.customers.search_limited'))) ?><?php if ($nextSearchUrl !== null): ?> <a href="<?= e($nextSearchUrl) ?>"><?= e(t('sbaio.customers.search_next')) ?></a><?php endif; ?></p>
      <?php endif; ?>
    <?php endif; ?>
  <?php endif; ?>
</section>
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
  <?php if ($searchResult !== null): ?>
  <?php elseif ($rows === []): ?>
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

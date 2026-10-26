<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<?php
$rows = is_array($rows ?? null) ? $rows : [];
$currentQuery = is_array($currentQuery ?? null) ? $currentQuery : [];
$searchResult = is_array($searchResult ?? null) ? $searchResult : null;
$saleStatusOptions = is_array($saleStatusOptions ?? null) ? $saleStatusOptions : [];
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
  <div class="card u-mb-14" data-sbaio-sales-search="1">
    <h3 class="u-mt-0"><?= e(t('sbaio.sales.search_title')) ?></h3>
    <div class="muted mb-10"><?= e(t('sbaio.sales.search_note')) ?></div>
    <form method="get" action="/apps/sbaio/sales" class="grid form-grid-4">
      <input type="hidden" name="sales_search" value="1">
      <label><?= e(t('sbaio.sales.search_reference')) ?><input class="input" type="text" name="sale_ref" maxlength="80" value="<?= e(is_string($currentQuery['sale_ref'] ?? null) ? $currentQuery['sale_ref'] : '') ?>"></label>
      <label><?= e(t('sbaio.sales.search_status')) ?><select class="input" name="sale_status">
        <option value=""><?= e(t('sbaio.sales.search_all_statuses')) ?></option>
        <?php foreach ($saleStatusOptions as $status): ?>
        <option value="<?= e((string)$status) ?>"<?= (($currentQuery['sale_status'] ?? '') === $status) ? ' selected' : '' ?>><?= e((string)$status) ?></option>
        <?php endforeach; ?>
      </select></label>
      <label><?= e(t('sbaio.sales.search_from')) ?><input class="input" type="date" name="sale_from" value="<?= e(is_string($currentQuery['sale_from'] ?? null) ? $currentQuery['sale_from'] : '') ?>"></label>
      <label><?= e(t('sbaio.sales.search_to')) ?><input class="input" type="date" name="sale_to" value="<?= e(is_string($currentQuery['sale_to'] ?? null) ? $currentQuery['sale_to'] : '') ?>"></label>
      <div class="col-span-full"><button class="btn" type="submit"><?= e(t('sbaio.sales.search_submit')) ?></button></div>
    </form>
    <?php if ($searchResult !== null): ?>
      <?php if (($searchResult['valid'] ?? false) !== true): ?>
        <p class="muted" role="alert"><?= e(t('sbaio.sales.search_invalid')) ?></p>
      <?php elseif (($searchResult['available'] ?? false) !== true): ?>
        <p class="muted" role="alert"><?= e(t('sbaio.sales.search_unavailable')) ?></p>
      <?php elseif ($rows === []): ?>
        <p class="muted"><?= e(t('sbaio.sales.search_no_results')) ?></p>
      <?php else: ?>
        <div class="table-wrap"><table><thead><tr><th><?= e(t('sbaio.sales.search_reference')) ?></th><th><?= e(t('sbaio.sales.search_customer')) ?></th><th><?= e(t('sbaio.sales.search_sale_date')) ?></th><th><?= e(t('sbaio.sales.search_amount')) ?></th><th><?= e(t('common.status_label')) ?></th></tr></thead><tbody>
          <?php foreach ($rows as $row): ?><tr>
            <td><?= e((string)($row['sale_ref'] ?? '')) ?></td>
            <td><?= e((string)($row['customer_name'] ?? '')) ?></td>
            <td><?= e((string)($row['sale_date'] ?? '')) ?></td>
            <td><?= e(number_format((float)($row['amount'] ?? 0), 2, '.', ',')) ?></td>
            <td><?= e((string)($row['sale_status'] ?? '')) ?></td>
          </tr><?php endforeach; ?>
        </tbody></table></div>
        <?php if (($searchResult['truncated'] ?? false) === true): ?><p class="muted"><?= e(str_replace('{limit}', (string)($searchResult['limit'] ?? 50), t('sbaio.sales.search_limited'))) ?></p><?php endif; ?>
      <?php endif; ?>
    <?php endif; ?>
  </div>
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

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
$exactExpense = is_array($exactExpense ?? null) ? $exactExpense : null;
$exactExpenseId = is_int($exactExpenseId ?? null) ? $exactExpenseId : null;
$exactExpenseState = is_string($exactExpenseState ?? null) ? $exactExpenseState : null;
$nextSearchUrl = null;
if (is_string($searchResult['next_cursor'] ?? null) && $searchResult['next_cursor'] !== '') {
  $nextParams = ['expenses_search' => '1', 'expenses_cursor' => $searchResult['next_cursor']];
  foreach ([
    'expense_ref' => 'expense_ref',
    'status' => 'expense_status',
    'from' => 'expense_from',
    'to' => 'expense_to',
  ] as $filterKey => $queryKey) {
    if (is_string($searchResult['filters'][$filterKey] ?? null) && $searchResult['filters'][$filterKey] !== '') {
      $nextParams[$queryKey] = $searchResult['filters'][$filterKey];
    }
  }
  $nextSearchUrl = '/apps/sbaio/expenses?' . http_build_query($nextParams, '', '&', PHP_QUERY_RFC3986);
}
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
<?php if ($exactExpenseState !== null): ?>
  <section class="card u-mb-14" data-sbaio-expenses-exact-record="1">
    <?php if ($exactExpenseState === 'invalid'): ?>
      <p class="muted" role="alert"><?= e(t('sbaio.expenses.record_id_invalid')) ?></p>
    <?php elseif ($exactExpenseState === 'unavailable'): ?>
      <p class="muted" role="alert"><?= e(t('sbaio.expenses.record_unavailable')) ?></p>
    <?php elseif ($exactExpenseState === 'not_found'): ?>
      <p class="muted" role="alert"><?= e(t('sbaio.expenses.record_not_found')) ?></p>
    <?php elseif ($exactExpense !== null): ?>
      <h3 class="u-mt-0"><?= e(t('sbaio.expenses.record_title')) ?></h3>
      <dl>
        <dt><?= e(t('sbaio.expenses.record_id')) ?></dt><dd><?= e((string)$exactExpenseId) ?></dd>
        <dt><?= e(t('sbaio.expenses.record_reference')) ?></dt><dd><?= e((string)($exactExpense['expense_ref'] ?? '')) ?></dd>
        <dt><?= e(t('sbaio.expenses.record_category')) ?></dt><dd><?= e((string)($exactExpense['category_name'] ?? '')) ?></dd>
        <dt><?= e(t('sbaio.expenses.record_date')) ?></dt><dd><?= e((string)($exactExpense['expense_date'] ?? '')) ?></dd>
        <dt><?= e(t('sbaio.expenses.record_amount')) ?></dt><dd><?= e(number_format((float)($exactExpense['amount'] ?? 0), 2, '.', ',')) ?></dd>
        <dt><?= e(t('sbaio.expenses.record_status')) ?></dt><dd><?= e((string)($exactExpense['expense_status'] ?? '')) ?></dd>
      </dl>
      <a class="btn" href="/apps/sbaio/expenses?expense_id=<?= e((string)$exactExpenseId) ?>"><?= e(t('sbaio.expenses.open_record')) ?></a>
    <?php endif; ?>
  </section>
<?php endif; ?>
<section class="card">
  <div class="card u-mb-14" data-sbaio-expenses-search="1">
    <h3 class="u-mt-0"><?= e(t('sbaio.expenses.search_title')) ?></h3>
    <div class="muted mb-10"><?= e(t('sbaio.expenses.search_note')) ?></div>
    <form method="get" action="/apps/sbaio/expenses" class="grid form-grid-4">
      <input type="hidden" name="expenses_search" value="1">
      <label><?= e(t('sbaio.expenses.search_reference')) ?><input class="input" type="text" name="expense_ref" maxlength="80" value="<?= e(is_string($currentQuery['expense_ref'] ?? null) ? $currentQuery['expense_ref'] : '') ?>"></label>
      <label><?= e(t('sbaio.expenses.search_status')) ?><input class="input" type="text" name="expense_status" maxlength="40" value="<?= e(is_string($currentQuery['expense_status'] ?? null) ? $currentQuery['expense_status'] : '') ?>"></label>
      <label><?= e(t('sbaio.expenses.search_from')) ?><input class="input" type="date" name="expense_from" value="<?= e(is_string($currentQuery['expense_from'] ?? null) ? $currentQuery['expense_from'] : '') ?>"></label>
      <label><?= e(t('sbaio.expenses.search_to')) ?><input class="input" type="date" name="expense_to" value="<?= e(is_string($currentQuery['expense_to'] ?? null) ? $currentQuery['expense_to'] : '') ?>"></label>
      <div class="col-span-full"><button class="btn" type="submit"><?= e(t('sbaio.expenses.search_submit')) ?></button></div>
    </form>
    <?php if ($searchResult !== null): ?>
      <?php if (($searchResult['valid'] ?? false) !== true): ?>
        <p class="muted" role="alert"><?= e(t('sbaio.expenses.search_invalid')) ?></p>
      <?php elseif (($searchResult['available'] ?? false) !== true): ?>
        <p class="muted" role="alert"><?= e(t('sbaio.expenses.search_unavailable')) ?></p>
      <?php elseif ($rows === []): ?>
        <p class="muted"><?= e(t('sbaio.expenses.search_no_results')) ?></p>
      <?php else: ?>
        <div class="table-wrap"><table><thead><tr><th><?= e(t('sbaio.expenses.search_id')) ?></th><th><?= e(t('sbaio.expenses.search_reference')) ?></th><th><?= e(t('sbaio.expenses.search_date')) ?></th><th><?= e(t('sbaio.expenses.search_amount')) ?></th><th><?= e(t('common.status_label')) ?></th></tr></thead><tbody>
          <?php foreach ($rows as $row): ?><tr>
            <td><a href="/apps/sbaio/expenses?expense_id=<?= e((string)($row['id'] ?? '')) ?>"><?= e((string)($row['id'] ?? '')) ?></a></td>
            <td><?= e((string)($row['expense_ref'] ?? '')) ?></td>
            <td><?= e((string)($row['expense_date'] ?? '')) ?></td>
            <td><?= e(number_format((float)($row['amount'] ?? 0), 2, '.', ',')) ?></td>
            <td><?= e((string)($row['expense_status'] ?? '')) ?></td>
          </tr><?php endforeach; ?>
        </tbody></table></div>
        <?php if (($searchResult['truncated'] ?? false) === true): ?>
          <p class="muted"><?= e(str_replace('{limit}', (string)($searchResult['limit'] ?? 50), t('sbaio.expenses.search_limited'))) ?><?php if ($nextSearchUrl !== null): ?> <a href="<?= e($nextSearchUrl) ?>"><?= e(t('sbaio.expenses.search_next')) ?></a><?php endif; ?></p>
        <?php endif; ?>
      <?php endif; ?>
    <?php endif; ?>
  </div>
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

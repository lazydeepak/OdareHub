<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<div class="card">
  <div class="row u-style-2d7d2729a4">
    <div class="ui-block">
      <h2 class="u-style-1169661891"> <?= e($tt('daily_orders.import_title')) ?> </h2>
      <div class="muted">Upload a CSV or Excel (.xlsx) file to bulk-create daily orders.</div>
    </div>
    <a class="btn" href="/daily-orders">Back to Daily Orders</a>
  </div>
</div>

<?php if (!empty($error ?? '')): ?><div class="card u-style-64e7e2c551"><?= e((string)$error) ?></div><?php endif; ?>

<div class="card">
  <form method="post" action="/daily-orders/import" enctype="multipart/form-data" class="row u-style-9bc05614aa">
    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
    <div class="u-style-1e9412f972">
      <label class="muted u-style-98847c28df">Import File (.csv or .xlsx)</label>
      <input class="input" type="file" name="import_file" accept=".csv,.xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" required>
    </div>
    <div class="row">
      <button class="btn ok" type="submit"> <?= e($tt('daily_orders.import_action')) ?> </button>
    </div>
  </form>
</div>

<div class="card">
  <h3 class="u-style-d462248a40"> <?= e($tt('daily_orders.required_columns_title')) ?> </h3>
  <div class="muted"> <?= e($tt('daily_orders.headers_are_case_label')) ?> <strong>order_date</strong>, <strong>customer_name</strong>, <strong>qty</strong>, and one product identifier: <strong>product_id</strong> or <strong>parts_number</strong> or <strong>parts_name</strong>.</div>
  <div class="muted u-style-8a77e5a311"> <?= e($tt('daily_orders.import_description')) ?> </div>
  <div class="table-wrap u-style-56f4356299">
    <table>
      <thead>
        <tr>
          <th>order_date</th>
          <th>customer_name</th>
          <th>parts_number</th>
          <th>qty</th>
          <th>status</th>
          <th>notes</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>2026-03-29</td>
          <td>ABC Manufacturing</td>
          <td>P-1001</td>
          <td>150</td>
          <td>Open</td>
          <td>Urgent dispatch</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

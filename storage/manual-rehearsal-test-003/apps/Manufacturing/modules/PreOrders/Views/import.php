<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<div class="card">
  <div class="row u-style-2d7d2729a4">
    <div class="ui-block">
      <h2 class="u-style-1169661891"> <?= e($tt('pre_orders.import_title')) ?> </h2>
      <div class="muted">Upload a CSV or Excel (.xlsx) file to bulk-create pre orders.</div>
    </div>
    <a class="btn" href="/pre-orders">Back to Pre Orders</a>
  </div>
</div>

<?php if (!empty($error ?? '')): ?><div class="card u-style-64e7e2c551"><?= e((string)$error) ?></div><?php endif; ?>

<div class="card">
  <form method="post" action="/pre-orders/import" enctype="multipart/form-data" class="row u-style-9bc05614aa">
    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
    <div class="u-style-1e9412f972">
      <label class="muted u-style-98847c28df">Import File (.csv or .xlsx)</label>
      <input class="input" type="file" name="import_file" accept=".csv,.xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" required>
    </div>
    <div class="row">
      <button class="btn ok" type="submit"> <?= e($tt('pre_orders.import_title')) ?> </button>
    </div>
  </form>
</div>

<div class="card">
  <h3 class="u-style-d462248a40"> <?= e($tt('pre_orders.required_columns_title')) ?> </h3>
  <div class="muted"> <?= e($tt('pre_orders.headers_are_case_label')) ?> <strong>forecast_type</strong>, <strong>planned_qty</strong>, and one product identifier: <strong>product_id</strong> or <strong>parts_number</strong> or <strong>parts_name</strong>.</div>
  <div class="muted u-style-8a77e5a311">Optional: planning_priority, balance_qty, required_date, notes.</div>
  <div class="table-wrap u-style-56f4356299">
    <table>
      <thead>
        <tr>
          <th>forecast_type</th>
          <th>planning_priority</th>
          <th>parts_number</th>
          <th>planned_qty</th>
          <th>required_date</th>
          <th>notes</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>Monthly</td>
          <td>High</td>
          <td>P-1001</td>
          <td>500</td>
          <td>2026-04-05</td>
          <td>Forecast batch import</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

<?php
$tt = static function (string $key): string {
  return t($key);
};
$rows = is_array($rows ?? null) ? $rows : [];
$viewportState = is_array($viewport_state ?? null) ? $viewport_state : [];
$viewportSummary = is_array($viewport_summary ?? null) ? $viewport_summary : [];
$viewportGroups = is_array($viewportSummary['groups'] ?? null) ? $viewportSummary['groups'] : [];
$futureModes = is_array($viewportState['future_modes'] ?? null) ? $viewportState['future_modes'] : [];
$currentViewport = is_array($viewportState['current'] ?? null) ? $viewportState['current'] : [];

require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php';
?>

<div class="card">
  <div class="row u-style-ff6d153471">
    <div class="ui-block">
      <h2 class="u-style-1169661891"><?= e(t('module.pre_orders.title')) ?></h2>
      <div class="muted"><?= e(t('module.pre_orders.subtitle')) ?></div>
    </div>
    <div class="row u-style-4725bb7117">
      <a class="btn" href="/pre-orders/import"> <?= e($tt('pre_orders.import_link')) ?> </a>
      <a class="btn ok" href="/pre-orders/add"><?= e(t('module.pre_orders.add')) ?></a>
    </div>
  </div>
</div>

<?php if (!empty($flash ?? '')): ?>
  <div class="card u-style-a25b37e313"><?= e((string)$flash) ?></div>
<?php endif; ?>
<?php if (!empty($error ?? '')): ?>
  <div class="card u-style-64e7e2c551"><?= e((string)$error) ?></div>
<?php endif; ?>

<div class="card">
  <div class="section-head">
    <div class="ui-block">
      <h3 class="u-style-1169661891">Planning Viewport</h3>
      <div class="muted"><?= e((string)($currentViewport['description'] ?? 'Detailed pre-order handling stays here.')) ?></div>
    </div>
    <div class="row u-style-ca6f5db40b">
      <span class="pill">Current: <?= e((string)($currentViewport['label'] ?? 'All Pre Orders')) ?></span>
      <?php foreach ($futureModes as $mode): ?>
        <span class="pill u-style-df4d144e7b"><?= e((string)($mode['label'] ?? 'Viewport')) ?></span>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="coverage-kpi-grid u-style-56f4356299">
    <div class="coverage-kpi">
      <div class="muted">Visible Pre Orders</div>
      <div class="coverage-kpi-value"><?= number_format((int)($viewportSummary['total_rows'] ?? count($rows))) ?></div>
      <div class="muted u-style-96ad6099e2">Planned Qty <?= number_format((float)($viewportSummary['total_planned_qty'] ?? 0), 0) ?></div>
    </div>
    <?php foreach ($viewportGroups as $group): ?>
      <div class="coverage-kpi">
        <div class="muted"><?= e((string)($group['label'] ?? 'Group')) ?></div>
        <div class="coverage-kpi-value"><?= number_format((int)($group['count'] ?? 0)) ?></div>
        <div class="muted u-style-96ad6099e2">Balance Qty <?= number_format((float)($group['balance_qty'] ?? 0), 0) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="card">
  <form method="get" action="/pre-orders" class="row u-style-cc3725f50d">
    <div class="u-style-471dcf6cc8">
      <label class="muted u-style-98847c28df"><?= e(t('common.search')) ?></label>
      <input class="input" name="q" value="<?= e((string)$q) ?>" placeholder="<?= e(t('module.pre_orders.search_placeholder')) ?>">
    </div>
    <div class="u-style-eb62184e30">
      <label class="muted u-style-98847c28df"><?= e(t('common.priority')) ?></label>
      <select name="priority">
        <option value=""><?= e(t('common.all')) ?></option>
        <option value="Critical" <?= ((string)$priority === 'Critical') ? 'selected' : '' ?>>Critical</option>
        <option value="High" <?= ((string)$priority === 'High') ? 'selected' : '' ?>>High</option>
        <option value="Medium" <?= ((string)$priority === 'Medium') ? 'selected' : '' ?>>Medium</option>
        <option value="Normal" <?= ((string)$priority === 'Normal') ? 'selected' : '' ?>>Normal</option>
        <option value="Low" <?= ((string)$priority === 'Low') ? 'selected' : '' ?>>Low</option>
      </select>
    </div>
    <div class="row u-style-4725bb7117">
      <button class="btn" type="submit"><?= e(t('common.filter')) ?></button>
      <a class="btn" href="/pre-orders"><?= e(t('common.reset')) ?></a>
    </div>
  </form>
</div>

<div class="card">
  <div class="section-head">
    <div class="ui-block">
      <h3 class="u-style-1169661891">Pre Order List</h3>
      <div class="muted"> <?= e($tt('pre_orders.description')) ?> </div>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th><?= e(t('common.id')) ?></th>
          <th><?= e(t('module.pre_orders.forecast_type')) ?></th>
          <th><?= e(t('module.pre_orders.planning_priority')) ?></th>
          <th><?= e(t('common.part')) ?></th>
          <th><?= e(t('module.pre_orders.planned_qty')) ?></th>
          <th><?= e(t('module.pre_orders.balance_qty')) ?></th>
          <th><?= e(t('common.required_date')) ?></th>
          <th><?= e(t('common.actions')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= (int)($r['id'] ?? 0) ?></td>
            <td><?= e((string)($r['forecast_type'] ?? '')) ?></td>
            <td><?= e((string)($r['planning_priority'] ?? '')) ?></td>
            <td>
              <?= e((string)($r['parts_name'] ?? '')) ?>
              <span class="muted">(<?= e((string)($r['parts_number'] ?? '')) ?>)</span>
            </td>
            <td><?= e((string)($r['planned_qty'] ?? '0')) ?></td>
            <td><?= e((string)($r['balance_qty'] ?? '0')) ?></td>
            <td><?= e((string)($r['required_date'] ?? '')) ?></td>
            <td>
              <div class="row u-style-4725bb7117">
                <a class="btn" href="/pre-orders/edit?id=<?= (int)($r['id'] ?? 0) ?>"><?= e(t('common.edit')) ?></a>
                <form method="post" action="/pre-orders/delete" onsubmit="return confirm('<?= e(t('module.pre_orders.delete_confirm')) ?>');" style="margin:0">
                  <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
                  <input type="hidden" name="id" value="<?= (int)($r['id'] ?? 0) ?>">
                  <button class="btn danger" type="submit"><?= e(t('common.delete')) ?></button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if ($rows === []): ?>
          <tr>
            <td colspan="8" class="muted"><?= e(t('module.pre_orders.none')) ?></td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

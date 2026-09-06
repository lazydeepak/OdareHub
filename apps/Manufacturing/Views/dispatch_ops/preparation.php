<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$products = isset($products) && is_array($products) ? $products : [];
$orders = isset($orders) && is_array($orders) ? $orders : [];
$selectedOrder = isset($selected_order) && is_array($selected_order) ? $selected_order : null;
$form = isset($form) && is_array($form) ? $form : [];
$history = isset($history) && is_array($history) ? $history : [];
$workflowHints = isset($workflow_hints) && is_array($workflow_hints) ? $workflow_hints : null;
$dispatchDate = (string)($dispatch_date ?? date('Y-m-d'));
$productId = (int)($product_id ?? 0);
$dailyOrderId = (int)($daily_order_id ?? 0);
$flash = isset($flash) ? (string)$flash : '';
$error = isset($error) ? (string)$error : '';

$viewer = \App\Core\Auth::user();
$viewerCtx = [];
if (class_exists('\\Plugins\\Base\\Services\\UserDashboardAssignmentService')) {
  $viewerCtx = \Plugins\Base\Services\UserDashboardAssignmentService::resolveUserContext(is_array($viewer) ? $viewer : []);
}
$viewerTokens = array_values(array_filter(array_map(
  static fn($token): string => strtolower(trim((string)$token)),
  array_merge((array)($viewerCtx['access_profiles'] ?? []), (array)($viewerCtx['duty_codes'] ?? []))
), static fn(string $token): bool => $token !== ''));
$isReadOnlyView = in_array('read_only', $viewerTokens, true) || in_array('display', $viewerTokens, true);

$canSave = !$isReadOnlyView && !($workflowHints !== null && !(bool)($workflowHints['allowed'] ?? false));
?>

<div class="card">
  <div class="mfg-page-header">
    <div class="ui-block">
      <h2 class="mfg-page-title"><?= e((string)t('mfg.dsp.action.prep_form')) ?></h2>
      <div class="muted"><?= e((string)t('mfg.dsp.focus.action_hint')) ?></div>
    </div>
    <div class="mfg-page-header-links">
      <a class="btn" href="/apps/manufacturing/dispatch-ops"><?= e((string)t('mfg.portal.action.dispatch_ops')) ?></a>
      <a class="btn" href="/apps/manufacturing/stage-board"><?= e((string)t('mfg.portal.action.stage_board')) ?></a>
      <a class="btn" href="/apps/manufacturing/assembly-plans"><?= e((string)t('mfg.qc.link.assembly_plans')) ?></a>
    </div>
  </div>
</div>

<?php if ($flash !== ''): ?><div class="card dsp-flash-ok"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="card dsp-flash-err"><?= e($error) ?></div><?php endif; ?>

<div class="card">
  <form method="get" action="/apps/manufacturing/dispatch-ops/preparation" class="mfg-filter-row">
    <div class="ui-block">
      <label class="muted mfg-filter-label"><?= e((string)t('common.date')) ?></label>
      <input class="input" type="date" name="dispatch_date" value="<?= e($dispatchDate) ?>">
    </div>
    <div class="ui-block">
      <label class="muted mfg-filter-label"><?= e((string)t('mfg.dsp.filter.part')) ?></label>
      <select name="product_id">
        <option value="0"><?= e((string)t('mfg.dsp.filter.all_parts')) ?></option>
        <?php foreach ($products as $product): ?>
          <option value="<?= (int)($product['id'] ?? 0) ?>" <?= $productId === (int)($product['id'] ?? 0) ? 'selected' : '' ?>>
            <?= e((string)($product['parts_name'] ?? '')) ?> (<?= e((string)($product['parts_number'] ?? '')) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="ui-block">
      <label class="muted mfg-filter-label"><?= e((string)t('ops.supervisor_cockpit.order')) ?></label>
      <select name="daily_order_id">
        <option value="0"><?= e((string)t('common.select')) ?></option>
        <?php foreach ($orders as $order): ?>
          <option value="<?= (int)($order['id'] ?? 0) ?>" <?= $dailyOrderId === (int)($order['id'] ?? 0) ? 'selected' : '' ?>>
            #<?= (int)($order['id'] ?? 0) ?> · <?= e((string)($order['customer_name'] ?? '')) ?> · <?= e((string)t('mfg.dsp.col.qty')) ?> <?= e((string)($order['qty'] ?? 0)) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="row">
      <button class="btn" type="submit"><?= e((string)t('common.filter')) ?></button>
      <a class="btn" href="/apps/manufacturing/dispatch-ops/preparation"><?= e((string)t('common.reset')) ?></a>
    </div>
  </form>
</div>

<?php if ($selectedOrder): ?>
<div class="stat-row">
  <div class="stat-box"><div class="stat-box-label"><?= e((string)t('ops.supervisor_cockpit.order')) ?></div><div class="stat-box-value">#<?= (int)($selectedOrder['id'] ?? 0) ?></div></div>
  <div class="stat-box"><div class="stat-box-label"><?= e((string)t('common.customer')) ?></div><div class="stat-box-value"><?= e((string)($selectedOrder['customer_name'] ?? '-')) ?></div></div>
  <div class="stat-box"><div class="stat-box-label"><?= e((string)t('mfg.dsp.col.qty')) ?></div><div class="stat-box-value"><?= e((string)($selectedOrder['qty'] ?? 0)) ?></div></div>
  <div class="stat-box"><div class="stat-box-label"><?= e((string)t('common.outstanding')) ?></div><div class="stat-box-value"><?= e((string)($selectedOrder['outstanding_qty'] ?? 0)) ?></div></div>
  <div class="stat-box"><div class="stat-box-label"><?= e((string)t('mfg.dsp.col.stage_eligibility')) ?></div><div class="stat-box-value <?= ($workflowHints !== null && !(bool)($workflowHints['allowed'] ?? false)) ? 'u-tone-danger' : 'u-tone-success' ?>"><?= ($workflowHints !== null && !(bool)($workflowHints['allowed'] ?? false)) ? e((string)t('mfg.dsp.eligibility.blocked')) : e((string)t('mfg.dsp.eligibility.ready')) ?></div></div>
</div>

<div class="card">
  <div class="section-head"><h2><?= e((string)t('mfg.dsp.action.prep_form')) ?></h2></div>
  <form method="post" action="/apps/manufacturing/dispatch-ops/preparation" class="mfg-filter-row">
    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
    <input type="hidden" name="dispatch_entry_id" value="<?= (int)($form['dispatch_entry_id'] ?? 0) ?>">
    <input type="hidden" name="daily_order_id" value="<?= (int)($form['daily_order_id'] ?? 0) ?>">
    <input type="hidden" name="product_id" value="<?= (int)($form['product_id'] ?? 0) ?>">
    <div class="ui-block">
      <label class="muted mfg-filter-label"><?= e((string)t('common.date')) ?></label>
      <input class="input" type="date" name="dispatch_date" value="<?= e((string)($form['dispatch_date'] ?? $dispatchDate)) ?>" required>
    </div>
    <div class="ui-block">
      <label class="muted mfg-filter-label"><?= e((string)t('mfg.dsp.col.qty')) ?></label>
      <input class="input" type="number" step="0.01" min="0" name="dispatchable_qty" value="<?= e((string)($form['dispatchable_qty'] ?? '0')) ?>" required>
    </div>
    <div class="ui-block">
      <label class="muted mfg-filter-label"><?= e((string)t('mfg.dsp.col.cases')) ?></label>
      <input class="input" type="number" min="0" name="cases_count" value="<?= (int)($form['cases_count'] ?? 0) ?>">
    </div>
    <div class="ui-block">
      <label class="muted mfg-filter-label"><?= e((string)t('mfg.dsp.col.pallets')) ?></label>
      <input class="input" type="number" min="0" name="pallets_count" value="<?= (int)($form['pallets_count'] ?? 0) ?>">
    </div>
    <div class="ui-block">
      <label class="muted mfg-filter-label"><?= e((string)t('common.destination')) ?></label>
      <input class="input" type="text" name="destination" value="<?= e((string)($form['destination'] ?? '')) ?>">
    </div>
    <div class="dem-filter-col dem-filter-col-wide">
      <label class="muted mfg-filter-label"><?= e((string)t('common.note')) ?></label>
      <textarea name="note"><?= e((string)($form['note'] ?? '')) ?></textarea>
    </div>
    <div class="row">
      <button class="btn ok" type="submit" <?= $canSave ? '' : 'disabled' ?>><?= (int)($form['dispatch_entry_id'] ?? 0) > 0 ? e((string)t('common.update')) : e((string)t('mfg.dsp.action.prepare')) ?></button>
      <a class="btn" href="/apps/manufacturing/dispatch-ops"><?= e((string)t('common.back')) ?></a>
    </div>
  </form>
  <?php if (!$canSave): ?>
    <div class="muted u-style-8a77e5a311"><?= e((string)t('mfg.dsp.readonly_note')) ?></div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="section-head"><h2><?= e((string)t('common.history')) ?></h2></div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th><?= e((string)t('common.date')) ?></th>
          <th><?= e((string)t('common.user')) ?></th>
          <th><?= e((string)t('mfg.dsp.col.qty')) ?></th>
          <th><?= e((string)t('mfg.dsp.col.cases')) ?></th>
          <th><?= e((string)t('mfg.dsp.col.pallets')) ?></th>
          <th><?= e((string)t('common.note')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($history as $item): ?>
          <tr>
            <td><?= e((string)($item['prepared_at'] ?? '')) ?></td>
            <td><?= e((string)($item['prepared_by'] ?? '-')) ?></td>
            <td><?= e((string)($item['prepared_qty'] ?? '0')) ?></td>
            <td><?= (int)($item['cases_count'] ?? 0) ?></td>
            <td><?= (int)($item['pallets_count'] ?? 0) ?></td>
            <td><?= e((string)($item['note'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if ($history === []): ?><tr><td colspan="6" class="muted"><?= e((string)t('common.no_data')) ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
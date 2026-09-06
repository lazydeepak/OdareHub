<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<?php $summary = is_array($summary ?? null) ? $summary : []; ?>
<?php $recentTransitions = is_array($recent_transitions ?? null) ? $recent_transitions : []; ?>
<?php $filterEntity = strtolower(trim((string)($_GET['entity'] ?? 'all'))); ?>
<?php $filterAction = strtolower(trim((string)($_GET['action'] ?? 'all'))); ?>

<?php
$chipStyle = static function (string $state): string {
  // Backward-compatible shim: map legacy inline chip styles to CSS classes.
  // NOTE: Returns an inline style only if templates still inject it.
  return '';
};

$chipClass = static function (string $state): string {
  $s = strtolower(trim($state));
  return match ($s) {
    'approved', 'issued', 'posted', 'closed' => 'chip chip--ok',
    'partial' => 'chip chip--warn',
    'cancelled', 'rejected', 'blocked', 'inactive' => 'chip chip--danger',
    default => 'chip chip--neutral',
  };
};

$summary = is_array($summary ?? null) ? $summary : [];
$recentTransitions = is_array($recent_transitions ?? null) ? $recent_transitions : [];
?>


<?php
$entityOptions = ['all' => 'All Entities'];
$actionOptions = ['all' => 'All Actions'];
$filteredTransitions = [];
foreach ($recentTransitions as $row) {
  $entity = strtolower(trim((string)($row['entity_type'] ?? '')));
  $action = strtolower(trim((string)($row['action_name'] ?? '')));
  if ($entity !== '' && !isset($entityOptions[$entity])) {
    $entityOptions[$entity] = ucfirst(str_replace('_', ' ', $entity));
  }
  if ($action !== '' && !isset($actionOptions[$action])) {
    $actionOptions[$action] = ucfirst(str_replace('_', ' ', $action));
  }
  if ($filterEntity !== 'all' && $entity !== $filterEntity) {
    continue;
  }
  if ($filterAction !== 'all' && $action !== $filterAction) {
    continue;
  }
  $filteredTransitions[] = $row;
}
?>

<?php if (!empty($message)): ?>
  <section class="card card--success"><?= e((string)$message) ?></section>
<?php endif; ?>
<?php if (!empty($error)): ?>
  <section class="card card--error"><?= e((string)$error) ?></section>
<?php endif; ?>

<section class="card">
  <div class="section-head">
    <div class="ui-block">
      <h2 class="u-style-1169661891"><?= e(t('proc.dashboard.title')) ?></h2>
      <div class="muted u-style-fe7b4979fe"><?= e(t('proc.dashboard.subtitle')) ?></div>
    </div>
    <div class="u-style-3de8f987ba">
      <a class="btn" href="/apps/procurement/requests"><?= e(t('proc.dashboard.link_requests')) ?></a>
      <a class="btn" href="/apps/procurement/orders"><?= e(t('proc.dashboard.link_orders')) ?></a>
      <a class="btn" href="/apps/procurement/receipts"><?= e(t('proc.dashboard.link_receipts')) ?></a>
    </div>
  </div>

  <div class="hero-meta u-style-56f4356299">
    <div class="hero-meta-card"><div class="muted"><?= e(t('proc.dashboard.kpi_suppliers')) ?></div><div class="hero-meta-value"><?= (int)($summary['suppliers'] ?? 0) ?></div></div>
    <div class="hero-meta-card"><div class="muted"><?= e(t('proc.dashboard.kpi_requests')) ?></div><div class="hero-meta-value"><?= (int)($summary['requests'] ?? 0) ?></div></div>
    <div class="hero-meta-card"><div class="muted"><?= e(t('proc.dashboard.kpi_open_orders')) ?></div><div class="hero-meta-value"><?= (int)($summary['open_orders'] ?? 0) ?></div></div>
    <div class="hero-meta-card"><div class="muted"><?= e(t('proc.dashboard.kpi_receipts')) ?></div><div class="hero-meta-value"><?= (int)($summary['receipts'] ?? 0) ?></div></div>
  </div>
</section>

<section class="card">
  <h3 class="u-style-d462248a40"><?= e(t('proc.dashboard.add_supplier_title')) ?></h3>
  <form method="post" action="/apps/procurement/suppliers/create" class="grid u-style-1a42931a01">
    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
    <label><?= e(t('proc.supplier.name')) ?><input class="input" type="text" name="supplier_name" required></label>
    <label><?= e(t('proc.supplier.code')) ?><input class="input" type="text" name="supplier_code"></label>
    <label><?= e(t('proc.supplier.email')) ?><input class="input" type="email" name="email"></label>
    <label><?= e(t('proc.supplier.phone')) ?><input class="input" type="text" name="phone"></label>
    <div class="u-style-068a8ddc17">
      <button class="btn ok" type="submit"><?= e(t('proc.dashboard.btn_create_supplier')) ?></button>
    </div>
  </form>
</section>

<section class="card">
  <h3 class="u-style-d462248a40"><?= e(t('proc.dashboard.latest_requests')) ?></h3>
  <?php if (empty($latest_requests)): ?>
    <p class="muted u-style-1169661891"><?= e(t('proc.dashboard.no_requests')) ?></p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th><?= e(t('proc.request.col_ref')) ?></th><th><?= e(t('proc.request.col_product')) ?></th><th class="u-style-54c2afb7ba"><?= e(t('proc.request.col_qty')) ?></th><th><?= e(t('common.status')) ?></th></tr></thead>
        <tbody>
          <?php foreach ((array)$latest_requests as $r): ?>
            <tr>
              <td><?= e((string)($r['request_ref'] ?? '')) ?></td>
              <td><?= (int)($r['product_id'] ?? 0) ?></td>
              <td class="u-style-54c2afb7ba"><?= number_format((float)($r['requested_qty'] ?? 0), 2) ?></td>
              <td><span class="<?= e($chipClass((string)($r['request_status'] ?? ''))) ?>"><?= e((string)($r['request_status'] ?? '')) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<section class="card">
  <h3 class="u-style-d462248a40"><?= e(t('proc.dashboard.latest_orders')) ?></h3>
  <?php if (empty($latest_orders)): ?>
    <p class="muted u-style-1169661891"><?= e(t('proc.dashboard.no_orders')) ?></p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th><?= e(t('proc.order.col_ref')) ?></th><th><?= e(t('proc.order.col_supplier')) ?></th><th><?= e(t('common.status')) ?></th><th><?= e(t('proc.order.col_expected')) ?></th></tr></thead>
        <tbody>
          <?php foreach ((array)$latest_orders as $r): ?>
            <tr>
              <td><?= e((string)($r['po_ref'] ?? '')) ?></td>
              <td>
                <?php $supplierName = trim((string)($r['supplier_name'] ?? '')); ?>
                <?= $supplierName !== '' ? e($supplierName) : ('#' . (int)($r['supplier_id'] ?? 0)) ?>
              </td>
              <td><span class="<?= e($chipClass((string)($r['po_status'] ?? ''))) ?>"><?= e((string)($r['po_status'] ?? '')) ?></span></td>
              <td><?= e((string)($r['expected_date'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<section class="card">
  <h3 class="u-style-d462248a40"><?= e(t('proc.dashboard.timeline_title')) ?></h3>
  <form method="get" action="/apps/procurement" class="row u-style-5a946285f3">
    <label><?= e(t('proc.dashboard.filter_entity')) ?>
      <select name="entity">
        <?php foreach ($entityOptions as $value => $label): ?>
          <option value="<?= e((string)$value) ?>" <?= $filterEntity === (string)$value ? 'selected' : '' ?>><?= e((string)$label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label><?= e(t('proc.dashboard.filter_action')) ?>
      <select name="action">
        <?php foreach ($actionOptions as $value => $label): ?>
          <option value="<?= e((string)$value) ?>" <?= $filterAction === (string)$value ? 'selected' : '' ?>><?= e((string)$label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button class="btn" type="submit"><?= e(t('proc.dashboard.btn_filter')) ?></button>
    <a class="btn" href="/apps/procurement"><?= e(t('common.reset')) ?></a>
  </form>

  <?php if ($filteredTransitions === []): ?>
    <p class="muted u-style-1169661891"><?= e(t('proc.dashboard.no_transitions')) ?></p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th><?= e(t('proc.transition.col_when')) ?></th><th><?= e(t('proc.transition.col_entity')) ?></th><th><?= e(t('proc.transition.col_action')) ?></th><th><?= e(t('proc.transition.col_state')) ?></th><th><?= e(t('proc.transition.col_note')) ?></th><th><?= e(t('proc.transition.col_actor')) ?></th></tr></thead>
        <tbody>
          <?php foreach ($filteredTransitions as $t): ?>
            <tr>
              <td><?= e((string)($t['created_at'] ?? '')) ?></td>
              <td><?= e((string)($t['entity_type'] ?? '')) ?> #<?= (int)($t['entity_id'] ?? 0) ?></td>
              <td><?= e((string)($t['action_name'] ?? '')) ?></td>
              <td><span class="<?= e($chipClass((string)($t['new_state'] ?? ''))) ?>"><?= e((string)($t['new_state'] ?? '')) ?></span></td>
              <td><?= e((string)($t['note_text'] ?? '')) ?></td>
              <td><?= e((string)($t['actor_label'] ?? 'System')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

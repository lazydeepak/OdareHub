<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<?php
$products = isset($products) && is_array($products) ? $products : [];
$rows = isset($rows) && is_array($rows) ? $rows : [];

$demandTypeLabelMap = [
  'production' => (string)t('mfg.dem.option.production'),
  'qc' => (string)t('mfg.dem.option.qc'),
  'assembly' => (string)t('mfg.dem.option.assembly'),
];
$statusLabelMap = [
  'calculated' => (string)t('mfg.dem.option.calculated'),
  'adjusted' => (string)t('mfg.dem.option.adjusted'),
  'approved' => (string)t('mfg.dem.option.approved'),
];
?>

<div class="card">
  <div class="mfg-page-header">
      <div class="dem-header">
        <h2 class="dem-title"><?php echo t('mfg.dem.title') ?></h2>
        <div class="muted dem-subtitle"><?php echo t('mfg.dem.subtitle') ?></div>
    </div>
    <div class="mfg-page-header-links">
      <a class="btn" href="/apps/manufacturing"><?php echo t('mfg.dem.link.portal') ?></a>
      <a class="btn" href="/apps/manufacturing/qc-queue"><?php echo t('mfg.dem.link.qc_queue') ?></a>
      <a class="btn" href="/apps/manufacturing/dispatch-ops"><?php echo t('mfg.dem.link.dispatch_ops') ?></a>
    </div>
  </div>
</div>

<?php if (!empty($flash ?? '')): ?><div class="card dsp-flash-ok"><?= e((string)$flash) ?></div><?php endif; ?>
<?php if (!empty($error ?? '')): ?><div class="card dsp-flash-err"><?= e((string)$error) ?></div><?php endif; ?>

<div class="card">
  <form method="get" action="/apps/manufacturing/demands" class="row dem-filter-row">
    <div class="dem-filter-col"><label class="muted dem-filter-label"><?php echo t('mfg.dem.filter.from') ?></label><input class="input" type="date" name="from_date" value="<?= e((string)($from_date ?? '')) ?>"></div>
    <div class="dem-filter-col"><label class="muted dem-filter-label"><?php echo t('mfg.dem.filter.to') ?></label><input class="input" type="date" name="to_date" value="<?= e((string)($to_date ?? '')) ?>"></div>
    <div class="dem-filter-col dem-filter-col-wide">
      <label class="muted dem-filter-label"><?php echo t('mfg.dem.filter.part') ?></label>
      <select name="product_id">
        <option value="0"><?php echo t('mfg.dem.filter.all_parts') ?></option>
        <?php foreach ($products as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= ((int)($product_id ?? 0) === (int)$p['id']) ? 'selected' : '' ?>>
            <?= e((string)$p['parts_name']) ?> (<?= e((string)$p['parts_number']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="dem-filter-col">
      <label class="muted dem-filter-label"><?php echo t('mfg.dem.filter.demand_type') ?></label>
      <select name="demand_type">
        <option value=""><?php echo t('mfg.dem.filter.all') ?></option>
        <?php foreach (['production' => t('mfg.dem.option.production'), 'qc' => t('mfg.dem.option.qc'), 'assembly' => t('mfg.dem.option.assembly')] as $k => $label): ?>
          <option value="<?= e($k) ?>" <?= ((string)($demand_type ?? '') === $k) ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="dem-filter-col">
      <label class="muted dem-filter-label"><?php echo t('mfg.dem.filter.status') ?></label>
      <select name="status">
        <option value=""><?php echo t('mfg.dem.filter.all') ?></option>
        <?php foreach (['calculated' => t('mfg.dem.option.calculated'), 'adjusted' => t('mfg.dem.option.adjusted'), 'approved' => t('mfg.dem.option.approved')] as $k => $label): ?>
          <option value="<?= e($k) ?>" <?= ((string)($status ?? '') === $k) ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="row"><button class="btn" type="submit"><?php echo t('mfg.dem.filter.apply') ?></button><a class="btn" href="/apps/manufacturing/demands"><?php echo t('mfg.dem.filter.reset') ?></a></div>
  </form>

  <form method="post" action="/apps/manufacturing/demands/recalculate" class="row dem-recalc-form">
    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
    <input type="hidden" name="from_date" value="<?= e((string)($from_date ?? '')) ?>">
    <input type="hidden" name="to_date" value="<?= e((string)($to_date ?? '')) ?>">
    <input type="hidden" name="product_id" value="<?= (int)($product_id ?? 0) ?>">
    <button class="btn ok" type="submit"><?php echo t('mfg.dem.form.recalculate') ?></button>
  </form>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th><?php echo t('mfg.dem.col.id') ?></th>
          <th><?php echo t('mfg.dem.col.date') ?></th>
          <th><?php echo t('mfg.dem.col.part') ?></th>
          <th><?php echo t('mfg.dem.col.type') ?></th>
          <th><?php echo t('mfg.dem.col.system_qty') ?></th>
          <th><?php echo t('mfg.dem.col.adjusted_qty') ?></th>
          <th><?php echo t('mfg.dem.col.approved_qty') ?></th>
          <th><?php echo t('mfg.dem.col.status') ?></th>
          <th><?php echo t('mfg.dem.col.action') ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td><?= e((string)$r['demand_date']) ?></td>
          <td><?= e((string)$r['parts_name']) ?> <span class="muted">(<?= e((string)$r['parts_number']) ?>)</span></td>
          <td><?php $demandType = strtolower(trim((string)($r['demand_type'] ?? ''))); ?><?= e((string)($demandTypeLabelMap[$demandType] ?? (string)($r['demand_type'] ?? ''))) ?></td>
          <td><?= e((string)$r['system_qty']) ?></td>
          <td><?= e((string)($r['adjusted_qty'] ?? '')) ?></td>
          <td><?= e((string)($r['approved_qty'] ?? '')) ?></td>
          <td><?php $statusValue = strtolower(trim((string)($r['status'] ?? ''))); ?><?= e((string)($statusLabelMap[$statusValue] ?? (string)($r['status'] ?? ''))) ?></td>
          <td>
            <form method="post" action="/apps/manufacturing/demands/adjust" class="dem-inline-form dem-inline-form-spaced">
              <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <input class="input" type="number" step="0.01" min="0" name="adjusted_qty" value="<?= e((string)($r['adjusted_qty'] ?? $r['system_qty'])) ?>" class="dem-qty-input">
              <input class="input" type="text" name="adjustment_note" value="<?= e((string)($r['adjustment_note'] ?? '')) ?>" placeholder="<?php echo t('mfg.dem.form.adjustment_note') ?>" class="dem-note-input">
              <button class="btn" type="submit"><?php echo t('mfg.dem.action.adjust') ?></button>
            </form>
            <form method="post" action="/apps/manufacturing/demands/approve" class="dem-inline-form">
              <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <input class="input" type="number" step="0.01" min="0" name="approved_qty" value="<?= e((string)($r['approved_qty'] ?? $r['adjusted_qty'] ?? $r['system_qty'])) ?>" class="dem-qty-input">
              <button class="btn ok" type="submit"><?php echo t('mfg.dem.action.approve') ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($rows)): ?>
        <tr><td colspan="9" class="muted"><?php echo t('mfg.dem.empty') ?></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

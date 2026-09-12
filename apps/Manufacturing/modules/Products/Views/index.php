<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<?php
$rows = is_array($rows ?? null) ? $rows : [];
$q = (string)($q ?? '');
$active = (string)($active ?? 'all');
$anchorDate = (string)($anchor_date ?? date('Y-m-d'));
$scopeMode = (string)($scope_mode ?? 'my');
$scopeSource = (string)($scope_source ?? 'assigned_parts');
$hasAssignedParts = !empty($has_assigned_parts);
$sort = (string)($sort ?? 'coverage_balance_qty');
$dir = (string)($dir ?? 'asc');

$fmt = static function (float $value, int $precision = 2): string {
    return number_format($value, $precision, '.', ',');
};

$sortLink = static function (string $field) use ($q, $active, $anchorDate, $scopeMode, $sort, $dir): string {
    $nextDir = ($sort === $field && $dir === 'asc') ? 'desc' : 'asc';
    $params = [
        'q' => $q,
        'active' => $active,
        'date' => $anchorDate,
        'scope' => $scopeMode,
        'sort' => $field,
        'dir' => $nextDir,
    ];
    return '/apps/manufacturing/products?' . http_build_query($params);
};

$scopeLink = static function (string $mode) use ($q, $active, $anchorDate, $sort, $dir): string {
    return '/apps/manufacturing/products?' . http_build_query([
        'q' => $q,
        'active' => $active,
        'date' => $anchorDate,
        'scope' => $mode,
        'sort' => $sort,
        'dir' => $dir,
    ]);
};

$sortIndicator = static function (string $field) use ($sort, $dir): string {
    if ($sort !== $field) {
        return '';
    }
    return $dir === 'desc' ? '▼' : '▲';
};

$scopeSummary = $scopeMode === 'all'
    ? 'Showing all parts in the canonical master list.'
    : ($scopeSource === 'assigned_parts'
        ? 'Showing your assigned parts first for operational focus.'
        : 'No assigned parts found, so this default viewport is showing the Top 5 risk parts.');
?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2><?= e(t('module.products.title')) ?></h2>
      <div class="muted">Operational master view for stock, lead-adjusted target, and current coverage balance.</div>
    </div>
    <div class="module-header-actions">
      <a class="btn ok" href="/apps/manufacturing/products/add"><?= e(t('module.products.add')) ?></a>
      <a class="btn" href="/apps/manufacturing/products/import"><?= e(t('common.import_csv')) ?></a>
    </div>
  </div>
</div>

<?php if (!empty($flash ?? '')): ?>
  <div class="card u-style-a25b37e313">
    <?= e((string)$flash) ?>
  </div>
<?php endif; ?>

<?php if (!empty($error ?? '')): ?>
  <div class="card u-style-64e7e2c551">
    <?= e((string)$error) ?>
  </div>
<?php endif; ?>

<div class="card">
  <form method="get" action="/apps/manufacturing/products" class="products-filterbar products-filterbar-operational">
    <div class="products-filter-field products-filter-date">
      <label for="partsAnchorDate" class="muted products-filter-label">Date</label>
      <input class="input" id="partsAnchorDate" type="date" name="date" value="<?= e($anchorDate) ?>">
    </div>
    <div class="products-filter-field products-filter-search">
      <label for="partsSearch" class="muted products-filter-label"><?= e(t('common.search')) ?></label>
      <input class="input" id="partsSearch" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('module.products.search_placeholder')) ?>" autocomplete="off">
    </div>
    <div class="products-filter-field products-filter-status">
      <label for="partsActive" class="muted products-filter-label"><?= e(t('common.status')) ?></label>
      <select id="partsActive" name="active">
        <option value="all" <?= $active === 'all' ? 'selected' : '' ?>><?= e(t('common.all')) ?></option>
        <option value="1" <?= $active === '1' ? 'selected' : '' ?>><?= e(t('common.active')) ?></option>
        <option value="0" <?= $active === '0' ? 'selected' : '' ?>><?= e(t('common.inactive')) ?></option>
      </select>
    </div>
    <input type="hidden" name="scope" value="<?= e($scopeMode) ?>">
    <input type="hidden" name="sort" value="<?= e($sort) ?>">
    <input type="hidden" name="dir" value="<?= e($dir) ?>">
    <div class="products-filter-actions">
      <button class="btn" type="submit"><?= e(t('common.filter')) ?></button>
      <a class="btn" href="/apps/manufacturing/products"><?= e(t('common.reset')) ?></a>
    </div>
  </form>

  <div class="products-scopebar">
    <div class="products-scope-toggle">
      <a class="btn<?= $scopeMode === 'my' ? ' ok' : '' ?>" href="<?= e($scopeLink('my')) ?>">My Parts</a>
      <a class="btn<?= $scopeMode === 'all' ? ' ok' : '' ?>" href="<?= e($scopeLink('all')) ?>">View All Parts</a>
    </div>
    <div class="muted"><?= e($scopeSummary) ?></div>
  </div>
</div>

<div class="card">
  <div class="table-wrap">
    <table class="products-table products-table-operational">
      <thead>
      <tr>
        <th><a class="products-sort-link" href="<?= e($sortLink('part_id')) ?>">ID <?= e($sortIndicator('part_id')) ?></a></th>
        <th><a class="products-sort-link" href="<?= e($sortLink('part_name')) ?>">Part Name <?= e($sortIndicator('part_name')) ?></a></th>
        <th><a class="products-sort-link" href="<?= e($sortLink('part_number')) ?>">Part Number <?= e($sortIndicator('part_number')) ?></a></th>
        <th><a class="products-sort-link" href="<?= e($sortLink('stock_qty')) ?>">Stock <?= e($sortIndicator('stock_qty')) ?></a></th>
        <th><a class="products-sort-link" href="<?= e($sortLink('target_today_qty')) ?>">Target Today <?= e($sortIndicator('target_today_qty')) ?></a></th>
        <th><a class="products-sort-link" href="<?= e($sortLink('coverage_balance_qty')) ?>">Coverage / Balance <?= e($sortIndicator('coverage_balance_qty')) ?></a></th>
        <th><a class="products-sort-link" href="<?= e($sortLink('is_active')) ?>"> <?= e($tt('common.status_label')) ?> <?= e($sortIndicator('is_active')) ?></a></th>
        <th><a class="products-sort-link" href="<?= e($sortLink('updated_at')) ?>">Updated <?= e($sortIndicator('updated_at')) ?></a></th>
        <th>Item Ref</th>
      </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <?php
          $part360Url = '/apps/manufacturing/products/360?id=' . (int)($r['part_id'] ?? 0);
          $coverage = (float)($r['coverage_balance_qty'] ?? 0);
          $coverageClass = $coverage < 0 ? 'p360-badge-bad' : ($coverage === 0.0 ? 'p360-badge-warn' : 'p360-badge-good');
          $isAct = (int)($r['is_active'] ?? 1) === 1;
        ?>
        <tr class="table-link-row" data-row-href="<?= e($part360Url) ?>">
          <td><?= (int)($r['part_id'] ?? 0) ?></td>
          <td>
            <a
              class="table-link-row-anchor"
              href="<?= e($part360Url) ?>"
              title="Open Part 360"
              aria-label="<?= e('Open Part 360 for ' . (string)($r['part_name'] ?? '') . ' (' . (string)($r['part_number'] ?? '') . ')') ?>"
            >
              <span><?= e((string)($r['part_name'] ?? '')) ?></span>
              <span class="table-link-row-meta" aria-hidden="true">360</span>
            </a>
            <div class="muted u-style-6cb285c61d">
              <?= !empty($r['is_assigned']) ? 'Assigned to you' : 'Visible in shared master list' ?>
            </div>
          </td>
          <td><code><?= e((string)($r['part_number'] ?? '')) ?></code></td>
          <td>
            <strong><?= e($fmt((float)($r['stock_qty'] ?? 0))) ?></strong>
            <div class="muted u-style-6cb285c61d">Current ledger-backed stock</div>
          </td>
          <td>
            <strong><?= e($fmt((float)($r['target_today_qty'] ?? 0))) ?></strong>
            <div class="muted u-style-6cb285c61d">
              <?= e($fmt((float)($r['target_demand_qty'] ?? 0))) ?> demand on <?= e((string)($r['target_date'] ?? '-')) ?> + <?= e($fmt((float)($r['safety_stock_qty'] ?? 0))) ?> safety stock
            </div>
          </td>
          <td>
            <span class="p360-badge <?= e($coverageClass) ?>"><?= e($fmt($coverage)) ?></span>
            <div class="muted u-style-6cb285c61d">Current stock - target today</div>
          </td>
          <td>
            <span class="pill" style="<?= $isAct ? 'border-color: var(--color-success-border);color: var(--color-success-text);background: var(--color-success-bg)' : 'border-color: var(--color-danger-border);color: var(--color-danger-text);background: var(--color-danger-bg)' ?>">
              <?= $isAct ? e(t('common.active')) : e(t('common.inactive')) ?>
            </span>
          </td>
          <td class="muted u-style-48f6fb5dfe">
            <?= ($r['updated_at'] ?? '') !== '' ? e(date('Y-m-d H:i', strtotime((string)$r['updated_at']))) : '' ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($rows === []): ?>
        <tr><td colspan="8" class="muted"><?= e(t('module.products.none')) ?></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="muted u-style-d2c171b18b"><?= e(localized_records_summary(count($rows))) ?></div>
</div>

<script>
  (function () {
    var table = document.querySelector('.products-table-operational tbody');
    if (!table) {
      return;
    }

    var interactiveSelector = 'a, button, input, select, textarea, summary, label, [role="button"], [role="link"], [data-row-ignore]';

    function isInteractiveTarget(target) {
      return target instanceof Element && !!target.closest(interactiveSelector);
    }

    table.addEventListener('click', function (event) {
      var target = event.target;
      var selection = window.getSelection ? window.getSelection() : null;
      if (!(target instanceof Element) || event.defaultPrevented || event.button !== 0) {
        return;
      }
      if (selection && !selection.isCollapsed) {
        return;
      }
      var row = target.closest('tr[data-row-href]');
      if (!row || isInteractiveTarget(target)) {
        return;
      }
      var href = row.getAttribute('data-row-href') || '';
      if (href !== '') {
        window.location.assign(href);
      }
    });
  }());
</script>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

<?php
declare(strict_types=1);


$tt = static function (string $key): string {
  return t($key);
};
$summary = is_array($summary ?? null) ? $summary : [];
$rows = is_array($rows ?? null) ? $rows : [];

usort($rows, static function (array $a, array $b): int {
  $shortageA = (float)($a['net_shortage_qty'] ?? 0);
  $shortageB = (float)($b['net_shortage_qty'] ?? 0);
  if ($shortageA !== $shortageB) {
    return $shortageA < $shortageB ? 1 : -1;
  }

  $requiredA = trim((string)($a['required_date'] ?? ''));
  $requiredB = trim((string)($b['required_date'] ?? ''));
  $sortA = $requiredA !== '' ? $requiredA : '9999-12-31';
  $sortB = $requiredB !== '' ? $requiredB : '9999-12-31';
  if ($sortA !== $sortB) {
    return strcmp($sortA, $sortB);
  }

  return (int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0);
});

require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php';
?>

<section class="card">
  <div class="section-head">
    <h2><?php echo t('mfg.cov.title') ?></h2>
    <div class="muted"><?php echo t('mfg.cov.subtitle') ?></div>
  </div>
  <div class="coverage-kpi-grid">
    <div class="coverage-kpi">
      <div class="muted"><?php echo t('mfg.cov.kpi.open_orders') ?></div>
      <div class="coverage-kpi-value"><?= (int)($summary['open_orders'] ?? 0) ?></div>
    </div>
    <div class="coverage-kpi">
      <div class="muted"><?php echo t('mfg.cov.kpi.demand_qty') ?></div>
      <div class="coverage-kpi-value"><?= e(number_format((float)($summary['demand_qty'] ?? 0), 2, '.', ',')) ?></div>
    </div>
    <div class="coverage-kpi">
      <div class="muted"><?php echo t('mfg.cov.kpi.covered_qty') ?></div>
      <div class="coverage-kpi-value"><?= e(number_format((float)($summary['covered_qty'] ?? 0), 2, '.', ',')) ?></div>
    </div>
    <div class="coverage-kpi">
      <div class="muted"><?php echo t('mfg.cov.kpi.shortage_qty') ?></div>
      <div class="coverage-kpi-value"><?= e(number_format((float)($summary['shortage_qty'] ?? 0), 2, '.', ',')) ?></div>
    </div>
    <div class="coverage-kpi coverage-kpi-accent">
      <div class="muted"><?php echo t('mfg.cov.kpi.avg_coverage_pct') ?></div>
      <div class="coverage-kpi-value"><?= e(number_format((float)($summary['coverage_pct'] ?? 0), 2, '.', ',')) ?>%</div>
    </div>
    <div class="coverage-kpi">
      <div class="muted"><?php echo t('mfg.cov.kpi.critical_count') ?></div>
      <div class="coverage-kpi-value"><?= (int)($summary['critical_orders_count'] ?? 0) ?></div>
    </div>
    <div class="coverage-kpi">
      <div class="muted"><?php echo t('mfg.cov.kpi.low_coverage_count') ?></div>
      <div class="coverage-kpi-value"><?= (int)($summary['low_coverage_orders_count'] ?? 0) ?></div>
    </div>
    <div class="coverage-kpi">
      <div class="muted"><?php echo t('mfg.cov.kpi.fully_covered_count') ?></div>
      <div class="coverage-kpi-value"><?= (int)($summary['fully_covered_orders_count'] ?? 0) ?></div>
    </div>
    <div class="coverage-kpi">
      <div class="muted"><?php echo t('mfg.cov.kpi.coverage_window') ?></div>
      <div class="coverage-window-filter-group" role="group" aria-label= "<?= e($tt('coverage.coverage_window_filter_action')) ?>">
        <button type="button" class="coverage-window-chip" data-window="today"><?php echo t('mfg.cov.filter.today') ?> <strong><?= (int)($summary['window_today_count'] ?? 0) ?></strong></button>
        <button type="button" class="coverage-window-chip" data-window="3d"><?php echo t('mfg.cov.filter.3day') ?> <strong><?= (int)($summary['window_3day_count'] ?? 0) ?></strong></button>
        <button type="button" class="coverage-window-chip is-active" data-window="7d"><?php echo t('mfg.cov.filter.7day') ?> <strong><?= (int)($summary['window_7day_count'] ?? 0) ?></strong></button>
        <button type="button" class="coverage-window-chip" data-window="all"><?php echo t('mfg.cov.filter.all') ?></button>
      </div>
      <div class="coverage-window-note"><?php echo t('mfg.cov.filter.note') ?></div>
    </div>
  </div>
</section>

<section class="card">
  <div class="section-head">
    <h2><?php echo t('mfg.cov.section.priorities') ?></h2>
    <div class="muted"><?php echo t('mfg.cov.section.priorities_desc') ?></div>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th><?php echo t('mfg.cov.col.order') ?></th>
          <th><?php echo t('mfg.cov.col.order_date') ?></th>
          <th><?php echo t('mfg.cov.col.required_date') ?></th>
          <th><?php echo t('mfg.cov.col.customer') ?></th>
          <th><?php echo t('mfg.cov.col.part') ?></th>
          <th><?php echo t('mfg.cov.col.demand_qty') ?></th>
          <th><?php echo t('mfg.cov.col.available_stock') ?></th>
          <th><?php echo t('mfg.cov.col.planned_supply') ?></th>
          <th><?php echo t('mfg.cov.col.net_coverage') ?></th>
          <th><?php echo t('mfg.cov.col.net_shortage') ?></th>
          <th><?php echo t('mfg.cov.col.coverage_pct') ?></th>
          <th><?php echo t('mfg.cov.col.status') ?></th>
          <th><?php echo t('mfg.cov.col.actions') ?></th>
        </tr>
      </thead>
      <tbody id="coverage-table-body">
        <?php foreach ($rows as $row): ?>
        <?php $orderId = (int)($row['id'] ?? 0); ?>
        <?php
          $requiredDate = trim((string)($row['required_date'] ?? ''));
          $orderDate = trim((string)($row['order_date'] ?? ''));
          $classification = strtolower(str_replace(' ', '-', trim((string)($row['coverage_classification'] ?? 'No Data'))));
          $classificationClass = 'coverage-pill-no-data';
          $rowClass = '';
          if ($classification === 'critical') {
            $classificationClass = 'coverage-pill-critical';
            $rowClass = 'coverage-row-priority-critical';
          } elseif ($classification === 'low') {
            $classificationClass = 'coverage-pill-low';
            $rowClass = 'coverage-row-priority-low';
          } elseif ($classification === 'balanced') {
            $classificationClass = 'coverage-pill-balanced';
          } elseif ($classification === 'high') {
            $classificationClass = 'coverage-pill-high';
          } elseif ($classification === 'supply-only') {
            $classificationClass = 'coverage-pill-supply-only';
          }
        ?>
        <tr class="<?= e($rowClass) ?>" data-row="coverage" data-required-date="<?= e($requiredDate) ?>" data-order-date="<?= e($orderDate) ?>">
          <td><a href="/daily-orders/360?id=<?= $orderId ?>">#<?= $orderId ?></a></td>
          <td><?= e((string)($row['order_date'] ?? '')) ?></td>
          <td><?= e((string)($row['required_date'] ?? '')) ?></td>
          <td><?= e((string)($row['customer_name'] ?? '')) ?></td>
          <td>
            <?= e((string)($row['parts_name'] ?? '')) ?>
            <span class="muted"><?= e((string)($row['parts_number'] ?? '')) ?></span>
          </td>
          <td><?= e(number_format((float)($row['qty'] ?? 0), 2, '.', ',')) ?></td>
          <td><?= e(number_format((float)($row['available_stock_qty'] ?? 0), 2, '.', ',')) ?></td>
          <td><?= e(number_format((float)($row['planned_supply_qty'] ?? 0), 2, '.', ',')) ?></td>
          <td><?= e(number_format((float)($row['net_coverage_qty'] ?? 0), 2, '.', ',')) ?></td>
          <td class="coverage-shortage-cell"><?= e(number_format((float)($row['net_shortage_qty'] ?? 0), 2, '.', ',')) ?></td>
          <td><?= e(number_format((float)($row['coverage_pct'] ?? 0), 2, '.', ',')) ?>%</td>
          <td>
            <span class="coverage-pill <?= e($classificationClass) ?>"><?= e((string)($row['coverage_classification'] ?? t('mfg.cov.status.no_data'))) ?></span>
            <div class="muted u-style-96ad6099e2"><?= e((string)($row['coverage_status'] ?? '')) ?></div>
          </td>
          <td>
            <div class="coverage-actions">
              <?php foreach ((array)($row['action_links'] ?? []) as $link): ?>
                <?php $url = (string)($link['url'] ?? ''); ?>
                <?php $label = (string)($link['label'] ?? 'Open'); ?>
                <?php if ($url !== ''): ?>
                  <a class="btn" href="<?= e($url) ?>"><?= e($label) ?></a>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
        <tr><td colspan="13">
          <div class="coverage-empty-state">
            <div class="ui-block"><strong><?php echo t('mfg.cov.empty') ?></strong></div>
            <div class="muted u-style-96ad6099e2"><?php echo t('mfg.cov.empty_note') ?></div>
          </div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div id="coverage-empty-filtered" class="coverage-empty-state u-style-6b99de8b69">
    <div class="ui-block"><strong><?php echo t('mfg.cov.empty') ?></strong></div>
    <div class="muted u-style-96ad6099e2"><?php echo t('mfg.cov.empty_note') ?></div>
  </div>
</section>

<script>
  (function () {
    var tableBody = document.getElementById('coverage-table-body');
    if (!tableBody) return;

    var rows = Array.prototype.slice.call(tableBody.querySelectorAll('tr[data-row="coverage"]'));
    var chips = Array.prototype.slice.call(document.querySelectorAll('.coverage-window-chip[data-window]'));
    var emptyFiltered = document.getElementById('coverage-empty-filtered');
    if (rows.length === 0 || chips.length === 0) return;

    function parseDate(raw) {
      if (!raw) return null;
      var d = new Date(raw + 'T00:00:00');
      return isNaN(d.getTime()) ? null : d;
    }

    function matchWindow(row, windowKey) {
      if (windowKey === 'all') return true;

      var requiredDate = parseDate(row.getAttribute('data-required-date') || '');
      var orderDate = parseDate(row.getAttribute('data-order-date') || '');
      var baseDate = requiredDate || orderDate;
      if (!baseDate) return true;

      var today = new Date();
      today.setHours(0, 0, 0, 0);
      var diffDays = Math.floor((baseDate.getTime() - today.getTime()) / 86400000);

      if (windowKey === 'today') return diffDays <= 0;
      if (windowKey === '3d') return diffDays <= 3;
      if (windowKey === '7d') return diffDays <= 7;
      return true;
    }

    function applyWindow(windowKey) {
      var visibleCount = 0;
      rows.forEach(function (row) {
        var show = matchWindow(row, windowKey);
        row.style.display = show ? '' : 'none';
        if (show) visibleCount += 1;
      });
      if (emptyFiltered) {
        emptyFiltered.style.display = visibleCount === 0 ? '' : 'none';
      }
    }

    chips.forEach(function (chip) {
      chip.addEventListener('click', function () {
        var windowKey = chip.getAttribute('data-window') || '7d';
        chips.forEach(function (item) { item.classList.remove('is-active'); });
        chip.classList.add('is-active');
        applyWindow(windowKey);
      });
    });

    applyWindow('7d');
  })();
</script>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

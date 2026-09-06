<?php
declare(strict_types=1);

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
    <h2><?= e(t('mfg.coverage.title')) ?></h2>
    <div class="muted"><?= e(t('mfg.coverage.subtitle')) ?></div>
  </div>
  <div class="coverage-kpi-grid">
    <div class="coverage-kpi">
      <div class="muted"><?= e(t('mfg.coverage.open_orders_kpi')) ?></div>
      <div class="coverage-kpi-value"><?= (int)($summary['open_orders'] ?? 0) ?></div>
    </div>
    <div class="coverage-kpi">
      <div class="muted"><?= e(t('mfg.coverage.demand_qty_kpi')) ?></div>
      <div class="coverage-kpi-value"><?= e(number_format((float)($summary['demand_qty'] ?? 0), 2, '.', ',')) ?></div>
    </div>
    <div class="coverage-kpi">
      <div class="muted"><?= e(t('mfg.coverage.covered_qty_kpi')) ?></div>
      <div class="coverage-kpi-value"><?= e(number_format((float)($summary['covered_qty'] ?? 0), 2, '.', ',')) ?></div>
    </div>
    <div class="coverage-kpi">
      <div class="muted"><?= e(t('mfg.coverage.shortage_qty_kpi')) ?></div>
      <div class="coverage-kpi-value"><?= e(number_format((float)($summary['shortage_qty'] ?? 0), 2, '.', ',')) ?></div>
    </div>
    <div class="coverage-kpi">
      <div class="muted"><?= e(t('mfg.coverage.avg_coverage_kpi')) ?></div>
      <div class="coverage-kpi-value"><?= e(number_format((float)($summary['coverage_pct'] ?? 0), 1, '.', ',')) ?>%</div>
    </div>
    <div class="coverage-kpi">
      <div class="muted"><?= e(t('mfg.coverage.critical_orders_kpi')) ?></div>
      <div class="coverage-kpi-value"><?= (int)($summary['critical_orders_count'] ?? 0) ?></div>
    </div>
  </div>
</section>

<section class="card">
  <div class="section-head">
    <h2><?= e(t('mfg.coverage.shortage_priorities')) ?></h2>
    <div class="muted"><?= e(t('mfg.coverage.top_shortage_desc')) ?></div>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th><?= e(t('mfg.coverage.order_col')) ?></th>
          <th><?= e(t('mfg.coverage.required_date_col')) ?></th>
          <th><?= e(t('mfg.coverage.customer_col')) ?></th>
          <th><?= e(t('mfg.coverage.part_col')) ?></th>
          <th><?= e(t('mfg.coverage.demand_qty_col')) ?></th>
          <th><?= e(t('mfg.coverage.net_shortage_col')) ?></th>
          <th><?= e(t('mfg.coverage.coverage_pct_col')) ?></th>
          <th><?= e(t('mfg.coverage.actions_col')) ?></th>
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
          <td><?= e((string)($row['required_date'] ?? '')) ?></td>
          <td><?= e((string)($row['customer_name'] ?? '')) ?></td>
          <td>
            <?= e((string)($row['parts_name'] ?? '')) ?>
            <span class="muted"><?= e((string)($row['parts_number'] ?? '')) ?></span>
          </td>
          <td><?= e(number_format((float)($row['qty'] ?? 0), 2, '.', ',')) ?></td>
          <td class="coverage-shortage-cell"><?= e(number_format((float)($row['net_shortage_qty'] ?? 0), 2, '.', ',')) ?></td>
          <td><?= e(number_format((float)($row['coverage_pct'] ?? 0), 2, '.', ',')) ?>%</td>
          <td>
            <span class="coverage-pill <?= e($classificationClass) ?>"><?= e((string)($row['coverage_classification'] ?? 'No Data')) ?></span>
            <?php foreach ((array)($row['action_links'] ?? []) as $link): ?>
              <?php $url = (string)($link['url'] ?? ''); ?>
              <?php $label = (string)($link['label'] ?? t('common.open')); ?>
              <?php if ($url !== ''): ?>
                <a class="btn" href="<?= e($url) ?>"><?= e($label) ?></a>
              <?php endif; ?>
            <?php endforeach; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
        <tr><td colspan="8">
          <div class="coverage-empty-state">
            <div class="ui-block"><strong><?= e(t('mfg.coverage.empty_state')) ?></strong></div>
            <div class="muted u-style-96ad6099e2"><?= e(t('mfg.coverage.fully_covered')) ?></div>
          </div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

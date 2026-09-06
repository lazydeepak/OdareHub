<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$payload = isset($payload) && is_array($payload) ? $payload : [];
$summary = isset($payload['summary']) && is_array($payload['summary']) ? $payload['summary'] : [];
$coverageRows = isset($payload['coverage_rows']) && is_array($payload['coverage_rows']) ? $payload['coverage_rows'] : [];
$assemblyPlans = isset($payload['assembly_plans']) && is_array($payload['assembly_plans']) ? $payload['assembly_plans'] : [];
$qcPlans = isset($payload['qc_plans']) && is_array($payload['qc_plans']) ? $payload['qc_plans'] : [];
$quickLinks = isset($payload['quick_links']) && is_array($payload['quick_links']) ? $payload['quick_links'] : [];
$date = (string)($payload['date'] ?? date('Y-m-d'));
$today = (string)($payload['today'] ?? date('Y-m-d'));
$redirect = '/apps/manufacturing/processing-operation?date=' . urlencode($date);
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

$routePolicy = function_exists('platform_user_access_policy_contract') ? platform_user_access_policy_contract() : null;
$canOpen = static function (string $url) use ($routePolicy, $viewer): bool {
  $path = trim((string)parse_url($url, PHP_URL_PATH));
  if ($path === '' || !is_object($routePolicy)) {
    return true;
  }
  $decision = $routePolicy->routeAccessDecision(is_array($viewer) ? $viewer : null, $path, 'GET');
  return (bool)($decision['allowed'] ?? false);
};
$quickLinks = array_values(array_filter($quickLinks, static fn(array $link): bool => $canOpen((string)($link['url'] ?? ''))));

$currentPriority = $coverageRows[0] ?? null;
$nextPriority = $coverageRows[1] ?? null;
$fmt = static fn(float $v): string => number_format($v, 2, '.', ',');
?>

<div class="card">
  <div class="mfg-page-header">
    <div class="ui-block">
      <h2 class="mfg-page-title"><?= e((string)t('module.processing_operation.title')) ?></h2>
      <div class="muted"><?= e((string)t('module.processing_operation.subtitle')) ?></div>
    </div>
    <div class="mfg-page-header-links">
      <?php foreach ($quickLinks as $link): ?>
        <a class="btn" href="<?= e((string)($link['url'] ?? '#')) ?>"><?= e((string)($link['label'] ?? t('common.open'))) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php if ($flash !== ''): ?><div class="card dsp-flash-ok"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="card dsp-flash-err"><?= e($error) ?></div><?php endif; ?>

<div class="stat-row">
  <div class="stat-box"><div class="stat-box-label"><?= e((string)t('module.processing_operation.current_priority')) ?></div><div class="stat-box-value"><?= e((string)($currentPriority['parts_name'] ?? t('module.processing_operation.no_active_priority'))) ?></div></div>
  <div class="stat-box"><div class="stat-box-label"><?= e((string)t('module.processing_operation.next_priority')) ?></div><div class="stat-box-value"><?= e((string)($nextPriority['parts_name'] ?? t('module.processing_operation.queue_clear'))) ?></div></div>
  <div class="stat-box"><div class="stat-box-label"><?= e((string)t('module.processing_operation.queue_status')) ?></div><div class="stat-box-value"><?= (int)($summary['coverage_count'] ?? 0) ?></div></div>
  <div class="stat-box"><div class="stat-box-label"><?= e((string)t('module.processing_operation.assembly_plans')) ?></div><div class="stat-box-value"><?= (int)($summary['assembly_count'] ?? 0) ?></div></div>
  <div class="stat-box"><div class="stat-box-label"><?= e((string)t('module.processing_operation.qc_plans_open', ['count' => (string)(int)count($qcPlans)])) ?></div><div class="stat-box-value"><?= count($qcPlans) ?></div></div>
</div>

<div class="card">
  <form method="get" action="/apps/manufacturing/processing-operation" class="mfg-filter-row">
    <div class="ui-block">
      <label class="muted mfg-filter-label"><?= e((string)t('common.date')) ?></label>
      <input class="input" type="date" name="date" value="<?= e($date) ?>">
    </div>
    <div class="row">
      <button class="btn" type="submit"><?= e((string)t('module.processing_operation.open_day')) ?></button>
      <?php if ($date !== $today): ?><a class="btn" href="/apps/manufacturing/processing-operation?date=<?= urlencode($today) ?>"><?= e((string)t('common.today')) ?></a><?php endif; ?>
    </div>
  </form>
</div>

<div class="card">
  <div class="section-head"><h2><?= e((string)t('module.processing_operation.processing_priorities')) ?></h2></div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th><?= e((string)t('common.part')) ?></th>
          <th><?= e((string)t('module.processing_operation.stage')) ?></th>
          <th><?= e((string)t('module.processing_operation.target')) ?></th>
          <th><?= e((string)t('module.processing_operation.done')) ?></th>
          <th><?= e((string)t('module.processing_operation.gap')) ?></th>
          <th><?= e((string)t('module.processing_operation.material')) ?></th>
          <th><?= e((string)t('module.processing_operation.risk')) ?></th>
          <th><?= e((string)t('module.processing_operation.action')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($coverageRows as $row): ?>
          <tr>
            <td><a href="<?= e((string)($row['part_url'] ?? '#')) ?>"><?= e((string)($row['parts_name'] ?? '-')) ?></a><div class="muted"><?= e((string)($row['parts_number'] ?? '')) ?></div></td>
            <td><?= e((string)($row['focus_stage'] ?? '-')) ?></td>
            <td><?= e($fmt((float)($row['target_qty'] ?? 0))) ?></td>
            <td><?= e($fmt((float)($row['completed_qty'] ?? 0))) ?></td>
            <td><?= e($fmt((float)($row['gap_qty'] ?? 0))) ?></td>
            <td><?= e((string)($row['material_status'] ?? t('module.processing_operation.material.balanced'))) ?></td>
            <td><?= e((string)($row['risk_label'] ?? t('module.processing_operation.risk.at_risk'))) ?></td>
            <td>
              <div class="row u-style-ca6f5db40b">
                <a class="btn" href="<?= e((string)($row['open_url'] ?? '#')) ?>"><?= e((string)($row['open_label'] ?? t('common.open'))) ?></a>
                <a class="btn" href="<?= e((string)($row['detail_url'] ?? '#')) ?>"><?= e((string)t('module.processing_operation.open_detail')) ?></a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if ($coverageRows === []): ?><tr><td colspan="8" class="muted"><?= e((string)t('module.processing_operation.no_risky_parts')) ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="section-head"><h2><?= e((string)t('module.processing_operation.assembly_plans')) ?></h2></div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th><?= e((string)t('common.part')) ?></th>
          <th><?= e((string)t('module.processing_operation.target')) ?></th>
          <th><?= e((string)t('module.processing_operation.completed')) ?></th>
          <th><?= e((string)t('module.processing_operation.remaining')) ?></th>
          <th><?= e((string)t('module.processing_operation.update')) ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($assemblyPlans as $row): ?>
        <tr>
          <td><a href="/apps/manufacturing/products/360?id=<?= (int)($row['product_id'] ?? 0) ?>"><?= e((string)($row['parts_name'] ?? '-')) ?></a><div class="muted"><?= e((string)($row['parts_number'] ?? '')) ?></div></td>
          <td><?= e($fmt((float)($row['target_qty'] ?? 0))) ?></td>
          <td><?= e($fmt((float)($row['completed_qty'] ?? 0))) ?></td>
          <td><?= e($fmt((float)($row['remaining_qty'] ?? 0))) ?></td>
          <td>
            <?php if (!$isReadOnlyView): ?>
              <form method="post" action="/apps/manufacturing/processing-operation/assembly-update" class="row u-style-482ada1d83">
                <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
                <input type="hidden" name="assembly_plan_id" value="<?= (int)($row['id'] ?? 0) ?>">
                <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
                <input class="input" type="number" name="completed_qty" step="0.01" min="0" value="<?= e(number_format((float)($row['completed_qty'] ?? 0), 2, '.', '')) ?>">
                <button class="btn ok" type="submit"><?= e((string)t('common.save')) ?></button>
              </form>
            <?php else: ?>
              <span class="muted"><?= e((string)t('module.processing_operation.read_only_view')) ?></span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($assemblyPlans === []): ?><tr><td colspan="5" class="muted"><?= e((string)t('module.processing_operation.no_assembly_plans')) ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="section-head"><h2><?= e((string)t('module.processing_operation.qc_plans_open', ['count' => (string)(int)count($qcPlans)])) ?></h2></div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th><?= e((string)t('common.part')) ?></th>
          <th><?= e((string)t('module.processing_operation.deadline')) ?></th>
          <th><?= e((string)t('module.processing_operation.target')) ?></th>
          <th><?= e((string)t('module.processing_operation.completed')) ?></th>
          <th><?= e((string)t('module.processing_operation.remaining')) ?></th>
          <th><?= e((string)t('module.processing_operation.update')) ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($qcPlans as $row): ?>
        <tr>
          <td><a href="/apps/manufacturing/products/360?id=<?= (int)($row['product_id'] ?? 0) ?>"><?= e((string)($row['parts_name'] ?? '-')) ?></a><div class="muted"><?= e((string)($row['parts_number'] ?? '')) ?></div></td>
          <td><?= e((string)($row['required_date'] ?? $date)) ?></td>
          <td><?= e($fmt((float)($row['target_qty'] ?? 0))) ?></td>
          <td><?= e($fmt((float)($row['checked_qty'] ?? 0))) ?></td>
          <td><?= e($fmt((float)($row['remaining_qty'] ?? 0))) ?></td>
          <td>
            <?php if (!$isReadOnlyView): ?>
              <form method="post" action="/apps/manufacturing/processing-operation/qc-update" class="row u-style-482ada1d83">
                <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
                <input type="hidden" name="qc_plan_id" value="<?= (int)($row['id'] ?? 0) ?>">
                <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
                <input class="input" type="number" name="checked_qty" step="0.01" min="0" value="<?= e(number_format((float)($row['checked_qty'] ?? 0), 2, '.', '')) ?>">
                <button class="btn ok" type="submit"><?= e((string)t('common.save')) ?></button>
              </form>
            <?php else: ?>
              <span class="muted"><?= e((string)t('module.processing_operation.read_only_view')) ?></span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($qcPlans === []): ?><tr><td colspan="6" class="muted"><?= e((string)t('module.processing_operation.no_actionable_qc')) ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
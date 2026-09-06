<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
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

$rows = isset($rows) && is_array($rows) ? $rows : [];
$products = isset($products) && is_array($products) ? $products : [];
$currentRow = $rows[0] ?? null;
$readyCount = 0;
$blockedCount = 0;
foreach ($rows as $row) {
  $rKey = ((int)($row['product_id'] ?? 0)) . '|' . ((string)($row['dispatch_date'] ?? '')) . '|' . ((int)($row['id'] ?? 0));
  $rData = ($readinessMap ?? [])[$rKey] ?? null;
  if ((bool)($rData['allowed'] ?? false)) {
    $readyCount++;
  } else {
    $blockedCount++;
  }
}

$statusLabelMap = [
  'draft' => (string)t('mfg.dsp.status.draft'),
  'ready' => (string)t('mfg.dsp.status.ready'),
  'prepared' => (string)t('mfg.dsp.status.prepared'),
  'completed' => (string)t('mfg.dsp.status.completed'),
];
?>

<div class="card">
  <div class="mfg-page-header">
    <div class="ui-block">
      <h2 class="mfg-page-title"><?= e((string)t('mfg.dsp.title')) ?></h2>
      <div class="muted"><?= e((string)t('mfg.dsp.subtitle')) ?></div>
    </div>
    <div class="mfg-page-header-links">
      <a class="btn ok" href="/apps/manufacturing/dispatch-ops/preparation"><?= e((string)t('mfg.dsp.link.prep_form')) ?></a>
      <a class="btn" href="/apps/manufacturing/stage-board"><?= e((string)t('mfg.dsp.link.stage_board')) ?></a>
      <a class="btn" href="/apps/manufacturing/demands"><?= e((string)t('mfg.dsp.link.demand_engine')) ?></a>
    </div>
  </div>
</div>

<div class="stat-row">
  <div class="stat-box">
    <div class="stat-box-label"><?= e((string)t('mfg.dsp.focus.current_job')) ?></div>
    <div class="stat-box-value"><?= e((string)($currentRow['parts_name'] ?? (string)t('mfg.dsp.focus.no_active_job'))) ?></div>
  </div>
  <div class="stat-box">
    <div class="stat-box-label"><?= e((string)t('mfg.dsp.focus.queue_status')) ?></div>
    <div class="stat-box-value"><?= count($rows) ?></div>
  </div>
  <div class="stat-box">
    <div class="stat-box-label"><?= e((string)t('mfg.portal.state.dispatch_ready_qty')) ?></div>
    <div class="stat-box-value <?= $readyCount > 0 ? 'u-tone-success' : '' ?>"><?= $readyCount ?></div>
  </div>
  <div class="stat-box">
    <div class="stat-box-label"><?= e((string)t('mfg.portal.state.dispatch_blocked_qty')) ?></div>
    <div class="stat-box-value <?= $blockedCount > 0 ? 'u-tone-danger' : 'u-tone-success' ?>"><?= $blockedCount ?></div>
  </div>
</div>

<?php if (!empty($flash ?? '')): ?><div class="card dsp-flash-ok"><?= e((string)$flash) ?></div><?php endif; ?>
<?php if (!empty($error ?? '')): ?><div class="card dsp-flash-err"><?= e((string)$error) ?></div><?php endif; ?>

<div class="card">
  <form method="get" action="/apps/manufacturing/dispatch-ops" class="mfg-filter-row">
    <div class="ui-block">
      <label class="muted mfg-filter-label"><?= e((string)t('mfg.dsp.filter.from')) ?></label>
      <input class="input" type="date" name="from_date" value="<?= e((string)($from_date ?? '')) ?>">
    </div>
    <div class="ui-block">
      <label class="muted mfg-filter-label"><?= e((string)t('mfg.dsp.filter.to')) ?></label>
      <input class="input" type="date" name="to_date" value="<?= e((string)($to_date ?? '')) ?>">
    </div>
    <div class="ui-block">
      <label class="muted mfg-filter-label"><?= e((string)t('mfg.dsp.filter.part')) ?></label>
      <select name="product_id">
        <option value="0"><?= e((string)t('mfg.dsp.filter.all_parts')) ?></option>
        <?php foreach ($products as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= ((int)($product_id ?? 0) === (int)$p['id']) ? 'selected' : '' ?>>
            <?= e((string)$p['parts_name']) ?> (<?= e((string)$p['parts_number']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="ui-block">
      <label class="muted mfg-filter-label"><?= e((string)t('mfg.dsp.filter.status')) ?></label>
      <select name="completion_status">
        <option value=""><?= e((string)t('mfg.dsp.filter.all')) ?></option>
        <option value="draft" <?= ((string)($completion_status ?? '') === 'draft') ? 'selected' : '' ?>><?= e((string)t('mfg.dsp.status.draft')) ?></option>
        <option value="ready" <?= ((string)($completion_status ?? '') === 'ready') ? 'selected' : '' ?>><?= e((string)t('mfg.dsp.status.ready')) ?></option>
        <option value="prepared" <?= ((string)($completion_status ?? '') === 'prepared') ? 'selected' : '' ?>><?= e((string)t('mfg.dsp.status.prepared')) ?></option>
        <option value="completed" <?= ((string)($completion_status ?? '') === 'completed') ? 'selected' : '' ?>><?= e((string)t('mfg.dsp.status.completed')) ?></option>
      </select>
    </div>
    <div class="row">
      <button class="btn" type="submit"><?= e((string)t('mfg.dsp.filter.apply')) ?></button>
      <a class="btn" href="/apps/manufacturing/dispatch-ops"><?= e((string)t('mfg.dsp.filter.reset')) ?></a>
    </div>
  </form>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th><?= e((string)t('mfg.dsp.col.date')) ?></th>
          <th><?= e((string)t('mfg.dsp.col.part')) ?></th>
          <th><?= e((string)t('mfg.dsp.col.qty')) ?></th>
          <th><?= e((string)t('mfg.dsp.col.status')) ?></th>
          <th><?= e((string)t('mfg.dsp.col.stage_eligibility')) ?></th>
          <th><?= e((string)t('mfg.dsp.col.actions')) ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <?php
          $rKey = ((int)($r['product_id'] ?? 0)) . '|' . ((string)($r['dispatch_date'] ?? '')) . '|' . ((int)($r['id'] ?? 0));
          $rData = ($readinessMap ?? [])[$rKey] ?? null;
          $allowed = (bool)($rData['allowed'] ?? false);
          $completion = strtolower(trim((string)($r['completion_status'] ?? 'draft')));
          $completionLabel = (string)($statusLabelMap[$completion] ?? (string)($r['completion_status'] ?? ''));
        ?>
        <tr>
          <td><?= e((string)($r['dispatch_date'] ?? '')) ?></td>
          <td><?= e((string)($r['parts_name'] ?? '')) ?> <span class="muted">(<?= e((string)($r['parts_number'] ?? '')) ?>)</span></td>
          <td><?= e((string)($r['dispatchable_qty'] ?? 0)) ?></td>
          <td><?= e($completionLabel) ?></td>
          <td>
            <?php if ($allowed): ?>
              <span class="u-tone-success"><?= e((string)t('mfg.dsp.eligibility.ready')) ?></span>
            <?php else: ?>
              <span class="u-tone-warning"><?= e((string)t('mfg.dsp.eligibility.blocked')) ?></span>
            <?php endif; ?>
            <?php if (!empty($rData['detail'])): ?><div class="muted"><?= e((string)$rData['detail']) ?></div><?php endif; ?>
          </td>
          <td>
            <div class="row u-style-ca6f5db40b">
              <a class="btn" href="/apps/manufacturing/dispatch-ops/preparation?dispatch_date=<?= urlencode((string)($r['dispatch_date'] ?? '')) ?>&product_id=<?= (int)($r['product_id'] ?? 0) ?>&daily_order_id=<?= (int)($r['daily_order_id'] ?? 0) ?>"><?= e((string)t('mfg.dsp.action.prep_form')) ?></a>
              <?php if (!$isReadOnlyView): ?>
                <form method="post" action="/apps/manufacturing/dispatch-ops/ready" class="dsp-form-ready">
                  <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
                  <input type="hidden" name="id" value="<?= (int)($r['id'] ?? 0) ?>">
                  <button class="btn" type="submit" <?= $allowed ? '' : 'disabled' ?>><?= e((string)t('mfg.dsp.action.ready')) ?></button>
                </form>
                <form method="post" action="/apps/manufacturing/dispatch-ops/complete" class="dsp-form-complete">
                  <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
                  <input type="hidden" name="id" value="<?= (int)($r['id'] ?? 0) ?>">
                  <button class="btn ok" type="submit" <?= $allowed ? '' : 'disabled' ?>><?= e((string)t('mfg.dsp.action.complete')) ?></button>
                </form>
              <?php else: ?>
                <span class="muted"><?= e((string)t('mfg.dsp.readonly_note')) ?></span>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($rows === []): ?>
        <tr><td colspan="6" class="muted"><?= e((string)t('mfg.dsp.empty')) ?></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
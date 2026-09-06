<?php declare(strict_types=1); ?>
<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php require __DIR__ . '/partials/surface_header.php'; ?>
<?php
$summary = is_array($summary ?? null) ? $summary : [];
$trackedStockCount = (int)($trackedStockCount ?? 0);
$openOrderCount = (int)($openOrderCount ?? 0);
$delayedOrderCount = (int)($delayedOrderCount ?? 0);
$materialsCount = (int)($summary['materials_count'] ?? 0);
$shortageCount = (int)($summary['shortage_risk_count'] ?? 0);
$delayCount = (int)($summary['incoming_delay_count'] ?? 0);
$actionNowCount = (int)($summary['action_now_count'] ?? 0);
?>

<section class="form-grid">
  <article class="card">
    <div class="muted">Tracked Materials</div>
    <div class="u-mt-8 u-style-193ca8b767"><?= number_format($materialsCount) ?></div>
    <div class="row u-mt-10">
      <a class="btn" href="/apps/manufacturing/materials/stock"> <?= e($tt('material_management.open_link')) ?> </a>
    </div>
  </article>
  <article class="card">
    <div class="muted">Open Inbound Orders</div>
    <div class="u-mt-8 u-style-193ca8b767"><?= number_format($openOrderCount) ?></div>
    <div class="row u-mt-10">
      <a class="btn" href="/apps/manufacturing/materials/orders">Manage Orders</a>
    </div>
  </article>
  <article class="card">
    <div class="muted">Incoming Delay Risk</div>
    <div class="u-mt-8 u-style-193ca8b767"><?= number_format($delayedOrderCount) ?></div>
    <div class="row u-mt-10">
      <a class="btn" href="/apps/manufacturing/materials/orders">Review Delays</a>
    </div>
  </article>
  <article class="card">
    <div class="muted">Action Now</div>
    <div class="u-mt-8 u-style-193ca8b767"><?= number_format($actionNowCount) ?></div>
    <div class="row u-mt-10">
      <a class="btn" href="/apps/manufacturing/materials/receipt">Record Receipt</a>
    </div>
  </article>
</section>

<section class="form-grid">
  <article class="card">
    <div class="section-head">
      <div class="ui-block">
        <h3 class="u-style-1169661891">Material v1 Surfaces</h3>
        <div class="muted">Only the live routes promoted by MaterialManagement right now.</div>
      </div>
    </div>
    <div class="u-mt-10">
      <div class="card">
        <strong>Material Orders</strong>
        <div class="muted"> <?= e($tt('material_management.dashboard_description')) ?> </div>
        <div class="row u-mt-10"><a class="btn" href="/apps/manufacturing/materials/orders">Open Orders</a></div>
      </div>
      <div class="card">
        <strong>Material Receipt</strong>
        <div class="muted">Canonical stock-in workflow for posted inbound material.</div>
        <div class="row u-mt-10"><a class="btn" href="/apps/manufacturing/materials/receipt">Open Receipt</a></div>
      </div>
      <div class="card">
        <strong>Material Stock</strong>
        <div class="muted">Truthful view of on hand, reserved, available, and incoming inventory.</div>
        <div class="row u-mt-10"><a class="btn" href="/apps/manufacturing/materials/stock"> <?= e($tt('material_management.open_link')) ?> </a></div>
      </div>
    </div>
  </article>

  <article class="card">
    <div class="section-head">
      <div class="ui-block">
        <h3 class="u-style-1169661891"> <?= e($tt('material_management.current_risk_summary_title')) ?> </h3>
        <div class="muted">Compact status only where the service layer already has supporting data.</div>
      </div>
    </div>
    <div class="u-mt-10">
      <div class="card">
        <strong>Shortage Risk</strong>
        <div class="muted"><?= number_format($shortageCount) ?> materials are currently in critical or low coverage bands.</div>
      </div>
      <div class="card">
        <strong>Delayed Incoming</strong>
        <div class="muted"><?= number_format($delayCount) ?> materials have delayed inbound signals from current order and planning data.</div>
      </div>
      <div class="card">
        <strong>Tracked Stock Rows</strong>
        <div class="muted"><?= number_format($trackedStockCount) ?> stock positions are available for review now.</div>
      </div>
    </div>
  </article>
</section>

<section class="card">
  <div class="section-head">
    <div class="ui-block">
      <h3 class="u-style-1169661891">Deferred From v1</h3>
      <div class="muted">Coverage, master data, mapping, planning, capacity, and cost remain module-owned but are intentionally not promoted as first-class runtime pages in this rebuild.</div>
    </div>
  </div>
</section>

</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

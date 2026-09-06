<?php
$recentOrders = is_array($recentOrders ?? null) ? $recentOrders : [];
$allOrdersUrl = trim((string)($allOrdersUrl ?? '/apps/manufacturing/daily-orders'));
$dispatchFeaturedItem = is_array($dispatchFeaturedItem ?? null) ? $dispatchFeaturedItem : null;
$quickLinkCards = is_array($quickLinkCards ?? null) ? $quickLinkCards : [];
$renderHostSection = is_callable($renderHostSection ?? null) ? $renderHostSection : static function (array $item): void {};
$blockOrderStyle = is_callable($blockOrderStyle ?? null) ? $blockOrderStyle : static fn(string $blockKey): string => '';
?>
<section class="me-dashboard-cockpit"<?= $blockOrderStyle('plugin_dashboards_charts') ?>>
  <article class="card me-dashboard-watch-card">
    <div class="section-head">
      <h4><?= e(t('admin.dashboard.orders.title')) ?></h4>
      <a class="btn" href="<?= e($allOrdersUrl) ?>"><?= e(t('admin.dashboard.orders.action_all')) ?></a>
    </div>
    <?php if ($recentOrders === []): ?>
      <div class="muted"><?= e(t('admin.dashboard.orders.empty')) ?></div>
    <?php else: ?>
      <div class="me-orders-list">
        <?php foreach ($recentOrders as $order): ?>
          <?php
            $orderUrl = trim((string)($order['url'] ?? ''));
            $orderTagName = $orderUrl !== '' ? 'a' : 'div';
            $statusTone = strtolower(trim((string)($order['status_tone'] ?? 'info')));
            $statusClass = match ($statusTone) {
                'success' => 'success',
                'warning' => 'warning',
                'danger' => 'danger',
                'neutral' => 'neutral',
                default => 'info',
            };
          ?>
          <<?= $orderTagName ?> class="me-order-item<?= $orderUrl !== '' ? ' is-clickable' : '' ?>"<?= $orderUrl !== '' ? ' href="' . e($orderUrl) . '"' : '' ?>>
            <div class="row-sb">
              <div class="me-order-copy">
                <strong class="me-order-code"><?= e((string)($order['code'] ?? t('admin.dashboard.orders.fallback_item'))) ?></strong>
                <div class="me-order-party"><?= e((string)($order['party'] ?? '')) ?></div>
              </div>
              <span class="status-chip <?= e($statusClass) ?>"><?= e((string)($order['status'] ?? t('admin.dashboard.orders.fallback_status'))) ?></span>
            </div>
            <div class="row-sb-center">
              <div class="muted me-order-detail"><?= e((string)($order['detail'] ?? '')) ?></div>
              <?php if (trim((string)($order['amount'] ?? '')) !== ''): ?>
                <strong class="me-order-amount"><?= e((string)$order['amount']) ?></strong>
              <?php endif; ?>
            </div>
          </<?= $orderTagName ?>>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </article>

  <?php if (is_array($dispatchFeaturedItem)): ?>
    <div class="me-dashboard-dispatch-slot">
      <?php $renderHostSection($dispatchFeaturedItem); ?>
    </div>
  <?php else: ?>
    <article class="card me-dashboard-dispatch-card is-empty">
      <div class="section-head">
        <h4><?= e(t('admin.dashboard.dispatch.title')) ?></h4>
      </div>
      <div class="muted"><?= e(t('admin.dashboard.dispatch.empty')) ?></div>
    </article>
  <?php endif; ?>

  <?php if ($quickLinkCards !== []): ?>
    <article class="card me-dashboard-watch-card">
      <div class="section-head">
        <h4><?= e(t('dashboard.quick_links_title')) ?></h4>
      </div>
      <div class="muted"><?= e(t('dashboard.quick_links_subtitle')) ?></div>
      <div class="acg-chip-row u-style-56f4356299">
        <?php foreach ($quickLinkCards as $quickLink): ?>
          <?php
            $quickLinkUrl = trim((string)($quickLink['url'] ?? ''));
            $quickLinkLabel = trim((string)($quickLink['label'] ?? $quickLink['title'] ?? $quickLinkUrl));
          ?>
          <?php if ($quickLinkUrl === '' || $quickLinkLabel === '') { continue; } ?>
          <a class="acg-chip" href="<?= e($quickLinkUrl) ?>"><?= e($quickLinkLabel) ?></a>
        <?php endforeach; ?>
      </div>
    </article>
  <?php endif; ?>
</section>

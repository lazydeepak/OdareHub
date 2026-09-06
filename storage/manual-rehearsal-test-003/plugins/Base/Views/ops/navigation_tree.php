<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$tree = is_array($tree ?? null) ? $tree : [];
$summary = is_array($tree['summary'] ?? null) ? $tree['summary'] : [];
$dashboards = is_array($tree['dashboards'] ?? null) ? $tree['dashboards'] : [];
$sidebarItems = is_array($tree['sidebar_items'] ?? null) ? $tree['sidebar_items'] : [];
$routesTree = is_array($tree['routes_tree'] ?? null) ? $tree['routes_tree'] : [];
$actionsTree = is_array($tree['actions_tree'] ?? null) ? $tree['actions_tree'] : [];
$orphans = is_array($tree['orphans'] ?? null) ? $tree['orphans'] : [];
$usageTokens = is_array($tree['usage_tokens'] ?? null) ? $tree['usage_tokens'] : [];

$summaryCards = [
  ['label' => 'GET Routes', 'key' => 'total_get_routes', 'tone' => '', 'action' => '#routes-tree', 'action_label' => 'View'],
  ['label' => 'POST Routes', 'key' => 'total_post_routes', 'tone' => '', 'action' => '#post-actions', 'action_label' => 'View'],
  ['label' => 'Sidebar Links', 'key' => 'sidebar_links', 'tone' => '', 'action' => '#sidebar-links', 'action_label' => 'View'],
  ['label' => 'Dashboard Routes', 'key' => 'dashboard_routes', 'tone' => '', 'action' => '#dashboard-routes', 'action_label' => 'View'],
  ['label' => 'Sidebar Missing Route', 'key' => 'sidebar_missing_routes', 'tone' => 'color: var(--color-danger-text)', 'action' => '#sidebar-links', 'action_label' => 'Fix'],
  ['label' => 'Sidebar POST-only Link', 'key' => 'sidebar_post_only_links', 'tone' => 'color: var(--color-warning-text)', 'action' => '#sidebar-links', 'action_label' => 'Review'],
  ['label' => 'Missing Dashboard Route', 'key' => 'missing_dashboard_routes', 'tone' => 'color: var(--color-danger-text)', 'action' => '#dashboard-routes', 'action_label' => 'Fix'],
  ['label' => 'Orphan Ops/Admin GET', 'key' => 'orphans_ops_admin', 'tone' => 'color: var(--color-warning-text)', 'action' => '#orphans', 'action_label' => 'Review'],
  ['label' => 'View Tokens', 'key' => 'view_tokens', 'tone' => '', 'action' => '#usage-tokens', 'action_label' => 'Inspect'],
  ['label' => 'Table Tokens', 'key' => 'table_tokens', 'tone' => '', 'action' => '#usage-tokens', 'action_label' => 'Inspect'],
  ['label' => 'Chart Tokens', 'key' => 'chart_tokens', 'tone' => '', 'action' => '#usage-tokens', 'action_label' => 'Inspect'],
];

$statusTone = static function (string $status): string {
    return match ($status) {
        'ok' => 'color: var(--color-success-text)',
        'misused_post_only' => 'color: var(--color-warning-text)',
        default => 'color: var(--color-danger-text)',
    };
};
?>

<section class="card u-style-ea75cdd499">
  <div class="section-head u-style-fdf33f2304">
    <h2 class="u-style-1169661891">System Navigation Tree</h2>
    <div class="muted u-style-96ad6099e2">Inventory for links/pages/forms/dashboards/charts with route integrity and usage signals.</div>
  </div>
  <div class="row u-style-4725bb7117">
    <a class="btn" href="/admin"><?= e(t('admin.launcher.title')) ?></a>
    <a class="btn" href="/admin/routes">Routes</a>
    <a class="btn" href="/admin/apps">Admin Tools</a>
    <a class="btn" href="/ops/access-control">Access Control Board</a>
  </div>
</section>

<section class="card">
  <div class="section-head"><h2>Summary</h2></div>
  <div class="coverage-kpi-grid">
    <?php foreach ($summaryCards as $card): ?>
      <div class="coverage-kpi u-style-18eb12f877">
        <div class="muted"><?= e((string)($card['label'] ?? 'Metric')) ?></div>
        <div class="coverage-kpi-value" style="<?= e((string)($card['tone'] ?? '')) ?>"><?= (int)($summary[(string)($card['key'] ?? '')] ?? 0) ?></div>
        <div class="u-style-1c53f0a55e">
          <a class="btn" href="<?= e((string)($card['action'] ?? '#')) ?>" style="font-size:.72em;padding:2px 8px"><?= e((string)($card['action_label'] ?? 'Open')) ?></a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="card" id="dashboard-routes">
  <div class="section-head"><h2>Dashboard Routes</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Dashboard Type</th><th>URL</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($dashboards as $row): ?>
        <?php $ok = (bool)($row['exists'] ?? false); ?>
        <tr>
          <td><?= e((string)($row['type'] ?? '')) ?></td>
          <td><a href="<?= e((string)($row['url'] ?? '/')) ?>"><?= e((string)($row['url'] ?? '/')) ?></a></td>
          <td style="<?= $ok ? 'color: var(--color-success-text)' : 'color: var(--color-danger-text)' ?>"><?= $ok ? 'ok' : 'missing route' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card" id="sidebar-links">
  <div class="section-head"><h2>Sidebar Navigation Links</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Section</th><th>Group</th><th>Key</th><th>Visible If</th><th>URL</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($sidebarItems as $row): ?>
        <?php $status = (string)($row['status'] ?? ''); ?>
        <tr>
          <td><?= e((string)($row['section'] ?? '')) ?></td>
          <td><?= e((string)($row['group'] ?? '')) ?></td>
          <td><?= e((string)($row['key'] ?? '')) ?></td>
          <td><code><?= e((string)($row['visible_if'] ?? 'always')) ?></code></td>
          <td><a href="<?= e((string)($row['url'] ?? '/')) ?>"><?= e((string)($row['url'] ?? '/')) ?></a></td>
          <td style="<?= $statusTone($status) ?>"><?= e($status) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card" id="routes-tree">
  <div class="section-head"><h2>Routes Tree (GET)</h2></div>
  <?php foreach ($routesTree as $segment => $items): ?>
    <details class="u-style-761d3addb2">
      <summary><strong>/<?= e((string)$segment) ?></strong> <span class="muted">(<?= count((array)$items) ?>)</span></summary>
      <ul class="u-style-cf7c0b1927">
        <?php foreach ((array)$items as $route): ?>
          <li><a href="<?= e((string)$route) ?>"><code><?= e((string)$route) ?></code></a></li>
        <?php endforeach; ?>
      </ul>
    </details>
  <?php endforeach; ?>
</section>

<section class="card" id="post-actions">
  <div class="section-head"><h2>Forms and Action Endpoints (POST)</h2></div>
  <?php foreach ($actionsTree as $segment => $items): ?>
    <details class="u-style-761d3addb2">
      <summary><strong>/<?= e((string)$segment) ?></strong> <span class="muted">(<?= count((array)$items) ?>)</span></summary>
      <ul class="u-style-cf7c0b1927">
        <?php foreach ((array)$items as $route): ?>
          <li><code><?= e((string)$route) ?></code></li>
        <?php endforeach; ?>
      </ul>
    </details>
  <?php endforeach; ?>
</section>

<section class="card" id="orphans">
  <div class="section-head"><h2>Potentially Unused / Unmapped Ops/Admin Routes</h2></div>
  <?php if (empty($orphans)): ?>
    <div class="muted">No orphan ops/admin GET routes detected from current references.</div>
  <?php else: ?>
    <ul class="u-style-592d196c45">
      <?php foreach ($orphans as $route): ?>
        <li><a href="<?= e((string)$route) ?>"><code><?= e((string)$route) ?></code></a></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<section class="card" id="usage-tokens">
  <div class="section-head"><h2>Assigned Views / Tables / Charts Usage</h2></div>
  <div class="row u-style-c5cbe0687f">
    <?php foreach (['view' => 'Views', 'table' => 'Tables', 'chart' => 'Charts'] as $kind => $label): ?>
      <?php $rows = is_array($usageTokens[$kind] ?? null) ? $usageTokens[$kind] : []; ?>
      <div class="u-style-9405df25bc">
        <h3 class="u-style-df671843ff"><?= e($label) ?></h3>
        <?php if (empty($rows)): ?>
          <div class="muted">No tokens assigned.</div>
        <?php else: ?>
          <ul class="u-style-592d196c45">
            <?php foreach ($rows as $row): ?>
              <li><code><?= e((string)($row['token'] ?? '')) ?></code> <span class="muted">(<?= (int)($row['count'] ?? 0) ?> users)</span></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

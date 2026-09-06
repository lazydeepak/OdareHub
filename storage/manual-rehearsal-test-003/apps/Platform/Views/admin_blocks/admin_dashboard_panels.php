<?php
$adminDashboardPanels = is_array($adminDashboardPanels ?? null) ? array_values(array_filter($adminDashboardPanels, 'is_array')) : [];
$businessDominantApp = strtolower(trim((string)($businessDominantApp ?? '')));
$experienceMode = (string)($experienceMode ?? '');
$dashboardType = (string)($dashboardType ?? '');
$authorityRole = (string)($authorityRole ?? '');
$blockOrderStyle = is_callable($blockOrderStyle ?? null) ? $blockOrderStyle : static fn(string $blockKey): string => '';
$preferredAdminPanel = null;

if ($adminDashboardPanels !== []) {
  if ($businessDominantApp !== '' && $businessDominantApp !== 'platform') {
    foreach ($adminDashboardPanels as $candidatePanel) {
      if (strtolower(trim((string)($candidatePanel['key'] ?? ''))) === 'app_admin') {
        $preferredAdminPanel = $candidatePanel;
        break;
      }
    }
  }

  if (!is_array($preferredAdminPanel)) {
    $preferredAdminPanel = $adminDashboardPanels[0];
  }
}

$panel = is_array($preferredAdminPanel) ? $preferredAdminPanel : ($adminDashboardPanels[0] ?? []);
$panelUrl = trim((string)($panel['url'] ?? ''));
$panelCards = array_values(array_filter((array)($panel['cards'] ?? []), 'is_array'));
$panelLinks = array_values(array_filter((array)($panel['quick_links'] ?? []), 'is_array'));
$panelSections = array_values(array_filter((array)($panel['sections'] ?? []), 'is_array'));
$panelPlaceholders = array_values(array_filter((array)($panel['placeholders'] ?? []), 'is_string'));
$panelTitle = trim((string)($panel['title'] ?? ''));
$panelSubtitle = trim((string)($panel['subtitle'] ?? ''));
?>
<section class="card me-dashboard-hero"<?= $blockOrderStyle('admin_dashboard_panels') ?>>
  <div class="me-dashboard-hero-head">
    <div class="ui-block">
      <h2 class="me-dashboard-title"><?= e($panelTitle !== '' ? $panelTitle : t('admin.dashboard.title')) ?></h2>
      <p class="muted me-dashboard-subtitle"><?= e($panelSubtitle !== '' ? $panelSubtitle : t('admin.dashboard.subtitle')) ?></p>
    </div>
    <div class="me-dashboard-badges">
      <?php foreach (array_unique(array_filter([strtoupper($experienceMode), strtoupper($dashboardType), strtoupper($authorityRole)])) as $_badge): ?>
        <span class="mapping-label"><?= e($_badge) ?></span>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="me-dashboard-grid me-fit-grid">
    <article class="card me-fit-card me-fit-medium">
      <?php if ($panelUrl !== ''): ?>
        <div class="row-sb-center u-style-1c0bb6f512">
          <a class="btn" href="<?= e($panelUrl) ?>"><?= e(t('common.open')) ?></a>
        </div>
      <?php endif; ?>
      <?php if ($panelCards !== []): ?>
        <div class="me-dashboard-kpi-grid">
          <?php foreach ($panelCards as $card): ?>
            <article class="me-dashboard-kpi-card">
              <div class="me-kpi-header">
                <div class="me-kpi-label muted"><?= e((string)($card['label'] ?? '')) ?></div>
              </div>
              <div class="me-kpi-main">
                <strong class="me-kpi-value"><?= e((string)($card['value'] ?? '0')) ?></strong>
              </div>
              <?php if (trim((string)($card['meta'] ?? '')) !== ''): ?>
                <div class="me-dashboard-kpi-meta"><?= e((string)($card['meta'] ?? '')) ?></div>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if ($panelLinks !== []): ?>
        <div class="acg-chip-row u-style-56f4356299">
          <?php foreach ($panelLinks as $link): ?>
            <?php $linkUrl = trim((string)($link['url'] ?? '')); ?>
            <?php if ($linkUrl === '') { continue; } ?>
            <a class="acg-chip" href="<?= e($linkUrl) ?>"><?= e((string)($link['label'] ?? $linkUrl)) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php foreach ($panelSections as $panelSection): ?>
        <div class="admin-panel-section">
          <h3><?= e((string)($panelSection['title'] ?? t('ops.role_dashboard.section'))) ?></h3>
          <dl class="detail-grid">
            <?php foreach ((array)($panelSection['items'] ?? []) as $panelItem): ?>
              <?php if (!is_array($panelItem)) { continue; } ?>
              <div class="detail-item"><dt><?= e((string)($panelItem['label'] ?? '')) ?></dt><dd><?= e((string)($panelItem['value'] ?? '')) ?></dd></div>
            <?php endforeach; ?>
          </dl>
        </div>
      <?php endforeach; ?>
      <?php if ($panelPlaceholders !== []): ?>
        <div class="admin-panel-notes">
          <?php foreach ($panelPlaceholders as $panelPlaceholder): ?><p class="muted"><?= e($panelPlaceholder) ?></p><?php endforeach; ?>
        </div>
      <?php endif; ?>
    </article>
  </div>
</section>

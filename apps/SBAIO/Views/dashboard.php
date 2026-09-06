<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$suiteRoleTemplateLabels = is_array($suite_role_template_labels ?? null) ? $suite_role_template_labels : [];
$modulePermissionTemplateLabels = is_array($module_permission_template_labels ?? null) ? $module_permission_template_labels : [];
?>

<section class="card">
  <div class="section-head">
    <div class="ui-block">
      <h2 class="u-style-1169661891"><?= e(t('nav.sbaio_dashboard')) ?></h2>
      <div class="muted u-style-fe7b4979fe"><?= e(t('sbaio.dashboard.subtitle')) ?></div>
    </div>
    <div class="u-style-3de8f987ba">
      <a class="btn" href="/apps/sbaio/imports"><?= e(t('sbaio.dashboard.legacy_imports')) ?></a>
      <a class="btn" href="/apps/sbaio/exports"><?= e(t('sbaio.dashboard.exports')) ?></a>
      <a class="btn" href="/apps/sbaio/restores"><?= e(t('sbaio.dashboard.restores')) ?></a>
    </div>
  </div>

  <?php if (!empty($heroStats)): ?>
    <div class="hero-meta u-style-56f4356299">
      <?php foreach ((array)$heroStats as $stat): ?>
        <div class="hero-meta-card">
          <div class="muted"><?= e((string)($stat['label'] ?? '')) ?></div>
          <div class="hero-meta-value"><?= e((string)($stat['value'] ?? '0')) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php if ($suiteRoleTemplateLabels !== [] || $modulePermissionTemplateLabels !== []): ?>
    <div class="row u-style-4aa9f07ad6">
      <?php foreach ($suiteRoleTemplateLabels as $label): ?>
        <span class="pill"><?= e((string)$label) ?></span>
      <?php endforeach; ?>
      <?php foreach ($modulePermissionTemplateLabels as $label): ?>
        <span class="pill"><?= e((string)$label) ?></span>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php foreach ((array)($sections ?? []) as $section): ?>
  <section class="card">
    <div class="section-head">
      <div class="ui-block">
        <h3 class="u-style-1169661891"><?= e((string)($section['title'] ?? 'SBAIO')) ?></h3>
        <?php if (!empty($section['subtitle'])): ?>
          <div class="muted u-style-fe7b4979fe"><?= e((string)$section['subtitle']) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="app-quicklink-row u-style-3f367c0031">
      <?php foreach ((array)($section['cards'] ?? []) as $card): ?>
        <?php $quickLinks = is_array($card['quick_links'] ?? null) ? (array)$card['quick_links'] : []; ?>
        <div class="app-quicklink-card u-style-a56c85e3df">
          <a class="sbaio-dashboard-card-link" href="<?= e((string)($card['url'] ?? '/apps/sbaio')) ?>">
            <span class="app-quicklink-label"><?= e((string)($card['label'] ?? '')) ?></span>
            <span class="app-quicklink-sub muted"><?= e((string)($card['desc'] ?? '')) ?></span>
          </a>
          <div class="u-style-df79cb0ad9">
              <div class="ui-block">
              <div class="muted u-style-01fee85ce8"><?= e(t('sbaio.common.count')) ?></div>
              <div class="u-style-0b92fe7b61"><?= e((string)($card['count'] ?? '0')) ?></div>
            </div>
            <?php if (!empty($card['state'])): ?>
              <span class="pill"><?= e((string)$card['state']) ?></span>
            <?php endif; ?>
          </div>
          <?php if ($quickLinks !== []): ?>
            <div class="u-style-c21c70e35c">
              <?php foreach ($quickLinks as $link): ?>
                <a class="btn sbaio-dashboard-quicklink-btn" href="<?= e((string)($link['url'] ?? ($card['url'] ?? '/apps/sbaio'))) ?>">
                  <?= e((string)($link['label'] ?? t('sbaio.common.open'))) ?>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

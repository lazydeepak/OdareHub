<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$summary = is_array($summary ?? null) ? $summary : [];
$company = is_array($summary['company'] ?? null) ? $summary['company'] : null;
$branchStats = is_array($summary['branch_stats'] ?? null) ? $summary['branch_stats'] : ['total_count' => 0, 'active_count' => 0];
$fiscal = is_array($summary['fiscal'] ?? null) ? $summary['fiscal'] : [];
$branding = is_array($summary['branding'] ?? null) ? $summary['branding'] : [];
$pageHeading = (string)t('organization.title');
$pageDescription = (string)t('organization.description.overview');
require __DIR__ . '/partials/nav.php';
require __DIR__ . '/partials/feedback.php';
?>

<div class="u-style-8af28ca1c8">
  <section class="card u-style-1169661891">
    <div class="muted"><?= e(t('organization.nav.company')) ?></div>
    <h3 class="u-style-8789337586"><?= $company ? e((string)($company['company_name'] ?? '')) : e(t('organization.not_configured')) ?></h3>
    <div class="muted">
      <?= $company ? e((string)($company['company_code'] ?? '')) . ' · ' . e((string)($company['base_currency'] ?? '')) . ' · ' . e((string)($company['timezone'] ?? '')) : e(t('organization.overview.company_hint')) ?>
    </div>
    <div class="u-style-a2615153a8">
      <a class="btn ok" href="/ops/organization/company"><?= e($canManage ? t('organization.action.manage') : t('organization.action.open')) ?></a>
    </div>
  </section>

  <section class="card u-style-1169661891">
    <div class="muted"><?= e(t('organization.nav.branches')) ?></div>
    <h3 class="u-style-8789337586"><?= e(t('organization.overview.branches_total', ['count' => (int)($branchStats['total_count'] ?? 0)])) ?></h3>
    <div class="muted"><?= e(t('organization.overview.branches_active', ['count' => (int)($branchStats['active_count'] ?? 0)])) ?></div>
    <div class="u-style-a2615153a8">
      <a class="btn ok" href="/ops/organization/branches"><?= e($canManage ? t('organization.action.manage') : t('organization.action.open')) ?></a>
    </div>
  </section>

  <section class="card u-style-1169661891">
    <div class="muted"><?= e(t('organization.nav.fiscal')) ?></div>
    <h3 class="u-style-8789337586">
      <?= !empty($fiscal['is_configured']) ? e((string)$fiscal['fiscal_year_start']) . ' → ' . e((string)($fiscal['fiscal_year_end'] ?? '')) : e(t('organization.not_configured')) ?>
    </h3>
    <div class="muted">
      <?= e(t('organization.overview.fiscal_tax_mode', ['mode' => (string)($fiscal['default_tax_mode'] ?? 'exclusive')])) ?>
      <?php if (!empty($fiscal['invoice_prefix'] ?? '') && !empty($fiscal['is_configured'])): ?>
        · <?= e(t('organization.overview.invoice_prefix', ['prefix' => (string)$fiscal['invoice_prefix']])) ?>
      <?php endif; ?>
    </div>
    <div class="u-style-a2615153a8">
      <a class="btn ok" href="/ops/organization/fiscal"><?= e($canManage ? t('organization.action.manage') : t('organization.action.open')) ?></a>
    </div>
  </section>

  <section class="card u-style-1169661891">
    <div class="muted"><?= e(t('organization.nav.branding')) ?></div>
    <h3 class="u-style-8789337586"><?= !empty($branding['is_configured']) && !empty($branding['short_brand_name'] ?? '') ? e((string)$branding['short_brand_name']) : e(t('organization.not_configured')) ?></h3>
    <div class="muted">
      <?= !empty($branding['is_configured']) && !empty($branding['report_header_text'] ?? '') ? e((string)$branding['report_header_text']) : e(t('organization.overview.branding_hint')) ?>
    </div>
    <div class="u-style-a2615153a8">
      <a class="btn ok" href="/ops/organization/branding"><?= e($canManage ? t('organization.action.manage') : t('organization.action.open')) ?></a>
    </div>
  </section>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

<?php
$tt = static function (string $key): string {
  return t($key);
};
require_once APP_ROOT . '/apps/Platform/Services/ModuleReportRegistryService.php';
require_once APP_ROOT . '/apps/Platform/modules/Organization/Services/OrganizationService.php';

use Apps\Platform\Services\ModuleReportRegistryService;
use Plugins\Organization\Services\OrganizationService;

$report = ModuleReportRegistryService::findActiveReport('platform.organization.overview');

$companies = [];
$summary = ['branch_stats' => ['total_count' => 0, 'active_count' => 0]];
if (is_array($report)) {
    try {
        $companies = OrganizationService::listCompanies();
        $summary = OrganizationService::landingSummary();
    } catch (\Throwable $e) {
        $companies = [];
    }
}
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-style-1169661891"> <?= e($tt('organization.organization_report_title')) ?> </h2>
      <div class="muted"> <?= e($tt('organization.report_description')) ?> </div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/ops/organization">&larr; Organization</a>
    </div>
  </div>
</div>

<div class="card">
  <?php if (is_array($report)): ?>
    <div class="row u-style-f679ea63c2">
      <div class="ui-block">
        <div class="ui-block"><strong><?= e($tt('organization.report_key_label')) ?>:</strong> <?= e((string)($report['report_key'] ?? '-')) ?></div>
        <div class="muted"><strong>Owner:</strong> <?= e((string)($report['owner'] ?? 'module')) ?> &middot; <strong>Scope:</strong> <?= e((string)($report['scope'] ?? '-')) ?></div>
      </div>
      <div class="ui-block">
        <a class="btn" href="/ops/organization/export"> <?= e($tt('organization.open_link')) ?> </a>
      </div>
    </div>
  <?php else: ?>
    <p class="muted u-style-1169661891"> <?= e($tt('organization.report_unavailable_message')) ?> </p>
  <?php endif; ?>
</div>

<?php if (is_array($report)): ?>
<div class="card">
  <h3 class="u-style-d462248a40"> <?= e($tt('organization.organization_snapshot_title')) ?> </h3>
  <div class="row u-style-034ef58065">
    <div class="ui-block"><div class="muted">Companies</div><div class="u-style-b9199e22b2"><strong><?= (int)count($companies) ?></strong></div></div>
    <div class="ui-block"><div class="muted">Total Branches</div><div class="u-style-b9199e22b2"><strong><?= (int)($summary['branch_stats']['total_count'] ?? 0) ?></strong></div></div>
    <div class="ui-block"><div class="muted"> <?= e($tt('organization.active_branches_message')) ?> </div><div class="u-style-b9199e22b2"><strong><?= (int)($summary['branch_stats']['active_count'] ?? 0) ?></strong></div></div>
  </div>
</div>

<div class="card">
  <h3 class="u-style-d462248a40">Companies</h3>
  <?php if (!$companies): ?>
    <p class="muted u-style-1169661891">No companies configured.</p>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>Company Code</th><th>Name</th><th>Primary</th><th> <?= e($tt('organization.active_column')) ?> </th></tr></thead>
      <tbody>
        <?php foreach ($companies as $c): ?>
          <tr>
            <td><?= e((string)($c['company_code'] ?? '')) ?></td>
            <td><?= e((string)($c['company_name'] ?? '')) ?></td>
            <td><?= !empty($c['is_primary']) ? 'Yes' : '-' ?></td>
            <td><?= !empty($c['is_active']) ? 'Yes' : 'No' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

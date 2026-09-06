<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-style-1169661891"><?= e(t('system_tools.data_control.title')) ?></h2>
      <div class="muted"><?= e(t('system_tools.data_control.subtitle')) ?></div>
      <?php if (!empty($canEdit) || !empty($canGovernApprovals)): ?>
        <div class="u-style-45e1866d15">
          <?php
            $permissions = [];
            if (!empty($canEdit)) {
              $permissions[] = t('system_tools.data_control.role_editor');
            }
            if (!empty($canGovernApprovals)) {
              $permissions[] = t('system_tools.data_control.role_governor');
            }
          ?>
          <?= e(t('system_tools.data_control.your_roles')) ?>: <?= e(implode(', ', $permissions)) ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card">
  <div class="stats-grid">
    <article class="stat-card">
      <div class="stat-label"><?= e(t('system_tools.data_control.total_tables')) ?></div>
      <div class="stat-value"><?= (int)($dbTableCount ?? 0) ?></div>
    </article>
    <article class="stat-card">
      <div class="stat-label"><?= e(t('system_tools.data_control.total_views')) ?></div>
      <div class="stat-value"><?= (int)($dbViewCount ?? 0) ?></div>
    </article>
  </div>
</div>

<div class="card">
  <div class="dashboard-grid">
    <a class="dashboard-link-card" href="/admin/base">
      <div class="dashboard-link-top"><strong><?= e(t('system_tools.data_control.open_base_builder')) ?></strong></div>
      <div class="muted"><?= e(t('system_tools.data_control.base_builder_hint')) ?></div>
    </a>
    <a class="dashboard-link-card" href="/admin/routes">
      <div class="dashboard-link-top"><strong><?= e(t('system_tools.data_control.open_routes_manager')) ?></strong></div>
      <div class="muted"><?= e(t('system_tools.data_control.routes_hint')) ?></div>
    </a>
  </div>
</div>

<div class="card">
  <h3 class="u-style-d462248a40"><?= e(t('system_tools.data_control.table_catalog')) ?></h3>
  <?php if (!empty($tableRows)): ?>
    <div class="u-style-a632715a5f">
      <table class="table">
        <thead>
          <tr>
            <th><?= e(t('base.db_control.table_name')) ?></th>
            <th><?= e(t('system_tools.data_control.estimated_rows')) ?></th>
            <th><?= e(t('system_tools.data_control.engine')) ?></th>
            <th><?= e(t('common.updated')) ?></th>
            <?php if (!empty($canEdit)): ?>
              <th><?= e(t('common.actions')) ?></th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($tableRows as $row): ?>
            <tr>
              <td><?= e((string)($row['table_name'] ?? '')) ?></td>
              <td><?= (int)($row['table_rows'] ?? 0) ?></td>
              <td><?= e((string)($row['engine'] ?? '')) ?></td>
              <td><?= e((string)($row['update_time'] ?? '')) ?></td>
              <?php if (!empty($canEdit)): ?>
                <td>
                  <a class="action-link data-control-action" href="/admin/system-tools/data-control/browse?table=<?= urlencode((string)($row['table_name'] ?? '')) ?>">
                    <?= e(t('system_tools.data_control.browse')) ?>
                  </a>
                  <a class="action-link data-control-action" href="/admin/system-tools/data-control/preview-mutation?table=<?= urlencode((string)($row['table_name'] ?? '')) ?>&amp;op=update">
                    <?= e(t('system_tools.data_control.action_preview_update')) ?>
                  </a>
                  <a class="action-link data-control-action data-control-action--danger" href="/admin/system-tools/data-control/preview-mutation?table=<?= urlencode((string)($row['table_name'] ?? '')) ?>&amp;op=delete">
                    <?= e(t('system_tools.data_control.action_preview_delete')) ?>
                  </a>
                </td>
              <?php else: ?>
                <td>
                  <a class="action-link" href="/admin/system-tools/data-control/browse?table=<?= urlencode((string)($row['table_name'] ?? '')) ?>">
                    <?= e(t('system_tools.data_control.browse')) ?>
                  </a>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="muted"><?= e(t('system_tools.data_control.no_tables')) ?></div>
  <?php endif; ?>
</div>

<div class="card">
  <h3 class="u-style-d462248a40"><?= e(t('system_tools.data_control.view_catalog')) ?></h3>
  <?php if (!empty($viewRows)): ?>
    <div class="u-style-a632715a5f">
      <table class="table">
        <thead>
          <tr>
            <th><?= e(t('base.db_control.table_name')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($viewRows as $row): ?>
            <tr>
              <td><?= e((string)($row['table_name'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="muted"><?= e(t('system_tools.data_control.no_views')) ?></div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="muted"><?= e(t('system_tools.data_control.note')) ?></div>
</div>

<?php if (!empty($canGovernApprovals)): ?>
  <div class="card">
    <h3 class="u-style-d462248a40"><?= e(t('system_tools.data_control.governance_title')) ?></h3>
    <div class="muted"><?= e(t('system_tools.data_control.governance_hint')) ?></div>
    <div class="u-style-97fe8c0f0f">
      <a class="u-style-681d194542" href="/admin/system-tools/data-control/approvals">
        <div class="u-style-8820e83522">
          <?= e(t('system_tools.data_control.pending_approvals')) ?>
          <?php if (!empty($pendingCount) && $pendingCount > 0): ?>
            <span class="u-style-07a3cb9a00"><?= (int)$pendingCount ?></span>
          <?php endif; ?>
        </div>
        <div class="u-style-8d9785d68f"><?= e(t('system_tools.data_control.review_mutations')) ?></div>
      </a>
      <a class="u-style-a37c17f56b" href="/admin/system-tools/data-control/audit-trail">
        <div class="u-style-2ab7487f8b"><?= e(t('system_tools.data_control.audit_trail_title')) ?></div>
        <div class="u-style-8d9785d68f"><?= e(t('system_tools.data_control.view_history')) ?></div>
      </a>
    </div>
  </div>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

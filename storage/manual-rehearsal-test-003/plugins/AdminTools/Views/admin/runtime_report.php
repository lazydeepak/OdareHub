<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$runtimeReportValue = static function (string $value): string {
    $known = ['yes', 'no', 'partial'];
    return in_array($value, $known, true) ? t('admin.runtime_report.value.' . $value) : $value;
};
?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-style-1169661891"><?= t('admin.runtime_report.title') ?></h2>
      <div class="muted"><?= t('admin.runtime_report.subtitle') ?></div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/admin/system-tools"><?= t('admin.system_tools.nav.back') ?></a>
      <a class="btn" href="/admin/system-tools/runtime-report?export=text" target="_blank"><?= t('admin.runtime_report.export_text') ?></a>
    </div>
  </div>
        </div>

<div class="card">
  <h3 class="u-style-291b7bbb01"><?= t('admin.runtime_report.summary.heading') ?></h3>
  <div class="u-style-0b95c964f5">
    <div class="u-style-baa5fba05d">
      <div class="u-style-4dbf65c507"><?= (int)($report['summary']['total_entities'] ?? 0) ?></div>
      <div class="muted"><?= t('admin.runtime_report.summary.total_entities') ?></div>
    </div>
    <div class="u-style-165d40d2ba">
      <div class="u-style-4d7722c286"><?= (int)($report['summary']['runtime_backed_count'] ?? 0) ?></div>
      <div class="muted"><?= t('admin.runtime_report.summary.runtime_backed') ?></div>
    </div>
    <div class="u-style-7cec62e299">
      <div class="u-style-e63448eda5"><?= (int)($report['summary']['partially_migrated_count'] ?? 0) ?></div>
      <div class="muted"><?= t('admin.runtime_report.summary.service_only') ?></div>
    </div>
    <div class="u-style-881cf3dc49">
      <div class="u-style-f460308f88"><?= (int)($report['summary']['legacy_count'] ?? 0) ?></div>
      <div class="muted"><?= t('admin.runtime_report.summary.legacy_unknown') ?></div>
    </div>
  </div>
  <div class="muted u-style-e71ae94b55">
    <?= t('admin.runtime_report.summary.generated') ?> <?= e((string)($report['generated_at'] ?? t('admin.runtime_report.not_applicable'))) ?>
  </div>
</div>

<?php if (empty($report['summary']['total_entities'] ?? 0)): ?>
<div class="card u-style-9374e84210">
  <h3 class="u-style-291b7bbb01"><?= t('admin.runtime_report.no_data.heading') ?></h3>
  <div class="row u-style-0b6ec721bd">
    <div class="card u-style-da5ab49497">
      <div class="muted u-style-e71ae94b55"><?= t('admin.runtime_report.no_data.no_work') ?></div>
    </div>
    <div class="card u-style-da5ab49497">
      <div class="muted u-style-e71ae94b55"><?= t('admin.runtime_report.no_data.no_approvals') ?></div>
    </div>
    <div class="card u-style-da5ab49497">
      <div class="muted u-style-e71ae94b55"><?= t('admin.runtime_report.no_data.no_alerts') ?></div>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <h3 class="u-style-291b7bbb01"><?= t('admin.runtime_report.entities.heading') ?></h3>
  <table class="data-table u-style-cad980f4b7">
    <thead>
      <tr>
        <th><?= t('admin.runtime_report.col.entity_key') ?></th>
        <th><?= t('admin.runtime_report.col.module') ?></th>
        <th><?= t('admin.runtime_report.col.namespace') ?></th>
        <th><?= t('admin.runtime_report.col.workflow_field') ?></th>
        <th><?= t('admin.runtime_report.col.states') ?></th>
        <th><?= t('admin.runtime_report.col.transitions') ?></th>
        <th><?= t('admin.runtime_report.col.service') ?></th>
        <th><?= t('admin.runtime_report.col.policies') ?></th>
        <th><?= t('admin.runtime_report.col.hooks') ?></th>
        <th><?= t('admin.runtime_report.col.my_work') ?></th>
        <th><?= t('admin.runtime_report.col.controller') ?></th>
        <th><?= t('admin.runtime_report.col.gateway_table') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($report['runtime_entities'] as $key => $entity): ?>
        <tr>
          <td><code><?= htmlspecialchars($key) ?></code></td>
          <td><?= e((string)($entity['module'] ?? t('admin.runtime_report.unknown'))) ?></td>
          <td><code class="u-style-e48b05836a"><?= e((string)($entity['namespace'] ?? t('admin.runtime_report.not_applicable'))) ?></code></td>
          <td><code><?= e((string)($entity['workflow_field'] ?? t('admin.runtime_report.not_applicable'))) ?></code></td>
          <td class="u-style-dac4fe6c9b"><?= (int)($entity['states_count'] ?? 0) ?></td>
          <td class="u-style-dac4fe6c9b"><?= (int)($entity['transitions_count'] ?? 0) ?></td>
          <td class="u-style-dac4fe6c9b">
            <?php if ($entity['has_service'] ?? false): ?>
              <span class="badge badge-success"><?= e(t('admin.runtime_report.value.yes')) ?></span>
            <?php else: ?>
              <span class="badge badge-danger"><?= e(t('admin.runtime_report.value.no')) ?></span>
            <?php endif; ?>
          </td>
          <td class="u-style-dac4fe6c9b">
            <?php if ($entity['has_policies'] ?? false): ?>
              <span class="badge badge-success"><?= e(t('admin.runtime_report.value.yes')) ?></span>
            <?php else: ?>
              <span class="badge badge-danger"><?= e(t('admin.runtime_report.value.no')) ?></span>
            <?php endif; ?>
          </td>
          <td class="u-style-dac4fe6c9b">
            <?php if ($entity['has_hooks'] ?? false): ?>
              <span class="badge badge-success"><?= e(t('admin.runtime_report.value.yes')) ?></span>
            <?php else: ?>
              <span class="badge badge-danger"><?= e(t('admin.runtime_report.value.no')) ?></span>
            <?php endif; ?>
          </td>
          <td class="u-style-dac4fe6c9b">
            <?php if ($entity['my_work_supported'] ?? false): ?>
              <span class="badge badge-success"><?= t('admin.runtime_report.badge.supported') ?></span>
            <?php else: ?>
              <span class="badge badge-secondary"><?= e(t('admin.runtime_report.value.no')) ?></span>
            <?php endif; ?>
          </td>
          <td>
            <?php
              $ctrlStatus = $entity['controller_status'] ?? 'unknown';
              $badgeClass = \App\Core\RuntimeReportInspector::getMigrationStatusBadgeClass($ctrlStatus);
              $label = \App\Core\RuntimeReportInspector::getMigrationStatusLabel($ctrlStatus);
            ?>
            <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($label) ?></span>
          </td>
          <td><code class="u-style-e48b05836a"><?= e((string)($entity['gateway_table'] ?? t('admin.runtime_report.not_applicable'))) ?></code></td>
        </tr>
      <?php endforeach; ?>
      <?php foreach ($report['legacy_entities'] ?? [] as $key => $entity): ?>
        <tr class="u-style-fc370c3c07">
          <td><code><?= htmlspecialchars($key) ?></code></td>
          <td colspan="10" class="muted"><?= t('admin.runtime_report.legacy.not_runtime_backed') ?></td>
          <td><span class="badge badge-danger"><?= e(t('admin.runtime_report.legacy')) ?></span></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if (!empty($report['dispatch_notes'])): ?>
  <div class="card">
    <h3 class="u-style-291b7bbb01"><?= t('admin.runtime_report.dispatch_notes.heading') ?></h3>
    <ul class="u-style-fbb5dc9811">
      <?php foreach ($report['dispatch_notes'] as $note): ?>
        <li class="u-style-e4ad4a163b"><?= htmlspecialchars($note) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="card">
  <h3 class="u-style-291b7bbb01"><?= t('admin.runtime_report.ctrl_migration.heading') ?></h3>
  <table class="data-table u-style-cad980f4b7">
    <thead>
      <tr>
        <th><?= t('admin.runtime_report.col.entity') ?></th>
        <th><?= t('admin.runtime_report.col.status') ?></th>
        <th><?= t('admin.runtime_report.col.description') ?></th>
        <th><?= t('admin.runtime_report.col.inspector_link') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($report['runtime_entities'] as $key => $entity): ?>
        <tr>
          <td><code><?= htmlspecialchars($key) ?></code></td>
          <td>
            <?php
              $ctrlStatus = $entity['controller_status'] ?? 'unknown';
              $badgeClass = \App\Core\RuntimeReportInspector::getMigrationStatusBadgeClass($ctrlStatus);
              $label = \App\Core\RuntimeReportInspector::getMigrationStatusLabel($ctrlStatus);
            ?>
            <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($label) ?></span>
          </td>
          <td class="muted">
            <?php if ($ctrlStatus === 'runtime_backed'): ?>
              <?= t('admin.runtime_report.ctrl.runtime_backed') ?>
            <?php elseif ($ctrlStatus === 'service_only'): ?>
              <?= t('admin.runtime_report.ctrl.service_only') ?>
            <?php else: ?>
              <?= t('admin.runtime_report.ctrl.unknown') ?>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($entity['my_work_supported'] ?? false): ?>
              <a href="/admin/system-tools/stage-inspector?inspect_entity=<?= urlencode($key) ?>&inspect_id=1" class="btn runtime-report-inspector-link"><?= t('admin.runtime_report.stage_inspector') ?></a>
            <?php else: ?>
              <span class="muted">-</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="card">
  <h3 class="u-style-291b7bbb01"><?= t('admin.runtime_report.action_class.heading') ?></h3>
  <?php
    $dispatchEntity = $report['runtime_entities']['DispatchEntry'] ?? null;
    $actionClass = $dispatchEntity['action_classification'] ?? null;
  ?>
  <?php if ($actionClass): ?>
    <table class="data-table u-style-cad980f4b7">
      <thead>
        <tr>
          <th><?= t('admin.runtime_report.col.category') ?></th>
          <th><?= t('admin.runtime_report.col.actions') ?></th>
          <th><?= t('admin.runtime_report.col.description') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if (isset($actionClass['entity_lifecycle'])): ?>
          <tr>
            <td><span class="badge badge-primary"><?= e(t('admin.runtime_report.action_class.lifecycle')) ?></span></td>
            <td><code><?= htmlspecialchars(implode(', ', $actionClass['entity_lifecycle'])) ?></code></td>
            <td class="muted"><?= t('admin.runtime_report.action_class.lifecycle_desc') ?></td>
          </tr>
        <?php endif; ?>
        <?php if (isset($actionClass['governance_only'])): ?>
          <tr>
            <td><span class="badge badge-warning"><?= e(t('admin.runtime_report.action_class.governance')) ?></span></td>
            <td><code><?= htmlspecialchars(implode(', ', $actionClass['governance_only'])) ?></code></td>
            <td class="muted"><?= t('admin.runtime_report.action_class.governance_desc') ?></td>
          </tr>
        <?php endif; ?>
        <?php if (isset($actionClass['operational'])): ?>
          <tr>
            <td><span class="badge badge-info"><?= e(t('admin.runtime_report.action_class.operational')) ?></span></td>
            <td><code><?= htmlspecialchars(implode(', ', $actionClass['operational'])) ?></code></td>
            <td class="muted"><?= t('admin.runtime_report.action_class.operational_desc') ?></td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p class="muted"><?= t('admin.runtime_report.action_class.unavailable') ?></p>
  <?php endif; ?>
</div>

<div class="card">
  <h3 class="u-style-291b7bbb01"><?= t('admin.runtime_report.gateway.heading') ?></h3>
  <table class="data-table u-style-cad980f4b7">
    <thead>
      <tr>
        <th><?= t('admin.runtime_report.col.entity') ?></th>
        <th><?= t('admin.runtime_report.col.gateway_table') ?></th>
        <th><?= t('admin.runtime_report.col.status') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($report['runtime_entities'] as $key => $entity): ?>
        <tr>
          <td><code><?= htmlspecialchars($key) ?></code></td>
          <td><code><?= e((string)($entity['gateway_table'] ?? t('admin.runtime_report.not_applicable'))) ?></code></td>
          <td>
            <?php if (!empty($entity['gateway_table'])): ?>
              <span class="badge badge-success"><?= t('admin.runtime_report.badge.available') ?></span>
            <?php else: ?>
              <span class="badge badge-secondary"><?= e(t('admin.runtime_report.not_applicable')) ?></span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="card">
  <h3 class="u-style-291b7bbb01"><?= t('admin.runtime_report.matrix.heading') ?></h3>
  <table class="data-table u-style-cad980f4b7">
    <thead>
      <tr>
        <th><?= t('admin.runtime_report.col.entity') ?></th>
        <th><?= t('admin.runtime_report.col.service') ?></th>
        <th><?= t('admin.runtime_report.col.controller') ?></th>
        <th><?= t('admin.runtime_report.col.create') ?></th>
        <th><?= t('admin.runtime_report.col.update') ?></th>
        <th><?= t('admin.runtime_report.col.transition') ?></th>
        <th><?= t('admin.runtime_report.col.my_work') ?></th>
        <th><?= t('admin.runtime_report.col.debug') ?></th>
        <th><?= t('admin.runtime_report.col.status') ?></th>
        <th><?= t('admin.runtime_report.col.notes') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach (($report['migration_matrix'] ?? []) as $key => $row): ?>
        <tr>
          <td>
            <code><?= htmlspecialchars($key) ?></code>
            <?php if (!empty($row['has_dispatch_split'])): ?>
              <br><span class="badge badge-warning u-style-0c8a27e103"><?= e(t('admin.runtime_report.dispatch_split')) ?></span>
            <?php endif; ?>
          </td>
          <td class="u-style-dac4fe6c9b">
            <?php if ($row['service_wrapper']): ?>
              <span class="badge badge-success"><?= e(t('admin.runtime_report.value.yes')) ?></span>
            <?php else: ?>
              <span class="badge badge-danger"><?= e(t('admin.runtime_report.value.no')) ?></span>
            <?php endif; ?>
          </td>
          <td class="u-style-dac4fe6c9b">
            <?php if ($row['controller_present']): ?>
              <span class="badge badge-success"><?= e(t('admin.runtime_report.value.yes')) ?></span>
            <?php else: ?>
              <span class="badge badge-danger"><?= e(t('admin.runtime_report.value.no')) ?></span>
            <?php endif; ?>
          </td>
          <td class="u-style-dac4fe6c9b">
            <?php
              $createClass = match ($row['create_path'] ?? 'no') {
                'yes' => 'badge-success',
                'partial' => 'badge-warning',
                default => 'badge-danger',
              };
            ?>
            <span class="badge <?= $createClass ?>"><?= e($runtimeReportValue((string)($row['create_path'] ?? 'no'))) ?></span>
          </td>
          <td class="u-style-dac4fe6c9b">
            <?php
              $updateClass = match ($row['update_path'] ?? 'no') {
                'yes' => 'badge-success',
                'partial' => 'badge-warning',
                default => 'badge-danger',
              };
            ?>
            <span class="badge <?= $updateClass ?>"><?= e($runtimeReportValue((string)($row['update_path'] ?? 'no'))) ?></span>
          </td>
          <td class="u-style-dac4fe6c9b">
            <?php
              $transClass = match ($row['transition_path'] ?? 'no') {
                'yes' => 'badge-success',
                'partial' => 'badge-warning',
                default => 'badge-danger',
              };
            ?>
            <span class="badge <?= $transClass ?>"><?= e($runtimeReportValue((string)($row['transition_path'] ?? 'no'))) ?></span>
          </td>
          <td class="u-style-dac4fe6c9b">
            <?php if ($row['my_work_support']): ?>
              <span class="badge badge-success"><?= e(t('admin.runtime_report.value.yes')) ?></span>
            <?php else: ?>
              <span class="badge badge-secondary"><?= e(t('admin.runtime_report.value.no')) ?></span>
            <?php endif; ?>
          </td>
          <td class="u-style-dac4fe6c9b">
            <?php if ($row['debug_tools_support']): ?>
              <span class="badge badge-success"><?= e(t('admin.runtime_report.value.yes')) ?></span>
            <?php else: ?>
              <span class="badge badge-secondary"><?= e(t('admin.runtime_report.value.no')) ?></span>
            <?php endif; ?>
          </td>
          <td>
            <?php
              $statusBadgeClass = \App\Core\RuntimeReportInspector::getMigrationStatusBadgeClass($row['controller_status'] ?? 'unknown');
              $statusLabel = \App\Core\RuntimeReportInspector::getMigrationStatusLabel($row['controller_status'] ?? 'unknown');
            ?>
            <span class="badge <?= $statusBadgeClass ?>"><?= htmlspecialchars($statusLabel) ?></span>
          </td>
          <td class="u-style-e48b05836a">
            <?php if (!empty($row['legacy_notes'])): ?>
              <?php foreach (array_slice($row['legacy_notes'], 0, 2) as $note): ?>
                <div class="ui-block"><?= htmlspecialchars($note) ?></div>
              <?php endforeach; ?>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="card">
  <h3 class="u-style-291b7bbb01"><?= t('admin.runtime_report.migration.heading') ?></h3>
  <table class="data-table u-style-cad980f4b7">
    <thead>
      <tr>
        <th><?= t('admin.runtime_report.col.entity') ?></th>
        <th><?= t('admin.runtime_report.col.current_status') ?></th>
        <th><?= t('admin.runtime_report.col.recommended_next') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach (($report['migration_matrix'] ?? []) as $key => $row): ?>
        <tr>
          <td><code><?= htmlspecialchars($key) ?></code></td>
          <td>
            <?php
              $statusBadgeClass = \App\Core\RuntimeReportInspector::getMigrationStatusBadgeClass($row['controller_status'] ?? 'unknown');
              $statusLabel = \App\Core\RuntimeReportInspector::getMigrationStatusLabel($row['controller_status'] ?? 'unknown');
            ?>
            <span class="badge <?= $statusBadgeClass ?>"><?= htmlspecialchars($statusLabel) ?></span>
          </td>
          <td class="u-style-e71ae94b55"><?= e((string)($row['next_migration_step'] ?? t('admin.runtime_report.not_applicable'))) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="card">
  <h3 class="u-style-291b7bbb01"><?= t('admin.runtime_report.matrix.route_heading') ?></h3>
  <table class="data-table u-style-cad980f4b7">
    <thead>
      <tr>
        <th><?= t('admin.runtime_report.col.entity') ?></th>
        <th><?= t('admin.runtime_report.col.route') ?></th>
        <th><?= t('admin.runtime_report.col.method') ?></th>
        <th><?= t('admin.runtime_report.col.action_type') ?></th>
        <th><?= t('admin.runtime_report.col.backing') ?></th>
        <th><?= t('admin.runtime_report.col.notes') ?></th>
        <th><?= t('admin.runtime_report.col.next_step') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach (($report['route_migration_matrix'] ?? []) as $entityKey => $routes): ?>
        <?php foreach ($routes as $route): ?>
          <?php
            $backingBadgeClass = \App\Core\RuntimeReportInspector::getBackingBadgeClass($route['backing']);
            $backingLabel = \App\Core\RuntimeReportInspector::getBackingLabel($route['backing']);
          ?>
          <tr>
            <td><code><?= htmlspecialchars($entityKey) ?></code></td>
            <td class="u-style-e48b05836a"><code><?= htmlspecialchars($route['route']) ?></code></td>
            <td><?= htmlspecialchars($route['method']) ?></td>
            <td class="u-style-e48b05836a"><?= htmlspecialchars(str_replace('_', ' ', $route['action_type'])) ?></td>
            <td>
              <span class="badge <?= $backingBadgeClass ?>"><?= htmlspecialchars($backingLabel) ?></span>
            </td>
            <td class="u-style-0c8a27e103">
              <?php if (!empty($route['notes'])): ?>
                <?php foreach (array_slice($route['notes'], 0, 2) as $note): ?>
                  <div class="ui-block"><?= htmlspecialchars($note) ?></div>
                <?php endforeach; ?>
              <?php endif; ?>
            </td>
            <td class="u-style-0c8a27e103"><?= htmlspecialchars($route['next_step']) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<style>
.badge { padding:2px 8px; border-radius:3px; font-size:11px; display:inline-block; }
.badge-success { background: var(--color-success-bg); color: var(--color-success-text); }
.badge-danger { background: var(--color-danger-bg); color: var(--color-danger-text); }
.badge-warning { background: var(--color-warning-bg); color: var(--color-warning-text); }
.badge-info { background: var(--style-subtle-bg); color: var(--text); }
.badge-primary { background: var(--style-subtle-bg); color: var(--text); }
.badge-secondary { background: var(--style-subtle-bg); color: var(--text); }
</style>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

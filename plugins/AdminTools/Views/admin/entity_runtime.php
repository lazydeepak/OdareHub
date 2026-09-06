<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-style-1169661891"><?= t('admin.entity_runtime.title') ?></h2>
      <div class="muted"><?= t('admin.entity_runtime.subtitle') ?></div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/admin/system-tools"><?= t('admin.system_tools.nav.back') ?></a>
    </div>
  </div>
</div>

<div class="card">
  <h3 class="u-style-291b7bbb01"><?= t('admin.entity_runtime.dry_run.heading') ?></h3>
  <form method="get" action="/admin/system-tools/entity-runtime">
    <div class="u-style-70c9d3dd04">
      <div class="ui-block">
        <label class="u-style-208124c9af"><?= t('admin.entity_runtime.dry_run.entity_key') ?></label>
        <select class="u-style-cad980f4b7" name="dry_entity">
          <option value=""><?= t('admin.entity_runtime.dry_run.select_entity') ?></option>
          <?php foreach ($report as $key => $info): ?>
            <option value="<?= htmlspecialchars($key) ?>" <?= ($dryRunParams['entity'] ?? '') === $key ? 'selected' : '' ?>><?= htmlspecialchars($key) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="ui-block">
        <label class="u-style-208124c9af"><?= t('admin.entity_runtime.dry_run.current_state') ?></label>
        <input class="input" type="text" name="dry_current" value="<?= htmlspecialchars($dryRunParams['current'] ?? '') ?>" placeholder="<?= htmlspecialchars(t('admin.entity_runtime.dry_run.placeholder_current')) ?>" style="width:100%;">
      </div>
      <div class="ui-block">
        <label class="u-style-208124c9af"><?= t('admin.entity_runtime.dry_run.target_state') ?></label>
        <input class="input" type="text" name="dry_target" value="<?= htmlspecialchars($dryRunParams['target'] ?? '') ?>" placeholder="<?= htmlspecialchars(t('admin.entity_runtime.dry_run.placeholder_target')) ?>" style="width:100%;">
      </div>
      <div class="ui-block">
        <label class="u-style-208124c9af"><?= t('admin.entity_runtime.dry_run.action_optional') ?></label>
        <input class="input" type="text" name="dry_action" value="<?= htmlspecialchars($dryRunParams['action'] ?? '') ?>" placeholder="e.g. submit" style="width:100%;">
      </div>
      <div class="ui-block">
        <button type="submit" class="btn"><?= t('admin.entity_runtime.dry_run.btn') ?></button>
      </div>
    </div>
  </form>

  <?php if ($dryRunError): ?>
    <div class="alert alert-error u-style-fe5e7a38f2">
      <strong>Error:</strong> <?= htmlspecialchars($dryRunError) ?>
    </div>
  <?php elseif ($dryRunResult): ?>
    <div class="u-style-2b2c3ac030">
      <table class="u-style-f3b3f2e4d2">
        <tr class="u-style-fc370c3c07">
          <td class="u-style-00932f6566"><?= t('admin.entity_runtime.dry_run.row.allowed') ?></td>
          <td class="u-style-44cd1236df">
            <?php if ($dryRunResult['allowed']): ?>
              <span class="badge badge-success">YES</span>
            <?php else: ?>
              <span class="badge badge-danger">NO</span>
            <?php endif; ?>
          </td>
        </tr>
        <tr>
          <td class="u-style-d85ad97259"><?= t('admin.entity_runtime.dry_run.row.reason') ?></td>
          <td class="u-style-44cd1236df"><?= htmlspecialchars($dryRunResult['reason']) ?></td>
        </tr>
        <tr class="u-style-fc370c3c07">
          <td class="u-style-d85ad97259"><?= t('admin.entity_runtime.dry_run.row.workflow_field') ?></td>
          <td class="u-style-44cd1236df"><code><?= htmlspecialchars($dryRunResult['workflow_field']) ?></code></td>
        </tr>
        <tr>
          <td class="u-style-d85ad97259"><?= t('admin.entity_runtime.dry_run.row.current_state') ?></td>
          <td class="u-style-44cd1236df"><code><?= htmlspecialchars($dryRunResult['current_state']) ?></code></td>
        </tr>
        <tr class="u-style-fc370c3c07">
          <td class="u-style-d85ad97259"><?= t('admin.entity_runtime.dry_run.row.target_state') ?></td>
          <td class="u-style-44cd1236df"><code><?= htmlspecialchars($dryRunResult['target_state']) ?></code></td>
        </tr>
        <tr>
          <td class="u-style-d85ad97259"><?= t('admin.entity_runtime.dry_run.row.allowed_next') ?></td>
          <td class="u-style-44cd1236df">
            <?php if (!empty($dryRunResult['allowed_next_states'])): ?>
              <code><?= htmlspecialchars(implode(', ', $dryRunResult['allowed_next_states'])) ?></code>
            <?php else: ?>
              <span class="muted"><?= t('admin.entity_runtime.dry_run.none') ?></span>
            <?php endif; ?>
          </td>
        </tr>
        <?php if ($dryRunResult['action']): ?>
          <tr class="u-style-fc370c3c07">
            <td class="u-style-d85ad97259"><?= t('admin.entity_runtime.dry_run.row.action') ?></td>
            <td class="u-style-44cd1236df"><code><?= htmlspecialchars($dryRunResult['action']) ?></code></td>
          </tr>
          <?php if ($dryRunResult['action_category']): ?>
            <tr>
              <td class="u-style-d85ad97259"><?= t('admin.entity_runtime.dry_run.row.action_category') ?></td>
              <td class="u-style-44cd1236df"><span class="badge badge-info"><?= htmlspecialchars($dryRunResult['action_category']) ?></span></td>
            </tr>
          <?php else: ?>
            <tr>
              <td class="u-style-d85ad97259">Action Category</td>
              <td class="u-style-44cd1236df"><span class="muted"><?= t('admin.entity_runtime.dry_run.not_classified') ?></span></td>
            </tr>
          <?php endif; ?>
        <?php endif; ?>
        <tr class="u-style-fc370c3c07">
          <td class="u-style-d85ad97259"><?= t('admin.entity_runtime.dry_run.row.all_states') ?></td>
          <td class="u-style-44cd1236df">
            <code><?= htmlspecialchars(implode(', ', $dryRunResult['all_states'])) ?></code>
          </td>
        </tr>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h3 class="u-style-291b7bbb01"><?= t('admin.entity_runtime.action_inspect.heading') ?></h3>
  <p class="muted u-style-3ef1fa1aa1"><?= t('admin.entity_runtime.action_inspect.desc') ?></p>
  <form method="get" action="/admin/system-tools/entity-runtime">
    <div class="u-style-7d14a0d023">
      <div class="ui-block">
        <label class="u-style-208124c9af"><?= t('admin.entity_runtime.action_inspect.row.entity_key') ?></label>
        <select class="u-style-cad980f4b7" name="inspect_entity">
          <option value=""><?= t('admin.entity_runtime.dry_run.select_entity') ?></option>
          <?php foreach ($report as $key => $info): ?>
            <option value="<?= htmlspecialchars($key) ?>" <?= ($actionInspectParams['entity'] ?? '') === $key ? 'selected' : '' ?>><?= htmlspecialchars($key) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="ui-block">
        <label class="u-style-208124c9af"><?= t('admin.entity_runtime.action_inspect.action') ?></label>
        <input class="input" type="text" name="inspect_action" value="<?= htmlspecialchars($actionInspectParams['action'] ?? '') ?>" placeholder="<?= htmlspecialchars(t('admin.entity_runtime.action_inspect.placeholder_action')) ?>" style="width:100%;">
      </div>
      <div class="ui-block">
        <label class="u-style-208124c9af"><?= t('admin.entity_runtime.action_inspect.current_optional') ?></label>
        <input class="input" type="text" name="inspect_current" value="<?= htmlspecialchars($actionInspectParams['current'] ?? '') ?>" placeholder="<?= htmlspecialchars(t('admin.entity_runtime.dry_run.placeholder_current')) ?>" style="width:100%;">
      </div>
      <div class="ui-block">
        <button type="submit" class="btn"><?= t('admin.entity_runtime.action_inspect.btn') ?></button>
      </div>
    </div>
  </form>

  <?php if ($actionInspectError): ?>
    <div class="alert alert-error u-style-fe5e7a38f2">
      <strong>Error:</strong> <?= htmlspecialchars($actionInspectError) ?>
    </div>
  <?php elseif ($actionInspectResult): ?>
    <div class="u-style-2b2c3ac030">
      <table class="u-style-f3b3f2e4d2">
        <tr class="u-style-fc370c3c07">
          <td class="u-style-00932f6566"><?= t('admin.entity_runtime.action_inspect.row.entity_key') ?></td>
          <td class="u-style-44cd1236df"><code><?= htmlspecialchars($actionInspectResult['entity_key']) ?></code></td>
        </tr>
        <tr>
          <td class="u-style-d85ad97259"><?= t('admin.entity_runtime.action_inspect.row.action') ?></td>
          <td class="u-style-44cd1236df"><code><?= htmlspecialchars($actionInspectResult['action']) ?></code></td>
        </tr>
        <tr class="u-style-fc370c3c07">
          <td class="u-style-d85ad97259"><?= t('admin.entity_runtime.action_inspect.row.recognized') ?></td>
          <td class="u-style-44cd1236df">
            <?php if ($actionInspectResult['action_recognized']): ?>
              <span class="badge badge-success">YES</span>
            <?php else: ?>
              <span class="badge badge-danger">NO</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php if ($actionInspectResult['action_category']): ?>
          <tr>
            <td class="u-style-d85ad97259"><?= t('admin.entity_runtime.action_inspect.row.category') ?></td>
            <td class="u-style-44cd1236df"><span class="badge badge-info"><?= htmlspecialchars($actionInspectResult['action_category']) ?></span></td>
          </tr>
        <?php endif; ?>
        <tr class="u-style-fc370c3c07">
          <td class="u-style-d85ad97259"><?= t('admin.entity_runtime.action_inspect.row.mapped_status') ?></td>
          <td class="u-style-44cd1236df">
            <?php if ($actionInspectResult['mapped_status']): ?>
              <code><?= htmlspecialchars($actionInspectResult['mapped_status']) ?></code>
            <?php else: ?>
              <span class="muted"><?= t('admin.entity_runtime.dry_run.none') ?></span>
            <?php endif; ?>
          </td>
        </tr>
        <tr>
          <td class="u-style-d85ad97259"><?= t('admin.entity_runtime.dry_run.row.workflow_field') ?></td>
          <td class="u-style-44cd1236df"><code><?= htmlspecialchars($actionInspectResult['workflow_field']) ?></code></td>
        </tr>
        <?php if ($actionInspectResult['current_state']): ?>
          <tr class="u-style-fc370c3c07">
            <td class="u-style-d85ad97259">Current State</td>
            <td class="u-style-44cd1236df"><code><?= htmlspecialchars($actionInspectResult['current_state']) ?></code></td>
          </tr>
          <tr>
            <td class="u-style-d85ad97259"><?= t('admin.entity_runtime.dry_run.row.allowed_next') ?></td>
            <td class="u-style-44cd1236df">
              <?php if (!empty($actionInspectResult['allowed_next_states'])): ?>
                <code><?= htmlspecialchars(implode(', ', $actionInspectResult['allowed_next_states'])) ?></code>
              <?php else: ?>
                <span class="muted"><?= t('admin.entity_runtime.dry_run.none') ?></span>
              <?php endif; ?>
          </tr>
          <tr class="u-style-fc370c3c07">
            <td class="u-style-d85ad97259"><?= t('admin.entity_runtime.action_inspect.row.allowed_from') ?></td>
            <td class="u-style-44cd1236df">
              <?php if ($actionInspectResult['allowed_from_current']): ?>
                <span class="badge badge-success">YES</span>
              <?php else: ?>
                <span class="badge badge-danger">NO</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endif; ?>
        <tr>
          <td class="u-style-d85ad97259"><?= t('admin.entity_runtime.action_inspect.row.reason') ?></td>
          <td class="u-style-44cd1236df"><?= htmlspecialchars($actionInspectResult['reason']) ?></td>
        </tr>
        <tr class="u-style-fc370c3c07">
          <td class="u-style-d85ad97259"><?= t('admin.entity_runtime.action_inspect.row.all_states') ?></td>
          <td class="u-style-44cd1236df">
            <code><?= htmlspecialchars(implode(', ', $actionInspectResult['all_states'])) ?></code>
          </td>
        </tr>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="u-style-79a1c5a5db">
    <?= t('admin.entity_runtime.registered.count_prefix') ?> <?= (int)$entityCount ?>
  </div>

  <?php if (empty($report)): ?>
    <div class="alert alert-info">No entities registered in EntityRegistry.</div>
  <?php else: ?>
    <table class="data-table u-style-cad980f4b7">
      <thead>
        <tr>
          <th><?= t('admin.entity_runtime.col.entity_key') ?></th>
          <th><?= t('admin.entity_runtime.col.module') ?></th>
          <th><?= t('admin.entity_runtime.col.workflow_field') ?></th>
          <th><?= t('admin.entity_runtime.col.states') ?></th>
          <th><?= t('admin.entity_runtime.col.transitions') ?></th>
          <th><?= t('admin.entity_runtime.col.hooks') ?></th>
          <th><?= t('admin.entity_runtime.col.fields') ?></th>
          <th><?= t('admin.entity_runtime.col.has_service') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($report as $key => $info): ?>
          <tr>
            <td><strong><?= htmlspecialchars($key) ?></strong></td>
            <td><?= htmlspecialchars($info['module']) ?></td>
            <td><?= htmlspecialchars($info['workflow']['field'] ?? 'N/A') ?></td>
            <td>
              <?php if (!empty($info['workflow']['states'])): ?>
                <code><?= htmlspecialchars(implode(', ', array_keys($info['workflow']['states']))) ?></code>
              <?php else: ?>
                <span class="muted">-</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if (!empty($info['workflow']['transitions'])): ?>
                <code><?= htmlspecialchars(implode(', ', array_keys($info['workflow']['transitions']))) ?></code>
              <?php else: ?>
                <span class="muted">-</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if (!empty($info['hooks']['types'])): ?>
                <code><?= htmlspecialchars(implode(', ', $info['hooks']['types'])) ?></code>
              <?php else: ?>
                <span class="muted">-</span>
              <?php endif; ?>
            </td>
            <td><?= (int)$info['fields']['count'] ?></td>
            <td>
              <?php if ($info['has_service']): ?>
                <span class="badge badge-success">Yes</span>
              <?php else: ?>
                <span class="badge badge-danger">No</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php if (!empty($report)): ?>
  <?php foreach ($report as $key => $info): ?>
    <div class="card u-style-8a359a76eb">
      <h3 class="u-style-291b7bbb01"><?= htmlspecialchars($key) ?> <?= t('admin.entity_runtime.detail.suffix') ?></h3>

      <div class="u-style-16b4cf0c3d">
        <div class="ui-block">
          <h4><?= t('admin.entity_runtime.detail.workflow_states') ?></h4>
          <?php if (!empty($info['workflow']['states'])): ?>
            <table class="data-table u-style-cad980f4b7">
              <thead>
                <tr><th><?= t('admin.entity_runtime.detail.col.state') ?></th><th><?= t('admin.entity_runtime.detail.col.type') ?></th></tr>
              </thead>
              <tbody>
                <?php foreach ($info['workflow']['states'] as $state => $config): ?>
                  <tr>
                    <td><code><?= htmlspecialchars($state) ?></code></td>
                    <td><?= htmlspecialchars(is_array($config) ? ($config['type'] ?? 'normal') : 'normal') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <span class="muted"><?= t('admin.entity_runtime.detail.no_states') ?></span>
          <?php endif; ?>
        </div>

        <div class="ui-block">
          <h4><?= t('admin.entity_runtime.detail.workflow_transitions') ?></h4>
          <?php if (!empty($info['workflow']['transitions'])): ?>
            <table class="data-table u-style-cad980f4b7">
              <thead>
                <tr><th><?= t('admin.entity_runtime.detail.col.action') ?></th><th><?= t('admin.entity_runtime.detail.col.from') ?></th><th><?= t('admin.entity_runtime.detail.col.to') ?></th></tr>
              </thead>
              <tbody>
                <?php foreach ($info['workflow']['transitions'] as $action => $config): ?>
                  <tr>
                    <td><code><?= htmlspecialchars($action) ?></code></td>
                    <td><?= htmlspecialchars(is_array($config) ? ($config['from'] ?? '*') : $config) ?></td>
                    <td><?= htmlspecialchars(is_array($config) ? ($config['to'] ?? '*') : '*') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <span class="muted"><?= t('admin.entity_runtime.detail.no_transitions') ?></span>
          <?php endif; ?>
        </div>
      </div>

      <?php if (!empty($info['action_classification'])): ?>
        <div class="u-style-8a359a76eb">
          <h4><?= t('admin.entity_runtime.detail.action_classification') ?></h4>
          <?php foreach ($info['action_classification'] as $category => $actions): ?>
            <div class="u-style-e4ad4a163b">
              <strong><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $category))) ?>:</strong>
              <code><?= htmlspecialchars(implode(', ', $actions)) ?></code>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($info['fields']['status_fields'])): ?>
        <div class="u-style-8a359a76eb">
          <h4><?= t('admin.entity_runtime.detail.status_fields') ?></h4>
          <code><?= htmlspecialchars(implode(', ', $info['fields']['status_fields'])) ?></code>
        </div>
      <?php endif; ?>

      <?php if ($info['sla_config']): ?>
        <div class="u-style-8a359a76eb">
          <h4><?= t('admin.entity_runtime.detail.sla_config_key') ?></h4>
          <code><?= htmlspecialchars($info['sla_config']) ?></code>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<style>
.badge { padding:2px 8px; border-radius:3px; font-size:12px; }
.badge-success { background: var(--color-success-bg); color: var(--color-success-text); }
.badge-danger { background: var(--color-danger-bg); color: var(--color-danger-text); }
</style>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

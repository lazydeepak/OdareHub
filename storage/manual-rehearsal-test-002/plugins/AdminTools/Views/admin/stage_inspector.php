<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-style-1169661891"><?= t('admin.stage_inspector.title') ?></h2>
      <div class="muted"><?= t('admin.stage_inspector.subtitle') ?></div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/admin/system-tools"><?= t('admin.system_tools.nav.back') ?></a>
      <a class="btn" href="/admin/system-tools/entity-runtime"><?= t('admin.stage_inspector.nav.entity_runtime') ?></a>
      <a class="btn" href="/admin/system-tools/my-work-runtime"><?= t('admin.stage_inspector.nav.my_work') ?></a>
    </div>
  </div>
</div>

<div class="card">
  <h3 class="u-style-291b7bbb01"><?= t('admin.stage_inspector.inspect.heading') ?></h3>
  <form method="get" action="/admin/system-tools/stage-inspector">
    <div class="u-style-b93bd0ab32">
      <div class="ui-block">
        <label class="u-style-208124c9af"><?= t('admin.stage_inspector.inspect.entity_key') ?></label>
        <select class="u-style-cad980f4b7" name="inspect_entity">
          <option value=""><?= t('admin.stage_inspector.inspect.select_entity') ?></option>
          <?php foreach ($entityOptions as $key => $label): ?>
            <option value="<?= htmlspecialchars($key) ?>" <?= ($inspectParams['entity'] ?? '') === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="ui-block">
        <label class="u-style-208124c9af"><?= t('admin.stage_inspector.inspect.record_id') ?></label>
        <input class="input" type="number" name="inspect_id" value="<?= (int)($inspectParams['id'] ?? 0) ?>" min="1" placeholder="ID" style="width:100px;">
      </div>
      <div class="ui-block">
        <label class="u-style-208124c9af"><?= t('admin.stage_inspector.inspect.primary_area') ?></label>
        <select class="u-style-9fe45cd88c" name="primary_area">
          <option value="production" <?= ($inspectParams['primary_area'] ?? '') === 'production' ? 'selected' : '' ?>><?= t('admin.stage_inspector.area.production') ?></option>
          <option value="order" <?= ($inspectParams['primary_area'] ?? '') === 'order' ? 'selected' : '' ?>><?= t('admin.stage_inspector.area.order') ?></option>
          <option value="qc" <?= ($inspectParams['primary_area'] ?? '') === 'qc' ? 'selected' : '' ?>><?= t('admin.stage_inspector.area.qc') ?></option>
          <option value="dispatch" <?= ($inspectParams['primary_area'] ?? '') === 'dispatch' ? 'selected' : '' ?>><?= t('admin.stage_inspector.area.dispatch') ?></option>
          <option value="assembly" <?= ($inspectParams['primary_area'] ?? '') === 'assembly' ? 'selected' : '' ?>><?= t('admin.stage_inspector.area.assembly') ?></option>
        </select>
      </div>
      <div class="ui-block">
        <button type="submit" class="btn"><?= t('admin.stage_inspector.inspect.btn') ?></button>
      </div>
    </div>
    <div class="u-style-b1ecc496e0">
      <label class="u-style-208124c9af"><?= t('admin.stage_inspector.inspect.cross_areas') ?></label>
      <input class="input" type="text" name="cross_areas" value="<?= htmlspecialchars($inspectParams['cross_areas'] ?? '') ?>" placeholder="<?= htmlspecialchars(t('admin.stage_inspector.inspect.cross_areas_placeholder')) ?>" style="width:300px;">
    </div>
  </form>
</div>

<?php if ($error): ?>
  <div class="card">
    <div class="alert alert-error u-style-3cce6712d8">
      <strong>Error:</strong> <?= htmlspecialchars($error) ?>
    </div>
  </div>
<?php elseif ($result): ?>
  <?php if (!$result['found']): ?>
    <div class="card">
      <h3 class="u-style-291b7bbb01"><?= t('admin.stage_inspector.result.heading') ?></h3>
      <div class="alert alert-error u-style-11c063ae1f">
        <strong>Error:</strong> <?= htmlspecialchars($result['error_message']) ?>
      </div>
      <table class="u-style-124bd027b4">
        <tr class="u-style-fc370c3c07">
          <td class="u-style-b511721769"><?= t('admin.stage_inspector.result.entity_key') ?></td>
          <td class="u-style-885d720aa4"><code><?= htmlspecialchars($result['entity_key'] ?? 'N/A') ?></code></td>
        </tr>
        <tr>
          <td class="u-style-f0f2e0fed6"><?= t('admin.stage_inspector.result.record_id') ?></td>
          <td class="u-style-885d720aa4"><?= (int)($result['entity_id'] ?? 0) ?></td>
        </tr>
        <tr class="u-style-fc370c3c07">
          <td class="u-style-f0f2e0fed6"><?= t('admin.stage_inspector.result.error_code') ?></td>
          <td class="u-style-885d720aa4"><code><?= htmlspecialchars($result['error'] ?? 'N/A') ?></code></td>
        </tr>
      </table>
    </div>
  <?php else: ?>
    <div class="card">
      <h3 class="u-style-291b7bbb01">
        Inspection Result
        <?php if ($result['detail_url'] !== '#'): ?>
          <a href="<?= htmlspecialchars($result['detail_url']) ?>" class="btn" style="font-size:12px; padding:4px 8px;"><?= t('admin.stage_inspector.inspect.open_record') ?></a>
        <?php endif; ?>
      </h3>
      <div class="u-style-f0342db9cd">
        <div class="u-style-614c54ed0f">
          <strong><?= t('admin.stage_inspector.result.entity') ?></strong><br>
          <code><?= htmlspecialchars($result['entity_key'] ?? 'N/A') ?></code> #<?= (int)($result['entity_id'] ?? 0) ?>
        </div>
        <div class="u-style-614c54ed0f">
          <strong><?= t('admin.stage_inspector.result.my_work_included') ?></strong><br>
          <?php if ($result['my_work']['included'] ?? false): ?>
            <span class="badge badge-success">YES</span>
          <?php else: ?>
            <span class="badge badge-danger">NO</span>
          <?php endif; ?>
        </div>
        <div class="u-style-614c54ed0f">
          <strong><?= t('admin.stage_inspector.result.my_work_section') ?></strong><br>
          <code><?= htmlspecialchars(str_replace('_', ' ', $result['my_work']['section'] ?? 'N/A')) ?></code>
        </div>
      </div>
    </div>

    <div class="u-style-16b4cf0c3d">
      <div class="card">
        <h4 class="u-style-291b7bbb01"><?= t('admin.stage_inspector.section.workflow') ?></h4>
        <table class="u-style-f3b3f2e4d2">
          <tr class="u-style-fc370c3c07">
            <td class="u-style-b28f135c37"><?= t('admin.stage_inspector.row.workflow_field') ?></td>
            <td class="u-style-885d720aa4"><code><?= htmlspecialchars($result['workflow_field'] ?? 'N/A') ?></code></td>
          </tr>
          <tr>
            <td class="u-style-f0f2e0fed6"><?= t('admin.stage_inspector.row.lifecycle_status') ?></td>
            <td class="u-style-885d720aa4"><code><?= htmlspecialchars($result['lifecycle_status'] ?? 'N/A') ?></code></td>
          </tr>
          <tr class="u-style-fc370c3c07">
            <td class="u-style-f0f2e0fed6"><?= t('admin.stage_inspector.row.all_states') ?></td>
            <td class="u-style-885d720aa4">
              <?php if (!empty($result['all_workflow_states'])): ?>
                <code><?= htmlspecialchars(implode(', ', $result['all_workflow_states'])) ?></code>
              <?php else: ?>
                <span class="muted"><?= t('admin.stage_inspector.row.none_defined') ?></span>
              <?php endif; ?>
            </td>
          </tr>
          <tr>
            <td class="u-style-f0f2e0fed6"><?= t('admin.stage_inspector.row.allowed_next') ?></td>
            <td class="u-style-885d720aa4">
              <?php if (!empty($result['allowed_next_states'])): ?>
                <code><?= htmlspecialchars(implode(', ', $result['allowed_next_states'])) ?></code>
              <?php else: ?>
                <span class="muted"><?= t('admin.stage_inspector.row.none_from_current') ?></span>
              <?php endif; ?>
            </td>
          </tr>
        </table>
      </div>

      <div class="card">
        <h4 class="u-style-291b7bbb01"><?= t('admin.stage_inspector.section.approval') ?></h4>
        <table class="u-style-f3b3f2e4d2">
          <?php $approval = $result['approval_status'] ?? null; ?>
          <tr class="u-style-fc370c3c07">
            <td class="u-style-b28f135c37"><?= t('admin.stage_inspector.row.approval_status') ?></td>
            <td class="u-style-885d720aa4">
              <?php if ($approval && $approval['exists']): ?>
                <?php
                  $appClass = match (strtolower((string)($approval['value'] ?? ''))) {
                    'approved' => 'badge-success',
                    'rejected' => 'badge-danger',
                    default => 'badge-warning',
                  };
                ?>
                <span class="badge <?= $appClass ?>"><?= htmlspecialchars($approval['label'] ?? 'N/A') ?></span>
              <?php else: ?>
                <span class="muted"><?= t('admin.stage_inspector.row.no_approval_field') ?></span>
              <?php endif; ?>
            </td>
          </tr>
          <?php if ($approval && $approval['exists']): ?>
            <tr>
              <td class="u-style-f0f2e0fed6"><?= t('admin.stage_inspector.row.locked') ?></td>
              <td class="u-style-885d720aa4">
                <?php if ($approval['locked']): ?>
                  <span class="badge badge-danger">YES</span>
                  <?php if ($approval['locked_at']): ?>
                    <span class="muted">at <?= htmlspecialchars($approval['locked_at']) ?></span>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="badge badge-success">No</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php $align = $result['approval_alignment'] ?? null; ?>
            <?php if ($align): ?>
              <tr class="u-style-fc370c3c07">
                <td class="u-style-f0f2e0fed6"><?= t('admin.stage_inspector.row.aligned') ?></td>
                <td class="u-style-885d720aa4">
                  <?php if ($align['aligned']): ?>
                    <span class="badge badge-success">YES</span>
                  <?php else: ?>
                    <span class="badge badge-danger">NO</span>
                  <?php endif; ?>
                </td>
              </tr>
              <tr>
                <td class="u-style-f0f2e0fed6" colspan="2">
                  <?php foreach ($align['notes'] as $note): ?>
                    <div class="u-style-a3a5568692"><code><?= htmlspecialchars($note) ?></code></div>
                  <?php endforeach; ?>
                </td>
              </tr>
            <?php endif; ?>
          <?php endif; ?>
        </table>
      </div>

      <div class="card">
        <h4 class="u-style-291b7bbb01"><?= t('admin.stage_inspector.section.routing') ?></h4>
        <table class="u-style-f3b3f2e4d2">
          <?php $routing = $result['routing_state'] ?? null; ?>
          <?php if ($routing && $routing['exists']): ?>
            <tr class="u-style-fc370c3c07">
              <td class="u-style-b28f135c37"><?= t('admin.stage_inspector.row.routing_field') ?></td>
              <td class="u-style-885d720aa4"><code><?= htmlspecialchars($routing['field'] ?? 'N/A') ?></code></td>
            </tr>
            <tr>
              <td class="u-style-f0f2e0fed6"><?= t('admin.stage_inspector.row.routing_state') ?></td>
              <td class="u-style-885d720aa4"><code><?= htmlspecialchars($routing['value'] ?? 'N/A') ?></code></td>
            </tr>
          <?php else: ?>
            <tr>
              <td class="u-style-885d720aa4" colspan="2"><span class="muted"><?= t('admin.stage_inspector.row.no_routing') ?></span></td>
            </tr>
          <?php endif; ?>
        </table>
      </div>

      <div class="card">
        <h4 class="u-style-291b7bbb01"><?= t('admin.stage_inspector.section.sla') ?></h4>
        <table class="u-style-f3b3f2e4d2">
          <?php $sla = $result['sla_result'] ?? null; ?>
          <?php if ($sla): ?>
            <?php
              $slaClass = match ($sla['state'] ?? '') {
                'breached', 'overdue' => 'badge-danger',
                'due_soon' => 'badge-warning',
                default => 'badge-success',
              };
            ?>
            <tr class="u-style-fc370c3c07">
              <td class="u-style-b28f135c37"><?= t('admin.stage_inspector.row.sla_state') ?></td>
              <td class="u-style-885d720aa4">
                <span class="badge <?= $slaClass ?>"><?= htmlspecialchars($sla['label'] ?? 'N/A') ?></span>
                <?php if ($sla['escalated'] ?? false): ?>
                  <span class="badge badge-danger u-style-ee82001542">ESCALATED</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php if ($sla['deadline_at'] ?? null): ?>
              <tr>
                <td class="u-style-f0f2e0fed6"><?= t('admin.stage_inspector.row.deadline') ?></td>
                <td class="u-style-885d720aa4"><?= htmlspecialchars($sla['deadline_at']) ?></td>
              </tr>
            <?php endif; ?>
            <?php if ($result['sla_config']): ?>
              <tr class="u-style-fc370c3c07">
                <td class="u-style-f0f2e0fed6"><?= t('admin.stage_inspector.row.sla_config') ?></td>
                <td class="u-style-885d720aa4">
                  <code><?= htmlspecialchars('field: ' . ($result['sla_config']['status_field'] ?? 'N/A')) ?></code>,
                  <code><?= htmlspecialchars('deadline: ' . ($result['sla_config']['deadline_field'] ?? 'N/A')) ?></code>
                </td>
              </tr>
            <?php endif; ?>
          <?php else: ?>
            <tr>
              <td class="u-style-885d720aa4" colspan="2"><span class="muted"><?= t('admin.stage_inspector.row.no_sla') ?></span></td>
            </tr>
          <?php endif; ?>
        </table>
      </div>
    </div>

    <div class="card">
      <h4 class="u-style-291b7bbb01"><?= t('admin.stage_inspector.section.mapped_actions') ?></h4>
      <?php if (!empty($result['mapped_actions'])): ?>
        <table class="data-table u-style-cad980f4b7">
          <thead>
            <tr>
              <th><?= t('admin.stage_inspector.col.action') ?></th>
              <th><?= t('admin.stage_inspector.col.target_status') ?></th>
              <th><?= t('admin.stage_inspector.col.category') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($result['mapped_actions'] as $action => $info): ?>
              <tr>
                <td><code><?= htmlspecialchars($action) ?></code></td>
                <td><code><?= htmlspecialchars($info['target_status'] ?? 'N/A') ?></code></td>
                <td>
                  <?php if ($info['category']): ?>
                    <span class="badge badge-info"><?= htmlspecialchars(str_replace('_', ' ', $info['category'])) ?></span>
                  <?php else: ?>
                    <span class="muted">-</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <span class="muted"><?= t('admin.stage_inspector.row.no_mapped_actions') ?></span>
      <?php endif; ?>
    </div>

    <?php if ($result['my_work']): ?>
      <?php $mw = $result['my_work']; ?>
      <div class="card">
        <h4 class="u-style-291b7bbb01"><?= t('admin.stage_inspector.section.my_work') ?></h4>
        <table class="u-style-f3b3f2e4d2">
          <tr class="u-style-fc370c3c07">
            <td class="u-style-b511721769"><?= t('admin.stage_inspector.row.included') ?></td>
            <td class="u-style-885d720aa4">
              <?php if ($mw['included'] ?? false): ?>
                <span class="badge badge-success">YES</span>
              <?php else: ?>
                <span class="badge badge-danger">NO</span>
              <?php endif; ?>
            </td>
          </tr>
          <tr>
            <td class="u-style-f0f2e0fed6"><?= t('admin.stage_inspector.row.reason') ?></td>
            <td class="u-style-885d720aa4"><code><?= htmlspecialchars($mw['inclusion_reason'] ?? 'N/A') ?></code></td>
          </tr>
          <tr class="u-style-fc370c3c07">
            <td class="u-style-f0f2e0fed6"><?= t('admin.stage_inspector.row.work_area') ?></td>
            <td class="u-style-885d720aa4"><code><?= htmlspecialchars($mw['work_area'] ?? 'N/A') ?></code></td>
          </tr>
          <tr>
            <td class="u-style-f0f2e0fed6"><?= t('admin.stage_inspector.row.bucket') ?></td>
            <td class="u-style-885d720aa4"><code><?= htmlspecialchars(str_replace('_', ' ', $mw['bucket'] ?? 'N/A')) ?></code></td>
          </tr>
          <tr class="u-style-fc370c3c07">
            <td class="u-style-f0f2e0fed6"><?= t('admin.stage_inspector.row.section') ?></td>
            <td class="u-style-885d720aa4"><code><?= htmlspecialchars(str_replace('_', ' ', $mw['section'] ?? 'N/A')) ?></code></td>
          </tr>
          <tr>
            <td class="u-style-f0f2e0fed6"><?= t('admin.stage_inspector.row.can_view') ?></td>
            <td class="u-style-885d720aa4">
              <?php if ($mw['can_view'] ?? false): ?>
                <span class="badge badge-success"><?= t('admin.stage_inspector.visibility.allowed') ?></span>
              <?php else: ?>
                <span class="badge badge-danger"><?= t('admin.stage_inspector.visibility.hidden') ?></span>
              <?php endif; ?>
              <code class="u-style-6170d94c81"><?= htmlspecialchars($mw['visibility_reason'] ?? '') ?></code>
            </td>
          </tr>
          <tr class="u-style-fc370c3c07">
            <td class="u-style-f0f2e0fed6"><?= t('admin.stage_inspector.row.status') ?></td>
            <td class="u-style-885d720aa4"><code><?= htmlspecialchars($mw['status'] ?? 'N/A') ?></code></td>
          </tr>
        </table>
      </div>
    <?php endif; ?>
  <?php endif; ?>
<?php endif; ?>

<style>
.badge { padding:2px 8px; border-radius:3px; font-size:12px; }
.badge-success { background: var(--color-success-bg); color: var(--color-success-text); }
.badge-danger { background: var(--color-danger-bg); color: var(--color-danger-text); }
.badge-warning { background: var(--color-warning-bg); color: var(--color-warning-text); }
.badge-info { background: var(--style-subtle-bg); color: var(--text); }
</style>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

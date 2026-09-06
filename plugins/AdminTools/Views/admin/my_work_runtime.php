<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header u-style-383a8f14a7">
    <div class="module-header-info">
      <div class="muted"><?= t('admin.my_work_runtime.title') ?></div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/admin/system-tools"><?= t('admin.system_tools.nav.back') ?></a>
      <a class="btn" href="/admin/system-tools/entity-runtime">Entity Runtime</a>
    </div>
  </div>
</div>

<div class="card u-style-79a1c5a5db">
  <h3 class="u-style-291b7bbb01"><?= t('admin.my_work_runtime.single_replay.heading') ?></h3>
  <p class="muted u-style-3ef1fa1aa1"><?= t('admin.my_work_runtime.single_replay.desc') ?></p>
  <form method="get" action="/admin/system-tools/my-work-runtime">
    <div class="u-style-b5813b1a60">
      <div class="ui-block">
        <label class="u-style-208124c9af"><?= t('admin.my_work_runtime.single_replay.entity_type') ?></label>
        <select class="u-style-cad980f4b7" name="replay_entity">
          <option value=""><?= t('admin.my_work_runtime.single_replay.select_entity') ?></option>
          <?php foreach ($entityOptions as $key => $label): ?>
            <option value="<?= htmlspecialchars($key) ?>" <?= ($singleRecordParams['entity'] ?? '') === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="ui-block">
        <label class="u-style-208124c9af"><?= t('admin.my_work_runtime.single_replay.record_id') ?></label>
        <input class="input" type="number" name="replay_id" value="<?= (int)($singleRecordParams['id'] ?? 0) ?>" min="1" placeholder="ID" style="width:80px;">
      </div>
      <div class="ui-block">
        <label class="u-style-208124c9af"><?= t('admin.my_work_runtime.single_replay.primary_area') ?></label>
        <select class="u-style-9fe45cd88c" name="replay_primary_area">
          <option value="production" <?= ($singleRecordParams['primary_area'] ?? '') === 'production' ? 'selected' : '' ?>><?= t('admin.my_work_runtime.area.production') ?></option>
          <option value="order" <?= ($singleRecordParams['primary_area'] ?? '') === 'order' ? 'selected' : '' ?>><?= t('admin.my_work_runtime.area.order') ?></option>
          <option value="qc" <?= ($singleRecordParams['primary_area'] ?? '') === 'qc' ? 'selected' : '' ?>><?= t('admin.my_work_runtime.area.qc') ?></option>
          <option value="dispatch" <?= ($singleRecordParams['primary_area'] ?? '') === 'dispatch' ? 'selected' : '' ?>><?= t('admin.my_work_runtime.area.dispatch') ?></option>
          <option value="assembly" <?= ($singleRecordParams['primary_area'] ?? '') === 'assembly' ? 'selected' : '' ?>><?= t('admin.my_work_runtime.area.assembly') ?></option>
        </select>
      </div>
      <div class="ui-block">
        <label class="u-style-208124c9af"><?= t('admin.my_work_runtime.single_replay.cross_areas') ?></label>
        <input class="input" type="text" name="replay_cross_areas" value="<?= htmlspecialchars($singleRecordParams['cross_areas'] ?? '') ?>" placeholder="<?= htmlspecialchars(t('admin.my_work_runtime.single_replay.cross_areas_placeholder')) ?>" style="width:150px;">
      </div>
      <div class="ui-block">
        <button type="submit" class="btn"><?= t('admin.my_work_runtime.single_replay.btn') ?></button>
      </div>
    </div>
  </form>
</div>

<?php if ($singleRecordError): ?>
  <div class="card">
    <div class="alert alert-error u-style-3cce6712d8">
      <strong>Error:</strong> <?= htmlspecialchars($singleRecordError) ?>
    </div>
  </div>
<?php elseif ($singleRecordResult): ?>
  <?php if (!$singleRecordResult['found']): ?>
    <div class="card">
      <h3 class="u-style-291b7bbb01"><?= t('admin.my_work_runtime.single_replay.result_heading') ?></h3>
      <div class="alert alert-error u-style-11c063ae1f">
        <strong>Error:</strong> <?= htmlspecialchars($singleRecordResult['error_message']) ?>
      </div>
      <table class="u-style-124bd027b4">
        <tr class="u-style-fc370c3c07">
          <td class="u-style-6703c3f102"><?= t('admin.my_work_runtime.row.entity_type') ?></td>
          <td class="u-style-885d720aa4"><code><?= htmlspecialchars($singleRecordResult['entity_type']) ?></code></td>
        </tr>
        <tr>
          <td class="u-style-f0f2e0fed6"><?= t('admin.my_work_runtime.row.record_id') ?></td>
          <td class="u-style-885d720aa4"><?= (int)$singleRecordResult['entity_id'] ?></td>
        </tr>
        <tr class="u-style-fc370c3c07">
          <td class="u-style-f0f2e0fed6"><?= t('admin.my_work_runtime.row.error_code') ?></td>
          <td class="u-style-885d720aa4"><code><?= htmlspecialchars($singleRecordResult['error']) ?></code></td>
        </tr>
      </table>
    </div>
  <?php else: ?>
    <?php $explanation = $singleRecordResult['explanation']; ?>
    <div class="card">
      <h3 class="u-style-291b7bbb01"><?= t('admin.my_work_runtime.single_replay.result_heading') ?></h3>
      <div class="u-style-79a1c5a5db">
        <?php if ($explanation['included']): ?>
          <span class="badge badge-success u-style-b94503f4b1"><?= t('admin.my_work_runtime.single_replay.included') ?></span>
        <?php else: ?>
          <span class="badge badge-danger u-style-b94503f4b1"><?= t('admin.my_work_runtime.single_replay.excluded') ?></span>
        <?php endif; ?>
        <span class="u-style-4018f4a8b5"><strong><?= htmlspecialchars(MyWorkInspector::getEntityTypeLabel($singleRecordResult['entity_type'])) ?></strong> #<?= (int)$singleRecordResult['entity_id'] ?></span>
        <?php if ($explanation['detail_url'] !== '#'): ?>
          <a href="<?= htmlspecialchars($explanation['detail_url']) ?>" class="btn" style="margin-left:12px; font-size:12px;">Open Record</a>
        <?php endif; ?>
      </div>
      <table class="u-style-f3b3f2e4d2">
        <tr class="u-style-fc370c3c07">
          <td class="u-style-b511721769"><?= t('admin.my_work_runtime.row.included') ?></td>
          <td class="u-style-885d720aa4">
            <?php if ($explanation['included']): ?>
              <span class="badge badge-success">YES</span>
            <?php else: ?>
              <span class="badge badge-danger">NO</span>
            <?php endif; ?>
          </td>
        </tr>
        <tr>
          <td class="u-style-f0f2e0fed6"><?= t('admin.my_work_runtime.row.inclusion_reason') ?></td>
          <td class="u-style-885d720aa4"><code><?= htmlspecialchars($explanation['inclusion_reason']) ?></code></td>
        </tr>
        <tr class="u-style-fc370c3c07">
          <td class="u-style-f0f2e0fed6"><?= t('admin.my_work_runtime.row.can_view') ?></td>
          <td class="u-style-885d720aa4">
            <?php if ($explanation['can_view']): ?>
              <span class="badge badge-success"><?= t('admin.my_work_runtime.visibility.allowed') ?></span>
            <?php else: ?>
              <span class="badge badge-danger"><?= t('admin.my_work_runtime.visibility.hidden') ?></span>
            <?php endif; ?>
            <code class="u-style-6170d94c81"><?= htmlspecialchars($explanation['visibility_reason']) ?></code>
          </td>
        </tr>
        <tr>
          <td class="u-style-f0f2e0fed6"><?= t('admin.my_work_runtime.row.work_area') ?></td>
          <td class="u-style-885d720aa4"><code><?= htmlspecialchars($explanation['work_area']) ?></code></td>
        </tr>
        <tr class="u-style-fc370c3c07">
          <td class="u-style-f0f2e0fed6"><?= t('admin.my_work_runtime.row.bucket') ?></td>
          <td class="u-style-885d720aa4"><code><?= htmlspecialchars(str_replace('_', ' ', $explanation['bucket'])) ?></code></td>
        </tr>
        <tr>
          <td class="u-style-f0f2e0fed6"><?= t('admin.my_work_runtime.row.status') ?></td>
          <td class="u-style-885d720aa4"><code><?= htmlspecialchars($explanation['status']) ?></code></td>
        </tr>
        <tr class="u-style-fc370c3c07">
          <td class="u-style-f0f2e0fed6"><?= t('admin.my_work_runtime.row.stage_label') ?></td>
          <td class="u-style-885d720aa4"><?= htmlspecialchars($explanation['stage_label']) ?></td>
        </tr>
        <tr>
          <td class="u-style-f0f2e0fed6"><?= t('admin.my_work_runtime.row.section') ?></td>
          <td class="u-style-885d720aa4"><code><?= htmlspecialchars(str_replace('_', ' ', $explanation['section'])) ?></code></td>
        </tr>
        <?php if ($explanation['has_sla']): ?>
          <tr class="u-style-fc370c3c07">
            <td class="u-style-f0f2e0fed6"><?= t('admin.my_work_runtime.row.sla_state') ?></td>
            <td class="u-style-885d720aa4">
              <?php
                $slaClass = match ($explanation['sla_state'] ?? '') {
                  'breached', 'overdue' => 'badge-danger',
                  'due_soon' => 'badge-warning',
                  default => 'badge-success',
                };
              ?>
              <span class="badge <?= $slaClass ?>"><?= htmlspecialchars($explanation['sla_label'] ?? 'N/A') ?></span>
              <?php if ($explanation['sla_deadline']): ?>
                <span class="muted u-style-6170d94c81">Deadline: <?= htmlspecialchars($explanation['sla_deadline']) ?></span>
              <?php endif; ?>
              <?php if ($explanation['sla_escalated']): ?>
                <span class="badge badge-danger u-style-6170d94c81">ESCALATED</span>
              <?php endif; ?>
            </td>
          </tr>
          <tr>
            <td class="u-style-f0f2e0fed6"><?= t('admin.my_work_runtime.row.sla_config') ?></td>
            <td class="u-style-885d720aa4">
              <code>
                <?= htmlspecialchars('status_field: ' . ($explanation['sla_config']['status_field'] ?? 'N/A')) ?>,
                <?= htmlspecialchars('deadline_field: ' . ($explanation['sla_config']['deadline_field'] ?? 'N/A')) ?>,
                <?= htmlspecialchars('due_soon_minutes: ' . ($explanation['sla_config']['due_soon_minutes'] ?? 'N/A')) ?>
              </code>
            </td>
          </tr>
        <?php else: ?>
          <tr class="u-style-fc370c3c07">
            <td class="u-style-f0f2e0fed6"><?= t('admin.my_work_runtime.row.sla') ?></td>
            <td class="u-style-885d720aa4"><span class="muted"><?= t('admin.my_work_runtime.row.no_sla') ?></span></td>
          </tr>
        <?php endif; ?>
        <tr>
          <td class="u-style-f0f2e0fed6"><?= t('admin.my_work_runtime.row.age_hours') ?></td>
          <td class="u-style-885d720aa4"><?= round((float)($explanation['age_hours'] ?? 0), 2) ?></td>
        </tr>
        <tr class="u-style-fc370c3c07">
          <td class="u-style-f0f2e0fed6"><?= t('admin.my_work_runtime.row.primary_area_used') ?></td>
          <td class="u-style-885d720aa4"><code><?= htmlspecialchars($singleRecordResult['primary_area']) ?></code></td>
        </tr>
        <tr>
          <td class="u-style-f0f2e0fed6"><?= t('admin.my_work_runtime.row.cross_areas_used') ?></td>
          <td class="u-style-885d720aa4">
            <?php if (!empty($singleRecordResult['cross_areas'])): ?>
              <code><?= htmlspecialchars(implode(', ', $singleRecordResult['cross_areas'])) ?></code>
            <?php else: ?>
              <span class="muted"><?= t('admin.my_work_runtime.row.none') ?></span>
            <?php endif; ?>
          </td>
        </tr>
      </table>
    </div>
  <?php endif; ?>
<?php endif; ?>

<div class="card">
  <h3 class="u-style-291b7bbb01"><?= t('admin.my_work_runtime.inspect.heading') ?></h3>
  <form method="get" action="/admin/system-tools/my-work-runtime">
    <div class="u-style-b93bd0ab32">
      <div class="ui-block">
        <label class="u-style-208124c9af"><?= t('admin.my_work_runtime.single_replay.entity_type') ?></label>
        <select class="u-style-cad980f4b7" name="inspect_entity">
          <option value=""><?= t('admin.my_work_runtime.single_replay.select_entity') ?></option>
          <?php foreach ($entityOptions as $key => $label): ?>
            <option value="<?= htmlspecialchars($key) ?>" <?= ($inspectParams['entity'] ?? '') === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="ui-block">
        <label class="u-style-208124c9af"><?= t('admin.my_work_runtime.inspect.limit') ?></label>
        <input class="input" type="number" name="limit" value="<?= (int)($inspectParams['limit'] ?? 10) ?>" min="1" max="50" style="width:80px;">
      </div>
      <div class="ui-block">
        <label class="u-style-208124c9af"><?= t('admin.my_work_runtime.single_replay.primary_area') ?></label>
        <select class="u-style-9fe45cd88c" name="primary_area">
          <option value="production" <?= ($inspectParams['primary_area'] ?? '') === 'production' ? 'selected' : '' ?>><?= t('admin.my_work_runtime.area.production') ?></option>
          <option value="order" <?= ($inspectParams['primary_area'] ?? '') === 'order' ? 'selected' : '' ?>><?= t('admin.my_work_runtime.area.order') ?></option>
          <option value="qc" <?= ($inspectParams['primary_area'] ?? '') === 'qc' ? 'selected' : '' ?>><?= t('admin.my_work_runtime.area.qc') ?></option>
          <option value="dispatch" <?= ($inspectParams['primary_area'] ?? '') === 'dispatch' ? 'selected' : '' ?>><?= t('admin.my_work_runtime.area.dispatch') ?></option>
          <option value="assembly" <?= ($inspectParams['primary_area'] ?? '') === 'assembly' ? 'selected' : '' ?>>Assembly</option>
        </select>
      </div>
      <div class="ui-block">
        <button type="submit" class="btn"><?= t('admin.my_work_runtime.inspect.btn') ?></button>
      </div>
    </div>
    <div class="u-style-b1ecc496e0">
      <label class="u-style-208124c9af"><?= t('admin.my_work_runtime.inspect.cross_areas') ?></label>
      <input class="input" type="text" name="cross_areas" value="<?= htmlspecialchars($inspectParams['cross_areas'] ?? '') ?>" placeholder="e.g. qc, dispatch" style="width:300px;">
    </div>
  </form>
</div>

<?php if ($error): ?>
  <div class="card">
    <div class="alert alert-error u-style-3cce6712d8">
      <strong>Error:</strong> <?= htmlspecialchars($error) ?>
    </div>
  </div>
<?php elseif (count($explainedItems) === 0): ?>
  <?php if (($inspectParams['entity'] ?? '') !== ''): ?>
    <div class="card">
      <div class="alert alert-info"><?= t('admin.my_work_runtime.inspect.no_items') ?></div>
    </div>
  <?php endif; ?>
<?php else: ?>
  <div class="card">
    <h3 class="u-style-291b7bbb01">Explained Items (<?= count($explainedItems) ?>)</h3>
    <table class="data-table u-style-cad980f4b7">
      <thead>
        <tr>
          <th><?= t('admin.my_work_runtime.col.id') ?></th>
          <th><?= t('admin.my_work_runtime.col.entity') ?></th>
          <th><?= t('admin.my_work_runtime.col.included') ?></th>
          <th><?= t('admin.my_work_runtime.col.reason') ?></th>
          <th><?= t('admin.my_work_runtime.col.can_view') ?></th>
          <th><?= t('admin.my_work_runtime.col.bucket') ?></th>
          <th><?= t('admin.my_work_runtime.col.sla_state') ?></th>
          <th><?= t('admin.my_work_runtime.col.section') ?></th>
          <th><?= t('admin.my_work_runtime.col.stage') ?></th>
          <th><?= t('admin.my_work_runtime.col.age_hrs') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($explainedItems as $item): ?>
          <tr>
            <td><?= (int)$item['entity_id'] ?></td>
            <td>
              <strong><?= htmlspecialchars(MyWorkInspector::getEntityTypeLabel($item['entity_type'])) ?></strong>
            </td>
            <td>
              <?php if ($item['included']): ?>
                <span class="badge badge-success">YES</span>
              <?php else: ?>
                <span class="badge badge-danger">NO</span>
              <?php endif; ?>
            </td>
            <td>
              <code><?= htmlspecialchars($item['inclusion_reason']) ?></code>
            </td>
            <td>
              <?php if ($item['can_view']): ?>
                <span class="badge badge-success">Yes</span>
              <?php else: ?>
                <span class="badge badge-danger">No</span>
              <?php endif; ?>
            </td>
            <td>
              <code><?= htmlspecialchars(str_replace('_', ' ', $item['bucket'])) ?></code>
            </td>
            <td>
              <?php if ($item['has_sla']): ?>
                <?php
                  $slaClass = match ($item['sla_state'] ?? '') {
                    'breached', 'overdue' => 'badge-danger',
                    'due_soon' => 'badge-warning',
                    default => 'badge-success',
                  };
                ?>
                <span class="badge <?= $slaClass ?>"><?= htmlspecialchars($item['sla_label'] ?? 'N/A') ?></span>
              <?php else: ?>
                <span class="muted"><?= t('admin.my_work_runtime.row.none') ?></span>
              <?php endif; ?>
            </td>
            <td>
              <code><?= htmlspecialchars(str_replace('_', ' ', $item['section'])) ?></code>
            </td>
            <td><?= htmlspecialchars($item['stage_label']) ?></td>
            <td><?= round((float)($item['age_hours'] ?? 0), 1) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php foreach ($explainedItems as $item): ?>
    <div class="card u-style-8a359a76eb">
      <h4 class="u-style-291b7bbb01">
        <?= htmlspecialchars(MyWorkInspector::getEntityTypeLabel($item['entity_type'])) ?> #<?= (int)$item['entity_id'] ?>
        <?php if ($item['detail_url'] !== '#'): ?>
          <a href="<?= htmlspecialchars($item['detail_url']) ?>" class="btn" style="font-size:12px; padding:4px 8px;"><?= t('admin.my_work_runtime.item.open') ?></a>
        <?php endif; ?>
      </h4>
      <table class="u-style-f3b3f2e4d2">
        <tr class="u-style-fc370c3c07">
          <td class="u-style-6703c3f102"><?= t('admin.my_work_runtime.row.included') ?></td>
          <td class="u-style-885d720aa4">
            <?php if ($item['included']): ?>
              <span class="badge badge-success">YES</span>
            <?php else: ?>
              <span class="badge badge-danger">NO</span>
            <?php endif; ?>
          </td>
        </tr>
        <tr>
          <td class="u-style-f0f2e0fed6"><?= t('admin.my_work_runtime.row.inclusion_reason') ?></td>
          <td class="u-style-885d720aa4"><code><?= htmlspecialchars($item['inclusion_reason']) ?></code></td>
        </tr>
        <tr class="u-style-fc370c3c07">
          <td class="u-style-f0f2e0fed6"><?= t('admin.my_work_runtime.row.can_view') ?></td>
          <td class="u-style-885d720aa4">
            <?php if ($item['can_view']): ?>
              <span class="badge badge-success"><?= t('admin.my_work_runtime.visibility.allowed') ?></span>
            <?php else: ?>
              <span class="badge badge-danger"><?= t('admin.my_work_runtime.visibility.hidden') ?></span>
            <?php endif; ?>
            <code class="u-style-6170d94c81"><?= htmlspecialchars($item['visibility_reason']) ?></code>
          </td>
        </tr>
        <tr>
          <td class="u-style-f0f2e0fed6"><?= t('admin.my_work_runtime.row.work_area') ?></td>
          <td class="u-style-885d720aa4"><code><?= htmlspecialchars($item['work_area']) ?></code></td>
        </tr>
        <tr class="u-style-fc370c3c07">
          <td class="u-style-f0f2e0fed6"><?= t('admin.my_work_runtime.row.bucket') ?></td>
          <td class="u-style-885d720aa4"><code><?= htmlspecialchars(str_replace('_', ' ', $item['bucket'])) ?></code></td>
        </tr>
        <tr>
          <td class="u-style-f0f2e0fed6"><?= t('admin.my_work_runtime.row.status') ?></td>
          <td class="u-style-885d720aa4"><code><?= htmlspecialchars($item['status']) ?></code></td>
        </tr>
        <tr class="u-style-fc370c3c07">
          <td class="u-style-f0f2e0fed6"><?= t('admin.my_work_runtime.row.stage_label') ?></td>
          <td class="u-style-885d720aa4"><?= htmlspecialchars($item['stage_label']) ?></td>
        </tr>
        <tr>
          <td class="u-style-f0f2e0fed6"><?= t('admin.my_work_runtime.row.section') ?></td>
          <td class="u-style-885d720aa4"><code><?= htmlspecialchars(str_replace('_', ' ', $item['section'])) ?></code></td>
        </tr>
        <?php if ($item['has_sla']): ?>
          <tr class="u-style-fc370c3c07">
            <td class="u-style-f0f2e0fed6"><?= t('admin.my_work_runtime.row.sla_state') ?></td>
            <td class="u-style-885d720aa4">
              <?php
                $slaClass = match ($item['sla_state'] ?? '') {
                  'breached', 'overdue' => 'badge-danger',
                  'due_soon' => 'badge-warning',
                  default => 'badge-success',
                };
              ?>
              <span class="badge <?= $slaClass ?>"><?= htmlspecialchars($item['sla_label'] ?? 'N/A') ?></span>
              <?php if ($item['sla_deadline']): ?>
                <span class="muted u-style-6170d94c81">Deadline: <?= htmlspecialchars($item['sla_deadline']) ?></span>
              <?php endif; ?>
              <?php if ($item['sla_escalated']): ?>
                <span class="badge badge-danger u-style-6170d94c81">ESCALATED</span>
              <?php endif; ?>
            </td>
          </tr>
          <tr>
            <td class="u-style-f0f2e0fed6"><?= t('admin.my_work_runtime.row.sla_config') ?></td>
            <td class="u-style-885d720aa4">
              <code>
                <?= htmlspecialchars('status_field: ' . ($item['sla_config']['status_field'] ?? 'N/A')) ?>,
                <?= htmlspecialchars('deadline_field: ' . ($item['sla_config']['deadline_field'] ?? 'N/A')) ?>,
                <?= htmlspecialchars('due_soon_minutes: ' . ($item['sla_config']['due_soon_minutes'] ?? 'N/A')) ?>
              </code>
            </td>
          </tr>
        <?php else: ?>
          <tr class="u-style-fc370c3c07">
            <td class="u-style-f0f2e0fed6"><?= t('admin.my_work_runtime.row.sla') ?></td>
            <td class="u-style-885d720aa4"><span class="muted"><?= t('admin.my_work_runtime.row.no_sla') ?></span></td>
          </tr>
        <?php endif; ?>
        <tr>
          <td class="u-style-f0f2e0fed6"><?= t('admin.my_work_runtime.row.age_hours') ?></td>
          <td class="u-style-885d720aa4"><?= round((float)($item['age_hours'] ?? 0), 2) ?></td>
        </tr>
      </table>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<style>
.badge { padding:2px 8px; border-radius:3px; font-size:12px; }
.badge-success { background: var(--color-success-bg); color: var(--color-success-text); }
.badge-danger { background: var(--color-danger-bg); color: var(--color-danger-text); }
.badge-warning { background: var(--color-warning-bg); color: var(--color-warning-text); }
</style>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

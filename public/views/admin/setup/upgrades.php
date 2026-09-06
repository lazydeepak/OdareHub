<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$scopeOptions = is_array($scopeOptions ?? null) ? $scopeOptions : [];
$targetOptions = is_array($targetOptions ?? null) ? $targetOptions : ['suites' => [], 'modules' => []];
$preview = is_array($preview ?? null) ? $preview : [];
$runs = is_array($runs ?? null) ? $runs : [];
$csrf = (string)($csrf ?? '');
?>
<?php $setupNavCurrent = 'upgrades'; require __DIR__ . '/_nav.php'; ?>
<?php require __DIR__ . '/_flash.php'; ?>

<div class="card">
  <h2 style="margin:0 0 8px">Upgrade Assistant</h2>
  <div class="muted">Use this before Core, suite, or module upgrades. It separates pre-upgrade checks, upgrade-plan preview, migration warnings, post-upgrade verification, and failure/recovery notes so upgrades stay guided and auditable.</div>
</div>

<div class="card">
  <h3 style="margin:0 0 8px">Upgrade Plan Preview</h3>
  <div class="muted" style="margin-bottom:10px">Choose the layer and target first. The assistant previews current and target version context, blockers, warnings, migration/setup notes, and the exact verification pass that will run after the upgrade.</div>
  <form method="post" action="/admin/setup/upgrades/preview">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));align-items:end">
      <label>
        <div class="muted" style="margin-bottom:6px">Upgrade scope</div>
        <select name="upgrade_scope">
          <?php foreach ($scopeOptions as $key => $label): ?>
            <option value="<?= e((string)$key) ?>"<?= ($preview['scope'] ?? '') === $key ? ' selected' : '' ?>><?= e((string)$label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>
        <div class="muted" style="margin-bottom:6px">Suite target</div>
        <select name="target_key">
          <option value="">Select target for suite/module previews</option>
          <?php foreach ((array)($targetOptions['suites'] ?? []) as $suite): ?>
            <option value="<?= e((string)($suite['key'] ?? '')) ?>"><?= e((string)($suite['label'] ?? $suite['key'] ?? '')) ?> (suite)</option>
          <?php endforeach; ?>
          <?php foreach ((array)($targetOptions['modules'] ?? []) as $module): ?>
            <option value="<?= e((string)($module['key'] ?? '')) ?>"><?= e((string)($module['label'] ?? $module['key'] ?? '')) ?> (module / <?= e((string)($module['suite'] ?? 'other')) ?>)</option>
          <?php endforeach; ?>
        </select>
      </label>
      <div>
        <button class="btn ok" type="submit">Preview Upgrade Plan</button>
      </div>
    </div>
  </form>
</div>

<?php if ($preview !== []): ?>
  <div class="card">
    <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start">
      <div>
        <h3 style="margin:0 0 8px"><?= e((string)($preview['scope_label'] ?? 'Upgrade')) ?> Preview: <?= e((string)($preview['target_label'] ?? '')) ?></h3>
        <div class="muted">Current version: <?= e((string)($preview['current_version'] ?? '')) ?> · Target version: <?= e((string)($preview['target_version'] ?? '')) ?></div>
        <div class="muted" style="margin-top:6px"><?= e((string)($preview['changelog_summary'] ?? '')) ?></div>
      </div>
      <span class="pill" style="<?= !empty($preview['ready']) ? 'color:#6df2a6' : 'color:#ff9b9b' ?>"><?= !empty($preview['ready']) ? 'Ready' : 'Blocked' ?></span>
    </div>

    <div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));margin:12px 0">
      <div class="card" style="margin:0"><strong>Missing Tables</strong><div class="muted"><?= e((string)($preview['verification_summary']['missing_tables'] ?? 0)) ?></div></div>
      <div class="card" style="margin:0"><strong>Failed Hooks</strong><div class="muted"><?= e((string)($preview['verification_summary']['failed_hooks'] ?? 0)) ?></div></div>
      <div class="card" style="margin:0"><strong>Schema Gaps</strong><div class="muted"><?= e((string)($preview['verification_summary']['schema_gaps'] ?? 0)) ?></div></div>
      <div class="card" style="margin:0"><strong>Warnings / Errors</strong><div class="muted"><?= e((string)($preview['verification_summary']['warnings'] ?? 0)) ?> / <?= e((string)($preview['verification_summary']['errors'] ?? 0)) ?></div></div>
    </div>

    <?php if (!empty($preview['dependency_summary'])): ?>
      <div class="muted" style="margin-bottom:8px">Dependency health: <?= e((string)($preview['dependency_summary']['status'] ?? 'safe')) ?> · <?= e((string)($preview['dependency_summary']['summary'] ?? '')) ?></div>
    <?php endif; ?>

    <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(260px,1fr))">
      <div class="card" style="margin:0">
        <h4 style="margin:0 0 8px">Pre-Upgrade Checks</h4>
        <?php if (!empty($preview['blocking_issues'])): ?>
          <?php foreach ((array)($preview['blocking_issues'] ?? []) as $issue): ?>
            <div class="muted" style="margin-bottom:6px">• <?= e((string)$issue) ?></div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="muted">No blocking issues found.</div>
        <?php endif; ?>
      </div>
      <div class="card" style="margin:0">
        <h4 style="margin:0 0 8px">Upgrade Plan</h4>
        <?php foreach ((array)($preview['plan_steps'] ?? []) as $step): ?>
          <div class="muted" style="margin-bottom:6px">• <?= e((string)$step) ?></div>
        <?php endforeach; ?>
      </div>
      <div class="card" style="margin:0">
        <h4 style="margin:0 0 8px">Migration Warnings</h4>
        <?php if (!empty($preview['migration_warnings'])): ?>
          <?php foreach ((array)($preview['migration_warnings'] ?? []) as $warning): ?>
            <div class="muted" style="margin-bottom:6px">• <?= e((string)$warning) ?></div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="muted">No migration-specific warnings recorded.</div>
        <?php endif; ?>
      </div>
      <div class="card" style="margin:0">
        <h4 style="margin:0 0 8px">Failure / Recovery Notes</h4>
        <?php foreach ((array)($preview['failure_recovery_notes'] ?? []) as $note): ?>
          <div class="muted" style="margin-bottom:6px">• <?= e((string)$note) ?></div>
        <?php endforeach; ?>
        <?php if (is_array($preview['latest_run'] ?? null)): ?>
          <div class="muted" style="margin-top:10px">Latest run: <?= e((string)($preview['latest_run']['action_key'] ?? '')) ?> · <?= e((string)($preview['latest_run']['status'] ?? '')) ?></div>
          <?php if (!empty($preview['latest_run']['failed_step']['step_label'])): ?>
            <div class="muted" style="margin-top:6px">Last failed step: <?= e((string)($preview['latest_run']['failed_step']['step_label'] ?? '')) ?></div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!empty($preview['warnings'])): ?>
      <div class="card" style="margin-top:12px">
        <h4 style="margin:0 0 8px">Upgrade Warnings</h4>
        <?php foreach ((array)($preview['warnings'] ?? []) as $warning): ?>
          <div class="muted" style="margin-bottom:6px">• <?= e((string)$warning) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
      <?php if (!empty($preview['ready'])): ?>
        <form method="post" action="/admin/setup/upgrades/apply">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="upgrade_scope" value="<?= e((string)($preview['scope'] ?? '')) ?>">
          <input type="hidden" name="target_key" value="<?= e((string)($preview['target_key'] ?? '')) ?>">
          <button class="btn ok" type="submit">Run Upgrade Assistant</button>
        </form>
      <?php endif; ?>
      <form method="post" action="/admin/setup/upgrades/cancel">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <button class="btn" type="submit">Clear Preview</button>
      </form>
    </div>
  </div>
<?php endif; ?>

<div class="card">
  <h3 style="margin:0 0 8px">Upgrade Runs</h3>
  <div class="muted" style="margin-bottom:8px">Recent upgrade assistant runs across Core, suites, and modules. This includes post-upgrade verification status and recovery guidance when a step fails.</div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Started</th>
          <th>Target</th>
          <th>Status</th>
          <th>Failed Step</th>
          <th>Rollback</th>
          <th>Manual Attention</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($runs as $run): ?>
          <tr>
            <td><?= e((string)($run['started_at'] ?? '')) ?></td>
            <td><?= e((string)($run['target_type'] ?? '')) ?> / <?= e((string)($run['target_key'] ?? '')) ?></td>
            <td><?= e((string)($run['status_label'] ?? $run['status'] ?? '')) ?></td>
            <td><?= e((string)($run['failed_step']['step_label'] ?? 'ok')) ?></td>
            <td><?= !empty($run['rollback_performed']) ? 'Yes' : 'No' ?></td>
            <td><?= e((string)($run['manual_attention_text'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if ($runs === []): ?>
          <tr>
            <td colspan="6" class="muted">No upgrade assistant runs yet.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

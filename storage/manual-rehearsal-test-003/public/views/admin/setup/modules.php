<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$modulePanels = is_array($modulePanels ?? null) ? $modulePanels : [];
$csrf = (string)($csrf ?? '');
$impactTone = static function (string $status): string {
    return match ($status) {
        'safe' => 'color:#6df2a6',
        'warning' => 'color:#ffd27d',
        'blocked' => 'color:#ff9b9b',
        'requires_prior_action' => 'color:#8ec1ff',
        default => 'color:#d7d7d7',
    };
};
?>
<?php $setupNavCurrent = 'modules'; require __DIR__ . '/_nav.php'; ?>
<?php require __DIR__ . '/_flash.php'; ?>

<div class="card">
  <h2 style="margin:0 0 8px">Module Lifecycle</h2>
  <div class="muted">Use this panel for focused install, update, repair, schema sync, and dependency validation. Destructive actions are intentionally not part of this surface.</div>
</div>

<div style="display:grid;gap:12px">
  <?php foreach ($modulePanels as $module): ?>
    <?php
    $versioning = is_array($module['versioning'] ?? null) ? $module['versioning'] : [];
    $latestEntry = is_array($versioning['latest_entry'] ?? null) ? $versioning['latest_entry'] : [];
    $dependencyDetail = is_array($module['dependency_detail'] ?? null) ? $module['dependency_detail'] : [];
    $dependencyHealth = is_array($dependencyDetail['dependency_health'] ?? null) ? $dependencyDetail['dependency_health'] : [];
    ?>
    <div class="card" style="margin:0">
      <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start">
        <div>
          <h3 style="margin:0 0 6px"><?= e((string)($module['display_name'] ?? $module['name'] ?? '')) ?></h3>
          <div class="muted">Suite: <?= e((string)($module['suite'] ?? 'other')) ?> · Runtime status: <?= e((string)($module['status'] ?? 'missing')) ?></div>
        </div>
        <span class="pill" style="<?= e((string)($module['status_tone'] ?? '')) ?>"><?= e((string)($module['status_label'] ?? '')) ?></span>
      </div>

      <div style="margin-top:10px">Next: <?= e((string)($module['next_action'] ?? '')) ?></div>

      <div class="muted" style="margin-top:6px">
        Current version: <?= e((string)($versioning['current_version'] ?? '')) ?>
        · Target version: <?= e((string)($versioning['target_version'] ?? '')) ?>
        <?php if (!empty($versioning['upgrade_available'])): ?> · upgrade available<?php endif; ?>
      </div>
      <div class="muted" style="margin-top:6px">Changelog: <?= e((string)($latestEntry['summary'] ?? 'No changelog summary recorded yet.')) ?></div>
      <?php if (!empty($latestEntry['migration_notes'])): ?>
        <div class="muted" style="margin-top:6px">Migration notes: <?= e(implode(' | ', array_map('strval', (array)($latestEntry['migration_notes'] ?? [])))) ?></div>
      <?php endif; ?>

      <?php if (is_array($module['setup_run'] ?? null)): ?>
        <div class="muted" style="margin-top:6px">
          Latest run: <?= e((string)($module['setup_run']['action_key'] ?? '')) ?>
          · <?= e((string)($module['setup_run']['status_label'] ?? '')) ?>
          <?php if (!empty($module['setup_run']['failed_step']['step_label'])): ?>
            · failed at <?= e((string)($module['setup_run']['failed_step']['step_label'] ?? '')) ?>
          <?php endif; ?>
          · rollback <?= !empty($module['setup_run']['rollback_performed']) ? 'yes' : 'no' ?>
        </div>
      <?php endif; ?>

      <div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));margin:12px 0">
        <div class="card" style="margin:0"><strong>Missing Tables</strong><div class="muted"><?= e((string)count((array)($module['missing_tables'] ?? []))) ?></div></div>
        <div class="card" style="margin:0"><strong>Schema Gaps</strong><div class="muted"><?= e((string)count((array)($module['schema_gaps'] ?? []))) ?></div></div>
        <div class="card" style="margin:0"><strong>Dependency Errors</strong><div class="muted"><?= e((string)count((array)($module['dependency_errors'] ?? []))) ?></div></div>
      </div>

      <?php if (!empty($module['dependency_errors'])): ?>
        <div class="card" style="margin:0 0 12px;border-color:rgba(255,80,80,.35)">
          <strong>Dependency Errors</strong>
          <div class="muted" style="margin-top:6px"><?= e(implode(' | ', array_map('strval', (array)($module['dependency_errors'] ?? [])))) ?></div>
        </div>
      <?php endif; ?>

      <div class="card" style="margin:0 0 12px">
        <h4 style="margin:0 0 8px">Dependency Detail</h4>
        <div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));margin-bottom:10px">
          <div class="card" style="margin:0">
            <strong>Direct Dependencies</strong>
            <div class="muted" style="margin-top:6px"><?= e(implode(', ', array_map('strval', (array)($dependencyDetail['direct_dependencies'] ?? []))) ?: 'None') ?></div>
          </div>
          <div class="card" style="margin:0">
            <strong>Reverse Dependencies</strong>
            <div class="muted" style="margin-top:6px"><?= e(implode(', ', array_map('strval', (array)($dependencyDetail['reverse_dependencies'] ?? []))) ?: 'None') ?></div>
          </div>
          <div class="card" style="margin:0">
            <strong>Shared Dependencies</strong>
            <div class="muted" style="margin-top:6px"><?= e(implode(', ', array_map('strval', (array)($dependencyDetail['shared_dependencies'] ?? []))) ?: 'None') ?></div>
          </div>
          <div class="card" style="margin:0">
            <strong>Dependency Health</strong>
            <div style="margin-top:6px"><span class="pill" style="<?= e($impactTone((string)($dependencyHealth['status'] ?? 'safe'))) ?>"><?= e(ucfirst(str_replace('_', ' ', (string)($dependencyHealth['status'] ?? 'safe')))) ?></span></div>
            <div class="muted" style="margin-top:6px"><?= e((string)($dependencyHealth['summary'] ?? '')) ?></div>
          </div>
        </div>
        <?php if (!empty($dependencyHealth['blocking_dependencies'])): ?>
          <div class="muted">Blocking dependencies: <?= e(implode(' | ', array_map('strval', (array)($dependencyHealth['blocking_dependencies'] ?? [])))) ?></div>
        <?php endif; ?>
      </div>

      <?php if (!empty($dependencyDetail['action_impacts'])): ?>
        <div class="card" style="margin:0 0 12px">
          <h4 style="margin:0 0 8px">Action Impact</h4>
          <div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
            <?php foreach ((array)($dependencyDetail['action_impacts'] ?? []) as $impact): ?>
              <div class="card" style="margin:0">
                <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start">
                  <strong><?= e(ucfirst((string)($impact['action'] ?? 'action'))) ?></strong>
                  <span class="pill" style="<?= e($impactTone((string)($impact['status'] ?? 'safe'))) ?>"><?= e((string)($impact['label'] ?? 'Safe')) ?></span>
                </div>
                <div class="muted" style="margin-top:6px"><?= e((string)($impact['summary'] ?? '')) ?></div>
                <?php if (!empty($impact['required_by'])): ?>
                  <div class="muted" style="margin-top:6px">Required by: <?= e(implode(', ', array_map('strval', (array)($impact['required_by'] ?? [])))) ?></div>
                <?php endif; ?>
                <?php if (!empty($impact['blocking_dependencies'])): ?>
                  <div class="muted" style="margin-top:6px">Blocking: <?= e(implode(' | ', array_map('strval', (array)($impact['blocking_dependencies'] ?? [])))) ?></div>
                <?php endif; ?>
                <?php if (!empty($impact['schema_runtime_risks'])): ?>
                  <div class="muted" style="margin-top:6px">Risks: <?= e(implode(' | ', array_map('strval', (array)($impact['schema_runtime_risks'] ?? [])))) ?></div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($latestEntry['setup_upgrade_notes'])): ?>
        <div class="card" style="margin:0 0 12px">
          <strong>Setup / Upgrade Notes</strong>
          <div class="muted" style="margin-top:6px"><?= e(implode(' | ', array_map('strval', (array)($latestEntry['setup_upgrade_notes'] ?? [])))) ?></div>
        </div>
      <?php endif; ?>

      <form method="post" action="/admin/setup/module" style="display:grid;gap:10px;grid-template-columns:minmax(180px,240px) auto;align-items:end">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="module_name" value="<?= e((string)($module['name'] ?? '')) ?>">
        <label>Operation
          <select name="operation">
            <option value="install">Install</option>
            <option value="update">Update</option>
            <option value="repair">Repair</option>
            <option value="schema_sync">Schema sync</option>
            <option value="validate">Validate dependencies</option>
          </select>
        </label>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
          <button class="btn ok" type="submit">Run</button>
          <span class="muted">Requires: <?= e(implode(', ', array_map('strval', (array)($module['requires'] ?? [])))) ?></span>
        </div>
      </form>

      <?php if (is_array($module['setup_run'] ?? null)): ?>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
        <form method="post" action="/admin/setup/recover">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="target_type" value="module">
          <input type="hidden" name="target_key" value="<?= e((string)($module['name'] ?? '')) ?>">
          <input type="hidden" name="mode" value="resume">
          <button class="btn ok" type="submit">Resume Setup</button>
        </form>
        <form method="post" action="/admin/setup/recover">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="target_type" value="module">
          <input type="hidden" name="target_key" value="<?= e((string)($module['name'] ?? '')) ?>">
          <input type="hidden" name="mode" value="retry">
          <button class="btn" type="submit">Retry Failed Step</button>
        </form>
        <form method="post" action="/admin/setup/recover">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="target_type" value="module">
          <input type="hidden" name="target_key" value="<?= e((string)($module['name'] ?? '')) ?>">
          <input type="hidden" name="mode" value="verify">
          <button class="btn" type="submit">Run Verification Again</button>
        </form>
        <form method="post" action="/admin/setup/recover">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="target_type" value="module">
          <input type="hidden" name="target_key" value="<?= e((string)($module['name'] ?? '')) ?>">
          <input type="hidden" name="mode" value="repair">
          <button class="btn" type="submit">Repair Current Target</button>
        </form>
      </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

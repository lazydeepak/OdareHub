<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$suite = is_array($suite ?? null) ? $suite : [];
$csrf = (string)($csrf ?? '');
$setupRun = is_array($suite['setup_run'] ?? null) ? $suite['setup_run'] : null;
$versioning = is_array($suite['versioning'] ?? null) ? $suite['versioning'] : [];
$latestVersionEntry = is_array($versioning['latest_entry'] ?? null) ? $versioning['latest_entry'] : [];
$dependencyDetail = is_array($dependencyDetail ?? null) ? $dependencyDetail : [];
$dependencyHealth = is_array($dependencyDetail['dependency_health'] ?? null) ? $dependencyDetail['dependency_health'] : [];
$issueModel = is_array($suite['verification']['issue_model'] ?? null) ? $suite['verification']['issue_model'] : [];
$issueCounts = is_array($issueModel['counts'] ?? null) ? $issueModel['counts'] : [];
$issueSections = is_array($issueModel['sections'] ?? null) ? $issueModel['sections'] : [];
$primaryIssue = is_array($issueModel['primary_issue'] ?? null) ? $issueModel['primary_issue'] : null;
$issueActions = is_array($issueModel['actions'] ?? null) ? $issueModel['actions'] : [];
$verifyAction = is_array($issueActions['verify'] ?? null) ? $issueActions['verify'] : ['label' => 'Run Verify', 'context' => 'Re-check schema, hooks, and runtime health for this suite.'];
$repairAction = is_array($issueActions['repair'] ?? null) ? $issueActions['repair'] : ['label' => 'Run Targeted Repair', 'context' => 'Review the current issue list, then repair the suite and verify again.'];
$rawDiagnostics = is_array($issueModel['raw_diagnostics'] ?? null) ? $issueModel['raw_diagnostics'] : ['verification' => (array)($suite['verification']['detail'] ?? [])];
$impactTone = static function (string $status): string {
    return match ($status) {
        'safe' => 'color:#6df2a6',
        'warning' => 'color:#ffd27d',
        'blocked' => 'color:#ff9b9b',
        'requires_prior_action' => 'color:#8ec1ff',
        default => 'color:#d7d7d7',
    };
};
$severityClass = static function (string $severity): string {
    return match ($severity) {
        'error' => 'setup-issue-badge-error',
        'warning' => 'setup-issue-badge-warning',
        default => 'setup-issue-badge-info',
    };
};
$severityLabel = static function (string $severity): string {
    return ucfirst($severity !== '' ? $severity : 'info');
};
$classificationLabel = static function (array $issue): string {
    $classification = trim((string)($issue['classification'] ?? ''));
    if ($classification !== '') {
        return ucwords(str_replace('_', ' ', $classification));
    }

    $category = trim((string)($issue['category'] ?? ''));
    return $category !== '' ? ucwords(str_replace('_', ' ', $category)) : 'General';
};
?>
<?php $setupNavCurrent = 'suites'; require __DIR__ . '/_nav.php'; ?>
<?php require __DIR__ . '/_flash.php'; ?>

<div class="card">
  <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start">
    <div>
      <h2 style="margin:0 0 6px"><?= e((string)($suite['label'] ?? 'Suite')) ?></h2>
      <div class="muted">Install, configure/defaults, optional setup profile, and verify are separated below.</div>
    </div>
    <span class="pill" style="<?= e((string)($suite['status_tone'] ?? '')) ?>"><?= e((string)($suite['status_label'] ?? '')) ?></span>
  </div>
  <div style="margin-top:10px">Next recommended action: <?= e((string)($suite['next_action'] ?? '')) ?></div>
</div>

<div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
  <div class="card" style="margin:0"><strong>Current Version</strong><div class="muted"><?= e((string)($versioning['current_version'] ?? '')) ?></div></div>
  <div class="card" style="margin:0"><strong>Target Version</strong><div class="muted"><?= e((string)($versioning['target_version'] ?? '')) ?></div></div>
  <div class="card" style="margin:0"><strong>Upgrade Status</strong><div class="muted"><?= !empty($versioning['upgrade_available']) ? 'Upgrade available' : 'Aligned with target version' ?></div></div>
  <div class="card" style="margin:0"><strong>Latest Changelog</strong><div class="muted"><?= e((string)($latestVersionEntry['summary'] ?? 'No changelog summary recorded yet.')) ?></div></div>
</div>

<?php if (is_array($setupRun)): ?>
<div class="card">
  <h3 style="margin:0 0 8px">Latest Setup State</h3>
  <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start">
    <div>
      <div>Action: <?= e((string)($setupRun['action_key'] ?? '')) ?></div>
      <div class="muted">Started: <?= e((string)($setupRun['started_at'] ?? '')) ?> · Finished: <?= e((string)($setupRun['finished_at'] ?? '')) ?></div>
      <?php if (!empty($setupRun['failed_step']['step_label'])): ?>
        <div style="margin-top:6px">Failed step: <?= e((string)($setupRun['failed_step']['step_label'] ?? '')) ?></div>
      <?php endif; ?>
      <div class="muted" style="margin-top:6px">Rollback performed: <?= !empty($setupRun['rollback_performed']) ? 'Yes' : 'No' ?></div>
      <?php if (trim((string)($setupRun['manual_attention_text'] ?? '')) !== ''): ?>
        <div class="muted" style="margin-top:6px">Manual attention: <?= e((string)($setupRun['manual_attention_text'] ?? '')) ?></div>
      <?php endif; ?>
    </div>
    <span class="pill" style="<?= e((string)($setupRun['status_tone'] ?? '')) ?>"><?= e((string)($setupRun['status_label'] ?? '')) ?></span>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
    <form method="post" action="/admin/setup/recover">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="target_type" value="suite">
      <input type="hidden" name="target_key" value="<?= e((string)($suite['app_key'] ?? '')) ?>">
      <input type="hidden" name="mode" value="resume">
      <button class="btn ok" type="submit">Resume Setup</button>
    </form>
    <form method="post" action="/admin/setup/recover">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="target_type" value="suite">
      <input type="hidden" name="target_key" value="<?= e((string)($suite['app_key'] ?? '')) ?>">
      <input type="hidden" name="mode" value="retry">
      <button class="btn" type="submit">Retry Failed Step</button>
    </form>
    <form method="post" action="/admin/setup/recover">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="target_type" value="suite">
      <input type="hidden" name="target_key" value="<?= e((string)($suite['app_key'] ?? '')) ?>">
      <input type="hidden" name="mode" value="verify">
      <button class="btn" type="submit"><?= e((string)($verifyAction['label'] ?? 'Run Verify')) ?></button>
    </form>
    <form method="post" action="/admin/setup/recover">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="target_type" value="suite">
      <input type="hidden" name="target_key" value="<?= e((string)($suite['app_key'] ?? '')) ?>">
      <input type="hidden" name="mode" value="repair">
      <button class="btn" type="submit"><?= e((string)($repairAction['label'] ?? 'Run Targeted Repair')) ?></button>
    </form>
  </div>
  <div class="setup-issue-action-note" style="margin-top:10px">
    <?= e((string)($verifyAction['context'] ?? '')) ?>
    <?php if (trim((string)($repairAction['context'] ?? '')) !== ''): ?>
      <br><?= e((string)($repairAction['context'] ?? '')) ?>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
  <div class="card" style="margin:0"><strong>Install Status</strong><div class="muted"><?= e((string)($suite['registry_status'] ?? 'uploaded')) ?></div></div>
  <div class="card" style="margin:0"><strong>Child Modules</strong><div class="muted"><?= e((string)($suite['module_summary']['installed'] ?? 0)) ?>/<?= e((string)($suite['module_summary']['total'] ?? 0)) ?> installed</div></div>
  <div class="card" style="margin:0"><strong>Configured Profile</strong><div class="muted"><?= e((string)($suite['configured_profile'] ?: 'not set')) ?></div></div>
  <div class="card" style="margin:0">
    <strong>Verification Status</strong>
    <div class="muted" style="margin-top:6px">Errors: <?= e((string)($issueCounts['errors'] ?? 0)) ?> · Warnings: <?= e((string)($issueCounts['warnings'] ?? 0)) ?></div>
  </div>
</div>

<div class="card">
  <div class="section-head">
    <div>
      <h3 style="margin:0 0 6px">Verification Status</h3>
      <div class="muted">Structured setup warnings and errors for this suite, grouped by schema, runtime, and UI diagnostics.</div>
    </div>
    <span class="pill" style="<?= e(((int)($issueCounts['errors'] ?? 0)) > 0 ? 'color:#ff9b9b' : (((int)($issueCounts['warnings'] ?? 0)) > 0 ? 'color:#ffd27d' : 'color:#6df2a6')) ?>">
      <?= ((int)($issueCounts['errors'] ?? 0)) > 0 ? 'Blocking Issues Present' : ((((int)($issueCounts['warnings'] ?? 0)) > 0) ? 'Warnings to Review' : 'Healthy') ?>
    </span>
  </div>

  <div class="setup-issue-summary-grid">
    <div class="setup-issue-stat">
      <div class="setup-issue-stat-label">Errors</div>
      <div class="setup-issue-stat-value u-tone-danger"><?= (int)($issueCounts['errors'] ?? 0) ?></div>
      <div class="setup-issue-stat-note">Blocking verification or runtime issues.</div>
    </div>
    <div class="setup-issue-stat">
      <div class="setup-issue-stat-label">Warnings</div>
      <div class="setup-issue-stat-value u-tone-warning"><?= (int)($issueCounts['warnings'] ?? 0) ?></div>
      <div class="setup-issue-stat-note">Non-blocking issues that still need follow-up.</div>
    </div>
    <div class="setup-issue-stat">
      <div class="setup-issue-stat-label">Schema Issues</div>
      <div class="setup-issue-stat-value"><?= (int)($issueCounts['schema'] ?? 0) ?></div>
      <div class="setup-issue-stat-note">Migrations, tables, and dependency gaps.</div>
    </div>
    <div class="setup-issue-stat">
      <div class="setup-issue-stat-label">Runtime / Hook Issues</div>
      <div class="setup-issue-stat-value"><?= (int)($issueCounts['runtime'] ?? 0) ?></div>
      <div class="setup-issue-stat-note">Route bootstrapping and runtime file checks.</div>
    </div>
    <div class="setup-issue-stat">
      <div class="setup-issue-stat-label">UI / Diagnostics</div>
      <div class="setup-issue-stat-value"><?= (int)($issueCounts['ui'] ?? 0) ?></div>
      <div class="setup-issue-stat-note">Navigation, locale, and contract diagnostics.</div>
    </div>
  </div>

  <?php if (is_array($primaryIssue)): ?>
    <div class="setup-issue-card setup-issue-card-primary" style="margin-top:14px">
      <div class="setup-issue-card-head">
        <div>
          <div class="setup-issue-stat-label">Primary Issue</div>
          <h4 class="setup-issue-card-title"><?= e((string)($primaryIssue['type'] ?? 'Issue')) ?></h4>
          <div class="setup-issue-card-subtitle"><?= e((string)($primaryIssue['reason'] ?? '')) ?></div>
        </div>
        <div class="setup-issue-badges">
          <span class="setup-issue-badge <?= e($severityClass((string)($primaryIssue['severity'] ?? 'info'))) ?>">
            <?= e($severityLabel((string)($primaryIssue['severity'] ?? 'info'))) ?>
          </span>
          <span class="setup-issue-badge"><?= e((string)($primaryIssue['status'] ?? 'Review')) ?></span>
        </div>
      </div>

      <div class="setup-issue-detail-grid">
        <div class="setup-issue-detail">
          <div class="setup-issue-detail-label">Type</div>
          <div class="setup-issue-detail-value"><?= e((string)($primaryIssue['type'] ?? 'Issue')) ?></div>
        </div>
        <div class="setup-issue-detail">
          <div class="setup-issue-detail-label">Classification</div>
          <div class="setup-issue-detail-value"><?= e($classificationLabel((array)$primaryIssue)) ?></div>
        </div>
        <div class="setup-issue-detail">
          <div class="setup-issue-detail-label">Affected Item</div>
          <div class="setup-issue-detail-value"><?= e((string)($primaryIssue['affected_item'] ?? '')) ?></div>
        </div>
        <div class="setup-issue-detail">
          <div class="setup-issue-detail-label">Severity</div>
          <div class="setup-issue-detail-value"><?= e($severityLabel((string)($primaryIssue['severity'] ?? 'info'))) ?></div>
        </div>
        <div class="setup-issue-detail">
          <div class="setup-issue-detail-label">Status</div>
          <div class="setup-issue-detail-value"><?= e((string)($primaryIssue['status'] ?? 'Review')) ?></div>
        </div>
      </div>

      <div class="setup-issue-detail-grid">
        <?php foreach ((array)($primaryIssue['details'] ?? []) as $detail): ?>
          <div class="setup-issue-detail">
            <div class="setup-issue-detail-label"><?= e((string)($detail['label'] ?? 'Detail')) ?></div>
            <div class="setup-issue-detail-value"><?= e((string)($detail['value'] ?? '')) ?></div>
          </div>
        <?php endforeach; ?>
      </div>

      <div>
        <div class="setup-issue-detail-label">Recommended Actions</div>
        <ul class="setup-issue-recommendations">
          <?php foreach ((array)($primaryIssue['suggested_actions'] ?? []) as $action): ?>
            <li><?= e((string)$action) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div class="setup-issue-actions">
        <button class="btn" type="button"<?= trim((string)($primaryIssue['file_path'] ?? '')) === '' ? ' disabled' : ' title="' . e((string)($primaryIssue['file_path'] ?? '')) . '"' ?>>View Migration File</button>
        <a class="btn" href="#suite-raw-diagnostics">Show Blocked SQL Rule</a>
        <form method="post" action="/admin/setup/recover">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="target_type" value="suite">
          <input type="hidden" name="target_key" value="<?= e((string)($suite['app_key'] ?? '')) ?>">
          <input type="hidden" name="mode" value="repair">
          <button class="btn" type="submit"><?= e((string)($repairAction['label'] ?? 'Run Targeted Repair')) ?></button>
        </form>
        <button class="btn" type="button" disabled>Mark as Reviewed</button>
      </div>
      <div class="setup-issue-action-note"><?= e((string)($repairAction['context'] ?? '')) ?></div>
    </div>
  <?php else: ?>
    <div class="setup-issue-empty" style="margin-top:14px">No schema, runtime, or UI verification issues are currently detected for this suite.</div>
  <?php endif; ?>
</div>

<div class="card">
  <h3 style="margin:0 0 8px">Dependency Detail</h3>
  <div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));margin-bottom:12px">
    <div class="card" style="margin:0">
      <strong>Direct Dependencies</strong>
      <div class="muted" style="margin-top:6px"><?= e(implode(', ', array_map('strval', (array)($dependencyDetail['direct_dependencies'] ?? []))) ?: 'None') ?></div>
    </div>
    <div class="card" style="margin:0">
      <strong>Reverse Dependencies</strong>
      <div class="muted" style="margin-top:6px"><?= e(implode(', ', array_map('strval', (array)($dependencyDetail['reverse_dependencies'] ?? []))) ?: 'None') ?></div>
    </div>
    <div class="card" style="margin:0">
      <strong>Shared Platform Dependencies</strong>
      <div class="muted" style="margin-top:6px"><?= e(implode(', ', array_map('strval', (array)($dependencyDetail['shared_platform_dependencies'] ?? []))) ?: 'None') ?></div>
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
<div class="card">
  <h3 style="margin:0 0 8px">Action Impact</h3>
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

<div class="card">
  <h3 style="margin:0 0 8px">Version & Changelog</h3>
  <div class="muted" style="margin-bottom:10px">Current and target suite versions are shown here before install, configure, verify, or restore-related work. Use the notes below to understand migration/setup expectations for this layer.</div>
  <?php if (!empty($versioning['entries'])): ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Version</th>
            <th>Release Date</th>
            <th>Summary</th>
            <th>Breaking Changes</th>
            <th>Migration Notes</th>
            <th>Setup / Upgrade Notes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ((array)($versioning['entries'] ?? []) as $entry): ?>
            <tr>
              <td><?= e((string)($entry['version'] ?? '')) ?></td>
              <td><?= e((string)($entry['release_date'] ?? '')) ?></td>
              <td><?= e((string)($entry['summary'] ?? '')) ?></td>
              <td><?= e(implode(' | ', array_map('strval', (array)($entry['breaking_changes'] ?? [])))) ?></td>
              <td><?= e(implode(' | ', array_map('strval', (array)($entry['migration_notes'] ?? [])))) ?></td>
              <td><?= e(implode(' | ', array_map('strval', (array)($entry['setup_upgrade_notes'] ?? [])))) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="muted">No suite changelog entries recorded yet.</div>
  <?php endif; ?>
</div>

<div class="card">
  <h3 style="margin:0 0 8px">Suite Actions</h3>
  <div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));align-items:end">
    <form method="post" action="/admin/setup/suite" class="card" style="margin:0">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="suite_key" value="<?= e((string)($suite['app_key'] ?? '')) ?>">
      <input type="hidden" name="phase" value="install">
      <strong>Install</strong>
      <div class="muted" style="margin:6px 0 10px">Register bundle, install child modules in safe order, and stage runtime artifacts.</div>
      <button class="btn ok" type="submit">Run Install</button>
    </form>

    <form method="post" action="/admin/setup/suite" class="card" style="margin:0">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="suite_key" value="<?= e((string)($suite['app_key'] ?? '')) ?>">
      <input type="hidden" name="phase" value="configure">
      <label>Configure / defaults
        <select name="profile_key">
          <option value="">Use default profile</option>
          <?php foreach ((array)($suite['available_profiles'] ?? []) as $profile): ?>
            <option value="<?= e((string)($profile['key'] ?? '')) ?>"<?= (string)($suite['configured_profile'] ?? '') === (string)($profile['key'] ?? '') ? ' selected' : '' ?>><?= e((string)($profile['label'] ?? '')) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <div class="muted" style="margin:6px 0 10px">Enable runtime, apply suite defaults, and activate the selected module subset.</div>
      <button class="btn ok" type="submit">Run Configure</button>
    </form>

    <form method="post" action="/admin/setup/suite" class="card" style="margin:0">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="suite_key" value="<?= e((string)($suite['app_key'] ?? '')) ?>">
      <input type="hidden" name="phase" value="profile">
      <label>Optional setup profile
        <select name="profile_key" required>
          <?php foreach ((array)($suite['available_profiles'] ?? []) as $profile): ?>
            <option value="<?= e((string)($profile['key'] ?? '')) ?>"><?= e((string)($profile['label'] ?? '')) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <div class="muted" style="margin:6px 0 10px">Apply a suite-specific profile without re-running the whole platform profile.</div>
      <button class="btn ok" type="submit">Apply Profile</button>
    </form>

    <form method="post" action="/admin/setup/suite" class="card" style="margin:0">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="suite_key" value="<?= e((string)($suite['app_key'] ?? '')) ?>">
      <input type="hidden" name="phase" value="verify">
      <strong>Verify</strong>
      <div class="muted" style="margin:6px 0 10px"><?= e((string)($verifyAction['context'] ?? 'Re-check schema, hooks, and runtime health for this suite.')) ?></div>
      <button class="btn ok" type="submit"><?= e((string)($verifyAction['label'] ?? 'Run Verify')) ?></button>
    </form>
  </div>
</div>

<div class="card">
  <h3 style="margin:0 0 8px">Child Module Status</h3>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Module</th>
          <th>Current / Target</th>
          <th>Status</th>
          <th>Installed</th>
          <th>Dependencies</th>
          <th>Changelog</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ((array)($suite['modules'] ?? []) as $module): ?>
          <?php $moduleVersion = is_array($module['versioning'] ?? null) ? $module['versioning'] : []; ?>
          <tr>
            <td><?= e((string)($module['display_name'] ?? $module['name'] ?? '')) ?></td>
            <td><?= e((string)($moduleVersion['current_version'] ?? '')) ?> / <?= e((string)($moduleVersion['target_version'] ?? '')) ?></td>
            <td><?= e((string)($module['status'] ?? 'missing')) ?></td>
            <td><?= !empty($module['installed']) ? 'Yes' : 'No' ?></td>
            <td><?= e(implode(', ', array_map('strval', (array)($module['requires'] ?? [])))) ?></td>
            <td><?= e((string)($moduleVersion['latest_entry']['summary'] ?? 'No changelog summary recorded yet.')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <h3 style="margin:0 0 8px">Verification</h3>
  <div class="muted" style="margin-bottom:12px">Issues are grouped below so it is clear what broke, where it came from, why it happened, and what to do next. Raw diagnostics are still available when you need them.</div>

  <div class="setup-issue-sections">
    <?php foreach ($issueSections as $section): ?>
      <?php $sectionIssues = is_array($section['issues'] ?? null) ? $section['issues'] : []; ?>
      <section class="setup-issue-section">
        <div class="setup-issue-section-head">
          <div>
            <h4 class="setup-issue-section-title"><?= e((string)($section['label'] ?? 'Issues')) ?></h4>
            <div class="setup-issue-section-desc"><?= e((string)($section['description'] ?? '')) ?></div>
          </div>
          <span class="pill"><?= e((string)($section['count'] ?? 0)) ?></span>
        </div>

        <?php if ($sectionIssues === []): ?>
          <div class="setup-issue-empty">No <?= e(strtolower((string)($section['label'] ?? 'issues'))) ?> are currently detected.</div>
        <?php else: ?>
          <div class="setup-issue-list">
            <?php foreach ($sectionIssues as $issue): ?>
              <article class="setup-issue-card">
                <div class="setup-issue-card-head">
                  <div>
                    <h5 class="setup-issue-card-title"><?= e((string)($issue['type'] ?? 'Issue')) ?></h5>
                    <div class="setup-issue-card-subtitle"><?= e((string)($issue['reason'] ?? '')) ?></div>
                  </div>
                  <div class="setup-issue-badges">
                    <span class="setup-issue-badge <?= e($severityClass((string)($issue['severity'] ?? 'info'))) ?>">
                      <?= e($severityLabel((string)($issue['severity'] ?? 'info'))) ?>
                    </span>
                    <span class="setup-issue-badge"><?= e((string)($issue['status'] ?? 'Review')) ?></span>
                  </div>
                </div>

                <div class="setup-issue-detail-grid">
                  <div class="setup-issue-detail">
                    <div class="setup-issue-detail-label">Classification</div>
                    <div class="setup-issue-detail-value"><?= e($classificationLabel((array)$issue)) ?></div>
                  </div>
                  <div class="setup-issue-detail">
                    <div class="setup-issue-detail-label">Affected Item</div>
                    <div class="setup-issue-detail-value"><?= e((string)($issue['affected_item'] ?? '')) ?></div>
                  </div>
                  <?php foreach ((array)($issue['details'] ?? []) as $detail): ?>
                    <div class="setup-issue-detail">
                      <div class="setup-issue-detail-label"><?= e((string)($detail['label'] ?? 'Detail')) ?></div>
                      <div class="setup-issue-detail-value"><?= e((string)($detail['value'] ?? '')) ?></div>
                    </div>
                  <?php endforeach; ?>
                </div>

                <div>
                  <div class="setup-issue-detail-label">Recommended Actions</div>
                  <ul class="setup-issue-recommendations">
                    <?php foreach ((array)($issue['suggested_actions'] ?? []) as $action): ?>
                      <li><?= e((string)$action) ?></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
  </div>

  <details class="setup-raw-diagnostics" id="suite-raw-diagnostics">
    <summary>Raw Diagnostics</summary>
    <pre><?= e(json_encode($rawDiagnostics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}') ?></pre>
  </details>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

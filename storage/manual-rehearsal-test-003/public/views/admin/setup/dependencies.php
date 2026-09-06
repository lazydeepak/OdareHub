<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$graph = is_array($graph ?? null) ? $graph : [];
$summary = is_array($graph['summary'] ?? null) ? $graph['summary'] : [];
$coreRows = is_array($graph['core'] ?? null) ? $graph['core'] : [];
$suiteRows = is_array($graph['suites'] ?? null) ? $graph['suites'] : [];
$moduleRows = is_array($graph['modules'] ?? null) ? $graph['modules'] : [];
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
<?php $setupNavCurrent = 'dependencies'; require __DIR__ . '/_nav.php'; ?>
<?php require __DIR__ . '/_flash.php'; ?>

<div class="card">
  <h2 style="margin:0 0 8px">Dependency Graph</h2>
  <div class="muted">Use this page before install, update, disable, uninstall, restore, purge, or release work. Core, suites, and modules stay visually separated so impact is easier to understand.</div>
</div>

<div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));margin-bottom:12px">
  <div class="card" style="margin:0"><strong>Core Modules</strong><div class="muted"><?= e((string)($summary['core_modules'] ?? 0)) ?></div></div>
  <div class="card" style="margin:0"><strong>Suites</strong><div class="muted"><?= e((string)($summary['suites'] ?? 0)) ?></div></div>
  <div class="card" style="margin:0"><strong>Modules</strong><div class="muted"><?= e((string)($summary['modules'] ?? 0)) ?></div></div>
  <div class="card" style="margin:0"><strong>Blocked / Warning</strong><div class="muted"><?= e((string)($summary['blocked_modules'] ?? 0)) ?> / <?= e((string)($summary['warning_modules'] ?? 0)) ?></div></div>
</div>

<div class="card">
  <h3 style="margin:0 0 8px">Core / Platform</h3>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Module</th>
          <th>Requires</th>
          <th>Required By</th>
          <th>Health</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($coreRows as $row): ?>
          <?php $health = is_array($row['health'] ?? null) ? $row['health'] : []; ?>
          <tr>
            <td><?= e((string)($row['display_name'] ?? $row['name'] ?? '')) ?></td>
            <td><?= e(implode(', ', array_map('strval', (array)($row['requires'] ?? []))) ?: 'None') ?></td>
            <td><?= e(implode(', ', array_map('strval', (array)($row['required_by'] ?? []))) ?: 'None') ?></td>
            <td><span class="pill" style="<?= e($impactTone((string)($health['status'] ?? 'safe'))) ?>"><?= e(ucfirst(str_replace('_', ' ', (string)($health['status'] ?? 'safe')))) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <h3 style="margin:0 0 8px">Suites</h3>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Suite</th>
          <th>Child Modules</th>
          <th>Shared Platform Dependencies</th>
          <th>Reverse Dependencies</th>
          <th>Health</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($suiteRows as $row): ?>
          <?php $health = is_array($row['dependency_health'] ?? null) ? $row['dependency_health'] : []; ?>
          <tr>
            <td><a href="/admin/setup/suites/detail?suite_key=<?= urlencode((string)($row['suite_key'] ?? '')) ?>"><?= e((string)($row['label'] ?? $row['suite_key'] ?? '')) ?></a></td>
            <td><?= e(implode(', ', array_map('strval', (array)($row['child_modules'] ?? []))) ?: 'None') ?></td>
            <td><?= e(implode(', ', array_map('strval', (array)($row['shared_platform_dependencies'] ?? []))) ?: 'None') ?></td>
            <td><?= e(implode(', ', array_map('strval', (array)($row['reverse_dependencies'] ?? []))) ?: 'None') ?></td>
            <td>
              <span class="pill" style="<?= e($impactTone((string)($health['status'] ?? 'safe'))) ?>"><?= e(ucfirst(str_replace('_', ' ', (string)($health['status'] ?? 'safe')))) ?></span>
              <div class="muted" style="margin-top:6px"><?= e((string)($health['summary'] ?? '')) ?></div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <h3 style="margin:0 0 8px">Modules</h3>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Module</th>
          <th>Suite</th>
          <th>Direct Dependencies</th>
          <th>Reverse Dependencies</th>
          <th>Shared Dependencies</th>
          <th>Health</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($moduleRows as $row): ?>
          <?php $health = is_array($row['dependency_health'] ?? null) ? $row['dependency_health'] : []; ?>
          <tr>
            <td><?= e((string)($row['display_name'] ?? $row['name'] ?? '')) ?></td>
            <td><?= e((string)($row['suite'] ?? 'other')) ?></td>
            <td><?= e(implode(', ', array_map('strval', (array)($row['direct_dependencies'] ?? []))) ?: 'None') ?></td>
            <td><?= e(implode(', ', array_map('strval', (array)($row['reverse_dependencies'] ?? []))) ?: 'None') ?></td>
            <td><?= e(implode(', ', array_map('strval', (array)($row['shared_dependencies'] ?? []))) ?: 'None') ?></td>
            <td>
              <span class="pill" style="<?= e($impactTone((string)($health['status'] ?? 'safe'))) ?>"><?= e(ucfirst(str_replace('_', ' ', (string)($health['status'] ?? 'safe')))) ?></span>
              <div class="muted" style="margin-top:6px"><?= e((string)($health['summary'] ?? '')) ?></div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

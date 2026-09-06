<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$csrf = (string)($csrf ?? '');
$scaffoldTypes = is_array($scaffoldTypes ?? null) ? $scaffoldTypes : [];
$suiteOptions = is_array($suiteOptions ?? null) ? $suiteOptions : [];
?>

<?php $setupNavCurrent = 'scaffolds'; require __DIR__ . '/_nav.php'; ?>
<?php require __DIR__ . '/_flash.php'; ?>

<div class="card">
  <h2 style="margin:0 0 8px">Suite & Module Scaffolds</h2>
  <div class="muted">Generate standard platform structure instead of hand-assembling files. This tool creates starter manifests, routes, navigation, bootstrap hooks, and a baseline controller/service/view shell so new work starts from a consistent layout.</div>
</div>

<div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));margin-bottom:12px">
  <?php foreach ($scaffoldTypes as $type): ?>
    <div class="card" style="margin:0">
      <strong><?= e((string)($type['label'] ?? '')) ?></strong>
      <div class="muted" style="margin-top:6px"><?= e((string)($type['summary'] ?? '')) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(320px,1fr))">
  <form method="post" action="/admin/setup/scaffold" class="card" style="margin:0;display:grid;gap:10px">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="scaffold_type" value="suite">

    <div>
      <h3 style="margin:0 0 6px">New Suite</h3>
      <div class="muted">Creates a new bundle/app shell under `apps/` and registers it through local app discovery.</div>
    </div>

    <label>Suite Name
      <input type="text" name="suite_name" placeholder="Field Ops" required>
    </label>
    <label>Suite Key
      <input type="text" name="suite_key" placeholder="field_ops">
    </label>
    <label>Directory Name
      <input type="text" name="directory_name" placeholder="FieldOps">
    </label>
    <label>Route Slug
      <input type="text" name="route_slug" placeholder="field_ops">
    </label>
    <label>Permission Prefix
      <input type="text" name="permission_prefix" placeholder="field_ops">
    </label>

    <div class="muted">Output includes `manifest.json`, `routes.php`, `navigation.php`, `bootstrap.php`, `versioning.php`, dashboard controller/view, host contribution placeholder, and starter directories for modules/migrations.</div>
    <button class="btn ok" type="submit">Create Suite Scaffold</button>
  </form>

  <form method="post" action="/admin/setup/scaffold" class="card" style="margin:0;display:grid;gap:10px">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="scaffold_type" value="module">

    <div>
      <h3 style="margin:0 0 6px">New Module</h3>
      <div class="muted">Adds a child module into an existing suite and updates the suite manifest so routes, menus, and discovery stay aligned.</div>
    </div>

    <label>Owner Suite
      <select name="suite_key" required>
        <option value="">Choose a suite</option>
        <?php foreach ($suiteOptions as $suite): ?>
          <option value="<?= e((string)($suite['key'] ?? '')) ?>"><?= e((string)($suite['label'] ?? $suite['key'] ?? '')) ?> (<?= e((string)($suite['directory_name'] ?? '')) ?>)</option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Module Name
      <input type="text" name="module_name" placeholder="Approvals" required>
    </label>
    <label>Module Display Name
      <input type="text" name="module_display_name" placeholder="Approvals">
    </label>
    <label>Module Slug
      <input type="text" name="module_slug" placeholder="approvals">
    </label>

    <div class="muted">Output includes `plugin.json`, `routes.php`, `navigation.php`, `bootstrap.php`, install/update scripts, controller/service/view structure, and a suite manifest update for module registration.</div>
    <button class="btn ok" type="submit">Create Module Scaffold</button>
  </form>
</div>

<div class="card">
  <h3 style="margin:0 0 8px">Generated Structure</h3>
  <div class="muted" style="margin-bottom:8px">Both scaffold types are intentionally small and practical. They produce a working shell that the team can evolve, rather than trying to guess business logic up front.</div>
  <div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(260px,1fr))">
    <div class="card" style="margin:0">
      <strong>Suite Output</strong>
      <div class="muted" style="margin-top:6px">`manifest.json`, `routes.php`, `navigation.php`, `bootstrap.php`, `versioning.php`, `Controllers/`, `Services/`, `Views/`, `migrations/`, `modules/`</div>
    </div>
    <div class="card" style="margin:0">
      <strong>Module Output</strong>
      <div class="muted" style="margin-top:6px">`plugin.json`, `routes.php`, `navigation.php`, `bootstrap.php`, `install.php`, `update.php`, `Controllers/`, `Services/`, `Views/`</div>
    </div>
  </div>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

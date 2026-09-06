<?php
declare(strict_types=1);

$developerStripContext = isset($developerStripContext) && is_array($developerStripContext) ? $developerStripContext : null;
if ($developerStripContext === null) {
    return;
}

$workspaceName = trim((string)($developerStripContext['workspace_display_name'] ?? ''));
$overviewUrl = trim((string)($developerStripContext['overview_url'] ?? ''));
$workUrl = trim((string)($developerStripContext['work_url'] ?? ''));
$rulesUrl = trim((string)($developerStripContext['rules_url'] ?? ''));
$decisionsUrl = trim((string)($developerStripContext['decisions_url'] ?? ''));
$breadcrumbs = isset($developerStripContext['breadcrumbs']) && is_array($developerStripContext['breadcrumbs'])
    ? array_values(array_filter($developerStripContext['breadcrumbs'], 'is_array'))
    : [];

if ($workspaceName === '') {
    return;
}

$links = [];
if ($overviewUrl !== '') {
    $links[] = '<a href="' . e($overviewUrl) . '">Overview</a>';
}
if ($workUrl !== '') {
    $links[] = '<a href="' . e($workUrl) . '">Work</a>';
}
if ($rulesUrl !== '') {
    $links[] = '<a href="' . e($rulesUrl) . '">Rules</a>';
}
if ($decisionsUrl !== '') {
    $links[] = '<a href="' . e($decisionsUrl) . '">Decisions</a>';
}

if ($links === []) {
    return;
}

$breadcrumbLinks = [];
foreach ($breadcrumbs as $breadcrumb) {
    $label = trim((string)($breadcrumb['label'] ?? ''));
    $url = trim((string)($breadcrumb['url'] ?? ''));
    if ($label === '') {
        continue;
    }
    $breadcrumbLinks[] = $url !== ''
        ? '<a href="' . e($url) . '">' . e($label) . '</a>'
        : '<span>' . e($label) . '</span>';
}
?>
<nav class="ew-strip" aria-label="Engineering workspace documents">
  <div class="ew-strip-inner">
    <?php if ($breadcrumbLinks !== []): ?>
      <div class="ew-strip-identity ew-strip-breadcrumbs"<?= strlen($workspaceName) > 32 ? ' title="' . e($workspaceName) . '"' : '' ?>><?= implode('<span class="ew-strip-sep" aria-hidden="true">/</span>', $breadcrumbLinks) ?></div>
    <?php else: ?>
      <div class="ew-strip-identity"<?= strlen($workspaceName) > 32 ? ' title="' . e($workspaceName) . '"' : '' ?>><?= e($workspaceName) ?></div>
    <?php endif; ?>
    <div class="ew-strip-documents">
      <?= implode("\n      ", $links) ?>
    </div>
  </div>
</nav>

<?php
$inspectorSections = [
    'ownership_studio',
    'ownership_shell',
    'ownership_platform',
    'ownership_apps',
    'ownership_org',
    'ownership_assets',
];
  $metadata = isset($vcModel['metadata']) && is_array($vcModel['metadata']) ? $vcModel['metadata'] : [];
  $socketCatalogs = isset($metadata['socket_catalogs']) && is_array($metadata['socket_catalogs'])
    ? array_values(array_filter($metadata['socket_catalogs'], 'is_array'))
    : [];
  $previewFixtures = isset($metadata['preview_fixtures']) && is_array($metadata['preview_fixtures'])
    ? array_values(array_filter($metadata['preview_fixtures'], 'is_array'))
    : [];
  $sourceScope = (string)($metadata['source_scope'] ?? 'studio_customization_metadata_only');
?>
<div class="cs-vc__panel">
  <h3><?= e($vc('inspector')) ?></h3>
  <p><?= e($vc('inspector_hint')) ?></p>
  <button type="button" disabled><?= e($vc('status_disabled')) ?></button>
</div>

<div class="cs-vc__panel">
  <h3><?= e($vc('ownership_title')) ?></h3>
  <ul>
    <?php foreach ($inspectorSections as $section): ?>
      <li><?= e($vc($section)) ?></li>
    <?php endforeach; ?>
  </ul>
</div>

<div class="cs-vc__panel">
  <h3><?= e($vc('metadata_read_only')) ?></h3>
  <p><?= e($vc('metadata_source_scope')) ?>: <strong><?= e($sourceScope) ?></strong></p>

  <h4><?= e($vc('socket_catalogs')) ?></h4>
  <?php if ($socketCatalogs === []): ?>
    <p><?= e($vc('metadata_none')) ?></p>
  <?php else: ?>
    <ul>
      <?php foreach ($socketCatalogs as $socket): ?>
        <li><?= e((string)($socket['name'] ?? '')) ?> (<code><?= e((string)($socket['file'] ?? '')) ?></code>)</li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <h4><?= e($vc('preview_fixtures')) ?></h4>
  <?php if ($previewFixtures === []): ?>
    <p><?= e($vc('metadata_none')) ?></p>
  <?php else: ?>
    <ul>
      <?php foreach ($previewFixtures as $fixture): ?>
        <li><?= e((string)($fixture['name'] ?? '')) ?> (<code><?= e((string)($fixture['file'] ?? '')) ?></code>)</li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

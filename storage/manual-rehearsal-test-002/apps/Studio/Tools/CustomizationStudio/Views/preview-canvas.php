<?php
$vcDefaultFixtureName = (string)($vcDefaultFixture['name'] ?? $vc('fixture_selected_title'));
$vcDefaultFixtureId = (string)($vcDefaultFixture['id'] ?? '');
?>
<article class="cs-vc__fixture-canvas" id="cs-vc-fixture-canvas" data-fixture-id="<?= e($vcDefaultFixtureId) ?>" aria-live="polite">
  <header class="cs-vc__fixture-canvas-head">
    <h3 id="cs-vc-canvas-title"><?= e($vcDefaultFixtureName) ?></h3>
    <p id="cs-vc-canvas-hint"><?= e($vc('canvas_placeholder_hint')) ?></p>
  </header>

  <div class="cs-vc__canvas-static-note"><?= e($vc('canvas_static_marker')) ?></div>

  <div class="cs-vc__canvas-scaffold" id="cs-vc-canvas-scaffold" data-layout="<?= e($vcDefaultFixtureId) ?>"></div>
</article>

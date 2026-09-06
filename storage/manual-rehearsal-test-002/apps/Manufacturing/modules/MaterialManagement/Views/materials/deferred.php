<?php declare(strict_types=1); ?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php require __DIR__ . '/partials/surface_header.php'; ?>
<?php
$deferredSurfaceTitle = (string)($deferredSurfaceTitle ?? 'Deferred Material Surface');
$detailNote = trim((string)($detailNote ?? ''));
?>

<section class="card">
  <div class="section-head">
    <div class="ui-block">
      <h3 class="u-style-1169661891"><?= e($deferredSurfaceTitle) ?></h3>
      <div class="muted">This surface remains module-owned, but it is intentionally deferred from the Material v1 rebuild.</div>
    </div>
  </div>
  <div class="muted u-mt-10">
    MaterialManagement now promotes only Dashboard, Orders, Receipt, and Stock as first-class runtime pages.
    This deferred surface is still reachable for governance continuity, but it is not treated as a primary operational route until it is rebuilt truthfully.
  </div>
  <?php if ($detailNote !== ''): ?>
    <div class="muted u-mt-10"><?= e($detailNote) ?></div>
  <?php endif; ?>
  <div class="row u-mt-10">
    <a class="btn" href="/apps/manufacturing/materials">Back To Materials Workspace</a>
    <a class="btn" href="/apps/manufacturing/materials/orders">Open Orders</a>
    <a class="btn" href="/apps/manufacturing/materials/receipt">Open Receipt</a>
    <a class="btn" href="/apps/manufacturing/materials/stock">Open Stock</a>
  </div>
</section>

</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>

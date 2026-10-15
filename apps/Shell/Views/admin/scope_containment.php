<?php
/**
 * Render an explicit unavailable state for a contained operational workspace.
 * A contained queue is not an empty queue: no records, counts, notifications,
 * role links, or operational destinations are safe to present here.
 */
$scopeContainment = is_array($scope_containment ?? null) ? $scope_containment : [];
$scopeContainmentState = strtolower(trim((string)($scopeContainment['state'] ?? '')));
$scopeContainmentUnavailable = $scopeContainmentState !== ''
    && $scopeContainmentState !== 'unrestricted'
    && ($scopeContainment['records_visible'] ?? true) === false;
?>
<?php if ($scopeContainmentUnavailable): ?>
  <section class="card me-scope-containment" role="status" aria-live="polite">
    <h2><?= e(t('admin.my_work.scope_containment.title')) ?></h2>
    <p class="muted"><?= e(t('admin.my_work.scope_containment.unavailable')) ?></p>
  </section>
<?php endif; ?>

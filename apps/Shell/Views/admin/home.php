<?php
// Shell-owned canonical admin home renderer.
// Orchestrates the admin /me surface by sequencing Shell-owned include files.
// Rendered by AdminSurfaceComposer via the 'shell::admin/home.php' view key.

// admin-surface.css and wrapper-shared.css are now injected by header.php for all admin routes.
?>

<?php

include APP_ROOT . '/apps/Shell/Views/admin/context_prep.php';
include APP_ROOT . '/apps/Shell/Views/admin/workspace_prep.php';
include APP_ROOT . '/apps/Shell/Views/admin/dashboard_prep.php';

?>
<?php include APP_ROOT . '/apps/Shell/Views/admin/dashboard.php'; ?>
<?php include APP_ROOT . '/apps/Shell/Views/admin/row_link_js.php'; ?>
<?php
?>

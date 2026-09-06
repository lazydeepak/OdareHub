<?php
declare(strict_types=1);

// Studio bootstrapping is intentionally empty for runtime integration.
// Route-only Studio capability extensions may register when routes.php supplies a router.
if (isset($router) && is_object($router)) {
    require_once __DIR__ . '/Routes/owner_structure_deletion_approval_routes.php';
    require_once __DIR__ . '/Routes/owner_structure_deletion_execution_request_routes.php';
    require_once __DIR__ . '/Routes/owner_structure_deletion_execution_claim_routes.php';
    require_once __DIR__ . '/Routes/owner_structure_deletion_snapshot_routes.php';
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/LedgerMaintenance.php';

try {
	\Plugins\Ledger\LedgerMaintenance::rebuildFromSources(false);
} catch (\Throwable $e) {
	// Avoid breaking app boot if maintenance hits legacy inconsistencies.
}

if (function_exists('base_register_menus')) {
	$menus = require __DIR__ . '/menu.php';
	base_register_menus($menus);
}

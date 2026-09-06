<?php
declare(strict_types=1);

$shellSource = APP_ROOT . '/apps/Shell/sidebar_sources/core.php';
if (is_file($shellSource)) {
	$items = require $shellSource;
	if (is_array($items)) {
		return $items;
	}
}

return [];

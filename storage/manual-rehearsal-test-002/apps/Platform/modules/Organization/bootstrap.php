<?php
declare(strict_types=1);

if (function_exists('base_register_menus')) {
    $menus = require __DIR__ . '/menu.php';
    base_register_menus($menus);
}

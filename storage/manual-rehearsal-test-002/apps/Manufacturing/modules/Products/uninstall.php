<?php
// Products uninstall hook
// If $PURGE is true, drop tables. Otherwise keep data (disable only).
if (!isset($PURGE)) $PURGE = false;

if ($PURGE) {
    \App\Core\DB::query("DROP TABLE IF EXISTS products");
}

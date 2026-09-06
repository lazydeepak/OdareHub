<?php
if (!isset($PURGE)) {
    $PURGE = false;
}

if ($PURGE) {
    \App\Core\DB::query('DROP TABLE IF EXISTS part_machine_map');
}

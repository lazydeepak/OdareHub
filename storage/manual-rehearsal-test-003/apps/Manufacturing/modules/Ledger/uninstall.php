<?php
declare(strict_types=1);

if (!isset($PURGE)) {
    $PURGE = false;
}

if ($PURGE) {
    \App\Core\DB::query('DROP TABLE IF EXISTS stock_ledger_entries');
}

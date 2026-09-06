<?php
return [
    ['key' => 'inventory.root', 'label_key' => 'nav.inventory', 'label' => 'Inventory', 'url' => null, 'parent' => null, 'order' => 40, 'perm' => null],
    ['key' => 'inventory.ledger', 'label_key' => 'nav.stock_ledger', 'label' => 'Stock Ledger', 'url' => '/ledger', 'parent' => 'inventory.root', 'order' => 10, 'perm' => null],
    ['key' => 'inventory.ledger_add', 'label_key' => 'nav.add_stock_entry', 'label' => 'Add Stock Entry', 'url' => '/ledger/add', 'parent' => 'inventory.root', 'order' => 20, 'perm' => null],
    ['key' => 'inventory.ledger_reconcile', 'label_key' => 'nav.stock_reconciliation', 'label' => 'Stock Reconciliation', 'url' => '/ledger/reconcile', 'parent' => 'inventory.root', 'order' => 30, 'perm' => null],
];

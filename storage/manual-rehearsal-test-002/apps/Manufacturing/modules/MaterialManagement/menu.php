<?php
return [
    ['key' => 'materials.root', 'label_key' => 'nav.materials_supply', 'label' => 'Materials & Supply', 'url' => null, 'parent' => null, 'order' => 38, 'perm' => 'materials.stock.view'],
    ['key' => 'materials.dashboard', 'label_key' => 'nav.material_dashboard', 'label' => 'Materials Workspace', 'url' => '/apps/manufacturing/materials', 'parent' => 'materials.root', 'order' => 10, 'perm' => 'materials.stock.view'],
    ['key' => 'materials.stock', 'label_key' => 'nav.material_stock', 'label' => 'Material Stock', 'url' => '/apps/manufacturing/materials/stock', 'parent' => 'materials.root', 'order' => 20, 'perm' => 'materials.stock.view'],
    ['key' => 'materials.orders', 'label_key' => 'nav.material_orders', 'label' => 'Material Orders', 'url' => '/apps/manufacturing/materials/orders', 'parent' => 'materials.root', 'order' => 30, 'perm' => 'materials.orders.manage'],
    ['key' => 'materials.receipt', 'label_key' => 'nav.material_receipt', 'label' => 'Material Receipt', 'url' => '/apps/manufacturing/materials/receipt', 'parent' => 'materials.root', 'order' => 40, 'perm' => 'materials.orders.manage'],
];

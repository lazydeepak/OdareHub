<?php
declare(strict_types=1);

return [
    'orders' => [
        'create' => ['admin', 'manager'],
        'update' => ['admin', 'manager'],
        'transition' => ['admin', 'manager', 'operator'],
        'view' => ['admin', 'manager', 'operator', 'viewer'],
        'delete' => ['admin'],
        'archive' => ['admin', 'manager'],
        'restore' => ['admin', 'manager'],
    ],
    'parts' => [
        'create' => ['admin', 'manager'],
        'update' => ['admin', 'manager'],
        'view' => ['admin', 'manager', 'operator', 'viewer'],
        'delete' => ['admin'],
        'archive' => ['admin', 'manager'],
        'restore' => ['admin', 'manager'],
    ],
];

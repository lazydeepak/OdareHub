<?php
declare(strict_types=1);

return [
    'orders' => [
        'states' => ['draft', 'approved', 'processing', 'completed', 'cancelled'],
        'transitions' => [
            'draft' => ['approved', 'cancelled'],
            'approved' => ['processing', 'cancelled'],
            'processing' => ['completed'],
        ],
        'transition_roles' => [
            'draft:approved' => ['manager', 'admin'],
            'draft:cancelled' => ['manager', 'admin'],
            'approved:processing' => ['operator', 'admin'],
            'approved:cancelled' => ['manager', 'admin'],
            'processing:completed' => ['operator', 'admin'],
        ],
    ],
];

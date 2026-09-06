<?php
declare(strict_types=1);

/**
 * Shell sidebar ownership config.
 *
 * Shell owns only the stable section taxonomy. Concrete sidebar groups now come
 * from app/module/plugin navigation metadata at runtime.
 */
return [
    'sections' => [
        'operations' => [
            'label' => 'Operations',
            'order' => 10,
        ],
        'apps' => [
            'label' => 'Apps',
            'order' => 20,
        ],
        'admin' => [
            'label' => 'Admin / System',
            'order' => 30,
        ],
    ],

    'groups' => [],
];

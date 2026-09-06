<?php
return static function(): array {
    return [
        'key' => 'admin_tools.summary',
        'title' => 'System Tools',
        'count' => 1,
        'detail' => 'Apps, routes, DB reset, and schema sync',
        'url' => '/admin/apps',
        'visible_if' => 'admin_tools',
        'order' => 12,
    ];
};

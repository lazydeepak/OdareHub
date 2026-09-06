<?php
declare(strict_types=1);
use App\Core\DB;
return static function(): array {
    $active = 0;
    $total  = 0;
    try {
        $active = (int)(DB::fetchOne("SELECT COUNT(*) AS c FROM part_machine_map WHERE is_active=1")['c'] ?? 0);
        $total  = (int)(DB::fetchOne("SELECT COUNT(*) AS c FROM part_machine_map")['c'] ?? 0);
    } catch (\Throwable $e) {}
    return [
        'key'    => 'part_machine_map.summary',
        'title'  => t('nav.part_machine_map'),
        'count'  => $active,
        'detail' => localized_count_phrase('dashboard.active_mappings', $active),
        'extra'  => $total !== $active ? localized_total_phrase($total) : null,
        'url'    => '/part-machine-map',
        'visible_if' => 'admin_strict',
        'order'  => 40,
    ];
};

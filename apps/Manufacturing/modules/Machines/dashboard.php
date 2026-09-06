<?php
declare(strict_types=1);
use App\Core\DB;
return static function(): array {
    $available = 0;
    $total     = 0;
    try {
        $available = (int)(DB::fetchOne("SELECT COUNT(*) AS c FROM machines WHERE is_active=1")['c'] ?? 0);
        $total     = (int)(DB::fetchOne("SELECT COUNT(*) AS c FROM machines")['c'] ?? 0);
    } catch (\Throwable $e) {}
    return [
        'key'    => 'machines.summary',
        'title'  => t('nav.machines'),
        'count'  => $available,
        'detail' => localized_count_phrase('dashboard.active_machines', $available),
        'extra'  => $total !== $available ? localized_total_phrase($total) : null,
        'url'    => '/manufacturing/production-workboard',
        'visible_if' => 'role_machine_leader',
        'order'  => 30,
    ];
};

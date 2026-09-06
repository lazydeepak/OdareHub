<?php
declare(strict_types=1);
use App\Core\DB;
return static function(): array {
    $ready = 0;
    $total = 0;
    try {
        $ready = (int)(DB::fetchOne("SELECT COUNT(*) AS c FROM dispatch_entries WHERE dispatch_status='Ready'")['c'] ?? 0);
        $total = (int)(DB::fetchOne("SELECT COUNT(*) AS c FROM dispatch_entries")['c'] ?? 0);
    } catch (\Throwable $e) {}
    return [
        'key'    => 'dispatch_entries.summary',
        'title'  => t('nav.dispatch_entries'),
        'count'  => $ready,
        'detail' => localized_count_phrase('dashboard.ready_to_dispatch', $ready),
        'extra'  => $total !== $ready ? localized_total_phrase($total) : null,
        'url'    => '/apps/manufacturing/dispatch-ops',
        'visible_if' => 'admin_or_dispatch',
        'order'  => 110,
    ];
};

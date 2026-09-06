<?php
declare(strict_types=1);
use App\Core\DB;
return static function(): array {
    $open  = 0;
    $total = 0;
    try {
        $open  = (int)(DB::fetchOne("SELECT COUNT(*) AS c FROM daily_orders WHERE status='Open'")['c'] ?? 0);
        $total = (int)(DB::fetchOne("SELECT COUNT(*) AS c FROM daily_orders")['c'] ?? 0);
    } catch (\Throwable $e) {}
    return [
        'key'    => 'daily_orders.summary',
        'title'  => t('nav.daily_orders'),
        'count'  => $open,
        'detail' => localized_count_phrase('dashboard.open_orders', $open),
        'extra'  => $total !== $open ? localized_total_phrase($total) : null,
        'url'    => '/daily-orders',
        'visible_if' => 'admin_strict',
        'order'  => 50,
    ];
};

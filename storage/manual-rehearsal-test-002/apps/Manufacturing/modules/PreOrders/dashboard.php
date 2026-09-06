<?php
declare(strict_types=1);
use App\Core\DB;
return static function(): array {
    $pending = 0;
    $total   = 0;
    try {
        $pending = (int)(DB::fetchOne("SELECT COUNT(*) AS c FROM pre_orders WHERE balance_qty > 0")['c'] ?? 0);
        $total   = (int)(DB::fetchOne("SELECT COUNT(*) AS c FROM pre_orders")['c'] ?? 0);
    } catch (\Throwable $e) {}
    return [
        'key'    => 'pre_orders.summary',
        'title'  => t('nav.pre_orders'),
        'count'  => $pending,
        'detail' => localized_count_phrase('dashboard.with_balance', $pending),
        'extra'  => $total !== $pending ? localized_total_phrase($total) : null,
        'url'    => '/pre-orders',
        'visible_if' => 'admin_strict',
        'order'  => 60,
    ];
};

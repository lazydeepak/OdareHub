<?php
declare(strict_types=1);
use App\Core\DB;
return static function(): array {
    $active = 0;
    $total  = 0;
    try {
        $active = (int)(DB::fetchOne("SELECT COUNT(*) AS c FROM products WHERE is_active=1")['c'] ?? 0);
        $total  = (int)(DB::fetchOne("SELECT COUNT(*) AS c FROM products")['c'] ?? 0);
    } catch (\Throwable $e) {}
    return [
        'key'    => 'products.summary',
        'title'  => t('nav.parts_master'),
        'count'  => $active,
        'detail' => localized_count_phrase('dashboard.active_parts', $active),
        'extra'  => $total !== $active ? localized_total_phrase($total) : null,
        'url'    => '/products',
        'visible_if' => 'admin_strict',
        'order'  => 20,
    ];
};

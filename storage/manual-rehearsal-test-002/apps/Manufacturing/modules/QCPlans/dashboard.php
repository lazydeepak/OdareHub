<?php
declare(strict_types=1);
use App\Core\DB;
return static function(): array {
    $open  = 0;
    $total = 0;
    try {
        $open  = (int)(DB::fetchOne("SELECT COUNT(*) AS c FROM qc_plans WHERE status='Open'")['c'] ?? 0);
        $total = (int)(DB::fetchOne("SELECT COUNT(*) AS c FROM qc_plans")['c'] ?? 0);
    } catch (\Throwable $e) {}
    return [
        'key'    => 'qc_plans.summary',
        'title'  => t('nav.qc_plans'),
        'count'  => $open,
        'detail' => localized_count_phrase('dashboard.open_orders', $open),
        'extra'  => $total !== $open ? localized_total_phrase($total) : null,
        'url'    => '/qc-plans',
        'visible_if' => 'admin_strict',
        'order'  => 90,
    ];
};

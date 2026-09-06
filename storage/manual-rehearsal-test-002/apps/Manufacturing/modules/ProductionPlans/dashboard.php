<?php
declare(strict_types=1);
use App\Core\DB;
return static function(): array {
    $planned = 0;
    $total   = 0;
    try {
        $planned = (int)(DB::fetchOne("SELECT COUNT(*) AS c FROM production_plans WHERE status='Planned'")['c'] ?? 0);
        $total   = (int)(DB::fetchOne("SELECT COUNT(*) AS c FROM production_plans")['c'] ?? 0);
    } catch (\Throwable $e) {}
    return [
        'key'    => 'production_plans.summary',
        'title'  => t('nav.production_plans'),
        'count'  => $planned,
        'detail' => localized_count_phrase('dashboard.planned', $planned),
        'extra'  => $total !== $planned ? localized_total_phrase($total) : null,
        'url'    => '/production-plans',
        'visible_if' => 'admin_strict',
        'order'  => 70,
    ];
};
